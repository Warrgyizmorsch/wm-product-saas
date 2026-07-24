@extends('layouts.duralux')

@section('title', "GRN {$grn->grn_number} | SaaS ERP")
@section('page-title', "Goods Receipt Note Details")
@section('breadcrumb')
    <a href="{{ route('purchase.orders.index') }}">Purchase</a> &gt; <a href="{{ route('purchase.grns.index') }}">Goods Receipt Notes</a> &gt; {{ $grn->grn_number }}
@endsection

@section('page-actions')
    <div class="d-flex align-items-center gap-0">
        <a href="{{ route('purchase.grns.index') }}" class="action-dropdown-btn me-2" title="Back to GRNs" data-bs-toggle="tooltip">
            <i class="feather feather-arrow-left"></i>
        </a>
        <a href="{{ route('purchase.grns.download', $grn->id) }}" class="action-dropdown-btn me-2" title="Download PDF" data-bs-toggle="tooltip">
            <i class="feather feather-download"></i>
        </a>

        @if($grn->status === 'Draft')
            <x-ui.button href="{{ route('purchase.grns.edit', $grn->id) }}" variant="warning" icon="feather-edit" class="me-2">
                Edit Draft
            </x-ui.button>
            <form action="{{ route('purchase.grns.approve', $grn->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Approve this GRN? This will update warehouse inventory stock and PO status.')">
                @csrf
                <x-ui.button type="submit" variant="success" icon="feather-check-circle" class="text-white">
                    Approve & Update Stock
                </x-ui.button>
            </form>
        @else
            <a href="{{ route('purchase.bills.create', ['grn_id' => $grn->id]) }}" class="btn btn-success text-white fs-12 fw-bold shadow-sm">
                <i class="feather-file-text me-1.5"></i>Create Vendor Bill
            </a>
        @endif
    </div>
@endsection

@once
    @push('styles')
        <style>
            .action-dropdown-btn {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 32px !important;
                height: 32px !important;
                border-radius: 8px !important;
                border: 1.5px solid #cbd5e1 !important;
                background-color: #ffffff !important;
                color: #475569 !important;
                transition: all 0.28s ease !important;
                text-decoration: none !important;
                cursor: pointer !important;
            }
            .action-dropdown-btn:hover {
                background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent) !important;
                border-color: var(--bs-primary) !important;
                color: var(--bs-primary) !important;
            }
            .so-status-pipeline {
                display: inline-flex;
                align-items: center;
                border-radius: 4px;
                overflow: hidden;
                border: 1px solid #cbd5e1;
                background-color: #f1f5f9;
            }
            .so-status-pipeline .pipeline-step {
                position: relative;
                padding: 6px 14px 6px 24px;
                background-color: #f1f5f9;
                color: #64748b;
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border: none;
                outline: none;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
            }
            .so-status-pipeline .pipeline-step:first-child {
                padding-left: 14px;
                border-top-left-radius: 3px;
                border-bottom-left-radius: 3px;
            }
            .so-status-pipeline .pipeline-step:last-child {
                padding-right: 14px;
                border-top-right-radius: 3px;
                border-bottom-right-radius: 3px;
            }
            .so-status-pipeline .pipeline-step::after {
                content: "";
                position: absolute;
                top: 0;
                right: -10px;
                width: 0;
                height: 0;
                border-top: 14px solid transparent;
                border-bottom: 14px solid transparent;
                border-left: 10px solid #f1f5f9;
                z-index: 10;
                transition: all 0.2s ease;
            }
            .so-status-pipeline .pipeline-step::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                width: 0;
                height: 0;
                border-top: 14px solid transparent;
                border-bottom: 14px solid transparent;
                border-left: 10px solid #ffffff;
                z-index: 5;
            }
            .so-status-pipeline .pipeline-step:first-child::before {
                display: none;
            }
            .so-status-pipeline .pipeline-step.active {
                background-color: var(--bs-primary, #3454d1);
                color: #ffffff;
            }
            .so-status-pipeline .pipeline-step.active::after {
                border-left-color: var(--bs-primary, #3454d1);
            }
            .so-status-pipeline .pipeline-step.completed {
                background-color: #cbd5e1;
                color: #475569;
            }
            .so-status-pipeline .pipeline-step.completed::after {
                border-left-color: #cbd5e1;
            }

            .so-status-pipeline::-webkit-scrollbar {
                display: none !important;
                width: 0 !important;
                height: 0 !important;
            }
            .so-status-pipeline {
                -ms-overflow-style: none !important;
                scrollbar-width: none !important;
            }

            @media (max-width: 991.98px) {
                .so-status-pipeline {
                    max-width: 100%;
                    overflow-x: auto !important;
                    white-space: nowrap !important;
                    -webkit-overflow-scrolling: touch;
                    margin-top: 4px;
                    margin-bottom: 4px;
                    display: flex !important;
                    flex-wrap: nowrap !important;
                }
                .so-status-pipeline .pipeline-step {
                    padding: 4px 8px 4px 18px !important;
                    font-size: 8.5px !important;
                    letter-spacing: 0px !important;
                    flex-shrink: 0 !important;
                }
                .so-status-pipeline .pipeline-step:first-child {
                    padding-left: 10px !important;
                }
                .so-status-pipeline .pipeline-step:last-child {
                    padding-right: 10px !important;
                }
                .so-status-pipeline .pipeline-step::after {
                    right: -8px !important;
                    border-top: 11px solid transparent !important;
                    border-bottom: 11px solid transparent !important;
                    border-left: 8px solid #f1f5f9 !important;
                }
                .so-status-pipeline .pipeline-step::before {
                    border-top: 11px solid transparent !important;
                    border-bottom: 11px solid transparent !important;
                    border-left: 8px solid #ffffff !important;
                }
                .so-status-pipeline .pipeline-step.active::after {
                    border-left-color: var(--bs-primary, #3454d1) !important;
                }
                .so-status-pipeline .pipeline-step.completed::after {
                    border-left-color: #cbd5e1 !important;
                }
                .odoo-sheet {
                    padding: 16px !important;
                }
            }
        </style>
    @endpush
@endonce

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

            @if($grn->status === 'Approved')
                <div class="alert alert-success d-flex align-items-center mb-4 border-0 shadow-sm">
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-check-circle fs-18 text-success"></i>
                        <div>
                            <strong class="text-dark">Goods Receipt Approved & Inventory Stock Updated</strong>
                            <div class="fs-12 text-muted">Stock has been credited to {{ $grn->warehouse?->name ?? 'Main Warehouse' }}. Stock transaction records created.</div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Main Single GRN Card -->
            <div class="card border-0 shadow-sm bg-white mb-4 print-area odoo-sheet">
                <div class="card-header bg-white border-bottom py-0 px-4 d-print-none d-flex justify-content-between align-items-center flex-wrap gap-2" style="min-height: 52px;">
                    <div class="d-flex align-items-center py-2">
                        <h5 class="fw-bold text-dark mb-0 me-3 fs-16">{{ $grn->grn_number }}</h5>
                        @php
                            $badgeClass = match($grn->status) {
                                'Draft' => 'bg-soft-warning text-warning',
                                'Approved' => 'bg-soft-success text-success',
                                'Cancelled' => 'bg-soft-danger text-danger',
                                default => 'bg-soft-secondary text-secondary',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }} px-2.5 py-1 fw-bold fs-11">{{ $grn->status }}</span>
                    </div>

                    <!-- Chevron Status Pipeline -->
                    <div class="so-status-pipeline my-2 d-print-none">
                        @php
                            $statuses = ['Draft' => 'Draft', 'Approved' => 'Received'];
                            if ($grn->status === 'Cancelled') {
                                $statuses['Cancelled'] = 'Cancelled';
                            }
                            $keys = array_keys($statuses);
                            $currentIndex = array_search($grn->status, $keys);
                        @endphp
                        @foreach($statuses as $key => $label)
                            @php
                                $stepIndex = array_search($key, $keys);
                                $stepClass = '';
                                if ($grn->status === $key) {
                                    $stepClass = 'active';
                                } elseif ($currentIndex !== false && $stepIndex < $currentIndex) {
                                    $stepClass = 'completed';
                                }
                            @endphp
                            <span class="pipeline-step {{ $stepClass }}">
                                {{ $label }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">
                    <!-- Top Details Grid -->
                    <div class="row g-4 fs-13 pb-4 border-bottom">
                        <div class="col-md-6 border-end">
                            <h6 class="fw-bold text-primary mb-3">Receipt & Vendor Information</h6>

                            <x-ui.odoo-form-ui type="input" label="GRN Number" name="grn_number" :value="$grn->grn_number" readonly="true" />
                            <x-ui.odoo-form-ui type="input" label="Purchase Order" name="po_number" :value="$grn->purchaseOrder ? $grn->purchaseOrder->purchase_order_number : 'Direct Receipt'" readonly="true" />
                            <x-ui.odoo-form-ui type="input" label="Vendor Name" name="vendor" :value="$grn->vendor?->name ?? '—'" readonly="true" />
                            <x-ui.odoo-form-ui type="input" label="Target Warehouse" name="warehouse" :value="$grn->warehouse?->name ?? 'Main Warehouse'" readonly="true" />
                            <x-ui.odoo-form-ui type="input" label="Receipt Date" name="received_date" :value="$grn->received_date ? $grn->received_date->format('d-M-Y') : '—'" readonly="true" />
                        </div>

                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary mb-3">Challan & Transporter Information</h6>

                            <x-ui.odoo-form-ui type="input" label="Challan / Invoice No" name="challan_number" :value="$grn->challan_number ?: '—'" readonly="true" />
                            <x-ui.odoo-form-ui type="input" label="Challan Date" name="challan_date" :value="$grn->challan_date ? $grn->challan_date->format('d-M-Y') : '—'" readonly="true" />
                            <x-ui.odoo-form-ui type="input" label="Transporter Name" name="transporter" :value="$grn->transporter_name ?: '—'" readonly="true" />
                            <x-ui.odoo-form-ui type="input" label="Vehicle Number" name="vehicle" :value="$grn->vehicle_number ?: '—'" readonly="true" />
                            <x-ui.odoo-form-ui type="input" label="L.R. Number" name="lr_number" :value="$grn->lr_number ?: '—'" readonly="true" />
                        </div>
                    </div>

                    <!-- Notes Section -->
                    @if($grn->notes)
                        <div class="mt-4 pt-2 mb-4">
                            <h6 class="fw-bold text-primary mb-2">Store Receipt Remarks / Notes</h6>
                            <p class="text-secondary bg-light p-3 rounded fs-13 border mb-0" style="white-space: pre-line;">{{ $grn->notes }}</p>
                        </div>
                    @endif

                    <!-- Itemized Received Table -->
                    <div class="mt-4 pt-2">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-layers text-primary me-2"></i>Itemized Received Products</h6>
                        <div class="table-responsive border rounded bg-white mb-4">
                            <table class="table table-hover align-middle mb-0 fs-13 text-dark">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 4%;" class="ps-3">#</th>
                                        <th>Product Name</th>
                                        <th class="text-center">Ordered Qty</th>
                                        <th class="text-center">Prev. Rec.</th>
                                        <th class="text-center">Received</th>
                                        <th class="text-center">Rejected</th>
                                        <th class="text-center">Accepted</th>
                                        <th class="text-end">Unit Rate ({{ $currency }})</th>
                                        <th class="text-end pe-3">Total Amount ({{ $currency }})</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $totRec = 0; $totRej = 0; $totAcc = 0; $totAmt = 0;
                                        $groupedItems = $grn->items->groupBy('product_id')->map(function($items) {
                                            $first = $items->first();
                                            return (object) [
                                                'id' => $first->id,
                                                'product' => $first->product,
                                                'product_id' => $first->product_id,
                                                'ordered_qty' => $items->sum('ordered_qty'),
                                                'previous_received_qty' => $items->sum('previous_received_qty'),
                                                'received_qty' => $items->sum('received_qty'),
                                                'rejected_qty' => $items->sum('rejected_qty'),
                                                'accepted_qty' => $items->sum('accepted_qty'),
                                                'unit_rate' => $first->unit_rate,
                                                'total_amount' => $items->sum('total_amount'),
                                                'remarks' => $items->pluck('remarks')->filter()->implode(', '),
                                            ];
                                        })->values();
                                    @endphp
                                    @foreach($groupedItems as $idx => $item)
                                        @php
                                            $totRec += (float)$item->received_qty;
                                            $totRej += (float)$item->rejected_qty;
                                            $totAcc += (float)$item->accepted_qty;
                                            $totAmt += (float)$item->total_amount;
                                        @endphp
                                        <tr>
                                            <td class="ps-3 text-center fw-semibold text-muted">{{ $idx + 1 }}</td>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $item->product?->name }}</div>
                                                <div class="fs-11 text-muted">SKU: {{ $item->product?->sku ?? 'N/A' }} | UOM: {{ $item->product?->uom?->name ?? 'Pcs' }}</div>
                                                @if($item->remarks)
                                                    <div class="fs-11 text-danger mt-0.5"><i class="feather-info me-1"></i>Remarks: {{ $item->remarks }}</div>
                                                @endif
                                            </td>
                                            <td class="text-center font-monospace">{{ number_format($item->ordered_qty, 2) }}</td>
                                            <td class="text-center font-monospace text-muted">{{ number_format($item->previous_received_qty, 2) }}</td>
                                            <td class="text-center font-monospace fw-bold text-primary">{{ number_format($item->received_qty, 2) }}</td>
                                            <td class="text-center font-monospace text-danger fw-semibold">{{ number_format($item->rejected_qty, 2) }}</td>
                                            <td class="text-center font-monospace text-success fw-bold">{{ number_format($item->accepted_qty, 2) }}</td>
                                            <td class="text-end font-monospace">{{ number_format($item->unit_rate, 2) }}</td>
                                            <td class="text-end pe-3 font-monospace fw-bold text-dark">{{ number_format($item->total_amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <td colspan="4" class="text-end pe-3">Total Summaries:</td>
                                        <td class="text-center font-monospace text-primary fs-14">{{ number_format($totRec, 2) }}</td>
                                        <td class="text-center font-monospace text-danger fs-14">{{ number_format($totRej, 2) }}</td>
                                        <td class="text-center font-monospace text-success fs-14">{{ number_format($totAcc, 2) }}</td>
                                        <td></td>
                                        <td class="text-end pe-3 font-monospace text-dark fs-14">{{ $currency }} {{ number_format($totAmt, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Audit Footer -->
                        <div class="row pt-3 text-secondary fs-12 border-top">
                            <div class="col-md-6">
                                <div><strong>Created By:</strong> {{ $grn->creator?->name ?? 'System' }} on {{ $grn->created_at->format('d-M-Y h:i A') }}</div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                @if($grn->status === 'Approved')
                                    <div><strong>Approved By:</strong> {{ $grn->approver?->name ?? 'System' }} on {{ $grn->approved_at ? $grn->approved_at->format('d-M-Y h:i A') : '—' }}</div>
                                @else
                                    <div><strong>Status:</strong> <span class="badge bg-soft-warning text-warning">Draft (Pending Approval)</span></div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
