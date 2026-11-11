<?php

use App\Http\Controllers\Api\StockTransferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Manager / Gudang DC: Kirim Barang
Route::prefix('stock-transfers')->group(function () {
    Route::get('/options', [StockTransferController::class, 'options']);
    Route::get('/', [StockTransferController::class, 'index']);
    Route::post('/', [StockTransferController::class, 'store']);
    Route::get('/{id}', [StockTransferController::class, 'show'])->whereNumber('id');
    Route::post('/{id}/dispatch', [StockTransferController::class, 'dispatch'])->whereNumber('id');
    Route::post('/{id}/cancel', [StockTransferController::class, 'cancel'])->whereNumber('id');
});

// Kasir / Toko: Barang Masuk (penerimaan transfer dari DC)
Route::prefix('goods-receipts')->group(function () {
    Route::get('/', fn (Request $r) => app(StockTransferController::class)->index($r->merge(['incoming' => 1])));
    Route::post('/{id}/verify', [StockTransferController::class, 'receive'])->whereNumber('id');
});
