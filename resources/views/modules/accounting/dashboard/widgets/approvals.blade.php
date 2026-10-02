{{--
    Maker-checker queue (ui-reference "pending-approvals-card"). Same data, routes and rules as
    Accounting → Pending Approvals: you can't approve an entry you made yourself.
    $pending: LengthAwarePaginator of journals awaiting approval; $userId: the viewer.
--}}
<section class="ax-section mb-3 h-100">
    <div class="ax-section-head">
        <h2 class="ax-section-title text-uppercase" style="letter-spacing: .06em; font-size: 12px;">
            <span class="ax-dot is-pending"></span>Pending Approvals
        </h2>
        <span class="ax-ref ax-tone-pending">{{ $pending->total() }} {{ \Illuminate\Support\Str::plural('Item', $pending->total()) }}</span>
    </div>
    <div class="ax-section-body py-2">
        @forelse ($pending->items() as $journal)
            @php
                $isOwn = (int) $journal->posted_by === $userId;
                $showUrl = $journal->voucher_type
                    ? route('accounting.vouchers.'.$journal->voucher_type.'.show', $journal)
                    : route('accounting.journals.show', $journal);
                $type = $journal->voucher_type ? ucfirst(str_replace('_', ' ', $journal->voucher_type)).' voucher' : 'Journal';
            @endphp
            <div class="d-flex align-items-center gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-items-center gap-2">
                        <span class="ax-mono fw-semibold text-dark fs-13">{{ $journal->journal_number }}</span>
                        <span class="ax-ref" style="font-family: var(--ax-font); font-size: 10px;">{{ $type }}</span>
                    </div>
                    <div class="fs-12 text-muted text-truncate">
                        <span class="ax-mono text-dark fw-medium">{{ $money($journal->total_debit) }}</span>
                        · {{ $journal->postedBy?->name ? 'by '.$journal->postedBy->name : $journal->journal_date?->format('d M Y') }}
                    </div>
                </div>
                @unless ($isOwn)
                    <form method="POST" action="{{ route('accounting.approvals.approve', $journal) }}" class="m-0">
                        @csrf
                        <button type="submit" class="ax-icon-btn ax-tone-positive border" title="Approve and post"><i class="feather-check"></i></button>
                    </form>
                @endunless
                <a href="{{ $showUrl }}" class="ax-icon-btn border" title="{{ $isOwn ? 'Your entry — someone else has to approve it' : 'Open' }}"><i class="feather-chevron-right"></i></a>
            </div>
        @empty
            <div class="ax-empty"><i class="feather-check-circle"></i>Nothing is waiting for approval.</div>
        @endforelse
        <a href="{{ route('accounting.approvals.index') }}" class="btn btn-outline-primary w-100 mt-3">
            Go to Approval Center ({{ $pending->total() }}) <i class="feather-arrow-right"></i>
        </a>
    </div>
</section>
