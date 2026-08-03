@extends('layouts.duralux')

@section('title', __('production.scheduling_calendar') . ' | SaaS ERP')
@section('page-title', __('production.scheduling_calendar'))
@section('breadcrumb', __('production.calendar_view'))

@section('page-actions')
    <a href="{{ route('production.schedules.index') }}" class="btn btn-secondary me-2">
        <i class="feather-list me-2"></i>{{ __('production.plans_list') ?? 'List View' }}
    </a>
    <a href="{{ route('production.schedules.work-center-view') }}" class="btn btn-light me-2">
        <i class="feather-grid me-2"></i>{{ __('production.work_center_view') }}
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4">

        {{-- Calendar Navigation & Layout Toggle --}}
        @php
            $layout = request('layout', 'gantt');

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

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
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
                <a href="{{ request()->fullUrlWithQuery(['start' => now()->toDateString()]) }}" class="btn btn-sm btn-outline-primary ms-2">{{ __('production.today') }}</a>
            </div>

            <div class="d-flex align-items-center gap-3">
                {{-- Layout Selection (Gantt vs Table) --}}
                <div class="btn-group" role="group">
                    <a href="{{ request()->fullUrlWithQuery(['layout' => 'gantt']) }}" class="btn btn-sm {{ $layout === 'gantt' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        <i class="feather-bar-chart-2 me-1"></i> {{ __('production.gantt_view') }}
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['layout' => 'list']) }}" class="btn btn-sm {{ $layout === 'list' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        <i class="feather-list me-1"></i> {{ __('production.table_view') }}
                    </a>
                </div>

                {{-- Time Horizon selection --}}
                <div class="btn-group" role="group">
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'day']) }}" class="btn btn-sm {{ $view === 'day' ? 'btn-primary' : 'btn-outline-secondary' }}">{{ __('production.day') }}</a>
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'week']) }}" class="btn btn-sm {{ $view === 'week' ? 'btn-primary' : 'btn-outline-secondary' }}">{{ __('production.week') }}</a>
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'month']) }}" class="btn btn-sm {{ $view === 'month' ? 'btn-primary' : 'btn-outline-secondary' }}">{{ __('production.month') }}</a>
                </div>
            </div>
        </div>

        @if($layout === 'gantt')
            @php
                // Generate timeline columns
                $columns = [];
                if ($view === 'day') {
                    for ($i = 0; $i < 24; $i++) {
                        $columns[] = [
                            'label' => sprintf('%02d:00', $i),
                            'start' => $startDate->copy()->startOfDay()->addHours($i),
                            'end' => $startDate->copy()->startOfDay()->addHours($i + 1),
                        ];
                    }
                } elseif ($view === 'month') {
                    $daysInMonth = $startDate->daysInMonth;
                    for ($i = 0; $i < $daysInMonth; $i++) {
                        $day = $startDate->copy()->startOfMonth()->addDays($i);
                        $columns[] = [
                            'label' => $day->format('d'),
                            'sublabel' => $day->format('D'),
                            'start' => $day->copy()->startOfDay(),
                            'end' => $day->copy()->endOfDay(),
                        ];
                    }
                } else { // week (default)
                    for ($i = 0; $i < 7; $i++) {
                        $day = $startDate->copy()->startOfWeek()->addDays($i);
                        $columns[] = [
                            'label' => $day->format('D'),
                            'sublabel' => $day->format('d M'),
                            'start' => $day->copy()->startOfDay(),
                            'end' => $day->copy()->endOfDay(),
                        ];
                    }
                }
                
                $timelineStart = $columns[0]['start'];
                $timelineEnd = end($columns)['end'];
                $totalSeconds = $timelineStart->diffInSeconds($timelineEnd);
                $totalSeconds = $totalSeconds > 0 ? $totalSeconds : 1;
                
                $groupedOperations = $operations->groupBy('work_center_id');
                $allWorkCenters = \App\Domains\Production\Models\WorkCenter::active()->get();
            @endphp

            @if($operations->count() > 0)
                <div class="gantt-chart-container border rounded overflow-hidden shadow-sm mb-4" style="overflow-x: auto;">
                    <div style="min-width: 1050px; background-color: #fafafa;">
                        <!-- Header Row -->
                        <div class="gantt-header-row d-flex border-bottom bg-light">
                            <div class="gantt-label-col border-end p-3 d-flex align-items-center" style="width: 250px; flex-shrink: 0; background-color: #f8f9fa; z-index: 10;">
                                <span class="fw-bold text-uppercase text-muted fs-11">{{ __('production.work_centers') }}</span>
                            </div>
                            <div class="gantt-timeline-col flex-grow-1 d-flex">
                                @foreach($columns as $col)
                                    <div class="flex-grow-1 text-center py-2 border-end d-flex flex-column justify-content-center" style="min-width: 40px; background-color: #f8f9fa;">
                                        <span class="fw-bold fs-11 text-dark">{{ $col['label'] }}</span>
                                        @if(isset($col['sublabel']))
                                            <span class="text-muted fs-9" style="font-size: 9px; line-height: 1;">{{ $col['sublabel'] }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Rows -->
                        @foreach($workCentersData as $wc)
                            <!-- Work Center Header Line -->
                            <div class="gantt-wc-group border-bottom bg-light px-3 py-2 fw-bold text-dark d-flex justify-content-between align-items-center" style="font-size: 13px;">
                                <span>
                                    <i class="feather-settings text-primary me-1"></i> {{ $wc['name'] }}
                                    @if(!empty($wc['code']))
                                        <small class="text-muted font-monospace fs-10">({{ $wc['code'] }})</small>
                                    @endif
                                    <span class="badge bg-soft-secondary text-secondary ms-2 text-capitalize fs-9" style="font-size: 9px;">{{ str_replace('_', ' ', $wc['classification']) }}</span>
                                </span>
                                @if($wc['conflicts_count'] > 0)
                                    <span class="badge bg-soft-danger text-danger fs-9" style="font-size: 9px;"><i class="feather-alert-triangle me-1"></i>{{ $wc['conflicts_count'] }} Conflicts</span>
                                @endif
                            </div>

                            @foreach($wc['machines'] as $machine)
                                @php
                                    $laneCount = max(1, count($machine['lanes']));
                                    $rowHeight = 20 + ($laneCount * 46);
                                @endphp
                                <div class="gantt-row d-flex align-items-stretch border-bottom bg-white" style="min-height: {{ $rowHeight }}px;">
                                    <div class="gantt-label-col border-end d-flex flex-column justify-content-center px-3 py-2" style="width: 250px; flex-shrink: 0; background-color: #fafafa; z-index: 10;">
                                        <span class="fw-semibold text-dark fs-12">
                                            {{ $machine['name'] }}
                                            @if(!empty($machine['code']))
                                                <small class="text-muted font-monospace d-block fs-10">{{ $machine['code'] }}</small>
                                            @endif
                                        </span>
                                        @if(!empty($machine['badge']))
                                            <span class="badge {{ !$machine['is_active'] ? 'bg-soft-danger text-danger' : 'bg-soft-warning text-warning' }} mt-1 align-self-start fs-9" style="font-size: 9px;">{{ $machine['badge'] }}</span>
                                        @endif
                                    </div>
                                    <div class="gantt-timeline-col position-relative flex-grow-1 py-2" style="min-height: {{ $rowHeight }}px;">
                                        <!-- Grid lines background -->
                                        <div class="position-absolute w-100 h-100 d-flex top-0 left-0" style="pointer-events: none; z-index: 1;">
                                            @foreach($columns as $col)
                                                <div class="flex-grow-1 border-end border-light" style="opacity: 0.7;"></div>
                                            @endforeach
                                        </div>

                                        <!-- Task bars -->
                                        @foreach($machine['lanes'] as $lane)
                                            @foreach($lane['operations'] as $op)
                                                @php
                                                    $barBg = 'bg-primary text-white';
                                                    if ($op['status'] === 'running') $barBg = 'bg-warning text-dark';
                                                    elseif ($op['status'] === 'completed') $barBg = 'bg-success text-white';
                                                    elseif ($op['status'] === 'paused') $barBg = 'bg-soft-danger text-danger border border-danger';
                                                    elseif ($op['status'] === 'ready') $barBg = 'bg-info text-white';

                                                    $hasConflict = $op['conflict_data']['has_conflict'];
                                                    $highestSeverity = $op['conflict_data']['highest_severity'];
                                                    $borderClass = '';
                                                    if ($hasConflict) {
                                                        $borderClass = $highestSeverity === 'danger' ? 'border border-2 border-danger' : 'border border-2 border-warning';
                                                    }

                                                    $tooltipHtml = "<strong>" . e($op['name']) . " (" . e($op['operation_number']) . ")</strong><br>"
                                                                 . "Order: " . e($op['order_number']) . "<br>"
                                                                 . "Product: " . e($op['product_name']) . "<br>"
                                                                 . "WC: " . e($wc['name']) . "<br>"
                                                                 . "Start: " . e($op['planned_start'] ? $op['planned_start']->format('d/m H:i') : 'N/A') . "<br>"
                                                                 . "End: " . e($op['planned_finish'] ? $op['planned_finish']->format('d/m H:i') : 'N/A') . "<br>"
                                                                 . "Status: <span class='badge bg-light text-dark'>" . ucfirst($op['status']) . "</span>";

                                                    if ($hasConflict) {
                                                        $tooltipHtml .= "<br><strong class='text-danger mt-1 d-block'>Warnings:</strong>";
                                                        foreach ($op['conflict_data']['conflicts'] as $c) {
                                                            $tooltipHtml .= "• " . e($c['message']) . "<br>";
                                                        }
                                                    }
                                                @endphp
                                                @if($op['width_percent'] > 0)
                                                    <div class="position-absolute rounded shadow-sm text-truncate px-2 py-1 fs-11 fw-bold {{ $barBg }} {{ $borderClass }} d-flex align-items-center justify-content-between cursor-pointer"
                                                         style="left: {{ $op['left_percent'] }}%; width: {{ $op['width_percent'] }}%; top: {{ 10 + ($op['visual_lane'] * 46) }}px; height: 36px; z-index: 5;"
                                                         data-bs-toggle="tooltip"
                                                         data-bs-html="true"
                                                         title="{!! $tooltipHtml !!}">
                                                        <span class="text-truncate flex-grow-1" style="max-width: calc(100% - 20px);">
                                                            {{ $op['order_number'] }} – {{ $op['name'] }}
                                                        </span>
                                                        @if($hasConflict)
                                                            <i class="feather-alert-triangle text-{{ $highestSeverity === 'danger' ? 'danger' : 'warning' }} ms-1 fs-12 flex-shrink-0" style="background: white; border-radius: 50%; padding: 1px;"></i>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endforeach
                                        @endforeach

                                        @if(empty($machine['lanes']))
                                            <div class="text-muted fs-11 text-center py-2 position-relative" style="z-index: 2; opacity: 0.4;">
                                                {{ __('production.no_operations_scheduled') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                     </div>
                 </div>

                 {{-- Status Legend --}}
                 <div class="d-flex flex-wrap gap-3 align-items-center justify-content-center mt-4 pt-3 border-top fs-12">
                     <span class="fw-bold text-muted">{{ __('production.legend') ?? 'Legend' }}:</span>
                     <span class="d-flex align-items-center gap-1"><span class="badge bg-success">&nbsp;&nbsp;</span> {{ __('production.completed') }}</span>
                     <span class="d-flex align-items-center gap-1"><span class="badge bg-warning text-dark">&nbsp;&nbsp;</span> {{ __('production.running') }}</span>
                     <span class="d-flex align-items-center gap-1"><span class="badge bg-info">&nbsp;&nbsp;</span> {{ __('production.ready') }}</span>
                     <span class="d-flex align-items-center gap-1"><span class="badge bg-primary">&nbsp;&nbsp;</span> {{ __('production.scheduled_schedules') }}</span>
                     <span class="d-flex align-items-center gap-1"><span class="badge bg-soft-danger text-danger border border-danger">&nbsp;&nbsp;</span> {{ __('production.paused') ?? 'Paused' }}</span>
                 </div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="feather-calendar fs-36 mb-3 d-block"></i>
                    <p class="fs-14">{{ __('production.no_operations_scheduled_period') }}</p>
                    <a href="{{ route('production.schedules.create') }}" class="btn btn-primary btn-sm">
                        <i class="feather-plus me-2"></i>{{ __('production.create_schedule') }}
                    </a>
                </div>
            @endif
        @else
            {{-- Operations List (Table-based calendar) --}}
            @if($operations->count() > 0)
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 12%">{{ __('production.planned_start') }}</th>
                            <th style="width: 12%">{{ __('production.planned_finish') }}</th>
                            <th style="width: 15%">{{ __('production.schedule_number') }}</th>
                            <th style="width: 20%">{{ __('production.production_order') }} / {{ __('production.product') }}</th>
                            <th style="width: 18%">{{ __('production.operations') }}</th>
                            <th style="width: 12%">{{ __('production.work_centers') }}</th>
                            <th style="width: 10%">{{ __('production.assigned_machine') ?? 'Machine' }}</th>
                            <th style="width: 10%">{{ __('production.status') }}</th>
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
                                        <span class="badge bg-soft-warning text-warning">{{ __('production.running') }}</span>
                                    @elseif($op->status === 'ready')
                                        <span class="badge bg-soft-info text-info">{{ __('production.ready') }}</span>
                                    @elseif($op->status === 'completed')
                                        <span class="badge bg-soft-success text-success">{{ __('production.completed') }}</span>
                                    @elseif($op->status === 'paused')
                                        <span class="badge bg-soft-warning text-warning">{{ __('production.paused') ?? 'Paused' }}</span>
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
                    <p class="fs-14">{{ __('production.no_operations_scheduled_period') }}</p>
                    <a href="{{ route('production.schedules.create') }}" class="btn btn-primary btn-sm">
                        <i class="feather-plus me-2"></i>{{ __('production.create_schedule') }}
                    </a>
                </div>
            @endif
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });
    </script>
@endpush
