@extends('layouts.duralux')

@section('title', __('production.routing_management') . ' | SaaS ERP')
@section('page-title', __('production.routing_master_data'))
@section('breadcrumb', __('production.routings'))

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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const headerCheckbox = document.getElementById('check-all-routings');
            const rowCheckboxes = document.querySelectorAll('.routing-checkbox');
            const normalToolbar = document.getElementById('normal-toolbar');
            const bulkActionsToolbar = document.getElementById('bulk-actions-toolbar');
            const bulkActionsLabel = document.querySelector('.bulk-actions-label');

            function updateToolbarVisibility() {
                const selectedCheckboxes = document.querySelectorAll('.routing-checkbox:checked');
                const selectedCount = selectedCheckboxes.length;

                if (selectedCount > 0) {
                    normalToolbar.classList.add('d-none');
                    bulkActionsToolbar.classList.remove('d-none');
                    if (bulkActionsLabel) {
                        bulkActionsLabel.textContent = `${@js(__('production.selected_actions'))} (${selectedCount})`;
                    }
                } else {
                    normalToolbar.classList.remove('d-none');
                    bulkActionsToolbar.classList.add('d-none');
                    if (bulkActionsLabel) {
                        bulkActionsLabel.textContent = `${@js(__('production.selected_actions'))} (0)`;
                    }
                }
            }

            if (headerCheckbox) {
                headerCheckbox.addEventListener('change', function () {
                    rowCheckboxes.forEach(cb => {
                        cb.checked = headerCheckbox.checked;
                    });
                    updateToolbarVisibility();
                });
            }

            rowCheckboxes.forEach(cb => {
                cb.addEventListener('change', function () {
                    const allChecked = Array.from(rowCheckboxes).every(r => r.checked);
                    const someChecked = Array.from(rowCheckboxes).some(r => r.checked);
                    if (headerCheckbox) {
                        headerCheckbox.checked = allChecked;
                        headerCheckbox.indeterminate = someChecked && !allChecked;
                    }
                    updateToolbarVisibility();
                });
            });

            document.querySelectorAll('.bulk-action-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const action = this.getAttribute('data-action');
                    const selectedCheckboxes = document.querySelectorAll('.routing-checkbox:checked');
                    if (selectedCheckboxes.length === 0) {
                        confirmAction(@js(__('production.select_one_routing_first') ?? 'Please select at least one routing first.'), null, {
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
                            title = @js(__('production.delete_selected_routings'));
                            confirmMessage = @js(__('production.confirm_delete_selected_routings')).replace(':count', selectedCheckboxes.length);
                            variant = 'danger';
                            confirmText = @js(__('production.delete'));
                            break;
                        case 'submit_approval':
                            title = @js(__('production.submit_selected_routings_approval'));
                            confirmMessage = @js(__('production.confirm_submit_selected_routings')).replace(':count', selectedCheckboxes.length);
                            variant = 'primary';
                            confirmText = @js(__('production.submit_approval'));
                            break;
                        case 'approve':
                            title = @js(__('production.approve_selected_routings'));
                            confirmMessage = @js(__('production.confirm_approve_selected_routings')).replace(':count', selectedCheckboxes.length);
                            variant = 'success';
                            confirmText = @js(__('production.approve_selected_routings'));
                            break;
                        case 'cancel':
                            title = @js(__('production.cancel_selected_routings'));
                            confirmMessage = @js(__('production.confirm_cancel_selected_routings')).replace(':count', selectedCheckboxes.length);
                            variant = 'warning';
                            confirmText = @js(__('production.cancel_routing'));
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

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.import-export-dropdown type="routings" importModalTarget="#importRoutingsModal" />
        @can('create', App\Domains\Production\Models\Routing::class)
            <a href="{{ route('production.routing.create') }}" class="btn btn-primary">
                <i class="feather-plus me-2"></i>{{ __('production.create_new_routing') }}
            </a>
        @endcan
    </div>
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
        @if (session('warning'))
            <x-ui.toast :auto="true" type="warning" title="{{ session('warning') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Toolbar: Title + Sort + Filter --}}
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('production.routing_list') }}</h5>
            <div class="d-flex gap-2 ms-auto">
                {{-- Normal Toolbar (Sort, Filter) --}}
                <div id="normal-toolbar" class="d-flex gap-2">
                    {{-- Sort Dropdown --}}
                    <x-ui.sort-dropdown :label="__('production.sort')">
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'routing_number', 'sort_order' => 'asc']) }}"
                           class="dropdown-item {{ $sortBy === 'routing_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('production.routing_number_asc') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'routing_number', 'sort_order' => 'desc']) }}"
                           class="dropdown-item {{ $sortBy === 'routing_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('production.routing_number_desc') }}</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}"
                           class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('production.name_az') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'desc']) }}"
                           class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('production.name_za') }}</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'version', 'sort_order' => 'asc']) }}"
                           class="dropdown-item {{ $sortBy === 'version' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('production.version_asc') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'version', 'sort_order' => 'desc']) }}"
                           class="dropdown-item {{ $sortBy === 'version' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('production.version_desc') }}</span>
                        </a>
                    </x-ui.sort-dropdown>

                    {{-- Filter Overlay --}}
                    <form method="GET" action="{{ route('production.routing.index') }}" class="d-inline">
                        <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('production.filter_options') }}</h6>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.search_keywords') }}</label>
                                <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('production.search_routing_placeholder')" value="{{ request('search') }}" />
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.finished_product') }}</label>
                                <x-ui.odoo-form-ui type="select" name="product_id">
                                    <option value="">{{ __('production.all_products') }}</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="">{{ __('production.all_statuses') }}</option>
                                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>{{ __('production.draft') }}</option>
                                    <option value="pending_approval" {{ request('status') === 'pending_approval' ? 'selected' : '' }}>{{ __('production.pending_approval') }}</option>
                                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('production.active') }}</option>
                                    <option value="historical" {{ request('status') === 'historical' ? 'selected' : '' }}>{{ __('production.historical') }}</option>
                                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('production.cancelled') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('production.routing.index') }}" class="btn btn-sm btn-light border">{{ __('production.reset') }}</a>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('production.apply_filters') }}</button>
                            </div>
                        </x-ui.filter>
                    </form>
                </div>

                <!-- Bulk Actions Toolbar (initially hidden) -->
                <div id="bulk-actions-toolbar" class="d-flex gap-2 d-none">
                    <x-ui.bulk-actions :label="__('production.selected_actions') . ' (0)'" id="bulk-actions-dropdown">
                        <button type="button" class="dropdown-item text-muted bulk-action-btn" data-action="submit_approval">
                            <i class="feather-send me-2 text-muted"></i> {{ __('production.submit_approval') }}
                        </button>
                        <button type="button" class="dropdown-item text-success bulk-action-btn" data-action="approve">
                            <i class="feather-check-circle me-2 text-success"></i> {{ __('production.approve_selected_routings') }}
                        </button>
                        <button type="button" class="dropdown-item text-warning bulk-action-btn" data-action="cancel">
                            <i class="feather-slash me-2 text-warning"></i> {{ __('production.cancel_selected_routings') }}
                        </button>
                        <div class="dropdown-divider"></div>
                        <button type="button" class="dropdown-item text-danger bulk-action-btn" data-action="delete">
                            <i class="feather-trash-2 text-danger"></i> {{ __('production.delete_selected_routings') }}
                        </button>
                    </x-ui.bulk-actions>
                </div>
            </div>
        </div>

        {{-- Routings Table --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" id="check-all-routings" class="form-check-input">
                        </th>
                        <th style="width: 12%">{{ __('production.routing_number') }}</th>
                        <th style="width: 18%">{{ __('production.routing_name') }}</th>
                        <th style="width: 20%">{{ __('production.product_to_manufacture') }}</th>
                        <th style="width: 7%">{{ __('production.version') }}</th>
                        <th style="width: 6%" class="text-center">{{ __('production.operations_count') }}</th>
                        <th style="width: 8%">{{ __('production.effective_from') }}</th>
                        <th style="width: 8%">{{ __('production.effective_to') }}</th>
                        <th style="width: 6%">{{ __('production.routing_type') }}</th>
                        <th style="width: 7%">{{ __('production.status') }}</th>
                        <th style="width: 5%" class="text-end">{{ __('production.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($routings as $routing)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input routing-checkbox" value="{{ $routing->id }}">
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
                                    <span class="text-muted">{{ __('production.no_product') ?? 'No Product' }}</span>
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
                            <td class="text-muted">{{ $routing->effective_from ? $routing->effective_from->format('Y-m-d') : __('production.immediate') ?? 'Immediate' }}</td>
                            <td class="text-muted">{{ $routing->effective_to ? $routing->effective_to->format('Y-m-d') : __('production.indefinite') ?? 'Indefinite' }}</td>
                            <td>
                                @if ($routing->is_default)
                                    <span class="badge bg-soft-success text-success px-2 py-1 rounded-pill fs-10">{{ __('production.primary') }}</span>
                                @else
                                    <span class="badge bg-soft-warning text-warning px-2 py-1 rounded-pill fs-10">{{ __('production.alternative') }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($routing->isDraft())
                                    <span class="erp-badge-draft">{{ __('production.draft') }}</span>
                                @elseif ($routing->isPendingApproval())
                                    <span class="erp-badge-pending">{{ __('production.pending') }}</span>
                                @elseif ($routing->isActive())
                                    <span class="erp-badge-active">{{ __('production.active') }}</span>
                                @elseif ($routing->isHistorical())
                                    <span class="badge bg-soft-info text-info rounded-pill px-2 py-1">{{ __('production.historical') }}</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger rounded-pill px-2 py-1">{{ __('production.cancelled') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <x-ui.action-dropdown :viewUrl="route('production.routing.show', $routing->id)">
                                    {{-- Edit / Submit (draft only) --}}
                                    @if($routing->isDraft())
                                        @can('update', $routing)
                                            <li>
                                                <a href="{{ route('production.routing.edit', $routing->id) }}" class="dropdown-item">
                                                    <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('production.edit_draft') }}
                                                </a>
                                            </li>
                                        @endcan
                                        @can('submit', $routing)
                                            <li>
                                                <form method="POST" action="{{ route('production.routing.submit', $routing->id) }}">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="feather-send me-2 text-muted fs-12"></i>{{ __('production.submit_approval') }}
                                                    </button>
                                                </form>
                                            </li>
                                        @endcan
                                    @endif

                                    {{-- Approve / Reject --}}
                                    @if($routing->isPendingApproval())
                                        @can('approve', $routing)
                                            <li>
                                                <form method="POST" action="{{ route('production.routing.approve', $routing->id) }}">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-success">
                                                        <i class="feather-check-circle me-2 text-success fs-12"></i>{{ __('production.approve_selected_routings') }}
                                                    </button>
                                                </form>
                                            </li>
                                        @endcan
                                        @can('reject', $routing)
                                            <li>
                                                <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $routing->id }}">
                                                    <i class="feather-x-circle me-2 text-danger fs-12"></i>{{ __('production.reject_routing') }}
                                                </button>
                                            </li>
                                        @endcan
                                    @endif

                                    {{-- Cancel --}}
                                    @if(!$routing->isCancelled() && !$routing->isHistorical())
                                        @can('cancel', $routing)
                                            <li>
                                                <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#cancelModal{{ $routing->id }}">
                                                    <i class="feather-slash me-2 text-danger fs-12"></i>{{ __('production.cancel_routing') }}
                                                </button>
                                            </li>
                                        @endcan
                                    @endif

                                    {{-- Duplicate --}}
                                    @can('duplicate', $routing)
                                        <li>
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#duplicateModal{{ $routing->id }}">
                                                <i class="feather-copy me-2 text-muted fs-12"></i>{{ __('production.duplicate_version') }}
                                            </button>
                                        </li>
                                    @endcan

                                    {{-- Delete (draft only) --}}
                                    @if($routing->isDraft())
                                        @can('delete', $routing)
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form id="delete-form-{{ $routing->id }}" method="POST" action="{{ route('production.routing.destroy', $routing->id) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger" onclick="confirmAction(
                                                        '{{ __('production.confirm_delete_routing') }}',
                                                        function() {
                                                            document.getElementById('delete-form-{{ $routing->id }}').submit();
                                                        },
                                                        {
                                                            title: '{{ __('production.confirm_delete_routing_title') }}',
                                                            confirmButtonText: '{{ __('production.delete') }}',
                                                            confirmButtonClass: 'btn-danger'
                                                        }
                                                    );">
                                                        <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('production.delete_permanent') }}
                                                    </button>
                                                </form>
                                            </li>
                                        @endcan
                                    @endif
                                </x-ui.action-dropdown>

                                <!-- Duplicate Modal -->
                                <x-ui.modal id="duplicateModal{{ $routing->id }}" title="{{ __('production.duplicate_routing_version') }}" submit-text="{{ __('production.create_version') }}" class="text-start">
                                    <form method="POST" action="{{ route('production.routing.duplicate', $routing->id) }}" id="dupForm{{ $routing->id }}">
                                        @csrf
                                        <p class="fs-13 text-muted">{{ __('production.duplicate_routing_modal_body') }}</p>
                                        <x-ui.input :label="__('production.new_version_name')" name="new_version" placeholder="e.g. 1.1.0 or 2.0.0" required />
                                    </form>
                                    <x-slot name="footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                                        <button type="submit" class="btn btn-primary" onclick="document.getElementById('dupForm{{ $routing->id }}').submit();">{{ __('production.duplicate_version') }}</button>
                                    </x-slot>
                                </x-ui.modal>

                                <!-- Reject Modal -->
                                <x-ui.modal id="rejectModal{{ $routing->id }}" title="{{ __('production.reject_routing_version') }}" submit-text="{{ __('production.reject_version') }}" class="text-start">
                                    <form method="POST" action="{{ route('production.routing.reject', $routing->id) }}" id="rejectForm{{ $routing->id }}">
                                        @csrf
                                        <p class="fs-13 text-muted">{{ __('production.reject_routing_modal_body') }}</p>
                                        <x-ui.input :label="__('production.rejection_reason')" name="comments" placeholder="e.g. Operation sequence correction required" required />
                                    </form>
                                    <x-slot name="footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                                        <button type="submit" class="btn btn-danger" onclick="document.getElementById('rejectForm{{ $routing->id }}').submit();">{{ __('production.reject_routing') }}</button>
                                    </x-slot>
                                </x-ui.modal>

                                <!-- Cancel Modal -->
                                <x-ui.modal id="cancelModal{{ $routing->id }}" title="{{ __('production.cancel_routing_version') }}" submit-text="{{ __('production.cancel_version') }}" class="text-start">
                                    <form method="POST" action="{{ route('production.routing.cancel', $routing->id) }}" id="cancelForm{{ $routing->id }}">
                                        @csrf
                                        <p class="fs-13 text-muted">{{ __('production.cancel_routing_modal_body') }}</p>
                                        <x-ui.input :label="__('production.cancellation_reason')" name="comments" placeholder="e.g. Process design obsolete" required />
                                    </form>
                                    <x-slot name="footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                                        <button type="submit" class="btn btn-danger" onclick="document.getElementById('cancelForm{{ $routing->id }}').submit();">{{ __('production.cancel_routing') }}</button>
                                    </x-slot>
                                </x-ui.modal>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted">
                                <i class="feather-info me-2 fs-16"></i>{{ __('production.no_routings_found') }}
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

    {{-- Import Routings Modal --}}
    <x-ui.modal id="importRoutingsModal" title="{{ __('production.import_routings_title') }}" submitText="{{ __('production.import_file') }}" :centered="true">
        <form method="POST" action="{{ route('production.import-export.import-preview', 'routings') }}" enctype="multipart/form-data" id="importRoutingsForm">
            @csrf
            <p class="fs-13 text-muted mb-3">{{ __('production.import_routings_body') }}</p>
            <x-ui.odoo-form-ui type="file" name="file" :label="__('production.file_label')" required :placeholder="__('production.choose_file')" />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
            <button type="submit" form="importRoutingsForm" class="btn btn-primary">{{ __('production.import_file') }}</button>
        </x-slot>
    </x-ui.modal>

    {{-- Bulk Actions Hidden Forms --}}
    <form id="bulk-action-form" action="{{ route('production.routing.bulk-action') }}" method="POST" style="display: none;">
        @csrf
        <div id="bulk-action-inputs-container"></div>
    </form>
@endsection
