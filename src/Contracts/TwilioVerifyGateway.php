<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Twilio\Contracts;

/**
 * Thin seam over Twilio's Verify API so the verification provider stays fully
 * unit-testable offline. The real implementation wraps the Twilio SDK; a fake ships
 * for tests, and the live test-suite uses the real one against the actual API.
 */
interface TwilioVerifyGateway
{
    /**
     * Start a verification. Returns the verification SID and its status.
     *
     * @param  string  $channel  Twilio channel: 'sms' | 'whatsapp' | 'call'
     * @return array{sid: string, status: string}
     */
    public function startVerification(string $to, string $channel): array;

    /**
     * Check a code. Returns the Twilio verification-check status
     * (e.g. 'approved', 'pending', 'canceled').
     */
    public function checkVerification(string $to, string $code): string;
}
