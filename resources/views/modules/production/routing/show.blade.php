@extends('layouts.duralux')

@section('title', __('production.routing_details') . ' | SaaS ERP')
@section('page-title', __('production.routing_details'))
@section('breadcrumb', $routing->routing_number)

@section('page-actions')
    <div class="d-flex gap-2">
        <x-ui.button :href="route('production.routing.index')" variant="secondary" icon="feather-arrow-left">
            {{ __('production.back_to_list') }}
        </x-ui.button>
        
        @if ($routing->isDraft())
            @can('update', $routing)
                <x-ui.button :href="route('production.routing.edit', $routing->id)" variant="primary" icon="feather-edit">
                    {{ __('production.edit_draft') }}
                </x-ui.button>
            @endcan

            @can('delete', $routing)
                <form id="delete-draft-routing-header-form" action="{{ route('production.routing.destroy', $routing->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="button" variant="danger" icon="feather-trash-2" onclick="deleteDraftRouting()">
                        {{ __('production.delete') }}
                    </x-ui.button>
                </form>
            @endcan
        @endif

        @can('duplicate', $routing)
            <x-ui.button type="button" variant="light" icon="feather-copy" data-bs-toggle="modal" data-bs-target="#duplicateVersionModal">
                {{ __('production.duplicate_routing_version') }}
            </x-ui.button>
        @endcan
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">

        <!-- Workflow Banner (Inlined inside panel) -->
        <div class="p-3 border rounded bg-light mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-brand text-white me-3 rounded-circle" style="width: 40px; height: 40px; font-size: 16px; display: flex; align-items: center; justify-content: center;">
                    <i class="feather-info"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">{{ __('production.routing_lifecycle_status') }}</h6>
                    <p class="fs-12 mb-0 text-muted">
                        {{ __('production.status') }}: 
                        @if ($routing->isDraft())
                            <span class="badge bg-soft-secondary text-secondary font-monospace fw-bold text-uppercase">{{ __('production.draft') }}</span>
                            — {{ __('production.draft_desc') }}
                        @elseif ($routing->isPendingApproval())
                            <span class="badge bg-soft-warning text-warning font-monospace fw-bold text-uppercase">{{ __('production.pending_approval') }}</span>
                            — {{ __('production.pending_desc') }}
                        @elseif ($routing->isScheduled())
                            <span class="badge bg-soft-warning text-warning font-monospace fw-bold text-uppercase">{{ __('production.scheduled') }}</span>
                            — Approved on {{ $routing->approved_at ? $routing->approved_at->format('Y-m-d') : '-' }}. Scheduled to take effect on {{ $routing->effective_from ? $routing->effective_from->format('Y-m-d') : '-' }}.
                        @elseif ($routing->isHistorical())
                            <span class="badge bg-soft-info text-info font-monospace fw-bold text-uppercase">{{ __('production.historical') }}</span>
                            — {{ __('production.historical_desc') }}
                        @elseif ($routing->isActive())
                            <span class="badge bg-soft-success text-success font-monospace fw-bold text-uppercase">{{ __('production.active') }}</span>
                            — {{ __('production.active_desc') }}
                        @else
                            <span class="badge bg-soft-danger text-danger font-monospace fw-bold text-uppercase">{{ __('production.cancelled') }}</span>
                            — {{ __('production.cancelled_desc') }}
                        @endif
                    </p>
                </div>
            </div>

            <!-- Workflow Actions -->
            <div class="d-flex gap-2">
                @if ($routing->isDraft())
                    @can('submit', $routing)
                        <form action="{{ route('production.routing.submit', $routing->id) }}" method="POST">
                            @csrf
                            <x-ui.button type="submit" variant="warning" icon="feather-send" class="px-3">
                                {{ __('production.submit_approval') }}
                            </x-ui.button>
                        </form>
                    @endcan
                @endif

                @if ($routing->isPendingApproval())
                    @can('approve', $routing)
                        <form action="{{ route('production.routing.approve', $routing->id) }}" method="POST" class="d-inline" id="approveRoutingForm">
                            @csrf
                            <x-ui.button type="button" variant="success" icon="feather-check-circle" class="px-3" onclick="confirmApproveRouting()">
                                {{ __('production.approve_activate') }}
                            </x-ui.button>
                        </form>
                    @endcan

                    @can('reject', $routing)
                        <x-ui.button type="button" variant="danger" icon="feather-x-circle" class="px-3" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            {{ __('production.reject') }}
                        </x-ui.button>
                    @endcan
                @endif

                @can('retire', $routing)
                    <x-ui.button type="button" variant="outline-secondary" icon="feather-archive" class="px-3" data-bs-toggle="modal" data-bs-target="#retireModal">
                        {{ __('production.retire_routing') }}
                    </x-ui.button>
                @endcan

                @if ($routing->isActive() || $routing->isScheduled())
                    @can('cancel', $routing)
                        <x-ui.button type="button" variant="outline-danger" icon="feather-slash" class="px-3" data-bs-toggle="modal" data-bs-target="#cancelModal">
                            {{ __('production.cancel_routing') }}
                        </x-ui.button>
                    @endcan
                @endif
            </div>
        </div>



        <!-- Summary Statistics Layout -->
        <div class="row g-4 mb-4 pb-4 border-bottom">
            <!-- Left Info Panel -->
            <div class="col-md-4 border-end">
                <div class="text-center py-2">
                    <div class="avatar-text avatar-xl bg-soft-primary text-primary mx-auto mb-3 rounded-circle" style="width: 70px; height: 70px; font-size: 28px; display: flex; align-items: center; justify-content: center;">
                        <i class="feather-git-pull-request"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">{{ $routing->name }}</h4>
                    <span class="fs-13 fw-semibold text-muted text-uppercase">{{ $routing->routing_number }}</span>
                </div>

                <div class="d-flex flex-column gap-3 mt-4 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.linked_boms') }}:</span>
                        <span class="badge bg-soft-primary text-primary font-monospace fw-bold">
                            {{ $routing->boms->count() }} BOM(s)
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.total_operations') ?? 'Total Operations' }}:</span>
                        <span class="fw-bold text-dark">{{ $routing->operations->count() }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.version') }}:</span>
                        <span class="fw-semibold text-dark">{{ $routing->version }} (Rev {{ $routing->revision }})</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.validity') }}:</span>
                        <span class="fw-semibold text-dark text-end fs-12">
                            {{ $routing->effective_from ? $routing->effective_from->format('Y-m-d') : '-' }}
                            {{ __('production.to') ?? 'to' }}
                            {{ $routing->effective_to ? $routing->effective_to->format('Y-m-d') : __('production.indefinite') }}
                        </span>
                    </div>
                    @if ($routing->approved_at)
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fs-13">Action / Approved Date:</span>
                            <span class="fw-semibold text-dark text-end fs-12">
                                {{ $routing->approved_at->format('Y-m-d') }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Time Metrics -->
            <div class="col-md-4 border-end">
                <h5 class="fw-bold text-dark mb-3">{{ __('production.time_process_stats') }}</h5>
                <div class="d-flex flex-column gap-4 py-2 px-2">
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.total_sequence_operations') }}</span>
                        <span class="fs-22 fw-bold text-dark">
                            {{ $routing->operations->where('is_alternative', false)->count() }} {{ __('production.stages') }}
                            @if ($routing->operations->where('is_alternative', true)->count() > 0)
                                <small class="fs-12 text-muted fw-normal">(+{{ $routing->operations->where('is_alternative', true)->count() }} alt)</small>
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.setup_duration_estimate') }}</span>
                        @php
                            $primarySetupMin = (float) $routing->operations->where('is_alternative', false)->sum('setup_time_minutes');
                        @endphp
                        <span class="fs-22 fw-bold text-dark font-monospace">{{ format_duration($primarySetupMin) }}</span>
                        <small class="text-muted d-block fs-11 mt-0.5 font-monospace">({{ format_decimal($primarySetupMin) }}m) setup per run</small>
                    </div>
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.total_cycle_running_time') }}</span>
                        @php
                            $primaryCycleMin = (float) $routing->totalCycleTimeMinutes();
                        @endphp
                        <span class="fs-22 fw-bold text-success font-monospace">{{ format_duration($primaryCycleMin) }}</span>
                        <small class="text-muted d-block fs-11 mt-0.5 font-monospace">({{ format_decimal($primaryCycleMin) }}m)</small>
                        <small class="text-muted d-block mt-1">{{ __('production.cycle_time_desc') }}</small>
                    </div>
                </div>
            </div>

            <!-- Cost Estimates -->
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0">{{ __('production.process_cost_predictions') }} (Qty: 1.0)</h5>
                    @if ($routing->operations->where('is_alternative', true)->count() > 0)
                        <button type="button" class="btn btn-xs btn-outline-primary fw-semibold d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#routeSimulationModal">
                            <i class="feather-sliders"></i> Simulate Route
                        </button>
                    @endif
                </div>
                @if ($costSummary)
                    <div class="d-flex flex-column gap-3 py-2 px-2">
                        <div>
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.routing_cost_per_unit') }}</span>
                            <span class="fs-24 fw-bold text-primary">{{ format_currency($costSummary['total_cost'], 4) }}</span>
                            <div class="mt-1 d-flex flex-column gap-0.5">
                                <span class="text-muted fs-11">
                                    <i class="feather-info text-primary me-0.5"></i>Primary route baseline (excludes alternative operations).
                                </span>
                                @if ($routing->operations->where('is_alternative', true)->count() > 0)
                                    <span class="text-muted fs-11">
                                        <span class="badge bg-soft-warning text-warning fs-9">+{{ $routing->operations->where('is_alternative', true)->count() }} alternative(s)</span> available to simulate.
                                    </span>
                                @endif
                            </div>
                        </div>
                        
                        <div class="border-top pt-3 mt-1">
                            <h6 class="fw-bold text-dark mb-2">{{ __('production.cost_contribution_details') }}</h6>
                            <div class="d-flex justify-content-between align-items-center mb-1 fs-12">
                                <span class="text-muted">{{ __('production.labor_cost') }}:</span>
                                <span class="fw-semibold text-dark">{{ format_currency($costSummary['labor_cost'], 4) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center fs-12">
                                <span class="text-muted">{{ __('production.machine_cost') }}:</span>
                                <span class="fw-semibold text-dark">{{ format_currency($costSummary['machine_cost'], 4) }}</span>
                            </div>
                            @if (!empty($costSummary['subcontract_cost']) && (float) $costSummary['subcontract_cost'] > 0.0)
                                <div class="d-flex justify-content-between align-items-center fs-12 mt-1">
                                    <span class="text-muted">Subcontract Cost:</span>
                                    <span class="fw-semibold text-dark">{{ format_currency($costSummary['subcontract_cost'], 4) }}</span>
                                </div>
                            @endif
                            {{-- RT-IMPL-13: setup per operation is a per-RUN cost, not part of Cost / Unit --}}
                            <div class="d-flex justify-content-between align-items-center fs-12 mt-1 pt-1 border-top routing-setup-cost-per-run" title="Σ (setup per operation × (labor + machine rate)) of primary operations — paid once per production run">
                                <span class="text-muted">Setup Cost per Run:</span>
                                <span class="fw-semibold text-dark">{{ format_currency($costSummary['setup_cost_per_run'] ?? 0, 4) }}</span>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="feather-dollar-sign text-muted fs-28 mb-2 d-block"></i>
                        <span class="text-muted fs-12">{{ __('production.no_pricing_components') }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- RT-IMPL-18 (RT-29 rule 2): inactive / deleted resources --}}
        @if (!empty($costStatus['inactive']))
            <div class="alert alert-soft-danger border border-danger-subtle d-flex align-items-start gap-2.5 mb-3 py-2.5 px-3 fs-12 rounded-3 routing-inactive-resources-banner">
                <i class="feather-slash text-danger fs-16 flex-shrink-0 mt-0.5"></i>
                <div class="flex-grow-1">
                    <strong class="text-dark d-block mb-1">{{ $costStatus['inactive_header'] }}</strong>
                    @foreach ($costStatus['inactive'] as $row)
                        <div class="mb-0.5">{{ $row['message'] }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- RT-IMPL-14 (RT-19 rule 7): outdated / not-calculated costs --}}
        @if (!empty($costStatus) && ($costStatus['outdated'] || $costStatus['not_calculated']))
            <div class="alert {{ $costStatus['outdated'] ? 'alert-soft-warning border-warning-subtle' : 'alert-soft-secondary border-secondary-subtle' }} border d-flex align-items-start gap-2.5 mb-4 py-2.5 px-3 fs-12 rounded-3 routing-cost-status-banner">
                <i class="feather-alert-triangle text-warning fs-16 flex-shrink-0 mt-0.5"></i>
                <div class="flex-grow-1">
                    <strong class="text-dark d-block mb-1">{{ $costStatus['outdated'] ? 'Costs outdated' : 'Costs not calculated' }}</strong>
                    @foreach ($costStatus['operations'] as $opStatus)
                        <div class="mb-0.5"><span class="font-monospace fw-semibold text-dark">{{ $opStatus['operation_number'] }}:</span> {{ implode(' ', $opStatus['messages']) }}</div>
                    @endforeach
                    <div class="mt-1 text-muted">{{ $costStatus['action'] }}</div>
                </div>
            </div>
        @endif

        <!-- Description Info -->
        @if ($routing->description)
            <div class="mb-4 pb-4 border-bottom">
                <h5 class="fw-bold text-dark mb-2">{{ __('production.routing_purpose_specs') }}</h5>
                <p class="mb-0 fs-13 text-muted" style="white-space: pre-line;">{{ $routing->description }}</p>
            </div>
        @endif

        <!-- Operations Sequence Details -->
        <div class="mb-4 pb-4 border-bottom">
            <h5 class="fw-bold text-dark mb-3">{{ __('production.sequence_operations_timeline') }}</h5>
            {{-- RT-IMPL-10 (RT-12 / RT-17) --}}
            <div class="alert alert-soft-info border border-info-subtle py-1.5 px-3 fs-12 mb-2 d-flex align-items-center gap-2 routing-one-unit-notice"><i class="feather-info text-info fs-13"></i><span>{{ __('production.routing_one_unit_notice') }}</span></div>
            <div class="table-responsive">
                <table class="erp-thin-table" style="min-width: 1420px;">
                    <thead>
                        <tr>
                            <th style="width: 4%; min-width: 45px;" class="text-center">{{ __('production.seq') }}</th>
                            <th style="width: 14%; min-width: 160px;">{{ __('production.operation_details') }}</th>
                            <th style="width: 14%; min-width: 160px;">{{ __('production.work_center') }}</th>
                            <th style="width: 14%; min-width: 160px;">Section</th>
                            <th style="width: 22%; min-width: 240px;">Resource</th>
                            <th class="text-end" style="width: 5%; min-width: 60px;" title="Setup per operation — once per production run">{{ __('production.setup') }} / run</th>
                            <th class="text-end" style="width: 5%; min-width: 60px;" title="Setup per piece — every unit">{{ __('production.setup') }} / piece</th>
                            <th class="text-end" style="width: 5%; min-width: 60px;">{{ __('production.run') }}</th>
                            <th class="text-end" style="width: 4%; min-width: 50px;">Wait</th>
                            <th class="text-end text-nowrap" style="width: 8%; min-width: 110px;">
                                Cost / Unit <i class="feather-info text-primary ms-0.5 fs-11" data-bs-toggle="tooltip" data-bs-placement="top" title="Cost / Unit (recurring, every piece) = (Setup / piece + Run time) × (Labor rate + Machine rate) × (100 ÷ Yield %). One-time setup per run is shown separately in Op Setup Cost."></i>
                            </th>
                            {{-- Short Full Review Point 1: one-time setup per run gets its own column --}}
                            <th class="text-end text-nowrap routing-op-setup-cost-head" style="width: 8%; min-width: 120px;">
                                Op Setup Cost <i class="feather-info text-primary ms-0.5 fs-11" data-bs-toggle="tooltip" data-bs-placement="top" title="Operation Setup Cost (per run): one-time setup cost incurred once per production run / batch for machine changeover or tool setting = Setup / run (m) × (Labor rate + Machine rate), yield excluded. Shown separately from Cost / Unit because batch size is decided at Production Order time."></i>
                            </th>
                            <th class="text-end" style="width: 4%; min-width: 50px;">{{ __('production.yield') }}</th>
                            <th class="text-center" style="width: 4%; min-width: 50px;">{{ __('production.qc_gate') }}</th>
                            <th style="width: 6%; min-width: 80px;">{{ __('production.type') }} / Alt</th>
                            <th class="text-center" style="width: 5%; min-width: 70px;">Queue Threshold</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($routing->operations as $op)
                            <tr>
                                <td class="fw-bold text-center font-monospace align-middle">{{ $op->sequence }}</td>
                                <td class="align-middle">
                                    <span class="fw-bold text-dark">{{ $op->name }}</span>
                                    <span class="badge bg-soft-primary text-primary font-monospace ms-1 fs-9">{{ $op->operation_number }}</span>
                                    @if ($op->description)
                                        <small class="text-muted d-block mt-1">{{ $op->description }}</small>
                                    @endif
                                    @if ($op->instructions)
                                        <div class="mt-1 p-1 px-2 bg-light rounded border fs-11 text-dark">
                                            <span class="fw-semibold text-primary"><i class="feather-file-text me-1"></i>SOP:</span>
                                            <span class="text-muted" style="white-space: pre-line;">{{ $op->instructions }}</span>
                                        </div>
                                    @endif
                                    @if ($op->is_external)
                                        <span class="badge bg-soft-danger text-danger mt-1 fs-9 text-uppercase">{{ __('production.outsourced') }}</span>
                                    @endif
                                    @if ($op->is_parallel)
                                        <span class="badge bg-soft-primary text-primary mt-1 fs-9"><i class="feather-shuffle me-1"></i>Parallel{{ $op->parallel_group ? ' (' . $op->parallel_group . ')' : '' }}</span>
                                    @elseif ($op->is_alternative)
                                        @if ($op->previousOperation)
                                            <span class="badge bg-soft-secondary text-secondary mt-1 fs-9"><i class="feather-git-commit me-1"></i>Predecessor: {{ $op->previousOperation->operation_number }} (Mirrored)</span>
                                        @else
                                            <span class="badge bg-soft-success text-success mt-1 fs-9"><i class="feather-play me-1"></i>Independent (Mirrored)</span>
                                        @endif
                                    @elseif ($op->previousOperation)
                                        <span class="badge bg-soft-secondary text-secondary mt-1 fs-9"><i class="feather-git-commit me-1"></i>Predecessor: {{ $op->previousOperation->operation_number }}</span>
                                    @elseif ($loop->index > 0 && $op->previous_operation_id === null)
                                        <span class="badge bg-soft-success text-success mt-1 fs-9"><i class="feather-play me-1"></i>Independent</span>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    @if ($op->workCenter)
                                        <a href="{{ route('production.work-centers.show', $op->work_center_id) }}" class="fw-semibold text-primary">
                                            {{ $op->workCenter->name }}
                                        </a>
                                        <small class="text-muted d-block fs-10">{{ $op->workCenter->code }}</small>
                                    @elseif ($op->is_external)
                                        <span class="badge bg-soft-secondary text-secondary">External</span>
                                    @else
                                        <span class="text-danger">{{ __('production.missing') }}</span>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    @if ($op->section)
                                        <span class="fw-semibold text-dark">{{ $op->section->name }}</span>
                                        <small class="text-muted d-block fs-10">{{ $op->section->code }}</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    @if ($op->is_external)
                                        <span class="badge bg-soft-info text-info"><i class="feather-truck me-1"></i>Subcontract</span>
                                        @if ($op->vendor)
                                            <small class="text-muted d-block fs-10">{{ $op->vendor->name }}</small>
                                        @endif
                                    @elseif ($op->machine)
                                        @php
                                            // RT-IMPL-14: saved figures only (no live calculation); capacity / speed from the saved snapshot
                                            $snap = $op->resource_snapshot ?? [];
                                            $cap = $snap['capacity'] ?? null;
                                            $speedMin = $snap['speed_minutes_per_unit'] ?? ($op->processing_time_minutes > 0 ? (float) $op->processing_time_minutes : null);
                                            $mCost = $op->costsNotCalculated() ? null : (float) ($op->machine_cost_per_unit ?? 0);
                                            $lCost = $op->costsNotCalculated() ? null : (float) ($op->labor_cost_per_unit ?? 0);
                                            $opCost = $op->costsNotCalculated() ? null : (float) $op->cost_per_unit;
                                        @endphp
                                        <span class="badge bg-soft-primary text-primary"><i class="feather-cpu me-1"></i>Machine</span>
                                        <span class="fw-semibold text-dark d-block fs-11 mt-0.5">{{ $op->machine->name }}</span>
                                        <small class="text-muted fs-10 font-monospace">{{ $op->machine->code }}</small>

                                        @if ($cap)
                                            <div class="mt-1.5 p-1.5 rounded bg-light border fs-10" style="max-width: 260px;">
                                                <div class="d-flex justify-content-between align-items-center mb-0.5">
                                                    <span class="text-muted"><i class="feather-activity me-1"></i>Capacity:</span>
                                                    <span class="fw-bold text-dark font-monospace">{{ $cap }} u/hr</span>
                                                </div>
                                                @if ($speedMin)
                                                    <div class="d-flex justify-content-between align-items-center mb-0.5">
                                                        <span class="text-muted"><i class="feather-clock me-1"></i>Run Speed:</span>
                                                        <span class="fw-bold text-dark font-monospace">
                                                            {{ format_duration($speedMin) }}
                                                            <span class="text-muted fw-normal fs-9">({{ format_decimal($speedMin) }} m/u)</span>
                                                        </span>
                                                    </div>
                                                @endif
                                                @if ($mCost !== null)
                                                    <div class="d-flex justify-content-between align-items-center mb-0.5">
                                                        <span class="text-muted"><i class="feather-cpu me-1"></i>Machine Cost:</span>
                                                        <span class="font-monospace text-dark">{{ format_currency($mCost, 4) }}</span>
                                                    </div>
                                                @endif
                                                @if ($lCost !== null)
                                                    <div class="d-flex justify-content-between align-items-center mb-0.5">
                                                        <span class="text-muted"><i class="feather-user me-1"></i>Operator Cost:</span>
                                                        <span class="font-monospace text-dark">{{ format_currency($lCost, 4) }}</span>
                                                    </div>
                                                    @if (isset($snap['non_batch_daily_labor_cost'], $snap['section_operating_hours'], $snap['active_machines_count']) && (float) $snap['section_operating_hours'] > 0 && (int) $snap['active_machines_count'] > 0)
                                                        {{-- RT-IMPL-12: how the operator labor rate was derived --}}
                                                        <div class="text-muted fs-9 mb-0.5 operator-labor-basis">
                                                            Non-batch labor {{ format_currency((float) $snap['non_batch_daily_labor_cost'], 4) }}/day
                                                            ÷ {{ format_hours((float) $snap['section_operating_hours']) }}
                                                            ÷ {{ (int) $snap['active_machines_count'] }} {{ \Illuminate\Support\Str::plural('machine', (int) $snap['active_machines_count']) }}
                                                            = {{ format_currency((float) ($snap['operator_labor_cost_per_machine_per_hour'] ?? 0), 4) }}/hr
                                                        </div>
                                                    @endif
                                                @endif
                                                @if ($opCost !== null)
                                                    <div class="d-flex justify-content-between align-items-center pt-0.5 border-top border-light-subtle">
                                                        <span class="fw-semibold text-dark"><i class="feather-dollar-sign me-0.5 text-muted"></i>Operation Cost:</span>
                                                        <span class="fw-bold text-primary font-monospace">{{ format_currency($opCost, 4) }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    @elseif ($op->manualProduction)
                                        @php
                                            // RT-IMPL-14: saved figures only (no live calculation)
                                            $snap = $op->resource_snapshot ?? [];
                                            $cap = $snap['capacity'] ?? null;
                                            $speedMin = $snap['speed_minutes_per_unit'] ?? ($op->processing_time_minutes > 0 ? (float) $op->processing_time_minutes : null);
                                            $lCost = $op->costsNotCalculated() ? null : (float) ($op->labor_cost_per_unit ?? $op->cost_per_unit);
                                        @endphp
                                        <span class="badge bg-soft-success text-success"><i class="feather-users me-1"></i>Manual Batch</span>
                                        <span class="fw-semibold text-dark d-block fs-11 mt-0.5">{{ $op->manualProduction->batch_name }}</span>
                                        @if ($op->manualProduction->shift)
                                            <small class="text-muted fs-10">Shift: {{ $op->manualProduction->shift->name }}</small>
                                        @endif

                                        @if ($cap || $lCost !== null)
                                            <div class="mt-1.5 p-1.5 rounded bg-light border fs-10" style="max-width: 260px;">
                                                @if ($cap)
                                                    <div class="d-flex justify-content-between align-items-center mb-0.5">
                                                        <span class="text-muted"><i class="feather-activity me-1"></i>Capacity:</span>
                                                        <span class="fw-bold text-dark font-monospace">{{ $cap }} u/hr</span>
                                                    </div>
                                                @endif
                                                @if ($speedMin)
                                                    <div class="d-flex justify-content-between align-items-center mb-0.5">
                                                        <span class="text-muted"><i class="feather-clock me-1"></i>Run Speed:</span>
                                                        <span class="fw-bold text-dark font-monospace">
                                                            {{ format_duration($speedMin) }}
                                                            <span class="text-muted fw-normal fs-9">({{ format_decimal($speedMin) }} m/u)</span>
                                                        </span>
                                                    </div>
                                                @endif
                                                @if ($lCost !== null)
                                                    <div class="d-flex justify-content-between align-items-center pt-0.5 border-top border-light-subtle">
                                                        <span class="fw-semibold text-dark"><i class="feather-dollar-sign me-0.5 text-muted"></i>Labor Cost:</span>
                                                        <span class="fw-bold text-success font-monospace">{{ format_currency($lCost, 4) }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end align-middle font-monospace">
                                    <span class="fw-semibold text-dark">{{ format_duration($op->setup_time_minutes) }}</span>
                                    @if ((float) $op->setup_time_minutes > 0)
                                        <small class="text-muted d-block fs-9">{{ format_decimal($op->setup_time_minutes) }} min</small>
                                    @endif
                                </td>
                                <td class="text-end align-middle font-monospace">
                                    @if ((float) ($op->setup_time_per_piece_minutes ?? 0) > 0)
                                        <span class="fw-semibold text-dark">{{ format_duration($op->setup_time_per_piece_minutes) }}</span>
                                        <small class="text-muted d-block fs-9">{{ format_decimal($op->setup_time_per_piece_minutes) }} min</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end align-middle font-monospace">
                                    <span class="fw-semibold text-dark">{{ format_duration($op->processing_time_minutes) }}</span>
                                    @if ((float) $op->processing_time_minutes > 0)
                                        <small class="text-muted d-block fs-9">{{ format_decimal($op->processing_time_minutes) }} min</small>
                                    @endif
                                </td>
                                <td class="text-end align-middle font-monospace">
                                    @if ((float)($op->wait_time_minutes ?? 0) > 0)
                                        <span class="fw-semibold text-dark">{{ format_duration($op->wait_time_minutes) }}</span>
                                        <small class="text-muted d-block fs-9">{{ format_decimal($op->wait_time_minutes) }} min</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end align-middle font-monospace">
                                    @php
                                        $setupMin = (float) ($op->setup_time_per_piece_minutes ?? 0); // RT-IMPL-13: per piece
                                        $runMin = (float) $op->processing_time_minutes;
                                        $activeMin = $setupMin + $runMin;
                                        $setupRunCost = $op->setupCostPerRun();
                                        $costVal = $op->cost_per_unit !== null && (float) $op->cost_per_unit > 0.0 ? (float) $op->cost_per_unit : $op->estimatedCostPerUnit();
                                    @endphp
                                    <span class="fw-bold text-primary fs-12" data-bs-toggle="tooltip" data-bs-placement="top" title="Setup / piece: {{ format_duration($setupMin) }} + Run: {{ format_duration($runMin) }} = {{ format_duration($activeMin) }} per piece">
                                        {{ format_currency($costVal, 4) }}
                                    </span>
                                    @if ($op->costsNotCalculated())
                                        <small class="text-warning d-block fs-9 routing-costs-not-calculated">Costs not calculated — open and save the routing</small>
                                    @elseif (!$op->is_external && $op->section_snapshot_number)
                                        <small class="text-muted d-block fs-9 font-monospace" title="Figures from section calculation snapshot #{{ $op->section_snapshot_number }}{{ $op->costs_calculated_at ? ' (' . $op->costs_calculated_at->format('Y-m-d H:i') . ')' : '' }}">Snapshot #{{ $op->section_snapshot_number }}</small>
                                    @endif
                                    @if ($op->is_alternative)
                                        <span class="badge bg-soft-warning text-warning fs-9 d-block mt-0.5" style="font-weight: 500;" title="Alternative operation — replaces a primary operation and is not added into the standard primary total.">
                                            <i class="feather-git-branch me-0.5"></i>Alt Branch
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end align-middle font-monospace text-nowrap routing-op-setup-cost" title="Setup per operation × (labor + machine rate) — once per production run, no yield">
                                    @if ($op->setupCostPerRun() > 0)
                                        <span class="fw-semibold text-dark fs-12">{{ format_currency($op->setupCostPerRun(), 4) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end align-middle font-monospace">{{ number_format($op->expected_yield_percentage, 0) }}%</td>
                                <td class="text-center align-middle">
                                    @if ($op->quality_required)
                                        <span class="badge bg-soft-danger text-danger"><i class="feather-shield"></i> QC</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    @if ($op->is_alternative)
                                        <span class="badge bg-soft-warning text-warning d-inline-flex align-items-center gap-1 mb-1 fs-10">
                                            <i class="feather-git-branch"></i> Alt (Pri: {{ $op->priority ?? 1 }})
                                        </span>
                                        @if ($op->primaryOperation)
                                            <small class="d-block text-muted fs-10">Alt of Op {{ $op->primaryOperation->sequence }}</small>
                                        @endif
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary text-uppercase fs-10">
                                            {{ config('production.operation_types')[$op->operation_type] ?? $op->operation_type }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center align-middle">
                                    @if ($op->queue_threshold_enabled ?? $op->overlap_enabled)
                                        <div class="d-flex flex-column align-items-center gap-1">
                                            <span class="badge bg-soft-info text-info" title="Queue Threshold Enabled (Partial Transfer Batch: {{ number_format($op->transfer_batch_quantity, 2) }})">
                                                <i class="feather-zap me-1"></i>{{ number_format($op->transfer_batch_quantity, 2) }}
                                            </span>
                                            @if (!empty($op->transfer_lag_minutes) && (float) $op->transfer_lag_minutes > 0)
                                                <span class="text-muted font-monospace fs-10" title="Transit / handling delay before the first transfer batch reaches the downstream operation.">
                                                    Lag: {{ format_duration($op->transfer_lag_minutes) }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted fs-9 fw-semibold" title="Finish-to-Start (Standard Sequential Operation: 100% completion required before downstream op starts)">FS</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>{{ __('production.no_operations_defined') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($routing->operations->count() > 0)
                        <tfoot>
                            <tr class="bg-light-subtle border-top">
                                <td colspan="9" class="text-end fw-bold text-dark py-2.5 fs-12">
                                    Primary Route Baseline ({{ $routing->operations->where('is_alternative', false)->count() }} {{ __('production.stages') }}):
                                </td>
                                <td class="text-end fw-bold text-primary py-2.5 fs-13 font-monospace">
                                    {{ format_currency($costSummary['total_cost'] ?? 0, 4) }}
                                </td>
                                <td class="text-end fw-bold text-dark py-2.5 fs-12 font-monospace text-nowrap routing-op-setup-cost-total" title="Σ setup cost per run of primary operations — paid once per production run">
                                    {{ format_currency($costSummary['setup_cost_per_run'] ?? 0, 4) }}
                                </td>
                                <td colspan="4" class="py-2.5 fs-11 text-muted">
                                    @if ($routing->operations->where('is_alternative', true)->count() > 0)
                                        <span class="badge bg-soft-warning text-warning font-monospace" title="Alternative operation branch cost (excluded from baseline unit cost)">
                                            +{{ $routing->operations->where('is_alternative', true)->count() }} Alt Op(s) ({{ format_currency($routing->operations->where('is_alternative', true)->sum(fn($o) => $o->cost_per_unit > 0 ? $o->cost_per_unit : $o->estimatedCostPerUnit()), 4) }})
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <!-- Linked Bill of Materials (BOMs) -->
        <div class="mb-4 pb-4 border-bottom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0">Linked Bill of Materials (BOMs)</h5>
                    <p class="fs-12 text-muted mb-0">Production BOMs utilizing this process routing as their manufacturing sequence.</p>
                </div>
                <span class="badge bg-soft-primary text-primary font-monospace">{{ $routing->boms->count() }} Linked</span>
            </div>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width: 18%">BOM Number</th>
                            <th style="width: 28%">Item / Product to Produce</th>
                            <th style="width: 12%">Version</th>
                            <th class="text-end" style="width: 15%">Base Quantity</th>
                            <th class="text-center" style="width: 15%">Status</th>
                            <th class="text-center" style="width: 12%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($routing->boms as $bom)
                            <tr>
                                <td class="align-middle">
                                    <a href="{{ route('production.boms.show', $bom->id) }}" class="fw-semibold text-primary font-monospace">
                                        {{ $bom->bom_number }}
                                    </a>
                                    @if ($bom->bom_name)
                                        <small class="text-muted d-block fs-11">{{ $bom->bom_name }}</small>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    <span class="fw-semibold text-dark fs-12">{{ $bom->product->name ?? '—' }}</span>
                                    @if ($bom->product && $bom->product->sku)
                                        <small class="text-muted d-block font-monospace fs-10">{{ $bom->product->sku }}</small>
                                    @endif
                                </td>
                                <td class="align-middle font-monospace fs-12">
                                    v{{ $bom->version }}
                                    @if ($bom->revision)
                                        <small class="text-muted fs-10">(r{{ $bom->revision }})</small>
                                    @endif
                                </td>
                                <td class="text-end align-middle font-monospace fs-12">
                                    {{ number_format($bom->base_quantity, 2) }} {{ $bom->baseUom->name ?? $bom->product->uom->name ?? 'units' }}
                                </td>
                                <td class="text-center align-middle">
                                    @if ($bom->status === 'approved' || $bom->status === 'active')
                                        <span class="badge bg-soft-success text-success text-uppercase font-monospace fs-10">{{ $bom->status }}</span>
                                    @elseif ($bom->status === 'draft')
                                        <span class="badge bg-soft-warning text-warning text-uppercase font-monospace fs-10">Draft</span>
                                    @elseif ($bom->status === 'pending_approval')
                                        <span class="badge bg-soft-info text-info text-uppercase font-monospace fs-10">Pending</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary text-uppercase font-monospace fs-10">{{ $bom->status }}</span>
                                    @endif
                                </td>
                                <td class="text-center align-middle">
                                    <a href="{{ route('production.boms.show', $bom->id) }}" class="btn btn-sm btn-outline-primary py-0 px-2 fs-11">
                                        <i class="feather-eye me-1"></i>View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>No Bill of Materials linked to this routing yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Approval Audit History -->
        <div>
            <h5 class="fw-bold text-dark mb-3">{{ __('production.routing_approval_history') }}</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width: 20%">{{ __('production.timestamp') }}</th>
                            <th style="width: 25%">{{ __('production.user') }}</th>
                            <th style="width: 15%">{{ __('production.transition') }}</th>
                            <th style="width: 40%">{{ __('production.notes_reasons') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($routing->approvals as $approval)
                            <tr>
                                <td class="font-monospace fs-12 align-middle text-muted">{{ $approval->created_at->format('Y-m-d H:i:s') }}</td>
                                <td class="fw-semibold text-dark align-middle">{{ $approval->user->name ?? 'System Process' }}</td>
                                <td class="align-middle">
                                    @if ($approval->action === 'Approved')
                                        <span class="badge bg-soft-success text-success text-uppercase font-monospace fs-10">{{ __('production.approved') }}</span>
                                    @elseif ($approval->action === 'Rejected')
                                        <span class="badge bg-soft-danger text-danger text-uppercase font-monospace fs-10">{{ __('production.rejected') }}</span>
                                    @elseif ($approval->action === 'Submitted')
                                        <span class="badge bg-soft-warning text-warning text-uppercase font-monospace fs-10">{{ __('production.submitted') }}</span>
                                    @elseif ($approval->action === 'Cancelled')
                                        <span class="badge bg-soft-danger text-danger text-uppercase font-monospace fs-10">{{ __('production.cancelled') }}</span>
                                    @elseif ($approval->action === 'Revision Created')
                                        <span class="badge bg-soft-info text-info text-uppercase font-monospace fs-10">{{ __('production.revision_created') }}</span>
                                    @elseif ($approval->action === 'Created')
                                        <span class="badge bg-soft-secondary text-secondary text-uppercase font-monospace fs-10">{{ __('production.created') }}</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary text-uppercase font-monospace fs-10">{{ $approval->action }}</span>
                                    @endif
                                </td>
                                <td class="text-muted fs-12 align-middle">{{ $approval->comments ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>{{ __('production.no_history_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @php
        $rejectTitle = '<i class="feather-x-circle me-2 text-danger"></i>' . __('production.reject_routing_version');
        $cancelTitle = '<i class="feather-slash me-2 text-dark"></i>' . __('production.cancel_routing_version');
        $duplicateTitle = '<i class="feather-git-branch me-2 text-primary"></i>' . __('production.duplicate_routing_version');
    @endphp

    <!-- Reject Comment Modal -->
    <x-ui.modal
        id="rejectModal"
        :title="$rejectTitle"
        formAction="{{ route('production.routing.reject', $routing->id) }}"
        formMethod="POST"
        :submitText="__('production.confirm_rejection')"
        :closeText="__('production.cancel')"
        :static="true"
    >
        <x-ui.odoo-form-ui type="textarea" :label="__('production.comments_notes')" name="comments" placeholder="{{ __('production.reject_comments_placeholder') }}" :required="true" rows="4"></x-ui.odoo-form-ui>
    </x-ui.modal>

    <!-- Cancel Comment Modal -->
    <x-ui.modal
        id="cancelModal"
        :title="$cancelTitle"
        formAction="{{ route('production.routing.cancel', $routing->id) }}"
        formMethod="POST"
        :submitText="__('production.decommission_routing')"
        :closeText="__('production.cancel')"
        :static="true"
    >
        @include('modules.production.routing.partials.bom-impact', ['routing' => $routing, 'impact' => $bomImpact ?? null])
        <x-ui.odoo-form-ui type="textarea" :label="__('production.notes_reasons')" name="comments" placeholder="{{ __('production.cancel_reason_placeholder') }}" :required="true" rows="4"></x-ui.odoo-form-ui>
    </x-ui.modal>

    <!-- RT-IMPL-02: Retire Modal -->
    @can('retire', $routing)
    @php $retireTitle = '<i class="feather-archive me-2 text-dark"></i>' . __('production.retire_routing'); @endphp
    <x-ui.modal
        id="retireModal"
        :title="$retireTitle"
        formAction="{{ route('production.routing.retire', $routing->id) }}"
        formMethod="POST"
        :submitText="__('production.retire_routing')"
        :closeText="__('production.cancel')"
        :static="true"
    >
        <p class="fs-13 text-muted">{{ __('production.retire_routing_body') }}</p>
        @include('modules.production.routing.partials.bom-impact', ['routing' => $routing, 'impact' => $bomImpact ?? null])
        <x-ui.odoo-form-ui type="textarea" :label="__('production.notes_reasons')" name="comments" placeholder="{{ __('production.retire_reason_placeholder') }}" rows="3"></x-ui.odoo-form-ui>
    </x-ui.modal>
    @endcan

    <!-- Duplicate / Create New Version Modal -->
    <x-ui.modal
        id="duplicateVersionModal"
        :title="$duplicateTitle"
        formAction="{{ route('production.routing.duplicate', $routing->id) }}"
        formMethod="POST"
        :submitText="__('production.create_revision_version')"
        :closeText="__('production.cancel')"
        :static="true"
    >
        <div class="mb-3 d-flex align-items-center">
            <span class="odoo-form-label" style="width: 150px;">{{ __('production.original_version_profile') }}</span>
            <span class="fw-bold text-dark fs-14">{{ $routing->version }} ({{ __('production.revision') ?? 'Revision' }} {{ $routing->revision }})</span>
        </div>
        
        <x-ui.odoo-form-ui type="input" :label="__('production.new_version_name')" name="new_version" placeholder="e.g. 1.0.1 or 2.0.0" :value="old('new_version')" :required="true" />
        <small class="text-muted d-block mt-2">{{ __('production.duplicate_routing_modal_body') }}</small>
    </x-ui.modal>

    <!-- Route Scenario Simulation Modal (Alternative Branch Costing) -->
    @if ($routing->operations->where('is_alternative', true)->count() > 0)
        @php
            $stagesData = [];
            $allOpsData = [];
            $primaryOps = $routing->operations->where('is_alternative', false)->sortBy('sequence')->values();
            $allAlts = $routing->operations->where('is_alternative', true);
            $claimedAltIds = [];

            foreach ($routing->operations as $op) {
                $cVal = $op->cost_per_unit !== null && (float) $op->cost_per_unit > 0.0 ? (float) $op->cost_per_unit : $op->estimatedCostPerUnit();
                $opCostDetails = app(\App\Domains\Production\Services\RoutingCostService::class)->calculateOperationCost($op, 1.0);
                
                $resType = 'manual';
                $resName = 'Manual Batch';
                if ($op->machine) {
                    $resType = 'machine';
                    $resName = $op->machine->name . ' (' . $op->machine->code . ')';
                } elseif ($op->manualProduction) {
                    $resType = 'manual';
                    $resName = $op->manualProduction->batch_name;
                } elseif ($op->is_external) {
                    $resType = 'subcontract';
                    $resName = $op->vendor?->name ?? 'External Subcontractor';
                }

                $allOpsData[$op->id] = [
                    'id' => $op->id,
                    'sequence' => $op->sequence,
                    'operation_number' => $op->operation_number,
                    'name' => $op->name,
                    'is_alternative' => (bool) $op->is_alternative,
                    'replaces_operation_id' => $op->replaces_operation_id,
                    'priority' => (int) ($op->priority ?? 1),
                    'work_center' => $op->workCenter?->name ?? ($op->is_external ? 'External' : '-'),
                    'section' => $op->section?->name ?? '-',
                    'resource_type' => $resType,
                    'resource' => $resName,
                    'setup_minutes' => (float) ($op->setup_time_per_piece_minutes ?? 0), // RT-IMPL-13: per piece (setup per run is separate)
                    'setup_per_run_minutes' => (float) $op->setup_time_minutes,
                    'processing_minutes' => (float) $op->processing_time_minutes,
                    'total_minutes' => $op->cycleTimePerPieceMinutes(),
                    'cost_per_unit' => round(convert_from_base((float) $cVal), 4), // Short Full Review Point 2: simulator works in the active currency
                    'labor_cost' => round(convert_from_base((float) ($opCostDetails['labor_cost'] ?? 0)), 4),
                    'machine_cost' => round(convert_from_base((float) ($opCostDetails['machine_cost'] ?? 0)), 4),
                    'subcontract_cost' => round(convert_from_base((float) ($opCostDetails['subcontract_cost'] ?? 0)), 4),
                    'yield_percentage' => (float) ($op->expected_yield_percentage > 0 ? $op->expected_yield_percentage : 100),
                ];
            }

            foreach ($primaryOps as $idx => $pOp) {
                $alts = $allAlts->where('replaces_operation_id', $pOp->id)->sortBy('priority')->values();
                foreach ($alts as $a) { $claimedAltIds[] = $a->id; }
                $stagesData[] = [
                    'stage_number' => $idx + 1,
                    'primary_id' => $pOp->id,
                    'has_alternatives' => $alts->count() > 0,
                    'options' => array_merge([$pOp->id], $alts->pluck('id')->toArray()),
                ];
            }

            // Capture any orphan alts
            $orphanAlts = $allAlts->whereNotIn('id', $claimedAltIds);
            if ($orphanAlts->count() > 0 && count($stagesData) > 0) {
                foreach ($orphanAlts as $oAlt) {
                    $assigned = false;
                    for ($i = count($stagesData) - 1; $i >= 0; $i--) {
                        if ($allOpsData[$stagesData[$i]['primary_id']]['sequence'] < $oAlt->sequence) {
                            $stagesData[$i]['options'][] = $oAlt->id;
                            $stagesData[$i]['has_alternatives'] = true;
                            $assigned = true;
                            break;
                        }
                    }
                    if (!$assigned) {
                        $stagesData[0]['options'][] = $oAlt->id;
                        $stagesData[0]['has_alternatives'] = true;
                    }
                }
            }

            $primaryBaselineCost = round(convert_from_base((float) ($costSummary['total_cost'] ?? 0)), 4);
            // RT-IMPL-13: simulator compares cycle time per piece (setup per piece + run) — same basis as its own totals
            $primaryBaselineCycle = (float) $routing->operations->where('is_alternative', false)->sum(fn($o) => $o->cycleTimePerPieceMinutes());
            $currencySym = active_currency_symbol();
        @endphp

        <div class="modal fade" id="routeSimulationModal" tabindex="-1" aria-labelledby="routeSimulationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"
                 x-data="routeSimulator({{ Js::from($stagesData) }}, {{ Js::from($allOpsData) }}, {{ $primaryBaselineCost }}, {{ $primaryBaselineCycle }}, '{{ $currencySym }}')">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-white border-bottom py-3 px-4">
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                <i class="feather-sliders fs-18"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold text-dark mb-0" id="routeSimulationModalLabel">
                                    Simulate Routing Scenario
                                </h5>
                                <span class="fs-12 text-muted">Substitute alternative branches and click "Run Calculations" to preview scenario cost & time</span>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4 bg-light-subtle">
                        <!-- Guidance Alert -->
                        <div class="alert alert-info py-2 px-3 fs-12 mb-3 d-flex align-items-center gap-2 rounded border-info-subtle">
                            <i class="feather-info fs-16 text-primary flex-shrink-0"></i>
                            <div>
                                Stages with <strong>no alternatives are locked</strong>. For stages with alternatives, select <strong>which variant runs</strong>, then click <strong>"RUN CALCULATIONS"</strong>.
                            </div>
                        </div>

                        <!-- Stages Sequence List -->
                        <div class="d-flex flex-column gap-3 mb-4">
                            <template x-for="stage in stages" :key="stage.primary_id">
                                <div class="card border rounded shadow-none mb-0">
                                    <!-- Stage Header -->
                                    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-soft-primary text-primary font-monospace fw-bold fs-11">
                                                Stage <span x-text="stage.stage_number"></span>
                                            </span>
                                            <span class="fw-bold text-dark fs-12 font-monospace" x-text="ops[stage.primary_id].operation_number + ': ' + ops[stage.primary_id].name"></span>
                                        </div>
                                        <div>
                                            <template x-if="!stage.has_alternatives">
                                                <span class="badge bg-soft-secondary text-secondary fs-10 d-inline-flex align-items-center gap-1">
                                                    <i class="feather-lock"></i> Locked (Primary Path)
                                                </span>
                                            </template>
                                            <template x-if="stage.has_alternatives">
                                                <span class="badge bg-soft-warning text-warning fs-10 d-inline-flex align-items-center gap-1">
                                                    <i class="feather-git-branch"></i> Alternative Available
                                                </span>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Stage Body / Options -->
                                    <div class="card-body p-2 bg-light">
                                        <!-- If No Alternatives: Locked Single Display -->
                                        <template x-if="!stage.has_alternatives">
                                            <div class="p-2.5 rounded bg-white border border-light-subtle d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <span class="badge bg-soft-primary text-primary fs-9">Fixed Primary</span>
                                                        <span class="fw-semibold text-dark fs-12" x-text="ops[stage.primary_id].resource"></span>
                                                    </div>
                                                    <small class="text-muted fs-11">
                                                        <span x-text="ops[stage.primary_id].work_center"></span> &bull; 
                                                        <span x-text="ops[stage.primary_id].section"></span>
                                                    </small>
                                                </div>
                                                <div class="text-end font-monospace">
                                                    <div class="fw-bold text-dark fs-12" x-text="currencySym + ' ' + fmt4(ops[stage.primary_id].cost_per_unit)"></div>
                                                    <small class="text-muted fs-11" x-text="formatDuration(ops[stage.primary_id].total_minutes) + ' (' + formatDuration(ops[stage.primary_id].setup_minutes) + ' setup + ' + formatDuration(ops[stage.primary_id].processing_minutes) + ' run)'"></small>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- If Has Alternatives: Mutually Exclusive Radio Options -->
                                        <template x-if="stage.has_alternatives">
                                            <div class="d-flex flex-column gap-2">
                                                <template x-for="opId in stage.options" :key="opId">
                                                    <label class="p-2.5 rounded border d-flex justify-content-between align-items-center cursor-pointer transition-all mb-0"
                                                           :class="selected['stage_' + stage.primary_id] == opId ? 'bg-white border-primary shadow-sm' : 'bg-white border-light-subtle'"
                                                           @click="selectVariant(stage.primary_id, opId)"
                                                           style="cursor: pointer;">
                                                        <div class="d-flex align-items-start gap-2.5">
                                                            <div class="form-check mt-0.5">
                                                                <input class="form-check-input" type="radio" 
                                                                       :name="'stage_' + stage.primary_id" 
                                                                       :value="opId"
                                                                       :checked="selected['stage_' + stage.primary_id] == opId"
                                                                       @change="selectVariant(stage.primary_id, opId)">
                                                            </div>
                                                            <div>
                                                                <div class="d-flex align-items-center gap-1.5 mb-0.5">
                                                                    <template x-if="!ops[opId].is_alternative">
                                                                        <span class="badge bg-soft-primary text-primary fs-9">Default Primary</span>
                                                                    </template>
                                                                    <template x-if="ops[opId].is_alternative">
                                                                        <span class="badge bg-soft-warning text-warning fs-9">
                                                                            <i class="feather-git-branch me-0.5"></i>Alt (Priority <span x-text="ops[opId].priority"></span>)
                                                                        </span>
                                                                    </template>
                                                                    <span class="fw-bold text-dark fs-12 font-monospace" x-text="ops[opId].operation_number + ': ' + ops[opId].name"></span>
                                                                </div>
                                                                <div class="fw-semibold text-dark fs-11" x-text="ops[opId].resource"></div>
                                                                <small class="text-muted fs-10">
                                                                    <span x-text="ops[opId].work_center"></span> &bull; 
                                                                    <span x-text="ops[opId].section"></span>
                                                                </small>
                                                            </div>
                                                        </div>
                                                        <div class="text-end font-monospace">
                                                            <div class="fw-bold fs-12" :class="selected['stage_' + stage.primary_id] == opId ? 'text-primary' : 'text-dark'" x-text="currencySym + ' ' + fmt4(ops[opId].cost_per_unit)"></div>
                                                            <small class="text-muted fs-10" x-text="formatDuration(ops[opId].total_minutes) + ' (' + formatDuration(ops[opId].setup_minutes) + ' setup + ' + formatDuration(ops[opId].processing_minutes) + ' run)'"></small>
                                                        </div>
                                                    </label>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Action Bar: Run Calculations Button -->
                        <div class="d-flex justify-content-between align-items-center p-3 bg-white rounded border mb-3 shadow-sm">
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-primary fw-bold px-4 d-inline-flex align-items-center gap-1.5 shadow-sm" @click="runCalculations()">
                                    <i class="feather-zap"></i> RUN CALCULATIONS
                                </button>
                                <button type="button" class="btn btn-light border text-muted px-3 fs-12" @click="resetToPrimary()">
                                    <i class="feather-rotate-ccw me-1"></i> Reset to Default Primary
                                </button>
                            </div>
                            <span class="fs-12 text-muted" x-show="!hasCalculated">
                                <i class="feather-arrow-left me-1"></i> Click button to execute calculation
                            </span>
                            <span class="badge bg-soft-success text-success fs-11 font-monospace px-2 py-1" x-show="hasCalculated">
                                <i class="feather-check-circle me-1"></i> Calculations Up-to-Date
                            </span>
                        </div>

                        <!-- Results Panel (Visible after Run Calculations) -->
                        <div x-show="hasCalculated" x-transition class="card border border-primary bg-white shadow-sm mb-0">
                            <div class="card-header bg-soft-primary border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-primary fs-12 text-uppercase font-monospace d-inline-flex align-items-center gap-1">
                                    <i class="feather-activity"></i> Simulation Scenario Results
                                </span>
                                <span class="badge bg-primary text-white fs-10 font-monospace">
                                    <span x-text="results.altCountSelected"></span> Alt Branch(es) Active
                                </span>
                            </div>
                            <div class="card-body p-3">
                                <div class="row g-3 text-center">
                                    <div class="col-sm-3 col-6">
                                        <div class="p-2.5 bg-light rounded border">
                                            <span class="text-muted fs-10 text-uppercase d-block mb-1">Simulated Cost / Unit</span>
                                            <span class="fs-18 fw-bold text-primary font-monospace" x-text="currencySym + ' ' + fmt4(results.totalCost)"></span>
                                        </div>
                                    </div>
                                    <div class="col-sm-3 col-6">
                                        <div class="p-2.5 bg-light rounded border">
                                            <span class="text-muted fs-10 text-uppercase d-block mb-1">Labor Cost</span>
                                            <span class="fs-16 fw-semibold text-dark font-monospace" x-text="currencySym + ' ' + fmt4(results.laborCost)"></span>
                                        </div>
                                    </div>
                                    <div class="col-sm-3 col-6">
                                        <div class="p-2.5 bg-light rounded border">
                                            <span class="text-muted fs-10 text-uppercase d-block mb-1">Machine Cost</span>
                                            <span class="fs-16 fw-semibold text-dark font-monospace" x-text="currencySym + ' ' + fmt4(results.machineCost)"></span>
                                        </div>
                                    </div>
                                    <div class="col-sm-3 col-6">
                                        <div class="p-2.5 bg-light rounded border">
                                            <span class="text-muted fs-10 text-uppercase d-block mb-1">Total Cycle Time</span>
                                            <span class="fs-16 fw-bold text-success font-monospace" x-text="formatDuration(results.cycleMinutes)"></span>
                                            <small class="text-muted d-block fs-10 font-monospace" x-text="'(' + fmt4(results.cycleMinutes) + 'm)'"></small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Variance Comparison Against Primary Baseline -->
                                <div class="mt-3 pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2 fs-12">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted fw-semibold">Variance from Primary Route:</span>
                                        <!-- Cost Delta -->
                                        <template x-if="results.costDelta == 0">
                                            <span class="badge bg-soft-secondary text-secondary">Identical to Primary Baseline</span>
                                        </template>
                                        <template x-if="results.costDelta < 0">
                                            <span class="badge bg-soft-success text-success font-monospace fw-bold">
                                                <i class="feather-trending-down me-0.5"></i> -<span x-text="currencySym"></span> <span x-text="fmt4(Math.abs(results.costDelta))"></span> (<span x-text="Math.abs(results.costDeltaPct).toFixed(1)"></span>% Savings)
                                            </span>
                                        </template>
                                        <template x-if="results.costDelta > 0">
                                            <span class="badge bg-soft-danger text-danger font-monospace fw-bold">
                                                <i class="feather-trending-up me-0.5"></i> +<span x-text="currencySym"></span> <span x-text="fmt4(results.costDelta)"></span> (+<span x-text="Number(results.costDeltaPct).toFixed(1)"></span>%)
                                            </span>
                                        </template>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted">Time Delta:</span>
                                        <template x-if="results.timeDelta == 0">
                                            <span class="badge bg-soft-secondary text-secondary font-monospace">0s (Identical)</span>
                                        </template>
                                        <template x-if="results.timeDelta < 0">
                                            <span class="badge bg-soft-success text-success font-monospace">
                                                <i class="feather-clock me-0.5"></i> <span x-text="formatDuration(Math.abs(results.timeDelta))"></span> Faster
                                            </span>
                                        </template>
                                        <template x-if="results.timeDelta > 0">
                                            <span class="badge bg-soft-warning text-warning font-monospace">
                                                <i class="feather-clock me-0.5"></i> +<span x-text="formatDuration(results.timeDelta)"></span> Longer
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-white border-top py-2 px-4 d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            window.fmt4 = function (v) { const n = Math.round((parseFloat(v) || 0) * 10000) / 10000; return Number.isInteger(n) ? String(n) : n.toFixed(4); }; // 4 decimals only when there are decimals
            function deleteDraftRouting() {
                var message = '{{ addslashes(__('production.confirm_delete_routing') ?: 'Are you sure you want to delete this draft routing?') }}';
                var options = {
                    title: '{{ addslashes(__('production.confirm_delete_routing_title') ?: 'Delete Routing') }}',
                    confirmButtonText: '{{ addslashes(__('production.delete') ?: 'Delete') }}',
                    confirmButtonClass: 'btn-danger',
                    variant: 'danger'
                };

                if (typeof confirmAction === 'function') {
                    confirmAction(message, function() {
                        document.getElementById('delete-draft-routing-header-form').submit();
                    }, options);
                } else if (confirm(message)) {
                    document.getElementById('delete-draft-routing-header-form').submit();
                }
            }

            function confirmApproveRouting() {
                confirmAction({
                    title: 'Approve & Activate Routing',
                    message: @js(__('production.confirm_approve_routing')),
                    confirmText: 'Approve & Activate',
                    variant: 'success',
                    onConfirm: function() {
                        document.getElementById('approveRoutingForm').submit();
                    }
                });
            }

            function routeSimulator(stages, ops, primaryTotalCost, primaryCycleTime, currencySym) {
                return {
                    stages: stages || [],
                    ops: ops || {},
                    primaryTotalCost: parseFloat(primaryTotalCost || 0),
                    primaryCycleTime: parseFloat(primaryCycleTime || 0),
                    currencySym: currencySym || '₹',
                    selected: {},
                    hasCalculated: false,
                    results: {
                        totalCost: 0,
                        laborCost: 0,
                        machineCost: 0,
                        subcontractCost: 0,
                        setupMinutes: 0,
                        processingMinutes: 0,
                        cycleMinutes: 0,
                        costDelta: 0,
                        costDeltaPct: 0,
                        timeDelta: 0,
                        activeCount: 0,
                        altCountSelected: 0
                    },

                    init() {
                        this.resetToPrimary();
                    },

                    formatDuration(minutes) {
                        if (!minutes || parseFloat(minutes) <= 0) return '0s';
                        if (parseFloat(minutes) * 60 < 1) return '< 1s'; // anything under 1 second
                        const totalSecs = Math.round(parseFloat(minutes) * 60);
                        const h = Math.floor(totalSecs / 3600);
                        const m = Math.floor((totalSecs % 3600) / 60);
                        const s = totalSecs % 60;
                        if (h > 0) {
                            if (m > 0 && s > 0) return h + 'h ' + m + 'm ' + String(s).padStart(2, '0') + 's';
                            if (m > 0) return h + 'h ' + m + 'm';
                            if (s > 0) return h + 'h ' + String(s).padStart(2, '0') + 's';
                            return h + 'h';
                        }
                        if (m > 0) {
                            if (s > 0) return m + 'm ' + String(s).padStart(2, '0') + 's';
                            return m + 'm';
                        }
                        return s + 's';
                    },

                    resetToPrimary() {
                        if (Array.isArray(this.stages)) {
                            this.stages.forEach(st => {
                                this.selected['stage_' + st.primary_id] = st.primary_id;
                            });
                        }
                        this.hasCalculated = false;
                    },

                    selectVariant(stagePrimaryId, opId) {
                        this.selected['stage_' + stagePrimaryId] = opId;
                        if (this.hasCalculated) {
                            this.hasCalculated = false;
                        }
                    },

                    runCalculations() {
                        let total = 0, labor = 0, mach = 0, sub = 0, setup = 0, proc = 0;
                        let altsChosen = 0;

                        if (Array.isArray(this.stages)) {
                            this.stages.forEach(st => {
                                const chosenId = this.selected['stage_' + st.primary_id] || st.primary_id;
                                const op = this.ops[chosenId];
                                if (op) {
                                    total += parseFloat(op.cost_per_unit || 0);
                                    labor += parseFloat(op.labor_cost || 0);
                                    mach += parseFloat(op.machine_cost || 0);
                                    sub += parseFloat(op.subcontract_cost || 0);
                                    setup += parseFloat(op.setup_minutes || 0);
                                    proc += parseFloat(op.processing_minutes || 0);
                                    if (op.is_alternative) {
                                        altsChosen++;
                                    }
                                }
                            });
                        }

                        const cycle = setup + proc;
                        const costDelta = total - this.primaryTotalCost;
                        const costDeltaPct = this.primaryTotalCost > 0 ? (costDelta / this.primaryTotalCost) * 100 : 0;
                        const timeDelta = cycle - this.primaryCycleTime;

                        this.results = {
                            totalCost: total,
                            laborCost: labor,
                            machineCost: mach,
                            subcontractCost: sub,
                            setupMinutes: setup,
                            processingMinutes: proc,
                            cycleMinutes: cycle,
                            costDelta: costDelta,
                            costDeltaPct: costDeltaPct,
                            timeDelta: timeDelta,
                            activeCount: this.stages.length,
                            altCountSelected: altsChosen
                        };

                        this.hasCalculated = true;
                    }
                };
            }
        </script>
    @endpush
@endsection
