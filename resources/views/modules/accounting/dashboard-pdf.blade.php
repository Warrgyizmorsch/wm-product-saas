<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Accounting Dashboard</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 6px; }
        table.meta td { padding: 1px 10px 1px 0; }
        h2 { font-size: 12px; margin: 16px 0 4px; padding-bottom: 2px; border-bottom: 1px solid #999; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #f1f3f5; text-align: left; padding: 4px 6px; font-size: 9px; text-transform: uppercase; }
        table.data td { padding: 3px 6px; border-bottom: 1px solid #e5e5e5; text-align: left; }
        table.data th.num, table.data td.num { text-align: right; }
        table.data th:first-child, table.data td:first-child { width: 38%; }
        .section { page-break-inside: avoid; }
        .muted { color: #777; }
    </style>
</head>
<body>
    <h1>{{ tenant_branding()['name'] }} — Accounting Dashboard</h1>
    <table class="meta">
        @foreach ($report['meta'] as [$label, $value])
            <tr><td><strong>{{ $label }}</strong></td><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    @foreach ($report['sections'] as $section)
        @php
            // A column is right-aligned (header and cells) when any cell in it is an
            // amount, percentage, ratio or day count — text columns stay left.
            // An empty table has nothing to inspect: every column after the first is an amount.
            $numericColumns = $section['rows'] === []
                ? array_fill_keys(range(1, max(count($section['header']) - 1, 1)), true)
                : [];
            foreach ($section['rows'] as $row) {
                foreach (array_values($row) as $index => $cell) {
                    if (is_int($cell) || is_float($cell) || (is_string($cell) && preg_match('/^(—|-?[\d,.]+(%|x| days| months)?)$/u', $cell))) {
                        $numericColumns[$index] = true;
                    }
                }
            }
        @endphp
        <div class="section">
            <h2>{{ $section['title'] }}</h2>
            <table class="data">
                <thead>
                    <tr>
                        @foreach ($section['header'] as $heading)
                            <th class="{{ isset($numericColumns[$loop->index]) ? 'num' : '' }}">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($section['rows'] as $row)
                        <tr>
                            @foreach (array_values($row) as $cell)
                                <td class="{{ isset($numericColumns[$loop->index]) ? 'num' : '' }}">
                                    {{ is_int($cell) || is_float($cell) ? number_format($cell, 2) : $cell }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($section['header']) }}" class="muted">Nothing to show.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
