@extends('layouts.duralux')

@section('title', __('production.schedule_details', ['number' => $schedule->schedule_number]) . ' | SaaS ERP')

@section('page-actions')
    <a href="{{ route('production.schedules.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>{{ __('production.back_to_list') }}
    </a>

    @if($schedule->isScheduled())
        <button type="button" class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#rescheduleStartModal">
            <i class="feather-calendar me-2"></i>Change Schedule Start Date
        </button>
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
                <div style="max-height: 180px; overflow-y: auto; padding-right: 8px;" class="warning-scrollbar-custom">
                    @foreach($warnings as $warning)
                        <div class="fs-12 text-warning-800 mb-1">• {{ $warning }}</div>
                    @endforeach
                </div>
            </div>
            
            <style>
                .warning-scrollbar-custom::-webkit-scrollbar {
                    width: 6px;
                }
                .warning-scrollbar-custom::-webkit-scrollbar-track {
                    background: rgba(245, 158, 11, 0.05);
                    border-radius: 4px;
                }
                .warning-scrollbar-custom::-webkit-scrollbar-thumb {
                    background: rgba(245, 158, 11, 0.3);
                    border-radius: 4px;
                }
                .warning-scrollbar-custom::-webkit-scrollbar-thumb:hover {
                    background: rgba(245, 158, 11, 0.5);
                }
            </style>
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
                <style>
                    .transition-icon {
                        transition: transform 0.2s ease-in-out;
                        display: inline-block;
                    }
                    tr[aria-expanded="true"] .transition-icon {
                        transform: rotate(90deg);
                    }
                    .hover-bg-light:hover {
                        background-color: rgba(0, 0, 0, 0.03) !important;
                    }
                </style>
                <div class="alert alert-info border-0 shadow-sm d-flex align-items-start mb-4 p-3 bg-light text-dark fs-13" style="border-left: 4px solid #0d6efd !important;">
                    <i class="feather-info me-3 fs-20 text-primary mt-1"></i>
                    <div>
                        <h6 class="fw-bold text-primary mb-1">Understanding Work Center Capacity</h6>
                        <p class="mb-0 text-muted fs-12">
                            The table below shows the <strong>total accumulated capacity</strong> summed over the entire scheduled period. 
                            If a work center is flagged with an overload warning above, it means scheduled work exceeded capacity on a <strong>specific day</strong> (e.g. on a weekend or shift overflow). 
                            <span class="fw-bold text-dark">Click on any work center row below</span> to toggle a detailed day-by-day breakdown.
                        </p>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-4 p-2 bg-light rounded border border-light shadow-none">
                    <div class="text-muted fs-12 ms-2">
                        @php
                            $currentGroup = $capacityDetails[0]['group_type'] ?? 'day';
                        @endphp
                        Showing breakdown grouped by: <strong class="text-capitalize text-dark">{{ $currentGroup }}</strong>
                    </div>
                    <div class="btn-group btn-group-sm" style="gap:10px;" role="group" aria-label="Capacity grouping">
                        <a href="{{ request()->fullUrlWithQuery(['group_by' => 'day']) }}" class="btn btn-outline-primary {{ $currentGroup === 'day' ? 'active' : '' }}">
                            Day
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['group_by' => 'week']) }}" class="btn btn-outline-primary {{ $currentGroup === 'week' ? 'active' : '' }}">
                            Week
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['group_by' => 'month']) }}" class="btn btn-outline-primary {{ $currentGroup === 'month' ? 'active' : '' }}">
                            Month
                        </a>
                    </div>
                </div>
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
                                <tr data-bs-toggle="collapse" data-bs-target="#collapse-wc-{{ $detail['work_center']->id }}" style="cursor: pointer;" class="hover-bg-light">
                                    <td class="fw-bold align-middle">
                                        <i class="feather-chevron-right me-2 text-muted transition-icon" id="arrow-wc-{{ $detail['work_center']->id }}"></i>
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
                                <tr class="collapse" id="collapse-wc-{{ $detail['work_center']->id }}">
                                    <td colspan="6" class="p-3 bg-light">
                                        <div class="card card-body border border-light shadow-sm p-4 bg-white rounded">
                                            @php
                                                $groupType = $detail['group_type'] ?? 'day';
                                                $headerTitle = $groupType === 'week' ? 'Weekly' : ($groupType === 'month' ? 'Monthly' : 'Day-by-Day');
                                                $col1Label = $groupType === 'week' ? 'Date Range' : ($groupType === 'month' ? 'Month' : 'Scheduled Date');
                                                $col2Label = $groupType === 'week' ? 'Week Number' : ($groupType === 'month' ? 'Period' : 'Day of Week');
                                            @endphp
                                            <h6 class="fw-bold text-dark fs-13 mb-3">
                                                <i class="feather-calendar me-2 text-primary"></i>{{ $headerTitle }} Capacity Breakdown for {{ $detail['work_center']->name }}
                                            </h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered fs-12 mb-0">
                                                    <thead class="table-light text-dark fw-bold">
                                                        <tr>
                                                            <th>{{ $col1Label }}</th>
                                                            <th>{{ $col2Label }}</th>
                                                            <th class="text-end">Scheduled Minutes</th>
                                                            <th class="text-end">Available Capacity</th>
                                                            <th class="text-end">Utilization %</th>
                                                            <th>Capacity Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($detail['daily_breakdown'] as $day)
                                                            @php
                                                                $isOverloaded = $day['scheduled_minutes'] > $day['capacity_minutes'];
                                                                $isSundayOverload = $day['capacity_minutes'] == 0 && $day['scheduled_minutes'] > 0;
                                                            @endphp
                                                            <tr style="background-color: {{ $isOverloaded ? '#fff5f5' : '#ffffff' }};">
                                                                <td class="font-monospace align-middle">{{ $day['date'] }}</td>
                                                                <td class="align-middle fw-medium">{{ $day['day_name'] }}</td>
                                                                <td class="text-end align-middle font-monospace">{{ number_format($day['scheduled_minutes'], 1) }} mins</td>
                                                                <td class="text-end align-middle font-monospace">{{ number_format($day['capacity_minutes'], 1) }} mins</td>
                                                                <td class="text-end align-middle font-monospace fw-bold {{ $isOverloaded ? 'text-danger' : 'text-success' }}">
                                                                    {{ number_format($day['utilization'], 2) }}%
                                                                </td>
                                                                <td class="align-middle">
                                                                    @if($isSundayOverload)
                                                                        <span class="badge bg-soft-danger text-danger border border-danger-subtle"><i class="feather-alert-triangle me-1"></i>Overloaded (Non-working Day)</span>
                                                                    @elseif($isOverloaded)
                                                                        <span class="badge bg-soft-danger text-danger border border-danger-subtle"><i class="feather-alert-octagon me-1"></i>Overloaded</span>
                                                                    @elseif($day['capacity_minutes'] == 0)
                                                                        <span class="badge bg-soft-secondary text-secondary border border-secondary-subtle">Closed</span>
                                                                    @else
                                                                        <span class="badge bg-soft-success text-success border border-success-subtle">Normal Capacity</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
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

    {{-- Reschedule Start Modal --}}
    <x-ui.modal id="rescheduleStartModal" title="Change Schedule Start Date" class="text-start">
        <form method="POST" action="{{ route('production.schedules.reschedule-start', $schedule->id) }}" id="rescheduleStartFormMain">
            @csrf
            <div class="mb-3 text-dark">
                <label class="form-label fw-bold fs-12 mb-1">New Start Date & Time</label>
                <input type="datetime-local" name="start_date" class="form-control fs-13" value="{{ $schedule->operations->min('planned_start')?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i') }}" required>
                <small class="text-muted mt-2 d-block fs-11">
                    This will delete all current planned operations for this order, recalculate using the corrected calendar working days, and schedule them starting from this new date.
                </small>
            </div>
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning" onclick="document.getElementById('rescheduleStartFormMain').submit();">Apply & Recalculate</button>
        </x-slot>
    </x-ui.modal>
@endsection
