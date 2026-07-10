@extends('layouts.duralux')

@section('title', 'Work Centers | SaaS ERP')
@section('page-title', 'Work Center Master')
@section('breadcrumb', 'Work Centers')

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
    @can('create', App\Domains\Production\Models\WorkCenter::class)
        <a href="{{ route('production.work-centers.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>Create Work Center
        </a>
    @endcan
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'code');
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
            <h5 class="fw-bold text-dark mb-0">Work Center List</h5>
            <div class="d-flex gap-2 ms-auto">
                {{-- Sort Dropdown --}}
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'code', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'code' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Code (A&ndash;Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'code', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'code' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Code (Z&ndash;A)</span>
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
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'cost_per_hour', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'cost_per_hour' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Cost/Hr (Low to High)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'cost_per_hour', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'cost_per_hour' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Cost/Hr (High to Low)</span>
                    </a>
                </x-ui.sort-dropdown>

                {{-- Filter Overlay --}}
                <form method="GET" action="{{ route('production.work-centers.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search code, name, or department..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Work Center Type</label>
                            <x-ui.odoo-form-ui type="select" name="work_center_type">
                                <option value="">All Types</option>
                                @foreach($workCenterTypes as $value => $label)
                                    <option value="{{ $value }}" {{ request('work_center_type') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.work-centers.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- Work Centers Table --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th style="width: 9%">Code</th>
                        <th style="width: 14%">Name</th>
                        <th style="width: 16%">Hierarchy Path</th>
                        <th style="width: 9%">Type</th>
                        <th style="width: 11%">Department</th>
                        <th style="width: 9%">Location</th>
                        <th style="width: 7%" class="text-end">Capacity/Hr</th>
                        <th style="width: 6%" class="text-end">Efficiency</th>
                        <th style="width: 6%" class="text-end">Cost/Hr</th>
                        <th style="width: 5%" class="text-center">Machines</th>
                        <th style="width: 5%">Status</th>
                        <th style="width: 6%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($workCenters as $wc)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td>
                                <a href="{{ route('production.work-centers.show', $wc->id) }}" class="fw-bold text-primary hover-primary">
                                    {{ $wc->code }}
                                </a>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $wc->name }}</span>
                            </td>
                            <td class="fs-11 text-muted">{{ $wc->getHierarchyPath() }}</td>
                            <td>
                                <span class="badge bg-soft-primary text-primary text-uppercase fs-10 rounded-pill px-2 py-1">
                                    {{ $workCenterTypes[$wc->work_center_type] ?? $wc->work_center_type ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="text-muted">{{ $wc->department_name ?? '—' }}</td>
                            <td class="text-muted">{{ $wc->location ?? '—' }}</td>
                            <td class="text-end fw-semibold">
                                {{ $wc->capacity_per_hour !== null ? number_format($wc->capacity_per_hour, 2) : 'Unlimited' }}
                            </td>
                            <td class="text-end text-muted">{{ number_format($wc->efficiency_percentage, 0) }}%</td>
                            <td class="text-end fw-semibold text-dark">${{ number_format($wc->cost_per_hour, 2) }}</td>
                            <td class="text-center">
                                <a href="{{ route('production.machines.index', ['work_center_id' => $wc->id]) }}"
                                   class="badge bg-soft-info text-info rounded-pill px-2 py-1 fw-bold">
                                    {{ $wc->machines_count }}
                                </a>
                            </td>
                            <td>
                                @if ($wc->isActive())
                                    <span class="erp-badge-active">Active</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger rounded-pill px-2 py-1">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <x-ui.action-dropdown :viewUrl="route('production.work-centers.show', $wc->id)">
                                    @can('update', $wc)
                                        <li>
                                            <a href="{{ route('production.work-centers.edit', $wc->id) }}" class="dropdown-item">
                                                <i class="feather-edit me-2 text-muted fs-12"></i>Edit Work Center
                                            </a>
                                        </li>
                                    @endcan
                                    @can('delete', $wc)
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('production.work-centers.destroy', $wc->id) }}" method="POST"
                                                  onsubmit="return confirm('Are you sure you want to delete this work center?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="feather-trash-2 me-2 text-danger fs-12"></i>Delete Permanent
                                                </button>
                                            </form>
                                        </li>
                                    @endcan
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-center py-4 text-muted">
                                <i class="feather-info me-2 fs-16"></i>No work centers found. Click "Create Work Center" to start.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="mt-4">
            {{ $workCenters->links() }}
        </div>
    </div>
@endsection
