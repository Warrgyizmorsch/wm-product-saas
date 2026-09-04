@extends('layouts.duralux')

@section('title', __('production.shop_floor_dashboard') . ' | SaaS ERP')
@section('page-title', __('production.shop_floor_operator_dashboard'))
@section('breadcrumb', __('production.mes_dashboard'))

@push('styles')
    <style>
        .btn-purple {
            background-color: #6b21a8 !important;
            border-color: #6b21a8 !important;
            color: #ffffff !important;
        }

        .btn-purple:hover {
            background-color: #581c87 !important;
            border-color: #581c87 !important;
            color: #ffffff !important;
        }

        .bg-purple {
            background-color: #6b21a8 !important;
            color: #ffffff !important;
        }

        .bg-soft-purple {
            background-color: rgba(107, 33, 168, 0.08) !important;
        }

        .bg-soft-purple-subtle {
            background-color: rgba(107, 33, 168, 0.04) !important;
        }

        .text-purple {
            color: #6b21a8 !important;
        }

        .border-purple-subtle {
            border-color: rgba(107, 33, 168, 0.25) !important;
        }

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

        .action-icon-btn.action-start-btn {
            background-color: #10b981 !important;
            border-color: #10b981 !important;
            color: #ffffff !important;
        }

        .action-icon-btn.action-start-btn:hover {
            background-color: #059669 !important;
            border-color: #059669 !important;
            color: #ffffff !important;
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
                        $completedOps = $ops->filter(function ($o) use ($order) {
                            $orderOp = $o->orderOperation;
                            $targetQty = (float) ($orderOp?->target_produced_qty ?: ($order->quantity_ordered ?: 0));
                            $doneQty = (float) ($orderOp?->quantity_produced ?: 0);
                            return $o->status === 'completed' || ($orderOp && $orderOp->status === 'completed') || ($targetQty > 0 && $doneQty >= ($targetQty - 0.0001));
                        })->count();
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
                                        <th style="width: 10%;">Action</th>
                                        <th style="width: 15%;">Item Details</th>
                                        <th style="width: 14%;">Process</th>
                                        <th style="width: 12%;">Workstation</th>
                                        <th style="width: 7%;">Shifts</th>
                                        <th style="width: 9%;">Schedule Start</th>
                                        <th style="width: 9%;">Schedule Finish</th>
                                        <th class="text-center" style="width: 5%;">Target Qty</th>
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

                                            $isOutsourced = (bool) ($orderOp->is_external || $orderOp->isOutsourced() || $op->work_center_id === null);
                                            $vendorName = $orderOp->vendor->name ?? $orderOp->vendor_name ?? 'Outsourced Vendor';
                                            $challans = $orderOp->relationLoaded('deliveryChallans') ? $orderOp->deliveryChallans : ($orderOp->deliveryChallans ?? collect());
                                            $latestChallan = $orderOp->relationLoaded('latestDeliveryChallan') ? $orderOp->latestDeliveryChallan : ($orderOp->latestDeliveryChallan ?? $challans->first());

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

                                            $isQcRequired = (bool) ($orderOp->quality_required || ($orderOp->routingOperation?->quality_required ?? false));
                                            $pendingQcQty = app(\App\Domains\Production\Services\MesExecutionService::class)->getPendingQcQuantity($orderOp->id);
                                            $acceptedQty = (float) ($orderOp->quantity_produced ?? 0.0);
                                            $rejectedQty = (float) ($orderOp->quantity_rejected ?? 0.0);
                                            $scrappedQty = (float) ($orderOp->quantity_scrapped ?? 0.0);
                                            $outputPid = $orderOp->product_id ?? $orderOp->source_product_id ?? $order->product_id;
                                            $rmScrapQty = (float) \App\Domains\Production\Models\ProductionOrderScrap::where('tenant_id', $op->tenant_id)
                                                ->where('production_order_id', $op->production_order_id ?? $op->order_id)
                                                ->where('production_order_operation_id', $orderOp->id)
                                                ->where('product_id', '!=', $outputPid)
                                                ->sum('quantity');

                                            $activeAssignment = $orderOp->relationLoaded('operatorAssignments')
                                                ? $orderOp->operatorAssignments->whereIn('status', ['assigned', 'accepted'])->sortByDesc('id')->first()
                                                : \App\Domains\Production\Models\ProductionOperatorAssignment::where('tenant_id', $op->tenant_id)
                                                    ->where('production_order_operation_id', $orderOp->id)
                                                    ->whereIn('status', ['assigned', 'accepted'])
                                                    ->latest('id')
                                                    ->first();

                                            $assignedUser = $activeAssignment?->user ?? $orderOp->operator ?? null;
                                            $assignmentStatus = $activeAssignment?->status ?? ($assignedUser ? 'accepted' : null);

                                            if ($assignmentStatus === 'assigned') {
                                                $assignedName = 'Pending: ' . ($assignedUser->name ?? 'Operator');
                                                $assignedBadgeClass = 'bg-soft-warning text-dark border border-warning-subtle';
                                                $assignedIcon = 'feather-clock text-warning';
                                                $assignedTooltip = 'Pending Acceptance by ' . ($assignedUser->name ?? 'Operator');
                                            } elseif ($assignmentStatus === 'accepted' || $assignedUser) {
                                                $assignedName = $assignedUser->name ?? 'Assigned Operator';
                                                $assignedBadgeClass = 'bg-soft-success text-success border border-success-subtle';
                                                $assignedIcon = 'feather-user-check text-success';
                                                $assignedTooltip = 'Assigned & Accepted by ' . ($assignedUser->name ?? '');
                                            } else {
                                                $assignedName = 'Unassigned';
                                                $assignedBadgeClass = 'bg-soft-secondary text-dark border';
                                                $assignedIcon = 'feather-user';
                                                $assignedTooltip = 'No Operator Assigned';
                                            }
                                        @endphp

                                        <tr
                                            class="{{ $isCompleted ? 'bg-soft-success-subtle' : ($op->status === 'running' ? 'bg-soft-primary-subtle' : ($isOutsourced ? 'bg-soft-purple-subtle' : '')) }}">
                                            <td class="ps-3 fw-bold text-muted text-center align-top">{{ $loop->iteration }}</td>

                                            {{-- Action Column --}}
                                            <td class="align-top">
                                                @if($isOutsourced)
                                                    @php
                                                        $opSupplyType = $orderOp->material_supply_type ?? 'company_supplied';
                                                        $isVendorSupplied = ($opSupplyType === 'vendor_supplied');
                                                     @endphp
                                                    <div class="d-flex flex-column align-items-start gap-1">
                                                        <div class="d-flex align-items-center gap-1">
                                                            @if($isCompleted)
                                                                <span
                                                                    class="badge bg-success rounded-circle me-1 d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                                                    style="width: 26px; height: 26px; min-width: 26px; min-height: 26px; padding: 0;"
                                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                                    title="Process Completed (Subcontract Received & Done)">
                                                                    <i class="feather-check text-white fs-12"></i>
                                                                </span>
                                                            @endif
                                                            @if($isVendorSupplied)
                                                                <button type="button"
                                                                    class="btn btn-sm btn-info fw-semibold px-2 py-1 fs-11 text-white shadow-sm d-inline-flex align-items-center gap-1 text-nowrap"
                                                                    data-bs-toggle="modal" data-bs-target="#completeModal{{ $op->id }}"
                                                                    title="Record Subcontract Receipt (Vendor Supplied - No Company Material Sent)">
                                                                    <i class="feather-download-cloud fs-12"></i>Record Subcontract Receipt
                                                                </button>
                                                            @else
                                                                <button type="button"
                                                                    class="btn btn-sm btn-purple fw-semibold px-2 py-1 fs-11 text-white shadow-sm d-inline-flex align-items-center gap-1 text-nowrap"
                                                                    data-bs-toggle="modal" data-bs-target="#manageChallanModal{{ $op->id }}"
                                                                    title="Manage Subcontract Delivery Challans (Company Material Sent)">
                                                                    <i class="feather-file-text fs-12"></i>Manage Challan
                                                                </button>
                                                            @endif
                                                        </div>
                                                        @if($isVendorSupplied)
                                                            <span class="badge bg-soft-info text-info border border-info-subtle fs-9">
                                                                <i class="feather-box me-1"></i>Vendor Supplied Material
                                                            </span>
                                                        @elseif($latestChallan)
                                                            @if($latestChallan->status === 'draft')
                                                                <a href="{{ route('production.subcontract.delivery-challans.show', $latestChallan->id) }}"
                                                                    class="badge bg-soft-warning text-dark border border-warning fs-9 text-decoration-none"
                                                                    title="Draft Gate Pass pending dispatch">
                                                                    <i class="feather-clock me-1"></i>Draft Gate Pass
                                                                </a>
                                                            @elseif($latestChallan->status === 'dispatched')
                                                                <a href="{{ route('production.subcontract.delivery-challans.show', $latestChallan->id) }}"
                                                                    class="badge bg-soft-info text-info border border-info-subtle fs-9 text-decoration-none"
                                                                    title="Material Dispatched to Vendor">
                                                                    <i class="feather-truck me-1"></i>Dispatched
                                                                </a>
                                                            @elseif($latestChallan->status === 'completed')
                                                                <a href="{{ route('production.subcontract.delivery-challans.show', $latestChallan->id) }}"
                                                                    class="badge bg-soft-success text-success border border-success-subtle fs-9 text-decoration-none"
                                                                    title="Subcontract Received & Completed">
                                                                    <i class="feather-check-circle me-1"></i>Received
                                                                </a>
                                                            @endif
                                                        @endif
                                                    </div>
                                                @else
                                                    <div class="d-flex flex-column align-items-start gap-1">
                                                        {{-- Primary Action Toolbar (Icon Only Horizontal Row) --}}
                                                        <div class="d-inline-flex align-items-center gap-1 text-nowrap flex-nowrap">
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
                                                                    <button type="submit" class="action-icon-btn"
                                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                                        title="Pause Process Execution">
                                                                        <i class="feather-pause fs-12"></i>
                                                                    </button>
                                                                </form>
                                                                <span class="d-inline-block" data-bs-toggle="tooltip"
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
                                                                    <button type="submit" class="action-icon-btn"
                                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                                        title="Resume Process Execution">
                                                                        <i class="feather-play fs-12"></i>
                                                                    </button>
                                                                </form>
                                                                <span class="d-inline-block" data-bs-toggle="tooltip"
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
                                                                    <button type="submit" class="action-icon-btn action-start-btn"
                                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                                        title="Start Process Execution">
                                                                        <i class="feather-play fs-12 text-white"></i>
                                                                    </button>
                                                                </form>
                                                            @else
                                                                <span class="action-icon-btn opacity-65" data-bs-toggle="tooltip"
                                                                    data-bs-placement="top" title="Waiting for Upstream Operation Completion">
                                                                    <i class="feather-lock fs-12"></i>
                                                                </span>
                                                            @endif

                                                            @if(!$isCompleted)
                                                                <span class="d-inline-block" data-bs-toggle="tooltip" data-bs-placement="top" title="Record Operational Scrap">
                                                                    <button type="button" class="action-icon-btn" data-bs-toggle="modal" data-bs-target="#scrapModal{{ $op->id }}">
                                                                        <i class="feather-trash-2 fs-12"></i>
                                                                    </button>
                                                                </span>
                                                            @endif
                                                        </div>

                                                        {{-- QC & Disposition Context Actions --}}
                                                        @if($isQcRequired || $rejectedQty > 0)
                                                            <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                                                @if($isQcRequired)
                                                                    @if($pendingQcQty > 0)
                                                                        <button type="button"
                                                                            class="btn btn-xs btn-warning text-dark fw-bold p-1 px-1.5 fs-10 d-inline-flex align-items-center gap-1 shadow-sm text-nowrap"
                                                                            data-bs-toggle="modal" data-bs-target="#qcModal{{ $op->id }}"
                                                                            title="Run Quality Inspection">
                                                                            <i class="feather-shield-check fs-11"></i>Run QC
                                                                            ({{ number_format($pendingQcQty, 0) }})
                                                                        </button>
                                                                    @elseif($isCompleted)
                                                                        <span
                                                                            class="badge bg-soft-success text-success border border-success-subtle p-1 px-1.5 fs-10 d-inline-flex align-items-center gap-1 text-nowrap">
                                                                            <i class="feather-check-circle fs-11"></i>QC Passed
                                                                        </span>
                                                                    @else
                                                                        <button type="button"
                                                                            class="btn btn-xs btn-outline-warning text-dark fw-bold p-1 px-1.5 fs-10 d-inline-flex align-items-center gap-1 shadow-sm text-nowrap"
                                                                            data-bs-toggle="modal" data-bs-target="#qcModal{{ $op->id }}"
                                                                            title="Run Inline Quality Inspection">
                                                                            <i class="feather-shield-check fs-11"></i>Run QC
                                                                        </button>
                                                                    @endif
                                                                @endif
                                                                @if($rejectedQty > 0)
                                                                    <button type="button"
                                                                        class="btn btn-xs btn-danger text-white fw-bold p-1 px-1.5 fs-10 d-inline-flex align-items-center gap-1 shadow-sm text-nowrap"
                                                                        data-bs-toggle="modal" data-bs-target="#dispositionModal{{ $op->id }}"
                                                                        title="Disposition Rejected Qty">
                                                                        <i class="feather-alert-triangle fs-11"></i>Rework / Scrap
                                                                        ({{ number_format($rejectedQty, 0) }})
                                                                    </button>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
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

                                            {{-- Process Name & Live Timer + Context Metrics --}}
                                            <td class="align-top">
                                                <span class="fw-bold text-primary d-block text-wrap mb-0.5" data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="{{ $orderOp->name ?? '' }}">{{ $orderOp->name ?? 'Op #' . $op->sequence }}</span>
                                                <div class="d-flex align-items-center gap-1">
                                                    <small class="text-muted fs-9">Seq: {{ $op->sequence }}</small>
                                                    @if($isQcRequired)
                                                        <span
                                                            class="badge bg-soft-info text-info border border-info-subtle fs-9 px-1 py-0"><i
                                                                class="feather-shield me-0.5"></i>QC</span>
                                                    @endif
                                                </div>

                                                {{-- Context Metrics Bar --}}
                                                <div class="d-flex flex-wrap align-items-center gap-1 mt-1 font-monospace fs-10">
                                                    <span class="badge bg-light text-dark border" title="Target Net Good Output">Tgt: {{ number_format($moQty, 0) }}</span>
                                                    @if($isQcRequired)
                                                        <span class="badge {{ $pendingQcQty > 0 ? 'bg-soft-warning text-dark border border-warning' : 'bg-light text-muted border' }}" title="Pending QC Inspection">QC: {{ number_format($pendingQcQty, 0) }}</span>
                                                    @endif
                                                    <span class="badge bg-soft-success text-success border border-success-subtle" title="Accepted Good Usable Qty">Acc: {{ number_format($acceptedQty, 0) }}</span>
                                                    @if($rejectedQty > 0)
                                                        <span class="badge bg-soft-danger text-danger border border-danger-subtle" title="Rejected Qty Pending Disposition">Rej: {{ number_format($rejectedQty, 0) }}</span>
                                                    @endif
                                                    @if($scrappedQty > 0)
                                                        <span class="badge bg-soft-secondary text-secondary border" title="Scrapped Output Qty">Scrp: {{ number_format($scrappedQty, 0) }}</span>
                                                    @endif
                                                    @if($rmScrapQty > 0)
                                                        <span class="badge bg-soft-warning text-warning border border-warning-subtle" title="Raw Material Scrap Logged (Replacement Requested)">RM Scrp: {{ number_format($rmScrapQty, 0) }}</span>
                                                    @endif
                                                </div>

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
                                                @if($isOutsourced)
                                                    <span class="fw-bold text-purple d-block text-wrap fs-11" data-bs-toggle="tooltip"
                                                        data-bs-placement="top" title="Outsourced Vendor: {{ $vendorName }}">
                                                        <i class="feather-truck me-1 text-purple"></i>{{ $vendorName }}
                                                    </span>
                                                    <small
                                                        class="badge bg-soft-purple text-purple fs-9 mt-0.5 border border-purple-subtle d-inline-block">Outsourced
                                                        Process</small>
                                                @else
                                                    <span class="fw-semibold text-dark d-block text-wrap" data-bs-toggle="tooltip"
                                                        data-bs-placement="top"
                                                        title="{{ $op->workCenter->name ?? '' }}">{{ $op->workCenter->name ?? 'Workstation' }}</span>
                                                    @if($op->machine)
                                                        <small class="text-muted d-block fs-9 text-wrap mt-0.5"><i
                                                                class="feather-cpu me-1"></i>{{ $op->machine->name }}</small>
                                                    @endif
                                                @endif
                                            </td>

                                            {{-- Shifts --}}
                                            <td class="align-top">
                                                @if($isOutsourced)
                                                    <span
                                                        class="badge bg-soft-purple text-purple border border-purple-subtle fs-10">Subcontract</span>
                                                    <small class="text-muted d-block fs-9 mt-0.5">Lead Time:
                                                        {{ $orderOp->subcontract_lead_time_days ?? 0 }}d</small>
                                                @else
                                                    <span class="badge bg-soft-info text-info border fs-10" data-bs-toggle="tooltip"
                                                        data-bs-placement="top" title="Active Shift: Day Shift">Day Shift</span>
                                                    <small
                                                        class="text-muted d-block fs-9 mt-0.5">Target={{ number_format($moQty, 0) }}</small>
                                                @endif
                                            </td>

                                            {{-- Schedule Start --}}
                                            <td class="text-muted fs-11 align-top">
                                                {{ $op->planned_start ? $op->planned_start->format('d/m H:i') : '-' }}
                                            </td>

                                            {{-- Schedule Finish --}}
                                            <td class="text-muted fs-11 align-top">
                                                {{ $op->planned_finish ? $op->planned_finish->format('d/m H:i') : '-' }}
                                            </td>

                                            {{-- MO Qty --}}
                                            <td class="text-center font-monospace fw-bold align-top fs-13">
                                                {{ number_format($moQty, 0) }}
                                            </td>

                                            {{-- Actual Start --}}
                                            <td class="text-muted fs-11 align-top">
                                                {{ $op->actual_start ? $op->actual_start->format('d/m H:i') : '-' }}
                                            </td>

                                            {{-- Actual Finish --}}
                                            <td class="text-muted fs-11 align-top">
                                                {{ $op->actual_finish ? $op->actual_finish->format('d/m H:i') : '-' }}
                                            </td>

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
                                                @if($isOutsourced)
                                                    <span
                                                        class="badge bg-soft-purple text-purple border border-purple-subtle fs-10 text-wrap d-inline-block"
                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                        title="Subcontract Vendor: {{ $vendorName }}">
                                                        <i class="feather-briefcase me-1"></i>{{ $vendorName }}
                                                    </span>
                                                @else
                                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                                        <span class="badge {{ $assignedBadgeClass }} fs-10 text-wrap d-inline-block"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            title="{{ $assignedTooltip }}">
                                                            <i class="{{ $assignedIcon }} me-1"></i>{{ $assignedName }}
                                                        </span>
                                                        @if(!$isCompleted)
                                                            <button type="button"
                                                                class="btn btn-xs btn-outline-primary p-0.5 px-1 fs-10 border shadow-none"
                                                                title="{{ $assignedName !== 'Unassigned' ? 'Reassign Operator' : 'Assign Operator' }}"
                                                                data-bs-toggle="modal" data-bs-target="#assignOperatorModal{{ $op->id }}">
                                                                <i
                                                                    class="feather-user-plus me-1"></i>{{ $assignedName !== 'Unassigned' ? 'Edit' : 'Assign' }}
                                                            </button>
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    {{-- Modals Loop: Manage Challan for Outsourced Ops & Add Production for In-House Ops --}}
                    @foreach($ops as $activeOp)
                        @php
                            $activeOrderOp = $activeOp->orderOperation ?? $activeOp;
                            $activeOrder = $activeOp->schedule->order ?? $activeOp->order;
                            $isActiveOutsourced = (bool) ($activeOrderOp->is_external || $activeOrderOp->isOutsourced() || $activeOp->work_center_id === null);
                        @endphp

                        @php
                            $activeOpSupplyType = $activeOrderOp->material_supply_type ?? 'company_supplied';
                            $isActiveCompanySupplied = $isActiveOutsourced && ($activeOpSupplyType === 'company_supplied');
                        @endphp

                        @if($isActiveCompanySupplied)
                            @php
                                $activeVendorName = $activeOrderOp->vendor->name ?? $activeOrderOp->vendor_name ?? 'Outsourced Vendor';
                                $activeChallans = $activeOrderOp->relationLoaded('deliveryChallans') ? $activeOrderOp->deliveryChallans : ($activeOrderOp->deliveryChallans ?? collect());
                                if ($activeChallans->isEmpty() && $activeOrderOp->id) {
                                    $activeChallans = \App\Domains\Production\Models\DeliveryChallan::where('production_order_operation_id', $activeOrderOp->id)->latest()->get();
                                }
                            @endphp
                            <x-ui.modal id="manageChallanModal{{ $activeOp->id }}"
                                title="MANAGE CHALLAN — {{ html_entity_decode($activeOrderOp->name ?? 'Op #' . $activeOp->sequence, ENT_QUOTES, 'UTF-8') }}"
                                class="text-start" size="xl">
                                <div
                                    class="d-flex justify-content-between align-items-center bg-soft-purple p-3 rounded mb-3 border border-purple-subtle">
                                    <div>
                                        <h6 class="fw-bold text-purple mb-1">
                                            <i class="feather-truck me-2"></i>Outsourced Subcontract Operation (Company Supplied
                                            Material)
                                        </h6>
                                        <span class="text-muted fs-11">Supplier / Vendor: <strong>{{ $activeVendorName }}</strong> |
                                            Production Order: <strong>{{ $activeOrder->order_number ?? '' }}</strong></span>
                                    </div>
                                    <a href="{{ route('production.subcontract.delivery-challans.create', ['production_order_id' => $activeOp->production_order_id ?? $activeOrder->id, 'operation_id' => $activeOrderOp->id]) }}"
                                        class="btn btn-sm btn-purple fw-semibold shadow-sm">
                                        <i class="feather-plus me-1"></i>+ New Challan
                                    </a>
                                </div>

                                @if($activeChallans->count() > 0)
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle fs-12 mb-0 border">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="text-center" style="width: 5%;">S.No.</th>
                                                    <th style="width: 12%;">Date</th>
                                                    <th style="width: 16%;">Challan No#</th>
                                                    <th style="width: 16%;">Reference#</th>
                                                    <th style="width: 22%;">Supplier Name</th>
                                                    <th style="width: 14%;">Receive Status</th>
                                                    <th class="text-end" style="width: 15%;">Action Operation</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($activeChallans as $chIdx => $ch)
                                                    <tr>
                                                        <td class="text-center fw-bold text-muted">{{ $loop->iteration }}</td>
                                                        <td>{{ $ch->challan_date ? \Carbon\Carbon::parse($ch->challan_date)->format('d/m/Y') : '-' }}
                                                        </td>
                                                        <td>
                                                            <a href="{{ route('production.subcontract.delivery-challans.show', $ch->id) }}"
                                                                class="fw-bold text-purple text-decoration-underline">
                                                                {{ $ch->challan_number }}
                                                            </a>
                                                        </td>
                                                        <td class="text-muted">{{ $ch->reference_number ?? $activeOrder->order_number ?? '-' }}
                                                        </td>
                                                        <td><strong class="text-dark">{{ $ch->vendor->name ?? $activeVendorName }}</strong></td>
                                                        <td>
                                                            @if($ch->status === 'draft')
                                                                <span class="badge bg-soft-warning text-dark border border-warning">Draft (Gate
                                                                    Pass)</span>
                                                            @elseif($ch->status === 'dispatched')
                                                                <span class="badge bg-soft-info text-info border border-info-subtle">Dispatched (In
                                                                    Transit)</span>
                                                            @elseif($ch->status === 'completed')
                                                                <span
                                                                    class="badge bg-soft-success text-success border border-success-subtle">Received
                                                                    & Completed</span>
                                                            @else
                                                                <span class="badge bg-light text-dark border">{{ ucfirst($ch->status) }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-end">
                                                            <a href="{{ route('production.subcontract.delivery-challans.show', $ch->id) }}"
                                                                class="btn btn-xs btn-purple fw-semibold px-2 py-1">
                                                                <i class="feather-eye me-1"></i>Click To Receive Items
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-4 bg-light rounded border border-dashed">
                                        <i class="feather-file-text text-muted fs-36 mb-2 d-block"></i>
                                        <p class="text-muted fs-12 mb-2">No record available. Generate a Delivery Challan to dispatch
                                            material to {{ $activeVendorName }}.</p>
                                        <a href="{{ route('production.subcontract.delivery-challans.create', ['production_order_id' => $activeOp->production_order_id ?? $activeOrder->id, 'operation_id' => $activeOrderOp->id]) }}"
                                            class="btn btn-sm btn-purple fw-semibold shadow-sm">
                                            <i class="feather-plus me-1"></i>+ New Challan
                                        </a>
                                    </div>
                                @endif
                                <x-slot name="footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </x-slot>
                            </x-ui.modal>
                        @endif

                        @if(!$isActiveCompanySupplied)
                            @php
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

                                $activeOutputProductId = $activeOrderOp->product_id ?? $activeOrderOp->source_product_id ?? $activeOrder->product_id;
                                $activeScrapQty = max(
                                    (float) ($activeOrderOp->quantity_scrapped ?? 0.0),
                                    (float) \App\Domains\Production\Models\ProductionOrderScrap::where('tenant_id', $activeOp->tenant_id ?? require_tenant_id())
                                        ->where('production_order_id', $activeOp->production_order_id ?? $activeOp->order_id)
                                        ->where('production_order_operation_id', $activeOp->production_order_operation_id ?? $activeOp->id)
                                        ->where(function ($q) use ($activeOutputProductId) {
                                            $q->where('product_id', $activeOutputProductId)
                                                ->orWhereNull('product_id');
                                        })
                                        ->sum('quantity')
                                );
                                $activeRemainingToComplete = max(0.0, $activeTargetQty - (($activeOrderOp->quantity_produced ?? 0.0) + $activeScrapQty + ($activeOrderOp->quantity_rejected ?? 0.0)));
                            @endphp
                            <x-ui.modal id="completeModal{{ $activeOp->id }}"
                                title="{{ ($activeOrderOp->is_external && ($activeOrderOp->material_supply_type ?? 'company_supplied') === 'vendor_supplied') ? 'RECEIVE SUBCONTRACT GOODS (VENDOR SUPPLIED) — ' . html_entity_decode($activeOrderOp->name ?? 'Op #' . $activeOp->sequence, ENT_QUOTES, 'UTF-8') : 'ADD PRODUCTION OF — ' . html_entity_decode($activeOrderOp->name ?? 'Op #' . $activeOp->sequence, ENT_QUOTES, 'UTF-8') }}"
                                class="text-start" size="lg">
                                <form method="POST" action="{{ route('production.mes.complete', $activeOp->id) }}"
                                    id="completeForm{{ $activeOp->id }}">
                                    @csrf
                                    <div class="card border-0 bg-light p-3 mb-3 rounded">
                                        <h6 class="fw-bold text-primary mb-1 fs-12 text-uppercase">
                                            <i class="feather-edit me-1"></i>
                                            @if($activeOrderOp->is_external && ($activeOrderOp->material_supply_type ?? 'company_supplied') === 'vendor_supplied')
                                                Receive Subcontract Goods From {{ $activeOrderOp->vendor->name ?? 'Vendor' }} -
                                                {{ $activeOrderOp->name }}
                                            @else
                                                Add Production OF - {{ $activeOrderOp->name ?? 'Process' }},
                                                {{ $activeOp->workCenter->name ?? 'Workstation' }}
                                            @endif
                                            For {{ $activeOrderOp->sourceProduct->name ?? $activeOrder->product->name ?? 'Item' }}
                                        </h6>
                                        <div class="text-muted fs-11">
                                            @if($activeOrderOp->is_external && ($activeOrderOp->material_supply_type ?? 'company_supplied') === 'vendor_supplied')
                                                Record completed items received from vendor (Vendor Supplied - No raw material dispatch
                                                required).
                                            @else
                                                How many have you Done?
                                            @endif
                                        </div>
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

                                        @php
                                            $activeIsQcRequired = (bool) ($activeOrderOp->quality_required || ($activeOrderOp->routingOperation?->quality_required ?? false));
                                        @endphp

                                        <div class="col-md-12">
                                            <x-ui.odoo-form-ui type="input"
                                                label="{{ ($activeOrderOp->is_external && ($activeOrderOp->material_supply_type ?? 'company_supplied') === 'vendor_supplied') ? 'Quantity Received' : ($activeIsQcRequired ? 'Processed Output Qty (Pending QC)' : 'Quantity Done') }}"
                                                name="quantity_produced" inputType="number" step="any"
                                                value="{{ $activeRemainingToComplete }}" :required="true" />
                                            <input type="hidden" name="quantity_rejected" value="0">
                                            <input type="hidden" name="quantity_scrapped" value="0">
                                        </div>
                                        @if($activeIsQcRequired)
                                            <div class="col-md-12">
                                                <div class="alert alert-soft-warning mb-0 py-2 fs-11 border border-warning-subtle">
                                                    <i class="feather-shield text-warning me-1"></i> Quality Check is required for this
                                                    process. Logged output enters <strong>QC Pending</strong> state. Quality rejection &
                                                    scrap disposition are handled via <strong>Run QC</strong>.
                                                </div>
                                            </div>
                                        @endif

                                        <div class="col-md-12">
                                            <x-ui.odoo-form-ui type="textarea" label="Remarks" name="remarks"
                                                placeholder="{{ ($activeOrderOp->is_external && ($activeOrderOp->material_supply_type ?? 'company_supplied') === 'vendor_supplied') ? 'Optional vendor invoice / delivery note reference...' : 'Optional comments...' }}"
                                                rows="2" />
                                        </div>
                                    </div>
                                </form>
                                <x-slot name="footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                                    <button type="submit" class="btn btn-success px-4"
                                        onclick="document.getElementById('completeForm{{ $activeOp->id }}').submit();">
                                        <i
                                            class="feather-check me-1"></i>{{ ($activeOrderOp->is_external && ($activeOrderOp->material_supply_type ?? 'company_supplied') === 'vendor_supplied') ? 'Record Subcontract Receipt' : 'Save Production Log' }}
                                    </button>
                                </x-slot>
                            </x-ui.modal>

                            {{-- Assign Operator Modal (Shopfloor Assignment) --}}
                            @if(!$isActiveOutsourced && $activeOp->status !== 'completed')
                                <x-ui.modal id="assignOperatorModal{{ $activeOp->id }}"
                                    title="Assign Operator — Op #{{ $activeOp->sequence }} ({{ $activeOrderOp->name ?? '' }})"
                                    class="text-start" :showFooter="true">
                                    <form method="POST" action="{{ route('production.mes.assignments.assign') }}"
                                        id="assignOperatorForm{{ $activeOp->id }}">
                                        @csrf
                                        <input type="hidden" name="production_order_operation_id" value="{{ $activeOrderOp->id }}">

                                        <x-ui.odoo-form-ui type="select" label="Select Operator" name="user_id" :required="true">
                                            <option value="">-- Choose Operator --</option>
                                            @foreach($operators as $operator)
                                                <option value="{{ $operator->id }}" {{ (($assignedUser->id ?? $activeOp->assignedOperator->id ?? $activeOrderOp->operator_id ?? null) == $operator->id) ? 'selected' : '' }}>
                                                    {{ $operator->name }} ({{ ucfirst($operator->role) }})
                                                </option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>

                                        <x-ui.odoo-form-ui type="textarea" label="Remarks / Instructions" name="remarks"
                                            placeholder="Provide shift remarks or operation requirements..." rows="3" />
                                    </form>
                                    <x-slot name="footer">
                                        <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary px-4"
                                            onclick="document.getElementById('assignOperatorForm{{ $activeOp->id }}').submit();">
                                            <i class="feather-user-check me-1"></i>Assign Operator
                                        </button>
                                    </x-slot>
                                </x-ui.modal>
                            @endif

                            @php
                                $activePendingQcQty = app(\App\Domains\Production\Services\MesExecutionService::class)->getPendingQcQuantity($activeOrderOp->id);
                                $activeRejectedQty = (float) ($activeOrderOp->quantity_rejected ?? 0.0);
                            @endphp

                            {{-- Run QC Inspection Modal --}}
                            <x-ui.modal id="qcModal{{ $activeOp->id }}"
                                title="RUN QUALITY INSPECTION — {{ html_entity_decode($activeOrderOp->name ?? 'Op #' . $activeOp->sequence, ENT_QUOTES, 'UTF-8') }}"
                                class="text-start" size="lg">
                                <form method="POST" action="{{ route('production.mes.quality-inspection', $activeOrderOp->id) }}"
                                    id="qcForm{{ $activeOp->id }}">
                                    @csrf
                                    <div
                                        class="bg-soft-warning p-3 rounded mb-3 border border-warning-subtle d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-1"><i
                                                    class="feather-shield-check text-warning me-2"></i>In-Process Quality Inspection
                                            </h6>
                                            <span class="fs-11 text-muted">Order: <strong>{{ $activeOrder->order_number }}</strong> |
                                                Item:
                                                <strong>{{ $activeOrderOp->sourceProduct->name ?? $activeOrder->product->name }}</strong></span>
                                        </div>
                                        <span class="badge bg-warning text-dark fs-12 px-3 py-2 font-monospace">Pending QC:
                                            {{ number_format($activePendingQcQty, 0) }}</span>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <x-ui.odoo-form-ui type="select" label="Quality Plan" name="quality_plan_id"
                                                id="quality_plan_id_{{ $activeOp->id }}" class="quality-plan-select"
                                                data-op-id="{{ $activeOp->id }}" :required="true">
                                                <option value="">-- Standard In-Process Quality Plan --</option>
                                                @foreach($qualityPlans as $qp)
                                                    <option value="{{ $qp->id }}">{{ $qp->name }}
                                                        ({{ strtoupper($qp->type ?? 'in_process') }})</option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="col-md-6">
                                            <x-ui.odoo-form-ui type="select" label="Quality Auditor / Inspector" name="audited_by"
                                                :required="true">
                                                @foreach($operators as $opUser)
                                                    <option value="{{ $opUser->id }}" {{ (auth()->id() == $opUser->id) ? 'selected' : '' }}>
                                                        {{ $opUser->name }} ({{ ucfirst($opUser->role ?? 'Operator') }})
                                                    </option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="col-md-12">
                                            <label class="form-label fw-semibold fs-11 text-uppercase text-dark mb-1"><i
                                                    class="feather-check-square text-primary me-1"></i>Quality Specification Checklist
                                                Parameters</label>
                                            <div class="p-2.5 border rounded bg-light fs-11"
                                                id="qualityChecklistContainer_{{ $activeOp->id }}">
                                                <div class="form-check mb-1.5">
                                                    <input class="form-check-input" type="checkbox" checked
                                                        id="chkVisual{{ $activeOp->id }}">
                                                    <label class="form-check-label fw-medium" for="chkVisual{{ $activeOp->id }}">Visual
                                                        Surface Finish & Coating Inspection (Pass)</label>
                                                </div>
                                                <div class="form-check mb-1.5">
                                                    <input class="form-check-input" type="checkbox" checked
                                                        id="chkDim{{ $activeOp->id }}">
                                                    <label class="form-check-label fw-medium"
                                                        for="chkDim{{ $activeOp->id }}">Dimensional & Thickness Tolerance Within
                                                        Specification (Pass)</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" checked
                                                        id="chkFunc{{ $activeOp->id }}">
                                                    <label class="form-check-label fw-medium" for="chkFunc{{ $activeOp->id }}">Assembly
                                                        & Structural Integrity Test (Pass)</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <x-ui.odoo-form-ui type="input" label="Accepted Qty (Good Usable Output)"
                                                name="accepted_qty" inputType="number" step="any" value="{{ $activePendingQcQty }}"
                                                :required="true" />
                                        </div>
                                        <div class="col-md-4">
                                            <x-ui.odoo-form-ui type="input" label="Rejected Qty (Defective Output)" name="rejected_qty"
                                                inputType="number" step="any" value="0" :required="true" />
                                        </div>
                                        <div class="col-md-4">
                                            <x-ui.odoo-form-ui type="select" label="Defect Reason (If Rejected)" name="defect_reason">
                                                <option value="">-- None / Meets Quality Standard --</option>
                                                <option value="Surface Scratch / Coating Damage">Surface Scratch / Coating Damage
                                                </option>
                                                <option value="Out of Dimension / Thickness Error">Out of Dimension / Thickness Error
                                                </option>
                                                <option value="Chipped Edge / Structural Defect">Chipped Edge / Structural Defect
                                                </option>
                                                <option value="Raw Material Defect">Raw Material Defect</option>
                                                <option value="Machine Calibration Error">Machine Calibration Error</option>
                                                <option value="Operator Assembly Error">Operator Assembly Error</option>
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="col-md-12">
                                            <x-ui.odoo-form-ui type="textarea" label="Inspection Remarks & Quality Notes" name="remarks"
                                                placeholder="Enter measured gauge dimensions, batch details, or quality observations..."
                                                rows="2" />
                                        </div>
                                    </div>
                                </form>
                                <x-slot name="footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-warning text-dark fw-bold px-4"
                                        onclick="document.getElementById('qcForm{{ $activeOp->id }}').submit();">
                                        <i class="feather-check-circle me-1"></i>Submit QC Inspection
                                    </button>
                                </x-slot>
                            </x-ui.modal>

                            {{-- Record Operational Scrap Modal --}}
                            <x-ui.modal id="scrapModal{{ $activeOp->id }}"
                                title="RECORD OPERATIONAL SCRAP — {{ html_entity_decode($activeOrderOp->name ?? 'Op #' . $activeOp->sequence, ENT_QUOTES, 'UTF-8') }}"
                                class="text-start" size="md">
                                <form method="POST" action="{{ route('production.mes.scrap', $activeOrderOp->id) }}"
                                    id="scrapForm{{ $activeOp->id }}">
                                    @csrf
                                    <div class="bg-soft-danger p-3 rounded mb-3 border border-danger-subtle">
                                        <h6 class="fw-bold text-danger mb-1"><i class="feather-trash-2 me-2"></i>Record Operational Loss
                                            / Damaged Output</h6>
                                        <span class="fs-11 text-muted">Order: <strong>{{ $activeOrder->order_number }}</strong> |
                                            Operation: <strong>{{ $activeOrderOp->name }}</strong></span>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <x-ui.odoo-form-ui type="select" label="Component / Material to Scrap" name="product_id"
                                                :required="true">
                                                @php
                                                    $scrappableMats = $activeOrderOp->scrappable_materials;
                                                @endphp
                                                @foreach($scrappableMats as $idx => $mat)
                                                    <option value="{{ $mat['id'] }}" {{ $idx === 0 ? 'selected' : '' }}>
                                                        {{ $mat['name'] }} — {{ $mat['type_label'] }}
                                                    </option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="col-md-6">
                                            <x-ui.odoo-form-ui type="input" label="Scrap Quantity" name="quantity" inputType="number"
                                                step="any" value="1" :required="true" />
                                        </div>
                                        <div class="col-md-6">
                                            <x-ui.odoo-form-ui type="select" label="Scrap Reason Category" name="reason"
                                                :required="true">
                                                <option value="Cutting Error / Wrong Dimension">Cutting Error / Wrong Dimension</option>
                                                <option value="Setup Damage / Calibration Loss">Setup Damage / Calibration Loss</option>
                                                <option value="Machine Breakdown / Tool Defect">Machine Breakdown / Tool Defect</option>
                                                <option value="Raw Material Void / Internal Defect">Raw Material Void / Internal Defect
                                                </option>
                                                <option value="Operator Mishap / Handling Damage">Operator Mishap / Handling Damage
                                                </option>
                                                <option value="Other Operational Loss">Other Operational Loss</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="col-md-12">
                                            <x-ui.odoo-form-ui type="textarea" label="Scrap Observations & Material Notes"
                                                name="remarks" placeholder="Provide additional details regarding scrap cause..."
                                                rows="2" />
                                        </div>
                                    </div>
                                </form>
                                <x-slot name="footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger fw-bold px-4"
                                        onclick="document.getElementById('scrapForm{{ $activeOp->id }}').submit();">
                                        <i class="feather-trash-2 me-1"></i>Record Scrap
                                    </button>
                                </x-slot>
                            </x-ui.modal>

                            {{-- Disposition Rejected Quantity Modal --}}
                            <x-ui.modal id="dispositionModal{{ $activeOp->id }}"
                                title="REJECTED OUTPUT DISPOSITION — {{ html_entity_decode($activeOrderOp->name ?? 'Op #' . $activeOp->sequence, ENT_QUOTES, 'UTF-8') }}"
                                class="text-start" size="lg">
                                <form method="POST" action="{{ route('production.mes.disposition', $activeOrderOp->id) }}"
                                    id="dispositionForm{{ $activeOp->id }}">
                                    @csrf
                                    <div
                                        class="bg-soft-danger p-3 rounded mb-3 border border-danger-subtle d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold text-danger mb-1"><i class="feather-alert-triangle me-2"></i>Quality
                                                Rejection Disposition</h6>
                                            <span class="fs-11 text-muted">Order: <strong>{{ $activeOrder->order_number }}</strong> |
                                                Item:
                                                <strong>{{ $activeOrderOp->sourceProduct->name ?? $activeOrder->product->name }}</strong></span>
                                        </div>
                                        <span class="badge bg-danger text-white fs-12 px-3 py-2 font-monospace">Rejected Qty:
                                            {{ number_format($activeRejectedQty, 0) }}</span>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <x-ui.odoo-form-ui type="select" label="Disposition Choice" name="disposition_type"
                                                :required="true">
                                                <option value="rework" selected>Rework (Create Repair Order & Reprocess Defect)</option>
                                                <option value="scrap">Scrap (Scrap Material & Evaluate Replacement)</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="col-md-6">
                                            <x-ui.odoo-form-ui type="input" label="Quantity to Dispose" name="quantity"
                                                inputType="number" step="any" value="{{ $activeRejectedQty }}" :required="true" />
                                        </div>

                                        <div class="col-md-6">
                                            <x-ui.odoo-form-ui type="select" label="Target Workstation / Process (For Rework)"
                                                name="work_center_id">
                                                @foreach($workCenters as $wc)
                                                    <option value="{{ $wc->id }}" {{ ($activeOrderOp->work_center_id == $wc->id) ? 'selected' : '' }}>
                                                        {{ $wc->name }} ({{ $wc->code }})
                                                    </option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="col-md-6">
                                            <x-ui.odoo-form-ui type="select" label="Target Machine / Equipment" name="machine_id">
                                                <option value="">-- Default Workstation Machine --</option>
                                                @foreach($machines as $m)
                                                    <option value="{{ $m->id }}" {{ ($activeOrderOp->machine_id == $m->id) ? 'selected' : '' }}>
                                                        {{ $m->name }} ({{ $m->code ?? 'MACH' }})
                                                    </option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="col-md-4">
                                            <x-ui.odoo-form-ui type="select" label="Assigned Repair Technician" name="assigned_to">
                                                @foreach($operators as $tech)
                                                    <option value="{{ $tech->id }}" {{ (auth()->id() == $tech->id) ? 'selected' : '' }}>
                                                        {{ $tech->name }} ({{ ucfirst($tech->role ?? 'Technician') }})
                                                    </option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="col-md-4">
                                            <x-ui.odoo-form-ui type="select" label="Rework Strategy" name="rework_type">
                                                <option value="reprocess" selected>Reprocess / Re-run Machine</option>
                                                <option value="repair">Manual Touch-up / Spot Repair</option>
                                                <option value="re_machining">Re-machining / Trim Specification</option>
                                                <option value="re_coating">Strip & Re-surface / Re-paint</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="col-md-4">
                                            <x-ui.odoo-form-ui type="input" label="Estimated Repair Cost ($)" name="cost_estimate"
                                                inputType="number" step="0.01" value="50.00" />
                                        </div>

                                        <div class="col-md-12">
                                            <x-ui.odoo-form-ui type="textarea" label="Reason / Defect Description" name="reason"
                                                placeholder="Explain defect cause (e.g. Surface Scratch, Dimension Out of Spec)..."
                                                rows="2" />
                                        </div>
                                        <div class="col-md-12">
                                            <x-ui.odoo-form-ui type="textarea" label="Detailed Rework Instructions for Technician"
                                                name="instructions"
                                                placeholder="Provide step-by-step repair instructions for the rework operator..."
                                                rows="2" />
                                        </div>
                                    </div>
                                </form>
                                <x-slot name="footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger fw-bold px-4"
                                        onclick="document.getElementById('dispositionForm{{ $activeOp->id }}').submit();">
                                        <i class="feather-check-circle me-1"></i>Submit Disposition
                                    </button>
                                </x-slot>
                            </x-ui.modal>
                        @endif
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

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const qualityPlansMap = @json($qualityPlans->keyBy('id'));

                function updateQualityChecklist(selectEl) {
                    if (!selectEl) return;
                    const form = selectEl.closest('form');
                    const container = form ? form.querySelector('[id^="qualityChecklistContainer_"]') : null;
                    if (!container) return;

                    const opId = container.id.replace('qualityChecklistContainer_', '');
                    const planId = selectEl.value;

                    const plan = qualityPlansMap[planId];
                    if (plan && plan.parameters && plan.parameters.length > 0) {
                        let html = '';
                        plan.parameters.forEach((param) => {
                            const isMandatory = param.is_mandatory ? '<span class="text-danger">*</span>' : '';
                            const uom = param.unit_of_measure || param.uom || '';
                            const hasMin = param.min_value !== null && param.min_value !== undefined && param.min_value !== '';
                            const hasMax = param.max_value !== null && param.max_value !== undefined && param.max_value !== '';
                            const minMaxStr = (hasMin || hasMax)
                                ? `<span class="badge bg-soft-info text-info fs-10 ms-1">Spec: ${hasMin ? param.min_value : ''} - ${hasMax ? param.max_value : ''} ${uom}</span>`
                                : '';

                            if (param.type === 'numeric') {
                                html += `
                                <div class="mb-2 p-2 border rounded bg-white">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label fw-semibold fs-11 text-dark mb-0">${param.name} ${isMandatory}</label>
                                        ${minMaxStr}
                                    </div>
                                    <input type="number" step="any" class="form-control form-control-sm" name="parameter_values[${param.id}]" placeholder="Enter measured value (${uom})" ${param.is_mandatory ? 'required' : ''}>
                                </div>
                            `;
                            } else if (param.type === 'text') {
                                html += `
                                <div class="mb-2 p-2 border rounded bg-white">
                                    <label class="form-label fw-semibold fs-11 text-dark mb-1">${param.name} ${isMandatory}</label>
                                    <input type="text" class="form-control form-control-sm" name="parameter_values[${param.id}]" placeholder="Enter observation / inspection notes..." ${param.is_mandatory ? 'required' : ''}>
                                </div>
                            `;
                            } else {
                                html += `
                                <div class="form-check mb-1.5 p-2 border rounded bg-white ms-0 ps-4">
                                    <input class="form-check-input ms--3" type="checkbox" checked name="parameter_values[${param.id}]" value="pass" id="param_${opId}_${param.id}">
                                    <label class="form-check-label fw-medium fs-11" for="param_${opId}_${param.id}">${param.name} (Pass)</label>
                                </div>
                            `;
                            }
                        });
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = `
                        <div class="form-check mb-1.5">
                            <input class="form-check-input" type="checkbox" checked id="chkVisual${opId}">
                            <label class="form-check-label fw-medium" for="chkVisual${opId}">Visual Surface Finish & Coating Inspection (Pass)</label>
                        </div>
                        <div class="form-check mb-1.5">
                            <input class="form-check-input" type="checkbox" checked id="chkDim${opId}">
                            <label class="form-check-label fw-medium" for="chkDim${opId}">Dimensional & Thickness Tolerance Within Specification (Pass)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" checked id="chkFunc${opId}">
                            <label class="form-check-label fw-medium" for="chkFunc${opId}">Assembly & Structural Integrity Test (Pass)</label>
                        </div>
                    `;
                    }
                }

                if (window.jQuery) {
                    window.jQuery(document).on('change change.select2', 'select[name="quality_plan_id"]', function () {
                        updateQualityChecklist(this);
                    });

                    window.jQuery(document).on('shown.bs.modal', function (e) {
                        const select = e.target.querySelector('select[name="quality_plan_id"]');
                        if (select) {
                            updateQualityChecklist(select);
                        }
                    });
                }

                document.querySelectorAll('select[name="quality_plan_id"]').forEach(selectEl => {
                    selectEl.addEventListener('change', function () {
                        updateQualityChecklist(this);
                    });
                    updateQualityChecklist(selectEl);
                });
            });
        </script>
    @endpush
@endsection