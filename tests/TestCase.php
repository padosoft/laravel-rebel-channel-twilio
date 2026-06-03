<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Twilio\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Padosoft\Rebel\Channel\Twilio\Contracts\TwilioVerifyGateway;
use Padosoft\Rebel\Channel\Twilio\RebelTwilioServiceProvider;
use Padosoft\Rebel\Channel\Twilio\Testing\FakeTwilioVerifyGateway;
use Padosoft\Rebel\Channels\RebelChannelsServiceProvider;
use Padosoft\Rebel\Core\RebelCoreServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            RebelCoreServiceProvider::class,
            RebelChannelsServiceProvider::class,
            RebelTwilioServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('rebel-core.peppers', [1 => 'test-pepper']);
        $app['config']->set('rebel-core.pepper_current', 1);
        $app['config']->set('cache.default', 'array');

        // Dummy credentials so the provider registers, plus a fake gateway so no real
        // Twilio call is made in the offline suite.
        $app['config']->set('rebel-channel-twilio.account_sid', 'AC_test');
        $app['config']->set('rebel-channel-twilio.auth_token', 'token_test');
        $app['config']->set('rebel-channel-twilio.verify_service_sid', 'VA_test');

        $app->instance(TwilioVerifyGateway::class, new FakeTwilioVerifyGateway('123456'));
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/padosoft/laravel-rebel-core/database/migrations');
    }
}
