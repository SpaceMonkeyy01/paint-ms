<?php

namespace App\Http\Controllers\Station;

use App\Http\Controllers\Controller;
use App\Http\Requests\Station\StoreMixRequest;
use App\Models\Item;
use App\Models\Order;
use App\Models\PantoneColour;
use App\Services\MixService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MixController extends Controller
{
    public function create(Request $request, StockService $stock): Response
    {
        $q = trim((string) $request->query('q', ''));
        $station = $stock->stationStockByItem();

        $orders = Order::query()
            ->when($q !== '', fn ($query) => $query->whereRaw('LOWER(code) LIKE ?', ['%'.mb_strtolower($q).'%']))
            ->when($q === '', fn ($query) => $query->has('bomLines'))
            ->orderByDesc('id')
            ->limit(30)
            ->get(['id', 'code', 'finish', 'colour_note']);

        $itemsByPool = Item::where('is_active', true)->whereNotNull('issue_pool')
            ->orderBy('name')->get()
            ->map(fn (Item $i) => [
                'id' => $i->id,
                'code' => $i->code,
                'name' => $i->name,
                'issue_pool' => $i->issue_pool,
                'station_on_hand' => (float) ($station[$i->id] ?? 0),
            ])
            ->groupBy('issue_pool');

        return Inertia::render('Station/Mix/Create', [
            'orders' => $orders,
            'q' => $q,
            'selectedOrderId' => (int) $request->query('order') ?: null,
            'itemsByPool' => $itemsByPool,
        ]);
    }

    /** Pantone typeahead: /station/pantones?q=7463 → [{code, hex}] */
    public function pantones(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(
            PantoneColour::query()
                ->whereRaw('LOWER(code) LIKE ?', ['%'.mb_strtolower($q).'%'])
                ->orderBy('code')
                ->limit(20)
                ->get(['code', 'hex']),
        );
    }

    public function store(StoreMixRequest $request, MixService $mixes, StockService $stock): RedirectResponse
    {
        $data = $request->validated();
        $order = Order::findOrFail($data['order_id']);

        $batch = $mixes->create(
            order: $order,
            colourRef: $data['colour_ref'],
            hex: $data['hex'] ?? null,
            components: $data['components'],
            enteredBy: $request->user(),
            notes: $data['notes'] ?? null,
        );

        // Warn-only BOM control (rule 6), same as plain consumption entry.
        $over = $stock->orderReconciliation($order)
            ->filter(fn ($p) => $p['bom_qty'] > 0 && $p['used_variance'] > 0.01)
            ->map(fn ($p) => sprintf('%s +%.0f g', $p['issue_pool'], $p['used_variance']));

        $response = redirect()
            ->route('station.consume.show', $order)
            ->with('success', "Mix {$batch->ref} saved — {$batch->colour_ref}, ".round($batch->batch_grams).' g consumed by '.$order->code.'.');

        if ($over->isNotEmpty()) {
            $response->with('warning', 'Over BOM: '.$over->implode(', ').' — flagged for admin review.');
        }

        return $response;
    }
}
