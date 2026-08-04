@extends('layouts.duralux')

@section('title', __('production.work_center_queue') . ' | SaaS ERP')
@section('page-title', $workCenter->name . ' — ' . __('production.ordered_execution_queue'))
@section('breadcrumb', __('production.work_center_queue'))

@section('page-actions')
    <a href="{{ route('production.mes.work-centers.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>{{ __('production.col_work_center') }}
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-text avatar-xl bg-soft-primary text-primary rounded">
                    <i class="feather-settings fs-22"></i>
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-0">{{ $workCenter->name }}</h4>
                    <div class="text-muted fs-13">{{ $workCenter->code ?? '' }}</div>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <x-ui.card class="border-0 shadow-sm text-center py-1">
                    <div class="fs-22 fw-bold text-warning">{{ $queue->where('status', 'running')->count() }}</div>
                    <div class="fs-11 text-muted text-uppercase">{{ __('production.running_jobs') }}</div>
                </x-ui.card>
            </div>
            <div class="col-md-3">
                <x-ui.card class="border-0 shadow-sm text-center py-1">
                    <div class="fs-22 fw-bold text-info">{{ $queue->where('status', 'ready')->count() + $queue->where('status', 'waiting')->count() }}</div>
                    <div class="fs-11 text-muted text-uppercase">{{ __('production.queued') }}</div>
                </x-ui.card>
            </div>
            <div class="col-md-3">
                <x-ui.card class="border-0 shadow-sm text-center py-1">
                    <div class="fs-22 fw-bold text-success">{{ $completedToday }}</div>
                    <div class="fs-11 text-muted text-uppercase">{{ __('production.done_today') }}</div>
                </x-ui.card>
            </div>
            <div class="col-md-3">
                <x-ui.card class="border-0 shadow-sm text-center py-1">
                    <div class="fs-22 fw-bold text-primary">{{ $utilization }}%</div>
                    <div class="fs-11 text-muted text-uppercase">{{ __('production.utilization') }}</div>
                </x-ui.card>
            </div>
        </div>

        {{-- Ordered Execution Queue --}}
        <h5 class="fw-bold text-dark mb-3">
            <i class="feather-list me-2"></i>{{ __('production.ordered_execution_queue') }}
        </h5>

        @if($queue->count() > 0)
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 5%" class="text-center">#</th>
                            <th style="width: 15%">{{ __('production.order_hash') }}</th>
                            <th style="width: 20%">{{ __('production.col_product') }}</th>
                            <th style="width: 18%">{{ __('production.col_operation') }}</th>
                            <th style="width: 10%">{{ __('production.col_machine') }}</th>
                            <th style="width: 12%">{{ __('production.planned_start') }}</th>
                            <th style="width: 12%">{{ __('production.planned_finish') }}</th>
                            <th style="width: 10%">{{ __('production.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($queue as $position => $op)
                            @php
                                $statusKey = 'production.' . $op->status;
                                $translatedStatus = __($statusKey) != $statusKey ? __($statusKey) : $op->status;
                            @endphp
                            <tr class="{{ $op->status === 'running' ? 'table-warning' : '' }}">
                                <td class="fw-bold text-center align-middle">{{ $position + 1 }}</td>
                                <td class="align-middle">
                                    <a href="{{ route('production.schedules.show', $op->production_schedule_id) }}" class="fw-semibold text-primary fs-12">
                                        {{ $op->order->order_number ?? '—' }}
                                    </a>
                                </td>
                                <td class="align-middle">
                                    <div class="fw-semibold text-dark fs-12">{{ $op->order->product->name ?? '—' }}</div>
                                </td>
                                <td class="align-middle">
                                    <span class="fw-semibold text-dark fs-12">{{ $op->orderOperation->name ?? '—' }}</span>
                                    <br><small class="text-muted font-monospace">{{ $op->orderOperation->operation_number ?? '' }}</small>
                                </td>
                                <td class="align-middle text-muted fs-12">{{ $op->machine->name ?? '—' }}</td>
                                <td class="align-middle fs-12 text-muted">{{ $op->planned_start->format('d/m H:i') }}</td>
                                <td class="align-middle fs-12 text-muted">{{ $op->planned_finish->format('d/m H:i') }}</td>
                                <td class="align-middle">
                                    @if($op->status === 'running')
                                        <span class="badge bg-soft-warning text-warning">{{ $translatedStatus }}</span>
                                    @elseif($op->status === 'ready')
                                        <span class="badge bg-soft-info text-info">{{ $translatedStatus }}</span>
                                    @elseif($op->status === 'waiting')
                                        <span class="erp-badge-draft">{{ $translatedStatus }}</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary text-capitalize">{{ $translatedStatus }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        @else
            <div class="text-center py-4 text-muted fs-13">
                <i class="feather-inbox me-2 fs-16"></i>No operations currently queued for this Work Center.
            </div>
        @endif

        {{-- Utilization Bar --}}
        <div class="mt-4 p-3 bg-light rounded">
            <div class="d-flex justify-content-between mb-2">
                <div>
                    <span class="fw-semibold text-dark fs-12">{{ __('production.actual_runtime_utilization_today') }}</span>
                    <span class="badge bg-soft-info text-info fs-11 ms-2">{{ __('production.realtime_mes') }}</span>
                </div>
                <span class="fw-bold text-primary fs-12">
                    {{ $actualUtilization }}% ({{ number_format($actualMinutesToday, 1) }} / {{ number_format($availableMinutes, 0) }} min)
                </span>
            </div>
            <div class="progress mb-2" style="height: 8px;">
                <div class="progress-bar {{ $actualUtilization > 90 ? 'bg-danger' : ($actualUtilization > 70 ? 'bg-warning' : 'bg-success') }}"
                     role="progressbar"
                     style="width: {{ max(2, $actualUtilization) }}%"
                     aria-valuenow="{{ $actualUtilization }}"
                     aria-valuemin="0"
                     aria-valuemax="100">
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center text-muted fs-11 mt-2 border-top pt-2">
                <span>
                    <i class="feather-calendar me-1"></i>
                    <strong>{{ __('production.planned_shift_commitment') }}:</strong> {{ $plannedUtilization }}% ({{ number_format($plannedMinutesToday, 0) }} min allocated)
                    @if($totalQueueMinutes > 0)
                        &middot; <span class="font-monospace">{{ __('production.total_queue_backlog') }}: {{ number_format($totalQueueMinutes, 0) }} min</span>
                    @endif
                </span>
                <span>
                    @if($shifts->isNotEmpty())
                        Based on active shift{{ $shifts->count() > 1 ? 's' : '' }}:
                        @foreach($shifts as $index => $shift)
                            <strong>{{ $shift->name }}</strong> ({{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }}{{ $shift->break_minutes > 0 ? ', break: ' . $shift->break_minutes . 'm' : '' }}){{ $index < $shifts->count() - 1 ? ', ' : '' }}
                        @endforeach
                        &middot; {{ $workCenter->efficiency_percentage }}% efficiency across {{ $workCenter->machines()->where('status', \App\Domains\Production\Models\Machine::STATUS_ACTIVE)->count() ?: 1 }} active machine(s).
                    @else
                        Standard Shift (8h) &middot; {{ $workCenter->efficiency_percentage }}% efficiency.
                    @endif
                </span>
            </div>
        </div>
    </div>
@endsection
