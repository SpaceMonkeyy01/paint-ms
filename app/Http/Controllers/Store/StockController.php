<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Request $request, StockService $stock): Response
    {
        $rows = $stock->stockList()->map(fn ($i) => [
            'id' => $i->id,
            'code' => $i->code,
            'name' => $i->name,
            'brand' => $i->brand,
            'issue_pool' => $i->issue_pool,
            'stock_on_hand' => $i->stock_on_hand,
            'stock_value' => $i->stock_value,
            'min_level' => (float) $i->min_level,
            'status' => $i->status,
            // rule 7: litres are display-only; null density → show grams and flag
            'litres' => $i->gramsToLitres($i->stock_on_hand),
        ])->values();

        return Inertia::render('Store/Stock', ['items' => $rows]);
    }
}
