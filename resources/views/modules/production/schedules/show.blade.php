@extends('layouts.duralux')

@section('title', __('production.schedule_details', ['number' => $schedule->schedule_number]) . ' | SaaS ERP')

@section('page-back-button')
    <x-ui.icon-btn href="{{ route('production.schedules.index') }}" icon="feather-arrow-left" variant="transparent-dark" title="{{ __('production.back_to_list') }}" />
@endsection

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button :href="route('production.schedules.dispatch-board', ['schedule_id' => $schedule->id])" variant="outline-primary" icon="feather-grid">
            {{ __('production.open_dispatch_board') }}
        </x-ui.button>

        <x-ui.button :href="route('production.schedules.change-history', $schedule->id)" variant="outline-secondary" icon="feather-clock">
            {{ __('production.history') }}
        </x-ui.button>

        @if($schedule->isScheduled())
            <x-ui.button type="button" onclick="openShowPreReleaseModal({{ $schedule->id }})" variant="primary" icon="feather-play-circle">
                {{ __('production.release_to_shop_floor') }}
            </x-ui.button>
        @endif

        @if(!$schedule->isFrozen())
            <x-ui.action-dropdown id="scheduleHeaderActionsDropdown">
                <li>
                    <a href="javascript:void(0)" class="dropdown-item py-1.5 fs-12" data-bs-toggle="modal" data-bs-target="#rescheduleStartModal">
                        <i class="feather-calendar me-2 text-warning fs-12"></i>{{ __('production.change_schedule_start_date') }}
                    </a>
                </li>
                <li>
                    <form method="POST" action="{{ route('production.schedules.cancel', $schedule->id) }}" onsubmit="return confirmFormSubmit(event, '{{ __('production.cancel_schedule_confirm', ['number' => $schedule->schedule_number]) }}', { title: '{{ __('production.cancel_schedule') }}', variant: 'danger', confirmButtonText: '{{ __('production.cancel_schedule') }}' });">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger py-1.5 fs-12">
                            <i class="feather-slash me-2 text-danger fs-12"></i>{{ __('production.cancel_schedule') }}
                        </button>
                    </form>
                </li>
            </x-ui.action-dropdown>
        @endif
    </div>
@endsection

@section('content')

    {{-- ── Schedule Workflow Guidance Component (Placed outside panel, matching mockup) ── --}}
    <x-ui.workflow-guide :title="__('production.whats_next')">
        @if($schedule->isScheduled())
            {!! __('production.schedule_workflow_guide_scheduled', [
                'order' => '<a href="' . route('production.orders.show', $schedule->production_order_id) . '" class="fw-bold text-primary text-decoration-underline">' . e($schedule->order->order_number ?? '') . '</a>',
                'release_link' => '<a href="javascript:void(0)" class="fw-bold text-primary text-decoration-underline" onclick="openShowPreReleaseModal(' . $schedule->id . ');">' . __('production.release_to_shop_floor') . '</a>'
            ]) !!}
        @elseif($schedule->isReleased() || $schedule->isInProgress())
            {!! __('production.schedule_workflow_guide_released', [
                'mes_link' => '<a href="' . route('production.mes.dashboard') . '" class="fw-bold text-primary text-decoration-underline me-1">' . __('production.step_shop_floor') . '</a>',
                'monitor_link' => '<a href="' . route('production.mes.work-centers.index') . '" class="fw-bold text-primary text-decoration-underline me-1">' . __('production.work_center_board') . '</a>',
                'operations_link' => '<a href="' . route('production.orders.show', ['order' => $schedule->production_order_id, 'tab' => 'vtab-operations']) . '" class="fw-bold text-primary text-decoration-underline">' . __('production.operations_routing') . '</a>'
            ]) !!}
        @else
            {{ __('production.schedule_status_notice', ['status' => ucfirst($schedule->status)]) }}
        @endif
    </x-ui.workflow-guide>

    <div class="erp-single-panel bg-white">

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <h4 class="fw-bold text-dark mb-0">
                {{ __('production.schedule_details', ['number' => $schedule->schedule_number]) }}
            </h4>
            <div>
                @if($schedule->status === 'released')
                    <span class="erp-badge-active">{{ __('production.released_schedules') }}</span>
                @elseif($schedule->status === 'in_progress')
                    <span class="badge bg-soft-warning text-warning">{{ __('production.in_progress_schedules') }}</span>
                @elseif($schedule->status === 'scheduled')
                    <span class="badge bg-soft-info text-info">{{ __('production.scheduled_schedules') }}</span>
                @elseif($schedule->status === 'draft')
                    <span class="erp-badge-draft">{{ __('production.draft_schedules') }}</span>
                @elseif($schedule->status === 'completed')
                    <span class="badge bg-soft-success text-success">{{ __('production.completed_schedules') }}</span>
                @elseif($schedule->status === 'cancelled')
                    <span class="badge bg-soft-danger text-danger">{{ __('production.cancelled_schedules') }}</span>
                @endif
            </div>
        </div>

        {{-- Summary Cards --}}
        @php
            $totalOps = $schedule->operations->count();
            $completedOps = $schedule->operations->where('status', 'completed')->count();
            $runningOps = $schedule->operations->where('status', 'running')->count();
            $remainingOps = $totalOps - $completedOps;
        @endphp
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-dark">{{ $totalOps }}</div>
                        <div class="fs-11 text-muted text-uppercase">{{ __('production.total_operations') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-success">{{ $completedOps }}</div>
                        <div class="fs-11 text-muted text-uppercase">{{ __('production.completed_schedules') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-warning">{{ $runningOps }}</div>
                        <div class="fs-11 text-muted text-uppercase">{{ __('production.running') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3 text-center">
                        <div class="fs-22 fw-bold text-info">{{ $remainingOps }}</div>
                        <div class="fs-11 text-muted text-uppercase">{{ __('production.remaining') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Info Grid --}}
        <div class="row g-4 mb-4">
            <div class="col-md-6 border-end">
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.production_order') }}:</span></div>
                    <div class="col-md-8">
                        <a href="{{ route('production.orders.show', $schedule->production_order_id) }}" class="fw-bold text-primary">
                            {{ $schedule->order->order_number ?? '—' }}
                        </a>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.product') }}:</span></div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">{{ $schedule->order->product->name ?? '—' }}</span>
                        <small class="text-muted ms-2 font-monospace">{{ $schedule->order->product->sku ?? '' }}</small>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.scheduling_type') }}:</span></div>
                    <div class="col-md-8">
                        <span class="badge bg-soft-info text-info text-capitalize">
                            {{ $schedule->scheduling_type === 'backward' ? __('production.backward_jit') : __('production.forward') }}
                        </span>
                    </div>
                </div>
                @if($schedule->order && $schedule->order->end_date)
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.due_date_target') }}:</span></div>
                        <div class="col-md-8">
                            <span class="fw-bold text-dark font-monospace fs-13">{{ \Illuminate\Support\Carbon::parse($schedule->order->end_date)->format('d/m/Y') }}</span>
                        </div>
                    </div>
                @endif
                @if($schedule->notes)
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.description') ?? 'Notes' }}:</span></div>
                        <div class="col-md-8"><span class="text-dark fs-13">{{ $schedule->notes }}</span></div>
                    </div>
                @endif
            </div>
            <div class="col-md-6">
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.created_by') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $schedule->creator->name ?? '—' }}</span></div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.scheduled_at') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fs-13">{{ $schedule->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.source') }}:</span></div>
                    <div class="col-md-8">
                        <span class="badge bg-soft-secondary text-secondary text-uppercase">{{ $schedule->generated_by === 'forward' ? __('production.forward') : ($schedule->generated_by ?? 'forward') }}</span>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.capacity_utilization') }}:</span></div>
                    <div class="col-md-8">
                        <span class="fw-bold {{ ($schedule->capacity_utilization ?? 0) > 85 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($schedule->capacity_utilization ?? 0, 2) }}%
                        </span>
                    </div>
                </div>
                @if($schedule->released_at)
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.released_schedules') }}:</span></div>
                        <div class="col-md-8"><span class="text-dark fs-13">{{ $schedule->released_at->format('d/m/Y H:i') }} by {{ $schedule->releasedBy->name ?? '—' }}</span></div>
                    </div>
                @endif
                @if($schedule->completed_at)
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.completed_schedules') }}:</span></div>
                        <div class="col-md-8"><span class="text-success fw-bold fs-13">{{ $schedule->completed_at->format('d/m/Y H:i') }}</span></div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Capacity Warnings --}}
        @if(count($warnings) > 0)
            <div class="alert alert-warning border-warning bg-soft-warning p-3 rounded mb-4">
                <div class="fw-bold text-warning mb-2"><i class="feather-alert-triangle me-2"></i>{{ __('production.capacity_overload_warnings') }} ({{ count($warnings) }})</div>
                <div style="max-height: 180px; overflow-y: auto; padding-right: 8px;" class="warning-scrollbar-custom">
                    @foreach($warnings as $warning)
                        <div class="fs-12 text-warning-800 mb-1">• {{ $warning }}</div>
                    @endforeach
                </div>
            </div>

            <style>
                .warning-scrollbar-custom::-webkit-scrollbar {
                    width: 6px;
                }
                .warning-scrollbar-custom::-webkit-scrollbar-track {
                    background: rgba(245, 158, 11, 0.05);
                    border-radius: 4px;
                }
                .warning-scrollbar-custom::-webkit-scrollbar-thumb {
                    background: rgba(245, 158, 11, 0.3);
                    border-radius: 4px;
                }
                .warning-scrollbar-custom::-webkit-scrollbar-thumb:hover {
                    background: rgba(245, 158, 11, 0.5);
                }
            </style>
        @endif

        {{-- Tabs --}}
        <x-ui.horizontal-tabs id="scheduleTabs" :tabs="[
        ['id' => 'tab-operations', 'label' => __('production.schedule_operations'), 'active' => true, 'icon' => 'feather-list'],
        ['id' => 'tab-capacity', 'label' => __('production.capacity_analysis'), 'active' => false, 'icon' => 'feather-activity'],
    ]" />

        <div class="tab-content mt-3">
            <div class="tab-pane fade show active" id="tab-operations" role="tabpanel">
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th style="width: 5%" class="text-center">{{ __('production.seq') }}</th>
                                <th style="width: 14%">{{ __('production.operations') }}</th>
                                <th style="width: 11%">{{ __('production.work_centers') }}</th>
                                <th style="width: 11%">{{ __('production.planned_machine') ?? 'Planned Machine' }}</th>
                                <th style="width: 10%">{{ __('production.actual_machine') }}</th>
                                <th style="width: 7%">{{ __('production.status') }}</th>
                                <th style="width: 8%">{{ __('production.duration') }}</th>
                                <th style="width: 9%">{{ __('production.planned_start') }}</th>
                                <th style="width: 9%">{{ __('production.planned_finish') }}</th>
                                <th style="width: 14%">Queue Threshold</th>
                                <th style="width: 10%">{{ __('production.gantt_config') }}</th>
                                <th style="width: 4%" class="text-center">{{ __('production.lock') }}</th>
                                <th style="width: 8%">{{ __('production.warnings') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schedule->operations->sortBy('planned_start') as $op)
                                <tr class="{{ $op->locked ? 'bg-light' : '' }}">
                                    <td class="fw-bold text-center align-middle">{{ $op->sequence }}</td>
                                    <td class="align-middle">
                                        <span class="fw-semibold text-dark">{{ $op->orderOperation->name ?? '—' }}</span>
                                        <br><small class="text-muted font-monospace">{{ $op->orderOperation->operation_number ?? '' }}</small>
                                        @php
                                            $schedOpIsQc = (bool) ($op->orderOperation?->quality_required || ($op->orderOperation?->routingOperation?->quality_required ?? false));
                                        @endphp
                                        @if($schedOpIsQc)
                                            <span class="badge bg-soft-info text-info border border-info-subtle font-monospace mt-1 ms-1">
                                                <i class="feather-shield me-1"></i>QC REQUIRED
                                            </span>
                                        @endif
                                        @if($op->orderOperation && $op->orderOperation->is_external)
                                            @php
                                                $isWipJobWork = $op->orderOperation->isWipJobWork();
                                                $supplyType = $op->orderOperation->material_supply_type ?? 'company_supplied';
                                                $isVendorSupplied = ($supplyType === 'vendor_supplied');
                                            @endphp
                                            <br><span class="badge {{ $isWipJobWork ? 'bg-soft-primary text-primary border border-primary-subtle' : ($isVendorSupplied ? 'bg-soft-info text-info border border-info-subtle' : 'bg-soft-warning text-dark border border-warning') }} font-monospace mt-1"><i class="feather-external-link me-1"></i>SUBCONTRACT ({{ $isWipJobWork ? 'Previous Op WIP Job Work' : ($isVendorSupplied ? 'Vendor Supplied' : 'Company Supplied') }}) — {{ $op->orderOperation->vendor->name ?? 'Vendor' }}</span>
                                            <br><small class="text-warning font-monospace fs-11">Lead: {{ $op->orderOperation->subcontract_lead_time_days ?? 0 }}d | Buffer: {{ $op->orderOperation->dispatch_buffer_days ?? 0 }}d / {{ $op->orderOperation->return_buffer_days ?? 0 }}d</small>
                                        @endif
                                    </td>
                                    <td class="align-middle">{{ $op->workCenter->name ?? '—' }}</td>
                                    <td class="align-middle text-muted">
                                        {{ $op->machine->name ?? '—' }}
                                        @if(($op->priority ?? 1) > 1)
                                            <span class="badge bg-soft-warning text-warning d-block mt-1 text-center" style="width: max-content">Alt Priority {{ $op->priority }}</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-muted">
                                        {{ $op->actualMachine->name ?? '—' }}
                                    </td>
                                    <td class="align-middle">
                                        @if($op->status === 'completed')
                                            <span class="badge bg-soft-success text-success">{{ __('production.completed') }}</span>
                                        @elseif($op->status === 'running')
                                            <span class="badge bg-soft-warning text-warning">{{ __('production.running') }}</span>
                                        @elseif($op->status === 'ready')
                                            <span class="badge bg-soft-info text-info">{{ __('production.ready') }}</span>
                                        @elseif($op->status === 'paused')
                                            <span class="badge bg-soft-warning text-warning">{{ __('production.paused') ?? 'Paused' }}</span>
                                        @elseif($op->status === 'waiting')
                                            <span class="erp-badge-draft">{{ __('production.waiting') }}</span>
                                        @elseif($op->status === 'cancelled')
                                            <span class="badge bg-soft-danger text-danger">{{ __('production.cancelled') }}</span>
                                        @else
                                            <span class="erp-badge-draft text-uppercase">{{ $op->status }}</span>
                                        @endif
                                    </td>
                                    <td class="align-middle fs-11 text-dark fw-semibold">{{ number_format($op->planned_duration_minutes, 1) }} mins</td>
                                    <td class="align-middle fs-11 text-muted">{{ $op->planned_start->format('d/m H:i') }}</td>
                                    <td class="align-middle fs-11 text-muted">{{ $op->planned_finish->format('d/m H:i') }}</td>
                                    
                                    {{-- Dedicated Queue Threshold Column --}}
                                    <td class="align-middle fs-11">
                                        @if($op->orderOperation && ($op->orderOperation->queue_threshold_enabled ?? $op->orderOperation->overlap_enabled))
                                            @php
                                                $trTime = app(\App\Domains\Production\Services\SchedulingService::class)->calculateTransferReadyAt(
                                                    $op->orderOperation,
                                                    $op->planned_start,
                                                    (float) ($schedule->order?->quantity_ordered ?? 1)
                                                );
                                            @endphp
                                            <span class="badge bg-soft-info text-info border font-monospace d-inline-flex align-items-center gap-1 mb-1">
                                                ⚡ Queue Threshold Enabled
                                            </span>
                                            <div class="text-dark font-monospace fs-11 fw-semibold">Batch: {{ (float) $op->orderOperation->transfer_batch_quantity }} | Lag: {{ (int) $op->orderOperation->transfer_lag_minutes }}m</div>
                                            <small class="text-primary font-monospace d-block fs-10">Transfer-Ready: {{ $trTime->format('d/m/Y H:i') }}</small>
                                        @else
                                            <span class="badge bg-soft-secondary text-muted font-monospace border fs-10">Disabled</span>
                                        @endif
                                    </td>
                                    <td class="align-middle fs-11 text-muted">
                                        <strong>Lane:</strong> {{ $op->lane ?? 'N/A' }}<br>
                                        <strong>Res:</strong> {{ $op->resource_id ?? 'N/A' }}
                                    </td>
                                    <td class="align-middle text-center">
                                        @if($op->locked)
                                            <span class="text-danger" title="Locked Operation"><i class="feather-lock"></i></span>
                                        @else
                                            <span class="text-muted" title="Unlocked"><i class="feather-unlock"></i></span>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        @if($op->warnings && count($op->warnings) > 0)
                                            @php
                                                $renderedWarnings = [];
                                            @endphp
                                            @foreach($op->warnings as $warn)
                                                @php
                                                    $warnCode = $warn['code'] ?? '';
                                                    $warnMsg = $warn['message'] ?? '';
                                                    $warnKey = $warnCode . '_' . $warnMsg;
                                                @endphp
                                                @if(!in_array($warnKey, $renderedWarnings))
                                                    @php
                                                        $renderedWarnings[] = $warnKey;
                                                    @endphp
                                                    <span class="badge bg-soft-danger text-danger d-block mb-1 font-monospace" style="font-size: 10px;" title="{{ $warnMsg }}">
                                                        {{ $warnCode }}
                                                    </span>
                                                @endif
                                            @endforeach
                                        @else
                                            <span class="text-success fs-12"><i class="feather-check-circle"></i> {{ __('production.clean') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center py-4 text-muted">
                                        <i class="feather-info me-2"></i>{{ __('production.no_operations_scheduled') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-capacity" role="tabpanel">
                <style>
                    .transition-icon {
                        transition: transform 0.2s ease-in-out;
                        display: inline-block;
                    }
                    tr[aria-expanded="true"] .transition-icon {
                        transform: rotate(90deg);
                    }
                    .hover-bg-light:hover {
                        background-color: rgba(0, 0, 0, 0.03) !important;
                    }

                    /* Capacity Info Box */
                    .capacity-info-box {
                        background-color: color-mix(in srgb, var(--bs-primary) 8%, #ffffff);
                        border-left: 4px solid var(--bs-primary) !important;
                        color: #1e293b;
                    }

                    /* Capacity Grouping Bar */
                    .capacity-grouping-bar {
                        background-color: #f8fafc;
                        border: 1px solid #e2e8f0 !important;
                    }

                    .capacity-expanded-cell {
                        background-color: #f8fafc;
                    }

                    .capacity-breakdown-card {
                        background-color: #ffffff;
                        border: 1px solid #e2e8f0 !important;
                    }

                    .capacity-breakdown-row {
                        background-color: #ffffff;
                    }

                    .capacity-breakdown-row.is-overloaded {
                        background-color: #fff5f5;
                    }

                    /* Dark Mode Overrides */
                    html.app-skin-dark .capacity-info-box,
                    body.app-skin-dark .capacity-info-box,
                    [data-bs-theme="dark"] .capacity-info-box,
                    [data-theme="dark"] .capacity-info-box {
                        background-color: color-mix(in srgb, var(--bs-primary) 15%, #162038) !important;
                        border-left: 4px solid var(--bs-primary) !important;
                        color: #cbd5e1 !important;
                    }

                    html.app-skin-dark .capacity-info-box h6,
                    body.app-skin-dark .capacity-info-box h6,
                    [data-bs-theme="dark"] .capacity-info-box h6,
                    [data-theme="dark"] .capacity-info-box h6 {
                        color: var(--bs-primary) !important;
                    }

                    html.app-skin-dark .capacity-info-box p,
                    body.app-skin-dark .capacity-info-box p,
                    [data-bs-theme="dark"] .capacity-info-box p,
                    [data-theme="dark"] .capacity-info-box p {
                        color: #94a3b8 !important;
                    }

                    html.app-skin-dark .capacity-info-box .text-dark,
                    body.app-skin-dark .capacity-info-box .text-dark,
                    [data-bs-theme="dark"] .capacity-info-box .text-dark,
                    [data-theme="dark"] .capacity-info-box .text-dark {
                        color: #f1f5f9 !important;
                    }

                    html.app-skin-dark .capacity-grouping-bar,
                    body.app-skin-dark .capacity-grouping-bar,
                    [data-bs-theme="dark"] .capacity-grouping-bar,
                    [data-theme="dark"] .capacity-grouping-bar {
                        background-color: #162038 !important;
                        border-color: #283c50 !important;
                        color: #cbd5e1 !important;
                    }

                    html.app-skin-dark .capacity-grouping-bar .text-dark,
                    body.app-skin-dark .capacity-grouping-bar .text-dark,
                    [data-bs-theme="dark"] .capacity-grouping-bar .text-dark,
                    [data-theme="dark"] .capacity-grouping-bar .text-dark {
                        color: #f1f5f9 !important;
                    }

                    html.app-skin-dark .capacity-expanded-cell,
                    body.app-skin-dark .capacity-expanded-cell,
                    [data-bs-theme="dark"] .capacity-expanded-cell,
                    [data-theme="dark"] .capacity-expanded-cell {
                        background-color: #111a2e !important;
                    }

                    html.app-skin-dark .capacity-breakdown-card,
                    body.app-skin-dark .capacity-breakdown-card,
                    [data-bs-theme="dark"] .capacity-breakdown-card,
                    [data-theme="dark"] .capacity-breakdown-card {
                        background-color: #0f172a !important;
                        border-color: #1e293b !important;
                        color: #cbd5e1 !important;
                    }

                    html.app-skin-dark .capacity-breakdown-card .table thead,
                    body.app-skin-dark .capacity-breakdown-card .table thead,
                    [data-bs-theme="dark"] .capacity-breakdown-card .table thead,
                    [data-theme="dark"] .capacity-breakdown-card .table thead {
                        background-color: #162038 !important;
                        border-color: #283c50 !important;
                        color: #cbd5e1 !important;
                    }

                    html.app-skin-dark .capacity-breakdown-card .table thead th,
                    body.app-skin-dark .capacity-breakdown-card .table thead th,
                    [data-bs-theme="dark"] .capacity-breakdown-card .table thead th,
                    [data-theme="dark"] .capacity-breakdown-card .table thead th {
                        background-color: #162038 !important;
                        border-color: #283c50 !important;
                        color: #f1f5f9 !important;
                    }

                    html.app-skin-dark .capacity-breakdown-card .table td,
                    body.app-skin-dark .capacity-breakdown-card .table td,
                    [data-bs-theme="dark"] .capacity-breakdown-card .table td,
                    [data-theme="dark"] .capacity-breakdown-card .table td {
                        border-color: #283c50 !important;
                    }

                    html.app-skin-dark .capacity-breakdown-row,
                    body.app-skin-dark .capacity-breakdown-row,
                    [data-bs-theme="dark"] .capacity-breakdown-row,
                    [data-theme="dark"] .capacity-breakdown-row {
                        background-color: #0f172a !important;
                        color: #cbd5e1 !important;
                    }

                    html.app-skin-dark .capacity-breakdown-row.is-overloaded,
                    body.app-skin-dark .capacity-breakdown-row.is-overloaded,
                    [data-bs-theme="dark"] .capacity-breakdown-row.is-overloaded,
                    [data-theme="dark"] .capacity-breakdown-row.is-overloaded {
                        background-color: rgba(239, 68, 68, 0.18) !important;
                        color: #fca5a5 !important;
                    }

                    html.app-skin-dark .hover-bg-light:hover,
                    body.app-skin-dark .hover-bg-light:hover,
                    [data-bs-theme="dark"] .hover-bg-light:hover,
                    [data-theme="dark"] .hover-bg-light:hover {
                        background-color: rgba(255, 255, 255, 0.05) !important;
                    }
                </style>
                <div class="capacity-info-box alert border-0 shadow-sm d-flex align-items-start mb-4 p-3 rounded fs-13">
                    <i class="feather-info me-3 fs-20 text-primary mt-1"></i>
                    <div>
                        <h6 class="fw-bold text-primary mb-1">{{ __('production.understanding_work_center_capacity') }}</h6>
                        <p class="mb-0 text-muted fs-12">
                            {{ __('production.understanding_work_center_capacity_desc') }}
                        </p>
                    </div>
                </div>
                <div class="capacity-grouping-bar d-flex justify-content-between align-items-center mb-4 p-2 rounded border shadow-none">
                    <div class="text-muted fs-12 ms-2">
                        @php
                            $currentGroup = $capacityDetails[0]['group_type'] ?? 'day';
                            $currentGroupLabel = $currentGroup === 'week' ? __('production.week') : ($currentGroup === 'month' ? __('production.month') : __('production.day'));
                        @endphp
                        {{ __('production.showing_breakdown_grouped_by') }}: <strong class="text-capitalize text-dark">{{ $currentGroupLabel }}</strong>
                    </div>
                    <div class="btn-group btn-group-sm" style="gap:10px;" role="group" aria-label="Capacity grouping">
                        <a href="{{ request()->fullUrlWithQuery(['group_by' => 'day']) }}" class="btn btn-outline-primary {{ $currentGroup === 'day' ? 'active' : '' }}">
                            {{ __('production.day') }}
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['group_by' => 'week']) }}" class="btn btn-outline-primary {{ $currentGroup === 'week' ? 'active' : '' }}">
                            {{ __('production.week') }}
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['group_by' => 'month']) }}" class="btn btn-outline-primary {{ $currentGroup === 'month' ? 'active' : '' }}">
                            {{ __('production.month') }}
                        </a>
                    </div>
                </div>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th>{{ __('production.work_centers') }}</th>
                                <th>{{ __('production.active_shifts') }}</th>
                                <th class="text-center">{{ __('production.resource_count') }}</th>
                                <th class="text-end">{{ __('production.scheduled_time') }}</th>
                                <th class="text-end">{{ __('production.available_capacity') }}</th>
                                <th>{{ __('production.utilization_percent') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($capacityDetails as $detail)
                                <tr data-bs-toggle="collapse" data-bs-target="#collapse-wc-{{ $detail['work_center']->id }}" style="cursor: pointer;" class="hover-bg-light">
                                    <td class="fw-bold align-middle">
                                        <i class="feather-chevron-right me-2 text-muted transition-icon" id="arrow-wc-{{ $detail['work_center']->id }}"></i>
                                        {{ $detail['work_center']->name }}
                                        <br><small class="text-muted">Calendar: {{ $detail['calendar_name'] }} ({{ $detail['working_days'] }})</small>
                                    </td>
                                    <td class="align-middle">
                                        {{ $detail['shifts'] }}
                                    </td>
                                    <td class="align-middle text-center">
                                        <span class="badge bg-soft-info text-info">{{ __('production.active_machines', ['count' => $detail['active_machines']]) }}</span>
                                    </td>
                                    <td class="align-middle text-end font-monospace">{{ number_format($detail['scheduled_minutes'], 1) }} mins</td>
                                    <td class="align-middle text-end font-monospace">{{ number_format($detail['capacity_minutes'], 1) }} mins</td>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            <span class="fw-bold me-2 {{ $detail['utilization'] > 85 ? 'text-danger' : 'text-success' }}">
                                                {{ number_format($detail['utilization'], 2) }}%
                                            </span>
                                            <div class="progress flex-grow-1" style="height: 6px; min-width: 100px;">
                                                <div class="progress-bar {{ $detail['utilization'] > 85 ? 'bg-danger' : 'bg-success' }}" 
                                                     role="progressbar" 
                                                     style="width: {{ $detail['utilization'] }}%" 
                                                     aria-valuenow="{{ $detail['utilization'] }}" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="collapse" id="collapse-wc-{{ $detail['work_center']->id }}">
                                    <td colspan="6" class="p-3 capacity-expanded-cell">
                                        <div class="capacity-breakdown-card card card-body shadow-sm p-4 rounded">
                                            @php
                                                $groupType = $detail['group_type'] ?? 'day';
                                                $headerTitle = $groupType === 'week' ? 'Weekly' : ($groupType === 'month' ? 'Monthly' : 'Day-by-Day');
                                                $col1Label = $groupType === 'week' ? 'Date Range' : ($groupType === 'month' ? 'Month' : 'Scheduled Date');
                                                $col2Label = $groupType === 'week' ? 'Week Number' : ($groupType === 'month' ? 'Period' : 'Day of Week');
                                            @endphp
                                            <h6 class="fw-bold text-dark fs-13 mb-3">
                                                <i class="feather-calendar me-2 text-primary"></i>{{ $headerTitle }} Capacity Breakdown for {{ $detail['work_center']->name }}
                                            </h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered fs-12 mb-0">
                                                    <thead class="table-light text-dark fw-bold">
                                                        <tr>
                                                            <th>{{ $col1Label }}</th>
                                                            <th>{{ $col2Label }}</th>
                                                            <th class="text-end">{{ __('production.scheduled_minutes') }}</th>
                                                            <th class="text-end">{{ __('production.available_capacity') }}</th>
                                                            <th class="text-end">{{ __('production.utilization_percent') }}</th>
                                                            <th>{{ __('production.capacity_status') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($detail['daily_breakdown'] as $day)
                                                            @php
                                                                $isOverloaded = $day['scheduled_minutes'] > $day['capacity_minutes'];
                                                                $isSundayOverload = $day['capacity_minutes'] == 0 && $day['scheduled_minutes'] > 0;
                                                            @endphp
                                                            <tr class="capacity-breakdown-row {{ $isOverloaded ? 'is-overloaded' : '' }}">
                                                                <td class="font-monospace align-middle">{{ $day['date'] }}</td>
                                                                <td class="align-middle fw-medium">{{ $day['day_name'] }}</td>
                                                                <td class="text-end align-middle font-monospace">{{ number_format($day['scheduled_minutes'], 1) }} mins</td>
                                                                <td class="text-end align-middle font-monospace">{{ number_format($day['capacity_minutes'], 1) }} mins</td>
                                                                <td class="text-end align-middle font-monospace fw-bold {{ $isOverloaded ? 'text-danger' : 'text-success' }}">
                                                                    {{ number_format($day['utilization'], 2) }}%
                                                                </td>
                                                                <td class="align-middle">
                                                                    @if($isSundayOverload)
                                                                        <span class="badge bg-soft-danger text-danger border border-danger-subtle"><i class="feather-alert-triangle me-1"></i>{{ __('production.overloaded_non_working_day') }}</span>
                                                                    @elseif($isOverloaded)
                                                                        <span class="badge bg-soft-danger text-danger border border-danger-subtle"><i class="feather-alert-octagon me-1"></i>{{ __('production.overloaded') }}</span>
                                                                    @elseif($day['capacity_minutes'] == 0)
                                                                        <span class="badge bg-soft-secondary text-secondary border border-secondary-subtle">{{ __('production.closed') }}</span>
                                                                    @else
                                                                        <span class="badge bg-soft-success text-success border border-success-subtle">{{ __('production.normal_capacity') }}</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="feather-info me-2"></i>{{ __('production.no_capacity_data') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>
        </div>
    </div>

    {{-- Cancel Modal --}}
    <x-ui.modal id="cancelModal" :title="__('production.cancel_schedule')" class="text-start">
        <form method="POST" action="{{ route('production.schedules.cancel', $schedule->id) }}" id="cancelFormMain">
            @csrf
            <p class="fs-13 text-muted">{{ __('production.cancel_schedule_confirm', ['number' => $schedule->schedule_number]) }}</p>
        </form>
        <x-slot name="footer">
            <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal">{{ __('production.back') ?? 'Back' }}</x-ui.button>
            <x-ui.button type="submit" variant="danger" onclick="document.getElementById('cancelFormMain').submit();">{{ __('production.cancel_schedule') }}</x-ui.button>
        </x-slot>
    </x-ui.modal>

    {{-- Reschedule Start Modal --}}
    {{-- Reschedule Start Modal --}}
    <x-ui.modal id="rescheduleStartModal" :title="__('production.change_schedule_start_date')" class="text-start">
        <form method="POST" action="{{ route('production.schedules.reschedule-start', $schedule->id) }}" id="rescheduleStartFormMain">
            @csrf
            <div class="mb-3 text-dark">
                <label class="form-label fw-bold fs-12 mb-1">{{ __('production.new_start_date_time') }}</label>
                <input type="datetime-local" name="start_date" class="form-control fs-13" value="{{ $schedule->operations->min('planned_start')?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i') }}" required>
                <small class="text-muted mt-2 d-block fs-11">
                    {{ __('production.reschedule_start_date_help') }}
                </small>
            </div>
        </form>
        <x-slot name="footer">
            <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal">{{ __('production.cancel') ?? 'Cancel' }}</x-ui.button>
            <x-ui.button type="submit" variant="warning" onclick="document.getElementById('rescheduleStartFormMain').submit();">{{ __('production.apply_recalculate') }}</x-ui.button>
        </x-slot>
    </x-ui.modal>

    {{-- Pre-Release Validation Modal --}}
    <x-ui.modal id="showPreReleaseModal" :title="__('production.schedule_pre_release_validation')" size="lg" centered="true">
        <div id="showPreReleaseModalBody" class="text-start">
            <div class="text-center py-4 text-muted">
                <div class="spinner-border spinner-border-sm me-2 text-info"></div>
                {{ __('production.running_pre_release_checks') }}
            </div>
        </div>
        <x-slot name="footer">
            <div id="showPreReleaseModalFooter" class="d-flex align-items-center justify-content-end gap-2 w-100">
                <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal">{{ __('production.close') }}</x-ui.button>
            </div>
        </x-slot>
    </x-ui.modal>

    <script>
    function openShowPreReleaseModal(scheduleId) {
        const modalEl = document.getElementById('showPreReleaseModal');
        const modalBody = document.getElementById('showPreReleaseModalBody');
        const modalFooter = document.getElementById('showPreReleaseModalFooter');
        
        modalBody.innerHTML = `<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2 text-info"></div> {{ __('production.running_pre_release_checks') }}</div>`;
        
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();

        fetch(`{{ url('production/schedules') }}/${scheduleId}/pre-release-check`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => r.json())
        .then(res => {
            let errorsHtml = '';
            let warningsHtml = '';

            if (res.errors && res.errors.length > 0) {
                errorsHtml = `
                    <div class="alert alert-danger border-danger p-3 mb-3">
                        <h6 class="fw-bold text-danger mb-2"><i class="feather-x-circle me-1"></i> {{ __('production.blocking_errors') }} (${res.errors.length})</h6>
                        <ul class="mb-0 ps-3">
                            ${res.errors.map(e => `<li><strong>${e.code}:</strong> ${e.message}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }

            if (res.warnings && res.warnings.length > 0) {
                warningsHtml = `
                    <div class="alert alert-warning border-warning p-3 mb-3">
                        <h6 class="fw-bold text-warning-dark mb-2"><i class="feather-alert-triangle me-1"></i> {{ __('production.warnings') }} (${res.warnings.length})</h6>
                        <ul class="mb-0 ps-3">
                            ${res.warnings.map(w => `<li><strong>${w.code}:</strong> ${w.message}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }

            if (!errorsHtml && !warningsHtml) {
                errorsHtml = `<div class="alert alert-success border-success p-3 mb-3"><i class="feather-check-circle me-1"></i> {{ __('production.schedule_pre_release_clean_success') }}</div>`;
            }

            modalBody.innerHTML = `
                <div class="d-flex align-items-center justify-content-between mb-3 p-3 bg-light rounded border">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">{{ __('production.schedule_pre_release_summary') }}</h6>
                        <span class="text-muted fs-11">${res.summary?.total_operations ?? 0} {{ __('production.operations_evaluated') }}</span>
                    </div>
                    <div>
                        ${res.can_release ? '<span class="badge bg-success">{{ __("production.can_release") }}</span>' : '<span class="badge bg-danger">{{ __("production.blocked") }}</span>'}
                        ${res.has_warnings ? '<span class="badge bg-warning text-dark ms-1">{{ __("production.has_warnings") }}</span>' : ''}
                    </div>
                </div>
                ${errorsHtml}
                ${warningsHtml}
            `;

            if (res.can_release) {
                const confirmBtn = res.has_warnings
                    ? `<button type="button" onclick="executeShowScheduleRelease(${scheduleId}, true)" class="btn btn-warning btn-sm"><i class="feather-play me-1"></i> {{ __('production.release_schedule_with_warnings') }}</button>`
                    : `<button type="button" onclick="executeShowScheduleRelease(${scheduleId}, false)" class="btn btn-primary btn-sm"><i class="feather-play me-1"></i> {{ __('production.release_schedule_to_shop_floor') }}</button>`;
                modalFooter.innerHTML = `<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('production.close') }}</button> ${confirmBtn}`;
            } else {
                modalFooter.innerHTML = `<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('production.close') }}</button><button class="btn btn-danger btn-sm" disabled>{{ __('production.release_disabled_blocking_errors') }}</button>`;
            }
        })
        .catch(err => {
            modalBody.innerHTML = `<div class="alert alert-danger p-3 mb-0">Error running pre-release check: ${err.message}</div>`;
        });
    }

    function executeShowScheduleRelease(scheduleId, confirmWarnings) {
        fetch(`{{ url('production/schedules') }}/${scheduleId}/release`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                confirm_warnings: confirmWarnings ? 1 : 0
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                window.location.href = res.redirect_url || "{{ route('production.mes.dashboard') }}";
            } else {
                alert(res.message || 'Failed to release schedule.');
            }
        })
        .catch(err => {
            alert('Error releasing schedule: ' + err.message);
        });
    }
    </script>
@endsection
