<?php

namespace App\Http\Controllers\Store;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreIssueRequest;
use App\Models\Item;
use App\Models\Transaction;
use App\Services\LedgerService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
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
            'recentIssues' => $recent,
        ]);
    }

    public function store(StoreIssueRequest $request, LedgerService $ledger): RedirectResponse
    {
        $data = $request->validated();

        $ledger->issueToStation(
            lines: $data['lines'],
            enteredBy: $request->user(),
            remarks: $data['remarks'] ?? null,
        );

        return back()->with('success', count($data['lines']).' line(s) issued to the station.');
    }
}
