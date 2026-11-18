<?php

namespace JeffersonGoncalves\UsPrivacy\Enums;

enum DataCategory: string
{
    /** Personal data: information linked or reasonably linkable to an identified or identifiable individual. */
    case Personal = 'personal';

    /**
     * Sensitive data (see SensitiveType): most states require opt-in consent to process it,
     * Utah and Iowa notice and a right to opt out, Maryland only what is strictly necessary.
     */
    case Sensitive = 'sensitive';
}
