@extends('layouts.duralux')

@section('title', 'What-If Schedule Scenarios | SaaS ERP')
@section('page-title', 'What-If Schedule Scenarios')
@section('breadcrumb', 'Schedule Scenarios')

@section('page-actions')
    <a href="{{ route('production.schedules.dispatch-board') }}" class="btn btn-outline-primary me-2">
        <i class="feather-calendar me-1"></i> Live Dispatch Board
    </a>
    <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createScenarioModal">
        <i class="feather-plus"></i> New What-If Scenario
    </button>
@endsection

@section('content')
<div class="erp-single-panel">
    <div class="d-flex align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-1">Schedule Scenarios Workspace</h5>
            <p class="text-muted small mb-0">Create, simulate, compare, and promote isolated production planning experiments.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase">
                <tr>
                    <th class="ps-4">Scenario Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Created At</th>
                    <th>Summary KPIs</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($scenarios as $scenario)
                    <tr>
                        <td class="ps-4 fw-bold">
                            <span class="text-dark">{{ $scenario->name }}</span>
                            @if($scenario->description)
                                <div class="small text-muted font-normal">{{ Str::limit($scenario->description, 50) }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-soft-info text-info font-normal">
                                {{ str_replace('_', ' ', ucfirst($scenario->scenario_type)) }}
                            </span>
                        </td>
                        <td>
                            @if($scenario->status === 'promoted')
                                <span class="badge bg-success">Promoted</span>
                            @elseif($scenario->status === 'calculated')
                                <span class="badge bg-primary">Calculated</span>
                            @elseif($scenario->status === 'discarded')
                                <span class="badge bg-secondary">Discarded</span>
                            @else
                                <span class="badge bg-warning text-dark">Draft</span>
                            @endif
                        </td>
                        <td class="small">{{ $scenario->creator?->name ?? 'System' }}</td>
                        <td class="small text-muted">{{ $scenario->created_at->format('Y-m-d H:i') }}</td>
                        <td class="small">
                            @if($scenario->summary)
                                <div><span class="fw-semibold">Makespan:</span> {{ round(($scenario->summary['makespan_minutes'] ?? 0) / 60, 1) }}h</div>
                                <div><span class="fw-semibold">Conflicts:</span> {{ $scenario->summary['conflicts'] ?? 0 }}</div>
                            @else
                                <span class="text-muted italic">Not Calculated</span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('production.schedules.dispatch-board', ['scenario_id' => $scenario->id]) }}" class="btn btn-outline-primary" title="Open in Dispatch Board">
                                    <i class="feather-eye me-1"></i> Open Board
                                </a>
                                @if(!$scenario->isPromoted() && !$scenario->isDiscarded())
                                    <button type="button" class="btn btn-outline-success" onclick="promoteScenario({{ $scenario->id }}, '{{ addslashes($scenario->name) }}')" title="Promote Scenario to Live">
                                        <i class="feather-check-circle me-1"></i> Promote
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="discardScenario({{ $scenario->id }}, '{{ addslashes($scenario->name) }}')" title="Discard Scenario">
                                        <i class="feather-trash-2 me-1"></i> Discard
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            No What-If scenarios created yet. Click <strong>New What-If Scenario</strong> to start planning experiments.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Create New What-If Scenario using x-ui.modal component -->
<x-ui.modal
    id="createScenarioModal"
    title='<i class="feather-layers text-primary me-2"></i> Create What-If Schedule Scenario'
    size="lg"
    formAction="{{ route('production.schedules.scenarios.store') }}"
    formMethod="POST"
    submitText="Create & Snapshot Scenario"
    closeText="Cancel"
>
    <div class="mb-3">
        <label for="name" class="form-label fw-semibold">Scenario Name <span class="text-danger">*</span></label>
        <x-ui.odoo-form-ui type="input" id="name" name="name" placeholder="e.g. CNC-01 Breakdown Simulation — Aug 15" required />
    </div>

    <div class="mb-3">
        <label for="description" class="form-label fw-semibold">Description / Purpose</label>
        <x-ui.odoo-form-ui type="textarea" id="description" name="description" rows="2" placeholder="Briefly describe the scenario hypothesis or objective..." />
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label for="scenario_type" class="form-label fw-semibold">Scenario Type</label>
            <x-ui.odoo-form-ui type="select" id="scenario_type" name="scenario_type">
                <option value="custom">Custom Experiment</option>
                <option value="machine_downtime">Machine Breakdown / Maintenance</option>
                <option value="rush_order">Rush Order Insertion</option>
                <option value="priority_change">Priority Shift</option>
                <option value="capacity_change">Capacity Extension</option>
            </x-ui.odoo-form-ui>
        </div>
        <div class="col-md-6">
            <label for="start_date" class="form-label fw-semibold">Scope Start Date</label>
            <x-ui.odoo-form-ui type="input" inputType="date" id="start_date" name="start_date" value="{{ date('Y-m-d') }}" />
        </div>
    </div>

    <div class="alert alert-info small mb-0">
        <i class="feather-info me-1"></i> Creating a scenario snapshots current live operations non-destructively. Live production schedules remain untouched until explicit promotion.
    </div>
</x-ui.modal>

<script>
function promoteScenario(scenarioId, scenarioName) {
    confirmAction({
        title: 'Promote What-If Scenario',
        message: `Are you sure you want to promote scenario "${scenarioName}" to the live production schedule? This action will overwrite live operation timings.`,
        confirmButtonText: 'Promote to Live',
        variant: 'success',
        onConfirm: function() {
            fetch(`/production/schedules/scenarios/${scenarioId}/promote`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAppToast('success', data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showAppToast('error', 'Promotion Error: ' + data.message);
                }
            })
            .catch(err => {
                showAppToast('error', 'Failed to promote scenario: ' + err);
            });
        }
    });
}

function discardScenario(scenarioId, scenarioName) {
    confirmAction({
        title: 'Discard What-If Scenario',
        message: `Are you sure you want to discard scenario "${scenarioName}"?`,
        confirmButtonText: 'Discard Scenario',
        variant: 'danger',
        onConfirm: function() {
            fetch(`/production/schedules/scenarios/${scenarioId}/discard`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAppToast('success', 'Scenario discarded successfully');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showAppToast('error', 'Error: ' + data.message);
                }
            })
            .catch(err => {
                showAppToast('error', 'Failed to discard scenario: ' + err);
            });
        }
    });
}
</script>
@endsection
