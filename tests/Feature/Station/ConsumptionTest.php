<?php

use App\Enums\IssueType;
use App\Enums\Role;
use App\Enums\TransactionType;
use App\Models\ColourBatch;
use App\Services\LedgerService;
use App\Services\StockService;
use Illuminate\Validation\ValidationException;

test('store users cannot reach station screens, painters and admin can', function () {
    $order = makeOrder();

    $this->actingAs(makeUser(Role::Store));
    $this->get('/station')->assertForbidden();

    foreach ([Role::Painter, Role::Admin] as $role) {
        $this->actingAs(makeUser($role));
        $this->get('/station')->assertOk();
        $this->get(route('station.consume.show', $order))->assertOk();
    }
});

test('consumption and wastage never move item stock — no double deduction (rule 1)', function () {
    $item = makeItem();
    $order = makeOrder(['Paint Mixing' => 500.0]);
    ledger()->record(TransactionType::Opening, $item, 1000);
    ledger()->record(TransactionType::Issue, $item, 500, $order, IssueType::Bom);

    expect($item->stockOnHand())->toBe(500.0); // issue moved stock

    ledger()->consumeSlots($order, [
        ['slot' => 'W01', 'item_id' => $item->id, 'start_wt' => 500, 'end_wt' => 120, 'wastage' => 30],
    ]);

    // scale readings recorded, store stock untouched
    expect($item->stockOnHand())->toBe(500.0)
        ->and((float) app(StockService::class)->stockByItem()[$item->id])->toBe(500.0);

    // but the order chain sees them (rule 3)
    $pool = app(StockService::class)->orderReconciliation($order->fresh())
        ->firstWhere('issue_pool', 'Paint Mixing');
    expect($pool['issued'])->toBe(500.0)
        ->and($pool['consumed'])->toBe(380.0)
        ->and($pool['wasted'])->toBe(30.0);
});

test('consumeSlots writes signed consumption rows with slot and scale weights', function () {
    $item = makeItem();
    $order = makeOrder();

    $txns = ledger()->consumeSlots($order, [
        ['slot' => 'P01', 'item_id' => $item->id, 'start_wt' => 800.5, 'end_wt' => 300.25],
    ], makeUser(Role::Painter));

    expect($txns)->toHaveCount(1);
    $txn = $txns[0];
    expect($txn->type)->toBe(TransactionType::Consumption)
        ->and($txn->qty)->toBe(-500.25)
        ->and($txn->slot)->toBe('P01')
        ->and((float) $txn->start_wt)->toBe(800.5)
        ->and((float) $txn->end_wt)->toBe(300.25)
        ->and($txn->rate)->toBe(2.5)      // rule 4 applies at the station too
        ->and($txn->value)->toBe(1250.63);
});

test('end weight above start weight rejects the whole session', function () {
    $itemA = makeItem();
    $itemB = makeItem();
    $order = makeOrder();

    expect(fn () => ledger()->consumeSlots($order, [
        ['slot' => 'W01', 'item_id' => $itemA->id, 'start_wt' => 500, 'end_wt' => 100],
        ['slot' => 'W02', 'item_id' => $itemB->id, 'start_wt' => 100, 'end_wt' => 500],
    ]))->toThrow(ValidationException::class);

    expect($order->transactions()->count())->toBe(0); // atomic
});

test('station submit endpoint records consumption and wastage', function () {
    $this->actingAs($user = makeUser(Role::Painter));
    $item = makeItem();
    $order = makeOrder();

    $this->post(route('station.consume.store', $order), [
        'readings' => [
            ['slot' => 'W01', 'item_id' => $item->id, 'start_wt' => 400, 'end_wt' => 150, 'wastage' => 20],
        ],
    ])->assertRedirect()->assertSessionHas('success');

    expect($order->transactions()->where('type', 'consumption')->count())->toBe(1)
        ->and($order->transactions()->where('type', 'wastage')->count())->toBe(1)
        ->and((float) $order->transactions()->where('type', 'wastage')->value('qty'))->toBe(-20.0)
        ->and($order->transactions()->first()->entered_by_id)->toBe($user->id);
});

test('colour batch entry stores components with computed percentages', function () {
    $this->actingAs(makeUser(Role::Painter));
    $tintA = makeItem();
    $tintB = makeItem();
    $order = makeOrder();

    $this->post(route('station.colour-batch.store', $order), [
        'colour_ref' => 'PANTONE 7463 C',
        'components' => [
            ['item_id' => $tintA->id, 'grams' => 750],
            ['item_id' => $tintB->id, 'grams' => 250],
        ],
    ])->assertRedirect()->assertSessionHas('success');

    $batch = ColourBatch::firstWhere('order_id', $order->id);
    expect($batch->colour_ref)->toBe('PANTONE 7463 C')
        ->and($batch->batch_grams)->toBe(1000.0)
        ->and($batch->ref)->toStartWith('CLR-')
        ->and($batch->components)->toHaveCount(2)
        ->and((float) $batch->components->firstWhere('item_id', $tintA->id)->pct)->toBe(75.0);
});

test('a colour batch from another order cannot be linked', function () {
    $this->actingAs(makeUser(Role::Painter));
    $item = makeItem();
    $order = makeOrder();
    $other = makeOrder();
    $foreign = ColourBatch::create([
        'ref' => 'CLR-TEST-1', 'mixed_at' => now(), 'order_id' => $other->id, 'colour_ref' => 'X',
    ]);

    $this->post(route('station.consume.store', $order), [
        'readings' => [
            ['slot' => 'W01', 'item_id' => $item->id, 'start_wt' => 100, 'end_wt' => 50, 'colour_batch_id' => $foreign->id],
        ],
    ])->assertSessionHasErrors(['readings.0.colour_batch_id']);

    expect($order->transactions()->count())->toBe(0);
});
