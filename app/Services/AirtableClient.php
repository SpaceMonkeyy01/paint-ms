<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Read-only Airtable API client. Fields come back keyed by FIELD ID
 * (docs/airtable-sync.md: ids are stable, names are not).
 */
class AirtableClient
{
    /**
     * Page through a table (optionally one view), yielding raw records.
     *
     * @return \Generator<array{id: string, createdTime: string, fields: array<string, mixed>}>
     */
    public function records(?string $view = null): \Generator
    {
        $base = config('services.airtable.base');
        $table = config('services.airtable.table');
        $offset = null;

        do {
            $query = array_filter([
                'pageSize' => 100,
                'returnFieldsByFieldId' => 'true',
                'view' => $view,
                'offset' => $offset,
            ]);

            $page = Http::withToken(config('services.airtable.token'))
                ->retry(3, 2000)
                ->timeout(30)
                ->get("https://api.airtable.com/v0/{$base}/{$table}", $query)
                ->throw()
                ->json();

            foreach ($page['records'] ?? [] as $record) {
                yield $record;
            }

            $offset = $page['offset'] ?? null;
        } while ($offset !== null);
    }
}
