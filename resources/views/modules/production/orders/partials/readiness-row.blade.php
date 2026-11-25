{{-- Readiness table row — one operation. UR-07: an operation that needs a sub-assembly has an arrow drawer with the readiness of the operations that
     make it; this partial includes itself for them. Needs $opEval, $opTree, $opEvalById, $formatDimStatus, $toneClass, $toneOfStatus; optional $depth. --}}
@php
    $depth = $depth ?? 0;
    $rdChildren = $opTree['children'][$opEval['operation_id']] ?? [];
    $rdStat = $opTree['stats'][$opEval['operation_id']] ?? ['total' => 0, 'done' => 0, 'ready' => 0, 'running' => 0];
    $rdOpen = !empty($rdChildren) && ($rdStat['running'] ?? 0) > 0;   // opens on its own while something inside is running
@endphp
@if(!empty($rdChildren) && $depth < 6)
    {{-- UR-07: the operations that make the sub-assembly this operation needs — executed FIRST, so shown ABOVE it (arrow drawer, closed by default) --}}
    <tr id="rd-sub-{{ $opEval['operation_id'] }}" class="collapse {{ $rdOpen ? 'show' : '' }}">
        <td colspan="8" class="p-2 bg-light-subtle" style="border-left: 3px solid var(--bs-warning, #ffc107);">
            <div class="fw-bold text-dark fs-12 mb-2">
                <i class="feather-layers text-warning me-1"></i>{{ __('production.sub_assembly_ops_title') }}
                <span class="text-muted fw-normal fs-11 ms-1">— {{ __('production.sub_assembly_executed_first') }}. {{ __('production.sub_assembly_parent_waits') }}</span>
            </div>
            @foreach($rdChildren as $rdGrp)
                <div class="border rounded-3 mb-2 bg-white">
                    <div class="px-3 py-2 bg-light-subtle border-bottom d-flex flex-wrap align-items-center gap-2 fs-11">
                        <i class="feather-corner-down-right text-secondary"></i>
                        <strong class="text-dark">{{ $rdGrp['name'] }}</strong>
                        <span class="badge {{ $rdGrp['done'] >= $rdGrp['total'] ? 'bg-soft-success text-success' : 'bg-soft-info text-info' }}">{{ __('production.sub_assembly_ops_done', ['done' => $rdGrp['done'], 'total' => $rdGrp['total']]) }}</span>
                    </div>
                    @if(empty($rdGrp['shown_ids']))
                        @php
                            $rdOwnerId = $opTree['claimedBy'][$rdGrp['op_ids'][0] ?? 0] ?? null;
                            $rdOwner = $rdOwnerId ? $opTree['byId']->get($rdOwnerId) : null;
                        @endphp
                        <div class="px-3 py-2 text-muted fs-11 fst-italic">{{ __(!empty($rdGrp['borrowed']) ? 'production.sa_made_together_with' : 'production.sub_assembly_listed_under', ['op' => $rdOwner ? ('OP' . $rdOwner->sequence . ' · ' . $rdOwner->name) : '—']) }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle fs-12 mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:5%">{{ __('production.seq') }}</th>
                                        <th style="width:20%">{{ __('production.operation') }}</th>
                                        <th style="width:12%">{{ __('production.material') }}</th>
                                        <th style="width:14%">{{ __('production.operation_flow') }}</th>
                                        <th style="width:16%">{{ __('production.resources') }}</th>
                                        <th style="width:12%">{{ __('production.subcontract') }}</th>
                                        <th style="width:12%">{{ __('production.quality') }}</th>
                                        <th style="width:13%">{{ __('production.batch_lot') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rdGrp['shown_ids'] as $rdChildId)
                                        @if($rdChildEval = $opEvalById->get($rdChildId))
                                            @include('modules.production.orders.partials.readiness-row', ['opEval' => $rdChildEval, 'depth' => $depth + 1])
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
                                                <td class="fw-bold text-nowrap">{{ $opEval['sequence'] }}</td>
                                                <td>
                                                    @if(!empty($rdChildren))
                                                        <button type="button"
                                                            class="btn btn-xs sub-toggle p-1 border-0 text-warning bg-transparent rounded-2 shadow-none me-1 {{ $rdOpen ? '' : 'collapsed' }}"
                                                            data-bs-toggle="collapse" data-bs-target="#rd-sub-{{ $opEval['operation_id'] }}" aria-expanded="{{ $rdOpen ? 'true' : 'false' }}"
                                                            aria-controls="rd-sub-{{ $opEval['operation_id'] }}" title="{{ __('production.sub_assembly_open_drawer') }}">
                                                            <i class="feather-chevron-up fs-12 sub-rotate-icon"></i>
                                                        </button>
                                                        <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 mb-1">
                                                            <i class="feather-layers me-1"></i>{{ __('production.sub_ops_badge', ['total' => $rdStat['total'], 'done' => $rdStat['done']]) }}@if($rdStat['ready'] > 0) · {{ __('production.sub_ops_ready', ['n' => $rdStat['ready']]) }}@endif @if($rdStat['running'] > 0) · {{ __('production.sub_ops_running', ['n' => $rdStat['running']]) }}@endif
                                                        </span>
                                                    @endif
                                                    <div class="fw-bold text-dark">{{ $opEval['operation_name'] }}</div>
                                                    <small class="text-muted">{{ __('production.target') }}: {{ qty_format($opEval['target_qty']) }}
                                                        | {{ __('production.ready') }}: {{ qty_format($opEval['ready_qty']) }}</small>
                                                </td>
                                                <td>
                                                    {{-- UR-01: direct materials — target -> need for 1 unit -> issued quantity --}}
                                                    @php $rdMat = $opEval['material'] ?? []; @endphp
                                                    <span class="badge {{ $toneClass($rdMat['tone'] ?? $toneOfStatus($rdMat['status'] ?? null)) }}">
                                                        {{ $rdMat['label'] ?? $formatDimStatus($rdMat['status'] ?? null) }}
                                                    </span>
                                                    @if(($rdMat['status'] ?? '') === 'PARTIALLY_READY' && isset($rdMat['materials']))
                                                        <div class="text-muted fs-10 mt-1">{{ __('production.material_can_make', ['ready' => qty_format($opEval['ready_qty']), 'target' => qty_format($opEval['target_qty'])]) }}</div>
                                                    @endif
                                                    @foreach(collect($rdMat['materials'] ?? [])->where('state', '!=', 'ready')->take(3) as $rdLine)
                                                        @php $rdIsSub = !empty($rdLine['sub_assembly']); @endphp
                                                        <div class="text-muted fs-10">
                                                            <strong>{{ $rdLine['name'] }}</strong>@if($rdIsSub) <span class="badge bg-soft-warning text-warning fs-9">{{ __('production.sub_assembly_tag') }}</span>@endif:
                                                            @if($rdLine['state'] === 'blocked')
                                                                {{ __($rdIsSub ? 'production.material_one_unit_needs_available' : 'production.material_one_unit_needs', ['need' => qty_format($rdLine['per_unit']), 'issued' => qty_format($rdLine['issued'])]) }}
                                                            @else
                                                                {{ __($rdIsSub ? 'production.material_available_of' : 'production.material_issued_of', ['issued' => qty_format($rdLine['issued']), 'required' => qty_format($rdLine['required'])]) }}
                                                            @endif
                                                            @if($rdIsSub)
                                                                {{-- RV-03: what the sub-assembly line is made of — the part from stock and the part made by the operations above --}}
                                                                @php
                                                                    $rdParts = [];
                                                                    if (($rdLine['from_stock'] ?? 0) > 0) {
                                                                        $rdParts[] = __('production.sa_line_from_stock', ['stock' => qty_format($rdLine['from_stock']), 'issued' => qty_format($rdLine['stock_issued'] ?? 0)]);
                                                                    }
                                                                    if (($rdLine['to_produce'] ?? 0) > 0) {
                                                                        $rdAllDone = ($rdLine['makers_total'] ?? 0) > 0 && ($rdLine['makers_done'] ?? 0) >= $rdLine['makers_total'];
                                                                        $rdParts[] = $rdAllDone
                                                                            ? __('production.sa_line_made_done', ['make' => qty_format($rdLine['to_produce']), 'made' => qty_format($rdLine['made'] ?? 0)])
                                                                            : __('production.sa_line_made', ['make' => qty_format($rdLine['to_produce']), 'done' => $rdLine['makers_done'] ?? 0, 'ops' => $rdLine['makers_total'] ?? 0]);
                                                                    }
                                                                @endphp
                                                                <div class="fs-9">{{ __('production.sa_line_total', ['total' => qty_format($rdLine['required']), 'parts' => implode(' + ', $rdParts)]) }}</div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                    @php $rdMore = collect($rdMat['materials'] ?? [])->where('state', '!=', 'ready')->count() - 3; @endphp
                                                    @if($rdMore > 0)
                                                        <div class="text-muted fs-10">+{{ $rdMore }} {{ __('production.more') }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{-- UR-02: where the operation stands in the sequence, in words (a wait is never red "Blocked") --}}
                                                    @php $rdFlow = $opEval['dependency'] ?? []; @endphp
                                                    <span class="badge {{ $toneClass($rdFlow['tone'] ?? $toneOfStatus($rdFlow['status'] ?? null)) }}">
                                                        @if(($rdFlow['state'] ?? '') === 'waiting')<i class="feather-clock me-1"></i>@endif
                                                        {{ $rdFlow['label'] ?? $formatDimStatus($rdFlow['status'] ?? null) }}
                                                    </span>
                                                    @if(!empty($rdFlow['detail']))
                                                        <div class="text-muted fs-10 mt-1">{{ $rdFlow['detail'] }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{-- UR-03: every resource the operation needs (machine or manual batch): ready or not, and whether another operation is on it --}}
                                                    @php $rdRes = $opEval['machine'] ?? []; @endphp
                                                    @forelse(($rdRes['resources'] ?? []) as $rdItem)
                                                        <div>
                                                            <span class="badge {{ $toneClass($rdItem['tone'] ?? null) }}">{{ $rdItem['state_label'] }}</span>
                                                            <span class="fw-semibold fs-11">{{ $rdItem['name'] }}</span>
                                                            <small class="text-muted">({{ $rdItem['type'] === 'manual_batch' ? __('production.resource_manual_batch') : __('production.machine') }})</small>
                                                        </div>
                                                        @if(!empty($rdItem['detail']))
                                                            <div class="text-muted fs-10">{{ $rdItem['detail'] }}</div>
                                                        @endif
                                                    @empty
                                                        <span class="badge {{ $toneClass($rdRes['tone'] ?? $toneOfStatus($rdRes['status'] ?? null)) }}">
                                                            {{ $rdRes['label'] ?? $formatDimStatus($rdRes['status'] ?? null) }}
                                                        </span>
                                                    @endforelse
                                                </td>
                                                <td>
                                                    {{-- UR-04: only "Subcontracted" / "Not subcontracted" for now --}}
                                                    @php $rdSub = $opEval['subcontract'] ?? []; @endphp
                                                    <span class="badge {{ $toneClass($rdSub['tone'] ?? 'secondary') }}">
                                                        {{ $rdSub['label'] ?? $formatDimStatus($rdSub['status'] ?? null) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge {{ ($opEval['quality']['status'] ?? '') === 'READY' ? 'bg-soft-success text-success' : 'bg-soft-warning text-dark' }}">
                                                        {{ $formatDimStatus($opEval['quality']['status'] ?? null) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge {{ ($opEval['tracking']['status'] ?? '') === 'READY' ? 'bg-soft-success text-success' : 'bg-soft-secondary text-muted' }}">
                                                        {{ $formatDimStatus($opEval['tracking']['status'] ?? null) }}
                                                    </span>
                                                </td>
                                            </tr>
