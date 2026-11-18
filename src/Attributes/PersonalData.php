<?php

namespace JeffersonGoncalves\UsPrivacy\Attributes;

use Attribute;
use JeffersonGoncalves\UsPrivacy\Enums\AnonymizeStrategy;
use JeffersonGoncalves\UsPrivacy\Enums\DataCategory;
use JeffersonGoncalves\UsPrivacy\Enums\SensitiveType;

/**
 * Declares one column of an Eloquent model as personal data. sold, targetedAdvertising and
 * profiling flag the processing every state law lets consumers opt out of.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class PersonalData
{
    public function __construct(
        public string $column,
        public DataCategory $category = DataCategory::Personal,
        public ?string $purpose = null,
        public ?SensitiveType $sensitiveType = null,
        public ?string $retention = null,
        public AnonymizeStrategy $anonymize = AnonymizeStrategy::Nullify,
        public bool $sold = false,
        public bool $targetedAdvertising = false,
        public bool $profiling = false,
    ) {}

    /**
     * @return array{column: string, category: string, purpose: string|null, sensitive_type: string|null, retention: string|null, anonymize: string, sold: bool, targeted_advertising: bool, profiling: bool}
     */
    public function toArray(): array
    {
        return [
            'column' => $this->column,
            // A sensitive type makes the column sensitive data even without the category.
            'category' => $this->sensitiveType === null ? $this->category->value : DataCategory::Sensitive->value,
            'purpose' => $this->purpose,
            'sensitive_type' => $this->sensitiveType?->value,
            'retention' => $this->retention,
            'anonymize' => $this->anonymize->value,
            'sold' => $this->sold,
            'targeted_advertising' => $this->targetedAdvertising,
            'profiling' => $this->profiling,
        ];
    }
}
