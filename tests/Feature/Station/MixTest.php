<?php

use App\Enums\Role;
use App\Models\ColourBatch;
use App\Models\PantoneColour;
use App\Services\StockService;

test('saving a mix records the recipe AND the order consumption in one step', function () {
    $this->actingAs($user = makeUser(Role::Painter));
    $white = makeItem();
    $blue = makeItem();
    $order = makeOrder(['Paint Mixing' => 1000.0]);

    $this->post(route('station.mix.store'), [
        'order_id' => $order->id,
        'colour_ref' => 'PANTONE 7463 C',
        'hex' => '#002554',
        'components' => [
            ['item_id' => $white->id, 'grams' => 500],
            ['item_id' => $blue->id, 'grams' => 200],
        ],
    ])->assertRedirect(route('station.consume.show', $order))->assertSessionHas('success');

    // the recipe
    $batch = ColourBatch::firstWhere('order_id', $order->id);
    expect($batch->colour_ref)->toBe('PANTONE 7463 C')
        ->and($batch->hex)->toBe('#002554')
        ->and($batch->batch_grams)->toBe(700.0)
        ->and($batch->ref)->toStartWith('CLR-')
        ->and($batch->matcher)->toBe($user->email)
        ->and((float) $batch->components->firstWhere('item_id', $white->id)->pct)->toBe(71.43);

    // the consumption, linked to the batch — entered once, recorded twice-over
    $consumed = $order->transactions()->where('type', 'consumption')->get();
    expect($consumed)->toHaveCount(2)
        ->and((float) $consumed->sum('qty'))->toBe(-700.0)
        ->and($consumed->pluck('colour_batch_id')->unique()->all())->toBe([$batch->id])
        ->and($consumed->first()->entered_by_id)->toBe($user->id);

    // station drained, warehouse untouched (rule 1)
    expect((float) (app(StockService::class)->stationStockByItem()[$white->id] ?? 0))->toBe(-500.0)
        ->and($white->stockOnHand())->toBe(0.0);
});

test('an over-BOM mix saves with a warning, never blocks (rule 6)', function () {
    $this->actingAs(makeUser(Role::Painter));
    $item = makeItem();
    $order = makeOrder(['Paint Mixing' => 100.0]);

    $this->post(route('station.mix.store'), [
        'order_id' => $order->id,
        'colour_ref' => 'PANTONE 185 C',
        'components' => [['item_id' => $item->id, 'grams' => 150]],
    ])->assertRedirect()->assertSessionHas('success')->assertSessionHas('warning');

    expect((float) $order->transactions()->where('type', 'consumption')->sum('qty'))->toBe(-150.0);
});

test('an invalid component rejects the whole mix — nothing half-saved', function () {
    $this->actingAs(makeUser(Role::Painter));
    $item = makeItem();
    $order = makeOrder();

    $this->post(route('station.mix.store'), [
        'order_id' => $order->id,
        'colour_ref' => 'PANTONE 185 C',
        'components' => [
            ['item_id' => $item->id, 'grams' => 100],
            ['item_id' => 999999, 'grams' => 50],
        ],
    ])->assertSessionHasErrors(['components.1.item_id']);

    expect(ColourBatch::count())->toBe(0)
        ->and($order->transactions()->count())->toBe(0);
});

test('pantone typeahead returns matching codes with hex', function () {
    $this->actingAs(makeUser(Role::Painter));
    PantoneColour::create(['code' => 'PANTONE 7463 C', 'hex' => '#002554']);
    PantoneColour::create(['code' => 'PANTONE 185 C', 'hex' => '#E4002B']);

    $this->getJson(route('station.pantones', ['q' => '7463']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['code' => 'PANTONE 7463 C', 'hex' => '#002554']);

    // too-short queries return nothing rather than the whole book
    $this->getJson(route('station.pantones', ['q' => '7']))->assertOk()->assertJsonCount(0);
});

test('pantone seeder loads the book once and is idempotent', function () {
    $this->seed(Database\Seeders\PantoneColourSeeder::class);
    $count = PantoneColour::count();
    expect($count)->toBeGreaterThan(3000);

    $this->seed(Database\Seeders\PantoneColourSeeder::class);
    expect(PantoneColour::count())->toBe($count);
});

test('only painters and admin can create mixes', function () {
    $this->actingAs(makeUser(Role::Store));
    $this->get(route('station.mix.create'))->assertForbidden();

    $this->actingAs(makeUser(Role::Painter));
    $this->get(route('station.mix.create'))->assertOk();
});
