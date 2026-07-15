@php
    $currentSort = $filters['sort'] ?? 'journal_date';
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

@section('title', 'Journals | SaaS ERP')
@section('page-title', 'Journals')
@section('breadcrumb', 'Accounting / Journals')

@section('page-actions')
    <x-ui.filter label="Filters">
        <form method="GET">
            <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
            <x-ui.select label="Status" name="status" :selected="$filters['status'] ?? ''" :options="[
                '' => 'All', 'draft' => 'Draft', 'posted' => 'Posted', 'reversed' => 'Reversed',
            ]" />
            <x-ui.select label="Source" name="source" :selected="$filters['source'] ?? ''" :options="[
                '' => 'All', 'manual' => 'Manual', 'sales' => 'Sales', 'purchase' => 'Purchase',
                'inventory' => 'Inventory', 'production' => 'Production', 'payroll' => 'Payroll',
            ]" />
            <x-ui.button type="submit" variant="primary" size="sm" class="w-100">Apply</x-ui.button>
        </form>
    </x-ui.filter>
    @can('post', \App\Domains\Accounting\Models\Journal::class)
        <x-ui.button href="{{ route('accounting.journals.create') }}" variant="primary" icon="feather-plus">
            New Journal
        </x-ui.button>
    @endcan
@endsection

@section('content')
    @if (session('success'))
        <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="mb-4">
            <strong>Success!</strong> {{ session('success') }}
        </x-ui.alert>
    @endif

    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <div class="d-flex align-items-center gap-3 p-3 border-bottom">
            <form method="GET" class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 280px; max-width: 360px;">
                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control border-0 bg-transparent p-0 fs-13"
                       placeholder="Search journal number or memo..." style="box-shadow: none; height: 32px;">
                @if (!empty($filters['status']))
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                @endif
                @if (!empty($filters['source']))
                    <input type="hidden" name="source" value="{{ $filters['source'] }}">
                @endif
            </form>
        </div>

        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">
                        <a href="{{ $sortUrl('journal_number') }}" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1">
                            Journal # <i class="{{ $sortIcon('journal_number') }} fs-12"></i>
                        </a>
                    </th>
                    <th>
                        <a href="{{ $sortUrl('journal_date') }}" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1">
                            Date <i class="{{ $sortIcon('journal_date') }} fs-12"></i>
                        </a>
                    </th>
                    <th>Source</th>
                    <th>Memo</th>
                    <th class="text-end">
                        <a href="{{ $sortUrl('total_debit') }}" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1">
                            Debit <i class="{{ $sortIcon('total_debit') }} fs-12"></i>
                        </a>
                    </th>
                    <th class="text-end">
                        <a href="{{ $sortUrl('total_credit') }}" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1">
                            Credit <i class="{{ $sortIcon('total_credit') }} fs-12"></i>
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
                @forelse ($journals as $journal)
                    <tr>
                        <td class="ps-4 fw-bold font-monospace">{{ $journal->journal_number }}</td>
                        <td>{{ $journal->journal_date->format('d M Y') }}</td>
                        <td class="text-capitalize">{{ $journal->source }}</td>
                        <td class="text-muted text-truncate" style="max-width: 220px;">{{ $journal->memo ?: '—' }}</td>
                        <td class="text-end">{{ number_format($journal->total_debit, 2) }}</td>
                        <td class="text-end">{{ number_format($journal->total_credit, 2) }}</td>
                        <td>
                            @if ($journal->status === 'posted')
                                <x-ui.badge variant="success" soft>Posted</x-ui.badge>
                            @elseif ($journal->status === 'reversed')
                                <x-ui.badge variant="secondary" soft>Reversed</x-ui.badge>
                            @else
                                <x-ui.badge variant="warning" soft>Draft</x-ui.badge>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <x-ui.icon-btn href="{{ route('accounting.journals.show', $journal) }}" variant="soft-primary" icon="feather-eye" title="View" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="feather-book-open fs-1 mb-2 d-block"></i>
                            No journals found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <x-ui.pagination
            :currentPage="$journals->currentPage()"
            :totalPages="$journals->lastPage()"
            :totalResults="$journals->total()"
            :perPage="$journals->perPage()" />
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
