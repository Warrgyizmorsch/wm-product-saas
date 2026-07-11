@extends('layouts.duralux')

@section('title', 'BOM Details | SaaS ERP')

@push('styles')
    <style>
        .erp-child-bom-row td {
            color: #828a96 !important;
            font-weight: 300 !important;
        }
        .erp-child-bom-row td .text-dark {
            color: #828a96 !important;
            font-weight: 400 !important;
        }
        .erp-child-bom-row td .fw-bold {
            font-weight: 400 !important;
        }
    </style>
@endpush

@section('page-actions')
    <a href="{{ route('production.boms.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>Back to List
    </a>
    
    @if($bom->isDraft() || $bom->isUnderRevision())
        <a href="{{ route('production.boms.edit', $bom->id) }}" class="btn btn-primary me-2">
            <i class="feather-edit me-2"></i>Edit Draft
        </a>

        @if($bom->routing_id)
            <form method="POST" action="{{ route('production.boms.submit', $bom->id) }}" class="d-inline me-2">
                @csrf
                <button type="submit" class="btn btn-info">
                    <i class="feather-send me-2"></i>Submit Approval
                </button>
            </form>
        @else
            <button type="button" class="btn btn-info me-2" disabled title="Routing reference must be selected in Edit Draft before submitting for approval" data-bs-toggle="tooltip">
                <i class="feather-send me-2"></i>Submit Approval (Routing Required)
            </button>
        @endif
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
    <div class="erp-single-panel bg-white">
        <!-- Success & Error Banners (Rendered via Toast Component) -->
        @if(isset($parentProduct))
            <div class="alert alert-success border-success bg-soft-success d-flex align-items-center justify-content-between p-3 mb-4 rounded shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <div class="avatar-text avatar-md bg-success text-white me-3">
                        <i class="feather-check-circle"></i>
                    </div>
                    <div>
                        <h6 class="alert-heading fw-bold mb-1 text-success">Child BOM Created Successfully!</h6>
                        <p class="fs-12 mb-0 text-success-800">Configure child BOM for <strong>{{ $bom->product->name }}</strong>. The parent form has been updated automatically. You can close this tab now to return to the parent form.</p>
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    @if(isset($parentBom))
                        <a href="{{ route('production.boms.show', $parentBom->id) }}" class="btn btn-success btn-sm text-white">
                            <i class="feather-arrow-left me-1"></i>Return to Parent BOM
                        </a>
                        <a href="{{ route('production.boms.edit', $parentBom->id) }}" class="btn btn-outline-success btn-sm bg-white">
                            <i class="feather-edit me-1"></i>Edit Parent BOM
                        </a>
                    @else
                        <a href="{{ route('production.boms.create') }}?product_id={{ $parentProduct->id }}" class="btn btn-success btn-sm text-white">
                            <i class="feather-plus me-1"></i>Return to Add Parent BOM
                        </a>
                    @endif
                    <button type="button" class="btn btn-secondary btn-sm ms-2" onclick="window.close();">
                        <i class="feather-x me-1"></i>Close Tab
                    </button>
                </div>
            </div>
        @elseif (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <!-- BOM Details Header Grid -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <h4 class="fw-bold text-dark mb-0">Bill of Materials Details (BOM #{{ $bom->bom_number }})</h4>
            <div>
                @if($bom->status === 'approved')
                    <span class="erp-badge-active">Active</span>
                @elseif($bom->status === 'draft')
                    <span class="erp-badge-draft">Draft</span>
                @elseif($bom->status === 'pending_approval')
                    <span class="erp-badge-pending">Pending</span>
                @else
                    <span class="erp-badge-draft text-uppercase">{{ $bom->status }}</span>
                @endif
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6 border-end">
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">BOM Name:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">{{ $bom->bom_name ?: 'N/A' }}</span>
                    </div>
                </div>

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">Item to Produce:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">{{ $bom->product->name }} ({{ $bom->product->sku }})</span>
                    </div>
                </div>

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">BOM Type:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="badge bg-soft-info text-info text-capitalize">{{ $bom->bom_type }}</span>
                    </div>
                </div>

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">Batch Size Qty:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">{{ number_format($bom->base_quantity, 4) }} {{ $bom->baseUom ? $bom->baseUom->code : 'PCS' }}</span>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">Version & Revision:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">Version: v{{ $bom->version }} (r{{ $bom->revision }})</span>
                    </div>
                </div>

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">Routing Reference:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">{{ $bom->routing ? $bom->routing->routing_number . ' - ' . $bom->routing->name : 'No Routing Associated' }}</span>
                    </div>
                </div>

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">Effective Range:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            From: {{ $bom->effective_date ? $bom->effective_date->format('d/m/Y') : 'N/A' }} 
                            {{ $bom->expiry_date ? ' To: ' . $bom->expiry_date->format('d/m/Y') : ' (No Expiry)' }}
                        </span>
                    </div>
                </div>

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">Revision Reason:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-semibold italic fs-12">{{ $bom->revision_reason ?: 'No revision reason provided.' }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if($bom->notes)
            <div class="mb-4 bg-light p-3 rounded border border-dashed">
                <span class="fw-semibold text-muted d-block fs-11 text-uppercase mb-2">Engineering & Recipe Notes</span>
                <p class="mb-0 text-dark fs-13 text-justify">{{ $bom->notes }}</p>
            </div>
        @endif

        <!-- TAB NAVIGATION -->
        <x-ui.horizontal-tabs id="bomDetailsTabs" :tabs="[
            ['id' => 'tab-components', 'label' => 'All Components', 'active' => true, 'icon' => 'feather-list'],
            ['id' => 'tab-explosion', 'label' => 'Expanded Material Explosion', 'icon' => 'feather-activity'],
            ['id' => 'tab-routing', 'label' => 'Routing Process', 'icon' => 'feather-sliders'],
            ['id' => 'tab-costing', 'label' => 'Cost Summary', 'icon' => 'feather-dollar-sign'],
            ['id' => 'tab-history', 'label' => 'Approval History', 'icon' => 'feather-clock'],
            ['id' => 'tab-whereused', 'label' => 'Where Used', 'icon' => 'feather-git-merge']
        ]" />

        <!-- TAB CONTENT CONTAINER -->
        <div class="tab-content mt-3">
            <!-- Tab 1: Components -->
            <div class="tab-pane fade show active" id="tab-components" role="tabpanel" aria-labelledby="tab-components-tab">
                <h5 class="fw-bold text-dark mb-3">Required Components (Recipe Items)</h5>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th style="width: 5%" class="text-center">Seq</th>
                                <th style="width: 45%">Material Component</th>
                                <th style="width: 20%" class="text-end">Quantity</th>
                                <th style="width: 15%">Unit</th>
                                <th style="width: 15%" class="text-end">Scrap %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                if (!function_exists('renderComponentTreeRows')) {
                                    function renderComponentTreeRows($items, $level = 1) {
                                        foreach ($items as $item) {
                                            $padding = ($level - 1) * 24;
                                            $hasChildBom = $item->childBom ? true : false;
                                            
                                            echo '<tr class="' . ($level > 1 ? 'table-light bg-light-soft erp-child-bom-row' : '') . '">';
                                            
                                            // Sequence
                                            echo '<td class="text-center fw-semibold text-muted align-middle">';
                                            if ($level > 1) {
                                                echo '—';
                                            } else {
                                                echo $item->sequence;
                                            }
                                            echo '</td>';
                                            
                                            // Material Component details with Indentation
                                            echo '<td class="align-middle">';
                                            echo '<div style="padding-left: ' . $padding . 'px;" class="d-flex align-items-center">';
                                            if ($level > 1) {
                                                echo '<i class="feather-corner-down-right text-muted me-2 fs-12"></i>';
                                            }
                                            echo '<div class="d-flex flex-column">';
                                            echo '<span class="fw-bold text-dark">' . e($item->material->name) . '</span>';
                                            echo '<small class="text-muted font-monospace fs-10">' . e($item->material->sku) . '</small>';
                                            
                                            if ($item->childBom) {
                                                echo '<small class="mt-1">';
                                                echo '<a href="' . route('production.boms.show', $item->childBom->id) . '" class="badge bg-soft-success text-success">';
                                                echo '<i class="feather-link me-1"></i>Linked BOM: ' . e($item->childBom->bom_name ?: $item->childBom->bom_number) . ' v' . e($item->childBom->version);
                                                echo '</a>';
                                                echo '</small>';
                                            }
                                            echo '</div>';
                                            echo '</div>';
                                            echo '</td>';
                                            
                                            // Quantity
                                            echo '<td class="text-end fw-bold align-middle">' . number_format($item->quantity, 4) . '</td>';
                                            
                                            // Unit
                                            echo '<td class="align-middle text-muted">' . e($item->uom ? $item->uom->code : 'PCS') . '</td>';
                                            
                                            // Scrap %
                                            echo '<td class="text-end text-danger fw-semibold align-middle">' . number_format($item->material_scrap_percentage, 2) . '%</td>';
                                            
                                            echo '</tr>';
                                            
                                            // Recursively render child components if linked BOM exists
                                            if ($item->childBom && $item->childBom->items->count() > 0) {
                                                renderComponentTreeRows($item->childBom->items, $level + 1);
                                            }
                                        }
                                    }
                                }
                            @endphp

                            @if($bom->items->count() > 0)
                                @php renderComponentTreeRows($bom->items) @endphp
                            @else
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No components found for this recipe.</td>
                                </tr>
                            @endif
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Tab 2: Expanded Material Explosion -->
            <div class="tab-pane fade" id="tab-explosion" role="tabpanel" aria-labelledby="tab-explosion-tab">
                <h5 class="fw-bold text-dark mb-3">MRP-Ready Expanded Material Explosion</h5>
                <p class="text-muted fs-12 mb-3">Below is the recursive, multi-level material explosion detailing all required sub-assemblies and direct raw materials scaled to the target production quantity.</p>
                
                @php
                    if (!function_exists('renderExplosionTableRows')) {
                        function renderExplosionTableRows($node, $level = 1) {
                            $padding = ($level - 1) * 20;
                            $isLeaf = empty($node['children']);
                            $bomVersion = $node['bom_version'] ?? 'N/A';
                            
                            $qty = isset($node['net_quantity']) ? $node['net_quantity'] : $node['quantity'];
                            $gross = isset($node['gross_quantity']) ? $node['gross_quantity'] : $node['quantity'];
                            $scrap = isset($node['material_scrap_percentage']) ? $node['material_scrap_percentage'] : 0.0;
                            
                            echo '<tr>';
                            echo '<td class="font-monospace text-center">' . $level . '</td>';
                            echo '<td>';
                            echo '<div style="padding-left: ' . $padding . 'px;" class="d-flex align-items-center">';
                            if (!$isLeaf) {
                                echo '<i class="feather-package text-primary me-2 fs-14"></i>';
                            } else {
                                echo '<i class="feather-box text-muted me-2 fs-12"></i>';
                            }
                            echo '<div class="d-flex flex-column">';
                            echo '<span class="fw-bold text-dark">' . e($node['product_name']) . '</span>';
                            echo '<small class="text-muted font-monospace fs-10">' . e($node['product_sku']) . '</small>';
                            echo '</div>';
                            echo '</div>';
                            echo '</td>';
                            
                            echo '<td class="text-end fw-bold">' . number_format($qty, 4) . '</td>';
                            echo '<td>' . e($node['uom_code']) . '</td>';
                            echo '<td class="text-end text-danger">' . number_format($scrap, 2) . '%</td>';
                            echo '<td class="text-end fw-bold text-primary">' . number_format($gross, 4) . '</td>';
                            echo '<td>' . e($bomVersion !== 'N/A' ? "v{$bomVersion}" : '—') . '</td>';
                            echo '<td>';
                            if (isset($node['has_sub_bom']) && $node['has_sub_bom']) {
                                echo '<span class="badge bg-soft-success text-success">Approved</span>';
                            } else {
                                echo '<span class="badge bg-soft-secondary text-secondary">—</span>';
                            }
                            echo '</td>';
                            echo '</tr>';
                            
                            if (!empty($node['children'])) {
                                foreach ($node['children'] as $child) {
                                    renderExplosionTableRows($child, $level + 1);
                                }
                            }
                        }
                    }
                @endphp

                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th style="width: 8%" class="text-center">Level</th>
                                <th style="width: 32%">Material Component</th>
                                <th style="width: 15%" class="text-end">Qty Required</th>
                                <th style="width: 10%">UOM</th>
                                <th style="width: 10%" class="text-end">Scrap %</th>
                                <th style="width: 15%" class="text-end">Gross Required</th>
                                <th style="width: 10%">Version</th>
                                <th style="width: 10%">BOM Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php renderExplosionTableRows($explosion['tree']) @endphp
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Tab 3: Routing Process -->
            <div class="tab-pane fade" id="tab-routing" role="tabpanel" aria-labelledby="tab-routing-tab">
                @if($bom->routing)
                    <div class="mb-4 p-3 bg-light rounded border border-light">
                        <span class="fw-semibold text-muted d-block fs-11 text-uppercase mb-1">Routing Header Reference</span>
                        <h5 class="fw-bold text-dark mb-1">{{ $bom->routing->name }} ({{ $bom->routing->routing_number }})</h5>
                        <span class="fs-12 text-muted">Version: v{{ $bom->routing->version }} | Status: 
                            <span class="badge bg-soft-success text-success text-uppercase font-monospace fs-10">{{ $bom->routing->status }}</span>
                        </span>
                    </div>

                    <h5 class="fw-bold text-dark mb-3">Operations Stage Sequence</h5>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table">
                            <thead>
                                <tr>
                                    <th style="width: 5%" class="text-center">Seq</th>
                                    <th style="width: 25%">Operation Detail</th>
                                    <th style="width: 15%">Operation Type</th>
                                    <th style="width: 20%">Work Center Location</th>
                                    <th style="width: 15%">Machine Asset</th>
                                    <th class="text-end" style="width: 10%">Setup / Process</th>
                                    <th class="text-center" style="width: 10%">QC Gate</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($bom->routing->operations as $op)
                                    <tr>
                                        <td class="fw-bold text-center font-monospace align-middle">{{ $op->sequence }}</td>
                                        <td class="align-middle">
                                            <span class="fw-bold text-dark">{{ $op->name }}</span>
                                            <span class="badge bg-soft-primary text-primary font-monospace ms-1 fs-9">{{ $op->operation_number }}</span>
                                            @if ($op->description)
                                                <small class="text-muted d-block mt-1">{{ $op->description }}</small>
                                            @endif
                                            @if ($op->is_external)
                                                <span class="badge bg-soft-danger text-danger mt-1 fs-9 text-uppercase">Outsourced</span>
                                            @endif
                                            
                                            <!-- Consumed operation-level materials -->
                                            @if($op->materials->count() > 0)
                                                <div class="mt-2 bg-white p-2 rounded border border-dashed">
                                                    <small class="fw-bold text-muted d-block mb-1 text-uppercase fs-9">Allocated Consumed Materials:</small>
                                                    <ul class="mb-0 ps-3 fs-10 text-muted">
                                                        @foreach($op->materials as $opMat)
                                                            <li>
                                                                 <span class="text-secondary fw-semibold">Seq {{ $opMat->sequence }}:</span>
                                                                <strong>{{ $opMat->material->name }}</strong>: {{ number_format($opMat->quantity, 4) }} {{ $opMat->uom->code }}
                                                                <span class="badge bg-light text-dark fs-8">{{ $opMat->consumption_type ?? 'manual' }}</span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="align-middle">
                                            <span class="badge bg-soft-secondary text-secondary text-uppercase fs-10">
                                                {{ config('production.operation_types')[$op->operation_type] ?? $op->operation_type }}
                                            </span>
                                        </td>
                                        <td class="align-middle">
                                            @if ($op->workCenter)
                                                <div class="d-flex flex-column">
                                                    <a href="{{ route('production.work-centers.show', $op->work_center_id) }}" class="fw-bold text-primary fs-13">
                                                        {{ $op->workCenter->name }}
                                                    </a>
                                                    @if($op->workCenter->parent)
                                                        <small class="text-muted fs-10">
                                                            {{ $op->workCenter->parent->parent ? $op->workCenter->parent->parent->name . ' › ' : '' }}{{ $op->workCenter->parent->name }}
                                                        </small>
                                                    @endif
                                                    <small class="text-secondary font-monospace fs-10 mt-1">{{ $op->workCenter->code }}</small>
                                                </div>
                                            @else
                                                <span class="text-danger">Missing</span>
                                            @endif
                                        </td>
                                        <td class="align-middle">
                                            @if ($op->machine)
                                                <span class="fw-semibold text-dark">{{ $op->machine->name }}</span>
                                                <small class="text-muted d-block fs-10">{{ $op->machine->code }}</small>
                                            @else
                                                <span class="text-muted">Generic Capacity</span>
                                            @endif
                                        </td>
                                        <td class="text-end align-middle font-monospace fs-11">
                                            <div>Setup: {{ number_format($op->setup_time_minutes, 1) }} min</div>
                                            <div>Run: {{ number_format($op->processing_time_minutes, 1) }} min</div>
                                        </td>
                                        <td class="text-center align-middle">
                                            @if ($op->quality_required)
                                                <span class="badge bg-soft-danger text-danger"><i class="feather-shield"></i> QC</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No operation stages defined in this routing.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                @else
                    <div class="p-4 text-center border rounded bg-light text-muted">
                        <i class="feather-info me-2"></i>No Routing Reference associated with this BOM.
                    </div>
                @endif
            </div>

            <!-- Tab 4: Cost Summary -->
            <div class="tab-pane fade" id="tab-costing" role="tabpanel" aria-labelledby="tab-costing-tab">
                <h5 class="fw-bold text-dark mb-3">Total Manufacturing Cost Summary Breakdown</h5>
                <div class="row g-3 mb-4">
                    <!-- Net Material Cost -->
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded border text-center">
                            <span class="text-muted fs-11 text-uppercase fw-bold">Material Cost (Net)</span>
                            <h4 class="text-dark fw-bold mt-1">${{ number_format($costSummary['material_cost'] - $costSummary['scrap_adjustment'], 4) }}</h4>
                            <small class="text-muted">Net of scrap loss</small>
                        </div>
                    </div>
                    <!-- Scrap Loss -->
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded border text-center">
                            <span class="text-muted fs-11 text-uppercase fw-bold text-danger">Scrap Loss</span>
                            <h4 class="text-danger fw-bold mt-1">${{ number_format($costSummary['scrap_adjustment'], 4) }}</h4>
                            <small class="text-muted">Expected material scrap value</small>
                        </div>
                    </div>
                    <!-- Labor Cost -->
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded border text-center">
                            <span class="text-muted fs-11 text-uppercase fw-bold">Labor Cost</span>
                            <h4 class="text-dark fw-bold mt-1">${{ number_format($costSummary['labor_cost'], 4) }}</h4>
                            <small class="text-muted">Routing setup & run labor</small>
                        </div>
                    </div>
                    <!-- Machine Cost -->
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded border text-center">
                            <span class="text-muted fs-11 text-uppercase fw-bold">Machine Cost</span>
                            <h4 class="text-dark fw-bold mt-1">${{ number_format($costSummary['machine_cost'], 4) }}</h4>
                            <small class="text-muted">Machine runtime cost</small>
                        </div>
                    </div>
                    <!-- Overhead Cost -->
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded border text-center">
                            <span class="text-muted fs-11 text-uppercase fw-bold">Work Center Overhead</span>
                            <h4 class="text-dark fw-bold mt-1">${{ number_format($costSummary['overhead_cost'], 4) }}</h4>
                            <small class="text-muted">WC hourly overhead rate x duration</small>
                        </div>
                    </div>
                    <!-- Total Mfg Cost -->
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded border text-center bg-soft-primary border-primary">
                            <span class="text-primary fs-11 text-uppercase fw-bold">Total Manufacturing Cost</span>
                            <h4 class="text-primary fw-bold mt-1">${{ number_format($costSummary['total_cost'], 4) }}</h4>
                            <small class="text-primary">Sum of all cost layers</small>
                        </div>
                    </div>
                </div>

                <!-- Cost Details Item Table -->
                <h6 class="fw-bold text-dark mb-3">Direct Component Cost Contributions</h6>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th style="width: 40%">Material Component</th>
                                <th style="width: 15%" class="text-end">Base Qty</th>
                                <th style="width: 10%" class="text-end">Scrap %</th>
                                <th style="width: 15%" class="text-end">Gross Qty Required</th>
                                <th style="width: 10%" class="text-end">Unit Cost</th>
                                <th style="width: 10%" class="text-end">Total Item Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($costSummary['items'] as $cItem)
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-dark">{{ $cItem['material_name'] }}</span>
                                            <small class="text-muted font-monospace fs-10">{{ $cItem['material_sku'] }}</small>
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
                                    <td colspan="6" class="text-center py-4 text-muted">No pricing components available.</td>
                                </tr>
                            @endforelse
                            <tr class="table-light fw-bold">
                                <td colspan="5" class="text-end">Estimated Total Material Cost:</td>
                                <td class="text-end text-primary fs-14">${{ number_format($materialCost, 4) }}</td>
                            </tr>
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Tab 5: Approval History -->
            <div class="tab-pane fade" id="tab-history" role="tabpanel" aria-labelledby="tab-history-tab">
                <h5 class="fw-bold text-dark mb-3">Workflow approvals & system audit timeline logs</h5>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th style="width: 20%">Timestamp</th>
                                <th style="width: 20%">User</th>
                                <th style="width: 20%">Transition</th>
                                <th style="width: 40%">Notes / Reasons</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bom->approvals as $approval)
                                <tr>
                                    <td>{{ $approval->created_at->format('d/m/Y H:i:s') }}</td>
                                    <td>{{ $approval->user ? $approval->user->name : 'System / Auto' }}</td>
                                    <td>
                                        <span class="badge bg-soft-info text-info text-capitalize">{{ str_replace('_', ' ', $approval->action ?? $approval->transition_type ?? '') }}</span>
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ $approval->comments ?: '—' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No state transition history found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Tab 6: Where Used -->
            <div class="tab-pane fade" id="tab-whereused" role="tabpanel" aria-labelledby="tab-whereused-tab">
                <h5 class="fw-bold text-dark mb-3"><i class="feather-git-merge me-2 text-primary"></i>Where Used (Consumed In Parent BOMs)</h5>
                @if($whereUsedBoms->count() > 0)
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table">
                            <thead>
                                <tr>
                                    <th style="width: 30%">Parent Product Name</th>
                                    <th style="width: 20%">BOM Number</th>
                                    <th style="width: 10%">Version</th>
                                    <th style="width: 15%">Status</th>
                                    <th style="width: 10%">Effective Date</th>
                                    <th style="width: 10%">Expiry Date</th>
                                    <th style="width: 5%" class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($whereUsedBoms as $wBom)
                                    <tr>
                                        <td class="fw-bold text-dark">
                                            {{ $wBom->product->name }}
                                            <small class="text-muted d-block font-monospace fs-10">{{ $wBom->product->sku }}</small>
                                        </td>
                                        <td>{{ $wBom->bom_number }}</td>
                                        <td>v{{ $wBom->version }}</td>
                                        <td>
                                            @if($wBom->status === 'approved')
                                                <span class="badge bg-soft-success text-success">Approved</span>
                                            @elseif($wBom->status === 'draft')
                                                <span class="badge bg-soft-warning text-warning">Draft</span>
                                            @elseif($wBom->status === 'pending_approval')
                                                <span class="badge bg-soft-info text-info">Pending</span>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary text-uppercase">{{ $wBom->status }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $wBom->effective_date ? $wBom->effective_date->format('d/m/Y') : 'N/A' }}</td>
                                        <td>{{ $wBom->expiry_date ? $wBom->expiry_date->format('d/m/Y') : 'No Expiry' }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('production.boms.show', $wBom->id) }}" class="btn btn-xs btn-soft-primary px-2 py-1 fs-11 text-nowrap">
                                                <i class="feather-eye me-1"></i>View BOM
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                @else
                    <div class="p-3 text-center border rounded bg-light text-muted fs-12">
                        <i class="feather-info me-2"></i>This product is not consumed in any other parent assembly BOMs.
                    </div>
                @endif
            </div>
        </div>

        <!-- Modals -->
        <!-- Duplicate Modal -->
        <x-ui.modal id="duplicateModal" title="Duplicate BOM Version" submit-text="Create Version" class="text-start">
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
        <x-ui.modal id="rejectModal" title="Reject BOM Version" submit-text="Reject Version" class="text-start">
            <form method="POST" action="{{ route('production.boms.reject', $bom->id) }}" id="rejectForm">
                @csrf
                <p class="fs-13 text-muted">Provide comments explaining the reason for rejection.</p>
                <x-ui.input label="Rejection Reason" name="comments" placeholder="e.g. Scrap percentage is too high" required />
            </form>
            <x-slot name="footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger" onclick="document.getElementById('rejectForm').submit();">Reject BOM</button>
            </x-slot>
        </x-ui.modal>

        <!-- Cancel Modal -->
        <x-ui.modal id="cancelModal" title="Cancel BOM Version" submit-text="Cancel Version" class="text-start">
            <form method="POST" action="{{ route('production.boms.cancel', $bom->id) }}" id="cancelForm">
                @csrf
                <p class="fs-13 text-muted">Provide comments explaining why this BOM is being cancelled.</p>
                <x-ui.input label="Cancellation Reason" name="comments" placeholder="e.g. Product design obsolete" required />
            </form>
            <x-slot name="footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger" onclick="document.getElementById('cancelForm').submit();">Cancel BOM</button>
            </x-slot>
        </x-ui.modal>
    </div>

    <script>
        @if(isset($parentProduct))
            // Notify opener (parent BOM page) about the newly created child BOM
            if (window.opener && !window.opener.closed) {
                window.opener.postMessage({
                    type: 'CHILD_BOM_CREATED',
                    product_id: '{{ $bom->product_id }}'
                }, '*');
            }
        @endif
    </script>
@endsection
