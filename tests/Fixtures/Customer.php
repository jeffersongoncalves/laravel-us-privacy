<?php

namespace JeffersonGoncalves\UsPrivacy\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\UsPrivacy\Attributes\PersonalData;
use JeffersonGoncalves\UsPrivacy\Concerns\InteractsWithPersonalData;
use JeffersonGoncalves\UsPrivacy\Contracts\HasPersonalData;
use JeffersonGoncalves\UsPrivacy\Enums\AnonymizeStrategy;
use JeffersonGoncalves\UsPrivacy\Enums\DataCategory;
use JeffersonGoncalves\UsPrivacy\Enums\SensitiveType;

#[PersonalData('name', purpose: 'Identification', anonymize: AnonymizeStrategy::Mask)]
#[PersonalData('email', retention: '5 years', anonymize: AnonymizeStrategy::Hash, targetedAdvertising: true)]
#[PersonalData('health_notes', sensitiveType: SensitiveType::Health)]
#[PersonalData('tax_id', category: DataCategory::Sensitive, anonymize: AnonymizeStrategy::Keep)]
class Customer extends Model implements HasPersonalData
{
    use InteractsWithPersonalData;

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['health_notes' => 'encrypted'];
    }
}
