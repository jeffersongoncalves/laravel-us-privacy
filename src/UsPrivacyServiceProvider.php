<?php

namespace JeffersonGoncalves\UsPrivacy;

use JeffersonGoncalves\UsPrivacy\Commands\InventoryCommand;
use JeffersonGoncalves\UsPrivacy\Commands\KAnonymityCommand;
use JeffersonGoncalves\UsPrivacy\Commands\StatesCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class UsPrivacyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('us-privacy')
            ->hasConfigFile()
            ->hasCommands([InventoryCommand::class, KAnonymityCommand::class, StatesCommand::class]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(UsPrivacy::class);
    }
}
