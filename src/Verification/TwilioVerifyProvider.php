<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Twilio\Verification;

use Padosoft\Rebel\Channel\Twilio\Contracts\TwilioVerifyGateway;
use Padosoft\Rebel\Channels\Contracts\VerificationProvider;
use Padosoft\Rebel\Channels\Enums\Channel;
use Padosoft\Rebel\Channels\Results\VerificationResult;
use Padosoft\Rebel\Core\Context\SecurityContext;
use Padosoft\Rebel\Core\Identifiers\PhoneIdentifier;

/**
 * Twilio Verify implementation of the Rebel Channels {@see VerificationProvider}.
 * It maps Rebel channels to Twilio Verify channels (voice → 'call') and never throws
 * out: any SDK/transport error becomes a generic `provider_error` failure so the
 * router can fall back to another provider.
 */
final class TwilioVerifyProvider implements VerificationProvider
{
    /**
     * @param  list<string>  $supported  Rebel channel values this provider may handle
     */
    public function __construct(
        private readonly TwilioVerifyGateway $gateway,
        private readonly array $supported = ['sms', 'whatsapp', 'voice'],
    ) {}

    public function key(): string
    {
        return 'twilio';
    }

    public function supports(Channel $channel): bool
    {
        return in_array($channel->value, $this->supported, true);
    }

    public function start(PhoneIdentifier $phone, Channel $channel, SecurityContext $context): VerificationResult
    {
        try {
            $result = $this->gateway->startVerification($phone->normalized(), $this->twilioChannel($channel));
        } catch (\Throwable) {
            return VerificationResult::fail('provider_error', 'twilio');
        }

        // Map Twilio Verify statuses explicitly: only 'pending' is a live challenge;
        // anything unexpected (canceled, max_attempts_reached, …) is a failure, not a
        // bogus "started" with a stale reference.
        return match ($result['status']) {
            'approved' => VerificationResult::approve('twilio'),
            'pending' => VerificationResult::started('twilio', $result['sid']),
            default => VerificationResult::fail('provider_status', 'twilio'),
        };
    }

    public function check(PhoneIdentifier $phone, string $code, ?string $reference, SecurityContext $context): VerificationResult
    {
        try {
            $status = $this->gateway->checkVerification($phone->normalized(), $code);
        } catch (\Throwable) {
            return VerificationResult::fail('provider_error', 'twilio');
        }

        return $status === 'approved'
            ? VerificationResult::approve('twilio')
            : VerificationResult::deny('twilio', 'not_approved');
    }

    private function twilioChannel(Channel $channel): string
    {
        return match ($channel) {
            Channel::Sms => 'sms',
            Channel::WhatsApp => 'whatsapp',
            Channel::Voice => 'call',
        };
    }
}
