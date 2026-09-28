<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;

$user = User::first();
auth()->login($user);

$endpoints = [
    // CRM
    ['CRM Lead Meta', App\Domains\CRM\Controllers\Api\LeadApiController::class, 'meta', 'GET', []],
    ['CRM Lead List', App\Domains\CRM\Controllers\Api\LeadApiController::class, 'index', 'GET', ['per_page' => 5]],
    ['CRM Lead Status List', App\Domains\CRM\Controllers\Api\LeadStatusApiController::class, 'index', 'GET', []],
    ['CRM Deal Meta', App\Domains\CRM\Controllers\Api\CrmDealApiController::class, 'meta', 'GET', []],
    ['CRM Deal List', App\Domains\CRM\Controllers\Api\CrmDealApiController::class, 'index', 'GET', ['per_page' => 5]],
    ['CRM Deal Status List', App\Domains\CRM\Controllers\Api\DealStatusApiController::class, 'index', 'GET', []],

    // Sales
    ['Sales Customer Meta', App\Domains\Sales\Controllers\Api\CustomerApiController::class, 'meta', 'GET', []],
    ['Sales Customer List', App\Domains\Sales\Controllers\Api\CustomerApiController::class, 'index', 'GET', ['per_page' => 5]],
    ['Sales Order Meta', App\Domains\Sales\Controllers\Api\SalesOrderApiController::class, 'meta', 'GET', []],
    ['Sales Order List', App\Domains\Sales\Controllers\Api\SalesOrderApiController::class, 'index', 'GET', ['per_page' => 5]],
    ['Sales Invoice Meta', App\Domains\Sales\Controllers\Api\InvoiceApiController::class, 'meta', 'GET', []],
    ['Sales Invoice List', App\Domains\Sales\Controllers\Api\InvoiceApiController::class, 'index', 'GET', ['per_page' => 5]],
    ['Sales Payment Meta', App\Domains\Sales\Controllers\Api\CustomerPaymentApiController::class, 'meta', 'GET', []],
    ['Sales Payment List', App\Domains\Sales\Controllers\Api\CustomerPaymentApiController::class, 'index', 'GET', ['per_page' => 5]],
    ['Sales Dispatch List', App\Domains\Sales\Controllers\Api\DispatchOrderApiController::class, 'index', 'GET', ['per_page' => 5]],

    // Purchase
    ['Purchase Vendor Meta', App\Domains\Purchase\Controllers\Api\VendorApiController::class, 'meta', 'GET', []],
    ['Purchase Vendor List', App\Domains\Purchase\Controllers\Api\VendorApiController::class, 'index', 'GET', ['per_page' => 5]],
    ['Purchase Order Meta', App\Domains\Purchase\Controllers\Api\PurchaseOrderApiController::class, 'meta', 'GET', []],
    ['Purchase Order List', App\Domains\Purchase\Controllers\Api\PurchaseOrderApiController::class, 'index', 'GET', ['per_page' => 5]],
    ['Purchase GRN List', App\Domains\Purchase\Controllers\Api\GoodsReceiptNoteApiController::class, 'index', 'GET', ['per_page' => 5]],
    ['Purchase Bill Meta', App\Domains\Purchase\Controllers\Api\VendorBillApiController::class, 'meta', 'GET', []],
    ['Purchase Bill List', App\Domains\Purchase\Controllers\Api\VendorBillApiController::class, 'index', 'GET', ['per_page' => 5]],
    ['Purchase Payment Meta', App\Domains\Purchase\Controllers\Api\VendorPaymentApiController::class, 'meta', 'GET', []],
    ['Purchase Payment List', App\Domains\Purchase\Controllers\Api\VendorPaymentApiController::class, 'index', 'GET', ['per_page' => 5]],

    // Inventory
    ['Inventory Product Export', App\Domains\Inventory\Controllers\Api\ProductApiController::class, 'export', 'GET', []],
    ['Inventory Warehouse List', App\Domains\Inventory\Controllers\Api\WarehouseApiController::class, 'index', 'GET', []],
    ['Inventory Transfer Meta', App\Domains\Inventory\Controllers\Api\StockTransferApiController::class, 'meta', 'GET', []],
    ['Inventory Transfer List', App\Domains\Inventory\Controllers\Api\StockTransferApiController::class, 'index', 'GET', ['per_page' => 5]],
    ['Inventory Adjustment List', App\Domains\Inventory\Controllers\Api\StockAdjustmentApiController::class, 'index', 'GET', ['per_page' => 5]],
    ['Inventory Batch List', App\Domains\Inventory\Controllers\Api\BatchApiController::class, 'index', 'GET', ['per_page' => 5]],
    ['Inventory Serial List', App\Domains\Inventory\Controllers\Api\SerialNumberApiController::class, 'index', 'GET', ['per_page' => 5]],
    ['Inventory Ledger List', App\Domains\Inventory\Controllers\Api\StockLedgerApiController::class, 'index', 'GET', ['per_page' => 5]],
];

echo "==================================================\n";
echo "   RUNNING COMPREHENSIVE ERP REST API TEST SUITE   \n";
echo "==================================================\n";

$pass = 0;
$fail = 0;

foreach ($endpoints as [$label, $class, $method, $httpMethod, $params]) {
    try {
        $controller = app($class);
        $request = Request::create('/test', $httpMethod, $params);
        $response = $controller->{$method}($request);

        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            echo "✅ [{$status}] {$label}\n";
            $pass++;
        } else {
            echo "❌ [{$status}] {$label} - Non-200 status\n";
            $fail++;
        }
    } catch (\Throwable $e) {
        echo "❌ [ERR] {$label}: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
        $fail++;
    }
}

echo "==================================================\n";
echo "RESULTS: {$pass} Passed, {$fail} Failed\n";
echo "==================================================\n";
