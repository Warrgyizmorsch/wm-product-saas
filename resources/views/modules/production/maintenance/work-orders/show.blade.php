@extends('layouts.duralux')

@section('title', 'Work Order ' . $workOrder->work_order_number . ' | SaaS ERP')
@section('page-title', 'Work Order Execution')
@section('breadcrumb', 'Work Order Detail')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        @if(!in_array($workOrder->status, ['in_progress', 'completed', 'cancelled']))
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                <i class="feather-calendar me-2"></i>Schedule
            </button>
        @endif

        @if(!in_array($workOrder->status, ['completed', 'cancelled']))
            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#assignModal">
                <i class="feather-user-plus me-2"></i>Assign
            </button>
        @endif

        @if(in_array($workOrder->status, ['draft', 'scheduled']))
            <form action="{{ route('production.maintenance.work-orders.start', $workOrder->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-warning text-dark fw-bold">
                    <i class="feather-play me-2"></i>Start Maintenance
                </button>
            </form>
        @endif

        @if($workOrder->status === 'in_progress')
            <button type="button" class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#completeModal">
                <i class="feather-check-circle me-2"></i>Complete Maintenance
            </button>
        @endif

        @if(!in_array($workOrder->status, ['in_progress','completed', 'cancelled']))
            <form action="{{ route('production.maintenance.work-orders.cancel', $workOrder->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this work order?');">
                @csrf
                <button type="submit" class="btn btn-outline-danger">
                    <i class="feather-x me-2"></i>{{ __('production.cancel') }}</button>
            </form>
        @endif

        <a href="{{ route('production.maintenance.work-orders.index') }}" class="btn btn-light border">
            <i class="feather-arrow-left me-2"></i>{{ __('production.back_to_list') }}</a>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel">
        <!-- Toast Notifications -->
        @if(session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if(session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <!-- Work Order Overview Header Card -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h4 class="mb-0 text-dark fw-bold">{{ $workOrder->work_order_number }}</h4>
                            <span class="badge bg-soft-{{ $workOrder->type === 'breakdown' ? 'danger' : 'info' }} text-{{ $workOrder->type === 'breakdown' ? 'danger' : 'info' }}">
                                {{ ucfirst($workOrder->type) }}
                            </span>
                            <span class="badge bg-soft-{{ $workOrder->status === 'completed' ? 'success' : ($workOrder->status === 'in_progress' ? 'warning' : 'secondary') }} text-dark fs-12">
                                {{ ucfirst(str_replace('_', ' ', $workOrder->status)) }}
                            </span>
                            @if($workOrder->was_machine_scraped)
                                <span class="badge bg-danger text-white fs-12">
                                    <i class="feather-trash-2 me-1"></i>Machine Scrapped
                                </span>
                            @endif
                        </div>
                        <p class="text-muted mb-0 fs-13">
                            Machine: <strong class="text-dark">{{ $workOrder->machine?->name }}</strong> ({{ $workOrder->machine?->code }}) |
                            Work Center: {{ $workOrder->machine?->workCenter?->name ?? 'N/A' }} |
                            Assigned Technician: <strong>{{ $workOrder->technician?->name ?? 'Unassigned' }}</strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Main Work Details -->
            <div class="col-lg-8">
                <!-- Problem Description & Work Log -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h6 class="card-title mb-0 fw-bold text-dark"><i class="feather-file-text me-2 text-primary"></i>Maintenance Scope & Details</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="fs-12 text-muted fw-bold d-block">Problem Description / Maintenance Task</label>
                            <div class="p-3 bg-light rounded text-dark fs-13">{{ $workOrder->problem_description }}</div>
                        </div>

                        @if($workOrder->work_performed)
                            <div class="mb-3">
                                <label class="fs-12 text-muted fw-bold d-block">Work Performed / Resolution Notes</label>
                                <div class="p-3 bg-soft-success text-dark rounded fs-13">{{ $workOrder->work_performed }}</div>
                            </div>
                        @endif

                        @php
                            $existingAssignments = $workOrder->assignments()->with('technician')->orderBy('assigned_at')->get();
                            $assignedPeople = $existingAssignments->map(function ($assignment) {
                                return $assignment->assignment_type === 'internal'
                                    ? ($assignment->technician?->name ?? $assignment->technician_name)
                                    : ($assignment->technician_name ?: 'External technician');
                            })->filter()->values();
                            $assignmentSummary = $assignedPeople->isNotEmpty() ? $assignedPeople->implode(', ') : 'No assigned people yet';
                        @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="fs-12 text-muted fw-bold d-block mb-0">Assignments</label>
                                <button
                                    type="button"
                                    class="btn btn-xs btn-light border rounded-pill d-inline-flex align-items-center gap-1"
                                    data-bs-toggle="popover"
                                    data-bs-trigger="hover focus"
                                    data-bs-placement="top"
                                    data-bs-content="{{ e($assignmentSummary) }}"
                                    title="Assigned people"
                                >
                                    <i class="feather-users fs-11"></i>
                                    <span class="fw-bold">{{ $assignedPeople->count() }}</span>
                                </button>
                            </div>
                            @if($existingAssignments->isNotEmpty())
                                <div class="list-group list-group-flush border rounded">
                                    @foreach($existingAssignments as $assignment)
                                        <div class="list-group-item py-3 fs-13">
                                            <div class="d-flex justify-content-between align-items-start gap-3">
                                                <div>
                                                    <strong class="text-dark">
                                                        {{ $assignment->assignment_type === 'internal' ? 'Internal / In-House' : 'External' }}
                                                    </strong>
                                                    <div class="text-muted mt-1">
                                                        @if($assignment->assignment_type === 'internal')
                                                            {{ $assignment->technician?->name ?? $assignment->technician_name }}
                                                        @else
                                                            {{ $assignment->technician_name }}
                                                        @endif
                                                    </div>
                                                </div>
                                                <span class="badge bg-light text-dark">
                                                    {{ number_format((float) $assignment->worked_hours, 2) }} hrs
                                                </span>
                                            </div>
                                            @if($assignment->hourly_rate > 0)
                                                <small class="text-muted d-block mt-1">Rate: {{ format_currency((float) $assignment->hourly_rate) }}/hr</small>
                                            @endif
                                            @if($assignment->notes)
                                                <small class="text-muted d-block mt-1">Notes: {{ $assignment->notes }}</small>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted fs-13 border rounded p-3 bg-light">No assignments added yet.</div>
                            @endif
                        </div>

                        @if($workOrder->logs->isNotEmpty())
                            <div class="mb-3">
                                <label class="fs-12 text-muted fw-bold d-block mb-2">Maintenance Log</label>
                                <div class="list-group list-group-flush border rounded">
                                    @foreach($workOrder->logs->sortByDesc('logged_at') as $log)
                                        <div class="list-group-item py-3 fs-13">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <strong class="text-dark">{{ ucfirst(str_replace('_', ' ', $log->event_type)) }}</strong>
                                                <span class="text-muted fs-11">{{ $log->logged_at?->format('d/m H:i') ?? '-' }}</span>
                                            </div>
                                            <div class="text-muted mb-1">{{ $log->summary }}</div>
                                            @if(!empty($log->details) && is_array($log->details))
                                                <ul class="mb-0 ps-3 text-muted mb-0">
                                                    @foreach($log->details as $key => $value)
                                                        @php
                                                            $label = ucwords(str_replace('_', ' ', (string) $key));
                                                            $valueText = is_bool($value) ? ($value ? 'Yes' : 'No') : (is_array($value) ? json_encode($value) : (string) $value);
                                                        @endphp
                                                        <li><span class="fw-semibold text-dark">{{ $label }}:</span> {{ $valueText }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(!empty($workOrder->checklist_json) && is_array($workOrder->checklist_json))
                            <div>
                                <label class="fs-12 text-muted fw-bold d-block mb-2">Maintenance Checklist</label>
                                <ul class="list-group list-group-flush border rounded">
                                    @foreach($workOrder->checklist_json as $idx => $item)
                                        <li class="list-group-item d-flex align-items-center fs-13">
                                            <i class="feather-check-square me-2 text-success"></i> {{ $item }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Spare Parts Management Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                        <h6 class="card-title mb-0 fw-bold text-dark"><i class="feather-box me-2 text-primary"></i>Spare Parts Consumption</h6>
                        @if(!in_array($workOrder->status, ['completed', 'cancelled']))
                            <button type="button" class="btn btn-outline-primary btn-xs" data-bs-toggle="modal" data-bs-target="#addSpareModal">
                                <i class="feather-plus me-1"></i> Add Spare Part
                            </button>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 fs-13">
                                <thead class="bg-light text-muted">
                                    <tr>
                                        <th>Product / Component</th>
                                        <th>{{ __('production.warehouse') }}</th>
                                        <th class="text-center">Requested</th>
                                        <th class="text-center">Issued</th>
                                        <th class="text-end">{{ __('production.unit_cost') }}</th>
                                        <th class="text-end">{{ __('production.total_cost') }}</th>
                                        <th class="text-end">{{ __('production.action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($workOrder->spares as $spare)
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $spare->product?->name }}</div>
                                                <span class="fs-11 text-muted">{{ $spare->product?->sku }}</span>
                                            </td>
                                            <td>{{ $spare->warehouse?->name }}</td>
                                            <td class="text-center fw-bold">{{ number_format($spare->requested_qty, 2) }}</td>
                                            <td class="text-center fw-bold text-{{ $spare->issued_qty > 0 ? 'success' : 'muted' }}">{{ number_format($spare->issued_qty, 2) }}</td>
                                            <td class="text-end">{{ format_currency($spare->unit_cost) }}</td>
                                            <td class="text-end fw-bold">{{ format_currency($spare->total_cost) }}</td>
                                            <td class="text-end">
                                                @if($spare->requested_qty > $spare->issued_qty && !in_array($workOrder->status, ['completed', 'cancelled']))
                                                    <form action="{{ route('production.maintenance.work-orders.issue-spare', $spare->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="issue_qty" value="{{ $spare->requested_qty - $spare->issued_qty }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-success btn-xs">
                                                            <i class="feather-download me-1"></i> Issue Stock
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="badge bg-soft-success text-success">Issued</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">No spare parts requested for this Work Order.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Log Card -->
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                        <h6 class="card-title mb-0 fw-bold text-dark"><i class="feather-activity me-2 text-primary"></i>Maintenance Activity Log</h6>
                        @if($workOrder->downtime_id)
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-xs" data-bs-toggle="modal" data-bs-target="#viewDowntimeLogsModal">
                                    <i class="feather-list me-1"></i> View Logs
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-xs" data-bs-toggle="modal" data-bs-target="#manualDowntimeLogModal">
                                    <i class="feather-plus me-1"></i> Add Manual Log
                                </button>
                            </div>
                        @endif
                    </div>
                    <div class="card-body p-3">
                        @if($workOrder->logs->isNotEmpty())
                            <div class="list-group list-group-flush">
                                @foreach($workOrder->logs->sortByDesc('logged_at')->take(5) as $log)
                                    <div class="list-group-item px-0 py-2">
                                        <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
                                            <strong class="fs-12 text-dark">{{ ucwords(str_replace('_', ' ', $log->event_type)) }}</strong>
                                            <span class="fs-11 text-muted">{{ $log->logged_at?->format('d/m H:i') ?? '-' }}</span>
                                        </div>
                                        <div class="fs-12 text-muted">{{ $log->summary }}</div>
                                        @if(!empty($log->details) && is_array($log->details))
                                            <small class="text-muted d-block mt-1">
                                                @foreach($log->details as $key => $value)
                                                    @php $valueText = is_bool($value) ? ($value ? 'Yes' : 'No') : (is_array($value) ? json_encode($value) : (string) $value); @endphp
                                                    {{ ucfirst(str_replace('_', ' ', (string) $key)) }}: {{ $valueText }}
                                                    @if(!$loop->last) <span class="mx-1">|</span> @endif
                                                @endforeach
                                            </small>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center text-muted py-3 fs-13">No maintenance logs recorded for this work order yet.</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sidebar Summary Cards -->
            <div class="col-lg-4">
                <!-- Timeline & Status Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h6 class="card-title mb-0 fw-bold text-dark"><i class="feather-clock me-2 text-primary"></i>Schedule & Execution Timeline</h6>
                    </div>
                    <div class="card-body p-3 fs-13">
                        <div class="mb-3">
                            <span class="text-muted d-block fs-12">{{ __('production.planned_window') }}</span>
                            <strong>{{ $workOrder->planned_start ? $workOrder->planned_start->format('Y-m-d H:i') : 'Not Scheduled' }}</strong>
                            <span class="text-muted">to</span>
                            <strong>{{ $workOrder->planned_end ? $workOrder->planned_end->format('H:i') : '' }}</strong>
                        </div>
                        <div class="mb-3">
                            <span class="text-muted d-block fs-12">Actual Execution</span>
                            <strong>Start:</strong> {{ $workOrder->actual_start ? $workOrder->actual_start->format('Y-m-d H:i') : '-' }}<br>
                            <strong>End:</strong> {{ $workOrder->actual_end ? $workOrder->actual_end->format('Y-m-d H:i') : '-' }}
                        </div>
                        <div>
                            <span class="text-muted d-block fs-12">Associated Machine Downtime</span>
                            @if($workOrder->downtime)
                                <span class="badge bg-soft-warning text-warning fs-12">Downtime #{{ $workOrder->downtime->id }} ({{ ucfirst($workOrder->downtime->status) }})</span>
                            @else
                                <span class="text-muted">None</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Maintenance Financial Rollup -->
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h6 class="card-title mb-0 fw-bold text-dark"><i class="feather-dollar-sign me-2 text-success"></i>Cost Summary Rollup</h6>
                    </div>
                    <div class="card-body p-3 fs-13">
                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                            <span class="text-muted">Mechanic / Labor Hours:</span>
                            <strong class="text-dark">{{ number_format($workOrder->repair_hours ?: $workOrder->labor_hours, 2) }} hrs</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                            <span class="text-muted">Mechanic Cost:</span>
                            <strong class="text-dark">{{ format_currency($workOrder->mechanic_cost ?: ($workOrder->internal_mechanic_cost + $workOrder->external_mechanic_cost)) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                            <span class="text-muted">Spare Parts Subtotal:</span>
                            <strong class="text-dark">{{ format_currency($workOrder->spare_parts_cost) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                            <span class="text-muted">Additional / Overhead Cost:</span>
                            <strong class="text-dark">{{ format_currency($workOrder->additional_cost) }}</strong>
                        </div>
                        @if($workOrder->external_parts_purchased)
                            <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                                <span class="text-muted">External Parts Purchased:</span>
                                <span class="badge bg-soft-info text-info">Yes</span>
                            </div>
                        @endif
                        @if($workOrder->was_machine_scraped)
                            <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                                <span class="text-muted">Machine Disposal:</span>
                                <span class="badge bg-danger text-white">Scrapped & Decommissioned</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between pt-2">
                            <span class="fw-bold text-dark fs-14">Total Maintenance Cost:</span>
                            <strong class="text-primary fs-16">{{ format_currency($workOrder->total_cost) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Modal -->
    <x-ui.modal id="scheduleModal" title="Schedule Work Order" formAction="{{ route('production.maintenance.work-orders.schedule', $workOrder->id) }}" submitText="Save Schedule">
        <x-ui.odoo-form-ui type="input" inputType="datetime-local" label="Planned Start Date & Time" name="planned_start" :required="true" :value="$workOrder->planned_start?->format('Y-m-d\TH:i')" />
        <x-ui.odoo-form-ui type="input" inputType="datetime-local" label="Planned End Date & Time" name="planned_end" :required="true" :value="$workOrder->planned_end?->format('Y-m-d\TH:i')" />
    </x-ui.modal>

    <!-- Assign Modal -->
    <x-ui.modal id="assignModal" title="Assign People to Work Order" formAction="{{ route('production.maintenance.work-orders.add-assignment', $workOrder->id) }}" submitText="Save Assignments">
        <div x-data="{
            rows: [{ assignment_type: 'internal', technician_id: '', technician_name: '', expected_work_hours: '', hourly_rate: '', notes: '' }],
            addRow() {
                this.rows.push({ assignment_type: 'internal', technician_id: '', technician_name: '', expected_work_hours: '', hourly_rate: '', notes: '' });
            },
            removeRow(index) {
                if (this.rows.length > 1) this.rows.splice(index, 1);
            }
        }">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <strong class="text-dark mb-0">Assigned People</strong>
                    <span class="badge bg-soft-secondary text-secondary border" x-text="rows.length"></span>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" x-on:click="addRow()">
                    <i class="feather-plus me-1"></i> Add Person
                </button>
            </div>

            <template x-for="(row, index) in rows" :key="index">
                <div class="border rounded p-3 mb-3 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <strong class="text-dark">Assignment #<span x-text="index + 1"></span></strong>
                        <button type="button" class="btn btn-sm btn-outline-danger" x-on:click="removeRow(index)" x-show="rows.length > 1">Remove</button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-12 text-muted fw-bold">Assignment Type</label>
                        <select :name="'assignments[' + index + '][assignment_type]'" x-model="row.assignment_type" class="form-select form-select-sm">
                            <option value="internal">Internal / In-House</option>
                            <option value="external">External</option>
                        </select>
                    </div>

                    <template x-if="row.assignment_type === 'internal'">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label fs-12 text-muted fw-bold">Person</label>
                                <select
                                    :name="'assignments[' + index + '][technician_id]'"
                                    x-model="row.technician_id"
                                    x-on:change="
                                        const option = $event.target.selectedOptions[0];
                                        const autoRate = Number(option?.dataset?.rate ?? 0);
                                        row.technician_name = option?.dataset?.name || '';
                                        if (autoRate > 0) {
                                            row.hourly_rate = autoRate;
                                        }
                                    "
                                    class="form-select form-select-sm">
                                    <option value="">Select employee</option>
                                    @foreach($technicians as $tech)
                                        <option value="{{ $tech->id }}" data-name="{{ $tech->name }}" data-rate="{{ $tech->calculated_hourly_rate ?? 0 }}">{{ $tech->name }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" :name="'assignments[' + index + '][technician_name]'" x-model="row.technician_name" />
                            </div>
                        </div>
                    </template>

                    <template x-if="row.assignment_type === 'external'">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label fs-12 text-muted fw-bold">Person</label>
                                <input type="text" :name="'assignments[' + index + '][technician_name]'" x-model="row.technician_name" class="form-control form-control-sm" placeholder="Mechanic name" />
                            </div>
                        </div>
                    </template>

                    <div class="row g-2 mt-2">
                        <div class="col-md-6">
                            <label class="form-label fs-12 text-muted fw-bold">Expected Work Hours</label>
                            <input type="number" step="0.01" min="0" :name="'assignments[' + index + '][expected_work_hours]'" x-model="row.expected_work_hours" class="form-control form-control-sm" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-12 text-muted fw-bold">Hourly Rate ({{ active_currency_symbol() }})</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">{{ active_currency_symbol() }}</span>
                                <input type="number" step="0.01" min="0" :name="'assignments[' + index + '][hourly_rate]'" x-model="row.hourly_rate" class="form-control form-control-sm" placeholder="0.00" />
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fs-12 text-muted fw-bold">Notes</label>
                        <input type="text" :name="'assignments[' + index + '][notes]'" x-model="row.notes" class="form-control form-control-sm" placeholder="Optional notes" />
                    </div>
                </div>
            </template>
        </div>
    </x-ui.modal>

    <!-- Complete Modal -->
    <x-ui.modal id="completeModal" size="lg" title="<span class='text-success fw-bold'><i class='feather-check-circle me-2'></i>Complete Maintenance Work Order</span>" formAction="{{ route('production.maintenance.work-orders.complete', $workOrder->id) }}">
        @php
            $modalAssignments = $workOrder->assignments->sortBy('assigned_at');
            $maintStartIso = $workOrder->actual_start 
                ? $workOrder->actual_start->toISOString() 
                : ($workOrder->planned_start ? $workOrder->planned_start->toISOString() : $workOrder->created_at->toISOString());
        @endphp

        <!-- Captured Completion Timestamp Banner -->
        <div class="alert alert-light border d-flex justify-content-between align-items-center py-2 px-3 mb-3">
            <div class="d-flex align-items-center gap-2">
                <i class="feather-clock text-primary fs-16"></i>
                <span class="fs-12 text-muted fw-semibold">Captured Completion Time:</span>
                <strong class="fs-13 text-dark font-monospace" id="display_completion_time">-</strong>
            </div>
            <span class="badge bg-soft-primary text-primary fs-11">Auto-Captured</span>
        </div>

        <input type="hidden" name="completed_at" id="completed_at_input" value="">
        <input type="hidden" name="was_machine_scraped" id="was_machine_scraped_input" value="0">
        <input type="hidden" name="completion_action" id="completion_action_input" value="restore">

        <!-- Assigned Personnel Table -->
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="fs-12 text-muted fw-bold mb-0">Assigned Personnel & Worked Hours</label>
                <span class="badge bg-light text-dark fs-11">{{ $modalAssignments->count() }} Person(s) Assigned</span>
            </div>

            @if($modalAssignments->isNotEmpty())
                <div class="table-responsive border rounded">
                    <table class="table table-sm table-hover align-middle mb-0 fs-13">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Name</th>
                                <th>Type</th>
                                <th style="width: 145px;">Hourly Rate ($)</th>
                                <th style="width: 140px;">Worked Hours</th>
                                <th class="text-end pe-3" style="width: 110px;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($modalAssignments as $idx => $assignment)
                                @php
                                    $techName = $assignment->assignment_type === 'internal'
                                        ? ($assignment->technician?->name ?? $assignment->technician_name)
                                        : ($assignment->technician_name ?: 'External technician');
                                    $assignedIso = $assignment->assigned_at ? $assignment->assigned_at->toISOString() : $maintStartIso;
                                    $rateVal = (float) ($assignment->hourly_rate > 0 ? $assignment->hourly_rate : 0.00);
                                    $hoursVal = (float) ($assignment->worked_hours > 0 ? $assignment->worked_hours : 0.00);
                                @endphp
                                <tr class="assignment-row"
                                    data-assignment-id="{{ $assignment->id }}"
                                    data-assigned-at="{{ $assignedIso }}"
                                    data-maint-start="{{ $maintStartIso }}">
                                    <td class="ps-3">
                                        <input type="hidden" name="assignments[{{ $idx }}][id]" value="{{ $assignment->id }}">
                                        <div class="fw-semibold text-dark">{{ $techName }}</div>
                                    </td>
                                    <td>
                                        @if($assignment->assignment_type === 'internal')
                                            <span class="badge bg-soft-primary text-primary">Internal</span>
                                        @else
                                            <span class="badge bg-soft-info text-info">External</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">{{ active_currency_symbol() }}</span>
                                            <input type="number"
                                                   step="0.01"
                                                   min="0"
                                                   name="assignments[{{ $idx }}][hourly_rate]"
                                                   class="form-control form-control-sm assignment-rate-input"
                                                   value="{{ number_format(convert_from_base($rateVal), 2, '.', '') }}">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number"
                                                   step="0.01"
                                                   min="0"
                                                   name="assignments[{{ $idx }}][worked_hours]"
                                                   class="form-control form-control-sm assignment-hours-input"
                                                   value="{{ number_format($hoursVal, 2, '.', '') }}">
                                            <span class="input-group-text">hrs</span>
                                        </div>
                                    </td>
                                    <td class="text-end pe-3 fw-bold text-dark assignment-subtotal">
                                        {{ active_currency_symbol() }} {{ number_format(convert_from_base($rateVal) * $hoursVal, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end fw-bold ps-3">Total Mechanic Cost:</td>
                                <td class="text-end pe-3 fw-bold text-primary" id="modalMechanicTotal">{{ active_currency_symbol() }} 0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="alert alert-light border py-2 px-3 fs-12 mb-2 text-muted">
                    <i class="feather-info me-1"></i> No individual personnel assignments recorded. You may record general hours below.
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label fs-12 text-muted fw-bold">Actual Repair Hours</label>
                        <input type="number" step="0.01" min="0" name="repair_hours" class="form-control form-control-sm" value="1.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fs-12 text-muted fw-bold">Mechanic Cost ({{ active_currency_symbol() }})</label>
                        <input type="number" step="0.01" min="0" name="internal_mechanic_cost" class="form-control form-control-sm" value="0.00">
                    </div>
                </div>
            @endif
        </div>

        <!-- Additional Cost / Overhead Expense -->
        <div class="mb-3">
            <label class="form-label fs-12 text-muted fw-bold">Additional Cost / Overhead Expense (Optional)</label>
            <div class="input-group">
                <span class="input-group-text">{{ active_currency_symbol() }}</span>
                <input type="number"
                       step="0.01"
                       min="0"
                       name="additional_cost"
                       id="modal_additional_cost"
                       class="form-control"
                       placeholder="0.00"
                       value="{{ number_format(convert_from_base((float) ($workOrder->additional_cost ?? 0.00)), 2, '.', '') }}">
            </div>
            <small class="text-muted fs-11">Contractor fees, transit, or miscellaneous overhead incurred during maintenance.</small>
        </div>

        <!-- External Parts Purchased -->
        <div class="mb-3 p-3 bg-light rounded border">
            <label class="form-label fs-12 text-muted fw-bold d-block mb-1">External Parts Purchased</label>
            <div class="d-flex align-items-center gap-4">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="external_parts_purchased" id="extPartsNo" value="0" {{ empty($workOrder->external_parts_purchased) ? 'checked' : '' }}>
                    <label class="form-check-label fs-13 text-dark fw-medium" for="extPartsNo">
                        No
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="external_parts_purchased" id="extPartsYes" value="1" {{ !empty($workOrder->external_parts_purchased) ? 'checked' : '' }}>
                    <label class="form-check-label fs-13 text-dark fw-medium" for="extPartsYes">
                        Yes
                    </label>
                </div>
            </div>
            <small class="text-muted fs-11 d-block mt-1">Detail flag only — record whether replacement parts were purchased externally outside standard inventory. Does not create a separate cost.</small>
        </div>

        <!-- Work Performed / Repair Resolution Notes -->
        <div class="mb-3">
            <label class="form-label fs-12 text-muted fw-bold">
                Work Performed / Repair Resolution Notes <span class="text-danger">*</span>
            </label>
            <textarea name="work_performed"
                      id="modal_work_performed"
                      class="form-control"
                      rows="3"
                      required
                      placeholder="Describe actions taken, replaced components, root cause, testing results, and repair resolution...">{{ $workOrder->work_performed }}</textarea>
        </div>

        <!-- Cost Breakdown Live Preview -->
        <div class="card border bg-light mb-2">
            <div class="card-body p-3">
                <div class="row text-center g-2">
                    <div class="col-sm-3 col-6">
                        <span class="text-muted fs-11 d-block">Mechanic Cost</span>
                        <strong class="text-dark fs-13" id="modalMechanicCostSummary">{{ active_currency_symbol() }} 0.00</strong>
                    </div>
                    <div class="col-sm-3 col-6 border-start-sm">
                        <span class="text-muted fs-11 d-block">Spare Parts Cost</span>
                        <strong class="text-dark fs-13" id="modalSparesCostSummary">{{ format_currency($workOrder->spare_parts_cost) }}</strong>
                    </div>
                    <div class="col-sm-3 col-6 border-start-sm">
                        <span class="text-muted fs-11 d-block">Additional Cost</span>
                        <strong class="text-dark fs-13" id="modalAdditionalCostSummary">{{ active_currency_symbol() }} 0.00</strong>
                    </div>
                    <div class="col-sm-3 col-6 border-start-sm">
                        <span class="text-muted fs-11 d-block fw-bold">Total Cost</span>
                        <strong class="text-primary fs-14 fw-bold" id="modalTotalCostSummary">{{ format_currency($workOrder->total_cost) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer: Exactly Cancel, Complete & Restore, Complete & Scrap -->
        <x-slot name="footer">
            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success fw-bold" id="completeRestoreBtn">
                <i class="feather-check-circle me-1"></i>Complete & Restore
            </button>
            <button type="button" class="btn btn-danger fw-bold" id="completeScrapBtn">
                <i class="feather-trash-2 me-1"></i>Complete & Scrap
            </button>
        </x-slot>
    </x-ui.modal>

    <!-- Add Spare Modal -->
    <x-ui.modal id="addSpareModal" title="Add Spare Part Request" formAction="{{ route('production.maintenance.work-orders.add-spare', $workOrder->id) }}" submitText="Add Request">
        <x-ui.odoo-form-ui type="select" label="Product / Spare Part" name="product_id" :required="true">
            <option value="">Select Spare Part</option>
            @foreach($products as $p)
                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="select" label="Warehouse" name="warehouse_id" :required="true">
            <option value="">Select Warehouse</option>
            @foreach($warehouses as $wh)
                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="input" inputType="number" step="1" label="Requested Quantity" name="requested_qty" :required="true" value="1" />
    </x-ui.modal>

    @if($workOrder->downtime_id)
        <x-ui.modal id="viewDowntimeLogsModal" title="Maintenance Logs" formAction="#" submitText="Close" modalType="dialog">
            @if($workOrder->logs->isNotEmpty())
                <div class="list-group list-group-flush">
                    @foreach($workOrder->logs->sortByDesc('logged_at') as $log)
                        <div class="list-group-item px-0 py-3">
                            <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
                                <strong class="fs-12 text-dark">{{ ucwords(str_replace('_', ' ', $log->event_type)) }}</strong>
                                <span class="fs-11 text-muted">{{ $log->logged_at?->format('d/m/Y H:i') ?? '-' }}</span>
                            </div>
                            <div class="fs-12 text-dark mb-1">{{ $log->summary }}</div>
                            @if(!empty($log->details) && is_array($log->details))
                                <ul class="mb-0 ps-3 text-muted fs-12">
                                    @foreach($log->details as $key => $value)
                                        @php $valueText = is_bool($value) ? ($value ? 'Yes' : 'No') : (is_array($value) ? json_encode($value) : (string) $value); @endphp
                                        <li><span class="fw-semibold text-dark">{{ ucfirst(str_replace('_', ' ', (string) $key)) }}:</span> {{ $valueText }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center text-muted py-3 fs-13">No maintenance logs have been recorded yet.</div>
            @endif
        </x-ui.modal>

        <x-ui.modal id="manualDowntimeLogModal" title="Add Manual Downtime Log" formAction="{{ route('production.maintenance.work-orders.store-downtime-log', $workOrder->id) }}" submitText="Save Log">
            <x-ui.odoo-form-ui type="select" label="Action Type" name="action_type" id="downtimeActionType" :required="true">
                <option value="inspection">Inspection</option>
                <option value="repair">Repair</option>
                <option value="adjustment">Adjustment</option>
                <option value="cleaning">Cleaning</option>
                <option value="tool_change">Tool Change</option>
                <option value="other">Other</option>
            </x-ui.odoo-form-ui>

            <div id="otherDowntimeActionGroup" class="mb-3" style="display:none;">
                <x-ui.odoo-form-ui type="input" inputType="text" label="Other Action" name="other_action" placeholder="Describe the manual downtime action" />
            </div>

            <x-ui.odoo-form-ui type="textarea" label="Log Details / Notes" name="details" rows="4" :required="true" placeholder="Describe what was checked, repaired, cleaned, or adjusted during downtime." />
        </x-ui.modal>
    @endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const completeModal = document.getElementById('completeModal');
        if (completeModal) {
            let capturedCompletionTime = null;
            const completedAtInput = document.getElementById('completed_at_input');
            const wasMachineScrapedInput = document.getElementById('was_machine_scraped_input');
            const completionActionInput = document.getElementById('completion_action_input');
            const displayCompletionTime = document.getElementById('display_completion_time');
            const activeCurrencySymbol = @json(active_currency_symbol());
            const additionalCostInput = document.getElementById('modal_additional_cost');
            const sparesCost = parseFloat("{{ (float) convert_from_base($workOrder->spare_parts_cost) }}") || 0;

            function recalculateCosts() {
                let mechanicCost = 0;
                const rows = completeModal.querySelectorAll('.assignment-row');
                rows.forEach(function (row) {
                    const rateInput = row.querySelector('.assignment-rate-input');
                    const hoursInput = row.querySelector('.assignment-hours-input');
                    const subtotalEl = row.querySelector('.assignment-subtotal');
                    const rate = parseFloat(rateInput?.value) || 0;
                    const hours = parseFloat(hoursInput?.value) || 0;
                    const subtotal = Math.round(rate * hours * 100) / 100;
                    if (subtotalEl) {
                        subtotalEl.textContent = activeCurrencySymbol + ' ' + subtotal.toFixed(2);
                    }
                    mechanicCost += subtotal;
                });

                const mechanicTotalEl = document.getElementById('modalMechanicTotal');
                if (mechanicTotalEl) {
                    mechanicTotalEl.textContent = activeCurrencySymbol + ' ' + mechanicCost.toFixed(2);
                }

                const additionalCost = parseFloat(additionalCostInput?.value) || 0;
                const totalCost = Math.round((mechanicCost + sparesCost + additionalCost) * 100) / 100;

                const summaryMechanic = document.getElementById('modalMechanicCostSummary');
                if (summaryMechanic) summaryMechanic.textContent = activeCurrencySymbol + ' ' + mechanicCost.toFixed(2);

                const summaryAdditional = document.getElementById('modalAdditionalCostSummary');
                if (summaryAdditional) summaryAdditional.textContent = activeCurrencySymbol + ' ' + additionalCost.toFixed(2);

                const summaryTotal = document.getElementById('modalTotalCostSummary');
                if (summaryTotal) summaryTotal.textContent = activeCurrencySymbol + ' ' + totalCost.toFixed(2);
            }

            // Capture timestamp and calculate initial worked hours on modal opening
            completeModal.addEventListener('show.bs.modal', function () {
                capturedCompletionTime = new Date();
                const isoString = capturedCompletionTime.toISOString();
                if (completedAtInput) {
                    completedAtInput.value = isoString;
                }

                if (displayCompletionTime) {
                    const pad = (n) => String(n).padStart(2, '0');
                    const yyyy = capturedCompletionTime.getFullYear();
                    const mm = pad(capturedCompletionTime.getMonth() + 1);
                    const dd = pad(capturedCompletionTime.getDate());
                    const hh = pad(capturedCompletionTime.getHours());
                    const min = pad(capturedCompletionTime.getMinutes());
                    const ss = pad(capturedCompletionTime.getSeconds());
                    displayCompletionTime.textContent = `${yyyy}-${mm}-${dd} ${hh}:${min}:${ss}`;
                }

                const rows = completeModal.querySelectorAll('.assignment-row');
                rows.forEach(function (row) {
                    const maintStartStr = row.dataset.maintStart;
                    const assignedAtStr = row.dataset.assignedAt;

                    const maintStartMs = maintStartStr ? new Date(maintStartStr).getTime() : capturedCompletionTime.getTime();
                    const assignedAtMs = assignedAtStr ? new Date(assignedAtStr).getTime() : maintStartMs;

                    const effectiveStartMs = Math.max(maintStartMs, assignedAtMs);
                    const diffMs = capturedCompletionTime.getTime() - effectiveStartMs;
                    const initialHours = Math.max(0, diffMs / (1000 * 60 * 60));

                    const hoursInput = row.querySelector('.assignment-hours-input');
                    if (hoursInput) {
                        hoursInput.value = initialHours.toFixed(2);
                    }
                });

                recalculateCosts();
            });

            // Discard timestamp when modal is cancelled/closed
            completeModal.addEventListener('hidden.bs.modal', function () {
                capturedCompletionTime = null;
                if (completedAtInput) {
                    completedAtInput.value = '';
                }
                if (displayCompletionTime) {
                    displayCompletionTime.textContent = '-';
                }
            });

            // Dynamic live cost updates on user input
            completeModal.addEventListener('input', function (e) {
                if (e.target.matches('.assignment-rate-input, .assignment-hours-input, #modal_additional_cost')) {
                    recalculateCosts();
                }
            });

            // Complete & Restore
            const restoreBtn = document.getElementById('completeRestoreBtn');
            if (restoreBtn) {
                restoreBtn.addEventListener('click', function () {
                    const form = completeModal.querySelector('form');
                    if (!form) return;

                    if (wasMachineScrapedInput) wasMachineScrapedInput.value = '0';
                    if (completionActionInput) completionActionInput.value = 'restore';

                    if (!form.reportValidity()) {
                        return;
                    }

                    form.submit();
                });
            }

            // Complete & Scrap
            const scrapBtn = document.getElementById('completeScrapBtn');
            if (scrapBtn) {
                scrapBtn.addEventListener('click', function () {
                    const form = completeModal.querySelector('form');
                    if (!form) return;

                    if (!confirm('Are you sure you want to Complete this Maintenance and SCRAP the machine? The machine will be decommissioned and taken out of service.')) {
                        return;
                    }

                    if (wasMachineScrapedInput) wasMachineScrapedInput.value = '1';
                    if (completionActionInput) completionActionInput.value = 'scrap';

                    if (!form.reportValidity()) {
                        return;
                    }

                    form.submit();
                });
            }
        }

        const downtimeActionType = document.getElementById('downtimeActionType');
        const otherDowntimeActionGroup = document.getElementById('otherDowntimeActionGroup');

        function toggleOtherDowntimeAction() {
            if (!downtimeActionType || !otherDowntimeActionGroup) {
                return;
            }

            otherDowntimeActionGroup.style.display = downtimeActionType.value === 'other' ? 'block' : 'none';
        }

        if (downtimeActionType) {
            downtimeActionType.addEventListener('change', toggleOtherDowntimeAction);
            toggleOtherDowntimeAction();
        }
    });
</script>
@endpush
