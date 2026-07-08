@extends('layouts.duralux')

@section('title', 'Quality Control Plans | SaaS ERP')
@section('page-title', 'Quality Control Plans')
@section('breadcrumb', 'Quality Plans')

@section('page-actions')
    <a href="{{ route('production.quality-plans.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>Configure New Plan
    </a>
@endsection

@section('content')
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif

    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    <div class="erp-single-panel">
        <!-- Toolbar: Search, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Quality Control Plans Registry</h5>
            <div class="d-flex gap-2 ms-auto">
                <form method="GET" action="{{ route('production.quality-plans.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.input name="search" placeholder="Search by plan name..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Plan Scope Type</label>
                            <select name="type" class="form-select fs-12">
                                <option value="">All Types</option>
                                <option value="product" @selected(request('type') === 'product')>Product Specific</option>
                                <option value="product_category" @selected(request('type') === 'product_category')>Category Specific</option>
                                <option value="process" @selected(request('type') === 'process')>Process General</option>
                                <option value="work_center" @selected(request('type') === 'work_center')>Work Center Specific</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status State</label>
                            <select name="status" class="form-select fs-12">
                                <option value="">All Statuses</option>
                                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                                <option value="submitted" @selected(request('status') === 'submitted')>Submitted</option>
                                <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                                <option value="archived" @selected(request('status') === 'archived')>Archived</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.quality-plans.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Quality Plans Table -->
        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width: 25%">Plan Name</th>
                    <th style="width: 10%">Version</th>
                    <th style="width: 15%">Scope Type</th>
                    <th style="width: 25%">Assigned Target</th>
                    <th style="width: 10%">Status</th>
                    <th class="text-end" style="width: 15%">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($plans as $plan)
                    <tr>
                        <td class="fw-bold text-dark">
                            <a href="{{ route('production.quality-plans.edit', $plan->id) }}" class="text-dark hover-primary">
                                {{ $plan->name }}
                            </a>
                        </td>
                        <td class="font-monospace text-muted">v{{ $plan->version }}</td>
                        <td>
                            <span class="badge bg-soft-info text-info text-uppercase font-monospace fs-10">
                                {{ str_replace('_', ' ', $plan->type) }}
                            </span>
                        </td>
                        <td>
                            @if($plan->type === 'product' && $plan->product)
                                <span class="fw-medium text-dark"><i class="feather-box me-1 text-muted"></i>{{ $plan->product->name }}</span>
                            @elseif($plan->type === 'work_center' && $plan->workCenter)
                                <span class="fw-medium text-dark"><i class="feather-settings me-1 text-muted"></i>{{ $plan->workCenter->name }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($plan->status === 'approved')
                                <span class="erp-badge-active">Approved</span>
                            @elseif($plan->status === 'draft')
                                <span class="erp-badge-draft">Draft</span>
                            @else
                                <span class="badge bg-soft-warning text-warning text-capitalize">{{ $plan->status }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="{{ route('production.quality-plans.edit', $plan->id) }}" class="btn btn-xs btn-outline-primary py-1"><i class="feather-edit-3 me-1"></i>Edit</a>
                                <form method="POST" action="{{ route('production.quality-plans.destroy', $plan->id) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Quality Plan?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger py-1"><i class="feather-trash-2"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
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
