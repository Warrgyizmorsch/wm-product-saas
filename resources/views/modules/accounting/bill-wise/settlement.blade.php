@extends('layouts.duralux')

@section('title', 'Bill Settlement | SaaS ERP')
@section('page-title', 'Bill Settlement')
@section('breadcrumb', 'Accounting / Bill Settlement')

@php
    $drCr = fn ($v) => number_format(abs((float) $v), 2) . ((float) $v >= 0 ? ' Dr' : ' Cr');
    $ledgerName = fn ($id) => isset($ledgers[$id]) ? $ledgers[$id]->code . ' — ' . $ledgers[$id]->name : '#' . $id;
    $typeLabels = ['on_account' => 'On Account', 'advance' => 'Advance'];
@endphp

@section('content')
    <x-ui.card class="mb-4">
        <x-ui.filter-toolbar formId="billSettlementFilter" :resetUrl="route('accounting.bill-wise.settlement')" searchLabel="Open">
            <x-ui.filter-field label="Party" col="col-md-6">
                <select name="party" id="settlementParty" class="form-select">
                    @if ($party)
                        <option value="{{ $party }}" selected>{{ $partyName }} ({{ $partyLabel }})</option>
                    @endif
                </select>
            </x-ui.filter-field>
        </x-ui.filter-toolbar>
        <p class="fs-12 text-muted mb-0 mt-2">
            Match money a party paid or received <strong>On Account</strong> (or as an <strong>Advance</strong>) against their open bills.
            On the same ledger nothing is posted — only the bill-wise breakup changes. An advance kept on another ledger
            (e.g. Advance from Customers against a Receivable bill) is moved by a small transfer journal.
        </p>
    </x-ui.card>

    @if (! $party)
        <x-ui.card>
            <div class="text-center py-5 text-muted">
                <i class="feather-git-merge fs-1 mb-2 d-block"></i>
                Choose a customer, supplier, employee or transporter to see their open bills and unadjusted money.
            </div>
        </x-ui.card>
    @else
        @if ($opening)
            @php
                $openingRows = old('rows', $opening['rows'] ?: [['ref' => '', 'bill_date' => '', 'due_date' => '', 'amount' => '']]);
            @endphp
            <x-ui.card class="mb-4 accounting-dense" bodyClass="p-0">
                <x-slot:title>
                    Opening balance {{ number_format($opening['amount'], 2) }}
                    <span class="fs-12 fw-normal text-muted">as on {{ \Illuminate\Support\Carbon::parse($opening['date'])->format('d M Y') }}</span>
                    @if ($opening['rows'])
                        <span class="badge bg-soft-success text-success ms-1">{{ count($opening['rows']) }} old bills</span>
                    @else
                        <span class="badge bg-soft-secondary text-secondary ms-1">one bill</span>
                    @endif
                </x-slot:title>
                <form method="POST" action="{{ route('accounting.bill-wise.opening-breakup') }}" id="openingBreakupForm">
                    @csrf
                    <input type="hidden" name="party" value="{{ $party }}">
                    <p class="fs-12 text-muted px-3 pt-3 mb-2">
                        Break the opening into the old bills it is made of, so ageing and Agst Ref work bill by bill.
                        The bills must add up to the opening. Anything already received / paid against the opening moves onto the oldest bills.
                    </p>
                    <x-ui.table>
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-3">Old Bill No.</th>
                                <th>Bill Date</th>
                                <th>Due Date</th>
                                <th class="text-end">Amount</th>
                                <th class="pe-3" style="width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="openingRows">
                            @foreach ($openingRows as $i => $row)
                                <tr>
                                    <td class="ps-3"><input type="text" name="rows[{{ $i }}][ref]" value="{{ $row['ref'] ?? '' }}" maxlength="100" class="form-control form-control-sm" @disabled(! $canSettle)></td>
                                    <td><input type="date" name="rows[{{ $i }}][bill_date]" value="{{ $row['bill_date'] ?? '' }}" max="{{ $opening['date'] }}" class="form-control form-control-sm" @disabled(! $canSettle)></td>
                                    <td><input type="date" name="rows[{{ $i }}][due_date]" value="{{ $row['due_date'] ?? '' }}" class="form-control form-control-sm" @disabled(! $canSettle)></td>
                                    <td><input type="number" step="0.01" min="0" name="rows[{{ $i }}][amount]" value="{{ $row['amount'] ?? '' }}" class="form-control form-control-sm text-end opening-amount" @disabled(! $canSettle)></td>
                                    <td class="pe-3 text-center"><button type="button" class="btn btn-link btn-sm text-danger p-0 opening-remove" title="Remove" @disabled(! $canSettle)><i class="feather-x"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                    @if ($canSettle)
                        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center p-3">
                            <button type="button" class="btn btn-light btn-sm border" id="openingAdd"><i class="feather-plus me-1"></i>Add bill</button>
                            <div class="fs-12">Total <b id="openingTotal">0.00</b> of {{ number_format($opening['amount'], 2) }} <span id="openingDiff" class="ms-1"></span></div>
                            <div class="d-flex gap-2">
                                @if ($opening['rows'])
                                    <button type="submit" class="btn btn-light btn-sm border" id="openingReset" formnovalidate>Make it one bill again</button>
                                @endif
                                <button type="submit" class="btn btn-primary btn-sm" id="openingSave">Save breakup</button>
                            </div>
                        </div>
                    @endif
                </form>
            </x-ui.card>
        @endif

        <form method="POST" action="{{ route('accounting.bill-wise.settlement.store') }}" id="settlementForm">
            @csrf
            <input type="hidden" name="party" value="{{ $party }}">

            <div class="row g-4">
                <div class="col-xl-6">
                    <x-ui.card :title="'Open bills — ' . $partyName" bodyClass="p-0" class="h-100 accounting-dense">
                        <x-ui.table hoverable>
                            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                                <tr>
                                    <th class="ps-3">Bill / Ref</th>
                                    <th>Due</th>
                                    <th class="text-end">Open</th>
                                    <th class="text-end pe-3" style="width: 130px;">Adjust</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13">
                                @forelse ($bills as $row)
                                    @php $overdue = $row->bill->due_date && $row->bill->due_date->isPast() ? (int) $row->bill->due_date->diffInDays(now()) : 0; @endphp
                                    <tr>
                                        <td class="ps-3">
                                            <div class="font-monospace">{{ $row->bill->ref_number }}</div>
                                            <div class="fs-11 text-muted">{{ $ledgerName($row->ledger_id) }} · {{ $row->bill->bill_date?->format('d M Y') }}</div>
                                        </td>
                                        <td class="{{ $overdue ? 'text-danger' : 'text-muted' }}">
                                            {{ $row->bill->due_date?->format('d M Y') ?? '—' }}
                                            @if ($overdue)<div class="fs-11">{{ $overdue }} days overdue</div>@endif
                                        </td>
                                        <td class="text-end">{{ $drCr($row->outstanding) }}</td>
                                        <td class="pe-3">
                                            <input type="number" step="0.01" min="0" max="{{ abs($row->outstanding) }}"
                                                name="bills[{{ $row->bill->id }}]" value="{{ old('bills.' . $row->bill->id) }}"
                                                class="form-control form-control-sm text-end bill-input" data-side="{{ $row->outstanding > 0 ? 'dr' : 'cr' }}"
                                                data-open="{{ abs($row->outstanding) }}" @disabled(! $canSettle || $pieces->isEmpty())
                                                @if ($pieces->isEmpty()) title="Nothing unadjusted to match against this bill" placeholder="—" @endif>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No open bills.</td></tr>
                                @endforelse
                            </tbody>
                        </x-ui.table>
                    </x-ui.card>
                </div>

                <div class="col-xl-6">
                    <x-ui.card title="Unadjusted money (On Account / Advance)" bodyClass="p-0" class="h-100 accounting-dense">
                        <x-ui.table hoverable>
                            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                                <tr>
                                    <th class="ps-3">Voucher</th>
                                    <th>Type</th>
                                    <th class="text-end">Unadjusted</th>
                                    <th class="text-end pe-3" style="width: 130px;">Use</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13">
                                @forelse ($pieces as $piece)
                                    <tr>
                                        <td class="ps-3">
                                            <a href="{{ route('accounting.journals.show', $piece->entry->journal_id) }}" class="font-monospace">{{ $piece->entry->journal->journal_number }}</a>
                                            <div class="fs-11 text-muted">{{ $ledgerName($piece->ledger_id) }} · {{ \Illuminate\Support\Carbon::parse($piece->date)->format('d M Y') }}</div>
                                            @if ($piece->entry->description)
                                                <div class="fs-11 text-muted text-truncate" style="max-width: 260px;">{{ $piece->entry->description }}</div>
                                            @endif
                                        </td>
                                        <td><span class="badge bg-soft-secondary text-secondary">{{ $typeLabels[$piece->ref_type] ?? $piece->ref_type }}</span></td>
                                        <td class="text-end">{{ $drCr($piece->amount) }}</td>
                                        <td class="pe-3">
                                            <input type="number" step="0.01" min="0" max="{{ abs($piece->amount) }}"
                                                name="pieces[{{ $piece->key }}]" value="{{ old('pieces.' . $piece->key) }}"
                                                class="form-control form-control-sm text-end piece-input" data-side="{{ $piece->amount > 0 ? 'dr' : 'cr' }}"
                                                data-open="{{ abs($piece->amount) }}" @disabled(! $canSettle)>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            Nothing unadjusted — every payment is matched to a bill.
                                            @if ($bills->isNotEmpty())
                                                @php $voucher = str_starts_with((string) $party, 'customer:') ? 'receipt' : 'payment'; @endphp
                                                <div class="fs-12 mt-2">
                                                    Adjusting only re-labels money already paid or received. To reduce a bill, record a
                                                    @if (\Illuminate\Support\Facades\Route::has("accounting.vouchers.{$voucher}.index"))
                                                        <a href="{{ route("accounting.vouchers.{$voucher}.index") }}">{{ $voucher === 'receipt' ? 'Receipt' : 'Payment' }} Voucher</a>
                                                    @else
                                                        {{ $voucher === 'receipt' ? 'Receipt' : 'Payment' }} Voucher
                                                    @endif
                                                    and pick the bill under "Pick party &amp; bills".
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.table>
                    </x-ui.card>
                </div>
            </div>

            @if ($canSettle && $bills->isNotEmpty() && $pieces->isNotEmpty())
                <x-ui.card class="mt-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label fs-12 fw-semibold mb-1">Adjust on</label>
                            <input type="date" name="settled_on" class="form-control form-control-sm" value="{{ old('settled_on', now()->toDateString()) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-12 fw-semibold mb-1">Note</label>
                            <input type="text" name="note" maxlength="255" class="form-control form-control-sm" value="{{ old('note') }}" placeholder="Optional">
                        </div>
                        <div class="col-md-3 fs-13">
                            Bills <b id="sumBills">0.00</b> · Money used <b id="sumPieces">0.00</b>
                            <div class="fs-11" id="sumStatus"></div>
                        </div>
                        <div class="col-md-3 d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-light btn-sm border" id="autoMatch">Auto-match oldest first</button>
                            <button type="button" class="btn btn-light btn-sm border" id="clearMatch">Clear</button>
                            <button type="submit" class="btn btn-primary btn-sm" id="saveMatch" disabled>Save</button>
                        </div>
                    </div>
                </x-ui.card>
            @endif
        </form>

        @if ($history->isNotEmpty())
            <x-ui.card title="Adjustments made" bodyClass="p-0" class="mt-4 accounting-dense">
                <x-ui.table hoverable>
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-3">Date</th>
                            <th>Ledger</th>
                            <th class="text-end">Amount</th>
                            <th>Transfer Journal</th>
                            <th>By</th>
                            <th>Note</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody class="fs-13">
                        @foreach ($history as $settlement)
                            <tr>
                                <td class="ps-3">{{ $settlement->settled_on?->format('d M Y') }}</td>
                                <td>{{ $ledgerName($settlement->control_account_id) }}</td>
                                <td class="text-end">{{ number_format((float) $settlement->amount, 2) }}</td>
                                <td>
                                    @if ($settlement->journal)
                                        <a href="{{ route('accounting.journals.show', $settlement->journal) }}" class="font-monospace">{{ $settlement->journal->journal_number }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $settlement->creator?->name ?? '—' }}</td>
                                <td class="text-muted">{{ $settlement->note ?: '—' }}</td>
                                <td class="pe-3 text-end">
                                    @if ($canSettle)
                                        <form method="POST" action="{{ route('accounting.bill-wise.settlement.destroy', $settlement) }}" onsubmit="return confirm('Undo this adjustment? The bills will be open again.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-link btn-sm text-danger p-0">Undo</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        @endif
    @endif
@endsection

@push('styles')
    <style>
        .accounting-dense table th,
        .accounting-dense table td {
            padding: 6px 10px !important;
            font-size: 12px !important;
            vertical-align: middle;
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(function () {
            const $party = $('#settlementParty');
            if (typeof $.fn.select2 === 'function') {
                $party.select2({
                    theme: 'bootstrap-5', width: '100%',
                    placeholder: 'Search customer, supplier, employee, transporter…',
                    ajax: { url: @json(route('accounting.bill-wise.parties')), dataType: 'json', delay: 250, data: (p) => ({ q: p.term || '' }), processResults: (d) => d },
                });
            }
            $party.on('change', function () {
                if (this.value) document.getElementById('billSettlementFilter').submit();
            });

            const suggestion = @json($suggestion);
            const fmt = (n) => (Math.round(n * 100) / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const sum = (sel) => $(sel).toArray().reduce((s, el) => s + (parseFloat(el.value) || 0), 0);

            function refresh() {
                const bills = sum('.bill-input');
                const pieces = sum('.piece-input');
                $('#sumBills').text(fmt(bills));
                $('#sumPieces').text(fmt(pieces));
                const sides = new Set($('.bill-input').filter((i, el) => parseFloat(el.value) > 0).map((i, el) => el.dataset.side).get());
                const pieceSides = new Set($('.piece-input').filter((i, el) => parseFloat(el.value) > 0).map((i, el) => el.dataset.side).get());
                let status = '';
                let ok = bills > 0 && Math.abs(bills - pieces) < 0.005;
                if (sides.size > 1) { status = 'Pick bills on one side only.'; ok = false; }
                else if ([...pieceSides].some((s) => sides.has(s))) { status = 'That money is on the same side as the bills.'; ok = false; }
                else if (bills > 0 && !ok) { status = 'Difference ' + fmt(Math.abs(bills - pieces)); }
                else if (ok) { status = 'Ready to save'; }
                $('#sumStatus').text(status).toggleClass('text-danger', !ok && status !== '').toggleClass('text-success', ok);
                $('#saveMatch').prop('disabled', !ok);
            }

            $(document).on('input', '.bill-input, .piece-input', refresh);
            $('#clearMatch').on('click', function () { $('.bill-input, .piece-input').val(''); refresh(); });
            $('#autoMatch').on('click', function () {
                $('.bill-input, .piece-input').val('');
                Object.entries(suggestion.bills || {}).forEach(([id, v]) => $(`input[name="bills[${id}]"]`).val(Number(v).toFixed(2)));
                Object.entries(suggestion.pieces || {}).forEach(([key, v]) => $(`input[name="pieces[${key}]"]`).val(Number(v).toFixed(2)));
                refresh();
            });
            refresh();

            // Opening breakup rows.
            const openingAmount = @json($opening['amount'] ?? 0);
            const openingDate = @json($opening['date'] ?? null);
            let openingIndex = $('#openingRows tr').length;
            function openingRefresh() {
                const total = sum('.opening-amount');
                const diff = Math.round((openingAmount - total) * 100) / 100;
                $('#openingTotal').text(fmt(total));
                $('#openingDiff').text(Math.abs(diff) < 0.005 ? 'matches' : (diff > 0 ? fmt(diff) + ' short' : fmt(-diff) + ' over'))
                    .toggleClass('text-success', Math.abs(diff) < 0.005).toggleClass('text-danger', Math.abs(diff) >= 0.005);
                $('#openingSave').prop('disabled', Math.abs(diff) >= 0.005);
            }
            $('#openingAdd').on('click', function () {
                const i = openingIndex++;
                $('#openingRows').append(`<tr>
                    <td class="ps-3"><input type="text" name="rows[${i}][ref]" maxlength="100" class="form-control form-control-sm"></td>
                    <td><input type="date" name="rows[${i}][bill_date]" max="${openingDate}" class="form-control form-control-sm"></td>
                    <td><input type="date" name="rows[${i}][due_date]" class="form-control form-control-sm"></td>
                    <td><input type="number" step="0.01" min="0" name="rows[${i}][amount]" class="form-control form-control-sm text-end opening-amount"></td>
                    <td class="pe-3 text-center"><button type="button" class="btn btn-link btn-sm text-danger p-0 opening-remove" title="Remove"><i class="feather-x"></i></button></td>
                </tr>`);
            });
            $(document).on('click', '.opening-remove', function () { $(this).closest('tr').remove(); openingRefresh(); });
            $(document).on('input', '.opening-amount', openingRefresh);
            $('#openingReset').on('click', function () {
                if (!confirm('Go back to one "Opening Balance" bill?')) return false;
                $('#openingRows').empty();
            });
            openingRefresh();
        });
    </script>
@endpush
