<?php

namespace App\Http\Controllers\Station;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Station\StoreConsumptionRequest;
use App\Models\Item;
use App\Models\Order;
use App\Services\LedgerService;
use App\Services\SlotTemplate;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsumptionController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $sumOf = fn (TransactionType $type) => fn ($t) => $t->where('type', $type->value);

        $orders = Order::query()
            ->withSum(['transactions as issued_grams' => $sumOf(TransactionType::Issue)], 'qty')
            ->withSum(['transactions as consumed_grams' => $sumOf(TransactionType::Consumption)], 'qty')
            ->withSum(['transactions as wasted_grams' => $sumOf(TransactionType::Wastage)], 'qty')
            ->when($q !== '', fn ($query) => $query->whereRaw('LOWER(code) LIKE ?', ['%'.mb_strtolower($q).'%']))
            ->when($q === '', fn ($query) => $query->has('transactions')) // default list: orders with movement
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'code' => $o->code,
                'finish' => $o->finish,
                'issued_grams' => abs((float) $o->issued_grams),
                'consumed_grams' => abs((float) $o->consumed_grams) + abs((float) $o->wasted_grams),
            ]);

        return Inertia::render('Station/Consume/Index', ['orders' => $orders, 'q' => $q]);
    }

    public function show(Order $order, StockService $stock): Response
    {
        $itemsByPool = Item::where('is_active', true)->whereNotNull('issue_pool')
            ->orderBy('name')->get()
            ->map(fn (Item $i) => [
                'id' => $i->id,
                'code' => $i->code,
                'name' => $i->name,
                'issue_pool' => $i->issue_pool,
                'density_kg_per_l' => $i->density_kg_per_l, // null → litres unknown (rule 7)
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

        return Inertia::render('Station/Consume/Show', [
            'order' => $order->only(['id', 'code', 'finish', 'colour_note']),
            'pools' => $stock->orderReconciliation($order)->values(),
            'slotTemplate' => SlotTemplate::for($order->finish),
            'classPools' => SlotTemplate::CLASS_POOLS,
            'itemsByPool' => $itemsByPool,
            'batches' => $batches,
            'recent' => $recent,
        ]);
    }

    public function store(StoreConsumptionRequest $request, Order $order, LedgerService $ledger): RedirectResponse
    {
        $readings = $request->validated()['readings'];

        $ledger->consumeSlots($order, $readings, $request->user());

        return back()->with('success', count($readings).' slot reading(s) recorded for '.$order->code.'.');
    }
}
