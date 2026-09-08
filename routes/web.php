<?php

use App\Http\Controllers\Admin\CostingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Station\ConsumptionController;
use App\Http\Controllers\Station\MixController;
use App\Http\Controllers\Station\RecipeController;
use App\Http\Controllers\Station\StationSlotController;
use App\Http\Controllers\Store\IssueController;
use App\Http\Controllers\Store\ReceiptController;
use App\Http\Controllers\Store\StockController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:store'])->prefix('store')->name('store.')->group(function () {
    Route::get('/issue', [IssueController::class, 'index'])->name('issue.index');
    Route::post('/issue', [IssueController::class, 'store'])->name('issue.store');
    Route::get('/stock', [StockController::class, 'index'])->name('stock');
    Route::get('/receipts', [ReceiptController::class, 'create'])->name('receipts.create');
    Route::post('/receipts', [ReceiptController::class, 'store'])->name('receipts.store');
});

Route::middleware(['auth', 'role:painter'])->prefix('station')->name('station.')->group(function () {
    Route::get('/', [ConsumptionController::class, 'index'])->name('consume.index');
    // literal paths before the {order} wildcard
    Route::get('/mix', [MixController::class, 'create'])->name('mix.create');
    Route::post('/mix', [MixController::class, 'store'])->name('mix.store');
    Route::get('/pantones', [MixController::class, 'pantones'])->name('pantones');
    Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes');
    Route::patch('/slots/{slot}', [StationSlotController::class, 'update'])->name('slots.update');
    Route::post('/slots', [StationSlotController::class, 'store'])->name('slots.store');
    Route::get('/recipes/colour', [RecipeController::class, 'show'])->name('recipes.show'); // ?colour=PANTONE 7463 C
    Route::get('/{order}', [ConsumptionController::class, 'show'])->name('consume.show');
    Route::post('/{order}', [ConsumptionController::class, 'store'])->name('consume.store');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/costing', [CostingController::class, 'index'])->name('costing.index');
    Route::get('/costing/{order}', [CostingController::class, 'show'])->name('costing.show');
});

require __DIR__.'/auth.php';
