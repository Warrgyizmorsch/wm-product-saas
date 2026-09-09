@extends('layouts.duralux')

@section('title', 'Bank Reconciliation | SaaS ERP')
@section('page-title', 'Bank Reconciliation')
@section('breadcrumb', 'Accounting / Bank Reconciliation / ' . $reconciliation->chartOfAccount->name)

@section('page-actions')
    <x-ui.button href="{{ route('accounting.bank-reconciliation.index') }}" variant="light" size="sm" class="border">Back</x-ui.button>
@endsection

@section('content')
    @if (session('success'))
        <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="mb-4">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">{{ session('error') }}</x-ui.alert>
    @endif

    <x-ui.card class="mb-3">
        <div class="row fs-13 text-dark">
            <div class="col-md-3">
                <div class="text-muted fs-11 text-uppercase fw-semibold">Account</div>
                <div class="fw-bold">{{ $reconciliation->chartOfAccount->code }} - {{ $reconciliation->chartOfAccount->name }}</div>
            </div>
            <div class="col-md-2">
                <div class="text-muted fs-11 text-uppercase fw-semibold">Statement Date</div>
                <div>{{ $reconciliation->statement_date->format('d M Y') }}</div>
            </div>
            <div class="col-md-2">
                <div class="text-muted fs-11 text-uppercase fw-semibold">Opening Balance</div>
                <div>{{ number_format($reconciliation->opening_balance, 2) }}</div>
            </div>
            <div class="col-md-2">
                <div class="text-muted fs-11 text-uppercase fw-semibold">Closing Balance</div>
                <div>{{ number_format($reconciliation->closing_balance, 2) }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted fs-11 text-uppercase fw-semibold">Status</div>
                @if ($reconciliation->isCompleted())
                    <x-ui.badge variant="success" soft>Completed by {{ $reconciliation->completedBy?->name }}</x-ui.badge>
                @else
                    <x-ui.badge variant="warning" soft>In Progress</x-ui.badge>
                @endif
            </div>
        </div>
    </x-ui.card>

    @unless ($reconciliation->isCompleted())
        <x-ui.card class="mb-3">
            <div class="d-flex flex-wrap gap-3 align-items-end">
                <form action="{{ route('accounting.bank-reconciliation.import', $reconciliation) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2 align-items-end">
                    @csrf
                    <div>
                        <label class="form-label fw-semibold fs-12 text-uppercase mb-0 text-dark">Import Statement (CSV/XLSX)</label>
                        <input type="file" name="file" class="form-control form-control-sm" required accept=".csv,.xlsx,.xls,.txt">
                    </div>
                    <x-ui.button type="submit" variant="light" size="sm" class="border">Import</x-ui.button>
                </form>

                <form action="{{ route('accounting.bank-reconciliation.auto-match', $reconciliation) }}" method="POST">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm" icon="feather-zap">Auto-Match</x-ui.button>
                </form>

                @if ($canComplete)
                    <form action="{{ route('accounting.bank-reconciliation.complete', $reconciliation) }}" method="POST" class="ms-auto">
                        @csrf
                        <x-ui.button type="submit" variant="success" size="sm" icon="feather-lock">Complete &amp; Lock</x-ui.button>
                    </form>
                @endif
            </div>
        </x-ui.card>
    @endunless

    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Date</th>
                    <th>Description</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th class="pe-4">Matched Journal Entry</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($reconciliation->statementLines as $line)
                    <tr>
                        <td class="ps-4">{{ $line->transaction_date->format('d M Y') }}</td>
                        <td class="text-muted">{{ $line->description ?: '—' }}</td>
                        <td class="text-end">{{ number_format($line->amount, 2) }}</td>
                        <td>
                            @if ($line->is_matched)
                                <x-ui.badge variant="success" soft>Matched</x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary" soft>Unmatched</x-ui.badge>
                            @endif
                        </td>
                        <td class="pe-4">
                            @if ($line->is_matched)
                                <span class="text-muted fs-12">
                                    {{ $line->matchedJournalEntry?->journal?->journal_number }} &mdash;
                                    {{ number_format($line->matchedJournalEntry?->signedAmount() ?? 0, 2) }}
                                </span>
                            @elseif (!$reconciliation->isCompleted())
                                <form action="{{ route('accounting.bank-reconciliation.match', $reconciliation) }}" method="POST" class="d-flex gap-2">
                                    @csrf
                                    <input type="hidden" name="statement_line_id" value="{{ $line->id }}">
                                    <select name="journal_entry_id" class="form-select form-select-sm" style="max-width: 260px;" required>
                                        <option value="">Select journal entry...</option>
                                        @foreach ($unreconciledEntries as $entry)
                                            <option value="{{ $entry->id }}">
                                                {{ $entry->journal?->journal_number }} &mdash; {{ $entry->journal?->journal_date?->format('d M Y') }} &mdash; {{ number_format($entry->signedAmount(), 2) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-ui.button type="submit" variant="light" size="sm" class="border">Match</x-ui.button>
                                </form>
                            @else
                                &mdash;
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="feather-upload fs-1 mb-2 d-block"></i>
                            No statement lines yet — import a statement to begin.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>
@endsection

@push('styles')
    <style>
        .accounting-dense table th,
        .accounting-dense table td {
            padding: 6px 10px !important;
            font-size: 12px !important;
        }
    </style>
@endpush
