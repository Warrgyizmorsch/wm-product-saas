@php
    $reportTitles = [
        'machine'              => __('production.machine_performance_report') ?? 'Machine Performance & OEE Report',
        'work-center'          => __('production.work_center_report') ?? (__('production.work_center') . ' Performance & OEE Report'),
        'downtime'             => __('production.downtime_breakdown_report') ?? 'Downtime Breakdown & Events Report',
        'production-orders'    => 'Production ' . __('production.order_summary') . ' & Output Report',
        'material-consumption' => 'Material Consumption & Variance Report',
        'cost-variance'        => 'Production Cost & Variance Report',
        'order-detail'         => 'Production Order Detail Report',
        'sales-order-tracking' => 'Sales Order Tracking Report (Order-to-Delivery Pipeline)',
    ];
    $displayTitle = $reportTitles[$type] ?? ($displayTitle ?? (ucwords(str_replace('-', ' ', $type)) . ' Report'));
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
                margin: 8mm 6mm;
            }
            body {
                background-color: #ffffff !important;
                color: #000000 !important;
                font-size: 8pt !important;
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
                padding: 4px 0 !important;
                margin: 0 !important;
            }
            /* Scale oversized tables to fit page width */
            .table-responsive {
                overflow: visible !important;
            }
            .report-table {
                width: 100% !important;
                border: 1px solid #475569 !important;
                border-collapse: collapse !important;
                table-layout: fixed;
                word-wrap: break-word;
                font-size: 7.5pt !important;
            }
            .report-table thead th {
                background-color: #1e293b !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                border: 1px solid #334155 !important;
                padding: 4px 5px !important;
                font-size: 7.5pt !important;
                white-space: normal !important;
                word-wrap: break-word;
            }
            .report-table tbody td {
                border: 1px solid #cbd5e1 !important;
                padding: 3px 5px !important;
                font-size: 7.5pt !important;
                color: #000000 !important;
                vertical-align: middle !important;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            .report-table tbody tr:nth-child(even) td {
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .kpi-box {
                border: 1px solid #64748b !important;
                padding: 5px 8px !important;
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .badge {
                border: 1px solid #475569 !important;
                font-size: 7pt !important;
                padding: 1px 4px !important;
            }
            a {
                text-decoration: none !important;
                color: #000000 !important;
            }
            /* Prevent sections from orphaning across pages */
            h5 { page-break-after: avoid; }
            .table-responsive { page-break-inside: auto; }
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

                        @if($type === 'order-detail')
                            {{-- Order-detail is filtered by order_id only, no date range --}}
                            @if(request('order_id'))
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Active Order</label>
                                    <div class="badge bg-soft-primary text-primary border border-primary-subtle fs-12 px-2 py-1 w-100 text-start">
                                        <i class="feather-file-text me-1"></i>
                                        Order #{{ request('order_id') }}
                                    </div>
                                    <input type="hidden" name="order_id" value="{{ request('order_id') }}">
                                </div>
                            @endif
                            <p class="text-muted fs-12 mb-0">To view a different order, go back to the Reports page and select another order.</p>
                        @else
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
                            @if(request('customer_id'))
                                <input type="hidden" name="customer_id" value="{{ request('customer_id') }}">
                            @endif

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <x-ui.button href="{{ route('production.intelligence.reports.show', $type) }}" variant="light"  class="border">
                                    {{ __('production.reset') }}
                                </x-ui.button>
                                <x-ui.button type="submit" variant="primary" >
                                    {{ __('production.apply_filters') }}
                                </x-ui.button>
                            </div>
                        @endif
                    </x-ui.filter>
                </form>

                {{-- Export Dropdown: CSV / Excel / PDF --}}
                <div class="dropdown">
                    <button class="btn btn-light border shadow-sm dropdown-toggle d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="feather-download"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:170px">
                        <li><span class="dropdown-header fs-11 text-uppercase text-muted fw-bold px-3 py-1">{{ __('production.export_format') }}</span></li>
                        <li>
                            <a class="dropdown-item fs-13 d-flex align-items-center gap-2"
                               href="{{ route('production.intelligence.reports.export', array_merge(['type' => $type], request()->all())) }}">
                                <i class="feather-file-text text-muted"></i> CSV
                                <small class="text-muted ms-auto">Basic</small>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item fs-13 d-flex align-items-center gap-2"
                               href="{{ route('production.intelligence.reports.export-excel', array_merge(['type' => $type], request()->all())) }}">
                                <i class="feather-grid text-success"></i> Excel
                                <small class="text-muted ms-auto">.xlsx</small>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item fs-13 d-flex align-items-center gap-2"
                               href="{{ route('production.intelligence.reports.export-pdf', array_merge(['type' => $type], request()->all())) }}">
                                <i class="feather-file text-danger"></i> PDF
                                <small class="text-muted ms-auto">.pdf</small>
                            </a>
                        </li>
                    </ul>
                </div>

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
                                    <td colspan="8" class="text-center text-muted py-4">{{ __('production.no_machine_records_found') }}</td>
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
                                <th>{{ __('production.work_center') }} Code</th>
                                <th>{{ __('production.work_center') }} Name</th>
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
                                    <td colspan="6" class="text-center text-muted py-4">{{ __('production.no_work_center_records_found') }}</td>
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
                                <th>{{ __('production.started_at') }}</th>
                                <th>{{ __('production.resolved_at') }}</th>
                                <th class="text-end">{{ __('production.duration_min') }}</th>
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
                                    <td colspan="6" class="text-center text-muted py-4">{{ __('production.no_downtime_events_found') }}</td>
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
                                <th class="text-end">{{ __('production.total_events_count') }}</th>
                                <th class="text-end">Total Accumulated {{ __('production.duration_min') }}</th>
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
                            <div class="text-muted fs-11 text-uppercase fw-semibold">{{ __('production.planned_volume') }}</div>
                            <div class="fs-18 fw-bold text-primary mt-1">{{ number_format($reportData['summary']['total_planned_qty'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">{{ __('production.produced_volume') }}</div>
                            <div class="fs-18 fw-bold text-success mt-1">{{ number_format($reportData['summary']['total_produced_qty'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">{{ __('production.scrapped_volume') }}</div>
                            <div class="fs-18 fw-bold text-danger mt-1">{{ number_format($reportData['summary']['total_scrapped_qty'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">{{ __('production.overall_completion') }}</div>
                            <div class="fs-18 fw-bold text-info mt-1">{{ number_format($reportData['summary']['overall_completion_pct'] ?? 0, 1) }}%</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">{{ __('production.overall_yield') }}</div>
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
                                <th class="text-end">{{ __('production.produced_qty') }}</th>
                                <th class="text-end">Scrapped Qty</th>
                                <th>{{ __('production.completion_pct') }}</th>
                                <th>{{ __('production.yield_pct') }}</th>
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
                                    <td colspan="10" class="text-center text-muted py-4">{{ __('production.no_production_orders_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif($type === 'material-consumption')
                {{-- Summary KPI Cards --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center h-100">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">{{ __('production.total_line_items') }}</div>
                            <div class="fs-18 fw-bold text-dark mt-1">{{ number_format($reportData['summary']['total_items'] ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center h-100">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Total Planned Cost</div>
                            <div class="fs-18 fw-bold text-primary mt-1">{{ number_format($reportData['summary']['total_planned_cost'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center h-100">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">{{ __('production.total_issued_cost') }}</div>
                            <div class="fs-18 fw-bold text-dark mt-1">{{ number_format($reportData['summary']['total_issued_cost'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="kpi-box text-center h-100">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">{{ __('production.actually_consumed') }} Cost</div>
                            <div class="fs-18 fw-bold text-success mt-1">{{ number_format($reportData['summary']['total_consumed_cost'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-12">
                        <div class="kpi-box text-center h-100">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">{{ __('production.floor_stock_wip') }} Balance</div>
                            <div class="fs-18 fw-bold text-warning mt-1">{{ number_format($reportData['summary']['total_floor_balance_cost'] ?? 0, 2) }}</div>
                            <small class="text-muted fs-11">{{ __('production.issued_floor_not_consumed') }}</small>
                        </div>
                    </div>
                </div>

                @if(!empty($reportData['summary']['uom_groups']))
                    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                        <span class="text-muted fs-12 fw-semibold"><i class="feather-box me-1"></i> {{ __('production.uom_totals') }}:</span>
                        @foreach($reportData['summary']['uom_groups'] as $uom => $uomData)
                            <span class="badge bg-white border text-dark fs-12 px-2 py-1 shadow-sm">
                                <strong>{{ $uom }}:</strong> Planned {{ number_format($uomData['planned'], 2) }} | Issued {{ number_format($uomData['issued'], 2) }} | Consumed {{ number_format($uomData['consumed'] ?? 0, 2) }} | Floor {{ number_format($uomData['floor_balance'] ?? 0, 2) }}
                            </span>
                        @endforeach
                    </div>
                @endif

                {{-- Tabular Results --}}
                <div class="table-responsive">
                    <table class="report-table align-middle">
                        <thead>
                            <tr class="text-nowrap">
                                <th>Order Number</th>
                                <th>{{ __('production.finished_good') }}</th>
                                <th>{{ __('production.consuming_operation') }}</th>
                                <th>{{ __('production.material_component') }}</th>
                                <th>UOM</th>
                                <th class="text-end">Planned Qty</th>
                                <th class="text-end">Issued Qty</th>
                                <th class="text-end">{{ __('production.consumed_qty') }}</th>
                                <th class="text-end">{{ __('production.floor_balance') }}</th>
                                <th class="text-center" style="min-width: 110px;">{{ __('production.consumption_pct') }}</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Planned Cost</th>
                                <th class="text-end">{{ __('production.issued_cost') }}</th>
                                <th class="text-end">{{ __('production.consumed_cost') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportData['data'] as $row)
                                <tr>
                                    <td class="fw-bold font-monospace">
                                        <a href="{{ route('production.intelligence.reports.show', ['type' => 'order-detail', 'order_id' => $row['order_id']]) }}" class="text-primary text-decoration-none" target="_blank" title="View Order Job Card">
                                            {{ $row['order_number'] }}
                                        </a>
                                    </td>
                                    <td class="fw-semibold text-dark">{{ $row['finished_good'] }}</td>
                                    <td>
                                        <span class="fw-semibold text-dark">{{ $row['operation_name'] }}</span>
                                        @if($row['work_center'] !== '—')
                                            <div class="text-muted fs-11"><i class="feather-map-pin me-1"></i>{{ $row['work_center'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $row['material_name'] }}</div>
                                        <small class="text-muted font-monospace">{{ $row['material_sku'] }}</small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">{{ $row['uom'] }}</span></td>
                                    <td class="text-end fw-semibold">{{ number_format($row['planned_qty'], 2) }}</td>
                                    <td class="text-end text-primary fw-bold">{{ number_format($row['issued_qty'], 2) }}</td>
                                    <td class="text-end text-success fw-bold">{{ number_format($row['consumed_qty'], 2) }}</td>
                                    <td class="text-end fw-semibold {{ $row['floor_balance'] > 0 ? 'text-warning' : 'text-muted' }}">
                                        {{ number_format($row['floor_balance'], 2) }}
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1 justify-content-center">
                                            <div class="progress flex-grow-1" style="height: 5px; min-width: 50px;">
                                                <div class="progress-bar bg-success" style="width: {{ min(100, $row['consumption_pct']) }}%;"></div>
                                            </div>
                                            <span class="fs-11 fw-semibold text-muted">{{ $row['consumption_pct'] }}%</span>
                                        </div>
                                    </td>
                                    <td class="text-end text-muted">{{ number_format($row['unit_cost'], 2) }}</td>
                                    <td class="text-end">{{ number_format($row['planned_cost'], 2) }}</td>
                                    <td class="text-end">{{ number_format($row['issued_cost'], 2) }}</td>
                                    <td class="text-end text-success fw-semibold">{{ number_format($row['consumed_cost'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="text-center text-muted py-4">{{ __('production.no_material_records_found') }}</td>
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
                            <div class="text-muted fs-11 text-uppercase fw-semibold">{{ __('production.total_cost_variance') }}</div>
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
                            <span class="text-muted d-block fs-11 text-uppercase fw-semibold">Overhead / {{ __('production.adjustments') }}</span>
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
                                <th class="text-end">{{ __('production.actual_material') }}</th>
                                <th class="text-end">{{ __('production.actual_labor') }}</th>
                                <th class="text-end">Actual Machine</th>
                                <th class="text-end">{{ __('production.actual_overhead') }}</th>
                                <th class="text-end">{{ __('production.adjustments') }}</th>
                                <th class="text-end">{{ __('production.actual_total') }}</th>
                                <th class="text-end">{{ __('production.variance_amount') }}</th>
                                <th class="text-end">{{ __('production.variance_pct') }}</th>
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
                                    <td colspan="12" class="text-center text-muted py-4">{{ __('production.no_production_cost_records_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @elseif($type === 'order-detail')
                @php
                    $order = $reportData['order'];
                    $statusBadge = match($order['status']) {
                        'completed'   => 'bg-soft-success text-success border border-success-subtle',
                        'in_progress' => 'bg-soft-primary text-primary border border-primary-subtle',
                        'released'    => 'bg-soft-info text-info border border-info-subtle',
                        'draft'       => 'bg-soft-secondary text-secondary border border-secondary-subtle',
                        'closed'      => 'bg-soft-dark text-dark border',
                        'cancelled'   => 'bg-soft-danger text-danger border border-danger-subtle',
                        default       => 'bg-soft-dark text-dark border',
                    };
                @endphp

                {{-- ── ORDER HEADER IDENTITY BLOCK ──────────────────────────────── --}}
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4 pb-3 border-bottom">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="fs-20 fw-bold text-dark font-monospace">{{ $order['order_number'] }}</span>
                            <span class="badge {{ $statusBadge }}">{{ ucfirst(str_replace('_', ' ', $order['status'])) }}</span>
                            @if($order['is_external'] ?? false)
                                <span class="badge bg-soft-warning text-warning border border-warning-subtle">Subcontract</span>
                            @endif
                        </div>
                        <div class="text-muted fs-13">{{ $order['product_name'] }} &nbsp;<span class="font-monospace text-muted">{{ $order['product_sku'] }}</span></div>
                        <div class="text-muted fs-12 mt-1">Created by <strong>{{ $order['created_by'] }}</strong> &nbsp;·&nbsp; Planned: <strong>{{ $order['start_date'] }}</strong> → <strong>{{ $order['end_date'] }}</strong></div>
                    </div>
                    <div class="text-sm-end text-start">
                        <div class="text-muted fs-12">Generated: <strong class="text-dark">{{ $reportData['generated_at'] }}</strong></div>
                        @if($order['actual_start'])
                            <div class="text-muted fs-12 mt-1">Actual Start: <strong class="text-dark">{{ $order['actual_start'] }}</strong></div>
                        @endif
                        @if($order['actual_end'])
                            <div class="text-muted fs-12">Actual End: <strong class="text-dark">{{ $order['actual_end'] }}</strong></div>
                        @endif
                    </div>
                </div>

                {{-- ── SECTION 1: ORDER KPI STRIP ──────────────────────────────── --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Planned Qty</div>
                            <div class="fs-18 fw-bold text-dark mt-1">{{ number_format($order['planned_qty'], 2) }}</div>
                            <div class="text-muted fs-11">{{ $order['uom'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Produced</div>
                            <div class="fs-18 fw-bold text-success mt-1">{{ number_format($order['produced_qty'], 2) }}</div>
                            <div class="text-muted fs-11">{{ $order['uom'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Scrapped</div>
                            <div class="fs-18 fw-bold {{ $order['scrapped_qty'] > 0 ? 'text-danger' : 'text-muted' }} mt-1">{{ number_format($order['scrapped_qty'], 2) }}</div>
                            <div class="text-muted fs-11">{{ $order['uom'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Rejected</div>
                            <div class="fs-18 fw-bold {{ $order['rejected_qty'] > 0 ? 'text-warning' : 'text-muted' }} mt-1">{{ number_format($order['rejected_qty'], 2) }}</div>
                            <div class="text-muted fs-11">{{ $order['uom'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Completion</div>
                            <div class="fs-18 fw-bold text-primary mt-1">{{ $order['completion_pct'] }}%</div>
                            <div class="progress mt-1" style="height:4px">
                                <div class="progress-bar bg-primary" style="width:{{ $order['completion_pct'] }}%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Yield</div>
                            <div class="fs-18 fw-bold {{ $order['yield_pct'] >= 95 ? 'text-success' : ($order['yield_pct'] >= 80 ? 'text-warning' : 'text-danger') }} mt-1">{{ $order['yield_pct'] }}%</div>
                        </div>
                    </div>
                </div>

                {{-- ── SECTION 1B: ESTIMATED COST VS ACTUAL COST BREAKDOWN ───────── --}}
                @if(!empty($reportData['cost_estimation']))
                    @php
                        $costEst = $reportData['cost_estimation'];
                        $totVariance = $costEst['total']['variance'] ?? 0;
                        $totVarPct = $costEst['total']['variance_pct'] ?? 0;
                    @endphp
                    <div class="card border border-light shadow-sm mb-4">
                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0 fs-13">
                                <i class="feather-dollar-sign me-1 text-success"></i>Production Order Cost Estimation vs. {{ __('production.actual_incurred') }}
                            </h6>
                            <span class="badge {{ $totVariance > 0 ? 'bg-soft-danger text-danger border border-danger-subtle' : ($totVariance < 0 ? 'bg-soft-success text-success border border-success-subtle' : 'bg-soft-secondary text-secondary border') }} fs-11">
                                {{ __('production.net_variance') }}: {{ $totVariance > 0 ? '+' : '' }}{{ number_format($totVariance, 2) }} ({{ $totVariance > 0 ? '+' : '' }}{{ $totVarPct }}%)
                            </span>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3">
                                {{-- Material Pillar --}}
                                <div class="col-md-3 col-6">
                                    <div class="p-2 border rounded bg-white text-center">
                                        <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Material Cost</span>
                                        <div class="fs-15 fw-bold text-primary">{{ number_format($costEst['material']['planned'] ?? 0, 2) }}</div>
                                        <small class="text-muted fs-11 d-block">Act: {{ number_format($costEst['material']['actual'] ?? 0, 2) }}</small>
                                        <div class="fs-11 {{ ($costEst['material']['variance'] ?? 0) > 0 ? 'text-danger fw-semibold' : 'text-success fw-semibold' }}">
                                            Var: {{ ($costEst['material']['variance'] ?? 0) > 0 ? '+' : '' }}{{ number_format($costEst['material']['variance'] ?? 0, 2) }}
                                        </div>
                                    </div>
                                </div>
                                {{-- Labor Pillar --}}
                                <div class="col-md-3 col-6">
                                    <div class="p-2 border rounded bg-white text-center">
                                        <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Labor Cost</span>
                                        <div class="fs-15 fw-bold text-primary">{{ number_format($costEst['labor']['planned'] ?? 0, 2) }}</div>
                                        <small class="text-muted fs-11 d-block">Act: {{ number_format($costEst['labor']['actual'] ?? 0, 2) }}</small>
                                        <div class="fs-11 {{ ($costEst['labor']['variance'] ?? 0) > 0 ? 'text-danger fw-semibold' : 'text-success fw-semibold' }}">
                                            Var: {{ ($costEst['labor']['variance'] ?? 0) > 0 ? '+' : '' }}{{ number_format($costEst['labor']['variance'] ?? 0, 2) }}
                                        </div>
                                    </div>
                                </div>
                                {{-- Machine Pillar --}}
                                <div class="col-md-3 col-6">
                                    <div class="p-2 border rounded bg-white text-center">
                                        <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Machine Cost</span>
                                        <div class="fs-15 fw-bold text-primary">{{ number_format($costEst['machine']['planned'] ?? 0, 2) }}</div>
                                        <small class="text-muted fs-11 d-block">Act: {{ number_format($costEst['machine']['actual'] ?? 0, 2) }}</small>
                                        <div class="fs-11 {{ ($costEst['machine']['variance'] ?? 0) > 0 ? 'text-danger fw-semibold' : 'text-success fw-semibold' }}">
                                            Var: {{ ($costEst['machine']['variance'] ?? 0) > 0 ? '+' : '' }}{{ number_format($costEst['machine']['variance'] ?? 0, 2) }}
                                        </div>
                                    </div>
                                </div>
                                {{-- Overhead Pillar --}}
                                <div class="col-md-3 col-6">
                                    <div class="p-2 border rounded bg-white text-center">
                                        <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Overhead Cost</span>
                                        <div class="fs-15 fw-bold text-primary">{{ number_format($costEst['overhead']['planned'] ?? 0, 2) }}</div>
                                        <small class="text-muted fs-11 d-block">Act: {{ number_format($costEst['overhead']['actual'] ?? 0, 2) }}</small>
                                        <div class="fs-11 {{ ($costEst['overhead']['variance'] ?? 0) > 0 ? 'text-danger fw-semibold' : 'text-success fw-semibold' }}">
                                            Var: {{ ($costEst['overhead']['variance'] ?? 0) > 0 ? '+' : '' }}{{ number_format($costEst['overhead']['variance'] ?? 0, 2) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-2 border-top fs-12">
                                <div>
                                    <span class="text-muted">Total {{ __('production.estimated_planned') }}:</span>
                                    <strong class="text-dark ms-1 fs-13">{{ number_format($costEst['total']['planned'] ?? 0, 2) }}</strong>
                                </div>
                                <div>
                                    <span class="text-muted">Total {{ __('production.actual_incurred') }}:</span>
                                    <strong class="text-dark ms-1 fs-13">{{ number_format($costEst['total']['actual'] ?? 0, 2) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ── SECTION 2: CURRENT WIP LOCATION ─────────────────────────── --}}
                @if(!empty($reportData['wip_locations']))
                    <div class="alert border border-primary-subtle bg-soft-primary mb-4 py-2 px-3" role="alert">
                        <div class="d-flex flex-wrap gap-3 align-items-center">
                            <span class="fw-bold text-primary fs-13"><i class="feather-map-pin me-1"></i>{{ __('production.current_wip_location') }}</span>
                            @foreach($reportData['wip_locations'] as $wip)
                                <span class="badge bg-white border text-dark fs-12 px-2 py-1 shadow-sm">
                                    <strong>{{ $wip['operation'] }}</strong>
                                    @if($wip['work_center'] !== '—') <span class="text-muted"> @ {{ $wip['work_center'] }}</span> @endif
                                    &nbsp;·&nbsp; <span class="text-primary fw-bold">{{ number_format($wip['available'], 2) }} units</span>
                                    @if($wip['batch'] !== '—') <span class="text-muted fs-11"> ({{ $wip['batch'] }})</span> @endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ── SECTION 3: OPERATION STAGES ──────────────────────────────── --}}
                <h5 class="fw-bold text-dark mb-3 mt-2"><i class="feather-git-merge me-2 text-primary"></i>{{ __('production.operation_stages') }}</h5>
                <div class="table-responsive mb-4">
                    <table class="report-table align-middle">
                        <thead>
                            <tr>
                                <th>Seq</th>
                                <th>Operation</th>
                                <th>{{ __('production.work_center') }}</th>
                                <th>Machine</th>
                                <th>Status</th>
                                <th class="text-end">Produced</th>
                                <th class="text-end">Rejected</th>
                                <th class="text-end">Scrapped</th>
                                <th class="text-end">Setup (min)<br><small class="fw-normal text-muted">Plan / Act</small></th>
                                <th class="text-end">Process (min)<br><small class="fw-normal text-muted">Plan / Act</small></th>
                                <th>{{ __('production.started_at') }}</th>
                                <th>{{ __('production.ended_at') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportData['operations'] as $op)
                                @php
                                    $opBadge = match($op['status']) {
                                        'completed' => 'bg-soft-success text-success border border-success-subtle',
                                        'running','in_progress' => 'bg-soft-primary text-primary border border-primary-subtle',
                                        'paused'    => 'bg-soft-warning text-warning border border-warning-subtle',
                                        'ready'     => 'bg-soft-info text-info border border-info-subtle',
                                        'waiting'   => 'bg-soft-secondary text-secondary border border-secondary-subtle',
                                        default     => 'bg-soft-dark text-dark border',
                                    };
                                @endphp
                                <tr>
                                    <td class="fw-bold text-muted font-monospace">{{ $op['sequence'] }}</td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $op['name'] }}</div>
                                        <div class="d-flex gap-1 mt-1">
                                            @if($op['is_external'])
                                                <span class="badge bg-soft-warning text-warning border border-warning-subtle" style="font-size:10px">{{ __('production.external') }}</span>
                                            @endif
                                            @if($op['quality_required'])
                                                <span class="badge bg-soft-info text-info border border-info-subtle" style="font-size:10px">{{ __('production.qc_gate') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="fw-semibold text-dark">{{ $op['work_center'] }}</td>
                                    <td class="text-muted">{{ $op['machine'] }}</td>
                                    <td><span class="badge {{ $opBadge }}">{{ ucfirst(str_replace('_', ' ', $op['status'])) }}</span></td>
                                    <td class="text-end text-success fw-bold">{{ number_format($op['qty_produced'], 2) }}</td>
                                    <td class="text-end {{ $op['qty_rejected'] > 0 ? 'text-warning fw-bold' : 'text-muted' }}">{{ number_format($op['qty_rejected'], 2) }}</td>
                                    <td class="text-end {{ $op['qty_scrapped'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">{{ number_format($op['qty_scrapped'], 2) }}</td>
                                    <td class="text-end">
                                        <span class="text-muted">{{ number_format($op['setup_planned'], 0) }}</span>
                                        <span class="text-muted"> / </span>
                                        <span class="{{ $op['setup_actual'] > $op['setup_planned'] && $op['setup_planned'] > 0 ? 'text-danger fw-bold' : 'text-dark fw-semibold' }}">{{ number_format($op['setup_actual'], 0) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-muted">{{ number_format($op['process_planned'], 0) }}</span>
                                        <span class="text-muted"> / </span>
                                        <span class="{{ $op['process_actual'] > $op['process_planned'] && $op['process_planned'] > 0 ? 'text-danger fw-bold' : 'text-dark fw-semibold' }}">{{ number_format($op['process_actual'], 0) }}</span>
                                    </td>
                                    <td class="text-muted fs-12">{{ $op['actual_start'] ?? '—' }}</td>
                                    <td class="text-muted fs-12">{{ $op['actual_end'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-4">{{ __('production.no_routing_operations_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- ── SECTION 4: MATERIAL CONSUMPTION ─────────────────────────── --}}
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="feather-layers me-2 text-warning"></i>{{ __('production.material_consumption_operation_allocation') }}</h5>
                    <span class="badge bg-soft-info text-info border border-info-subtle fs-11">
                        <i class="feather-info me-1"></i>Consumed based on Operation Progress
                    </span>
                </div>

                {{-- Material Cost Summary strip --}}
                @php
                    $matSummary = $reportData['material_summary'] ?? [];
                    $matVar = $matSummary['variance_cost'] ?? 0;
                @endphp
                <div class="row g-3 mb-3">
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center p-2">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Planned Cost</div>
                            <div class="fs-15 fw-bold text-primary mt-1">{{ number_format($matSummary['total_planned_cost'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="kpi-box text-center p-2">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Issued (Store)</div>
                            <div class="fs-15 fw-bold text-dark mt-1">{{ number_format($matSummary['total_issued_cost'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="kpi-box text-center p-2">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">{{ __('production.actually_consumed') }}</div>
                            <div class="fs-15 fw-bold text-success mt-1">{{ number_format($matSummary['total_consumed_cost'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="kpi-box text-center p-2">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">{{ __('production.floor_stock_wip') }}</div>
                            <div class="fs-15 fw-bold text-warning mt-1">{{ number_format($matSummary['total_floor_stock_cost'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-12">
                        <div class="kpi-box text-center p-2">
                            <div class="text-muted fs-11 text-uppercase fw-semibold">Cost Variance</div>
                            <div class="fs-15 fw-bold {{ $matVar > 0 ? 'text-danger' : ($matVar < 0 ? 'text-success' : 'text-muted') }} mt-1">
                                {{ $matVar > 0 ? '+' : '' }}{{ number_format($matVar, 2) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mb-4">
                    <table class="report-table align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('production.material_component') }}</th>
                                <th>{{ __('production.consuming_operation') }}</th>
                                <th>UOM</th>
                                <th class="text-end">Planned Qty</th>
                                <th class="text-end">Issued Qty</th>
                                <th class="text-end text-success">{{ __('production.consumed_qty') }}</th>
                                <th class="text-end text-warning">{{ __('production.floor_wip_stock') }}</th>
                                <th class="text-end">Progress %</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Planned Cost</th>
                                <th class="text-end">{{ __('production.consumed_cost') }}</th>
                                <th class="text-end">Cost Variance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportData['materials'] as $mat)
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $mat['material_name'] }}</div>
                                        <div class="font-monospace text-muted fs-11">{{ $mat['material_sku'] }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark fs-12">{{ $mat['operation_name'] ?? 'Stage Intake' }}</div>
                                        @if(!empty($mat['work_center']))
                                            <div class="text-muted fs-11"><i class="feather-cpu me-1"></i>{{ $mat['work_center'] }}</div>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-light text-dark border">{{ $mat['uom'] }}</span></td>
                                    <td class="text-end fw-semibold">{{ number_format($mat['planned_qty'], 2) }}</td>
                                    <td class="text-end text-primary fw-bold">{{ number_format($mat['issued_qty'], 2) }}</td>
                                    <td class="text-end text-success fw-bold">{{ number_format($mat['consumed_qty'] ?? 0, 2) }}</td>
                                    <td class="text-end {{ ($mat['floor_balance'] ?? 0) > 0 ? 'text-warning fw-bold' : 'text-muted' }}">
                                        {{ number_format($mat['floor_balance'] ?? 0, 2) }}
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex align-items-center justify-content-end gap-1">
                                            <span class="fw-semibold fs-12">{{ $mat['consumption_pct'] ?? 0 }}%</span>
                                            <div class="progress" style="width: 45px; height: 5px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ min(100, $mat['consumption_pct'] ?? 0) }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end text-muted">{{ number_format($mat['unit_cost'], 2) }}</td>
                                    <td class="text-end">{{ number_format($mat['planned_cost'], 2) }}</td>
                                    <td class="text-end text-success fw-semibold">{{ number_format($mat['consumed_cost'] ?? 0, 2) }}</td>
                                    <td class="text-end {{ $mat['variance_cost'] > 0 ? 'text-danger fw-bold' : ($mat['variance_cost'] < 0 ? 'text-success fw-bold' : 'text-muted') }}">
                                        {{ $mat['variance_cost'] > 0 ? '+' : '' }}{{ number_format($mat['variance_cost'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-4">No material reservations found for this order.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- ── SECTION 5: SCRAP EVENTS ──────────────────────────────────── --}}
                <h5 class="fw-bold text-dark mb-3"><i class="feather-trash-2 me-2 text-danger"></i>Scrap Events
                    @if(count($reportData['scrap_events']) > 0)
                        <span class="badge bg-soft-danger text-danger border border-danger-subtle ms-1">{{ count($reportData['scrap_events']) }}</span>
                    @endif
                </h5>
                @if(empty($reportData['scrap_events']))
                    <div class="text-muted fs-13 mb-4 py-3 px-3 border rounded bg-light text-center">
                        <i class="feather-check-circle text-success me-1"></i> No scrap events recorded for this order.
                    </div>
                @else
                    <div class="table-responsive mb-2">
                        <table class="report-table align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('production.scrapped_item') }}</th>
                                    <th>SKU</th>
                                    <th>Operation</th>
                                    <th class="text-end">Quantity</th>
                                    <th>Reason</th>
                                    <th>{{ __('production.recorded_at') }}</th>
                                    <th>{{ __('production.stock_posted') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reportData['scrap_events'] as $scrap)
                                    <tr>
                                        <td class="fw-semibold text-dark">{{ $scrap['product'] }}</td>
                                        <td class="font-monospace text-muted fs-12">{{ $scrap['product_sku'] }}</td>
                                        <td class="text-muted">{{ $scrap['operation'] }}</td>
                                        <td class="text-end text-danger fw-bold">{{ number_format($scrap['quantity'], 2) }}</td>
                                        <td>{{ $scrap['reason'] }}</td>
                                        <td class="text-muted fs-12">{{ $scrap['recorded_at'] }}</td>
                                        <td>
                                            @if($scrap['stock_posted'])
                                                <span class="badge bg-soft-success text-success border border-success-subtle"><i class="feather-check me-1"></i>{{ __('production.posted') }}</span>
                                            @else
                                                <span class="badge bg-soft-warning text-warning border border-warning-subtle">Pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

            @elseif($type === 'sales-order-tracking')
                {{-- KPI Metric Summary Strip --}}
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-md-2">
                        <div class="p-3 bg-light rounded border text-center h-100">
                            <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">{{ __('production.total_sales_orders') }}</span>
                            <h4 class="fw-bold text-dark mb-0">{{ number_format($reportData['total_orders'] ?? 0) }}</h4>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <div class="p-3 bg-light rounded border text-center h-100">
                            <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">{{ __('production.total_ordered_qty') }}</span>
                            <h4 class="fw-bold text-primary mb-0">{{ number_format($reportData['total_ordered_qty'] ?? 0, 1) }}</h4>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <div class="p-3 bg-light rounded border text-center h-100">
                            <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">MO {{ __('production.produced_qty') }}</span>
                            <h4 class="fw-bold text-success mb-0">{{ number_format($reportData['total_produced_qty'] ?? 0, 1) }}</h4>
                            <small class="text-muted fs-11">{{ $reportData['production_pct'] ?? 0 }}% of ordered</small>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <div class="p-3 bg-light rounded border text-center h-100">
                            <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">{{ __('production.delivered_qty') }}</span>
                            <h4 class="fw-bold mb-0" style="color: #0d9488;">{{ number_format($reportData['total_delivered_qty'] ?? 0, 1) }}</h4>
                            <small class="text-muted fs-11">{{ $reportData['fulfillment_pct'] ?? 0 }}% fulfilled</small>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <div class="p-3 bg-light rounded border text-center h-100">
                            <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">{{ __('production.pending_delivery') }}</span>
                            <h4 class="fw-bold text-danger mb-0">{{ number_format($reportData['total_pending_qty'] ?? 0, 1) }}</h4>
                            <small class="text-muted fs-11">{{ __('production.awaiting_dispatch') }}</small>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <div class="p-3 bg-light rounded border text-center h-100">
                            <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">{{ __('production.fulfillment_rate') }}</span>
                            <h4 class="fw-bold mb-0" style="color: #7c3aed;">{{ $reportData['fulfillment_pct'] ?? 0 }}%</h4>
                            <div class="progress mt-2" style="height: 4px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ min(100, $reportData['fulfillment_pct'] ?? 0) }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Table Representation --}}
                <div class="table-responsive">
                    <table class="report-table align-middle">
                        <thead>
                            <tr class="text-nowrap">
                                <th style="width: 35px;">SR</th>
                                <th>{{ __('production.sales_person') }}</th>
                                <th>{{ __('production.sale_order_no') }}</th>
                                <th>{{ __('production.order_date') }}</th>
                                <th>{{ __('production.customer_name') }}</th>
                                <th>{{ __('production.product_description') }}</th>
                                <th class="text-end">{{ __('production.so_qty') }}</th>
                                <th>{{ __('production.delivery_date') }}</th>
                                <th>Status</th>
                                <th>{{ __('production.mo_no') }}</th>
                                <th>{{ __('production.mo_date') }}</th>
                                <th>{{ __('production.requisition_no') }}</th>
                                <th>{{ __('production.indent_no') }}</th>
                                <th>{{ __('production.po_no') }}</th>
                                <th>{{ __('production.supplier_name') }}</th>
                                <th class="text-end">{{ __('production.mo_done') }}</th>
                                <th class="text-end">{{ __('production.mo_done') }} Date</th>
                                <th class="text-end">{{ __('production.mo_pending') }}</th>
                                <th class="text-end">{{ __('production.delivered_qty') }}</th>
                                <th>{{ __('production.delivered_date') }}</th>
                                <th class="text-end">{{ __('production.pending_qty') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportData['data'] as $row)
                                @php
                                    $moBadgeClass = match($row['mo_color']) {
                                        'success' => 'bg-soft-success text-success border border-success-subtle',
                                        'warning' => 'bg-soft-warning text-warning border border-warning-subtle',
                                        default   => 'bg-soft-danger text-danger border border-danger-subtle',
                                    };
                                    $delBadgeClass = match($row['delivery_color']) {
                                        'success' => 'bg-soft-success text-success border border-success-subtle',
                                        'warning' => 'bg-soft-warning text-warning border border-warning-subtle',
                                        default   => 'bg-soft-danger text-danger border border-danger-subtle',
                                    };
                                    $reqBadgeClass = match($row['req_color']) {
                                        'success' => 'bg-soft-success text-success border border-success-subtle',
                                        'warning' => 'bg-soft-warning text-warning border border-warning-subtle',
                                        default   => 'bg-soft-secondary text-secondary border border-secondary-subtle',
                                    };
                                    $poBadgeClass = match($row['po_color']) {
                                        'success' => 'bg-soft-success text-success border border-success-subtle',
                                        'warning' => 'bg-soft-warning text-warning border border-warning-subtle',
                                        default   => 'bg-soft-secondary text-secondary border border-secondary-subtle',
                                    };
                                @endphp
                                <tr>
                                    <td class="text-muted fs-12 text-center">{{ $row['sr_no'] }}</td>
                                    <td class="fs-12 text-muted">{{ $row['sales_person'] }}</td>
                                    <td>
                                        <span class="fw-bold font-monospace text-primary">{{ $row['sales_order_no'] }}</span>
                                    </td>
                                    <td class="text-muted fs-12 text-nowrap">{{ $row['sales_order_date'] }}</td>
                                    <td class="fw-semibold text-dark">{{ $row['customer_name'] }}</td>
                                    <td>
                                        <div class="fw-medium text-dark">{{ $row['product_name'] }}</div>
                                        <small class="text-muted font-monospace fs-11">{{ $row['product_sku'] }}</small>
                                    </td>
                                    <td class="text-end fw-bold text-dark">{{ number_format($row['so_qty'], 1) }}</td>
                                    <td class="text-muted fs-12 text-nowrap">{{ $row['customer_delivery_date'] }}</td>
                                    <td>
                                        <span class="badge bg-soft-primary text-primary border border-primary-subtle fs-11">{{ $row['status'] }}</span>
                                    </td>
                                    {{-- MO Stage --}}
                                    <td>
                                        @if($row['mo_no'] !== '—')
                                            @if(!empty($row['mo_id']))
                                                <a href="{{ route('production.intelligence.reports.show', ['type' => 'order-detail', 'order_id' => $row['mo_id']]) }}" target="_blank" class="fw-bold font-monospace text-decoration-none" title="View MO detail">
                                                    {{ $row['mo_no'] }}
                                                </a>
                                            @else
                                                <span class="fw-bold font-monospace">{{ $row['mo_no'] }}</span>
                                            @endif
                                            <div><span class="badge {{ $moBadgeClass }} fs-10">{{ $row['mo_status'] }}</span></div>
                                        @else
                                            <span class="badge bg-soft-danger text-danger border border-danger-subtle fs-10">{{ __('production.not_created') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-muted fs-12 text-nowrap">{{ $row['mo_date'] }}</td>
                                    {{-- Requisition & Indent Stage --}}
                                    <td>
                                        @if($row['requisition_no'] !== '—')
                                            <span class="font-monospace fs-12 text-dark">{{ $row['requisition_no'] }}</span>
                                        @else
                                            <span class="text-muted fs-12">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($row['indent_no'] !== '—')
                                            <span class="badge {{ $reqBadgeClass }} fs-11 font-monospace">{{ $row['indent_no'] }}</span>
                                            <div class="fs-10 text-muted">{{ $row['indent_date'] }}</div>
                                        @else
                                            <span class="text-muted fs-12">—</span>
                                        @endif
                                    </td>
                                    {{-- Purchase Order Stage --}}
                                    <td>
                                        @if($row['po_no'] !== '—')
                                            <span class="badge {{ $poBadgeClass }} fs-11 font-monospace">{{ $row['po_no'] }}</span>
                                        @else
                                            <span class="text-muted fs-12">—</span>
                                        @endif
                                    </td>
                                    <td class="fs-12 text-muted">{{ $row['supplier_name'] }}</td>
                                    {{-- MO Quantities --}}
                                    <td class="text-end fw-bold text-success">{{ number_format($row['mo_done_qty'], 1) }}</td>
                                    <td class="text-end text-muted fs-12 text-nowrap">{{ $row['mo_done_date'] }}</td>
                                    <td class="text-end fw-bold {{ $row['mo_pending_qty'] > 0 ? 'text-danger' : 'text-muted' }}">
                                        {{ number_format($row['mo_pending_qty'], 1) }}
                                    </td>
                                    {{-- Dispatch & Delivery Stage --}}
                                    <td class="text-end fw-bold" style="color: #0d9488;">{{ number_format($row['delivered_qty'], 1) }}</td>
                                    <td class="text-muted fs-12 text-nowrap">{{ $row['delivered_date'] }}</td>
                                    <td class="text-end">
                                        <span class="badge {{ $delBadgeClass }} fs-11 fw-bold">
                                            {{ number_format($row['pending_qty'], 1) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="21" class="text-center text-muted py-4">
                                        <i class="feather-info fs-24 d-block mb-2 text-muted"></i>
                                        No sales orders found for the selected filter period.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @endif
        </div>
    </div>

    @if(!empty($print))
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
