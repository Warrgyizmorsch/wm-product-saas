@extends('layouts.duralux')

@section('title', 'Edit RFQ | SaaS ERP')
@section('page-title', 'Edit Request for Quotation')
@section('breadcrumb')
    <a href="{{ route('purchase.rfqs.index') }}">RFQs</a> &gt; Edit Details
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
                <form action="{{ route('purchase.rfqs.update', $rfq->id) }}" method="POST" id="editRfqForm" class="odoo-sheet">
                    @csrf
                    @method('PUT')

                    <!-- Top buttons bar -->
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom flex-wrap gap-2">
                        <div>
                            <h4 class="fw-bold text-dark mb-0">Edit RFQ: {{ $rfq->rfq_number }}</h4>
                            <small class="text-muted fs-12">Update draft quotation inquiry details.</small>
                        </div>
                        <div class="d-flex gap-2">
                            <x-ui.button href="{{ route('purchase.rfqs.show', $rfq->id) }}" variant="light" size="sm">
                                Cancel
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary" size="sm" icon="feather-save" style="background-color: #714B67; border-color: #714B67;">
                                Update RFQ
                            </x-ui.button>
                        </div>
                    </div>

                    <div class="row g-4 fs-13 text-dark">
                        <!-- Left Column -->
                        <div class="col-md-6 border-end">
                            <h6 class="fw-bold text-primary mb-3">General Information</h6>

                            <x-ui.odoo-form-ui type="select" label="Vendors / Suppliers" name="vendor_ids[]" required="true" multiple="true" class="select2-simple">
                                @foreach($vendors as $v)
                                    <option value="{{ $v->id }}" @selected(in_array($v->id, $linkedVendorIds))>{{ $v->name }} ({{ $v->code }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" label="RFQ Date" name="rfq_date" inputType="date" :value="old('rfq_date', $rfq->rfq_date ? $rfq->rfq_date->format('Y-m-d') : '')" required="true" />
                            
                            <x-ui.odoo-form-ui type="select" label="Source Requisition" name="purchase_requisition_id" id="requisitionSelect" class="select2-simple">
                                <option value="">Select Approved PR (Optional)...</option>
                                @foreach($requisitions as $pr)
                                    <option value="{{ $pr->id }}" @selected($rfq->purchase_requisition_id == $pr->id)>
                                        {{ $pr->requisition_number }} (Requested by: {{ $pr->requester?->name ?? '—' }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Right Column -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary mb-3">Additional Details</h6>
                            <x-ui.odoo-form-ui type="textarea" label="Notes" name="notes" rows="6" placeholder="Terms of delivery, special requests, remarks, etc.">{{ old('notes', $rfq->notes) }}</x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <!-- Line Items Section -->
                    <div class="mt-5">
                        <h5 class="fw-bold text-dark mb-3"><i class="feather-layers text-primary me-2"></i>Inquiry Line Items</h5>
                        <div class="table-responsive">
                            <table class="odoo-table" id="rfqItemsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 50%">Product <span class="text-danger">*</span></th>
                                        <th class="text-end" style="width: 20%">Quantity <span class="text-danger">*</span></th>
                                        <th class="text-end" style="width: 25%">Est. Cost (₹)</th>
                                        <th class="text-center" style="width: 5%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rfq->items as $index => $item)
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
                                                <x-ui.odoo-form-ui type="input" name="items[{{ $index }}][quantity]" inputType="number" class="text-end qty-input" step="0.0001" min="0.0001" required="true" :value="(float)$item->quantity" placeholder="0.00" />
                                            </td>
                                            <td>
                                                <x-ui.odoo-form-ui type="input" name="items[{{ $index }}][estimated_cost]" inputType="number" class="text-end cost-input" step="0.01" min="0" :value="$item->estimated_cost" placeholder="0.00" />
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

    <!-- Row Template for Dynamic Lines -->
    <template id="rowTemplate">
        <tr class="item-row" data-index="__INDEX__">
            <td>
                <select name="items[__INDEX__][product_id]" class="odoo-table-select product-select select2-simple" required>
                    <option value="">Select Product...</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" data-cost="{{ $p->unit_cost ?? 0.00 }}">{{ $p->name }} ({{ $p->sku ?: 'No SKU' }})</option>
                    @endforeach
                </select>
            </td>
            <td>
                <input type="number" name="items[__INDEX__][quantity]" class="odoo-table-input text-end qty-input" step="0.0001" min="0.0001" required placeholder="0.00">
            </td>
            <td>
                <input type="number" name="items[__INDEX__][estimated_cost]" class="odoo-table-input text-end cost-input" step="0.01" min="0" placeholder="0.00">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0"><i class="feather-trash-2 fs-14"></i></button>
            </td>
        </tr>
    </template>
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

            // Handle product cost pre-filling
            $(document).on('change', '.product-select', function() {
                const option = this.options[this.selectedIndex];
                const cost = parseFloat($(option).attr('data-cost')) || 0.00;
                $(this).closest('tr').find('.cost-input').val(cost.toFixed(2));
            });

            // Dynamic items table rows
            let rowIdx = {{ $rfq->items->count() }};

            function addRow(data = null) {
                let html = $('#rowTemplate').html();
                html = html.replace(/__INDEX__/g, rowIdx);
                
                const $newTr = $(html);
                $('#rfqItemsTable tbody').append($newTr);
                initSelect2($newTr);

                if (data) {
                    $newTr.find('.product-select').val(data.product_id).trigger('change.select2');
                    $newTr.find('.qty-input').val(data.quantity.toFixed(4));
                    $newTr.find('.cost-input').val(data.estimated_cost.toFixed(2));
                }

                rowIdx++;
                updateRemoveRowButtons();
            }

            $('#addRowBtn').on('click', function() {
                addRow();
            });

            // Requisition Change listener -> Pull items via AJAX
            $('#requisitionSelect').on('change', function() {
                const reqId = $(this).val();
                if (!reqId) {
                    return;
                }

                $.ajax({
                    url: '{{ route("purchase.rfqs.get-requisition-items") }}',
                    method: 'GET',
                    data: { requisition_id: reqId },
                    success: function(response) {
                        if (response.success) {
                            $('#rfqItemsTable tbody').empty();
                            rowIdx = 0;

                            if (response.items.length === 0) {
                                addRow();
                                return;
                            }

                            response.items.forEach(function(item) {
                                addRow(item);
                            });
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading PR items:', xhr);
                    }
                });
            });

            // Remove row button click
            $(document).on('click', '.remove-row-btn', function() {
                $(this).closest('tr').remove();
                updateRemoveRowButtons();
            });

            function updateRemoveRowButtons() {
                const rowCount = $('#rfqItemsTable tbody tr').length;
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
