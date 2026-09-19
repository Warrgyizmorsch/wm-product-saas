@extends('layouts.duralux')

@section('title', 'Quotation details ' . $quotation->quotation_number . ' | SaaS ERP')
@section('page-title', 'Sales Quotation')
@section('breadcrumb', 'CRM / Quotations / ' . $quotation->quotation_number)

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('crm.quotations.index') }}" class="action-dropdown-btn" title="Back to Quotations" data-bs-toggle="tooltip">
            <i class="feather feather-arrow-left"></i>
        </a>

        <a href="javascript:void(0)" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold px-3">
            <i class="feather-printer me-1.5"></i>Print
        </a>

        @if ($quotation->status === 'Accepted')
            @php
                $deal = $quotation->crmDeal;
                $hasCustomer = ($deal && strtolower($deal->stage) === 'won')
                    || !empty($deal?->account?->customer_id)
                    || !empty($quotation->account?->customer_id);
            @endphp
            @if (!$hasCustomer)
                @if ($quotation->crm_deal_id)
                    <a href="{{ route('crm.deals.showConvertForm', $quotation->crm_deal_id) }}" class="btn btn-sm btn-warning text-dark fw-bold px-3">
                        <i class="feather-user-check me-1.5"></i>Convert to Customer
                    </a>
                @else
                    <a href="{{ route('crm.quotations.showConvertForm', $quotation->id) }}" class="btn btn-sm btn-warning text-dark fw-bold px-3">
                        <i class="feather-user-check me-1.5"></i>Convert to Customer
                    </a>
                @endif
            @else
                <a href="{{ route('sales.orders.create', ['quotation_id' => $quotation->id]) }}" class="btn btn-sm btn-success fw-bold px-3">
                    <i class="feather-shopping-cart me-1.5"></i>Convert to Sales Order
                </a>
            @endif
        @endif

        <!-- MORE Dropdown Button -->
        <div class="dropdown">
            <button class="btn btn-sm btn-light border fw-bold px-3 py-1.5 dropdown-toggle d-inline-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More Quotation Actions">
                <i class="feather-more-horizontal me-1"></i>MORE
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border p-1" style="min-width: 210px;">
                <li>
                    <button type="button" 
                            class="dropdown-item py-2 fs-12 fw-medium btn-open-send-quote-wa-modal d-flex align-items-center" 
                            data-quotation-id="{{ $quotation->id }}" 
                            data-quotation-num="{{ $quotation->quotation_number }}" 
                            data-client-phone="{{ $quotation->prepared_for_phone !== '—' ? $quotation->prepared_for_phone : ($quotation->deal?->contact?->phone ?: ($quotation->lead?->company_phone ?: $quotation->lead?->phone)) }}" 
                            data-deal-title="{{ addslashes($quotation->deal?->title ?? 'Quotation') }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" class="me-2"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 2.15.68 4.14 1.838 5.776L2.5 21.5l3.876-1.303A9.957 9.957 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18a7.96 7.96 0 01-4.086-1.125l-.293-.174-2.295.771.785-2.238-.191-.304A7.96 7.96 0 014 12c0-4.418 3.582-8 8-8s8 3.582 8 8-3.582 8-8 8z" fill="#25D366"/><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.447-.521.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.149-.669-1.612-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.124-.272-.198-.57-.347z" fill="#25D366"/></svg>
                        Send via WhatsApp
                    </button>
                </li>
                <li>
                    <button type="button" 
                            class="dropdown-item py-2 fs-12 fw-medium btn-open-send-quote-modal d-flex align-items-center" 
                            data-quotation-id="{{ $quotation->id }}" 
                            data-quotation-num="{{ $quotation->quotation_number }}" 
                            data-client-email="{{ $quotation->prepared_for_email !== '—' ? $quotation->prepared_for_email : ($quotation->deal?->contact?->email ?: ($quotation->lead?->company_email ?: $quotation->lead?->email)) }}" 
                            data-deal-title="{{ addslashes($quotation->deal?->title ?? 'Quotation') }}">
                        <i class="feather-mail me-2 text-primary fs-14"></i>
                        Send via Email
                    </button>
                </li>
                <li>
                    <a href="{{ route('crm.quotations.download', $quotation->id) }}" class="dropdown-item py-2 fs-12 fw-medium d-flex align-items-center">
                        <i class="feather-download me-2 text-danger fs-14"></i>
                        Download PDF Document
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a href="javascript:void(0)" onclick="window.print()" class="dropdown-item py-2 fs-12 fw-medium d-flex align-items-center">
                        <i class="feather-printer me-2 text-secondary fs-14"></i>
                        Print Quotation
                    </a>
                </li>
            </ul>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .invoice-sheet {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            padding: 40px;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            position: relative;
            overflow: hidden;
        }

        .invoice-corner-ribbon {
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            overflow: hidden;
            pointer-events: none;
            z-index: 10;
        }

        .invoice-corner-ribbon .ribbon-inner {
            position: absolute;
            top: 16px;
            right: -24px;
            width: 105px;
            transform: rotate(45deg);
            text-align: center;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 4px 0;
            color: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
        }

        .ribbon-accepted, .ribbon-approved {
            background-color: #16a34a;
        }
        .ribbon-sent, .ribbon-quotation-sent {
            background-color: #2563eb;
        }
        .ribbon-draft {
            background-color: #64748b;
        }
        .ribbon-rejected, .ribbon-declined {
            background-color: #dc2626;
        }
        .ribbon-rework {
            background-color: #d97706;
        }

        .invoice-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 2px solid #cbd5e1;
            padding: 10px 14px;
        }
        .invoice-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            color: #1e293b;
        }

        .terms-conditions-content p {
            margin-bottom: 4px !important;
            line-height: 1.4 !important;
        }
        .terms-conditions-content p:last-child {
            margin-bottom: 0 !important;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0 !important;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            body > *,
            .nxl-container,
            .nxl-content,
            .page-header,
            .main-content,
            .nxl-navigation,
            .nxl-header,
            footer,
            .loader-bg {
                visibility: hidden;
            }

            .invoice-sheet,
            .invoice-sheet * {
                visibility: visible;
            }

            html, body, main, .nxl-container, .nxl-content, .main-content, .row, .col-12 {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                font-size: 10px !important;
            }
            .invoice-sheet {
                box-shadow: none !important;
                border: 0 !important;
                margin: 0 auto !important;
                padding: 2mm 6mm 2mm 6mm !important;
                width: 100% !important;
                max-width: 100% !important;
                position: relative !important;
            }

            .invoice-sheet > div:first-child,
            .invoice-sheet > .row:first-child {
                margin-top: 0 !important;
                padding-top: 0 !important;
            }

            .invoice-sheet .row {
                --bs-gutter-x: 0.75rem !important;
                --bs-gutter-y: 0.35rem !important;
                margin-bottom: 4px !important;
            }

            .invoice-sheet .avatar-text {
                width: 36px !important;
                height: 36px !important;
                font-size: 1rem !important;
            }

            .invoice-sheet h2 {
                font-size: 17px !important;
                margin-bottom: 2px !important;
            }

            .invoice-sheet h4 {
                font-size: 13.5px !important;
                margin-bottom: 2px !important;
            }

            .invoice-sheet .invoice-table thead th {
                padding: 3px 5px !important;
                font-size: 9.5px !important;
            }

            .invoice-sheet .invoice-table td {
                padding: 3px 5px !important;
                font-size: 10px !important;
            }

            .invoice-sheet .mb-4,
            .invoice-sheet .mb-3,
            .invoice-sheet .mb-5 {
                margin-bottom: 4px !important;
            }

            .invoice-sheet .mt-4,
            .invoice-sheet .mt-5 {
                margin-top: 4px !important;
            }

            .invoice-sheet .p-3 {
                padding: 5px 8px !important;
            }

            .invoice-corner-ribbon {
                display: none !important;
            }

            .d-print-none {
                display: none !important;
                visibility: hidden !important;
            }

            tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
@endpush

@section('content')
    <div class="row text-dark">
        <div class="col-12">

            @if (in_array($quotation->status, ['Rejected', 'Declined']))
                <div class="alert alert-danger border-danger border-start border-4 shadow-sm mb-4 d-print-none" role="alert" style="background-color: #fff5f5;">
                    <div class="d-flex align-items-start">
                        <div class="avatar-text avatar-md bg-danger text-white me-3 mt-0.5 rounded-circle flex-shrink-0">
                            <i class="feather-x-circle fs-18"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="alert-heading fw-bold text-danger mb-1"><i class="feather-alert-triangle me-1"></i> Quotation Rejected</h6>
                            <p class="fs-13 text-dark mb-0">
                                <strong>Rejection Reason / Remarks:</strong> 
                                <span class="text-danger fw-semibold">{{ $quotation->rejection_reason ?: 'No specific reason provided.' }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Standard ERP Customer Quotation Sheet -->
            <div class="invoice-sheet print-area mb-5">
                <!-- Status Corner Ribbon Tag Patti -->
                <div class="invoice-corner-ribbon">
                    @php
                        $statusSlug = strtolower(str_replace(' ', '-', $quotation->status));
                        $ribbonClass = match($quotation->status) {
                            'Accepted', 'Approved' => 'ribbon-accepted',
                            'Sent', 'Quotation Sent' => 'ribbon-sent',
                            'Rejected', 'Declined' => 'ribbon-rejected',
                            'Rework', 'Quotation Rework' => 'ribbon-rework',
                            default => 'ribbon-draft',
                        };
                    @endphp
                    <div class="ribbon-inner {{ $ribbonClass }}">
                        {{ $quotation->status }}
                    </div>
                </div>

                <!-- 1. Document Header -->
                <div class="row align-items-start pb-4 border-bottom mb-4">
                    <div class="col-7">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-text bg-primary text-white fs-2 fw-bold me-3 shadow-sm d-flex align-items-center justify-content-center" style="border-radius: 8px; width: 50px; height: 50px; flex-shrink: 0; background-color: #1e40af !important;">
                                {{ strtoupper(substr(tenant() ? tenant()->name : 'ERP', 0, 1)) }}
                            </div>
                            <div>
                                <h4 class="fw-bold text-dark mb-0 fs-17">{{ tenant() ? tenant()->name : 'SaaS ERP Workspace' }}</h4>
                                <span class="fs-11 text-muted">Official Corporate Sales Unit</span>
                            </div>
                        </div>
                        <div class="fs-12 text-secondary leading-relaxed">
                            <div>H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India</div>
                            <div><strong>GSTIN:</strong> 08AAFCS1234E1Z0 &nbsp;|&nbsp; <strong>State Code:</strong> 08 (Rajasthan)</div>
                            <div><strong>Email:</strong> {{ tenant() ? tenant()->billing_email : 'sales@saaserp.com' }} &nbsp;|&nbsp; <strong>Phone:</strong> +91 294 2440230</div>
                        </div>
                    </div>

                    <div class="col-5 text-end">
                        <h2 class="fw-black text-uppercase tracking-wide mb-1" style="color: #1e40af; font-size: 22px; letter-spacing: 1px;">SALES QUOTATION</h2>
                        <div class="fs-14 fw-bold text-dark"># {{ $quotation->quotation_number }}</div>
                        
                        <div class="mt-3 fs-12 text-secondary">
                            <div class="d-flex justify-content-end gap-2 mb-1">
                                <span class="text-muted">Quotation Date:</span>
                                <strong class="text-dark">{{ $quotation->quotation_date ? date('d-M-Y', strtotime($quotation->quotation_date)) : '—' }}</strong>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mb-1">
                                <span class="text-muted">Valid Until:</span>
                                <strong class="text-dark">{{ $quotation->expiry_date ? date('d-M-Y', strtotime($quotation->expiry_date)) : '—' }}</strong>
                            </div>
                            @if($quotation->salesPerson)
                                <div class="d-flex justify-content-end gap-2">
                                    <span class="text-muted">Sales Rep:</span>
                                    <strong class="text-dark">{{ $quotation->salesPerson->name }}</strong>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- 2. Prepared For & References (2 Box Columns) -->
                <div class="row g-4 mb-4 fs-12 text-dark">
                    <!-- Left: Prepared For -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                            <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">Prepared For:</span>
                            <h6 class="fw-bold text-dark mb-1 fs-14">{{ $quotation->prepared_for_name }}</h6>
                            @if($quotation->prepared_for_email !== '—' || $quotation->prepared_for_phone !== '—')
                                <div class="text-secondary mb-1 d-flex align-items-center flex-wrap gap-2">
                                    @if($quotation->prepared_for_email !== '—')
                                        <span class="d-inline-flex align-items-center">
                                            <i class="feather-mail me-1 fs-11 text-muted"></i>{{ $quotation->prepared_for_email }}
                                        </span>
                                    @endif
                                    @if($quotation->prepared_for_email !== '—' && $quotation->prepared_for_phone !== '—')
                                        <span class="text-muted">|</span>
                                    @endif
                                    @if($quotation->prepared_for_phone !== '—')
                                        <span class="d-inline-flex align-items-center">
                                            <i class="feather-phone me-1 fs-11 text-muted"></i>{{ $quotation->prepared_for_phone }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                            @if ($quotation->prepared_for_address)
                                <div class="mt-2 pt-2 border-top text-secondary fs-11" style="white-space: pre-wrap;">
                                    <strong>Billing Address:</strong><br>{{ $quotation->prepared_for_address }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Right: References & Revision Info -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                            <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">Quotation References:</span>
                            
                            @if ($quotation->crmDeal)
                                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                    <span class="text-muted">CRM Deal Ref:</span>
                                    <a href="{{ route('crm.deals.show', $quotation->crm_deal_id) }}" class="fw-bold text-primary">
                                        {{ $quotation->crmDeal->deal_number }} ({{ $quotation->crmDeal->title }})
                                    </a>
                                </div>
                            @elseif ($quotation->lead)
                                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                    <span class="text-muted">Lead Ref:</span>
                                    <a href="{{ route('crm.leads.show', $quotation->lead_id) }}" class="fw-bold text-primary">
                                        {{ $quotation->lead->title ?: $quotation->lead->name }}
                                    </a>
                                </div>
                            @endif

                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">Revision Count:</span>
                                <span class="fw-semibold text-dark">Revision {{ $quotation->revision_number }}</span>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Status:</span>
                                <span class="fw-bold text-dark">{{ $quotation->status }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Order Lines Table -->
                <div class="mb-4">
                    <div class="table-responsive">
                        <table class="table invoice-table align-middle w-100 mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 5%;" class="text-center">#</th>
                                    <th style="width: 45%;">Item & Description</th>
                                    <th class="text-end" style="width: 12%;">Qty</th>
                                    <th class="text-end" style="width: 13%;">Rate</th>
                                    <th class="text-end" style="width: 10%;">Tax %</th>
                                    <th class="text-end" style="width: 15%;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quotation->items as $idx => $item)
                                    <tr>
                                        <td class="text-center text-muted fs-12">{{ $idx + 1 }}</td>
                                        <td>
                                            <strong class="text-dark d-block fs-13">{{ $item->item_name }}</strong>
                                            @if($item->description)
                                                <small class="text-muted d-block fs-11 mt-0.5">{{ $item->description }}</small>
                                            @endif
                                        </td>
                                        <td class="text-end font-monospace">{{ $item->quantity }}</td>
                                        <td class="text-end font-monospace">{{ format_currency($item->unit_price) }}</td>
                                        <td class="text-end font-monospace">{{ number_format($item->tax_rate, 2) }}%</td>
                                        <td class="text-end font-monospace fw-bold text-dark">{{ format_currency($item->total_price ?: ($item->amount ?: ($item->quantity * $item->unit_price))) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. Calculations & Terms Section -->
                <div class="row g-4 border-top pt-3">
                    <!-- Left Column: Terms & Conditions and Notes -->
                    <div class="col-7">
                        @if($quotation->terms_conditions)
                            <div class="mb-3">
                                <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-1" style="letter-spacing: 0.5px;">Terms & Conditions:</span>
                                <div class="text-secondary fs-12 terms-conditions-content">{!! $quotation->terms_conditions !!}</div>
                            </div>
                        @endif

                        @if($quotation->notes)
                            <div class="mb-2">
                                <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-1" style="letter-spacing: 0.5px;">Internal Notes:</span>
                                <div class="text-secondary fs-12 p-2.5 rounded bg-light border" style="white-space: pre-line;">{{ $quotation->notes }}</div>
                            </div>
                        @endif
                    </div>

                    <!-- Right Column: Totals Summary -->
                    <div class="col-5">
                        <div class="p-3 bg-light bg-opacity-50 rounded border">
                            <div class="d-flex justify-content-between py-1 border-bottom fs-12">
                                <span class="text-muted">Subtotal:</span>
                                <span class="fw-bold text-dark">{{ format_currency($quotation->subtotal) }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom fs-12">
                                <span class="text-muted">Tax Amount (GST):</span>
                                <span class="fw-bold text-dark">{{ format_currency($quotation->tax) }}</span>
                            </div>
                            @if($quotation->discount > 0)
                                <div class="d-flex justify-content-between py-1 border-bottom fs-12 text-danger">
                                    <span>Discount:</span>
                                    <span class="fw-bold">-{{ format_currency($quotation->discount) }}</span>
                                </div>
                            @endif
                            <div class="d-flex justify-content-between py-2 mt-2 fs-15 rounded px-2" style="background-color: #f1f5f9;">
                                <span class="fw-bold text-dark">Total Payable:</span>
                                <span class="fw-extrabold text-primary">{{ format_currency($quotation->total_amount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Signature Footer Block -->
                <div class="row align-items-end mt-5 pt-4 border-top">
                    <div class="col-7">
                        <span class="fs-11 text-muted d-block">This sales quotation is valid until {{ $quotation->expiry_date ? date('d-M-Y', strtotime($quotation->expiry_date)) : 'the expiry date' }}.</span>
                        <span class="fs-11 text-muted d-block">For any queries, please contact our support at {{ tenant() ? tenant()->billing_email : 'sales@saaserp.com' }}.</span>
                    </div>
                    <div class="col-5 text-end">
                        <div class="d-inline-block text-center" style="min-width: 180px;">
                            <div class="border-top pt-1 text-uppercase text-muted fs-10 fw-bold" style="letter-spacing: 0.5px;">
                                Authorized Signature
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

<!-- SEND QUOTATION EMAIL MODAL WITH PDF ATTACHMENT -->
<x-ui.modal id="sendQuotationEmailModal" title="<i class='feather-send text-success me-1.5'></i>Send Quotation PDF Email to Client" size="lg" :centered="true" :showFooter="false">
    <form id="sendQuotationEmailForm" action="" method="POST">
        @csrf
        @php
            $availableSmtps = \App\Models\EmailConfiguration::where('is_active', true)->orderByDesc('is_default')->get();
        @endphp
        @if($availableSmtps->isNotEmpty())
            <div class="mb-3">
                <x-ui.modal-form-ui type="select" label="From SMTP Account" name="account_id" :searchable="false">
                    @foreach($availableSmtps as $s)
                        <option value="{{ $s->id }}" @selected($s->is_default)>{{ $s->name }} ({{ $s->email_address }})</option>
                    @endforeach
                </x-ui.modal-form-ui>
            </div>
        @endif

        <div class="mb-3">
            <x-ui.modal-form-ui type="input" inputType="email" label="Client Email Address (To)" name="to_email" id="sendQuoteToEmail" placeholder="client@company.com" :required="true" />
        </div>

        <div class="mb-3">
            <x-ui.modal-form-ui type="input" label="Subject" name="subject" id="sendQuoteSubject" :required="true" />
        </div>

        <div class="p-2.5 rounded border bg-light-subtle mb-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="feather-paperclip text-primary fs-16"></i>
                <span class="fs-12 fw-bold text-dark" id="sendQuotePdfBadge">Quotation.pdf</span>
                <span class="badge bg-soft-danger text-danger border px-1.5 py-0.5 fs-10">PDF Attached</span>
            </div>
            <span class="fs-11 text-muted">Auto-generated via DomPDF</span>
        </div>

        <div class="mb-3">
            <x-ui.modal-form-ui type="textarea" label="Message Body" name="body_html" id="sendQuoteBody" rows="6" :required="true" />
        </div>

        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <span class="fs-11 text-muted"><i class="feather-info me-1"></i>Email will be dispatched immediately via SMTP server.</span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="btnSubmitSendQuoteEmail" class="btn btn-sm btn-success fw-bold px-4">
                    <i class="feather-send me-1"></i>Send Email Now
                </button>
            </div>
        </div>
    </form>
</x-ui.modal>

<!-- SEND QUOTATION WHATSAPP MODAL -->
<x-ui.modal id="sendQuotationWhatsAppModal" title="<i class='feather-message-circle text-success me-1.5'></i>Send Quotation PDF via WhatsApp" size="lg" :centered="true" :showFooter="false">
    <form id="sendQuotationWhatsAppForm" action="" method="POST">
        @csrf
        
        <!-- WhatsApp Connection Status Banner -->
        <div id="waStatusContainer" class="p-3 rounded border mb-3 text-start fs-12 bg-light">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span id="waStatusBadge" class="badge bg-secondary">Checking WhatsApp...</span>
                    <span id="waStatusText" class="text-muted fs-11">Connecting to WhatsApp Baileys bridge...</span>
                </div>
                <button type="button" id="btnConnectWA" class="btn btn-xs btn-outline-success fw-bold d-none">
                    <i class="feather-smartphone me-1"></i>Connect / QR Scan
                </button>
            </div>
            <div id="waQrContainer" class="text-center mt-3 d-none">
                <p class="fs-12 fw-bold text-dark mb-1">Scan QR Code from WhatsApp app (Linked Devices)</p>
                <img id="waQrImg" src="" alt="WhatsApp QR Code" class="img-thumbnail" style="max-width: 200px;">
                <p class="fs-11 text-muted mt-1">Open WhatsApp on your phone -> Settings/Menu -> Linked Devices -> Link a Device</p>
            </div>
        </div>

        <div class="mb-3">
            <x-ui.modal-form-ui type="input" label="Recipient Mobile / WhatsApp Number" name="phone" id="sendWaPhone" placeholder="9876543210 (Country code 91 auto-added)" :required="true" />
        </div>

        <div class="p-2.5 rounded border bg-light-subtle mb-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="feather-paperclip text-success fs-16"></i>
                <span class="fs-12 fw-bold text-dark" id="sendWaPdfBadge">Quotation.pdf</span>
                <span class="badge bg-soft-success text-success border px-1.5 py-0.5 fs-10">PDF Attached</span>
            </div>
            <span class="fs-11 text-muted">Base64 PDF Attachment</span>
        </div>

        <div class="mb-3">
            <x-ui.modal-form-ui type="textarea" label="Caption / Message Text" name="caption" id="sendWaCaption" rows="5" :required="true" />
        </div>

        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <span class="fs-11 text-muted"><i class="feather-info me-1"></i>Document will be sent directly via linked WhatsApp account.</span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="btnSubmitSendQuoteWA" class="btn btn-sm btn-success fw-bold px-4">
                    <i class="feather-send me-1"></i>Send WhatsApp PDF
                </button>
            </div>
        </div>
    </form>
</x-ui.modal>

<!-- RESULT NOTIFICATION MODAL (COMMON COMPONENT) -->
<x-ui.modal id="waResultModal" title="<i class='feather-info me-1.5 text-primary'></i>System Notification" size="lg" :centered="true" :showFooter="false">
    <div class="py-4 px-3 text-center">
        <div id="waResultIcon" class="mb-3 d-flex justify-content-center"></div>
        <h4 id="waResultTitle" class="fw-bold text-dark mb-3 fs-18"></h4>
        <div id="waResultMessage" class="alert alert-light border text-center fs-13 mb-4 font-monospace p-3.5 text-break shadow-2xs rounded-3 mx-auto" style="max-width: 520px; background-color: #f8fafc; border-color: #e2e8f0 !important; color: #334155; line-height: 1.6;"></div>
        <div class="d-flex justify-content-center mt-3">
            <button type="button" class="btn btn-primary fw-bold px-5 py-2 fs-13 shadow-2xs rounded-3" data-bs-dismiss="modal" style="min-width: 140px;">OK</button>
        </div>
    </div>
</x-ui.modal>

@push('scripts')
<script>
    function showNotificationModal(isSuccess, title, message) {
        const iconHtml = isSuccess 
            ? '<div class="avatar avatar-xl bg-soft-success text-success rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center shadow-2xs" style="width: 64px; height: 64px; border: 2px solid rgba(34, 197, 94, 0.2);"><i class="feather-check-circle fs-32"></i></div>'
            : '<div class="avatar avatar-xl bg-soft-danger text-danger rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center shadow-2xs" style="width: 64px; height: 64px; border: 2px solid rgba(239, 68, 68, 0.2);"><i class="feather-alert-triangle fs-32"></i></div>';
        
        $('#waResultIcon').html(iconHtml);
        $('#waResultTitle').text(title).attr('class', isSuccess ? 'fw-bold text-success mb-2 fs-18' : 'fw-bold text-danger mb-2 fs-18');
        $('#waResultMessage').text(message);
        
        const modalEl = document.getElementById('waResultModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    function checkWhatsAppStatus() {
        $.ajax({
            url: "{{ route('platform.whatsapp.status') }}",
            method: "GET",
            success: function(res) {
                if (res.status === 'connected') {
                    $('#waStatusBadge').attr('class', 'badge bg-success').text('Connected');
                    const userName = res.user ? (res.user.name || res.user.id || 'Linked Account') : 'Linked Account';
                    $('#waStatusText').text('Connected: ' + userName);
                    $('#waQrContainer').addClass('d-none');
                    $('#btnConnectWA').addClass('d-none');
                    $('#btnSubmitSendQuoteWA').prop('disabled', false);
                } else if (res.status === 'qr' && res.qr) {
                    $('#waStatusBadge').attr('class', 'badge bg-warning text-dark').text('Scan QR');
                    $('#waStatusText').text('Open WhatsApp app on your phone to scan QR code');
                    $('#waQrImg').attr('src', res.qr);
                    $('#waQrContainer').removeClass('d-none');
                    $('#btnConnectWA').addClass('d-none');
                    $('#btnSubmitSendQuoteWA').prop('disabled', true);
                } else if (res.status === 'connecting') {
                    $('#waStatusBadge').attr('class', 'badge bg-info text-dark').text('Connecting...');
                    $('#waStatusText').text('Initializing Baileys socket...');
                    $('#waQrContainer').addClass('d-none');
                    $('#btnConnectWA').addClass('d-none');
                    $('#btnSubmitSendQuoteWA').prop('disabled', true);
                } else {
                    $('#waStatusBadge').attr('class', 'badge bg-danger').text('Disconnected');
                    $('#waStatusText').text(res.message || 'No WhatsApp account linked.');
                    $('#waQrContainer').addClass('d-none');
                    $('#btnConnectWA').removeClass('d-none');
                    $('#btnSubmitSendQuoteWA').prop('disabled', true);
                }
            },
            error: function() {
                $('#waStatusBadge').attr('class', 'badge bg-danger').text('Bridge Offline');
                $('#waStatusText').text('Node.js WhatsApp bridge is offline. Start Node.js server (services/whatsapp-bridge).');
                $('#btnConnectWA').removeClass('d-none');
            }
        });
    }

    $(document).on('click', '#btnConnectWA', function() {
        $('#waStatusBadge').attr('class', 'badge bg-info text-dark').text('Connecting...');
        $('#waStatusText').text('Requesting QR code connection...');
        $.ajax({
            url: "{{ route('platform.whatsapp.connect') }}",
            method: "POST",
            data: { _token: "{{ csrf_token() }}" },
            success: function() {
                setTimeout(checkWhatsAppStatus, 1500);
            }
        });
    });

    $(document).on('click', '.btn-open-send-quote-wa-modal', function () {
        const qId = $(this).attr('data-quotation-id');
        const qNum = $(this).attr('data-quotation-num');
        const cPhone = $(this).attr('data-client-phone') || '';
        const dTitle = $(this).attr('data-deal-title') || 'Quotation';

        $('#sendQuotationWhatsAppForm').attr('action', '/crm/quotations/' + qId + '/send-whatsapp');
        $('#sendWaPhone').val(cPhone);
        $('#sendWaPdfBadge').text('Quotation_' + qNum + '.pdf');

        const defaultCaption = "Dear Valued Client,\n\nPlease find attached Quotation *" + qNum + "* for your review regarding " + dTitle + ".\n\nThank you,\nSales Team";
        $('#sendWaCaption').val(defaultCaption);

        const sendWaModal = new bootstrap.Modal(document.getElementById('sendQuotationWhatsAppModal'));
        sendWaModal.show();
        checkWhatsAppStatus();
    });

    $(document).on('submit', '#sendQuotationWhatsAppForm', function (e) {
        e.preventDefault();
        const form = $(this);
        const btn = $('#btnSubmitSendQuoteWA');
        const origHtml = btn.html();

        btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>Sending WhatsApp...');

        $.ajax({
            url: form.attr('action'),
            method: "POST",
            data: form.serialize(),
            success: function (res) {
                btn.prop('disabled', false).html(origHtml);
                const modalEl = document.getElementById('sendQuotationWhatsAppModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                showNotificationModal(true, "WhatsApp Message Delivered!", res.message);
            },
            error: function (xhr) {
                btn.prop('disabled', false).html(origHtml);
                const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to send WhatsApp document.';
                const modalEl = document.getElementById('sendQuotationWhatsAppModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                showNotificationModal(false, "WhatsApp Dispatch Failed", errMsg);
            }
        });
    });

    $(document).on('click', '.btn-open-send-quote-modal', function () {
        const qId = $(this).attr('data-quotation-id');
        const qNum = $(this).attr('data-quotation-num');
        const cEmail = $(this).attr('data-client-email') || '';
        const dTitle = $(this).attr('data-deal-title') || 'Quotation';

        $('#sendQuotationEmailForm').attr('action', '/crm/quotations/' + qId + '/send-email');
        $('#sendQuoteToEmail').val(cEmail);
        $('#sendQuoteSubject').val('Quotation ' + qNum + ' - ' + dTitle);
        $('#sendQuotePdfBadge').text('Quotation_' + qNum + '.pdf');

        const defaultBody = "Dear Valued Client,\n\nPlease find attached Quotation " + qNum + " for your review regarding " + dTitle + ".\n\nWe look forward to your feedback. Please let us know if you have any questions.\n\nBest regards,\nSales Team";
        $('#sendQuoteBody').val(defaultBody);

        const sendModal = new bootstrap.Modal(document.getElementById('sendQuotationEmailModal'));
        sendModal.show();
    });

    $(document).on('submit', '#sendQuotationEmailForm', function (e) {
        e.preventDefault();
        const form = $(this);
        const btn = $('#btnSubmitSendQuoteEmail');
        const origHtml = btn.html();

        btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>Sending Email...');

        $.ajax({
            url: form.attr('action'),
            method: "POST",
            data: form.serialize(),
            success: function (res) {
                btn.prop('disabled', false).html(origHtml);
                const modalEl = document.getElementById('sendQuotationEmailModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                showNotificationModal(true, "Quotation Email Dispatched!", res.message);
            },
            error: function (xhr) {
                btn.prop('disabled', false).html(origHtml);
                const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to send Quotation Email.';
                const modalEl = document.getElementById('sendQuotationEmailModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                showNotificationModal(false, "Email Dispatch Failed", errMsg);
            }
        });
    });
</script>
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
