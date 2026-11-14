<?php

// CRM sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'revenue_cycle', 'order' => 1, 'module' => 'crm',
        'label' => 'crm.dashboard_sidebar', 'default' => 'CRM Executive Dashboard', 'icon' => 'feather-grid',
        'route' => 'crm.dashboard',
        'permission' => ['crm.leads.view', 'crm.deals.view', 'crm.customers.view'],
    ],
    [
        'section' => 'revenue_cycle', 'order' => 10,
        'label' => 'ui.crm', 'default' => 'CRM', 'icon' => 'feather-users',
        'children' => [
            ['label' => 'crm.leads', 'default' => 'Leads', 'route' => 'crm.leads.index', 'permission' => 'crm.leads.view'],
            ['label' => 'crm.deals_sidebar', 'default' => 'Deals (Pipeline)', 'route' => 'crm.deals.index', 'permission' => 'crm.deals.view'],
            ['label' => 'crm.my_meetings_sidebar', 'default' => 'My Meetings', 'route' => 'crm.meetings.index', 'permission' => ['crm.deals.view', 'crm.leads.view']],
            ['label' => 'crm.accounts_sidebar', 'default' => 'Accounts (Companies)', 'route' => 'crm.accounts.index', 'permission' => 'crm.customers.view'],
            ['label' => 'crm.customers_sidebar', 'default' => 'Customers', 'route' => 'crm.customers.index', 'permission' => 'crm.customers.view'],
            ['label' => 'crm.web_forms_sidebar', 'default' => 'Web-to-Lead Forms', 'route' => 'crm.web-forms.index', 'permission' => 'crm.leads.view'],
        ],
    ],
    [
        'section' => 'revenue_cycle', 'order' => 20,
        'label' => 'crm.crm_masters_sidebar', 'default' => 'CRM Masters', 'icon' => 'feather-settings',
        'children' => [
            ['label' => 'crm.lead_status_master', 'default' => 'Lead Status Master', 'route' => 'crm.masters.lead-statuses.index', 'permission' => 'crm.leads.create'],
            ['label' => 'crm.deal_stage_master', 'default' => 'Deal Stage Master', 'route' => 'crm.masters.deal-statuses.index', 'permission' => 'crm.deals.create'],
            ['label' => 'crm.crm_settings', 'default' => 'CRM Settings', 'route' => 'crm.settings.index', 'permission' => 'crm.leads.create'],
        ],
    ],
    [
        'section' => 'revenue_cycle', 'order' => 30,
        'label' => 'crm.approvals_sidebar', 'default' => 'Approvals', 'icon' => 'feather-check-circle',
        'children' => [
            ['label' => 'crm.quotation_approval', 'default' => 'Quotation Approval', 'route' => 'crm.approvals.quotations.index', 'permission' => 'crm.quotations.approve'],
        ],
    ],
];
