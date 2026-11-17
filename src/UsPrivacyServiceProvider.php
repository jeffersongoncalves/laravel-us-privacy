<?php

namespace JeffersonGoncalves\UsPrivacy;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class UsPrivacyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-us-privacy')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations();
    }
}
