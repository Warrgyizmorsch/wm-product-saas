@extends('layouts.duralux')

@section('title', 'Create Service Bill | SaaS ERP')
@section('page-title', 'Create Direct Service Bill')
@section('breadcrumb')
    <a href="{{ route('purchase.bills.index') }}">Vendor Bills</a> &gt; Create Service Bill
@endsection

@php
    $transportersGroup = $vendors->filter(fn($v) => $v->is_transporter);
    $suppliersGroup = $vendors->filter(fn($v) => !$v->is_transporter);
@endphp

@section('content')
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <form method="POST" action="{{ route('purchase.bills.store-service') }}" id="serviceBillForm" class="odoo-sheet">
            @csrf

            <!-- Title & Header Bar -->
            <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom flex-wrap gap-2">
                <div>
                    <small class="text-muted text-uppercase font-monospace fw-bold fs-11">Direct Logistics &amp; Expense Billing</small>
                    <h3 class="fw-bold text-dark mb-0">Create Direct Service Bill</h3>
                    <p class="text-muted fs-12 mb-0">Book standalone Freight, Logistics, Customs, or Handling Service Bills with optional GRN reference.</p>
                </div>

                <div class="d-flex gap-2">
                    <x-ui.button href="{{ route('purchase.bills.index') }}" variant="light" size="sm">
                        Cancel
                    </x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="sm" icon="feather-save">
                        Save Service Bill
                    </x-ui.button>
                </div>
            </div>

            <!-- Form Body Grid -->
            <div class="row g-4 fs-13 mb-4">
                <!-- Left Column: Source Selection & Vendor -->
                <div class="col-md-6 border-end pe-md-4">
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-user me-1"></i>Vendor &amp; Source Category</h6>

                    <!-- Freight Source Type Radio Buttons -->
                    <div class="mb-3 p-3 bg-light rounded border">
                        <label class="form-label fw-bold text-dark fs-12 mb-2 d-block">
                            <i class="feather-layers me-1 text-primary"></i>Freight Type / Source Category
                        </label>
                        <div class="d-flex align-items-center gap-4 flex-wrap">
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="radio" name="freight_source_type" id="typeOutbound" value="outbound" @checked(request('dispatch_order_id') || old('freight_source_type', 'outbound') === 'outbound') onchange="toggleFreightSource()">
                                <label class="form-check-label fw-semibold text-dark fs-13 pointer" for="typeOutbound">
                                    🚛 Outbound Freight (Sales Dispatch)
                                </label>
                            </div>
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="radio" name="freight_source_type" id="typeInbound" value="inbound" @checked(request('grn_id') || old('freight_source_type') === 'inbound') onchange="toggleFreightSource()">
                                <label class="form-check-label fw-semibold text-dark fs-13 pointer" for="typeInbound">
                                    🏭 Inbound Freight (Purchase GRN)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Source Dispatch Order Selection (Outbound) -->
                    <div id="dispatchWrapper" class="mb-3">
                        <x-ui.odoo-form-ui type="select" label="Source Sales Dispatch (Outward Freight Reference)" name="dispatch_order_id" id="dispatchSelect">
                            <option value="">-- Select Sales Dispatch Order --</option>
                            @if(isset($dispatches))
                                @foreach($dispatches as $d)
                                    <option value="{{ $d->id }}" @selected(old('dispatch_order_id', request('dispatch_order_id', $selectedDispatch?->id)) == $d->id)>
                                        Dispatch #{{ $d->dispatch_number ?? $d->id }} — {{ $d->transporter?->name ?? 'Transporter' }} (₹{{ number_format($d->freight_amount, 2) }})
                                    </option>
                                @endforeach
                            @endif
                        </x-ui.odoo-form-ui>
                        <small class="text-muted fs-11 d-block mt-1"><i class="feather-info me-1 text-info"></i>Links Freight Bill to Sales Dispatch Shipment.</small>
                    </div>

                    <!-- Source GRN Selection (Inbound) -->
                    <div id="grnWrapper" class="mb-3" style="display: none;">
                        <x-ui.odoo-form-ui type="select" label="Source GRN (Inward Freight Reference)" name="goods_receipt_note_id" id="grnSelect">
                            <option value="">-- Select Goods Receipt Note (GRN) --</option>
                            @foreach($grns as $g)
                                <option value="{{ $g->id }}" @selected(old('goods_receipt_note_id', $selectedGrn?->id) == $g->id)>
                                    {{ $g->grn_number }} — {{ $g->vendor?->name ?? 'Vendor' }} ({{ date('d-M-Y', strtotime($g->received_date)) }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        <small class="text-muted fs-11 d-block mt-1"><i class="feather-info me-1 text-info"></i>Links Freight Bill to Material Purchase GRN.</small>
                    </div>

                    <!-- Vendor Selection -->
                    <x-ui.odoo-form-ui type="select" label="Service Provider / Vendor" name="vendor_id" id="vendorSelect" required="true">
                        <option value="">Select Vendor / Transporter...</option>
                        @if($transportersGroup->isNotEmpty())
                            <optgroup label="🚛 Transporters &amp; Logistics">
                                @foreach($transportersGroup as $v)
                                    <option value="{{ $v->id }}" @selected(old('vendor_id', $prefilled['vendor_id'] ?? request('vendor_id')) == $v->id)>
                                        {{ $v->name }} (Transporter / Logistics)
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if($suppliersGroup->isNotEmpty())
                            <optgroup label="🏭 Material Suppliers &amp; Vendors">
                                @foreach($suppliersGroup as $v)
                                    <option value="{{ $v->id }}" @selected(old('vendor_id', $prefilled['vendor_id'] ?? request('vendor_id')) == $v->id)>
                                        {{ $v->name }} (Supplier / Vendor)
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="input" label="Vendor Invoice / Bilty No." name="vendor_invoice_number" :value="old('vendor_invoice_number', $prefilled['vendor_invoice_number'] ?? request('vendor_invoice_number'))" placeholder="e.g. LR-987654 / BL-2026" />
                </div>

                <!-- Right Column: Service Head & Amount -->
                <div class="col-md-6 ps-md-4">
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-sliders me-1"></i>Service &amp; Tax Calculation</h6>

                    <x-ui.odoo-form-ui type="select" label="Service Head / Type" name="service_head" id="serviceHeadSelect" required="true">
                        <option value="Freight & Transport" @selected(old('service_head', $prefilled['service_head'] ?? request('service_head', 'Freight & Transport')) === 'Freight & Transport')>Freight &amp; Transport Charges</option>
                        <option value="Customs Duty" @selected(old('service_head', $prefilled['service_head'] ?? request('service_head')) === 'Customs Duty')>Customs Duty &amp; Import Tariff</option>
                        <option value="Loading & Unloading" @selected(old('service_head', $prefilled['service_head'] ?? request('service_head')) === 'Loading & Unloading')>Loading &amp; Unloading Charges</option>
                        <option value="Handling Charges" @selected(old('service_head', $prefilled['service_head'] ?? request('service_head')) === 'Handling Charges')>Logistics &amp; Handling Charges</option>
                        <option value="Insurance" @selected(old('service_head', $prefilled['service_head'] ?? request('service_head')) === 'Insurance')>Transit Insurance Premium</option>
                        <option value="Other Service Charges" @selected(old('service_head', $prefilled['service_head'] ?? request('service_head')) === 'Other Service Charges')>Other Service Charges</option>
                    </x-ui.odoo-form-ui>

                    <div class="row g-2">
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="input" inputType="number" label="Service Amount (₹)" name="amount" id="serviceAmountInput" step="0.01" min="0.01" value="{{ old('amount', $prefilled['amount'] ?? request('amount', '')) }}" placeholder="0.00" required="true" />
                        </div>
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="select" label="GST Rate (%)" name="tax_rate" id="taxRateSelect" required="true">
                                <option value="0" @selected(old('tax_rate', $prefilled['tax_rate'] ?? request('tax_rate')) == 0)>0% (No Tax)</option>
                                <option value="5" @selected(old('tax_rate', $prefilled['tax_rate'] ?? request('tax_rate', 5)) == 5)>5% GST</option>
                                <option value="12" @selected(old('tax_rate', $prefilled['tax_rate'] ?? request('tax_rate')) == 12)>12% GST</option>
                                <option value="18" @selected(old('tax_rate', $prefilled['tax_rate'] ?? request('tax_rate')) == 18)>18% GST</option>
                                <option value="28" @selected(old('tax_rate', $prefilled['tax_rate'] ?? request('tax_rate')) == 28)>28% GST</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <x-ui.odoo-form-ui type="select" label="GST Mechanism" name="gst_type" id="gstTypeSelect" required="true">
                        <option value="cgst_sgst" @selected(old('gst_type', $prefilled['gst_type'] ?? request('gst_type')) === 'cgst_sgst')>Intra-State CGST + SGST (FCM)</option>
                        <option value="igst" @selected(old('gst_type', $prefilled['gst_type'] ?? request('gst_type')) === 'igst')>Inter-State IGST (FCM)</option>
                        <option value="rcm" @selected(old('gst_type', $prefilled['gst_type'] ?? request('gst_type', 'rcm')) === 'rcm')>RCM (Reverse Charge 5% Transporter RCM)</option>
                    </x-ui.odoo-form-ui>

                    <div class="row g-2">
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Bill Date" name="bill_date" :value="old('bill_date', date('Y-m-d'))" required="true" />
                        </div>
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Due Date" name="due_date" :value="old('due_date', date('Y-m-d', strtotime('+30 days')))" required="true" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes Section -->
            <div class="mt-3 pt-3 border-top">
                <x-ui.odoo-form-ui type="textarea" label="Notes / Remarks" name="notes" :value="old('notes', $prefilled['notes'] ?? request('notes'))" placeholder="Enter additional bill remarks, Bilty details or references..." rows="2" />
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
