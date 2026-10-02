@extends('layouts.duralux')

@section('title', __('visitor.visitor_gate_pass') . ' - ' . $pass->pass_number . ' | SaaS ERP')
@section('page-title', __('visitor.visitor_gate_pass'))
@section('breadcrumb', __('visitor.visitor_management') . ' > ' . __('visitor.visitor_passes') . ' > ' . $pass->pass_number)

@push('styles')
<style>
    /* ─── Enterprise Single Pass Layout ─────────────────── */
    .visitor-pass-container {
        max-width: 960px;
        margin: 0 auto;
    }

    /* ─── Odoo / Zoho Style Status Chevron Pipeline ───────── */
    .visitor-pipeline {
        display: inline-flex;
        align-items: center;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #cbd5e1;
        background-color: #f8fafc;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .visitor-pipeline .pipeline-step {
        position: relative;
        padding: 6px 16px 6px 22px;
        background-color: #f8fafc;
        color: #64748b;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
        transition: all 0.2s ease;
    }
    .visitor-pipeline .pipeline-step:first-child {
        padding-left: 16px;
    }
    .visitor-pipeline .pipeline-step::after {
        content: "";
        position: absolute;
        top: 0;
        right: -10px;
        width: 0;
        height: 0;
        border-top: 15px solid transparent;
        border-bottom: 15px solid transparent;
        border-left: 10px solid #f8fafc;
        z-index: 10;
        transition: all 0.2s ease;
    }
    .visitor-pipeline .pipeline-step::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 0;
        height: 0;
        border-top: 15px solid transparent;
        border-bottom: 15px solid transparent;
        border-left: 10px solid #ffffff;
        z-index: 5;
    }
    .visitor-pipeline .pipeline-step:first-child::before {
        display: none;
    }
    .visitor-pipeline .pipeline-step.active {
        background-color: var(--bs-primary);
        color: #ffffff;
    }
    .visitor-pipeline .pipeline-step.active::after {
        border-left-color: var(--bs-primary);
    }
    .visitor-pipeline .pipeline-step.completed {
        background-color: #e2e8f0;
        color: #334155;
    }
    .visitor-pipeline .pipeline-step.completed::after {
        border-left-color: #e2e8f0;
    }
    .visitor-pipeline .pipeline-step.danger-active {
        background-color: #dc2626;
        color: #ffffff;
    }
    .visitor-pipeline .pipeline-step.danger-active::after {
        border-left-color: #dc2626;
    }

    /* ─── Pass Master Card ────────────────────────────────── */
    .erp-pass-card {
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }
    .erp-pass-header {
        background: linear-gradient(135deg, color-mix(in srgb, var(--bs-primary) 70%, #0f172a) 0%, var(--bs-primary) 65%, color-mix(in srgb, var(--bs-primary) 85%, #ffffff) 100%);
        color: #ffffff;
        padding: 22px 28px;
        position: relative;
    }
    .erp-pass-header::before {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.22), transparent 55%);
        pointer-events: none;
    }

    /* ─── Avatar & Monograms ──────────────────────────────── */
    .visitor-pro-avatar {
        width: 96px;
        height: 96px;
        border-radius: 14px;
        object-fit: cover;
        border: 3px solid #ffffff;
        box-shadow: 0 8px 20px -4px rgba(0, 0, 0, 0.12);
    }
    .visitor-pro-placeholder {
        width: 96px;
        height: 96px;
        border-radius: 14px;
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        font-weight: 800;
        color: #475569;
        border: 3px solid #ffffff;
        box-shadow: 0 8px 20px -4px rgba(0, 0, 0, 0.12);
    }

    /* ─── Metric Tile ─────────────────────────────────────── */
    .metric-tile {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px 14px;
        height: 100%;
        transition: all 0.2s ease;
    }
    .metric-tile:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
    }
    .metric-tile .tile-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        margin-bottom: 3px;
    }
    .metric-tile .tile-value {
        font-size: 13.5px;
        font-weight: 700;
        color: #1e293b;
    }

    /* ─── QR Code Container Inside Card ───────────────────── */
    .pass-qr-box {
        background: #ffffff;
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        padding: 10px;
        display: inline-block;
        text-align: center;
        transition: all 0.2s ease;
    }
    .pass-qr-box:hover {
        border-color: var(--bs-primary);
        box-shadow: 0 4px 15px rgba(var(--bs-primary-rgb), 0.15);
    }

    /* ─── Print Styles ────────────────────────────────────── */
    @page {
        size: A4 portrait;
        margin: 8mm 10mm;
    }

    @media print {
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            background: #ffffff !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        body * {
            visibility: hidden;
        }
        #printableGatePass, #printableGatePass * {
            visibility: visible;
        }
        #printableGatePass {
            position: absolute;
            left: 0;
            top: 0;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            background: #ffffff !important;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        #printableGatePass .erp-pass-header {
            padding: 14px 20px !important;
            border-radius: 0 !important;
            margin: 0 !important;
        }
        #printableGatePass .p-4 {
            padding: 14px 18px !important;
        }
        #printableGatePass .visitor-pro-avatar,
        #printableGatePass .visitor-pro-placeholder {
            width: 76px !important;
            height: 76px !important;
            font-size: 26px !important;
            border-radius: 10px !important;
        }
        #printableGatePass .pass-qr-box {
            padding: 6px !important;
        }
        #printableGatePass .pass-qr-box img {
            width: 90px !important;
            height: 90px !important;
        }
        #printableGatePass .mb-4 {
            margin-bottom: 10px !important;
        }
        #printableGatePass .pb-4 {
            padding-bottom: 10px !important;
        }
        #printableGatePass .metric-tile {
            padding: 7px 10px !important;
            background: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
        }
        #printableGatePass .row.pt-3 {
            padding-top: 10px !important;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        #printableGatePass table {
            font-size: 11px !important;
        }
        .no-print {
            display: none !important;
        }
    }
</style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2 no-print">
        <x-ui.button href="{{ route('visitor.index') }}" variant="primary" icon="feather-arrow-left">
            {{ __('visitor.back_to_list') }}
        </x-ui.button>
        <x-ui.button type="button" variant="primary" icon="feather-printer" onclick="window.print()">
            {{ __('visitor.print_pass') }}
        </x-ui.button>
    </div>
@endsection

@section('content')
<div class="visitor-pass-container">

    <!-- Flash Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center no-print" role="alert">
            <i class="feather-check-circle fs-18 text-success me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center no-print" role="alert">
            <i class="feather-alert-octagon fs-18 text-danger me-2"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- 1. Top Enterprise Status Pipeline Toolbar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 no-print">
        <div class="d-flex align-items-center gap-2">
            <span class="fs-13 fw-bold text-dark font-monospace">
                <i class="feather-tag text-primary me-1"></i> {{ $pass->pass_number }}
            </span>
            <span class="badge bg-light text-dark border px-2 py-0.5 fs-11">
                {{ $pass->visitor_type ?? 'Client' }}
            </span>
        </div>

        <!-- Step Chevron Flow -->
        <div class="visitor-pipeline">
            @php
                $currentStatus = $pass->status;
                $isPreReg = in_array($currentStatus, ['Expected', 'Pre-registered']);
                $isArrived = $currentStatus === 'Arrived';
                $isWaitingApproval = $currentStatus === 'Waiting Approval';
                $isApproved = $currentStatus === 'Approved';
                $isCheckedIn = $currentStatus === 'Checked-In';
                $isMeeting = $currentStatus === 'Meeting in Progress';
                $isCheckedOut = $currentStatus === 'Checked-Out';
                $isOverstayed = $currentStatus === 'Overstayed';
                $isDenied = in_array($currentStatus, ['Denied', 'Rejected']);
            @endphp

            <div class="pipeline-step {{ $isPreReg ? 'active' : 'completed' }}">
                <i class="feather-calendar"></i> 1. Expected
            </div>
            <div class="pipeline-step {{ $isArrived ? 'active' : ($isWaitingApproval || $isApproved || $isCheckedIn || $isMeeting || $isCheckedOut || $isOverstayed ? 'completed' : '') }}">
                <i class="feather-map-pin"></i> 2. Gate Arrival
            </div>
            <div class="pipeline-step {{ $isWaitingApproval ? 'active' : ($isApproved || $isCheckedIn || $isMeeting || $isCheckedOut || $isOverstayed ? 'completed' : '') }}">
                <i class="feather-shield"></i> 3. Host Approval
            </div>
            <div class="pipeline-step {{ $isCheckedIn || $isOverstayed ? 'active' : ($isMeeting || $isCheckedOut ? 'completed' : '') }}">
                <i class="feather-log-in"></i> 4. Checked In
            </div>
            <div class="pipeline-step {{ $isMeeting ? 'active' : ($isCheckedOut ? 'completed' : '') }}">
                <i class="feather-users"></i> 5. In Meeting
            </div>
            <div class="pipeline-step {{ $isCheckedOut ? 'active' : '' }}">
                <i class="feather-log-out"></i> 6. Checked Out
            </div>
            @if($isDenied)
                <div class="pipeline-step danger-active">
                    <i class="feather-slash"></i> Denied
                </div>
            @endif
        </div>
    </div>

    <!-- 2. Single Master Pass Card -->
    <div class="erp-pass-card mb-4" id="printableGatePass">
        
        <!-- Header -->
        <div class="erp-pass-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-white/20 text-white border border-white/25 px-2.5 py-1 text-uppercase fw-bold fs-11 tracking-wider" style="backdrop-filter: blur(4px);">
                        {{ __('visitor.visitor_gate_pass') }}
                    </span>
                    <span class="badge bg-white text-dark px-2 py-0.5 fs-11 fw-semibold">
                        {{ $pass->entry_type ?? 'Walk-in' }}
                    </span>
                    @if($pass->gate_number)
                        <span class="badge bg-white/20 text-white border border-white/20 px-2 py-0.5 fs-11">
                            <i class="feather-navigation me-1"></i> {{ $pass->gate_number }}
                        </span>
                    @endif
                </div>
                <h3 class="mb-0 text-white font-monospace fw-bold tracking-tight">
                    {{ $pass->pass_number }}
                </h3>
            </div>
            <div class="text-end">
                @if($pass->status === 'Waiting Approval')
                    <span class="badge bg-warning text-dark fs-12 px-3 py-2 fw-bold shadow-sm">
                        <i class="feather-clock me-1"></i> {{ __('visitor.statuses.Waiting Approval') }}
                    </span>
                @elseif($pass->status === 'Approved')
                    <span class="badge bg-success fs-12 px-3 py-2 fw-bold shadow-sm">
                        <i class="feather-check-circle me-1"></i> {{ __('visitor.statuses.Approved') }}
                    </span>
                @elseif($pass->status === 'Rejected' || $pass->status === 'Denied')
                    <span class="badge bg-danger fs-12 px-3 py-2 fw-bold shadow-sm">
                        <i class="feather-slash me-1"></i> {{ __('visitor.statuses.' . $pass->status, [], null) ?? $pass->status }}
                    </span>
                @elseif($pass->status === 'Checked-In')
                    <span class="badge bg-success fs-12 px-3 py-2 fw-bold shadow-sm">
                        <i class="feather-log-in me-1"></i> {{ __('visitor.statuses.Checked-In') }}
                    </span>
                @elseif($pass->status === 'Meeting in Progress')
                    <span class="badge bg-info fs-12 px-3 py-2 fw-bold shadow-sm">
                        <i class="feather-users me-1"></i> {{ __('visitor.statuses.Meeting in Progress') }}
                    </span>
                @elseif($pass->status === 'Overstayed')
                    <span class="badge bg-danger fs-12 px-3 py-2 fw-bold shadow-sm">
                        <i class="feather-alert-triangle me-1"></i> {{ __('visitor.statuses.Overstayed') }}
                    </span>
                @elseif($pass->status === 'Checked-Out')
                    <span class="badge bg-secondary fs-12 px-3 py-2 fw-bold shadow-sm">
                        <i class="feather-log-out me-1"></i> {{ __('visitor.statuses.Checked-Out') }}
                    </span>
                @else
                    <span class="badge bg-primary text-white fs-12 px-3 py-2 fw-bold shadow-sm">
                        {{ __('visitor.statuses.' . $pass->status, [], null) ?? $pass->status }}
                    </span>
                @endif
            </div>
        </div>

        <!-- Host Approval Pending Banner -->
        @if($pass->status === 'Waiting Approval')
            <div class="bg-warning-subtle border-bottom border-warning p-3 d-flex flex-wrap align-items-center justify-content-between gap-2 no-print">
                <div class="d-flex align-items-center">
                    <div class="bg-warning text-dark p-2 rounded-circle me-2.5 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="feather-clock fs-16"></i>
                    </div>
                    <div>
                        <strong class="d-block text-dark fs-13">{{ __('visitor.pending_approvals') }}</strong>
                        <span class="fs-12 text-muted">{{ __('visitor.host_approval_pending') }}</span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <form action="{{ route('visitor.approvals.approve', $pass->id) }}" method="POST" class="d-inline">
                        @csrf
                        <x-ui.button type="submit" variant="success" size="sm" icon="feather-check">
                            {{ __('visitor.approve') }}
                        </x-ui.button>
                    </form>
                    <x-ui.button type="button" variant="danger" size="sm" icon="feather-x" onclick="openRejectModalShow({{ $pass->id }})">
                        {{ __('visitor.reject') }}
                    </x-ui.button>
                </div>
            </div>
        @endif

        <!-- Blacklist Security Alert Banner -->
        @if($pass->visitor?->is_blacklisted)
            <div class="bg-danger-subtle border-bottom border-danger p-3 d-flex flex-wrap align-items-center justify-content-between gap-2 no-print">
                <div class="d-flex align-items-center">
                    <div class="bg-danger text-white p-2 rounded-circle me-2.5 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="feather-slash fs-16"></i>
                    </div>
                    <div>
                        <strong class="d-block text-danger fs-13">{{ __('visitor.blacklist_warning_title') }}</strong>
                        <span class="fs-12 text-dark">
                            <strong>{{ __('visitor.blacklist_reason') }}:</strong> {{ $pass->visitor->blacklist_reason ?? 'Security restriction' }}
                        </span>
                    </div>
                </div>
                <form method="POST" action="{{ route('visitor.visitors.toggle-blacklist', $pass->visitor_id) }}" onsubmit="return confirm('Remove this visitor from blacklist?')">
                    @csrf
                    <x-ui.button type="submit" variant="light" size="sm" icon="feather-check-circle" class="border-danger text-danger">
                        {{ __('visitor.unblock_visitor') }}
                    </x-ui.button>
                </form>
            </div>
        @endif

        <div class="p-4">
            
            <!-- Visitor Profile Row (Avatar + Details + QR Code together inside card) -->
            <div class="row align-items-center mb-4 pb-4 border-bottom g-3">
                <!-- Avatar Column -->
                <div class="col-auto">
                    <div class="position-relative">
                        @if(!empty($pass->visitor->photo_url))
                            <img src="{{ $pass->visitor->photo_url }}" alt="{{ $pass->visitor->full_name }}" class="visitor-pro-avatar">
                        @else
                            <div class="visitor-pro-placeholder">
                                {{ strtoupper(substr($pass->visitor->full_name ?? 'V', 0, 1)) }}
                            </div>
                        @endif
                        @if($pass->visitor?->is_blacklisted)
                            <span class="position-absolute bottom-0 end-0 bg-danger text-white rounded-circle p-1 border border-2 border-white shadow-sm" title="Blacklisted">
                                <i class="feather-slash fs-11 d-block"></i>
                            </span>
                        @else
                            <span class="position-absolute bottom-0 end-0 bg-success text-white rounded-circle p-1 border border-2 border-white shadow-sm" title="Active">
                                <i class="feather-check fs-11 d-block"></i>
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Info Column -->
                <div class="col">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h4 class="fw-bold text-dark mb-0 font-display">
                            {{ $pass->visitor->full_name ?? 'N/A' }}
                        </h4>
                        @if($pass->visitor?->is_blacklisted)
                            <x-ui.status-badge status="blocked" :label="__('visitor.blacklisted')" :dot="true" />
                        @endif
                        @if($pass->id_verification_status === 'Verified')
                            <span class="badge bg-success-subtle text-success border border-success-subtle fs-11 px-2 py-0.5 fw-semibold">
                                <i class="feather-check-circle me-1"></i> {{ __('visitor.id_verified') }}
                            </span>
                        @endif
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-3 text-muted fs-13 mb-2">
                        <a href="tel:{{ $pass->visitor->phone ?? '' }}" class="text-muted text-decoration-none hover-primary">
                            <i class="feather-phone text-primary me-1"></i> {{ $pass->visitor->phone ?? '—' }}
                        </a>
                        @if(!empty($pass->visitor->email))
                            <span class="text-slate-300">•</span>
                            <a href="mailto:{{ $pass->visitor->email }}" class="text-muted text-decoration-none hover-primary">
                                <i class="feather-mail text-primary me-1"></i> {{ $pass->visitor->email }}
                            </a>
                        @endif
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 fs-12">
                        @if(!empty($pass->visitor->company_name))
                            <span class="badge bg-light text-dark border px-2.5 py-1">
                                <i class="feather-briefcase text-muted me-1"></i> <strong>{{ $pass->visitor->company_name }}</strong>
                            </span>
                        @endif
                        @if(!empty($pass->visitor->designation))
                            <span class="text-muted">({{ $pass->visitor->designation }})</span>
                        @endif
                        @if($pass->id_proof_type)
                            <span class="badge bg-light text-muted border px-2 py-1">
                                <i class="feather-credit-card me-1"></i> {{ $pass->id_proof_type }}: <span class="font-monospace text-dark fw-bold">{{ $pass->id_proof_number ?? 'Verified' }}</span>
                            </span>
                        @endif
                    </div>
                </div>

                <!-- QR Code Column Inside the Card -->
                <div class="col-auto text-center">
                    <div class="pass-qr-box bg-white shadow-sm">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=8&data={{ urlencode($pass->pass_number) }}" alt="QR Code" width="118" height="118" class="img-fluid rounded" style="display:block; margin: 0 auto; background: #fff;">
                        <div class="fs-11 fw-bold text-dark mt-1 font-monospace">{{ $pass->pass_number }}</div>
                    </div>
                </div>
            </div>

            <!-- Visit Logistics Grid -->
            <div class="row g-3 mb-4">
                <!-- Host Card -->
                <div class="col-md-6">
                    <div class="metric-tile">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="tile-label"><i class="feather-user me-1 text-primary"></i> {{ __('visitor.host_details') }}</div>
                            @if($pass->host_notified_at)
                                <span class="badge bg-success-subtle text-success fs-10 px-1.5 py-0.5">
                                    <i class="feather-bell me-0.5"></i> Notified: {{ \Carbon\Carbon::parse($pass->host_notified_at)->format('h:i A') }}
                                </span>
                            @endif
                        </div>
                        <div class="tile-value fs-14 text-dark mb-0.5">
                            {{ $pass->host->name ?? 'Direct / Reception' }}
                        </div>
                        <div class="fs-12 text-muted">
                            {{ $pass->host->email ?? 'reception@erp.internal' }}
                        </div>
                    </div>
                </div>

                <!-- Purpose & Schedule -->
                <div class="col-md-6">
                    <div class="metric-tile">
                        <div class="tile-label"><i class="feather-target me-1 text-primary"></i> {{ __('visitor.purpose_of_visit') }}</div>
                        <div class="tile-value fs-14 text-dark mb-0.5">
                            {{ __('visitor.purposes.' . $pass->purpose, [], null) ?? $pass->purpose }}
                        </div>
                        <div class="fs-12 text-muted">
                            Expected Duration: <strong class="text-dark">{{ $pass->expected_duration_minutes ?: 60 }} Mins</strong>
                        </div>
                    </div>
                </div>

                <!-- Vehicle & Parking Slot -->
                <div class="col-sm-4">
                    <div class="metric-tile">
                        <div class="tile-label"><i class="feather-truck me-1 text-muted"></i> {{ __('visitor.vehicle_details') }}</div>
                        <div class="tile-value font-monospace">
                            {{ $pass->vehicle_number ?: 'Pedestrian (No Vehicle)' }}
                        </div>
                        @if($pass->parking_slot)
                            <div class="fs-11 text-primary fw-semibold mt-0.5">
                                <i class="feather-map-pin me-0.5"></i> Slot: {{ $pass->parking_slot }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Accompanying Guests -->
                <div class="col-sm-4">
                    <div class="metric-tile">
                        <div class="tile-label"><i class="feather-users me-1 text-muted"></i> {{ __('visitor.accompanying_persons') }}</div>
                        <div class="tile-value">
                            +{{ $pass->accompanying_count ?? 0 }} Guests
                        </div>
                        @if($pass->accompanying_names)
                            <div class="fs-11 text-muted text-truncate mt-0.5" title="{{ $pass->accompanying_names }}">
                                {{ $pass->accompanying_names }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- RFID Badge -->
                <div class="col-sm-4">
                    <div class="metric-tile">
                        <div class="tile-label"><i class="feather-credit-card me-1 text-muted"></i> {{ __('visitor.badge_number') }}</div>
                        <div class="tile-value font-monospace">
                            {{ $pass->badge_number ?: 'N/A' }}
                        </div>
                        @if($pass->badge_returned)
                            <div class="fs-11 text-success fw-semibold mt-0.5">
                                <i class="feather-check-circle me-0.5"></i> Returned to Security
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Timestamps Row -->
                <div class="col-sm-4">
                    <div class="metric-tile">
                        <div class="tile-label"><i class="feather-clock me-1 text-muted"></i> {{ __('visitor.check_in_time') }}</div>
                        <div class="tile-value fs-13">
                            {{ $pass->check_in_at ? \Carbon\Carbon::parse($pass->check_in_at)->format('d M Y, h:i A') : '—' }}
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="metric-tile">
                        <div class="tile-label"><i class="feather-users me-1 text-muted"></i> {{ __('visitor.meeting_started_time') }}</div>
                        <div class="tile-value fs-13">
                            {{ $pass->meeting_started_at ? \Carbon\Carbon::parse($pass->meeting_started_at)->format('d M Y, h:i A') : '—' }}
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="metric-tile">
                        <div class="tile-label"><i class="feather-log-out me-1 text-muted"></i> {{ __('visitor.check_out_time') }}</div>
                        <div class="tile-value fs-13">
                            {{ $pass->check_out_at ? \Carbon\Carbon::parse($pass->check_out_at)->format('d M Y, h:i A') : '—' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Declared Belongings / Assets -->
            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-bold text-dark mb-0 fs-13">
                        <i class="feather-package me-1.5 text-primary"></i> {{ __('visitor.belongings') }}
                    </h6>
                    @if($pass->belongings && $pass->belongings->count() > 0)
                        <span class="badge bg-light text-dark border fs-11">{{ $pass->belongings->count() }} Items</span>
                    @endif
                </div>

                @if($pass->belongings && $pass->belongings->count() > 0)
                    <div class="table-responsive border rounded-3 overflow-hidden">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="fs-11 text-uppercase text-muted">
                                    <th class="py-2 ps-3">{{ __('visitor.item_type') }}</th>
                                    <th class="py-2">{{ __('visitor.serial_number') }}</th>
                                    <th class="py-2">{{ __('visitor.gate_pass_number') }}</th>
                                    <th class="py-2 pe-3 text-end">Clearance Status</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                @foreach($pass->belongings as $item)
                                    <tr>
                                        <td class="ps-3 fw-semibold text-dark">{{ $item->item_type }}</td>
                                        <td class="font-monospace">{{ $item->serial_number ?? '—' }}</td>
                                        <td class="font-monospace text-muted">{{ $item->gate_pass_number ?? '—' }}</td>
                                        <td class="pe-3 text-end">
                                            @if($item->is_verified_on_exit)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5">
                                                    <i class="feather-check-circle me-1"></i> Exit Verified
                                                </span>
                                            @else
                                                <span class="badge bg-light text-muted border px-2 py-0.5">
                                                    Inside Premises
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-3 bg-light rounded-3 text-muted fs-12 fst-italic border">
                        <i class="feather-info me-1"></i> {{ __('visitor.no_belongings') }}
                    </div>
                @endif
            </div>

            <!-- Safety & Compliance Notice -->
            <div class="p-3 bg-light border-start border-3 border-warning rounded-2 mb-4">
                <div class="fw-bold text-dark fs-12 mb-1">
                    <i class="feather-shield text-warning me-1"></i> {{ __('visitor.safety_notice') }}
                </div>
                <ul class="fs-12 text-muted mb-0 ps-3">
                    <li>{{ __('visitor.safety_point_1') }}</li>
                    <li>{{ __('visitor.safety_point_2') }}</li>
                    <li>{{ __('visitor.safety_point_3') }}</li>
                </ul>
            </div>

            <!-- Signatures Section -->
            <div class="row pt-3 text-center border-top">
                <div class="col-6">
                    <div class="border-bottom mx-auto mb-2" style="width: 170px; height: 35px;"></div>
                    <div class="fs-11 fw-semibold text-uppercase text-muted">{{ __('visitor.visitor_sign') }}</div>
                </div>
                <div class="col-6">
                    <div class="border-bottom mx-auto mb-2" style="width: 170px; height: 35px;"></div>
                    <div class="fs-11 fw-semibold text-uppercase text-muted">{{ __('visitor.authorized_sign') }}</div>
                </div>
            </div>
        </div>

        <!-- Integrated Card Footer Actions (Clean & Single Card) -->
        <div class="card-footer bg-light p-3 d-flex flex-wrap justify-content-between align-items-center gap-2 no-print border-top">
            <div class="fs-12 text-muted">
                {{ __('visitor.entry_type') }}: <strong class="text-dark">{{ $pass->entry_type ?? 'Walk-in' }}</strong>
                @if($pass->pass_fee > 0)
                    <span class="mx-2">•</span>
                    Fee: <strong class="text-dark">{{ format_currency($pass->pass_fee) }}</strong>
                @endif
            </div>
            
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if($pass->status === 'Expected' || $pass->status === 'Approved' || $pass->status === 'Arrived')
                    <form action="{{ route('visitor.passes.check-in', $pass->id) }}" method="POST" class="d-inline">
                        @csrf
                        <x-ui.button type="submit" variant="success" icon="feather-log-in" size="sm">
                            {{ __('visitor.check_in') }}
                        </x-ui.button>
                    </form>
                @elseif($pass->status === 'Checked-In')
                    <form action="{{ route('visitor.passes.start-meeting', $pass->id) }}" method="POST" class="d-inline">
                        @csrf
                        <x-ui.button type="submit" variant="info" icon="feather-users" size="sm" class="text-white">
                            {{ __('visitor.start_meeting') }}
                        </x-ui.button>
                    </form>
                    <x-ui.button type="button" variant="warning" icon="feather-clock" size="sm" class="text-dark" onclick="openExtendModalShow({{ $pass->id }}, '{{ $pass->pass_number }}')">
                        {{ __('visitor.extend_visit') }} (+Time)
                    </x-ui.button>
                    <form action="{{ route('visitor.passes.check-out', $pass->id) }}" method="POST" class="d-inline">
                        @csrf
                        <x-ui.button type="submit" variant="danger" icon="feather-log-out" size="sm">
                            {{ __('visitor.check_out') }}
                        </x-ui.button>
                    </form>
                @elseif($pass->status === 'Meeting in Progress')
                    <x-ui.button type="button" variant="warning" icon="feather-clock" size="sm" class="text-dark" onclick="openExtendModalShow({{ $pass->id }}, '{{ $pass->pass_number }}')">
                        {{ __('visitor.extend_visit') }} (+Time)
                    </x-ui.button>
                    <form action="{{ route('visitor.passes.check-out', $pass->id) }}" method="POST" class="d-inline">
                        @csrf
                        <x-ui.button type="submit" variant="danger" icon="feather-log-out" size="sm">
                            {{ __('visitor.check_out') }}
                        </x-ui.button>
                    </form>
                @endif

                <x-ui.button type="button" variant="primary" icon="feather-printer" size="sm" onclick="window.print()">
                    {{ __('visitor.print_badge') }}
                </x-ui.button>
            </div>
        </div>

    </div>

</div>

<!-- Reject Modal for Show View -->
<x-ui.modal 
    id="rejectPassModalShow" 
    title="<i class='feather-alert-octagon text-danger me-2'></i> {{ __('visitor.reject_pass') }}" 
    centered 
    :showFooter="false">
    <form id="rejectPassFormShow" method="POST" action="{{ route('visitor.approvals.reject', $pass->id) }}">
        @csrf
        <div class="mb-3">
            <p class="fs-13 text-dark mb-1">
                Visitor: <strong>{{ $pass->visitor?->full_name }}</strong> ({{ $pass->pass_number }})
            </p>
            <p class="fs-12 text-muted">
                {{ __('visitor.enter_rejection_reason') }}
            </p>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold fs-12 text-dark">{{ __('visitor.rejection_reason') }} <span class="text-danger">*</span></label>
            <textarea name="rejection_reason" class="form-control fs-13" rows="3" placeholder="{{ __('visitor.enter_rejection_reason') }}" required></textarea>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-4">
            <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">{{ __('crm.cancel') ?? 'Cancel' }}</button>
            <button type="submit" class="btn btn-danger px-3 fw-semibold">
                <i class="feather-x me-1"></i> {{ __('visitor.reject') }}
            </button>
        </div>
    </form>
</x-ui.modal>

<!-- Extend Visit Modal for Show View -->
<x-ui.modal 
    id="extendVisitModalShow" 
    title="<i class='feather-clock text-warning me-2'></i> {{ __('visitor.extend_visit') }} - {{ $pass->pass_number }}" 
    centered 
    :showFooter="false">
    <form id="extendVisitFormShow" method="POST" action="{{ route('visitor.passes.extend', $pass->id) }}">
        @csrf
        <div class="mb-3">
            <label class="form-label fw-bold fs-12 text-dark">{{ __('visitor.extend_minutes') }} <span class="text-danger">*</span></label>
            <select name="extend_minutes" class="form-select fs-13" required>
                <option value="30">+30 Minutes</option>
                <option value="60" selected>+60 Minutes (1 Hour)</option>
                <option value="120">+120 Minutes (2 Hours)</option>
                <option value="240">+240 Minutes (4 Hours)</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold fs-12 text-dark">Reason / Remarks</label>
            <textarea name="extend_notes" class="form-control fs-13" rows="2" placeholder="Meeting prolonged, additional agenda..."></textarea>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-4">
            <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">{{ __('crm.cancel') ?? 'Cancel' }}</button>
            <button type="submit" class="btn btn-warning px-3 fw-semibold">
                <i class="feather-clock me-1"></i> Extend Stay
            </button>
        </div>
    </form>
</x-ui.modal>
@endsection

@push('scripts')
<script>
    function openRejectModalShow(passId) {
        const modal = new bootstrap.Modal(document.getElementById('rejectPassModalShow'));
        modal.show();
    }

    function openExtendModalShow(passId, passNumber) {
        const modal = new bootstrap.Modal(document.getElementById('extendVisitModalShow'));
        modal.show();
    }
</script>
@endpush
