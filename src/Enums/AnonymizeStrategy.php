<?php

namespace JeffersonGoncalves\UsPrivacy\Enums;

use Illuminate\Support\Str;

enum AnonymizeStrategy: string
{
    /** Set the column to null. */
    case Nullify = 'nullify';

    /** Replace with a keyed HMAC-SHA256 (stable pseudonym; keeps unique indexes working). */
    case Hash = 'hash';

    /** Keep the first character, mask the rest with "*". */
    case Mask = 'mask';

    /** Leave the value untouched (e.g. data under a legal retention duty). */
    case Keep = 'keep';

    public function apply(mixed $value): mixed
    {
        if ($value === null || $this === self::Keep) {
            return $value;
        }

        return match ($this) {
            self::Nullify => null,
            self::Hash => hash_hmac('sha256', (string) $value, self::key()),
            self::Mask => Str::mask((string) $value, '*', 1),
        };
    }

    private static function key(): string
    {
        $key = config('us-privacy.hash_key') ?: config('app.key');

        if (! is_string($key) || $key === '') {
            throw new \RuntimeException('Set us-privacy.hash_key (or app.key) before using the hash anonymize strategy.');
        }

        return $key;
    }
}
