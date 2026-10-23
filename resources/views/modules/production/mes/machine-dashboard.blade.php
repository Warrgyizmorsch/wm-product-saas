@extends('layouts.duralux')

@section('title', __('production.machine_dashboard') . ' | SaaS ERP')
@section('page-title', __('production.machine_status_dashboard'))
@section('breadcrumb', __('production.machines'))

@section('page-actions')
    <a href="{{ route('production.mes.dashboard') }}" class="btn btn-secondary me-2">
        <i class="feather-monitor me-2"></i>{{ __('production.operator_dashboard') }}
    </a>
    <a href="{{ route('production.mes.work-centers.index') }}" class="btn btn-light">
        <i class="feather-settings me-2"></i>{{ __('production.col_work_center') }}
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">

        @if($machines->count() === 0)
            <div class="text-center py-5 text-muted">
                <i class="feather-cpu fs-36 mb-3 d-block"></i>
                <p class="fs-14">No machines configured for this tenant.</p>
            </div>
        @else
            <div class="row g-3">
                @foreach($machines as $machine)
                    @php
                        $isBreakdown = (
                            $machine->current_state === 'Breakdown'
                            || $machine->maintenance_status === 'breakdown'
                            || ($machine->activeMaintenanceWo && $machine->activeMaintenanceWo->type === \App\Domains\Production\Models\ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN)
                            || ($machine->activeDowntime && $machine->activeDowntime->category === 'Breakdown')
                        );

                        $isMaintenance = !$isBreakdown && (
                            $machine->status === \App\Domains\Production\Models\Machine::STATUS_UNDER_MAINTENANCE
                            || $machine->current_state === 'Maintenance'
                            || $machine->activeMaintenanceWo !== null
                        );

                        $isDecommissioned = (
                            $machine->status === \App\Domains\Production\Models\Machine::STATUS_DECOMMISSIONED
                            || $machine->current_state === 'Decommissioned'
                        );

                        $isRunning = !$isBreakdown && !$isMaintenance && !$isDecommissioned && (
                            $machine->currentOp !== null
                            || $machine->current_state === 'Running'
                        );

                        $isInactive = !$isBreakdown && !$isMaintenance && !$isDecommissioned && !$isRunning && (
                            $machine->status === \App\Domains\Production\Models\Machine::STATUS_INACTIVE
                            || $machine->current_state === 'Inactive'
                        );

                        if ($isBreakdown) {
                            $avatarClass = 'bg-soft-danger text-danger';
                            $avatarIcon = 'alert-octagon';
                        } elseif ($isMaintenance) {
                            $avatarClass = 'bg-soft-warning text-warning';
                            $avatarIcon = 'tool';
                        } elseif ($isDecommissioned) {
                            $avatarClass = 'bg-soft-secondary text-secondary';
                            $avatarIcon = 'slash';
                        } elseif ($isRunning) {
                            $avatarClass = 'bg-soft-warning text-warning';
                            $avatarIcon = 'play-circle';
                        } elseif ($isInactive) {
                            $avatarClass = 'bg-soft-secondary text-secondary';
                            $avatarIcon = 'pause-circle';
                        } else {
                            $avatarClass = 'bg-soft-success text-success';
                            $avatarIcon = 'cpu';
                        }
                    @endphp
                    <div class="col-md-4">
                        <x-ui.card class="border-0 shadow-sm h-100 touch-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-text avatar-md {{ $avatarClass }} rounded">
                                        <i class="feather-{{ $avatarIcon }}"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0">{{ $machine->name }}</h6>
                                        <small class="text-muted">{{ $machine->workCenter->name ?? '—' }} ({{ $machine->code }})</small>
                                    </div>
                                </div>
                                @if($isBreakdown)
                                    <span class="badge bg-soft-danger text-danger fw-bold">
                                        <i class="feather-alert-octagon me-1"></i>Breakdown
                                    </span>
                                @elseif($isMaintenance)
                                    <span class="badge bg-soft-warning text-warning fw-bold">
                                        <i class="feather-tool me-1"></i>Under Maintenance
                                    </span>
                                @elseif($isDecommissioned)
                                    <span class="badge bg-soft-secondary text-secondary fw-bold">
                                        <i class="feather-slash me-1"></i>Decommissioned
                                    </span>
                                @elseif($isRunning)
                                    <span class="badge bg-soft-warning text-warning fw-bold">
                                        <i class="feather-play-circle me-1"></i>{{ __('production.running') }}
                                    </span>
                                @elseif($isInactive)
                                    <span class="badge bg-soft-secondary text-secondary fw-bold">
                                        <i class="feather-pause-circle me-1"></i>Inactive
                                    </span>
                                @else
                                    <span class="badge bg-soft-success text-success fw-bold">Active / Ready</span>
                                @endif
                            </div>

                            @if($isBreakdown)
                                <div class="border rounded p-2 bg-soft-danger mb-3">
                                    <div class="fs-12 fw-bold text-danger mb-1 d-flex justify-content-between align-items-center">
                                        <span><i class="feather-alert-octagon me-1"></i>Breakdown Event</span>
                                        @if($machine->activeMaintenanceWo)
                                            <span class="badge bg-danger text-white text-uppercase" style="font-size: 10px;">{{ $machine->activeMaintenanceWo->status }}</span>
                                        @endif
                                    </div>
                                    @if($machine->activeMaintenanceWo)
                                        <div class="fs-12 text-dark fw-semibold">
                                            <a href="{{ route('production.maintenance.work-orders.show', $machine->activeMaintenanceWo->id) }}" class="text-danger fw-bold text-decoration-none">
                                                {{ $machine->activeMaintenanceWo->work_order_number }}
                                            </a>
                                        </div>
                                        <div class="fs-11 text-muted">{{ Str::limit($machine->activeMaintenanceWo->problem_description ?: ($machine->current_state_reason ?: 'Machine reported in breakdown state'), 60) }}</div>
                                    @elseif($machine->current_state_reason)
                                        <div class="fs-11 text-danger fw-semibold">{{ Str::limit($machine->current_state_reason, 60) }}</div>
                                    @else
                                        <div class="fs-11 text-muted">Machine reported in breakdown state</div>
                                    @endif
                                </div>
                            @elseif($isMaintenance)
                                <div class="border rounded p-2 bg-soft-warning mb-3">
                                    <div class="fs-12 fw-bold text-warning mb-1 d-flex justify-content-between align-items-center">
                                        <span><i class="feather-tool me-1"></i>Maintenance Work Order</span>
                                        @if($machine->activeMaintenanceWo)
                                            <span class="badge bg-warning text-dark text-uppercase" style="font-size: 10px;">{{ $machine->activeMaintenanceWo->status }}</span>
                                        @endif
                                    </div>
                                    @if($machine->activeMaintenanceWo)
                                        <div class="fs-12 text-dark fw-semibold">
                                            <a href="{{ route('production.maintenance.work-orders.show', $machine->activeMaintenanceWo->id) }}" class="text-warning fw-bold text-decoration-none">
                                                {{ $machine->activeMaintenanceWo->work_order_number }}
                                            </a>
                                        </div>
                                        <div class="fs-11 text-muted">{{ Str::limit($machine->activeMaintenanceWo->problem_description ?: 'Machine undergoing maintenance', 60) }}</div>
                                    @else
                                        <div class="fs-11 text-muted">Machine undergoing maintenance</div>
                                    @endif
                                </div>
                            @elseif($isDecommissioned)
                                <div class="border rounded p-2 bg-soft-secondary mb-3">
                                    <div class="fs-12 fw-bold text-secondary mb-1">
                                        <i class="feather-slash me-1"></i>Decommissioned
                                    </div>
                                    <div class="fs-11 text-muted">{{ $machine->current_state_reason ?: 'Machine decommissioned / scrapped' }}</div>
                                </div>
                            @elseif($isRunning && $machine->currentOp)
                                <div class="border rounded p-2 bg-soft-warning mb-3">
                                    <div class="fs-12 fw-bold text-warning mb-1">
                                        <i class="feather-play-circle me-1"></i>{{ __('production.current_operation') }}
                                    </div>
                                    <div class="fs-12 text-dark fw-semibold">{{ $machine->currentOp->orderOperation->name ?? '—' }}</div>
                                    <div class="fs-11 text-muted">{{ $machine->currentOp->order->product->name ?? '' }}</div>
                                    @if($machine->currentOp->actual_start)
                                        <div class="fs-11 text-muted mt-1">
                                            Since {{ $machine->currentOp->actual_start->format('H:i') }} · {{ $machine->currentOp->actual_start->diffForHumans(null, true) }} ago
                                        </div>
                                    @endif
                                </div>
                            @elseif($isInactive && $machine->current_state_reason)
                                <div class="border rounded p-2 bg-light mb-3">
                                    <div class="fs-12 fw-semibold text-muted mb-1">
                                        <i class="feather-pause-circle me-1"></i>Inactive Reason
                                    </div>
                                    <div class="fs-11 text-muted">{{ Str::limit($machine->current_state_reason, 60) }}</div>
                                </div>
                            @endif

                            <a href="{{ route('production.mes.machines.show', $machine->id) }}" class="btn btn-sm btn-outline-primary w-100">
                                <i class="feather-bar-chart-2 me-1"></i>{{ __('production.view_details') ?? 'View Details' }}
                            </a>
                        </x-ui.card>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
