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
                        <label class="form-label fw-semibold fs-12 text-uppercase mb-0 text-dark">Upload Statement</label>
                        <input type="file" name="file" class="form-control form-control-sm" required accept=".csv,.xlsx,.xls,.txt,.pdf">
                        <div class="fs-11 text-muted mt-1">CSV/Excel imports directly · PDF is sent to the configured extraction API</div>
                    </div>
                    <x-ui.button type="submit" variant="light" size="sm" class="border">Upload</x-ui.button>
                    <a href="{{ route('accounting.bank-reconciliation.template') }}" class="fs-12 text-muted text-decoration-none d-inline-flex align-items-center gap-1" style="height: 31px;" title="Download a sample CSV with the expected columns">
                        <i class="feather-download"></i> Download template
                    </a>
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

    @if ($reconciliation->statementUploads->isNotEmpty())
        <x-ui.card title="Statement Uploads" bodyClass="p-0" class="accounting-dense mb-3">
            <x-ui.table hoverable>
                <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                    <tr>
                        <th class="ps-4">File</th>
                        <th>Uploaded</th>
                        <th>Status</th>
                        <th>Lines Extracted</th>
                        <th>Detected Account</th>
                        <th class="pe-4">Error</th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
                    @foreach ($reconciliation->statementUploads as $upload)
                        @php $accountInfo = array_filter($upload->raw_response['account_info'] ?? []); @endphp
                        <tr>
                            <td class="ps-4">{{ $upload->original_filename }}</td>
                            <td class="text-muted">{{ $upload->created_at->format('d M Y H:i') }}</td>
                            <td>
                                @if ($upload->status === 'completed')
                                    <x-ui.badge variant="success" soft>Completed</x-ui.badge>
                                @elseif ($upload->status === 'failed')
                                    <x-ui.badge variant="danger" soft>Failed</x-ui.badge>
                                @else
                                    <x-ui.badge variant="warning" soft>Pending</x-ui.badge>
                                @endif
                            </td>
                            <td>{{ $upload->extracted_count }}</td>
                            <td class="text-muted fs-12">
                                @if (empty($accountInfo))
                                    —
                                @else
                                    @if (!empty($accountInfo['bank']))
                                        {{ $accountInfo['bank'] }}<br>
                                    @endif
                                    @if (!empty($accountInfo['account_no']))
                                        A/C {{ $accountInfo['account_no'] }}<br>
                                    @endif
                                    @if (!empty($accountInfo['branch']))
                                        {{ $accountInfo['branch'] }}
                                    @endif
                                @endif
                            </td>
                            <td class="pe-4 text-muted fs-12">{{ $upload->error_message ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endif

    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4" style="width: 90px;">Date</th>
                    <th style="max-width: 260px;">Description</th>
                    <th class="text-end" style="width: 110px;">Amount</th>
                    <th style="width: 90px;">Status</th>
                    <th class="pe-4" style="width: 260px;">Matched Journal Entry</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($reconciliation->statementLines as $line)
                    <tr>
                        <td class="ps-4">{{ $line->transaction_date->format('d M Y') }}</td>
                        <td class="text-muted" style="max-width: 260px;">
                            <div class="desc-clamp" id="desc-{{ $line->id }}" style="max-width: 260px;">{{ $line->description ?: '—' }}</div>
                            @if ($line->description && strlen($line->description) > 70)
                                <button type="button" class="btn btn-link p-0 fs-11 desc-toggle-btn" data-target="desc-{{ $line->id }}">Read more</button>
                            @endif
                            @if ($line->suggested_ledger && !$line->is_matched)
                                <div class="fs-11 text-primary"><i class="feather-zap"></i> {{ $line->suggested_ledger }}</div>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format($line->amount, 2) }}</td>
                        <td>
                            @if ($line->is_matched)
                                <x-ui.badge variant="success" soft>Matched</x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary" soft>Unmatched</x-ui.badge>
                            @endif
                        </td>
                        <td class="pe-4" style="width: 260px;">
                            @if ($line->is_matched)
                                <span class="text-muted fs-12">
                                    {{ $line->matchedJournalEntry?->journal?->journal_number }} &mdash;
                                    {{ number_format($line->matchedJournalEntry?->signedAmount() ?? 0, 2) }}
                                </span>
                            @elseif (!$reconciliation->isCompleted())
                                <div class="d-flex flex-column gap-2" style="width: 260px;">
                                    <div>
                                        <div class="fs-10 text-uppercase text-muted fw-semibold mb-1">Match existing</div>
                                        <form action="{{ route('accounting.bank-reconciliation.match', $reconciliation) }}" method="POST" class="d-flex gap-1">
                                            @csrf
                                            <input type="hidden" name="statement_line_id" value="{{ $line->id }}">
                                            <select name="journal_entry_id" class="form-select form-select-sm flex-grow-1" style="min-width: 0; font-size: 11px;" required>
                                                <option value="">Select journal entry...</option>
                                                @foreach ($unreconciledEntries as $entry)
                                                    <option value="{{ $entry->id }}">
                                                        {{ $entry->journal?->journal_number }} &mdash; {{ $entry->journal?->journal_date?->format('d M Y') }} &mdash; {{ number_format($entry->signedAmount(), 2) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <x-ui.button type="submit" variant="light" size="sm" class="border flex-shrink-0 px-2">Match</x-ui.button>
                                        </form>
                                    </div>

                                    {{-- No journal entry exists yet for this line (the normal case for a
                                         freshly-uploaded statement) — post one directly against a ledger,
                                         defaulted to the provider's suggested_ledger guess when it resolves. --}}
                                    <div>
                                        <div class="fs-10 text-uppercase text-muted fw-semibold mb-1">Or post new</div>
                                        <form action="{{ route('accounting.bank-reconciliation.create-and-match', $reconciliation) }}" method="POST" class="d-flex gap-1">
                                            @csrf
                                            <input type="hidden" name="statement_line_id" value="{{ $line->id }}">
                                            <select name="chart_of_account_id" class="form-select form-select-sm flex-grow-1" style="min-width: 0; font-size: 11px;" required>
                                                <option value="">Select ledger account...</option>
                                                @foreach ($allAccounts as $account)
                                                    @continue($account->id === $reconciliation->chart_of_account_id)
                                                    <option value="{{ $account->id }}" @selected(($suggestedAccounts[$line->id] ?? null) === $account->id)>
                                                        {{ $account->code }} &mdash; {{ $account->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <x-ui.button type="submit" variant="primary" size="sm" class="flex-shrink-0 px-2" title="Post a new journal entry against this ledger and match it">Create</x-ui.button>
                                        </form>
                                    </div>
                                </div>
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
        .desc-clamp {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            white-space: normal;
        }
        .desc-clamp.desc-expanded {
            -webkit-line-clamp: unset;
            overflow: visible;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('.desc-toggle-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = document.getElementById(btn.getAttribute('data-target'));
                var expanded = target.classList.toggle('desc-expanded');
                btn.textContent = expanded ? 'Read less' : 'Read more';
            });
        });
    </script>
@endpush
