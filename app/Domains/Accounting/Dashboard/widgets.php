<?php

// Dashboard widgets for the common dashboard. See App\Core\Dashboard\WidgetRegistry.

use App\Core\Dashboard\WidgetContext;
use App\Domains\Accounting\Services\AccountingDashboardService;
use App\Domains\Accounting\Services\Dashboard\PartyBalances;
use App\Domains\Accounting\Support\DashboardPeriod;
use Illuminate\Support\Carbon;

// Same cached summary the Accounting dashboard uses, so the numbers always agree with it.
$summary = function (WidgetContext $c): array {
    $service = app(AccountingDashboardService::class);
    $today = Carbon::today();
    $period = $c->period->preset === 'fiscal_year'
        ? DashboardPeriod::resolve(['preset' => 'fiscal_year'], $today, $service->fiscalYearStart($today))
        : DashboardPeriod::resolve(['preset' => 'custom', 'from' => $c->period->from->toDateString(), 'to' => $c->period->to->toDateString()], $today);

    return $service->cachedSummary($c->tenantId, $period, $c->consolidated, $c->costCenterId, $today);
};
$money = fn ($value): string => number_format((float) $value, 2);

// Blocks of the Accounting dashboard page: each keeps the page's own markup (resources/views/modules/accounting/dashboard/widgets).
$block = fn (string $view, array $extra = [], ?Closure $charts = null) => function (WidgetContext $c) use ($summary, $money, $view, $extra, $charts): array {
    $data = $summary($c);

    return [
        'html' => view('modules.accounting.dashboard.widgets.'.$view, $data + $extra + ['money' => $money])->render(),
        'charts' => $charts ? $charts($data) : [],
    ];
};
$page = ['type' => 'html', 'chrome' => false, 'cache' => false, 'dashboards' => ['accounting'], 'module' => 'accounting', 'permission' => 'accounting.reports.view'];
$agingChart = fn (string $side, string $id) => fn (array $s) => $s[$side]['total'] > 0 ? [[
    'id' => $id, 'format' => ['axis' => 0, 'tooltip' => 2],
    'options' => [
        'chart' => ['type' => 'bar', 'height' => 220, 'toolbar' => ['show' => false], 'fontFamily' => 'inherit'],
        'series' => [['name' => 'Outstanding', 'data' => array_values($s[$side]['buckets'])]],
        'xaxis' => ['categories' => array_values(PartyBalances::BUCKETS)],
        'colors' => ['#17c666', '#ffa21d', '#f97316', '#ea4d4d', '#b91c1c'],
        'plotOptions' => ['bar' => ['distributed' => true, 'columnWidth' => '50%', 'borderRadius' => 3]],
        'legend' => ['show' => false],
    ],
]] : [];

return [
    [
        'key' => 'accounting.cash', 'title' => 'Cash & Bank', 'module' => 'accounting', 'permission' => 'accounting.reports.view',
        'type' => 'kpi', 'icon' => 'feather-dollar-sign', 'w' => 3, 'h' => 2,
        'description' => 'Combined cash and bank balance today.',
        'data' => fn (WidgetContext $c): array => ['value' => $money($summary($c)['cash']['total']), 'sub' => null, 'tone' => 'success'],
    ],
    [
        'key' => 'accounting.net_profit', 'title' => 'Net Profit', 'module' => 'accounting', 'permission' => 'accounting.reports.view',
        'type' => 'kpi', 'icon' => 'feather-trending-up', 'w' => 3, 'h' => 2, 'settings' => ['period' => true],
        'description' => 'Income minus expenses for the period.',
        'data' => function (WidgetContext $c) use ($summary, $money): array {
            $pl = $summary($c)['profitAndLoss'];

            return ['value' => $money($pl['net_profit']), 'sub' => 'Income '.$money($pl['income']).' · Expense '.$money($pl['expense']), 'tone' => $pl['net_profit'] >= 0 ? 'success' : 'danger'];
        },
    ],
    [
        'key' => 'accounting.receivable_payable', 'title' => 'Receivables vs Payables', 'module' => 'accounting', 'permission' => 'accounting.reports.view',
        'type' => 'bar', 'icon' => 'feather-bar-chart-2', 'w' => 4, 'h' => 4,
        'description' => 'What customers owe you against what you owe suppliers.',
        'data' => function (WidgetContext $c) use ($summary): array {
            $s = $summary($c);

            return ['labels' => ['Receivables', 'Payables'], 'values' => [round((float) $s['receivables']['total'], 2), round((float) $s['payables']['total'], 2)]];
        },
    ],
    [
        'key' => 'accounting.income_expense_trend', 'title' => 'Income vs Expense Trend', 'module' => 'accounting', 'permission' => 'accounting.reports.view',
        'type' => 'line', 'icon' => 'feather-activity', 'w' => 6, 'h' => 4, 'settings' => ['period' => true],
        'description' => 'Monthly income and expense over the last months, ending with the period.',
        'data' => function (WidgetContext $c) use ($summary): array {
            $trend = $summary($c)['trend'];

            return ['labels' => $trend['labels'], 'series' => [['name' => 'Income', 'data' => $trend['income']], ['name' => 'Expense', 'data' => $trend['expense']]]];
        },
    ],
    [
        'key' => 'accounting.expense_breakdown', 'title' => 'Top Expenses', 'module' => 'accounting', 'permission' => 'accounting.reports.view',
        'type' => 'donut', 'icon' => 'feather-pie-chart', 'w' => 4, 'h' => 4, 'settings' => ['period' => true],
        'description' => 'Where the money went in the period.',
        'data' => function (WidgetContext $c) use ($summary): array {
            $rows = $summary($c)['expenseBreakdown'];

            return ['labels' => array_column($rows, 'label'), 'values' => array_map(fn ($row) => round((float) $row['amount'], 2), $rows)];
        },
    ],

    // ── Blocks of the Accounting dashboard page (offered only there) ──
    $page + ['key' => 'accounting.kpi_revenue', 'title' => 'Revenue', 'icon' => 'feather-trending-up', 'w' => 3, 'h' => 2, 'settings' => ['period' => true],
        'description' => 'Income for the period against the one before.', 'data' => $block('kpi', ['which' => 'revenue'])],
    $page + ['key' => 'accounting.kpi_expenses', 'title' => 'Expenses', 'icon' => 'feather-trending-down', 'w' => 3, 'h' => 2, 'settings' => ['period' => true],
        'description' => 'Expenses for the period against the one before.', 'data' => $block('kpi', ['which' => 'expenses'])],
    $page + ['key' => 'accounting.kpi_net_profit', 'title' => 'Net Profit', 'icon' => 'feather-award', 'w' => 3, 'h' => 2, 'settings' => ['period' => true],
        'description' => 'Profit for the period against the one before.', 'data' => $block('kpi', ['which' => 'net_profit'])],
    $page + ['key' => 'accounting.kpi_cash', 'title' => 'Cash & Bank', 'icon' => 'feather-briefcase', 'w' => 3, 'h' => 2,
        'description' => 'Cash and bank balance with the runway.', 'data' => $block('kpi', ['which' => 'cash'])],
    $page + ['key' => 'accounting.trend_detail', 'title' => 'Income vs Expense (6 months)', 'icon' => 'feather-bar-chart-2', 'w' => 8, 'h' => 7,
        'description' => 'Six months of income and expense, with gross profit.',
        'data' => $block('trend', [], fn (array $s) => array_sum($s['trend']['income']) != 0 || array_sum($s['trend']['expense']) != 0 ? [[
            'id' => 'acc-trend-chart', 'format' => ['axis' => 0, 'tooltip' => 2],
            'options' => [
                'chart' => ['type' => 'bar', 'height' => 300, 'toolbar' => ['show' => false], 'fontFamily' => 'inherit'],
                'series' => [['name' => 'Income', 'data' => $s['trend']['income']], ['name' => 'Expense', 'data' => $s['trend']['expense']]],
                'xaxis' => ['categories' => $s['trend']['labels']],
                'colors' => ['#17c666', '#ea4d4d'],
                'plotOptions' => ['bar' => ['columnWidth' => '45%', 'borderRadius' => 3]],
                'legend' => ['position' => 'top', 'horizontalAlign' => 'right'],
            ],
        ]] : [])],
    $page + ['key' => 'accounting.financial_health', 'title' => 'Financial Health', 'icon' => 'feather-heart', 'w' => 4, 'h' => 7,
        'description' => 'Margins, liquidity ratios, working capital and runway.', 'data' => $block('health')],
    $page + ['key' => 'accounting.receivables_aging', 'title' => 'Receivables Aging', 'icon' => 'feather-arrow-down-left', 'w' => 6, 'h' => 8,
        'description' => 'What customers owe, by how overdue it is.', 'data' => $block('aging', ['which' => 'receivables'], $agingChart('receivables', 'acc-ar-chart'))],
    $page + ['key' => 'accounting.payables_aging', 'title' => 'Payables Aging', 'icon' => 'feather-arrow-up-right', 'w' => 6, 'h' => 8,
        'description' => 'What you owe suppliers, by how overdue it is.', 'data' => $block('aging', ['which' => 'payables'], $agingChart('payables', 'acc-ap-chart'))],
    $page + ['key' => 'accounting.cash_forecast', 'title' => 'Cash Forecast', 'icon' => 'feather-trending-up', 'w' => 6, 'h' => 4,
        'description' => 'Projected cash after the receivables and payables due.', 'data' => $block('forecast')],
    $page + ['key' => 'accounting.expense_mix', 'title' => 'Where the Money Went', 'icon' => 'feather-pie-chart', 'w' => 6, 'h' => 5, 'settings' => ['period' => true],
        'description' => 'Top expense accounts in the period.',
        'data' => $block('expense-mix', [], fn (array $s) => count($s['expenseBreakdown']) > 0 ? [[
            'id' => 'acc-expense-chart', 'format' => ['axis' => 0, 'tooltip' => 2],
            'options' => [
                'chart' => ['type' => 'donut', 'height' => 280, 'fontFamily' => 'inherit'],
                'series' => array_column($s['expenseBreakdown'], 'amount'),
                'labels' => array_column($s['expenseBreakdown'], 'label'),
                'legend' => ['position' => 'bottom'],
            ],
        ]] : [])],
    $page + ['key' => 'accounting.month_end_close', 'title' => 'Month-End Close', 'icon' => 'feather-check-square', 'w' => 7, 'h' => 6,
        'description' => 'The checklist of things to clear before closing the month.', 'data' => $block('close')],
    $page + ['key' => 'accounting.gst_tds', 'title' => 'GST & TDS', 'icon' => 'feather-percent', 'w' => 5, 'h' => 7,
        'description' => 'GST return position, due dates and TDS payable.', 'data' => $block('gst')],
    $page + ['key' => 'accounting.budget_alerts', 'title' => 'Budget Alerts', 'icon' => 'feather-alert-triangle', 'w' => 6, 'h' => 5,
        'description' => 'Budget lines at or over 80% used.', 'data' => $block('budgets')],
    $page + ['key' => 'accounting.bank_reconciliation', 'title' => 'Bank Reconciliation', 'icon' => 'feather-credit-card', 'w' => 6, 'h' => 4,
        'description' => 'Which bank accounts are due for reconciliation.', 'data' => $block('bank')],
    $page + ['key' => 'accounting.recent_journals', 'title' => 'Recent Journals', 'icon' => 'feather-book-open', 'w' => 7, 'h' => 6,
        'description' => 'The latest posted journals.', 'data' => $block('journals')],
    $page + ['key' => 'accounting.cash_balances', 'title' => 'Cash & Bank Balances', 'icon' => 'feather-dollar-sign', 'w' => 5, 'h' => 5,
        'description' => 'Balance of each cash and bank account.', 'data' => $block('balances')],
    $page + ['key' => 'accounting.report_links', 'title' => 'Reports', 'icon' => 'feather-file-text', 'w' => 12, 'h' => 3,
        'description' => 'Shortcuts to every accounting report.', 'data' => $block('reports')],
];
