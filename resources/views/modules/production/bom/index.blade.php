@extends('layouts.duralux')

@section('title', 'Production BOM | SaaS ERP')
@section('page-title', 'Bill of Materials (BOM) Management')
@section('breadcrumb', 'BOM Management')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
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
    <div class="erp-single-panel">
        <!-- Success & Error Messages -->
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
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Filters Section - Inlined on Single panel -->
        <form method="GET" action="{{ route('production.boms.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4 erp-form-input-col">
                    <input type="text" name="search" class="form-control" placeholder="Search BOM number, name or sku..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-3 erp-form-input-col">
                    <select name="product_id" class="form-select" data-select2-selector="default">
                        <option value="">All Products</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                                {{ $product->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 erp-form-input-col">
                    <select name="status" class="form-select" data-select2-selector="default">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending_approval" {{ request('status') === 'pending_approval' ? 'selected' : '' }}>Pending Approval</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved / Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-grid w-100">
                        <button type="submit" class="btn btn-secondary h-40">
                            <i class="feather-filter me-2"></i>Filter
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- BOM List Table -->
        <div class="table-responsive">
            <table class="erp-thin-table">
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
                        <th class="text-end" style="width: 15%">Actions</th>
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
                                <div class="d-inline-flex gap-1 justify-content-end align-items-center">
                                    {{-- View --}}
                                    <x-ui.icon-btn href="{{ route('production.boms.show', $bom->id) }}" variant="soft-info" title="View Recipe" icon="feather-eye" />

                                    {{-- Edit / Submit (draft only) --}}
                                    @if($bom->isDraft() || $bom->isUnderRevision())
                                        <x-ui.icon-btn href="{{ route('production.boms.edit', $bom->id) }}" variant="soft-primary" title="Edit Draft" icon="feather-edit" />
                                        <form method="POST" action="{{ route('production.boms.submit', $bom->id) }}" class="d-inline">
                                            @csrf
                                            <x-ui.icon-btn type="submit" variant="soft-info" title="Submit for Approval" icon="feather-send" />
                                        </form>
                                    @endif

                                    {{-- Approve / Reject --}}
                                    @if($bom->isPendingApproval())
                                        <form method="POST" action="{{ route('production.boms.approve', $bom->id) }}" class="d-inline">
                                            @csrf
                                            <x-ui.icon-btn type="submit" variant="soft-success" title="Approve BOM" icon="feather-check-circle" />
                                        </form>
                                        <x-ui.icon-btn type="button" variant="soft-danger" title="Reject BOM" icon="feather-x-circle" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $bom->id }}" />
                                    @endif

                                    {{-- Cancel --}}
                                    @if($bom->isApproved())
                                        <x-ui.icon-btn type="button" variant="soft-danger" title="Cancel BOM" icon="feather-slash" data-bs-toggle="modal" data-bs-target="#cancelModal{{ $bom->id }}" />
                                    @endif

                                    {{-- Duplicate --}}
                                    <x-ui.icon-btn type="button" variant="soft-warning" title="Duplicate Version" icon="feather-copy" data-bs-toggle="modal" data-bs-target="#duplicateModal{{ $bom->id }}" />

                                    {{-- Delete (draft only) --}}
                                    @if($bom->isDraft() || $bom->isUnderRevision())
                                        <form method="POST" action="{{ route('production.boms.destroy', $bom->id) }}" class="d-inline" onsubmit="return confirm('Delete this BOM permanently?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.icon-btn type="submit" variant="soft-danger" title="Delete Permanent" icon="feather-trash-2" />
                                        </form>
                                    @endif
                                </div>

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
            </table>
        </div>
    </div>
@endsection
