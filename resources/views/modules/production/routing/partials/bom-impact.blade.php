{{-- RT-IMPL-06 (RT-05 rule 1): linked BOMs and what Retire / Cancel will do to each --}}
@php
    $impact = $impact ?? app(\App\Domains\Production\Services\RoutingLifecycleService::class)->bomImpact($routing);
@endphp
<div class="mb-3">
    <div class="fw-semibold fs-12 text-dark mb-1">{{ __('production.bom_impact_title') }}</div>
    @if (empty($impact))
        <div class="fs-12 text-muted">{{ __('production.bom_impact_none') }}</div>
    @else
        <table class="table table-sm table-bordered mb-0 fs-12">
            <thead class="table-light">
                <tr><th>BOM</th><th>{{ __('production.status') }}</th><th>{{ __('production.bom_impact_effect') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($impact as $row)
                    <tr>
                        <td><a href="{{ route('production.boms.show', $row['id']) }}" target="_blank">{{ $row['bom_number'] }}</a>{{ $row['version'] ? ' v' . $row['version'] : '' }}</td>
                        <td class="text-uppercase">{{ str_replace('_', ' ', $row['status']) }}</td>
                        <td>{{ $row['effect'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
