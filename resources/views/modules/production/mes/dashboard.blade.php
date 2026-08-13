@extends('layouts.duralux')

@section('title', __('production.shop_floor_dashboard') . ' | SaaS ERP')
@section('page-title', __('production.shop_floor_operator_dashboard'))
@section('breadcrumb', __('production.mes_dashboard'))

@push('styles')
    <style>
        .mes-op-card {
            border-radius: 12px;
            transition: all 0.25s ease;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
            overflow: hidden;
        }
        .mes-op-card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,.06);
            transform: translateY(-2px);
        }
        .running-card {
            border-left: 5px solid #28a745 !important;
            background: #ffffff;
        }
        .paused-card {
            border-left: 5px solid #ffc107 !important;
            background: #ffffff;
        }
        .ready-card {
            border-left: 5px solid #17a2b8 !important;
            background: #ffffff;
        }
        .mes-action-btn {
            min-width: 100px;
        }
        @keyframes pulse-glowing {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: #28a745;
            border-radius: 50%;
            display: inline-block;
            animation: pulse-glowing 2s infinite;
        }
        .progress-sm {
            height: 6px;
            border-radius: 3px;
        }
        .sidebar-widget {
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,0.06);
        }
        /* Visual Routing Timelines */
        .mes-progress-track-wrapper {
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
            padding: 10px 0;
            width: 100%;
        }
        .mes-progress-track-wrapper::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        .mes-progress-track {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            margin: 10px 0;
            padding: 0;
            min-width: max-content;
            width: 100%;
        }
        .mes-progress-track::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 60px;
            right: 60px;
            height: 4px;
            background-color: #e9ecef;
            z-index: 1;
        }
        .mes-track-line-filled {
            position: absolute;
            top: 20px;
            left: 60px;
            height: 4px;
            background-color: #28a745;
            z-index: 2;
            transition: width 0.3s ease;
        }
        .mes-progress-step {
            position: relative;
            z-index: 3;
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 0 0 130px;
            width: 130px;
            text-align: center;
            padding: 0 5px;
        }
        .mes-step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #ffffff;
            border: 3px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #6c757d;
            transition: all 0.3s ease;
            cursor: default;
            flex-shrink: 0;
        }
        .mes-progress-step.step-completed .mes-step-icon {
            border-color: #28a745;
            background-color: #28a745;
            color: #ffffff;
        }
        .mes-progress-step.step-running .mes-step-icon {
            border-color: #0d6efd;
            background-color: #0d6efd;
            color: #ffffff;
            box-shadow: 0 0 0 5px rgba(13, 110, 253, 0.2);
        }
        .mes-progress-step.step-paused .mes-step-icon {
            border-color: #ffc107;
            background-color: #ffc107;
            color: #212529;
            box-shadow: 0 0 0 5px rgba(255, 193, 7, 0.2);
        }
        .mes-progress-step.step-ready .mes-step-icon {
            border-color: #17a2b8;
            background-color: #ffffff;
            color: #17a2b8;
            border-style: dashed;
        }
        .mes-step-title {
            font-size: 11px;
            font-weight: 600;
            margin-top: 8px;
            color: #495057;
            line-height: 1.3;
            width: 100%;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .mes-step-subtitle {
            font-size: 9px;
            color: #6c757d;
            margin-top: 3px;
            width: 100%;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.2;
        }
        .mes-step-status-badge {
            font-size: 8px;
            text-transform: uppercase;
            padding: 2px 6px;
            border-radius: 4px;
            margin-top: 6px;
            font-weight: 700;
            display: inline-block;
        }
        .step-completed .mes-step-status-badge {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .step-running .mes-step-status-badge {
            background-color: #cfe2ff;
            color: #084298;
            animation: pulse-glowing 2s infinite;
        }
        .step-paused .mes-step-status-badge {
            background-color: #fff3cd;
            color: #664d03;
        }
        .step-ready .mes-step-status-badge {
            background-color: #cff4fc;
            color: #087990;
        }
        .step-waiting .mes-step-status-badge {
            background-color: #f8f9fa;
            color: #6c757d;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Calculate client-server clock offset once
            let clockOffsetMs = 0;
            const firstBlock = document.querySelector('.mes-timer-block');
            if (firstBlock && firstBlock.dataset.serverTime) {
                const serverTime = new Date(firstBlock.dataset.serverTime);
                const clientTime = new Date();
                clockOffsetMs = serverTime - clientTime;
            }

            function updateTimers() {
                const now = new Date(new Date().getTime() + clockOffsetMs);

                document.querySelectorAll('.mes-timer-block').forEach(block => {
                    const startTimeStr = block.dataset.start;
                    const plannedStartStr = block.dataset.plannedStart;
                    const finishTimeStr = block.dataset.finish;
                    const status = block.dataset.status;
                    const accumulatedPausedSecs = parseInt(block.dataset.accumulatedPausedSeconds || '0', 10);
                    const lastPausedAtStr = block.dataset.lastPausedAt;

                    if (startTimeStr) {
                        const start = new Date(startTimeStr);

                        let elapsedMs = 0;
                        if (status === 'paused' && lastPausedAtStr) {
                            const lastPausedAt = new Date(lastPausedAtStr);
                            elapsedMs = (lastPausedAt - start) - (accumulatedPausedSecs * 1000);
                        } else {
                            elapsedMs = (now - start) - (accumulatedPausedSecs * 1000);
                        }

                        if (elapsedMs > 0) {
                            const elapsedSecs = Math.floor(elapsedMs / 1000);
                            const h = String(Math.floor(elapsedSecs / 3600)).padStart(2, '0');
                            const m = String(Math.floor((elapsedSecs % 3600) / 60)).padStart(2, '0');
                            const s = String(elapsedSecs % 60).padStart(2, '0');

                            const elapsedEl = block.querySelector('.timer-elapsed');
                            if (elapsedEl) elapsedEl.textContent = `${h}:${m}:${s}`;
                        }
                    }

                    if (finishTimeStr) {
                        const finish = new Date(finishTimeStr);
                        const start = plannedStartStr ? new Date(plannedStartStr) : (startTimeStr ? new Date(startTimeStr) : null);

                        // 1. Progress Bar (Calendar timeline progress)
                        if (start) {
                            const totalDuration = finish - start;
                            let percent = 0;
                            if (totalDuration > 0) {
                                percent = ((now - start) / totalDuration) * 100;
                                if (percent < 0) percent = 0;
                                if (percent > 100) percent = 100;
                            } else {
                                percent = 100;
                            }

                            const progressBar = block.querySelector('.timer-progress-bar');
                            if (progressBar) {
                                progressBar.style.width = `${percent}%`;
                                progressBar.setAttribute('aria-valuenow', percent);
                                if (percent > 90) {
                                    progressBar.className = 'progress-bar timer-progress-bar bg-danger';
                                } else if (percent > 70) {
                                    progressBar.className = 'progress-bar timer-progress-bar bg-warning';
                                } else {
                                    progressBar.className = 'progress-bar timer-progress-bar bg-success';
                                }
                            }
                        }

                        // 2. Remaining Countdown (Calendar deadline countdown)
                        const remainingSecsLeft = (finish - now) / 1000;

                        const remainingEl = block.querySelector('.timer-remaining');
                        if (remainingEl) {
                            if (remainingSecsLeft > 0) {
                                const remainingSecs = Math.floor(remainingSecsLeft);
                                const h = String(Math.floor(remainingSecs / 3600)).padStart(2, '0');
                                const m = String(Math.floor((remainingSecs % 3600) / 60)).padStart(2, '0');
                                const s = String(remainingSecs % 60).padStart(2, '0');
                                remainingEl.textContent = `${h}:${m}:${s}`;
                                remainingEl.className = 'timer-remaining text-success fw-bold font-monospace';
                            } else {
                                const overdueSecs = Math.floor(Math.abs(remainingSecsLeft));
                                const h = String(Math.floor(overdueSecs / 3600)).padStart(2, '0');
                                const m = String(Math.floor((overdueSecs % 3600) / 60)).padStart(2, '0');
                                const s = String(overdueSecs % 60).padStart(2, '0');
                                remainingEl.textContent = `Overdue: -${h}:${m}:${s}`;
                                remainingEl.className = 'timer-remaining text-danger fw-bold font-monospace';
                            }
                        }
                    }
                });
            }

            // Initial call and tick every second
            updateTimers();
            setInterval(updateTimers, 1000);

            // Automatically pre-fill run minutes in the completion modals based on actual elapsed stopwatch time
            document.querySelectorAll('[id^="completeModal"]').forEach(modal => {
                modal.addEventListener('show.bs.modal', function () {
                    const opId = modal.id.replace('completeModal', '');
                    const block = document.querySelector(`.mes-timer-block[data-start]`);
                    const card = modal.closest('.card') || document.getElementById(`completeModal${opId}`).closest('.card');
                    if (card) {
                        const timerElapsedText = card.querySelector('.timer-elapsed')?.textContent;
                        if (timerElapsedText) {
                            const parts = timerElapsedText.split(':');
                            if (parts.length === 3) {
                                const hours = parseInt(parts[0], 10);
                                const minutes = parseInt(parts[1], 10);
                                const seconds = parseInt(parts[2], 10);
                                const totalMinutes = (hours * 60) + minutes + (seconds / 60);
                                const runMinutesInput = modal.querySelector('input[name="run_minutes"]');
                                if (runMinutesInput) {
                                    runMinutesInput.value = totalMinutes.toFixed(1);
                                }
                            }
                        }
                    }
                });
            });
        });

        function toggleExtraShifts(btn) {
            const extra = document.getElementById('extra-shifts');
            const label = document.getElementById('more-shifts-label');
            if (extra.classList.contains('d-none')) {
                extra.classList.remove('d-none');
                label.textContent = @js(__('production.show_less_shifts'));
            } else {
                extra.classList.add('d-none');
                label.textContent = @js(__('production.view_more_shifts')) + ' (' + ({{ $shifts->count() }} - 2) + ')';
            }
        }
    </script>
@endpush

@section('page-actions')
    <a href="{{ route('production.mes.machines.index') }}" class="btn btn-light me-2">
        <i class="feather-cpu me-2"></i>{{ __('production.machines') ?? 'Machines' }}
    </a>
    <a href="{{ route('production.mes.work-centers.index') }}" class="btn btn-light">
        <i class="feather-settings me-2"></i>{{ __('production.col_work_center') }}
    </a>
@endsection

@section('content')

    {{-- ── Shop Floor (MES) Workflow Guidance Component ── --}}
    <x-ui.workflow-guide title="What's Next?">
        @if(isset($activeSchedules) && $activeSchedules->count() > 0)
            @php $firstOrder = $activeSchedules->first()->order; @endphp
            Shop floor operations execution is active. You can assign operators to specific operations from the 
            @if($firstOrder)
                <a href="{{ route('production.orders.show', ['order' => $firstOrder->id, 'tab' => 'vtab-operations']) }}" class="fw-bold text-primary text-decoration-underline">Production Order Operations tab</a>
            @else
                <a href="{{ route('production.orders.index') }}" class="fw-bold text-primary text-decoration-underline">Production Order Operations tab</a>
            @endif
            to allocate operators for live tracking. Operators can also view assigned tasks in the <a href="{{ route('production.mes.operator.dashboard') }}" class="fw-bold text-primary text-decoration-underline">MES Operator Console</a>.
        @else
            No active schedules on the shop floor. Release confirmed schedules from <a href="{{ route('production.schedules.index') }}" class="fw-bold text-primary text-decoration-underline">Production Schedules</a> and assign operators under the <a href="{{ route('production.orders.index') }}" class="fw-bold text-primary text-decoration-underline">Production Order Operations tab</a> to begin execution.
        @endif
    </x-ui.workflow-guide>

    <div class="erp-single-panel bg-transparent border-0 p-0">

        <div class="row g-4">
            {{-- LEFT COLUMN: Production Project Dashboard (8 Cols) --}}
            <div class="col-lg-8">

                <div class="d-flex align-items-center mb-4">
                    <h5 class="fw-bold text-dark mb-0 d-flex align-items-center">
                        <i class="feather-grid me-2 text-primary"></i>{{ __('production.active_manufacturing_projects') }}
                    </h5>
                    <span class="badge bg-soft-primary text-primary rounded-pill ms-2 fw-bold font-monospace">{{ $activeSchedules->count() }}</span>
                </div>

                @forelse($activeSchedules as $schedule)
                    @php
                        $order = $schedule->order;
                        $ops = $schedule->operations->sortBy('sequence');
                        $totalOps = $ops->count();
                        $completedOps = $ops->where('status', 'completed')->count();
                        $progressPercent = $totalOps > 0 ? ($completedOps / $totalOps) * 100 : 0;

                        // Active operations that need control panels
                        $activeOps = $ops->whereIn('status', ['running', 'paused', 'ready']);
                    @endphp

                    <div class="card mb-4 border border-light shadow-sm" style="border-radius: 12px; overflow: hidden;">
                        {{-- Card Header --}}
                        <div class="card-header bg-light border-bottom border-light p-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h6 class="fw-bold text-dark mb-1 fs-14">
                                    <a href="{{ route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-operations']) }}" class="text-primary fw-bold text-decoration-underline" title="Assign operators under Operations tab">{{ $order->order_number ?? '' }}</a>
                                    <span class="mx-1 text-muted">&middot;</span>
                                    {{ $order->product->name ?? 'Unknown Product' }}
                                </h6>
                                <div class="text-muted fs-11">
                                    {{ __('production.schedule') }}: <strong class="text-secondary">{{ $schedule->schedule_number }}</strong>
                                    <span class="mx-2">|</span>
                                    {{ __('production.quantity') ?? 'Quantity' }}: <strong class="text-secondary">{{ (int) $order->quantity_ordered }} {{ __('production.units') }}</strong>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-soft-success text-success fs-10 px-2 py-1 rounded-pill mb-1">
                                    {{ __('production.steps_complete', ['completed' => $completedOps, 'total' => $totalOps]) }}
                                </span>
                                <div class="progress progress-sm bg-white border" style="width: 150px; height: 6px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $progressPercent }}%" aria-valuenow="{{ $progressPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Card Body: Visual Routing Flow --}}
                        <div class="card-body p-4 bg-white">
                            <h6 class="fw-bold text-secondary mb-3 fs-11 text-uppercase tracking-wider">{{ __('production.project_routing_sequence') }}</h6>

                            <div class="mes-progress-track-wrapper">
                                <div class="mes-progress-track">
                                    @php
                                        $stepFraction = $totalOps > 1 ? ($completedOps / ($totalOps - 1)) : 0;
                                        if ($completedOps >= $totalOps) {
                                            $stepFraction = 1;
                                        }
                                    @endphp
                                    <div class="mes-track-line-filled" style="width: calc((100% - 120px) * {{ $stepFraction }});"></div>

                                    @foreach($ops as $op)
                                        @php
                                            $stepClass = 'step-waiting';
                                            $stepIcon = 'lock';
                                            if ($op->status === 'completed') {
                                                $stepClass = 'step-completed';
                                                $stepIcon = 'check';
                                            } elseif ($op->status === 'running') {
                                                $stepClass = 'step-running';
                                                $stepIcon = 'play';
                                            } elseif ($op->status === 'paused') {
                                                $stepClass = 'step-paused';
                                                $stepIcon = 'pause';
                                            } elseif ($op->status === 'ready') {
                                                $stepClass = 'step-ready';
                                                $stepIcon = 'arrow-right';
                                            }
                                            $statusKey = 'production.' . $op->status;
                                            $translatedStatus = __($statusKey) != $statusKey ? __($statusKey) : $op->status;
                                        @endphp

                                        <div class="mes-progress-step {{ $stepClass }}">
                                            <div class="mes-step-icon" title="{{ ucfirst($op->status) }}">
                                                <i class="feather-{{ $stepIcon }}"></i>
                                            </div>
                                            <div class="mes-step-title" title="{{ $op->orderOperation->name ?? $op->name }}">
                                                {{ $op->orderOperation->name ?? 'Op' }}
                                            </div>
                                            <div class="mes-step-subtitle" title="{{ $op->workCenter->name ?? '' }}">
                                                {{ $op->workCenter->name ?? '' }}
                                            </div>
                                            <span class="mes-step-status-badge">{{ $translatedStatus }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Card Footer: Active Action Area --}}
                            @if($activeOps->count() > 0)
                                <div class="mt-4 pt-3 border-top">
                                    <h6 class="fw-bold text-secondary mb-3 fs-11 text-uppercase tracking-wider">{{ __('production.active_shopfloor_controls') }}</h6>

                                    @foreach($activeOps as $activeOp)
                                        <div class="p-3 bg-light rounded border mb-3">
                                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                                <div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <strong class="text-dark fs-13">{{ $activeOp->orderOperation->name ?? 'Operation' }}</strong>
                                                        <span class="badge bg-secondary font-monospace fs-10 px-2 py-0.5">Seq {{ $activeOp->sequence }}</span>
                                                    </div>
                                                    <div class="text-muted fs-11 mt-1">
                                                        <i class="feather-settings me-1"></i>{{ __('production.work_center') }}: <strong>{{ $activeOp->workCenter->name ?? 'Generic' }}</strong>
                                                        @if($activeOp->machine)
                                                            <span class="mx-1">&middot;</span>
                                                            <i class="feather-cpu me-1"></i>{{ __('production.col_machine') ?? 'Machine' }}: <strong>{{ $activeOp->machine->name }}</strong>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="d-flex align-items-center gap-3 flex-grow-1 flex-md-grow-0 justify-content-end">
                                                    @if($activeOp->status === 'running')
                                                        {{-- Timer block --}}
                                                        <div class="bg-white px-3 py-2 rounded border mes-timer-block shadow-none text-center"
                                                             style="min-width: 200px;"
                                                             data-start="{{ $activeOp->actual_start ? $activeOp->actual_start->toISOString() : '' }}"
                                                             data-planned-start="{{ $activeOp->planned_start ? $activeOp->planned_start->toISOString() : '' }}"
                                                             data-finish="{{ $activeOp->planned_finish ? $activeOp->planned_finish->toISOString() : '' }}"
                                                             data-status="{{ $activeOp->status }}"
                                                             data-accumulated-paused-seconds="{{ $activeOp->accumulated_paused_seconds ?? 0 }}"
                                                             data-last-paused-at="{{ $activeOp->last_paused_at ? $activeOp->last_paused_at->toISOString() : '' }}"
                                                             data-server-time="{{ now()->toISOString() }}">
                                                            <div class="d-flex justify-content-between gap-3">
                                                                <div>
                                                                    <small class="text-muted text-uppercase d-block fs-8 fw-bold">{{ __('production.stopwatch') }}</small>
                                                                    <span class="timer-elapsed font-monospace fw-bold text-dark fs-12">00:00:00</span>
                                                                </div>
                                                                <div class="border-end"></div>
                                                                <div>
                                                                    <small class="text-muted text-uppercase d-block fs-8 fw-bold">{{ __('production.countdown') }}</small>
                                                                    <span class="timer-remaining font-monospace fw-bold text-success fs-12">00:00:00</span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="d-flex gap-2">
                                                            <form method="POST" action="{{ route('production.mes.pause', $activeOp->id) }}">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-warning text-dark px-3 fw-semibold">
                                                                    <i class="feather-pause me-1"></i>{{ __('production.pause') }}
                                                                </button>
                                                            </form>
                                                            <button type="button" class="btn btn-sm btn-success px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#completeModal{{ $activeOp->id }}">
                                                                <i class="feather-check-circle me-1"></i>{{ __('production.complete') }}
                                                            </button>
                                                        </div>

                                                    @elseif($activeOp->status === 'paused')
                                                        {{-- Paused display --}}
                                                        <div class="bg-white px-3 py-2 rounded border text-center" style="min-width: 200px;">
                                                            <span class="text-danger fw-bold fs-12 d-block"><i class="feather-pause-circle me-1"></i>{{ __('production.paused') }}</span>
                                                            <small class="text-muted fs-10 italic">"{{ $activeOp->remarks ?? 'No remarks' }}"</small>
                                                        </div>

                                                        <div class="d-flex gap-2">
                                                            <form method="POST" action="{{ route('production.mes.resume', $activeOp->id) }}">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-primary px-3 fw-semibold">
                                                                    <i class="feather-play me-1"></i>{{ __('production.resume') }}
                                                                </button>
                                                            </form>
                                                            <button type="button" class="btn btn-sm btn-success px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#completeModal{{ $activeOp->id }}">
                                                                <i class="feather-check-circle me-1"></i>{{ __('production.complete') }}
                                                            </button>
                                                        </div>

                                                    @elseif($activeOp->status === 'ready')
                                                        {{-- Ready to start --}}
                                                        <form method="POST" action="{{ route('production.mes.start', $activeOp->id) }}">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-success px-4 py-2 fw-bold shadow-sm">
                                                                <i class="feather-play me-1"></i>{{ __('production.start_operation') }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Completion Modals for active operations in this schedule --}}
                    @foreach($activeOps->whereIn('status', ['running', 'paused']) as $activeOp)
                        @php
                            $elapsedSecs = 0;
                            if ($activeOp->actual_start && $activeOp->last_paused_at) {
                                $elapsedSecs = max(0, $activeOp->last_paused_at->timestamp - $activeOp->actual_start->timestamp - $activeOp->accumulated_paused_seconds);
                            } elseif ($activeOp->actual_start) {
                                $elapsedSecs = max(0, now()->timestamp - $activeOp->actual_start->timestamp - $activeOp->accumulated_paused_seconds);
                            }
                            $elapsedMinutes = round($elapsedSecs / 60, 1);
                            
                            $activeScrapQty = max(
                                (float) ($activeOp->orderOperation->quantity_scrapped ?? 0.0),
                                (float) \App\Domains\Production\Models\ProductionOrderScrap::where('tenant_id', $activeOp->tenant_id ?? require_tenant_id())
                                    ->where('production_order_id', $activeOp->production_order_id ?? $activeOp->order_id)
                                    ->where('production_order_operation_id', $activeOp->production_order_operation_id ?? $activeOp->id)
                                    ->sum('quantity')
                            );
                            $activeRemainingToComplete = max(0.0, ($activeOp->schedule->order->quantity_ordered ?? 0.0) - (($activeOp->orderOperation->quantity_produced ?? 0.0) + $activeScrapQty + ($activeOp->orderOperation->quantity_rejected ?? 0.0)));
                        @endphp
                        <x-ui.modal id="completeModal{{ $activeOp->id }}" title="{{ __('production.log_production_progress') }} — {{ html_entity_decode($activeOp->orderOperation->name ?? 'Op #' . $activeOp->sequence, ENT_QUOTES, 'UTF-8') }}" class="text-start">
                            <form method="POST" action="{{ route('production.mes.complete', $activeOp->id) }}" id="completeForm{{ $activeOp->id }}">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="{{ __('production.produced') }}" name="quantity_produced" inputType="number" step="any" value="{{ $activeRemainingToComplete }}" :required="true" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="{{ __('production.rejects') }}" name="quantity_rejected" inputType="number" step="any" value="0" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="{{ __('production.scrapped') }}" name="quantity_scrapped" inputType="number" step="any" value="0" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="{{ __('production.setup_adjustment') }} (min)" name="setup_minutes" inputType="number" step="any" value="0" />
                                    </div>
                                    <div class="col-md-12">
                                        <x-ui.odoo-form-ui type="input" label="{{ __('production.run_time_min') }}" name="run_minutes" inputType="number" step="any" value="{{ $elapsedMinutes }}" />
                                    </div>
                                    <div class="col-md-12">
                                        <x-ui.odoo-form-ui type="textarea" label="{{ __('production.revision_notes') }}" name="remarks" placeholder="Optional completion notes..." />
                                    </div>
                                </div>
                            </form>
                            <x-slot name="footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                                <button type="submit" class="btn btn-success px-4" onclick="document.getElementById('completeForm{{ $activeOp->id }}').submit();">
                                    <i class="feather-check me-1"></i>{{ __('production.complete') }}
                                </button>
                            </x-slot>
                        </x-ui.modal>
                    @endforeach
                @empty
                    <div class="card p-5 text-center border bg-white rounded-3 shadow-sm mb-4">
                        <div class="avatar-text avatar-lg bg-soft-light text-muted rounded mx-auto mb-3">
                            <i class="feather-grid fs-28"></i>
                        </div>
                        <h6 class="fw-bold text-dark">{{ __('production.no_active_projects') }}</h6>
                        <p class="text-muted fs-12 mb-0">{{ __('production.create_release_schedule_desc') }}</p>
                    </div>
                @endforelse
            </div>

            {{-- RIGHT COLUMN: Secondary Metadata Widgets & Shortcuts (4 Cols) --}}
            <div class="col-lg-4">

                {{-- Side Widget A: Stats Summary --}}
                <div class="card border-0 shadow-sm mb-4 sidebar-widget">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="feather-pie-chart text-primary me-2"></i>{{ __('production.performance_tracking') }}</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="bg-light p-3 rounded text-center border">
                                    <span class="fs-20 fw-bold text-success">{{ $completedToday }}</span>
                                    <span class="text-muted text-uppercase fs-9 d-block mt-1 font-monospace fw-bold">{{ __('production.done_today') }}</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-light p-3 rounded text-center border">
                                    <span class="fs-20 fw-bold text-warning">{{ $running->count() }}</span>
                                    <span class="text-muted text-uppercase fs-9 d-block mt-1 font-monospace fw-bold">{{ __('production.active_ops') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Side Widget B: Quick Scan Shortcuts --}}
                <div class="card border-0 shadow-sm mb-4 sidebar-widget">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="feather-maximize text-secondary me-2"></i>{{ __('production.shop_floor_scan_center') }}</h6>
                        <div class="list-group list-group-flush mb-0">
                            <a href="{{ route('production.mes.scanner.index') }}" class="list-group-item list-group-item-action d-flex align-items-center px-0 py-3">
                                <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded me-3">
                                    <i class="feather-maximize"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fs-13 fw-semibold text-dark">{{ __('production.barcode_scanner') }}</div>
                                    <small class="text-muted">{{ __('production.simulate_scan_actions') }}</small>
                                </div>
                                <i class="feather-chevron-right text-muted fs-12"></i>
                            </a>
                            <a href="{{ route('production.mes.traceability.index') }}" class="list-group-item list-group-item-action d-flex align-items-center px-0 py-3">
                                <div class="avatar-text avatar-sm bg-soft-success text-success rounded me-3">
                                    <i class="feather-git-commit"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fs-13 fw-semibold text-dark">{{ __('production.lot_traceability') }}</div>
                                    <small class="text-muted">{{ __('production.trace_component_origins') }}</small>
                                </div>
                                <i class="feather-chevron-right text-muted fs-12"></i>
                            </a>
                            <a href="{{ route('production.scan-logs.index') }}" class="list-group-item list-group-item-action d-flex align-items-center px-0 py-3">
                                <div class="avatar-text avatar-sm bg-soft-info text-info rounded me-3">
                                    <i class="feather-list"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fs-13 fw-semibold text-dark">{{ __('production.scan_logs') }}</div>
                                    <small class="text-muted">{{ __('production.view_historical_scans') }}</small>
                                </div>
                                <i class="feather-chevron-right text-muted fs-12"></i>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Side Widget C: Active Shifts (Showing max 2 by default + View More toggle) --}}
                <div class="card border-0 shadow-sm sidebar-widget">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <h6 class="fw-bold text-dark mb-0"><i class="feather-sun me-2 text-warning"></i>{{ __('production.active_shifts') }}</h6>
                            <a href="{{ route('production.shifts.index') }}" class="btn btn-xs btn-outline-primary ms-auto">
                                <i class="feather-settings me-1"></i>{{ __('production.manage') }}
                            </a>
                        </div>
                        @if($shifts->count() > 0)
                            @php
                                $initialShifts = $shifts->take(2);
                                $remainingShifts = $shifts->slice(2);
                            @endphp

                            <div class="d-flex flex-column gap-2">
                                @foreach($initialShifts as $sf)
                                    <div class="bg-light p-3 rounded border">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="font-monospace fw-bold text-primary fs-11">{{ $sf->code }}</span>
                                            @if($sf->active)
                                                <span class="badge bg-soft-success text-success fs-9 rounded-pill px-2">{{ __('production.status_active') }}</span>
                                            @endif
                                        </div>
                                        <h6 class="fw-semibold text-dark mb-1 fs-12">{{ $sf->name }}</h6>
                                        <div class="text-muted fs-11">
                                            <i class="feather-clock me-1"></i>{{ substr($sf->start_time, 0, 5) }} - {{ substr($sf->end_time, 0, 5) }}
                                            <span class="mx-1">&middot;</span>
                                            <span>{{ __('production.break') }}: {{ $sf->break_minutes }}m</span>
                                        </div>
                                    </div>
                                @endforeach

                                @if($remainingShifts->count() > 0)
                                    <div id="extra-shifts" class="d-none d-flex flex-column gap-2">
                                        @foreach($remainingShifts as $sf)
                                            <div class="bg-light p-3 rounded border">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="font-monospace fw-bold text-primary fs-11">{{ $sf->code }}</span>
                                                    @if($sf->active)
                                                        <span class="badge bg-soft-success text-success fs-9 rounded-pill px-2">{{ __('production.status_active') }}</span>
                                                    @endif
                                                </div>
                                                <h6 class="fw-semibold text-dark mb-1 fs-12">{{ $sf->name }}</h6>
                                                <div class="text-muted fs-11">
                                                    <i class="feather-clock me-1"></i>{{ substr($sf->start_time, 0, 5) }} - {{ substr($sf->end_time, 0, 5) }}
                                                    <span class="mx-1">&middot;</span>
                                                    <span>{{ __('production.break') }}: {{ $sf->break_minutes }}m</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <button type="button" class="btn btn-sm btn-link text-primary p-0 mt-2 fw-semibold text-decoration-none text-start" onclick="toggleExtraShifts(this)">
                                        <span id="more-shifts-label">{{ __('production.view_more_shifts') }} ({{ $remainingShifts->count() }})</span>
                                    </button>
                                @endif
                            </div>
                        @else
                            <div class="text-center py-4 text-muted fs-12">
                                <span>No active shifts configured. <a href="{{ route('production.shifts.create') }}" class="text-primary fw-semibold">Add Shift</a>.</span>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
