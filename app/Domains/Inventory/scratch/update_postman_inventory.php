<?php

$postmanPath = __DIR__ . '/../../../../ERP_Master_REST_API_Postman_Collection.json';
$artifactPath = 'C:/Users/HP5CD/.gemini/antigravity-ide/brain/cef28d39-0827-40c5-8ef8-7ecf580cc887/ERP_Master_REST_API_Postman_Collection.json';

$collection = json_decode(file_get_contents($postmanPath), true);

function makeReq($name, $method, $path, $body = null, $desc = '') {
    $urlParts = explode('?', $path);
    $pathOnly = $urlParts[0];
    $queryStr = $urlParts[1] ?? null;

    $pathSegments = array_values(array_filter(explode('/', $pathOnly)));
    
    $req = [
        'name' => $name,
        'request' => [
            'method' => $method,
            'header' => [
                ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
            ],
            'url' => [
                'raw' => '{{base_url}}' . $path,
                'host' => ['{{base_url}}'],
                'path' => $pathSegments,
            ],
            'description' => $desc,
        ],
        'response' => [],
    ];

    if ($queryStr) {
        $queryParams = [];
        parse_str($queryStr, $parsed);
        foreach ($parsed as $k => $v) {
            $queryParams[] = ['key' => $k, 'value' => (string)$v];
        }
        $req['request']['url']['query'] = $queryParams;
    }

    if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        $req['request']['header'][] = ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'];
        $req['request']['body'] = [
            'mode' => 'raw',
            'raw' => is_string($body) ? $body : json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'options' => [
                'raw' => ['language' => 'json']
            ]
        ];
    }

    return $req;
}

// Build Inventory Folder
$inventoryFolder = [
    'name' => 'Inventory Module',
    'item' => [
        // 1. Supply Chain Dashboard
        [
            'name' => '1. Supply Chain Dashboard',
            'item' => [
                makeReq('Get Dashboard Stats', 'GET', '/api/inventory/dashboard/stats?preset=this_month', null, 'Fetch unified KPI stats, stock valuation aggregates, monthly trends, and recent operations'),
            ]
        ],
        // 2. Products
        [
            'name' => '2. Products',
            'item' => [
                makeReq('Product Metadata', 'GET', '/api/inventory/products/meta', null, 'Get product form dropdown options, units, categories, taxes'),
                makeReq('List Products', 'GET', '/api/inventory/products?search=&category_id=&status=&page=1&per_page=15', null, 'Paginated list of products with filters'),
                makeReq('Export Products to Excel', 'GET', '/api/inventory/products/export', null, 'Export filtered products to Excel spreadsheet'),
                makeReq('Barcode Lookup', 'GET', '/api/inventory/products/barcode-lookup?code=PROD-SKU-001', null, 'Scan barcode / SKU to retrieve product details and warehouse stock'),
                makeReq('Realtime Stock Check', 'GET', '/api/inventory/products/stock-check?product_id=1&warehouse_id=1', null, 'Check physical, reserved, and net available stock for a product in a warehouse'),
                makeReq('Create Product', 'POST', '/api/inventory/products', [
                    'name' => 'Industrial Steel Pipe 2-Inch',
                    'sku' => 'ISP-002',
                    'barcode' => '8901234567890',
                    'type' => 'finished_good',
                    'category_id' => 1,
                    'unit' => 'pcs',
                    'selling_price' => 450.00,
                    'cost_price' => 320.00,
                    'min_selling_price' => 400.00,
                    'tax_rate' => 18.00,
                    'hsn_code' => '7306',
                    'reorder_point' => 20,
                    'track_batch' => true,
                    'track_serial_number' => false,
                    'status' => 'active',
                ], 'Create a new catalog item or material'),
                makeReq('Quick Create Product', 'POST', '/api/inventory/products/quick-create', [
                    'name' => 'Copper Fitting 90-Deg',
                    'sku' => 'CPF-090',
                    'selling_price' => 85.00,
                    'cost_price' => 55.00,
                    'unit' => 'pcs',
                ], 'Quick create product on-the-fly from quotation/sales modal'),
                makeReq('Get Product Details', 'GET', '/api/inventory/products/1', null, 'Get single product details with warehouse stocks and price history'),
                makeReq('Update Product', 'PUT', '/api/inventory/products/1', [
                    'name' => 'Industrial Steel Pipe 2-Inch (Heavy Duty)',
                    'selling_price' => 480.00,
                    'cost_price' => 340.00,
                    'reorder_point' => 25,
                ], 'Update existing product properties'),
                makeReq('Delete Product', 'DELETE', '/api/inventory/products/1', null, 'Delete product if no active stock or transactions exist'),
                makeReq('Toggle Product Status', 'POST', '/api/inventory/products/1/toggle-status', null, 'Toggle active/inactive product status'),
                makeReq('Get Opening Stock', 'GET', '/api/inventory/products/1/opening-stock', null, 'Get current warehouse stock distribution for product opening stock setup'),
                makeReq('Save Opening Stock', 'POST', '/api/inventory/products/1/opening-stock', [
                    'stocks' => [
                        ['warehouse_id' => 1, 'quantity' => 100, 'unit_cost' => 320.00],
                        ['warehouse_id' => 2, 'quantity' => 50, 'unit_cost' => 320.00],
                    ]
                ], 'Set or update multi-warehouse opening balance inventory'),
            ]
        ],
        // 3. Warehouses
        [
            'name' => '3. Warehouses',
            'item' => [
                makeReq('List Warehouses', 'GET', '/api/inventory/warehouses?search=&status=', null, 'List all warehouses with status and address'),
                makeReq('Export Warehouses to Excel', 'GET', '/api/inventory/warehouses/export', null, 'Download warehouse directory Excel sheet'),
                makeReq('Create Warehouse', 'POST', '/api/inventory/warehouses', [
                    'name' => 'South Regional Fulfillment Center',
                    'code' => 'SRFC-01',
                    'address' => 'Plot 42, Industrial Area',
                    'city' => 'Bengaluru',
                    'state' => 'Karnataka',
                    'pincode' => '560100',
                    'country' => 'India',
                    'contact_person' => 'Ramesh Kumar',
                    'phone' => '+91-9876543210',
                    'email' => 'warehouse.blr@example.com',
                    'is_default' => false,
                    'status' => 'active',
                ], 'Create a new physical warehouse location'),
                makeReq('Quick Create Warehouse', 'POST', '/api/inventory/warehouses/quick-create', [
                    'name' => 'Temporary Transit Hub',
                    'code' => 'TTH-01',
                    'address' => 'Dock 4, Port Area',
                ], 'Fast modal warehouse creation'),
                makeReq('Get Warehouse Details', 'GET', '/api/inventory/warehouses/1', null, 'Get warehouse details and product stock summary'),
                makeReq('Update Warehouse', 'PUT', '/api/inventory/warehouses/1', [
                    'name' => 'Central Hub Warehouse',
                    'contact_person' => 'Suresh Reddy',
                    'status' => 'active',
                ], 'Update warehouse info'),
                makeReq('Delete Warehouse', 'DELETE', '/api/inventory/warehouses/1', null, 'Delete warehouse if zero stock on hand'),
            ]
        ],
        // 4. Stock Transfers
        [
            'name' => '4. Stock Transfers',
            'item' => [
                makeReq('Stock Transfer Metadata', 'GET', '/api/inventory/transfers/meta', null, 'Get warehouses, sellable products, and transfer statuses'),
                makeReq('List Stock Transfers', 'GET', '/api/inventory/transfers?search=&status=&from_warehouse_id=&to_warehouse_id=&page=1', null, 'Paginated list of inter-warehouse transfers'),
                makeReq('Export Transfers to Excel', 'GET', '/api/inventory/transfers/export', null, 'Export stock transfers report to Excel'),
                makeReq('Create Stock Transfer', 'POST', '/api/inventory/transfers', [
                    'from_warehouse_id' => 1,
                    'to_warehouse_id' => 2,
                    'transfer_date' => date('Y-m-d'),
                    'status' => 'Draft',
                    'notes' => 'Replenishing North Warehouse branch stock',
                    'items' => [
                        ['product_id' => 1, 'quantity' => 25, 'batch_id' => null, 'serial_numbers' => null],
                    ]
                ], 'Create inter-warehouse transfer order with stock validation'),
                makeReq('Get Stock Transfer Details', 'GET', '/api/inventory/transfers/1', null, 'View full transfer slip, dispatched/received item lines'),
                makeReq('Update Transfer Status', 'PATCH', '/api/inventory/transfers/1/status', [
                    'status' => 'In Transit'
                ], 'Update transfer status directly'),
                makeReq('Dispatch Transfer', 'POST', '/api/inventory/transfers/1/dispatch', null, 'Dispatch stock transfer: records physical stock OUT from source warehouse'),
                makeReq('Receive Transfer', 'POST', '/api/inventory/transfers/1/receive', null, 'Receive stock transfer: records physical stock IN into destination warehouse'),
                makeReq('Cancel Transfer', 'POST', '/api/inventory/transfers/1/cancel', null, 'Cancel Draft or Pending transfer'),
            ]
        ],
        // 5. Stock Adjustments
        [
            'name' => '5. Stock Adjustments',
            'item' => [
                makeReq('Stock Adjustment Metadata', 'GET', '/api/inventory/adjustments/meta', null, 'Get adjustment reasons, types (Addition/Deduction), and warehouses'),
                makeReq('List Stock Adjustments', 'GET', '/api/inventory/adjustments?search=&status=&reason=&warehouse_id=&page=1', null, 'List physical count adjustments and write-offs'),
                makeReq('Export Adjustments to Excel', 'GET', '/api/inventory/adjustments/export', null, 'Export stock adjustment audits to Excel'),
                makeReq('Create Stock Adjustment', 'POST', '/api/inventory/adjustments', [
                    'warehouse_id' => 1,
                    'adjustment_date' => date('Y-m-d'),
                    'reason' => 'Stock Count Variance',
                    'status' => 'Draft',
                    'notes' => 'Annual physical count reconciliation',
                    'items' => [
                        ['product_id' => 1, 'type' => 'Addition', 'quantity' => 10, 'unit_cost' => 320.00],
                    ]
                ], 'Record stock count adjustment voucher'),
                makeReq('Get Stock Adjustment Details', 'GET', '/api/inventory/adjustments/1', null, 'View adjustment details, items, creator and approver info'),
                makeReq('Approve Stock Adjustment', 'POST', '/api/inventory/adjustments/1/approve', null, 'Approve adjustment and commit physical inventory changes via StockService'),
                makeReq('Cancel Stock Adjustment', 'POST', '/api/inventory/adjustments/1/cancel', null, 'Cancel unapproved adjustment'),
            ]
        ],
        // 6. Batch Tracking
        [
            'name' => '6. Batch Tracking',
            'item' => [
                makeReq('List Batches', 'GET', '/api/inventory/batches?search=&product_id=&warehouse_id=&available_only=1&page=1', null, 'List production batches with expiry tracking and available quantity'),
                makeReq('Export Batches to Excel', 'GET', '/api/inventory/batches/export', null, 'Export batch inventory and expiry report'),
                makeReq('Create Batch', 'POST', '/api/inventory/batches', [
                    'product_id' => 1,
                    'warehouse_id' => 1,
                    'batch_number' => 'BAT-2026-009',
                    'quantity' => 500,
                    'manufacturing_date' => date('Y-m-d'),
                    'expiry_date' => date('Y-m-d', strtotime('+365 days')),
                ], 'Create a new manufacturing batch record'),
                makeReq('Get Batch Details', 'GET', '/api/inventory/batches/1', null, 'View batch details and linked serial numbers'),
            ]
        ],
        // 7. Serial Numbers
        [
            'name' => '7. Serial Numbers',
            'item' => [
                makeReq('List Serial Numbers', 'GET', '/api/inventory/serials?search=&product_id=&warehouse_id=&status=Available&page=1', null, 'List unique serial numbers, warehouse locations and lifecycle status'),
                makeReq('Export Serials to CSV', 'GET', '/api/inventory/serials/export', null, 'Export full serial number lifecycle dataset'),
                makeReq('Create Serial Number', 'POST', '/api/inventory/serials', [
                    'product_id' => 1,
                    'warehouse_id' => 1,
                    'batch_id' => 1,
                    'serial_number' => 'SN-2026-X89021',
                    'purchase_rate' => 320.00,
                    'status' => 'Available',
                ], 'Register individual serialized equipment item'),
                makeReq('Get Serial Number Details', 'GET', '/api/inventory/serials/1', null, 'View serial item lifecycle, inbound & outbound transactions'),
            ]
        ],
        // 8. Stock Ledger
        [
            'name' => '8. Stock Ledger',
            'item' => [
                makeReq('List Stock Ledger Transactions', 'GET', '/api/inventory/ledger?product_id=&warehouse_id=&type=&reference_type=&from_date=&to_date=&page=1', null, 'Audit trail of every stock IN/OUT transaction with unit costs and references'),
                makeReq('Export Stock Ledger to Excel', 'GET', '/api/inventory/ledger/export', null, 'Export complete inventory movement ledger to Excel'),
            ]
        ],
        // 9. Inventory Reports
        [
            'name' => '9. Inventory Reports',
            'item' => [
                makeReq('Low Stock Alerts Report', 'GET', '/api/inventory/reports/low-stock?search=&page=1', null, 'Get items below reorder point with shortage calculation and active PR flags'),
                makeReq('Create PR From Low Stock', 'POST', '/api/inventory/reports/low-stock/create-pr', [
                    'product_ids' => [1, 2]
                ], 'Auto-generate a Draft Purchase Requisition for selected low stock products'),
                makeReq('Stock Asset Valuation Report', 'GET', '/api/inventory/reports/valuation?warehouse_id=&item_category=all&search=&page=1', null, 'Dynamic asset valuation breakdown (Total, Finished Goods, Raw Materials)'),
                makeReq('Export Stock Valuation Report', 'GET', '/api/inventory/reports/valuation/export?warehouse_id=&item_category=all', null, 'Download formatted Stock Valuation CSV Report with category totals'),
            ]
        ],
        // 10. Stock Reservations
        [
            'name' => '10. Stock Reservations',
            'item' => [
                makeReq('List Stock Reservations', 'GET', '/api/inventory/reservations?search=&status=Active&warehouse_id=&product_id=&page=1', null, 'List active reservations allocated to sales orders / production'),
                makeReq('Release Stock Reservation', 'POST', '/api/inventory/reservations/1/release', null, 'Manually release reserved quantity back into net available stock'),
            ]
        ],
        // 11. Barcode System
        [
            'name' => '11. Barcode System',
            'item' => [
                makeReq('Barcode Metadata', 'GET', '/api/inventory/barcodes/meta', null, 'Get barcode-ready products and warehouses for sticker generation'),
                makeReq('Get Product Serial Numbers for Barcodes', 'GET', '/api/inventory/barcodes/serials/1', null, 'Get available serial numbers for a given product'),
                makeReq('Generate Printable Barcode Labels', 'POST', '/api/inventory/barcodes/print', [
                    'product_id' => 1,
                    'print_type' => 'product',
                    'warehouse_id' => 1,
                    'copies' => 5,
                ], 'Generate structured label payloads for thermal barcode printers'),
            ]
        ],
        // 12. Units of Measurement (UOM)
        [
            'name' => '12. Units of Measurement (UOM)',
            'item' => [
                makeReq('List UOMs', 'GET', '/api/inventory/uoms?search=', null, 'List registered units of measurement (pcs, kg, box, mtr, etc.)'),
                makeReq('Create UOM', 'POST', '/api/inventory/uoms', [
                    'name' => 'Kilogram',
                    'code' => 'KG',
                    'description' => 'Metric weight unit',
                ], 'Create new unit of measurement'),
                makeReq('Quick Create UOM', 'POST', '/api/inventory/uoms/quick-create', [
                    'name' => 'Square Meter',
                    'code' => 'SQM',
                ], 'Fast modal UOM creation'),
            ]
        ],
        // 13. Material Requirements
        [
            'name' => '13. Material Requirements & Store Dispatch',
            'item' => [
                makeReq('List Material Requirements', 'GET', '/api/inventory/material-requirements?search=&status=&page=1', null, 'List all store dispatch requests and sales order material requisitions'),
                makeReq('Create Material Requirement', 'POST', '/api/inventory/material-requirements', [
                    'warehouse_id' => 1,
                    'sales_order_id' => 1,
                    'required_date' => date('Y-m-d', strtotime('+3 days')),
                    'priority' => 'High',
                    'notes' => 'Urgent site delivery',
                    'items' => [
                        ['product_id' => 1, 'requested_quantity' => 15]
                    ]
                ], 'Create a new store dispatch requisition'),
                makeReq('Get Material Requirement Details', 'GET', '/api/inventory/material-requirements/1', null, 'Get store requisition lines, picked quantities and dispatch status'),
                makeReq('Start Picking', 'POST', '/api/inventory/material-requirements/1/pick', [
                    'picked_items' => [
                        ['id' => 1, 'picked_quantity' => 15]
                    ]
                ], 'Start warehouse picking process'),
                makeReq('Pack Items', 'POST', '/api/inventory/material-requirements/1/pack', [
                    'package_count' => 2,
                    'package_weight' => 45.5,
                ], 'Pack items into shipping boxes / containers'),
                makeReq('Dispatch Items', 'POST', '/api/inventory/material-requirements/1/dispatch', [
                    'transporter_name' => 'DHL Express',
                    'tracking_number' => 'DHL-890123891',
                    'vehicle_number' => 'KA-01-EA-1234',
                ], 'Dispatch goods from warehouse and update inventory'),
                makeReq('Deliver Order', 'POST', '/api/inventory/material-requirements/1/deliver', [
                    'received_by' => 'John Doe (Client Rep)',
                    'delivery_notes' => 'Delivered in good condition',
                ], 'Mark order as delivered at destination'),
                makeReq('Cancel Material Requirement', 'POST', '/api/inventory/material-requirements/1/cancel', null, 'Cancel warehouse material requisition'),
            ]
        ],
    ]
];

// Replace or add Inventory Folder in collection
$existingIndex = -1;
foreach ($collection['item'] as $i => $item) {
    if (isset($item['name']) && stripos($item['name'], 'Inventory') !== false) {
        $existingIndex = $i;
        break;
    }
}

if ($existingIndex >= 0) {
    $collection['item'][$existingIndex] = $inventoryFolder;
} else {
    $collection['item'][] = $inventoryFolder;
}

file_put_contents($postmanPath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($artifactPath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Postman collection updated successfully at:\n- {$postmanPath}\n- {$artifactPath}\n";
