<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Services\Dashboard\PartyBalances;

/**
 * Flattens dashboard data into titled tables, shared by the PDF and Excel
 * exports so both always contain the same figures. Amounts stay numeric
 * (floats) so Excel can sum them; percentages and statuses are text.
 */
final class DashboardReport
{
    /**
     * @return array{meta: list<array{0: string, 1: string}>, sections: list<array{title: string, header: list<string>, rows: list<list<mixed>>}>}
     */
    public static function build(array $data): array
    {
        $percent = fn (?float $value) => $value === null ? '—' : number_format($value, 1).'%';
        $times = fn (?float $value) => $value === null ? '—' : number_format($value, 2).'x';
        $days = fn (?int $value) => $value === null ? '—' : $value.' days';

        $meta = [
            ['Period', $data['period']->label()],
            ['Compared with', $data['period']->previousLabel()],
            ['Scope', $data['filters']['consolidated'] ? 'All companies (consolidated)' : 'Selected company'],
        ];
        if ($data['filters']['cost_center']) {
            $meta[] = ['Cost center', $data['filters']['cost_center']->code.' — '.$data['filters']['cost_center']->name.' (income, expense and budgets only)'];
        }
        if (! empty($data['currencyNote'])) {
            $meta[] = ['Amounts', $data['currencyNote']];
        }
        $meta[] = ['Generated', ($data['generatedAt'] ?? now())->format('d M Y H:i')];

        $kpi = fn (string $label, array $row) => [$label, $row['current'], $row['previous'], $percent($row['change'])];

        $sections = [
            [
                'title' => 'Headline figures',
                'header' => ['Metric', 'This period', 'Previous period', 'Change'],
                'rows' => [
                    $kpi('Revenue', $data['kpis']['income']),
                    $kpi('Expenses', $data['kpis']['expense']),
                    $kpi('Net profit', $data['kpis']['net_profit']),
                    ['Cash & bank (as of '.$data['asOf']->format('d M Y').')', $data['cash']['total'], '', ''],
                ],
            ],
            [
                'title' => 'Financial health',
                'header' => ['Metric', 'Value'],
                'rows' => [
                    ['Gross margin', $percent($data['ratios']['gross_margin'])],
                    ['Net margin', $percent($data['ratios']['net_margin'])],
                    ['Current ratio', $times($data['ratios']['current_ratio'])],
                    ['Quick ratio', $times($data['ratios']['quick_ratio'])],
                    ['Working capital', $data['ratios']['working_capital']],
                    ['Days sales outstanding', $days($data['ratios']['dso'])],
                    ['Days payable outstanding', $days($data['ratios']['dpo'])],
                    ['Average monthly expense (3 months)', $data['burn']['monthly_expense']],
                    ['Cash runway', $data['burn']['runway_months'] === null ? 'Not burning cash' : number_format($data['burn']['runway_months'], 1).' months'],
                ],
            ],
        ];

        foreach (['Receivables' => [$data['receivables'], 'Customer'], 'Payables' => [$data['payables'], 'Vendor']] as $title => [$side, $party]) {
            $rows = [];
            foreach (PartyBalances::BUCKETS as $key => $label) {
                $rows[] = [$label, $side['buckets'][$key]];
            }
            $rows[] = ['Total outstanding', $side['total']];
            $rows[] = ['Of which overdue', $side['overdue']];
            $sections[] = ['title' => "{$title} aging", 'header' => ['Bucket', 'Amount'], 'rows' => $rows];
            $sections[] = [
                'title' => "Top {$party}s",
                'header' => [$party, 'Amount'],
                'rows' => array_map(fn (array $row) => [$row['name'], $row['amount']], $side['top']),
            ];
        }

        $sections[] = [
            'title' => $data['forecast']['days'].'-day cash forecast',
            'header' => ['Line', 'Amount'],
            'rows' => [
                ['Cash & bank today', $data['forecast']['opening']],
                ['Receivables due (incl. overdue)', $data['forecast']['inflow']],
                ['Payables due (incl. overdue)', -$data['forecast']['outflow']],
                ['Projected cash', $data['forecast']['closing']],
            ],
        ];

        // A negative net figure is input credit carried forward, not a negative liability.
        $gstLine = fn (string $what, float $amount) => $amount < 0
            ? ["Input credit carried forward ({$what})", abs($amount)]
            : ["Net GST payable ({$what})", $amount];

        $sections[] = [
            'title' => 'GST & TDS',
            'header' => ['Line', 'Amount'],
            'rows' => [
                ["Output GST ({$data['gst']['return_month']})", $data['gst']['output']],
                ["Input tax credit ({$data['gst']['return_month']})", $data['gst']['input']],
                $gstLine($data['gst']['return_month'], $data['gst']['payable']),
                ['GSTR-1 due', $data['gst']['gstr1_due']->format('d M Y')],
                ['GSTR-3B due', $data['gst']['gstr3b_due']->format('d M Y')],
                $gstLine($data['gst']['accruing_month'].' so far', $data['gst']['accruing_payable']),
                ['TDS payable', $data['tdsPayable']],
            ],
        ];

        $sections[] = [
            'title' => 'Expense breakdown',
            'header' => ['Account', 'Amount'],
            'rows' => array_map(fn (array $row) => [$row['label'], $row['amount']], $data['expenseBreakdown']),
        ];

        $sections[] = [
            'title' => 'Budget alerts (80% or more used)',
            'header' => ['Account', 'Cost center', 'Budgeted', 'Actual', 'Used'],
            'rows' => array_map(fn (array $row) => [
                $row['account'], $row['cost_center'] ?? '', $row['budgeted'], $row['actual'], number_format($row['percent'], 1).'%',
            ], $data['budgetAlerts']),
        ];

        $sections[] = [
            'title' => 'Month-end close',
            'header' => ['Check', 'Status', 'Detail'],
            'rows' => array_map(fn (array $item) => [$item['label'], $item['ok'] ? 'Done' : 'Action needed', $item['detail']], array_values($data['checklist'])),
        ];

        $sections[] = [
            'title' => 'Cash & bank balances',
            'header' => ['Account', 'Balance'],
            'rows' => $data['cash']['accounts']->map(fn (array $row) => [$row['account']->code.' '.$row['account']->name, $row['balance']])->all(),
        ];

        return ['meta' => $meta, 'sections' => $sections];
    }
}
