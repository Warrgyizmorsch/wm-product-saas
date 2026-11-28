@extends('layouts.duralux')

@section('title', __('production.shop_floor_dashboard') . ' | SaaS ERP')
@section('page-title', __('production.shop_floor_operator_dashboard'))
@section('breadcrumb', __('production.mes_dashboard'))

@push('styles')
    <style>
        .btn-purple {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #ffffff !important;
        }

        .btn-purple:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 85%, #000000) !important;
            border-color: color-mix(in srgb, var(--bs-primary) 85%, #000000) !important;
            color: #ffffff !important;
        }

        .bg-purple {
            background-color: var(--bs-primary) !important;
            color: #ffffff !important;
        }

        .bg-soft-purple {
            background-color: color-mix(in srgb, var(--bs-primary) 8%, transparent) !important;
        }

        .bg-soft-purple-subtle {
            background-color: color-mix(in srgb, var(--bs-primary) 4%, transparent) !important;
        }

        .text-purple {
            color: var(--bs-primary) !important;
        }

        .border-purple-subtle {
            border-color: color-mix(in srgb, var(--bs-primary) 25%, transparent) !important;
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

        /* ==========================================================================
           MES Shop Floor Dashboard Dark Mode
           ========================================================================== */
        html.app-skin-dark .mes-project-card,
        body.app-skin-dark .mes-project-card,
        [data-bs-theme="dark"] .mes-project-card,
        [data-theme="dark"] .mes-project-card {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
        }

        html.app-skin-dark .mes-project-header,
        body.app-skin-dark .mes-project-header,
        [data-bs-theme="dark"] .mes-project-header,
        [data-theme="dark"] .mes-project-header {
            background-color: #162038 !important;
            border-color: #283c50 !important;
        }

        html.app-skin-dark .mes-project-header .text-dark,
        body.app-skin-dark .mes-project-header .text-dark,
        [data-bs-theme="dark"] .mes-project-header .text-dark,
        [data-theme="dark"] .mes-project-header .text-dark,
        html.app-skin-dark .mes-project-product,
        body.app-skin-dark .mes-project-product,
        [data-bs-theme="dark"] .mes-project-product,
        [data-theme="dark"] .mes-project-product {
            color: #f1f5f9 !important;
        }

        html.app-skin-dark .mes-project-header .text-muted,
        body.app-skin-dark .mes-project-header .text-muted,
        [data-bs-theme="dark"] .mes-project-header .text-muted,
        [data-theme="dark"] .mes-project-header .text-muted {
            color: #94a3b8 !important;
        }

        html.app-skin-dark .mes-project-header .text-secondary,
        body.app-skin-dark .mes-project-header .text-secondary,
        [data-bs-theme="dark"] .mes-project-header .text-secondary,
        [data-theme="dark"] .mes-project-header .text-secondary {
            color: #cbd5e1 !important;
        }

        html.app-skin-dark .mes-project-header .progress,
        body.app-skin-dark .mes-project-header .progress,
        [data-bs-theme="dark"] .mes-project-header .progress,
        [data-theme="dark"] .mes-project-header .progress {
            background-color: #0f172a !important;
            border-color: #283c50 !important;
        }

        html.app-skin-dark .mes-project-card .card-body,
        body.app-skin-dark .mes-project-card .card-body,
        [data-bs-theme="dark"] .mes-project-card .card-body,
        [data-theme="dark"] .mes-project-card .card-body {
            background-color: #0f172a !important;
        }

        html.app-skin-dark .mes-project-card .table,
        body.app-skin-dark .mes-project-card .table,
        [data-bs-theme="dark"] .mes-project-card .table,
        [data-theme="dark"] .mes-project-card .table {
            background-color: #0f172a !important;
            color: #cbd5e1 !important;
            border-color: #1e293b !important;
        }

        html.app-skin-dark .mes-project-card .table td,
        body.app-skin-dark .mes-project-card .table td,
        [data-bs-theme="dark"] .mes-project-card .table td,
        [data-theme="dark"] .mes-project-card .table td,
        html.app-skin-dark .mes-project-card .table th,
        body.app-skin-dark .mes-project-card .table th,
        [data-bs-theme="dark"] .mes-project-card .table th,
        [data-theme="dark"] .mes-project-card .table th {
            border-color: #1e293b !important;
        }

        html.app-skin-dark .action-icon-btn,
        body.app-skin-dark .action-icon-btn,
        [data-bs-theme="dark"] .action-icon-btn,
        [data-theme="dark"] .action-icon-btn {
            background-color: #1e293b !important;
            border-color: #334155 !important;
            color: #cbd5e1 !important;
        }

        html.app-skin-dark .action-icon-btn:hover,
        body.app-skin-dark .action-icon-btn:hover,
        [data-bs-theme="dark"] .action-icon-btn:hover,
        [data-theme="dark"] .action-icon-btn:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 20%, #1e293b) !important;
            border-color: var(--bs-primary) !important;
            color: #ffffff !important;
        }

        html.app-skin-dark .action-icon-btn.action-start-btn,
        body.app-skin-dark .action-icon-btn.action-start-btn,
        [data-bs-theme="dark"] .action-icon-btn.action-start-btn,
        [data-theme="dark"] .action-icon-btn.action-start-btn {
            background-color: #10b981 !important;
            border-color: #10b981 !important;
            color: #ffffff !important;
        }

        html.app-skin-dark .mes-empty-card,
        body.app-skin-dark .mes-empty-card,
        [data-bs-theme="dark"] .mes-empty-card,
        [data-theme="dark"] .mes-empty-card {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
            color: #cbd5e1 !important;
        }

        html.app-skin-dark .mes-empty-card .text-dark,
        body.app-skin-dark .mes-empty-card .text-dark,
        [data-bs-theme="dark"] .mes-empty-card .text-dark,
        [data-theme="dark"] .mes-empty-card .text-dark {
            color: #f1f5f9 !important;
        }

        html.app-skin-dark .running-card,
        body.app-skin-dark .running-card,
        [data-bs-theme="dark"] .running-card,
        [data-theme="dark"] .running-card {
            background-color: #0f172a !important;
        }

        html.app-skin-dark .paused-card,
        body.app-skin-dark .paused-card,
        [data-bs-theme="dark"] .paused-card,
        [data-theme="dark"] .paused-card {
            background-color: #0f172a !important;
        }

        html.app-skin-dark .ready-card,
        body.app-skin-dark .ready-card,
        [data-bs-theme="dark"] .ready-card,
        [data-theme="dark"] .ready-card {
            background-color: #0f172a !important;
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
    <x-ui.workflow-guide :title="__('production.whats_next')">
        @if(isset($activeSchedules) && $activeSchedules->count() > 0)
            @php 
                $firstOrder = $activeSchedules->first()->order; 
                $opsUrl = $firstOrder ? route('production.orders.show', ['order' => $firstOrder->id, 'tab' => 'vtab-operations']) : route('production.orders.index');
            @endphp
            {!! __('production.mes_workflow_guide_active', [
                'operations_link' => '<a href="' . $opsUrl . '" class="fw-bold text-primary text-decoration-underline">' . __('production.production_order_operations_tab') . '</a>',
                'console_link' => '<a href="' . route('production.mes.operator.dashboard') . '" class="fw-bold text-primary text-decoration-underline">' . __('production.mes_operator_console') . '</a>'
            ]) !!}
        @else
            {!! __('production.mes_workflow_guide_empty', [
                'schedules_link' => '<a href="' . route('production.schedules.index') . '" class="fw-bold text-primary text-decoration-underline">' . __('production.production_schedules') . '</a>',
                'operations_link' => '<a href="' . route('production.orders.index') . '" class="fw-bold text-primary text-decoration-underline">' . __('production.production_order_operations_tab') . '</a>'
            ]) !!}
        @endif
    </x-ui.workflow-guide>

    <div class="erp-single-panel">

        {{-- SECTION 1: Active Manufacturing Projects --}}
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center">
                            <i class="feather-grid me-2 text-primary"></i>{{ __('production.active_manufacturing_projects') }}
                        </h5>
                        <span
                            class="badge bg-soft-primary text-primary rounded-pill ms-2 fw-bold font-monospace">{{ $activeSchedules->count() }}</span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <!-- Custom Filter Component matching BOM module -->
                        <form method="GET" action="{{ route('production.mes.dashboard') }}" class="d-inline">
                            <x-ui.filter :label="__('ui.filter') ?? 'Filter'" offset="0, 5">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('production.filter_options') ?? 'Filter Options' }}</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.search_keywords') ?? 'Search Keywords' }}</label>
                                    <x-ui.odoo-form-ui type="input" name="search" placeholder="Search Order #, Schedule #, Product..." value="{{ request('search') }}" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.production_order') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="order_id">
                                        <option value="">{{ __('production.all_production_orders') }}</option>
                                        @foreach($orders as $po)
                                            <option value="{{ $po->id }}" {{ request('order_id') == $po->id ? 'selected' : '' }}>
                                                {{ $po->order_number }} - {{ $po->product->name ?? '' }}
                                            </option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.item_to_produce') ?? 'Product' }}</label>
                                    <x-ui.odoo-form-ui type="select" name="product_id">
                                        <option value="">{{ __('production.all_products') ?? 'All Products' }}</option>
                                        @foreach($products as $product)
                                            <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                                                {{ $product->name }}
                                            </option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.workstation_center') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="work_center_id">
                                        <option value="">{{ __('production.all_work_centers') }}</option>
                                        @foreach($workCenters as $wc)
                                            <option value="{{ $wc->id }}" {{ request('work_center_id') == $wc->id ? 'selected' : '' }}>
                                                {{ $wc->name }}
                                            </option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="d-flex gap-2 justify-content-end mt-4">
                                    <a href="{{ route('production.mes.dashboard') }}" class="btn btn-sm btn-light text-muted fw-bold">
                                        {{ __('production.reset') }}
                                    </a>
                                    <button type="submit" class="btn btn-sm btn-primary fw-bold">
                                        <i class="feather-filter me-1"></i>{{ __('production.apply_filters') }}
                                    </button>
                                </div>
                            </x-ui.filter>
                        </form>
                    </div>
                </div>

                @if(request('search') || request('order_id') || request('product_id') || request('work_center_id') || request('status'))
                    <div class="alert alert-soft-info d-flex align-items-center justify-content-between p-2 px-3 mb-3 fs-11 rounded border border-info-subtle">
                        <div class="d-flex align-items-center gap-2">
                            <i class="feather-filter text-info fs-14"></i>
                            <span><strong>{{ __('production.active_filters') }}</strong>
                                @if(request('search')) Search: "{{ request('search') }}" &bull; @endif
                                @if(request('order_id') && ($matchedPo = $orders->firstWhere('id', request('order_id')))) PO: {{ $matchedPo->order_number }} &bull; @endif
                                @if(request('product_id') && ($matchedProd = $products->firstWhere('id', request('product_id')))) Product: {{ $matchedProd->name }} &bull; @endif
                                @if(request('work_center_id') && ($matchedWc = $workCenters->firstWhere('id', request('work_center_id')))) Workstation: {{ $matchedWc->name }} &bull; @endif
                            </span>
                        </div>
                        <a href="{{ route('production.mes.dashboard') }}" class="btn btn-xs btn-outline-info fw-bold py-0">{{ __('production.clear_filters') }}</a>
                    </div>
                @endif

                @forelse($activeSchedules as $schedule)
                    @php
                        $order = $schedule->order;
                        $ops = $schedule->operations->sort(function ($a, $b) use ($order) {
                            if ($a->planned_start && $b->planned_start && !$a->planned_start->equalTo($b->planned_start)) {
                                return $a->planned_start <=> $b->planned_start;
                            }

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

                    <div class="card mb-4 border border-light shadow-sm mes-project-card" style="border-radius: 12px; overflow: hidden;">
                        {{-- Card Header --}}
                        <div
                            class="card-header bg-light border-bottom border-light p-3 d-flex flex-wrap align-items-center justify-content-between gap-2 mes-project-header">
                            <div>
                                <h6 class="fw-bold text-dark mb-1 fs-14 d-flex align-items-center gap-2">
                                    <i class="feather-box text-primary"></i>
                                    <a href="{{ route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-operations']) }}"
                                        class="text-primary fw-bold text-decoration-underline"
                                        title="Assign operators under Operations tab">{{ $order->order_number ?? '' }}</a>
                                    <span class="text-muted">&middot;</span>
                                    <span class="fw-bold mes-project-product">{{ $order->product->name ?? 'Unknown Product' }}</span>
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
                        <div class="card-body p-0 bg-white table-responsive" style="overflow-x: auto;">
                            <x-ui.odoo-form-ui type="table" class="align-middle mb-0 fs-11" style="min-width: 1650px; width: 100%;">
                                <thead class="bg-soft-primary text-primary fw-bold text-uppercase border-bottom">
                                    <tr>
                                        <th class="ps-3 text-center" style="min-width: 55px; width: 55px;">{{ __('production.s_no') }}</th>
                                        <th style="min-width: 150px; width: 150px;">{{ __('production.action') }}</th>
                                        <th style="min-width: 220px; width: 220px;">{{ __('production.item_details') }}</th>
                                        <th style="min-width: 190px; width: 190px;">{{ __('production.process') }}</th>
                                        <th style="min-width: 190px; width: 190px;">{{ __('production.workstation') }}</th>
                                        <th style="min-width: 100px; width: 100px;">{{ __('production.shifts_sidebar') }}</th>
                                        <th style="min-width: 110px; width: 110px;">{{ __('production.schedule_start') }}</th>
                                        <th style="min-width: 110px; width: 110px;">{{ __('production.schedule_finish') }}</th>
                                        <th class="text-center" style="min-width: 90px; width: 90px;">{{ __('production.target_qty') }}</th>
                                        <th style="min-width: 110px; width: 110px;">{{ __('production.actual_start') }}</th>
                                        <th style="min-width: 110px; width: 110px;">{{ __('production.actual_finish') }}</th>
                                        <th class="text-center" style="min-width: 90px; width: 90px;">{{ __('production.done_qty') }}</th>
                                        <th class="text-center" style="min-width: 90px; width: 90px;">{{ __('production.pending_qty') }}</th>
                                        <th style="min-width: 120px; width: 120px;">{{ __('production.assign_to') }}</th>
                                        <th class="pe-3" style="min-width: 120px; width: 120px;">Executed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ops as $idx => $op)
                                        @php
                                            $orderOp = $op->orderOperation ?? $op;
                                            $itemName = $orderOp->sourceProduct->name ?? $order->product->name ?? 'Item';
                                            $itemNotes = $orderOp->notes ?? $op->remarks ?? '';

                                            $isOutsourced = (bool) ($orderOp->is_external || $orderOp->isOutsourced() || ($op->work_center_id === null && !config('production.temp_flow')));
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
                                            $isQcRequired = (bool) ($orderOp->quality_required || ($orderOp->routingOperation?->quality_required ?? false));
                                            $pendingQcQty = app(\App\Domains\Production\Services\MesExecutionService::class)->getPendingQcQuantity($orderOp->id);
                                            // QD: the operation's performed inspection records, for the QC badge tooltip
                                            $qcStats = $isQcRequired ? \App\Domains\Production\Models\ProductionQualityInspection::where('production_order_operation_id', $orderOp->id)
                                                ->where('status', 'approved')
                                                ->selectRaw("COUNT(*) as records, COALESCE(SUM(CASE WHEN result = 'passed' THEN inspected_quantity ELSE 0 END), 0) as passed, COALESCE(SUM(CASE WHEN result = 'failed' THEN inspected_quantity ELSE 0 END), 0) as failed")
                                                ->first() : null;
                                            $qcTip = 'Waiting for QC: ' . number_format($pendingQcQty, 0) . ' · ' . (int) ($qcStats->records ?? 0) . ' inspection record(s): ' . number_format((float) ($qcStats->passed ?? 0), 0) . ' passed, ' . number_format((float) ($qcStats->failed ?? 0), 0) . ' failed. One record = one quantity with one result.';
                                            $acceptedQty = (float) ($orderOp->quantity_produced ?? 0.0);
                                            $rejectedQty = (float) ($orderOp->quantity_rejected ?? 0.0);
                                            $scrappedQty = (float) ($orderOp->quantity_scrapped ?? 0.0);
                                            $pendingQty = max(0.0, $moQty - ($doneQty + $pendingQcQty + $scrappedQty + $rejectedQty));
                                            $isCompleted = ($op->status === 'completed') || ($doneQty >= $moQty && $moQty > 0);
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

                                            $assignedUser = $activeAssignment?->user ?? null;
                                            $assignmentStatus = $activeAssignment?->status ?? null;

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

                                            // Executed By resolution (person who starts or logs progress on the operation)
                                            $executedUser = $orderOp->relationLoaded('executedBy') ? $orderOp->executedBy : ($orderOp->executedBy ?? null);
                                            if (!$executedUser && $orderOp->relationLoaded('progressLogs')) {
                                                $latestProgressLog = $orderOp->progressLogs->sortByDesc('id')->first();
                                                $executedUser = $latestProgressLog?->user ?? null;
                                            } elseif (!$executedUser) {
                                                $latestProgressLog = \App\Domains\Production\Models\ProductionOrderProgressLog::where('operation_id', $orderOp->id)->latest('id')->first();
                                                $executedUser = $latestProgressLog?->user ?? null;
                                            }
                                        @endphp

                                        <tr
                                            class="{{ $isCompleted ? 'bg-soft-success-subtle' : ($op->status === 'running' ? 'bg-soft-primary-subtle' : ($isOutsourced ? 'bg-soft-purple-subtle' : '')) }}">
                                            <td class="ps-3 fw-bold text-muted text-center align-top">{{ $loop->iteration }}</td>

                                            {{-- Action Column --}}
                                            <td class="align-top">
                                                @if($isOutsourced)
                                                    @php
                                                        // SF-2: a dispatch slip only when something of ours goes (previous output or BOM rows)
                                                        $needsSlip = $orderOp->needsDispatchSlip();
                                                        $isVendorSupplied = !$needsSlip;
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
                                                            @php
                                                                // SF-4: no PR / PO; receipt when the vendor has the work (or nothing of ours was sent)
                                                                $scAtVendor = \App\Domains\Production\Models\DeliveryChallan::where('production_order_operation_id', $orderOp->id)
                                                                    ->whereIn('status', \App\Domains\Production\Models\DeliveryChallan::AT_VENDOR_STATUSES)->exists();
                                                                $scCanReceive = !$isCompleted && ($isVendorSupplied || $scAtVendor);
                                                            @endphp
                                                            @if($needsSlip && !$isCompleted)
                                                                <a href="{{ route('production.subcontract.delivery-challans.create', ['production_order_id' => $orderOp->production_order_id, 'operation_id' => $orderOp->id]) }}"
                                                                    class="btn btn-sm btn-primary fw-semibold px-2 py-1 fs-11 text-white shadow-sm d-inline-flex align-items-center gap-1 text-nowrap"
                                                                    title="Dispatch slip: send our material / the previous operation's output to the vendor">
                                                                    <i class="feather-truck fs-12"></i>Dispatch</a>
                                                            @endif
                                                            @if($scCanReceive)
                                                                <a href="{{ route('production.subcontract.receipts.create', ['operation_id' => $orderOp->id, 'source' => 'shop_floor']) }}"
                                                                    class="btn btn-sm btn-info fw-semibold px-2 py-1 fs-11 text-white shadow-sm d-inline-flex align-items-center gap-1 text-nowrap"
                                                                    title="Record the vendor's work received on the shop floor">
                                                                    <i class="feather-download-cloud fs-12"></i>{{ __('production.record_subcontract_receipt') }}</a>
                                                            @endif
                                                        </div>
                                                        @if($isVendorSupplied)
                                                            <span class="badge bg-soft-info text-info border border-info-subtle fs-9">
                                                                <i class="feather-box me-1"></i>{{ __('production.vendor_supplied_material') }}</span>
                                                        @elseif($latestChallan)
                                                            @if($latestChallan->status === 'draft')
                                                                <a href="{{ route('production.subcontract.delivery-challans.show', $latestChallan->id) }}"
                                                                    class="badge bg-soft-warning text-dark border border-warning fs-9 text-decoration-none"
                                                                    title="Draft Gate Pass pending dispatch">
                                                                    <i class="feather-clock me-1"></i>{{ __('production.draft_gate_pass') }}</a>
                                                            @elseif($latestChallan->status === 'partially_received')
                                                                <a href="{{ route('production.subcontract.delivery-challans.show', $latestChallan->id) }}"
                                                                    class="badge bg-soft-warning text-dark border border-warning fs-9 text-decoration-none"
                                                                    title="Part of the work is back, the rest is still with the vendor">
                                                                    <i class="feather-truck me-1"></i>Partly received</a>
                                                            @elseif($latestChallan->status === 'dispatched')
                                                                <a href="{{ route('production.subcontract.delivery-challans.show', $latestChallan->id) }}"
                                                                    class="badge bg-soft-info text-info border border-info-subtle fs-9 text-decoration-none"
                                                                    title="Material Dispatched to Vendor">
                                                                    <i class="feather-truck me-1"></i>{{ __('production.dispatched') }}</a>
                                                            @elseif($latestChallan->status === 'completed')
                                                                <a href="{{ route('production.subcontract.delivery-challans.show', $latestChallan->id) }}"
                                                                    class="badge bg-soft-success text-success border border-success-subtle fs-9 text-decoration-none"
                                                                    title="Subcontract Received & Completed">
                                                                    <i class="feather-check-circle me-1"></i>{{ __('production.received') }}</a>
                                                            @endif
                                                        @endif

                                                        {{-- QC & Disposition Context Actions for Outsourced Operations --}}
                                                        @if(($isQcRequired && ($pendingQcQty > 0 || $isCompleted)) || $rejectedQty > 0)
                                                            <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                                                @if($isQcRequired)
                                                                    @if($pendingQcQty > 0)
                                                                        <a href="{{ route('production.quality.operation-inspection.show', ['op' => $orderOp->id, 'source' => 'shop_floor']) }}"
                                                                            class="btn btn-xs btn-warning text-dark fw-bold p-1 px-1.5 fs-10 d-inline-flex align-items-center gap-1 shadow-sm text-nowrap btn-badge-container text-decoration-none"
                                                                            title="Run Quality Inspection on Received Subcontract Goods — opens the operation's inspection page">
                                                                            <i class="feather-shield-check fs-11"></i>{{ __('production.run_qc') }}<span class="btn-badge-count">{{ number_format($pendingQcQty, 0) }}</span>
                                                                        </a>
                                                                    @elseif($isCompleted)
                                                                        <span
                                                                            class="badge bg-soft-success text-success border border-success-subtle p-1 px-1.5 fs-10 d-inline-flex align-items-center gap-1 text-nowrap">
                                                                            <i class="feather-check-circle fs-11"></i>QC Passed
                                                                        </span>
                                                                    @endif
                                                                @endif
                                                                @if($rejectedQty > 0)
                                                                    <button type="button"
                                                                        class="btn btn-xs btn-danger text-white fw-bold p-1 px-1.5 fs-10 d-inline-flex align-items-center gap-1 shadow-sm text-nowrap btn-badge-container"
                                                                        data-bs-toggle="modal" data-bs-target="#dispositionModal{{ $op->id }}"
                                                                        title="Disposition Rejected Qty">
                                                                        <i class="feather-alert-triangle fs-11"></i>{{ __('production.rework_scrap') }}<span class="btn-badge-count">{{ number_format($rejectedQty, 0) }}</span>
                                                                    </button>
                                                                @endif
                                                            </div>
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
                                                                @php
                                                                    // UR-07: say it when the operation waits for the operations that make its sub-assembly
                                                                    $subWaitIds = $orderOp->relationLoaded('predecessorDependencies') ? $orderOp->subAssemblyPredecessorIds() : [];
                                                                    $subWaiting = $subWaitIds === [] ? 0 : $orderOp->predecessorDependencies->filter(fn ($p) => in_array((int) $p->id, $subWaitIds, true) && !in_array($p->status, ['completed', 'skipped', 'cancelled'], true))->count();
                                                                @endphp
                                                                <span class="action-icon-btn opacity-65" data-bs-toggle="tooltip"
                                                                    data-bs-placement="top" title="{{ $subWaiting > 0 ? 'Waiting for the operations that make its sub-assembly (' . $subWaiting . ' not completed)' : 'Waiting for Upstream Operation Completion' }}">
                                                                    <i class="feather-lock fs-12"></i>
                                                                </span>
                                                            @endif

                                                            @if(!$isCompleted)
                                                                <span class="d-inline-block" data-bs-toggle="tooltip" data-bs-placement="top" title="Material Disposition (Scrap / Offcut / Return)">
                                                                    <button type="button" class="action-icon-btn" data-bs-toggle="modal" data-bs-target="#materialDispositionModal{{ $op->id }}">
                                                                        <i class="feather-package fs-12"></i>
                                                                    </button>
                                                                </span>
                                                            @endif
                                                        </div>

                                                        {{-- QC & Disposition Context Actions --}}
                                                        @if(($isQcRequired && ($pendingQcQty > 0 || $isCompleted)) || $rejectedQty > 0)
                                                            <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                                                @if($isQcRequired)
                                                                    @if($pendingQcQty > 0)
                                                                        <a href="{{ route('production.quality.operation-inspection.show', ['op' => $orderOp->id, 'source' => 'shop_floor']) }}"
                                                                            class="btn btn-xs btn-warning text-dark fw-bold p-1 px-1.5 fs-10 d-inline-flex align-items-center gap-1 shadow-sm text-nowrap btn-badge-container text-decoration-none"
                                                                            title="Run Quality Inspection — opens the operation's inspection page">
                                                                            <i class="feather-shield-check fs-11"></i>{{ __('production.run_qc') }}<span class="btn-badge-count">{{ number_format($pendingQcQty, 0) }}</span>
                                                                        </a>
                                                                    @elseif($isCompleted)
                                                                        <span
                                                                            class="badge bg-soft-success text-success border border-success-subtle p-1 px-1.5 fs-10 d-inline-flex align-items-center gap-1 text-nowrap">
                                                                            <i class="feather-check-circle fs-11"></i>QC Passed
                                                                        </span>
                                                                    @endif
                                                                @endif
                                                                @if($rejectedQty > 0)
                                                                    <button type="button"
                                                                        class="btn btn-xs btn-danger text-white fw-bold p-1 px-1.5 fs-10 d-inline-flex align-items-center gap-1 shadow-sm text-nowrap btn-badge-container"
                                                                        data-bs-toggle="modal" data-bs-target="#dispositionModal{{ $op->id }}"
                                                                        title="Disposition Rejected Qty">
                                                                        <i class="feather-alert-triangle fs-11"></i>{{ __('production.rework_scrap') }}<span class="btn-badge-count">{{ number_format($rejectedQty, 0) }}</span>
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
                                                        <span class="badge {{ $pendingQcQty > 0 ? 'bg-soft-warning text-dark border border-warning' : 'bg-light text-muted border' }}" title="{{ $qcTip }}" data-bs-toggle="tooltip">QC: {{ number_format($pendingQcQty, 0) }}</span>
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
                                                            <i class="feather-pause-circle me-1"></i>{{ __('production.paused') }}</span>
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
                                                    @elseif($op->manualProduction)
                                                        <small class="text-muted d-block fs-9 text-wrap mt-0.5"><i
                                                                class="feather-users me-1"></i>{{ $op->manualProduction->batch_name }} <span class="badge bg-soft-secondary text-secondary fs-9">Manual batch</span></small>
                                                    @endif
                                                @endif
                                            </td>

                                            {{-- Shifts --}}
                                            <td class="align-top">
                                                @if($isOutsourced)
                                                    <span
                                                        class="badge bg-soft-warning text-dark border fs-10">Subcontract</span>
                                                    <small class="text-muted d-block fs-9 mt-0.5">Vendor time:
                                                        {{ \App\Domains\Production\Services\SubcontractDates::formatHours($orderOp->vendorTotalHours()) }}</small>
                                                @else
                                                    <span class="badge bg-soft-info text-info border fs-10" data-bs-toggle="tooltip"
                                                        data-bs-placement="top" title="Active Shift: Day Shift">{{ __('production.day_shift') }}</span>
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
                                            <td class="align-top">
                                                @if($isOutsourced)
                                                    <span
                                                        class="badge bg-soft-purple text-purple border border-purple-subtle fs-10 text-wrap d-inline-block"
                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                        title="Subcontract Vendor: {{ $vendorName }}">
                                                        <i class="feather-briefcase me-1"></i>{{ $vendorName }}
                                                    </span>
                                                @else
                                                    @php
                                                        $isOpStarted = in_array($op->status, ['running', 'paused', 'completed'])
                                                            || in_array($orderOp->status ?? '', ['running', 'paused', 'completed'])
                                                            || !empty($op->actual_start)
                                                            || !empty($orderOp->actual_start_time)
                                                            || ((float) ($orderOp->quantity_produced ?? 0) > 0);
                                                        $canChangeOperator = !$isCompleted && (!$isOpStarted || !$assignedUser);
                                                    @endphp
                                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                                        <span class="badge {{ $assignedBadgeClass }} fs-10 text-wrap d-inline-block"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            title="{{ $assignedTooltip }}">
                                                            <i class="{{ $assignedIcon }} me-1"></i>{{ $assignedName }}
                                                        </span>
                                                        @if($canChangeOperator)
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

                                            {{-- Executed By --}}
                                            <td class="pe-3 align-top">
                                                @if($isOutsourced)
                                                    <span class="text-muted fs-11">—</span>
                                                @elseif($executedUser)
                                                    <span class="badge bg-soft-info text-info border border-info-subtle fs-10 text-wrap d-inline-block"
                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                        title="Executed / Logged by {{ $executedUser->name }}">
                                                        <i class="feather-play-circle me-1"></i>{{ $executedUser->name }}
                                                    </span>
                                                @else
                                                    <span class="text-muted fs-11">—</span>
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
                            $isActiveOutsourced = (bool) ($activeOrderOp->is_external || $activeOrderOp->isOutsourced() || ($activeOp->work_center_id === null && !config('production.temp_flow')));
                        @endphp

                        {{-- SF-4: dispatch slip and receipt are pages (redirects), not popups --}}

                        @if(!$isActiveOutsourced)
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
                                $activePendingQcQty = app(\App\Domains\Production\Services\MesExecutionService::class)->getPendingQcQuantity($activeOrderOp->id);
                                $activeRemainingToComplete = max(0.0, $activeTargetQty - (($activeOrderOp->quantity_produced ?? 0.0) + $activePendingQcQty + $activeScrapQty + ($activeOrderOp->quantity_rejected ?? 0.0)));
                            @endphp
                            <x-ui.modal id="completeModal{{ $activeOp->id }}"
                                title="{{ ($activeOrderOp->is_external && $activeOrderOp->vendor_supplies_materials) ? 'RECEIVE SUBCONTRACT GOODS (VENDOR SUPPLIED) — ' . html_entity_decode($activeOrderOp->name ?? 'Op #' . $activeOp->sequence, ENT_QUOTES, 'UTF-8') : 'ADD PRODUCTION OF — ' . html_entity_decode($activeOrderOp->name ?? 'Op #' . $activeOp->sequence, ENT_QUOTES, 'UTF-8') }}"
                                class="text-start" size="lg">
                                <form method="POST" action="{{ route('production.mes.complete', $activeOp->id) }}"
                                    id="completeForm{{ $activeOp->id }}">
                                    @csrf
                                    <div class="card border-0 bg-light p-3 mb-3 rounded">
                                        <h6 class="fw-bold text-primary mb-1 fs-12 text-uppercase">
                                            <i class="feather-edit me-1"></i>
                                            @if($activeOrderOp->is_external && $activeOrderOp->vendor_supplies_materials)
                                                Receive Subcontract Goods From {{ $activeOrderOp->vendor->name ?? 'Vendor' }} -
                                                {{ $activeOrderOp->name }}
                                            @else
                                                Add Production OF - {{ $activeOrderOp->name ?? 'Process' }},
                                                {{ $activeOp->workCenter->name ?? 'Workstation' }}
                                            @endif
                                            For {{ $activeOrderOp->sourceProduct->name ?? $activeOrder->product->name ?? 'Item' }}
                                        </h6>
                                        <div class="text-muted fs-11">
                                            @if($activeOrderOp->is_external && $activeOrderOp->vendor_supplies_materials)
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
                                                <option value="">{{ __('production.none_selected') }}</option>
                                                @foreach(\App\Models\User::pluck('name', 'id') as $uId => $uName)
                                                    <option value="{{ $uId }}" {{ (int) ($activeOp->assigned_operator_id ?? auth()->id()) === (int) $uId ? 'selected' : '' }}>{{ $uName }}</option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="col-md-4">
                                            <x-ui.odoo-form-ui type="select" label="Shift" name="shift_name">
                                                <option value="Day Shift" selected>{{ __('production.day_shift_standard') }}</option>
                                                <option value="Night Shift">{{ __('production.night_shift') }}</option>
                                                <option value="Morning Shift">{{ __('production.morning_shift_a') }}</option>
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
                                                label="{{ ($activeOrderOp->is_external && $activeOrderOp->vendor_supplies_materials) ? 'Quantity Received' : ($activeIsQcRequired ? 'Processed Output Qty (Pending QC)' : 'Quantity Done') }}"
                                                name="quantity_produced" inputType="number" step="any"
                                                value="{{ $activeRemainingToComplete }}" :required="true" />
                                            <input type="hidden" name="quantity_rejected" value="0">
                                            <input type="hidden" name="quantity_scrapped" value="0">
                                        </div>
                                        @if($activeIsQcRequired)
                                            <div class="col-md-12">
                                                <div class="alert alert-soft-warning mb-0 py-2 fs-11 border border-warning-subtle">
                                                    <i class="feather-shield text-warning me-1"></i> Quality Check is required for this
                                                    process. Logged output enters <strong>{{ __('production.qc_pending') }}</strong> state. Quality rejection &
                                                    scrap disposition are handled via <strong>{{ __('production.run_qc') }}</strong>.
                                                </div>
                                            </div>
                                        @endif

                                        @unless($activeOrderOp->is_external)
                                            <div class="col-md-12">
                                                @include('modules.production.partials.consumed-materials', ['cmId' => 'cmMesDash' . $activeOp->id])
                                            </div>
                                        @endunless

                                        <div class="col-md-12">
                                            <x-ui.odoo-form-ui type="textarea" label="Remarks" name="remarks"
                                                placeholder="{{ ($activeOrderOp->is_external && $activeOrderOp->vendor_supplies_materials) ? 'Optional vendor invoice / delivery note reference...' : 'Optional comments...' }}"
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
                                            class="feather-check me-1"></i>{{ ($activeOrderOp->is_external && $activeOrderOp->vendor_supplies_materials) ? 'Record Subcontract Receipt' : 'Save Production Log' }}
                                    </button>
                                </x-slot>
                            </x-ui.modal>
                        @endif

                            {{-- Assign Operator Modal (Shopfloor Assignment) --}}
                            @php
                                $activeAssignment = $activeOrderOp->relationLoaded('operatorAssignments')
                                    ? $activeOrderOp->operatorAssignments->whereIn('status', ['assigned', 'accepted'])->sortByDesc('id')->first()
                                    : \App\Domains\Production\Models\ProductionOperatorAssignment::where('tenant_id', $activeOp->tenant_id ?? require_tenant_id())
                                        ->where('production_order_operation_id', $activeOrderOp->id)
                                        ->whereIn('status', ['assigned', 'accepted'])
                                        ->latest('id')
                                        ->first();
                                $activeAssignedUser = $activeAssignment?->user ?? null;
                                $isActiveOpStarted = in_array($activeOp->status, ['running', 'paused', 'completed'])
                                    || in_array($activeOrderOp->status ?? '', ['running', 'paused', 'completed'])
                                    || !empty($activeOp->actual_start)
                                    || !empty($activeOrderOp->actual_start_time)
                                    || ((float) ($activeOrderOp->quantity_produced ?? 0) > 0);
                                $canChangeActiveOperator = ($activeOp->status !== 'completed') && (!$isActiveOpStarted || !$activeAssignedUser);
                            @endphp
                            @if(!$isActiveOutsourced && $canChangeActiveOperator)
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
                                                <option value="{{ $operator->id }}" {{ (($activeAssignedUser->id ?? $activeOp->assignedOperator->id ?? $activeOrderOp->operator_id ?? null) == $operator->id) ? 'selected' : '' }}>
                                                    {{ $operator->name }} ({{ ucfirst($operator->role) }})
                                                </option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>

                                        <x-ui.odoo-form-ui type="textarea" label="Remarks / Instructions" name="remarks"
                                            placeholder="Provide shift remarks or operation requirements..." rows="3" />
                                    </form>
                                    <x-slot name="footer">
                                        <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                                        <button type="button" class="btn btn-primary px-4"
                                            onclick="document.getElementById('assignOperatorForm{{ $activeOp->id }}').submit();">
                                            <i class="feather-user-check me-1"></i>{{ __('production.assign_operator') }}</button>
                                    </x-slot>
                                </x-ui.modal>
                            @endif

                            @php
                                $activeRejectedQty = (float) ($activeOrderOp->quantity_rejected ?? 0.0);
                            @endphp

                            {{-- Unified Material Disposition Modal (Scrap, Offcut, Return Unused) --}}
                            @include('modules.production.partials.material-disposition-modal', [
                                'op' => $activeOrderOp,
                                'order' => $activeOrder,
                                'modalId' => 'materialDispositionModal' . $activeOp->id,
                                'warehouses' => $warehouses
                            ])

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

                                    @php
                                        $isOutsourcedOp = (bool) ($activeOrderOp->is_external || $activeOrderOp->isOutsourced() || ($activeOp->work_center_id === null && !config('production.temp_flow')));
                                        $vendorName = $activeOrderOp->vendor->name ?? 'Subcontract Vendor';
                                    @endphp

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

                                        @if($isOutsourcedOp)
                                            <div class="col-md-12">
                                                <label class="form-label fw-bold text-dark fs-11">Rework Location / Pathway <span class="text-danger">*</span></label>
                                                <select class="form-select form-select-sm" name="rework_location" id="reworkLocation_{{ $activeOp->id }}" onchange="toggleReworkFields{{ $activeOp->id }}(this.value)">
                                                    <option value="vendor_rework" selected>Return to Subcontract Vendor (Generate Return Gate Pass / DC)</option>
                                                    <option value="internal_repair">Perform In-House Repair (Internal Workstation)</option>
                                                </select>
                                            </div>

                                            <div id="vendorReworkBanner_{{ $activeOp->id }}" class="col-md-12">
                                                <div class="alert alert-info border border-info-subtle p-2.5 rounded fs-11 mb-0">
                                                    <i class="feather-truck me-1"></i>
                                                    <strong>Subcontract Vendor Rework</strong>: A Rework Delivery Challan (Return Gate Pass) will be created to dispatch <strong>{{ number_format($activeRejectedQty, 0) }} unit(s)</strong> back to <strong>{{ $vendorName }}</strong> for rework/rectification.
                                                </div>
                                            </div>
                                        @endif

                                        <div class="col-md-12 p-0 m-0">
                                            <div id="internalReworkFields_{{ $activeOp->id }}" class="row g-3 m-0" style="{{ $isOutsourcedOp ? 'display: none;' : '' }}">
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

                                                <div class="col-md-6">
                                                    <x-ui.odoo-form-ui type="select" label="Assigned Repair Technician" name="assigned_to">
                                                        @foreach($operators as $tech)
                                                            <option value="{{ $tech->id }}" {{ (auth()->id() == $tech->id) ? 'selected' : '' }}>
                                                                {{ $tech->name }} ({{ ucfirst($tech->role ?? 'Technician') }})
                                                            </option>
                                                        @endforeach
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <x-ui.odoo-form-ui type="select" label="Rework Strategy" name="rework_type">
                                                <option value="reprocess" selected>Reprocess / Re-run Machine</option>
                                                <option value="repair">Manual Touch-up / Spot Repair</option>
                                                <option value="re_machining">Re-machining / Trim Specification</option>
                                                <option value="re_coating">Strip & Re-surface / Re-paint</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="col-md-6">
                                            <x-ui.odoo-form-ui type="input" :label="'Estimated Repair Cost (' . active_currency_symbol() . ')'" name="cost_estimate"
                                                inputType="number" step="0.01" value="50.00" />
                                        </div>

                                        <div class="col-md-12">
                                            <x-ui.odoo-form-ui type="textarea" label="Reason / Defect Description" name="reason"
                                                placeholder="Explain defect cause (e.g. Surface Scratch, Dimension Out of Spec)..."
                                                rows="2" />
                                        </div>
                                        <div class="col-md-12">
                                            <x-ui.odoo-form-ui type="textarea" label="Detailed Rework Instructions for Vendor / Technician"
                                                name="instructions"
                                                placeholder="Provide step-by-step repair instructions..."
                                                rows="2" />
                                        </div>
                                    </div>
                                    @if($isOutsourcedOp)
                                        <script>
                                            function toggleReworkFields{{ $activeOp->id }}(val) {
                                                const internalDiv = document.getElementById('internalReworkFields_{{ $activeOp->id }}');
                                                const vendorBanner = document.getElementById('vendorReworkBanner_{{ $activeOp->id }}');
                                                if (val === 'internal_repair') {
                                                    if (internalDiv) internalDiv.style.display = 'flex';
                                                    if (vendorBanner) vendorBanner.style.display = 'none';
                                                } else {
                                                    if (internalDiv) internalDiv.style.display = 'none';
                                                    if (vendorBanner) vendorBanner.style.display = 'block';
                                                }
                                            }
                                        </script>
                                    @endif
                                </form>
                                <x-slot name="footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                                    <button type="button" class="btn btn-danger fw-bold px-4"
                                        onclick="document.getElementById('dispositionForm{{ $activeOp->id }}').submit();">
                                        <i class="feather-check-circle me-1"></i>Submit Disposition
                                    </button>
                                </x-slot>
                            </x-ui.modal>
                    @endforeach
                @empty
                    <div class="card p-5 text-center border bg-white rounded-3 shadow-sm mb-4 mes-empty-card">
                        <div class="avatar-text avatar-lg bg-soft-light text-muted rounded mx-auto mb-3">
                            <i class="feather-grid fs-28"></i>
                        </div>
                        <h6 class="fw-bold text-dark">{{ __('production.no_active_projects') }}</h6>
                        <p class="text-muted fs-12 mb-0">{{ __('production.create_release_schedule_desc') }}</p>
                    </div>
                @endforelse

    </div>

@endsection
