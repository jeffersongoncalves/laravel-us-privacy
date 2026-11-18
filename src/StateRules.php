<?php

namespace JeffersonGoncalves\UsPrivacy;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/** The rules of one state law (config us-privacy.states), or the strictest combination of several. */
final class StateRules
{
    /**
     * @param  array{consumers?: int, consumers_with_sale?: int, sale_revenue_share?: int|float, revenue?: int, any_sensitive?: bool, any_sale?: bool}  $thresholds
     * @param  list<string>  $rights
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $law,
        public readonly string $citation,
        public readonly CarbonImmutable $effective,
        public readonly array $thresholds,
        public readonly int $responseDays,
        public readonly int $extensionDays,
        public readonly ?int $appealDays,
        public readonly int $appealExtensionDays,
        public readonly string $sensitive,
        public readonly ?CarbonImmutable $universalOptOut,
        public readonly bool $assessments,
        public readonly int $minorAge,
        public readonly string $minorRule,
        public readonly array $rights,
    ) {}

    /** @param  array<string, mixed>  $rules */
    public static function fromConfig(string $code, array $rules): self
    {
        foreach (['name', 'effective', 'response_days', 'sensitive', 'rights'] as $key) {
            if (! array_key_exists($key, $rules)) {
                throw new InvalidArgumentException("us-privacy.states.{$code} is missing [{$key}].");
            }
        }

        if (! in_array($rules['sensitive'], ['opt_in', 'opt_out', 'strictly_necessary'], true)) {
            throw new InvalidArgumentException("us-privacy.states.{$code}.sensitive must be opt_in, opt_out or strictly_necessary.");
        }

        return new self(
            code: $code,
            name: (string) $rules['name'],
            law: (string) ($rules['law'] ?? $rules['name']),
            citation: (string) ($rules['citation'] ?? ''),
            effective: CarbonImmutable::parse((string) $rules['effective'])->startOfDay(),
            thresholds: (array) ($rules['thresholds'] ?? []),
            responseDays: (int) $rules['response_days'],
            extensionDays: (int) ($rules['extension_days'] ?? 0),
            appealDays: isset($rules['appeal_days']) ? (int) $rules['appeal_days'] : null,
            appealExtensionDays: (int) ($rules['appeal_extension_days'] ?? 0),
            sensitive: (string) $rules['sensitive'],
            universalOptOut: isset($rules['universal_opt_out']) ? CarbonImmutable::parse((string) $rules['universal_opt_out'])->startOfDay() : null,
            assessments: (bool) ($rules['assessments'] ?? false),
            minorAge: (int) ($rules['minor_age'] ?? 13),
            minorRule: (string) ($rules['minor_rule'] ?? 'consent'),
            rights: array_values(array_map('strval', (array) $rules['rights'])),
        );
    }

    public function inForce(?DateTimeInterface $on = null): bool
    {
        return $this->effective->lessThanOrEqualTo($on ?? now());
    }

    public function hasRight(string $right): bool
    {
        return in_array($right, $this->rights, true);
    }

    public function hasAppeals(): bool
    {
        return $this->appealDays !== null;
    }

    /** Whether an opt-out preference signal such as Global Privacy Control must be honored. */
    public function requiresUniversalOptOut(?DateTimeInterface $on = null): bool
    {
        return $this->universalOptOut !== null && $this->universalOptOut->lessThanOrEqualTo($on ?? now());
    }

    /** Processing sensitive data needs the consumer's consent first (opt-in or strictly necessary). */
    public function sensitiveNeedsConsent(): bool
    {
        return $this->sensitive !== 'opt_out';
    }

    /**
     * Whether a controller with these numbers is covered (the SBA small business exemption of
     * Texas and Nebraska is not checked: they have no count).
     *
     * @param  float  $saleRevenueShare  % of gross revenue from selling personal data
     */
    public function applies(int $consumers, int $consumersWhileSelling = 0, float $saleRevenueShare = 0, int $revenue = 0, bool $processesSensitive = false, bool $sells = false): bool
    {
        $t = $this->thresholds;

        if (isset($t['revenue']) && $revenue <= $t['revenue']) {
            return false;
        }

        if ((($t['any_sensitive'] ?? false) && $processesSensitive) || (($t['any_sale'] ?? false) && $sells)) {
            return true;
        }

        if ($consumers >= (int) ($t['consumers'] ?? PHP_INT_MAX)) {
            return true;
        }

        if (! isset($t['consumers_with_sale']) || $consumersWhileSelling < (int) $t['consumers_with_sale']) {
            return false;
        }

        // 0 = any revenue (or discount) from selling personal data.
        $share = (float) ($t['sale_revenue_share'] ?? 0);

        return $share == 0 ? $saleRevenueShare > 0 : $saleRevenueShare >= $share;
    }

    /** Deadline for a request received at $received, with the extension when $extended. */
    public function requestDeadline(DateTimeInterface $received, bool $extended = false): CarbonImmutable
    {
        return CarbonImmutable::instance($received)->addDays($this->responseDays + ($extended ? $this->extensionDays : 0));
    }

    /** Deadline for an appeal received at $received, or null when the state has no appeals. */
    public function appealDeadline(DateTimeInterface $received, bool $extended = false): ?CarbonImmutable
    {
        return $this->appealDays === null ? null
            : CarbonImmutable::instance($received)->addDays($this->appealDays + ($extended ? $this->appealExtensionDays : 0));
    }

    /**
     * The strictest combination: shortest deadlines, appeals with the shortest window, consent for
     * sensitive data (strictly necessary if any state says so), universal opt-out honored, every right.
     *
     * @param  list<self>  $states
     */
    public static function strictest(array $states): self
    {
        if ($states === []) {
            throw new InvalidArgumentException('No state rules to combine.');
        }

        $appeals = array_values(array_filter($states, fn (self $s): bool => $s->appealDays !== null));
        $sensitive = array_map(fn (self $s): string => $s->sensitive, $states);
        $optOuts = array_filter(array_map(fn (self $s): ?CarbonImmutable => $s->universalOptOut, $states));

        return new self(
            code: 'US',
            name: 'Strictest of '.implode(', ', array_map(fn (self $s): string => $s->code, $states)),
            law: 'Strictest combination of the state laws',
            citation: '',
            effective: min(array_map(fn (self $s): CarbonImmutable => $s->effective, $states)),
            thresholds: ['consumers' => 0],
            responseDays: min(array_map(fn (self $s): int => $s->responseDays, $states)),
            extensionDays: min(array_map(fn (self $s): int => $s->extensionDays, $states)),
            appealDays: $appeals === [] ? null : min(array_map(fn (self $s): int => (int) $s->appealDays, $appeals)),
            appealExtensionDays: $appeals === [] ? 0 : min(array_map(fn (self $s): int => $s->appealExtensionDays, $appeals)),
            sensitive: in_array('strictly_necessary', $sensitive, true) ? 'strictly_necessary' : (in_array('opt_in', $sensitive, true) ? 'opt_in' : 'opt_out'),
            universalOptOut: $optOuts === [] ? null : min($optOuts),
            assessments: in_array(true, array_map(fn (self $s): bool => $s->assessments, $states), true),
            minorAge: max(array_map(fn (self $s): int => $s->minorAge, $states)),
            minorRule: in_array('prohibited', array_map(fn (self $s): string => $s->minorRule, $states), true) ? 'prohibited' : 'consent',
            rights: array_values(array_unique(array_merge(...array_map(fn (self $s): array => $s->rights, $states)))),
        );
    }
}
