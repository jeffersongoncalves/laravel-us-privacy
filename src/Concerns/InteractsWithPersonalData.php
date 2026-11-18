<?php

namespace JeffersonGoncalves\UsPrivacy\Concerns;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\Privacy\Concerns\InteractsWithPersonalData as BaseInteractsWithPersonalData;
use JeffersonGoncalves\UsPrivacy\Attributes\PersonalData;
use JeffersonGoncalves\UsPrivacy\Events\PersonalDataAnonymized;

/**
 * Default HasPersonalData implementation for Eloquent models, driven by #[PersonalData] attributes.
 * Reads only this suite's declarations and fires this suite's PersonalDataAnonymized.
 *
 * @mixin Model
 */
trait InteractsWithPersonalData
{
    use BaseInteractsWithPersonalData;

    protected static function personalDataAttribute(): string
    {
        return PersonalData::class;
    }

    protected static function personalDataAnonymizedEvent(): string
    {
        return PersonalDataAnonymized::class;
    }
}
