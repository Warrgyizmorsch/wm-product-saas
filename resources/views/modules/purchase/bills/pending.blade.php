@extends('layouts.duralux')

@section('title', 'Pending Bills (Unbilled GRNs) | SaaS ERP')
@section('page-title', 'Pending Vendor Bills')
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.vendor_bills') . ' / Pending Bills')

@push('styles')
    <style>
        #pendingBillsTable th {
            white-space: nowrap !important;
            font-size: 11px !important;
            letter-spacing: 0.5px !important;
        }
        #pendingBillsTable td {
            vertical-align: middle !important;
            white-space: nowrap !important;
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
        .action-icon-btn.bill-btn:hover {
            background-color: color-mix(in srgb, #10b981 12%, transparent) !important;
            border-color: #10b981 !important;
            color: #10b981 !important;
        }
        .action-icon-btn.view-btn:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent) !important;
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
        }
        .action-icon-btn.download-btn:hover {
            background-color: color-mix(in srgb, #0284c7 10%, transparent) !important;
            border-color: #0284c7 !important;
            color: #0284c7 !important;
        }
    </style>
@endpush

@section('content')
    <div class="erp-single-panel">
        <!-- 1. Header Title & Common Filter (Top) -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-clock me-2 text-warning"></i>{{ __('purchase.pending_goods_receipts') }}
                </h5>
                <p class="text-muted fs-12 mb-0">{{ __('purchase.pending_goods_receipts_help') }}</p>
            </div>

            <!-- Actions & Common Filter Panel -->
            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Search Box (CRM Leads / HRMS Style) -->
                <form method="GET" action="{{ route('purchase.bills.pending') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('purchase.search_grn_po_vendor') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('purchase.bills.pending', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <a href="{{ route('purchase.bills.create-service') }}" class="btn btn-sm btn-soft-primary fw-bold text-primary px-3 shadow-sm border border-primary-subtle">
                    <i class="feather-plus me-1"></i>{{ __('purchase.create_service_bill') }}
                </a>

                <form method="GET" action="{{ route('purchase.bills.pending') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.search_keyword') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('purchase.search_grn_po_vendor') }}" value="{{ request('search') }}" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('purchase.bills.pending') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- 2. Tab navigation (Below filter line) -->
        <x-ui.horizontal-tabs id="vendorBillsPendingTabNav" class="mb-3" :tabs="[
            [
                'id' => 'tab-all-bills',
                'label' => __('purchase.tab_all_bills'),
                'active' => false,
                'icon' => 'feather-file-text',
            ],
            [
                'id' => 'tab-pending-bills',
                'label' => __('purchase.tab_pending_inbound_bills') . (($pendingGrnsCount ?? 0) > 0 ? ' (' . $pendingGrnsCount . ')' : ''),
                'active' => true,
                'icon' => 'feather-clock',
            ],
            [
                'id' => 'tab-pending-freight',
                'label' => __('purchase.tab_pending_outbound_freight') . (($pendingFreightCount ?? 0) > 0 ? ' (' . $pendingFreightCount . ')' : ''),
                'active' => false,
                'icon' => 'feather-truck',
            ]
        ]" />

        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="pendingBillsTable" class="mb-0">
                <thead>
                    <tr style="background-color: #e8ecf1 !important;">
                        <th style="min-width: 140px; background-color: #e8ecf1 !important;">{{ __('purchase.grn_number') }}</th>
                        <th style="min-width: 140px; background-color: #e8ecf1 !important;">{{ __('purchase.po_number') }}</th>
                        <th style="min-width: 170px; background-color: #e8ecf1 !important;">{{ __('purchase.supplier_vendor') }}</th>
                        <th style="min-width: 140px; background-color: #e8ecf1 !important;">{{ __('purchase.warehouse') }}</th>
                        <th style="min-width: 110px; background-color: #e8ecf1 !important;">{{ __('purchase.receipt_date') }}</th>
                        <th style="min-width: 90px; background-color: #e8ecf1 !important;" class="text-center">{{ __('purchase.received_qty') }}</th>
                        <th style="min-width: 110px; background-color: #e8ecf1 !important;" class="text-center">{{ __('purchase.billing_status') }}</th>
                        <th style="min-width: 160px; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('purchase.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingGrns as $grn)
                        @php
                            $recQty = (float)$grn->items->sum('received_qty');
                            $activeBills = $grn->vendorBills ? $grn->vendorBills->where('status', '!=', 'Cancelled') : collect();
                            $hasMaterialBill = $activeBills->contains(fn($b) => (int)$b->vendor_id === (int)$grn->vendor_id);
                            $hasFreightBill = $activeBills->contains(fn($b) => (int)$b->vendor_id !== (int)$grn->vendor_id);
                            $requiresFreight = $grn->purchaseOrder && in_array(strtolower($grn->purchaseOrder->freight_terms ?? ''), ['to_pay', 'to pay']);
                        @endphp
                        <tr>
                            <td class="ps-4 fw-bold font-monospace">
                                <a href="{{ route('grns.show', $grn->id) }}" class="text-primary">
                                    {{ $grn->grn_number }}
                                </a>
                            </td>
                            <td class="font-monospace fw-semibold">
                                @if($grn->purchaseOrder)
                                    <a href="{{ route('purchase.orders.show', $grn->purchase_order_id) }}" class="text-dark">
                                        {{ $grn->purchaseOrder->purchase_order_number }}
                                    </a>
                                @else
                                    <span class="text-muted">{{ __('purchase.direct_receipt') }}</span>
                                @endif
                            </td>
                            <td class="fw-semibold text-dark">{{ $grn->vendor?->name ?: '—' }}</td>
                            <td>
                                <span class="badge bg-light text-secondary border">
                                    <i class="feather-box me-1"></i>{{ $grn->warehouse?->name ?: 'Main Warehouse' }}
                                </span>
                            </td>
                            <td>{{ $grn->receipt_date ? $grn->receipt_date->format('d-M-Y') : '—' }}</td>
                            <td class="text-center font-monospace fw-bold">{{ number_format($recQty, 2) }}</td>
                            <td class="text-center">
                                @if($hasMaterialBill && $requiresFreight && !$hasFreightBill)
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 fs-11">
                                        <i class="feather-truck me-1"></i>{{ __('purchase.material_billed_freight_pending') }}
                                    </span>
                                @elseif($hasMaterialBill)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-11">
                                        <i class="feather-check me-1"></i>{{ __('purchase.fully_billed') }}
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-11">
                                        <i class="feather-clock me-1"></i>{{ __('purchase.pending_bill') }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-1.5 align-items-center">
                                    @if(!$hasMaterialBill)
                                        <a href="{{ route('purchase.bills.create', ['grn_id' => $grn->id]) }}" class="btn btn-sm btn-primary fw-bold shadow-sm py-1 px-2.5 fs-11 d-inline-flex align-items-center gap-1">
                                            <i class="feather-plus-circle"></i> {{ __('purchase.create_bill') }}
                                        </a>
                                    @else
                                        @if(!$requiresFreight || $hasFreightBill)
                                            <span class="badge bg-light text-muted border py-1.5 px-2 fs-11">
                                                <i class="feather-check-circle me-1 text-success"></i>Billed
                                            </span>
                                        @else
                                            <a href="{{ route('purchase.bills.create-service', ['grn_id' => $grn->id]) }}" class="btn btn-sm btn-outline-primary fw-bold shadow-sm py-1 px-2 fs-11 d-inline-flex align-items-center gap-1" title="Create Freight / Service Bill for this GRN" data-bs-toggle="tooltip">
                                                <i class="feather-truck"></i> Freight Bill
                                            </a>
                                        @endif
                                    @endif

                                    <a href="{{ route('grns.show', $grn->id) }}" class="action-icon-btn view-btn" title="{{ __('ui.view') }}" data-bs-toggle="tooltip">
                                        <i class="feather-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="feather-check-circle fs-36 text-success d-block mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">{{ __('purchase.all_approved_grns_billed') }}</h6>
                                <p class="fs-12 mb-0">{{ __('purchase.no_unbilled_grns_help') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        @if($pendingGrns->hasPages())
            <div class="mt-3">
                {{ $pendingGrns->links() }}
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
            $('#tab-pending-freight-tab').on('click', function() {
                window.location.href = "{{ route('purchase.bills.pending-freight') }}";
            });
        });
    </script>
@endpush
