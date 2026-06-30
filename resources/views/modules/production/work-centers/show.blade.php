@extends('layouts.duralux')

@section('title', 'Work Center Details | SaaS ERP')
@section('page-title', 'Work Center Details')
@section('breadcrumb', $workCenter->code)

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('production.work-centers.index') }}" class="btn btn-secondary">
            <i class="feather-arrow-left me-2"></i>Back to List
        </a>
        @can('update', $workCenter)
            <a href="{{ route('production.work-centers.edit', $workCenter->id) }}" class="btn btn-primary">
                <i class="feather-edit me-2"></i>Edit
            </a>
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

    <div class="row g-4">
        <!-- Main Stats & Info -->
        <div class="col-xl-4">
            <x-ui.card title="Work Center Status" class="h-100">
                <div class="text-center py-4 mb-3 border-bottom">
                    <div class="avatar-text avatar-xl bg-soft-primary text-primary mx-auto mb-3 rounded-circle" style="width: 80px; height: 80px; font-size: 32px; display: flex; align-items: center; justify-content: center;">
                        <i class="feather-box"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">{{ $workCenter->name }}</h4>
                    <span class="fs-13 fw-semibold text-muted text-uppercase">{{ $workCenter->code }}</span>
                    <div class="mt-3">
                        @if ($workCenter->isActive())
                            <span class="badge bg-soft-success text-success px-3 py-2 rounded-pill">Active / Operating</span>
                        @else
                            <span class="badge bg-soft-danger text-danger px-3 py-2 rounded-pill">Inactive / Suspended</span>
                        @endif
                    </div>
                </div>

                <div class="d-flex flex-column gap-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">Work Center Type:</span>
                        <span class="fw-bold text-dark text-uppercase fs-12">
                            {{ config('production.work_center_types')[$workCenter->work_center_type] ?? $workCenter->work_center_type ?? 'N/A' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">Department:</span>
                        <span class="fw-semibold text-dark">{{ $workCenter->department_name ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">Physical Location:</span>
                        <span class="fw-semibold text-dark">{{ $workCenter->location ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fs-13">Created Date:</span>
                        <span class="fw-semibold text-dark">{{ $workCenter->created_at->format('Y-m-d') }}</span>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <div class="col-xl-4">
            <x-ui.card title="Capacity & Operations Parameters" class="h-100">
                <div class="d-flex flex-column gap-4 py-2">
                    <div>
                        <span class="text-muted fs-12 text-uppercase d-block mb-1">Nominal Capacity per Hour</span>
                        <span class="fs-24 fw-bold text-dark">
                            {{ $workCenter->capacity_per_hour !== null ? number_format($workCenter->capacity_per_hour, 2) . ' Units' : 'Unlimited' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-muted fs-12 text-uppercase d-block mb-1">Operational Efficiency</span>
                        <span class="fs-24 fw-bold text-dark">{{ number_format($workCenter->efficiency_percentage, 0) }}%</span>
                        <div class="progress progress-xs mt-2" style="height: 6px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $workCenter->efficiency_percentage }}%;" aria-valuenow="{{ $workCenter->efficiency_percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div>
                        <span class="text-muted fs-12 text-uppercase d-block mb-1">Effective Capacity</span>
                        <span class="fs-20 fw-bold text-success">
                            {{ $workCenter->capacity_per_hour !== null ? number_format($workCenter->effectiveCapacityPerHour(), 2) . ' Units/Hr' : 'Unlimited' }}
                        </span>
                        <small class="text-muted d-block mt-1">Based on nominal capacity adjusted by {{ number_format($workCenter->efficiency_percentage, 0) }}% efficiency.</small>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <div class="col-xl-4">
            <x-ui.card title="Operating Cost Structure" class="h-100">
                <div class="d-flex flex-column gap-4 py-2">
                    <div>
                        <span class="text-muted fs-12 text-uppercase d-block mb-1">Overhead Cost Rate</span>
                        <span class="fs-28 fw-bold text-dark">${{ number_format($workCenter->cost_per_hour, 2) }} <span class="fs-14 fw-normal text-muted">/ Hour</span></span>
                        <small class="text-muted d-block mt-1">This rate includes floor space, power, maintenance, and shared department overhead.</small>
                    </div>
                    
                    <div class="border-top pt-3 mt-2">
                        <h6 class="fw-bold mb-2">Cost Calculations Quick Guide</h6>
                        <ul class="fs-12 text-muted ps-3 mb-0">
                            <li class="mb-1">Hourly Cost = ${{$workCenter->cost_per_hour}}</li>
                            <li>Applied to Setup time & Processing time in routing calculations.</li>
                        </ul>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <!-- Description Card -->
        @if ($workCenter->description)
            <div class="col-12">
                <x-ui.card title="Work Center Purpose & Notes">
                    <p class="mb-0 fs-13 text-muted" style="white-space: pre-line;">{{ $workCenter->description }}</p>
                </x-ui.card>
            </div>
        @endif

        <!-- Assigned Machines List -->
        <div class="col-12">
            <x-ui.card>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0 fw-bold">Assigned Equipment & Machines</h5>
                    @can('create', App\Domains\Production\Models\Machine::class)
                        <a href="{{ route('production.machines.create', ['work_center_id' => $workCenter->id]) }}" class="btn btn-sm btn-soft-primary">
                            <i class="feather-plus me-1"></i>Add Machine
                        </a>
                    @endcan
                </div>

                <x-ui.table striped>
                    <thead>
                        <tr>
                            <th>Machine Code</th>
                            <th>Machine Name</th>
                            <th>Machine Type</th>
                            <th>Manufacturer</th>
                            <th>Model Number</th>
                            <th class="text-end">Capacity/Hour</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workCenter->machines as $machine)
                            <tr>
                                <td class="fw-bold">{{ $machine->code }}</td>
                                <td>{{ $machine->name }}</td>
                                <td>{{ $machine->machine_type ?? '—' }}</td>
                                <td>{{ $machine->manufacturer ?? '—' }}</td>
                                <td>{{ $machine->model_number ?? '—' }}</td>
                                <td class="text-end">
                                    {{ $machine->capacity !== null ? number_format($machine->capacity, 2) : '—' }}
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
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('production.machines.edit', $machine->id) }}" class="btn btn-icon btn-light btn-sm" title="Edit Machine">
                                            <i class="feather-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>No machines registered in this work center.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>
    </div>
@endsection
