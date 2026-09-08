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
            'consumed_grams' => [$ofType(TransactionType::Consumption), 'qty'],
            'wasted_grams' => [$ofType(TransactionType::Wastage), 'qty'],
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
            ->when($q, fn ($qq) => $qq->whereRaw('LOWER(code) LIKE ?', ['%'.mb_strtolower($q).'%']))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Order $o) => $this->row($o));
    }

    public function row(Order $o): array
    {
        $bomCost = $o->bom_total_cost !== null ? (float) $o->bom_total_cost : null;
        $issued = (float) ($o->issued_value ?? 0);
        $consumed = (float) ($o->consumed_value ?? 0);
        $wasted = (float) ($o->wasted_value ?? 0);
        $area = (float) ($o->total_area ?? 0);

        // Station model: actual cost = what painters consumed. Legacy orders
        // (issued per order, before the station rework) fall back to issued value.
        $actual = ($consumed + $wasted) > 0 ? $consumed + $wasted : $issued;

        return [
            'id' => $o->id,
            'code' => $o->code,
            'finish' => $o->finish,
            'total_area' => $area ?: null,
            'bom_cost' => $bomCost,
            'bom_grams' => (float) ($o->bom_grams ?? 0),
            'issued_grams' => abs((float) ($o->issued_grams ?? 0)),
            'used_grams' => abs((float) ($o->consumed_grams ?? 0)) + abs((float) ($o->wasted_grams ?? 0)),
            'issued_value' => $issued,
            'consumed_value' => $consumed,
            'wasted_value' => $wasted,
            'repaint_value' => (float) ($o->repaint_value ?? 0),
            'variance_value' => (float) ($o->variance_value ?? 0),
            'actual_value' => $actual,
            // + means spent more than the BOM priced in
            'variance_pct' => ($bomCost !== null && $bomCost > 0)
                ? round(($actual - $bomCost) / $bomCost * 100, 1)
                : null,
            'cost_per_sqft' => $area > 0 ? round($actual / $area, 2) : null,
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

    /** Orders whose BOM still has grams the station hasn't consumed (the painters' queue). */
    public function pendingConsumption(int $limit = 10): array
    {
        $orders = $this->ordersWithUsage()
            ->filter(fn ($o) => (float) $o->bom_grams > 0)
            ->map(fn ($o) => [
                'id' => $o->id,
                'code' => $o->code,
                'remaining_grams' => (float) $o->bom_grams - $this->usedGrams($o),
            ])
            ->filter(fn ($o) => $o['remaining_grams'] > 0.01)
            ->sortByDesc('remaining_grams')
            ->values();

        return ['count' => $orders->count(), 'top' => $orders->take($limit)->all()];
    }

    /**
     * Orders whose station usage exceeds the BOM — the warn-only variance list
     * that replaced issue-time gating (rule 6).
     */
    public function overBomOrders(int $limit = 15): Collection
    {
        return $this->ordersWithUsage()
            ->filter(fn ($o) => (float) $o->bom_grams > 0)
            ->map(function ($o) {
                $bom = (float) $o->bom_grams;
                $used = $this->usedGrams($o);
                return [
                    'id' => $o->id,
                    'code' => $o->code,
                    'bom_grams' => $bom,
                    'used_grams' => $used,
                    'variance_grams' => $used - $bom,
                    'variance_pct' => round(($used - $bom) / $bom * 100, 1),
                ];
            })
            ->filter(fn ($o) => $o['variance_grams'] > 0.01)
            ->sortByDesc('variance_grams')
            ->take($limit)
            ->values();
    }

    private function ordersWithUsage(): Collection
    {
        return Order::query()
            ->withSum('bomLines as bom_grams', 'allocated_qty')
            ->withSum(['transactions as consumed_grams' => fn ($t) => $t->where('type', TransactionType::Consumption->value)], 'qty')
            ->withSum(['transactions as wasted_grams' => fn ($t) => $t->where('type', TransactionType::Wastage->value)], 'qty')
            ->get();
    }

    private function usedGrams(Order $o): float
    {
        return abs((float) $o->consumed_grams) + abs((float) $o->wasted_grams);
    }
}
