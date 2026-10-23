@extends('layouts.duralux')

@section('title', __('production.machine_details') . ' | SaaS ERP')
@section('page-title', __('production.machine_details'))
@section('breadcrumb', $machine->code)

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('production.mes.machines.index') }}" class="btn btn-secondary">
            <i class="feather-arrow-left me-1"></i>{{ __('production.back_to_list') ?? 'Back to Machines' }}
        </a>
        @can('update', $machine)
            <a href="{{ route('production.machines.edit', $machine->id) }}" class="btn btn-outline-primary">
                <i class="feather-edit me-1"></i>{{ __('production.edit') ?? 'Edit Machine' }}
            </a>
        @endcan
        <a href="{{ route('production.maintenance.dashboard') }}" class="btn btn-danger">
            <i class="feather-alert-triangle me-1"></i>Report Breakdown
        </a>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">

        {{-- Active Downtime Alert (Links to Maintenance Dashboard) --}}
        @php
            $activeDowntimes = $downtimes->where('status', 'open');
        @endphp
        @if($activeDowntimes->count() > 0)
            <div class="alert alert-danger border-danger bg-soft-danger d-flex flex-wrap justify-content-between align-items-center p-3 rounded mb-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-text avatar-md bg-danger text-white rounded">
                        <i class="feather-alert-octagon fs-18"></i>
                    </div>
                    <div>
                        <strong class="text-danger fs-14 d-block">Active Breakdown / Downtime Event in Progress</strong>
                        <span class="text-dark fs-12">
                            {{ $activeDowntimes->count() }} active event(s) logged for this machine. Work orders and issue resolutions are managed centrally in the Maintenance module.
                        </span>
                    </div>
                </div>
                <div class="mt-2 mt-md-0">
                    <a href="{{ route('production.maintenance.dashboard') }}" class="btn btn-sm btn-danger fw-bold">
                        <i class="feather-external-link me-1"></i>Manage in Maintenance Dashboard
                    </a>
                </div>
            </div>
        @endif

        {{-- Active Run Info (if any) --}}
        @if($currentOp)
            <x-ui.card class="border-warning border mb-4">
                <x-slot name="headerAction">
                    <h6 class="fw-bold text-warning mb-0"><i class="feather-play-circle me-2"></i>{{ __('production.current_operation') }}</h6>
                </x-slot>
                <div class="row g-3 py-2">
                    <div class="col-md-4">
                        <div class="text-muted fs-11 text-uppercase fw-bold mb-1">{{ __('production.col_operation') }}</div>
                        <div class="fw-bold text-dark">{{ $currentOp->orderOperation->name ?? '—' }}</div>
                        <div class="text-muted fs-12">{{ $currentOp->orderOperation->operation_number ?? '' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted fs-11 text-uppercase fw-bold mb-1">{{ __('production.col_product') }}</div>
                        <div class="fw-bold text-dark">{{ $currentOp->order->product->name ?? '—' }}</div>
                        <div class="text-muted fs-12">{{ $currentOp->order->order_number ?? '' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted fs-11 text-uppercase fw-bold mb-1">Progress</div>
                        @if($currentOp->actual_start)
                            @php
                                $pausedSec = $currentOp->accumulated_paused_seconds ?? 0;
                                $netSeconds = max(0, now()->timestamp - $currentOp->actual_start->timestamp - $pausedSec);
                                $netMinutes = round($netSeconds / 60);
                            @endphp
                            <div class="fw-bold text-warning">
                                @if($netMinutes >= 60)
                                    {{ floor($netMinutes / 60) }}h {{ $netMinutes % 60 }}m
                                @else
                                    {{ $netMinutes }} minutes
                                @endif
                            </div>
                            <div class="text-muted fs-12">Started {{ $currentOp->actual_start->format('d/m H:i') }}</div>
                        @endif
                        <div class="text-muted fs-12 mt-1">Est. finish: {{ $currentOp->planned_finish->format('d/m H:i') }}</div>
                    </div>
                </div>
            </x-ui.card>
        @endif

        {{-- Next Job Alert (if any) --}}
        @if($nextOp)
            <div class="alert alert-info border-info bg-soft-info d-flex align-items-center p-3 rounded mb-4">
                <i class="feather-arrow-right me-3 text-info"></i>
                <div>
                    <strong class="text-info fs-12">Next in Queue:</strong>
                    <span class="ms-2 text-dark fs-12">{{ $nextOp->orderOperation->name ?? 'Operation' }}</span>
                    <span class="ms-2 text-muted fs-12">— {{ $nextOp->order->order_number ?? '' }}</span>
                    <span class="ms-2 text-muted fs-12">· Planned: {{ $nextOp->planned_start->format('d/m H:i') }}</span>
                </div>
            </div>
        @endif

        {{-- Machine Profile & Master Data Details (3 Panels mirroring Work Center / Routing show views) --}}
        <div class="row g-4 mb-4 pb-4 border-bottom">
            {{-- Panel 1: Machine Identity & Status --}}
            <div class="col-md-4 border-end">
                @php
                    $avatarColor = match($machine->current_state) {
                        'Running' => 'bg-soft-success text-success',
                        'Breakdown' => 'bg-soft-danger text-danger',
                        'Setup' => 'bg-soft-info text-info',
                        'Waiting Material', 'Waiting Operator' => 'bg-soft-warning text-warning',
                        'Maintenance' => 'bg-soft-primary text-primary',
                        default => 'bg-soft-secondary text-secondary',
                    };

                    $statusBadge = match($machine->status) {
                        'active' => 'bg-soft-success text-success',
                        'under_maintenance' => 'bg-soft-warning text-warning',
                        'inactive' => 'bg-soft-secondary text-secondary',
                        'decommissioned' => 'bg-soft-danger text-danger',
                        default => 'bg-soft-secondary text-secondary',
                    };
                @endphp
                <div class="text-center py-3">
                    <div class="avatar-text avatar-xl {{ $avatarColor }} mx-auto mb-3 rounded-circle" style="width: 80px; height: 80px; font-size: 32px; display: flex; align-items: center; justify-content: center;">
                        <i class="feather-cpu"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">{{ $machine->name }}</h4>
                    <span class="fs-13 fw-semibold text-muted text-uppercase font-monospace">{{ $machine->code }}</span>
                    <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                        <span class="badge {{ $statusBadge }} px-3 py-1.5 rounded-pill text-uppercase">
                            {{ ucfirst(str_replace('_', ' ', $machine->status)) }}
                        </span>
                        <span class="badge {{ $avatarColor }} px-3 py-1.5 rounded-pill fw-bold">
                            <i class="feather-activity me-1"></i>{{ $machine->current_state ?: 'Idle' }}
                        </span>
                    </div>
                    @if($machine->current_state_reason)
                        <div class="text-muted fs-11 mt-2 fst-italic">{{ $machine->current_state_reason }}</div>
                    @endif
                </div>

                <div class="d-flex flex-column gap-3 mt-3 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.work_center') ?? 'Work Center' }}:</span>
                        @if($machine->workCenter)
                            <a href="{{ route('production.work-centers.show', $machine->workCenter->id) }}" class="fw-bold text-primary fs-12 text-decoration-none">
                                {{ $machine->workCenter->name }} ({{ $machine->workCenter->code }})
                            </a>
                        @else
                            <span class="text-muted fs-12">—</span>
                        @endif
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.machine_type') ?? 'Machine Type' }}:</span>
                        <span class="fw-semibold text-dark fs-12">{{ $machine->machine_type ?: '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.manufacturer') ?? 'Manufacturer' }}:</span>
                        <span class="fw-semibold text-dark fs-12">{{ $machine->manufacturer ?: '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.model_number') ?? 'Model Number' }}:</span>
                        <span class="fw-semibold text-dark fs-12">{{ $machine->model_number ?: '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.created_date') ?? 'Created Date' }}:</span>
                        <span class="fw-semibold text-dark fs-12">{{ $machine->created_at->format('Y-m-d') }}</span>
                    </div>
                </div>
            </div>

            {{-- Panel 2: Technical Specs & Capital Asset Integration --}}
            <div class="col-md-4 border-end">
                <h5 class="fw-bold text-dark mb-3">Technical & Operating Specs</h5>
                <div class="d-flex flex-column gap-3 py-1 px-2">
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.machine_hourly_capacity') ?? 'Nominal Capacity' }}</span>
                        <span class="fs-20 fw-bold text-dark">
                            {{ $machine->capacity !== null ? number_format($machine->capacity, 2) . ' ' . (__('production.units_hr') ?? 'units/hr') : 'Not specified' }}
                        </span>
                    </div>

                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.installation_date') ?? 'Commissioning Date' }}</span>
                        <span class="fs-15 fw-semibold text-dark">
                            {{ $machine->installation_date ? $machine->installation_date->format('Y-m-d') : '—' }}
                        </span>
                    </div>

                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.maintenance_details') ?? 'Maintenance Details' }}</span>
                        <span class="fs-13 text-muted">
                            {{ $machine->maintenance_status ?: 'Standard operating condition' }}
                        </span>
                    </div>

                    <div class="border-top pt-3">
                        <span class="text-muted fs-11 text-uppercase d-block mb-2"><i class="feather-link me-1"></i>Linked Capital Asset (Accounting)</span>
                        @if($machine->asset)
                            <div class="p-2.5 rounded bg-light border">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <a href="{{ route('accounting.fixed-assets.show', $machine->asset->id) }}" class="fw-bold text-primary font-monospace fs-12 text-decoration-none">
                                            {{ $machine->asset->asset_code }}
                                        </a>
                                        <div class="fw-semibold text-dark fs-12">{{ $machine->asset->name }}</div>
                                        <small class="text-muted fs-11">{{ $machine->asset->category->name ?? 'Fixed Asset' }} &middot; Cost: {{ format_currency($machine->asset->purchase_cost) }}</small>
                                    </div>
                                    <span class="badge bg-soft-success text-success fs-10 text-uppercase">{{ $machine->asset->status ?? 'active' }}</span>
                                </div>
                            </div>
                        @else
                            <div class="p-2 rounded bg-light border text-muted fs-12 fst-italic">
                                No fixed asset linked. Machine is tracked independently.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Panel 3: Maintenance Schedule & Module Link --}}
            @php
                $earliestActivePm = isset($pmSchedules) ? $pmSchedules->where('is_active', true)->whereNotNull('next_due_date')->sortBy('next_due_date')->first() : null;
                $effectiveNextMaintenanceDue = $machine->next_maintenance_due_date ?? $earliestActivePm?->next_due_date;

                $latestCompletedPm = isset($pmSchedules) ? $pmSchedules->whereNotNull('last_completed_date')->sortByDesc('last_completed_date')->first() : null;
                $latestCompletedWo = isset($maintenanceWorkOrders) ? $maintenanceWorkOrders->where('status', 'completed')->whereNotNull('actual_end')->sortByDesc('actual_end')->first() : null;

                $pmDate = $latestCompletedPm?->last_completed_date;
                $woDate = $latestCompletedWo?->actual_end;
                $fallbackLastDate = ($pmDate && $woDate) ? ($pmDate->gt($woDate) ? $pmDate : $woDate) : ($pmDate ?? $woDate);
                $effectiveLastMaintenanceDate = $machine->last_maintenance_date ?? $fallbackLastDate;
            @endphp
            <div class="col-md-4">
                <h5 class="fw-bold text-dark mb-3">Maintenance & Health Summary</h5>
                <div class="d-flex flex-column gap-3 py-1 px-1">
                    <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                        <div>
                            <span class="text-muted fs-11 text-uppercase d-block fw-semibold">Last Maintenance</span>
                            <small class="text-muted fs-11">Previous service or overhaul</small>
                        </div>
                        <div class="text-end">
                            @if($effectiveLastMaintenanceDate)
                                @php
                                    $lastCarbon = is_string($effectiveLastMaintenanceDate) ? \Carbon\Carbon::parse($effectiveLastMaintenanceDate) : $effectiveLastMaintenanceDate;
                                @endphp
                                <span class="fs-14 fw-bold text-dark">
                                    {{ $lastCarbon->format('Y-m-d') }}
                                </span>
                                <small class="text-muted fs-11 d-block">{{ $lastCarbon->diffForHumans() }}</small>
                            @else
                                <span class="fs-14 fw-bold text-dark">No record</span>
                            @endif
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                        <div>
                            <span class="text-muted fs-11 text-uppercase d-block fw-semibold">Next Maintenance Due</span>
                            <small class="text-muted fs-11">Preventive schedule target</small>
                        </div>
                        <div class="text-end">
                            @if($effectiveNextMaintenanceDue)
                                @php
                                    $dueCarbon = is_string($effectiveNextMaintenanceDue) ? \Carbon\Carbon::parse($effectiveNextMaintenanceDue) : $effectiveNextMaintenanceDue;
                                    $isOverdue = $dueCarbon->isPast() && !$dueCarbon->isToday();
                                @endphp
                                <span class="fs-14 fw-bold {{ $isOverdue ? 'text-danger' : 'text-dark' }}">
                                    {{ $dueCarbon->format('Y-m-d') }}
                                </span>
                                @if($isOverdue)
                                    <span class="badge bg-soft-danger text-danger fs-10 d-block mt-0.5">Overdue ({{ $dueCarbon->diffForHumans() }})</span>
                                @elseif($dueCarbon->isToday())
                                    <span class="badge bg-soft-warning text-warning fs-10 d-block mt-0.5">Due Today</span>
                                @else
                                    <small class="text-muted fs-11 d-block">{{ $dueCarbon->diffForHumans() }}</small>
                                @endif
                                @if($earliestActivePm)
                                    <small class="text-muted fs-10 font-monospace d-block" title="{{ $earliestActivePm->name }}">{{ $earliestActivePm->code }}</small>
                                @endif
                            @else
                                <span class="fs-13 text-muted">Not scheduled</span>
                            @endif
                        </div>
                    </div>

                    <div class="p-2.5 rounded bg-light border d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-dark fw-bold fs-12 text-uppercase d-block">Total Maintenance Cost</span>
                            <small class="text-muted fs-11">Work orders expenditure</small>
                        </div>
                        <span class="fs-18 fw-bolder text-primary font-monospace">{{ format_currency($totalMaintenanceCost ?? 0) }}</span>
                    </div>

                    {{-- Maintenance Module Callout Card --}}
                    <div class="border rounded p-3 bg-soft-light mt-1">
                        <div class="d-flex align-items-start gap-2">
                            <i class="feather-info text-primary mt-1"></i>
                            <div>
                                <h6 class="fw-bold text-dark mb-1 fs-12">Centralized Maintenance Management</h6>
                                <p class="text-muted fs-11 mb-2">
                                    Breakdowns, corrective/preventive work orders, spare part assignments, and technician dispatches are managed in the Plant Maintenance module.
                                </p>
                                <a href="{{ route('production.maintenance.dashboard') }}" class="btn btn-sm btn-outline-danger fw-bold">
                                    <i class="feather-tool me-1"></i>Open Maintenance Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Machine State History --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">
                <i class="feather-bar-chart-2 me-2 text-primary"></i>{{ __('production.recent_machine_state_history') }}
            </h5>
            <span class="text-muted fs-12">Past 10 state changes</span>
        </div>
        @if($stateHistories->count() > 0)
            <div class="table-responsive mb-5 border rounded">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 20%">{{ __('production.status') }}</th>
                            <th style="width: 30%">{{ __('production.state_reason') }}</th>
                            <th style="width: 30%">{{ __('production.duration') ?? 'Duration' }}</th>
                            <th style="width: 20%">{{ __('production.changed_by') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stateHistories as $sh)
                            @php
                                $shColor = match($sh->state) {
                                    'Running' => 'text-success fw-bold',
                                    'Breakdown' => 'text-danger fw-bold',
                                    'Setup' => 'text-info fw-bold',
                                    'Waiting Material', 'Waiting Operator' => 'text-warning fw-bold',
                                    'Maintenance' => 'text-primary fw-bold',
                                    default => 'text-secondary',
                                };
                            @endphp
                            <tr>
                                <td class="fs-12 py-2"><span class="{{ $shColor }}">{{ $sh->state }}</span></td>
                                <td class="fs-12 py-2 text-muted fw-semibold">{{ $sh->reason ?: '—' }}</td>
                                <td class="fs-12 py-2">
                                    @if($sh->ended_at)
                                        {{ $sh->started_at->format('H:i') }} - {{ $sh->ended_at->format('H:i') }}
                                        <small class="text-muted d-block">
                                            @if($sh->duration_seconds >= 3600)
                                                {{ round($sh->duration_seconds / 3600, 1) }}h
                                            @elseif($sh->duration_seconds >= 60)
                                                {{ round($sh->duration_seconds / 60, 1) }}m
                                            @elseif($sh->duration_seconds > 0)
                                                {{ $sh->duration_seconds }}s
                                            @else
                                                < 1s
                                            @endif
                                        </small>
                                    @else
                                        <span class="badge bg-soft-success text-success fs-10">Active Since {{ $sh->started_at->format('H:i') }}</span>
                                    @endif
                                </td>
                                <td class="fs-12 py-2 text-dark">{{ $sh->changer->name ?? 'System' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        @else
            <div class="text-center py-4 text-muted fs-12 border rounded mb-5">
                <i class="feather-inbox me-2"></i>No state logs recorded yet.
            </div>
        @endif

        {{-- Downtime Logging History --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">
                <i class="feather-alert-octagon me-2 text-danger"></i>{{ __('production.downtime_logging_history') }}
            </h5>
            <span class="text-muted fs-12">Recorded breakdown & stoppage events</span>
        </div>
        @if($downtimes->count() > 0)
            <div class="table-responsive mb-5 border rounded">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 15%">{{ __('production.downtime_category') }}</th>
                            <th style="width: 25%">{{ __('production.state_reason') }}</th>
                            <th style="width: 20%">{{ __('production.active_period') }}</th>
                            <th style="width: 12%">{{ __('production.duration') ?? 'Duration' }}</th>
                            <th style="width: 15%">{{ __('production.logged_by') }}</th>
                            <th style="width: 13%">{{ __('production.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($downtimes as $dt)
                            <tr>
                                @php
                                    $catBadge = match(strtolower($dt->category ?? '')) {
                                        'breakdown', 'power failure', 'equipment failure', 'operator shortage' => 'badge bg-soft-danger text-danger',
                                        'material shortage', 'quality hold' => 'badge bg-soft-warning text-warning',
                                        'operator pause', 'setup', 'cleaning', 'tool change', 'calibration' => 'badge bg-soft-info text-info',
                                        default => 'badge bg-soft-secondary text-secondary',
                                    };
                                @endphp
                                <td class="fs-12 fw-bold"><span class="{{ $catBadge }} fs-11">{{ $dt->category }}</span></td>
                                <td class="fs-12 text-dark font-medium">
                                    {{ $dt->reason }}
                                    @if($dt->remarks)
                                        <small class="text-muted d-block font-normal mt-1">{{ $dt->remarks }}</small>
                                    @endif
                                </td>
                                <td class="fs-12 text-muted">
                                    {{ $dt->start_time->format('d/m H:i') }}
                                    @if($dt->end_time)
                                        &ndash; {{ $dt->end_time->format('H:i') }}
                                    @else
                                        &ndash; <span class="text-danger fw-bold">Present</span>
                                    @endif
                                </td>
                                <td class="fs-12 text-dark fw-bold">
                                    @if($dt->duration_minutes !== null)
                                        @php
                                            $totalSec = round($dt->duration_minutes * 60);
                                        @endphp
                                        @if($totalSec < 60 && $totalSec > 0)
                                            {{ $totalSec }}s
                                        @elseif($totalSec === 0.0 || $totalSec === 0)
                                            < 1s
                                        @else
                                            {{ number_format($dt->duration_minutes, 1) }} min
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="fs-12 text-muted">{{ $dt->creator->name ?? 'System' }}</td>
                                <td>
                                    @if($dt->status === 'closed')
                                        <span class="badge bg-soft-success text-success">Resolved</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger">Unresolved</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        @else
            <div class="text-center py-4 text-muted fs-12 border rounded mb-5">
                <i class="feather-check-square me-2 text-success"></i>No downtime events logged for this machine.
            </div>
        @endif

        {{-- Operation History --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">
                <i class="feather-clock me-2 text-info"></i>{{ __('production.recent_work_orders_executed') }}
            </h5>
            <span class="text-muted fs-12">Completed schedule operations</span>
        </div>
        @if($history->count() > 0)
            <div class="table-responsive mb-5 border rounded">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 20%">{{ __('production.col_operation') }}</th>
                            <th style="width: 20%">{{ __('production.col_product') }}</th>
                            <th style="width: 13%">{{ __('production.planned_start') }}</th>
                            <th style="width: 13%">{{ __('production.actual_start') ?? 'Actual Start' }}</th>
                            <th style="width: 13%">{{ __('production.planned_finish') }}</th>
                            <th style="width: 13%">{{ __('production.actual_finish') ?? 'Actual Finish' }}</th>
                            <th style="width: 10%">{{ __('production.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history as $op)
                            <tr>
                                <td class="fw-semibold text-dark fs-12">{{ $op->orderOperation->name ?? 'Op #'.$op->sequence }}</td>
                                <td>
                                    <div class="fw-semibold text-dark fs-12">{{ $op->order->product->name ?? '—' }}</div>
                                    <small class="text-muted">{{ $op->order->order_number ?? '' }}</small>
                                </td>
                                <td class="text-muted fs-12">{{ $op->planned_start->format('d/m H:i') }}</td>
                                <td class="fs-12 {{ $op->actual_start ? 'text-dark fw-semibold' : 'text-muted' }}">
                                    {{ $op->actual_start ? $op->actual_start->format('d/m H:i') : '—' }}
                                </td>
                                <td class="text-muted fs-12">{{ $op->planned_finish->format('d/m H:i') }}</td>
                                <td class="fs-12 {{ $op->actual_finish ? 'text-success fw-semibold' : 'text-muted' }}">
                                    {{ $op->actual_finish ? $op->actual_finish->format('d/m H:i') : '—' }}
                                </td>
                                <td>
                                    @if($op->status === 'completed')
                                        <span class="badge bg-soft-success text-success">Done</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger text-capitalize">{{ $op->status }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        @else
            <div class="text-center py-4 text-muted fs-13 border rounded mb-5">
                <i class="feather-inbox me-2"></i>No completed operations yet for this machine.
            </div>
        @endif

        {{-- Plant Maintenance Summary & Work Orders --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">
                <i class="feather-tool me-2 text-primary"></i>Plant Maintenance Work Orders
            </h5>
            <span class="fs-12 text-muted fw-bold">Total Cost: <strong class="text-primary fs-14">{{ format_currency($totalMaintenanceCost ?? 0) }}</strong></span>
        </div>
        @if(isset($maintenanceWorkOrders) && $maintenanceWorkOrders->count() > 0)
            <div class="table-responsive mb-5 border rounded">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th>WO Number</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Technician</th>
                            <th>Status</th>
                            <th class="text-end">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($maintenanceWorkOrders as $mwo)
                            <tr>
                                <td>
                                    <a href="{{ route('production.maintenance.work-orders.show', $mwo->id) }}" class="fw-bold text-primary text-decoration-none">
                                        {{ $mwo->work_order_number }}
                                    </a>
                                </td>
                                <td><span class="badge bg-soft-info text-info">{{ ucfirst($mwo->type) }}</span></td>
                                <td><span class="badge bg-soft-secondary text-dark">{{ ucfirst($mwo->priority) }}</span></td>
                                <td>{{ $mwo->technician?->name ?? 'Unassigned' }}</td>
                                <td>
                                    <span class="badge bg-soft-{{ $mwo->status === 'completed' ? 'success' : ($mwo->status === 'in_progress' ? 'warning' : 'secondary') }} text-dark">
                                        {{ ucfirst(str_replace('_', ' ', $mwo->status)) }}
                                    </span>
                                </td>
                                <td class="text-end fw-bold">{{ format_currency($mwo->total_cost) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        @else
            <div class="text-center py-4 text-muted fs-13 border rounded mb-5">
                <i class="feather-tool me-2 text-muted"></i>No maintenance work orders recorded for this machine.
            </div>
        @endif

        {{-- Preventive Maintenance Schedules --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">
                <i class="feather-calendar me-2 text-success"></i>Preventive Maintenance (PM) Schedules
            </h5>
            <span class="text-muted fs-12">Recurring servicing plans</span>
        </div>
        @if(isset($pmSchedules) && $pmSchedules->count() > 0)
            <div class="table-responsive border rounded">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th>Schedule Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Frequency</th>
                            <th>Last Completed</th>
                            <th>Next Due</th>
                            <th>Priority</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pmSchedules as $pm)
                            <tr>
                                <td class="fw-bold font-monospace fs-12 text-dark">{{ $pm->code }}</td>
                                <td class="fw-semibold text-dark fs-12">{{ $pm->name }}</td>
                                <td><span class="badge bg-soft-info text-info text-capitalize">{{ $pm->maintenance_type }}</span></td>
                                <td class="fs-12 text-muted">Every {{ $pm->frequency_value }} {{ $pm->frequency_type }}</td>
                                <td class="fs-12 text-muted">{{ $pm->last_completed_date ? $pm->last_completed_date->format('Y-m-d') : 'Never' }}</td>
                                <td class="fs-12">
                                    @if($pm->next_due_date)
                                        <span class="{{ $pm->next_due_date->isPast() ? 'text-danger fw-bold' : 'text-dark' }}">
                                            {{ $pm->next_due_date->format('Y-m-d') }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-soft-secondary text-dark text-capitalize">{{ $pm->priority }}</span></td>
                                <td>
                                    @if($pm->is_active)
                                        <span class="badge bg-soft-success text-success">Active</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary">Inactive</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        @else
            <div class="text-center py-4 text-muted fs-13 border rounded">
                <i class="feather-calendar me-2 text-muted"></i>No preventive maintenance schedules assigned to this machine.
            </div>
        @endif

    </div>
@endsection
