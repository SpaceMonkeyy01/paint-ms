<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CostingService;
use App\Services\StockService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(StockService $stock, CostingService $costing): Response
    {
        $list = $stock->stockList();

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
            'pendingIssue' => $costing->pendingIssue(),
            'exceptions' => $costing->varianceExceptions(),
        ]);
    }
}
