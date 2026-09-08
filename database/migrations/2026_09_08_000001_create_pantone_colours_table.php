<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pantone Solid Coated reference book (community HEX/Lab approximations,
     * seeded from database/data/pantone_colours.csv). Read-only lookup for the
     * station's Create Mix screen; colour_batches still stores its own
     * colour_ref + hex snapshot so a batch survives edits to this table.
     */
    public function up(): void
    {
        Schema::create('pantone_colours', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();  // PANTONE 7463 C
            $table->string('hex', 9);
            $table->decimal('lab_l', 7, 2)->nullable();
            $table->decimal('lab_a', 7, 2)->nullable();
            $table->decimal('lab_b', 7, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pantone_colours');
    }
};
