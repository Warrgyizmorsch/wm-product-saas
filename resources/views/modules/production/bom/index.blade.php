@extends('layouts.duralux')

@section('title', 'Production BOM | SaaS ERP')
@section('page-title', 'Bill of Materials (BOM) Management')
@section('breadcrumb', 'BOM Management')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .erp-single-panel {
            display: flex !important;
            flex-direction: column !important;
            min-height: calc(100vh - 180px) !important;
        }
        .table-responsive {
            position: relative;
        }
        .table-responsive:has(.dropdown.show) {
            overflow: visible !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <a href="{{ route('production.boms.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>Create New BOM
    </a>
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'bom_number');
        $sortOrder = request('sort_order', 'asc');
    @endphp

    <div class="erp-single-panel">
        <!-- Success & Error Messages (Rendered via Toast Component) -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <!-- Toolbar: Sort, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">BOM List</h5>
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Component -->
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'bom_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'bom_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>BOM Number (Asc)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'bom_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'bom_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>BOM Number (Desc)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'bom_name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'bom_name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>BOM Name (A-Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'bom_name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'bom_name' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>BOM Name (Z-A)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'base_quantity', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'base_quantity' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Quantity (Low to High)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'base_quantity', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'base_quantity' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Quantity (High to Low)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('production.boms.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search BOM number, name..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Item to Produce</label>
                            <x-ui.odoo-form-ui type="select" name="product_id">
                                <option value="">All Products</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                                        {{ $product->name }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="pending_approval" {{ request('status') === 'pending_approval' ? 'selected' : '' }}>Pending Approval</option>
                                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved / Active</option>
                                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.boms.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- BOM List Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th style="width: 15%">Bill of Material#</th>
                        <th style="width: 20%">Bill of Material Name</th>
                        <th style="width: 12%">Status</th>
                        <th style="width: 15%">Description</th>
                        <th style="width: 18%">Item to Produce</th>
                        <th style="width: 10%" class="text-end">Quantity to Produce</th>
                        <th style="width: 7%">Unit</th>
                        <th class="text-end" style="width: 10%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($boms as $bom)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td>
                                <a href="{{ route('production.boms.show', $bom->id) }}" class="fw-bold text-primary hover-primary">
                                    {{ $bom->bom_number }}
                                </a>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $bom->bom_name ?: 'N/A' }}</span>
                            </td>
                            <td>
                                @if($bom->status === 'approved')
                                    <span class="erp-badge-active">Active</span>
                                @elseif($bom->status === 'draft')
                                    <span class="erp-badge-draft">Draft</span>
                                @elseif($bom->status === 'pending_approval')
                                    <span class="erp-badge-pending">Pending</span>
                                @else
                                    <span class="erp-badge-draft text-uppercase">{{ $bom->status }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-muted text-truncate d-inline-block" style="max-width: 150px;" title="{{ $bom->notes }}">
                                    {{ $bom->notes ?: '—' }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-dark">{{ $bom->product->name }}</span>
                                    <small class="text-muted font-monospace fs-10">{{ $bom->product->sku }}</small>
                                </div>
                            </td>
                            <td class="text-end fw-bold">
                                {{ number_format($bom->base_quantity, 2) }}
                            </td>
                            <td>
                                <span class="text-muted">{{ $bom->baseUom ? $bom->baseUom->code : 'PCS' }}</span>
                            </td>
                            <td class="text-end">
                                <x-ui.action-dropdown :viewUrl="route('production.boms.show', $bom->id)">
                                    {{-- Edit / Submit (draft only) --}}
                                    @if($bom->isDraft() || $bom->isUnderRevision())
                                        <li>
                                            <a href="{{ route('production.boms.edit', $bom->id) }}" class="dropdown-item">
                                                <i class="feather-edit me-2 text-muted fs-12"></i>Edit Draft
                                            </a>
                                        </li>
                                        <li>
                                            @if($bom->routing_id)
                                                <form method="POST" action="{{ route('production.boms.submit', $bom->id) }}">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="feather-send me-2 text-muted fs-12"></i>Submit Approval
                                                    </button>
                                                </form>
                                            @else
                                                <button type="button" class="dropdown-item text-muted" disabled title="Routing reference is required before submitting for approval" data-bs-toggle="tooltip" style="cursor: not-allowed;">
                                                    <i class="feather-send me-2 text-muted fs-12"></i>Submit Approval (Routing Required)
                                                </button>
                                            @endif
                                        </li>
                                    @endif

                                    {{-- Approve / Reject --}}
                                    @if($bom->isPendingApproval())
                                        <li>
                                            <form method="POST" action="{{ route('production.boms.approve', $bom->id) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-success">
                                                    <i class="feather-check-circle me-2 text-success fs-12"></i>Approve BOM
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $bom->id }}">
                                                <i class="feather-x-circle me-2 text-danger fs-12"></i>Reject BOM
                                            </button>
                                        </li>
                                    @endif

                                    {{-- Cancel --}}
                                    @if($bom->isApproved())
                                        <li>
                                            <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#cancelModal{{ $bom->id }}">
                                                <i class="feather-slash me-2 text-danger fs-12"></i>Cancel BOM
                                            </button>
                                        </li>
                                    @endif

                                    {{-- Duplicate --}}
                                    <li>
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#duplicateModal{{ $bom->id }}">
                                            <i class="feather-copy me-2 text-muted fs-12"></i>Duplicate Version
                                        </button>
                                    </li>

                                    {{-- Delete (draft only) --}}
                                    @if($bom->isDraft() || $bom->isUnderRevision())
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('production.boms.destroy', $bom->id) }}" onsubmit="return confirm('Delete this BOM permanently?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="feather-trash-2 me-2 text-danger fs-12"></i>Delete Permanent
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>

                                <!-- Duplicate Modal -->
                                <x-ui.modal id="duplicateModal{{ $bom->id }}" title="Duplicate BOM Version" submit-text="Create Version" class="text-start">
                                    <form method="POST" action="{{ route('production.boms.duplicate', $bom->id) }}" id="dupForm{{ $bom->id }}">
                                        @csrf
                                        <p class="fs-13 text-muted">Enter a new version string for this recipe duplicate. The new version will be created as a Draft.</p>
                                        <x-ui.input label="New Version Name" name="new_version" placeholder="e.g. 1.1.0 or 2.0.0" required />
                                    </form>
                                    <x-slot name="footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary" onclick="document.getElementById('dupForm{{ $bom->id }}').submit();">Duplicate Version</button>
                                    </x-slot>
                                </x-ui.modal>

                                <!-- Reject Modal -->
                                <x-ui.modal id="rejectModal{{ $bom->id }}" title="Reject BOM Version" submit-text="Reject Version" class="text-start">
                                    <form method="POST" action="{{ route('production.boms.reject', $bom->id) }}" id="rejectForm{{ $bom->id }}">
                                        @csrf
                                        <p class="fs-13 text-muted">Provide comments explaining the reason for rejection.</p>
                                        <x-ui.input label="Rejection Reason" name="comments" placeholder="e.g. Scrap percentage is too high" required />
                                    </form>
                                    <x-slot name="footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger" onclick="document.getElementById('rejectForm{{ $bom->id }}').submit();">Reject BOM</button>
                                    </x-slot>
                                </x-ui.modal>

                                <!-- Cancel Modal -->
                                <x-ui.modal id="cancelModal{{ $bom->id }}" title="Cancel BOM Version" submit-text="Cancel Version" class="text-start">
                                    <form method="POST" action="{{ route('production.boms.cancel', $bom->id) }}" id="cancelForm{{ $bom->id }}">
                                        @csrf
                                        <p class="fs-13 text-muted">Provide comments explaining why this BOM is being cancelled.</p>
                                        <x-ui.input label="Cancellation Reason" name="comments" placeholder="e.g. Product design obsolete" required />
                                    </form>
                                    <x-slot name="footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger" onclick="document.getElementById('cancelForm{{ $bom->id }}').submit();">Cancel BOM</button>
                                    </x-slot>
                                </x-ui.modal>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="feather-info me-2 fs-16"></i>No Bills of Material found. Click "Create New BOM" to start.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="mt-4">
            {{ $boms->links() }}
        </div>
    </div>
@endsection
