@extends('layouts.duralux')

@section('title', __('production.work_center_details') . ' | SaaS ERP')
@section('page-title', __('production.work_center_details'))
@section('breadcrumb', $workCenter->code)

@section('page-actions')
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#workCenterCalculationModal">
            <i class="feather-pie-chart me-1"></i>{{ __('production.calculation_breakdown') }}
        </button>
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
        .modal .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice .select2-selection__choice__remove:hover {
            color: #ef4444;
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
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
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
                            {{ $workCenter->capacity_per_hour !== null ? number_format((float) $workCenter->capacity_per_hour, 2) . ' ' . __('production.units_hr') : __('production.unlimited') }}
                        </span>
                        @if($workCenter->total_theoretical_capacity !== null)
                            <small class="text-muted fs-11 d-block mt-0.5">
                                {{ __('production.total_theoretical_capacity') }}: {{ number_format((float) $workCenter->total_theoretical_capacity, 2) }} units ({{ number_format((float) ($workCenter->unique_operating_hours ?? 0), 2) }} h)
                            </small>
                        @endif
                    </div>
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.operational_efficiency') }}</span>
                        <span class="fs-22 fw-bold text-dark">{{ number_format($workCenter->efficiency_percentage, 0) }}%</span>
                        <div class="progress progress-xs mt-2" style="height: 6px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $workCenter->efficiency_percentage }}%;" aria-valuenow="{{ $workCenter->efficiency_percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.effective_capacity') }}</span>
                        <span class="fs-18 fw-bold text-success">
                            {{ $workCenter->capacity_per_hour !== null ? number_format($workCenter->effectiveCapacityPerHour(), 2) . ' ' . __('production.units_hr') : __('production.unlimited') }}
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
                    $directCost = (float) ($workCenter->cost_per_hour ?? 0);
                    $overheadCost = (float) ($workCenter->overhead_rate ?? 0);
                    $totalStationCost = $directCost + $overheadCost;
                @endphp
                <div class="d-flex flex-column gap-3 py-1 px-1">
                    @if($workCenter->labor_cost_per_hour !== null)
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
                            <span class="fs-18 fw-bold text-primary font-monospace">{{ format_currency((float) $workCenter->labor_cost_per_hour) }} <span class="fs-11 fw-normal text-muted">/ {{ __('production.hour') }}</span></span>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                        <div>
                            <span class="text-muted fs-11 text-uppercase d-block fw-semibold">{{ __('production.cost_per_hour') }}</span>
                            <small class="text-muted fs-11">Direct Labor / Machine rate</small>
                        </div>
                        <span class="fs-18 fw-bold text-dark font-monospace">{{ format_currency($directCost) }} <span class="fs-11 fw-normal text-muted">/ {{ __('production.hour') }}</span></span>
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
                            <small class="text-muted fs-11">Combined Direct + Overhead rate</small>
                        </div>
                        <span class="fs-20 fw-bolder text-primary font-monospace">{{ format_currency($totalStationCost) }} <span class="fs-11 fw-normal text-muted">/ {{ __('production.hour') }}</span></span>
                    </div>
                    
                    <div class="border-top pt-2 mt-1">
                        <h6 class="fw-bold text-dark mb-1 fs-12">{{ __('production.wc_cost_guide') }}</h6>
                        <ul class="fs-11 text-muted ps-3 mb-0">
                            <li class="mb-1">{{ __('production.wc_cost_guide_text_1', ['cost' => format_currency($directCost)]) }}</li>
                            <li class="mb-1">Overhead Rate = <span class="fw-semibold text-dark">{{ format_currency($overheadCost) }}</span> / hr</li>
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
                <h5 class="fw-bold text-dark mb-0">{{ __('production.assigned_shifts') }}</h5>
                @can('update', $workCenter)
                    <x-ui.button :href="route('production.work-centers.edit', $workCenter->id)" variant="light" icon="feather-edit-2">
                        {{ __('production.manage_shifts') }}
                    </x-ui.button>
                @endcan
            </div>

            @if($workCenter->shifts->count() > 0)
                <div class="row g-3">
                    @foreach($workCenter->shifts as $shift)
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
                    <i class="feather-info me-2 fs-16"></i>{{ __('production.no_shifts_assigned') }}
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
                                $capPerHour = (float) ($section->capacity_per_hour ?? $calc['capacity']['capacity_per_hour'] ?? 0);
                                $capUnit = $calc['capacity']['capacity_unit'] ?? __('production.units_per_hour');
                                $laborCost = (float) ($section->labor_cost_per_hour ?? $calc['labor_cost']['labor_cost_per_hour'] ?? 0);
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
                                </td>
                                <td class="align-middle">
                                    @forelse ($section->shifts as $shift)
                                        @php
                                            $shiftWorkers = (int) ($shift->pivot->workers_required ?? $section->workers_required ?? 1);
                                        @endphp
                                        <div class="d-inline-flex align-items-center gap-1.5 py-1 px-2 border rounded bg-light mb-1 me-1 fs-11">
                                            <span class="fw-semibold text-dark">{{ $shift->name }}</span>
                                            @if(!str_contains($shift->name, substr($shift->start_time, 0, 5)))
                                                <span class="text-muted font-monospace fs-10">({{ substr($shift->start_time, 0, 5) }}–{{ substr($shift->end_time, 0, 5) }})</span>
                                            @endif
                                            <span class="text-muted">&mdash;</span>
                                            <span class="badge bg-soft-info text-info rounded-pill px-2 py-0.5 fw-semibold">
                                                <i class="feather-users me-0.5 fs-10"></i>{{ $shiftWorkers }} {{ $shiftWorkers == 1 ? __('production.worker') : __('production.workers') }}
                                            </span>
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
                                        @can('update', $section)
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editSectionModal_{{ $section->id }}">
                                                    <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('production.edit_section') }}
                                                </button>
                                            </li>
                                        @endcan

                                        @can('delete', $section)
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteSectionModal_{{ $section->id }}">
                                                    <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('production.delete_section') }}
                                                </button>
                                            </li>
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
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header border-bottom py-3">
                        <h5 class="modal-title fw-bold text-dark fs-15" id="createSectionModalLabel">
                            <i class="feather-plus-circle text-primary me-2"></i>{{ __('production.create_section') }} &mdash; {{ $workCenter->name }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('production.work-center-sections.store') }}">
                        @csrf
                        <input type="hidden" name="work_center_id" value="{{ $workCenter->id }}">
                        <input type="hidden" name="_modal" value="createSectionModal">

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

                                <!-- Manual Production -->
                                <div class="col-12">
                                    <div class="p-3 border rounded bg-light">
                                        <div class="form-check form-switch mb-0">
                                            <input type="hidden" name="supports_manual_production" value="0">
                                            <input class="form-check-input manual-production-toggle" type="checkbox" name="supports_manual_production" value="1" 
                                                   id="create_supports_manual_production" 
                                                   data-target="#create_manual_capacity_container"
                                                   @checked(old('_modal') === 'createSectionModal' && (bool) old('supports_manual_production'))>
                                            <label class="form-check-label fw-bold text-dark fs-12" for="create_supports_manual_production">
                                                 {{ __('production.manual_production') }}
                                            </label>
                                            <div class="form-text fs-11 text-muted">{{ __('production.manual_production_desc') }}</div>
                                        </div>

                                        <div id="create_manual_capacity_container" class="mt-3 pt-3 border-top @if(!(old('_modal') === 'createSectionModal' && (bool) old('supports_manual_production'))) d-none @endif">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold fs-12 text-dark" for="create_manual_capacity_per_hour">
                                                        {{ __('production.manual_capacity_per_hour') }} <span class="text-danger">*</span>
                                                    </label>
                                                    <div class="input-group">
                                                        <input type="number" step="any" min="0.0001" name="manual_capacity_per_hour" id="create_manual_capacity_per_hour" 
                                                               class="form-control fs-12 manual-capacity-input" 
                                                               placeholder="e.g. 10.00"
                                                               value="{{ old('_modal') === 'createSectionModal' ? old('manual_capacity_per_hour') : '' }}"
                                                               @if(!(old('_modal') === 'createSectionModal' && (bool) old('supports_manual_production'))) disabled @endif>
                                                        <span class="input-group-text fs-11 text-muted bg-white">{{ __('production.units_per_hour') }}</span>
                                                    </div>
                                                    <div class="form-text fs-11 text-muted">{{ __('production.manual_capacity_hint') }}</div>
                                                    @if(old('_modal') === 'createSectionModal' && $errors->has('manual_capacity_per_hour'))
                                                        <div class="text-danger fs-11 mt-1 d-block">{{ $errors->first('manual_capacity_per_hour') }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold fs-12 text-dark d-block">
                                        {{ __('production.section_shifts') }} <span class="text-danger">*</span>
                                        <span class="fw-normal text-muted fs-11 ms-1">({{ __('production.section_shifts_helper') }})</span>
                                    </label>

                                    @if($workCenter->shifts->count() > 0)
                                        <div class="p-3 border rounded bg-light">
                                            <div class="d-flex flex-column gap-2">
                                                @foreach($workCenter->shifts as $sh)
                                                    @php
                                                        $isShiftChecked = is_array(old('shifts')) && old('_modal') === 'createSectionModal' 
                                                            ? in_array($sh->id, old('shifts')) 
                                                            : false;
                                                        $shiftWorkerCount = old('_modal') === 'createSectionModal' && isset(old('shift_workers')[$sh->id])
                                                            ? old('shift_workers')[$sh->id]
                                                            : 1;
                                                    @endphp
                                                    <div class="p-2 border rounded bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 shift-row">
                                                        <div class="form-check mb-0">
                                                            <input class="form-check-input section-shift-checkbox" type="checkbox" name="shifts[]" value="{{ $sh->id }}" 
                                                                   id="create_shift_{{ $sh->id }}"
                                                                   data-target="#create_shift_workers_wrapper_{{ $sh->id }}"
                                                                   @checked($isShiftChecked)>
                                                            <label class="form-check-label text-dark fs-12 cursor-pointer" for="create_shift_{{ $sh->id }}">
                                                                <span class="fw-bold font-monospace">{{ $sh->code }}</span> &mdash; {{ $sh->name }}
                                                                @if(!str_contains($sh->name, substr($sh->start_time, 0, 5)))
                                                                    <span class="text-muted fs-11">({{ substr($sh->start_time, 0, 5) }}–{{ substr($sh->end_time, 0, 5) }})</span>
                                                                @endif
                                                            </label>
                                                        </div>
                                                        <div id="create_shift_workers_wrapper_{{ $sh->id }}" class="d-flex align-items-center gap-1.5 ms-auto shift-workers-group @if(!$isShiftChecked) d-none @endif">
                                                            <label class="form-label fs-11 text-muted mb-0 fw-semibold text-nowrap" for="create_shift_workers_{{ $sh->id }}">
                                                                {{ __('production.workers_required') }}:
                                                            </label>
                                                            <div style="width: 85px;">
                                                                <input type="number" name="shift_workers[{{ $sh->id }}]" id="create_shift_workers_{{ $sh->id }}"
                                                                       class="form-control form-control-sm text-center shift-worker-input"
                                                                       min="1" step="1"
                                                                       value="{{ $shiftWorkerCount }}"
                                                                       @if(!$isShiftChecked) disabled @endif>
                                                            </div>
                                                            <span class="fs-11 text-muted">{{ __('production.workers') }}</span>
                                                        </div>
                                                    </div>
                                                    @if(old('_modal') === 'createSectionModal' && $errors->has('shift_workers.'.$sh->id))
                                                        <div class="text-danger fs-11 mt-1 ms-3">{{ $errors->first('shift_workers.'.$sh->id) }}</div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <div class="alert alert-warning py-2 px-3 fs-12 mb-0">
                                            <i class="feather-alert-triangle me-1"></i>{{ __('production.section_shifts_none_available') }}
                                        </div>
                                    @endif

                                    @if(old('_modal') === 'createSectionModal' && $errors->has('shifts'))
                                        <div class="text-danger fs-11 mt-1 d-block">{{ $errors->first('shifts') }}</div>
                                    @endif
                                    @if(old('_modal') === 'createSectionModal' && $errors->has('shift_workers'))
                                        <div class="text-danger fs-11 mt-1 d-block">{{ $errors->first('shift_workers') }}</div>
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
                                        <select name="machine_ids[]" id="create_section_machine_ids" class="form-select section-machine-select" multiple="multiple" data-placeholder="{{ __('production.select_machines') }}">
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
                            <button type="submit" class="btn btn-primary" @if($workCenter->shifts->count() === 0) disabled @endif>
                                <i class="feather-check me-1"></i>{{ __('production.save_section') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    <!-- Edit and Delete Modals for Each Section -->
    @foreach($workCenter->sections as $section)
        @can('update', $section)
            <div class="modal fade" id="editSectionModal_{{ $section->id }}" tabindex="-1" aria-labelledby="editSectionModalLabel_{{ $section->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-0 shadow-lg rounded-3">
                        <div class="modal-header border-bottom py-3">
                            <h5 class="modal-title fw-bold text-dark fs-15" id="editSectionModalLabel_{{ $section->id }}">
                                <i class="feather-edit text-primary me-2"></i>{{ __('production.edit_section') }} &mdash; {{ $section->code }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="{{ route('production.work-center-sections.update', $section->id) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="work_center_id" value="{{ $workCenter->id }}">
                            <input type="hidden" name="_modal" value="editSectionModal_{{ $section->id }}">

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

                                    <!-- Manual Production -->
                                    @php
                                        $isManual = old('_modal') === 'editSectionModal_'.$section->id 
                                            ? (bool) old('supports_manual_production') 
                                            : (bool) $section->supports_manual_production;
                                        $manualCapacity = old('_modal') === 'editSectionModal_'.$section->id 
                                            ? old('manual_capacity_per_hour') 
                                            : ($section->manual_capacity_per_hour !== null ? (float) $section->manual_capacity_per_hour : '');
                                    @endphp
                                    <div class="col-12">
                                        <div class="p-3 border rounded bg-light">
                                            <div class="form-check form-switch mb-0">
                                                <input type="hidden" name="supports_manual_production" value="0">
                                                <input class="form-check-input manual-production-toggle" type="checkbox" name="supports_manual_production" value="1" 
                                                       id="edit_supports_manual_production_{{ $section->id }}" 
                                                       data-target="#edit_manual_capacity_container_{{ $section->id }}"
                                                       @checked($isManual)>
                                                <label class="form-check-label fw-bold text-dark fs-12" for="edit_supports_manual_production_{{ $section->id }}">
                                                    {{ __('production.manual_production') }}
                                                </label>
                                                <div class="form-text fs-11 text-muted">{{ __('production.manual_production_desc') }}</div>
                                            </div>

                                            <div id="edit_manual_capacity_container_{{ $section->id }}" class="mt-3 pt-3 border-top @if(!$isManual) d-none @endif">
                                                <div class="row align-items-center">
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-bold fs-12 text-dark" for="edit_manual_capacity_per_hour_{{ $section->id }}">
                                                            {{ __('production.manual_capacity_per_hour') }} <span class="text-danger">*</span>
                                                        </label>
                                                        <div class="input-group">
                                                            <input type="number" step="any" min="0.0001" name="manual_capacity_per_hour" id="edit_manual_capacity_per_hour_{{ $section->id }}" 
                                                                   class="form-control fs-12 manual-capacity-input" 
                                                                   placeholder="e.g. 10.00"
                                                                   value="{{ $manualCapacity }}"
                                                                   @if(!$isManual) disabled @endif>
                                                            <span class="input-group-text fs-11 text-muted bg-white">{{ __('production.units_per_hour') }}</span>
                                                        </div>
                                                        <div class="form-text fs-11 text-muted">{{ __('production.manual_capacity_hint') }}</div>
                                                        @if(old('_modal') === 'editSectionModal_'.$section->id && $errors->has('manual_capacity_per_hour'))
                                                            <div class="text-danger fs-11 mt-1 d-block">{{ $errors->first('manual_capacity_per_hour') }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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
                                            $savedShiftWorkers = $section->shifts->pluck('pivot.workers_required', 'id')->toArray();
                                        @endphp

                                        @if($workCenter->shifts->count() > 0)
                                            <div class="p-3 border rounded bg-light">
                                                <div class="d-flex flex-column gap-2">
                                                    @foreach($workCenter->shifts as $sh)
                                                        @php
                                                            $isShiftChecked = in_array($sh->id, $selectedShifts);
                                                            $shiftWorkerCount = old('_modal') === 'editSectionModal_'.$section->id && isset(old('shift_workers')[$sh->id])
                                                                ? old('shift_workers')[$sh->id]
                                                                : ($savedShiftWorkers[$sh->id] ?? $section->workers_required ?? 1);
                                                        @endphp
                                                        <div class="p-2 border rounded bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 shift-row">
                                                            <div class="form-check mb-0">
                                                                <input class="form-check-input section-shift-checkbox" type="checkbox" name="shifts[]" value="{{ $sh->id }}" 
                                                                       id="edit_shift_{{ $section->id }}_{{ $sh->id }}"
                                                                       data-target="#edit_shift_workers_wrapper_{{ $section->id }}_{{ $sh->id }}"
                                                                       @checked($isShiftChecked)>
                                                                <label class="form-check-label text-dark fs-12 cursor-pointer" for="edit_shift_{{ $section->id }}_{{ $sh->id }}">
                                                                    <span class="fw-bold font-monospace">{{ $sh->code }}</span> &mdash; {{ $sh->name }}
                                                                    @if(!str_contains($sh->name, substr($sh->start_time, 0, 5)))
                                                                        <span class="text-muted fs-11">({{ substr($sh->start_time, 0, 5) }}–{{ substr($sh->end_time, 0, 5) }})</span>
                                                                    @endif
                                                                </label>
                                                            </div>
                                                            <div id="edit_shift_workers_wrapper_{{ $section->id }}_{{ $sh->id }}" class="d-flex align-items-center gap-1.5 ms-auto shift-workers-group @if(!$isShiftChecked) d-none @endif">
                                                                <label class="form-label fs-11 text-muted mb-0 fw-semibold text-nowrap" for="edit_shift_workers_{{ $section->id }}_{{ $sh->id }}">
                                                                    {{ __('production.workers_required') }}:
                                                                </label>
                                                                <div style="width: 85px;">
                                                                    <input type="number" name="shift_workers[{{ $sh->id }}]" id="edit_shift_workers_{{ $section->id }}_{{ $sh->id }}"
                                                                           class="form-control form-control-sm text-center shift-worker-input"
                                                                           min="1" step="1"
                                                                           value="{{ $shiftWorkerCount }}"
                                                                           @if(!$isShiftChecked) disabled @endif>
                                                                </div>
                                                                <span class="fs-11 text-muted">{{ __('production.workers') }}</span>
                                                            </div>
                                                        </div>
                                                        @if(old('_modal') === 'editSectionModal_'.$section->id && $errors->has('shift_workers.'.$sh->id))
                                                            <div class="text-danger fs-11 mt-1 ms-3">{{ $errors->first('shift_workers.'.$sh->id) }}</div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        @else
                                            <div class="alert alert-warning py-2 px-3 fs-12 mb-0">
                                                <i class="feather-alert-triangle me-1"></i>{{ __('production.section_shifts_none_available') }}
                                            </div>
                                        @endif

                                        @if(old('_modal') === 'editSectionModal_'.$section->id && $errors->has('shifts'))
                                            <div class="text-danger fs-11 mt-1 d-block">{{ $errors->first('shifts') }}</div>
                                        @endif
                                        @if(old('_modal') === 'editSectionModal_'.$section->id && $errors->has('shift_workers'))
                                            <div class="text-danger fs-11 mt-1 d-block">{{ $errors->first('shift_workers') }}</div>
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
                                            <select name="machine_ids[]" id="edit_section_machine_ids_{{ $section->id }}" class="form-select section-machine-select" multiple="multiple" data-placeholder="{{ __('production.select_machines') }}">
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
                                <button type="submit" class="btn btn-primary" @if($workCenter->shifts->count() === 0) disabled @endif>
                                    <i class="feather-check me-1"></i>{{ __('production.update_section') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endcan

        @can('delete', $section)
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
                                @if($section->machines->count() > 0)
                                    <div class="alert alert-danger border border-danger d-flex align-items-start gap-2 mb-3">
                                        <i class="feather-slash fs-18 text-danger mt-1 flex-shrink-0"></i>
                                        <div>
                                            <h6 class="fw-bold text-danger mb-1">{{ __('production.cannot_delete_has_machines') }}</h6>
                                            <p class="fs-12 text-dark mb-0">{{ __('production.delete_section_has_machines_warning') }}</p>
                                            <div class="mt-2 fw-semibold fs-12 text-dark">
                                                Assigned Machines ({{ $section->machines->count() }}):
                                                <span class="text-muted">{{ $section->machines->pluck('name')->join(', ') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <p class="text-dark fs-13 mb-2">{{ __('production.delete_section_confirm') }}</p>
                                    <div class="p-3 bg-light rounded border">
                                        <span class="fw-bold text-dark">{{ $section->name }}</span>
                                        <span class="font-monospace text-muted ms-2">({{ $section->code }})</span>
                                    </div>
                                @endif
                            </div>

                            <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                                @if($section->machines->count() === 0)
                                    <button type="submit" class="btn btn-danger">
                                        <i class="feather-trash-2 me-1"></i>{{ __('production.delete_section') }}
                                    </button>
                                @else
                                    <button type="submit" class="btn btn-danger" disabled title="{{ __('production.cannot_delete_has_machines') }}">
                                        <i class="feather-slash me-1"></i>{{ __('production.cannot_delete_has_machines') }}
                                    </button>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
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
        $wcCapPerHour = (float) ($workCenter->capacity_per_hour ?? $wcCapCalc['capacity_per_hour'] ?? 0);
        $wcTotalTheoCap = (float) ($workCenter->total_theoretical_capacity ?? $wcCapCalc['total_theoretical_capacity'] ?? 0);
        $wcLaborCostPerHour = (float) ($workCenter->labor_cost_per_hour ?? $wcLaborCalc['labor_cost_per_hour'] ?? 0);
        $wcTotalSchedLabor = (float) ($workCenter->total_scheduled_labor_cost ?? $wcLaborCalc['total_scheduled_labor_cost'] ?? 0);
        $wcUniqOperatingHours = (float) ($workCenter->unique_operating_hours ?? $wcCapCalc['unique_operating_hours'] ?? $wcLaborCalc['unique_operating_hours'] ?? 0);
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
                </div>

                <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-end">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        {{ __('production.close_tab') ?? 'Close' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize Bootstrap popovers for machine badges
                if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
                    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
                    popoverTriggerList.forEach(function (popoverTriggerEl) {
                        if (!bootstrap.Popover.getInstance(popoverTriggerEl)) {
                            new bootstrap.Popover(popoverTriggerEl, {
                                sanitize: false
                            });
                        }
                    });
                }

                if (typeof $ !== 'undefined' && $.fn.select2) {
                    $('.modal').on('shown.bs.modal', function () {
                        var $modal = $(this);
                        $modal.find('.section-machine-select').each(function() {
                            var $select = $(this);
                            if ($select.hasClass('select2-hidden-accessible')) {
                                $select.select2('destroy');
                            }
                            $select.select2({
                                theme: 'bootstrap-5',
                                dropdownParent: $modal.find('.modal-content'),
                                width: '100%',
                                placeholder: $select.data('placeholder') || '{{ __('production.select_machines') }}',
                                closeOnSelect: true
                            });
                        });
                    });

                    // Auto-close Select2 dropdown when scrolling or hiding modal to prevent floating detached dropdowns
                    $('.modal').on('hide.bs.modal scroll', function () {
                        $(this).find('.section-machine-select').each(function() {
                            if ($(this).data('select2')) {
                                $(this).select2('close');
                            }
                        });
                    });

                    $('.modal-body').on('scroll', function () {
                        $(this).find('.section-machine-select').each(function() {
                            if ($(this).data('select2')) {
                                $(this).select2('close');
                            }
                        });
                    });
                }

                // Handle manual production toggle
                document.addEventListener('change', function(e) {
                    if (e.target && e.target.classList.contains('manual-production-toggle')) {
                        var targetSelector = e.target.getAttribute('data-target');
                        var targetEl = document.querySelector(targetSelector);
                        if (targetEl) {
                            var input = targetEl.querySelector('.manual-capacity-input');
                            if (e.target.checked) {
                                targetEl.classList.remove('d-none');
                                if (input) {
                                    input.removeAttribute('disabled');
                                    input.focus();
                                }
                            } else {
                                targetEl.classList.add('d-none');
                                if (input) {
                                    input.setAttribute('disabled', 'disabled');
                                    input.value = '';
                                }
                            }
                        }
                    }
                });

                // Handle section shift selection toggle for worker inputs
                document.addEventListener('change', function(e) {
                    if (e.target && e.target.classList.contains('section-shift-checkbox')) {
                        var targetSelector = e.target.getAttribute('data-target');
                        var targetEl = document.querySelector(targetSelector);
                        if (targetEl) {
                            var input = targetEl.querySelector('.shift-worker-input');
                            if (e.target.checked) {
                                targetEl.classList.remove('d-none');
                                if (input) {
                                    input.removeAttribute('disabled');
                                    if (!input.value || parseInt(input.value, 10) < 1) {
                                        input.value = '1';
                                    }
                                }
                            } else {
                                targetEl.classList.add('d-none');
                                if (input) {
                                    input.setAttribute('disabled', 'disabled');
                                }
                            }
                        }
                    }
                });
            });
        </script>
    @endpush

    @if($errors->any() && old('_modal'))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
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
