@extends('layouts.duralux')

@section('title', 'Rework Execution Track | SaaS ERP')
@section('page-title', 'Rework Execution Shopfloor')
@section('breadcrumb', 'Rework Details')

@section('page-actions')
    <a href="{{ route('production.rework.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>{{ __('production.back_to_list') }}</a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">

        {{-- Detail Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h4 class="fw-bold text-dark mb-1">Rework Order: {{ $rework->rework_number }}</h4>
                <div class="text-muted fs-12">
                    Linked Non-Conformance Defect: 
                    <strong class="text-dark">
                        @if($rework->ncr)
                            <a href="{{ route('production.ncrs.show', $rework->ncr->id) }}" class="text-primary">
                                {{ $rework->ncr->ncr_number }}
                            </a>
                        @else
                            —
                        @endif
                    </strong>
                </div>
                <div class="text-muted fs-12 mt-1">
                    Original Production Order: 
                    <strong class="text-dark">
                        @if($rework->originalOrder)
                            <a href="{{ route('production.orders.show', $rework->originalOrder->id) }}" class="text-primary fw-bold">
                                {{ $rework->originalOrder->order_number }}
                            </a>
                            @if($rework->originalOrder->product)
                                <span class="text-muted fw-normal ms-1">
                                    (Product: <strong>{{ $rework->originalOrder->product->name }}</strong> — {{ $rework->originalOrder->product->sku }})
                                </span>
                            @endif
                        @else
                            —
                        @endif
                    </strong>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                @if(in_array($rework->status, ['draft', 'scheduled', 'running']))
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#failReworkModal">
                        <i class="feather-x-circle me-1"></i>Fail Rework &amp; Move to Scrap
                    </button>
                @endif
                @php
                    $badgeClass = match($rework->status) {
                        'completed' => 'bg-soft-success text-success',
                        'failed' => 'bg-soft-danger text-danger',
                        'running' => 'bg-soft-primary text-primary',
                        default => 'bg-soft-secondary text-secondary',
                    };
                @endphp
                <span class="badge {{ $badgeClass }} px-3 py-1.5 rounded-pill text-uppercase">{{ $rework->status === 'failed' ? 'FAILED / SCRAPPED' : $rework->status }}</span>
            </div>
        </div>

        {{-- Costs & Time Summary --}}
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="border p-3 rounded text-center bg-light-soft">
                    <span class="text-muted fs-10 text-uppercase d-block mb-1">Rework Cost Estimate</span>
                    <h5 class="fw-bold text-dark mb-0">{{ format_currency($rework->cost_estimate) }}</h5>
                </div>
            </div>
            <div class="col-md-2">
                <div class="border p-3 rounded text-center border-warning bg-soft-warning">
                    <span class="text-warning-emphasis fs-10 text-uppercase d-block mb-1">Actual Rework Cost</span>
                    <h5 class="fw-bold text-warning mb-0">{{ format_currency($rework->actual_cost) }}</h5>
                </div>
            </div>
            <div class="col-md-2">
                <div class="border p-3 rounded text-center bg-light-soft">
                    <span class="text-muted fs-10 text-uppercase d-block mb-1">Material Cost Issued</span>
                    <h5 class="fw-bold text-primary mb-0">{{ format_currency($rework->material_cost) }}</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border p-3 rounded text-center bg-light-soft">
                    <span class="text-muted fs-10 text-uppercase d-block mb-1">Labor Hours Logged</span>
                    <h5 class="fw-bold text-dark mb-0">{{ $rework->formatted_labor_hours }}</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border p-3 rounded text-center bg-light-soft">
                    <span class="text-muted fs-10 text-uppercase d-block mb-1">Machine Hours Logged</span>
                    <h5 class="fw-bold text-dark mb-0">{{ $rework->formatted_machine_hours }}</h5>
                </div>
            </div>
        </div>

        {{-- Material Consumption Section --}}
        <div class="border-top pt-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0">Rework Material Consumption</h5>
                    <span class="text-muted fs-11">Materials issued from inventory stores directly to this rework order via central Store Material Requests</span>
                </div>
                @if(in_array($rework->status, ['draft', 'scheduled', 'running']))
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#requestMaterialModal">
                        <i class="feather-clipboard me-1"></i>Request Material from Store
                    </button>
                @endif
            </div>

            {{-- Pending Requisition Slips sent to Store --}}
            @if($rework->requisitionSlips->isNotEmpty())
                <div class="mb-3 bg-light rounded p-2 border">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fs-11 fw-bold text-uppercase text-muted"><i class="feather-file-text me-1"></i>Store Requisition Slips (Material Requests)</span>
                        <a href="{{ route('inventory.material-requests.index') }}" class="fs-11 text-primary text-decoration-none" target="_blank">
                            View Store Queue <i class="feather-external-link ms-1"></i>
                        </a>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($rework->requisitionSlips as $slip)
                            @php
                                $badgeClass = match(strtolower($slip->status)) {
                                    'issued', 'completed' => 'bg-soft-success text-success',
                                    'partial', 'partially issued' => 'bg-soft-info text-info',
                                    default => 'bg-soft-warning text-warning',
                                };
                            @endphp
                            <a href="{{ route('inventory.material-requests.show', $slip->id) }}" class="badge bg-white text-dark border p-2 text-decoration-none d-flex align-items-center gap-2" title="Click to view Store Material Request details">
                                <span class="font-monospace fw-bold text-primary">{{ $slip->requisition_number }}</span>
                                <span class="badge {{ $badgeClass }} fs-10 text-uppercase">{{ $slip->status }}</span>
                                <span class="text-muted fs-11">({{ $slip->items->count() }} {{ Str::plural('item', $slip->items->count()) }})</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 25%">Material / Item</th>
                            <th style="width: 15%">Warehouse</th>
                            <th style="width: 15%">Quantity</th>
                            <th style="width: 15%">Unit Valuation</th>
                            <th style="width: 15%">Total Cost</th>
                            <th style="width: 15%">Issued At / By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rework->issues as $issue)
                            <tr>
                                <td class="align-middle">
                                    <div class="fw-bold text-dark fs-13">{{ $issue->product->name ?? 'Unknown Product' }}</div>
                                    <div class="text-muted fs-11 font-monospace">{{ $issue->product->sku ?? '' }}</div>
                                </td>
                                <td class="align-middle text-muted fs-12">
                                    {{ $issue->warehouse->name ?? '—' }}
                                </td>
                                <td class="align-middle fw-bold font-monospace fs-12">
                                    {{ number_format($issue->quantity_issued, 2) }} {{ $issue->product->uom?->name ?? 'Units' }}
                                </td>
                                <td class="align-middle font-monospace fs-12 text-muted">
                                    {{ format_currency($issue->quantity_issued > 0 ? ($issue->total_cost / $issue->quantity_issued) : 0.0) }}
                                </td>
                                <td class="align-middle fw-bold font-monospace fs-12 text-primary">
                                    {{ format_currency($issue->total_cost) }}
                                </td>
                                <td class="align-middle text-muted fs-11">
                                    <div>{{ $issue->issued_at?->format('d M Y, H:i') ?? '—' }}</div>
                                    <span class="badge bg-soft-secondary text-secondary fs-10">{{ $issue->user->name ?? 'System' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted fs-12">
                                    <i class="feather-box me-1"></i>No additional materials have been issued for this rework order yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        {{-- Operations queue list --}}
        <div class="border-top pt-4">
            <h5 class="fw-bold text-dark mb-3">Rework Operations Execution Queue</h5>
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 8%">{{ __('production.js_sequence_label') }}</th>
                            <th style="width: 28%">Operation Stage Detail</th>
                            <th style="width: 28%">Work Center &amp; Machine Rates</th>
                            <th style="width: 12%">{{ __('production.status') }}</th>
                            <th style="width: 24%" class="text-end">Execution Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rework->operations as $op)
                            <tr>
                                <td class="align-middle fw-bold font-monospace text-muted fs-12">{{ $op->sequence }}</td>
                                <td class="align-middle">
                                    <div class="fw-bold text-dark fs-13">{{ $op->name }}</div>
                                    @if($op->setup_time_actual > 0 || $op->processing_time_actual > 0)
                                        <div class="text-muted fs-10">
                                            Setup: {{ number_format($op->setup_time_actual, 1) }}m | Run: {{ number_format($op->processing_time_actual * 60, 1) }}m
                                        </div>
                                    @endif
                                </td>
                                <td class="align-middle text-muted fs-12">
                                    <div class="fw-semibold text-dark">{{ $op->workCenter->name ?? '—' }}</div>
                                    @if($op->machine)
                                        <div class="text-primary fs-11 mt-0.5">
                                            <i class="feather-cpu me-1"></i>{{ $op->machine->name }} <span class="font-monospace">({{ $op->machine->code }})</span>
                                        </div>
                                    @elseif($op->workCenter && $op->workCenter->machines->count() > 0)
                                        <div class="text-muted fs-11 mt-0.5 fst-italic">
                                            <i class="feather-cpu me-1"></i>No machine assigned
                                        </div>
                                    @endif
                                    @if($op->workCenter)
                                        <div class="fs-10 text-muted mt-0.5">
                                            Labor: {{ format_currency($op->workCenter->cost_per_hour) }}/hr
                                            @if($op->workCenter->overhead_rate > 0)
                                                | OH: {{ format_currency($op->workCenter->overhead_rate) }}/hr
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    @php
                                        $opClass = match($op->status) {
                                            'waiting' => 'bg-soft-secondary text-secondary',
                                            'running' => 'bg-soft-primary text-primary',
                                            'completed' => 'bg-soft-success text-success',
                                            default => 'bg-soft-dark text-dark',
                                        };
                                    @endphp
                                    <span class="badge {{ $opClass }} text-uppercase fs-10">{{ $op->status }}</span>
                                </td>
                                <td class="align-middle text-end">
                                    @if($op->status === 'waiting')
                                        <form method="POST" action="{{ route('production.quality.rework.ops.start', $op->id) }}" class="d-inline-flex align-items-center gap-1 justify-content-end">
                                            @csrf
                                            @if($op->workCenter && $op->workCenter->machines->count() > 1)
                                                <select name="machine_id" class="form-select form-select-xs font-monospace fs-11" style="width: 140px; height: 26px; padding: 2px 4px;" title="Select Machine">
                                                    @foreach($op->workCenter->machines as $wcMach)
                                                        <option value="{{ $wcMach->id }}" {{ $op->machine_id == $wcMach->id ? 'selected' : '' }}>
                                                            {{ Str::limit($wcMach->name, 18) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @elseif($op->workCenter && $op->workCenter->machines->count() === 1)
                                                <input type="hidden" name="machine_id" value="{{ $op->workCenter->machines->first()->id }}">
                                            @endif
                                            <button type="submit" class="btn btn-xs btn-primary" style="height: 26px;">
                                                <i class="feather-play me-1"></i>Start Op
                                            </button>
                                        </form>
                                    @elseif($op->status === 'running')
                                        <form method="POST" action="{{ route('production.quality.rework.ops.complete', $op->id) }}" class="d-inline-block">
                                            @csrf
                                            <div class="d-flex align-items-center gap-1 justify-content-end">
                                                <input type="number" step="0.1" min="0" name="setup_time_actual" class="form-control form-control-sm font-monospace" placeholder="Setup m" style="width: 78px; height: 26px; padding: 2px 5px;" title="Setup time in minutes">
                                                <input type="number" step="0.1" min="0" name="processing_time_actual" class="form-control form-control-sm font-monospace" placeholder="Run m" style="width: 78px; height: 26px; padding: 2px 5px;" title="Actual processing run time in minutes">
                                                <button type="submit" class="btn btn-xs btn-success" style="height: 26px;">
                                                    <i class="feather-check me-1"></i>{{ __('production.complete') }}
                                                </button>
                                            </div>
                                        </form>
                                    @else
                                        <span class="text-success fw-bold fs-12"><i class="feather-check-circle me-1"></i>{{ __('production.completed_schedules') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>
    </div>

    {{-- Request Material Modal (Store MR Pattern) --}}
    @if(in_array($rework->status, ['draft', 'scheduled', 'running']))
        <x-ui.modal 
            id="requestMaterialModal" 
            title="<span class='text-primary fw-bold'><i class='feather-clipboard me-2'></i>Request Raw Material from Store</span>" 
            :centered="true" 
            formAction="{{ route('production.quality.rework.request-material', $rework->id) }}" 
            submitText="Send Requisition to Store">
            
            <div class="alert alert-soft-info border-info-subtle mb-3 fs-12 py-2">
                <i class="feather-info me-1"></i>
                This request generates an official <strong>Material Request (MR)</strong> slip. The Storekeeper will review inventory stock, allocate warehouse/bin, and issue materials from the store.
            </div>

            <div class="mb-3">
                <label class="form-label fs-12 fw-semibold text-dark">Material / Component <span class="text-danger">*</span></label>
                <select name="product_id" class="form-select form-select-sm" required>
                    <option value="">-- Select Material Item --</option>
                    @foreach($products ?? [] as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku ?? $product->code }})</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fs-12 fw-semibold text-dark">Quantity to Request <span class="text-danger">*</span></label>
                <input type="number" step="0.0001" min="0.0001" name="quantity" class="form-control form-control-sm font-monospace" placeholder="e.g. 2.50" required>
            </div>

            <div class="mb-3">
                <label class="form-label fs-12 fw-semibold text-dark">Target Rework Stage (Optional)</label>
                <select name="rework_operation_id" class="form-select form-select-sm">
                    <option value="">-- General Rework Consumption --</option>
                    @foreach($rework->operations as $stage)
                        <option value="{{ $stage->id }}">Stage {{ $stage->sequence }}: {{ $stage->name }}</option>
                    @endforeach
                </select>
            </div>

            <x-ui.textarea 
                name="remarks" 
                label="Requisition Remarks / Reason" 
                placeholder="Reason additional material is required for rework..." 
                rows="2" />
        </x-ui.modal>
    @endif

    {{-- Fail Rework Confirmation Modal --}}
    @if(in_array($rework->status, ['draft', 'scheduled', 'running']))
        <x-ui.modal 
            id="failReworkModal" 
            title="<span class='text-danger fw-bold'><i class='feather-alert-triangle me-2'></i>Fail Rework &amp; Move to Scrap</span>" 
            :centered="true" 
            formAction="{{ route('production.quality.rework.fail', $rework->id) }}" 
            submitText="Confirm Failure &amp; Scrap">
            
            <div class="alert alert-soft-warning border-warning-subtle mb-3 fs-12">
                <i class="feather-alert-octagon me-1"></i>
                <strong>Warning:</strong> This action will permanently convert the unresolved rework quantity to <strong>Scrap</strong>. The quantity will <strong>not</strong> return to available production output.
            </div>

            <x-ui.textarea 
                name="reason" 
                label="Failure Reason / Remarks" 
                placeholder="Provide detailed explanation why rework attempt failed..." 
                :required="true" 
                rows="3" />
        </x-ui.modal>
    @endif
@endsection
