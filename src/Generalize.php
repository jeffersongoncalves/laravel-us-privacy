<?php

namespace JeffersonGoncalves\UsPrivacy;

use JeffersonGoncalves\Privacy\Generalize as BaseGeneralize;

/**
 * Generalizers for quasi-identifiers: each returns a closure that coarsens a value
 * (null stays null), for UsPrivacy::kAnonymity() or for building an anonymized dataset.
 */
final class Generalize extends BaseGeneralize {}
