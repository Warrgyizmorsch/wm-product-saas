@extends('layouts.duralux')

@section('title', __('purchase.create_vendor_bill') . ' | SaaS ERP')
@section('page-title', __('purchase.create_vendor_bill'))
@section('breadcrumb')
    <a href="{{ route('purchase.bills.index') }}">{{ __('purchase.vendor_bills') }}</a> &gt; {{ __('purchase.create') }}
@endsection

@push('styles')
    <style>
        .odoo-sheet {
            background: #ffffff;
            border-radius: 6px;
        }
        .so-status-pipeline {
            display: inline-flex;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            background-color: #f8fafc;
        }
        .so-status-pipeline .pipeline-step {
            padding: 4px 16px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            border-right: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .so-status-pipeline .pipeline-step:last-child {
            border-right: none;
        }
        .so-status-pipeline .pipeline-step.active {
            background: #3b82f6;
            color: #ffffff;
        }
        .bill-totals-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            max-width: 380px;
            width: 100%;
        }
    </style>
@endpush

@section('content')

    <div class="row text-dark">
        <div class="col-12">
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white">
                <form action="{{ route('purchase.bills.store') }}" method="POST" id="createVendorBillForm" class="odoo-sheet">
                    @csrf

                    <input type="hidden" name="goods_receipt_note_id" value="{{ $selectedGrn->id }}">
                    <input type="hidden" name="purchase_order_id" value="{{ $selectedGrn->purchase_order_id }}">

                    <!-- Top Header Bar & Status Pipeline -->
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-soft-warning text-warning fw-bold px-3 py-1.5 fs-12 border border-warning-subtle">
                                <i class="feather-clock me-1"></i>Draft Bill
                            </span>
                            @if($selectedGrn)
                                <span class="badge bg-soft-success text-success fs-12 fw-semibold px-3 py-1.5 rounded-pill">
                                    <i class="feather-check-circle me-1"></i>Source GRN: {{ $selectedGrn->grn_number }} @if($selectedGrn->purchaseOrder) (PO: {{ $selectedGrn->purchaseOrder->purchase_order_number }}) @endif
                                </span>
                            @endif
                        </div>

                        <!-- Status Pipeline -->
                        <div class="so-status-pipeline d-none d-sm-inline-flex">
                            <span class="pipeline-step active">Draft</span>
                            <span class="pipeline-step">Posted</span>
                        </div>
                    </div>

                    <!-- Title Banner -->
                    <div class="mb-4">
                        <h3 class="fw-bold text-dark mb-1">Draft Vendor Bill</h3>
                        <p class="text-muted fs-12 mb-0">Generate vendor invoice record and post stock liability directly to accounting ledger.</p>
                    </div>

                    <!-- General Information Grid -->
                    <div class="row g-4 fs-13 mb-4 pb-3 border-bottom">
                        <div class="col-md-6 border-end pe-md-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-user me-1"></i>Vendor &amp; Invoice Info</h6>

                            @if($selectedGrn)
                                <input type="hidden" name="vendor_id" value="{{ $selectedGrn->vendor_id }}">
                                <div class="mb-3">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('purchase.supplier_vendor') }}" name="vendor_display_name" :value="$selectedGrn->vendor?->name ?? 'N/A'" readonly="true" />
                                    <small class="text-muted fs-11 mt-1 d-block"><i class="feather-lock me-1 text-primary"></i>Vendor is locked from Source Goods Receipt Note ({{ $selectedGrn->grn_number }})</small>
                                </div>
                            @else
                                <x-ui.odoo-form-ui type="select" label="{{ __('purchase.supplier_vendor') }}" name="vendor_id" required="true" :errorText="$errors->first('vendor_id')">
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->id }}" @selected(old('vendor_id') == $vendor->id)>
                                            {{ $vendor->name }}
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            @endif

                            <x-ui.odoo-form-ui type="input" label="{{ __('purchase.vendor_invoice_bill_no') }}" name="vendor_invoice_number" :value="old('vendor_invoice_number', $selectedGrn->challan_number)" placeholder="e.g. INV-98765" :errorText="$errors->first('vendor_invoice_number')" />
                        </div>

                        <div class="col-md-6 ps-md-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-calendar me-1"></i>Accounting Dates</h6>

                            <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('purchase.bill_date') }}" name="bill_date" :value="old('bill_date', date('Y-m-d'))" required="true" :errorText="$errors->first('bill_date')" />

                            <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('purchase.due_date') }}" name="due_date" :value="old('due_date', date('Y-m-d', strtotime('+30 days')))" required="true" :errorText="$errors->first('due_date')" />
                        </div>
                    </div>

                    <!-- Line Items Table (Odoo Common Table Component) -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark mb-2"><i class="feather-box text-primary me-2"></i>{{ __('purchase.grn_received_items_rates') }}</h6>

                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table" id="billItemsTable">
                                <thead>
                                    <tr>
                                        <th class="ps-2 py-2" style="width: 40%;">{{ __('purchase.product') }}</th>
                                        <th class="text-center py-2" style="width: 18%;">{{ __('purchase.accepted_qty') }}</th>
                                        <th class="text-end py-2" style="width: 20%;">{{ __('purchase.unit_rate') }} (₹)</th>
                                        <th class="text-end pe-2 py-2" style="width: 22%;">{{ __('purchase.line_total') }} (₹)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $grandTotal = 0; @endphp
                                    @foreach($selectedGrn->items as $idx => $item)
                                        @php
                                            $lineTotal = (float) $item->accepted_qty * (float) $item->unit_rate;
                                            $grandTotal += $lineTotal;
                                        @endphp
                                        <tr class="bill-line-row">
                                            <td class="ps-2 py-2">
                                                <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $item->product_id }}">
                                                <input type="hidden" name="items[{{ $idx }}][goods_receipt_note_item_id]" value="{{ $item->id }}">
                                                <strong class="text-dark d-block fs-13">{{ $item->product?->name }}</strong>
                                                @if($item->product?->sku)
                                                    <span class="text-muted fs-11 font-monospace">{{ __('purchase.sku') }}: {{ $item->product->sku }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center py-2">
                                                <input type="number" name="items[{{ $idx }}][quantity]" class="odoo-table-input text-center font-monospace item-qty" value="{{ (float)$item->accepted_qty }}" step="0.001" min="0.001" required readonly>
                                            </td>
                                            <td class="text-end py-2">
                                                <input type="number" name="items[{{ $idx }}][unit_price]" class="odoo-table-input text-end font-monospace item-rate" value="{{ (float)$item->unit_rate }}" step="0.01" min="0" required>
                                            </td>
                                            <td class="text-end pe-2 py-2 font-monospace fw-bold text-dark item-total-display">
                                                ₹{{ number_format($lineTotal, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <!-- Bottom Section: Notes & Summary Totals -->
                    <div class="row g-4 align-items-start mt-2">
                        <div class="col-md-7 col-lg-8">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('purchase.notes_terms') }}" name="notes" placeholder="{{ __('purchase.notes_placeholder') }}" rows="3" />
                        </div>

                        <div class="col-md-5 col-lg-4 ms-auto">
                            <div class="bill-totals-box ms-auto shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2 fs-13">
                                    <span class="text-muted fw-semibold">Untaxed Amount:</span>
                                    <span class="font-monospace fw-bold text-dark" id="subtotalDisplay">₹{{ number_format($grandTotal, 2) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2 fs-13">
                                    <span class="text-muted fw-semibold">Tax:</span>
                                    <span class="font-monospace text-muted">₹0.00</span>
                                </div>
                                <hr class="my-2 border-slate">
                                <div class="d-flex justify-content-between align-items-center fs-15">
                                    <span class="fw-bold text-dark">Total Amount:</span>
                                    <span class="font-monospace fw-bold text-success fs-17" id="grandTotalDisplay">₹{{ number_format($grandTotal, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Action Control Bar (Post Bill & Cancel at bottom) -->
                    <div class="d-flex justify-content-between align-items-center mt-5 pt-3 border-top flex-wrap gap-2">
                        <x-ui.button href="{{ route('purchase.bills.pending') }}" variant="light" icon="feather-arrow-left" class="border fw-semibold">
                            {{ __('purchase.cancel') }}
                        </x-ui.button>

                        <x-ui.button type="submit" variant="primary" icon="feather-check-circle" class="fw-bold px-4 py-2 text-white shadow-sm" style="background-color: #714B67; border-color: #714B67;">
                            {{ __('purchase.post_bill_to_gl') }}
                        </x-ui.button>
                    </div>

                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = document.getElementById('billItemsTable');
            if (!table) return;

            function calculateTotals() {
                let grandTotal = 0;
                const rows = table.querySelectorAll('.bill-line-row');

                rows.forEach(row => {
                    const qtyInput = row.querySelector('.item-qty');
                    const rateInput = row.querySelector('.item-rate');
                    const totalDisplay = row.querySelector('.item-total-display');

                    const qty = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
                    const rate = parseFloat(rateInput ? rateInput.value : 0) || 0;
                    const lineTotal = qty * rate;

                    grandTotal += lineTotal;

                    if (totalDisplay) {
                        totalDisplay.textContent = '₹' + lineTotal.toLocaleString('en-IN', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                });

                const formattedGrand = '₹' + grandTotal.toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                const subtotalEl = document.getElementById('subtotalDisplay');
                const grandTotalEl = document.getElementById('grandTotalDisplay');

                if (subtotalEl) subtotalEl.textContent = formattedGrand;
                if (grandTotalEl) grandTotalEl.textContent = formattedGrand;
            }

            table.addEventListener('input', function (e) {
                if (e.target.classList.contains('item-rate') || e.target.classList.contains('item-qty')) {
                    calculateTotals();
                }
            });
        });
    </script>
@endpush

