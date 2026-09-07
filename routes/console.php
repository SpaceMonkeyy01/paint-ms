<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Orders + BOM feed from Airtable (docs/airtable-sync.md). Read-only; skips cleanly when no token.
Schedule::command('airtable:sync-orders')->everyFifteenMinutes()->withoutOverlapping();
