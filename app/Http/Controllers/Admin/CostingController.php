<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CostingService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CostingController extends Controller
{
    public function index(Request $request, CostingService $costing): Response
    {
        $q = trim((string) $request->query('q', ''));

        return Inertia::render('Admin/Costing/Index', [
            'orders' => $costing->costingList($q ?: null),
            'q' => $q,
        ]);
    }

    public function show(Order $order, CostingService $costing, StockService $stock): Response
    {
        $txns = $order->transactions()
            ->with('item:id,code,name')
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->limit(50)->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'occurred_at' => $t->occurred_at->toIso8601String(),
                'type' => $t->type->value,
                'issue_type' => $t->issue_type?->value,
                'slot' => $t->slot,
                'item' => $t->item?->name,
                'grams' => abs($t->qty),
                'rate' => $t->rate,
                'value' => (float) $t->value,
                'remarks' => $t->remarks,
                'authorized_by' => $t->authorized_by,
                'entered_by' => $t->entered_by ?: $t->enteredBy?->name,
            ]);

        return Inertia::render('Admin/Costing/Show', [
            'order' => $order->only(['id', 'code', 'finish', 'total_area', 'surface_area', 'bom_total_cost']),
            'costing' => $costing->orderCosting($order),
            'pools' => $stock->orderReconciliation($order)->values(),
            'transactions' => $txns,
        ]);
    }
}
