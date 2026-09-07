<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();          // internal / iFlow inventory code (e.g. "2214", "2245 / 2673")
            $table->unsignedBigInteger('odoo_id')->nullable()->index();
            $table->string('name');
            $table->string('brand', 60)->nullable();       // Nexa, Nippon, Local, Master...
            $table->string('manufacturer_code', 30)->nullable();
            $table->string('conventional_name', 80)->nullable(); // floor name: "Black Jet", "Mehroon"
            $table->string('bom_category')->nullable();    // FK by name -> bom_categories.name
            $table->string('issue_pool')->nullable();
            $table->string('uom', 10)->default('Gram');
            $table->decimal('density_kg_per_l', 8, 4)->nullable(); // drives g <-> L at the station
            $table->decimal('rate_per_uom', 12, 4)->default(0);    // Rs per gram
            $table->decimal('min_level', 12, 2)->default(0);
            $table->string('legacy_category')->nullable();  // Odoo category path from the Consumption Tool
            $table->string('pantone_ref', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('items'); }
};
