{{-- Operations tab row — one operation. UR-07: the operations that make the sub-assembly this operation needs are executed FIRST, so their drawer is
     shown ABOVE the operation (closed by default, opens by the arrow; open on its own when something inside is running). Recursive.
     Needs $order, $opTree, $opEvalById, $schedOpsMap, $op; optional $depth. --}}
@php
    $depth = $depth ?? 0;
    $subGroups = $opTree['children'][$op->id] ?? [];
    $subStat = $opTree['stats'][$op->id] ?? ['total' => 0, 'done' => 0, 'ready' => 0, 'running' => 0];
    $subOpen = !empty($subGroups) && ($subStat['running'] ?? 0) > 0;
    $toneClass = $toneClass ?? fn (?string $tone) => match($tone) {
        'success' => 'bg-soft-success text-success',
        'warning' => 'bg-soft-warning text-dark',
        'danger' => 'bg-soft-danger text-danger',
        'info' => 'bg-soft-info text-info',
        'primary' => 'bg-soft-primary text-primary',
        default => 'bg-soft-secondary text-muted',
    };
@endphp
@if(!empty($subGroups) && $depth < 6)
    <tr id="ops-sub-{{ $op->id }}" class="collapse {{ $subOpen ? 'show' : '' }}">
        <td colspan="10" class="p-2 bg-light-subtle" style="border-left: 3px solid var(--bs-warning, #ffc107);">
            <div class="fw-bold text-dark fs-12 mb-2">
                <i class="feather-layers text-warning me-1"></i>{{ __('production.sub_assembly_ops_title') }}
                <span class="text-muted fw-normal fs-11 ms-1">— {{ __('production.sub_assembly_executed_first') }}</span>
            </div>
            @foreach($subGroups as $grp)
                <div class="border rounded-3 mb-2 bg-white">
                    <div class="px-3 py-2 bg-light-subtle border-bottom d-flex flex-wrap align-items-center gap-2 fs-11">
                        <i class="feather-corner-down-right text-secondary"></i>
                        <strong class="text-dark">{{ __('production.makes_sub_assembly', ['name' => $grp['name']]) }}</strong>
                        @if(!empty($grp['sku']))<small class="text-muted font-monospace">({{ $grp['sku'] }})</small>@endif
                        <span class="badge bg-soft-secondary text-secondary">{{ __('production.sub_assembly_needs', ['required' => qty_format($grp['required']), 'stock' => qty_format($grp['from_stock']), 'produce' => qty_format($grp['to_produce'])]) }} {{ $grp['uom'] }}</span>
                        <span class="badge {{ $grp['done'] >= $grp['total'] ? 'bg-soft-success text-success' : 'bg-soft-info text-info' }}">{{ __('production.sub_assembly_ops_done', ['done' => $grp['done'], 'total' => $grp['total']]) }}</span>
                        @include('modules.production.orders.partials.sub-assembly-group-actions', ['op' => $op, 'grp' => $grp])
                    </div>
                    @if(empty($grp['shown_ids']))
                        @php
                            $ownerId = $opTree['claimedBy'][$grp['op_ids'][0] ?? 0] ?? null;
                            $owner = $ownerId ? $opTree['byId']->get($ownerId) : null;
                        @endphp
                        <div class="px-3 py-2 text-muted fs-11 fst-italic">{{ __(!empty($grp['borrowed']) ? 'production.sa_made_together_with' : 'production.sub_assembly_listed_under', ['op' => $owner ? ('OP' . $owner->sequence . ' · ' . $owner->name) : '—']) }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="erp-thin-table w-100 mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:5%" class="text-center">{{ __('production.seq') }}</th>
                                        <th style="width:18%">{{ __('production.operation') }}</th>
                                        <th style="width:12%">{{ __('production.work_center') }}</th>
                                        <th style="width:12%">{{ __('production.resources') }}</th>
                                        <th style="width:14%">{{ __('production.operator') }}</th>
                                        <th style="width:10%" class="text-center">{{ __('production.planned_setup_run') }}</th>
                                        <th style="width:10%" class="text-center">{{ __('production.actual_setup_run') }}</th>
                                        <th style="width:10%" class="text-center">{{ __('production.produced_scrap') }}</th>
                                        <th style="width:7%">{{ __('production.status') }}</th>
                                        <th style="width:3%" class="text-end">{{ __('production.log') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($grp['shown_ids'] as $childId)
                                        @if($childOp = $opTree['byId']->get($childId))
                                            @include('modules.production.orders.partials.operations-row', ['op' => $childOp, 'depth' => $depth + 1])
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
        </td>
    </tr>
@endif
                                            <tr>
                                                <td class="text-center fw-semibold text-muted">{{ $opTree['exec'][$op->id] ?? '' }}</td>
                                                <td>
                                                    @if(!empty($subGroups))
                                                        <button type="button"
                                                            class="btn btn-xs sub-toggle p-1 border-0 text-warning bg-transparent rounded-2 shadow-none me-1 {{ $subOpen ? '' : 'collapsed' }}"
                                                            data-bs-toggle="collapse" data-bs-target="#ops-sub-{{ $op->id }}" aria-expanded="{{ $subOpen ? 'true' : 'false' }}"
                                                            aria-controls="ops-sub-{{ $op->id }}" title="{{ __('production.sub_assembly_open_drawer') }}">
                                                            <i class="feather-chevron-up fs-12 sub-rotate-icon"></i>
                                                        </button>
                                                        <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 mb-1">
                                                            <i class="feather-layers me-1"></i>{{ __('production.sub_ops_badge', ['total' => $subStat['total'], 'done' => $subStat['done']]) }}@if($subStat['ready'] > 0) · {{ __('production.sub_ops_ready', ['n' => $subStat['ready']]) }}@endif @if($subStat['running'] > 0) · {{ __('production.sub_ops_running', ['n' => $subStat['running']]) }}@endif
                                                        </span><br>
                                                    @endif
                                                    @if($depth === 0 && ($op->bom_level ?? 1) > 1)
                                                        <span class="badge bg-soft-info text-info border border-info-subtle font-monospace mb-1" style="font-size: 10px;"><i
                                                                class="feather-box me-1"></i>{{ $op->sourceProduct?->name ?? '' }} ({{ __('production.level') }} {{ $op->bom_level }})</span><br>
                                                    @endif
                                                    <span class="fw-semibold text-dark">{{ html_entity_decode($op->name ?? '', ENT_QUOTES, 'UTF-8') }}</span>
                                                    <br><small class="text-muted font-monospace">{{ $op->operation_number }}</small>
                                                    @php
                                                        $opIsQcRequired = (bool) ($op->quality_required || ($op->routingOperation?->quality_required ?? false));
                                                    @endphp
                                                    @if($opIsQcRequired)
                                                        <span class="badge bg-soft-info text-info border border-info-subtle font-monospace ms-1"
                                                            title="Quality Check Required for this operation">
                                                            <i class="feather-shield me-1"></i>{{ __('production.qc_required') }}
                                                        </span>
                                                    @endif
                                                    @if($op->is_external)
                                                        <span class="badge bg-soft-warning text-dark border border-warning ms-1"><i
                                                                class="feather-external-link me-1"></i>{{ __('production.subcontract') }}</span>
                                                        @if($op->vendor_supplies_materials)
                                                            <span class="badge bg-soft-info text-info border ms-1" title="The vendor uses its own materials"><i class="feather-package me-1"></i>Vendor's materials</span>
                                                        @endif
                                                        @if($op->needsDispatchSlip())
                                                            <div class="mt-1">
                                                                @php
                                                                    $existingChallan = $op->latestDeliveryChallan;
                                                                @endphp
                                                                @if($existingChallan)
                                                                    @if($existingChallan->status === 'draft')
                                                                        <a href="{{ route('production.subcontract.delivery-challans.show', $existingChallan->id) }}"
                                                                            class="badge bg-soft-warning text-dark border border-warning fs-10 text-decoration-none"
                                                                            title="Draft Gate Pass pending dispatch">
                                                                            <i class="feather-clock me-1"></i>{{ __('production.draft_gate_pass') }}
                                                                            (#{{ $existingChallan->challan_number }})
                                                                        </a>
                                                                    @elseif($existingChallan->status === 'dispatched')
                                                                        <a href="{{ route('production.subcontract.delivery-challans.show', $existingChallan->id) }}"
                                                                            class="badge bg-soft-info text-info border border-info-subtle fs-10 text-decoration-none"
                                                                            title="Material Dispatched to Vendor">
                                                                            <i class="feather-truck me-1"></i>{{ __('production.dispatched') }}
                                                                            (#{{ $existingChallan->challan_number }})
                                                                        </a>
                                                                    @else
                                                                        <a href="{{ route('production.subcontract.delivery-challans.show', $existingChallan->id) }}"
                                                                            class="badge bg-soft-success text-success border border-success-subtle fs-10 text-decoration-none"
                                                                            title="Delivery Challan Completed">
                                                                            <i class="feather-check-circle me-1"></i>{{ __('production.challan_completed') }}
                                                                            (#{{ $existingChallan->challan_number }})
                                                                        </a>
                                                                    @endif
                                                                @else
                                                                    <a href="{{ route('production.subcontract.delivery-challans.create', ['production_order_id' => $order->id, 'operation_id' => $op->id]) }}"
                                                                        class="badge bg-soft-primary text-primary border border-primary-subtle fs-10 text-decoration-none"
                                                                        title="Generate Subcontract Material Delivery Challan / Gate Pass to Vendor">
                                                                        <i class="feather-truck me-1"></i>{{ __('production.dispatch_material_challan') }}
                                                                    </a>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($op->is_external)
                                                        <span class="fw-semibold text-dark"><i
                                                                class="feather-truck me-1 text-primary"></i>{{ $op->vendor->name ?? __('production.subcontract_vendor') }}</span>
                                                        <div class="fs-10 text-muted">Processing: {{ \App\Domains\Production\Services\SubcontractDates::formatHours($op->vendor_processing_hours) }}
                                                        </div>
                                                    @else
                                                        {{ $op->workCenter?->name ?? 'Unassigned' }}
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($op->is_external)
                                                        <span class="badge bg-light text-muted">N/A (Subcontract)</span>
                                                    @else
                                                        @php $opRes = $opEvalById->get($op->id)['machine'] ?? null; @endphp
                                                        @if($opRes && !empty($opRes['resources']))
                                                            @foreach($opRes['resources'] as $resItem)
                                                                <div>
                                                                    <span class="badge {{ $toneClass($resItem['tone'] ?? null) }}">{{ $resItem['state_label'] }}</span>
                                                                    <span class="fw-semibold fs-11">{{ $resItem['name'] }}</span>
                                                                    <small class="text-muted">({{ $resItem['type'] === 'manual_batch' ? __('production.resource_manual_batch') : __('production.machine') }})</small>
                                                                </div>
                                                                @if(!empty($resItem['detail']))
                                                                    <div class="text-muted fs-10">{{ $resItem['detail'] }}</div>
                                                                @endif
                                                            @endforeach
                                                        @elseif($op->machine)
                                                            {{ $op->machine->name }}
                                                        @elseif($op->section_manual_production_id)
                                                            {{ $op->manualProduction?->batch_name ?? '—' }} <small class="text-muted">({{ __('production.resource_manual_batch') }})</small>
                                                        @else
                                                            <span class="badge bg-soft-secondary text-muted">{{ __('production.resource_not_assigned') }}</span>
                                                        @endif
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($op->is_external)
                                                        <span class="text-muted fs-11"><i class="feather-user-check me-1"></i>External
                                                            Vendor</span>
                                                    @else
                                                        @php
                                                            $activeAssignment = $op->operatorAssignments->whereIn('status', ['assigned', 'accepted'])->first();
                                                            $schedOp = $schedOpsMap->get($op->id);
                                                            $isOpStarted = in_array($op->status, ['running', 'paused', 'completed'])
                                                                || ($schedOp && in_array($schedOp->status, ['running', 'paused', 'completed']))
                                                                || !empty($op->actual_start_time)
                                                                || ($schedOp && !empty($schedOp->actual_start))
                                                                || ((float) ($op->quantity_produced ?? 0) > 0);
                                                        @endphp
                                                        @if($activeAssignment)
                                                            <div>
                                                                <span
                                                                    class="fw-semibold text-dark fs-12">{{ $activeAssignment->user->name }}</span>
                                                                <div class="d-flex align-items-center gap-1.5">
                                                                    @if($activeAssignment->status === 'accepted')
                                                                        <span
                                                                            class="badge bg-soft-success text-success fs-9 py-0.5 px-1">Accepted</span>
                                                                    @else
                                                                        <span
                                                                            class="badge bg-soft-warning text-warning fs-9 py-0.5 px-1">Pending</span>
                                                                    @endif
                                                                    @if(($order->isReleased() || $order->isInProgress()) && $op->status !== 'completed' && !$isOpStarted)
                                                                        @if($order->schedules->isNotEmpty())
                                                                            <button type="button"
                                                                                class="btn btn-xs btn-outline-secondary p-0.5 border-0"
                                                                                title="Reassign" data-bs-toggle="modal"
                                                                                data-bs-target="#orderAssignOperatorModal"
                                                                                onclick="document.getElementById('assign_op_id').value = '{{ $op->id }}';">
                                                                                <i class="feather-edit fs-11"></i>
                                                                            </button>
                                                                        @else
                                                                            <button type="button"
                                                                                class="btn btn-xs btn-outline-secondary p-0.5 border-0" disabled
                                                                                title="Generate a schedule first to enable operator assignment"
                                                                                data-bs-toggle="tooltip">
                                                                                <i class="feather-edit fs-11"></i>
                                                                            </button>
                                                                        @endif
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div class="d-flex align-items-center gap-1.5">
                                                                <span class="text-muted fs-12">—</span>
                                                                @if(($order->isReleased() || $order->isInProgress()) && $op->status !== 'completed')
                                                                    @if($order->schedules->isNotEmpty())
                                                                        <button type="button" class="btn btn-xs btn-outline-primary py-0.5 px-1.5"
                                                                            data-bs-toggle="modal" data-bs-target="#orderAssignOperatorModal"
                                                                            onclick="document.getElementById('assign_op_id').value = '{{ $op->id }}';">
                                                                            <i class="feather-user-plus me-1"></i> Assign
                                                                        </button>
                                                                    @else
                                                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0.5 px-1.5"
                                                                            disabled title="Generate a schedule first to enable operator assignment"
                                                                            data-bs-toggle="tooltip">
                                                                            <i class="feather-user-plus me-1"></i> Assign
                                                                        </button>
                                                                    @endif
                                                                @endif
                                                            </div>
                                                        @endif
                                                    @endif
                                                </td>
                                                <td class="text-center text-muted">
                                                    @if($op->is_external)
                                                        Processing: {{ \App\Domains\Production\Services\SubcontractDates::formatHours($op->vendor_processing_hours) }}
                                                    @else
                                                        {{ format_duration($op->setup_time_planned) }} / {{ format_duration($op->processing_time_planned) }}
                                                    @endif
                                                </td>
                                                <td class="text-center fw-semibold text-dark">
                                                    @if($op->is_external)
                                                        Dispatch: {{ \App\Domains\Production\Services\SubcontractDates::formatHours($op->vendor_dispatch_hours) }} / Receipt:
                                                        {{ \App\Domains\Production\Services\SubcontractDates::formatHours($op->vendor_receipt_hours) }}
                                                    @else
                                                        {{ format_duration($op->setup_time_actual) }} / {{ format_duration($op->processing_time_actual) }}
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <span
                                                        class="text-success fw-bold">{{ qty_format($op->quantity_produced) }}</span>
                                                    /
                                                    <span
                                                        class="text-danger">{{ qty_format($op->quantity_scrapped + $op->quantity_rejected) }}</span>
                                                </td>
                                                <td>
                                                    @if($order->isPreRelease())
                                                        <span class="badge bg-light text-dark border">{{ __('production.draft_not_released') }}</span>
                                                    @else
                                                        @if($op->status === 'waiting')
                                                        <span class="badge bg-secondary text-white">Waiting</span>
                                                    @elseif($op->status === 'ready')
                                                        <span class="badge bg-primary text-white">Ready</span>
                                                    @elseif($op->status === 'running')
                                                        <span class="badge bg-info text-white">Running</span>
                                                    @elseif($op->status === 'vendor_dispatched')
                                                        <span class="badge bg-soft-info text-info border border-info-subtle"><i
                                                                class="feather-truck me-1"></i>At Vendor (Dispatched)</span>
                                                    @elseif($op->status === 'paused')
                                                        <span class="badge bg-warning text-dark">Paused</span>
                                                    @elseif($op->status === 'completed')
                                                        <span
                                                            class="badge bg-success text-white">{{ __('production.completed') }}</span>
                                                    @else
                                                        <span
                                                            class="badge bg-light text-dark">{{ ucfirst(str_replace('_', ' ', $op->status)) }}</span>
                                                    @endif
                                                        @php $opFlow = $opEvalById->get($op->id)['dependency'] ?? null; @endphp
                                                        @if($opFlow && in_array($opFlow['state'] ?? '', ['waiting', 'partial'], true))
                                                            <div class="fs-10 mt-1 fw-semibold">{{ $opFlow['label'] }}</div>
                                                            @if(!empty($opFlow['detail']))
                                                                <div class="text-muted fs-10">{{ $opFlow['detail'] }}</div>
                                                            @endif
                                                        @endif
                                                    @endif

                                                    @if($op->scheduleOperation && ($op->scheduleOperation->status === 'running' || $op->scheduleOperation->status === 'paused'))
                                                        <div class="live-timer" data-status="{{ $op->scheduleOperation->status }}"
                                                            data-start="{{ $op->scheduleOperation->actual_start ? $op->scheduleOperation->actual_start->toIso8601String() : '' }}"
                                                            data-paused-at="{{ $op->scheduleOperation->last_paused_at ? $op->scheduleOperation->last_paused_at->toIso8601String() : '' }}"
                                                            data-accumulated-paused="{{ $op->scheduleOperation->accumulated_paused_seconds ?? 0 }}">
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    @if($op->is_external)
                                                        <a href="?tab=vtab-subcontract"
                                                            class="btn btn-sm btn-outline-warning py-1 px-2 fs-11 d-inline-flex align-items-center gap-1">
                                                            <i class="feather-truck"></i> Subcontract
                                                        </a>
                                                    @elseif(($order->isReleased() || $order->isInProgress()) && $op->status !== 'completed' && ($logWait = $opEvalById->get($op->id)['dependency'] ?? null) && ($logWait['state'] ?? '') === 'waiting')
                                                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 fs-11 d-inline-flex align-items-center gap-1" disabled
                                                            data-bs-toggle="tooltip" title="{{ $logWait['label'] }}{{ !empty($logWait['detail']) ? ' — ' . $logWait['detail'] : '' }}">
                                                            <i class="feather-lock"></i> {{ __('production.log_progress') }}
                                                        </button>
                                                    @elseif(($order->isReleased() || $order->isInProgress()) && $op->status !== 'completed')
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-primary py-1 px-2 fs-11 d-inline-flex align-items-center gap-1"
                                                            data-bs-toggle="modal" data-bs-target="#progressModal"
                                                            onclick="var selectEl = document.getElementById('op_select_id'); if (selectEl) { selectEl.value = '{{ $op->id }}'; selectEl.dispatchEvent(new Event('change')); if (window.jQuery && jQuery().select2) { $(selectEl).trigger('change'); } }">
                                                            <i class="feather-edit-3"></i> {{ __('production.log_progress') }}
                                                        </button>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                            </tr>
