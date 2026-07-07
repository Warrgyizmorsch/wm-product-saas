@extends('layouts.duralux')

@section('title', 'Schedule Calendar | SaaS ERP')
@section('page-title', 'Scheduling Calendar')
@section('breadcrumb', 'Calendar')

@section('page-actions')
    <a href="{{ route('production.schedules.index') }}" class="btn btn-secondary me-2">
        <i class="feather-list me-2"></i>List View
    </a>
    <a href="{{ route('production.schedules.work-center-view') }}" class="btn btn-light me-2">
        <i class="feather-grid me-2"></i>Work Center View
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        {{-- Calendar Navigation --}}
        @php
            $prevStart = match($view) {
                'day'   => $startDate->copy()->subDay(),
                'month' => $startDate->copy()->subMonth(),
                default => $startDate->copy()->subWeek(),
            };
            $nextStart = match($view) {
                'day'   => $startDate->copy()->addDay(),
                'month' => $startDate->copy()->addMonth(),
                default => $startDate->copy()->addWeek(),
            };
        @endphp

        <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <a href="{{ request()->fullUrlWithQuery(['start' => $prevStart->toDateString()]) }}" class="btn btn-sm btn-light">
                    <i class="feather-chevron-left"></i>
                </a>
                <h5 class="fw-bold text-dark mb-0">
                    @if($view === 'day')
                        {{ $startDate->format('l, d F Y') }}
                    @elseif($view === 'month')
                        {{ $startDate->format('F Y') }}
                    @else
                        {{ $startDate->format('d M') }} – {{ $endDate->format('d M Y') }}
                    @endif
                </h5>
                <a href="{{ request()->fullUrlWithQuery(['start' => $nextStart->toDateString()]) }}" class="btn btn-sm btn-light">
                    <i class="feather-chevron-right"></i>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['start' => now()->toDateString()]) }}" class="btn btn-sm btn-outline-primary ms-2">Today</a>
            </div>

            {{-- View Tabs --}}
            <div class="btn-group" role="group">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'day']) }}" class="btn btn-sm {{ $view === 'day' ? 'btn-primary' : 'btn-outline-secondary' }}">Day</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'week']) }}" class="btn btn-sm {{ $view === 'week' ? 'btn-primary' : 'btn-outline-secondary' }}">Week</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'month']) }}" class="btn btn-sm {{ $view === 'month' ? 'btn-primary' : 'btn-outline-secondary' }}">Month</a>
                <span class="btn btn-sm btn-outline-secondary disabled" title="Coming Soon — Gantt">Gantt</span>
            </div>
        </div>

        {{-- Operations List (Table-based calendar, Gantt-ready data structure) --}}
        @if($operations->count() > 0)
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 12%">Planned Start</th>
                            <th style="width: 12%">Planned Finish</th>
                            <th style="width: 15%">Schedule #</th>
                            <th style="width: 20%">Order / Product</th>
                            <th style="width: 18%">Operation</th>
                            <th style="width: 12%">Work Center</th>
                            <th style="width: 10%">Machine</th>
                            <th style="width: 10%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($operations->sortBy('planned_start') as $op)
                            <tr>
                                <td class="fs-12 fw-semibold text-dark">{{ $op->planned_start->format('d/m H:i') }}</td>
                                <td class="fs-12 text-muted">{{ $op->planned_finish->format('d/m H:i') }}</td>
                                <td>
                                    <a href="{{ route('production.schedules.show', $op->production_schedule_id) }}" class="fw-bold text-primary">
                                        {{ $op->schedule->schedule_number ?? '—' }}
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-semibold text-dark fs-12">{{ $op->order->product->name ?? '—' }}</span>
                                        <small class="text-muted">{{ $op->order->order_number ?? '' }}</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark fs-12">{{ $op->orderOperation->name ?? '—' }}</span>
                                    <br><small class="text-muted font-monospace">{{ $op->orderOperation->operation_number ?? '' }}</small>
                                </td>
                                <td class="fs-12">{{ $op->workCenter->name ?? '—' }}</td>
                                <td class="fs-12 text-muted">{{ $op->machine->name ?? '—' }}</td>
                                <td>
                                    @if($op->status === 'running')
                                        <span class="badge bg-soft-warning text-warning">Running</span>
                                    @elseif($op->status === 'ready')
                                        <span class="badge bg-soft-info text-info">Ready</span>
                                    @elseif($op->status === 'completed')
                                        <span class="badge bg-soft-success text-success">Done</span>
                                    @elseif($op->status === 'paused')
                                        <span class="badge bg-soft-warning text-warning">Paused</span>
                                    @else
                                        <span class="erp-badge-draft">{{ ucfirst($op->status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        @else
            <div class="text-center py-5 text-muted">
                <i class="feather-calendar fs-36 mb-3 d-block"></i>
                <p class="fs-14">No operations scheduled for this period.</p>
                <a href="{{ route('production.schedules.create') }}" class="btn btn-primary btn-sm">
                    <i class="feather-plus me-2"></i>Create Schedule
                </a>
            </div>
        @endif

        {{-- Gantt Future Notice --}}
        <div class="alert alert-info border-info bg-soft-info d-flex align-items-center p-3 rounded mt-4">
            <i class="feather-info me-3 text-info"></i>
            <span class="fs-12 text-info-800">
                <strong>Gantt Chart Coming Soon:</strong> The calendar architecture is Gantt-ready. Drag-and-drop scheduling and visual Gantt charts will be available in a future release.
            </span>
        </div>
    </div>
@endsection
