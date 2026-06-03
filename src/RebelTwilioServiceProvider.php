<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Channel\Twilio;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Skeleton iniziale di padosoft/laravel-rebel-channel-twilio. Implementazione in arrivo.
 */
final class RebelTwilioServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-rebel-channel-twilio');
    }
}
