<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();          // BS-ET-16188, BS-US-2501 A ...
            $table->string('finish', 20)->nullable();      // Gloss | Matt | Satin
            $table->string('work_center', 40)->nullable();
            $table->decimal('total_area', 10, 2)->nullable();
            $table->decimal('surface_area', 10, 2)->nullable();
            $table->decimal('bom_total_cost', 12, 2)->nullable(); // "Total: 3,749.00" from Odoo BOM
            $table->text('bom_source')->nullable();        // raw Odoo BOM string, kept for audit
            $table->string('status', 20)->default('open'); // open | issued | painted | closed
            $table->timestamp('bom_loaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('orders'); }
};
