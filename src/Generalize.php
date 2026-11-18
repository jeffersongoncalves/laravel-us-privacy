<?php

namespace JeffersonGoncalves\UsPrivacy;

use Closure;
use Illuminate\Support\Carbon;

/**
 * Generalizers for quasi-identifiers: each returns a closure that coarsens a value
 * (null stays null), for UsPrivacy::kAnonymity() or for building an anonymized dataset.
 */
final class Generalize
{
    /**
     * Keep the first $length characters: CEP 01310-100 → 01310.
     *
     * @return Closure(mixed): ?string
     */
    public static function prefix(int $length): Closure
    {
        return fn (mixed $value): ?string => $value === null ? null : mb_substr((string) $value, 0, $length);
    }

    /**
     * Bucket a number: range(10) turns 34 into "30-39".
     *
     * @return Closure(mixed): ?string
     */
    public static function range(int $size): Closure
    {
        return function (mixed $value) use ($size): ?string {
            if ($value === null) {
                return null;
            }

            $start = (int) (floor((float) $value / $size) * $size);

            return $start.'-'.($start + $size - 1);
        };
    }

    /**
     * Reduce a date to the given format: 'Y' (year, the default) or 'Y-m' (month).
     *
     * @return Closure(mixed): ?string
     */
    public static function date(string $format = 'Y'): Closure
    {
        return fn (mixed $value): ?string => $value === null ? null : Carbon::parse($value)->format($format);
    }
}
