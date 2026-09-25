<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AirtableOrderSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * The admin "Parse" button — pull orders + BOM from Airtable on demand
 * instead of waiting for the 15-minute schedule (docs/airtable-sync.md).
 * Read-only against Airtable; same code path as airtable:sync-orders.
 */
class AirtableSyncController extends Controller
{
    public function store(AirtableOrderSync $sync): RedirectResponse
    {
        if (! config('services.airtable.token')) {
            return back()->with('warning', 'AIRTABLE_TOKEN is not set — nothing to parse.');
        }

        // Paging the view over the network outlives the default request budget.
        set_time_limit(300);

        try {
            $stats = $sync->syncExclusive(config('services.airtable.view'));
        } catch (\Throwable $e) {
            Log::error('Airtable parse failed', ['exception' => $e]);

            return back()->with('warning', 'Airtable parse failed: '.$e->getMessage());
        }

        if ($stats === null) {
            return back()->with('warning', 'A parse is already running — give it a moment.');
        }

        $changed = $stats['created'] + $stats['updated'];
        $response = back()->with('success', $changed === 0 && $stats['bom_reparsed'] === 0
            ? 'Parsed — nothing new from Airtable.'
            : sprintf(
                'Parsed — %d new, %d updated, %d BOM%s re-parsed.',
                $stats['created'],
                $stats['updated'],
                $stats['bom_reparsed'],
                $stats['bom_reparsed'] === 1 ? '' : 's',
            ));

        if ($stats['warnings'] > 0) {
            $response->with('warning', $stats['warnings'].' BOM parse warning(s) — check the order for unknown categories.');
        }

        return $response;
    }
}
