@extends('layouts.duralux')

@section('title', 'Pending Freight Bills | SaaS ERP')
@section('page-title', 'Pending Freight Obligations')
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.vendor_bills') . ' / Pending Freight Bills')

@push('styles')
    <style>
        #pendingFreightTable {
            table-layout: auto !important;
            width: 100% !important;
        }
        #pendingFreightTable th {
            white-space: nowrap !important;
            font-size: 11px !important;
            letter-spacing: 0.5px !important;
            padding: 10px 12px !important;
            background-color: #f8fafc !important;
        }
        #pendingFreightTable td {
            vertical-align: middle !important;
            white-space: nowrap !important;
            padding: 10px 12px !important;
        }
        .customer-col {
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .transporter-col {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .action-icon-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 32px !important;
            height: 32px !important;
            border-radius: 8px !important;
            border: 1.5px solid #cbd5e1 !important;
            background-color: #ffffff !important;
            color: #475569 !important;
            transition: all 0.28s ease !important;
            text-decoration: none !important;
            cursor: pointer !important;
        }
        .action-icon-btn.view-btn:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent) !important;
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
        }
    </style>
@endpush

@section('content')
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Tab navigation using Common UI Component -->
        <x-ui.horizontal-tabs id="vendorBillsPendingFreightTabNav" class="mb-4" :tabs="[
            [
                'id' => 'tab-all-bills',
                'label' => 'All Bills',
                'active' => false,
                'icon' => 'feather-file-text',
            ],
            [
                'id' => 'tab-pending-bills',
                'label' => 'Pending Inbound Bills' . (($pendingGrnsCount ?? 0) > 0 ? ' (' . $pendingGrnsCount . ')' : ''),
                'active' => false,
                'icon' => 'feather-clock',
            ],
            [
                'id' => 'tab-pending-freight',
                'label' => 'Pending Outbound Freight Bills' . (($pendingFreightCount ?? 0) > 0 ? ' (' . $pendingFreightCount . ')' : ''),
                'active' => true,
                'icon' => 'feather-truck',
            ]
        ]" />

        <!-- Header Title & Common Filter -->
        <div class="d-flex align-items-center justify-content-between mb-3.5 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-truck me-2 text-primary"></i>Pending Freight Obligations
                </h5>
                <p class="text-muted fs-12 mb-0">Sales dispatch transporter payables pending bill creation.</p>
            </div>

            <!-- Actions & Common Filter Panel -->
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('purchase.bills.create-service') }}" class="btn btn-sm btn-primary fw-bold px-3 shadow-sm">
                    <i class="feather-plus me-1"></i>Create Service Bill
                </a>

                <form method="GET" action="{{ route('purchase.bills.pending-freight') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.search_keyword') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search Dispatch #, Invoice, Transporter..." value="{{ request('search') }}" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('purchase.bills.pending-freight') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <div class="table-responsive" style="overflow-x: auto;">
            <x-ui.odoo-form-ui type="table" id="pendingFreightTable">
                <thead>
                    <tr>
                        <th class="ps-3">DISPATCH #</th>
                        <th>REF / INVOICE #</th>
                        <th>CUSTOMER</th>
                        <th>TRANSPORTER</th>
                        <th>LR / BILTY</th>
                        <th>TERMS</th>
                        <th class="text-end">EXPECTED FREIGHT</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-end pe-3">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingFreightDispatches as $dispatch)
                        <tr>
                            <td class="ps-3 fw-bold font-monospace fs-12">
                                <a href="{{ route('sales.dispatches.show', $dispatch->id) }}" class="text-primary">
                                    {{ $dispatch->dispatch_number }}
                                </a>
                            </td>
                            <td class="font-monospace fw-semibold fs-12">
                                @if($dispatch->invoice)
                                    <a href="{{ route('sales.invoices.show', $dispatch->invoice_id) }}" class="text-dark">
                                        {{ $dispatch->invoice->invoice_number }}
                                    </a>
                                @elseif($dispatch->salesOrder)
                                    <a href="{{ route('sales.orders.show', $dispatch->sales_order_id) }}" class="text-muted">
                                        {{ $dispatch->salesOrder->sales_order_number }}
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="customer-col fw-semibold text-dark fs-12" title="{{ $dispatch->customer?->name ?? 'N/A' }}">
                                {{ $dispatch->customer?->name ?? 'N/A' }}
                            </td>
                            <td class="transporter-col fs-12" title="{{ $dispatch->transporter?->name ?: ($dispatch->carrier ?: 'Transporter N/A') }}">
                                <i class="feather-truck me-1 text-muted"></i>{{ $dispatch->transporter?->name ?: ($dispatch->carrier ?: 'Transporter N/A') }}
                            </td>
                            <td class="font-monospace fs-12">
                                {{ $dispatch->lr_number ?: '—' }}
                            </td>
                            <td>
                                @php
                                    $termClass = match($dispatch->freight_terms) {
                                        'To Be Billed' => 'bg-soft-success text-success border-success-subtle',
                                        'FOR Site', 'Prepaid' => 'bg-soft-info text-info border-info-subtle',
                                        default => 'bg-soft-primary text-primary border-primary-subtle',
                                    };
                                @endphp
                                <span class="badge {{ $termClass }} border px-2 py-0.5 fw-bold fs-11">
                                    {{ $dispatch->freight_terms ?: 'To Be Billed' }}
                                </span>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark fs-12">
                                ₹{{ number_format((float) $dispatch->freight_amount, 2) }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-soft-warning text-warning border border-warning-subtle px-2 py-0.5 fw-bold fs-11">
                                    <i class="feather-clock me-1"></i>Pending Bill
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-2 align-items-center">
                                    <a href="{{ route('purchase.bills.create-service', ['mode' => 'outbound', 'dispatch_order_id' => $dispatch->id]) }}" class="btn btn-sm btn-success text-white fw-bold shadow-sm px-2.5 py-1 fs-11 d-inline-flex align-items-center gap-1.5 text-nowrap">
                                        <i class="feather-plus-circle fs-12"></i> Create Freight Bill
                                    </a>
                                    <a href="{{ route('sales.dispatches.show', $dispatch->id) }}" class="action-icon-btn view-btn" title="{{ __('ui.view') }}" data-bs-toggle="tooltip">
                                        <i class="feather-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="feather-check-circle fs-32 text-success d-block mb-1"></i>
                                <h6 class="fw-bold text-dark mb-0fs-13">No Pending Freight Obligations</h6>
                                <p class="fs-12 text-muted mb-0">All sales dispatch transporter bills have been created.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        @if($pendingFreightDispatches->hasPages())
            <div class="mt-3">
                {{ $pendingFreightDispatches->links() }}
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#tab-all-bills-tab').on('click', function() {
                window.location.href = "{{ route('purchase.bills.index') }}";
            });
            $('#tab-pending-bills-tab').on('click', function() {
                window.location.href = "{{ route('purchase.bills.pending') }}";
            });
        });
    </script>
@endpush
