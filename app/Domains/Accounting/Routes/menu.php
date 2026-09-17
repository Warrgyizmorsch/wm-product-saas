<?php

// Accounting sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'finance', 'order' => 10,
        'label' => 'Accounting', 'icon' => 'feather-credit-card',
        'children' => [
            ['label' => 'Dashboard', 'route' => 'accounting.dashboard', 'permission' => 'accounting.reports.view'],
            ['label' => 'Chart of Accounts', 'route' => 'accounting.chart-of-accounts.index', 'permission' => 'accounting.chart_of_accounts.view'],
            ['label' => 'Cost Centers', 'route' => 'accounting.cost-centers.index', 'permission' => 'accounting.cost_centers.view'],
            // Fixed Asset Register is gated by the HRMS asset-custody permission
            // server-side (AssetRegisterController uses HRMS's AssetPolicy), not a
            // fixed_assets.* permission — mirrors the actual authorize() check.
            ['label' => 'Fixed Asset Register', 'route' => 'accounting.fixed-assets.index', 'permission' => 'hrms.assets.view'],
            ['label' => 'Asset Categories', 'route' => 'accounting.fixed-assets.categories.index', 'permission' => 'fixed_assets.categories.view'],
            ['label' => 'Depreciation', 'route' => 'accounting.fixed-assets.depreciation.index', 'permission' => 'fixed_assets.depreciation.view'],
            ['label' => 'Asset Disposals', 'route' => 'accounting.fixed-assets.disposals.index', 'permission' => 'fixed_assets.disposal.view'],
            // No dedicated .view permission exists for these two — their viewAny reuses .create.
            ['label' => 'Asset Write-offs', 'route' => 'accounting.fixed-assets.write-offs.index', 'permission' => 'fixed_assets.writeoff.create'],
            ['label' => 'Asset Revaluations', 'route' => 'accounting.fixed-assets.revaluations.index', 'permission' => 'fixed_assets.revaluation.create'],
            ['label' => 'Budgets', 'route' => 'accounting.budgets.index', 'permission' => 'accounting.budgets.view'],
            ['label' => 'Journals', 'route' => 'accounting.journals.index', 'permission' => 'accounting.journals.view'],
            ['label' => 'Posting Failures', 'route' => 'accounting.posting-failures.index', 'permission' => 'accounting.journals.view'],
            ['label' => 'Payment Vouchers', 'route' => 'accounting.vouchers.payment.index', 'permission' => 'accounting.vouchers.payment.view'],
            ['label' => 'Receipt Vouchers', 'route' => 'accounting.vouchers.receipt.index', 'permission' => 'accounting.vouchers.receipt.view'],
            ['label' => 'Contra Vouchers', 'route' => 'accounting.vouchers.contra.index', 'permission' => 'accounting.vouchers.contra.view'],
            ['label' => 'Credit Notes', 'route' => 'accounting.vouchers.credit_note.index', 'permission' => 'accounting.vouchers.credit_note.view'],
            ['label' => 'Debit Notes', 'route' => 'accounting.vouchers.debit_note.index', 'permission' => 'accounting.vouchers.debit_note.view'],
            ['label' => 'Bank Reconciliation', 'route' => 'accounting.bank-reconciliation.index', 'permission' => 'accounting.bank_reconciliation.view'],
            ['label' => 'Fiscal Years & Periods', 'route' => 'accounting.fiscal-years.index', 'permission' => 'accounting.fiscal_years.view'],
            ['label' => 'Tax Rates', 'route' => 'accounting.tax-rates.index', 'permission' => 'accounting.tax_rates.view'],
            ['label' => 'Exchange Rates', 'route' => 'accounting.exchange-rates.index', 'permission' => 'accounting.exchange_rates.view'],
            ['label' => 'Day Book', 'route' => 'accounting.reports.day-book', 'permission' => 'accounting.reports.view'],
            ['label' => 'Vouchers by Staff', 'route' => 'accounting.reports.vouchers-by-staff', 'permission' => 'accounting.reports.view'],
            ['label' => 'Trial Balance', 'route' => 'accounting.reports.trial-balance', 'permission' => 'accounting.reports.view'],
            ['label' => 'General Ledger', 'route' => 'accounting.reports.general-ledger', 'permission' => 'accounting.reports.view'],
            ['label' => 'Party Ledger', 'route' => 'accounting.reports.party-ledger', 'permission' => 'accounting.reports.view'],
            ['label' => 'Balance Sheet', 'route' => 'accounting.reports.balance-sheet', 'permission' => 'accounting.reports.view'],
            ['label' => 'Profit & Loss', 'route' => 'accounting.reports.profit-loss', 'permission' => 'accounting.reports.view'],
            ['label' => 'AR Aging', 'route' => 'accounting.reports.ar-aging', 'permission' => 'accounting.reports.view'],
            ['label' => 'AP Aging', 'route' => 'accounting.reports.ap-aging', 'permission' => 'accounting.reports.view'],
            ['label' => 'Cash Flow', 'route' => 'accounting.reports.cash-flow', 'permission' => 'accounting.reports.view'],
            ['label' => 'GST Summary', 'route' => 'accounting.reports.gst-summary', 'permission' => 'accounting.reports.view'],
            ['label' => 'GSTR-1', 'route' => 'accounting.reports.gstr1', 'permission' => 'accounting.reports.view'],
            ['label' => 'GSTR-3B', 'route' => 'accounting.reports.gstr3b', 'permission' => 'accounting.reports.view'],
            ['label' => 'Audit Trail', 'route' => 'accounting.reports.audit-trail', 'permission' => 'accounting.reports.view'],
            // Budget vs Actual is the one report that checks accounting.budgets.view, not accounting.reports.view.
            ['label' => 'Budget vs Actual', 'route' => 'accounting.reports.budget-vs-actual', 'permission' => 'accounting.budgets.view'],
        ],
    ],
    [
        'section' => 'finance', 'order' => 20,
        'label' => 'Reports & BI', 'icon' => 'feather-bar-chart-2',
        'children' => [
            ['label' => 'Financials'],
            ['label' => 'Sales Analytics'],
            ['label' => 'Inventory Aging'],
            ['label' => 'Payroll Summary'],
        ],
    ],
];
