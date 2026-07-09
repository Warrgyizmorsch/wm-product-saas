@extends('layouts.duralux')

@section('title', 'Shop Floor Dashboard | SaaS ERP')
@section('page-title', 'Shop Floor — Operator Dashboard')
@section('breadcrumb', 'MES Dashboard')

@push('styles')
    <style>
        .mes-op-card {
            border-radius: 12px;
            transition: box-shadow 0.2s;
        }
        .mes-op-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }
        .mes-action-btn {
            min-width: 100px;
        }
    </style>
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
    <div class="erp-single-panel">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Summary Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md col-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-text avatar-md bg-soft-warning text-warning rounded me-3">
                                <i class="feather-play-circle"></i>
                            </div>
                            <div>
                                <div class="fs-18 fw-bold text-dark">{{ $running->count() }}</div>
                                <div class="fs-11 text-muted text-uppercase">Running</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md col-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-text avatar-md bg-soft-info text-info rounded me-3">
                                <i class="feather-check-square"></i>
                            </div>
                            <div>
                                <div class="fs-18 fw-bold text-dark">{{ $ready->count() }}</div>
                                <div class="fs-11 text-muted text-uppercase">Ready to Start</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md col-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-text avatar-md bg-soft-success text-success rounded me-3">
                                <i class="feather-check-circle"></i>
                            </div>
                            <div>
                                <div class="fs-18 fw-bold text-dark">{{ $completedToday }}</div>
                                <div class="fs-11 text-muted text-uppercase">Done Today</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md col-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-text avatar-md bg-soft-danger text-danger rounded me-3">
                                <i class="feather-pause-circle"></i>
                            </div>
                            <div>
                                <div class="fs-18 fw-bold text-dark">{{ $paused->count() }}</div>
                                <div class="fs-11 text-muted text-uppercase">On Hold</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md col-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-text avatar-md bg-soft-secondary text-secondary rounded me-3">
                                <i class="feather-clock"></i>
                            </div>
                            <div>
                                <div class="fs-18 fw-bold text-dark">{{ $upcoming->count() }}</div>
                                <div class="fs-11 text-muted text-uppercase">Upcoming</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Running Operations --}}
        @if($running->count() > 0)
            <div class="mb-4">
                <h5 class="fw-bold text-warning mb-3">
                    <i class="feather-play-circle me-2"></i>Currently Running ({{ $running->count() }})
                </h5>
                @foreach($running as $op)
                    <div class="card mes-op-card border-warning border mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-5">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-text avatar-lg bg-soft-warning text-warning rounded">
                                            <i class="feather-play-circle fs-20"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0">{{ $op->orderOperation->name ?? 'Operation #' . $op->sequence }}</h6>
                                            <div class="text-muted fs-12">
                                                <span>{{ $op->order->product->name ?? '—' }}</span>
                                                <span class="mx-2">·</span>
                                                <span>{{ $op->order->order_number ?? '' }}</span>
                                            </div>
                                            <div class="text-muted fs-12 mt-1">
                                                <i class="feather-settings me-1"></i>{{ $op->workCenter->name ?? '—' }}
                                                @if($op->machine)
                                                    <span class="mx-2">·</span>
                                                    <i class="feather-cpu me-1"></i>{{ $op->machine->name }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    @if($op->actual_start)
                                        <div class="fs-12 text-muted">Started: <strong class="text-dark">{{ $op->actual_start->format('d/m H:i') }}</strong></div>
                                        <div class="fs-12 text-muted mt-1">Elapsed: <strong class="text-warning">{{ $op->actual_start->diffForHumans(null, true) }}</strong></div>
                                    @endif
                                    <div class="fs-12 text-muted mt-1">Planned finish: <strong class="text-dark">{{ $op->planned_finish->format('d/m H:i') }}</strong></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex gap-2 justify-content-end flex-wrap">
                                        <form method="POST" action="{{ route('production.mes.pause', $op->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-warning mes-action-btn">
                                                <i class="feather-pause me-1"></i>Pause
                                            </button>
                                        </form>

                                        {{-- Complete Modal Trigger --}}
                                        <button type="button" class="btn btn-success mes-action-btn" data-bs-toggle="modal" data-bs-target="#completeModal{{ $op->id }}">
                                            <i class="feather-check me-1"></i>Complete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Complete Operation Modal --}}
                    <x-ui.modal id="completeModal{{ $op->id }}" title="Complete Operation — {{ $op->orderOperation->name ?? 'Op #'.$op->sequence }}" class="text-start">
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
                            <button type="submit" class="btn btn-success" onclick="document.getElementById('completeForm{{ $op->id }}').submit();">
                                <i class="feather-check me-1"></i>Complete Operation
                            </button>
                        </x-slot>
                    </x-ui.modal>
                @endforeach
            </div>
        @endif

        {{-- Paused Operations --}}
        @if($paused->count() > 0)
            <div class="mb-4">
                <h5 class="fw-bold text-danger mb-3">
                    <i class="feather-pause-circle me-2"></i>On Hold / Paused ({{ $paused->count() }})
                </h5>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th>Operation</th>
                                <th>Order / Product</th>
                                <th>Work Center</th>
                                <th>Planned Finish</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paused as $op)
                                <tr>
                                    <td class="fw-semibold text-dark">{{ $op->orderOperation->name ?? 'Op #'.$op->sequence }}</td>
                                    <td>
                                        <div class="fw-semibold text-dark fs-12">{{ $op->order->product->name ?? '—' }}</div>
                                        <small class="text-muted">{{ $op->order->order_number ?? '' }}</small>
                                    </td>
                                    <td class="text-muted fs-12">{{ $op->workCenter->name ?? '—' }}</td>
                                    <td class="text-muted fs-12">{{ $op->planned_finish->format('d/m H:i') }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('production.mes.resume', $op->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary">
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

        {{-- Ready Queue --}}
        <div class="mb-4">
            <h5 class="fw-bold text-info mb-3">
                <i class="feather-check-square me-2"></i>Ready Queue ({{ $ready->count() }})
            </h5>
            @if($ready->count() > 0)
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th>Operation</th>
                                <th>Order / Product</th>
                                <th>Work Center</th>
                                <th>Machine</th>
                                <th>Planned Start</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ready as $op)
                                <tr>
                                    <td class="fw-semibold text-dark">{{ $op->orderOperation->name ?? 'Op #'.$op->sequence }}</td>
                                    <td>
                                        <div class="fw-semibold text-dark fs-12">{{ $op->order->product->name ?? '—' }}</div>
                                        <small class="text-muted">{{ $op->order->order_number ?? '' }}</small>
                                    </td>
                                    <td class="text-muted fs-12">{{ $op->workCenter->name ?? '—' }}</td>
                                    <td class="text-muted fs-12">{{ $op->machine->name ?? '—' }}</td>
                                    <td class="text-muted fs-12">{{ $op->planned_start->format('d/m H:i') }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('production.mes.start', $op->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">
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
                <div class="text-center py-3 text-muted fs-13">
                    <i class="feather-inbox me-2"></i>No operations are ready to start right now.
                </div>
            @endif
        </div>

        {{-- Upcoming Operations --}}
        @if($upcoming->count() > 0)
            <div class="mb-4">
                <h5 class="fw-bold text-muted mb-3">
                    <i class="feather-clock me-2"></i>Upcoming Operations ({{ $upcoming->count() }})
                </h5>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th>Operation</th>
                                <th>Order / Product</th>
                                <th>Work Center</th>
                                <th>Estimated Start</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($upcoming as $op)
                                <tr>
                                    <td class="fw-semibold text-dark">{{ $op->orderOperation->name ?? 'Op #'.$op->sequence }}</td>
                                    <td>
                                        <div class="fw-semibold text-dark fs-12">{{ $op->order->product->name ?? '—' }}</div>
                                        <small class="text-muted">{{ $op->order->order_number ?? '' }}</small>
                                    </td>
                                    <td class="text-muted fs-12">{{ $op->workCenter->name ?? '—' }}</td>
                                    <td class="text-muted fs-12">{{ $op->planned_start->format('d/m H:i') }}</td>
                                    <td><span class="erp-badge-draft">Waiting</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>
        @endif

        {{-- Shifts Integration --}}
        <div class="card border-0 bg-light shadow-sm">
            <div class="card-body py-4">
                <div class="d-flex align-items-center mb-3">
                    <h6 class="fw-bold text-dark mb-0"><i class="feather-sun me-2 text-warning"></i>Active Production Shifts</h6>
                    <a href="{{ route('production.shifts.index') }}" class="btn btn-xs btn-outline-primary ms-auto">
                        <i class="feather-settings me-1"></i>Manage Shifts
                    </a>
                </div>
                @if($shifts->count() > 0)
                    <div class="row g-3">
                        @foreach($shifts as $sf)
                            <div class="col-md-3">
                                <div class="bg-white p-3 rounded border">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="font-monospace fw-bold text-primary">{{ $sf->code }}</span>
                                        @if($sf->active)
                                            <span class="badge bg-soft-success text-success fs-10">Active</span>
                                        @endif
                                    </div>
                                    <h6 class="fw-semibold text-dark mb-1 fs-13">{{ $sf->name }}</h6>
                                    <div class="text-muted fs-11">
                                        <i class="feather-clock me-1"></i>{{ substr($sf->start_time, 0, 5) }} - {{ substr($sf->end_time, 0, 5) }}
                                        <span class="mx-1">·</span>
                                        <span>Break: {{ $sf->break_minutes }}m</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-2 text-muted fs-12">
                        <span>No shifts configured yet. Configured shifts will dynamically manage work center capacity and schedule timings. <a href="{{ route('production.shifts.create') }}" class="text-primary fw-semibold">Click here to add your first Shift</a>.</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
