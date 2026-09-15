<?php

// Inventory sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'supply_chain', 'order' => 10, 'module' => 'inventory',
        'label' => 'Store', 'icon' => 'feather-archive',
        'children' => [
            ['label' => 'Material Requirements', 'route' => 'inventory.material-requirements.index'],
            ['label' => 'MRP & Shortage Analysis', 'route' => 'inventory.mrp-shortage.index'],
            ['label' => 'Material Requests (Prod)', 'route' => 'inventory.material-requests.index'],
            ['label' => 'Dispatch Orders', 'route' => 'inventory.dispatches.index'],
            ['label' => 'Transporters Master', 'route' => 'platform.transporters.index'],
        ],
    ],
    [
        'section' => 'supply_chain', 'order' => 20,
        'label' => 'ui.inventory', 'default' => 'Inventory', 'icon' => 'feather-box',
        'children' => [
            ['label' => 'inventory.products', 'default' => 'Products', 'route' => 'inventory.products.index'],
            ['label' => 'inventory.warehouses', 'default' => 'Warehouses', 'route' => 'inventory.warehouses.index'],
            ['label' => 'inventory.serial_numbers', 'default' => 'Serial Numbers', 'route' => 'inventory.serial-numbers.index'],
            ['label' => 'inventory.batches_fefo', 'default' => 'Batches (FEFO)', 'route' => 'inventory.batches.index'],
            ['label' => 'Stock Transfers', 'route' => 'inventory.transfers.index'],
            ['label' => 'Stock Adjustments', 'route' => 'inventory.adjustments.index'],
            ['label' => 'Stock Ledger History', 'route' => 'inventory.transactions.index'],
            ['label' => 'Stock Reservations', 'route' => 'inventory.reservations.index'],
            ['label' => 'Barcode Labels', 'route' => 'inventory.barcodes.index'],
            ['label' => 'Low Stock Report', 'route' => 'inventory.reports.low-stock'],
            ['label' => 'Stock Valuation Report', 'route' => 'inventory.reports.valuation'],
        ],
    ],
];
