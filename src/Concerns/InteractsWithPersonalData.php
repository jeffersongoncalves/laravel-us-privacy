<?php

namespace JeffersonGoncalves\UsPrivacy\Concerns;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\UsPrivacy\Attributes\PersonalData;
use JeffersonGoncalves\UsPrivacy\Enums\AnonymizeStrategy;
use JeffersonGoncalves\UsPrivacy\Events\PersonalDataAnonymized;
use ReflectionClass;
use RuntimeException;

/**
 * Default HasPersonalData implementation for Eloquent models, driven by #[PersonalData] attributes.
 *
 * @mixin Model
 */
trait InteractsWithPersonalData
{
    /**
     * @return list<PersonalData>
     */
    public static function personalDataFields(): array
    {
        /** @var array<class-string, list<PersonalData>> $cache */
        static $cache = [];

        return $cache[static::class] ??= array_map(
            fn (\ReflectionAttribute $attribute): PersonalData => $attribute->newInstance(),
            (new ReflectionClass(static::class))->getAttributes(PersonalData::class),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function exportPersonalData(): array
    {
        $data = [];

        foreach (static::personalDataFields() as $field) {
            $data[$field->column] = $this->getAttribute($field->column);
        }

        return $data;
    }

    public function anonymizePersonalData(): void
    {
        $columns = [];

        foreach (static::personalDataFields() as $field) {
            if ($field->anonymize === AnonymizeStrategy::Keep) {
                continue;
            }

            // Cast value on purpose: encrypted columns hash to a stable pseudonym, and setAttribute re-encrypts.
            $this->setAttribute($field->column, $field->anonymize->apply($this->getAttribute($field->column)));
            $columns[] = $field->column;
        }

        // An observer cancelling the save must not pass for a completed anonymization.
        if ($this->save() === false) {
            throw new RuntimeException('Anonymizing '.static::class.' ['.$this->getKey().'] was cancelled: save() returned false.');
        }

        event(new PersonalDataAnonymized($this, $columns));
    }
}
