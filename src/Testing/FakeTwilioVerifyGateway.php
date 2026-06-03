<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Twilio\Testing;

use Padosoft\Rebel\Channel\Twilio\Contracts\TwilioVerifyGateway;
use RuntimeException;

/**
 * Deterministic {@see TwilioVerifyGateway} for tests: records started verifications,
 * approves a fixed expected code, and can simulate an API outage.
 */
final class FakeTwilioVerifyGateway implements TwilioVerifyGateway
{
    /** @var list<array{to: string, channel: string}> */
    public array $started = [];

    public function __construct(
        private readonly string $expectedCode = '123456',
        private readonly bool $healthy = true,
    ) {}

    public function startVerification(string $to, string $channel): array
    {
        if (! $this->healthy) {
            throw new RuntimeException('twilio unavailable');
        }

        $this->started[] = ['to' => $to, 'channel' => $channel];

        return ['sid' => 'VE'.str_pad((string) count($this->started), 8, '0', STR_PAD_LEFT), 'status' => 'pending'];
    }

    public function checkVerification(string $to, string $code): string
    {
        if (! $this->healthy) {
            throw new RuntimeException('twilio unavailable');
        }

        // Scope the check to a recipient that actually started a verification, mirroring
        // Twilio: a check for a number with no pending verification is 'canceled'.
        $startedForRecipient = array_filter($this->started, fn (array $v): bool => $v['to'] === $to);
        if ($startedForRecipient === []) {
            return 'canceled';
        }

        return hash_equals($this->expectedCode, $code) ? 'approved' : 'pending';
    }
}
