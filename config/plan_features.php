<?php

/*
|--------------------------------------------------------------------------
| Plan features (Zoho-style feature-level gating)
|--------------------------------------------------------------------------
|
| Every feature a plan can leave out, grouped by module for the plan editor
| and the Subscription page comparison table. A feature's key is a
| permission-name prefix ("purchase.returns" covers purchase.returns.view,
| .create, .delete …); 'covers' adds more prefixes for the same feature.
| A permission belongs to the feature with the longest matching prefix.
|
| plans.excluded_features lists the keys a plan does NOT include. Excluded
| features stay view-only — their screens open, but nothing can be created,
| changed, posted or deleted (PlanFeatureGate). Permissions not covered here
| (access, platform, dashboard, audit, settings) are never gated.
|
*/

return [

    // Actions still allowed on an excluded feature (its data stays readable).
    'view_only_actions' => ['view'],

    'groups' => [

        'crm' => [
            'label' => 'CRM',
            'features' => [
                'crm.leads' => ['label' => 'Leads'],
                'crm.deals' => ['label' => 'Deals pipeline'],
                'crm.customers' => ['label' => 'Customer management'],
                'crm.quotations' => ['label' => 'Quotations'],
            ],
        ],

        'sales' => [
            'label' => 'Sales',
            'features' => [
                'sales.orders' => ['label' => 'Sales orders'],
                'sales.invoices' => ['label' => 'Invoices'],
                'sales.payments' => ['label' => 'Payments received'],
                'sales.returns' => ['label' => 'Sales returns'],
                'sales.dispatches' => ['label' => 'Dispatch'],
                'sales.material_requirements' => ['label' => 'Material requirements', 'covers' => ['inventory.material_requirements']],
            ],
        ],

        'inventory' => [
            'label' => 'Inventory',
            'features' => [
                'inventory.products' => ['label' => 'Products & stock', 'covers' => ['inventory.uoms']],
                'inventory.warehouses' => ['label' => 'Multiple warehouses'],
                'inventory.dispatches' => ['label' => 'Dispatch orders'],
            ],
        ],

        'purchase' => [
            'label' => 'Purchase',
            'features' => [
                'purchase.vendors' => ['label' => 'Vendor management'],
                'purchase.requisitions' => ['label' => 'Purchase requisitions'],
                'purchase.rfqs' => ['label' => 'Requests for quotation'],
                'purchase.orders' => ['label' => 'Purchase orders'],
                'grns' => ['label' => 'Goods receipts'],
                'purchase.bills' => ['label' => 'Bills & three-way match'],
                'purchase.payments' => ['label' => 'Vendor payments'],
                'purchase.advances' => ['label' => 'Vendor advances'],
                'purchase.returns' => ['label' => 'Purchase returns'],
                'purchase.landed_costs' => ['label' => 'Landed costs'],
                'purchase.approvals' => ['label' => 'Purchase approvals'],
            ],
        ],

        'production' => [
            'label' => 'Production',
            'features' => [
                'production.bom' => ['label' => 'Bills of materials'],
                'production.work_center' => ['label' => 'Work centers & machines', 'covers' => ['production.machine']],
                'production.routing' => ['label' => 'Routings'],
                'production.order' => ['label' => 'Production orders'],
                'production.planning' => ['label' => 'Planning & scheduling', 'covers' => ['production.schedule']],
                'production.mes' => ['label' => 'Shop floor (MES)'],
                'production.quality' => ['label' => 'Quality control'],
                'production.cost_adjustment' => ['label' => 'Cost adjustments'],
                'production.intelligence' => ['label' => 'Production intelligence'],
            ],
        ],

        'hrms' => [
            'label' => 'HR & Payroll',
            'features' => [
                'hrms.employees' => ['label' => 'Employee records', 'covers' => ['hr.employees', 'hrms.org', 'hrms.profile_requests', 'hrms.self_service']],
                'hrms.attendance' => ['label' => 'Attendance', 'covers' => ['hr.attendance', 'hrms.attendance_corrections', 'hrms.wfh_requests', 'hrms.overtime']],
                'hrms.biometric_devices' => ['label' => 'Biometric devices'],
                'hrms.rosters' => ['label' => 'Shifts & rosters', 'covers' => ['hrms.roster', 'hrms.shift_roster', 'hrms.shift_changes']],
                'hrms.leaves' => ['label' => 'Leave & holidays', 'covers' => ['hrms.leave_requests', 'hrms.leave_structures', 'hrms.holidays', 'hrms.holiday_calendar']],
                'hrms.leave_encashments' => ['label' => 'Leave encashment'],
                'hrms.payroll' => ['label' => 'Payroll', 'covers' => ['hr.payroll', 'hrms.payroll_runs', 'hrms.salary_structures']],
                'hrms.penalties' => ['label' => 'Penalties', 'covers' => ['hrms.penalization_policies']],
                'hrms.travel_expenses' => ['label' => 'Travel & expenses', 'covers' => ['hrms.expense_policies']],
                'hrms.assets' => ['label' => 'Employee assets'],
                'hrms.documents' => ['label' => 'Documents & templates', 'covers' => ['hrms.document_templates']],
                'hrms.recruitment' => ['label' => 'Recruitment'],
                'hrms.performance' => ['label' => 'Performance reviews'],
                'hrms.goals' => ['label' => 'Goals & KPIs', 'covers' => ['hrms.kpi']],
                'hrms.feedback_360' => ['label' => '360° feedback'],
                'hrms.pip' => ['label' => 'PIP & probation', 'covers' => ['hrms.probation']],
                'hrms.employee_exits' => ['label' => 'Exits & clearance', 'covers' => ['hrms.exit_policies']],
                'hrms.helpdesk' => ['label' => 'HR helpdesk & knowledge base'],
                'hrms.broadcasts' => ['label' => 'Announcements', 'covers' => ['hrms.communications']],
                'hrms.sop' => ['label' => 'SOPs'],
            ],
        ],

        'accounting' => [
            'label' => 'Accounting',
            'features' => [
                'accounting.chart_of_accounts' => ['label' => 'Chart of accounts', 'covers' => ['accounting.ledger_groups']],
                'accounting.journals' => ['label' => 'Manual journals'],
                'accounting.vouchers' => ['label' => 'Payment, receipt & contra vouchers'],
                'accounting.bank_reconciliation' => ['label' => 'Bank reconciliation'],
                'accounting.budgets' => ['label' => 'Budgeting'],
                'accounting.cost_centers' => ['label' => 'Cost centers'],
                'accounting.exchange_rates' => ['label' => 'Multi-currency'],
                'accounting.gst_returns' => ['label' => 'GST returns'],
                'accounting.periods' => ['label' => 'Period & year-end close', 'covers' => ['accounting.fiscal_years']],
                'accounting.approvals' => ['label' => 'Accounting approvals'],
                'accounting.reports' => ['label' => 'Financial reports'],
                'fixed_assets' => ['label' => 'Fixed asset management'],
            ],
        ],

        'projects' => [
            'label' => 'Projects',
            'features' => [
                'projects.projects' => ['label' => 'Projects & tasks', 'covers' => ['projects.members', 'projects.dashboard', 'projects.tasklists', 'projects.tasks']],
                'projects.milestones' => ['label' => 'Milestones'],
                'projects.timetracking' => ['label' => 'Timesheets'],
                'projects.billing' => ['label' => 'Project billing'],
                'projects.issues' => ['label' => 'Issue tracking'],
                'projects.changerequests' => ['label' => 'Change requests'],
                'projects.documents' => ['label' => 'Project documents'],
                'projects.reviews' => ['label' => 'Project reviews'],
                'projects.reports' => ['label' => 'Project reports'],
            ],
        ],

        'visitor' => [
            'label' => 'Visitor Management',
            'features' => [
                'visitor.passes' => ['label' => 'Visitor passes & check-in', 'covers' => ['visitor.visitors']],
            ],
        ],

    ],

];
