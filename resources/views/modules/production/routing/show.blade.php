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
    <!-- Success & Error Alerts -->
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-success text-white me-3">
                    <i class="feather-check-circle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Success!</h6>
                    <p class="fs-12 mb-0">{{ session('success') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-danger text-white me-3">
                    <i class="feather-alert-triangle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                    <p class="fs-12 mb-0">{{ session('error') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Workflow Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-light-brand">
                <div class="card-body py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar-text avatar-md bg-brand text-white me-3 rounded-circle">
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
                                    <button type="submit" class="btn btn-warning">
                                        <i class="feather-send me-1"></i>Submit for Approval
                                    </button>
                                </form>
                            @endcan
                        @endif

                        @if ($routing->isPendingApproval())
                            @can('approve', $routing)
                                <form action="{{ route('production.routing.approve', $routing->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success" onclick="return confirm('Approving this routing will automatically archive the previous active version. Proceed?');">
                                        <i class="feather-check-circle me-1"></i>Approve & Activate
                                    </button>
                                </form>
                            @endcan

                            @can('reject', $routing)
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="feather-x-circle me-1"></i>Reject
                                </button>
                            @endcan
                        @endif

                        @if (!$routing->isCancelled() && !$routing->isHistorical())
                            @can('cancel', $routing)
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                                    <i class="feather-slash me-1"></i>Cancel Routing
                                </button>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Cards -->
    <div class="row g-4">
        <!-- Routing Summary Header -->
        <div class="col-xl-4">
            <x-ui.card title="Routing Process Info" class="h-100">
                <div class="text-center py-3 mb-3 border-bottom">
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

                <div class="d-flex flex-column gap-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">Manufactured Item:</span>
                        <span class="fw-bold text-dark text-end">
                            @if ($routing->product)
                                {{ $routing->product->name }}
                                <small class="text-muted d-block">{{ $routing->product->sku }}</small>
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
                        <span class="text-muted fs-13">Effective Date Range:</span>
                        <span class="fw-semibold text-dark text-end fs-12">
                            {{ $routing->effective_from ? $routing->effective_from->format('Y-m-d') : 'Immediate' }}
                            to
                            {{ $routing->effective_to ? $routing->effective_to->format('Y-m-d') : 'Indefinite' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">Registered By:</span>
                        <span class="fw-semibold text-dark">{{ $routing->creator->name ?? 'System' }}</span>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <!-- Time & Operation Statistics -->
        <div class="col-xl-4">
            <x-ui.card title="Time & Process Statistics" class="h-100">
                <div class="d-flex flex-column gap-4 py-2">
                    <div>
                        <span class="text-muted fs-12 text-uppercase d-block mb-1">Total Sequence Operations</span>
                        <span class="fs-24 fw-bold text-dark">{{ $routing->operations->count() }} Stage(s)</span>
                    </div>
                    <div>
                        <span class="text-muted fs-12 text-uppercase d-block mb-1">Estimated Setup Time</span>
                        <span class="fs-24 fw-bold text-dark">{{ number_format($routing->operations->sum('setup_time_minutes'), 1) }} <span class="fs-14 fw-normal text-muted">Minutes</span></span>
                    </div>
                    <div>
                        <span class="text-muted fs-12 text-uppercase d-block mb-1">Total Cycle Running Time</span>
                        <span class="fs-20 fw-bold text-success">{{ number_format($routing->totalCycleTimeMinutes(), 1) }} <span class="fs-14 fw-normal text-muted">Minutes</span></span>
                        <small class="text-muted d-block mt-1">Includes setup, processing, and queuing/cooling wait times.</small>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <!-- Routing Cost Preparation Analysis Summary -->
        <div class="col-xl-4">
            <x-ui.card title="Process Cost Calculations (Qty: 1.0)" class="h-100">
                @if ($costSummary)
                    <div class="d-flex flex-column gap-4 py-2">
                        <div>
                            <span class="text-muted fs-12 text-uppercase d-block mb-1">Routing Cost Per Unit</span>
                            <span class="fs-28 fw-bold text-primary">${{ number_format($costSummary['total_cost'], 4) }}</span>
                            <small class="text-muted d-block mt-1">Calculated using Labor + Machine rates adjusted by yield loss.</small>
                        </div>
                        
                        <div class="border-top pt-3 mt-2">
                            <h6 class="fw-bold mb-2">Cost Rate Breakdown</h6>
                            <div class="d-flex justify-content-between align-items-center mb-1 fs-12">
                                <span class="text-muted">Labor Expense Contribution:</span>
                                <span class="fw-semibold text-dark">${{ number_format(collect($costSummary['operations'])->sum('labor_cost'), 4) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center fs-12">
                                <span class="text-muted">Machine Overhead Contribution:</span>
                                <span class="fw-semibold text-dark">${{ number_format(collect($costSummary['operations'])->sum('machine_cost'), 4) }}</span>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="feather-dollar-sign text-muted fs-28 mb-2 d-block"></i>
                        <span class="text-muted fs-12">No cost estimates calculated. Add operations with cost rates to view predictions.</span>
                    </div>
                @endif
            </x-ui.card>
        </div>

        <!-- Description Info -->
        @if ($routing->description)
            <div class="col-12">
                <x-ui.card title="Routing Process Scope & Specifications">
                    <p class="mb-0 fs-13 text-muted" style="white-space: pre-line;">{{ $routing->description }}</p>
                </x-ui.card>
            </div>
        @endif

        <!-- Operations Sequence Detail list -->
        <div class="col-12">
            <x-ui.card title="Sequence Operations Timeline Details">
                <x-ui.table striped>
                    <thead>
                        <tr>
                            <th style="width: 5%">Seq</th>
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
                                <td class="fw-bold text-center font-monospace">{{ $op->sequence }}</td>
                                <td>
                                    <span class="fw-bold text-dark">{{ $op->name }}</span>
                                    <span class="badge bg-soft-primary text-primary font-monospace ms-1 fs-9">{{ $op->operation_number }}</span>
                                    @if ($op->description)
                                        <small class="text-muted d-block mt-1">{{ $op->description }}</small>
                                    @endif
                                    @if ($op->is_external)
                                        <span class="badge bg-soft-danger text-danger mt-1 fs-9 text-uppercase">Outsourced / External</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-soft-secondary text-secondary text-uppercase fs-10">
                                        {{ config('production.operation_types')[$op->operation_type] ?? $op->operation_type }}
                                    </span>
                                </td>
                                <td>
                                    @if ($op->workCenter)
                                        <a href="{{ route('production.work-centers.show', $op->work_center_id) }}" class="fw-semibold text-primary">
                                            {{ $op->workCenter->name }}
                                        </a>
                                        <small class="text-muted d-block">{{ $op->workCenter->code }}</small>
                                    @else
                                        <span class="text-danger">Missing</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($op->machine)
                                        <span class="fw-semibold text-dark">{{ $op->machine->name }}</span>
                                        <small class="text-muted d-block">{{ $op->machine->code }}</small>
                                    @else
                                        <span class="text-muted">Generic Capacity</span>
                                    @endif
                                </td>
                                <td class="text-end font-monospace">{{ number_format($op->setup_time_minutes, 1) }} min</td>
                                <td class="text-end font-monospace">{{ number_format($op->processing_time_minutes, 1) }} min</td>
                                <td class="text-end font-monospace">{{ number_format($op->expected_yield_percentage, 0) }}%</td>
                                <td class="text-center">
                                    @if ($op->quality_required)
                                        <span class="badge bg-soft-danger text-danger"><i class="feather-shield-alert"></i> QC Check</span>
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
                </x-ui.table>
            </x-ui.card>
        </div>

        <!-- Approval Audit Trail History list -->
        <div class="col-12">
            <x-ui.card title="Routing Document Lifecycle Approval History">
                <x-ui.table>
                    <thead>
                        <tr>
                            <th>DateTime Stamp</th>
                            <th>Reviewer User</th>
                            <th>Action / Transition Trigger</th>
                            <th>Review Comments / Reasons</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($routing->approvals as $approval)
                            <tr>
                                <td class="font-monospace fs-12">{{ $approval->created_at->format('Y-m-d H:i:s') }}</td>
                                <td class="fw-semibold text-dark">{{ $approval->user->name ?? 'System Process' }}</td>
                                <td>
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
                                <td class="text-muted fs-12">{{ $approval->comments ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>No workflow entries recorded.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
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
                        <label class="form-label fw-semibold text-dark fs-12 text-uppercase mb-2">Engineering / Management Rejection Comments</label>
                        <textarea class="form-control" name="comments" rows="4" placeholder="Detail any design faults, time study corrections, or validation errors preventing approval..." required></textarea>
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
                        <label class="form-label fw-semibold text-dark fs-12 text-uppercase mb-2">Cancellation Reason</label>
                        <textarea class="form-control" name="comments" rows="4" placeholder="Detail the reason for termination or process decommissioning..." required></textarea>
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
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <span class="fs-12 text-muted d-block mb-2">Original Version Profile:</span>
                            <span class="fw-bold text-dark fs-14">{{ $routing->version }} (Revision {{ $routing->revision }})</span>
                        </div>
                        
                        <x-ui.input label="Next Target Version Code" name="new_version" placeholder="e.g. 1.0.1 or 2.0.0" value="{{ old('new_version') }}" required />
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
@endsection
