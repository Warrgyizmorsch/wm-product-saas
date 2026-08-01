@extends('layouts.duralux')

@section('title', 'MES Execute Operation | SaaS ERP')
@section('page-title', 'Execute Operation: ' . ($op->name ?? '—'))
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
        .btn-touch-large {
            min-height: 50px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
    </style>
@endpush

@section('page-actions')
    <x-ui.button href="{{ route('production.labels.orders.print', $order->id) }}" target="_blank" icon="feather-printer" variant="outline-dark" size="sm" class="me-2">
        {{ __('production.print_order_label') }}
    </x-ui.button>
    <x-ui.icon-btn href="{{ route('production.mes.operator.dashboard') }}" icon="feather-arrow-left" variant="transparent-dark" title="Dashboard">
        Dashboard
    </x-ui.icon-btn>
@endsection

@section('content')
<div class="erp-single-panel bg-white p-4">

    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif
    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    <x-ui.odoo-form-ui type="sheet">

        {{-- Header Identity Row --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <x-ui.badge variant="secondary" soft class="font-monospace mb-2">{{ $op->operation_number ?? 'OP-??' }}</x-ui.badge>
                <h3 class="fw-bold text-dark mb-1">{{ $op->name }}</h3>
                <p class="text-muted fs-13 mb-0">
                    Order: <strong class="text-dark">{{ $order->order_number }}</strong> | Product: <strong class="text-dark">{{ $order->product->name }}</strong>
                    | Mode: <x-ui.badge variant="info" soft class="font-monospace ms-1">{{ strtoupper($order->production_mode) }}</x-ui.badge>
                </p>
            </div>
            <div class="text-end">
                <div class="fs-11 text-muted uppercase font-semibold mb-1">{{ __('production.status') }}</div>
                @php
                    $statusVariant = match($op->status) {
                        'running' => 'success',
                        'paused' => 'warning',
                        'completed' => 'secondary',
                        default => 'primary',
                    };
                @endphp
                <x-ui.badge :variant="$statusVariant" class="fs-13 px-3 py-2 fw-bold">{{ strtoupper($op->status) }}</x-ui.badge>
            </div>
        </div>

        {{-- Execution Controls --}}
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <x-ui.card :title="__('production.touch_controls')" class="h-100 border">
                    <div class="d-flex flex-column justify-content-center p-2">
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

                        <div class="row g-3">
                            @if($op->status !== 'running' && $op->status !== 'paused' && $op->status !== 'completed')
                                <div class="col-12">
                                    <form method="POST" action="{{ route('production.mes.start', optional($scheduleOp)->id ?? $op->id) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="success" icon="feather-play" class="btn-touch-large w-100">
                                            START OPERATION
                                        </x-ui.button>
                                    </form>
                                </div>
                            @endif

                            @if($op->status === 'running')
                                <div class="col-4">
                                    <x-ui.button variant="warning" icon="feather-pause" class="btn-touch-large w-100" data-bs-toggle="modal" data-bs-target="#pauseModal">
                                        PAUSE
                                    </x-ui.button>
                                </div>
                            @endif

                            @if($op->status === 'paused')
                                <div class="col-4">
                                    <form method="POST" action="{{ route('production.mes.resume', optional($scheduleOp)->id ?? $op->id) }}" class="w-100">
                                        @csrf
                                        <x-ui.button type="submit" variant="success" icon="feather-play" class="btn-touch-large w-100">
                                            RESUME
                                        </x-ui.button>
                                    </form>
                                </div>
                            @endif

                            @if($op->status === 'running' || $op->status === 'paused')
                                <div class="col-4">
                                    <x-ui.button variant="info" icon="feather-edit-3" class="btn-touch-large w-100 text-white" data-bs-toggle="modal" data-bs-target="#logProgressModal">
                                        LOG PROGRESS
                                    </x-ui.button>
                                </div>
                                <div class="col-4">
                                    <x-ui.button variant="primary" icon="feather-check-circle" class="btn-touch-large w-100" data-bs-toggle="modal" data-bs-target="#completeModal">
                                        COMPLETE
                                    </x-ui.button>
                                </div>
                            @endif

                            @if($op->status === 'completed')
                                <div class="col-12 text-center py-4">
                                    <i class="feather-check-circle text-success fs-48 mb-2 d-block"></i>
                                    <h5 class="fw-bold text-dark">Operation is Completed</h5>
                                    <p class="text-muted mb-0 fs-13">This operation's steps on the shop floor have finished successfully.</p>
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
                                <x-ui.button variant="outline-primary" size="sm" icon="feather-user-plus me-1" class="ms-auto" data-bs-toggle="modal" data-bs-target="#assignOperatorModal">
                                    {{ $assignment ? 'Reassign' : 'Assign' }}
                                </x-ui.button>
                            @endcan
                        </div>
                    </div>
                    <div class="mb-2 pt-3 border-top">
                        <span class="text-muted d-block fs-11 uppercase font-semibold mb-1">Process Instructions</span>
                        <div class="bg-light p-3 rounded text-dark font-monospace fs-13 border" style="max-height: 120px; overflow-y: auto;">
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
                        <div class="tab-pane fade @if($activeMesTab === 'batch-content') show active @endif" id="batch-content" role="tabpanel" aria-labelledby="batch-content-tab">
                            @include('modules.production.mes.operator.batch-production')
                        </div>
                    @endif
                    {{-- Serial Tab --}}
                    @if($order->production_mode === 'serial' || $order->production_mode === 'batch_and_serial')
                        <div class="tab-pane fade @if($activeMesTab === 'serial-content') show active @endif" id="serial-content" role="tabpanel" aria-labelledby="serial-content-tab">
                            @include('modules.production.mes.operator.serial-production')
                        </div>
                    @endif
                </div>
            </div>
        @endif

    </x-ui.odoo-form-ui>

</div>{{-- end .erp-single-panel --}}

{{-- Pause Modal --}}
<x-ui.modal 
    id="pauseModal" 
    title="Pause Operation" 
    centered="true"
    formAction="{{ route('production.mes.pause', optional($scheduleOp)->id ?? $op->id) }}"
    submitText="Pause Operation"
    closeText="Cancel"
>
    <x-ui.odoo-form-ui 
        type="textarea" 
        label="Reason for Pause / Remarks" 
        name="remarks" 
        placeholder="Enter reason (e.g. material shortage, machine breakdown)..." 
        :required="true" 
        rows="3"
    />
</x-ui.modal>

{{-- Complete Modal (Touch Numeric Pad) --}}
<x-ui.modal 
    id="completeModal" 
    title="Log Progress & Complete" 
    centered="true"
    size="lg"
    formAction="{{ route('production.mes.complete', optional($scheduleOp)->id ?? $op->id) }}"
    submitText="Submit & Complete"
    closeText="Cancel"
>
    <div class="row g-4 text-start">
        {{-- Numeric Pad & Inputs --}}
        <div class="col-md-7 border-end pe-md-4">
            <div class="mb-3">
                <label class="form-label uppercase font-semibold fs-11 text-muted">Active Input field</label>
                <div class="d-flex gap-2">
                    <x-ui.button type="button" variant="outline-primary" size="sm" class="active-input-btn active" onclick="selectInput('produced', this)">Produced</x-ui.button>
                    <x-ui.button type="button" variant="outline-primary" size="sm" class="active-input-btn" onclick="selectInput('rejected', this)">Rejected</x-ui.button>
                    <x-ui.button type="button" variant="outline-primary" size="sm" class="active-input-btn" onclick="selectInput('scrapped', this)">Scrapped</x-ui.button>
                </div>
            </div>

            <div class="row g-2 mb-3">
                @for($i = 1; $i <= 9; $i++)
                    <div class="col-4">
                        <x-ui.button type="button" variant="light" class="num-btn w-100" onclick="numPress('{{ $i }}')">{{ $i }}</x-ui.button>
                    </div>
                @endfor
                <div class="col-4">
                    <x-ui.button type="button" variant="light" class="num-btn w-100" onclick="numPress('.')">.</x-ui.button>
                </div>
                <div class="col-4">
                    <x-ui.button type="button" variant="light" class="num-btn w-100" onclick="numPress('0')">0</x-ui.button>
                </div>
                <div class="col-4">
                    <x-ui.button type="button" variant="soft-danger" class="num-btn w-100" onclick="numPress('C')"><i class="feather-delete"></i></x-ui.button>
                </div>
            </div>
        </div>

        {{-- Target Quantities --}}
        <div class="col-md-5 ps-md-4">
            <x-ui.odoo-form-ui 
                type="input" 
                label="Quantity Produced" 
                name="quantity_produced" 
                id="producedInput" 
                inputType="number" 
                step="0.0001"
                :value="max(0, $order->quantity_ordered - ($op->quantity_produced ?? 0))" 
                :required="true" 
            />
            <x-ui.odoo-form-ui 
                type="input" 
                label="Quantity Rejected" 
                name="quantity_rejected" 
                id="rejectedInput" 
                inputType="number" 
                step="0.0001"
                value="0" 
            />
            <x-ui.odoo-form-ui 
                type="input" 
                label="Quantity Scrapped" 
                name="quantity_scrapped" 
                id="scrappedInput" 
                inputType="number" 
                step="0.0001"
                value="0" 
            />
            <x-ui.odoo-form-ui 
                type="textarea" 
                label="Remarks" 
                name="remarks" 
                placeholder="Optional comments..." 
                rows="2"
            />
        </div>
    </div>
</x-ui.modal>

{{-- Log Daily/Partial Progress Modal (Touch Numeric Pad) --}}
<x-ui.modal 
    id="logProgressModal" 
    title="Log Daily / Shift Progress" 
    centered="true"
    size="lg"
    formAction="{{ route('production.mes.log-progress', optional($scheduleOp)->id ?? $op->id) }}"
    submitText="Submit Progress Log"
    closeText="Cancel"
>
    <div class="row g-4 text-start">
        {{-- Numeric Pad & Inputs --}}
        <div class="col-md-7 border-end pe-md-4">
            <div class="mb-3">
                <label class="form-label uppercase font-semibold fs-11 text-muted">Active Input field</label>
                <div class="d-flex gap-2">
                    <x-ui.button type="button" variant="outline-primary" size="sm" class="active-input-btn active" onclick="selectInput('log_produced', this)">Produced</x-ui.button>
                    <x-ui.button type="button" variant="outline-primary" size="sm" class="active-input-btn" onclick="selectInput('log_rejected', this)">Rejected</x-ui.button>
                    <x-ui.button type="button" variant="outline-primary" size="sm" class="active-input-btn" onclick="selectInput('log_scrapped', this)">Scrapped</x-ui.button>
                </div>
            </div>

            <div class="row g-2 mb-3">
                @for($i = 1; $i <= 9; $i++)
                    <div class="col-4">
                        <x-ui.button type="button" variant="light" class="num-btn w-100" onclick="numPress('{{ $i }}')">{{ $i }}</x-ui.button>
                    </div>
                @endfor
                <div class="col-4">
                    <x-ui.button type="button" variant="light" class="num-btn w-100" onclick="numPress('.')">.</x-ui.button>
                </div>
                <div class="col-4">
                    <x-ui.button type="button" variant="light" class="num-btn w-100" onclick="numPress('0')">0</x-ui.button>
                </div>
                <div class="col-4">
                    <x-ui.button type="button" variant="soft-danger" class="num-btn w-100" onclick="numPress('C')"><i class="feather-delete"></i></x-ui.button>
                </div>
            </div>
        </div>

        {{-- Target Quantities --}}
        <div class="col-md-5 ps-md-4">
            @if(!empty($batchQueue['active']))
                <x-ui.odoo-form-ui type="select" label="Production Batch" name="production_batch_id">
                    @foreach($batchQueue['active'] as $item)
                        <option value="{{ $item['batch']->id }}">
                            Batch #{{ $item['batch']->batch_number }} (Remaining: {{ number_format($item['remaining_to_process'], 2) }})
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            @endif
            <x-ui.odoo-form-ui 
                type="input" 
                label="Quantity Produced (Today)" 
                name="quantity_produced" 
                id="log_producedInput" 
                inputType="number" 
                step="0.0001"
                value="0" 
                :required="true" 
            />
            <x-ui.odoo-form-ui 
                type="input" 
                label="Quantity Rejected (Today)" 
                name="quantity_rejected" 
                id="log_rejectedInput" 
                inputType="number" 
                step="0.0001"
                value="0" 
            />
            <x-ui.odoo-form-ui 
                type="input" 
                label="Quantity Scrapped (Today)" 
                name="quantity_scrapped" 
                id="log_scrappedInput" 
                inputType="number" 
                step="0.0001"
                value="0" 
            />
            <x-ui.odoo-form-ui 
                type="textarea" 
                label="Remarks" 
                name="remarks" 
                placeholder="Optional shift handover comments..." 
                rows="2"
            />
        </div>
    </div>
</x-ui.modal>

{{-- Assign/Reassign Operator Modal --}}
<x-ui.modal 
    id="assignOperatorModal" 
    title="{{ $assignment ? 'Reassign Operator' : 'Assign Operator' }}"
    centered="true"
    formAction="{{ $assignment ? route('production.mes.assignments.reassign', $assignment->id) : route('production.mes.assignments.assign') }}"
    submitText="{{ $assignment ? 'Reassign' : 'Assign' }}"
    closeText="Cancel"
>
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

    <x-ui.odoo-form-ui 
        type="textarea" 
        label="Remarks / Instructions" 
        name="remarks" 
        placeholder="Specify instructions or skill requirements..." 
        rows="3"
    />
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
                                    } catch (err) {}
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
