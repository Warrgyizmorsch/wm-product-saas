@extends('layouts.duralux')

@section('title', __('production.routing_details') . ' | SaaS ERP')
@section('page-title', __('production.routing_details'))
@section('breadcrumb', $routing->routing_number)

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('production.routing.index') }}" class="btn btn-secondary">
            <i class="feather-arrow-left me-2"></i>{{ __('production.back_to_list') }}
        </a>
        
        @if ($routing->isDraft())
            @can('update', $routing)
                <a href="{{ route('production.routing.edit', $routing->id) }}" class="btn btn-primary">
                    <i class="feather-edit me-2"></i>{{ __('production.edit_draft') }}
                </a>
            @endcan
        @endif

        @can('duplicate', $routing)
            <button type="button" class="btn btn-light-brand" data-bs-toggle="modal" data-bs-target="#duplicateVersionModal">
                <i class="feather-copy me-2"></i>{{ __('production.duplicate_routing_version') }}
            </button>
        @endcan
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <!-- Workflow Banner (Inlined inside panel) -->
        <div class="p-3 border rounded bg-light mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-brand text-white me-3 rounded-circle" style="width: 40px; height: 40px; font-size: 16px; display: flex; align-items: center; justify-content: center;">
                    <i class="feather-info"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">{{ __('production.routing_lifecycle_status') }}</h6>
                    <p class="fs-12 mb-0 text-muted">
                        {{ __('production.status') }}: 
                        @if ($routing->isDraft())
                            <span class="badge bg-soft-secondary text-secondary font-monospace fw-bold text-uppercase">{{ __('production.draft') }}</span>
                            — {{ __('production.draft_desc') }}
                        @elseif ($routing->isPendingApproval())
                            <span class="badge bg-soft-warning text-warning font-monospace fw-bold text-uppercase">{{ __('production.pending_approval') }}</span>
                            — {{ __('production.pending_desc') }}
                        @elseif ($routing->isActive())
                            <span class="badge bg-soft-success text-success font-monospace fw-bold text-uppercase">{{ __('production.active') }}</span>
                            — {{ __('production.active_desc') }}
                        @elseif ($routing->isHistorical())
                            <span class="badge bg-soft-info text-info font-monospace fw-bold text-uppercase">{{ __('production.historical') }}</span>
                            — {{ __('production.historical_desc') }}
                        @else
                            <span class="badge bg-soft-danger text-danger font-monospace fw-bold text-uppercase">{{ __('production.cancelled') }}</span>
                            — {{ __('production.cancelled_desc') }}
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
                                <i class="feather-send me-1"></i>{{ __('production.submit_approval') }}
                            </button>
                        </form>
                    @endcan
                @endif

                @if ($routing->isPendingApproval())
                    @can('approve', $routing)
                        <form action="{{ route('production.routing.approve', $routing->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm px-3" onclick="return confirm(@js(__('production.confirm_approve_routing')));">
                                <i class="feather-check-circle me-1"></i>{{ __('production.approve_activate') }}
                            </button>
                        </form>
                    @endcan

                    @can('reject', $routing)
                        <button type="button" class="btn btn-danger btn-sm px-3" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="feather-x-circle me-1"></i>{{ __('production.reject') }}
                        </button>
                    @endcan
                @endif

                @if (!$routing->isCancelled() && !$routing->isHistorical())
                    @can('cancel', $routing)
                        <button type="button" class="btn btn-outline-danger btn-sm px-3" data-bs-toggle="modal" data-bs-target="#cancelModal">
                            <i class="feather-slash me-1"></i>{{ __('production.cancel_routing') }}
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
                            <span class="badge bg-soft-success text-success px-3 py-1 rounded-pill">{{ __('production.primary') }}</span>
                        @else
                            <span class="badge bg-soft-warning text-warning px-3 py-1 rounded-pill">{{ __('production.alternative') }}</span>
                        @endif
                    </div>
                </div>

                <div class="d-flex flex-column gap-3 mt-4 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.item_to_produce') }}:</span>
                        <span class="fw-bold text-dark text-end">
                            @if ($routing->product)
                                {{ $routing->product->name }}
                                <small class="text-muted d-block fs-11">{{ $routing->product->sku }}</small>
                            @else
                                <span class="text-danger">{{ __('production.not_specified') }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.version') }}:</span>
                        <span class="fw-semibold text-dark">{{ $routing->version }} (Rev {{ $routing->revision }})</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">{{ __('production.validity') }}:</span>
                        <span class="fw-semibold text-dark text-end fs-12">
                            {{ $routing->effective_from ? $routing->effective_from->format('Y-m-d') : __('production.immediate') }}
                            {{ __('production.to') ?? 'to' }}
                            {{ $routing->effective_to ? $routing->effective_to->format('Y-m-d') : __('production.indefinite') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Time Metrics -->
            <div class="col-md-4 border-end">
                <h5 class="fw-bold text-dark mb-3">{{ __('production.time_process_stats') }}</h5>
                <div class="d-flex flex-column gap-4 py-2 px-2">
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.total_sequence_operations') }}</span>
                        <span class="fs-22 fw-bold text-dark">{{ $routing->operations->count() }} {{ __('production.stages') }}</span>
                    </div>
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.setup_duration_estimate') }}</span>
                        <span class="fs-22 fw-bold text-dark">{{ number_format($routing->operations->sum('setup_time_minutes'), 1) }} <span class="fs-13 fw-normal text-muted">{{ __('production.minutes') }}</span></span>
                    </div>
                    <div>
                        <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.total_cycle_running_time') }}</span>
                        <span class="fs-18 fw-bold text-success">{{ number_format($routing->totalCycleTimeMinutes(), 1) }} <span class="fs-13 fw-normal text-muted">{{ __('production.minutes') }}</span></span>
                        <small class="text-muted d-block mt-1">{{ __('production.cycle_time_desc') }}</small>
                    </div>
                </div>
            </div>

            <!-- Cost Estimates -->
            <div class="col-md-4">
                <h5 class="fw-bold text-dark mb-3">{{ __('production.process_cost_predictions') }} (Qty: 1.0)</h5>
                @if ($costSummary)
                    <div class="d-flex flex-column gap-4 py-2 px-2">
                        <div>
                            <span class="text-muted fs-11 text-uppercase d-block mb-1">{{ __('production.routing_cost_per_unit') }}</span>
                            <span class="fs-24 fw-bold text-primary">{{ format_currency($costSummary['total_cost']) }}</span>
                            <small class="text-muted d-block mt-1">{{ __('production.cost_yield_desc') }}</small>
                        </div>
                        
                        <div class="border-top pt-3 mt-2">
                            <h6 class="fw-bold text-dark mb-2">{{ __('production.cost_contribution_details') }}</h6>
                            <div class="d-flex justify-content-between align-items-center mb-1 fs-12">
                                <span class="text-muted">{{ __('production.labor_cost') }}:</span>
                                <span class="fw-semibold text-dark">{{ format_currency(collect($costSummary['operations'])->sum('labor_cost')) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center fs-12">
                                <span class="text-muted">{{ __('production.machine_cost') }}:</span>
                                <span class="fw-semibold text-dark">{{ format_currency(collect($costSummary['operations'])->sum('machine_cost')) }}</span>
                            </div>


                        </div>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="feather-dollar-sign text-muted fs-28 mb-2 d-block"></i>
                        <span class="text-muted fs-12">{{ __('production.no_pricing_components') }}</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Description Info -->
        @if ($routing->description)
            <div class="mb-4 pb-4 border-bottom">
                <h5 class="fw-bold text-dark mb-2">{{ __('production.routing_purpose_specs') }}</h5>
                <p class="mb-0 fs-13 text-muted" style="white-space: pre-line;">{{ $routing->description }}</p>
            </div>
        @endif

        <!-- Operations Sequence Details -->
        <div class="mb-4 pb-4 border-bottom">
            <h5 class="fw-bold text-dark mb-3">{{ __('production.sequence_operations_timeline') }}</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width: 5%" class="text-center">{{ __('production.seq') }}</th>
                            <th style="width: 25%">{{ __('production.operation_details') }}</th>
                            <th style="width: 12%">{{ __('production.type') }}</th>
                            <th style="width: 18%">{{ __('production.work_center') }}</th>
                            <th style="width: 15%">{{ __('production.machine') }}</th>
                            <th class="text-end" style="width: 8%">{{ __('production.setup') }}</th>
                            <th class="text-end" style="width: 8%">{{ __('production.run') }}</th>
                            <th class="text-end" style="width: 8%">{{ __('production.yield') }}</th>
                            <th class="text-center" style="width: 5%">{{ __('production.qc_gate') }}</th>
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
                                        <span class="badge bg-soft-danger text-danger mt-1 fs-9 text-uppercase">{{ __('production.outsourced') }}</span>
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
                                        <span class="text-danger">{{ __('production.missing') }}</span>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    @if ($op->machine)
                                        <span class="fw-semibold text-dark">{{ $op->machine->name }}</span>
                                        <small class="text-muted d-block fs-10">{{ $op->machine->code }}</small>
                                    @else
                                        <span class="text-muted">{{ __('production.generic_capacity') }}</span>
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
                                    <i class="feather-info me-2"></i>{{ __('production.no_operations_defined') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Approval Audit History -->
        <div>
            <h5 class="fw-bold text-dark mb-3">{{ __('production.routing_approval_history') }}</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width: 20%">{{ __('production.timestamp') }}</th>
                            <th style="width: 25%">{{ __('production.user') }}</th>
                            <th style="width: 15%">{{ __('production.transition') }}</th>
                            <th style="width: 40%">{{ __('production.notes_reasons') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($routing->approvals as $approval)
                            <tr>
                                <td class="font-monospace fs-12 align-middle text-muted">{{ $approval->created_at->format('Y-m-d H:i:s') }}</td>
                                <td class="fw-semibold text-dark align-middle">{{ $approval->user->name ?? 'System Process' }}</td>
                                <td class="align-middle">
                                    @if ($approval->action === 'Approved')
                                        <span class="badge bg-soft-success text-success text-uppercase font-monospace fs-10">{{ __('production.approved') }}</span>
                                    @elseif ($approval->action === 'Rejected')
                                        <span class="badge bg-soft-danger text-danger text-uppercase font-monospace fs-10">{{ __('production.rejected') }}</span>
                                    @elseif ($approval->action === 'Submitted')
                                        <span class="badge bg-soft-warning text-warning text-uppercase font-monospace fs-10">{{ __('production.submitted') }}</span>
                                    @elseif ($approval->action === 'Cancelled')
                                        <span class="badge bg-soft-danger text-danger text-uppercase font-monospace fs-10">{{ __('production.cancelled') }}</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary text-uppercase font-monospace fs-10">{{ $approval->action }}</span>
                                    @endif
                                </td>
                                <td class="text-muted fs-12 align-middle">{{ $approval->comments ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>{{ __('production.no_history_found') }}
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
                        <h5 class="modal-title fw-bold" id="rejectModalLabel"><i class="feather-x-circle me-2"></i>{{ __('production.reject_routing_version') }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <x-ui.odoo-form-ui type="textarea" :label="__('production.comments_notes')" name="comments" placeholder="{{ __('production.reject_comments_placeholder') }}" :required="true" rows="4"></x-ui.odoo-form-ui>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('production.confirm_rejection') }}</button>
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
                        <h5 class="modal-title fw-bold" id="cancelModalLabel"><i class="feather-slash me-2"></i>{{ __('production.cancel_routing_version') }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <x-ui.odoo-form-ui type="textarea" :label="__('production.notes_reasons')" name="comments" placeholder="{{ __('production.cancel_reason_placeholder') }}" :required="true" rows="4"></x-ui.odoo-form-ui>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                        <button type="submit" class="btn btn-dark">{{ __('production.decommission_routing') }}</button>
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
                        <h5 class="modal-title fw-bold" id="duplicateVersionModalLabel"><i class="feather-git-branch me-2"></i>{{ __('production.duplicate_routing_version') }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4 text-dark fs-13">
                        <div class="mb-3 d-flex align-items-center">
                            <span class="odoo-form-label" style="width: 150px;">{{ __('production.original_version_profile') }}</span>
                            <span class="fw-bold text-dark fs-14">{{ $routing->version }} ({{ __('production.revision') ?? 'Revision' }} {{ $routing->revision }})</span>
                        </div>
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.new_version_name')" name="new_version" placeholder="e.g. 1.0.1 or 2.0.0" :value="old('new_version')" :required="true" />
                        <small class="text-muted d-block mt-2">{{ __('production.duplicate_routing_modal_body') }}</small>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                        <button type="submit" class="btn btn-brand text-white">{{ __('production.create_revision_version') }}</button>
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
