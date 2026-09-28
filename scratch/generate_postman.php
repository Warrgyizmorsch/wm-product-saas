<?php

function buildPostmanCollection(): array
{
    $collection = [
        'info' => [
            '_postman_id' => 'erp-master-api-kit-' . bin2hex(random_bytes(4)),
            'name'        => 'ERP Master REST API Kit (CRM, Sales, Purchase, Inventory)',
            'description' => 'Complete, enterprise-grade Postman collection for all ERP Modules: CRM Leads & Deals, Sales, Purchase, and Inventory with ready-to-run JSON payloads and query filters.',
            'schema'      => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        ],
        'variable' => [
            ['key' => 'baseUrl', 'value' => 'http://127.0.0.1:8000', 'type' => 'string'],
            ['key' => 'token', 'value' => 'YOUR_SANCTUM_BEARER_TOKEN_HERE', 'type' => 'string'],
        ],
        'item' => []
    ];

    $authHeaders = [
        ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
        ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'],
        ['key' => 'Authorization', 'value' => 'Bearer {{token}}', 'type' => 'text'],
    ];

    $publicHeaders = [
        ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
        ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'],
    ];

    $collection['item'][] = [
        'name' => '0. Authentication',
        'item' => [
            [
                'name' => 'Login (Get Token)',
                'request' => [
                    'method' => 'POST',
                    'header' => $publicHeaders,
                    'url'    => ['raw' => '{{baseUrl}}/api/auth/login', 'host' => ['{{baseUrl}}'], 'path' => ['api', 'auth', 'login']],
                    'body'   => [
                        'mode' => 'raw',
                        'raw'  => json_encode(['email' => 'admin@example.com', 'password' => 'password'], JSON_PRETTY_PRINT)
                    ]
                ]
            ],
            [
                'name' => 'Logout (Revoke Token)',
                'request' => [
                    'method' => 'POST',
                    'header' => $authHeaders,
                    'url'    => ['raw' => '{{baseUrl}}/api/auth/logout', 'host' => ['{{baseUrl}}'], 'path' => ['api', 'auth', 'logout']],
                ]
            ]
        ]
    ];

    $createReq = function(string $name, string $method, string $path, ?array $query = null, ?array $body = null) use ($authHeaders) {
        $urlParts = array_values(array_filter(explode('/', trim($path, '/'))));
        $urlObj = [
            'raw'  => '{{baseUrl}}/' . trim($path, '/') . ($query ? '?' . http_build_query($query) : ''),
            'host' => ['{{baseUrl}}'],
            'path' => $urlParts,
        ];
        if ($query) {
            $urlObj['query'] = [];
            foreach ($query as $k => $v) {
                $urlObj['query'][] = ['key' => $k, 'value' => (string)$v];
            }
        }

        $req = [
            'name'    => $name,
            'request' => [
                'method' => $method,
                'header' => $authHeaders,
                'url'    => $urlObj,
            ]
        ];

        if ($body !== null) {
            $req['request']['body'] = [
                'mode' => 'raw',
                'raw'  => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            ];
        }

        return $req;
    };

    // 1. CRM - LEADS
    $collection['item'][] = [
        'name' => '1. CRM - Leads',
        'item' => [
            $createReq('1. Lead Meta Dropdowns', 'GET', 'api/crm/leads/meta'),
            $createReq('2. List Leads (With Filters)', 'GET', 'api/crm/leads', [
                'search' => 'Vikram',
                'priority' => 'high',
                'status_id' => 1,
                'per_page' => 15
            ]),
            $createReq('3. Check Duplicate Lead', 'GET', 'api/crm/leads/check-duplicate', [
                'email' => 'vikram@example.com',
                'phone' => '+919876543210'
            ]),
            $createReq('4. Create Lead', 'POST', 'api/crm/leads', null, [
                'contact_person'  => 'Vikram Singhania',
                'company_name'    => 'Singhania Industrial Corp',
                'email'           => 'vikram@singhania.com',
                'phone'           => '+919876543210',
                'status_id'       => 1,
                'priority'        => 'high',
                'source'          => 'Website',
                'expected_amount' => 250000.00,
                'lead_owner_id'   => 1,
                'city'            => 'Mumbai',
                'state'           => 'Maharashtra',
                'country'         => 'India',
                'notes'           => 'Interested in bulk purchasing'
            ]),
            $createReq('5. View Lead Detail', 'GET', 'api/crm/leads/1'),
            $createReq('6. Update Lead', 'PUT', 'api/crm/leads/1', null, [
                'contact_person'  => 'Vikram Singhania Updated',
                'company_name'    => 'Singhania Industrial Corp Ltd',
                'priority'        => 'urgent',
                'expected_amount' => 350000.00
            ]),
            $createReq('7. Inline Status Update', 'PATCH', 'api/crm/leads/1/status', null, [
                'status_id' => 2
            ]),
            $createReq('8. Inline Priority Update', 'PATCH', 'api/crm/leads/1/priority', null, [
                'priority' => 'urgent'
            ]),
            $createReq('9. 1-Click Qualify Lead (Convert to Deal & Customer)', 'POST', 'api/crm/leads/1/qualify', null, [
                'deal_title' => 'Singhania Enterprise Supply Deal',
                'deal_amount' => 350000.00,
                'deal_status_id' => 1,
                'expected_close_date' => '2026-10-31',
                'notes' => 'Qualified after initial budget discovery call'
            ]),
            $createReq('10. Schedule Follow-up Activity / Call', 'POST', 'api/crm/leads/1/followups', null, [
                'type' => 'meeting',
                'title' => 'Product Demo & Proposal Discussion',
                'scheduled_at' => '2026-09-30 15:00:00',
                'notes' => 'Online Google Meet demo with client leadership team'
            ]),
            $createReq('11. Delete Lead (Soft Delete)', 'DELETE', 'api/crm/leads/1'),
            $createReq('12. Restore Deleted Lead', 'POST', 'api/crm/leads/1/restore'),
        ]
    ];

    // 2. CRM - LEAD STATUS MASTER
    $collection['item'][] = [
        'name' => '2. CRM - Lead Status Master',
        'item' => [
            $createReq('1. List Status Masters', 'GET', 'api/crm/lead-statuses'),
            $createReq('2. Create Custom Status', 'POST', 'api/crm/lead-statuses', null, [
                'name' => 'Negotiation Stage',
                'color' => 'bg-warning',
                'sort_order' => 3
            ]),
            $createReq('3. Update Status Master', 'PUT', 'api/crm/lead-statuses/5', null, [
                'name' => 'Contract Finalization',
                'color' => 'bg-purple'
            ]),
            $createReq('4. Reorder Statuses (Drag & Drop)', 'POST', 'api/crm/lead-statuses/reorder', null, [
                'order' => [
                    ['id' => 1, 'sort_order' => 1],
                    ['id' => 2, 'sort_order' => 2],
                    ['id' => 3, 'sort_order' => 3]
                ]
            ]),
            $createReq('5. Delete Status Master', 'DELETE', 'api/crm/lead-statuses/5')
        ]
    ];

    // 3. CRM - DEALS
    $collection['item'][] = [
        'name' => '3. CRM - Deals',
        'item' => [
            $createReq('1. Deal Meta Dropdowns', 'GET', 'api/crm/deals/meta'),
            $createReq('2. List Deals (With Stage Metrics)', 'GET', 'api/crm/deals', [
                'search' => 'Enterprise',
                'stage' => 'Qualification',
                'per_page' => 15
            ]),
            $createReq('3. Create Deal', 'POST', 'api/crm/deals', null, [
                'title' => 'Enterprise Supply Agreement 2026',
                'stage' => 'Qualification',
                'crm_account_id' => 1,
                'crm_contact_id' => 1,
                'estimated_value' => 750000.00,
                'probability' => 70,
                'closing_date' => '2026-10-31',
                'owner_id' => 1,
                'lead_source' => 'Referral',
                'notes' => 'High-priority enterprise contract'
            ]),
            $createReq('4. View Deal Detail', 'GET', 'api/crm/deals/1'),
            $createReq('5. Update Deal', 'PUT', 'api/crm/deals/1', null, [
                'title' => 'Enterprise Supply Agreement 2026 (Revised)',
                'estimated_value' => 850000.00,
                'probability' => 80
            ]),
            $createReq('6. Move Deal Stage (Kanban Drag)', 'PATCH', 'api/crm/deals/1/move-stage', null, [
                'stage' => 'Negotiation',
                'probability' => 85,
                'notes' => 'Moved to Negotiation stage after proposal presentation'
            ]),
            $createReq('7. Mark Deal Won', 'POST', 'api/crm/deals/1/mark-won', null, [
                'close_reason' => 'Best Pricing & Superior Quality',
                'notes' => 'Contract signed successfully'
            ]),
            $createReq('8. Mark Deal Lost', 'POST', 'api/crm/deals/1/mark-lost', null, [
                'close_reason' => 'Competitor Won',
                'notes' => 'Lost to competitor on delivery timeline'
            ]),
            $createReq('9. Delete Deal', 'DELETE', 'api/crm/deals/1')
        ]
    ];

    // 4. SALES - CUSTOMERS
    $collection['item'][] = [
        'name' => '4. Sales - Customers',
        'item' => [
            $createReq('1. Customer Meta', 'GET', 'api/sales/customers/meta'),
            $createReq('2. List Customers', 'GET', 'api/sales/customers', ['search' => 'Apex', 'status' => 'active']),
            $createReq('3. Create Customer', 'POST', 'api/sales/customers', null, [
                'name' => 'Apex Retail Solutions Ltd',
                'company_name' => 'Apex Retail',
                'email' => 'procurement@apexretail.in',
                'phone' => '+919812345678',
                'gstin' => '27AAAAA0000A1Z5',
                'status' => 'active',
                'billing_address' => 'Plot 45, Phase 2, MIDC, Andheri East, Mumbai 400093',
                'shipping_address' => 'Warehouse 12, Bhiwandi Logistics Park, Thane 421302',
                'opening_balance' => 0.00
            ]),
            $createReq('4. View Customer Profile & Orders Stats', 'GET', 'api/sales/customers/1'),
            $createReq('5. Update Customer', 'PUT', 'api/sales/customers/1', null, [
                'name' => 'Apex Retail Solutions Private Limited',
                'phone' => '+919812345679'
            ]),
            $createReq('6. Delete Customer', 'DELETE', 'api/sales/customers/1')
        ]
    ];

    // 5. SALES - SALES ORDERS
    $collection['item'][] = [
        'name' => '5. Sales - Sales Orders',
        'item' => [
            $createReq('1. Sales Order Meta', 'GET', 'api/sales/orders/meta'),
            $createReq('2. List Sales Orders', 'GET', 'api/sales/orders', ['status' => 'Draft', 'per_page' => 15]),
            $createReq('3. Create Sales Order (With Multi-Line Items)', 'POST', 'api/sales/orders', null, [
                'customer_id' => 1,
                'order_date' => '2026-09-26',
                'shipment_date' => '2026-10-05',
                'status' => 'Draft',
                'sales_person_id' => 1,
                'payment_terms' => 'Net 30',
                'discount_type' => 'fixed',
                'tax_type' => 'exclusive',
                'gst_type' => 'cgst_sgst',
                'freight_amount' => 1500.00,
                'shipping_charges' => 500.00,
                'items' => [
                    [
                        'product_id' => 1,
                        'warehouse_id' => 1,
                        'item_name' => 'Industrial Hydraulic Valve 50mm',
                        'quantity' => 10,
                        'unit_price' => 4500.00,
                        'tax_rate' => 18.00,
                        'discount' => 500.00,
                        'description' => 'Heavy duty cast iron valve'
                    ]
                ]
            ]),
            $createReq('4. View Sales Order Detail', 'GET', 'api/sales/orders/1'),
            $createReq('5. Update Sales Order Status (Confirmed/Closed)', 'PATCH', 'api/sales/orders/1/status', null, [
                'status' => 'Confirmed',
                'notes' => 'Customer confirmed order via email'
            ]),
            $createReq('6. Convert SO to Tax Invoice (1-Click)', 'POST', 'api/sales/orders/1/convert-to-invoice', null, [
                'invoice_date' => '2026-09-26',
                'due_date' => '2026-10-26'
            ]),
            $createReq('7. Convert SO to Delivery Challan / Dispatch (1-Click)', 'POST', 'api/sales/orders/1/convert-to-dispatch', null, [
                'dispatch_date' => '2026-09-26',
                'transporter_name' => 'VRL Logistics',
                'tracking_number' => 'VRL-9988231'
            ]),
            $createReq('8. Delete Sales Order', 'DELETE', 'api/sales/orders/1'),
            $createReq('9. Restore Sales Order', 'POST', 'api/sales/orders/1/restore')
        ]
    ];

    // 6. SALES - INVOICES & PAYMENTS
    $collection['item'][] = [
        'name' => '6. Sales - Invoices & Payments',
        'item' => [
            $createReq('1. Invoice Meta', 'GET', 'api/sales/invoices/meta'),
            $createReq('2. List Invoices', 'GET', 'api/sales/invoices', ['unpaid_only' => 1]),
            $createReq('3. Create Tax Invoice Directly', 'POST', 'api/sales/invoices', null, [
                'customer_id' => 1,
                'invoice_date' => '2026-09-26',
                'due_date' => '2026-10-26',
                'payment_terms' => 'Net 30',
                'tax_type' => 'exclusive',
                'gst_type' => 'cgst_sgst',
                'items' => [
                    [
                        'product_id' => 1,
                        'item_name' => 'Precision Bearing Unit',
                        'quantity' => 20,
                        'unit_price' => 2500.00,
                        'tax_rate' => 18.00
                    ]
                ]
            ]),
            $createReq('4. View Invoice Detail', 'GET', 'api/sales/invoices/1'),
            $createReq('5. Record Customer Payment (With Auto-Allocation)', 'POST', 'api/sales/payments', null, [
                'customer_id' => 1,
                'payment_date' => '2026-09-26',
                'amount' => 59000.00,
                'payment_method' => 'Bank Transfer',
                'reference_no' => 'HDFC-NEFT-9847291',
                'notes' => 'Received full payment against Invoice 1',
                'allocations' => [
                    [
                        'invoice_id' => 1,
                        'amount' => 59000.00
                    ]
                ]
            ]),
            $createReq('6. List Payments', 'GET', 'api/sales/payments'),
            $createReq('7. List Dispatches / Challans', 'GET', 'api/sales/dispatches')
        ]
    ];

    // 7. PURCHASE - VENDORS & ORDERS
    $collection['item'][] = [
        'name' => '7. Purchase - Vendors & Orders',
        'item' => [
            $createReq('1. Vendor Meta', 'GET', 'api/purchase/vendors/meta'),
            $createReq('2. List Vendors', 'GET', 'api/purchase/vendors'),
            $createReq('3. Create Vendor', 'POST', 'api/purchase/vendors', null, [
                'name' => 'National Steel & Alloy Suppliers',
                'company_name' => 'National Steel Ltd',
                'email' => 'sales@nationalsteel.com',
                'phone' => '+919822334455',
                'gstin' => '27BBBBB1111B1Z2',
                'pan' => 'BBBBB1111B',
                'status' => 'active',
                'payment_terms' => 'Net 45',
                'billing_address' => 'Plot 101, Industrial Estate, Pune 411019'
            ]),
            $createReq('4. Purchase Order Meta', 'GET', 'api/purchase/orders/meta'),
            $createReq('5. List Purchase Orders', 'GET', 'api/purchase/orders'),
            $createReq('6. Create Purchase Order (With Items)', 'POST', 'api/purchase/orders', null, [
                'vendor_id' => 1,
                'date' => '2026-09-26',
                'delivery_date' => '2026-10-10',
                'status' => 'Draft',
                'reference' => 'RFQ-2026-089',
                'supplier_quotation_number' => 'SQN-5542',
                'items' => [
                    [
                        'product_id' => 1,
                        'warehouse_id' => 1,
                        'quantity' => 100,
                        'unit_price' => 3200.00,
                        'tax_rate' => 18.00,
                        'discount' => 0,
                        'description' => 'Grade 304 Stainless Steel Rods'
                    ]
                ]
            ]),
            $createReq('7. Approve Purchase Order', 'PATCH', 'api/purchase/orders/1/status', null, [
                'status' => 'Approved'
            ]),
            $createReq('8. Convert PO to Goods Receipt Note (GRN) (1-Click)', 'POST', 'api/purchase/orders/1/convert-to-grn', null, [
                'receipt_date' => '2026-09-26',
                'warehouse_id' => 1,
                'challan_number' => 'CH-8891'
            ]),
            $createReq('9. Convert PO to Vendor Bill (1-Click)', 'POST', 'api/purchase/orders/1/convert-to-bill', null, [
                'bill_date' => '2026-09-26',
                'due_date' => '2026-10-26'
            ]),
            $createReq('10. Record Vendor Payment', 'POST', 'api/purchase/payments', null, [
                'vendor_id' => 1,
                'payment_date' => '2026-09-26',
                'amount' => 377600.00,
                'payment_method' => 'Bank Transfer',
                'reference_number' => 'RTGS-2026-0926-88',
                'allocations' => [
                    [
                        'vendor_bill_id' => 1,
                        'amount' => 377600.00
                    ]
                ]
            ])
        ]
    ];

    // 8. INVENTORY - COMPLETE KIT
    $collection['item'][] = [
        'name' => '8. Inventory - Full Kit (Stock, Transfer, Batches, Serials, Ledger)',
        'item' => [
            $createReq('1. Export Product Catalog (With Variants & Stock)', 'GET', 'api/inventory/products/export'),
            $createReq('2. Create/Ingest Product (With Variants)', 'POST', 'api/inventory/products', null, [
                'name' => 'Heavy Duty Industrial Flange',
                'sku' => 'FLG-HD-50',
                'type' => 'finished_good',
                'item_type' => 'Goods',
                'variation_type' => 'Single',
                'selling_price' => 5200.00,
                'cost_price' => 3400.00,
                'gst_rate' => 18.00,
                'hsn_sac' => '7307',
                'status' => 'active'
            ]),
            $createReq('3. List Warehouses', 'GET', 'api/inventory/warehouses'),
            $createReq('4. Create Warehouse', 'POST', 'api/inventory/warehouses', null, [
                'name' => 'Central Finished Goods Warehouse',
                'code' => 'WH-CENTRAL-01',
                'city' => 'Navi Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400705',
                'contact_person' => 'Rajesh Sharma',
                'phone' => '+919988776655',
                'is_default' => true
            ]),
            $createReq('5. View Warehouse Stock Levels', 'GET', 'api/inventory/warehouses/1'),
            $createReq('6. Inter-Warehouse Stock Transfer', 'POST', 'api/inventory/transfers', null, [
                'from_warehouse_id' => 1,
                'to_warehouse_id' => 2,
                'transfer_date' => '2026-09-26',
                'status' => 'In Transit',
                'notes' => 'Stock replenishment transfer',
                'items' => [
                    [
                        'product_id' => 1,
                        'quantity' => 25
                    ]
                ]
            ]),
            $createReq('7. Stock Adjustment (Physical Count Discrepancy)', 'POST', 'api/inventory/adjustments', null, [
                'warehouse_id' => 1,
                'adjustment_date' => '2026-09-26',
                'reason' => 'Quarterly Physical Stock Count Correction',
                'items' => [
                    [
                        'product_id' => 1,
                        'type' => 'increase',
                        'quantity' => 5,
                        'unit_cost' => 3400.00
                    ]
                ]
            ]),
            $createReq('8. Create Product Batch (With Expiry)', 'POST', 'api/inventory/batches', null, [
                'product_id' => 1,
                'warehouse_id' => 1,
                'batch_number' => 'BATCH-2026-SEP-01',
                'quantity' => 500,
                'manufacturing_date' => '2026-09-01',
                'expiry_date' => '2028-08-31'
            ]),
            $createReq('9. Create Item Serial Number', 'POST', 'api/inventory/serials', null, [
                'product_id' => 1,
                'warehouse_id' => 1,
                'batch_id' => 1,
                'serial_number' => 'SRN-2026-009182',
                'purchase_rate' => 3400.00,
                'status' => 'Available'
            ]),
            $createReq('10. Query Stock Ledger Audit Trail', 'GET', 'api/inventory/ledger', [
                'product_id' => 1,
                'warehouse_id' => 1,
                'type' => 'IN'
            ])
        ]
    ];

    return $collection;
}

$collection = buildPostmanCollection();
$json = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

$outputPath1 = __DIR__ . '/../ERP_Master_REST_API_Postman_Collection.json';
file_put_contents($outputPath1, $json);

echo "SUCCESS: Postman collection JSON updated at: " . realpath($outputPath1) . "\n";
