@extends('layouts.duralux')

@section('title', 'Edit Purchase Request | SaaS ERP')
@section('page-title', 'Edit Purchase Request')
@section('breadcrumb')
    <a href="{{ route('purchase.requisitions.index') }}">Purchase Requests</a> &gt; Edit Details
@endsection

@push('styles')
    <!-- Select2 Theme Styles -->
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <!-- Professional Flat Form Sheet -->
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white">
                <form action="{{ route('purchase.requisitions.update', $requisition->id) }}" method="POST" id="editPrForm" class="odoo-sheet">
                    @csrf
                    @method('PUT')

                    <!-- Top buttons bar -->
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom flex-wrap gap-2">
                        <div>
                            <h4 class="fw-bold text-dark mb-0">Edit Purchase Request: {{ $requisition->requisition_number }}</h4>
                            <small class="text-muted fs-12">Modify the details of your draft purchase request.</small>
                        </div>
                        <div class="d-flex gap-2">
                            <x-ui.button href="{{ route('purchase.requisitions.show', $requisition->id) }}" variant="light" size="sm">
                                Cancel
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary" size="sm" icon="feather-save" style="background-color: #714B67; border-color: #714B67;">
                                Update Requisition
                            </x-ui.button>
                        </div>
                    </div>

                    <div class="row g-4 fs-13 text-dark">
                        <!-- Left: Metadata & Source Info -->
                        <div class="col-md-6 border-end">
                            <h6 class="fw-bold text-primary mb-3">Requisition Information</h6>

                            <x-ui.odoo-form-ui type="input" label="Requisition Date" name="requisition_date" inputType="date" :value="old('requisition_date', $requisition->requisition_date ? $requisition->requisition_date->format('Y-m-d') : '')" required="true" />
                            
                            <x-ui.odoo-form-ui type="select" label="Source Type" name="source_type" id="sourceTypeSelect" required="true">
                                <option value="direct" @selected(old('source_type', $requisition->source_type) === 'direct')>Direct / Manual</option>
                                <option value="so" @selected(old('source_type', $requisition->source_type) === 'so')>Sales Order</option>
                                <option value="mo" @selected(old('source_type', $requisition->source_type) === 'mo')>Manufacturing Order (MO)</option>
                                <option value="material_request" @selected(old('source_type', $requisition->source_type) === 'material_request')>Material Request (Prod)</option>
                                <option value="material_requirement" @selected(old('source_type', $requisition->source_type) === 'material_requirement')>Material Requirement (Store)</option>
                                <option value="requisition_slip" @selected(old('source_type', $requisition->source_type) === 'requisition_slip')>Requisition Slip</option>
                            </x-ui.odoo-form-ui>

                            <!-- Dynamic Source Reference Containers -->
                            <div class="source-ref-container" id="container-so" style="display: none;">
                                <x-ui.odoo-form-ui type="select" label="Sales Order Reference" name="sales_order_id">
                                    <option value="">Select Sales Order...</option>
                                    @foreach($salesOrders as $so)
                                        <option value="{{ $so->id }}" @selected(old('sales_order_id', ($requisition->source_type === 'so' ? $requisition->source_id : null)) == $so->id)>{{ $so->sales_order_number }} ({{ $so->customer?->name }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="source-ref-container" id="container-mo" style="display: none;">
                                <x-ui.odoo-form-ui type="select" label="Manufacturing Order Reference" name="production_order_id">
                                    <option value="">Select Production Order...</option>
                                    @foreach($productionOrders as $mo)
                                        <option value="{{ $mo->id }}" @selected(old('production_order_id', ($requisition->source_type === 'mo' ? $requisition->source_id : null)) == $mo->id)>{{ $mo->order_number }} ({{ $mo->product?->name }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="source-ref-container" id="container-material_request" style="display: none;">
                                <x-ui.odoo-form-ui type="select" label="Material Request Slip Reference" name="production_requisition_slip_id">
                                    <option value="">Select Material Request...</option>
                                    @foreach($materialRequests as $mr)
                                        <option value="{{ $mr->id }}" @selected(old('production_requisition_slip_id', ($requisition->source_type === 'material_request' ? $requisition->source_id : null)) == $mr->id)>{{ $mr->requisition_number }} (MO: {{ $mr->order?->order_number }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="source-ref-container" id="container-material_requirement" style="display: none;">
                                <x-ui.odoo-form-ui type="select" label="Material Requirement Reference" name="material_requirement_id">
                                    <option value="">Select Material Requirement...</option>
                                    @foreach($materialRequirements as $mreq)
                                        <option value="{{ $mreq->id }}" @selected(old('material_requirement_id', ($requisition->source_type === 'material_requirement' ? $requisition->source_id : null)) == $mreq->id)>{{ $mreq->requirement_number }} (SO: {{ $mreq->salesOrder?->sales_order_number }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="source-ref-container" id="container-requisition_slip" style="display: none;">
                                <x-ui.odoo-form-ui type="input" label="Requisition Slip Number" name="requisition_slip_number" :value="old('requisition_slip_number', $requisition->requisition_slip_number)" placeholder="e.g. SLIP-2026-98765" />
                            </div>
                        </div>

                        <!-- Right: Notes & Additional Info -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary mb-3">Additional Details</h6>
                            <x-ui.odoo-form-ui type="textarea" label="Notes" name="notes" rows="6" placeholder="Procurement reason, vendor recommendations, priority remarks, etc.">{{ old('notes', $requisition->notes) }}</x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <!-- Requisition Items Dynamic Section -->
                    <div class="mt-5">
                        <h5 class="fw-bold text-dark mb-3"><i class="feather-layers text-primary me-2"></i>Requisition Line Items</h5>
                        <div class="table-responsive">
                            <table class="odoo-table" id="prItemsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 40%">Product <span class="text-danger">*</span></th>
                                        <th style="width: 25%">Destination Warehouse</th>
                                        <th class="text-end" style="width: 15%">Quantity <span class="text-danger">*</span></th>
                                        <th class="text-end" style="width: 15%">Est. Cost (₹) <span class="text-danger">*</span></th>
                                        <th class="text-center" style="width: 5%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requisition->items as $index => $item)
                                        <tr class="item-row" data-index="{{ $index }}">
                                            <td>
                                                <x-ui.odoo-form-ui type="select" name="items[{{ $index }}][product_id]" required="true" class="product-select select2-simple">
                                                    <option value="">Select Product...</option>
                                                    @foreach($products as $p)
                                                        <option value="{{ $p->id }}" data-cost="{{ $p->unit_cost ?? 0.00 }}" @selected($item->product_id == $p->id)>{{ $p->name }} ({{ $p->sku ?: 'No SKU' }})</option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </td>
                                            <td>
                                                <x-ui.odoo-form-ui type="select" name="items[{{ $index }}][warehouse_id]" class="warehouse-select select2-simple">
                                                    <option value="">Select Warehouse...</option>
                                                    @foreach($warehouses as $w)
                                                        <option value="{{ $w->id }}" @selected($item->warehouse_id == $w->id)>{{ $w->name }}</option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </td>
                                            <td>
                                                <x-ui.odoo-form-ui type="input" name="items[{{ $index }}][quantity]" inputType="number" class="text-end qty-input" step="0.0001" min="0.0001" required="true" :value="(float)$item->quantity" placeholder="0.00" />
                                            </td>
                                            <td>
                                                <x-ui.odoo-form-ui type="input" name="items[{{ $index }}][estimated_cost]" inputType="number" class="text-end cost-input" step="0.01" min="0" required="true" :value="$item->estimated_cost" placeholder="0.00" />
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0"><i class="feather-trash-2 fs-14"></i></button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-sm btn-soft-primary px-3 fw-bold" id="addRowBtn">
                                <i class="feather-plus me-1"></i> Add Line
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <!-- Select2 Vendor Scripts -->
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            // Initialize select2
            function initSelect2(context) {
                $(context).find('.select2-simple').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }
            initSelect2(document);

            // Setup dynamic source reference dropdowns
            function toggleSourceRefs() {
                const srcType = $('#sourceTypeSelect').val();
                $('.source-ref-container').hide();

                if (srcType && srcType !== 'direct') {
                    $('#container-' + srcType).slideDown(150);
                }
            }

            $('#sourceTypeSelect').on('change', toggleSourceRefs);
            toggleSourceRefs(); // run on load

            // Handle product cost pre-filling
            $(document).on('change', '.product-select', function() {
                const option = this.options[this.selectedIndex];
                const cost = parseFloat($(option).attr('data-cost')) || 0.00;
                $(this).closest('tr').find('.cost-input').val(cost.toFixed(2));
            });

            // Dynamic items table rows
            let rowIdx = {{ $requisition->items->count() - 1 }};

            $('#addRowBtn').on('click', function() {
                rowIdx++;
                const newRow = `
                    <tr class="item-row" data-index="${rowIdx}">
                        <td>
                            <select name="items[${rowIdx}][product_id]" class="odoo-table-select product-select select2-simple" required>
                                <option value="">Select Product...</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}" data-cost="{{ $p->unit_cost ?? 0.00 }}">{{ $p->name }} ({{ $p->sku ?: 'No SKU' }})</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select name="items[${rowIdx}][warehouse_id]" class="odoo-table-select warehouse-select select2-simple">
                                <option value="">Select Warehouse...</option>
                                @foreach($warehouses as $w)
                                    <option value="{{ $w->id }}" @selected($w->is_default)>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" name="items[${rowIdx}][quantity]" class="odoo-table-input text-end qty-input" step="0.0001" min="0.0001" required placeholder="0.00">
                        </td>
                        <td>
                            <input type="number" name="items[${rowIdx}][estimated_cost]" class="odoo-table-input text-end cost-input" step="0.01" min="0" required placeholder="0.00">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0"><i class="feather-trash-2 fs-14"></i></button>
                        </td>
                    </tr>
                `;

                const $newTr = $(newRow);
                $('#prItemsTable tbody').append($newTr);
                initSelect2($newTr);
                updateRemoveRowButtons();
            });

            // Remove row button click
            $(document).on('click', '.remove-row-btn', function() {
                $(this).closest('tr').remove();
                updateRemoveRowButtons();
            });

            function updateRemoveRowButtons() {
                const rowCount = $('#prItemsTable tbody tr').length;
                if (rowCount <= 1) {
                    $('.remove-row-btn').prop('disabled', true);
                } else {
                    $('.remove-row-btn').prop('disabled', false);
                }
            }

            updateRemoveRowButtons(); // run on load
        });
    </script>
@endpush
