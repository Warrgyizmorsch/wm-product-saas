<x-ui.card title="Recent Journals" bodyClass="p-0" class="accounting-dense mb-3" stretch>
    <x-slot:headerAction>
        <a href="{{ route('accounting.journals.index') }}" class="fs-12">All journals <i class="feather-arrow-right"></i></a>
    </x-slot:headerAction>
    <x-ui.table hoverable>
        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
            <tr>
                <th class="ps-4">Date</th>
                <th>Number</th>
                <th>Source</th>
                <th>Memo</th>
                <th class="text-end pe-4">Amount</th>
            </tr>
        </thead>
        <tbody class="fs-13 text-dark">
            @forelse ($recentJournals as $journal)
                <tr>
                    <td class="ps-4 text-nowrap">{{ $journal->journal_date?->format('d M Y') }}</td>
                    <td class="text-nowrap">
                        <a href="{{ route('accounting.journals.show', $journal) }}" class="fw-semibold">{{ $journal->journal_number }}</a>
                        @if ($journal->status === \App\Domains\Accounting\Models\Journal::STATUS_REVERSED)
                            <span class="badge bg-soft-secondary text-secondary ms-1">Reversed</span>
                        @endif
                    </td>
                    <td class="text-capitalize">{{ str_replace('_', ' ', $journal->voucher_type ?? $journal->source) }}</td>
                    <td class="text-muted">{{ \Illuminate\Support\Str::limit($journal->memo, 40) }}</td>
                    <td class="text-end pe-4">{{ $money($journal->total_debit) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-4 text-muted">No journals posted yet.</td></tr>
            @endforelse
        </tbody>
    </x-ui.table>
</x-ui.card>
