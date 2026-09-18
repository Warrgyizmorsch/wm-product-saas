<?php

// Purchase sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'supply_chain', 'order' => 30,
        'label' => 'ui.purchase', 'default' => 'Purchase', 'icon' => 'feather-truck',
        'children' => [
            ['label' => 'purchase.vendors_suppliers', 'default' => 'Vendors / Suppliers', 'route' => 'purchase.vendors.index', 'permission' => 'purchase.vendors.view'],
            ['label' => 'purchase.savings_dashboard', 'default' => 'Savings Dashboard', 'route' => 'purchase.rfqs.savings', 'permission' => 'purchase.rfqs.view'],
            ['label' => 'ui.purchase_requests', 'default' => 'Purchase Requests', 'route' => 'purchase.requisitions.index', 'permission' => 'purchase.requisitions.view'],
            ['label' => 'purchase.pending_pr_items', 'default' => 'Pending PR Items', 'route' => 'purchase.requisitions.pending-items', 'permission' => 'purchase.requisitions.view'],
            ['label' => 'purchase.rfqs', 'default' => 'RFQs', 'route' => 'purchase.rfqs.index', 'permission' => 'purchase.rfqs.view'],
            ['label' => 'purchase.purchase_orders', 'default' => 'Purchase Orders', 'route' => 'purchase.orders.index', 'permission' => 'purchase.orders.view'],
            ['label' => 'purchase.landed_cost_vouchers', 'default' => 'Landed Cost Vouchers', 'route' => 'purchase.landed-costs.index', 'permission' => 'purchase.landed_costs.view'],
            ['label' => 'purchase.vendor_bills', 'default' => 'Vendor Bills', 'route' => 'purchase.bills.index', 'permission' => 'purchase.bills.view'],
            ['label' => 'purchase.vendor_payments', 'default' => 'Vendor Payments', 'route' => 'purchase.payments.index', 'permission' => 'purchase.payments.view'],
            ['label' => 'purchase.advance_payments', 'default' => 'Advance Payments', 'route' => 'purchase.advances.index', 'permission' => 'purchase.advances.view'],
            ['label' => 'purchase.purchase_returns', 'default' => 'Purchase Returns', 'route' => 'purchase.returns.index', 'permission' => 'purchase.returns.view'],
        ],
    ],
    [
        'section' => 'supply_chain', 'order' => 40, 'module' => 'purchase', 'permission' => 'grns.view',
        'label' => 'purchase.goods_receipts', 'default' => 'GRN (Goods Receipts)', 'icon' => 'feather-package',
        'children' => [
            ['label' => 'purchase.pending_grns', 'default' => 'Pending GRNs', 'route' => 'grns.pending'],
            ['label' => 'purchase.all_goods_receipts', 'default' => 'All Goods Receipts', 'route' => 'grns.index', 'permission' => 'grns.view'],
            ['label' => 'purchase.new_goods_receipt', 'default' => 'New Goods Receipt', 'route' => 'grns.create', 'permission' => 'grns.create'],
        ],
    ],
    [
        'section' => 'supply_chain', 'order' => 50,
        'label' => 'purchase.purchase_approvals', 'default' => 'Purchase Approvals', 'icon' => 'feather-check-circle',
        'children' => [
            ['label' => 'purchase.pr_approvals', 'default' => 'PR Approvals', 'route' => 'purchase.pr-approvals.index', 'permission' => 'purchase.requisitions.view'],
            ['label' => 'purchase.po_approvals', 'default' => 'PO Approvals', 'route' => 'purchase.po-approvals.index', 'permission' => 'purchase.orders.view'],
        ],
    ],
];
