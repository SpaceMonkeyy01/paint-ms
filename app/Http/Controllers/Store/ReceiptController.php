<?php

namespace App\Http\Controllers\Store;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreReceiptRequest;
use App\Models\Item;
use App\Services\LedgerService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function create(StockService $stock): Response
    {
        $stockByItem = $stock->stockByItem();

        $items = Item::where('is_active', true)->orderBy('name')->get()
            ->map(fn (Item $i) => [
                'id' => $i->id,
                'code' => $i->code,
                'name' => $i->name,
                'rate_per_uom' => (float) $i->rate_per_uom,
                'stock_on_hand' => (float) ($stockByItem[$i->id] ?? 0),
            ]);

        return Inertia::render('Store/Receipts', ['items' => $items]);
    }

    public function store(StoreReceiptRequest $request, LedgerService $ledger): RedirectResponse
    {
        $data = $request->validated();
        $item = Item::findOrFail($data['item_id']);

        $ledger->record(
            type: TransactionType::from($data['type']),
            item: $item,
            qty: (float) $data['grams'],
            enteredBy: $request->user(),
            remarks: $data['remarks'] ?? null,
            externalRef: $data['external_ref'] ?? null,
            rate: isset($data['rate']) ? (float) $data['rate'] : null,
        );

        $verb = $data['type'] === 'receipt' ? 'received' : 'adjusted';

        return back()->with('success', abs((float) $data['grams'])." g {$verb} for {$item->name}.");
    }
}
