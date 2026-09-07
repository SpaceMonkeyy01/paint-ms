<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // One row per order x BOM category, allocated grams. Structured replacement for the Odoo string.
    public function up(): void
    {
        Schema::create('bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('bom_category');
            $table->string('issue_pool');
            $table->decimal('allocated_qty', 12, 2)->default(0);
            $table->string('uom', 10)->default('Gram');
            $table->timestamps();
            $table->unique(['order_id', 'bom_category']);
        });
    }

    public function down(): void { Schema::dropIfExists('bom_lines'); }
};
