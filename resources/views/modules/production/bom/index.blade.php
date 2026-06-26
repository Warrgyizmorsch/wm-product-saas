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

    <!-- Filters Section -->
    <x-ui.card class="mb-4">
        <form method="GET" action="{{ route('production.boms.index') }}">
            <div class="row g-3">
                <div class="col-md-4">
                    <x-ui.input label="Search BOM" name="search" placeholder="BOM number, name or sku..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-3">
                    <x-ui.select label="Filter by Product" name="product_id" :options="['' => 'All Products'] + $products->pluck('name', 'id')->toArray()" selected="{{ request('product_id') }}" data-select2-selector="default" />
                </div>
                <div class="col-md-3">
                    <x-ui.select label="Filter by Status" name="status" :options="[
                        '' => 'All Statuses',
                        'draft' => 'Draft',
                        'pending_approval' => 'Pending Approval',
                        'approved' => 'Approved / Active',
                        'inactive' => 'Inactive',
                        'cancelled' => 'Cancelled'
                    ]" selected="{{ request('status') }}" data-select2-selector="default" />
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-grid w-100">
                        <button type="submit" class="btn btn-light-brand h-42">
                            <i class="feather-filter me-2"></i>Filter
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </x-ui.card>

    <!-- BOM List Table -->
    <x-ui.card>
        <x-ui.table title="Production Recipes (BOMs)" striped hoverable>
            <thead>
                <tr>
                    <th>BOM Number</th>
                    <th>BOM Name</th>
                    <th>Target Finished Product</th>
                    <th>BOM Type</th>
                    <th class="text-end">Base Qty</th>
                    <th>Version (Rev)</th>
                    <th>Effective Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($boms as $bom)
                    <tr>
                        <td>
                            <a href="{{ route('production.boms.show', $bom->id) }}" class="fw-bold text-dark hover-primary">
                                <i class="feather-file-text text-muted me-2"></i>{{ $bom->bom_number }}
                            </a>
                        </td>
                        <td>
                            <span class="text-dark fw-semibold fs-12">{{ $bom->bom_name ?: 'N/A' }}</span>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-bold text-dark fs-12">{{ $bom->product->name }}</span>
                                <small class="text-muted fs-11">{{ $bom->product->sku }}</small>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-soft-info text-info text-capitalize fs-10">{{ $bom->bom_type }}</span>
                        </td>
                        <td class="text-end fw-bold">
                            {{ number_format($bom->base_quantity, 2) }}
                            <small class="text-muted fw-normal">{{ $bom->baseUom ? $bom->baseUom->code : 'PCS' }}</small>
                        </td>
                        <td>
                            <span class="badge bg-soft-secondary text-secondary">
                                v{{ $bom->version }} (r{{ $bom->revision }})
                            </span>
                        </td>
                        <td>
                            <span class="fs-12">{{ $bom->effective_date ? $bom->effective_date->format('d/m/Y') : 'N/A' }}</span>
                            @if($bom->expiry_date)
                                <small class="text-muted d-block fs-11">Expires: {{ $bom->expiry_date->format('d/m/Y') }}</small>
                            @endif
                        </td>
                        <td>
                            @if($bom->status === 'draft')
                                <span class="badge bg-soft-warning text-warning">Draft</span>
                            @elseif($bom->status === 'pending_approval')
                                <span class="badge bg-soft-primary text-primary">Pending Approval</span>
                            @elseif($bom->status === 'approved')
                                <span class="badge bg-soft-success text-success">Approved / Active</span>
                            @elseif($bom->status === 'cancelled')
                                <span class="badge bg-soft-danger text-danger">Cancelled</span>
                            @else
                                <span class="badge bg-soft-secondary text-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-light-brand dropdown-toggle no-arrow" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="feather-more-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('production.boms.show', $bom->id) }}">
                                            <i class="feather-eye me-2 text-muted"></i>View Recipe
                                        </a>
                                    </li>
                                    
                                    @if($bom->isDraft() || $bom->isUnderRevision())
                                        <li>
                                            <a class="dropdown-item" href="{{ route('production.boms.edit', $bom->id) }}">
                                                <i class="feather-edit me-2 text-muted"></i>Edit Draft
                                            </a>
                                        </li>
                                        <li>
                                            <form method="POST" action="{{ route('production.boms.submit', $bom->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="feather-send me-2 text-muted"></i>Submit Approval
                                                </button>
                                            </form>
                                        </li>
                                    @endif

                                    @if($bom->isPendingApproval())
                                        <li>
                                            <form method="POST" action="{{ route('production.boms.approve', $bom->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-success">
                                                    <i class="feather-check-circle me-2"></i>Approve BOM
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $bom->id }}">
                                                <i class="feather-x-circle me-2"></i>Reject BOM
                                            </a>
                                        </li>
                                    @endif

                                    @if($bom->isApproved())
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#cancelModal{{ $bom->id }}">
                                                <i class="feather-slash me-2"></i>Cancel BOM
                                            </a>
                                        </li>
                                    @endif

                                    <li>
                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#duplicateModal{{ $bom->id }}">
                                            <i class="feather-copy me-2 text-muted"></i>Duplicate Version
                                        </a>
                                    </li>

                                    @if($bom->isDraft() || $bom->isUnderRevision())
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('production.boms.destroy', $bom->id) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this BOM?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="feather-trash-2 me-2"></i>Delete BOM
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </ul>
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
                        <td colspan="9" class="text-center py-4">
                            <i class="feather-info fs-24 text-muted mb-2 d-block"></i>
                            <span class="text-muted">No Bill of Materials found. Click "Create New BOM" to start.</span>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>
@endsection
