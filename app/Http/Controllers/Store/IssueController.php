<?php

namespace App\Http\Controllers\Store;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreIssueRequest;
use App\Models\Item;
use App\Models\StationSlot;
use App\Models\Transaction;
use App\Services\LedgerService;
use App\Services\SlotTemplate;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Store → station replenishment. Issues carry no order (rule 3): when a slot
 * runs low the painter asks for a new container and the store issues it here.
 * Order attribution happens at the station, on consumption entries.
 */
class IssueController extends Controller
{
    public function index(StockService $stock): Response
    {
        $warehouse = $stock->stockByItem();

        $board = $stock->stationList()->map(fn (Item $i) => [
            'id' => $i->id,
            'code' => $i->code,
            'name' => $i->name,
            'issue_pool' => $i->issue_pool,
            'station_on_hand' => (float) $i->station_on_hand,
            'station_status' => $i->station_status,
            'stock_on_hand' => (float) ($warehouse[$i->id] ?? 0),
        ]);

        $slots = StationSlot::where('is_active', true)->orderBy('position')->get()
            ->map(fn (StationSlot $s) => [
                'id' => $s->id,
                'slot' => $s->slot,
                'class' => $s->class,
                'item_id' => $s->item_id,
            ]);

        $recent = Transaction::query()
            ->where('type', TransactionType::Issue->value)
            ->with('item:id,code,name')
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->limit(20)->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'occurred_at' => $t->occurred_at->toIso8601String(),
                'item' => $t->item?->name,
                'grams' => abs($t->qty),
                'issue_pool' => $t->issue_pool,
                'remarks' => $t->remarks,
            ]);

        return Inertia::render('Store/Issue/Index', [
            'board' => $board->groupBy('issue_pool'),
            'slots' => $slots,
            'classPools' => SlotTemplate::CLASS_POOLS,
            'recentIssues' => $recent,
        ]);
    }

    public function store(StoreIssueRequest $request, LedgerService $ledger): RedirectResponse
    {
        $data = $request->validated();

        // A line that targets a slot re-loads it: validate the item fits the
        // slot's class BEFORE any ledger write, so a bad line rejects the submit.
        $slotUpdates = [];
        foreach ($data['lines'] as $line) {
            if (! empty($line['slot_id'])) {
                $slot = StationSlot::findOrFail($line['slot_id']);
                $item = Item::findOrFail($line['item_id']);
                if (! in_array($item->issue_pool, SlotTemplate::CLASS_POOLS[$slot->class] ?? [], true)) {
                    throw ValidationException::withMessages([
                        'lines' => "{$item->name} ({$item->issue_pool}) does not belong in slot {$slot->slot}.",
                    ]);
                }
                $slotUpdates[$slot->id] = $item->id;
            }
        }

        DB::transaction(function () use ($ledger, $data, $request, $slotUpdates) {
            $ledger->issueToStation(
                lines: $data['lines'],
                enteredBy: $request->user(),
                remarks: $data['remarks'] ?? null,
            );

            foreach ($slotUpdates as $slotId => $itemId) {
                StationSlot::whereKey($slotId)->update(['item_id' => $itemId]);
            }
        });

        return back()->with('success', count($data['lines']).' line(s) issued to the station.');
    }
}
