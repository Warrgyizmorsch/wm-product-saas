<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Inventory\Controllers\Api\ProductApiController;
use App\Domains\Inventory\Controllers\Api\WarehouseApiController;
use App\Domains\Inventory\Controllers\Api\StockTransferApiController;
use App\Domains\Inventory\Controllers\Api\StockAdjustmentApiController;
use App\Domains\Inventory\Controllers\Api\BatchApiController;
use App\Domains\Inventory\Controllers\Api\SerialNumberApiController;
use App\Domains\Inventory\Controllers\Api\StockLedgerApiController;
use App\Domains\Inventory\Controllers\Api\InventoryReportApiController;
use App\Domains\Inventory\Controllers\Api\StockReservationApiController;
use App\Domains\Inventory\Controllers\Api\BarcodeApiController;
use App\Domains\Inventory\Controllers\Api\UomApiController;
use App\Domains\Inventory\Controllers\Api\SupplyChainDashboardApiController;
use App\Domains\Inventory\Controllers\Api\MaterialRequirementApiController;

/*
|--------------------------------------------------------------------------
| Inventory Domain REST API Kit Routes
|--------------------------------------------------------------------------
| Base Prefix: /api/inventory
| Protected by: auth:sanctum, company, branch
|--------------------------------------------------------------------------
*/

Route::prefix('api/inventory')->name('api.inventory.')->group(function () {

    // Supply Chain Dashboard APIs
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/stats', [SupplyChainDashboardApiController::class, 'stats'])->name('stats');
    });

    // Product APIs
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/meta', [ProductApiController::class, 'meta'])->name('meta');
        Route::get('/export', [ProductApiController::class, 'export'])->name('export');
        Route::get('/barcode-lookup', [ProductApiController::class, 'barcodeLookup'])->name('barcode-lookup');
        Route::get('/stock-check', [ProductApiController::class, 'stockCheck'])->name('stock-check');
        Route::post('/quick-create', [ProductApiController::class, 'quickCreate'])->name('quick-create');
        Route::get('/', [ProductApiController::class, 'index'])->name('index');
        Route::post('/', [ProductApiController::class, 'store'])->name('store');
        Route::get('/{id}', [ProductApiController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{id}', [ProductApiController::class, 'update'])->name('update');
        Route::delete('/{id}', [ProductApiController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/toggle-status', [ProductApiController::class, 'toggleStatus'])->name('toggle-status');
        Route::get('/{id}/opening-stock', [ProductApiController::class, 'getOpeningStock'])->name('opening-stock.get');
        Route::post('/{id}/opening-stock', [ProductApiController::class, 'saveOpeningStock'])->name('opening-stock.save');
    });

    // Warehouse APIs
    Route::prefix('warehouses')->name('warehouses.')->group(function () {
        Route::get('/export', [WarehouseApiController::class, 'export'])->name('export');
        Route::post('/quick-create', [WarehouseApiController::class, 'quickCreate'])->name('quick-create');
        Route::get('/', [WarehouseApiController::class, 'index'])->name('index');
        Route::post('/', [WarehouseApiController::class, 'store'])->name('store');
        Route::get('/{id}', [WarehouseApiController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{id}', [WarehouseApiController::class, 'update'])->name('update');
        Route::delete('/{id}', [WarehouseApiController::class, 'destroy'])->name('destroy');
    });

    // Stock Transfer APIs
    Route::prefix('transfers')->name('transfers.')->group(function () {
        Route::get('/meta', [StockTransferApiController::class, 'meta'])->name('meta');
        Route::get('/export', [StockTransferApiController::class, 'export'])->name('export');
        Route::get('/', [StockTransferApiController::class, 'index'])->name('index');
        Route::post('/', [StockTransferApiController::class, 'store'])->name('store');
        Route::get('/{id}', [StockTransferApiController::class, 'show'])->name('show');
        Route::patch('/{id}/status', [StockTransferApiController::class, 'updateStatus'])->name('status');
        Route::post('/{id}/dispatch', [StockTransferApiController::class, 'dispatch'])->name('dispatch');
        Route::post('/{id}/receive', [StockTransferApiController::class, 'receive'])->name('receive');
        Route::post('/{id}/cancel', [StockTransferApiController::class, 'cancel'])->name('cancel');
    });

    // Stock Adjustment APIs
    Route::prefix('adjustments')->name('adjustments.')->group(function () {
        Route::get('/meta', [StockAdjustmentApiController::class, 'meta'])->name('meta');
        Route::get('/export', [StockAdjustmentApiController::class, 'export'])->name('export');
        Route::get('/', [StockAdjustmentApiController::class, 'index'])->name('index');
        Route::post('/', [StockAdjustmentApiController::class, 'store'])->name('store');
        Route::get('/{id}', [StockAdjustmentApiController::class, 'show'])->name('show');
        Route::post('/{id}/approve', [StockAdjustmentApiController::class, 'approve'])->name('approve');
        Route::post('/{id}/cancel', [StockAdjustmentApiController::class, 'cancel'])->name('cancel');
    });

    // Batch Tracking APIs
    Route::prefix('batches')->name('batches.')->group(function () {
        Route::get('/export', [BatchApiController::class, 'export'])->name('export');
        Route::get('/', [BatchApiController::class, 'index'])->name('index');
        Route::post('/', [BatchApiController::class, 'store'])->name('store');
        Route::get('/{id}', [BatchApiController::class, 'show'])->name('show');
    });

    // Serial Number Tracking APIs
    Route::prefix('serials')->name('serials.')->group(function () {
        Route::get('/export', [SerialNumberApiController::class, 'export'])->name('export');
        Route::get('/', [SerialNumberApiController::class, 'index'])->name('index');
        Route::post('/', [SerialNumberApiController::class, 'store'])->name('store');
        Route::get('/{id}', [SerialNumberApiController::class, 'show'])->name('show');
    });

    // Stock Ledger / Transaction Ledger APIs
    Route::prefix('ledger')->name('ledger.')->group(function () {
        Route::get('/export', [StockLedgerApiController::class, 'export'])->name('export');
        Route::get('/', [StockLedgerApiController::class, 'index'])->name('index');
    });

    // Inventory Reports APIs
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/low-stock', [InventoryReportApiController::class, 'lowStockReport'])->name('low-stock');
        Route::post('/low-stock/create-pr', [InventoryReportApiController::class, 'createPrFromLowStock'])->name('low-stock.create-pr');
        Route::get('/valuation', [InventoryReportApiController::class, 'valuationReport'])->name('valuation');
        Route::get('/valuation/export', [InventoryReportApiController::class, 'exportValuationReport'])->name('valuation.export');
    });

    // Stock Reservations APIs
    Route::prefix('reservations')->name('reservations.')->group(function () {
        Route::get('/', [StockReservationApiController::class, 'index'])->name('index');
        Route::post('/{id}/release', [StockReservationApiController::class, 'release'])->name('release');
    });

    // Barcode APIs
    Route::prefix('barcodes')->name('barcodes.')->group(function () {
        Route::get('/meta', [BarcodeApiController::class, 'meta'])->name('meta');
        Route::get('/serials/{productId}', [BarcodeApiController::class, 'getSerials'])->name('serials');
        Route::post('/print', [BarcodeApiController::class, 'print'])->name('print');
    });

    // Units of Measurement (UOM) APIs
    Route::prefix('uoms')->name('uoms.')->group(function () {
        Route::get('/', [UomApiController::class, 'index'])->name('index');
        Route::post('/', [UomApiController::class, 'store'])->name('store');
        Route::post('/quick-create', [UomApiController::class, 'quickCreate'])->name('quick-create');
    });

    // Material Requirements (Warehouse Dispatch Slips & Pick/Pack/Dispatch)
    Route::prefix('material-requirements')->name('material-requirements.')->group(function () {
        Route::get('/', [MaterialRequirementApiController::class, 'index'])->name('index');
        Route::post('/', [MaterialRequirementApiController::class, 'store'])->name('store');
        Route::get('/{id}', [MaterialRequirementApiController::class, 'show'])->name('show');
        Route::post('/{id}/pick', [MaterialRequirementApiController::class, 'startPicking'])->name('pick');
        Route::post('/{id}/pack', [MaterialRequirementApiController::class, 'pack'])->name('pack');
        Route::post('/{id}/dispatch', [MaterialRequirementApiController::class, 'dispatch'])->name('dispatch');
        Route::post('/{id}/deliver', [MaterialRequirementApiController::class, 'deliver'])->name('deliver');
        Route::post('/{id}/cancel', [MaterialRequirementApiController::class, 'cancel'])->name('cancel');
    });

});
