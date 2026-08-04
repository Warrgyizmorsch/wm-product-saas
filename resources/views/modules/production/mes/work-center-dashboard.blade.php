@extends('layouts.duralux')

@section('title', __('production.work_center_dashboard') . ' | SaaS ERP')
@section('page-title', __('production.work_center_status_dashboard'))
@section('breadcrumb', __('production.col_work_center'))

@section('page-actions')
    <a href="{{ route('production.mes.dashboard') }}" class="btn btn-secondary me-2">
        <i class="feather-monitor me-2"></i>{{ __('production.operator_dashboard') }}
    </a>
    <a href="{{ route('production.mes.machines.index') }}" class="btn btn-light">
        <i class="feather-cpu me-2"></i>{{ __('production.machines') }}
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">

        @if($workCenters->count() === 0)
            <div class="text-center py-5 text-muted">
                <i class="feather-settings fs-36 mb-3 d-block"></i>
                <p class="fs-14">No active Work Centers configured.</p>
            </div>
        @else
            <div class="row g-3">
                @foreach($workCenters as $wc)
                    <div class="col-md-4">
                        <x-ui.card class="border-0 shadow-sm h-100 touch-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-text avatar-md {{ $wc->runningCount > 0 ? 'bg-soft-warning text-warning' : 'bg-soft-secondary text-secondary' }} rounded">
                                        <i class="feather-settings"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0">{{ $wc->name }}</h6>
                                        <small class="text-muted">{{ $wc->code ?? '' }} · {{ $wc->machines->count() }} {{ strtolower(__('production.machines')) }}</small>
                                    </div>
                                </div>
                                @if($wc->runningCount > 0)
                                    <span class="badge bg-soft-warning text-warning">{{ __('production.status_active') }}</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary">{{ __('production.status_disabled') }}</span>
                                @endif
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-4 text-center">
                                    <div class="fw-bold text-warning fs-16">{{ $wc->runningCount }}</div>
                                    <div class="fs-10 text-muted text-uppercase">{{ __('production.running') }}</div>
                                </div>
                                <div class="col-4 text-center">
                                    <div class="fw-bold text-info fs-16">{{ $wc->waitingCount }}</div>
                                    <div class="fs-10 text-muted text-uppercase">{{ __('production.in_queue') }}</div>
                                </div>
                                <div class="col-4 text-center">
                                    <div class="fw-bold text-success fs-16">{{ $wc->completedToday }}</div>
                                    <div class="fs-10 text-muted text-uppercase">{{ __('production.done_today') }}</div>
                                </div>
                            </div>

                            <a href="{{ route('production.mes.work-centers.show', $wc->id) }}" class="btn btn-sm btn-outline-primary w-100">
                                <i class="feather-list me-1"></i>{{ __('production.view_queue') }}
                            </a>
                        </x-ui.card>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
