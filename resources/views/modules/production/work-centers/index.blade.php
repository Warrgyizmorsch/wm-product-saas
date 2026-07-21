@extends('layouts.duralux')

@section('title', __('production.work_centers') . ' | SaaS ERP')
@section('page-title', __('production.work_center_master'))
@section('breadcrumb', __('production.work_centers'))

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
                <i class="feather-plus me-2"></i>{{ __('production.create_work_center') }}
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
        @if (session('warning'))
            <x-ui.toast :auto="true" type="warning" title="{{ session('warning') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Toolbar: Title + Sort + Filter --}}
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('production.work_center_list') }}</h5>
            <div class="d-flex gap-2 ms-auto">
                {{-- Normal Toolbar (Sort, Filter) --}}
                <div id="normal-toolbar" class="d-flex gap-2">
                    {{-- Sort Dropdown --}}
                    <x-ui.sort-dropdown :label="__('production.sort')">
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
                        <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('production.filter_options') }}</h6>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.search_keywords') }}</label>
                                <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('production.wc_name_placeholder')"
                                    value="{{ request('search') }}" />
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.work_center_type') }}</label>
                                <x-ui.odoo-form-ui type="select" name="work_center_type">
                                    <option value="">{{ __('production.all_types') }}</option>
                                    @foreach($workCenterTypes as $value => $label)
                                        <option value="{{ $value }}" {{ request('work_center_type') === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="">{{ __('production.all_statuses') }}</option>
                                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('production.active') }}</option>
                                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ __('production.inactive') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('production.work-centers.index') }}"
                                    class="btn btn-sm btn-light border">{{ __('production.reset') }}</a>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('production.apply_filters') }}</button>
                            </div>
                        </x-ui.filter>
                    </form>
                </div>

                <!-- Bulk Actions Toolbar (initially hidden) -->
                <div id="bulk-actions-toolbar" class="d-flex gap-2 d-none">
                    <x-ui.bulk-actions :label="__('production.selected_actions') . ' (0)'" id="bulk-actions-dropdown" class="bulk-actions-label">
                        <button type="button" class="dropdown-item text-success bulk-action-btn" data-action="activate">
                            <i class="feather-check-circle me-2 text-success"></i> {{ __('production.bulk_activate') }}
                        </button>
                        <button type="button" class="dropdown-item text-warning bulk-action-btn" data-action="deactivate">
                            <i class="feather-slash me-2 text-warning"></i> {{ __('production.bulk_deactivate') }}
                        </button>
                        <div class="dropdown-divider"></div>
                        <button type="button" class="dropdown-item text-danger bulk-action-btn" data-action="delete">
                            <i class="feather-trash-2 text-danger"></i> {{ __('production.bulk_delete') }}
                        </button>
                    </x-ui.bulk-actions>
                </div>
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
                                <input type="checkbox" class="form-check-input check-all-work-centers">
                            </th>
                            <th style="width: 12%">{{ __('production.work_center_code') }}</th>
                            <th style="width: 25%">{{ __('production.name_hierarchy') }}</th>
                            <th style="width: 10%">{{ __('production.work_center_type') }}</th>
                            <th style="width: 10%">{{ __('production.department_name') }}</th>
                            <th style="width: 10%">{{ __('production.physical_location') }}</th>
                            <th style="width: 8%" class="text-end">{{ __('production.capacity_hr') }}</th>
                            <th style="width: 7%" class="text-end">{{ __('production.efficiency') }}</th>
                            <th style="width: 7%" class="text-end">{{ __('production.cost_per_hour') }}</th>
                            <th style="width: 3%" class="text-center">{{ __('production.machines') }}</th>
                            <th style="width: 5%">{{ __('production.status') }}</th>
                            <th style="width: 5%" class="text-end">{{ __('production.actions') }}</th>
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
                                    <input type="checkbox" class="form-check-input work-center-checkbox" value="{{ $wc->id }}" data-can-delete="{{ auth()->user()->can('delete', $wc) ? 'true' : 'false' }}">
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
                                    {{ $wc->capacity_per_hour !== null ? number_format($wc->capacity_per_hour, 2) : __('production.unlimited') }}
                                </td>
                                <td class="text-end text-muted">{{ number_format($wc->efficiency_percentage, 0) }}%</td>
                                <td class="text-end fw-semibold text-dark">{{ format_currency($wc->cost_per_hour) }}</td>
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
                                        <span class="erp-badge-active">{{ __('production.active') }}</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger rounded-pill px-2 py-0.5 fs-10">{{ __('production.inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <x-ui.action-dropdown :viewUrl="route('production.work-centers.show', $wc->id)">
                                        @can('update', $wc)
                                            <li>
                                                <a href="{{ route('production.work-centers.edit', $wc->id) }}" class="dropdown-item">
                                                    <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('production.edit_work_center') }}
                                                </a>
                                            </li>
                                        @endcan
                                        @can('delete', $wc)
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li>
                                                <form action="{{ route('production.work-centers.destroy', $wc->id) }}" method="POST"
                                                    onsubmit="return confirm('{{ __('production.confirm_delete_selected_routings') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('production.delete') }}
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
                                    <i class="feather-info me-2 fs-16"></i>{{ __('production.no_work_centers_configured') }}
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
                                <input type="checkbox" class="form-check-input check-all-work-centers">
                            </th>
                            <th style="width: 9%">{{ __('production.work_center_code') }}</th>
                            <th style="width: 14%">{{ __('production.work_center_name') }}</th>
                            <th style="width: 16%">{{ __('production.hierarchy_path') }}</th>
                            <th style="width: 9%">{{ __('production.work_center_type') }}</th>
                            <th style="width: 11%">{{ __('production.department_name') }}</th>
                            <th style="width: 9%">{{ __('production.physical_location') }}</th>
                            <th style="width: 7%" class="text-end">{{ __('production.capacity_hr') }}</th>
                            <th style="width: 6%" class="text-end">{{ __('production.efficiency') }}</th>
                            <th style="width: 6%" class="text-end">{{ __('production.cost_per_hour') }}</th>
                            <th style="width: 5%" class="text-center">{{ __('production.machines') }}</th>
                            <th style="width: 5%">{{ __('production.status') }}</th>
                            <th style="width: 6%" class="text-end">{{ __('production.actions') }}</th>
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
                                    <input type="checkbox" class="form-check-input work-center-checkbox" value="{{ $wc->id }}" data-can-delete="{{ auth()->user()->can('delete', $wc) ? 'true' : 'false' }}">
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
                                    {{ $wc->capacity_per_hour !== null ? number_format($wc->capacity_per_hour, 2) : __('production.unlimited') }}
                                </td>
                                <td class="text-end text-muted">{{ number_format($wc->efficiency_percentage, 0) }}%</td>
                                <td class="text-end fw-semibold text-dark">{{ format_currency($wc->cost_per_hour) }}</td>
                                <td class="text-center">
                                    <a href="{{ route('production.machines.index', ['work_center_id' => $wc->id]) }}"
                                        class="badge bg-soft-info text-info rounded-pill px-2 py-1 fw-bold">
                                        {{ $wc->machines_count }}
                                    </a>
                                </td>
                                <td>
                                    @if ($wc->isActive())
                                        <span class="erp-badge-active">{{ __('production.active') }}</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger rounded-pill px-2 py-1">{{ __('production.inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <x-ui.action-dropdown :viewUrl="route('production.work-centers.show', $wc->id)">
                                        @can('update', $wc)
                                            <li>
                                                <a href="{{ route('production.work-centers.edit', $wc->id) }}" class="dropdown-item">
                                                    <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('production.edit_work_center') }}
                                                </a>
                                            </li>
                                        @endcan
                                        @can('delete', $wc)
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li>
                                                <form action="{{ route('production.work-centers.destroy', $wc->id) }}" method="POST"
                                                    onsubmit="return confirm('{{ __('production.confirm_delete_selected_routings') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('production.delete') }}
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
                                    <i class="feather-info me-2 fs-16"></i>{{ __('production.no_work_centers_matching') }}
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
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
            <button type="submit" form="importWorkCentersForm" class="btn btn-primary">Import File</button>
        </x-slot>
    </x-ui.modal>

    {{-- Bulk Actions Hidden Forms --}}
    <form id="bulk-action-form" action="{{ route('production.work-centers.bulk-action') }}" method="POST" style="display: none;">
        @csrf
        <div id="bulk-action-inputs-container"></div>
    </form>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const headerCheckboxes = document.querySelectorAll('.check-all-work-centers');
                const rowCheckboxes = document.querySelectorAll('.work-center-checkbox');
                const normalToolbar = document.getElementById('normal-toolbar');
                const bulkActionsToolbar = document.getElementById('bulk-actions-toolbar');
                const bulkActionsLabel = document.querySelector('.bulk-actions-label');

                function updateToolbarVisibility() {
                    const selectedCheckboxes = document.querySelectorAll('.work-center-checkbox:checked');
                    const selectedCount = selectedCheckboxes.length;

                    if (selectedCount > 0) {
                        normalToolbar.classList.add('d-none');
                        bulkActionsToolbar.classList.remove('d-none');
                        if (bulkActionsLabel) {
                            bulkActionsLabel.innerHTML = `<span class="fw-bold text-dark"><i class="feather-check-square me-1"></i> ${@js(__('production.selected_actions'))} (${selectedCount})</span>`;
                        }

                        // Dynamically hide delete bulk action if any selected work center cannot be deleted
                        const cannotDeleteAny = Array.from(selectedCheckboxes).some(cb => cb.getAttribute('data-can-delete') === 'false');
                        const deleteBtn = document.querySelector('.bulk-action-btn[data-action="delete"]');
                        if (deleteBtn) {
                            const deleteDivider = deleteBtn.previousElementSibling;
                            if (cannotDeleteAny) {
                                deleteBtn.classList.add('d-none');
                                if (deleteDivider && deleteDivider.classList.contains('dropdown-divider')) {
                                    deleteDivider.classList.add('d-none');
                                }
                            } else {
                                deleteBtn.classList.remove('d-none');
                                if (deleteDivider && deleteDivider.classList.contains('dropdown-divider')) {
                                    deleteDivider.classList.remove('d-none');
                                }
                            }
                        }
                    } else {
                        normalToolbar.classList.remove('d-none');
                        bulkActionsToolbar.classList.add('d-none');
                    }
                }

                headerCheckboxes.forEach(headerCheckbox => {
                    headerCheckbox.addEventListener('change', function () {
                        rowCheckboxes.forEach(cb => {
                            cb.checked = headerCheckbox.checked;
                        });
                        headerCheckboxes.forEach(other => {
                            if (other !== headerCheckbox) {
                                other.checked = headerCheckbox.checked;
                            }
                        });
                        updateToolbarVisibility();
                    });
                });

                rowCheckboxes.forEach(cb => {
                    cb.addEventListener('change', function () {
                        const allChecked = Array.from(rowCheckboxes).every(r => r.checked);
                        const someChecked = Array.from(rowCheckboxes).some(r => r.checked);
                        headerCheckboxes.forEach(headerCheckbox => {
                            headerCheckbox.checked = allChecked;
                            headerCheckbox.indeterminate = someChecked && !allChecked;
                        });
                        updateToolbarVisibility();
                    });
                });

                document.querySelectorAll('.bulk-action-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const action = this.getAttribute('data-action');
                        const selectedCheckboxes = document.querySelectorAll('.work-center-checkbox:checked');
                        if (selectedCheckboxes.length === 0) {
                            confirmAction(@js(__('production.no_work_centers_selected')), null, {
                                title: @js(__('production.no_selection') ?? 'No Selection'),
                                confirmButtonText: @js(__('production.ok') ?? 'OK'),
                                confirmButtonClass: 'btn-primary'
                            });
                            return;
                        }

                        let confirmMessage = '';
                        let title = 'Confirm Action';
                        let variant = 'primary';
                        let confirmText = 'Confirm';

                        switch (action) {
                            case 'delete':
                                title = @js(__('production.bulk_delete'));
                                confirmMessage = @js(__('production.confirm_bulk_delete_wcs'));
                                variant = 'danger';
                                confirmText = @js(__('production.bulk_delete'));
                                break;
                            case 'activate':
                                title = @js(__('production.bulk_activate'));
                                confirmMessage = @js(__('production.confirm_bulk_activate_wcs'));
                                variant = 'success';
                                confirmText = @js(__('production.bulk_activate'));
                                break;
                            case 'deactivate':
                                title = @js(__('production.bulk_deactivate'));
                                confirmMessage = @js(__('production.confirm_bulk_deactivate_wcs'));
                                variant = 'warning';
                                confirmText = @js(__('production.bulk_deactivate'));
                                break;
                        }

                        confirmAction(
                            confirmMessage,
                            function() {
                                const form = document.getElementById('bulk-action-form');
                                const container = document.getElementById('bulk-action-inputs-container');
                                container.innerHTML = '';

                                // Action input
                                const actionInput = document.createElement('input');
                                actionInput.type = 'hidden';
                                actionInput.name = 'action';
                                actionInput.value = action;
                                container.appendChild(actionInput);

                                // Selected IDs
                                selectedCheckboxes.forEach(cb => {
                                    const hiddenInput = document.createElement('input');
                                    hiddenInput.type = 'hidden';
                                    hiddenInput.name = 'ids[]';
                                    hiddenInput.value = cb.value;
                                    container.appendChild(hiddenInput);
                                });

                                form.submit();
                            },
                            {
                                title: title,
                                confirmButtonText: confirmText,
                                confirmButtonClass: 'btn-' + variant
                            }
                        );
                    });
                });
            });
        </script>
    @endpush
@endsection