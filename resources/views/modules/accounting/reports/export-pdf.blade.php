<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 12mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #222; }
        .header { margin-bottom: 8px; }
        .tenant { font-size: 15px; font-weight: bold; color: #1e293b; }
        .title { font-size: 12px; font-weight: bold; margin-top: 2px; }
        table.meta td { padding: 1px 10px 1px 0; }
        h2 { font-size: 11px; margin: 14px 0 4px; padding-bottom: 2px; border-bottom: 1px solid #999; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #f1f3f5; text-align: left; padding: 4px 5px; font-size: 8.5px; text-transform: uppercase; }
        table.data td { padding: 3px 5px; border-bottom: 1px solid #e5e5e5; text-align: left; }
        table.data th.num, table.data td.num { text-align: right; }
        table.data tr.footer td { font-weight: bold; background: #f8f9fa; border-top: 1px solid #999; }
        .section { page-break-inside: avoid; }
        .muted { color: #777; }
    </style>
</head>
<body>
    <div class="header">
        <div class="tenant">{{ tenant_branding()['name'] }}</div>
        <div class="title">{{ $report['title'] }}</div>
    </div>
    <table class="meta">
        @foreach ($report['meta'] as [$label, $value])
            <tr><td><strong>{{ $label }}</strong></td><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    @foreach ($report['sections'] as $section)
        @php
            // Right-align a column when any of its cells is an amount, percentage or count.
            $numericColumns = [];
            foreach ([...$section['rows'], ...$section['footer']] as $row) {
                foreach (array_values($row) as $index => $cell) {
                    if (is_int($cell) || is_float($cell) || (is_string($cell) && preg_match('/^-?[\d,.]+%$/', $cell))) {
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
                                <td class="{{ isset($numericColumns[$loop->index]) ? 'num' : '' }}">{{ is_float($cell) ? number_format($cell, 2) : (is_int($cell) ? number_format($cell) : $cell) }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($section['header']) }}" class="muted">Nothing to show.</td></tr>
                    @endforelse
                    @foreach ($section['footer'] as $row)
                        <tr class="footer">
                            @foreach (array_values($row) as $cell)
                                <td class="{{ isset($numericColumns[$loop->index]) ? 'num' : '' }}">{{ is_float($cell) ? number_format($cell, 2) : (is_int($cell) ? number_format($cell) : $cell) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
