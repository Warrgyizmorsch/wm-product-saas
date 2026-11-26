@extends('layouts.duralux')

{{--
    Quality Plan View screen — QR-12 (approved mockup, docs/production_quality_rules.md):
    lifecycle banner + workflow buttons, header / scope cards, checklist, version history, where used, approval history.
--}}

@section('title', 'Quality Plan Details | SaaS ERP')
@section('page-title', 'Quality Plan Details')
@section('breadcrumb', $plan->plan_number ?: 'Quality Plan')

@use('App\Domains\Production\Models\ProductionQualityPlan', 'QP')
@php
    $statusBadge = [
        QP::STATUS_DRAFT            => 'bg-soft-secondary text-secondary',
        QP::STATUS_PENDING_APPROVAL => 'bg-soft-warning text-warning',
        QP::STATUS_APPROVED         => 'bg-soft-success text-success',
        QP::STATUS_HISTORICAL       => 'bg-soft-info text-info',
    ];
    $statusText = [
        QP::STATUS_DRAFT            => 'Draft — editable. Submit it for approval when it is ready.',
        QP::STATUS_PENDING_APPROVAL => 'Waiting for approval. Read only until it is approved or rejected.',
        QP::STATUS_APPROVED         => 'Approved and in use. Editing creates the next version as a draft; this version stays in use until that one is approved.',
        QP::STATUS_HISTORICAL       => 'Replaced or retired. Kept for records — read only.',
    ];
    $isDeleted = $plan->trashed();
    $current = $approvedVersion && $approvedVersion->id !== $plan->id ? $approvedVersion : null;
    $mandatoryCount = $plan->parameters->where('is_mandatory', true)->count();
    $actionBadge = [
        'Approved' => 'bg-soft-success text-success',
        'Rejected' => 'bg-soft-danger text-danger',
        'Submitted' => 'bg-soft-warning text-warning',
        'Marked Historical' => 'bg-soft-info text-info',
        'Deleted' => 'bg-soft-danger text-danger',
    ];
@endphp

@section('page-actions')
    <div class="d-flex gap-2">
        <x-ui.button :href="route('production.quality-plans.index')" variant="secondary" icon="feather-arrow-left">
            {{ __('production.back_to_list') }}
        </x-ui.button>

        @unless ($isDeleted)
            @if ($plan->isDraft())
                @can('update', $plan)
                    <x-ui.button :href="route('production.quality-plans.edit', $plan->id)" variant="primary" icon="feather-edit">Edit Draft</x-ui.button>
                @endcan
                @can('delete', $plan)
                    <form id="delete-plan-form" action="{{ route('production.quality-plans.destroy', $plan->id) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="button" variant="danger" icon="feather-trash-2" onclick="if (confirm('Permanently delete this draft quality plan?')) document.getElementById('delete-plan-form').submit();">
                            Delete
                        </x-ui.button>
                    </form>
                @endcan
            @elseif ($plan->isApproved())
                @can('update', $plan)
                    @if ($openVersion)
                        <x-ui.button :href="route('production.quality-plans.show', $openVersion->id)" variant="light" icon="feather-git-branch">
                            Open v{{ $openVersion->version }} ({{ $openVersion->statusLabel() }})
                        </x-ui.button>
                    @else
                        <x-ui.button :href="route('production.quality-plans.edit', $plan->id)" variant="primary" icon="feather-git-branch">Edit (New Version)</x-ui.button>
                    @endif
                @endcan
            @elseif ($plan->isHistorical() && $current)
                <x-ui.button :href="route('production.quality-plans.show', $current->id)" variant="primary" icon="feather-arrow-right">
                    Open Current Version (v{{ $current->version }})
                </x-ui.button>
            @endif
        @endunless
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">

        @if ($isDeleted)
            <div class="alert alert-soft-danger border border-danger fs-12 mb-4">
                <i class="feather-trash-2 me-1"></i>This draft was deleted on {{ $plan->deleted_at->format('Y-m-d H:i') }}. Kept for records only.
            </div>
        @endif

        <!-- Lifecycle banner -->
        <div class="p-3 border rounded bg-light mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-brand text-white me-3 rounded-circle" style="width: 40px; height: 40px; font-size: 16px; display: flex; align-items: center; justify-content: center;">
                    <i class="feather-info"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">Quality Plan Lifecycle</h6>
                    <p class="fs-12 mb-0 text-muted">
                        {{ __('production.status') }}:
                        <span class="badge {{ $statusBadge[$plan->status] ?? 'bg-soft-secondary text-secondary' }} font-monospace fw-bold text-uppercase">{{ $plan->statusLabel() }}</span>
                        — {{ $statusText[$plan->status] ?? '' }}
                    </p>
                </div>
            </div>

            @unless ($isDeleted)
                <div class="d-flex gap-2">
                    @if ($plan->isDraft())
                        @can('submit', $plan)
                            <form action="{{ route('production.quality-plans.submit', $plan->id) }}" method="POST">
                                @csrf
                                <x-ui.button type="submit" variant="warning" icon="feather-send" class="px-3">Submit for Approval</x-ui.button>
                            </form>
                        @endcan
                    @endif

                    @if ($plan->isPendingApproval())
                        @can('approve', $plan)
                            <form action="{{ route('production.quality-plans.approve', $plan->id) }}" method="POST" id="approvePlanForm">
                                @csrf
                                <x-ui.button type="button" variant="success" icon="feather-check-circle" class="px-3"
                                    onclick="if (confirm('Approve version {{ $plan->version }}?{{ $approvedVersion ? ' Version ' . $approvedVersion->version . ' becomes historical.' : '' }}')) document.getElementById('approvePlanForm').submit();">
                                    Approve
                                </x-ui.button>
                            </form>
                        @endcan
                        @can('reject', $plan)
                            <x-ui.button type="button" variant="danger" icon="feather-x-circle" class="px-3" data-bs-toggle="modal" data-bs-target="#rejectPlanModal">Reject</x-ui.button>
                        @endcan
                    @endif

                    @can('retire', $plan)
                        <x-ui.button type="button" variant="outline-secondary" icon="feather-archive" class="px-3" data-bs-toggle="modal" data-bs-target="#retirePlanModal">Mark Historical</x-ui.button>
                    @endcan
                </div>
            @endunless
        </div>

        @if ($plan->isHistorical() && $current)
            <div class="alert alert-soft-warning border border-warning fs-12 mb-4">
                <i class="feather-alert-triangle me-1"></i>This is an old version. Version {{ $current->version }} is current. Read only.
            </div>
        @endif

        <!-- Header + scope -->
        <div class="row g-4 mb-4 pb-4 border-bottom">
            <div class="col-md-6 border-end">
                <h4 class="fw-bold text-dark mb-1">{{ $plan->name }}</h4>
                <span class="fs-13 fw-semibold text-muted text-uppercase font-monospace">{{ $plan->plan_number }}</span>
                <div class="d-flex flex-column gap-2 mt-3 fs-13">
                    <div class="d-flex justify-content-between"><span class="text-muted">Version</span><span class="fw-semibold text-dark">v{{ $plan->version }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Status</span><span class="badge {{ $statusBadge[$plan->status] ?? '' }} text-uppercase font-monospace fs-10">{{ $plan->statusLabel() }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Created by</span><span class="fw-semibold text-dark">{{ $plan->creator?->name ?? '—' }} · {{ $plan->created_at?->format('Y-m-d') }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Submitted by / on</span><span class="fw-semibold text-dark">{{ $plan->submitter?->name ?? '—' }}{{ $plan->submitted_at ? ' · ' . $plan->submitted_at->format('Y-m-d') : '' }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Approved by / on</span><span class="fw-semibold text-dark">{{ $plan->approver?->name ?? '—' }}{{ $plan->approved_at ? ' · ' . $plan->approved_at->format('Y-m-d') : '' }}</span></div>
                    @if ($plan->historical_at)
                        <div class="d-flex justify-content-between"><span class="text-muted">Historical since</span><span class="fw-semibold text-dark">{{ $plan->historical_at->format('Y-m-d') }}</span></div>
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <h5 class="fw-bold text-dark mb-3">Scope</h5>
                <div class="d-flex flex-column gap-2 fs-13">
                    <div class="d-flex justify-content-between"><span class="text-muted">Scope type</span><span class="fw-semibold text-dark">{{ $plan->scopeLabel() }}</span></div>
                    @if ($plan->type === QP::SCOPE_PRODUCT)
                        <div class="d-flex justify-content-between gap-3"><span class="text-muted">Product</span><span class="fw-semibold text-dark text-end">{{ $plan->product ? $plan->product->sku . ' - ' . $plan->product->name : '—' }}</span></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Product type</span><span class="fw-semibold text-dark">{{ $plan->product?->type ? ucwords(str_replace('_', ' ', $plan->product->type)) : '—' }}</span></div>
                    @elseif ($plan->type === QP::SCOPE_SECTION)
                        <div class="d-flex justify-content-between gap-3"><span class="text-muted">Section</span><span class="fw-semibold text-dark text-end">{{ $plan->section ? (($plan->section->workCenter?->name ? $plan->section->workCenter->name . ' / ' : '') . $plan->section->name) : '—' }}</span></div>
                    @else
                        <div class="d-flex justify-content-between"><span class="text-muted">Applies to</span><span class="fw-semibold text-dark">Any process</span></div>
                    @endif
                    <div class="d-flex justify-content-between"><span class="text-muted">Parameters</span><span class="fw-semibold text-dark">{{ $plan->parameters->count() }} ({{ $mandatoryCount }} mandatory)</span></div>
                </div>
            </div>
        </div>

        <!-- Checklist -->
        <div class="mb-4 pb-4 border-bottom">
            <h5 class="fw-bold text-dark mb-3">Checklist Parameters</h5>
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 5%">#</th>
                        <th style="width: 27%">Parameter</th>
                        <th style="width: 15%">Type</th>
                        <th style="width: 41%">Limits / Basis</th>
                        <th style="width: 12%" class="text-center">Mandatory</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plan->parameters as $i => $param)
                        <tr>
                            <td class="text-muted">{{ $i + 1 }}</td>
                            <td class="fw-semibold text-dark">{{ $param->name }}</td>
                            <td>{{ $param->typeLabel() }}</td>
                            <td class="{{ $param->type === 'text' ? 'text-muted' : '' }}">{{ $param->type === 'text' ? 'Free notes at inspection' : $param->limitsLabel() }}</td>
                            <td class="text-center">
                                @if ($param->is_mandatory)
                                    <span class="badge bg-soft-danger text-danger fs-10">Yes</span>
                                @else
                                    <span class="text-muted">No</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No checklist parameters.</td></tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Versions + where used -->
        <div class="row g-4 mb-4 pb-4 border-bottom">
            <div class="col-md-6">
                <h5 class="fw-bold text-dark mb-3">Version History</h5>
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr><th>Version</th><th>Status</th><th>Approved</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($versions as $v)
                            <tr class="{{ $v->id === $plan->id ? 'fw-bold' : '' }}">
                                <td>
                                    @if ($v->id === $plan->id)
                                        v{{ $v->version }} <span class="text-muted fw-normal fs-11">(this)</span>
                                    @else
                                        <a href="{{ route('production.quality-plans.show', $v->id) }}" class="text-primary">v{{ $v->version }}</a>
                                    @endif
                                </td>
                                <td><span class="badge {{ $statusBadge[$v->status] ?? '' }} text-uppercase font-monospace fs-10">{{ $v->statusLabel() }}</span></td>
                                <td class="font-monospace fs-12">{{ $v->approved_at?->format('Y-m-d') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <h5 class="fw-bold text-dark mb-3">Where Used</h5>
                <div class="d-flex justify-content-between fs-13 mb-2">
                    <span class="text-muted">Routing operations</span>
                    <span class="fw-semibold text-dark">{{ $routingLinks->count() }}{{ $routingLinks->where('quality_plan_id', '!=', $approvedVersion?->id)->count() && $approvedVersion ? ' (' . $routingLinks->where('quality_plan_id', '!=', $approvedVersion->id)->count() . ' on an older version)' : '' }}</span>
                </div>
                <div class="fs-12 text-muted mb-3">
                    @forelse ($routingLinks as $link)
                        @php
                            $op = $link->routingOperation;
                            $onOld = $approvedVersion && $link->quality_plan_id !== $approvedVersion->id;
                        @endphp
                        <div>
                            <a href="{{ route('production.routing.show', $op->routing_id) }}" class="text-primary">{{ $op->routing->routing_number }}</a>
                            · Op {{ $op->operation_number ?: $op->sequence }} {{ $op->name }} · v{{ $link->plan?->version }}
                            @if ($onOld)<span class="text-warning ms-1">newer version exists</span>@endif
                        </div>
                    @empty
                        <div>Not linked to any routing operation yet.</div>
                    @endforelse
                </div>
                <div class="d-flex justify-content-between fs-13">
                    <span class="text-muted">Inspections on this version</span>
                    <span class="fw-semibold text-dark">{{ $inspectionCount }}</span>
                </div>
            </div>
        </div>

        <!-- Approval history -->
        <div>
            <h5 class="fw-bold text-dark mb-3">Approval History</h5>
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr><th style="width: 22%">Date</th><th style="width: 20%">User</th><th style="width: 18%">Action</th><th>Comments</th></tr>
                </thead>
                <tbody>
                    @forelse ($plan->approvals as $approval)
                        <tr>
                            <td class="font-monospace fs-12 text-muted">{{ $approval->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="fw-semibold text-dark">{{ $approval->user->name ?? 'System Process' }}</td>
                            <td><span class="badge {{ $actionBadge[$approval->action] ?? 'bg-soft-secondary text-secondary' }} text-uppercase font-monospace fs-10">{{ $approval->action }}</span></td>
                            <td class="text-muted fs-12">{{ $approval->comments ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No history yet.</td></tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>
    </div>

    @can('reject', $plan)
        <x-ui.modal id="rejectPlanModal" title="<i class='feather-x-circle me-2 text-danger'></i>Reject Quality Plan"
            formAction="{{ route('production.quality-plans.reject', $plan->id) }}" formMethod="POST"
            submitText="Confirm Rejection" :closeText="__('production.cancel')" :static="true">
            <p class="fs-12 text-muted">The plan goes back to Draft so it can be corrected and submitted again.</p>
            <x-ui.odoo-form-ui type="textarea" label="Reason" name="comments" placeholder="What needs to change?" :required="true" rows="4"></x-ui.odoo-form-ui>
        </x-ui.modal>
    @endcan

    @can('retire', $plan)
        <x-ui.modal id="retirePlanModal" title="<i class='feather-archive me-2 text-dark'></i>Mark Plan Historical"
            formAction="{{ route('production.quality-plans.retire', $plan->id) }}" formMethod="POST"
            submitText="Mark Historical" :closeText="__('production.cancel')" :static="true">
            <p class="fs-12 text-muted">A historical plan is no longer offered for new inspections or routing operations. Routings already linked to it show a warning. Past inspections keep their records.</p>
            <x-ui.odoo-form-ui type="textarea" label="Reason" name="comments" placeholder="Optional" rows="3"></x-ui.odoo-form-ui>
        </x-ui.modal>
    @endcan
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.location.hash === '#reject' && document.getElementById('rejectPlanModal') && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('rejectPlanModal')).show();
            }
        });
    </script>
@endpush
