<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use JeffersonGoncalves\UsPrivacy\Facades\UsPrivacy;
use JeffersonGoncalves\UsPrivacy\StateRules;

afterEach(fn () => UsPrivacy::resolveStateUsing(null));

function visitor(array $headers): Request
{
    $request = Request::create('/');
    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

it('ships the rules of 18 state laws', function () {
    $states = UsPrivacy::states();

    expect($states)->toHaveCount(18)
        ->and(array_keys($states))->toContain('VA', 'CO', 'TX', 'MD', 'RI')
        ->and($states)->not->toHaveKey('CA')
        ->and($states['VA'])->toBeInstanceOf(StateRules::class);
});

it('keeps each state\'s own deadlines, appeals and sensitive data model', function () {
    $va = UsPrivacy::state('va');
    $co = UsPrivacy::state('CO');

    expect([$va->responseDays, $va->extensionDays, $va->appealDays, $va->appealExtensionDays])->toBe([45, 45, 60, 0])
        ->and([$co->appealDays, $co->appealExtensionDays])->toBe([45, 60])
        ->and(UsPrivacy::state('IA')->responseDays)->toBe(90)
        ->and(UsPrivacy::state('UT')->hasAppeals())->toBeFalse()
        ->and(UsPrivacy::state('UT')->sensitive)->toBe('opt_out')
        ->and(UsPrivacy::state('UT')->sensitiveNeedsConsent())->toBeFalse()
        ->and(UsPrivacy::state('MD')->sensitive)->toBe('strictly_necessary')
        ->and(UsPrivacy::state('MD')->minorRule)->toBe('prohibited')
        ->and(UsPrivacy::state('MN')->hasRight('question_profiling'))->toBeTrue()
        ->and(UsPrivacy::state('IA')->hasRight('correct'))->toBeFalse();
});

it('knows when each law and its universal opt-out duty start', function () {
    $or = UsPrivacy::state('OR');

    expect($or->inForce(CarbonImmutable::parse('2024-06-30')))->toBeFalse()
        ->and($or->inForce(CarbonImmutable::parse('2024-07-01')))->toBeTrue()
        ->and($or->requiresUniversalOptOut(CarbonImmutable::parse('2025-12-31')))->toBeFalse()
        ->and($or->requiresUniversalOptOut(CarbonImmutable::parse('2026-01-01')))->toBeTrue()
        ->and(UsPrivacy::state('VA')->requiresUniversalOptOut())->toBeFalse()
        ->and(UsPrivacy::inForce(CarbonImmutable::parse('2025-01-01')))->not->toHaveKey('IN')->toHaveKey('DE');
});

it('computes request and appeal deadlines', function () {
    $received = CarbonImmutable::parse('2026-03-01');
    $co = UsPrivacy::state('CO');

    expect($co->requestDeadline($received)->toDateString())->toBe('2026-04-15')
        ->and($co->requestDeadline($received, extended: true)->toDateString())->toBe('2026-05-30')
        ->and($co->appealDeadline($received)->toDateString())->toBe('2026-04-15')
        ->and($co->appealDeadline($received, extended: true)->toDateString())->toBe('2026-06-14')
        ->and(UsPrivacy::state('UT')->appealDeadline($received))->toBeNull();
});

it('checks the applicability thresholds', function () {
    expect(UsPrivacy::state('VA')->applies(100000))->toBeTrue()
        ->and(UsPrivacy::state('VA')->applies(99999))->toBeFalse()
        ->and(UsPrivacy::state('VA')->applies(30000, 30000, 50))->toBeTrue()
        ->and(UsPrivacy::state('VA')->applies(30000, 30000, 49))->toBeFalse()
        // Colorado: any revenue or discount from sales counts.
        ->and(UsPrivacy::state('CO')->applies(30000, 30000, 0.1))->toBeTrue()
        ->and(UsPrivacy::state('CO')->applies(30000, 30000, 0))->toBeFalse()
        // Utah and Tennessee also need the revenue.
        ->and(UsPrivacy::state('UT')->applies(200000, revenue: 10000000))->toBeFalse()
        ->and(UsPrivacy::state('UT')->applies(200000, revenue: 30000000))->toBeTrue()
        // Connecticut: sensitive data or a sale is enough.
        ->and(UsPrivacy::state('CT')->applies(10, processesSensitive: true))->toBeTrue()
        ->and(UsPrivacy::state('CT')->applies(10))->toBeFalse()
        // Texas and Nebraska have no count.
        ->and(UsPrivacy::state('TX')->applies(1))->toBeTrue();
});

it('combines the strictest rules for consumers of unknown state', function () {
    $strictest = UsPrivacy::strictest(CarbonImmutable::parse('2026-11-17'));

    expect($strictest->code)->toBe('US')
        ->and($strictest->responseDays)->toBe(45)
        ->and($strictest->appealDays)->toBe(45)
        ->and($strictest->sensitive)->toBe('strictly_necessary')
        ->and($strictest->requiresUniversalOptOut(CarbonImmutable::parse('2026-11-17')))->toBeTrue()
        ->and($strictest->minorAge)->toBe(18)
        ->and($strictest->minorRule)->toBe('prohibited')
        ->and($strictest->hasRight('list_third_parties'))->toBeTrue()
        ->and(UsPrivacy::rulesFor(null)->code)->toBe('US');

    config()->set('us-privacy.unknown_state', 'VA');
    expect(UsPrivacy::rulesFor(null)->code)->toBe('VA');
});

it('finds no state law for states without one, nor before it takes effect', function () {
    expect(UsPrivacy::rulesFor('CA'))->toBeNull()
        ->and(UsPrivacy::rulesFor('KY', CarbonImmutable::parse('2025-06-01')))->toBeNull()
        ->and(UsPrivacy::rulesFor('KY', CarbonImmutable::parse('2026-01-01'))?->code)->toBe('KY');
});

it('reads the visitor state from the location headers', function () {
    expect(UsPrivacy::stateFromRequest(visitor(['cf-ipcountry' => 'US', 'cf-region-code' => 'co'])))->toBe('CO')
        ->and(UsPrivacy::stateFromRequest(visitor(['cf-ipcountry' => 'BR', 'cf-region-code' => 'SP'])))->toBeNull()
        ->and(UsPrivacy::rulesForRequest(visitor(['cf-ipcountry' => 'US', 'cf-region-code' => 'TX']))->code)->toBe('TX')
        ->and(UsPrivacy::rulesForRequest(visitor(['cf-ipcountry' => 'BR'])))->toBeNull()
        ->and(UsPrivacy::rulesForRequest(visitor(['cf-ipcountry' => 'US']))->code)->toBe('US')
        ->and(UsPrivacy::rulesForRequest(visitor([]))->code)->toBe('US');

    UsPrivacy::resolveStateUsing(fn (Request $request) => 'or');

    expect(UsPrivacy::rulesForRequest(visitor(['cf-ipcountry' => 'BR']))->code)->toBe('OR');
});

it('rejects an incomplete state in the config', function () {
    config()->set('us-privacy.states.XX', ['name' => 'Nowhere']);

    UsPrivacy::states();
})->throws(InvalidArgumentException::class, 'missing [effective]');

it('lists the states and which apply', function () {
    Artisan::call('us-privacy:states', ['--consumers' => 50000, '--sensitive' => true]);
    $output = Artisan::output();

    expect($output)->toContain('Virginia Consumer Data Protection Act')
        ->toContain('Applies');
});
