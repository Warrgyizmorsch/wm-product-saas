@extends('layouts.duralux')

@section('title', 'Budget vs Actual | SaaS ERP')
@section('page-title', 'Budget vs Actual')
@section('breadcrumb', 'Accounting / Reports / Budget vs Actual')

@section('content')
    <x-ui.card class="mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-6">
                <x-ui.select label="Budget" name="budget_id" onchange="this.form.submit()" :options="$allBudgets->mapWithKeys(fn ($b) => [
                    $b->id => $b->name . ' — ' . ($b->fiscalYear?->name) . ' (' . $b->status . ')',
                ])->all()" :selected="$budget?->id" />
            </div>
        </form>
    </x-ui.card>

    @if ($budget)
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <x-ui.card bodyClass="text-center py-3">
                    <div class="fs-22 fw-bold text-success">{{ $summary['ok'] }}</div>
                    <div class="fs-12 text-muted text-uppercase fw-semibold">Within Budget</div>
                </x-ui.card>
            </div>
            <div class="col-md-4">
                <x-ui.card bodyClass="text-center py-3">
                    <div class="fs-22 fw-bold text-warning">{{ $summary['warning'] }}</div>
                    <div class="fs-12 text-muted text-uppercase fw-semibold">Near Limit (&ge;80%)</div>
                </x-ui.card>
            </div>
            <div class="col-md-4">
                <x-ui.card bodyClass="text-center py-3">
                    <div class="fs-22 fw-bold text-danger">{{ $summary['over'] }}</div>
                    <div class="fs-12 text-muted text-uppercase fw-semibold">Over Budget</div>
                </x-ui.card>
            </div>
        </div>
    @endif

    <x-ui.card title="Budget vs Actual{{ $budget ? ' — ' . $budget->name : '' }}" bodyClass="p-0">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Account</th>
                    <th>Dimension</th>
                    <th class="text-end">Budgeted</th>
                    <th class="text-end">Actual</th>
                    <th class="text-end">Variance</th>
                    <th class="text-end pe-4">Status</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($rows as $row)
                    @php $line = $row['line']; $dimension = $line->dimension(); @endphp
                    <tr>
                        <td class="ps-4 fw-bold font-monospace">{{ $line->account->code }} — {{ $line->account->name }}</td>
                        <td class="text-muted">
                            @if ($dimension === null)
                                —
                            @elseif ($dimension['type'] === 'cost_center')
                                {{ $line->costCenter?->name }} <span class="fs-11">(Cost Center)</span>
                            @else
                                #{{ $dimension['id'] }} <span class="fs-11">({{ ucfirst($dimension['type']) }})</span>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format($row['budgeted'], 2) }}</td>
                        <td class="text-end">
                            @if ($row['has_actuals'])
                                {{ number_format($row['actual'], 2) }}
                            @else
                                <span class="text-muted fst-italic">no dimension data</span>
                            @endif
                        </td>
                        <td class="text-end">{{ $row['has_actuals'] ? number_format($row['variance'], 2) : '—' }}</td>
                        <td class="text-end pe-4">
                            @if (!$row['has_actuals'])
                                <x-ui.badge variant="secondary" soft>N/A</x-ui.badge>
                            @elseif ($row['status'] === 'over')
                                <x-ui.badge variant="danger" soft>Over</x-ui.badge>
                            @elseif ($row['status'] === 'warning')
                                <x-ui.badge variant="warning" soft>Near Limit</x-ui.badge>
                            @else
                                <x-ui.badge variant="success" soft>OK</x-ui.badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="feather-bar-chart-2 fs-1 mb-2 d-block"></i>
                            @if (!$budget)
                                No approved budgets exist yet.
                            @else
                                This budget has no lines.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>
@endsection
