@extends('layouts.duralux')

@section('title', 'Quality Control Plans | SaaS ERP')
@section('page-title', 'Quality Control Plans')
@section('breadcrumb', 'Quality Plans')

@section('page-actions')
    @can('create', \App\Domains\Production\Models\ProductionQualityPlan::class)
        <a href="{{ route('production.quality-plans.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>Configure New Plan
        </a>
    @endcan
@endsection

@section('content')
    @use('App\Domains\Production\Models\ProductionQualityPlan', 'QP')
    @php
        $statusBadge = [
            QP::STATUS_DRAFT            => 'bg-soft-secondary text-secondary',
            QP::STATUS_PENDING_APPROVAL => 'bg-soft-warning text-warning',
            QP::STATUS_APPROVED         => 'bg-soft-success text-success',
            QP::STATUS_HISTORICAL       => 'bg-soft-info text-info',
        ];
    @endphp

    <div class="erp-single-panel">
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Quality Control Plans Registry</h5>
            <div class="d-flex gap-2 ms-auto">
                <form method="GET" action="{{ route('production.quality-plans.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i>{{ __('production.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.search_keywords') }}</label>
                            <x-ui.input name="search" placeholder="Plan name or number…" value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Scope Type</label>
                            <x-ui.odoo-form-ui type="select" name="type">
                                <option value="">{{ __('production.all_types') }}</option>
                                @foreach (QP::SCOPES as $key => $label)
                                    <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('production.all_statuses') }}</option>
                                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                                <option value="pending_approval" @selected(request('status') === 'pending_approval')>Pending approval</option>
                                <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                                <option value="historical" @selected(request('status') === 'historical')>Historical</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.quality-plans.index') }}" class="btn btn-sm btn-light border">{{ __('production.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('production.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width: 27%">{{ __('production.plan_name') }}</th>
                    <th style="width: 8%">{{ __('production.version') }}</th>
                    <th style="width: 14%">Scope Type</th>
                    <th style="width: 24%">Assigned Target</th>
                    <th style="width: 7%" class="text-center">Checks</th>
                    <th style="width: 12%">{{ __('production.status') }}</th>
                    <th class="text-end" style="width: 8%">{{ __('production.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($plans as $plan)
                    <tr>
                        <td class="fw-bold text-dark">
                            <a href="{{ route('production.quality-plans.show', $plan->id) }}" class="text-dark hover-primary">{{ $plan->name }}</a>
                            <div class="fs-11 text-muted fw-normal font-monospace">{{ $plan->plan_number }}</div>
                        </td>
                        <td class="font-monospace text-muted">v{{ $plan->version }}</td>
                        <td>
                            <span class="badge bg-soft-info text-info text-uppercase font-monospace fs-10">{{ $plan->scopeLabel() }}</span>
                        </td>
                        <td>
                            @if ($plan->type === QP::SCOPE_PRODUCT && $plan->product)
                                <span class="fw-medium text-dark"><i class="feather-box me-1 text-muted"></i>{{ $plan->product->sku }} - {{ $plan->product->name }}</span>
                            @elseif ($plan->type === QP::SCOPE_SECTION && $plan->section)
                                <span class="fw-medium text-dark"><i class="feather-grid me-1 text-muted"></i>{{ $plan->section->workCenter?->name ? $plan->section->workCenter->name . ' / ' : '' }}{{ $plan->section->name }}</span>
                            @elseif ($plan->type === QP::SCOPE_PROCESS)
                                <span class="text-muted">Any process</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center font-monospace">{{ $plan->parameters_count }}</td>
                        <td>
                            <span class="badge {{ $statusBadge[$plan->status] ?? 'bg-soft-secondary text-secondary' }} text-uppercase font-monospace fs-10">{{ $plan->statusLabel() }}</span>
                        </td>
                        <td class="text-end">
                            <x-ui.action-dropdown>
                                <li>
                                    <a href="{{ route('production.quality-plans.show', $plan->id) }}" class="dropdown-item">
                                        <i class="feather-eye me-2 text-muted fs-12"></i>View</a>
                                </li>

                                @if ($plan->isDraft())
                                    @can('update', $plan)
                                        <li>
                                            <a href="{{ route('production.quality-plans.edit', $plan->id) }}" class="dropdown-item">
                                                <i class="feather-edit me-2 text-muted fs-12"></i>Edit Draft</a>
                                        </li>
                                    @endcan
                                    @can('submit', $plan)
                                        <li>
                                            <form method="POST" action="{{ route('production.quality-plans.submit', $plan->id) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item"><i class="feather-send me-2 text-muted fs-12"></i>Submit for Approval</button>
                                            </form>
                                        </li>
                                    @endcan
                                    @can('delete', $plan)
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('production.quality-plans.destroy', $plan->id) }}"
                                                  onsubmit="return confirm('Permanently delete this draft quality plan?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger"><i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('production.delete_permanent') }}</button>
                                            </form>
                                        </li>
                                    @endcan
                                @elseif ($plan->isPendingApproval())
                                    @can('approve', $plan)
                                        <li>
                                            <form method="POST" action="{{ route('production.quality-plans.approve', $plan->id) }}"
                                                  onsubmit="return confirm('Approve version {{ $plan->version }}? Any older approved version of this plan becomes historical.');">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-success"><i class="feather-check-circle me-2 text-success fs-12"></i>Approve</button>
                                            </form>
                                        </li>
                                    @endcan
                                    @can('reject', $plan)
                                        <li>
                                            <a href="{{ route('production.quality-plans.show', $plan->id) }}#reject" class="dropdown-item text-danger">
                                                <i class="feather-x-circle me-2 text-danger fs-12"></i>Reject</a>
                                        </li>
                                    @endcan
                                @elseif ($plan->isApproved())
                                    @can('update', $plan)
                                        <li>
                                            <a href="{{ route('production.quality-plans.edit', $plan->id) }}" class="dropdown-item">
                                                <i class="feather-git-branch me-2 text-muted fs-12"></i>Edit (New Version)</a>
                                        </li>
                                    @endcan
                                    @can('retire', $plan)
                                        <li>
                                            <form method="POST" action="{{ route('production.quality-plans.retire', $plan->id) }}"
                                                  onsubmit="return confirm('Mark this plan historical? It will no longer be offered for new inspections or routings.');">
                                                @csrf
                                                <button type="submit" class="dropdown-item"><i class="feather-archive me-2 text-muted fs-12"></i>Mark Historical</button>
                                            </form>
                                        </li>
                                    @endcan
                                @elseif ($plan->isHistorical())
                                    @php $current = $plan->latestApprovedVersion() ?? $plan->latestVersion(); @endphp
                                    @if ($current && $current->id !== $plan->id)
                                        <li>
                                            <a href="{{ route('production.quality-plans.show', $current->id) }}" class="dropdown-item">
                                                <i class="feather-arrow-right me-2 text-muted fs-12"></i>Open Current Version (v{{ $current->version }})</a>
                                        </li>
                                    @endif
                                @endif
                            </x-ui.action-dropdown>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="feather-info me-2 fs-16"></i>No Quality Control Plans registered.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>

        <div class="mt-4">
            {{ $plans->links() }}
        </div>
    </div>
@endsection
