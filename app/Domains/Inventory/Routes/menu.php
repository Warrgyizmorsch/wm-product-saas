<?php

// Inventory sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'supply_chain', 'order' => 10, 'module' => 'inventory',
        'label' => 'inventory.store', 'default' => 'Store', 'icon' => 'feather-archive',
        'children' => [
            ['label' => 'inventory.material_requirements', 'default' => 'Material Requirements', 'route' => 'inventory.material-requirements.index', 'permission' => ['sales.material_requirements.view', 'inventory.products.view']],
            ['label' => 'inventory.mrp_shortage_analysis', 'default' => 'MRP & Shortage Analysis', 'route' => 'inventory.mrp-shortage.index'],
            ['label' => 'inventory.material_requests_prod', 'default' => 'Material Requests (Prod)', 'route' => 'inventory.material-requests.index'],
            ['label' => 'inventory.dispatch_orders', 'default' => 'Dispatch Orders', 'route' => 'inventory.dispatches.index', 'permission' => 'sales.dispatches.view'],
            ['label' => 'inventory.transporters_master', 'default' => 'Transporters Master', 'route' => 'platform.transporters.index', 'permission' => ['sales.dispatches.view', 'sales.orders.view', 'inventory.dispatches.view', 'inventory.warehouses.manage']],
        ],
    ],
    [
        'section' => 'supply_chain', 'order' => 20,
        'label' => 'ui.inventory', 'default' => 'Inventory', 'icon' => 'feather-box',
        'children' => [
            ['label' => 'inventory.products', 'default' => 'Products', 'route' => 'inventory.products.index', 'permission' => 'inventory.products.view'],
            ['label' => 'inventory.warehouses', 'default' => 'Warehouses', 'route' => 'inventory.warehouses.index', 'permission' => 'inventory.warehouses.manage'],
            ['label' => 'inventory.serial_numbers', 'default' => 'Serial Numbers', 'route' => 'inventory.serial-numbers.index'],
            ['label' => 'inventory.batches_fefo', 'default' => 'Batches (FEFO)', 'route' => 'inventory.batches.index'],
            ['label' => 'inventory.stock_transfers', 'default' => 'Stock Transfers', 'route' => 'inventory.transfers.index'],
            ['label' => 'inventory.stock_adjustments', 'default' => 'Stock Adjustments', 'route' => 'inventory.adjustments.index'],
            ['label' => 'inventory.stock_ledger_history', 'default' => 'Stock Ledger History', 'route' => 'inventory.transactions.index'],
            ['label' => 'inventory.stock_reservations', 'default' => 'Stock Reservations', 'route' => 'inventory.reservations.index'],
            ['label' => 'inventory.barcode_labels', 'default' => 'Barcode Labels', 'route' => 'inventory.barcodes.index'],
            ['label' => 'inventory.low_stock_report', 'default' => 'Low Stock Report', 'route' => 'inventory.reports.low-stock'],
            ['label' => 'inventory.stock_valuation_report', 'default' => 'Stock Valuation Report', 'route' => 'inventory.reports.valuation'],
        ],
    ],
];
