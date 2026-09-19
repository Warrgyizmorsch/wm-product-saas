<?php

// CRM sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'revenue_cycle', 'order' => 1, 'module' => 'crm',
        'label' => 'crm.dashboard_sidebar', 'default' => 'CRM Executive Dashboard', 'icon' => 'feather-grid',
        'route' => 'crm.dashboard',
    ],
    [
        'section' => 'revenue_cycle', 'order' => 10,
        'label' => 'ui.crm', 'default' => 'CRM', 'icon' => 'feather-users',
        'children' => [
            ['label' => 'crm.leads', 'default' => 'Leads', 'route' => 'crm.leads.index', 'permission' => 'crm.leads.view'],
            ['label' => 'crm.deals_sidebar', 'default' => 'Deals (Pipeline)', 'route' => 'crm.deals.index'],
            ['label' => 'crm.accounts_sidebar', 'default' => 'Accounts (Companies)', 'route' => 'crm.accounts.index'],
            ['label' => 'crm.customers_sidebar', 'default' => 'Customers', 'route' => 'crm.customers.index', 'permission' => 'crm.customers.view'],
            ['label' => 'crm.activities', 'default' => 'Activities', 'route' => 'crm.activities.index', 'permission' => 'crm.leads.view'],
            ['label' => 'crm.track_status_sidebar', 'default' => 'Track Status', 'route' => 'crm.leads.trackStatus', 'permission' => 'crm.leads.view'],
        ],
    ],
    [
        'section' => 'revenue_cycle', 'order' => 20,
        'label' => 'crm.crm_masters_sidebar', 'default' => 'CRM Masters', 'icon' => 'feather-settings',
        'children' => [
            ['label' => 'crm.lead_status_master', 'default' => 'Lead Status Master', 'route' => 'crm.masters.lead-statuses.index'],
            ['label' => 'crm.deal_stage_master', 'default' => 'Deal Stage Master', 'route' => 'crm.masters.deal-statuses.index'],
            ['label' => 'crm.crm_sales_settings', 'default' => 'CRM & Sales Settings', 'route' => 'crm.settings.index'],
        ],
    ],
    [
        'section' => 'revenue_cycle', 'order' => 30,
        'label' => 'crm.approvals_sidebar', 'default' => 'Approvals', 'icon' => 'feather-check-circle',
        'children' => [
            ['label' => 'crm.quotation_approval', 'default' => 'Quotation Approval', 'route' => 'crm.approvals.quotations.index', 'permission' => 'crm.quotations.view'],
        ],
    ],
];
