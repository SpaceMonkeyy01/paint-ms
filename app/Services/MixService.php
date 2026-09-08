<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\ColourBatch;
use App\Models\Item;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The station's primary output: a mixed colour targeting a Pantone reference.
 * One save records BOTH the recipe (colour batch + components) and the order's
 * consumption of those grams — entered once, never twice (rule 3).
 */
class MixService
{
    public function __construct(private LedgerService $ledger)
    {
    }

    /**
     * @param array<int, array{item_id: int, grams: float}> $components
     */
    public function create(
        Order $order,
        string $colourRef,
        ?string $hex,
        array $components,
        ?User $enteredBy = null,
        ?string $notes = null,
    ): ColourBatch {
        $total = collect($components)->sum('grams');
        $items = Item::whereIn('id', array_column($components, 'item_id'))->get()->keyBy('id');

        return DB::transaction(function () use ($order, $colourRef, $hex, $components, $enteredBy, $notes, $total, $items) {
            $batch = ColourBatch::create([
                'ref' => $this->nextRef(),
                'mixed_at' => now(),
                'order_id' => $order->id,
                'colour_ref' => $colourRef,
                'hex' => $hex,
                'batch_grams' => round($total, 2),
                'matcher' => $enteredBy?->email,
                'notes' => $notes,
            ]);

            foreach ($components as $c) {
                $grams = (float) $c['grams'];

                $batch->components()->create([
                    'item_id' => $c['item_id'],
                    'grams' => round($grams, 2),
                    'pct' => $total > 0 ? round($grams / $total * 100, 2) : null,
                ]);

                $this->ledger->record(
                    type: TransactionType::Consumption,
                    item: $items[$c['item_id']],
                    qty: $grams,
                    order: $order,
                    enteredBy: $enteredBy,
                    colourBatchId: $batch->id,
                    remarks: "mix {$batch->ref}",
                );
            }

            return $batch;
        });
    }

    private function nextRef(): string
    {
        $today = now()->format('ymd');
        $seq = 1 + (int) ColourBatch::where('ref', 'like', "CLR-{$today}-%")->pluck('ref')
            ->map(fn ($r) => (int) substr($r, strrpos($r, '-') + 1))->max();

        return "CLR-{$today}-{$seq}";
    }
}
