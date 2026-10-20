@extends('layouts.duralux')

@section('title', __('production.work_center_details') . ' | SaaS ERP')
@section('page-title', __('production.work_center_details'))
@section('breadcrumb', $workCenter->code)

@section('page-actions')
    <div class="d-flex gap-2">
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
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.nominal_capacity') }}</span>
                        <span class="fs-22 fw-bold text-dark">
                            {{ $workCenter->capacity_per_hour !== null ? number_format($workCenter->capacity_per_hour, 2) . ' ' . __('production.units') : __('production.unlimited') }}
                        </span>
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
                            <th style="width: 15%">{{ __('production.section_code') }}</th>
                            <th style="width: 22%">{{ __('production.section_name') }}</th>
                            <th style="width: 35%">{{ __('production.section_shifts') }}</th>
                            <th style="width: 10%">{{ __('production.machines_count') }}</th>
                            <th style="width: 10%">{{ __('production.status') }}</th>
                            <th style="width: 8%" class="text-end">{{ __('production.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workCenter->sections as $section)
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
                                    @if ($section->isActive())
                                        <span class="badge bg-soft-success text-success rounded-pill px-2 py-1">{{ __('production.active') }}</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger rounded-pill px-2 py-1">{{ __('production.inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end align-middle">
                                    <x-ui.action-dropdown>
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
                                <td colspan="6" class="text-center py-4 text-muted">
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
    @endforeach

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
