<x-ui.card title="Bank Reconciliation" bodyClass="p-0" class="accounting-dense mb-3" stretch>
    <x-slot:headerAction>
        <a href="{{ route('accounting.bank-reconciliation.index') }}" class="fs-12">Reconcile <i class="feather-arrow-right"></i></a>
    </x-slot:headerAction>
    <x-ui.table hoverable>
        <tbody class="fs-13 text-dark">
            @forelse ($bankAccounts as $bank)
                <tr>
                    <td class="ps-4">
                        {{ $bank['account']->name }}
                        <div class="fs-11 text-muted">Last reconciled: {{ $bank['last_reconciled']?->format('d M Y') ?? 'never' }}</div>
                    </td>
                    <td class="text-end pe-4 align-middle">
                        @if ($bank['in_progress'])
                            <span class="badge bg-soft-info text-info">In progress</span>
                        @elseif ($bank['stale'])
                            <span class="badge bg-soft-danger text-danger">Due</span>
                        @else
                            <span class="badge bg-soft-success text-success">Up to date</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="2" class="text-center py-4 text-muted">No bank accounts in the chart of accounts.</td></tr>
            @endforelse
        </tbody>
    </x-ui.table>
</x-ui.card>
