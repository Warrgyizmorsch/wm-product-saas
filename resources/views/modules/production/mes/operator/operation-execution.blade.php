@extends('layouts.duralux')

@section('title', 'MES Execute Operation | SaaS ERP')
@section('page-title', 'Execute Operation: ' . ($op->name ?? '—'))
@section('breadcrumb', 'Execute Operation')

@push('styles')
    <style>
        .num-btn {
            min-height: 60px;
            font-size: 20px;
            font-weight: bold;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-touch-large {
            min-height: 64px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .touch-tab {
            min-height: 48px;
            font-weight: 600;
        }
    </style>
@endpush

@section('page-actions')
    <x-ui.icon-btn href="{{ route('production.mes.operator.dashboard') }}" icon="feather-arrow-left" variant="transparent-dark" title="Dashboard">
        Dashboard
    </x-ui.icon-btn>
@endsection

@section('content')
<div class="erp-single-panel bg-white">

    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif
    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    {{-- Header Identity Row --}}
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div>
            <span class="badge bg-soft-secondary font-monospace mb-2">{{ $op->operation_number ?? 'OP-??' }}</span>
            <h3 class="fw-bold text-dark mb-1">{{ $op->name }}</h3>
            <p class="text-muted fs-14 mb-0">
                Order: <strong>{{ $order->order_number }}</strong> | Product: <strong>{{ $order->product->name }}</strong>
                | Mode: <span class="badge bg-soft-info">{{ strtoupper($order->production_mode) }}</span>
            </p>
        </div>
        <div class="text-end">
            <div class="fs-12 text-muted uppercase font-semibold mb-1">Status</div>
            @php
                $statusClass = match($op->status) {
                    'running' => 'bg-success text-white',
                    'paused' => 'bg-warning text-dark',
                    'completed' => 'bg-secondary text-white',
                    default => 'bg-primary text-white',
                };
            @endphp
            <span class="badge {{ $statusClass }} fs-14 px-3 py-2">{{ strtoupper($op->status) }}</span>
        </div>
    </div>

    {{-- Execution Controls --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border border-light shadow-none h-100">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h5 class="fw-bold text-dark mb-0">Touch Controls</h5>
                </div>
                <div class="card-body d-flex flex-column justify-content-center p-4">
                    <div class="row g-3">
                        @if($op->status !== 'running' && $op->status !== 'completed')
                            <div class="col-12">
                                <form method="POST" action="{{ route('production.mes.start', optional($scheduleOp)->id ?? $op->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-touch-large btn-success w-100">
                                        <i class="feather-play me-2 fs-20"></i> START OPERATION
                                    </button>
                                </form>
                            </div>
                        @endif

                        @if($op->status === 'running')
                            <div class="col-4">
                                <button class="btn btn-touch-large btn-warning w-100" data-bs-toggle="modal" data-bs-target="#pauseModal">
                                    <i class="feather-pause me-2 fs-18"></i> PAUSE
                                </button>
                            </div>
                            <div class="col-4">
                                <button class="btn btn-touch-large btn-info text-white w-100" data-bs-toggle="modal" data-bs-target="#logProgressModal">
                                    <i class="feather-edit-3 me-2 fs-18"></i> LOG PROGRESS
                                </button>
                            </div>
                            <div class="col-4">
                                <button class="btn btn-touch-large btn-primary w-100" data-bs-toggle="modal" data-bs-target="#completeModal">
                                    <i class="feather-check-circle me-2 fs-18"></i> COMPLETE
                                </button>
                            </div>
                        @endif

                        @if($op->status === 'paused')
                            <div class="col-12">
                                <form method="POST" action="{{ route('production.mes.resume', optional($scheduleOp)->id ?? $op->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-touch-large btn-success w-100">
                                        <i class="feather-play me-2 fs-20"></i> RESUME WORK
                                    </button>
                                </form>
                            </div>
                        @endif

                        @if($op->status === 'completed')
                            <div class="col-12 text-center py-4">
                                <i class="feather-check-circle text-success fs-48 mb-2"></i>
                                <h5 class="fw-bold">Operation is Completed</h5>
                                <p class="text-muted mb-0">This operation's steps on the shop floor have finished successfully.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Stats / Assignment info --}}
        <div class="col-lg-6">
            <div class="card border border-light shadow-none h-100">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h5 class="fw-bold text-dark mb-0">Operator Info & Instructions</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="text-muted d-block fs-12 uppercase font-semibold">Assigned Operator</span>
                        <div class="d-flex align-items-center mt-1">
                            <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle me-2">
                                <i class="feather-user"></i>
                            </div>
                            <span class="fw-bold text-dark me-3">
                                {{ $assignment ? $assignment->user->name : 'No active assignments' }}
                                @if($assignment)
                                    @if($assignment->status === 'assigned')
                                        <span class="badge bg-soft-warning text-warning fs-10 ms-1">Pending Acceptance</span>
                                    @elseif($assignment->status === 'accepted')
                                        <span class="badge bg-soft-success text-success fs-10 ms-1">Accepted</span>
                                    @endif
                                @endif
                            </span>
                            @can('manage', \App\Domains\Production\Models\ProductionOperatorAssignment::class)
                                <button type="button" class="btn btn-xs btn-outline-primary ms-auto" data-bs-toggle="modal" data-bs-target="#assignOperatorModal">
                                    <i class="feather-user-plus me-1"></i> {{ $assignment ? 'Reassign' : 'Assign' }}
                                </button>
                            @endcan
                        </div>
                    </div>
                    <div class="mb-3 pt-3 border-top">
                        <span class="text-muted d-block fs-12 uppercase font-semibold mb-1">Process Instructions</span>
                        <div class="bg-light p-3 rounded text-dark font-monospace fs-13" style="max-height: 120px; overflow-y: auto;">
                            {!! nl2br(e($op->instructions ?? 'No special process instructions provided for this step.')) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Dynamic Tabs based on Production Mode --}}
    @if($order->production_mode !== 'standard')
        <div class="card border border-light shadow-none">
            <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0">
                <ul class="nav nav-pills nav-fill bg-light p-1 rounded" id="mesTab" role="tablist">
                    @if($order->production_mode === 'batch' || $order->production_mode === 'batch_and_serial')
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active touch-tab" id="batch-tab" data-bs-toggle="tab" data-bs-target="#batch-content" type="button" role="tab">
                                <i class="feather-box me-2"></i> Batch Control Panel
                            </button>
                        </li>
                    @endif
                    @if($order->production_mode === 'serial' || $order->production_mode === 'batch_and_serial')
                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if($order->production_mode === 'serial') active @endif touch-tab" id="serial-tab" data-bs-toggle="tab" data-bs-target="#serial-content" type="button" role="tab">
                                <i class="feather-hash me-2"></i> Serial Numbers Manager
                            </button>
                        </li>
                    @endif
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="mesTabContent">
                    {{-- Batch Tab --}}
                    @if($order->production_mode === 'batch' || $order->production_mode === 'batch_and_serial')
                        <div class="tab-pane fade show active" id="batch-content" role="tabpanel">
                            @include('modules.production.mes.operator.batch-production')
                        </div>
                    @endif
                    {{-- Serial Tab --}}
                    @if($order->production_mode === 'serial' || $order->production_mode === 'batch_and_serial')
                        <div class="tab-pane fade @if($order->production_mode === 'serial') show active @endif" id="serial-content" role="tabpanel">
                            @include('modules.production.mes.operator.serial-production')
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

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
                    <button type="button" class="btn btn-sm btn-outline-primary active-input-btn active" onclick="selectInput('produced', this)">Produced</button>
                    <button type="button" class="btn btn-sm btn-outline-primary active-input-btn" onclick="selectInput('rejected', this)">Rejected</button>
                    <button type="button" class="btn btn-sm btn-outline-primary active-input-btn" onclick="selectInput('scrapped', this)">Scrapped</button>
                </div>
            </div>

            <div class="row g-2 mb-3">
                @for($i = 1; $i <= 9; $i++)
                    <div class="col-4">
                        <button type="button" class="btn btn-light num-btn w-100" onclick="numPress('{{ $i }}')">{{ $i }}</button>
                    </div>
                @endfor
                <div class="col-4">
                    <button type="button" class="btn btn-light num-btn w-100" onclick="numPress('.')">.</button>
                </div>
                <div class="col-4">
                    <button type="button" class="btn btn-light num-btn w-100" onclick="numPress('0')">0</button>
                </div>
                <div class="col-4">
                    <button type="button" class="btn btn-soft-danger num-btn w-100" onclick="numPress('C')"><i class="feather-delete"></i></button>
                </div>
            </div>
        </div>

        {{-- Target Quantities --}}
        <div class="col-md-5 ps-md-4">
            <div class="mb-3">
                <label class="form-label uppercase font-semibold fs-11 text-muted">Quantity Produced</label>
                <input type="number" step="0.0001" class="form-control form-control-lg fw-bold text-success text-center" id="producedInput" name="quantity_produced" value="{{ $order->quantity_ordered }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label uppercase font-semibold fs-11 text-muted">Quantity Rejected</label>
                <input type="number" step="0.0001" class="form-control form-control-lg fw-bold text-warning text-center" id="rejectedInput" name="quantity_rejected" value="0">
            </div>
            <div class="mb-3">
                <label class="form-label uppercase font-semibold fs-11 text-muted">Quantity Scrapped</label>
                <input type="number" step="0.0001" class="form-control form-control-lg fw-bold text-danger text-center" id="scrappedInput" name="quantity_scrapped" value="0">
            </div>
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
                    <button type="button" class="btn btn-sm btn-outline-primary active-input-btn active" onclick="selectInput('log_produced', this)">Produced</button>
                    <button type="button" class="btn btn-sm btn-outline-primary active-input-btn" onclick="selectInput('log_rejected', this)">Rejected</button>
                    <button type="button" class="btn btn-sm btn-outline-primary active-input-btn" onclick="selectInput('log_scrapped', this)">Scrapped</button>
                </div>
            </div>

            <div class="row g-2 mb-3">
                @for($i = 1; $i <= 9; $i++)
                    <div class="col-4">
                        <button type="button" class="btn btn-light num-btn w-100" onclick="numPress('{{ $i }}')">{{ $i }}</button>
                    </div>
                @endfor
                <div class="col-4">
                    <button type="button" class="btn btn-light num-btn w-100" onclick="numPress('.')">.</button>
                </div>
                <div class="col-4">
                    <button type="button" class="btn btn-light num-btn w-100" onclick="numPress('0')">0</button>
                </div>
                <div class="col-4">
                    <button type="button" class="btn btn-soft-danger num-btn w-100" onclick="numPress('C')"><i class="feather-delete"></i></button>
                </div>
            </div>
        </div>

        {{-- Target Quantities --}}
        <div class="col-md-5 ps-md-4">
            <div class="mb-3">
                <label class="form-label uppercase font-semibold fs-11 text-muted">Quantity Produced (Today)</label>
                <input type="number" step="0.0001" class="form-control form-control-lg fw-bold text-success text-center" id="log_producedInput" name="quantity_produced" value="0" required>
            </div>
            <div class="mb-3">
                <label class="form-label uppercase font-semibold fs-11 text-muted">Quantity Rejected (Today)</label>
                <input type="number" step="0.0001" class="form-control form-control-lg fw-bold text-warning text-center" id="log_rejectedInput" name="quantity_rejected" value="0">
            </div>
            <div class="mb-3">
                <label class="form-label uppercase font-semibold fs-11 text-muted">Quantity Scrapped (Today)</label>
                <input type="number" step="0.0001" class="form-control form-control-lg fw-bold text-danger text-center" id="log_scrappedInput" name="quantity_scrapped" value="0">
            </div>
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
        });
    </script>
@endpush
@endsection
