@extends('layouts.duralux')

@section('title', 'BOM Details | SaaS ERP')

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
    <div class="erp-single-panel bg-white">
        <!-- Success & Error Banners -->
        @if(request()->has('parent_product_id'))
            <x-ui.alert variant="success" icon="feather-check-circle" class="mb-4">
                <div class="d-flex align-items-center justify-content-between w-100" style="width: 100%;">
                    <div>
                        <h6 class="alert-heading fw-bold mb-1">Child BOM Saved Successfully!</h6>
                        <p class="fs-12 mb-0">The child BOM for <strong>{{ $bom->product->name }}</strong> is now saved. You can close this tab and return to the parent tab.</p>
                    </div>
                    <div>
                        <button type="button" class="btn btn-success btn-sm ms-3" onclick="window.close();">
                            <i class="feather-x-circle me-1"></i>Close & Return
                        </button>
                    </div>
                </div>
            </x-ui.alert>
        @elseif (session('success'))
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
        <div class="erp-tabs-nav">
            <a class="erp-tabs-link active" id="btn-tab-components" onclick="switchTab('components')">Components List</a>
            <a class="erp-tabs-link" id="btn-tab-costing" onclick="switchTab('costing')">Cost Preview</a>
            <a class="erp-tabs-link" id="btn-tab-hierarchy" onclick="switchTab('hierarchy')">BOM Explosion Tree</a>
            <a class="erp-tabs-link" id="btn-tab-history" onclick="switchTab('history')">Approval & Audit History</a>
        </div>

        <!-- TAB CONTENT CONTAINER (No cards, sits flat) -->
        <div class="tab-content mt-3">
            <!-- Tab 1: Components -->
            <div class="tab-pane-custom" id="tab-components">
                <h5 class="fw-bold text-dark mb-3">Required Components (Recipe Items)</h5>
                <div class="table-responsive">
                    <table class="erp-thin-table">
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
                            @forelse($bom->items as $item)
                                <tr>
                                    <td class="text-center fw-semibold text-muted">{{ $item->sequence }}</td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-dark">{{ $item->material->name }}</span>
                                            <small class="text-muted font-monospace fs-10">{{ $item->material->sku }}</small>
                                            @if($item->notes)
                                                <small class="text-muted mt-1"><i class="feather-info me-1"></i>{{ $item->notes }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold">{{ number_format($item->quantity, 4) }}</td>
                                    <td>
                                        <span class="text-muted">{{ $item->uom ? $item->uom->code : 'PCS' }}</span>
                                    </td>
                                    <td class="text-end text-danger fw-semibold">
                                        {{ number_format($item->material_scrap_percentage, 2) }}%
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No components found for this recipe.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Costing -->
            <div class="tab-pane-custom d-none" id="tab-costing">
                <h5 class="fw-bold text-dark mb-3">Cost Breakdown of Raw Components</h5>
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

                <div class="table-responsive">
                    <table class="erp-thin-table">
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
                                <td class="text-end text-primary fs-14">${{ number_format($costSummary['total_cost'], 4) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 3: Hierarchy Tree -->
            <div class="tab-pane-custom d-none" id="tab-hierarchy">
                <h5 class="fw-bold text-dark mb-3">Recursive Multi-Level BOM Structure</h5>
                <p class="text-muted fs-12 mb-3">Below is the complete engineering bill of materials structure showing recursively expanded sub-assemblies (semi-finished products) down to raw components.</p>
                
                @php
                    if (!function_exists('renderHtmlBomTree')) {
                        function renderHtmlBomTree($node) {
                            echo '<li class="mb-2 list-unstyled">';
                            echo '<div class="d-flex align-items-center gap-3 p-2 bg-light rounded border border-light">';
                            
                            if (!empty($node['children'])) {
                                echo '<i class="feather-package text-primary fs-16"></i>';
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
                                echo '<ul class="ps-4 border-start border-primary border-2 ms-3 mt-2">';
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
            </div>

            <!-- Tab 4: History -->
            <div class="tab-pane-custom d-none" id="tab-history">
                <h5 class="fw-bold text-dark mb-3">Workflow approvals & system audit timeline logs</h5>
                <div class="table-responsive">
                    <table class="erp-thin-table">
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
                                        <span class="badge bg-soft-info text-info text-capitalize">{{ str_replace('_', ' ', $approval->transition_type) }}</span>
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
                    </table>
                </div>
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
        function switchTab(tabId) {
            // Remove active class from all links
            document.querySelectorAll('.erp-tabs-link').forEach(link => {
                link.classList.remove('active');
            });
            // Add active class to clicked link
            document.getElementById('btn-tab-' + tabId).classList.add('active');

            // Hide all tabs
            document.querySelectorAll('.tab-pane-custom').forEach(pane => {
                pane.classList.add('d-none');
            });
            // Show target tab
            document.getElementById('tab-' + tabId).classList.remove('d-none');
        }
    </script>
@endsection
