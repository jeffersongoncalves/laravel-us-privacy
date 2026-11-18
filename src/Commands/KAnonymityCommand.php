<?php

namespace JeffersonGoncalves\UsPrivacy\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JeffersonGoncalves\UsPrivacy\UsPrivacy;

class KAnonymityCommand extends Command
{
    protected $signature = 'us-privacy:k-anonymity
        {model : Eloquent model class}
        {columns* : Quasi-identifier columns}
        {--k=5 : Minimum group size}
        {--sensitive= : Sensitive column for l-diversity}';

    protected $description = 'Measure k-anonymity (and l-diversity) of a model over quasi-identifier columns';

    public function handle(UsPrivacy $privacy): int
    {
        $model = (string) $this->argument('model');

        if (! is_subclass_of($model, Model::class)) {
            $this->error("[{$model}] is not an Eloquent model.");

            return self::FAILURE;
        }

        $k = filter_var($this->option('k'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($k === false) {
            $this->error('--k must be a positive integer.');

            return self::FAILURE;
        }

        $sensitive = $this->option('sensitive');

        try {
            $result = $privacy->kAnonymity($model, (array) $this->argument('columns'), $k, is_string($sensitive) ? $sensitive : null);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(array_keys($result), [array_map(fn (?int $value): string => $value === null ? '-' : (string) $value, $result)]);

        if ($result['rows'] > 0 && $result['k'] < $k) {
            $this->warn("{$result['rows_below_k']} row(s) sit in groups smaller than k={$k}: generalize or suppress them before sharing.");

            return self::FAILURE;
        }

        $this->info("k-anonymous with k={$k}.");

        return self::SUCCESS;
    }
}
