@extends('layouts.duralux')

@section('title', 'MES Execute Operation | SaaS ERP')
@section('page-title', 'Execute Operation: ' . html_entity_decode($op->name ?? '—', ENT_QUOTES, 'UTF-8'))
@section('breadcrumb', 'Execute Operation')

@push('styles')
    <style>
        .num-btn {
            min-height: 56px;
            font-size: 18px;
            font-weight: 700;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .touch-tab {
            min-height: 44px;
            font-weight: 600;
        }
    </style>
@endpush

@section('page-actions')
    <x-ui.button href="{{ route('production.labels.orders.print', $order->id) }}" target="_blank" icon="feather-printer"
        variant="outline-dark" class="me-2">
        {{ __('production.print_order_label') }}
    </x-ui.button>
    <x-ui.icon-btn href="{{ route('production.mes.operator.dashboard') }}" icon="feather-arrow-left"
        variant="transparent-dark" title="Dashboard">
        Dashboard
    </x-ui.icon-btn>
@endsection

@section('content')

    {{-- ── MES Operation Execution Workflow Guidance Component ── --}}
    @php
        $maxSeq = $order->operations->max('sequence');
        $isFinalOp = ($op->sequence == $maxSeq);
    @endphp

    <x-ui.workflow-guide :title="__('production.whats_next')">
        @if($op->status !== 'running' && $op->status !== 'paused' && $op->status !== 'completed')
            {!! __('production.op_execution_guide_not_started', [
                'start_badge' => '<span class="badge bg-soft-success text-success border border-success-subtle fw-semibold">' . __('production.start_operation') . '</span>'
            ]) !!}
        @elseif($op->status === 'completed')
            @if($isFinalOp)
                {!! __('production.op_execution_guide_completed_final', [
                    'order' => '<strong class="text-dark">' . e($order->order_number) . '</strong>',
                    'wip_link' => '<a href="' . url('production/wip') . '?search=' . urlencode($order->order_number) . '" class="fw-bold text-primary text-decoration-underline">' . __('production.wip_tracking_page') . '</a>'
                ]) !!}
            @else
                {{ __('production.op_execution_guide_completed_intermediate') }}
            @endif
        @else
            @php
                $reworkLink = '<a href="' . url('production/quality/rework') . '" class="fw-bold text-primary text-decoration-underline">' . __('production.rework_management') . '</a>';
                $scrapLink = '<a href="' . url('production/quality/scrap') . '" class="fw-bold text-primary text-decoration-underline">' . __('production.scrap_management') . '</a>';
            @endphp
            @if(strtolower($order->production_mode ?? '') === 'batch')
                {!! __('production.op_execution_guide_running_batch', ['rework_link' => $reworkLink, 'scrap_link' => $scrapLink]) !!}
            @elseif(strtolower($order->production_mode ?? '') === 'serial')
                {!! __('production.op_execution_guide_running_serial', ['rework_link' => $reworkLink, 'scrap_link' => $scrapLink]) !!}
            @else
                {!! __('production.op_execution_guide_running_standard', ['rework_link' => $reworkLink, 'scrap_link' => $scrapLink]) !!}
            @endif

            @if($isFinalOp)
                <div class="mt-1.5 fs-12 text-dark">
                    <i class="feather-check-circle me-1 text-primary"></i>{!! __('production.op_execution_guide_final_note', [
                        'wip_link' => '<a href="' . url('production/wip') . '?search=' . urlencode($order->order_number) . '" class="fw-bold text-primary text-decoration-underline">' . __('production.wip_tracking_page') . '</a>',
                        'order' => '<strong>' . e($order->order_number) . '</strong>'
                    ]) !!}
                </div>
            @endif
        @endif
    </x-ui.workflow-guide>

    <div class="erp-single-panel bg-white p-4">

        <x-ui.odoo-form-ui type="sheet">

            {{-- Header Identity Row --}}
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <div>
                    <x-ui.badge variant="secondary" soft
                        class="font-monospace mb-2">{{ $op->operation_number ?? 'OP-??' }}</x-ui.badge>
                    <h3 class="fw-bold text-dark mb-1">{{ html_entity_decode($op->name ?? '', ENT_QUOTES, 'UTF-8') }}</h3>
                    <p class="text-muted fs-13 mb-0">
                        Order: <strong class="text-dark">{{ $order->order_number }}</strong> | Product: <strong
                            class="text-dark">{{ html_entity_decode($order->product->name ?? '', ENT_QUOTES, 'UTF-8') }}</strong>
                        | Mode: <x-ui.badge variant="info" soft
                            class="font-monospace ms-1">{{ strtoupper($order->production_mode) }}</x-ui.badge>
                    </p>
                </div>
                <div class="text-end">
                    <div class="fs-11 text-muted uppercase font-semibold mb-1">{{ __('production.status') }}</div>
                    @php
                        $statusVariant = match ($op->status) {
                            'running' => 'success',
                            'paused' => 'warning',
                            'completed' => 'secondary',
                            default => 'primary',
                        };
                        $readiness = app(\App\Domains\Production\Services\MesExecutionService::class)->calculateOperationReadiness($op);

                        // Unified progress and quantity metrics (matching Shopfloor execution)
                        $opTargetQty = isset($targetQty) ? (float) $targetQty : (float) ($op->target_produced_qty > 0 ? $op->target_produced_qty : ($order->quantity_ordered ?? 0.0));
                        $opDoneQty = isset($doneQty) ? (float) $doneQty : (float) ($op->quantity_produced ?? 0.0);
                        $opScrapQty = isset($scrapQty) ? (float) $scrapQty : (float) ($op->quantity_scrapped ?? 0.0);
                        $opRejectedQty = isset($rejectedQty) ? (float) $rejectedQty : (float) ($op->quantity_rejected ?? 0.0);
                        $opPendingQcQty = isset($pendingQcQty) ? (float) $pendingQcQty : (float) app(\App\Domains\Production\Services\MesExecutionService::class)->getPendingQcQuantity($op->id);
                        $opRemainingQty = isset($remainingQty) ? (float) $remainingQty : max(0.0, $opTargetQty - ($opDoneQty + $opPendingQcQty + $opScrapQty + $opRejectedQty));
                        $opProgressPercent = isset($progressPercent) ? (float) $progressPercent : ($opTargetQty > 0 ? min(100.0, round(($opDoneQty / $opTargetQty) * 100, 1)) : ($op->status === 'completed' ? 100.0 : 0.0));
                        $itemUom = $op->sourceProduct->uom->symbol ?? $order->product->uom->symbol ?? 'PCS';
                        $isQcRequired = (bool) ($op->quality_required || ($op->routingOperation?->quality_required ?? false));
                    @endphp
                    <x-ui.badge :variant="$statusVariant"
                        class="fs-13 px-3 py-2 fw-bold">{{ strtoupper($op->status) }}</x-ui.badge>
                </div>
            </div>

            {{-- Operation Execution Progress & Output Status Card --}}
            <div class="card border border-light shadow-sm mb-4" style="border-radius: 12px; overflow: hidden;">
                <div class="card-body p-3 bg-light-subtle">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded">
                                <i class="feather-activity fs-16"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13">Operation Output and Completion Progress</h6>
                                <span class="fs-11 text-muted">Item: <strong class="text-dark">{{ $op->sourceProduct->name ?? $order->product->name }}</strong> ({{ $itemUom }})</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @if($op->status === 'completed' || ($opTargetQty > 0 && $opDoneQty >= ($opTargetQty - 0.0001)))
                                <span class="badge bg-success text-white px-2.5 py-1.5 fs-11 font-monospace">
                                    <i class="feather-check-circle me-1"></i>COMPLETED
                                </span>
                            @else
                                <span class="badge bg-soft-primary text-primary border border-primary-subtle px-2.5 py-1.5 fs-11 font-monospace">
                                    <i class="feather-clock me-1"></i>IN PROGRESS ({{ number_format($opProgressPercent, 1) }}%)
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Progress Bar --}}
                    <div class="progress mb-3" style="height: 8px; border-radius: 6px;">
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar"
                            style="width: {{ $opProgressPercent }}%;"
                            aria-valuenow="{{ $opProgressPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>

                    {{-- Metric Grid --}}
                    <div class="row g-2 text-center">
                        <div class="col-6 col-sm-4 col-md">
                            <div class="bg-white border rounded p-2.5 h-100 shadow-2xs">
                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.target_quantity') }}</span>
                                <span class="fs-18 fw-extrabold text-dark font-monospace">{{ number_format($opTargetQty, 2) }}</span>
                                <small class="text-muted d-block fs-10">{{ $itemUom }}</small>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4 col-md">
                            <div class="bg-white border border-success-subtle rounded p-2.5 h-100 shadow-2xs bg-soft-success-subtle">
                                <span class="fs-10 text-success text-uppercase fw-bold d-block mb-1">
                                    <i class="feather-check text-success me-0.5"></i>Completed / Produced
                                </span>
                                <span class="fs-18 fw-extrabold text-success font-monospace">{{ number_format($opDoneQty, 2) }}</span>
                                <small class="text-muted d-block fs-10">{{ $itemUom }}</small>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4 col-md">
                            <div class="bg-white border border-danger-subtle rounded p-2.5 h-100 shadow-2xs {{ $opRemainingQty > 0 ? 'bg-soft-danger-subtle' : '' }}">
                                <span class="fs-10 text-danger text-uppercase fw-bold d-block mb-1">
                                    <i class="feather-alert-circle text-danger me-0.5"></i>Remaining Left
                                </span>
                                <span class="fs-18 fw-extrabold text-danger font-monospace">{{ number_format($opRemainingQty, 2) }}</span>
                                <small class="text-muted d-block fs-10">{{ $itemUom }}</small>
                            </div>
                        </div>
                        @if($isQcRequired)
                            <div class="col-6 col-sm-4 col-md">
                                <div class="bg-white border border-warning-subtle rounded p-2.5 h-100 shadow-2xs">
                                    <span class="fs-10 text-warning text-uppercase fw-bold d-block mb-1">
                                        <i class="feather-shield text-warning me-0.5"></i>{{ __('production.pending_qc') }}</span>
                                    <span class="fs-18 fw-extrabold text-warning font-monospace">{{ number_format($opPendingQcQty, 2) }}</span>
                                    <small class="text-muted d-block fs-10">{{ $itemUom }}</small>
                                </div>
                            </div>
                        @endif
                        @if($opScrapQty > 0)
                            <div class="col-6 col-sm-4 col-md">
                                <div class="bg-white border border-secondary-subtle rounded p-2.5 h-100 shadow-2xs">
                                    <span class="fs-10 text-secondary text-uppercase fw-bold d-block mb-1">Scrapped</span>
                                    <span class="fs-18 fw-extrabold text-secondary font-monospace">{{ number_format($opScrapQty, 2) }}</span>
                                    <small class="text-muted d-block fs-10">{{ $itemUom }}</small>
                                </div>
                            </div>
                        @endif
                        @if($opRejectedQty > 0)
                            <div class="col-6 col-sm-4 col-md">
                                <div class="bg-white border border-danger-subtle rounded p-2.5 h-100 shadow-2xs">
                                    <span class="fs-10 text-danger text-uppercase fw-bold d-block mb-1">{{ __('production.rejected') }}</span>
                                    <span class="fs-18 fw-extrabold text-danger font-monospace">{{ number_format($opRejectedQty, 2) }}</span>
                                    <small class="text-muted d-block fs-10">{{ $itemUom }}</small>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- F-04: Multi-Level Read-Only Readiness Panel --}}
            @if($op->predecessorDependencies->isNotEmpty() || $op->is_intermediate)
                <div class="card border mb-4 bg-light shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-dark mb-0"><i class="feather-shield text-primary me-2"></i>Multi-Level Component Execution Readiness</h6>
                            @if($op->status === 'completed')
                                <span class="badge bg-soft-success text-success fw-bold px-2 py-1"><i class="feather-check-circle me-1"></i>{{ __('production.completed_schedules') }}</span>
                            @elseif($readiness['is_ready'])
                                <span class="badge bg-soft-success text-success fw-bold px-2 py-1"><i class="feather-check-circle me-1"></i>Executable</span>
                            @else
                                <span class="badge bg-soft-danger text-danger fw-bold px-2 py-1"><i class="feather-alert-triangle me-1"></i>Dependency Waiting</span>
                            @endif
                        </div>
                        <div class="row g-3 text-center border-top pt-2 mt-1">
                            <div class="col-6 col-md-3">
                                <span class="fs-11 text-muted text-uppercase d-block">Component Source</span>
                                <strong class="text-dark fs-13">{{ $op->sourceProduct->name ?? $order->product->name }}</strong>
                                <span class="badge bg-soft-info text-info fs-10 ms-1">Level {{ $op->bom_level ?? 1 }}</span>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="fs-11 text-muted text-uppercase d-block">Max Executable Qty</span>
                                <strong class="fs-14 text-dark font-monospace">{{ number_format($readiness['executable_qty'], 2) }}</strong>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="fs-11 text-muted text-uppercase d-block">Already Claimed / Processed</span>
                                <strong class="fs-14 text-secondary font-monospace">{{ number_format($readiness['claimed_qty'], 2) }}</strong>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="fs-11 text-muted text-uppercase d-block">Remaining Executable</span>
                                <strong class="fs-14 text-primary font-monospace">{{ number_format($readiness['remaining_executable_qty'], 2) }}</strong>
                            </div>
                        </div>
                        @if(!empty($readiness['warnings']))
                            <div class="alert alert-warning py-1.5 px-3 fs-11 mb-2 mt-3 border border-warning-subtle">
                                <i class="feather-alert-circle me-1"></i><strong>Material Issue Pending:</strong> {{ implode(' | ', $readiness['warnings']) }}
                            </div>
                        @endif
                        @if(!empty($readiness['blockers']))
                            <div class="alert alert-danger py-1.5 px-3 fs-11 mb-0 mt-2 border border-danger-subtle">
                                <i class="feather-alert-triangle me-1"></i><strong>Blockers:</strong> {{ implode(' | ', $readiness['blockers']) }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Execution Controls --}}
            @if($op->is_external)
                @php
                    $user = auth()->user();
                    $accessService = app(\App\Services\Access\AccessService::class);
                    $canPurchase = $user && ($accessService->allows($user, 'purchase.orders.view') || $accessService->allows($user, 'purchase.requisitions.view'));
                    $canQuality = $user && $accessService->allows($user, 'production.quality.view');
                    $canInventory = $user && $accessService->allows($user, 'inventory.transfers.view');


                    // H-06: from the dispatch slips / receipts of this operation
                    $scBalance = app(\App\Domains\Production\Services\SubcontractMaterialBalanceService::class)->getMaterialBalance($order->tenant_id, $order->id, $op->id);
                    $sentQty = $scBalance['units_sent'];
                    $atVendorQty = $scBalance['units_at_vendor'];
                    $receivedQty = $op->quantity_produced ?? 0;
                    $rejectedQty = $op->quantity_rejected ?? 0;
                    $scrappedQty = $op->quantity_scrapped ?? 0;
                    $qcPending = (float) \App\Domains\Production\Models\ProductionSubcontractReceipt::where('production_order_operation_id', $op->id)
                        ->where('status', 'qc_pending')->get()
                        ->sum(fn ($r) => max(0, $r->received_qty - $r->scrapped_qty - $r->rejected_qty - $r->accepted_qty));

                    // H-02 / N-18: dates from the operation's scheduled start
                    $scDates = app(\App\Domains\Production\Services\SubcontractDates::class);
                    $plannedDispatch = $scDates->leavesPlant($op)->format('d/m/Y');
                    $actualDispatch = $op->actual_start_time ? $op->actual_start_time->format('d/m/Y H:i') : '—';
                    $expectedReturn = $scDates->expectedReturnDate($op)->format('d/m/Y');
                    $actualReceipt = $op->actual_end_time ? $op->actual_end_time->format('d/m/Y H:i') : '—';
                @endphp

                <div class="alert alert-warning border border-warning bg-soft-warning p-4 rounded-3 mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-warning-subtle pb-3">
                        <div class="d-flex align-items-center">
                            <i class="feather-external-link text-warning fs-24 me-3"></i>
                            <div>
                                <h5 class="fw-bold text-dark mb-0">External Subcontracted Operation</h5>
                                <small class="text-dark">This operation is executed off-site by vendor <strong>{{ $op->vendor->name ?? 'Subcontractor' }}</strong>. Shop-floor machine execution is disabled; the work goes out on a dispatch slip and comes back on a receipt.</small>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            {{-- SF-4: no PR / PO — dispatch slip and receipt page (redirects, no popup) --}}
                            @if($op->needsDispatchSlip())
                                <a href="{{ route('production.subcontract.delivery-challans.create', ['production_order_id' => $order->id, 'operation_id' => $op->id]) }}" class="btn btn-sm btn-outline-primary fw-semibold">
                                    <i class="feather-send me-1"></i>Dispatch slip
                                </a>
                            @endif
                            <a href="{{ route('production.subcontract.receipts.create', ['operation_id' => $op->id, 'source' => 'shop_floor']) }}" class="btn btn-sm btn-outline-success fw-semibold">
                                <i class="feather-download-cloud me-1"></i>Receive from vendor
                            </a>
                            @if($canQuality && \Illuminate\Support\Facades\Route::has('production.quality.rework.index'))
                                <a href="{{ route('production.quality.rework.index') }}" class="btn btn-sm btn-outline-warning fw-semibold">
                                    <i class="feather-shield me-1"></i>Quality & Rework
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="row g-3 fs-12">
                        <div class="col-md-3">
                            <span class="text-muted text-uppercase fs-10 fw-bold d-block">Subcontractor</span>
                            <strong class="text-dark">{{ $op->vendor->name ?? 'N/A' }} ({{ $op->vendor->code ?? '' }})</strong>
                            @if($op->vendor_supplies_materials)<div class="text-muted fs-11 mt-0.5"><i class="feather-package me-1"></i>Vendor uses own materials</div>@endif
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted text-uppercase fs-10 fw-bold d-block">Vendor times</span>
                            <div>Dispatch <strong>{{ \App\Domains\Production\Services\SubcontractDates::formatHours($op->vendor_dispatch_hours) }}</strong> · processing <strong>{{ \App\Domains\Production\Services\SubcontractDates::formatHours($op->vendor_processing_hours) }}</strong></div>
                            <div>Receipt <strong>{{ \App\Domains\Production\Services\SubcontractDates::formatHours($op->vendor_receipt_hours) }}</strong></div>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted text-uppercase fs-10 fw-bold d-block">Dispatch & Timing</span>
                            <div>Disp: <strong>{{ $plannedDispatch }}</strong> <small class="text-muted">({{ $actualDispatch }})</small></div>
                            <div>Ret: <strong>{{ $expectedReturn }}</strong> <small class="text-muted">({{ $actualReceipt }})</small></div>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted text-uppercase fs-10 fw-bold d-block">Quantity & QC Status</span>
                            <div>Sent: <strong>{{ number_format($sentQty, 2) }}</strong> | At Vendor: <strong class="text-warning">{{ number_format($atVendorQty, 2) }}</strong></div>
                            <div>Rec: <strong class="text-success">{{ number_format($receivedQty, 2) }}</strong> | QC Pend: <strong class="text-info">{{ number_format($qcPending, 2) }}</strong></div>
                            @if($rejectedQty > 0 || $scrappedQty > 0)
                                <div class="text-danger">Rej: {{ number_format($rejectedQty, 2) }} | Scrap: {{ number_format($scrappedQty, 2) }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="row g-4 mb-4" @if($op->is_external) style="display:none;" @endif>
                <div class="col-lg-6">
                    <x-ui.card :title="__('production.touch_controls')" class="h-100 border">
                        <div class="d-flex flex-column h-100 justify-content-between p-2">
                            @if($scheduleOp && ($scheduleOp->status === 'running' || $scheduleOp->status === 'paused'))
                                <div class="text-center mb-4 bg-light py-3 rounded border">
                                    <div class="text-muted fs-11 uppercase font-semibold mb-1">Active Execution Time</div>
                                    <h1 class="display-6 fw-bold text-dark font-monospace mb-0" id="opLiveTimer"
                                        data-status="{{ $scheduleOp->status }}"
                                        data-start="{{ $scheduleOp->actual_start ? $scheduleOp->actual_start->toIso8601String() : '' }}"
                                        data-paused-at="{{ $scheduleOp->last_paused_at ? $scheduleOp->last_paused_at->toIso8601String() : '' }}"
                                        data-accumulated-paused="{{ $scheduleOp->accumulated_paused_seconds ?? 0 }}">
                                        00:00:00
                                    </h1>
                                </div>
                            @endif

                            <div class="row g-2">
                                @if($op->status !== 'running' && $op->status !== 'paused' && $op->status !== 'completed')
                                    @php $startFlow = $readiness['readiness_evaluation']['dependency'] ?? null; @endphp
                                    <div class="col-12">
                                        @if(($startFlow['state'] ?? '') === 'waiting')
                                            {{-- UR-07: waiting for its predecessor / the operations that make its sub-assembly — cannot start yet --}}
                                            <div class="alert alert-info py-2 px-3 fs-12 mb-2"><i class="feather-clock me-1"></i><strong>{{ $startFlow['label'] }}</strong>@if(!empty($startFlow['detail'])) — {{ $startFlow['detail'] }}@endif</div>
                                            <x-ui.button type="button" variant="secondary" icon="feather-lock" class="btn-touch-large w-100" disabled>
                                                START OPERATION
                                            </x-ui.button>
                                        @else
                                            <form method="POST"
                                                action="{{ route('production.mes.start', optional($scheduleOp)->id ?? $op->id) }}">
                                                @csrf
                                                <x-ui.button type="submit" variant="success" icon="feather-play"
                                                    class="btn-touch-large w-100">
                                                    START OPERATION
                                                </x-ui.button>
                                            </form>
                                        @endif
                                    </div>
                                @endif

                                @if($op->status === 'running')
                                    <div class="col">
                                        <x-ui.button variant="warning" icon="feather-pause" class="btn-touch-large w-100"
                                            data-bs-toggle="modal" data-bs-target="#pauseModal">
                                            PAUSE
                                        </x-ui.button>
                                    </div>
                                @endif

                                @if($op->status === 'paused')
                                    <div class="col">
                                        <form method="POST"
                                            action="{{ route('production.mes.resume', optional($scheduleOp)->id ?? $op->id) }}"
                                            class="w-100">
                                            @csrf
                                            <x-ui.button type="submit" variant="success" icon="feather-play"
                                                class="btn-touch-large w-100">
                                                RESUME
                                            </x-ui.button>
                                        </form>
                                    </div>
                                @endif

                                @if($op->status === 'running' || $op->status === 'paused')
                                    @php
                                        $isQcRequired = (bool) ($op->quality_required || ($op->routingOperation?->quality_required ?? false));
                                        $touchPendingQcQty = $pendingQcQty ?? app(\App\Domains\Production\Services\MesExecutionService::class)->getPendingQcQuantity($op->id);
                                        $touchRejectedQty = (float) ($op->quantity_rejected ?? 0.0);
                                    @endphp

                                    <div class="col">
                                        <x-ui.button variant="info" icon="feather-edit-3"
                                            class="btn-touch-large w-100 text-white" data-bs-toggle="modal"
                                            data-bs-target="#logProgressModal">
                                            LOG PROGRESS
                                        </x-ui.button>
                                    </div>

                                    <div class="col">
                                        <x-ui.button variant="outline-primary" icon="feather-package"
                                            class="btn-touch-large w-100 fw-bold shadow-2xs" data-bs-toggle="modal"
                                            data-bs-target="#materialDispositionModal">
                                            MATERIAL DISPOSITION
                                        </x-ui.button>
                                    </div>

                                    @if($isQcRequired && $touchPendingQcQty > 0)
                                        <div class="col">
                                            <x-ui.button variant="warning" icon="feather-shield-check"
                                                class="btn-touch-large w-100 text-dark btn-badge-container"
                                                href="{{ route('production.quality.operation-inspection.show', ['op' => $op->id, 'source' => 'operator']) }}">
                                                QC CHECK
                                                <span class="btn-badge-count">{{ number_format($touchPendingQcQty, 0) }}</span>
                                            </x-ui.button>
                                        </div>
                                    @endif

                                    @if($touchRejectedQty > 0)
                                        <div class="col">
                                            <x-ui.button variant="danger" icon="feather-alert-triangle"
                                                class="btn-touch-large w-100 text-white btn-badge-container" data-bs-toggle="modal"
                                                data-bs-target="#dispositionModal">
                                                REWORK / SCRAP
                                                <span class="btn-badge-count">{{ number_format($touchRejectedQty, 0) }}</span>
                                            </x-ui.button>
                                        </div>
                                    @endif
                                @endif

                                @if($op->status === 'completed')
                                    @php
                                        $touchRejectedQty = (float) ($op->quantity_rejected ?? 0.0);
                                    @endphp
                                    <div class="col-12 text-center py-4">
                                        <i class="feather-check-circle text-success fs-48 mb-2 d-block"></i>
                                        <h5 class="fw-bold text-dark">Operation is Completed</h5>
                                        <p class="text-muted mb-0 fs-13">This operation's steps on the shop floor have finished successfully.</p>
                                        @if($touchRejectedQty > 0)
                                            <div class="mt-3">
                                                <x-ui.button variant="danger" icon="feather-alert-triangle"
                                                    class="btn-touch-large text-white px-4 btn-badge-container" data-bs-toggle="modal"
                                                    data-bs-target="#dispositionModal">
                                                    DISPOSITION REJECTED UNITS
                                                    <span class="btn-badge-count">{{ number_format($touchRejectedQty, 0) }}</span>
                                                </x-ui.button>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </x-ui.card>
                </div>

                {{-- Quick Stats / Assignment info --}}
                <div class="col-lg-6">
                    <x-ui.card :title="__('production.operator_info_instructions')" class="h-100 border">
                        <div class="mb-3">
                            <span class="text-muted d-block fs-11 uppercase font-semibold">Assigned Operator</span>
                            <div class="d-flex align-items-center mt-1">
                                <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle me-2">
                                    <i class="feather-user"></i>
                                </div>
                                <span class="fw-bold text-dark me-3 fs-13">
                                    {{ $assignment ? $assignment->user->name : 'No active assignments' }}
                                    @if($assignment)
                                        @if($assignment->status === 'assigned')
                                            <x-ui.badge variant="warning" soft class="fs-10 ms-1">Pending Acceptance</x-ui.badge>
                                        @elseif($assignment->status === 'accepted')
                                            <x-ui.badge variant="success" soft class="fs-10 ms-1">Accepted</x-ui.badge>
                                        @endif
                                    @endif
                                </span>
                                @can('manage', \App\Domains\Production\Models\ProductionOperatorAssignment::class)
                                    <x-ui.button variant="outline-primary" icon="feather-user-plus me-1"
                                        class="ms-auto" data-bs-toggle="modal" data-bs-target="#assignOperatorModal">
                                        {{ $assignment ? 'Reassign' : 'Assign' }}
                                    </x-ui.button>
                                @endcan
                            </div>
                        </div>
                        <div class="mb-2 pt-3 border-top">
                            <span class="text-muted d-block fs-11 uppercase font-semibold mb-1">Process Instructions</span>
                            <div class="bg-light p-3 rounded text-dark font-monospace fs-13 border"
                                style="max-height: 120px; overflow-y: auto;">
                                {!! nl2br(e($op->instructions ?? 'No special process instructions provided for this step.')) !!}
                            </div>
                        </div>
                    </x-ui.card>
                </div>
            </div>

            {{-- Dynamic Tabs based on Production Mode --}}
            @php
                $defaultTab = ($order->production_mode === 'serial') ? 'serial-content' : 'batch-content';
                $activeMesTab = request('tab', request('active_tab', session('active_tab', $defaultTab)));
                if ($activeMesTab !== 'batch-content' && $activeMesTab !== 'serial-content') {
                    $activeMesTab = $defaultTab;
                }

                $mesTabs = [];
                if ($order->production_mode === 'batch' || $order->production_mode === 'batch_and_serial') {
                    $mesTabs[] = [
                        'id' => 'batch-content',
                        'label' => __('production.batch_control_panel'),
                        'active' => ($activeMesTab === 'batch-content'),
                        'icon' => 'feather-box',
                    ];
                }
                if ($order->production_mode === 'serial' || $order->production_mode === 'batch_and_serial') {
                    $mesTabs[] = [
                        'id' => 'serial-content',
                        'label' => __('production.serial_numbers_manager'),
                        'active' => ($activeMesTab === 'serial-content'),
                        'icon' => 'feather-hash',
                    ];
                }
            @endphp

            @if($order->production_mode !== 'standard' && !empty($mesTabs))
                <div class="mt-4">
                    <x-ui.horizontal-tabs id="mesOperatorTabs" :tabs="$mesTabs" class="mb-3" />

                    <div class="tab-content pt-2" id="mesOperatorTabsContent">
                        {{-- Batch Tab --}}
                        @if($order->production_mode === 'batch' || $order->production_mode === 'batch_and_serial')
                            <div class="tab-pane fade @if($activeMesTab === 'batch-content') show active @endif" id="batch-content"
                                role="tabpanel" aria-labelledby="batch-content-tab">
                                @include('modules.production.mes.operator.batch-production')
                            </div>
                        @endif
                        {{-- Serial Tab --}}
                        @if($order->production_mode === 'serial' || $order->production_mode === 'batch_and_serial')
                            <div class="tab-pane fade @if($activeMesTab === 'serial-content') show active @endif"
                                id="serial-content" role="tabpanel" aria-labelledby="serial-content-tab">
                                @include('modules.production.mes.operator.serial-production')
                            </div>
                        @endif
                    </div>
                </div>
            @endif

        </x-ui.odoo-form-ui>

    </div>{{-- end .erp-single-panel --}}

    {{-- Pause Modal --}}
    <x-ui.modal id="pauseModal" title="Pause Operation" centered="true"
        formAction="{{ route('production.mes.pause', optional($scheduleOp)->id ?? $op->id) }}" submitText="Pause Operation"
        closeText="Cancel">
        <x-ui.odoo-form-ui type="textarea" label="Reason for Pause / Remarks" name="remarks"
            placeholder="Enter reason (e.g. material shortage, machine breakdown)..." :required="true" rows="3" />
    </x-ui.modal>

    {{-- Log Daily/Partial Progress Modal (Touch Numeric Pad) --}}
    <x-ui.modal id="logProgressModal" title="Log Shift / Daily Progress" centered="true" size="lg"
        formAction="{{ route('production.mes.complete', optional($scheduleOp)->id ?? $op->id) }}"
        submitText="Submit Progress Log" closeText="Cancel">
        <div class="row g-4 text-start">
            {{-- Numeric Pad & Inputs --}}
            <div class="col-md-7 border-end pe-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="form-label uppercase font-semibold fs-11 text-muted mb-0">Touch Keypad (Quantity Produced)</label>
                    <span class="fs-11 text-muted">Remaining: <strong class="text-danger font-monospace">{{ number_format($opRemainingQty, 2) }} {{ $itemUom }}</strong></span>
                </div>

                <div class="row g-2 mb-3">
                    @for($i = 1; $i <= 9; $i++)
                        <div class="col-4">
                            <x-ui.button type="button" variant="light" class="num-btn w-100"
                                onclick="numPress('{{ $i }}')">{{ $i }}</x-ui.button>
                        </div>
                    @endfor
                    <div class="col-4">
                        <x-ui.button type="button" variant="light" class="num-btn w-100"
                            onclick="numPress('.')">.</x-ui.button>
                    </div>
                    <div class="col-4">
                        <x-ui.button type="button" variant="light" class="num-btn w-100"
                            onclick="numPress('0')">0</x-ui.button>
                    </div>
                    <div class="col-4">
                        <x-ui.button type="button" variant="soft-danger" class="num-btn w-100" onclick="numPress('C')"><i
                                class="feather-delete"></i></x-ui.button>
                    </div>
                </div>
            </div>

            {{-- Target Quantities --}}
            <div class="col-md-5 ps-md-4">
                <div class="bg-light p-2.5 rounded border mb-3 fs-11">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Target Output:</span>
                        <strong class="font-monospace text-dark">{{ number_format($opTargetQty, 2) }} {{ $itemUom }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Already Done:</span>
                        <strong class="font-monospace text-success">{{ number_format($opDoneQty, 2) }} {{ $itemUom }}</strong>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-1 mt-1">
                        <span class="fw-bold text-dark">Remaining to Produce:</span>
                        <strong class="font-monospace text-danger">{{ number_format($opRemainingQty, 2) }} {{ $itemUom }}</strong>
                    </div>
                </div>

                @if($isQcRequired)
                    <div class="alert alert-soft-warning py-2 fs-11 mb-3 border border-warning-subtle">
                        <i class="feather-shield text-warning me-1"></i>
                        <strong>Quality Check Required:</strong>
                        <div>Output logged here enters <strong>{{ __('production.pending_qc') }}</strong> status until inspected. Rejections must be recorded through <strong>QC Check</strong>, and scrap through <strong>{{ __('production.log_scrap_tab') }}</strong>.</div>
                    </div>
                @endif

                @if(!empty($batchQueue['active']))
                    <x-ui.odoo-form-ui type="select" label="Production Batch" name="production_batch_id">
                        @foreach($batchQueue['active'] as $item)
                            <option value="{{ $item['batch']->id }}">
                                Batch #{{ $item['batch']->batch_number }} (Remaining:
                                {{ number_format($item['remaining_to_process'], 2) }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                @endif
                <x-ui.odoo-form-ui type="input"
                    label="{{ $isQcRequired ? 'Processed Output Qty (Pending QC)' : 'Quantity Produced / Output' }}"
                    name="quantity_produced"
                    id="log_producedInput" inputType="number" step="0.0001" value="{{ $opRemainingQty > 0 ? (float) $opRemainingQty : 0 }}" :required="true" />
                <input type="hidden" name="quantity_rejected" value="0" id="log_rejectedInput">
                <input type="hidden" name="quantity_scrapped" value="0" id="log_scrappedInput">
                <x-ui.odoo-form-ui type="textarea" label="Remarks" name="remarks"
                    placeholder="Optional shift handover comments..." rows="2" />
                @include('modules.production.partials.consumed-materials', ['cmId' => 'cmMesLog'])
            </div>
        </div>
    </x-ui.modal>

    {{-- Assign/Reassign Operator Modal --}}
    <x-ui.modal id="assignOperatorModal" title="{{ $assignment ? 'Reassign Operator' : 'Assign Operator' }}" centered="true"
        formAction="{{ $assignment ? route('production.mes.assignments.reassign', $assignment->id) : route('production.mes.assignments.assign') }}"
        submitText="{{ $assignment ? 'Reassign' : 'Assign' }}" closeText="Cancel">
        @if(!$assignment)
            <input type="hidden" name="production_order_operation_id" value="{{ $op->id }}">
        @endif

        <x-ui.odoo-form-ui type="select" label="Select Operator" name="user_id" :required="true">
            <option value="">-- Choose Operator --</option>
            @foreach($operators as $operator)
                <option value="{{ $operator->id }}" {{ $assignment && $assignment->user_id == $operator->id ? 'selected' : '' }}>
                    {{ $operator->name }} ({{ ucfirst($operator->role) }})
                </option>
            @endforeach
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="textarea" label="Remarks / Instructions" name="remarks"
            placeholder="Specify instructions or skill requirements..." rows="3" />
    </x-ui.modal>

    {{-- Operator Shopfloor Quality Inspection Modal --}}
    @php
        $touchPendingQcQty = $pendingQcQty ?? app(\App\Domains\Production\Services\MesExecutionService::class)->getPendingQcQuantity($op->id);
        $touchRejectedQty = (float) ($op->quantity_rejected ?? 0.0);
    @endphp

    {{-- QD: QC is done on the operation's inspection page (QC CHECK button) --}}
    {{-- Unified Material Disposition Modal (Scrap, Offcut, Return Unused) --}}
    @include('modules.production.partials.material-disposition-modal', [
        'op' => $op,
        'order' => $order,
        'modalId' => 'materialDispositionModal',
        'warehouses' => $warehouses
    ])


    {{-- Rejected Quantity Disposition Modal --}}
    <x-ui.modal id="dispositionModal" title="REJECTED OUTPUT DISPOSITION — {{ html_entity_decode($op->name ?? 'Op #' . $op->sequence, ENT_QUOTES, 'UTF-8') }}" centered="true" size="lg"
        formAction="{{ route('production.mes.disposition', $op->id) }}" submitText="Submit Disposition" closeText="Cancel">
        <div class="bg-soft-danger p-3 rounded mb-3 border border-danger-subtle d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold text-danger mb-1"><i class="feather-alert-triangle me-2"></i>Quality Rejection Disposition</h6>
                <span class="fs-11 text-muted">Order: <strong>{{ $order->order_number }}</strong> | Item: <strong>{{ $op->sourceProduct->name ?? $order->product->name }}</strong></span>
            </div>
            <span class="badge bg-danger text-white fs-12 px-3 py-2 font-monospace">Rejected Qty: {{ number_format($touchRejectedQty, 0) }}</span>
        </div>

        @php
            $isOutsourcedOp = (bool) ($op->is_external || $op->isOutsourced() || ($op->work_center_id === null && !config('production.temp_flow')));
            $vendorName = $op->vendor->name ?? 'Subcontract Vendor';
        @endphp

        <div class="row g-3 text-start">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="Disposition Choice" name="disposition_type" :required="true">
                    <option value="rework" selected>Rework (Create Repair Order & Reprocess Defect)</option>
                    <option value="scrap">Scrap (Scrap Material & Evaluate Replacement)</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Quantity to Dispose" name="quantity" inputType="number" step="any" value="{{ $touchRejectedQty }}" :required="true" />
            </div>

            @if($isOutsourcedOp)
                <div class="col-md-12">
                    <label class="form-label fw-bold text-dark fs-11">Rework Location / Pathway <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" name="rework_location" id="reworkLocationOp" onchange="toggleReworkFieldsOp(this.value)">
                        <option value="vendor_rework" selected>Return to Subcontract Vendor (Generate Return Gate Pass / DC)</option>
                        <option value="internal_repair">Perform In-House Repair (Internal Workstation)</option>
                    </select>
                </div>

                <div id="vendorReworkBannerOp" class="col-md-12">
                    <div class="alert alert-info border border-info-subtle p-2.5 rounded fs-11 mb-0">
                        <i class="feather-truck me-1"></i>
                        <strong>Subcontract Vendor Rework</strong>: A Rework Delivery Challan (Return Gate Pass) will be created to dispatch <strong>{{ number_format($touchRejectedQty, 0) }} unit(s)</strong> back to <strong>{{ $vendorName }}</strong> for rework/rectification.
                    </div>
                </div>
            @endif

            <div class="col-md-12 p-0 m-0">
                <div id="internalReworkFieldsOp" class="row g-3 m-0" style="{{ $isOutsourcedOp ? 'display: none;' : '' }}">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="Target Workstation / Process (For Rework)" name="work_center_id">
                            @foreach($workCenters ?? [] as $wc)
                                <option value="{{ $wc->id }}" {{ ($op->work_center_id == $wc->id) ? 'selected' : '' }}>
                                    {{ $wc->name }} ({{ $wc->code }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="Target Machine / Equipment" name="machine_id">
                            <option value="">-- Default Workstation Machine --</option>
                            @foreach($machines ?? [] as $m)
                                <option value="{{ $m->id }}" {{ ($op->machine_id == $m->id) ? 'selected' : '' }}>
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
                <x-ui.odoo-form-ui type="input" :label="'Estimated Repair Cost (' . active_currency_symbol() . ')'" name="cost_estimate" inputType="number" step="0.01" value="50.00" />
            </div>

            <div class="col-md-12">
                <x-ui.odoo-form-ui type="textarea" label="Reason / Defect Description" name="reason"
                    placeholder="Explain defect cause (e.g. Surface Scratch, Dimension Out of Spec)..." rows="2" />
            </div>
            <div class="col-md-12">
                <x-ui.odoo-form-ui type="textarea" label="Detailed Rework Instructions for Vendor / Technician" name="instructions"
                    placeholder="Provide step-by-step repair instructions..." rows="2" />
            </div>
        </div>
        @if($isOutsourcedOp)
            <script>
                function toggleReworkFieldsOp(val) {
                    const internalDiv = document.getElementById('internalReworkFieldsOp');
                    const vendorBanner = document.getElementById('vendorReworkBannerOp');
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
    </x-ui.modal>

    @push('scripts')
        <script>
            let currentField = 'log_produced';
            let keypadReplaceNext = true;

            function selectInput(field, btnEl) {
                currentField = field;
                keypadReplaceNext = true;
                const modal = btnEl.closest('.modal');
                modal.querySelectorAll('.active-input-btn').forEach(btn => btn.classList.remove('active'));
                btnEl.classList.add('active');
            }

            function numPress(val) {
                let input = document.getElementById(currentField + 'Input');
                if (!input) return;

                if (val === 'C') {
                    input.value = '0';
                    keypadReplaceNext = true;
                    return;
                }

                if (keypadReplaceNext || input.value === '0') {
                    input.value = (val === '.' ? '0.' : val);
                    keypadReplaceNext = false;
                } else {
                    if (val === '.' && input.value.includes('.')) return;
                    input.value += val;
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                const logModal = document.getElementById('logProgressModal');
                if (logModal) {
                    logModal.addEventListener('shown.bs.modal', function () {
                        currentField = 'log_produced';
                        keypadReplaceNext = true;
                    });
                }


                // Live Operation Timer (HH:MM:SS format)
                const liveTimerEl = document.getElementById('opLiveTimer');
                if (liveTimerEl) {
                    function updateOpLiveTimer() {
                        const now = new Date();
                        const status = liveTimerEl.getAttribute('data-status');
                        const startVal = liveTimerEl.getAttribute('data-start');
                        if (!startVal) {
                            liveTimerEl.textContent = '00:00:00';
                            return;
                        }

                        const start = new Date(startVal);
                        let end;

                        if (status === 'running') {
                            end = now;
                        } else if (status === 'paused') {
                            const pausedAtVal = liveTimerEl.getAttribute('data-paused-at');
                            end = pausedAtVal ? new Date(pausedAtVal) : now;
                        } else {
                            liveTimerEl.textContent = '00:00:00';
                            return;
                        }

                        const diffSeconds = (end.getTime() - start.getTime()) / 1000;
                        const pausedSec = parseInt(liveTimerEl.getAttribute('data-accumulated-paused') || 0);
                        const activeSeconds = Math.max(0, diffSeconds - pausedSec);

                        const hrs = Math.floor(activeSeconds / 3600);
                        const mins = Math.floor((activeSeconds % 3600) / 60);
                        const secs = Math.floor(activeSeconds % 60);

                        const hrsStr = hrs.toString().padStart(2, '0');
                        const minsStr = mins.toString().padStart(2, '0');
                        const secsStr = secs.toString().padStart(2, '0');

                        liveTimerEl.textContent = `${hrsStr}:${minsStr}:${secsStr}`;
                    }

                    updateOpLiveTimer();
                    setInterval(updateOpLiveTimer, 1000);
                }

                // MES Tab URL Persistence & Form State Sync
                const mesTabsContainer = document.getElementById('mesOperatorTabs');
                if (mesTabsContainer) {
                    mesTabsContainer.querySelectorAll('button[data-bs-toggle="tab"]').forEach(function (tabBtn) {
                        tabBtn.addEventListener('shown.bs.tab', function (e) {
                            const targetId = e.target.getAttribute('data-bs-target')?.replace('#', '') || e.target.getAttribute('aria-controls');
                            if (targetId) {
                                const url = new URL(window.location.href);
                                url.searchParams.set('tab', targetId);
                                window.history.replaceState(null, '', url.toString());

                                // Attach tab parameter to form actions inside the activated tab content
                                const tabPane = document.getElementById(targetId);
                                if (tabPane) {
                                    tabPane.querySelectorAll('form').forEach(function (form) {
                                        try {
                                            const formUrl = new URL(form.action, window.location.origin);
                                            formUrl.searchParams.set('tab', targetId);
                                            form.action = formUrl.toString();
                                        } catch (err) { }
                                    });
                                }
                            }
                        });
                    });
                }

            });
        </script>
    @endpush
@endsection
