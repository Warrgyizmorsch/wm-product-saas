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
        variant="outline-dark" size="sm" class="me-2">
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

    <x-ui.workflow-guide title="What's Next?">
        @if($op->status !== 'running' && $op->status !== 'paused' && $op->status !== 'completed')
            Click <span class="badge bg-soft-success text-success border border-success-subtle fw-semibold">START
                OPERATION</span> below to begin shop floor execution.
        @elseif($op->status === 'completed')
            @if($isFinalOp)
                Operation complete. This was the final routing operation for Order <strong
                    class="text-dark">{{ $order->order_number }}</strong>. Finished goods production can now be transferred into the
                warehouse from the <a href="{{ url('production/wip') }}?search={{ $order->order_number }}"
                    class="fw-bold text-primary text-decoration-underline">WIP Tracking Page</a>.
            @else
                Operation complete. The WIP batch and completed output have transitioned to the next routing operation.
            @endif
        @else
            @if(strtolower($order->production_mode ?? '') === 'batch')
                Create or select the required production batch below and log progress for that batch. Any rejected or scrapped
                quantities will automatically move under Quality Control (<a href="{{ url('production/quality/rework') }}"
                    class="fw-bold text-primary text-decoration-underline">Rework Management</a> & <a
                    href="{{ url('production/quality/scrap') }}" class="fw-bold text-primary text-decoration-underline">Scrap
                    Management</a>). Once completed with rework/scrap decomposition, the WIP batch will transition to the next
                operation.
            @elseif(strtolower($order->production_mode ?? '') === 'serial')
                Scan or select serial numbers below to log progress. Any rejected or scrapped units will automatically move under
                Quality Control (<a href="{{ url('production/quality/rework') }}"
                    class="fw-bold text-primary text-decoration-underline">Rework Management</a> & <a
                    href="{{ url('production/quality/scrap') }}" class="fw-bold text-primary text-decoration-underline">Scrap
                    Management</a>).
            @else
                Log completed output or progress below. Any rejected or scrapped quantities will automatically move under Quality
                Control (<a href="{{ url('production/quality/rework') }}"
                    class="fw-bold text-primary text-decoration-underline">Rework Management</a> & <a
                    href="{{ url('production/quality/scrap') }}" class="fw-bold text-primary text-decoration-underline">Scrap
                    Management</a>).
            @endif

            @if($isFinalOp)
                <div class="mt-1.5 fs-12 text-dark">
                    <i class="feather-check-circle me-1 text-primary"></i><strong>Final Operation Note:</strong> Upon completing
                    this final routing operation, finished goods production can be moved from WIP into the warehouse directly from
                    the <a href="{{ url('production/wip') }}?search={{ $order->order_number }}"
                        class="fw-bold text-primary text-decoration-underline">Work-in-Progress (WIP) Tracking Page</a> for Order
                    <strong>{{ $order->order_number }}</strong>.
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
                    @endphp
                    <x-ui.badge :variant="$statusVariant"
                        class="fs-13 px-3 py-2 fw-bold">{{ strtoupper($op->status) }}</x-ui.badge>
                </div>
            </div>

            {{-- F-04: Multi-Level Read-Only Readiness Panel --}}
            @if($op->predecessorDependencies->isNotEmpty() || $op->is_intermediate)
                <div class="card border mb-4 bg-light shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-dark mb-0"><i class="feather-shield text-primary me-2"></i>Multi-Level Component Execution Readiness</h6>
                            @if($readiness['is_ready'])
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
                                <span class="fs-11 text-muted text-uppercase d-block">Already Claimed</span>
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

                    $poItem = $op->purchaseOrderItem ?? \App\Domains\Purchase\Models\PurchaseOrderItem::where('production_order_operation_id', $op->id)->first();
                    $po = $poItem?->purchaseOrder ?? $op->purchaseOrder;
                    $prItem = \App\Domains\Purchase\Models\PurchaseRequisitionItem::whereHas('requisition', function($q) use ($order) {
                        $q->where('source_type', 'ProductionOrder')->where('source_id', $order->id);
                    })->first();
                    $pr = $prItem?->requisition;

                    $opWips = $order->wips->where('current_routing_operation_id', $op->id);
                    $sentQty = $op->quantity_transferred_out ?? 0;
                    $atVendorQty = $opWips->sum('quantity_available');
                    $receivedQty = $op->quantity_produced ?? 0;
                    $rejectedQty = $op->quantity_rejected ?? 0;
                    $scrappedQty = $op->quantity_scrapped ?? 0;
                    $qcPending = $order->wips->where('current_routing_operation_id', $op->id)->where('status', 'quality_hold')->sum('quantity_available');

                    $plannedDispatch = $order->start_date ? \Carbon\Carbon::parse($order->start_date)->subDays($op->dispatch_buffer_days ?? 0)->format('d/m/Y') : '—';
                    $actualDispatch = $op->actual_start_time ? $op->actual_start_time->format('d/m/Y H:i') : '—';
                    $expectedReturn = $order->start_date ? \Carbon\Carbon::parse($order->start_date)->addDays(($op->subcontract_lead_time_days ?? 0) + ($op->return_buffer_days ?? 0))->format('d/m/Y') : '—';
                    $actualReceipt = $op->actual_end_time ? $op->actual_end_time->format('d/m/Y H:i') : '—';
                @endphp

                <div class="alert alert-warning border border-warning bg-soft-warning p-4 rounded-3 mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-warning-subtle pb-3">
                        <div class="d-flex align-items-center">
                            <i class="feather-external-link text-warning fs-24 me-3"></i>
                            <div>
                                <h5 class="fw-bold text-dark mb-0">External Subcontracted Operation</h5>
                                <small class="text-dark">This operation is executed off-site by vendor <strong>{{ $op->vendor->name ?? 'Subcontractor' }}</strong>. Shop-floor machine execution is disabled; execution is tracked via Procurement & GRN.</small>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            @if($canPurchase && $po && \Illuminate\Support\Facades\Route::has('purchase.orders.show'))
                                <a href="{{ route('purchase.orders.show', $po->id) }}" class="btn btn-sm btn-outline-dark fw-semibold">
                                    <i class="feather-shopping-cart me-1"></i>View PO
                                </a>
                            @endif
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
                            <div class="text-muted fs-11 mt-0.5">Supply: {{ ucwords(str_replace('_', ' ', $op->material_supply_type ?? 'company_supplied')) }}</div>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted text-uppercase fs-10 fw-bold d-block">Procurement Status</span>
                            <div>PR: <strong>{{ $pr ? $pr->requisition_number . ' (' . ucfirst($pr->status) . ')' : 'Awaiting PR' }}</strong></div>
                            <div>PO: <strong>{{ $po ? $po->po_number . ' (' . ucfirst($po->status) . ')' : 'Awaiting PO' }}</strong></div>
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
                                    <div class="col-12">
                                        <form method="POST"
                                            action="{{ route('production.mes.start', optional($scheduleOp)->id ?? $op->id) }}">
                                            @csrf
                                            <x-ui.button type="submit" variant="success" icon="feather-play"
                                                class="btn-touch-large w-100">
                                                START OPERATION
                                            </x-ui.button>
                                        </form>
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
                                        $isQcRequired = (bool) ($op->routingOperation?->quality_required ?? $op->quality_required ?? false);
                                    @endphp

                                    <div class="col">
                                        <x-ui.button variant="info" icon="feather-edit-3"
                                            class="btn-touch-large w-100 text-white" data-bs-toggle="modal"
                                            data-bs-target="#logProgressModal">
                                            LOG PROGRESS
                                        </x-ui.button>
                                    </div>

                                    @if($isQcRequired)
                                        <div class="col">
                                            <x-ui.button variant="warning" icon="feather-shield-check"
                                                class="btn-touch-large w-100 text-dark" data-bs-toggle="modal"
                                                data-bs-target="#quickQcModal">
                                                QC CHECK
                                            </x-ui.button>
                                        </div>
                                    @endif

                                    <div class="col">
                                        <x-ui.button variant="primary" icon="feather-check-circle" class="btn-touch-large w-100"
                                            data-bs-toggle="modal" data-bs-target="#completeModal">
                                            COMPLETE
                                        </x-ui.button>
                                    </div>
                                @endif

                                @if($op->status === 'completed')
                                    <div class="col-12 text-center py-4">
                                        <i class="feather-check-circle text-success fs-48 mb-2 d-block"></i>
                                        <h5 class="fw-bold text-dark">Operation is Completed</h5>
                                        <p class="text-muted mb-0 fs-13">This operation's steps on the shop floor have finished
                                            successfully.</p>
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
                                    <x-ui.button variant="outline-primary" size="sm" icon="feather-user-plus me-1"
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

    {{-- Complete Modal (Touch Numeric Pad) --}}
    <x-ui.modal id="completeModal" title="Log Progress & Complete" centered="true" size="lg"
        formAction="{{ route('production.mes.complete', optional($scheduleOp)->id ?? $op->id) }}"
        submitText="Submit & Complete" closeText="Cancel">
        <div class="row g-4 text-start">
            {{-- Numeric Pad & Inputs --}}
            <div class="col-md-7 border-end pe-md-4">
                <div class="mb-3">
                    <label class="form-label uppercase font-semibold fs-11 text-muted">Active Input field</label>
                    <div class="d-flex gap-2">
                        <x-ui.button type="button" variant="outline-primary" size="sm" class="active-input-btn active"
                            onclick="selectInput('produced', this)">Produced</x-ui.button>
                        <x-ui.button type="button" variant="outline-primary" size="sm" class="active-input-btn"
                            onclick="selectInput('rejected', this)">Rejected</x-ui.button>
                        <x-ui.button type="button" variant="outline-primary" size="sm" class="active-input-btn"
                            onclick="selectInput('scrapped', this)">Scrapped</x-ui.button>
                    </div>
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
                @php
                    $opTargetQty = 0.0;
                    if ((float) ($op->target_produced_qty ?? 0) > 0) {
                        $opTargetQty = (float) $op->target_produced_qty;
                    } elseif ($op->source_product_id && (int) $op->source_product_id !== (int) $order->product_id) {
                        $bomItem = \App\Domains\Production\Models\ProductionBomItem::where('tenant_id', $op->tenant_id)
                            ->where('bom_id', $order->bom_id)
                            ->where('material_id', $op->source_product_id)
                            ->first();
                        $ratio = ($bomItem && (float) $bomItem->quantity > 0) ? (float) $bomItem->quantity : 1.0;
                        $opTargetQty = (float) $order->quantity_ordered * $ratio;
                    } else {
                        $opTargetQty = (float) $order->quantity_ordered;
                    }

                    if (isset($batchQueue['active']) && !empty($batchQueue['active'])) {
                        $activeBatch = reset($batchQueue['active']);
                        if ($activeBatch && (float) ($activeBatch['planned_quantity'] ?? 0) > 0) {
                            $opTargetQty = min($opTargetQty, (float) $activeBatch['planned_quantity']);
                        }
                    }

                    $totalScrappedAtOp = max(
                        (float) ($op->quantity_scrapped ?? 0),
                        (float) \App\Domains\Production\Models\ProductionOrderScrap::where('tenant_id', $op->tenant_id)
                            ->where('production_order_id', $op->production_order_id)
                            ->where('production_order_operation_id', $op->id)
                            ->sum('quantity')
                    );
                    $remainingToComplete = max(0.0, $opTargetQty - (($op->quantity_produced ?? 0) + $totalScrappedAtOp + ($op->quantity_rejected ?? 0)));
                @endphp
                <x-ui.odoo-form-ui type="input" label="Quantity Produced" name="quantity_produced" id="producedInput"
                    inputType="number" step="0.0001" :value="$remainingToComplete" :required="true" />
                <x-ui.odoo-form-ui type="input" label="Quantity Rejected" name="quantity_rejected" id="rejectedInput"
                    inputType="number" step="0.0001" value="0" />
                <x-ui.odoo-form-ui type="input" label="Quantity Scrapped" name="quantity_scrapped" id="scrappedInput"
                    inputType="number" step="0.0001" value="0" />
                <x-ui.odoo-form-ui type="textarea" label="Remarks" name="remarks" placeholder="Optional comments..."
                    rows="2" />
            </div>
        </div>
    </x-ui.modal>

    {{-- Log Daily/Partial Progress Modal (Touch Numeric Pad) --}}
    <x-ui.modal id="logProgressModal" title="Log Daily / Shift Progress" centered="true" size="lg"
        formAction="{{ route('production.mes.log-progress', optional($scheduleOp)->id ?? $op->id) }}"
        submitText="Submit Progress Log" closeText="Cancel">
        <div class="row g-4 text-start">
            {{-- Numeric Pad & Inputs --}}
            <div class="col-md-7 border-end pe-md-4">
                <div class="mb-3">
                    <label class="form-label uppercase font-semibold fs-11 text-muted">Active Input field</label>
                    <div class="d-flex gap-2">
                        <x-ui.button type="button" variant="outline-primary" size="sm" class="active-input-btn active"
                            onclick="selectInput('log_produced', this)">Produced</x-ui.button>
                        <x-ui.button type="button" variant="outline-primary" size="sm" class="active-input-btn"
                            onclick="selectInput('log_rejected', this)">Rejected</x-ui.button>
                        <x-ui.button type="button" variant="outline-primary" size="sm" class="active-input-btn"
                            onclick="selectInput('log_scrapped', this)">Scrapped</x-ui.button>
                    </div>
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
                <x-ui.odoo-form-ui type="input" label="Quantity Produced (Today)" name="quantity_produced"
                    id="log_producedInput" inputType="number" step="0.0001" value="0" :required="true" />
                <x-ui.odoo-form-ui type="input" label="Quantity Rejected (Today)" name="quantity_rejected"
                    id="log_rejectedInput" inputType="number" step="0.0001" value="0" />
                <x-ui.odoo-form-ui type="input" label="Quantity Scrapped (Today)" name="quantity_scrapped"
                    id="log_scrappedInput" inputType="number" step="0.0001" value="0" />
                <x-ui.odoo-form-ui type="textarea" label="Remarks" name="remarks"
                    placeholder="Optional shift handover comments..." rows="2" />
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

    {{-- Operator Quick Quality Check Modal --}}
    <x-ui.modal id="quickQcModal" title="Operator Quality Inspection Check" centered="true"
        formAction="{{ route('production.quality.inspections.quick') }}" submitText="Submit & Approve Quality Inspection"
        closeText="Cancel">
        <input type="hidden" name="production_order_operation_id" value="{{ $op->id }}">
        <input type="hidden" name="production_order_id" value="{{ $order->id }}">
        <input type="hidden" name="stage" value="in_process">

        <div class="alert alert-info py-2 fs-12 mb-3">
            <i class="feather-shield me-1"></i> Perform inline quality inspection for <strong>{{ $op->name }}</strong>
            before completing.
        </div>

        <x-ui.odoo-form-ui type="select" label="Inspection Result" name="result" :required="true">
            <option value="passed">PASSED — Meets Quality Standard</option>
            <option value="hold">QUALITY HOLD — Requires QA Review</option>
            <option value="failed">FAILED — Defective / NCR Generated</option>
        </x-ui.odoo-form-ui>

        <div class="mb-3">
            <label class="form-label fw-semibold fs-12 text-uppercase text-dark mb-1">Quality Checklist Parameters</label>
            <div class="p-2 border rounded bg-light fs-12">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" checked id="chkVisual">
                    <label class="form-check-label" for="chkVisual">Visual Surface & Finish Inspection Passed</label>
                </div>
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" checked id="chkDim">
                    <label class="form-check-label" for="chkDim">Dimensional Tolerance Check Within Specification</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" checked id="chkFunc">
                    <label class="form-check-label" for="chkFunc">Functional / Assembly Test Passed</label>
                </div>
            </div>
        </div>

        <x-ui.odoo-form-ui type="textarea" label="Inspection Remarks" name="remarks"
            placeholder="Enter measured dimensions, batch details, or quality observations..." rows="3" />
    </x-ui.modal>

    @push('scripts')
        <script>
            let currentField = 'produced';

            function selectInput(field, btnEl) {
                currentField = field;
                const modal = btnEl.closest('.modal');
                modal.querySelectorAll('.active-input-btn').forEach(btn => btn.classList.remove('active'));
                btnEl.classList.add('active');
            }

            function numPress(val) {
                let input = document.getElementById(currentField + 'Input');
                if (!input) return;

                if (val === 'C') {
                    input.value = '0';
                    return;
                }

                if (input.value === '0') {
                    input.value = val;
                } else {
                    input.value += val;
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                const logModal = document.getElementById('logProgressModal');
                if (logModal) {
                    logModal.addEventListener('shown.bs.modal', function () {
                        currentField = 'log_produced';
                    });
                }
                const compModal = document.getElementById('completeModal');
                if (compModal) {
                    compModal.addEventListener('shown.bs.modal', function () {
                        currentField = 'produced';
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