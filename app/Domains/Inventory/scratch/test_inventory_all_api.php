<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
if (!$user) {
    echo "No user found\n";
    exit(1);
}

$tenantId = $user->tenant_id ?? 1;
$token = $user->createToken('test-token')->plainTextToken;
$baseUrl = 'http://127.0.0.1:8000/api/inventory';

function callApi($method, $url, $data = [], $token = '') {
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ];
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($data)) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif (in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($data)) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return ['code' => $httpCode, 'response' => $response, 'json' => json_decode($response, true), 'error' => $error];
}

echo "=================================================================\n";
echo "       COMPREHENSIVE END-TO-END INVENTORY MODULE API TEST       \n";
echo "=================================================================\n\n";

$passed = 0;
$failed = 0;

function runTest($label, $method, $url, $data, $token, &$passed, &$failed, $expectedCodes = [200, 201]) {
    $res = callApi($method, $url, $data, $token);
    $code = $res['code'];
    $shortUrl = str_replace('http://127.0.0.1:8000/api/inventory', '', $url);

    if (in_array($code, $expectedCodes)) {
        echo sprintf("[PASS] [%s] %-45s HTTP %d\n", $method, $shortUrl, $code);
        $passed++;
        return $res['json'];
    } else {
        echo sprintf("[FAIL] [%s] %-45s HTTP %d\n  Response: %s\n", $method, $shortUrl, $code, substr($res['response'], 0, 300));
        $failed++;
        return null;
    }
}

// 1. DASHBOARD
runTest('Dashboard Stats', 'GET', "{$baseUrl}/dashboard/stats", [], $token, $passed, $failed);

// 2. PRODUCTS
$pMeta = runTest('Product Meta', 'GET', "{$baseUrl}/products/meta", [], $token, $passed, $failed);
$pList = runTest('Product List', 'GET', "{$baseUrl}/products", [], $token, $passed, $failed);
runTest('Product Export', 'GET', "{$baseUrl}/products/export", [], $token, $passed, $failed);

// Create Product via API
$pNew = runTest('Product Create', 'POST', "{$baseUrl}/products", [
    'name' => 'Auto Test Product ' . rand(1000, 9999),
    'sku' => 'SKU-ATP-' . rand(1000, 9999),
    'selling_price' => 299.99,
    'cost_price' => 180.00,
    'unit' => 'pcs',
    'type' => 'finished_good',
    'status' => 'active',
    'reorder_point' => 5,
], $token, $passed, $failed);

$productId = $pNew['data']['id'] ?? null;
$productSku = $pNew['data']['sku'] ?? null;

if ($productId) {
    runTest('Product Show', 'GET', "{$baseUrl}/products/{$productId}", [], $token, $passed, $failed);
    runTest('Product Update', 'PUT', "{$baseUrl}/products/{$productId}", [
        'name' => 'Auto Test Product Updated',
        'selling_price' => 320.00,
    ], $token, $passed, $failed);
    runTest('Product Toggle Status', 'POST', "{$baseUrl}/products/{$productId}/toggle-status", [], $token, $passed, $failed);
    runTest('Product Barcode Lookup', 'GET', "{$baseUrl}/products/barcode-lookup?code={$productSku}", [], $token, $passed, $failed);
    runTest('Product Get Opening Stock', 'GET', "{$baseUrl}/products/{$productId}/opening-stock", [], $token, $passed, $failed);
}

// 3. WAREHOUSES
$wList = runTest('Warehouse List', 'GET', "{$baseUrl}/warehouses", [], $token, $passed, $failed);
runTest('Warehouse Export', 'GET', "{$baseUrl}/warehouses/export", [], $token, $passed, $failed);

$wNew = runTest('Warehouse Quick Create', 'POST', "{$baseUrl}/warehouses/quick-create", [
    'name' => 'WH Secondary ' . rand(100, 999),
    'code' => 'WHS' . rand(100, 999),
    'address' => 'Secondary storage unit',
], $token, $passed, $failed);

$wId1 = $wList['data'][0]['id'] ?? 1;
$wId2 = $wNew['data']['id'] ?? 2;

if ($productId && $wId1) {
    runTest('Product Stock Check', 'GET', "{$baseUrl}/products/stock-check?product_id={$productId}&warehouse_id={$wId1}", [], $token, $passed, $failed);
    runTest('Product Save Opening Stock', 'POST', "{$baseUrl}/products/{$productId}/opening-stock", [
        'stocks' => [
            ['warehouse_id' => $wId1, 'quantity' => 50, 'unit_cost' => 180.00]
        ]
    ], $token, $passed, $failed);
}

// 4. STOCK TRANSFERS
runTest('Stock Transfer Meta', 'GET', "{$baseUrl}/transfers/meta", [], $token, $passed, $failed);
runTest('Stock Transfer List', 'GET', "{$baseUrl}/transfers", [], $token, $passed, $failed);
runTest('Stock Transfer Export', 'GET', "{$baseUrl}/transfers/export", [], $token, $passed, $failed);

if ($productId && $wId1 && $wId2 && $wId1 !== $wId2) {
    $stNew = runTest('Stock Transfer Create', 'POST', "{$baseUrl}/transfers", [
        'from_warehouse_id' => $wId1,
        'to_warehouse_id' => $wId2,
        'transfer_date' => date('Y-m-d'),
        'notes' => 'API automated transfer test',
        'items' => [
            ['product_id' => $productId, 'quantity' => 10]
        ]
    ], $token, $passed, $failed);

    $stId = $stNew['data']['id'] ?? null;
    if ($stId) {
        runTest('Stock Transfer Show', 'GET', "{$baseUrl}/transfers/{$stId}", [], $token, $passed, $failed);
        runTest('Stock Transfer Dispatch', 'POST', "{$baseUrl}/transfers/{$stId}/dispatch", [], $token, $passed, $failed);
        runTest('Stock Transfer Receive', 'POST', "{$baseUrl}/transfers/{$stId}/receive", [], $token, $passed, $failed);
    }
}

// 5. STOCK ADJUSTMENTS
runTest('Stock Adjustment Meta', 'GET', "{$baseUrl}/adjustments/meta", [], $token, $passed, $failed);
runTest('Stock Adjustment List', 'GET', "{$baseUrl}/adjustments", [], $token, $passed, $failed);
runTest('Stock Adjustment Export', 'GET', "{$baseUrl}/adjustments/export", [], $token, $passed, $failed);

if ($productId && $wId1) {
    $adjNew = runTest('Stock Adjustment Create (Draft)', 'POST', "{$baseUrl}/adjustments", [
        'warehouse_id' => $wId1,
        'adjustment_date' => date('Y-m-d'),
        'reason' => 'Stock Count Variance',
        'status' => 'Draft',
        'notes' => 'API automated adjustment test',
        'items' => [
            ['product_id' => $productId, 'type' => 'Addition', 'quantity' => 5, 'unit_cost' => 180.00]
        ]
    ], $token, $passed, $failed);

    $adjId = $adjNew['data']['id'] ?? null;
    if ($adjId) {
        runTest('Stock Adjustment Show', 'GET', "{$baseUrl}/adjustments/{$adjId}", [], $token, $passed, $failed);
        runTest('Stock Adjustment Approve', 'POST', "{$baseUrl}/adjustments/{$adjId}/approve", [], $token, $passed, $failed);
    }
}

// 6. BATCHES
runTest('Batch List', 'GET', "{$baseUrl}/batches", [], $token, $passed, $failed);
runTest('Batch Export', 'GET', "{$baseUrl}/batches/export", [], $token, $passed, $failed);
if ($productId && $wId1) {
    $batchNew = runTest('Batch Create', 'POST', "{$baseUrl}/batches", [
        'product_id' => $productId,
        'warehouse_id' => $wId1,
        'batch_number' => 'BATCH-TEST-' . rand(1000, 9999),
        'quantity' => 20,
        'manufacturing_date' => date('Y-m-d'),
        'expiry_date' => date('Y-m-d', strtotime('+180 days')),
    ], $token, $passed, $failed);
    $batchId = $batchNew['data']['id'] ?? null;
    if ($batchId) {
        runTest('Batch Show', 'GET', "{$baseUrl}/batches/{$batchId}", [], $token, $passed, $failed);
    }
}

// 7. SERIAL NUMBERS
runTest('Serial Number List', 'GET', "{$baseUrl}/serials", [], $token, $passed, $failed);
runTest('Serial Number Export', 'GET', "{$baseUrl}/serials/export", [], $token, $passed, $failed);
if ($productId && $wId1) {
    $snNew = runTest('Serial Number Create', 'POST', "{$baseUrl}/serials", [
        'product_id' => $productId,
        'warehouse_id' => $wId1,
        'serial_number' => 'SN-TEST-' . rand(10000, 99999),
        'purchase_rate' => 180.00,
        'status' => 'Available',
    ], $token, $passed, $failed);
    $snId = $snNew['data']['id'] ?? null;
    if ($snId) {
        runTest('Serial Number Show', 'GET', "{$baseUrl}/serials/{$snId}", [], $token, $passed, $failed);
    }
}

// 8. STOCK LEDGER
runTest('Stock Ledger List', 'GET', "{$baseUrl}/ledger", [], $token, $passed, $failed);
runTest('Stock Ledger Export', 'GET', "{$baseUrl}/ledger/export", [], $token, $passed, $failed);

// 9. INVENTORY REPORTS
runTest('Low Stock Report', 'GET', "{$baseUrl}/reports/low-stock", [], $token, $passed, $failed);
if ($productId) {
    runTest('Create PR From Low Stock', 'POST', "{$baseUrl}/reports/low-stock/create-pr", [
        'product_ids' => [$productId]
    ], $token, $passed, $failed);
}
runTest('Stock Valuation Report', 'GET', "{$baseUrl}/reports/valuation", [], $token, $passed, $failed);
runTest('Stock Valuation Report Export', 'GET', "{$baseUrl}/reports/valuation/export", [], $token, $passed, $failed);

// 10. STOCK RESERVATIONS
runTest('Stock Reservations List', 'GET', "{$baseUrl}/reservations", [], $token, $passed, $failed);

// 11. BARCODES
runTest('Barcode Meta', 'GET', "{$baseUrl}/barcodes/meta", [], $token, $passed, $failed);
if ($productId) {
    runTest('Barcode Serials Lookup', 'GET', "{$baseUrl}/barcodes/serials/{$productId}", [], $token, $passed, $failed);
    runTest('Barcode Print', 'POST', "{$baseUrl}/barcodes/print", [
        'product_id' => $productId,
        'print_type' => 'product',
        'copies' => 2
    ], $token, $passed, $failed);
}

// 12. UOMs
runTest('UOM List', 'GET', "{$baseUrl}/uoms", [], $token, $passed, $failed);
runTest('UOM Store', 'POST', "{$baseUrl}/uoms", [
    'name' => 'Drum ' . rand(100, 999),
    'code' => 'DRM' . rand(100, 999),
    'description' => 'Industrial liquid drum',
], $token, $passed, $failed);
runTest('UOM Quick Create', 'POST', "{$baseUrl}/uoms/quick-create", [
    'name' => 'Packet ' . rand(100, 999),
    'code' => 'PKT' . rand(100, 999),
], $token, $passed, $failed);

// 13. MATERIAL REQUIREMENTS
runTest('Material Requirements List', 'GET', "{$baseUrl}/material-requirements", [], $token, $passed, $failed);

echo "\n=================================================================\n";
echo "FINAL RESULTS: Passed: {$passed} | Failed: {$failed} | Total: " . ($passed + $failed) . "\n";
echo "=================================================================\n";
