<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Station\ColourBatchController;
use App\Http\Controllers\Station\ConsumptionController;
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
    Route::get('/issue/{order}', [IssueController::class, 'show'])->name('issue.show');
    Route::post('/issue/{order}', [IssueController::class, 'store'])->name('issue.store');
    Route::get('/stock', [StockController::class, 'index'])->name('stock');
    Route::get('/receipts', [ReceiptController::class, 'create'])->name('receipts.create');
    Route::post('/receipts', [ReceiptController::class, 'store'])->name('receipts.store');
});

Route::middleware(['auth', 'role:painter'])->prefix('station')->name('station.')->group(function () {
    Route::get('/', [ConsumptionController::class, 'index'])->name('consume.index');
    Route::get('/{order}', [ConsumptionController::class, 'show'])->name('consume.show');
    Route::post('/{order}', [ConsumptionController::class, 'store'])->name('consume.store');
    Route::post('/{order}/colour-batch', [ColourBatchController::class, 'store'])->name('colour-batch.store');
});

require __DIR__.'/auth.php';
