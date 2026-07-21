@extends('layouts.duralux')

@section('title', __('production.production_plan_details', ['number' => $plan->plan_number]) . ' | SaaS ERP')

@section('page-actions')
    <a href="{{ route('production.plans.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>{{ __('production.back_to_list') }}
    </a>

    @if($plan->isDraft())
        <a href="{{ route('production.plans.edit', $plan->id) }}" class="btn btn-primary me-2">
            <i class="feather-edit me-2"></i>{{ __('production.edit_plan') }}
        </a>
        <form method="POST" action="{{ route('production.plans.submit', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-info">
                <i class="feather-send me-2"></i>{{ __('production.submit_for_approval') }}
            </button>
        </form>
    @endif

    @if($plan->isPendingApproval())
        <form method="POST" action="{{ route('production.plans.approve', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="feather-check-circle me-2"></i>{{ __('production.approve_plan') }}
            </button>
        </form>
        <form method="POST" action="{{ route('production.plans.reject', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-danger">
                <i class="feather-x-circle me-2"></i>{{ __('production.reject') }}
            </button>
        </form>
    @endif

    @can('runMrp', $plan)
        <form method="POST" action="{{ route('production.plans.run-mrp', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-warning text-dark">
                <i class="feather-cpu me-2"></i>{{ $plan->isMrpGenerated() ? __('production.refresh_mrp_snapshot') : __('production.run_mrp_engine') }}
            </button>
        </form>
    @endcan

    @if($plan->isApproved() || $plan->isMrpGenerated())
        <form method="POST" action="{{ route('production.plans.create-order', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="feather-file-text me-2"></i>{{ __('production.generate_production_order') }}
            </button>
        </form>
        <form method="POST" action="{{ route('production.plans.release', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="feather-play-circle me-2"></i>{{ __('production.release_to_shop_floor') }}
            </button>
        </form>
    @endif

    @if($plan->isReleased())
        <form method="POST" action="{{ route('production.plans.complete', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="feather-check me-2"></i>{{ __('production.complete_plan') }}
            </button>
        </form>
    @endif

    @if($plan->isCompleted())
        <form method="POST" action="{{ route('production.plans.close', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-dark">
                <i class="feather-archive me-2"></i>{{ __('production.close_archive') }}
            </button>
        </form>
    @endif

    @if(!$plan->isClosed() && !$plan->isCompleted() && !$plan->isCancelled())
        <form method="POST" action="{{ route('production.plans.cancel', $plan->id) }}" class="d-inline"
              onsubmit="return confirm('{{ __('production.confirm_cancel_plan') }}');">
            @csrf
            <button type="submit" class="btn btn-outline-danger">
                <i class="feather-slash me-2"></i>{{ __('production.cancel_plan') }}
            </button>
        </form>
    @endif
@endsection

@section('content')
<div class="erp-single-panel bg-white">

    {{-- Alerts --}}
    @if(session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif

    @if(session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    {{-- Warnings Diagnostics Panel --}}
    @if(count($warnings) > 0)
        <div class="alert alert-danger border-danger bg-soft-danger mb-4 rounded shadow-sm" role="alert">
            <div class="d-flex align-items-center mb-2">
                <i class="feather-alert-triangle me-2 fs-18 text-danger"></i>
                <span class="fw-bold text-danger">{{ __('production.planning_resource_warnings') }} ({{ count($warnings) }})</span>
            </div>
            <ul class="mb-0 ps-3">
                @foreach($warnings as $warn)
                    <li class="text-{{ $warn['severity'] === 'danger' ? 'danger fw-semibold' : 'dark' }} mb-1 fs-12">
                        {{ $warn['message'] }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Header Identity Row --}}
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <h4 class="fw-bold text-dark mb-0">{{ $plan->plan_number }} — {{ $plan->name }}</h4>
        <div>
            @if($plan->status === 'draft')
                <span class="erp-badge-draft">{{ __('production.draft') }}</span>
            @elseif($plan->status === 'pending_approval')
                <span class="erp-badge-pending">{{ __('production.pending') }}</span>
            @elseif($plan->status === 'approved')
                <span class="erp-badge-active">{{ __('production.approved') }}</span>
            @elseif($plan->status === 'mrp_generated')
                <span class="badge bg-soft-info text-info">{{ __('production.mrp_generated') }}</span>
            @elseif($plan->status === 'released')
                <span class="badge bg-soft-primary text-primary">{{ __('production.released') }}</span>
            @elseif($plan->status === 'completed')
                <span class="erp-badge-active">{{ __('production.completed') }}</span>
            @elseif($plan->status === 'closed')
                <span class="badge bg-soft-dark text-dark">{{ __('production.closed') }}</span>
            @else
                <span class="badge bg-soft-danger text-danger">{{ __('production.cancelled') }}</span>
            @endif
        </div>
    </div>

    {{-- Identity Grid --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6 border-end">
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.item_to_produce') }}:</span></div>
                <div class="col-md-8">
                    <span class="text-dark fw-bold fs-13">{{ $plan->product->name }}</span>
                    <small class="text-muted font-monospace d-block fs-10">{{ $plan->product->sku }}</small>
                </div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.target_quantity') }}:</span></div>
                <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ number_format($plan->quantity, 2) }}</span></div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.planned_window') }}:</span></div>
                <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $plan->start_date->format('d M Y') }} → {{ $plan->end_date->format('d M Y') }}</span></div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.created_by') }}:</span></div>
                <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $plan->creator ? $plan->creator->name : 'System' }} on {{ $plan->created_at->format('d/m/Y H:i') }}</span></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.bom_reference') }}:</span></div>
                <div class="col-md-8">
                    @if($plan->bom)
                        <a href="{{ route('production.boms.show', $plan->bom_id) }}" class="fw-bold text-primary fs-13">
                            {{ $plan->bom->bom_number }} (v{{ $plan->bom->version }})
                        </a>
                        <small class="text-muted d-block fs-11">{{ __('production.frozen_at_plan_creation') }}</small>
                    @else
                        <span class="text-muted fs-13">{{ __('production.none_assigned') }}</span>
                    @endif
                </div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.routing_reference') }}:</span></div>
                <div class="col-md-8">
                    @if($plan->routing)
                        <a href="{{ route('production.routing.show', $plan->routing_id) }}" class="fw-bold text-primary fs-13">
                            {{ $plan->routing->routing_number }} (v{{ $plan->routing->version }})
                        </a>
                        <small class="text-muted d-block fs-11">{{ __('production.frozen_at_plan_creation') }}</small>
                    @else
                        <span class="text-muted fs-13">{{ __('production.none_assigned') }}</span>
                    @endif
                </div>
            </div>
            @if($plan->approved_by)
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.approved_by') }}:</span></div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">{{ $plan->approver ? $plan->approver->name : 'N/A' }}</span>
                        <small class="text-muted d-block fs-11">{{ $plan->approved_at ? $plan->approved_at->format('d/m/Y H:i') : '' }}</small>
                    </div>
                </div>
            @endif
            @if($plan->description)
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.description') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fs-13">{{ $plan->description }}</span></div>
                </div>
            @endif
        </div>
    </div>

    {{-- Tab Navigation (BOM-style) --}}
    <div class="erp-tabs-nav">
        <a class="erp-tabs-link active" id="btn-tab-mrp"        onclick="switchTab('mrp')"><i class="feather-cpu me-2 fs-13"></i>{{ __('production.material_requirements_mrp') }}</a>
        <a class="erp-tabs-link"        id="btn-tab-summary"    onclick="switchTab('summary')"><i class="feather-info me-2 fs-13"></i>{{ __('production.mrp_summary') }}</a>
        <a class="erp-tabs-link"        id="btn-tab-operations" onclick="switchTab('operations')"><i class="feather-sliders me-2 fs-13"></i>{{ __('production.capacity_operations') }}</a>
        <a class="erp-tabs-link"        id="btn-tab-details"    onclick="switchTab('details')"><i class="feather-activity me-2 fs-13"></i>{{ __('production.details_logs') }}</a>
    </div>

    {{-- Tab Content --}}
    <div class="tab-content mt-3">

        {{-- Tab 1: Material Requirements (MRP) --}}
        <div class="tab-pane-custom" id="tab-mrp">
            @if($plan->requirements->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="feather-cpu fs-32 mb-2 d-block text-muted"></i>
                    <p class="mb-2 fw-semibold">{{ __('production.no_snapshot_mrp') }}</p>
                    <p class="fs-12 mb-3">{{ __('production.run_mrp_to_explode') }}</p>
                    @can('runMrp', $plan)
                        <form method="POST" action="{{ route('production.plans.run-mrp', $plan->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-warning text-dark px-4 btn-sm">
                                <i class="feather-play me-2"></i>{{ __('production.run_mrp_now') }}
                            </button>
                        </form>
                    @endcan
                </div>
            @else
                <h5 class="fw-bold text-dark mb-3">{{ __('production.material_requirements_snapshot') }}</h5>
                <div class="table-responsive">
                    <table class="erp-thin-table fs-12">
                        <thead>
                            <tr>
                                <th style="width:4%">{{ __('production.lv') }}</th>
                                <th style="width:26%">{{ __('production.component_item') }}</th>
                                <th style="width:12%" class="text-end">{{ __('production.required_qty') }}</th>
                                <th style="width:12%" class="text-end">{{ __('production.available_qty') }}</th>
                                <th style="width:12%" class="text-end">{{ __('production.reserved_qty') }}</th>
                                <th style="width:12%" class="text-end text-danger">{{ __('production.shortage_qty') }}</th>
                                <th style="width:8%">{{ __('production.uom') ?? 'UOM' }}</th>
                                <th>{{ __('production.source_item') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($plan->requirements as $req)
                                @php
                                    $level = (int) $req->bom_level;
                                    $padding = max(0, ($level - 1) * 24);
                                    $hasShortage = (float) $req->shortage_quantity > 0;
                                    $isChild = $level > 1;
                                    $rowClass = $hasShortage ? 'bg-soft-danger' : ($isChild ? 'table-light-soft text-muted' : '');
                                    $componentClass = $hasShortage ? 'fw-bold text-danger' : ($isChild ? 'fw-normal text-dark' : 'fw-bold text-dark');
                                    $skuClass = $hasShortage ? 'text-danger opacity-75 font-monospace fs-10' : 'text-muted font-monospace fs-10';
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td class="fw-bold {{ $hasShortage ? 'text-danger' : '' }}">{{ $level }}</td>
                                    <td>
                                        <div style="padding-left: {{ $padding }}px;" class="d-flex align-items-start">
                                            @if($isChild)
                                                <i class="feather-corner-down-right {{ $hasShortage ? 'text-danger' : 'text-muted' }} me-2 mt-1" style="font-size: 11px;"></i>
                                            @endif
                                            <div class="d-flex flex-column">
                                                <span class="{{ $componentClass }}">{{ $req->product->name }}</span>
                                                <small class="{{ $skuClass }}">{{ $req->product->sku }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end fw-semibold {{ $hasShortage ? 'text-danger' : 'text-dark' }}">{{ number_format($req->required_quantity, 2) }}</td>
                                    <td class="text-end {{ $hasShortage ? 'text-danger' : 'text-muted' }}">{{ number_format($req->available_quantity, 2) }}</td>
                                    <td class="text-end {{ $hasShortage ? 'text-danger' : 'text-muted' }}">{{ number_format($req->reserved_quantity, 2) }}</td>
                                    <td class="text-end text-danger fw-bold">{{ number_format($req->shortage_quantity, 2) }}</td>
                                    <td class="{{ $hasShortage ? 'text-danger' : '' }}">{{ $req->uom ? $req->uom->code : 'PCS' }}</td>
                                    <td class="{{ $hasShortage ? 'text-danger' : 'text-muted' }}">{{ $req->sourceItem ? $req->sourceItem->name : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Tab 2: MRP Summary --}}
        <div class="tab-pane-custom d-none" id="tab-summary">
            @if(!$summary)
                <div class="text-center py-5 text-muted">
                    <i class="feather-info fs-32 mb-2 d-block"></i>
                    <p class="mb-0">{{ __('production.mrp_summary_appear') }}</p>
                </div>
            @else
                <h5 class="fw-bold text-dark mb-3">{{ __('production.mrp_execution_summary') }}</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="bg-light rounded p-3 text-center border">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.total_components') }}</span>
                            <span class="fw-bold text-dark fs-18">{{ $summary['total_components'] }}</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light rounded p-3 text-center border">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.subassemblies') }}</span>
                            <span class="fw-bold text-dark fs-18">{{ $summary['total_subassemblies'] }}</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light rounded p-3 text-center border">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.operations') }}</span>
                            <span class="fw-bold text-dark fs-18">{{ $summary['total_operations'] }}</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-soft-danger rounded p-3 text-center border border-danger">
                            <span class="text-danger fs-11 text-uppercase d-block mb-1">{{ __('production.warnings') }}</span>
                            <span class="fw-bold text-danger fs-18">{{ $summary['warnings_count'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="bg-soft-success rounded p-3 text-center border border-success">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.est_material_cost') }}</span>
                            <h4 class="fw-bold text-success mt-1 mb-0">{{ format_currency($summary['estimated_material_cost']) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-soft-primary rounded p-3 text-center border border-primary">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.manufacturing_overhead') }}</span>
                            <h4 class="fw-bold text-primary mt-1 mb-0">{{ format_currency($summary['estimated_manufacturing_cost']) }}</h4>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="bg-light rounded p-3 text-center border">
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.capacity_utilization') }}</span>
                            <h4 class="fw-bold text-dark mt-1 mb-0">{{ $summary['capacity_utilization'] }}%</h4>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Tab 3: Capacity Operations Load --}}
        <div class="tab-pane-custom d-none" id="tab-operations">
            @if($plan->operations->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="feather-sliders fs-32 mb-2 d-block"></i>
                    <p class="mb-0">{{ __('production.no_routing_capacity_snapshotted') }}</p>
                </div>
            @else
                <h5 class="fw-bold text-dark mb-3">{{ __('production.capacity_operations') }}</h5>
                <div class="table-responsive">
                    <table class="erp-thin-table fs-12">
                        <thead>
                            <tr>
                                <th style="width:5%">{{ __('production.seq') }}</th>
                                <th style="width:12%">{{ __('production.operation_number') }}</th>
                                <th style="width:20%">{{ __('production.machine_name') }}</th>
                                <th style="width:22%">{{ __('production.work_center_hierarchy') }}</th>
                                <th style="width:15%">{{ __('production.assigned_machine') }}</th>
                                <th style="width:8%" class="text-end">{{ __('production.setup_min') }}</th>
                                <th style="width:8%" class="text-end">{{ __('production.run_min_unit') }}</th>
                                <th style="width:10%" class="text-end">{{ __('production.total_time') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($plan->operations as $op)
                                <tr>
                                    <td class="fw-bold">{{ $op->sequence }}</td>
                                    <td class="text-dark font-monospace fw-semibold">{{ $op->operation_number }}</td>
                                    <td>{{ $op->name }}</td>
                                     <td>
                                         <span class="fw-medium text-dark d-block">{{ $op->workCenter->name }}</span>
                                         @if($op->workCenter->parent)
                                             <small class="text-muted fs-10">
                                                 {{ $op->workCenter->parent->parent ? $op->workCenter->parent->parent->name . ' › ' : '' }}{{ $op->workCenter->parent->name }}
                                             </small>
                                         @endif
                                     </td>
                                    <td class="text-muted">{{ $op->machine ? $op->machine->name : __('production.manual_none') }}</td>
                                    <td class="text-end">{{ number_format($op->setup_time_minutes, 1) }}</td>
                                    <td class="text-end">{{ number_format($op->processing_time_minutes, 1) }}</td>
                                    <td class="text-end fw-bold text-dark">{{ number_format($op->total_time_minutes, 1) }} min</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Tab 4: Details & Logs --}}
        <div class="tab-pane-custom d-none" id="tab-details">
            <div class="row g-4 fs-13">
                <div class="col-md-6">
                    <h5 class="fw-bold text-dark mb-3">{{ __('production.description_notes') }}</h5>
                    <p class="text-muted">{{ $plan->description ?: __('production.no_description_notes') }}</p>
                </div>
                <div class="col-md-6 border-start ps-4">
                    <h5 class="fw-bold text-dark mb-3">{{ __('production.audit_metadata') }}</h5>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-3 d-flex align-items-start">
                            <div class="avatar-text avatar-sm bg-soft-primary text-primary me-3 mt-1 rounded-circle">
                                <i class="feather-user fs-14"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark">{{ __('production.plan_created') }}</div>
                                <div class="text-muted fs-11">By: {{ $plan->creator ? $plan->creator->name : 'System' }} on {{ $plan->created_at->format('d/m/Y H:i') }}</div>
                            </div>
                        </li>
                        @if($plan->approved_by)
                            <li class="mb-3 d-flex align-items-start">
                                <div class="avatar-text avatar-sm bg-soft-success text-success me-3 mt-1 rounded-circle">
                                    <i class="feather-check-circle fs-14"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ __('production.plan_approved') }}</div>
                                    <div class="text-muted fs-11">By: {{ $plan->approver ? $plan->approver->name : 'N/A' }} on {{ $plan->approved_at ? $plan->approved_at->format('d/m/Y H:i') : '' }}</div>
                                </div>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>

    </div>{{-- end .tab-content --}}

</div>{{-- end .erp-single-panel --}}

<script>
    function switchTab(tabId) {
        document.querySelectorAll('.erp-tabs-link').forEach(l => l.classList.remove('active'));
        document.getElementById('btn-tab-' + tabId).classList.add('active');
        document.querySelectorAll('.tab-pane-custom').forEach(p => p.classList.add('d-none'));
        document.getElementById('tab-' + tabId).classList.remove('d-none');
    }
</script>
@endsection
