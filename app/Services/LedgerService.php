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
 *   rule 6 — issue_type=variance requires remarks + authorized_by;
 *            issue_type=bom cannot exceed the pool's remaining BOM allowance.
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

        if ($type === TransactionType::Issue) {
            $this->validateIssue($item, $order, $issueType, abs($signedQty), $remarks, $authorizedBy);
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
     * Issue several lines to one order atomically (the Issue screen submit).
     *
     * @param array<int, array{item_id: int, grams: float}> $lines
     * @return array<int, Transaction>
     */
    public function issueLines(
        Order $order,
        array $lines,
        IssueType $issueType,
        ?User $enteredBy = null,
        ?string $authorizedBy = null,
        ?string $remarks = null,
    ): array {
        $items = Item::whereIn('id', array_column($lines, 'item_id'))->get()->keyBy('id');

        return DB::transaction(function () use ($order, $lines, $issueType, $enteredBy, $authorizedBy, $remarks, $items) {
            $txns = [];
            foreach ($lines as $line) {
                $txns[] = $this->record(
                    type: TransactionType::Issue,
                    item: $items[$line['item_id']],
                    qty: (float) $line['grams'],
                    order: $order,
                    issueType: $issueType,
                    enteredBy: $enteredBy,
                    authorizedBy: $authorizedBy,
                    remarks: $remarks,
                );
            }

            return $txns;
        });
    }

    /**
     * Record a station session: one consumption row per slot reading
     * (qty = start_wt − end_wt) plus a wastage row where a container gap
     * was entered. Atomic across all slots.
     *
     * @param array<int, array{slot: string, item_id: int, start_wt: float, end_wt: float,
     *                         wastage?: float|null, colour_batch_id?: int|null}> $readings
     * @return array<int, Transaction>
     */
    public function consumeSlots(Order $order, array $readings, ?User $enteredBy = null): array
    {
        $items = Item::whereIn('id', array_column($readings, 'item_id'))->get()->keyBy('id');

        return DB::transaction(function () use ($order, $readings, $enteredBy, $items) {
            $txns = [];
            foreach ($readings as $r) {
                $item = $items[$r['item_id']];
                $consumed = round((float) $r['start_wt'] - (float) $r['end_wt'], 3);
                $wastage = round((float) ($r['wastage'] ?? 0), 3);

                if ($consumed < 0) {
                    throw ValidationException::withMessages([
                        'readings' => "{$r['slot']}: end weight is above start weight.",
                    ]);
                }
                if ($consumed == 0.0 && $wastage == 0.0) {
                    throw ValidationException::withMessages([
                        'readings' => "{$r['slot']}: nothing consumed and no wastage — remove the slot.",
                    ]);
                }

                if ($consumed > 0) {
                    $txns[] = $this->record(
                        type: TransactionType::Consumption,
                        item: $item,
                        qty: $consumed,
                        order: $order,
                        enteredBy: $enteredBy,
                        slot: $r['slot'],
                        startWt: (float) $r['start_wt'],
                        endWt: (float) $r['end_wt'],
                        colourBatchId: $r['colour_batch_id'] ?? null,
                    );
                }
                if ($wastage > 0) {
                    $txns[] = $this->record(
                        type: TransactionType::Wastage,
                        item: $item,
                        qty: $wastage,
                        order: $order,
                        enteredBy: $enteredBy,
                        slot: $r['slot'],
                        colourBatchId: $r['colour_batch_id'] ?? null,
                        remarks: 'container gap',
                    );
                }
            }

            return $txns;
        });
    }

    /** Remaining BOM allowance for a pool on an order: allocated − already issued (grams). */
    public function remainingAllowance(Order $order, string $pool): float
    {
        $allocated = (float) $order->bomLines()->where('issue_pool', $pool)->sum('allocated_qty');
        $issued = abs((float) $order->transactions()
            ->where('type', TransactionType::Issue->value)
            ->where('issue_pool', $pool)
            ->sum('qty'));

        return $allocated - $issued;
    }

    private function validateIssue(
        Item $item,
        ?Order $order,
        ?IssueType $issueType,
        float $grams,
        ?string $remarks,
        ?string $authorizedBy,
    ): void {
        if (! $order || ! $issueType) {
            throw ValidationException::withMessages(['issue' => 'Issues need an order and an issue type.']);
        }

        if (! $item->issue_pool) {
            throw ValidationException::withMessages(['issue' => "Item {$item->code} has no issue pool — set it in the item master first."]);
        }

        if ($issueType === IssueType::Variance && (blank($remarks) || blank($authorizedBy))) {
            // rule 6: over-BOM issue needs a reason and an authoriser
            throw ValidationException::withMessages(['issue' => 'Over-BOM issue needs a reason and an authoriser.']);
        }

        if ($issueType === IssueType::Bom) {
            $remaining = $this->remainingAllowance($order, $item->issue_pool);
            if ($grams > $remaining + 0.001) {
                throw ValidationException::withMessages([
                    'issue' => sprintf(
                        '%s: %.2f g exceeds the remaining %s allowance of %.2f g — issue the excess as a variance with a reason and authoriser.',
                        $item->code, $grams, $item->issue_pool, max($remaining, 0),
                    ),
                ]);
            }
        }
    }
}
