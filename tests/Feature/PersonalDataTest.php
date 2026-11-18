<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\UsPrivacy\Events\PersonalDataAnonymized;
use JeffersonGoncalves\UsPrivacy\Facades\UsPrivacy;
use JeffersonGoncalves\UsPrivacy\Tests\Fixtures\Customer;

beforeEach(function () {
    Schema::create('customers', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->unique()->nullable();
        $table->text('health_notes')->nullable();
        $table->string('tax_id')->nullable();
        $table->string('plan')->nullable();
    });

    config()->set('us-privacy.models', [Customer::class]);
});

function customer(): Customer
{
    return Customer::create([
        'name' => 'Mary Smith',
        'email' => 'mary@example.com',
        'health_notes' => 'diabetes',
        'tax_id' => '123-45-6789',
        'plan' => 'pro',
    ]);
}

it('builds the inventory from attributes', function () {
    $inventory = UsPrivacy::inventory();

    expect($inventory)->toHaveCount(4)
        ->and($inventory[0])->toBe([
            'model' => Customer::class,
            'table' => 'customers',
            'column' => 'name',
            'category' => 'personal',
            'purpose' => 'Identification',
            'sensitive_type' => null,
            'retention' => null,
            'anonymize' => 'mask',
            'sold' => false,
            'targeted_advertising' => false,
            'profiling' => false,
        ])
        ->and($inventory[1]['targeted_advertising'])->toBeTrue()
        ->and($inventory[2])->toMatchArray(['category' => 'sensitive', 'sensitive_type' => 'health'])
        ->and($inventory[3]['category'])->toBe('sensitive');
});

it('exports only declared columns, decrypted', function () {
    expect(customer()->exportPersonalData())->toBe([
        'name' => 'Mary Smith',
        'email' => 'mary@example.com',
        'health_notes' => 'diabetes',
        'tax_id' => '123-45-6789',
    ]);
});

it('anonymizes each column with its strategy', function () {
    $customer = customer();
    $customer->anonymizePersonalData();
    $fresh = $customer->fresh();

    expect($fresh->name)->toBe('M*********')
        ->and($fresh->email)->toBe(hash_hmac('sha256', 'mary@example.com', config('app.key')))
        ->and($fresh->health_notes)->toBeNull()
        ->and($fresh->tax_id)->toBe('123-45-6789')
        ->and($fresh->plan)->toBe('pro');
});

it('dispatches PersonalDataAnonymized with the changed columns', function () {
    Event::fake([PersonalDataAnonymized::class]);
    $customer = customer();

    $customer->anonymizePersonalData();

    Event::assertDispatched(PersonalDataAnonymized::class, fn (PersonalDataAnonymized $event) => $event->model->is($customer)
        && $event->columns === ['name', 'email', 'health_notes']);
});

it('throws and dispatches nothing when an observer cancels the save', function () {
    Event::fake([PersonalDataAnonymized::class]);
    $customer = customer();
    Customer::updating(fn () => false);

    expect(fn () => $customer->anonymizePersonalData())->toThrow(RuntimeException::class, 'cancelled')
        ->and(Customer::find($customer->id)->name)->toBe('Mary Smith');

    Event::assertNotDispatched(PersonalDataAnonymized::class);
});

it('rejects models that do not implement the contract', function () {
    config()->set('us-privacy.models', [stdClass::class]);

    UsPrivacy::models();
})->throws(InvalidArgumentException::class);

it('prints the inventory as json', function () {
    Artisan::call('us-privacy:inventory', ['--json' => true]);

    expect(json_decode(Artisan::output(), true))->toHaveCount(4);
});
