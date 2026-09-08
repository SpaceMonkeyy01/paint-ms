<?php

namespace App\Services;

use App\Enums\IssueType;
use App\Enums\TransactionType;
use App\Models\Item;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of transactions rows (CLAUDE.md conventions).
 * Enforces:
 *   rule 2 — qty is signed by TransactionType::sign(); callers pass positive grams
 *            (adjust passes signed grams, sign() = 0 means "caller-signed").
 *   rule 4 — rate is snapshotted from the item at write time, value = |qty| × rate.
 *   rule 3 — issue = store replenishes the STATION (no order); consumption =
 *            painter's entry against an order. BOM tally is consumption-side,
 *            warn-only (rule 6): nothing blocks, variances surface on the dashboard.
 */
class LedgerService
{
    /**
     * @param float $qty positive grams for all types except adjust, which is signed
     */
    public function record(
        TransactionType $type,
        Item $item,
        float $qty,
        ?Order $order = null,
        ?IssueType $issueType = null,
        ?User $enteredBy = null,
        ?string $authorizedBy = null,
        ?string $remarks = null,
        ?string $externalRef = null,
        ?float $rate = null,
        ?string $slot = null,
        ?float $startWt = null,
        ?float $endWt = null,
        ?int $colourBatchId = null,
    ): Transaction {
        if ($qty == 0.0) {
            throw ValidationException::withMessages(['qty' => 'Quantity cannot be zero.']);
        }

        $sign = $type->sign();
        if ($sign === 0) { // adjust: caller supplies the sign
            $signedQty = $qty;
        } else {
            if ($qty < 0) {
                throw ValidationException::withMessages(['qty' => 'Pass positive grams; the ledger applies the sign.']);
            }
            $signedQty = $sign * $qty;
        }

        if ($type === TransactionType::Issue && ! $item->issue_pool) {
            throw ValidationException::withMessages([
                'issue' => "Item {$item->code} has no issue pool — set it in the item master first.",
            ]);
        }

        $rate = $rate ?? (float) $item->rate_per_uom; // rule 4: snapshot, never join to items later

        return Transaction::create([
            'txn_ref' => 'W-'.Str::ulid(),
            'occurred_at' => now(),
            'type' => $type,
            'issue_type' => $issueType,
            'order_id' => $order?->id,
            'item_id' => $item->id,
            'issue_pool' => $item->issue_pool,
            'qty' => round($signedQty, 3),
            'uom' => $item->uom,
            'rate' => round($rate, 4),
            'value' => round(abs($signedQty) * $rate, 2),
            'colour_batch_id' => $colourBatchId,
            'slot' => $slot,
            'start_wt' => $startWt,
            'end_wt' => $endWt,
            'entered_by_id' => $enteredBy?->id,
            'authorized_by' => $authorizedBy,
            'external_ref' => $externalRef,
            'remarks' => $remarks,
        ]);
    }

    /**
     * Replenish the station: several lines out of the sub-warehouse, no order.
     * Order attribution happens later, on the painter's consumption entries.
     *
     * @param array<int, array{item_id: int, grams: float}> $lines
     * @return array<int, Transaction>
     */
    public function issueToStation(array $lines, ?User $enteredBy = null, ?string $remarks = null): array
    {
        $items = Item::whereIn('id', array_column($lines, 'item_id'))->get()->keyBy('id');

        return DB::transaction(function () use ($lines, $enteredBy, $remarks, $items) {
            $txns = [];
            foreach ($lines as $line) {
                $txns[] = $this->record(
                    type: TransactionType::Issue,
                    item: $items[$line['item_id']],
                    qty: (float) $line['grams'],
                    enteredBy: $enteredBy,
                    remarks: $remarks,
                );
            }

            return $txns;
        });
    }

    /**
     * Record a station session: one consumption row per slot, quantity as typed
     * by the painter — grams directly, or litres converted through the item's
     * density (rule 7: no density, no litres — never guess). Atomic across slots.
     *
     * @param array<int, array{slot: string, item_id: int, grams?: float|null,
     *                         litres?: float|null, colour_batch_id?: int|null}> $readings
     * @return array<int, Transaction>
     */
    public function consumeSlots(Order $order, array $readings, ?User $enteredBy = null): array
    {
        $items = Item::whereIn('id', array_column($readings, 'item_id'))->get()->keyBy('id');

        return DB::transaction(function () use ($order, $readings, $enteredBy, $items) {
            $txns = [];
            foreach ($readings as $r) {
                $item = $items[$r['item_id']];
                $grams = (float) ($r['grams'] ?? 0);
                $litres = (float) ($r['litres'] ?? 0);

                if ($grams > 0 && $litres > 0) {
                    throw ValidationException::withMessages([
                        'readings' => "{$r['slot']}: enter grams or litres, not both.",
                    ]);
                }

                if ($litres > 0) {
                    if (! $item->density_kg_per_l) {
                        throw ValidationException::withMessages([
                            'readings' => "{$r['slot']}: {$item->code} has no density — enter grams (rule 7).",
                        ]);
                    }
                    $grams = $litres * (float) $item->density_kg_per_l * 1000;
                }

                if ($grams <= 0) {
                    throw ValidationException::withMessages([
                        'readings' => "{$r['slot']}: nothing entered — remove the slot.",
                    ]);
                }

                $txns[] = $this->record(
                    type: TransactionType::Consumption,
                    item: $item,
                    qty: round($grams, 3),
                    order: $order,
                    enteredBy: $enteredBy,
                    slot: $r['slot'],
                    colourBatchId: $r['colour_batch_id'] ?? null,
                );
            }

            return $txns;
        });
    }
}
