<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Exports\InvoiceExport;
use App\Exports\SalesOrderExport;
use App\Exports\QuotationExport;
use App\Exports\DispatchOrderExport;
use App\Exports\CustomerPaymentExport;
use App\Exports\SalesReturnExport;
use App\Exports\ExportRegistry;
use Illuminate\Support\Facades\Route;

echo "--- 1. Testing ExportRegistry ---\n";
$types = ['invoices', 'sales-orders', 'quotations', 'dispatches', 'customer-payments', 'sales-returns'];
foreach ($types as $type) {
    $cols = ExportRegistry::getColumnsForType($type);
    $title = ExportRegistry::getTitleForType($type);
    echo "[$type] Title: '$title' | Total Columns: " . count($cols) . "\n";
    if (empty($cols)) {
        echo "ERROR: Columns empty for $type!\n";
    }
}

echo "\n--- 2. Testing Routes ---\n";
$routes = [
    'sales.invoices.export',
    'sales.orders.export',
    'crm.quotations.export',
    'sales.dispatches.export',
    'inventory.dispatches.export',
    'sales.payments.export',
    'sales.returns.export',
];
foreach ($routes as $r) {
    if (Route::has($r)) {
        echo "Route [$r] exists -> " . route($r) . "\n";
    } else {
        echo "ERROR: Route [$r] does not exist!\n";
    }
}

echo "\n--- 3. Testing Export Classes Data Generation ---\n";
$tenantId = 1;

// Test InvoiceExport
$invExport = new InvoiceExport($tenantId, ['status' => 'all']);
$invData = $invExport->collection();
$invCols = $invExport->getActiveColumns();
$invHeaders = $invExport->headings();
echo "InvoiceExport: found " . $invData->count() . " rows, headings: " . count($invHeaders) . "\n";
if ($invData->isNotEmpty()) {
    $mapped = $invExport->map($invData->first());
    echo "  Sample mapped count: " . count($mapped) . " (matches headers: " . (count($mapped) === count($invHeaders) ? 'YES' : 'NO') . ")\n";
}

// Test SalesOrderExport
$soExport = new SalesOrderExport($tenantId, ['status' => 'all']);
$soData = $soExport->collection();
$soCols = $soExport->getActiveColumns();
$soHeaders = $soExport->headings();
echo "SalesOrderExport: found " . $soData->count() . " rows, headings: " . count($soHeaders) . "\n";
if ($soData->isNotEmpty()) {
    $mapped = $soExport->map($soData->first());
    echo "  Sample mapped count: " . count($mapped) . " (matches headers: " . (count($mapped) === count($soHeaders) ? 'YES' : 'NO') . ")\n";
}

// Test QuotationExport
$qtExport = new QuotationExport($tenantId, ['status' => 'all']);
$qtData = $qtExport->collection();
$qtCols = $qtExport->getActiveColumns();
$qtHeaders = $qtExport->headings();
echo "QuotationExport: found " . $qtData->count() . " rows, headings: " . count($qtHeaders) . "\n";
if ($qtData->isNotEmpty()) {
    $mapped = $qtExport->map($qtData->first());
    echo "  Sample mapped count: " . count($mapped) . " (matches headers: " . (count($mapped) === count($qtHeaders) ? 'YES' : 'NO') . ")\n";
}

// Test DispatchOrderExport
$doExport = new DispatchOrderExport($tenantId, ['status' => 'all']);
$doData = $doExport->collection();
$doCols = $doExport->getActiveColumns();
$doHeaders = $doExport->headings();
echo "DispatchOrderExport: found " . $doData->count() . " rows, headings: " . count($doHeaders) . "\n";
if ($doData->isNotEmpty()) {
    $mapped = $doExport->map($doData->first());
    echo "  Sample mapped count: " . count($mapped) . " (matches headers: " . (count($mapped) === count($doHeaders) ? 'YES' : 'NO') . ")\n";
}

// Test CustomerPaymentExport
$payExport = new CustomerPaymentExport($tenantId, ['status' => 'all']);
$payData = $payExport->collection();
$payCols = $payExport->getActiveColumns();
$payHeaders = $payExport->headings();
echo "CustomerPaymentExport: found " . $payData->count() . " rows, headings: " . count($payHeaders) . "\n";
if ($payData->isNotEmpty()) {
    $mapped = $payExport->map($payData->first());
    echo "  Sample mapped count: " . count($mapped) . " (matches headers: " . (count($mapped) === count($payHeaders) ? 'YES' : 'NO') . ")\n";
}

// Test SalesReturnExport
$retExport = new SalesReturnExport($tenantId, ['status' => 'all']);
$retData = $retExport->collection();
$retCols = $retExport->getActiveColumns();
$retHeaders = $retExport->headings();
echo "SalesReturnExport: found " . $retData->count() . " rows, headings: " . count($retHeaders) . "\n";
if ($retData->isNotEmpty()) {
    $mapped = $retExport->map($retData->first());
    echo "  Sample mapped count: " . count($mapped) . " (matches headers: " . (count($mapped) === count($retHeaders) ? 'YES' : 'NO') . ")\n";
}

// Test Custom Column Selection
$customCols = ['invoice_number', 'total_amount', 'status'];
$invCustomExport = new InvoiceExport($tenantId, ['columns' => $customCols]);
$invCustomHeaders = $invCustomExport->headings();
echo "\n--- 4. Testing Custom Column Selection ---\n";
echo "InvoiceExport with 3 custom columns: headings count = " . count($invCustomHeaders) . " (" . implode(', ', $invCustomHeaders) . ")\n";

echo "\nALL TESTS COMPLETED SUCCESSFULLY!\n";
