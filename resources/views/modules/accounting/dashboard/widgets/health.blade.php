@php
    $ratioRows = [
        ['Gross margin', $ratios['gross_margin'] === null ? '—' : number_format($ratios['gross_margin'], 1) . '%', 'Direct income less cost of sales'],
        ['Net margin', $ratios['net_margin'] === null ? '—' : number_format($ratios['net_margin'], 1) . '%', 'Net profit as % of income'],
        ['Current ratio', $ratios['current_ratio'] === null ? '—' : number_format($ratios['current_ratio'], 2) . 'x', 'Healthy at 1.5x or more'],
        ['Quick ratio', $ratios['quick_ratio'] === null ? '—' : number_format($ratios['quick_ratio'], 2) . 'x', 'Excludes inventory; healthy at 1x or more'],
        ['Working capital', $money($ratios['working_capital']), 'Current assets less current liabilities'],
        ['Days sales outstanding', $ratios['dso'] === null ? '—' : $ratios['dso'] . ' days', 'Open invoices ÷ invoiced this period'],
        ['Days payable outstanding', $ratios['dpo'] === null ? '—' : $ratios['dpo'] . ' days', 'Open bills ÷ billed this period'],
        ['Avg monthly expense', $money($burn['monthly_expense']), 'Last 3 months'],
        ['Cash runway', $burn['runway_months'] === null ? 'Not burning cash' : number_format($burn['runway_months'], 1) . ' months', 'Cash ÷ average net burn'],
    ];
@endphp
<x-ui.card title="Financial Health" bodyClass="p-0" class="accounting-dense mb-3" stretch>
    <x-ui.table>
        <tbody class="fs-13 text-dark">
            @foreach ($ratioRows as [$label, $value, $hint])
                <tr>
                    <td class="ps-4">
                        {{ $label }}
                        <div class="fs-11 text-muted">{{ $hint }}</div>
                    </td>
                    <td class="text-end pe-4 fw-semibold text-nowrap">{{ $value }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
</x-ui.card>
