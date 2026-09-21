<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $displayTitle }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #1e293b;
            background: #fff;
            padding: 10mm 8mm;
        }

        /* ── Header ──────────────────────────────────── */
        .report-header {
            border-bottom: 2px solid #1e293b;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .report-header h1 { font-size: 14pt; font-weight: bold; color: #1e293b; }
        .report-header .meta { font-size: 8pt; color: #64748b; margin-top: 4px; }
        .report-header table { width: 100%; }
        .report-header td.right { text-align: right; font-size: 8pt; color: #64748b; vertical-align: top; }

        /* ── Section heading ──────────────────────────── */
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            color: #fff;
            background-color: #1e293b;
            padding: 4px 8px;
            margin: 12px 0 4px 0;
        }

        /* ── Tables ───────────────────────────────────── */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-bottom: 4px;
        }
        table.data-table thead th {
            background-color: #334155;
            color: #fff;
            padding: 4px 5px;
            text-align: left;
            border: 1px solid #475569;
            font-weight: bold;
            white-space: nowrap;
        }
        table.data-table tbody td {
            padding: 3px 5px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        table.data-table tbody tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        table.data-table .text-right { text-align: right; }
        table.data-table .text-center { text-align: center; }

        /* ── KPI strip ────────────────────────────────── */
        .kpi-strip {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .kpi-strip td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            text-align: center;
            background: #f8fafc;
        }
        .kpi-strip .kpi-label {
            font-size: 7pt;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }
        .kpi-strip .kpi-value {
            font-size: 13pt;
            font-weight: bold;
            color: #1e293b;
        }
        .kpi-strip .kpi-value.success { color: #16a34a; }
        .kpi-strip .kpi-value.danger  { color: #dc2626; }
        .kpi-strip .kpi-value.primary { color: #2563eb; }
        .kpi-strip .kpi-value.warning { color: #d97706; }

        /* ── Badges ───────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 7.5pt;
            font-weight: bold;
        }
        .badge-success { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .badge-primary { background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd; }
        .badge-info    { background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; }
        .badge-warning { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .badge-danger  { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .badge-dark    { background: #f1f5f9; color: #334155; border: 1px solid #94a3b8; }

        /* ── WIP banner ───────────────────────────────── */
        .wip-banner {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 8.5pt;
            margin-bottom: 8px;
            color: #1d4ed8;
        }

        /* ── Page break ───────────────────────────────── */
        .page-break { page-break-after: always; }

        /* ── Footer line ──────────────────────────────── */
        .footer {
            position: fixed;
            bottom: 6mm;
            left: 8mm;
            right: 8mm;
            font-size: 7pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 3px;
        }
        .footer table { width: 100%; }
        .footer td.right { text-align: right; }
    </style>
</head>
<body>

{{-- Fixed footer on every page --}}
<div class="footer">
    <table>
        <tr>
            <td>SaaS ERP — Manufacturing Intelligence</td>
            <td class="right">Generated: {{ now()->format('d M Y, H:i') }}</td>
        </tr>
    </table>
</div>

{{-- ── Report Header ──────────────────────────────────────── --}}
<div class="report-header">
    <table>
        <tr>
            <td>
                <h1>{{ $displayTitle }}</h1>
                <div class="meta">SaaS Enterprise Manufacturing Intelligence &amp; Analytics</div>
            </td>
            <td class="right">
                @if($type !== 'order-detail')
                    Period: {{ $reportData['period_start'] }} → {{ $reportData['period_end'] }}<br>
                @endif
                Generated: {{ now()->format('d M Y, H:i') }}
            </td>
        </tr>
    </table>
</div>

{{-- ════════════════════════════════════════════════════════
     ORDER DETAIL
     ════════════════════════════════════════════════════════ --}}
@if($type === 'order-detail')
    @php
        $order = $reportData['order'];
        $statusBadgeClass = match($order['status']) {
            'completed'   => 'badge-success',
            'in_progress' => 'badge-primary',
            'released'    => 'badge-info',
            'closed'      => 'badge-dark',
            'cancelled'   => 'badge-danger',
            default       => 'badge-dark',
        };
    @endphp

    {{-- Order identity --}}
    <table style="width:100%; margin-bottom:8px;">
        <tr>
            <td>
                <strong style="font-size:12pt;">{{ $order['order_number'] }}</strong>
                <span class="badge {{ $statusBadgeClass }}" style="margin-left:6px;">{{ ucfirst(str_replace('_', ' ', $order['status'])) }}</span>
            </td>
            <td style="text-align:right; font-size:8pt; color:#64748b;">
                Created by {{ $order['created_by'] }}<br>
                Planned: {{ $order['start_date'] }} → {{ $order['end_date'] }}
                @if($order['actual_start']) <br>Actual: {{ $order['actual_start'] }} → {{ $order['actual_end'] ?? 'In Progress' }} @endif
            </td>
        </tr>
        <tr>
            <td colspan="2" style="color:#64748b; font-size:8.5pt; padding-top:2px;">
                {{ $order['product_name'] }} &nbsp;|&nbsp; <span style="font-family:monospace;">{{ $order['product_sku'] }}</span>
            </td>
        </tr>
    </table>

    {{-- KPI Strip --}}
    <table class="kpi-strip">
        <tr>
            <td><div class="kpi-label">Planned Qty</div><div class="kpi-value">{{ number_format($order['planned_qty'], 2) }}</div><div style="font-size:7pt;color:#94a3b8;">{{ $order['uom'] }}</div></td>
            <td><div class="kpi-label">Produced</div><div class="kpi-value success">{{ number_format($order['produced_qty'], 2) }}</div><div style="font-size:7pt;color:#94a3b8;">{{ $order['uom'] }}</div></td>
            <td><div class="kpi-label">Scrapped</div><div class="kpi-value {{ $order['scrapped_qty'] > 0 ? 'danger' : '' }}">{{ number_format($order['scrapped_qty'], 2) }}</div><div style="font-size:7pt;color:#94a3b8;">{{ $order['uom'] }}</div></td>
            <td><div class="kpi-label">Rejected</div><div class="kpi-value {{ $order['rejected_qty'] > 0 ? 'warning' : '' }}">{{ number_format($order['rejected_qty'], 2) }}</div><div style="font-size:7pt;color:#94a3b8;">{{ $order['uom'] }}</div></td>
            <td><div class="kpi-label">Completion</div><div class="kpi-value primary">{{ $order['completion_pct'] }}%</div></td>
            <td><div class="kpi-label">Yield</div><div class="kpi-value {{ $order['yield_pct'] >= 95 ? 'success' : ($order['yield_pct'] >= 80 ? 'warning' : 'danger') }}">{{ $order['yield_pct'] }}%</div></td>
        </tr>
    </table>

    {{-- WIP Banner --}}
    @if(!empty($reportData['wip_locations']))
        <div class="wip-banner">
            &#9679; Current WIP Location:
            @foreach($reportData['wip_locations'] as $wip)
                <strong>{{ $wip['operation'] }}</strong>@if($wip['work_center'] !== '—') @ {{ $wip['work_center'] }} @endif
                &mdash; <strong>{{ number_format($wip['available'], 2) }} units</strong>@if(!$loop->last), @endif
            @endforeach
        </div>
    @endif

    {{-- Cost Estimation vs Actual Breakdown --}}
    @if(!empty($reportData['cost_estimation']))
        @php
            $est = $reportData['cost_estimation'];
            $var = $est['net_variance'] ?? 0;
        @endphp
        <div class="section-title">Cost Estimation vs Actual Breakdown</div>
        <table class="data-table" style="margin-bottom: 10px;">
            <thead>
                <tr>
                    <th>Cost Element</th>
                    <th class="text-right">Estimated (Planned)</th>
                    <th class="text-right">Actual Incurred</th>
                    <th class="text-right">Variance</th>
                    <th class="text-right">Variance Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="font-weight:600;">Material Cost (Direct)</td>
                    <td class="text-right">{{ number_format($est['estimated']['material_cost'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($est['actual']['material_cost'] ?? 0, 2) }}</td>
                    <td class="text-right" style="{{ ($est['variance']['material'] ?? 0) > 0 ? 'color:#dc2626;font-weight:bold' : 'color:#16a34a' }}">
                        {{ ($est['variance']['material'] ?? 0) > 0 ? '+' : '' }}{{ number_format($est['variance']['material'] ?? 0, 2) }}
                    </td>
                    <td class="text-right">{{ ($est['variance']['material'] ?? 0) > 0 ? 'Unfavorable' : 'Favorable' }}</td>
                </tr>
                <tr>
                    <td style="font-weight:600;">Direct Labor Cost</td>
                    <td class="text-right">{{ number_format($est['estimated']['labor_cost'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($est['actual']['labor_cost'] ?? 0, 2) }}</td>
                    <td class="text-right" style="{{ ($est['variance']['labor'] ?? 0) > 0 ? 'color:#dc2626;font-weight:bold' : 'color:#16a34a' }}">
                        {{ ($est['variance']['labor'] ?? 0) > 0 ? '+' : '' }}{{ number_format($est['variance']['labor'] ?? 0, 2) }}
                    </td>
                    <td class="text-right">{{ ($est['variance']['labor'] ?? 0) > 0 ? 'Unfavorable' : 'Favorable' }}</td>
                </tr>
                <tr>
                    <td style="font-weight:600;">Machine / Equipment Cost</td>
                    <td class="text-right">{{ number_format($est['estimated']['machine_cost'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($est['actual']['machine_cost'] ?? 0, 2) }}</td>
                    <td class="text-right" style="{{ ($est['variance']['machine'] ?? 0) > 0 ? 'color:#dc2626;font-weight:bold' : 'color:#16a34a' }}">
                        {{ ($est['variance']['machine'] ?? 0) > 0 ? '+' : '' }}{{ number_format($est['variance']['machine'] ?? 0, 2) }}
                    </td>
                    <td class="text-right">{{ ($est['variance']['machine'] ?? 0) > 0 ? 'Unfavorable' : 'Favorable' }}</td>
                </tr>
                <tr>
                    <td style="font-weight:600;">Factory Overhead</td>
                    <td class="text-right">{{ number_format($est['estimated']['overhead_cost'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($est['actual']['overhead_cost'] ?? 0, 2) }}</td>
                    <td class="text-right" style="{{ ($est['variance']['overhead'] ?? 0) > 0 ? 'color:#dc2626;font-weight:bold' : 'color:#16a34a' }}">
                        {{ ($est['variance']['overhead'] ?? 0) > 0 ? '+' : '' }}{{ number_format($est['variance']['overhead'] ?? 0, 2) }}
                    </td>
                    <td class="text-right">{{ ($est['variance']['overhead'] ?? 0) > 0 ? 'Unfavorable' : 'Favorable' }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background:#f1f5f9; font-weight:bold;">
                    <td>Total Production Cost:</td>
                    <td class="text-right">{{ number_format($est['estimated']['total_cost'] ?? 0, 2) }}</td>
                    <td class="text-right" style="color:#2563eb;">{{ number_format($est['actual']['total_cost'] ?? 0, 2) }}</td>
                    <td class="text-right" style="{{ $var > 0 ? 'color:#dc2626' : ($var < 0 ? 'color:#16a34a' : '') }}">
                        {{ $var > 0 ? '+' : '' }}{{ number_format($var, 2) }}
                    </td>
                    <td class="text-right" style="{{ $var > 0 ? 'color:#dc2626' : ($var < 0 ? 'color:#16a34a' : '') }}">
                        {{ $est['variance_status'] ?? ($var > 0 ? 'OVER BUDGET' : 'UNDER BUDGET') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    @endif

    {{-- Operations --}}
    <div class="section-title">Operation Stages</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Seq</th><th>Operation</th><th>Work Center</th><th>Machine</th><th>Status</th>
                <th class="text-right">Produced</th><th class="text-right">Rejected</th><th class="text-right">Scrapped</th>
                <th class="text-right">Setup<br>Plan/Act (min)</th><th class="text-right">Process<br>Plan/Act (min)</th>
                <th>Started</th><th>Ended</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportData['operations'] as $op)
                @php
                    $opClass = match($op['status']) {
                        'completed'   => 'badge-success',
                        'running'     => 'badge-primary',
                        'paused'      => 'badge-warning',
                        'ready'       => 'badge-info',
                        default       => 'badge-dark',
                    };
                @endphp
                <tr>
                    <td class="text-center" style="font-family:monospace; color:#64748b;">{{ $op['sequence'] }}</td>
                    <td>
                        {{ $op['name'] }}
                        @if($op['is_external']) <span class="badge badge-warning" style="font-size:6.5pt;">Ext</span> @endif
                        @if($op['quality_required']) <span class="badge badge-info" style="font-size:6.5pt;">QC</span> @endif
                    </td>
                    <td>{{ $op['work_center'] }}</td>
                    <td style="color:#64748b;">{{ $op['machine'] }}</td>
                    <td><span class="badge {{ $opClass }}">{{ ucfirst(str_replace('_', ' ', $op['status'])) }}</span></td>
                    <td class="text-right" style="color:#16a34a; font-weight:bold;">{{ number_format($op['qty_produced'], 2) }}</td>
                    <td class="text-right" style="{{ $op['qty_rejected'] > 0 ? 'color:#d97706;font-weight:bold' : 'color:#94a3b8' }}">{{ number_format($op['qty_rejected'], 2) }}</td>
                    <td class="text-right" style="{{ $op['qty_scrapped'] > 0 ? 'color:#dc2626;font-weight:bold' : 'color:#94a3b8' }}">{{ number_format($op['qty_scrapped'], 2) }}</td>
                    <td class="text-right">
                        <span style="color:#94a3b8;">{{ number_format($op['setup_planned'], 0) }}</span> /
                        <span style="{{ $op['setup_actual'] > $op['setup_planned'] && $op['setup_planned'] > 0 ? 'color:#dc2626;font-weight:bold' : '' }}">{{ number_format($op['setup_actual'], 0) }}</span>
                    </td>
                    <td class="text-right">
                        <span style="color:#94a3b8;">{{ number_format($op['process_planned'], 0) }}</span> /
                        <span style="{{ $op['process_actual'] > $op['process_planned'] && $op['process_planned'] > 0 ? 'color:#dc2626;font-weight:bold' : '' }}">{{ number_format($op['process_actual'], 0) }}</span>
                    </td>
                    <td style="font-size:7.5pt; color:#64748b;">{{ $op['actual_start'] ?? '—' }}</td>
                    <td style="font-size:7.5pt; color:#64748b;">{{ $op['actual_end'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center" style="color:#94a3b8; padding:8px;">No operations found.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Materials --}}
    <div class="section-title">Material Consumption & Operation Allocation</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Material</th><th>Consuming Op</th><th>UOM</th>
                <th class="text-right">Planned</th><th class="text-right">Issued</th>
                <th class="text-right">Consumed</th><th class="text-right">Floor WIP</th>
                <th class="text-right">Prog%</th>
                <th class="text-right">Unit Cost</th><th class="text-right">Planned Cost</th><th class="text-right">Consumed Cost</th><th class="text-right">Var Cost</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportData['materials'] as $mat)
                <tr>
                    <td style="font-weight:600;">
                        {{ $mat['material_name'] }}
                        <div style="font-family:monospace; color:#64748b; font-size:7pt;">{{ $mat['material_sku'] }}</div>
                    </td>
                    <td>
                        <span style="font-weight:600;">{{ $mat['operation_name'] ?? 'Intake' }}</span>
                        @if(!empty($mat['work_center']))
                            <div style="color:#64748b; font-size:6.5pt;">{{ $mat['work_center'] }}</div>
                        @endif
                    </td>
                    <td class="text-center">{{ $mat['uom'] }}</td>
                    <td class="text-right">{{ number_format($mat['planned_qty'], 2) }}</td>
                    <td class="text-right" style="color:#2563eb;">{{ number_format($mat['issued_qty'], 2) }}</td>
                    <td class="text-right" style="color:#16a34a; font-weight:bold;">{{ number_format($mat['consumed_qty'] ?? 0, 2) }}</td>
                    <td class="text-right" style="{{ ($mat['floor_balance'] ?? 0) > 0 ? 'color:#d97706; font-weight:bold;' : 'color:#94a3b8;' }}">
                        {{ number_format($mat['floor_balance'] ?? 0, 2) }}
                    </td>
                    <td class="text-right">{{ $mat['consumption_pct'] ?? 0 }}%</td>
                    <td class="text-right" style="color:#64748b;">{{ number_format($mat['unit_cost'], 2) }}</td>
                    <td class="text-right">{{ number_format($mat['planned_cost'], 2) }}</td>
                    <td class="text-right" style="color:#16a34a;">{{ number_format($mat['consumed_cost'] ?? 0, 2) }}</td>
                    <td class="text-right" style="{{ $mat['variance_cost'] > 0 ? 'color:#dc2626;font-weight:bold' : ($mat['variance_cost'] < 0 ? 'color:#16a34a;font-weight:bold' : 'color:#94a3b8') }}">
                        {{ $mat['variance_cost'] > 0 ? '+' : '' }}{{ number_format($mat['variance_cost'], 2) }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center" style="color:#94a3b8; padding:8px;">No material records found.</td></tr>
            @endforelse
        </tbody>
        @if(!empty($reportData['materials']))
            @php $ms = $reportData['material_summary']; $vc = $ms['variance_cost'] ?? 0; @endphp
            <tfoot>
                <tr style="background:#f1f5f9; font-weight:bold;">
                    <td colspan="9" class="text-right" style="border:1px solid #cbd5e1; padding:3px 5px; color:#334155;">Totals:</td>
                    <td class="text-right" style="border:1px solid #cbd5e1; padding:3px 5px;">{{ number_format($ms['total_planned_cost'], 2) }}</td>
                    <td class="text-right" style="border:1px solid #cbd5e1; padding:3px 5px; color:#16a34a;">{{ number_format($ms['total_consumed_cost'] ?? 0, 2) }}</td>
                    <td class="text-right" style="border:1px solid #cbd5e1; padding:3px 5px; {{ $vc > 0 ? 'color:#dc2626' : ($vc < 0 ? 'color:#16a34a' : '') }}">
                        {{ $vc > 0 ? '+' : '' }}{{ number_format($vc, 2) }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>

    {{-- Scrap Events --}}
    <div class="section-title">Scrap Events ({{ count($reportData['scrap_events']) }})</div>
    @if(empty($reportData['scrap_events']))
        <p style="color:#16a34a; font-size:8.5pt; padding:5px 0;">&#10003; No scrap events recorded for this order.</p>
    @else
        <table class="data-table">
            <thead>
                <tr><th>Scrapped Item</th><th>SKU</th><th>Operation</th><th class="text-right">Quantity</th><th>Reason</th><th>Recorded At</th><th>Stock Posted</th></tr>
            </thead>
            <tbody>
                @foreach($reportData['scrap_events'] as $s)
                    <tr>
                        <td style="font-weight:600;">{{ $s['product'] }}</td>
                        <td style="font-family:monospace; color:#64748b;">{{ $s['product_sku'] }}</td>
                        <td style="color:#64748b;">{{ $s['operation'] }}</td>
                        <td class="text-right" style="color:#dc2626; font-weight:bold;">{{ number_format($s['quantity'], 2) }}</td>
                        <td>{{ $s['reason'] }}</td>
                        <td style="color:#64748b; font-size:7.5pt;">{{ $s['recorded_at'] }}</td>
                        <td class="text-center">
                            @if($s['stock_posted'])
                                <span class="badge badge-success">Posted</span>
                            @else
                                <span class="badge badge-warning">Pending</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

{{-- ════════════════════════════════════════════════════════
     ALL OTHER REPORT TYPES (flat single-table)
     ════════════════════════════════════════════════════════ --}}
@elseif($type === 'machine')
    <table class="data-table">
        <thead><tr><th>Machine Code</th><th>Machine Name</th><th class="text-right">OEE %</th><th class="text-right">Availability %</th><th class="text-right">Performance %</th><th class="text-right">Quality %</th><th class="text-right">Total Produced</th><th class="text-right">Downtime (min)</th></tr></thead>
        <tbody>
            @forelse($reportData['data'] as $r)
                <tr>
                    <td style="font-family:monospace;">{{ $r['code'] }}</td><td style="font-weight:600;">{{ $r['name'] }}</td>
                    <td class="text-right" style="font-weight:bold; color:#2563eb;">{{ number_format($r['oee'], 2) }}%</td>
                    <td class="text-right">{{ number_format($r['availability'], 2) }}%</td>
                    <td class="text-right">{{ number_format($r['performance'], 2) }}%</td>
                    <td class="text-right" style="color:#16a34a;">{{ number_format($r['quality'], 2) }}%</td>
                    <td class="text-right">{{ number_format($r['total_produced'], 0) }}</td>
                    <td class="text-right" style="{{ $r['downtime_minutes'] > 0 ? 'color:#dc2626;font-weight:bold' : 'color:#94a3b8' }}">{{ number_format($r['downtime_minutes'], 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center" style="color:#94a3b8; padding:8px;">No records for this period.</td></tr>
            @endforelse
        </tbody>
    </table>

@elseif($type === 'work-center')
    <table class="data-table">
        <thead><tr><th>Code</th><th>Work Center</th><th class="text-right">OEE %</th><th class="text-right">Availability %</th><th class="text-right">Performance %</th><th class="text-right">Quality %</th></tr></thead>
        <tbody>
            @forelse($reportData['data'] as $r)
                <tr>
                    <td style="font-family:monospace;">{{ $r['code'] }}</td><td style="font-weight:600;">{{ $r['name'] }}</td>
                    <td class="text-right" style="font-weight:bold; color:#2563eb;">{{ number_format($r['oee'], 2) }}%</td>
                    <td class="text-right">{{ number_format($r['availability'], 2) }}%</td>
                    <td class="text-right">{{ number_format($r['performance'], 2) }}%</td>
                    <td class="text-right" style="color:#16a34a;">{{ number_format($r['quality'], 2) }}%</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center" style="color:#94a3b8; padding:8px;">No records for this period.</td></tr>
            @endforelse
        </tbody>
    </table>

@elseif($type === 'downtime')
    <table class="data-table">
        <thead><tr><th>Machine</th><th>Category</th><th>Reason</th><th>Started At</th><th>Resolved At</th><th class="text-right">Duration (min)</th></tr></thead>
        <tbody>
            @forelse($reportData['downtimes'] as $d)
                <tr>
                    <td style="font-weight:600;">{{ $d->machine->name ?? '—' }}</td>
                    <td><span class="badge badge-danger">{{ $d->category }}</span></td>
                    <td>{{ $d->reason ?? '—' }}</td>
                    <td style="color:#64748b; font-size:7.5pt;">{{ $d->start_time }}</td>
                    <td style="color:#64748b; font-size:7.5pt;">{{ $d->end_time ?? 'Unresolved' }}</td>
                    <td class="text-right" style="color:#dc2626; font-weight:bold;">{{ number_format($d->duration_minutes ?? 0, 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center" style="color:#94a3b8; padding:8px;">No downtime events.</td></tr>
            @endforelse
        </tbody>
    </table>

@elseif($type === 'production-orders')
    <table class="data-table">
        <thead><tr><th>Order Number</th><th>Product / SKU</th><th>Status</th><th class="text-right">Planned</th><th class="text-right">Produced</th><th class="text-right">Scrapped</th><th class="text-right">Completion%</th><th class="text-right">Yield%</th><th>Start</th><th>End</th></tr></thead>
        <tbody>
            @forelse($reportData['data'] as $r)
                @php
                    $sc = match($r['status']) { 'completed' => 'badge-success', 'in_progress' => 'badge-primary', 'released' => 'badge-info', 'draft' => 'badge-dark', default => 'badge-dark' };
                @endphp
                <tr>
                    <td style="font-weight:bold; font-family:monospace;">{{ $r['order_number'] }}</td>
                    <td>{{ $r['product_name'] }}<br><span style="font-family:monospace; color:#64748b; font-size:7.5pt;">{{ $r['product_sku'] }}</span></td>
                    <td><span class="badge {{ $sc }}">{{ ucfirst(str_replace('_',' ',$r['status'])) }}</span></td>
                    <td class="text-right">{{ number_format($r['planned_qty'], 2) }}</td>
                    <td class="text-right" style="color:#16a34a; font-weight:bold;">{{ number_format($r['produced_qty'], 2) }}</td>
                    <td class="text-right" style="{{ $r['scrapped_qty'] > 0 ? 'color:#dc2626;font-weight:bold' : 'color:#94a3b8' }}">{{ number_format($r['scrapped_qty'], 2) }}</td>
                    <td class="text-right" style="color:#2563eb; font-weight:bold;">{{ $r['completion_pct'] }}%</td>
                    <td class="text-right">{{ $r['yield_pct'] }}%</td>
                    <td style="color:#64748b; font-size:7.5pt;">{{ $r['start_date'] }}</td>
                    <td style="color:#64748b; font-size:7.5pt;">{{ $r['end_date'] }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center" style="color:#94a3b8; padding:8px;">No production orders found.</td></tr>
            @endforelse
        </tbody>
    </table>

@elseif($type === 'material-consumption')
    @if(!empty($reportData['summary']))
        @php
            $sum = $reportData['summary'];
            $varCost = $sum['total_variance_cost'] ?? 0;
        @endphp
        <table class="kpi-strip" style="margin-bottom: 8px;">
            <tr>
                <td><div class="kpi-label">Total Items</div><div class="kpi-value">{{ $sum['items_count'] ?? count($reportData['data']) }}</div></td>
                <td><div class="kpi-label">Planned Cost</div><div class="kpi-value primary">{{ number_format($sum['total_planned_cost'] ?? 0, 2) }}</div></td>
                <td><div class="kpi-label">Issued Store</div><div class="kpi-value">{{ number_format($sum['total_issued_cost'] ?? 0, 2) }}</div></td>
                <td><div class="kpi-label">Actually Consumed</div><div class="kpi-value success">{{ number_format($sum['total_consumed_cost'] ?? 0, 2) }}</div></td>
                <td><div class="kpi-label">Floor WIP Stock</div><div class="kpi-value warning">{{ number_format($sum['total_floor_stock_cost'] ?? 0, 2) }}</div></td>
                <td><div class="kpi-label">Cost Variance</div><div class="kpi-value {{ $varCost > 0 ? 'danger' : ($varCost < 0 ? 'success' : '') }}">{{ $varCost > 0 ? '+' : '' }}{{ number_format($varCost, 2) }}</div></td>
            </tr>
        </table>
    @endif

    <table class="data-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Finished Good</th>
                <th>Consuming Operation</th>
                <th>Material / SKU</th>
                <th>UOM</th>
                <th class="text-right">Planned</th>
                <th class="text-right">Issued</th>
                <th class="text-right">Consumed</th>
                <th class="text-right">Floor WIP</th>
                <th class="text-right">Prog%</th>
                <th class="text-right">Planned Cost</th>
                <th class="text-right">Consumed Cost</th>
                <th class="text-right">Var Cost</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportData['data'] as $r)
                <tr>
                    <td style="font-family:monospace; font-weight:bold;">{{ $r['order_number'] }}</td>
                    <td>{{ $r['finished_good'] }}</td>
                    <td>
                        <span style="font-weight:600;">{{ $r['operation_name'] ?? 'Intake' }}</span>
                        @if(!empty($r['work_center']))
                            <div style="color:#64748b; font-size:6.5pt;">{{ $r['work_center'] }}</div>
                        @endif
                    </td>
                    <td>{{ $r['material_name'] }}<br><span style="font-family:monospace; color:#64748b; font-size:7pt;">{{ $r['material_sku'] }}</span></td>
                    <td class="text-center">{{ $r['uom'] }}</td>
                    <td class="text-right">{{ number_format($r['planned_qty'], 2) }}</td>
                    <td class="text-right" style="color:#2563eb; font-weight:bold;">{{ number_format($r['issued_qty'], 2) }}</td>
                    <td class="text-right" style="color:#16a34a; font-weight:bold;">{{ number_format($r['consumed_qty'] ?? 0, 2) }}</td>
                    <td class="text-right" style="{{ ($r['floor_balance'] ?? 0) > 0 ? 'color:#d97706; font-weight:bold;' : 'color:#94a3b8;' }}">
                        {{ number_format($r['floor_balance'] ?? 0, 2) }}
                    </td>
                    <td class="text-right">{{ $r['consumption_pct'] ?? 0 }}%</td>
                    <td class="text-right">{{ number_format($r['planned_cost'], 2) }}</td>
                    <td class="text-right" style="color:#16a34a;">{{ number_format($r['consumed_cost'] ?? 0, 2) }}</td>
                    <td class="text-right" style="{{ $r['variance_cost'] > 0 ? 'color:#dc2626;font-weight:bold' : ($r['variance_cost'] < 0 ? 'color:#16a34a;font-weight:bold' : 'color:#94a3b8') }}">
                        {{ $r['variance_cost'] > 0 ? '+' : '' }}{{ number_format($r['variance_cost'], 2) }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="13" class="text-center" style="color:#94a3b8; padding:8px;">No material records found.</td></tr>
            @endforelse
        </tbody>
        @if(!empty($reportData['summary']))
            @php $s = $reportData['summary']; $vc = $s['total_variance_cost'] ?? 0; @endphp
            <tfoot>
                <tr style="background:#f1f5f9; font-weight:bold;">
                    <td colspan="10" class="text-right" style="border:1px solid #cbd5e1; padding:3px 5px; color:#334155;">Totals:</td>
                    <td class="text-right" style="border:1px solid #cbd5e1; padding:3px 5px;">{{ number_format($s['total_planned_cost'], 2) }}</td>
                    <td class="text-right" style="border:1px solid #cbd5e1; padding:3px 5px; color:#16a34a;">{{ number_format($s['total_consumed_cost'] ?? 0, 2) }}</td>
                    <td class="text-right" style="border:1px solid #cbd5e1; padding:3px 5px; {{ $vc > 0 ? 'color:#dc2626' : ($vc < 0 ? 'color:#16a34a' : '') }}">
                        {{ $vc > 0 ? '+' : '' }}{{ number_format($vc, 2) }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>

@elseif($type === 'cost-variance')
    <table class="data-table">
        <thead><tr><th>Order</th><th>Product / SKU</th><th>Status</th><th class="text-right">Planned</th><th class="text-right">Mat</th><th class="text-right">Labor</th><th class="text-right">Machine</th><th class="text-right">OH</th><th class="text-right">Adj</th><th class="text-right">Actual Total</th><th class="text-right">Variance</th><th class="text-right">Var%</th></tr></thead>
        <tbody>
            @forelse($reportData['data'] as $r)
                <tr>
                    <td style="font-family:monospace; font-weight:bold;">{{ $r['order_number'] }}</td>
                    <td>{{ $r['product_name'] }}<br><span style="font-family:monospace; color:#64748b; font-size:7.5pt;">{{ $r['product_sku'] }}</span></td>
                    <td><span class="badge badge-dark">{{ ucfirst(str_replace('_',' ',$r['status'])) }}</span></td>
                    <td class="text-right">{{ number_format($r['planned_cost'], 2) }}</td>
                    <td class="text-right">{{ number_format($r['actual_material_cost'], 2) }}</td>
                    <td class="text-right">{{ number_format($r['actual_labor_cost'], 2) }}</td>
                    <td class="text-right">{{ number_format($r['actual_machine_cost'], 2) }}</td>
                    <td class="text-right">{{ number_format($r['actual_overhead_cost'], 2) }}</td>
                    <td class="text-right" style="{{ $r['adjustments'] > 0 ? 'color:#d97706;font-weight:bold' : 'color:#94a3b8' }}">{{ number_format($r['adjustments'], 2) }}</td>
                    <td class="text-right" style="font-weight:bold;">{{ number_format($r['actual_total_cost'], 2) }}</td>
                    <td class="text-right" style="{{ $r['variance_amount'] > 0 ? 'color:#dc2626;font-weight:bold' : ($r['variance_amount'] < 0 ? 'color:#16a34a;font-weight:bold' : 'color:#94a3b8') }}">{{ $r['variance_amount'] > 0 ? '+' : '' }}{{ number_format($r['variance_amount'], 2) }}</td>
                    <td class="text-right" style="{{ $r['variance_pct'] > 0 ? 'color:#dc2626;font-weight:bold' : ($r['variance_pct'] < 0 ? 'color:#16a34a;font-weight:bold' : 'color:#94a3b8') }}">{{ $r['variance_pct'] > 0 ? '+' : '' }}{{ $r['variance_pct'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center" style="color:#94a3b8; padding:8px;">No cost records found.</td></tr>
            @endforelse
        </tbody>
    </table>

@elseif($type === 'sales-order-tracking')
    {{-- KPI Summary Ribbon --}}
    <table style="width:100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="width:16.6%; background:#f8fafc; border:1px solid #cbd5e1; padding:6px; text-align:center;">
                <span style="font-size:7pt; text-transform:uppercase; color:#64748b; display:block;">Total Orders</span>
                <strong style="font-size:11pt; color:#0f172a;">{{ number_format($reportData['total_orders'] ?? 0) }}</strong>
            </td>
            <td style="width:16.6%; background:#f8fafc; border:1px solid #cbd5e1; padding:6px; text-align:center;">
                <span style="font-size:7pt; text-transform:uppercase; color:#64748b; display:block;">Total Ordered Qty</span>
                <strong style="font-size:11pt; color:#2563eb;">{{ number_format($reportData['total_ordered_qty'] ?? 0, 1) }}</strong>
            </td>
            <td style="width:16.6%; background:#f8fafc; border:1px solid #cbd5e1; padding:6px; text-align:center;">
                <span style="font-size:7pt; text-transform:uppercase; color:#64748b; display:block;">Produced (MO)</span>
                <strong style="font-size:11pt; color:#16a34a;">{{ number_format($reportData['total_produced_qty'] ?? 0, 1) }}</strong>
            </td>
            <td style="width:16.6%; background:#f8fafc; border:1px solid #cbd5e1; padding:6px; text-align:center;">
                <span style="font-size:7pt; text-transform:uppercase; color:#64748b; display:block;">Delivered Qty</span>
                <strong style="font-size:11pt; color:#0d9488;">{{ number_format($reportData['total_delivered_qty'] ?? 0, 1) }}</strong>
            </td>
            <td style="width:16.6%; background:#f8fafc; border:1px solid #cbd5e1; padding:6px; text-align:center;">
                <span style="font-size:7pt; text-transform:uppercase; color:#64748b; display:block;">Pending Delivery</span>
                <strong style="font-size:11pt; color:#dc2626;">{{ number_format($reportData['total_pending_qty'] ?? 0, 1) }}</strong>
            </td>
            <td style="width:16.6%; background:#f8fafc; border:1px solid #cbd5e1; padding:6px; text-align:center;">
                <span style="font-size:7pt; text-transform:uppercase; color:#64748b; display:block;">Fulfillment Rate</span>
                <strong style="font-size:11pt; color:#7c3aed;">{{ $reportData['fulfillment_pct'] ?? 0 }}%</strong>
            </td>
        </tr>
    </table>

    <table class="data-table" style="font-size: 6.5pt;">
        <thead>
            <tr>
                <th style="width:20px;">SR</th>
                <th>Sales Person</th>
                <th>SO No / Date</th>
                <th>Customer</th>
                <th>Product Description</th>
                <th class="text-right">SO Qty</th>
                <th>Due Date</th>
                <th>SO Status</th>
                <th>MO No / Date</th>
                <th>Requisition / Indent</th>
                <th>PO / Vendor</th>
                <th class="text-right">MO Done</th>
                <th class="text-right">MO Pend</th>
                <th class="text-right">Delivered</th>
                <th class="text-right">Pending</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportData['data'] as $r)
                @php
                    $moBadge = match($r['mo_color']) { 'success' => 'badge-success', 'warning' => 'badge-warning', default => 'badge-danger' };
                    $delBadge = match($r['delivery_color']) { 'success' => 'badge-success', 'warning' => 'badge-warning', default => 'badge-danger' };
                @endphp
                <tr>
                    <td class="text-center">{{ $r['sr_no'] }}</td>
                    <td>{{ $r['sales_person'] }}</td>
                    <td style="font-weight:bold; font-family:monospace;">
                        {{ $r['sales_order_no'] }}<br>
                        <span style="font-weight:normal; color:#64748b; font-size:6pt;">{{ $r['sales_order_date'] }}</span>
                    </td>
                    <td style="font-weight:600;">{{ $r['customer_name'] }}</td>
                    <td>
                        {{ $r['product_name'] }}<br>
                        <span style="font-family:monospace; color:#64748b; font-size:6pt;">{{ $r['product_sku'] }}</span>
                    </td>
                    <td class="text-right" style="font-weight:bold;">{{ number_format($r['so_qty'], 1) }}</td>
                    <td style="color:#64748b;">{{ $r['customer_delivery_date'] }}</td>
                    <td><span class="badge badge-dark">{{ $r['status'] }}</span></td>
                    <td>
                        @if($r['mo_no'] !== '—')
                            <strong style="font-family:monospace;">{{ $r['mo_no'] }}</strong><br>
                            <span class="badge {{ $moBadge }}">{{ $r['mo_status'] }}</span>
                        @else
                            <span class="badge badge-danger">Not Started</span>
                        @endif
                    </td>
                    <td style="font-size:6pt;">
                        Req: {{ $r['requisition_no'] }}<br>
                        Indent: {{ $r['indent_no'] }}
                    </td>
                    <td style="font-size:6pt;">
                        PO: {{ $r['po_no'] }}<br>
                        {{ $r['supplier_name'] }}
                    </td>
                    <td class="text-right" style="color:#16a34a; font-weight:bold;">{{ number_format($r['mo_done_qty'], 1) }}</td>
                    <td class="text-right" style="{{ $r['mo_pending_qty'] > 0 ? 'color:#dc2626; font-weight:bold;' : 'color:#64748b;' }}">{{ number_format($r['mo_pending_qty'], 1) }}</td>
                    <td class="text-right" style="font-weight:bold; color:#0d9488;">{{ number_format($r['delivered_qty'], 1) }}</td>
                    <td class="text-right" style="{{ $r['pending_qty'] > 0 ? 'color:#dc2626; font-weight:bold;' : 'color:#16a34a;' }}">
                        <span class="badge {{ $delBadge }}">{{ number_format($r['pending_qty'], 1) }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="15" class="text-center" style="color:#94a3b8; padding:8px;">No sales orders found for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
@endif

</body>
</html>
