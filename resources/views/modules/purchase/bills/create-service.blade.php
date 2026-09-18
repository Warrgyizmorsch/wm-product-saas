@extends('layouts.duralux')

@section('title', __('purchase.create_service_bill') . ' | SaaS ERP')
@section('page-title', __('purchase.create_direct_service_bill'))
@section('breadcrumb')
    <a href="{{ route('purchase.bills.index') }}">{{ __('purchase.vendor_bills') }}</a> &gt; {{ __('purchase.create_service_bill') }}
@endsection

@php
    $transportersGroup = $vendors->filter(fn($v) => $v->is_transporter);
    $suppliersGroup = $vendors->filter(fn($v) => !$v->is_transporter);
@endphp

@section('content')
    <div class="erp-single-panel">
        <form method="POST" action="{{ route('purchase.bills.store-service') }}" id="serviceBillForm" class="odoo-sheet">
            @csrf

            <!-- Title & Header Bar -->
            <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom flex-wrap gap-2">
                <div>
                    <small class="text-muted text-uppercase font-monospace fw-bold fs-11">{{ __('purchase.direct_logistics_expense_billing') }}</small>
                    <h3 class="fw-bold text-dark mb-0">{{ __('purchase.create_direct_service_bill') }}</h3>
                    <p class="text-muted fs-12 mb-0">{{ __('purchase.create_service_bill_help') }}</p>
                </div>
            </div>

            <!-- Form Body Grid -->
            <div class="row g-4 fs-13 mb-4">
                <!-- Left Column: Source Selection & Vendor -->
                <div class="col-md-6 border-end pe-md-4">
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-user me-1"></i>{{ __('purchase.vendor_source_category') }}</h6>

                    <!-- Freight Source Type Radio Buttons -->
                    <div class="mb-3 p-3 bg-light rounded border">
                        <label class="form-label fw-bold text-dark fs-12 mb-2 d-block">
                            <i class="feather-layers me-1 text-primary"></i>{{ __('purchase.freight_type_source_category') }}
                        </label>
                        <div class="d-flex align-items-center gap-4 flex-wrap">
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="radio" name="freight_source_type" id="typeOutbound" value="outbound" @checked(request('dispatch_order_id') || old('freight_source_type', 'outbound') === 'outbound') onchange="toggleFreightSource()">
                                <label class="form-check-label fw-semibold text-dark fs-13 pointer" for="typeOutbound">
                                    {{ __('purchase.outbound_freight_sales_dispatch') }}
                                </label>
                            </div>
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="radio" name="freight_source_type" id="typeInbound" value="inbound" @checked(request('grn_id') || old('freight_source_type') === 'inbound') onchange="toggleFreightSource()">
                                <label class="form-check-label fw-semibold text-dark fs-13 pointer" for="typeInbound">
                                    {{ __('purchase.inbound_freight_purchase_grn') }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Source Dispatch Order Selection (Outbound) -->
                    <div id="dispatchWrapper" class="mb-3">
                        <x-ui.odoo-form-ui type="select" :label="__('purchase.source_sales_dispatch_label')" name="dispatch_order_id" id="dispatchSelect">
                            <option value="">{{ __('purchase.select_sales_dispatch_order') }}</option>
                            @if(isset($dispatches))
                                @foreach($dispatches as $d)
                                    <option value="{{ $d->id }}" @selected(old('dispatch_order_id', request('dispatch_order_id', $selectedDispatch?->id)) == $d->id)>
                                        {{ __('purchase.dispatch') }} #{{ $d->dispatch_number ?? $d->id }} — {{ $d->transporter?->name ?? __('purchase.transporter') }} ({{ format_currency($d->freight_amount) }})
                                    </option>
                                @endforeach
                            @endif
                        </x-ui.odoo-form-ui>
                        <small class="text-muted fs-11 d-block mt-1"><i class="feather-info me-1 text-info"></i>{{ __('purchase.dispatch_help_text') }}</small>
                    </div>

                    <!-- Source GRN Selection (Inbound) -->
                    <div id="grnWrapper" class="mb-3" style="display: none;">
                        <x-ui.odoo-form-ui type="select" :label="__('purchase.source_grn_label')" name="goods_receipt_note_id" id="grnSelect">
                            <option value="">{{ __('purchase.select_grn_placeholder') }}</option>
                            @foreach($grns as $g)
                                <option value="{{ $g->id }}" @selected(old('goods_receipt_note_id', $selectedGrn?->id) == $g->id)>
                                    {{ $g->grn_number }} — {{ $g->vendor?->name ?? __('purchase.supplier_vendor') }} ({{ date('d-M-Y', strtotime($g->receipt_date ?? $g->received_date)) }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        <small class="text-muted fs-11 d-block mt-1"><i class="feather-info me-1 text-info"></i>{{ __('purchase.grn_help_text') }}</small>
                    </div>

                    <!-- Vendor Selection -->
                    <x-ui.odoo-form-ui type="select" :label="__('purchase.service_provider_vendor')" name="vendor_id" id="vendorSelect" :required="true">
                        <option value="">{{ __('purchase.select_vendor_transporter') }}</option>
                        @if($transportersGroup->isNotEmpty())
                            <optgroup label="{{ __('purchase.transporters_logistics') }}">
                                @foreach($transportersGroup as $v)
                                    <option value="{{ $v->id }}" @selected(old('vendor_id', $prefilled['vendor_id'] ?? request('vendor_id')) == $v->id)>
                                        {{ $v->name }} ({{ __('purchase.transporter_logistics_suffix') }})
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if($suppliersGroup->isNotEmpty())
                            <optgroup label="{{ __('purchase.material_suppliers_vendors') }}">
                                @foreach($suppliersGroup as $v)
                                    <option value="{{ $v->id }}" @selected(old('vendor_id', $prefilled['vendor_id'] ?? request('vendor_id')) == $v->id)>
                                        {{ $v->name }} ({{ __('purchase.supplier_vendor_suffix') }})
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="input" :label="__('purchase.vendor_invoice_bilty_no')" name="vendor_invoice_number" :value="old('vendor_invoice_number', $prefilled['vendor_invoice_number'] ?? request('vendor_invoice_number'))" :placeholder="__('purchase.vendor_invoice_bilty_placeholder')" />
                </div>

                <!-- Right Column: Service Head & Amount -->
                <div class="col-md-6 ps-md-4">
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-sliders me-1"></i>{{ __('purchase.service_tax_calculation') }}</h6>

                    <x-ui.odoo-form-ui type="select" :label="__('purchase.service_head_type')" name="service_head" id="serviceHeadSelect" :required="true">
                        <option value="Freight & Transport" @selected(old('service_head', $prefilled['service_head'] ?? request('service_head', 'Freight & Transport')) === 'Freight & Transport')>{{ __('purchase.service_head_freight') }}</option>
                        <option value="Customs Duty" @selected(old('service_head', $prefilled['service_head'] ?? request('service_head')) === 'Customs Duty')>{{ __('purchase.service_head_customs') }}</option>
                        <option value="Loading & Unloading" @selected(old('service_head', $prefilled['service_head'] ?? request('service_head')) === 'Loading & Unloading')>{{ __('purchase.service_head_loading') }}</option>
                        <option value="Handling Charges" @selected(old('service_head', $prefilled['service_head'] ?? request('service_head')) === 'Handling Charges')>{{ __('purchase.service_head_handling') }}</option>
                        <option value="Insurance" @selected(old('service_head', $prefilled['service_head'] ?? request('service_head')) === 'Insurance')>{{ __('purchase.service_head_insurance') }}</option>
                        <option value="Other Service Charges" @selected(old('service_head', $prefilled['service_head'] ?? request('service_head')) === 'Other Service Charges')>{{ __('purchase.service_head_other') }}</option>
                    </x-ui.odoo-form-ui>

                    <div class="row g-2">
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="input" inputType="number" :label="__('purchase.service_amount') . ' (' . active_currency_symbol() . ')'" name="amount" id="serviceAmountInput" step="0.01" min="0.01" value="{{ old('amount', $prefilled['amount'] ?? request('amount', '')) }}" placeholder="0.00" :required="true" />
                        </div>
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="select" :label="__('purchase.gst_rate')" name="tax_rate" id="taxRateSelect" :required="true">
                                <option value="0" @selected(old('tax_rate', $prefilled['tax_rate'] ?? request('tax_rate')) == 0)>{{ __('purchase.no_tax') }}</option>
                                <option value="5" @selected(old('tax_rate', $prefilled['tax_rate'] ?? request('tax_rate', 5)) == 5)>5% GST</option>
                                <option value="12" @selected(old('tax_rate', $prefilled['tax_rate'] ?? request('tax_rate')) == 12)>12% GST</option>
                                <option value="18" @selected(old('tax_rate', $prefilled['tax_rate'] ?? request('tax_rate')) == 18)>18% GST</option>
                                <option value="28" @selected(old('tax_rate', $prefilled['tax_rate'] ?? request('tax_rate')) == 28)>28% GST</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <x-ui.odoo-form-ui type="select" :label="__('purchase.gst_mechanism')" name="gst_type" id="gstTypeSelect" :required="true">
                        <option value="cgst_sgst" @selected(old('gst_type', $prefilled['gst_type'] ?? request('gst_type')) === 'cgst_sgst')>{{ __('purchase.gst_cgst_sgst') }}</option>
                        <option value="igst" @selected(old('gst_type', $prefilled['gst_type'] ?? request('gst_type')) === 'igst')>{{ __('purchase.gst_igst') }}</option>
                        <option value="rcm_cgst_sgst" @selected(old('gst_type', $prefilled['gst_type'] ?? request('gst_type', 'rcm')) === 'rcm_cgst_sgst' || old('gst_type', $prefilled['gst_type'] ?? request('gst_type', 'rcm')) === 'rcm')>{{ __('purchase.gst_rcm_cgst_sgst') }}</option>
                        <option value="rcm_igst" @selected(old('gst_type', $prefilled['gst_type'] ?? request('gst_type')) === 'rcm_igst')>{{ __('purchase.gst_rcm_igst') }}</option>
                    </x-ui.odoo-form-ui>

                    <div class="row g-2">
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" :label="__('purchase.bill_date')" name="bill_date" :value="old('bill_date', date('Y-m-d'))" :required="true" />
                        </div>
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" :label="__('purchase.due_date')" name="due_date" :value="old('due_date', date('Y-m-d', strtotime('+30 days')))" :required="true" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes Section -->
            <div class="mt-3 pt-3 border-top">
                <x-ui.odoo-form-ui type="textarea" :label="__('purchase.notes_remarks')" name="notes" :value="old('notes', $prefilled['notes'] ?? request('notes'))" :placeholder="__('purchase.notes_placeholder')" rows="2" />
            </div>

            <!-- Bottom Action Buttons (like Lead form) -->
            <div class="d-flex align-items-center justify-content-end gap-2 mt-4 pt-3 border-top">
                <x-ui.button href="{{ route('purchase.bills.index') }}" variant="light" class="border px-4 py-2 fs-13">
                    {{ __('purchase.cancel') }}
                </x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-save" class="px-4 py-2 fs-13 fw-bold shadow-sm">
                    {{ __('purchase.save_service_bill') }}
                </x-ui.button>
            </div>
        </form>
    </div>

<script>
function toggleFreightSource() {
    const isOutbound = document.getElementById('typeOutbound').checked;
    const dispatchWrap = document.getElementById('dispatchWrapper');
    const grnWrap      = document.getElementById('grnWrapper');

    if (isOutbound) {
        dispatchWrap.style.display = 'block';
        grnWrap.style.display      = 'none';
        if (document.getElementById('grnSelect')) document.getElementById('grnSelect').value = '';
    } else {
        dispatchWrap.style.display = 'none';
        grnWrap.style.display      = 'block';
        if (document.getElementById('dispatchSelect')) document.getElementById('dispatchSelect').value = '';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    toggleFreightSource();
});
</script>
@endsection
