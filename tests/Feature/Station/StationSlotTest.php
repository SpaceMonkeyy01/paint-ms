<?php

use App\Enums\Role;
use App\Models\StationSlot;
use Database\Seeders\StationSlotSeeder;

test('the default rack seeds once and is idempotent', function () {
    $this->seed(StationSlotSeeder::class);
    expect(StationSlot::count())->toBe(8)
        ->and(StationSlot::where('class', 'A')->count())->toBe(4);

    $this->seed(StationSlotSeeder::class);
    expect(StationSlot::count())->toBe(8);
});

test('a painter can load an item into a slot, and the load persists', function () {
    $this->actingAs(makeUser(Role::Painter));
    $this->seed(StationSlotSeeder::class);
    $item = makeItem(); // Paint Mixing → W class
    $slot = StationSlot::firstWhere('slot', 'W01');

    $this->patch(route('station.slots.update', $slot), ['item_id' => $item->id])
        ->assertRedirect()->assertSessionHas('success');

    expect($slot->fresh()->item_id)->toBe($item->id);

    // the consumption screen serves the loaded rack
    $order = makeOrder();
    $slots = collect($this->get(route('station.consume.show', $order))
        ->assertOk()->viewData('page')['props']['slots']);
    expect($slots->firstWhere('slot', 'W01')['item']['id'])->toBe($item->id);
});

test('an item cannot be loaded into a slot of the wrong class', function () {
    $this->actingAs(makeUser(Role::Painter));
    $this->seed(StationSlotSeeder::class);
    $tint = makeItem(); // Paint Mixing does not belong in a P (Epoxy Set) slot
    $slot = StationSlot::firstWhere('slot', 'P01');

    $this->patch(route('station.slots.update', $slot), ['item_id' => $tint->id])
        ->assertSessionHasErrors(['item_id']);

    expect($slot->fresh()->item_id)->toBeNull();
});

test('a store issue targeting a slot re-loads that slot', function () {
    $this->actingAs(makeUser(Role::Store));
    $this->seed(StationSlotSeeder::class);
    $item = makeItem();
    ledger()->record(App\Enums\TransactionType::Opening, $item, 1000);
    $slot = StationSlot::firstWhere('slot', 'W02');

    $this->post(route('store.issue.store'), [
        'lines' => [['item_id' => $item->id, 'grams' => 500, 'slot_id' => $slot->id]],
    ])->assertRedirect()->assertSessionHas('success');

    expect($slot->fresh()->item_id)->toBe($item->id)
        ->and($item->stockOnHand())->toBe(500.0);
});

test('a wrong-class slot target rejects the whole issue — nothing moves', function () {
    $this->actingAs(makeUser(Role::Store));
    $this->seed(StationSlotSeeder::class);
    $item = makeItem();
    ledger()->record(App\Enums\TransactionType::Opening, $item, 1000);
    $slot = StationSlot::firstWhere('slot', 'P01'); // Paint Mixing item, Epoxy slot

    $this->post(route('store.issue.store'), [
        'lines' => [['item_id' => $item->id, 'grams' => 500, 'slot_id' => $slot->id]],
    ])->assertSessionHasErrors(['lines']);

    expect($item->stockOnHand())->toBe(1000.0)
        ->and($slot->fresh()->item_id)->toBeNull();
});

test('adding a slot takes the next free code in its class', function () {
    $this->actingAs(makeUser(Role::Painter));
    $this->seed(StationSlotSeeder::class);

    $this->post(route('station.slots.store'), ['class' => 'W'])
        ->assertRedirect()->assertSessionHas('success');

    expect(StationSlot::where('class', 'W')->pluck('slot')->all())->toBe(['W01', 'W02', 'W03']);
});

test('ad-hoc X-slot readings are accepted by the consumption endpoint', function () {
    $this->actingAs(makeUser(Role::Painter));
    $item = makeItem();
    $order = makeOrder(['Paint Mixing' => 500.0]);

    $this->post(route('station.consume.store', $order), [
        'readings' => [
            ['slot' => 'X01', 'item_id' => $item->id, 'grams' => 120],
        ],
    ])->assertRedirect()->assertSessionHas('success');

    expect((float) $order->transactions()->where('slot', 'X01')->value('qty'))->toBe(-120.0);
});
