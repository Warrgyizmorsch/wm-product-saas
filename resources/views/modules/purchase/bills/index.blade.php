@extends('layouts.duralux')

@section('title', __('purchase.vendor_bills') . ' | SaaS ERP')
@section('page-title', __('purchase.vendor_bills_invoices'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.vendor_bills'))

@push('styles')
    <style>
        #billsTable th {
            white-space: nowrap !important;
            font-size: 11px !important;
            letter-spacing: 0.5px !important;
        }
        #billsTable td {
            vertical-align: middle !important;
            white-space: nowrap !important;
        }
        .vendor-inv-col {
            max-width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .action-icon-btn {
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
        .action-icon-btn.view-btn:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent) !important;
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
        }
    </style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.import-export-dropdown 
            type="vendor_bills" 
            :can-import="false" 
            :can-download-template="false" 
            export-route="{{ route('purchase.bills.export') }}" />
        <a href="{{ route('purchase.bills.create-service') }}" class="btn btn-primary fs-12 px-3 fw-semibold">
            <i class="feather-plus me-1.5"></i>{{ __('purchase.create_service_bill') }}
        </a>
    </div>
@endsection

@section('content')

    <div class="erp-single-panel">
        <!-- 1. Header Title & Common Filter (Top) -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-file-text me-2 text-primary"></i>{{ __('purchase.vendor_bills') }}
                </h5>
                <p class="text-muted fs-12 mb-0">{{ __('purchase.manage_vendor_invoices_help') }}</p>
            </div>

            <!-- Actions & Common Filter Panel -->
            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Search Box (CRM Leads / HRMS Style) -->
                <form method="GET" action="{{ route('purchase.bills.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('purchase.search_po_placeholder') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('purchase.bills.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <a href="{{ route('purchase.bills.create-service') }}" class="btn btn-sm btn-soft-primary fw-bold text-primary px-3 shadow-sm border border-primary-subtle">
                    <i class="feather-plus me-1"></i>{{ __('purchase.create_service_bill') }}
                </a>

                <form method="GET" action="{{ route('purchase.bills.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.search_keyword') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('purchase.search_po_placeholder') }}" value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('purchase.all_statuses') }}</option>
                                <option value="Posted" @selected(request('status') === 'Posted')>{{ __('purchase.status_posted') }}</option>
                                <option value="Paid" @selected(request('status') === 'Paid')>{{ __('purchase.status_paid') }}</option>
                                <option value="Partially Paid" @selected(request('status') === 'Partially Paid')>{{ __('purchase.status_partially_paid') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('purchase.bills.index') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- 2. Tab navigation (Below filter line) -->
        <x-ui.horizontal-tabs id="vendorBillsTabNav" class="mb-3" :tabs="[
            [
                'id' => 'tab-all-bills',
                'label' => __('purchase.tab_all_bills'),
                'active' => true,
                'icon' => 'feather-file-text',
            ],
            [
                'id' => 'tab-pending-bills',
                'label' => __('purchase.tab_pending_inbound_bills') . (($pendingGrnsCount ?? 0) > 0 ? ' (' . $pendingGrnsCount . ')' : ''),
                'active' => false,
                'icon' => 'feather-clock',
            ],
            [
                'id' => 'tab-pending-freight',
                'label' => __('purchase.tab_pending_outbound_freight') . (($pendingFreightCount ?? 0) > 0 ? ' (' . $pendingFreightCount . ')' : ''),
                'active' => false,
                'icon' => 'feather-truck',
            ]
        ]" />

        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="billsTable" class="mb-0">
                <thead>
                    <tr style="background-color: #e8ecf1 !important;">
                        <th style="min-width: 140px; background-color: #e8ecf1 !important;">{{ __('purchase.bill_number') }}</th>
                        <th style="min-width: 150px; background-color: #e8ecf1 !important;">{{ __('purchase.vendor_invoice_no') }}</th>
                        <th style="min-width: 180px; background-color: #e8ecf1 !important;">{{ __('purchase.supplier_vendor') }}</th>
                        <th style="min-width: 110px; background-color: #e8ecf1 !important;">{{ __('purchase.bill_date') }}</th>
                        <th style="min-width: 110px; background-color: #e8ecf1 !important;">{{ __('purchase.due_date') }}</th>
                        <th style="min-width: 100px; background-color: #e8ecf1 !important;" class="text-center">{{ __('purchase.status') }}</th>
                        <th style="min-width: 120px; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('purchase.grand_total') }}</th>
                        <th style="min-width: 120px; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('purchase.paid_amount') }}</th>
                        <th style="min-width: 120px; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('purchase.due_amount') }}</th>
                        <th style="min-width: 70px; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('purchase.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bills as $bill)
                        @php
                            $statusText = match($bill->status) {
                                'Paid' => 'Paid',
                                'Partially Paid' => 'Partially Paid',
                                'Unpaid' => 'Unpaid',
                                'Posted' => 'Posted',
                                'Cancelled' => 'Cancelled',
                                default => $bill->status,
                            };
                            $badgeClass = match($bill->status) {
                                'Paid' => 'success',
                                'Partially Paid' => 'info',
                                'Unpaid' => 'danger',
                                'Posted', 'Draft' => 'warning',
                                'Cancelled' => 'secondary',
                                default => 'secondary',
                            };

                            $billJson = json_encode([
                                'id' => $bill->id,
                                'bill_number' => $bill->bill_number,
                                'vendor_invoice_number' => $bill->vendor_invoice_number ?: '—',
                                'vendor_name' => $bill->vendor?->name ?: '—',
                                'bill_date' => $bill->bill_date ? $bill->bill_date->format('d-M-Y') : '—',
                                'due_date' => $bill->due_date ? $bill->due_date->format('d-M-Y') : '—',
                                'status' => $statusText,
                                'badge_class' => $badgeClass,
                                'grn_number' => $bill->goodsReceiptNote?->grn_number ?: '—',
                                'po_number' => $bill->purchaseOrder?->purchase_order_number ?: '—',
                                'grand_total' => format_currency($bill->grand_total),
                                'paid_amount' => format_currency($bill->paid_amount),
                                'due_amount_raw' => (float)$bill->due_amount,
                                'due_amount' => format_currency($bill->due_amount),
                                'payment_url' => route('purchase.payments.create', ['bill_id' => $bill->id]),
                                'show_url' => route('purchase.bills.show', $bill->id),
                                'items' => $bill->items->map(fn($item) => [
                                    'product_name' => $item->product?->name ?: 'Item',
                                    'quantity' => floatval($item->quantity),
                                    'unit_rate' => format_currency($item->unit_rate),
                                    'total_amount' => format_currency($item->total_amount),
                                ])->values()->all(),
                            ]);
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('purchase.bills.show', $bill->id) }}" class="fw-bold text-primary">
                                    {{ $bill->bill_number }}
                                </a>
                                @if($bill->goodsReceiptNote)
                                    <small class="text-muted d-block fs-11">GRN: {{ $bill->goodsReceiptNote->grn_number }}</small>
                                @endif
                            </td>
                            <td class="font-monospace fw-semibold vendor-inv-col" title="{{ $bill->vendor_invoice_number ?: '—' }}">{{ $bill->vendor_invoice_number ?: '—' }}</td>
                            <td class="fw-semibold text-dark">{{ $bill->vendor?->name ?: '—' }}</td>
                            <td>{{ $bill->bill_date ? $bill->bill_date->format('d-M-Y') : '—' }}</td>
                            <td>{{ $bill->due_date ? $bill->due_date->format('d-M-Y') : '—' }}</td>
                            <td class="text-center">
                                <span class="badge bg-soft-{{ $badgeClass }} text-{{ $badgeClass }} px-2.5 py-1 fs-11 fw-bold">
                                    {{ $statusText }}
                                </span>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark">{{ format_currency($bill->grand_total) }}</td>
                            <td class="text-end font-monospace text-success fw-semibold">{{ format_currency($bill->paid_amount) }}</td>
                            <td class="text-end font-monospace fw-bold text-danger">{{ format_currency($bill->due_amount) }}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <button type="button" class="action-icon-btn view-btn btn-view-offcanvas" data-bill='{{ $billJson }}' title="{{ __('purchase.view_details') }}" data-bs-toggle="tooltip">
                                        <i class="feather feather-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="feather-info fs-36 text-secondary d-block mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">{{ __('purchase.no_vendor_bills_found') }}</h6>
                                <p class="fs-12 mb-0">{{ __('purchase.no_vendor_bills_help') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <x-ui.pagination 
            :currentPage="$bills->currentPage()" 
            :totalPages="$bills->lastPage()" 
            :totalResults="$bills->total()" 
            :perPage="$bills->perPage()" 
        />
    </div>

    <!-- Offcanvas Drawer for Vendor Bill Details -->
    <div class="offcanvas offcanvas-end shadow-lg" tabindex="-1" id="billDetailsOffcanvas" style="width: 700px; max-width: 94vw;">
        <div class="offcanvas-header border-bottom bg-light py-3 px-4">
            <div>
                <h5 class="offcanvas-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="feather-file-text text-primary"></i>
                    <span id="ocBillNumber">BILL-0000</span>
                </h5>
                <small class="text-muted fs-13" id="ocVendorName">Vendor Name</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span id="ocStatusBadge" class="badge px-3 py-1.5 fs-12 fw-bold">Posted</span>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
        </div>

        <div class="offcanvas-body p-4 text-dark fs-13">
            <!-- Document Details -->
            <div class="card border-0 bg-soft-light p-3 mb-4 rounded">
                <div class="row g-3 fs-13">
                    <div class="col-md-3 col-6">
                        <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-1">{{ __('purchase.vendor_invoice_no') }}</span>
                        <strong id="ocVendorInvoice" class="font-monospace text-dark fs-13">—</strong>
                    </div>
                    <div class="col-md-3 col-6">
                        <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-1">{{ __('purchase.grn_reference') }}</span>
                        <strong id="ocGrnNumber" class="text-primary fw-bold fs-13">—</strong>
                    </div>
                    <div class="col-md-3 col-6">
                        <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-1">{{ __('purchase.bill_date') }}</span>
                        <span id="ocBillDate" class="fw-semibold text-dark fs-13">—</span>
                    </div>
                    <div class="col-md-3 col-6">
                        <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-1">{{ __('purchase.due_date') }}</span>
                        <span id="ocDueDate" class="fw-semibold text-dark fs-13">—</span>
                    </div>
                </div>
            </div>

            <!-- Financial Summary -->
            <div class="card border p-3 mb-4 rounded">
                <h6 class="fw-bold text-dark mb-3 fs-12 text-uppercase"><i class="feather-bar-chart-2 text-success me-1"></i> {{ __('purchase.financial_summary') }}</h6>
                <div class="d-flex justify-content-between py-1.5 border-bottom">
                    <span class="text-muted">{{ __('purchase.grand_total') }}:</span>
                    <strong class="font-monospace text-dark fs-14" id="ocGrandTotal">{{ format_currency(0) }}</strong>
                </div>
                <div class="d-flex justify-content-between py-1.5 border-bottom">
                    <span class="text-muted">{{ __('purchase.paid_amount') }}:</span>
                    <span class="font-monospace text-success fw-bold fs-14" id="ocPaidAmount">{{ format_currency(0) }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 mt-1">
                    <span class="fw-bold text-dark fs-14">{{ __('purchase.outstanding_due') }}:</span>
                    <strong class="font-monospace text-danger fs-16" id="ocDueAmount">{{ format_currency(0) }}</strong>
                </div>
            </div>

            <!-- Products Table -->
            <div class="mb-3">
                <h6 class="fw-bold text-dark mb-2 fs-12 text-uppercase"><i class="feather-box text-primary me-1"></i> {{ __('purchase.bill_line_items') }}</h6>
                <div class="rounded border" style="overflow-x: auto;">
                    <table class="table table-sm table-striped align-middle fs-13 mb-0" style="width: 100%;">
                        <thead class="table-light text-muted fw-semibold">
                            <tr>
                                <th style="width: 46%;">{{ __('purchase.product') }}</th>
                                <th class="text-center" style="width: 14%;">{{ __('purchase.quantity') }}</th>
                                <th class="text-end" style="width: 20%;">{{ __('purchase.unit_rate') }} ({{ active_currency_symbol() }})</th>
                                <th class="text-end" style="width: 20%;">{{ __('purchase.line_total') }} ({{ active_currency_symbol() }})</th>
                            </tr>
                        </thead>
                        <tbody id="ocItemsBody">
                            <!-- Dynamic Items -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="offcanvas-footer p-3 px-4 border-top bg-light d-flex flex-column gap-2">
            <a href="#" id="ocRegisterPaymentBtn" class="btn btn-success text-white fw-bold shadow-sm py-2.5 text-center fs-13">
                <i class="feather-credit-card me-1.5"></i>{{ __('purchase.register_payment') }}
            </a>
            <a href="#" id="ocFullDetailsBtn" class="btn btn-light border fw-semibold py-2 text-center fs-12 text-muted">
                <i class="feather-external-link me-1"></i>{{ __('purchase.view_full_bill_page') }}
            </a>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#tab-pending-bills-tab').on('click', function() {
                window.location.href = "{{ route('purchase.bills.pending') }}";
            });

            $(document).on('click', '.btn-view-offcanvas', function(e) {
                e.preventDefault();
                const bill = $(this).data('bill');
                if (!bill) return;

                $('#ocBillNumber').text(bill.bill_number);
                $('#ocVendorName').text(bill.vendor_name);
                $('#ocVendorInvoice').text(bill.vendor_invoice_number);
                $('#ocGrnNumber').text(bill.grn_number);
                $('#ocBillDate').text(bill.bill_date);
                $('#ocDueDate').text(bill.due_date);
                $('#ocGrandTotal').text(bill.grand_total);
                $('#ocPaidAmount').text(bill.paid_amount);
                $('#ocDueAmount').text(bill.due_amount);

                const statusBadge = $('#ocStatusBadge');
                statusBadge.attr('class', 'badge px-2.5 py-1 fs-11 fw-bold bg-soft-' + bill.badge_class + ' text-' + bill.badge_class);
                statusBadge.text(bill.status);

                let itemsHtml = '';
                if (bill.items && bill.items.length > 0) {
                    bill.items.forEach(function(item) {
                        itemsHtml += `
                            <tr>
                                <td class="fw-semibold text-dark">${escapeHtml(item.product_name)}</td>
                                <td class="text-center font-monospace">${item.quantity}</td>
                                <td class="text-end font-monospace">${item.unit_rate}</td>
                                <td class="text-end font-monospace fw-bold">${item.total_amount}</td>
                            </tr>
                        `;
                    });
                } else {
                    itemsHtml = '<tr><td colspan="4" class="text-center text-muted py-2">No line items found</td></tr>';
                }
                $('#ocItemsBody').html(itemsHtml);

                const payBtn = $('#ocRegisterPaymentBtn');
                if (bill.due_amount_raw > 0) {
                    payBtn.attr('href', bill.payment_url).show();
                } else {
                    payBtn.hide();
                }

                $('#ocFullDetailsBtn').attr('href', bill.show_url);

                const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance('#billDetailsOffcanvas');
                bsOffcanvas.show();
            });

            function escapeHtml(str) {
                return String(str || '').replace(/[&<>"']/g, function (s) {
                    return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': '&quot;', "'": '&#39;' }[s];
                });
            }
            $('#tab-pending-bills-tab').on('click', function() {
                window.location.href = "{{ route('purchase.bills.pending') }}";
            });
            $('#tab-pending-freight-tab').on('click', function() {
                window.location.href = "{{ route('purchase.bills.pending-freight') }}";
            });
        });
    </script>
@endpush

@endsection
