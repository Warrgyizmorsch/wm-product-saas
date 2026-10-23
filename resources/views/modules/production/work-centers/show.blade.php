@extends('layouts.duralux')

@section('title', __('production.work_center_details') . ' | SaaS ERP')
@section('page-title', __('production.work_center_details'))
@section('breadcrumb', $workCenter->code)

@section('page-actions')
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#workCenterCalculationModal">
            <i class="feather-pie-chart me-1"></i>{{ __('production.calculation_breakdown') }}
        </button>
        @can('update', $workCenter)
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
        .section-machines-popover {
            max-width: 320px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 8px;
        }
        .section-machines-popover .popover-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 8px 12px;
        }
        .section-machines-popover .popover-body {
            padding: 8px 12px;
            max-height: 240px;
            overflow-y: auto;
        }
        .section-machines-badge {
            cursor: pointer;
            transition: all 0.15s ease-in-out;
        }
        .section-machines-badge:hover {
            filter: brightness(0.95);
            transform: translateY(-1px);
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
            z-index: 1065 !important;
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

        <!-- Profile / Metrics Layout -->
        <div class="row g-4 mb-4 pb-4 border-bottom">
            <!-- Left Info Panel -->
            <div class="col-md-4 border-end">
                <div class="text-center py-3">
                    <div class="avatar-text avatar-xl bg-soft-primary text-primary mx-auto mb-3 rounded-circle" style="width: 80px; height: 80px; font-size: 32px; display: flex; align-items: center; justify-content: center;">
                        <i class="feather-box"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">{{ $workCenter->name }}</h4>
                    <span class="fs-13 fw-semibold text-muted text-uppercase">{{ $workCenter->code }}</span>
                    <div class="mt-3">
                        @if ($workCenter->isActive())
                            <span class="badge bg-soft-success text-success px-3 py-1.5 rounded-pill">{{ __('production.active_operating') }}</span>
                        @else
                            <span class="badge bg-soft-danger text-danger px-3 py-1.5 rounded-pill">{{ __('production.inactive_suspended') }}</span>
                        @endif
                    </div>
                </div>

                <div class="d-flex flex-column gap-3 mt-4 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.work_center_type') }}:</span>
                        <span class="fw-bold text-dark text-uppercase fs-12">
                            {{ config('production.work_center_types')[$workCenter->work_center_type] ?? $workCenter->work_center_type ?? 'N/A' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.physical_location') }}:</span>
                        <span class="fw-semibold text-dark">{{ $workCenter->location ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.created_date') }}:</span>
                        <span class="fw-semibold text-dark">{{ $workCenter->created_at->format('Y-m-d') }}</span>
                    </div>
                    @php
                        $wcDirectEmps = $workCenter->employeeAssignments ? $workCenter->employeeAssignments->where('active', true) : collect();
                        $secEmps = $workCenter->sections ? $workCenter->sections->flatMap(fn($sec) => $sec->employeeAssignments ? $sec->employeeAssignments->where('active', true) : collect()) : collect();
                        $allActiveEmpAssignments = $wcDirectEmps->concat($secEmps)->unique('employee_id');
                        $wcAssignedEmpCount = $allActiveEmpAssignments->count();

                        if ($wcAssignedEmpCount > 0) {
                            $wcEmpListHtml = '<ul class=\'list-unstyled mb-0 fs-12\'>';
                            foreach ($allActiveEmpAssignments as $assign) {
                                $emp = $assign->employee;
                                $empCode = $emp?->employee_id ?? ('EMP-' . ($emp?->id ?? $assign->employee_id));
                                $empName = $emp?->full_name ?? $emp?->name ?? 'Employee #' . $assign->employee_id;
                                $secTag = $assign->section ? '<span class=\'text-muted fs-10 ms-1\'>(' . e($assign->section->name) . ')</span>' : '';
                                $wcEmpListHtml .= '<li class=\'py-1 border-bottom border-light d-flex align-items-center gap-1\'>'
                                    . '<i class=\'feather-user text-primary fs-11\'></i>'
                                    . '<span class=\'fw-bold font-monospace text-dark\'>[' . e($empCode) . ']</span> '
                                    . '<span class=\'text-dark\'>' . e($empName) . '</span>'
                                    . $secTag
                                    . '</li>';
                            }
                            $wcEmpListHtml .= '</ul>';
                        } else {
                            $wcEmpListHtml = '<div class=\'text-muted fs-11 fst-italic py-1 text-center\'>'
                                . '<i class=\'feather-info text-muted me-1\'></i>' . e(__('production.no_employees_assigned'))
                                . '</div>';
                        }
                    @endphp
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.assigned_employees') }}:</span>
                        <span class="badge bg-soft-secondary text-secondary rounded-pill px-2.5 py-1 fs-12"
                              role="button"
                              tabindex="0"
                              data-bs-toggle="popover"
                              data-bs-trigger="hover focus"
                              data-bs-placement="top"
                              data-bs-html="true"
                              data-bs-custom-class="section-machines-popover"
                              data-bs-title="<div class='fw-bold fs-12 text-dark d-flex align-items-center gap-1'><i class='feather-users text-primary fs-12'></i>{{ __('production.assigned_employees') }} ({{ $wcAssignedEmpCount }})</div>"
                              data-bs-content="{{ $wcEmpListHtml }}">
                            <i class="feather-users me-1 fs-11"></i>{{ $wcAssignedEmpCount }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Capacity Panel -->
            <div class="col-md-4 border-end">
                <h5 class="fw-bold text-dark mb-3">{{ __('production.wc_metrics') }}</h5>
                <div class="d-flex flex-column gap-4 py-2 px-2">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted fs-11 text-uppercase fw-semibold">{{ __('production.capacity_per_hour') }}</span>
                            <button type="button" class="btn btn-xs btn-outline-primary border-0 p-0"
                                    data-bs-toggle="modal" data-bs-target="#workCenterCalculationModal"
                                    title="{{ __('production.view_calculation_breakdown') }}">
                                <i class="feather-info fs-13 me-1"></i>{{ __('production.calculation_breakdown') }}
                            </button>
                        </div>
                        <span class="fs-22 fw-bold text-dark font-monospace">
                            0
                        </span>
                    </div>
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.operational_efficiency') }}</span>
                        <span class="fs-22 fw-bold text-dark">0%</span>
                        <div class="progress progress-xs mt-2" style="height: 6px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.effective_capacity') }}</span>
                        <span class="fs-18 fw-bold text-success">
                            0
                        </span>
                    </div>
                    <div class="border-top pt-3">
                        <span class="text-muted fs-11 text-uppercase d-block mb-1"><i class="feather-calendar me-1"></i>Production Calendar</span>
                        <span class="fs-13 fw-semibold text-dark">
                            @if ($workCenter->calendar)
                                <span class="badge bg-soft-primary text-primary fs-11"><i class="feather-calendar me-1"></i>{{ $workCenter->calendar->name }}</span>
                            @else
                                <span class="text-muted fs-12 fst-italic">Default Factory Calendar</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            <!-- Operating Cost Structure Panel -->
            <div class="col-md-4">
                <h5 class="fw-bold text-dark mb-3">{{ __('production.wc_cost_structure') }}</h5>
                @php
                    $laborCost = 0.0;
                    $overheadCost = (float) ($workCenter->overhead_cost ?? 0);
                    $totalStationCost = $laborCost + $overheadCost;
                @endphp
                <div class="d-flex flex-column gap-3 py-1 px-1">
                    <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                        <div>
                            <div class="d-flex align-items-center gap-1">
                                <span class="text-muted fs-11 text-uppercase fw-semibold">{{ __('production.wc_labor_cost_per_hour') }}</span>
                                <button type="button" class="btn btn-xs btn-outline-primary border-0 p-0"
                                        data-bs-toggle="modal" data-bs-target="#workCenterCalculationModal"
                                        title="{{ __('production.view_calculation_breakdown') }}">
                                    <i class="feather-info fs-12"></i>
                                </button>
                            </div>
                            <small class="text-muted fs-11">Scheduled Section shifts</small>
                        </div>
                        <span class="fs-18 fw-bold text-primary font-monospace">{{ format_currency($laborCost) }} <span class="fs-11 fw-normal text-muted">/ {{ __('production.hour') }}</span></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                        <div>
                            <span class="text-muted fs-11 text-uppercase d-block fw-semibold">{{ __('production.overhead_cost_rate') }}</span>
                            <small class="text-muted fs-11">{{ __('production.overhead_cost_rate_help') }}</small>
                        </div>
                        <span class="fs-18 fw-bold text-dark font-monospace">{{ format_currency($overheadCost) }} <span class="fs-11 fw-normal text-muted">/ {{ __('production.hour') }}</span></span>
                    </div>

                    <div class="p-2.5 rounded bg-light border d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-dark fw-bold fs-12 text-uppercase d-block">Total Station Cost</span>
                            <small class="text-muted fs-11">Combined Labor + Overhead cost</small>
                        </div>
                        <span class="fs-20 fw-bolder text-primary font-monospace">{{ format_currency($totalStationCost) }} <span class="fs-11 fw-normal text-muted">/ {{ __('production.hour') }}</span></span>
                    </div>
                    
                    <div class="border-top pt-2 mt-1">
                        <h6 class="fw-bold text-dark mb-1 fs-12">{{ __('production.wc_cost_guide') }}</h6>
                        <ul class="fs-11 text-muted ps-3 mb-0">
                            <li class="mb-1">{{ __('production.wc_cost_guide_text_1', ['cost' => format_currency($laborCost)]) }}</li>
                            <li class="mb-1">Overhead Cost = <span class="fw-semibold text-dark">{{ format_currency($overheadCost) }}</span> / hr</li>
                            <li>{{ __('production.wc_cost_guide_text_2') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Description Section -->
        @if ($workCenter->description)
            <div class="mb-4 pb-4 border-bottom">
                <h5 class="fw-bold text-dark mb-2">{{ __('production.purpose_notes') }}</h5>
                <p class="mb-0 fs-13 text-muted" style="white-space: pre-line;">{{ $workCenter->description }}</p>
            </div>
        @endif

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
                            <th style="width: 12%">{{ __('production.section_code') }}</th>
                            <th style="width: 17%">{{ __('production.section_name') }}</th>
                            <th style="width: 25%">{{ __('production.section_shifts') }}</th>
                            <th style="width: 7%">{{ __('production.machines_count') }}</th>
                            <th style="width: 13%">{{ __('production.capacity_per_hour') }}</th>
                            <th style="width: 16%">{{ __('production.labor_cost_per_hour') }}</th>
                            <th style="width: 6%">{{ __('production.status') }}</th>
                            <th style="width: 4%" class="text-end">{{ __('production.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workCenter->sections as $section)
                            @php
                                $calc = $section->getCalculationDetails();
                                $capPerHour = (float) ($calc['capacity']['capacity_per_hour'] ?? 0);
                                $capUnit = $calc['capacity']['capacity_unit'] ?? __('production.units_per_hour');
                                $laborCost = (float) ($calc['labor_cost']['labor_cost_per_hour'] ?? 0);
                                $hasOverlaps = (bool) ($calc['labor_cost']['has_overlapping_shifts'] ?? false);
                            @endphp
                            <tr>
                                <td class="align-middle">
                                    <span class="font-monospace fw-bold text-dark">{{ $section->code }}</span>
                                </td>
                                <td class="align-middle">
                                    <div class="fw-semibold text-dark d-flex align-items-center flex-wrap gap-1">
                                        <span>{{ $section->name }}</span>
                                        @if($section->supports_manual_production)
                                            <span class="badge bg-soft-warning text-warning fs-10 px-1.5 py-0.5 rounded-pill" title="{{ __('production.manual_capacity_per_hour') }}: {{ number_format((float) $section->manual_capacity_per_hour, 2) }} {{ __('production.units_per_hour') }}">
                                                <i class="feather-user me-0.5"></i>{{ __('production.manual_production') }} ({{ number_format((float) $section->manual_capacity_per_hour, 2) }} {{ __('production.units_per_hour') }})
                                            </span>
                                        @endif
                                    </div>
                                    @if($section->description)
                                        <small class="text-muted fs-11 text-truncate d-block" style="max-width: 250px;">{{ $section->description }}</small>
                                    @endif
                                    <div class="mt-1 d-flex align-items-center gap-1.5 flex-wrap">
                                        @php
                                            $activeSecEmpCount = $section->employeeAssignments ? $section->employeeAssignments->where('active', true)->count() : 0;
                                            $secExtAllocs = $section->externalWorkerAllocations ? $section->externalWorkerAllocations->where('active', true) : collect();
                                            $activeSecExtCount = (int) $secExtAllocs->sum('number_of_external_workers');
                                            $totalSecPersonnel = $activeSecEmpCount + $activeSecExtCount;
                                        @endphp
                                        <button type="button" 
                                                class="btn btn-xs btn-outline-info rounded-pill px-2.5 py-0.5 fw-semibold d-inline-flex align-items-center gap-1 sec-personnel-trigger"
                                                id="secPersonnelBtn_{{ $section->id }}"
                                                data-section-id="{{ $section->id }}"
                                                data-bs-toggle="modal"
                                                data-bs-target="#sectionEmployeesModal_{{ $section->id }}"
                                                data-target-tab="internal"
                                                title="Manage Section Personnel (Internal Employees & External Workers)">
                                            <i class="feather-users fs-10"></i>
                                            <span>Section Personnel</span>
                                            <span class="badge bg-soft-info text-info rounded-pill px-1.5 py-0 fs-10 ms-0.5 font-monospace sec-personnel-count-badge" title="{{ $activeSecEmpCount }} Internal, {{ $activeSecExtCount }} External">{{ $totalSecPersonnel }}</span>
                                        </button>

                                        <button type="button" 
                                                class="btn btn-xs {{ $section->supports_manual_production ? 'btn-soft-warning text-warning border border-warning-subtle' : 'btn-outline-secondary' }} rounded-pill px-2 py-0.5 fw-semibold d-inline-flex align-items-center gap-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#sectionManualProductionModal_{{ $section->id }}"
                                                title="Configure Manual Production for this Section">
                                            <i class="feather-tool fs-10"></i>
                                            <span>Manual Production</span>
                                            @if($section->supports_manual_production)
                                                <span class="badge bg-warning text-white rounded-pill px-1.5 py-0 fs-10 ms-0.5 font-monospace">{{ number_format((float) $section->manual_capacity_per_hour, 1) }}/hr</span>
                                            @endif
                                        </button>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    @forelse ($section->shifts as $shift)
                                        @php
                                            $extAllocations = $section->externalWorkerAllocations ? $section->externalWorkerAllocations->where('shift_id', $shift->id) : collect();
                                            $activeExtCount = (int) $extAllocations->where('active', true)->sum('number_of_external_workers');
                                        @endphp
                                        <div class="d-inline-flex flex-column gap-1 p-2 border rounded bg-white mb-1.5 me-1.5 fs-11 shadow-xs">
                                            <div class="d-flex align-items-center justify-content-between gap-2">
                                                <span class="fw-semibold text-dark">{{ $shift->name }}</span>
                                                @if(!str_contains($shift->name, substr($shift->start_time, 0, 5)))
                                                    <span class="text-muted font-monospace fs-10">({{ substr($shift->start_time, 0, 5) }}–{{ substr($shift->end_time, 0, 5) }})</span>
                                                @endif
                                            </div>
                                            <div class="d-flex align-items-center gap-1.5 flex-wrap mt-0.5">
                                                <button type="button" 
                                                        class="btn btn-xs {{ $activeExtCount > 0 ? 'btn-soft-primary text-primary' : 'btn-outline-secondary' }} rounded-pill px-2.5 py-0.5 fw-semibold d-inline-flex align-items-center gap-1" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#sectionEmployeesModal_{{ $section->id }}"
                                                        data-target-tab="external"
                                                        data-target-shift="{{ $shift->id }}"
                                                        title="Allocate External Workers for this Section & Shift">
                                                    <i class="feather-users fs-10"></i>
                                                    <span>External Workers</span>
                                                    @if($activeExtCount > 0)
                                                        <span class="badge bg-primary text-white rounded-pill px-1.5 py-0 fs-10 ms-0.5" title="{{ $activeExtCount }} External Workers">{{ $activeExtCount }}</span>
                                                    @endif
                                                </button>
                                            </div>
                                        </div>
                                    @empty
                                        <span class="text-danger fs-11"><i class="feather-alert-triangle me-1"></i>No shifts</span>
                                    @endforelse
                                </td>
                                <td class="align-middle">
                                    @php
                                        $machineCount = $section->machines->count();
                                        if ($machineCount > 0) {
                                            $machineListHtml = '<ul class=\'list-unstyled mb-0 text-start fs-12\'>';
                                            foreach ($section->machines as $sm) {
                                                $machineListHtml .= '<li class=\'py-1 border-bottom border-light d-flex align-items-center gap-1\'>'
                                                    . '<i class=\'feather-cpu text-primary fs-11\'></i>'
                                                    . '<span class=\'fw-bold font-monospace text-dark\'>[' . e($sm->code) . ']</span> '
                                                    . '<span class=\'text-dark\'>' . e($sm->name) . '</span>'
                                                    . '</li>';
                                            }
                                            $machineListHtml .= '</ul>';
                                        } else {
                                            $machineListHtml = '<div class=\'text-muted fs-11 fst-italic py-1 text-center\'>'
                                                . '<i class=\'feather-info text-muted me-1\'></i>' . e(__('production.no_machines_assigned'))
                                                . '</div>';
                                        }
                                    @endphp
                                    <span class="badge bg-soft-secondary text-secondary rounded-pill px-2.5 py-1 fs-12 section-machines-badge"
                                          role="button"
                                          tabindex="0"
                                          data-bs-toggle="popover"
                                          data-bs-trigger="hover focus"
                                          data-bs-placement="top"
                                          data-bs-html="true"
                                          data-bs-custom-class="section-machines-popover"
                                          data-bs-title="<div class='fw-bold fs-12 text-dark d-flex align-items-center gap-1'><i class='feather-cpu text-primary fs-12'></i>{{ __('production.assigned_machines') }} ({{ $machineCount }})</div>"
                                          data-bs-content="{{ $machineListHtml }}">
                                        <i class="feather-cpu me-1 fs-11"></i>{{ $machineCount }}
                                    </span>
                                </td>
                                <td class="align-middle">
                                    <div class="d-flex align-items-center gap-1.5 flex-nowrap">
                                        <span class="fw-bold font-monospace text-dark fs-12">
                                            {{ number_format($capPerHour, 2) }}
                                        </span>
                                        <small class="text-muted fs-10">{{ $capUnit }}</small>
                                        <button type="button" class="btn btn-xs btn-outline-light text-primary border-0 p-0 ms-0.5" 
                                                data-bs-toggle="modal" data-bs-target="#sectionCalculationModal_{{ $section->id }}"
                                                title="{{ __('production.view_calculation_breakdown') }}">
                                            <i class="feather-info fs-12"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <div class="d-flex flex-column gap-1">
                                        <div class="d-flex align-items-center gap-1.5 flex-nowrap">
                                            <span class="fw-bold font-monospace text-dark fs-12">
                                                {{ format_currency($laborCost) }}
                                            </span>
                                            <small class="text-muted fs-10">/ hr</small>
                                            <button type="button" class="btn btn-xs btn-outline-light text-primary border-0 p-0 ms-0.5" 
                                                    data-bs-toggle="modal" data-bs-target="#sectionCalculationModal_{{ $section->id }}"
                                                    title="{{ __('production.view_calculation_breakdown') }}">
                                                <i class="feather-info fs-12"></i>
                                            </button>
                                        </div>
                                        @if($hasOverlaps)
                                            <div>
                                                <span class="badge bg-soft-warning text-warning fs-10 px-1.5 py-0.5 rounded-pill" title="{{ __('production.overlapping_shifts') }}">
                                                    <i class="feather-layers me-0.5"></i>{{ __('production.overlapping_shifts') }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="align-middle">
                                    @if ($section->isActive())
                                        <span class="badge bg-soft-success text-success rounded-pill px-2 py-1">{{ __('production.active') }}</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger rounded-pill px-2 py-1">{{ __('production.inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end align-middle">
                                    <x-ui.action-dropdown>
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
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>{{ __('production.no_sections_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
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
        <div class="modal fade" id="sectionCalculationModal_{{ $section->id }}" tabindex="-1" aria-labelledby="sectionCalculationModalLabel_{{ $section->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header border-bottom py-3 bg-light">
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                <i class="feather-bar-chart-2"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="sectionCalculationModalLabel_{{ $section->id }}">
                                    {{ __('production.calculation_breakdown') }} &mdash; <span class="font-monospace text-primary">{{ $section->code }}</span> ({{ $section->name }})
                                </h5>
                                <small class="text-muted fs-11">{{ __('production.work_center') }}: {{ $workCenter->name }} [{{ $workCenter->code }}]</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                        @php
                            $calcData = $section->getCalculationDetails();
                            $capData = $calcData['capacity'] ?? [];
                            $laborData = $calcData['labor_cost'] ?? [];
                            $machinesBreakdown = $capData['machines'] ?? [];
                            $shiftsBreakdown = $laborData['shifts'] ?? [];
                            $windowsBreakdown = $laborData['timeline_windows'] ?? [];
                            $totalSchedCost = (float) ($laborData['total_scheduled_labor_cost'] ?? 0);
                            $uniqHours = (float) ($laborData['unique_operating_hours'] ?? 0);
                            $avgLaborCost = (float) ($laborData['labor_cost_per_hour'] ?? 0);
                            $totalCap = (float) ($capData['capacity_per_hour'] ?? 0);
                            $cUnit = $capData['capacity_unit'] ?? __('production.units_per_hour');
                            $hasOverlapping = (bool) ($laborData['has_overlapping_shifts'] ?? false);
                        @endphp

                        <!-- Top Summary Cards (KPIs) -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="p-3 rounded border bg-light h-100">
                                    <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                        <i class="feather-activity me-1 text-primary"></i>{{ __('production.capacity_per_hour') }}
                                    </span>
                                    <span class="fs-20 fw-bolder text-dark font-monospace d-block">
                                        {{ number_format($totalCap, 2) }}
                                    </span>
                                    <small class="text-muted fs-11">{{ $cUnit }}</small>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="p-3 rounded border bg-light h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-muted fs-11 text-uppercase fw-semibold">
                                            <i class="feather-dollar-sign me-1 text-success"></i>{{ __('production.labor_cost_per_hour') }}
                                        </span>
                                        @if($hasOverlapping)
                                            <span class="badge bg-soft-warning text-warning fs-10 px-1.5 py-0.5 rounded-pill">
                                                <i class="feather-layers me-0.5"></i>{{ __('production.overlapping_shifts') }}
                                            </span>
                                        @endif
                                    </div>
                                    <span class="fs-20 fw-bolder text-primary font-monospace d-block">
                                        {{ format_currency($avgLaborCost) }}
                                    </span>
                                    <small class="text-muted fs-11">per unique elapsed operating hour</small>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="p-3 rounded border bg-light h-100">
                                    <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                        <i class="feather-credit-card me-1 text-info"></i>{{ __('production.total_scheduled_labor_cost') }}
                                    </span>
                                    <span class="fs-20 fw-bolder text-dark font-monospace d-block">
                                        {{ format_currency($totalSchedCost) }}
                                    </span>
                                    <small class="text-muted fs-11">Sum of all window labor costs</small>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="p-3 rounded border bg-light h-100">
                                    <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                        <i class="feather-clock me-1 text-warning"></i>{{ __('production.unique_operating_hours') }}
                                    </span>
                                    <span class="fs-20 fw-bolder text-dark font-monospace d-block">
                                        {{ number_format($uniqHours, 2) }} h
                                    </span>
                                    <small class="text-muted fs-11">Union of active timeline hours</small>
                                </div>
                            </div>
                        </div>

                        <!-- Section 1: Capacity Per Hour Breakdown -->
                        <div class="card border shadow-none mb-4">
                            <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold text-dark mb-0 fs-13">
                                    <i class="feather-cpu text-primary me-1.5"></i>{{ __('production.capacity_breakdown') }}
                                </h6>
                                <span class="badge bg-soft-primary text-primary font-monospace fs-11">
                                    {{ number_format($totalCap, 2) }} {{ $cUnit }}
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 25%">Resource Type</th>
                                                <th style="width: 35%">Resource Name / Identifier</th>
                                                <th style="width: 20%" class="text-end">Base Capacity</th>
                                                <th style="width: 20%" class="text-end">Contribution / Hr</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($machinesBreakdown as $m)
                                                <tr>
                                                    <td class="align-middle">
                                                        <span class="badge bg-soft-secondary text-secondary rounded-pill">
                                                            <i class="feather-cpu me-1"></i>Machine
                                                        </span>
                                                    </td>
                                                    <td class="align-middle">
                                                        <span class="fw-bold font-monospace text-dark">[{{ $m['code'] }}]</span>
                                                        <span class="text-dark ms-1">{{ $m['name'] }}</span>
                                                    </td>
                                                    <td class="align-middle text-end font-monospace">
                                                        {{ number_format((float)$m['capacity'], 2) }} {{ $m['capacity_unit'] ?? $cUnit }}
                                                    </td>
                                                    <td class="align-middle text-end font-monospace fw-semibold text-dark">
                                                        +{{ number_format((float)$m['capacity'], 2) }} {{ $cUnit }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-muted text-center py-2 fst-italic">
                                                        {{ __('production.no_machines_assigned_zero_cap') }}
                                                    </td>
                                                </tr>
                                            @endforelse

                                            <!-- Manual Production Row -->
                                            <tr class="{{ !empty($capData['manual_production']['enabled']) ? 'table-warning bg-soft-warning' : 'table-light' }}">
                                                <td class="align-middle">
                                                    <span class="badge bg-soft-warning text-warning rounded-pill">
                                                        <i class="feather-user me-1"></i>Manual Production
                                                    </span>
                                                </td>
                                                <td class="align-middle">
                                                    @if(!empty($capData['manual_production']['enabled']))
                                                        <span class="fw-bold text-dark">{{ __('production.manual_production') }}</span>
                                                        <span class="badge bg-success text-white fs-10 px-1 py-0.2 ms-1">Enabled</span>
                                                    @else
                                                        <span class="text-muted">{{ __('production.manual_production') }}</span>
                                                        <span class="badge bg-secondary text-white fs-10 px-1 py-0.2 ms-1">Disabled</span>
                                                    @endif
                                                </td>
                                                <td class="align-middle text-end font-monospace text-muted">
                                                    @if(!empty($capData['manual_production']['enabled']))
                                                        {{ number_format((float)$capData['manual_production']['configured_capacity'], 2) }} {{ $cUnit }}
                                                    @else
                                                        &mdash;
                                                    @endif
                                                </td>
                                                <td class="align-middle text-end font-monospace fw-semibold {{ !empty($capData['manual_production']['enabled']) ? 'text-dark' : 'text-muted' }}">
                                                    @if(!empty($capData['manual_production']['enabled']))
                                                        +{{ number_format((float)$capData['manual_production']['effective_capacity'], 2) }} {{ $cUnit }}
                                                    @else
                                                        0.00 {{ $cUnit }}
                                                    @endif
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="table-light border-top">
                                            <tr class="fw-bold">
                                                <td colspan="3" class="text-end text-dark">{{ __('production.total_section_capacity') }}:</td>
                                                <td class="text-end font-monospace text-primary fs-13">
                                                    {{ number_format($totalCap, 2) }} {{ $cUnit }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Shift-Level Labor Configuration -->
                        <div class="card border shadow-none mb-4">
                            <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold text-dark mb-0 fs-13">
                                    <i class="feather-users text-primary me-1.5"></i>{{ __('production.shift_labor_configuration') }}
                                </h6>
                                <span class="text-muted fs-11">
                                    Shift Labor Cost / Hr = Workers Required &times; Hourly Rate Per Worker
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 25%">Shift Name</th>
                                                <th style="width: 20%">Operating Hours</th>
                                                <th style="width: 15%" class="text-center">{{ __('production.shift_worker_count') }}</th>
                                                <th style="width: 20%" class="text-end">{{ __('production.hourly_rate_per_worker') }}</th>
                                                <th style="width: 20%" class="text-end">{{ __('production.shift_labor_cost_per_hour') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($shiftsBreakdown as $sh)
                                                <tr>
                                                    <td class="align-middle">
                                                        <span class="fw-bold text-dark">{{ $sh['name'] }}</span>
                                                        @if(empty($sh['hourly_labor_rate']) || (float)$sh['hourly_labor_rate'] <= 0)
                                                            <span class="badge bg-soft-secondary text-secondary fs-10 px-1 py-0.2 ms-1" title="{{ __('production.missing_rate_note') }}">
                                                                Rate: ₹0
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="align-middle font-monospace text-muted">
                                                        {{ $sh['start_time'] }} &ndash; {{ $sh['end_time'] }}
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="badge bg-soft-info text-info rounded-pill px-2 py-0.5 fw-semibold font-monospace">
                                                            {{ $sh['workers_required'] }}
                                                        </span>
                                                    </td>
                                                    <td class="align-middle text-end font-monospace">
                                                        {{ format_currency((float)$sh['hourly_labor_rate']) }} / worker
                                                    </td>
                                                    <td class="align-middle text-end font-monospace fw-bold text-dark">
                                                        {{ format_currency((float)$sh['shift_labor_cost_per_hour']) }} / hr
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-muted text-center py-2 fst-italic">
                                                        {{ __('production.no_shifts_assigned') }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Timeline Windows Breakdown -->
                        <div class="card border shadow-none mb-3">
                            <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold text-dark mb-0 fs-13">
                                    <i class="feather-calendar text-primary me-1.5"></i>{{ __('production.timeline_windows') }}
                                </h6>
                                <span class="text-muted fs-11">
                                    Discretized by shift boundaries; paid breaks included; gaps excluded
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 22%">{{ __('production.window_range') }}</th>
                                                <th style="width: 12%">{{ __('production.window_duration') }}</th>
                                                <th style="width: 26%">{{ __('production.active_shifts') }}</th>
                                                <th style="width: 12%" class="text-center">{{ __('production.active_workers') }}</th>
                                                <th style="width: 14%" class="text-end">{{ __('production.combined_labor_rate') }}</th>
                                                <th style="width: 14%" class="text-end">{{ __('production.window_labor_cost') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($windowsBreakdown as $w)
                                                <tr class="{{ !empty($w['is_overlap']) ? 'table-warning bg-soft-warning' : '' }}">
                                                    <td class="align-middle font-monospace fw-semibold text-dark">
                                                        {{ $w['window'] }}
                                                        @if(!empty($w['is_overlap']))
                                                            <span class="badge bg-warning text-dark fs-10 px-1 py-0.2 ms-1 rounded-pill">
                                                                Overlap
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="align-middle font-monospace text-muted">
                                                        {{ number_format((float)$w['duration_hours'], 2) }} h
                                                    </td>
                                                    <td class="align-middle">
                                                        <div class="d-flex flex-wrap gap-1">
                                                            @foreach($w['active_shift_names'] as $asn)
                                                                <span class="badge bg-soft-primary text-primary fs-11 px-1.5 py-0.5">
                                                                    {{ $asn }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                    <td class="align-middle text-center font-monospace fw-semibold">
                                                        {{ $w['active_workers'] }}
                                                    </td>
                                                    <td class="align-middle text-end font-monospace">
                                                        {{ format_currency((float)$w['combined_hourly_cost']) }} / hr
                                                    </td>
                                                    <td class="align-middle text-end font-monospace fw-bold text-dark">
                                                        {{ format_currency((float)$w['window_cost']) }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-muted text-center py-2 fst-italic">
                                                        No timeline windows generated.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        <tfoot class="table-light border-top">
                                            <tr class="fw-bold">
                                                <td class="text-dark">{{ __('production.unique_operating_hours') }}:</td>
                                                <td class="font-monospace text-primary">{{ number_format($uniqHours, 2) }} h</td>
                                                <td colspan="3" class="text-end text-dark">{{ __('production.total_scheduled_labor_cost') }}:</td>
                                                <td class="text-end font-monospace text-primary fs-13">{{ format_currency($totalSchedCost) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Mathematical Summary Card -->
                        <div class="p-3 bg-light rounded border d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <div class="fw-bold text-dark fs-13 mb-1">
                                    <i class="feather-check-circle text-success me-1"></i>{{ __('production.calculation_formula_explanation') }}
                                </div>
                                <div class="text-muted fs-11 font-monospace">
                                    {{ format_currency($totalSchedCost) }} &divide; {{ number_format($uniqHours, 2) }} hrs = 
                                    <strong class="text-primary fs-12">{{ format_currency($avgLaborCost) }} / hr</strong>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="text-muted fs-11 d-block">Resulting Section Labor Cost:</span>
                                <span class="fs-18 fw-bolder text-primary font-monospace">{{ format_currency($avgLaborCost) }} <span class="fs-11 fw-normal text-muted">/ hr</span></span>
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
    @endforeach

    <!-- Work Center Calculation Breakdown Modal -->
    @php
        $wcCalc = $workCenter->getCalculationDetails();
        $wcCapCalc = $wcCalc['capacity'] ?? [];
        $wcLaborCalc = $wcCalc['labor_cost'] ?? [];
        $wcCapPerHour = (float) ($wcCapCalc['capacity_per_hour'] ?? 0);
        $wcTotalTheoCap = (float) ($wcCapCalc['total_theoretical_capacity'] ?? 0);
        $wcLaborCostPerHour = (float) ($wcLaborCalc['labor_cost_per_hour'] ?? 0);
        $wcTotalSchedLabor = (float) ($wcLaborCalc['total_scheduled_labor_cost'] ?? 0);
        $wcUniqOperatingHours = (float) ($wcCapCalc['unique_operating_hours'] ?? $wcLaborCalc['unique_operating_hours'] ?? 0);
        $wcCapWindows = $wcCapCalc['windows'] ?? [];
        $wcCapGaps = $wcCapCalc['gaps'] ?? [];
        $wcLaborAssignments = $wcLaborCalc['assignments'] ?? [];
        $wcLaborWindows = $wcLaborCalc['windows'] ?? [];
        $wcLaborGaps = $wcLaborCalc['gaps'] ?? [];
        $wcHasMissingRates = (bool) ($wcLaborCalc['has_missing_rates'] ?? false);
        $wcHasOverlapping = (bool) ($wcLaborCalc['has_overlapping_assignments'] ?? false);
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
                            <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="workCenterCalculationModalLabel">
                                {{ __('production.wc_calculation_breakdown') }} &mdash; <span class="font-monospace text-primary">{{ $workCenter->code }}</span> ({{ $workCenter->name }})
                            </h5>
                            <small class="text-muted fs-11">Auditable Time-Window Aggregations across all Sections</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Top Summary Cards (KPIs) -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="p-3 rounded border bg-light h-100">
                                <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                    <i class="feather-activity me-1 text-primary"></i>{{ __('production.capacity_per_hour') }}
                                </span>
                                <span class="fs-20 fw-bolder text-dark font-monospace d-block">
                                    {{ number_format($wcCapPerHour, 2) }}
                                </span>
                                <small class="text-muted fs-11">{{ __('production.units_hr') }}</small>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="p-3 rounded border bg-light h-100">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted fs-11 text-uppercase fw-semibold">
                                        <i class="feather-dollar-sign me-1 text-success"></i>{{ __('production.labor_cost_per_hour') }}
                                    </span>
                                    @if($wcHasOverlapping)
                                        <span class="badge bg-soft-warning text-warning fs-10 px-1.5 py-0.5 rounded-pill">
                                            <i class="feather-layers me-0.5"></i>{{ __('production.overlapping_assignments') }}
                                        </span>
                                    @endif
                                </div>
                                <span class="fs-20 fw-bolder text-primary font-monospace d-block">
                                    {{ format_currency($wcLaborCostPerHour) }}
                                </span>
                                <small class="text-muted fs-11">per unique elapsed operating hour</small>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="p-3 rounded border bg-light h-100">
                                <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                    <i class="feather-box me-1 text-info"></i>{{ __('production.total_theoretical_capacity') }}
                                </span>
                                <span class="fs-20 fw-bolder text-dark font-monospace d-block">
                                    {{ number_format($wcTotalTheoCap, 2) }}
                                </span>
                                <small class="text-muted fs-11">Sum of window theoretical capacities</small>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="p-3 rounded border bg-light h-100">
                                <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                    <i class="feather-clock me-1 text-warning"></i>{{ __('production.unique_operating_hours') }}
                                </span>
                                <span class="fs-20 fw-bolder text-dark font-monospace d-block">
                                    {{ number_format($wcUniqOperatingHours, 2) }} h
                                </span>
                                <small class="text-muted fs-11">Union of all section shift intervals (gaps excluded)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Tabs for Capacity vs Labor Cost -->
                    <ul class="nav nav-tabs nav-tabs-custom mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-semibold fs-13 d-flex align-items-center gap-1.5 py-2 px-3" 
                                    id="wc-capacity-tab" data-bs-toggle="tab" data-bs-target="#wc-capacity-pane" 
                                    type="button" role="tab" aria-controls="wc-capacity-pane" aria-selected="true">
                                <i class="feather-activity text-primary fs-14"></i>
                                {{ __('production.wc_capacity_breakdown') }}
                                <span class="badge bg-soft-primary text-primary ms-1 font-monospace">{{ number_format($wcCapPerHour, 2) }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold fs-13 d-flex align-items-center gap-1.5 py-2 px-3" 
                                    id="wc-labor-tab" data-bs-toggle="tab" data-bs-target="#wc-labor-pane" 
                                    type="button" role="tab" aria-controls="wc-labor-pane" aria-selected="false">
                                <i class="feather-dollar-sign text-success fs-14"></i>
                                {{ __('production.wc_labor_cost_breakdown') }}
                                <span class="badge bg-soft-success text-success ms-1 font-monospace">{{ format_currency($wcLaborCostPerHour) }}</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- CAPACITY TAB CONTENT -->
                        <div class="tab-pane fade show active" id="wc-capacity-pane" role="tabpanel" aria-labelledby="wc-capacity-tab">
                            <!-- Formula Banner -->
                            <div class="p-3 mb-3 rounded bg-soft-primary border border-primary-subtle text-primary fs-12">
                                <div class="fw-bold mb-1">
                                    <i class="feather-info me-1"></i>{{ __('production.wc_capacity_formula_explanation') }}
                                </div>
                                <div class="font-monospace fs-11 text-dark">
                                    {{ number_format($wcTotalTheoCap, 2) }} units &divide; {{ number_format($wcUniqOperatingHours, 2) }} h = <strong class="text-primary fs-13">{{ number_format($wcCapPerHour, 2) }} {{ __('production.units_hr') }}</strong>
                                </div>
                            </div>

                            <!-- Active Sections Contributing to Capacity -->
                            <div class="card border mb-3 shadow-none">
                                <div class="card-header bg-light py-2 px-3 border-bottom">
                                    <h6 class="fw-bold text-dark fs-12 mb-0">
                                        <i class="feather-grid me-1 text-primary"></i>Contributing Sections ({{ count($wcCapCalc['sections'] ?? []) }})
                                    </h6>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 15%">{{ __('production.section_code') }}</th>
                                                <th style="width: 30%">{{ __('production.section_name') }}</th>
                                                <th style="width: 15%">Machines</th>
                                                <th style="width: 20%">Manual Production</th>
                                                <th style="width: 20%" class="text-end">{{ __('production.capacity_per_hour') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($wcCapCalc['sections'] ?? [] as $sec)
                                                <tr>
                                                    <td><span class="font-monospace fw-bold">{{ $sec['code'] }}</span></td>
                                                    <td class="fw-semibold text-dark">{{ $sec['name'] }}</td>
                                                    <td>
                                                        <span class="badge bg-soft-secondary text-secondary rounded-pill px-2">
                                                            <i class="feather-cpu me-1"></i>{{ $sec['machine_count'] }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        @if($sec['supports_manual_production'])
                                                            <span class="badge bg-soft-warning text-warning rounded-pill px-2">
                                                                <i class="feather-user me-1"></i>{{ number_format($sec['manual_capacity_per_hour'], 2) }} units/hr
                                                            </span>
                                                        @else
                                                            <span class="text-muted fs-11">Disabled</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end font-monospace fw-bold text-dark">
                                                        {{ number_format($sec['capacity_per_hour'], 2) }} units/hr
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-2">No sections configured</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Timeline Slicing Windows -->
                            <div class="card border mb-3 shadow-none">
                                <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                    <h6 class="fw-bold text-dark fs-12 mb-0">
                                        <i class="feather-clock me-1 text-warning"></i>Capacity Operating Windows Breakdown ({{ count($wcCapWindows) }})
                                    </h6>
                                    <small class="text-muted fs-11">Split at every Section shift boundary</small>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover table-bordered align-middle mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 18%">{{ __('production.window_range') }}</th>
                                                <th style="width: 10%">{{ __('production.window_duration') }}</th>
                                                <th style="width: 32%">{{ __('production.active_sections') }}</th>
                                                <th style="width: 20%" class="text-end">{{ __('production.combined_capacity_per_hour') }}</th>
                                                <th style="width: 20%" class="text-end">{{ __('production.window_theoretical_capacity') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($wcCapWindows as $win)
                                                <tr class="@if($win['is_overlapping']) bg-soft-info-subtle @endif">
                                                    <td>
                                                        <span class="font-monospace fw-bold text-dark">{{ $win['window_label'] }}</span>
                                                        @if($win['is_overnight'])
                                                            <span class="badge bg-soft-dark text-dark fs-10 px-1 py-0.5 ms-1">Overnight</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-soft-secondary text-secondary font-monospace">{{ number_format($win['duration_hours'], 2) }} h</span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                                            @foreach($win['active_sections'] as $as)
                                                                <span class="badge bg-soft-primary text-primary px-2 py-0.5">
                                                                    {{ $as['name'] }} ({{ number_format($as['capacity_per_hour'], 2) }}/h)
                                                                </span>
                                                            @endforeach
                                                            @if($win['is_overlapping'])
                                                                <span class="badge bg-soft-warning text-warning rounded-pill px-1.5 py-0.5 fs-10 ms-1">
                                                                    <i class="feather-layers me-0.5"></i>Overlap ({{ count($win['active_sections']) }})
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="text-end font-monospace fw-bold text-dark">
                                                        {{ number_format($win['combined_capacity_per_hour'], 2) }} units/hr
                                                    </td>
                                                    <td class="text-end font-monospace fw-bold text-primary">
                                                        {{ number_format($win['theoretical_capacity'], 2) }} units
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-3">No operating windows</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        @if(!empty($wcCapWindows))
                                            <tfoot class="table-light fw-bold">
                                                <tr>
                                                    <td>Total</td>
                                                    <td><span class="font-monospace">{{ number_format($wcUniqOperatingHours, 2) }} h</span></td>
                                                    <td class="text-muted fs-11">Unique operating duration</td>
                                                    <td class="text-end text-muted fs-11">Theoretical Capacity Sum:</td>
                                                    <td class="text-end font-monospace text-primary fs-13">{{ number_format($wcTotalTheoCap, 2) }} units</td>
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            </div>

                            <!-- Timeline Gaps Table -->
                            @if(!empty($wcCapGaps))
                                <div class="card border border-warning-subtle mb-0 shadow-none">
                                    <div class="card-header bg-soft-warning py-2 px-3 border-bottom border-warning-subtle">
                                        <h6 class="fw-bold text-warning-emphasis fs-12 mb-0">
                                            <i class="feather-pause-circle me-1"></i>{{ __('production.timeline_gaps') }}
                                        </h6>
                                        <small class="text-muted fs-11">{{ __('production.timeline_gaps_desc') }}</small>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0 fs-12">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 25%">Excluded Gap Interval</th>
                                                    <th style="width: 15%">{{ __('production.gap_duration') }}</th>
                                                    <th style="width: 60%">Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($wcCapGaps as $gap)
                                                    <tr>
                                                        <td><span class="font-monospace fw-bold text-danger">{{ $gap['window_label'] }}</span></td>
                                                        <td><span class="badge bg-soft-danger text-danger font-monospace">{{ number_format($gap['duration_hours'], 2) }} h</span></td>
                                                        <td class="text-muted fs-11">No active section shifts operating during this period. Safely excluded from timeline.</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- LABOR COST TAB CONTENT -->
                        <div class="tab-pane fade" id="wc-labor-pane" role="tabpanel" aria-labelledby="wc-labor-tab">
                            <!-- Formula Banner -->
                            <div class="p-3 mb-3 rounded bg-soft-success border border-success-subtle text-success fs-12">
                                <div class="fw-bold mb-1">
                                    <i class="feather-info me-1"></i>{{ __('production.wc_labor_formula_explanation') }}
                                </div>
                                <div class="font-monospace fs-11 text-dark">
                                    {{ format_currency($wcTotalSchedLabor) }} &divide; {{ number_format($wcUniqOperatingHours, 2) }} h = <strong class="text-success fs-13">{{ format_currency($wcLaborCostPerHour) }} / hr</strong>
                                </div>
                            </div>

                            <!-- Section / Shift Assignments Table -->
                            <div class="card border mb-3 shadow-none">
                                <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                    <h6 class="fw-bold text-dark fs-12 mb-0">
                                        <i class="feather-users me-1 text-success"></i>{{ __('production.section_shift_assignments') }} ({{ count($wcLaborAssignments) }})
                                    </h6>
                                    <small class="text-muted fs-11">Costed individually using each Section's assigned worker count</small>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 20%">Section</th>
                                                <th style="width: 20%">Shift</th>
                                                <th style="width: 15%">Shift Hours</th>
                                                <th style="width: 12%">Workers</th>
                                                <th style="width: 15%">Hourly Rate / Worker</th>
                                                <th style="width: 18%" class="text-end">Hourly Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($wcLaborAssignments as $a)
                                                <tr>
                                                    <td>
                                                        <span class="fw-semibold text-dark">{{ $a['section_name'] }}</span>
                                                        <span class="font-monospace text-muted fs-11">[{{ $a['section_code'] }}]</span>
                                                    </td>
                                                    <td>
                                                        <span class="fw-semibold text-dark">{{ $a['shift_name'] }}</span>
                                                        <span class="font-monospace text-muted fs-11">[{{ $a['shift_code'] }}]</span>
                                                    </td>
                                                    <td>
                                                        <span class="font-monospace fs-11">{{ $a['start_time'] }}–{{ $a['end_time'] }}</span>
                                                        @if($a['is_overnight'])
                                                            <span class="badge bg-soft-dark text-dark fs-9">Overnight</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-soft-info text-info rounded-pill px-2">
                                                            <i class="feather-user me-0.5"></i>{{ $a['workers_required'] }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        @if($a['is_rate_missing'])
                                                            <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 px-1.5 py-0.5" title="No hourly rate configured on shift">
                                                                {{ __('production.missing_rate_badge') }}
                                                            </span>
                                                        @elseif($a['effective_hourly_rate'] == 0)
                                                            <span class="badge bg-soft-secondary text-secondary fs-10 px-1.5 py-0.5">
                                                                {{ __('production.explicit_zero_rate_badge') }}
                                                            </span>
                                                        @else
                                                            <span class="font-monospace text-dark">{{ format_currency($a['effective_hourly_rate']) }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end font-monospace fw-bold text-dark">
                                                        {{ format_currency($a['assignment_labor_cost_per_hour']) }} / hr
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-2">No shift assignments configured</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Timeline Slicing Windows -->
                            <div class="card border mb-3 shadow-none">
                                <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                    <h6 class="fw-bold text-dark fs-12 mb-0">
                                        <i class="feather-clock me-1 text-primary"></i>Labor Cost Operating Windows Breakdown ({{ count($wcLaborWindows) }})
                                    </h6>
                                    <small class="text-muted fs-11">Sum of active assignment costs &times; window duration</small>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover table-bordered align-middle mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 17%">{{ __('production.window_range') }}</th>
                                                <th style="width: 9%">{{ __('production.window_duration') }}</th>
                                                <th style="width: 32%">Active Assignments</th>
                                                <th style="width: 10%">Workers</th>
                                                <th style="width: 16%" class="text-end">{{ __('production.combined_labor_rate') }}</th>
                                                <th style="width: 16%" class="text-end">{{ __('production.window_labor_cost') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($wcLaborWindows as $win)
                                                <tr class="@if($win['is_overlapping']) bg-soft-info-subtle @endif">
                                                    <td>
                                                        <span class="font-monospace fw-bold text-dark">{{ $win['window_label'] }}</span>
                                                        @if($win['is_overnight'])
                                                            <span class="badge bg-soft-dark text-dark fs-10 px-1 py-0.5 ms-1">Overnight</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-soft-secondary text-secondary font-monospace">{{ number_format($win['duration_hours'], 2) }} h</span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                                            @foreach($win['active_assignments'] as $aa)
                                                                <span class="badge bg-soft-primary text-primary px-2 py-0.5 fs-11">
                                                                    {{ $aa['section_name'] }} / {{ $aa['shift_name'] }} ({{ $aa['workers_required'] }}w &times; {{ format_currency($aa['effective_hourly_rate']) }})
                                                                </span>
                                                            @endforeach
                                                            @if($win['is_overlapping'])
                                                                <span class="badge bg-soft-warning text-warning rounded-pill px-1.5 py-0.5 fs-10 ms-1">
                                                                    <i class="feather-layers me-0.5"></i>Overlap ({{ count($win['active_assignments']) }})
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-soft-info text-info font-monospace">{{ $win['combined_workers'] }}</span>
                                                    </td>
                                                    <td class="text-end font-monospace fw-bold text-dark">
                                                        {{ format_currency($win['combined_hourly_cost']) }} / hr
                                                    </td>
                                                    <td class="text-end font-monospace fw-bold text-success">
                                                        {{ format_currency($win['window_cost']) }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-3">No operating windows</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        @if(!empty($wcLaborWindows))
                                            <tfoot class="table-light fw-bold">
                                                <tr>
                                                    <td>Total</td>
                                                    <td><span class="font-monospace">{{ number_format($wcUniqOperatingHours, 2) }} h</span></td>
                                                    <td colspan="2" class="text-muted fs-11">Union of active assignments</td>
                                                    <td class="text-end text-muted fs-11">Total Scheduled Cost:</td>
                                                    <td class="text-end font-monospace text-success fs-13">{{ format_currency($wcTotalSchedLabor) }}</td>
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            </div>

                            <!-- Timeline Gaps Table -->
                            @if(!empty($wcLaborGaps))
                                <div class="card border border-warning-subtle mb-0 shadow-none">
                                    <div class="card-header bg-soft-warning py-2 px-3 border-bottom border-warning-subtle">
                                        <h6 class="fw-bold text-warning-emphasis fs-12 mb-0">
                                            <i class="feather-pause-circle me-1"></i>{{ __('production.timeline_gaps') }}
                                        </h6>
                                        <small class="text-muted fs-11">{{ __('production.timeline_gaps_desc') }}</small>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0 fs-12">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 25%">Excluded Gap Interval</th>
                                                    <th style="width: 15%">{{ __('production.gap_duration') }}</th>
                                                    <th style="width: 60%">Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($wcLaborGaps as $gap)
                                                    <tr>
                                                        <td><span class="font-monospace fw-bold text-danger">{{ $gap['window_label'] }}</span></td>
                                                        <td><span class="badge bg-soft-danger text-danger font-monospace">{{ number_format($gap['duration_hours'], 2) }} h</span></td>
                                                        <td class="text-muted fs-11">No section shifts operating during this period. Safely excluded from timeline.</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
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
    @foreach ($workCenter->sections as $section)
        @php
            $hasWorkerAssignments = $section->hasActiveWorkerAssignments();
            $workerCounts = $section->getActiveWorkerCounts();
            $isManualProd = (bool) $section->supports_manual_production;
            $manualCapVal = $section->manual_capacity_per_hour !== null ? (float) $section->manual_capacity_per_hour : '';
        @endphp
        <div class="modal fade" id="sectionManualProductionModal_{{ $section->id }}" tabindex="-1" aria-labelledby="sectionManualProductionModalLabel_{{ $section->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header border-bottom py-3">
                        <div>
                            <h5 class="modal-title fw-bold text-dark fs-15" id="sectionManualProductionModalLabel_{{ $section->id }}">
                                <i class="feather-tool text-warning me-2"></i>{{ __('production.manual_production') }} &mdash; {{ $section->name }}
                            </h5>
                            <div class="text-muted fs-11 mt-0.5">
                                Work Center: <span class="font-monospace fw-semibold text-dark">{{ $workCenter->code }}</span>
                                &bull; Section: <span class="fw-semibold text-dark">{{ $section->name }} ({{ $section->code }})</span>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    @if(!$hasWorkerAssignments)
                        <div class="modal-body p-4">
                            <div class="alert alert-warning d-flex align-items-start gap-2 py-3 px-3 mb-0">
                                <i class="feather-alert-triangle text-warning fs-18 mt-0.5 flex-shrink-0"></i>
                                <div>
                                    <h6 class="alert-heading fw-bold fs-13 mb-1">No Active Worker Assignments</h6>
                                    <div class="fs-12 text-muted">
                                        Manual production cannot be configured or enabled because this section has not been assigned any active internal employees or external workers. Manual production operations require human workers.
                                    </div>
                                    <div class="mt-3 d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-xs btn-outline-info rounded-pill px-2.5 py-1 fw-semibold" data-bs-target="#sectionEmployeesModal_{{ $section->id }}" data-target-tab="internal" data-bs-toggle="modal">
                                            <i class="feather-user-check me-1"></i>Assign Internal Employees
                                        </button>
                                        @can('create', App\Domains\Production\Models\ExternalWorkerAllocation::class)
                                            <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-1 fw-semibold" data-bs-target="#sectionEmployeesModal_{{ $section->id }}" data-target-tab="external" data-bs-toggle="modal">
                                                <i class="feather-users me-1"></i>Allocate External Workers
                                            </button>
                                        @endcan
                                    </div>
                                </div>
                            </div>

                            @if($isManualProd)
                                <div class="mt-3 p-3 bg-light rounded border">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold text-dark fs-12">Manual Production Currently Enabled</div>
                                            <small class="text-muted fs-11">Since there are no active workers, disable manual production to keep section capacity accurate.</small>
                                        </div>
                                        <form method="POST" action="{{ route('production.work-center-sections.update-manual-production', $section->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="supports_manual_production" value="0">
                                            <button type="submit" class="btn btn-xs btn-outline-danger">
                                                Disable Now
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        </div>
                    @else
                        <form method="POST" action="{{ route('production.work-center-sections.update-manual-production', $section->id) }}">
                            @csrf
                            @method('PATCH')
                            <div class="modal-body p-4">
                                <!-- Workforce summary banner -->
                                <div class="alert alert-info py-2 px-3 fs-12 mb-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <i class="feather-users me-1 text-info"></i>
                                        <strong>Active Section Workforce:</strong>
                                        {{ $workerCounts['internal'] }} Internal, {{ $workerCounts['external'] }} External (Total: {{ $workerCounts['total'] }})
                                    </div>
                                    <span class="badge bg-soft-success text-success font-monospace">Eligible</span>
                                </div>

                                <div class="p-3 border rounded bg-light mb-0">
                                    <div class="form-check form-switch mb-0">
                                        <input type="hidden" name="supports_manual_production" value="0">
                                        <input class="form-check-input section-manual-prod-toggle" type="checkbox" name="supports_manual_production" value="1" 
                                               id="modal_supports_manual_production_{{ $section->id }}" 
                                               data-target="#modal_manual_capacity_container_{{ $section->id }}"
                                               @checked($isManualProd)>
                                        <label class="form-check-label fw-bold text-dark fs-13" for="modal_supports_manual_production_{{ $section->id }}">
                                            {{ __('production.manual_production') }}
                                        </label>
                                        <div class="form-text fs-11 text-muted">{{ __('production.manual_production_desc') }}</div>
                                    </div>

                                    <div id="modal_manual_capacity_container_{{ $section->id }}" class="mt-3 pt-3 border-top @if(!$isManualProd) d-none @endif">
                                        <label class="form-label fw-bold fs-12 text-dark" for="modal_manual_capacity_per_hour_{{ $section->id }}">
                                            {{ __('production.manual_capacity_per_hour') }} <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="number" step="any" min="0.0001" name="manual_capacity_per_hour" id="modal_manual_capacity_per_hour_{{ $section->id }}" 
                                                   class="form-control fs-12" 
                                                   placeholder="e.g. 10.00"
                                                   value="{{ $manualCapVal }}"
                                                   @if(!$isManualProd) disabled @endif>
                                            <span class="input-group-text fs-11 text-muted bg-white">{{ __('production.units_per_hour') }}</span>
                                        </div>
                                        <div class="form-text fs-11 text-muted">{{ __('production.manual_capacity_hint') }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-save me-1"></i>Save Configuration
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    @push('scripts')
        <script>
            $(function() {
                // Toggle manual capacity per hour input in dedicated modal
                $(document).on('change', '.section-manual-prod-toggle', function() {
                    var $target = $($(this).data('target'));
                    var $input = $target.find('input');
                    if ($(this).is(':checked')) {
                        $target.removeClass('d-none');
                        $input.prop('disabled', false);
                    } else {
                        $target.addClass('d-none');
                        $input.prop('disabled', true);
                    }
                });

                // Initialize Bootstrap popovers for machine badges
                if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
                    $('[data-bs-toggle="popover"]').each(function () {
                        if (!bootstrap.Popover.getInstance(this)) {
                            new bootstrap.Popover(this, { sanitize: false });
                        }
                    });
                }

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

                // Initialize visible modal selects on modal shown
                $(document).on('shown.bs.modal', '.modal', function () {
                    var $modal = $(this);
                    $modal.find('[data-select2-selector]').each(function() {
                        var $sel = $(this);
                        if ($sel.closest('.shift-picker-panel.d-none').length === 0) {
                            initModalSelect2($sel, $modal);
                        }
                    });
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
