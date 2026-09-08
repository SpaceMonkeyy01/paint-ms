<?php

namespace App\Http\Controllers\Station;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\ColourBatch;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * All batches of one colour with the analysis the index can't show:
     * per-component consistency across mixes, totals, and per-batch cost
     * (summed from the linked consumption rows — snapshot values, rule 4).
     */
    public function show(Request $request): Response
    {
        $colour = trim((string) $request->query('colour', ''));
        abort_if($colour === '', 404);

        $batches = ColourBatch::query()
            ->where('colour_ref', $colour)
            ->with(['components.item:id,code,name', 'order:id,code'])
            ->orderByDesc('mixed_at')->orderByDesc('id')
            ->limit(200)
            ->get();

        abort_if($batches->isEmpty(), 404);

        $costs = Transaction::query()
            ->whereIn('colour_batch_id', $batches->pluck('id'))
            ->where('type', TransactionType::Consumption->value)
            ->select('colour_batch_id', DB::raw('SUM(value) as cost'))
            ->groupBy('colour_batch_id')
            ->pluck('cost', 'colour_batch_id');

        $n = $batches->count();
        $components = $batches->flatMap(fn ($b) => $b->components)
            ->groupBy('item_id')
            ->map(function ($group) use ($n) {
                $pcts = $group->pluck('pct')->reject(fn ($p) => $p === null)->map(fn ($p) => (float) $p);
                return [
                    'item' => $group->first()->item?->name,
                    'code' => $group->first()->item?->code,
                    'used_in' => $group->count(),
                    'of' => $n,
                    'avg_pct' => $pcts->isNotEmpty() ? round($pcts->avg(), 1) : null,
                    'min_pct' => $pcts->isNotEmpty() ? round($pcts->min(), 1) : null,
                    'max_pct' => $pcts->isNotEmpty() ? round($pcts->max(), 1) : null,
                    'avg_grams' => round((float) $group->avg('grams'), 1),
                ];
            })
            ->sortByDesc('avg_pct')
            ->values();

        $totalCost = round((float) $costs->sum(), 2);
        $totalGrams = (float) $batches->sum('batch_grams');

        return Inertia::render('Station/Recipes/Show', [
            'colour' => [
                'colour_ref' => $colour,
                'hex' => $batches->firstWhere('hex', '!=', null)?->hex,
            ],
            'summary' => [
                'mix_count' => $n,
                'total_grams' => $totalGrams,
                'total_cost' => $totalCost,
                'avg_batch_grams' => $n > 0 ? round($totalGrams / $n, 1) : 0,
                'cost_per_kg' => $totalGrams > 0 ? round($totalCost / $totalGrams * 1000, 2) : null,
                'first_mixed_at' => $batches->last()->mixed_at->toIso8601String(),
                'last_mixed_at' => $batches->first()->mixed_at->toIso8601String(),
            ],
            'components' => $components,
            'batches' => $batches->map(fn ($b) => $this->batchRow($b) + [
                'cost' => round((float) ($costs[$b->id] ?? 0), 2),
            ])->values(),
        ]);
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
