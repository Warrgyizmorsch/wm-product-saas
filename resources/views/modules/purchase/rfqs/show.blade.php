@extends('layouts.duralux')

@section('title', "RFQ {$rfq->rfq_number} | SaaS ERP")
@section('page-title', "RFQ Details & Comparison Matrix")
@section('breadcrumb')
    <a href="{{ route('purchase.rfqs.index') }}">RFQs</a> &gt; {{ $rfq->rfq_number }}
@endsection

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('purchase.rfqs.index') }}" class="btn btn-light border">
            <i class="feather-arrow-left me-2"></i>Back to RFQs
        </a>

        @if($rfq->status === 'Draft')
            <a href="{{ route('purchase.rfqs.edit', $rfq->id) }}" class="btn btn-warning">
                <i class="feather-edit me-2"></i>Edit Draft
            </a>
            <form action="{{ route('purchase.rfqs.send', $rfq->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-info text-white">
                    <i class="feather-mail me-2"></i>Send RFQ to Vendors
                </button>
            </form>
        @endif

        @if($rfq->status === 'Received')
            <form action="{{ route('purchase.rfqs.confirm', $rfq->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary" style="background-color: #714B67; border-color: #714B67;">
                    <i class="feather-check-circle me-2"></i>Confirm & Finalize
                </button>
            </form>
        @endif

    </div>
@endsection

@section('content')
    @php
        $currency = tenant()?->settings['currency'] ?? 'INR';
    @endphp
    <div class="row text-dark">
        <div class="col-12">
            <!-- Toast Notifications -->
            @if (session('success'))
                <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
            @endif
            @if (session('error'))
                <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
            @endif

            <!-- Stage Header (Odoo Style) -->
            <div class="card border-0 shadow-sm mb-4 bg-white">
                <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <span class="fs-18 fw-bold text-dark me-3">{{ $rfq->rfq_number }}</span>
                        @php
                            $badgeClass = match($rfq->status) {
                                'Draft' => 'bg-soft-secondary text-secondary',
                                'Sent' => 'bg-soft-info text-info',
                                'Received' => 'bg-soft-warning text-warning',
                                'Confirmed' => 'bg-soft-success text-success',
                                'Cancelled' => 'bg-soft-danger text-danger',
                                default => 'bg-soft-dark text-dark',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }} px-3 py-1 fw-bold fs-12">{{ $rfq->status }}</span>
                    </div>

                    <!-- Steps Timeline -->
                    <div class="d-flex align-items-center gap-1 fs-12 font-monospace">
                        <span class="px-2 py-1 rounded {{ $rfq->status === 'Draft' ? 'bg-primary text-white fw-bold' : 'text-muted' }}">Draft</span>
                        <i class="feather-chevron-right text-muted"></i>
                        <span class="px-2 py-1 rounded {{ $rfq->status === 'Sent' ? 'bg-info text-white fw-bold' : 'text-muted' }}">Sent</span>
                        <i class="feather-chevron-right text-muted"></i>
                        <span class="px-2 py-1 rounded {{ $rfq->status === 'Received' ? 'bg-warning text-white fw-bold' : 'text-muted' }}">Rates Received</span>
                        <i class="feather-chevron-right text-muted"></i>
                        <span class="px-2 py-1 rounded {{ $rfq->status === 'Confirmed' ? 'bg-success text-white fw-bold' : 'text-muted' }}">Confirmed</span>
                    </div>
                </div>
            </div>

            <!-- Main RFQ Form Sheet -->
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white mb-4 odoo-sheet">
                <div class="row g-4 fs-13 pb-4 border-bottom">
                    <div class="col-md-6 border-end">
                        <h6 class="fw-bold text-primary mb-3">RFQ General Details</h6>
                        <x-ui.odoo-form-ui type="input" label="RFQ Date" name="rfq_date" :value="$rfq->rfq_date ? $rfq->rfq_date->format('d-M-Y') : '—'" readonly="true" />
                        <x-ui.odoo-form-ui type="input" label="Source Requisition" name="requisition" :value="$rfq->requisition ? $rfq->requisition->requisition_number : 'Direct Inquiry (No Link)'" readonly="true" />
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-primary mb-3">Auditing Details</h6>
                        <x-ui.odoo-form-ui type="input" label="Created By" name="created_by" :value="$rfq->creator?->name ?? 'System'" readonly="true" />
                        <x-ui.odoo-form-ui type="input" label="Created At" name="created_at" :value="$rfq->created_at->format('d-M-Y h:i A')" readonly="true" />
                    </div>
                </div>

                @if($rfq->notes)
                    <div class="mt-4 pt-2 mb-4">
                        <h6 class="fw-bold text-primary mb-2">Terms & Notes</h6>
                        <p class="text-secondary bg-light p-3 rounded fs-13 border mb-0" style="white-space: pre-line;">{{ $rfq->notes }}</p>
                    </div>
                @endif

                <!-- Absolute ERP Vendor Quotations Update Form -->
                <form action="{{ route('purchase.rfqs.save-comparison', $rfq->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="mt-5">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div>
                                <h5 class="fw-bold text-dark mb-0"><i class="feather-layers text-primary me-2"></i>Update Rate Supplier</h5>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold" style="background-color: #714B67; border-color: #714B67;">
                                    <i class="feather-save me-2"></i>Save Quotation Matrix
                                </button>
                            </div>
                        </div>

                        <style>
                            .rfq-matrix-table th, .rfq-matrix-table td {
                                border: 1px solid #e9ecef !important;
                            }
                            .rfq-matrix-table td {
                                background-color: #ffffff !important;
                            }
                        </style>
                        <div class="table-responsive rounded">
                            <table class="odoo-table rfq-matrix-table align-middle fs-12 text-dark mb-0" style="min-width: 1000px; width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 3%;" class="text-center align-middle">
                                            <input type="checkbox" id="select-all-items" class="form-check-input" title="Select All Items">
                                        </th>
                                        <th style="width: 4%;" class="text-center">S.No.</th>
                                        <th style="width: 23%;">Product Description</th>
                                        <th style="width: 8%;" class="text-center">Qty / UoM</th>
                                        <th style="width: 10%;" class="text-muted fw-bold text-end pe-2">Vendor Details</th>
                                        
                                        <!-- Loop each Vendor column -->
                                        @foreach($rfq->rfqVendors as $rv)
                                            <th class="text-center bg-soft-primary border-start font-weight-bold" style="width: 220px; min-width: 220px; max-width: 220px;">
                                                {{-- Supplier select radio --}}
                                                <div class="form-check d-flex align-items-center justify-content-center gap-1 mb-1">
                                                    <input type="radio"
                                                        class="form-check-input supplier-select-radio flex-shrink-0"
                                                        name="po_vendor"
                                                        id="vendor_radio_{{ $rv->id }}"
                                                        value="{{ $rv->id }}"
                                                        data-vendor-id="{{ $rv->id }}"
                                                        data-db-vendor-id="{{ $rv->vendor_id }}"
                                                        data-vendor-name="{{ $rv->vendor?->name }}"
                                                        data-quotation-number="{{ $rv->quotation_number }}"
                                                    >
                                                    <label class="form-check-label fw-bold text-primary fs-12 text-truncate c-pointer" for="vendor_radio_{{ $rv->id }}" style="max-width:160px;">
                                                        {{ $rv->vendor?->name }}
                                                    </label>
                                                </div>
                                                
                                                <!-- Copy Link & Open Portal Buttons -->
                                                <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                                                    <button type="button" class="btn btn-xs btn-outline-primary copy-portal-btn d-inline-flex align-items-center gap-0.5 py-2 px-2 fs-10" style="border-radius: 12px; font-weight: 500;" data-link="{{ route('purchase.rfqs.portal', $rv->token) }}">
                                                        <i class="feather-copy" style="font-size: 10px;"></i> Copy
                                                    </button>
                                                    <a href="{{ route('purchase.rfqs.portal', $rv->token) }}" target="_blank" class="btn btn-xs btn-outline-secondary d-inline-flex align-items-center gap-0.5 py-2 px-2 fs-10" style="border-radius: 12px; font-weight: 500;">
                                                        <i class="feather-external-link" style="font-size: 10px;"></i> Portal
                                                    </a>
                                                </div>
                                                
                                                @if($rv->status === 'Received')
                                                    <span class="badge bg-soft-success text-success fs-9 fw-bold">Submitted</span>
                                                @else
                                                    <span class="badge bg-soft-secondary text-secondary fs-9 fw-bold">Pending</span>
                                                @endif
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rfq->items as $index => $item)
                                        @php
                                            $expectedDate = $rfq->requisition?->requisition_date ? $rfq->requisition->requisition_date->format('d-M-Y') : $rfq->rfq_date->format('d-M-Y');
                                        @endphp
                                        <!-- Row 1: Rate/Unit -->
                                        <tr class="po-item-row"
                                            data-product-id="{{ $item->product_id }}"
                                            data-product-name="{{ $item->product?->name }}"
                                            data-req-qty="{{ (float)$item->quantity }}">
                                            <td rowspan="5" class="text-center align-middle bg-white">
                                                <input type="checkbox"
                                                    class="form-check-input item-select-cb"
                                                    data-product-id="{{ $item->product_id }}"
                                                    data-product-name="{{ $item->product?->name }}"
                                                    data-req-qty="{{ (float)$item->quantity }}"
                                                >
                                            </td>
                                            <td rowspan="5" class="text-center fw-semibold align-middle bg-white">{{ $index + 1 }}</td>
                                            <td rowspan="5" class="align-middle bg-white">
                                                <div class="fw-bold text-dark">{{ $item->product?->name }}</div>
                                                <div class="d-flex flex-column gap-0.5 mt-1">
                                                    <small class="text-muted font-monospace fs-10">SKU: {{ $item->product?->sku ?: '—' }}</small>
                                                    <small class="text-info fs-10 fw-semibold">
                                                        <i class="feather-calendar me-1"></i>Expected: {{ $expectedDate }}
                                                    </small>
                                                </div>
                                            </td>
                                            <td rowspan="5" class="text-center font-monospace fw-bold text-dark align-middle bg-white">
                                                {{ (float)$item->quantity }} <span class="text-muted small">({{ $item->product?->uom?->name ?? 'Pcs' }})</span>
                                            </td>
                                            <td class="text-end pe-2 fw-semibold text-muted fs-11 align-middle bg-white">Rate/Unit ({{ $currency }}):</td>
                                            
                                            @foreach($rfq->rfqVendors as $rv)
                                                @php
                                                    $quote = $rv->rates->firstWhere('product_id', $item->product_id);
                                                    $quotedRate = $quote ? (float)$quote->rate : '';
                                                @endphp
                                                <td class="border-start p-1 bg-white" style="width: 220px; min-width: 220px; max-width: 220px;">
                                                    <input type="number" name="vendor_quotes[{{ $rv->id }}][{{ $item->product_id }}][rate]" class="vendor-rate-input odoo-table-input text-end font-monospace py-1" style="font-size: 11px; background-color: transparent;" step="0.01" min="0" value="{{ $quotedRate }}" placeholder="0.00" data-vendor="{{ $rv->id }}" data-product="{{ $item->product_id }}">
                                                </td>
                                            @endforeach
                                        </tr>

                                        <!-- Row 2: Quoted Qty -->
                                        <tr>
                                            <td class="text-end pe-2 fw-semibold text-muted fs-11 align-middle bg-white">Quoted Qty:</td>
                                            @foreach($rfq->rfqVendors as $rv)
                                                @php
                                                    $quote = $rv->rates->firstWhere('product_id', $item->product_id);
                                                    $quotedQty = $quote ? (float)$quote->quantity : (float)$item->quantity;
                                                @endphp
                                                <td class="border-start p-1 bg-white" style="width: 220px; min-width: 220px; max-width: 220px;">
                                                    <input type="number" name="vendor_quotes[{{ $rv->id }}][{{ $item->product_id }}][quantity]" class="vendor-qty-input odoo-table-input text-end font-monospace py-1" style="font-size: 11px; background-color: transparent;" step="0.0001" min="0" value="{{ $quotedQty }}" placeholder="0.00" data-vendor="{{ $rv->id }}" data-product="{{ $item->product_id }}">
                                                </td>
                                            @endforeach
                                        </tr>

                                        <!-- Row 3: Total Amount -->
                                        <tr>
                                            <td class="text-end pe-2 fw-semibold text-muted fs-11 align-middle bg-white">Total Amount ({{ $currency }}):</td>
                                            @foreach($rfq->rfqVendors as $rv)
                                                @php
                                                    $quote = $rv->rates->firstWhere('product_id', $item->product_id);
                                                    $quotedRate = $quote ? (float)$quote->rate : 0;
                                                    $quotedQty = $quote ? (float)$quote->quantity : (float)$item->quantity;
                                                    $totalCost = $quotedRate * $quotedQty;
                                                @endphp
                                                <td class="border-start p-2 bg-white text-end align-middle font-monospace fw-bold text-success fs-11" style="width: 220px; min-width: 220px; max-width: 220px;">
                                                    <span class="vendor-currency-symbol font-monospace text-muted me-0.5">{{ $currency }}</span><span class="vendor-total-val" id="total_{{ $rv->id }}_{{ $item->product_id }}">{{ number_format($totalCost, 2, '.', '') }}</span>
                                                </td>
                                            @endforeach
                                        </tr>

                                        <!-- Row 4: Delivery Date -->
                                        <tr>
                                            <td class="text-end pe-2 fw-semibold text-muted fs-11 align-middle bg-white">Deliv Date:</td>
                                            @foreach($rfq->rfqVendors as $rv)
                                                @php
                                                    $quote = $rv->rates->firstWhere('product_id', $item->product_id);
                                                    $quotedDeliv = $quote && $quote->delivery_date ? $quote->delivery_date->format('Y-m-d') : '';
                                                @endphp
                                                <td class="border-start p-1 bg-white" style="width: 220px; min-width: 220px; max-width: 220px;">
                                                    <input type="date" name="vendor_quotes[{{ $rv->id }}][{{ $item->product_id }}][delivery_date]" class="odoo-table-input py-1" style="font-size: 11px; background-color: transparent;" value="{{ $quotedDeliv }}">
                                                </td>
                                            @endforeach
                                        </tr>

                                        <!-- Row 5: Validity Date -->
                                        <tr>
                                            <td class="text-end pe-2 fw-semibold text-muted fs-11 align-middle bg-white">Valid Date:</td>
                                            @foreach($rfq->rfqVendors as $rv)
                                                @php
                                                    $quote = $rv->rates->firstWhere('product_id', $item->product_id);
                                                    $quotedValid = $quote && $quote->validity_date ? $quote->validity_date->format('Y-m-d') : '';
                                                @endphp
                                                <td class="border-start p-1 bg-white" style="width: 220px; min-width: 220px; max-width: 220px;">
                                                    <input type="date" name="vendor_quotes[{{ $rv->id }}][{{ $item->product_id }}][validity_date]" class="odoo-table-input py-1" style="font-size: 11px; background-color: transparent;" value="{{ $quotedValid }}">
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach

                                    <!-- Document-Level Global Footers -->
                                    <tr>
                                        <td colspan="5" class="fw-bold text-end pe-2 text-muted fs-11 bg-white" style="vertical-align: middle;">Payment Type</td>
                                        @foreach($rfq->rfqVendors as $rv)
                                            <td class="text-center border-start p-1 bg-white" style="width: 220px; min-width: 220px; max-width: 220px;">
                                                <input type="hidden" name="vendors[{{ $rv->id }}][id]" value="{{ $rv->id }}">
                                                <select name="vendors[{{ $rv->id }}][payment_type]" class="odoo-table-select py-0.5" style="font-size: 11px; background-color: transparent;">
                                                    <option value="">Select...</option>
                                                    <option value="Cash" @selected($rv->payment_type === 'Cash')>Cash</option>
                                                    <option value="Net 30" @selected($rv->payment_type === 'Net 30')>Net 30</option>
                                                    <option value="Net 60" @selected($rv->payment_type === 'Net 60')>Net 60</option>
                                                    <option value="50% Advance, 50% Delivery" @selected($rv->payment_type === '50% Advance, 50% Delivery')>50% Advance, 50% Delivery</option>
                                                </select>
                                            </td>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="fw-bold text-end pe-2 text-muted fs-11 bg-white" style="vertical-align: middle;">Quotation No.</td>
                                        @foreach($rfq->rfqVendors as $rv)
                                            <td class="text-center border-start p-1 bg-white" style="width: 220px; min-width: 220px; max-width: 220px;">
                                                <input type="text" name="vendors[{{ $rv->id }}][quotation_number]" class="odoo-table-input py-0.5" style="font-size: 11px; background-color: transparent;" value="{{ $rv->quotation_number }}" placeholder="Ref Code">
                                            </td>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="fw-bold text-end pe-2 text-muted fs-11 bg-white" style="vertical-align: middle;">Terms & Conditions</td>
                                        @foreach($rfq->rfqVendors as $rv)
                                            <td class="text-center border-start p-1 bg-white" style="width: 220px; min-width: 220px; max-width: 220px;">
                                                <input type="text" name="vendors[{{ $rv->id }}][terms_conditions]" class="odoo-table-input py-0.5" style="font-size: 11px; background-color: transparent;" value="{{ $rv->terms_conditions }}" placeholder="T&C remarks">
                                            </td>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="fw-bold text-end pe-2 text-muted fs-11 bg-white" style="vertical-align: middle;">Attach File</td>
                                        @foreach($rfq->rfqVendors as $rv)
                                            <td class="text-center border-start p-1 bg-white" style="width: 220px; min-width: 220px; max-width: 220px;">
                                                <div class="d-flex flex-column gap-1 align-items-center">
                                                    <input type="file" name="vendors[{{ $rv->id }}][attachment]" class="odoo-table-input py-0.5" style="font-size: 11px; background-color: transparent;">
                                                    @if($rv->attachment_path)
                                                        <a href="{{ asset('storage/' . $rv->attachment_path) }}" target="_blank" class="text-success text-decoration-underline fw-bold small fs-10"><i class="feather-paperclip me-0.5"></i>Download</a>
                                                    @endif
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end mt-4 gap-3">
                            <a href="{{ route('purchase.rfqs.index') }}" class="btn btn-light px-4 py-2 border">Cancel</a>
                            <button type="submit" class="btn btn-primary px-5 py-2 fw-semibold" style="background-color: #714B67; border-color: #714B67;">
                                <i class="feather-save me-2"></i>Save Quotation Matrix
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===================== Fixed Bottom PO Action Bar ===================== --}}
    <div id="po-action-bar"
         style="display:none; position:fixed; bottom:0; left:0; right:0; z-index:1040;
                background:linear-gradient(135deg,#1a7a4a 0%,#00a76f 100%);
                box-shadow:0 -4px 24px rgba(0,0,0,0.18);
                padding:12px 24px;
                transition:transform 0.3s ease, opacity 0.3s ease;
                transform:translateY(100%); opacity:0;">
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-3">
                <i class="feather-shopping-cart text-white fs-18"></i>
                <div>
                    <div class="text-white fw-bold fs-14">Create Purchase Order</div>
                    <div class="text-white fs-12" style="opacity:0.85;">
                        <span id="po-bar-items">0</span> item(s) selected
                        &nbsp;&bull;&nbsp;
                        Supplier: <strong id="po-bar-supplier">None</strong>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button"
                        class="btn btn-outline-light btn-sm fw-semibold px-3"
                        onclick="clearPoSelection()">
                    <i class="feather-x me-1"></i>Clear
                </button>
                <button type="button"
                        id="btn-open-po-modal"
                        class="btn btn-white btn-sm fw-bold px-4"
                        style="background:#fff; color:#00a76f; border:none;"
                        data-bs-toggle="modal"
                        data-bs-target="#createPoModal">
                    <i class="feather-check-circle me-2"></i>Create PO
                    <span id="po-selection-count" class="badge ms-1" style="background:#00a76f; color:#fff;">0</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ===================== Create PO Modal ===================== --}}
    <x-ui.modal id="createPoModal" title="Create Purchase Order" size="xl" :centered="true" :showFooter="true" formAction="{{ route('purchase.rfqs.create-po', $rfq->id) }}" formMethod="POST">
        <input type="hidden" name="vendor_id" id="po-form-vendor-id" value="">
        <input type="hidden" name="source_type" value="rfq">
        <div id="po-form-items-inputs"></div>

        <div class="fs-13">

            {{-- Selected Supplier --}}
            <div class="mb-3">
                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-2">Selected Supplier</label>
                <div class="p-2 rounded border bg-soft-success">
                    <span class="fw-bold text-success fs-13">
                        <i class="feather-user me-1"></i><span id="po-supplier-name">None selected</span>
                    </span>
                </div>
            </div>

            <div class="row g-3 mb-3">
                {{-- Location / Warehouse --}}
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Location / Warehouse" name="location" id="po-location" required="true">
                        <option value="">Select Warehouse...</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->name }}">{{ $w->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                {{-- PO Date --}}
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="PO Date" name="date" id="po-date" :value="now()->format('Y-m-d')" required="true" />
                </div>
            </div>

            <div class="row g-3 mb-3">
                {{-- Delivery Date --}}
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Delivery Date" name="delivery_date" id="po-delivery-date" />
                </div>
                {{-- Reference --}}
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="Reference Document" name="reference" id="po-reference" :value="'RFQ: ' . $rfq->rfq_number" />
                </div>
                {{-- Supplier Quotation No --}}
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="Supplier Quotation No." name="supplier_quotation_number" id="po-supplier-quotation-number" placeholder="e.g. QU-9876..." />
                </div>
            </div>

            <div class="row g-3 mb-3">
                {{-- Discount Option --}}
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="Discount Option" name="discount_type" id="po-discount-type" required="true">
                        <option value="without_discount" selected>Without Discount</option>
                        <option value="item_wise">With Discount At Item Level</option>
                        <option value="order_wise">With Discount At Order Level</option>
                    </x-ui.odoo-form-ui>
                </div>
                {{-- Tax Option --}}
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="Tax Option" name="tax_type" id="po-tax-type" required="true">
                        <option value="without_tax" selected>Without Tax</option>
                        <option value="item_wise_tax">Item Wise Tax</option>
                        <option value="order_wise_tax">Order Wise Tax</option>
                    </x-ui.odoo-form-ui>
                </div>
                {{-- GST Type --}}
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="GST Type" name="gst_type" id="po-gst-type" required="true">
                        <option value="cgst_sgst" selected>CGST + SGST (Intra-State)</option>
                        <option value="igst">IGST (Inter-State)</option>
                    </x-ui.odoo-form-ui>
                </div>
            </div>


            {{-- Items preview table --}}
            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-2">Selected Items</label>
            <div class="table-responsive mb-3" style="max-height:280px; overflow-y:auto;">
                <x-ui.odoo-form-ui type="table" id="poItemsTableModal" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 4%;">#</th>
                            <th style="width: 25%;">Product</th>
                            <th class="text-end" style="width: 10%;">Qty <span class="text-danger">*</span></th>
                            <th class="text-end" style="width: 10%;">Rate <span class="text-danger">*</span></th>
                            <th class="text-end" style="width: 12%;">Amount</th>
                            <!-- Discount Columns -->
                            <th class="text-end discount-column" style="width: 8%;">Disc %</th>
                            <th class="text-end discount-column" style="width: 10%;">Disc Amt</th>
                            <!-- Tax Columns -->
                            <th class="text-end tax-column" style="width: 8%;">Tax %</th>
                            <th class="text-end tax-column" style="width: 10%;">Tax Amt</th>
                            <th class="text-end" style="width: 13%;">Total Amt</th>
                        </tr>
                    </thead>
                    <tbody id="po-preview-tbody">
                        <tr id="po-no-items-row">
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="feather-package fs-2 d-block mb-2 text-light"></i>
                                Tick items from the matrix
                            </td>
                        </tr>
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>

            <!-- Calculation Summary Grid -->
            <div class="row mb-3">
                <!-- Left side: Terms & Notes Editor -->
                <div class="col-md-7">
                    <x-ui.odoo-form-ui type="editor" label="Terms & Notes" name="notes" id="po-notes" placeholder="Specify any delivery terms, quality checks, payment instructions, etc.">
                    </x-ui.odoo-form-ui>
                </div>

                <!-- Right side: Calculation Card -->
                <div class="col-md-5 d-flex flex-column align-items-end fs-13">
                    <div class="card border-0 shadow-sm w-100" style="max-width: 380px; background: #ffffff; border-radius: 8px; border: 1px solid #cbd5e1 !important; overflow: hidden;">
                        <div class="fw-bold py-2 px-3 text-white" style="background-color: #2563eb; font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase;">
                            ORDER SUMMARY
                        </div>
                        <div class="p-3 bg-white text-dark">
                            <!-- Taxable Subtotal -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted fs-12 fw-semibold">Taxable Subtotal</span>
                                <input type="text" id="summarySubtotalTextModal" class="form-control form-control-sm text-end fw-bold" style="width: 140px; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; background-color: #f8fafc;" readonly value="0.00">
                                <input type="hidden" name="subtotal" id="summarySubtotalModal" value="0.00">
                            </div>

                            <!-- Total Discount -->
                            <div class="d-flex justify-content-between align-items-center mb-2" id="summaryDiscountRowModal">
                                <span class="text-muted fs-12 fw-semibold">Discount Amount</span>
                                <input type="number" name="discount_amount" id="summaryDiscountModal" class="form-control form-control-sm text-end fw-bold" style="width: 140px; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155;" step="0.01" value="0.00">
                            </div>

                            <!-- Gross Total -->
                            <div class="d-flex justify-content-between align-items-center mb-2" id="summaryGrossRowModal">
                                <span class="text-muted fs-12 fw-semibold">Gross Total (Before Tax)</span>
                                <input type="text" id="summaryGrossTextModal" class="form-control form-control-sm text-end fw-bold" style="width: 140px; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; background-color: #f8fafc;" readonly value="0.00">
                            </div>

                            <!-- Tax Rate (Percent) -->
                            <div class="d-flex justify-content-between align-items-center mb-2" id="orderTaxPercentRowModal">
                                <span class="text-muted fs-12 fw-semibold">Tax Rate (%)</span>
                                <input type="number" id="orderTaxPercentModal" class="form-control form-control-sm text-end fw-bold" style="width: 140px; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155;" min="0" step="0.01" value="0.00">
                            </div>

                            <!-- Hidden splits submitted to backend -->
                            <input type="hidden" name="cgst_amount" id="summaryCgstModal" value="0.00">
                            <input type="hidden" name="sgst_amount" id="summarySgstModal" value="0.00">
                            <input type="hidden" name="igst_amount" id="summaryIgstModal" value="0.00">

                            <!-- Tax Amount -->
                            <div class="d-flex justify-content-between align-items-center mb-2" id="summaryTaxRowModal">
                                <span class="text-muted fs-12 fw-semibold">Tax Amount</span>
                                <input type="text" id="summaryTaxTextModal" class="form-control form-control-sm text-end fw-bold" style="width: 140px; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; background-color: #f8fafc;" readonly value="0.00">
                                <input type="hidden" name="tax_amount" id="summaryTaxModal" value="0.00">
                            </div>

                            <!-- Grand Total -->
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="fw-bold fs-13" style="color: #2563eb;">Grand Total</span>
                                <input type="text" id="summaryGrandtotalTextModal" class="form-control form-control-sm text-end fw-extrabold" style="width: 140px; height: 32px; border: 1px solid #2563eb; border-radius: 4px; background-color: #eff6ff; color: #2563eb;" readonly value="0.00">
                                <input type="hidden" name="grand_total" id="summaryGrandtotalModal" value="0.00">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Validation --}}
            <div id="po-alert" class="alert alert-warning py-2 fs-12 mb-0 d-none"></div>

        </div>

        <x-slot name="footer">
            <x-ui.button variant="light" class="border" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button variant="primary" icon="feather-check" id="btn-confirm-po" type="submit" style="background-color: #714B67; border-color: #714B67;">
                Confirm &amp; Create PO
            </x-ui.button>
        </x-slot>
    </x-ui.modal>

@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Copy portal link to clipboard helper
            $('.copy-portal-btn').on('click', function() {
                const link = $(this).attr('data-link');
                navigator.clipboard.writeText(link).then(() => {
                    const $btn = $(this);
                    const originalHtml = $btn.html();
                    $btn.html('<i class="feather-check me-1"></i>Copied!');
                    $btn.removeClass('btn-outline-info').addClass('btn-success');
                    
                    setTimeout(() => {
                        $btn.html(originalHtml);
                        $btn.removeClass('btn-success').addClass('btn-outline-info');
                    }, 2000);
                }).catch(err => {
                    console.error('Could not copy portal link:', err);
                });
            });

            // ---- Real-time total calc ----
            $(document).on('input', '.vendor-rate-input, .vendor-qty-input', function() {
                const vendorId = $(this).attr('data-vendor');
                const productId = $(this).attr('data-product');
                const rateVal = parseFloat($(`.vendor-rate-input[data-vendor="${vendorId}"][data-product="${productId}"]`).val()) || 0;
                const qtyVal = parseFloat($(`.vendor-qty-input[data-vendor="${vendorId}"][data-product="${productId}"]`).val()) || 0;
                const total = rateVal * qtyVal;
                $(`#total_${vendorId}_${productId}`).text(total.toFixed(2));
                syncPoPreview();
            });

            // ---- Select-all checkbox ----
            $('#select-all-items').on('change', function() {
                $('.item-select-cb').prop('checked', $(this).prop('checked'));
                updatePoBtn();
                syncPoPreview();
            });

            $(document).on('change', '.item-select-cb', function() {
                const total = $('.item-select-cb').length;
                const checked = $('.item-select-cb:checked').length;
                $('#select-all-items').prop('indeterminate', checked > 0 && checked < total);
                $('#select-all-items').prop('checked', checked === total && total > 0);
                updatePoBtn();
                syncPoPreview();
            });

            // ---- Supplier radio ----
            $(document).on('change', '.supplier-select-radio', function() {
                updatePoBtn();
                syncPoPreview();
            });

            // ---- Sync when modal opens ----
            document.getElementById('createPoModal').addEventListener('show.bs.modal', function() {
                syncPoPreview();
            });

            // Handle form submission to validate inputs and set vendor ID
            $('#createPoModal form').on('submit', function(e) {
                const vendorId = $('.supplier-select-radio:checked').attr('data-vendor-id');
                const dbVendorId = $('.supplier-select-radio:checked').attr('data-db-vendor-id');
                const items = $('.item-select-cb:checked');
                const alertEl = $('#po-alert');
                alertEl.addClass('d-none').text('');

                if (!vendorId || !dbVendorId) {
                    e.preventDefault();
                    alertEl.removeClass('d-none').html('<i class="feather-alert-triangle me-1"></i> Please select a supplier using the radio button in the column header.');
                    return false;
                }
                if (!items.length) {
                    e.preventDefault();
                    alertEl.removeClass('d-none').html('<i class="feather-alert-triangle me-1"></i> Please select at least one item.');
                    return false;
                }

                $('#po-form-vendor-id').val(dbVendorId);
                return true;
            });

            // Toggle Layout in Modal
            $(document).on('change', '#po-discount-type, #po-tax-type, #po-gst-type', adjustModalLayout);

            // Calculation Triggers in Modal
            $(document).on('input', '#poItemsTableModal tbody tr.item-row input', calculateAllModal);
            $('#summaryDiscountModal, #orderTaxPercentModal').on('input', calculateAllModal);

        });

        function updatePoBtn() {
            const items      = $('.item-select-cb:checked').length;
            const vendorRadio = $('.supplier-select-radio:checked');
            const vendorName = vendorRadio.attr('data-vendor-name') || 'None';
            const bar        = document.getElementById('po-action-bar');

            $('#po-selection-count').text(items);
            $('#po-bar-items').text(items);
            $('#po-bar-supplier').text(vendorName);

            if (items > 0 || vendorRadio.length > 0) {
                bar.style.display = 'block';
                setTimeout(() => {
                    bar.style.transform = 'translateY(0)';
                    bar.style.opacity   = '1';
                }, 10);
            } else {
                bar.style.transform = 'translateY(100%)';
                bar.style.opacity   = '0';
                setTimeout(() => { bar.style.display = 'none'; }, 320);
            }
        }

        function clearPoSelection() {
            $('.item-select-cb').prop('checked', false);
            $('.supplier-select-radio').prop('checked', false);
            $('#select-all-items').prop('checked', false).prop('indeterminate', false);
            updatePoBtn();
        }

        function syncPoPreview() {
            const vendorRadio = $('.supplier-select-radio:checked');
            const vendorId   = vendorRadio.attr('data-vendor-id')   || '';
            const vendorName = vendorRadio.attr('data-vendor-name') || 'None selected';
            const quoteNo    = vendorRadio.attr('data-quotation-number') || '';
            const currency   = '{{ $currency }}';

            $('#po-supplier-name').text(vendorName);

            $('#po-reference').val("RFQ: {{ $rfq->rfq_number }}");
            $('#po-supplier-quotation-number').val(quoteNo);

            const tbody = $('#po-preview-tbody');
            const checked = $('.item-select-cb:checked');
            tbody.empty();

            if (!checked.length) {
                tbody.html('<tr id="po-no-items-row"><td colspan="10" class="text-center text-muted py-4"><i class="feather-package fs-2 d-block mb-2 text-light"></i>Tick items from the matrix</td></tr>');
                adjustModalLayout();
                return;
            }

            let rowNum = 1;
            checked.each(function() {
                const productId   = $(this).attr('data-product-id');
                const productName = $(this).attr('data-product-name');
                const rateVal = vendorId ? (parseFloat($(`.vendor-rate-input[data-vendor="${vendorId}"][data-product="${productId}"]`).val()) || 0) : 0;
                const qtyVal  = vendorId ? (parseFloat($(`.vendor-qty-input[data-vendor="${vendorId}"][data-product="${productId}"]`).val()) || 0) : 0;

                tbody.append(`<tr class="item-row" data-product-id="${productId}">
                    <td class="text-muted">${rowNum++}</td>
                    <td class="fw-semibold text-truncate" style="max-width: 150px;" title="${productName}">
                        ${productName}
                        <input type="hidden" name="items[${productId}][product_id]" value="${productId}">
                    </td>
                    <td>
                        <input type="number" name="items[${productId}][quantity]" class="odoo-table-input text-end qty-input" step="0.0001" min="0.0001" required value="${qtyVal.toFixed(4)}">
                    </td>
                    <td>
                        <input type="number" name="items[${productId}][rate]" class="odoo-table-input text-end rate-input" step="0.01" min="0" required value="${rateVal.toFixed(2)}">
                    </td>
                    <td>
                        <input type="number" name="items[${productId}][amount]" class="odoo-table-input text-end amount-input" step="0.01" readonly value="${(qtyVal * rateVal).toFixed(2)}" style="background-color: #f8fafc;">
                    </td>
                    <!-- Discount Columns -->
                    <td class="discount-column">
                        <input type="number" name="items[${productId}][discount_percent]" class="odoo-table-input text-end disc-percent-input" step="0.01" min="0" max="100" value="0.00">
                    </td>
                    <td class="discount-column">
                        <input type="number" name="items[${productId}][discount_amount]" class="odoo-table-input text-end disc-amount-input" step="0.01" readonly value="0.00" style="background-color: #f8fafc;">
                    </td>
                    <!-- Tax Columns -->
                    <td class="tax-column">
                        <input type="number" name="items[${productId}][tax_percent]" class="odoo-table-input text-end tax-percent-input" step="0.01" min="0" value="0.00">
                        <input type="hidden" name="items[${productId}][cgst_percent]" class="cgst-percent-input" value="0.00">
                        <input type="hidden" name="items[${productId}][sgst_percent]" class="sgst-percent-input" value="0.00">
                        <input type="hidden" name="items[${productId}][igst_percent]" class="igst-percent-input" value="0.00">
                        <input type="hidden" name="items[${productId}][cgst_amount]" class="cgst-amount-input" value="0.00">
                        <input type="hidden" name="items[${productId}][sgst_amount]" class="sgst-amount-input" value="0.00">
                        <input type="hidden" name="items[${productId}][igst_amount]" class="igst-amount-input" value="0.00">
                    </td>
                    <td class="tax-column">
                        <input type="number" name="items[${productId}][tax_amount]" class="odoo-table-input text-end tax-amount-input" step="0.01" readonly value="0.00" style="background-color: #f8fafc;">
                    </td>
                    <td>
                        <input type="number" name="items[${productId}][total_amount]" class="odoo-table-input text-end total-amount-input" step="0.01" readonly value="${(qtyVal * rateVal).toFixed(2)}" style="background-color: #f8fafc;">
                    </td>
                </tr>`);
            });

            adjustModalLayout();
        }

        // Modal Layout and Calculation Engines
        function adjustModalLayout() {
            const discType = $('#po-discount-type').val();
            const taxType = $('#po-tax-type').val();

            // 1. Discount option changes
            if (discType === 'item_wise') {
                $('#poItemsTableModal .discount-column').show();
                $('#summaryDiscountRowModal').show();
                $('#summaryGrossRowModal').show();
                $('#summaryDiscountModal').prop('readonly', true).css('background-color', '#f8fafc');
            } else if (discType === 'order_wise') {
                $('#poItemsTableModal .discount-column').hide();
                $('#poItemsTableModal .disc-percent-input').val('0.00');
                $('#poItemsTableModal .disc-amount-input').val('0.00');
                $('#summaryDiscountRowModal').show();
                $('#summaryGrossRowModal').show();
                $('#summaryDiscountModal').prop('readonly', false).css('background-color', '#ffffff');
            } else {
                // without_discount
                $('#poItemsTableModal .discount-column').hide();
                $('#poItemsTableModal .disc-percent-input').val('0.00');
                $('#poItemsTableModal .disc-amount-input').val('0.00');
                $('#summaryDiscountRowModal').hide();
                $('#summaryGrossRowModal').hide();
                $('#summaryDiscountModal').val('0.00');
            }

            // 2. Tax option changes
            if (taxType === 'item_wise_tax') {
                $('#poItemsTableModal .tax-column').show();
                $('#orderTaxPercentRowModal').hide().find('#orderTaxPercentModal').val('0.00');
                $('#summaryTaxRowModal').show();
                $('#gstTypeContainerModal').show();
            } else if (taxType === 'order_wise_tax') {
                $('#poItemsTableModal .tax-column').hide();
                $('#poItemsTableModal .tax-percent-input, #poItemsTableModal .tax-amount-input').val('0.00');
                $('#poItemsTableModal .cgst-percent-input, #poItemsTableModal .sgst-percent-input, #poItemsTableModal .igst-percent-input').val('0.00');
                $('#poItemsTableModal .cgst-amount-input, #poItemsTableModal .sgst-amount-input, #poItemsTableModal .igst-amount-input').val('0.00');
                $('#orderTaxPercentRowModal').show();
                $('#orderTaxPercentModal').prop('readonly', false).css('background-color', '#ffffff');
                $('#summaryTaxRowModal').show();
                $('#gstTypeContainerModal').show();
            } else {
                // without_tax
                $('#poItemsTableModal .tax-column').hide();
                $('#poItemsTableModal .tax-percent-input, #poItemsTableModal .tax-amount-input').val('0.00');
                $('#poItemsTableModal .cgst-percent-input, #poItemsTableModal .sgst-percent-input, #poItemsTableModal .igst-percent-input').val('0.00');
                $('#poItemsTableModal .cgst-amount-input, #poItemsTableModal .sgst-amount-input, #poItemsTableModal .igst-amount-input').val('0.00');
                $('#orderTaxPercentRowModal').hide().find('#orderTaxPercentModal').val('0.00');
                $('#summaryTaxRowModal').hide();
                $('#gstTypeContainerModal').hide();
                $('#summaryCgstModal, #summarySgstModal, #summaryIgstModal, #summaryTaxModal').val('0.00');
            }

            calculateAllModal();
        }

        function calculateAllModal() {
            const discType = $('#po-discount-type').val();
            const taxType = $('#po-tax-type').val();
            const gstType = $('#po-gst-type').val();
            
            let subtotal = 0.00;
            let totalItemDiscount = 0.00;
            let totalItemTax = 0.00;

            let totalCgst = 0.00;
            let totalSgst = 0.00;
            let totalIgst = 0.00;

            $('#poItemsTableModal tbody tr.item-row').each(function() {
                const $row = $(this);
                const qty = parseFloat($row.find('.qty-input').val()) || 0;
                const rate = parseFloat($row.find('.rate-input').val()) || 0;
                
                const amount = qty * rate;
                $row.find('.amount-input').val(amount.toFixed(2));
                subtotal += amount;

                // Row Discount calculations
                let rowDiscount = 0.00;
                if (discType === 'item_wise') {
                    const discPercent = parseFloat($row.find('.disc-percent-input').val()) || 0;
                    rowDiscount = amount * (discPercent / 100);
                    $row.find('.disc-amount-input').val(rowDiscount.toFixed(2));
                    totalItemDiscount += rowDiscount;
                } else {
                    $row.find('.disc-amount-input').val('0.00');
                }

                const taxableAmount = amount - rowDiscount;

                // Row Tax calculations
                let rowTax = 0.00;
                if (taxType === 'item_wise_tax') {
                    const taxPercent = parseFloat($row.find('.tax-percent-input').val()) || 0;
                    
                    let cgstPct = 0.00;
                    let sgstPct = 0.00;
                    let igstPct = 0.00;

                    if (gstType === 'cgst_sgst') {
                        cgstPct = taxPercent / 2;
                        sgstPct = taxPercent / 2;
                        igstPct = 0;
                    } else {
                        cgstPct = 0;
                        sgstPct = 0;
                        igstPct = taxPercent;
                    }

                    $row.find('.cgst-percent-input').val(cgstPct.toFixed(2));
                    $row.find('.sgst-percent-input').val(sgstPct.toFixed(2));
                    $row.find('.igst-percent-input').val(igstPct.toFixed(2));

                    const cgstAmt = taxableAmount * (cgstPct / 100);
                    const sgstAmt = taxableAmount * (sgstPct / 100);
                    const igstAmt = taxableAmount * (igstPct / 100);

                    $row.find('.cgst-amount-input').val(cgstAmt.toFixed(2));
                    $row.find('.sgst-amount-input').val(sgstAmt.toFixed(2));
                    $row.find('.igst-amount-input').val(igstAmt.toFixed(2));

                    totalCgst += cgstAmt;
                    totalSgst += sgstAmt;
                    totalIgst += igstAmt;

                    rowTax = cgstAmt + sgstAmt + igstAmt;
                    $row.find('.tax-amount-input').val(rowTax.toFixed(2));
                    totalItemTax += rowTax;
                } else {
                    $row.find('.tax-amount-input').val('0.00');
                    $row.find('.cgst-percent-input, .sgst-percent-input, .igst-percent-input').val('0.00');
                    $row.find('.cgst-amount-input, .sgst-amount-input, .igst-amount-input').val('0.00');
                }

                const rowTotal = taxableAmount + rowTax;
                $row.find('.total-amount-input').val(rowTotal.toFixed(2));
            });

            // Update subtotal
            $('#summarySubtotalModal').val(subtotal.toFixed(2));
            $('#summarySubtotalTextModal').val(subtotal.toFixed(2));

            // Resolve discount
            let finalDiscount = 0.00;
            if (discType === 'item_wise') {
                finalDiscount = totalItemDiscount;
                $('#summaryDiscountModal').val(finalDiscount.toFixed(2));
            } else if (discType === 'order_wise') {
                finalDiscount = parseFloat($('#summaryDiscountModal').val()) || 0.00;
            } else {
                finalDiscount = 0.00;
                $('#summaryDiscountModal').val('0.00');
            }

            const grossTotal = subtotal - finalDiscount;
            $('#summaryGrossTextModal').val(grossTotal.toFixed(2));

            // Resolve tax totals
            let finalTax = 0.00;
            if (taxType === 'item_wise_tax') {
                finalTax = totalItemTax;
                $('#summaryCgstModal').val(totalCgst.toFixed(2));
                $('#summarySgstModal').val(totalSgst.toFixed(2));
                $('#summaryIgstModal').val(totalIgst.toFixed(2));
                
                $('#summaryTaxTextModal').val(finalTax.toFixed(2));
            } else if (taxType === 'order_wise_tax') {
                const orderTaxPercent = parseFloat($('#orderTaxPercentModal').val()) || 0;

                let cgstPct = 0;
                let sgstPct = 0;
                let igstPct = 0;

                if (gstType === 'cgst_sgst') {
                    cgstPct = orderTaxPercent / 2;
                    sgstPct = orderTaxPercent / 2;
                    igstPct = 0;
                } else {
                    cgstPct = 0;
                    sgstPct = 0;
                    igstPct = orderTaxPercent;
                }

                const cgstAmt = grossTotal * (cgstPct / 100);
                const sgstAmt = grossTotal * (sgstPct / 100);
                const igstAmt = grossTotal * (igstPct / 100);

                finalTax = cgstAmt + sgstAmt + igstAmt;

                $('#summaryCgstModal').val(cgstAmt.toFixed(2));
                $('#summarySgstModal').val(sgstAmt.toFixed(2));
                $('#summaryIgstModal').val(igstAmt.toFixed(2));
                
                $('#summaryTaxTextModal').val(finalTax.toFixed(2));
            } else {
                $('#summaryCgstModal').val('0.00');
                $('#summarySgstModal').val('0.00');
                $('#summaryIgstModal').val('0.00');
                $('#summaryTaxTextModal').val('0.00');
            }

            $('#summaryTaxModal').val(finalTax.toFixed(2));

            const grandTotal = grossTotal + finalTax;
            $('#summaryGrandtotalModal').val(grandTotal.toFixed(2));
            $('#summaryGrandtotalTextModal').val(grandTotal.toFixed(2));
        }
    </script>
@endpush
