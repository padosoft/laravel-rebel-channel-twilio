<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Twilio;

use Illuminate\Contracts\Config\Repository;
use Padosoft\Rebel\Channel\Twilio\Contracts\TwilioVerifyGateway;
use Padosoft\Rebel\Channel\Twilio\Gateway\RestTwilioVerifyGateway;
use Padosoft\Rebel\Channel\Twilio\Verification\TwilioVerifyProvider;
use Padosoft\Rebel\Channels\Routing\ProviderRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Twilio\Rest\Client;

/**
 * Registers the Twilio Verify provider into the Rebel Channels registry (when
 * credentials are configured) and binds the Twilio gateway.
 *
 * Credentials are read lazily: the package installs cleanly with no Twilio config, and
 * the provider simply does not register until you set the three TWILIO_* values.
 */
final class RebelTwilioServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-rebel-channel-twilio')
            ->hasConfigFile('rebel-channel-twilio');
    }

    public function packageBooted(): void
    {
        $config = $this->app->make(Repository::class);

        // No credentials → nothing Twilio-backed is wired (an unauthenticated Client is
        // never constructed in the container).
        if (! $this->hasCredentials($config)) {
            return;
        }

        // Bind the real gateway only when not already bound (so a test can bind a fake first).
        if (! $this->app->bound(TwilioVerifyGateway::class)) {
            $this->app->singleton(TwilioVerifyGateway::class, function () use ($config): RestTwilioVerifyGateway {
                return new RestTwilioVerifyGateway(
                    new Client($this->stringConfig($config, 'account_sid'), $this->stringConfig($config, 'auth_token')),
                    $this->stringConfig($config, 'verify_service_sid'),
                );
            });
        }

        if ($config->get('rebel-channel-twilio.register_provider', true) === true && class_exists(ProviderRegistry::class)) {
            $this->app->make(ProviderRegistry::class)->register(
                new TwilioVerifyProvider($this->app->make(TwilioVerifyGateway::class), $this->channels($config)),
            );
        }
    }

    private function hasCredentials(Repository $config): bool
    {
        return $this->stringConfig($config, 'account_sid') !== ''
            && $this->stringConfig($config, 'auth_token') !== ''
            && $this->stringConfig($config, 'verify_service_sid') !== '';
    }

    /**
     * @return list<string>
     */
    private function channels(Repository $config): array
    {
        $default = ['sms', 'whatsapp', 'voice'];
        $value = $config->get('rebel-channel-twilio.channels');

        if (! is_array($value)) {
            return $default;
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        // A misconfigured (all-empty) list would register a provider that supports nothing;
        // fall back to the defaults so the provider stays useful.
        return $out === [] ? $default : $out;
    }

    private function stringConfig(Repository $config, string $key): string
    {
        $value = $config->get("rebel-channel-twilio.{$key}");

        return is_string($value) ? $value : '';
    }
}
