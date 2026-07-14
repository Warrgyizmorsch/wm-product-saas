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
    <div class="d-flex align-items-center gap-2">
        <x-ui.import-export-dropdown type="work-centers" importModalTarget="#importWorkCentersModal" />
        @can('create', App\Domains\Production\Models\WorkCenter::class)
            <a href="{{ route('production.work-centers.create') }}" class="btn btn-primary">
                <i class="feather-plus me-2"></i>Create Work Center
            </a>
        @endcan
    </div>
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
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter
                            Options</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search code, name, or department..."
                                value="{{ request('search') }}" />
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
                                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive
                                </option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.work-centers.index') }}"
                                class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        @php
            $hasFilters = !empty(request('search')) || !empty(request('work_center_type')) || !empty(request('status'));
        @endphp

        @if(!$hasFilters)
            @php
                $allWorkCenters = \App\Domains\Production\Models\WorkCenter::withCount('machines')->get();
                $orderedWorkCenters = collect();
                $visitedIds = [];

                $departments = $allWorkCenters->where('type', 'department')->sortBy('name');

                foreach ($departments as $dept) {
                    $orderedWorkCenters->push([
                        'node' => $dept,
                        'level' => 0,
                        'icon' => 'feather-grid text-primary',
                    ]);
                    $visitedIds[] = $dept->id;

                    $sections = $allWorkCenters->where('parent_id', $dept->id)->where('type', 'section')->sortBy('name');
                    foreach ($sections as $sec) {
                        $orderedWorkCenters->push([
                            'node' => $sec,
                            'level' => 1,
                            'icon' => 'feather-folder text-warning',
                        ]);
                        $visitedIds[] = $sec->id;

                        $wcs = $allWorkCenters->where('parent_id', $sec->id)->sortBy('name');
                        foreach ($wcs as $wc) {
                            $orderedWorkCenters->push([
                                'node' => $wc,
                                'level' => 2,
                                'icon' => 'feather-sliders text-secondary',
                            ]);
                            $visitedIds[] = $wc->id;
                        }
                    }

                    $directWcs = $allWorkCenters->where('parent_id', $dept->id)->where('type', '!=', 'section')->sortBy('name');
                    foreach ($directWcs as $wc) {
                        if (!in_array($wc->id, $visitedIds)) {
                            $orderedWorkCenters->push([
                                'node' => $wc,
                                'level' => 1,
                                'icon' => 'feather-sliders text-secondary',
                            ]);
                            $visitedIds[] = $wc->id;
                        }
                    }
                }

                $standalone = $allWorkCenters->reject(function ($wc) use ($visitedIds) {
                    return in_array($wc->id, $visitedIds);
                })->sortBy('name');

                foreach ($standalone as $wc) {
                    $level = 0;
                    $icon = 'feather-sliders text-secondary';
                    if ($wc->type === 'section') {
                        $icon = 'feather-folder text-warning';
                    } elseif ($wc->type === 'department') {
                        $icon = 'feather-grid text-primary';
                    }
                    $orderedWorkCenters->push([
                        'node' => $wc,
                        'level' => $level,
                        'icon' => $icon,
                    ]);
                }
            @endphp

            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 3%" class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </th>
                            <th style="width: 12%">Code</th>
                            <th style="width: 25%">Name &amp; Hierarchy</th>
                            <th style="width: 10%">Type</th>
                            <th style="width: 10%">Department</th>
                            <th style="width: 10%">Location</th>
                            <th style="width: 8%" class="text-end">Capacity/Hr</th>
                            <th style="width: 7%" class="text-end">Efficiency</th>
                            <th style="width: 7%" class="text-end">Cost/Hr</th>
                            <th style="width: 3%" class="text-center">Machines</th>
                            <th style="width: 5%">Status</th>
                            <th style="width: 5%" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orderedWorkCenters as $item)
                            @php
                                $wc = $item['node'];
                                $level = $item['level'];
                                $icon = $item['icon'];
                                $padding = $level * 24;

                                $rowClass = '';
                                $spanClass = '';
                                if ($wc->type === 'department') {
                                    $rowClass = 'bg-light-soft fw-bold';
                                    $spanClass = 'fw-bold text-dark';
                                } elseif ($wc->type === 'section') {
                                    $rowClass = 'bg-white';
                                    $spanClass = 'fw-semibold text-dark';
                                } else {
                                    $rowClass = 'table-light-soft text-muted';
                                    $spanClass = 'fw-normal text-dark';
                                }
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input">
                                </td>
                                <td>
                                    <a href="{{ route('production.work-centers.show', $wc->id) }}"
                                        class="fw-bold text-primary hover-primary">
                                        {{ $wc->code }}
                                    </a>
                                </td>
                                <td>
                                    <div style="padding-left: {{ $padding }}px;" class="d-flex align-items-center">
                                        @if($level > 0)
                                            <i class="feather-corner-down-right text-muted me-2" style="font-size: 11px;"></i>
                                        @endif
                                        <i class="{{ $icon }} me-2 fs-13"></i>
                                        <span class="{{ $spanClass }}">{{ $wc->name }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-soft-primary text-primary text-uppercase fs-9 rounded-pill px-2 py-0.5">
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
                                    @if($wc->machines_count > 0)
                                        <a href="{{ route('production.machines.index', ['work_center_id' => $wc->id]) }}"
                                            class="badge bg-soft-info text-info rounded-pill px-2 py-1 fw-bold">
                                            {{ $wc->machines_count }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($wc->isActive())
                                        <span class="erp-badge-active">Active</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger rounded-pill px-2 py-0.5 fs-10">Inactive</span>
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
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
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
                                <td colspan="12" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2 fs-16"></i>No work centers configured in hierarchy.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        @else
            {{-- Flat Table Search/Filters Fallback View --}}
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
                            @php
                                $rowClass = '';
                                $spanClass = '';
                                if ($wc->type === 'department') {
                                    $rowClass = 'bg-light-soft fw-bold';
                                    $spanClass = 'fw-bold text-dark';
                                } elseif ($wc->type === 'section') {
                                    $rowClass = 'bg-white';
                                    $spanClass = 'fw-semibold text-dark';
                                } else {
                                    $rowClass = 'table-light-soft text-muted';
                                    $spanClass = 'fw-normal text-dark';
                                }
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input">
                                </td>
                                <td>
                                    <a href="{{ route('production.work-centers.show', $wc->id) }}"
                                        class="fw-bold text-primary hover-primary">
                                        {{ $wc->code }}
                                    </a>
                                </td>
                                <td>
                                    <span class="{{ $spanClass }}">{{ $wc->name }}</span>
                                </td>
                                <td class="fs-11 text-muted">
                                    @if($wc->parent)
                                        <span>
                                            {{ $wc->parent->parent ? $wc->parent->parent->name . ' › ' : '' }}{{ $wc->parent->name }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
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
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
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
                                    <i class="feather-info me-2 fs-16"></i>No work centers found matching active search filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>

            <div class="mt-4">
                {{ $workCenters->links() }}
            </div>
        @endif
    </div>

    {{-- Import Work Centers Modal --}}
    <x-ui.modal id="importWorkCentersModal" title="Import Work Centers via Excel/CSV" submitText="Import File" :centered="true">
        <form method="POST" action="{{ route('production.import-export.import-preview', 'work-centers') }}" enctype="multipart/form-data" id="importWorkCentersForm">
            @csrf
            <p class="fs-13 text-muted mb-3">Upload an Excel (.xlsx, .xls) or CSV (.csv) file containing Work Center records. Make sure the headers match the column names in the template file.</p>
            <x-ui.odoo-form-ui type="file" name="file" label="Excel/CSV File" required placeholder="Choose file..." />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" form="importWorkCentersForm" class="btn btn-primary">Import File</button>
        </x-slot>
    </x-ui.modal>
@endsection