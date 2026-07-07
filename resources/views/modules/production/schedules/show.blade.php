@extends('layouts.duralux')

@section('title', 'Schedule Details | SaaS ERP')

@section('page-actions')
    <a href="{{ route('production.schedules.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>Back to List
    </a>

    @if($schedule->isScheduled())
        <form method="POST" action="{{ route('production.schedules.release', $schedule->id) }}" class="d-inline me-2">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="feather-play-circle me-2"></i>Release to Shop Floor
            </button>
        </form>
    @endif

    @if(!$schedule->isFrozen())
        <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#cancelModal">
            <i class="feather-slash me-2"></i>Cancel Schedule
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
                Schedule Details ({{ $schedule->schedule_number }})
            </h4>
            <div>
                @if($schedule->status === 'released')
                    <span class="erp-badge-active">Released</span>
                @elseif($schedule->status === 'in_progress')
                    <span class="badge bg-soft-warning text-warning">In Progress</span>
                @elseif($schedule->status === 'scheduled')
                    <span class="badge bg-soft-info text-info">Scheduled</span>
                @elseif($schedule->status === 'draft')
                    <span class="erp-badge-draft">Draft</span>
                @elseif($schedule->status === 'completed')
                    <span class="badge bg-soft-success text-success">Completed</span>
                @elseif($schedule->status === 'cancelled')
                    <span class="badge bg-soft-danger text-danger">Cancelled</span>
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
                        <div class="fs-11 text-muted text-uppercase">Total Operations</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-success">{{ $completedOps }}</div>
                        <div class="fs-11 text-muted text-uppercase">Completed</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-warning">{{ $runningOps }}</div>
                        <div class="fs-11 text-muted text-uppercase">Running</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-info">{{ $remainingOps }}</div>
                        <div class="fs-11 text-muted text-uppercase">Remaining</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Info Grid --}}
        <div class="row g-4 mb-4">
            <div class="col-md-6 border-end">
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Production Order:</span></div>
                    <div class="col-md-8">
                        <a href="{{ route('production.orders.show', $schedule->production_order_id) }}" class="fw-bold text-primary">
                            {{ $schedule->order->order_number ?? '—' }}
                        </a>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Product:</span></div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">{{ $schedule->order->product->name ?? '—' }}</span>
                        <small class="text-muted ms-2 font-monospace">{{ $schedule->order->product->sku ?? '' }}</small>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Scheduling Type:</span></div>
                    <div class="col-md-8">
                        <span class="badge bg-soft-info text-info text-capitalize">{{ $schedule->scheduling_type }}</span>
                    </div>
                </div>
                @if($schedule->notes)
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Notes:</span></div>
                        <div class="col-md-8"><span class="text-dark fs-13">{{ $schedule->notes }}</span></div>
                    </div>
                @endif
            </div>
            <div class="col-md-6">
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Created By:</span></div>
                    <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $schedule->creator->name ?? '—' }}</span></div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Scheduled At:</span></div>
                    <div class="col-md-8"><span class="text-dark fs-13">{{ $schedule->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Source:</span></div>
                    <div class="col-md-8">
                        <span class="badge bg-soft-secondary text-secondary text-uppercase">{{ $schedule->generated_by ?? 'forward' }}</span>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Capacity Utilization:</span></div>
                    <div class="col-md-8">
                        <span class="fw-bold {{ ($schedule->capacity_utilization ?? 0) > 85 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($schedule->capacity_utilization ?? 0, 2) }}%
                        </span>
                    </div>
                </div>
                @if($schedule->released_at)
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Released:</span></div>
                        <div class="col-md-8"><span class="text-dark fs-13">{{ $schedule->released_at->format('d/m/Y H:i') }} by {{ $schedule->releasedBy->name ?? '—' }}</span></div>
                    </div>
                @endif
                @if($schedule->completed_at)
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-4"><span class="fw-semibold text-muted fs-13">Completed:</span></div>
                        <div class="col-md-8"><span class="text-success fw-bold fs-13">{{ $schedule->completed_at->format('d/m/Y H:i') }}</span></div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Capacity Warnings --}}
        @if(count($warnings) > 0)
            <div class="alert alert-warning border-warning bg-soft-warning p-3 rounded mb-4">
                <div class="fw-bold text-warning mb-2"><i class="feather-alert-triangle me-2"></i>Capacity Overload Warnings ({{ count($warnings) }})</div>
                @foreach($warnings as $warning)
                    <div class="fs-12 text-warning-800 mb-1">• {{ $warning }}</div>
                @endforeach
            </div>
        @endif

        {{-- Tabs --}}
        <x-ui.horizontal-tabs id="scheduleTabs" :tabs="[
            ['id' => 'tab-operations', 'label' => 'Schedule Operations', 'active' => true, 'icon' => 'feather-list'],
        ]" />

        <div class="tab-content mt-3">
            <div class="tab-pane fade show active" id="tab-operations" role="tabpanel">
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th style="width: 5%" class="text-center">Seq</th>
                                <th style="width: 15%">Operation</th>
                                <th style="width: 12%">Work Center</th>
                                <th style="width: 12%">Planned Machine</th>
                                <th style="width: 12%">Actual Machine</th>
                                <th style="width: 8%">Status</th>
                                <th style="width: 10%">Planned Start</th>
                                <th style="width: 10%">Planned Finish</th>
                                <th style="width: 13%">Gantt Config</th>
                                <th style="width: 5%" class="text-center">Lock</th>
                                <th style="width: 8%">Warnings</th>
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
                                            <span class="badge bg-soft-success text-success">Completed</span>
                                        @elseif($op->status === 'running')
                                            <span class="badge bg-soft-warning text-warning">Running</span>
                                        @elseif($op->status === 'ready')
                                            <span class="badge bg-soft-info text-info">Ready</span>
                                        @elseif($op->status === 'paused')
                                            <span class="badge bg-soft-warning text-warning">Paused</span>
                                        @elseif($op->status === 'waiting')
                                            <span class="erp-badge-draft">Waiting</span>
                                        @elseif($op->status === 'cancelled')
                                            <span class="badge bg-soft-danger text-danger">Cancelled</span>
                                        @else
                                            <span class="erp-badge-draft text-uppercase">{{ $op->status }}</span>
                                        @endif
                                    </td>
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
                                            @foreach($op->warnings as $warn)
                                                <span class="badge bg-soft-danger text-danger d-block mb-1 font-monospace" style="font-size: 10px;" title="{{ $warn['message'] }}">
                                                    {{ $warn['code'] }}
                                                </span>
                                            @endforeach
                                        @else
                                            <span class="text-success fs-12"><i class="feather-check-circle"></i> Clean</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">
                                        <i class="feather-info me-2"></i>No operations scheduled.
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
    <x-ui.modal id="cancelModal" title="Cancel Schedule" class="text-start">
        <form method="POST" action="{{ route('production.schedules.cancel', $schedule->id) }}" id="cancelFormMain">
            @csrf
            <p class="fs-13 text-muted">Are you sure you want to cancel <strong>{{ $schedule->schedule_number }}</strong>? This will stop all planned operations.</p>
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
            <button type="submit" class="btn btn-danger" onclick="document.getElementById('cancelFormMain').submit();">Cancel Schedule</button>
        </x-slot>
    </x-ui.modal>
@endsection
