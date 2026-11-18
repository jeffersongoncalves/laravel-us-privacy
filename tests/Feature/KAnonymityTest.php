<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\UsPrivacy\Facades\UsPrivacy;
use JeffersonGoncalves\UsPrivacy\Generalize;

class Patient extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('patients', function (Blueprint $table) {
        $table->id();
        $table->string('zip');
        $table->date('birth_date');
        $table->unsignedInteger('age');
        $table->string('diagnosis');
    });

    foreach ([
        ['01310-100', '1990-03-01', 34, 'flu'],
        ['01310-200', '1990-07-12', 35, 'asthma'],
        ['01310-300', '1990-11-30', 31, 'flu'],
        ['04538-000', '1985-01-01', 40, 'flu'],
    ] as [$zip, $birth, $age, $diagnosis]) {
        Patient::create(['zip' => $zip, 'birth_date' => $birth, 'age' => $age, 'diagnosis' => $diagnosis]);
    }
});

it('measures k over raw quasi-identifiers', function () {
    expect(UsPrivacy::kAnonymity(Patient::class, ['zip', 'age'], k: 2))->toBe([
        'rows' => 4, 'groups' => 4, 'k' => 1, 'rows_below_k' => 4, 'l' => null,
    ]);
});

it('raises k with generalizers and reports l-diversity', function () {
    $result = UsPrivacy::kAnonymity(Patient::class, [
        'zip' => Generalize::prefix(5),
        'birth_date' => Generalize::date(),
    ], k: 2, sensitive: 'diagnosis');

    // 01310/1990 holds 3 rows (flu, asthma); 04538/1985 holds 1 row.
    expect($result)->toBe(['rows' => 4, 'groups' => 2, 'k' => 1, 'rows_below_k' => 1, 'l' => 1])
        ->and(UsPrivacy::kAnonymity(Patient::query()->where('zip', 'like', '01310%'), ['zip' => Generalize::prefix(5), 'age' => Generalize::range(10)], 3, 'diagnosis'))
        ->toBe(['rows' => 3, 'groups' => 1, 'k' => 3, 'rows_below_k' => 0, 'l' => 2]);
});

it('generalizes values', function () {
    expect(Generalize::prefix(5)('01310-100'))->toBe('01310')
        ->and(Generalize::range(10)(34))->toBe('30-39')
        ->and(Generalize::range(10)(40))->toBe('40-49')
        ->and(Generalize::date('Y-m')('1990-03-01'))->toBe('1990-03')
        ->and(Generalize::date()(null))->toBeNull();
});

it('rejects columns the rows do not have', function () {
    expect(fn () => UsPrivacy::kAnonymity(Patient::query()->select('id'), ['zip']))->toThrow(InvalidArgumentException::class, 'Column [zip]')
        ->and(fn () => UsPrivacy::kAnonymity(Patient::class, ['zip'], sensitive: 'diagnose'))->toThrow(InvalidArgumentException::class, 'Column [diagnose]')
        ->and(fn () => UsPrivacy::kAnonymity(Patient::class, ['zip'], k: 0))->toThrow(InvalidArgumentException::class);
});

it('validates the command input', function (string $k) {
    expect(Artisan::call('us-privacy:k-anonymity', ['model' => Patient::class, 'columns' => ['zip'], '--k' => $k]))->toBe(1)
        ->and(Artisan::output())->toContain('--k must be a positive integer.');
})->with(['', 'abc', '0', '-2']);

it('fails the command on a misspelled column', function () {
    expect(Artisan::call('us-privacy:k-anonymity', ['model' => Patient::class, 'columns' => ['zipcode']]))->toBe(1)
        ->and(Artisan::output())->toContain('Column [zipcode] is not available');
});

it('fails the command below the target k', function () {
    expect(Artisan::call('us-privacy:k-anonymity', ['model' => Patient::class, 'columns' => ['zip'], '--k' => 2]))->toBe(1)
        ->and(Artisan::output())->toContain('4 row(s) sit in groups smaller than k=2');

    Patient::query()->update(['zip' => '01310']);

    expect(Artisan::call('us-privacy:k-anonymity', ['model' => Patient::class, 'columns' => ['zip'], '--k' => 2]))->toBe(0);
});
