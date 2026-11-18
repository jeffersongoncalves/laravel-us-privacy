<?php

namespace JeffersonGoncalves\UsPrivacy\Contracts;

use JeffersonGoncalves\UsPrivacy\Attributes\PersonalData;

interface HasPersonalData
{
    /**
     * @return list<PersonalData>
     */
    public static function personalDataFields(): array;

    /**
     * Declared personal data of this record, keyed by column (rights to access and to data portability).
     *
     * @return array<string, mixed>
     */
    public function exportPersonalData(): array;

    /**
     * Apply each column's anonymize strategy and persist (right to delete; deidentified data).
     */
    public function anonymizePersonalData(): void;
}
