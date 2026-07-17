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
        .capacity-kpi-card {
            border-left: 4px solid #3b82f6;
            transition: all 0.2s ease-in-out;
        }
        .capacity-kpi-card:hover {
            transform: translateY(-2px);
        }
        .progress-bar-utilization {
            height: 8px;
            border-radius: 4px;
        }
        .badge-status-available { background-color: #d1fae5; color: #065f46; }
        .badge-status-balanced { background-color: #dbeafe; color: #1e40af; }
        .badge-status-near_capacity { background-color: #fef3c7; color: #92400e; }
        .badge-status-overloaded { background-color: #fee2e2; color: #991b1b; }
        .badge-status-unavailable { background-color: #f3f4f6; color: #374151; }
        .badge-status-downtime { background-color: #ffedd5; color: #c2410c; }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Tab Toggle
            const tabButtons = document.querySelectorAll('.capacity-tab-btn');
            const tabPanels = document.querySelectorAll('.capacity-tab-panel');

            tabButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    const target = this.getAttribute('data-target');
                    tabButtons.forEach(b => {
                        b.classList.remove('active', 'btn-primary');
                        b.classList.add('btn-light');
                    });
                    this.classList.remove('btn-light');
                    this.classList.add('active', 'btn-primary');

                    tabPanels.forEach(p => p.classList.add('d-none'));
                    document.getElementById(target).classList.remove('d-none');
                });
            });

            // Reschedule Form trigger
            const rescheduleModal = document.getElementById('rescheduleModal');
            if (rescheduleModal) {
                rescheduleModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const opId = button.getAttribute('data-op-id');
                    const opSeq = button.getAttribute('data-op-seq');
                    const currentStart = button.getAttribute('data-op-start');
                    const machineId = button.getAttribute('data-op-machine');
                    const workCenterName = button.getAttribute('data-op-wc-name');

                    const form = rescheduleModal.querySelector('form');
                    form.action = `/production/capacity/${opId}/reschedule`;

                    document.getElementById('reschedule-op-title').textContent = `Sequence ${opSeq} (Work Center: ${workCenterName})`;
                    document.getElementById('planned_start').value = currentStart ? currentStart.replace(' ', 'T').substring(0, 16) : '';
                    
                    // Filter machine options based on work center logic
                    const machineSelect = document.getElementById('machine_id_select');
                    const wcId = button.getAttribute('data-op-wc-id');
                    Array.from(machineSelect.options).forEach(opt => {
                        if (opt.value === '') {
                            opt.style.display = 'block';
                        } else {
                            const optWc = opt.getAttribute('data-wc-id');
                            opt.style.display = (optWc === wcId) ? 'block' : 'none';
                        }
                    });
                    machineSelect.value = machineId || '';
                });
            }

            // Load Balance Suggestions trigger
            const suggestionsModal = document.getElementById('suggestionsModal');
            if (suggestionsModal) {
                suggestionsModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const opId = button.getAttribute('data-op-id');
                    const opSeq = button.getAttribute('data-op-seq');
                    const currentStart = button.getAttribute('data-op-start');

                    document.getElementById('suggestions-op-title').textContent = `Sequence ${opSeq}`;
                    const container = document.getElementById('suggestions-container');
                    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Calculating eligible slots...</p></div>';

                    fetch(`/production/capacity/${opId}/suggest`)
                        .then(response => response.json())
                        .then(data => {
                            container.innerHTML = '';
                            if (data.success && data.suggestions.length > 0) {
                                let html = '<div class="list-group">';
                                data.suggestions.forEach(s => {
                                    const badgeClass = s.conflict_resolved ? 'badge bg-success-subtle text-success' : 'badge bg-warning-subtle text-warning';
                                    const badgeText = s.conflict_resolved ? 'Conflict Resolved' : 'Alternative Time Slot';
                                    html += `
                                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center mb-2 rounded border p-3">
                                            <div>
                                                <h6 class="fw-bold mb-1 text-dark">${s.machine_name} (${s.machine_code})</h6>
                                                <p class="mb-1 text-muted fs-12">
                                                    <strong>Suggested Start:</strong> ${s.suggested_start}<br>
                                                    <strong>Suggested Finish:</strong> ${s.suggested_finish}
                                                </p>
                                                ${s.warning ? `<span class="text-warning fs-11"><i class="feather-alert-triangle me-1"></i>${s.warning}</span>` : ''}
                                            </div>
                                            <div class="text-end">
                                                <span class="${badgeClass} d-block mb-2">${badgeText}</span>
                                                <button type="button" class="btn btn-sm btn-primary apply-suggestion-btn" 
                                                        data-op-id="${opId}" 
                                                        data-machine-id="${s.machine_id}" 
                                                        data-start="${s.suggested_start}">
                                                    Apply
                                                </button>
                                            </div>
                                        </div>
                                    `;
                                });
                                html += '</div>';
                                container.innerHTML = html;

                                // Register event handlers for Apply buttons
                                document.querySelectorAll('.apply-suggestion-btn').forEach(btn => {
                                    btn.addEventListener('click', function () {
                                        const targetOpId = this.getAttribute('data-op-id');
                                        const mId = this.getAttribute('data-machine-id');
                                        const startVal = this.getAttribute('data-start');

                                        fetch(`/production/capacity/${targetOpId}/reschedule`, {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                            },
                                            body: JSON.stringify({
                                                planned_start: startVal,
                                                machine_id: mId,
                                                reason: 'Applied load-balancing suggestion'
                                            })
                                        })
                                        .then(res => res.json())
                                        .then(result => {
                                            if (result.success) {
                                                window.location.reload();
                                            } else {
                                                alert(result.message || 'Rescheduling failed.');
                                            }
                                        });
                                    });
                                });
                            } else {
                                container.innerHTML = '<div class="alert alert-light text-center">No alternate machine configurations or empty slots found in same work center.</div>';
                            }
                        })
                        .catch(err => {
                            container.innerHTML = '<div class="alert alert-danger text-center">Failed to load suggestions.</div>';
                        });
                });
            }
        });
    </script>
@endpush

@section('content')
    <div class="erp-single-panel">
        <!-- Success & Error Messages -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <!-- Filter Card -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('production.capacity.index') }}" class="row align-items-end g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-muted text-uppercase fs-11">{{ __('production.start_date') }}</label>
                        <x-ui.input type="date" name="start_date" :value="request('start_date', $startDate->toDateString())" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-muted text-uppercase fs-11">{{ __('production.end_date') }}</label>
                        <x-ui.input type="date" name="end_date" :value="request('end_date', $endDate->toDateString())" />
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <x-ui.button type="submit" variant="primary" class="flex-grow-1" icon="feather-filter">
                            {{ __('production.apply_filters') }}
                        </x-ui.button>
                        <x-ui.button href="{{ route('production.capacity.index') }}" variant="light" class="border">
                            {{ __('production.reset') }}
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>

        @php
            $wcOverloaded = collect($workCenterLoads)->where('status', 'overloaded')->count();
            $machOverloaded = collect($machineLoads)->where('status', 'overloaded')->count();
            $wcTotalAvailable = collect($workCenterLoads)->sum('available_hours');
            $wcTotalRequired = collect($workCenterLoads)->sum('required_hours');
            $wcAvgUtilization = $wcTotalAvailable > 0 ? ($wcTotalRequired / $wcTotalAvailable) * 100 : 0;
        @endphp

        <!-- KPI Block -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card capacity-kpi-card shadow-sm border-0">
                    <div class="card-body p-3">
                        <p class="text-muted fw-bold text-uppercase fs-10 mb-1">Available Capacity</p>
                        <h4 class="text-dark fw-bold mb-0">{{ number_format($wcTotalAvailable, 1) }} Hrs</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card capacity-kpi-card shadow-sm border-0" style="border-left-color: #10b981;">
                    <div class="card-body p-3">
                        <p class="text-muted fw-bold text-uppercase fs-10 mb-1">Planned Capacity</p>
                        <h4 class="text-dark fw-bold mb-0">{{ number_format($wcTotalRequired, 1) }} Hrs</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card capacity-kpi-card shadow-sm border-0" style="border-left-color: #f59e0b;">
                    <div class="card-body p-3">
                        <p class="text-muted fw-bold text-uppercase fs-10 mb-1">Avg Utilization</p>
                        <h4 class="text-dark fw-bold mb-0">{{ number_format($wcAvgUtilization, 1) }} %</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card capacity-kpi-card shadow-sm border-0" style="border-left-color: #ef4444;">
                    <div class="card-body p-3">
                        <p class="text-muted fw-bold text-uppercase fs-10 mb-1">Conflicts / Overloads</p>
                        <h4 class="text-dark fw-bold mb-0">{{ count($conflictMessages) }} / {{ $wcOverloaded + $machOverloaded }}</h4>
                    </div>
                </div>
            </div>
        </div>

        @if(count($conflictMessages) > 0 || count($overloadMessages) > 0)
            <div class="alert alert-warning mb-4 shadow-sm border-0">
                <h6 class="fw-bold mb-2"><i class="feather-alert-triangle me-2"></i>Scheduling Overloads &amp; Machine Overlaps Detected</h6>
                <ul class="mb-0 fs-12 px-3">
                    @foreach($conflictMessages as $cm)
                        <li><strong>Overlap:</strong> {{ $cm }}</li>
                    @endforeach
                    @foreach($overloadMessages as $om)
                        <li><strong>Overload:</strong> {{ $om }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Toggle Tabs -->
        <div class="mb-3 d-flex gap-2">
            <x-ui.button variant="primary" class="capacity-tab-btn active" data-target="work-center-panel">Work Center Load</x-ui.button>
            <x-ui.button variant="light" class="capacity-tab-btn" data-target="machine-panel">Machine Load</x-ui.button>
            <x-ui.button variant="light" class="capacity-tab-btn" data-target="daily-panel">Daily Capacity Grid</x-ui.button>
            <x-ui.button variant="light" class="capacity-tab-btn" data-target="ops-panel">Scheduled Operations List</x-ui.button>
        </div>

        <!-- Panel: Work Centers -->
        <div id="work-center-panel" class="capacity-tab-panel">
            <div class="table-responsive bg-white rounded border shadow-sm">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th>Work Center</th>
                            <th>Code</th>
                            <th>Available Hrs</th>
                            <th>Setup Hrs</th>
                            <th>Run Hrs</th>
                            <th>Required Hrs</th>
                            <th style="width: 20%">Utilization</th>
                            <th>Conflicts</th>
                            <th>Status</th>
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
                                        <div class="progress flex-grow-1 mb-0" style="height: 6px;">
                                            <div class="progress-bar {{ $wcl['utilization'] > 100 ? 'bg-danger' : ($wcl['utilization'] > 85 ? 'bg-warning' : 'bg-success') }}" 
                                                 role="progressbar" 
                                                 style="width: {{ min(100, $wcl['utilization']) }}%"></div>
                                        </div>
                                        <span class="fs-12 fw-bold">{{ number_format($wcl['utilization'], 1) }}%</span>
                                    </div>
                                </td>
                                <td>
                                    @if($wcl['conflicts_count'] > 0)
                                        <span class="badge bg-danger-subtle text-danger">{{ $wcl['conflicts_count'] }} Conflicts</span>
                                    @else
                                        <span class="text-muted">-</span>
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
                                <td colspan="9" class="text-center py-4 text-muted">No active work centers configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <!-- Panel: Machines -->
        <div id="machine-panel" class="capacity-tab-panel d-none">
            <div class="table-responsive bg-white rounded border shadow-sm">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th>Machine</th>
                            <th>Work Center</th>
                            <th>Available Hrs</th>
                            <th>Required Hrs</th>
                            <th style="width: 25%">Utilization</th>
                            <th>Downtime Hrs</th>
                            <th>Overload Hrs</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($machineLoads as $ml)
                            <tr>
                                <td><strong>{{ $ml['machine']->name }}</strong> ({{ $ml['machine']->code }})</td>
                                <td>{{ $ml['machine']->workCenter?->name }}</td>
                                <td>{{ number_format($ml['available_hours'], 1) }}</td>
                                <td>{{ number_format($ml['required_hours'], 1) }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1 mb-0" style="height: 6px;">
                                            <div class="progress-bar {{ $ml['utilization'] > 100 ? 'bg-danger' : ($ml['utilization'] > 85 ? 'bg-warning' : 'bg-success') }}" 
                                                 role="progressbar" 
                                                 style="width: {{ min(100, $ml['utilization']) }}%"></div>
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
                                <td colspan="8" class="text-center py-4 text-muted">No active machines configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <!-- Panel: Daily Grid -->
        <div id="daily-panel" class="capacity-tab-panel d-none">
            <div class="table-responsive bg-white rounded border shadow-sm">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Work Center</th>
                            <th>Available Hrs</th>
                            <th>Used Hrs</th>
                            <th>Remaining Hrs</th>
                            <th>Utilization</th>
                            <th>Indicator</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dailyLoads as $dl)
                            <tr class="{{ $dl['overloaded'] ? 'table-danger-subtle' : '' }}">
                                <td><code>{{ $dl['date'] }}</code></td>
                                <td>{{ $dl['work_center']->name }}</td>
                                <td>{{ number_format($dl['available_hours'], 1) }}</td>
                                <td>{{ number_format($dl['used_hours'], 1) }}</td>
                                <td>{{ number_format($dl['remaining_hours'], 1) }}</td>
                                <td><strong>{{ number_format($dl['utilization'], 1) }}%</strong></td>
                                <td>
                                    @if($dl['overloaded'])
                                        <span class="badge bg-danger text-white"><i class="feather-alert-octagon me-1"></i>Overloaded</span>
                                    @elseif($dl['utilization'] > 85)
                                        <span class="badge bg-warning text-dark">Near Capacity</span>
                                    @else
                                        <span class="badge bg-success text-white">Normal</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No grid data calculated.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <!-- Panel: Active Operations List -->
        <div id="ops-panel" class="capacity-tab-panel d-none">
            <div class="table-responsive bg-white rounded border shadow-sm">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Product</th>
                            <th>Seq</th>
                            <th>Work Center</th>
                            <th>Machine</th>
                            <th>Planned Qty</th>
                            <th>Duration (Mins)</th>
                            <th>Planned Start</th>
                            <th>Planned Finish</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activeOperations as $ao)
                            <tr>
                                <td><strong>{{ $ao->order?->order_number }}</strong></td>
                                <td>{{ $ao->order?->product?->name }}</td>
                                <td><code>{{ $ao->sequence }}</code></td>
                                <td>{{ $ao->workCenter?->name }}</td>
                                <td>{{ $ao->machine?->name ?? 'N/A' }}</td>
                                <td>{{ number_format($ao->order?->quantity_ordered ?? 0) }}</td>
                                <td>{{ number_format($ao->planned_duration_minutes) }}</td>
                                <td><span class="text-dark">{{ $ao->planned_start->toDateTimeString() }}</span></td>
                                <td><span class="text-muted">{{ $ao->planned_finish->toDateTimeString() }}</span></td>
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
                                            Reschedule
                                        </x-ui.button>
                                        <x-ui.button size="sm" variant="outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#suggestionsModal"
                                                data-op-id="{{ $ao->id }}"
                                                data-op-seq="{{ $ao->sequence }}"
                                                data-op-start="{{ $ao->planned_start->toDateTimeString() }}">
                                            Balance Load
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">No scheduled operations found in this timeframe.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>
    </div>

    <!-- Modal: Reschedule -->
    <x-ui.modal id="rescheduleModal" title="Reschedule Operation" formAction="#" submitText="Save Changes" closeText="Close">
        <p class="text-muted fs-12 mb-3">Adjust scheduling timeslot and assign machines for <strong id="reschedule-op-title"></strong>.</p>
        
        <div class="mb-3">
            <label for="planned_start" class="form-label fw-bold text-muted fs-11 text-uppercase">Planned Start</label>
            <x-ui.input type="datetime-local" name="planned_start" id="planned_start" required />
        </div>

        <div class="mb-3">
            <label for="machine_id" class="form-label fw-bold text-muted fs-11 text-uppercase">Assign Machine</label>
            <x-ui.select name="machine_id" id="machine_id_select">
                <option value="">-- Maintain Work Center Default --</option>
                @foreach($machines as $m)
                    <option value="{{ $m->id }}" data-wc-id="{{ $m->work_center_id }}">
                        {{ $m->name }} ({{ $m->code }})
                    </option>
                @endforeach
            </x-ui.select>
        </div>

        <div class="mb-3">
            <label for="reason" class="form-label fw-bold text-muted fs-11 text-uppercase">Reason for Change</label>
            <x-ui.textarea name="reason" rows="2" placeholder="Describe why this schedule change is occurring..." required />
        </div>
    </x-ui.modal>

    <!-- Modal: Load Balance Suggestions -->
    <x-ui.modal id="suggestionsModal" title="Load Balancing Suggestions" :showFooter="false">
        <p class="text-muted fs-12 mb-3">Eligible alternative machine slots in the same work center for <strong id="suggestions-op-title"></strong>:</p>
        <div id="suggestions-container">
            <!-- Filled by AJAX -->
        </div>
    </x-ui.modal>
@endsection
