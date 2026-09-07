<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function makeUser(\App\Enums\Role $role): \App\Models\User
{
    return \App\Models\User::factory()->create(['role' => $role]);
}

function makeItem(array $attrs = []): \App\Models\Item
{
    return \App\Models\Item::create(array_merge([
        'code' => 'T-'.fake()->unique()->numerify('####'),
        'name' => 'Test Paint '.fake()->unique()->word(),
        'bom_category' => 'Paint Mixing',
        'issue_pool' => 'Paint Mixing',
        'uom' => 'Gram',
        'rate_per_uom' => 2.5,
        'min_level' => 100,
        'is_active' => true,
    ], $attrs));
}

/** @param array<string, float> $pools issue_pool => allocated grams */
function makeOrder(array $pools = ['Paint Mixing' => 500.0]): \App\Models\Order
{
    $order = \App\Models\Order::create(['code' => 'BS-TEST-'.fake()->unique()->numerify('####')]);
    foreach ($pools as $pool => $qty) {
        \App\Models\BomLine::create([
            'order_id' => $order->id,
            'bom_category' => $pool,
            'issue_pool' => $pool,
            'allocated_qty' => $qty,
            'uom' => 'Gram',
        ]);
    }

    return $order;
}

function ledger(): \App\Services\LedgerService
{
    return app(\App\Services\LedgerService::class);
}
