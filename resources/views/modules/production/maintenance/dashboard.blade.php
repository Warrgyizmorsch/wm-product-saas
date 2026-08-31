@extends('layouts.duralux')

@section('title', 'Plant Maintenance Dashboard | SaaS ERP')
@section('page-title', 'Plant Maintenance Dashboard')
@section('breadcrumb', 'Plant Maintenance')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <form action="{{ route('production.maintenance.schedules.generate-work-orders') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary">
                <i class="feather-refresh-cw me-2"></i>Generate Due WOs
            </button>
        </form>
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reportBreakdownModal">
            <i class="feather-alert-octagon me-2"></i>Report Breakdown
        </button>
        <a href="{{ route('production.maintenance.schedules.create') }}" class="btn btn-outline-secondary">
            <i class="feather-plus me-2"></i>New PM Schedule
        </a>
        <a href="{{ route('production.maintenance.work-orders.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>New Work Order
        </a>
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

        <!-- KPI Summary Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="avatar avatar-md bg-soft-warning text-warning rounded-circle me-3 d-flex align-items-center justify-content-center">
                            <i class="feather-tool fs-18"></i>
                        </div>
                        <div>
                            <span class="fs-12 text-muted d-block">Under Maintenance</span>
                            <h4 class="mb-0 fw-bold text-dark">{{ $machinesUnderMaintenance }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="avatar avatar-md bg-soft-danger text-danger rounded-circle me-3 d-flex align-items-center justify-content-center">
                            <i class="feather-alert-triangle fs-18"></i>
                        </div>
                        <div>
                            <span class="fs-12 text-muted d-block">Open Breakdown WOs</span>
                            <h4 class="mb-0 fw-bold text-dark">{{ $openBreakdownWos }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="avatar avatar-md bg-soft-info text-info rounded-circle me-3 d-flex align-items-center justify-content-center">
                            <i class="feather-calendar fs-18"></i>
                        </div>
                        <div>
                            <span class="fs-12 text-muted d-block">PM Due / Overdue</span>
                            <h4 class="mb-0 fw-bold text-dark">{{ $dueSchedules }} <span class="fs-12 text-danger">({{ $overdueSchedules }} Overdue)</span></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="avatar avatar-md bg-soft-success text-success rounded-circle me-3 d-flex align-items-center justify-content-center">
                            <i class="feather-dollar-sign fs-18"></i>
                        </div>
                        <div>
                            <span class="fs-12 text-muted d-block">Monthly Expense</span>
                            <h4 class="mb-0 fw-bold text-dark">${{ number_format($monthlyExpense, 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Tables Row -->
        <div class="row g-3">
            <!-- Recent Work Orders -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                        <h6 class="card-title mb-0 fw-bold text-dark"><i class="feather-file-text me-2 text-primary"></i>Recent Maintenance Work Orders</h6>
                        <a href="{{ route('production.maintenance.work-orders.index') }}" class="fs-12 text-primary fw-bold">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 fs-13">
                                <thead class="bg-light text-muted">
                                    <tr>
                                        <th>WO Number</th>
                                        <th>Machine</th>
                                        <th>Type</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th class="text-end">Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentWorkOrders as $wo)
                                        <tr>
                                            <td>
                                                <a href="{{ route('production.maintenance.work-orders.show', $wo->id) }}" class="fw-bold text-primary">
                                                    {{ $wo->work_order_number }}
                                                </a>
                                            </td>
                                            <td>{{ $wo->machine?->name ?? '-' }}</td>
                                            <td>
                                                <span class="badge bg-soft-{{ $wo->type === 'breakdown' ? 'danger' : 'info' }} text-{{ $wo->type === 'breakdown' ? 'danger' : 'info' }}">
                                                    {{ ucfirst($wo->type) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-soft-{{ $wo->priority === 'critical' ? 'danger' : ($wo->priority === 'high' ? 'warning' : 'secondary') }} text-dark">
                                                    {{ ucfirst($wo->priority) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-soft-{{ $wo->status === 'completed' ? 'success' : ($wo->status === 'in_progress' ? 'warning' : 'secondary') }} text-dark">
                                                    {{ ucfirst(str_replace('_', ' ', $wo->status)) }}
                                                </span>
                                            </td>
                                            <td class="text-end fw-bold">${{ number_format($wo->total_cost, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No recent maintenance work orders found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming PM Schedules -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                        <h6 class="card-title mb-0 fw-bold text-dark"><i class="feather-clock me-2 text-primary"></i>Upcoming PM Schedules</h6>
                        <a href="{{ route('production.maintenance.schedules.index') }}" class="fs-12 text-primary fw-bold">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 fs-13">
                                <thead class="bg-light text-muted">
                                    <tr>
                                        <th>Schedule</th>
                                        <th>Machine</th>
                                        <th>Next Due</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($duePmList as $pm)
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $pm->name }}</div>
                                                <span class="fs-11 text-muted">{{ $pm->code }} (Every {{ $pm->frequency_value }} {{ $pm->frequency_type }})</span>
                                            </td>
                                            <td>{{ $pm->machine?->name ?? '-' }}</td>
                                            <td>
                                                <span class="{{ $pm->isOverdue() ? 'text-danger fw-bold' : ($pm->isDue() ? 'text-warning fw-bold' : 'text-muted') }}">
                                                    {{ $pm->next_due_date ? $pm->next_due_date->format('Y-m-d') : '-' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-4">No upcoming PM schedules.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Breakdown Modal Component -->
    <x-ui.modal id="reportBreakdownModal" title="<span class='text-danger fw-bold'><i class='feather-alert-octagon me-2'></i>Report Emergency Breakdown</span>" formAction="{{ route('production.maintenance.work-orders.breakdown') }}" submitText="Report & Start Breakdown WO">
        <x-ui.odoo-form-ui type="select" label="Machine" name="machine_id" :required="true">
            <option value="">Select Machine</option>
            @foreach(\App\Domains\Production\Models\Machine::all() as $m)
                <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->code }})</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="select" label="Priority" name="priority" :required="true">
            <option value="high" selected>High</option>
            <option value="critical">Critical</option>
            <option value="medium">Medium</option>
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="textarea" label="Breakdown Description / Reason" name="reason" rows="3" :required="true" placeholder="Describe the failure, error codes, or abnormal sounds..." />
    </x-ui.modal>
@endsection
