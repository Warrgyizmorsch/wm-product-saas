<?php

// CRM sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'revenue_cycle', 'order' => 10,
        'label' => 'ui.crm', 'default' => 'CRM', 'icon' => 'feather-users',
        'children' => [
            ['label' => 'CRM Dashboard', 'default' => 'CRM Executive Dashboard', 'route' => 'crm.dashboard'],
            ['label' => 'crm.leads', 'default' => 'Leads', 'route' => 'crm.leads.index'],
            ['label' => 'crm.leads', 'default' => 'Leads', 'route' => 'crm.leads.index', 'permission' => 'crm.leads.view'],
            ['label' => 'crm.deals_sidebar', 'default' => 'Deals (Pipeline)', 'route' => 'crm.deals.index'],
            ['label' => 'crm.accounts_sidebar', 'default' => 'Accounts (Companies)', 'route' => 'crm.accounts.index'],
            ['label' => 'crm.customers_sidebar', 'default' => 'Customers', 'route' => 'crm.customers.index', 'permission' => 'crm.customers.view'],
            ['label' => 'Activities', 'route' => 'crm.activities.index', 'permission' => 'crm.leads.view'],
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
            ['label' => 'Email & SMTP Accounts', 'route' => 'crm.emailSettings.index'],
            ['label' => 'WhatsApp Web Setup', 'route' => 'crm.whatsappSettings.index'],
        ],
    ],
    [
        'section' => 'revenue_cycle', 'order' => 30,
        'label' => 'Approvals', 'icon' => 'feather-check-circle',
        'children' => [
            ['label' => 'Quotation Approval', 'route' => 'crm.approvals.quotations.index', 'permission' => 'crm.quotations.view'],
        ],
    ],
];
