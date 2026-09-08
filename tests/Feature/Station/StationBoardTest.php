<?php

use App\Enums\Role;
use App\Services\StationBoardService;

function boardLanes(): array
{
    return app(StationBoardService::class)->lanes();
}

test('lanes derive from tags and the ledger', function () {
    $item = makeItem();

    $queued = makeOrder(['Paint Mixing' => 500.0]);
    $repaint = makeOrder(['Paint Mixing' => 500.0]);
    $repaint->update(['airtable_tags' => ['Repaint needed']]);
    $inPaint = makeOrder(['Paint Mixing' => 500.0]);
    $doneByUse = makeOrder(['Paint Mixing' => 200.0]);
    $doneByTag = makeOrder(['Paint Mixing' => 500.0]);
    $doneByTag->update(['airtable_tags' => ['Done']]);

    ledger()->consumeSlots($inPaint, [['slot' => 'W01', 'item_id' => $item->id, 'grams' => 100]]);
    ledger()->consumeSlots($doneByUse, [['slot' => 'W01', 'item_id' => $item->id, 'grams' => 200]]);

    $lanes = boardLanes();

    expect($lanes['queue']->pluck('code'))->toContain($queued->code)
        ->and($lanes['repaint']->pluck('code'))->toContain($repaint->code)
        ->and($lanes['in_paint']->pluck('code'))->toContain($inPaint->code)
        ->and($lanes['done']->pluck('code'))->toContain($doneByUse->code)->toContain($doneByTag->code);

    // each order sits in exactly one lane
    $all = collect($lanes)->flatMap(fn ($l) => $l->pluck('code'));
    expect($all->duplicates())->toBeEmpty();
});

test('inside a lane, overdue comes first, then nearest due date, undated last', function () {
    $overdue = makeOrder(['Paint Mixing' => 100.0]);
    $overdue->update(['due_date' => now()->subDays(3)]);
    $soon = makeOrder(['Paint Mixing' => 100.0]);
    $soon->update(['due_date' => now()->addDays(2)]);
    $later = makeOrder(['Paint Mixing' => 100.0]);
    $later->update(['due_date' => now()->addDays(10)]);
    $undated = makeOrder(['Paint Mixing' => 100.0]);

    $codes = boardLanes()['queue']->pluck('code')->values();

    expect($codes->all())->toBe([$overdue->code, $soon->code, $later->code, $undated->code]);

    $card = boardLanes()['queue']->first();
    expect($card['days_overdue'])->toBe(3);
});

test('the station home renders the board, a search renders the flat list', function () {
    $this->actingAs(makeUser(Role::Painter));
    $order = makeOrder(['Paint Mixing' => 100.0]);

    $props = $this->get(route('station.consume.index'))->assertOk()->viewData('page')['props'];
    expect($props['lanes'])->not->toBeNull()
        ->and($props['orders'])->toBeNull()
        ->and(collect($props['lanes']['queue'])->pluck('code'))->toContain($order->code);

    $props = $this->get(route('station.consume.index', ['q' => $order->code]))->assertOk()->viewData('page')['props'];
    expect($props['lanes'])->toBeNull()
        ->and(collect($props['orders'])->pluck('code'))->toContain($order->code);
});
