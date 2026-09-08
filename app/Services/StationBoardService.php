<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Station kanban. Lanes are DERIVED — from the ledger (consumption) and the
 * Airtable stage tags — never stored or dragged, the same philosophy as stock.
 *   queue      no paint recorded yet (tagged for paint, or has a BOM)
 *   repaint    tagged "Repaint needed"
 *   in_paint   some consumption, BOM not fully used
 *   done       tagged Done/Shipped, or BOM fully used
 * Priority inside a lane = due date (overdue first, undated last).
 */
class StationBoardService
{
    private const PAINT_TAGS = ['Paint needed', 'Paint Compound'];
    private const REPAINT_TAG = 'Repaint needed';
    private const CLOSED_TAGS = ['Done', 'Shipped'];

    /** @return array{queue: Collection, repaint: Collection, in_paint: Collection, done: Collection} */
    public function lanes(int $doneLimit = 15): array
    {
        $sumOf = fn (TransactionType $type) => fn ($t) => $t->where('type', $type->value);

        $orders = Order::query()
            ->withSum('bomLines as bom_grams', 'allocated_qty')
            ->withSum(['transactions as consumed_grams' => $sumOf(TransactionType::Consumption)], 'qty')
            ->withSum(['transactions as wasted_grams' => $sumOf(TransactionType::Wastage)], 'qty')
            ->where(function ($q) {
                $q->has('bomLines')->orWhereNotNull('airtable_tags');
            })
            ->orderByDesc('id')
            ->limit(400)
            ->get()
            ->map(fn (Order $o) => $this->card($o));

        $lane = fn (string $name) => $orders->where('lane', $name);

        return [
            'queue' => $lane('queue')->sortBy($this->priority())->values(),
            'repaint' => $lane('repaint')->sortBy($this->priority())->values(),
            'in_paint' => $lane('in_paint')->sortBy($this->priority())->values(),
            'done' => $lane('done')->take($doneLimit)->values(),
        ];
    }

    private function card(Order $o): array
    {
        $bom = (float) $o->bom_grams;
        $used = abs((float) $o->consumed_grams) + abs((float) $o->wasted_grams);
        $tags = collect($o->airtable_tags ?? []);

        $lane = match (true) {
            $tags->contains(self::REPAINT_TAG) => 'repaint',
            $tags->intersect(self::CLOSED_TAGS)->isNotEmpty() => 'done',
            $bom > 0 && $used >= $bom - 0.01 => 'done',
            $used > 0 => 'in_paint',
            default => 'queue',
        };

        $daysLeft = $o->due_date?->startOfDay()->diffInDays(now()->startOfDay(), false);

        return [
            'id' => $o->id,
            'code' => $o->code,
            'finish' => $o->finish,
            'colour_note' => $o->colour_note,
            'order_date' => $o->order_date?->toDateString(),
            'due_date' => $o->due_date?->toDateString(),
            // negative = due in N days, 0 = today, positive = N days overdue
            'days_overdue' => $daysLeft !== null ? (int) $daysLeft : null,
            'bom_grams' => $bom,
            'used_grams' => $used,
            'tags' => $tags->values(),
            'lane' => $lane,
        ];
    }

    /** Overdue first, then nearest due date, undated last, newest as tiebreak. */
    private function priority(): callable
    {
        return fn (array $card) => [
            $card['due_date'] === null ? 1 : 0,
            $card['due_date'] ?? '9999-12-31',
            -$card['id'],
        ];
    }
}
