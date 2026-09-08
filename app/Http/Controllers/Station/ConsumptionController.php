<?php

namespace App\Http\Controllers\Station;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Station\StoreConsumptionRequest;
use App\Models\Item;
use App\Models\Order;
use App\Models\StationSlot;
use App\Services\LedgerService;
use App\Services\SlotTemplate;
use App\Services\StationBoardService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsumptionController extends Controller
{
    public function index(Request $request, StationBoardService $board): Response
    {
        $q = trim((string) $request->query('q', ''));

        // With a search: flat result list. Without: the kanban board.
        if ($q !== '') {
            $sumOf = fn (TransactionType $type) => fn ($t) => $t->where('type', $type->value);

            $orders = Order::query()
                ->withSum('bomLines as bom_grams', 'allocated_qty')
                ->withSum(['transactions as consumed_grams' => $sumOf(TransactionType::Consumption)], 'qty')
                ->withSum(['transactions as wasted_grams' => $sumOf(TransactionType::Wastage)], 'qty')
                ->whereRaw('LOWER(code) LIKE ?', ['%'.mb_strtolower($q).'%'])
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'code' => $o->code,
                    'finish' => $o->finish,
                    'bom_grams' => (float) $o->bom_grams,
                    'used_grams' => abs((float) $o->consumed_grams) + abs((float) $o->wasted_grams),
                ]);

            return Inertia::render('Station/Consume/Index', ['orders' => $orders, 'lanes' => null, 'q' => $q]);
        }

        return Inertia::render('Station/Consume/Index', [
            'orders' => null,
            'lanes' => $board->lanes(),
            'q' => $q,
        ]);
    }

    public function show(Order $order, StockService $stock): Response
    {
        $station = $stock->stationStockByItem();

        $itemsByPool = Item::where('is_active', true)->whereNotNull('issue_pool')
            ->orderBy('name')->get()
            ->map(fn (Item $i) => [
                'id' => $i->id,
                'code' => $i->code,
                'name' => $i->name,
                'issue_pool' => $i->issue_pool,
                'density_kg_per_l' => $i->density_kg_per_l, // null → litres entry disabled (rule 7)
                'station_on_hand' => (float) ($station[$i->id] ?? 0),
            ])
            ->groupBy('issue_pool');

        $batches = $order->colourBatches()->with('components.item:id,code,name')->latest('mixed_at')->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'ref' => $b->ref,
                'colour_ref' => $b->colour_ref,
                'hex' => $b->hex,
                'batch_grams' => $b->batch_grams,
                'components' => $b->components->map(fn ($c) => [
                    'item' => $c->item?->name, 'grams' => $c->grams, 'pct' => $c->pct,
                ]),
            ]);

        $recent = $order->transactions()
            ->whereIn('type', [TransactionType::Consumption->value, TransactionType::Wastage->value])
            ->with('item:id,code,name')
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->limit(30)->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type->value,
                'slot' => $t->slot,
                'item' => $t->item?->name,
                'grams' => abs($t->qty),
                'occurred_at' => $t->occurred_at->toIso8601String(),
            ]);

        // the physical rack: each slot with the item currently loaded in it
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
                    'density_kg_per_l' => $s->item->density_kg_per_l,
                    'station_on_hand' => (float) ($station[$s->item->id] ?? 0),
                ] : null,
            ]);

        return Inertia::render('Station/Consume/Show', [
            'order' => $order->only(['id', 'code', 'finish', 'colour_note', 'due_date']),
            'pools' => $stock->orderReconciliation($order)->values(),
            'slots' => $slots,
            'classPools' => SlotTemplate::CLASS_POOLS,
            'itemsByPool' => $itemsByPool,
            'batches' => $batches,
            'recent' => $recent,
        ]);
    }

    public function store(
        StoreConsumptionRequest $request,
        Order $order,
        LedgerService $ledger,
        StockService $stock,
    ): RedirectResponse {
        $readings = $request->validated()['readings'];

        $ledger->consumeSlots($order, $readings, $request->user());

        // Warn-only BOM control (rule 6): flag pools this entry pushed over allowance.
        $over = $stock->orderReconciliation($order)
            ->filter(fn ($p) => $p['bom_qty'] > 0 && $p['used_variance'] > 0.01)
            ->map(fn ($p) => sprintf('%s +%.0f g', $p['issue_pool'], $p['used_variance']));

        $response = back()->with('success', count($readings).' entr'.(count($readings) === 1 ? 'y' : 'ies').' recorded for '.$order->code.'.');

        if ($over->isNotEmpty()) {
            $response->with('warning', 'Over BOM: '.$over->implode(', ').' — flagged for admin review.');
        }

        return $response;
    }
}
