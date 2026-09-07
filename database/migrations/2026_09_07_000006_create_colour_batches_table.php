<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colour_batches', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 30)->unique();          // CLR-260827-5
            $table->timestamp('mixed_at');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('colour_ref', 60)->nullable();  // PANTONE 7463 C
            $table->string('hex', 9)->nullable();
            $table->decimal('lab_l', 7, 2)->nullable();
            $table->decimal('lab_a', 7, 2)->nullable();
            $table->decimal('lab_b', 7, 2)->nullable();
            $table->decimal('batch_grams', 12, 2)->default(0);
            $table->string('brand_mix', 40)->nullable();  // Mixed (Nexa+Nippon)
            $table->string('matcher')->nullable();        // user email for now; FK to users later
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('colour_batch_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colour_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->decimal('grams', 12, 2);
            $table->decimal('pct', 6, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colour_batch_components');
        Schema::dropIfExists('colour_batches');
    }
};
