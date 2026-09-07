<?php

namespace Database\Seeders;

use App\Models\BomCategory;
use App\Models\BomLine;
use App\Models\ColourBatch;
use App\Models\ColourBatchComponent;
use App\Models\Item;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Loads the CSVs produced by tools/extract_seed_data.py (from the two legacy Google Sheets).
 * Idempotent: safe to re-run; matches on natural keys (code / txn_ref / ref).
 *
 *   php artisan db:seed --class=LegacyImportSeeder
 */
class LegacyImportSeeder extends Seeder
{
    private string $dir;

    public function run(): void
    {
        $this->dir = database_path('seeders/data');

        DB::transaction(function () {
            $this->categories();
            $items = $this->items();
            $orders = $this->orders();
            $this->bomLines($orders);
            $batches = $this->colourBatches($orders, $items);
            $this->transactions($orders, $items, $batches);
        });

        $this->command?->info('Legacy import complete.');
    }

    private function csv(string $file): \Generator
    {
        $h = fopen("{$this->dir}/{$file}", 'r');
        $header = fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            yield array_combine($header, $row);
        }
        fclose($h);
    }

    private function n(?string $v): ?float
    {
        return ($v === null || $v === '') ? null : (float) $v;
    }

    private function categories(): void
    {
        foreach ($this->csv('bom_categories.csv') as $r) {
            BomCategory::updateOrCreate(['name' => $r['name']], [
                'issue_pool' => $r['issue_pool'],
                'clubbed' => (bool) $r['clubbed'],
                'ignored' => $r['issue_pool'] === 'IGNORE',
                'uom' => $r['uom'] ?: 'Gram',
                'notes' => $r['notes'] ?: null,
            ]);
        }
    }

    /** @return array<string,int> code => id */
    private function items(): array
    {
        $map = [];
        foreach ($this->csv('items.csv') as $r) {
            $item = Item::updateOrCreate(['code' => $r['code']], [
                'odoo_id' => $r['odoo_id'] ?: null,
                'name' => $r['name'] ?: "Item {$r['code']}",
                'brand' => $r['brand'] ?: null,
                'manufacturer_code' => $r['manufacturer_code'] ?: null,
                'conventional_name' => $r['conventional_name'] ?: null,
                'bom_category' => $r['bom_category'] ?: null,
                'issue_pool' => $r['issue_pool'] ?: null,
                'uom' => $r['uom'] ?: 'Gram',
                'density_kg_per_l' => $this->n($r['density_kg_per_l']),
                'rate_per_uom' => $this->n($r['rate_per_uom']) ?? 0,
                'min_level' => $this->n($r['min_level']) ?? 0,
                'legacy_category' => $r['legacy_category'] ?: null,
                'pantone_ref' => $r['pantone_ref'] ?: null,
            ]);
            $map[$r['code']] = $item->id;
        }
        $this->command?->info('items: '.count($map));
        return $map;
    }

    /** @return array<string,int> code => id */
    private function orders(): array
    {
        $map = [];
        foreach ($this->csv('orders.csv') as $r) {
            $order = Order::updateOrCreate(['code' => $r['code']], [
                'total_area' => $this->n($r['total_area']),
                'surface_area' => $this->n($r['surface_area']),
                'bom_total_cost' => $this->n($r['bom_total_cost']),
                'bom_source' => $r['bom_source'] ?: null,
                'bom_loaded_at' => $r['bom_source'] ? now() : null,
            ]);
            $map[$r['code']] = $order->id;
        }
        $this->command?->info('orders: '.count($map));
        return $map;
    }

    private function bomLines(array $orders): void
    {
        $n = 0;
        foreach ($this->csv('bom_lines.csv') as $r) {
            if (! isset($orders[$r['order_code']])) continue;
            BomLine::updateOrCreate(
                ['order_id' => $orders[$r['order_code']], 'bom_category' => $r['bom_category']],
                ['issue_pool' => $r['issue_pool'], 'allocated_qty' => $this->n($r['allocated_qty']) ?? 0, 'uom' => $r['uom'] ?: 'Gram']
            );
            $n++;
        }
        $this->command?->info("bom lines: {$n}");
    }

    /** @return array<string,int> ref => id */
    private function colourBatches(array $orders, array $items): array
    {
        $map = [];
        foreach ($this->csv('colour_batches.csv') as $r) {
            $b = ColourBatch::updateOrCreate(['ref' => $r['ref']], [
                'mixed_at' => $r['mixed_at'] ?: now(),
                'order_id' => $orders[$r['order_code']] ?? null,
                'colour_ref' => trim($r['colour_ref']) ?: null,
                'hex' => $r['hex'] ?: null,
                'lab_l' => $this->n($r['lab_l']), 'lab_a' => $this->n($r['lab_a']), 'lab_b' => $this->n($r['lab_b']),
                'batch_grams' => $this->n($r['batch_grams']) ?? 0,
                'brand_mix' => $r['brand_mix'] ?: null,
                'matcher' => $r['matcher'] ?: null,
                'notes' => $r['notes'] ?: null,
            ]);
            $map[$r['ref']] = $b->id;
            $b->components()->delete();
        }
        foreach ($this->csv('colour_batch_components.csv') as $r) {
            if (! isset($map[$r['batch_ref']], $items[$r['item_code']])) continue;
            ColourBatchComponent::create([
                'colour_batch_id' => $map[$r['batch_ref']],
                'item_id' => $items[$r['item_code']],
                'grams' => $this->n($r['grams']) ?? 0,
                'pct' => $this->n($r['pct']),
            ]);
        }
        $this->command?->info('colour batches: '.count($map));
        return $map;
    }

    private function transactions(array $orders, array $items, array $batches): void
    {
        $n = 0; $skipped = [];
        foreach ($this->csv('transactions.csv') as $r) {
            if (! isset($items[$r['item_code']])) { $skipped[] = $r['item_code']; continue; }
            $qty = (float) $r['qty'];
            $rate = $this->n($r['rate']);
            Transaction::updateOrCreate(['txn_ref' => $r['txn_ref']], [
                'occurred_at' => $r['occurred_at'] ?: now(),
                'type' => $r['type'],
                'issue_type' => $r['issue_type'] ?: null,
                'order_id' => $orders[$r['order_code']] ?? null,
                'item_id' => $items[$r['item_code']],
                'issue_pool' => $r['issue_pool'] ?: null,
                'qty' => $qty,
                'uom' => $r['uom'] ?: 'Gram',
                'rate' => $rate,
                'value' => $rate !== null ? round(abs($qty) * $rate, 2) : null,
                'colour_batch_id' => $batches[$r['batch_ref']] ?? null,
                'entered_by' => $r['entered_by'] ?: null,
                'authorized_by' => $r['authorized_by'] ?: null,
                'external_ref' => $r['external_ref'] ?: null,
                'remarks' => $r['remarks'] ?: null,
            ]);
            $n++;
        }
        $this->command?->info("transactions: {$n}".($skipped ? ' (skipped unknown items: '.implode(',', array_unique($skipped)).')' : ''));
    }
}
