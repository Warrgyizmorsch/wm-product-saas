@extends('layouts.duralux')

@section('title', __('production.bom_index_title') . ' | SaaS ERP')
@section('page-title', __('production.bom_management'))
@section('breadcrumb', __('production.bom_management'))


@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const headerCheckbox = document.getElementById('check-all-boms');
            const rowCheckboxes = document.querySelectorAll('.bom-checkbox');
            const normalToolbar = document.getElementById('normal-toolbar');
            const bulkActionsToolbar = document.getElementById('bulk-actions-toolbar');
            const bulkActionsLabel = document.querySelector('.bulk-actions-label');

            function updateToolbarVisibility() {
                const selectedCheckboxes = document.querySelectorAll('.bom-checkbox:checked');
                const selectedCount = selectedCheckboxes.length;

                if (selectedCount > 0) {
                    normalToolbar.classList.add('d-none');
                    bulkActionsToolbar.classList.remove('d-none');
                    if (bulkActionsLabel) {
                        bulkActionsLabel.textContent = `Selected Actions (${selectedCount})`;
                    }
                } else {
                    normalToolbar.classList.remove('d-none');
                    bulkActionsToolbar.classList.add('d-none');
                    if (bulkActionsLabel) {
                        bulkActionsLabel.textContent = 'Selected Actions (0)';
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
                    const selectedCheckboxes = document.querySelectorAll('.bom-checkbox:checked');
                    if (selectedCheckboxes.length === 0) {
                        confirmAction(@js(__('production.select_one_first') ?? 'Please select at least one BOM first.'), null, {
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
                            title = @js(__('production.delete_selected'));
                            confirmMessage = @js(__('production.confirm_delete_selected')).replace(':count', selectedCheckboxes.length);
                            variant = 'danger';
                            confirmText = @js(__('production.delete'));
                            break;
                        case 'submit_approval':
                            title = @js(__('production.submit_selected_approval'));
                            confirmMessage = @js(__('production.confirm_submit_selected')).replace(':count', selectedCheckboxes.length);
                            variant = 'primary';
                            confirmText = @js(__('production.submit_approval'));
                            break;
                        case 'approve':
                            title = @js(__('production.approve_selected'));
                            confirmMessage = @js(__('production.confirm_approve_selected')).replace(':count', selectedCheckboxes.length);
                            variant = 'success';
                            confirmText = @js(__('production.approve_bom'));
                            break;
                        case 'cancel':
                            title = @js(__('production.cancel_selected'));
                            confirmMessage = @js(__('production.confirm_cancel_selected')).replace(':count', selectedCheckboxes.length);
                            variant = 'warning';
                            confirmText = @js(__('production.cancel_bom'));
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
        <x-ui.import-export-dropdown type="boms" importModalTarget="#importBomsModal" />
        <a href="{{ route('production.boms.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>{{ __('production.create_new_bom') }}
        </a>
    </div>
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
            <h5 class="fw-bold text-dark mb-0">{{ __('production.bill_of_materials') }}</h5>
            <div class="d-flex gap-2 ms-auto">
                <!-- Normal Toolbar (Sort, Filter) -->
                <div id="normal-toolbar" class="d-flex gap-2">
                    <!-- Custom Sort Component -->
                    <x-ui.sort-dropdown :label="__('production.sort')">
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'bom_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'bom_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('production.bom_number_asc') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'bom_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'bom_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('production.bom_number_desc') }}</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'bom_name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'bom_name' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('production.bom_name_az') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'bom_name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'bom_name' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('production.bom_name_za') }}</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'base_quantity', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'base_quantity' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('production.qty_low_high') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'base_quantity', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'base_quantity' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('production.qty_high_low') }}</span>
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Custom Filter Component -->
                    <form method="GET" action="{{ route('production.boms.index') }}" class="d-inline">
                        <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('production.filter_options') }}</h6>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.search_keywords') }}</label>
                                <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('production.search_bom_placeholder')" value="{{ request('search') }}" />
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.item_to_produce') }}</label>
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
                                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>{{ __('production.approved_active') }}</option>
                                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ __('production.inactive') }}</option>
                                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('production.cancelled') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('production.boms.index') }}" class="btn btn-sm btn-light border">{{ __('production.reset') }}</a>
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
                            <i class="feather-check-circle me-2 text-success"></i> {{ __('production.approve_selected') }}
                        </button>
                        <button type="button" class="dropdown-item text-warning bulk-action-btn" data-action="cancel">
                            <i class="feather-slash me-2 text-warning"></i> {{ __('production.cancel_selected') }}
                        </button>
                        <div class="dropdown-divider"></div>
                        <button type="button" class="dropdown-item text-danger bulk-action-btn" data-action="delete">
                            <i class="feather-trash-2 text-danger"></i> {{ __('production.delete_selected') }}
                        </button>
                    </x-ui.bulk-actions>
                </div>
            </div>
        </div>

        <!-- BOM List Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" id="check-all-boms" class="form-check-input">
                        </th>
                        <th style="width: 15%">{{ __('production.bill_of_material_hash') }}</th>
                        <th style="width: 20%">{{ __('production.bill_of_material_name') }}</th>
                        <th style="width: 12%">{{ __('production.status') }}</th>
                        <th style="width: 15%">{{ __('production.revision_notes') }}</th>
                        <th style="width: 18%">{{ __('production.item_to_produce') }}</th>
                        <th style="width: 10%" class="text-end">{{ __('production.quantity_to_produce') }}</th>
                        <th style="width: 7%">{{ __('production.unit') }}</th>
                        <th class="text-end" style="width: 10%">{{ __('production.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($boms as $bom)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input bom-checkbox" value="{{ $bom->id }}">
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
                                    <span class="erp-badge-active">{{ __('production.active') }}</span>
                                @elseif($bom->status === 'draft')
                                    <span class="erp-badge-draft">{{ __('production.draft') }}</span>
                                @elseif($bom->status === 'pending_approval')
                                    <span class="erp-badge-pending">{{ __('production.pending') }}</span>
                                @else
                                    <span class="erp-badge-draft text-uppercase">{{ __('production.' . $bom->status) }}</span>
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
                                                <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('production.edit_draft') }}
                                            </a>
                                        </li>
                                        <li>
                                            @if($bom->routing_id)
                                                <form method="POST" action="{{ route('production.boms.submit', $bom->id) }}">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="feather-send me-2 text-muted fs-12"></i>{{ __('production.submit_approval') }}
                                                    </button>
                                                </form>
                                            @else
                                                <button type="button" class="dropdown-item text-muted" disabled title="Routing reference is required before submitting for approval" data-bs-toggle="tooltip" style="cursor: not-allowed;">
                                                    <i class="feather-send me-2 text-muted fs-12"></i>{{ __('production.submit_approval_routing_required') }}
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
                                                    <i class="feather-check-circle me-2 text-success fs-12"></i>{{ __('production.approve_bom') }}
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $bom->id }}">
                                                <i class="feather-x-circle me-2 text-danger fs-12"></i>{{ __('production.reject_bom') }}
                                            </button>
                                        </li>
                                    @endif

                                    {{-- Cancel --}}
                                    @if($bom->isApproved())
                                        <li>
                                            <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#cancelModal{{ $bom->id }}">
                                                <i class="feather-slash me-2 text-danger fs-12"></i>{{ __('production.cancel_bom') }}
                                            </button>
                                        </li>
                                    @endif

                                    {{-- Duplicate --}}
                                    <li>
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#duplicateModal{{ $bom->id }}">
                                            <i class="feather-copy me-2 text-muted fs-12"></i>{{ __('production.duplicate_version') }}
                                        </button>
                                    </li>

                                    {{-- Delete (draft only) --}}
                                    @if($bom->isDraft() || $bom->isUnderRevision())
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form id="delete-form-{{ $bom->id }}" method="POST" action="{{ route('production.boms.destroy', $bom->id) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="dropdown-item text-danger" onclick="confirmAction(
                                                    '{{ __('production.confirm_delete') }}',
                                                    function() {
                                                        document.getElementById('delete-form-{{ $bom->id }}').submit();
                                                    },
                                                    {
                                                        title: '{{ __('production.confirm_delete_title') }}',
                                                        confirmButtonText: '{{ __('production.delete') }}',
                                                        confirmButtonClass: 'btn-danger'
                                                    }
                                                );">
                                                    <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('production.delete_permanent') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>

                                <!-- Duplicate Modal -->
                                <x-ui.modal id="duplicateModal{{ $bom->id }}" title="{{ __('production.duplicate_bom_version') }}" submit-text="{{ __('production.create_version') }}" class="text-start">
                                    <form method="POST" action="{{ route('production.boms.duplicate', $bom->id) }}" id="dupForm{{ $bom->id }}">
                                        @csrf
                                        <p class="fs-13 text-muted">{{ __('production.duplicate_modal_body') }}</p>
                                        <x-ui.input :label="__('production.new_version_name')" name="new_version" placeholder="e.g. 1.1.0 or 2.0.0" required />
                                    </form>
                                    <x-slot name="footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                                        <button type="submit" class="btn btn-primary" onclick="document.getElementById('dupForm{{ $bom->id }}').submit();">{{ __('production.duplicate_version') }}</button>
                                    </x-slot>
                                </x-ui.modal>

                                <!-- Reject Modal -->
                                <x-ui.modal id="rejectModal{{ $bom->id }}" title="{{ __('production.reject_bom_version') }}" submit-text="{{ __('production.reject_version') }}" class="text-start">
                                    <form method="POST" action="{{ route('production.boms.reject', $bom->id) }}" id="rejectForm{{ $bom->id }}">
                                        @csrf
                                        <p class="fs-13 text-muted">{{ __('production.reject_modal_body') ?? 'Provide comments explaining the reason for rejection.' }}</p>
                                        <x-ui.input :label="__('production.rejection_reason')" name="comments" placeholder="e.g. Scrap percentage is too high" required />
                                    </form>
                                    <x-slot name="footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                                        <button type="submit" class="btn btn-danger" onclick="document.getElementById('rejectForm{{ $bom->id }}').submit();">{{ __('production.reject_bom') }}</button>
                                    </x-slot>
                                </x-ui.modal>

                                <!-- Cancel Modal -->
                                <x-ui.modal id="cancelModal{{ $bom->id }}" title="{{ __('production.cancel_bom_version') }}" submit-text="{{ __('production.cancel_version') }}" class="text-start">
                                    <form method="POST" action="{{ route('production.boms.cancel', $bom->id) }}" id="cancelForm{{ $bom->id }}">
                                        @csrf
                                        <p class="fs-13 text-muted">{{ __('production.cancel_modal_body') ?? 'Provide comments explaining why this BOM is being cancelled.' }}</p>
                                        <x-ui.input :label="__('production.cancellation_reason')" name="comments" placeholder="e.g. Product design obsolete" required />
                                    </form>
                                    <x-slot name="footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                                        <button type="submit" class="btn btn-danger" onclick="document.getElementById('cancelForm{{ $bom->id }}').submit();">{{ __('production.cancel_bom') }}</button>
                                    </x-slot>
                                </x-ui.modal>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="feather-info me-2 fs-16"></i>{{ __('production.no_boms_found') }}
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

    {{-- Import BOMs Modal --}}
    <x-ui.modal id="importBomsModal" title="{{ __('production.import_boms_title') }}" submitText="{{ __('production.import_file') }}" :centered="true">
        <form method="POST" action="{{ route('production.import-export.import-preview', 'boms') }}" enctype="multipart/form-data" id="importBomsForm">
            @csrf
            <p class="fs-13 text-muted mb-3">{{ __('production.import_modal_body') }}</p>
            <x-ui.odoo-form-ui type="file" name="file" :label="__('production.file_label')" required :placeholder="__('production.choose_file')" />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
            <button type="submit" form="importBomsForm" class="btn btn-primary">{{ __('production.import_file') }}</button>
        </x-slot>
    </x-ui.modal>

    {{-- Bulk Actions Hidden Forms --}}
    <form id="bulk-action-form" action="{{ route('production.boms.bulk-action') }}" method="POST" style="display: none;">
        @csrf
        <div id="bulk-action-inputs-container"></div>
    </form>

@endsection
