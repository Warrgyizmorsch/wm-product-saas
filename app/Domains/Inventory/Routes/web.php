<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Inventory\Controllers\ProductController;
use App\Domains\Inventory\Controllers\UomController;
use App\Domains\Inventory\Controllers\WarehouseController;
use App\Domains\Inventory\Controllers\SerialNumberController;
use App\Domains\Inventory\Controllers\BatchController;
use App\Domains\Inventory\Controllers\StockTransferController;
use App\Domains\Inventory\Controllers\StockAdjustmentController;
use App\Domains\Inventory\Controllers\StockTransactionController;
use App\Domains\Inventory\Controllers\InventoryReportController;
use App\Domains\Inventory\Controllers\BarcodeController;
use App\Domains\Inventory\Controllers\StockReservationController;

Route::prefix('inventory')
    ->as('inventory.')
    ->group(function (): void {
        // Products
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::get('products/download-sample', [ProductController::class, 'downloadSample'])->name('products.downloadSample');
        Route::get('products/barcode-lookup', [ProductController::class, 'barcodeLookup'])->name('products.barcodeLookup');
        Route::get('products/stock-check', [ProductController::class, 'stockCheck'])->name('products.stockCheck');
        Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
        Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::get('products/{product}/opening-stock', [ProductController::class, 'openingStock'])->name('products.opening-stock');
        Route::post('products/{product}/opening-stock', [ProductController::class, 'saveOpeningStock'])->name('products.opening-stock.save');

        // Serial Numbers & Batches
        Route::get('serial-numbers', [SerialNumberController::class, 'index'])->name('serial-numbers.index');
        Route::get('batches', [BatchController::class, 'index'])->name('batches.index');

        // Warehouses
        Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
        Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');

        // Stock Transfers
        Route::get('transfers', [StockTransferController::class, 'index'])->name('transfers.index');
        Route::get('transfers/create', [StockTransferController::class, 'create'])->name('transfers.create');
        Route::post('transfers', [StockTransferController::class, 'store'])->name('transfers.store');
        Route::get('transfers/{transfer}', [StockTransferController::class, 'show'])->name('transfers.show');
        Route::post('transfers/{transfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('transfers.dispatch');
        Route::post('transfers/{transfer}/receive', [StockTransferController::class, 'receive'])->name('transfers.receive');
        Route::post('transfers/{transfer}/cancel', [StockTransferController::class, 'cancel'])->name('transfers.cancel');

        // Stock Adjustments
        Route::get('adjustments', [StockAdjustmentController::class, 'index'])->name('adjustments.index');
        Route::get('adjustments/create', [StockAdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('adjustments', [StockAdjustmentController::class, 'store'])->name('adjustments.store');
        Route::get('adjustments/{adjustment}', [StockAdjustmentController::class, 'show'])->name('adjustments.show');
        Route::post('adjustments/{adjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('adjustments.approve');
        Route::post('adjustments/{adjustment}/cancel', [StockAdjustmentController::class, 'cancel'])->name('adjustments.cancel');

        // Stock Ledger / Transactions
        Route::get('transactions', [StockTransactionController::class, 'index'])->name('transactions.index');

        // Reports
        Route::get('reports/low-stock', [InventoryReportController::class, 'lowStockReport'])->name('reports.low-stock');
        Route::get('reports/expiry', fn() => redirect()->route('inventory.batches.index'))->name('reports.expiry');
        Route::get('reports/valuation', [InventoryReportController::class, 'valuationReport'])->name('reports.valuation');

        // Barcode Generator
        Route::get('barcodes', [BarcodeController::class, 'index'])->name('barcodes.index');
        Route::get('barcodes/serials/{product}', [BarcodeController::class, 'getSerials'])->name('barcodes.serials');
        Route::post('barcodes/print', [BarcodeController::class, 'print'])->name('barcodes.print');

        // Stock Reservations
        Route::get('reservations', [StockReservationController::class, 'index'])->name('reservations.index');
        Route::post('reservations/{reservation}/release', [StockReservationController::class, 'release'])->name('reservations.release');
    });

Route::post('products/quick-create', [ProductController::class, 'quickCreate'])
    ->name('products.quick-create');
Route::post('uoms/quick-create', [UomController::class, 'quickCreate'])
    ->name('uoms.quick-create');
Route::post('warehouses/quick-create', [WarehouseController::class, 'quickCreate'])
    ->name('warehouses.quick-create');
