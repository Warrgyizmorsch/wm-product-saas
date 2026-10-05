{{-- Latest postings (ui-reference "ledger-transactions-table"). --}}
{{-- No overflow-hidden here: the grid grows the cell by measuring this card's overflow. --}}
<section class="ax-section mb-3 h-100">
    <div class="ax-section-head">
        <div>
            <h2 class="ax-section-title">Recent Ledger Postings &amp; Voucher Activity</h2>
            <p class="ax-section-subtitle">The latest posted journals and vouchers</p>
        </div>
        <a href="{{ route('accounting.journals.index') }}" class="btn btn-sm btn-light">All journals <i class="feather-arrow-right"></i></a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Voucher No</th>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th class="text-end">Amount</th>
                    <th class="text-center">Status</th>
                    <th class="text-end pe-4"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentJournals as $journal)
                    @php
                        $reversed = $journal->status === \App\Domains\Accounting\Models\Journal::STATUS_REVERSED;
                        $type = str_replace('_', ' ', $journal->voucher_type ?? $journal->source ?? 'journal');
                    @endphp
                    <tr>
                        <td class="ps-4"><a href="{{ route('accounting.journals.show', $journal) }}" class="ax-mono fw-semibold">{{ $journal->journal_number }}</a></td>
                        <td class="ax-mono text-muted text-nowrap">{{ $journal->journal_date?->format('d M Y') }}</td>
                        <td class="text-dark fw-medium">{{ \Illuminate\Support\Str::limit($journal->memo ?: ucfirst($type), 48) }}</td>
                        <td><span class="ax-ref text-capitalize">{{ $type }}</span></td>
                        <td class="text-end ax-mono fw-semibold text-dark">{{ $money($journal->total_debit) }}</td>
                        <td class="text-center">
                            @if ($reversed)
                                <x-ui.chip tone="neutral">Reversed</x-ui.chip>
                            @else
                                <x-ui.chip tone="positive"><span class="ax-dot is-positive" style="width: 6px; height: 6px;"></span>Posted</x-ui.chip>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <a href="{{ route('accounting.journals.show', $journal) }}" class="ax-icon-btn" title="Open"><i class="feather-chevron-right"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="ax-empty">No journals posted yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($recentJournals->isNotEmpty())
        <div class="ax-section-foot fs-12 text-muted">Showing the latest <strong class="text-dark">{{ $recentJournals->count() }}</strong> entries</div>
    @endif
</section>
