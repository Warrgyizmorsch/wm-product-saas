@extends('layouts.duralux')

@section('title', 'General Ledger | SaaS ERP')
@section('page-title', 'General Ledger')
@section('breadcrumb', 'Accounting / Reports / General Ledger')

@section('page-actions')
    @include('modules.accounting.reports.partials.export-buttons', ['report' => 'general-ledger'])
@endsection

@section('content')
    <x-ui.card class="mb-4">
        <x-ui.filter-toolbar :resetUrl="route('accounting.reports.general-ledger')" searchLabel="View">
            <x-ui.filter-field label="Accounting Period" col="col-md-5">
                <select name="period_id" class="form-select form-select-sm">
                    @foreach ($allPeriods as $p)
                        <option value="{{ $p->id }}" @selected($period?->id == $p->id)>{{ $p->fiscalYear?->name }} — {{ $p->name }} ({{ $p->status }})</option>
                    @endforeach
                </select>
            </x-ui.filter-field>
            <x-ui.filter-field label="Account" col="col-md-5">
                <select name="chart_of_account_id" class="form-select form-select-sm">
                    <option value="">Select Account...</option>
                    @foreach ($accounts as $a)
                        <option value="{{ $a->id }}" @selected($account?->id == $a->id)>{{ $a->code }} - {{ $a->name }}</option>
                    @endforeach
                </select>
            </x-ui.filter-field>
        </x-ui.filter-toolbar>
    </x-ui.card>

    <x-ui.card bodyClass="{{ $account ? 'p-0' : '' }}">
        <x-slot:title>
            General Ledger
            @if ($account) — {{ $account->code }} {{ $account->name }} @endif
            @if ($period) ({{ $period->name }}) @endif
        </x-slot:title>

        @if (!$account)
            <div class="text-center py-5 text-muted">
                <i class="feather-file-text fs-1 mb-2 d-block"></i>
                Select an account and period to view its ledger.
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
                        <td class="text-end pe-4 fw-bold">{{ number_format($openingBalance, 2) }}</td>
                    </tr>
                    @forelse ($rows as $row)
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
                            <td colspan="6" class="text-center py-4 text-muted">No posted activity for this account in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="fw-bold fs-13 bg-light">
                        <td class="ps-4" colspan="5">Closing Balance</td>
                        <td class="text-end pe-4">{{ number_format($closingBalance, 2) }}</td>
                    </tr>
                </tfoot>
            </x-ui.table>
        @endif
    </x-ui.card>
@endsection
