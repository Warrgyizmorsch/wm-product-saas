@extends('layouts.duralux')

@section('title', 'Depreciation | SaaS ERP')
@section('page-title', 'Depreciation')
@section('breadcrumb', 'Accounting / Fixed Assets / Depreciation')

@section('page-actions')
    <x-ui.button href="{{ route('accounting.fixed-assets.index') }}" variant="light" icon="feather-list" class="border">
        Asset Register
    </x-ui.button>
@endsection

@section('content')
    <x-ui.card class="mb-4">
        <form method="GET" id="periodFilterForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-12 text-uppercase text-muted mb-1">Year</label>
                    <input type="number" name="year" value="{{ $year }}" class="form-control form-control-sm" min="2000" max="2100">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-12 text-uppercase text-muted mb-1">Month</label>
                    <select name="month" class="form-select form-select-sm">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected($m === $month)>{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-12 text-uppercase text-muted mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach (['draft' => 'Draft', 'reviewed' => 'Reviewed', 'approved' => 'Approved', 'posted' => 'Posted'] as $val => $label)
                            <option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-light border">Apply</button>
                </div>
            </div>
        </form>

        @if ($canGenerate)
            <form method="POST" action="{{ route('accounting.fixed-assets.depreciation.generate') }}" id="generateForm" class="mt-3">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <button type="button" class="btn btn-sm btn-primary" onclick="confirmAction({title: 'Generate Depreciation', message: 'Generate draft depreciation schedules for all eligible active assets for {{ $month }}/{{ $year }}?', variant: 'primary', confirmText: 'Generate'}, function() { document.getElementById('generateForm').submit(); })">
                    <i class="feather-play me-1"></i> Generate for {{ $month }}/{{ $year }}
                </button>
            </form>
        @endif
    </x-ui.card>

    <x-ui.card bodyClass="p-0">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Asset</th>
                    <th class="text-end">Opening</th>
                    <th class="text-end">Depreciation</th>
                    <th class="text-end">Closing</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($schedules as $schedule)
                    <tr>
                        <td class="ps-4">
                            <a href="{{ route('accounting.fixed-assets.show', $schedule->asset_id) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                {{ $schedule->asset->asset_code }}
                            </a>
                            <div class="text-muted fs-11">{{ $schedule->asset->name }}</div>
                        </td>
                        <td class="text-end">{{ number_format($schedule->opening_book_value, 2) }}</td>
                        <td class="text-end">{{ number_format($schedule->depreciation_amount, 2) }}</td>
                        <td class="text-end">{{ number_format($schedule->closing_book_value, 2) }}</td>
                        <td><x-ui.status-badge :status="$schedule->status" /></td>
                        <td class="text-end pe-4" style="white-space: nowrap;">
                            @if ($schedule->status === 'draft' && $canGenerate)
                                <form action="{{ route('accounting.fixed-assets.depreciation.review', $schedule) }}" method="POST" class="d-inline-flex" id="reviewForm_{{ $schedule->id }}">
                                    @csrf
                                    <button type="button" class="btn btn-xs btn-soft-info border-0" title="Mark Reviewed" onclick="confirmAction({title: 'Review Schedule', message: 'Mark this depreciation schedule as reviewed?', variant: 'info', confirmText: 'Review'}, function() { document.getElementById('reviewForm_{{ $schedule->id }}').submit(); })">
                                        <i class="feather-check"></i> Review
                                    </button>
                                </form>
                            @elseif ($schedule->status === 'reviewed' && $canGenerate)
                                <form action="{{ route('accounting.fixed-assets.depreciation.approve', $schedule) }}" method="POST" class="d-inline-flex" id="approveForm_{{ $schedule->id }}">
                                    @csrf
                                    <button type="button" class="btn btn-xs btn-soft-success border-0" title="Approve" onclick="confirmAction({title: 'Approve Schedule', message: 'Approve this depreciation schedule for posting?', variant: 'success', confirmText: 'Approve'}, function() { document.getElementById('approveForm_{{ $schedule->id }}').submit(); })">
                                        <i class="feather-check-circle"></i> Approve
                                    </button>
                                </form>
                            @elseif ($schedule->status === 'approved' && $canPost)
                                <form action="{{ route('accounting.fixed-assets.depreciation.post', $schedule) }}" method="POST" class="d-inline-flex" id="postForm_{{ $schedule->id }}">
                                    @csrf
                                    <button type="button" class="btn btn-xs btn-primary" title="Post to GL" onclick="confirmAction({title: 'Post Depreciation', message: 'Post this depreciation to the general ledger? This cannot be undone.', variant: 'primary', confirmText: 'Post'}, function() { document.getElementById('postForm_{{ $schedule->id }}').submit(); })">
                                        <i class="feather-upload"></i> Post
                                    </button>
                                </form>
                            @else
                                <span class="text-muted fs-11">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="feather-calendar fs-1 mb-2 d-block"></i>
                            No depreciation schedules for {{ $month }}/{{ $year }}@if ($status) with status "{{ $status }}" @endif.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <x-ui.pagination
            :currentPage="$schedules->currentPage()"
            :totalPages="$schedules->lastPage()"
            :totalResults="$schedules->total()"
            :perPage="$schedules->perPage()" />
    </x-ui.card>
@endsection
