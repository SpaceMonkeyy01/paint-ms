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

test('order costing sums snapshotted values per bucket (rule 4)', function () {
    $item = makeItem(['rate_per_uom' => 2.0]);
    $order = makeOrder(['Paint Mixing' => 2000.0]);
    $order->update(['bom_total_cost' => 1000.00, 'total_area' => 50]);

    ledger()->record(TransactionType::Opening, $item, 10000);
    ledger()->record(TransactionType::Issue, $item, 300, $order, IssueType::Bom);          // 600 Rs
    ledger()->record(TransactionType::Issue, $item, 100, $order, IssueType::Rework,
        remarks: 'repaint after scratch');                                                  // 200 Rs
    ledger()->record(TransactionType::Issue, $item, 50, $order, IssueType::Variance,
        authorizedBy: 'PM', remarks: 'client colour change');                               // 100 Rs
    ledger()->consumeSlots($order, [
        ['slot' => 'W01', 'item_id' => $item->id, 'start_wt' => 300, 'end_wt' => 100, 'wastage' => 25],
    ]);                                                                                     // consumed 400 Rs, wasted 50 Rs

    // rates change later — costing must not move (rule 4)
    $item->update(['rate_per_uom' => 999]);

    $c = app(CostingService::class)->orderCosting($order);

    expect($c['bom_cost'])->toBe(1000.0)
        ->and($c['issued_value'])->toBe(900.0)      // 600 + 200 + 100
        ->and($c['repaint_value'])->toBe(200.0)
        ->and($c['variance_value'])->toBe(100.0)
        ->and($c['consumed_value'])->toBe(400.0)
        ->and($c['wasted_value'])->toBe(50.0)
        ->and($c['variance_pct'])->toBe(-10.0)      // issued 900 vs bom 1000
        ->and($c['cost_per_sqft'])->toBe(18.0);     // 900 / 50 sqft
});

test('costing handles orders without bom cost or area', function () {
    $order = makeOrder();

    $c = app(CostingService::class)->orderCosting($order);

    expect($c['variance_pct'])->toBeNull()
        ->and($c['cost_per_sqft'])->toBeNull()
        ->and($c['issued_value'])->toBe(0.0);
});

test('pending issue lists orders with remaining BOM grams', function () {
    $item = makeItem();
    $pending = makeOrder(['Paint Mixing' => 500.0]);
    $done = makeOrder(['Paint Mixing' => 200.0]);
    ledger()->record(TransactionType::Opening, $item, 10000);
    ledger()->record(TransactionType::Issue, $item, 200, $done, IssueType::Bom);

    $result = app(CostingService::class)->pendingIssue();

    $codes = collect($result['top'])->pluck('code');
    expect($codes)->toContain($pending->code)->not->toContain($done->code)
        ->and($result['count'])->toBe(1);
});

test('variance exceptions surface reason and authoriser', function () {
    $item = makeItem();
    $order = makeOrder(['Paint Mixing' => 0.0]);
    ledger()->record(TransactionType::Opening, $item, 1000);
    ledger()->record(TransactionType::Issue, $item, 75, $order, IssueType::Variance,
        authorizedBy: 'GM', remarks: 'urgent respray');

    $ex = app(CostingService::class)->varianceExceptions();

    expect($ex)->toHaveCount(1)
        ->and($ex[0]['order'])->toBe($order->code)
        ->and($ex[0]['issue_type'])->toBe('variance')
        ->and($ex[0]['grams'])->toBe(75.0)
        ->and($ex[0]['remarks'])->toBe('urgent respray')
        ->and($ex[0]['authorized_by'])->toBe('GM');
});

test('costing detail page renders with pools and ledger', function () {
    $this->actingAs(makeUser(Role::Admin));
    $item = makeItem();
    $order = makeOrder();
    ledger()->record(TransactionType::Opening, $item, 1000);
    ledger()->record(TransactionType::Issue, $item, 100, $order, IssueType::Bom);

    $this->get(route('admin.costing.show', $order))->assertOk();
});
