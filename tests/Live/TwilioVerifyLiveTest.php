<?php

declare(strict_types=1);

use Padosoft\Rebel\Channel\Twilio\Gateway\RestTwilioVerifyGateway;
use Twilio\Rest\Client;

/**
 * LIVE tests: they hit the real Twilio Verify API and SEND A REAL MESSAGE.
 *
 * They run ONLY when you explicitly opt in with REBEL_TWILIO_LIVE=1 AND all credentials
 * (TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_VERIFY_SERVICE_SID, TWILIO_TEST_PHONE)
 * are present — otherwise they self-skip, so the offline suite and external PRs never
 * trigger a send. In CI, provide the values as secrets and set REBEL_TWILIO_LIVE=1.
 */
function liveEnv(string $key): string
{
    $value = getenv($key);

    return is_string($value) ? $value : '';
}

beforeEach(function (): void {
    if (liveEnv('REBEL_TWILIO_LIVE') !== '1') {
        test()->markTestSkipped('Live Twilio tests are opt-in (set REBEL_TWILIO_LIVE=1).');
    }

    foreach (['TWILIO_ACCOUNT_SID', 'TWILIO_AUTH_TOKEN', 'TWILIO_VERIFY_SERVICE_SID', 'TWILIO_TEST_PHONE'] as $key) {
        if (liveEnv($key) === '') {
            test()->markTestSkipped("Live Twilio credentials absent ({$key}).");
        }
    }
});

it('starts a real verification via Twilio Verify', function (): void {
    $gateway = new RestTwilioVerifyGateway(
        new Client(liveEnv('TWILIO_ACCOUNT_SID'), liveEnv('TWILIO_AUTH_TOKEN')),
        liveEnv('TWILIO_VERIFY_SERVICE_SID'),
    );

    $result = $gateway->startVerification(liveEnv('TWILIO_TEST_PHONE'), 'sms');

    expect($result['sid'])->toStartWith('VE')
        ->and($result['status'])->toBeIn(['pending', 'approved']);
})->group('live');
