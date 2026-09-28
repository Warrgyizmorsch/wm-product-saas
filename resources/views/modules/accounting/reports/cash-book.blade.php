@extends('layouts.duralux')

@section('title', 'Cash Book | SaaS ERP')
@section('page-title', 'Cash Book')
@section('breadcrumb', 'Accounting / Reports / Cash Book')

@section('page-actions')
    @include('modules.accounting.reports.partials.export-buttons', ['report' => 'cash-book'])
@endsection

@section('content')
    <x-ui.card class="mb-4">
        <x-ui.filter-toolbar :resetUrl="route('accounting.reports.cash-book')" searchLabel="View">
            <x-ui.filter-field label="Account" col="col-md-4">
                <select name="account_id" class="form-select erp-premium-select">
                    @forelse ($cashAccounts as $cashAccount)
                        <option value="{{ $cashAccount->id }}" @selected($account && $account->id === $cashAccount->id)>
                            {{ $cashAccount->code }} — {{ $cashAccount->name }}
                        </option>
                    @empty
                        <option value="">No cash/bank accounts configured</option>
                    @endforelse
                </select>
            </x-ui.filter-field>
            <x-ui.filter-field label="From" col="col-md-3">
                <x-ui.input name="from" type="date" :value="$from->toDateString()" />
            </x-ui.filter-field>
            <x-ui.filter-field label="To" col="col-md-3">
                <x-ui.input name="to" type="date" :value="$to->toDateString()" />
            </x-ui.filter-field>
        </x-ui.filter-toolbar>
    </x-ui.card>

    <x-ui.card bodyClass="{{ $account ? 'p-0' : '' }}">
        <x-slot:title>
            Cash Book
            @if ($account) — {{ $account->code }} {{ $account->name }} @endif
            @if ($account) ({{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}) @endif
        </x-slot:title>

        @if (!$account)
            <div class="text-center py-5 text-muted">
                <i class="feather-file-text fs-1 mb-2 d-block"></i>
                No cash or bank account is configured yet.
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
                            <td class="text-muted">{{ $entry->description ?: $entry->journal->voucherDetail?->party_name ?: $entry->journal->memo ?: '—' }}</td>
                            <td class="text-end">{{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}</td>
                            <td class="text-end">{{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}</td>
                            <td class="text-end pe-4">{{ number_format($row['running_balance'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No cash movements in this date range.</td>
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
            $('select[name="account_id"]').on('change', function () {
                $(this).closest('form').submit();
            });
        });
    </script>
@endpush
