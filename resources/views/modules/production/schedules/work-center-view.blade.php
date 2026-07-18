@extends('layouts.duralux')

@section('title', __('production.work_center_view') . ' | SaaS ERP')
@section('page-title', __('production.work_center_view'))
@section('breadcrumb', __('production.work_center_view'))

@section('page-actions')
    <a href="{{ route('production.schedules.index') }}" class="btn btn-secondary me-2">
        <i class="feather-list me-2"></i>{{ __('production.plans_list') ?? 'List View' }}
    </a>
    <a href="{{ route('production.schedules.calendar') }}" class="btn btn-light me-2">
        <i class="feather-calendar me-2"></i>{{ __('production.calendar_view') }}
    </a>
    <a href="{{ route('production.mes.work-centers.index') }}" class="btn btn-light">
        <i class="feather-monitor me-2"></i>{{ __('production.mes_dashboard') ?? 'MES Dashboard' }}
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        @if($workCenters->count() === 0)
            <div class="text-center py-5 text-muted">
                <i class="feather-grid fs-36 mb-3 d-block"></i>
                <p class="fs-14">{{ __('production.no_active_work_centers') }}</p>
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
                                <span class="badge bg-soft-warning text-warning">{{ $runningCount }} {{ __('production.running') }}</span>
                            @endif
                            @if($readyCount > 0)
                                <span class="badge bg-soft-info text-info">{{ $readyCount }} {{ __('production.ready') }}</span>
                            @endif
                            @if($waitingCount > 0)
                                <span class="erp-badge-draft">{{ $waitingCount }} {{ __('production.waiting') }}</span>
                            @endif
                            @if($wcOps->count() === 0)
                                <span class="badge bg-soft-secondary text-secondary">{{ __('production.no_jobs') }}</span>
                            @endif
                        </div>
                    </div>

                    @if($wcOps->count() > 0)
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table">
                                <thead>
                                    <tr>
                                        <th style="width: 5%" class="text-center">{{ __('production.seq') }}</th>
                                        <th style="width: 15%">{{ __('production.production_order') }} #</th>
                                        <th style="width: 20%">{{ __('production.product') }}</th>
                                        <th style="width: 18%">{{ __('production.operations') }}</th>
                                        <th style="width: 10%">{{ __('production.assigned_machine') ?? 'Machine' }}</th>
                                        <th style="width: 12%">{{ __('production.planned_start') }}</th>
                                        <th style="width: 12%">{{ __('production.planned_finish') }}</th>
                                        <th style="width: 10%">{{ __('production.status') }}</th>
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
                                                    <span class="badge bg-soft-warning text-warning">{{ __('production.running') }}</span>
                                                @elseif($op->status === 'ready')
                                                    <span class="badge bg-soft-info text-info">{{ __('production.ready') }}</span>
                                                @elseif($op->status === 'waiting')
                                                    <span class="erp-badge-draft">{{ __('production.waiting') }}</span>
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
                            <i class="feather-inbox me-2"></i>{{ __('production.no_active_ops_wc') }}
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
@endsection
