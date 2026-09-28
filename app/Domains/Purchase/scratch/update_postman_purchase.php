<?php

$masterFile = __DIR__ . '/../../../../ERP_Master_REST_API_Postman_Collection.json';
$artifactFile = 'C:/Users/HP5CD/.gemini/antigravity-ide/brain/cef28d39-0827-40c5-8ef8-7ecf580cc887/ERP_Master_REST_API_Postman_Collection.json';

$json = json_decode(file_get_contents($masterFile), true);

function makeReq($name, $method, $url, $body = null, $desc = '') {
    $rawUrl = '{{base_url}}' . $url;
    $parts = explode('/', trim($url, '/'));
    
    $req = [
        'name' => $name,
        'request' => [
            'method' => $method,
            'header' => [
                ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
                ['key' => 'Authorization', 'value' => 'Bearer {{auth_token}}', 'type' => 'text'],
            ],
            'url' => [
                'raw' => $rawUrl,
                'host' => ['{{base_url}}'],
                'path' => $parts,
            ],
            'description' => $desc,
        ],
        'response' => [],
    ];

    if ($body !== null) {
        $req['request']['header'][] = ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'];
        $req['request']['body'] = [
            'mode' => 'raw',
            'raw' => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'options' => [
                'raw' => ['language' => 'json']
            ]
        ];
    }

    return $req;
}

$purchaseFolder = [
    'name' => 'Purchase Module',
    'item' => [
        // 1. Vendors
        [
            'name' => '1. Vendors / Suppliers',
            'item' => [
                makeReq('Vendor Meta', 'GET', '/api/purchase/vendors/meta', null, 'Get vendor creation metadata including payment terms and status options'),
                makeReq('Get Vendors List', 'GET', '/api/purchase/vendors', null, 'List vendors with pagination, search, status filter'),
                makeReq('Create Vendor', 'POST', '/api/purchase/vendors', [
                    'name' => 'Apex Industrial Supplies Ltd',
                    'company_name' => 'Apex Enterprises Inc',
                    'email' => 'sales@apexsupplies.com',
                    'phone' => '+91-9876543210',
                    'gstin' => '27AABCU9603R1ZM',
                    'pan' => 'AABCU9603R',
                    'status' => 'active',
                    'billing_address' => 'Plot 45, Phase 2, GIDC Estate',
                    'shipping_address' => 'Plot 45, Phase 2, GIDC Estate',
                    'opening_balance' => 0.00,
                    'payment_terms' => 'Net 30',
                ], 'Create a new vendor with full tax & financial details'),
                makeReq('Quick Create Vendor', 'POST', '/api/purchase/vendors/quick-create', [
                    'name' => 'Quick Supplier Ltd',
                    'phone' => '+91-9988776655',
                ], 'Fast minimal vendor creation modal helper'),
                makeReq('Get Vendor Details', 'GET', '/api/purchase/vendors/{{vendor_id}}', null, 'Retrieve vendor by ID with PO/Bill activity and financial stats'),
                makeReq('Update Vendor', 'PUT', '/api/purchase/vendors/{{vendor_id}}', [
                    'name' => 'Apex Industrial Supplies Pvt Ltd',
                    'payment_terms' => 'Net 45',
                ], 'Update existing vendor'),
                makeReq('Delete Vendor', 'DELETE', '/api/purchase/vendors/{{vendor_id}}', null, 'Delete vendor if no active purchase history'),
                makeReq('Toggle Vendor Status', 'POST', '/api/purchase/vendors/{{vendor_id}}/toggle-status', null, 'Toggle active/inactive vendor status'),
                makeReq('Export Vendors', 'GET', '/api/purchase/vendors/export', null, 'Download Excel spreadsheet of vendors'),
            ]
        ],
        // 2. PR
        [
            'name' => '2. Purchase Requisitions (PR)',
            'item' => [
                makeReq('Requisition Meta', 'GET', '/api/purchase/requisitions/meta', null, 'Get PR meta, source types, products and warehouses'),
                makeReq('Get Requisitions List', 'GET', '/api/purchase/requisitions', null, 'List PRs with search, status, and date filtering'),
                makeReq('Create Purchase Requisition', 'POST', '/api/purchase/requisitions', [
                    'requisition_date' => '2026-09-26',
                    'expected_date' => '2026-10-05',
                    'source_type' => 'direct',
                    'notes' => 'Monthly raw material replenishment',
                    'items' => [
                        ['product_id' => 1, 'warehouse_id' => 1, 'quantity' => 100, 'estimated_cost' => 150.00]
                    ]
                ], 'Create a new purchase requisition with line items'),
                makeReq('Get Requisition Details', 'GET', '/api/purchase/requisitions/{{requisition_id}}', null, 'View single PR with line items, warehouse, reminders'),
                makeReq('Update Purchase Requisition', 'PUT', '/api/purchase/requisitions/{{requisition_id}}', [
                    'requisition_date' => '2026-09-26',
                    'source_type' => 'direct',
                    'notes' => 'Updated requirement',
                    'items' => [
                        ['product_id' => 1, 'warehouse_id' => 1, 'quantity' => 120, 'estimated_cost' => 145.00]
                    ]
                ], 'Update draft PR details and line items'),
                makeReq('Delete Purchase Requisition', 'DELETE', '/api/purchase/requisitions/{{requisition_id}}', null, 'Delete draft PR'),
                makeReq('Approve Requisition', 'POST', '/api/purchase/requisitions/{{requisition_id}}/approve', null, 'Approve PR for RFQ/PO conversion'),
                makeReq('Reject Requisition', 'POST', '/api/purchase/requisitions/{{requisition_id}}/reject', [
                    'rejection_reason' => 'Budget limit exceeded for this quarter'
                ], 'Reject PR with explanation'),
                makeReq('Remind Requisition Approver', 'POST', '/api/purchase/requisitions/{{requisition_id}}/remind', [
                    'note' => 'Please approve urgently to avoid production downtime'
                ], 'Send and log an approval reminder'),
                makeReq('Get Pending Requisition Items', 'GET', '/api/purchase/requisitions/pending-items', null, 'Get unfulfilled PR items grouped for PO conversion'),
                makeReq('Create POs from Pending Items', 'POST', '/api/purchase/requisitions/create-pos-from-pending', [
                    'selected_items' => [
                        [
                            'item_id' => 1,
                            'product_id' => 1,
                            'vendor_id' => 1,
                            'warehouse_id' => 1,
                            'quantity' => 50,
                            'rate' => 145.00,
                            'tax_percent' => 18,
                        ]
                    ]
                ], 'Bulk generate POs directly from selected pending PR items'),
                makeReq('Export Requisitions', 'GET', '/api/purchase/requisitions/export', null, 'Download Excel spreadsheet of PRs'),
            ]
        ],
        // 3. RFQs
        [
            'name' => '3. Request for Quotations (RFQ)',
            'item' => [
                makeReq('RFQ Meta', 'GET', '/api/purchase/rfqs/meta', null, 'Get RFQ metadata, vendors, requisitions, products'),
                makeReq('Get RFQ List', 'GET', '/api/purchase/rfqs', null, 'List RFQs with statuses (Draft, Sent, Received, Confirmed)'),
                makeReq('Create RFQ', 'POST', '/api/purchase/rfqs', [
                    'rfq_date' => '2026-09-26',
                    'purchase_requisition_id' => 1,
                    'notes' => 'Competitive bidding for annual supply',
                    'items' => [
                        [
                            'product_id' => 1,
                            'quantity' => 100,
                            'estimated_cost' => 150.00,
                            'vendor_ids' => [1, 2]
                        ]
                    ]
                ], 'Create RFQ and link targeted vendors to items'),
                makeReq('Get RFQ Details', 'GET', '/api/purchase/rfqs/{{rfq_id}}', null, 'View RFQ with vendor quotes matrix and comparison'),
                makeReq('Update RFQ', 'PUT', '/api/purchase/rfqs/{{rfq_id}}', [
                    'rfq_date' => '2026-09-26',
                    'notes' => 'Updated RFQ notes',
                ], 'Update draft RFQ parameters'),
                makeReq('Delete RFQ', 'DELETE', '/api/purchase/rfqs/{{rfq_id}}', null, 'Delete draft RFQ'),
                makeReq('Store Vendor Quotations', 'POST', '/api/purchase/rfqs/{{rfq_id}}/store-quotes', [
                    'quotes' => [
                        '1' => [
                            'quotation_number' => 'QTN-SUPPLIER-A-101',
                            'rates' => [
                                '1' => ['rate' => 138.00]
                            ]
                        ]
                    ]
                ], 'Record vendor quotation rates received for RFQ items'),
                makeReq('Confirm RFQ (Award Quotes)', 'POST', '/api/purchase/rfqs/{{rfq_id}}/confirm', null, 'Confirm RFQ, lock quotes, calculate potential cost savings'),
                makeReq('Create PO from RFQ', 'POST', '/api/purchase/rfqs/{{rfq_id}}/create-po', [
                    'vendor_id' => 1,
                    'date' => '2026-09-26',
                    'delivery_date' => '2026-10-05',
                ], 'Generate Purchase Order from won quotation in RFQ'),
                makeReq('RFQ Savings Dashboard', 'GET', '/api/purchase/rfqs/savings-dashboard', null, 'Procurement analytics: estimated vs negotiated actual savings metrics'),
                makeReq('PO Savings Details', 'GET', '/api/purchase/rfqs/savings-details/{{po_id}}', null, 'View negotiated savings breakdown on specific PO generated from RFQ'),
                makeReq('Get Requisition Items for RFQ', 'GET', '/api/purchase/rfqs/get-requisition-items?requisition_id={{requisition_id}}', null, 'Helper to fetch PR line items into RFQ form'),
                makeReq('Export RFQs', 'GET', '/api/purchase/rfqs/export', null, 'Download Excel spreadsheet of RFQs'),
            ]
        ],
        // 4. PO
        [
            'name' => '4. Purchase Orders (PO)',
            'item' => [
                makeReq('PO Meta', 'GET', '/api/purchase/orders/meta', null, 'Get metadata, active vendors, products, warehouses, tax terms'),
                makeReq('Get PO List', 'GET', '/api/purchase/orders', null, 'List Purchase Orders with filters, status, vendors'),
                makeReq('Create Purchase Order', 'POST', '/api/purchase/orders', [
                    'vendor_id' => 1,
                    'date' => '2026-09-26',
                    'delivery_date' => '2026-10-05',
                    'status' => 'Draft',
                    'reference' => 'PO-REF-2026-001',
                    'discount_type' => 'fixed',
                    'tax_type' => 'exclusive',
                    'gst_type' => 'cgst_sgst',
                    'items' => [
                        [
                            'product_id' => 1,
                            'warehouse_id' => 1,
                            'quantity' => 50,
                            'unit_price' => 140.00,
                            'tax_rate' => 18,
                            'discount' => 0
                        ]
                    ]
                ], 'Create a new purchase order with multi-line items and tax calculations'),
                makeReq('Get PO Details', 'GET', '/api/purchase/orders/{{order_id}}', null, 'View complete PO with GRNs, bills, advance payments linked'),
                makeReq('Update Purchase Order', 'PUT', '/api/purchase/orders/{{order_id}}', [
                    'date' => '2026-09-26',
                    'notes' => 'Urgent priority dispatch',
                ], 'Update draft PO'),
                makeReq('Update PO Status', 'PATCH', '/api/purchase/orders/{{order_id}}/status', [
                    'status' => 'Approved'
                ], 'Update PO status (Draft, Approved, Completed, Cancelled)'),
                makeReq('Delete Purchase Order', 'DELETE', '/api/purchase/orders/{{order_id}}', null, 'Delete draft PO'),
                makeReq('Approve PO', 'POST', '/api/purchase/orders/{{order_id}}/approve', null, 'Approve PO for goods receipt'),
                makeReq('Reject PO', 'POST', '/api/purchase/orders/{{order_id}}/reject', [
                    'rejection_reason' => 'Incorrect delivery timeframe'
                ], 'Reject PO with reason'),
                makeReq('Remind PO Approver', 'POST', '/api/purchase/orders/{{order_id}}/remind', [
                    'note' => 'Please review and approve PO'
                ], 'Send and log reminder for PO approval'),
                makeReq('Get PO Approvals List', 'GET', '/api/purchase/orders/approvals', null, 'List pending approval PO requests for manager'),
                makeReq('Convert PO to GRN', 'POST', '/api/purchase/orders/{{order_id}}/convert-to-grn', [
                    'received_date' => '2026-09-26',
                    'challan_number' => 'DC-99881',
                    'challan_date' => '2026-09-26',
                ], 'Convert approved PO into Goods Receipt Note (GRN)'),
                makeReq('Get PR Items for PO', 'GET', '/api/purchase/orders/get-requisition-items/{{requisition_id}}', null, 'Fetch PR items to pre-fill PO create form'),
                makeReq('Export Purchase Orders', 'GET', '/api/purchase/orders/export', null, 'Download Excel spreadsheet of POs'),
            ]
        ],
        // 5. GRN
        [
            'name' => '5. Goods Receipt Notes (GRN)',
            'item' => [
                makeReq('Get GRN List', 'GET', '/api/purchase/grns', null, 'List GRNs with warehouse, vendor, PO linkages'),
                makeReq('Create Direct GRN', 'POST', '/api/purchase/grns', [
                    'vendor_id' => 1,
                    'warehouse_id' => 1,
                    'received_date' => '2026-09-26',
                    'challan_number' => 'DC-12345',
                    'challan_date' => '2026-09-26',
                    'status' => 'Received',
                    'items' => [
                        [
                            'product_id' => 1,
                            'received_qty' => 50,
                            'accepted_qty' => 50,
                            'rejected_qty' => 0,
                            'unit_price' => 140.00
                        ]
                    ]
                ], 'Create Goods Receipt Note directly'),
                makeReq('Get GRN Details', 'GET', '/api/purchase/grns/{{grn_id}}', null, 'View GRN with product line items, accepted/rejected counts'),
                makeReq('Update GRN', 'PUT', '/api/purchase/grns/{{grn_id}}', [
                    'notes' => 'Inspected and verified batch quality'
                ], 'Update draft GRN information'),
                makeReq('Delete GRN', 'DELETE', '/api/purchase/grns/{{grn_id}}', null, 'Delete draft GRN'),
                makeReq('Approve GRN (Stock Inflow)', 'POST', '/api/purchase/grns/{{grn_id}}/approve', null, 'Approve GRN: triggers real-time StockService inventory inflow into warehouse ledger'),
                makeReq('Get Pending GRNs', 'GET', '/api/purchase/grns/pending', null, 'List approved GRNs pending vendor invoice generation'),
                makeReq('Get PO Items for GRN', 'GET', '/api/purchase/grns/get-po-items/{{order_id}}', null, 'Helper to fetch PO items into GRN receipt modal'),
                makeReq('Export GRNs', 'GET', '/api/purchase/grns/export', null, 'Download Excel spreadsheet of GRNs'),
            ]
        ],
        // 6. Landed Costs
        [
            'name' => '6. Landed Cost Vouchers',
            'item' => [
                makeReq('Get Landed Cost List', 'GET', '/api/purchase/landed-costs', null, 'List Landed Cost Vouchers with status (Draft, Posted)'),
                makeReq('Create Landed Cost Voucher', 'POST', '/api/purchase/landed-costs', [
                    'voucher_date' => '2026-09-26',
                    'grn_ids' => [1],
                    'expenses' => [
                        [
                            'cost_head' => 'Freight / Transportation',
                            'amount' => 500.00,
                            'allocation_basis' => 'by_qty'
                        ],
                        [
                            'cost_head' => 'Customs & Port Duty',
                            'amount' => 300.00,
                            'allocation_basis' => 'by_amount'
                        ]
                    ]
                ], 'Create Landed Cost voucher allocating multi-head expenses across GRN items'),
                makeReq('Get Landed Cost Details', 'GET', '/api/purchase/landed-costs/{{landed_cost_id}}', null, 'View Landed Cost voucher with revaluation details'),
                makeReq('Post Landed Cost Voucher', 'POST', '/api/purchase/landed-costs/{{landed_cost_id}}/post', null, 'Post Landed Cost: revalues inventory stock unit costs & posts accounting entries'),
                makeReq('Delete Landed Cost Voucher', 'DELETE', '/api/purchase/landed-costs/{{landed_cost_id}}', null, 'Delete draft Landed Cost voucher'),
                makeReq('Get GRN Items for Allocation', 'GET', '/api/purchase/landed-costs/get-grn-items?grn_ids[]=1', null, 'Preview all items from selected GRNs to allocate landed costs against'),
            ]
        ],
        // 7. Vendor Bills
        [
            'name' => '7. Vendor Bills & Invoices',
            'item' => [
                makeReq('Vendor Bill Meta', 'GET', '/api/purchase/bills/meta', null, 'Get metadata, approved GRNs, POs, active vendors'),
                makeReq('Get Vendor Bills List', 'GET', '/api/purchase/bills', null, 'List vendor bills with due dates, payment status (Draft, Posted, Paid, Partial)'),
                makeReq('Create Vendor Bill', 'POST', '/api/purchase/bills', [
                    'vendor_id' => 1,
                    'purchase_order_id' => 1,
                    'goods_receipt_note_id' => 1,
                    'vendor_invoice_number' => 'INV-SUP-2026-88',
                    'bill_date' => '2026-09-26',
                    'due_date' => '2026-10-26',
                    'items' => [
                        [
                            'product_id' => 1,
                            'quantity' => 50,
                            'unit_price' => 140.00,
                            'tax_rate' => 18
                        ]
                    ]
                ], 'Create Vendor Bill against GRN / PO'),
                makeReq('Store Standalone Service Bill', 'POST', '/api/purchase/bills/store-service', [
                    'vendor_id' => 1,
                    'vendor_invoice_number' => 'INV-SRV-991',
                    'bill_date' => '2026-09-26',
                    'due_date' => '2026-10-26',
                    'items' => [
                        [
                            'service_description' => 'Machinery Calibration & AMC Service',
                            'amount' => 2500.00,
                            'tax_rate' => 18
                        ]
                    ]
                ], 'Record vendor bill for non-inventory direct services'),
                makeReq('Get Vendor Bill Details', 'GET', '/api/purchase/bills/{{bill_id}}', null, 'View bill with payment allocations, balance due, linked PO/GRN'),
                makeReq('Update Vendor Bill Status', 'PATCH', '/api/purchase/bills/{{bill_id}}/status', [
                    'status' => 'Posted'
                ], 'Update bill status (Draft, Posted, Paid, Cancelled)'),
                makeReq('Delete Vendor Bill', 'DELETE', '/api/purchase/bills/{{bill_id}}', null, 'Delete draft vendor bill'),
                makeReq('Apply Advance to Bill', 'POST', '/api/purchase/bills/{{bill_id}}/apply-advance', [
                    'advance_payment_id' => 1,
                    'amount' => 500.00
                ], 'Apply unallocated PO advance payment against vendor bill'),
                makeReq('Get Pending GRNs for Billing', 'GET', '/api/purchase/bills/pending', null, 'List approved GRNs waiting for invoice generation'),
                makeReq('Get Pending Freight for Billing', 'GET', '/api/purchase/bills/pending-freight', null, 'List freight charges from POs awaiting freight billing'),
                makeReq('Export Vendor Bills', 'GET', '/api/purchase/bills/export', null, 'Download Excel spreadsheet of vendor bills'),
            ]
        ],
        // 8. Advance Payments
        [
            'name' => '8. Purchase Advance Payments',
            'item' => [
                makeReq('Get Advance Payments List', 'GET', '/api/purchase/advances', null, 'List advance payments disbursed to vendors on POs'),
                makeReq('Record Advance Payment', 'POST', '/api/purchase/advances', [
                    'vendor_id' => 1,
                    'purchase_order_id' => 1,
                    'payment_date' => '2026-09-26',
                    'amount' => 1000.00,
                    'payment_method' => 'Bank Transfer',
                    'reference_number' => 'NEFT-ADV-2026-01',
                    'notes' => '30% advance on PO confirmation'
                ], 'Record advance payment to vendor against PO'),
                makeReq('Get Advance Payment Details', 'GET', '/api/purchase/advances/{{advance_id}}', null, 'View advance payment record'),
            ]
        ],
        // 9. Vendor Payments
        [
            'name' => '9. Vendor Payments & Disbursements',
            'item' => [
                makeReq('Vendor Payment Meta', 'GET', '/api/purchase/payments/meta', null, 'Get payment methods, active vendors, outstanding bills'),
                makeReq('Get Vendor Payments List', 'GET', '/api/purchase/payments', null, 'List payments made to vendors with bill allocations'),
                makeReq('Record Vendor Payment', 'POST', '/api/purchase/payments', [
                    'vendor_id' => 1,
                    'payment_date' => '2026-09-26',
                    'amount' => 3000.00,
                    'payment_method' => 'Bank Transfer',
                    'reference_number' => 'UTR-987654321',
                    'allocations' => [
                        ['vendor_bill_id' => 1, 'amount' => 3000.00]
                    ]
                ], 'Record vendor payment and allocate to one or multiple vendor bills'),
                makeReq('Get Payment Details', 'GET', '/api/purchase/payments/{{payment_id}}', null, 'View payment voucher and bill allocation breakdown'),
                makeReq('Export Vendor Payments', 'GET', '/api/purchase/payments/export', null, 'Download Excel spreadsheet of payments'),
            ]
        ],
        // 10. Returns
        [
            'name' => '10. Purchase Returns & Debit Notes',
            'item' => [
                makeReq('Purchase Return Meta', 'GET', '/api/purchase/returns/meta', null, 'Get return meta, active vendors, warehouses, products'),
                makeReq('Get Purchase Returns List', 'GET', '/api/purchase/returns', null, 'List purchase returns and debit notes'),
                makeReq('Create Purchase Return', 'POST', '/api/purchase/returns', [
                    'vendor_id' => 1,
                    'purchase_order_id' => 1,
                    'vendor_bill_id' => 1,
                    'return_date' => '2026-09-26',
                    'reason' => 'Defective batch replacement',
                    'items' => [
                        [
                            'product_id' => 1,
                            'warehouse_id' => 1,
                            'quantity' => 2,
                            'unit_price' => 140.00
                        ]
                    ]
                ], 'Create draft purchase return against PO/Bill'),
                makeReq('Get Purchase Return Details', 'GET', '/api/purchase/returns/{{return_id}}', null, 'View purchase return details and item refund totals'),
                makeReq('Approve Purchase Return (Stock Outflow)', 'POST', '/api/purchase/returns/{{return_id}}/approve', null, 'Approve purchase return: records inventory stock outflow from warehouse and reduces vendor bill due balance'),
                makeReq('Export Purchase Returns', 'GET', '/api/purchase/returns/export', null, 'Download Excel spreadsheet of purchase returns'),
            ]
        ]
    ]
];

// Replace or insert Purchase Module folder in Postman collection
$existingIdx = -1;
foreach ($json['item'] as $idx => $folder) {
    if (isset($folder['name']) && stripos($folder['name'], 'Purchase') !== false) {
        $existingIdx = $idx;
        break;
    }
}

if ($existingIdx >= 0) {
    $json['item'][$existingIdx] = $purchaseFolder;
    echo "Replaced existing Purchase folder at index {$existingIdx}\n";
} else {
    // Insert after Inventory or CRM
    $json['item'][] = $purchaseFolder;
    echo "Appended Purchase folder\n";
}

$updatedJson = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents($masterFile, $updatedJson);
file_put_contents($artifactFile, $updatedJson);

echo "Successfully updated Postman Collection in both Master and Artifact locations!\n";
