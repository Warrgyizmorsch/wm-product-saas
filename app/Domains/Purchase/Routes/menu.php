<?php

// Purchase sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'supply_chain', 'order' => 30,
        'label' => 'ui.purchase', 'default' => 'Purchase', 'icon' => 'feather-truck',
        'children' => [
            ['label' => 'Vendors / Suppliers', 'route' => 'purchase.vendors.index'],
            ['label' => 'purchase.savings_dashboard', 'default' => 'Savings Dashboard', 'route' => 'purchase.rfqs.savings'],
            ['label' => 'ui.purchase_requests', 'default' => 'Purchase Requests', 'route' => 'purchase.requisitions.index'],
            ['label' => 'purchase.pending_pr_items', 'default' => 'Pending PR Items', 'route' => 'purchase.requisitions.pending-items'],
            ['label' => 'purchase.rfqs', 'default' => 'RFQs', 'route' => 'purchase.rfqs.index'],
            ['label' => 'purchase.purchase_orders', 'default' => 'Purchase Orders', 'route' => 'purchase.orders.index'],
            ['label' => 'Landed Cost Vouchers', 'route' => 'purchase.landed-costs.index'],
            ['label' => 'purchase.vendor_bills', 'default' => 'Vendor Bills', 'route' => 'purchase.bills.index'],
            ['label' => 'purchase.vendor_payments', 'default' => 'Vendor Payments', 'route' => 'purchase.payments.index'],
            ['label' => 'Advance Payments', 'route' => 'purchase.advance-payments.index', 'permission' => 'purchase.advances.view'],
            ['label' => 'Purchase Returns', 'route' => 'purchase.returns.index'],
        ],
    ],
    [
        'section' => 'supply_chain', 'order' => 40, 'module' => 'purchase',
        'label' => 'GRN (Goods Receipts)', 'icon' => 'feather-package',
        'children' => [
            ['label' => 'purchase.pending_grns', 'default' => 'Pending GRNs', 'route' => 'grns.pending'],
            ['label' => 'purchase.all_goods_receipts', 'default' => 'All Goods Receipts', 'route' => 'grns.index'],
            ['label' => 'New Goods Receipt', 'route' => 'grns.create'],
        ],
    ],
    [
        'section' => 'supply_chain', 'order' => 50,
        'label' => 'Purchase Approvals', 'icon' => 'feather-check-circle',
        'children' => [
            ['label' => 'PR Approvals', 'route' => 'purchase.pr-approvals.index'],
            ['label' => 'PO Approvals', 'route' => 'purchase.po-approvals.index'],
        ],
    ],
];
