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

        @keyframes pulse-glowing {
            0% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
            }

            70% {
                box-shadow: 0 0 0 8px rgba(40, 167, 69, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
        }

        .progress-sm {
            height: 6px;
            border-radius: 3px;
        }

        .action-icon-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 28px !important;
            height: 28px !important;
            border-radius: 8px !important;
            border: 1.5px solid #cbd5e1 !important;
            background-color: #ffffff !important;
            color: #475569 !important;
            transition: all 0.28s ease !important;
            text-decoration: none !important;
            cursor: pointer !important;
            padding: 0 !important;
        }

        .action-icon-btn:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent) !important;
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
                });
            }

            updateTimers();
            setInterval(updateTimers, 1000);

            // Initialize Bootstrap Tooltips for dark floating labels on hover
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
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
                <a href="{{ route('production.orders.show', ['order' => $firstOrder->id, 'tab' => 'vtab-operations']) }}"
                    class="fw-bold text-primary text-decoration-underline">Production Order Operations tab</a>
            @else
                <a href="{{ route('production.orders.index') }}" class="fw-bold text-primary text-decoration-underline">Production
                    Order Operations tab</a>
            @endif
            to allocate operators for live tracking. Operators can also view assigned tasks in the <a
                href="{{ route('production.mes.operator.dashboard') }}"
                class="fw-bold text-primary text-decoration-underline">MES Operator Console</a>.
        @else
            No active schedules on the shop floor. Release confirmed schedules from <a
                href="{{ route('production.schedules.index') }}"
                class="fw-bold text-primary text-decoration-underline">Production Schedules</a> and assign operators under the
            <a href="{{ route('production.orders.index') }}" class="fw-bold text-primary text-decoration-underline">Production
                Order Operations tab</a> to begin execution.
        @endif
    </x-ui.workflow-guide>

    <div class="erp-single-panel bg-transparent border-0 p-0">
        <div class="row">
            <div class="col-12">

                {{-- SECTION 1: Active Manufacturing Projects --}}
                <div class="d-flex align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0 d-flex align-items-center">
                        <i class="feather-grid me-2 text-primary"></i>{{ __('production.active_manufacturing_projects') }}
                    </h5>
                    <span
                        class="badge bg-soft-primary text-primary rounded-pill ms-2 fw-bold font-monospace">{{ $activeSchedules->count() }}</span>
                </div>

                @forelse($activeSchedules as $schedule)
                    @php
                        $order = $schedule->order;
                        $ops = $schedule->operations->sort(function ($a, $b) use ($order) {
                            $aOrderOp = $a->orderOperation ?? $a;
                            $bOrderOp = $b->orderOperation ?? $b;

                            $aIsSfg = ($aOrderOp->source_product_id && (int) $aOrderOp->source_product_id !== (int) $order->product_id) || ($aOrderOp->is_intermediate ?? false);
                            $bIsSfg = ($bOrderOp->source_product_id && (int) $bOrderOp->source_product_id !== (int) $order->product_id) || ($bOrderOp->is_intermediate ?? false);

                            if ($aIsSfg !== $bIsSfg) {
                                return $aIsSfg ? -1 : 1;
                            }

                            return $a->sequence <=> $b->sequence;
                        });
                        $totalOps = $ops->count();
                        $completedOps = $ops->where('status', 'completed')->count();
                        $progressPercent = $totalOps > 0 ? ($completedOps / $totalOps) * 100 : 0;
                        $activeOps = $ops->whereIn('status', ['running', 'paused', 'ready']);
                    @endphp

                    <div class="card mb-4 border border-light shadow-sm" style="border-radius: 12px; overflow: hidden;">
                        {{-- Card Header --}}
                        <div
                            class="card-header bg-light border-bottom border-light p-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h6 class="fw-bold text-dark mb-1 fs-14 d-flex align-items-center gap-2">
                                    <i class="feather-box text-primary"></i>
                                    <a href="{{ route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-operations']) }}"
                                        class="text-primary fw-bold text-decoration-underline"
                                        title="Assign operators under Operations tab">{{ $order->order_number ?? '' }}</a>
                                    <span class="text-muted">&middot;</span>
                                    <span class="fw-bold">{{ $order->product->name ?? 'Unknown Product' }}</span>
                                </h6>
                                <div class="text-muted fs-11">
                                    {{ __('production.schedule') }}: <strong
                                        class="text-secondary">{{ $schedule->schedule_number }}</strong>
                                    <span class="mx-2">|</span>
                                    {{ __('production.quantity') ?? 'Quantity' }}: <strong
                                        class="text-secondary">{{ (int) $order->quantity_ordered }}
                                        {{ __('production.units') }}</strong>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-soft-success text-success fs-10 px-2 py-1 rounded-pill mb-1">
                                    {{ __('production.steps_complete', ['completed' => $completedOps, 'total' => $totalOps]) }}
                                </span>
                                <div class="progress progress-sm bg-white border" style="width: 150px; height: 6px;">
                                    <div class="progress-bar bg-success" role="progressbar"
                                        style="width: {{ $progressPercent }}%"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Card Body: Odoo Form Component Table Grid --}}
                        <div class="card-body p-0 bg-white">
                            <x-ui.odoo-form-ui type="table" class="align-middle mb-0 fs-11" style="width: 100%;">
                                <thead class="bg-soft-primary text-primary fw-bold text-uppercase border-bottom">
                                    <tr>
                                        <th class="ps-3 text-center" style="width: 4%;">S.No.</th>
                                        <th style="width: 9%;">Action</th>
                                        <th style="width: 16%;">Item Details</th>
                                        <th style="width: 14%;">Process</th>
                                        <th style="width: 12%;">Workstation</th>
                                        <th style="width: 7%;">Shifts</th>
                                        <th style="width: 9%;">Schedule Start</th>
                                        <th style="width: 9%;">Schedule Finish</th>
                                        <th class="text-center" style="width: 5%;">MO Qty</th>
                                        <th style="width: 8%;">Actual Start</th>
                                        <th style="width: 8%;">Actual Finish</th>
                                        <th class="text-center" style="width: 5%;">Done Qty</th>
                                        <th class="text-center" style="width: 5%;">Pending Qty</th>
                                        <th class="pe-3" style="width: 8%;">Assign To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ops as $idx => $op)
                                        @php
                                            $orderOp = $op->orderOperation ?? $op;
                                            $itemName = $orderOp->sourceProduct->name ?? $order->product->name ?? 'Item';
                                            $itemNotes = $orderOp->notes ?? $op->remarks ?? '';

                                            $moQty = 0.0;
                                            if ((float) ($orderOp->target_produced_qty ?? 0) > 0) {
                                                $moQty = (float) $orderOp->target_produced_qty;
                                            } elseif ($orderOp->source_product_id && (int) $orderOp->source_product_id !== (int) $order->product_id) {
                                                $bomItem = \App\Domains\Production\Models\ProductionBomItem::where('tenant_id', $op->tenant_id)
                                                    ->where('bom_id', $order->bom_id)
                                                    ->where('material_id', $orderOp->source_product_id)
                                                    ->first();
                                                $ratio = ($bomItem && (float) $bomItem->quantity > 0) ? (float) $bomItem->quantity : 1.0;
                                                $moQty = (float) $order->quantity_ordered * $ratio;
                                            } else {
                                                $moQty = (float) ($order->quantity_ordered ?? 0.0);
                                            }

                                            $doneQty = (float) ($orderOp->quantity_produced ?? 0.0);
                                            $pendingQty = max(0.0, $moQty - $doneQty);
                                            $isCompleted = ($op->status === 'completed') || ($doneQty >= $moQty && $moQty > 0);

                                            $assignedName = $op->assignedOperator->name ?? $orderOp->operator->name ?? 'Unassigned';
                                        @endphp

                                        <tr
                                            class="{{ $isCompleted ? 'bg-soft-success-subtle' : ($op->status === 'running' ? 'bg-soft-primary-subtle' : '') }}">
                                            <td class="ps-3 fw-bold text-muted text-center align-top">{{ $loop->iteration }}</td>

                                            {{-- Action Column --}}
                                            <td class="align-top">
                                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                                    @if($isCompleted)
                                                        <span
                                                            class="badge bg-success rounded-circle me-1 d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                                            style="width: 26px; height: 26px; min-width: 26px; min-height: 26px; padding: 0;"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            title="Process Completed (Done Qty Reached)">
                                                            <i class="feather-check text-white fs-12"></i>
                                                        </span>
                                                        <span class="d-inline-block" data-bs-toggle="tooltip" data-bs-placement="top"
                                                            title="Log Additional Output">
                                                            <button type="button" class="action-icon-btn view-btn"
                                                                data-bs-toggle="modal" data-bs-target="#completeModal{{ $op->id }}">
                                                                <i class="feather-rotate-cw fs-12"></i>
                                                            </button>
                                                        </span>
                                                    @elseif($op->status === 'running')
                                                        <form method="POST" action="{{ route('production.mes.pause', $op->id) }}"
                                                            class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="action-icon-btn border-warning text-warning"
                                                                data-bs-toggle="tooltip" data-bs-placement="top"
                                                                title="Pause Process Execution">
                                                                <i class="feather-pause fs-12"></i>
                                                            </button>
                                                        </form>
                                                        <span class="d-inline-block ms-1" data-bs-toggle="tooltip"
                                                            data-bs-placement="top" title="Add Production Log">
                                                            <button type="button" class="action-icon-btn view-btn"
                                                                data-bs-toggle="modal" data-bs-target="#completeModal{{ $op->id }}">
                                                                <i class="feather-check-circle fs-12"></i>
                                                            </button>
                                                        </span>
                                                    @elseif($op->status === 'paused')
                                                        <form method="POST" action="{{ route('production.mes.resume', $op->id) }}"
                                                            class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="action-icon-btn border-primary text-primary"
                                                                data-bs-toggle="tooltip" data-bs-placement="top"
                                                                title="Resume Process Execution">
                                                                <i class="feather-play fs-12"></i>
                                                            </button>
                                                        </form>
                                                        <span class="d-inline-block ms-1" data-bs-toggle="tooltip"
                                                            data-bs-placement="top" title="Add Production Log">
                                                            <button type="button" class="action-icon-btn view-btn"
                                                                data-bs-toggle="modal" data-bs-target="#completeModal{{ $op->id }}">
                                                                <i class="feather-check-circle fs-12"></i>
                                                            </button>
                                                        </span>
                                                    @elseif($op->status === 'ready')
                                                        <form method="POST" action="{{ route('production.mes.start', $op->id) }}"
                                                            class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-xs btn-success p-1 px-2 fw-semibold"
                                                                data-bs-toggle="tooltip" data-bs-placement="top"
                                                                title="Start Process Execution">
                                                                <i class="feather-play me-1 fs-12"></i>Start
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="badge bg-light text-muted border fs-10" data-bs-toggle="tooltip"
                                                            data-bs-placement="top" title="Waiting for Upstream Operation Completion"><i
                                                                class="feather-lock me-1"></i>Waiting</span>
                                                    @endif
                                                </div>
                                            </td>

                                            {{-- Item Details (Wrapped Multi-line, NO TRUNCATE) --}}
                                            <td class="align-top">
                                                <strong class="text-dark d-block fs-11 text-wrap mb-0.5" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" title="{{ $itemName }}">{{ $itemName }}</strong>
                                                @if($itemNotes)
                                                    <small class="text-muted fs-10 italic text-wrap d-block">Notes:
                                                        {{ $itemNotes }}</small>
                                                @endif
                                            </td>

                                            {{-- Process Name & Live Timer (Wrapped Multi-line, NO TRUNCATE) --}}
                                            <td class="align-top">
                                                <span class="fw-bold text-primary d-block text-wrap mb-0.5" data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="{{ $orderOp->name ?? '' }}">{{ $orderOp->name ?? 'Op #' . $op->sequence }}</span>
                                                <small class="text-muted d-block fs-9">Seq: {{ $op->sequence }}</small>
                                                @if($op->status === 'running')
                                                    <div class="mt-1">
                                                        <span
                                                            class="badge bg-soft-success text-success border font-monospace mes-timer-block shadow-none py-1 px-1.5 fs-10"
                                                            data-start="{{ $op->actual_start ? $op->actual_start->toISOString() : '' }}"
                                                            data-planned-start="{{ $op->planned_start ? $op->planned_start->toISOString() : '' }}"
                                                            data-finish="{{ $op->planned_finish ? $op->planned_finish->toISOString() : '' }}"
                                                            data-status="{{ $op->status }}"
                                                            data-accumulated-paused-seconds="{{ $op->accumulated_paused_seconds ?? 0 }}"
                                                            data-last-paused-at="{{ $op->last_paused_at ? $op->last_paused_at->toISOString() : '' }}"
                                                            data-server-time="{{ now()->toISOString() }}" data-bs-toggle="tooltip"
                                                            data-bs-placement="top" title="Active Run Time (Excluding Paused Time)">
                                                            <i class="feather-clock me-1"></i><span
                                                                class="timer-elapsed">00:00:00</span>
                                                        </span>
                                                    </div>
                                                @elseif($op->status === 'paused')
                                                    <div class="mt-1">
                                                        <span
                                                            class="badge bg-soft-warning text-warning border font-monospace py-1 px-1.5 fs-10"
                                                            data-bs-toggle="tooltip" data-bs-placement="top" title="Operation Paused">
                                                            <i class="feather-pause-circle me-1"></i>Paused
                                                        </span>
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- Workstation (Wrapped Multi-line, NO TRUNCATE) --}}
                                            <td class="align-top">
                                                <span class="fw-semibold text-dark d-block text-wrap" data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="{{ $op->workCenter->name ?? '' }}">{{ $op->workCenter->name ?? 'Workstation' }}</span>
                                                @if($op->machine)
                                                    <small class="text-muted d-block fs-9 text-wrap mt-0.5"><i
                                                            class="feather-cpu me-1"></i>{{ $op->machine->name }}</small>
                                                @endif
                                            </td>

                                            {{-- Shifts --}}
                                            <td class="align-top">
                                                <span class="badge bg-soft-info text-info border fs-10" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" title="Active Shift: Day Shift">Day Shift</span>
                                                <small
                                                    class="text-muted d-block fs-9 mt-0.5">Target={{ number_format($moQty, 0) }}</small>
                                            </td>

                                            {{-- Schedule Start --}}
                                            <td class="text-muted fs-11 align-top">
                                                {{ $op->planned_start ? $op->planned_start->format('d/m H:i') : '-' }}</td>

                                            {{-- Schedule Finish --}}
                                            <td class="text-muted fs-11 align-top">
                                                {{ $op->planned_finish ? $op->planned_finish->format('d/m H:i') : '-' }}</td>

                                            {{-- MO Qty --}}
                                            <td class="text-center font-monospace fw-bold align-top fs-13">
                                                {{ number_format($moQty, 0) }}</td>

                                            {{-- Actual Start --}}
                                            <td class="text-muted fs-11 align-top">
                                                {{ $op->actual_start ? $op->actual_start->format('d/m H:i') : '-' }}</td>

                                            {{-- Actual Finish --}}
                                            <td class="text-muted fs-11 align-top">
                                                {{ $op->actual_finish ? $op->actual_finish->format('d/m H:i') : '-' }}</td>

                                            {{-- Done Qty --}}
                                            <td class="text-center align-top">
                                                @if($isCompleted)
                                                    <span
                                                        class="badge bg-success text-white font-monospace fs-13 shadow-sm d-inline-flex align-items-center justify-content-center flex-shrink-0 mx-auto"
                                                        style="width: 32px; height: 32px; min-width: 32px; min-height: 32px; border-radius: 50%; padding: 0;"
                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                        title="Done Quantity: {{ number_format($doneQty, 0) }}">
                                                        {{ number_format($doneQty, 0) }}
                                                    </span>
                                                @else
                                                    <span
                                                        class="badge bg-soft-primary text-primary font-monospace fs-13 d-inline-flex align-items-center justify-content-center flex-shrink-0 mx-auto"
                                                        style="width: 32px; height: 32px; min-width: 32px; min-height: 32px; border-radius: 50%; padding: 0;"
                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                        title="Done Quantity: {{ number_format($doneQty, 0) }}">
                                                        {{ number_format($doneQty, 0) }}
                                                    </span>
                                                @endif
                                                <small class="text-muted d-block fs-9 mt-1">Shift
                                                    {{ number_format($doneQty, 0) }}</small>
                                            </td>

                                            {{-- Pending Qty --}}
                                            <td class="text-center align-top">
                                                <span class="font-monospace text-danger fw-extrabold fs-14 d-block"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="Pending Quantity: {{ number_format($pendingQty, 0) }}">
                                                    {{ number_format($pendingQty, 0) }}
                                                </span>
                                            </td>

                                            {{-- Assign To (Wrapped Multi-line, NO TRUNCATE) --}}
                                            <td class="pe-3 align-top">
                                                <span
                                                    class="badge bg-soft-secondary text-dark border fs-10 text-wrap d-inline-block"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="Assigned Operator: {{ $assignedName }}">
                                                    <i class="feather-user me-1"></i>{{ $assignedName }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    {{-- ADD PRODUCTION Modals --}}
                    @foreach($ops as $activeOp)
                        @php
                            $activeOrderOp = $activeOp->orderOperation ?? $activeOp;
                            $activeOrder = $activeOp->schedule->order ?? $activeOp->order;
                            $activeTargetQty = 0.0;
                            if ($activeOrderOp && (float) ($activeOrderOp->target_produced_qty ?? 0) > 0) {
                                $activeTargetQty = (float) $activeOrderOp->target_produced_qty;
                            } elseif ($activeOrderOp && $activeOrder && $activeOrderOp->source_product_id && (int) $activeOrderOp->source_product_id !== (int) $activeOrder->product_id) {
                                $bomItem = \App\Domains\Production\Models\ProductionBomItem::where('tenant_id', $activeOp->tenant_id ?? require_tenant_id())
                                    ->where('bom_id', $activeOrder->bom_id)
                                    ->where('material_id', $activeOrderOp->source_product_id)
                                    ->first();
                                $ratio = ($bomItem && (float) $bomItem->quantity > 0) ? (float) $bomItem->quantity : 1.0;
                                $activeTargetQty = (float) $activeOrder->quantity_ordered * $ratio;
                            } else {
                                $activeTargetQty = (float) ($activeOrder->quantity_ordered ?? 0.0);
                            }

                            $activeScrapQty = max(
                                (float) ($activeOrderOp->quantity_scrapped ?? 0.0),
                                (float) \App\Domains\Production\Models\ProductionOrderScrap::where('tenant_id', $activeOp->tenant_id ?? require_tenant_id())
                                    ->where('production_order_id', $activeOp->production_order_id ?? $activeOp->order_id)
                                    ->where('production_order_operation_id', $activeOp->production_order_operation_id ?? $activeOp->id)
                                    ->sum('quantity')
                            );
                            $activeRemainingToComplete = max(0.0, $activeTargetQty - (($activeOrderOp->quantity_produced ?? 0.0) + $activeScrapQty + ($activeOrderOp->quantity_rejected ?? 0.0)));
                        @endphp
                        <x-ui.modal id="completeModal{{ $activeOp->id }}"
                            title="ADD PRODUCTION OF — {{ html_entity_decode($activeOrderOp->name ?? 'Op #' . $activeOp->sequence, ENT_QUOTES, 'UTF-8') }}"
                            class="text-start" size="lg">
                            <form method="POST" action="{{ route('production.mes.complete', $activeOp->id) }}"
                                id="completeForm{{ $activeOp->id }}">
                                @csrf
                                <div class="card border-0 bg-light p-3 mb-3 rounded">
                                    <h6 class="fw-bold text-primary mb-1 fs-12 text-uppercase">
                                        <i class="feather-edit me-1"></i>Add Production OF -
                                        {{ $activeOrderOp->name ?? 'Process' }}, {{ $activeOp->workCenter->name ?? 'Workstation' }}
                                        For {{ $activeOrderOp->sourceProduct->name ?? $activeOrder->product->name ?? 'Item' }}
                                    </h6>
                                    <div class="text-muted fs-11">How many have you Done?</div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <x-ui.odoo-form-ui type="input" label="Date" name="log_date" inputType="date"
                                            value="{{ date('Y-m-d') }}" :required="true" />
                                    </div>
                                    <div class="col-md-4">
                                        <x-ui.odoo-form-ui type="select" label="Employee" name="operator_id">
                                            <option value="">None selected</option>
                                            @foreach(\App\Models\User::pluck('name', 'id') as $uId => $uName)
                                                <option value="{{ $uId }}" {{ (int) ($activeOp->assigned_operator_id ?? auth()->id()) === (int) $uId ? 'selected' : '' }}>{{ $uName }}</option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-md-4">
                                        <x-ui.odoo-form-ui type="select" label="Shift" name="shift_name">
                                            <option value="Day Shift" selected>Day Shift (Standard)</option>
                                            <option value="Night Shift">Night Shift</option>
                                            <option value="Morning Shift">Morning Shift A</option>
                                        </x-ui.odoo-form-ui>
                                    </div>

                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="Start Time" name="start_time" inputType="time"
                                            value="{{ $activeOp->actual_start ? $activeOp->actual_start->format('H:i') : '09:00' }}" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="End Time" name="end_time" inputType="time"
                                            value="{{ date('H:i') }}" />
                                    </div>

                                    <div class="col-md-4">
                                        <x-ui.odoo-form-ui type="input" label="Quantity Done" name="quantity_produced"
                                            inputType="number" step="any" value="{{ $activeRemainingToComplete }}"
                                            :required="true" />
                                    </div>
                                    <div class="col-md-4">
                                        <x-ui.odoo-form-ui type="input" label="Quantity Rejected" name="quantity_rejected"
                                            inputType="number" step="any" value="0" />
                                    </div>
                                    <div class="col-md-4">
                                        <x-ui.odoo-form-ui type="input" label="Quantity Scrapped" name="quantity_scrapped"
                                            inputType="number" step="any" value="0" />
                                    </div>

                                    <div class="col-md-12">
                                        <x-ui.odoo-form-ui type="textarea" label="Remarks" name="remarks"
                                            placeholder="Optional comments..." rows="2" />
                                    </div>
                                </div>
                            </form>
                            <x-slot name="footer">
                                <button type="button" class="btn btn-secondary"
                                    data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                                <button type="submit" class="btn btn-success px-4"
                                    onclick="document.getElementById('completeForm{{ $activeOp->id }}').submit();">
                                    <i class="feather-check me-1"></i>Save Production Log
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
        </div>
    </div>
@endsection