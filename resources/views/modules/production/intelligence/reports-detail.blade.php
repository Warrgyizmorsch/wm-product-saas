@php
    $reportTitles = [
        'machine'              => __('production.machine_performance_report') ?? 'Machine Performance & OEE Report',
        'work-center'          => __('production.work_center_report') ?? 'Work Center Performance & OEE Report',
        'downtime'             => __('production.downtime_breakdown_report') ?? 'Downtime Breakdown & Events Report',
        'production-orders'    => 'Production Order Summary & Output Report',
        'material-consumption' => 'Material Consumption & Variance Report',
        'cost-variance'        => 'Production Cost & Variance Report',
    ];
    $displayTitle = $reportTitles[$type] ?? (ucwords(str_replace('-', ' ', $type)) . ' Report');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $displayTitle }} | SaaS ERP</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/erp.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/production.css') }}">
    
    <style>
        :root {
            --bs-body-font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        body {
            background-color: #f1f5f9;
            font-family: var(--bs-body-font-family);
            color: #1e293b;
            -webkit-font-smoothing: antialiased;
        }
        .report-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }
        .kpi-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 16px;
            transition: transform 0.15s ease;
        }
        .kpi-box:hover {
            border-color: #cbd5e1;
        }
        
        /* Clear, crisp table borders */
        .report-table {
            width: 100%;
            border: 1px solid #cbd5e1 !important;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 6px;
            overflow: hidden;
            background-color: #ffffff;
        }
        .report-table thead th {
            background-color: #f8fafc !important;
            color: #334155 !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            border-bottom: 2px solid #cbd5e1 !important;
            border-right: 1px solid #e2e8f0 !important;
            padding: 10px 12px !important;
            vertical-align: middle !important;
            white-space: nowrap !important;
        }
        .report-table thead th:last-child {
            border-right: none !important;
        }
        .report-table tbody td {
            border-bottom: 1px solid #e2e8f0 !important;
            border-right: 1px solid #f1f5f9 !important;
            padding: 9px 12px !important;
            font-size: 13px !important;
            color: #1e293b !important;
            vertical-align: middle !important;
        }
        .report-table tbody td:last-child {
            border-right: none !important;
        }
        .report-table tbody tr:hover td {
            background-color: #f8fafc !important;
        }
        .report-table tbody tr:last-child td {
            border-bottom: none !important;
        }

        /* Print Media Styles */
        @media print {
            @page {
                size: landscape;
                margin: 10mm 8mm;
            }
            body {
                background-color: #ffffff !important;
                color: #000000 !important;
                font-size: 9pt !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .container {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .report-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .report-table {
                width: 100% !important;
                border: 1px solid #000000 !important;
                border-collapse: collapse !important;
            }
            .report-table thead th, .report-table tbody td {
                border: 1px solid #475569 !important;
                padding: 5px 8px !important;
                font-size: 8.5pt !important;
                color: #000000 !important;
            }
            .report-table thead th {
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .kpi-box {
                border: 1px solid #64748b !important;
                padding: 6px 10px !important;
                background: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .badge {
                border: 1px solid #475569 !important;
                color: #000000 !important;
                background: transparent !important;
            }
            a {
                text-decoration: none !important;
                color: #000000 !important;
            }
        }
    </style>
    @stack('styles')
</head>
<body class="py-4">
    <div class="container-fluid px-lg-5 px-3">
        {{-- Top Action Controls --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 no-print gap-2">
            <div>
                <x-ui.button href="javascript:window.close();" variant="light"  icon="feather-x" class="border shadow-sm">
                    {{ __('production.close_window') ?? 'Close Window' }}
                </x-ui.button>
            </div>
            <div class="d-flex align-items-center gap-2">
                <!-- Custom Filter Component (just like used in BOM) -->
                <form method="GET" action="{{ route('production.intelligence.reports.show', $type) }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter')"  offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('production.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.date_start') }}</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="date_start" :value="request('date_start', $reportData['period_start'])" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.date_end') }}</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="date_end" :value="request('date_end', $reportData['period_end'])" />
                        </div>

                        {{-- Preserve existing active filters --}}
                        @if(request('order_id'))
                            <input type="hidden" name="order_id" value="{{ request('order_id') }}">
                        @endif
                        @if(request('product_id'))
                            <input type="hidden" name="product_id" value="{{ request('product_id') }}">
                        @endif
                        @if(request('material_id'))
                            <input type="hidden" name="material_id" value="{{ request('material_id') }}">
                        @endif
                        @if(request('machine_id'))
                            <input type="hidden" name="machine_id" value="{{ request('machine_id') }}">
                        @endif
                        @if(request('work_center_id'))
                            <input type="hidden" name="work_center_id" value="{{ request('work_center_id') }}">
                        @endif
                        @if(request('status'))
                            <input type="hidden" name="status" value="{{ request('status') }}">
                        @endif

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <x-ui.button href="{{ route('production.intelligence.reports.show', $type) }}" variant="light"  class="border">
                                {{ __('production.reset') }}
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary" >
                                {{ __('production.apply_filters') }}
                            </x-ui.button>
                        </div>
                    </x-ui.filter>
                </form>

                <x-ui.button href="{{ route('production.intelligence.reports.export', array_merge(['type' => $type], request()->all())) }}" variant="light"  icon="feather-download" class="border shadow-sm">
                    {{ __('production.export_csv') ?? 'Export CSV' }}
                </x-ui.button>

                <x-ui.button onclick="window.print();" variant="primary"  icon="feather-printer" class="shadow-sm">
                    {{ __('production.print_report') ?? 'Print Report' }}
                </x-ui.button>
            </div>
        </div>

        {{-- Report Document Card --}}
        <div class="report-card p-4 p-md-5">
            {{-- Document Header --}}
            <div class="row mb-4 pb-3 border-bottom align-items-center">
                <div class="col-sm-7 col-12 mb-3 mb-sm-0">
                    <h2 class="fw-bold text-dark mb-1">{{ $displayTitle }}</h2>
                    <p class="text-muted fs-13 mb-0">SaaS Enterprise Manufacturing Intelligence & Analytics</p>
                </div>
                <div class="col-sm-5 col-12 text-sm-end text-start">
                    <div class="text-muted fs-12 mb-1">
                        {{ __('production.period_start') ?? 'Period Start' }}: <strong class="text-dark">{{ $reportData['period_start'] }}</strong>
                    </div>
                    <div class="text-muted fs-12 mb-1">
                        {{ __('production.period_end') ?? 'Period End' }}: <strong class="text-dark">{{ $reportData['period_end'] }}</strong>
                    </div>
                    <div class="text-muted fs-12">
                        {{ __('production.generated_at') ?? 'Generated' }}: <strong class="text-dark">{{ now()->toDateTimeString() }}</strong>
                    </div>
                </div>
            </div>

            {{-- Table representation of report data --}}
            @if($type === 'machine')
                <div class="table-responsive">
                    <table class="report-table align-middle">
                        <thead>
                            <tr>
                                <th>Machine Code</th>
                                <th>Machine Name</th>
                                <th>OEE %</th>
                                <th>Availability %</th>
                                <th>Performance %</th>
                                <th>Quality %</th>
                                <th class="text-end">{{ __('production.total_units') }}</th>
                                <th class="text-end">{{ __('production.todays_downtime') }} (min)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportData['data'] as $row)
                                <tr>
                                    <td class="fw-bold font-monospace">{{ $row['code'] }}</td>
                                    <td class="fw-semibold text-dark">{{ $row['name'] }}</td>
                                    <td class="text-primary fw-bold">{{ number_format($row['oee'], 2) }}%</td>
                                    <td>{{ number_format($row['availability'], 2) }}%</td>
                                    <td>{{ number_format($row['performance'], 2) }}%</td>
                                    <td class="text-success">{{ number_format($row['quality'], 2) }}%</td>
                                    <td class="text-end fw-semibold">{{ number_format($row['total_produced'], 0) }}</td>
                                    <td class="text-end text-danger fw-semibold">{{ number_format($row['downtime_minutes'], 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No machine performance records found for the selected period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif($type === 'work-center')
                <div class="table-responsive">
                    <table class="report-table align-middle">
                        <thead>
                            <tr>
                                <th>Work Center Code</th>
                                <th>Work Center Name</th>
                                <th>OEE %</th>
                                <th>Availability %</th>
                                <th>Performance %</th>
                                <th>Quality %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportData['data'] as $row)
                                <tr>
                                    <td class="fw-bold font-monospace">{{ $row['code'] }}</td>
                                    <td class="fw-semibold text-dark">{{ $row['name'] }}</td>
                                    <td class="text-primary fw-bold">{{ number_format($row['oee'], 2) }}%</td>
                                    <td>{{ number_format($row['availability'], 2) }}%</td>
                                    <td>{{ number_format($row['performance'], 2) }}%</td>
                                    <td class="text-success">{{ number_format($row['quality'], 2) }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No work center performance records found for the selected period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif($type === 'downtime')
                <h5 class="fw-bold text-dark mb-3">{{ __('production.downtime_events_log') }}</h5>
                <div class="table-responsive mb-4">
                    <table class="report-table align-middle">
                        <thead>
                            <tr>
                                <th>Machine</th>
                                <th>Downtime Category</th>
                                <th>{{ __('production.reason') }}</th>
                                <th>Started At</th>
                                <th>Resolved At</th>
                                <th class="text-end">Duration (min)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportData['downtimes'] as $d)
                                <tr>
                                    <td class="fw-bold text-dark">{{ $d->machine->name ?? '—' }}</td>
                                    <td><span class="badge bg-soft-danger text-danger border border-danger-subtle">{{ $d->category }}</span></td>
                                    <td>{{ $d->reason ?? '—' }}</td>
                                    <td class="text-muted fs-12">{{ $d->start_time }}</td>
                                    <td class="text-muted fs-12">{{ $d->end_time ?? 'Unresolved' }}</td>
                                    <td class="text-end text-danger fw-bold">{{ number_format($d->duration_minutes ?? 0, 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No downtime events found for the period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h5 class="fw-bold text-dark mt-4 mb-3">{{ __('production.category_breakdown_summary') }}</h5>
                <div class="table-responsive">
                    <table class="report-table align-middle">
                        <thead>
                            <tr>
                                <th>Downtime Category</th>
                                <th class="text-end">Total Events Count</th>
                                <th class="text-end">Total Accumulated Duration (min)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportData['category_summary'] as $sum)
                                <tr>
                                    <td class="fw-bold text-dark">{{ $sum->category }}</td>
                                    <td class="text-end fw-semibold">{{ $sum->total_events }}</td>
                                    <td class="text-end text-danger fw-bold">{{ number_format($sum->total_duration, 0) }} mins</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No category breakdowns recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif($type === 'production-orders')
                {{-- Summary KPI Cards --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Total Orders</div>
                            <div class="fs-18 fw-bold text-dark mt-1">{{ number_format($reportData['summary']['total_orders'] ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Planned Volume</div>
                            <div class="fs-18 fw-bold text-primary mt-1">{{ number_format($reportData['summary']['total_planned_qty'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Produced Volume</div>
                            <div class="fs-18 fw-bold text-success mt-1">{{ number_format($reportData['summary']['total_produced_qty'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Scrapped Volume</div>
                            <div class="fs-18 fw-bold text-danger mt-1">{{ number_format($reportData['summary']['total_scrapped_qty'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Overall Completion</div>
                            <div class="fs-18 fw-bold text-info mt-1">{{ number_format($reportData['summary']['overall_completion_pct'] ?? 0, 1) }}%</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Overall Yield</div>
                            <div class="fs-18 fw-bold text-dark mt-1">{{ number_format($reportData['summary']['overall_yield_pct'] ?? 0, 1) }}%</div>
                        </div>
                    </div>
                </div>

                {{-- Tabular Results --}}
                <div class="table-responsive">
                    <table class="report-table align-middle">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Product / SKU</th>
                                <th>Status</th>
                                <th class="text-end">Planned Qty</th>
                                <th class="text-end">Produced Qty</th>
                                <th class="text-end">Scrapped Qty</th>
                                <th>Completion %</th>
                                <th>Yield %</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportData['data'] as $row)
                                <tr>
                                    <td class="fw-bold">
                                        <a href="{{ route('production.orders.show', $row['id']) }}" class="text-primary text-decoration-none" target="_blank">
                                            {{ $row['order_number'] }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $row['product_name'] }}</div>
                                        <small class="text-muted font-monospace">{{ $row['product_sku'] }}</small>
                                    </td>
                                    <td>
                                        @php
                                            $badgeClass = match($row['status']) {
                                                'completed' => 'bg-soft-success text-success border border-success-subtle',
                                                'in_progress' => 'bg-soft-primary text-primary border border-primary-subtle',
                                                'released' => 'bg-soft-info text-info border border-info-subtle',
                                                'draft' => 'bg-soft-secondary text-secondary border border-secondary-subtle',
                                                default => 'bg-soft-dark text-dark border',
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }}">{{ ucfirst(str_replace('_', ' ', $row['status'])) }}</span>
                                    </td>
                                    <td class="text-end fw-semibold">{{ number_format($row['planned_qty'], 2) }}</td>
                                    <td class="text-end text-success fw-bold">{{ number_format($row['produced_qty'], 2) }}</td>
                                    <td class="text-end {{ $row['scrapped_qty'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                                        {{ number_format($row['scrapped_qty'], 2) }}
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar bg-success" style="width: {{ $row['completion_pct'] }}%;"></div>
                                            </div>
                                            <span class="fs-12 fw-semibold">{{ $row['completion_pct'] }}%</span>
                                        </div>
                                    </td>
                                    <td class="fw-semibold text-dark">{{ $row['yield_pct'] }}%</td>
                                    <td class="text-muted fs-12">{{ $row['start_date'] }}</td>
                                    <td class="text-muted fs-12">{{ $row['end_date'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">No production orders found for the selected criteria.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif($type === 'material-consumption')
                {{-- Summary KPI Cards --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Total Line Items</div>
                            <div class="fs-18 fw-bold text-dark mt-1">{{ number_format($reportData['summary']['total_items'] ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Total Planned Cost</div>
                            <div class="fs-18 fw-bold text-primary mt-1">{{ number_format($reportData['summary']['total_planned_cost'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Total Issued Cost</div>
                            <div class="fs-18 fw-bold text-success mt-1">{{ number_format($reportData['summary']['total_issued_cost'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Total Cost Variance</div>
                            @php $totVar = $reportData['summary']['total_variance_cost'] ?? 0; @endphp
                            <div class="fs-18 fw-bold {{ $totVar > 0 ? 'text-danger' : ($totVar < 0 ? 'text-success' : 'text-dark') }} mt-1">
                                {{ $totVar > 0 ? '+' : '' }}{{ number_format($totVar, 2) }}
                            </div>
                        </div>
                    </div>
                </div>

                @if(!empty($reportData['summary']['uom_groups']))
                    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                        <span class="text-muted fs-12 fw-semibold"><i class="feather-box me-1"></i> UOM Totals:</span>
                        @foreach($reportData['summary']['uom_groups'] as $uom => $uomData)
                            <span class="badge bg-white border text-dark fs-12 px-2 py-1 shadow-sm">
                                <strong>{{ $uom }}:</strong> Planned {{ number_format($uomData['planned'], 2) }} | Issued {{ number_format($uomData['issued'], 2) }} 
                                <span class="{{ $uomData['variance'] > 0 ? 'text-danger fw-bold' : ($uomData['variance'] < 0 ? 'text-success fw-bold' : 'text-muted') }}">
                                    ({{ $uomData['variance'] > 0 ? '+' : '' }}{{ number_format($uomData['variance'], 2) }})
                                </span>
                            </span>
                        @endforeach
                    </div>
                @endif

                {{-- Tabular Results --}}
                <div class="table-responsive">
                    <table class="report-table align-middle">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Finished Good</th>
                                <th>Material / Component</th>
                                <th>UOM</th>
                                <th class="text-end">Planned Qty</th>
                                <th class="text-end">Issued Qty</th>
                                <th class="text-end">Variance Qty</th>
                                <th class="text-end">Variance %</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Planned Cost</th>
                                <th class="text-end">Issued Cost</th>
                                <th class="text-end">Variance Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportData['data'] as $row)
                                <tr>
                                    <td class="fw-bold">
                                        <a href="{{ route('production.orders.show', $row['order_id']) }}" class="text-primary text-decoration-none" target="_blank">
                                            {{ $row['order_number'] }}
                                        </a>
                                    </td>
                                    <td class="fw-semibold text-dark">{{ $row['finished_good'] }}</td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $row['material_name'] }}</div>
                                        <small class="text-muted font-monospace">{{ $row['material_sku'] }}</small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">{{ $row['uom'] }}</span></td>
                                    <td class="text-end fw-semibold">{{ number_format($row['planned_qty'], 2) }}</td>
                                    <td class="text-end text-primary fw-bold">{{ number_format($row['issued_qty'], 2) }}</td>
                                    <td class="text-end {{ $row['variance_qty'] > 0 ? 'text-danger fw-bold' : ($row['variance_qty'] < 0 ? 'text-success fw-bold' : 'text-muted') }}">
                                        {{ $row['variance_qty'] > 0 ? '+' : '' }}{{ number_format($row['variance_qty'], 2) }}
                                    </td>
                                    <td class="text-end {{ $row['variance_pct'] > 0 ? 'text-danger' : ($row['variance_pct'] < 0 ? 'text-success' : 'text-muted') }}">
                                        {{ $row['variance_pct'] > 0 ? '+' : '' }}{{ $row['variance_pct'] }}%
                                    </td>
                                    <td class="text-end">{{ number_format($row['unit_cost'], 2) }}</td>
                                    <td class="text-end">{{ number_format($row['planned_cost'], 2) }}</td>
                                    <td class="text-end">{{ number_format($row['issued_cost'], 2) }}</td>
                                    <td class="text-end {{ $row['variance_cost'] > 0 ? 'text-danger fw-bold' : ($row['variance_cost'] < 0 ? 'text-success fw-bold' : 'text-muted') }}">
                                        {{ $row['variance_cost'] > 0 ? '+' : '' }}{{ number_format($row['variance_cost'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-4">No material reservations or consumption records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif($type === 'cost-variance')
                {{-- Summary KPI Cards --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Total Orders</div>
                            <div class="fs-18 fw-bold text-dark mt-1">{{ number_format($reportData['summary']['total_orders'] ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Total Planned Cost</div>
                            <div class="fs-18 fw-bold text-primary mt-1">{{ number_format($reportData['summary']['total_planned_cost'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Total Actual Cost</div>
                            <div class="fs-18 fw-bold text-success mt-1">{{ number_format($reportData['summary']['total_actual_cost'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Total Cost Variance</div>
                            @php $costVar = $reportData['summary']['total_variance'] ?? 0; @endphp
                            <div class="fs-18 fw-bold {{ $costVar > 0 ? 'text-danger' : ($costVar < 0 ? 'text-success' : 'text-dark') }} mt-1">
                                {{ $costVar > 0 ? '+' : '' }}{{ number_format($costVar, 2) }}
                                <span class="fs-12 fw-normal">({{ $reportData['summary']['overall_variance_pct'] ?? 0 }}%)</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Component Totals Breakdown --}}
                <div class="card bg-light border p-3 mb-4">
                    <div class="row text-center fs-13 g-2">
                        <div class="col-md-3 col-6 border-end">
                            <span class="text-muted d-block fs-11 text-uppercase fw-semibold">Material (Plan / Act)</span>
                            <strong>{{ number_format($reportData['summary']['material_planned'] ?? 0, 2) }}</strong> / 
                            <strong class="text-primary">{{ number_format($reportData['summary']['material_actual'] ?? 0, 2) }}</strong>
                        </div>
                        <div class="col-md-3 col-6 border-end">
                            <span class="text-muted d-block fs-11 text-uppercase fw-semibold">Labor (Plan / Act)</span>
                            <strong>{{ number_format($reportData['summary']['labor_planned'] ?? 0, 2) }}</strong> / 
                            <strong class="text-primary">{{ number_format($reportData['summary']['labor_actual'] ?? 0, 2) }}</strong>
                        </div>
                        <div class="col-md-3 col-6 border-end">
                            <span class="text-muted d-block fs-11 text-uppercase fw-semibold">Machine (Plan / Act)</span>
                            <strong>{{ number_format($reportData['summary']['machine_planned'] ?? 0, 2) }}</strong> / 
                            <strong class="text-primary">{{ number_format($reportData['summary']['machine_actual'] ?? 0, 2) }}</strong>
                        </div>
                        <div class="col-md-3 col-6">
                            <span class="text-muted d-block fs-11 text-uppercase fw-semibold">Overhead / Adjustments</span>
                            <strong>{{ number_format($reportData['summary']['overhead_actual'] ?? 0, 2) }}</strong> / 
                            <span class="text-muted">Adj: {{ number_format($reportData['summary']['total_adjustments'] ?? 0, 2) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Tabular Results --}}
                <div class="table-responsive">
                    <table class="report-table align-middle">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Product / SKU</th>
                                <th>Status</th>
                                <th class="text-end">Planned Cost</th>
                                <th class="text-end">Actual Material</th>
                                <th class="text-end">Actual Labor</th>
                                <th class="text-end">Actual Machine</th>
                                <th class="text-end">Actual Overhead</th>
                                <th class="text-end">Adjustments</th>
                                <th class="text-end">Actual Total</th>
                                <th class="text-end">Variance Amount</th>
                                <th class="text-end">Variance %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportData['data'] as $row)
                                <tr>
                                    <td class="fw-bold">
                                        <a href="{{ route('production.orders.show', ['order' => $row['id'], 'tab' => 'vtab-cost']) }}" class="text-primary text-decoration-none" target="_blank">
                                            {{ $row['order_number'] }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $row['product_name'] }}</div>
                                        <small class="text-muted font-monospace">{{ $row['product_sku'] }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-soft-secondary text-dark border">{{ ucfirst(str_replace('_', ' ', $row['status'])) }}</span>
                                    </td>
                                    <td class="text-end fw-semibold">{{ number_format($row['planned_cost'], 2) }}</td>
                                    <td class="text-end">{{ number_format($row['actual_material_cost'], 2) }}</td>
                                    <td class="text-end">{{ number_format($row['actual_labor_cost'], 2) }}</td>
                                    <td class="text-end">{{ number_format($row['actual_machine_cost'], 2) }}</td>
                                    <td class="text-end">{{ number_format($row['actual_overhead_cost'], 2) }}</td>
                                    <td class="text-end {{ $row['adjustments'] > 0 ? 'text-warning fw-bold' : 'text-muted' }}">
                                        {{ number_format($row['adjustments'], 2) }}
                                    </td>
                                    <td class="text-end fw-bold text-dark">{{ number_format($row['actual_total_cost'], 2) }}</td>
                                    <td class="text-end {{ $row['variance_amount'] > 0 ? 'text-danger fw-bold' : ($row['variance_amount'] < 0 ? 'text-success fw-bold' : 'text-muted') }}">
                                        {{ $row['variance_amount'] > 0 ? '+' : '' }}{{ number_format($row['variance_amount'], 2) }}
                                    </td>
                                    <td class="text-end {{ $row['variance_pct'] > 0 ? 'text-danger fw-bold' : ($row['variance_pct'] < 0 ? 'text-success fw-bold' : 'text-muted') }}">
                                        {{ $row['variance_pct'] > 0 ? '+' : '' }}{{ $row['variance_pct'] }}%
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-4">No production cost records found for the selected criteria.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if($print)
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    @endif

    <script src="{{ asset('assets/vendors/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('assets/js/common-init.min.js') }}"></script>
    @stack('scripts')
    @stack('styles')
</body>
</html>
