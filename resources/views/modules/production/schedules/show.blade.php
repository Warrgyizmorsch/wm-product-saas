@extends('layouts.duralux')

@section('title', __('production.schedule_details', ['number' => $schedule->schedule_number]) . ' | SaaS ERP')

@section('page-actions')
    <a href="{{ route('production.schedules.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>{{ __('production.back_to_list') }}
    </a>

    @if($schedule->isScheduled())
        <form method="POST" action="{{ route('production.schedules.release', $schedule->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="feather-play-circle me-2"></i>{{ __('production.release_to_shop_floor') }}
            </button>
        </form>
    @endif

    @if(!$schedule->isFrozen())
        <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#cancelModal">
            <i class="feather-slash me-2"></i>{{ __('production.cancel_schedule') }}
        </button>
    @endif
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <h4 class="fw-bold text-dark mb-0">
                {{ __('production.schedule_details', ['number' => $schedule->schedule_number]) }}
            </h4>
            <div>
                @if($schedule->status === 'released')
                    <span class="erp-badge-active">{{ __('production.released_schedules') }}</span>
                @elseif($schedule->status === 'in_progress')
                    <span class="badge bg-soft-warning text-warning">{{ __('production.in_progress_schedules') }}</span>
                @elseif($schedule->status === 'scheduled')
                    <span class="badge bg-soft-info text-info">{{ __('production.scheduled_schedules') }}</span>
                @elseif($schedule->status === 'draft')
                    <span class="erp-badge-draft">{{ __('production.draft_schedules') }}</span>
                @elseif($schedule->status === 'completed')
                    <span class="badge bg-soft-success text-success">{{ __('production.completed_schedules') }}</span>
                @elseif($schedule->status === 'cancelled')
                    <span class="badge bg-soft-danger text-danger">{{ __('production.cancelled_schedules') }}</span>
                @endif
            </div>
        </div>

        {{-- Summary Cards --}}
        @php
            $totalOps     = $schedule->operations->count();
            $completedOps = $schedule->operations->where('status', 'completed')->count();
            $runningOps   = $schedule->operations->where('status', 'running')->count();
            $remainingOps = $totalOps - $completedOps;
        @endphp
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-dark">{{ $totalOps }}</div>
                        <div class="fs-11 text-muted text-uppercase">{{ __('production.total_operations') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-success">{{ $completedOps }}</div>
                        <div class="fs-11 text-muted text-uppercase">{{ __('production.completed_schedules') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-warning">{{ $runningOps }}</div>
                        <div class="fs-11 text-muted text-uppercase">{{ __('production.running') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-info">{{ $remainingOps }}</div>
                        <div class="fs-11 text-muted text-uppercase">{{ __('production.remaining') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Info Grid --}}
        <div class="row g-4 mb-4">
            <div class="col-md-6 border-end">
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.production_order') }}:</span></div>
                    <div class="col-md-8">
                        <a href="{{ route('production.orders.show', $schedule->production_order_id) }}" class="fw-bold text-primary">
                            {{ $schedule->order->order_number ?? '—' }}
                        </a>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.product') }}:</span></div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">{{ $schedule->order->product->name ?? '—' }}</span>
                        <small class="text-muted ms-2 font-monospace">{{ $schedule->order->product->sku ?? '' }}</small>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.scheduling_type') }}:</span></div>
                    <div class="col-md-8">
                        <span class="badge bg-soft-info text-info text-capitalize">
                            {{ __('production.' . $schedule->scheduling_type) ?? $schedule->scheduling_type }}
                        </span>
                    </div>
                </div>
                @if($schedule->notes)
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.description') ?? 'Notes' }}:</span></div>
                        <div class="col-md-8"><span class="text-dark fs-13">{{ $schedule->notes }}</span></div>
                    </div>
                @endif
            </div>
            <div class="col-md-6">
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.created_by') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $schedule->creator->name ?? '—' }}</span></div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.scheduled_schedules') }} At:</span></div>
                    <div class="col-md-8"><span class="text-dark fs-13">{{ $schedule->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.source_item') ?? 'Source' }}:</span></div>
                    <div class="col-md-8">
                        <span class="badge bg-soft-secondary text-secondary text-uppercase">{{ $schedule->generated_by ?? 'forward' }}</span>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.capacity_utilization') }}:</span></div>
                    <div class="col-md-8">
                        <span class="fw-bold {{ ($schedule->capacity_utilization ?? 0) > 85 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($schedule->capacity_utilization ?? 0, 2) }}%
                        </span>
                    </div>
                </div>
                @if($schedule->released_at)
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.released_schedules') }}:</span></div>
                        <div class="col-md-8"><span class="text-dark fs-13">{{ $schedule->released_at->format('d/m/Y H:i') }} by {{ $schedule->releasedBy->name ?? '—' }}</span></div>
                    </div>
                @endif
                @if($schedule->completed_at)
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.completed_schedules') }}:</span></div>
                        <div class="col-md-8"><span class="text-success fw-bold fs-13">{{ $schedule->completed_at->format('d/m/Y H:i') }}</span></div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Capacity Warnings --}}
        @if(count($warnings) > 0)
            <div class="alert alert-warning border-warning bg-soft-warning p-3 rounded mb-4">
                <div class="fw-bold text-warning mb-2"><i class="feather-alert-triangle me-2"></i>{{ __('production.capacity_overload_warnings') }} ({{ count($warnings) }})</div>
                @foreach($warnings as $warning)
                    <div class="fs-12 text-warning-800 mb-1">• {{ $warning }}</div>
                @endforeach
            </div>
        @endif

        {{-- Tabs --}}
        <x-ui.horizontal-tabs id="scheduleTabs" :tabs="[
            ['id' => 'tab-operations', 'label' => __('production.schedule_operations'), 'active' => true, 'icon' => 'feather-list'],
            ['id' => 'tab-capacity', 'label' => __('production.capacity_analysis'), 'active' => false, 'icon' => 'feather-activity'],
        ]" />

        <div class="tab-content mt-3">
            <div class="tab-pane fade show active" id="tab-operations" role="tabpanel">
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th style="width: 5%" class="text-center">{{ __('production.seq') }}</th>
                                <th style="width: 15%">{{ __('production.operations') }}</th>
                                <th style="width: 12%">{{ __('production.work_centers') }}</th>
                                <th style="width: 12%">{{ __('production.planned_machine') ?? 'Planned Machine' }}</th>
                                <th style="width: 12%">{{ __('production.actual_machine') }}</th>
                                <th style="width: 8%">{{ __('production.status') }}</th>
                                <th style="width: 10%">{{ __('production.duration') }}</th>
                                <th style="width: 10%">{{ __('production.planned_start') }}</th>
                                <th style="width: 10%">{{ __('production.planned_finish') }}</th>
                                <th style="width: 13%">{{ __('production.gantt_config') }}</th>
                                <th style="width: 5%" class="text-center">{{ __('production.lock') }}</th>
                                <th style="width: 8%">{{ __('production.warnings') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schedule->operations as $op)
                                <tr class="{{ $op->locked ? 'bg-light' : '' }}">
                                    <td class="fw-bold text-center align-middle">{{ $op->sequence }}</td>
                                    <td class="align-middle">
                                        <span class="fw-semibold text-dark">{{ $op->orderOperation->name ?? '—' }}</span>
                                        <br><small class="text-muted font-monospace">{{ $op->orderOperation->operation_number ?? '' }}</small>
                                    </td>
                                    <td class="align-middle">{{ $op->workCenter->name ?? '—' }}</td>
                                    <td class="align-middle text-muted">
                                        {{ $op->machine->name ?? '—' }}
                                        @if(($op->priority ?? 1) > 1)
                                            <span class="badge bg-soft-warning text-warning d-block mt-1 text-center" style="width: max-content">Alt Priority {{ $op->priority }}</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-muted">
                                        {{ $op->actualMachine->name ?? '—' }}
                                    </td>
                                    <td class="align-middle">
                                        @if($op->status === 'completed')
                                            <span class="badge bg-soft-success text-success">{{ __('production.completed') }}</span>
                                        @elseif($op->status === 'running')
                                            <span class="badge bg-soft-warning text-warning">{{ __('production.running') }}</span>
                                        @elseif($op->status === 'ready')
                                            <span class="badge bg-soft-info text-info">{{ __('production.ready') }}</span>
                                        @elseif($op->status === 'paused')
                                            <span class="badge bg-soft-warning text-warning">{{ __('production.paused') ?? 'Paused' }}</span>
                                        @elseif($op->status === 'waiting')
                                            <span class="erp-badge-draft">{{ __('production.waiting') }}</span>
                                        @elseif($op->status === 'cancelled')
                                            <span class="badge bg-soft-danger text-danger">{{ __('production.cancelled') }}</span>
                                        @else
                                            <span class="erp-badge-draft text-uppercase">{{ $op->status }}</span>
                                        @endif
                                    </td>
                                    <td class="align-middle fs-11 text-dark fw-semibold">{{ number_format($op->planned_duration_minutes, 1) }} mins</td>
                                    <td class="align-middle fs-11 text-muted">{{ $op->planned_start->format('d/m H:i') }}</td>
                                    <td class="align-middle fs-11 text-muted">{{ $op->planned_finish->format('d/m H:i') }}</td>
                                    <td class="align-middle fs-11 text-muted">
                                        <strong>Lane:</strong> {{ $op->lane ?? 'N/A' }}<br>
                                        <strong>Res:</strong> {{ $op->resource_id ?? 'N/A' }}
                                    </td>
                                    <td class="align-middle text-center">
                                        @if($op->locked)
                                            <span class="text-danger" title="Locked Operation"><i class="feather-lock"></i></span>
                                        @else
                                            <span class="text-muted" title="Unlocked"><i class="feather-unlock"></i></span>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        @if($op->warnings && count($op->warnings) > 0)
                                            @php
                                                $renderedWarnings = [];
                                            @endphp
                                            @foreach($op->warnings as $warn)
                                                @php
                                                    $warnCode = $warn['code'] ?? '';
                                                    $warnMsg = $warn['message'] ?? '';
                                                    $warnKey = $warnCode . '_' . $warnMsg;
                                                @endphp
                                                @if(!in_array($warnKey, $renderedWarnings))
                                                    @php
                                                        $renderedWarnings[] = $warnKey;
                                                    @endphp
                                                    <span class="badge bg-soft-danger text-danger d-block mb-1 font-monospace" style="font-size: 10px;" title="{{ $warnMsg }}">
                                                        {{ $warnCode }}
                                                    </span>
                                                @endif
                                            @endforeach
                                        @else
                                            <span class="text-success fs-12"><i class="feather-check-circle"></i> {{ __('production.clean') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center py-4 text-muted">
                                        <i class="feather-info me-2"></i>{{ __('production.no_operations_scheduled') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-capacity" role="tabpanel">
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th>{{ __('production.work_centers') }}</th>
                                <th>{{ __('production.active_shifts') }}</th>
                                <th class="text-center">{{ __('production.resource_count') }}</th>
                                <th class="text-end">{{ __('production.scheduled_time') }}</th>
                                <th class="text-end">{{ __('production.available_capacity') }}</th>
                                <th>{{ __('production.utilization_percent') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($capacityDetails as $detail)
                                <tr>
                                    <td class="fw-bold align-middle">
                                        {{ $detail['work_center']->name }}
                                        <br><small class="text-muted">Calendar: {{ $detail['calendar_name'] }} ({{ $detail['working_days'] }})</small>
                                    </td>
                                    <td class="align-middle">
                                        {{ $detail['shifts'] }}
                                    </td>
                                    <td class="align-middle text-center">
                                        <span class="badge bg-soft-info text-info">{{ __('production.active_machines', ['count' => $detail['active_machines']]) }}</span>
                                    </td>
                                    <td class="align-middle text-end font-monospace">{{ number_format($detail['scheduled_minutes'], 1) }} mins</td>
                                    <td class="align-middle text-end font-monospace">{{ number_format($detail['capacity_minutes'], 1) }} mins</td>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            <span class="fw-bold me-2 {{ $detail['utilization'] > 85 ? 'text-danger' : 'text-success' }}">
                                                {{ number_format($detail['utilization'], 2) }}%
                                            </span>
                                            <div class="progress flex-grow-1" style="height: 6px; min-width: 100px;">
                                                <div class="progress-bar {{ $detail['utilization'] > 85 ? 'bg-danger' : 'bg-success' }}" 
                                                     role="progressbar" 
                                                     style="width: {{ $detail['utilization'] }}%" 
                                                     aria-valuenow="{{ $detail['utilization'] }}" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="feather-info me-2"></i>{{ __('production.no_capacity_data') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>
        </div>
    </div>

    {{-- Cancel Modal --}}
    <x-ui.modal id="cancelModal" :title="__('production.cancel_schedule')" class="text-start">
        <form method="POST" action="{{ route('production.schedules.cancel', $schedule->id) }}" id="cancelFormMain">
            @csrf
            <p class="fs-13 text-muted">{{ __('production.cancel_schedule_confirm', ['number' => $schedule->schedule_number]) }}</p>
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.back') ?? 'Back' }}</button>
            <button type="submit" class="btn btn-danger" onclick="document.getElementById('cancelFormMain').submit();">{{ __('production.cancel_schedule') }}</button>
        </x-slot>
    </x-ui.modal>
@endsection
