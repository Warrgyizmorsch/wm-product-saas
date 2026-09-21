<x-ui.card title="Budget Alerts" bodyClass="p-0" class="accounting-dense mb-3" stretch>
    <x-slot:headerAction>
        <a href="{{ route('accounting.reports.budget-vs-actual') }}" class="fs-12">Budget vs Actual <i class="feather-arrow-right"></i></a>
    </x-slot:headerAction>
    <x-ui.table hoverable>
        <tbody class="fs-13 text-dark">
            @forelse ($budgetAlerts as $alert)
                <tr>
                    <td class="ps-4">
                        <span class="fw-semibold">{{ $alert['account'] }}</span>
                        @if ($alert['cost_center'])<span class="text-muted"> · {{ $alert['cost_center'] }}</span>@endif
                        <div class="progress mt-1" style="height: 5px;">
                            <div class="progress-bar {{ $alert['status'] === 'over' ? 'bg-danger' : 'bg-warning' }}" style="width: {{ min($alert['percent'], 100) }}%"></div>
                        </div>
                        <div class="fs-11 text-muted mt-1">{{ $money($alert['actual']) }} of {{ $money($alert['budgeted']) }} · {{ $alert['budget'] }}</div>
                    </td>
                    <td class="text-end pe-4 align-middle">
                        <span class="badge {{ $alert['status'] === 'over' ? 'bg-danger' : 'bg-warning' }}">{{ number_format($alert['percent'], 1) }}%</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="2" class="text-center py-5 text-muted"><i class="feather-check-circle fs-1 mb-2 d-block"></i>Every budget line is under 80% used.</td></tr>
            @endforelse
        </tbody>
    </x-ui.table>
</x-ui.card>
