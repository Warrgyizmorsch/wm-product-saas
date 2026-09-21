<x-ui.card :title="$forecast['days'] . '-Day Cash Forecast'" bodyClass="p-0" class="accounting-dense mb-3" stretch>
    <x-ui.table>
        <tbody class="fs-13 text-dark">
            <tr><td class="ps-4">Cash &amp; bank today</td><td class="text-end pe-4">{{ $money($forecast['opening']) }}</td></tr>
            <tr><td class="ps-4">+ Receivables due</td><td class="text-end pe-4 text-success">{{ $money($forecast['inflow']) }}</td></tr>
            <tr><td class="ps-4">− Payables due</td><td class="text-end pe-4 text-danger">{{ $money($forecast['outflow']) }}</td></tr>
        </tbody>
        <tfoot>
            <tr class="fw-bold fs-14 bg-light">
                <td class="ps-4">Projected cash</td>
                <td class="text-end pe-4 {{ $forecast['closing'] < 0 ? 'text-danger' : '' }}">{{ $money($forecast['closing']) }}</td>
            </tr>
        </tfoot>
    </x-ui.table>
    <div class="px-4 py-2 fs-11 text-muted">Includes amounts already overdue. Assumes everything due is collected and paid on time.</div>
</x-ui.card>
