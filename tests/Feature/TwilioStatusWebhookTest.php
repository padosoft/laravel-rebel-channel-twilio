<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Padosoft\Rebel\Channel\Twilio\Http\TwilioSignatureValidator;
use Padosoft\Rebel\Core\Audit\AuditEvent;
use Padosoft\Rebel\Core\Contracts\AuditLogger;
use Twilio\Security\RequestValidator;

/**
 * In-memory AuditLogger so we can assert exactly what the webhook records
 * without depending on the DB layer.
 */
function fakeAudit(): AuditLogger
{
    $logger = new class implements AuditLogger
    {
        /** @var list<AuditEvent> */
        public array $events = [];

        public function record(AuditEvent $event): void
        {
            $this->events[] = $event;
        }
    };

    app()->instance(AuditLogger::class, $logger);

    return $logger;
}

beforeEach(function (): void {
    // Default: disable signature validation so we exercise the happy path; individual
    // tests that care about signatures flip it back on.
    config()->set('rebel-channel-twilio.webhook.validate_signature', false);
});

it('registers the status route named rebel-twilio.status at the configured path', function (): void {
    expect(Route::has('rebel-twilio.status'))->toBeTrue();

    $route = Route::getRoutes()->getByName('rebel-twilio.status');
    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe('rebel/twilio/status')
        ->and($route?->methods())->toContain('POST');
});

it('records channel.verification.delivered with the absolute price in metadata', function (): void {
    $audit = fakeAudit();

    $response = $this->post('rebel/twilio/status', [
        'MessageStatus' => 'delivered',
        'MessageSid' => 'SM123',
        'To' => '+393331234567',
        'Price' => '-0.0075',
        'PriceUnit' => 'USD',
    ]);

    $response->assertNoContent();

    expect($audit->events)->toHaveCount(1);
    $event = $audit->events[0];

    expect($event->type)->toBe('channel.verification.delivered')
        ->and($event->provider)->toBe('twilio')
        ->and($event->channel)->toBe('sms')
        ->and($event->identifierHmac)->not->toBeNull()
        ->and($event->identifierHmac)->not->toBe('+393331234567')
        ->and($event->keyVersion)->toBe(1)
        ->and($event->metadata['message_status'])->toBe('delivered')
        ->and($event->metadata['price'])->toBe(0.0075)
        ->and($event->metadata['price_unit'])->toBe('USD')
        ->and($event->metadata['message_sid'])->toBe('SM123')
        ->and($event->metadata['error_code'])->toBeNull();
});

it('records channel.verification.undelivered for a failed callback with the error code', function (): void {
    $audit = fakeAudit();

    $response = $this->post('rebel/twilio/status', [
        'MessageStatus' => 'undelivered',
        'MessageSid' => 'SM999',
        'To' => '+393339999999',
        'ErrorCode' => '30008',
    ]);

    $response->assertNoContent();

    expect($audit->events)->toHaveCount(1)
        ->and($audit->events[0]->type)->toBe('channel.verification.undelivered')
        ->and($audit->events[0]->metadata['error_code'])->toBe('30008')
        ->and($audit->events[0]->metadata['message_sid'])->toBe('SM999')
        ->and($audit->events[0]->metadata['price'])->toBeNull();
});

it('treats failed as undelivered as well', function (): void {
    $audit = fakeAudit();

    $this->post('rebel/twilio/status', [
        'MessageStatus' => 'failed',
        'To' => '+393331112222',
    ])->assertNoContent();

    expect($audit->events[0]->type)->toBe('channel.verification.undelivered');
});

it('records channel.verification.dispatched for queued/sent statuses', function (): void {
    $audit = fakeAudit();

    $this->post('rebel/twilio/status', [
        'MessageStatus' => 'sent',
        'To' => '+393334445555',
    ])->assertNoContent();

    expect($audit->events)->toHaveCount(1)
        ->and($audit->events[0]->type)->toBe('channel.verification.dispatched');
});

it('reads Verify-style Status and Sid fields and maps the channel from the payload', function (): void {
    $audit = fakeAudit();

    $this->post('rebel/twilio/status', [
        'Status' => 'delivered',
        'Sid' => 'VE321',
        'To' => '+393336667777',
        'Channel' => 'whatsapp',
    ])->assertNoContent();

    expect($audit->events[0]->type)->toBe('channel.verification.delivered')
        ->and($audit->events[0]->channel)->toBe('whatsapp')
        ->and($audit->events[0]->metadata['message_sid'])->toBe('VE321');
});

it('acknowledges a malformed payload with 204 and records nothing', function (): void {
    $audit = fakeAudit();

    // No status, no recipient.
    $this->post('rebel/twilio/status', ['foo' => 'bar'])->assertNoContent();
    // Recipient but no status.
    $this->post('rebel/twilio/status', ['To' => '+393330000000'])->assertNoContent();
    // Status but no recipient.
    $this->post('rebel/twilio/status', ['MessageStatus' => 'delivered'])->assertNoContent();

    expect($audit->events)->toBeEmpty();
});

it('never stores the raw phone number (identifier is HMAC of To)', function (): void {
    $audit = fakeAudit();

    $this->post('rebel/twilio/status', [
        'MessageStatus' => 'delivered',
        'To' => '+393331234567',
    ])->assertNoContent();

    $hmac = $audit->events[0]->identifierHmac;
    expect($hmac)->not->toBeNull()
        ->and(str_contains((string) $hmac, '393331234567'))->toBeFalse();
});

it('rejects a bad signature with 403 when validation is enabled', function (): void {
    config()->set('rebel-channel-twilio.webhook.validate_signature', true);
    $audit = fakeAudit();

    $response = $this->call(
        'POST',
        'rebel/twilio/status',
        ['MessageStatus' => 'delivered', 'To' => '+393331234567'],
        [],
        [],
        ['HTTP_X_TWILIO_SIGNATURE' => 'totally-bogus'],
    );

    $response->assertForbidden();
    expect($audit->events)->toBeEmpty();
});

it('rejects a missing signature with 403 when validation is enabled', function (): void {
    config()->set('rebel-channel-twilio.webhook.validate_signature', true);
    $audit = fakeAudit();

    $this->post('rebel/twilio/status', [
        'MessageStatus' => 'delivered',
        'To' => '+393331234567',
    ])->assertForbidden();

    expect($audit->events)->toBeEmpty();
});

it('accepts a valid signature when validation is enabled', function (): void {
    config()->set('rebel-channel-twilio.webhook.validate_signature', true);
    $audit = fakeAudit();

    $authToken = (string) config('rebel-channel-twilio.auth_token');
    $params = ['MessageStatus' => 'delivered', 'To' => '+393331234567'];
    $url = url('rebel/twilio/status');

    $signature = (new RequestValidator($authToken))->computeSignature($url, $params);

    $response = $this->call(
        'POST',
        'rebel/twilio/status',
        $params,
        [],
        [],
        ['HTTP_X_TWILIO_SIGNATURE' => $signature],
    );

    $response->assertNoContent();
    expect($audit->events)->toHaveCount(1)
        ->and($audit->events[0]->type)->toBe('channel.verification.delivered');
});

it('returns false from the signature validator when the auth token is empty', function (): void {
    $validator = new TwilioSignatureValidator('');
    $request = Request::create('https://example.test/rebel/twilio/status', 'POST', ['To' => '+1']);
    $request->headers->set('X-Twilio-Signature', 'anything');

    expect($validator->isValid($request))->toBeFalse();
});
