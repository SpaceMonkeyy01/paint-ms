<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\BomCategory;
use App\Models\BomLine;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Orders + BOM in from the production Airtable base (docs/airtable-sync.md).
 * Upserts on orders.code; never deletes an order that left the view.
 * The raw payload of every created/updated record lands in airtable_sync_log.
 */
class AirtableOrderSync
{
    // Field IDs from docs/airtable-sync.md — read by id, never by name.
    private const F_CODE = 'fldxO7kzDnRPbOJ5z';   // trimmed order code (formula)
    private const F_PAINT = 'fld71BWWdjayToa51';  // BOM string
    private const F_AREA = 'fldPk7fraqtNIrg89';   // w*h/144 (sqft)
    private const F_FINISH = 'fldmbyQLZDJH78QjD';
    private const F_COLOUR = 'flddTw8GtJCCiIvbU';
    private const F_TAGS = 'fld9jhixbr09YeZuN';   // production stage multiSelect
    private const F_ORDER_DATE = 'fldbwll1a6M4e9xfb';
    private const F_DUE_DATE = 'flddMyUCQQNYLbMRx';

    private const CLOSING_TAGS = ['Done', 'Shipped'];

    public function __construct(
        private AirtableClient $client,
        private BomStringParser $parser,
    ) {}

    /** @return array{created: int, updated: int, bom_reparsed: int, unchanged: int, skipped: int, warnings: int} */
    public function sync(?string $view = null): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'bom_reparsed' => 0, 'unchanged' => 0, 'skipped' => 0, 'warnings' => 0];
        $pools = BomCategory::pluck('issue_pool', 'name');

        foreach ($this->client->records($view) as $record) {
            $fields = $record['fields'] ?? [];
            $code = trim((string) ($fields[self::F_CODE] ?? ''));

            if ($code === '') {
                $stats['skipped']++;
                continue;
            }

            DB::transaction(function () use ($record, $fields, $code, $pools, &$stats) {
                $order = Order::firstOrNew(['code' => $code]);
                $isNew = ! $order->exists;

                $order->fill([
                    'airtable_record_id' => $record['id'],
                    'total_area' => is_numeric($fields[self::F_AREA] ?? null) ? round((float) $fields[self::F_AREA], 2) : $order->total_area,
                    'finish' => $this->normaliseFinish($fields[self::F_FINISH] ?? null) ?? $order->finish,
                    'colour_note' => isset($fields[self::F_COLOUR]) ? trim((string) $fields[self::F_COLOUR]) : $order->colour_note,
                    'order_date' => $fields[self::F_ORDER_DATE] ?? $order->order_date,
                    'due_date' => $fields[self::F_DUE_DATE] ?? $order->due_date,
                    'airtable_tags' => $fields[self::F_TAGS] ?? $order->airtable_tags,
                ]);

                // Done/Shipped close the order; other tag states leave status alone.
                if (array_intersect(self::CLOSING_TAGS, (array) ($fields[self::F_TAGS] ?? []))) {
                    $order->status = 'closed';
                }

                $newBom = trim((string) ($fields[self::F_PAINT] ?? ''));
                $bomChanged = $newBom !== '' && $newBom !== trim((string) $order->bom_source);

                if ($bomChanged && ! $isNew
                    && $order->transactions()->where('type', TransactionType::Issue->value)->exists()) {
                    // BOM edited after issuing started — allowed, but logged and surfaced (rule 10)
                    $order->bom_changed_after_issue_at = now();
                }

                $dirty = $order->isDirty() || $bomChanged;
                $order->save();

                if ($bomChanged) {
                    $stats['warnings'] += $this->reparseBom($order, $newBom, $pools);
                    $stats['bom_reparsed']++;
                }

                if ($isNew || $dirty) {
                    $order->syncLogs()->create([
                        'airtable_record_id' => $record['id'],
                        'action' => $isNew ? 'created' : ($bomChanged ? 'bom_reparsed' : 'updated'),
                        'payload' => $fields,
                        'synced_at' => now(),
                    ]);
                }

                $stats[$isNew ? 'created' : ($dirty ? 'updated' : 'unchanged')]++;
            });
        }

        return $stats;
    }

    /** Rebuild bom_lines from the string. Returns the number of parse warnings. */
    private function reparseBom(Order $order, string $raw, $pools): int
    {
        $parsed = $this->parser->parse($raw);
        $warnings = [];

        $order->bomLines()->delete();

        if (! $this->parser->isEmptyBom($parsed)) {
            foreach ($parsed['lines'] as $category => $line) {
                $pool = $pools[$category] ?? null;
                if ($pool === null) {
                    // rule 5: unknown category → keep the line, null pool, flag for admin
                    $warnings[] = "unknown category: {$category}";
                }
                BomLine::create([
                    'order_id' => $order->id,
                    'bom_category' => $category,
                    'issue_pool' => $pool, // 'IGNORE' lines kept for audit; reconciliation filters them
                    'allocated_qty' => round($line['qty'], 2),
                    'uom' => $line['uom'],
                ]);
            }
        }

        foreach ($parsed['unparsed'] as $part) {
            $warnings[] = "unparsed: {$part}";
        }

        $order->forceFill([
            'bom_source' => $raw,
            'bom_total_cost' => $parsed['total_cost'],
            'bom_loaded_at' => now(),
            'bom_parse_warnings' => $warnings ? implode("\n", $warnings) : null,
        ])->save();

        return count($warnings);
    }

    private function normaliseFinish(?string $finish): ?string
    {
        $f = strtolower(trim((string) $finish));

        return match (true) {
            $f === '' => null,
            in_array($f, ['matt', 'matte']) => 'Matt',
            in_array($f, ['gloss', 'glossy']) => 'Gloss',
            str_starts_with($f, 'satin') => 'Satin',
            default => null,
        };
    }
}
