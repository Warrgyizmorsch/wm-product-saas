@extends('layouts.duralux')

@section('title', 'BOM Details | SaaS ERP')
@section('page-title', 'Bill of Materials Details')
@section('breadcrumb', 'BOM Details')

@section('page-actions')
    <a href="{{ route('production.boms.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>Back to List
    </a>
    
    @if($bom->isDraft() || $bom->isUnderRevision())
        <a href="{{ route('production.boms.edit', $bom->id) }}" class="btn btn-primary me-2">
            <i class="feather-edit me-2"></i>Edit Draft
        </a>

        <form method="POST" action="{{ route('production.boms.submit', $bom->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-info">
                <i class="feather-send me-2"></i>Submit Approval
            </button>
        </form>
    @endif

    @if($bom->isPendingApproval())
        <form method="POST" action="{{ route('production.boms.approve', $bom->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="feather-check-circle me-2"></i>Approve BOM
            </button>
        </form>
        <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#rejectModal">
            <i class="feather-x-circle me-2"></i>Reject
        </button>
    @endif

    @if($bom->isApproved())
        <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#cancelModal">
            <i class="feather-slash me-2"></i>Cancel BOM
        </button>
    @endif

    <button type="button" class="btn btn-light-brand" data-bs-toggle="modal" data-bs-target="#duplicateModal">
        <i class="feather-copy me-2"></i>Duplicate Version
    </button>
@endsection

@section('content')
    <!-- Success & Error Banners -->
    @if (session('success'))
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

    @if (session('error'))
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

    <div class="row g-4">
        <!-- Main details section -->
        <div class="col-xl-8">
            <!-- BOM Header Summary Card -->
            <x-ui.card class="mb-4" title="BOM #{{ $bom->bom_number }}">
                <div class="row g-3 fs-13">
                    <div class="col-md-6 border-end">
                        <p class="mb-1 text-muted text-uppercase fs-10 fw-bold">Finished Product</p>
                        <h5 class="text-dark fw-bold mb-1">{{ $bom->product->name }}</h5>
                        <p class="mb-0 text-muted">SKU: <span class="fw-semibold text-dark">{{ $bom->product->sku }}</span></p>
                    </div>
                    <div class="col-md-3 border-end">
                        <p class="mb-1 text-muted text-uppercase fs-10 fw-bold">Version & Revision</p>
                        <h6 class="text-dark mb-0">Version: v{{ $bom->version }}</h6>
                        <span class="text-muted">Revision: r{{ $bom->revision }}</span>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1 text-muted text-uppercase fs-10 fw-bold">Workflow Status</p>
                        @if($bom->status === 'draft')
                            <span class="badge bg-soft-warning text-warning fs-12 px-3 py-1">Draft</span>
                        @elseif($bom->status === 'pending_approval')
                            <span class="badge bg-soft-primary text-primary fs-12 px-3 py-1">Pending Approval</span>
                        @elseif($bom->status === 'approved')
                            <span class="badge bg-soft-success text-success fs-12 px-3 py-1">Approved & Active</span>
                        @elseif($bom->status === 'cancelled')
                            <span class="badge bg-soft-danger text-danger fs-12 px-3 py-1">Cancelled</span>
                        @else
                            <span class="badge bg-soft-secondary text-secondary fs-12 px-3 py-1">Inactive</span>
                        @endif
                    </div>
                    
                    <div class="col-12 mt-3 pt-3 border-top">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <span class="text-muted"><strong>BOM Name:</strong></span>
                                <span class="text-dark fw-bold d-block">{{ $bom->bom_name ?: 'N/A' }}</span>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted"><strong>BOM Type:</strong></span>
                                <span class="badge bg-soft-info text-info text-capitalize d-block mt-1 text-center" style="max-width: 150px">{{ $bom->bom_type }}</span>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted"><strong>Base Batch Quantity:</strong></span>
                                <span class="text-dark fw-bold d-block">{{ number_format($bom->base_quantity, 4) }} {{ $bom->baseUom ? $bom->baseUom->code : 'PCS' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-3 pt-3 border-top">
                        <p class="mb-1 text-muted text-uppercase fs-10 fw-bold">Manufacturing Context</p>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <span class="text-muted"><strong>Routing Reference:</strong></span>
                                <span class="text-dark d-block fw-semibold">{{ $bom->routing ? $bom->routing->name : 'No Routing Associated' }}</span>
                            </div>
                            <div class="col-md-8">
                                <span class="text-muted"><strong>Revision Change Reason:</strong></span>
                                <span class="text-dark d-block italic fs-12">{{ $bom->revision_reason ?: 'No revision reason provided.' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-3 pt-3 border-top">
                        <p class="mb-1 text-muted text-uppercase fs-10 fw-bold">Effective Dates</p>
                        <div class="d-flex gap-4">
                            <span><strong>Start Date:</strong> {{ $bom->effective_date ? $bom->effective_date->format('d/m/Y') : 'N/A' }}</span>
                            @if($bom->expiry_date)
                                <span><strong>Expiry Date:</strong> {{ $bom->expiry_date->format('d/m/Y') }}</span>
                            @else
                                <span class="text-muted"><strong>Expiry Date:</strong> No expiry date set</span>
                            @endif
                        </div>
                    </div>
                    @if($bom->notes)
                        <div class="col-12 mt-3 pt-3 border-top">
                            <p class="mb-1 text-muted text-uppercase fs-10 fw-bold">Production Recipe Notes</p>
                            <p class="mb-0 text-dark bg-light p-3 rounded border border-dashed text-justify">{{ $bom->notes }}</p>
                        </div>
                    @endif
                </div>
            </x-ui.card>

            <!-- TABS SECTION -->
            <ul class="nav nav-tabs border-bottom mb-4" id="bomTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold text-uppercase fs-11" id="components-tab" data-bs-toggle="tab" data-bs-target="#components" type="button" role="tab" aria-controls="components" aria-selected="true">
                        <i class="feather-list me-2"></i>Components List
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-uppercase fs-11" id="costing-tab" data-bs-toggle="tab" data-bs-target="#costing" type="button" role="tab" aria-controls="costing" aria-selected="false">
                        <i class="feather-dollar-sign me-2"></i>Cost Preview
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-uppercase fs-11" id="hierarchy-tab" data-bs-toggle="tab" data-bs-target="#hierarchy" type="button" role="tab" aria-controls="hierarchy" aria-selected="false">
                        <i class="feather-git-merge me-2"></i>BOM Explosion Tree
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-uppercase fs-11" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
                        <i class="feather-activity me-2"></i>Approval & Audit History
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="bomTabsContent">
                <!-- Tab 1: Components List -->
                <div class="tab-pane fade show active" id="components" role="tabpanel" aria-labelledby="components-tab">
                    <x-ui.card title="Required Components (Recipe Items)">
                        <x-ui.table striped>
                            <thead>
                                <tr>
                                    <th style="width: 5%">Seq</th>
                                    <th style="width: 35%">Material Component</th>
                                    <th style="width: 12%" class="text-end">Qty</th>
                                    <th style="width: 10%">UOM</th>
                                    <th style="width: 10%" class="text-end">Scrap %</th>
                                    <th style="width: 8%" class="text-end">Priority</th>
                                    <th style="width: 10%">Alternative</th>
                                    <th style="width: 10%">Validity Limits</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($bom->items as $item)
                                    <tr>
                                        <td>{{ $item->sequence }}</td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold text-dark fs-12">{{ $item->material->name }}</span>
                                                <small class="text-muted">{{ $item->material->sku }}</small>
                                                @if($item->notes)
                                                    <span class="text-muted fs-11 mt-1"><i class="feather-info me-1"></i>{{ $item->notes }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold text-dark">{{ number_format($item->quantity, 4) }}</td>
                                        <td><span class="badge bg-soft-secondary text-secondary">{{ $item->uom ? $item->uom->code : 'PCS' }}</span></td>
                                        <td class="text-end text-danger">{{ number_format($item->material_scrap_percentage, 2) }}%</td>
                                        <td class="text-end">{{ $item->priority }}</td>
                                        <td>
                                            @if($item->is_alternative)
                                                <span class="badge bg-soft-warning text-warning" title="Group: {{ $item->alternative_group }}">
                                                    Alt: {{ $item->alternative_group }}
                                                </span>
                                            @else
                                                <span class="text-muted fs-12">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($item->effective_from || $item->effective_to)
                                                <small class="text-muted d-block">From: {{ $item->effective_from ? $item->effective_from->format('d/m/Y') : 'Start' }}</small>
                                                <small class="text-muted d-block">To: {{ $item->effective_to ? $item->effective_to->format('d/m/Y') : 'End' }}</small>
                                            @else
                                                <span class="text-muted fs-12">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">No component items added.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.table>
                    </x-ui.card>
                </div>

                <!-- Tab 2: Cost Preview -->
                <div class="tab-pane fade" id="costing" role="tabpanel" aria-labelledby="costing-tab">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="bg-light p-3 rounded border text-center">
                                <span class="text-muted fs-11 text-uppercase fw-bold">Total Material Cost</span>
                                <h4 class="text-dark fw-bold mt-1">${{ number_format($costSummary['total_cost'], 4) }}</h4>
                                <small class="text-muted">For base recipe size</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light p-3 rounded border text-center">
                                <span class="text-muted fs-11 text-uppercase fw-bold">Base Recipe Quantity</span>
                                <h4 class="text-dark fw-bold mt-1">{{ number_format($bom->base_quantity, 2) }}</h4>
                                <small class="text-muted">{{ $bom->baseUom ? $bom->baseUom->code : 'PCS' }}</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light p-3 rounded border text-center">
                                <span class="text-muted fs-11 text-uppercase fw-bold">Cost Per Unit</span>
                                <h4 class="text-primary fw-bold mt-1">${{ number_format($costSummary['cost_per_unit'], 4) }}</h4>
                                <small class="text-muted">Estimated manufacturing unit cost</small>
                            </div>
                        </div>
                    </div>

                    <x-ui.card title="Cost Breakdown of Raw Components">
                        <x-ui.table striped>
                            <thead>
                                <tr>
                                    <th>Material Component</th>
                                    <th class="text-end">Base Qty</th>
                                    <th class="text-end">Scrap %</th>
                                    <th class="text-end">Gross Qty Required</th>
                                    <th class="text-end">Unit Cost</th>
                                    <th class="text-end">Total Item Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($costSummary['items'] as $cItem)
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold text-dark fs-12">{{ $cItem['material_name'] }}</span>
                                                <small class="text-muted">{{ $cItem['material_sku'] }}</small>
                                            </div>
                                        </td>
                                        <td class="text-end">{{ number_format($cItem['quantity'], 4) }} {{ $cItem['uom_code'] }}</td>
                                        <td class="text-end text-danger">{{ number_format($cItem['scrap_percentage'], 2) }}%</td>
                                        <td class="text-end fw-bold">{{ number_format($cItem['gross_quantity'], 4) }} {{ $cItem['uom_code'] }}</td>
                                        <td class="text-end">${{ number_format($cItem['unit_cost'], 4) }}</td>
                                        <td class="text-end text-dark fw-bold">${{ number_format($cItem['total_cost'], 4) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No components to compute cost.</td>
                                    </tr>
                                @endforelse
                                <tr class="table-light fw-bold">
                                    <td colspan="5" class="text-end">Estimated Total Material Cost:</td>
                                    <td class="text-end text-dark">${{ number_format($costSummary['total_cost'], 4) }}</td>
                                </tr>
                            </tbody>
                        </x-ui.table>
                        <div class="mt-3">
                            <span class="text-muted fs-11 italic"><i class="feather-info me-1"></i>Note: Prices are simulated based on standard item cost records for raw materials. Full routing labor and overhead costs can be rolled up in the costing module.</span>
                        </div>
                    </x-ui.card>
                </div>

                <!-- Tab 3: Explosion Tree -->
                <div class="tab-pane fade" id="hierarchy" role="tabpanel" aria-labelledby="hierarchy-tab">
                    <x-ui.card title="Recursive Multi-Level BOM Structure">
                        <p class="text-muted fs-12 mb-3">Below is the complete engineering bill of materials structure showing recursively expanded sub-assemblies (semi-finished products) down to raw components.</p>
                        
                        @php
                            if (!function_exists('renderHtmlBomTree')) {
                                function renderHtmlBomTree($node) {
                                    echo '<li class="mb-2 list-unstyled">';
                                    echo '<div class="d-flex align-items-center gap-3 p-2 bg-light rounded border border-light">';
                                    
                                    if (!empty($node['children'])) {
                                        echo '<i class="feather-package text-brand fs-16"></i>';
                                    } else {
                                        echo '<i class="feather-box text-muted fs-14"></i>';
                                    }
                                    
                                    echo '<div>';
                                    echo '<span class="fw-bold text-dark fs-13">' . e($node['product_name']) . '</span>';
                                    echo '<span class="text-muted fs-11 ms-2">[' . e($node['product_sku']) . ']</span>';
                                    if (isset($node['bom_number'])) {
                                        echo ' <span class="badge bg-soft-info text-info fs-10 ms-2">BOM: ' . e($node['bom_number']) . ' v' . e($node['bom_version']) . '</span>';
                                    }
                                    echo '</div>';
                                    
                                    echo '<div class="ms-auto text-end">';
                                    if (isset($node['net_quantity'])) {
                                        echo '<span class="text-muted fs-12 me-3">Net: ' . number_format($node['net_quantity'], 4) . ' ' . e($node['uom_code']) . '</span>';
                                        echo '<span class="fw-bold text-dark fs-12">Gross (With Scrap): ' . number_format($node['gross_quantity'], 4) . ' ' . e($node['uom_code']) . '</span>';
                                        if ($node['material_scrap_percentage'] > 0) {
                                            echo '<small class="text-danger d-block fs-10">Scrap: +' . number_format($node['material_scrap_percentage'], 1) . '%</small>';
                                        }
                                    } else {
                                        echo '<span class="fw-bold text-dark fs-12">Target Batch Size: ' . number_format($node['quantity'], 4) . ' ' . e($node['uom_code']) . '</span>';
                                    }
                                    echo '</div>';
                                    echo '</div>';
                                    
                                    if (!empty($node['children'])) {
                                        echo '<ul class="ps-4 border-start border-light-brand border-2 ms-3 mt-2">';
                                        foreach ($node['children'] as $child) {
                                            renderHtmlBomTree($child);
                                        }
                                        echo '</ul>';
                                    }
                                    echo '</li>';
                                }
                            }
                        @endphp

                        <div class="bom-hierarchy-tree p-3 bg-white border rounded">
                            <ul class="ps-0 mb-0">
                                @php renderHtmlBomTree($explosion['tree']) @endphp
                            </ul>
                        </div>
                    </x-ui.card>
                </div>

                <!-- Tab 4: Approval history -->
                <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
                    <x-ui.card title="Approval Lifecycle & Engineering History">
                        <div class="table-responsive">
                            <table class="table align-middle fs-13">
                                <thead>
                                    <tr class="table-light">
                                        <th>Date & Time</th>
                                        <th>Action Taken</th>
                                        <th>Triggered By</th>
                                        <th>Engineering Comments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($bom->approvals as $approval)
                                        <tr>
                                            <td>{{ $approval->created_at->format('d/m/Y H:i:s') }}</td>
                                            <td>
                                                @if($approval->action === 'Approved')
                                                    <span class="badge bg-soft-success text-success">Approved / Active</span>
                                                @elseif($approval->action === 'Submitted')
                                                    <span class="badge bg-soft-info text-info">Submitted</span>
                                                @elseif($approval->action === 'Created')
                                                    <span class="badge bg-soft-warning text-warning">Draft Created</span>
                                                @elseif($approval->action === 'Rejected')
                                                    <span class="badge bg-soft-danger text-danger">Rejected</span>
                                                @elseif($approval->action === 'Cancelled')
                                                    <span class="badge bg-soft-danger text-danger">Cancelled</span>
                                                @else
                                                    <span class="badge bg-soft-secondary text-secondary">{{ $approval->action }}</span>
                                                @endif
                                            </td>
                                            <td class="fw-semibold">{{ $approval->user ? $approval->user->name : 'System' }}</td>
                                            <td class="italic text-muted">"{{ $approval->comments ?: 'No comments logged.' }}"</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-3 text-muted">No approval logs recorded.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-ui.card>
                </div>
            </div>
        </div>

        <!-- Right sidebar / details context section -->
        <div class="col-xl-4">
            <!-- ERP Integration & Workflow History Card -->
            <x-ui.card title="Workflow Status" class="mb-4">
                <div class="d-flex flex-column gap-3 fs-13">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Created By:</span>
                        <span class="fw-semibold text-dark">{{ $bom->creator ? $bom->creator->name : 'System' }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Created At:</span>
                        <span class="text-dark">{{ $bom->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    @if($bom->approved_by)
                        <div class="dropdown-divider"></div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Approved By:</span>
                            <span class="fw-semibold text-dark">{{ $bom->approver->name }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Approved At:</span>
                            <span class="text-dark">{{ $bom->approved_at->format('d/m/Y H:i') }}</span>
                        </div>
                    @endif
                </div>
            </x-ui.card>

            <!-- MRP & Costing Simulation Sandbox Card -->
            <x-ui.card title="Batch Explosion Requirements (MRP)">
                <p class="text-muted fs-12 mb-3">
                    Input a target manufacturing batch size to calculate the gross material quantity requirements, accounting for wastage rates.
                </p>
                <form method="GET" action="{{ route('production.boms.show', $bom->id) }}" class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="feather-activity"></i></span>
                        <input type="number" step="any" name="calc_qty" class="form-control border-start-0" placeholder="Batch Size Quantity..." value="{{ $calcQty }}" required min="0.0001">
                        <button type="submit" class="btn btn-primary">Calculate</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle fs-12">
                        <thead>
                            <tr>
                                <th>Component SKU</th>
                                <th class="text-end">Net Qty</th>
                                <th class="text-end">Gross (Scrap)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($explosion['flat'] as $req)
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-dark text-truncate" style="max-width: 120px;" title="{{ $req['product_name'] }}">{{ $req['product_name'] }}</span>
                                            <small class="text-muted">{{ $req['product_sku'] }}</small>
                                        </div>
                                    </td>
                                    <td class="text-end text-dark">{{ number_format($req['net_quantity'], 4) }} {{ $req['uom_code'] }}</td>
                                    <td class="text-end text-danger fw-bold">
                                        {{ number_format($req['gross_quantity'], 4) }} {{ $req['uom_code'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
    </div>

    <!-- Duplicate Modal -->
    <x-ui.modal id="duplicateModal" title="Duplicate BOM Version" submit-text="Create Version">
        <form method="POST" action="{{ route('production.boms.duplicate', $bom->id) }}" id="dupForm">
            @csrf
            <p class="fs-13 text-muted">Enter a new version string for this recipe duplicate. The new version will be created as a Draft.</p>
            <x-ui.input label="New Version Name" name="new_version" placeholder="e.g. 1.1.0 or 2.0.0" required />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" onclick="document.getElementById('dupForm').submit();">Duplicate Version</button>
        </x-slot>
    </x-ui.modal>

    <!-- Reject Modal -->
    <x-ui.modal id="rejectModal" title="Reject BOM Version" submit-text="Reject Version">
        <form method="POST" action="{{ route('production.boms.reject', $bom->id) }}" id="rejectForm">
            @csrf
            <p class="fs-13 text-muted">Provide comments explaining the reason for rejection.</p>
            <x-ui.input label="Rejection Reason" name="comments" placeholder="e.g. Component scrap rates are unacceptable." required />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" onclick="document.getElementById('rejectForm').submit();">Reject BOM</button>
        </x-slot>
    </x-ui.modal>

    <!-- Cancel Modal -->
    <x-ui.modal id="cancelModal" title="Cancel BOM Version" submit-text="Cancel Version">
        <form method="POST" action="{{ route('production.boms.cancel', $bom->id) }}" id="cancelForm">
            @csrf
            <p class="fs-13 text-muted">Provide comments explaining why this BOM is being cancelled.</p>
            <x-ui.input label="Cancellation Reason" name="comments" placeholder="e.g. Obsoleted by design revision." required />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" onclick="document.getElementById('cancelForm').submit();">Cancel BOM</button>
        </x-slot>
    </x-ui.modal>
@endsection
