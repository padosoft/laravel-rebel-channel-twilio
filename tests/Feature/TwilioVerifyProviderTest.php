<?php

declare(strict_types=1);

use Padosoft\Rebel\Channel\Twilio\Contracts\TwilioVerifyGateway;
use Padosoft\Rebel\Channel\Twilio\Testing\FakeTwilioVerifyGateway;
use Padosoft\Rebel\Channel\Twilio\Verification\TwilioVerifyProvider;
use Padosoft\Rebel\Channels\Enums\Channel;
use Padosoft\Rebel\Core\Context\SecurityContext;
use Padosoft\Rebel\Core\Identifiers\PhoneIdentifier;

function ctx(): SecurityContext
{
    return new SecurityContext('req-1');
}

it('starts a verification and approves the correct code', function (): void {
    $provider = new TwilioVerifyProvider(new FakeTwilioVerifyGateway('123456'));
    $phone = PhoneIdentifier::from('+393331234567');

    $start = $provider->start($phone, Channel::Sms, ctx());
    expect($start->pending())->toBeTrue()
        ->and($start->provider)->toBe('twilio')
        ->and($start->reference)->toStartWith('VE');

    expect($provider->check($phone, '123456', $start->reference, ctx())->approved())->toBeTrue()
        ->and($provider->check($phone, '000000', $start->reference, ctx())->failed())->toBeTrue();
});

it('supports sms, whatsapp and voice', function (): void {
    $provider = new TwilioVerifyProvider(new FakeTwilioVerifyGateway);

    expect($provider->supports(Channel::Sms))->toBeTrue()
        ->and($provider->supports(Channel::WhatsApp))->toBeTrue()
        ->and($provider->supports(Channel::Voice))->toBeTrue();
});

it('only advertises the configured channels', function (): void {
    $provider = new TwilioVerifyProvider(new FakeTwilioVerifyGateway, ['sms']);

    expect($provider->supports(Channel::Sms))->toBeTrue()
        ->and($provider->supports(Channel::WhatsApp))->toBeFalse();
});

it('does not approve a code for a recipient that never started a verification', function (): void {
    $provider = new TwilioVerifyProvider(new FakeTwilioVerifyGateway('123456'));
    $provider->start(PhoneIdentifier::from('+393331111111'), Channel::Sms, ctx());

    // Correct code, but a DIFFERENT recipient → must not approve.
    expect($provider->check(PhoneIdentifier::from('+393332222222'), '123456', null, ctx())->approved())->toBeFalse();
});

it('treats an unexpected start status as a failure (not a bogus pending)', function (): void {
    $gateway = new class implements TwilioVerifyGateway
    {
        public function startVerification(string $to, string $channel): array
        {
            return ['sid' => 'VE1', 'status' => 'canceled'];
        }

        public function checkVerification(string $to, string $code): string
        {
            return 'canceled';
        }
    };

    $result = (new TwilioVerifyProvider($gateway))->start(PhoneIdentifier::from('+393331234567'), Channel::Sms, ctx());

    expect($result->failed())->toBeTrue()
        ->and($result->reason)->toBe('provider_status');
});

it('returns a provider_error (not an exception) when Twilio is down', function (): void {
    $provider = new TwilioVerifyProvider(new FakeTwilioVerifyGateway('123456', healthy: false));
    $phone = PhoneIdentifier::from('+393331234567');

    expect($provider->start($phone, Channel::Sms, ctx())->reason)->toBe('provider_error')
        ->and($provider->check($phone, '123456', null, ctx())->reason)->toBe('provider_error');
});
