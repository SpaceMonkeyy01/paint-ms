<?php

namespace App\Http\Controllers\Station;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StationSlot;
use App\Services\SlotTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Slot rack management. Both store (on refill) and painters (at the station)
 * may load/swap what sits in a slot; the rack itself is flexible (add slots).
 */
class StationSlotController extends Controller
{
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
}
