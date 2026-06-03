<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Twilio\Http\Controllers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Padosoft\Rebel\Channel\Twilio\Http\TwilioSignatureValidator;
use Padosoft\Rebel\Core\Audit\AuditEvent;
use Padosoft\Rebel\Core\Contracts\AuditLogger;
use Padosoft\Rebel\Core\Contracts\KeyedHasher;

/**
 * Receives Twilio delivery-status callbacks (Messaging `StatusCallback` and
 * Verify status webhooks share the same shape) and records a Rebel audit event
 * so the admin panel's Channel Performance can show real delivered / failed /
 * cost figures.
 *
 * Twilio posts server-to-server, so there is no user session to authenticate.
 * Instead we (optionally) verify the `X-Twilio-Signature` header. The endpoint
 * is deliberately defensive: a malformed or empty callback is acknowledged with
 * a 204 and recorded nothing — Twilio retries on non-2xx, and we never want a
 * bad payload to 500 (which would trigger pointless retries / alerting).
 */
final class TwilioStatusController
{
    /** Twilio statuses that mean the message reached the handset. */
    private const DELIVERED = ['delivered'];

    /** Twilio statuses that mean delivery failed. */
    private const UNDELIVERED = ['undelivered', 'failed'];

    public function __invoke(
        Request $request,
        Repository $config,
        AuditLogger $audit,
        KeyedHasher $hasher,
    ): Response {
        if ($this->signatureRequired($config) && ! $this->signatureValid($request, $config)) {
            // 403 (not 204): a forged/unsigned callback is a security signal, and we
            // do NOT want Twilio to treat it as accepted.
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $status = $this->status($request);
        $to = $this->stringField($request, 'To');

        // Nothing we can attribute → acknowledge and drop (never 500 on junk).
        if ($status === '' || $to === '') {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $hash = $hasher->hash($to);

        $audit->record(new AuditEvent(
            type: $this->eventType($status),
            identifierHmac: $hash->hash,
            keyVersion: $hash->keyVersion,
            channel: $this->channel($request),
            provider: 'twilio',
            metadata: [
                'message_status' => $status,
                'price' => $this->price($request),
                'price_unit' => $this->nullableField($request, 'PriceUnit'),
                'message_sid' => $this->messageSid($request),
                'error_code' => $this->nullableField($request, 'ErrorCode'),
            ],
        ));

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function eventType(string $status): string
    {
        if (in_array($status, self::DELIVERED, true)) {
            return 'channel.verification.delivered';
        }

        if (in_array($status, self::UNDELIVERED, true)) {
            return 'channel.verification.undelivered';
        }

        // queued / sent / accepted / sending / … — an in-flight dispatch.
        return 'channel.verification.dispatched';
    }

    /** Messaging sends `MessageStatus`; Verify sends `Status`. Normalise to lower-case. */
    private function status(Request $request): string
    {
        $status = $this->stringField($request, 'MessageStatus');
        if ($status === '') {
            $status = $this->stringField($request, 'Status');
        }

        return strtolower($status);
    }

    /** Messaging sends `MessageSid`; Verify sends `Sid`. */
    private function messageSid(Request $request): ?string
    {
        return $this->nullableField($request, 'MessageSid')
            ?? $this->nullableField($request, 'Sid');
    }

    /**
     * Twilio quotes prices as a NEGATIVE string (e.g. "-0.0075") because it is a
     * debit. We store the absolute value so the admin can sum spend directly.
     * Null/empty price stays null.
     */
    private function price(Request $request): ?float
    {
        $raw = $this->nullableField($request, 'Price');

        if ($raw === null || ! is_numeric($raw)) {
            return null;
        }

        return abs((float) $raw);
    }

    /**
     * Map the Twilio channel when present. Messaging callbacks don't send one;
     * SMS is the sensible default for this provider.
     */
    private function channel(Request $request): string
    {
        $channel = strtolower($this->stringField($request, 'Channel'));

        return $channel !== '' ? $channel : 'sms';
    }

    private function signatureRequired(Repository $config): bool
    {
        return $config->get('rebel-channel-twilio.webhook.validate_signature', true) === true;
    }

    private function signatureValid(Request $request, Repository $config): bool
    {
        $authToken = $config->get('rebel-channel-twilio.auth_token');

        return (new TwilioSignatureValidator(is_string($authToken) ? $authToken : ''))->isValid($request);
    }

    private function stringField(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function nullableField(Request $request, string $key): ?string
    {
        $value = $this->stringField($request, $key);

        return $value === '' ? null : $value;
    }
}
