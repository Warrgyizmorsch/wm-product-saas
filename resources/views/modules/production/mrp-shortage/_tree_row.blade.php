@php
    $level = (int) ($node['level'] ?? 1);
    $padding = max(0, ($level - 1) * 24);
    $hasShortage = (float)($node['net_shortage_qty'] ?? 0) > 0;
    $isManufactured = !empty($node['has_bom']) || ($node['planning_type'] ?? '') === 'manufacture';
    $type = $node['type'] ?? 'goods';
    
    $rowClass = $hasShortage ? 'bg-soft-danger-subtle' : ($level > 1 ? 'table-light-soft' : '');
@endphp

<tr class="{{ $rowClass }}">
    <td>
        <div style="padding-left: {{ $padding }}px;" class="d-flex align-items-center">
            @if($level > 1)
                <span class="text-muted me-2 font-monospace">└─</span>
            @endif
            <span class="badge {{ $level === 1 ? 'bg-primary' : ($level === 2 ? 'bg-info' : 'bg-secondary') }} me-2">
                L{{ $level }}
            </span>
            <div>
                <span class="fw-bold text-dark fs-12">{{ $node['product_name'] }}</span>
                <small class="text-muted font-monospace d-block fs-10">{{ $node['sku'] }}</small>
                @if(!empty($node['source_ref']))
                    <small class="text-primary fs-10 d-block">{{ $node['source_ref'] }}</small>
                @endif
            </div>
        </div>
    </td>
    <td>
        @if($isManufactured)
            <span class="badge bg-soft-primary text-primary">Make (BOM)</span>
        @else
            <span class="badge bg-soft-success text-success">Buy (Direct)</span>
        @endif
        <small class="d-block text-muted fs-10 mt-1">{{ ucfirst(str_replace('_', ' ', $type)) }}</small>
    </td>
    <td class="text-end fw-semibold">{{ number_format($node['required_qty'], 2) }}</td>
    <td class="text-end text-muted">{{ number_format($node['on_hand_qty'], 2) }}</td>
    <td class="text-end text-muted">{{ number_format($node['reserved_qty'], 2) }}</td>
    <td class="text-end fw-semibold {{ $node['available_qty'] <= 0 ? 'text-danger' : 'text-dark' }}">
        {{ number_format($node['available_qty'], 2) }}
    </td>
    <td class="text-end fw-bold {{ $hasShortage ? 'text-danger fs-13' : 'text-muted' }}">
        {{ number_format($node['net_shortage_qty'], 2) }}
    </td>
    <td>
        @if($hasShortage)
            <span class="badge bg-danger text-white">Shortage</span>
        @else
            <span class="badge bg-success text-white">Available</span>
        @endif
    </td>
</tr>

@if(!empty($node['children']))
    @foreach($node['children'] as $childNode)
        @include('modules.production.mrp-shortage._tree_row', ['node' => $childNode])
    @endforeach
@endif
