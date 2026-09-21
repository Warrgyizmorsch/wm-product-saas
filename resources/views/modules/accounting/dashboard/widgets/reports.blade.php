<x-ui.card title="Reports">
    <div class="row g-2 fs-13">
        @foreach ([
            'Profit & Loss' => 'accounting.reports.profit-loss',
            'Balance Sheet' => 'accounting.reports.balance-sheet',
            'Trial Balance' => 'accounting.reports.trial-balance',
            'Cash Flow' => 'accounting.reports.cash-flow',
            'General Ledger' => 'accounting.reports.general-ledger',
            'Day Book' => 'accounting.reports.day-book',
            'AR Aging' => 'accounting.reports.ar-aging',
            'AP Aging' => 'accounting.reports.ap-aging',
            'GST Summary' => 'accounting.reports.gst-summary',
            'GSTR-1' => 'accounting.reports.gstr1',
            'GSTR-3B' => 'accounting.reports.gstr3b',
            'Budget vs Actual' => 'accounting.reports.budget-vs-actual',
        ] as $label => $routeName)
            <div class="col-lg-2 col-sm-3 col-6">
                <a href="{{ route($routeName) }}" class="d-block text-dark"><i class="feather-file-text me-1 text-muted"></i>{{ $label }}</a>
            </div>
        @endforeach
    </div>
</x-ui.card>
