<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Placeholder entries
    |--------------------------------------------------------------------------
    |
    | Menu entries without a route are screens that don't exist yet. They are
    | hidden so users never click into a dead link; turn this on while
    | building to see where they will sit.
    |
    */

    'show_placeholders' => (bool) env('NAVIGATION_SHOW_PLACEHOLDERS', false),

    /*
    |--------------------------------------------------------------------------
    | Module add-on pricing
    |--------------------------------------------------------------------------
    |
    | The one-time price (in whole rupees) a tenant pays via Razorpay to
    | self-install any module not already in their plan — flat across every
    | module for now (see TenantModuleController). Paise conversion happens
    | at checkout time, same as Plan::price.
    |
    */

    'module_addon_price' => (int) env('MODULE_ADDON_PRICE', 999),

    /*
    |--------------------------------------------------------------------------
    | Module dependencies
    |--------------------------------------------------------------------------
    |
    | module => modules it cannot work without. A tenant can't install a module
    | unless its requirements are installed (or bought in the same checkout),
    | and can't uninstall a module another installed module requires (see
    | TenantModuleService). Only functional needs belong here — models shared
    | across domains don't count, since uninstalling hides a module's screens
    | but never deletes or stops its data.
    |
    */

    'module_requires' => [
        'production' => ['inventory'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sidebar sections
    |--------------------------------------------------------------------------
    |
    | Top-to-bottom order of the sidebar sections. Each module adds its own
    | entries to these sections from app/Domains/{Module}/Routes/menu.php
    | (see App\Core\Navigation\MenuBuilder). `label` may be a translation key;
    | `default` is used when that key has no translation.
    |
    */

    'sections' => [
        'workspace' => ['label' => 'ui.workspace', 'default' => 'Workspace'],
        'revenue_cycle' => ['label' => 'ui.revenue_cycle', 'default' => 'Revenue Cycle'],
        'supply_chain' => ['label' => 'ui.supply_chain', 'default' => 'Supply Chain'],
        'production' => ['label' => 'ui.production', 'default' => 'Production'],
        'hrms' => ['label' => 'ui.hrms', 'default' => 'HRMS'],
        'finance' => ['label' => 'ui.finance_people', 'default' => 'Finance & People'],
        'platform_admin' => ['label' => 'ui.platform_admin', 'default' => 'Platform Admin', 'app' => 'admin'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Apps
    |--------------------------------------------------------------------------
    |
    | Like Zoho's apps: while you work in one (CRM, Inventory, ...) the sidebar
    | shows only that app's menu, kept open on the page you are on, and the
    | header's Modules launcher switches between them. A menu entry belongs to
    | an app through its `app` key, else its `module`, else the app of its
    | parent, else the module its route belongs to (see MenuBuilder). Entries
    | that belong to no app (the workspace dashboard, approvals) show on the
    | home page, where every section is listed. Each app also carries a
    | `color` (hex) for its icon tile — like Odoo's app grid, every app gets
    | its own distinct color rather than one uniform brand blue, so the
    | launcher and switcher stay scannable at a glance.
    |
    */

    'apps' => [
        'crm' => ['label' => 'CRM', 'icon' => 'feather-users', 'description' => 'Leads, deals, customers, activities', 'color' => '#7C3AED'],
        'sales' => ['label' => 'Sales', 'icon' => 'feather-shopping-cart', 'description' => 'Quotations, orders, invoices, receipts', 'color' => '#2563EB'],
        'inventory' => ['label' => 'Inventory', 'icon' => 'feather-box', 'description' => 'Products, stock, warehouses, store', 'color' => '#EA580C'],
        'purchase' => ['label' => 'Purchase', 'icon' => 'feather-truck', 'description' => 'Suppliers, POs, bills, goods receipts', 'color' => '#0D9488'],
        'production' => ['label' => 'Production', 'icon' => 'feather-cpu', 'description' => 'BOM, orders, shop floor, quality', 'color' => '#DB2777'],
        'hrms' => ['label' => 'HR & Payroll', 'icon' => 'feather-user-check', 'description' => 'Employees, attendance, leave, payroll', 'color' => '#16A34A'],
        'accounting' => ['label' => 'Accounting', 'icon' => 'feather-credit-card', 'description' => 'Ledgers, journals, tax, reports', 'color' => '#CA8A04'],
        'projects' => ['label' => 'Projects', 'icon' => 'feather-briefcase', 'description' => 'Projects, milestones, tasks', 'color' => '#4F46E5'],
        'admin' => ['label' => 'Administration', 'icon' => 'feather-shield', 'description' => 'Tenants, plans, users, roles, audit', 'color' => '#475569'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route prefix → app
    |--------------------------------------------------------------------------
    |
    | For pages that are not in the menu themselves (a "create lead" form): the
    | first segment of the route name tells which app's menu to show.
    |
    */

    'route_apps' => [
        'crm' => 'crm',
        'sales' => 'sales',
        'inventory' => 'inventory',
        'supply-chain' => 'inventory',
        'purchase' => 'purchase',
        'grns' => 'purchase',
        'production' => 'production',
        'hrms' => 'hrms',
        'accounting' => 'accounting',
        'projects' => 'projects',
        'platform' => 'admin',
        'access' => 'admin',
    ],

];
