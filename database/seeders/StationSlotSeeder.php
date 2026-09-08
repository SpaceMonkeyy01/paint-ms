<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\StationSlot;
use Illuminate\Database\Seeder;

/**
 * The real booth rack, mirrored from the legacy Paint Consumption Tool's
 * Config sheet (Paint_Consumption_Tool_v8, Sep 2026): W01–W25 colour slots,
 * P01–P05 primer slots, A01–A11 additive slots, with the items loaded at
 * migration time keyed by iFlow code (items.code, rule 8).
 *
 * Idempotent and safe to re-run on every deploy: missing slots are created,
 * and a Config item is loaded ONLY into a slot that is currently empty —
 * an operator's later assignment is never overwritten. Config densities
 * backfill items whose density is null (rule 7: real data only, no guesses).
 */
class StationSlotSeeder extends Seeder
{
    /** slot => [iFlow code|null, density kg/L|null] from the Config sheet */
    private const RACK = [
        'W01' => ['2214', 1.005], 'W02' => ['2239', 1.0], 'W03' => ['2241', 1.076],
        'W04' => ['2242', 1.049], 'W05' => ['2243', 0.932], 'W06' => ['2244', 0.939],
        'W07' => ['2245', 1.0157], 'W08' => ['2253', 1.003], 'W09' => ['2271', 0.802],
        'W10' => ['2272', 0.989], 'W11' => ['2274', 0.971], 'W12' => ['2273', 0.959],
        'W13' => ['2246', 0.982], // not in the item master yet — seeds empty
        'W14' => ['2351', 0.957], 'W15' => ['2353', 0.938], 'W16' => ['2438', 0.957],
        'W17' => ['2525', 1.178],
        'W18' => [null, null], 'W19' => [null, null], 'W20' => [null, null],
        'W21' => [null, null], 'W22' => [null, null], 'W23' => [null, null],
        'W24' => [null, null], 'W25' => [null, null],
        'P01' => [null, null], 'P02' => [null, null], 'P03' => [null, null],
        'P04' => [null, null], 'P05' => [null, null],
        'A01' => ['1036', 0.967], 'A02' => ['1788', 0.816], 'A03' => ['2247', 0.9476],
        'A04' => ['2254', 0.92], 'A05' => ['2267', 0.993], // 2267 not in the item master yet
        'A06' => ['2268', 0.716], 'A07' => ['2354', 0.973], 'A08' => ['2352', 1.0],
        'A09' => ['2430', 0.975],
        'A10' => [null, null], 'A11' => [null, null],
    ];

    public function run(): void
    {
        $position = 0;
        foreach (self::RACK as $code => [$iflow, $density]) {
            $slot = StationSlot::firstOrCreate(
                ['slot' => $code],
                ['class' => $code[0], 'position' => $position],
            );
            $position++;

            if (! $iflow) {
                continue;
            }

            $item = $this->findByIflow($iflow);
            if (! $item) {
                $this->command?->warn("Config slot {$code}: iFlow {$iflow} not in the item master — left empty.");
                continue;
            }

            if ($slot->item_id === null) {
                $slot->update(['item_id' => $item->id]);
            }

            if ($item->density_kg_per_l === null && $density !== null) {
                $item->update(['density_kg_per_l' => $density]);
            }
        }
    }

    /** items.code may be compound ("2245 / 2673") — match the iFlow part exactly. */
    private function findByIflow(string $iflow): ?Item
    {
        return Item::where('code', $iflow)
            ->orWhere('code', 'like', "{$iflow} /%")
            ->orWhere('code', 'like', "%/ {$iflow}")
            ->first();
    }
}
