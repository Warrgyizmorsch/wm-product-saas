@extends('layouts.duralux')

@section('title', 'Routing Management | SaaS ERP')
@section('page-title', 'Routing Master Data')
@section('breadcrumb', 'Routings')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .erp-single-panel {
            display: flex !important;
            flex-direction: column !important;
            min-height: calc(100vh - 180px) !important;
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
    @can('create', App\Domains\Production\Models\Routing::class)
        <a href="{{ route('production.routing.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>Create New Routing
        </a>
    @endcan
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'routing_number');
        $sortOrder = request('sort_order', 'asc');
    @endphp

    <div class="erp-single-panel">
        {{-- Toast Notifications --}}
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Toolbar: Title + Sort + Filter --}}
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Routing List</h5>
            <div class="d-flex gap-2 ms-auto">
                {{-- Sort Dropdown --}}
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'routing_number', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'routing_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Routing Number (Asc)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'routing_number', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'routing_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Routing Number (Desc)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Name (A&ndash;Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Name (Z&ndash;A)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'version', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'version' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Version (Asc)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'version', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'version' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Version (Desc)</span>
                    </a>
                </x-ui.sort-dropdown>

                {{-- Filter Overlay --}}
                <form method="GET" action="{{ route('production.routing.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search number, name, or SKU..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Finished Product</label>
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
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="historical" {{ request('status') === 'historical' ? 'selected' : '' }}>Historical</option>
                                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.routing.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- Routings Table --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th style="width: 12%">Routing Number</th>
                        <th style="width: 18%">Routing Name</th>
                        <th style="width: 20%">Product to Manufacture</th>
                        <th style="width: 7%">Version</th>
                        <th style="width: 6%" class="text-center">Operations</th>
                        <th style="width: 8%">Effective From</th>
                        <th style="width: 8%">Effective To</th>
                        <th style="width: 6%">Type</th>
                        <th style="width: 7%">Status</th>
                        <th style="width: 5%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($routings as $routing)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td>
                                <a href="{{ route('production.routing.show', $routing->id) }}" class="fw-bold text-primary hover-primary">
                                    {{ $routing->routing_number }}
                                </a>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $routing->name }}</span>
                            </td>
                            <td>
                                @if ($routing->product)
                                    <div class="d-flex flex-column">
                                        <span class="fw-semibold text-dark">{{ $routing->product->name }}</span>
                                        <small class="text-muted font-monospace fs-10">{{ $routing->product->sku }}</small>
                                    </div>
                                @else
                                    <span class="text-muted">No Product</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-dark">{{ $routing->version }}</span>
                                @if ($routing->revision > 0)
                                    <small class="text-muted d-block fs-10">Rev {{ $routing->revision }}</small>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-soft-info text-info rounded-pill px-2 py-1 fw-bold">
                                    {{ $routing->operations_count }}
                                </span>
                            </td>
                            <td class="text-muted">{{ $routing->effective_from ? $routing->effective_from->format('Y-m-d') : 'Immediate' }}</td>
                            <td class="text-muted">{{ $routing->effective_to ? $routing->effective_to->format('Y-m-d') : 'Indefinite' }}</td>
                            <td>
                                @if ($routing->is_default)
                                    <span class="badge bg-soft-success text-success px-2 py-1 rounded-pill fs-10">Primary</span>
                                @else
                                    <span class="badge bg-soft-warning text-warning px-2 py-1 rounded-pill fs-10">Alt</span>
                                @endif
                            </td>
                            <td>
                                @if ($routing->isDraft())
                                    <span class="erp-badge-draft">Draft</span>
                                @elseif ($routing->isPendingApproval())
                                    <span class="erp-badge-pending">Pending</span>
                                @elseif ($routing->isActive())
                                    <span class="erp-badge-active">Active</span>
                                @elseif ($routing->isHistorical())
                                    <span class="badge bg-soft-info text-info rounded-pill px-2 py-1">Historical</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger rounded-pill px-2 py-1">Cancelled</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <x-ui.action-dropdown :viewUrl="route('production.routing.show', $routing->id)">
                                    @can('update', $routing)
                                        <li>
                                            <a href="{{ route('production.routing.edit', $routing->id) }}" class="dropdown-item">
                                                <i class="feather-edit me-2 text-muted fs-12"></i>Edit Routing
                                            </a>
                                        </li>
                                    @endcan
                                    @if ($routing->isDraft())
                                        @can('delete', $routing)
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('production.routing.destroy', $routing->id) }}" method="POST"
                                                      onsubmit="return confirm('Delete this draft routing permanently?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather-trash-2 me-2 text-danger fs-12"></i>Delete Draft
                                                    </button>
                                                </form>
                                            </li>
                                        @endcan
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted">
                                <i class="feather-info me-2 fs-16"></i>No process routings found. Click "Create New Routing" to start.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="mt-4">
            {{ $routings->links() }}
        </div>
    </div>
@endsection
