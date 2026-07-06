@extends('layouts.duralux')

@section('title', 'Work Center Dashboard | SaaS ERP')
@section('page-title', 'Work Center Status Dashboard')
@section('breadcrumb', 'Work Centers')

@section('page-actions')
    <a href="{{ route('production.mes.dashboard') }}" class="btn btn-secondary me-2">
        <i class="feather-monitor me-2"></i>Operator Dashboard
    </a>
    <a href="{{ route('production.mes.machines.index') }}" class="btn btn-light">
        <i class="feather-cpu me-2"></i>Machines
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        @if($workCenters->count() === 0)
            <div class="text-center py-5 text-muted">
                <i class="feather-settings fs-36 mb-3 d-block"></i>
                <p class="fs-14">No active Work Centers configured.</p>
            </div>
        @else
            <div class="row g-3">
                @foreach($workCenters as $wc)
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-text avatar-md {{ $wc->runningCount > 0 ? 'bg-soft-warning text-warning' : 'bg-soft-secondary text-secondary' }} rounded">
                                            <i class="feather-settings"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0">{{ $wc->name }}</h6>
                                            <small class="text-muted">{{ $wc->code ?? '' }} · {{ $wc->machines->count() }} machine(s)</small>
                                        </div>
                                    </div>
                                    @if($wc->runningCount > 0)
                                        <span class="badge bg-soft-warning text-warning">Active</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary">Idle</span>
                                    @endif
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-4 text-center">
                                        <div class="fw-bold text-warning fs-16">{{ $wc->runningCount }}</div>
                                        <div class="fs-10 text-muted text-uppercase">Running</div>
                                    </div>
                                    <div class="col-4 text-center">
                                        <div class="fw-bold text-info fs-16">{{ $wc->waitingCount }}</div>
                                        <div class="fs-10 text-muted text-uppercase">In Queue</div>
                                    </div>
                                    <div class="col-4 text-center">
                                        <div class="fw-bold text-success fs-16">{{ $wc->completedToday }}</div>
                                        <div class="fs-10 text-muted text-uppercase">Done Today</div>
                                    </div>
                                </div>

                                <a href="{{ route('production.mes.work-centers.show', $wc->id) }}" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="feather-list me-1"></i>View Queue
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
