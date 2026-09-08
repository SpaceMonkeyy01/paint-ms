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
