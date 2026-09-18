@extends('layouts.duralux')

@section('title', __('purchase.supplier_finance_360') . ' | ' . $vendor->name)
@section('page-title', __('purchase.supplier_finance_360_view'))
@section('breadcrumb', __('purchase.supply_chain_purchase_vendors_profile'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('purchase.vendors.index') }}" class="btn btn-light border p-2 d-inline-flex align-items-center justify-content-center" title="{{ __('purchase.back_to_suppliers') }}">
            <i class="feather-arrow-left fs-16"></i>
        </a>
        <a href="{{ route('purchase.vendors.edit', $vendor->id) }}" class="btn btn-light border" title="{{ __('purchase.edit_vendor_details') }}">
            <i class="feather-edit-2 me-1"></i>{{ __('purchase.edit') }}
        </a>
        @if(Route::has('purchase.orders.create'))
            <x-ui.button href="{{ route('purchase.orders.create', ['vendor_id' => $vendor->id]) }}" variant="primary" icon="feather-plus">
                {{ __('purchase.create_po') }}
            </x-ui.button>
        @endif
        @if(Route::has('purchase.bills.create'))
            <x-ui.button href="{{ route('purchase.bills.create', ['vendor_id' => $vendor->id]) }}" variant="success" icon="feather-file-plus">
                {{ __('purchase.create_vendor_bill') }}
            </x-ui.button>
        @endif
        @if(Route::has('purchase.payments.create'))
            <x-ui.button href="{{ route('purchase.payments.create', ['vendor_id' => $vendor->id]) }}" variant="warning" icon="feather-credit-card">
                {{ __('purchase.register_payment') }}
            </x-ui.button>
        @endif
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
        <x-ui.odoo-form-ui type="sheet">

            {{-- 1. Single Page Header: Vendor Profile Info --}}
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 pb-3 border-bottom mb-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="avatar-text avatar-xl bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold fs-22 shadow-sm flex-shrink-0" style="width: 56px; height: 56px;">
                        {{ strtoupper(substr($vendor->name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <h4 class="fw-bold text-dark mb-0 fs-19 me-1">{{ $vendor->name }}</h4>
                            @if(strtolower($vendor->status) === 'active')
                                <x-ui.status-badge status="active" :label="__('purchase.active_supplier')" dot="true" size="sm" />
                            @else
                                <x-ui.status-badge status="inactive" :label="__('purchase.inactive_supplier')" dot="true" size="sm" />
                            @endif

                            <span class="badge bg-soft-secondary text-dark border font-monospace fs-11 px-2.5 py-1">
                                {{ __('purchase.code_lbl') }} {{ $vendor->code ?: 'N/A' }}
                            </span>
                        </div>

                        <div class="d-flex align-items-center gap-3 text-muted fs-12 mt-2 flex-wrap">
                            @if($vendor->company_name)
                                <span><i class="feather-building me-1 text-primary"></i>{{ __('purchase.company_lbl') }} <strong class="text-dark">{{ $vendor->company_name }}</strong></span>
                                <span class="text-black-50">•</span>
                            @endif
                            @if($vendor->gstin)
                                <span><strong class="text-dark">{{ __('purchase.gstin_lbl') }}</strong> <span class="font-monospace text-primary fw-bold">{{ $vendor->gstin }}</span></span>
                                <span class="text-black-50">•</span>
                            @endif
                            @if($vendor->phone)
                                <span><i class="feather-phone me-1 text-primary"></i><strong class="text-dark">{{ $vendor->phone }}</strong></span>
                                <span class="text-black-50">•</span>
                            @endif
                            @if($vendor->email)
                                <span><i class="feather-mail me-1 text-primary"></i><a href="mailto:{{ $vendor->email }}" class="text-primary fw-semibold">{{ $vendor->email }}</a></span>
                            @endif
                        </div>

                        @if($vendor->address || $vendor->billing_address || $vendor->shipping_address)
                            <div class="mt-2 pt-2 d-flex align-items-center gap-3 fs-11 text-muted flex-wrap">
                                @if($vendor->address)
                                    <span><i class="feather-map-pin me-1 text-danger"></i><strong>{{ __('purchase.primary_lbl') }}</strong> {{ Str::limit($vendor->address, 65) }}</span>
                                @endif
                                @if($vendor->billing_address)
                                    <span><i class="feather-file-text me-1 text-primary"></i><strong>{{ __('purchase.billing_lbl') }}</strong> {{ Str::limit($vendor->billing_address, 65) }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="text-end">
                    <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">{{ __('purchase.payable_health_status') }}</span>
                    @if(($vendorAdvanceCredit ?? 0) > 0)
                        <span class="badge bg-soft-info text-info fs-12 px-3 py-1.5 fw-bold border"><i class="feather-arrow-down-left me-1"></i>{{ __('purchase.advance_paid_kpi') }} ({!! format_currency($vendorAdvanceCredit) !!})</span>
                    @elseif($outstandingPayable <= 0)
                        <span class="badge bg-soft-success text-success fs-12 px-3 py-1.5 fw-bold border"><i class="feather-check-circle me-1"></i>{{ __('purchase.all_settled_zero_dues') }}</span>
                    @elseif($overdueAmount > 0)
                        <span class="badge bg-soft-danger text-danger fs-12 px-3 py-1.5 fw-bold border"><i class="feather-alert-triangle me-1"></i>{{ __('purchase.overdue_payment_dues') }}</span>
                    @else
                        <span class="badge bg-soft-warning text-warning fs-12 px-3 py-1.5 fw-bold border"><i class="feather-clock me-1"></i>{{ __('purchase.payment_dues_pending') }}</span>
                    @endif
                </div>
            </div>

            {{-- 2. Finance KPI Stat Cards --}}
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="p-3 rounded-3 border bg-light shadow-2xs">
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">{{ __('purchase.total_bills_amount') }}</span>
                        <h4 class="fw-bold text-dark mb-0 fs-18 font-monospace">{!! format_currency($totalBilled) !!}</h4>
                        <span class="fs-11 text-muted">{{ __('purchase.vendor_bills_count', ['count' => $bills->count()]) }}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-3 border bg-soft-success shadow-2xs">
                        <span class="text-success fs-11 fw-bold text-uppercase d-block mb-1">{{ __('purchase.total_paid_amount') }}</span>
                        <h4 class="fw-bold text-success mb-0 fs-18 font-monospace">{!! format_currency($totalPaid) !!}</h4>
                        <span class="fs-11 text-success">{{ __('purchase.payment_records_count', ['count' => $payments->count()]) }}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-3 border bg-soft-danger shadow-2xs">
                        <span class="text-danger fs-11 fw-bold text-uppercase d-block mb-1">{{ __('purchase.net_outstanding_dues') }}</span>
                        <h4 class="fw-bold text-danger mb-0 fs-18 font-monospace">{!! format_currency($outstandingPayable) !!}</h4>
                        <span class="fs-11 text-danger">{{ __('purchase.net_payable_balance') }}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-3 border bg-soft-warning shadow-2xs">
                        <span class="text-warning fs-11 fw-bold text-uppercase d-block mb-1">{{ __('purchase.overdue_amount') }}</span>
                        <h4 class="fw-bold text-warning mb-0 fs-18 font-monospace">{!! format_currency($overdueAmount) !!}</h4>
                        <span class="fs-11 text-warning">{{ __('purchase.passed_due_date') }}</span>
                    </div>
                </div>
            </div>

            {{-- 3. Interactive Tabs Navigation --}}
            <ul class="nav nav-tabs custom-tabs mb-4 border-bottom" id="vendorTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold fs-13" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab"><i class="feather-info me-1.5"></i>{{ __('purchase.profile_master_info') }}</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold fs-13" id="bills-tab" data-bs-toggle="tab" data-bs-target="#bills-pane" type="button" role="tab"><i class="feather-file-text me-1.5"></i>{{ __('purchase.vendor_bills_tab') }} ({{ $bills->count() }})</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold fs-13" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments-pane" type="button" role="tab"><i class="feather-credit-card me-1.5"></i>{{ __('purchase.payments_paid_tab') }} ({{ $payments->count() }})</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold fs-13" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders-pane" type="button" role="tab"><i class="feather-truck me-1.5"></i>{{ __('purchase.purchase_orders_tab') }} ({{ $purchaseOrders->count() }})</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold fs-13 text-primary" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger-pane" type="button" role="tab"><i class="feather-book me-1.5"></i>{{ __('purchase.vendor_ledger_statement') }}</button>
                </li>
            </ul>

            {{-- 4. Tab Content Panels --}}
            <div class="tab-content" id="vendorTabsContent">

                {{-- TAB 1: OVERVIEW --}}
                <div class="tab-pane fade show active" id="overview-pane" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 bg-white">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-briefcase me-2"></i>{{ __('purchase.supplier_master_details') }}</h6>
                                <table class="table table-borderless table-sm mb-0 fs-13">
                                    <tr>
                                        <td class="text-muted" style="width: 40%;">{{ __('purchase.supplier_name') }}:</td>
                                        <td class="fw-bold text-dark">{{ $vendor->name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">{{ __('purchase.company_trade_name') }}:</td>
                                        <td class="fw-semibold text-dark">{{ $vendor->company_name ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">{{ __('purchase.supplier_code_id') }}:</td>
                                        <td class="font-monospace text-primary fw-bold">{{ $vendor->code ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">{{ __('purchase.email_address') }}:</td>
                                        <td>{{ $vendor->email ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">{{ __('purchase.phone_mobile') }}:</td>
                                        <td>{{ $vendor->phone ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">{{ __('purchase.status') }}:</td>
                                        <td>
                                            @if(strtolower($vendor->status) === 'active')
                                                <x-ui.status-badge status="active" :label="__('purchase.active')" dot="true" size="sm" />
                                            @else
                                                <x-ui.status-badge status="inactive" :label="__('purchase.inactive')" dot="true" size="sm" />
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 bg-white">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-credit-card me-2"></i>{{ __('purchase.tax_banking_setup') }}</h6>
                                <table class="table table-borderless table-sm mb-0 fs-13">
                                    <tr>
                                        <td class="text-muted" style="width: 40%;">{{ __('purchase.gstin_tax_id') }}:</td>
                                        <td class="font-monospace text-primary fw-bold">{{ $vendor->gstin ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">{{ __('purchase.pan_number') }}:</td>
                                        <td class="font-monospace text-dark fw-semibold">{{ $vendor->pan ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">{{ __('purchase.bank_name') }}:</td>
                                        <td class="fw-semibold text-dark">{{ $vendor->bank_name ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">{{ __('purchase.bank_account_number') }}:</td>
                                        <td class="font-monospace text-dark">{{ $vendor->account_number ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">{{ __('purchase.ifsc_swift_code') }}:</td>
                                        <td class="font-monospace text-dark">{{ $vendor->ifsc_code ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">{{ __('purchase.default_payment_terms') }}:</td>
                                        <td class="fw-bold text-success">{{ $vendor->payment_terms ?: __('purchase.standard_net_30_days') }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="p-3 border rounded-3 bg-white">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-map-pin me-2"></i>{{ __('purchase.addresses_locations') }}</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">{{ __('purchase.primary_office_address') }}</label>
                                        <p class="fs-13 text-dark mb-0">{!! nl2br(e($vendor->address ?: 'N/A')) !!}</p>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">{{ __('purchase.billing_address') }}</label>
                                        <p class="fs-13 text-dark mb-0">{!! nl2br(e($vendor->billing_address ?: 'N/A')) !!}</p>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">{{ __('purchase.dispatch_warehouse_address') }}</label>
                                        <p class="fs-13 text-dark mb-0">{!! nl2br(e($vendor->shipping_address ?: 'N/A')) !!}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- TAB 2: VENDOR BILLS --}}
                <div class="tab-pane fade" id="bills-pane" role="tabpanel">
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" class="mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('purchase.bill_number') }}</th>
                                    <th>{{ __('purchase.challan_inv_no') }}</th>
                                    <th>{{ __('purchase.bill_date') }}</th>
                                    <th>{{ __('purchase.due_date') }}</th>
                                    <th class="text-end">{{ __('purchase.total_amount') }}</th>
                                    <th class="text-end">{{ __('purchase.paid_amount') }}</th>
                                    <th class="text-end">{{ __('purchase.balance_due') }}</th>
                                    <th class="text-center">{{ __('purchase.status') }}</th>
                                    <th class="text-end">{{ __('purchase.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($bills as $bill)
                                    <tr>
                                        <td class="font-monospace fw-bold text-primary">
                                            <a href="{{ route('purchase.bills.show', $bill->id) }}">
                                                #{{ $bill->bill_number ?: $bill->id }}
                                            </a>
                                        </td>
                                        <td class="font-monospace text-dark">{{ $bill->vendor_invoice_number ?: 'N/A' }}</td>
                                        <td>{{ \Carbon\Carbon::parse($bill->bill_date)->format('d-M-Y') }}</td>
                                        <td>
                                            @if($bill->due_date)
                                                @php $isPast = \Carbon\Carbon::parse($bill->due_date)->isPast() && (float)$bill->balance_due > 0; @endphp
                                                <span class="{{ $isPast ? 'text-danger fw-bold' : '' }}">
                                                    {{ \Carbon\Carbon::parse($bill->due_date)->format('d-M-Y') }}
                                                    @if($isPast)<i class="feather-alert-circle ms-1" title="{{ __('purchase.overdue') }}"></i>@endif
                                                </span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end font-monospace fw-bold text-dark">{!! format_currency($bill->total_amount) !!}</td>
                                        <td class="text-end font-monospace text-success">{!! format_currency($bill->amount_paid) !!}</td>
                                        <td class="text-end font-monospace fw-bold text-danger">{!! format_currency($bill->balance_due) !!}</td>
                                        <td class="text-center">
                                            <x-ui.status-badge :status="$bill->status" size="sm" />
                                        </td>
                                        <td class="text-end pe-3">
                                            <x-ui.action-dropdown :viewUrl="route('purchase.bills.show', $bill->id)" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">{{ __('purchase.no_vendor_bills_for_supplier') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                {{-- TAB 3: PAYMENTS PAID --}}
                <div class="tab-pane fade" id="payments-pane" role="tabpanel">
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" class="mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('purchase.payment_number') }}</th>
                                    <th>{{ __('purchase.payment_date') }}</th>
                                    <th>{{ __('purchase.payment_method') }}</th>
                                    <th>{{ __('purchase.ref_txn_no') }}</th>
                                    <th class="text-end">{{ __('purchase.paid_amount') }}</th>
                                    <th class="text-center">{{ __('purchase.status') }}</th>
                                    <th class="text-end pe-3">{{ __('purchase.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($payments as $pmt)
                                    <tr>
                                        <td class="font-monospace fw-bold text-primary">
                                            <a href="{{ route('purchase.payments.show', $pmt->id) }}">
                                                #{{ $pmt->payment_number ?: $pmt->id }}
                                            </a>
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($pmt->payment_date)->format('d-M-Y') }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ ucfirst($pmt->payment_method ?: 'Bank') }}</span></td>
                                        <td class="font-monospace text-muted">{{ $pmt->reference_number ?: 'N/A' }}</td>
                                        <td class="text-end font-monospace fw-bold text-success">{!! format_currency($pmt->amount) !!}</td>
                                        <td class="text-center">
                                            <x-ui.status-badge :status="$pmt->status" size="sm" />
                                        </td>
                                        <td class="text-end pe-3">
                                            <x-ui.action-dropdown :viewUrl="route('purchase.payments.show', $pmt->id)" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">{{ __('purchase.no_payments_for_supplier') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                {{-- TAB 4: PURCHASE ORDERS --}}
                <div class="tab-pane fade" id="orders-pane" role="tabpanel">
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" class="mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('purchase.po_number') }}</th>
                                    <th>{{ __('purchase.order_date') }}</th>
                                    <th>{{ __('purchase.expected_date') }}</th>
                                    <th class="text-center">{{ __('purchase.items') }}</th>
                                    <th class="text-end">{{ __('purchase.total_amount') }}</th>
                                    <th class="text-center">{{ __('purchase.status') }}</th>
                                    <th class="text-end pe-3">{{ __('purchase.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($purchaseOrders as $po)
                                    <tr>
                                        <td class="font-monospace fw-bold text-primary">
                                            <a href="{{ route('purchase.orders.show', $po->id) }}">
                                                {{ $po->purchase_order_number }}
                                            </a>
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($po->order_date)->format('d-M-Y') }}</td>
                                        <td>{{ $po->expected_date ? \Carbon\Carbon::parse($po->expected_date)->format('d-M-Y') : '—' }}</td>
                                        <td class="text-center font-monospace">{{ __('purchase.items_count_badge', ['count' => $po->items->count()]) }}</td>
                                        <td class="text-end font-monospace fw-bold text-dark">{!! format_currency($po->total_amount) !!}</td>
                                        <td class="text-center">
                                            <x-ui.status-badge :status="$po->status" size="sm" />
                                        </td>
                                        <td class="text-end pe-3">
                                            <x-ui.action-dropdown :viewUrl="route('purchase.orders.show', $po->id)" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">{{ __('purchase.no_pos_for_supplier') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                {{-- TAB 5: VENDOR LEDGER STATEMENT --}}
                <div class="tab-pane fade" id="ledger-pane" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="fw-bold text-primary mb-0"><i class="feather-book me-1"></i>{{ __('purchase.account_payable_ledger_statement') }}</h6>
                            <span class="fs-12 text-muted">{{ __('purchase.chronological_statement_help') }}</span>
                        </div>
                        <div>
                            <span class="badge bg-soft-primary text-primary px-3 py-2 fs-12 fw-bold font-monospace border">
                                {{ __('purchase.current_running_payable') }} {!! format_currency($outstandingPayable) !!}
                            </span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" class="mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('purchase.date') }}</th>
                                    <th>{{ __('purchase.voucher_ref_no') }}</th>
                                    <th>{{ __('purchase.entry_type') }}</th>
                                    <th>{{ __('purchase.description') }}</th>
                                    <th class="text-end">{{ __('purchase.debit_settlement') }}</th>
                                    <th class="text-end">{{ __('purchase.credit_bill_payable') }}</th>
                                    <th class="text-end">{{ __('purchase.running_payable_balance') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($vendor->opening_balance > 0)
                                    <tr class="table-warning">
                                        <td class="fw-bold">—</td>
                                        <td class="font-monospace fw-bold">OPENING-BAL</td>
                                        <td><span class="badge bg-warning text-dark">{{ __('purchase.opening_balance') }}</span></td>
                                        <td>{{ __('purchase.initial_supplier_opening_dues') }}</td>
                                        <td class="text-end font-monospace">0.00</td>
                                        <td class="text-end font-monospace fw-bold text-dark">{!! format_currency($vendor->opening_balance) !!}</td>
                                        <td class="text-end font-monospace fw-bold text-primary">{!! format_currency($vendor->opening_balance) !!}</td>
                                    </tr>
                                @endif

                                @forelse($ledgerWithBalance as $entry)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($entry['date'])->format('d-M-Y') }}</td>
                                        <td class="font-monospace fw-bold">
                                            @if($entry['url'])
                                                <a href="{{ $entry['url'] }}" class="text-primary">{{ $entry['reference'] }}</a>
                                            @else
                                                {{ $entry['reference'] }}
                                            @endif
                                        </td>
                                        <td>
                                            @if($entry['type'] === 'Vendor Bill')
                                                <span class="badge bg-soft-danger text-danger border">{{ __('purchase.vendor_bill') }}</span>
                                            @else
                                                <span class="badge bg-soft-success text-success border">{{ __('purchase.payment') }}</span>
                                            @endif
                                        </td>
                                        <td class="fs-12 text-muted">{{ $entry['description'] }}</td>
                                        <td class="text-end font-monospace fw-bold text-success">
                                            {!! $entry['debit'] > 0 ? format_currency($entry['debit']) : '—' !!}
                                        </td>
                                        <td class="text-end font-monospace fw-bold text-danger">
                                            {!! $entry['credit'] > 0 ? format_currency($entry['credit']) : '—' !!}
                                        </td>
                                        <td class="text-end font-monospace fw-bold text-primary">
                                            {!! format_currency($entry['running_balance']) !!}
                                        </td>
                                    </tr>
                                @empty
                                    @if(!($vendor->opening_balance > 0))
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">{{ __('purchase.no_ledger_transactions') }}</td>
                                        </tr>
                                    @endif
                                @endforelse
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

            </div>
        </x-ui.odoo-form-ui>
    </div>
@endsection

