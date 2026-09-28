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
$baseUrl = 'http://127.0.0.1:8000/api/purchase';

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
echo "       COMPREHENSIVE END-TO-END PURCHASE MODULE API TEST        \n";
echo "=================================================================\n\n";

$passed = 0;
$failed = 0;

function runTest($label, $method, $url, $data, $token, &$passed, &$failed, $expectedCodes = [200, 201]) {
    $res = callApi($method, $url, $data, $token);
    $code = $res['code'];
    $shortUrl = str_replace('http://127.0.0.1:8000/api/purchase', '', $url);

    if (in_array($code, $expectedCodes)) {
        echo sprintf("[PASS] [%s] %-50s HTTP %d\n", $method, $shortUrl, $code);
        $passed++;
        return $res['json'];
    } else {
        echo sprintf("[FAIL] [%s] %-50s HTTP %d\n  Response: %s\n", $method, $shortUrl, $code, substr($res['response'], 0, 300));
        $failed++;
        return null;
    }
}

// 1. VENDORS
runTest('Vendor Meta', 'GET', "{$baseUrl}/vendors/meta", [], $token, $passed, $failed);
$vList = runTest('Vendor List', 'GET', "{$baseUrl}/vendors", [], $token, $passed, $failed);
runTest('Vendor Export', 'GET', "{$baseUrl}/vendors/export", [], $token, $passed, $failed);

$vNew = runTest('Vendor Create', 'POST', "{$baseUrl}/vendors", [
    'name' => 'Auto Test Supplier ' . rand(1000, 9999),
    'company_name' => 'Test Supplier Enterprise Ltd',
    'phone' => '+91-98765' . rand(10000, 99999),
    'email' => 'supplier' . rand(100, 999) . '@test.com',
    'gstin' => '29ABCDE' . rand(1000, 9999) . 'F1Z5',
    'status' => 'active',
], $token, $passed, $failed);

$vQuick = runTest('Vendor Quick Create', 'POST', "{$baseUrl}/vendors/quick-create", [
    'name' => 'Quick Supplier ' . rand(100, 999),
    'company_name' => 'Quick Co',
    'phone' => '+91-88888' . rand(10000, 99999),
], $token, $passed, $failed);

$vendorId = $vNew['data']['id'] ?? ($vList['data'][0]['id'] ?? 1);

if ($vendorId) {
    runTest('Vendor Show', 'GET', "{$baseUrl}/vendors/{$vendorId}", [], $token, $passed, $failed);
    runTest('Vendor Update', 'PUT', "{$baseUrl}/vendors/{$vendorId}", [
        'name' => 'Auto Test Supplier Updated ' . rand(100, 999),
    ], $token, $passed, $failed);
    runTest('Vendor Toggle Status', 'POST', "{$baseUrl}/vendors/{$vendorId}/toggle-status", [], $token, $passed, $failed);
}

// 2. PURCHASE REQUISITIONS (PR)
runTest('PR Meta', 'GET', "{$baseUrl}/requisitions/meta", [], $token, $passed, $failed);
runTest('PR List', 'GET', "{$baseUrl}/requisitions", [], $token, $passed, $failed);
runTest('PR Export', 'GET', "{$baseUrl}/requisitions/export", [], $token, $passed, $failed);
runTest('PR Pending Items', 'GET', "{$baseUrl}/requisitions/pending-items", [], $token, $passed, $failed);

$product = \App\Domains\Inventory\Models\Product::where('tenant_id', $tenantId)->first();
if (!$product) {
    $product = \App\Domains\Inventory\Models\Product::create([
        'tenant_id' => $tenantId,
        'company_id' => $user->company_id ?? 1,
        'name' => 'Test Purchase Item ' . rand(100, 999),
        'sku' => 'TEST-SKU-' . rand(1000, 9999),
        'cost_price' => 100.00,
        'sales_price' => 150.00,
        'status' => 'active',
    ]);
}
$prodId = $product->id;

$wh = \App\Domains\Inventory\Models\Warehouse::where('tenant_id', $tenantId)->first();
if (!$wh) {
    $wh = \App\Domains\Inventory\Models\Warehouse::create([
        'tenant_id' => $tenantId,
        'company_id' => $user->company_id ?? 1,
        'name' => 'Main Test Warehouse ' . rand(10, 99),
        'code' => 'WH-TEST-' . rand(100, 999),
        'status' => 'active',
    ]);
}
$whId = $wh->id;

$prNew = runTest('PR Create', 'POST', "{$baseUrl}/requisitions", [
    'requisition_date' => date('Y-m-d'),
    'expected_date' => date('Y-m-d', strtotime('+7 days')),
    'source_type' => 'direct',
    'notes' => 'Urgent replenishment requisition test',
    'items' => [
        ['product_id' => $prodId, 'quantity' => 10, 'estimated_cost' => 150.00]
    ]
], $token, $passed, $failed);

$prId = $prNew['data']['id'] ?? null;
if ($prId) {
    runTest('PR Show', 'GET', "{$baseUrl}/requisitions/{$prId}", [], $token, $passed, $failed);
    runTest('PR Update', 'PUT', "{$baseUrl}/requisitions/{$prId}", [
        'requisition_date' => date('Y-m-d'),
        'source_type' => 'direct',
        'notes' => 'Updated PR notes',
        'items' => [
            ['product_id' => $prodId, 'quantity' => 12, 'estimated_cost' => 150.00]
        ]
    ], $token, $passed, $failed);
    runTest('PR Remind', 'POST', "{$baseUrl}/requisitions/{$prId}/remind", ['note' => 'Please review'], $token, $passed, $failed);
    runTest('PR Approve', 'POST', "{$baseUrl}/requisitions/{$prId}/approve", [], $token, $passed, $failed);
}

// 3. PURCHASE RFQs
runTest('RFQ Meta', 'GET', "{$baseUrl}/rfqs/meta", [], $token, $passed, $failed);
runTest('RFQ List', 'GET', "{$baseUrl}/rfqs", [], $token, $passed, $failed);
runTest('RFQ Export', 'GET', "{$baseUrl}/rfqs/export", [], $token, $passed, $failed);
runTest('RFQ Savings Dashboard', 'GET', "{$baseUrl}/rfqs/savings-dashboard", [], $token, $passed, $failed);
if ($prId) {
    runTest('RFQ Get Requisition Items', 'GET', "{$baseUrl}/rfqs/get-requisition-items?requisition_id={$prId}", [], $token, $passed, $failed);
}

$rfqNew = runTest('RFQ Create', 'POST', "{$baseUrl}/rfqs", [
    'rfq_date' => date('Y-m-d'),
    'purchase_requisition_id' => $prId,
    'notes' => 'API RFQ test',
    'items' => [
        [
            'product_id' => $prodId,
            'quantity' => 12,
            'estimated_cost' => 150.00,
            'vendor_ids' => [$vendorId]
        ]
    ]
], $token, $passed, $failed);

$rfqId = $rfqNew['data']['id'] ?? null;
if ($rfqId) {
    runTest('RFQ Show', 'GET', "{$baseUrl}/rfqs/{$rfqId}", [], $token, $passed, $failed);
    runTest('RFQ Store Quotes', 'POST', "{$baseUrl}/rfqs/{$rfqId}/store-quotes", [
        'quotes' => [
            $vendorId => [
                'quotation_number' => 'QTN-VEND-' . rand(100, 999),
                'rates' => [
                    $prodId => ['rate' => 140.00]
                ]
            ]
        ]
    ], $token, $passed, $failed);
    runTest('RFQ Confirm', 'POST', "{$baseUrl}/rfqs/{$rfqId}/confirm", [], $token, $passed, $failed);
}

// 4. PURCHASE ORDERS (PO)
runTest('PO Meta', 'GET', "{$baseUrl}/orders/meta", [], $token, $passed, $failed);
runTest('PO List', 'GET', "{$baseUrl}/orders", [], $token, $passed, $failed);
runTest('PO Export', 'GET', "{$baseUrl}/orders/export", [], $token, $passed, $failed);
runTest('PO Approvals', 'GET', "{$baseUrl}/orders/approvals", [], $token, $passed, $failed);

$poNew = runTest('PO Create', 'POST', "{$baseUrl}/orders", [
    'vendor_id' => $vendorId,
    'date' => date('Y-m-d'),
    'delivery_date' => date('Y-m-d', strtotime('+5 days')),
    'status' => 'Draft',
    'reference' => 'PO-REF-' . rand(100, 999),
    'discount_type' => 'fixed',
    'tax_type' => 'exclusive',
    'gst_type' => 'cgst_sgst',
    'items' => [
        [
            'product_id' => $prodId,
            'quantity' => 20,
            'unit_price' => 140.00,
            'tax_rate' => 18,
            'discount' => 0
        ]
    ]
], $token, $passed, $failed);

$poId = $poNew['data']['id'] ?? null;
if ($poId) {
    runTest('PO Show', 'GET', "{$baseUrl}/orders/{$poId}", [], $token, $passed, $failed);
    runTest('PO Remind', 'POST', "{$baseUrl}/orders/{$poId}/remind", ['note' => 'Please approve'], $token, $passed, $failed);
    runTest('PO Approve', 'POST', "{$baseUrl}/orders/{$poId}/approve", [], $token, $passed, $failed);
    runTest('PO Savings Details', 'GET', "{$baseUrl}/rfqs/savings-details/{$poId}", [], $token, $passed, $failed);
}

// 5. GOODS RECEIPT NOTES (GRN)
runTest('GRN List', 'GET', "{$baseUrl}/grns", [], $token, $passed, $failed);
runTest('GRN Export', 'GET', "{$baseUrl}/grns/export", [], $token, $passed, $failed);
runTest('GRN Pending List', 'GET', "{$baseUrl}/grns/pending", [], $token, $passed, $failed);

if ($poId) {
    runTest('GRN Get PO Items', 'GET', "{$baseUrl}/grns/get-po-items/{$poId}", [], $token, $passed, $failed);

    $grnNew = runTest('PO Convert to GRN', 'POST', "{$baseUrl}/orders/{$poId}/convert-to-grn", [
        'receipt_date' => date('Y-m-d'),
        'warehouse_id' => $whId,
        'challan_number' => 'DC-' . rand(1000, 9999),
    ], $token, $passed, $failed);

    $grnId = $grnNew['data']['id'] ?? null;
    if ($grnId) {
        runTest('GRN Show', 'GET', "{$baseUrl}/grns/{$grnId}", [], $token, $passed, $failed);
        runTest('GRN Approve', 'POST', "{$baseUrl}/grns/{$grnId}/approve", [], $token, $passed, $failed);
    }
}

// 6. LANDED COSTS
runTest('Landed Cost List', 'GET', "{$baseUrl}/landed-costs", [], $token, $passed, $failed);
if (isset($grnId) && $grnId) {
    runTest('Landed Cost Get GRN Items', 'GET', "{$baseUrl}/landed-costs/get-grn-items?grn_ids[]={$grnId}", [], $token, $passed, $failed);

    $lcNew = runTest('Landed Cost Create', 'POST', "{$baseUrl}/landed-costs", [
        'voucher_date' => date('Y-m-d'),
        'grn_ids' => [$grnId],
        'expenses' => [
            [
                'cost_head' => 'Freight / Transportation',
                'amount' => 500.00,
                'allocation_basis' => 'by_qty'
            ]
        ]
    ], $token, $passed, $failed);

    $lcId = $lcNew['data']['id'] ?? null;
    if ($lcId) {
        runTest('Landed Cost Show', 'GET', "{$baseUrl}/landed-costs/{$lcId}", [], $token, $passed, $failed);
        runTest('Landed Cost Post', 'POST', "{$baseUrl}/landed-costs/{$lcId}/post", [], $token, $passed, $failed);
    }
}

// 7. VENDOR BILLS
runTest('Vendor Bill Meta', 'GET', "{$baseUrl}/bills/meta", [], $token, $passed, $failed);
runTest('Vendor Bill List', 'GET', "{$baseUrl}/bills", [], $token, $passed, $failed);
runTest('Vendor Bill Export', 'GET', "{$baseUrl}/bills/export", [], $token, $passed, $failed);
runTest('Vendor Bill Pending GRNs', 'GET', "{$baseUrl}/bills/pending", [], $token, $passed, $failed);
runTest('Vendor Bill Pending Freight', 'GET', "{$baseUrl}/bills/pending-freight", [], $token, $passed, $failed);

$srvBill = runTest('Vendor Bill Store Service', 'POST', "{$baseUrl}/bills/store-service", [
    'vendor_id' => $vendorId,
    'bill_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'vendor_invoice_no' => 'INV-SRV-' . rand(100, 999),
    'items' => [
        ['service_description' => 'Warehouse Security & Maintenance', 'amount' => 1200.00, 'tax_rate' => 18]
    ]
], $token, $passed, $failed);

$billId = $srvBill['data']['id'] ?? null;
if ($billId) {
    runTest('Vendor Bill Show', 'GET', "{$baseUrl}/bills/{$billId}", [], $token, $passed, $failed);
}

// 8. PURCHASE ADVANCES
runTest('Advance Payments List', 'GET', "{$baseUrl}/advances", [], $token, $passed, $failed);
$advNew = runTest('Advance Payment Store', 'POST', "{$baseUrl}/advances", [
    'vendor_id' => $vendorId,
    'purchase_order_id' => $poId ?? null,
    'payment_date' => date('Y-m-d'),
    'amount' => 300.00,
    'payment_method' => 'Bank Transfer',
    'reference_number' => 'REF-ADV-' . rand(1000, 9999),
], $token, $passed, $failed);
$advId = $advNew['data']['id'] ?? null;
if ($advId) {
    runTest('Advance Payment Show', 'GET', "{$baseUrl}/advances/{$advId}", [], $token, $passed, $failed);
    if ($billId) {
        runTest('Apply Advance to Bill', 'POST', "{$baseUrl}/bills/{$billId}/apply-advance", [
            'advance_payment_id' => $advId,
            'amount' => 300.00
        ], $token, $passed, $failed);
    }
}

// 9. VENDOR PAYMENTS
runTest('Vendor Payment Meta', 'GET', "{$baseUrl}/payments/meta", [], $token, $passed, $failed);
runTest('Vendor Payment List', 'GET', "{$baseUrl}/payments", [], $token, $passed, $failed);
runTest('Vendor Payment Export', 'GET', "{$baseUrl}/payments/export", [], $token, $passed, $failed);

if ($billId) {
    $payNew = runTest('Vendor Payment Store', 'POST', "{$baseUrl}/payments", [
        'vendor_id' => $vendorId,
        'payment_date' => date('Y-m-d'),
        'amount' => 500.00,
        'payment_method' => 'Bank Transfer',
        'reference_number' => 'TXN-' . rand(10000, 99999),
        'allocations' => [
            ['vendor_bill_id' => $billId, 'amount' => 500.00]
        ]
    ], $token, $passed, $failed);

    $payId = $payNew['data']['id'] ?? null;
    if ($payId) {
        runTest('Vendor Payment Show', 'GET', "{$baseUrl}/payments/{$payId}", [], $token, $passed, $failed);
    }
}

// 10. PURCHASE RETURNS / DEBIT NOTES
runTest('Purchase Return Meta', 'GET', "{$baseUrl}/returns/meta", [], $token, $passed, $failed);
runTest('Purchase Return List', 'GET', "{$baseUrl}/returns", [], $token, $passed, $failed);
runTest('Purchase Return Export', 'GET', "{$baseUrl}/returns/export", [], $token, $passed, $failed);


$retNew = runTest('Purchase Return Store', 'POST', "{$baseUrl}/returns", [
    'vendor_id' => $vendorId,
    'purchase_order_id' => $poId ?? null,
    'vendor_bill_id' => $billId ?? null,
    'return_date' => date('Y-m-d'),
    'reason' => 'Defective batch replacement',
    'items' => [
        [
            'product_id' => $prodId,
            'warehouse_id' => $whId,
            'quantity' => 2,
            'unit_price' => 140.00
        ]
    ]
], $token, $passed, $failed);

$retId = $retNew['data']['id'] ?? null;
if ($retId) {
    runTest('Purchase Return Show', 'GET', "{$baseUrl}/returns/{$retId}", [], $token, $passed, $failed);
    runTest('Purchase Return Approve', 'POST', "{$baseUrl}/returns/{$retId}/approve", [], $token, $passed, $failed);
}

echo "\n=================================================================\n";
echo "FINAL RESULTS: Passed: {$passed} | Failed: {$failed} | Total: " . ($passed + $failed) . "\n";
echo "=================================================================\n";
