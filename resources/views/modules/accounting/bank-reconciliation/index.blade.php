@extends('layouts.duralux')

@section('title', 'Bank Reconciliation | SaaS ERP')
@section('page-title', 'Bank Reconciliation')
@section('breadcrumb', 'Accounting / Bank Reconciliation')

@section('page-actions')
    @can('create', \App\Domains\Accounting\Models\BankReconciliation::class)
        <x-ui.button href="{{ route('accounting.bank-reconciliation.create') }}" variant="primary" icon="feather-plus">
            New Reconciliation
        </x-ui.button>
    @endcan
@endsection

@section('content')
    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Account</th>
                    <th>Statement Date</th>
                    <th class="text-end">Opening Balance</th>
                    <th class="text-end">Closing Balance</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($reconciliations as $reconciliation)
                    <tr>
                        <td class="ps-4 fw-bold">{{ $reconciliation->chartOfAccount->code }} - {{ $reconciliation->chartOfAccount->name }}</td>
                        <td>{{ $reconciliation->statement_date->format('d M Y') }}</td>
                        <td class="text-end">{{ number_format($reconciliation->opening_balance, 2) }}</td>
                        <td class="text-end">{{ number_format($reconciliation->closing_balance, 2) }}</td>
                        <td>
                            @if ($reconciliation->isCompleted())
                                <x-ui.badge variant="success" soft>Completed</x-ui.badge>
                            @else
                                <x-ui.badge variant="warning" soft>In Progress</x-ui.badge>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <x-ui.icon-btn href="{{ route('accounting.bank-reconciliation.show', $reconciliation) }}" variant="soft-primary" icon="feather-eye" title="Open" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="feather-repeat fs-1 mb-2 d-block"></i>
                            No bank reconciliations yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <x-ui.pagination
            :currentPage="$reconciliations->currentPage()"
            :totalPages="$reconciliations->lastPage()"
            :totalResults="$reconciliations->total()"
            :perPage="$reconciliations->perPage()" />
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
