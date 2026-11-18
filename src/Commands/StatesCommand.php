<?php

namespace JeffersonGoncalves\UsPrivacy\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\UsPrivacy\StateRules;
use JeffersonGoncalves\UsPrivacy\UsPrivacy;

class StatesCommand extends Command
{
    protected $signature = 'us-privacy:states
        {--consumers= : Consumers whose personal data you process a year, to check which laws apply}
        {--consumers-selling=0 : Of those, consumers while you derive revenue from selling personal data}
        {--sale-share=0 : % of gross revenue from selling personal data}
        {--revenue=0 : Annual gross revenue in USD}
        {--sensitive : You process sensitive data}
        {--sells : You sell personal data}';

    protected $description = 'List the state privacy laws, their deadlines and rules, and which apply to you';

    public function handle(UsPrivacy $privacy): int
    {
        $check = $this->option('consumers') !== null;

        $rows = array_map(fn (StateRules $s): array => [
            $s->code,
            $s->law,
            $s->effective->toDateString().($s->inForce() ? '' : ' (not yet)'),
            "{$s->responseDays}+{$s->extensionDays}",
            $s->hasAppeals() ? "{$s->appealDays}+{$s->appealExtensionDays}" : '-',
            $s->sensitive,
            $s->universalOptOut?->toDateString() ?? '-',
            $s->assessments ? 'yes' : 'no',
            ...($check ? [$s->applies(
                (int) $this->option('consumers'),
                (int) $this->option('consumers-selling'),
                (float) $this->option('sale-share'),
                (int) $this->option('revenue'),
                (bool) $this->option('sensitive'),
                (bool) $this->option('sells'),
            ) ? 'yes' : 'no'] : []),
        ], array_values($privacy->states()));

        $this->table(['State', 'Law', 'Effective', 'Response days', 'Appeal days', 'Sensitive data', 'Universal opt-out', 'Assessments', ...($check ? ['Applies'] : [])], $rows);

        return self::SUCCESS;
    }
}
