<?php

namespace App\Services;

use App\Enums\IssueType;
use App\Enums\TransactionType;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Order costing. Rule 4: every figure is a SUM(transactions.value) — the
 * rate snapshotted when the material moved — never current rate × old qty
 * (the legacy Order_Costing ~100× bug).
 */
class CostingService
{
    /** Eloquent withSum constraints reused by list() and show(). */
    private function sums(): array
    {
        $ofType = fn (TransactionType $t) => fn ($q) => $q->where('type', $t->value);

        return [
            'issued_value' => [$ofType(TransactionType::Issue), 'value'],
            'consumed_value' => [$ofType(TransactionType::Consumption), 'value'],
            'wasted_value' => [$ofType(TransactionType::Wastage), 'value'],
            'repaint_value' => [
                fn ($q) => $q->where('type', TransactionType::Issue->value)
                    ->whereIn('issue_type', [IssueType::Rework->value, IssueType::Reissue->value]),
                'value',
            ],
            'variance_value' => [
                fn ($q) => $q->where('type', TransactionType::Issue->value)
                    ->where('issue_type', IssueType::Variance->value),
                'value',
            ],
            'issued_grams' => [$ofType(TransactionType::Issue), 'qty'],
        ];
    }

    public function costingList(?string $q = null, int $limit = 100): Collection
    {
        $query = Order::query();
        foreach ($this->sums() as $alias => [$constraint, $column]) {
            $query->withSum(["transactions as {$alias}" => $constraint], $column);
        }

        return $query
            ->withSum('bomLines as bom_grams', 'allocated_qty')
            ->when($q, fn ($qq) => $qq->where('code', 'like', "%{$q}%"))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Order $o) => $this->row($o));
    }

    public function row(Order $o): array
    {
        $bomCost = $o->bom_total_cost !== null ? (float) $o->bom_total_cost : null;
        $issued = (float) ($o->issued_value ?? 0);
        $area = (float) ($o->total_area ?? 0);

        return [
            'id' => $o->id,
            'code' => $o->code,
            'finish' => $o->finish,
            'total_area' => $area ?: null,
            'bom_cost' => $bomCost,
            'bom_grams' => (float) ($o->bom_grams ?? 0),
            'issued_grams' => abs((float) ($o->issued_grams ?? 0)),
            'issued_value' => $issued,
            'consumed_value' => (float) ($o->consumed_value ?? 0),
            'wasted_value' => (float) ($o->wasted_value ?? 0),
            'repaint_value' => (float) ($o->repaint_value ?? 0),
            'variance_value' => (float) ($o->variance_value ?? 0),
            // + means spent more than the BOM priced in
            'variance_pct' => ($bomCost !== null && $bomCost > 0)
                ? round(($issued - $bomCost) / $bomCost * 100, 1)
                : null,
            'cost_per_sqft' => $area > 0 ? round($issued / $area, 2) : null,
        ];
    }

    public function orderCosting(Order $order): array
    {
        $fresh = Order::query();
        foreach ($this->sums() as $alias => [$constraint, $column]) {
            $fresh->withSum(["transactions as {$alias}" => $constraint], $column);
        }

        return $this->row(
            $fresh->withSum('bomLines as bom_grams', 'allocated_qty')->findOrFail($order->id),
        );
    }

    /** Orders whose BOM still has grams to issue (the store's queue). */
    public function pendingIssue(int $limit = 10): array
    {
        $orders = Order::query()
            ->withSum('bomLines as bom_grams', 'allocated_qty')
            ->withSum(['transactions as issued_grams' => fn ($t) => $t->where('type', TransactionType::Issue->value)], 'qty')
            ->get()
            ->filter(fn ($o) => (float) $o->bom_grams > 0)
            ->map(fn ($o) => [
                'id' => $o->id,
                'code' => $o->code,
                'remaining_grams' => (float) $o->bom_grams - abs((float) $o->issued_grams),
            ])
            ->filter(fn ($o) => $o['remaining_grams'] > 0.01)
            ->sortByDesc('remaining_grams')
            ->values();

        return ['count' => $orders->count(), 'top' => $orders->take($limit)->all()];
    }

    /** Recent over-BOM / rework issues with their reason and authoriser. */
    public function varianceExceptions(int $limit = 15): Collection
    {
        return Transaction::query()
            ->where('type', TransactionType::Issue->value)
            ->whereIn('issue_type', [IssueType::Variance->value, IssueType::Rework->value, IssueType::Reissue->value])
            ->with(['order:id,code', 'item:id,code,name'])
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'occurred_at' => $t->occurred_at->toIso8601String(),
                'order' => $t->order?->code,
                'order_id' => $t->order_id,
                'item' => $t->item?->name,
                'issue_type' => $t->issue_type?->value,
                'grams' => abs($t->qty),
                'value' => (float) $t->value,
                'remarks' => $t->remarks,
                'authorized_by' => $t->authorized_by,
            ]);
    }
}
