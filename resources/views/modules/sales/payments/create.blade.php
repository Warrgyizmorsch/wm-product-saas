@extends('layouts.duralux')

@section('title', __('crm.record_customer_payment') . ' | SaaS ERP')
@section('page-title', __('crm.record_customer_payment'))
@section('breadcrumb', __('crm.sales') . ' / ' . __('crm.payments') . ' / ' . __('crm.record'))

@section('content')
    <div class="erp-single-panel bg-white">

        <form action="{{ route('sales.payments.store') }}" method="POST" id="paymentForm">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">{{ __('crm.record_customer_receipt') }}</h5>
                        <span class="fs-12 text-muted">{{ __('crm.record_customer_receipt_subtitle') }}</span>
                    </div>
                    <x-ui.button href="{{ route('sales.payments.index') }}" variant="light" size="sm"
                        class="border">{{ __('crm.cancel') }}</x-ui.button>
                </div>

                <div class="row g-4 mb-4 fs-13 text-dark">
                    <!-- Column 1: Customer & Payment Details -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" :label="__('crm.customer')" name="customer_id" id="customerSelect"
                            :required="true">
                            <option value="">{{ __('crm.select_customer') }}</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}" @selected(old('customer_id', $prefillCustomerId) == $c->id)>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" inputType="number" :label="__('crm.amount') . ' (' . active_currency_symbol() . ')'" name="amount" id="amountInput" :value="old('amount', $prefillAmount ? round(convert_from_base((float) $prefillAmount), 2) : '')" :required="true"
                            step="0.01" placeholder="0.00" style="font-weight: bold; color: #1e40af;" :errorText="$errors->first('amount')" />
                        <div id="amountDueErrorMsg" class="invalid-feedback d-block fs-11 mt-1 text-danger fw-semibold" style="display: none !important;">
                            <i class="feather-alert-circle me-1"></i><span id="amountDueErrorText">Payment amount cannot exceed the net balance due of {{ $prefillInvoice ? format_currency($prefillInvoice->balance_due) : '' }}.</span>
                        </div>
                        <div id="amountHelperText" class="form-text fs-11 text-muted mt-1" style="{{ $prefillInvoice ? '' : 'display: none !important;' }}">
                            <i class="feather-info me-1 text-info"></i>Max payable balance: <strong class="text-danger font-monospace" id="amountHelperTextValue">{{ $prefillInvoice ? format_currency($prefillInvoice->balance_due) : '' }}</strong>
                        </div>

                        <x-ui.odoo-form-ui type="select" :label="__('crm.payment_method')" name="payment_method"
                            :required="true">
                            <option value="Bank Transfer" @selected(old('payment_method') == 'Bank Transfer')>
                                {{ __('crm.method_bank_transfer') }}</option>
                            <option value="Cash" @selected(old('payment_method') == 'Cash')>{{ __('crm.method_cash') }}
                            </option>
                            <option value="Cheque" @selected(old('payment_method') == 'Cheque')>{{ __('crm.method_cheque') }}
                            </option>
                            <option value="UPI / QR" @selected(old('payment_method') == 'UPI / QR')>
                                {{ __('crm.method_upi_qr') }}</option>
                            <option value="Card" @selected(old('payment_method') == 'Card')>{{ __('crm.method_card') }}
                            </option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Deposited To" name="bank_account_id">
                            <option value="">Default bank account</option>
                            @foreach($bankAccounts as $bankAccount)
                                <option value="{{ $bankAccount->id }}" @selected((string) old('bank_account_id') === (string) $bankAccount->id)>{{ $bankAccount->code }} — {{ $bankAccount->name }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" :label="__('crm.bank_reference_txn_no')" name="reference_no"
                            :value="old('reference_no')" :placeholder="__('crm.bank_reference_placeholder')" />

                        {{-- TDS the customer deducted: settles the invoice along with the money received. --}}
                        <div class="row g-2">
                            <div class="col-7">
                                <x-ui.odoo-form-ui type="input" inputType="number" :label="'TDS deducted by customer (' . active_currency_symbol() . ')'" name="tds_amount" id="tdsAmountInput" :value="old('tds_amount', '')"
                                    step="0.01" min="0" placeholder="0.00" />
                            </div>
                            <div class="col-5">
                                <x-ui.odoo-form-ui type="select" label="TDS section" name="tds_section_code">
                                    <option value="">—</option>
                                    @foreach (['194C', '194J', '194H', '194I', '194Q', '194A', '194O'] as $tdsCode)
                                        <option value="{{ $tdsCode }}" @selected(old('tds_section_code') === $tdsCode)>
                                            {{ $tdsCode }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Date & Allocations -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('crm.payment_number')" name="payment_number"
                            :value="old('payment_number', $nextPaymentNumber)" :readonly="true" :required="true"
                            style="font-weight: bold;" />

                        <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.payment_date')" name="payment_date"
                            :value="old('payment_date', date('Y-m-d'))" :required="true" />

                        <x-ui.odoo-form-ui type="select" :label="__('crm.allocate_to')" name="allocate_to"
                            id="allocateToSelect">
                            <option value="unallocated" @selected(old('allocate_to') == 'unallocated')>
                                {{ __('crm.unallocated_advance') }}</option>
                            <option value="opening_balance" @selected(old('allocate_to') == 'opening_balance')>Against Opening
                                Balance (old dues)</option>
                            <option value="sales_order" @selected(old('allocate_to', $prefillSalesOrderId ? 'sales_order' : '') == 'sales_order')>{{ __('crm.sales_order_advance') }}</option>
                            <option value="invoice" @selected(old('allocate_to', $prefillInvoiceId ? 'invoice' : '') == 'invoice')>{{ __('crm.invoice_adjust') }}</option>
                        </x-ui.odoo-form-ui>

                        <!-- Dynamic Sales Orders List -->
                        <div class="allocation-group" id="salesOrderGroup" style="display: none;">
                            <x-ui.odoo-form-ui type="select" :label="__('crm.link_to_sales_order')" name="sales_order_id"
                                id="salesOrderSelect">
                                <option value="">{{ __('crm.select_sales_order') }}</option>
                                @foreach ($salesOrders as $so)
                                    <option value="{{ $so->id }}" data-customer="{{ $so->customer_id }}"
                                        data-total="{{ round(convert_from_base((float) $so->total_amount), 2) }}"
                                        @selected(old('sales_order_id', $prefillSalesOrderId) == $so->id)>
                                        {{ $so->sales_order_number }} ({{ __('crm.total') }}:
                                        {{ format_currency($so->total_amount) }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Dynamic Invoices List -->
                        <div class="allocation-group" id="invoiceGroup" style="display: none;">
                            <x-ui.odoo-form-ui type="select" :label="__('crm.link_to_invoice')" name="invoice_id"
                                id="invoiceSelect">
                                <option value="">{{ __('crm.select_invoice') }}</option>
                                @foreach ($invoices as $inv)
                                    <option value="{{ $inv->id }}"
                                        data-customer="{{ $inv->customer_id ?? $inv->salesOrder?->customer_id }}"
                                        data-balance="{{ round(convert_from_base((float) $inv->balance_due), 2) }}"
                                        data-balance-formatted="{{ format_currency($inv->balance_due) }}"
                                        @selected(old('invoice_id', $prefillInvoiceId) == $inv->id)>
                                        {{ $inv->invoice_number }} ({{ __('crm.balance_label') }}:
                                        {{ format_currency($inv->balance_due) }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="row g-4 mt-1 border-top pt-3 fs-13 text-dark">
                    <div class="col-md-12">
                        <x-ui.odoo-form-ui type="textarea" :label="__('crm.payment_notes')" name="notes" rows="2"
                            :placeholder="__('crm.payment_notes_placeholder')">{{ old('notes') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('sales.payments.index') }}" variant="light" size="md"
                        class="border py-2 px-4 fs-12 shadow-sm">{{ __('crm.discard') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="md" id="btnSubmitPayment" class="py-2 px-5 fw-bold fs-12 shadow-sm"
                        style="background-color: #1e40af; border-color: #1e40af;">{{ __('crm.save_confirm_payment') }}</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection


@push('scripts')
    <script>
        $(document).ready(function () {
            function toggleAllocationGroups() {
                const target = $('#allocateToSelect').val();
                $('.allocation-group').hide();

                if (target === 'sales_order') {
                    $('#salesOrderGroup').slideDown(150);
                } else if (target === 'invoice') {
                    $('#invoiceGroup').slideDown(150);
                }
            }

            function filterAllocationsByCustomer() {
                const customerId = $('#customerSelect').val();

                // Filter Sales Order options
                $('#salesOrderSelect option').each(function () {
                    const optionCust = $(this).attr('data-customer');
                    if (!customerId || !optionCust || optionCust == customerId) {
                        $(this).show();
                    } else {
                        $(this).hide();
                        if ($(this).is(':selected')) {
                            $('#salesOrderSelect').val('');
                        }
                    }
                });

                // Filter Invoice options
                $('#invoiceSelect option').each(function () {
                    const optionCust = $(this).attr('data-customer');
                    if (!customerId || !optionCust || optionCust == customerId) {
                        $(this).show();
                    } else {
                        $(this).hide();
                        if ($(this).is(':selected')) {
                            $('#invoiceSelect').val('');
                        }
                    }
                });
            }

            // Check due limit validation
            function checkDueLimit() {
                const allocateTo = $('#allocateToSelect').val();
                const amountInput = document.getElementById('amountInput');
                const tdsInput = document.getElementById('tdsAmountInput');
                const submitBtn = document.getElementById('btnSubmitPayment');
                const errorEl = document.getElementById('amountDueErrorMsg');
                const errorTextEl = document.getElementById('amountDueErrorText');
                const helperEl = document.getElementById('amountHelperText');
                const helperValEl = document.getElementById('amountHelperTextValue');

                if (allocateTo === 'invoice') {
                    const selected = $('#invoiceSelect option:selected');
                    const rawBalance = selected.data('balance');
                    const formattedBalance = selected.data('balance-formatted') || (rawBalance !== undefined ? '₹' + parseFloat(rawBalance).toFixed(2) : '');
                    const maxInvoiceDue = parseFloat(rawBalance);

                    if (!isNaN(maxInvoiceDue) && maxInvoiceDue > 0) {
                        if (helperValEl) helperValEl.textContent = formattedBalance;
                        if (errorTextEl) errorTextEl.textContent = "Payment amount cannot exceed the net balance due of " + formattedBalance + ".";
                        if (helperEl) helperEl.style.setProperty('display', 'block', 'important');

                        const amountVal = parseFloat(amountInput ? amountInput.value : 0) || 0;
                        const tdsVal = parseFloat(tdsInput ? tdsInput.value : 0) || 0;
                        const totalAttempted = amountVal + tdsVal;

                        if (amountVal > 0 && totalAttempted > (maxInvoiceDue + 0.001)) {
                            if (errorEl) errorEl.style.setProperty('display', 'block', 'important');
                            if (helperEl) helperEl.style.setProperty('display', 'none', 'important');
                            if (amountInput) {
                                amountInput.classList.add('is-invalid');
                                amountInput.style.borderBottom = '2px solid #dc3545';
                            }
                            if (submitBtn) {
                                submitBtn.disabled = true;
                                submitBtn.classList.add('disabled');
                                submitBtn.style.opacity = '0.5';
                                submitBtn.style.pointerEvents = 'none';
                            }
                            return false;
                        } else {
                            if (errorEl) errorEl.style.setProperty('display', 'none', 'important');
                            if (helperEl) helperEl.style.setProperty('display', 'block', 'important');
                            if (amountInput) {
                                amountInput.classList.remove('is-invalid');
                                amountInput.style.borderBottom = '';
                            }
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.classList.remove('disabled');
                                submitBtn.style.opacity = '1';
                                submitBtn.style.pointerEvents = 'auto';
                            }
                            return true;
                        }
                    }
                }

                // If not invoice allocation or no invoice chosen with valid balance
                if (errorEl) errorEl.style.setProperty('display', 'none', 'important');
                if (helperEl) helperEl.style.setProperty('display', 'none', 'important');
                if (amountInput) {
                    amountInput.classList.remove('is-invalid');
                    amountInput.style.borderBottom = '';
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('disabled');
                    submitBtn.style.opacity = '1';
                    submitBtn.style.pointerEvents = 'auto';
                }
                return true;
            }

            // Auto-fill amount from selected invoice's balance_due
            function syncAmountFromInvoice() {
                const selected = $('#invoiceSelect option:selected');
                const balance = selected.data('balance');
                if (balance !== undefined && balance > 0 && !$('#amountInput').val()) {
                    $('#amountInput').val(parseFloat(balance).toFixed(2));
                }
            }

            // Auto-fill amount from selected sales order's total
            function syncAmountFromSalesOrder() {
                const selected = $('#salesOrderSelect option:selected');
                const total = selected.data('total');
                if (total !== undefined && total > 0 && !$('#amountInput').val()) {
                    $('#amountInput').val(parseFloat(total).toFixed(2));
                }
            }

            // Auto-select customer when invoice is chosen
            function syncCustomerFromInvoice() {
                const selected = $('#invoiceSelect option:selected');
                const customerId = selected.data('customer');
                if (customerId && !$('#customerSelect').val()) {
                    $('#customerSelect').val(customerId);
                }
            }

            // Auto-select customer when sales order is chosen
            function syncCustomerFromSalesOrder() {
                const selected = $('#salesOrderSelect option:selected');
                const customerId = selected.data('customer');
                if (customerId && !$('#customerSelect').val()) {
                    $('#customerSelect').val(customerId);
                }
            }

            $('#allocateToSelect').on('change', function () {
                toggleAllocationGroups();
                checkDueLimit();
            });

            $('#customerSelect').on('change', function () {
                filterAllocationsByCustomer();
                checkDueLimit();
            });

            $('#invoiceSelect').on('change', function () {
                syncCustomerFromInvoice();
                syncAmountFromInvoice();
                filterAllocationsByCustomer();
                checkDueLimit();
            });

            $('#salesOrderSelect').on('change', function () {
                syncCustomerFromSalesOrder();
                syncAmountFromSalesOrder();
                filterAllocationsByCustomer();
                checkDueLimit();
            });

            $('#amountInput').on('input change keyup', checkDueLimit);
            $('#tdsAmountInput').on('input change keyup', checkDueLimit);

            $('#paymentForm').on('submit', function (e) {
                if (!checkDueLimit()) {
                    e.preventDefault();
                    return false;
                }
            });

            // On initial page load:
            // 1. If invoice or SO is pre-selected, sync customer & amount first
            if ($('#invoiceSelect').val()) {
                syncCustomerFromInvoice();
                syncAmountFromInvoice();
            } else if ($('#salesOrderSelect').val()) {
                syncCustomerFromSalesOrder();
                syncAmountFromSalesOrder();
            }

            // 2. Trigger allocation display & filtering
            toggleAllocationGroups();
            filterAllocationsByCustomer();
            checkDueLimit();
        });
    </script>
@endpush