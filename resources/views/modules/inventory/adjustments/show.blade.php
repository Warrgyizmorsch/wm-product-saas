@extends('layouts.duralux')

@section('title', 'Adjustment Details #' . $adjustment->adjustment_number . ' | SaaS ERP')
@section('page-title', 'Stock Adjustment Details')
@section('breadcrumb', 'Inventory / Stock Adjustments / Details')

@section('content')
<div class="erp-single-panel text-dark">
    <x-ui.odoo-form-ui type="sheet">

        <!-- Top Header & Actions Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom flex-wrap gap-3">
            <div>
                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">
                    <i class="feather-sliders text-primary me-1"></i>Stock Adjustment Document
                </span>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h3 class="fw-bold text-dark mb-0">{{ $adjustment->adjustment_number }}</h3>
                    <x-ui.status-badge :status="$adjustment->status" size="md" />
                </div>
                <small class="text-muted fs-12">
                    <i class="feather-clock me-1"></i>Created on {{ \Carbon\Carbon::parse($adjustment->created_at)->format('d M Y, h:i A') }}
                </small>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2 flex-wrap align-items-center">
                @if($adjustment->status === 'Draft')
                    <form action="{{ route('inventory.adjustments.approve', $adjustment->id) }}" method="POST" onsubmit="return confirm('Approve adjustment and update stock levels?')">
                        @csrf
                        <x-ui.button type="submit" variant="success" icon="feather-check-circle" class="text-white">
                            Approve & Apply
                        </x-ui.button>
                    </form>
                    <form action="{{ route('inventory.adjustments.cancel', $adjustment->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this adjustment?')">
                        @csrf
                        <x-ui.button type="submit" variant="danger" icon="feather-x-circle">
                            Cancel Adjustment
                        </x-ui.button>
                    </form>
                @endif

                <x-ui.button href="{{ route('inventory.adjustments.index') }}" variant="light" icon="feather-arrow-left">
                    Back to List
                </x-ui.button>
            </div>
        </div>

        <!-- Metadata Summary Grid Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border h-100">
                    <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1" style="letter-spacing:0.5px;">Warehouse</span>
                    <span class="fw-bold text-dark fs-14 d-flex align-items-center">
                        <i class="feather-map-pin text-muted me-1 fs-13"></i>{{ $adjustment->warehouse->name ?? 'N/A' }}
                    </span>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border h-100">
                    <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1" style="letter-spacing:0.5px;">Reason</span>
                    <div class="mt-1">
                        <span class="badge bg-light text-dark border font-monospace fs-11">{{ $adjustment->reason }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border h-100">
                    <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1" style="letter-spacing:0.5px;">Adjustment Date</span>
                    <span class="fw-bold text-dark fs-14 d-flex align-items-center">
                        <i class="feather-calendar text-muted me-1 fs-13"></i>{{ \Carbon\Carbon::parse($adjustment->adjustment_date)->format('d M Y') }}
                    </span>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border h-100">
                    <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1" style="letter-spacing:0.5px;">Current Status</span>
                    <div class="mt-1">
                        <x-ui.status-badge :status="$adjustment->status" size="sm" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Adjusted Items Section -->
        <div class="mb-4">
            <h5 class="fw-bold text-dark mb-3">
                <i class="feather-layers text-primary me-2"></i>Adjusted Line Items
            </h5>
            
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table" id="adjustedItemsTable">
                    <thead class="table-light bg-light">
                        <tr>
                            <th style="width: 5%;" class="text-center">#</th>
                            <th style="width: 25%;">Product Name</th>
                            <th style="width: 12%;" class="text-center">Type</th>
                            <th style="width: 12%;" class="text-end">Quantity</th>
                            <th style="width: 13%;" class="text-end">Unit Cost</th>
                            <th style="width: 15%;" class="text-end">Total Amount</th>
                            <th style="width: 18%;">Serial Numbers</th>
                        </tr>
                    </thead>
                    <tbody class="text-dark">
                        @foreach($adjustment->items as $index => $item)
                            <tr>
                                <td class="text-center fw-bold text-muted">{{ $index + 1 }}</td>
                                <td>
                                    @if($item->product)
                                        <a href="{{ route('inventory.products.show', $item->product_id) }}" class="fw-bold text-dark text-decoration-none">
                                            {{ $item->product->name }}
                                        </a>
                                        @if($item->product->sku)
                                            <div class="fs-11 text-muted font-monospace">SKU: {{ $item->product->sku }}</div>
                                        @endif
                                    @else
                                        <strong class="text-dark">N/A</strong>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($item->type === 'Addition')
                                        <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-0.5 fs-11 fw-bold">
                                            <i class="feather-plus me-0.5"></i>Addition
                                        </span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger border border-danger-subtle px-2 py-0.5 fs-11 fw-bold">
                                            <i class="feather-minus me-0.5"></i>Deduction
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end font-monospace fw-bold fs-13 text-dark">
                                    {{ number_format($item->quantity, 2) }}
                                </td>
                                <td class="text-end font-monospace fw-semibold fs-12 text-muted">
                                    ₹{{ number_format($item->unit_cost, 2) }}
                                </td>
                                <td class="text-end font-monospace fw-bold fs-13 text-dark">
                                    ₹{{ number_format($item->total_amount, 2) }}
                                </td>
                                <td>
                                    @if(!empty($item->serial_numbers))
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($item->serial_numbers as $sn)
                                                <span class="badge bg-soft-primary text-primary font-monospace border border-primary-subtle fs-11">
                                                    <i class="feather-hash me-0.5"></i>{{ $sn }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted fs-12">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <!-- Notes Section -->
        @if($adjustment->notes)
            <div class="p-3 bg-light rounded border">
                <h6 class="fw-bold text-dark mb-1">
                    <i class="feather-file-text text-primary me-1"></i>Notes & Remarks:
                </h6>
                <p class="mb-0 text-muted fs-13">{{ $adjustment->notes }}</p>
            </div>
        @endif

    </x-ui.odoo-form-ui>
</div>
@endsection
