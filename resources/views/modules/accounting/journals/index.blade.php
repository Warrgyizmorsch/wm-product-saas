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
            <x-ui.select label="Posted by" name="posted_by" :selected="$filters['posted_by'] ?? ''" :options="['' => 'Anyone', 'system' => 'System (auto-posted)'] + $posters->pluck('name', 'id')->all()" />
            <x-ui.input label="From" name="from" type="date" :value="$filters['from'] ?? ''" />
            <x-ui.input label="To" name="to" type="date" :value="$filters['to'] ?? ''" />
            <div class="d-flex gap-2">
                <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">Apply</x-ui.button>
                <x-ui.button href="{{ route('accounting.journals.index') }}" variant="light" size="sm" class="border flex-grow-1">Reset</x-ui.button>
            </div>
        </form>
    </x-ui.filter>
    @if ($canCreate)
        <x-ui.button type="button" variant="primary" icon="feather-plus" data-bs-toggle="offcanvas" data-bs-target="#journalCreateDrawer">
            New Journal
        </x-ui.button>
    @endif
@endsection

@section('content')

    <div class="d-flex flex-wrap gap-3 mb-4">
        <x-ui.stat-pill icon="feather-layers" :value="$summary['total']" label="Total Journals" color="primary" />
        <x-ui.stat-pill icon="feather-check-circle" :value="$summary['posted']" label="Posted" color="success" />
        <x-ui.stat-pill icon="feather-edit-3" :value="$summary['draft']" label="Draft" color="warning" />
        <x-ui.stat-pill icon="feather-rotate-ccw" :value="$summary['reversed']" label="Reversed" color="secondary" />
    </div>

    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <div class="d-flex align-items-center gap-3 p-3 border-bottom">
            <form method="GET" class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 280px; max-width: 360px;">
                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control border-0 bg-transparent p-0 fs-13"
                       placeholder="Search journal number or memo..." style="box-shadow: none; height: 32px;">
                @if (!empty($filters['search']))
                    <a href="{{ route('accounting.journals.index', collect($filters)->except('search')->filter()->all()) }}" class="text-muted ms-2" title="Clear search">
                        <i class="feather-x" style="font-size: 14px;"></i>
                    </a>
                @endif
                @foreach (['status', 'source', 'posted_by', 'from', 'to'] as $kept)
                    @if (!empty($filters[$kept]))
                        <input type="hidden" name="{{ $kept }}" value="{{ $filters[$kept] }}">
                    @endif
                @endforeach
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
                    <th>Posted by</th>
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
                        <td>{{ $journal->postedBy?->name ?? 'System' }}</td>
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
                            <x-ui.row-actions :view-url="route('accounting.journals.show', $journal)" class="justify-content-end" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
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

    @if ($canCreate)
        <x-ui.drawer id="journalCreateDrawer" title="New Journal" scroll style="--bs-offcanvas-width: min(920px, 94vw);">
            @include('modules.accounting.journals._form', ['embedded' => true])
        </x-ui.drawer>

        @if ($errors->any())
            @push('scripts')
                <script>
                    $(function () {
                        new bootstrap.Offcanvas(document.getElementById('journalCreateDrawer')).show();
                    });
                </script>
            @endpush
        @endif
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
