<?php

use App\Enums\Role;
use App\Services\MixService;

function makeMix(\App\Models\Order $order, string $colourRef, array $components, ?string $hex = null): \App\Models\ColourBatch
{
    return app(MixService::class)->create(
        order: $order,
        colourRef: $colourRef,
        hex: $hex,
        components: $components,
    );
}

test('store users cannot reach the formulation library, painters and admin can', function () {
    $this->actingAs(makeUser(Role::Store));
    $this->get(route('station.recipes'))->assertForbidden();

    foreach ([Role::Painter, Role::Admin] as $role) {
        $this->actingAs(makeUser($role));
        $this->get(route('station.recipes'))->assertOk();
    }
});

test('mixes group by colour with the newest recipe first', function () {
    $this->actingAs(makeUser(Role::Painter));
    $white = makeItem();
    $blue = makeItem();
    $orderA = makeOrder();
    $orderB = makeOrder();

    makeMix($orderA, 'PANTONE 7463 C', [
        ['item_id' => $white->id, 'grams' => 700],
        ['item_id' => $blue->id, 'grams' => 300],
    ], '#002554');
    $this->travel(1)->hour();
    $newest = makeMix($orderB, 'PANTONE 7463 C', [
        ['item_id' => $white->id, 'grams' => 650],
        ['item_id' => $blue->id, 'grams' => 350],
    ]);
    makeMix($orderA, 'PANTONE 185 C', [['item_id' => $blue->id, 'grams' => 100]]);

    $colours = collect($this->get(route('station.recipes'))
        ->assertOk()
        ->viewData('page')['props']['colours']);

    expect($colours)->toHaveCount(2);

    $c = $colours->firstWhere('colour_ref', 'PANTONE 7463 C');
    expect($c['mix_count'])->toBe(2)
        ->and($c['hex'])->toBe('#002554') // carried from the batch that had one
        ->and($c['latest']['ref'])->toBe($newest->ref)
        ->and($c['latest']['components'][0]['pct'])->toBe(65.0)
        ->and($c['history'])->toHaveCount(1);
});

test('colour detail page aggregates every batch with consistency and cost', function () {
    $this->actingAs(makeUser(Role::Painter));
    $white = makeItem();          // rate 2.5 Rs/g
    $blue = makeItem();
    $order = makeOrder();

    makeMix($order, 'PANTONE 7463 C', [
        ['item_id' => $white->id, 'grams' => 700],
        ['item_id' => $blue->id, 'grams' => 300],
    ], '#002554');
    $this->travel(1)->hour();
    makeMix($order, 'PANTONE 7463 C', [
        ['item_id' => $white->id, 'grams' => 650],
        ['item_id' => $blue->id, 'grams' => 350],
    ]);

    $props = $this->get(route('station.recipes.show', ['colour' => 'PANTONE 7463 C']))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['summary']['mix_count'])->toBe(2)
        ->and($props['summary']['total_grams'])->toBe(2000.0)
        ->and($props['summary']['total_cost'])->toBe(5000.0)   // 2000 g × 2.5 Rs, snapshot values
        ->and($props['summary']['cost_per_kg'])->toBe(2500.0)
        ->and($props['summary']['avg_batch_grams'])->toBe(1000.0);

    $whiteRow = collect($props['components'])->firstWhere('code', $white->code);
    expect($whiteRow['used_in'])->toBe(2)
        ->and($whiteRow['avg_pct'])->toBe(67.5)
        ->and($whiteRow['min_pct'])->toBe(65.0)
        ->and($whiteRow['max_pct'])->toBe(70.0);

    expect($props['batches'])->toHaveCount(2)
        ->and($props['batches'][0]['cost'])->toBe(2500.0);
});

test('unknown colour on the detail page is a 404', function () {
    $this->actingAs(makeUser(Role::Painter));
    $this->get(route('station.recipes.show', ['colour' => 'PANTONE NOPE C']))->assertNotFound();
    $this->get(route('station.recipes.show'))->assertNotFound();
});

test('search filters the library by colour reference', function () {
    $this->actingAs(makeUser(Role::Painter));
    $item = makeItem();
    $order = makeOrder();
    makeMix($order, 'PANTONE 7463 C', [['item_id' => $item->id, 'grams' => 100]]);
    makeMix($order, 'PANTONE 185 C', [['item_id' => $item->id, 'grams' => 100]]);

    $colours = collect($this->get(route('station.recipes', ['q' => '185']))
        ->assertOk()
        ->viewData('page')['props']['colours']);

    expect($colours)->toHaveCount(1)
        ->and($colours[0]['colour_ref'])->toBe('PANTONE 185 C');
});
