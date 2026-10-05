@extends('layouts.duralux')

@section('title', 'Journal ' . $journal->journal_number . ' | SaaS ERP')
@section('page-title', 'Journal ' . $journal->journal_number)
@section('breadcrumb', 'Accounting / Journals / ' . $journal->journal_number)

@section('page-actions')
    <x-ui.button href="{{ route('accounting.journals.index') }}" variant="light" icon="feather-arrow-left" class="border">
        Back to Journals
    </x-ui.button>
@endsection

@section('content')

    @if ($journal->status === 'pending_approval')
        <x-ui.alert variant="primary" icon="feather-clock" class="fs-13 mb-4">
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                <span>Awaiting approval — not in the ledger yet. Entered by {{ $journal->postedBy?->name ?? 'unknown' }}.</span>
                @if ($canApprove ?? false)
                    <span class="d-flex align-items-center gap-2">
                        <form method="POST" action="{{ route('accounting.approvals.approve', $journal) }}" class="m-0">
                            @csrf
                            <x-ui.button type="submit" variant="primary" size="sm" icon="feather-check">Approve &amp; post</x-ui.button>
                        </form>
                        <x-ui.button type="button" variant="light-brand" size="sm" icon="feather-x"
                            data-bs-toggle="modal" data-bs-target="#rejectJournalModal"
                            data-reject-url="{{ route('accounting.approvals.reject', $journal) }}"
                            data-journal-number="{{ $journal->journal_number }}">Reject</x-ui.button>
                    </span>
                @endif
            </div>
        </x-ui.alert>
        @if ($canApprove ?? false)
            @include('modules.accounting.approvals._reject-modal')
        @endif
    @elseif ($journal->status === 'rejected')
        <x-ui.alert variant="danger" icon="feather-x-circle" class="fs-13 mb-4">
            Rejected by {{ $journal->rejectedBy?->name ?? 'unknown' }} on {{ $journal->rejected_at?->format('d M Y H:i') }} — it did not post.
            <span class="d-block mt-1"><strong>Reason:</strong> {{ $journal->rejection_reason }}</span>
        </x-ui.alert>
    @elseif ($journal->approved_by)
        <div class="fs-12 text-muted mb-3"><i class="feather-check-circle text-success me-1"></i>Approved by {{ $journal->approvedBy?->name ?? 'unknown' }} on {{ $journal->approved_at?->format('d M Y H:i') }}</div>
    @endif

    <x-ui.card class="mb-4">
        <div class="row g-4 fs-13">
            <div class="col-md-3">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Journal Number</span>
                <span class="fw-bold text-dark font-monospace">{{ $journal->journal_number }}</span>
            </div>
            <div class="col-md-2">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Date</span>
                <span class="fw-bold text-dark">{{ $journal->journal_date->format('d M Y') }}</span>
            </div>
            <div class="col-md-2">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Period</span>
                <span class="fw-bold text-dark">{{ $journal->period?->name ?: '—' }}</span>
            </div>
            <div class="col-md-2">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Source</span>
                <span class="fw-bold text-dark text-capitalize">{{ $journal->source }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Status</span>
                @include('modules.accounting.journals._status-badge', ['status' => $journal->status])
                @if ($journal->status === 'reversed' && $journal->reversedJournal)
                    <a href="{{ route('accounting.journals.show', $journal->reversedJournal) }}" class="fs-11 ms-2">View reversal &rarr;</a>
                @endif
            </div>
        </div>
        @if ($journal->memo)
            <div class="mt-3 pt-3 border-top fs-13">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Memo</span>
                {{ $journal->memo }}
            </div>
        @endif
    </x-ui.card>

    <x-ui.card bodyClass="p-0">
        <x-slot:title>Entries</x-slot:title>
        @can('reverse', $journal)
            @if ($journal->status === 'posted')
                <x-slot:headerAction>
                    <x-ui.button type="button" variant="danger" size="sm" icon="feather-rotate-ccw" data-bs-toggle="modal" data-bs-target="#reverseJournalModal">
                        Reverse Journal
                    </x-ui.button>
                </x-slot:headerAction>
            @endif
        @endcan

        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Account</th>
                    <th>Description</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end pe-4">Credit</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @foreach ($journal->entries as $entry)
                    <tr>
                        <td class="ps-4">
                            <span class="fw-bold">{{ $entry->account?->code }}</span>
                            <span class="text-muted">{{ $entry->account?->name }}</span>
                        </td>
                        <td class="text-muted">{{ $entry->description ?: '—' }}</td>
                        <td class="text-end">{{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}</td>
                        <td class="text-end pe-4">{{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-bold fs-13 bg-light">
                    <td class="ps-4" colspan="2">Total</td>
                    <td class="text-end">{{ number_format($journal->total_debit, 2) }}</td>
                    <td class="text-end pe-4">{{ number_format($journal->total_credit, 2) }}</td>
                </tr>
            </tfoot>
        </x-ui.table>
    </x-ui.card>

    @can('reverse', $journal)
        <x-ui.modal id="reverseJournalModal" title="Reverse Journal {{ $journal->journal_number }}"
                    :formAction="route('accounting.journals.reverse', $journal)" submitText="Reverse">
            <p class="fs-13">This creates a mirror-image journal that cancels out this one. The original journal is never edited or deleted — only marked reversed.</p>
            <x-ui.textarea label="Reason (optional)" name="reason" rows="2" placeholder="Why is this journal being reversed?" />
        </x-ui.modal>
    @endcan
@endsection
