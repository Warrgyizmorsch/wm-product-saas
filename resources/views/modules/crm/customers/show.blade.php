@extends('layouts.duralux')

@section('title', 'Customer Finance 360° | ' . $customer->name)
@section('page-title', 'Customer Finance 360° View')
@section('breadcrumb', 'Customer Profile')

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('crm.customers.index') }}" class="btn btn-light border p-2 d-inline-flex align-items-center justify-content-center" title="Back to Customers">
            <i class="feather-arrow-left fs-16"></i>
        </a>
        @if(Route::has('sales.orders.create'))
            <x-ui.button href="{{ route('sales.orders.create', ['customer_id' => $customer->id]) }}" variant="primary" icon="feather-plus">
                Sales Order
            </x-ui.button>
        @endif
        @if(Route::has('sales.invoices.create'))
            <x-ui.button href="{{ route('sales.invoices.create', ['customer_id' => $customer->id]) }}" variant="success" icon="feather-file-plus">
                Create Invoice
            </x-ui.button>
        @endif
        @if($customer->crmAccount)
            <x-ui.button href="{{ route('crm.accounts.show', $customer->crmAccount->id) }}" variant="secondary" icon="feather-briefcase">
                View Account
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
                                <x-ui.status-badge status="active" label="Active Customer" dot="true" size="sm" />
                            @else
                                <x-ui.status-badge status="inactive" label="Inactive Customer" dot="true" size="sm" />
                            @endif

                            @if($customer->crmAccount)
                                <span class="badge bg-soft-info text-info border fs-11 px-2.5 py-1 ms-2" title="Linked CRM Company Account">
                                    <i class="feather-building me-1.5"></i>Account: <strong>{{ $customer->crmAccount->name }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="d-flex align-items-center gap-3 text-muted fs-12 mt-2 flex-wrap">
                            @if($customer->gstin)
                                <span><strong class="text-dark">GSTIN:</strong> <span class="font-monospace text-primary fw-bold">{{ $customer->gstin }}</span></span>
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
                                <span><i class="feather-user me-1 text-primary"></i>Account Manager: <strong class="text-dark">{{ $customer->crmAccount->owner->name }}</strong></span>
                            @endif
                        </div>

                        @if($customer->billing_address || $customer->shipping_address)
                            <div class="mt-2 pt-2 d-flex align-items-center gap-3 fs-11 text-muted flex-wrap">
                                @if($customer->billing_address)
                                    <span><i class="feather-map-pin me-1 text-danger"></i><strong>Billing:</strong> {{ Str::limit($customer->billing_address, 65) }}</span>
                                @endif
                                @if($customer->shipping_address)
                                    <span><i class="feather-truck me-1 text-info"></i><strong>Shipping:</strong> {{ Str::limit($customer->shipping_address, 65) }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="text-end">
                    <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">Financial Health Status</span>
                    @if($outstandingBalance <= 0)
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
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-file-text me-1 text-primary"></i>Total Lifetime Billed</span>
                        <h4 class="fw-bold text-primary mb-0 fs-18">₹{{ number_format($totalBilled, 2) }}</h4>
                        <span class="fs-11 text-muted">{{ $invoices->count() }} Invoices Issued</span>
                    </div>
                    <div class="col-md-3 border-end">
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-alert-circle me-1 text-warning"></i>Outstanding Receivables</span>
                        <h4 class="fw-bold text-warning mb-0 fs-18">₹{{ number_format($outstandingBalance, 2) }}</h4>
                        <span class="fs-11 text-muted">Total Uncollected</span>
                    </div>
                    <div class="col-md-3 border-end">
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-clock me-1 text-danger"></i>Overdue Amount</span>
                        <h4 class="fw-bold text-danger mb-0 fs-18">₹{{ number_format($overdueAmount, 2) }}</h4>
                        <span class="fs-11 text-muted">Due Date Passed</span>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-shield me-1 text-success"></i>Credit Limit / Available</span>
                        <h4 class="fw-bold text-success mb-0 fs-18">₹{{ number_format($creditLimit, 2) }}</h4>
                        <span class="fs-11 text-muted">Available: ₹{{ number_format($availableCredit, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- 3. Integrated Navigation Tabs Strip --}}
            <div class="border-bottom mb-3">
                <ul class="nav nav-tabs border-0 gap-1" id="customerTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link zoho-tab-link active fw-bold px-3 py-2" id="invoices-tab" data-bs-toggle="tab" href="#invoices-pane" role="tab">
                            <i class="feather-file-text me-1.5 text-primary"></i>GST Invoices
                            <span class="badge bg-soft-primary text-primary ms-1 px-1.5 rounded-pill">{{ $invoices->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link zoho-tab-link fw-bold px-3 py-2" id="payments-tab" data-bs-toggle="tab" href="#payments-pane" role="tab">
                            <i class="feather-dollar-sign me-1.5 text-success"></i>Payments & Receipts
                            <span class="badge bg-soft-success text-success ms-1 px-1.5 rounded-pill">{{ $payments->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link zoho-tab-link fw-bold px-3 py-2" id="orders-tab" data-bs-toggle="tab" href="#orders-pane" role="tab">
                            <i class="feather-shopping-cart me-1.5 text-info"></i>Sales Orders
                            <span class="badge bg-soft-info text-info ms-1 px-1.5 rounded-pill">{{ $salesOrders->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link zoho-tab-link fw-bold px-3 py-2" id="ledger-tab" data-bs-toggle="tab" href="#ledger-pane" role="tab">
                            <i class="feather-book-open me-1.5 text-warning"></i>Customer Ledger Statement
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
                            <h6 class="fw-bold text-dark mb-0"><i class="feather-file-text me-2 text-primary"></i>Sales Invoices & Linked CRM Deals</h6>
                            <span class="text-muted fs-11">List of all GST Sales Invoices issued to this customer</span>
                        </div>
                        @if(Route::has('sales.invoices.create'))
                            <x-ui.button href="{{ route('sales.invoices.create', ['customer_id' => $customer->id]) }}" variant="outline-primary" size="sm" icon="feather-plus">
                                Create New Invoice
                            </x-ui.button>
                        @endif
                    </div>

                    @if($invoices->isNotEmpty())
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table" id="invoicesTable" class="mb-0">
                                <thead>
                                    <tr style="background-color: #e8ecf1 !important;">
                                        <th style="background-color: #e8ecf1 !important;">Invoice #</th>
                                        <th style="background-color: #e8ecf1 !important;">Linked Sales Order & Deal</th>
                                        <th style="background-color: #e8ecf1 !important;">Invoice Date</th>
                                        <th style="background-color: #e8ecf1 !important;">Due Date</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end">Total Amount (₹)</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end">Amount Paid (₹)</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end">Balance Due (₹)</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-center">Status</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end pe-3">Action</th>
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
                                                        <span class="text-muted fs-12">— Direct Invoice —</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>{{ $inv->invoice_date ? \Carbon\Carbon::parse($inv->invoice_date)->format('d/m/Y') : '—' }}</td>
                                            <td>{{ $inv->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('d/m/Y') : '—' }}</td>
                                            <td class="text-end fw-bold text-dark">₹{{ number_format($inv->total_amount, 2) }}</td>
                                            <td class="text-end text-success fw-bold">₹{{ number_format($inv->amount_paid ?: 0, 2) }}</td>
                                            <td class="text-end text-danger fw-bold">₹{{ number_format($inv->balance_due ?: ($inv->total_amount - ($inv->amount_paid ?: 0)), 2) }}</td>
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
                    @else
                        <div class="text-center py-5 text-muted border rounded">
                            <i class="feather-file-text display-6 text-muted opacity-50 mb-2 d-block"></i>
                            <p class="mb-0 fs-13">No GST Invoices issued for this customer yet.</p>
                        </div>
                    @endif
                </div>

                {{-- ================= TAB 2: PAYMENTS & RECEIPTS ================= --}}
                <div class="tab-pane fade" id="payments-pane" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0"><i class="feather-dollar-sign me-2 text-success"></i>Customer Payments & Receipts Traceability</h6>
                            <span class="text-muted fs-11">List of payment receipts collected from this customer</span>
                        </div>
                    </div>
                    @if($payments->isNotEmpty())
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table" id="paymentsTable" class="mb-0">
                                <thead>
                                    <tr style="background-color: #e8ecf1 !important;">
                                        <th style="background-color: #e8ecf1 !important;">Receipt #</th>
                                        <th style="background-color: #e8ecf1 !important;">Date</th>
                                        <th style="background-color: #e8ecf1 !important;">Linked Invoice & Deal</th>
                                        <th style="background-color: #e8ecf1 !important;">Payment Mode & Ref #</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end">Amount Received (₹)</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-center">Status</th>
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
                                                            <span class="fs-10 text-muted uppercase me-1">Deal:</span>
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
                                                            <span class="fs-10 text-muted uppercase me-1">Invoice:</span>
                                                            @if(Route::has('sales.invoices.show'))
                                                                <a href="{{ route('sales.invoices.show', $payInv->id) }}" class="badge bg-soft-primary text-primary font-monospace text-decoration-none px-2.5 py-1.5 d-inline-flex align-items-center">
                                                                    <i class="feather-file-text me-1"></i>{{ $payInv->invoice_number }}
                                                                </a>
                                                            @else
                                                                <span class="badge bg-soft-primary text-primary font-monospace px-2.5 py-1.5 d-inline-flex align-items-center">{{ $payInv->invoice_number }}</span>
                                                            @endif
                                                        </div>
                                                    @elseif(!$dealNo)
                                                        <span class="text-muted fs-12">— Advance / General Receipt —</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border me-1">{{ ucfirst($pay->payment_method ?: 'Bank/Cash') }}</span>
                                                @if($pay->reference_no)
                                                    <span class="font-monospace text-muted fs-11">Ref: {{ $pay->reference_no }}</span>
                                                @endif
                                            </td>
                                            <td class="text-end fw-bold text-success fs-14">₹{{ number_format($pay->amount, 2) }}</td>
                                            <td class="text-center">
                                                <x-ui.status-badge status="completed" :label="ucfirst($pay->status ?: 'Completed')" size="sm" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted border rounded">
                            <i class="feather-dollar-sign display-6 text-muted opacity-50 mb-2 d-block"></i>
                            <p class="mb-0 fs-13">No payment receipts recorded for this customer yet.</p>
                        </div>
                    @endif
                </div>

                {{-- ================= TAB 3: SALES ORDERS ================= --}}
                <div class="tab-pane fade" id="orders-pane" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0"><i class="feather-shopping-cart me-2 text-info"></i>Sales Orders & Linked Deals Traceability</h6>
                            <span class="text-muted fs-11">List of Sales Orders generated for this customer</span>
                        </div>
                        @if(Route::has('sales.orders.create'))
                            <x-ui.button href="{{ route('sales.orders.create', ['customer_id' => $customer->id]) }}" variant="outline-primary" size="sm" icon="feather-plus">
                                Create Sales Order
                            </x-ui.button>
                        @endif
                    </div>
                    @if($salesOrders->isNotEmpty())
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table" id="ordersTable" class="mb-0">
                                <thead>
                                    <tr style="background-color: #e8ecf1 !important;">
                                        <th style="background-color: #e8ecf1 !important;">Order #</th>
                                        <th style="background-color: #e8ecf1 !important;">Linked Opportunity / Deal</th>
                                        <th style="background-color: #e8ecf1 !important;">Order Date</th>
                                        <th style="background-color: #e8ecf1 !important;">Items</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end">Total Amount (₹)</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-center">Status</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end pe-3">Action</th>
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

                                                    @if($so->quotation)
                                                        <div>
                                                            <span class="fs-10 text-muted uppercase me-1">Quote:</span>
                                                            @if(Route::has('crm.quotations.show'))
                                                                <a href="{{ route('crm.quotations.show', $so->quotation->id) }}" class="badge bg-soft-secondary text-secondary font-monospace text-decoration-none px-2.5 py-1.5 d-inline-flex align-items-center">
                                                                    <i class="feather-file-text me-1"></i>{{ $so->quotation->quotation_number }}
                                                                </a>
                                                            @else
                                                                <span class="badge bg-soft-secondary text-secondary font-monospace px-2.5 py-1.5 d-inline-flex align-items-center">{{ $so->quotation->quotation_number }}</span>
                                                            @endif
                                                        </div>
                                                    @elseif(!$dealNo)
                                                        <span class="text-muted fs-12">— Direct Order —</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>{{ $so->order_date ? \Carbon\Carbon::parse($so->order_date)->format('d/m/Y') : '—' }}</td>
                                            <td>
                                                <span class="badge bg-light text-dark border">{{ $so->items->count() ?: 1 }} Item(s)</span>
                                            </td>
                                            <td class="text-end fw-bold text-dark">₹{{ number_format($so->total_amount ?: ($so->grand_total ?? 0), 2) }}</td>
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
                    @else
                        <div class="text-center py-5 text-muted border rounded">
                            <i class="feather-shopping-cart display-6 text-muted opacity-50 mb-2 d-block"></i>
                            <p class="mb-0 fs-13">No Sales Orders placed for this customer yet.</p>
                        </div>
                    @endif
                </div>

                {{-- ================= TAB 4: LEDGER STATEMENT ================= --}}
                <div class="tab-pane fade" id="ledger-pane" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0"><i class="feather-book-open me-2 text-warning"></i>Customer Account Ledger Statement</h6>
                            <span class="text-muted fs-11">Real-time debit, credit and running balance statement with linked Deal & SO reference</span>
                        </div>
                        <x-ui.button type="button" variant="light" size="sm" class="border" icon="feather-printer" onclick="window.print()">
                            Print Statement
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
                        $sortedLedger = $ledgerRows->sortBy('date');
                    @endphp

                    @if($sortedLedger->isNotEmpty())
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table" id="ledgerTable" class="mb-0">
                                <thead>
                                    <tr style="background-color: #e8ecf1 !important;">
                                        <th style="background-color: #e8ecf1 !important;">Date</th>
                                        <th style="background-color: #e8ecf1 !important;">Type</th>
                                        <th style="background-color: #e8ecf1 !important;">Reference #</th>
                                        <th style="background-color: #e8ecf1 !important;">Particulars / Description</th>
                                        <th style="background-color: #e8ecf1 !important;">Linked Deal & SO</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end text-danger">Debit (Billed) (₹)</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end text-success">Credit (Paid) (₹)</th>
                                        <th style="background-color: #e8ecf1 !important;" class="text-end text-primary">Running Balance (₹)</th>
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
                                            <td><span class="badge {{ $row['type'] === 'Invoice' ? 'bg-soft-danger text-danger' : 'bg-soft-success text-success' }} fs-11">{{ $row['type'] }}</span></td>
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
                                            <td class="text-end text-danger font-monospace">{{ $row['debit'] > 0 ? '₹' . number_format($row['debit'], 2) : '—' }}</td>
                                            <td class="text-end text-success font-monospace">{{ $row['credit'] > 0 ? '₹' . number_format($row['credit'], 2) : '—' }}</td>
                                            <td class="text-end font-monospace fw-bold {{ $running > 0 ? 'text-danger' : 'text-success' }}">₹{{ number_format($running, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light font-monospace fw-bold">
                                    <tr>
                                        <td colspan="5" class="text-end text-dark">Closing Balance:</td>
                                        <td class="text-end text-danger">₹{{ number_format($sortedLedger->sum('debit'), 2) }}</td>
                                        <td class="text-end text-success">₹{{ number_format($sortedLedger->sum('credit'), 2) }}</td>
                                        <td class="text-end text-primary fs-14">₹{{ number_format($running, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </x-ui.odoo-form-ui>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted border rounded">
                            <i class="feather-book-open display-6 text-muted opacity-50 mb-2 d-block"></i>
                            <p class="mb-0 fs-13">No financial ledger transactions found for this customer.</p>
                        </div>
                    @endif
                </div>

            </div>
        </x-ui.odoo-form-ui>
    </div>
@endsection
