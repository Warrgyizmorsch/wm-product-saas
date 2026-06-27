@php
    $isEdit = isset($quotation) && $quotation->exists;
@endphp

@extends('layouts.duralux')

@section('title', $isEdit ? 'Edit Quotation | SaaS ERP' : 'Create Quotation | SaaS ERP')
@section('page-title', $isEdit ? 'Edit Quotation' : 'Create Quotation')
@section('breadcrumb', $isEdit ? 'CRM / Quotations / Edit' : 'CRM / Quotations / Create')

@section('page-actions')
    @if($isEdit)
        <a href="{{ route('crm.quotations.show', $quotation->id) }}" class="btn btn-light btn-md">
            <i class="feather-arrow-left me-2"></i>Back to Details
        </a>
    @else
        <a href="{{ route('crm.quotations.index') }}" class="btn btn-light btn-md">
            <i class="feather-arrow-left me-2"></i>Back to List
        </a>
    @endif
@endsection

@section('content')
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

    <form action="{{ $isEdit ? route('crm.quotations.update', $quotation->id) : route('crm.quotations.store') }}" method="POST" id="quotationForm">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <!-- Zoho/Absolute ERP Flat Unified Document Canvas -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                
                <!-- Section 1: General Info -->
                <div class="mb-4">
                    <h6 class="fs-12 text-uppercase fw-bold text-primary mb-3">
                        <i class="feather-info me-2"></i>General Information
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <x-ui.input 
                                label="Quotation Number" 
                                name="quotation_number" 
                                value="{{ old('quotation_number', $isEdit ? $quotation->quotation_number : $nextQuotationNumber) }}" 
                                readonly 
                                class="bg-light fw-bold text-primary border-light-subtle" 
                            />
                        </div>
                        <div class="col-md-3">
                            <x-ui.select 
                                label="Select Customer" 
                                name="customer_id" 
                                data-select2-selector="default" 
                                required
                            >
                                <option value="">Select a Customer</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected(old('customer_id', $isEdit ? $quotation->customer_id : '') == $customer->id)>
                                        {{ $customer->name }} ({{ $customer->email ?: 'No Email' }})
                                    </option>
                                @endforeach
                            </x-ui.select>
                            @error('customer_id')
                                <div class="invalid-feedback d-block" style="margin-top: -10px; margin-bottom: 15px;">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <x-ui.select 
                                label="Sales Person" 
                                name="sales_person_id" 
                                data-select2-selector="default"
                            >
                                <option value="">Select Sales Person</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('sales_person_id', $isEdit ? $quotation->sales_person_id : '') == $user->id)>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                            @error('sales_person_id')
                                <div class="invalid-feedback d-block" style="margin-top: -10px; margin-bottom: 15px;">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <x-ui.select 
                                label="Status" 
                                name="status" 
                                required
                                class="fw-semibold"
                            >
                                <option value="Draft" @selected(old('status', $isEdit ? $quotation->status : 'Draft') === 'Draft')>Draft</option>
                                <option value="Sent" @selected(old('status', $isEdit ? $quotation->status : '') === 'Sent')>Sent</option>
                                <option value="Accepted" @selected(old('status', $isEdit ? $quotation->status : '') === 'Accepted')>Accepted</option>
                                <option value="Declined" @selected(old('status', $isEdit ? $quotation->status : '') === 'Declined')>Declined</option>
                            </x-ui.select>
                            @error('status')
                                <div class="invalid-feedback d-block" style="margin-top: -10px; margin-bottom: 15px;">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mt-0">
                            <x-ui.input 
                                type="date" 
                                label="Quotation Date" 
                                name="quotation_date" 
                                value="{{ old('quotation_date', $isEdit && $quotation->quotation_date ? $quotation->quotation_date->format('Y-m-d') : date('Y-m-d')) }}" 
                                required 
                            />
                            @error('quotation_date')
                                <div class="invalid-feedback d-block" style="margin-top: -10px; margin-bottom: 15px;">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mt-0">
                            <x-ui.input 
                                type="date" 
                                label="Expiry Date" 
                                name="expiry_date" 
                                value="{{ old('expiry_date', $isEdit && $quotation->expiry_date ? $quotation->expiry_date->format('Y-m-d') : '') }}" 
                            />
                            @error('expiry_date')
                                <div class="invalid-feedback d-block" style="margin-top: -10px; margin-bottom: 15px;">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <hr class="my-4 text-muted opacity-25">

                <!-- Section 2: Items Details -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fs-12 text-uppercase fw-bold text-primary mb-0">
                            <i class="feather-list me-2"></i>Line Items & Scope
                        </h6>
                        <button type="button" class="btn btn-xs btn-primary px-3 fw-bold" id="addItemRow">
                            <i class="feather-plus me-1"></i>Add Row
                        </button>
                    </div>
                    
                    <x-ui.table id="itemsTable" bordered hoverable>
                        <thead class="table-light fs-11 text-uppercase fw-bold text-muted">
                            <tr>
                                <th class="ps-3" style="width: 45%;">Item Name & Description <span class="text-danger">*</span></th>
                                <th style="width: 10%;">Qty <span class="text-danger">*</span></th>
                                <th style="width: 15%;">Rate (₹) <span class="text-danger">*</span></th>
                                <th style="width: 15%;">Tax (%)</th>
                                <th class="text-end pe-3" style="width: 15%;">Amount (₹)</th>
                                <th class="text-center" style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody class="fs-13">
                            <!-- Dynamic lines will render here -->
                        </tbody>
                    </x-ui.table>
                </div>

                <hr class="my-4 text-muted opacity-25">

                <!-- Section 3: Notes & Totals Summary -->
                <div class="row g-4">
                    <!-- Left: Notes & Conditions -->
                    <div class="col-lg-7">
                        <div class="mb-3">
                            <label class="form-label text-uppercase fs-11 fw-bold text-muted mb-1">Terms & Conditions</label>
                            <textarea name="terms_conditions" rows="3" class="form-control text-dark fs-13 border-light-subtle" placeholder="Specify any delivery, validation or payment conditions...">{{ old('terms_conditions', $isEdit ? $quotation->terms_conditions : '') }}</textarea>
                        </div>
                        <div>
                            <label class="form-label text-uppercase fs-11 fw-bold text-muted mb-1">Internal Notes</label>
                            <textarea name="notes" rows="3" class="form-control text-dark fs-13 border-light-subtle" placeholder="Internal remarks for the sales team...">{{ old('notes', $isEdit ? $quotation->notes : '') }}</textarea>
                        </div>
                    </div>
                    
                    <!-- Right: Summary Calculator -->
                    <div class="col-lg-5">
                        <div class="border border-light-subtle rounded p-3 bg-light-subtle">
                            <h6 class="fs-11 text-uppercase fw-bold text-muted mb-3">Calculated Summary</h6>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted fs-13">Subtotal:</span>
                                <span class="fw-bold text-dark fs-13" id="calcSubtotal">₹{{ $isEdit ? number_format($quotation->subtotal, 2) : '0.00' }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted fs-13">Tax total (GST):</span>
                                <span class="fw-bold text-dark fs-13" id="calcTax">₹{{ $isEdit ? number_format($quotation->tax, 2) : '0.00' }}</span>
                            </div>
                            
                            <div class="mb-3">
                                <x-ui.input 
                                    type="number" 
                                    label="Discount (₹)" 
                                    name="discount" 
                                    id="discountInput" 
                                    value="{{ old('discount', $isEdit ? $quotation->discount : 0) }}" 
                                    min="0" 
                                    step="0.01" 
                                    class="text-end fw-bold text-dark" 
                                />
                            </div>
                            
                            <hr class="my-3 text-muted opacity-25">
                            
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <span class="fs-6 fw-bold text-dark text-uppercase">Grand Total:</span>
                                <span class="fs-4 fw-bold text-primary" id="calcTotal">₹{{ $isEdit ? number_format($quotation->total_amount, 2) : '0.00' }}</span>
                            </div>

                            <button type="submit" class="btn btn-primary btn-md w-100 fw-bold py-2.5 shadow-sm">
                                <i class="feather-save me-2"></i>{{ $isEdit ? 'Update Quotation' : 'Save & Publish' }}
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </form>
@endsection

@push('styles')
    <!-- Select2 Stylesheet stack -->
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        #itemsTable tbody td {
            vertical-align: top !important;
            padding-top: 10px !important;
            padding-bottom: 10px !important;
        }
        #itemsTable .form-control:not(textarea) {
            height: 41px !important;
            padding: 6px 12px !important;
            font-size: 13px !important;
            margin-bottom: 4px !important;
            border: 1px solid #cbd5e1 !important;
        }
        #itemsTable textarea.form-control {
            min-height: 60px !important;
            margin-bottom: 6px !important;
            border: 1px solid #cbd5e1 !important;
        }
        #itemsTable .form-control:not(textarea):focus,
        #itemsTable textarea.form-control:focus {
            border-color: var(--bs-primary) !important;
            box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb), 0.2) !important;
            outline: 0 !important;
        }
        #itemsTable .amount-display {
            line-height: 41px;
        }
        #itemsTable .remove-row-btn {
            margin-top: 5px;
        }
    </style>
@endpush

@push('scripts')
    <!-- Select2 Script Stack & Custom javascript for Dynamic Rows and calculations -->
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            let rowIndex = 0;

            // Template for an item row
            function getRowHtml(index) {
                return `
                    <tr class="item-row" data-row-id="${index}">
                        <td class="ps-3">
                            <select name="items[${index}][item_name]" class="form-select erp-premium-select item-name-input" required>
                                <option value="">Select Item</option>
                                <option value="ERP Software License">ERP Software License</option>
                                <option value="Custom ERP Development">Custom ERP Development</option>
                                <option value="SaaS Annual Subscription">SaaS Annual Subscription</option>
                                <option value="CRM Integration Module">CRM Integration Module</option>
                                <option value="Database Migration Services">Database Migration Services</option>
                                <option value="IT Infrastructure Support">IT Infrastructure Support</option>
                                <option value="Training Workshop (per day)">Training Workshop (per day)</option>
                                <option value="Other">Other (Specify in details)</option>
                            </select>
                            <div class="description-container mt-2" id="desc-container-${index}" style="display: none;">
                                <textarea name="items[${index}][description]" class="form-control erp-premium-input" placeholder="Details / Scope of work..."></textarea>
                            </div>
                            <a href="javascript:void(0)" class="toggle-desc-btn text-primary fs-11 mt-1 d-inline-block" data-row-id="${index}">
                                <i class="feather-plus me-1"></i>Add Description
                            </a>
                        </td>
                        <td>
                            <input type="number" name="items[${index}][quantity]" class="form-control erp-premium-input text-end qty-input" value="1" min="1" required>
                        </td>
                        <td>
                            <input type="number" name="items[${index}][unit_price]" class="form-control erp-premium-input text-end price-input" value="0.00" min="0" step="0.01" required>
                        </td>
                        <td>
                            <input type="number" name="items[${index}][tax_rate]" class="form-control erp-premium-input text-end tax-input" value="18.00" min="0" max="100" step="0.01">
                        </td>
                        <td class="text-end fw-bold text-dark amount-display pe-3">
                            ₹0.00
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row-btn">
                                <i class="feather-trash-2"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }

            // Prefill existing items if in Edit mode
            const isEdit = @json($isEdit);
            const existingItems = isEdit ? @json($isEdit ? $quotation->items : []) : [];

            if (existingItems.length > 0) {
                existingItems.forEach(function(item) {
                    addRow(item);
                });
            } else {
                addRow();
            }

            // Add row action
            $('#addItemRow').on('click', function() {
                addRow();
            });

            // Toggle Description input visibility
            $(document).on('click', '.toggle-desc-btn', function(e) {
                e.preventDefault();
                const idx = $(this).data('row-id');
                const container = $('#desc-container-' + idx);
                if (container.is(':visible')) {
                    container.slideUp(120);
                    container.find('textarea').val('');
                    $(this).html('<i class="feather-plus me-1"></i>Add Description');
                } else {
                    container.slideDown(120);
                    $(this).html('<i class="feather-minus me-1"></i>Remove Description');
                }
            });

            // Remove row action
            $(document).on('click', '.remove-row-btn', function() {
                const rowsCount = $('.item-row').length;
                if (rowsCount > 1) {
                    $(this).closest('tr').remove();
                    calculateTotals();
                } else {
                    alert('You must include at least one item line in a quotation.');
                }
            });

            // Input listener for calculations
            $(document).on('input', '.qty-input, .price-input, .tax-input, #discountInput', function() {
                calculateTotals();
            });

            function addRow(item = null) {
                const newRow = $(getRowHtml(rowIndex));
                $('#itemsTable tbody').append(newRow);

                // Initialize select2 on the newly added select element
                newRow.find('.item-name-input').select2({
                    theme: "bootstrap-5",
                    width: "100%"
                });

                // Prefill if editing
                if (item) {
                    newRow.find('.item-name-input').val(item.item_name).trigger('change');
                    newRow.find('textarea').val(item.description || '');
                    if (item.description) {
                        $('#desc-container-' + rowIndex).show();
                        newRow.find('.toggle-desc-btn').html('<i class="feather-minus me-1"></i>Remove Description');
                    }
                    newRow.find('.qty-input').val(item.quantity);
                    newRow.find('.price-input').val(item.unit_price);
                    newRow.find('.tax-input').val(item.tax_rate);
                }

                rowIndex++;
                calculateTotals();
            }

            function calculateTotals() {
                let subtotal = 0;
                let taxTotal = 0;

                $('.item-row').each(function() {
                    const qty = parseInt($(this).find('.qty-input').val()) || 0;
                    const price = parseFloat($(this).find('.price-price-input, .price-input').val()) || 0;
                    const taxRate = parseFloat($(this).find('.tax-input').val()) || 0;

                    const amount = qty * price;
                    const tax = amount * (taxRate / 100);

                    subtotal += amount;
                    taxTotal += tax;

                    $(this).find('.amount-display').text('₹' + amount.toFixed(2));
                });

                const discount = parseFloat($('#discountInput').val()) || 0;
                const grandTotal = subtotal + taxTotal - discount;

                $('#calcSubtotal').text('₹' + subtotal.toFixed(2));
                $('#calcTax').text('₹' + taxTotal.toFixed(2));
                $('#calcTotal').text('₹' + Math.max(0, grandTotal).toFixed(2));
            }
        });
    </script>
@endpush
