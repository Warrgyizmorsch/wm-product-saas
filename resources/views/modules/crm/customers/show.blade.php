@extends('layouts.duralux')

@section('title', __('crm.customer_finance_360') . ' | ' . $customer->name)
@section('page-title', __('crm.customer_finance_360_view'))
@section('breadcrumb', __('crm.customer_profile'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('crm.customers.index') }}" class="btn btn-light border p-2 d-inline-flex align-items-center justify-content-center" title="{{ __('crm.back_to_customers') }}">
            <i class="feather-arrow-left fs-16"></i>
        </a>
        @if(Route::has('sales.orders.create'))
            <x-ui.button href="{{ route('sales.orders.create', ['customer_id' => $customer->id]) }}" variant="primary" icon="feather-plus">
                {{ __('crm.sales_orders') }}
            </x-ui.button>
        @endif
        @if(Route::has('sales.invoices.create'))
            <x-ui.button href="{{ route('sales.invoices.create', ['customer_id' => $customer->id]) }}" variant="success" icon="feather-file-plus">
                {{ __('crm.create_new_invoice') }}
            </x-ui.button>
        @endif
        @if($customer->crmAccount)
            <x-ui.button href="{{ route('crm.accounts.show', $customer->crmAccount->id) }}" variant="secondary" icon="feather-briefcase">
                {{ __('crm.view_account') }}
            </x-ui.button>
        @endif
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
        <x-ui.odoo-form-ui type="sheet">

            {{-- 1. Single Page Header: Customer Profile Info --}}
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 pb-3 border-bottom mb-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="avatar-text avatar-xl bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold fs-22 shadow-sm flex-shrink-0" style="width: 56px; height: 56px;">
                        {{ strtoupper(substr($customer->name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <h4 class="fw-bold text-dark mb-0 fs-19 me-1">{{ $customer->name }}</h4>
                            @if(strtolower($customer->status) === 'active')
                                <x-ui.status-badge status="active" :label="__('crm.active_customer')" dot="true" size="sm" />
                            @else
                                <x-ui.status-badge status="inactive" :label="__('crm.inactive_customer')" dot="true" size="sm" />
                            @endif

                            @if($customer->crmAccount)
                                <span class="badge bg-soft-info text-info border fs-11 px-2.5 py-1 ms-2" title="{{ __('crm.linked_crm_account') }}">
                                    <i class="feather-building me-1.5"></i>{{ __('crm.account_colon') }} <strong>{{ $customer->crmAccount->name }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="d-flex align-items-center gap-3 text-muted fs-12 mt-2 flex-wrap">
                            @if($customer->gstin)
                                <span><strong class="text-dark">{{ __('crm.gstin') }}:</strong> <span class="font-monospace text-primary fw-bold">{{ $customer->gstin }}</span></span>
                                <span class="text-black-50">•</span>
                            @endif
                            @if($customer->phone)
                                <span><i class="feather-phone me-1 text-primary"></i><strong class="text-dark">{{ $customer->phone }}</strong></span>
                                <span class="text-black-50">•</span>
                            @endif
                            @if($customer->email)
                                <span><i class="feather-mail me-1 text-primary"></i><a href="mailto:{{ $customer->email }}" class="text-primary fw-semibold">{{ $customer->email }}</a></span>
                                <span class="text-black-50">•</span>
                            @endif
                            @if($customer->crmAccount?->owner)
                                <span><i class="feather-user me-1 text-primary"></i>{{ __('crm.account_manager') }}: <strong class="text-dark">{{ $customer->crmAccount->owner->name }}</strong></span>
                            @endif
                        </div>

                        @if($customer->billing_address || $customer->shipping_address)
                            <div class="mt-2 pt-2 d-flex align-items-center gap-3 fs-11 text-muted flex-wrap">
                                @if($customer->billing_address)
                                    <span><i class="feather-map-pin me-1 text-danger"></i><strong>{{ __('crm.billing_colon') }}</strong> {{ Str::limit($customer->billing_address, 65) }}</span>
                                @endif
                                @if($customer->shipping_address)
                                    <span><i class="feather-truck me-1 text-info"></i><strong>{{ __('crm.shipping_colon') }}</strong> {{ Str::limit($customer->shipping_address, 65) }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="text-end">
                    <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">{{ __('crm.financial_health_status') }}</span>
                    @if(($customerCreditBalance ?? 0) > 0)
                        <span class="badge bg-soft-info text-info fs-12 px-3 py-1.5 fw-bold border"><i class="feather-arrow-down-left me-1"></i>Advance Credit (-{{ format_currency($customerCreditBalance) }})</span>
                    @elseif($outstandingBalance <= 0)
                        <span class="badge bg-soft-success text-success fs-12 px-3 py-1.5 fw-bold border"><i class="feather-check-circle me-1"></i>All Clear (Zero Balance)</span>
                    @elseif($overdueAmount > 0)
                        <span class="badge bg-soft-danger text-danger fs-12 px-3 py-1.5 fw-bold border"><i class="feather-alert-triangle me-1"></i>Overdue Balance</span>
                    @else
                        <span class="badge bg-soft-warning text-warning fs-12 px-3 py-1.5 fw-bold border"><i class="feather-clock me-1"></i>Payment Pending</span>
                    @endif
                </div>
            </div>

            {{-- 2. Single Page Integrated Financial Metrics Strip --}}
            <div class="bg-light p-3 rounded-3 border mb-4">
                <div class="row g-3 text-center text-md-start">
                    <div class="col-md-3 border-end">
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-file-text me-1 text-primary"></i>{{ __('crm.total_lifetime_billed') }}</span>
                        <h4 class="fw-bold text-primary mb-0 fs-18">{{ format_currency($totalBilled) }}</h4>
                        <span class="fs-11 text-muted">{{ $invoices->count() }} {{ __('crm.invoices_issued') }}</span>
                    </div>
                    <div class="col-md-3 border-end">
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-alert-circle me-1 text-warning"></i>{{ __('crm.outstanding_receivables') }}</span>
                        <h4 class="fw-bold text-warning mb-0 fs-18">{{ format_currency($outstandingBalance) }}</h4>
                        <span class="fs-11 text-muted">Total Uncollected</span>
                    </div>
                    <div class="col-md-3 border-end">
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-clock me-1 text-danger"></i>{{ __('crm.overdue_amount') }}</span>
                        <h4 class="fw-bold text-danger mb-0 fs-18">{{ format_currency($overdueAmount) }}</h4>
                        <span class="fs-11 text-muted">Due Date Passed</span>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-shield me-1 text-success"></i>{{ __('crm.credit_limit') }}</span>
                        <h4 class="fw-bold text-success mb-0 fs-18">{{ format_currency($creditLimit) }}</h4>
                        <span class="fs-11 text-muted">{{ __('crm.available') }}: {{ format_currency($availableCredit) }}</span>
                    </div>
                </div>
            </div>

            {{-- 3. Integrated Navigation Tabs Strip --}}
            <div class="border-bottom mb-3">
                <ul class="nav nav-tabs border-0 gap-1" id="customerTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link zoho-tab-link active fw-bold px-3 py-2" id="invoices-tab" data-bs-toggle="tab" href="#invoices-pane" role="tab">
                            <i class="feather-file-text me-1.5 text-primary"></i>{{ __('crm.invoices') }}
                            <span class="badge bg-soft-primary text-primary ms-1 px-1.5 rounded-pill">{{ $invoices->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link zoho-tab-link fw-bold px-3 py-2" id="payments-tab" data-bs-toggle="tab" href="#payments-pane" role="tab">
                            <i class="feather-dollar-sign me-1.5 text-success"></i>{{ __('crm.payments') }}
                            <span class="badge bg-soft-success text-success ms-1 px-1.5 rounded-pill">{{ $payments->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link zoho-tab-link fw-bold px-3 py-2" id="orders-tab" data-bs-toggle="tab" href="#orders-pane" role="tab">
                            <i class="feather-shopping-cart me-1.5 text-info"></i>{{ __('crm.sales_orders') }}
                            <span class="badge bg-soft-info text-info ms-1 px-1.5 rounded-pill">{{ $salesOrders->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link zoho-tab-link fw-bold px-3 py-2" id="ledger-tab" data-bs-toggle="tab" href="#ledger-pane" role="tab">
                            <i class="feather-book-open me-1.5 text-warning"></i>{{ __('crm.ledger_statement') }}
                        </a>
                    </li>
                </ul>
            </div>

            {{-- 4. Tab Content Panels --}}
            <div class="tab-content pt-2" id="customerTabsContent">
                
                {{-- ================= TAB 1: INVOICES ================= --}}
                <div class="tab-pane fade show active" id="invoices-pane" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0"><i class="feather-file-text me-2 text-primary"></i>{{ __('crm.invoices_linked_deals') }}</h6>
                            <span class="text-muted fs-11">{{ __('crm.list_gst_invoices_issued') }}</span>
                        </div>
                        @if(Route::has('sales.invoices.create'))
                            <x-ui.button href="{{ route('sales.invoices.create', ['customer_id' => $customer->id]) }}" variant="outline-primary" size="sm" icon="feather-plus">
                                {{ __('crm.create_new_invoice') }}
                            </x-ui.button>
                        @endif
                    </div>

                    @if($invoices->isNotEmpty())
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table" id="invoicesTable" class="mb-0">
                                <thead>
                                    <tr style="background-color: #e8ecf1 !important;">
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.invoice_no') }}</th>
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.linked_so_deal') }}</th>
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.invoice_date') }}</th>
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.due_date') }}</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end">{{ __('crm.total_amount') }} ({{ active_currency_symbol() }})</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end">{{ __('crm.amount_paid') }} ({{ active_currency_symbol() }})</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end">{{ __('crm.balance_due') }} ({{ active_currency_symbol() }})</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-center">{{ __('crm.status') }}</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('crm.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoices as $inv)
                                        @php
                                            $linkedSO = $inv->salesOrder;
                                            $linkedDeal = $linkedSO?->quotation?->deal;
                                            $soNo = $linkedSO ? ($linkedSO->sales_order_number ?: ($linkedSO->so_number ?: ('SO-' . str_pad($linkedSO->id, 4, '0', STR_PAD_LEFT)))) : null;
                                            $dealNo = $linkedDeal ? ($linkedDeal->deal_number ?: ('DL-' . str_pad($linkedDeal->id, 4, '0', STR_PAD_LEFT))) : null;
                                        @endphp
                                        <tr>
                                            <td class="font-monospace fw-bold text-primary">
                                                @if(Route::has('sales.invoices.show'))
                                                    <a href="{{ route('sales.invoices.show', $inv->id) }}" class="text-primary hover-primary fw-bold">{{ $inv->invoice_number }}</a>
                                                @else
                                                    {{ $inv->invoice_number }}
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-2 py-1">
                                                    @if($dealNo)
                                                        <div>
                                                            <span class="fs-10 text-muted uppercase me-1">Deal:</span>
                                                            @if(Route::has('crm.deals.show'))
                                                                <a href="{{ route('crm.deals.show', $linkedDeal->id) }}" class="badge bg-soft-info text-info text-decoration-none fw-bold px-2.5 py-1.5 d-inline-flex align-items-center" title="Title: {{ $linkedDeal->title }}">
                                                                    <i class="feather-git-branch me-1"></i>{{ $dealNo }}
                                                                </a>
                                                            @else
                                                                <span class="badge bg-soft-info text-info fw-bold px-2.5 py-1.5 d-inline-flex align-items-center" title="{{ $linkedDeal->title }}"><i class="feather-git-branch me-1"></i>{{ $dealNo }}</span>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    @if($soNo)
                                                        <div>
                                                            <span class="fs-10 text-muted uppercase me-1">SO:</span>
                                                            @if(Route::has('sales.orders.show'))
                                                                <a href="{{ route('sales.orders.show', $linkedSO->id) }}" class="badge bg-soft-primary text-primary font-monospace text-decoration-none px-2.5 py-1.5 d-inline-flex align-items-center">
                                                                    <i class="feather-shopping-cart me-1"></i>{{ $soNo }}
                                                                </a>
                                                            @else
                                                                <span class="badge bg-soft-primary text-primary font-monospace px-2.5 py-1.5 d-inline-flex align-items-center">{{ $soNo }}</span>
                                                            @endif
                                                        </div>
                                                    @elseif(!$dealNo)
                                                        <span class="text-muted fs-12">— {{ __('crm.direct_invoice') }} —</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>{{ $inv->invoice_date ? \Carbon\Carbon::parse($inv->invoice_date)->format('d/m/Y') : '—' }}</td>
                                            <td>{{ $inv->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('d/m/Y') : '—' }}</td>
                                            <td class="text-end fw-bold text-dark">{{ format_currency($inv->total_amount) }}</td>
                                            <td class="text-end text-success fw-bold">{{ format_currency($inv->amount_paid ?: 0) }}</td>
                                            <td class="text-end text-danger fw-bold">{{ format_currency($inv->balance_due ?: ($inv->total_amount - ($inv->amount_paid ?: 0))) }}</td>
                                            <td class="text-center">
                                                @if(strtolower($inv->status) === 'paid' || $inv->amount_paid >= $inv->total_amount)
                                                    <x-ui.status-badge status="completed" label="Paid" size="sm" />
                                                @elseif(strtolower($inv->status) === 'unpaid')
                                                    <x-ui.status-badge status="blocked" label="Unpaid" size="sm" />
                                                @else
                                                    <x-ui.status-badge status="on_hold" :label="ucfirst($inv->status ?: 'Pending')" size="sm" />
                                                @endif
                                            </td>
                                            <td class="text-end pe-3">
                                                @if(Route::has('sales.invoices.show'))
                                                    <x-ui.action-dropdown :viewUrl="route('sales.invoices.show', $inv->id)" />
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                            <div id="invoicesTable-info" class="fs-12 text-muted"></div>
                            <div id="invoicesTable-pagination"></div>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted border rounded">
                            <i class="feather-file-text display-6 text-muted opacity-50 mb-2 d-block"></i>
                            <p class="mb-0 fs-13">{{ __('crm.no_invoices_issued') }}</p>
                        </div>
                    @endif
                </div>

                {{-- ================= TAB 2: PAYMENTS & RECEIPTS ================= --}}
                <div class="tab-pane fade" id="payments-pane" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0"><i class="feather-dollar-sign me-2 text-success"></i>{{ __('crm.customer_payments_receipts_traceability') }}</h6>
                            <span class="text-muted fs-11">{{ __('crm.list_payment_receipts') }}</span>
                        </div>
                    </div>
                    @if($payments->isNotEmpty())
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table" id="paymentsTable" class="mb-0">
                                <thead>
                                    <tr style="background-color: #e8ecf1 !important;">
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.receipt_no') }}</th>
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.date') }}</th>
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.linked_invoice_deal') }}</th>
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.payment_mode_ref') }}</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end">{{ __('crm.amount_received') }} ({{ active_currency_symbol() }})</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-center">{{ __('crm.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payments as $pay)
                                        @php
                                            $firstAlloc = $pay->allocations->first();
                                            $payInv = $firstAlloc?->invoice;
                                            $paySO = $firstAlloc?->salesOrder ?: $payInv?->salesOrder;
                                            $payDeal = $paySO?->quotation?->deal;
                                            $dealNo = $payDeal ? ($payDeal->deal_number ?: ('DL-' . str_pad($payDeal->id, 4, '0', STR_PAD_LEFT))) : null;
                                        @endphp
                                        <tr>
                                            <td class="font-monospace fw-bold text-success">{{ $pay->payment_number ?: ('RCP-' . str_pad($pay->id, 4, '0', STR_PAD_LEFT)) }}</td>
                                            <td>{{ $pay->payment_date ? \Carbon\Carbon::parse($pay->payment_date)->format('d/m/Y') : '—' }}</td>
                                            <td>
                                                <div class="d-flex flex-column gap-2 py-1">
                                                    @if($dealNo)
                                                        <div>
                                                            <span class="fs-10 text-muted uppercase me-1">{{ __('crm.deal_colon') }}</span>
                                                            @if(Route::has('crm.deals.show'))
                                                                <a href="{{ route('crm.deals.show', $payDeal->id) }}" class="badge bg-soft-info text-info text-decoration-none fw-bold px-2.5 py-1.5 d-inline-flex align-items-center" title="Title: {{ $payDeal->title }}">
                                                                    <i class="feather-git-branch me-1"></i>{{ $dealNo }}
                                                                </a>
                                                            @else
                                                                <span class="badge bg-soft-info text-info fw-bold px-2.5 py-1.5 d-inline-flex align-items-center" title="{{ $payDeal->title }}"><i class="feather-git-branch me-1"></i>{{ $dealNo }}</span>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    @if($payInv)
                                                        <div>
                                                            <span class="fs-10 text-muted uppercase me-1">{{ __('crm.invoice_colon') }}</span>
                                                            @if(Route::has('sales.invoices.show'))
                                                                <a href="{{ route('sales.invoices.show', $payInv->id) }}" class="badge bg-soft-primary text-primary font-monospace text-decoration-none px-2.5 py-1.5 d-inline-flex align-items-center">
                                                                    <i class="feather-file-text me-1"></i>{{ $payInv->invoice_number }}
                                                                </a>
                                                            @else
                                                                <span class="badge bg-soft-primary text-primary font-monospace px-2.5 py-1.5 d-inline-flex align-items-center">{{ $payInv->invoice_number }}</span>
                                                            @endif
                                                        </div>
                                                    @elseif(!$dealNo)
                                                        <span class="text-muted fs-12">— {{ __('crm.advance_general_receipt') }} —</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border me-1">{{ ucfirst($pay->payment_method ?: 'Bank/Cash') }}</span>
                                                @if($pay->reference_no)
                                                    <span class="font-monospace text-muted fs-11">{{ __('crm.ref_colon') }} {{ $pay->reference_no }}</span>
                                                @endif
                                            </td>
                                            <td class="text-end fw-bold text-success fs-14">{{ format_currency($pay->amount) }}</td>
                                            <td class="text-center">
                                                <x-ui.status-badge status="completed" :label="ucfirst($pay->status ?: 'Completed')" size="sm" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                            <div id="paymentsTable-info" class="fs-12 text-muted"></div>
                            <div id="paymentsTable-pagination"></div>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted border rounded">
                            <i class="feather-dollar-sign display-6 text-muted opacity-50 mb-2 d-block"></i>
                            <p class="mb-0 fs-13">{{ __('crm.no_payment_receipts') }}</p>
                        </div>
                    @endif
                </div>

                {{-- ================= TAB 3: SALES ORDERS ================= --}}
                <div class="tab-pane fade" id="orders-pane" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0"><i class="feather-shopping-cart me-2 text-info"></i>{{ __('crm.sales_orders_linked_deals') }}</h6>
                            <span class="text-muted fs-11">{{ __('crm.list_sales_orders') }}</span>
                        </div>
                        @if(Route::has('sales.orders.create'))
                            <x-ui.button href="{{ route('sales.orders.create', ['customer_id' => $customer->id]) }}" variant="outline-primary" size="sm" icon="feather-plus">
                                {{ __('crm.create_sales_order') }}
                            </x-ui.button>
                        @endif
                    </div>
                    @if($salesOrders->isNotEmpty())
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table" id="ordersTable" class="mb-0">
                                <thead>
                                    <tr style="background-color: #e8ecf1 !important;">
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.order_no') }}</th>
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.linked_opportunity_deal') }}</th>
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.order_date') }}</th>
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.items') }}</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end">{{ __('crm.total_amount') }} ({{ active_currency_symbol() }})</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-center">{{ __('crm.status') }}</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('crm.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($salesOrders as $so)
                                        @php
                                            $orderNo = $so->sales_order_number ?: ($so->so_number ?: ($so->order_number ?: ('SO-' . str_pad($so->id, 4, '0', STR_PAD_LEFT))));
                                            $linkedDeal = $so->quotation?->deal;
                                            $dealNo = $linkedDeal ? ($linkedDeal->deal_number ?: ('DL-' . str_pad($linkedDeal->id, 4, '0', STR_PAD_LEFT))) : null;
                                        @endphp
                                        <tr>
                                            <td class="font-monospace fw-bold text-primary">
                                                @if(Route::has('sales.orders.show'))
                                                    <a href="{{ route('sales.orders.show', $so->id) }}" class="text-primary hover-primary fw-bold">{{ $orderNo }}</a>
                                                @else
                                                    {{ $orderNo }}
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-2 py-1">
                                                    @if($dealNo)
                                                        <div>
                                                            <span class="fs-10 text-muted uppercase me-1">{{ __('crm.deal_colon') }}</span>
                                                            @if(Route::has('crm.deals.show'))
                                                                <a href="{{ route('crm.deals.show', $linkedDeal->id) }}" class="badge bg-soft-info text-info text-decoration-none fw-bold px-2.5 py-1.5 d-inline-flex align-items-center" title="Title: {{ $linkedDeal->title }}">
                                                                    <i class="feather-git-branch me-1"></i>{{ $dealNo }}
                                                                </a>
                                                            @else
                                                                <span class="badge bg-soft-info text-info fw-bold px-2.5 py-1.5 d-inline-flex align-items-center" title="{{ $linkedDeal->title }}"><i class="feather-git-branch me-1"></i>{{ $dealNo }}</span>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    @if($so->quotation)
                                                        <div>
                                                            <span class="fs-10 text-muted uppercase me-1">{{ __('crm.quote_colon') }}</span>
                                                            @if(Route::has('crm.quotations.show'))
                                                                <a href="{{ route('crm.quotations.show', $so->quotation->id) }}" class="badge bg-soft-secondary text-secondary font-monospace text-decoration-none px-2.5 py-1.5 d-inline-flex align-items-center">
                                                                    <i class="feather-file-text me-1"></i>{{ $so->quotation->quotation_number }}
                                                                </a>
                                                            @else
                                                                <span class="badge bg-soft-secondary text-secondary font-monospace px-2.5 py-1.5 d-inline-flex align-items-center">{{ $so->quotation->quotation_number }}</span>
                                                            @endif
                                                        </div>
                                                    @elseif(!$dealNo)
                                                        <span class="text-muted fs-12">— {{ __('crm.direct_order') }} —</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>{{ $so->order_date ? \Carbon\Carbon::parse($so->order_date)->format('d/m/Y') : '—' }}</td>
                                            <td>
                                                <span class="badge bg-light text-dark border">{{ $so->items->count() ?: 1 }} {{ __('crm.items') }}</span>
                                            </td>
                                            <td class="text-end fw-bold text-dark">{{ format_currency($so->total_amount ?: ($so->grand_total ?? 0)) }}</td>
                                            <td class="text-center">
                                                <x-ui.status-badge status="confirmed" :label="ucfirst($so->status ?: 'Confirmed')" size="sm" />
                                            </td>
                                            <td class="text-end pe-3">
                                                @if(Route::has('sales.orders.show'))
                                                    <x-ui.action-dropdown :viewUrl="route('sales.orders.show', $so->id)" />
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                            <div id="ordersTable-info" class="fs-12 text-muted"></div>
                            <div id="ordersTable-pagination"></div>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted border rounded">
                            <i class="feather-shopping-cart display-6 text-muted opacity-50 mb-2 d-block"></i>
                            <p class="mb-0 fs-13">{{ __('crm.no_sales_orders') }}</p>
                        </div>
                    @endif
                </div>

                {{-- ================= TAB 4: LEDGER STATEMENT ================= --}}
                <div class="tab-pane fade" id="ledger-pane" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0"><i class="feather-book-open me-2 text-warning"></i>{{ __('crm.customer_account_ledger_statement') }}</h6>
                            <span class="text-muted fs-11">{{ __('crm.realtime_debit_credit_balance') }}</span>
                        </div>
                        <x-ui.button type="button" variant="light" size="sm" class="border" icon="feather-printer" onclick="window.print()">
                            {{ __('crm.print_statement') }}
                        </x-ui.button>
                    </div>

                    @php
                        $ledgerRows = collect();
                        foreach($invoices as $inv) {
                            $linkedSO = $inv->salesOrder;
                            $linkedDeal = $linkedSO?->quotation?->deal;
                            $soNo = $linkedSO ? ($linkedSO->sales_order_number ?: ($linkedSO->so_number ?: ('SO-' . str_pad($linkedSO->id, 4, '0', STR_PAD_LEFT)))) : null;
                            $dealNo = $linkedDeal ? ($linkedDeal->deal_number ?: ('DL-' . str_pad($linkedDeal->id, 4, '0', STR_PAD_LEFT))) : null;
                            $dealTitle = $linkedDeal ? ($linkedDeal->title ?: $linkedDeal->name) : null;
                            $dealId = $linkedDeal?->id;

                            $ledgerRows->push([
                                'date' => $inv->invoice_date ?: $inv->created_at,
                                'type' => 'Invoice',
                                'reference' => $inv->invoice_number,
                                'description' => 'Sales Invoice #' . $inv->invoice_number,
                                'deal_no' => $dealNo,
                                'deal_title' => $dealTitle,
                                'deal_id' => $dealId,
                                'so_no' => $soNo,
                                'debit' => floatval($inv->total_amount),
                                'credit' => 0.00,
                            ]);
                        }
                        foreach($salesReturns ?? [] as $ret) {
                            $linkedSO = $ret->salesOrder;
                            $linkedDeal = $linkedSO?->quotation?->deal;
                            $soNo = $linkedSO ? ($linkedSO->sales_order_number ?: ($linkedSO->so_number ?: ('SO-' . str_pad($linkedSO->id, 4, '0', STR_PAD_LEFT)))) : null;
                            $dealNo = $linkedDeal ? ($linkedDeal->deal_number ?: ('DL-' . str_pad($linkedDeal->id, 4, '0', STR_PAD_LEFT))) : null;
                            $dealTitle = $linkedDeal ? ($linkedDeal->title ?: $linkedDeal->name) : null;
                            $dealId = $linkedDeal?->id;

                            $retTaxable = (float)$ret->items->sum(fn($i) => floatval($i->quantity) * floatval($i->unit_price));
                            $taxAmt = round($retTaxable * 0.18, 2);
                            $retAmount = floatval($ret->total_refund_amount ?: ($ret->total_amount > $retTaxable ? $ret->total_amount : ($retTaxable + $taxAmt)));
                            if ($retAmount > 0) {
                                $ledgerRows->push([
                                    'date' => $ret->return_date ?: $ret->created_at,
                                    'type' => 'Credit Note',
                                    'reference' => $ret->return_number ?: ('RET-' . str_pad($ret->id, 4, '0', STR_PAD_LEFT)),
                                    'description' => 'Sales Return / Credit Note #' . ($ret->return_number ?: ('RET-' . str_pad($ret->id, 4, '0', STR_PAD_LEFT))),
                                    'deal_no' => $dealNo,
                                    'deal_title' => $dealTitle,
                                    'deal_id' => $dealId,
                                    'so_no' => $soNo,
                                    'debit' => 0.00,
                                    'credit' => $retAmount,
                                ]);
                            }
                        }
                        foreach($payments as $pay) {
                            $firstAlloc = $pay->allocations->first();
                            $payInv = $firstAlloc?->invoice;
                            $paySO = $firstAlloc?->salesOrder ?: $payInv?->salesOrder;
                            $payDeal = $paySO?->quotation?->deal;
                            $dealNo = $payDeal ? ($payDeal->deal_number ?: ('DL-' . str_pad($payDeal->id, 4, '0', STR_PAD_LEFT))) : null;
                            $dealTitle = $payDeal ? ($payDeal->title ?: $payDeal->name) : null;
                            $dealId = $payDeal?->id;
                            $soNo = $paySO ? ($paySO->sales_order_number ?: ($paySO->so_number ?: ('SO-' . str_pad($paySO->id, 4, '0', STR_PAD_LEFT)))) : null;

                            $ledgerRows->push([
                                'date' => $pay->payment_date ?: $pay->created_at,
                                'type' => 'Payment Receipt',
                                'reference' => $pay->payment_number ?: ('RCP-' . str_pad($pay->id, 4, '0', STR_PAD_LEFT)),
                                'description' => 'Payment Received (' . ($pay->payment_method ?: 'Bank') . ')' . ($payInv ? ' against INV #' . $payInv->invoice_number : ''),
                                'deal_no' => $dealNo,
                                'deal_title' => $dealTitle,
                                'deal_id' => $dealId,
                                'so_no' => $soNo,
                                'debit' => 0.00,
                                'credit' => floatval($pay->amount),
                            ]);
                        }
                        $sortedLedger = $ledgerRows->sortBy(function ($row) {
                            $ts = \Carbon\Carbon::parse($row['date'])->timestamp;
                            $typePriority = match($row['type']) {
                                'Invoice' => 0,
                                'Payment Receipt' => 1,
                                'Credit Note' => 2,
                                'Payment Refund' => 3,
                                default => 4,
                            };
                            return sprintf('%012d_%d', $ts, $typePriority);
                        })->values();
                    @endphp

                    @if($sortedLedger->isNotEmpty())
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table" id="ledgerTable" class="mb-0">
                                <thead>
                                    <tr style="background-color: #e8ecf1 !important;">
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.date') }}</th>
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.type') }}</th>
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.reference_no') }}</th>
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.particulars_description') }}</th>
                                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.linked_deal_so') }}</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end text-danger">{{ __('crm.debit_billed') }} ({{ active_currency_symbol() }})</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end text-success">{{ __('crm.credit_paid') }} ({{ active_currency_symbol() }})</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end text-primary">{{ __('crm.running_balance') }} ({{ active_currency_symbol() }})</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $running = 0; @endphp
                                    @foreach($sortedLedger as $row)
                                        @php
                                            $running += ($row['debit'] - $row['credit']);
                                        @endphp
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                                            <td><span class="badge {{ $row['type'] === 'Invoice' ? 'bg-soft-danger text-danger' : ($row['type'] === 'Credit Note' ? 'bg-soft-info text-info' : 'bg-soft-success text-success') }} fs-11">{{ $row['type'] }}</span></td>
                                            <td class="font-monospace text-dark fw-bold">{{ $row['reference'] }}</td>
                                            <td>{{ $row['description'] }}</td>
                                            <td>
                                                @if($row['deal_no'] || $row['so_no'])
                                                    <div class="d-flex flex-column gap-2 py-1">
                                                        @if($row['deal_no'])
                                                            <div>
                                                                @if($row['deal_id'] && Route::has('crm.deals.show'))
                                                                    <a href="{{ route('crm.deals.show', $row['deal_id']) }}" class="badge bg-soft-info text-info text-decoration-none fw-bold px-2.5 py-1.5 d-inline-flex align-items-center" title="Title: {{ $row['deal_title'] }}">
                                                                        <i class="feather-git-branch me-1"></i>{{ $row['deal_no'] }}
                                                                    </a>
                                                                @else
                                                                    <span class="badge bg-soft-info text-info fw-bold px-2.5 py-1.5 d-inline-flex align-items-center" title="{{ $row['deal_title'] }}"><i class="feather-git-branch me-1"></i>{{ $row['deal_no'] }}</span>
                                                                @endif
                                                            </div>
                                                        @endif

                                                        @if($row['so_no'])
                                                            <div>
                                                                <span class="badge bg-soft-primary text-primary font-monospace px-2.5 py-1.5 d-inline-flex align-items-center"><i class="feather-shopping-cart me-1"></i>{{ $row['so_no'] }}</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end text-danger font-monospace">{{ $row['debit'] > 0 ? format_currency($row['debit']) : '—' }}</td>
                                            <td class="text-end text-success font-monospace">{{ $row['credit'] > 0 ? format_currency($row['credit']) : '—' }}</td>
                                            <td class="text-end font-monospace fw-bold {{ $running > 0 ? 'text-danger' : 'text-success' }}">{{ format_currency($running) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light font-monospace fw-bold">
                                    <tr>
                                        <td colspan="5" class="text-end text-dark">{{ __('crm.closing_balance') }}:</td>
                                        <td class="text-end text-danger">{{ format_currency($sortedLedger->sum('debit')) }}</td>
                                        <td class="text-end text-success">{{ format_currency($sortedLedger->sum('credit')) }}</td>
                                        <td class="text-end text-primary fs-14">{{ format_currency($running) }}</td>
                                    </tr>
                                </tfoot>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                            <div id="ledgerTable-info" class="fs-12 text-muted"></div>
                            <div id="ledgerTable-pagination"></div>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted border rounded">
                            <i class="feather-book-open display-6 text-muted opacity-50 mb-2 d-block"></i>
                            <p class="mb-0 fs-13">{{ __('crm.no_ledger_transactions') }}</p>
                        </div>
                    @endif
                </div>

            </div>
        </x-ui.odoo-form-ui>
    </div>
@endsection

@push('scripts')
<script>
function initTablePagination(tableId, infoId, paginationId, itemsPerPage = 10) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.children).filter(tr => tr.tagName === 'TR' && !tr.querySelector('td[colspan]'));
    const totalItems = rows.length;
    if (totalItems === 0) return;

    const totalPages = Math.ceil(totalItems / itemsPerPage);
    let currentPage = 1;

    function renderPage(page) {
        currentPage = page;
        const start = (page - 1) * itemsPerPage;
        const end = start + itemsPerPage;

        rows.forEach((row, index) => {
            row.style.display = (index >= start && index < end) ? '' : 'none';
        });

        const infoEl = document.getElementById(infoId);
        if (infoEl) {
            infoEl.textContent = `Showing ${start + 1} to ${Math.min(end, totalItems)} of ${totalItems} entries`;
        }

        const pagEl = document.getElementById(paginationId);
        if (pagEl) {
            if (totalPages <= 1) {
                pagEl.innerHTML = '';
                return;
            }
            let html = '<ul class="pagination pagination-sm mb-0">';
            html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>
            </li>`;
            for (let i = 1; i <= totalPages; i++) {
                html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>`;
            }
            html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>
            </li>`;
            html += '</ul>';
            pagEl.innerHTML = html;

            pagEl.querySelectorAll('a.page-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const p = parseInt(this.getAttribute('data-page'));
                    if (p >= 1 && p <= totalPages && p !== currentPage) {
                        renderPage(p);
                    }
                });
            });
        }
    }

    renderPage(1);
}

document.addEventListener('DOMContentLoaded', function () {
    initTablePagination('invoicesTable', 'invoicesTable-info', 'invoicesTable-pagination', 10);
    initTablePagination('paymentsTable', 'paymentsTable-info', 'paymentsTable-pagination', 10);
    initTablePagination('ordersTable', 'ordersTable-info', 'ordersTable-pagination', 10);
    initTablePagination('ledgerTable', 'ledgerTable-info', 'ledgerTable-pagination', 10);
});
</script>
@endpush
