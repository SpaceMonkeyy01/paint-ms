<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Odoo BOM category -> issue pool mapping (was Category_Map sheet).
    public function up(): void
    {
        Schema::create('bom_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // e.g. "Paint Epoxy Primer"
            $table->string('issue_pool');              // e.g. "Epoxy Set" — several categories can share a pool
            $table->boolean('clubbed')->default(false); // issued as one set (epoxy primer+thinner+hardner)
            $table->boolean('ignored')->default(false); // cost-only flag in BOM, never issued
            $table->string('uom', 10)->default('Gram');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('bom_categories'); }
};
