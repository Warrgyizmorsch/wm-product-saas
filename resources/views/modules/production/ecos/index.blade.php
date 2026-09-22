@extends('layouts.duralux')

@section('title', 'Engineering Change Orders (ECO) | SaaS ERP')
@section('page-title', 'Engineering Change Orders (ECO)')
@section('breadcrumb', 'ECO Management')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('production.ecos.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>New ECO
        </a>
    </div>
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'created_at');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('warning'))
            <x-ui.toast :auto="true" type="warning" title="{{ session('warning') }}" />
        @endif

        <!-- Toolbar: Sort & Filter -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Engineering Change Orders</h5>
            <div class="d-flex gap-2 ms-auto">
                <div id="normal-toolbar" class="d-flex gap-2">
                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown label="Sort">
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('production.sort_newest_first') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('production.sort_oldest_first') }}</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'eco_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'eco_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>ECO Number</span>
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Component -->
                    <form method="GET" action="{{ route('production.ecos.index') }}" class="d-inline">
                        <x-ui.filter label="Filter" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter ECOs</h6>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.search_keywords') }}</label>
                                <x-ui.odoo-form-ui type="input" name="search" placeholder="Search ECO number or title..." value="{{ request('search') }}" />
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="">{{ __('production.all_statuses') }}</option>
                                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>{{ __('production.draft_schedules') }}</option>
                                    <option value="under_review" {{ request('status') == 'under_review' ? 'selected' : '' }}>Under Review</option>
                                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>{{ __('production.approved') }}</option>
                                    <option value="released" {{ request('status') == 'released' ? 'selected' : '' }}>{{ __('production.released_schedules') }}</option>
                                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>{{ __('production.rejected') }}</option>
                                    <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>{{ __('production.closed') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Change Type</label>
                                <x-ui.odoo-form-ui type="select" name="change_type">
                                    <option value="">All Change Types</option>
                                    <option value="BOM_CHANGE" {{ request('change_type') == 'BOM_CHANGE' ? 'selected' : '' }}>BOM Change</option>
                                    <option value="ROUTING_CHANGE" {{ request('change_type') == 'ROUTING_CHANGE' ? 'selected' : '' }}>Routing Change</option>
                                    <option value="BOM_AND_ROUTING_CHANGE" {{ request('change_type') == 'BOM_AND_ROUTING_CHANGE' ? 'selected' : '' }}>BOM & Routing Change</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('production.ecos.index') }}" class="btn btn-sm btn-light border">{{ __('production.reset') }}</a>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('production.apply_filters') }}</button>
                            </div>
                        </x-ui.filter>
                    </form>
                </div>
            </div>
        </div>

        <!-- Table Component -->
        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width: 15%">ECO Number</th>
                    <th style="width: 25%">Title</th>
                    <th style="width: 15%">{{ __('production.product') }}</th>
                    <th style="width: 12%">Change Type</th>
                    <th style="width: 18%">Revisions</th>
                    <th style="width: 10%">{{ __('production.status') }}</th>
                    <th style="width: 5%" class="text-end">{{ __('production.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ecos as $eco)
                    <tr>
                        <td>
                            <a href="{{ route('production.ecos.show', $eco->id) }}" class="fw-bold text-primary text-decoration-none">
                                {{ $eco->eco_number }}
                            </a>
                        </td>
                        <td class="fw-semibold text-dark">{{ $eco->title }}</td>
                        <td>{{ $eco->product ? $eco->product->name : 'N/A' }}</td>
                        <td>
                            <span class="badge bg-soft-info text-info">{{ $eco->change_type }}</span>
                        </td>
                        <td>
                            <small class="text-muted">
                                BOM: Rev {{ $eco->current_bom_revision }} &rarr; Rev {{ $eco->proposed_bom_revision }} |
                                Rtg: Rev {{ $eco->current_routing_revision }} &rarr; Rev {{ $eco->proposed_routing_revision }}
                            </small>
                        </td>
                        <td>
                            @if($eco->status === 'draft')
                                <span class="badge bg-soft-secondary text-secondary">{{ __('production.draft_schedules') }}</span>
                            @elseif($eco->status === 'under_review')
                                <span class="badge bg-soft-warning text-warning">Under Review</span>
                            @elseif($eco->status === 'approved')
                                <span class="badge bg-soft-primary text-primary">{{ __('production.approved') }}</span>
                            @elseif($eco->status === 'released')
                                <span class="badge bg-soft-success text-success">{{ __('production.released_schedules') }}</span>
                            @elseif($eco->status === 'rejected')
                                <span class="badge bg-soft-danger text-danger">{{ __('production.rejected') }}</span>
                            @else
                                <span class="badge bg-soft-light text-dark">{{ $eco->status }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <x-ui.action-dropdown :viewUrl="route('production.ecos.show', $eco->id)" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            No Engineering Change Orders found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>

        @if($ecos->hasPages())
            <div class="mt-3">
                {{ $ecos->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
