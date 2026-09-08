<?php

namespace App\Http\Controllers\Station;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StationSlot;
use App\Services\SlotTemplate;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Slot rack management. The rack is configured HERE (and by store refills that
 * target a slot) — the consumption and mix screens only read the loaded rack.
 */
class StationSlotController extends Controller
{
    /** The rack manager page: configure what sits in each slot. */
    public function index(StockService $stock): Response
    {
        $station = $stock->stationStockByItem();

        $slots = StationSlot::where('is_active', true)->with('item')
            ->orderBy('position')->get()
            ->map(fn (StationSlot $s) => [
                'id' => $s->id,
                'slot' => $s->slot,
                'class' => $s->class,
                'item' => $s->item ? [
                    'id' => $s->item->id,
                    'code' => $s->item->code,
                    'name' => $s->item->name,
                    'issue_pool' => $s->item->issue_pool,
                    'station_on_hand' => (float) ($station[$s->item->id] ?? 0),
                ] : null,
            ]);

        $itemsByPool = Item::where('is_active', true)->whereNotNull('issue_pool')
            ->orderBy('name')->get()
            ->map(fn (Item $i) => [
                'id' => $i->id,
                'code' => $i->code,
                'name' => $i->name,
                'issue_pool' => $i->issue_pool,
                'station_on_hand' => (float) ($station[$i->id] ?? 0),
            ])
            ->groupBy('issue_pool');

        return Inertia::render('Station/Slots/Index', [
            'slots' => $slots,
            'classPools' => SlotTemplate::CLASS_POOLS,
            'itemsByPool' => $itemsByPool,
        ]);
    }

    /** Load or swap the item in a slot (null unloads it). */
    public function update(Request $request, StationSlot $slot): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['nullable', 'integer', Rule::exists('items', 'id')->where('is_active', true)],
        ]);

        if ($data['item_id']) {
            $item = Item::findOrFail($data['item_id']);
            $allowed = SlotTemplate::CLASS_POOLS[$slot->class] ?? [];
            if (! in_array($item->issue_pool, $allowed, true)) {
                throw ValidationException::withMessages([
                    'item_id' => "{$item->name} ({$item->issue_pool}) does not belong in a {$slot->class} slot.",
                ]);
            }
        }

        $slot->update(['item_id' => $data['item_id'] ?? null]);

        return back()->with('success', $data['item_id']
            ? "{$slot->slot} loaded with ".Item::find($data['item_id'])->name.'.'
            : "{$slot->slot} unloaded.");
    }

    /** Add a slot of a class to the rack (next free code). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['class' => ['required', Rule::in(['W', 'P', 'A'])]]);

        $n = 1 + (int) StationSlot::where('class', $data['class'])->pluck('slot')
            ->map(fn ($s) => (int) substr($s, 1))->max();
        $code = $data['class'].str_pad((string) $n, 2, '0', STR_PAD_LEFT);

        StationSlot::create([
            'slot' => $code,
            'class' => $data['class'],
            'position' => 1 + (int) StationSlot::max('position'),
        ]);

        return back()->with('success', "Slot {$code} added.");
    }

    /** Remove a slot from the rack (deactivates — ledger history keeps its code). */
    public function destroy(StationSlot $slot): RedirectResponse
    {
        $slot->update(['is_active' => false, 'item_id' => null]);

        return back()->with('success', "Slot {$slot->slot} removed from the rack.");
    }
}
