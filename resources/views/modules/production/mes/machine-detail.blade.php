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

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-text avatar-xl {{ $currentOp ? 'bg-soft-warning text-warning' : 'bg-soft-secondary text-secondary' }} rounded">
                    <i class="feather-cpu fs-22"></i>
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-0">{{ $machine->name }}</h4>
                    <div class="text-muted fs-13">
                        <i class="feather-settings me-1"></i>{{ $machine->workCenter->name ?? '—' }}
                    </div>
                </div>
            </div>
            @if($currentOp)
                <span class="badge bg-soft-warning text-warning fs-13">Running</span>
            @else
                <span class="badge bg-soft-secondary text-secondary fs-13">Idle</span>
            @endif
        </div>

        {{-- Current Operation --}}
        @if($currentOp)
            <div class="card border-warning border mb-4">
                <div class="card-header bg-soft-warning border-0">
                    <h6 class="fw-bold text-warning mb-0"><i class="feather-play-circle me-2"></i>Currently Running</h6>
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

        {{-- Next Job --}}
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

        {{-- Operation History --}}
        <h5 class="fw-bold text-dark mb-3">
            <i class="feather-clock me-2"></i>Recent History
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
