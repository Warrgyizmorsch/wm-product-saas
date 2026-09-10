@php
    $currentSort = $filters['sort'] ?? 'created_at';
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

@section('title', 'Audit Trail | SaaS ERP')
@section('page-title', 'Audit Trail')
@section('breadcrumb', 'Accounting / Reports / Audit Trail')

@section('page-actions')
    <x-ui.filter label="Filters">
        <form method="GET">
            <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
            <x-ui.select label="Subject" name="subject_type" :selected="$filters['subject_type'] ?? ''" :options="[
                '' => 'All',
                \App\Domains\Accounting\Models\Journal::class => 'Journal',
                \App\Domains\Accounting\Models\ChartOfAccount::class => 'Chart of Account',
                \App\Domains\Accounting\Models\FiscalYear::class => 'Fiscal Year',
                \App\Domains\Accounting\Models\AccountingPeriod::class => 'Accounting Period',
                \App\Domains\Accounting\Models\TaxRate::class => 'Tax Rate',
            ]" />
            <x-ui.select label="Event" name="event_type" :selected="$filters['event_type'] ?? ''" :options="['' => 'All'] + $eventTypes->mapWithKeys(fn ($e) => [$e => $e])->all()" />
            <div class="mb-2">
                <label class="form-label fw-semibold fs-12 text-uppercase mb-0 text-dark">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
            </div>
            <div class="mb-2">
                <label class="form-label fw-semibold fs-12 text-uppercase mb-0 text-dark">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
            </div>
            <x-ui.button type="submit" variant="primary" size="sm" class="w-100">Apply</x-ui.button>
        </form>
    </x-ui.filter>
@endsection

@section('content')
    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <div class="d-flex align-items-center gap-3 p-3 border-bottom">
            <form method="GET" class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 280px; max-width: 360px;">
                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control border-0 bg-transparent p-0 fs-13"
                       placeholder="Search title or description..." style="box-shadow: none; height: 32px;">
                @foreach (['subject_type', 'event_type', 'from', 'to'] as $carry)
                    @if (!empty($filters[$carry]))
                        <input type="hidden" name="{{ $carry }}" value="{{ $filters[$carry] }}">
                    @endif
                @endforeach
            </form>
        </div>

        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">
                        <a href="{{ $sortUrl('created_at') }}" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1">
                            Date <i class="{{ $sortIcon('created_at') }} fs-12"></i>
                        </a>
                    </th>
                    <th>
                        <a href="{{ $sortUrl('event_type') }}" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1">
                            Event <i class="{{ $sortIcon('event_type') }} fs-12"></i>
                        </a>
                    </th>
                    <th>
                        <a href="{{ $sortUrl('subject_type') }}" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1">
                            Subject <i class="{{ $sortIcon('subject_type') }} fs-12"></i>
                        </a>
                    </th>
                    <th>User</th>
                    <th class="pe-4">Details</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($logs as $log)
                    <tr>
                        <td class="ps-4 text-nowrap">{{ $log->created_at?->format('d M Y H:i') }}</td>
                        <td><x-ui.badge variant="info" soft>{{ $log->event_type }}</x-ui.badge></td>
                        <td>{{ $log->title }}</td>
                        <td>{{ $log->triggeredBy?->name ?? 'System' }}</td>
                        <td class="pe-4 text-muted">
                            @if (!empty($log->metadata))
                                <code class="fs-11">{{ json_encode($log->metadata) }}</code>
                            @else
                                &mdash;
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="feather-shield fs-1 mb-2 d-block"></i>
                            No audit activity recorded yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <x-ui.pagination
            :currentPage="$logs->currentPage()"
            :totalPages="$logs->lastPage()"
            :totalResults="$logs->total()"
            :perPage="$logs->perPage()" />
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
