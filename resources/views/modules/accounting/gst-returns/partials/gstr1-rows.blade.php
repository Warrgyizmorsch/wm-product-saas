@php
    use App\Domains\Accounting\Support\GstStates;
@endphp
@if ($heading)
    <tr class="gstr-section">
        <td></td>
        <td colspan="4">{{ $heading }}</td>
        <td class="text-end">{{ $money($sum($docs, 'taxable')) }}</td>
        <td class="text-end">{{ $money($sum($docs, 'tax')) }}</td>
        <td class="text-end pe-3">{{ $money($sum($docs, 'value')) }}</td>
    </tr>
@endif
@foreach ($docs as $key => $doc)
    @php([$badge, $badgeClass] = $stateBadges[$doc['state']])
    <tr data-key="{{ $key }}" class="{{ $doc['state'] === 'exception' ? 'text-muted' : '' }}">
        <td class="ps-3">
            @if ($doc['state'] !== 'exception')
                <input type="checkbox" class="form-check-input gstr-row" name="keys[]" value="{{ $key }}" aria-label="Select {{ $doc['number'] }}">
            @endif
        </td>
        <td class="text-nowrap">{{ $doc['date']?->format('j-M-y') }}</td>
        <td>
            <div class="text-dark">{{ $doc['party'] }}</div>
            <div class="fs-11 text-muted">
                @if ($doc['ctin'])<span class="font-monospace">{{ $doc['ctin'] }}</span> · @endif
                @if ($doc['pos'])POS {{ $doc['pos'] }}-{{ GstStates::name($doc['pos']) }}@endif
                @if (!empty($doc['against'])) · against {{ $doc['against'] }}@endif
                <span class="badge {{ $badgeClass }} ms-1">{{ $badge }}</span>
            </div>
            @foreach ($doc['issues'] as $issue)
                <div class="gstr-issue text-danger"><i class="feather-alert-circle me-1"></i>{{ $issue }}</div>
            @endforeach
            @if (! $doc['live'] && $doc['state'] === 'delete')
                <div class="gstr-issue text-danger"><i class="feather-trash-2 me-1"></i>{{ $doc['gone_reason'] ?? 'Cancelled after upload' }} — will be deleted from the portal.</div>
            @endif
        </td>
        <td class="text-nowrap">{{ $doc['vch_type'] }}</td>
        <td class="font-monospace text-nowrap">
            @if ($doc['url'])
                <a href="{{ $doc['url'] }}" target="_blank" rel="noopener">{{ $doc['number'] }}</a>
            @else
                {{ $doc['number'] }}
            @endif
        </td>
        <td class="text-end">{{ $money($doc['taxable']) }}</td>
        <td class="text-end">{{ $money($doc['tax']) }}</td>
        <td class="text-end pe-3">{{ $money($doc['value']) }}</td>
    </tr>
@endforeach
