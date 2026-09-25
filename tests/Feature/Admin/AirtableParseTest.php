<?php

use App\Enums\Role;
use App\Models\BomCategory;
use App\Models\Order;
use App\Services\AirtableOrderSync;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.airtable.token' => 'fake-token']);
    BomCategory::firstOrCreate(['name' => 'Paint Mixing'], ['issue_pool' => 'Paint Mixing', 'ignored' => false]);
});

function fakeOneRecord(string $code): void
{
    Http::fake(['api.airtable.com/*' => Http::response(['records' => [[
        'id' => 'recPARSE1',
        'createdTime' => '2026-09-01T00:00:00.000Z',
        'fields' => [
            'fldxO7kzDnRPbOJ5z' => $code,                              // order code
            'fld71BWWdjayToa51' => 'Paint Mixing 250 Gram | Total: 500.00', // BOM string
        ],
    ]]])]);
}

test('only admin can parse', function () {
    fakeOneRecord('BS-US-7001');

    foreach ([Role::Store, Role::Painter] as $role) {
        $this->actingAs(makeUser($role));
        $this->post('/admin/parse')->assertForbidden();
    }

    expect(Order::count())->toBe(0);
});

test('the Parse button pulls orders in and reports what changed', function () {
    fakeOneRecord('BS-US-7002');
    $this->actingAs(makeUser(Role::Admin));

    $this->post('/admin/parse')
        ->assertRedirect()
        ->assertSessionHas('success', fn (string $m) => str_contains($m, '1 new'));

    $order = Order::firstWhere('code', 'BS-US-7002');
    expect($order)->not->toBeNull()
        ->and($order->bomLines)->toHaveCount(1)
        ->and((float) $order->bomLines[0]->allocated_qty)->toBe(250.0);

    // the run is remembered so the dashboard can show when the feed was last read
    expect(Cache::get(AirtableOrderSync::LAST_RUN)['stats']['created'])->toBe(1);
});

test('a second parse reports nothing new rather than duplicating the order', function () {
    fakeOneRecord('BS-US-7003');
    $this->actingAs(makeUser(Role::Admin));

    $this->post('/admin/parse');
    $this->post('/admin/parse')
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'nothing new'));

    expect(Order::where('code', 'BS-US-7003')->count())->toBe(1);
});

test('parsing without a token warns instead of failing', function () {
    config(['services.airtable.token' => null]);
    Http::fake();
    $this->actingAs(makeUser(Role::Admin));

    $this->post('/admin/parse')->assertSessionHas('warning', fn (string $m) => str_contains($m, 'AIRTABLE_TOKEN'));

    Http::assertNothingSent();
});

test('a parse already in flight is refused rather than run twice', function () {
    fakeOneRecord('BS-US-7004');
    $this->actingAs(makeUser(Role::Admin));

    Cache::lock(AirtableOrderSync::LOCK, 600)->get(); // the 15-min schedule is mid-run

    $this->post('/admin/parse')->assertSessionHas('warning', fn (string $m) => str_contains($m, 'already running'));

    expect(Order::count())->toBe(0);
    Http::assertNothingSent();
});

test('an Airtable outage warns instead of 500ing the dashboard', function () {
    Http::fake(['api.airtable.com/*' => Http::response(['error' => 'boom'], 500)]);
    $this->actingAs(makeUser(Role::Admin));

    $this->post('/admin/parse')->assertSessionHas('warning', fn (string $m) => str_contains($m, 'failed'));

    expect(Order::count())->toBe(0);
});
