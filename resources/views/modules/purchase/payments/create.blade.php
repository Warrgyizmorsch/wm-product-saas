@extends('layouts.duralux')

@section('title', __('purchase.register_vendor_payment') . ' | SaaS ERP')
@section('page-title', __('purchase.register_vendor_payment'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.vendor_payments') . ' / ' . __('purchase.register'))

@section('content')

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <form action="{{ route('purchase.payments.store') }}" method="POST" class="odoo-sheet">
            @csrf

            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1">{{ __('purchase.register_vendor_payment') }}</h5>
                    <small class="text-muted fs-11">{{ __('purchase.post_payment_ledger_help') }}</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('purchase.payments.index') }}" class="btn btn-light border fs-12">{{ __('purchase.cancel') }}</a>
                    <button type="submit" id="btnSubmitPayment" class="btn btn-success text-white fs-12 px-4 fw-semibold">
                        <i class="feather-check me-1.5"></i>{{ __('purchase.post_payment') }}
                    </button>
                </div>
            </div>

            @php
                $initialPayAmount = ($suggestedNetPayable > 0) ? $suggestedNetPayable : ($selectedBill?->due_amount ?? 0);
                $initialPayAmountFormatted = $initialPayAmount > 0 ? number_format(convert_from_base($initialPayAmount), 2, '.', '') : '';
            @endphp

            @if($selectedBill && ($availableAdvance ?? 0) > 0)
                <div class="alert alert-info border-info p-3 mb-4 rounded shadow-sm">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="feather-info text-info me-1.5"></i>{{ __('purchase.po_advance_payment_summary') }}
                        </h6>
                        <span class="badge bg-success text-white px-2.5 py-1 fs-11 fw-bold">{{ format_currency($availableAdvance) }} {{ __('purchase.advance_available') }}</span>
                    </div>
                    <div class="row g-2 text-dark fs-13">
                        <div class="col-md-4">
                            <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.total_bill_due') }}</span>
                            <strong class="font-monospace text-dark fs-14">{{ format_currency($selectedBill->due_amount) }}</strong>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted d-block fs-11 text-uppercase fw-bold text-success">{{ __('purchase.po_advance_paid') }}</span>
                            <strong class="font-monospace text-success fs-14">- {{ format_currency($availableAdvance) }}</strong>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted d-block fs-11 text-uppercase fw-bold text-primary">{{ __('purchase.suggested_net_bank_out') }}</span>
                            <strong class="font-monospace text-primary fs-15">{{ format_currency($suggestedNetPayable) }}</strong>
                        </div>
                    </div>
                    <div class="form-check mt-2 pt-2 border-top">
                        <input class="form-check-input" type="checkbox" name="auto_apply_advance" value="1" id="autoApplyAdvanceCheck" checked>
                        <label class="form-check-label fs-12 fw-semibold text-dark" for="autoApplyAdvanceCheck">
                            Auto-apply available advance credit ({{ format_currency($availableAdvance) }}) to settle remaining bill balance
                        </label>
                    </div>
                </div>
            @endif

            @if(!$selectedBill && isset($preselectedVendor) && $preselectedVendor && $suggestedNetPayable > 0)
                <div class="alert alert-warning border-warning p-3 mb-4 rounded shadow-sm">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-1">
                                <i class="feather-info text-warning me-1.5"></i>{{ __('purchase.opening_balance') }} Settlement
                            </h6>
                            <span class="fs-12 text-muted">Settling historical opening balance dues for <strong>{{ $preselectedVendor->name }}</strong> ({{ $preselectedVendor->code }}).</span>
                        </div>
                        <span class="badge bg-warning text-dark px-3 py-1.5 fs-12 fw-bold font-monospace border">
                            {{ format_currency($suggestedNetPayable) }} Pending Opening Balance
                        </span>
                    </div>
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    @if($selectedBill)
                        <x-ui.odoo-form-ui type="select" label="{{ __('purchase.supplier_vendor') }}" name="vendor_id_disabled" required="true" disabled="disabled">
                            @foreach($vendors as $vendor)
                                @if($selectedBill->vendor_id == $vendor->id)
                                    <option value="{{ $vendor->id }}" selected>{{ $vendor->name }}</option>
                                @endif
                            @endforeach
                        </x-ui.odoo-form-ui>
                        <input type="hidden" name="vendor_id" value="{{ $selectedBill->vendor_id }}">
                    @else
                        <x-ui.odoo-form-ui type="select" label="{{ __('purchase.supplier_vendor') }}" name="vendor_id" required="true" :errorText="$errors->first('vendor_id')">
                            <option value="">{{ __('purchase.select_vendor_placeholder') }}</option>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}" @selected(old('vendor_id', request('vendor_id')) == $vendor->id)>
                                    {{ $vendor->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    @endif
                </div>

                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('purchase.net_bank_outflow') }} ({{ active_currency_symbol() }})" name="amount" :value="old('amount', $initialPayAmountFormatted)" step="0.01" min="0.01" required="true" placeholder="{{ __('purchase.amount_placeholder') }}" :errorText="$errors->first('amount')" />
                    @if($selectedBill)
                        <div id="amountDueErrorMsg" class="invalid-feedback d-block fs-11 mt-1 text-danger fw-semibold" style="display: none !important;">
                            <i class="feather-alert-circle me-1"></i>Payment amount cannot exceed the net balance due of {{ format_currency($selectedBill->due_amount) }}.
                        </div>
                        <div id="amountHelperText" class="form-text fs-11 text-muted mt-1">
                            <i class="feather-info me-1 text-info"></i>Max payable balance: <strong class="text-danger font-monospace">{{ format_currency($selectedBill->due_amount) }}</strong>
                        </div>
                    @endif
                </div>

                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="{{ __('purchase.payment_method') }}" name="payment_method" required="true">
                        <option value="Bank Transfer" selected>{{ __('purchase.pay_method_bank_transfer_full') }}</option>
                        <option value="Cheque">{{ __('purchase.pay_method_cheque') }}</option>
                        <option value="Cash">{{ __('purchase.pay_method_cash') }}</option>
                        <option value="UPI">UPI</option>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="Paid From (Bank / Cash Account)" name="bank_account_id" id="bank_account_id" :errorText="$errors->first('bank_account_id')">
                        <option value="">Select Bank / Cash Account...</option>
                        @foreach($bankAccounts as $bankAccount)
                            <option value="{{ $bankAccount->id }}" data-code="{{ $bankAccount->code }}" @selected((string) old('bank_account_id') === (string) $bankAccount->id)>
                                {{ $bankAccount->code }} — {{ $bankAccount->name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('purchase.payment_date') }}" name="payment_date" :value="old('payment_date', date('Y-m-d'))" required="true" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="{{ __('purchase.reference_transaction_utr_no') }}" name="reference_number" placeholder="{{ __('purchase.reference_utr_placeholder') }}" />
                </div>
            </div>

            @if($selectedBill)
                <h6 class="fw-bold text-dark mb-2">{{ __('purchase.payment_allocation_to_bill') }}</h6>
                <div class="table-responsive rounded border mb-4">
                    <table class="table table-bordered align-middle fs-13 text-dark mb-0">
                        <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                            <tr>
                                <th class="ps-3">{{ __('purchase.bill_number') }}</th>
                                <th>{{ __('purchase.invoice_date') }}</th>
                                <th class="text-end">{{ __('purchase.grand_total') }}</th>
                                <th class="text-end">{{ __('purchase.outstanding_due') }}</th>
                                <th class="text-end pe-3" style="width: 200px;">{{ __('purchase.allocated_amount') }} ({{ active_currency_symbol() }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="ps-3 fw-bold text-primary">
                                    <input type="hidden" name="allocations[0][vendor_bill_id]" value="{{ $selectedBill->id }}">
                                    {{ $selectedBill->bill_number }}
                                </td>
                                <td>{{ $selectedBill->bill_date ? $selectedBill->bill_date->format('d-M-Y') : '—' }}</td>
                                <td class="text-end font-monospace">{{ format_currency($selectedBill->grand_total) }}</td>
                                <td class="text-end font-monospace text-danger fw-bold">{{ format_currency($selectedBill->due_amount) }}</td>
                                <td class="text-end pe-3">
                                    <input type="number" name="allocations[0][allocated_amount]" class="form-control form-control-sm text-end font-monospace fw-bold text-success" value="{{ old('allocations.0.allocated_amount', $initialPayAmountFormatted) }}" step="0.01" min="0.01" required>
                                    <div id="allocDueErrorMsg" class="invalid-feedback d-block fs-11 mt-1 text-danger fw-semibold" style="display: none !important;">
                                        <i class="feather-alert-circle me-1"></i>Exceeds net balance due of {{ format_currency($selectedBill->due_amount) }}.
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif

            <x-ui.odoo-form-ui type="textarea" label="{{ __('purchase.payment_remarks') }}" name="notes" placeholder="{{ __('purchase.enter_remarks') }}" rows="2" />

        </form>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const amountInput = document.querySelector('input[name="amount"]');
            const allocInput = document.querySelector('input[name="allocations[0][allocated_amount]"]');
            const submitBtn = document.getElementById('btnSubmitPayment');
            const errorEl = document.getElementById('amountDueErrorMsg');
            const helperEl = document.getElementById('amountHelperText');
            const allocErrorEl = document.getElementById('allocDueErrorMsg');

            @if($selectedBill)
                const maxBillDue = parseFloat("{{ number_format(convert_from_base($selectedBill->due_amount), 2, '.', '') }}");

                function checkDueLimit(val) {
                    const num = parseFloat(val);
                    if (!isNaN(num) && num > (maxBillDue + 0.001)) {
                        if (errorEl) errorEl.style.setProperty('display', 'block', 'important');
                        if (helperEl) helperEl.style.setProperty('display', 'none', 'important');
                        if (allocErrorEl) allocErrorEl.style.setProperty('display', 'block', 'important');
                        if (amountInput) {
                            amountInput.classList.add('is-invalid');
                            amountInput.style.borderBottom = '2px solid #dc3545';
                        }
                        if (allocInput) {
                            allocInput.classList.add('is-invalid');
                            allocInput.style.borderColor = '#dc3545';
                        }
                        if (submitBtn) {
                            submitBtn.disabled = true;
                            submitBtn.classList.add('disabled');
                            submitBtn.style.opacity = '0.5';
                            submitBtn.style.pointerEvents = 'none';
                        }
                    } else {
                        if (errorEl) errorEl.style.setProperty('display', 'none', 'important');
                        if (helperEl) helperEl.style.setProperty('display', 'block', 'important');
                        if (allocErrorEl) allocErrorEl.style.setProperty('display', 'none', 'important');
                        if (amountInput) {
                            amountInput.classList.remove('is-invalid');
                            amountInput.style.borderBottom = '';
                        }
                        if (allocInput) {
                            allocInput.classList.remove('is-invalid');
                            allocInput.style.borderColor = '';
                        }
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('disabled');
                            submitBtn.style.opacity = '1';
                            submitBtn.style.pointerEvents = 'auto';
                        }
                    }
                }
            @endif

            if (amountInput && allocInput) {
                if (amountInput.value && (!allocInput.value || parseFloat(allocInput.value) === 0)) {
                    allocInput.value = amountInput.value;
                } else if (allocInput.value && (!amountInput.value || parseFloat(amountInput.value) === 0)) {
                    amountInput.value = allocInput.value;
                }

                allocInput.addEventListener('input', function () {
                    amountInput.value = this.value;
                    @if($selectedBill)
                        checkDueLimit(this.value);
                    @endif
                });
                amountInput.addEventListener('input', function () {
                    allocInput.value = this.value;
                    @if($selectedBill)
                        checkDueLimit(this.value);
                    @endif
                });

                @if($selectedBill)
                    checkDueLimit(amountInput.value);
                @endif
            }

            const methodSelect = document.querySelector('select[name="payment_method"]');
            const bankSelect = document.querySelector('select[name="bank_account_id"]');
            if (methodSelect && bankSelect) {
                methodSelect.addEventListener('change', function () {
                    if (this.value === 'Cash') {
                        const cashOpt = Array.from(bankSelect.options).find(opt => opt.getAttribute('data-code') === '1010' || opt.text.toLowerCase().includes('cash'));
                        if (cashOpt) {
                            bankSelect.value = cashOpt.value;
                        }
                    }
                });
            }
        });
    </script>
@endpush

