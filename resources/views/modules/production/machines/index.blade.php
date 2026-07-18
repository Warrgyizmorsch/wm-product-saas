@extends('layouts.duralux')

@section('title', __('production.machines') . ' | SaaS ERP')
@section('page-title', __('production.machine_master'))
@section('breadcrumb', __('production.machines'))

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
        <x-ui.import-export-dropdown type="machines" importModalTarget="#importMachinesModal" />
        @can('create', App\Domains\Production\Models\Machine::class)
            <a href="{{ route('production.machines.create') }}" class="btn btn-primary">
                <i class="feather-plus me-2"></i>{{ __('production.create_machine') }}
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
            <h5 class="fw-bold text-dark mb-0">{{ __('production.machine_list') }}</h5>
            <div class="d-flex gap-2 ms-auto">
                {{-- Normal Toolbar --}}
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
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'installation_date', 'sort_order' => 'desc']) }}"
                           class="dropdown-item {{ $sortBy === 'installation_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>Installed Date (Newest)</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'installation_date', 'sort_order' => 'asc']) }}"
                           class="dropdown-item {{ $sortBy === 'installation_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>Installed Date (Oldest)</span>
                        </a>
                    </x-ui.sort-dropdown>

                    {{-- Filter Overlay --}}
                    <form method="GET" action="{{ route('production.machines.index') }}" class="d-inline">
                        <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('production.filter_options') }}</h6>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.search_keywords') }}</label>
                                <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('production.search_machine_placeholder')" value="{{ request('search') }}" />
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.work_center') }}</label>
                                <x-ui.odoo-form-ui type="select" name="work_center_id">
                                    <option value="">{{ __('production.all_work_centers') }}</option>
                                    @foreach($workCenters as $wc)
                                        <option value="{{ $wc->id }}" {{ request('work_center_id') == $wc->id ? 'selected' : '' }}>
                                            {{ $wc->name }}
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="">{{ __('production.all_statuses') }}</option>
                                    @foreach($statuses as $value => $label)
                                        <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                                            {{ __('production.' . $value) ?? $label }}
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('production.machines.index') }}" class="btn btn-sm btn-light border">{{ __('production.reset') }}</a>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('production.apply_filters') }}</button>
                            </div>
                        </x-ui.filter>
                    </form>
                </div>

                <!-- Bulk Actions Toolbar (initially hidden) -->
                <div id="bulk-actions-toolbar" class="d-flex gap-2 d-none">
                    <x-ui.bulk-actions :label="__('production.selected_actions') . ' (0)'" id="bulk-actions-dropdown" class="bulk-actions-label">
                        <button type="button" class="dropdown-item text-success bulk-action-btn" data-action="activate">
                            <i class="feather-check-circle me-2 text-success"></i> {{ __('production.bulk_activate_machines') }}
                        </button>
                        <button type="button" class="dropdown-item text-warning bulk-action-btn" data-action="deactivate">
                            <i class="feather-slash me-2 text-warning"></i> {{ __('production.bulk_deactivate_machines') }}
                        </button>
                        <button type="button" class="dropdown-item text-info bulk-action-btn" data-action="maintenance">
                            <i class="feather-tool me-2 text-info"></i> {{ __('production.bulk_maintenance_machines') }}
                        </button>
                        <div class="dropdown-divider"></div>
                        <button type="button" class="dropdown-item text-danger bulk-action-btn" data-action="delete">
                            <i class="feather-trash-2 text-danger"></i> {{ __('production.bulk_delete_machines') }}
                        </button>
                    </x-ui.bulk-actions>
                </div>
            </div>
        </div>

        {{-- Machines Table --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input check-all-machines">
                        </th>
                        <th style="width: 9%">{{ __('production.machine_code') }}</th>
                        <th style="width: 14%">{{ __('production.machine_name') }}</th>
                        <th style="width: 18%">{{ __('production.work_center') }}</th>
                        <th style="width: 9%">{{ __('production.machine_type') }}</th>
                        <th style="width: 10%">{{ __('production.manufacturer') }}</th>
                        <th style="width: 10%">{{ __('production.model_number') }}</th>
                        <th style="width: 8%" class="text-end">{{ __('production.capacity_hr') }}</th>
                        <th style="width: 7%">{{ __('production.status') }}</th>
                        <th style="width: 9%">{{ __('production.installation_date') }}</th>
                        <th style="width: 5%" class="text-end">{{ __('production.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($machines as $machine)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input machine-checkbox" value="{{ $machine->id }}" data-can-delete="{{ auth()->user()->can('delete', $machine) ? 'true' : 'false' }}">
                            </td>
                            <td>
                                <span class="fw-bold text-dark">{{ $machine->code }}</span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $machine->name }}</span>
                            </td>
                            <td>
                                @if ($machine->workCenter)
                                    <div class="d-flex flex-column">
                                        <a href="{{ route('production.work-centers.show', $machine->work_center_id) }}" class="fw-semibold text-primary hover-primary">
                                            {{ $machine->workCenter->name }}
                                        </a>
                                        <small class="text-muted font-monospace fs-10">{{ $machine->workCenter->code }}</small>
                                    </div>
                                @else
                                    <span class="text-muted">Orphaned</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $machine->machine_type ?? '—' }}</td>
                            <td class="text-muted">{{ $machine->manufacturer ?? '—' }}</td>
                            <td class="text-muted">{{ $machine->model_number ?? '—' }}</td>
                            <td class="text-end fw-semibold">
                                {{ $machine->capacity !== null ? number_format($machine->capacity, 2) : 'Flexible' }}
                            </td>
                            <td>
                                @if ($machine->isActive())
                                    <span class="erp-badge-active">{{ __('production.active') }}</span>
                                @elseif ($machine->isUnderMaintenance())
                                    <span class="erp-badge-pending">{{ __('production.maint') }}</span>
                                @elseif ($machine->isDecommissioned())
                                    <span class="badge bg-soft-dark text-dark rounded-pill px-2 py-1">{{ __('production.decom') }}</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger rounded-pill px-2 py-1">{{ __('production.inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $machine->installation_date ? $machine->installation_date->format('Y-m-d') : '—' }}</td>
                            <td class="text-end">
                                <x-ui.action-dropdown>
                                    @can('update', $machine)
                                        <li>
                                            <a href="{{ route('production.machines.edit', $machine->id) }}" class="dropdown-item">
                                                <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('production.edit_machine') }}
                                            </a>
                                        </li>
                                    @endcan
                                    @can('delete', $machine)
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('production.machines.destroy', $machine->id) }}" method="POST"
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
                            <td colspan="11" class="text-center py-4 text-muted">
                                <i class="feather-info me-2 fs-16"></i>{{ __('production.no_machines_matching') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="mt-4">
            {{ $machines->links() }}
        </div>
    </div>

    {{-- Import Machines Modal --}}
    <x-ui.modal id="importMachinesModal" title="Import Machines via Excel/CSV" submitText="Import File" :centered="true">
        <form method="POST" action="{{ route('production.import-export.import-preview', 'machines') }}" enctype="multipart/form-data" id="importMachinesForm">
            @csrf
            <p class="fs-13 text-muted mb-3">Upload an Excel (.xlsx, .xls) or CSV (.csv) file containing Machine records. Make sure the headers match the column names in the template file.</p>
            <x-ui.odoo-form-ui type="file" name="file" label="Excel/CSV File" required placeholder="Choose file..." />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
            <button type="submit" form="importMachinesForm" class="btn btn-primary">Import File</button>
        </x-slot>
    </x-ui.modal>

    {{-- Bulk Actions Hidden Forms --}}
    <form id="bulk-action-form" action="{{ route('production.machines.bulk-action') }}" method="POST" style="display: none;">
        @csrf
        <div id="bulk-action-inputs-container"></div>
    </form>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const headerCheckboxes = document.querySelectorAll('.check-all-machines');
                const rowCheckboxes = document.querySelectorAll('.machine-checkbox');
                const normalToolbar = document.getElementById('normal-toolbar');
                const bulkActionsToolbar = document.getElementById('bulk-actions-toolbar');
                const bulkActionsLabel = document.querySelector('.bulk-actions-label');

                function updateToolbarVisibility() {
                    const selectedCheckboxes = document.querySelectorAll('.machine-checkbox:checked');
                    const selectedCount = selectedCheckboxes.length;

                    if (selectedCount > 0) {
                        normalToolbar.classList.add('d-none');
                        bulkActionsToolbar.classList.remove('d-none');
                        if (bulkActionsLabel) {
                            bulkActionsLabel.innerHTML = `<span class="fw-bold text-dark"><i class="feather-check-square me-1"></i> ${@js(__('production.selected_actions'))} (${selectedCount})</span>`;
                        }

                        // Dynamically hide delete bulk action if any selected machine cannot be deleted
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
                        const selectedCheckboxes = document.querySelectorAll('.machine-checkbox:checked');
                        if (selectedCheckboxes.length === 0) {
                            confirmAction(@js(__('production.no_machines_selected')), null, {
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
                                title = @js(__('production.bulk_delete_machines'));
                                confirmMessage = @js(__('production.confirm_bulk_delete_machines'));
                                variant = 'danger';
                                confirmText = @js(__('production.delete'));
                                break;
                            case 'activate':
                                title = @js(__('production.bulk_activate_machines'));
                                confirmMessage = @js(__('production.confirm_bulk_activate_machines'));
                                variant = 'success';
                                confirmText = @js(__('production.bulk_activate_machines'));
                                break;
                            case 'deactivate':
                                title = @js(__('production.bulk_deactivate_machines'));
                                confirmMessage = @js(__('production.confirm_bulk_deactivate_machines'));
                                variant = 'warning';
                                confirmText = @js(__('production.bulk_deactivate_machines'));
                                break;
                            case 'maintenance':
                                title = @js(__('production.bulk_maintenance_machines'));
                                confirmMessage = @js(__('production.confirm_bulk_maintenance_machines'));
                                variant = 'info';
                                confirmText = @js(__('production.bulk_maintenance_machines'));
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
