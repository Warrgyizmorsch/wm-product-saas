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
                                                        data-vendor-name="{{ $rv->vendor?->name }}"
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
    <x-ui.modal id="createPoModal" title="Create Purchase Order" size="lg" :centered="true" :showFooter="true">
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
                {{-- PO Date --}}
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="PO Date" id="po-date" name="po_date" :value="now()->format('Y-m-d')" />
                </div>
                {{-- Delivery Remarks --}}
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="Remarks" id="po-remarks" name="po_remarks" placeholder="Optional notes..." />
                </div>
            </div>

            {{-- Items preview table --}}
            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-2">Selected Items</label>
            <div class="table-responsive border rounded mb-3" style="max-height:280px; overflow-y:auto;">
                <table class="table table-sm table-hover align-middle mb-0 fs-12">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody id="po-preview-tbody">
                        <tr id="po-no-items-row">
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="feather-package fs-2 d-block mb-2 text-light"></i>
                                Tick items from the matrix
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="4" class="text-end fw-bold fs-12">Grand Total:</td>
                            <td class="text-end fw-bold text-success fs-13" id="po-grand-total">&mdash;</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Validation --}}
            <div id="po-alert" class="alert alert-warning py-2 fs-12 mb-0 d-none"></div>

        </div>

        <x-slot name="footer">
            <x-ui.button variant="light" class="border" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button variant="success" icon="feather-check" id="btn-confirm-po" type="button" onclick="submitCreatePO()">
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
            const currency   = '{{ $currency }}';

            $('#po-supplier-name').text(vendorName);

            const tbody = $('#po-preview-tbody');
            const checked = $('.item-select-cb:checked');
            tbody.empty();

            if (!checked.length) {
                tbody.html('<tr id="po-no-items-row"><td colspan="5" class="text-center text-muted py-4"><i class="feather-package fs-2 d-block mb-2 text-light"></i>Tick items from the matrix</td></tr>');
                $('#po-grand-total').html('&mdash;');
                return;
            }

            let grandTotal = 0, rowNum = 1;
            checked.each(function() {
                const productId   = $(this).attr('data-product-id');
                const productName = $(this).attr('data-product-name');
                const rateVal = vendorId ? (parseFloat($(`.vendor-rate-input[data-vendor="${vendorId}"][data-product="${productId}"]`).val()) || 0) : 0;
                const qtyVal  = vendorId ? (parseFloat($(`.vendor-qty-input[data-vendor="${vendorId}"][data-product="${productId}"]`).val()) || 0) : 0;
                const total   = rateVal * qtyVal;
                grandTotal   += total;
                const rowCls  = rateVal === 0 ? 'class="table-warning"' : '';
                tbody.append(`<tr ${rowCls}>
                    <td class="text-muted">${rowNum++}</td>
                    <td class="fw-semibold">${productName}</td>
                    <td class="text-end font-monospace">${qtyVal.toFixed(2)}</td>
                    <td class="text-end font-monospace">${rateVal > 0 ? currency + ' ' + rateVal.toFixed(2) : '<span class="text-danger">—</span>'}</td>
                    <td class="text-end font-monospace fw-bold ${total > 0 ? 'text-success' : 'text-danger'}">${total > 0 ? currency + ' ' + total.toLocaleString('en-IN', {minimumFractionDigits:2}) : '—'}</td>
                </tr>`);
            });

            $('#po-grand-total').html(grandTotal > 0 ? `<span class="text-success">${currency} ${grandTotal.toLocaleString('en-IN', {minimumFractionDigits:2})}</span>` : '&mdash;');
        }

        function submitCreatePO() {
            const vendorId = $('.supplier-select-radio:checked').attr('data-vendor-id');
            const vendorName = $('.supplier-select-radio:checked').attr('data-vendor-name');
            const items = $('.item-select-cb:checked');
            const alertEl = $('#po-alert');
            alertEl.addClass('d-none').text('');

            if (!vendorId) {
                alertEl.removeClass('d-none').html('<i class="feather-alert-triangle me-1"></i> Please select a supplier using the radio button in the column header.');
                return;
            }
            if (!items.length) {
                alertEl.removeClass('d-none').html('<i class="feather-alert-triangle me-1"></i> Please select at least one item.');
                return;
            }

            // UI-only toast (DB integration pending)
            bootstrap.Modal.getInstance(document.getElementById('createPoModal'))?.hide();
            const toast = document.createElement('div');
            toast.className = 'alert alert-success shadow-lg border-0 d-flex align-items-center gap-3 rounded-3 py-3 px-4';
            toast.style.cssText = 'position:fixed;bottom:80px;right:24px;z-index:9999;min-width:340px;';
            toast.innerHTML = `<i class="feather-check-circle fs-20 text-success"></i><div><div class="fw-bold text-dark">PO Ready!</div><div class="fs-12 text-muted">${items.length} item(s) for <strong>${vendorName}</strong> — DB integration coming soon.</div></div>`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 4500);
        }
    </script>
@endpush
