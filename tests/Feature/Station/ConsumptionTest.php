<?php

use App\Enums\Role;
use App\Enums\TransactionType;
use App\Models\ColourBatch;
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

test('consumption never moves warehouse stock — no double deduction (rule 1)', function () {
    $item = makeItem();
    $order = makeOrder(['Paint Mixing' => 500.0]);
    ledger()->record(TransactionType::Opening, $item, 1000);
    ledger()->issueToStation([['item_id' => $item->id, 'grams' => 500]]);

    expect($item->stockOnHand())->toBe(500.0); // the station issue moved stock

    ledger()->consumeSlots($order, [
        ['slot' => 'W01', 'item_id' => $item->id, 'grams' => 380],
    ]);

    // consumption recorded against the order, warehouse stock untouched
    expect($item->stockOnHand())->toBe(500.0)
        ->and((float) app(StockService::class)->stockByItem()[$item->id])->toBe(500.0);

    // the order chain tallies used vs BOM (rule 3)
    $pool = app(StockService::class)->orderReconciliation($order->fresh())
        ->firstWhere('issue_pool', 'Paint Mixing');
    expect($pool['consumed'])->toBe(380.0)
        ->and($pool['used'])->toBe(380.0)
        ->and($pool['remaining'])->toBe(120.0);
});

test('consumeSlots writes signed consumption rows with slot and snapshot rate', function () {
    $item = makeItem();
    $order = makeOrder();

    $txns = ledger()->consumeSlots($order, [
        ['slot' => 'P01', 'item_id' => $item->id, 'grams' => 500.25],
    ], makeUser(Role::Painter));

    expect($txns)->toHaveCount(1);
    $txn = $txns[0];
    expect($txn->type)->toBe(TransactionType::Consumption)
        ->and($txn->qty)->toBe(-500.25)
        ->and($txn->slot)->toBe('P01')
        ->and($txn->order_id)->toBe($order->id)
        ->and($txn->rate)->toBe(2.5)      // rule 4 applies at the station too
        ->and($txn->value)->toBe(1250.63);
});

test('litres convert to grams through density (rule 7)', function () {
    $item = makeItem(['density_kg_per_l' => 1.2]);
    $order = makeOrder();

    $txns = ledger()->consumeSlots($order, [
        ['slot' => 'W01', 'item_id' => $item->id, 'litres' => 0.5],
    ]);

    expect($txns[0]->qty)->toBe(-600.0); // 0.5 L × 1.2 kg/L × 1000
});

test('litres without density are rejected — never guess (rule 7)', function () {
    $item = makeItem(['density_kg_per_l' => null]);
    $order = makeOrder();

    expect(fn () => ledger()->consumeSlots($order, [
        ['slot' => 'W01', 'item_id' => $item->id, 'litres' => 0.5],
    ]))->toThrow(ValidationException::class);

    expect($order->transactions()->count())->toBe(0);
});

test('a bad line rolls the whole session back', function () {
    $itemA = makeItem();
    $itemB = makeItem(['density_kg_per_l' => null]);
    $order = makeOrder();

    expect(fn () => ledger()->consumeSlots($order, [
        ['slot' => 'W01', 'item_id' => $itemA->id, 'grams' => 100],
        ['slot' => 'W02', 'item_id' => $itemB->id, 'litres' => 1], // no density
    ]))->toThrow(ValidationException::class);

    expect($order->transactions()->count())->toBe(0); // atomic
});

test('station submit endpoint records consumption for the order', function () {
    $this->actingAs($user = makeUser(Role::Painter));
    $item = makeItem();
    $order = makeOrder(['Paint Mixing' => 500.0]);

    $this->post(route('station.consume.store', $order), [
        'readings' => [
            ['slot' => 'W01', 'item_id' => $item->id, 'grams' => 250],
        ],
    ])->assertRedirect()->assertSessionHas('success')->assertSessionMissing('warning');

    expect($order->transactions()->where('type', 'consumption')->count())->toBe(1)
        ->and((float) $order->transactions()->value('qty'))->toBe(-250.0)
        ->and($order->transactions()->first()->entered_by_id)->toBe($user->id);
});

test('over-BOM consumption goes through with a warning, never blocks (rule 6)', function () {
    $this->actingAs(makeUser(Role::Painter));
    $item = makeItem();
    $order = makeOrder(['Paint Mixing' => 100.0]);

    $this->post(route('station.consume.store', $order), [
        'readings' => [
            ['slot' => 'W01', 'item_id' => $item->id, 'grams' => 150],
        ],
    ])->assertRedirect()->assertSessionHas('success')->assertSessionHas('warning');

    // the entry was written despite exceeding BOM
    expect((float) $order->transactions()->where('type', 'consumption')->sum('qty'))->toBe(-150.0);
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
            ['slot' => 'W01', 'item_id' => $item->id, 'grams' => 50, 'colour_batch_id' => $foreign->id],
        ],
    ])->assertSessionHasErrors(['readings.0.colour_batch_id']);

    expect($order->transactions()->count())->toBe(0);
});
