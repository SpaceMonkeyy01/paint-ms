<?php

namespace Database\Seeders;

use App\Models\StationSlot;
use Illuminate\Database\Seeder;

/**
 * Default slot rack following the W/P/A template (CLAUDE.md slice 3):
 * 2 primer slots, 2 colour slots, 4 additive slots. The layout is flexible —
 * slots can be added at the station; this only seeds an empty rack once.
 */
class StationSlotSeeder extends Seeder
{
    public function run(): void
    {
        if (StationSlot::count() > 0) {
            return;
        }

        $slots = ['P01', 'P02', 'W01', 'W02', 'A01', 'A02', 'A03', 'A04'];

        foreach ($slots as $i => $code) {
            StationSlot::create([
                'slot' => $code,
                'class' => $code[0],
                'position' => $i,
            ]);
        }
    }
}
