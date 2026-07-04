@extends('layouts.duralux')

@section('title', 'Production Order ' . $order->order_number . ' | SaaS ERP')

@section('page-actions')
    {{-- Back to List --}}
    <a href="{{ route('production.orders.index') }}" class="btn btn-sm btn-soft-secondary">
        <i class="feather-arrow-left me-1"></i>Back to List
    </a>

    @if($order->isDraft())
        <a href="{{ route('production.orders.edit', $order->id) }}" class="btn btn-sm btn-soft-primary">
            <i class="feather-edit me-1"></i>Edit Order
        </a>

        <form method="POST" action="{{ route('production.orders.release', $order->id) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-soft-success">
                <i class="feather-play-circle me-1"></i>Release Order
            </button>
        </form>

        <form method="POST" action="{{ route('production.orders.destroy', $order->id) }}" class="d-inline"
              onsubmit="return confirm('Delete this draft production order?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-soft-danger">
                <i class="feather-trash-2 me-1"></i>Delete Order
            </button>
        </form>
    @endif

    @if($order->isReleased() || $order->isInProgress())
        <button type="button" class="btn btn-sm btn-soft-primary" data-bs-toggle="modal" data-bs-target="#progressModal">
            <i class="feather-edit-3 me-1"></i>Log Progress
        </button>

        <button type="button" class="btn btn-sm btn-soft-info" data-bs-toggle="modal" data-bs-target="#issueModal">
            <i class="feather-log-in me-1"></i>Issue Materials
        </button>

        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#returnModal">
            <i class="feather-log-out me-1"></i>Return Materials
        </button>

        <button type="button" class="btn btn-sm btn-soft-warning text-dark" data-bs-toggle="modal" data-bs-target="#receiptModal">
            <i class="feather-download me-1"></i>Receive FG
        </button>

        <button type="button" class="btn btn-sm btn-soft-danger" data-bs-toggle="modal" data-bs-target="#scrapReworkModal">
            <i class="feather-alert-triangle me-1"></i>Log Scrap/Rework
        </button>

        <form method="POST" action="{{ route('production.orders.complete', $order->id) }}" class="d-inline"
              onsubmit="return confirm('Complete this Production Order? All operations must be completed.');">
            @csrf
            <button type="submit" class="btn btn-sm btn-soft-success">
                <i class="feather-check-circle me-1"></i>Complete Order
            </button>
        </form>

        <form method="POST" action="{{ route('production.orders.cancel', $order->id) }}" class="d-inline"
              onsubmit="return confirm('Cancel this Production Order?');">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="feather-slash me-1"></i>Cancel Order
            </button>
        </form>
    @endif

    @if($order->isCompleted())
        <form method="POST" action="{{ route('production.orders.close', $order->id) }}" class="d-inline"
              onsubmit="return confirm('Close and Archive this completed order? Costs will be locked.');">
            @csrf
            <button type="submit" class="btn btn-sm btn-soft-secondary">
                <i class="feather-archive me-1"></i>Close &amp; Archive
            </button>
        </form>
    @endif
@endsection


@section('content')
<div class="erp-single-panel bg-white">

    {{-- ── Success / Error Banners ──────────────────────────────────────── --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-success text-white me-3">
                    <i class="feather-check-circle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Success!</h6>
                    <p class="fs-12 mb-0">{{ session('success') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-danger text-white me-3">
                    <i class="feather-alert-triangle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                    <p class="fs-12 mb-0">{{ session('error') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── Header Identity Row ──────────────────────────────────────────── --}}
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <h4 class="fw-bold text-dark mb-0">Production Order ({{ $order->order_number }})</h4>
        <div>
            @if($order->isDraft())
                <span class="erp-badge-draft">Draft</span>
            @elseif($order->isReleased())
                <span class="erp-badge-pending">Released</span>
            @elseif($order->isInProgress())
                <span class="badge bg-soft-info text-info">In Progress</span>
            @elseif($order->isCompleted())
                <span class="erp-badge-active">Completed</span>
            @elseif($order->isClosed())
                <span class="badge bg-soft-dark text-dark">Closed</span>
            @elseif($order->isCancelled())
                <span class="badge bg-soft-danger text-danger">Cancelled</span>
            @endif
        </div>
    </div>

    {{-- ── Identity / KPI Grid ──────────────────────────────────────────── --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6 border-end">
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Finished Product:</span></div>
                <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $order->product->name }} ({{ $order->product->sku }})</span></div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">BOM Reference:</span></div>
                <div class="col-md-8">
                    <a href="{{ route('production.boms.show', $order->bom_id ?? 0) }}" class="fw-bold text-primary fs-13">
                        {{ $order->bom->bom_number ?? 'N/A' }} (v{{ $order->bom->version ?? '—' }})
                    </a>
                </div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Routing Reference:</span></div>
                <div class="col-md-8">
                    <a href="{{ route('production.routing.show', $order->routing_id ?? 0) }}" class="fw-bold text-primary fs-13">
                        {{ $order->routing->routing_number ?? 'N/A' }} — {{ $order->routing->name ?? '' }} (v{{ $order->routing->version ?? '—' }})
                    </a>
                </div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Source Plan:</span></div>
                <div class="col-md-8">
                    @if($order->plan)
                        <a href="{{ route('production.plans.show', $order->production_plan_id) }}" class="fw-bold text-primary fs-13">
                            {{ $order->plan->plan_number }}
                        </a>
                    @else
                        <span class="text-dark fw-bold fs-13">Direct Order (No Plan)</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Quantity Ordered:</span></div>
                <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ number_format($order->quantity_ordered, 2) }} units</span></div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Quantity Produced:</span></div>
                <div class="col-md-8">
                    @php $progressPct = $order->quantity_ordered > 0 ? min(100.0, ($order->quantity_produced / $order->quantity_ordered) * 100) : 0.0; @endphp
                    <span class="text-success fw-bold fs-13">{{ number_format($order->quantity_produced, 2) }} units</span>
                    <div class="progress mt-1" style="height:5px;">
                        <div class="progress-bar bg-success" style="width:{{ $progressPct }}%;"></div>
                    </div>
                    <div class="text-muted fs-11 mt-1">{{ round($progressPct, 1) }}% completed</div>
                </div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Scheduled Dates:</span></div>
                <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $order->start_date->format('Y-m-d') }} → {{ $order->end_date->format('Y-m-d') }}</span></div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Created By:</span></div>
                <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $order->creator->name ?? 'System' }} at {{ $order->created_at->format('Y-m-d H:i') }}</span></div>
            </div>
        </div>
    </div>

    {{-- ── Tab Navigation (BOM-style) ───────────────────────────────────── --}}
    <div class="erp-tabs-nav">
        <a class="erp-tabs-link active" id="btn-tab-overview"      onclick="switchTab('overview')">Overview</a>
        <a class="erp-tabs-link"        id="btn-tab-operations"    onclick="switchTab('operations')">Operations</a>
        <a class="erp-tabs-link"        id="btn-tab-reservations"  onclick="switchTab('reservations')">Reservations</a>
        <a class="erp-tabs-link"        id="btn-tab-issues"        onclick="switchTab('issues')">Material Issues</a>
        <a class="erp-tabs-link"        id="btn-tab-progress"      onclick="switchTab('progress')">Progress Logs</a>
        <a class="erp-tabs-link"        id="btn-tab-scrap"         onclick="switchTab('scrap')">Scrap &amp; Rework</a>
        <a class="erp-tabs-link"        id="btn-tab-cost"          onclick="switchTab('cost')">Cost Analysis</a>
        <a class="erp-tabs-link"        id="btn-tab-audit"         onclick="switchTab('audit')">Audit Trail</a>
    </div>

    {{-- ── Tab Content ──────────────────────────────────────────────────── --}}
    <div class="tab-content mt-3">

        {{-- Tab 1: Overview --}}
        <div class="tab-pane-custom" id="tab-overview">
            <div class="row g-4">
                <div class="col-md-8">
                    <h5 class="fw-bold text-dark mb-3">Shop Floor Remarks &amp; Notes</h5>
                    <p class="text-dark fs-13">{{ $order->description ?? 'No specific remarks or shop floor notes logged for this production order.' }}</p>

                    <h6 class="fw-bold text-muted text-uppercase fs-11 mb-3 mt-4">Actual Execution Timeline</h6>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="bg-light p-3 rounded">
                                <div class="text-muted fs-11 text-uppercase mb-1">Scheduled Window</div>
                                <div class="text-dark fw-bold fs-14">
                                    {{ $order->start_date->format('Y-m-d') }} → {{ $order->end_date->format('Y-m-d') }}
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light p-3 rounded">
                                <div class="text-muted fs-11 text-uppercase mb-1">Actual Execution Dates</div>
                                <div class="text-dark fw-bold fs-14">
                                    {{ $order->actual_start_date ? $order->actual_start_date->format('Y-m-d H:i') : '—' }}
                                    →
                                    {{ $order->actual_end_date ? $order->actual_end_date->format('Y-m-d H:i') : '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <h5 class="fw-bold text-dark mb-3">Frozen Engineering References</h5>
                    <div class="mb-3 pb-2 border-bottom">
                        <div class="text-muted fs-11 text-uppercase mb-1">BOM Version (Frozen)</div>
                        <a href="{{ route('production.boms.show', $order->bom_id ?? 0) }}" class="fw-bold text-primary">
                            {{ $order->bom->bom_number ?? 'BOM Reference' }} (v{{ $order->bom->version ?? '1.0' }})
                        </a>
                        <div class="fs-12 text-muted mt-1">{{ $order->bom->bom_name ?? 'Default BOM' }}</div>
                    </div>
                    <div class="mb-3 pb-2 border-bottom">
                        <div class="text-muted fs-11 text-uppercase mb-1">Routing Version (Frozen)</div>
                        <a href="{{ route('production.routing.show', $order->routing_id ?? 0) }}" class="fw-bold text-primary">
                            {{ $order->routing->routing_number ?? 'Routing Reference' }}
                        </a>
                        <div class="fs-12 text-muted mt-1">{{ $order->routing->name ?? 'Default Routing' }} (v{{ $order->routing->version ?? '1.0' }})</div>
                    </div>
                    <div>
                        <div class="text-muted fs-11 text-uppercase mb-1">Source Planning Order</div>
                        @if($order->plan)
                            <a href="{{ route('production.plans.show', $order->production_plan_id) }}" class="fw-bold text-primary">
                                {{ $order->plan->plan_number }}
                            </a>
                        @else
                            <span class="text-dark fw-bold">Direct Order (No Plan)</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab 2: Operations --}}
        <div class="tab-pane-custom d-none" id="tab-operations">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">Routing Operations &amp; Capacity Execution</h5>
                <span class="fs-12 text-muted">Operations must be processed sequentially.</span>
            </div>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:5%" class="text-center">Seq</th>
                            <th style="width:20%">Operation</th>
                            <th style="width:15%">Work Center</th>
                            <th style="width:12%">Machine</th>
                            <th style="width:12%" class="text-center">Planned Setup / Run</th>
                            <th style="width:12%" class="text-center">Actual Setup / Run</th>
                            <th style="width:12%" class="text-center">Produced / Scrap</th>
                            <th style="width:7%">Status</th>
                            <th style="width:5%" class="text-end">Log</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->operations as $op)
                            <tr>
                                <td class="text-center fw-semibold text-muted">#{{ $op->sequence }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $op->operation_number }}</div>
                                    <small class="text-muted">{{ $op->name }}</small>
                                </td>
                                <td>{{ $op->workCenter->name }}</td>
                                <td>{{ $op->machine->name ?? 'Any' }}</td>
                                <td class="text-center text-muted">{{ $op->setup_time_planned }}m / {{ $op->processing_time_planned }}m</td>
                                <td class="text-center fw-semibold text-dark">{{ $op->setup_time_actual }}m / {{ $op->processing_time_actual }}m</td>
                                <td class="text-center">
                                    <span class="text-success fw-bold">{{ number_format($op->quantity_produced, 2) }}</span>
                                    /
                                    <span class="text-danger">{{ number_format($op->quantity_scrapped + $op->quantity_rejected, 2) }}</span>
                                </td>
                                <td>
                                    @if($op->status === 'waiting')
                                        <span class="badge bg-secondary text-white">Waiting</span>
                                    @elseif($op->status === 'ready')
                                        <span class="badge bg-primary text-white">Ready</span>
                                    @elseif($op->status === 'running')
                                        <span class="badge bg-info text-white">Running</span>
                                    @elseif($op->status === 'paused')
                                        <span class="badge bg-warning text-dark">Paused</span>
                                    @elseif($op->status === 'completed')
                                        <span class="badge bg-success text-white">Completed</span>
                                    @else
                                        <span class="badge bg-light text-dark">{{ $op->status }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if(($order->isReleased() || $order->isInProgress()) && $op->status !== 'completed')
                                        <button type="button" class="btn btn-sm btn-primary"
                                                data-bs-toggle="modal" data-bs-target="#progressModal"
                                                onclick="document.getElementById('op_select_id').value = '{{ $op->id }}';">
                                            Log
                                        </button>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 3: Reservations --}}
        <div class="tab-pane-custom d-none" id="tab-reservations">
            <h5 class="fw-bold text-dark mb-3">Component Material Reservations</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:30%">Material Component</th>
                            <th style="width:15%" class="text-center">Planned Qty</th>
                            <th style="width:15%" class="text-center">Reserved Qty</th>
                            <th style="width:15%" class="text-center">Issued Qty</th>
                            <th style="width:10%">UOM</th>
                            <th style="width:15%" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->reservations as $res)
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">{{ $res->product->name }}</div>
                                    <small class="text-muted font-monospace fs-10">{{ $res->product->sku }}</small>
                                </td>
                                <td class="text-center fw-semibold text-dark">{{ number_format($res->quantity_planned, 4) }}</td>
                                <td class="text-center fw-bold" style="color: var(--bs-info);">{{ number_format($res->quantity_reserved, 4) }}</td>
                                <td class="text-center fw-bold text-success">{{ number_format($res->quantity_issued, 4) }}</td>
                                <td>{{ $res->uom->name }}</td>
                                <td class="text-end">
                                    @if($order->isReleased() || $order->isInProgress())
                                        <button type="button" class="btn btn-sm btn-outline-info me-1"
                                                data-bs-toggle="modal" data-bs-target="#issueModal"
                                                onclick="document.getElementById('issue_reservation_id').value = '{{ $res->id }}';">
                                            Issue
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal" data-bs-target="#returnModal"
                                                onclick="document.getElementById('return_reservation_id').value = '{{ $res->id }}';">
                                            Return
                                        </button>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 4: Material Issues --}}
        <div class="tab-pane-custom d-none" id="tab-issues">
            <h5 class="fw-bold text-dark mb-3">Shop Floor Material Issues &amp; Returns Log</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:13%">Date</th>
                            <th style="width:12%">SKU</th>
                            <th style="width:22%">Product Name</th>
                            <th style="width:10%" class="text-center">Qty</th>
                            <th style="width:10%">Type</th>
                            <th style="width:12%">Operator</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->issues as $iss)
                            <tr>
                                <td class="text-muted">{{ $iss->issued_at->format('Y-m-d H:i') }}</td>
                                <td class="fw-bold text-dark font-monospace fs-12">{{ $iss->product->sku }}</td>
                                <td>{{ $iss->product->name }}</td>
                                <td class="text-center fw-bold {{ $iss->quantity_issued < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($iss->quantity_issued, 4) }}
                                </td>
                                <td>
                                    @if($iss->issue_type === 'standard')
                                        <span class="badge bg-light text-success border border-success">Standard</span>
                                    @elseif($iss->issue_type === 'additional')
                                        <span class="badge bg-light text-warning border border-warning">Additional</span>
                                    @elseif($iss->issue_type === 'return')
                                        <span class="badge bg-light text-danger border border-danger">Return</span>
                                    @else
                                        <span class="badge bg-light text-dark">{{ $iss->issue_type }}</span>
                                    @endif
                                </td>
                                <td>{{ $iss->user->name ?? 'System' }}</td>
                                <td class="text-muted">{{ $iss->remarks ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="feather-info fs-20 d-block mb-2"></i>No material issue logs registered.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 5: Progress Logs --}}
        <div class="tab-pane-custom d-none" id="tab-progress">
            {{-- KPI Summary Row --}}
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="bg-light rounded p-3 text-center border">
                        <div class="text-muted fs-11 text-uppercase fw-bold mb-1">Planned Target</div>
                        <h3 class="text-dark fw-bold mb-0">{{ number_format($order->quantity_ordered, 2) }}</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="bg-soft-success rounded p-3 text-center border border-success">
                        <div class="text-success fs-11 text-uppercase fw-bold mb-1">Actual Produced</div>
                        <h3 class="text-success fw-bold mb-0">{{ number_format($order->quantity_produced, 2) }}</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="bg-soft-danger rounded p-3 text-center border border-danger">
                        <div class="text-danger fs-11 text-uppercase fw-bold mb-1">Scrapped Qty</div>
                        <h3 class="text-danger fw-bold mb-0">{{ number_format($order->quantity_scrapped, 2) }}</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="bg-soft-warning rounded p-3 text-center border border-warning">
                        <div class="text-warning fs-11 text-uppercase fw-bold mb-1">Rejected / Rework</div>
                        <h3 class="text-warning fw-bold mb-0">{{ number_format($order->quantity_rejected, 2) }}</h3>
                    </div>
                </div>
            </div>

            <h5 class="fw-bold text-dark mb-3">Finished Goods Receipts Log</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:18%">Receipt Date</th>
                            <th style="width:15%" class="text-center">Qty Received</th>
                            <th style="width:15%">Quality Status</th>
                            <th style="width:15%">Receiver</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->receipts as $rec)
                            <tr>
                                <td class="text-muted">{{ $rec->received_at->format('Y-m-d H:i') }}</td>
                                <td class="text-center fw-bold text-success">{{ number_format($rec->quantity_received, 2) }}</td>
                                <td>
                                    @if($rec->quality_status === 'passed')
                                        <span class="badge bg-success text-white">Passed</span>
                                    @elseif($rec->quality_status === 'quarantine')
                                        <span class="badge bg-warning text-dark">Quarantine</span>
                                    @elseif($rec->quality_status === 'failed')
                                        <span class="badge bg-danger text-white">Failed</span>
                                    @endif
                                </td>
                                <td>{{ $rec->user->name ?? 'System' }}</td>
                                <td class="text-muted">{{ $rec->remarks ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="feather-info fs-20 d-block mb-2"></i>No finished goods receipts logged.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 6: Scrap & Rework --}}
        <div class="tab-pane-custom d-none" id="tab-scrap">
            <div class="row g-4">
                <div class="col-md-6">
                    <h5 class="fw-bold text-dark mb-3">Scrap Log Entries</h5>
                    <div class="table-responsive">
                        <table class="erp-thin-table">
                            <thead>
                                <tr>
                                    <th style="width:20%">Date</th>
                                    <th style="width:30%">Item / Component</th>
                                    <th style="width:15%" class="text-center">Qty</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($order->scraps as $scr)
                                    <tr>
                                        <td class="text-muted">{{ $scr->recorded_at->format('m-d H:i') }}</td>
                                        <td>
                                            <span class="fw-bold text-dark">{{ $scr->product ? $scr->product->sku : 'Finished Good' }}</span>
                                            @if($scr->operation)
                                                <div class="text-muted fs-11">Op: {{ $scr->operation->operation_number }}</div>
                                            @endif
                                        </td>
                                        <td class="text-center text-danger fw-bold">{{ number_format($scr->quantity, 2) }}</td>
                                        <td class="text-muted fs-12">{{ $scr->reason ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No scrap logged.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5 class="fw-bold text-dark mb-3">Rework Events Track</h5>
                    <div class="table-responsive">
                        <table class="erp-thin-table">
                            <thead>
                                <tr>
                                    <th style="width:20%">Date</th>
                                    <th style="width:25%">Operation</th>
                                    <th style="width:12%" class="text-center">Qty</th>
                                    <th style="width:15%">Status</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($order->reworks as $rew)
                                    <tr>
                                        <td class="text-muted">{{ $rew->recorded_at->format('m-d H:i') }}</td>
                                        <td class="fw-bold text-dark">
                                            {{ $rew->operation ? $rew->operation->operation_number : 'Header Order' }}
                                        </td>
                                        <td class="text-center text-warning fw-bold">{{ number_format($rew->quantity, 2) }}</td>
                                        <td>
                                            @if($rew->status === 'completed')
                                                <span class="badge bg-success text-white">Resolved</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Rework Pending</span>
                                            @endif
                                        </td>
                                        <td class="text-muted fs-12">{{ $rew->reason ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No reworks tracked.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab 7: Cost Analysis --}}
        <div class="tab-pane-custom d-none" id="tab-cost">
            {{-- Cost KPI Row --}}
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="bg-light rounded p-4 text-center border">
                        <span class="text-muted fs-11 text-uppercase fw-bold">Total Planned Cost</span>
                        <h2 class="text-dark fw-bold mt-2 mb-0">${{ number_format($costs['totals']['planned'], 2) }}</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-light rounded p-4 text-center border">
                        <span class="text-muted fs-11 text-uppercase fw-bold">Total Actual Cost</span>
                        <h2 class="text-dark fw-bold mt-2 mb-0">${{ number_format($costs['totals']['actual'], 2) }}</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    @php $vVal = $costs['totals']['variance']; @endphp
                    <div class="bg-light rounded p-4 text-center border {{ $vVal > 0 ? 'border-danger' : ($vVal < 0 ? 'border-success' : '') }}">
                        <span class="text-muted fs-11 text-uppercase fw-bold">Variance</span>
                        <h2 class="fw-bold mt-2 mb-0 {{ $vVal > 0 ? 'text-danger' : ($vVal < 0 ? 'text-success' : 'text-muted') }}">
                            ${{ number_format($vVal, 2) }}
                            <span class="fs-12 fw-normal">({{ $costs['totals']['variance_percentage'] }}%)</span>
                        </h2>
                    </div>
                </div>
            </div>

            <h5 class="fw-bold text-dark mb-3">Variance Cost Analysis Matrix</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:35%">Cost Element</th>
                            <th style="width:20%" class="text-end">Planned Cost</th>
                            <th style="width:20%" class="text-end">Actual Cost</th>
                            <th style="width:25%" class="text-end">Variance ($ / %)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach([
                            ['label' => 'Material Costs',           'key' => 'material'],
                            ['label' => 'Labor Cost',               'key' => 'labor'],
                            ['label' => 'Machine Utilization Cost', 'key' => 'machine'],
                            ['label' => 'Work Center Overhead',     'key' => 'overhead'],
                        ] as $row)
                            <tr>
                                <td class="fw-bold text-dark">{{ $row['label'] }}</td>
                                <td class="text-end">${{ number_format($costs[$row['key']]['planned'], 2) }}</td>
                                <td class="text-end">${{ number_format($costs[$row['key']]['actual'], 2) }}</td>
                                <td class="text-end fw-bold {{ $costs[$row['key']]['variance'] > 0 ? 'text-danger' : 'text-success' }}">
                                    ${{ number_format($costs[$row['key']]['variance'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                        <tr class="table-light">
                            <td class="fw-bold text-dark text-uppercase fs-12">Total Cost</td>
                            <td class="text-end fw-bold text-dark">${{ number_format($costs['totals']['planned'], 2) }}</td>
                            <td class="text-end fw-bold text-dark">${{ number_format($costs['totals']['actual'], 2) }}</td>
                            <td class="text-end fw-bold {{ $costs['totals']['variance'] > 0 ? 'text-danger' : 'text-success' }}">
                                ${{ number_format($costs['totals']['variance'], 2) }}
                                ({{ $costs['totals']['variance_percentage'] }}%)
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 8: Audit Trail --}}
        <div class="tab-pane-custom d-none" id="tab-audit">
            <h5 class="fw-bold text-dark mb-3">Audit Logs Trail</h5>
            <ul class="list-unstyled mb-0 fs-13">
                <li class="mb-3 d-flex align-items-start">
                    <div class="avatar-text avatar-sm bg-soft-primary text-primary me-3 mt-1 rounded-circle">
                        <i class="feather-user fs-14"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark">Order Document Created</div>
                        <div class="text-muted fs-11">By: {{ $order->creator->name ?? 'System' }} at {{ $order->created_at->format('Y-m-d H:i:s') }}</div>
                    </div>
                </li>
                @if($order->released_at)
                    <li class="mb-3 d-flex align-items-start">
                        <div class="avatar-text avatar-sm bg-soft-success text-success me-3 mt-1 rounded-circle">
                            <i class="feather-play-circle fs-14"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark">Released to Shop Floor</div>
                            <div class="text-muted fs-11">By: {{ $order->releaser->name ?? 'System' }} at {{ $order->released_at->format('Y-m-d H:i:s') }}</div>
                        </div>
                    </li>
                @endif
                @if($order->completed_at)
                    <li class="mb-3 d-flex align-items-start">
                        <div class="avatar-text avatar-sm bg-soft-success text-success me-3 mt-1 rounded-circle">
                            <i class="feather-check-circle fs-14"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark">Production Order Completed</div>
                            <div class="text-muted fs-11">By: {{ $order->completer->name ?? 'System' }} at {{ $order->completed_at->format('Y-m-d H:i:s') }}</div>
                        </div>
                    </li>
                @endif
                @if($order->closed_at)
                    <li class="mb-3 d-flex align-items-start">
                        <div class="avatar-text avatar-sm bg-soft-dark text-dark me-3 mt-1 rounded-circle">
                            <i class="feather-archive fs-14"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark">Order Closed &amp; Archived</div>
                            <div class="text-muted fs-11">By: {{ $order->closer->name ?? 'System' }} at {{ $order->closed_at->format('Y-m-d H:i:s') }}</div>
                        </div>
                    </li>
                @endif
            </ul>
        </div>

    </div>{{-- end .tab-content --}}

    {{-- ── MODALS (using x-ui.modal component — body.appendChild fixes z-index) ── --}}

    {{-- Log Progress Modal --}}
    <x-ui.modal id="progressModal" title="Log Operation Execution" size="lg" class="text-start">
        <form method="POST" action="{{ route('production.orders.log-progress', $order->id) }}" id="progressForm">
            @csrf
            
            <x-ui.odoo-form-ui type="select" label="Select Operation" name="operation_id" id="op_select_id" :required="true">
                @foreach($order->operations as $op)
                    @if($op->status !== 'completed')
                        <option value="{{ $op->id }}">{{ $op->operation_number }} — {{ $op->name }}</option>
                    @endif
                @endforeach
            </x-ui.odoo-form-ui>

            <div class="row g-2 mb-1 fs-13 text-dark">
                <div class="col-4">
                    <x-ui.odoo-form-ui type="input" label="Qty Produced" name="quantity_produced" inputType="number" step="0.0001" value="0" :required="true" />
                </div>
                <div class="col-4">
                    <x-ui.odoo-form-ui type="input" label="Qty Rejected" name="quantity_rejected" inputType="number" step="0.0001" value="0" :required="true" />
                </div>
                <div class="col-4">
                    <x-ui.odoo-form-ui type="input" label="Qty Scrapped" name="quantity_scrapped" inputType="number" step="0.0001" value="0" :required="true" />
                </div>
            </div>

            <div class="row g-2 mb-1 fs-13 text-dark">
                <div class="col-6">
                    <x-ui.odoo-form-ui type="input" label="Setup Minutes" name="setup_minutes_logged" inputType="number" value="0" :required="true" />
                </div>
                <div class="col-6">
                    <x-ui.odoo-form-ui type="input" label="Run Minutes" name="run_minutes_logged" inputType="number" value="0" :required="true" />
                </div>
            </div>

            <x-ui.odoo-form-ui type="input" label="Remarks" name="remarks" placeholder="E.g. operator name, work center notes" />

            <div class="odoo-form-group">
                <label class="odoo-form-label">Completion</label>
                <div class="flex-grow-1">
                    <div class="form-check form-switch pt-1">
                        <input class="form-check-input" type="checkbox" name="complete_operation" value="1" id="complete_operation">
                        <label class="form-check-label fw-bold text-dark fs-12 ms-2" for="complete_operation">Mark Operation Completed (Ready for Next Sequence)</label>
                    </div>
                </div>
            </div>
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" onclick="document.getElementById('progressForm').submit();">Save Progress Log</button>
        </x-slot>
    </x-ui.modal>

    {{-- Issue Materials Modal --}}
    <x-ui.modal id="issueModal" title="Issue Raw Material Component" class="text-start">
        <form method="POST" action="{{ route('production.orders.issue', $order->id) }}" id="issueForm">
            @csrf
            
            <x-ui.odoo-form-ui type="select" label="Reservation" name="reservation_id" id="issue_reservation_id" :required="true">
                @foreach($order->reservations as $res)
                    <option value="{{ $res->id }}">
                        {{ $res->product->name }} ({{ $res->product->sku }}) — Reserved: {{ number_format($res->quantity_reserved, 2) }}
                    </option>
                @endforeach
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="input" label="Issue Qty" name="quantity" inputType="number" step="0.0001" :required="true" />
            
            <x-ui.odoo-form-ui type="input" label="Remarks" name="remarks" />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-info text-white" onclick="document.getElementById('issueForm').submit();">Log Material Issue</button>
        </x-slot>
    </x-ui.modal>

    {{-- Return Materials Modal --}}
    <x-ui.modal id="returnModal" title="Return Materials to Stock" class="text-start">
        <form method="POST" action="{{ route('production.orders.return', $order->id) }}" id="returnForm">
            @csrf

            <x-ui.odoo-form-ui type="select" label="Reservation" name="reservation_id" id="return_reservation_id" :required="true">
                @foreach($order->reservations as $res)
                    <option value="{{ $res->id }}">
                        {{ $res->product->name }} ({{ $res->product->sku }}) — Issued: {{ number_format($res->quantity_issued, 2) }}
                    </option>
                @endforeach
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="input" label="Return Qty" name="quantity" inputType="number" step="0.0001" :required="true" />

            <x-ui.odoo-form-ui type="input" label="Remarks" name="remarks" />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" onclick="document.getElementById('returnForm').submit();">Process Return</button>
        </x-slot>
    </x-ui.modal>

    {{-- Receive Finished Goods Modal --}}
    <x-ui.modal id="receiptModal" title="Receive Finished Goods" class="text-start">
        <form method="POST" action="{{ route('production.orders.receive-fg', $order->id) }}" id="receiptForm">
            @csrf
            
            <div class="mb-3 bg-light p-3 rounded fs-13 text-dark">
                <label class="form-label fw-bold text-muted fs-11 text-uppercase mb-1">Target Product</label>
                <div class="text-dark fw-bold">{{ $order->product->name }} ({{ $order->product->sku }})</div>
            </div>

            <x-ui.odoo-form-ui type="input" label="Receipt Qty" name="quantity_received" inputType="number" step="0.0001" :required="true" />

            <x-ui.odoo-form-ui type="select" label="Quality Status" name="quality_status" :required="true">
                <option value="passed">Passed (Standard Inventory)</option>
                <option value="quarantine">Quarantine (Under QA Inspection)</option>
                <option value="failed">Failed (Defective / Blocked)</option>
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="input" label="Remarks" name="remarks" />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning text-dark" onclick="document.getElementById('receiptForm').submit();">Confirm FG Receipt</button>
        </x-slot>
    </x-ui.modal>

    {{-- Log Scrap / Rework Modal --}}
    <x-ui.modal id="scrapReworkModal" title="Log Scrap / Rework Event" size="lg" class="text-start">
        {{-- Inner tab nav --}}
        <ul class="nav nav-tabs mb-3" id="scrapReworkTabNav" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="sr-scrap-tab" data-bs-toggle="tab" data-bs-target="#sr-scrap" type="button" role="tab">Log Scrap</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="sr-rework-tab" data-bs-toggle="tab" data-bs-target="#sr-rework" type="button" role="tab">Log Rework Loop</button>
            </li>
        </ul>

        <div class="tab-content" id="scrapReworkTabContent">
            {{-- Scrap Tab --}}
            <div class="tab-pane fade show active" id="sr-scrap" role="tabpanel">
                <form method="POST" action="{{ route('production.orders.log-scrap', $order->id) }}" id="scrapForm">
                    @csrf

                    <x-ui.odoo-form-ui type="select" label="Operation">
                        <option value="">Order Header (Whole assembly scrap)</option>
                        @foreach($order->operations as $op)
                            <option value="{{ $op->id }}">Op {{ $op->operation_number }} — {{ $op->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="select" label="Scrap Target">
                        <option value="">Finished Good ({{ $order->product->sku }})</option>
                        @foreach($order->reservations as $res)
                            <option value="{{ $res->product_id }}">{{ $res->product->name }} ({{ $res->product->sku }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="input" label="Scrap Qty" name="quantity" inputType="number" step="0.0001" :required="true" />

                    <x-ui.odoo-form-ui type="input" label="Reason" name="reason" placeholder="E.g. material defect, processing error" :required="true" />

                    <div class="text-end mt-3 border-top pt-2">
                        <button type="button" class="btn btn-light-brand me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Log Scrap</button>
                    </div>
                </form>
            </div>

            {{-- Rework Tab --}}
            <div class="tab-pane fade" id="sr-rework" role="tabpanel">
                <form method="POST" action="{{ route('production.orders.log-rework', $order->id) }}" id="reworkForm">
                    @csrf

                    <x-ui.odoo-form-ui type="select" label="Rework Target" name="operation_id" :required="true">
                        @foreach($order->operations as $op)
                            <option value="{{ $op->id }}">Op {{ $op->operation_number }} — {{ $op->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="input" label="Rework Qty" name="quantity" inputType="number" step="0.0001" :required="true" />

                    <x-ui.odoo-form-ui type="input" label="Rework Notes" name="reason" placeholder="Describe issue and corrective actions" :required="true" />

                    <div class="text-end mt-3 border-top pt-2">
                        <button type="button" class="btn btn-light-brand me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning text-dark">Log Rework Loop</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Empty footer slot --}}
        <x-slot name="footer"></x-slot>
    </x-ui.modal>

</div>{{-- end .erp-single-panel --}}

<script>
    function switchTab(tabId) {
        // Remove active from all tab links
        document.querySelectorAll('.erp-tabs-link').forEach(link => link.classList.remove('active'));
        document.getElementById('btn-tab-' + tabId).classList.add('active');

        // Hide all panes, show target
        document.querySelectorAll('.tab-pane-custom').forEach(pane => pane.classList.add('d-none'));
        document.getElementById('tab-' + tabId).classList.remove('d-none');
    }
</script>
@endsection
