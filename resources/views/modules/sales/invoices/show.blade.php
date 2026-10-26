@extends('layouts.duralux')

@section('title', __('crm.tax_invoice') . ' ' . $invoice->invoice_number . ' | SaaS ERP')
@section('page-title', __('crm.tax_invoice'))
@section('breadcrumb')
    <a href="{{ route('sales.invoices.index') }}">{{ __('crm.invoices') }}</a> &gt; {{ $invoice->invoice_number }}
@endsection

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('sales.invoices.index') }}" class="btn btn-sm btn-light border d-inline-flex align-items-center justify-content-center px-2.5" title="{{ __('crm.back_to_invoices') }}" data-bs-toggle="tooltip">
            <i class="feather feather-arrow-left"></i>
        </a>

        @if ($invoice->status === 'Draft')
            <form action="{{ route('sales.invoices.post', $invoice->id) }}" method="POST" class="d-inline-flex align-items-center m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 d-inline-flex align-items-center">
                    <i class="feather-check-circle me-1.5"></i>{{ __('crm.mark_as_post') }}
                </button>
            </form>
        @endif

        @if (in_array($invoice->status, ['Posted', 'Partially Paid']) && $invoice->balance_due > 0)
            <a href="{{ route('sales.payments.create', ['invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id ?: $invoice->salesOrder?->customer_id]) }}" class="btn btn-sm btn-success fw-bold px-3 d-inline-flex align-items-center">
                <i class="feather-dollar-sign me-1.5"></i>{{ __('crm.register_payment') }}
            </a>
        @endif

        @php
            $isCancelled = in_array(strtolower((string)$invoice->status), ['cancelled', 'void']);
        @endphp

        @if (!$isCancelled && $invoice->status !== 'Draft')
            <!-- E-Invoice Action Buttons -->
            @if ($invoice->einvoice_status === 'Generated')
                <div class="dropdown d-inline-flex align-items-center">
                    <button class="btn btn-sm btn-soft-success text-success border border-success-subtle fw-bold px-3 dropdown-toggle d-inline-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="feather-check-circle me-1.5 text-success"></i>E-Invoice (IRN)
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border p-1.5" style="min-width: 290px;">
                        <li><span class="dropdown-header fs-10 text-uppercase fw-bold text-muted px-3 py-1 font-monospace text-truncate d-block">IRN: {{ substr($invoice->irn, 0, 18) }}...</span></li>
                        <li>
                            <button type="button" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium d-flex align-items-center text-nowrap" data-bs-toggle="modal" data-bs-target="#viewEInvoiceQrModal">
                                <i class="feather-maximize me-2.5 text-primary fs-14"></i>View / Scan Signed QR
                            </button>
                        </li>
                        <li>
                            <a href="{{ route('sales.invoices.einvoice.export-json', $invoice->id) }}" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium d-flex align-items-center text-success text-nowrap">
                                <i class="feather-code me-2.5 text-success fs-14"></i>Export NIC JSON (Govt Schema)
                            </a>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium d-flex align-items-center text-nowrap" onclick="navigator.clipboard.writeText('{{ $invoice->irn }}'); alert('IRN copied to clipboard!');">
                                <i class="feather-copy me-2.5 text-secondary fs-14"></i>Copy IRN Hash
                            </button>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <button type="button" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium text-danger d-flex align-items-center text-nowrap" data-bs-toggle="modal" data-bs-target="#cancelEInvoiceModal">
                                <i class="feather-slash me-2.5 text-danger fs-14"></i>Cancel E-Invoice
                            </button>
                        </li>
                    </ul>
                </div>
            @else
                <form action="{{ route('sales.invoices.einvoice.generate', $invoice->id) }}" method="POST" class="d-inline-flex align-items-center m-0" onsubmit="return confirm('Generate Government GST E-Invoice (IRN)?');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 d-inline-flex align-items-center">
                        <i class="feather-zap me-1.5"></i>Generate E-Invoice
                    </button>
                </form>
            @endif

            <!-- E-Way Bill Action Buttons -->
            @if ($invoice->eway_bill_status === 'Generated')
                <div class="dropdown d-inline-flex align-items-center">
                    <button class="btn btn-sm btn-soft-info text-info border border-info-subtle fw-bold px-3 dropdown-toggle d-inline-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="feather-truck me-1.5 text-info"></i>EWB: {{ $invoice->eway_bill_no }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border p-1.5" style="min-width: 290px;">
                        <li><span class="dropdown-header fs-10 text-uppercase fw-bold text-muted px-3 py-1 text-nowrap d-block">Valid Till: {{ $invoice->eway_bill_valid_till ? date('d-M-Y H:i', strtotime($invoice->eway_bill_valid_till)) : '—' }}</span></li>
                        <li>
                            <button type="button" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium d-flex align-items-center text-nowrap" onclick="navigator.clipboard.writeText('{{ $invoice->eway_bill_no }}'); alert('E-Way Bill number copied!');">
                                <i class="feather-copy me-2.5 text-secondary fs-14"></i>Copy EWB Number
                            </button>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <button type="button" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium text-danger d-flex align-items-center text-nowrap" data-bs-toggle="modal" data-bs-target="#cancelEWayBillModal">
                                <i class="feather-x-circle me-2.5 text-danger fs-14"></i>Cancel E-Way Bill
                            </button>
                        </li>
                    </ul>
                </div>
            @else
                <button type="button" class="btn btn-sm btn-outline-info fw-bold px-3 d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#generateEWayBillModal">
                    <i class="feather-truck me-1.5"></i>Generate E-Way Bill
                </button>
            @endif

            <a href="{{ route('sales.invoices.einvoice.export-json', $invoice->id) }}" class="btn btn-sm btn-outline-success fw-bold px-3 d-inline-flex align-items-center" title="Download Official Government GEPP JSON">
                <i class="feather-code me-1.5"></i>Export JSON
            </a>
        @elseif ($isCancelled)
            {{-- When invoice is cancelled, only allow viewing existing E-Invoice/E-Way Bill if they were generated before cancellation --}}
            @if ($invoice->einvoice_status === 'Generated')
                <div class="dropdown d-inline-flex align-items-center">
                    <button class="btn btn-sm btn-soft-secondary text-secondary border fw-bold px-3 dropdown-toggle d-inline-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="feather-check-circle me-1.5"></i>E-Invoice (IRN)
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border p-1.5" style="min-width: 290px;">
                        <li><span class="dropdown-header fs-10 text-uppercase fw-bold text-muted px-3 py-1 font-monospace text-truncate d-block">IRN: {{ substr($invoice->irn, 0, 18) }}...</span></li>
                        <li>
                            <button type="button" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium d-flex align-items-center text-nowrap" data-bs-toggle="modal" data-bs-target="#viewEInvoiceQrModal">
                                <i class="feather-maximize me-2.5 text-primary fs-14"></i>View / Scan Signed QR
                            </button>
                        </li>
                        <li>
                            <a href="{{ route('sales.invoices.einvoice.export-json', $invoice->id) }}" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium d-flex align-items-center text-success text-nowrap">
                                <i class="feather-code me-2.5 text-success fs-14"></i>Export NIC JSON (Govt Schema)
                            </a>
                        </li>
                    </ul>
                </div>
            @endif

            @if ($invoice->eway_bill_status === 'Generated')
                <div class="dropdown d-inline-flex align-items-center">
                    <button class="btn btn-sm btn-soft-secondary text-secondary border fw-bold px-3 dropdown-toggle d-inline-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="feather-truck me-1.5"></i>EWB: {{ $invoice->eway_bill_no }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border p-1.5" style="min-width: 290px;">
                        <li><span class="dropdown-header fs-10 text-uppercase fw-bold text-muted px-3 py-1 text-nowrap d-block">Valid Till: {{ $invoice->eway_bill_valid_till ? date('d-M-Y H:i', strtotime($invoice->eway_bill_valid_till)) : '—' }}</span></li>
                    </ul>
                </div>
            @endif
        @endif

        <a href="{{ route('sales.invoices.download', $invoice->id) }}" class="btn btn-sm btn-outline-danger fw-bold px-3 d-inline-flex align-items-center" title="Download PDF Document">
            <i class="feather-download me-1.5"></i>PDF
        </a>

        @php
            $hasAvailableAdvances = (!empty($availableAdvances) && $availableAdvances->isNotEmpty()) || (!empty($unallocatedPayments) && $unallocatedPayments->isNotEmpty());
            $totalAvailAdvance = (!empty($availableAdvances) ? $availableAdvances->sum('allocated_amount') : 0) + (!empty($unallocatedPayments) ? $unallocatedPayments->sum('allocated_amount') : 0);
        @endphp

        @php
            $clientPhone = $invoice->customer?->phone ?: ($invoice->salesOrder?->customer?->phone ?: '');
            $clientEmail = $invoice->customer?->email ?: ($invoice->salesOrder?->customer?->email ?: '');
        @endphp

        <!-- MORE Dropdown Button -->
        <div class="dropdown d-inline-flex align-items-center">
            <button class="btn btn-sm btn-light border fw-bold px-3 dropdown-toggle d-inline-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More Invoice Actions">
                <i class="feather-more-horizontal me-1"></i>MORE
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border p-1.5" style="min-width: 290px;">
                <li>
                    <button type="button" 
                            class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium btn-open-send-invoice-wa-modal d-flex align-items-center text-nowrap" 
                            data-invoice-id="{{ $invoice->id }}" 
                            data-invoice-num="{{ $invoice->invoice_number }}" 
                            data-client-phone="{{ $clientPhone }}" 
                            data-total-amount="{{ format_currency($invoice->total_amount) }}"
                            data-balance-due="{{ format_currency($invoice->balance_due) }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" class="me-2.5"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 2.15.68 4.14 1.838 5.776L2.5 21.5l3.876-1.303A9.957 9.957 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18a7.96 7.96 0 01-4.086-1.125l-.293-.174-2.295.771.785-2.238-.191-.304A7.96 7.96 0 014 12c0-4.418 3.582-8 8-8s8 3.582 8 8-3.582 8-8 8z" fill="#25D366"/><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.447-.521.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.149-.669-1.612-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.124-.272-.198-.57-.347z" fill="#25D366"/></svg>
                        Send via WhatsApp
                    </button>
                </li>
                <li>
                    <button type="button" 
                            class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium btn-open-send-invoice-modal d-flex align-items-center text-nowrap" 
                            data-invoice-id="{{ $invoice->id }}" 
                            data-invoice-num="{{ $invoice->invoice_number }}" 
                            data-client-email="{{ $clientEmail }}" 
                            data-total-amount="{{ format_currency($invoice->total_amount) }}"
                            data-balance-due="{{ format_currency($invoice->balance_due) }}">
                        <i class="feather-mail me-2.5 text-primary fs-14"></i>
                        Send via Email
                    </button>
                </li>
                <li>
                    <a href="{{ route('sales.invoices.download', $invoice->id) }}" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium d-flex align-items-center text-nowrap">
                        <i class="feather-download me-2.5 text-danger fs-14"></i>
                        Download PDF Document
                    </a>
                </li>
                <li>
                    <a href="{{ route('sales.invoices.einvoice.export-json', $invoice->id) }}" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium d-flex align-items-center text-success text-nowrap">
                        <i class="feather-code me-2.5 text-success fs-14"></i>
                        Export NIC JSON (Govt Schema)
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a href="javascript:void(0)" onclick="window.print()" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium d-flex align-items-center text-nowrap">
                        <i class="feather-printer me-2.5 text-secondary fs-14"></i>
                        Print Invoice
                    </a>
                </li>

                @if ($invoice->status !== 'Cancelled')
                    <li><hr class="dropdown-divider my-1"></li>
                    @if (!empty($hasDispatched))
                        {{-- Goods WERE Dispatched -> Show Sales Return --}}
                        <li>
                            <a href="{{ route('sales.returns.create', ['invoice_id' => $invoice->id, 'sales_order_id' => $invoice->sales_order_id]) }}" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium text-warning d-flex align-items-center text-nowrap">
                                <i class="feather-rotate-ccw me-2.5 text-warning fs-14"></i>
                                <span>{{ __('crm.create_sales_return') }} ({{ __('crm.dispatched_items') }})</span>
                            </a>
                        </li>
                    @else
                        {{-- Goods NOT Dispatched -> Show Direct Financial Credit Note --}}
                        <li>
                            <button type="button" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium text-danger d-flex align-items-center text-nowrap" data-bs-toggle="modal" data-bs-target="#createCreditNoteModal">
                                <i class="feather-file-minus me-2.5 text-danger fs-14"></i>
                                <span>{{ __('crm.create_credit_note') }} ({{ __('crm.financial_only') }})</span>
                            </button>
                        </li>
                    @endif
                @endif

                @if (!empty($linkedCreditNotes) && $linkedCreditNotes->isNotEmpty())
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><span class="dropdown-header fs-10 text-uppercase fw-bold text-muted px-3 py-1 font-monospace">Linked Credit Notes</span></li>
                    @foreach ($linkedCreditNotes as $cn)
                        <li>
                            <a href="{{ route('accounting.vouchers.credit_note.show', $cn->id) }}" class="dropdown-item py-1.5 px-3 pe-4 fs-12 fw-medium text-danger d-flex align-items-center text-nowrap">
                                <i class="feather-file-text me-2 text-danger fs-13"></i>
                                <span>{{ $cn->journal_number }} ({{ format_currency($cn->total_amount ?? $cn->entries->where('debit', '>', 0)->sum('debit')) }})</span>
                            </a>
                        </li>
                    @endforeach
                @endif
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

        .ribbon-paid {
            background-color: #16a34a;
        }
        .ribbon-partially-paid {
            background-color: #d97706;
        }
        .ribbon-posted {
            background-color: #2563eb;
        }
        .ribbon-sent {
            background-color: #2563eb;
        }
        .ribbon-draft {
            background-color: #64748b;
        }
        .ribbon-cancelled {
            background-color: #dc2626;
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
            padding: 8px 12px;
        }
        .invoice-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 12px;
            color: #1e293b;
        }

        .summary-table td {
            padding: 4px 10px;
            font-size: 12px;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0 !important;
            }

            /* Hide top header, sidebar, buttons, and navigation */
            .nxl-navigation,
            .nxl-header,
            .page-header,
            .page-actions,
            .action-dropdown-btn,
            footer,
            .invoice-corner-ribbon,
            .d-print-none,
            .btn {
                display: none !important;
            }

            /* Reset page wrappers for full height & width printing and remove top offsets */
            html, body, main, .nxl-container, .nxl-content, .main-content, .row, .col-12 {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                height: auto !important;
                overflow: visible !important;
                font-size: 10px !important;
            }

            /* Invoice sheet: static layout with minimal top padding */
            .invoice-sheet {
                position: static !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 2mm 6mm 2mm 6mm !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                background: #ffffff !important;
                overflow: visible !important;
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

            .invoice-sheet h6 {
                font-size: 11px !important;
                margin-bottom: 2px !important;
            }

            /* Ensure background colors in cards & headers print cleanly */
            .invoice-sheet .bg-light,
            .invoice-sheet [class*="bg-light"] {
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .invoice-sheet .invoice-table thead th {
                background-color: #f8fafc !important;
                padding: 3px 5px !important;
                font-size: 9.5px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .invoice-sheet .invoice-table td {
                padding: 3px 5px !important;
                font-size: 10px !important;
            }

            .invoice-sheet .p-3 {
                padding: 4px 8px !important;
            }

            .invoice-sheet .mb-4,
            .invoice-sheet .mb-3,
            .invoice-sheet .mb-5 {
                margin-bottom: 4px !important;
            }

            .invoice-sheet .mt-3,
            .invoice-sheet .mt-4,
            .invoice-sheet .mt-5 {
                margin-top: 4px !important;
            }

            .invoice-sheet .pb-4 {
                padding-bottom: 4px !important;
            }

            .invoice-sheet .pt-2,
            .invoice-sheet .pt-3,
            .invoice-sheet .pt-4 {
                padding-top: 4px !important;
            }

            .invoice-sheet .fs-12,
            .invoice-sheet .fs-13 {
                font-size: 10.5px !important;
            }

            .invoice-sheet .fs-14 {
                font-size: 11.5px !important;
            }

            .summary-table-box .p-3 {
                padding: 5px 8px !important;
            }

            .summary-table-box .mb-2 {
                margin-bottom: 2px !important;
            }

            .summary-table-box .my-2 {
                margin: 2px 0 !important;
            }

            .summary-table-box .py-1\.5 {
                padding-top: 2px !important;
                padding-bottom: 2px !important;
            }

            .summary-table-box .fs-12 {
                font-size: 10px !important;
            }

            .summary-table-box .fs-13 {
                font-size: 10.5px !important;
            }

            .summary-table-box .fs-16 {
                font-size: 13px !important;
            }

            .gst-summary-box table th,
            .gst-summary-box table td {
                padding: 2px 4px !important;
                font-size: 9.5px !important;
            }

            .bank-details-box {
                padding: 4px 8px !important;
                margin-bottom: 4px !important;
            }

            /* Avoid breaking inside table rows */
            tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            /* Page Break Protection for Cards */
            .bank-details-box,
            .gst-summary-box,
            .summary-table-box {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            /* Terms Box & Lists: Allow natural splitting across pages without card border clipping */
            .terms-box {
                border: none !important;
                background: transparent !important;
                padding: 0 !important;
                margin-top: 4px !important;
                page-break-inside: auto !important;
                break-inside: auto !important;
            }

            .terms-box ol {
                list-style-type: decimal !important;
                padding-left: 20px !important;
                margin-left: 0 !important;
                page-break-inside: auto !important;
                break-inside: auto !important;
            }

            .terms-box ul {
                list-style-type: disc !important;
                padding-left: 20px !important;
                margin-left: 0 !important;
                page-break-inside: auto !important;
                break-inside: auto !important;
            }

            /* Restore list-item display so numbers show cleanly in print */
            .terms-box ol > li,
            .terms-box ul > li,
            .terms-box li {
                display: list-item !important;
                list-style-position: outside !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                break-inside: avoid-page !important;
                margin-bottom: 2px !important;
            }

            .terms-box p {
                display: block !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                break-inside: avoid-page !important;
                margin-bottom: 2px !important;
            }

            .terms-box li *,
            .terms-box p * {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .signature-footer-block {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                margin-top: 6px !important;
                padding-top: 4px !important;
            }
        }
    </style>
@endpush
@section('content')
    <div class="row text-dark">
        <div class="col-12">

            @if ($invoice->status === 'Cancelled')
                <div class="alert alert-soft-danger border border-danger-subtle d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-sm d-print-none" style="max-width: 900px; margin: 0 auto 1.5rem auto;">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md bg-danger text-white rounded-circle me-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                            <i class="feather-slash fs-18"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-danger mb-0.5 fs-14">{{ __('crm.invoice_cancelled_title') }}</h6>
                            <p class="text-muted fs-12 mb-0">
                                {{ __('crm.invoice_cancelled_desc') }}
                                @if (!empty($linkedCreditNotes) && $linkedCreditNotes->isNotEmpty())
                                    <span class="ms-1 fw-bold text-dark">
                                        (Credit Note: 
                                        @foreach ($linkedCreditNotes as $cn)
                                            <a href="{{ route('accounting.vouchers.credit_note.show', $cn->id) }}" class="text-danger text-decoration-underline">{{ $cn->journal_number }}</a>{{ !$loop->last ? ', ' : '' }}
                                        @endforeach
                                        )
                                    </span>
                                @endif
                            </p>
                        </div>
                    </div>
                    @if (!empty($linkedCreditNotes) && $linkedCreditNotes->first())
                        <a href="{{ route('accounting.vouchers.credit_note.show', $linkedCreditNotes->first()->id) }}" class="btn btn-sm btn-danger fw-bold px-3 ms-2 flex-shrink-0">
                            <i class="feather-file-text me-1.5"></i>{{ __('crm.view_credit_note') }}
                        </a>
                    @endif
                </div>
            @endif

            @if ($hasAvailableAdvances && $invoice->balance_due > 0 && !in_array(strtolower((string)$invoice->status), ['cancelled', 'void']))
                <!-- Premium Odoo-Style Advance Payment Reconcile Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-3 d-print-none" style="max-width: 900px; margin: 0 auto 1.25rem auto; background: #ffffff; border: 1px solid #e2e8f0 !important; border-left: 5px solid #10b981 !important;">
                    <div class="card-body p-3.5">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 shadow-2xs" style="width: 44px; height: 44px; background: rgba(16, 185, 129, 0.12); color: #059669;">
                                    <i class="feather-credit-card fs-20"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 fs-10 fw-bold text-uppercase">
                                            <i class="feather-zap me-1"></i>Advance Credit Available
                                        </span>
                                        @if($invoice->salesOrder)
                                            <span class="fs-12 text-muted">
                                                Sales Order: <strong class="text-dark">{{ $invoice->salesOrder->sales_order_number }}</strong>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-baseline gap-2">
                                        <span class="fs-13 text-secondary">Available Advance:</span>
                                        <span class="fs-18 fw-bold text-success font-monospace">{{ format_currency($totalAvailAdvance) }}</span>
                                        <span class="fs-12 text-muted d-none d-md-inline">| Balance Due: <strong class="text-danger font-monospace">{{ format_currency($invoice->balance_due) }}</strong></span>
                                    </div>
                                    <div class="fs-11 text-muted mt-0.5">
                                        You can reconcile/apply this advance to this invoice now, or keep it for future orders.
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0 ms-auto ms-sm-0">
                                <button type="button" class="btn btn-success fw-bold px-3.5 py-2 shadow-sm d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#applyAdvanceModal" style="border-radius: 6px;">
                                    <i class="feather-check-circle fs-15"></i>
                                    <span>Apply Advance ({{ format_currency(min($totalAvailAdvance, $invoice->balance_due)) }})</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Standard ERP Customer Tax Invoice Sheet -->
            <div class="invoice-sheet print-area mb-5">
                <!-- Status Corner Ribbon Tag Patti -->
                <div class="invoice-corner-ribbon">
                    @php
                        $ribbonClass = match($invoice->status) {
                            'Paid' => 'ribbon-paid',
                            'Partially Paid' => 'ribbon-partially-paid',
                            'Posted' => 'ribbon-posted',
                            'Sent' => 'ribbon-posted',
                            'Cancelled' => 'ribbon-cancelled',
                            default => 'ribbon-draft',
                        };

                        $statusLabel = match($invoice->status) {
                            'Paid' => __('crm.status_paid'),
                            'Partially Paid' => __('crm.status_partially_paid'),
                            'Posted' => __('crm.status_posted'),
                            'Sent' => __('crm.status_posted'),
                            'Cancelled' => __('crm.status_cancelled'),
                            default => __('crm.status_draft'),
                        };
                    @endphp
                    <div class="ribbon-inner {{ $ribbonClass }}">
                        {{ $statusLabel }}
                    </div>
                </div>

                @if ($invoice->einvoice_status === 'Generated')
                    <!-- Official NIC E-Invoice Header Stripe / QR Banner -->
                    <div class="p-2.5 mb-3 rounded border bg-light d-flex align-items-center justify-content-between flex-wrap gap-2" style="border-left: 4px solid #16a34a !important; background-color: #f8fafc !important;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-white p-1 rounded border shadow-2xs" role="button" data-bs-toggle="modal" data-bs-target="#viewEInvoiceQrModal" title="Click to View Full Size QR Code">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={{ urlencode($invoice->signed_qrcode ?: $invoice->irn) }}" alt="E-Invoice QR" style="width: 78px; height: 78px; display: block;">
                            </div>
                            <div class="fs-11">
                                <div class="d-flex align-items-center gap-2 mb-0.5">
                                    <span class="badge bg-success text-white px-2 py-0.5 fs-10 fw-bold"><i class="feather-check-circle me-1"></i>GST E-INVOICE</span>
                                    <span class="text-muted fs-10">Govt. E-Invoice Portal (NIC)</span>
                                </div>
                                <div class="text-dark mb-0.5">
                                    <strong>Ack No:</strong> <span class="font-monospace text-primary fw-bold">{{ $invoice->ack_no }}</span>
                                    &nbsp;|&nbsp; 
                                    <strong>Ack Date:</strong> <span class="text-secondary">{{ $invoice->ack_date ? date('d-M-Y H:i', strtotime($invoice->ack_date)) : '—' }}</span>
                                </div>
                                <div class="text-break text-muted font-monospace" style="font-size: 9.5px; max-width: 600px;">
                                    <strong class="text-dark">IRN:</strong> {{ $invoice->irn }}
                                </div>
                            </div>
                        </div>
                        @if ($invoice->eway_bill_status === 'Generated')
                            <div class="text-end fs-11 ps-3 border-start pe-2">
                                <span class="badge bg-info text-white px-2 py-0.5 fs-10 fw-bold mb-1"><i class="feather-truck me-1"></i>E-WAY BILL ACTIVE</span>
                                <div class="text-dark fw-bold font-monospace fs-12">{{ $invoice->eway_bill_no }}</div>
                                @if($invoice->transporter_name || $invoice->transporter_id)
                                    <div class="text-muted fs-10"><strong>Transporter:</strong> {{ $invoice->transporter_name ?: '—' }} @if($invoice->transporter_id)<span class="font-monospace">({{ $invoice->transporter_id }})</span>@endif</div>
                                @endif
                                @if($invoice->vehicle_no || $invoice->transport_mode)
                                    <div class="text-muted fs-10"><strong>Vehicle/Mode:</strong> {{ $invoice->vehicle_no ?: ($invoice->transport_doc_no ?: '—') }} ({{ ucfirst($invoice->transport_mode ?: 'Road') }})</div>
                                @endif
                                <div class="text-muted fs-10">Valid Till: <span class="text-dark fw-semibold">{{ $invoice->eway_bill_valid_till ? date('d-M-Y H:i', strtotime($invoice->eway_bill_valid_till)) : '—' }}</span></div>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- 1. Invoice Document Header -->
                <div class="row align-items-start pb-4 border-bottom mb-4">
                    <div class="col-7">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-text bg-primary text-white fs-2 fw-bold me-3 shadow-sm d-flex align-items-center justify-content-center" style="border-radius: 8px; width: 50px; height: 50px; flex-shrink: 0; background-color: #1e40af !important;">
                                {{ strtoupper(substr(tenant() ? tenant()->name : 'ERP', 0, 1)) }}
                            </div>
                            <div>
                                <h4 class="fw-bold text-dark mb-0 fs-17">{{ tenant() ? tenant()->name : 'SaaS ERP Workspace' }}</h4>
                                <span class="fs-11 text-muted">{{ __('crm.official_corporate_billing_unit') }}</span>
                            </div>
                        </div>
                        <div class="fs-12 text-secondary leading-relaxed">
                            <div>H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India</div>
                            <div><strong>GSTIN:</strong> 08AAFCS1234E1Z0 &nbsp;|&nbsp; <strong>State Code:</strong> 08 (Rajasthan)</div>
                            <div><strong>Email:</strong> {{ tenant() ? tenant()->billing_email : 'billing@sasserp.com' }} &nbsp;|&nbsp; <strong>Phone:</strong> +91 294 2440230</div>
                        </div>
                    </div>

                    <div class="col-5 text-end">
                        <h2 class="fw-black text-uppercase tracking-wide mb-1" style="color: #1e40af; font-size: 22px; letter-spacing: 1px;">{{ __('crm.tax_invoice') }}</h2>
                        <div class="fs-14 fw-bold text-dark"># {{ $invoice->invoice_number }}</div>
                        
                        <div class="mt-3 fs-12 text-secondary">
                            <div class="d-flex justify-content-end gap-2 mb-1">
                                <span class="text-muted">{{ __('crm.invoice_date') }}:</span>
                                <strong class="text-dark">{{ date('d-M-Y', strtotime($invoice->invoice_date)) }}</strong>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mb-1">
                                <span class="text-muted">{{ __('crm.due_date') }}:</span>
                                <strong class="text-dark">{{ $invoice->due_date ? date('d-M-Y', strtotime($invoice->due_date)) : '—' }}</strong>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <span class="text-muted">{{ __('crm.payment_terms') }}:</span>
                                <strong class="text-dark">{{ \App\Domains\Platform\Models\PaymentTerm::getLabel($invoice->payment_terms ?: $invoice->salesOrder?->payment_terms) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Address & Order Details Section -->
                <div class="row g-4 mb-4 fs-12 text-dark">
                    <!-- Left: Customer Details -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                            <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">{{ __('crm.billed_to_customer') }}</span>
                            <h6 class="fw-bold text-dark mb-1 fs-14">{{ $invoice->customer?->name ?: ($invoice->salesOrder?->customer?->name ?: '—') }}</h6>
                            @if($invoice->customer?->company_name)
                                <div class="text-muted fw-semibold mb-1">{{ $invoice->customer->company_name }}</div>
                            @endif
                            <div class="text-secondary mb-1">
                                <i class="feather-mail me-1 fs-11 text-muted"></i>{{ $invoice->customer?->email ?: ($invoice->salesOrder?->customer?->email ?: '—') }}
                                &nbsp;|&nbsp;
                                <i class="feather-phone me-1 fs-11 text-muted"></i>{{ $invoice->customer?->phone ?: ($invoice->salesOrder?->customer?->phone ?: '—') }}
                            </div>
                            @if ($invoice->salesOrder?->billing_address)
                                <div class="mt-2 pt-2 border-top text-secondary fs-11" style="white-space: pre-wrap;">
                                    <strong>{{ __('crm.billing_address') }}:</strong><br>{{ $invoice->salesOrder->billing_address }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Right: Order References & Dispatch Info -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                            <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">{{ __('crm.order_dispatch_references') }}</span>
                            
                            @if ($invoice->salesOrder)
                                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                    <span class="text-muted">{{ __('crm.sales_order_reference') }}:</span>
                                    <a href="{{ route('sales.orders.show', $invoice->sales_order_id) }}" class="fw-bold text-primary">
                                        {{ $invoice->salesOrder->sales_order_number }}
                                    </a>
                                </div>
                            @endif

                            @if ($invoice->materialRequirement)
                                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                    <span class="text-muted">{{ __('crm.dispatch_order_reference') }}:</span>
                                    <a href="{{ route('inventory.material-requirements.show', $invoice->material_requirement_id) }}" class="fw-bold text-info">
                                        {{ $invoice->materialRequirement->requirement_number }}
                                    </a>
                                </div>
                            @endif

                            @if ($invoice->eway_bill_status === 'Generated')
                                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                    <span class="text-muted">E-Way Bill No:</span>
                                    <span class="fw-bold text-dark font-monospace">{{ $invoice->eway_bill_no }} ({{ $invoice->vehicle_no ?: 'Road' }})</span>
                                </div>
                                @if($invoice->transporter_name)
                                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                        <span class="text-muted">Transporter:</span>
                                        <span class="fw-semibold text-dark">{{ $invoice->transporter_name }} @if($invoice->transporter_id)({{ $invoice->transporter_id }})@endif</span>
                                    </div>
                                @endif
                            @endif

                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">{{ __('crm.place_of_supply') }}</span>
                                @php $supply = app(\App\Domains\Accounting\Services\Gst\Gstr1ReturnService::class)->supplyFor($invoice->customer ?? $invoice->salesOrder?->customer); @endphp
                                <span class="fw-semibold text-dark">{{ $supply['pos'] ? \App\Domains\Accounting\Support\GstStates::name($supply['pos']) . ' (' . $supply['pos'] . ')' : '—' }}</span>
                            </div>

                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">{{ __('crm.gst_option') }}</span>
                                <span class="fw-bold text-dark">{{ $invoice->gst_type === 'igst' ? __('crm.inter_state_gst') : __('crm.intra_state_gst') }}</span>
                            </div>

                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">{{ __('crm.tax_mode') }}</span>
                                <span class="badge {{ ($invoice->effective_tax_mode ?? 'exclusive') === 'inclusive' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary' }} fw-bold">
                                    {{ ($invoice->effective_tax_mode ?? 'exclusive') === 'inclusive' ? __('crm.tax_inclusive') : __('crm.tax_exclusive') }}
                                </span>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span class="text-muted">{{ __('crm.payment_status') }}</span>
                                @if (in_array(strtolower((string)$invoice->status), ['cancelled', 'void']))
                                    <span class="badge bg-danger-subtle text-danger fw-bold fs-11">{{ __('crm.cancelled') ?? 'Cancelled' }}</span>
                                @else
                                    <span class="fw-bold {{ ($balanceDue ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ ($balanceDue ?? 0) > 0 ? __('crm.balance_outstanding') : __('crm.fully_paid') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Line Items Table -->
                <div class="mb-4">
                    <div class="table-responsive">
                        <table class="table invoice-table align-middle w-100 mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 5%;" class="text-center">#</th>
                                    <th style="width: 45%;">{{ __('crm.item_and_description') }}</th>
                                    <th class="text-end" style="width: 12%;">{{ __('crm.qty') }}</th>
                                    <th class="text-end" style="width: 13%;">{{ __('crm.rate') }}</th>
                                    <th class="text-end" style="width: 10%;">{{ __('crm.tax_rate') }}</th>
                                    <th class="text-end" style="width: 15%;">{{ __('crm.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->items as $idx => $item)
                                    <tr>
                                        <td class="text-center text-muted fs-12">{{ $idx + 1 }}</td>
                                        <td>
                                            <strong class="text-dark d-block fs-13">{{ $item->product?->name ?: $item->item_name }}</strong>
                                            @if($item->product?->sku)
                                                <span class="text-muted fs-11">SKU: {{ $item->product->sku }}</span>
                                            @endif
                                            @if($item->description)
                                                <span class="text-muted fs-11 d-block">{{ $item->description }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">{{ (float)$item->quantity }}</td>
                                        <td class="text-end">{{ format_currency($item->unit_price) }}</td>
                                        <td class="text-end text-muted">{{ (float)$item->tax_rate }}%</td>
                                        <td class="text-end fw-bold text-dark">
                                            @php
                                                $lineTotal = ($item->total_amount > 0)
                                                    ? $item->total_amount
                                                    : ($item->subtotal > 0 ? $item->subtotal : ($item->quantity * $item->unit_price));
                                            @endphp
                                            {{ format_currency($lineTotal) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. Summary & Calculations Row -->
                @php
                    $taxMode = $invoice->effective_tax_mode ?? 'exclusive';
                    $grossSubtotal = 0;
                    $totalItemDiscount = 0;
                    $itemsTaxAmount = 0;
                    $maxItemTaxRate = 0;
                    foreach($invoice->items as $it) {
                        $grossSubtotal += ($it->quantity * $it->unit_price);
                        $totalItemDiscount += $it->discount;
                        if ($invoice->tax_type === 'item_wise_tax') {
                            $lineNet = max(0, ($it->quantity * $it->unit_price) - $it->discount);
                            if ($taxMode === 'inclusive') {
                                $lineBase = ($it->tax_rate > 0) ? round($lineNet / (1 + ($it->tax_rate / 100)), 4) : $lineNet;
                                $lineTax = $lineNet - $lineBase;
                            } else {
                                $lineTax = ($lineNet * ($it->tax_rate / 100));
                            }
                            $itemsTaxAmount += $lineTax;
                            if ($it->tax_rate > $maxItemTaxRate) $maxItemTaxRate = $it->tax_rate;
                        }
                    }
                    $effectiveDiscount = ($invoice->discount_type === 'order_wise') ? (float)$invoice->discount_amount : $totalItemDiscount;
                    $itemsNet = max(0, $grossSubtotal - $effectiveDiscount);

                    if ($invoice->tax_type === 'order_wise_tax') {
                        $orderTaxRate = (float)($invoice->order_tax_rate ?: 18);
                        if ($taxMode === 'inclusive') {
                            $itemsTaxAmount = ($orderTaxRate > 0) ? ($itemsNet - ($itemsNet / (1 + ($orderTaxRate / 100)))) : 0;
                        } else {
                            $itemsTaxAmount = $itemsNet * ($orderTaxRate / 100);
                        }
                    } elseif ($invoice->tax_type === 'without_tax') {
                        $itemsTaxAmount = 0;
                    }

                    if ($taxMode === 'inclusive') {
                        $taxableBase = max(0, $itemsNet - $itemsTaxAmount);
                        $itemsTotalInclGst = $itemsNet;
                    } else {
                        $taxableBase = $itemsNet;
                        $itemsTotalInclGst = $taxableBase + $itemsTaxAmount;
                    }

                    $freightAmount = ($invoice->freight_terms === 'To Be Billed') ? (float)($invoice->freight_amount ?: 0) : 0;

                    $freightTaxRateOpt = $invoice->freight_tax_rate ?? 'highest';
                    $freightTaxRatePercent = 18;
                    if ($freightTaxRateOpt === 'highest') {
                        if ($invoice->tax_type === 'order_wise_tax') {
                            $freightTaxRatePercent = $invoice->order_tax_rate > 0 ? $invoice->order_tax_rate : 18;
                        } else {
                            $freightTaxRatePercent = ($maxItemTaxRate > 0) ? $maxItemTaxRate : 18;
                        }
                    } else {
                        $freightTaxRatePercent = floatval($freightTaxRateOpt);
                    }

                    $freightTax = ($freightAmount > 0 && $invoice->tax_type !== 'without_tax') ? round($freightAmount * ($freightTaxRatePercent / 100), 2) : 0;
                    $totalFreightInclGst = $freightAmount + $freightTax;
                    $totalInvoiceTaxAmount = $itemsTaxAmount + $freightTax;
                    $adjustment = (float)($invoice->adjustment ?? 0);
                    $grandTotal = (float)($invoice->total_amount ?: ($taxMode === 'inclusive' ? ($itemsTotalInclGst + $freightAmount + $adjustment) : (($taxableBase + $freightAmount) + $totalInvoiceTaxAmount + $adjustment)));
                    $gstType = $invoice->gst_type ?? 'cgst_sgst';
                    $taxGroups = $invoice->items->groupBy(fn($item) => (string)(float)$item->tax_rate);
                    $hasFreightTax = ($freightAmount > 0 && $freightTax > 0);
                @endphp

                <div class="row pt-2 fs-13 text-dark mb-4">
                    <!-- Left: Bank Details & Terms -->
                    <div class="col-7">
                        <div class="p-3 bg-light bg-opacity-30 rounded border mb-3 bank-details-box" style="border-color: #cbd5e1 !important;">
                            <h6 class="fw-bold text-dark fs-11 text-uppercase mb-2" style="letter-spacing: 0.5px;">{{ __('crm.bank_payment_details') }}</h6>
                            <div class="row fs-11 text-secondary g-2">
                                <div class="col-6"><strong>{{ __('crm.bank_name') }}</strong> State Bank of India</div>
                                <div class="col-6"><strong>{{ __('crm.account_name') }}</strong> {{ tenant() ? tenant()->name : 'SaaS ERP' }}</div>
                                <div class="col-6"><strong>{{ __('crm.account_no') }}</strong> 398402948201</div>
                                <div class="col-6"><strong>{{ __('crm.ifsc_code') }}</strong> SBIN0001234</div>
                            </div>
                        </div>

                        @if ($invoice->notes)
                            <div class="p-3 bg-light bg-opacity-30 rounded border terms-box" style="border-color: #cbd5e1 !important;">
                                <h6 class="fw-bold text-dark fs-11 text-uppercase mb-1" style="letter-spacing: 0.5px;">{{ __('crm.terms_conditions_notes') }}</h6>
                                <div class="mb-0 text-muted fs-12">{!! $invoice->notes !!}</div>
                            </div>
                        @endif
                    </div>

                    <!-- Right: Subtotal Calculations -->
                    <div class="col-5 summary-table-box ms-auto">
                        <div class="p-3 rounded-3 border bg-light text-dark" style="border-color: #cbd5e1 !important; background-color: #f8fafc !important;">

                            <!-- 1. Subtotal -->
                            <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                <span class="text-muted fw-semibold">{{ $taxMode === 'inclusive' ? __('crm.subtotal_incl_tax') : __('crm.subtotal_excl_tax') }}</span>
                                <span class="fw-bold text-dark">{{ format_currency($grossSubtotal) }}</span>
                            </div>

                            <!-- 2. Less: Item Discounts -->
                            @if($invoice->discount_type !== 'without_discount' && $effectiveDiscount > 0)
                                <div class="d-flex justify-content-between align-items-center mb-2 fs-12 text-danger">
                                    <span class="fw-semibold">{{ __('crm.less_item_discounts') }}</span>
                                    <span class="fw-bold">-{{ format_currency($effectiveDiscount) }}</span>
                                </div>
                            @endif

                            <!-- 3. Items Taxable Value -->
                            <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                <span class="text-muted fw-semibold">{{ $taxMode === 'inclusive' ? __('crm.taxable_value_excl_tax') : __('crm.items_taxable_value') }}</span>
                                <span class="fw-bold text-dark">{{ format_currency($taxableBase) }}</span>
                            </div>

                            <!-- 4. Freight Charges -->
                            @if($freightAmount > 0)
                                <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                    <span class="text-muted fw-semibold">{{ __('crm.freight_charges') }}</span>
                                    <span class="fw-bold text-primary">{{ format_currency($freightAmount) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2 fs-12 fw-bold text-dark">
                                    <span>Total Taxable Value</span>
                                    <span>{{ format_currency($taxableBase + $freightAmount) }}</span>
                                </div>
                            @endif

                            <!-- OVERALL TAX BREAKDOWN -->
                            @if($invoice->tax_type !== 'without_tax' && $totalInvoiceTaxAmount > 0)
                                <hr class="my-2 border-slate">
                                @if($gstType === 'cgst_sgst')
                                    <div class="d-flex justify-content-between align-items-center mb-1.5 fs-12">
                                        <span class="text-muted fw-medium">{{ __('crm.cgst_central_tax') }} {{ $taxMode === 'inclusive' ? __('crm.tax_included') : '' }}</span>
                                        <span class="text-muted font-monospace">{{ ($taxMode === 'inclusive' ? '' : '+') . format_currency($totalInvoiceTaxAmount / 2) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                        <span class="text-muted fw-medium">{{ __('crm.sgst_state_tax') }} {{ $taxMode === 'inclusive' ? __('crm.tax_included') : '' }}</span>
                                        <span class="text-muted font-monospace">{{ ($taxMode === 'inclusive' ? '' : '+') . format_currency($totalInvoiceTaxAmount / 2) }}</span>
                                    </div>
                                @else
                                    <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                        <span class="text-muted fw-medium">{{ __('crm.igst_integrated_tax') }} {{ $taxMode === 'inclusive' ? __('crm.tax_included') : '' }}</span>
                                        <span class="text-muted font-monospace">{{ ($taxMode === 'inclusive' ? '' : '+') . format_currency($totalInvoiceTaxAmount) }}</span>
                                    </div>
                                @endif
                            @endif

                            <!-- 7. Adjustment -->
                            @if($adjustment != 0)
                                <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                    <span class="text-muted fw-semibold">{{ __('crm.adjustment') }}</span>
                                    <span class="fw-bold text-dark">{{ format_currency($adjustment) }}</span>
                                </div>
                            @endif

                            <!-- 8. Grand Total -->
                            <div class="d-flex justify-content-between align-items-center pt-2.5 border-top mt-2" style="border-color: #cbd5e1 !important;">
                                <span class="fw-bold text-dark fs-13 text-uppercase" style="letter-spacing: 0.5px;">{{ __('crm.grand_total') }}</span>
                                <span class="fw-bold text-primary fs-16">{{ format_currency($grandTotal) }}</span>
                            </div>

                            <!-- 9. Balance Due (if applicable) -->
                            @php
                                $totalPaid = $invoice->amount_paid ?: 0;
                                $balDue = max(0, $grandTotal - $totalPaid);
                            @endphp
                            @if($totalPaid > 0)
                                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top text-success fs-12">
                                    <span class="fw-semibold">{{ __('crm.amount_paid') }}:</span>
                                    <span class="fw-bold">-{{ format_currency($totalPaid) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1 pt-1 text-danger fs-13">
                                    <span class="fw-bold">{{ __('crm.balance_due') }}:</span>
                                    <span class="fw-bold">{{ format_currency($balDue) }}</span>
                                </div>
                            @endif

                            @if($invoice->allocations->isNotEmpty())
                                <div class="mt-2 pt-2 border-top fs-11 text-muted">
                                    <span class="fw-bold text-dark d-block mb-1">Applied Payments / Advances:</span>
                                    @foreach($invoice->allocations as $alloc)
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span>
                                                <i class="feather-check text-success me-1"></i>
                                                {{ $alloc->payment?->payment_number ?? 'Advance' }}
                                                @if($alloc->payment?->payment_date)
                                                    <span class="text-secondary">({{ date('d-M', strtotime($alloc->payment->payment_date)) }})</span>
                                                @endif
                                            </span>
                                            <span class="fw-semibold text-dark">{{ format_currency($alloc->allocated_amount) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- 5. Full-Width GST Tax Summary / Tax Analysis (A4 Optimized) -->
                @if (($taxGroups->count() > 0 && $invoice->tax_amount > 0) || $hasFreightTax)
                    <div class="card border shadow-none mb-4 gst-summary-box" style="border-radius: 6px; overflow: hidden; border-color: #cbd5e1 !important;">
                        <div class="py-1.5 px-3 bg-light border-bottom text-muted fw-bold fs-11 text-uppercase d-flex justify-content-between align-items-center">
                            <span><i class="feather-pie-chart me-1 text-primary"></i>{{ __('crm.gst_tax_summary') }} / TAX ANALYSIS</span>
                            <span class="badge bg-soft-primary text-primary fs-10" style="font-size: 10px;">{{ $gstType === 'igst' ? 'IGST (Inter-State)' : 'CGST + SGST (Intra-State)' }}</span>
                        </div>
                        <div class="table-responsive" style="overflow-x: visible;">
                            <table class="table table-sm table-bordered align-middle text-center fs-11 mb-0 w-100">
                                <thead class="bg-light text-secondary fw-bold">
                                    @if($gstType === 'igst')
                                        <tr>
                                            <th class="py-1.5 text-start ps-3" style="width: 35%;">Tax Rate / Description</th>
                                            <th class="py-1.5 text-end" style="width: 25%;">Taxable Value</th>
                                            <th class="py-1.5 text-end" style="width: 20%;">Total Tax</th>
                                            <th class="py-1.5 text-end pe-3" style="width: 20%;">IGST Amount</th>
                                        </tr>
                                    @else
                                        <tr>
                                            <th class="py-1.5 text-start ps-3" style="width: 32%;">Tax Rate / Description</th>
                                            <th class="py-1.5 text-end" style="width: 20%;">Taxable Value</th>
                                            <th class="py-1.5 text-end" style="width: 16%;">Total Tax</th>
                                            <th class="py-1.5 text-end" style="width: 16%;">CGST Amount</th>
                                            <th class="py-1.5 text-end pe-3" style="width: 16%;">SGST Amount</th>
                                        </tr>
                                    @endif
                                </thead>
                                <tbody>
                                    @php
                                        $totTaxable = 0;
                                        $totCgst = 0;
                                        $totSgst = 0;
                                        $totIgst = 0;
                                        $totTax = 0;
                                    @endphp
                                    @if($invoice->tax_type === 'order_wise_tax')
                                        @php
                                            $orderTaxRate = $invoice->order_tax_rate ?: 18;
                                            $totTaxable += $taxableBase;
                                            $totTax += $itemsTaxAmount;
                                        @endphp
                                        @if($gstType === 'igst')
                                            @php $totIgst += $itemsTaxAmount; @endphp
                                            <tr>
                                                <td class="py-1.5 text-start ps-3 fw-bold">GST {{ $orderTaxRate }}% (Order Level)</td>
                                                <td class="py-1.5 text-end">{{ format_currency($taxableBase) }}</td>
                                                <td class="py-1.5 text-end fw-bold text-dark">{{ format_currency($itemsTaxAmount) }}</td>
                                                <td class="py-1.5 text-end pe-3">{{ format_currency($itemsTaxAmount) }}</td>
                                            </tr>
                                        @else
                                            @php
                                                $oCgst = round($itemsTaxAmount / 2, 2);
                                                $oSgst = round($itemsTaxAmount - $oCgst, 2);
                                                $totCgst += $oCgst;
                                                $totSgst += $oSgst;
                                            @endphp
                                            <tr>
                                                <td class="py-1.5 text-start ps-3 fw-bold">GST {{ $orderTaxRate }}% (Order Level)</td>
                                                <td class="py-1.5 text-end">{{ format_currency($taxableBase) }}</td>
                                                <td class="py-1.5 text-end fw-bold text-dark">{{ format_currency($itemsTaxAmount) }}</td>
                                                <td class="py-1.5 text-end">{{ format_currency($oCgst) }}</td>
                                                <td class="py-1.5 text-end pe-3">{{ format_currency($oSgst) }}</td>
                                            </tr>
                                        @endif
                                    @else
                                        {{-- 1. Pure Items-wise Tax Rows (No freight mixed in) --}}
                                        @foreach($taxGroups as $rateStr => $gItems)
                                            @php
                                                $rate = floatval($rateStr);
                                                $grpTaxable = $gItems->sum(function($it) use ($taxMode) {
                                                    $lineNet = max(0, ($it->quantity * $it->unit_price) - $it->discount);
                                                    return $taxMode === 'inclusive' ? max(0, $lineNet - $it->tax_amount) : $lineNet;
                                                });
                                                $grpTax = $gItems->sum('tax_amount');
                                                $totTaxable += $grpTaxable;
                                                $totTax += $grpTax;
                                            @endphp
                                            @if($gstType === 'igst')
                                                @php
                                                    $grpIgst = $gItems->sum('igst_amount') > 0 ? $gItems->sum('igst_amount') : $grpTax;
                                                    $totIgst += $grpIgst;
                                                @endphp
                                                <tr>
                                                    <td class="py-1.5 text-start ps-3 fw-bold">GST {{ $rate }}%</td>
                                                    <td class="py-1.5 text-end">{{ format_currency($grpTaxable) }}</td>
                                                    <td class="py-1.5 text-end fw-bold text-dark">{{ format_currency($grpTax) }}</td>
                                                    <td class="py-1.5 text-end pe-3">{{ format_currency($grpIgst) }}</td>
                                                </tr>
                                            @else
                                                @php
                                                    $grpCgst = $gItems->sum('cgst_amount') > 0 ? $gItems->sum('cgst_amount') : round($grpTax / 2, 2);
                                                    $grpSgst = $gItems->sum('sgst_amount') > 0 ? $gItems->sum('sgst_amount') : round($grpTax - $grpCgst, 2);
                                                    $totCgst += $grpCgst;
                                                    $totSgst += $grpSgst;
                                                @endphp
                                                <tr>
                                                    <td class="py-1.5 text-start ps-3 fw-bold">GST {{ $rate }}%</td>
                                                    <td class="py-1.5 text-end">{{ format_currency($grpTaxable) }}</td>
                                                    <td class="py-1.5 text-end fw-bold text-dark">{{ format_currency($grpTax) }}</td>
                                                    <td class="py-1.5 text-end">{{ format_currency($grpCgst) }}</td>
                                                    <td class="py-1.5 text-end pe-3">{{ format_currency($grpSgst) }}</td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    @endif

                                    {{-- 2. Dedicated Freight Tax Row (Separate & Clean) --}}
                                    @if($hasFreightTax)
                                        @php
                                            $totTaxable += $freightAmount;
                                            $totTax += $freightTax;
                                        @endphp
                                        @if($gstType === 'igst')
                                            @php $totIgst += $freightTax; @endphp
                                            <tr class="table-light">
                                                <td class="py-1.5 text-start ps-3 fw-bold text-primary"><i class="feather-truck me-1"></i>Freight / Shipping ({{ $freightTaxRatePercent }}%)</td>
                                                <td class="py-1.5 text-end text-primary">{{ format_currency($freightAmount) }}</td>
                                                <td class="py-1.5 text-end fw-bold text-primary">{{ format_currency($freightTax) }}</td>
                                                <td class="py-1.5 text-end pe-3 text-primary">{{ format_currency($freightTax) }}</td>
                                            </tr>
                                        @else
                                            @php
                                                $fCgst = round($freightTax / 2, 2);
                                                $fSgst = round($freightTax - $fCgst, 2);
                                                $totCgst += $fCgst;
                                                $totSgst += $fSgst;
                                            @endphp
                                            <tr class="table-light">
                                                <td class="py-1.5 text-start ps-3 fw-bold text-primary"><i class="feather-truck me-1"></i>Freight / Shipping ({{ $freightTaxRatePercent }}%)</td>
                                                <td class="py-1.5 text-end text-primary">{{ format_currency($freightAmount) }}</td>
                                                <td class="py-1.5 text-end fw-bold text-primary">{{ format_currency($freightTax) }}</td>
                                                <td class="py-1.5 text-end text-primary">{{ format_currency($fCgst) }}</td>
                                                <td class="py-1.5 text-end pe-3 text-primary">{{ format_currency($fSgst) }}</td>
                                            </tr>
                                        @endif
                                    @endif
                                </tbody>
                                <tfoot class="bg-light fw-bold text-dark">
                                    @if($gstType === 'igst')
                                        <tr>
                                            <td class="py-1.5 text-start ps-3">Total</td>
                                            <td class="py-1.5 text-end">{{ format_currency($totTaxable) }}</td>
                                            <td class="py-1.5 text-end text-primary">{{ format_currency($totTax) }}</td>
                                            <td class="py-1.5 text-end pe-3">{{ format_currency($totIgst) }}</td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td class="py-1.5 text-start ps-3">Total</td>
                                            <td class="py-1.5 text-end">{{ format_currency($totTaxable) }}</td>
                                            <td class="py-1.5 text-end text-primary">{{ format_currency($totTax) }}</td>
                                            <td class="py-1.5 text-end">{{ format_currency($totCgst) }}</td>
                                            <td class="py-1.5 text-end pe-3">{{ format_currency($totSgst) }}</td>
                                        </tr>
                                    @endif
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @endif

                <!-- 5. Signature Footer Block -->
                <div class="row pt-4 border-top fs-11 text-muted align-items-end mt-4 signature-footer-block">
                    <div class="col-7">
                        <div>{{ __('crm.thank_you_for_your_business') }}</div>
                        <div class="mt-1">{{ __('crm.computer_generated_invoice_notice') }}</div>
                    </div>
                    <div class="col-5 text-end">
                        <div class="fw-bold text-dark mb-5">For {{ tenant() ? tenant()->name : 'SaaS ERP' }}</div>
                        <div class="border-top d-inline-block pt-1 px-4 text-muted fw-semibold">{{ __('crm.authorized_signatory') }}</div>
                    </div>
                </div>

                <!-- Settled Payments & Advance Adjustments Table (Screen view only) -->
                @if($invoice->allocations->isNotEmpty())
                    <div class="card border shadow-none mt-4 d-print-none" style="border-radius: 6px; border-color: #cbd5e1 !important;">
                        <div class="py-2 px-3 bg-light border-bottom text-muted fw-bold fs-11 text-uppercase d-flex justify-content-between align-items-center">
                            <span><i class="feather-check-circle me-1 text-success"></i>Settled Payments / Advance Adjustments</span>
                            <span class="badge bg-soft-success text-success fs-10">{{ $invoice->allocations->count() }} Entry(s)</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle text-center fs-11 mb-0 w-100">
                                <thead class="bg-light text-secondary fw-bold">
                                    <tr>
                                        <th class="py-1.5 text-start ps-3">Payment #</th>
                                        <th class="py-1.5">Date</th>
                                        <th class="py-1.5">Method</th>
                                        <th class="py-1.5">Ref / Notes</th>
                                        <th class="py-1.5 text-end">Applied Amount</th>
                                        <th class="py-1.5 text-center pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoice->allocations as $alloc)
                                        <tr>
                                            <td class="py-1.5 text-start ps-3 fw-bold">
                                                @if($alloc->customer_payment_id)
                                                    <a href="{{ route('sales.payments.show', $alloc->customer_payment_id) }}" class="text-primary">
                                                        {{ $alloc->payment?->payment_number ?? 'Payment #' . $alloc->customer_payment_id }}
                                                    </a>
                                                @else
                                                    {{ $alloc->payment?->payment_number ?? 'Advance' }}
                                                @endif
                                            </td>
                                            <td class="py-1.5">{{ $alloc->payment?->payment_date ? date('d-M-Y', strtotime($alloc->payment->payment_date)) : '—' }}</td>
                                            <td class="py-1.5">{{ $alloc->payment?->payment_method ?? 'Bank/Cash' }}</td>
                                            <td class="py-1.5 text-muted">{{ $alloc->payment?->reference_no ?: ($alloc->payment?->notes ?: '—') }}</td>
                                            <td class="py-1.5 text-end fw-bold text-success">{{ format_currency($alloc->allocated_amount) }}</td>
                                            <td class="py-1.5 text-center pe-3">
                                                <form action="{{ route('sales.invoices.remove-advance', [$invoice->id, $alloc->id]) }}" method="POST" onsubmit="return confirm('Unlink this payment from invoice? It will return to available advances.');" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-outline-danger py-0.5 px-2 fs-10" title="Unlink / Return to Advance">
                                                        <i class="feather-rotate-ccw me-1"></i>Unlink
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

<!-- SEND INVOICE EMAIL MODAL WITH PDF ATTACHMENT -->
<x-ui.modal id="sendInvoiceEmailModal" title="<i class='feather-send text-success me-1.5'></i>Send Tax Invoice PDF Email to Customer" size="lg" :centered="true" :showFooter="false">
    <form id="sendInvoiceEmailForm" action="" method="POST" enctype="multipart/form-data">
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
            <x-ui.modal-form-ui type="input" inputType="email" label="Customer Email Address (To)" name="to_email" id="sendInvoiceToEmail" placeholder="customer@company.com" :required="true" />
        </div>

        <div class="mb-3">
            <x-ui.modal-form-ui type="input" label="Subject" name="subject" id="sendInvoiceSubject" :required="true" />
        </div>

        <!-- Enhanced PDF Attachment Box -->
        <div class="card border mb-3 bg-light-subtle shadow-2xs">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="avatar avatar-sm bg-soft-danger text-danger rounded d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="feather-file-text fs-16"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fs-12 fw-bold text-dark text-break" id="sendInvoicePdfBadge">Tax_Invoice.pdf</span>
                                <span class="badge bg-soft-danger text-danger border px-2 py-0.5 fs-10" id="sendInvoicePdfStatusBadge">Auto Generated PDF</span>
                            </div>
                            <span class="fs-11 text-muted d-block mt-0.5" id="sendInvoicePdfSubText">Base64 PDF Attachment generated from ERP System</span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <!-- View PDF Button -->
                        <button type="button" class="btn btn-xs btn-outline-primary fw-bold d-inline-flex align-items-center px-2.5 py-1" id="btnPreviewEmailPdf" title="View / Preview attached PDF">
                            <i class="feather-eye me-1"></i>View PDF
                        </button>
                        
                        <!-- Change / Upload Custom PDF Button -->
                        <button type="button" class="btn btn-xs btn-outline-secondary fw-bold d-inline-flex align-items-center px-2.5 py-1" id="btnTriggerCustomEmailPdf" title="Upload a custom PDF from your system">
                            <i class="feather-upload me-1"></i>Change PDF
                        </button>

                        <!-- Reset to default ERP PDF Button -->
                        <button type="button" class="btn btn-xs btn-outline-danger fw-bold d-none align-items-center px-2 py-1" id="btnResetCustomEmailPdf" title="Reset to default ERP Generated PDF">
                            <i class="feather-x me-1"></i>Reset
                        </button>
                    </div>
                </div>

                <!-- Hidden file input for custom PDF upload -->
                <input type="file" name="custom_pdf" id="inputCustomEmailPdf" class="d-none" accept="application/pdf">
            </div>
        </div>

        <div class="mb-3">
            <x-ui.modal-form-ui type="textarea" label="Message Body" name="body_html" id="sendInvoiceBody" rows="6" :required="true" />
        </div>

        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <span class="fs-11 text-muted"><i class="feather-info me-1"></i>Email will be dispatched immediately via SMTP server.</span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="btnSubmitSendInvoiceEmail" class="btn btn-sm btn-success fw-bold px-4">
                    <i class="feather-send me-1"></i>Send Email Now
                </button>
            </div>
        </div>
    </form>
</x-ui.modal>

<!-- SEND INVOICE WHATSAPP MODAL -->
<x-ui.modal id="sendInvoiceWhatsAppModal" title="<i class='feather-message-circle text-success me-1.5'></i>Send Tax Invoice PDF via WhatsApp" size="lg" :centered="true" :showFooter="false">
    <form id="sendInvoiceWhatsAppForm" action="" method="POST" enctype="multipart/form-data">
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

        <!-- Enhanced PDF Attachment Box -->
        <div class="card border mb-3 bg-light-subtle shadow-2xs">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="avatar avatar-sm bg-soft-success text-success rounded d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="feather-file-text fs-16"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fs-12 fw-bold text-dark text-break" id="sendWaPdfBadge">Tax_Invoice.pdf</span>
                                <span class="badge bg-soft-success text-success border px-2 py-0.5 fs-10" id="sendWaPdfStatusBadge">Auto Generated PDF</span>
                            </div>
                            <span class="fs-11 text-muted d-block mt-0.5" id="sendWaPdfSubText">Base64 PDF Attachment generated from ERP System</span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <!-- View PDF Button -->
                        <button type="button" class="btn btn-xs btn-outline-primary fw-bold d-inline-flex align-items-center px-2.5 py-1" id="btnPreviewWaPdf" title="View / Preview attached PDF">
                            <i class="feather-eye me-1"></i>View PDF
                        </button>
                        
                        <!-- Change / Upload Custom PDF Button -->
                        <button type="button" class="btn btn-xs btn-outline-secondary fw-bold d-inline-flex align-items-center px-2.5 py-1" id="btnTriggerCustomWaPdf" title="Upload a custom PDF from your system">
                            <i class="feather-upload me-1"></i>Change PDF
                        </button>

                        <!-- Reset to default ERP PDF Button -->
                        <button type="button" class="btn btn-xs btn-outline-danger fw-bold d-none align-items-center px-2 py-1" id="btnResetCustomWaPdf" title="Reset to default ERP Generated PDF">
                            <i class="feather-x me-1"></i>Reset
                        </button>
                    </div>
                </div>

                <!-- Hidden file input for custom PDF upload -->
                <input type="file" name="custom_pdf" id="inputCustomWaPdf" class="d-none" accept="application/pdf">
            </div>
        </div>

        <div class="mb-3">
            <x-ui.modal-form-ui type="textarea" label="Caption / Message Text" name="caption" id="sendWaCaption" rows="5" :required="true" />
        </div>

        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <span class="fs-11 text-muted"><i class="feather-info me-1"></i>Document will be sent directly via linked WhatsApp account.</span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="btnSubmitSendInvoiceWA" class="btn btn-sm btn-success fw-bold px-4">
                    <i class="feather-send me-1"></i>Send WhatsApp PDF
                </button>
            </div>
        </div>
    </form>
</x-ui.modal>

@if (!$isCancelled)
<!-- GENERATE E-WAY BILL MODAL -->
<x-ui.modal id="generateEWayBillModal" title="<i class='feather-truck text-info me-1.5'></i>Generate GST E-Way Bill (EWB)" size="lg" :centered="true" :showFooter="false">
    <form action="{{ route('sales.invoices.ewaybill.generate', $invoice->id) }}" method="POST">
        @csrf
        <div class="p-3 bg-light rounded border mb-3 fs-12">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-muted">Invoice Number:</span>
                <strong class="text-dark">{{ $invoice->invoice_number }}</strong>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-muted">Customer / Consignee:</span>
                <strong class="text-dark">{{ $invoice->customer?->name ?: ($invoice->salesOrder?->customer?->name ?: 'Customer') }}</strong>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">Total Consignment Value:</span>
                <strong class="text-primary fs-13">{{ format_currency($invoice->total_amount) }}</strong>
            </div>
        </div>

        <div class="row g-3 mb-2">
            <!-- Transporter Master Selection with Select2 -->
            <div class="col-12">
                <x-ui.modal-form-ui type="select" label="Transporter Master" name="transporter_master_id" id="transporterSelect" :searchable="true" helperText="Select from saved transporters or create new. Fields will auto-fill below.">
                    <option value="">-- Manual Entry / None --</option>
                    <option value="__ADD_NEW__" class="fw-bold text-primary">+ Add New Transporter</option>
                    @if(isset($transporters))
                        @foreach($transporters as $trp)
                            <option value="{{ $trp->id }}" 
                                    data-name="{{ $trp->name }}" 
                                    data-id="{{ $trp->transporter_id ?: $trp->gstin }}" 
                                    data-gstin="{{ $trp->gstin ?: $trp->transporter_id }}" 
                                    data-mode="{{ $trp->transport_mode }}"
                                    {{ (isset($invoice->transporter_id) && ($invoice->transporter_id == $trp->transporter_id || $invoice->transporter_id == $trp->gstin)) ? 'selected' : '' }}>
                                {{ $trp->name }} ({{ $trp->gstin ?: $trp->transporter_id ?: $trp->code }})
                            </option>
                        @endforeach
                    @endif
                </x-ui.modal-form-ui>
            </div>

            <div class="col-md-6">
                <x-ui.modal-form-ui type="input" inputType="number" label="Approx Distance (in KM)" name="distance_km" id="ewbDistanceKm" :value="$invoice->distance_km ?: 250" :required="true" helperText="NIC statutory validity: 1 day per 200 km." min="1" max="4000" />
            </div>
            <div class="col-md-6">
                <x-ui.modal-form-ui type="select" label="Transport Mode" name="transport_mode" id="ewbTransportMode" :searchable="false" :required="true">
                    <option value="road" {{ $invoice->transport_mode === 'road' || !$invoice->transport_mode ? 'selected' : '' }}>Road (1)</option>
                    <option value="rail" {{ $invoice->transport_mode === 'rail' ? 'selected' : '' }}>Rail (2)</option>
                    <option value="air" {{ $invoice->transport_mode === 'air' ? 'selected' : '' }}>Air (3)</option>
                    <option value="ship" {{ $invoice->transport_mode === 'ship' ? 'selected' : '' }}>Ship (4)</option>
                </x-ui.modal-form-ui>
            </div>

            <div class="col-md-6">
                <x-ui.modal-form-ui type="input" label="Vehicle Number" name="vehicle_no" id="ewbVehicleNo" :value="$invoice->vehicle_no" placeholder="e.g. RJ27GA1234" helperText="Required if mode is Road without transporter." class="text-uppercase font-monospace" />
            </div>
            <div class="col-md-6">
                <x-ui.modal-form-ui type="select" label="Vehicle Type" name="vehicle_type" id="ewbVehicleType" :searchable="false">
                    <option value="regular" {{ $invoice->vehicle_type === 'regular' || !$invoice->vehicle_type ? 'selected' : '' }}>Regular Cargo</option>
                    <option value="odc" {{ $invoice->vehicle_type === 'odc' ? 'selected' : '' }}>Over Dimensional Cargo (ODC)</option>
                </x-ui.modal-form-ui>
            </div>

            <div class="col-md-6">
                <x-ui.modal-form-ui type="input" label="Transporter GSTIN / ID" name="transporter_id" id="ewbTransporterId" :value="$invoice->transporter_id" placeholder="15-digit GSTIN (Optional)" maxlength="15" class="text-uppercase font-monospace" />
            </div>
            <div class="col-md-6">
                <x-ui.modal-form-ui type="input" label="Transporter Name" name="transporter_name" id="ewbTransporterName" :value="$invoice->transporter_name" placeholder="e.g. VRL Logistics / SafeXpress" />
            </div>

            <div class="col-md-6">
                <x-ui.modal-form-ui type="input" label="Transport Doc / LR / RR No" name="transport_doc_no" id="ewbTransportDocNo" :value="$invoice->transport_doc_no" placeholder="e.g. LR-98341" />
            </div>
            <div class="col-md-6">
                <x-ui.modal-form-ui type="input" inputType="date" label="Transport Doc Date" name="transport_doc_date" id="ewbTransportDocDate" :value="$invoice->transport_doc_date ?: date('Y-m-d')" />
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <span class="fs-11 text-muted"><i class="feather-shield text-info me-1"></i>Transmitted via NIC E-Way Bill Portal</span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-info text-white fw-bold px-4">
                    <i class="feather-truck me-1"></i>Generate E-Way Bill
                </button>
            </div>
        </div>
    </form>
</x-ui.modal>
@endif

<!-- QUICK ADD TRANSPORTER MODAL -->
<x-ui.modal id="quickTransporterModal" title="<i class='feather-truck text-primary me-2'></i>Add New Transporter Master" size="lg" :centered="true" :showFooter="false">
    <form id="quickTransporterForm">
        @csrf
        <div class="p-1">
            <!-- Section 1: Basic Logistics Info -->
            <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-1.5"></i>Basic Transporter Info</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-7">
                    <x-ui.modal-form-ui type="input" label="Transporter Name" name="name" placeholder="e.g. V-Trans, TCI Logistics, GATI KWE" :required="true" />
                </div>
                <div class="col-md-5">
                    <x-ui.modal-form-ui type="input" label="Transporter Code" name="code" :value="$autoTransporterCode ?? 'TRP-0001'" placeholder="e.g. TRP-0005" />
                </div>
                <div class="col-md-7">
                    <x-ui.modal-form-ui type="input" label="Transporter ID (E-Way Bill)" name="transporter_id" placeholder="e.g. 27AAACM1234F1Z1" class="text-uppercase font-monospace" />
                </div>
                <div class="col-md-5">
                    <x-ui.modal-form-ui type="select" label="Transport Mode" name="transport_mode" :searchable="false">
                        <option value="road">Road</option>
                        <option value="rail">Rail</option>
                        <option value="air">Air</option>
                        <option value="ship">Ship / Sea Cargo</option>
                    </x-ui.modal-form-ui>
                </div>
            </div>

            <hr class="my-3 text-muted opacity-25">

            <!-- Section 2: Taxation & Contact Info -->
            <h6 class="fw-bold text-primary mb-3"><i class="feather-shield me-1.5"></i>Taxation & Contact Details</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <x-ui.modal-form-ui type="input" label="GSTIN Number" name="gstin" placeholder="e.g. 27AAAAA0000A1Z5" class="text-uppercase font-monospace" />
                </div>
                <div class="col-md-6">
                    <x-ui.modal-form-ui type="input" label="PAN Number" name="pan_number" placeholder="e.g. ABCDE1234F" class="text-uppercase font-monospace" />
                </div>
                <div class="col-md-6">
                    <x-ui.modal-form-ui type="input" inputType="tel" label="Primary Phone" name="phone" placeholder="Contact number" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />
                </div>
                <div class="col-md-6">
                    <x-ui.modal-form-ui type="input" inputType="email" label="Official Email Address" name="email" placeholder="dispatch@transporter.com" />
                </div>
                <div class="col-md-6">
                    <x-ui.modal-form-ui type="input" label="City" name="city" placeholder="City" />
                </div>
                <div class="col-md-6">
                    <x-ui.modal-form-ui type="input" label="State" name="state" placeholder="State" />
                </div>
            </div>

            <!-- Footer Action Buttons -->
            <div class="d-flex justify-content-end align-items-center gap-2 pt-3 border-top mt-3">
                <button type="button" class="btn btn-light border fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold px-4" id="saveQuickTransporterBtn">
                    <i class="feather-save me-1.5"></i>Save Transporter Master
                </button>
            </div>
        </div>
    </form>
</x-ui.modal>

<!-- VIEW / SCAN E-INVOICE QR CODE MODAL -->
<x-ui.modal id="viewEInvoiceQrModal" title="<i class='feather-maximize text-primary me-1.5'></i>Official GST E-Invoice QR Code" size="lg" :centered="true" :showFooter="false">
    <div class="p-3 text-center">
        <div class="row align-items-center g-4">
            <div class="col-md-5 text-center">
                <div class="p-3 bg-white rounded border shadow-sm d-inline-block">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=350x350&data={{ urlencode($invoice->signed_qrcode ?: $invoice->irn) }}" alt="E-Invoice QR Code" class="img-fluid" style="width: 220px; height: 220px; display: block; margin: 0 auto;">
                </div>
                <div class="mt-2.5">
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 fs-11 fw-bold">
                        <i class="feather-shield me-1"></i>Official NIC QR Code
                    </span>
                </div>
                <div class="mt-2 text-muted fs-11">
                    Point your Phone Camera / Google Lens at this QR Code to scan.
                </div>
            </div>

            <div class="col-md-7 text-start">
                <h6 class="fw-bold text-dark fs-13 mb-2 pb-1 border-bottom d-flex justify-content-between align-items-center">
                    <span>Decoded Payload Details</span>
                    <span class="badge bg-primary text-white fs-10 font-monospace">Schema 1.03</span>
                </h6>
                
                @php
                    $qrDecoded = json_decode($invoice->signed_qrcode, true) ?: [];
                @endphp

                <div class="table-responsive">
                    <table class="table table-sm table-borderless fs-11 mb-2">
                        <tbody>
                            <tr>
                                <td class="text-muted py-0.5" style="width: 38%;">Seller GSTIN:</td>
                                <td class="fw-bold text-dark py-0.5 font-monospace">{{ $qrDecoded['sellerGstin'] ?? (tenant()?->gstin ?: '08AAFCS1234E1Z0') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted py-0.5">Buyer GSTIN:</td>
                                <td class="fw-bold text-dark py-0.5 font-monospace">{{ $qrDecoded['buyerGstin'] ?? ($invoice->customer?->gstin ?: '27AABCU9603R1ZM') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted py-0.5">Doc Number / Type:</td>
                                <td class="fw-semibold text-dark py-0.5">{{ $qrDecoded['docNo'] ?? $invoice->invoice_number }} ({{ $qrDecoded['docTyp'] ?? 'INV' }})</td>
                            </tr>
                            <tr>
                                <td class="text-muted py-0.5">Doc Date:</td>
                                <td class="text-dark py-0.5">{{ $qrDecoded['docDate'] ?? date('d/m/Y', strtotime($invoice->invoice_date)) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted py-0.5">Total Value:</td>
                                <td class="fw-bold text-primary py-0.5">{{ format_currency($qrDecoded['totInvVal'] ?? $invoice->total_amount) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted py-0.5">Ack Number:</td>
                                <td class="font-monospace fw-bold text-dark py-0.5">{{ $invoice->ack_no }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted py-0.5">Ack Date/Time:</td>
                                <td class="text-secondary py-0.5">{{ $invoice->ack_date ? date('d-M-Y H:i:s', strtotime($invoice->ack_date)) : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-2 bg-light rounded border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fs-10 fw-bold text-muted text-uppercase">IRN Hash (SHA-256):</span>
                        <button type="button" class="btn btn-2xs btn-light border py-0 px-1.5 fs-10" onclick="navigator.clipboard.writeText('{{ $invoice->irn }}'); alert('IRN copied to clipboard!');">
                            <i class="feather-copy me-1"></i>Copy IRN
                        </button>
                    </div>
                    <div class="font-monospace text-break fs-10 text-secondary" style="word-break: break-all; line-height: 1.4;">
                        {{ $invoice->irn }}
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary fw-bold px-3" data-bs-dismiss="modal">Close</button>
            <a href="https://api.qrserver.com/v1/create-qr-code/?size=600x600&data={{ urlencode($invoice->signed_qrcode ?: $invoice->irn) }}" target="_blank" download="EInvoice_QR_{{ $invoice->invoice_number }}.png" class="btn btn-sm btn-primary fw-bold px-3">
                <i class="feather-download me-1"></i>Download High-Res QR
            </a>
        </div>
    </div>
</x-ui.modal>

<!-- CANCEL E-INVOICE MODAL -->
<x-ui.modal id="cancelEInvoiceModal" title="<i class='feather-alert-triangle text-danger me-1.5'></i>Cancel GST E-Invoice (IRN)" size="md" :centered="true" :showFooter="false">
    <form action="{{ route('sales.invoices.einvoice.cancel', $invoice->id) }}" method="POST">
        @csrf
        <div class="alert alert-warning py-2 px-3 fs-11 mb-3">
            <i class="feather-alert-circle me-1"></i><strong>Warning:</strong> E-Invoice cancellation on the GST portal can only be performed within 24 hours of generation.
        </div>

        <div class="mb-3">
            <label class="form-label fs-12 fw-bold text-dark">IRN to Cancel</label>
            <input type="text" class="form-control form-control-sm font-monospace bg-light" value="{{ $invoice->irn }}" readonly>
        </div>

        <div class="mb-3">
            <label class="form-label fs-12 fw-bold text-dark">Cancellation Reason <span class="text-danger">*</span></label>
            <select name="cancel_reason" class="form-select form-select-sm" required>
                <option value="1">1 - Duplicate</option>
                <option value="2">2 - Data Entry Mistake</option>
                <option value="3" selected>3 - Order Cancelled</option>
                <option value="4">4 - Others</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label fs-12 fw-bold text-dark">Cancellation Remarks <span class="text-danger">*</span></label>
            <textarea name="cancel_remarks" class="form-control form-control-sm" rows="3" placeholder="State clear reason for IRN cancellation..." required></textarea>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-sm btn-danger fw-bold px-3">
                <i class="feather-slash me-1"></i>Confirm IRN Cancellation
            </button>
        </div>
    </form>
</x-ui.modal>

<!-- CANCEL E-WAY BILL MODAL -->
<x-ui.modal id="cancelEWayBillModal" title="<i class='feather-alert-triangle text-danger me-1.5'></i>Cancel GST E-Way Bill" size="md" :centered="true" :showFooter="false">
    <form action="{{ route('sales.invoices.ewaybill.cancel', $invoice->id) }}" method="POST">
        @csrf
        <div class="alert alert-warning py-2 px-3 fs-11 mb-3">
            <i class="feather-alert-circle me-1"></i><strong>Notice:</strong> An E-Way Bill can be cancelled within 24 hours of generation if the goods have not been inspected in transit.
        </div>

        <div class="mb-3">
            <label class="form-label fs-12 fw-bold text-dark">E-Way Bill Number</label>
            <input type="text" class="form-control form-control-sm font-monospace bg-light fw-bold text-primary" value="{{ $invoice->eway_bill_no }}" readonly>
        </div>

        <div class="mb-3">
            <label class="form-label fs-12 fw-bold text-dark">Cancellation Reason <span class="text-danger">*</span></label>
            <select name="cancel_reason" class="form-select form-select-sm" required>
                <option value="1">1 - Duplicate</option>
                <option value="2" selected>2 - Order Cancelled</option>
                <option value="3">3 - Data Entry Mistake</option>
                <option value="4">4 - Vehicle Breakdown / Transshipment</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label fs-12 fw-bold text-dark">Cancellation Remarks <span class="text-danger">*</span></label>
            <textarea name="cancel_remarks" class="form-control form-control-sm" rows="3" placeholder="Provide cancellation remarks..." required></textarea>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-sm btn-danger fw-bold px-3">
                <i class="feather-x-circle me-1"></i>Confirm EWB Cancellation
            </button>
        </div>
    </form>
</x-ui.modal>

<!-- RESULT NOTIFICATION MODAL -->
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

<!-- CREATE FINANCIAL CREDIT NOTE MODAL (Enterprise ERP Style) -->
<x-ui.modal id="createCreditNoteModal" title="<i class='feather-file-minus text-danger me-1.5'></i>{{ __('crm.issue_financial_credit_note') }}" size="lg" :centered="true" :showFooter="false">
    <form action="{{ route('sales.invoices.credit-note', $invoice->id) }}" method="POST" id="createCreditNoteForm">
        @csrf

        <!-- Informational Banner: Stock Integrity Safe -->
        <div class="alert alert-soft-success border border-success-subtle py-2.5 px-3 fs-12 mb-3 d-flex align-items-center rounded-3">
            <i class="feather-shield text-success fs-20 me-2.5 flex-shrink-0"></i>
            <div>
                <strong class="text-success d-block mb-0.5">{{ __('crm.credit_note_safe_title') }}</strong>
                <span class="text-muted">{{ __('crm.credit_note_safe_desc') }}</span>
            </div>
        </div>

        <!-- Invoice Financial Breakdown Summary Card -->
        <div class="card bg-light border mb-3 rounded-3">
            <div class="card-body p-3">
                <div class="row g-2 fs-12">
                    <div class="col-6 col-md-3">
                        <span class="text-muted d-block fs-11">{{ __('crm.invoice_number') }}</span>
                        <strong class="text-dark font-monospace fs-13">{{ $invoice->invoice_number }}</strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="text-muted d-block fs-11">{{ __('crm.customer') }}</span>
                        <strong class="text-dark text-truncate d-block fs-13">{{ $invoice->customer?->name ?: ($invoice->salesOrder?->customer?->name ?: 'Customer') }}</strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="text-muted d-block fs-11">Total Invoice Value</span>
                        <strong class="text-dark fs-13">{{ format_currency($invoice->total_amount) }}</strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="text-muted d-block fs-11">Amount Paid</span>
                        <strong class="{{ $invoice->amount_paid > 0 ? 'text-success' : 'text-muted' }} fs-13">{{ format_currency($invoice->amount_paid) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fs-12 fw-bold text-dark">{{ __('crm.cancellation_reason') }} <span class="text-danger">*</span></label>
                <select name="reason" class="form-select form-select-sm" required>
                    <option value="Order Cancelled before Dispatch" selected>{{ __('crm.order_cancelled_before_dispatch') }}</option>
                    <option value="Customer Requested Cancellation">{{ __('crm.customer_requested_cancellation') }}</option>
                    <option value="Billing / Quantity Mistake">{{ __('crm.billing_mistake') }}</option>
                    <option value="Duplicate Invoice">{{ __('crm.duplicate_invoice') }}</option>
                    <option value="Other Reason">{{ __('crm.other_reason') }}</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fs-12 fw-bold text-dark">{{ __('crm.credit_note_date') }} <span class="text-danger">*</span></label>
                <input type="date" name="credit_note_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
            </div>
        </div>

        <!-- Payment Handling Section (if invoice has any payment) -->
        @if ($invoice->amount_paid > 0)
            <div class="card border border-warning-subtle bg-soft-warning mb-3 rounded-3">
                <div class="card-body p-3">
                    <label class="form-label fs-12 fw-bold text-dark d-flex align-items-center mb-1">
                        <i class="feather-dollar-sign text-warning me-1.5 fs-15"></i>
                        {{ __('crm.settle_received_payment') }} ({{ format_currency($invoice->amount_paid) }})
                    </label>
                    <p class="fs-11 text-muted mb-2.5">
                        {{ __('crm.payment_received_notice') }}
                    </p>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="refund_action" id="refundActionCredit" value="credit_balance" checked onchange="$('#bankRefundSelectWrapper').slideUp(150);">
                        <label class="form-check-label fs-12 fw-semibold text-dark" for="refundActionCredit">
                            {{ __('crm.retain_as_customer_credit') }}
                            <span class="d-block fs-11 text-muted fw-normal">{{ __('crm.retain_as_customer_credit_desc') }}</span>
                        </label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="refund_action" id="refundActionBank" value="bank_refund" onchange="$('#bankRefundSelectWrapper').slideDown(150);">
                        <label class="form-check-label fs-12 fw-semibold text-dark" for="refundActionBank">
                            {{ __('crm.refund_via_bank') }}
                            <span class="d-block fs-11 text-muted fw-normal">{{ __('crm.refund_via_bank_desc') }}</span>
                        </label>
                    </div>

                    <div id="bankRefundSelectWrapper" class="mt-2.5 pt-2 border-top border-warning-subtle" style="display: none;">
                        <label class="form-label fs-11 fw-bold text-dark mb-1">{{ __('crm.select_refund_bank_account') }} <span class="text-danger">*</span></label>
                        <select name="refund_bank_account_id" class="form-select form-select-sm">
                            <option value="">-- {{ __('crm.choose_bank_or_cash_account') }} --</option>
                            @foreach ($cashBankAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->code }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        @else
            <input type="hidden" name="refund_action" value="credit_balance">
        @endif

        <div class="mb-3">
            <label class="form-label fs-12 fw-bold text-dark">{{ __('crm.notes_remarks') }} <span class="text-muted fw-normal">({{ __('crm.optional') }})</span></label>
            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="{{ __('crm.credit_note_remarks_placeholder') }}"></textarea>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" data-bs-dismiss="modal">{{ __('crm.close') }}</button>
            <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 d-inline-flex align-items-center" onclick="return confirm('{{ __('crm.confirm_issue_credit_note') }}');">
                <i class="feather-file-minus me-1.5"></i>{{ __('crm.issue_credit_note_btn') }}
            </button>
        </div>
    </form>
</x-ui.modal>

@push('scripts')
<script>
    const defaultInvoicePdfUrl = "{{ route('sales.invoices.previewPdf', $invoice->id) }}";
    let defaultInvoicePdfName = "Tax_Invoice_{{ $invoice->invoice_number }}.pdf";
    let customWaPdfFile = null;
    let customEmailPdfFile = null;

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
                    $('#btnSubmitSendInvoiceWA').prop('disabled', false);
                } else if (res.status === 'qr' && res.qr) {
                    $('#waStatusBadge').attr('class', 'badge bg-warning text-dark').text('Scan QR');
                    $('#waStatusText').text('Open WhatsApp app on your phone to scan QR code');
                    $('#waQrImg').attr('src', res.qr);
                    $('#waQrContainer').removeClass('d-none');
                    $('#btnConnectWA').addClass('d-none');
                    $('#btnSubmitSendInvoiceWA').prop('disabled', true);
                } else if (res.status === 'connecting') {
                    $('#waStatusBadge').attr('class', 'badge bg-info text-dark').text('Connecting...');
                    $('#waStatusText').text('Initializing Baileys socket...');
                    $('#waQrContainer').addClass('d-none');
                    $('#btnConnectWA').addClass('d-none');
                    $('#btnSubmitSendInvoiceWA').prop('disabled', true);
                } else {
                    $('#waStatusBadge').attr('class', 'badge bg-danger').text('Disconnected');
                    $('#waStatusText').text(res.message || 'No WhatsApp account linked.');
                    $('#waQrContainer').addClass('d-none');
                    $('#btnConnectWA').removeClass('d-none');
                    $('#btnSubmitSendInvoiceWA').prop('disabled', true);
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

    // ── WhatsApp Modal Handlers ─────────────────────────────────────
    $(document).on('click', '.btn-open-send-invoice-wa-modal', function () {
        const invId = $(this).attr('data-invoice-id');
        const invNum = $(this).attr('data-invoice-num');
        const cPhone = $(this).attr('data-client-phone') || '';
        const totalAmount = $(this).attr('data-total-amount') || '';
        const balanceDue = $(this).attr('data-balance-due') || '';

        defaultInvoicePdfName = 'Tax_Invoice_' + invNum + '.pdf';

        $('#sendInvoiceWhatsAppForm').attr('action', '/sales/invoices/' + invId + '/send-whatsapp');
        $('#sendWaPhone').val(cPhone);
        
        // Reset custom PDF attachment to default
        resetWaPdfToDefault();

        const companyName = "{{ tenant() ? tenant()->name : 'Accounts Team' }}";
        const defaultCaption = "Dear Valued Customer,\n\nPlease find attached Tax Invoice *" + invNum + "*.\n💰 *Total Amount:* " + totalAmount + "\n💳 *Balance Due:* " + balanceDue + "\n\nThank you for your business!\n*" + companyName + "*";
        $('#sendWaCaption').val(defaultCaption);

        const sendWaModal = new bootstrap.Modal(document.getElementById('sendInvoiceWhatsAppModal'));
        sendWaModal.show();
        checkWhatsAppStatus();
    });

    function resetWaPdfToDefault() {
        customWaPdfFile = null;
        $('#inputCustomWaPdf').val('');
        $('#sendWaPdfBadge').text(defaultInvoicePdfName);
        $('#sendWaPdfStatusBadge').attr('class', 'badge bg-soft-success text-success border px-2 py-0.5 fs-10').text('Auto Generated PDF');
        $('#sendWaPdfSubText').text('Base64 PDF Attachment generated from ERP System');
        $('#btnResetCustomWaPdf').addClass('d-none').removeClass('d-inline-flex');
    }

    $(document).on('click', '#btnTriggerCustomWaPdf', function() {
        $('#inputCustomWaPdf').click();
    });

    $(document).on('change', '#inputCustomWaPdf', function(e) {
        const file = e.target.files && e.target.files[0];
        if (file) {
            if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                alert('Please select a valid PDF file.');
                resetWaPdfToDefault();
                return;
            }
            customWaPdfFile = file;
            const sizeKb = (file.size / 1024).toFixed(1);
            $('#sendWaPdfBadge').text(file.name + ' (' + sizeKb + ' KB)');
            $('#sendWaPdfStatusBadge').attr('class', 'badge bg-soft-primary text-primary border px-2 py-0.5 fs-10').text('Custom PDF Selected');
            $('#sendWaPdfSubText').text('Custom document attached from your system');
            $('#btnResetCustomWaPdf').removeClass('d-none').addClass('d-inline-flex');
        }
    });

    $(document).on('click', '#btnResetCustomWaPdf', function() {
        resetWaPdfToDefault();
    });

    $(document).on('click', '#btnPreviewWaPdf', function() {
        if (customWaPdfFile) {
            const fileUrl = URL.createObjectURL(customWaPdfFile);
            window.open(fileUrl, '_blank');
        } else {
            window.open(defaultInvoicePdfUrl, '_blank');
        }
    });

    $(document).on('submit', '#sendInvoiceWhatsAppForm', function (e) {
        e.preventDefault();
        const form = $(this);
        const btn = $('#btnSubmitSendInvoiceWA');
        const origHtml = btn.html();

        btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>Sending WhatsApp...');

        const formData = new FormData(this);

        $.ajax({
            url: form.attr('action'),
            method: "POST",
            data: formData,
            processData: false,
            contentType: false,
            cache: false,
            success: function (res) {
                btn.prop('disabled', false).html(origHtml);
                const modalEl = document.getElementById('sendInvoiceWhatsAppModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                showNotificationModal(true, "WhatsApp Message Delivered!", res.message);
            },
            error: function (xhr) {
                btn.prop('disabled', false).html(origHtml);
                const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to send WhatsApp document.';
                const modalEl = document.getElementById('sendInvoiceWhatsAppModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                showNotificationModal(false, "WhatsApp Dispatch Failed", errMsg);
            }
        });
    });

    // ── Email Modal Handlers ────────────────────────────────────────
    $(document).on('click', '.btn-open-send-invoice-modal', function () {
        const invId = $(this).attr('data-invoice-id');
        const invNum = $(this).attr('data-invoice-num');
        const cEmail = $(this).attr('data-client-email') || '';
        const totalAmount = $(this).attr('data-total-amount') || '';
        const balanceDue = $(this).attr('data-balance-due') || '';
        const companyName = "{{ tenant() ? tenant()->name : config('app.name') }}";

        defaultInvoicePdfName = 'Tax_Invoice_' + invNum + '.pdf';

        $('#sendInvoiceEmailForm').attr('action', '/sales/invoices/' + invId + '/send-email');
        $('#sendInvoiceToEmail').val(cEmail);
        $('#sendInvoiceSubject').val('Tax Invoice ' + invNum + ' - ' + companyName);
        
        // Reset custom PDF attachment to default
        resetEmailPdfToDefault();

        const defaultBody = "Dear Valued Customer,\n\nPlease find attached Tax Invoice " + invNum + " for your records.\n\nTotal Amount: " + totalAmount + "\nBalance Due: " + balanceDue + "\n\nBest regards,\n" + companyName;
        $('#sendInvoiceBody').val(defaultBody);

        const sendModal = new bootstrap.Modal(document.getElementById('sendInvoiceEmailModal'));
        sendModal.show();
    });

    function resetEmailPdfToDefault() {
        customEmailPdfFile = null;
        $('#inputCustomEmailPdf').val('');
        $('#sendInvoicePdfBadge').text(defaultInvoicePdfName);
        $('#sendInvoicePdfStatusBadge').attr('class', 'badge bg-soft-danger text-danger border px-2 py-0.5 fs-10').text('Auto Generated PDF');
        $('#sendInvoicePdfSubText').text('Base64 PDF Attachment generated from ERP System');
        $('#btnResetCustomEmailPdf').addClass('d-none').removeClass('d-inline-flex');
    }

    $(document).on('click', '#btnTriggerCustomEmailPdf', function() {
        $('#inputCustomEmailPdf').click();
    });

    $(document).on('change', '#inputCustomEmailPdf', function(e) {
        const file = e.target.files && e.target.files[0];
        if (file) {
            if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                alert('Please select a valid PDF file.');
                resetEmailPdfToDefault();
                return;
            }
            customEmailPdfFile = file;
            const sizeKb = (file.size / 1024).toFixed(1);
            $('#sendInvoicePdfBadge').text(file.name + ' (' + sizeKb + ' KB)');
            $('#sendInvoicePdfStatusBadge').attr('class', 'badge bg-soft-primary text-primary border px-2 py-0.5 fs-10').text('Custom PDF Selected');
            $('#sendInvoicePdfSubText').text('Custom document attached from your system');
            $('#btnResetCustomEmailPdf').removeClass('d-none').addClass('d-inline-flex');
        }
    });

    $(document).on('click', '#btnResetCustomEmailPdf', function() {
        resetEmailPdfToDefault();
    });

    $(document).on('click', '#btnPreviewEmailPdf', function() {
        if (customEmailPdfFile) {
            const fileUrl = URL.createObjectURL(customEmailPdfFile);
            window.open(fileUrl, '_blank');
        } else {
            window.open(defaultInvoicePdfUrl, '_blank');
        }
    });

    $(document).on('submit', '#sendInvoiceEmailForm', function (e) {
        e.preventDefault();
        const form = $(this);
        const btn = $('#btnSubmitSendInvoiceEmail');
        const origHtml = btn.html();

        btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>Sending Email...');

        const formData = new FormData(this);

        $.ajax({
            url: form.attr('action'),
            method: "POST",
            data: formData,
            processData: false,
            contentType: false,
            cache: false,
            success: function (res) {
                btn.prop('disabled', false).html(origHtml);
                const modalEl = document.getElementById('sendInvoiceEmailModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                showNotificationModal(true, "Invoice Email Dispatched!", res.message);
            },
            error: function (xhr) {
                btn.prop('disabled', false).html(origHtml);
                const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to send Invoice Email.';
                const modalEl = document.getElementById('sendInvoiceEmailModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                showNotificationModal(false, "Email Dispatch Failed", errMsg);
            }
        });
    });

    // E-Way Bill Transporter Selection & Quick Create Handlers
    $(document).on('change', '#transporterSelect', function () {
        const val = $(this).val();
        if (val === '__ADD_NEW__') {
            $(this).val('').trigger('change.select2');
            const modalEl = document.getElementById('quickTransporterModal');
            if (modalEl) {
                const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
                modalInstance.show();
            }
            return;
        }

        if (val) {
            const $opt = $(this).find('option:selected');
            const name = $opt.data('name') || '';
            const gstin = $opt.data('gstin') || $opt.data('id') || '';
            const mode = $opt.data('mode') || '';

            if (gstin) $('#ewbTransporterId').val(gstin);
            if (name) $('#ewbTransporterName').val(name);
            if (mode && ['road', 'rail', 'air', 'ship'].includes(String(mode).toLowerCase())) {
                $('#ewbTransportMode').val(String(mode).toLowerCase()).trigger('change');
            }
        }
    });

    $(document).on('select2:select', '#transporterSelect', function (e) {
        const selectedId = (e && e.params && e.params.data) ? e.params.data.id : null;
        if (selectedId === '__ADD_NEW__') {
            $(this).val('').trigger('change.select2');
            const modalEl = document.getElementById('quickTransporterModal');
            if (modalEl) {
                const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
                modalInstance.show();
            }
        }
    });

    // Quick Transporter Form AJAX Submission
    $(document).on('submit', '#quickTransporterForm', function (e) {
        e.preventDefault();
        const form = $(this);
        const btn = $('#saveQuickTransporterBtn');
        const origHtml = btn.html();

        // Clear previous errors
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.invalid-feedback.ajax-feedback').remove();
        form.find('.transporter-alert-error').remove();

        // Client-side email validation check
        const emailInput = form.find('input[name="email"]');
        const emailVal = emailInput.val().trim();
        if (emailVal && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
            emailInput.addClass('is-invalid');
            emailInput.parent().find('.invalid-feedback.ajax-feedback').remove();
            emailInput.after('<div class="invalid-feedback d-block fs-11 mt-1 ajax-feedback">The email field must be a valid email address.</div>');
            emailInput.focus();
            return false;
        }

        btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>Saving...');

        $.ajax({
            url: "{{ route('platform.transporters.quick-create') }}",
            type: "POST",
            data: form.serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (res) {
                btn.prop('disabled', false).html(origHtml);
                if (res.success && res.transporter) {
                    const t = res.transporter;
                    const labelText = t.name + (t.transporter_id ? ' (' + t.transporter_id + ')' : (t.gstin ? ' (' + t.gstin + ')' : ''));
                    
                    const newOpt = new Option(labelText, t.id, true, true);
                    $(newOpt).attr('data-name', t.name)
                             .attr('data-id', t.transporter_id || t.gstin || '')
                             .attr('data-gstin', t.gstin || t.transporter_id || '')
                             .attr('data-mode', t.transport_mode || 'road');

                    $('#transporterSelect').append(newOpt).val(t.id).trigger('change').trigger('change.select2');

                    $('#ewbTransporterId').val(t.gstin || t.transporter_id || '');
                    $('#ewbTransporterName').val(t.name);
                    if (t.transport_mode && ['road', 'rail', 'air', 'ship'].includes(String(t.transport_mode).toLowerCase())) {
                        $('#ewbTransportMode').val(String(t.transport_mode).toLowerCase()).trigger('change');
                    }

                    const modalEl = document.getElementById('quickTransporterModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) modalInstance.hide();
                    form[0].reset();
                    form.find('.is-invalid').removeClass('is-invalid');
                    form.find('.invalid-feedback.ajax-feedback').remove();
                } else {
                    form.prepend('<div class="alert alert-danger py-2 px-3 fs-12 mb-3 transporter-alert-error">' + (res.message || 'Could not create transporter.') + '</div>');
                }
            },
            error: function (xhr) {
                btn.prop('disabled', false).html(origHtml);
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    let firstField = null;
                    $.each(errors, function(field, messages) {
                        const $input = form.find('[name="' + field + '"]');
                        if ($input.length) {
                            $input.addClass('is-invalid');
                            $input.parent().find('.invalid-feedback.ajax-feedback').remove();
                            $input.after('<div class="invalid-feedback d-block fs-11 mt-1 ajax-feedback">' + messages[0] + '</div>');
                            if (!firstField) firstField = $input;
                        } else {
                            form.prepend('<div class="alert alert-danger py-2 px-3 fs-12 mb-3 transporter-alert-error">' + messages[0] + '</div>');
                        }
                    });
                    if (firstField) {
                        firstField.focus();
                    }
                } else {
                    const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to save transporter.';
                    form.prepend('<div class="alert alert-danger py-2 px-3 fs-12 mb-3 transporter-alert-error">' + errMsg + '</div>');
                }
            }
        });
    });

    // Realtime clear invalid feedback on input/change
    $(document).on('input change', '#quickTransporterForm input, #quickTransporterForm select', function() {
        $(this).removeClass('is-invalid');
        $(this).parent().find('.invalid-feedback.ajax-feedback').remove();
        $('#quickTransporterForm').find('.transporter-alert-error').remove();
    });

    // Email blur validation for quick transporter form
    $(document).on('blur', '#quickTransporterForm input[name="email"]', function() {
        const val = $(this).val().trim();
        if (val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
            $(this).addClass('is-invalid');
            $(this).parent().find('.invalid-feedback.ajax-feedback').remove();
            $(this).after('<div class="invalid-feedback d-block fs-11 mt-1 ajax-feedback">The email field must be a valid email address.</div>');
        }
    });

    // Handle Odoo-style Advance Settlement Modal
    function updateAdvanceModalFields() {
        const $sel = $('#advanceSourceSelect');
        const val = $sel.val();
        if (!val) return;
        const parts = val.split(':');
        const type = parts[0];
        const id = parts[1];
        $('#advSourceType').val(type);
        if (type === 'allocation') {
            $('#advAllocId').val(id);
            $('#advCustPayId').val('');
        } else {
            $('#advAllocId').val('');
            $('#advCustPayId').val(id);
        }
        const avail = parseFloat($sel.find('option:selected').data('avail') || 0);
        const balDue = parseFloat("{{ (float)$invoice->balance_due }}");
        const defaultAmt = Math.min(avail, balDue);
        $('#advanceApplyAmountInput').val(defaultAmt.toFixed(2)).attr('max', defaultAmt.toFixed(2));
        $('#advAvailBadge').text('₹ ' + avail.toFixed(2));
    }

    $(document).on('change', '#advanceSourceSelect', updateAdvanceModalFields);
    $('#applyAdvanceModal').on('shown.bs.modal', function () {
        updateAdvanceModalFields();
        $('#advanceApplyAmountInput').focus().select();
    });
</script>

<!-- APPLY ADVANCE / RECONCILE PAYMENT MODAL -->
@if ($hasAvailableAdvances)
<div class="modal fade" id="applyAdvanceModal" tabindex="-1" aria-labelledby="applyAdvanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-2.5 px-3 border-bottom">
                <h6 class="modal-title fw-bold text-dark fs-14" id="applyAdvanceModalLabel">
                    <i class="feather-check-circle text-success me-1.5"></i>Apply Advance / Reconcile Payment
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('sales.invoices.apply-advance', $invoice->id) }}" method="POST">
                @csrf
                <div class="modal-body p-3 fs-12">
                    <div class="p-2.5 bg-light rounded border mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Invoice Total:</span>
                            <strong class="text-dark">{{ format_currency($invoice->total_amount) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Already Paid:</span>
                            <span class="text-success fw-bold">{{ format_currency($invoice->amount_paid) }}</span>
                        </div>
                        <div class="d-flex justify-content-between pt-1 border-top">
                            <span class="text-muted fw-bold">Current Balance Due:</span>
                            <strong class="text-danger fs-14 font-monospace">{{ format_currency($invoice->balance_due) }}</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold text-dark fs-12 mb-0">Select Advance Payment</label>
                            <span class="badge bg-soft-primary text-primary fs-11" id="advAvailBadge">Available: {{ format_currency($totalAvailAdvance) }}</span>
                        </div>
                        <select name="selected_source" id="advanceSourceSelect" class="form-select form-select-sm" required>
                            @if(!empty($availableAdvances))
                                @foreach($availableAdvances as $adv)
                                    <option value="allocation:{{ $adv->id }}" data-avail="{{ (float)$adv->allocated_amount }}">
                                        Sales Order Advance: {{ $adv->payment?->payment_number ?? 'Payment' }} (Avail: {{ format_currency($adv->allocated_amount) }})
                                    </option>
                                @endforeach
                            @endif
                            @if(!empty($unallocatedPayments))
                                @foreach($unallocatedPayments as $up)
                                    <option value="payment:{{ $up->customer_payment_id }}" data-avail="{{ (float)$up->allocated_amount }}">
                                        Customer Credit: {{ $up->payment?->payment_number ?? 'Payment' }} (Avail: {{ format_currency($up->allocated_amount) }})
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        <input type="hidden" name="source_type" id="advSourceType" value="allocation">
                        <input type="hidden" name="payment_allocation_id" id="advAllocId" value="{{ !empty($availableAdvances) ? $availableAdvances->first()?->id : '' }}">
                        <input type="hidden" name="customer_payment_id" id="advCustPayId" value="">
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold text-dark fs-12">Amount to Apply (₹)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="advanceApplyAmountInput" class="form-control form-control-sm font-monospace fw-bold" required>
                        <div class="form-text fs-11 text-muted">
                            You can apply the full amount or a partial amount. Any remaining advance remains available for future invoices.
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 bg-light border-top">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success fw-bold px-3">
                        <i class="feather-check me-1"></i>Confirm & Apply
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
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

