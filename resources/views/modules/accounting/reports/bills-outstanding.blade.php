@extends('layouts.duralux')

@section('title', 'Bills Outstanding | SaaS ERP')
@section('page-title', 'Bills Outstanding')
@section('breadcrumb', 'Accounting / Reports / Bills Outstanding')

@section('content')
    <x-ui.card class="mb-4">
        <x-ui.filter-toolbar :resetUrl="route('accounting.reports.bills-outstanding')" searchLabel="View">
            <x-ui.filter-field label="Show" col="col-md-3">
                <select name="ledger" class="form-select form-select-sm">
                    <option value="receivable" @selected($choice === 'receivable')>All receivables (owed to us)</option>
                    <option value="payable" @selected($choice === 'payable')>All payables (we owe)</option>
                    <optgroup label="One ledger">
                        @foreach ($ledgers as $l)
                            <option value="{{ $l->id }}" @selected($choice === (string) $l->id)>{{ $l->code }} — {{ $l->name }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </x-ui.filter-field>
            <x-ui.filter-field label="Party Type" col="col-md-2">
                <select name="party_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach (\App\Domains\Accounting\Support\PartyDirectory::LABELS as $type => $typeLabel)
                        <option value="{{ $type }}" @selected($partyType === $type)>{{ $typeLabel }}s</option>
                    @endforeach
                </select>
            </x-ui.filter-field>
            <x-ui.filter-field label="Party" col="col-md-3">
                <select name="party" class="form-select form-select-sm">
                    <option value="">All parties</option>
                    @foreach ($partyOptions as $key => $name)
                        <option value="{{ $key }}" @selected($partyFilter === $key)>{{ $name }}</option>
                    @endforeach
                </select>
            </x-ui.filter-field>
            <x-ui.filter-field label="As Of Date" col="col-md-2">
                <input type="date" name="as_of" class="form-control form-control-sm" value="{{ $asOf->toDateString() }}">
            </x-ui.filter-field>
        </x-ui.filter-toolbar>
    </x-ui.card>

    <x-ui.card :title="($ledger === 'receivable' ? 'Receivable' : 'Payable') . ' bills as at ' . $asOf->format('d M Y')" bodyClass="p-0" class="accounting-dense">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Bill / Reference</th>
                    <th>Bill Date</th>
                    <th>Due Date</th>
                    <th class="text-end">Overdue Days</th>
                    <th class="text-end pe-4">Pending Amount</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($parties as $party)
                    <tr class="bg-light">
                        <td class="ps-4 fw-bold" colspan="4">
                            <a href="{{ route('accounting.reports.party-ledger', ['party_type' => $party['party_type'], 'party_id' => $party['party_id']]) }}">{{ $party['name'] }}</a>
                            <span class="badge bg-soft-secondary text-secondary ms-1 fw-normal">{{ $party['type_label'] }}</span>
                            <a href="{{ route('accounting.bill-wise.settlement', ['party' => $party['party_type'] . ':' . $party['party_id']]) }}" class="ms-2 fs-12 fw-normal">Settle bills →</a>
                        </td>
                        <td class="text-end pe-4 fw-bold">{{ number_format($party['total'], 2) }}</td>
                    </tr>
                    @foreach ($party['documents'] as $item)
                        @php $doc = $item[$ledger === 'receivable' ? 'invoice' : 'bill']; @endphp
                        <tr>
                            <td class="ps-5 font-monospace">{{ $ledger === 'receivable' ? $doc->invoice_number : $doc->bill_number }}</td>
                            <td>{{ $item['bill_date']?->format('d M Y') ?? '—' }}</td>
                            <td>{{ $item['due_date']?->format('d M Y') ?? '—' }}</td>
                            <td class="text-end {{ $item['days_overdue'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $item['days_overdue'] }}</td>
                            <td class="text-end pe-4">{{ number_format($item['balance'], 2) }}</td>
                        </tr>
                    @endforeach
                    @if (abs($party['advance']) >= 0.01)
                        <tr>
                            <td class="ps-5 fst-italic text-muted" colspan="4">Advance</td>
                            <td class="text-end pe-4 fst-italic">{{ number_format($party['advance'], 2) }}</td>
                        </tr>
                    @endif
                    @if (abs($party['on_account']) >= 0.01)
                        <tr>
                            <td class="ps-5 fst-italic text-muted" colspan="4">On Account (not yet matched to a bill)</td>
                            <td class="text-end pe-4 fst-italic">{{ number_format($party['on_account'], 2) }}</td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No outstanding bills.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="fw-bold fs-13 bg-light">
                    <td class="ps-4" colspan="4">Total</td>
                    <td class="text-end pe-4">{{ number_format($grandTotal, 2) }}</td>
                </tr>
            </tfoot>
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
