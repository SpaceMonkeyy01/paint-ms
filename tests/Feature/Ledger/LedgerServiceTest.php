<?php

use App\Enums\TransactionType;
use App\Services\StockService;
use Illuminate\Validation\ValidationException;

test('issue writes signed negative qty and moves stock on hand', function () {
    $item = makeItem();
    ledger()->record(TransactionType::Opening, $item, 1000);
    expect($item->stockOnHand())->toBe(1000.0);

    ledger()->record(TransactionType::Issue, $item, 200);

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

test('issueToStation writes order-less issues atomically (rule 3)', function () {
    $itemA = makeItem();
    $itemB = makeItem();
    ledger()->record(TransactionType::Opening, $itemA, 1000);
    ledger()->record(TransactionType::Opening, $itemB, 1000);

    $txns = ledger()->issueToStation([
        ['item_id' => $itemA->id, 'grams' => 400],
        ['item_id' => $itemB->id, 'grams' => 250],
    ], remarks: 'W01 refill');

    expect($txns)->toHaveCount(2)
        ->and($txns[0]->order_id)->toBeNull()
        ->and($txns[0]->qty)->toBe(-400.0)
        ->and($txns[0]->issue_pool)->toBe('Paint Mixing')
        ->and($itemA->stockOnHand())->toBe(600.0)
        ->and($itemB->stockOnHand())->toBe(750.0);
});

test('station stock is issued minus consumed, warehouse stock untouched by consumption', function () {
    $item = makeItem();
    $order = makeOrder();
    ledger()->record(TransactionType::Opening, $item, 1000);
    ledger()->issueToStation([['item_id' => $item->id, 'grams' => 600]]);

    $stock = app(StockService::class);
    expect((float) $stock->stationStockByItem()[$item->id])->toBe(600.0)
        ->and($item->stockOnHand())->toBe(400.0);

    ledger()->consumeSlots($order, [
        ['slot' => 'W01', 'item_id' => $item->id, 'grams' => 250],
    ]);

    // consumption drains the station, never the warehouse (rule 1)
    expect((float) $stock->stationStockByItem()[$item->id])->toBe(350.0)
        ->and($item->stockOnHand())->toBe(400.0);
});

test('station list flags EMPTY and LOW slot items', function () {
    $empty = makeItem();
    $low = makeItem();
    $ok = makeItem();
    ledger()->record(TransactionType::Opening, $low, 5000);
    ledger()->record(TransactionType::Opening, $ok, 5000);
    ledger()->issueToStation([
        ['item_id' => $low->id, 'grams' => 500],  // below STATION_LOW_G
        ['item_id' => $ok->id, 'grams' => 2000],
    ]);

    $list = app(StockService::class)->stationList()->keyBy('id');

    expect($list[$empty->id]->station_status)->toBe('EMPTY')
        ->and($list[$low->id]->station_status)->toBe('LOW')
        ->and($list[$ok->id]->station_status)->toBe('OK');
});

test('issue on an item without an issue pool is rejected', function () {
    $item = makeItem(['issue_pool' => null]);
    ledger()->record(TransactionType::Opening, $item, 100);

    ledger()->record(TransactionType::Issue, $item, 10);
})->throws(ValidationException::class);
