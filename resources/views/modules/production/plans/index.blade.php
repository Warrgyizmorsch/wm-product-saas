@extends('layouts.duralux')

@section('title', __('production.production_planning') . ' | SaaS ERP')
@section('page-title', __('production.mps_planning'))
@section('breadcrumb', __('production.production_planning'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <a href="{{ route('production.plans.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>{{ __('production.create_production_plan') }}
    </a>
@endsection

@section('content')
<div class="erp-single-panel">
    <!-- KPI Status Summary -->
    <div class="row g-3 mb-4">
        <div class="col">
            <div class="bg-light border rounded p-3 text-center">
                <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('production.draft') }}</span>
                <h4 class="text-dark fw-bold mt-1 mb-0">{{ $statusCounts['draft'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col">
            <div class="bg-soft-warning border rounded p-3 text-center">
                <span class="text-warning fs-11 text-uppercase fw-bold">{{ __('production.pending') }}</span>
                <h4 class="text-warning fw-bold mt-1 mb-0">{{ $statusCounts['pending_approval'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col">
            <div class="bg-soft-success border rounded p-3 text-center">
                <span class="text-success fs-11 text-uppercase fw-bold">{{ __('production.approved') }}</span>
                <h4 class="text-success fw-bold mt-1 mb-0">{{ $statusCounts['approved'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col">
            <div class="bg-soft-info border rounded p-3 text-center">
                <span class="text-info fs-11 text-uppercase fw-bold">{{ __('production.mrp_generated') }}</span>
                <h4 class="text-info fw-bold mt-1 mb-0">{{ $statusCounts['mrp_generated'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col">
            <div class="bg-soft-primary border rounded p-3 text-center">
                <span class="text-primary fs-11 text-uppercase fw-bold">{{ __('production.released') }}</span>
                <h4 class="text-primary fw-bold mt-1 mb-0">{{ $statusCounts['released'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col">
            <div class="bg-light border rounded p-3 text-center">
                <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('production.completed_closed') }}</span>
                <h4 class="text-muted fw-bold mt-1 mb-0">{{ ($statusCounts['completed'] ?? 0) + ($statusCounts['closed'] ?? 0) }}</h4>
            </div>
        </div>
    </div>
    {{-- Toast alerts --}}
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif

    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    <!-- Toolbar: Sort, Filters -->
    <div class="d-flex align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0">{{ __('production.plans_list') }}</h5>
        <div class="d-flex gap-2 ms-auto">
            <!-- Sort dropdown -->
            @php
                $sortBy = request('sort_by', 'id');
                $sortOrder = request('sort_order', 'desc');
            @endphp
            <x-ui.sort-dropdown :label="__('production.sort')">
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'id' && $sortOrder === 'desc' ? 'active' : '' }}">
                    <span>{{ __('production.newest_first') ?? 'Newest First' }}</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'id' && $sortOrder === 'asc' ? 'active' : '' }}">
                    <span>{{ __('production.oldest_first') ?? 'Oldest First' }}</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'plan_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'plan_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                    <span>{{ __('production.plan_number') }} (Asc)</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'plan_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'plan_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                    <span>{{ __('production.plan_number') }} (Desc)</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                    <span>{{ __('production.plan_name') }} (A-Z)</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'desc' ? 'active' : '' }}">
                    <span>{{ __('production.plan_name') }} (Z-A)</span>
                </a>
            </x-ui.sort-dropdown>

            <!-- Custom Filter Component -->
            <form method="GET" action="{{ route('production.plans.index') }}" class="d-inline">
                <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('production.filter_options') }}</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.search_keywords') }}</label>
                        <x-ui.input name="search" placeholder="Search plan number, name or product..." value="{{ request('search') }}" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.status') }}</label>
                        <x-ui.select name="status" :options="[
                            '' => __('production.all_statuses'),
                            'draft' => __('production.draft'),
                            'pending_approval' => __('production.pending'),
                            'approved' => __('production.approved'),
                            'mrp_generated' => __('production.mrp_generated'),
                            'released' => __('production.released'),
                            'completed' => __('production.completed'),
                            'closed' => __('production.closed'),
                            'cancelled' => __('production.cancelled')
                        ]" selected="{{ request('status') }}" data-select2-selector="default" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.date_range') ?? 'Date Range' }}</label>
                        <div class="d-flex gap-2">
                            <x-ui.input type="date" name="start_date" placeholder="Start Date" value="{{ request('start_date') }}" />
                            <x-ui.input type="date" name="end_date" placeholder="End Date" value="{{ request('end_date') }}" />
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('production.plans.index') }}" class="btn btn-sm btn-light border">{{ __('production.reset') }}</a>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('production.apply_filters') }}</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>
    </div>

        <!-- Plans Table -->
        <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 12%">{{ __('production.plan_number') }}</th>
                        <th style="width: 20%">{{ __('production.plan_name') }}</th>
                        <th style="width: 20%">{{ __('production.item_to_produce') }}</th>
                        <th style="width: 10%" class="text-end">{{ __('production.target_qty') }}</th>
                        <th style="width: 10%">{{ __('production.start_date') }}</th>
                        <th style="width: 10%">{{ __('production.end_date') }}</th>
                        <th style="width: 10%">{{ __('production.status') }}</th>
                        <th style="width: 8%" class="text-end">{{ __('production.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $plan)
                        <tr>
                            <td class="align-middle">
                                <a href="{{ route('production.plans.show', $plan->id) }}" class="fw-bold text-primary">
                                    {{ $plan->plan_number }}
                                </a>
                            </td>
                            <td class="align-middle text-dark fw-medium">{{ $plan->name }}</td>
                            <td class="align-middle">
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-dark">{{ $plan->product->name }}</span>
                                    <small class="text-muted font-monospace fs-10">{{ $plan->product->sku }}</small>
                                </div>
                            </td>
                            <td class="align-middle text-end fw-semibold text-dark">{{ number_format($plan->quantity, 2) }}</td>
                            <td class="align-middle">{{ $plan->start_date->format('d/m/Y') }}</td>
                            <td class="align-middle">{{ $plan->end_date->format('d/m/Y') }}</td>
                            <td class="align-middle">
                                @if($plan->status === 'draft')
                                    <span class="badge bg-soft-secondary text-secondary text-uppercase fs-10 rounded-pill px-2 py-1">{{ __('production.draft') }}</span>
                                @elseif($plan->status === 'pending_approval')
                                    <span class="badge bg-soft-warning text-warning text-uppercase fs-10 rounded-pill px-2 py-1">{{ __('production.pending') }}</span>
                                @elseif($plan->status === 'approved')
                                    <span class="badge bg-soft-success text-success text-uppercase fs-10 rounded-pill px-2 py-1">{{ __('production.approved') }}</span>
                                @elseif($plan->status === 'mrp_generated')
                                    <span class="badge bg-soft-info text-info text-uppercase fs-10 rounded-pill px-2 py-1">{{ __('production.mrp_run') }}</span>
                                @elseif($plan->status === 'released')
                                    <span class="badge bg-soft-primary text-primary text-uppercase fs-10 rounded-pill px-2 py-1">{{ __('production.released') }}</span>
                                @elseif($plan->status === 'completed')
                                    <span class="badge bg-soft-success text-success text-uppercase fs-10 rounded-pill px-2 py-1">{{ __('production.completed') }}</span>
                                @elseif($plan->status === 'closed')
                                    <span class="badge bg-soft-dark text-dark text-uppercase fs-10 rounded-pill px-2 py-1">{{ __('production.closed') }}</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger text-uppercase fs-10 rounded-pill px-2 py-1">{{ __('production.cancelled') }}</span>
                                @endif
                            </td>
                            <td class="align-middle text-end">
                                <div class="d-inline-flex gap-1">
                                    <x-ui.icon-btn href="{{ route('production.plans.show', $plan->id) }}" variant="light" size="sm" icon="feather-eye" :title="__('production.view_details')" />
                                    
                                    @if(!$plan->isFrozen())
                                        @can('update', $plan)
                                            <x-ui.icon-btn href="{{ route('production.plans.edit', $plan->id) }}" variant="light" size="sm" icon="feather-edit" :title="__('production.edit')" />
                                        @endcan
                                        @can('delete', $plan)
                                            <form action="{{ route('production.plans.destroy', $plan->id) }}" method="POST" onsubmit="return confirm('{{ __('production.confirm_delete_plan') }}');" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.icon-btn type="submit" variant="light" size="sm" icon="feather-trash-2" class="text-danger" :title="__('production.delete')" />
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="feather-info me-2"></i>{{ __('production.no_production_plans') }}
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
