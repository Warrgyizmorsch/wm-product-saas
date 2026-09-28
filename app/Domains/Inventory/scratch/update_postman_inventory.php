<?php

$filePath = 'c:/xampp/htdocs/new erp/wm-product-saas/ERP_Master_REST_API_Postman_Collection.json';
$data = json_decode(file_get_contents($filePath), true);

function makeHeader($auth = true) {
    $headers = [
        ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
        ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'],
    ];
    if ($auth) {
        $headers[] = ['key' => 'Authorization', 'value' => 'Bearer {{token}}', 'type' => 'text'];
    }
    return $headers;
}

function makeUrl($raw, $pathParts) {
    return [
        'raw' => $raw,
        'host' => ['{{baseUrl}}'],
        'path' => $pathParts,
    ];
}

// Find Inventory Module
foreach ($data['item'] as &$module) {
    if ($module['name'] === 'Inventory Module' || str_contains($module['name'], 'Inventory')) {
        foreach ($module['item'] as &$folder) {
            // 1. Products
            if (str_contains($folder['name'], 'Products')) {
                // Check if warehouse-stocks exists
                $hasWs = false;
                foreach ($folder['item'] as $req) {
                    if (str_contains($req['name'], 'Warehouse Stocks')) $hasWs = true;
                }
                if (!$hasWs) {
                    $folder['item'][] = [
                        'name' => 'Download Product Sample CSV',
                        'request' => [
                            'method' => 'GET',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/products/download-sample', ['api', 'inventory', 'products', 'download-sample']),
                        ]
                    ];
                    $folder['item'][] = [
                        'name' => 'Bulk Import Products (CSV)',
                        'request' => [
                            'method' => 'POST',
                            'header' => [
                                ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
                                ['key' => 'Authorization', 'value' => 'Bearer {{token}}', 'type' => 'text'],
                            ],
                            'url' => makeUrl('{{baseUrl}}/api/inventory/products/import', ['api', 'inventory', 'products', 'import']),
                            'body' => [
                                'mode' => 'formdata',
                                'formdata' => [
                                    ['key' => 'file', 'type' => 'file', 'src' => '']
                                ]
                            ]
                        ]
                    ];
                    $folder['item'][] = [
                        'name' => 'Get Product Warehouse Stocks Breakdown',
                        'request' => [
                            'method' => 'GET',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/products/1/warehouse-stocks', ['api', 'inventory', 'products', '1', 'warehouse-stocks']),
                        ]
                    ];
                }
            }

            // 2. Batches
            if (str_contains($folder['name'], 'Batch Tracking')) {
                $hasPut = false;
                foreach ($folder['item'] as $req) {
                    if ($req['request']['method'] === 'PUT') $hasPut = true;
                }
                if (!$hasPut) {
                    $folder['item'][] = [
                        'name' => 'Update Batch',
                        'request' => [
                            'method' => 'PUT',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/batches/1', ['api', 'inventory', 'batches', '1']),
                            'body' => [
                                'mode' => 'raw',
                                'raw' => json_encode([
                                    'batch_number' => 'BATCH-2026-A1',
                                    'available_qty' => 95,
                                    'manufacturing_date' => '2026-01-10',
                                    'expiry_date' => '2027-01-10',
                                ], JSON_PRETTY_PRINT)
                            ]
                        ]
                    ];
                    $folder['item'][] = [
                        'name' => 'Delete Batch',
                        'request' => [
                            'method' => 'DELETE',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/batches/1', ['api', 'inventory', 'batches', '1']),
                        ]
                    ];
                }
            }

            // 3. Serial Numbers
            if (str_contains($folder['name'], 'Serial Numbers')) {
                $hasPut = false;
                foreach ($folder['item'] as $req) {
                    if ($req['request']['method'] === 'PUT') $hasPut = true;
                }
                if (!$hasPut) {
                    $folder['item'][] = [
                        'name' => 'Update Serial Number',
                        'request' => [
                            'method' => 'PUT',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/serials/1', ['api', 'inventory', 'serials', '1']),
                            'body' => [
                                'mode' => 'raw',
                                'raw' => json_encode([
                                    'status' => 'Damaged',
                                    'notes' => 'Damaged during transit inspection',
                                ], JSON_PRETTY_PRINT)
                            ]
                        ]
                    ];
                    $folder['item'][] = [
                        'name' => 'Delete Serial Number',
                        'request' => [
                            'method' => 'DELETE',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/serials/1', ['api', 'inventory', 'serials', '1']),
                        ]
                    ];
                }
            }

            // 4. Reports
            if (str_contains($folder['name'], 'Inventory Reports')) {
                $hasExp = false;
                foreach ($folder['item'] as $req) {
                    if (str_contains($req['name'], 'Expiry')) $hasExp = true;
                }
                if (!$hasExp) {
                    $folder['item'][] = [
                        'name' => 'Near-Expiry Batches Risk Report',
                        'request' => [
                            'method' => 'GET',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/reports/expiry?days=30&status=all&page=1', ['api', 'inventory', 'reports', 'expiry']),
                        ]
                    ];
                    $folder['item'][] = [
                        'name' => 'Export Expiry Risk Report to CSV',
                        'request' => [
                            'method' => 'GET',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/reports/expiry/export?days=30', ['api', 'inventory', 'reports', 'expiry', 'export']),
                        ]
                    ];
                }
            }
        }

        // Add 14. Transporters Folder if not present
        $hasTransporters = false;
        foreach ($module['item'] as $f) {
            if (str_contains($f['name'], 'Transporters')) $hasTransporters = true;
        }
        if (!$hasTransporters) {
            $module['item'][] = [
                'name' => '14. Transporters Master',
                'item' => [
                    [
                        'name' => 'List Transporters',
                        'request' => [
                            'method' => 'GET',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/transporters?search=&status=active&page=1', ['api', 'inventory', 'transporters']),
                        ]
                    ],
                    [
                        'name' => 'Create Transporter',
                        'request' => [
                            'method' => 'POST',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/transporters', ['api', 'inventory', 'transporters']),
                            'body' => [
                                'mode' => 'raw',
                                'raw' => json_encode([
                                    'name' => 'Safexpress Logistics India',
                                    'code' => 'SAFE-01',
                                    'transporter_id' => 'TRP-10029',
                                    'gstin' => '07AAAAA0000A1Z5',
                                    'phone' => '9876543210',
                                    'email' => 'dispatch@safexpress.example.com',
                                    'transport_mode' => 'Road',
                                    'status' => 'active',
                                ], JSON_PRETTY_PRINT)
                            ]
                        ]
                    ],
                    [
                        'name' => 'Quick Create Transporter',
                        'request' => [
                            'method' => 'POST',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/transporters/quick-create', ['api', 'inventory', 'transporters', 'quick-create']),
                            'body' => [
                                'mode' => 'raw',
                                'raw' => json_encode([
                                    'name' => 'VRL Logistics',
                                    'transporter_id' => 'TRP-VRL-02',
                                    'phone' => '9811223344',
                                ], JSON_PRETTY_PRINT)
                            ]
                        ]
                    ],
                    [
                        'name' => 'Get Transporter Details',
                        'request' => [
                            'method' => 'GET',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/transporters/1', ['api', 'inventory', 'transporters', '1']),
                        ]
                    ],
                    [
                        'name' => 'Update Transporter',
                        'request' => [
                            'method' => 'PUT',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/transporters/1', ['api', 'inventory', 'transporters', '1']),
                            'body' => [
                                'mode' => 'raw',
                                'raw' => json_encode([
                                    'city' => 'Gurugram',
                                    'state' => 'Haryana',
                                ], JSON_PRETTY_PRINT)
                            ]
                        ]
                    ],
                    [
                        'name' => 'Delete Transporter',
                        'request' => [
                            'method' => 'DELETE',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/transporters/1', ['api', 'inventory', 'transporters', '1']),
                        ]
                    ],
                ]
            ];
        }

        // Add 15. MRP & Net Shortage Planning Folder if not present
        $hasMrp = false;
        foreach ($module['item'] as $f) {
            if (str_contains($f['name'], 'MRP & Net Shortage')) $hasMrp = true;
        }
        if (!$hasMrp) {
            $module['item'][] = [
                'name' => '15. MRP & Net Shortage Planning',
                'item' => [
                    [
                        'name' => 'Calculate MRP & Net Shortage',
                        'request' => [
                            'method' => 'GET',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/mrp-shortage/calculate?product_id=1&quantity=50&warehouse_id=1', ['api', 'inventory', 'mrp-shortage', 'calculate']),
                        ]
                    ],
                    [
                        'name' => 'Generate PR From Net Shortages',
                        'request' => [
                            'method' => 'POST',
                            'header' => makeHeader(),
                            'url' => makeUrl('{{baseUrl}}/api/inventory/mrp-shortage/generate-pr', ['api', 'inventory', 'mrp-shortage', 'generate-pr']),
                            'body' => [
                                'mode' => 'raw',
                                'raw' => json_encode([
                                    'warehouse_id' => 1,
                                    'notes' => 'Automated shortage procurement request',
                                    'items' => [
                                        [
                                            'product_id' => 1,
                                            'quantity' => 25,
                                            'shortage_qty' => 25,
                                            'unit_cost' => 180.00,
                                        ]
                                    ]
                                ], JSON_PRETTY_PRINT)
                            ]
                        ]
                    ],
                ]
            ];
        }
    }
}

file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Successfully updated ERP_Master_REST_API_Postman_Collection.json!\n";
