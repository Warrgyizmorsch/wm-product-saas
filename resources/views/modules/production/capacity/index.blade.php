@extends('layouts.duralux')

@section('title', __('production.capacity_planning') . ' | SaaS ERP')
@section('page-title', __('production.capacity_planning'))
@section('breadcrumb', __('production.capacity_planning'))

@push('styles')
    <style>
        .erp-single-panel {
            display: flex !important;
            flex-direction: column !important;
            min-height: calc(100vh - 180px) !important;
        }
        .table-responsive {
            position: relative;
        }
        .table-responsive:has(.dropdown.show) {
            overflow: visible !important;
        }
        /* KPI Cards */
        .capacity-kpi-card {
            border-left: 4px solid #3b82f6;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .capacity-kpi-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,.08) !important; }

        /* Status Badges */
        .badge-status-available    { background-color: #d1fae5; color: #065f46; }
        .badge-status-balanced     { background-color: #dbeafe; color: #1e40af; }
        .badge-status-near_capacity{ background-color: #fef3c7; color: #92400e; }
        .badge-status-overloaded   { background-color: #fee2e2; color: #991b1b; }
        .badge-status-unavailable  { background-color: #f3f4f6; color: #374151; }
        .badge-status-downtime     { background-color: #ffedd5; color: #c2410c; }

        /* Daily grid — date separator row */
        .daily-date-row td {
            background: #f8f9fa;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.5px;
            color: #374151;
            border-top: 2px solid #e5e7eb !important;
            padding: 6px 12px !important;
        }
        .daily-wc-row td:first-child { padding-left: 28px !important; }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ----- Daily Capacity Grid: date pill switching -----
            document.querySelectorAll('.daily-date-pill').forEach(function(pill) {
                pill.addEventListener('click', function () {
                    const targetId = this.getAttribute('data-daily-target');

                    // Hide all date panels
                    document.querySelectorAll('.daily-date-panel').forEach(p => p.classList.add('d-none'));

                    // Show selected
                    const panel = document.getElementById(targetId);
                    if (panel) panel.classList.remove('d-none');

                    // Update pill styles
                    document.querySelectorAll('.daily-date-pill').forEach(p => {
                        p.classList.remove('btn-primary');
                        p.classList.add('btn-light', 'border');
                        // Reset inner badge colour to default grey
                        const badge = p.querySelector('.badge');
                        if (badge) {
                            badge.classList.remove('bg-white', 'text-primary');
                            if (!badge.classList.contains('bg-danger-subtle')) {
                                badge.classList.add('bg-secondary-subtle', 'text-secondary');
                            }
                        }
                        // Reset icon colour
                        const icon = p.querySelector('i.feather-alert-octagon');
                        if (icon) { icon.classList.remove('text-white'); icon.classList.add('text-danger'); }
                    });

                    this.classList.add('btn-primary');
                    this.classList.remove('btn-light', 'border');
                    const activeBadge = this.querySelector('.badge');
                    if (activeBadge && !activeBadge.classList.contains('bg-danger-subtle')) {
                        activeBadge.classList.remove('bg-secondary-subtle', 'text-secondary');
                        activeBadge.classList.add('bg-white', 'text-primary');
                    }
                    const activeIcon = this.querySelector('i.feather-alert-octagon');
                    if (activeIcon) { activeIcon.classList.remove('text-danger'); activeIcon.classList.add('text-white'); }
                });
            });

            // ----- Reschedule Modal -----
            const rescheduleModal = document.getElementById('rescheduleModal');
            if (rescheduleModal) {
                rescheduleModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const opId          = button.getAttribute('data-op-id');
                    const opSeq         = button.getAttribute('data-op-seq');
                    const currentStart  = button.getAttribute('data-op-start');
                    const machineId     = button.getAttribute('data-op-machine');
                    const workCenterName= button.getAttribute('data-op-wc-name');
                    const wcId          = button.getAttribute('data-op-wc-id');

                    rescheduleModal.querySelector('form').action = `/production/capacity/${opId}/reschedule`;
                    document.getElementById('reschedule-op-title').textContent =
                        `{{ __('production.js_sequence_label') }} ${opSeq} ({{ __('production.js_work_center_label') }}: ${workCenterName})`;
                    document.getElementById('planned_start').value =
                        currentStart ? currentStart.replace(' ', 'T').substring(0, 16) : '';

                    const machineSelect = document.getElementById('machine_id_select');
                    Array.from(machineSelect.options).forEach(opt => {
                        opt.style.display = (opt.value === '' || opt.getAttribute('data-wc-id') === wcId) ? '' : 'none';
                    });
                    machineSelect.value = machineId || '';
                });
            }

            // ----- Load Balance Suggestions Modal -----
            const suggestionsModal = document.getElementById('suggestionsModal');
            if (suggestionsModal) {
                suggestionsModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const opId  = button.getAttribute('data-op-id');
                    const opSeq = button.getAttribute('data-op-seq');

                    document.getElementById('suggestions-op-title').textContent =
                        `{{ __('production.js_sequence_label') }} ${opSeq}`;
                    const container = document.getElementById('suggestions-container');
                    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">{{ __('production.js_calculating_slots') }}</p></div>';

                    fetch(`/production/capacity/${opId}/suggest`)
                        .then(r => r.json())
                        .then(data => {
                            container.innerHTML = '';
                            if (data.success && data.suggestions.length > 0) {
                                let html = '<div class="list-group">';
                                data.suggestions.forEach(s => {
                                    const badgeClass = s.conflict_resolved
                                        ? 'badge bg-success-subtle text-success'
                                        : 'badge bg-warning-subtle text-warning';
                                    const badgeText = s.conflict_resolved
                                        ? '{{ __('production.js_conflict_resolved') }}'
                                        : '{{ __('production.js_alt_time_slot') }}';
                                    html += `
                                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center mb-2 rounded border p-3">
                                            <div>
                                                <h6 class="fw-bold mb-1 text-dark">${s.machine_name} (${s.machine_code})</h6>
                                                <p class="mb-1 text-muted fs-12">
                                                    <strong>{{ __('production.js_suggested_start') }}:</strong> ${s.suggested_start}<br>
                                                    <strong>{{ __('production.js_suggested_finish') }}:</strong> ${s.suggested_finish}
                                                </p>
                                                ${s.warning ? `<span class="text-warning fs-11"><i class="feather-alert-triangle me-1"></i>${s.warning}</span>` : ''}
                                            </div>
                                            <div class="text-end">
                                                <span class="${badgeClass} d-block mb-2">${badgeText}</span>
                                                <button type="button" class="btn btn-sm btn-primary apply-suggestion-btn"
                                                        data-op-id="${opId}"
                                                        data-machine-id="${s.machine_id}"
                                                        data-start="${s.suggested_start}">
                                                    {{ __('production.btn_apply') }}
                                                </button>
                                            </div>
                                        </div>`;
                                });
                                html += '</div>';
                                container.innerHTML = html;

                                document.querySelectorAll('.apply-suggestion-btn').forEach(btn => {
                                    btn.addEventListener('click', function () {
                                        fetch(`/production/capacity/${this.getAttribute('data-op-id')}/reschedule`, {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                            },
                                            body: JSON.stringify({
                                                planned_start: this.getAttribute('data-start'),
                                                machine_id:    this.getAttribute('data-machine-id'),
                                                reason: '{{ __('production.js_applied_load_reason') }}'
                                            })
                                        })
                                        .then(r => r.json())
                                        .then(result => {
                                            if (result.success) window.location.reload();
                                            else alert(result.message || '{{ __('production.js_rescheduling_failed') }}');
                                        });
                                    });
                                });
                            } else {
                                container.innerHTML = '<div class="alert alert-light text-center">{{ __('production.modal_no_slots') }}</div>';
                            }
                        })
                        .catch(() => {
                            container.innerHTML = '<div class="alert alert-danger text-center">{{ __('production.modal_load_failed') }}</div>';
                        });
                });
            }
        });
    </script>
@endpush

@section('content')
    @php
        $wcOverloaded      = collect($workCenterLoads)->where('status', 'overloaded')->count();
        $machOverloaded    = collect($machineLoads)->where('status', 'overloaded')->count();
        $wcTotalAvailable  = collect($workCenterLoads)->sum('available_hours');
        $wcTotalRequired   = collect($workCenterLoads)->sum('required_hours');
        $wcAvgUtilization  = $wcTotalAvailable > 0 ? ($wcTotalRequired / $wcTotalAvailable) * 100 : 0;

        // Group daily loads by date for the compact grid
        $dailyByDate = collect($dailyLoads)->groupBy('date');
    @endphp

    <div class="erp-single-panel">

        {{-- Toasts --}}
        @if(session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if(session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- ─── Toolbar (BOM-style) ─── --}}
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('production.capacity_planning') }}</h5>
            <div class="d-flex gap-2 ms-auto">

                {{-- Date-range filter inside x-ui.filter (BOM pattern) --}}
                <form method="GET" action="{{ route('production.capacity.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3">
                            <i class="feather-sliders me-1 text-primary"></i>
                            {{ __('production.filter_options') }}
                        </h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.start_date') }}</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="start_date"
                                value="{{ request('start_date', $startDate->toDateString()) }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.end_date') }}</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="end_date"
                                value="{{ request('end_date', $endDate->toDateString()) }}" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.capacity.index') }}" class="btn btn-sm btn-light border">
                                {{ __('production.reset') }}
                            </a>
                            <button type="submit" class="btn btn-sm btn-primary">
                                {{ __('production.apply_filters') }}
                            </button>
                        </div>
                    </x-ui.filter>
                </form>

            </div>
        </div>

        {{-- ─── KPI Cards ─── --}}
        <div class="row mb-4 g-3">
            <div class="col-6 col-md-3">
                <div class="card capacity-kpi-card shadow-sm border-0">
                    <div class="card-body p-3">
                        <p class="text-muted fw-bold text-uppercase fs-10 mb-1">{{ __('production.available_capacity_kpi') }}</p>
                        <h4 class="text-dark fw-bold mb-0">{{ number_format($wcTotalAvailable, 1) }} <small class="fs-13">{{ __('production.hrs_unit') }}</small></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card capacity-kpi-card shadow-sm border-0" style="border-left-color: #10b981;">
                    <div class="card-body p-3">
                        <p class="text-muted fw-bold text-uppercase fs-10 mb-1">{{ __('production.planned_capacity_kpi') }}</p>
                        <h4 class="text-dark fw-bold mb-0">{{ number_format($wcTotalRequired, 1) }} <small class="fs-13">{{ __('production.hrs_unit') }}</small></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card capacity-kpi-card shadow-sm border-0" style="border-left-color: #f59e0b;">
                    <div class="card-body p-3">
                        <p class="text-muted fw-bold text-uppercase fs-10 mb-1">{{ __('production.avg_utilization') }}</p>
                        <h4 class="text-dark fw-bold mb-0">{{ number_format($wcAvgUtilization, 1) }}<small class="fs-13">%</small></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card capacity-kpi-card shadow-sm border-0" style="border-left-color: #ef4444;">
                    <div class="card-body p-3">
                        <p class="text-muted fw-bold text-uppercase fs-10 mb-1">{{ __('production.conflicts_overloads') }}</p>
                        <h4 class="text-dark fw-bold mb-0">{{ count($conflictMessages) }} / {{ $wcOverloaded + $machOverloaded }}</h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─── Conflict / Overload Alert ─── --}}
        @if(count($conflictMessages) > 0 || count($overloadMessages) > 0)
            <div class="alert alert-warning mb-4 shadow-sm border-0">
                <h6 class="fw-bold mb-2">
                    <i class="feather-alert-triangle me-2"></i>{{ __('production.scheduling_overloads_detected') }}
                </h6>
                <ul class="mb-0 fs-12 px-3">
                    @foreach($conflictMessages as $cm)
                        <li><strong>{{ __('production.overlap_label') }}:</strong> {{ $cm }}</li>
                    @endforeach
                    @foreach($overloadMessages as $om)
                        <li><strong>{{ __('production.overload_label') }}:</strong> {{ $om }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ─── x-ui.horizontal-tabs (BOM-style) ─── --}}
        <x-ui.horizontal-tabs id="capacityTabs" :tabs="[
            ['id' => 'tab-wc',    'label' => __('production.tab_work_center_load'),    'active' => true, 'icon' => 'feather-cpu'],
            ['id' => 'tab-mach',  'label' => __('production.tab_machine_load'),        'icon' => 'feather-settings'],
            ['id' => 'tab-daily', 'label' => __('production.tab_daily_capacity_grid'), 'icon' => 'feather-calendar'],
            ['id' => 'tab-ops',   'label' => __('production.tab_scheduled_ops'),       'icon' => 'feather-list'],
        ]" />

        <div class="tab-content mt-3">

        {{-- ══════════════════════════════════════════════════════════
             Panel 1: Work Center Load
        ══════════════════════════════════════════════════════════ --}}
        <div class="tab-pane fade show active" id="tab-wc" role="tabpanel" aria-labelledby="tab-wc-tab">
            <div class="table-responsive bg-white rounded border shadow-sm">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th>{{ __('production.col_work_center') }}</th>
                            <th>{{ __('production.code') }}</th>
                            <th>{{ __('production.col_available_hrs') }}</th>
                            <th>{{ __('production.col_setup_hrs') }}</th>
                            <th>{{ __('production.col_run_hrs') }}</th>
                            <th>{{ __('production.col_required_hrs') }}</th>
                            <th style="width:20%">{{ __('production.col_utilization') }}</th>
                            <th>{{ __('production.col_conflicts') }}</th>
                            <th>{{ __('production.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($workCenterLoads as $wcl)
                            <tr>
                                <td><strong>{{ $wcl['work_center']->name }}</strong></td>
                                <td><code>{{ $wcl['work_center']->code }}</code></td>
                                <td>{{ number_format($wcl['available_hours'], 1) }}</td>
                                <td>{{ number_format($wcl['setup_hours'], 1) }}</td>
                                <td>{{ number_format($wcl['run_hours'], 1) }}</td>
                                <td>{{ number_format($wcl['required_hours'], 1) }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1 mb-0" style="height:6px;">
                                            <div class="progress-bar {{ $wcl['utilization'] > 100 ? 'bg-danger' : ($wcl['utilization'] > 85 ? 'bg-warning' : 'bg-success') }}"
                                                 role="progressbar"
                                                 style="width:{{ min(100, $wcl['utilization']) }}%">
                                            </div>
                                        </div>
                                        <span class="fs-12 fw-bold">{{ number_format($wcl['utilization'], 1) }}%</span>
                                    </div>
                                </td>
                                <td>
                                    @if($wcl['conflicts_count'] > 0)
                                        <span class="badge bg-danger-subtle text-danger">
                                            {{ trans('production.x_conflicts', ['count' => $wcl['conflicts_count']]) }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-status-{{ $wcl['status'] }}">
                                        {{ str_replace('_', ' ', strtoupper($wcl['status'])) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="feather-cpu mb-2 d-block" style="font-size:28px;opacity:.3"></i>
                                    {{ __('production.no_active_work_centers') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             Panel 2: Machine Load
        ══════════════════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="tab-mach" role="tabpanel" aria-labelledby="tab-mach-tab">
            <div class="table-responsive bg-white rounded border shadow-sm">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th>{{ __('production.col_machine') }}</th>
                            <th>{{ __('production.col_work_center') }}</th>
                            <th>{{ __('production.col_available_hrs') }}</th>
                            <th>{{ __('production.col_required_hrs') }}</th>
                            <th style="width:25%">{{ __('production.col_utilization') }}</th>
                            <th>{{ __('production.col_downtime_hrs') }}</th>
                            <th>{{ __('production.col_overload_hrs') }}</th>
                            <th>{{ __('production.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($machineLoads as $ml)
                            <tr>
                                <td><strong>{{ $ml['machine']->name }}</strong> <span class="text-muted fs-11">({{ $ml['machine']->code }})</span></td>
                                <td>{{ $ml['machine']->workCenter?->name }}</td>
                                <td>{{ number_format($ml['available_hours'], 1) }}</td>
                                <td>{{ number_format($ml['required_hours'], 1) }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1 mb-0" style="height:6px;">
                                            <div class="progress-bar {{ $ml['utilization'] > 100 ? 'bg-danger' : ($ml['utilization'] > 85 ? 'bg-warning' : 'bg-success') }}"
                                                 role="progressbar"
                                                 style="width:{{ min(100, $ml['utilization']) }}%">
                                            </div>
                                        </div>
                                        <span class="fs-12 fw-bold">{{ number_format($ml['utilization'], 1) }}%</span>
                                    </div>
                                </td>
                                <td>{{ number_format($ml['downtime_hours'], 1) }}</td>
                                <td>{{ number_format($ml['overload_hours'], 1) }}</td>
                                <td>
                                    <span class="badge badge-status-{{ $ml['status'] }}">
                                        {{ strtoupper($ml['status']) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="feather-settings mb-2 d-block" style="font-size:28px;opacity:.3"></i>
                                    {{ __('production.no_active_machines') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             Panel 3: Daily Capacity Grid — DATE TABS
             One pill per date; clicking shows that day's WC rows.
        ══════════════════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="tab-daily" role="tabpanel" aria-labelledby="tab-daily-tab">

            @if($dailyByDate->isEmpty())
                <div class="text-center py-5 text-muted bg-white rounded border shadow-sm">
                    <i class="feather-calendar mb-2 d-block" style="font-size:32px;opacity:.3"></i>
                    {{ __('production.no_grid_data') }}
                </div>
            @else
                {{-- ── Date range info bar ── --}}
                <div class="d-flex align-items-center gap-3 mb-3 px-1">
                    <span class="text-muted fs-12">
                        <i class="feather-calendar me-1 text-primary"></i>
                        <strong>{{ __('production.start_date') }}:</strong> {{ $startDate->toDateString() }}
                        &nbsp;→&nbsp;
                        <strong>{{ __('production.end_date') }}:</strong> {{ $endDate->toDateString() }}
                    </span>
                    <span class="badge bg-primary-subtle text-primary fw-semibold fs-11">
                        {{ $dailyByDate->count() }} {{ Str::plural('day', $dailyByDate->count()) }}
                    </span>
                    @php $totalOverloadedDates = $dailyByDate->filter(fn($rows) => $rows->where('overloaded', true)->count() > 0)->count(); @endphp
                    @if($totalOverloadedDates > 0)
                        <span class="badge bg-danger-subtle text-danger fw-semibold fs-11">
                            <i class="feather-alert-octagon me-1" style="font-size:10px"></i>
                            {{ $totalOverloadedDates }} {{ Str::plural('day', $totalOverloadedDates) }} overloaded
                        </span>
                    @endif
                </div>

                {{-- ── Date pill tabs ── --}}
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach($dailyByDate as $date => $rows)
                        @php
                            $dateId   = 'dg-' . Str::slug($date);
                            $isFirst  = $loop->first;
                            $hasOverload = $rows->where('overloaded', true)->count() > 0;
                            $avgUtil  = $rows->avg('utilization');
                        @endphp
                        <button type="button"
                                class="btn btn-sm daily-date-pill {{ $isFirst ? 'btn-primary' : 'btn-light border' }}"
                                data-daily-target="{{ $dateId }}">
                            @if($hasOverload)
                                <i class="feather-alert-octagon me-1 text-{{ $isFirst ? 'white' : 'danger' }}" style="font-size:10px"></i>
                            @endif
                            {{ \Carbon\Carbon::parse($date)->format('d M') }}
                            <span class="ms-1 badge {{ $isFirst ? 'bg-white text-primary' : ($hasOverload ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary') }} fw-normal" style="font-size:10px">
                                {{ round($avgUtil) }}%
                            </span>
                        </button>
                    @endforeach
                </div>

                {{-- ── Per-date panels ── --}}
                @foreach($dailyByDate as $date => $rows)
                    @php
                        $dateId  = 'dg-' . Str::slug($date);
                        $isFirst = $loop->first;
                        $dayOfWeek = \Carbon\Carbon::parse($date)->format('l');
                        $hasOverload = $rows->where('overloaded', true)->count() > 0;
                    @endphp
                    <div id="{{ $dateId }}"
                         class="daily-date-panel {{ $isFirst ? '' : 'd-none' }}">

                        {{-- Date panel header --}}
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="feather-calendar me-2 text-primary"></i>
                                {{ \Carbon\Carbon::parse($date)->format('l, d F Y') }}
                            </h6>
                            <span class="badge bg-secondary-subtle text-secondary fw-normal fs-11">
                                {{ $rows->count() }} {{ __('production.col_work_center') }}
                            </span>
                            @if($hasOverload)
                                <span class="badge bg-danger-subtle text-danger fw-semibold fs-11">
                                    <i class="feather-alert-octagon me-1" style="font-size:10px"></i>
                                    {{ $rows->where('overloaded', true)->count() }} {{ __('production.status_overloaded') }}
                                </span>
                            @endif
                        </div>

                        <div class="table-responsive bg-white rounded border shadow-sm mb-3">
                            <x-ui.odoo-form-ui type="table">
                                <thead>
                                    <tr>
                                        <th>{{ __('production.col_work_center') }}</th>
                                        <th>{{ __('production.col_available_hrs') }}</th>
                                        <th>{{ __('production.col_used_hrs') }}</th>
                                        <th>{{ __('production.col_remaining_hrs') }}</th>
                                        <th style="width:18%">{{ __('production.col_utilization') }}</th>
                                        <th>{{ __('production.col_indicator') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $dl)
                                        <tr class="{{ $dl['overloaded'] ? 'table-danger' : '' }}">
                                            <td><strong>{{ $dl['work_center']->name }}</strong></td>
                                            <td>{{ number_format($dl['available_hours'], 1) }}</td>
                                            <td>{{ number_format($dl['used_hours'], 1) }}</td>
                                            <td>{{ number_format($dl['remaining_hours'], 1) }}</td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1 mb-0" style="height:6px;">
                                                        <div class="progress-bar {{ $dl['utilization'] > 100 ? 'bg-danger' : ($dl['utilization'] > 85 ? 'bg-warning' : 'bg-success') }}"
                                                             role="progressbar"
                                                             style="width:{{ min(100, $dl['utilization']) }}%"></div>
                                                    </div>
                                                    <span class="fs-11 fw-bold">{{ number_format($dl['utilization'], 1) }}%</span>
                                                </div>
                                            </td>
                                            <td>
                                                @if($dl['overloaded'])
                                                    <span class="badge bg-danger text-white">
                                                        <i class="feather-alert-octagon me-1"></i>{{ __('production.status_overloaded') }}
                                                    </span>
                                                @elseif($dl['utilization'] > 85)
                                                    <span class="badge bg-warning text-dark">{{ __('production.status_near_capacity') }}</span>
                                                @else
                                                    <span class="badge bg-success text-white">{{ __('production.status_normal') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>


        {{-- ══════════════════════════════════════════════════════════
             Panel 4: Scheduled Operations List
        ══════════════════════════════════════════════════════════ --}}
        <div class="tab-pane fade" id="tab-ops" role="tabpanel" aria-labelledby="tab-ops-tab">
            <div class="table-responsive bg-white rounded border shadow-sm">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th>{{ __('production.production_order') }}</th>
                            <th>{{ __('production.product') }}</th>
                            <th>{{ __('production.col_seq') }}</th>
                            <th>{{ __('production.col_work_center') }}</th>
                            <th>{{ __('production.col_machine') }}</th>
                            <th>{{ __('production.col_planned_qty') }}</th>
                            <th>{{ __('production.col_duration_mins') }}</th>
                            <th>{{ __('production.col_planned_start') }}</th>
                            <th>{{ __('production.col_planned_finish') }}</th>
                            <th class="text-end">{{ __('production.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activeOperations as $ao)
                            <tr>
                                <td><strong>{{ $ao->order?->order_number }}</strong></td>
                                <td>{{ $ao->order?->product?->name }}</td>
                                <td><code>{{ $ao->sequence }}</code></td>
                                <td>{{ $ao->workCenter?->name }}</td>
                                <td>{{ $ao->machine?->name ?? '—' }}</td>
                                <td>{{ number_format($ao->order?->quantity_ordered ?? 0) }}</td>
                                <td>{{ number_format($ao->planned_duration_minutes) }}</td>
                                <td><span class="text-dark fs-12">{{ $ao->planned_start->toDateTimeString() }}</span></td>
                                <td><span class="text-muted fs-12">{{ $ao->planned_finish->toDateTimeString() }}</span></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <x-ui.button size="sm" variant="light" class="border"
                                                data-bs-toggle="modal"
                                                data-bs-target="#rescheduleModal"
                                                data-op-id="{{ $ao->id }}"
                                                data-op-seq="{{ $ao->sequence }}"
                                                data-op-start="{{ $ao->planned_start->toDateTimeString() }}"
                                                data-op-machine="{{ $ao->machine_id }}"
                                                data-op-wc-id="{{ $ao->work_center_id }}"
                                                data-op-wc-name="{{ $ao->workCenter?->name }}">
                                            {{ __('production.btn_reschedule') }}
                                        </x-ui.button>
                                        <x-ui.button size="sm" variant="outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#suggestionsModal"
                                                data-op-id="{{ $ao->id }}"
                                                data-op-seq="{{ $ao->sequence }}"
                                                data-op-start="{{ $ao->planned_start->toDateTimeString() }}">
                                            {{ __('production.btn_balance_load') }}
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="feather-list mb-2 d-block" style="font-size:28px;opacity:.3"></i>
                                    {{ __('production.no_scheduled_ops') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        </div>{{-- /tab-content --}}

    </div>{{-- /erp-single-panel --}}

    {{-- ─── Modal: Reschedule ─── --}}
    <x-ui.modal id="rescheduleModal"
        :title="__('production.modal_reschedule_title')"
        formAction="#"
        :submitText="__('production.modal_reschedule_save')"
        :closeText="__('ui.close')">
        <p class="text-muted fs-12 mb-3">
            {{ __('production.modal_reschedule_desc') }} <strong id="reschedule-op-title"></strong>.
        </p>
        <div class="mb-3">
            <label for="planned_start" class="form-label fw-bold text-muted fs-11 text-uppercase">
                {{ __('production.modal_planned_start_label') }}
            </label>
            <x-ui.input type="datetime-local" name="planned_start" id="planned_start" required />
        </div>
        <div class="mb-3">
            <label for="machine_id" class="form-label fw-bold text-muted fs-11 text-uppercase">
                {{ __('production.modal_assign_machine_label') }}
            </label>
            <x-ui.select name="machine_id" id="machine_id_select">
                <option value="">{{ __('production.modal_machine_default_option') }}</option>
                @foreach($machines as $m)
                    <option value="{{ $m->id }}" data-wc-id="{{ $m->work_center_id }}">
                        {{ $m->name }} ({{ $m->code }})
                    </option>
                @endforeach
            </x-ui.select>
        </div>
        <div class="mb-3">
            <label for="reason" class="form-label fw-bold text-muted fs-11 text-uppercase">
                {{ __('production.modal_reason_label') }}
            </label>
            <x-ui.textarea name="reason" rows="2"
                placeholder="{{ __('production.modal_reason_placeholder') }}" required />
        </div>
    </x-ui.modal>

    {{-- ─── Modal: Load Balance Suggestions ─── --}}
    <x-ui.modal id="suggestionsModal"
        :title="__('production.modal_load_balance_title')"
        :showFooter="false">
        <p class="text-muted fs-12 mb-3">
            {{ __('production.modal_load_balance_desc') }} <strong id="suggestions-op-title"></strong>:
        </p>
        <div id="suggestions-container">
            {{-- Filled by AJAX --}}
        </div>
    </x-ui.modal>
@endsection
