<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The physical slot rack at the paint station. Each slot is LOADED with an
     * item until someone swaps the container — painters consume from loaded
     * slots without re-picking items (the legacy Paint Consumption Tool model).
     * Both store (on refill) and painters (at the station) may change item_id.
     */
    public function up(): void
    {
        Schema::create('station_slots', function (Blueprint $table) {
            $table->id();
            $table->string('slot', 10)->unique();       // W01 / P02 / A03
            $table->string('class', 1);                 // W | P | A
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_slots');
    }
};
