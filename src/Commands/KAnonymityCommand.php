<?php

namespace JeffersonGoncalves\UsPrivacy\Commands;

use JeffersonGoncalves\Privacy\Commands\KAnonymityCommand as BaseKAnonymityCommand;
use JeffersonGoncalves\UsPrivacy\UsPrivacy;

class KAnonymityCommand extends BaseKAnonymityCommand
{
    protected $signature = 'us-privacy:k-anonymity
        {model : Eloquent model class}
        {columns* : Quasi-identifier columns}
        {--k=5 : Minimum group size}
        {--sensitive= : Sensitive column for l-diversity}';

    protected string $privacy = UsPrivacy::class;
}
