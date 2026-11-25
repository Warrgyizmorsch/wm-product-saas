{{-- Operation Tracking row (Component Plan matrix) — one operation + its arrow drawer. UR-07: the drawer also holds the operations that make the
     sub-assembly this operation needs; this partial includes itself for them. Needs $order, $opTree, $cOp; optional $depth, $rowNo. --}}
@php
    $depth = $depth ?? 0;
    $rowNo = $rowNo ?? 1;
    $children = $opTree['children'][$cOp->id] ?? [];
    $subStat = $opTree['stats'][$cOp->id] ?? ['total' => 0, 'done' => 0, 'ready' => 0, 'running' => 0];
    $subOpen = !empty($children) && ($subStat['running'] ?? 0) > 0;   // opens on its own while something inside is running
@endphp
@if(!empty($children) && $depth < 6)
    {{-- UR-07: the operations that make the sub-assembly this operation needs — executed FIRST, so shown ABOVE it (arrow drawer, closed by default) --}}
    <tr id="cp-sub-{{ $cOp->id }}" class="collapse {{ $subOpen ? 'show' : '' }}">
        <td colspan="7" class="p-2 bg-light-subtle" style="border-left: 3px solid var(--bs-warning, #ffc107);">
            <div class="fw-bold text-dark fs-12 mb-2">
                <i class="feather-layers text-warning me-1"></i>{{ __('production.sub_assembly_ops_title') }}
                <span class="text-muted fw-normal fs-11 ms-1">— {{ __('production.sub_assembly_executed_first') }}</span>
            </div>
            @foreach($children as $grp)
                <div class="border rounded-3 mb-2 bg-white">
                    <div class="px-3 py-2 bg-light-subtle border-bottom d-flex flex-wrap align-items-center gap-2 fs-11">
                        <i class="feather-corner-down-right text-secondary"></i>
                        <strong class="text-dark">{{ __('production.makes_sub_assembly', ['name' => $grp['name']]) }}</strong>
                        @if(!empty($grp['sku']))<small class="text-muted font-monospace">({{ $grp['sku'] }})</small>@endif
                        <span class="badge bg-soft-secondary text-secondary">{{ __('production.sub_assembly_needs', ['required' => qty_format($grp['required']), 'stock' => qty_format($grp['from_stock']), 'produce' => qty_format($grp['to_produce'])]) }} {{ $grp['uom'] }}</span>
                        <span class="badge {{ $grp['done'] >= $grp['total'] ? 'bg-soft-success text-success' : 'bg-soft-info text-info' }}">{{ __('production.sub_assembly_ops_done', ['done' => $grp['done'], 'total' => $grp['total']]) }}</span>
                        @include('modules.production.orders.partials.sub-assembly-group-actions', ['op' => $cOp, 'grp' => $grp])
                    </div>
                    @if(empty($grp['shown_ids']))
                        @php
                            $ownerId = $opTree['claimedBy'][$grp['op_ids'][0] ?? 0] ?? null;
                            $owner = $ownerId ? $opTree['byId']->get($ownerId) : null;
                        @endphp
                        <div class="px-3 py-2 text-muted fs-11 fst-italic">{{ __(!empty($grp['borrowed']) ? 'production.sa_made_together_with' : 'production.sub_assembly_listed_under', ['op' => $owner ? ('OP' . $owner->sequence . ' · ' . $owner->name) : '—']) }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="bg-light text-uppercase fs-10 text-muted border-bottom">
                                    <tr>
                                        <th style="width: 25%">{{ __('production.item_component') }}</th>
                                        <th style="width: 17%">{{ __('production.operation') }}</th>
                                        <th style="width: 17%">{{ __('production.resource') }}</th>
                                        <th style="width: 13%" class="text-end">{{ __('production.requirement') }}</th>
                                        <th style="width: 12%">{{ __('production.schedule') }}</th>
                                        <th style="width: 11%">{{ __('production.execution') }}</th>
                                        <th style="width: 5%" class="text-center">{{ __('production.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($grp['shown_ids'] as $childId)
                                        @if($childOp = $opTree['byId']->get($childId))
                                            @include('modules.production.orders.partials.operation-tracking-row', ['cOp' => $childOp, 'depth' => $depth + 1, 'rowNo' => $loop->iteration])
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
                                                    @php
                                                        $itemProduct = $cOp->sourceProduct ?? $order->product;
                                                        // PX-13: target comes from the order snapshot; "per FG" ratio = target / order quantity
                                                        $targetQty = (float) ($cOp->target_produced_qty > 0 ? $cOp->target_produced_qty : $order->quantity_ordered);
                                                        $bomItemRatio = (float) $order->quantity_ordered > 0 ? $targetQty / (float) $order->quantity_ordered : 1.0;
                                                        $doneQty = (float) $cOp->quantity_produced;
                                                        $rejectedQty = (float) $cOp->quantity_rejected;
                                                        $scrappedQty = (float) $cOp->quantity_scrapped;
                                                        $reworkQty = (float) ($cOp->quantity_rework ?? 0);
                                                        $pendingQty = max(0.0, $targetQty - $doneQty);
                                                        $pct = $targetQty > 0 ? min(100.0, ($doneQty / $targetQty) * 100) : 0.0;
                                                        $itemUom = $itemProduct?->uom?->code ?? '';

                                                        $isSfg = ($itemProduct && (int) $itemProduct->id !== (int) $order->product_id);
                                                        $isExternal = (bool) $cOp->is_external;
                                                        $level = $cOp->bom_level ?? ($isSfg ? 2 : 1);
                                                    @endphp
                                                    <tr class="bg-white">
                                                        {{-- Cell 1: Item / Component --}}
                                                        <td class="bg-white">
                                                            <div class="d-flex align-items-start gap-2">
                                                                <button type="button"
                                                                    class="btn btn-xs btn-outline-primary p-1 border-0 text-primary bg-transparent rounded-2 shadow-none me-1 collapsed"
                                                                    data-bs-toggle="collapse"
                                                                    data-bs-target="#op-detail-{{ $cOp->id }}" aria-expanded="false"
                                                                    aria-controls="op-detail-{{ $cOp->id }}"
                                                                    title="Toggle operation details">
                                                                    <i
                                                                        class="feather-chevron-right fs-12 accordion-rotate-icon"></i>
                                                                </button>
                                                                <div>
                                                                    <div class="d-flex align-items-center gap-2 mb-0.5">
                                                                        <span
                                                                            class="badge bg-soft-primary text-primary border border-primary-subtle fs-10 py-0.5 px-1.5 fw-bold me-1">
                                                                            {{ $level > 1 ? 'L' . $level . ' SFG' : 'L1 FG' }}
                                                                        </span>
                                                                        <span
                                                                            class="fw-bold text-dark fs-12">{{ $itemProduct->name }}</span>
                                                                    </div>
                                                                    <div
                                                                        class="fs-10 text-muted font-monospace d-flex align-items-center gap-2">
                                                                        <span><i
                                                                                class="feather-tag me-0.5"></i>{{ $itemProduct->sku ?? 'SKU-N/A' }}</span>
                                                                        <span>&bull;</span>
                                                                        <span
                                                                            class="text-secondary fw-semibold">{{ qty_format($bomItemRatio) }}
                                                                            {{ $itemUom }}/FG</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        {{-- Cell 2: Operation --}}
                                                        <td class="bg-white">
                                                            <div class="fw-bold text-primary fs-12">
                                                                <span class="badge bg-light text-muted border fs-10 me-1">{{ __('production.step') }} {{ $opTree['exec'][$cOp->id] ?? '' }}</span>OP{{ $cOp->sequence ?? $rowNo * 10 }} &middot;
                                                                {{ $cOp->name }}
                                                            </div>
                                                            <div class="fs-10 text-muted">
                                                                @if($isExternal)
                                                                    <span
                                                                        class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 px-1.5 py-0.5">
                                                                        <i class="feather-truck me-1"></i>External Subcontract
                                                                    </span>
                                                                @else
                                                                    <span
                                                                        class="badge bg-soft-secondary text-secondary border fs-10 px-1.5 py-0.5">
                                                                        <i class="feather-cpu me-1"></i>Internal Routing
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        
                                                            {{-- UR-07: the operations that make the sub-assembly are executed first — their drawer is ABOVE this row --}}
                                                            @if(!empty($children))
                                                                <div class="fs-10 mt-1">
                                                                    <button type="button"
                                                                        class="btn btn-xs sub-toggle p-1 border-0 text-warning bg-transparent rounded-2 shadow-none me-1 {{ $subOpen ? '' : 'collapsed' }}"
                                                                        data-bs-toggle="collapse" data-bs-target="#cp-sub-{{ $cOp->id }}" aria-expanded="{{ $subOpen ? 'true' : 'false' }}"
                                                                        aria-controls="cp-sub-{{ $cOp->id }}" title="{{ __('production.sub_assembly_open_drawer') }}">
                                                                        <i class="feather-chevron-up fs-12 sub-rotate-icon"></i>
                                                                    </button>
                                                                    <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 px-1.5 py-0.5">
                                                                        <i class="feather-layers me-1"></i>{{ __('production.sub_ops_badge', ['total' => $subStat['total'], 'done' => $subStat['done']]) }}@if($subStat['ready'] > 0) · {{ __('production.sub_ops_ready', ['n' => $subStat['ready']]) }}@endif @if($subStat['running'] > 0) · {{ __('production.sub_ops_running', ['n' => $subStat['running']]) }}@endif
                                                                    </span>
                                                                </div>
                                                            @endif
                                                        </td>

                                                        {{-- Cell 3: Resource --}}
                                                        <td class="bg-white">
                                                            @if($isExternal)
                                                                <div class="fw-semibold text-dark fs-11"><i
                                                                        class="feather-users me-1 text-primary"></i>{{ $cOp->vendor->name ?? 'Outsourced Vendor' }}
                                                                </div>
                                                                <div class="fs-10 text-muted">Subcontract Vendor</div>
                                                            @else
                                                                <div class="fw-semibold text-dark fs-11"><i
                                                                        class="feather-grid me-1 text-primary"></i>{{ $cOp->workCenter->name ?? 'Workstation Unassigned' }}
                                                                </div>
                                                                <div class="fs-10 text-muted">
                                                                    @if($cOp->machine)
                                                                        <i class="feather-cpu me-1"></i>{{ $cOp->machine->name }}
                                                                    @elseif($cOp->section_manual_production_id)
                                                                        <span class="fst-italic"><i class="feather-users me-1"></i>{{ $cOp->manualProduction?->batch_name ?? "Manual production batch" }} <span class="badge bg-soft-secondary text-secondary fs-9">Manual batch</span></span>
                                                                    @elseif($cOp->work_center_id)
                                                                        <span class="fst-italic">Machine not assigned</span>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </td>

                                                        {{-- Cell 4: Requirement --}}
                                                        <td class="text-end bg-white">
                                                            <div class="fw-bold text-dark fs-13 font-monospace">
                                                                {{ qty_format($targetQty) }} <small
                                                                    class="text-muted fs-10">{{ $itemUom }}</small>
                                                            </div>
                                                            <div class="fs-10 text-muted">
                                                                {{ qty_format($bomItemRatio) }} &times;
                                                                {{ qty_format($order->quantity_ordered) }} FG
                                                            </div>
                                                        </td>

                                                        {{-- Cell 5: Schedule --}}
                                                        <td class="fs-11 text-muted bg-white">
                                                            @if($cOp->scheduleOperation)
                                                                @php
                                                                    $isOverdue = $cOp->scheduleOperation->planned_finish && $cOp->scheduleOperation->planned_finish->isPast() && $cOp->status !== 'completed';
                                                                @endphp
                                                                <div class="{{ $isOverdue ? 'text-danger fw-bold' : '' }}">
                                                                    <i
                                                                        class="feather-calendar me-1"></i>{{ $cOp->scheduleOperation->planned_start ? $cOp->scheduleOperation->planned_start->format('d M H:i') : '-' }}
                                                                </div>
                                                                <div class="{{ $isOverdue ? 'text-danger fw-bold' : '' }}">
                                                                    <i
                                                                        class="feather-flag me-1"></i>{{ $cOp->scheduleOperation->planned_finish ? $cOp->scheduleOperation->planned_finish->format('d M H:i') : '-' }}
                                                                </div>
                                                            @else
                                                                <span class="badge bg-soft-secondary text-secondary border fs-10">Not
                                                                    Scheduled</span>
                                                            @endif
                                                        </td>

                                                        {{-- Cell 6: Execution Progress --}}
                                                        <td class="bg-white">
                                                            <div class="d-flex justify-content-between fs-11 mb-1">
                                                                <span
                                                                    class="fw-bold text-success font-monospace">{{ qty_format($doneQty) }}</span>
                                                                <span class="text-muted font-monospace">/
                                                                    {{ qty_format($targetQty) }} {{ $itemUom }}</span>
                                                            </div>
                                                            <div class="progress style-3" style="height: 6px;">
                                                                <div class="progress-bar {{ $pct >= 100 ? 'bg-success' : ($pct > 0 ? 'bg-primary' : 'bg-secondary') }}"
                                                                    role="progressbar" style="width: {{ $pct }}%"
                                                                    aria-valuenow="{{ $pct }}" aria-valuemin="0"
                                                                    aria-valuemax="100"></div>
                                                            </div>
                                                            @if($rejectedQty > 0 || $scrappedQty > 0 || $reworkQty > 0)
                                                                <div class="fs-10 mt-1 d-flex flex-wrap gap-1">
                                                                    @if($rejectedQty > 0)
                                                                        <span class="badge bg-soft-danger text-danger fs-9 p-0.5 px-1">Rej:
                                                                            {{ number_format($rejectedQty, 0) }}</span>
                                                                    @endif
                                                                    @if($scrappedQty > 0)
                                                                        <span class="badge bg-soft-dark text-dark fs-9 p-0.5 px-1">Scrap:
                                                                            {{ number_format($scrappedQty, 0) }}</span>
                                                                    @endif
                                                                    @if($reworkQty > 0)
                                                                        <span
                                                                            class="badge bg-soft-warning text-warning fs-9 p-0.5 px-1">Rework:
                                                                            {{ number_format($reworkQty, 0) }}</span>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </td>

                                                        {{-- Cell 7: Status --}}
                                                        <td class="text-center bg-white">
                                                            @if($cOp->status === 'completed')
                                                                <span
                                                                    class="badge bg-soft-success text-success border border-success-subtle px-2 py-1 fs-11"><i
                                                                        class="feather-check-circle me-1"></i>Completed</span>
                                                            @elseif($cOp->status === 'running')
                                                                <span
                                                                    class="badge bg-soft-primary text-primary border border-primary-subtle px-2 py-1 fs-11"><i
                                                                        class="feather-play me-1"></i>Running</span>
                                                            @elseif($cOp->status === 'ready')
                                                                <span
                                                                    class="badge bg-soft-info text-info border border-info-subtle px-2 py-1 fs-11"><i
                                                                        class="feather-clock me-1"></i>Ready</span>
                                                            @elseif($cOp->status === 'qc_hold')
                                                                <span
                                                                    class="badge bg-soft-warning text-warning border border-warning-subtle px-2 py-1 fs-11"><i
                                                                        class="feather-shield me-1"></i>QC Hold</span>
                                                            @elseif($cOp->status === 'rework')
                                                                <span
                                                                    class="badge bg-soft-danger text-danger border border-danger-subtle px-2 py-1 fs-11"><i
                                                                        class="feather-alert-triangle me-1"></i>Rework</span>
                                                            @else
                                                                <span
                                                                    class="badge bg-soft-secondary text-secondary border border-secondary-subtle px-2 py-1 fs-11">{{ ucfirst($cOp->status) }}</span>
                                                            @endif
                                                        </td>
                                                    </tr>

                                                    {{-- Expandable Drawer Detail Panel --}}
                                                    <tr id="op-detail-{{ $cOp->id }}" class="collapse bg-white border-bottom">
                                                        <td colspan="7" class="p-3 bg-white">
                                                            <div
                                                                class="card border border-light-subtle shadow-sm mb-0 rounded-3 bg-light-subtle">
                                                                <div class="card-body p-3">
                                                                    <div class="row g-3 fs-11">
                                                                        {{-- Column 1: Material Inputs Required --}}
                                                                        <div class="col-md-4 border-end">
                                                                            <h6
                                                                                class="fw-bold text-dark fs-12 mb-2 d-flex align-items-center gap-1">
                                                                                <i class="feather-box text-primary"></i>Material
                                                                                Inputs Required
                                                                            </h6>
                                                                            @php
                                                                                // PX-01..04: one resolver (this order's BOM + chosen rows), required qty per this operation
                                                                                $opMat = $opDescribe[$cOp->id] ?? app(\App\Domains\Production\Services\OperationMaterialService::class)->describe($cOp);
                                                                            @endphp
                                                                            @if(!empty($opMat['materials']))
                                                                                <ul
                                                                                    class="list-group list-group-flush border rounded-2 fs-11">
                                                                                    @foreach($opMat['materials'] as $mRow)
                                                                                        <li
                                                                                            class="list-group-item py-1.5 px-2 bg-white">
                                                                                            <div class="d-flex justify-content-between align-items-center">
                                                                                                <span>{{ $mRow['name'] }}
                                                                                                    @if(!empty($mRow['shared_with']))
                                                                                                        <span class="badge bg-soft-info text-info fs-9 ms-1">Shared with {{ implode(', ', $mRow['shared_with']) }}</span>
                                                                                                    @endif
                                                                                                    @if($mRow['is_alternative'])
                                                                                                        <span class="badge bg-soft-warning text-warning fs-9 ms-1">Alternative</span>
                                                                                                    @endif
                                                                                                </span>
                                                                                                <span class="fw-bold text-dark font-monospace">{{ qty_format($mRow['required']) }} {{ $mRow['uom'] }}</span>
                                                                                            </div>
                                                                                            <div class="fs-10 text-muted">Order total: planned {{ qty_format($mRow['order_planned']) }} &middot; issued {{ qty_format($mRow['order_issued']) }} &middot; pending {{ qty_format($mRow['order_pending']) }}</div>
                                                                                        </li>
                                                                                    @endforeach
                                                                                </ul>
                                                                            @elseif(empty($opMat['sub_assemblies']))
                                                                                <div class="text-muted fs-11 fst-italic">No material is assigned to this operation</div>
                                                                            @endif
                                                                            
                                                                        </div>

                                                                        {{-- Column 2: Execution & Quality Metrics --}}
                                                                        <div class="col-md-4 border-end">
                                                                            <h6
                                                                                class="fw-bold text-dark fs-12 mb-2 d-flex align-items-center gap-1">
                                                                                <i
                                                                                    class="feather-check-square text-success"></i>Execution
                                                                                & Quality Summary
                                                                            </h6>
                                                                            <div class="bg-white p-2 border rounded-2">
                                                                                <div class="d-flex justify-content-between mb-1">
                                                                                    <span class="text-muted">Assigned
                                                                                        Operator:</span>
                                                                                    <strong
                                                                                        class="text-dark">{{ $cOp->operator->name ?? 'Unassigned' }}</strong>
                                                                                </div>
                                                                                <div class="d-flex justify-content-between mb-1">
                                                                                    <span class="text-muted">Target
                                                                                        Requirement:</span>
                                                                                    <strong
                                                                                        class="text-dark">{{ qty_format($targetQty) }}
                                                                                        {{ $itemUom }}</strong>
                                                                                </div>
                                                                                <div class="d-flex justify-content-between mb-1">
                                                                                    <span class="text-muted">Good Output
                                                                                        Produced:</span>
                                                                                    <strong
                                                                                        class="text-success">{{ qty_format($doneQty) }}
                                                                                        {{ $itemUom }}</strong>
                                                                                </div>
                                                                                <div class="d-flex justify-content-between">
                                                                                    <span class="text-muted">Pending Balance:</span>
                                                                                    <strong
                                                                                        class="text-primary">{{ qty_format($pendingQty) }}
                                                                                        {{ $itemUom }}</strong>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        {{-- Column 3: Contextual Quick Actions --}}
                                                                        <div class="col-md-4">
                                                                            <h6
                                                                                class="fw-bold text-dark fs-12 mb-2 d-flex align-items-center gap-1">
                                                                                <i class="feather-command text-info"></i>Contextual
                                                                                Action Shortcuts
                                                                            </h6>
                                                                            <div class="d-flex flex-wrap gap-2">
                                                                                @if(in_array($order->status, ['released', 'in_progress'], true))
                                                                                    <a href="{{ route('production.mes.operator.execution', ['op' => $cOp->id]) }}"
                                                                                        class="btn btn-sm btn-outline-primary fs-11 py-1 px-2">
                                                                                        <i class="feather-play me-1"></i>Open this operation in MES
                                                                                    </a>
                                                                                @endif
                                                                                @if(collect($opMat['materials'] ?? [])->contains(fn ($m) => $m['covered_by_issue'] + 0.00005 < $m['required']))
                                                                                    <a href="{{ route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-reservations']) }}"
                                                                                        class="btn btn-sm btn-outline-warning fs-11 py-1 px-2">
                                                                                        <i class="feather-package me-1"></i>Issue material
                                                                                    </a>
                                                                                @endif
                                                                                @if($isExternal)
                                                                                    <a href="{{ route('production.subcontract.delivery-challans.index') }}"
                                                                                        class="btn btn-sm btn-outline-primary fs-11 py-1 px-2">
                                                                                        <i class="feather-file-text me-1"></i>Manage
                                                                                        Subcontract
                                                                                    </a>
                                                                                @endif
                                                                                <a href="{{ route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-operations']) }}"
                                                                                    class="btn btn-sm btn-outline-secondary fs-11 py-1 px-2">
                                                                                    <i class="feather-edit me-1"></i>View Operations
                                                                                    Tab
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                