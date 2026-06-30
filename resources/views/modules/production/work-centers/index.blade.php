@extends('layouts.duralux')

@section('title', 'Work Centers | SaaS ERP')
@section('page-title', 'Work Center Master')
@section('breadcrumb', 'Work Centers')

@section('page-actions')
    @can('create', App\Domains\Production\Models\WorkCenter::class)
        <a href="{{ route('production.work-centers.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>Create Work Center
        </a>
    @endcan
@endsection

@section('content')
    <!-- Alerts -->
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

    <!-- Filters -->
    <x-ui.card class="mb-4">
        <form method="GET" action="{{ route('production.work-centers.index') }}">
            <div class="row g-3">
                <div class="col-md-4">
                    <x-ui.input label="Search Work Center" name="search" placeholder="Code, name, or department..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-3">
                    <x-ui.select label="Filter by Type" name="work_center_type" :options="['' => 'All Types'] + $workCenterTypes" selected="{{ request('work_center_type') }}" />
                </div>
                <div class="col-md-3">
                    <x-ui.select label="Filter by Status" name="status" :options="[
                        '' => 'All Statuses',
                        'active' => 'Active',
                        'inactive' => 'Inactive'
                    ]" selected="{{ request('status') }}" />
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-grid w-100">
                        <button type="submit" class="btn btn-light-brand h-42">
                            <i class="feather-filter me-2"></i>Filter
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </x-ui.card>

    <!-- Work Centers Table -->
    <x-ui.card>
        <x-ui.table title="Manufacturing Work Centers" striped hoverable>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Department</th>
                    <th>Location</th>
                    <th class="text-end">Capacity/Hour</th>
                    <th class="text-end">Efficiency</th>
                    <th class="text-end">Cost/Hour</th>
                    <th class="text-center">Machines</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($workCenters as $wc)
                    <tr>
                        <td>
                            <a href="{{ route('production.work-centers.show', $wc->id) }}" class="fw-bold text-primary">
                                {{ $wc->code }}
                            </a>
                        </td>
                        <td>{{ $wc->name }}</td>
                        <td>
                            <span class="badge bg-soft-primary text-primary text-uppercase fs-10">
                                {{ $workCenterTypes[$wc->work_center_type] ?? $wc->work_center_type ?? 'N/A' }}
                            </span>
                        </td>
                        <td>{{ $wc->department_name ?? '—' }}</td>
                        <td>{{ $wc->location ?? '—' }}</td>
                        <td class="text-end fw-semibold">
                            {{ $wc->capacity_per_hour !== null ? number_format($wc->capacity_per_hour, 2) : 'Unlimited' }}
                        </td>
                        <td class="text-end">{{ number_format($wc->efficiency_percentage, 0) }}%</td>
                        <td class="text-end fw-semibold">${{ number_format($wc->cost_per_hour, 2) }}</td>
                        <td class="text-center">
                            <a href="{{ route('production.machines.index', ['work_center_id' => $wc->id]) }}" class="badge bg-soft-info text-info rounded-pill">
                                {{ $wc->machines_count }}
                            </a>
                        </td>
                        <td>
                            @if ($wc->isActive())
                                <span class="badge bg-soft-success text-success">Active</span>
                            @else
                                <span class="badge bg-soft-danger text-danger">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('production.work-centers.show', $wc->id) }}" class="btn btn-icon btn-light" title="View Details">
                                    <i class="feather-eye"></i>
                                </a>
                                @can('update', $wc)
                                    <a href="{{ route('production.work-centers.edit', $wc->id) }}" class="btn btn-icon btn-light" title="Edit">
                                        <i class="feather-edit"></i>
                                    </a>
                                @endcan
                                @can('delete', $wc)
                                    <form action="{{ route('production.work-centers.destroy', $wc->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this work center?');" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-icon btn-light text-danger" title="Delete">
                                            <i class="feather-trash-2"></i>
                                        </button>
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
        </x-ui.table>
    </x-ui.card>
@endsection
