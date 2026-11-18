<?php

namespace JeffersonGoncalves\UsPrivacy;

use Closure;
use DateTimeInterface;
use Illuminate\Http\Request;
use JeffersonGoncalves\Privacy\Privacy;
use JeffersonGoncalves\UsPrivacy\Contracts\HasPersonalData;

class UsPrivacy extends Privacy
{
    protected string $config = 'us-privacy';

    protected string $contract = HasPersonalData::class;

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
}
