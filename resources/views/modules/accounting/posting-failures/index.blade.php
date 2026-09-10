@php
    $currentSort = $filters['sort'] ?? 'occurred_at';
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

@section('title', 'Posting Failures | SaaS ERP')
@section('page-title', 'Accounting Posting Failures')
@section('breadcrumb', 'Accounting / Posting Failures')

@section('page-actions')
    <x-ui.filter label="Filters">
        <form method="GET">
            <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
            <x-ui.select label="Source" name="model_class" :selected="$filters['model_class'] ?? ''" :options="['' => 'All'] + $modelClasses->mapWithKeys(fn ($c) => [$c => class_basename($c)])->all()" />
            <x-ui.button type="submit" variant="primary" size="sm" class="w-100">Apply</x-ui.button>
        </form>
    </x-ui.filter>
@endsection

@section('content')
    <x-ui.card class="mb-4">
        <p class="fs-13 text-muted mb-0">
            Transactions in this list completed successfully in their own module (Sales, Purchase, HRMS, Inventory)
            but their automatic accounting journal could not be posted — usually because the accounting period was
            closed/locked, or a required Chart of Accounts entry was missing. The source transaction was never
            blocked, so these need to be resolved manually: fix the underlying cause (reopen the period, add the
            missing account) then click <strong>Retry</strong>, or <strong>Dismiss</strong> if you already corrected
            the books with a manual journal.
        </p>
    </x-ui.card>

    <x-ui.card title="Unresolved Failures" bodyClass="p-0" class="accounting-dense">
        <div class="d-flex align-items-center gap-3 p-3 border-bottom">
            <form method="GET" class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 280px; max-width: 360px;">
                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control border-0 bg-transparent p-0 fs-13"
                       placeholder="Search reason or source..." style="box-shadow: none; height: 32px;">
                @if (!empty($filters['model_class']))
                    <input type="hidden" name="model_class" value="{{ $filters['model_class'] }}">
                @endif
            </form>
        </div>

        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">
                        <a href="{{ $sortUrl('occurred_at') }}" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1">
                            Occurred <i class="{{ $sortIcon('occurred_at') }} fs-12"></i>
                        </a>
                    </th>
                    <th>
                        <a href="{{ $sortUrl('model_class') }}" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1">
                            Source <i class="{{ $sortIcon('model_class') }} fs-12"></i>
                        </a>
                    </th>
                    <th>Reason</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($failures as $failure)
                    @php $model = $failure->model(); @endphp
                    <tr>
                        <td class="ps-4 text-muted">{{ $failure->occurred_at->format('d M Y H:i') }}</td>
                        <td>
                            <span class="fw-semibold">{{ class_basename($failure->model_class) }} #{{ $failure->model_id }}</span>
                            @if ($model === null)
                                <span class="d-block fs-11 text-danger">Original record no longer exists</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $failure->message }}</td>
                        <td class="text-end pe-4">
                            <form method="POST" action="{{ route('accounting.posting-failures.retry', $failure) }}" class="d-inline">
                                @csrf
                                <x-ui.button type="submit" variant="primary" size="sm" icon="feather-refresh-cw" :disabled="$model === null">
                                    Retry
                                </x-ui.button>
                            </form>
                            <form method="POST" action="{{ route('accounting.posting-failures.dismiss', $failure) }}" class="d-inline"
                                  onsubmit="return confirm('Dismiss without posting a journal? Only do this if you already corrected the books manually.');">
                                @csrf
                                <x-ui.button type="submit" variant="light" size="sm" class="border">
                                    Dismiss
                                </x-ui.button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 text-muted">No unresolved posting failures. Books are in sync with the ERP transactions.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <x-ui.pagination
            :currentPage="$failures->currentPage()"
            :totalPages="$failures->lastPage()"
            :totalResults="$failures->total()"
            :perPage="$failures->perPage()" />
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
