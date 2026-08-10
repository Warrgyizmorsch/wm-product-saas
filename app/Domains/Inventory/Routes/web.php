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
use App\Domains\Sales\Controllers\MaterialRequirementController;
use App\Domains\Sales\Controllers\MaterialRequestController;
use App\Domains\Sales\Controllers\DispatchOrderController;

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

        // Material Requirements Routes (/inventory/material-requirements)
        Route::get('material-requirements', [MaterialRequirementController::class, 'index'])->name('material-requirements.index');
        Route::get('material-requirements/create', [MaterialRequirementController::class, 'create'])->name('material-requirements.create');
        Route::post('material-requirements', [MaterialRequirementController::class, 'store'])->name('material-requirements.store');
        Route::get('material-requirements/{delivery}', [MaterialRequirementController::class, 'show'])->name('material-requirements.show');
        Route::post('material-requirements/{delivery}/ship', [MaterialRequirementController::class, 'ship'])->name('material-requirements.ship');
        Route::post('material-requirements/{delivery}/cancel', [MaterialRequirementController::class, 'cancel'])->name('material-requirements.cancel');

        Route::post('material-requirements/items/{itemId}/warehouse', [MaterialRequirementController::class, 'updateWarehouse'])->name('material-requirements.update-warehouse');
        Route::post('material-requirements/items/{itemId}/reserve', [MaterialRequirementController::class, 'reserveQty'])->name('material-requirements.reserve-qty');
        Route::post('material-requirements/items/{itemId}/indent', [MaterialRequirementController::class, 'mockIndent'])->name('material-requirements.mock-indent');
        Route::post('material-requirements/items/{itemId}/mo', [MaterialRequirementController::class, 'mockMo'])->name('material-requirements.mock-mo');
        Route::post('material-requirements/{delivery}/picking', [MaterialRequirementController::class, 'startPicking'])->name('material-requirements.picking');
        Route::post('material-requirements/{delivery}/pack', [MaterialRequirementController::class, 'pack'])->name('material-requirements.pack');
        Route::post('material-requirements/{delivery}/dispatch', [MaterialRequirementController::class, 'dispatch'])->name('material-requirements.dispatch');
        Route::post('material-requirements/{delivery}/dispatch-order', [MaterialRequirementController::class, 'storeDispatchOrder'])->name('material-requirements.dispatch-order.store');
        Route::post('material-requirements/{delivery}/deliver', [MaterialRequirementController::class, 'deliver'])->name('material-requirements.deliver');

        // Material Requests (Prod) Routes (/inventory/material-requests)
        Route::get('material-requests', [MaterialRequestController::class, 'index'])->name('material-requests.index');
        Route::get('material-requests/{id}', [MaterialRequestController::class, 'show'])->name('material-requests.show');
        Route::post('material-requests/items/{id}/reserve', [MaterialRequestController::class, 'reserve'])->name('material-requests.reserve');
        Route::post('material-requests/items/{id}/issue', [MaterialRequestController::class, 'issue'])->name('material-requests.issue');
        Route::post('material-requests/items/{id}/create-pr', [MaterialRequestController::class, 'createPurchaseRequisition'])->name('material-requests.create-pr');
        Route::post('material-requests/{id}/bulk-action', [MaterialRequestController::class, 'bulkAction'])->name('material-requests.bulk-action');

        // Dispatch Orders Routes (/inventory/dispatches)
        Route::get('dispatches', [DispatchOrderController::class, 'index'])->name('dispatches.index');
        Route::get('dispatches/create', [DispatchOrderController::class, 'create'])->name('dispatches.create');
        Route::post('dispatches', [DispatchOrderController::class, 'store'])->name('dispatches.store');
        Route::get('dispatches/material-requirements', [DispatchOrderController::class, 'pendingMaterialRequirements'])->name('dispatches.pending-mr');
        Route::get('dispatches/available-serials', [DispatchOrderController::class, 'getAvailableSerials'])->name('dispatches.available-serials');
        Route::get('dispatches/available-batches', [DispatchOrderController::class, 'getAvailableBatches'])->name('dispatches.available-batches');
        Route::get('dispatches/warehouse/{warehouse}/address', [DispatchOrderController::class, 'warehouseAddress'])->name('dispatches.warehouse-address');
        Route::get('dispatches/{dispatch}', [DispatchOrderController::class, 'show'])->name('dispatches.show');
        Route::post('dispatches/{dispatch}/confirm', [DispatchOrderController::class, 'confirm'])->name('dispatches.confirm');
    });

Route::post('products/quick-create', [ProductController::class, 'quickCreate'])
    ->name('products.quick-create');
Route::post('uoms/quick-create', [UomController::class, 'quickCreate'])
    ->name('uoms.quick-create');
Route::post('warehouses/quick-create', [WarehouseController::class, 'quickCreate'])
    ->name('warehouses.quick-create');
