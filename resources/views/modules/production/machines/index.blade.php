@extends('layouts.duralux')

@section('title', 'Machines | SaaS ERP')
@section('page-title', 'Machine Master')
@section('breadcrumb', 'Machines')

@section('page-actions')
    @can('create', App\Domains\Production\Models\Machine::class)
        <a href="{{ route('production.machines.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>Create Machine
        </a>
    @endcan
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

    <!-- Filters -->
    <x-ui.card class="mb-4">
        <form method="GET" action="{{ route('production.machines.index') }}">
            <div class="row g-3">
                <div class="col-md-4">
                    <x-ui.input label="Search Machine" name="search" placeholder="Code, name, model, type..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-3">
                    <x-ui.select label="Filter by Work Center" name="work_center_id" :options="['' => 'All Work Centers'] + $workCenters->pluck('name', 'id')->toArray()" selected="{{ request('work_center_id') }}" />
                </div>
                <div class="col-md-3">
                    <x-ui.select label="Filter by Status" name="status" :options="['' => 'All Statuses'] + $statuses" selected="{{ request('status') }}" />
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

    <!-- Machines List Table -->
    <x-ui.card>
        <x-ui.table title="Equipment & Machine Master Data" striped hoverable>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Work Center</th>
                    <th>Type</th>
                    <th>Manufacturer</th>
                    <th>Model Number</th>
                    <th class="text-end">Capacity/Hour</th>
                    <th>Status</th>
                    <th>Installation Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($machines as $machine)
                    <tr>
                        <td class="fw-bold">{{ $machine->code }}</td>
                        <td>{{ $machine->name }}</td>
                        <td>
                            @if ($machine->workCenter)
                                <a href="{{ route('production.work-centers.show', $machine->work_center_id) }}" class="fw-semibold text-primary">
                                    {{ $machine->workCenter->name }} ({{ $machine->workCenter->code }})
                                </a>
                            @else
                                <span class="text-muted">Orphaned</span>
                            @endif
                        </td>
                        <td>{{ $machine->machine_type ?? '—' }}</td>
                        <td>{{ $machine->manufacturer ?? '—' }}</td>
                        <td>{{ $machine->model_number ?? '—' }}</td>
                        <td class="text-end fw-semibold">
                            {{ $machine->capacity !== null ? number_format($machine->capacity, 2) : 'Flexible' }}
                        </td>
                        <td>
                            @if ($machine->isActive())
                                <span class="badge bg-soft-success text-success">Active</span>
                            @elseif ($machine->isUnderMaintenance())
                                <span class="badge bg-soft-warning text-warning">Under Maintenance</span>
                            @elseif ($machine->isDecommissioned())
                                <span class="badge bg-soft-dark text-dark">Decommissioned</span>
                            @else
                                <span class="badge bg-soft-danger text-danger">Inactive</span>
                            @endif
                        </td>
                        <td>{{ $machine->installation_date ? $machine->installation_date->format('Y-m-d') : '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                @can('update', $machine)
                                    <a href="{{ route('production.machines.edit', $machine->id) }}" class="btn btn-icon btn-light" title="Edit Machine">
                                        <i class="feather-edit"></i>
                                    </a>
                                @endcan
                                @can('delete', $machine)
                                    <form action="{{ route('production.machines.destroy', $machine->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this machine?');" class="d-inline">
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
                        <td colspan="10" class="text-center py-4 text-muted">
                            <i class="feather-info me-2"></i>No machines found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>
@endsection
