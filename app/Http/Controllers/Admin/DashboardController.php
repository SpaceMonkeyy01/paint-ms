<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AirtableOrderSync;
use App\Services\CostingService;
use App\Services\StockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(StockService $stock, CostingService $costing): Response
    {
        $list = $stock->stockList();
        $lastRun = Cache::get(AirtableOrderSync::LAST_RUN);
        $finishedAt = isset($lastRun['finished_at']) ? Carbon::parse($lastRun['finished_at']) : null;

        return Inertia::render('Admin/Dashboard', [
            'stock' => [
                'out' => $list->where('status', 'OUT')->count(),
                'low' => $list->where('status', 'LOW')->count(),
                'ok' => $list->where('status', 'OK')->count(),
                'total_value' => round($list->sum('stock_value'), 2),
                'worst' => $list->where('status', '!=', 'OK')
                    ->sortBy(fn ($i) => [$i->status === 'OUT' ? 0 : 1, $i->stock_on_hand])
                    ->take(8)
                    ->map(fn ($i) => [
                        'id' => $i->id, 'code' => $i->code, 'name' => $i->name,
                        'stock_on_hand' => $i->stock_on_hand, 'min_level' => (float) $i->min_level,
                        'status' => $i->status,
                    ])->values(),
            ],
            'pendingConsumption' => $costing->pendingConsumption(),
            'overBom' => $costing->overBomOrders(),
            'sync' => [
                'configured' => (bool) config('services.airtable.token'),
                'ago' => $finishedAt?->diffForHumans(),
                'at' => $finishedAt?->timezone('Asia/Karachi')->format('d M Y, H:i'),
                'stats' => $lastRun['stats'] ?? null,
            ],
        ]);
    }
}
