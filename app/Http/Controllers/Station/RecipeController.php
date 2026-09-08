<?php

namespace App\Http\Controllers\Station;

use App\Http\Controllers\Controller;
use App\Models\ColourBatch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Formulation library: every mix ever saved, grouped by target colour.
 * Read-only — recipes are written by the Create Mix flow (MixService).
 */
class RecipeController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $batches = ColourBatch::query()
            ->whereNotNull('colour_ref')
            ->when($q !== '', fn ($query) => $query->whereRaw('LOWER(colour_ref) LIKE ?', ['%'.mb_strtolower($q).'%']))
            ->with(['components.item:id,code,name', 'order:id,code'])
            ->orderByDesc('mixed_at')->orderByDesc('id')
            ->limit(400) // cap the working set; grouped below
            ->get();

        $colours = $batches
            ->groupBy('colour_ref')
            ->map(function ($group, $ref) {
                $latest = $group->first(); // already sorted newest-first
                return [
                    'colour_ref' => $ref,
                    'hex' => $group->firstWhere('hex', '!=', null)?->hex,
                    'mix_count' => $group->count(),
                    'last_mixed_at' => $latest->mixed_at->toIso8601String(),
                    'latest' => $this->batchRow($latest),
                    'history' => $group->skip(1)->take(10)->map(fn ($b) => $this->batchRow($b))->values(),
                ];
            })
            ->sortByDesc('last_mixed_at')
            ->take(60)
            ->values();

        return Inertia::render('Station/Recipes/Index', ['colours' => $colours, 'q' => $q]);
    }

    private function batchRow(ColourBatch $b): array
    {
        return [
            'id' => $b->id,
            'ref' => $b->ref,
            'mixed_at' => $b->mixed_at->toIso8601String(),
            'order_id' => $b->order_id,
            'order_code' => $b->order?->code,
            'batch_grams' => (float) $b->batch_grams,
            'matcher' => $b->matcher,
            'notes' => $b->notes,
            'components' => $b->components->map(fn ($c) => [
                'item' => $c->item?->name,
                'code' => $c->item?->code,
                'grams' => (float) $c->grams,
                'pct' => $c->pct !== null ? (float) $c->pct : null,
            ]),
        ];
    }
}
