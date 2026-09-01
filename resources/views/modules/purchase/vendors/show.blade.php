@extends('layouts.duralux')

@section('title', 'Supplier Finance 360° | ' . $vendor->name)
@section('page-title', 'Supplier Finance 360° View')
@section('breadcrumb', 'Supply Chain / Purchase / Vendors / Profile')

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('purchase.vendors.index') }}" class="btn btn-light border p-2 d-inline-flex align-items-center justify-content-center" title="Back to Suppliers">
            <i class="feather-arrow-left fs-16"></i>
        </a>
        <a href="{{ route('purchase.vendors.edit', $vendor->id) }}" class="btn btn-light border" title="Edit Vendor Details">
            <i class="feather-edit-2 me-1"></i>Edit
        </a>
        @if(Route::has('purchase.orders.create'))
            <x-ui.button href="{{ route('purchase.orders.create', ['vendor_id' => $vendor->id]) }}" variant="primary" icon="feather-plus">
                Create PO
            </x-ui.button>
        @endif
        @if(Route::has('purchase.bills.create'))
            <x-ui.button href="{{ route('purchase.bills.create', ['vendor_id' => $vendor->id]) }}" variant="success" icon="feather-file-plus">
                Enter Vendor Bill
            </x-ui.button>
        @endif
        @if(Route::has('purchase.payments.create'))
            <x-ui.button href="{{ route('purchase.payments.create', ['vendor_id' => $vendor->id]) }}" variant="warning" icon="feather-dollar-sign">
                Record Payment
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
                                <x-ui.status-badge status="active" label="Active Supplier" dot="true" size="sm" />
                            @else
                                <x-ui.status-badge status="inactive" label="Inactive Supplier" dot="true" size="sm" />
                            @endif

                            <span class="badge bg-soft-secondary text-dark border font-monospace fs-11 px-2.5 py-1">
                                Code: {{ $vendor->code ?: 'N/A' }}
                            </span>
                        </div>

                        <div class="d-flex align-items-center gap-3 text-muted fs-12 mt-2 flex-wrap">
                            @if($vendor->company_name)
                                <span><i class="feather-building me-1 text-primary"></i>Company: <strong class="text-dark">{{ $vendor->company_name }}</strong></span>
                                <span class="text-black-50">•</span>
                            @endif
                            @if($vendor->gstin)
                                <span><strong class="text-dark">GSTIN:</strong> <span class="font-monospace text-primary fw-bold">{{ $vendor->gstin }}</span></span>
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
                                    <span><i class="feather-map-pin me-1 text-danger"></i><strong>Primary:</strong> {{ Str::limit($vendor->address, 65) }}</span>
                                @endif
                                @if($vendor->billing_address)
                                    <span><i class="feather-file-text me-1 text-primary"></i><strong>Billing:</strong> {{ Str::limit($vendor->billing_address, 65) }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="text-end">
                    <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">Payable Health Status</span>
                    @if(($vendorAdvanceCredit ?? 0) > 0)
                        <span class="badge bg-soft-info text-info fs-12 px-3 py-1.5 fw-bold border"><i class="feather-arrow-down-left me-1"></i>Advance Paid ({{ active_currency_symbol() }}{{ number_format($vendorAdvanceCredit, 2) }})</span>
                    @elseif($outstandingPayable <= 0)
                        <span class="badge bg-soft-success text-success fs-12 px-3 py-1.5 fw-bold border"><i class="feather-check-circle me-1"></i>All Settled (Zero Dues)</span>
                    @elseif($overdueAmount > 0)
                        <span class="badge bg-soft-danger text-danger fs-12 px-3 py-1.5 fw-bold border"><i class="feather-alert-triangle me-1"></i>Overdue Payment Dues</span>
                    @else
                        <span class="badge bg-soft-warning text-warning fs-12 px-3 py-1.5 fw-bold border"><i class="feather-clock me-1"></i>Payment Dues Pending</span>
                    @endif
                </div>
            </div>

            {{-- 2. Finance KPI Stat Cards --}}
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="p-3 rounded-3 border bg-light shadow-2xs">
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">Total Bills Amount</span>
                        <h4 class="fw-bold text-dark mb-0 fs-18 font-monospace">{{ active_currency_symbol() }}{{ number_format($totalBilled, 2) }}</h4>
                        <span class="fs-11 text-muted">{{ $bills->count() }} vendor bills</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-3 border bg-soft-success shadow-2xs">
                        <span class="text-success fs-11 fw-bold text-uppercase d-block mb-1">Total Paid Amount</span>
                        <h4 class="fw-bold text-success mb-0 fs-18 font-monospace">{{ active_currency_symbol() }}{{ number_format($totalPaid, 2) }}</h4>
                        <span class="fs-11 text-success">{{ $payments->count() }} payment records</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-3 border bg-soft-danger shadow-2xs">
                        <span class="text-danger fs-11 fw-bold text-uppercase d-block mb-1">Net Outstanding Dues</span>
                        <h4 class="fw-bold text-danger mb-0 fs-18 font-monospace">{{ active_currency_symbol() }}{{ number_format($outstandingPayable, 2) }}</h4>
                        <span class="fs-11 text-danger">Net payable balance</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-3 border bg-soft-warning shadow-2xs">
                        <span class="text-warning fs-11 fw-bold text-uppercase d-block mb-1">Overdue Amount</span>
                        <h4 class="fw-bold text-warning mb-0 fs-18 font-monospace">{{ active_currency_symbol() }}{{ number_format($overdueAmount, 2) }}</h4>
                        <span class="fs-11 text-warning">Passed due date</span>
                    </div>
                </div>
            </div>

            {{-- 3. Interactive Tabs Navigation --}}
            <ul class="nav nav-tabs custom-tabs mb-4 border-bottom" id="vendorTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold fs-13" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab"><i class="feather-info me-1.5"></i>Profile & Master Info</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold fs-13" id="bills-tab" data-bs-toggle="tab" data-bs-target="#bills-pane" type="button" role="tab"><i class="feather-file-text me-1.5"></i>Vendor Bills ({{ $bills->count() }})</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold fs-13" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments-pane" type="button" role="tab"><i class="feather-dollar-sign me-1.5"></i>Payments Paid ({{ $payments->count() }})</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold fs-13" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders-pane" type="button" role="tab"><i class="feather-truck me-1.5"></i>Purchase Orders ({{ $purchaseOrders->count() }})</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold fs-13 text-primary" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger-pane" type="button" role="tab"><i class="feather-book me-1.5"></i>Vendor Ledger Statement</button>
                </li>
            </ul>

            {{-- 4. Tab Content Panels --}}
            <div class="tab-content" id="vendorTabsContent">

                {{-- TAB 1: OVERVIEW --}}
                <div class="tab-pane fade show active" id="overview-pane" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 bg-white">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-briefcase me-2"></i>Supplier Master Details</h6>
                                <table class="table table-borderless table-sm mb-0 fs-13">
                                    <tr>
                                        <td class="text-muted" style="width: 40%;">Supplier Name:</td>
                                        <td class="fw-bold text-dark">{{ $vendor->name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Company Trade Name:</td>
                                        <td class="fw-semibold text-dark">{{ $vendor->company_name ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Supplier Code:</td>
                                        <td class="font-monospace text-primary fw-bold">{{ $vendor->code ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Email:</td>
                                        <td>{{ $vendor->email ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Phone:</td>
                                        <td>{{ $vendor->phone ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Status:</td>
                                        <td>
                                            @if(strtolower($vendor->status) === 'active')
                                                <x-ui.status-badge status="active" label="Active" dot="true" size="sm" />
                                            @else
                                                <x-ui.status-badge status="inactive" label="Inactive" dot="true" size="sm" />
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 bg-white">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-credit-card me-2"></i>Tax & Banking Setup</h6>
                                <table class="table table-borderless table-sm mb-0 fs-13">
                                    <tr>
                                        <td class="text-muted" style="width: 40%;">GSTIN / TAX ID:</td>
                                        <td class="font-monospace text-primary fw-bold">{{ $vendor->gstin ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">PAN Number:</td>
                                        <td class="font-monospace text-dark fw-semibold">{{ $vendor->pan ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Bank Name:</td>
                                        <td class="fw-semibold text-dark">{{ $vendor->bank_name ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Account Number:</td>
                                        <td class="font-monospace text-dark">{{ $vendor->account_number ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">IFSC / SWIFT Code:</td>
                                        <td class="font-monospace text-dark">{{ $vendor->ifsc_code ?: 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Default Payment Terms:</td>
                                        <td class="fw-bold text-success">{{ $vendor->payment_terms ?: 'Standard Net 30 Days' }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="p-3 border rounded-3 bg-white">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-map-pin me-2"></i>Addresses & Locations</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">Primary Office Address</label>
                                        <p class="fs-13 text-dark mb-0">{!! nl2br(e($vendor->address ?: 'N/A')) !!}</p>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">Billing Address</label>
                                        <p class="fs-13 text-dark mb-0">{!! nl2br(e($vendor->billing_address ?: 'N/A')) !!}</p>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">Dispatch Warehouse Address</label>
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
                                    <th>Bill #</th>
                                    <th>Challan / Inv #</th>
                                    <th>Bill Date</th>
                                    <th>Due Date</th>
                                    <th class="text-end">Total Amount</th>
                                    <th class="text-end">Paid Amount</th>
                                    <th class="text-end">Balance Due</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Action</th>
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
                                                    @if($isPast)<i class="feather-alert-circle ms-1" title="Overdue"></i>@endif
                                                </span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end font-monospace fw-bold text-dark">{{ active_currency_symbol() }}{{ number_format($bill->total_amount, 2) }}</td>
                                        <td class="text-end font-monospace text-success">{{ active_currency_symbol() }}{{ number_format($bill->amount_paid, 2) }}</td>
                                        <td class="text-end font-monospace fw-bold text-danger">{{ active_currency_symbol() }}{{ number_format($bill->balance_due, 2) }}</td>
                                        <td class="text-center">
                                            <x-ui.status-badge :status="$bill->status" size="sm" />
                                        </td>
                                        <td class="text-end pe-3">
                                            <x-ui.action-dropdown :viewUrl="route('purchase.bills.show', $bill->id)" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">No vendor bills recorded for this supplier.</td>
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
                                    <th>Payment #</th>
                                    <th>Payment Date</th>
                                    <th>Payment Method</th>
                                    <th>Reference / Txn No</th>
                                    <th class="text-end">Amount Paid</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end pe-3">Action</th>
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
                                        <td class="text-end font-monospace fw-bold text-success">{{ active_currency_symbol() }}{{ number_format($pmt->amount, 2) }}</td>
                                        <td class="text-center">
                                            <x-ui.status-badge :status="$pmt->status" size="sm" />
                                        </td>
                                        <td class="text-end pe-3">
                                            <x-ui.action-dropdown :viewUrl="route('purchase.payments.show', $pmt->id)" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No payment records found for this supplier.</td>
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
                                    <th>PO #</th>
                                    <th>Order Date</th>
                                    <th>Expected Date</th>
                                    <th class="text-center">Items</th>
                                    <th class="text-end">Total Amount</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end pe-3">Action</th>
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
                                        <td class="text-center font-monospace">{{ $po->items->count() }} items</td>
                                        <td class="text-end font-monospace fw-bold text-dark">{{ active_currency_symbol() }}{{ number_format($po->total_amount, 2) }}</td>
                                        <td class="text-center">
                                            <x-ui.status-badge :status="$po->status" size="sm" />
                                        </td>
                                        <td class="text-end pe-3">
                                            <x-ui.action-dropdown :viewUrl="route('purchase.orders.show', $po->id)" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No purchase orders issued to this supplier.</td>
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
                            <h6 class="fw-bold text-primary mb-0"><i class="feather-book me-1"></i>Account Payable Ledger Statement</h6>
                            <span class="fs-12 text-muted">Chronological statement of vendor bills (credit) and payments (debit).</span>
                        </div>
                        <div>
                            <span class="badge bg-soft-primary text-primary px-3 py-2 fs-12 fw-bold font-monospace border">
                                Current Running Payable: {{ active_currency_symbol() }}{{ number_format($outstandingPayable, 2) }}
                            </span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" class="mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Voucher / Ref #</th>
                                    <th>Entry Type</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit (Settlement)</th>
                                    <th class="text-end">Credit (Bill Payable)</th>
                                    <th class="text-end">Running Payable Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($vendor->opening_balance > 0)
                                    <tr class="table-warning">
                                        <td class="fw-bold">—</td>
                                        <td class="font-monospace fw-bold">OPENING-BAL</td>
                                        <td><span class="badge bg-warning text-dark">Opening Balance</span></td>
                                        <td>Initial Supplier Opening Dues</td>
                                        <td class="text-end font-monospace">0.00</td>
                                        <td class="text-end font-monospace fw-bold text-dark">{{ active_currency_symbol() }}{{ number_format($vendor->opening_balance, 2) }}</td>
                                        <td class="text-end font-monospace fw-bold text-primary">{{ active_currency_symbol() }}{{ number_format($vendor->opening_balance, 2) }}</td>
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
                                                <span class="badge bg-soft-danger text-danger border">Vendor Bill</span>
                                            @else
                                                <span class="badge bg-soft-success text-success border">Vendor Payment</span>
                                            @endif
                                        </td>
                                        <td class="fs-12 text-muted">{{ $entry['description'] }}</td>
                                        <td class="text-end font-monospace fw-bold text-success">
                                            {{ $entry['debit'] > 0 ? active_currency_symbol() . number_format($entry['debit'], 2) : '—' }}
                                        </td>
                                        <td class="text-end font-monospace fw-bold text-danger">
                                            {{ $entry['credit'] > 0 ? active_currency_symbol() . number_format($entry['credit'], 2) : '—' }}
                                        </td>
                                        <td class="text-end font-monospace fw-bold text-primary">
                                            {{ active_currency_symbol() }}{{ number_format($entry['running_balance'], 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    @if(!($vendor->opening_balance > 0))
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">No ledger transactions recorded yet.</td>
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
