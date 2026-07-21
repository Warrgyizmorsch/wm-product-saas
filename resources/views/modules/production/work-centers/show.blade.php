@extends('layouts.duralux')

@section('title', __('production.work_center_details') . ' | SaaS ERP')
@section('page-title', __('production.work_center_details'))
@section('breadcrumb', $workCenter->code)

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('production.work-centers.index') }}" class="btn btn-secondary">
            <i class="feather-arrow-left me-2"></i>{{ __('production.back_to_list') }}
        </a>
        @can('update', $workCenter)
            <a href="{{ route('production.work-centers.edit', $workCenter->id) }}" class="btn btn-primary">
                <i class="feather-edit me-2"></i>{{ __('production.edit') }}
            </a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        <!-- Success & Error Alerts -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
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
                        <span class="text-muted fs-13">{{ __('production.department_name') }}:</span>
                        <span class="fw-semibold text-dark">{{ $workCenter->department_name ?? '—' }}</span>
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
                </div>
            </div>

            <!-- Overhead Cost Panel -->
            <div class="col-md-4">
                <h5 class="fw-bold text-dark mb-3">{{ __('production.wc_cost_structure') }}</h5>
                <div class="d-flex flex-column gap-4 py-2 px-2">
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.overhead_cost_rate') }}</span>
                        <span class="fs-24 fw-bold text-dark">{{ format_currency($workCenter->cost_per_hour) }} <span class="fs-13 fw-normal text-muted">/ {{ __('production.hour') }}</span></span>
                        <small class="text-muted d-block mt-1">{{ __('production.overhead_cost_rate_help') }}</small>
                    </div>
                    
                    <div class="border-top pt-3 mt-2">
                        <h6 class="fw-bold text-dark mb-2">{{ __('production.wc_cost_guide') }}</h6>
                        <ul class="fs-12 text-muted ps-3 mb-0">
                            <li class="mb-1">{{ __('production.wc_cost_guide_text_1', ['cost' => format_currency($workCenter->cost_per_hour)]) }}</li>

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
                    <a href="{{ route('production.work-centers.edit', $workCenter->id) }}" class="btn btn-sm btn-soft-primary">
                        <i class="feather-edit-2 me-1"></i>{{ __('production.manage_shifts') }}
                    </a>
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

        <!-- Assigned Machines List -->
        <div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">{{ __('production.assigned_equipment') }}</h5>
                @can('create', App\Domains\Production\Models\Machine::class)
                    <a href="{{ route('production.machines.create', ['work_center_id' => $workCenter->id]) }}" class="btn btn-sm btn-soft-primary">
                        <i class="feather-plus me-1"></i>{{ __('production.add_machine') }}
                    </a>
                @endcan
            </div>

            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 15%">{{ __('production.machine_code') }}</th>
                            <th style="width: 20%">{{ __('production.machine_name') }}</th>
                            <th style="width: 15%">{{ __('production.machine_type') }}</th>
                            <th style="width: 15%">{{ __('production.manufacturer') }}</th>
                            <th style="width: 12%">{{ __('production.model_number') }}</th>
                            <th style="width: 10%" class="text-end">{{ __('production.capacity_hr') }}</th>
                            <th style="width: 8%">{{ __('production.status') }}</th>
                            <th style="width: 5%" class="text-end">{{ __('production.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workCenter->machines as $machine)
                            <tr>
                                <td class="align-middle fw-bold text-dark">{{ $machine->code }}</td>
                                <td class="align-middle">{{ $machine->name }}</td>
                                <td class="align-middle">{{ $machine->machine_type ?? '—' }}</td>
                                <td class="align-middle">{{ $machine->manufacturer ?? '—' }}</td>
                                <td class="align-middle">{{ $machine->model_number ?? '—' }}</td>
                                <td class="text-end align-middle fw-semibold">
                                    {{ $machine->capacity !== null ? number_format($machine->capacity, 2) : '—' }}
                                </td>
                                <td class="align-middle">
                                    @if ($machine->isActive())
                                        <span class="badge bg-soft-success text-success rounded-pill px-2 py-1">{{ __('production.active') }}</span>
                                    @elseif ($machine->isUnderMaintenance())
                                        <span class="badge bg-soft-warning text-warning rounded-pill px-2 py-1">Maint.</span>
                                    @elseif ($machine->isDecommissioned())
                                        <span class="badge bg-soft-dark text-dark rounded-pill px-2 py-1">Decom.</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger rounded-pill px-2 py-1">{{ __('production.inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end align-middle">
                                    <x-ui.action-dropdown>
                                        <li>
                                            <a href="{{ route('production.machines.edit', $machine->id) }}" class="dropdown-item">
                                                <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('production.edit_machine') }}
                                            </a>
                                        </li>
                                    </x-ui.action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>{{ __('production.no_machines_registered') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>
    </div>
@endsection
