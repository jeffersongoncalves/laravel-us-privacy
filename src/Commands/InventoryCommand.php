<?php

namespace JeffersonGoncalves\UsPrivacy\Commands;

use JeffersonGoncalves\Privacy\Commands\InventoryCommand as BaseInventoryCommand;
use JeffersonGoncalves\UsPrivacy\UsPrivacy;

class InventoryCommand extends BaseInventoryCommand
{
    protected $signature = 'us-privacy:inventory {--json : Output as JSON}';

    protected string $privacy = UsPrivacy::class;
}
