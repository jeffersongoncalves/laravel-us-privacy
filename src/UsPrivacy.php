<?php

namespace JeffersonGoncalves\UsPrivacy;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JeffersonGoncalves\UsPrivacy\Contracts\HasPersonalData;

class UsPrivacy
{
    /** @var (Closure(Request): (string|null))|null */
    private ?Closure $stateResolver = null;

    /**
     * Every state law in config('us-privacy.states'), keyed by code.
     *
     * @return array<string, StateRules>
     */
    public function states(): array
    {
        $states = [];

        foreach ((array) config('us-privacy.states', []) as $code => $rules) {
            $states[strtoupper((string) $code)] = StateRules::fromConfig(strtoupper((string) $code), (array) $rules);
        }

        return $states;
    }

    /** The law of a state (two-letter code), or null when the state has none in the config. */
    public function state(?string $code): ?StateRules
    {
        return $code === null ? null : ($this->states()[strtoupper($code)] ?? null);
    }

    /**
     * Laws in force on a date (today by default).
     *
     * @return array<string, StateRules>
     */
    public function inForce(?DateTimeInterface $on = null): array
    {
        return array_filter($this->states(), fn (StateRules $state): bool => $state->inForce($on));
    }

    /** The strictest combination of the laws in force. */
    public function strictest(?DateTimeInterface $on = null): StateRules
    {
        return StateRules::strictest(array_values($this->inForce($on)));
    }

    /**
     * The rules for a consumer: their state's law in force; for an unknown state, the
     * us-privacy.unknown_state fallback; null for a state without a law (nothing applies).
     */
    public function rulesFor(?string $code, ?DateTimeInterface $on = null): ?StateRules
    {
        if ($code === null) {
            $fallback = (string) config('us-privacy.unknown_state', 'strictest');

            return $fallback === 'strictest' ? $this->strictest($on) : $this->rulesFor($fallback, $on);
        }

        $state = $this->state($code);

        return $state !== null && $state->inForce($on) ? $state : null;
    }

    /** The visitor's two-letter state code, or null when unknown or outside the US. */
    public function stateFromRequest(Request $request): ?string
    {
        if ($this->stateResolver !== null) {
            $code = ($this->stateResolver)($request);

            return is_string($code) && $code !== '' ? strtoupper($code) : null;
        }

        $country = $request->header((string) config('us-privacy.country_header', 'cf-ipcountry'));
        $region = $request->header((string) config('us-privacy.state_header', 'cf-region-code'));

        return strtoupper((string) $country) === 'US' && is_string($region) && preg_match('/^[A-Za-z]{2}$/', $region) === 1
            ? strtoupper($region)
            : null;
    }

    /**
     * The rules for the visitor of a request: none outside the US (country header set to another
     * country), the state's law when known, the unknown_state fallback otherwise.
     */
    public function rulesForRequest(Request $request, ?DateTimeInterface $on = null): ?StateRules
    {
        $country = $request->header((string) config('us-privacy.country_header', 'cf-ipcountry'));

        if ($this->stateResolver === null && is_string($country) && $country !== '' && ! in_array(strtoupper($country), ['US', 'XX', 'T1'], true)) {
            return null;
        }

        return $this->rulesFor($this->stateFromRequest($request), $on);
    }

    /** @param  (Closure(Request): (string|null))|null  $resolver */
    public function resolveStateUsing(?Closure $resolver): void
    {
        $this->stateResolver = $resolver;
    }

    /**
     * Models registered in config('us-privacy.models').
     *
     * @return list<class-string<Model&HasPersonalData>>
     */
    public function models(): array
    {
        $models = [];

        foreach ((array) config('us-privacy.models', []) as $model) {
            if (! is_string($model) || ! is_subclass_of($model, Model::class) || ! is_subclass_of($model, HasPersonalData::class)) {
                throw new InvalidArgumentException('us-privacy.models entries must be Eloquent models implementing '.HasPersonalData::class.', got: '.var_export($model, true));
            }

            $models[] = $model;
        }

        return $models;
    }

    /**
     * Personal data inventory: one row per declared column (feeds ROPA, DSAR and retention).
     *
     * @return list<array{model: class-string, table: string, column: string, category: string, purpose: string|null, sensitive_type: string|null, retention: string|null, anonymize: string, sold: bool, targeted_advertising: bool, profiling: bool}>
     */
    public function inventory(): array
    {
        $rows = [];

        foreach ($this->models() as $model) {
            $table = (new $model)->getTable();

            foreach ($model::personalDataFields() as $field) {
                $rows[] = ['model' => $model, 'table' => $table] + $field->toArray();
            }
        }

        return $rows;
    }

    /**
     * Measure k-anonymity over quasi-identifiers: every combination of their values must be
     * shared by at least k rows. With a sensitive column, l is the smallest number of distinct
     * sensitive values inside a group (l-diversity).
     *
     * ponytail: scans every row in PHP (casts and generalizers apply), so run it off-peak on big tables.
     *
     * @param  class-string<Model>|Builder<Model>  $source
     * @param  array<int|string, string|Closure>  $quasiIdentifiers  columns, or column => generalizer (see Generalize)
     * @return array{rows: int, groups: int, k: int, rows_below_k: int, l: int|null}
     */
    public function kAnonymity(string|Builder $source, array $quasiIdentifiers, int $k = 5, ?string $sensitive = null): array
    {
        if ($k < 1) {
            throw new InvalidArgumentException("k must be at least 1, got {$k}.");
        }

        $generalizers = [];

        foreach ($quasiIdentifiers as $column => $generalizer) {
            if (is_int($column)) {
                $generalizers[(string) $generalizer] = null;
            } else {
                $generalizers[$column] = $generalizer instanceof Closure ? $generalizer : null;
            }
        }

        $counts = [];
        $values = [];

        $checked = false;

        foreach ((is_string($source) ? $source::query() : $source)->cursor() as $row) {
            if (! $checked) {
                // A misspelled or unselected column reads as null and would group every row together.
                foreach ([...array_keys($generalizers), ...($sensitive === null ? [] : [$sensitive])] as $column) {
                    if (! array_key_exists($column, $row->getAttributes()) && ! $row->hasGetMutator($column) && ! $row->hasAttributeMutator($column)) {
                        throw new InvalidArgumentException("Column [{$column}] is not available on ".$row::class.'.');
                    }
                }

                $checked = true;
            }

            $key = serialize(array_map(
                fn (string $column): mixed => ($generalizers[$column] ?? fn (mixed $value): mixed => $value)($row->getAttribute($column)),
                array_keys($generalizers),
            ));

            $counts[$key] = ($counts[$key] ?? 0) + 1;

            if ($sensitive !== null) {
                $values[$key][serialize($row->getAttribute($sensitive))] = true;
            }
        }

        return [
            'rows' => array_sum($counts),
            'groups' => count($counts),
            'k' => $counts === [] ? 0 : min($counts),
            'rows_below_k' => array_sum(array_filter($counts, fn (int $count): bool => $count < $k)),
            'l' => $sensitive === null || $values === [] ? null : min(array_map('count', $values)),
        ];
    }
}
