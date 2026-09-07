<?php

use App\Enums\IssueType;
use App\Enums\TransactionType;
use App\Models\BomLine;
use App\Models\Item;
use App\Models\Order;
use App\Services\LedgerService;
use Illuminate\Validation\ValidationException;

function makeItem(array $attrs = []): Item
{
    return Item::create(array_merge([
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

function makeOrder(array $pools = ['Paint Mixing' => 500.0]): Order
{
    $order = Order::create(['code' => 'BS-TEST-'.fake()->unique()->numerify('####')]);
    foreach ($pools as $pool => $qty) {
        BomLine::create([
            'order_id' => $order->id,
            'bom_category' => $pool,
            'issue_pool' => $pool,
            'allocated_qty' => $qty,
            'uom' => 'Gram',
        ]);
    }

    return $order;
}

function ledger(): LedgerService
{
    return app(LedgerService::class);
}

test('issue writes signed negative qty and moves stock on hand', function () {
    $item = makeItem();
    ledger()->record(TransactionType::Opening, $item, 1000);
    expect($item->stockOnHand())->toBe(1000.0);

    ledger()->record(TransactionType::Issue, $item, 200, makeOrder(), IssueType::Bom);

    expect($item->stockOnHand())->toBe(800.0)
        ->and((float) $item->transactions()->where('type', 'issue')->value('qty'))->toBe(-200.0);
});

test('rates are snapshotted at write time, not joined later (rule 4)', function () {
    $item = makeItem(['rate_per_uom' => 2.5]);
    $txn = ledger()->record(TransactionType::Receipt, $item, 100);

    expect($txn->rate)->toBe(2.5)->and($txn->value)->toBe(250.0);

    $item->update(['rate_per_uom' => 99.0]);
    expect($txn->fresh()->value)->toBe(250.0); // history unchanged
});

test('negative qty from callers is rejected for non-adjust types (rule 2)', function () {
    ledger()->record(TransactionType::Receipt, makeItem(), -50);
})->throws(ValidationException::class);

test('adjust keeps the caller sign in both directions', function () {
    $item = makeItem();
    ledger()->record(TransactionType::Adjust, $item, 30, remarks: 'found extra');
    ledger()->record(TransactionType::Adjust, $item, -10, remarks: 'spillage');

    expect($item->stockOnHand())->toBe(20.0);
});

test('bom issue cannot exceed remaining pool allowance (rule 6)', function () {
    $item = makeItem();
    $order = makeOrder(['Paint Mixing' => 500.0]);
    ledger()->record(TransactionType::Opening, $item, 10000);

    ledger()->record(TransactionType::Issue, $item, 400, $order, IssueType::Bom);

    expect(fn () => ledger()->record(TransactionType::Issue, $item, 101, $order, IssueType::Bom))
        ->toThrow(ValidationException::class);

    // exactly the remaining 100 g is fine
    ledger()->record(TransactionType::Issue, $item, 100, $order, IssueType::Bom);
    expect($item->stockOnHand())->toBe(9500.0);
});

test('variance issue requires remarks and authoriser (rule 6)', function () {
    $item = makeItem();
    $order = makeOrder(['Paint Mixing' => 0.0]);
    ledger()->record(TransactionType::Opening, $item, 1000);

    expect(fn () => ledger()->record(TransactionType::Issue, $item, 50, $order, IssueType::Variance))
        ->toThrow(ValidationException::class);

    $txn = ledger()->record(
        TransactionType::Issue, $item, 50, $order, IssueType::Variance,
        authorizedBy: 'Production Manager', remarks: 'colour re-mix after client change',
    );

    expect($txn->qty)->toBe(-50.0)->and($item->stockOnHand())->toBe(950.0);
});

test('issueLines is atomic — one over-BOM line rolls the whole submit back', function () {
    $itemA = makeItem();
    $itemB = makeItem();
    $order = makeOrder(['Paint Mixing' => 300.0]);
    ledger()->record(TransactionType::Opening, $itemA, 1000);
    ledger()->record(TransactionType::Opening, $itemB, 1000);

    expect(fn () => ledger()->issueLines($order, [
        ['item_id' => $itemA->id, 'grams' => 200],
        ['item_id' => $itemB->id, 'grams' => 200], // pool total 400 > 300
    ], IssueType::Bom))->toThrow(ValidationException::class);

    expect($itemA->stockOnHand())->toBe(1000.0)
        ->and($itemB->stockOnHand())->toBe(1000.0)
        ->and($order->transactions()->count())->toBe(0);
});

test('issueLines within allowance writes all lines and shrinks the pool', function () {
    $itemA = makeItem();
    $itemB = makeItem();
    $order = makeOrder(['Paint Mixing' => 300.0]);
    ledger()->record(TransactionType::Opening, $itemA, 1000);
    ledger()->record(TransactionType::Opening, $itemB, 1000);

    ledger()->issueLines($order, [
        ['item_id' => $itemA->id, 'grams' => 150],
        ['item_id' => $itemB->id, 'grams' => 150],
    ], IssueType::Bom);

    expect($itemA->stockOnHand())->toBe(850.0)
        ->and($itemB->stockOnHand())->toBe(850.0)
        ->and(ledger()->remainingAllowance($order, 'Paint Mixing'))->toBe(0.0);
});

test('issue on an item without an issue pool is rejected', function () {
    $item = makeItem(['issue_pool' => null]);
    ledger()->record(TransactionType::Opening, $item, 100);

    ledger()->record(TransactionType::Issue, $item, 10, makeOrder(), IssueType::Bom);
})->throws(ValidationException::class);
