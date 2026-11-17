<?php

namespace JeffersonGoncalves\UsPrivacy\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JeffersonGoncalves\UsPrivacy\UsPrivacy
 */
class UsPrivacy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-us-privacy';
    }
}
