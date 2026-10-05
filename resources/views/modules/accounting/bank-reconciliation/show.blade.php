@php
    $account = $reconciliation->chartOfAccount;
    $lines = $reconciliation->statementLines;
    $counts = $brs['line_counts'];
    $isOpen = ! $reconciliation->isCompleted();
    $money = fn ($value) => number_format((float) $value, 2);
    $ready = collect($readiness)->every('ok');
    $period = ($reconciliation->statement_from_date ? $reconciliation->statement_from_date->format('d M Y') . ' – ' : 'Up to ') . $reconciliation->statement_date->format('d M Y');
    $diffZero = $brs['difference'] === 0.0;
    $ruleSuggestions = $ruleSuggestions ?? [];
    // Unmatched lines with their suggested ledger/party, for "Post selected…".
    $bulkLines = $lines->where('is_matched', false)->map(fn ($line) => [
        'id' => $line->id,
        'date' => $line->transaction_date->format('d M Y'),
        'description' => $line->description,
        'amount' => (float) $line->amount,
        'account' => $suggestedAccounts[$line->id] ?? null,
        'party' => isset($ruleSuggestions[$line->id]) ? $ruleSuggestions[$line->id]->party_name : null,
        'narration' => isset($ruleSuggestions[$line->id]) && $ruleSuggestions[$line->id]->narration ? $ruleSuggestions[$line->id]->narration : $line->description,
        'rule' => isset($ruleSuggestions[$line->id]) ? $ruleSuggestions[$line->id]->name : null,
    ])->values();
@endphp

@extends('layouts.duralux')

@section('title', 'Bank Reconciliation | SaaS ERP')
@section('page-title', 'Bank Reconciliation')
@section('breadcrumb', 'Accounting / Bank Reconciliation / ' . $account->name)

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap">
        <div class="dropdown">
            <x-ui.button type="button" variant="light" size="sm" class="border dropdown-toggle" icon="feather-download" data-bs-toggle="dropdown">BRS</x-ui.button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('accounting.bank-reconciliation.export', [$reconciliation, 'pdf']) }}"><i class="feather-file-text me-2"></i>Download PDF</a></li>
                <li><a class="dropdown-item" href="{{ route('accounting.bank-reconciliation.export', [$reconciliation, 'xlsx']) }}"><i class="feather-grid me-2"></i>Download Excel</a></li>
            </ul>
        </div>
        @if ($canReopen)
            <form action="{{ route('accounting.bank-reconciliation.reopen', $reconciliation) }}" method="POST" onsubmit="return confirm('Reopen this reconciliation? Its matches can then be changed and it must be completed again.');">
                @csrf
                <x-ui.button type="submit" variant="light" size="sm" class="border" icon="feather-unlock">Reopen</x-ui.button>
            </form>
        @endif
        <x-ui.button href="{{ route('accounting.bank-reconciliation.index') }}" variant="light" size="sm" class="border">Back</x-ui.button>
    </div>
@endsection

@section('content')
    @if (session('success'))
        <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="mb-3">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-3">{{ session('error') }}</x-ui.alert>
    @endif
    @if (session('import_errors'))
        <x-ui.alert variant="warning" icon="feather-info" dismissible class="mb-3">
            <div class="fw-semibold mb-1">Rows that could not be read</div>
            <ul class="mb-0 ps-3 fs-12">
                @foreach (session('import_errors') as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif
    @if ($errors->any())
        <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-3">
            <ul class="mb-0 ps-3 fs-12">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    {{-- Header: account, period, status --}}
    <x-ui.card class="mb-3">
        <div class="d-flex flex-wrap align-items-start gap-4 fs-13 text-dark">
            <div>
                <div class="text-muted fs-11 text-uppercase fw-semibold">Bank Account</div>
                <div class="fw-bold">{{ $account->code }} - {{ $account->name }}</div>
            </div>
            <div>
                <div class="text-muted fs-11 text-uppercase fw-semibold">Statement Period</div>
                <div>{{ $period }}</div>
            </div>
            <div>
                <div class="text-muted fs-11 text-uppercase fw-semibold">Bank Opening</div>
                <div>{{ $money($reconciliation->opening_balance) }}</div>
            </div>
            <div>
                <div class="text-muted fs-11 text-uppercase fw-semibold">Bank Closing</div>
                <div>{{ $money($reconciliation->closing_balance) }}</div>
            </div>
            <div>
                <div class="text-muted fs-11 text-uppercase fw-semibold">Status</div>
                @if ($reconciliation->isCompleted())
                    <x-ui.badge variant="success" soft>Completed {{ $reconciliation->completed_at?->format('d M Y') }} by {{ $reconciliation->completedBy?->name ?? '—' }}</x-ui.badge>
                @else
                    <x-ui.badge variant="warning" soft>In Progress</x-ui.badge>
                    @if ($reconciliation->reopened_at)
                        <div class="fs-11 text-muted mt-1">Reopened {{ $reconciliation->reopened_at->format('d M Y') }} by {{ $reconciliation->reopenedBy?->name ?? '—' }}</div>
                    @endif
                @endif
            </div>
            @if ($canUpdate)
                <div class="ms-auto">
                    <x-ui.button type="button" variant="light" size="sm" class="border" icon="feather-edit-2" data-bs-toggle="modal" data-bs-target="#brDetailsModal">Edit period / balances</x-ui.button>
                </div>
            @endif
        </div>
        @if ($reconciliation->notes)
            <div class="fs-12 text-muted mt-2">{{ $reconciliation->notes }}</div>
        @endif
    </x-ui.card>

    {{-- Summary tiles --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm h-100"><div class="card-body p-3">
                <div class="text-muted fs-11 text-uppercase fw-semibold">Balance as per Books</div>
                <div class="fs-5 fw-bold text-dark">{{ $money($brs['book_balance']) }}</div>
                <div class="fs-11 text-muted">on {{ $reconciliation->statement_date->format('d M Y') }}</div>
            </div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm h-100"><div class="card-body p-3">
                <div class="text-muted fs-11 text-uppercase fw-semibold">Balance as per Bank</div>
                <div class="fs-5 fw-bold text-dark">{{ $money($brs['statement_balance']) }}</div>
                <div class="fs-11 text-muted">statement closing</div>
            </div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm h-100 {{ $diffZero ? 'bg-soft-success' : 'bg-soft-danger' }}"><div class="card-body p-3">
                <div class="text-muted fs-11 text-uppercase fw-semibold">Difference</div>
                <div class="fs-5 fw-bold {{ $diffZero ? 'text-success' : 'text-danger' }}">{{ $money($brs['difference']) }}</div>
                <div class="fs-11 text-muted">bank − books adjusted</div>
            </div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm h-100"><div class="card-body p-3">
                <div class="text-muted fs-11 text-uppercase fw-semibold">Lines Matched</div>
                <div class="fs-5 fw-bold text-dark">{{ $counts['matched'] }} / {{ $counts['total'] }}</div>
                @php $pct = $counts['total'] ? round($counts['matched'] * 100 / $counts['total']) : 0; @endphp
                <div class="progress mt-1" style="height: 5px;"><div class="progress-bar bg-success" style="width: {{ $pct }}%"></div></div>
            </div></div>
        </div>
    </div>

    {{-- Readiness checklist + primary actions --}}
    <x-ui.card class="mb-3">
        <div class="row g-3 align-items-center">
            <div class="col-lg-8">
                <div class="row g-2">
                    @foreach ($readiness as $check)
                        <div class="col-md-6 d-flex gap-2 fs-12">
                            <i class="{{ $check['ok'] ? 'feather-check-circle text-success' : 'feather-alert-circle text-warning' }} mt-1"></i>
                            <div>
                                <div class="fw-semibold text-dark">{{ $check['label'] }}</div>
                                <div class="text-muted">{{ $check['detail'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @if ($isOpen)
                <div class="col-lg-4 d-flex flex-wrap gap-2 justify-content-lg-end">
                    @if ($canUpdate)
                        <form action="{{ route('accounting.bank-reconciliation.auto-match', $reconciliation) }}" method="POST">
                            @csrf
                            <x-ui.button type="submit" variant="primary" size="sm" icon="feather-zap" :disabled="$counts['unmatched'] === 0">Auto-Match</x-ui.button>
                        </form>
                    @endif
                    @if ($canComplete)
                        <form action="{{ route('accounting.bank-reconciliation.complete', $reconciliation) }}" method="POST">
                            @csrf
                            <x-ui.button type="submit" :variant="$ready ? 'success' : 'light'" size="sm" icon="feather-lock" :class="$ready ? '' : 'border'" title="{{ $ready ? 'Lock this reconciliation' : 'Resolve the open checks first' }}">Complete &amp; Lock</x-ui.button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </x-ui.card>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-0 border-bottom-0" role="tablist">
        <li class="nav-item"><button class="nav-link active fs-13" data-bs-toggle="tab" data-bs-target="#tab-lines" type="button">Statement Lines <span class="badge bg-soft-secondary text-dark ms-1">{{ $counts['total'] }}</span></button></li>
        <li class="nav-item"><button class="nav-link fs-13" data-bs-toggle="tab" data-bs-target="#tab-outstanding" type="button">Outstanding in Books <span class="badge bg-soft-secondary text-dark ms-1">{{ $brs['cheques_not_presented']->count() + $brs['deposits_not_credited']->count() }}</span></button></li>
        <li class="nav-item"><button class="nav-link fs-13" data-bs-toggle="tab" data-bs-target="#tab-brs" type="button">Reconciliation Statement</button></li>
        <li class="nav-item"><button class="nav-link fs-13" data-bs-toggle="tab" data-bs-target="#tab-uploads" type="button">Uploads <span class="badge bg-soft-secondary text-dark ms-1">{{ $reconciliation->statementUploads->count() }}</span></button></li>
    </ul>

    <div class="tab-content">
        {{-- Statement lines --}}
        <div class="tab-pane fade show active" id="tab-lines">
            <x-ui.card bodyClass="p-0" class="accounting-dense">
                @if ($canUpdate)
                    <div class="p-3 border-bottom">
                        <form action="{{ route('accounting.bank-reconciliation.import', $reconciliation) }}" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-end">
                            @csrf
                            <div>
                                <label class="form-label fw-semibold fs-11 text-uppercase mb-1 text-dark">Upload bank statement</label>
                                <input type="file" name="file" id="brStatementFile" class="form-control form-control-sm" required accept=".csv,.xlsx,.xls,.txt,.pdf">
                            </div>
                            <div id="brPasswordField" class="d-none">
                                <label class="form-label fw-semibold fs-11 text-uppercase mb-1 text-dark">PDF password</label>
                                <input type="password" name="password" class="form-control form-control-sm" autocomplete="off" placeholder="If the PDF is locked">
                            </div>
                            <x-ui.button type="submit" variant="light" size="sm" class="border" icon="feather-upload">Upload</x-ui.button>
                            <a href="{{ route('accounting.bank-reconciliation.template') }}" class="fs-12 text-muted text-decoration-none d-inline-flex align-items-center gap-1 pb-1">
                                <i class="feather-download"></i> Template
                            </a>
                            <div class="fs-11 text-muted w-100">
                                Upload the CSV/Excel exactly as downloaded from net banking — the header row and columns are found automatically (you'll be asked to map them once if not). Lines outside {{ $period }} and lines already imported are skipped. PDFs are read by the statement extraction service.
                            </div>
                        </form>
                    </div>
                @endif

                <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-bottom bg-light">
                    <div class="btn-group btn-group-sm" role="group" id="brLineFilter">
                        <button type="button" class="btn btn-light border active" data-filter="all">All</button>
                        <button type="button" class="btn btn-light border" data-filter="unmatched">Unmatched ({{ $counts['unmatched'] }})</button>
                        <button type="button" class="btn btn-light border" data-filter="matched">Matched ({{ $counts['matched'] }})</button>
                    </div>
                    <input type="search" id="brLineSearch" class="form-control form-control-sm" style="max-width: 240px;" placeholder="Search description, ref, amount…">
                    @if ($canUpdate && $counts['unmatched'] > 0)
                        <div class="ms-auto d-flex gap-2">
                            <x-ui.button type="button" variant="primary" size="sm" icon="feather-layers" id="brBulkPostBtn" disabled>Post selected…</x-ui.button>
                            <form action="{{ route('accounting.bank-reconciliation.delete-lines', $reconciliation) }}" method="POST" id="brDeleteLinesForm" onsubmit="return confirm('Delete the selected statement lines?');">
                                @csrf
                                <div id="brDeleteLinesInputs"></div>
                                <x-ui.button type="submit" variant="light" size="sm" class="border text-danger" icon="feather-trash-2" id="brDeleteLinesBtn" disabled>Delete selected</x-ui.button>
                            </form>
                        </div>
                        <div class="w-100 fs-11 text-muted">Tick lines to post them all at once as Receipt/Payment vouchers (ledgers pre-filled from your bank rules), or to delete wrong lines.</div>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                @if ($canUpdate)
                                    <th class="ps-3" style="width: 28px;"><input type="checkbox" class="form-check-input" id="brSelectAll" title="Select all unmatched"></th>
                                @endif
                                <th class="{{ $canUpdate ? '' : 'ps-3' }}" style="width: 95px;">Date</th>
                                <th>Description</th>
                                <th class="text-end" style="width: 110px;">Withdrawal</th>
                                <th class="text-end" style="width: 110px;">Deposit</th>
                                <th class="pe-3" style="min-width: 320px;">Match</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13 text-dark">
                            @forelse ($lines as $line)
                                @php
                                    $lineSuggestions = collect($suggestions[$line->id] ?? [])->map(fn ($id) => $candidateMap[$id] ?? null)->filter();
                                    $searchText = strtolower(($line->description ?? '') . ' ' . ($line->reference ?? '') . ' ' . number_format(abs($line->amount), 2, '.', ''));
                                @endphp
                                <tr data-status="{{ $line->is_matched ? 'matched' : 'unmatched' }}" data-search="{{ $searchText }}">
                                    @if ($canUpdate)
                                        <td class="ps-3">
                                            @unless ($line->is_matched)
                                                <input type="checkbox" class="form-check-input br-line-check" value="{{ $line->id }}">
                                            @endunless
                                        </td>
                                    @endif
                                    <td class="{{ $canUpdate ? '' : 'ps-3' }} text-nowrap">{{ $line->transaction_date->format('d M Y') }}</td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 340px;" title="{{ $line->description }}">{{ $line->description ?: '—' }}</div>
                                        <div class="fs-11 text-muted">
                                            @if ($line->reference)<span class="me-2">Ref {{ $line->reference }}</span>@endif
                                            @if (! $line->is_matched && isset($ruleSuggestions[$line->id]))
                                                <span class="badge bg-soft-info text-info" title="From bank rule “{{ $ruleSuggestions[$line->id]->name }}”">
                                                    <i class="feather-zap"></i> {{ $ruleSuggestions[$line->id]->targetAccount?->code }} {{ $ruleSuggestions[$line->id]->targetAccount?->name }}@if ($ruleSuggestions[$line->id]->party_name) · {{ $ruleSuggestions[$line->id]->party_name }}@endif
                                                </span>
                                            @elseif ($line->suggested_ledger && ! $line->is_matched)<span>Suggested: {{ $line->suggested_ledger }}</span>@endif
                                        </div>
                                    </td>
                                    <td class="text-end text-nowrap">{{ $line->amount < 0 ? $money(abs($line->amount)) : '' }}</td>
                                    <td class="text-end text-nowrap">{{ $line->amount > 0 ? $money($line->amount) : '' }}</td>
                                    <td class="pe-3">
                                        @if ($line->is_matched)
                                            <div class="d-flex align-items-start gap-2">
                                                <x-ui.badge variant="success" soft>Matched</x-ui.badge>
                                                <div class="fs-12 flex-grow-1">
                                                    @foreach ($line->matches as $match)
                                                        @php $entry = $match->journalEntry; @endphp
                                                        <div>
                                                            @if ($entry?->journal)
                                                                <a href="{{ route('accounting.journals.show', $entry->journal_id) }}" class="text-decoration-none">{{ $entry->journal->journal_number }}</a>
                                                                <span class="text-muted">· {{ $entry->journal->journal_date->format('d M Y') }} · {{ $money($match->amount) }}</span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                                @if ($canUpdate)
                                                    <form action="{{ route('accounting.bank-reconciliation.unmatch', [$reconciliation, $line->id]) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-link text-muted p-0 fs-12" title="Undo this match">Unmatch</button>
                                                    </form>
                                                @endif
                                            </div>
                                        @elseif ($canUpdate)
                                            <div class="d-flex flex-wrap gap-1 align-items-center">
                                                @foreach ($lineSuggestions as $candidate)
                                                    <form action="{{ route('accounting.bank-reconciliation.match', $reconciliation) }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="statement_line_id" value="{{ $line->id }}">
                                                        <input type="hidden" name="journal_entry_id" value="{{ $candidate->id }}">
                                                        <button type="submit" class="btn btn-sm btn-soft-success fs-11 py-0 px-2" title="{{ $candidate->description ?: $candidate->journal?->memo }}">
                                                            <i class="feather-check me-1"></i>{{ $candidate->journal?->journal_number }} · {{ $candidate->journal?->journal_date?->format('d M') }}
                                                        </button>
                                                    </form>
                                                @endforeach
                                                <button type="button" class="btn btn-sm btn-light border fs-11 py-0 px-2 br-open-match"
                                                    data-line="{{ json_encode(['id' => $line->id, 'date' => $line->transaction_date->format('d M Y'), 'iso' => $line->transaction_date->toDateString(), 'description' => $line->description, 'reference' => $line->reference, 'amount' => (float) $line->amount]) }}">
                                                    Match…
                                                </button>
                                                <button type="button" class="btn btn-sm btn-light border fs-11 py-0 px-2 br-open-adjust"
                                                    data-line="{{ json_encode(['id' => $line->id, 'date' => $line->transaction_date->format('d M Y'), 'description' => $line->description, 'amount' => (float) $line->amount, 'suggested' => $suggestedAccounts[$line->id] ?? null, 'party' => isset($ruleSuggestions[$line->id]) ? $ruleSuggestions[$line->id]->party_name : null, 'narration' => isset($ruleSuggestions[$line->id]) && $ruleSuggestions[$line->id]->narration ? $ruleSuggestions[$line->id]->narration : $line->description]) }}">
                                                    Post entry…
                                                </button>
                                                <button type="button" class="btn btn-sm btn-light border fs-11 py-0 px-2 br-open-settle"
                                                    data-line="{{ json_encode(['id' => $line->id, 'date' => $line->transaction_date->format('d M Y'), 'description' => $line->description, 'reference' => $line->reference, 'amount' => (float) $line->amount]) }}"
                                                    title="{{ $line->amount > 0 ? 'Receive against customer invoices' : 'Pay vendor bills' }}">
                                                    {{ $line->amount > 0 ? 'Invoices…' : 'Bills…' }}
                                                </button>
                                            </div>
                                        @else
                                            <x-ui.badge variant="warning" soft>Unmatched</x-ui.badge>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-5">No statement lines yet. Upload the bank statement for {{ $period }}.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($lines->isNotEmpty())
                            <tfoot class="fs-12 fw-semibold">
                                <tr class="table-light">
                                    <td colspan="{{ $canUpdate ? 3 : 2 }}" class="ps-3">Totals</td>
                                    <td class="text-end">{{ $money(abs($lines->where('amount', '<', 0)->sum('amount'))) }}</td>
                                    <td class="text-end">{{ $money($lines->where('amount', '>', 0)->sum('amount')) }}</td>
                                    <td class="pe-3 text-muted">Net {{ $money($brs['statement_check']['lines_total']) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </x-ui.card>
        </div>

        {{-- Outstanding in books --}}
        <div class="tab-pane fade" id="tab-outstanding">
            <x-ui.card bodyClass="p-0" class="accounting-dense">
                <div class="px-3 py-2 border-bottom fs-12 text-muted">
                    Entries in the books up to {{ $reconciliation->statement_date->format('d M Y') }} that the bank had not cleared by that date. They are the normal reconciling items of a BRS and roll into the next statement.
                </div>
                @foreach ([['Cheques issued / payments not yet presented', $brs['cheques_not_presented'], $brs['cheques_not_presented_total']], ['Cheques deposited / receipts not yet credited', $brs['deposits_not_credited'], $brs['deposits_not_credited_total']]] as [$title, $rows, $total])
                    <div class="px-3 pt-3 fw-semibold fs-13 text-dark">{{ $title }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light fs-11 text-uppercase text-muted">
                                <tr><th class="ps-3" style="width: 110px;">Date</th><th style="width: 140px;">Voucher</th><th>Particulars</th><th class="text-end pe-3" style="width: 130px;">Amount</th></tr>
                            </thead>
                            <tbody class="fs-12">
                                @forelse ($rows as $row)
                                    <tr>
                                        <td class="ps-3">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                                        <td><a href="{{ route('accounting.journals.show', $row['journal_id']) }}" class="text-decoration-none">{{ $row['journal_number'] }}</a></td>
                                        <td>{{ $row['description'] }} @if ($row['reference'])<span class="text-muted">(Ref {{ $row['reference'] }})</span>@endif</td>
                                        <td class="text-end pe-3">{{ $money($row['amount']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="ps-3 text-muted">None.</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot class="fs-12 fw-semibold"><tr><td colspan="3" class="ps-3">Total</td><td class="text-end pe-3">{{ $money($total) }}</td></tr></tfoot>
                        </table>
                    </div>
                @endforeach
            </x-ui.card>
        </div>

        {{-- Bank Reconciliation Statement --}}
        <div class="tab-pane fade" id="tab-brs">
            <x-ui.card>
                <div class="text-center mb-3">
                    <div class="fw-bold text-dark">{{ tenant_branding()['name'] ?? '' }}</div>
                    <div class="fw-semibold">Bank Reconciliation Statement as on {{ $reconciliation->statement_date->format('d M Y') }}</div>
                    <div class="fs-12 text-muted">{{ $account->code }} - {{ $account->name }}</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mx-auto fs-13" style="max-width: 720px;">
                        <tbody>
                            <tr class="fw-semibold"><td>Balance as per company books</td><td class="text-end">{{ $money($brs['book_balance']) }}</td></tr>
                            <tr><td class="ps-4">Add: Cheques issued / payments not yet presented <span class="text-muted">({{ $brs['cheques_not_presented']->count() }})</span></td><td class="text-end">{{ $money($brs['cheques_not_presented_total']) }}</td></tr>
                            <tr><td class="ps-4">Less: Cheques deposited / receipts not yet credited <span class="text-muted">({{ $brs['deposits_not_credited']->count() }})</span></td><td class="text-end">({{ $money($brs['deposits_not_credited_total']) }})</td></tr>
                            <tr><td class="ps-4">Add: Amounts credited by bank, not in books <span class="text-muted">({{ $brs['bank_credits_not_in_books']->count() }})</span></td><td class="text-end">{{ $money($brs['bank_credits_not_in_books_total']) }}</td></tr>
                            <tr><td class="ps-4">Less: Amounts debited by bank, not in books <span class="text-muted">({{ $brs['bank_debits_not_in_books']->count() }})</span></td><td class="text-end">({{ $money($brs['bank_debits_not_in_books_total']) }})</td></tr>
                            <tr class="fw-semibold border-top"><td>Balance as per bank (computed)</td><td class="text-end">{{ $money($brs['computed_bank_balance']) }}</td></tr>
                            <tr><td>Balance as per bank statement</td><td class="text-end">{{ $money($brs['statement_balance']) }}</td></tr>
                            <tr class="fw-bold {{ $diffZero ? 'text-success' : 'text-danger' }}"><td>Difference</td><td class="text-end">{{ $money($brs['difference']) }}</td></tr>
                        </tbody>
                    </table>
                </div>
                @if ($brs['bank_credits_not_in_books']->isNotEmpty() || $brs['bank_debits_not_in_books']->isNotEmpty())
                    <div class="fs-12 text-muted text-center">Amounts in the bank but not in the books are the unmatched statement lines — post them with <strong>Post entry…</strong> (bank charges, interest, direct debits) to bring them into the books.</div>
                @endif
                @if ($reconciliation->isCompleted() && $reconciliation->brs_snapshot)
                    <div class="fs-11 text-muted text-center mt-2">A copy of this statement was saved when the reconciliation was completed ({{ \Illuminate\Support\Carbon::parse($reconciliation->brs_snapshot['computed_at'] ?? $reconciliation->completed_at)->format('d M Y H:i') }}).</div>
                @endif
            </x-ui.card>
        </div>

        {{-- Uploads --}}
        <div class="tab-pane fade" id="tab-uploads">
            <x-ui.card bodyClass="p-0" class="accounting-dense">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr><th class="ps-3">File</th><th>Uploaded</th><th>Status</th><th>Lines</th><th>Detected account</th><th>Notes</th><th class="pe-3"></th></tr>
                        </thead>
                        <tbody class="fs-12 text-dark">
                            @forelse ($reconciliation->statementUploads as $upload)
                                @php
                                    $raw = $upload->raw_response ?? [];
                                    $accountInfo = array_filter($raw['account_info'] ?? []);
                                    $info = $raw['_import'] ?? $raw;
                                @endphp
                                <tr>
                                    <td class="ps-3">{{ $upload->original_filename }}</td>
                                    <td class="text-muted text-nowrap">{{ $upload->created_at->format('d M Y H:i') }}</td>
                                    <td>
                                        @if ($upload->status === 'completed')
                                            <x-ui.badge variant="success" soft>Completed</x-ui.badge>
                                        @elseif ($upload->status === 'failed')
                                            <x-ui.badge variant="danger" soft>Failed</x-ui.badge>
                                        @elseif ($upload->status === 'needs_mapping')
                                            @if ($canUpdate)
                                                <a href="{{ route('accounting.bank-reconciliation.mapping', [$reconciliation, $upload->id]) }}" class="badge bg-soft-warning text-warning text-decoration-none">Map columns →</a>
                                            @else
                                                <x-ui.badge variant="warning" soft>Needs mapping</x-ui.badge>
                                            @endif
                                        @else
                                            <x-ui.badge variant="warning" soft>Pending</x-ui.badge>
                                        @endif
                                    </td>
                                    <td>{{ $upload->extracted_count }}</td>
                                    <td class="text-muted">
                                        {{ collect([$accountInfo['bank'] ?? null, isset($accountInfo['account_no']) ? 'A/C ' . $accountInfo['account_no'] : null, $accountInfo['branch'] ?? null])->filter()->implode(' · ') ?: '—' }}
                                    </td>
                                    <td class="text-muted">
                                        @if ($upload->error_message)
                                            <span class="text-danger">{{ $upload->error_message }}</span>
                                        @else
                                            {{ collect([
                                                ! empty($info['duplicates']) ? $info['duplicates'] . ' already imported' : null,
                                                ! empty($info['out_of_period']) ? $info['out_of_period'] . ' outside period' : null,
                                                ! empty($info['invalid']) ? $info['invalid'] . ' unreadable' : null,
                                                ! empty($info['balance_mismatches']) ? $info['balance_mismatches'] . ' running-balance mismatch' : null,
                                                ! empty($info['header_row']) ? 'header on row ' . $info['header_row'] : null,
                                            ])->filter()->implode(', ') ?: '—' }}
                                        @endif
                                    </td>
                                    <td class="pe-3 text-end">
                                        @if ($canUpdate)
                                            <form action="{{ route('accounting.bank-reconciliation.delete-upload', [$reconciliation, $upload->id]) }}" method="POST" onsubmit="return confirm('Remove this upload and all its unmatched statement lines?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-link text-danger p-0 fs-12">Remove</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No uploads yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
    </div>

    @if ($canUpdate)
        {{-- Edit period / balances --}}
        <div class="modal fade" id="brDetailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" action="{{ route('accounting.bank-reconciliation.update-details', $reconciliation) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header"><h5 class="modal-title">Statement period &amp; balances</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body row g-3 fs-13">
                        <div class="col-6"><label class="form-label fw-semibold">Period from</label><input type="date" name="statement_from_date" class="form-control" value="{{ $reconciliation->statement_from_date?->toDateString() }}"></div>
                        <div class="col-6"><label class="form-label fw-semibold">Statement date <span class="text-danger">*</span></label><input type="date" name="statement_date" class="form-control" required value="{{ $reconciliation->statement_date->toDateString() }}"></div>
                        <div class="col-6"><label class="form-label fw-semibold">Bank opening balance <span class="text-danger">*</span></label><input type="number" step="0.01" name="opening_balance" class="form-control" required value="{{ $reconciliation->opening_balance }}"></div>
                        <div class="col-6"><label class="form-label fw-semibold">Bank closing balance <span class="text-danger">*</span></label><input type="number" step="0.01" name="closing_balance" class="form-control" required value="{{ $reconciliation->closing_balance }}"></div>
                        <div class="col-12 fs-11 text-muted">Use the balances printed on the bank statement. An overdrawn balance is negative.</div>
                        <div class="col-12"><label class="form-label fw-semibold">Notes</label><textarea name="notes" class="form-control" rows="2">{{ $reconciliation->notes }}</textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
                </form>
            </div>
        </div>

        {{-- Match one line to one or more ledger entries --}}
        <div class="modal fade" id="brMatchModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form class="modal-content" action="{{ route('accounting.bank-reconciliation.match', $reconciliation) }}" method="POST" id="brMatchForm">
                    @csrf
                    <input type="hidden" name="statement_line_id" id="brMatchLineId">
                    <div class="modal-header"><h5 class="modal-title">Match statement line</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body fs-13">
                        <div class="p-2 bg-light rounded mb-3 d-flex justify-content-between flex-wrap gap-2">
                            <div><span class="fw-semibold" id="brMatchLineDate"></span> · <span id="brMatchLineDesc"></span></div>
                            <div class="fw-bold" id="brMatchLineAmount"></div>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <input type="search" class="form-control form-control-sm" id="brMatchSearch" placeholder="Search voucher no., narration, reference, amount…">
                            <div class="form-check form-switch text-nowrap mt-1">
                                <input class="form-check-input" type="checkbox" id="brMatchSameSide" checked>
                                <label class="form-check-label fs-12" for="brMatchSameSide">Same direction only</label>
                            </div>
                        </div>
                        <div class="border rounded" style="max-height: 320px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light fs-11 text-uppercase text-muted" style="position: sticky; top: 0;">
                                    <tr><th style="width: 28px;"></th><th>Date</th><th>Voucher</th><th>Particulars</th><th class="text-end">Amount</th></tr>
                                </thead>
                                <tbody id="brMatchRows" class="fs-12"></tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between mt-2 fs-12">
                            <div>Selected: <span class="fw-semibold" id="brMatchSelected">0.00</span></div>
                            <div>Difference: <span class="fw-semibold" id="brMatchDiff">0.00</span></div>
                        </div>
                        <div id="brMatchDiffBox" class="mt-3 d-none">
                            <div class="fs-12 text-muted mb-1">The selected entries don't add up to the statement line. Post the difference (e.g. bank charges, rounding) to:</div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <select name="difference_account_id" class="form-select form-select-sm" id="brMatchDiffAccount">
                                        <option value="">Select account…</option>
                                        @foreach ($postableAccounts as $postable)
                                            <option value="{{ $postable->id }}">{{ $postable->code }} — {{ $postable->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6"><input type="text" name="difference_description" class="form-control form-control-sm" maxlength="255" placeholder="Narration (optional)"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="brMatchSubmit" disabled>Match</button></div>
                </form>
            </div>
        </div>

        {{-- Post a new entry for a bank-only line --}}
        <div class="modal fade" id="brAdjustModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" action="{{ route('accounting.bank-reconciliation.create-and-match', $reconciliation) }}" method="POST">
                    @csrf
                    <input type="hidden" name="statement_line_id" id="brAdjustLineId">
                    <div class="modal-header"><h5 class="modal-title">Post voucher for bank transaction</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body fs-13">
                        <div class="p-2 bg-light rounded mb-3 d-flex justify-content-between flex-wrap gap-2">
                            <div><span class="fw-semibold" id="brAdjustLineDate"></span> · <span id="brAdjustLineDesc"></span></div>
                            <div class="fw-bold" id="brAdjustLineAmount"></div>
                        </div>
                        <div class="fs-12 text-muted mb-2" id="brAdjustHint"></div>
                        <div id="brSingleBox">
                            <div class="d-flex justify-content-between align-items-end">
                                <label class="form-label fw-semibold mb-1">Ledger account <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-link btn-sm p-0 mb-1 fs-12" id="brSplitOn">Split across several ledgers</button>
                            </div>
                            <select name="chart_of_account_id" class="form-select mb-3" id="brAdjustAccount" required>
                                <option value="">Select account…</option>
                                @foreach ($postableAccounts as $postable)
                                    <option value="{{ $postable->id }}">{{ $postable->code }} — {{ $postable->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="brSplitBox" class="d-none mb-3">
                            <div class="d-flex justify-content-between align-items-end mb-1">
                                <label class="form-label fw-semibold mb-0">Split across ledgers</label>
                                <button type="button" class="btn btn-link btn-sm p-0 fs-12" id="brSplitOff">Use one ledger</button>
                            </div>
                            <table class="table table-sm mb-1"><tbody id="brSplitRows"></tbody></table>
                            <div class="d-flex justify-content-between fs-12">
                                <button type="button" class="btn btn-sm btn-light border py-0" id="brSplitAdd"><i class="feather-plus"></i> Add ledger</button>
                                <span>Remaining: <strong id="brSplitRemaining">0.00</strong></span>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Party</label>
                                <input type="text" name="party_name" id="brAdjustParty" class="form-control" maxlength="255" placeholder="Who paid / was paid">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Narration</label>
                                <input type="text" name="description" id="brAdjustDescription" class="form-control" maxlength="255">
                            </div>
                        </div>
                        <div class="fs-11 text-muted mt-2">Posted as a Receipt voucher for money in, a Payment voucher for money out (Contra between bank/cash). The ledger and party are remembered as a bank rule for similar lines.</div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Post &amp; Match</button></div>
                </form>
            </div>
        </div>

        {{-- Post many unmatched lines at once --}}
        <div class="modal fade" id="brBulkModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <form class="modal-content" action="{{ route('accounting.bank-reconciliation.bulk-post', $reconciliation) }}" method="POST">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Post selected lines as vouchers</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body fs-13">
                        <div class="fs-12 text-muted mb-2">Check the ledger and party for each line. Money in is posted as a Receipt voucher, money out as a Payment voucher; each is matched to its bank line. Lines left without a ledger are skipped.</div>
                        <div class="d-flex gap-2 mb-2 align-items-center">
                            <select class="form-select form-select-sm" id="brBulkAll" style="max-width: 320px;">
                                <option value="">Set one ledger for all rows without one…</option>
                                @foreach ($postableAccounts as $postable)
                                    <option value="{{ $postable->id }}">{{ $postable->code }} — {{ $postable->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <table class="table table-sm align-middle">
                            <thead class="table-light fs-11 text-uppercase text-muted">
                                <tr><th>Date</th><th>Bank narration</th><th class="text-end">Amount</th><th style="width: 26%;">Ledger</th><th style="width: 16%;">Party</th><th style="width: 20%;">Narration</th></tr>
                            </thead>
                            <tbody id="brBulkRows"></tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <span class="me-auto fs-12" id="brBulkSummary"></span>
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Post &amp; Match</button>
                    </div>
                </form>
            </div>
        </div>
        {{-- Receive against customer invoices / pay vendor bills --}}
        <div class="modal fade" id="brSettleModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form class="modal-content" action="{{ route('accounting.bank-reconciliation.settle', $reconciliation) }}" method="POST" id="brSettleForm" data-docs="{{ route('accounting.bank-reconciliation.party-documents', $reconciliation) }}">
                    @csrf
                    <input type="hidden" name="statement_line_id" id="brSettleLineId">
                    <input type="hidden" name="party_type" id="brSettleType">
                    <div class="modal-header"><h5 class="modal-title" id="brSettleTitle">Settle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body fs-13">
                        <div class="p-2 bg-light rounded mb-3 d-flex justify-content-between flex-wrap gap-2">
                            <div><span class="fw-semibold" id="brSettleLineDate"></span> · <span id="brSettleLineDesc"></span></div>
                            <div class="fw-bold" id="brSettleLineAmount"></div>
                        </div>
                        <label class="form-label fw-semibold" id="brSettlePartyLabel">Party</label>
                        <select name="party_id" class="form-select mb-3" id="brSettleParty" required></select>
                        <div id="brSettleDocs" class="border rounded" style="max-height: 300px; overflow-y: auto;">
                            <div class="p-3 text-muted fs-12">Choose the party to see their open documents.</div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 fs-12">
                            <div>Applied: <strong id="brSettleApplied">0.00</strong></div>
                            <div>On account (advance): <strong id="brSettleOnAccount">0.00</strong></div>
                        </div>
                        <div class="fs-11 text-muted mt-2" id="brSettleHint"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="brSettleSubmit">Record &amp; Match</button></div>
                </form>
            </div>
        </div>

        <select id="brAccountTemplate" class="d-none">
            <option value="">Select account…</option>
            @foreach ($postableAccounts as $postable)
                <option value="{{ $postable->id }}">{{ $postable->code }} — {{ $postable->name }}</option>
            @endforeach
        </select>
    @endif
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

@push('scripts')
<script>
(function () {
    // The layout's content wrapper creates its own stacking context, which puts
    // modals rendered inside it underneath Bootstrap's body-level backdrop
    // (page goes dark/blurred, dialog never shows). Hoist them to <body>.
    ['brDetailsModal', 'brMatchModal', 'brAdjustModal', 'brBulkModal', 'brSettleModal'].forEach((id) => {
        const el = document.getElementById(id);
        if (el && el.parentElement !== document.body) document.body.appendChild(el);
    });

    const fmt = (n) => Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // Filter + search statement lines.
    const rows = Array.from(document.querySelectorAll('#tab-lines tbody tr[data-status]'));
    let filter = 'all';
    const applyFilter = () => {
        const q = (document.getElementById('brLineSearch')?.value || '').trim().toLowerCase();
        rows.forEach((row) => {
            const okStatus = filter === 'all' || row.dataset.status === filter;
            const okSearch = !q || row.dataset.search.includes(q);
            row.classList.toggle('d-none', !(okStatus && okSearch));
        });
    };
    document.querySelectorAll('#brLineFilter [data-filter]').forEach((btn) => btn.addEventListener('click', () => {
        document.querySelectorAll('#brLineFilter [data-filter]').forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        filter = btn.dataset.filter;
        applyFilter();
    }));
    document.getElementById('brLineSearch')?.addEventListener('input', applyFilter);

    // PDF password field only for PDFs.
    document.getElementById('brStatementFile')?.addEventListener('change', (e) => {
        const isPdf = /\.pdf$/i.test(e.target.files[0]?.name || '');
        document.getElementById('brPasswordField')?.classList.toggle('d-none', !isPdf);
    });

    // Bulk delete selected unmatched lines.
    const checks = () => Array.from(document.querySelectorAll('.br-line-check'));
    const syncDelete = () => {
        const selected = checks().filter((c) => c.checked);
        const btn = document.getElementById('brDeleteLinesBtn');
        if (btn) { btn.disabled = selected.length === 0; btn.innerHTML = btn.innerHTML.replace(/Delete selected.*$/, 'Delete selected' + (selected.length ? ' (' + selected.length + ')' : '')); }
        const bulkBtn = document.getElementById('brBulkPostBtn');
        if (bulkBtn) { bulkBtn.disabled = selected.length === 0; }
        const box = document.getElementById('brDeleteLinesInputs');
        if (box) box.innerHTML = selected.map((c) => '<input type="hidden" name="line_ids[]" value="' + c.value + '">').join('');
    };
    checks().forEach((c) => c.addEventListener('change', syncDelete));
    document.getElementById('brSelectAll')?.addEventListener('change', (e) => {
        checks().forEach((c) => { if (!c.closest('tr').classList.contains('d-none')) c.checked = e.target.checked; });
        syncDelete();
    });

    // Match modal.
    const candidates = @json($candidatesJson);
    let currentLine = null;
    const matchModalEl = document.getElementById('brMatchModal');
    const renderCandidates = () => {
        if (!currentLine) return;
        const q = (document.getElementById('brMatchSearch').value || '').trim().toLowerCase();
        const sameSide = document.getElementById('brMatchSameSide').checked;
        const checked = new Set(Array.from(document.querySelectorAll('#brMatchRows input:checked')).map((i) => Number(i.value)));
        const list = candidates
            .filter((c) => !sameSide || Math.sign(c.amount) === Math.sign(currentLine.amount))
            .filter((c) => !q || [c.number, c.text, c.ref, c.date, Math.abs(c.amount).toFixed(2)].join(' ').toLowerCase().includes(q))
            .sort((a, b) => {
                const exactA = Math.abs(a.amount - currentLine.amount) < 0.005 ? 0 : 1;
                const exactB = Math.abs(b.amount - currentLine.amount) < 0.005 ? 0 : 1;
                if (exactA !== exactB) return exactA - exactB;
                return Math.abs(new Date(a.iso) - new Date(currentLine.iso)) - Math.abs(new Date(b.iso) - new Date(currentLine.iso));
            });
        document.getElementById('brMatchRows').innerHTML = list.length ? list.map((c) =>
            '<tr><td><input type="checkbox" class="form-check-input" name="journal_entry_ids[]" value="' + c.id + '" data-amount="' + c.amount + '"' + (checked.has(c.id) ? ' checked' : '') + '></td>'
            + '<td class="text-nowrap">' + esc(c.date) + '</td><td class="text-nowrap">' + esc(c.number) + '</td>'
            + '<td>' + esc(c.text) + (c.ref ? ' <span class="text-muted">(Ref ' + esc(c.ref) + ')</span>' : '') + '</td>'
            + '<td class="text-end text-nowrap' + (Math.abs(c.amount - currentLine.amount) < 0.005 ? ' fw-bold text-success' : '') + '">' + fmt(c.amount) + '</td></tr>'
        ).join('') : '<tr><td colspan="5" class="text-muted p-3">No open ledger entries on this bank account up to the statement date. Use “Post entry…” to record this transaction in the books.</td></tr>';
        updateTotals();
    };
    const updateTotals = () => {
        const selected = Array.from(document.querySelectorAll('#brMatchRows input:checked'));
        const totalMinor = selected.reduce((sum, i) => sum + Math.round(Number(i.dataset.amount) * 100), 0);
        const diffMinor = Math.round(currentLine.amount * 100) - totalMinor;
        document.getElementById('brMatchSelected').textContent = fmt(totalMinor / 100);
        const diffEl = document.getElementById('brMatchDiff');
        diffEl.textContent = fmt(diffMinor / 100);
        diffEl.className = 'fw-semibold ' + (diffMinor === 0 ? 'text-success' : 'text-danger');
        const needsAccount = selected.length > 0 && diffMinor !== 0;
        document.getElementById('brMatchDiffBox').classList.toggle('d-none', !needsAccount);
        document.getElementById('brMatchDiffAccount').required = needsAccount;
        document.getElementById('brMatchSubmit').disabled = selected.length === 0;
    };
    document.getElementById('brMatchRows')?.addEventListener('change', updateTotals);
    document.getElementById('brMatchSearch')?.addEventListener('input', renderCandidates);
    document.getElementById('brMatchSameSide')?.addEventListener('change', renderCandidates);
    document.querySelectorAll('.br-open-match').forEach((btn) => btn.addEventListener('click', () => {
        currentLine = JSON.parse(btn.dataset.line);
        document.getElementById('brMatchLineId').value = currentLine.id;
        document.getElementById('brMatchLineDate').textContent = currentLine.date;
        document.getElementById('brMatchLineDesc').textContent = currentLine.description || '—';
        document.getElementById('brMatchLineAmount').textContent = (currentLine.amount > 0 ? 'Deposit ' : 'Withdrawal ') + fmt(Math.abs(currentLine.amount));
        document.getElementById('brMatchSearch').value = '';
        document.getElementById('brMatchRows').innerHTML = '';
        document.getElementById('brMatchDiffAccount').value = '';
        renderCandidates();
        bootstrap.Modal.getOrCreateInstance(matchModalEl).show();
    }));

    // Adjustment modal.
    const adjustModalEl = document.getElementById('brAdjustModal');
    document.querySelectorAll('.br-open-adjust').forEach((btn) => btn.addEventListener('click', () => {
        const line = JSON.parse(btn.dataset.line);
        document.getElementById('brAdjustLineId').value = line.id;
        document.getElementById('brAdjustLineDate').textContent = line.date;
        document.getElementById('brAdjustLineDesc').textContent = line.description || '—';
        document.getElementById('brAdjustLineAmount').textContent = (line.amount > 0 ? 'Deposit ' : 'Withdrawal ') + fmt(Math.abs(line.amount));
        document.getElementById('brAdjustHint').textContent = line.amount > 0
            ? 'Money received by the bank that is not in the books (e.g. interest, direct credit). Posts: Dr {{ $account->name }} / Cr the account below.'
            : 'Money taken by the bank that is not in the books (e.g. bank charges, direct debit). Posts: Dr the account below / Cr {{ $account->name }}.';
        document.getElementById('brAdjustAccount').value = line.suggested || '';
        document.getElementById('brAdjustParty').value = line.party || '';
        document.getElementById('brAdjustDescription').value = line.narration || line.description || '';
        adjustLine = line;
        setSplit(false);
        bootstrap.Modal.getOrCreateInstance(adjustModalEl).show();
    }));

    // Split one bank line across several ledgers (e.g. amount + GST).
    let adjustLine = null;
    const template = document.getElementById('brAccountTemplate');
    const splitRows = document.getElementById('brSplitRows');
    const addSplit = (accountId = '', amount = '') => {
        const i = splitRows.children.length;
        const tr = document.createElement('tr');
        const select = template.cloneNode(true);
        select.removeAttribute('id');
        select.className = 'form-select form-select-sm';
        select.name = `splits[${i}][account_id]`;
        select.value = accountId;
        tr.innerHTML = '<td class="ps-0" style="width: 60%;"></td><td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="splits[' + i + '][amount]"></td><td class="pe-0 text-end"><button type="button" class="btn btn-sm btn-link text-danger p-0">✕</button></td>';
        tr.children[0].appendChild(select);
        tr.querySelector('input').value = amount;
        tr.querySelector('input').addEventListener('input', splitRemaining);
        tr.querySelector('button').addEventListener('click', () => { tr.remove(); renumber(); splitRemaining(); });
        splitRows.appendChild(tr);
        splitRemaining();
    };
    const renumber = () => Array.from(splitRows.children).forEach((tr, i) => {
        tr.querySelector('select').name = `splits[${i}][account_id]`;
        tr.querySelector('input').name = `splits[${i}][amount]`;
    });
    const splitRemaining = () => {
        if (!adjustLine) return;
        const used = Array.from(splitRows.querySelectorAll('input')).reduce((s, i) => s + Math.round(Number(i.value || 0) * 100), 0);
        const left = Math.round(Math.abs(adjustLine.amount) * 100) - used;
        const el = document.getElementById('brSplitRemaining');
        el.textContent = fmt(left / 100);
        el.className = left === 0 ? 'text-success' : 'text-danger';
    };
    const setSplit = (on) => {
        document.getElementById('brSplitBox').classList.toggle('d-none', !on);
        document.getElementById('brSingleBox').classList.toggle('d-none', on);
        const single = document.getElementById('brAdjustAccount');
        single.required = !on;
        single.disabled = on;
        splitRows.innerHTML = '';
        if (on) {
            addSplit(single.value, Math.abs(adjustLine.amount).toFixed(2));
            addSplit('', '');
        }
    };
    document.getElementById('brSplitOn')?.addEventListener('click', () => setSplit(true));
    document.getElementById('brSplitOff')?.addEventListener('click', () => setSplit(false));
    document.getElementById('brSplitAdd')?.addEventListener('click', () => addSplit());

    // Settle a line against customer invoices / vendor bills.
    const parties = { customer: @json($customers ?? []), vendor: @json($vendors ?? []) };
    const settleModalEl = document.getElementById('brSettleModal');
    const settleForm = document.getElementById('brSettleForm');
    let settleLine = null;
    const words = (t) => String(t || '').toLowerCase().replace(/[^a-z0-9 ]+/g, ' ').split(/\s+/).filter((w) => w.length >= 3 && !/^(pvt|ltd|limited|private|the|and|traders|enterprises|india|co)$/.test(w));
    const guessParty = (list, narration) => {
        const text = ' ' + String(narration || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ') + ' ';
        let best = null, bestScore = 0;
        list.forEach((p) => {
            const w = words(p.name);
            const hits = w.filter((x) => text.includes(' ' + x + ' ')).length;
            const score = w.length ? hits / w.length : 0;
            if (hits > 0 && score > bestScore) { best = p; bestScore = score; }
        });
        return best;
    };
    const settleTotals = () => {
        const applied = Array.from(settleForm.querySelectorAll('.br-alloc')).reduce((s, i) => s + Math.round(Number(i.value || 0) * 100), 0);
        const total = Math.round(Math.abs(settleLine.amount) * 100);
        document.getElementById('brSettleApplied').textContent = fmt(applied / 100);
        const left = total - applied;
        const el = document.getElementById('brSettleOnAccount');
        el.textContent = fmt(left / 100);
        el.className = left < 0 ? 'text-danger' : '';
        document.getElementById('brSettleSubmit').disabled = left < 0 || !document.getElementById('brSettleParty').value;
    };
    const loadDocs = async () => {
        const partyId = document.getElementById('brSettleParty').value;
        const box = document.getElementById('brSettleDocs');
        settleTotals();
        if (!partyId) { box.innerHTML = '<div class="p-3 text-muted fs-12">Choose the party to see their open documents.</div>'; return; }
        box.innerHTML = '<div class="p-3 text-muted fs-12">Loading…</div>';
        const url = settleForm.dataset.docs + '?type=' + document.getElementById('brSettleType').value + '&party_id=' + partyId;
        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const docs = res.ok ? (await res.json()).documents : [];
        if (!docs.length) { box.innerHTML = '<div class="p-3 text-muted fs-12">No open documents — the whole amount will be recorded on account (advance).</div>'; settleTotals(); return; }
        // Pre-fill: an exact match first, otherwise oldest first until the money runs out.
        let left = Math.round(Math.abs(settleLine.amount) * 100);
        const exact = docs.find((d) => Math.round(d.balance * 100) === left);
        const fill = {};
        if (exact) { fill[exact.id] = exact.balance; } else {
            docs.forEach((d) => { const take = Math.min(left, Math.round(d.balance * 100)); if (take > 0) { fill[d.id] = take / 100; left -= take; } });
        }
        box.innerHTML = '<table class="table table-sm mb-0"><thead class="table-light fs-11 text-uppercase text-muted"><tr><th>Document</th><th>Date</th><th>Due</th><th class="text-end">Outstanding</th><th class="text-end" style="width: 140px;">Apply</th></tr></thead><tbody>'
            + docs.map((d) => '<tr><td>' + esc(d.number) + '</td><td>' + esc(d.date || '') + '</td><td>' + esc(d.due || '') + '</td><td class="text-end">' + fmt(d.balance) + '</td>'
                + '<td><input type="number" step="0.01" min="0" max="' + d.balance + '" class="form-control form-control-sm text-end br-alloc" name="allocations[' + d.id + ']" value="' + (fill[d.id] ? fill[d.id].toFixed(2) : '') + '"></td></tr>').join('')
            + '</tbody></table>';
        box.querySelectorAll('.br-alloc').forEach((i) => i.addEventListener('input', settleTotals));
        settleTotals();
    };
    document.getElementById('brSettleParty')?.addEventListener('change', loadDocs);
    document.querySelectorAll('.br-open-settle').forEach((btn) => btn.addEventListener('click', () => {
        settleLine = JSON.parse(btn.dataset.line);
        const type = settleLine.amount > 0 ? 'customer' : 'vendor';
        document.getElementById('brSettleLineId').value = settleLine.id;
        document.getElementById('brSettleType').value = type;
        document.getElementById('brSettleTitle').textContent = type === 'customer' ? 'Receive against customer invoices' : 'Pay vendor bills';
        document.getElementById('brSettlePartyLabel').textContent = type === 'customer' ? 'Customer' : 'Vendor';
        document.getElementById('brSettleLineDate').textContent = settleLine.date;
        document.getElementById('brSettleLineDesc').textContent = settleLine.description || '—';
        document.getElementById('brSettleLineAmount').textContent = (settleLine.amount > 0 ? 'Deposit ' : 'Withdrawal ') + fmt(Math.abs(settleLine.amount));
        document.getElementById('brSettleHint').textContent = type === 'customer'
            ? 'Records a customer payment in Sales (invoices marked paid / partly paid), posts Dr {{ $account->name }} / Cr Accounts Receivable, and matches it to this line. Anything not applied stays as an advance on the customer.'
            : 'Records a vendor payment in Purchase (bills marked paid / partly paid), posts Dr Accounts Payable / Cr {{ $account->name }}, and matches it to this line. Anything not applied is an advance to the vendor.';
        const select = document.getElementById('brSettleParty');
        const list = parties[type];
        select.innerHTML = '<option value="">Select ' + type + '…</option>' + list.map((p) => '<option value="' + p.id + '">' + esc(p.name) + '</option>').join('');
        const guess = guessParty(list, settleLine.description);
        select.value = guess ? String(guess.id) : '';
        loadDocs();
        bootstrap.Modal.getOrCreateInstance(settleModalEl).show();
    }));

    // Post selected lines in one go.
    const bulkLines = @json($bulkLines);
    const bulkModalEl = document.getElementById('brBulkModal');
    const bulkSummary = () => {
        const rows = Array.from(document.querySelectorAll('#brBulkRows tr'));
        const ready = rows.filter((tr) => tr.querySelector('select').value !== '').length;
        document.getElementById('brBulkSummary').textContent = ready + ' of ' + rows.length + ' line(s) ready to post.';
    };
    document.getElementById('brBulkPostBtn')?.addEventListener('click', () => {
        const ids = new Set(checks().filter((c) => c.checked).map((c) => Number(c.value)));
        const body = document.getElementById('brBulkRows');
        body.innerHTML = '';
        bulkLines.filter((l) => ids.has(l.id)).forEach((l) => {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td class="text-nowrap">' + esc(l.date) + '</td>'
                + '<td>' + esc(l.description || '—') + (l.rule ? '<div class="fs-11 text-info"><i class="feather-zap"></i> ' + esc(l.rule) + '</div>' : '') + '</td>'
                + '<td class="text-end text-nowrap ' + (l.amount > 0 ? 'text-success' : 'text-danger') + '">' + (l.amount > 0 ? '+' : '−') + fmt(Math.abs(l.amount)) + '</td>'
                + '<td></td>'
                + '<td><input type="text" class="form-control form-control-sm" maxlength="255" name="items[' + l.id + '][party_name]"></td>'
                + '<td><input type="text" class="form-control form-control-sm" maxlength="255" name="items[' + l.id + '][narration]"></td>';
            const select = template.cloneNode(true);
            select.removeAttribute('id');
            select.className = 'form-select form-select-sm';
            select.name = 'items[' + l.id + '][account_id]';
            select.value = l.account || '';
            select.addEventListener('change', bulkSummary);
            tr.children[3].appendChild(select);
            tr.querySelector('[name$="[party_name]"]').value = l.party || '';
            tr.querySelector('[name$="[narration]"]').value = l.narration || '';
            body.appendChild(tr);
        });
        document.getElementById('brBulkAll').value = '';
        bulkSummary();
        bootstrap.Modal.getOrCreateInstance(bulkModalEl).show();
    });
    document.getElementById('brBulkAll')?.addEventListener('change', (e) => {
        if (!e.target.value) return;
        document.querySelectorAll('#brBulkRows select').forEach((s) => { if (!s.value) s.value = e.target.value; });
        bulkSummary();
    });
})();
</script>
@endpush
