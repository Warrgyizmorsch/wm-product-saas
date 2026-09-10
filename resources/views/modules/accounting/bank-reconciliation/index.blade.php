@php
    $currentSort = $filters['sort'] ?? 'statement_date';
    $currentDirection = $filters['direction'] ?? 'desc';

    $sortUrl = function (string $column) use ($currentSort, $currentDirection) {
        $nextDirection = ($currentSort === $column && $currentDirection === 'asc') ? 'desc' : 'asc';

        return request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDirection]);
    };

    $sortIcon = function (string $column) use ($currentSort, $currentDirection) {
        if ($currentSort !== $column) {
            return 'feather-chevrons-up';
        }

        return $currentDirection === 'asc' ? 'feather-chevron-up' : 'feather-chevron-down';
    };
@endphp

@extends('layouts.duralux')

@section('title', 'Bank Reconciliation | SaaS ERP')
@section('page-title', 'Bank Reconciliation')
@section('breadcrumb', 'Accounting / Bank Reconciliation')

@section('page-actions')
    <x-ui.filter label="Filters">
        <form method="GET">
            <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
            <x-ui.select label="Status" name="status" :selected="$filters['status'] ?? ''" :options="[
                '' => 'All', 'in_progress' => 'In Progress', 'completed' => 'Completed',
            ]" />
            <x-ui.button type="submit" variant="primary" size="sm" class="w-100">Apply</x-ui.button>
        </form>
    </x-ui.filter>
    @can('create', \App\Domains\Accounting\Models\BankReconciliation::class)
        <x-ui.button href="{{ route('accounting.bank-reconciliation.create') }}" variant="primary" icon="feather-plus">
            New Reconciliation
        </x-ui.button>
    @endcan
@endsection

@section('content')
    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <div class="d-flex align-items-center gap-3 p-3 border-bottom">
            <form method="GET" class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 280px; max-width: 360px;">
                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control border-0 bg-transparent p-0 fs-13"
                       placeholder="Search account code or name..." style="box-shadow: none; height: 32px;">
                @if (!empty($filters['status']))
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                @endif
            </form>
        </div>

        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Account</th>
                    <th>
                        <a href="{{ $sortUrl('statement_date') }}" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1">
                            Statement Date <i class="{{ $sortIcon('statement_date') }} fs-12"></i>
                        </a>
                    </th>
                    <th class="text-end">
                        <a href="{{ $sortUrl('opening_balance') }}" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1">
                            Opening Balance <i class="{{ $sortIcon('opening_balance') }} fs-12"></i>
                        </a>
                    </th>
                    <th class="text-end">
                        <a href="{{ $sortUrl('closing_balance') }}" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1">
                            Closing Balance <i class="{{ $sortIcon('closing_balance') }} fs-12"></i>
                        </a>
                    </th>
                    <th>
                        <a href="{{ $sortUrl('status') }}" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1">
                            Status <i class="{{ $sortIcon('status') }} fs-12"></i>
                        </a>
                    </th>
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
