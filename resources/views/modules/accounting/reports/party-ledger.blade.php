@extends('layouts.duralux')

@section('title', 'Party Ledger | SaaS ERP')
@section('page-title', 'Party Ledger')
@section('breadcrumb', 'Accounting / Reports / Party Ledger')

@section('content')
    <x-ui.card class="mb-4">
        <form method="GET" class="row g-3 align-items-end" id="partyLedgerForm">
            <div class="col-md-4">
                <label class="form-label fw-semibold fs-12 text-uppercase mb-1 text-dark">Party</label>
                <select id="partySelect" class="form-select erp-premium-select">
                    <option value="">Select Customer / Vendor / Transporter...</option>
                    <optgroup label="Customers">
                        @foreach ($customers as $customer)
                            <option value="customer:{{ $customer->id }}" @selected($partyType === 'customer' && $partyId == $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Vendors">
                        @foreach ($vendors as $vendor)
                            <option value="vendor:{{ $vendor->id }}" @selected($partyType === 'vendor' && $partyId == $vendor->id)>{{ $vendor->name }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Transporters">
                        @foreach ($transporters as $transporter)
                            <option value="vendor:{{ $transporter->vendor_id }}" @selected($partyType === 'vendor' && $partyId == $transporter->vendor_id)>{{ $transporter->name }} (via Vendor Ledger)</option>
                        @endforeach
                    </optgroup>
                </select>
                <input type="hidden" name="party_type" id="partyTypeInput" value="{{ $partyType }}">
                <input type="hidden" name="party_id" id="partyIdInput" value="{{ $partyId }}">
            </div>
            <div class="col-md-3">
                <x-ui.input label="From" name="from" type="date" :value="$from->toDateString()" />
            </div>
            <div class="col-md-3">
                <x-ui.input label="To" name="to" type="date" :value="$to->toDateString()" />
            </div>
            <div class="col-md-2">
                <x-ui.button type="submit" variant="primary" class="w-100">View</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card bodyClass="{{ $party ? 'p-0' : '' }}">
        <x-slot:title>
            Party Ledger
            @if ($party) — {{ $party->name }} @endif
            @if ($party) ({{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}) @endif
        </x-slot:title>

        @if (!$party)
            <div class="text-center py-5 text-muted">
                <i class="feather-file-text fs-1 mb-2 d-block"></i>
                Select a customer, vendor, or transporter to view their ledger.
            </div>
        @else
            <x-ui.table hoverable>
                <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                    <tr>
                        <th class="ps-4">Date</th>
                        <th>Journal #</th>
                        <th>Description</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th class="text-end pe-4">Balance</th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
                    <tr class="bg-light">
                        <td class="ps-4 fw-bold" colspan="5">Opening Balance</td>
                        <td class="text-end pe-4 fw-bold">{{ number_format($ledger['opening'], 2) }}</td>
                    </tr>
                    @forelse ($ledger['entries'] as $row)
                        @php $entry = $row['entry']; @endphp
                        <tr>
                            <td class="ps-4">{{ $entry->journal->journal_date->format('d M Y') }}</td>
                            <td>
                                <a href="{{ route('accounting.journals.show', $entry->journal) }}" class="font-monospace">
                                    {{ $entry->journal->journal_number }}
                                </a>
                            </td>
                            <td class="text-muted">{{ $entry->description ?: $entry->journal->memo ?: '—' }}</td>
                            <td class="text-end">{{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}</td>
                            <td class="text-end">{{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}</td>
                            <td class="text-end pe-4">{{ number_format($row['running_balance'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No posted activity for this party in this date range.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="fw-bold fs-13 bg-light">
                        <td class="ps-4" colspan="5">Closing Balance</td>
                        <td class="text-end pe-4">{{ number_format($ledger['closing'], 2) }}</td>
                    </tr>
                </tfoot>
            </x-ui.table>
        @endif
    </x-ui.card>
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {
            const select = document.getElementById('partySelect');
            const typeInput = document.getElementById('partyTypeInput');
            const idInput = document.getElementById('partyIdInput');

            function syncHiddenInputs() {
                const parts = (select.value || '').split(':');
                typeInput.value = parts[0] || '';
                idInput.value = parts[1] || '';
            }

            select.addEventListener('change', function () {
                syncHiddenInputs();
                document.getElementById('partyLedgerForm').submit();
            });

            syncHiddenInputs();
        });
    </script>
@endpush
