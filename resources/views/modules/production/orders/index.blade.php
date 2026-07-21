@extends('layouts.duralux')

@section('title', __('production.production_orders') . ' | SaaS ERP')
@section('page-title', __('production.production_order_execution'))
@section('breadcrumb', __('production.production_orders'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const headerCheckbox = document.getElementById('check-all-orders');
            const rowCheckboxes = document.querySelectorAll('.order-checkbox');
            const normalToolbar = document.getElementById('normal-toolbar');
            const bulkActionsToolbar = document.getElementById('bulk-actions-toolbar');
            const bulkActionsLabel = document.querySelector('.bulk-actions-label');

            function updateToolbarVisibility() {
                const selectedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
                const selectedCount = selectedCheckboxes.length;

                if (selectedCount > 0) {
                    if (normalToolbar) normalToolbar.classList.add('d-none');
                    if (bulkActionsToolbar) bulkActionsToolbar.classList.remove('d-none');
                    if (bulkActionsLabel) {
                        bulkActionsLabel.textContent = `Selected Actions (${selectedCount})`;
                    }
                } else {
                    if (normalToolbar) normalToolbar.classList.remove('d-none');
                    if (bulkActionsToolbar) bulkActionsToolbar.classList.add('d-none');
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
                    const selectedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
                    if (selectedCheckboxes.length === 0) {
                        confirmAction(@js(__('production.select_one_order_first')), null, {
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
                        case 'release':
                            title = @js(__('production.release_selected'));
                            confirmMessage = @js(__('production.confirm_release_selected')).replace(':count', selectedCheckboxes.length);
                            variant = 'primary';
                            confirmText = @js(__('production.release_order'));
                            break;
                        case 'complete':
                            title = @js(__('production.complete_selected'));
                            confirmMessage = @js(__('production.confirm_complete_selected')).replace(':count', selectedCheckboxes.length);
                            variant = 'success';
                            confirmText = @js(__('production.complete_order'));
                            break;
                        case 'cancel':
                            title = @js(__('production.cancel_selected_orders'));
                            confirmMessage = @js(__('production.confirm_cancel_selected_orders')).replace(':count', selectedCheckboxes.length);
                            variant = 'warning';
                            confirmText = @js(__('production.cancel_order'));
                            break;
                        case 'delete':
                            title = @js(__('production.delete_selected_orders'));
                            confirmMessage = @js(__('production.confirm_delete_selected_orders')).replace(':count', selectedCheckboxes.length);
                            variant = 'danger';
                            confirmText = @js(__('production.delete'));
                            break;
                    }

                    confirmAction(
                        confirmMessage,
                        function() {
                            const form = document.getElementById('bulk-action-form');
                            const container = document.getElementById('bulk-action-inputs-container');
                            container.innerHTML = '';

                            const actionInput = document.createElement('input');
                            actionInput.type = 'hidden';
                            actionInput.name = 'action';
                            actionInput.value = action;
                            container.appendChild(actionInput);

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
    <a href="{{ route('production.orders.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>{{ __('production.create_direct_order') }}
    </a>
@endsection

@section('content')
<div class="erp-single-panel">
    {{-- Alerts --}}
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif

    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    {{-- Hidden Bulk Action Form --}}
    <form id="bulk-action-form" action="{{ route('production.orders.bulk-action') }}" method="POST" class="d-none">
        @csrf
        <div id="bulk-action-inputs-container"></div>
    </form>

    {{-- KPI Status Summary --}}
    <div class="row g-3 mb-4">
        <div class="col">
            <div class="bg-light border rounded p-3 text-center">
                <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('production.draft') }}</span>
                <h4 class="text-dark fw-bold mt-1 mb-0">{{ $statusCounts['draft'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col">
            <div class="bg-soft-primary border rounded p-3 text-center">
                <span class="text-primary fs-11 text-uppercase fw-bold">{{ __('production.released') }}</span>
                <h4 class="text-primary fw-bold mt-1 mb-0">{{ $statusCounts['released'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col">
            <div class="bg-soft-info border rounded p-3 text-center">
                <span class="text-info fs-11 text-uppercase fw-bold">{{ __('production.in_progress') }}</span>
                <h4 class="text-info fw-bold mt-1 mb-0">{{ $statusCounts['in_progress'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col">
            <div class="bg-soft-success border rounded p-3 text-center">
                <span class="text-success fs-11 text-uppercase fw-bold">{{ __('production.completed') }}</span>
                <h4 class="text-success fw-bold mt-1 mb-0">{{ $statusCounts['completed'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col">
            <div class="bg-light border rounded p-3 text-center">
                <span class="text-dark fs-11 text-uppercase fw-bold">{{ __('production.closed') }}</span>
                <h4 class="text-dark fw-bold mt-1 mb-0">{{ $statusCounts['closed'] ?? 0 }}</h4>
            </div>
        </div>
    </div>

    {{-- Toolbar: Title + Sort + Filter + Bulk Actions --}}
    @php
        $sortBy = request('sort_by', 'id');
        $sortOrder = request('sort_order', 'desc');
    @endphp
    <div class="d-flex align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0">{{ __('production.production_orders') }}</h5>
        <div class="d-flex gap-2 ms-auto">
            {{-- Normal Toolbar --}}
            <div id="normal-toolbar" class="d-flex gap-2">
                {{-- Sort Dropdown --}}
                <x-ui.sort-dropdown :label="__('production.sort')">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'id' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('production.sort_newest_first') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'id' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('production.sort_oldest_first') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'order_number', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'order_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('production.order_number_asc') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'order_number', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'order_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('production.order_number_desc') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'end_date', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'end_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('production.due_date_earliest') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'end_date', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'end_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('production.due_date_latest') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                {{-- Filter Overlay --}}
                <form method="GET" action="{{ route('production.orders.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('production.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.search_keywords') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('production.search_orders_placeholder')" value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('production.all_statuses') }}</option>
                                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>{{ __('production.draft') }}</option>
                                <option value="released" {{ request('status') === 'released' ? 'selected' : '' }}>{{ __('production.released') }}</option>
                                <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>{{ __('production.in_progress') }}</option>
                                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('production.completed') }}</option>
                                <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>{{ __('production.closed') }}</option>
                                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('production.cancelled') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.scheduled_dates') }}</label>
                            <div class="d-flex gap-2">
                                <x-ui.odoo-form-ui type="input" name="start_date" value="{{ request('start_date') }}" />
                                <x-ui.odoo-form-ui type="input" name="end_date" value="{{ request('end_date') }}" />
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.orders.index') }}" class="btn btn-sm btn-light border">{{ __('production.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('production.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>

            {{-- Bulk Actions Toolbar (initially hidden) --}}
            <div id="bulk-actions-toolbar" class="d-flex gap-2 d-none">
                <x-ui.bulk-actions :label="__('production.selected_actions') . ' (0)'" id="bulk-actions-dropdown">
                    <button type="button" class="dropdown-item text-primary bulk-action-btn" data-action="release">
                        <i class="feather-play me-2 text-primary"></i> {{ __('production.release_selected') }}
                    </button>
                    <button type="button" class="dropdown-item text-success bulk-action-btn" data-action="complete">
                        <i class="feather-check-circle me-2 text-success"></i> {{ __('production.complete_selected') }}
                    </button>
                    <button type="button" class="dropdown-item text-warning bulk-action-btn" data-action="cancel">
                        <i class="feather-slash me-2 text-warning"></i> {{ __('production.cancel_selected_orders') }}
                    </button>
                    <div class="dropdown-divider"></div>
                    <button type="button" class="dropdown-item text-danger bulk-action-btn" data-action="delete">
                        <i class="feather-trash-2 text-danger"></i> {{ __('production.delete_selected_orders') }}
                    </button>
                </x-ui.bulk-actions>
            </div>
        </div>
    </div>

    {{-- Orders Table --}}
    <div class="table-responsive">
        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width:3%" class="text-center">
                        <input type="checkbox" id="check-all-orders" class="form-check-input">
                    </th>
                    <th style="width:14%">{{ __('production.order_number') }}</th>
                    <th style="width:22%">{{ __('production.finished_product') }}</th>
                    <th style="width:12%" class="text-center">{{ __('production.ordered_qty') }}</th>
                    <th style="width:12%" class="text-center">{{ __('production.quantity_produced') }}</th>
                    <th style="width:18%">{{ __('production.scheduled_dates') }}</th>
                    <th style="width:10%">{{ __('production.status') }}</th>
                    <th style="width:9%" class="text-end">{{ __('production.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input order-checkbox" value="{{ $order->id }}">
                        </td>
                        <td>
                            <a href="{{ route('production.orders.show', $order->id) }}" class="fw-bold text-primary hover-primary">
                                {{ $order->order_number }}
                            </a>
                            @if($order->plan)
                                <div class="fs-11 text-muted">Plan: {{ $order->plan->plan_number }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-semibold text-dark">{{ $order->product->name }}</span>
                                <small class="text-muted font-monospace fs-10">{{ $order->product->sku }}</small>
                            </div>
                        </td>
                        <td class="text-center fw-semibold text-dark">{{ number_format($order->quantity_ordered, 2) }}</td>
                        <td class="text-center fw-bold text-success">{{ number_format($order->quantity_produced, 2) }}</td>
                        <td>
                            <div class="fs-12 text-dark">{{ $order->start_date->format('Y-m-d') }} &rarr; {{ $order->end_date->format('Y-m-d') }}</div>
                            @if($order->actual_start_date)
                                <div class="fs-11 text-info">{{ __('production.started') }}: {{ $order->actual_start_date->format('m-d H:i') }}</div>
                            @endif
                        </td>
                        <td>
                            @if($order->isDraft())
                                <span class="erp-badge-draft">{{ __('production.draft') }}</span>
                            @elseif($order->isReleased())
                                <span class="erp-badge-pending">{{ __('production.released') }}</span>
                            @elseif($order->isInProgress())
                                <span class="badge bg-soft-info text-info">{{ __('production.in_progress') }}</span>
                            @elseif($order->isCompleted())
                                <span class="erp-badge-active">{{ __('production.completed') }}</span>
                            @elseif($order->isClosed())
                                <span class="badge bg-soft-dark text-dark">{{ __('production.closed') }}</span>
                            @elseif($order->isCancelled())
                                <span class="badge bg-soft-danger text-danger">{{ __('production.cancelled') }}</span>
                            @else
                                <span class="erp-badge-draft text-uppercase">{{ $order->status }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <x-ui.action-dropdown :viewUrl="route('production.orders.show', $order->id)">
                                @if($order->isDraft())
                                    <li>
                                        <a href="{{ route('production.orders.edit', $order->id) }}" class="dropdown-item">
                                            <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('production.edit_draft') }}
                                        </a>
                                    </li>
                                @endif
                            </x-ui.action-dropdown>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="feather-package fs-24 d-block mb-2"></i>
                            {{ __('production.no_production_orders_found') }} <a href="{{ route('production.orders.create') }}" class="text-primary">{{ __('production.create_first_order') }}</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>
    </div>

    {{-- Pagination --}}
    @if($orders->hasPages())
        <div class="mt-3">
            {{ $orders->links() }}
        </div>
    @endif
</div>
@endsection
