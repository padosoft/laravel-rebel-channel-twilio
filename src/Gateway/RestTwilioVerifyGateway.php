<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Twilio\Gateway;

use Padosoft\Rebel\Channel\Twilio\Contracts\TwilioVerifyGateway;
use Twilio\Rest\Client;

/**
 * Real {@see TwilioVerifyGateway} backed by the Twilio SDK's Verify v2 API.
 */
final class RestTwilioVerifyGateway implements TwilioVerifyGateway
{
    public function __construct(
        private readonly Client $client,
        private readonly string $serviceSid,
    ) {}

    public function startVerification(string $to, string $channel): array
    {
        $verification = $this->client->verify->v2
            ->services($this->serviceSid)
            ->verifications
            ->create($to, $channel);

        return [
            'sid' => $this->toString($verification->sid),
            'status' => $this->toString($verification->status),
        ];
    }

    public function checkVerification(string $to, string $code): string
    {
        $check = $this->client->verify->v2
            ->services($this->serviceSid)
            ->verificationChecks
            ->create(['to' => $to, 'code' => $code]);

        return $this->toString($check->status);
    }

    private function toString(mixed $value): string
    {
        // Fail loudly on an unexpected (non-scalar) shape rather than silently returning
        // '' — the provider wraps this in a try/catch and turns it into a clean
        // 'provider_error' so the router can fall back.
        if (! is_scalar($value)) {
            throw new \RuntimeException('Unexpected non-scalar value returned by the Twilio API.');
        }

        return (string) $value;
    }
}
