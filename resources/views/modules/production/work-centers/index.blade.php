@extends('layouts.duralux')

@section('title', 'Work Centers | SaaS ERP')
@section('page-title', 'Work Center Master')
@section('breadcrumb', 'Work Centers')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    @can('create', App\Domains\Production\Models\WorkCenter::class)
        <a href="{{ route('production.work-centers.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>Create Work Center
        </a>
    @endcan
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        <!-- Success & Error Messages -->
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

        <!-- Inlined Filters Section -->
        <form method="GET" action="{{ route('production.work-centers.index') }}" class="mb-4 pb-3 border-bottom">
            <div class="row g-3">
                <div class="col-md-4">
                    <x-ui.input name="search" placeholder="Search code, name, or department..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-3">
                    <x-ui.select name="work_center_type" :options="['' => 'All Types'] + $workCenterTypes" selected="{{ request('work_center_type') }}" data-select2-selector="default" />
                </div>
                <div class="col-md-3">
                    <x-ui.select name="status" :options="[
                        '' => 'All Statuses',
                        'active' => 'Active',
                        'inactive' => 'Inactive'
                    ]" selected="{{ request('status') }}" data-select2-selector="default" />
                </div>
                <div class="col-md-2 d-flex align-items-start">
                    <div class="d-grid w-100">
                        <button type="submit" class="btn btn-secondary h-40">
                            <i class="feather-filter me-2"></i>Filter
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Work Centers Table (Dense & Thin row) -->
        <div class="table-responsive">
            <table class="erp-thin-table">
                <thead>
                    <tr>
                        <th style="width: 10%">Code</th>
                        <th style="width: 15%">Name</th>
                        <th style="width: 18%">Hierarchy Path</th>
                        <th style="width: 10%">Type</th>
                        <th style="width: 12%">Department</th>
                        <th style="width: 10%">Location</th>
                        <th style="width: 7%" class="text-end">Capacity/Hr</th>
                        <th style="width: 6%" class="text-end">Efficiency</th>
                        <th style="width: 6%" class="text-end">Cost/Hr</th>
                        <th style="width: 6%" class="text-center">Machines</th>
                        <th style="width: 6%">Status</th>
                        <th style="width: 8%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($workCenters as $wc)
                        <tr>
                            <td class="align-middle">
                                <a href="{{ route('production.work-centers.show', $wc->id) }}" class="fw-bold text-primary">
                                    {{ $wc->code }}
                                </a>
                            </td>
                            <td class="align-middle text-dark fw-medium">{{ $wc->name }}</td>
                            <td class="align-middle fs-11 text-muted">{{ $wc->getHierarchyPath() }}</td>
                            <td class="align-middle">
                                <span class="badge bg-soft-primary text-primary text-uppercase fs-10 rounded-pill px-2 py-1">
                                    {{ $workCenterTypes[$wc->work_center_type] ?? $wc->work_center_type ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="align-middle">{{ $wc->department_name ?? '—' }}</td>
                            <td class="align-middle">{{ $wc->location ?? '—' }}</td>
                            <td class="text-end align-middle fw-semibold">
                                {{ $wc->capacity_per_hour !== null ? number_format($wc->capacity_per_hour, 2) : 'Unlimited' }}
                            </td>
                            <td class="text-end align-middle text-muted">{{ number_format($wc->efficiency_percentage, 0) }}%</td>
                            <td class="text-end align-middle fw-semibold text-dark">${{ number_format($wc->cost_per_hour, 2) }}</td>
                            <td class="text-center align-middle">
                                <a href="{{ route('production.machines.index', ['work_center_id' => $wc->id]) }}" class="badge bg-soft-info text-info rounded-pill px-2 py-1 fw-bold">
                                    {{ $wc->machines_count }}
                                </a>
                            </td>
                            <td class="align-middle">
                                @if ($wc->isActive())
                                    <span class="badge bg-soft-success text-success rounded-pill px-2 py-1">Active</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger rounded-pill px-2 py-1">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end align-middle">
                                <div class="d-inline-flex gap-1">
                                    <x-ui.icon-btn href="{{ route('production.work-centers.show', $wc->id) }}" variant="light" size="sm" icon="feather-eye" title="View Details" />
                                    @can('update', $wc)
                                        <x-ui.icon-btn href="{{ route('production.work-centers.edit', $wc->id) }}" variant="light" size="sm" icon="feather-edit" title="Edit" />
                                    @endcan
                                    @can('delete', $wc)
                                        <form action="{{ route('production.work-centers.destroy', $wc->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this work center?');" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.icon-btn type="submit" variant="light" size="sm" icon="feather-trash-2" class="text-danger" title="Delete" />
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted">
                                <i class="feather-info me-2"></i>No work centers found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $workCenters->links() }}
        </div>
    </div>
@endsection
