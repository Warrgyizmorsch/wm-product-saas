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
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Tab navigation using Common UI Component -->
        <x-ui.horizontal-tabs id="vendorBillsPendingTabNav" class="mb-4" :tabs="[
            [
                'id' => 'tab-all-bills',
                'label' => 'All Bills',
                'active' => false,
                'icon' => 'feather-file-text',
            ],
            [
                'id' => 'tab-pending-bills',
                'label' => 'Pending Inbound Bills' . (($pendingGrnsCount ?? 0) > 0 ? ' (' . $pendingGrnsCount . ')' : ''),
                'active' => true,
                'icon' => 'feather-clock',
            ],
            [
                'id' => 'tab-pending-freight',
                'label' => 'Pending Outbound Freight Bills' . (($pendingFreightCount ?? 0) > 0 ? ' (' . $pendingFreightCount . ')' : ''),
                'active' => false,
                'icon' => 'feather-truck',
            ]
        ]" />

        <!-- Header Title & Common Filter -->
        <div class="d-flex align-items-center justify-content-between mb-3.5 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-clock me-2 text-warning"></i>Pending Goods Receipts
                </h5>
                <p class="text-muted fs-12 mb-0">Approved store material receipts ready for vendor billing.</p>
            </div>

            <!-- Actions & Common Filter Panel -->
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('purchase.bills.create-service') }}" class="btn btn-sm btn-soft-primary fw-bold text-primary px-3 shadow-sm border border-primary-subtle">
                    <i class="feather-plus me-1"></i>Create Service Bill
                </a>

                <form method="GET" action="{{ route('purchase.bills.pending') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.search_keyword') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="GRN / PO / Vendor Name..." value="{{ request('search') }}" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('purchase.bills.pending') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="pendingBillsTable">
                <thead>
                    <tr class="text-nowrap">
                        <th style="min-width: 140px;">GRN NUMBER</th>
                        <th style="min-width: 140px;">PO NUMBER</th>
                        <th style="min-width: 170px;">SUPPLIER / VENDOR</th>
                        <th style="min-width: 140px;">WAREHOUSE</th>
                        <th style="min-width: 110px;">RECEIPT DATE</th>
                        <th style="min-width: 90px;" class="text-center">REC. QTY</th>
                        <th style="min-width: 110px;" class="text-center">BILLING STATUS</th>
                        <th style="min-width: 160px;" class="text-end">ACTION</th>
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
                            <td class="fw-semibold text-dark">{{ $grn->vendor?->name ?? 'N/A' }}</td>
                            <td>
                                <i class="feather-archive me-1 text-muted"></i>{{ $grn->warehouse?->name ?? __('purchase.main_warehouse') }}
                            </td>
                            <td>{{ $grn->received_date ? $grn->received_date->format('d M Y') : '—' }}</td>
                            <td class="text-center font-monospace font-weight-bold text-success">
                                {{ number_format($recQty, 2) }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-soft-warning text-warning border border-warning-subtle px-2.5 py-1 fw-bold fs-11">
                                    <i class="feather-clock me-1"></i>Pending Bill
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end align-items-center" style="gap: 8px;">
                                    @if($hasMaterialBill)
                                        <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-1 fw-bold fs-11 d-inline-flex align-items-center gap-1" title="Material Goods Bill Has Been Created">
                                            <i class="feather-check"></i> Material Billed
                                        </span>
                                    @else
                                        <a href="{{ route('purchase.bills.create', ['grn_id' => $grn->id]) }}" class="btn btn-sm btn-success text-white fw-bold shadow-sm py-1 px-2 fs-11 d-inline-flex align-items-center gap-1" title="Create Material Goods Bill" data-bs-toggle="tooltip">
                                            <i class="feather-file-text"></i> Create Bill
                                        </a>
                                    @endif

                                    @if($requiresFreight)
                                        @if($hasFreightBill)
                                            <span class="badge bg-soft-info text-info border border-info-subtle px-2 py-1 fw-bold fs-11 d-inline-flex align-items-center gap-1" title="Freight / Transporter Bill Has Been Created">
                                                <i class="feather-check"></i> Freight Billed
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
                                <h6 class="fw-bold text-dark mb-1">All Approved GRNs Have Been Billed!</h6>
                                <p class="fs-12 mb-0">There are currently no unbilled Goods Receipt Notes waiting for vendor bill creation.</p>
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
