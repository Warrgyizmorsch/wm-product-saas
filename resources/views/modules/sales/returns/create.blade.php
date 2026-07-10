@extends('layouts.duralux')

@section('title', 'Process Sales Return | SaaS ERP')
@section('page-title', 'Create Sales Return')
@section('breadcrumb', 'Sales / Returns / Create')

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

        <form action="{{ route('sales.returns.store') }}" method="POST" id="returnForm">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Record Customer Return</h5>
                        <span class="fs-12 text-muted">Create a sales return to credit customer and return items back to inventory.</span>
                    </div>
                    <x-ui.button href="{{ route('sales.returns.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
                </div>

                <div class="row g-4 mb-4 fs-13 text-dark">
                    <!-- Column 1: Source Document -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="Sales Order Reference" name="sales_order_id" id="salesOrderSelect" :required="true">
                            <option value="">Select Sales Order...</option>
                            @foreach ($salesOrders as $so)
                                <option value="{{ $so->id }}" @selected(old('sales_order_id', $prefillSalesOrderId) == $so->id)>
                                    {{ $so->sales_order_number }} (Customer: {{ $so->customer?->name }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" label="Reason for Return" name="reason" :value="old('reason')" placeholder="e.g. Defective items, wrong size delivered..." />
                    </div>

                    <!-- Column 2: Date & Return Code -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Return Number" name="return_number" :value="old('return_number', $nextReturnNumber)" :readonly="true" :required="true" style="font-weight: bold;" />

                        <x-ui.odoo-form-ui type="input" inputType="date" label="Return Date" name="return_date" :value="old('return_date', date('Y-m-d'))" :required="true" />
                    </div>
                </div>

                <!-- Return Lines Table -->
                <div class="border-top pt-4 mt-4">
                    <h5 class="fw-bold text-dark mb-3 fs-14">Items to Return</h5>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="returnItemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">Product Details</th>
                                    <th style="width: 25%;">Restock Warehouse</th>
                                    <th class="text-end" style="width: 15%;">Return Qty</th>
                                    <th class="text-end pe-3" style="width: 25%;">Refund Unit Price</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3 fs-12">
                                        Please select a Sales Order to populate items.
                                    </td>
                                </tr>
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('sales.returns.index') }}" variant="light" size="md" class="border py-2 px-4 fs-12 shadow-sm">Discard</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="md" class="py-2 px-5 fw-bold fs-12 shadow-sm" style="background-color: #1e40af; border-color: #1e40af;">Save Return Draft</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection

@push('scripts')
    @php
        $salesOrdersData = $salesOrders->map(function($so) {
            return [
                'id' => $so->id,
                'items' => $so->items->map(function($item) {
                    return [
                        'product_id' => $item->product_id,
                        'item_name' => $item->item_name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'warehouse_id' => $item->warehouse_id,
                        'warehouse_name' => $item->warehouse?->name ?? 'Main Warehouse',
                    ];
                })->values()->toArray()
            ];
        })->values()->toArray();
    @endphp
    <script>
        $(document).ready(function() {
            // Load Sales Orders with eager loaded items and warehouse details
            const salesOrdersList = @json($salesOrdersData);

            $('#salesOrderSelect').on('change', function() {
                const soId = $(this).val();
                const tbody = $('#returnItemsTable tbody');
                tbody.empty();

                if (!soId) {
                    tbody.append('<tr><td colspan="4" class="text-center text-muted py-3 fs-12">Please select a Sales Order to populate items.</td></tr>');
                    return;
                }

                const selectedSo = salesOrdersList.find(so => so.id == soId);
                if (selectedSo && selectedSo.items.length > 0) {
                    selectedSo.items.forEach((item, index) => {
                        if (!item.product_id) return;
                        
                        const row = `
                            <tr>
                                <td>
                                    <strong class="text-dark">${item.item_name}</strong>
                                    <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                                </td>
                                <td>
                                    <span class="text-muted fw-semibold">${item.warehouse_name}</span>
                                    <input type="hidden" name="items[${index}][warehouse_id]" value="${item.warehouse_id}">
                                </td>
                                <td class="text-end">
                                    <input type="number" 
                                           name="items[${index}][quantity]" 
                                           class="odoo-table-input text-end fw-bold text-primary" 
                                           value="${item.quantity}" 
                                           min="1" 
                                           max="${item.quantity}" 
                                           required 
                                           style="width: 100px; margin-left: auto;">
                                </td>
                                <td class="text-end pe-3">
                                    <input type="number" 
                                           name="items[${index}][unit_price]" 
                                           class="odoo-table-input text-end text-muted" 
                                           value="${item.unit_price}" 
                                           min="0" 
                                           step="0.01" 
                                           required 
                                           style="width: 120px; margin-left: auto;">
                                </td>
                            </tr>
                        `;
                        tbody.append(row);
                    });
                } else {
                    tbody.append('<tr><td colspan="4" class="text-center text-muted py-3 fs-12">No products found on this Sales Order.</td></tr>');
                }
            });

            // Trigger change on load if prefilled
            if ($('#salesOrderSelect').val()) {
                $('#salesOrderSelect').trigger('change');
            }
        });
    </script>
@endpush
