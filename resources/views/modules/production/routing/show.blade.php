@extends('layouts.duralux')

@section('title', 'Routing Details | SaaS ERP')
@section('page-title', 'Routing Details')
@section('breadcrumb', $routing->routing_number)

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('production.routing.index') }}" class="btn btn-secondary">
            <i class="feather-arrow-left me-2"></i>Back to List
        </a>
        
        @if ($routing->isDraft())
            @can('update', $routing)
                <a href="{{ route('production.routing.edit', $routing->id) }}" class="btn btn-primary">
                    <i class="feather-edit me-2"></i>Edit Draft
                </a>
            @endcan
        @endif

        @can('duplicate', $routing)
            <button type="button" class="btn btn-light-brand" data-bs-toggle="modal" data-bs-target="#duplicateVersionModal">
                <i class="feather-copy me-2"></i>Create New Version
            </button>
        @endcan
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        <!-- Success & Error Alerts -->
        @if (session('success'))
            <x-ui.alert variant="success" icon="feather-check-circle" dismissible>
                <h6 class="alert-heading fw-bold mb-1">Success!</h6>
                <p class="fs-12 mb-0">{{ session('success') }}</p>
            </x-ui.alert>
            <div class="mb-4"></div>
        @endif

        @if (session('error'))
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                <p class="fs-12 mb-0">{{ session('error') }}</p>
            </x-ui.alert>
            <div class="mb-4"></div>
        @endif

        <!-- Workflow Banner (Inlined inside panel) -->
        <div class="p-3 border rounded bg-light mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-brand text-white me-3 rounded-circle" style="width: 40px; height: 40px; font-size: 16px; display: flex; align-items: center; justify-content: center;">
                    <i class="feather-info"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">Process Routing Lifecycle Status</h6>
                    <p class="fs-12 mb-0 text-muted">
                        Current Status: 
                        @if ($routing->isDraft())
                            <span class="badge bg-soft-secondary text-secondary font-monospace fw-bold text-uppercase">Draft</span>
                            — Open for editing. Fill out operation sequences before submitting for approval.
                        @elseif ($routing->isPendingApproval())
                            <span class="badge bg-soft-warning text-warning font-monospace fw-bold text-uppercase">Pending Approval</span>
                            — Locked for review by Production Engineering & Managers.
                        @elseif ($routing->isActive())
                            <span class="badge bg-soft-success text-success font-monospace fw-bold text-uppercase">Active / Released</span>
                            — Currently used in shop-floor production orders and costing templates.
                        @elseif ($routing->isHistorical())
                            <span class="badge bg-soft-info text-info font-monospace fw-bold text-uppercase">Historical / Archived</span>
                            — Replaced by a newer version. Kept for retrospective audit trail.
                        @else
                            <span class="badge bg-soft-danger text-danger font-monospace fw-bold text-uppercase">Cancelled</span>
                            — Process terminated. Cannot be used for scheduling or orders.
                        @endif
                    </p>
                </div>
            </div>

            <!-- Workflow Actions -->
            <div class="d-flex gap-2">
                @if ($routing->isDraft())
                    @can('submit', $routing)
                        <form action="{{ route('production.routing.submit', $routing->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-sm px-3">
                                <i class="feather-send me-1"></i>Submit for Approval
                            </button>
                        </form>
                    @endcan
                @endif

                @if ($routing->isPendingApproval())
                    @can('approve', $routing)
                        <form action="{{ route('production.routing.approve', $routing->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm px-3" onclick="return confirm('Approving this routing will automatically archive the previous active version. Proceed?');">
                                <i class="feather-check-circle me-1"></i>Approve & Activate
                            </button>
                        </form>
                    @endcan

                    @can('reject', $routing)
                        <button type="button" class="btn btn-danger btn-sm px-3" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="feather-x-circle me-1"></i>Reject
                        </button>
                    @endcan
                @endif

                @if (!$routing->isCancelled() && !$routing->isHistorical())
                    @can('cancel', $routing)
                        <button type="button" class="btn btn-outline-danger btn-sm px-3" data-bs-toggle="modal" data-bs-target="#cancelModal">
                            <i class="feather-slash me-1"></i>Cancel Routing
                        </button>
                    @endcan
                @endif
            </div>
        </div>

        <!-- Summary Statistics Layout -->
        <div class="row g-4 mb-4 pb-4 border-bottom">
            <!-- Left Info Panel -->
            <div class="col-md-4 border-end">
                <div class="text-center py-2">
                    <div class="avatar-text avatar-xl bg-soft-primary text-primary mx-auto mb-3 rounded-circle" style="width: 70px; height: 70px; font-size: 28px; display: flex; align-items: center; justify-content: center;">
                        <i class="feather-git-pull-request"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">{{ $routing->name }}</h4>
                    <span class="fs-13 fw-semibold text-muted text-uppercase">{{ $routing->routing_number }}</span>
                    <div class="mt-2">
                        @if ($routing->is_default)
                            <span class="badge bg-soft-success text-success px-3 py-1 rounded-pill">Primary Route</span>
                        @else
                            <span class="badge bg-soft-warning text-warning px-3 py-1 rounded-pill">Alternative Route</span>
                        @endif
                    </div>
                </div>

                <div class="d-flex flex-column gap-3 mt-4 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">Manufactured Item:</span>
                        <span class="fw-bold text-dark text-end">
                            @if ($routing->product)
                                {{ $routing->product->name }}
                                <small class="text-muted d-block fs-11">{{ $routing->product->sku }}</small>
                            @else
                                <span class="text-danger">Not specified</span>
                            @endif
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">Version Profile:</span>
                        <span class="fw-semibold text-dark">{{ $routing->version }} (Rev {{ $routing->revision }})</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">Effective Range:</span>
                        <span class="fw-semibold text-dark text-end fs-12">
                            {{ $routing->effective_from ? $routing->effective_from->format('Y-m-d') : 'Immediate' }}
                            to
                            {{ $routing->effective_to ? $routing->effective_to->format('Y-m-d') : 'Indefinite' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Time Metrics -->
            <div class="col-md-4 border-end">
                <h5 class="fw-bold text-dark mb-3">Time & Process Statistics</h5>
                <div class="d-flex flex-column gap-4 py-2 px-2">
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">Total Sequence Operations</span>
                        <span class="fs-22 fw-bold text-dark">{{ $routing->operations->count() }} Stage(s)</span>
                    </div>
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">Setup Duration Estimate</span>
                        <span class="fs-22 fw-bold text-dark">{{ number_format($routing->operations->sum('setup_time_minutes'), 1) }} <span class="fs-13 fw-normal text-muted">Minutes</span></span>
                    </div>
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">Total Cycle Running Time</span>
                        <span class="fs-18 fw-bold text-success">{{ number_format($routing->totalCycleTimeMinutes(), 1) }} <span class="fs-13 fw-normal text-muted">Minutes</span></span>
                        <small class="text-muted d-block mt-1">Includes setup, processing, and queuing/wait times.</small>
                    </div>
                </div>
            </div>

            <!-- Cost Estimates -->
            <div class="col-md-4">
                <h5 class="fw-bold text-dark mb-3">Process Cost Predictions (Qty: 1.0)</h5>
                @if ($costSummary)
                    <div class="d-flex flex-column gap-4 py-2 px-2">
                        <div>
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">Routing Cost Per Unit</span>
                            <span class="fs-24 fw-bold text-primary">${{ number_format($costSummary['total_cost'], 4) }}</span>
                            <small class="text-muted d-block mt-1">Adjusted labor and machine rates based on yield percentages.</small>
                        </div>
                        
                        <div class="border-top pt-3 mt-2">
                            <h6 class="fw-bold text-dark mb-2">Cost Contribution Details</h6>
                            <div class="d-flex justify-content-between align-items-center mb-1 fs-12">
                                <span class="text-muted">Labor Expense:</span>
                                <span class="fw-semibold text-dark">${{ number_format(collect($costSummary['operations'])->sum('labor_cost'), 4) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center fs-12">
                                <span class="text-muted">Machine Overhead:</span>
                                <span class="fw-semibold text-dark">${{ number_format(collect($costSummary['operations'])->sum('machine_cost'), 4) }}</span>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="feather-dollar-sign text-muted fs-28 mb-2 d-block"></i>
                        <span class="text-muted fs-12">No cost estimates available.</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Description Info -->
        @if ($routing->description)
            <div class="mb-4 pb-4 border-bottom">
                <h5 class="fw-bold text-dark mb-2">Routing Purpose & Specifications</h5>
                <p class="mb-0 fs-13 text-muted" style="white-space: pre-line;">{{ $routing->description }}</p>
            </div>
        @endif

        <!-- Operations Sequence Details -->
        <div class="mb-4 pb-4 border-bottom">
            <h5 class="fw-bold text-dark mb-3">Sequence Operations Timeline Details</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width: 5%" class="text-center">Seq</th>
                            <th style="width: 25%">Operation Detail</th>
                            <th style="width: 12%">Operation Type</th>
                            <th style="width: 18%">Work Center Location</th>
                            <th style="width: 15%">Machine Asset</th>
                            <th class="text-end" style="width: 8%">Setup Time</th>
                            <th class="text-end" style="width: 8%">Process Time</th>
                            <th class="text-end" style="width: 8%">Yield Rate</th>
                            <th class="text-center" style="width: 5%">QC Gate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($routing->operations as $op)
                            <tr>
                                <td class="fw-bold text-center font-monospace align-middle">{{ $op->sequence }}</td>
                                <td class="align-middle">
                                    <span class="fw-bold text-dark">{{ $op->name }}</span>
                                    <span class="badge bg-soft-primary text-primary font-monospace ms-1 fs-9">{{ $op->operation_number }}</span>
                                    @if ($op->description)
                                        <small class="text-muted d-block mt-1">{{ $op->description }}</small>
                                    @endif
                                    @if ($op->is_external)
                                        <span class="badge bg-soft-danger text-danger mt-1 fs-9 text-uppercase">Outsourced</span>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    <span class="badge bg-soft-secondary text-secondary text-uppercase fs-10">
                                        {{ config('production.operation_types')[$op->operation_type] ?? $op->operation_type }}
                                    </span>
                                </td>
                                <td class="align-middle">
                                    @if ($op->workCenter)
                                        <a href="{{ route('production.work-centers.show', $op->work_center_id) }}" class="fw-semibold text-primary">
                                            {{ $op->workCenter->name }}
                                        </a>
                                        <small class="text-muted d-block fs-10">{{ $op->workCenter->code }}</small>
                                    @else
                                        <span class="text-danger">Missing</span>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    @if ($op->machine)
                                        <span class="fw-semibold text-dark">{{ $op->machine->name }}</span>
                                        <small class="text-muted d-block fs-10">{{ $op->machine->code }}</small>
                                    @else
                                        <span class="text-muted">Generic Capacity</span>
                                    @endif
                                </td>
                                <td class="text-end align-middle font-monospace">{{ number_format($op->setup_time_minutes, 1) }} min</td>
                                <td class="text-end align-middle font-monospace">{{ number_format($op->processing_time_minutes, 1) }} min</td>
                                <td class="text-end align-middle font-monospace">{{ number_format($op->expected_yield_percentage, 0) }}%</td>
                                <td class="text-center align-middle">
                                    @if ($op->quality_required)
                                        <span class="badge bg-soft-danger text-danger"><i class="feather-shield"></i> QC</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>No operation stages defined in this routing.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Approval Audit History -->
        <div>
            <h5 class="fw-bold text-dark mb-3">Routing Document Lifecycle Approval History</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width: 20%">DateTime Stamp</th>
                            <th style="width: 25%">Reviewer User</th>
                            <th style="width: 15%">Action / Transition</th>
                            <th style="width: 40%">Review Comments / Reasons</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($routing->approvals as $approval)
                            <tr>
                                <td class="font-monospace fs-12 align-middle text-muted">{{ $approval->created_at->format('Y-m-d H:i:s') }}</td>
                                <td class="fw-semibold text-dark align-middle">{{ $approval->user->name ?? 'System Process' }}</td>
                                <td class="align-middle">
                                    @if ($approval->action === 'Approved')
                                        <span class="badge bg-soft-success text-success text-uppercase font-monospace fs-10">Approved</span>
                                    @elseif ($approval->action === 'Rejected')
                                        <span class="badge bg-soft-danger text-danger text-uppercase font-monospace fs-10">Rejected</span>
                                    @elseif ($approval->action === 'Submitted')
                                        <span class="badge bg-soft-warning text-warning text-uppercase font-monospace fs-10">Submitted</span>
                                    @elseif ($approval->action === 'Cancelled')
                                        <span class="badge bg-soft-danger text-danger text-uppercase font-monospace fs-10">Cancelled</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary text-uppercase font-monospace fs-10">{{ $approval->action }}</span>
                                    @endif
                                </td>
                                <td class="text-muted fs-12 align-middle">{{ $approval->comments ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>No workflow entries recorded.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- Reject Comment Modal -->
    <div class="modal fade" id="rejectModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <form action="{{ route('production.routing.reject', $routing->id) }}" method="POST">
                    @csrf
                    <div class="modal-header bg-danger text-white border-0 py-3">
                        <h5 class="modal-title fw-bold" id="rejectModalLabel"><i class="feather-x-circle me-2"></i>Reject Engineering Routing</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <x-ui.odoo-form-ui type="textarea" label="Comments" name="comments" placeholder="Detail any design faults, time study corrections, or validation errors preventing approval..." :required="true" rows="4"></x-ui.odoo-form-ui>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Comment Modal -->
    <div class="modal fade" id="cancelModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <form action="{{ route('production.routing.cancel', $routing->id) }}" method="POST">
                    @csrf
                    <div class="modal-header bg-dark text-white border-0 py-3">
                        <h5 class="modal-title fw-bold" id="cancelModalLabel"><i class="feather-slash me-2"></i>Cancel Process Routing</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <x-ui.odoo-form-ui type="textarea" label="Reason" name="comments" placeholder="Detail the reason for termination or process decommissioning..." :required="true" rows="4"></x-ui.odoo-form-ui>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-dark">Decommission Routing</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Duplicate / Create New Version Modal -->
    <div class="modal fade" id="duplicateVersionModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="duplicateVersionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <form action="{{ route('production.routing.duplicate', $routing->id) }}" method="POST">
                    @csrf
                    <div class="modal-header bg-brand text-white border-0 py-3">
                        <h5 class="modal-title fw-bold" id="duplicateVersionModalLabel"><i class="feather-git-branch me-2"></i>Release Next Version</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4 text-dark fs-13">
                        <div class="mb-3 d-flex align-items-center">
                            <span class="odoo-form-label" style="width: 150px;">Original Version Profile:</span>
                            <span class="fw-bold text-dark fs-14">{{ $routing->version }} (Revision {{ $routing->revision }})</span>
                        </div>
                        
                        <x-ui.odoo-form-ui type="input" label="Next Version" name="new_version" placeholder="e.g. 1.0.1 or 2.0.0" :value="old('new_version')" :required="true" />
                        <small class="text-muted d-block mt-2">Creates a new draft routing duplicating all original operations. The new revision number will be incremented automatically.</small>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-brand text-white">Create Revision Version</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function() {
                $('.modal').appendTo('body');
            });
        </script>
    @endpush
@endsection
