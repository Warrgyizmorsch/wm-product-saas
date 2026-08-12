@forelse($wips as $sw)
    <tr class="align-middle fs-12">
        <td class="fw-bold text-primary">
            <a href="{{ route('production.wip.show', $sw->id) }}">
                WIP-#{{ str_pad($sw->id, 5, '0', STR_PAD_LEFT) }}
            </a>
        </td>
        <td>
            <span class="badge bg-soft-secondary text-dark font-monospace fs-10">
                {{ $sw->batch->batch_number ?? 'Main Order' }}
            </span>
        </td>
        <td class="fw-semibold">
            {{ $sw->currentRoutingOperation->name ?? 'Finished Goods' }}
        </td>
        <td class="text-end fw-semibold text-dark">{{ number_format($sw->available_quantity, 2) }}</td>
        <td class="text-end text-success fw-bold">{{ number_format($sw->completed_quantity, 2) }}</td>
        <td class="text-end text-primary fw-bold">{{ format_currency($sw->total_value) }}</td>
        <td>
            @if($sw->status === 'transferred')
                <span class="badge bg-soft-info text-info text-uppercase fs-10">Transferred</span>
            @elseif($sw->status === 'active')
                <span class="badge bg-soft-success text-success text-uppercase fs-10">Active</span>
            @elseif($sw->status === 'quality_hold')
                <span class="badge bg-soft-warning text-warning text-uppercase fs-10">Quality Hold</span>
            @elseif($sw->status === 'rework')
                <span class="badge bg-soft-danger text-danger text-uppercase fs-10">Rework</span>
            @else
                <span class="badge bg-soft-secondary text-secondary text-uppercase fs-10">Completed</span>
            @endif
        </td>
        <td class="text-end">
            <a href="{{ route('production.wip.show', $sw->id) }}" class="btn btn-xs btn-outline-primary py-0 px-2 fs-11">
                View Detail
            </a>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="8" class="text-center py-3 text-muted fs-12">
            No WIP batches matching selected criteria.
        </td>
    </tr>
@endforelse
