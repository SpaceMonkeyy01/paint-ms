<?php

namespace App\Http\Controllers\Store;

use App\Enums\IssueType;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreIssueRequest;
use App\Models\Item;
use App\Models\Order;
use App\Services\LedgerService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $orders = Order::query()
            ->withSum('bomLines as bom_grams', 'allocated_qty')
            ->withSum(['transactions as issued_grams' => fn ($t) => $t->where('type', TransactionType::Issue->value)], 'qty')
            ->when($q !== '', fn ($query) => $query->where('code', 'like', "%{$q}%"))
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'code' => $o->code,
                'finish' => $o->finish,
                'bom_grams' => (float) $o->bom_grams,
                'issued_grams' => abs((float) $o->issued_grams),
                'remaining_grams' => max((float) $o->bom_grams - abs((float) $o->issued_grams), 0),
            ]);

        return Inertia::render('Store/Issue/Index', ['orders' => $orders, 'q' => $q]);
    }

    public function show(Order $order, StockService $stock): Response
    {
        $stockByItem = $stock->stockByItem();

        $itemsByPool = Item::where('is_active', true)->whereNotNull('issue_pool')
            ->orderBy('name')->get()
            ->map(fn (Item $i) => [
                'id' => $i->id,
                'code' => $i->code,
                'name' => $i->name,
                'issue_pool' => $i->issue_pool,
                'stock_on_hand' => (float) ($stockByItem[$i->id] ?? 0),
            ])
            ->groupBy('issue_pool');

        $recent = $order->transactions()
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
                'issue_type' => $t->issue_type?->value,
            ]);

        return Inertia::render('Store/Issue/Show', [
            'order' => $order->only(['id', 'code', 'finish', 'bom_total_cost']),
            'pools' => $stock->orderReconciliation($order)->values(),
            'itemsByPool' => $itemsByPool,
            'recentIssues' => $recent,
        ]);
    }

    public function store(StoreIssueRequest $request, Order $order, LedgerService $ledger): RedirectResponse
    {
        $data = $request->validated();

        $ledger->issueLines(
            order: $order,
            lines: $data['lines'],
            issueType: IssueType::from($data['issue_type']),
            enteredBy: $request->user(),
            authorizedBy: $data['authorized_by'] ?? null,
            remarks: $data['remarks'] ?? null,
        );

        return back()->with('success', count($data['lines']).' line(s) issued to '.$order->code.'.');
    }
}
