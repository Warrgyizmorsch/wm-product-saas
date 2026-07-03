@extends('layouts.duralux')

@section('title', 'Production Plan Details | SaaS ERP')
@section('page-title', 'Production Plan Details')
@section('breadcrumb', 'Plan Details')

@section('page-actions')
    <a href="{{ route('production.plans.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>Back to List
    </a>
    
    @if($plan->isDraft())
        <a href="{{ route('production.plans.edit', $plan->id) }}" class="btn btn-primary me-2">
            <i class="feather-edit me-2"></i>Edit Plan
        </a>

        <form method="POST" action="{{ route('production.plans.submit', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-info">
                <i class="feather-send me-2"></i>Submit Approval
            </button>
        </form>
    @endif

    @if($plan->isPendingApproval())
        <form method="POST" action="{{ route('production.plans.approve', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="feather-check-circle me-2"></i>Approve Plan
            </button>
        </form>
        <form method="POST" action="{{ route('production.plans.reject', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-danger">
                <i class="feather-x-circle me-2"></i>Reject
            </button>
        </form>
    @endif

    @if(!$plan->isFrozen())
        <form method="POST" action="{{ route('production.plans.run-mrp', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-warning text-dark">
                <i class="feather-cpu me-2"></i>Run MRP Engine
            </button>
        </form>
    @endif

    @if($plan->isApproved() || $plan->isMrpGenerated())
        <form method="POST" action="{{ route('production.plans.create-order', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="feather-file-text me-2"></i>Generate Production Order
            </button>
        </form>

        <form method="POST" action="{{ route('production.plans.release', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="feather-play-circle me-2"></i>Release to Shop Floor
            </button>
        </form>
    @endif

    @if($plan->isReleased())
        <form method="POST" action="{{ route('production.plans.complete', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="feather-check me-2"></i>Complete Plan
            </button>
        </form>
    @endif

    @if($plan->isCompleted())
        <form method="POST" action="{{ route('production.plans.close', $plan->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-dark">
                <i class="feather-archive me-2"></i>Close & Archive
            </button>
        </form>
    @endif

    @if(!$plan->isClosed() && !$plan->isCompleted() && !$plan->isCancelled())
        <form method="POST" action="{{ route('production.plans.cancel', $plan->id) }}" class="d-inline me-2" onsubmit="return confirm('Cancel this Production Plan?');">
            @csrf
            <button type="submit" class="btn btn-outline-danger">
                <i class="feather-slash me-2"></i>Cancel Plan
            </button>
        </form>
    @endif
@endsection

@section('content')
    <!-- Warnings Diagnostics Panel -->
    @if(count($warnings) > 0)
        <div class="row mb-4">
            <div class="col-12">
                <x-ui.card class="border-danger">
                    <div class="card-header bg-soft-danger text-danger d-flex align-items-center py-2">
                        <i class="feather-alert-triangle me-2 fs-18"></i>
                        <span class="fw-bold">Planning & Resource Warnings ({{ count($warnings) }})</span>
                    </div>
                    <div class="card-body py-3">
                        <ul class="mb-0 ps-3">
                            @foreach($warnings as $warn)
                                <li class="text-{{ $warn['severity'] === 'danger' ? 'danger fw-semibold' : 'dark' }} mb-1 fs-12">
                                    {{ $warn['message'] }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </x-ui.card>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <!-- Plan Header / Metadata Summary -->
        <div class="col-md-4">
            <x-ui.card class="bg-white">
                <div class="card-header border-bottom py-3">
                    <h5 class="fw-bold text-dark mb-0">Plan Identification</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-3 fs-13">
                        <div>
                            <span class="text-muted d-block">Plan Number</span>
                            <span class="fw-bold text-dark fs-15">{{ $plan->plan_number }}</span>
                        </div>
                        <div>
                            <span class="text-muted d-block">Plan Name</span>
                            <span class="fw-semibold text-dark">{{ $plan->name }}</span>
                        </div>
                        <div>
                            <span class="text-muted d-block">Item to Produce</span>
                            <span class="fw-bold text-dark">{{ $plan->product->name }}</span>
                            <small class="text-muted font-monospace d-block">{{ $plan->product->sku }}</small>
                        </div>
                        <div>
                            <span class="text-muted d-block">Target Quantity</span>
                            <span class="fw-bold text-dark fs-14">{{ number_format($plan->quantity, 2) }} Units</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <span class="text-muted d-block">Start Date</span>
                                <span class="fw-semibold text-dark">{{ $plan->start_date->format('d M Y') }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block">End Date</span>
                                <span class="fw-semibold text-dark">{{ $plan->end_date->format('d M Y') }}</span>
                            </div>
                        </div>
                        <div>
                            <span class="text-muted d-block">Status</span>
                            @if($plan->status === 'draft')
                                <span class="badge bg-soft-secondary text-secondary text-uppercase rounded-pill px-2 py-1 fs-10">Draft</span>
                            @elseif($plan->status === 'pending_approval')
                                <span class="badge bg-soft-warning text-warning text-uppercase rounded-pill px-2 py-1 fs-10">Pending Approval</span>
                            @elseif($plan->status === 'approved')
                                <span class="badge bg-soft-success text-success text-uppercase rounded-pill px-2 py-1 fs-10">Approved</span>
                            @elseif($plan->status === 'mrp_generated')
                                <span class="badge bg-soft-info text-info text-uppercase rounded-pill px-2 py-1 fs-10">MRP Run</span>
                            @elseif($plan->status === 'released')
                                <span class="badge bg-soft-primary text-primary text-uppercase rounded-pill px-2 py-1 fs-10">Released to Shop Floor</span>
                            @elseif($plan->status === 'completed')
                                <span class="badge bg-soft-success text-success text-uppercase rounded-pill px-2 py-1 fs-10">Completed</span>
                            @elseif($plan->status === 'closed')
                                <span class="badge bg-soft-dark text-dark text-uppercase rounded-pill px-2 py-1 fs-10">Closed</span>
                            @else
                                <span class="badge bg-soft-danger text-danger text-uppercase rounded-pill px-2 py-1 fs-10">Cancelled</span>
                            @endif
                        </div>
                        <div class="border-top pt-2 mt-2">
                            <span class="text-muted d-block">BOM Reference (Frozen)</span>
                            @if($plan->bom)
                                <a href="{{ route('production.boms.show', $plan->bom_id) }}" class="fw-semibold text-primary">
                                    {{ $plan->bom->bom_number }} (v{{ $plan->bom->version }})
                                </a>
                            @else
                                <span class="text-muted">None Assigned</span>
                            @endif
                        </div>
                        <div>
                            <span class="text-muted d-block">Routing Reference (Frozen)</span>
                            @if($plan->routing)
                                <a href="{{ route('production.routing.show', $plan->routing_id) }}" class="fw-semibold text-primary">
                                    {{ $plan->routing->routing_number }} (v{{ $plan->routing->version }})
                                </a>
                            @else
                                <span class="text-muted">None Assigned</span>
                            @endif
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <!-- Planning Tabs & Snapshot Displays -->
        <div class="col-md-8">
            <div class="bg-white border rounded shadow-sm p-4">
                
                <ul class="nav nav-tabs border-bottom mb-3" id="planTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="mrp-tab" data-bs-toggle="tab" data-bs-target="#mrpContent" type="button" role="tab">
                            <i class="feather-cpu me-2"></i>Material Requirements (MRP)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summaryContent" type="button" role="tab">
                            <i class="feather-info me-2"></i>MRP Summary Panel
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="operations-tab" data-bs-toggle="tab" data-bs-target="#operationsContent" type="button" role="tab">
                            <i class="feather-sliders me-2"></i>Capacity Operations Load
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="audit-tab" data-bs-toggle="tab" data-bs-target="#auditContent" type="button" role="tab">
                            <i class="feather-activity me-2"></i>Details & Logs
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="planTabsContent">
                    
                    <!-- TAB 1: Material Requirements -->
                    <div class="tab-pane fade show active" id="mrpContent" role="tabpanel">
                        @if($plan->requirements->isEmpty())
                            <div class="text-center py-5 text-muted">
                                <i class="feather-cpu fs-32 mb-2 d-block text-muted"></i>
                                <p class="mb-2 fw-semibold">No snapshot material requirements recorded.</p>
                                <p class="fs-12 mb-3">Run the MRP Engine to explode the BOM and populate required components.</p>
                                @if(!$plan->isFrozen())
                                    <form method="POST" action="{{ route('production.plans.run-mrp', $plan->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-warning text-dark px-4 btn-sm">
                                            <i class="feather-play me-2"></i>Run MRP Engine Now
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="erp-thin-table fs-12">
                                    <thead>
                                        <tr>
                                            <th>Lv</th>
                                            <th>Component Item</th>
                                            <th class="text-end">Required Qty</th>
                                            <th class="text-end">Available Qty</th>
                                            <th class="text-end">Reserved Qty</th>
                                            <th class="text-end text-danger">Shortage Qty</th>
                                            <th>UOM</th>
                                            <th>Source Item</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($plan->requirements as $req)
                                            <tr>
                                                <td class="fw-bold">{{ $req->bom_level }}</td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span class="fw-bold text-dark">{{ $req->product->name }}</span>
                                                        <small class="text-muted font-monospace fs-10">{{ $req->product->sku }}</small>
                                                    </div>
                                                </td>
                                                <td class="text-end fw-semibold text-dark">{{ number_format($req->required_quantity, 2) }}</td>
                                                <td class="text-end text-muted">{{ number_format($req->available_quantity, 2) }}</td>
                                                <td class="text-end text-muted">{{ number_format($req->reserved_quantity, 2) }}</td>
                                                <td class="text-end text-danger fw-bold">{{ number_format($req->shortage_quantity, 2) }}</td>
                                                <td>{{ $req->uom ? $req->uom->code : 'PCS' }}</td>
                                                <td class="text-muted">
                                                    @if($req->sourceItem)
                                                        {{ $req->sourceItem->name }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <!-- TAB 2: MRP Execution Summary Panel -->
                    <div class="tab-pane fade" id="summaryContent" role="tabpanel">
                        @if(!$summary)
                            <div class="text-center py-5 text-muted">
                                <i class="feather-info fs-32 mb-2 d-block text-muted"></i>
                                <p class="mb-0">MRP summary dashboard will be loaded once you successfully Run the MRP Engine.</p>
                            </div>
                        @else
                            <div class="row g-3 mb-4">
                                <div class="col-md-6 col-lg-3">
                                    <div class="bg-light p-3 rounded text-center">
                                        <span class="text-muted fs-11 text-uppercase d-block mb-1">Total Components</span>
                                        <span class="fw-bold text-dark fs-18">{{ $summary['total_components'] }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="bg-light p-3 rounded text-center">
                                        <span class="text-muted fs-11 text-uppercase d-block mb-1">Subassemblies</span>
                                        <span class="fw-bold text-dark fs-18">{{ $summary['total_subassemblies'] }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="bg-light p-3 rounded text-center">
                                        <span class="text-muted fs-11 text-uppercase d-block mb-1">Operations</span>
                                        <span class="fw-bold text-dark fs-18">{{ $summary['total_operations'] }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="bg-light p-3 rounded text-center">
                                        <span class="text-muted fs-11 text-uppercase d-block mb-1">Warnings</span>
                                        <span class="fw-bold text-danger fs-18">{{ $summary['warnings_count'] }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="bg-light p-3 rounded text-center">
                                        <span class="text-muted fs-11 text-uppercase d-block mb-1">Estimated Material Cost</span>
                                        <h4 class="fw-bold text-success mt-1 mb-0">${{ number_format($summary['estimated_material_cost'], 2) }}</h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="bg-light p-3 rounded text-center">
                                        <span class="text-muted fs-11 text-uppercase d-block mb-1">Manufacturing Overhead</span>
                                        <h4 class="fw-bold text-primary mt-1 mb-0">${{ number_format($summary['estimated_manufacturing_cost'], 2) }}</h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="bg-light p-3 rounded text-center">
                                        <span class="text-muted fs-11 text-uppercase d-block mb-1">Capacity Utilization</span>
                                        <h4 class="fw-bold text-dark mt-1 mb-0">{{ $summary['capacity_utilization'] }}%</h4>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- TAB 3: Capacity Operations Load -->
                    <div class="tab-pane fade" id="operationsContent" role="tabpanel">
                        @if($plan->operations->isEmpty())
                            <div class="text-center py-5 text-muted">
                                <i class="feather-sliders fs-32 mb-2 d-block text-muted"></i>
                                <p class="mb-0">No Routing capacity operations snapshotted. Run the MRP Engine to compute capacity requirements.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="erp-thin-table fs-12">
                                    <thead>
                                        <tr>
                                            <th>Seq</th>
                                            <th>Operation#</th>
                                            <th>Name</th>
                                            <th>Work Center Location (Hierarchy)</th>
                                            <th>Assigned Machine</th>
                                            <th class="text-end">Setup Min</th>
                                            <th class="text-end">Run Min / unit</th>
                                            <th class="text-end">Total Time (Min)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($plan->operations as $op)
                                            <tr>
                                                <td class="fw-bold align-middle">{{ $op->sequence }}</td>
                                                <td class="align-middle text-dark font-monospace fw-semibold">{{ $op->operation_number }}</td>
                                                <td class="align-middle">{{ $op->name }}</td>
                                                <td class="align-middle">
                                                    <span class="fw-medium text-dark d-block">{{ $op->workCenter->name }}</span>
                                                    <small class="text-muted fs-10">{{ $op->workCenter->getHierarchyPath() }}</small>
                                                </td>
                                                <td class="align-middle text-muted">{{ $op->machine ? $op->machine->name : 'Manual / None' }}</td>
                                                <td class="align-middle text-end">{{ number_format($op->setup_time_minutes, 1) }}</td>
                                                <td class="align-middle text-end">{{ number_format($op->processing_time_minutes, 1) }}</td>
                                                <td class="align-middle text-end fw-bold text-dark">{{ number_format($op->total_time_minutes, 1) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <!-- TAB 4: General Details & Logs -->
                    <div class="tab-pane fade" id="auditContent" role="tabpanel">
                        <div class="row g-3 fs-13">
                            <div class="col-md-6">
                                <h6 class="fw-bold text-dark mb-2">Description / Notes</h6>
                                <p class="text-muted">{{ $plan->description ?: 'No detailed planning notes provided.' }}</p>
                            </div>
                            <div class="col-md-6 border-start ps-4">
                                <h6 class="fw-bold text-dark mb-2">Planning Metadata</h6>
                                <div class="d-flex flex-column gap-2">
                                    <div>
                                        <span class="text-muted">Created By:</span>
                                        <span class="fw-semibold text-dark">{{ $plan->creator ? $plan->creator->name : 'System Generated' }}</span>
                                        <small class="text-muted d-block">on {{ $plan->created_at->format('d/m/Y H:i') }}</small>
                                    </div>
                                    @if($plan->approved_by)
                                        <div>
                                            <span class="text-muted">Approved By:</span>
                                            <span class="fw-semibold text-dark">{{ $plan->approver ? $plan->approver->name : 'N/A' }}</span>
                                            <small class="text-muted d-block">on {{ $plan->approved_at ? $plan->approved_at->format('d/m/Y H:i') : '' }}</small>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection
