<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Slice 5 (docs/airtable-sync.md): orders gain their Airtable identity + fields
// mapped from the production board; bom_lines.issue_pool becomes nullable so an
// unknown BOM category can be stored (flagged) instead of silently dropped.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('airtable_record_id', 20)->nullable()->unique()->after('code');
            $table->string('colour_note')->nullable()->after('finish');
            $table->date('order_date')->nullable()->after('status');
            $table->date('due_date')->nullable()->after('order_date');
            $table->json('airtable_tags')->nullable()->after('due_date');
            $table->timestamp('bom_changed_after_issue_at')->nullable()->after('bom_loaded_at');
            $table->text('bom_parse_warnings')->nullable()->after('bom_changed_after_issue_at');
        });

        Schema::table('bom_lines', function (Blueprint $table) {
            $table->string('issue_pool')->nullable()->change();
        });

        Schema::create('airtable_sync_log', function (Blueprint $table) {
            $table->id();
            $table->string('airtable_record_id', 20)->index();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);              // created | updated | bom_reparsed
            $table->json('payload');                   // raw Airtable fields, by field id (rule 10)
            $table->timestamp('synced_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airtable_sync_log');
        Schema::table('bom_lines', fn (Blueprint $t) => $t->string('issue_pool')->nullable(false)->change());
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn([
            'airtable_record_id', 'colour_note', 'order_date', 'due_date',
            'airtable_tags', 'bom_changed_after_issue_at', 'bom_parse_warnings',
        ]));
    }
};
