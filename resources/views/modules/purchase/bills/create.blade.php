@extends('layouts.duralux')

@section('title', __('purchase.create_vendor_bill') . ' | SaaS ERP')
@section('page-title', __('purchase.create_vendor_bill'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.vendor_bills') . ' / ' . __('purchase.create'))

@section('content')

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <form action="{{ route('purchase.bills.store') }}" method="POST" class="odoo-sheet">
            @csrf

            <input type="hidden" name="goods_receipt_note_id" value="{{ $selectedGrn->id }}">
            <input type="hidden" name="purchase_order_id" value="{{ $selectedGrn->purchase_order_id }}">

            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1">{{ __('purchase.create_bill_from_grn') }}</h5>
                    <span class="badge bg-soft-success text-success fs-12 fw-semibold">
                        <i class="feather-check-circle me-1"></i>{{ __('purchase.approved_grn') }}: {{ $selectedGrn->grn_number }} (PO: {{ $selectedGrn->purchaseOrder?->purchase_order_number ?: __('purchase.direct_grn') }})
                    </span>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('purchase.grns.show', $selectedGrn->id) }}" class="btn btn-light border fs-12">{{ __('purchase.cancel') }}</a>
                    <button type="submit" class="btn btn-primary fs-12 px-4 fw-semibold" style="background-color: var(--bs-primary); border-color: var(--bs-primary);">
                        <i class="feather-check me-1.5"></i>{{ __('purchase.post_bill_to_gl') }}
                    </button>
                </div>
            </div>

            @php
                $availAdv = max($availableAdvance ?? 0, $selectedGrn->purchaseOrder?->total_advance_paid ?? 0);
            @endphp

            @if($availAdv > 0)
                <div class="alert alert-info border-info p-3 mb-4 rounded shadow-sm">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <strong class="text-dark fs-13"><i class="feather-info text-info me-1.5"></i>Vendor Advance Credit Available:</strong>
                            <span class="text-success fw-bold font-monospace fs-14 ms-1">₹{{ number_format($availAdv, 2) }}</span>
                            <small class="text-muted d-block fs-11 mt-0.5">Check the toggle option to automatically apply and deduct advance credit on this bill.</small>
                        </div>
                        <div class="form-check form-switch fs-14 bg-white p-2 px-3 rounded border shadow-sm">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="use_vendor_advance" value="1" id="useVendorAdvance" checked style="cursor: pointer;">
                            <label class="form-check-label fw-bold text-dark" for="useVendorAdvance" style="cursor: pointer;">
                                Deduct Advance Credit (₹{{ number_format($availAdv, 2) }})
                            </label>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="{{ __('purchase.supplier_vendor') }}" name="vendor_id" required="true" :errorText="$errors->first('vendor_id')">
                        @foreach($vendors as $vendor)
                            <option value="{{ $vendor->id }}" @selected(old('vendor_id', $selectedGrn->vendor_id) == $vendor->id)>
                                {{ $vendor->name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="{{ __('purchase.vendor_invoice_bill_no') }}" name="vendor_invoice_number" :value="old('vendor_invoice_number', $selectedGrn->challan_number)" placeholder="e.g. INV-98765" :errorText="$errors->first('vendor_invoice_number')" />
                </div>

                <div class="col-md-2">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('purchase.bill_date') }}" name="bill_date" :value="old('bill_date', date('Y-m-d'))" required="true" :errorText="$errors->first('bill_date')" />
                </div>

                <div class="col-md-2">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('purchase.due_date') }}" name="due_date" :value="old('due_date', date('Y-m-d', strtotime('+30 days')))" required="true" :errorText="$errors->first('due_date')" />
                </div>
            </div>

            <h6 class="fw-bold text-dark mb-2">{{ __('purchase.grn_received_items_rates') }}</h6>
            <div class="table-responsive rounded border mb-4">
                <table class="table table-bordered align-middle fs-13 text-dark mb-0">
                    <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                        <tr>
                            <th class="ps-3" style="width: 40%;">{{ __('purchase.product') }}</th>
                            <th class="text-center" style="width: 15%;">{{ __('purchase.accepted_qty') }}</th>
                            <th class="text-end" style="width: 20%;">{{ __('purchase.unit_rate') }} (₹)</th>
                            <th class="text-end pe-3" style="width: 25%;">{{ __('purchase.line_total') }} (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($selectedGrn->items as $idx => $item)
                            <tr>
                                <td class="ps-3">
                                    <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $item->product_id }}">
                                    <input type="hidden" name="items[{{ $idx }}][goods_receipt_note_item_id]" value="{{ $item->id }}">
                                    <strong class="text-dark">{{ $item->product?->name }}</strong>
                                    @if($item->product?->sku)
                                        <small class="text-muted d-block">{{ __('purchase.sku') }}: {{ $item->product->sku }}</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <input type="number" name="items[{{ $idx }}][quantity]" class="form-control form-control-sm text-center font-monospace" value="{{ (float)$item->accepted_qty }}" step="0.001" min="0.001" required readonly style="background-color: #f8fafc;">
                                </td>
                                <td class="text-end">
                                    <input type="number" name="items[{{ $idx }}][unit_price]" class="form-control form-control-sm text-end font-monospace" value="{{ (float)$item->unit_rate }}" step="0.01" min="0" required>
                                </td>
                                <td class="text-end pe-3 font-monospace fw-bold text-success">
                                    ₹{{ number_format($item->accepted_qty * $item->unit_rate, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.odoo-form-ui type="textarea" label="{{ __('purchase.notes_terms') }}" name="notes" placeholder="{{ __('purchase.notes_placeholder') }}" rows="3" />

        </form>
    </div>

@endsection
