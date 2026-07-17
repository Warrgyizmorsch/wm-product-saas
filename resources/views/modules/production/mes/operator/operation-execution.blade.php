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
    <a href="{{ route('production.mes.operator.dashboard') }}" class="btn btn-light border px-3">
        <i class="feather-arrow-left me-2"></i> Dashboard
    </a>
@endsection

@section('content')
    <div class="container-fluid py-2">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Top Info Bar --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <span class="badge bg-soft-secondary font-monospace mb-2">{{ $op->operation_number ?? 'OP-??' }}</span>
                    <h3 class="fw-bold text-dark mb-1">{{ $op->name }}</h3>
                    <p class="text-muted fs-14 mb-0">
                        Order: <strong>{{ $order->order_number }}</strong> | Product: <strong>{{ $order->product->name }}</strong>
                        | Mode: <span class="badge bg-soft-info">{{ strtoupper($order->production_mode) }}</span>
                    </p>
                </div>
                <div class="mt-3 mt-md-0 text-md-end">
                    <div class="fs-12 text-muted uppercase font-semibold">Status</div>
                    @php
                        $statusClass = match($op->status) {
                            'running' => 'bg-success text-white',
                            'paused' => 'bg-warning text-dark',
                            'completed' => 'bg-secondary text-white',
                            default => 'bg-primary text-white',
                        };
                    @endphp
                    <span class="badge {{ $statusClass }} fs-14 px-3 py-2 mt-1">{{ strtoupper($op->status) }}</span>
                </div>
            </div>
        </div>

        {{-- Execution Controls --}}
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
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
                                <div class="col-6">
                                    <button class="btn btn-touch-large btn-warning w-100" data-bs-toggle="modal" data-bs-target="#pauseModal">
                                        <i class="feather-pause me-2 fs-18"></i> PAUSE
                                    </button>
                                </div>
                                <div class="col-6">
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
                <div class="card border-0 shadow-sm h-100">
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
            <div class="card border-0 shadow-sm">
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
    </div>

    {{-- Pause Modal --}}
    <div class="modal fade" id="pauseModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('production.mes.pause', optional($scheduleOp)->id ?? $op->id) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Pause Operation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label uppercase font-semibold fs-11 text-muted">Reason for Pause / Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3" placeholder="Enter reason (e.g. material shortage, machine breakdown)..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning px-4">Pause Operation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Complete Modal (Touch Numeric Pad) --}}
    <div class="modal fade" id="completeModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('production.mes.complete', optional($scheduleOp)->id ?? $op->id) }}" id="completeForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Log Progress & Complete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-4">
                            {{-- Numeric Pad & Inputs --}}
                            <div class="col-md-7 border-end pe-md-4">
                                <div class="mb-3">
                                    <label class="form-label uppercase font-semibold fs-11 text-muted">Active Input field</label>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary active-input-btn active" onclick="selectInput('produced')">Produced</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary active-input-btn" onclick="selectInput('rejected')">Rejected</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary active-input-btn" onclick="selectInput('scrapped')">Scrapped</button>
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
                                    <input type="number" step="0.0001" class="form-control form-control-lg fw-bold text-success text-center" id="producedInput" name="produced" value="{{ $order->quantity_ordered }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label uppercase font-semibold fs-11 text-muted">Quantity Rejected</label>
                                    <input type="number" step="0.0001" class="form-control form-control-lg fw-bold text-warning text-center" id="rejectedInput" name="rejected" value="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label uppercase font-semibold fs-11 text-muted">Quantity Scrapped</label>
                                    <input type="number" step="0.0001" class="form-control form-control-lg fw-bold text-danger text-center" id="scrappedInput" name="scrapped" value="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label uppercase font-semibold fs-11 text-muted">Remarks</label>
                                    <textarea name="remarks" class="form-control" rows="2" placeholder="Optional comments..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success px-4">Submit & Complete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

        <div class="mb-3 text-start text-dark">
            <label class="form-label uppercase font-semibold fs-11 text-muted">Select Operator</label>
            <select name="user_id" class="form-select form-control" required>
                <option value="">-- Choose Operator --</option>
                @foreach($operators as $operator)
                    <option value="{{ $operator->id }}" {{ $assignment && $assignment->user_id == $operator->id ? 'selected' : '' }}>
                        {{ $operator->name }} ({{ ucfirst($operator->role) }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3 text-start text-dark">
            <label class="form-label uppercase font-semibold fs-11 text-muted">Remarks / Instructions</label>
            <textarea name="remarks" class="form-control" rows="3" placeholder="Specify instructions or skill requirements..."></textarea>
        </div>
    </x-ui.modal>

    @push('scripts')
        <script>
            let currentField = 'produced';

            function selectInput(field) {
                currentField = field;
                document.querySelectorAll('.active-input-btn').forEach(btn => btn.classList.remove('active'));
                event.target.classList.add('active');
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
        </script>
    @endpush
@endsection
