@extends('layouts.duralux')

@section('title', 'Quotation details | SaaS ERP')
@section('page-title', 'Quotation ' . $quotation->quotation_number)
@section('breadcrumb', 'CRM / Quotations / ' . $quotation->quotation_number)

@section('page-actions')
    <div class="d-flex gap-2">
        <x-ui.button href="{{ route('crm.quotations.index') }}" variant="light" class="d-print-none" icon="feather-arrow-left">
            Back to List
        </x-ui.button>
        @if ($quotation->status === 'Accepted')
            <x-ui.button href="{{ route('sales.orders.create', ['quotation_id' => $quotation->id]) }}" variant="success" class="d-print-none" icon="feather-shopping-cart">
                Convert to Sales Order
            </x-ui.button>
        @endif
        <x-ui.button href="{{ route('crm.quotations.download', $quotation->id) }}" variant="primary" class="d-print-none" icon="feather-printer">
            Print / Download PDF
        </x-ui.button>
    </div>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4 d-print-none" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-success text-white me-3">
                    <i class="feather-check-circle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Success!</h6>
                    <p class="fs-12 mb-0">{{ session('success') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <x-ui.card class="print-area" id="printableInvoice" bodyClass="p-5">
        <!-- Invoice Header -->
        <div class="row align-items-center mb-5">
            <div class="col-sm-6 text-start">
                <div class="d-flex align-items-center">
                    <div class="avatar-text avatar-lg bg-primary text-white fs-3 fw-bold me-3 shadow">
                        {{ strtoupper(substr(tenant() ? tenant()->name : 'ERP', 0, 1)) }}
                    </div>
                    <div>
                        <h3 class="fw-bold text-dark mb-0">{{ tenant() ? tenant()->name : 'SaaS ERP Workspace' }}</h3>
                        <p class="text-muted mb-0 fs-12">Official Sales Quotation</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 text-sm-end mt-4 mt-sm-0">
                <h4 class="fw-bold text-primary mb-1">ESTIMATE</h4>
                <span class="fs-14 fw-bold text-dark d-block">No: {{ $quotation->quotation_number }}</span>
                @php
                    $badgeClass = 'bg-soft-secondary text-secondary';
                    if ($quotation->status === 'Sent' || $quotation->status === 'Quotation Sent') $badgeClass = 'bg-soft-info text-info';
                    elseif ($quotation->status === 'Accepted' || $quotation->status === 'Approved') $badgeClass = 'bg-soft-success text-success';
                    elseif ($quotation->status === 'Declined' || $quotation->status === 'Rejected') $badgeClass = 'bg-soft-danger text-danger';
                    elseif ($quotation->status === 'Pending Approval') $badgeClass = 'bg-soft-warning text-warning';
                    elseif ($quotation->status === 'Quotation Rework' || $quotation->status === 'Rework') $badgeClass = 'bg-soft-warning text-warning';
                @endphp
                <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-11 mt-1">{{ $quotation->status }}</span>
            </div>
        </div>

        <hr class="my-4">

        <!-- Meta details (Bill to / Dates) -->
        <div class="row mb-5">
            <div class="col-6 text-start">
                <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-2">Prepared For</span>
                <h5 class="fw-bold text-dark mb-1">{{ $quotation->customer?->name ?? ($quotation->lead?->company_name ?? '—') }}</h5>
                <p class="text-muted mb-1 fs-13"><i class="feather-mail me-2"></i>{{ $quotation->customer?->email ?: ($quotation->lead?->email ?: '—') }}</p>
                <p class="text-muted mb-1 fs-13"><i class="feather-phone me-2"></i>{{ $quotation->customer?->phone ?: ($quotation->lead?->phone ?: '—') }}</p>
                
                @if($quotation->lead && ($quotation->lead->address || $quotation->lead->city || $quotation->lead->state || $quotation->lead->country))
                    <div class="text-muted mt-3 fs-12">
                        <span class="d-block fw-bold text-uppercase text-muted mb-0.5" style="font-size: 9px; letter-spacing: 0.5px;">Billing Address</span>
                        @if($quotation->lead->address)
                            <span class="d-block text-dark">{{ $quotation->lead->address }}</span>
                        @endif
                        <span class="d-block text-dark">
                            {{ implode(', ', array_filter([$quotation->lead->city, $quotation->lead->state, $quotation->lead->country])) }}
                        </span>
                    </div>
                @endif
            </div>
            <div class="col-6 text-end">
                <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-2">Quotation Schedule</span>
                <p class="text-dark mb-1 fs-13"><strong>Quotation Date:</strong> {{ $quotation->quotation_date ? $quotation->quotation_date->format('d/m/Y') : '—' }}</p>
                <p class="text-dark mb-1 fs-13"><strong>Valid Until:</strong> {{ $quotation->expiry_date ? $quotation->expiry_date->format('d/m/Y') : '—' }}</p>
                @if($quotation->salesPerson)
                    <p class="text-dark mb-0 fs-13"><strong>Sales Rep:</strong> {{ $quotation->salesPerson->name }}</p>
                @endif
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-responsive mb-5">
            <table class="table table-bordered align-middle">
                <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                    <tr>
                        <th class="ps-3" style="width: 5%;">#</th>
                        <th style="width: 40%;">Description of Service / Product</th>
                        <th class="text-center" style="width: 10%;">Qty</th>
                        <th class="text-end" style="width: 15%;">Unit Price (₹)</th>
                        <th class="text-end" style="width: 12%;">Tax Rate</th>
                        <th class="text-end pe-3" style="width: 18%;">Amount (₹)</th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
                    @foreach ($quotation->items as $index => $item)
                        <tr>
                            <td class="ps-3 text-muted text-center">{{ $index + 1 }}</td>
                            <td>
                                <strong class="text-dark">{{ $item->item_name }}</strong>
                                @if($item->description)
                                    <small class="text-muted d-block mt-0.5">{{ $item->description }}</small>
                                @endif
                            </td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-end">₹{{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-end">{{ number_format($item->tax_rate, 2) }}%</td>
                            <td class="text-end pe-3 fw-semibold">₹{{ number_format($item->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Bottom Notes and Calculations -->
        <div class="row g-4">
            <div class="col-7 text-start">
                @if($quotation->terms_conditions)
                    <h6 class="fw-bold text-dark mb-2 fs-12 text-uppercase">Terms & Conditions</h6>
                    <div class="text-muted fs-12 mb-4 terms-conditions-content">{!! $quotation->terms_conditions !!}</div>
                @endif

                @if($quotation->notes)
                    <h6 class="fw-bold text-dark mb-2 fs-12 text-uppercase">Client Notes</h6>
                    <p class="text-muted fs-12 mb-0" style="white-space: pre-line;">{{ $quotation->notes }}</p>
                @endif
            </div>
            <div class="col-5">
                <div class="border p-3 rounded bg-light">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal:</span>
                        <span class="fw-bold text-dark">₹{{ number_format($quotation->subtotal, 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Tax total (GST):</span>
                        <span class="fw-bold text-dark">₹{{ number_format($quotation->tax, 2) }}</span>
                    </div>
                    @if($quotation->discount > 0)
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>Discount:</span>
                            <span>-₹{{ number_format($quotation->discount, 2) }}</span>
                        </div>
                    @endif
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fs-15 fw-bold text-dark">Total Payable:</span>
                        <span class="fs-15 fw-bold text-primary">₹{{ number_format($quotation->total_amount, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-5">

        <!-- Signature block -->
        <div class="row mt-5">
            <div class="col-sm-6 text-start">
                <p class="fs-11 text-muted mb-0">For any questions concerning this quotation, contact sales office.</p>
            </div>
            <div class="col-sm-6 text-sm-end mt-4 mt-sm-0">
                <div class="d-inline-block text-center" style="width: 200px;">
                    <hr class="mb-1 mt-5">
                    <span class="fs-11 text-muted text-uppercase fw-semibold">Authorized Signature</span>
                </div>
            </div>
        </div>
    </x-ui.card>
@endsection

@push('styles')
    <style>
        .terms-conditions-content p {
            margin-bottom: 4px !important;
            line-height: 1.4 !important;
        }
        .terms-conditions-content p:last-child {
            margin-bottom: 0 !important;
        }

        @media print {
            @page {
                margin: 0 !important;
            }

            /* Hide unnecessary UI elements completely */
            .nxl-sidebar,
            .nxl-header,
            .page-header,
            .d-print-none,
            .alert,
            header,
            footer,
            aside,
            nav {
                display: none !important;
            }

            /* Reset container layouts and margins */
            body {
                background: #fff !important;
                margin: 0 !important;
                padding: 8mm 12mm !important; /* Manual clean margins */
            }

            .nxl-container,
            .nxl-content,
            .main-content,
            .content-body,
            .container-fluid {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-shadow: none !important;
                transform: none !important;
                top: 0 !important;
                position: static !important;
            }

            /* Print area styling */
            .print-area {
                border: 0 !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                position: static !important;
            }

            /* Reduce card body padding for printing */
            .card-body.p-5 {
                padding: 0 !important;
            }

            /* Reduce spaces to fit content on one page */
            .mb-5 {
                margin-bottom: 1rem !important;
            }
            .my-5 {
                margin-top: 1rem !important;
                margin-bottom: 1rem !important;
            }
            .mt-5 {
                margin-top: 1rem !important;
            }
            .mb-4 {
                margin-bottom: 0.75rem !important;
            }
            hr {
                margin: 0.75rem 0 !important;
            }
        }
    </style>
@endpush

@if(request()->has('print'))
    @push('scripts')
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                window.print();
            });
        </script>
    @endpush
@endif
