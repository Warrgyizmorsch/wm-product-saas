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
        table.data th { background: #f1f3f5; text-align: left; padding: 4px; font-size: 9px; text-transform: uppercase; }
        table.data td { padding: 3px 4px; border-bottom: 1px solid #e5e5e5; }
        .num { text-align: right; }
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
        <div class="section">
            <h2>{{ $section['title'] }}</h2>
            <table class="data">
                <thead>
                    <tr>
                        @foreach ($section['header'] as $heading)
                            <th class="{{ $loop->first ? '' : 'num' }}">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($section['rows'] as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td class="{{ is_int($cell) || is_float($cell) || ! $loop->first ? 'num' : '' }}">
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
