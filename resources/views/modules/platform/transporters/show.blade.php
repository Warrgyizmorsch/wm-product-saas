@extends('layouts.duralux')

@section('title', __('crm.transporter_360_profile') . ' | ' . $transporter->name)
@section('page-title', __('crm.transporter_360_profile'))
@section('breadcrumb', __('crm.platform_transporters_profile_breadcrumb'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('platform.transporters.index') }}" class="btn btn-light border p-2 d-inline-flex align-items-center justify-content-center" title="{{ __('crm.back_to_transporters') }}">
            <i class="feather-arrow-left fs-16"></i>
        </a>
        <button type="button" class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#editTransporterModal" title="{{ __('crm.edit_transporter_master') }}">
            <i class="feather-edit-2 me-1"></i>{{ __('crm.edit_profile') }}
        </button>
        @if(Route::has('inventory.dispatches.create'))
            <x-ui.button href="{{ route('inventory.dispatches.create', ['transporter_id' => $transporter->id]) }}" variant="primary" icon="feather-send">
                {{ __('crm.create_dispatch_order_btn') }}
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
                            <x-ui.status-badge status="active" :label="__('crm.active_transporter')" dot="true" size="sm" />
                        @else
                            <x-ui.status-badge status="inactive" :label="__('crm.inactive_transporter')" dot="true" size="sm" />
                        @endif

                        @if($transporter->transporter_id)
                            <span class="badge bg-soft-primary text-primary border font-monospace fs-11 px-2.5 py-1">
                                {{ __('crm.eway_id') }}: {{ $transporter->transporter_id }}
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
                            <span><i class="feather-map-pin me-1 text-danger"></i><strong>{{ __('crm.location_label') }}</strong> {{ implode(', ', array_filter([$transporter->city, $transporter->state])) }}</span>
                        @endif
                    </div>

                    @if($transporter->address)
                        <div class="text-muted fs-12 mt-1">
                            <i class="feather-home me-1 text-muted"></i><strong>{{ __('crm.address_label') }}</strong> {{ $transporter->address }} @if($transporter->pincode) - {{ $transporter->pincode }} @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-soft-danger text-danger fs-13 px-3 py-2 fw-bold border">
                    <i class="feather-alert-circle me-1"></i>{{ __('crm.freight_payable') }}: {{ format_currency($stats['outstanding_payable']) }}
                </span>
                <span class="badge bg-soft-warning text-warning fs-13 px-3 py-2 fw-bold border">
                    <i class="feather-clock me-1"></i>{{ $stats['pending_bills_count'] }} {{ __('crm.pending_bills') }}
                </span>
            </div>
        </div>

        {{-- 2. KPI Summary Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">{{ __('crm.freight_booked_billed') }}</div>
                    <div class="fs-18 fw-bold text-dark"><i class="feather-file-text me-1 text-primary"></i>{{ format_currency($stats['total_freight_booked']) }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">{{ __('crm.freight_payments_paid') }}</div>
                    <div class="fs-18 fw-bold text-success"><i class="feather-check-circle me-1"></i>{{ format_currency($stats['total_paid']) }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">{{ __('crm.outstanding_payable') }}</div>
                    <div class="fs-18 fw-bold text-danger"><i class="feather-dollar-sign me-1"></i>{{ format_currency($stats['outstanding_payable']) }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">{{ __('crm.bill_summary_paid_pending') }}</div>
                    <div class="fs-16 fw-bold text-dark">
                        <span class="text-success"><i class="feather-check-circle me-1"></i>{{ $stats['paid_bills_count'] }} {{ __('crm.paid') }}</span>
                        <span class="text-muted mx-1">|</span>
                        <span class="text-danger"><i class="feather-clock me-1"></i>{{ $stats['pending_bills_count'] }} {{ __('crm.pending') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Standard Clean 5 Tabs --}}
        <ul class="nav nav-tabs custom-tabs mb-4 border-bottom" id="transporterTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold fs-13" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab">
                    <i class="feather-info me-1.5"></i>{{ __('crm.profile_master_info_tab') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold fs-13" id="bills-tab" data-bs-toggle="tab" data-bs-target="#bills-pane" type="button" role="tab">
                    <i class="feather-file-text me-1.5 text-primary"></i>{{ __('crm.freight_bills_tab') }} ({{ $freightBills->count() }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold fs-13" id="dispatches-tab" data-bs-toggle="tab" data-bs-target="#dispatches-pane" type="button" role="tab">
                    <i class="feather-truck me-1.5 text-info"></i>{{ __('crm.dispatch_orders_tab') }} ({{ number_format($stats['total_dispatches']) }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold fs-13" id="vehicles-tab" data-bs-toggle="tab" data-bs-target="#vehicles-pane" type="button" role="tab">
                    <i class="feather-navigation me-1.5"></i>{{ __('crm.vehicles_lr_logs_tab') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold fs-13 text-primary" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger-pane" type="button" role="tab">
                    <i class="feather-book me-1.5"></i>{{ __('crm.transporter_ledger_tab') }}
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
                                <h6 class="fw-bold text-primary mb-0 fs-13"><i class="feather-briefcase me-2"></i>{{ __('crm.transporter_master_tax') }}</h6>
                            </div>
                            <div class="card-body fs-13">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 45%;">{{ __('crm.transporter_name_label') }}</td>
                                            <td class="fw-bold text-dark">{{ $transporter->name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.master_code') }}</td>
                                            <td class="font-monospace text-primary fw-bold">{{ $transporter->code ?: 'TRP-' . str_pad($transporter->id, 4, '0', STR_PAD_LEFT) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.eway_transporter_id') }}</td>
                                            <td>
                                                @if($transporter->transporter_id)
                                                    <span class="badge bg-light text-dark font-monospace border px-2 py-1">{{ $transporter->transporter_id }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.gstin_number') }}</td>
                                            <td>
                                                @if($transporter->gstin)
                                                    <span class="font-monospace text-primary fw-bold">{{ $transporter->gstin }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.pan_number') }}</td>
                                            <td class="font-monospace text-dark fw-semibold">{{ $transporter->pan_number ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.sac_code') }}</td>
                                            <td class="font-monospace text-dark">{{ $transporter->sac_code ?: '996511' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.tds_section_rate') }}</td>
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
                                <h6 class="fw-bold text-primary mb-0 fs-13"><i class="feather-credit-card me-2"></i>{{ __('crm.banking_payout') }}</h6>
                            </div>
                            <div class="card-body fs-13">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 45%;">{{ __('crm.bank_name_label') }}</td>
                                            <td class="fw-bold text-dark">{{ $transporter->bank_name ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.branch_name_label') }}</td>
                                            <td>{{ $transporter->branch_name ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.account_holder') }}</td>
                                            <td class="fw-semibold text-dark">{{ $transporter->account_name ?: ($transporter->name) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.account_number_label') }}</td>
                                            <td class="font-monospace text-primary fw-bold">{{ $transporter->account_number ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.ifsc_code_label') }}</td>
                                            <td class="font-monospace text-dark fw-semibold">{{ $transporter->ifsc_code ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.payment_credit_terms') }}</td>
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
                                <h6 class="fw-bold text-primary mb-0 fs-13"><i class="feather-truck me-2"></i>{{ __('crm.fleet_capabilities_zones') }}</h6>
                            </div>
                            <div class="card-body fs-13">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 45%;">{{ __('crm.transport_mode_label') }}</td>
                                            <td><span class="badge bg-soft-primary text-primary border text-uppercase">{{ $transporter->transport_mode ?: 'Road Transport' }}</span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.fleet_types_operated') }}</td>
                                            <td class="fw-semibold text-dark">{{ $transporter->fleet_type ?: 'Containers, Open Trucks, Trailers' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.serviceable_zones_routes') }}</td>
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
                                <h6 class="fw-bold text-primary mb-0 fs-13"><i class="feather-users me-2"></i>{{ __('crm.key_contacts') }}</h6>
                            </div>
                            <div class="card-body fs-13">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 45%;">{{ __('crm.coordinator_label') }}</td>
                                            <td class="fw-bold text-dark">{{ $transporter->contact_person_name ?: 'Primary Dispatch Coordinator' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.mobile_phone_label') }}</td>
                                            <td>{{ $transporter->contact_person_phone ?: ($transporter->phone ?: '—') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('crm.email_address_label') }}</td>
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

            {{-- TAB 2: FREIGHT BILLS --}}
            <div class="tab-pane fade" id="bills-pane" role="tabpanel">

                {{-- Unbilled Dispatches Section --}}
                @if(isset($unbilledDispatches) && $unbilledDispatches->isNotEmpty())
                    <div class="card border border-warning shadow-none mb-4">
                        <div class="card-header bg-soft-warning d-flex align-items-center justify-content-between py-2.5">
                            <h6 class="fw-bold text-dark mb-0"><i class="feather-alert-circle me-1.5 text-warning"></i>{{ __('crm.unbilled_dispatches') }}</h6>
                            <span class="badge bg-warning text-dark font-monospace px-2.5 py-1">
                                {{ __('crm.total_pending_freight') }}: {{ format_currency($unbilledFreightTotal) }}
                            </span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 fs-12">
                                <thead class="table-light fs-11 text-uppercase fw-bold text-muted">
                                    <tr>
                                        <th class="ps-3">{{ __('crm.dispatch_no') }}</th>
                                        <th>{{ __('crm.date') }}</th>
                                        <th>{{ __('crm.lr_number_col') }}</th>
                                        <th>{{ __('crm.vehicle_no_col') }}</th>
                                        <th class="text-end">{{ __('crm.agreed_freight_col') }}</th>
                                        <th class="text-center">{{ __('crm.status') }}</th>
                                        <th class="text-end pe-3">{{ __('crm.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($unbilledDispatches as $ud)
                                        <tr>
                                            <td class="ps-3 font-monospace fw-bold text-dark">{{ $ud['dispatch_number'] }}</td>
                                            <td class="text-muted font-monospace">{{ \Carbon\Carbon::parse($ud['dispatch_date'])->format('d M Y') }}</td>
                                            <td>
                                                <span class="badge bg-light text-dark border font-monospace px-2 py-1">{{ $ud['lr_number'] }}</span>
                                            </td>
                                            <td>{{ $ud['vehicle_number'] }}</td>
                                            <td class="text-end fw-bold text-dark font-monospace fs-13">
                                                {{ format_currency($ud['freight_amount']) }}
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-soft-warning text-warning border px-2.5 py-1 fs-11">
                                                    <i class="feather-clock me-1"></i>{{ __('crm.unbilled_badge') }}
                                                </span>
                                            </td>
                                            <td class="text-end pe-3">
                                                <a href="{{ $ud['create_bill_url'] }}" class="btn btn-xs btn-primary shadow-sm" title="{{ __('crm.create_freight_bill') }}">
                                                    <i class="feather-file-plus me-1"></i>{{ __('crm.create_freight_bill') }}
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Posted Bills Table --}}
                <div class="card border shadow-none">
                    <div class="card-header bg-light d-flex align-items-center justify-content-between py-2.5">
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-file-text me-1 text-primary"></i>{{ __('crm.posted_freight_bills') }}</h6>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-soft-success text-success border fs-11 px-2.5 py-1">
                                <i class="feather-check-circle me-1"></i>{{ $stats['paid_bills_count'] }} {{ __('crm.paid') }}
                            </span>
                            <span class="badge bg-soft-danger text-danger border fs-11 px-2.5 py-1">
                                <i class="feather-alert-triangle me-1"></i>{{ $stats['pending_bills_count'] }} {{ __('crm.pending') }}
                            </span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-12">
                            <thead class="table-light fs-11 text-uppercase fw-bold text-muted">
                                <tr>
                                    <th class="ps-3" style="width: 15%;">{{ __('crm.bill_ref_no') }}</th>
                                    <th style="width: 11%;">{{ __('crm.bill_date') }}</th>
                                    <th style="width: 16%;">{{ __('crm.category_source') }}</th>
                                    <th style="width: 18%;">{{ __('crm.particulars') }}</th>
                                    <th class="text-end" style="width: 10%;">{{ __('crm.bill_amount') }}</th>
                                    <th class="text-end" style="width: 10%;">{{ __('crm.paid_amount') }}</th>
                                    <th class="text-end" style="width: 10%;">{{ __('crm.balance_due') }}</th>
                                    <th class="text-center" style="width: 10%;">{{ __('crm.status') }}</th>
                                    <th class="text-end pe-3" style="width: 10%;">{{ __('crm.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12 text-dark">
                                @forelse($freightBills as $fb)
                                    <tr>
                                        <td class="ps-3 font-monospace">
                                            @if(!empty($fb['url']) && $fb['url'] !== '#')
                                                <a href="{{ $fb['url'] }}" class="fw-bold text-primary">{{ $fb['bill_number'] }}</a>
                                            @else
                                                <span class="fw-bold text-dark">{{ $fb['bill_number'] }}</span>
                                            @endif
                                        </td>
                                        <td class="text-muted font-monospace">{{ \Carbon\Carbon::parse($fb['date'])->format('d M Y') }}</td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1 fs-11">{{ $fb['type'] }}</span>
                                        </td>
                                        <td class="text-muted">{{ $fb['reference'] }}</td>
                                        <td class="text-end fw-bold text-dark font-monospace">{{ format_currency($fb['total_amount']) }}</td>
                                        <td class="text-end text-success fw-semibold font-monospace">{{ format_currency($fb['paid_amount']) }}</td>
                                        <td class="text-end text-danger fw-bold font-monospace">{{ format_currency($fb['balance_due']) }}</td>
                                        <td class="text-center">
                                            @if($fb['status'] === 'paid' || $fb['balance_due'] <= 0)
                                                <span class="badge bg-soft-success text-success px-2.5 py-1 fs-11 border">
                                                    <i class="feather-check-circle me-1"></i>{{ __('crm.paid') }}
                                                </span>
                                            @elseif($fb['status'] === 'partially_paid')
                                                <span class="badge bg-soft-warning text-warning px-2.5 py-1 fs-11 border">
                                                    <i class="feather-clock me-1"></i>{{ __('crm.partially_paid') }}
                                                </span>
                                            @else
                                                <span class="badge bg-soft-danger text-danger px-2.5 py-1 fs-11 border">
                                                    <i class="feather-x-circle me-1"></i>{{ __('crm.unpaid') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            @if(!empty($fb['url']) && $fb['url'] !== '#')
                                                <a href="{{ $fb['url'] }}" class="btn btn-xs btn-light text-primary border" title="{{ __('crm.view_bill') }}">
                                                    <i class="feather-eye me-1"></i>{{ __('crm.view') }}
                                                </a>
                                            @else
                                                <span class="btn btn-xs btn-light text-muted border disabled" style="opacity: 0.6;">
                                                    <i class="feather-eye me-1"></i>{{ __('crm.view') }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">
                                            <i class="feather-file-text fs-32 d-block mb-2 text-muted"></i>
                                            {{ __('crm.no_freight_bills') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="table-light fw-bold fs-12 border-top">
                                <tr>
                                    <td colspan="4" class="ps-3 text-end text-uppercase">{{ __('crm.summary_totals') }}</td>
                                    <td class="text-end text-dark font-monospace">{{ format_currency($stats['total_freight_booked']) }}</td>
                                    <td class="text-end text-success font-monospace">{{ format_currency($stats['total_paid']) }}</td>
                                    <td class="text-end text-danger font-monospace fs-13">{{ format_currency($stats['outstanding_payable']) }}</td>
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
                                    <th class="ps-3">{{ __('crm.dispatch_no') }}</th>
                                    <th>{{ __('crm.date') }}</th>
                                    <th>{{ __('crm.source_warehouse') }}</th>
                                    <th>{{ __('crm.lr_number_col') }}</th>
                                    <th>{{ __('crm.vehicle_no_col') }}</th>
                                    <th>{{ __('crm.gross_weight') }}</th>
                                    <th>{{ __('crm.freight_col') }} ({{ active_currency_symbol() }})</th>
                                    <th>{{ __('crm.status') }}</th>
                                    <th class="text-end pe-3">{{ __('crm.actions') }}</th>
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
                                        <td class="fw-bold text-dark">{{ format_currency($dispatch->freight_amount) }}</td>
                                        <td>
                                            @if($dispatch->status === 'delivered')
                                                <span class="badge bg-soft-success text-success">{{ __('crm.delivered') }}</span>
                                            @elseif($dispatch->status === 'in_transit')
                                                <span class="badge bg-soft-warning text-warning">{{ __('crm.in_transit') }}</span>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary">{{ ucfirst((string) $dispatch->status) }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            @if(Route::has('inventory.dispatches.show'))
                                                <a href="{{ route('inventory.dispatches.show', $dispatch->id) }}" class="btn btn-xs btn-light text-primary border" title="{{ __('crm.view') }}">
                                                    <i class="feather-eye me-1"></i>{{ __('crm.view') }}
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="feather-send fs-28 d-block mb-1 text-muted"></i>
                                            {{ __('crm.no_dispatches_for_transporter') }}
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
                                    <th class="ps-3">{{ __('crm.vehicle_number_col') }}</th>
                                    <th>{{ __('crm.lr_number_col') }}</th>
                                    <th>{{ __('crm.driver_name_col') }}</th>
                                    <th>{{ __('crm.driver_phone_col') }}</th>
                                    <th>{{ __('crm.associated_dispatch') }}</th>
                                    <th>{{ __('crm.date') }}</th>
                                    <th>{{ __('crm.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dispatches->filter(fn($d) => $d->vehicle_number || $d->lr_number || $d->driver_name) as $d)
                                    <tr>
                                        <td class="ps-3 fw-bold text-dark font-monospace">{{ $d->vehicle_number ?: '—' }}</td>
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
                                                <span class="badge bg-soft-success text-success">{{ __('crm.delivered') }}</span>
                                            @elseif($d->status === 'in_transit')
                                                <span class="badge bg-soft-warning text-warning">{{ __('crm.in_transit') }}</span>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary">{{ ucfirst((string) $d->status) }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="feather-truck fs-28 d-block mb-1 text-muted"></i>
                                            {{ __('crm.no_vehicle_logs') }}
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
                    <div class="card-header bg-light py-2.5">
                        <h6 class="fw-bold text-primary mb-0"><i class="feather-book me-1"></i>{{ __('crm.ledger_statement') }}</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-12">
                            <thead class="table-light fs-11 text-uppercase fw-bold text-muted">
                                <tr>
                                    <th class="ps-3" style="width: 11%;">{{ __('crm.date') }}</th>
                                    <th style="width: 14%;">{{ __('crm.type') }}</th>
                                    <th style="width: 16%;">{{ __('crm.reference_no_col') }}</th>
                                    <th style="width: 22%;">{{ __('crm.particulars_description') }}</th>
                                    <th class="text-end" style="width: 10%;">{{ __('crm.debit_paid') }}</th>
                                    <th class="text-end" style="width: 10%;">{{ __('crm.credit_billed') }}</th>
                                    <th class="text-end" style="width: 10%;">{{ __('crm.running_balance') }}</th>
                                    <th class="text-end pe-3" style="width: 7%;">{{ __('crm.actions') }}</th>
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
                                                <a href="{{ $entry['url'] }}" class="fw-bold text-primary font-monospace">{{ $entry['reference'] }}</a>
                                            @else
                                                <span class="fw-bold font-monospace text-dark">{{ $entry['reference'] }}</span>
                                            @endif
                                        </td>
                                        <td class="text-muted">{{ $entry['description'] }}</td>
                                        <td class="text-end text-success fw-semibold">
                                            {{ $entry['debit'] > 0 ? format_currency($entry['debit']) : '—' }}
                                        </td>
                                        <td class="text-end text-danger fw-semibold">
                                            {{ $entry['credit'] > 0 ? format_currency($entry['credit']) : '—' }}
                                        </td>
                                        <td class="text-end fw-bold text-dark font-monospace">
                                            {{ format_currency($entry['running_balance']) }}
                                        </td>
                                        <td class="text-end pe-3">
                                            @if(!empty($entry['url']) && $entry['url'] !== '#')
                                                <a href="{{ $entry['url'] }}" class="btn btn-xs btn-icon btn-light text-primary border" title="{{ __('crm.view') }}">
                                                    <i class="feather-eye"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="feather-book fs-32 d-block mb-2 text-muted"></i>
                                            {{ __('crm.no_ledger_transactions') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="table-light fw-bold fs-12 border-top">
                                <tr>
                                    <td colspan="4" class="ps-3 text-end text-uppercase">{{ __('crm.total_statement_summary') }}</td>
                                    <td class="text-end text-success">{{ format_currency($stats['total_paid']) }}</td>
                                    <td class="text-end text-danger">{{ format_currency($stats['total_freight_booked']) }}</td>
                                    <td class="text-end text-primary font-monospace fs-13">
                                        {{ format_currency($stats['outstanding_payable']) }}
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
<x-ui.modal id="editTransporterModal" :title="__('crm.edit_transporter_master')" size="lg" :centered="true" :formAction="route('platform.transporters.update', $transporter)" formMethod="PUT" :submitText="__('crm.save_master_changes')" :closeText="__('crm.cancel')">
    <div class="row g-3">
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_name_field')" name="name" :value="$transporter->name" :required="true" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_master_code')" name="code" :value="$transporter->code" placeholder="e.g. TRP-001" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_id_eway')" name="transporter_id" :value="$transporter->transporter_id" placeholder="Optional 15-digit E-Way ID" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" :label="__('crm.gstin_number_field')" name="gstin" :value="$transporter->gstin" placeholder="Optional 15-digit GSTIN" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" :label="__('crm.pan_number_field')" name="pan_number" :value="$transporter->pan_number" placeholder="10-character PAN Number" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" :label="__('crm.tds_section_field')" name="tds_section" :value="$transporter->tds_section ?: '194C'" placeholder="e.g. 194C" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" :label="__('crm.tds_rate_field')" name="tds_rate" :value="$transporter->tds_rate ?: 1.00" placeholder="1.00" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" :label="__('crm.sac_code_field')" name="sac_code" :value="$transporter->sac_code ?: '996511'" placeholder="996511" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="select" :label="__('crm.transport_mode')" name="transport_mode" :searchable="false">
                <option value="road" @selected(($transporter->transport_mode ?: 'road') === 'road')>{{ __('crm.road_transport') }}</option>
                <option value="rail" @selected($transporter->transport_mode === 'rail')>{{ __('crm.rail_logistics') }}</option>
                <option value="air" @selected($transporter->transport_mode === 'air')>{{ __('crm.air_freight') }}</option>
                <option value="sea" @selected($transporter->transport_mode === 'sea')>{{ __('crm.sea_cargo') }}</option>
                <option value="multimodal" @selected($transporter->transport_mode === 'multimodal')>{{ __('crm.multimodal') }}</option>
            </x-ui.odoo-form-ui>
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" :label="__('crm.payment_credit_terms_field')" name="payment_terms" :value="$transporter->payment_terms ?: 'Net 30 Days'" placeholder="Net 30 Days" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" :label="__('crm.bank_name_field')" name="bank_name" :value="$transporter->bank_name" :placeholder="__('crm.bank_name_field')" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" :label="__('crm.branch_name_field')" name="branch_name" :value="$transporter->branch_name" :placeholder="__('crm.branch_name_field')" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" :label="__('crm.account_holder_name')" name="account_name" :value="$transporter->account_name" :placeholder="__('crm.account_holder_name')" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" :label="__('crm.bank_account_number')" name="account_number" :value="$transporter->account_number" :placeholder="__('crm.bank_account_number')" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" :label="__('crm.ifsc_swift_code')" name="ifsc_code" :value="$transporter->ifsc_code" placeholder="IFSC Code" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" :label="__('crm.phone_mobile_col')" name="phone" :value="$transporter->phone" placeholder="Mobile / Landline" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" inputType="email" :label="__('crm.official_email_address')" name="email" :value="$transporter->email" placeholder="email@domain.com" />
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="select" :label="__('crm.active_status')" name="status" :required="true" :searchable="false">
                <option value="active" @selected($transporter->status === 'active')>{{ __('crm.active') }}</option>
                <option value="inactive" @selected($transporter->status === 'inactive')>{{ __('crm.inactive') }}</option>
            </x-ui.odoo-form-ui>
        </div>
        <div class="col-12">
            <x-ui.odoo-form-ui type="textarea" :label="__('crm.registered_address')" name="address" rows="2" placeholder="Full address...">{{ $transporter->address }}</x-ui.odoo-form-ui>
        </div>
        <div class="col-md-4">
            <x-ui.odoo-form-ui type="input" :label="__('crm.city')" name="city" :value="$transporter->city" />
        </div>
        <div class="col-md-4">
            <x-ui.odoo-form-ui type="input" :label="__('crm.state')" name="state" :value="$transporter->state" />
        </div>
        <div class="col-md-4">
            <x-ui.odoo-form-ui type="input" :label="__('crm.pincode')" name="pincode" :value="$transporter->pincode" />
        </div>
    </div>
</x-ui.modal>
@endsection
