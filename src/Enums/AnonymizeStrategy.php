<?php

namespace JeffersonGoncalves\UsPrivacy\Enums;

use JeffersonGoncalves\Privacy\Concerns\AppliesAnonymizeStrategy;
use JeffersonGoncalves\Privacy\Contracts\AnonymizeStrategy as AnonymizeStrategyContract;

enum AnonymizeStrategy: string implements AnonymizeStrategyContract
{
    use AppliesAnonymizeStrategy;

    /** Config key of the "hash" strategy's key (falls back to app.key). */
    const HASH_KEY = 'us-privacy.hash_key';

    /** Set the column to null. */
    case Nullify = 'nullify';

    /** Replace with a keyed HMAC-SHA256 (stable pseudonym; keeps unique indexes working). */
    case Hash = 'hash';

    /** Keep the first character, mask the rest with "*". */
    case Mask = 'mask';

    /** Leave the value untouched (e.g. data under a legal retention duty). */
    case Keep = 'keep';
}
