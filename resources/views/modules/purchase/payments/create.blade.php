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

            @php
                $inactiveVendorReasons = $inactiveVendorReasons ?? [];
                $openBankChanges = $openBankChanges ?? [];
                $initialVendorId = $selectedBill?->vendor_id ?? old('vendor_id', request('vendor_id'));
                $initialInactiveReason = $initialVendorId ? ($inactiveVendorReasons[$initialVendorId] ?? null) : null;
                $initialBankChange = $initialVendorId ? ($openBankChanges[$initialVendorId] ?? null) : null;
                $initialVendorModel = $vendors->firstWhere('id', $initialVendorId);
            @endphp

            <div id="inactiveVendorWarning" class="alert alert-warning border-warning p-3 mb-4 rounded shadow-sm" @if(!$initialInactiveReason) style="display: none;" @endif>
                <i class="feather-alert-triangle text-warning me-1.5"></i>
                <strong>This supplier is inactive (<span id="inactiveVendorReasonText">{{ $initialInactiveReason ?? '' }}</span>).</strong>
                You can still pay what is owed, but check that you meant to pay them.
            </div>

            {{-- FRS 2.3 / Task C-16: High-visibility warning when a bank change request is pending --}}
            <div id="pendingBankChangeWarning" class="alert alert-danger border-danger p-3 mb-4 rounded shadow-sm" @if(!$initialBankChange) style="display: none;" @endif>
                @if($initialBankChange)
                <div class="d-flex align-items-start gap-2">
                    <i class="feather-alert-octagon text-danger fs-18 mt-1 flex-shrink-0"></i>
                    <div class="w-100">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                            <h6 class="fw-bold text-danger mb-0">
                                <i class="feather-lock me-1"></i>Security Alert: Bank Change Request Pending Verification (<span id="bankChangeReqId">#{{ $initialBankChange['id'] }}</span>)
                            </h6>
                            <span class="badge bg-danger text-white px-2 py-0.5 fs-11">Unapproved Account</span>
                        </div>
                        <p class="mb-2 fs-12 text-dark">
                            A request to change this supplier's payout bank details to <strong><span id="bankChangeNewBank">{{ $initialBankChange['new_bank_name'] ?? '—' }}</span> (<span id="bankChangeNewAccount">{{ $initialBankChange['new_account_number'] ?? '—' }}</span>)</strong> is currently <strong>PENDING independent callback verification</strong>. The requested new bank details are <strong>NOT APPROVED</strong>.
                        </p>
                        <div class="alert alert-warning py-1.5 px-2.5 fs-11 mb-2 border-warning text-dark">
                            <i class="feather-shield text-warning me-1"></i>
                            <strong>Do NOT release RTGS/NEFT to any new or unconfirmed bank account.</strong> Payouts must only be disbursed to the verified active account on record, or held until the independent callback is confirmed.
                        </div>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="confirm_pending_bank_change" value="1" id="confirmPendingBankChange" @checked(old('confirm_pending_bank_change'))>
                            <label class="form-check-label fs-12 fw-bold text-danger" for="confirmPendingBankChange">
                                I acknowledge that a bank change request is pending and confirm this payout is strictly made to the VERIFIED active bank account on file.
                            </label>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Beneficiary Payout Details Card (Vendor Bank Master) --}}
            <div id="beneficiaryBankCard" class="card border rounded p-3 mb-4 bg-light shadow-sm" @if(!$initialVendorModel) style="display: none;" @endif>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <h6 class="fw-bold text-dark mb-0 fs-13">
                        <i class="feather-credit-card text-primary me-1.5"></i>Beneficiary Payout Details (Vendor Bank Master)
                    </h6>
                    <span id="beneficiaryBankBadge" class="badge {{ $initialVendorModel && $initialVendorModel->account_number ? 'bg-soft-success text-success' : 'bg-soft-warning text-warning' }} px-2.5 py-1 fs-11 fw-bold">
                        @if($initialVendorModel && $initialVendorModel->account_number)
                            <i class="feather-check-circle me-1"></i>Verified Active Account
                        @else
                            <i class="feather-alert-triangle me-1"></i>No Bank Account On File
                        @endif
                    </span>
                </div>
                <div class="row g-2 fs-12 text-dark">
                    <div class="col-md-4">
                        <span class="text-muted d-block fs-11 text-uppercase fw-semibold">Beneficiary Bank</span>
                        <strong id="beneficiaryBankName" class="text-dark fs-13">{{ $initialVendorModel?->bank_name ?: '—' }}</strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted d-block fs-11 text-uppercase fw-semibold">Account Number</span>
                        <strong id="beneficiaryAccountNumber" class="text-dark font-monospace fs-13">{{ $initialVendorModel?->account_number ?: '—' }}</strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted d-block fs-11 text-uppercase fw-semibold">IFSC / Swift Code</span>
                        <strong id="beneficiaryIfscCode" class="text-dark font-monospace fs-13">{{ $initialVendorModel?->ifsc_code ?: '—' }}</strong>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    @if($selectedBill)
                        <x-ui.odoo-form-ui type="select" label="{{ __('purchase.supplier_vendor') }}" name="vendor_id_disabled" required="true" disabled="disabled">
                            @foreach($vendors as $vendor)
                                @if($selectedBill->vendor_id == $vendor->id)
                                    @php
                                        $isInactiveVendor = isset($inactiveVendorReasons[$vendor->id]);
                                        $hasBankChange = isset($openBankChanges[$vendor->id]);
                                    @endphp
                                    <option value="{{ $vendor->id }}" selected
                                        data-bank-name="{{ $vendor->bank_name ?? '' }}"
                                        data-account-number="{{ $vendor->account_number ?? '' }}"
                                        data-ifsc-code="{{ $vendor->ifsc_code ?? '' }}"
                                        data-has-bank-change="{{ $hasBankChange ? '1' : '0' }}"
                                        data-bank-change-id="{{ $openBankChanges[$vendor->id]['id'] ?? '' }}"
                                        data-bank-change-new-bank="{{ $openBankChanges[$vendor->id]['new_bank_name'] ?? '' }}"
                                        data-bank-change-new-account="{{ $openBankChanges[$vendor->id]['new_account_number'] ?? '' }}"
                                        @if($isInactiveVendor) data-inactive="1" data-inactive-reason="{{ $inactiveVendorReasons[$vendor->id] }}" @endif>
                                        {{ $vendor->name }}{{ $isInactiveVendor ? ' (inactive)' : '' }}
                                    </option>
                                @endif
                            @endforeach
                        </x-ui.odoo-form-ui>
                        <input type="hidden" name="vendor_id" value="{{ $selectedBill->vendor_id }}">
                    @else
                        <x-ui.odoo-form-ui type="select" label="{{ __('purchase.supplier_vendor') }}" name="vendor_id" required="true" :errorText="$errors->first('vendor_id')">
                            <option value="">{{ __('purchase.select_vendor_placeholder') }}</option>
                            @foreach($vendors as $vendor)
                                @php
                                    $isInactiveVendor = isset($inactiveVendorReasons[$vendor->id]);
                                    $hasBankChange = isset($openBankChanges[$vendor->id]);
                                @endphp
                                <option value="{{ $vendor->id }}" @selected(old('vendor_id', request('vendor_id')) == $vendor->id)
                                    data-bank-name="{{ $vendor->bank_name ?? '' }}"
                                    data-account-number="{{ $vendor->account_number ?? '' }}"
                                    data-ifsc-code="{{ $vendor->ifsc_code ?? '' }}"
                                    data-has-bank-change="{{ $hasBankChange ? '1' : '0' }}"
                                    data-bank-change-id="{{ $openBankChanges[$vendor->id]['id'] ?? '' }}"
                                    data-bank-change-new-bank="{{ $openBankChanges[$vendor->id]['new_bank_name'] ?? '' }}"
                                    data-bank-change-new-account="{{ $openBankChanges[$vendor->id]['new_account_number'] ?? '' }}"
                                    @if($isInactiveVendor) data-inactive="1" data-inactive-reason="{{ $inactiveVendorReasons[$vendor->id] }}" @endif>
                                    {{ $vendor->name }}{{ $isInactiveVendor ? ' (inactive)' : '' }}{{ $hasBankChange ? ' ⚠️ [Bank Change Pending]' : '' }}
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

            const vendorSelect = document.querySelector('select[name="vendor_id"], select[name="vendor_id_disabled"]');
            const inactiveWarningEl = document.getElementById('inactiveVendorWarning');
            const inactiveReasonEl = document.getElementById('inactiveVendorReasonText');
            const bankChangeWarningEl = document.getElementById('pendingBankChangeWarning');
            const bankChangeReqIdEl = document.getElementById('bankChangeReqId');
            const bankChangeNewBankEl = document.getElementById('bankChangeNewBank');
            const bankChangeNewAccEl = document.getElementById('bankChangeNewAccount');
            const confirmBankChangeCheck = document.getElementById('confirmPendingBankChange');
            const beneficiaryCardEl = document.getElementById('beneficiaryBankCard');
            const beneficiaryBankNameEl = document.getElementById('beneficiaryBankName');
            const beneficiaryAccNumEl = document.getElementById('beneficiaryAccountNumber');
            const beneficiaryIfscEl = document.getElementById('beneficiaryIfscCode');
            const beneficiaryBadgeEl = document.getElementById('beneficiaryBankBadge');

            if (vendorSelect) {
                const syncVendorDetails = function () {
                    const opt = vendorSelect.options[vendorSelect.selectedIndex];
                    if (!opt || !opt.value) {
                        if (inactiveWarningEl) inactiveWarningEl.style.display = 'none';
                        if (bankChangeWarningEl) bankChangeWarningEl.style.display = 'none';
                        if (beneficiaryCardEl) beneficiaryCardEl.style.display = 'none';
                        return;
                    }

                    // 1. Inactive Warning
                    if (opt.getAttribute('data-inactive') === '1') {
                        if (inactiveReasonEl) {
                            inactiveReasonEl.textContent = opt.getAttribute('data-inactive-reason') || 'Not recorded';
                        }
                        if (inactiveWarningEl) inactiveWarningEl.style.display = 'block';
                    } else {
                        if (inactiveWarningEl) inactiveWarningEl.style.display = 'none';
                    }

                    // 2. Pending Bank Change Security Warning
                    const hasBankChange = opt.getAttribute('data-has-bank-change') === '1';
                    if (hasBankChange && bankChangeWarningEl) {
                        const reqId = opt.getAttribute('data-bank-change-id') || '';
                        const newBank = opt.getAttribute('data-bank-change-new-bank') || '—';
                        const newAcc = opt.getAttribute('data-bank-change-new-account') || '—';
                        if (!bankChangeWarningEl.innerHTML.trim() || !document.getElementById('confirmPendingBankChange')) {
                            bankChangeWarningEl.innerHTML = `
                                <div class="d-flex align-items-start gap-2">
                                    <i class="feather-alert-octagon text-danger fs-18 mt-1 flex-shrink-0"></i>
                                    <div class="w-100">
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                            <h6 class="fw-bold text-danger mb-0">
                                                <i class="feather-lock me-1"></i>Security Alert: Bank Change Request Pending Verification (<span id="bankChangeReqId">#${reqId}</span>)
                                            </h6>
                                            <span class="badge bg-danger text-white px-2 py-0.5 fs-11">Unapproved Account</span>
                                        </div>
                                        <p class="mb-2 fs-12 text-dark">
                                            A request to change this supplier's payout bank details to <strong><span id="bankChangeNewBank">${newBank}</span> (<span id="bankChangeNewAccount">${newAcc}</span>)</strong> is currently <strong>PENDING independent callback verification</strong>. The requested new bank details are <strong>NOT APPROVED</strong>.
                                        </p>
                                        <div class="alert alert-warning py-1.5 px-2.5 fs-11 mb-2 border-warning text-dark">
                                            <i class="feather-shield text-warning me-1"></i>
                                            <strong>Do NOT release RTGS/NEFT to any new or unconfirmed bank account.</strong> Payouts must only be disbursed to the verified active account on record, or held until the independent callback is confirmed.
                                        </div>
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" name="confirm_pending_bank_change" value="1" id="confirmPendingBankChange">
                                            <label class="form-check-label fs-12 fw-bold text-danger" for="confirmPendingBankChange">
                                                I acknowledge that a bank change request is pending and confirm this payout is strictly made to the VERIFIED active bank account on file.
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            const reqIdEl = document.getElementById('bankChangeReqId');
                            const newBankEl = document.getElementById('bankChangeNewBank');
                            const newAccEl = document.getElementById('bankChangeNewAccount');
                            if (reqIdEl) reqIdEl.textContent = '#' + reqId;
                            if (newBankEl) newBankEl.textContent = newBank;
                            if (newAccEl) newAccEl.textContent = newAcc;
                        }
                        bankChangeWarningEl.style.display = 'block';
                    } else if (bankChangeWarningEl) {
                        bankChangeWarningEl.style.display = 'none';
                        const checkEl = document.getElementById('confirmPendingBankChange');
                        if (checkEl) checkEl.checked = false;
                    }

                    // 3. Beneficiary Payout Bank Details
                    if (beneficiaryCardEl) {
                        const bankName = opt.getAttribute('data-bank-name') || '';
                        const accNum = opt.getAttribute('data-account-number') || '';
                        const ifsc = opt.getAttribute('data-ifsc-code') || '';

                        if (beneficiaryBankNameEl) beneficiaryBankNameEl.textContent = bankName || '—';
                        if (beneficiaryAccNumEl) beneficiaryAccNumEl.textContent = accNum || '—';
                        if (beneficiaryIfscEl) beneficiaryIfscEl.textContent = ifsc || '—';

                        if (beneficiaryBadgeEl) {
                            if (accNum) {
                                beneficiaryBadgeEl.className = 'badge bg-soft-success text-success px-2.5 py-1 fs-11 fw-bold';
                                beneficiaryBadgeEl.innerHTML = '<i class="feather-check-circle me-1"></i>Verified Active Account';
                            } else {
                                beneficiaryBadgeEl.className = 'badge bg-soft-warning text-warning px-2.5 py-1 fs-11 fw-bold';
                                beneficiaryBadgeEl.innerHTML = '<i class="feather-alert-triangle me-1"></i>No Bank Account On File';
                            }
                        }

                        beneficiaryCardEl.style.display = 'block';
                    }
                };

                vendorSelect.addEventListener('change', syncVendorDetails);
                syncVendorDetails();
            }

            // Guard payment submit if pending bank change warning is visible and not confirmed
            const paymentForm = document.querySelector('form.odoo-sheet');
            if (paymentForm) {
                paymentForm.addEventListener('submit', function (e) {
                    if (bankChangeWarningEl && bankChangeWarningEl.style.display !== 'none') {
                        if (confirmBankChangeCheck && !confirmBankChangeCheck.checked) {
                            e.preventDefault();
                            confirmBankChangeCheck.focus();
                            alert("Security Notice: A bank change request is pending for this vendor. Please check the confirmation box acknowledging that this payout is strictly being made to the verified active account on record, not the unapproved request.");
                        }
                    }
                });
            }
        });
    </script>
@endpush

