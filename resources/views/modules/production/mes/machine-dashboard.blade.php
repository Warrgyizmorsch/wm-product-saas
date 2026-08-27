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
                    <div class="col-md-4">
                        <x-ui.card class="border-0 shadow-sm h-100 touch-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-text avatar-md {{ $machine->status === 'under_maintenance' ? 'bg-soft-danger text-danger' : ($machine->currentOp ? 'bg-soft-warning text-warning' : 'bg-soft-success text-success') }} rounded">
                                        <i class="feather-{{ $machine->status === 'under_maintenance' ? 'tool' : 'cpu' }}"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0">{{ $machine->name }}</h6>
                                        <small class="text-muted">{{ $machine->workCenter->name ?? '—' }} ({{ $machine->code }})</small>
                                    </div>
                                </div>
                                @if($machine->status === 'under_maintenance')
                                    <span class="badge bg-soft-danger text-danger fw-bold">
                                        <i class="feather-alert-octagon me-1"></i>{{ $machine->maintenance_status === 'breakdown' ? 'Breakdown' : 'Under Maintenance' }}
                                    </span>
                                @elseif($machine->currentOp)
                                    <span class="badge bg-soft-warning text-warning fw-bold">{{ __('production.running') }}</span>
                                @else
                                    <span class="badge bg-soft-success text-success fw-bold">Active / Ready</span>
                                @endif
                            </div>

                            @if($machine->status === 'under_maintenance')
                                <div class="border rounded p-2 bg-soft-danger mb-3">
                                    <div class="fs-12 fw-bold text-danger mb-1">
                                        <i class="feather-tool me-1"></i>Maintenance Work Order
                                    </div>
                                    @if($machine->activeMaintenanceWo)
                                        <div class="fs-12 text-dark fw-semibold">
                                            <a href="{{ route('production.maintenance.work-orders.show', $machine->activeMaintenanceWo->id) }}" class="text-danger fw-bold">
                                                {{ $machine->activeMaintenanceWo->work_order_number }}
                                            </a>
                                        </div>
                                        <div class="fs-11 text-muted">{{ Str::limit($machine->activeMaintenanceWo->problem_description, 60) }}</div>
                                    @else
                                        <div class="fs-11 text-muted">Machine undergoing maintenance</div>
                                    @endif
                                </div>
                            @elseif($machine->currentOp)
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
