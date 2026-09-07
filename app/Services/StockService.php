<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Item;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockService
{
    /** item_id => stock on hand (grams), one query. Stock-moving types only —
     *  consumption/wastage are order-side usage of already-issued material. */
    public function stockByItem(): Collection
    {
        return Transaction::query()
            ->whereIn('type', TransactionType::stockMoving())
            ->select('item_id', DB::raw('SUM(qty) as qty'))
            ->groupBy('item_id')
            ->pluck('qty', 'item_id');
    }

    /** Items with status OK / LOW / OUT, like the legacy Dashboard. */
    public function stockList(): Collection
    {
        $stock = $this->stockByItem();

        return Item::where('is_active', true)->orderBy('bom_category')->orderBy('name')->get()
            ->map(function (Item $item) use ($stock) {
                $qty = (float) ($stock[$item->id] ?? 0);
                $item->setAttribute('stock_on_hand', $qty);
                $item->setAttribute('stock_value', round($qty * $item->rate_per_uom, 2));
                $item->setAttribute('status', $qty <= 0 ? 'OUT' : ($qty < $item->min_level ? 'LOW' : 'OK'));
                return $item;
            });
    }

    /**
     * Per-order reconciliation: for each issue pool, BOM allocated vs issued vs consumed.
     * Clubbed categories (Epoxy Set) collapse to the pool total.
     */
    public function orderReconciliation(Order $order): Collection
    {
        // Ignored categories (Paint Miscellaneous cost → pool IGNORE) are audit-only
        // lines; unknown categories have a null pool until an admin maps them.
        $ignored = \App\Models\BomCategory::where('ignored', true)->pluck('issue_pool')->all();

        $allocated = $order->bomLines()
            ->whereNotNull('issue_pool')
            ->whereNotIn('issue_pool', $ignored ?: [''])
            ->select('issue_pool', DB::raw('SUM(allocated_qty) as qty'))
            ->groupBy('issue_pool')->pluck('qty', 'issue_pool');

        $moved = $order->transactions()
            ->select('issue_pool', 'type', DB::raw('SUM(qty) as qty'))
            ->whereIn('type', [TransactionType::Issue->value, TransactionType::Consumption->value, TransactionType::Wastage->value])
            ->groupBy('issue_pool', 'type')->get()
            ->groupBy('issue_pool');

        return collect($allocated->keys())->merge($moved->keys())->unique()->values()
            ->map(function (string $pool) use ($allocated, $moved) {
                $rows = $moved->get($pool, collect())->keyBy(fn ($r) => $r->type->value);
                $issued = abs((float) ($rows->get('issue')?->qty ?? 0));
                $consumed = abs((float) ($rows->get('consumption')?->qty ?? 0));
                $wasted = abs((float) ($rows->get('wastage')?->qty ?? 0));
                $bom = (float) ($allocated[$pool] ?? 0);
                return [
                    'issue_pool' => $pool,
                    'bom_qty' => $bom,
                    'issued' => $issued,
                    'consumed' => $consumed,
                    'wasted' => $wasted,
                    'issue_variance' => $issued - $bom,      // + means over BOM
                    'remaining' => max($bom - $issued, 0),
                ];
            });
    }
}
