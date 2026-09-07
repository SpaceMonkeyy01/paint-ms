<?php

namespace App\Console\Commands;

use App\Services\AirtableOrderSync;
use Illuminate\Console\Command;

class SyncAirtableOrders extends Command
{
    protected $signature = 'airtable:sync-orders
        {--view= : Airtable view id (defaults to the "This Month" view)}
        {--all : Walk the whole table instead of the view (backfill)}';

    protected $description = 'Pull orders + paint BOM from the production Airtable base (read-only)';

    public function handle(AirtableOrderSync $sync): int
    {
        if (! config('services.airtable.token')) {
            $this->error('AIRTABLE_TOKEN is not set.');

            return self::FAILURE;
        }

        $view = $this->option('all') ? null : ($this->option('view') ?: config('services.airtable.view'));

        $stats = $sync->sync($view);

        foreach ($stats as $k => $v) {
            $this->line(sprintf('%-13s %d', $k, $v));
        }

        if ($stats['warnings'] > 0) {
            $this->warn('Some BOM strings had parse warnings — check orders.bom_parse_warnings.');
        }

        return self::SUCCESS;
    }
}
