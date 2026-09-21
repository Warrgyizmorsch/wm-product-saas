<x-ui.card title="Cash & Bank Balances" bodyClass="p-0" class="accounting-dense mb-3" stretch>
    <x-ui.table hoverable>
        <tbody class="fs-13 text-dark">
            @forelse ($cash['accounts'] as $row)
                <tr>
                    <td class="ps-4"><span class="fw-bold font-monospace me-1">{{ $row['account']->code }}</span>{{ $row['account']->name }}</td>
                    <td class="text-end pe-4 {{ $row['balance'] < 0 ? 'text-danger' : '' }}">{{ $money($row['balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="text-center py-4 text-muted">No cash or bank activity yet.</td></tr>
            @endforelse
        </tbody>
    </x-ui.table>
</x-ui.card>
