@extends('layouts.duralux')

@section('title', 'Record Customer Payment | SaaS ERP')
@section('page-title', 'Record Customer Payment')
@section('breadcrumb', 'Sales / Payments / Record')

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <div class="avatar-text avatar-md bg-danger text-white me-3">
                        <i class="feather-alert-triangle"></i>
                    </div>
                    <div>
                        <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                        <ul class="fs-12 mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form action="{{ route('sales.payments.store') }}" method="POST" id="paymentForm">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Record Customer Receipt</h5>
                        <span class="fs-12 text-muted">Create payment vouchers and adjust against sales orders or invoices.</span>
                    </div>
                    <x-ui.button href="{{ route('sales.payments.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
                </div>

                <div class="row g-4 mb-4 fs-13 text-dark">
                    <!-- Column 1: Customer & Payment Details -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="Customer" name="customer_id" id="customerSelect" :required="true">
                            <option value="">Select Customer...</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}" @selected(old('customer_id', $prefillCustomerId) == $c->id)>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" inputType="number" label="Amount (₹)" name="amount" id="amountInput" :value="old('amount')" :required="true" step="0.01" placeholder="0.00" style="font-weight: bold; color: #1e40af;" />

                        <x-ui.odoo-form-ui type="select" label="Payment Method" name="payment_method" :required="true">
                            <option value="Bank Transfer" @selected(old('payment_method') == 'Bank Transfer')>Bank Transfer / Wire</option>
                            <option value="Cash" @selected(old('payment_method') == 'Cash')>Cash</option>
                            <option value="Cheque" @selected(old('payment_method') == 'Cheque')>Cheque</option>
                            <option value="UPI / QR" @selected(old('payment_method') == 'UPI / QR')>UPI / QR Code</option>
                            <option value="Card" @selected(old('payment_method') == 'Card')>Credit / Debit Card</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" label="Bank Reference / Txn No" name="reference_no" :value="old('reference_no')" placeholder="e.g. UTR number, Check number..." />
                    </div>

                    <!-- Column 2: Date & Allocations -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Payment Number" name="payment_number" :value="old('payment_number', $nextPaymentNumber)" :readonly="true" :required="true" style="font-weight: bold;" />

                        <x-ui.odoo-form-ui type="input" inputType="date" label="Payment Date" name="payment_date" :value="old('payment_date', date('Y-m-d'))" :required="true" />

                        <x-ui.odoo-form-ui type="select" label="Allocate To" name="allocate_to" id="allocateToSelect">
                            <option value="unallocated" @selected(old('allocate_to') == 'unallocated')>Keep Unallocated (Advance)</option>
                            <option value="sales_order" @selected(old('allocate_to', $prefillSalesOrderId ? 'sales_order' : '') == 'sales_order')>Sales Order (Advance Reservation)</option>
                            <option value="invoice" @selected(old('allocate_to', $prefillInvoiceId ? 'invoice' : '') == 'invoice')>Adjust Against Invoice</option>
                        </x-ui.odoo-form-ui>

                        <!-- Dynamic Sales Orders List -->
                        <div class="allocation-group" id="salesOrderGroup" style="display: none;">
                            <x-ui.odoo-form-ui type="select" label="Link to Sales Order" name="sales_order_id" id="salesOrderSelect">
                                <option value="">Select Sales Order...</option>
                                @foreach ($salesOrders as $so)
                                    <option value="{{ $so->id }}" data-customer="{{ $so->customer_id }}" @selected(old('sales_order_id', $prefillSalesOrderId) == $so->id)>
                                        {{ $so->sales_order_number }} (Total: ₹{{ number_format($so->total_amount, 2) }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Dynamic Invoices List -->
                        <div class="allocation-group" id="invoiceGroup" style="display: none;">
                            <x-ui.odoo-form-ui type="select" label="Link to Invoice" name="invoice_id" id="invoiceSelect">
                                <option value="">Select Invoice...</option>
                                @foreach ($invoices as $inv)
                                    <option value="{{ $inv->id }}" data-customer="{{ $inv->salesOrder->customer_id }}" @selected(old('invoice_id', $prefillInvoiceId) == $inv->id)>
                                        {{ $inv->invoice_number }} (Grand Total: ₹{{ number_format($inv->grand_total, 2) }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="row g-4 mt-1 border-top pt-3 fs-13 text-dark">
                    <div class="col-md-12">
                        <x-ui.odoo-form-ui type="textarea" label="Payment Notes" name="notes" rows="2" placeholder="Private internal comments or bank reference remarks...">{{ old('notes') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('sales.payments.index') }}" variant="light" size="md" class="border py-2 px-4 fs-12 shadow-sm">Discard</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="md" class="py-2 px-5 fw-bold fs-12 shadow-sm" style="background-color: #1e40af; border-color: #1e40af;">Save and Confirm Payment</x-ui.button>
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

            // Initial Triggers
            toggleAllocationGroups();
            filterAllocationsByCustomer();
        });
    </script>
@endpush
