<?php

namespace JeffersonGoncalves\UsPrivacy\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\UsPrivacy\UsPrivacy;

class InventoryCommand extends Command
{
    protected $signature = 'us-privacy:inventory {--json : Output as JSON}';

    protected $description = 'List every personal data column declared with #[PersonalData]';

    public function handle(UsPrivacy $privacy): int
    {
        $rows = $privacy->inventory();

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($rows === []) {
            $this->warn('No personal data declared. Add models to config us-privacy.models.');

            return self::SUCCESS;
        }

        $this->table(array_keys($rows[0]), $rows);

        return self::SUCCESS;
    }
}
