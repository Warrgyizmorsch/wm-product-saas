@extends('layouts.duralux')

@section('title', 'Machine Dashboard | SaaS ERP')
@section('page-title', 'Machine Status Dashboard')
@section('breadcrumb', 'Machines')

@section('page-actions')
    <a href="{{ route('production.mes.dashboard') }}" class="btn btn-secondary me-2">
        <i class="feather-monitor me-2"></i>Operator Dashboard
    </a>
    <a href="{{ route('production.mes.work-centers.index') }}" class="btn btn-light">
        <i class="feather-settings me-2"></i>Work Centers
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        @if($machines->count() === 0)
            <div class="text-center py-5 text-muted">
                <i class="feather-cpu fs-36 mb-3 d-block"></i>
                <p class="fs-14">No active machines configured.</p>
            </div>
        @else
            <div class="row g-3">
                @foreach($machines as $machine)
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-text avatar-md {{ $machine->currentOp ? 'bg-soft-warning text-warning' : 'bg-soft-secondary text-secondary' }} rounded">
                                            <i class="feather-cpu"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0">{{ $machine->name }}</h6>
                                            <small class="text-muted">{{ $machine->workCenter->name ?? '—' }}</small>
                                        </div>
                                    </div>
                                    @if($machine->currentOp)
                                        <span class="badge bg-soft-warning text-warning">Running</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary">Idle</span>
                                    @endif
                                </div>

                                @if($machine->currentOp)
                                    <div class="border rounded p-2 bg-soft-warning mb-3">
                                        <div class="fs-12 fw-bold text-warning mb-1">
                                            <i class="feather-play-circle me-1"></i>Current Operation
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
                                    <i class="feather-bar-chart-2 me-1"></i>View Details
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
