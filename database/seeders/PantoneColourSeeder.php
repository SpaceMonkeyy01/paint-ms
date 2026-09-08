<?php

namespace Database\Seeders;

use App\Models\PantoneColour;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Pantone Solid Coated book from database/data/pantone_colours.csv
 * (community HEX/Lab approximations of the 2024 coated library, 3219 colours).
 * Idempotent and cheap to re-run: skips entirely once the table is populated.
 */
class PantoneColourSeeder extends Seeder
{
    public function run(): void
    {
        if (PantoneColour::count() > 0) {
            return;
        }

        $handle = fopen(database_path('data/pantone_colours.csv'), 'r');
        fgetcsv($handle); // header

        $rows = [];
        $now = now();
        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = [
                'code' => $line[0],
                'hex' => $line[1],
                'lab_l' => $line[2],
                'lab_a' => $line[3],
                'lab_b' => $line[4],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        fclose($handle);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('pantone_colours')->insert($chunk);
        }
    }
}
