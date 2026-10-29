@extends('layouts.duralux')

@section('title', __('production.work_center_details') . ' | SaaS ERP')
@section('page-title', __('production.work_center_details'))
@section('breadcrumb', $workCenter->code)

@section('page-actions')
    <div class="d-flex gap-2 align-items-center">
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#workCenterCalculationModal">
            <i class="feather-pie-chart me-1"></i>{{ __('production.calculation_breakdown') }}
        </button>
        @can('update', $workCenter)
            <button type="button" 
                    class="btn btn-sm {{ $isCountdownActive ? 'btn-soft-warning' : 'btn-outline-secondary' }}" 
                    data-bs-toggle="modal" 
                    data-bs-target="#editCountdownDurationModal"
                    @if($isCountdownActive) disabled title="Countdown is actively ticking. Debounce duration cannot be changed." @endif>
                <i class="feather-clock me-1"></i>Debounce: {{ $workCenter->calculation_countdown_minutes ?? 10 }}m
            </button>
            <form method="POST" action="{{ route('production.work-centers.recalculate-now', $workCenter->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-warning" title="Manually trigger immediate recalculation rollup">
                    <i class="feather-zap me-1"></i>Recalculate
                </button>
            </form>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#workCenterEmployeesModal">
                <i class="feather-user-check me-1"></i>{{ __('production.assign_internal_employees') }}
            </button>
        @endcan
        <x-ui.button :href="route('production.work-centers.index')" variant="secondary" icon="feather-arrow-left">
            {{ __('production.back_to_list') }}
        </x-ui.button>
        @can('update', $workCenter)
            <x-ui.button :href="route('production.work-centers.edit', $workCenter->id)" variant="primary" icon="feather-edit">
                {{ __('production.edit') }}
            </x-ui.button>
        @endcan
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .section-machines-popover,
        .section-popover {
            max-width: 440px;
            box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.15);
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        .section-table-popover {
            max-width: 480px;
            box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.15);
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        .wc-breakdown-popover {
            width: 350px !important;
            min-width: 330px !important;
            max-width: 360px !important;
            box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.15);
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        .metric-audit-popover {
            max-width: 380px;
            box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.15);
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        .section-machines-popover .popover-header,
        .section-popover .popover-header,
        .section-table-popover .popover-header,
        .metric-audit-popover .popover-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
        }
        .wc-breakdown-popover .popover-header {
            display: none !important;
        }
        .section-machines-popover .popover-body,
        .section-popover .popover-body,
        .metric-audit-popover .popover-body {
            padding: 8px 12px;
            max-height: 280px;
            overflow-y: auto;
        }
        .section-table-popover .popover-body,
        .wc-breakdown-popover .popover-body {
            padding: 0 !important;
            overflow: hidden;
        }
        .section-machines-badge {
            cursor: pointer;
            transition: all 0.15s ease-in-out;
        }
        .section-machines-badge:hover {
            filter: brightness(0.95);
            transform: translateY(-1px);
        }
        .resource-cluster-pill {
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            user-select: none;
        }
        .resource-cluster-pill:hover {
            filter: brightness(0.92);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        }
        .section-code-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: var(--bs-font-monospace);
            font-weight: 700;
            font-size: 11px;
            line-height: 1;
            padding: 5px 8px;
            border-radius: 6px;
            background-color: #ede9fe;
            color: #5b21b6;
            border: 1px solid #c4b5fd;
            letter-spacing: 0.3px;
            box-shadow: 0 1px 2px rgba(91, 33, 182, 0.06);
            margin-right: 6px;
        }
        .section-shift-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: var(--bs-font-monospace);
            font-weight: 600;
            font-size: 11px;
            line-height: 1.2;
            padding: 4px 8px;
            border-radius: 6px;
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            box-shadow: 0 1px 2px rgba(4, 120, 87, 0.05);
            transition: all 0.15s ease-in-out;
            cursor: pointer;
            user-select: none;
            margin-right: 4px;
            margin-bottom: 4px;
        }
        .section-shift-pill:hover {
            background-color: #d1fae5;
            border-color: #6ee7b7;
            color: #065f46;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(4, 120, 87, 0.12);
        }
        .section-details-trigger {
            cursor: pointer;
            color: #1e293b;
            text-decoration: none;
            transition: color 0.15s ease-in-out;
        }
        .section-details-trigger:hover {
            color: #4f46e5;
            text-decoration: none;
        }
        .capacity-popover-trigger,
        .cost-popover-trigger {
            cursor: pointer;
            transition: all 0.15s ease-in-out;
        }
        .capacity-popover-trigger:hover,
        .cost-popover-trigger:hover {
            opacity: 0.85;
        }

        /* Fix Select2 accessible hidden select */
        select.select2-hidden-accessible {
            display: none !important;
            visibility: hidden !important;
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            overflow: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        /* Modal Select2 styling polish */
        .modal .select2-container--bootstrap-5 {
            width: 100% !important;
            display: block;
        }
        .modal .select2-container--bootstrap-5 .select2-selection--multiple {
            min-height: 42px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 4px 8px;
            background-color: #fff;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .modal .select2-container--bootstrap-5.select2-container--focus .select2-selection--multiple,
        .modal .select2-container--bootstrap-5.select2-container--open .select2-selection--multiple {
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.15);
        }
        .modal .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            padding: 0;
            margin: 0;
            list-style: none;
        }
        .modal .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            border-radius: 4px;
            padding: 3px 8px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            margin: 0;
        }
        .modal .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice .select2-selection__choice__remove {
            margin-right: 6px;
            color: #3b82f6;
            cursor: pointer;
            border: none;
            background: transparent;
            font-weight: bold;
        }
        .modal .select2-container--bootstrap-5 .select2-selection--single {
            min-height: 38px;
            height: 38px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 4px 10px;
            background-color: #fff;
            display: flex;
            align-items: center;
        }
        .modal .select2-container--bootstrap-5.select2-container--focus .select2-selection--single,
        .modal .select2-container--bootstrap-5.select2-container--open .select2-selection--single {
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.15);
        }
        .modal .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding-left: 0;
            color: #1e293b;
            font-size: 12px;
            line-height: normal;
        }
        .modal .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            top: 50%;
            transform: translateY(-50%);
            right: 8px;
        }
        .modal .select2-container--bootstrap-5 .select2-dropdown {
            z-index: 2000 !important;
            border: 1px solid #cbd5e1 !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
            border-radius: 8px !important;
            overflow: hidden !important;
        }
        .modal .select2-container--bootstrap-5 .select2-results__option {
            padding: 8px 12px;
            font-size: 12px;
        }
        .modal .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: #3b82f6 !important;
            color: #ffffff !important;
        }
        .modal .select2-container--bootstrap-5 .select2-results__option--selected {
            background-color: #f1f5f9 !important;
            color: #64748b !important;
        }

        /* Section Personnel Modal Nav Pills */
        .section-personnel-modal .nav-pills .nav-link {
            color: #4b5563;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }
        .section-personnel-modal .nav-pills .nav-link:hover {
            background-color: #f8fafc;
            border-color: #cbd5e1;
            color: #1e293b;
        }
        .section-personnel-modal .nav-pills .nav-link.active {
            color: #ffffff;
            background-color: var(--bs-primary, #4361ee);
            border-color: var(--bs-primary, #4361ee);
            box-shadow: 0 2px 6px rgba(67, 97, 238, 0.25);
        }
        .section-personnel-modal .nav-pills .nav-link.active .badge {
            color: var(--bs-primary, #4361ee) !important;
            background-color: #ffffff !important;
        }

        /* =========================================================================
           MULTI-MODAL STACKING & Z-INDEX OVERRIDES (CRITICAL FIX FOR DURALUX LAYOUT)
           duralux.blade.php sets .modal { z-index: 1060 !important; } and
           .modal-backdrop { z-index: 1050 !important; }.
           To stack child modals (e.g. Manual Production, Personnel, Calculation)
           above Section Details, we provide explicit rules with higher specificity & !important.
           ========================================================================= */
        .modal.modal-stacked,
        .modal.modal-level-2,
        .modal[data-modal-stack="2"] {
            z-index: 1080 !important;
        }
        .modal.modal-level-3,
        .modal[data-modal-stack="3"] {
            z-index: 1100 !important;
        }
        .modal.modal-level-4,
        .modal[data-modal-stack="4"] {
            z-index: 1120 !important;
        }

        .modal-backdrop.backdrop-stacked,
        .modal-backdrop.backdrop-level-2,
        .modal-backdrop[data-backdrop-stack="2"] {
            z-index: 1075 !important;
        }
        .modal-backdrop.backdrop-level-3,
        .modal-backdrop[data-backdrop-stack="3"] {
            z-index: 1095 !important;
        }
        .modal-backdrop.backdrop-level-4,
        .modal-backdrop[data-backdrop-stack="4"] {
            z-index: 1115 !important;
        }

        /* Direct sibling override: when Section Details is open, child modals are automatically elevated */
        .section-details-modal.show ~ .manual-production-modal.show,
        .section-details-modal.show ~ .section-personnel-modal.show,
        .section-details-modal.show ~ [id^="sectionCalculationModal_"].show {
            z-index: 1080 !important;
        }

        /* Manual Production Setup Modal Styling */
        .manual-production-modal .modal-dialog {
            max-height: calc(100vh - 2.5rem);
            margin-top: 1.25rem;
            margin-bottom: 1.25rem;
        }
        .manual-production-modal .modal-content {
            max-height: calc(100vh - 2.5rem);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .manual-production-modal .modal-header,
        .manual-production-modal .modal-footer {
            flex-shrink: 0;
        }
        .manual-production-modal .modal-body {
            overflow-y: auto;
            flex: 1 1 auto;
            max-height: calc(100vh - 12rem);
        }
        .manual-production-modal .worker-card-item {
            transition: all 0.15s ease-in-out;
            border: 1px solid #e2e8f0;
        }
        .manual-production-modal .worker-card-item:hover {
            border-color: #3b82f6 !important;
            background-color: #f8faff !important;
        }
        .manual-production-modal .worker-card-item.selected-card {
            border-color: #3b82f6 !important;
            background-color: #eff6ff !important;
        }
        .manual-production-modal .workers-scroll-list::-webkit-scrollbar,
        .manual-production-modal .modal-body::-webkit-scrollbar {
            width: 6px;
        }
        .manual-production-modal .workers-scroll-list::-webkit-scrollbar-thumb,
        .manual-production-modal .modal-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .manual-production-modal .workers-scroll-list::-webkit-scrollbar-thumb:hover,
        .manual-production-modal .modal-body::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Dedicated Batches Toolbar */
        .manual-batches-bar {
            background-color: #f8fafc;
            padding: 12px 20px !important;
            border-bottom: 1px solid #e2e8f0;
        }
        .manual-batches-bar .manual-batch-mode-btn {
            display: inline-flex;
            align-items: center;
            font-size: 11px;
            padding: 4px 10px;
            white-space: nowrap;
        }

        /* Bootstrap utility polyfills for fractional spacing classes */
        .py-2\.5 { padding-top: 0.625rem !important; padding-bottom: 0.625rem !important; }
        .px-2\.5 { padding-left: 0.625rem !important; padding-right: 0.625rem !important; }
        .p-2\.5 { padding: 0.625rem !important; }
        .py-1\.5 { padding-top: 0.375rem !important; padding-bottom: 0.375rem !important; }
        .px-1\.5 { padding-left: 0.375rem !important; padding-right: 0.375rem !important; }
        .pt-1\.5 { padding-top: 0.375rem !important; }
        .pb-1\.5 { padding-bottom: 0.375rem !important; }
        .gap-1\.5 { gap: 0.375rem !important; }
        .gap-0\.5 { gap: 0.125rem !important; }
        .py-0\.5 { padding-top: 0.125rem !important; padding-bottom: 0.125rem !important; }
        .px-0\.5 { padding-left: 0.125rem !important; padding-right: 0.125rem !important; }
        .mt-0\.5 { margin-top: 0.125rem !important; }
        .mb-0\.5 { margin-bottom: 0.125rem !important; }
    </style>
@endpush

@section('content')
    <div class="erp-single-panel bg-white">
        <!-- Success & Error Alerts -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="{{ __('production.validation_failed') ?? 'Validation Failed' }}: {{ $errors->first() }}" />
        @endif

        @php
            $latestSnapshot = $workCenter->latestCalculation;
        @endphp

        <!-- Active Countdown Banner -->
        @if($isCountdownActive)
            <div class="alert alert-warning border border-warning shadow-sm rounded-3 p-3 mb-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3" id="activeCountdownBanner">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-text avatar-md bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="feather-clock fs-20"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="fw-bold text-dark mb-0 fs-14">
                                <i class="feather-loader text-warning me-1"></i>Automatic Debounce Countdown Active
                            </h6>
                            <span class="badge bg-warning text-dark font-monospace fs-13 px-2.5 py-1 rounded-pill" id="countdownTimerDisplay">
                                {{ gmdate('i:s', $countdownRemainingSeconds) }}
                            </span>
                        </div>
                        <p class="text-muted fs-12 mb-0 mt-1">
                            Pending updates detected on sections: 
                            @if(!empty($workCenter->pending_calculation_section_ids))
                                @foreach($workCenter->pending_calculation_section_ids as $pSecId)
                                    <span class="badge bg-soft-dark text-dark font-monospace fs-10">SEC #{{ $pSecId }}</span>
                                @endforeach
                            @else
                                <span class="badge bg-soft-dark text-dark font-monospace fs-10">All Active Sections</span>
                            @endif
                            &bull; Automatic recalculation fires when timer completes.
                        </p>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <form method="POST" action="{{ route('production.work-centers.recalculate-now', $workCenter->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-warning text-dark fw-bold btn-sm shadow-sm d-flex align-items-center gap-1.5">
                            <i class="feather-zap fs-13"></i> Recalculate Now (Bypass)
                        </button>
                    </form>
                </div>
            </div>
        @endif

        <!-- Profile / Metrics Layout -->
        <div class="row g-4 mb-4 pb-4 border-bottom">
            <!-- Left Info Panel (Column 1) -->
            <div class="col-md-4 border-end">
                <div class="text-center py-3">
                    <div class="avatar-text avatar-xl bg-soft-primary text-primary mx-auto mb-3 rounded-circle" style="width: 80px; height: 80px; font-size: 32px; display: flex; align-items: center; justify-content: center;">
                        <i class="feather-box"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">{{ $workCenter->name }}</h4>
                    <span class="fs-13 fw-semibold text-muted text-uppercase">{{ $workCenter->code }}</span>
                    <div class="mt-2">
                        @if ($workCenter->isActive())
                            <span class="badge bg-soft-success text-success px-3 py-1.5 rounded-pill">{{ __('production.active_operating') }}</span>
                        @else
                            <span class="badge bg-soft-danger text-danger px-3 py-1.5 rounded-pill">{{ __('production.inactive_suspended') }}</span>
                        @endif
                    </div>
                </div>

                <div class="d-flex flex-column gap-2 mt-2.5 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-12">{{ __('production.work_center_type') }}:</span>
                        <span class="fw-bold text-dark text-uppercase fs-12">
                            {{ config('production.work_center_types')[$workCenter->work_center_type] ?? $workCenter->work_center_type ?? 'N/A' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-12">{{ __('production.physical_location') }}:</span>
                        <span class="fw-semibold text-dark fs-12">{{ $workCenter->location ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-12">{{ __('production.created_date') }}:</span>
                        <span class="fw-semibold text-dark fs-12">{{ $workCenter->created_at->format('Y-m-d') }}</span>
                    </div>

                    @php
                        $directStaffService = app(\App\Domains\Production\Services\WorkCenterCalculationService::class);
                        $liveDirectStaffData = $directStaffService->calculateDirectStaffLabor($workCenter);
                        $liveDirectStaffEmployees = $liveDirectStaffData['employees'] ?? [];
                        $liveDirectStaffTotal = (float) ($liveDirectStaffData['total_daily_cost'] ?? 0.0);
                        $liveDirectStaffCount = count($liveDirectStaffEmployees);

                        $wcEmpListHtml = '<div class="card border-0 shadow-none mb-0 overflow-hidden" style="width: 350px; max-width: 360px;">'
                            . '<div class="card-header bg-soft-primary py-1.5 px-3 border-bottom d-flex justify-content-between align-items-center">'
                            . '<span class="text-dark fw-bold fs-11 text-uppercase"><i class="feather-users text-primary me-1.5"></i>DIRECT ASSIGNED STAFF (' . $liveDirectStaffCount . ')</span>'
                            . ($liveDirectStaffTotal > 0 
                                ? '<span class="badge bg-white text-primary border border-primary-subtle fs-10 px-2 py-0.5 rounded-pill font-monospace">' . format_currency($liveDirectStaffTotal) . ' / day</span>' 
                                : '<span class="badge bg-white text-secondary border fs-10 px-2 py-0.5 rounded-pill font-monospace">' . $liveDirectStaffCount . ' Personnel</span>')
                            . '</div>'
                            . '<div class="card-body bg-white p-2.5">'
                            . '<div class="text-muted fs-11 mb-1.5">Personnel directly allocated to this work center:</div>'
                            . '<div class="d-flex flex-column gap-1 fs-11">';

                        if ($liveDirectStaffCount > 0) {
                            foreach ($liveDirectStaffEmployees as $empData) {
                                $empCode   = $empData['employee_code'] ?? 'EMP';
                                $empName   = $empData['name'] ?? 'Employee';
                                $empRole   = $empData['role'] ?? 'Staff';
                                $empDaily  = (float) ($empData['daily_salary'] ?? $empData['daily_cost'] ?? 0);
                                $empHourly = (float) ($empData['hourly_rate'] ?? 0);

                                $wcEmpListHtml .= '<div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">'
                                    . '<div class="text-truncate me-2" style="max-width: 62%;">'
                                    . '<div class="text-dark text-truncate">'
                                    . '<span class="font-monospace fw-semibold text-primary">[' . e($empCode) . ']</span> '
                                    . '<span class="fw-semibold text-dark">' . e($empName) . '</span>'
                                    . '</div>'
                                    . '<div class="text-muted fs-10 text-truncate">' . e($empRole) . '</div>'
                                    . '</div>'
                                    . '<div class="text-end flex-shrink-0">'
                                    . '<div class="font-monospace fw-bold text-dark fs-11">' . format_currency($empDaily) . ' <small class="text-muted fw-normal">/ day</small></div>'
                                    . ($empHourly > 0 ? '<div class="text-muted font-monospace fs-10">' . format_currency($empHourly) . ' / hr</div>' : '')
                                    . '</div>'
                                    . '</div>';
                            }
                        } else {
                            $wcEmpListHtml .= '<div class="text-muted fst-italic py-2 text-center">'
                                . '<i class="feather-info text-muted me-1"></i>' . e(__('production.no_employees_assigned') ?? 'No direct personnel assigned')
                                . '</div>';
                        }
                        $wcEmpListHtml .= '</div></div></div>';
                    @endphp

                    <!-- Direct Assigned Staff Card with Hover Popover (Live Calculated Total) -->
                    <div class="card border rounded-3 shadow-none mt-2.5 overflow-hidden capacity-popover-trigger"
                         role="button"
                         tabindex="0"
                         data-bs-toggle="popover"
                         data-bs-trigger="hover focus"
                         data-bs-placement="top"
                         data-bs-html="true"
                         data-bs-custom-class="wc-breakdown-popover"
                         data-bs-content="{{ $wcEmpListHtml }}">
                        <div class="card-body bg-light p-2.5 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-1.5">
                                <i class="feather-users text-primary fs-13"></i>
                                <span class="fw-bold fs-11 text-uppercase text-dark">Direct Assigned Staff ({{ $liveDirectStaffCount }})</span>
                            </div>
                            @if($liveDirectStaffTotal > 0)
                                <span class="badge bg-soft-primary text-primary font-monospace fs-10 px-2 py-1">
                                    {{ format_currency($liveDirectStaffTotal) }}/day
                                </span>
                            @else
                                <span class="badge bg-soft-secondary text-secondary font-monospace fs-10 px-2 py-0.5">{{ $liveDirectStaffCount }} Assigned</span>
                            @endif
                        </div>
                    </div>

                    <!-- Purpose & Operational Notes Card -->
                    @if ($workCenter->description)
                        <div class="card border rounded-3 shadow-none mt-2.5 overflow-hidden">
                            <div class="card-header bg-light py-1.5 px-3 border-bottom d-flex align-items-center">
                                <span class="text-dark fw-bold fs-11 text-uppercase"><i class="feather-file-text text-primary me-1.5"></i>{{ __('production.purpose_notes') ?? 'Purpose & Operational Notes' }}</span>
                            </div>
                            <div class="card-body bg-white p-2.5">
                                <p class="mb-0 fs-12 text-muted" style="white-space: pre-line;">{{ $workCenter->description }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Capacity Panel (Column 2) -->
            <div class="col-md-4 border-end">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0 fs-14"><i class="feather-activity text-primary me-1.5"></i>{{ __('production.wc_metrics') }}</h5>
                    @if($latestSnapshot)
                        <span class="badge bg-soft-success text-success fs-10 font-monospace">Snapshot #{{ $latestSnapshot->id }}</span>
                    @endif
                </div>

                @php
                    $activeSections = $workCenter->sections ? $workCenter->sections->where('status', 'active') : collect();

                    // 1. Hourly Capacity Popover
                    $wcHourlyCapPopoverHtml = '<div class="card border-0 shadow-none mb-0 overflow-hidden" style="width: 350px; max-width: 360px;">'
                        . '<div class="card-header bg-soft-warning py-1.5 px-3 border-bottom d-flex justify-content-between align-items-center">'
                        . '<span class="text-dark fw-bold fs-11 text-uppercase text-nowrap"><i class="feather-clock text-warning me-1.5"></i>Hourly Capacity</span>'
                        . '<span class="badge bg-white text-warning border border-warning-subtle fs-10 px-2 py-0.5 rounded-pill font-monospace text-nowrap">' . number_format($latestSnapshot?->capacity_per_hour ?? 0, 1) . ' u/h</span>'
                        . '</div>'
                        . '<div class="card-body bg-white p-2.5">'
                        . '<div class="text-muted fs-11 mb-1.5">Section contribution to average hourly speed:</div>'
                        . '<div class="d-flex flex-column gap-1 fs-11">';

                    if ($activeSections->isNotEmpty()) {
                        foreach ($activeSections as $sec) {
                            $sCalc = $sec->latestCalculation;
                            $sCapHr = (float) ($sCalc?->capacity_per_hour ?? 0);
                            $wcHourlyCapPopoverHtml .= '<div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">'
                                . '<span class="text-dark text-truncate me-2" style="max-width: 62%;">'
                                . '<span class="font-monospace fw-semibold text-primary">[' . e($sec->code) . ']</span> ' . e($sec->name)
                                . '</span>'
                                . '<span class="font-monospace fw-bold text-dark flex-shrink-0">' . number_format($sCapHr, 1) . ' <small class="text-muted fw-normal">u/h</small></span>'
                                . '</div>';
                        }
                    } else {
                        $wcHourlyCapPopoverHtml .= '<span class="text-muted fst-italic">No active sections</span>';
                    }
                    $wcHourlyCapPopoverHtml .= '</div></div></div>';

                    // 2. Daily Output Popover
                    $wcDailyCapPopoverHtml = '<div class="card border-0 shadow-none mb-0 overflow-hidden" style="width: 350px; max-width: 360px;">'
                        . '<div class="card-header bg-soft-success py-1.5 px-3 border-bottom d-flex justify-content-between align-items-center">'
                        . '<span class="text-dark fw-bold fs-11 text-uppercase text-nowrap"><i class="feather-box text-success me-1.5"></i>Daily Output</span>'
                        . '<span class="badge bg-white text-success border border-success-subtle fs-10 px-2 py-0.5 rounded-pill font-monospace text-nowrap">' . number_format($latestSnapshot?->daily_capacity ?? 0, 1) . ' units</span>'
                        . '</div>'
                        . '<div class="card-body bg-white p-2.5">'
                        . '<div class="text-muted fs-11 mb-1.5">Section contribution to total daily output:</div>'
                        . '<div class="d-flex flex-column gap-1 fs-11">';

                    if ($activeSections->isNotEmpty()) {
                        foreach ($activeSections as $sec) {
                            $sCalc = $sec->latestCalculation;
                            $sDailyCap = (float) ($sCalc?->daily_capacity ?? 0);
                            $wcDailyCapPopoverHtml .= '<div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">'
                                . '<span class="text-dark text-truncate me-2" style="max-width: 62%;">'
                                . '<span class="font-monospace fw-semibold text-success">[' . e($sec->code) . ']</span> ' . e($sec->name)
                                . '</span>'
                                . '<span class="font-monospace fw-bold text-dark flex-shrink-0">' . number_format($sDailyCap, 1) . ' <small class="text-muted fw-normal">units</small></span>'
                                . '</div>';
                        }
                    } else {
                        $wcDailyCapPopoverHtml .= '<span class="text-muted fst-italic">No active sections</span>';
                    }
                    $wcDailyCapPopoverHtml .= '</div></div></div>';
                @endphp

                <!-- 1. Work Center Capacity per Hour Card (With Hover Popover) -->
                <div class="card border rounded-3 shadow-none mb-3 overflow-hidden capacity-popover-trigger"
                     role="button"
                     tabindex="0"
                     data-bs-toggle="popover"
                     data-bs-trigger="hover focus"
                     data-bs-placement="top"
                     data-bs-html="true"
                     data-bs-target="#workCenterCalculationModal"
                     data-bs-custom-class="wc-breakdown-popover"
                     data-bs-content="{{ $wcHourlyCapPopoverHtml }}">
                    <div class="card-header bg-soft-warning bg-opacity-50 py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                        <span class="text-dark fw-bold fs-11 text-uppercase"><i class="feather-clock text-warning me-1"></i>Capacity / Hour</span>
                        <span class="badge bg-white text-warning border border-warning-subtle fs-10 px-2 py-0.5 rounded-pill" title="Average capacity across {{ format_hours($latestSnapshot?->unique_operating_hours ?? 0) }} operational hours.">
                            <i class="feather-alert-triangle me-0.5"></i>Avg / hr
                        </span>
                    </div>
                    <div class="card-body bg-white p-3 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-baseline gap-1.5">
                            <span class="fs-22 fw-bold text-primary font-monospace">
                                {{ number_format($latestSnapshot?->capacity_per_hour ?? 0, 1) }}
                            </span>
                            <span class="fs-11 text-muted">units / hr</span>
                        </div>
                        <span class="badge bg-soft-secondary text-secondary fs-11 font-monospace">
                            <i class="feather-grid me-1 text-primary"></i>{{ $activeSections->count() }} Sections <i class="feather-chevron-right ms-0.5 fs-10"></i>
                        </span>
                    </div>
                </div>

                <!-- 2. Work Center Daily Capacity Card (With Hover Popover) -->
                <div class="card border rounded-3 shadow-none mb-3 overflow-hidden capacity-popover-trigger"
                     role="button"
                     tabindex="0"
                     data-bs-toggle="popover"
                     data-bs-trigger="hover focus"
                     data-bs-placement="top"
                     data-bs-html="true"
                     data-bs-target="#workCenterCalculationModal"
                     data-bs-custom-class="wc-breakdown-popover"
                     data-bs-content="{{ $wcDailyCapPopoverHtml }}">
                    <div class="card-header bg-soft-success bg-opacity-50 py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                        <span class="text-dark fw-bold fs-11 text-uppercase"><i class="feather-box text-success me-1"></i>Daily Production Capacity</span>
                        <span class="badge bg-white text-success border border-success-subtle fs-10 px-2 py-0.5 rounded-pill">Total Output</span>
                    </div>
                    <div class="card-body bg-white p-3 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-baseline gap-1.5">
                            <span class="fs-22 fw-bold text-success font-monospace">
                                {{ number_format($latestSnapshot?->daily_capacity ?? 0, 1) }}
                            </span>
                            <span class="fs-11 text-muted">units / day</span>
                        </div>
                        <span class="badge bg-soft-secondary text-secondary fs-11 font-monospace">
                            <i class="feather-grid me-1 text-success"></i>{{ $activeSections->count() }} Sections <i class="feather-chevron-right ms-0.5 fs-10"></i>
                        </span>
                    </div>
                </div>

                <!-- 3. Operational Schedule & Window Card (Direct Card) -->
                <div class="card border rounded-3 shadow-none mb-0 overflow-hidden">
                    <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                        <span class="text-dark fw-bold fs-11 text-uppercase"><i class="feather-calendar text-primary me-1"></i>Operational Schedule</span>
                        <small class="text-muted fs-10">Across section shifts</small>
                    </div>
                    <div class="card-body bg-white p-3 d-flex flex-column gap-2 fs-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fs-11 text-uppercase">Operating Window</span>
                            <span class="fs-13 fw-bold text-dark font-monospace">{{ format_hours($latestSnapshot?->unique_operating_hours ?? 0, false) }} <span class="fs-10 fw-normal text-muted">hrs / day</span></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="text-muted fs-11 text-uppercase">Production Calendar</span>
                            <span class="fs-12 fw-semibold text-dark">
                                @if ($workCenter->calendar)
                                    <span class="badge bg-soft-primary text-primary fs-10"><i class="feather-calendar me-0.5"></i>{{ $workCenter->calendar->name }}</span>
                                @else
                                    <span class="text-muted fs-11 fst-italic">Default Factory Calendar</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Operating Cost Structure Panel (Column 3) -->
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0 fs-14"><i class="feather-dollar-sign text-primary me-1.5"></i>{{ __('production.wc_cost_structure') }}</h5>
                </div>
                @php
                    $laborCostHour = (float) ($latestSnapshot?->labor_cost_per_hour ?? 0.0);
                    $laborCostDaily = (float) ($latestSnapshot?->total_daily_labor_cost ?? 0.0);
                    $machCostHour = (float) ($latestSnapshot?->machine_cost_per_hour ?? 0.0);
                    $machCostDaily = (float) ($latestSnapshot?->total_daily_machine_cost ?? 0.0);
                    $overheadFlat = (float) ($latestSnapshot?->overhead_cost ?? $workCenter->overhead_cost ?? 0);
                    $totalDailyCost = (float) ($latestSnapshot?->total_daily_cost ?? ($overheadFlat + $laborCostDaily + $machCostDaily));
                    $totalCostHour = (float) ($latestSnapshot?->total_cost_per_hour ?? ($latestSnapshot?->unique_operating_hours > 0 ? $totalDailyCost / $latestSnapshot->unique_operating_hours : 0));
                    $wcCostPerUnitVal = (float) ($latestSnapshot?->cost_per_unit ?? ($latestSnapshot?->daily_capacity > 0 ? $totalDailyCost / $latestSnapshot->daily_capacity : 0));
                    $directStaffDailyCost = (float) ($latestSnapshot?->direct_staff_daily_labor_cost ?? 0);
                    $snapDirectStaffList = $latestSnapshot?->calculation_details['direct_work_center_staff']['employees'] 
                        ?? $latestSnapshot?->calculation_details['direct_personnel']['employees'] 
                        ?? [];
                    $snapDirectStaffCount = count($snapDirectStaffList);

                    // 3. Daily Labor Popover
                    $wcDailyLaborPopoverHtml = '<div class="card border-0 shadow-none mb-0 overflow-hidden" style="width: 350px; max-width: 360px;">'
                        . '<div class="card-header bg-soft-primary py-1.5 px-3 border-bottom d-flex justify-content-between align-items-center">'
                        . '<span class="text-dark fw-bold fs-11 text-uppercase text-nowrap"><i class="feather-users text-primary me-1.5"></i>Labor Breakdown</span>'
                        . '<span class="badge bg-white text-primary border border-primary-subtle fs-10 px-2 py-0.5 rounded-pill font-monospace text-nowrap">' . format_currency($laborCostDaily) . '</span>'
                        . '</div>'
                        . '<div class="card-body bg-white p-2.5">'
                        . '<div class="text-muted fs-11 mb-1.5">Section & direct personnel labor contributions:</div>'
                        . '<div class="d-flex flex-column gap-1 fs-11">';

                    foreach ($activeSections as $sec) {
                        $sLabor = (float) ($sec->latestCalculation?->total_daily_labor_cost ?? 0);
                        $wcDailyLaborPopoverHtml .= '<div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">'
                            . '<span class="text-dark text-truncate me-2" style="max-width: 62%;">'
                            . '<span class="font-monospace fw-semibold text-primary">[' . e($sec->code) . ']</span> ' . e($sec->name)
                            . '</span>'
                            . '<span class="font-monospace fw-semibold text-primary flex-shrink-0">' . format_currency($sLabor) . '</span>'
                            . '</div>';
                    }
                    if ($snapDirectStaffCount > 0 || $directStaffDailyCost > 0) {
                        $wcDailyLaborPopoverHtml .= '<div class="d-flex justify-content-between align-items-center pt-1.5 mt-1 border-top border-light-subtle">'
                            . '<span class="text-dark fw-semibold text-truncate me-2" style="max-width: 62%;">'
                            . '<i class="feather-user-check me-1 text-primary"></i>Direct Personnel' . ($snapDirectStaffCount > 0 ? ' (' . $snapDirectStaffCount . ')' : '')
                            . '</span>'
                            . '<span class="font-monospace fw-bold text-primary flex-shrink-0">' . format_currency($directStaffDailyCost) . '</span>'
                            . '</div>';
                    }
                    $wcDailyLaborPopoverHtml .= '</div></div></div>';

                    // 4. Daily Machinery Popover
                    $wcDailyMachPopoverHtml = '<div class="card border-0 shadow-none mb-0 overflow-hidden" style="width: 350px; max-width: 360px;">'
                        . '<div class="card-header bg-soft-warning py-1.5 px-3 border-bottom d-flex justify-content-between align-items-center">'
                        . '<span class="text-dark fw-bold fs-11 text-uppercase text-nowrap"><i class="feather-cpu text-warning me-1.5"></i>Machinery Breakdown</span>'
                        . '<span class="badge bg-white text-warning border border-warning-subtle fs-10 px-2 py-0.5 rounded-pill font-monospace text-nowrap">' . format_currency($machCostDaily) . '</span>'
                        . '</div>'
                        . '<div class="card-body bg-white p-2.5">'
                        . '<div class="text-muted fs-11 mb-1.5">Section machinery power and operating expenses:</div>'
                        . '<div class="d-flex flex-column gap-1 fs-11">';

                    foreach ($activeSections as $sec) {
                        $sMach = (float) ($sec->latestCalculation?->total_daily_machine_cost ?? 0);
                        $wcDailyMachPopoverHtml .= '<div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle">'
                            . '<span class="text-dark text-truncate me-2" style="max-width: 62%;">'
                            . '<span class="font-monospace fw-semibold text-dark">[' . e($sec->code) . ']</span> ' . e($sec->name)
                            . '</span>'
                            . '<span class="font-monospace fw-semibold text-warning flex-shrink-0">' . format_currency($sMach) . '</span>'
                            . '</div>';
                    }
                    $wcDailyMachPopoverHtml .= '</div></div></div>';
                @endphp

                <!-- 1. Daily Labor Cost Card (With Hover Popover) -->
                <div class="card border rounded-3 shadow-none mb-3 overflow-hidden cost-popover-trigger"
                     role="button"
                     tabindex="0"
                     data-bs-toggle="popover"
                     data-bs-trigger="hover focus"
                     data-bs-placement="top"
                     data-bs-html="true"
                     data-bs-target="#workCenterCalculationModal"
                     data-bs-custom-class="wc-breakdown-popover"
                     data-bs-content="{{ $wcDailyLaborPopoverHtml }}">
                    <div class="card-header bg-soft-primary bg-opacity-50 py-2 px-3 border-bottom d-flex justify-content-between align-items-center gap-2">
                        <span class="text-muted fs-11 text-uppercase fw-bold text-dark text-nowrap"><i class="feather-users text-primary me-1"></i>Daily Labor Cost</span>
                        <span class="badge bg-white text-primary border border-primary-subtle fs-10 px-2 py-0.5 rounded-pill font-monospace text-nowrap">Avg: {{ format_currency($laborCostHour) }} / hr</span>
                    </div>
                    <div class="card-body bg-white p-3 d-flex justify-content-between align-items-center">
                        <span class="fs-20 fw-bold text-primary font-monospace">{{ format_currency($laborCostDaily) }} <span class="fs-11 fw-normal text-muted">/ day</span></span>
                        <span class="badge bg-soft-secondary text-secondary fs-11 font-monospace">
                            <i class="feather-pie-chart me-1 text-primary"></i>Breakdown <i class="feather-chevron-right ms-0.5 fs-10"></i>
                        </span>
                    </div>
                </div>

                <!-- 2. Daily Machinery Expense Card (With Hover Popover) -->
                <div class="card border rounded-3 shadow-none mb-3 overflow-hidden cost-popover-trigger"
                     role="button"
                     tabindex="0"
                     data-bs-toggle="popover"
                     data-bs-trigger="hover focus"
                     data-bs-placement="top"
                     data-bs-html="true"
                     data-bs-target="#workCenterCalculationModal"
                     data-bs-custom-class="wc-breakdown-popover"
                     data-bs-content="{{ $wcDailyMachPopoverHtml }}">
                    <div class="card-header bg-soft-warning bg-opacity-50 py-2 px-3 border-bottom d-flex justify-content-between align-items-center gap-2">
                        <span class="text-muted fs-11 text-uppercase fw-bold text-dark text-nowrap"><i class="feather-cpu text-warning me-1"></i>Daily Machinery Expense</span>
                        <span class="badge bg-white text-warning border border-warning-subtle fs-10 px-2 py-0.5 rounded-pill font-monospace text-nowrap">Avg: {{ format_currency($machCostHour) }} / hr</span>
                    </div>
                    <div class="card-body bg-white p-3 d-flex justify-content-between align-items-center">
                        <span class="fs-20 fw-bold text-warning font-monospace">{{ format_currency($machCostDaily) }} <span class="fs-11 fw-normal text-muted">/ day</span></span>
                        <span class="badge bg-soft-secondary text-secondary fs-11 font-monospace">
                            <i class="feather-pie-chart me-1 text-warning"></i>Breakdown <i class="feather-chevron-right ms-0.5 fs-10"></i>
                        </span>
                    </div>
                </div>

                <!-- 3. Facility Overhead Card (Direct Card) -->
                <div class="card border rounded-3 shadow-none mb-3 overflow-hidden">
                    <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center gap-2">
                        <span class="text-dark fw-bold fs-11 text-uppercase text-nowrap"><i class="feather-home text-secondary me-1"></i>Facility Overhead</span>
                        <small class="text-muted fs-10 text-nowrap">Fixed daily</small>
                    </div>
                    <div class="card-body bg-white p-2.5 d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-11">Facility operational cost</span>
                        <span class="fs-13 fw-bold font-monospace text-dark">{{ format_currency($overheadFlat) }} <span class="fs-10 text-muted fw-normal">/ day</span></span>
                    </div>
                </div>

                <!-- 4. Total Daily Operating Cost Card (Direct Card) -->
                <div class="card border border-primary-subtle rounded-3 shadow-none mb-0 overflow-hidden">
                    <div class="card-header bg-soft-primary py-2 px-3 border-bottom border-primary-subtle d-flex justify-content-between align-items-center gap-2">
                        <span class="text-dark fw-bold fs-11 text-uppercase text-nowrap"><i class="feather-check-circle text-primary me-1.5"></i>TOTAL DAILY COST</span>
                        <span class="badge bg-primary text-white fs-10 px-2.5 py-1 rounded-pill font-monospace text-nowrap">Effective: {{ format_currency($totalCostHour) }} / hr</span>
                    </div>
                    <div class="card-body bg-white p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="d-flex align-items-baseline gap-1">
                                <span class="fs-22 fw-bolder text-primary font-monospace">{{ format_currency($totalDailyCost) }}</span>
                                <span class="fs-11 text-muted">/ day</span>
                            </div>
                        </div>
                        @if($wcCostPerUnitVal > 0)
                            <div class="text-end">
                                <span class="badge bg-soft-success text-success border border-success-subtle font-monospace fs-11 px-2.5 py-1">
                                    <i class="feather-tag me-1"></i>{{ format_currency($wcCostPerUnitVal) }} / unit
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Shifts Section -->
        <div class="mb-4 pb-4 border-bottom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0">{{ __('production.assigned_shifts') }}</h5>
                    <small class="text-muted">Effective shifts derived from active sections</small>
                </div>
            </div>

            @if($workCenter->effective_shifts->count() > 0)
                <div class="row g-3">
                    @foreach($workCenter->effective_shifts as $shift)
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-soft-primary text-primary font-monospace fs-10 px-2">{{ $shift->code }}</span>
                                    @if($shift->active)
                                        <span class="badge bg-soft-success text-success fs-9 rounded-pill px-2">{{ __('production.active') }}</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary fs-9 rounded-pill px-2">{{ __('production.inactive') }}</span>
                                    @endif
                                </div>
                                <h6 class="fw-bold text-dark mb-1 fs-13">{{ $shift->name }}</h6>
                                <div class="text-muted fs-12 mt-2">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="feather-clock text-muted fs-13"></i>
                                        <span>{{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="feather-coffee text-muted fs-13"></i>
                                        <span>{{ __('production.break_time', ['minutes' => $shift->break_minutes]) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-4 bg-light rounded text-muted fs-13 border border-dashed">
                    <i class="feather-info me-2 fs-16"></i>No active shifts configured across sections.
                </div>
            @endif
        </div>

        <!-- Sections Management Section -->
        <div class="mb-4 pb-4 border-bottom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0">{{ __('production.work_center_sections') }}</h5>
                    <small class="text-muted">{{ __('production.section_management') }}</small>
                </div>
                @can('create', App\Domains\Production\Models\WorkCenterSection::class)
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createSectionModal">
                        <i class="feather-plus me-1"></i>{{ __('production.add_section') }}
                    </button>
                @endcan
            </div>

            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 20%">{{ __('production.section_name') }}</th>
                            <th style="width: 12%">{{ __('production.section_shifts') }}</th>
                            <th style="width: 27%">Resources & Workforce</th>
                            <th style="width: 15%">Daily Capacity</th>
                            <th style="width: 16%">Daily Operating Cost</th>
                            <th style="width: 6%">{{ __('production.status') }}</th>
                            <th style="width: 4%" class="text-end">{{ __('production.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workCenter->sections as $section)
                            @php
                                $secCalc = $section->latestCalculation;
                                $capPerHour = (float) ($secCalc?->capacity_per_hour ?? 0);
                                $dailyCap = (float) ($secCalc?->daily_capacity ?? 0);
                                $laborCost = (float) ($secCalc?->labor_cost_per_hour ?? 0);
                                $dailyLaborCost = (float) ($secCalc?->total_daily_labor_cost ?? 0);
                                $machCost = (float) ($secCalc?->machine_cost_per_hour ?? 0);
                                $dailyMachCost = (float) ($secCalc?->total_daily_machine_cost ?? 0);
                                $dailyOperatingCost = (float) ($secCalc?->total_daily_operating_cost ?? 0);
                                $hasBreaks = !empty($secCalc?->calculation_details['breaks'] ?? []);
                                $manualBatchesCount = $section->manualProductions ? $section->manualProductions->count() : 0;
                                $activeBatchesCount = $section->manualProductions ? $section->manualProductions->where('active', true)->count() : 0;

                                // Internal Employees
                                $activeSecEmpCount = $section->employeeAssignments ? $section->employeeAssignments->where('active', true)->count() : 0;
                                if ($activeSecEmpCount > 0) {
                                    $empListHtml = '<ul class="list-unstyled mb-0 text-start fs-12">';
                                    foreach ($section->employeeAssignments->where('active', true) as $assign) {
                                        $empCode = $assign->employee?->code ?? 'EMP';
                                        $empName = $assign->employee?->full_name ?? $assign->employee?->name ?? 'Employee';
                                        $empShift = $assign->employee?->shift;
                                        $shName = $empShift ? ($empShift->code ?: $empShift->name) : 'No shift';
                                        $empListHtml .= '<li class="py-1 border-bottom border-light d-flex align-items-center justify-content-between gap-1">'
                                            . '<div><span class="badge bg-soft-info text-info font-monospace fs-10 me-1">' . e($empCode) . '</span>'
                                            . '<span class="text-dark">' . e($empName) . '</span></div>'
                                            . '<span class="badge bg-soft-success text-success font-monospace fs-10">' . e($shName) . '</span>'
                                            . '</li>';
                                    }
                                    $empListHtml .= '</ul>';
                                } else {
                                    $empListHtml = '<div class="text-muted fs-11 fst-italic py-1 text-center"><i class="feather-info me-1"></i>No employees assigned</div>';
                                }

                                // External Workers
                                $secExtAllocs = $section->externalWorkerAllocations ? $section->externalWorkerAllocations->where('active', true) : collect();
                                $activeSecExtCount = (int) $secExtAllocs->sum('number_of_external_workers');
                                if ($activeSecExtCount > 0) {
                                    $extListHtml = '<ul class="list-unstyled mb-0 text-start fs-12">';
                                    foreach ($secExtAllocs as $alloc) {
                                        $pgName = $alloc->payGrade?->name ?? 'Worker';
                                        $wCount = $alloc->number_of_external_workers;
                                        $shCode = $alloc->shift ? ($alloc->shift->code ?: substr($alloc->shift->name, 0, 2)) : '';
                                        $extListHtml .= '<li class="py-1 border-bottom border-light d-flex align-items-center justify-content-between gap-1">'
                                            . '<div><span class="fw-medium text-dark">' . e($pgName) . '</span></div>'
                                            . '<div class="d-flex align-items-center gap-1">'
                                            . '<span class="badge bg-soft-primary text-primary font-monospace fs-10">' . $wCount . ' ' . \Illuminate\Support\Str::plural('Worker', $wCount) . '</span>'
                                            . ($shCode ? '<span class="badge bg-soft-success text-success font-monospace fs-10">' . e($shCode) . '</span>' : '')
                                            . '</div></li>';
                                    }
                                    $extListHtml .= '</ul>';
                                } else {
                                    $extListHtml = '<div class="text-muted fs-11 fst-italic py-1 text-center"><i class="feather-info me-1"></i>No external workers allocated</div>';
                                }

                                $totalSecPersonnel = $activeSecEmpCount + $activeSecExtCount;

                                // Assigned Machines
                                $machineCount = $section->machines ? $section->machines->count() : 0;
                                if ($machineCount > 0) {
                                    $machineListHtml = '<ul class="list-unstyled mb-0 text-start fs-12">';
                                    foreach ($section->machines as $sm) {
                                        $machCostInfo = $sm->operating_cost_per_hour > 0 ? (' &bull; ' . format_currency($sm->operating_cost_per_hour) . '/hr') : '';
                                        $machineListHtml .= '<li class="py-1 border-bottom border-light d-flex align-items-center justify-content-between gap-1">'
                                            . '<div><i class="feather-cpu text-secondary fs-11 me-1"></i>'
                                            . '<span class="badge bg-soft-secondary text-secondary font-monospace fs-10 me-1">' . e($sm->code) . '</span>'
                                            . '<span class="text-dark">' . e($sm->name) . '</span></div>'
                                            . '<span class="text-muted fs-11 font-monospace">' . $machCostInfo . '</span>'
                                            . '</li>';
                                    }
                                    $machineListHtml .= '</ul>';
                                } else {
                                    $machineListHtml = '<div class="text-muted fs-11 fst-italic py-1 text-center"><i class="feather-info me-1"></i>No machines assigned</div>';
                                }

                                // Manual Batches
                                if ($manualBatchesCount > 0) {
                                    $batchListHtml = '<ul class="list-unstyled mb-0 text-start fs-12">';
                                    foreach ($section->manualProductions as $batch) {
                                        $shCode = $batch->shift ? ($batch->shift->code ?: substr($batch->shift->name, 0, 2)) : 'N/A';
                                        $statusBadge = $batch->active
                                            ? '<span class="badge bg-soft-success text-success fs-9 px-1 py-0">Active</span>'
                                            : '<span class="badge bg-soft-secondary text-muted fs-9 px-1 py-0">Inactive</span>';
                                        $batchListHtml .= '<li class="py-1 border-bottom border-light d-flex align-items-center justify-content-between gap-1">'
                                            . '<div><i class="feather-tool text-warning fs-11 me-1"></i>'
                                            . '<span class="fw-semibold text-dark">' . e($batch->batch_name) . '</span> '
                                            . '<span class="badge bg-soft-success text-success font-monospace fs-10">' . e($shCode) . '</span></div>'
                                            . '<div class="d-flex align-items-center gap-1.5">'
                                            . '<span class="text-muted fs-11 font-monospace">' . number_format((float) ($batch->capacity_per_hour ?? 0), 1) . ' u/h</span>'
                                            . $statusBadge
                                            . '</div></li>';
                                    }
                                    $batchListHtml .= '</ul>';
                                } else {
                                    $batchListHtml = '<div class="text-muted fs-11 fst-italic py-1 text-center"><i class="feather-info me-1"></i>No manual production batches configured</div>';
                                }

                                // Capacity & Operating Cost Popovers (Directly read from dedicated table columns; zero JSON decode)
                                $machHourlyCap = (float) ($secCalc?->hourly_machine_capacity ?? $secCalc?->calculation_details['totals']['hourly_machine_capacity'] ?? 0);
                                $machDailyCap  = (float) ($secCalc?->daily_machine_capacity ?? $secCalc?->calculation_details['totals']['daily_machine_capacity'] ?? 0);
                                $manHourlyCap  = (float) ($secCalc?->hourly_manual_capacity ?? $secCalc?->calculation_details['totals']['hourly_manual_capacity'] ?? 0);
                                $manDailyCap   = (float) ($secCalc?->daily_manual_capacity ?? $secCalc?->calculation_details['totals']['daily_manual_capacity'] ?? 0);
                                $totalDailyCap = (float) ($secCalc?->daily_capacity ?? 0);
                                $totalHourlyCap= (float) ($secCalc?->capacity_per_hour ?? 0);

                                $dailyOperatingCost = (float) ($secCalc?->total_daily_operating_cost ?? 0);
                                $hourlyOperatingCost = (float) ($secCalc?->total_operating_cost_per_hour ?? 0);
                                $dailyLaborCost = (float) ($secCalc?->total_daily_labor_cost ?? 0);
                                $hourlyLaborCost = (float) ($secCalc?->labor_cost_per_hour ?? 0);
                                $dailyMachCost = (float) ($secCalc?->total_daily_machine_cost ?? 0);
                                $hourlyMachCost = (float) ($secCalc?->machine_cost_per_hour ?? 0);

                                $intLaborDaily = (float) ($secCalc?->total_daily_internal_labor_cost ?? $secCalc?->calculation_details['internal_labor']['total_daily_internal_labor_cost'] ?? 0);
                                $intLaborRate = (float) ($secCalc?->internal_labor_cost_per_hour ?? $secCalc?->calculation_details['internal_labor']['average_internal_hourly_rate'] ?? 0);
                                $extLaborDaily = (float) ($secCalc?->total_daily_external_labor_cost ?? $secCalc?->calculation_details['external_labor']['total_daily_external_labor_cost'] ?? 0);
                                $extLaborRate = (float) ($secCalc?->external_labor_cost_per_hour ?? $secCalc?->calculation_details['external_labor']['average_external_hourly_rate'] ?? 0);

                                if ($secCalc) {
                                    // 1. Capacity Popover (Simple 3-column table)
                                    $capacityPopoverHtml = '<div class="p-0">'
                                        . '<table class="table table-sm table-borderless align-middle mb-0 fs-11" style="min-width: 350px;">'
                                        . '<thead class="border-bottom text-muted fs-10 text-uppercase fw-semibold" style="background-color: #f8fafc;">'
                                        . '<tr>'
                                        . '<th class="py-2 ps-3 text-start">Capacity Source</th>'
                                        . '<th class="py-2 text-end">Total Units / Day</th>'
                                        . '<th class="py-2 pe-3 text-end">Avg Units / Hour</th>'
                                        . '</tr>'
                                        . '</thead>'
                                        . '<tbody>'
                                        . '<tr class="border-bottom border-light">'
                                        . '<td class="py-2 ps-3 text-dark text-nowrap"><i class="feather-cpu text-secondary me-1.5"></i>Machine Output</td>'
                                        . '<td class="py-2 text-end font-monospace fw-semibold text-dark">' . number_format($machDailyCap, 1) . ' <span class="fs-10 text-muted">u/d</span></td>'
                                        . '<td class="py-2 pe-3 text-end font-monospace text-muted">' . number_format($machHourlyCap, 1) . ' <span class="fs-10">u/h</span></td>'
                                        . '</tr>'
                                        . '<tr class="border-bottom border-light">'
                                        . '<td class="py-2 ps-3 text-dark text-nowrap"><i class="feather-tool text-warning me-1.5"></i>Manual Batches</td>'
                                        . '<td class="py-2 text-end font-monospace fw-semibold text-dark">' . number_format($manDailyCap, 1) . ' <span class="fs-10 text-muted">u/d</span></td>'
                                        . '<td class="py-2 pe-3 text-end font-monospace text-muted">' . number_format($manHourlyCap, 1) . ' <span class="fs-10">u/h</span></td>'
                                        . '</tr>'
                                        . '</tbody>'
                                        . '<tfoot class="border-top border-light-subtle fw-bold" style="background-color: #f1f5f9;">'
                                        . '<tr>'
                                        . '<td class="py-2 ps-3 text-dark"><i class="feather-activity text-primary me-1.5"></i>Total Capacity</td>'
                                        . '<td class="py-2 text-end font-monospace text-primary fw-bolder fs-12">' . number_format($totalDailyCap, 1) . ' <span class="fs-10">u/d</span></td>'
                                        . '<td class="py-2 pe-3 text-end font-monospace text-dark">' . number_format($totalHourlyCap, 1) . ' <span class="fs-10">u/h</span></td>'
                                        . '</tr>'
                                        . '</tfoot>'
                                        . '</table>'
                                        . '</div>';

                                    // 2. Cost Popover (Simple 3-column table)
                                    $costPopoverHtml = '<div class="p-0">'
                                        . '<table class="table table-sm table-borderless align-middle mb-0 fs-11" style="min-width: 350px;">'
                                        . '<thead class="border-bottom text-muted fs-10 text-uppercase fw-semibold" style="background-color: #f8fafc;">'
                                        . '<tr>'
                                        . '<th class="py-2 ps-3 text-start">Cost Component</th>'
                                        . '<th class="py-2 text-end">Daily Cost</th>'
                                        . '<th class="py-2 pe-3 text-end">Hourly Cost</th>'
                                        . '</tr>'
                                        . '</thead>'
                                        . '<tbody>'
                                        . '<tr class="border-bottom border-light">'
                                        . '<td class="py-2 ps-3 text-dark text-nowrap"><i class="feather-users text-info me-1.5"></i>Labor Cost</td>'
                                        . '<td class="py-2 text-end font-monospace fw-bold text-dark text-nowrap">' . format_currency($dailyLaborCost) . '</td>'
                                        . '<td class="py-2 pe-3 text-end font-monospace text-muted text-nowrap">' . format_currency($hourlyLaborCost) . '/hr</td>'
                                        . '</tr>';

                                    if ($intLaborDaily > 0 || $extLaborDaily > 0) {
                                        $costPopoverHtml .= '<tr class="text-muted fs-10 border-bottom border-light" style="background-color: #fafbfc;">'
                                            . '<td class="py-1 ps-4 text-secondary text-nowrap">&bull; Internal Labor</td>'
                                            . '<td class="py-1 text-end font-monospace text-nowrap">' . format_currency($intLaborDaily) . '</td>'
                                            . '<td class="py-1 pe-3 text-end font-monospace text-nowrap">' . format_currency($intLaborRate) . '/hr</td>'
                                            . '</tr>'
                                            . '<tr class="text-muted fs-10 border-bottom border-light" style="background-color: #fafbfc;">'
                                            . '<td class="py-1 ps-4 text-secondary text-nowrap">&bull; External Workforce</td>'
                                            . '<td class="py-1 text-end font-monospace text-nowrap">' . format_currency($extLaborDaily) . '</td>'
                                            . '<td class="py-1 pe-3 text-end font-monospace text-nowrap">' . format_currency($extLaborRate) . '/hr</td>'
                                            . '</tr>';
                                    }

                                    $costPopoverHtml .= '<tr class="border-bottom border-light">'
                                        . '<td class="py-2 ps-3 text-dark text-nowrap"><i class="feather-cpu text-secondary me-1.5"></i>Machine Expense</td>'
                                        . '<td class="py-2 text-end font-monospace fw-bold text-dark text-nowrap">' . format_currency($dailyMachCost) . '</td>'
                                        . '<td class="py-2 pe-3 text-end font-monospace text-muted text-nowrap">' . format_currency($hourlyMachCost) . '/hr</td>'
                                        . '</tr>'
                                        . '</tbody>'
                                        . '<tfoot class="border-top fw-bold" style="background-color: #f1f5f9;">'
                                        . '<tr>'
                                        . '<td class="py-2 ps-3 text-primary text-nowrap"><i class="feather-dollar-sign me-1.5"></i>Total Operating Cost</td>'
                                        . '<td class="py-2 text-end font-monospace text-primary fs-12 text-nowrap">' . format_currency($dailyOperatingCost) . '</td>'
                                        . '<td class="py-2 pe-3 text-end font-monospace text-primary fs-12 text-nowrap">' . format_currency($hourlyOperatingCost) . '/hr</td>'
                                        . '</tr>'
                                        . '</tfoot>'
                                        . '</table>'
                                        . '</div>';
                                } else {
                                    // Simple clean warning when uncalculated (No detailed 0s table)
                                    $capacityPopoverHtml = '<div class="p-3 text-start" style="min-width: 260px;">'
                                        . '<div class="d-flex align-items-center gap-1.5 text-warning mb-1">'
                                        . '<i class="feather-alert-triangle fs-14"></i><strong class="text-dark fs-12">Calculation Pending</strong>'
                                        . '</div>'
                                        . '<p class="text-muted fs-11 mb-0 leading-tight">Initial calculation snapshot has not been generated yet. Values will update upon recalculation.</p>'
                                        . '</div>';

                                    $costPopoverHtml = '<div class="p-3 text-start" style="min-width: 260px;">'
                                        . '<div class="d-flex align-items-center gap-1.5 text-warning mb-1">'
                                        . '<i class="feather-alert-triangle fs-14"></i><strong class="text-dark fs-12">Calculation Pending</strong>'
                                        . '</div>'
                                        . '<p class="text-muted fs-11 mb-0 leading-tight">Initial calculation snapshot has not been generated yet. Costs will compute upon recalculation.</p>'
                                        . '</div>';
                                }
                            @endphp
                            <tr>
                                <!-- 1. Section Code Badge + Name -->
                                <td class="align-middle">
                                    <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                                        <span class="section-code-badge me-1">{{ $section->code }}</span>
                                        <a href="javascript:void(0)" 
                                           class="fw-bold text-dark text-decoration-none section-details-trigger fs-13"
                                           data-bs-toggle="modal" 
                                           data-bs-target="#sectionDetailsModal_{{ $section->id }}"
                                           title="View Section Details">
                                            <span>{{ $section->name }}</span>
                                            <i class="feather-maximize-2 text-primary fs-10 ms-1.5 opacity-75"></i>
                                        </a>
                                    </div>
                                </td>

                                <!-- 2. Shifts (Clean Badges with Tooltips) -->
                                <td class="align-middle">
                                    <div class="d-flex align-items-center flex-wrap gap-1">
                                        @forelse ($section->shifts as $shift)
                                            @php
                                                $shiftCode = $shift->code ?: strtoupper(substr($shift->name, 0, 2));
                                                $shiftWindow = substr($shift->start_time, 0, 5) . '–' . substr($shift->end_time, 0, 5);
                                                $shiftWorkingHrs = $shift->working_hours_formatted ?? ($shift->working_hours ? format_hours($shift->working_hours) : '8.00 hrs');
                                                $shiftTooltip = e($shift->name . ' (' . $shiftWindow . ' · ' . $shiftWorkingHrs . ')');
                                            @endphp
                                            <span class="section-shift-pill"
                                                  role="button"
                                                  tabindex="0"
                                                  data-bs-toggle="tooltip"
                                                  data-bs-placement="top"
                                                  title="{{ $shiftTooltip }}">
                                                <i class="feather-clock fs-10 opacity-75"></i>{{ $shiftCode }}
                                            </span>
                                        @empty
                                            <span class="text-muted fs-11 fst-italic">No shifts</span>
                                        @endforelse
                                    </div>
                                </td>

                                <!-- 3. Resources & Workforce Cluster (4 Pills) -->
                                <td class="align-middle">
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                        <!-- Pill 1: Internal Employees -->
                                        <span class="badge bg-soft-info text-info border border-info-subtle rounded-pill px-2.5 py-1 fs-11 resource-cluster-pill"
                                              role="button"
                                              tabindex="0"
                                              data-bs-toggle="popover"
                                              data-bs-trigger="hover focus"
                                              data-bs-placement="top"
                                              data-bs-html="true"
                                              data-bs-custom-class="section-popover"
                                              data-bs-title="<div class='fw-bold fs-12 text-dark d-flex align-items-center gap-1'><i class='feather-user-check text-info fs-12'></i>Internal Employees ({{ $activeSecEmpCount }})</div>"
                                              data-bs-content="{{ $empListHtml }}"
                                              data-modal-target="#sectionEmployeesModal_{{ $section->id }}"
                                              data-modal-tab="internal"
                                              title="Click to manage Internal Employees">
                                            <i class="feather-user me-1 fs-11"></i>{{ $activeSecEmpCount }} Emp
                                        </span>

                                        <!-- Pill 2: External Workers -->
                                        <span class="badge bg-soft-primary text-primary border border-primary-subtle rounded-pill px-2.5 py-1 fs-11 resource-cluster-pill"
                                              role="button"
                                              tabindex="0"
                                              data-bs-toggle="popover"
                                              data-bs-trigger="hover focus"
                                              data-bs-placement="top"
                                              data-bs-html="true"
                                              data-bs-custom-class="section-popover"
                                              data-bs-title="<div class='fw-bold fs-12 text-dark d-flex align-items-center gap-1'><i class='feather-users text-primary fs-12'></i>External Workers ({{ $activeSecExtCount }})</div>"
                                              data-bs-content="{{ $extListHtml }}"
                                              data-modal-target="#sectionEmployeesModal_{{ $section->id }}"
                                              data-modal-tab="external"
                                              title="Click to allocate External Workers">
                                            <i class="feather-users me-1 fs-11"></i>{{ $activeSecExtCount }} Ext
                                        </span>

                                        <!-- Pill 3: Assigned Machines -->
                                        <span class="badge bg-soft-secondary text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 fs-11 resource-cluster-pill"
                                              role="button"
                                              tabindex="0"
                                              data-bs-toggle="popover"
                                              data-bs-trigger="hover focus"
                                              data-bs-placement="top"
                                              data-bs-html="true"
                                              data-bs-custom-class="section-popover"
                                              data-bs-title="<div class='fw-bold fs-12 text-dark d-flex align-items-center gap-1'><i class='feather-cpu text-secondary fs-12'></i>Assigned Machines ({{ $machineCount }})</div>"
                                              data-bs-content="{{ $machineListHtml }}">
                                            <i class="feather-cpu me-1 fs-11"></i>{{ $machineCount }} Mach
                                        </span>

                                        <!-- Pill 4: Manual Production Batches -->
                                        @if($manualBatchesCount > 0)
                                            <span class="badge bg-soft-warning text-warning border border-warning-subtle rounded-pill px-2.5 py-1 fs-11 resource-cluster-pill"
                                                  role="button"
                                                  tabindex="0"
                                                  data-bs-toggle="popover"
                                                  data-bs-trigger="hover focus"
                                                  data-bs-placement="top"
                                                  data-bs-html="true"
                                                  data-bs-custom-class="section-popover"
                                                  data-bs-title="<div class='fw-bold fs-12 text-dark d-flex align-items-center gap-1'><i class='feather-tool text-warning fs-12'></i>Manual Production Batches ({{ $manualBatchesCount }})</div>"
                                                  data-bs-content="{{ $batchListHtml }}"
                                                  data-modal-target="#sectionManualProductionModal_{{ $section->id }}"
                                                  title="Click to configure Manual Production Batches">
                                                <i class="feather-tool me-1 fs-11"></i>{{ $manualBatchesCount }} {{ \Illuminate\Support\Str::plural('Manual Batch', $manualBatchesCount) }}
                                            </span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 fs-11 resource-cluster-pill"
                                                  role="button"
                                                  tabindex="0"
                                                  data-bs-toggle="popover"
                                                  data-bs-trigger="hover focus"
                                                  data-bs-placement="top"
                                                  data-bs-html="true"
                                                  data-bs-custom-class="section-popover"
                                                  data-bs-title="<div class='fw-bold fs-12 text-dark d-flex align-items-center gap-1'><i class='feather-tool text-secondary fs-12'></i>Manual Production Batches (0)</div>"
                                                  data-bs-content="{{ $batchListHtml }}"
                                                  data-modal-target="#sectionManualProductionModal_{{ $section->id }}"
                                                  title="Click to configure Manual Production Batches">
                                                <i class="feather-tool me-1 fs-11"></i>No Manual Batch
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <!-- 4. Daily Capacity (with Hover Breakdown Popover) -->
                                <td class="align-middle">
                                    <div class="d-flex flex-column align-items-start capacity-popover-trigger"
                                         role="button"
                                         tabindex="0"
                                         data-bs-toggle="popover"
                                         data-bs-trigger="hover focus"
                                         data-bs-placement="top"
                                         data-bs-html="true"
                                         data-bs-custom-class="section-table-popover"
                                         data-bs-title="<div class='fw-bold fs-12 text-dark d-flex align-items-center gap-1'><i class='feather-activity text-success fs-12'></i>Daily Capacity Breakdown</div>"
                                         data-bs-content="{{ $capacityPopoverHtml }}"
                                         data-bs-target="#sectionCalculationModal_{{ $section->id }}">
                                        <div class="d-flex align-items-center gap-1 flex-nowrap">
                                            <span class="fw-bold font-monospace text-dark fs-13">
                                                {{ number_format($dailyCap, 1) }}
                                            </span>
                                            <small class="text-muted fs-10">units/day</small>
                                            <i class="feather-info text-primary opacity-75 fs-11 ms-1" 
                                               role="button"
                                               data-bs-toggle="modal" 
                                               data-bs-target="#sectionCalculationModal_{{ $section->id }}"
                                               title="{{ __('production.view_calculation_breakdown') }}"
                                               style="cursor: pointer;"></i>
                                        </div>
                                        <small class="text-muted fs-10 font-monospace">Avg: {{ number_format($capPerHour, 1) }}/hr</small>
                                    </div>
                                </td>

                                <!-- 5. Daily Operating Cost (with Hover Breakdown Popover) -->
                                <td class="align-middle">
                                    <div class="d-flex flex-column align-items-start cost-popover-trigger"
                                         role="button"
                                         tabindex="0"
                                         data-bs-toggle="popover"
                                         data-bs-trigger="hover focus"
                                         data-bs-placement="top"
                                         data-bs-html="true"
                                         data-bs-custom-class="section-table-popover"
                                         data-bs-title="<div class='fw-bold fs-12 text-dark d-flex align-items-center gap-1'><i class='feather-dollar-sign text-warning fs-12'></i>Daily Operating Cost Breakdown</div>"
                                         data-bs-content="{{ $costPopoverHtml }}"
                                         data-bs-target="#sectionCalculationModal_{{ $section->id }}">
                                        <div class="d-flex align-items-center gap-1.5 flex-nowrap">
                                            <span class="fw-bold font-monospace text-primary fs-12">
                                                {{ format_currency($dailyOperatingCost) }}
                                            </span>
                                            <small class="text-muted fs-10">/day</small>
                                            <i class="feather-info text-primary opacity-75 fs-11 ms-1" 
                                               role="button"
                                               data-bs-toggle="modal" 
                                               data-bs-target="#sectionCalculationModal_{{ $section->id }}"
                                               title="{{ __('production.view_calculation_breakdown') }}"
                                               style="cursor: pointer;"></i>
                                        </div>
                                        <small class="text-muted fs-10 font-monospace">Labor: {{ format_currency($dailyLaborCost) }} | Mach: {{ format_currency($dailyMachCost) }}</small>
                                        @if($hasBreaks)
                                            <div>
                                                <span class="badge bg-soft-warning text-warning fs-9 px-1.5 py-0.5 rounded-pill" title="Contains shift break gaps in timeline">
                                                    <i class="feather-coffee me-0.5"></i>Break Gaps
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                <!-- 6. Status -->
                                <td class="align-middle">
                                    @if ($section->isActive())
                                        <span class="badge bg-soft-success text-success rounded-pill px-2 py-1">{{ __('production.active') }}</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger rounded-pill px-2 py-1">{{ __('production.inactive') }}</span>
                                    @endif
                                </td>

                                <!-- 7. Actions Dropdown -->
                                <td class="text-end align-middle">
                                    <x-ui.action-dropdown>
                                        <li>
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#sectionDetailsModal_{{ $section->id }}">
                                                <i class="feather-eye me-2 text-primary fs-12"></i>View Section Details
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#sectionCalculationModal_{{ $section->id }}">
                                                <i class="feather-bar-chart-2 me-2 text-primary fs-12"></i>{{ __('production.view_calculation_breakdown') }}
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#sectionEmployeesModal_{{ $section->id }}" data-target-tab="internal">
                                                <i class="feather-user-check me-2 text-info fs-12"></i>{{ __('production.assign_internal_employees') }}
                                            </button>
                                        </li>
                                        @can('create', App\Domains\Production\Models\ExternalWorkerAllocation::class)
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#sectionEmployeesModal_{{ $section->id }}" data-target-tab="external">
                                                    <i class="feather-users me-2 text-primary fs-12"></i>Allocate External Workers
                                                </button>
                                            </li>
                                        @endcan
                                        @can('update', $section)
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#sectionManualProductionModal_{{ $section->id }}">
                                                    <i class="feather-tool me-2 text-warning fs-12"></i>{{ __('production.manual_production') }}
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editSectionModal_{{ $section->id }}">
                                                    <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('production.edit_section') }}
                                                </button>
                                            </li>
                                        @endcan

                                        @can('delete', $section)
                                            <li><hr class="dropdown-divider"></li>
                                            @if($section->hasProductionHistory())
                                                <li>
                                                    <button type="button" class="dropdown-item text-muted fst-italic" disabled title="Cannot delete section: This section is linked to recorded production history. Deactivate the section instead.">
                                                        <i class="feather-lock me-2 text-muted fs-12"></i>{{ __('production.delete_section') }} <span class="badge bg-soft-secondary text-secondary ms-1 fs-10">Locked</span>
                                                    </button>
                                                </li>
                                            @else
                                                <li>
                                                    <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteSectionModal_{{ $section->id }}">
                                                        <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('production.delete_section') }}
                                                    </button>
                                                </li>
                                            @endif
                                        @endcan
                                    </x-ui.action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>{{ __('production.no_sections_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        <!-- Work Center Activity & Audit Logs Section -->
        <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="feather-activity me-1.5 text-primary"></i>Activity & Audit History
                    </h5>
                    <small class="text-muted">Real-time log of debounce triggers, calculation rollups, manual batch changes, and configuration updates</small>
                </div>
                <button type="button" class="btn btn-xs btn-outline-primary" data-bs-toggle="modal" data-bs-target="#workCenterCalculationModal">
                    <i class="feather-file-text me-1"></i>Inspect Full Snapshot
                </button>
            </div>

            <div class="card border shadow-none rounded-3 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 fs-12">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 18%">Timestamp</th>
                                <th style="width: 18%">Action Type</th>
                                <th style="width: 44%">Description</th>
                                <th style="width: 20%">Initiated By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($workCenter->activityLogs()->latest()->take(10)->get() as $log)
                                <tr>
                                    <td class="font-monospace text-muted fs-11">
                                        {{ $log->created_at->format('Y-m-d H:i:s') }}
                                        <small class="text-muted d-block fs-10">({{ $log->created_at->diffForHumans() }})</small>
                                    </td>
                                    <td>
                                        @php
                                            $badgeClass = match($log->action_type) {
                                                'calculation_completed' => 'bg-soft-success text-success',
                                                'countdown_armed', 'countdown_reset' => 'bg-soft-warning text-warning',
                                                'manual_bypass' => 'bg-soft-primary text-primary',
                                                'duration_updated' => 'bg-soft-info text-info',
                                                'section_modified' => 'bg-soft-secondary text-secondary',
                                                default => 'bg-light text-dark',
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }} font-monospace fs-10 text-uppercase px-2 py-0.5 rounded-pill">
                                            {{ str_replace('_', ' ', $log->action_type) }}
                                        </span>
                                    </td>
                                    <td class="text-dark">
                                        {{ $log->description }}
                                    </td>
                                    <td>
                                        @if($log->user)
                                            <span class="fw-semibold text-dark"><i class="feather-user me-1 text-muted fs-11"></i>{{ $log->user->name }}</span>
                                        @else
                                            <span class="text-muted fst-italic"><i class="feather-cpu me-1 fs-11"></i>Automated System</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted fst-italic">
                                        <i class="feather-info me-1"></i>No activity logs recorded yet. Calculations or modifications will generate audit logs automatically.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals for Section Management -->

    <!-- Create Section Modal -->
    @can('create', App\Domains\Production\Models\WorkCenterSection::class)
        <div class="modal fade" id="createSectionModal" tabindex="-1" aria-labelledby="createSectionModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <form method="POST" action="{{ route('production.work-center-sections.store') }}" class="modal-content border-0 shadow-lg rounded-3">
                    @csrf
                    <input type="hidden" name="work_center_id" value="{{ $workCenter->id }}">
                    <input type="hidden" name="_modal" value="createSectionModal">

                    <div class="modal-header border-bottom py-3">
                        <h5 class="modal-title fw-bold text-dark fs-15" id="createSectionModalLabel">
                            <i class="feather-plus-circle text-primary me-2"></i>{{ __('production.create_section') }} &mdash; {{ $workCenter->name }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                            <div class="alert alert-soft-warning border-warning d-flex align-items-start gap-2 mb-3 py-2 px-3 fs-11">
                                <i class="feather-alert-triangle text-warning fs-14 mt-0.5 flex-shrink-0"></i>
                                <div>
                                    <strong class="text-dark">{{ __('production.section_operating_hours_assumption_title') }}:</strong>
                                    <span class="text-muted">{{ __('production.section_operating_hours_assumption_body') }}</span>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label fw-bold fs-12 text-dark">{{ __('production.section_name') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" 
                                           placeholder="{{ __('production.section_placeholder_name') }}" 
                                           value="{{ old('_modal') === 'createSectionModal' ? old('name') : '' }}" required>
                                    @if(old('_modal') === 'createSectionModal' && $errors->has('name'))
                                        <div class="text-danger fs-11 mt-1">{{ $errors->first('name') }}</div>
                                    @endif
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-bold fs-12 text-dark">{{ __('production.section_code') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="code" class="form-control font-monospace text-uppercase" 
                                           placeholder="{{ __('production.section_placeholder_code') }}" 
                                           value="{{ old('_modal') === 'createSectionModal' ? old('code') : '' }}" required>
                                    @if(old('_modal') === 'createSectionModal' && $errors->has('code'))
                                        <div class="text-danger fs-11 mt-1">{{ $errors->first('code') }}</div>
                                    @endif
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-bold fs-12 text-dark">{{ __('production.status') }} <span class="text-danger">*</span></label>
                                    <select name="status" class="form-select" required>
                                        <option value="active" @selected((old('_modal') === 'createSectionModal' ? old('status') : 'active') === 'active')>{{ __('production.active') }}</option>
                                        <option value="inactive" @selected((old('_modal') === 'createSectionModal' ? old('status') : '') === 'inactive')>{{ __('production.inactive') }}</option>
                                    </select>
                                    @if(old('_modal') === 'createSectionModal' && $errors->has('status'))
                                        <div class="text-danger fs-11 mt-1">{{ $errors->first('status') }}</div>
                                    @endif
                                </div>

                                <div class="col-12">
                                    @php
                                        $shiftsPool = $availableShifts ?? \App\Domains\Production\Models\ProductionShift::where('tenant_id', $workCenter->tenant_id)->where('active', true)->orderBy('name')->get();
                                        $createOldShifts = is_array(old('shifts')) && old('_modal') === 'createSectionModal' ? old('shifts') : [];
                                    @endphp

                                    <div class="border rounded p-3 bg-white shadow-xs section-shifts-box" id="create_section_shifts_box">
                                        <!-- Header with Add Shift button (Reference Image 2) -->
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom flex-wrap gap-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="feather-clock text-primary fs-14"></i>
                                                <span class="fw-bold fs-12 text-dark text-uppercase tracking-wide">{{ __('production.section_shifts') }} <span class="text-danger">*</span></span>
                                                <span class="badge bg-soft-primary text-primary fs-10 px-2 py-0.5 rounded-pill shifts-count-badge">
                                                    {{ count($createOldShifts) }} {{ __('production.shifts') }}
                                                </span>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold btn-add-shift-toggle" data-box="#create_section_shifts_box">
                                                <i class="feather-plus me-1"></i>{{ __('production.add_shift') }}
                                            </button>
                                        </div>
                                        <small class="text-muted fs-11 d-block mb-3">{{ __('production.section_shifts_helper') }}</small>

                                        <!-- Add Shift Inline Selector Panel -->
                                        <div class="p-2.5 mb-3 bg-light border rounded d-none shift-picker-panel">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-sm-8">
                                                    <select class="form-select form-select-sm shift-picker-select" data-select2-selector="default" style="width: 100%;">
                                                        <option value="">-- {{ __('production.add_shift') }} --</option>
                                                        @foreach($shiftsPool as $sh)
                                                            @php
                                                                $timeRange = substr($sh->start_time, 0, 5) . '–' . substr($sh->end_time, 0, 5);
                                                                $hasTime = str_contains($sh->name, substr($sh->start_time, 0, 5));
                                                            @endphp
                                                            <option value="{{ $sh->id }}" 
                                                                    data-name="{{ $sh->name }}" 
                                                                    data-code="{{ $sh->code }}" 
                                                                    data-time="{{ $timeRange }}">
                                                                {{ $sh->code }} &mdash; {{ $sh->name }}@if(!$hasTime) ({{ $timeRange }})@endif
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-sm-4 d-flex gap-1.5">
                                                    <button type="button" class="btn btn-sm btn-primary flex-grow-1 btn-add-shift-confirm">
                                                        <i class="feather-plus me-1"></i>Add
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-add-shift-cancel">
                                                        Cancel
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Assigned Shifts Structured Container -->
                                        <div class="assigned-shifts-wrapper">
                                            <div class="assigned-shifts-list d-flex flex-column gap-2 mb-2">
                                                @foreach($createOldShifts as $shId)
                                                    @php $shObj = $shiftsPool->firstWhere('id', (int) $shId); @endphp
                                                    @if($shObj)
                                                        <div class="p-2 border rounded bg-light d-flex justify-content-between align-items-center assigned-shift-row" data-shift-id="{{ $shObj->id }}">
                                                            <input type="hidden" name="shifts[]" value="{{ $shObj->id }}">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <i class="feather-clock text-primary fs-12"></i>
                                                                <span class="fw-bold font-monospace text-dark fs-12">[{{ $shObj->code }}]</span>
                                                                <span class="text-dark fs-12">{{ $shObj->name }}</span>
                                                                <span class="text-muted fs-11">({{ substr($shObj->start_time, 0, 5) }}–{{ substr($shObj->end_time, 0, 5) }})</span>
                                                            </div>
                                                            <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2 py-0.5 btn-remove-shift" title="Remove shift">
                                                                <i class="feather-trash-2 me-0.5 fs-10"></i>Remove
                                                            </button>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>

                                            <div class="assigned-shifts-empty p-3 bg-light rounded border border-dashed text-center text-muted fs-12 @if(!empty($createOldShifts)) d-none @endif">
                                                <i class="feather-info text-primary me-1"></i>No shifts assigned yet. Click "<strong>+ {{ __('production.add_shift') }}</strong>" above to assign operating shifts to this Section.
                                            </div>
                                        </div>
                                    </div>

                                    @if(old('_modal') === 'createSectionModal' && $errors->has('shifts'))
                                        <div class="text-danger fs-11 mt-1 d-block">{{ $errors->first('shifts') }}</div>
                                    @endif
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold fs-12 text-dark d-block">
                                        {{ __('production.assigned_machines') }}
                                        <span class="fw-normal text-muted fs-11 ms-1">({{ __('production.assigned_machines_helper') }})</span>
                                    </label>
                                    <input type="hidden" name="sync_machines" value="1">

                                    @php
                                        $selectedMachines = old('_modal') === 'createSectionModal' ? (array) old('machine_ids', []) : [];
                                    @endphp

                                    @if($eligibleMachines->count() > 0)
                                        <select name="machine_ids[]" id="create_section_machine_ids" class="form-select section-machine-select" multiple="multiple" data-select2-selector="default" data-placeholder="{{ __('production.select_machines') }}" style="width: 100%;">
                                            @foreach($eligibleMachines as $m)
                                                <option value="{{ $m->id }}" @selected(in_array($m->id, $selectedMachines))>
                                                    [{{ $m->code }}] {{ $m->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text fs-11 text-muted">{{ __('production.assigned_machines_hint') }}</div>
                                    @else
                                        <div class="text-muted fs-11 fst-italic p-2 bg-light rounded border">
                                            <i class="feather-info me-1"></i>{{ __('production.no_eligible_machines_available') }}
                                        </div>
                                    @endif

                                    @if(old('_modal') === 'createSectionModal' && $errors->has('machine_ids'))
                                        <div class="text-danger fs-11 mt-1 d-block">{{ $errors->first('machine_ids') }}</div>
                                    @endif
                                    @if(old('_modal') === 'createSectionModal' && $errors->has('machine_ids.*'))
                                        <div class="text-danger fs-11 mt-1 d-block">{{ $errors->first('machine_ids.*') }}</div>
                                    @endif
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold fs-12 text-dark">{{ __('production.section_description') }}</label>
                                    <textarea name="description" rows="3" class="form-control fs-12" placeholder="{{ __('production.description') }}...">{{ old('_modal') === 'createSectionModal' ? old('description') : '' }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-check me-1"></i>{{ __('production.save_section') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan

    <!-- Edit and Delete Modals for Each Section -->
    @foreach($workCenter->sections as $section)
        @can('update', $section)
            <div class="modal fade" id="editSectionModal_{{ $section->id }}" tabindex="-1" aria-labelledby="editSectionModalLabel_{{ $section->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <form method="POST" action="{{ route('production.work-center-sections.update', $section->id) }}" class="modal-content border-0 shadow-lg rounded-3">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="work_center_id" value="{{ $workCenter->id }}">
                        <input type="hidden" name="_modal" value="editSectionModal_{{ $section->id }}">

                        <div class="modal-header border-bottom py-3">
                            <h5 class="modal-title fw-bold text-dark fs-15" id="editSectionModalLabel_{{ $section->id }}">
                                <i class="feather-edit text-primary me-2"></i>{{ __('production.edit_section') }} &mdash; {{ $section->code }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body p-4">
                                <div class="alert alert-soft-warning border-warning d-flex align-items-start gap-2 mb-3 py-2 px-3 fs-11">
                                    <i class="feather-alert-triangle text-warning fs-14 mt-0.5 flex-shrink-0"></i>
                                    <div>
                                        <strong class="text-dark">{{ __('production.section_operating_hours_assumption_title') }}:</strong>
                                        <span class="text-muted">{{ __('production.section_operating_hours_assumption_body') }}</span>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label class="form-label fw-bold fs-12 text-dark">{{ __('production.section_name') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="name" class="form-control" 
                                               value="{{ old('_modal') === 'editSectionModal_'.$section->id ? old('name') : $section->name }}" required>
                                        @if(old('_modal') === 'editSectionModal_'.$section->id && $errors->has('name'))
                                            <div class="text-danger fs-11 mt-1">{{ $errors->first('name') }}</div>
                                        @endif
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label fw-bold fs-12 text-dark">{{ __('production.section_code') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="code" class="form-control font-monospace text-uppercase" 
                                               value="{{ old('_modal') === 'editSectionModal_'.$section->id ? old('code') : $section->code }}" required>
                                        @if(old('_modal') === 'editSectionModal_'.$section->id && $errors->has('code'))
                                            <div class="text-danger fs-11 mt-1">{{ $errors->first('code') }}</div>
                                        @endif
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-bold fs-12 text-dark">{{ __('production.status') }} <span class="text-danger">*</span></label>
                                        <select name="status" class="form-select" required>
                                            @php
                                                $currStatus = old('_modal') === 'editSectionModal_'.$section->id ? old('status') : $section->status;
                                            @endphp
                                            <option value="active" @selected($currStatus === 'active')>{{ __('production.active') }}</option>
                                            <option value="inactive" @selected($currStatus === 'inactive')>{{ __('production.inactive') }}</option>
                                        </select>
                                        @if(old('_modal') === 'editSectionModal_'.$section->id && $errors->has('status'))
                                            <div class="text-danger fs-11 mt-1">{{ $errors->first('status') }}</div>
                                        @endif
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-bold fs-12 text-dark d-block">
                                            {{ __('production.section_shifts') }} <span class="text-danger">*</span>
                                            <span class="fw-normal text-muted fs-11 ms-1">({{ __('production.section_shifts_helper') }})</span>
                                        </label>

                                        @php
                                            $selectedShifts = old('_modal') === 'editSectionModal_'.$section->id 
                                                ? (array) old('shifts', []) 
                                                : $section->shifts->pluck('id')->toArray();
                                        @endphp

                                        <div class="border rounded p-3 bg-white shadow-xs section-shifts-box" id="edit_section_shifts_box_{{ $section->id }}">
                                            <!-- Header with Add Shift button (Reference Image 2) -->
                                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom flex-wrap gap-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="feather-clock text-primary fs-14"></i>
                                                    <span class="fw-bold fs-12 text-dark text-uppercase tracking-wide">{{ __('production.section_shifts') }} <span class="text-danger">*</span></span>
                                                    <span class="badge bg-soft-primary text-primary fs-10 px-2 py-0.5 rounded-pill shifts-count-badge">
                                                        {{ count($selectedShifts) }} {{ __('production.shifts') }}
                                                    </span>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold btn-add-shift-toggle" data-box="#edit_section_shifts_box_{{ $section->id }}">
                                                    <i class="feather-plus me-1"></i>{{ __('production.add_shift') }}
                                                </button>
                                            </div>
                                            <small class="text-muted fs-11 d-block mb-3">{{ __('production.section_shifts_helper') }}</small>

                                            <!-- Add Shift Inline Selector Panel -->
                                            <div class="p-2.5 mb-3 bg-light border rounded d-none shift-picker-panel">
                                                <div class="row g-2 align-items-center">
                                                    <div class="col-sm-8">
                                                        <select class="form-select form-select-sm shift-picker-select" data-select2-selector="default" style="width: 100%;">
                                                            <option value="">-- {{ __('production.add_shift') }} --</option>
                                                            @foreach($shiftsPool as $sh)
                                                                @php
                                                                    $timeRange = substr($sh->start_time, 0, 5) . '–' . substr($sh->end_time, 0, 5);
                                                                    $hasTime = str_contains($sh->name, substr($sh->start_time, 0, 5));
                                                                @endphp
                                                                <option value="{{ $sh->id }}" 
                                                                        data-name="{{ $sh->name }}" 
                                                                        data-code="{{ $sh->code }}" 
                                                                        data-time="{{ $timeRange }}">
                                                                    {{ $sh->code }} &mdash; {{ $sh->name }}@if(!$hasTime) ({{ $timeRange }})@endif
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-sm-4 d-flex gap-1.5">
                                                        <button type="button" class="btn btn-sm btn-primary flex-grow-1 btn-add-shift-confirm">
                                                            <i class="feather-plus me-1"></i>Add
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-add-shift-cancel">
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Assigned Shifts Structured Container -->
                                            <div class="assigned-shifts-wrapper">
                                                <div class="assigned-shifts-list d-flex flex-column gap-2 mb-2">
                                                    @foreach($selectedShifts as $shId)
                                                        @php $shObj = $shiftsPool->firstWhere('id', (int) $shId); @endphp
                                                        @if($shObj)
                                                            <div class="p-2 border rounded bg-light d-flex justify-content-between align-items-center assigned-shift-row" data-shift-id="{{ $shObj->id }}">
                                                                <input type="hidden" name="shifts[]" value="{{ $shObj->id }}">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <i class="feather-clock text-primary fs-12"></i>
                                                                    <span class="fw-bold font-monospace text-dark fs-12">[{{ $shObj->code }}]</span>
                                                                    <span class="text-dark fs-12">{{ $shObj->name }}</span>
                                                                    <span class="text-muted fs-11">({{ substr($shObj->start_time, 0, 5) }}–{{ substr($shObj->end_time, 0, 5) }})</span>
                                                                </div>
                                                                <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2 py-0.5 btn-remove-shift" title="Remove shift">
                                                                    <i class="feather-trash-2 me-0.5 fs-10"></i>Remove
                                                                </button>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>

                                                <div class="assigned-shifts-empty p-3 bg-light rounded border border-dashed text-center text-muted fs-12 @if(!empty($selectedShifts)) d-none @endif">
                                                    <i class="feather-info text-primary me-1"></i>No shifts assigned yet. Click "<strong>+ {{ __('production.add_shift') }}</strong>" above to assign operating shifts to this Section.
                                                </div>
                                            </div>
                                        </div>

                                        @if(old('_modal') === 'editSectionModal_'.$section->id && $errors->has('shifts'))
                                            <div class="text-danger fs-11 mt-1 d-block">{{ $errors->first('shifts') }}</div>
                                        @endif
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-bold fs-12 text-dark d-block">
                                            {{ __('production.assigned_machines') }}
                                            <span class="fw-normal text-muted fs-11 ms-1">({{ __('production.assigned_machines_helper') }})</span>
                                        </label>
                                        <input type="hidden" name="sync_machines" value="1">

                                        @php
                                            $selectedMachines = old('_modal') === 'editSectionModal_'.$section->id 
                                                ? (array) old('machine_ids', []) 
                                                : $section->machines->pluck('id')->toArray();
                                            $modalMachineOptions = $section->machines->concat($eligibleMachines)->unique('id')->sortBy('code');
                                        @endphp

                                        @if($modalMachineOptions->count() > 0)
                                            <select name="machine_ids[]" id="edit_section_machine_ids_{{ $section->id }}" class="form-select section-machine-select" multiple="multiple" data-select2-selector="default" data-placeholder="{{ __('production.select_machines') }}" style="width: 100%;">
                                                @foreach($modalMachineOptions as $m)
                                                    <option value="{{ $m->id }}" @selected(in_array($m->id, $selectedMachines))>
                                                        [{{ $m->code }}] {{ $m->name }} @if($m->section_id === $section->id) ({{ __('production.currently_assigned') }}) @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text fs-11 text-muted">{{ __('production.assigned_machines_hint') }}</div>
                                        @else
                                            <div class="text-muted fs-11 fst-italic p-2 bg-light rounded border">
                                                <i class="feather-info me-1"></i>{{ __('production.no_eligible_machines_available') }}
                                            </div>
                                        @endif

                                        @if(old('_modal') === 'editSectionModal_'.$section->id && $errors->has('machine_ids'))
                                            <div class="text-danger fs-11 mt-1 d-block">{{ $errors->first('machine_ids') }}</div>
                                        @endif
                                        @if(old('_modal') === 'editSectionModal_'.$section->id && $errors->has('machine_ids.*'))
                                            <div class="text-danger fs-11 mt-1 d-block">{{ $errors->first('machine_ids.*') }}</div>
                                        @endif
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-bold fs-12 text-dark">{{ __('production.section_description') }}</label>
                                        <textarea name="description" rows="3" class="form-control fs-12" placeholder="{{ __('production.description') }}...">{{ old('_modal') === 'editSectionModal_'.$section->id ? old('description') : $section->description }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather-check me-1"></i>{{ __('production.update_section') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
        @endcan

        @can('delete', $section)
            @if(!$section->hasProductionHistory())
            <div class="modal fade" id="deleteSectionModal_{{ $section->id }}" tabindex="-1" aria-labelledby="deleteSectionModalLabel_{{ $section->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-3">
                        <div class="modal-header border-bottom py-3 bg-soft-danger">
                            <h5 class="modal-title fw-bold text-danger fs-15" id="deleteSectionModalLabel_{{ $section->id }}">
                                <i class="feather-alert-triangle text-danger me-2"></i>{{ __('production.delete_section') }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="{{ route('production.work-center-sections.destroy', $section->id) }}">
                            @csrf
                            @method('DELETE')

                            <div class="modal-body p-4">
                                @php
                                    $assignedMachines = $section->machines;
                                    $assignedEmployees = $section->employeeAssignments;
                                    $assignedAllocations = $section->externalWorkerAllocations;
                                    $hasAnyAssignments = $assignedMachines->isNotEmpty() || $assignedEmployees->isNotEmpty() || $assignedAllocations->isNotEmpty();
                                @endphp

                                <p class="text-dark fs-13 mb-2">
                                    Are you sure you want to delete section <strong>{{ $section->name }}</strong> (<span class="font-monospace text-muted">{{ $section->code }}</span>)?
                                </p>

                                @if($hasAnyAssignments)
                                    <div class="alert alert-warning border border-warning d-flex align-items-start gap-2 mb-3">
                                        <i class="feather-alert-circle fs-18 text-warning mt-1 flex-shrink-0"></i>
                                        <div class="fs-12 text-dark">
                                            <div class="fw-bold mb-1">Deleting this section will also remove its associated assignments:</div>
                                            <ul class="mb-0 ps-3">
                                                @if($assignedMachines->isNotEmpty())
                                                    <li>
                                                        <strong>{{ $assignedMachines->count() }} Machine(s)</strong> will become unassigned and returned to the pool:
                                                        <span class="text-muted">{{ $assignedMachines->pluck('name')->join(', ') }}</span>
                                                    </li>
                                                @endif
                                                @if($assignedEmployees->isNotEmpty())
                                                    <li>
                                                        <strong>{{ $assignedEmployees->count() }} Internal Employee Assignment(s)</strong> will be deleted.
                                                    </li>
                                                @endif
                                                @if($assignedAllocations->isNotEmpty())
                                                    <li>
                                                        <strong>{{ $assignedAllocations->count() }} External Worker Allocation(s)</strong> will be deleted.
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                @else
                                    <div class="p-3 bg-light rounded border fs-12 text-muted">
                                        This section currently has 0 machines, internal employees, or external worker allocations assigned.
                                    </div>
                                @endif

                                <p class="text-danger fs-12 fw-semibold mb-0 mt-2">
                                    <i class="feather-alert-triangle me-1"></i>This action will remove the section from this Work Center.
                                </p>
                            </div>

                            <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                                <button type="submit" class="btn btn-danger">
                                    <i class="feather-trash-2 me-1"></i>{{ __('production.delete_section') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif
        @endcan

        <!-- Calculation Breakdown Modal for Section -->
        @include('modules.production.work-centers.partials.section-calculation-modal', ['section' => $section, 'workCenter' => $workCenter])

        <!-- Section Details Modal for Section {{ $section->id }} -->
        @php
            $secDetailsCalc = $section->latestCalculation;
            $secDetailsDailyCap = (float) ($secDetailsCalc?->daily_capacity ?? 0);
            $secDetailsCapPerHour = (float) ($secDetailsCalc?->capacity_per_hour ?? 0);
            $secDetailsDailyOperatingCost = (float) ($secDetailsCalc?->total_daily_operating_cost ?? 0);
            $secDetailsHourlyOperatingCost = (float) ($secDetailsCalc?->total_operating_cost_per_hour ?? 0);
            $secDetailsLaborCost = (float) ($secDetailsCalc?->labor_cost_per_hour ?? 0);
            $secDetailsDailyLaborCost = (float) ($secDetailsCalc?->total_daily_labor_cost ?? 0);
            $secDetailsMachCost = (float) ($secDetailsCalc?->machine_cost_per_hour ?? 0);
            $secDetailsDailyMachCost = (float) ($secDetailsCalc?->total_daily_machine_cost ?? 0);
            
            $secDetailsEmpCount = $section->employeeAssignments ? $section->employeeAssignments->where('active', true)->count() : 0;
            $secDetailsExtAllocs = $section->externalWorkerAllocations ? $section->externalWorkerAllocations->where('active', true) : collect();
            $secDetailsExtCount = (int) $secDetailsExtAllocs->sum('number_of_external_workers');
            $secDetailsTotalPersonnel = $secDetailsEmpCount + $secDetailsExtCount;

            $secDetailsManualBatchesCount = $section->manualProductions ? $section->manualProductions->count() : 0;

            $secDetailsCalcDetails = $secDetailsCalc?->calculation_details ?? [];
            $secDetailsMach = $secDetailsCalcDetails['machinery'] ?? [];
            $secDetailsMan  = $secDetailsCalcDetails['manual_batches'] ?? [];
            $secDetailsFin  = $secDetailsCalcDetails['financial_summary'] ?? [];
            $secDetailsTotals = $secDetailsCalcDetails['totals'] ?? [];

            $secDetailsMachHourlyCap = $secDetailsCalc
                ? (float) ($secDetailsCalc->hourly_machine_capacity ?? $secDetailsMach['hourly_machine_capacity'] ?? $secDetailsTotals['hourly_machine_capacity'] ?? 0)
                : 0.0;
            $secDetailsMachCost = $secDetailsCalc
                ? (float) ($secDetailsCalc->machine_cost_per_hour ?? $secDetailsMach['hourly_operating_cost'] ?? 0)
                : 0.0;
            $secDetailsMachDailyCap  = (float) ($secDetailsCalc?->daily_machine_capacity ?? $secDetailsMach['daily_machine_capacity'] ?? $secDetailsTotals['daily_machine_capacity'] ?? 0);
            $secDetailsManHourlyCap  = (float) ($secDetailsCalc?->hourly_manual_capacity ?? $secDetailsMan['hourly_manual_capacity'] ?? $secDetailsTotals['hourly_manual_capacity'] ?? 0);
            $secDetailsManDailyCap   = (float) ($secDetailsCalc?->daily_manual_capacity ?? $secDetailsMan['daily_manual_capacity'] ?? $secDetailsTotals['daily_manual_capacity'] ?? 0);
            $secDetailsTotalDailyCap = (float) ($secDetailsCalc?->daily_capacity ?? 0);
            $secDetailsTotalHourlyCap= (float) ($secDetailsCalc?->capacity_per_hour ?? 0);
        @endphp
        <div class="modal fade section-details-modal" id="sectionDetailsModal_{{ $section->id }}" tabindex="-1" aria-labelledby="sectionDetailsModalLabel_{{ $section->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header border-bottom py-3 bg-light">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="section-code-badge fs-12 px-2.5 py-1.5 me-2">{{ $section->code }}</span>
                            <h5 class="modal-title fw-bold text-dark fs-16 mb-0" id="sectionDetailsModalLabel_{{ $section->id }}">
                                {{ $section->name }}
                            </h5>
                            @if ($section->isActive())
                                <span class="badge bg-soft-success text-success rounded-pill px-2.5 py-1 fs-11">● Active</span>
                            @else
                                <span class="badge bg-soft-danger text-danger rounded-pill px-2.5 py-1 fs-11">● Inactive</span>
                            @endif
                            <div class="w-100 text-muted fs-12 mt-0.5">
                                Work Center: <span class="fw-semibold text-dark">{{ $workCenter->name }}</span>
                                <span class="badge bg-soft-primary text-primary font-monospace ms-1">{{ $workCenter->code }}</span>
                                @if($section->description)
                                    &bull; <span class="fst-italic">{{ $section->description }}</span>
                                @endif
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                        <!-- Top 4 KPI Metric Cards -->
                        <div class="row g-3 mb-4">
                            <!-- 1. Operating Window -->
                            <div class="col-md-3 col-6">
                                <div class="p-3 rounded-3 bg-light border h-100 d-flex flex-column justify-content-between">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="text-muted fs-11 text-uppercase fw-semibold">Operating Window</span>
                                        <i class="feather-clock text-primary fs-14"></i>
                                    </div>
                                    @php
                                        $uohHoursVal = (float) ($secDetailsCalc?->unique_operating_hours ?? 0);
                                        if ($uohHoursVal <= 0 && $section->shifts->isNotEmpty()) {
                                            $totalShiftMin = 0;
                                            foreach ($section->shifts as $s) {
                                                $totalShiftMin += (int) round(($s->working_hours ?? 8.0) * 60);
                                            }
                                            $uohFormatted = sprintf('%d:%02d', intdiv($totalShiftMin, 60), $totalShiftMin % 60);
                                            $uohNote = 'Shift schedule sum (Uncalculated Fallback)';
                                        } else {
                                            $uohTotalMin = (int) round($uohHoursVal * 60);
                                            $uohFormatted = sprintf('%d:%02d', intdiv($uohTotalMin, 60), $uohTotalMin % 60);
                                            $uohNote = $section->shifts->count() . ' configured ' . \Illuminate\Support\Str::plural('shift', $section->shifts->count()) . ' (Snapshot)';
                                        }
                                    @endphp
                                    <div class="fs-20 fw-bold text-dark font-monospace">
                                        {{ $uohFormatted }}
                                        <span class="fs-11 fw-normal text-muted">hrs/day</span>
                                    </div>
                                    <small class="text-muted fs-11 mt-1">
                                        {{ $uohNote }}
                                    </small>
                                </div>
                            </div>

                            <!-- 2. Daily Capacity -->
                            <div class="col-md-3 col-6">
                                <div class="p-3 rounded-3 bg-light border h-100 d-flex flex-column justify-content-between">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="text-muted fs-11 text-uppercase fw-semibold">Daily Capacity</span>
                                        <i class="feather-activity text-success fs-14"></i>
                                    </div>
                                    @if($secDetailsCalc)
                                        <div class="fs-20 fw-bold text-dark font-monospace">
                                            {{ number_format($secDetailsDailyCap, 1) }}
                                            <span class="fs-11 fw-normal text-muted">units/day</span>
                                        </div>
                                        <small class="text-muted fs-11 mt-1">
                                            Avg {{ number_format($secDetailsCapPerHour, 1) }} units/hr
                                        </small>
                                    @else
                                        <div class="my-auto py-1">
                                            <div class="d-flex align-items-center gap-1.5 text-warning mb-0.5">
                                                <i class="feather-alert-triangle fs-12"></i>
                                                <strong class="text-dark fs-12">Calculation Pending</strong>
                                            </div>
                                            <small class="text-muted fs-10 d-block leading-tight">Initial calculation snapshot has not been generated yet. Values will update upon recalculation.</small>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- 3. Daily Operating Cost -->
                            <div class="col-md-3 col-6">
                                <div class="p-3 rounded-3 bg-light border h-100 d-flex flex-column justify-content-between">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="text-muted fs-11 text-uppercase fw-semibold">Daily Operating Cost</span>
                                        <i class="feather-dollar-sign text-warning fs-14"></i>
                                    </div>
                                    @if($secDetailsCalc)
                                        <div class="fs-20 fw-bold text-primary font-monospace">
                                            {{ format_currency($secDetailsDailyOperatingCost) }}
                                        </div>
                                        <small class="text-muted fs-11 mt-1">
                                            {{ format_currency($secDetailsHourlyOperatingCost) }}/hr
                                        </small>
                                    @else
                                        <div class="my-auto py-1">
                                            <div class="d-flex align-items-center gap-1.5 text-warning mb-0.5">
                                                <i class="feather-alert-triangle fs-12"></i>
                                                <strong class="text-dark fs-12">Calculation Pending</strong>
                                            </div>
                                            <small class="text-muted fs-10 d-block leading-tight">Initial calculation snapshot has not been generated yet. Values will update upon recalculation.</small>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- 4. Total Workforce -->
                            <div class="col-md-3 col-6">
                                <div class="p-3 rounded-3 bg-light border h-100 d-flex flex-column justify-content-between">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="text-muted fs-11 text-uppercase fw-semibold">Total Workforce</span>
                                        <i class="feather-users text-info fs-14"></i>
                                    </div>
                                    <div class="fs-20 fw-bold text-dark font-monospace">
                                        {{ $secDetailsTotalPersonnel }}
                                        <span class="fs-11 fw-normal text-muted">Personnel</span>
                                    </div>
                                    <small class="text-muted fs-11 mt-1">
                                        {{ $secDetailsEmpCount }} Internal + {{ $secDetailsExtCount }} External
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs nav-tabs-bottom mb-3" id="secDetailsTabs_{{ $section->id }}" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-semibold fs-13 py-2 px-3" 
                                        id="sec-shifts-tab-{{ $section->id }}" 
                                        data-bs-toggle="tab" 
                                        data-bs-target="#sec-shifts-pane-{{ $section->id }}" 
                                        type="button" 
                                        role="tab">
                                    <i class="feather-calendar me-1"></i>Shifts & Schedule
                                    <span class="badge bg-soft-secondary text-secondary rounded-pill px-1.5 py-0 fs-10 ms-1">{{ $section->shifts->count() }}</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold fs-13 py-2 px-3" 
                                        id="sec-machines-tab-{{ $section->id }}" 
                                        data-bs-toggle="tab" 
                                        data-bs-target="#sec-machines-pane-{{ $section->id }}" 
                                        type="button" 
                                        role="tab">
                                    <i class="feather-cpu me-1"></i>Machines
                                    <span class="badge bg-soft-secondary text-secondary rounded-pill px-1.5 py-0 fs-10 ms-1">{{ $section->machines->count() }}</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold fs-13 py-2 px-3" 
                                        id="sec-personnel-tab-{{ $section->id }}" 
                                        data-bs-toggle="tab" 
                                        data-bs-target="#sec-personnel-pane-{{ $section->id }}" 
                                        type="button" 
                                        role="tab">
                                    <i class="feather-users me-1"></i>Personnel
                                    <span class="badge bg-soft-info text-info rounded-pill px-1.5 py-0 fs-10 ms-1">{{ $secDetailsTotalPersonnel }}</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold fs-13 py-2 px-3" 
                                        id="sec-batches-tab-{{ $section->id }}" 
                                        data-bs-toggle="tab" 
                                        data-bs-target="#sec-batches-pane-{{ $section->id }}" 
                                        type="button" 
                                        role="tab">
                                    <i class="feather-tool me-1"></i>Manual Batches
                                    <span class="badge bg-soft-warning text-warning rounded-pill px-1.5 py-0 fs-10 ms-1">{{ $secDetailsManualBatchesCount }}</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold fs-13 py-2 px-3" 
                                        id="sec-cost-tab-{{ $section->id }}" 
                                        data-bs-toggle="tab" 
                                        data-bs-target="#sec-cost-pane-{{ $section->id }}" 
                                        type="button" 
                                        role="tab">
                                    <i class="feather-pie-chart me-1"></i>Cost & Capacity
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Panes Content -->
                        <div class="tab-content" id="secDetailsTabContent_{{ $section->id }}">
                            <!-- Pane 1: Shifts & Schedule -->
                            <div class="tab-pane fade show active" id="sec-shifts-pane-{{ $section->id }}" role="tabpanel">
                                <div class="table-responsive border rounded-3">
                                    <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 15%">Shift Code</th>
                                                <th style="width: 25%">Shift Name</th>
                                                <th style="width: 25%">Operating Window</th>
                                                <th style="width: 15%">Net Working Hours</th>
                                                <th style="width: 20%">Workforce On Duty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($section->shifts as $sh)
                                                @php
                                                    $shExtCount = (int) ($section->externalWorkerAllocations ? $section->externalWorkerAllocations->where('shift_id', $sh->id)->where('active', true)->sum('number_of_external_workers') : 0);
                                                    $shEmpCount = (int) ($section->employeeAssignments ? $section->employeeAssignments->where('active', true)->filter(fn($a) => ($a->employee?->shift_id ?? $a->shift_id) == $sh->id)->count() : 0);
                                                    $shCode = $sh->code ?: strtoupper(substr($sh->name, 0, 2));
                                                    $shWorkingDuration = $sh->working_hours_formatted ?? ($sh->working_hours ? format_hours($sh->working_hours) : '8.00 hrs');
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <span class="section-shift-pill fs-11 py-0.5 px-2 mb-0"><i class="feather-clock fs-10 opacity-75"></i>{{ $shCode }}</span>
                                                    </td>
                                                    <td class="fw-semibold text-dark">{{ $sh->name }}</td>
                                                    <td class="font-monospace text-muted">
                                                        {{ substr($sh->start_time, 0, 5) }} &ndash; {{ substr($sh->end_time, 0, 5) }}
                                                    </td>
                                                    <td class="font-monospace fw-semibold text-dark">
                                                        {{ $shWorkingDuration }}
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-soft-info text-info font-monospace fs-10 px-1.5 py-0.5 me-1" title="{{ $shEmpCount }} Internal">{{ $shEmpCount }} Emp</span>
                                                        <span class="badge bg-soft-primary text-primary font-monospace fs-10 px-1.5 py-0.5" title="{{ $shExtCount }} External">{{ $shExtCount }} Ext</span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center py-3 text-muted fst-italic">
                                                        <i class="feather-info me-1"></i>No shifts assigned to this section.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        @if($section->shifts && $section->shifts->isNotEmpty())
                                            @php
                                                $totalNetHours = 0;
                                                $allStartTimes = [];
                                                $allEndTimes = [];
                                                $totalShiftEmp = 0;
                                                $totalShiftExt = 0;
                                                foreach ($section->shifts as $sh) {
                                                    $totalNetHours += (float) ($sh->working_hours ?? 8.0);
                                                    if ($sh->start_time) $allStartTimes[] = substr($sh->start_time, 0, 5);
                                                    if ($sh->end_time) $allEndTimes[] = substr($sh->end_time, 0, 5);
                                                    $totalShiftExt += (int) ($section->externalWorkerAllocations ? $section->externalWorkerAllocations->where('shift_id', $sh->id)->where('active', true)->sum('number_of_external_workers') : 0);
                                                    $totalShiftEmp += (int) ($section->employeeAssignments ? $section->employeeAssignments->where('active', true)->filter(fn($a) => ($a->employee?->shift_id ?? $a->shift_id) == $sh->id)->count() : 0);
                                                }
                                                sort($allStartTimes);
                                                sort($allEndTimes);
                                                $earliestStart = $allStartTimes[0] ?? '--:--';
                                                $latestEnd = end($allEndTimes) ?: '--:--';
                                            @endphp
                                            <tfoot class="table-light border-top">
                                                <tr class="fw-bold">
                                                    <td colspan="2" class="text-dark">
                                                        <i class="feather-calendar text-primary me-1"></i>Combined Schedule Total:
                                                    </td>
                                                    <td class="font-monospace text-muted">
                                                        {{ $earliestStart }} &ndash; {{ $latestEnd }}
                                                        <span class="fs-10 text-muted fw-normal ms-1">({{ count($section->shifts) }} shifts)</span>
                                                    </td>
                                                    <td class="font-monospace text-dark fw-bold">
                                                        {{ format_hours($totalNetHours) }}
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-soft-info text-info font-monospace fs-10 px-1.5 py-0.5 me-1">{{ $totalShiftEmp }} Emp</span>
                                                        <span class="badge bg-soft-primary text-primary font-monospace fs-10 px-1.5 py-0.5">{{ $totalShiftExt }} Ext</span>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            </div>

                            <!-- Pane 2: Machines -->
                            <div class="tab-pane fade" id="sec-machines-pane-{{ $section->id }}" role="tabpanel">
                                <div class="table-responsive border rounded-3">
                                    <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 15%">Machine Code</th>
                                                <th style="width: 25%">Machine Name</th>
                                                <th style="width: 15%">Power (kW)</th>
                                                <th style="width: 20%">Capacity / hr</th>
                                                <th style="width: 25%">Operating Cost / hr</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($section->machines as $sm)
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-soft-secondary text-secondary font-monospace">{{ $sm->code }}</span>
                                                    </td>
                                                    <td class="fw-medium text-dark">{{ $sm->name }}</td>
                                                    <td class="font-monospace text-muted">
                                                        {{ number_format((float) ($sm->consumption_per_hour ?? 0), 2) }} kW
                                                    </td>
                                                    <td class="font-monospace fw-semibold text-success">
                                                        {{ number_format((float) ($sm->capacity ?? 0), 1) }} u/h
                                                    </td>
                                                    <td class="font-monospace fw-semibold text-primary">
                                                        {{ format_currency($sm->operating_cost_per_hour) }}/hr
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center py-3 text-muted fst-italic">
                                                        <i class="feather-info me-1"></i>No machines assigned to this section.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        @if($section->machines && $section->machines->isNotEmpty())
                                            @php
                                                $machTableTotalCap = (float) $section->machines->sum('capacity');
                                                $machTableTotalCost = (float) $section->machines->sum('operating_cost_per_hour');
                                            @endphp
                                            <tfoot class="table-light border-top">
                                                <tr class="fw-bold">
                                                    <td colspan="3" class="text-dark">Total Machine Output:</td>
                                                    <td class="font-monospace text-success">{{ number_format($machTableTotalCap, 1) }} u/h</td>
                                                    <td class="font-monospace text-primary">{{ format_currency($machTableTotalCost) }}/hr</td>
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            </div>

                            <!-- Pane 3: Personnel -->
                            <div class="tab-pane fade" id="sec-personnel-pane-{{ $section->id }}" role="tabpanel">
                                <div class="row g-3">
                                    <!-- Internal Employees -->
                                    <div class="col-md-6">
                                        <div class="card border rounded-3 mb-0 shadow-none">
                                            <div class="card-header bg-light py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                                <span class="fw-bold fs-12 text-dark">
                                                    <i class="feather-user-check text-info me-1"></i>Internal Employees ({{ $secDetailsEmpCount }})
                                                </span>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                                                    <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Code</th>
                                                                <th>Name</th>
                                                                <th>Shift</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @forelse($section->employeeAssignments ? $section->employeeAssignments->where('active', true) : [] as $assign)
                                                                <tr>
                                                                    <td><span class="badge bg-soft-info text-info font-monospace">{{ $assign->employee?->code ?? 'EMP' }}</span></td>
                                                                    <td>{{ $assign->employee?->full_name ?? $assign->employee?->name ?? 'Employee' }}</td>
                                                                    <td class="text-muted font-monospace fs-11">{{ $assign->employee?->shift?->name ?? 'No shift assigned' }}</td>
                                                                </tr>
                                                            @empty
                                                                <tr>
                                                                    <td colspan="3" class="text-center py-3 text-muted fst-italic">No internal employees assigned.</td>
                                                                </tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- External Workers -->
                                    <div class="col-md-6">
                                        <div class="card border rounded-3 mb-0 shadow-none">
                                            <div class="card-header bg-light py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                                <span class="fw-bold fs-12 text-dark">
                                                    <i class="feather-users text-primary me-1"></i>External Workers ({{ $secDetailsExtCount }})
                                                </span>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                                                    <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Pay Grade</th>
                                                                <th>Shift</th>
                                                                <th class="text-center">Count</th>
                                                                <th class="text-end">Rate</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @forelse($secDetailsExtAllocs as $alloc)
                                                                <tr>
                                                                    <td class="fw-medium text-dark">{{ $alloc->payGrade?->name ?? 'Worker' }}</td>
                                                                    <td class="text-muted font-monospace fs-11">{{ $alloc->shift?->name ?? 'Shift' }}</td>
                                                                    <td class="text-center font-monospace fw-bold text-primary">{{ $alloc->number_of_external_workers }}</td>
                                                                    <td class="text-end font-monospace">{{ format_currency((float) ($alloc->payGrade?->hourly_rate ?? 0)) }}/hr</td>
                                                                </tr>
                                                            @empty
                                                                <tr>
                                                                    <td colspan="4" class="text-center py-3 text-muted fst-italic">No external workers allocated.</td>
                                                                </tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pane 4: Manual Batches -->
                            <div class="tab-pane fade" id="sec-batches-pane-{{ $section->id }}" role="tabpanel">
                                <div class="table-responsive border rounded-3">
                                    <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 25%">Batch Name</th>
                                                <th style="width: 25%">Shift</th>
                                                <th style="width: 20%">Target Rate</th>
                                                <th style="width: 20%">Workforce Allocated</th>
                                                <th style="width: 10%">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($section->manualProductions ?: [] as $batch)
                                                @php
                                                    $batchEmpCount = $batch->employees ? $batch->employees->count() : 0;
                                                    $batchExtCount = (int) ($batch->externalAllocations ? $batch->externalAllocations->sum('number_of_external_workers') : 0);
                                                @endphp
                                                <tr>
                                                    <td class="fw-semibold text-dark">
                                                        <i class="feather-tool text-warning me-1"></i>{{ $batch->batch_name }}
                                                    </td>
                                                    <td class="font-monospace text-muted">
                                                        {{ $batch->shift?->name ?? 'Shift' }}
                                                        @if($batch->shift?->code)
                                                            <span class="badge bg-soft-success text-success font-monospace">{{ $batch->shift->code }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="font-monospace fw-bold text-dark">
                                                        {{ number_format((float) ($batch->capacity_per_hour ?? 0), 1) }} u/h
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-soft-info text-info font-monospace fs-10 px-1.5 py-0.5 me-1">{{ $batchEmpCount }} Emp</span>
                                                        <span class="badge bg-soft-primary text-primary font-monospace fs-10 px-1.5 py-0.5">{{ $batchExtCount }} Ext</span>
                                                    </td>
                                                    <td>
                                                        @if($batch->active)
                                                            <span class="badge bg-soft-success text-success rounded-pill px-2 py-0.5">Active</span>
                                                        @else
                                                            <span class="badge bg-soft-secondary text-muted rounded-pill px-2 py-0.5">Inactive</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center py-3 text-muted fst-italic">
                                                        <i class="feather-info me-1"></i>No manual production batches configured for this section.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        @if($section->manualProductions && $section->manualProductions->isNotEmpty())
                                            @php
                                                $totBatchEmp = 0;
                                                $totBatchExt = 0;
                                                foreach($section->manualProductions as $b) {
                                                    $totBatchEmp += (int) ($b->employees ? $b->employees->count() : 0);
                                                    $totBatchExt += (int) ($b->externalAllocations ? $b->externalAllocations->sum('number_of_external_workers') : 0);
                                                }
                                            @endphp
                                            <tfoot class="table-light border-top fw-bold">
                                                <tr>
                                                    <td colspan="2" class="text-dark"><i class="feather-tool text-warning me-1"></i>Total Manual Batch Output:</td>
                                                    <td>
                                                        @if($secDetailsCalc)
                                                            <div class="font-monospace text-dark fw-bold">{{ number_format($secDetailsManDailyCap, 1) }} <small class="text-muted fw-normal">u/d</small></div>
                                                            <small class="text-muted fs-11" title="It is average production per hour throughout section shifts. Formula: {{ number_format($secDetailsManDailyCap, 1) }} units / {{ format_hours($uohHoursVal) }} = {{ number_format($secDetailsManHourlyCap, 1) }} u/h" data-bs-toggle="tooltip">
                                                                Avg: <span class="text-dark font-monospace fw-semibold">{{ number_format($secDetailsManHourlyCap, 1) }} u/h</span>
                                                                <i class="feather-info text-muted ms-0.5" style="font-size: 10px;"></i>
                                                            </small>
                                                        @else
                                                            <div class="font-monospace text-muted fw-semibold">0.0 <small class="text-muted fw-normal">u/d</small></div>
                                                            <small class="text-muted fs-10 fst-italic">Pending calculation</small>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-soft-info text-info font-monospace fs-10 px-1.5 py-0.5 me-1">{{ $totBatchEmp }} Emp</span>
                                                        <span class="badge bg-soft-primary text-primary font-monospace fs-10 px-1.5 py-0.5">{{ $totBatchExt }} Ext</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-soft-success text-success rounded-pill px-2 py-0.5 fs-10">Active</span>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            </div>

                            <!-- Pane 5: Cost & Capacity -->
                            <div class="tab-pane fade" id="sec-cost-pane-{{ $section->id }}" role="tabpanel">
                                @if(!$secDetailsCalc)
                                    <div class="alert alert-soft-warning py-2 px-3 fs-12 mb-3 d-flex align-items-center gap-2 rounded-2 border border-warning-subtle">
                                        <i class="feather-alert-triangle fs-15 text-warning flex-shrink-0"></i>
                                        <span><strong>Calculation Pending:</strong> Initial calculation snapshot has not been generated yet. Values will update upon recalculation.</span>
                                    </div>
                                @endif
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="card border rounded-3 p-3 shadow-none mb-0">
                                            <h6 class="fw-bold text-dark mb-3"><i class="feather-dollar-sign text-warning me-1"></i>Operating Cost Breakdown</h6>
                                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                <span class="text-muted fs-12">Labor Cost per Hour:</span>
                                                <span class="fw-bold font-monospace text-dark fs-13">{{ format_currency($secDetailsLaborCost) }}/hr</span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                <span class="text-muted fs-12">Total Daily Labor Cost:</span>
                                                <span class="fw-bold font-monospace text-dark fs-13">{{ format_currency($secDetailsDailyLaborCost) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                <span class="text-muted fs-12">Machine Cost per Hour:</span>
                                                <span class="fw-bold font-monospace text-dark fs-13">{{ format_currency($secDetailsMachCost) }}/hr</span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                <span class="text-muted fs-12">Total Daily Machine Cost:</span>
                                                <span class="fw-bold font-monospace text-dark fs-13">{{ format_currency($secDetailsDailyMachCost) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center pt-2">
                                                <span class="fw-bold text-dark fs-13">Total Daily Operating Cost:</span>
                                                <span class="fs-16 fw-bolder text-primary font-monospace">{{ format_currency($secDetailsDailyOperatingCost) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card border rounded-3 p-3 shadow-none mb-0">
                                            <h6 class="fw-bold text-dark mb-3"><i class="feather-activity text-success me-1"></i>Capacity Breakdown</h6>
                                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                <span class="text-muted fs-12">Machine Daily Capacity:</span>
                                                <span class="fw-bold font-monospace text-dark fs-13">{{ number_format($secDetailsMachDailyCap, 1) }} u/d <span class="text-muted fw-normal fs-11">({{ number_format($secDetailsMachHourlyCap, 1) }} u/h)</span></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                <span class="text-muted fs-12 d-flex align-items-center gap-1">
                                                    Manual Daily Capacity:
                                                    <i class="feather-info text-muted fs-11 cursor-pointer" title="It is average production per hour throughout section shifts. Formula: {{ number_format($secDetailsManDailyCap, 1) }} units / {{ format_hours($uohHoursVal) }} = {{ number_format($secDetailsManHourlyCap, 1) }} u/h" data-bs-toggle="tooltip"></i>
                                                </span>
                                                <span class="fw-bold font-monospace text-dark fs-13">{{ number_format($secDetailsManDailyCap, 1) }} u/d <span class="text-muted fw-normal fs-11">({{ number_format($secDetailsManHourlyCap, 1) }} u/h)</span></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                <span class="text-muted fs-12">Average Hourly Output:</span>
                                                <span class="fw-bold font-monospace text-dark fs-13">{{ number_format($secDetailsTotalHourlyCap, 1) }} units/hr</span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center pt-2">
                                                <span class="fw-bold text-dark fs-13">Total Daily Production Capacity:</span>
                                                <span class="fs-16 fw-bolder text-success font-monospace">{{ number_format($secDetailsTotalDailyCap, 1) }} units</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-between">
                        <div class="d-flex align-items-center gap-1.5">
                            <button type="button" class="btn btn-xs btn-outline-info rounded-pill px-2.5 py-1 fw-semibold"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#sectionEmployeesModal_{{ $section->id }}"
                                    data-target-tab="internal">
                                <i class="feather-users me-1"></i>Manage Personnel
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-2.5 py-1 fw-semibold"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#sectionManualProductionModal_{{ $section->id }}">
                                <i class="feather-tool me-1"></i>Manual Production
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-1 fw-semibold"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#sectionCalculationModal_{{ $section->id }}">
                                <i class="feather-bar-chart-2 me-1"></i>Calculation Breakdown
                            </button>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <!-- Edit Countdown Duration Modal -->
    <div class="modal fade" id="editCountdownDurationModal" tabindex="-1" aria-labelledby="editCountdownDurationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <form method="POST" action="{{ route('production.work-centers.countdown-duration', $workCenter->id) }}">
                    @csrf
                    <div class="modal-header border-bottom py-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-text avatar-sm bg-soft-warning text-warning rounded d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                <i class="feather-clock"></i>
                            </div>
                            <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="editCountdownDurationModalLabel">
                                Debounce Countdown Settings
                            </h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-muted fs-12 mb-3">
                            When machines, shifts, or workers are updated on any section of this Work Center, an automatic countdown is armed before running the heavy calculation roll-up. Subsequent updates during the countdown reset the timer (debouncing).
                        </p>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark fs-13" for="calculation_countdown_minutes">
                                Debounce Duration (Minutes) <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="calculation_countdown_minutes" id="calculation_countdown_minutes" 
                                   class="form-control font-monospace" min="1" max="1440" required
                                   value="{{ $workCenter->calculation_countdown_minutes ?? 10 }}">
                            <div class="form-text fs-11 text-muted">
                                Wait time in minutes before recalculating after changes. Minimum 1 minute (cannot be zero). Default is 10 minutes.
                            </div>
                        </div>
                        @if($isCountdownActive)
                            <div class="alert alert-warning py-2 px-3 fs-12 mb-0">
                                <i class="feather-alert-triangle me-1"></i> A countdown is actively running ({{ gmdate('i:s', $countdownRemainingSeconds) }} remaining). Duration cannot be modified until the current calculation finishes.
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm" @if($isCountdownActive) disabled @endif>
                            <i class="feather-save me-1"></i>Save Duration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Work Center Calculation Breakdown Modal -->
    @php
        $latestSnapshot = $workCenter->latestCalculation;
        $wcDetails = $latestSnapshot?->calculation_details ?? [];
        $wcCapPerHour = (float) ($latestSnapshot?->capacity_per_hour ?? 0);
        $wcDailyCap = (float) ($latestSnapshot?->daily_capacity ?? 0);
        $wcLaborCostPerHour = (float) ($latestSnapshot?->labor_cost_per_hour ?? 0);
        $wcDailyLaborCost = (float) ($latestSnapshot?->total_daily_labor_cost ?? 0);
        $wcMachCostPerHour = (float) ($latestSnapshot?->machine_cost_per_hour ?? 0);
        $wcDailyMachCost = (float) ($latestSnapshot?->total_daily_machine_cost ?? 0);
        $wcOverheadDaily = (float) ($latestSnapshot?->overhead_cost ?? $workCenter->overhead_cost ?? 0);
        $wcTotalDailyCost = (float) ($latestSnapshot?->total_daily_cost ?? 0);
        $wcTotalCostPerHour = (float) ($latestSnapshot?->total_cost_per_hour ?? 0);
        $wcUniqOperatingHours = (float) ($latestSnapshot?->unique_operating_hours ?? 0);

        $wcSectionsBreakdown = $wcDetails['sections_contribution'] ?? $wcDetails['sections'] ?? [];
        $wcDirectStaff = $wcDetails['direct_work_center_staff']['employees'] ?? $wcDetails['direct_personnel']['employees'] ?? [];
        $wcDirectStaffDaily = (float) ($latestSnapshot?->direct_staff_daily_labor_cost ?? $wcDetails['direct_work_center_staff']['total_daily_cost'] ?? $wcDetails['direct_personnel']['total_daily_cost'] ?? 0);
        $wcCostPerUnit = (float) ($latestSnapshot?->cost_per_unit ?? $wcDetails['totals']['cost_per_unit'] ?? ($wcDailyCap > 0 ? $wcTotalDailyCost / $wcDailyCap : 0));
        $wcTimelineIntervals = $wcDetails['timeline_intervals'] ?? [];
        $wcTimelineGaps = $wcDetails['timeline_gaps'] ?? [];
    @endphp

    <div class="modal fade" id="workCenterCalculationModal" tabindex="-1" aria-labelledby="workCenterCalculationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header border-bottom py-3 bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="feather-pie-chart"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="workCenterCalculationModalLabel">
                                    {{ __('production.wc_calculation_breakdown') }} &mdash; <span class="font-monospace text-primary">{{ $workCenter->code }}</span> ({{ $workCenter->name }})
                                </h5>
                                @if($latestSnapshot)
                                    <span class="badge bg-soft-success text-success font-monospace fs-11">Snapshot #{{ $latestSnapshot->id }}</span>
                                @endif
                            </div>
                            <small class="text-muted fs-11">
                                Snapshot Recorded: {{ $latestSnapshot ? $latestSnapshot->created_at->format('Y-m-d H:i:s') : 'None yet' }}
                                &bull; Unique Operating Hours: <span class="font-monospace fw-semibold text-dark">{{ format_hours($wcUniqOperatingHours) }}</span>
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Top Summary Cards (6 KPIs) -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-2 col-sm-4">
                            <div class="p-3 rounded border bg-light h-100">
                                <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                    <i class="feather-activity me-1 text-primary"></i>Daily Capacity
                                </span>
                                <span class="fs-18 fw-bolder text-dark font-monospace d-block">
                                    {{ number_format($wcDailyCap, 1) }}
                                </span>
                                <small class="text-muted fs-10">Total units / day</small>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-4">
                            <div class="p-3 rounded border bg-light h-100">
                                <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                    <i class="feather-clock me-1 text-warning"></i>Avg Capacity / hr
                                </span>
                                <span class="fs-18 fw-bolder text-dark font-monospace d-block">
                                    {{ number_format($wcCapPerHour, 2) }}
                                </span>
                                <span class="badge bg-soft-warning text-warning fs-9 px-1 py-0.5 rounded-pill">
                                    <i class="feather-alert-triangle me-0.5"></i>Avg Across Operating
                                </span>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-4">
                            <div class="p-3 rounded border bg-light h-100">
                                <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                    <i class="feather-dollar-sign me-1 text-primary"></i>Daily Labor Cost
                                </span>
                                <span class="fs-18 fw-bolder text-primary font-monospace d-block">
                                    {{ format_currency($wcDailyLaborCost) }}
                                </span>
                                <small class="text-muted fs-10">Avg: {{ format_currency($wcLaborCostPerHour) }}/hr</small>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-4">
                            <div class="p-3 rounded border bg-light h-100">
                                <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                    <i class="feather-cpu me-1 text-warning"></i>Daily Machinery
                                </span>
                                <span class="fs-18 fw-bolder text-warning font-monospace d-block">
                                    {{ format_currency($wcDailyMachCost) }}
                                </span>
                                <small class="text-muted fs-10">Avg: {{ format_currency($wcMachCostPerHour) }}/hr</small>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-4">
                            <div class="p-3 rounded border bg-light h-100">
                                <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                    <i class="feather-home me-1 text-secondary"></i>Daily Overhead
                                </span>
                                <span class="fs-18 fw-bolder text-dark font-monospace d-block">
                                    {{ format_currency($wcOverheadDaily) }}
                                </span>
                                <small class="text-muted fs-10">Flat facility cost</small>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-4">
                            <div class="p-3 rounded border bg-soft-primary border-primary h-100">
                                <span class="text-primary fs-11 text-uppercase fw-semibold d-block mb-1">
                                    <i class="feather-check-circle me-1"></i>Total Daily Cost
                                </span>
                                <span class="fs-18 fw-bolder text-primary font-monospace d-block">
                                    {{ format_currency($wcTotalDailyCost) }}
                                </span>
                                <small class="text-primary fs-10">Effective: {{ format_currency($wcTotalCostPerHour) }}/hr</small>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs: Timeline Intervals, Sections Rollup, Direct Staff & Overhead, Activity Logs, Raw JSON -->
                    <ul class="nav nav-tabs nav-tabs-bottom mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-semibold fs-13 d-flex align-items-center gap-1.5 py-2 px-3" 
                                    id="wc-intervals-tab" data-bs-toggle="tab" data-bs-target="#wc-intervals-pane" 
                                    type="button" role="tab" aria-controls="wc-intervals-pane" aria-selected="true">
                                <i class="feather-clock text-warning fs-14"></i>
                                Timeline Intervals ({{ count($wcTimelineIntervals) }})
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold fs-13 d-flex align-items-center gap-1.5 py-2 px-3" 
                                    id="wc-sections-tab" data-bs-toggle="tab" data-bs-target="#wc-sections-pane" 
                                    type="button" role="tab" aria-controls="wc-sections-pane" aria-selected="false">
                                <i class="feather-grid text-primary fs-14"></i>
                                Contributing Sections ({{ count($wcSectionsBreakdown) }})
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold fs-13 d-flex align-items-center gap-1.5 py-2 px-3" 
                                    id="wc-direct-tab" data-bs-toggle="tab" data-bs-target="#wc-direct-pane" 
                                    type="button" role="tab" aria-controls="wc-direct-pane" aria-selected="false">
                                <i class="feather-users text-info fs-14"></i>
                                Direct Personnel & Overhead ({{ count($wcDirectStaff) }})
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold fs-13 d-flex align-items-center gap-1.5 py-2 px-3" 
                                    id="wc-logs-tab" data-bs-toggle="tab" data-bs-target="#wc-logs-pane" 
                                    type="button" role="tab" aria-controls="wc-logs-pane" aria-selected="false">
                                <i class="feather-activity text-secondary fs-14"></i>
                                Activity & Audit Logs
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold fs-13 d-flex align-items-center gap-1.5 py-2 px-3" 
                                    id="wc-json-tab" data-bs-toggle="tab" data-bs-target="#wc-json-pane" 
                                    type="button" role="tab" aria-controls="wc-json-pane" aria-selected="false">
                                <i class="feather-code text-muted fs-14"></i>
                                Raw Snapshot JSON
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- TAB 1: TIMELINE INTERVALS -->
                        <div class="tab-pane fade show active" id="wc-intervals-pane" role="tabpanel" aria-labelledby="wc-intervals-tab">
                            <div class="card border mb-3 shadow-none">
                                <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                    <h6 class="fw-bold text-dark fs-12 mb-0">
                                        <i class="feather-clock me-1 text-warning"></i>1,440-Minute Timeline Interval Slices
                                    </h6>
                                    <small class="text-muted fs-11">Discrete intervals computed at every shift, machine, or break boundary</small>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover table-bordered align-middle mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 14%">Time Window</th>
                                                <th style="width: 10%">Duration</th>
                                                <th style="width: 14%">Active Sections</th>
                                                <th style="width: 14%">Machines Burn</th>
                                                <th style="width: 16%">Labor Burn Rate</th>
                                                <th style="width: 16%" class="text-end">Interval Capacity/hr</th>
                                                <th style="width: 16%" class="text-end">Interval Total Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($wcTimelineIntervals as $interval)
                                                @php
                                                    $durHrs = ($interval['duration_minutes'] ?? 0) / 60;
                                                    $machBurn = (float) ($interval['machine_burn_rate_per_hour'] ?? 0);
                                                    $laborBurn = (float) ($interval['labor_burn_rate_per_hour'] ?? 0);
                                                    $intervalCap = (float) ($interval['capacity_per_hour'] ?? 0);
                                                    $intervalCost = ($machBurn + $laborBurn) * $durHrs;
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <span class="font-monospace fw-bold text-dark">
                                                            {{ $interval['start_time'] ?? '' }} &ndash; {{ $interval['end_time'] ?? '' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-soft-secondary text-secondary font-monospace">
                                                            {{ $interval['duration_minutes'] ?? 0 }}m ({{ format_hours($durHrs) }})
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-soft-primary text-primary font-monospace">
                                                            {{ count($interval['active_section_ids'] ?? []) }} Sections
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="font-monospace fw-semibold text-warning">
                                                            {{ format_currency($machBurn) }}/hr
                                                        </span>
                                                        <small class="text-muted d-block fs-10">({{ $interval['active_machines_count'] ?? 0 }} machines)</small>
                                                    </td>
                                                    <td>
                                                        <span class="font-monospace fw-semibold text-primary">
                                                            {{ format_currency($laborBurn) }}/hr
                                                        </span>
                                                        <small class="text-muted d-block fs-10">({{ $interval['active_workers_count'] ?? 0 }} workers)</small>
                                                    </td>
                                                    <td class="text-end font-monospace fw-bold text-dark">
                                                        {{ number_format($intervalCap, 2) }} units/hr
                                                    </td>
                                                    <td class="text-end font-monospace fw-bold text-dark">
                                                        {{ format_currency($intervalCost) }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted py-3 fst-italic">
                                                        No timeline intervals calculated.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: CONTRIBUTING SECTIONS BREAKDOWN -->
                        <div class="tab-pane fade" id="wc-sections-pane" role="tabpanel" aria-labelledby="wc-sections-tab">
                            <div class="card border mb-3 shadow-none">
                                <div class="card-header bg-light py-2 px-3 border-bottom">
                                    <h6 class="fw-bold text-dark fs-12 mb-0">
                                        <i class="feather-grid me-1 text-primary"></i>Section Capacity & Expense Roll-Up
                                    </h6>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover table-bordered align-middle mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 12%">Code</th>
                                                <th style="width: 22%">Section Name</th>
                                                <th style="width: 14%">Operating Hours</th>
                                                <th style="width: 16%" class="text-end">Daily Capacity</th>
                                                <th style="width: 18%" class="text-end">Daily Labor Cost</th>
                                                <th style="width: 18%" class="text-end">Daily Machine Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($wcSectionsBreakdown as $secB)
                                                <tr>
                                                    <td><span class="font-monospace fw-bold text-dark">{{ $secB['section_code'] ?? $secB['code'] ?? '—' }}</span></td>
                                                    <td class="fw-semibold text-dark">{{ $secB['section_name'] ?? $secB['name'] ?? '—' }}</td>
                                                    <td><span class="badge bg-soft-secondary text-secondary font-monospace">{{ format_hours($secB['unique_operating_hours'] ?? $secB['operating_hours'] ?? 0) }}</span></td>
                                                    <td class="text-end font-monospace fw-bold text-dark">{{ number_format($secB['daily_capacity'] ?? 0, 1) }} units</td>
                                                    <td class="text-end font-monospace text-primary fw-semibold">{{ format_currency($secB['total_daily_labor_cost'] ?? 0) }}</td>
                                                    <td class="text-end font-monospace text-warning fw-semibold">{{ format_currency($secB['total_daily_machine_cost'] ?? 0) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-3 fst-italic">
                                                        No sections contributing to this snapshot.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: DIRECT STAFF & OVERHEAD -->
                        <div class="tab-pane fade" id="wc-direct-pane" role="tabpanel" aria-labelledby="wc-direct-tab">
                            <div class="card border mb-3 shadow-none">
                                <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                    <h6 class="fw-bold text-dark fs-12 mb-0">
                                        <i class="feather-users me-1 text-info"></i>Direct Work Center Staff (Unassigned to any Section)
                                    </h6>
                                    <span class="badge bg-soft-info text-info font-monospace fs-11">Total Daily: {{ format_currency($wcDirectStaffDaily) }}</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover table-bordered align-middle mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 35%">Employee Name</th>
                                                <th style="width: 25%">Monthly HRMS CTC</th>
                                                <th style="width: 20%">Daily Salary (CTC / 30)</th>
                                                <th style="width: 20%">Role / Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($wcDirectStaff as $dst)
                                                <tr>
                                                    <td class="fw-semibold text-dark">
                                                        <i class="feather-user me-1 text-muted"></i>{{ $dst['name'] ?? '—' }}
                                                        @if(!empty($dst['employee_code']))
                                                            <span class="badge bg-soft-dark text-dark font-monospace fs-10 ms-1">{{ $dst['employee_code'] }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="font-monospace text-muted">{{ format_currency($dst['monthly_ctc'] ?? $dst['hrms_net_monthly_salary'] ?? 0) }} / mo</td>
                                                    <td class="font-monospace fw-bold text-primary">
                                                        {{ format_currency($dst['daily_cost'] ?? $dst['daily_salary'] ?? 0) }} / day
                                                        @if(!empty($dst['hourly_rate']))
                                                            <small class="text-muted d-block fs-10 fw-normal">({{ format_currency($dst['hourly_rate']) }} / hr)</small>
                                                        @endif
                                                    </td>
                                                    <td class="text-muted fs-11">{{ $dst['role'] ?? 'Floating Station Personnel' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted py-3 fst-italic">
                                                        No direct station employees assigned. All labor is sourced from section-level rosters.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="card border shadow-none">
                                <div class="card-header bg-light py-2 px-3 border-bottom">
                                    <h6 class="fw-bold text-dark fs-12 mb-0">
                                        <i class="feather-home me-1 text-secondary"></i>Work Center Flat Overhead Integration
                                    </h6>
                                </div>
                                <div class="card-body p-3 fs-12">
                                    <p class="text-muted mb-2">
                                        Facility overhead is configured directly on the Work Center as a flat daily cost. It is aggregated into the total daily operational cost and distributed across active operational hours.
                                    </p>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="p-2 border rounded bg-light">
                                                <span class="text-muted fs-10 text-uppercase d-block fw-semibold">Configured Daily Overhead</span>
                                                <span class="fs-16 fw-bold font-monospace text-dark">{{ format_currency($wcOverheadDaily) }} / day</span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-2 border rounded bg-light">
                                                <span class="text-muted fs-10 text-uppercase d-block fw-semibold">Unique Operating Hours</span>
                                                <span class="fs-16 fw-bold font-monospace text-dark">{{ format_hours($wcUniqOperatingHours) }}</span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-2 border rounded bg-light">
                                                <span class="text-muted fs-10 text-uppercase d-block fw-semibold">Derived Hourly Rate</span>
                                                <span class="fs-16 fw-bold font-monospace text-primary">
                                                    {{ $wcUniqOperatingHours > 0 ? format_currency($wcOverheadDaily / $wcUniqOperatingHours) : '—' }} / hr
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 4: ACTIVITY & AUDIT LOGS -->
                        <div class="tab-pane fade" id="wc-logs-pane" role="tabpanel" aria-labelledby="wc-logs-tab">
                            <div class="card border shadow-none">
                                <div class="card-header bg-light py-2 px-3 border-bottom">
                                    <h6 class="fw-bold text-dark fs-12 mb-0">
                                        <i class="feather-activity me-1 text-primary"></i>Audit Log Entries
                                    </h6>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 20%">Timestamp</th>
                                                <th style="width: 20%">Action</th>
                                                <th style="width: 40%">Description</th>
                                                <th style="width: 20%">User</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($workCenter->activityLogs()->latest()->take(20)->get() as $log)
                                                <tr>
                                                    <td class="font-monospace text-muted fs-11">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                                    <td>
                                                        <span class="badge bg-soft-secondary text-secondary font-monospace fs-10 text-uppercase px-2 py-0.5 rounded-pill">
                                                            {{ str_replace('_', ' ', $log->action_type) }}
                                                        </span>
                                                    </td>
                                                    <td class="text-dark">{{ $log->description }}</td>
                                                    <td>{{ $log->user ? $log->user->name : 'System Automation' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center py-3 text-muted fst-italic">No activity logs recorded.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 5: RAW SNAPSHOT JSON -->
                        <div class="tab-pane fade" id="wc-json-pane" role="tabpanel" aria-labelledby="wc-json-tab">
                            <div class="card border shadow-none">
                                <div class="card-header bg-dark text-white py-2 px-3 d-flex justify-content-between align-items-center">
                                    <span class="font-monospace fs-11">production_work_center_calculations #{{ $latestSnapshot?->id ?? 'N/A' }}</span>
                                    <span class="badge bg-secondary font-monospace fs-10">JSON Payload</span>
                                </div>
                                <pre class="bg-dark text-light p-3 mb-0 fs-11 font-monospace" style="max-height: 450px; overflow-y: auto;"><code>{{ json_encode($latestSnapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-end">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        {{ __('production.close_tab') ?? 'Close' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Worker Section-Shift External Worker Allocation Modals -->
    @foreach ($workCenter->sections as $section)
        @foreach ($section->shifts as $shift)
            @php
                $isSectionShiftActive = $section->isActive() && $shift->isActive() && (bool) ($shift->pivot->active ?? true);

                // External worker allocations
                $extAllocations = $section->externalWorkerAllocations ? $section->externalWorkerAllocations->where('shift_id', $shift->id) : collect();
                $activeExtAllocations = $extAllocations->where('active', true);
                $inactiveExtAllocations = $extAllocations->where('active', false);
                $activeExtCount = (int) $activeExtAllocations->sum('number_of_external_workers');
            @endphp
            <div class="modal fade" id="sectionShiftWorkersModal_{{ $section->id }}_{{ $shift->id }}" tabindex="-1" aria-labelledby="sectionShiftWorkersModalLabel_{{ $section->id }}_{{ $shift->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg rounded-3">
                        <div class="modal-header border-bottom py-3">
                            <div>
                                <h5 class="modal-title fw-bold text-dark fs-15" id="sectionShiftWorkersModalLabel_{{ $section->id }}_{{ $shift->id }}">
                                    <i class="feather-users text-primary me-2"></i>Allocate External Workers &mdash; {{ $section->name }}
                                </h5>
                                <div class="text-muted fs-11 mt-0.5">
                                    Operating Shift: <span class="fw-semibold text-dark">{{ $shift->name }}</span>
                                    <span class="font-monospace">({{ substr($shift->start_time, 0, 5) }} &ndash; {{ substr($shift->end_time, 0, 5) }})</span>
                                    @if(!$isSectionShiftActive)
                                        <span class="badge bg-soft-warning text-warning ms-2">Section or Shift Inactive</span>
                                    @endif
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body p-4">
                            <!-- Staffing Overview Metric Banner -->
                            <div class="p-3 rounded bg-soft-primary border border-primary-subtle mb-4 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fs-11 text-uppercase fw-semibold mb-0.5 text-primary">
                                        <i class="feather-users me-1"></i>External Workers for {{ $shift->name }}
                                    </div>
                                    <small class="text-muted fs-11">Allocated across {{ $activeExtAllocations->count() }} active pay grade(s)</small>
                                </div>
                                <div class="fs-22 fw-bold font-monospace text-primary">
                                    {{ $activeExtCount }} <span class="fs-12 fw-normal text-muted">workers allocated</span>
                                </div>
                            </div>

                            <!-- Active Allocations Table -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold text-dark fs-13 mb-0">Active Pay Grade Allocations</h6>
                                    <span class="badge bg-soft-success text-success fs-10 px-2 py-0.5 rounded-pill">{{ $activeExtAllocations->count() }} Active</span>
                                </div>

                                @if($activeExtAllocations->isNotEmpty())
                                    <div class="table-responsive border rounded">
                                        <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th style="width: 30%">Pay Grade</th>
                                                    <th style="width: 20%">Hourly Rate</th>
                                                    <th style="width: 25%">Number of External Workers</th>
                                                    <th style="width: 10%">Status</th>
                                                    <th style="width: 15%" class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($activeExtAllocations as $alloc)
                                                    <tr>
                                                        <td>
                                                            <div class="fw-semibold text-dark">{{ $alloc->payGrade?->name ?? 'Unknown' }}</div>
                                                            <span class="font-monospace text-muted fs-11">[{{ $alloc->payGrade?->code ?? 'N/A' }}]</span>
                                                        </td>
                                                        <td class="font-monospace">
                                                            {{ format_currency((float) ($alloc->payGrade?->hourly_rate ?? 0)) }} <span class="text-muted fs-10">/ hr</span>
                                                        </td>
                                                        <td>
                                                            <form method="POST" action="{{ route('production.external-worker-allocations.quantity', $alloc->id) }}" class="d-flex align-items-center gap-1 ext-worker-qty-form">
                                                                @csrf
                                                                @method('PATCH')
                                                                <input type="number" name="number_of_external_workers" min="1" value="{{ $alloc->number_of_external_workers }}" data-initial-value="{{ $alloc->number_of_external_workers }}" class="form-control form-control-sm font-monospace text-center py-0 px-1 ext-worker-qty-input" style="width: 60px; height: 26px;">
                                                                <button type="submit" class="btn btn-xs btn-outline-primary ext-worker-qty-btn d-none" title="Update Number of External Workers">
                                                                    <i class="feather-check"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-soft-success text-success rounded-pill px-2">Active</span>
                                                        </td>
                                                        <td class="text-end">
                                                            <div class="d-inline-flex gap-1">
                                                                <form method="POST" action="{{ route('production.external-worker-allocations.toggle-status', $alloc->id) }}" class="d-inline">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <input type="hidden" name="active" value="0">
                                                                    <button type="submit" class="btn btn-xs btn-outline-warning" title="Deactivate allocation">
                                                                        <i class="feather-pause-circle"></i>
                                                                    </button>
                                                                </form>
                                                                <form method="POST" action="{{ route('production.external-worker-allocations.destroy', $alloc->id) }}" class="d-inline" onsubmit="return confirm('Deallocate this external worker allocation?');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Deallocate">
                                                                        <i class="feather-trash-2"></i>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-3 bg-light rounded border border-dashed text-muted fs-12">
                                        No active external worker allocations for this shift.
                                    </div>
                                @endif
                            </div>

                            <!-- Inactive / Historical Allocations -->
                            @if($inactiveExtAllocations->isNotEmpty())
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-bold text-muted fs-13 mb-0">Inactive / Historical Allocations</h6>
                                        <span class="badge bg-soft-secondary text-secondary fs-10 px-2 py-0.5 rounded-pill">{{ $inactiveExtAllocations->count() }} Inactive</span>
                                    </div>
                                    <div class="table-responsive border rounded">
                                        <table class="table table-sm table-hover align-middle mb-0 fs-12 opacity-75">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th style="width: 35%">Pay Grade</th>
                                                    <th style="width: 25%">Hourly Rate</th>
                                                    <th style="width: 15%">Number of External Workers</th>
                                                    <th style="width: 10%">Status</th>
                                                    <th style="width: 15%" class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($inactiveExtAllocations as $alloc)
                                                    <tr>
                                                        <td>
                                                            <div class="fw-semibold text-dark">{{ $alloc->payGrade?->name ?? 'Unknown' }}</div>
                                                            <span class="font-monospace text-muted fs-11">[{{ $alloc->payGrade?->code ?? 'N/A' }}]</span>
                                                        </td>
                                                        <td class="font-monospace">
                                                            {{ format_currency((float) ($alloc->payGrade?->hourly_rate ?? 0)) }} <span class="text-muted fs-10">/ hr</span>
                                                        </td>
                                                        <td class="font-monospace fw-bold">{{ $alloc->number_of_external_workers }}</td>
                                                        <td>
                                                            <span class="badge bg-soft-secondary text-secondary rounded-pill px-2">Inactive</span>
                                                        </td>
                                                        <td class="text-end">
                                                            <div class="d-inline-flex gap-1">
                                                                <form method="POST" action="{{ route('production.external-worker-allocations.toggle-status', $alloc->id) }}" class="d-inline">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <input type="hidden" name="active" value="1">
                                                                    <button type="submit" class="btn btn-xs btn-outline-success" title="Reactivate allocation" @disabled(!$isSectionShiftActive || !$alloc->payGrade?->isActive())>
                                                                        <i class="feather-play-circle me-1"></i>Activate
                                                                    </button>
                                                                </form>
                                                                <form method="POST" action="{{ route('production.external-worker-allocations.destroy', $alloc->id) }}" class="d-inline" onsubmit="return confirm('Deallocate this historical allocation?');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Deallocate">
                                                                        <i class="feather-trash-2"></i>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            <!-- Add New External Allocation Form -->
                            <div class="border rounded p-3 bg-light">
                                <h6 class="fw-bold text-dark fs-12 mb-2">
                                    <i class="feather-plus-circle text-primary me-1"></i>Allocate External Workers
                                </h6>

                                @if(!$isSectionShiftActive)
                                    <div class="alert alert-warning py-2 px-3 fs-11 mb-0">
                                        <i class="feather-alert-triangle me-1"></i>
                                        Cannot create or activate allocations because this Section, Shift, or Section&ndash;Shift assignment is inactive.
                                    </div>
                                @elseif(($activePayGrades ?? collect())->isEmpty())
                                    <div class="alert alert-info py-2 px-3 fs-11 mb-0">
                                        <i class="feather-info me-1"></i>
                                        No active external worker pay grades found. Please create or activate pay grades in 
                                        <a href="{{ route('production.pay-grades.index') }}" class="fw-bold text-primary">Pay Grade Management</a> first.
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('production.external-worker-allocations.store') }}">
                                        @csrf
                                        <input type="hidden" name="section_id" value="{{ $section->id }}">

                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">Operating Shift <span class="text-danger">*</span></label>
                                                <select name="shift_id" class="form-select form-select-sm" data-select2-selector="default" style="width: 100%;" required>
                                                    @foreach($section->shifts as $secShift)
                                                        @php
                                                            $startTime = substr($secShift->start_time, 0, 5);
                                                            $endTime = substr($secShift->end_time, 0, 5);
                                                            $timeStr = $startTime . '–' . $endTime;
                                                            $hasTime = str_contains($secShift->name, $startTime);
                                                        @endphp
                                                        <option value="{{ $secShift->id }}" @selected($secShift->id === $shift->id)>
                                                            {{ $secShift->name }}{{ $hasTime ? '' : ' (' . $timeStr . ')' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">Pay Grade <span class="text-danger">*</span></label>
                                                <select name="pay_grade_id" class="form-select form-select-sm" data-select2-selector="default" style="width: 100%;" required>
                                                    <option value="">-- Select Active Pay Grade --</option>
                                                    @foreach($activePayGrades as $pg)
                                                        <option value="{{ $pg->id }}">
                                                            {{ $pg->name }} [{{ $pg->code }}] &mdash; {{ format_currency((float) $pg->hourly_rate) }}/hr
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">Workers <span class="text-danger">*</span></label>
                                                <input type="number" name="number_of_external_workers" class="form-control form-control-sm font-monospace" min="1" value="1" required>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="submit" class="btn btn-sm btn-primary w-100">
                                                    <i class="feather-plus me-1"></i>Allocate
                                                </button>
                                            </div>
                                        </div>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="update_if_exists" value="1" id="update_if_exists_{{ $section->id }}_{{ $shift->id }}" checked>
                                            <label class="form-check-label fs-11 text-muted" for="update_if_exists_{{ $section->id }}_{{ $shift->id }}">
                                                Update Number of External Workers if allocation already exists for this Pay Grade
                                            </label>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endforeach

    <!-- Work Center-Level Internal Employee Assignment Modal -->
    <div class="modal fade wc-employees-modal" id="workCenterEmployeesModal" tabindex="-1" aria-labelledby="workCenterEmployeesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header border-bottom py-3">
                    <div>
                        <h5 class="modal-title fw-bold text-dark fs-15" id="workCenterEmployeesModalLabel">
                            <i class="feather-user-check text-primary me-2"></i>{{ __('production.assign_internal_employees') }} &mdash; {{ $workCenter->name }}
                        </h5>
                        <div class="text-muted fs-11 mt-0.5">
                            Work Center: <span class="font-monospace fw-semibold text-dark">{{ $workCenter->code }}</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Scope & Exclusivity Banner -->
                    <div class="alert alert-info py-2.5 px-3 fs-12 mb-3 d-flex align-items-start gap-2">
                        <i class="feather-info text-info fs-16 mt-0.5"></i>
                        <div>
                            <strong>Scope of Assignment:</strong> Internal employees assigned here have operational assignment across all Sections in <strong>{{ $workCenter->name }}</strong> without requiring separate Section records. 
                            <div class="text-muted fs-11 mt-0.5">
                                Employees may be assigned across multiple Work Centers. However, mutual exclusivity prevents holding both active Work Center-level and active Section-level assignments simultaneously.
                            </div>
                        </div>
                    </div>

                    <!-- Currently Assigned Employees List -->
                    @php
                        $wcDirectAssignments = $workCenter->employeeAssignments ? $workCenter->employeeAssignments->whereNull('section_id') : collect();
                    @endphp
                    <div class="mb-4" id="wc-employees-list-wrapper">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-dark fs-13 mb-0">
                                Assigned Employees
                                <span class="badge bg-soft-primary text-primary fs-10 px-2 py-0.5 rounded-pill ms-1 font-monospace" id="wc-employees-active-badge">
                                    {{ $wcDirectAssignments->where('active', true)->count() }} Active
                                </span>
                            </h6>
                        </div>

                        @if($wcDirectAssignments->isNotEmpty())
                            <div class="table-responsive border rounded">
                                <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width: 18%">Employee ID</th>
                                            <th style="width: 25%">Name</th>
                                            <th style="width: 22%">Designation</th>
                                            <th style="width: 20%">Predefined Shift</th>
                                            <th style="width: 5%">Status</th>
                                            <th style="width: 10%" class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($wcDirectAssignments as $assignment)
                                            @php
                                                $emp = $assignment->employee;
                                                $empId = $emp?->employee_id ?? ('EMP-' . ($emp?->id ?? $assignment->employee_id));
                                                $empFullName = $emp?->full_name ?? $emp?->name ?? 'Unknown Employee';
                                                $desigName = $emp?->designation?->name ?? $emp?->job_title;
                                                $deptName = $emp?->department?->name;
                                                $empShift = $emp?->shift;
                                            @endphp
                                            <tr>
                                                <td class="font-monospace text-dark fw-semibold">
                                                    {{ $empId }}
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-dark">{{ $empFullName }}</div>
                                                    @if($emp?->office_email ?? $emp?->personal_email)
                                                        <small class="text-muted fs-11">{{ $emp?->office_email ?? $emp?->personal_email }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="text-dark fw-medium">{{ $desigName ?? 'Staff' }}</span>
                                                    @if($deptName)
                                                        <div class="text-muted fs-11">{{ $deptName }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php
                                                        $wcEffectiveShifts = $workCenter->getEffectiveShifts(true);
                                                        $wcCompatResult = ($emp && $empShift && $wcEffectiveShifts->isNotEmpty())
                                                            ? app(\App\Domains\Production\Services\WorkCenterEmployeeService::class)->isEmployeeCompatibleWithShifts($emp, $wcEffectiveShifts, false)
                                                            : false;
                                                    @endphp
                                                    @if($empShift)
                                                        @if($wcCompatResult)
                                                            <span class="badge bg-soft-success text-success px-2 py-1 font-monospace" title="Shift Compatible: Matches Work Center Operating Hours">
                                                                <i class="feather-clock me-1"></i>{{ $empShift->name }} ({{ substr($empShift->start_time, 0, 5) }}&ndash;{{ substr($empShift->end_time, 0, 5) }})
                                                            </span>
                                                        @else
                                                            <span class="badge bg-soft-danger text-danger px-2 py-1 font-monospace border border-danger-subtle" title="Shift Mismatch: Incompatible with Work Center Operating Hours">
                                                                <i class="feather-alert-triangle me-1"></i>{{ $empShift->name }} ({{ substr($empShift->start_time, 0, 5) }}&ndash;{{ substr($empShift->end_time, 0, 5) }}) [Mismatch]
                                                            </span>
                                                        @endif
                                                    @else
                                                        <span class="badge bg-soft-danger text-danger px-2 py-1 font-monospace">
                                                            <i class="feather-alert-circle me-1"></i>No shift predefined
                                                        </span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($assignment->active)
                                                        <span class="badge bg-soft-success text-success rounded-pill px-2">Active</span>
                                                    @else
                                                        <span class="badge bg-soft-secondary text-secondary rounded-pill px-2">Inactive</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-inline-flex gap-1">
                                                        <form method="POST" action="{{ route('production.work-center-employees.toggle-status', $assignment->id) }}" class="d-inline">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="active" value="{{ $assignment->active ? 0 : 1 }}">
                                                            <button type="submit" class="btn btn-xs {{ $assignment->active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $assignment->active ? 'Deactivate' : 'Activate' }}">
                                                                <i class="feather-{{ $assignment->active ? 'pause-circle' : 'play-circle' }}"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="{{ route('production.work-center-employees.destroy', $assignment->id) }}" class="d-inline" data-confirm="Remove employee {{ addslashes($empFullName) }} from this Work Center?">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-xs btn-outline-danger" title="Remove assignment">
                                                                <i class="feather-trash-2"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-3 bg-light rounded border border-dashed text-muted fs-12">
                                No internal employees currently assigned to this Work Center.
                            </div>
                        @endif
                    </div>

                    <!-- Assign Employee Form -->
                    <div class="border rounded p-3 bg-light">
                        <h6 class="fw-bold text-dark fs-12 mb-2">
                            <i class="feather-user-plus text-primary me-1"></i>Assign New Employee
                        </h6>

                        @php
                            $activeSections = $workCenter->sections->where('status', 'active');
                            $hasActiveSections = $activeSections->isNotEmpty();
                            $effectiveShifts = $workCenter->getEffectiveShifts(true);
                            $hasEffectiveShifts = $effectiveShifts->isNotEmpty();
                        @endphp

                        @if(!$hasActiveSections)
                            <div class="alert alert-warning py-2.5 px-3 fs-12 mb-0 d-flex align-items-center gap-2">
                                <i class="feather-alert-triangle text-warning fs-18"></i>
                                <div>
                                    <strong>Work Center has no active sections.</strong>
                                    <div class="text-muted fs-11 mt-0.5">
                                        Internal employees cannot be assigned at the Work Center level until at least one active section with operating shifts is created. Operating shifts for the Work Center are derived from its active sections.
                                    </div>
                                </div>
                            </div>
                        @elseif(!$hasEffectiveShifts)
                            <div class="alert alert-warning py-2.5 px-3 fs-12 mb-0 d-flex align-items-center gap-2">
                                <i class="feather-alert-triangle text-warning fs-18"></i>
                                <div>
                                    <strong>No operating shifts found on active sections.</strong>
                                    <div class="text-muted fs-11 mt-0.5">
                                        Internal employees cannot be assigned at the Work Center level because none of the active sections have operating shifts assigned.
                                    </div>
                                </div>
                            </div>
                        @elseif(($availableEmployees ?? collect())->isEmpty())
                            <div class="alert alert-warning py-2 px-3 fs-11 mb-0">
                                <i class="feather-alert-triangle me-1"></i>
                                No active employees found in this organization to assign.
                            </div>
                        @else
                            @php
                                $activeWcEmpIds = $wcDirectAssignments->where('active', true)->pluck('employee_id')->all();
                                $activeWcEmpMap = array_flip($activeWcEmpIds);
                                $wcEmpService = app(\App\Domains\Production\Services\WorkCenterEmployeeService::class);
                            @endphp
                            <form method="POST" action="{{ route('production.work-center-employees.store') }}" id="assignEmployeeForm">
                                @csrf
                                <input type="hidden" name="work_center_id" value="{{ $workCenter->id }}">

                                <div class="row g-2 align-items-end">
                                    <div class="col-md-9">
                                        <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">Select Employee <span class="text-danger">*</span></label>
                                        <select name="employee_id" class="form-select form-select-sm" data-select2-selector="default" style="width: 100%;" required id="wcEmployeeSelect">
                                            <option value="">-- Choose Employee --</option>
                                            @foreach($availableEmployees as $emp)
                                                @php
                                                    $isAssignedToThisWc = isset($activeWcEmpMap[$emp->id]);
                                                    $assignedSec = ($employeesWithActiveSection ?? collect())->get($emp->id);
                                                    $isAssignedToSec = $assignedSec !== null;
                                                    $compatStatus = $wcEmpService->getShiftCompatibilityStatus($emp, $effectiveShifts, false);
                                                    $isShiftIncompatible = !$compatStatus['compatible'];

                                                    $isDisabled = $isAssignedToThisWc || $isAssignedToSec || $isShiftIncompatible;

                                                    $empCode = $emp->employee_id ?? ('EMP-' . $emp->id);
                                                    $empName = $emp->full_name ?? $emp->name ?? 'Employee #' . $emp->id;
                                                    $desig = $emp->designation?->name ?? $emp->job_title ?? 'Staff';
                                                    $dept = $emp->department?->name;

                                                    if ($isAssignedToThisWc) {
                                                        $statusLabel = '(Already Assigned)';
                                                    } elseif ($isAssignedToSec) {
                                                        $statusLabel = '(Assigned to Section: ' . ($assignedSec->section?->name ?? ('#' . $assignedSec->section_id)) . ')';
                                                    } elseif ($isShiftIncompatible) {
                                                        $statusLabel = '(' . $compatStatus['label'] . ')';
                                                    } else {
                                                        $statusLabel = '';
                                                    }
                                                @endphp
                                                <option value="{{ $emp->id }}"
                                                        @if($isDisabled) disabled @endif
                                                        @if($isDisabled) class="text-muted bg-light fst-italic" style="color: #94a3b8; font-style: italic; background-color: #f8fafc;" @endif>
                                                    @if($isDisabled)⛔ @endif{{ $empName }} ({{ $empCode }}) &mdash; {{ $desig }}@if($dept) [{{ $dept }}]@endif @if($statusLabel) {{ $statusLabel }}@endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-sm btn-primary w-100">
                                            <i class="feather-plus me-1"></i>Assign
                                        </button>
                                    </div>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-end">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Unified Section-Level Personnel Modals (Internal Employees & External Workers) -->
    @foreach ($workCenter->sections as $section)
        @php
            $secExtAllocs = $section->externalWorkerAllocations ? $section->externalWorkerAllocations->sortBy('shift_id') : collect();
            $secActiveAllocs = $secExtAllocs->where('active', true);
            $secInactiveAllocs = $secExtAllocs->where('active', false);
            $secTotalActiveWorkers = (int) $secActiveAllocs->sum('number_of_external_workers');

            $secDirectAssignments = $section->employeeAssignments ?: collect();
            $secActiveEmpCount = $secDirectAssignments->where('active', true)->count();
            $secTotalPersonnel = $secActiveEmpCount + $secTotalActiveWorkers;
        @endphp
        <div class="modal fade section-personnel-modal" id="sectionEmployeesModal_{{ $section->id }}" tabindex="-1" aria-labelledby="sectionEmployeesModalLabel_{{ $section->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header border-bottom py-3">
                        <div>
                            <h5 class="modal-title fw-bold text-dark fs-15" id="sectionEmployeesModalLabel_{{ $section->id }}">
                                <i class="feather-users text-primary me-2"></i>Section Personnel &mdash; {{ $section->name }}
                            </h5>
                            <div class="text-muted fs-11 mt-0.5">
                                Work Center: <span class="font-monospace fw-semibold text-dark">{{ $workCenter->code }}</span>
                                &bull; Section: <span class="font-monospace fw-semibold text-dark">{{ $section->code }}</span>
                                @if(!$section->isActive())
                                    <span class="badge bg-soft-warning text-warning ms-2">Section Inactive</span>
                                @endif
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <!-- Top Sub-Header: Mode Switcher & Aggregate Summary -->
                    <div class="d-flex align-items-center justify-content-between p-3 bg-light border-bottom flex-wrap gap-2">
                        <ul class="nav nav-pills gap-2" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active px-3 py-1.5 fw-semibold fs-12 rounded-pill d-inline-flex align-items-center gap-1.5"
                                        id="sec-internal-tab-{{ $section->id }}"
                                        data-bs-toggle="pill"
                                        data-bs-target="#sec-internal-pane-{{ $section->id }}"
                                        type="button" role="tab" aria-controls="sec-internal-pane-{{ $section->id }}" aria-selected="true">
                                    <i class="feather-user-check fs-12"></i>
                                    <span>Internal Employees</span>
                                    <span class="badge bg-white text-primary rounded-pill px-1.5 py-0 fs-10 font-monospace border ms-0.5">
                                        {{ $secActiveEmpCount }}
                                    </span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link px-3 py-1.5 fw-semibold fs-12 rounded-pill d-inline-flex align-items-center gap-1.5"
                                        id="sec-external-tab-{{ $section->id }}"
                                        data-bs-toggle="pill"
                                        data-bs-target="#sec-external-pane-{{ $section->id }}"
                                        type="button" role="tab" aria-controls="sec-external-pane-{{ $section->id }}" aria-selected="false">
                                    <i class="feather-users fs-12"></i>
                                    <span>External Workers</span>
                                    <span class="badge bg-white text-primary rounded-pill px-1.5 py-0 fs-10 font-monospace border ms-0.5">
                                        {{ $secTotalActiveWorkers }}
                                    </span>
                                </button>
                            </li>
                        </ul>

                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted fs-11 text-uppercase fw-semibold">Total Personnel:</span>
                            <span class="badge bg-soft-primary text-primary px-2.5 py-1 fs-12 font-monospace fw-bold rounded-pill">
                                <i class="feather-users me-1 fs-11"></i>{{ $secTotalPersonnel }}
                            </span>
                        </div>
                    </div>

                    <div class="modal-body p-4">
                        <div class="tab-content">
                            <!-- Tab 1: Internal Employees -->
                            <div class="tab-pane fade show active" id="sec-internal-pane-{{ $section->id }}" role="tabpanel" aria-labelledby="sec-internal-tab-{{ $section->id }}">
                                <!-- Scope & Exclusivity Banner -->
                                <div class="alert alert-info py-2.5 px-3 fs-12 mb-3 d-flex align-items-start gap-2">
                                    <i class="feather-info text-info fs-16 mt-0.5"></i>
                                    <div>
                                        <strong>Scope of Assignment:</strong> Internal employees assigned here are dedicated directly to <strong>{{ $section->name }}</strong>.
                                        <div class="text-muted fs-11 mt-0.5">
                                            Employees assigned at the Work Center level automatically have operational coverage across all sections. An employee dedicated to a Section cannot be assigned to another Section or Work Center simultaneously.
                                        </div>
                                    </div>
                                </div>

                                <!-- Currently Assigned Employees List -->
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-bold text-dark fs-13 mb-0">
                                            Assigned Employees
                                            <span class="badge bg-soft-primary text-primary fs-10 px-2 py-0.5 rounded-pill ms-1 font-monospace">
                                                {{ $secActiveEmpCount }} Active
                                            </span>
                                        </h6>
                                    </div>

                                    @if($section->employeeAssignments && $section->employeeAssignments->isNotEmpty())
                                        <div class="table-responsive border rounded">
                                            <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th style="width: 18%">Employee ID</th>
                                                        <th style="width: 25%">Name</th>
                                                        <th style="width: 22%">Designation</th>
                                                        <th style="width: 20%">Predefined Shift</th>
                                                        <th style="width: 5%">Status</th>
                                                        <th style="width: 10%" class="text-end">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($section->employeeAssignments as $assignment)
                                                        @php
                                                            $emp = $assignment->employee;
                                                            $empId = $emp?->employee_id ?? ('EMP-' . ($emp?->id ?? $assignment->employee_id));
                                                            $empFullName = $emp?->full_name ?? $emp?->name ?? 'Unknown Employee';
                                                            $desigName = $emp?->designation?->name ?? $emp?->job_title;
                                                            $deptName = $emp?->department?->name;
                                                            $empShift = $emp?->shift;
                                                            $secActiveShifts = $section->shifts->filter(fn($s) => (bool) ($s->active ?? true));
                                                            $secCompatResult = ($emp && $empShift && $secActiveShifts->isNotEmpty())
                                                                ? app(\App\Domains\Production\Services\WorkCenterEmployeeService::class)->isEmployeeCompatibleWithShifts($emp, $secActiveShifts, true)
                                                                : false;
                                                        @endphp
                                                        <tr>
                                                            <td class="font-monospace text-dark fw-semibold">
                                                                {{ $empId }}
                                                            </td>
                                                            <td>
                                                                <div class="fw-semibold text-dark">{{ $empFullName }}</div>
                                                                @if($emp?->office_email ?? $emp?->personal_email)
                                                                    <small class="text-muted fs-11">{{ $emp?->office_email ?? $emp?->personal_email }}</small>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <span class="text-dark fw-medium">{{ $desigName ?? 'Staff' }}</span>
                                                                @if($deptName)
                                                                    <div class="text-muted fs-11">{{ $deptName }}</div>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if($empShift)
                                                                    @if($secCompatResult)
                                                                        <span class="badge bg-soft-success text-success px-2 py-1 font-monospace" title="Shift Compatible: Matches Section Operating Hours">
                                                                            <i class="feather-clock me-1"></i>{{ $empShift->name }} ({{ substr($empShift->start_time, 0, 5) }}&ndash;{{ substr($empShift->end_time, 0, 5) }})
                                                                        </span>
                                                                    @else
                                                                        <span class="badge bg-soft-danger text-danger px-2 py-1 font-monospace border border-danger-subtle" title="Shift Mismatch: Incompatible with Section Operating Hours">
                                                                            <i class="feather-alert-triangle me-1"></i>{{ $empShift->name }} ({{ substr($empShift->start_time, 0, 5) }}&ndash;{{ substr($empShift->end_time, 0, 5) }}) [Mismatch]
                                                                        </span>
                                                                    @endif
                                                                @else
                                                                    <span class="badge bg-soft-danger text-danger px-2 py-1 font-monospace">
                                                                        <i class="feather-alert-circle me-1"></i>No shift predefined
                                                                    </span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if($assignment->active)
                                                                    <span class="badge bg-soft-success text-success rounded-pill px-2">Active</span>
                                                                @else
                                                                    <span class="badge bg-soft-secondary text-secondary rounded-pill px-2">Inactive</span>
                                                                @endif
                                                            </td>
                                                            <td class="text-end">
                                                                <div class="d-inline-flex gap-1">
                                                                    <form method="POST" action="{{ route('production.work-center-employees.toggle-status', $assignment->id) }}" class="d-inline">
                                                                        @csrf
                                                                        @method('PATCH')
                                                                        <input type="hidden" name="active" value="{{ $assignment->active ? 0 : 1 }}">
                                                                        <button type="submit" class="btn btn-xs {{ $assignment->active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $assignment->active ? 'Deactivate' : 'Activate' }}">
                                                                            <i class="{{ $assignment->active ? 'feather-pause-circle' : 'feather-play-circle' }}"></i>
                                                                        </button>
                                                                    </form>
                                                                    <form method="POST" action="{{ route('production.work-center-employees.destroy', $assignment->id) }}" class="d-inline" data-confirm="Remove {{ $empFullName }} from this Section?">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="btn btn-xs btn-outline-danger" title="Remove assignment">
                                                                            <i class="feather-trash-2"></i>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="text-center py-4 bg-light rounded border border-dashed text-muted fs-12">
                                            <i class="feather-users fs-18 d-block mb-1 text-muted"></i>
                                            No internal employees currently assigned directly to this Section.
                                        </div>
                                    @endif
                                </div>

                                <!-- Assign Section Employee Form -->
                                <div class="border rounded p-3 bg-light">
                                    <h6 class="fw-bold text-dark fs-12 mb-2">
                                        <i class="feather-user-plus text-primary me-1"></i>Assign New Employee to {{ $section->name }}
                                    </h6>

                                    @php
                                        $secActiveShifts = $section->shifts->filter(fn($s) => (bool) ($s->active ?? true));
                                        $hasSecShifts = $secActiveShifts->isNotEmpty();
                                    @endphp

                                    @if(!$hasSecShifts)
                                        <div class="alert alert-warning py-2.5 px-3 fs-12 mb-0 d-flex align-items-center gap-2">
                                            <i class="feather-alert-triangle text-warning fs-18"></i>
                                            <div>
                                                <strong>Section has no active operating shifts assigned.</strong>
                                                <div class="text-muted fs-11 mt-0.5">
                                                    Internal employees cannot be assigned until at least one active operating shift is assigned to this section.
                                                </div>
                                            </div>
                                        </div>
                                    @elseif(($availableEmployees ?? collect())->isEmpty())
                                        <div class="alert alert-warning py-2 px-3 fs-11 mb-0">
                                            <i class="feather-alert-triangle me-1"></i>
                                            No active employees found in this organization to assign.
                                        </div>
                                    @else
                                        @php
                                            $activeSecEmpIds = $section->employeeAssignments ? $section->employeeAssignments->where('active', true)->pluck('employee_id')->all() : [];
                                            $activeSecEmpMap = array_flip($activeSecEmpIds);
                                            $secEmpService = app(\App\Domains\Production\Services\WorkCenterEmployeeService::class);
                                        @endphp
                                        <form method="POST" action="{{ route('production.work-center-employees.store') }}" id="assignSectionEmployeeForm_{{ $section->id }}">
                                            @csrf
                                            <input type="hidden" name="work_center_id" value="{{ $workCenter->id }}">
                                            <input type="hidden" name="section_id" value="{{ $section->id }}">

                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-9">
                                                    <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">Select Employee <span class="text-danger">*</span></label>
                                                    <select name="employee_id" class="form-select form-select-sm" data-select2-selector="default" style="width: 100%;" required id="secEmployeeSelect_{{ $section->id }}">
                                                        <option value="">-- Choose Employee --</option>
                                                        @foreach($availableEmployees as $emp)
                                                            @php
                                                                $isAssignedToThisSec = isset($activeSecEmpMap[$emp->id]);
                                                                $assignedWcAssignments = ($employeesWithActiveWorkCenter ?? collect())->get($emp->id);
                                                                $isAssignedToAnyWc = $assignedWcAssignments !== null && $assignedWcAssignments->isNotEmpty();
                                                                $assignedOtherSec = ($employeesWithActiveSection ?? collect())->get($emp->id);
                                                                $isAssignedToOtherSec = $assignedOtherSec !== null && $assignedOtherSec->section_id !== $section->id;
                                                                $compatStatus = $secEmpService->getShiftCompatibilityStatus($emp, $secActiveShifts, true);
                                                                $isShiftIncompatible = !$compatStatus['compatible'];

                                                                $isDisabled = $isAssignedToThisSec || $isAssignedToAnyWc || $isAssignedToOtherSec || $isShiftIncompatible;

                                                                $empCode = $emp->employee_id ?? ('EMP-' . $emp->id);
                                                                $empName = $emp->full_name ?? $emp->name ?? 'Employee #' . $emp->id;
                                                                $desig = $emp->designation?->name ?? $emp->job_title ?? 'Staff';
                                                                $dept = $emp->department?->name;

                                                                if ($isAssignedToThisSec) {
                                                                    $statusLabel = '(Already Assigned to this Section)';
                                                                } elseif ($isAssignedToAnyWc) {
                                                                    $wcNames = $assignedWcAssignments->map(fn($a) => $a->workCenter?->name ?? ('#' . $a->work_center_id))->unique()->implode(', ');
                                                                    $statusLabel = '(Assigned at Work Center level: ' . $wcNames . ')';
                                                                } elseif ($isAssignedToOtherSec) {
                                                                    $statusLabel = '(Assigned to Section: ' . ($assignedOtherSec->section?->name ?? ('#' . $assignedOtherSec->section_id)) . ')';
                                                                } elseif ($isShiftIncompatible) {
                                                                    $statusLabel = '(' . $compatStatus['label'] . ')';
                                                                } else {
                                                                    $statusLabel = '';
                                                                }
                                                            @endphp
                                                            <option value="{{ $emp->id }}"
                                                                    @if($isDisabled) disabled @endif
                                                                    @if($isDisabled) class="text-danger fst-italic" style="color: #dc3545; font-style: italic; background-color: #fff5f5;" @endif>
                                                                @if($isDisabled)⛔ @endif{{ $empName }} ({{ $empCode }}) &mdash; {{ $desig }}@if($dept) [{{ $dept }}]@endif @if($statusLabel) {{ $statusLabel }}@endif
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <button type="submit" class="btn btn-sm btn-primary w-100">
                                                        <i class="feather-plus me-1"></i>Assign
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            <!-- Tab 2: External Workers -->
                            <div class="tab-pane fade" id="sec-external-pane-{{ $section->id }}" role="tabpanel" aria-labelledby="sec-external-tab-{{ $section->id }}">
                                <!-- Overview Metric -->
                                <div class="row g-3 mb-4">
                                    <div class="col-sm-6">
                                        <div class="p-3 rounded bg-soft-primary border border-primary-subtle h-100">
                                            <div class="fs-11 text-uppercase fw-semibold mb-1 text-primary">
                                                <i class="feather-users me-1"></i>Active External Workers
                                            </div>
                                            <div class="fs-22 fw-bold font-monospace text-primary">
                                                {{ $secTotalActiveWorkers }} <span class="fs-12 fw-normal text-muted">workers allocated</span>
                                            </div>
                                            <small class="text-muted fs-11 d-block mt-1">{{ $secActiveAllocs->count() }} active allocation record(s)</small>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="p-3 rounded bg-light border text-dark h-100">
                                            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">
                                                <i class="feather-clock me-1 text-secondary"></i>Configured Section Operating Shifts
                                            </div>
                                            <div class="fs-22 fw-bold font-monospace text-dark">
                                                {{ $section->shifts->count() }} <span class="fs-12 fw-normal text-muted">operating shift(s)</span>
                                            </div>
                                            <small class="text-muted fs-11 d-block mt-1">Allocations can only be assigned to these operating shifts</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- All Allocations Table (active + inactive) -->
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-bold text-dark fs-13 mb-0">Section External Worker Allocations
                                            <span class="badge bg-soft-success text-success fs-10 px-2 py-0.5 rounded-pill ms-1">{{ $secActiveAllocs->count() }} Active</span>
                                            @if($secInactiveAllocs->isNotEmpty())
                                                <span class="badge bg-soft-secondary text-secondary fs-10 px-2 py-0.5 rounded-pill ms-1">{{ $secInactiveAllocs->count() }} Inactive</span>
                                            @endif
                                        </h6>
                                    </div>

                                    @if($secExtAllocs->isNotEmpty())
                                        <div class="table-responsive border rounded">
                                            <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th style="width: 25%">Shift</th>
                                                        <th style="width: 22%">Pay Grade</th>
                                                        <th style="width: 13%">Hourly Rate</th>
                                                        <th style="width: 20%">Workers</th>
                                                        <th style="width: 8%">Status</th>
                                                        <th style="width: 12%" class="text-end">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($secExtAllocs as $alloc)
                                                        <tr class="{{ $alloc->active ? '' : 'opacity-60 bg-light' }}">
                                                            <td>
                                                                <span class="fw-semibold text-dark">{{ $alloc->shift?->name ?? 'Unknown Shift' }}</span>
                                                                @if($alloc->shift)
                                                                    <small class="text-muted d-block font-monospace fs-10">({{ substr($alloc->shift->start_time, 0, 5) }}&ndash;{{ substr($alloc->shift->end_time, 0, 5) }})</small>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <div class="fw-semibold text-dark">{{ $alloc->payGrade?->name ?? 'Unknown' }}</div>
                                                                <span class="font-monospace text-muted fs-11">[{{ $alloc->payGrade?->code ?? 'N/A' }}]</span>
                                                            </td>
                                                            <td class="font-monospace">
                                                                {{ format_currency((float) ($alloc->payGrade?->hourly_rate ?? 0)) }} <span class="text-muted fs-10">/ hr</span>
                                                            </td>
                                                            <td>
                                                                @if($alloc->active)
                                                                    <form method="POST" action="{{ route('production.external-worker-allocations.quantity', $alloc->id) }}" class="d-flex align-items-center gap-1 ext-worker-qty-form">
                                                                        @csrf
                                                                        @method('PATCH')
                                                                        <input type="number" name="number_of_external_workers" min="1" value="{{ $alloc->number_of_external_workers }}" data-initial-value="{{ $alloc->number_of_external_workers }}" class="form-control form-control-sm font-monospace text-center py-0 px-1 ext-worker-qty-input" style="width: 60px; height: 26px;">
                                                                        <button type="submit" class="btn btn-xs btn-outline-primary ext-worker-qty-btn d-none" title="Update Number of External Workers">
                                                                            <i class="feather-check"></i>
                                                                        </button>
                                                                    </form>
                                                                @else
                                                                    <span class="font-monospace text-muted">{{ $alloc->number_of_external_workers }}</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if($alloc->active)
                                                                    <span class="badge bg-soft-success text-success rounded-pill px-2">Active</span>
                                                                @else
                                                                    <span class="badge bg-soft-secondary text-secondary rounded-pill px-2">Inactive</span>
                                                                @endif
                                                            </td>
                                                            <td class="text-end">
                                                                <div class="d-inline-flex gap-1">
                                                                    @if($alloc->active)
                                                                        <form method="POST" action="{{ route('production.external-worker-allocations.toggle-status', $alloc->id) }}" class="d-inline">
                                                                            @csrf
                                                                            @method('PATCH')
                                                                            <input type="hidden" name="active" value="0">
                                                                            <button type="submit" class="btn btn-xs btn-outline-warning" title="Deactivate allocation">
                                                                                <i class="feather-pause-circle"></i>
                                                                            </button>
                                                                        </form>
                                                                    @else
                                                                        <form method="POST" action="{{ route('production.external-worker-allocations.toggle-status', $alloc->id) }}" class="d-inline">
                                                                            @csrf
                                                                            @method('PATCH')
                                                                            <input type="hidden" name="active" value="1">
                                                                            <button type="submit" class="btn btn-xs btn-outline-success" title="Activate allocation">
                                                                                <i class="feather-play-circle"></i>
                                                                            </button>
                                                                        </form>
                                                                    @endif
                                                                    <form method="POST" action="{{ route('production.external-worker-allocations.destroy', $alloc->id) }}" class="d-inline" data-confirm="Permanently remove this external worker allocation?">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="btn btn-xs btn-outline-danger" title="Remove allocation">
                                                                            <i class="feather-trash-2"></i>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="text-center py-3 bg-light rounded border border-dashed text-muted fs-12">
                                            No external worker allocations for this Section yet.
                                        </div>
                                    @endif
                                </div>

                                <!-- Add External Worker Allocation Form -->
                                <div class="border rounded p-3 bg-light">
                                    <h6 class="fw-bold text-dark fs-12 mb-2">
                                        <i class="feather-plus-circle text-primary me-1"></i>Allocate External Workers
                                    </h6>

                                    @if(!$section->isActive())
                                        <div class="alert alert-warning py-2 px-3 fs-11 mb-0">
                                            <i class="feather-alert-triangle me-1"></i>
                                            Cannot create allocations because this Section is inactive.
                                        </div>
                                    @elseif($section->shifts->isEmpty())
                                        <div class="alert alert-warning py-2 px-3 fs-11 mb-0">
                                            <i class="feather-alert-triangle me-1"></i>
                                            Cannot allocate external workers because this Section has no operating shifts configured. Please configure shifts in Section settings first.
                                        </div>
                                    @elseif(($activePayGrades ?? collect())->isEmpty())
                                        <div class="alert alert-info py-2 px-3 fs-11 mb-0">
                                            <i class="feather-info me-1"></i>
                                            No active external worker pay grades found. Please create or activate pay grades in 
                                            <a href="{{ route('production.pay-grades.index') }}" class="fw-bold text-primary">Pay Grade Management</a> first.
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('production.external-worker-allocations.store') }}">
                                            @csrf
                                            <input type="hidden" name="section_id" value="{{ $section->id }}">

                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-4">
                                                    <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">Operating Shift <span class="text-danger">*</span></label>
                                                    <select name="shift_id" class="form-select form-select-sm sec-shift-select" data-select2-selector="default" style="width: 100%;" required>
                                                        <option value="">-- Select Section Shift --</option>
                                                        @foreach($section->shifts as $secShift)
                                                            @php
                                                                $startTime = substr($secShift->start_time, 0, 5);
                                                                $endTime = substr($secShift->end_time, 0, 5);
                                                                $timeStr = $startTime . '–' . $endTime;
                                                                $hasTime = str_contains($secShift->name, $startTime);
                                                            @endphp
                                                            <option value="{{ $secShift->id }}">
                                                                {{ $secShift->name }}{{ $hasTime ? '' : ' (' . $timeStr . ')' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">Pay Grade <span class="text-danger">*</span></label>
                                                    <select name="pay_grade_id" class="form-select form-select-sm" data-select2-selector="default" style="width: 100%;" required>
                                                        <option value="">-- Select Active Pay Grade --</option>
                                                        @foreach($activePayGrades as $pg)
                                                            <option value="{{ $pg->id }}">
                                                                {{ $pg->name }} [{{ $pg->code }}] &mdash; {{ format_currency((float) $pg->hourly_rate) }}/hr
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">Workers <span class="text-danger">*</span></label>
                                                    <input type="number" name="number_of_external_workers" class="form-control form-control-sm font-monospace" min="1" value="1" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="submit" class="btn btn-sm btn-primary w-100">
                                                        <i class="feather-plus me-1"></i>Allocate
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" name="update_if_exists" value="1" id="update_sec_if_exists_{{ $section->id }}" checked>
                                                <label class="form-check-label fs-11 text-muted" for="update_sec_if_exists_{{ $section->id }}">
                                                    Update Number of External Workers if allocation already exists for this Shift and Pay Grade
                                                </label>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <!-- Section-Level Manual Production Modals -->
    @php
        $tenantId = (int) $workCenter->tenant_id;
        $allActiveBatches = \App\Domains\Production\Models\SectionManualProduction::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with(['employees', 'externalAllocations', 'section'])
            ->get();

        $occupiedEmpAssignments = [];
        $occupiedEmployeeIds = [];
        $occupiedExtAllocations = [];

        foreach ($allActiveBatches as $actBatch) {
            foreach ($actBatch->employees as $empAssign) {
                $occupiedEmpAssignments[$empAssign->id] = [
                    'batch_id' => $actBatch->id,
                    'batch_name' => $actBatch->batch_name,
                    'section_name' => $actBatch->section->name ?? '',
                ];
                if ($empAssign->employee_id) {
                    $occupiedEmployeeIds[$empAssign->employee_id] = [
                        'batch_id' => $actBatch->id,
                        'batch_name' => $actBatch->batch_name,
                        'section_name' => $actBatch->section->name ?? '',
                    ];
                }
            }
            foreach ($actBatch->externalAllocations as $extAlloc) {
                $occupiedExtAllocations[$extAlloc->id] = [
                    'batch_id' => $actBatch->id,
                    'batch_name' => $actBatch->batch_name,
                    'section_name' => $actBatch->section->name ?? '',
                ];
            }
        }
    @endphp
    @foreach ($workCenter->sections as $section)
        @php
            $secActiveShifts = $section->shifts->filter(fn($s) => (bool) ($s->active ?? true));
            $secInternalAssignments = $section->employeeAssignments->where('active', true)
                ->concat($workCenter->employeeAssignments->whereNull('section_id')->where('active', true))
                ->unique('id');
            $secExtAllocs = $section->externalWorkerAllocations->where('active', true);
            $hasBatches = $section->manualProductions->isNotEmpty();
        @endphp
        <div class="modal fade manual-production-modal" id="sectionManualProductionModal_{{ $section->id }}" tabindex="-1" aria-labelledby="sectionManualProductionModalLabel_{{ $section->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
                <form method="POST" action="{{ route('production.work-center-sections.manual-productions.store', $section->id) }}" id="manualProductionForm_{{ $section->id }}" class="modal-content border-0 shadow-lg rounded-3 manual-prod-form" data-section-id="{{ $section->id }}" data-store-url="{{ route('production.work-center-sections.manual-productions.store', $section->id) }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST" class="form-method-input">
                    <input type="hidden" name="_modal" value="sectionManualProductionModal_{{ $section->id }}">
                    <input type="hidden" name="batch_id" class="batch-id-input" value="">

                    <div class="modal-header border-bottom py-3 px-4 bg-light flex-shrink-0">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <h5 class="modal-title fw-bold text-dark fs-16 mb-0 d-flex align-items-center" id="sectionManualProductionModalLabel_{{ $section->id }}">
                                        <i class="feather-tool text-warning me-2 fs-18"></i>Manual Production Setup
                                    </h5>
                                    <span class="badge bg-soft-primary text-primary px-2.5 py-1 rounded-pill font-monospace fs-11 ms-1">
                                        <i class="feather-layers me-1"></i>Section: {{ $section->name }} ({{ $section->code }})
                                    </span>
                                </div>
                                <div class="text-muted fs-11 mt-1">
                                    Work Center: <span class="font-monospace fw-semibold text-dark">{{ $workCenter->code }} &mdash; {{ $workCenter->name }}</span>
                                </div>
                            </div>
                            <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                    </div>

                    @if($hasBatches)
                        <!-- Dedicated Batches Toolbar Below Header -->
                        <div class="manual-batches-bar border-bottom flex-shrink-0" style="background-color: #f8fafc; padding: 12px 20px;">
                            <div class="d-flex align-items-center flex-wrap" style="gap: 8px; row-gap: 8px;">
                                <span class="text-muted fs-11 fw-bold text-uppercase me-1 d-inline-flex align-items-center">
                                    <i class="feather-layers text-primary me-1"></i>Batches:
                                </span>
                                <button type="button" class="btn btn-xs btn-primary rounded-pill px-3 py-1 fw-semibold manual-batch-mode-btn active" data-section-id="{{ $section->id }}" data-batch-mode="create">
                                    <i class="feather-plus me-1"></i>New Batch
                                </button>
                                @foreach($section->manualProductions as $exBatch)
                                    <button type="button" 
                                            class="btn btn-xs btn-outline-secondary rounded-pill px-2.5 py-1 fw-semibold manual-batch-mode-btn"
                                            data-section-id="{{ $section->id }}"
                                            data-batch-mode="edit"
                                            data-batch-id="{{ $exBatch->id }}"
                                            data-batch-name="{{ $exBatch->batch_name }}"
                                            data-shift-id="{{ $exBatch->shift_id }}"
                                            data-capacity="{{ $exBatch->capacity_per_hour }}"
                                            data-active="{{ $exBatch->active ? '1' : '0' }}"
                                            data-update-url="{{ route('production.manual-productions.update', $exBatch->id) }}"
                                            data-delete-url="{{ route('production.manual-productions.destroy', $exBatch->id) }}"
                                            data-internal-ids="{{ json_encode($exBatch->employees->pluck('id')->values()->all()) }}"
                                            data-external-ids="{{ json_encode($exBatch->externalAllocations->pluck('id')->values()->all()) }}"
                                            title="Click to edit {{ $exBatch->batch_name }}">
                                        <i class="feather-edit-2 me-1 text-primary"></i>{{ $exBatch->batch_name }}
                                        <span class="badge bg-white text-dark border ms-1 font-monospace fs-10">{{ (float) $exBatch->capacity_per_hour }} u/h</span>
                                        @if(!$exBatch->active)
                                            <span class="badge bg-soft-danger text-danger border border-danger-subtle ms-1 font-monospace fs-10">Inactive</span>
                                        @else
                                            <span class="badge bg-soft-success text-success border border-success-subtle ms-1 font-monospace fs-10">Active</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="modal-body p-4" style="overflow-y: auto;">
                        @if(old('_modal') === 'sectionManualProductionModal_'.$section->id && $errors->any())
                            <div class="alert alert-danger py-2.5 px-3 fs-12 d-flex align-items-center gap-2 mb-3" role="alert">
                                <i class="feather-alert-triangle text-danger fs-16 flex-shrink-0"></i>
                                <div>
                                    <strong>Validation Error:</strong> {{ $errors->first() }}
                                </div>
                            </div>
                        @endif

                        <!-- Worker Selection Validation Error Alert -->
                        <div class="alert alert-danger py-2 px-3 fs-12 d-flex align-items-center gap-2 mb-3 d-none worker-selection-error" role="alert">
                            <i class="feather-alert-triangle text-danger fs-16 flex-shrink-0"></i>
                            <div>
                                <strong>Worker Selection Required:</strong> A manual production batch cannot be created without selecting at least one worker (internal employee or external worker allocation).
                            </div>
                        </div>
                        <!-- Top Shift Filter Bar -->
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-7">
                                    <label class="form-label fw-bold text-dark fs-12 mb-1" for="shiftSelect_{{ $section->id }}">
                                        <i class="feather-clock text-primary me-1"></i>Select Operating Shift <span class="text-danger">*</span>
                                    </label>
                                    <select name="shift_id" class="form-select form-select-sm shift-filter-select" data-select2-selector="default" id="shiftSelect_{{ $section->id }}" style="width: 100%;" required>
                                        @if($secActiveShifts->isEmpty())
                                            <option value="">No Active Operating Shifts Configured</option>
                                        @else
                                            @foreach($secActiveShifts as $sShift)
                                                @php
                                                    $startTime = substr($sShift->start_time, 0, 5);
                                                    $endTime = substr($sShift->end_time, 0, 5);
                                                    $timeStr = $startTime . '–' . $endTime;
                                                    $hasTime = str_contains($sShift->name, $startTime);
                                                @endphp
                                                <option value="{{ $sShift->id }}" 
                                                        data-name="{{ $sShift->name }}"
                                                        data-start="{{ $startTime }}" 
                                                        data-end="{{ $endTime }}"
                                                        @selected($loop->first)>
                                                    {{ $sShift->name }}{{ $hasTime ? '' : ' (' . $timeStr . ')' }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                    <div class="fs-11 text-muted mt-1">
                                        <i class="feather-info me-1"></i>Filters both internal and external worker pools below to workers operating on this shift.
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="p-2.5 rounded-2 bg-white border text-md-end">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="fs-10 fw-bold text-muted text-uppercase">Operating Shift Scope</span>
                                            <span class="badge bg-soft-info text-info font-monospace fs-11 shift-time-display">
                                                @if($secActiveShifts->first())
                                                    {{ substr($secActiveShifts->first()->start_time, 0, 5) }} &ndash; {{ substr($secActiveShifts->first()->end_time, 0, 5) }}
                                                @else
                                                    N/A
                                                @endif
                                            </span>
                                        </div>
                                        <div class="fs-12 fw-semibold text-dark text-truncate shift-name-display">
                                            {{ $secActiveShifts->first()?->name ?? 'No shift' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($secActiveShifts->isEmpty())
                            <div class="alert alert-warning py-3 px-3 fs-12 d-flex align-items-center gap-2 mb-0">
                                <i class="feather-alert-triangle text-warning fs-18 flex-shrink-0"></i>
                                <div>
                                    <strong>No Operating Shifts Configured:</strong> Manual production batches must be anchored to an operating shift. Please configure shifts for this section before creating batches.
                                </div>
                            </div>
                        @else
                            <!-- Two Side-by-Side Worker Blocks -->
                            <div class="row g-3 mb-3">
                                <!-- Column 1: Internal Employees -->
                                <div class="col-md-6">
                                    <div class="card border rounded-3 h-100 shadow-none mb-0">
                                        <div class="card-header bg-white py-2.5 px-3 border-bottom d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <i class="feather-user-check text-info fs-14 me-2"></i>
                                                <span class="fw-bold text-dark fs-13">Internal Employees</span>
                                            </div>
                                            <span class="badge bg-soft-info text-info rounded-pill font-monospace fs-10 px-2 py-1 internal-selected-badge">
                                                0 Selected
                                            </span>
                                        </div>
                                        <div class="card-body p-3">
                                            <!-- Search Box -->
                                            <div class="input-group input-group-sm mb-2">
                                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="feather-search fs-11"></i></span>
                                                <input type="text" class="form-control form-control-sm border-start-0 search-workers-input search-internal-input" data-target="#internalList_{{ $section->id }}" placeholder="Search employees by name, ID or role...">
                                            </div>
                                            <!-- Select All Bar -->
                                            <div class="d-flex align-items-center justify-content-between py-1.5 px-2 mb-2 bg-light rounded-2 border">
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="checkbox" class="form-check-input select-all-btn select-all-internal m-0" id="selectAllInternal_{{ $section->id }}" data-target="#internalList_{{ $section->id }}" style="float: none; width: 1.05rem; height: 1.05rem; cursor: pointer;">
                                                    <label class="form-check-label fs-11 fw-semibold text-dark cursor-pointer m-0" for="selectAllInternal_{{ $section->id }}">
                                                        Select All on this Shift
                                                    </label>
                                                </div>
                                                <span class="badge bg-white text-muted border font-monospace fs-10 internal-pool-count-label"></span>
                                            </div>
                                            <!-- Scrollable Checkbox List -->
                                            <div class="workers-scroll-list border rounded p-2 bg-light-subtle" id="internalList_{{ $section->id }}" style="min-height: 90px; max-height: 200px; overflow-y: auto;">
                                                @forelse($secInternalAssignments as $assignment)
                                                    @php
                                                        $emp = $assignment->employee;
                                                        if (!$emp) continue;
                                                        $empShift = $emp->shift;
                                                        $empShiftId = $empShift?->id ?? '';
                                                        $empShiftName = $empShift?->name ?? 'Any Shift';
                                                        $isWcLevel = is_null($assignment->section_id);

                                                        $assignedBatchInfo = $occupiedEmpAssignments[$assignment->id] ?? ($emp ? ($occupiedEmployeeIds[$emp->id] ?? null) : null);
                                                        $isAssignedToOther = !empty($assignedBatchInfo);
                                                        $assignedBatchId = $assignedBatchInfo['batch_id'] ?? '';
                                                        $assignedBatchName = $assignedBatchInfo['batch_name'] ?? '';
                                                    @endphp
                                                    <div class="worker-card-item rounded-2 mb-2 p-2.5 bg-white border worker-row internal-worker-row {{ $isAssignedToOther ? 'worker-occupied bg-light opacity-75' : '' }}"
                                                         data-worker-name="{{ strtolower($emp->full_name ?? $emp->name ?? '') }} {{ strtolower($emp->employee_id ?? '') }} {{ strtolower($emp->designation?->name ?? '') }}"
                                                         data-shift-id="{{ $empShiftId }}"
                                                         data-assigned-batch-id="{{ $assignedBatchId }}"
                                                         data-assigned-batch-name="{{ $assignedBatchName }}">
                                                        <label class="d-flex align-items-start gap-2.5 w-100 {{ $isAssignedToOther ? 'cursor-not-allowed' : 'cursor-pointer' }} mb-0" for="sec_emp_{{ $section->id }}_{{ $assignment->id }}">
                                                            <div class="pt-0.5 flex-shrink-0">
                                                                <input class="form-check-input worker-checkbox internal-cb m-0" 
                                                                       type="checkbox" 
                                                                       name="employee_ids[]" 
                                                                       value="{{ $assignment->id }}" 
                                                                       id="sec_emp_{{ $section->id }}_{{ $assignment->id }}"
                                                                       {{ $isAssignedToOther ? 'disabled' : '' }}
                                                                       style="float: none; width: 1.15rem; height: 1.15rem; cursor: {{ $isAssignedToOther ? 'not-allowed' : 'pointer' }};">
                                                            </div>
                                                            <div class="flex-grow-1 min-w-0">
                                                                <div class="d-flex align-items-center justify-content-between gap-2">
                                                                    <div class="d-flex align-items-center gap-1.5 min-w-0 flex-wrap">
                                                                        <span class="fw-semibold text-dark fs-12 text-truncate">{{ $emp->full_name ?? $emp->name }}</span>
                                                                        <span class="badge bg-soft-warning text-warning border border-warning-subtle rounded-pill font-monospace fs-10 worker-assigned-badge {{ $isAssignedToOther ? '' : 'd-none' }}" title="Assigned to batch: {{ $assignedBatchName }}">
                                                                            <i class="feather-lock me-1"></i>Assigned: <span class="assigned-batch-label">{{ $assignedBatchName }}</span>
                                                                        </span>
                                                                    </div>
                                                                    <span class="badge bg-soft-secondary text-secondary font-monospace fs-10 flex-shrink-0">
                                                                        {{ $emp->employee_id ? '['.$emp->employee_id.']' : '[ID:'.$emp->id.']' }}
                                                                    </span>
                                                                </div>
                                                                <div class="d-flex align-items-center justify-content-between gap-2 mt-1">
                                                                    <span class="text-muted fs-11 text-truncate">
                                                                        {{ $emp->designation?->name ?? 'Staff' }}
                                                                        @if($isWcLevel)
                                                                            <span class="badge bg-soft-warning text-warning border border-warning-subtle rounded-pill font-monospace fs-9 py-0 ms-1">WC Level</span>
                                                                        @endif
                                                                    </span>
                                                                    <span class="badge bg-light text-muted border font-monospace fs-10 flex-shrink-0">
                                                                        <i class="feather-clock me-1 text-primary"></i>{{ $empShiftName }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                @empty
                                                    <div class="text-center py-4 text-muted fs-11 fst-italic">
                                                        No internal employees assigned to this section or work center.
                                                    </div>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Column 2: External Workers (Agency Allocations) -->
                                <div class="col-md-6">
                                    <div class="card border rounded-3 h-100 shadow-none mb-0">
                                        <div class="card-header bg-white py-2.5 px-3 border-bottom d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <i class="feather-users text-primary fs-14 me-2"></i>
                                                <span class="fw-bold text-dark fs-13">External Workers (Agency Allocations)</span>
                                            </div>
                                            <span class="badge bg-soft-primary text-primary rounded-pill font-monospace fs-10 px-2 py-1 external-selected-badge">
                                                0 Batches (0 Workers)
                                            </span>
                                        </div>
                                        <div class="card-body p-3">
                                            <!-- Search Box -->
                                            <div class="input-group input-group-sm mb-2">
                                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="feather-search fs-11"></i></span>
                                                <input type="text" class="form-control form-control-sm border-start-0 search-workers-input search-external-input" data-target="#externalList_{{ $section->id }}" placeholder="Search by pay grade, agency or code...">
                                            </div>
                                            <!-- Select All Bar -->
                                            <div class="d-flex align-items-center justify-content-between py-1.5 px-2 mb-2 bg-light rounded-2 border">
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="checkbox" class="form-check-input select-all-btn select-all-external m-0" id="selectAllExternal_{{ $section->id }}" data-target="#externalList_{{ $section->id }}" style="float: none; width: 1.05rem; height: 1.05rem; cursor: pointer;">
                                                    <label class="form-check-label fs-11 fw-semibold text-dark cursor-pointer m-0" for="selectAllExternal_{{ $section->id }}">
                                                        Select All on this Shift
                                                    </label>
                                                </div>
                                                <span class="badge bg-white text-muted border font-monospace fs-10 external-pool-count-label"></span>
                                            </div>
                                            <!-- Scrollable Checkbox List -->
                                            <div class="workers-scroll-list border rounded p-2 bg-light-subtle" id="externalList_{{ $section->id }}" style="min-height: 90px; max-height: 200px; overflow-y: auto;">
                                                @forelse($secExtAllocs as $alloc)
                                                    @php
                                                        $pg = $alloc->payGrade;
                                                        $allocShift = $alloc->shift;
                                                        $assignedBatchInfo = $occupiedExtAllocations[$alloc->id] ?? null;
                                                        $isAssignedToOther = !empty($assignedBatchInfo);
                                                        $assignedBatchId = $assignedBatchInfo['batch_id'] ?? '';
                                                        $assignedBatchName = $assignedBatchInfo['batch_name'] ?? '';
                                                    @endphp
                                                    <div class="worker-card-item rounded-2 mb-2 p-2.5 bg-white border worker-row external-worker-row {{ $isAssignedToOther ? 'worker-occupied bg-light opacity-75' : '' }}"
                                                         data-worker-name="{{ strtolower($pg?->name ?? '') }} {{ strtolower($pg?->code ?? '') }} {{ strtolower($allocShift?->name ?? '') }}"
                                                         data-shift-id="{{ $alloc->shift_id }}"
                                                         data-worker-count="{{ $alloc->number_of_external_workers }}"
                                                         data-assigned-batch-id="{{ $assignedBatchId }}"
                                                         data-assigned-batch-name="{{ $assignedBatchName }}">
                                                        <label class="d-flex align-items-start gap-2.5 w-100 {{ $isAssignedToOther ? 'cursor-not-allowed' : 'cursor-pointer' }} mb-0" for="sec_alloc_{{ $section->id }}_{{ $alloc->id }}">
                                                            <div class="pt-0.5 flex-shrink-0">
                                                                <input class="form-check-input worker-checkbox external-cb m-0" 
                                                                       type="checkbox" 
                                                                       name="external_allocation_ids[]" 
                                                                       value="{{ $alloc->id }}" 
                                                                       id="sec_alloc_{{ $section->id }}_{{ $alloc->id }}"
                                                                       data-worker-count="{{ $alloc->number_of_external_workers }}"
                                                                       {{ $isAssignedToOther ? 'disabled' : '' }}
                                                                       style="float: none; width: 1.15rem; height: 1.15rem; cursor: {{ $isAssignedToOther ? 'not-allowed' : 'pointer' }};">
                                                            </div>
                                                            <div class="flex-grow-1 min-w-0">
                                                                <div class="d-flex align-items-center justify-content-between gap-2">
                                                                    <div class="d-flex align-items-center gap-1.5 min-w-0 flex-wrap">
                                                                        <span class="fw-semibold text-dark fs-12 text-truncate">{{ $pg?->name ?? 'External Worker Batch' }}</span>
                                                                        <span class="badge bg-soft-warning text-warning border border-warning-subtle rounded-pill font-monospace fs-10 worker-assigned-badge {{ $isAssignedToOther ? '' : 'd-none' }}" title="Assigned to batch: {{ $assignedBatchName }}">
                                                                            <i class="feather-lock me-1"></i>Assigned: <span class="assigned-batch-label">{{ $assignedBatchName }}</span>
                                                                        </span>
                                                                    </div>
                                                                    <span class="badge bg-soft-primary text-primary font-monospace fs-10 flex-shrink-0">
                                                                        {{ $alloc->number_of_external_workers }} {{ Str::plural('Worker', $alloc->number_of_external_workers) }}
                                                                    </span>
                                                                </div>
                                                                <div class="d-flex align-items-center justify-content-between gap-2 mt-1">
                                                                    <span class="text-muted fs-11 font-monospace text-truncate">
                                                                        Rate: <strong class="text-dark">{{ format_currency((float) ($pg?->hourly_rate ?? 0)) }}</strong> / hr
                                                                    </span>
                                                                    <span class="badge bg-light text-muted border font-monospace fs-10 flex-shrink-0">
                                                                        <i class="feather-clock me-1 text-primary"></i>{{ $allocShift?->name ?? 'Shift #'.$alloc->shift_id }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                @empty
                                                    <div class="text-center py-4 text-muted fs-11 fst-italic">
                                                        No external worker allocations configured for this section.
                                                    </div>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Batch Details, Target Speed, Status -->
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="row g-3 align-items-start">
                                    <div class="col-md-5">
                                        <label class="form-label fw-bold text-dark fs-12 mb-1" for="batchName_{{ $section->id }}">
                                            Batch Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="batch_name" id="batchName_{{ $section->id }}" class="form-control form-control-sm batch-name-input" placeholder="e.g. Morning Packing Line 1" required style="height: 38px;">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold text-dark fs-12 mb-1" for="batchCapacity_{{ $section->id }}">
                                            Target Production Rate <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="any" min="0.0001" name="capacity_per_hour" id="batchCapacity_{{ $section->id }}" class="form-control capacity-input font-monospace" placeholder="e.g. 50.00" required style="height: 38px;">
                                            <span class="input-group-text fs-11 text-muted bg-white font-monospace">pcs / hr</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-dark fs-12 mb-1">Batch Status</label>
                                        <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-white border" style="height: 38px;">
                                            <span class="fw-semibold fs-12 status-label-text text-success">Active Batch</span>
                                            <div class="form-check form-switch m-0 p-0 d-flex align-items-center">
                                                <input type="hidden" name="active" value="0">
                                                <input class="form-check-input batch-active-switch m-0" type="checkbox" name="active" value="1" id="batchActive_{{ $section->id }}" checked style="cursor: pointer; width: 2.25rem; height: 1.2rem;">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Live Dynamic Summary Banner -->
                                <div class="rounded-3 bg-soft-primary border border-primary-subtle py-2.5 px-3 mt-3 mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="feather-check-circle text-primary fs-16 flex-shrink-0"></i>
                                        <span class="fw-semibold text-dark fs-12">
                                            Team Size: <strong class="text-primary font-monospace fs-13 total-team-count">0</strong> Workers
                                            <span class="text-muted ms-1">(<span class="internal-team-count font-monospace text-dark fw-bold">0</span> Internal + <span class="external-team-count font-monospace text-dark fw-bold">0</span> External)</span>
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 font-monospace fs-12">
                                        <span class="text-muted">Target Speed:</span>
                                        <strong class="text-success fs-13 speed-preview-val">0.00</strong>
                                        <span class="text-muted fs-11">pcs / hr</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="modal-footer bg-light py-2.5 px-4 border-top d-flex justify-content-between flex-shrink-0">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-soft-info text-info rounded-pill font-monospace fs-11 mode-indicator-badge px-2.5 py-1">
                                <i class="feather-plus-circle me-1"></i>New Batch Mode
                            </span>
                            <button type="button" class="btn btn-xs btn-outline-danger d-none delete-batch-btn" data-section-id="{{ $section->id }}" title="Delete this batch">
                                <i class="feather-trash-2 me-1"></i>Delete Batch
                            </button>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm px-3 save-batch-submit-btn" @if($secActiveShifts->isEmpty()) disabled @endif>
                                <i class="feather-save me-1"></i><span class="submit-btn-text">Save Manual Batch</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Hidden Delete Form for Batch Deletion -->
        <form method="POST" action="" id="deleteBatchForm_{{ $section->id }}" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    @endforeach

    @push('scripts')
        <script>
            $(function() {
                // =========================================================================
                // MANUAL PRODUCTION MODAL INTERACTIVITY
                // =========================================================================

                function updateManualModalSummary($modal) {
                    // Count internal checked
                    var internalChecked = 0;
                    $modal.find('.internal-worker-row').each(function() {
                        var $row = $(this);
                        var isChecked = $row.find('.internal-cb').is(':checked');
                        if (isChecked) {
                            $row.addClass('selected-card');
                        } else {
                            $row.removeClass('selected-card');
                        }
                        if (!$row.hasClass('d-none')) {
                            if (isChecked) {
                                internalChecked++;
                            }
                        }
                    });

                    // Count external checked & worker headcounts
                    var externalBatchesChecked = 0;
                    var externalWorkersTotal = 0;
                    $modal.find('.external-worker-row').each(function() {
                        var $row = $(this);
                        var $cb = $row.find('.external-cb');
                        var isChecked = $cb.is(':checked');
                        if (isChecked) {
                            $row.addClass('selected-card');
                        } else {
                            $row.removeClass('selected-card');
                        }
                        if (!$row.hasClass('d-none')) {
                            if (isChecked) {
                                externalBatchesChecked++;
                                var count = parseInt($cb.attr('data-worker-count'), 10) || 0;
                                externalWorkersTotal += count;
                            }
                        }
                    });

                    var totalTeam = internalChecked + externalWorkersTotal;

                    // Update badges and summary text
                    $modal.find('.internal-selected-badge').text(internalChecked + ' Selected');
                    $modal.find('.external-selected-badge').text(externalBatchesChecked + ' ' + (externalBatchesChecked === 1 ? 'Batch' : 'Batches') + ' (' + externalWorkersTotal + ' Workers)');
                    $modal.find('.total-team-count').text(totalTeam);
                    $modal.find('.internal-team-count').text(internalChecked);
                    $modal.find('.external-team-count').text(externalWorkersTotal);

                    // Speed preview
                    var speedVal = parseFloat($modal.find('.capacity-input').val()) || 0;
                    $modal.find('.speed-preview-val').text(speedVal.toFixed(2));
                }

                function applyWorkerAvailability($modal, currentEditingBatchId) {
                    currentEditingBatchId = currentEditingBatchId ? String(currentEditingBatchId) : '';

                    $modal.find('.worker-row').each(function() {
                        var $row = $(this);
                        var assignedBatchId = String($row.attr('data-assigned-batch-id') || '');
                        var assignedBatchName = $row.attr('data-assigned-batch-name') || '';
                        var $cb = $row.find('.worker-checkbox');
                        var $badge = $row.find('.worker-assigned-badge');
                        var $label = $row.find('label');

                        if (assignedBatchId !== '' && assignedBatchId !== currentEditingBatchId) {
                            $cb.prop('disabled', true);
                            $badge.removeClass('d-none');
                            $badge.find('.assigned-batch-label').text(assignedBatchName);
                            $badge.attr('title', 'Assigned to batch: ' + assignedBatchName);
                            $row.addClass('worker-occupied bg-light opacity-75');
                            $label.removeClass('cursor-pointer').addClass('cursor-not-allowed');
                            $cb.css('cursor', 'not-allowed');
                        } else {
                            $cb.prop('disabled', false);
                            $badge.addClass('d-none');
                            $row.removeClass('worker-occupied bg-light opacity-75');
                            $label.removeClass('cursor-not-allowed').addClass('cursor-pointer');
                            $cb.css('cursor', 'pointer');
                        }
                    });
                }

                function filterWorkersByShiftAndSearch($modal) {
                    var selectedShiftId = String($modal.find('.shift-filter-select').val() || '');
                    var internalSearch = ($modal.find('.search-internal-input').val() || '').toLowerCase().trim();
                    var externalSearch = ($modal.find('.search-external-input').val() || '').toLowerCase().trim();

                    var visibleInternalCount = 0;
                    var availableInternalCount = 0;
                    var allInternalChecked = true;

                    $modal.find('.internal-worker-row').each(function() {
                        var $row = $(this);
                        var rowShiftId = String($row.attr('data-shift-id') || '');
                        var workerName = ($row.attr('data-worker-name') || '').toLowerCase();

                        // Match shift: if employee has no specific master shift (blank), or matches selected shift
                        var matchesShift = (selectedShiftId === '' || rowShiftId === '' || rowShiftId === selectedShiftId);
                        var matchesSearch = (internalSearch === '' || workerName.indexOf(internalSearch) !== -1);

                        if (matchesShift && matchesSearch) {
                            $row.removeClass('d-none');
                            visibleInternalCount++;
                            var isLocked = $row.find('.internal-cb').is(':disabled');
                            if (!isLocked) {
                                availableInternalCount++;
                                if (!$row.find('.internal-cb').is(':checked')) {
                                    allInternalChecked = false;
                                }
                            }
                        } else {
                            $row.addClass('d-none');
                        }
                    });

                    if (availableInternalCount === 0) allInternalChecked = false;
                    $modal.find('.select-all-internal').prop('checked', availableInternalCount > 0 && allInternalChecked);

                    var lockedInternalCount = visibleInternalCount - availableInternalCount;
                    var intLabelText = availableInternalCount + ' available';
                    if (lockedInternalCount > 0) {
                        intLabelText += ' (' + lockedInternalCount + ' in other batches)';
                    }
                    $modal.find('.internal-pool-count-label').text(intLabelText);

                    var visibleExternalCount = 0;
                    var availableExternalCount = 0;
                    var allExternalChecked = true;

                    $modal.find('.external-worker-row').each(function() {
                        var $row = $(this);
                        var rowShiftId = String($row.attr('data-shift-id') || '');
                        var workerName = ($row.attr('data-worker-name') || '').toLowerCase();

                        var matchesShift = (selectedShiftId === '' || rowShiftId === selectedShiftId);
                        var matchesSearch = (externalSearch === '' || workerName.indexOf(externalSearch) !== -1);

                        if (matchesShift && matchesSearch) {
                            $row.removeClass('d-none');
                            visibleExternalCount++;
                            var isLocked = $row.find('.external-cb').is(':disabled');
                            if (!isLocked) {
                                availableExternalCount++;
                                if (!$row.find('.external-cb').is(':checked')) {
                                    allExternalChecked = false;
                                }
                            }
                        } else {
                            $row.addClass('d-none');
                        }
                    });

                    if (availableExternalCount === 0) allExternalChecked = false;
                    $modal.find('.select-all-external').prop('checked', availableExternalCount > 0 && allExternalChecked);

                    var lockedExternalCount = visibleExternalCount - availableExternalCount;
                    var extLabelText = availableExternalCount + ' allocations available';
                    if (lockedExternalCount > 0) {
                        extLabelText += ' (' + lockedExternalCount + ' in other batches)';
                    }
                    $modal.find('.external-pool-count-label').text(extLabelText);

                    // Update shift display card
                    var $selectedOpt = $modal.find('.shift-filter-select option:selected');
                    if ($selectedOpt.length && $selectedOpt.val()) {
                        $modal.find('.shift-name-display').text($selectedOpt.attr('data-name') || $selectedOpt.text());
                        var start = $selectedOpt.attr('data-start') || '';
                        var end = $selectedOpt.attr('data-end') || '';
                        if (start && end) {
                            $modal.find('.shift-time-display').text(start + ' - ' + end);
                        }
                    }

                    updateManualModalSummary($modal);
                }

                // Event: Shift dropdown change
                $(document).on('change', '.manual-production-modal .shift-filter-select', function() {
                    var $modal = $(this).closest('.manual-production-modal');
                    filterWorkersByShiftAndSearch($modal);
                });

                // Event: Search text typing (internal & external)
                $(document).on('input', '.manual-production-modal .search-workers-input', function() {
                    var $modal = $(this).closest('.manual-production-modal');
                    filterWorkersByShiftAndSearch($modal);
                });

                // Event: "Select All" toggle for Internal
                $(document).on('change', '.manual-production-modal .select-all-internal', function() {
                    var $modal = $(this).closest('.manual-production-modal');
                    var isChecked = $(this).is(':checked');
                    $modal.find('.internal-worker-row:not(.d-none) .internal-cb:not(:disabled)').prop('checked', isChecked);
                    updateManualModalSummary($modal);
                });

                // Event: "Select All" toggle for External
                $(document).on('change', '.manual-production-modal .select-all-external', function() {
                    var $modal = $(this).closest('.manual-production-modal');
                    var isChecked = $(this).is(':checked');
                    $modal.find('.external-worker-row:not(.d-none) .external-cb:not(:disabled)').prop('checked', isChecked);
                    updateManualModalSummary($modal);
                });

                // Event: Individual worker checkbox toggle
                $(document).on('change', '.manual-production-modal .worker-checkbox', function() {
                    var $modal = $(this).closest('.manual-production-modal');
                    // Check if all visible internals are checked
                    var allInternalChecked = true;
                    var hasAvailableInternals = false;
                    $modal.find('.internal-worker-row:not(.d-none)').each(function() {
                        var $cb = $(this).find('.internal-cb');
                        if (!$cb.is(':disabled')) {
                            hasAvailableInternals = true;
                            if (!$cb.is(':checked')) allInternalChecked = false;
                        }
                    });
                    $modal.find('.select-all-internal').prop('checked', hasAvailableInternals && allInternalChecked);

                    // Check if all visible externals are checked
                    var allExternalChecked = true;
                    var hasAvailableExternals = false;
                    $modal.find('.external-worker-row:not(.d-none)').each(function() {
                        var $cb = $(this).find('.external-cb');
                        if (!$cb.is(':disabled')) {
                            hasAvailableExternals = true;
                            if (!$cb.is(':checked')) allExternalChecked = false;
                        }
                    });
                    $modal.find('.select-all-external').prop('checked', hasAvailableExternals && allExternalChecked);

                    updateManualModalSummary($modal);
                });

                // Event: Capacity input typing
                $(document).on('input', '.manual-production-modal .capacity-input', function() {
                    var $modal = $(this).closest('.manual-production-modal');
                    updateManualModalSummary($modal);
                });

                // Event: Active switch toggle
                $(document).on('change', '.manual-production-modal .batch-active-switch', function() {
                    var $modal = $(this).closest('.manual-production-modal');
                    var isChecked = $(this).is(':checked');
                    if (isChecked) {
                        $modal.find('.status-label-text').removeClass('text-muted text-danger').addClass('text-success').text('Active Batch');
                    } else {
                        $modal.find('.status-label-text').removeClass('text-success').addClass('text-danger').text('Inactive Batch');
                    }
                });

                // Event: Batch Mode button click (Create New vs Edit Existing)
                $(document).on('click', '.manual-batch-mode-btn', function() {
                    var $btn = $(this);
                    var $modal = $btn.closest('.manual-production-modal');
                    var mode = $btn.attr('data-batch-mode');
                    var $form = $modal.find('.manual-prod-form');

                    $modal.find('.manual-batch-mode-btn').removeClass('active btn-primary').addClass('btn-outline-secondary');
                    $btn.removeClass('btn-outline-secondary').addClass('active btn-primary');
                    $modal.find('.worker-selection-error').addClass('d-none');

                    if (mode === 'create') {
                        // Reset to Create mode
                        $form.attr('action', $form.attr('data-store-url'));
                        $form.find('.form-method-input').val('POST');
                        $modal.find('.batch-id-input').val('');
                        $modal.find('.batch-name-input').val('');
                        $modal.find('.capacity-input').val('');
                        $modal.find('.batch-active-switch').prop('checked', true).trigger('change');
                        $modal.find('.worker-checkbox').prop('checked', false);
                        applyWorkerAvailability($modal, '');
                        $modal.find('.delete-batch-btn').addClass('d-none');
                        $modal.find('.submit-btn-text').text('Save Manual Batch');
                        $modal.find('.mode-indicator-badge').html('<i class="feather-plus-circle me-1"></i>New Batch Mode');

                        var firstShiftVal = $modal.find('.shift-filter-select option:first').val();
                        if (firstShiftVal) {
                            $modal.find('.shift-filter-select').val(firstShiftVal).trigger('change');
                        }
                    } else if (mode === 'edit') {
                        // Switch to Edit mode
                        var batchId = $btn.attr('data-batch-id');
                        var batchName = $btn.attr('data-batch-name');
                        var shiftId = $btn.attr('data-shift-id');
                        var capacity = $btn.attr('data-capacity');
                        var active = $btn.attr('data-active') === '1';
                        var deleteUrl = $btn.attr('data-delete-url');

                        var internalIds = [];
                        var externalIds = [];
                        try {
                            internalIds = JSON.parse($btn.attr('data-internal-ids') || '[]');
                        } catch(e) {}
                        try {
                            externalIds = JSON.parse($btn.attr('data-external-ids') || '[]');
                        } catch(e) {}

                        // Update form action and method to PUT
                        var updateUrl = $btn.attr('data-update-url') || ('{{ url("production/manual-productions") }}/' + batchId);
                        $form.attr('action', updateUrl);
                        $form.find('.form-method-input').val('PUT');
                        $modal.find('.batch-id-input').val(batchId);

                        // Fill fields
                        $modal.find('.batch-name-input').val(batchName);
                        $modal.find('.capacity-input').val(capacity);
                        $modal.find('.batch-active-switch').prop('checked', active).trigger('change');

                        // Apply availability for this batch (unlocks its own workers, locks others)
                        applyWorkerAvailability($modal, batchId);

                        // Select shift
                        if (shiftId) {
                            $modal.find('.shift-filter-select').val(shiftId).trigger('change');
                        }

                        // Check matching internal & external checkboxes
                        $modal.find('.internal-cb').each(function() {
                            var val = parseInt($(this).val(), 10);
                            $(this).prop('checked', internalIds.indexOf(val) !== -1);
                        });

                        $modal.find('.external-cb').each(function() {
                            var val = parseInt($(this).val(), 10);
                            $(this).prop('checked', externalIds.indexOf(val) !== -1);
                        });

                        // Delete button
                        if (deleteUrl) {
                            $modal.find('.delete-batch-btn').removeClass('d-none').attr('data-delete-url', deleteUrl);
                        } else {
                            $modal.find('.delete-batch-btn').addClass('d-none');
                        }

                        var statusBadge = active 
                            ? '<span class="badge bg-soft-success text-success ms-1 font-monospace fs-10">Active</span>' 
                            : '<span class="badge bg-soft-danger text-danger ms-1 font-monospace fs-10">Inactive</span>';

                        $modal.find('.submit-btn-text').text('Update Manual Batch');
                        $modal.find('.mode-indicator-badge').html('<i class="feather-edit-2 me-1"></i>Editing: ' + $('<div>').text(batchName).html() + ' ' + statusBadge);
                    }

                    filterWorkersByShiftAndSearch($modal);
                });

                // Prevent submitting empty batch (no workers selected)
                $(document).on('submit', '.manual-prod-form', function(e) {
                    var $form = $(this);
                    var $modal = $form.closest('.manual-production-modal');
                    var internalChecked = $modal.find('.internal-cb:checked').length;
                    var externalChecked = $modal.find('.external-cb:checked').length;
                    var totalSelected = internalChecked + externalChecked;

                    if (totalSelected === 0) {
                        e.preventDefault();
                        e.stopPropagation();

                        var $err = $modal.find('.worker-selection-error');
                        $err.removeClass('d-none');
                        if ($err.length && $err[0].scrollIntoView) {
                            $err[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }

                        $modal.find('.workers-scroll-list').addClass('border-danger shadow-sm');
                        setTimeout(function() {
                            $modal.find('.workers-scroll-list').removeClass('border-danger shadow-sm');
                        }, 4000);

                        alert('A manual production batch cannot be created or updated without selecting at least one worker or employee. Please select at least one worker from the lists above.');
                        return false;
                    } else {
                        $modal.find('.worker-selection-error').addClass('d-none');
                    }
                });

                // Event: Delete batch inside modal
                $(document).on('click', '.manual-production-modal .delete-batch-btn', function() {
                    var $modal = $(this).closest('.manual-production-modal');
                    var deleteUrl = $(this).attr('data-delete-url');
                    var secId = $(this).attr('data-section-id');
                    if (deleteUrl && confirm('Are you sure you want to remove this manual batch?')) {
                        var $delForm = $('#deleteBatchForm_' + secId);
                        if ($delForm.length) {
                            $delForm.attr('action', deleteUrl).submit();
                        }
                    }
                });

                // Initialize when modal is shown
                $(document).on('shown.bs.modal', '.manual-production-modal', function() {
                    var $modal = $(this);
                    $modal.find('.shift-filter-select[data-select2-selector]').each(function() {
                        initModalSelect2($(this), $modal);
                    });
                    var activeEditBatchId = $modal.find('.manual-batch-mode-btn.active').attr('data-batch-id') || '';
                    applyWorkerAvailability($modal, activeEditBatchId);
                    filterWorkersByShiftAndSearch($modal);
                });

                // Initialize Bootstrap popovers for machine, resource badges, and breakdown tables
                function initSectionPopovers() {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
                        if (bootstrap.Tooltip && bootstrap.Tooltip.Default && bootstrap.Tooltip.Default.allowList) {
                            var allowedTableTags = ['table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th'];
                            allowedTableTags.forEach(function(tag) {
                                bootstrap.Tooltip.Default.allowList[tag] = ['class', 'style', 'title', 'colspan', 'rowspan'];
                            });
                            if (bootstrap.Tooltip.Default.allowList['*'] && bootstrap.Tooltip.Default.allowList['*'].indexOf('style') === -1) {
                                bootstrap.Tooltip.Default.allowList['*'].push('style');
                            }
                        }
                        $('[data-bs-toggle="popover"]').each(function () {
                            var existing = bootstrap.Popover.getInstance(this);
                            if (existing) {
                                existing.dispose();
                            }
                            new bootstrap.Popover(this, { 
                                sanitize: false,
                                html: true,
                                trigger: 'hover focus'
                            });
                        });
                    }
                }
                initSectionPopovers();
                $(window).on('load', function() {
                    initSectionPopovers();
                });

                // Initialize Bootstrap tooltips for shift badges
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    $('[data-bs-toggle="tooltip"]').each(function () {
                        if (!bootstrap.Tooltip.getInstance(this)) {
                            new bootstrap.Tooltip(this);
                        }
                    });
                }

                // Resource Cluster Pills: click handler to hide hover popover and trigger target modal
                $(document).on('click', '.resource-cluster-pill[data-modal-target]', function(e) {
                    e.preventDefault();
                    if (typeof bootstrap !== 'undefined') {
                        var popover = bootstrap.Popover.getInstance(this);
                        if (popover) {
                            popover.hide();
                        }
                        var targetModal = $(this).attr('data-modal-target');
                        var targetTab = $(this).attr('data-modal-tab');
                        if (targetModal) {
                            var $m = $(targetModal);
                            if ($m.length) {
                                if (targetTab) {
                                    var secId = targetModal.replace('#sectionEmployeesModal_', '');
                                    var tabBtn = $m.find('#sec-' + targetTab + '-tab-' + secId);
                                    if (tabBtn.length && bootstrap.Tab) {
                                        bootstrap.Tab.getOrCreateInstance(tabBtn[0]).show();
                                    }
                                }
                                var modalInstance = bootstrap.Modal.getOrCreateInstance($m[0]);
                                modalInstance.show();
                            }
                        }
                    }
                });

                // Capacity & Cost Popovers: click to open calculation breakdown modal
                $(document).on('click', '.capacity-popover-trigger, .cost-popover-trigger', function(e) {
                    if ($(e.target).closest('button[data-bs-toggle="modal"]').length) return;
                    e.preventDefault();
                    if (typeof bootstrap !== 'undefined') {
                        var popover = bootstrap.Popover.getInstance(this);
                        if (popover) {
                            popover.hide();
                        }
                        var targetModal = $(this).attr('data-bs-target');
                        if (targetModal) {
                            var $m = $(targetModal);
                            if ($m.length) {
                                bootstrap.Modal.getOrCreateInstance($m[0]).show();
                            }
                        }
                    }
                });

                // =========================================================================
                // SECTION DETAILS MODAL TO CHILD MODAL TRANSITIONS
                // =========================================================================

                // Cleanly close section details modal before opening child modal to avoid stacking & z-index issues
                $(document).on('click', '.section-details-modal [data-bs-toggle="modal"][data-bs-target]', function(e) {
                    var targetSelector = $(this).attr('data-bs-target');
                    if (!targetSelector || !targetSelector.startsWith('#')) return;

                    var targetEl = document.querySelector(targetSelector);
                    if (!targetEl || !targetEl.classList.contains('modal')) return;

                    e.preventDefault();
                    e.stopPropagation();

                    var $detailsModal = $(this).closest('.section-details-modal');
                    var targetTab = $(this).attr('data-target-tab');

                    var detailsInstance = bootstrap.Modal.getInstance($detailsModal[0]) || new bootstrap.Modal($detailsModal[0]);
                    detailsInstance.hide();

                    $detailsModal.one('hidden.bs.modal', function() {
                        var targetInstance = bootstrap.Modal.getOrCreateInstance(targetEl);
                        targetInstance.show();

                        if (targetTab) {
                            var tabTrigger = targetEl.querySelector('[data-bs-toggle="pill"][data-target-tab="' + targetTab + '"], [data-bs-toggle="tab"][data-target-tab="' + targetTab + '"]');
                            if (tabTrigger) {
                                var tab = new bootstrap.Tab(tabTrigger);
                                tab.show();
                            }
                        }
                    });
                });

                // Dismiss open popovers & tooltips whenever any modal is displayed
                $(document).on('show.bs.modal', '.modal', function() {
                    if (typeof bootstrap !== 'undefined') {
                        $('[data-bs-toggle="popover"]').each(function() {
                            var p = bootstrap.Popover.getInstance(this);
                            if (p) p.hide();
                        });
                        $('[data-bs-toggle="tooltip"]').each(function() {
                            var t = bootstrap.Tooltip.getInstance(this);
                            if (t) t.hide();
                        });
                    }
                });

                // Handle target-tab navigation when opening personnel modal from shortcuts
                $(document).on('click', '[data-bs-target^="#sectionEmployeesModal_"][data-target-tab]', function() {
                    var targetModal = $(this).attr('data-bs-target');
                    var targetTab = $(this).attr('data-target-tab');
                    if (targetModal && targetTab) {
                        var secId = targetModal.replace('#sectionEmployeesModal_', '');
                        var $m = $(targetModal);
                        var tabBtn = $m.find('#sec-' + targetTab + '-tab-' + secId);
                        if (tabBtn.length && bootstrap.Tab) {
                            bootstrap.Tab.getOrCreateInstance(tabBtn[0]).show();
                        }
                    }
                });

                // Standard modal Select2 initialization adhering to theme-wide bootstrap-5 standard
                function initModalSelect2($select, $modal) {
                    if (!$select || !$select.length || typeof $ === 'undefined' || !$.fn.select2) return;
                    var $modalEl = ($modal && $modal.length) ? $modal : $select.closest('.modal');
                    var $dropdownParent = $modalEl.find('.modal-content').length ? $modalEl.find('.modal-content') : ($modalEl.length ? $modalEl : $(document.body));

                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }

                    var isMulti = $select.prop('multiple');
                    var placeholder = $select.data('placeholder') || $select.find('option:first').text() || 'Select...';

                    $select.select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $dropdownParent,
                        width: '100%',
                        placeholder: placeholder,
                        allowClear: false,
                        closeOnSelect: !isMulti
                    });
                }

                // Initialize visible modal selects and popovers on modal shown
                $(document).on('shown.bs.modal', '.modal', function () {
                    var $modal = $(this);
                    $modal.find('[data-select2-selector]').each(function() {
                        var $sel = $(this);
                        if ($sel.closest('.shift-picker-panel.d-none').length === 0) {
                            initModalSelect2($sel, $modal);
                        }
                    });
                    if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
                        $modal.find('[data-bs-toggle="popover"]').each(function() {
                            var p = bootstrap.Popover.getInstance(this);
                            if (!p) {
                                new bootstrap.Popover(this, { sanitize: false, html: true, trigger: 'hover focus' });
                            }
                        });
                    }
                });

                // Section Personnel Modal: Target tab activation and Select2 re-initialization on tab switch
                $(document).on('click', '[data-bs-toggle="modal"][data-target-tab]', function() {
                    var targetModal = $(this).attr('data-bs-target');
                    var targetTab = $(this).attr('data-target-tab');
                    var targetShift = $(this).attr('data-target-shift');
                    if (targetModal && targetTab) {
                        var $m = $(targetModal);
                        if ($m.length) {
                            var secId = targetModal.replace('#sectionEmployeesModal_', '');
                            var tabBtn = $m.find('#sec-' + targetTab + '-tab-' + secId);
                            if (tabBtn.length && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                                bootstrap.Tab.getOrCreateInstance(tabBtn[0]).show();
                            }
                            if (targetShift && targetTab === 'external') {
                                setTimeout(function() {
                                    var $shiftSel = $m.find('.sec-shift-select');
                                    if ($shiftSel.length) {
                                        $shiftSel.val(targetShift).trigger('change');
                                    }
                                }, 100);
                            }
                        }
                    }
                });

                $(document).on('shown.bs.tab', '.section-personnel-modal [data-bs-toggle="pill"]', function () {
                    var $modal = $(this).closest('.modal');
                    $modal.find('.tab-pane.active [data-select2-selector]').each(function() {
                        initModalSelect2($(this), $modal);
                    });
                });

                // Auto-close Select2 dropdowns when scrolling modal to prevent detached floating menus
                $(document).on('scroll', '.modal, .modal-body', function () {
                    $(this).find('.select2-hidden-accessible').each(function() {
                        if ($(this).data('select2')) {
                            $(this).select2('close');
                        }
                    });
                });

                // Modal close cleanup
                $(document).on('hidden.bs.modal', '.modal', function () {
                    var $modal = $(this);
                    $modal.find('.shift-picker-panel').addClass('d-none');
                    $modal.find('.shift-picker-select').each(function() {
                        if ($(this).data('select2')) {
                            $(this).val('').trigger('change.select2');
                            $(this).select2('close');
                        } else {
                            this.value = '';
                        }
                    });
                    $modal.find('.shift-picker-warning').remove();

                    setTimeout(function() {
                        if (!$('.modal.show').length) {
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open').css({'overflow': '', 'padding-right': ''});
                        }
                    }, 150);
                });

                // Handle manual production toggle
                $(document).on('change', '.manual-production-toggle', function() {
                    var targetSelector = $(this).data('target');
                    var $target = $(targetSelector);
                    if ($target.length) {
                        var $input = $target.find('.manual-capacity-input');
                        if (this.checked) {
                            $target.removeClass('d-none');
                            $input.prop('disabled', false).focus();
                        } else {
                            $target.addClass('d-none');
                            $input.prop('disabled', true).val('');
                        }
                    }
                });

                // External worker quantity inline edit: reveal tick button only when value changes
                $(document).on('input change keyup', '.ext-worker-qty-input', function () {
                    var initialVal = String($(this).data('initial-value') || '').trim();
                    var currentVal = String($(this).val() || '').trim();
                    var $btn = $(this).closest('form').find('.ext-worker-qty-btn');
                    if (currentVal !== '' && currentVal !== initialVal) {
                        $btn.removeClass('d-none');
                    } else {
                        $btn.addClass('d-none');
                    }
                });

                // ── Add Shift Workflow Interactive Handlers ───────────────────────
                // 1. Toggle shift picker inline panel
                $(document).on('click', '.btn-add-shift-toggle', function(e) {
                    e.preventDefault();
                    var $box = $(this).closest('.section-shifts-box');
                    if (!$box.length) return;
                    var $panel = $box.find('.shift-picker-panel');
                    if ($panel.length) {
                        var isHidden = $panel.hasClass('d-none');
                        if (isHidden) {
                            $panel.removeClass('d-none');
                            var $select = $panel.find('.shift-picker-select');
                            var $modal = $(this).closest('.modal');
                            initModalSelect2($select, $modal);
                            setTimeout(function() {
                                if ($select.data('select2')) {
                                    $select.select2('open');
                                } else {
                                    $select.focus();
                                }
                            }, 50);
                        } else {
                            $panel.addClass('d-none');
                            var $select = $panel.find('.shift-picker-select');
                            if ($select.data('select2')) {
                                $select.select2('close');
                            }
                        }
                    }
                });

                // 2. Cancel shift picker panel
                $(document).on('click', '.btn-add-shift-cancel', function(e) {
                    e.preventDefault();
                    var $panel = $(this).closest('.shift-picker-panel');
                    if ($panel.length) {
                        $panel.addClass('d-none');
                        var $select = $panel.find('.shift-picker-select');
                        if ($select.length) {
                            $select.val('').trigger('change.select2');
                            if ($select.data('select2')) {
                                $select.select2('close');
                            }
                        }
                        $panel.find('.shift-picker-warning').remove();
                    }
                });

                // 3. Confirm adding shift from selector to assigned list
                $(document).on('click', '.btn-add-shift-confirm', function(e) {
                    e.preventDefault();
                    var $box = $(this).closest('.section-shifts-box');
                    if (!$box.length) return;

                    var $panel = $box.find('.shift-picker-panel');
                    var $select = $panel.find('.shift-picker-select');
                    var selectedVal = $select.val();

                    if (!selectedVal) {
                        if (typeof window.showAppToast === 'function') {
                            window.showAppToast('warning', 'Please select a shift from the list.');
                        } else {
                            alert('Please select a shift from the list.');
                        }
                        return;
                    }

                    var $selectedOpt = $select.find('option:selected');
                    var shiftId = selectedVal;
                    var shiftName = $selectedOpt.data('name') || $selectedOpt.text();
                    var shiftCode = $selectedOpt.data('code') || '';
                    var shiftTime = $selectedOpt.data('time') || '';

                    var $list = $box.find('.assigned-shifts-list');
                    var $emptyState = $box.find('.assigned-shifts-empty');
                    var $countBadge = $box.find('.shifts-count-badge');

                    // Check duplicate
                    if ($list.find('[data-shift-id="' + shiftId + '"]').length) {
                        if (typeof window.showAppToast === 'function') {
                            window.showAppToast('warning', 'This shift is already assigned to this Section.');
                        } else {
                            alert('This shift is already assigned to this Section.');
                        }
                        return;
                    }

                    // Build row
                    var codeBadge = shiftCode ? '<span class="fw-bold font-monospace text-dark fs-12">[' + $('<div>').text(shiftCode).html() + ']</span> ' : '';
                    var nameSpan = '<span class="text-dark fs-12">' + $('<div>').text(shiftName).html() + '</span> ';
                    var timeSpan = shiftTime ? '<span class="text-muted fs-11">(' + $('<div>').text(shiftTime).html() + ')</span>' : '';

                    var rowHtml = '<div class="p-2 border rounded bg-light d-flex justify-content-between align-items-center assigned-shift-row" data-shift-id="' + shiftId + '">' +
                        '<input type="hidden" name="shifts[]" value="' + shiftId + '">' +
                        '<div class="d-flex align-items-center gap-2">' +
                            '<i class="feather-clock text-primary fs-12"></i>' +
                            codeBadge +
                            nameSpan +
                            timeSpan +
                        '</div>' +
                        '<button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2 py-0.5 btn-remove-shift" title="Remove shift">' +
                            '<i class="feather-trash-2 me-0.5 fs-10"></i>Remove' +
                        '</button>' +
                    '</div>';

                    $list.append(rowHtml);

                    if ($emptyState.length) {
                        $emptyState.addClass('d-none');
                    }

                    if ($countBadge.length) {
                        var count = $list.find('.assigned-shift-row').length;
                        $countBadge.text(count + ' Shifts');
                    }

                    // Reset select and close panel
                    $select.val('').trigger('change.select2');
                    if ($select.data('select2')) {
                        $select.select2('close');
                    }
                    $panel.addClass('d-none');
                    $panel.find('.shift-picker-warning').remove();
                });

                // 4. Remove shift from assigned list
                $(document).on('click', '.btn-remove-shift', function(e) {
                    e.preventDefault();
                    var $row = $(this).closest('.assigned-shift-row');
                    var $box = $(this).closest('.section-shifts-box');
                    $row.remove();
                    if ($box.length) {
                        var $list = $box.find('.assigned-shifts-list');
                        var $emptyState = $box.find('.assigned-shifts-empty');
                        var $countBadge = $box.find('.shifts-count-badge');
                        var count = $list.find('.assigned-shift-row').length;
                        if ($countBadge.length) {
                            $countBadge.text(count + ' Shifts');
                        }
                        if (count === 0 && $emptyState.length) {
                            $emptyState.removeClass('d-none');
                        }
                    }
                });

                // ══════════════════════════════════════════════════════════════
                // SECTION PERSONNEL MODAL – AJAX ENGINE
                // ══════════════════════════════════════════════════════════════

                /**
                 * Refresh the section personnel modal content without a page reload.
                 * Fetches current page HTML, extracts updated tab panes and counters,
                 * then swaps them in-place, keeping the modal open and active.
                 *
                 * @param {number|string} secId       – section ID
                 * @param {string}        activeTabId – 'internal' | 'external'
                 * @param {function}      [done]      – optional callback after refresh
                 */
                function refreshSectionPersonnelModal(secId, activeTabId, done) {
                    $.ajax({
                        url: window.location.href,
                        method: 'GET',
                        success: function(html) {
                            var $newDoc = $(html);

                            // --- Swap Internal tab pane ---
                            var internalPaneId = '#sec-internal-pane-' + secId;
                            var $newInternal = $newDoc.find(internalPaneId);
                            if ($newInternal.length) {
                                var $currentInternal = $(internalPaneId);
                                $currentInternal.html($newInternal.html());
                            }

                            // --- Swap External tab pane ---
                            var externalPaneId = '#sec-external-pane-' + secId;
                            var $newExternal = $newDoc.find(externalPaneId);
                            if ($newExternal.length) {
                                var $currentExternal = $(externalPaneId);
                                $currentExternal.html($newExternal.html());
                            }

                            // --- Update modal nav-pill badges ---
                            var $modal = $('#sectionEmployeesModal_' + secId);

                            var $newInternalTab = $newDoc.find('#sec-internal-tab-' + secId);
                            if ($newInternalTab.length) {
                                var $currentInternalTab = $modal.find('#sec-internal-tab-' + secId);
                                // Only swap the badge text inside the tab pill
                                var newInternalBadgeTxt = $newInternalTab.find('.badge').text().trim();
                                $currentInternalTab.find('.badge').text(newInternalBadgeTxt);
                            }

                            var $newExternalTab = $newDoc.find('#sec-external-tab-' + secId);
                            if ($newExternalTab.length) {
                                var $currentExternalTab = $modal.find('#sec-external-tab-' + secId);
                                var newExternalBadgeTxt = $newExternalTab.find('.badge').text().trim();
                                $currentExternalTab.find('.badge').text(newExternalBadgeTxt);
                            }

                            // --- Update Total Personnel badge in modal sub-header ---
                            var $newModalHeader = $newDoc.find('#sectionEmployeesModal_' + secId + ' .modal-body').prev('.d-flex');
                            if ($newModalHeader.length) {
                                var $currentModalHeader = $modal.find('.modal-body').prev('.d-flex');
                                var $newTotalBadge = $newModalHeader.find('.bg-soft-primary.text-primary');
                                var $currentTotalBadge = $currentModalHeader.find('.bg-soft-primary.text-primary');
                                if ($newTotalBadge.length && $currentTotalBadge.length) {
                                    $currentTotalBadge.html($newTotalBadge.html());
                                }
                            }

                            // --- Update Section Personnel count badge in main table ---
                            var $newPersonnelBtn = $newDoc.find('#secPersonnelBtn_' + secId);
                            if ($newPersonnelBtn.length) {
                                var newCount = $newPersonnelBtn.find('.sec-personnel-count-badge').text().trim();
                                $('#secPersonnelBtn_' + secId).find('.sec-personnel-count-badge').text(newCount);
                            }

                            // --- Re-initialize Select2 on newly injected selects ---
                            var $activePane = $modal.find(activeTabId === 'external' ? externalPaneId : internalPaneId);
                            $activePane.find('[data-select2-selector]').each(function() {
                                initModalSelect2($(this), $modal);
                            });

                            if (typeof done === 'function') done();
                        },
                        error: function() {
                            // silent fail – content will reflect on next natural page load
                        }
                    });
                }

                /**
                 * Determine which tab is currently active in a section modal.
                 * Returns 'external' or 'internal'.
                 */
                function getActiveSecTab(secId) {
                    var $extTab = $('#sec-external-tab-' + secId);
                    return ($extTab.length && $extTab.hasClass('active')) ? 'external' : 'internal';
                }

                /**
                 * Show a loading spinner / disable a button while AJAX is in flight.
                 */
                function setAjaxBusy($btn, busy) {
                    if (!$btn || !$btn.length) return;
                    if (busy) {
                        $btn.prop('disabled', true).data('_original_html', $btn.html());
                        $btn.html('<span class="spinner-border spinner-border-sm" role="status"></span>');
                    } else {
                        $btn.prop('disabled', false);
                        var orig = $btn.data('_original_html');
                        if (orig) $btn.html(orig);
                    }
                }

                // ── Intercept ALL form submissions inside a section-personnel-modal ──
                $(document).on('submit', '.section-personnel-modal form', function(e) {
                    e.preventDefault();

                    var $form    = $(this);
                    var $modal   = $form.closest('.section-personnel-modal');
                    var secId    = $modal.attr('id').replace('sectionEmployeesModal_', '');
                    var action   = $form.attr('action');
                    var method   = ($form.find('input[name="_method"]').val() || $form.attr('method') || 'POST').toUpperCase();
                    var formData = $form.serialize();

                    // Locate a sensible submit/action button to show loading on
                    var $submitBtn = $form.find('[type="submit"]');
                    setAjaxBusy($submitBtn, true);

                    var activeTab = getActiveSecTab(secId);

                    // Confirm dialogs (delete / deallocate) via data-confirm attribute
                    var confirmMsg = $form.data('confirm');
                    if (confirmMsg) {
                        if (!confirm(confirmMsg)) {
                            setAjaxBusy($submitBtn, false);
                            return;
                        }
                    }

                    $.ajax({
                        url: action,
                        type: 'POST',             // always POST – _method spoofs PATCH/DELETE
                        data: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        success: function(resp) {
                            setAjaxBusy($submitBtn, false);
                            if (resp && resp.message) {
                                var toastType = resp.success !== false ? 'success' : 'error';
                                if (typeof window.showAppToast === 'function') {
                                    window.showAppToast(toastType, resp.message);
                                }
                            }
                            // Refresh modal content from the server
                            refreshSectionPersonnelModal(secId, activeTab);
                        },
                        error: function(xhr) {
                            setAjaxBusy($submitBtn, false);
                            var errMsg = 'An error occurred. Please try again.';
                            try {
                                var json = JSON.parse(xhr.responseText);
                                if (json && json.message) errMsg = json.message;
                            } catch(ex) {}
                            if (typeof window.showAppToast === 'function') {
                                window.showAppToast('error', errMsg);
                            }
                        }
                    });
                });

                // ══════════════════════════════════════════════════════════════
                // WORK-CENTER EMPLOYEES MODAL – AJAX ENGINE
                // ══════════════════════════════════════════════════════════════

                /**
                 * Refresh the work-center employees modal content without a page reload.
                 * Fetches current page HTML, extracts the updated employees list wrapper,
                 * and swaps it in-place keeping the modal open.
                 *
                 * @param {function} [done] – optional callback after refresh
                 */
                function refreshWcEmployeesModal(done) {
                    $.ajax({
                        url: window.location.href,
                        method: 'GET',
                        success: function(html) {
                            var $newDoc = $(html);

                            // --- Swap the entire employees list section ---
                            var $newWrapper = $newDoc.find('#wc-employees-list-wrapper');
                            if ($newWrapper.length) {
                                $('#wc-employees-list-wrapper').html($newWrapper.html());
                            }

                            // --- Re-initialize Select2 on freshly injected selects ---
                            var $modal = $('#workCenterEmployeesModal');
                            $modal.find('[data-select2-selector]').each(function() {
                                initModalSelect2($(this), $modal);
                            });

                            if (typeof done === 'function') done();
                        },
                        error: function() {
                            // silent fail – content reflects on next natural page load
                        }
                    });
                }

                // ── Intercept ALL form submissions inside .wc-employees-modal ──
                $(document).on('submit', '.wc-employees-modal form', function(e) {
                    e.preventDefault();

                    var $form    = $(this);
                    var action   = $form.attr('action');
                    var formData = $form.serialize();

                    // Submit button spinner
                    var $submitBtn = $form.find('[type="submit"]');
                    if ($submitBtn.length) {
                        $submitBtn.prop('disabled', true).data('_orig_html', $submitBtn.html());
                        $submitBtn.html('<span class="spinner-border spinner-border-sm" role="status"></span>');
                    }

                    // Confirm guard (data-confirm attribute)
                    var confirmMsg = $form.data('confirm');
                    if (confirmMsg) {
                        if (!confirm(confirmMsg)) {
                            $submitBtn.prop('disabled', false).html($submitBtn.data('_orig_html'));
                            return;
                        }
                    }

                    $.ajax({
                        url: action,
                        type: 'POST',
                        data: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        success: function(resp) {
                            if ($submitBtn.length) {
                                $submitBtn.prop('disabled', false).html($submitBtn.data('_orig_html'));
                            }
                            if (resp && resp.message) {
                                var toastType = resp.success !== false ? 'success' : 'error';
                                if (typeof window.showAppToast === 'function') {
                                    window.showAppToast(toastType, resp.message);
                                }
                            }
                            refreshWcEmployeesModal();
                        },
                        error: function(xhr) {
                            if ($submitBtn.length) {
                                $submitBtn.prop('disabled', false).html($submitBtn.data('_orig_html'));
                            }
                            var errMsg = 'An error occurred. Please try again.';
                            try {
                                var json = JSON.parse(xhr.responseText);
                                if (json && json.message) errMsg = json.message;
                            } catch(ex) {}
                            if (typeof window.showAppToast === 'function') {
                                window.showAppToast('error', errMsg);
                            }
                        }
                    });
                });

                // Countdown Timer Ticker
                var countdownSeconds = {{ $isCountdownActive ? (int) $countdownRemainingSeconds : 0 }};
                if (countdownSeconds > 0) {
                    var $timerEl = $('#countdown-timer-display');
                    var timerInterval = setInterval(function() {
                        countdownSeconds--;
                        if (countdownSeconds <= 0) {
                            clearInterval(timerInterval);
                            if ($timerEl.length) $timerEl.text('00:00');
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        } else {
                            var m = Math.floor(countdownSeconds / 60);
                            var s = countdownSeconds % 60;
                            var formatted = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                            if ($timerEl.length) $timerEl.text(formatted);
                        }
                    }, 1000);
                }

            });
        </script>
    @endpush

    @if($errors->any() && old('_modal'))
        @push('scripts')
            <script>
                $(function() {
                    var modalId = "{{ old('_modal') }}";
                    var modalEl = document.getElementById(modalId);
                    if (modalEl && typeof bootstrap !== 'undefined') {
                        var modal = new bootstrap.Modal(modalEl);
                        modal.show();
                    }
                });
            </script>
        @endpush
    @endif
@endsection
