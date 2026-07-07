@extends('layouts.duralux')

@section('title', 'Machine Detail | SaaS ERP')
@section('page-title', $machine->name)
@section('breadcrumb', 'Machine Detail')

@section('page-actions')
    <a href="{{ route('production.mes.machines.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>All Machines
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="danger" title="{{ session('error') }}" />
        @endif

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center gap-3">
                @php
                    $avatarColor = match($machine->current_state) {
                        'Running' => 'bg-soft-success text-success',
                        'Breakdown' => 'bg-soft-danger text-danger',
                        'Setup' => 'bg-soft-info text-info',
                        'Waiting Material', 'Waiting Operator' => 'bg-soft-warning text-warning',
                        'Maintenance' => 'bg-soft-primary text-primary',
                        default => 'bg-soft-secondary text-secondary',
                    };
                @endphp
                <div class="avatar-text avatar-xl {{ $avatarColor }} rounded">
                    <i class="feather-cpu fs-22"></i>
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-0">{{ $machine->name }}</h4>
                    <div class="text-muted fs-13">
                        <i class="feather-settings me-1"></i>{{ $machine->workCenter->name ?? '—' }}
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div>
                    <span class="badge {{ $avatarColor }} fs-13 px-3 py-2 fw-bold">{{ $machine->current_state }}</span>
                    @if($machine->current_state_reason)
                        <div class="text-muted fs-11 text-end mt-1 fw-semibold">{{ $machine->current_state_reason }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Active Run Info --}}
        @if($currentOp)
            <div class="card border-warning border mb-4">
                <div class="card-header bg-soft-warning border-0">
                    <h6 class="fw-bold text-warning mb-0"><i class="feather-play-circle me-2"></i>Currently Running Operation</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="text-muted fs-11 text-uppercase fw-bold mb-1">Operation</div>
                            <div class="fw-bold text-dark">{{ $currentOp->orderOperation->name ?? '—' }}</div>
                            <div class="text-muted fs-12">{{ $currentOp->orderOperation->operation_number ?? '' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-11 text-uppercase fw-bold mb-1">Product</div>
                            <div class="fw-bold text-dark">{{ $currentOp->order->product->name ?? '—' }}</div>
                            <div class="text-muted fs-12">{{ $currentOp->order->order_number ?? '' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-11 text-uppercase fw-bold mb-1">Progress</div>
                            @if($currentOp->actual_start)
                                <div class="fw-bold text-warning">{{ $currentOp->actual_start->diffForHumans(null, true) }}</div>
                                <div class="text-muted fs-12">Started {{ $currentOp->actual_start->format('d/m H:i') }}</div>
                            @endif
                            <div class="text-muted fs-12 mt-1">Est. finish: {{ $currentOp->planned_finish->format('d/m H:i') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Next Job Alert --}}
        @if($nextOp)
            <div class="alert alert-info border-info bg-soft-info d-flex align-items-center p-3 rounded mb-4">
                <i class="feather-arrow-right me-3 text-info"></i>
                <div>
                    <strong class="text-info fs-12">Next in Queue:</strong>
                    <span class="ms-2 text-dark fs-12">{{ $nextOp->orderOperation->name ?? 'Operation' }}</span>
                    <span class="ms-2 text-muted fs-12">— {{ $nextOp->order->order_number ?? '' }}</span>
                    <span class="ms-2 text-muted fs-12">· Planned: {{ $nextOp->planned_start->format('d/m H:i') }}</span>
                </div>
            </div>
        @endif

        {{-- OEE Control Panels Grid --}}
        <div class="row g-4 mb-5">
            {{-- Column 1: Control Panels --}}
            <div class="col-md-5">
                <div class="card border shadow-sm mb-4">
                    <div class="card-header bg-light border-0 py-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-tool me-2 text-primary"></i>Supervisor State Override</h6>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('production.mes.machines.override-state') }}" method="POST">
                            @csrf
                            <input type="hidden" name="machine_id" value="{{ $machine->id }}">
                            <div class="mb-3">
                                <label class="form-label fs-12 fw-bold text-dark">Target Machine State</label>
                                <select class="form-select fs-12" name="state" required>
                                    <option value="Idle" {{ $machine->current_state === 'Idle' ? 'selected' : '' }}>Idle</option>
                                    <option value="Running" {{ $machine->current_state === 'Running' ? 'selected' : '' }}>Running</option>
                                    <option value="Setup" {{ $machine->current_state === 'Setup' ? 'selected' : '' }}>Setup</option>
                                    <option value="Waiting Material" {{ $machine->current_state === 'Waiting Material' ? 'selected' : '' }}>Waiting Material</option>
                                    <option value="Waiting Operator" {{ $machine->current_state === 'Waiting Operator' ? 'selected' : '' }}>Waiting Operator</option>
                                    <option value="Maintenance" {{ $machine->current_state === 'Maintenance' ? 'selected' : '' }}>Maintenance</option>
                                    <option value="Breakdown" {{ $machine->current_state === 'Breakdown' ? 'selected' : '' }}>Breakdown</option>
                                    <option value="Offline" {{ $machine->current_state === 'Offline' ? 'selected' : '' }}>Offline</option>
                                    <option value="Unknown" {{ $machine->current_state === 'Unknown' ? 'selected' : '' }}>Unknown</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fs-12 fw-bold text-dark">State Reason</label>
                                <input type="text" class="form-control fs-12" name="reason" placeholder="e.g. Preventive Maintenance, Operator Break" maxlength="255">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fs-12 fw-bold text-dark">Remarks</label>
                                <textarea class="form-control fs-12" name="remarks" rows="2" placeholder="Additional state change details..." maxlength="1000"></textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold">Override Machine State</button>
                        </form>
                    </div>
                </div>

                <div class="card border shadow-sm">
                    <div class="card-header bg-light border-0 py-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-alert-triangle me-2 text-danger"></i>Report Machine Downtime</h6>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('production.mes.downtime.start') }}" method="POST">
                            @csrf
                            <input type="hidden" name="machine_id" value="{{ $machine->id }}">
                            @if($currentOp)
                                <input type="hidden" name="production_order_id" value="{{ $currentOp->production_order_id }}">
                                <input type="hidden" name="production_order_operation_id" value="{{ $currentOp->production_order_operation_id }}">
                            @endif
                            <div class="mb-3">
                                <label class="form-label fs-12 fw-bold text-dark">Downtime Category</label>
                                <select class="form-select fs-12" name="category" required>
                                    <option value="Breakdown">Breakdown</option>
                                    <option value="Preventive Maintenance">Preventive Maintenance</option>
                                    <option value="Corrective Maintenance">Corrective Maintenance</option>
                                    <option value="Setup">Setup</option>
                                    <option value="Tool Change">Tool Change</option>
                                    <option value="Power Failure">Power Failure</option>
                                    <option value="Material Shortage">Material Shortage</option>
                                    <option value="Operator Shortage">Operator Shortage</option>
                                    <option value="Quality Hold">Quality Hold</option>
                                    <option value="Calibration">Calibration</option>
                                    <option value="Cleaning">Cleaning</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fs-12 fw-bold text-dark">Root Cause Reason</label>
                                <input type="text" class="form-control fs-12" name="reason" placeholder="e.g. Hydraulic pump leak, power outage" required maxlength="255">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fs-12 fw-bold text-dark">Remarks</label>
                                <textarea class="form-control fs-12" name="remarks" rows="2" placeholder="Describe symptoms or resolution details..." maxlength="1000"></textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-danger w-100 fw-bold">Report & Start Downtime</button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Column 2: Active & History Logs --}}
            <div class="col-md-7">
                {{-- Active Downtimes list --}}
                <div class="card border border-danger shadow-sm mb-4">
                    <div class="card-header bg-soft-danger border-0 py-3">
                        <h6 class="fw-bold text-danger mb-0"><i class="feather-clock me-2"></i>Active Downtime Events</h6>
                    </div>
                    <div class="card-body p-0">
                        @php
                            $activeDowntimes = $downtimes->where('status', 'open');
                        @endphp
                        @if($activeDowntimes->count() > 0)
                            <div class="list-group list-group-flush">
                                @foreach($activeDowntimes as $dt)
                                    <div class="list-group-item p-3 border-bottom">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <span class="badge bg-soft-danger text-danger fs-11 mb-1">{{ $dt->category }}</span>
                                                <h6 class="fw-bold text-dark mb-0 fs-12">{{ $dt->reason }}</h6>
                                                <small class="text-muted">Started {{ $dt->start_time->format('d/m/Y H:i') }} · {{ $dt->start_time->diffForHumans() }}</small>
                                            </div>
                                        </div>
                                        <form action="{{ route('production.mes.downtime.end', $dt->id) }}" method="POST" class="mt-2">
                                            @csrf
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="remarks" class="form-control fs-11" placeholder="Resolution / Remarks...">
                                                <button type="submit" class="btn btn-success fw-bold fs-11"><i class="feather-check-circle me-1"></i>Resolve & End</button>
                                            </div>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-4 text-muted fs-12">
                                <i class="feather-shield me-2 text-success"></i>No active downtime events. Machine is running healthy!
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Machine State History --}}
                <div class="card border shadow-sm">
                    <div class="card-header bg-light border-0 py-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-bar-chart-2 me-2 text-primary"></i>Recent Machine State History</h6>
                    </div>
                    <div class="card-body p-0">
                        @if($stateHistories->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="fs-11 py-2" style="width: 20%">State</th>
                                            <th class="fs-11 py-2" style="width: 25%">Reason</th>
                                            <th class="fs-11 py-2" style="width: 30%">Duration</th>
                                            <th class="fs-11 py-2" style="width: 25%">Changed By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($stateHistories as $sh)
                                            @php
                                                $shColor = match($sh->state) {
                                                    'Running' => 'text-success fw-bold',
                                                    'Breakdown' => 'text-danger fw-bold',
                                                    'Setup' => 'text-info fw-bold',
                                                    'Waiting Material', 'Waiting Operator' => 'text-warning fw-bold',
                                                    'Maintenance' => 'text-primary fw-bold',
                                                    default => 'text-secondary',
                                                };
                                            @endphp
                                            <tr>
                                                <td class="fs-12 py-2"><span class="{{ $shColor }}">{{ $sh->state }}</span></td>
                                                <td class="fs-12 py-2 text-muted fw-semibold">{{ $sh->reason ?: '—' }}</td>
                                                <td class="fs-12 py-2">
                                                    @if($sh->ended_at)
                                                        {{ $sh->started_at->format('H:i') }} - {{ $sh->ended_at->format('H:i') }}
                                                        <small class="text-muted d-block">
                                                            @if($sh->duration_seconds >= 3600)
                                                                {{ round($sh->duration_seconds / 3600, 1) }}h
                                                            @elseif($sh->duration_seconds >= 60)
                                                                {{ round($sh->duration_seconds / 60, 1) }}m
                                                            @else
                                                                {{ $sh->duration_seconds }}s
                                                            @endif
                                                        </small>
                                                    @else
                                                        <span class="badge bg-soft-success text-success fs-10">Active Since {{ $sh->started_at->format('H:i') }}</span>
                                                    @endif
                                                </td>
                                                <td class="fs-12 py-2 text-dark">{{ $sh->changer->name ?? 'System' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4 text-muted fs-12">
                                <i class="feather-inbox me-2"></i>No state logs recorded yet.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Downtime Logs History --}}
        <h5 class="fw-bold text-dark mb-3">
            <i class="feather-alert-octagon me-2"></i>Downtime Logging History
        </h5>
        @if($downtimes->count() > 0)
            <div class="table-responsive mb-5">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 15%">Category</th>
                            <th style="width: 25%">Reason</th>
                            <th style="width: 20%">Active Period</th>
                            <th style="width: 12%">Duration</th>
                            <th style="width: 15%">Logged By</th>
                            <th style="width: 13%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($downtimes as $dt)
                            <tr>
                                <td class="fs-12 fw-bold"><span class="text-capitalize text-danger">{{ $dt->category }}</span></td>
                                <td class="fs-12 text-dark font-medium">
                                    {{ $dt->reason }}
                                    @if($dt->remarks)
                                        <small class="text-muted d-block font-normal mt-1">{{ $dt->remarks }}</small>
                                    @endif
                                </td>
                                <td class="fs-12 text-muted">
                                    {{ $dt->start_time->format('d/m H:i') }}
                                    @if($dt->end_time)
                                        - {{ $dt->end_time->format('d/m H:i') }}
                                    @else
                                        - <span class="text-danger fw-bold">Present</span>
                                    @endif
                                </td>
                                <td class="fs-12 text-dark fw-bold">
                                    @if($dt->duration_minutes !== null)
                                        {{ $dt->duration_minutes }} min
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="fs-12 text-muted">{{ $dt->creator->name ?? 'System' }}</td>
                                <td>
                                    @if($dt->status === 'closed')
                                        <span class="badge bg-soft-success text-success">Resolved</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger">Unresolved</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        @else
            <div class="text-center py-4 text-muted fs-12 border rounded mb-5">
                <i class="feather-check-square me-2 text-success"></i>No downtime events logged.
            </div>
        @endif

        {{-- Operation History --}}
        <h5 class="fw-bold text-dark mb-3">
            <i class="feather-clock me-2"></i>Recent Work Orders Executed
        </h5>
        @if($history->count() > 0)
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 20%">Operation</th>
                            <th style="width: 20%">Order / Product</th>
                            <th style="width: 13%">Planned Start</th>
                            <th style="width: 13%">Actual Start</th>
                            <th style="width: 13%">Planned Finish</th>
                            <th style="width: 13%">Actual Finish</th>
                            <th style="width: 10%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history as $op)
                            <tr>
                                <td class="fw-semibold text-dark fs-12">{{ $op->orderOperation->name ?? 'Op #'.$op->sequence }}</td>
                                <td>
                                    <div class="fw-semibold text-dark fs-12">{{ $op->order->product->name ?? '—' }}</div>
                                    <small class="text-muted">{{ $op->order->order_number ?? '' }}</small>
                                </td>
                                <td class="text-muted fs-12">{{ $op->planned_start->format('d/m H:i') }}</td>
                                <td class="fs-12 {{ $op->actual_start ? 'text-dark fw-semibold' : 'text-muted' }}">
                                    {{ $op->actual_start ? $op->actual_start->format('d/m H:i') : '—' }}
                                </td>
                                <td class="text-muted fs-12">{{ $op->planned_finish->format('d/m H:i') }}</td>
                                <td class="fs-12 {{ $op->actual_finish ? 'text-success fw-semibold' : 'text-muted' }}">
                                    {{ $op->actual_finish ? $op->actual_finish->format('d/m H:i') : '—' }}
                                </td>
                                <td>
                                    @if($op->status === 'completed')
                                        <span class="badge bg-soft-success text-success">Done</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger text-capitalize">{{ $op->status }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        @else
            <div class="text-center py-3 text-muted fs-13">
                <i class="feather-inbox me-2"></i>No completed operations yet for this machine.
            </div>
        @endif
    </div>
@endsection
