<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CRM Executive Dashboard Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; line-height: 1.4; margin: 15px; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #0f172a; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .meta td { padding: 3px 12px 3px 0; font-size: 10px; }
        .kpi-grid { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .kpi-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; text-align: left; }
        .kpi-title { font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: bold; }
        .kpi-value { font-size: 16px; font-weight: bold; color: #0f172a; margin: 4px 0; }
        h2 { font-size: 12px; margin: 15px 0 6px; padding-bottom: 4px; border-bottom: 2px solid #cbd5e1; color: #1e293b; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.data th { background: #f1f5f9; text-align: left; padding: 5px 8px; font-size: 9px; text-transform: uppercase; color: #475569; border-bottom: 1px solid #cbd5e1; }
        table.data td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 10px; }
        .num { text-align: right !important; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: bold; }
        .bg-success { background: #dcfce7; color: #166534; }
        .bg-primary { background: #dbeafe; color: #1e40af; }
        .bg-warning { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <h1>{{ tenant_branding()['name'] ?? 'SaaS ERP' }} — CRM Executive Report</h1>
    <table class="meta">
        <tr>
            <td><strong>Period:</strong> {{ $startDate->format('d M Y') }} to {{ $endDate->format('d M Y') }}</td>
            <td><strong>Generated On:</strong> {{ now()->format('d M Y, h:i A') }}</td>
            <td><strong>Scope:</strong> {{ ucfirst($companyScope) }} Company</td>
        </tr>
    </table>

    <h2>Executive Key Performance Indicators (KPIs)</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Metric Name</th>
                <th class="num">Current Period</th>
                <th class="num">Status / Win Rate</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Leads Captured</td>
                <td class="num">{{ number_format($currentLeadsCount) }}</td>
                <td class="num">{{ $leadsGrowth >= 0 ? '+' : '' }}{{ $leadsGrowth }}% Growth</td>
            </tr>
            <tr>
                <td>Active Revenue Pipeline</td>
                <td class="num">{{ active_currency_symbol() }} {{ number_format($pipelineValue, 2) }}</td>
                <td class="num">{{ $openDealsCount }} Open Deals</td>
            </tr>
            <tr>
                <td>Closed Won Revenue</td>
                <td class="num">{{ active_currency_symbol() }} {{ number_format($wonRevenue, 2) }}</td>
                <td class="num">Win Rate: {{ $winRate }}% ({{ $wonCount }} Won)</td>
            </tr>
            <tr>
                <td>Quotations Issued</td>
                <td class="num">{{ active_currency_symbol() }} {{ number_format($totalQuotationValue, 2) }}</td>
                <td class="num">{{ $totalQuotationsCount }} Quotations ({{ $pendingQuotationsCount }} Pending)</td>
            </tr>
            <tr>
                <td>WhatsApp Bot Leads</td>
                <td class="num">{{ number_format($whatsappLeadsCount) }}</td>
                <td class="num">{{ $whatsappQualificationRate }}% Auto-Qualified</td>
            </tr>
        </tbody>
    </table>

    <h2>Sales Funnel Stage Distribution</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Funnel Stage</th>
                <th class="num">Lead Count</th>
                <th class="num">Percentage Share</th>
            </tr>
        </thead>
        <tbody>
            @php $maxF = max(array_values($funnelStages)) ?: 1; @endphp
            @foreach ($funnelStages as $stage => $cnt)
                <tr>
                    <td>{{ $stage }}</td>
                    <td class="num">{{ number_format($cnt) }}</td>
                    <td class="num">{{ round(($cnt / $maxF) * 100) }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (!empty($salesLeaderboard))
        <h2>Sales Reps Leaderboard Performance</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Sales Representative</th>
                    <th class="num">Leads</th>
                    <th class="num">Deals</th>
                    <th class="num">Won Deals</th>
                    <th class="num">Won Revenue</th>
                    <th class="num">Win Rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($salesLeaderboard as $rep)
                    <tr>
                        <td>{{ $rep['name'] }}</td>
                        <td class="num">{{ $rep['leads_count'] }}</td>
                        <td class="num">{{ $rep['deals_count'] }}</td>
                        <td class="num">{{ $rep['won_count'] }}</td>
                        <td class="num">{{ active_currency_symbol() }} {{ number_format($rep['won_revenue'], 2) }}</td>
                        <td class="num">{{ $rep['win_rate'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Top High-Value Open Opportunities</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Deal Number</th>
                <th>Title</th>
                <th>Client / Account</th>
                <th>Stage</th>
                <th class="num">Estimated Value</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($topOpenDeals as $deal)
                <tr>
                    <td>{{ $deal->deal_number }}</td>
                    <td>{{ $deal->title }}</td>
                    <td>{{ $deal->account?->company_name ?: ($deal->contact?->first_name ?: 'Direct Client') }}</td>
                    <td>{{ ucfirst($deal->stage) }}</td>
                    <td class="num">{{ active_currency_symbol() }} {{ number_format($deal->estimated_value, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;">No open deals in this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
