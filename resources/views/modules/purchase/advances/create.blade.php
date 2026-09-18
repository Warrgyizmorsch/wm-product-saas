@extends('layouts.duralux')

@section('title', __('purchase.record_advance_payment') . ' | SaaS ERP')
@section('page-title', __('purchase.record_advance_payment'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.advance_payments') . ' / ' . __('purchase.record_advance_payment'))

@section('content')

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <form action="{{ route('purchase.advances.store') }}" method="POST" class="odoo-sheet">
            @csrf

            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="feather-dollar-sign text-primary me-2"></i>{{ __('purchase.record_advance_payment') }}
                    </h5>
                    <small class="text-muted fs-12">{{ __('purchase.create_advance_help') }}</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('purchase.advances.index') }}" class="btn btn-light border fs-12">{{ __('purchase.cancel') }}</a>
                    <button type="submit" class="btn btn-primary text-white fs-12 px-4 fw-semibold shadow-sm">
                        <i class="feather-check me-1.5"></i>{{ __('purchase.save_advance_payment') }}
                    </button>
                </div>
            </div>

            @if($prefillPo)
                <div class="alert alert-info border-info p-3 mb-4 rounded shadow-sm">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-1">
                                <i class="feather-info text-info me-1.5"></i>{{ __('purchase.purchase_order') }}: {{ $prefillPo->order_number }}
                            </h6>
                            <span class="fs-12 text-muted">{{ __('purchase.supplier_vendor') }}: <strong class="text-dark">{{ $prefillPo->vendor?->name }}</strong></span>
                        </div>
                        <div class="text-end">
                            <span class="text-muted fs-11 text-uppercase fw-bold d-block">{{ __('purchase.total_amount') }}</span>
                            <strong class="font-monospace text-dark fs-15">{{ format_currency($prefillPo->grand_total ?? $prefillPo->total_amount ?? 0) }}</strong>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                        {{ __('purchase.supplier_vendor') }} <span class="text-danger">*</span>
                    </label>
                    @if($prefillPo && $prefillPo->vendor_id)
                        <x-ui.odoo-form-ui type="select" name="vendor_id_disabled" required="true" disabled="disabled">
                            @foreach($vendors as $vendor)
                                @if($prefillPo->vendor_id == $vendor->id)
                                    <option value="{{ $vendor->id }}" selected>{{ $vendor->name }}</option>
                                @endif
                            @endforeach
                        </x-ui.odoo-form-ui>
                        <input type="hidden" name="vendor_id" value="{{ $prefillPo->vendor_id }}">
                    @else
                        <x-ui.odoo-form-ui type="select" name="vendor_id" required="true" :errorText="$errors->first('vendor_id')">
                            <option value="">{{ __('purchase.select_vendor_placeholder') }}</option>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}" @selected(old('vendor_id') == $vendor->id)>
                                    {{ $vendor->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    @endif
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                        {{ __('purchase.linked_po') }}
                    </label>
                    <x-ui.odoo-form-ui type="select" name="purchase_order_id" :errorText="$errors->first('purchase_order_id')">
                        <option value="">{{ __('purchase.select_po_optional') }}</option>
                        @foreach($purchaseOrders as $po)
                            <option value="{{ $po->id }}" @selected(old('purchase_order_id', $prefillPo?->id) == $po->id)>
                                {{ $po->order_number }} - {{ $po->vendor?->name }} ({{ format_currency($po->grand_total ?? $po->total_amount ?? 0) }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                        {{ __('purchase.payment_date') }} <span class="text-danger">*</span>
                    </label>
                    <x-ui.odoo-form-ui type="input" inputType="date" name="payment_date" :value="old('payment_date', date('Y-m-d'))" required="true" :errorText="$errors->first('payment_date')" />
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                        {{ __('purchase.amount') }} ({{ active_currency_symbol() }}) <span class="text-danger">*</span>
                    </label>
                    <x-ui.odoo-form-ui type="input" inputType="number" name="amount" :value="old('amount')" step="0.01" min="0.01" required="true" placeholder="{{ __('purchase.amount_placeholder') }}" :errorText="$errors->first('amount')" />
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                        {{ __('purchase.payment_method') }} <span class="text-danger">*</span>
                    </label>
                    <x-ui.odoo-form-ui type="select" name="payment_method" required="true" :errorText="$errors->first('payment_method')">
                        <option value="Bank Transfer" @selected(old('payment_method', 'Bank Transfer') == 'Bank Transfer')>{{ __('purchase.pay_method_bank_transfer_full') }}</option>
                        <option value="UPI" @selected(old('payment_method') == 'UPI')>{{ __('purchase.pay_method_upi') }}</option>
                        <option value="Cash" @selected(old('payment_method') == 'Cash')>{{ __('purchase.pay_method_cash') }}</option>
                        <option value="Cheque" @selected(old('payment_method') == 'Cheque')>{{ __('purchase.pay_method_cheque') }}</option>
                        <option value="Credit Card" @selected(old('payment_method') == 'Credit Card')>{{ __('purchase.pay_method_credit_card') }}</option>
                    </x-ui.odoo-form-ui>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                        {{ __('purchase.reference_utr_no') }}
                    </label>
                    <x-ui.odoo-form-ui type="input" name="reference_number" :value="old('reference_number')" placeholder="{{ __('purchase.reference_utr_placeholder') }}" :errorText="$errors->first('reference_number')" />
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                        {{ __('purchase.notes') }}
                    </label>
                    <x-ui.odoo-form-ui type="textarea" name="notes" rows="3" placeholder="{{ __('purchase.enter_notes_optional') }}">{{ old('notes') }}</x-ui.odoo-form-ui>
                </div>
            </div>
        </form>
    </div>

@endsection
