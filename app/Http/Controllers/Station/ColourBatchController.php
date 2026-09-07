<?php

namespace App\Http\Controllers\Station;

use App\Http\Controllers\Controller;
use App\Http\Requests\Station\StoreColourBatchRequest;
use App\Models\ColourBatch;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ColourBatchController extends Controller
{
    public function store(StoreColourBatchRequest $request, Order $order): RedirectResponse
    {
        $data = $request->validated();
        $total = collect($data['components'])->sum('grams');

        DB::transaction(function () use ($order, $data, $total, $request) {
            $today = now()->format('ymd');
            $seq = 1 + (int) ColourBatch::where('ref', 'like', "CLR-{$today}-%")->pluck('ref')
                ->map(fn ($r) => (int) substr($r, strrpos($r, '-') + 1))->max();

            $batch = ColourBatch::create([
                'ref' => "CLR-{$today}-{$seq}",
                'mixed_at' => now(),
                'order_id' => $order->id,
                'colour_ref' => $data['colour_ref'],
                'batch_grams' => round($total, 2),
                'matcher' => $request->user()->email,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['components'] as $c) {
                $batch->components()->create([
                    'item_id' => $c['item_id'],
                    'grams' => round((float) $c['grams'], 2),
                    'pct' => $total > 0 ? round($c['grams'] / $total * 100, 2) : null,
                ]);
            }
        });

        return back()->with('success', "Colour batch saved ({$data['colour_ref']}, ".round($total)." g).");
    }
}
