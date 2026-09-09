@extends('layouts.duralux')

@section('title', $account->name . ' - Account 360° | SaaS ERP')
@section('page-title', 'Account 360° View')
@section('breadcrumb', 'Account Details')

@push('styles')
<style>
    .zoho-field-label {
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        margin-bottom: 2px;
    }
    .zoho-field-value {
        font-size: 13px;
        color: #0f172a;
    }
    .account-info-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px 18px;
    }
</style>
@endpush


@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('crm.accounts.index') }}" class="btn btn-light border p-2 d-inline-flex align-items-center justify-content-center" title="Back to Accounts">
            <i class="feather-arrow-left fs-16"></i>
        </a>
        <a href="{{ route('crm.deals.create', ['account_id' => $account->id]) }}" class="btn btn-soft-warning fw-bold px-3">
            <i class="feather-plus me-1"></i>Create New Deal
        </a>
        <a href="{{ route('crm.accounts.edit', $account) }}" class="btn btn-primary px-3" style="background-color: #1e40af; border-color: #1e40af;">
            <i class="feather-edit me-1"></i>Edit Account
        </a>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
    

        {{-- 1. Single Page Header: Account Profile Info --}}
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 pb-3 border-bottom mb-4">
            <div class="d-flex align-items-start gap-3">
                <div class="avatar-text avatar-xl bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold fs-22 shadow-sm flex-shrink-0" style="width: 56px; height: 56px;">
                    <i class="feather-briefcase"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <h4 class="fw-bold text-dark mb-0 fs-20 me-1">{{ $account->name }}</h4>
                        <span class="badge bg-light text-primary border font-monospace px-2.5 py-1 fs-12">{{ $account->account_number }}</span>
                        @if($account->status === 'active')
                            <span class="badge bg-soft-success text-success border border-success-subtle rounded-pill px-2.5 py-1 fs-11 fw-bold">
                                <i class="feather-check-circle me-1"></i>Active Account
                            </span>
                        @else
                            <span class="badge bg-soft-danger text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fs-11 fw-bold">
                                <i class="feather-x-circle me-1"></i>Inactive Account
                            </span>
                        @endif
                        @if($account->customer)
                            <span class="badge bg-soft-success text-success border fs-11 px-2.5 py-1 fw-bold">
                                <i class="feather-user-check me-1"></i>Verified Customer
                            </span>
                        @endif
                    </div>

                    {{-- Clean Spaced 2-Row Contact & Location Meta --}}
                    <div class="d-flex align-items-center gap-4 text-muted fs-12 flex-wrap pt-1 mb-2.5">
                        @if($account->gstin)
                            <span><i class="feather-file-text me-1.5 text-primary"></i><strong>GSTIN:</strong> <span class="font-monospace text-primary fw-bold ms-1">{{ $account->gstin }}</span></span>
                        @endif
                        <span><i class="feather-phone me-1.5 text-primary"></i><strong>Phone:</strong> <strong class="text-dark ms-1">{{ $account->phone ?: 'N/A' }}</strong></span>
                        <span><i class="feather-mail me-1.5 text-primary"></i><strong>Email:</strong> @if($account->email)<a href="mailto:{{ $account->email }}" class="text-primary fw-semibold ms-1">{{ $account->email }}</a>@else<strong class="text-dark ms-1">N/A</strong>@endif</span>
                    </div>

                    <div class="d-flex align-items-center gap-4 text-muted fs-12 flex-wrap pt-1">
                        @if($account->city || $account->state)
                            <span><i class="feather-map-pin me-1.5 text-primary"></i><strong>Location:</strong> <strong class="text-dark ms-1">{{ implode(', ', array_filter([$account->city, $account->state])) }}</strong></span>
                        @endif
                        @if($account->owner)
                            <span><i class="feather-user me-1.5 text-primary"></i><strong>Account Manager:</strong> <span class="badge bg-soft-primary text-primary fs-11 fw-bold ms-1 px-2.5 py-1">{{ $account->owner->name }}</span></span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Financial Health Status (right side) --}}
            <div class="text-end">
                <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">Financial Health Status</span>
                @if(($customerCreditBalance ?? 0) > 0)
                    <span class="badge bg-soft-info text-info fs-12 px-3 py-1.5 fw-bold border"><i class="feather-arrow-down-left me-1"></i>Advance Credit</span>
                @elseif($outstandingBalance <= 0)
                    <span class="badge bg-soft-success text-success fs-12 px-3 py-1.5 fw-bold border"><i class="feather-check-circle me-1"></i>All Clear (Zero Balance)</span>
                @elseif($overdueAmount > 0)
                    <span class="badge bg-soft-danger text-danger fs-12 px-3 py-1.5 fw-bold border"><i class="feather-alert-triangle me-1"></i>Overdue Balance</span>
                @else
                    <span class="badge bg-soft-warning text-warning fs-12 px-3 py-1.5 fw-bold border"><i class="feather-clock me-1"></i>Payment Pending</span>
                @endif
            </div>
        </div>

        {{-- 2. Navigation Tabs - using common horizontal-tabs component --}}
        <x-ui.horizontal-tabs id="accountTabs" :tabs="[
            ['id' => 'overview-pane',   'label' => 'Overview',                  'icon' => 'feather-info',          'active' => true],
            ['id' => 'invoices-pane',   'label' => 'Invoices ' . $invoices->count(),       'icon' => 'feather-file-text',     'active' => false],
            ['id' => 'payments-pane',   'label' => 'Payments ' . $payments->count(), 'icon' => 'feather-dollar-sign',   'active' => false],
            ['id' => 'orders-pane',     'label' => 'Sales Orders ' . $salesOrders->count(),     'icon' => 'feather-shopping-cart', 'active' => false],
            ['id' => 'ledger-pane',     'label' => 'Ledger Statement',  'icon' => 'feather-book-open',     'active' => false],
            ['id' => 'deals-pane',      'label' => 'Deals ' . $account->deals->count(), 'icon' => 'feather-git-branch', 'active' => false],
        ]" class="mb-0" />

        {{-- Spacer --}}
        <div class="pt-3"></div>

        {{-- 3. OVERVIEW METRICS (Financial - shown on Overview/default tabs) --}}
        <div id="overview-metrics" class="bg-light p-3 rounded-3 border mb-4">
            <div class="row g-3 text-center text-md-start">
                <div class="col-md-3 border-end">
                    <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-file-text me-1 text-primary"></i>Total Lifetime Billed</span>
                    <h4 class="fw-bold text-primary mb-0 fs-18">₹{{ number_format($totalBilled, 2) }}</h4>
                    <span class="fs-11 text-muted">{{ $invoiceCount }} Invoices Issued</span>
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

        {{-- 3b. DEALS METRICS (shown on Deals tab) --}}
        <div id="deals-metrics" class="bg-light p-3 rounded-3 border mb-4" style="display: none;">
            <div class="row g-3 text-center text-md-start">
                <div class="col-md-3 border-end">
                    <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-trending-up me-1 text-primary"></i>Total Pipeline Value</span>
                    <h4 class="fw-bold text-primary mb-0 fs-18">₹{{ number_format($pipelineValue, 2) }}</h4>
                    <span class="fs-11 text-muted">{{ $account->deals->count() }} Total Opportunities</span>
                </div>
                <div class="col-md-3 border-end">
                    <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-layers me-1 text-info"></i>Active Deals Count</span>
                    <h4 class="fw-bold text-info mb-0 fs-18">{{ $openDealsCount }} Deals</h4>
                    <span class="fs-11 text-muted">In Progress / Qualified</span>
                </div>
                <div class="col-md-3 border-end">
                    <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-check-circle me-1 text-success"></i>Won Deals Count</span>
                    <h4 class="fw-bold text-success mb-0 fs-18">{{ $wonDealsCount }} Deals</h4>
                    <span class="fs-11 text-muted">Successfully Closed</span>
                </div>
                <div class="col-md-3">
                    <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-credit-card me-1 text-warning"></i>Credit Limit / Available</span>
                    <h4 class="fw-bold text-dark mb-0 fs-18">₹{{ number_format($creditLimit, 2) }}</h4>
                    <span class="fs-11 text-muted">Quotations: {{ $account->quotations->count() }} Quotes</span>
                </div>
            </div>
        </div>

        {{-- 4. Tab Content Panes --}}
        <div class="tab-content pt-2" id="accountTabsContent">

            {{-- ================= TAB 1: OVERVIEW ================= --}}
            <div class="tab-pane fade show active" id="overview-pane" role="tabpanel">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="account-info-box mb-3">
                            <h6 class="fw-bold text-primary mb-3 fs-13"><i class="feather-briefcase me-1.5"></i>Company Account Details</h6>
                            <div class="row g-3 fs-12">
                                <div class="col-6">
                                    <div class="zoho-field-label">Account Name</div>
                                    <div class="zoho-field-value fw-bold text-dark">{{ $account->name }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="zoho-field-label">Account Number</div>
                                    <div class="zoho-field-value font-monospace text-primary fw-bold">{{ $account->account_number }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="zoho-field-label">GSTIN / Tax ID</div>
                                    <div class="zoho-field-value font-monospace fw-bold text-dark">{{ $account->gstin ?: 'Not Available' }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="zoho-field-label">Industry Type</div>
                                    <div class="zoho-field-value text-dark">{{ $account->industry_type ?: 'General Business' }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="zoho-field-label">Website</div>
                                    <div class="zoho-field-value text-primary">
                                        @if($account->website)
                                            <a href="{{ Str::startsWith($account->website, 'http') ? $account->website : 'https://' . $account->website }}" target="_blank" class="text-primary text-decoration-none fw-semibold">
                                                <i class="feather-external-link me-1"></i>{{ $account->website }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="zoho-field-label">Credit Limit</div>
                                    <div class="zoho-field-value text-dark fw-bold">₹{{ number_format($account->credit_limit ?: 0, 2) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="account-info-box">
                            <h6 class="fw-bold text-primary mb-3 fs-13"><i class="feather-map-pin me-1.5"></i>Billing & Corporate Address</h6>
                            <div class="row g-3 fs-12 text-dark">
                                <div class="col-6">
                                    <div class="zoho-field-label">Street</div>
                                    <div class="zoho-field-value">{{ $account->street ?: '—' }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="zoho-field-label">City / State</div>
                                    <div class="zoho-field-value">{{ implode(', ', array_filter([$account->city, $account->state])) ?: '—' }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="zoho-field-label">Country / Zip</div>
                                    <div class="zoho-field-value">{{ implode(' - ', array_filter([$account->country, $account->zip_code])) ?: '—' }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="zoho-field-label">Account Manager</div>
                                    <div class="zoho-field-value"><span class="badge bg-soft-primary text-primary fs-11 fw-bold">{{ $account->owner?->name ?: 'Unassigned' }}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="account-info-box mb-3">
                            <h6 class="fw-bold text-primary mb-3 fs-13"><i class="feather-bar-chart-2 me-1.5"></i>Deals & Opportunity Stats</h6>
                            <div class="row g-3 fs-12">
                                <div class="col-6">
                                    <div class="zoho-field-label">Pipeline Value</div>
                                    <div class="zoho-field-value fw-bold text-primary fs-15">₹{{ number_format($pipelineValue, 2) }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="zoho-field-label">Active Deals Count</div>
                                    <div class="zoho-field-value fw-bold text-info fs-15">{{ $openDealsCount }} Deals</div>
                                </div>
                                <div class="col-6">
                                    <div class="zoho-field-label">Won Deals Count</div>
                                    <div class="zoho-field-value text-success fw-bold">{{ $wonDealsCount }} Deals</div>
                                </div>
                                <div class="col-6">
                                    <div class="zoho-field-label">Total Quotations</div>
                                    <div class="zoho-field-value text-dark fw-bold">{{ $account->quotations->count() }} Quotes</div>
                                </div>
                            </div>
                        </div>

                        <div class="account-info-box">
                            <h6 class="fw-bold text-primary mb-3 fs-13"><i class="feather-user me-1.5"></i>Primary Contact & Ownership</h6>
                            @php
                                $primaryContact = $account->contacts->where('is_primary', true)->first() ?: $account->contacts->first();
                            @endphp
                            @if($primaryContact)
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-dark fs-13"><i class="feather-user-check me-1 text-success"></i>{{ $primaryContact->name }}</span>
                                    <span class="badge bg-soft-primary text-primary fs-11">Primary Contact</span>
                                </div>
                                <div class="row g-2 fs-12 mb-2">
                                    <div class="col-6">
                                        <div class="zoho-field-label">Role</div>
                                        <div class="zoho-field-value">{{ $primaryContact->role ?: 'Decision Maker' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="zoho-field-label">Phone</div>
                                        <div class="zoho-field-value">{{ $primaryContact->mobile ?: ($primaryContact->phone ?: 'N/A') }}</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="zoho-field-label">Email</div>
                                        <div class="zoho-field-value text-primary">{{ $primaryContact->email ?: 'N/A' }}</div>
                                    </div>
                                </div>
                            @else
                                <div class="text-muted fs-12 mb-2">No primary contact added yet. Add via Personnel Contacts tab.</div>
                            @endif
                            <div class="pt-2 border-top d-flex align-items-center justify-content-between fs-12">
                                <span class="text-muted fw-semibold">Account Manager / Owner:</span>
                                <span class="badge bg-soft-primary text-primary fs-11 fw-bold"><i class="feather-user me-1"></i>{{ $account->owner?->name ?: 'Unassigned' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Personnel Contacts Section in Overview --}}
                <div class="mt-4 pt-2 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h6 class="fw-bold mb-0 text-dark fs-14">
                            <i class="feather-users me-2 text-primary"></i>Personnel Contacts
                            <span class="badge bg-soft-primary text-primary ms-1 fs-11">{{ $account->contacts->count() }}</span>
                        </h6>
                        <button type="button" class="btn btn-sm btn-soft-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addContactModal">
                            <i class="feather-plus me-1"></i>Add Contact
                        </button>
                    </div>
                    @if($account->contacts->isNotEmpty())
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table" id="contactsTable" class="mb-0">
                                <thead>
                                    <tr style="background-color: #e8ecf1 !important;">
                                        <th style="background-color: #e8ecf1 !important;">Name</th>
                                        <th style="background-color: #e8ecf1 !important;">Designation</th>
                                        <th style="background-color: #e8ecf1 !important;">Buying Center Role</th>
                                        <th style="background-color: #e8ecf1 !important;">Email</th>
                                        <th style="background-color: #e8ecf1 !important;">Mobile</th>
                                        <th style="background-color: #e8ecf1 !important;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($account->contacts as $cnt)
                                        <tr>
                                            <td class="fw-bold text-dark">
                                                {{ $cnt->name }}
                                                @if($cnt->is_primary)
                                                    <span class="badge bg-soft-primary text-primary ms-1">Primary</span>
                                                @endif
                                            </td>
                                            <td>{{ $cnt->designation ?: '—' }}</td>
                                            <td><span class="badge bg-light text-dark border px-2 py-0.5 fs-11">{{ $cnt->role ?: 'Decision Maker' }}</span></td>
                                            <td>{{ $cnt->email ?: '—' }}</td>
                                            <td>{{ $cnt->mobile ?: $cnt->phone ?: '—' }}</td>
                                            <td>
                                                <span class="badge {{ $cnt->status === 'active' ? 'bg-soft-success text-success' : 'bg-light text-muted' }} px-2 py-0.5">{{ ucfirst($cnt->status) }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                            <div id="contactsTable-info" class="fs-12 text-muted"></div>
                            <div id="contactsTable-pagination"></div>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted border rounded">
                            <i class="feather-users display-6 text-muted opacity-50 mb-2 d-block"></i>
                            <p class="mb-0 fs-13">No contact persons added yet.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ================= TAB 2: GST INVOICES ================= --}}
            <div class="tab-pane fade" id="invoices-pane" role="tabpanel">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-file-text me-2 text-primary"></i>Sales Invoices & Linked CRM Deals</h6>
                        <span class="text-muted fs-11">List of all GST Sales Invoices issued to this account's customer</span>
                    </div>
                    @if(Route::has('sales.invoices.create') && $account->customer)
                        <x-ui.button href="{{ route('sales.invoices.create', ['customer_id' => $account->customer->id]) }}" variant="outline-primary" size="sm" icon="feather-plus">
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
                                                <a href="{{ route('sales.invoices.show', $inv->id) }}" class="text-primary fw-bold">{{ $inv->invoice_number }}</a>
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
                                                            <a href="{{ route('crm.deals.show', $linkedDeal->id) }}" class="badge bg-soft-info text-info text-decoration-none fw-bold px-2.5 py-1.5 d-inline-flex align-items-center">
                                                                <i class="feather-git-branch me-1"></i>{{ $dealNo }}
                                                            </a>
                                                        @else
                                                            <span class="badge bg-soft-info text-info fw-bold px-2.5 py-1.5 d-inline-flex align-items-center"><i class="feather-git-branch me-1"></i>{{ $dealNo }}</span>
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
                    <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                        <div id="invoicesTable-info" class="fs-12 text-muted"></div>
                        <div id="invoicesTable-pagination"></div>
                    </div>
                @else
                    <div class="text-center py-5 text-muted border rounded">
                        <i class="feather-file-text display-6 text-muted opacity-50 mb-2 d-block"></i>
                        <p class="mb-0 fs-13">No GST Invoices issued for this account yet.</p>
                    </div>
                @endif
            </div>

            {{-- ================= TAB 3: PAYMENTS & RECEIPTS ================= --}}
            <div class="tab-pane fade" id="payments-pane" role="tabpanel">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-dollar-sign me-2 text-success"></i>Customer Payments & Receipts Traceability</h6>
                        <span class="text-muted fs-11">List of payment receipts collected from this account's customer</span>
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
                                                            <a href="{{ route('crm.deals.show', $payDeal->id) }}" class="badge bg-soft-info text-info text-decoration-none fw-bold px-2.5 py-1.5 d-inline-flex align-items-center">
                                                                <i class="feather-git-branch me-1"></i>{{ $dealNo }}
                                                            </a>
                                                        @else
                                                            <span class="badge bg-soft-info text-info fw-bold px-2.5 py-1.5 d-inline-flex align-items-center"><i class="feather-git-branch me-1"></i>{{ $dealNo }}</span>
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
                    <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                        <div id="paymentsTable-info" class="fs-12 text-muted"></div>
                        <div id="paymentsTable-pagination"></div>
                    </div>
                @else
                    <div class="text-center py-5 text-muted border rounded">
                        <i class="feather-dollar-sign display-6 text-muted opacity-50 mb-2 d-block"></i>
                        <p class="mb-0 fs-13">No payment receipts recorded for this account yet.</p>
                    </div>
                @endif
            </div>

            {{-- ================= TAB 4: SALES ORDERS ================= --}}
            <div class="tab-pane fade" id="orders-pane" role="tabpanel">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-shopping-cart me-2 text-info"></i>Sales Orders & Linked Deals Traceability</h6>
                        <span class="text-muted fs-11">List of Sales Orders generated for this account's customer</span>
                    </div>
                    @if(Route::has('sales.orders.create') && $account->customer)
                        <x-ui.button href="{{ route('sales.orders.create', ['customer_id' => $account->customer->id]) }}" variant="outline-primary" size="sm" icon="feather-plus">
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
                                                <a href="{{ route('sales.orders.show', $so->id) }}" class="text-primary fw-bold">{{ $orderNo }}</a>
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
                                                            <a href="{{ route('crm.deals.show', $linkedDeal->id) }}" class="badge bg-soft-info text-info text-decoration-none fw-bold px-2.5 py-1.5 d-inline-flex align-items-center">
                                                                <i class="feather-git-branch me-1"></i>{{ $dealNo }}
                                                            </a>
                                                        @else
                                                            <span class="badge bg-soft-info text-info fw-bold px-2.5 py-1.5 d-inline-flex align-items-center"><i class="feather-git-branch me-1"></i>{{ $dealNo }}</span>
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
                                        <td><span class="badge bg-light text-dark border">{{ $so->items->count() ?: 1 }} Item(s)</span></td>
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
                    <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                        <div id="ordersTable-info" class="fs-12 text-muted"></div>
                        <div id="ordersTable-pagination"></div>
                    </div>
                @else
                    <div class="text-center py-5 text-muted border rounded">
                        <i class="feather-shopping-cart display-6 text-muted opacity-50 mb-2 d-block"></i>
                        <p class="mb-0 fs-13">No Sales Orders placed for this account yet.</p>
                    </div>
                @endif
            </div>

            {{-- ================= TAB 5: CUSTOMER LEDGER STATEMENT ================= --}}
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
                            'deal_no' => $dealNo, 'deal_title' => $dealTitle, 'deal_id' => $dealId,
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
                                'deal_no' => $dealNo, 'deal_title' => $dealTitle, 'deal_id' => $dealId,
                                'so_no' => $soNo,
                                'debit' => 0.00, 'credit' => $retAmount,
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
                            'deal_no' => $dealNo, 'deal_title' => $dealTitle, 'deal_id' => $dealId,
                            'so_no' => $soNo,
                            'debit' => 0.00, 'credit' => floatval($pay->amount),
                        ]);
                    }
                    $sortedLedger = $ledgerRows->sortBy(function ($row) {
                        $ts = \Carbon\Carbon::parse($row['date'])->timestamp;
                        $typePriority = match($row['type']) { 'Invoice' => 0, 'Payment Receipt' => 1, 'Credit Note' => 2, default => 4 };
                        return sprintf('%012d_%d', $ts, $typePriority);
                    })->values();
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
                                    @php $running += ($row['debit'] - $row['credit']); @endphp
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
                                                                <a href="{{ route('crm.deals.show', $row['deal_id']) }}" class="badge bg-soft-info text-info text-decoration-none fw-bold px-2.5 py-1.5 d-inline-flex align-items-center">
                                                                    <i class="feather-git-branch me-1"></i>{{ $row['deal_no'] }}
                                                                </a>
                                                            @else
                                                                <span class="badge bg-soft-info text-info fw-bold px-2.5 py-1.5 d-inline-flex align-items-center"><i class="feather-git-branch me-1"></i>{{ $row['deal_no'] }}</span>
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
                    <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                        <div id="ledgerTable-info" class="fs-12 text-muted"></div>
                        <div id="ledgerTable-pagination"></div>
                    </div>
                @else
                    <div class="text-center py-5 text-muted border rounded">
                        <i class="feather-book-open display-6 text-muted opacity-50 mb-2 d-block"></i>
                        <p class="mb-0 fs-13">No financial ledger transactions found for this account.</p>
                    </div>
                @endif
            </div>

            {{-- ================= TAB 6: DEALS & PROJECTS ================= --}}
            <div class="tab-pane fade" id="deals-pane" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="feather-git-branch me-2 text-primary"></i>Linked Projects & Sales Opportunities</h6>
                    <a href="{{ route('crm.deals.create', ['account_id' => $account->id]) }}" class="btn btn-sm btn-soft-primary fw-bold">
                        <i class="feather-plus me-1"></i>New Deal
                    </a>
                </div>
                @if($account->deals->isNotEmpty())
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="dealsTable" class="mb-0">
                            <thead>
                                <tr style="background-color: #e8ecf1 !important;">
                                    <th style="background-color: #e8ecf1 !important;">Deal #</th>
                                    <th style="background-color: #e8ecf1 !important;">Project Title</th>
                                    <th style="background-color: #e8ecf1 !important;">Stage</th>
                                    <th style="background-color: #e8ecf1 !important;" class="text-end">Value (₹)</th>
                                    <th style="background-color: #e8ecf1 !important;">Closing Date</th>
                                    <th style="background-color: #e8ecf1 !important;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($account->deals as $deal)
                                    @php
                                        $stageColors = ['Qualification' => 'info', 'Needs Analysis' => 'primary', 'Proposal' => 'warning', 'Negotiation' => 'purple', 'Won' => 'success', 'Closed Won' => 'success', 'Lost' => 'danger', 'Closed Lost' => 'danger'];
                                        $badgeColor = $stageColors[$deal->stage] ?? 'secondary';
                                    @endphp
                                    <tr>
                                        <td class="font-monospace fw-bold text-primary">{{ $deal->deal_number }}</td>
                                        <td class="fw-bold text-dark">{{ $deal->title }}</td>
                                        <td>
                                            <span class="badge bg-soft-{{ $badgeColor }} text-{{ $badgeColor }} border border-{{ $badgeColor }}-subtle px-2 py-0.5 fw-bold">{{ $deal->stage }}</span>
                                        </td>
                                        <td class="text-end fw-bold text-success">₹{{ number_format($deal->actual_value ?: $deal->estimated_value, 2) }}</td>
                                        <td>{{ $deal->closing_date ? \Illuminate\Support\Carbon::parse($deal->closing_date)->format('d M Y') : '—' }}</td>
                                        <td class="text-center">
                                            <x-ui.action-dropdown :viewUrl="route('crm.deals.show', $deal)" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                        <div id="dealsTable-info" class="fs-12 text-muted"></div>
                        <div id="dealsTable-pagination"></div>
                    </div>
                @else
                    <div class="text-center py-4 text-muted border rounded">
                        <i class="feather-git-branch display-6 text-muted opacity-50 mb-2 d-block"></i>
                        <p class="mb-0 fs-13">No deals recorded for this account yet.</p>
                    </div>
                @endif
            </div>

        </div>
    </div>

    {{-- Modal: Add Contact --}}
    <x-ui.modal
        id="addContactModal"
        :title="'<i class=\'feather-user-plus text-primary me-2\'></i>Add Contact Person to <strong>' . e($account->name) . '</strong>'"
        formAction="{{ route('crm.accounts.contacts.store', $account) }}"
        formMethod="POST"
        submitText="Save Contact"
        closeText="Cancel"
        centered="true"
        size="md">

        <div class="mb-3">
            <label class="form-label fw-semibold fs-13">Contact Name <span class="text-danger">*</span></label>
            <x-ui.odoo-form-ui type="input" name="name" placeholder="e.g. Amit Patel" required />
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold fs-13">Designation</label>
            <x-ui.odoo-form-ui type="input" name="designation" placeholder="e.g. Director" />
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold fs-13">Buying Center Role</label>
            <x-ui.odoo-form-ui type="select" name="role">
                <option value="Purchase Decision Maker">Purchase Decision Maker</option>
                <option value="Technical Evaluator">Technical Evaluator</option>
                <option value="Finance">Finance / Accounts</option>
                <option value="Influencer">Influencer</option>
                <option value="End User">End User</option>
            </x-ui.odoo-form-ui>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold fs-13">Email</label>
            <x-ui.odoo-form-ui type="input" inputType="email" name="email" placeholder="amit@company.com" />
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold fs-13">Mobile Number</label>
            <x-ui.odoo-form-ui type="input" name="mobile" placeholder="9876543210" />
        </div>
        <div class="form-check mt-1">
            <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="isPrimaryCheck">
            <label class="form-check-label fs-13" for="isPrimaryCheck">Set as Primary Contact</label>
        </div>

    </x-ui.modal>
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
    // Initialize 10-item pagination for all tables
    initTablePagination('contactsTable', 'contactsTable-info', 'contactsTable-pagination', 10);
    initTablePagination('invoicesTable', 'invoicesTable-info', 'invoicesTable-pagination', 10);
    initTablePagination('paymentsTable', 'paymentsTable-info', 'paymentsTable-pagination', 10);
    initTablePagination('ordersTable', 'ordersTable-info', 'ordersTable-pagination', 10);
    initTablePagination('ledgerTable', 'ledgerTable-info', 'ledgerTable-pagination', 10);
    initTablePagination('dealsTable', 'dealsTable-info', 'dealsTable-pagination', 10);

    const overviewMetrics = document.getElementById('overview-metrics');
    const dealsMetrics = document.getElementById('deals-metrics');

    // Listen on Bootstrap tab shown event
    const tabEl = document.getElementById('accountTabs');
    if (tabEl) {
        tabEl.addEventListener('shown.bs.tab', function (e) {
            const targetId = e.target.getAttribute('data-bs-target');
            if (targetId === '#deals-pane') {
                if (overviewMetrics) overviewMetrics.style.display = 'none';
                if (dealsMetrics) dealsMetrics.style.display = 'block';
            } else {
                if (overviewMetrics) overviewMetrics.style.display = 'block';
                if (dealsMetrics) dealsMetrics.style.display = 'none';
            }
        });
    }
});
</script>
@endpush

