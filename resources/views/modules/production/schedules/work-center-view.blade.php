@extends('layouts.duralux')

@section('title', 'Work Center Schedule View | SaaS ERP')
@section('page-title', 'Work Center Schedule View')
@section('breadcrumb', 'Work Center View')

@section('page-actions')
    <a href="{{ route('production.schedules.index') }}" class="btn btn-secondary me-2">
        <i class="feather-list me-2"></i>List View
    </a>
    <a href="{{ route('production.schedules.calendar') }}" class="btn btn-light me-2">
        <i class="feather-calendar me-2"></i>Calendar View
    </a>
    <a href="{{ route('production.mes.work-centers.index') }}" class="btn btn-light">
        <i class="feather-monitor me-2"></i>MES Dashboard
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        @if($workCenters->count() === 0)
            <div class="text-center py-5 text-muted">
                <i class="feather-grid fs-36 mb-3 d-block"></i>
                <p class="fs-14">No active Work Centers configured.</p>
            </div>
        @else
            @foreach($workCenters as $wc)
                @php
                    $wcOps = $operations->get($wc->id, collect());
                    $runningCount = $wcOps->where('status', 'running')->count();
                    $readyCount   = $wcOps->where('status', 'ready')->count();
                    $waitingCount = $wcOps->where('status', 'waiting')->count();
                @endphp

                <div class="mb-5">
                    {{-- Work Center Header --}}
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-md bg-soft-primary text-primary rounded">
                                <i class="feather-settings"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0">{{ $wc->name }}</h5>
                                <small class="text-muted">{{ $wc->code ?? '' }}</small>
                            </div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            @if($runningCount > 0)
                                <span class="badge bg-soft-warning text-warning">{{ $runningCount }} Running</span>
                            @endif
                            @if($readyCount > 0)
                                <span class="badge bg-soft-info text-info">{{ $readyCount }} Ready</span>
                            @endif
                            @if($waitingCount > 0)
                                <span class="erp-badge-draft">{{ $waitingCount }} Waiting</span>
                            @endif
                            @if($wcOps->count() === 0)
                                <span class="badge bg-soft-secondary text-secondary">No Jobs</span>
                            @endif
                        </div>
                    </div>

                    @if($wcOps->count() > 0)
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table">
                                <thead>
                                    <tr>
                                        <th style="width: 5%" class="text-center">Seq</th>
                                        <th style="width: 15%">Order #</th>
                                        <th style="width: 20%">Product</th>
                                        <th style="width: 18%">Operation</th>
                                        <th style="width: 10%">Machine</th>
                                        <th style="width: 12%">Planned Start</th>
                                        <th style="width: 12%">Planned Finish</th>
                                        <th style="width: 10%">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($wcOps->sortBy('planned_start') as $op)
                                        <tr class="{{ $op->status === 'running' ? 'table-warning' : '' }}">
                                            <td class="fw-bold text-center">{{ $op->sequence }}</td>
                                            <td>
                                                <a href="{{ route('production.schedules.show', $op->production_schedule_id) }}" class="fw-semibold text-primary">
                                                    {{ $op->order->order_number ?? '—' }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-dark fs-12">{{ $op->order->product->name ?? '—' }}</span>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-dark fs-12">{{ $op->orderOperation->name ?? '—' }}</span>
                                                <br><small class="text-muted font-monospace">{{ $op->orderOperation->operation_number ?? '' }}</small>
                                            </td>
                                            <td class="text-muted fs-12">{{ $op->machine->name ?? '—' }}</td>
                                            <td class="fs-12 text-muted">{{ $op->planned_start->format('d/m H:i') }}</td>
                                            <td class="fs-12 text-muted">{{ $op->planned_finish->format('d/m H:i') }}</td>
                                            <td>
                                                @if($op->status === 'running')
                                                    <span class="badge bg-soft-warning text-warning">Running</span>
                                                @elseif($op->status === 'ready')
                                                    <span class="badge bg-soft-info text-info">Ready</span>
                                                @elseif($op->status === 'waiting')
                                                    <span class="erp-badge-draft">Waiting</span>
                                                @else
                                                    <span class="badge bg-soft-secondary text-secondary text-capitalize">{{ $op->status }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    @else
                        <div class="text-center py-3 text-muted fs-13">
                            <i class="feather-inbox me-2"></i>No active operations queued for this Work Center.
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
@endsection
