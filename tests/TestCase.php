<?php

namespace JeffersonGoncalves\UsPrivacy\Tests;

use JeffersonGoncalves\UsPrivacy\UsPrivacyServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            UsPrivacyServiceProvider::class,
        ];
    }
}
