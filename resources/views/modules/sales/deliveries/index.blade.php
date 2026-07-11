@extends('layouts.duralux')

@section('title', 'Delivery Orders | SaaS ERP')
@section('page-title', 'Delivery Orders')
@section('breadcrumb', 'Sales / Deliveries')

@section('content')

    {{-- Session Alerts --}}
    @if (session('success'))
        <x-ui.alert variant="success" :dismissible="true" icon="feather-check-circle" class="shadow-sm mb-4">
            <strong>Success!</strong> {{ session('success') }}
        </x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert variant="danger" :dismissible="true" icon="feather-alert-triangle" class="shadow-sm mb-4">
            <strong>Error!</strong> {{ session('error') }}
        </x-ui.alert>
    @endif

    <x-ui.odoo-form-ui type="sheet" class="p-0">

        {{-- Header --}}
        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom">
            <h6 class="fw-bold text-dark mb-0">
                <i class="feather-truck me-2 text-primary"></i>Delivery Orders (Shipments)
            </h6>
        </div>

        {{-- Table --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" class="align-middle fs-13 mb-0" style="margin-top:0; border-radius:0;">
                <thead class="fs-11 text-uppercase fw-semibold text-muted">
                    <tr>
                        <th class="ps-4">Delivery Number</th>
                        <th>Sales Order</th>
                        <th>Customer</th>
                        <th>Delivery Date</th>
                        <th>Carrier</th>
                        <th>Tracking</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-dark">
                    @forelse ($deliveries as $do)
                        @php
                            $badgeVariant = 'secondary';
                            if (in_array($do->status, ['Ready', 'Delivered']))              $badgeVariant = 'success';
                            elseif (in_array($do->status, ['Partially Ready', 'Processing'])) $badgeVariant = 'info';
                            elseif (in_array($do->status, ['Picked', 'Packed']))             $badgeVariant = 'primary';
                            elseif ($do->status === 'Dispatched')                            $badgeVariant = 'dark';
                            elseif ($do->status === 'Cancelled')                             $badgeVariant = 'danger';
                        @endphp
                        <tr>
                            <td class="ps-4">
                                <a href="{{ route('sales.deliveries.show', $do->id) }}" class="fw-bold text-primary">
                                    {{ $do->delivery_number }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('sales.orders.show', $do->sales_order_id) }}" class="fw-semibold text-dark">
                                    {{ $do->salesOrder->sales_order_number }}
                                </a>
                            </td>
                            <td class="fw-semibold">{{ $do->salesOrder->customer?->name ?? '—' }}</td>
                            <td class="text-muted">{{ $do->delivery_date->format('d/m/Y') }}</td>
                            <td class="text-muted">{{ $do->carrier ?: '—' }}</td>
                            <td class="text-muted font-monospace fs-12">{{ $do->tracking_number ?: '—' }}</td>
                            <td>
                                <x-ui.badge :soft="true" :variant="$badgeVariant" class="fs-11 px-2">
                                    {{ $do->status }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end pe-4">
                                @php
                                    $invoiced      = $do->salesOrder?->invoices->where('delivery_order_id', $do->id)->first();
                                    $invoicePolicy = config('sales.invoice_policy', 'On Dispatch');
                                    $canInvoice    = ($invoicePolicy === 'On Dispatch')
                                        ? in_array($do->status, ['Dispatched', 'Delivered', 'Shipped'])
                                        : ($do->status === 'Delivered');
                                @endphp
                                <x-ui.action-dropdown :viewUrl="route('sales.deliveries.show', $do->id)">
                                    <x-ui.dropdown-item href="{{ route('sales.deliveries.show', $do->id) }}" icon="feather-eye">
                                        View Details
                                    </x-ui.dropdown-item>
                                    @if ($canInvoice && !$invoiced)
                                        <x-ui.dropdown-item href="{{ route('sales.invoices.create', ['delivery_order_id' => $do->id]) }}" icon="feather-file-text">
                                            Create Invoice
                                        </x-ui.dropdown-item>
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="feather-truck fs-1 d-block text-muted mb-2"></i>
                                <span class="text-muted fs-13">No delivery orders found. Create shipments directly from Confirmed Sales Orders.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

    </x-ui.odoo-form-ui>

@endsection
