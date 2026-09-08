<?php

use App\Enums\IssueType;
use App\Enums\Role;
use App\Enums\TransactionType;
use App\Services\CostingService;

test('only admin reaches admin screens', function () {
    foreach ([Role::Store, Role::Painter] as $role) {
        $this->actingAs(makeUser($role));
        $this->get('/admin')->assertForbidden();
        $this->get('/admin/costing')->assertForbidden();
    }

    $this->actingAs(makeUser(Role::Admin));
    $this->get('/admin')->assertOk();
    $this->get('/admin/costing')->assertOk();
});

test('order costing sums snapshotted values, actual basis is consumption (rule 4)', function () {
    $item = makeItem(['rate_per_uom' => 2.0]);
    $order = makeOrder(['Paint Mixing' => 2000.0]);
    $order->update(['bom_total_cost' => 1000.00, 'total_area' => 50]);

    ledger()->record(TransactionType::Opening, $item, 10000);
    // legacy per-order issues still count into their buckets
    ledger()->record(TransactionType::Issue, $item, 300, $order, IssueType::Bom);          // 600 Rs
    ledger()->record(TransactionType::Issue, $item, 100, $order, IssueType::Rework,
        remarks: 'repaint after scratch');                                                  // 200 Rs
    ledger()->consumeSlots($order, [
        ['slot' => 'W01', 'item_id' => $item->id, 'grams' => 200],
    ]);                                                                                     // consumed 400 Rs
    ledger()->record(TransactionType::Wastage, $item, 25, $order, remarks: 'legacy gap');   // 50 Rs

    // rates change later — costing must not move (rule 4)
    $item->update(['rate_per_uom' => 999]);

    $c = app(CostingService::class)->orderCosting($order);

    expect($c['bom_cost'])->toBe(1000.0)
        ->and($c['issued_value'])->toBe(800.0)      // 600 + 200
        ->and($c['repaint_value'])->toBe(200.0)
        ->and($c['consumed_value'])->toBe(400.0)
        ->and($c['wasted_value'])->toBe(50.0)
        ->and($c['actual_value'])->toBe(450.0)      // consumption basis, not issued
        ->and($c['used_grams'])->toBe(225.0)
        ->and($c['variance_pct'])->toBe(-55.0)      // actual 450 vs bom 1000
        ->and($c['cost_per_sqft'])->toBe(9.0);      // 450 / 50 sqft
});

test('orders with no consumption yet fall back to issued value (legacy)', function () {
    $item = makeItem(['rate_per_uom' => 2.0]);
    $order = makeOrder(['Paint Mixing' => 2000.0]);
    $order->update(['bom_total_cost' => 1000.00]);

    ledger()->record(TransactionType::Opening, $item, 10000);
    ledger()->record(TransactionType::Issue, $item, 300, $order, IssueType::Bom); // 600 Rs

    $c = app(CostingService::class)->orderCosting($order);

    expect($c['actual_value'])->toBe(600.0)
        ->and($c['variance_pct'])->toBe(-40.0);
});

test('costing handles orders without bom cost or area', function () {
    $order = makeOrder();

    $c = app(CostingService::class)->orderCosting($order);

    expect($c['variance_pct'])->toBeNull()
        ->and($c['cost_per_sqft'])->toBeNull()
        ->and($c['actual_value'])->toBe(0.0);
});

test('pending consumption lists orders whose BOM is not fully used', function () {
    $item = makeItem();
    $pending = makeOrder(['Paint Mixing' => 500.0]);
    $done = makeOrder(['Paint Mixing' => 200.0]);
    ledger()->consumeSlots($done, [
        ['slot' => 'W01', 'item_id' => $item->id, 'grams' => 200],
    ]);

    $result = app(CostingService::class)->pendingConsumption();

    $codes = collect($result['top'])->pluck('code');
    expect($codes)->toContain($pending->code)->not->toContain($done->code)
        ->and($result['count'])->toBe(1);
});

test('over-BOM orders surface on the variance list with grams and percent', function () {
    $item = makeItem();
    $order = makeOrder(['Paint Mixing' => 100.0]);
    $fine = makeOrder(['Paint Mixing' => 500.0]);
    ledger()->consumeSlots($order, [
        ['slot' => 'W01', 'item_id' => $item->id, 'grams' => 150],
    ]);
    ledger()->consumeSlots($fine, [
        ['slot' => 'W01', 'item_id' => $item->id, 'grams' => 100],
    ]);

    $over = app(CostingService::class)->overBomOrders();

    expect($over)->toHaveCount(1)
        ->and($over[0]['code'])->toBe($order->code)
        ->and($over[0]['used_grams'])->toBe(150.0)
        ->and($over[0]['variance_grams'])->toBe(50.0)
        ->and($over[0]['variance_pct'])->toBe(50.0);
});

test('costing detail page renders with pools and ledger', function () {
    $this->actingAs(makeUser(Role::Admin));
    $item = makeItem();
    $order = makeOrder();
    ledger()->record(TransactionType::Opening, $item, 1000);
    ledger()->consumeSlots($order, [
        ['slot' => 'W01', 'item_id' => $item->id, 'grams' => 100],
    ]);

    $this->get(route('admin.costing.show', $order))->assertOk();
});
