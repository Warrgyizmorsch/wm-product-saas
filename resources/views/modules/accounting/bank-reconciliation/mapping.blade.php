@php
    $account = $reconciliation->chartOfAccount;
    $letters = fn (int $i) => $i < 26 ? chr(65 + $i) : chr(64 + intdiv($i, 26)) . chr(65 + $i % 26);
    $columnOptions = ['' => '— not in this file —'] + collect(range(0, max(0, $columnCount - 1)))
        ->mapWithKeys(fn ($i) => [$i => 'Column ' . $letters($i)])->all();
@endphp

@extends('layouts.duralux')

@section('title', 'Map Statement Columns | SaaS ERP')
@section('page-title', 'Map Statement Columns')
@section('breadcrumb', 'Accounting / Bank Reconciliation / ' . $account->name . ' / Map columns')

@section('page-actions')
    <x-ui.button href="{{ route('accounting.bank-reconciliation.show', $reconciliation) }}" variant="light" size="sm" class="border">Back</x-ui.button>
@endsection

@section('content')
    @if (session('error'))
        <x-ui.alert variant="warning" icon="feather-info" dismissible class="mb-3">{{ session('error') }}</x-ui.alert>
    @endif

    <form action="{{ route('accounting.bank-reconciliation.apply-mapping', [$reconciliation, $upload->id]) }}" method="POST" id="brMappingForm">
        @csrf

        <x-ui.card class="mb-3">
            <div class="fs-13 text-dark mb-3">
                <div class="fw-semibold">{{ $upload->original_filename }}</div>
                <div class="text-muted fs-12">
                    1. Click the row that holds the column headings. 2. Say which column holds what.
                    Only the date and the amount column(s) are required. This layout is saved for <strong>{{ $account->name }}</strong>, so the next statement in this format imports straight away.
                </div>
            </div>

            <input type="hidden" name="header_row" id="brHeaderRow" value="{{ old('header_row') }}">

            <div class="row g-2">
                @foreach ($fields as $field => $label)
                    <div class="col-md-3 col-6">
                        <label class="form-label fs-11 text-uppercase fw-semibold mb-1">{{ $label }}</label>
                        <select name="columns[{{ $field }}]" class="form-select form-select-sm br-map-select" data-field="{{ $field }}">
                            @foreach ($columnOptions as $value => $text)
                                <option value="{{ $value }}" @selected((string) old("columns.$field") === (string) $value && $value !== '')>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="fs-12" id="brMappingHint">Click the header row in the preview below.</div>
                <x-ui.button type="submit" variant="primary" size="sm" icon="feather-check" id="brMappingSubmit">Import with this mapping</x-ui.button>
            </div>
        </x-ui.card>
    </form>

    <x-ui.card bodyClass="p-0">
        <div class="table-responsive" style="max-height: 520px;">
            <table class="table table-sm table-bordered mb-0 fs-12" id="brPreview">
                <thead class="table-light" style="position: sticky; top: 0;">
                    <tr>
                        <th style="width: 44px;">Row</th>
                        @for ($i = 0; $i < $columnCount; $i++)
                            <th class="text-center">{{ $letters($i) }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach ($preview as $index => $row)
                        <tr data-row="{{ $index }}" style="cursor: pointer;">
                            <td class="text-muted">{{ $index + 1 }}</td>
                            @for ($i = 0; $i < $columnCount; $i++)
                                <td class="text-nowrap">{{ $row[$i] ?? '' }}</td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>
@endsection

@push('scripts')
<script>
(function () {
    const preview = @json($preview);
    const aliases = {
        date: /^(txn |tran |transaction |posting |value )?(date|dt)$/,
        description: /(narration|description|particulars|remarks|details)/,
        reference: /(chq|cheque|ref|utr|instrument)/,
        withdrawal: /(withdrawal|debit|^dr$|paid out)/,
        deposit: /(deposit|credit|^cr$|paid in)/,
        amount: /^(txn |transaction )?amount/,
        drcr: /^(dr ?\/? ?cr|cr ?\/? ?dr|type)$/,
        balance: /balance/,
    };
    const norm = (t) => String(t || '').toLowerCase().replace(/\(.*?\)/g, ' ').replace(/[^a-z0-9\/]+/g, ' ').trim();

    const pickHeader = (index) => {
        document.getElementById('brHeaderRow').value = index;
        document.querySelectorAll('#brPreview tbody tr').forEach((tr) => {
            const n = Number(tr.dataset.row);
            tr.classList.toggle('table-primary', n === index);
            tr.classList.toggle('text-muted', n < index);
        });
        // Suggest columns from the chosen header's text; the user can change any.
        const cells = preview[index] || [];
        Object.entries(aliases).forEach(([field, re]) => {
            const select = document.querySelector(`.br-map-select[data-field="${field}"]`);
            if (select.value !== '') return;
            const col = cells.findIndex((c) => re.test(norm(c)));
            if (col >= 0) select.value = String(col);
        });
        validate();
    };

    const validate = () => {
        const v = (f) => document.querySelector(`.br-map-select[data-field="${f}"]`).value !== '';
        const header = document.getElementById('brHeaderRow').value !== '';
        const ok = header && v('date') && (v('amount') || v('withdrawal') || v('deposit'));
        document.getElementById('brMappingSubmit').disabled = !ok;
        document.getElementById('brMappingHint').innerHTML = !header
            ? 'Click the header row in the preview below.'
            : (ok ? '<span class="text-success">Ready — header on row ' + (Number(document.getElementById('brHeaderRow').value) + 1) + '.</span>'
                  : '<span class="text-danger">Choose the Date column and an Amount (or Withdrawal/Deposit) column.</span>');
    };

    document.querySelectorAll('#brPreview tbody tr').forEach((tr) => tr.addEventListener('click', () => pickHeader(Number(tr.dataset.row))));
    document.querySelectorAll('.br-map-select').forEach((s) => s.addEventListener('change', validate));

    const initial = document.getElementById('brHeaderRow').value;
    if (initial !== '') pickHeader(Number(initial)); else validate();
})();
</script>
@endpush
