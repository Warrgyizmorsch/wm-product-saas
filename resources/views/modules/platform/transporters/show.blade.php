@extends('layouts.duralux')

@section('title', 'Transporter 360° Profile | ' . $transporter->name)
@section('page-title', 'Transporter 360° Profile')
@section('breadcrumb', 'Platform / Transporters / Profile')

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('platform.transporters.index') }}" class="btn btn-light border p-2 d-inline-flex align-items-center justify-content-center" title="Back to Transporters">
            <i class="feather-arrow-left fs-16"></i>
        </a>
        <button type="button" class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#editTransporterModal" title="Edit Transporter Master">
            <i class="feather-edit-2 me-1"></i>Edit Profile
        </button>
        @if(Route::has('inventory.dispatches.create'))
            <x-ui.button href="{{ route('inventory.dispatches.create', ['transporter_id' => $transporter->id]) }}" variant="primary" icon="feather-send">
                Create Dispatch Order
            </x-ui.button>
        @endif
    </div>
@endsection

@section('content')
<div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
    <x-ui.odoo-form-ui type="sheet">

        {{-- 1. Single Page Header: Profile Info --}}
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 pb-3 border-bottom mb-4">
            <div class="d-flex align-items-start gap-3">
                <div class="avatar-text avatar-xl bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold fs-22 shadow-sm flex-shrink-0" style="width: 56px; height: 56px;">
                    <i class="feather-truck fs-24"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h4 class="fw-bold text-dark mb-0 fs-19 me-1">{{ $transporter->name }}</h4>
                        @if($transporter->code)
                            <span class="badge bg-light text-dark border font-monospace fs-11 px-2 py-1">
                                {{ $transporter->code }}
                            </span>
                        @endif

                        @if($transporter->status === 'active')
                            <x-ui.status-badge status="active" label="Active Transporter" dot="true" size="sm" />
                        @else
                            <x-ui.status-badge status="inactive" label="Inactive Transporter" dot="true" size="sm" />
                        @endif

                        @if($transporter->transporter_id)
                            <span class="badge bg-soft-primary text-primary border font-monospace fs-11 px-2.5 py-1">
                                E-Way ID: {{ $transporter->transporter_id }}
                            </span>
                        @endif

                        <span class="badge bg-soft-info text-info border fs-11 px-2.5 py-1 text-uppercase">
                            <i class="feather-navigation me-1"></i>{{ $transporter->transport_mode ?: 'Road' }}
                        </span>
                    </div>

                    <div class="d-flex align-items-center gap-3 text-muted fs-12 mt-2 flex-wrap">
                        @if($transporter->gstin)
                            <span><strong>GSTIN:</strong> <span class="font-monospace text-primary fw-bold">{{ $transporter->gstin }}</span></span>
                            <span class="text-black-50">•</span>
                        @endif

                        @if($transporter->pan_number)
                            <span><strong>PAN:</strong> <span class="font-monospace text-dark fw-bold">{{ $transporter->pan_number }}</span></span>
                            <span class="text-black-50">•</span>
                        @endif

                        @if($transporter->phone)
                            <span><i class="feather-phone me-1 text-primary"></i><strong class="text-dark">{{ $transporter->phone }}</strong></span>
                            <span class="text-black-50">•</span>
                        @endif

                        @if($transporter->email)
                            <span><i class="feather-mail me-1 text-primary"></i><a href="mailto:{{ $transporter->email }}" class="text-primary fw-semibold">{{ $transporter->email }}</a></span>
                            <span class="text-black-50">•</span>
                        @endif

                        @if($transporter->city || $transporter->state)
                            <span><i class="feather-map-pin me-1 text-danger"></i><strong>Location:</strong> {{ implode(', ', array_filter([$transporter->city, $transporter->state])) }}</span>
                        @endif
                    </div>

                    @if($transporter->address)
                        <div class="text-muted fs-12 mt-1">
                            <i class="feather-home me-1 text-muted"></i><strong>Address:</strong> {{ $transporter->address }} @if($transporter->pincode) - {{ $transporter->pincode }} @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-soft-danger text-danger fs-13 px-3 py-2 fw-bold border">
                    <i class="feather-alert-circle me-1"></i>Freight Payable: {{ active_currency_symbol() }}{{ number_format($stats['outstanding_payable'], 2) }}
                </span>
                <span class="badge bg-soft-warning text-warning fs-13 px-3 py-2 fw-bold border">
                    <i class="feather-clock me-1"></i>{{ $stats['pending_bills_count'] }} Pending Bills
                </span>
            </div>
        </div>

        {{-- 2. KPI Summary Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">Freight Booked (Billed)</div>
                    <div class="fs-18 fw-bold text-dark"><i class="feather-file-text me-1 text-primary"></i>{{ active_currency_symbol() }}{{ number_format($stats['total_freight_booked'], 2) }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">Freight Payments (Paid)</div>
                    <div class="fs-18 fw-bold text-success"><i class="feather-check-circle me-1"></i>{{ active_currency_symbol() }}{{ number_format($stats['total_paid'], 2) }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">Outstanding Payable</div>
                    <div class="fs-18 fw-bold text-danger"><i class="feather-dollar-sign me-1"></i>{{ active_currency_symbol() }}{{ number_format($stats['outstanding_payable'], 2) }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">Bill Summary (Paid / Pending)</div>
                    <div class="fs-16 fw-bold text-dark">
                        <span class="text-success"><i class="feather-check-circle me-1"></i>{{ $stats['paid_bills_count'] }} Paid</span>
                        <span class="text-muted mx-1">|</span>
                        <span class="text-danger"><i class="feather-clock me-1"></i>{{ $stats['pending_bills_count'] }} Pending</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Standard Clean 5 Tabs --}}
        <ul class="nav nav-tabs custom-tabs mb-4 border-bottom" id="transporterTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold fs-13" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab">
                    <i class="feather-info me-1.5"></i>Profile & Master Info
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold fs-13" id="bills-tab" data-bs-toggle="tab" data-bs-target="#bills-pane" type="button" role="tab">
                    <i class="feather-file-text me-1.5 text-primary"></i>Freight Bills ({{ $freightBills->count() }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold fs-13" id="dispatches-tab" data-bs-toggle="tab" data-bs-target="#dispatches-pane" type="button" role="tab">
                    <i class="feather-truck me-1.5 text-info"></i>Dispatch Orders ({{ number_format($stats['total_dispatches']) }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold fs-13" id="vehicles-tab" data-bs-toggle="tab" data-bs-target="#vehicles-pane" type="button" role="tab">
                    <i class="feather-navigation me-1.5"></i>Vehicles & LR Logs
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold fs-13 text-primary" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger-pane" type="button" role="tab">
                    <i class="feather-book me-1.5"></i>Transporter Ledger Statement
                </button>
            </li>
        </ul>

        {{-- 4. Tab Content Panels --}}
        <div class="tab-content" id="transporterTabsContent">

            {{-- TAB 1: PROFILE & MASTER INFO --}}
            <div class="tab-pane fade show active" id="overview-pane" role="tabpanel">
                <div class="row g-4">
                    {{-- 1. Master & Tax Compliance --}}
                    <div class="col-lg-6">
                        <div class="card border shadow-none h-100">
                            <div class="card-header bg-light py-2.5">
                                <h6 class="fw-bold text-primary mb-0 fs-13"><i class="feather-briefcase me-2"></i>Transporter Master & Tax Details</h6>
                            </div>
                            <div class="card-body fs-13">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 45%;">Transporter Name:</td>
                                            <td class="fw-bold text-dark">{{ $transporter->name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Master Code:</td>
                                            <td class="font-monospace text-primary fw-bold">{{ $transporter->code ?: 'TRP-' . str_pad($transporter->id, 4, '0', STR_PAD_LEFT) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">E-Way Transporter ID:</td>
                                            <td>
                                                @if($transporter->transporter_id)
                                                    <span class="badge bg-light text-dark font-monospace border px-2 py-1">{{ $transporter->transporter_id }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">GSTIN Number:</td>
                                            <td>
                                                @if($transporter->gstin)
                                                    <span class="font-monospace text-primary fw-bold">{{ $transporter->gstin }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">PAN Number:</td>
                                            <td class="font-monospace text-dark fw-semibold">{{ $transporter->pan_number ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">SAC Code:</td>
                                            <td class="font-monospace text-dark">{{ $transporter->sac_code ?: '996511' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">TDS Section & Rate:</td>
                                            <td>
                                                <span class="badge bg-soft-info text-info border font-monospace">
                                                    {{ $transporter->tds_section ?: '194C' }} ({{ number_format($transporter->tds_rate ?: 1.00, 2) }}%)
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Banking & Payment Terms --}}
                    <div class="col-lg-6">
                        <div class="card border shadow-none h-100">
                            <div class="card-header bg-light py-2.5">
                                <h6 class="fw-bold text-primary mb-0 fs-13"><i class="feather-credit-card me-2"></i>Banking & Payout Details</h6>
                            </div>
                            <div class="card-body fs-13">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 45%;">Bank Name:</td>
                                            <td class="fw-bold text-dark">{{ $transporter->bank_name ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Branch Name:</td>
                                            <td>{{ $transporter->branch_name ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Account Holder:</td>
                                            <td class="fw-semibold text-dark">{{ $transporter->account_name ?: ($transporter->name) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Account Number:</td>
                                            <td class="font-monospace text-primary fw-bold">{{ $transporter->account_number ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">IFSC Code:</td>
                                            <td class="font-monospace text-dark fw-semibold">{{ $transporter->ifsc_code ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Payment Credit Terms:</td>
                                            <td><span class="badge bg-light text-dark border fw-bold">{{ $transporter->payment_terms ?: 'Net 30 Days' }}</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- 3. Fleet & Operating Capabilities --}}
                    <div class="col-lg-6">
                        <div class="card border shadow-none h-100">
                            <div class="card-header bg-light py-2.5">
                                <h6 class="fw-bold text-primary mb-0 fs-13"><i class="feather-truck me-2"></i>Fleet Capabilities & Service Zones</h6>
                            </div>
                            <div class="card-body fs-13">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 45%;">Transport Mode:</td>
                                            <td><span class="badge bg-soft-primary text-primary border text-uppercase">{{ $transporter->transport_mode ?: 'Road Transport' }}</span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Fleet Types Operated:</td>
                                            <td class="fw-semibold text-dark">{{ $transporter->fleet_type ?: 'Containers, Open Trucks, Trailers' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Serviceable Zones / Routes:</td>
                                            <td class="text-muted">{{ $transporter->serviceable_zones ?: 'Pan India / All Routes' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- 4. Contact Escalation Matrix --}}
                    <div class="col-lg-6">
                        <div class="card border shadow-none h-100">
                            <div class="card-header bg-light py-2.5">
                                <h6 class="fw-bold text-primary mb-0 fs-13"><i class="feather-users me-2"></i>Key Contacts</h6>
                            </div>
                            <div class="card-body fs-13">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 45%;">Coordinator:</td>
                                            <td class="fw-bold text-dark">{{ $transporter->contact_person_name ?: 'Primary Dispatch Coordinator' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Mobile / Phone:</td>
                                            <td>{{ $transporter->contact_person_phone ?: ($transporter->phone ?: '—') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Email Address:</td>
                                            <td>
                                                @if($transporter->contact_person_email || $transporter->email)
                                                    <a href="mailto:{{ $transporter->contact_person_email ?: $transporter->email }}" class="text-primary">{{ $transporter->contact_person_email ?: $transporter->email }}</a>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB 2: FREIGHT BILLS WITH EXPLICIT VIEW BUTTON --}}
            <div class="tab-pane fade" id="bills-pane" role="tabpanel">
                <div class="card border shadow-none">
                    <div class="card-header bg-light d-flex align-items-center justify-content-between py-2.5">
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-file-text me-1 text-primary"></i>Transporter Freight Bills (Paid vs Pending)</h6>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-soft-success text-success border fs-11 px-2.5 py-1">
                                <i class="feather-check-circle me-1"></i>{{ $stats['paid_bills_count'] }} Paid
                            </span>
                            <span class="badge bg-soft-danger text-danger border fs-11 px-2.5 py-1">
                                <i class="feather-alert-triangle me-1"></i>{{ $stats['pending_bills_count'] }} Pending
                            </span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-12">
                            <thead class="table-light fs-11 text-uppercase fw-bold text-muted">
                                <tr>
                                    <th class="ps-3" style="width: 15%;">Bill / Ref No.</th>
                                    <th style="width: 11%;">Bill Date</th>
                                    <th style="width: 16%;">Category / Source</th>
                                    <th style="width: 18%;">Particulars</th>
                                    <th class="text-end" style="width: 10%;">Bill Amount</th>
                                    <th class="text-end" style="width: 10%;">Paid Amount</th>
                                    <th class="text-end" style="width: 10%;">Balance Due</th>
                                    <th class="text-center" style="width: 10%;">Status</th>
                                    <th class="text-end pe-3" style="width: 10%;">Action</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12 text-dark">
                                @forelse($freightBills as $fb)
                                    <tr>
                                        <td class="ps-3 font-monospace">
                                            @if(!empty($fb['url']) && $fb['url'] !== '#')
                                                <a href="{{ $fb['url'] }}" class="fw-bold text-primary">
                                                    {{ $fb['bill_number'] }}
                                                </a>
                                            @else
                                                <span class="fw-bold text-dark">{{ $fb['bill_number'] }}</span>
                                            @endif
                                        </td>
                                        <td class="text-muted font-monospace">
                                            {{ \Carbon\Carbon::parse($fb['date'])->format('d M Y') }}
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1 fs-11">
                                                {{ $fb['type'] }}
                                            </span>
                                        </td>
                                        <td class="text-muted">{{ $fb['reference'] }}</td>
                                        <td class="text-end fw-bold text-dark font-monospace">
                                            {{ active_currency_symbol() }}{{ number_format($fb['total_amount'], 2) }}
                                        </td>
                                        <td class="text-end text-success fw-semibold font-monospace">
                                            {{ active_currency_symbol() }}{{ number_format($fb['paid_amount'], 2) }}
                                        </td>
                                        <td class="text-end text-danger fw-bold font-monospace">
                                            {{ active_currency_symbol() }}{{ number_format($fb['balance_due'], 2) }}
                                        </td>
                                        <td class="text-center">
                                            @if($fb['status'] === 'paid' || $fb['balance_due'] <= 0)
                                                <span class="badge bg-soft-success text-success px-2.5 py-1 fs-11 border">
                                                    <i class="feather-check-circle me-1"></i>Paid
                                                </span>
                                            @elseif($fb['status'] === 'partially_paid')
                                                <span class="badge bg-soft-warning text-warning px-2.5 py-1 fs-11 border">
                                                    <i class="feather-clock me-1"></i>Partially Paid
                                                </span>
                                            @else
                                                <span class="badge bg-soft-danger text-danger px-2.5 py-1 fs-11 border">
                                                    <i class="feather-x-circle me-1"></i>Unpaid
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            @if(!empty($fb['url']) && $fb['url'] !== '#')
                                                <a href="{{ $fb['url'] }}" class="btn btn-xs btn-light text-primary border" title="View Bill Details">
                                                    <i class="feather-eye me-1"></i>View Bill
                                                </a>
                                            @else
                                                <span class="btn btn-xs btn-light text-muted border disabled" style="opacity: 0.6;">
                                                    <i class="feather-eye me-1"></i>View
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">
                                            <i class="feather-file-text fs-32 d-block mb-2 text-muted"></i>
                                            No freight bills or invoices recorded yet for this transporter.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="table-light fw-bold fs-12 border-top">
                                <tr>
                                    <td colspan="4" class="ps-3 text-end text-uppercase">Summary Totals:</td>
                                    <td class="text-end text-dark font-monospace">{{ active_currency_symbol() }}{{ number_format($stats['total_freight_booked'], 2) }}</td>
                                    <td class="text-end text-success font-monospace">{{ active_currency_symbol() }}{{ number_format($stats['total_paid'], 2) }}</td>
                                    <td class="text-end text-danger font-monospace fs-13">{{ active_currency_symbol() }}{{ number_format($stats['outstanding_payable'], 2) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {{-- TAB 3: DISPATCH ORDERS --}}
            <div class="tab-pane fade" id="dispatches-pane" role="tabpanel">
                <div class="card border shadow-none">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-12">
                            <thead class="table-light fs-11 text-uppercase fw-bold text-muted">
                                <tr>
                                    <th class="ps-3">Dispatch No.</th>
                                    <th>Date</th>
                                    <th>Source Warehouse</th>
                                    <th>LR Number</th>
                                    <th>Vehicle No.</th>
                                    <th>Gross Weight</th>
                                    <th>Freight ({{ active_currency_symbol() }})</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dispatches as $dispatch)
                                    <tr>
                                        <td class="ps-3">
                                            @if(Route::has('inventory.dispatches.show'))
                                                <a href="{{ route('inventory.dispatches.show', $dispatch->id) }}" class="fw-bold text-primary font-monospace">
                                                    {{ $dispatch->dispatch_number }}
                                                </a>
                                            @else
                                                <span class="fw-bold text-dark font-monospace">{{ $dispatch->dispatch_number }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $dispatch->dispatch_date ? \Carbon\Carbon::parse($dispatch->dispatch_date)->format('d M Y') : '—' }}</td>
                                        <td>{{ $dispatch->warehouse?->name ?: 'Main Warehouse' }}</td>
                                        <td>
                                            @if($dispatch->lr_number)
                                                <span class="badge bg-light text-dark font-monospace border px-2 py-1">{{ $dispatch->lr_number }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $dispatch->vehicle_number ?: '—' }}</td>
                                        <td>{{ number_format($dispatch->gross_weight, 2) }} kg</td>
                                        <td class="fw-bold text-dark">{{ number_format($dispatch->freight_amount, 2) }}</td>
                                        <td>
                                            @if($dispatch->status === 'delivered')
                                                <span class="badge bg-soft-success text-success">Delivered</span>
                                            @elseif($dispatch->status === 'in_transit')
                                                <span class="badge bg-soft-warning text-warning">In Transit</span>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary">{{ ucfirst((string) $dispatch->status) }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            @if(Route::has('inventory.dispatches.show'))
                                                <a href="{{ route('inventory.dispatches.show', $dispatch->id) }}" class="btn btn-xs btn-light text-primary border" title="View Dispatch Details">
                                                    <i class="feather-eye me-1"></i>View
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="feather-send fs-28 d-block mb-1 text-muted"></i>
                                            No dispatch orders found for this transporter.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($dispatches->hasPages())
                        <div class="card-footer bg-white py-2.5 border-top">
                            {{ $dispatches->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- TAB 4: VEHICLES & DRIVER LOGS --}}
            <div class="tab-pane fade" id="vehicles-pane" role="tabpanel">
                <div class="card border shadow-none">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-12">
                            <thead class="table-light fs-11 text-uppercase fw-bold text-muted">
                                <tr>
                                    <th class="ps-3">Vehicle Number</th>
                                    <th>LR Number</th>
                                    <th>Driver Name</th>
                                    <th>Driver Phone</th>
                                    <th>Associated Dispatch</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dispatches->filter(fn($d) => $d->vehicle_number || $d->lr_number || $d->driver_name) as $d)
                                    <tr>
                                        <td class="ps-3 fw-bold text-dark font-monospace">
                                            {{ $d->vehicle_number ?: '—' }}
                                        </td>
                                        <td>
                                            @if($d->lr_number)
                                                <span class="badge bg-light text-dark font-monospace border px-2 py-1">{{ $d->lr_number }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $d->driver_name ?: '—' }}</td>
                                        <td>{{ $d->driver_phone ?: '—' }}</td>
                                        <td>
                                            @if(Route::has('inventory.dispatches.show'))
                                                <a href="{{ route('inventory.dispatches.show', $d->id) }}" class="fw-bold text-primary font-monospace">
                                                    {{ $d->dispatch_number }}
                                                </a>
                                            @else
                                                <span class="font-monospace text-dark">{{ $d->dispatch_number }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $d->dispatch_date ? \Carbon\Carbon::parse($d->dispatch_date)->format('d M Y') : '—' }}</td>
                                        <td>
                                            @if($d->status === 'delivered')
                                                <span class="badge bg-soft-success text-success">Delivered</span>
                                            @elseif($d->status === 'in_transit')
                                                <span class="badge bg-soft-warning text-warning">In Transit</span>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary">{{ ucfirst((string) $d->status) }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="feather-truck fs-28 d-block mb-1 text-muted"></i>
                                            No vehicle or driver log details recorded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- TAB 5: TRANSPORTER LEDGER STATEMENT --}}
            <div class="tab-pane fade" id="ledger-pane" role="tabpanel">
                <div class="card border shadow-none">
                    <div class="card-header bg-light d-flex align-items-center justify-content-between py-2.5">
                        <h6 class="fw-bold text-primary mb-0"><i class="feather-book me-1"></i>Account Payable Transporter Ledger Statement</h6>
                        <button type="button" onclick="window.print()" class="btn btn-xs btn-light border">
                            <i class="feather-printer me-1"></i>Print Statement
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-12">
                            <thead class="table-light fs-11 text-uppercase fw-bold text-muted">
                                <tr>
                                    <th class="ps-3" style="width: 11%;">Date</th>
                                    <th style="width: 14%;">Type</th>
                                    <th style="width: 16%;">Reference No.</th>
                                    <th style="width: 22%;">Particulars / Description</th>
                                    <th class="text-end" style="width: 10%;">Debit (Paid)</th>
                                    <th class="text-end" style="width: 10%;">Credit (Billed)</th>
                                    <th class="text-end" style="width: 10%;">Running Balance</th>
                                    <th class="text-end pe-3" style="width: 7%;">Action</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12 text-dark">
                                @forelse($ledgerWithBalance as $entry)
                                    <tr>
                                        <td class="ps-3 font-monospace text-muted">
                                            {{ \Carbon\Carbon::parse($entry['date'])->format('d M Y') }}
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-info text-info border px-2 py-1 fs-11 fw-semibold">
                                                {{ $entry['type'] }}
                                            </span>
                                        </td>
                                        <td>
                                            @if(!empty($entry['url']) && $entry['url'] !== '#')
                                                <a href="{{ $entry['url'] }}" class="fw-bold text-primary font-monospace">
                                                    {{ $entry['reference'] }}
                                                </a>
                                            @else
                                                <span class="fw-bold font-monospace text-dark">{{ $entry['reference'] }}</span>
                                            @endif
                                        </td>
                                        <td class="text-muted">{{ $entry['description'] }}</td>
                                        <td class="text-end text-success fw-semibold">
                                            {{ $entry['debit'] > 0 ? active_currency_symbol() . number_format($entry['debit'], 2) : '—' }}
                                        </td>
                                        <td class="text-end text-danger fw-semibold">
                                            {{ $entry['credit'] > 0 ? active_currency_symbol() . number_format($entry['credit'], 2) : '—' }}
                                        </td>
                                        <td class="text-end fw-bold text-dark font-monospace">
                                            {{ active_currency_symbol() }}{{ number_format($entry['running_balance'], 2) }}
                                        </td>
                                        <td class="text-end pe-3">
                                            @if(!empty($entry['url']) && $entry['url'] !== '#')
                                                <a href="{{ $entry['url'] }}" class="btn btn-xs btn-icon btn-light text-primary border" title="View Document">
                                                    <i class="feather-eye"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="feather-book fs-32 d-block mb-2 text-muted"></i>
                                            No ledger transactions or freight charges recorded yet for this transporter.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="table-light fw-bold fs-12 border-top">
                                <tr>
                                    <td colspan="4" class="ps-3 text-end text-uppercase">Total Statement Summary:</td>
                                    <td class="text-end text-success">{{ active_currency_symbol() }}{{ number_format($stats['total_paid'], 2) }}</td>
                                    <td class="text-end text-danger">{{ active_currency_symbol() }}{{ number_format($stats['total_freight_booked'], 2) }}</td>
                                    <td class="text-end text-primary font-monospace fs-13">
                                        {{ active_currency_symbol() }}{{ number_format($stats['outstanding_payable'], 2) }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </x-ui.odoo-form-ui>
</div>

<!-- Edit Enterprise Transporter Modal Component -->
<x-ui.modal id="editTransporterModal" title="Edit Transporter Master" size="lg" :centered="true" :formAction="route('platform.transporters.update', $transporter)" formMethod="PUT" submitText="Save Master Changes" closeText="Cancel">
    <div class="row g-3">
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="Transporter Name" name="name" :value="$transporter->name" :required="true" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="Transporter Code" name="code" :value="$transporter->code" placeholder="e.g. TRP-001" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="15-Digit Transporter ID (E-Way Bill)" name="transporter_id" :value="$transporter->transporter_id" placeholder="Optional 15-digit E-Way ID" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="GSTIN Number" name="gstin" :value="$transporter->gstin" placeholder="Optional 15-digit GSTIN" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="PAN Number" name="pan_number" :value="$transporter->pan_number" placeholder="10-character PAN Number" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="TDS Section" name="tds_section" :value="$transporter->tds_section ?: '194C'" placeholder="e.g. 194C" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="TDS Rate (%)" name="tds_rate" :value="$transporter->tds_rate ?: 1.00" placeholder="1.00" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="SAC Code" name="sac_code" :value="$transporter->sac_code ?: '996511'" placeholder="996511" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="select" label="Transport Mode" name="transport_mode" :searchable="false">
                <option value="road" @selected(($transporter->transport_mode ?: 'road') === 'road')>Road Transport</option>
                <option value="rail" @selected($transporter->transport_mode === 'rail')>Rail Logistics</option>
                <option value="air" @selected($transporter->transport_mode === 'air')>Air Freight</option>
                <option value="sea" @selected($transporter->transport_mode === 'sea')>Sea Cargo</option>
                <option value="multimodal" @selected($transporter->transport_mode === 'multimodal')>Multimodal</option>
            </x-ui.odoo-form-ui>
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="Payment Terms" name="payment_terms" :value="$transporter->payment_terms ?: 'Net 30 Days'" placeholder="Net 30 Days" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="Bank Name" name="bank_name" :value="$transporter->bank_name" placeholder="Bank Name" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="Branch Name" name="branch_name" :value="$transporter->branch_name" placeholder="Branch Name" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="Account Holder Name" name="account_name" :value="$transporter->account_name" placeholder="Account Name" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="Account Number" name="account_number" :value="$transporter->account_number" placeholder="Account Number" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="IFSC Code" name="ifsc_code" :value="$transporter->ifsc_code" placeholder="IFSC Code" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="Phone Number" name="phone" :value="$transporter->phone" placeholder="Mobile / Landline" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" inputType="email" label="Email Address" name="email" :value="$transporter->email" placeholder="email@domain.com" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="select" label="Status" name="status" :required="true" :searchable="false">
                <option value="active" @selected($transporter->status === 'active')>Active</option>
                <option value="inactive" @selected($transporter->status === 'inactive')>Inactive</option>
            </x-ui.odoo-form-ui>
        </div>
        <div class="col-12">
            <x-ui.odoo-form-ui type="textarea" label="Office / Branch Address" name="address" rows="2" placeholder="Full address...">{{ $transporter->address }}</x-ui.odoo-form-ui>
        </div>
        <div class="col-md-4">
            <x-ui.odoo-form-ui type="input" label="City" name="city" :value="$transporter->city" />
        </div>
        <div class="col-md-4">
            <x-ui.odoo-form-ui type="input" label="State" name="state" :value="$transporter->state" />
        </div>
        <div class="col-md-4">
            <x-ui.odoo-form-ui type="input" label="Pincode" name="pincode" :value="$transporter->pincode" />
        </div>
    </div>
</x-ui.modal>
@endsection
