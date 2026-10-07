@extends('layouts.duralux')

@section('title', 'Work Order ' . $workOrder->work_order_number . ' | SaaS ERP')
@section('page-title', 'Work Order Execution')
@section('breadcrumb', 'Work Order Detail')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        @if($workOrder->status === 'draft')
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                <i class="feather-calendar me-2"></i>Schedule
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

        @if(!in_array($workOrder->status, ['completed', 'cancelled']))
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
                                            <td class="text-end">${{ number_format($spare->unit_cost, 2) }}</td>
                                            <td class="text-end fw-bold">${{ number_format($spare->total_cost, 2) }}</td>
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
                            <span class="text-muted">Labor Hours:</span>
                            <strong class="text-dark">{{ number_format($workOrder->labor_hours, 2) }} hrs</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                            <span class="text-muted">Labor Rate:</span>
                            <strong class="text-dark">${{ number_format($workOrder->labor_cost_rate, 2) }} / hr</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                            <span class="text-muted">Labor Subtotal:</span>
                            <strong class="text-dark">${{ number_format($workOrder->labor_cost, 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                            <span class="text-muted">Spare Parts Subtotal:</span>
                            <strong class="text-dark">${{ number_format($workOrder->spare_parts_cost, 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-2">
                            <span class="fw-bold text-dark fs-14">Total Maintenance Cost:</span>
                            <strong class="text-primary fs-16">${{ number_format($workOrder->total_cost, 2) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Modal -->
    <x-ui.modal id="scheduleModal" title="Schedule Work Order" formAction="{{ route('production.maintenance.work-orders.schedule', $workOrder->id) }}" submitText="Save Schedule">
        <x-ui.odoo-form-ui type="select" label="Assigned Technician" name="assigned_technician_id">
            <option value="">Unassigned</option>
            @foreach($technicians as $tech)
                <option value="{{ $tech->id }}" @selected($workOrder->assigned_technician_id == $tech->id)>{{ $tech->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="input" inputType="datetime-local" label="Planned Start Date & Time" name="planned_start" :required="true" :value="$workOrder->planned_start?->format('Y-m-d\TH:i')" />
        <x-ui.odoo-form-ui type="input" inputType="datetime-local" label="Planned End Date & Time" name="planned_end" :required="true" :value="$workOrder->planned_end?->format('Y-m-d\TH:i')" />
    </x-ui.modal>

    <!-- Complete Modal -->
    <x-ui.modal id="completeModal" title="<span class='text-success fw-bold'><i class='feather-check-circle me-2'></i>Complete Maintenance</span>" formAction="{{ route('production.maintenance.work-orders.complete', $workOrder->id) }}" submitText="Complete & Restore Machine">
        <x-ui.odoo-form-ui type="input" inputType="number" step="0.1" label="Actual Repair Hours Spent" name="repair_hours" :required="true" :value="$workOrder->repair_hours ?: $workOrder->labor_hours ?: 1.0" />
        <x-ui.odoo-form-ui type="select" label="Mechanic Type" name="mechanic_type" id="mechanicTypeSelect">
            <option value="inhouse" @selected(($workOrder->mechanic_type ?? 'inhouse') === 'inhouse')>INHOUSE</option>
            <option value="external" @selected(($workOrder->mechanic_type ?? 'inhouse') === 'external')>EXTERNAL</option>
            <option value="both" @selected(($workOrder->mechanic_type ?? 'inhouse') === 'both')>BOTH</option>
        </x-ui.odoo-form-ui>
        <div id="externalMechanicCostGroup" class="mb-3" style="display:none;">
            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="External Mechanic Cost ($)" name="external_mechanic_cost" :value="0.00" />
        </div>
        <div id="internalMechanicCostGroup" class="mb-3" style="display:none;">
            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="Internal Mechanic / Engineer Cost ($)" name="internal_mechanic_cost" :value="0.00" />
        </div>
        <x-ui.odoo-form-ui type="checkbox" label="Scrap machine instead of repairing it" name="scrap_machine" value="1" />
        <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="Scrap / Broken Asset Value ($)" name="scrap_value" :value="0.00" />
        <x-ui.odoo-form-ui type="textarea" label="Work Performed / Repair Resolution Notes" name="work_performed" rows="4" :required="true" placeholder="Describe actions taken, replaced components, testing results..." />
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
        const mechanicTypeSelect = document.getElementById('mechanicTypeSelect');
        const externalGroup = document.getElementById('externalMechanicCostGroup');
        const internalGroup = document.getElementById('internalMechanicCostGroup');

        function toggleMechanicCostFields() {
            if (!mechanicTypeSelect || !externalGroup || !internalGroup) {
                return;
            }

            const value = mechanicTypeSelect.value;
            const showExternal = value === 'external' || value === 'both';
            const showInternal = value === 'inhouse' || value === 'both';

            externalGroup.style.display = showExternal ? 'block' : 'none';
            internalGroup.style.display = showInternal ? 'block' : 'none';
        }

        if (mechanicTypeSelect) {
            mechanicTypeSelect.addEventListener('change', toggleMechanicCostFields);
            toggleMechanicCostFields();
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
