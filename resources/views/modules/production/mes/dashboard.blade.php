@extends('layouts.duralux')

@section('title', 'Shop Floor Dashboard | SaaS ERP')
@section('page-title', 'Shop Floor — Operator Dashboard')
@section('breadcrumb', 'MES Dashboard')

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
            border-left: 5px solid #dc3545 !important;
            background: #ffffff;
        }
        .ready-card {
            border-left: 5px solid #17a2b8 !important;
            background: #ffffff;
        }
        .mes-action-btn {
            min-width: 100px;
        }
        @@keyframes pulse-glowing {
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
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function updateTimers() {
                const now = new Date();
                
                document.querySelectorAll('.mes-timer-block').forEach(block => {
                    const startTimeStr = block.dataset.start;
                    const finishTimeStr = block.dataset.finish;
                    
                    if (startTimeStr) {
                        const start = new Date(startTimeStr);
                        const elapsedMs = now - start;
                        if (elapsedMs > 0) {
                            const elapsedSecs = Math.floor(elapsedMs / 1000);
                            const h = String(Math.floor(elapsedSecs / 3600)).padStart(2, '0');
                            const m = String(Math.floor((elapsedSecs % 3600) / 60)).padStart(2, '0');
                            const s = String(elapsedSecs % 60).padStart(2, '0');
                            
                            const elapsedEl = block.querySelector('.timer-elapsed');
                            if (elapsedEl) elapsedEl.textContent = `${h}:${m}:${s}`;
                        }
                    }
                    
                    if (finishTimeStr && startTimeStr) {
                        const start = new Date(startTimeStr);
                        const finish = new Date(finishTimeStr);
                        
                        const totalDuration = finish - start;
                        const elapsed = now - start;
                        
                        if (totalDuration > 0) {
                            let percent = (elapsed / totalDuration) * 100;
                            if (percent < 0) percent = 0;
                            if (percent > 100) percent = 100;
                            
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
                            
                            // Remaining countdown
                            const remainingMs = finish - now;
                            const remainingEl = block.querySelector('.timer-remaining');
                            if (remainingEl) {
                                if (remainingMs > 0) {
                                    const remainingSecs = Math.floor(remainingMs / 1000);
                                    const h = String(Math.floor(remainingSecs / 3600)).padStart(2, '0');
                                    const m = String(Math.floor((remainingSecs % 3600) / 60)).padStart(2, '0');
                                    const s = String(remainingSecs % 60).padStart(2, '0');
                                    remainingEl.textContent = `${h}:${m}:${s}`;
                                    remainingEl.className = 'timer-remaining text-success fw-bold font-monospace';
                                } else {
                                    const overdueSecs = Math.floor(Math.abs(remainingMs) / 1000);
                                    const h = String(Math.floor(overdueSecs / 3600)).padStart(2, '0');
                                    const m = String(Math.floor((overdueSecs % 3600) / 60)).padStart(2, '0');
                                    const s = String(overdueSecs % 60).padStart(2, '0');
                                    remainingEl.textContent = `Overdue: -${h}:${m}:${s}`;
                                    remainingEl.className = 'timer-remaining text-danger fw-bold font-monospace';
                                }
                            }
                        }
                    }
                });
            }
            
            // Initial call and tick every second
            updateTimers();
            setInterval(updateTimers, 1000);
        });
    </script>
@endpush

@section('page-actions')
    <a href="{{ route('production.mes.machines.index') }}" class="btn btn-light me-2">
        <i class="feather-cpu me-2"></i>Machines
    </a>
    <a href="{{ route('production.mes.work-centers.index') }}" class="btn btn-light">
        <i class="feather-settings me-2"></i>Work Centers
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-transparent border-0 p-0">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <div class="row g-4">
            {{-- LEFT COLUMN: Production Queue Lists (8 Cols) --}}
            <div class="col-lg-8">
                
                {{-- 1. Currently Running operations --}}
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center">
                            <span class="pulse-dot me-2"></span>Currently Running Operations
                        </h5>
                        <span class="badge bg-soft-success text-success rounded-pill ms-2 fw-bold font-monospace">{{ $running->count() }}</span>
                    </div>

                    @forelse($running as $op)
                        <div class="card mes-op-card running-card mb-3">
                            <div class="card-body p-4">
                                <div class="row align-items-center g-3">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="avatar-text avatar-lg bg-soft-success text-success rounded">
                                                <i class="feather-play-circle fs-20"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold text-dark mb-1 fs-14">{{ $op->orderOperation->name ?? 'Operation #' . $op->sequence }}</h6>
                                                <div class="text-muted fs-12 mb-2">
                                                    <span class="fw-semibold text-secondary">{{ $op->order->product->name ?? '—' }}</span>
                                                    <span class="mx-1">&middot;</span>
                                                    <span class="font-monospace fs-11">{{ $op->order->order_number ?? '' }}</span>
                                                </div>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <span class="badge bg-light text-secondary fs-10 px-2 py-1 border">
                                                        <i class="feather-settings me-1 fs-9"></i>{{ $op->workCenter->name ?? '—' }}
                                                    </span>
                                                    @if($op->machine)
                                                        <span class="badge bg-light text-secondary fs-10 px-2 py-1 border">
                                                            <i class="feather-cpu me-1 fs-9"></i>{{ $op->machine->name }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="bg-light p-3 rounded border mes-timer-block" 
                                             data-start="{{ $op->actual_start ? $op->actual_start->toISOString() : '' }}"
                                             data-finish="{{ $op->planned_finish ? $op->planned_finish->toISOString() : '' }}">
                                            <div class="row text-center g-2">
                                                <div class="col-6 border-end">
                                                    <small class="text-muted text-uppercase d-block fs-9 mb-1 fw-bold">Elapsed Stopwatch</small>
                                                    <span class="timer-elapsed font-monospace fw-bold text-dark fs-14">00:00:00</span>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted text-uppercase d-block fs-9 mb-1 fw-bold">Planned Countdown</small>
                                                    <span class="timer-remaining font-monospace fw-bold text-success fs-14">00:00:00</span>
                                                </div>
                                            </div>
                                            <div class="progress progress-sm bg-white border mt-2">
                                                <div class="progress-bar timer-progress-bar bg-success" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                                    <div class="fs-11 text-muted">
                                        Started: <span class="fw-semibold text-dark">{{ $op->actual_start ? $op->actual_start->format('d M, H:i') : '—' }}</span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="{{ route('production.mes.pause', $op->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-warning text-dark px-3 fw-semibold">
                                                <i class="feather-pause me-1"></i>Pause
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-success px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#completeModal{{ $op->id }}">
                                            <i class="feather-check-circle me-1"></i>Complete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Complete Operation Modal --}}
                        <x-ui.modal id="completeModal{{ $op->id }}" title="Log Production Progress — {{ $op->orderOperation->name ?? 'Op #'.$op->sequence }}" class="text-start">
                            <form method="POST" action="{{ route('production.mes.complete', $op->id) }}" id="completeForm{{ $op->id }}">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="Qty Produced" name="quantity_produced" inputType="number" step="any" value="0" :required="true" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="Qty Rejected" name="quantity_rejected" inputType="number" step="any" value="0" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="Qty Scrapped" name="quantity_scrapped" inputType="number" step="any" value="0" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="Setup Time (min)" name="setup_minutes" inputType="number" step="any" value="0" />
                                    </div>
                                    <div class="col-md-12">
                                        <x-ui.odoo-form-ui type="input" label="Run Time (min)" name="run_minutes" inputType="number" step="any" value="0" />
                                    </div>
                                    <div class="col-md-12">
                                        <x-ui.odoo-form-ui type="textarea" label="Remarks" name="remarks" placeholder="Optional completion notes..." />
                                    </div>
                                </div>
                            </form>
                            <x-slot name="footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success px-4" onclick="document.getElementById('completeForm{{ $op->id }}').submit();">
                                    <i class="feather-check me-1"></i>Complete Operation
                                </button>
                            </x-slot>
                        </x-ui.modal>
                    @empty
                        <div class="card p-4 text-center border bg-white rounded-3 shadow-xs mb-4">
                            <div class="avatar-text avatar-lg bg-soft-light text-muted rounded mx-auto mb-3">
                                <i class="feather-play-circle fs-24"></i>
                            </div>
                            <h6 class="fw-bold text-dark">No active operations running</h6>
                            <p class="text-muted fs-12 mb-0">Select an operation from the Ready Queue below to initiate work center progress.</p>
                        </div>
                    @endforelse
                </div>

                {{-- 2. Ready Queue --}}
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center">
                            <i class="feather-check-square text-info me-2 fs-18"></i>Ready Queue
                        </h5>
                        <span class="badge bg-soft-info text-info rounded-pill ms-2 fw-bold font-monospace">{{ $ready->count() }}</span>
                    </div>

                    @if($ready->count() > 0)
                        <div class="table-responsive border rounded bg-white">
                            <x-ui.odoo-form-ui type="table" class="mb-0">
                                <thead>
                                    <tr>
                                        <th>Operation</th>
                                        <th>Order / Item</th>
                                        <th>Work Center / Asset</th>
                                        <th>Planned Start</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ready as $op)
                                        <tr>
                                            <td>
                                                <span class="fw-bold text-dark d-block fs-13">{{ $op->orderOperation->name ?? 'Op #'.$op->sequence }}</span>
                                                <small class="text-muted">Seq {{ $op->sequence }}</small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-secondary fs-12">{{ $op->order->product->name ?? '—' }}</div>
                                                <small class="text-muted font-monospace">{{ $op->order->order_number ?? '' }}</small>
                                            </td>
                                            <td>
                                                <div class="fs-12 text-dark"><i class="feather-settings me-1 fs-10 text-muted"></i>{{ $op->workCenter->name ?? '—' }}</div>
                                                <small class="text-muted"><i class="feather-cpu me-1 fs-10"></i>{{ $op->machine->name ?? 'Generic Capacity' }}</small>
                                            </td>
                                            <td class="fs-12 text-muted">{{ $op->planned_start->format('d M, H:i') }}</td>
                                            <td class="text-end">
                                                <form method="POST" action="{{ route('production.mes.start', $op->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success px-3 fw-semibold">
                                                        <i class="feather-play me-1"></i>Start
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    @else
                        <div class="card p-4 text-center border bg-white rounded-3 shadow-xs">
                            <div class="avatar-text avatar-lg bg-soft-light text-muted rounded mx-auto mb-3">
                                <i class="feather-inbox fs-24"></i>
                            </div>
                            <h6 class="fw-bold text-dark">Ready Queue is empty</h6>
                            <p class="text-muted fs-12 mb-0">No scheduling operations have been released to the shop floor.</p>
                        </div>
                    @endif
                </div>

                {{-- 3. On Hold / Paused --}}
                @if($paused->count() > 0)
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <h5 class="fw-bold text-danger mb-0 d-flex align-items-center">
                                <i class="feather-pause-circle me-2 fs-18"></i>On Hold &amp; Paused Operations
                            </h5>
                            <span class="badge bg-soft-danger text-danger rounded-pill ms-2 fw-bold font-monospace">{{ $paused->count() }}</span>
                        </div>
                        <div class="table-responsive border rounded bg-white">
                            <x-ui.odoo-form-ui type="table" class="mb-0">
                                <thead>
                                    <tr>
                                        <th>Operation</th>
                                        <th>Order / Item</th>
                                        <th>Work Center</th>
                                        <th>Reason / Remarks</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($paused as $op)
                                        <tr class="table-danger-soft">
                                            <td>
                                                <span class="fw-bold text-dark d-block fs-13">{{ $op->orderOperation->name ?? 'Op #'.$op->sequence }}</span>
                                                <small class="text-muted">Seq {{ $op->sequence }}</small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-secondary fs-12">{{ $op->order->product->name ?? '—' }}</div>
                                                <small class="text-muted font-monospace">{{ $op->order->order_number ?? '' }}</small>
                                            </td>
                                            <td class="text-muted fs-12">{{ $op->workCenter->name ?? '—' }}</td>
                                            <td class="text-muted fs-12 italic">
                                                {{ $op->remarks ?? 'No remarks provided.' }}
                                            </td>
                                            <td class="text-end">
                                                <form method="POST" action="{{ route('production.mes.resume', $op->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary px-3 fw-semibold">
                                                        <i class="feather-play me-1"></i>Resume
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                @endif

                {{-- 4. Upcoming operations --}}
                @if($upcoming->count() > 0)
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <h5 class="fw-bold text-muted mb-0 d-flex align-items-center">
                                <i class="feather-clock me-2 fs-18"></i>Upcoming Operations
                            </h5>
                            <span class="badge bg-soft-secondary text-secondary rounded-pill ms-2 fw-bold font-monospace">{{ $upcoming->count() }}</span>
                        </div>
                        <div class="table-responsive border rounded bg-white">
                            <x-ui.odoo-form-ui type="table" class="mb-0">
                                <thead>
                                    <tr>
                                        <th>Operation</th>
                                        <th>Order / Item</th>
                                        <th>Work Center</th>
                                        <th>Estimated Start</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($upcoming as $op)
                                        <tr>
                                            <td>
                                                <span class="fw-bold text-dark d-block fs-13">{{ $op->orderOperation->name ?? 'Op #'.$op->sequence }}</span>
                                                <small class="text-muted">Seq {{ $op->sequence }}</small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-secondary fs-12">{{ $op->order->product->name ?? '—' }}</div>
                                                <small class="text-muted font-monospace">{{ $op->order->order_number ?? '' }}</small>
                                            </td>
                                            <td class="text-muted fs-12">{{ $op->workCenter->name ?? '—' }}</td>
                                            <td class="text-muted fs-12">{{ $op->planned_start->format('d M, H:i') }}</td>
                                            <td>
                                                <span class="badge bg-soft-secondary text-secondary fs-10 px-2 py-0.5">Waiting</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                @endif
            </div>

            {{-- RIGHT COLUMN: Secondary Metadata Widgets & Shortcuts (4 Cols) --}}
            <div class="col-lg-4">
                
                {{-- Side Widget A: Stats Summary --}}
                <div class="card border-0 shadow-sm mb-4 sidebar-widget">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="feather-pie-chart text-primary me-2"></i>Performance Tracking</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="bg-light p-3 rounded text-center border">
                                    <span class="fs-20 fw-bold text-success">{{ $completedToday }}</span>
                                    <span class="text-muted text-uppercase fs-9 d-block mt-1 font-monospace fw-bold">Done Today</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-light p-3 rounded text-center border">
                                    <span class="fs-20 fw-bold text-warning">{{ $running->count() }}</span>
                                    <span class="text-muted text-uppercase fs-9 d-block mt-1 font-monospace fw-bold">Active Ops</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Side Widget B: Quick Scan Shortcuts --}}
                <div class="card border-0 shadow-sm mb-4 sidebar-widget">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="feather-maximize text-secondary me-2"></i>Shop Floor Scan Center</h6>
                        <div class="list-group list-group-flush mb-0">
                            <a href="{{ route('production.mes.scanner.index') }}" class="list-group-item list-group-item-action d-flex align-items-center px-0 py-3">
                                <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded me-3">
                                    <i class="feather-maximize"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fs-13 fw-semibold text-dark">Barcode Scanner</div>
                                    <small class="text-muted">Simulate scan actions for parts</small>
                                </div>
                                <i class="feather-chevron-right text-muted fs-12"></i>
                            </a>
                            <a href="{{ route('production.mes.traceability.index') }}" class="list-group-item list-group-item-action d-flex align-items-center px-0 py-3">
                                <div class="avatar-text avatar-sm bg-soft-success text-success rounded me-3">
                                    <i class="feather-git-commit"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fs-13 fw-semibold text-dark">Lot Traceability</div>
                                    <small class="text-muted">Trace component origins</small>
                                </div>
                                <i class="feather-chevron-right text-muted fs-12"></i>
                            </a>
                            <a href="{{ route('production.scan-logs.index') }}" class="list-group-item list-group-item-action d-flex align-items-center px-0 py-3">
                                <div class="avatar-text avatar-sm bg-soft-info text-info rounded me-3">
                                    <i class="feather-list"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fs-13 fw-semibold text-dark">Scan Logs</div>
                                    <small class="text-muted">View historical scans</small>
                                </div>
                                <i class="feather-chevron-right text-muted fs-12"></i>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Side Widget C: Shifts Integration --}}
                <div class="card border-0 shadow-sm sidebar-widget">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <h6 class="fw-bold text-dark mb-0"><i class="feather-sun me-2 text-warning"></i>Active Shifts</h6>
                            <a href="{{ route('production.shifts.index') }}" class="btn btn-xs btn-outline-primary ms-auto">
                                <i class="feather-settings me-1"></i>Manage
                            </a>
                        </div>
                        @if($shifts->count() > 0)
                            <div class="d-flex flex-column gap-2">
                                @foreach($shifts as $sf)
                                    <div class="bg-light p-3 rounded border">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="font-monospace fw-bold text-primary fs-11">{{ $sf->code }}</span>
                                            @if($sf->active)
                                                <span class="badge bg-soft-success text-success fs-9 rounded-pill px-2">Active</span>
                                            @endif
                                        </div>
                                        <h6 class="fw-semibold text-dark mb-1 fs-12">{{ $sf->name }}</h6>
                                        <div class="text-muted fs-11">
                                            <i class="feather-clock me-1"></i>{{ substr($sf->start_time, 0, 5) }} - {{ substr($sf->end_time, 0, 5) }}
                                            <span class="mx-1">&middot;</span>
                                            <span>Break: {{ $sf->break_minutes }}m</span>
                                        </div>
                                    </div>
                                @endforeach
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
