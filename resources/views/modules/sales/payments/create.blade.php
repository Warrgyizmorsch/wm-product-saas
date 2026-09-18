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
                    <x-ui.button href="{{ route('sales.payments.index') }}" variant="light" size="sm" class="border">{{ __('crm.cancel') }}</x-ui.button>
                </div>

                <div class="row g-4 mb-4 fs-13 text-dark">
                    <!-- Column 1: Customer & Payment Details -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" :label="__('crm.customer')" name="customer_id" id="customerSelect" :required="true">
                            <option value="">{{ __('crm.select_customer') }}</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}" @selected(old('customer_id', $prefillCustomerId) == $c->id)>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" inputType="number" :label="__('crm.amount')" name="amount" id="amountInput" :value="old('amount', $prefillAmount)" :required="true" step="0.01" placeholder="0.00" style="font-weight: bold; color: #1e40af;" />

                        <x-ui.odoo-form-ui type="select" :label="__('crm.payment_method')" name="payment_method" :required="true">
                            <option value="Bank Transfer" @selected(old('payment_method') == 'Bank Transfer')>{{ __('crm.method_bank_transfer') }}</option>
                            <option value="Cash" @selected(old('payment_method') == 'Cash')>{{ __('crm.method_cash') }}</option>
                            <option value="Cheque" @selected(old('payment_method') == 'Cheque')>{{ __('crm.method_cheque') }}</option>
                            <option value="UPI / QR" @selected(old('payment_method') == 'UPI / QR')>{{ __('crm.method_upi_qr') }}</option>
                            <option value="Card" @selected(old('payment_method') == 'Card')>{{ __('crm.method_card') }}</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" :label="__('crm.bank_reference_txn_no')" name="reference_no" :value="old('reference_no')" :placeholder="__('crm.bank_reference_placeholder')" />
                    </div>

                    <!-- Column 2: Date & Allocations -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('crm.payment_number')" name="payment_number" :value="old('payment_number', $nextPaymentNumber)" :readonly="true" :required="true" style="font-weight: bold;" />

                        <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.payment_date')" name="payment_date" :value="old('payment_date', date('Y-m-d'))" :required="true" />

                        <x-ui.odoo-form-ui type="select" :label="__('crm.allocate_to')" name="allocate_to" id="allocateToSelect">
                            <option value="unallocated" @selected(old('allocate_to') == 'unallocated')>{{ __('crm.unallocated_advance') }}</option>
                            <option value="sales_order" @selected(old('allocate_to', $prefillSalesOrderId ? 'sales_order' : '') == 'sales_order')>{{ __('crm.sales_order_advance') }}</option>
                            <option value="invoice" @selected(old('allocate_to', $prefillInvoiceId ? 'invoice' : '') == 'invoice')>{{ __('crm.invoice_adjust') }}</option>
                        </x-ui.odoo-form-ui>

                        <!-- Dynamic Sales Orders List -->
                        <div class="allocation-group" id="salesOrderGroup" style="display: none;">
                            <x-ui.odoo-form-ui type="select" :label="__('crm.link_to_sales_order')" name="sales_order_id" id="salesOrderSelect">
                                <option value="">{{ __('crm.select_sales_order') }}</option>
                                @foreach ($salesOrders as $so)
                                    <option value="{{ $so->id }}" data-customer="{{ $so->customer_id }}" @selected(old('sales_order_id', $prefillSalesOrderId) == $so->id)>
                                        {{ $so->sales_order_number }} ({{ __('crm.total') }}: {{ format_currency($so->total_amount) }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Dynamic Invoices List -->
                        <div class="allocation-group" id="invoiceGroup" style="display: none;">
                            <x-ui.odoo-form-ui type="select" :label="__('crm.link_to_invoice')" name="invoice_id" id="invoiceSelect">
                                <option value="">{{ __('crm.select_invoice') }}</option>
                                @foreach ($invoices as $inv)
                                    <option value="{{ $inv->id }}" data-customer="{{ $inv->customer_id ?? $inv->salesOrder?->customer_id }}" data-balance="{{ $inv->balance_due }}" @selected(old('invoice_id', $prefillInvoiceId) == $inv->id)>
                                        {{ $inv->invoice_number }} ({{ __('crm.balance_label') }}: {{ format_currency($inv->balance_due) }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="row g-4 mt-1 border-top pt-3 fs-13 text-dark">
                    <div class="col-md-12">
                        <x-ui.odoo-form-ui type="textarea" :label="__('crm.payment_notes')" name="notes" rows="2" :placeholder="__('crm.payment_notes_placeholder')">{{ old('notes') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('sales.payments.index') }}" variant="light" size="md" class="border py-2 px-4 fs-12 shadow-sm">{{ __('crm.discard') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="md" class="py-2 px-5 fw-bold fs-12 shadow-sm" style="background-color: #1e40af; border-color: #1e40af;">{{ __('crm.save_confirm_payment') }}</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection


@push('scripts')
    <script>
        $(document).ready(function() {
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
                $('#salesOrderSelect option').each(function() {
                    const optionCust = $(this).attr('data-customer');
                    if (!optionCust || optionCust == customerId) {
                        $(this).show();
                    } else {
                        $(this).hide();
                        if ($(this).is(':selected')) {
                            $('#salesOrderSelect').val('');
                        }
                    }
                });

                // Filter Invoice options
                $('#invoiceSelect option').each(function() {
                    const optionCust = $(this).attr('data-customer');
                    if (!optionCust || optionCust == customerId) {
                        $(this).show();
                    } else {
                        $(this).hide();
                        if ($(this).is(':selected')) {
                            $('#invoiceSelect').val('');
                        }
                    }
                });
            }

            $('#allocateToSelect').on('change', toggleAllocationGroups);
            $('#customerSelect').on('change', filterAllocationsByCustomer);

            // Initial Triggers — run toggle first so invoice/SO group is visible
            toggleAllocationGroups();
            filterAllocationsByCustomer();

            // Auto-fill amount from selected invoice's balance_due
            function syncAmountFromInvoice() {
                const selected = $('#invoiceSelect option:selected');
                const balance  = selected.data('balance');
                if (balance !== undefined && balance > 0) {
                    $('#amountInput').val(parseFloat(balance).toFixed(2));
                }
            }

            // Auto-select customer when invoice is chosen
            function syncCustomerFromInvoice() {
                const selected    = $('#invoiceSelect option:selected');
                const customerId  = selected.data('customer');
                if (customerId) {
                    $('#customerSelect').val(customerId).trigger('change');
                }
            }

            // Auto-select customer when sales order is chosen
            function syncCustomerFromSalesOrder() {
                const selected   = $('#salesOrderSelect option:selected');
                const customerId = selected.data('customer');
                if (customerId) {
                    $('#customerSelect').val(customerId).trigger('change');
                }
            }

            $('#invoiceSelect').on('change', function () {
                syncAmountFromInvoice();
                syncCustomerFromInvoice();
            });

            $('#salesOrderSelect').on('change', syncCustomerFromSalesOrder);

            // On page load if invoice/SO is pre-selected, sync customer & amount
            if ($('#invoiceSelect').val()) {
                syncCustomerFromInvoice();
                syncAmountFromInvoice();
            }
            if ($('#salesOrderSelect').val()) {
                syncCustomerFromSalesOrder();
            }
        });
    </script>
@endpush
