{{-- UR-10 (M-22 .. M-24): "produce all at one level" for ONE sub-assembly group of ONE operation.
     Every operation that needs a sub-assembly has its own set of operations above it. Here the user can let this set also make what the LATER
     operations need (quantities added up, their sets removed) — and undo it — until the order is scheduled.
     Needs $order, $opTree, $op (the operation that needs the sub-assembly), $grp (its group in $opTree['children']). --}}
@php
    $saAction = $opTree['actions'][$op->id][$grp['child_bom_id'] ?? 0] ?? null;
@endphp
@if($saAction)
    @if(!empty($saAction['serves']))
        <span class="badge bg-soft-warning text-dark" title="{{ __('production.sa_also_makes_for_hint') }}">
            <i class="feather-layers me-1"></i>{{ __('production.sa_also_makes_for', ['ops' => collect($saAction['serves'])->map(fn ($s) => $s['label'] . ' (' . qty_format($s['qty']) . ')')->implode(', ')]) }}
        </span>
    @endif
    @if($saAction['can_merge'] || $saAction['can_split'])
        @php
            $saLock = $saAction['lock_reason'];
            $saLaterText = collect($saAction['later'])->map(fn ($l) => $l['label'] . ' (' . qty_format($l['qty']) . ')')->implode(', ');
            $saServedText = collect($saAction['serves'])->map(fn ($l) => $l['label'] . ' (' . qty_format($l['qty']) . ')')->implode(', ');
        @endphp
        @if($saAction['can_merge'])
            <span title="{{ $saLock ?: __('production.sa_produce_all_here_hint', ['ops' => $saLaterText]) }}">
                <form method="POST" action="{{ route('production.orders.sub-assembly.merge', $order->id) }}" class="d-inline"
                    onsubmit="return confirm({{ json_encode(__('production.sa_merge_confirm', ['ops' => $saLaterText])) }});">
                    @csrf
                    <input type="hidden" name="operation_id" value="{{ $op->id }}">
                    <input type="hidden" name="child_bom_id" value="{{ $grp['child_bom_id'] }}">
                    <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-2 fs-11" @disabled($saLock !== null)>
                        <i class="feather-layers me-1"></i>{{ __('production.sa_produce_all_here') }}@if($saLock !== null)<i class="feather-lock ms-1"></i>@endif
                    </button>
                </form>
            </span>
        @endif
        @if($saAction['can_split'])
            <span title="{{ $saLock ?: __('production.sa_produce_separately_hint', ['ops' => $saServedText]) }}">
                <form method="POST" action="{{ route('production.orders.sub-assembly.split', $order->id) }}" class="d-inline"
                    onsubmit="return confirm({{ json_encode(__('production.sa_split_confirm', ['ops' => $saServedText])) }});">
                    @csrf
                    <input type="hidden" name="operation_id" value="{{ $op->id }}">
                    <input type="hidden" name="child_bom_id" value="{{ $grp['child_bom_id'] }}">
                    <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2 fs-11" @disabled($saLock !== null)>
                        <i class="feather-rotate-ccw me-1"></i>{{ __('production.sa_produce_separately') }}@if($saLock !== null)<i class="feather-lock ms-1"></i>@endif
                    </button>
                </form>
            </span>
        @endif
    @endif
@endif
