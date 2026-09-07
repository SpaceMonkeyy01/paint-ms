<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The single stock ledger. Stock on hand is ALWAYS derived as SUM(qty) per item —
     * never stored. qty is signed: + adds to stock, - removes.
     *   opening / receipt / adjust(+)      -> +
     *   issue / consumption / wastage / adjust(-) -> -
     * Issue = store hands material out against an order (sub-warehouse view).
     * Consumption = painter's scale reading at the station (start - end weight).
     * Wastage = gap between what should be in the container and the scale reading.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('txn_ref', 40)->unique();
            $table->timestamp('occurred_at')->index();
            $table->string('type', 20)->index();          // opening|receipt|issue|consumption|wastage|adjust
            $table->string('issue_type', 20)->nullable();  // bom|variance|rework|reissue
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->string('issue_pool')->nullable();
            $table->decimal('qty', 12, 3);                  // signed, in item uom (grams)
            $table->string('uom', 10)->default('Gram');
            $table->decimal('rate', 12, 4)->nullable();     // rate snapshot at time of txn
            $table->decimal('value', 14, 2)->nullable();    // abs(qty) * rate
            $table->foreignId('colour_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slot', 10)->nullable();         // station slot (W01/P02/A03) for consumption rows
            $table->decimal('start_wt', 10, 2)->nullable(); // scale readings for consumption rows
            $table->decimal('end_wt', 10, 2)->nullable();
            $table->foreignId('entered_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entered_by')->nullable();       // legacy email, until users are mapped
            $table->string('authorized_by')->nullable();
            $table->string('external_ref')->nullable();     // supplier invoice / GRN / free-text ref
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['item_id', 'occurred_at']);
            $table->index(['order_id', 'type']);
        });
    }

    public function down(): void { Schema::dropIfExists('transactions'); }
};
