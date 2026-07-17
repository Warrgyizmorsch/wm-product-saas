@extends('layouts.duralux')

@section('title', 'Generate Invoice | SaaS ERP')
@section('page-title', 'Generate Invoice')
@section('breadcrumb', 'Sales / Invoices / Generate')

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

        <form action="{{ route('sales.invoices.store') }}" method="POST" id="invoiceForm">
            @csrf
            <input type="hidden" name="sales_order_id" value="{{ $salesOrder->id }}">
            <input type="hidden" name="material_requirement_id" value="{{ $materialRequirement?->id }}">

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Generate Customer Invoice</h5>
                        @if ($materialRequirement)
                            <span class="fs-12 text-muted">Billing for Requirement: <span class="badge bg-soft-success text-success fw-bold px-2 py-0.5">{{ $materialRequirement->requirement_number }}</span> (SO: {{ $salesOrder->sales_order_number }})</span>
                        @else
                            <span class="fs-12 text-muted">Billing for Sales Order: <strong>{{ $salesOrder->sales_order_number }}</strong></span>
                        @endif
                    </div>
                    <x-ui.button href="{{ route('sales.orders.show', $salesOrder->id) }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
                </div>

                <div class="row g-4 mb-4 fs-13 text-dark">
                    <!-- Column 1: Client & Payment Terms -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Customer" name="_customer_display" :value="$salesOrder->customer?->name" readonly="true" style="font-weight: bold; background-color: #f8f9fa;" />

                        <x-ui.odoo-form-ui type="input" label="Payment Terms" name="_terms_display" :value="$salesOrder->payment_terms" readonly="true" style="background-color: #f8f9fa;" />

                        @if ($advanceAllocations > 0)
                            <div class="alert alert-info border-0 shadow-sm mt-3 py-2 px-3 fs-12 text-info">
                                <i class="feather-info me-2 fw-bold"></i>
                                Advance Paid on Sales Order: <strong>₹{{ number_format($advanceAllocations, 2) }}</strong>. This will be automatically adjusted upon generation!
                            </div>
                        @endif
                    </div>

                    <!-- Column 2: Meta Info -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Invoice Number" name="invoice_number" :value="old('invoice_number', $nextInvoiceNumber)" :required="true" style="font-weight: bold; color: #495057;" />

                        <x-ui.odoo-form-ui type="input" inputType="date" label="Invoice Date" name="invoice_date" :value="old('invoice_date', date('Y-m-d'))" :required="true" />

                        <x-ui.odoo-form-ui type="input" inputType="date" label="Due Date" name="due_date" :value="old('due_date', date('Y-m-d', strtotime('+15 days')))" />
                    </div>
                </div>

                <!-- Notes -->
                <div class="row g-4 mt-1 border-top pt-3 fs-13 text-dark">
                    <div class="col-md-12">
                        <x-ui.odoo-form-ui type="textarea" label="Invoice Notes / Terms" name="notes" rows="2" placeholder="e.g. Please wire payments to Bank details...">{{ old('notes') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Invoice Lines Table -->
                <div class="border-top pt-4 mt-4">
                    <h5 class="fw-bold text-dark mb-3 fs-14">Invoice Line Items</h5>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="invoiceItemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 30%;">Product Details</th>
                                    <th style="width: 20%;">Warehouse</th>
                                    <th class="text-end" style="width: 10%;">Qty</th>
                                    <th class="text-end" style="width: 13%;">Unit Price</th>
                                    <th class="text-end" style="width: 10%;">Tax Rate</th>
                                    <th class="text-end" style="width: 13%;">Discount</th>
                                    <th class="text-end pe-3" style="width: 14%;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @php
                                    $calcSubtotal = 0;
                                    $calcDiscount = 0;
                                    $calcTax = 0;
                                @endphp
                                @foreach ($invoiceItems as $index => $item)
                                    <tr>
                                        <td>
                                            <strong class="text-dark">{{ $item['product_name'] }}</strong>
                                            @if($item['sku'])
                                                <small class="text-muted d-block mt-0.5">SKU: {{ $item['sku'] }}</small>
                                            @endif
                                            <input type="hidden" name="items[{{ $index }}][sales_order_item_id]" value="{{ $item['sales_order_item_id'] }}">
                                            <input type="hidden" name="items[{{ $index }}][material_requirement_item_id]" value="{{ $item['material_requirement_item_id'] }}">
                                            <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item['product_id'] }}">
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-muted">{{ $item['warehouse_name'] ?: '—' }}</span>
                                            <input type="hidden" name="items[{{ $index }}][warehouse_id]" value="{{ $item['warehouse_id'] }}">
                                        </td>
                                        <td class="text-end">
                                            <span>{{ (int)$item['quantity'] }}</span>
                                            <input type="hidden" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] }}">
                                        </td>
                                        <td class="text-end">
                                            <span>₹{{ number_format($item['unit_price'], 2) }}</span>
                                            <input type="hidden" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] }}">
                                        </td>
                                        <td class="text-end text-muted">
                                            <span>{{ (int)$item['tax_rate'] }}%</span>
                                            <input type="hidden" name="items[{{ $index }}][tax_rate]" value="{{ $item['tax_rate'] }}">
                                        </td>
                                        <td class="text-end">
                                            <span>₹{{ number_format($item['discount'], 2) }}</span>
                                            <input type="hidden" name="items[{{ $index }}][discount]" value="{{ $item['discount'] }}">
                                        </td>
                                        <td class="text-end pe-3 fw-semibold">
                                            ₹{{ number_format($item['subtotal'], 2) }}
                                        </td>
                                    </tr>
                                    @php
                                        $calcSubtotal += $item['quantity'] * $item['unit_price'];
                                        $calcDiscount += $item['discount'];
                                        $calcTax += ($item['subtotal']) * ($item['tax_rate'] / 100);
                                    @endphp
                                @endforeach
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Calculation Summary Grid -->
                <div class="row mt-4 pt-3 border-top justify-content-end">
                    <div class="col-md-5">
                        <div class="bg-light p-3 rounded fs-13">
                            <div class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted">Subtotal:</span>
                                <span class="fw-bold text-dark">₹{{ number_format($calcSubtotal, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted">Discount:</span>
                                <span class="fw-bold text-danger">-₹{{ number_format($calcDiscount, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted">Tax total:</span>
                                <span class="fw-bold text-dark">₹{{ number_format($calcTax, 2) }}</span>
                            </div>
                            @php
                                $calcGrandTotal = $calcSubtotal - $calcDiscount + $calcTax;
                            @endphp
                            <div class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted">Grand Total:</span>
                                <span class="fw-extrabold text-primary fs-14">₹{{ number_format($calcGrandTotal, 2) }}</span>
                            </div>
                            @if ($advanceAllocations > 0)
                                <div class="d-flex justify-content-between py-1 border-bottom text-info">
                                    <span>Advance Adjusted:</span>
                                    <span class="fw-bold">-₹{{ number_format(min($advanceAllocations, $calcGrandTotal), 2) }}</span>
                                </div>
                                <div class="d-flex justify-content-between py-1.5 mt-1 bg-white px-2 rounded">
                                    <span class="fw-bold text-dark">Balance Due:</span>
                                    <span class="fw-extrabold text-success fs-14">₹{{ number_format(max(0, $calcGrandTotal - $advanceAllocations), 2) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('sales.orders.show', $salesOrder->id) }}" variant="light" size="md" class="border py-2 px-4 fs-12 shadow-sm">Discard</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="md" class="py-2 px-5 fw-bold fs-12 shadow-sm" style="background-color: #1e40af; border-color: #1e40af;">Generate and Save Invoice</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
