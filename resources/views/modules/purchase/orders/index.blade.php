@extends('layouts.duralux')

@section('title', __('purchase.purchase_orders') . ' | SaaS ERP')
@section('page-title', __('purchase.purchase_orders'))
@section('breadcrumb')
    {{ __('ui.purchase') }} / {{ __('purchase.purchase_orders') }}
@endsection

@section('page-actions')
    <x-ui.button href="{{ route('purchase.orders.create') }}" variant="primary" icon="feather-plus" style="background-color: #714B67; border-color: #714B67;">
        {{ __('purchase.create_purchase_order') }}
    </x-ui.button>
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'id');
        $sortOrder = request('sort_order', 'desc');
        $currency = tenant()?->settings['currency'] ?? 'INR';
    @endphp

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Toast Notifications -->

        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('purchase.purchase_orders_listing') }}</h5>
            
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Dropdown -->
                <x-ui.sort-dropdown :label="__('purchase.sort') ?? 'Sort'">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('purchase.date_latest') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('purchase.date_oldest') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'purchase_order_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'purchase_order_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('purchase.po_number_az') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'purchase_order_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'purchase_order_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('purchase.po_number_za') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'grand_total', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'grand_total' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('purchase.amount_high_low') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'grand_total', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'grand_total' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('purchase.amount_low_high') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Panel -->
                <form method="GET" action="{{ route('purchase.orders.index') }}" class="d-inline">
                    <x-ui.filter :label="__('purchase.filter') ?? 'Filters'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.search_keyword') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('purchase.search_po_placeholder') }}" value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('purchase.all_statuses') }}</option>
                                <option value="Draft" @selected(request('status') === 'Draft')>{{ __('purchase.status_draft') }}</option>
                                <option value="Approved" @selected(request('status') === 'Approved')>{{ __('purchase.status_approved') }}</option>
                                <option value="Cancelled" @selected(request('status') === 'Cancelled')>{{ __('purchase.status_cancelled') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('purchase.orders.index') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Listing Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="poTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input select-all">
                        </th>
                        <th style="width: 12%">{{ __('purchase.po_no') }}</th>
                        <th style="width: 18%">{{ __('purchase.supplier_name') }}</th>
                        <th style="width: 12%">{{ __('purchase.ref_document') }}</th>
                        <th style="width: 10%">{{ __('purchase.po_date') }}</th>
                        <th style="width: 10%" class="text-end">{{ __('purchase.subtotal') }}</th>
                        <th style="width: 9%" class="text-end">{{ __('purchase.total_tax') }}</th>
                        <th style="width: 12%" class="text-end">{{ __('purchase.grand_total') }}</th>
                        <th style="width: 12%" class="text-center">{{ __('purchase.status') }}</th>
                        <th style="width: 12%" class="text-end">{{ __('purchase.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input row-checkbox" value="{{ $order->id }}">
                            </td>
                            <td class="fw-bold">
                                <a href="{{ route('purchase.orders.show', $order->id) }}" class="text-primary text-decoration-none">
                                    {{ $order->purchase_order_number }}
                                </a>
                            </td>
                            <td>{{ $order->vendor->name ?? '—' }}</td>
                            <td>
                                @if($order->requisition)
                                    <a href="{{ route('purchase.requisitions.show', $order->purchase_requisition_id) }}" class="text-primary fw-medium">
                                        {{ $order->requisition->requisition_number }}
                                    </a>
                                @else
                                    <span class="text-muted small">{{ __('purchase.direct_po') }}</span>
                                @endif
                            </td>
                            <td>{{ $order->date ? $order->date->format('d-M-Y') : '—' }}</td>
                            <td class="text-end font-monospace">{{ number_format($order->subtotal, 2) }}</td>
                            <td class="text-end font-monospace text-muted">{{ number_format($order->tax_amount, 2) }}</td>
                            <td class="text-end font-monospace fw-bold text-success">{{ number_format($order->grand_total, 2) }}</td>
                            <td class="text-center">
                                @php
                                    $statusClass = 'warning';
                                    if ($order->status === 'Approved') $statusClass = 'success';
                                    elseif ($order->status === 'Cancelled') $statusClass = 'danger';

                                    $statusText = match($order->status) {
                                        'Draft' => __('purchase.status_draft'),
                                        'Approved' => __('purchase.status_approved'),
                                        'Cancelled' => __('purchase.status_cancelled'),
                                        default => $order->status,
                                    };
                                @endphp
                                <x-ui.badge :soft="true" :variant="$statusClass">
                                    {{ $statusText }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end">
                                <x-ui.action-dropdown :viewUrl="route('purchase.orders.show', $order->id)" id="poActions-{{ $order->id }}">
                                    @if($order->status === 'Draft')
                                        <li>
                                            <a class="dropdown-item py-2" href="{{ route('purchase.orders.edit', $order->id) }}">
                                                <i class="feather-edit me-1.5 text-muted"></i> {{ __('purchase.edit_draft') }}
                                            </a>
                                        </li>
                                         <li>
                                             <form action="{{ route('purchase.orders.destroy', $order->id) }}" method="POST" id="deletePoListForm_{{ $order->id }}">
                                                 @csrf
                                                 @method('DELETE')
                                                 <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmAction({ title: 'Delete PO', message: '{{ __('purchase.confirm_delete_po') }}', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deletePoListForm_{{ $order->id }}').submit(); })">
                                                     <i class="feather-trash-2 me-1.5"></i> {{ __('purchase.delete') }}
                                                 </button>
                                             </form>
                                         </li>
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted fs-14">
                                <i class="feather-truck fs-24 mb-1.5 d-block opacity-50"></i>
                                {{ __('purchase.no_pos_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Links -->
        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$orders->currentPage()" 
                :totalPages="$orders->lastPage()" 
                :totalResults="$orders->total()" 
                :perPage="$orders->perPage()" />
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('.select-all').on('change', function() {
                $('.row-checkbox').prop('checked', $(this).prop('checked'));
            });
        });
    </script>
@endpush
