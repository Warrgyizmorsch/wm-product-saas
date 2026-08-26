@extends('layouts.duralux')

@section('title', 'Subcontract Vendor Performance & Management Analytics | SaaS ERP')
@section('page-title', 'Subcontract Performance & Vendor Analytics')
@section('breadcrumb', 'Subcontract Analytics')

@section('content')
<div class="erp-single-panel bg-white p-4 rounded-3 shadow-sm mb-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="feather-trending-up me-2 text-primary"></i>Subcontract Vendor Analytics & Performance Governance</h4>
            <span class="fs-12 text-muted">Authoritative operational SLA, quality rates, lead-time variance, WIP value, and procurement mode throughput.</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('production.subcontract.delivery-challans.index') }}" class="btn btn-sm btn-outline-primary">
                <i class="feather-truck me-1"></i> Delivery Challans
            </a>
            <a href="{{ route('production.dashboard') }}" class="btn btn-sm btn-light border">
                <i class="feather-arrow-left me-1"></i> Production Dashboard
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 bg-light p-3 mb-4 rounded-3">
        <form action="{{ route('production.subcontract.analytics') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fs-12 fw-bold text-muted text-uppercase mb-1">Time Period</label>
                <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" @selected(($filters['period'] ?? 'all') === 'all')>All Time</option>
                    <option value="this_month" @selected(($filters['period'] ?? '') === 'this_month')>This Month</option>
                    <option value="last_month" @selected(($filters['period'] ?? '') === 'last_month')>Last Month</option>
                    <option value="this_quarter" @selected(($filters['period'] ?? '') === 'this_quarter')>This Quarter</option>
                    <option value="this_year" @selected(($filters['period'] ?? '') === 'this_year')>This Year</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fs-12 fw-bold text-muted text-uppercase mb-1">Vendor Filter</label>
                <select name="vendor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Subcontract Vendors</option>
                    @foreach($vendors as $v)
                        <option value="{{ $v->id }}" @selected(($filters['vendor_id'] ?? '') == $v->id)>
                            {{ $v->name }} ({{ $v->vendor_code ?? 'VND' }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fs-12 fw-bold text-muted text-uppercase mb-1">Product Filter</label>
                <select name="product_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Finished Products</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" @selected(($filters['product_id'] ?? '') == $p->id)>
                            {{ $p->name }} (SKU: {{ $p->sku }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 text-end">
                <a href="{{ route('production.subcontract.analytics') }}" class="btn btn-sm btn-outline-secondary">Reset Filters</a>
            </div>
        </form>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <!-- Active Vendors & WIP Ops -->
        <div class="col-xl-3 col-md-6">
            <div class="card border border-light-subtle shadow-sm h-100 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="fs-12 text-muted fw-bold text-uppercase d-block mb-1">Active Vendors & WIP Ops</span>
                        <h3 class="fw-bold text-dark mb-0">{{ number_format($overall['active_vendors_count']) }} <span class="fs-14 text-muted fw-normal">Vendors</span></h3>
                        <div class="fs-12 text-primary font-monospace mt-1">
                            <i class="feather-clock me-1"></i>{{ number_format($overall['active_ops_at_vendor']) }} ops currently at vendor
                        </div>
                    </div>
                    <div class="avatar-md bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center fs-20">
                        <i class="feather-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- On-Time Delivery % -->
        <div class="col-xl-3 col-md-6">
            <div class="card border border-light-subtle shadow-sm h-100 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="fs-12 text-muted fw-bold text-uppercase d-block mb-1">On-Time Delivery Rate</span>
                        <h3 class="fw-bold {{ $overall['on_time_delivery_pct'] >= 90 ? 'text-success' : 'text-warning' }} mb-0">
                            {{ number_format($overall['on_time_delivery_pct'], 1) }}%
                        </h3>
                        <div class="fs-12 text-muted font-monospace mt-1">
                            Avg Delay: <strong class="text-danger">{{ number_format($overall['avg_late_delay_days'], 1) }}d</strong> (Late Only)
                        </div>
                    </div>
                    <div class="avatar-md bg-soft-success text-success rounded-circle d-flex align-items-center justify-content-center fs-20">
                        <i class="feather-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acceptance Rate -->
        <div class="col-xl-3 col-md-6">
            <div class="card border border-light-subtle shadow-sm h-100 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="fs-12 text-muted fw-bold text-uppercase d-block mb-1">Quality Acceptance</span>
                        <h3 class="fw-bold text-success mb-0">{{ number_format($overall['acceptance_rate'], 1) }}%</h3>
                        <div class="fs-12 text-muted font-monospace mt-1">
                            Rejection: <strong class="text-danger">{{ number_format($overall['rejection_rate'], 1) }}%</strong> (Rework: {{ number_format($overall['rework_rate'], 1) }}%)
                        </div>
                    </div>
                    <div class="avatar-md bg-soft-info text-info rounded-circle d-flex align-items-center justify-content-center fs-20">
                        <i class="feather-shield"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- WIP Value at Vendor -->
        <div class="col-xl-3 col-md-6">
            <div class="card border border-light-subtle shadow-sm h-100 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="fs-12 text-muted fw-bold text-uppercase d-block mb-1">WIP Value at Vendor</span>
                        <h3 class="fw-bold text-dark mb-0 font-monospace">₹{{ number_format($overall['wip_value_at_vendor'], 2) }}</h3>
                        <div class="fs-12 text-muted font-monospace mt-1">
                            Qty: <strong>{{ number_format($overall['wip_quantity_at_vendor'], 2) }}</strong> units outside
                        </div>
                    </div>
                    <div class="avatar-md bg-soft-warning text-warning rounded-circle d-flex align-items-center justify-content-center fs-20">
                        <i class="feather-dollar-sign"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vendor Comparison Table -->
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0"><i class="feather-award me-2 text-warning"></i>Subcontract Vendor Comparison & SLA Scorecard</h5>
            <span class="fs-12 text-muted">Sorted by On-Time % and Quality Acceptance Rate</span>
        </div>

        <x-ui.odoo-form-ui type="table">
            <thead class="table-light fs-11 text-uppercase text-muted">
                <tr>
                    <th style="width: 25%">Vendor Name</th>
                    <th style="width: 10%" class="text-center">Active Ops</th>
                    <th style="width: 10%" class="text-center">Completed</th>
                    <th style="width: 12%" class="text-center">On-Time %</th>
                    <th style="width: 10%" class="text-center">Avg Delay</th>
                    <th style="width: 12%" class="text-center">Acceptance %</th>
                    <th style="width: 10%" class="text-center">Rework %</th>
                    <th style="width: 11%" class="text-end">Cost Var %</th>
                </tr>
            </thead>
            <tbody>
                @forelse($comparison as $row)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $row['vendor_name'] }}</div>
                            <span class="badge bg-light text-muted border font-monospace fs-10">{{ $row['vendor_code'] }}</span>
                        </td>
                        <td class="text-center font-monospace">{{ $row['active_ops'] }}</td>
                        <td class="text-center font-monospace text-muted">{{ $row['completed_ops'] }}</td>
                        <td class="text-center">
                            <span class="badge {{ $row['on_time_pct'] >= 90 ? 'bg-soft-success text-success' : ($row['on_time_pct'] >= 75 ? 'bg-soft-warning text-warning' : 'bg-soft-danger text-danger') }} border font-monospace px-2 py-1 fs-12">
                                {{ number_format($row['on_time_pct'], 1) }}%
                            </span>
                        </td>
                        <td class="text-center font-monospace {{ $row['avg_delay_days'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                            {{ $row['avg_delay_days'] > 0 ? '+' . number_format($row['avg_delay_days'], 1) . 'd' : '0d' }}
                        </td>
                        <td class="text-center font-monospace fw-bold text-success">
                            {{ number_format($row['acceptance_rate'], 1) }}%
                        </td>
                        <td class="text-center font-monospace {{ $row['rework_rate'] > 0 ? 'text-warning fw-bold' : 'text-muted' }}">
                            {{ number_format($row['rework_rate'], 1) }}%
                        </td>
                        <td class="text-end font-monospace {{ $row['cost_variance_pct'] > 0 ? 'text-danger' : 'text-success' }}">
                            {{ $row['cost_variance_pct'] > 0 ? '+' : '' }}{{ number_format($row['cost_variance_pct'], 1) }}%
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No vendor subcontract metrics found for the selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>
    </div>

    <!-- Delayed Vendor Operations Report -->
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0"><i class="feather-alert-triangle me-2 text-danger"></i>Delayed Vendor Operations & Successor Impact Report</h5>
            <span class="badge bg-soft-danger text-danger border font-monospace fs-11">{{ count($delayedOps) }} Overdue Ops</span>
        </div>

        <x-ui.odoo-form-ui type="table">
            <thead class="table-light fs-11 text-uppercase text-muted">
                <tr>
                    <th style="width: 15%">MO # & Product</th>
                    <th style="width: 20%">Operation</th>
                    <th style="width: 18%">Vendor</th>
                    <th style="width: 12%">Dispatch Date</th>
                    <th style="width: 12%">Expected Return</th>
                    <th style="width: 10%" class="text-center">Overdue</th>
                    <th style="width: 13%">Blocking Successor</th>
                </tr>
            </thead>
            <tbody>
                @forelse($delayedOps as $d)
                    <tr>
                        <td>
                            <a href="{{ route('production.orders.show', $d['order_id']) }}" class="fw-bold text-primary font-monospace">{{ $d['order_number'] }}</a>
                            <div class="fs-11 text-muted text-truncate" style="max-width: 180px;">{{ $d['product_name'] }}</div>
                        </td>
                        <td class="fw-semibold text-dark fs-12">{{ $d['operation_name'] }}</td>
                        <td>
                            <div class="fw-semibold text-dark fs-12">{{ $d['vendor_name'] }}</div>
                            <span class="fs-10 text-muted font-monospace">PO: {{ $d['po_number'] }}</span>
                        </td>
                        <td class="font-monospace fs-12 text-muted">{{ $d['dispatch_date'] }}</td>
                        <td class="font-monospace fs-12 text-muted">{{ $d['expected_return_date'] }}</td>
                        <td class="text-center">
                            <span class="badge bg-soft-danger text-danger border border-danger-subtle font-monospace fw-bold px-2 py-1">
                                +{{ $d['days_overdue'] }} Days Late
                            </span>
                        </td>
                        <td>
                            @if($d['blocking_successor'])
                                <span class="badge bg-soft-warning text-dark border border-warning fs-10" title="Successor operation blocked by vendor delay">
                                    <i class="feather-alert-octagon me-1"></i>Blocking {{ $d['blocking_successor'] }}
                                </span>
                            @else
                                <span class="text-muted fs-11">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4"><i class="feather-check-circle me-1 text-success"></i>No vendor operations are currently overdue.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>
    </div>

    <!-- Procurement Automation Throughput Breakdown -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border border-light-subtle shadow-sm p-4 h-100">
                <h6 class="fw-bold text-dark mb-3"><i class="feather-cpu me-2 text-primary"></i>Procurement Automation Mode Throughput</h6>
                <div class="d-flex justify-content-around text-center my-3">
                    <div class="p-3 bg-light rounded-3 flex-fill me-2">
                        <span class="fs-11 text-muted fw-bold text-uppercase d-block">Manual PR → PO</span>
                        <h4 class="fw-bold text-dark mb-0 font-monospace">{{ $overall['automation']['manual_pr_po_count'] }}</h4>
                    </div>
                    <div class="p-3 bg-light rounded-3 flex-fill me-2">
                        <span class="fs-11 text-muted fw-bold text-uppercase d-block">Auto-Draft PO</span>
                        <h4 class="fw-bold text-info mb-0 font-monospace">{{ $overall['automation']['auto_draft_po_count'] }}</h4>
                    </div>
                    <div class="p-3 bg-light rounded-3 flex-fill">
                        <span class="fs-11 text-muted fw-bold text-uppercase d-block">Auto-Approved PO</span>
                        <h4 class="fw-bold text-success mb-0 font-monospace">{{ $overall['automation']['auto_approved_po_count'] }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border border-light-subtle shadow-sm p-4 h-100">
                <h6 class="fw-bold text-dark mb-3"><i class="feather-slash me-2 text-warning"></i>Auto-Approval Fallback Exceptions</h6>
                @if(!empty($overall['automation']['fallback_reasons']))
                    <ul class="list-group list-group-flush fs-12">
                        @foreach($overall['automation']['fallback_reasons'] as $reason => $cnt)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-bottom">
                                <span class="text-dark"><i class="feather-info me-2 text-warning"></i>{{ $reason }}</span>
                                <span class="badge bg-soft-warning text-dark font-monospace">{{ $cnt }} Fallbacks</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="p-4 text-center text-muted fs-12 bg-light rounded-3">
                        <i class="feather-check-circle me-1 text-success"></i>No auto-approval fallbacks recorded. Policy rules operating at 100% throughput.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
