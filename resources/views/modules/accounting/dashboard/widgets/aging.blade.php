{{-- $side: receivables | payables --}}
@php
    $sides = [
        'receivables' => ['title' => 'Receivables', 'data' => $receivables, 'chart' => 'acc-ar-chart', 'party' => 'Top customers', 'route' => 'accounting.reports.ar-aging', 'noun' => 'invoice(s)'],
        'payables' => ['title' => 'Payables', 'data' => $payables, 'chart' => 'acc-ap-chart', 'party' => 'Top vendors', 'route' => 'accounting.reports.ap-aging', 'noun' => 'bill(s)'],
    ];
    $side = $sides[$which];
@endphp
<x-ui.card :title="$side['title'] . ' Aging'" class="mb-3" stretch>
    <x-slot:headerAction>
        <a href="{{ route($side['route']) }}" class="fs-12">Full report <i class="feather-arrow-right"></i></a>
    </x-slot:headerAction>
    <div class="d-flex flex-wrap gap-4 mb-2 fs-13 text-muted">
        <span>Outstanding <strong class="text-dark">{{ $money($side['data']['total']) }}</strong></span>
        <span>Overdue <strong class="{{ $side['data']['overdue'] > 0 ? 'text-danger' : 'text-dark' }}">{{ $money($side['data']['overdue']) }}</strong></span>
        <span>{{ $side['data']['count'] }} {{ $side['noun'] }}</span>
    </div>
    @if ($side['data']['total'] > 0)
        <div id="{{ $side['chart'] }}" style="min-height: 220px;"></div>
        <div class="fs-11 text-uppercase fw-semibold text-muted mt-2 mb-1">{{ $side['party'] }}</div>
        <table class="table table-sm mb-0 fs-13">
            <tbody>
                @foreach ($side['data']['top'] as $party)
                    <tr>
                        <td class="ps-0">{{ $party['name'] }}</td>
                        <td class="text-end pe-0">{{ $money($party['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="text-center py-5 text-muted"><i class="feather-check-circle fs-1 mb-2 d-block"></i>Nothing outstanding.</div>
    @endif
</x-ui.card>
