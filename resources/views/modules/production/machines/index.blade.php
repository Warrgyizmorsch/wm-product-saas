@extends('layouts.duralux')

@section('title', 'Machines | SaaS ERP')
@section('page-title', 'Machine Master')
@section('breadcrumb', 'Machines')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .erp-single-panel {
            display: flex !important;
            flex-direction: column !important;
            min-height: calc(100vh - 180px) !important;
        }
        .table-responsive:has(.dropdown.show) {
            overflow: visible !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.import-export-dropdown type="machines" importModalTarget="#importMachinesModal" />
        @can('create', App\Domains\Production\Models\Machine::class)
            <a href="{{ route('production.machines.create') }}" class="btn btn-primary">
                <i class="feather-plus me-2"></i>Create Machine
            </a>
        @endcan
    </div>
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'code');
        $sortOrder = request('sort_order', 'asc');
    @endphp

    <div class="erp-single-panel">
        {{-- Toast Notifications --}}
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Toolbar: Title + Sort + Filter --}}
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Machine List</h5>
            <div class="d-flex gap-2 ms-auto">
                {{-- Sort Dropdown --}}
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'code', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'code' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Code (A&ndash;Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'code', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'code' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Code (Z&ndash;A)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Name (A&ndash;Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Name (Z&ndash;A)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'installation_date', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'installation_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Installed Date (Newest)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'installation_date', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'installation_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Installed Date (Oldest)</span>
                    </a>
                </x-ui.sort-dropdown>

                {{-- Filter Overlay --}}
                <form method="GET" action="{{ route('production.machines.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search code, name, model, type..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Work Center</label>
                            <x-ui.odoo-form-ui type="select" name="work_center_id">
                                <option value="">All Work Centers</option>
                                @foreach($workCenters as $wc)
                                    <option value="{{ $wc->id }}" {{ request('work_center_id') == $wc->id ? 'selected' : '' }}>
                                        {{ $wc->name }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                @foreach($statuses as $value => $label)
                                    <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.machines.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- Machines Table --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th style="width: 9%">Code</th>
                        <th style="width: 14%">Name</th>
                        <th style="width: 18%">Work Center</th>
                        <th style="width: 9%">Type</th>
                        <th style="width: 10%">Manufacturer</th>
                        <th style="width: 10%">Model Number</th>
                        <th style="width: 8%" class="text-end">Capacity/Hr</th>
                        <th style="width: 7%">Status</th>
                        <th style="width: 9%">Installed Date</th>
                        <th style="width: 5%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($machines as $machine)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td>
                                <span class="fw-bold text-dark">{{ $machine->code }}</span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $machine->name }}</span>
                            </td>
                            <td>
                                @if ($machine->workCenter)
                                    <div class="d-flex flex-column">
                                        <a href="{{ route('production.work-centers.show', $machine->work_center_id) }}" class="fw-semibold text-primary hover-primary">
                                            {{ $machine->workCenter->name }}
                                        </a>
                                        <small class="text-muted font-monospace fs-10">{{ $machine->workCenter->code }}</small>
                                    </div>
                                @else
                                    <span class="text-muted">Orphaned</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $machine->machine_type ?? '—' }}</td>
                            <td class="text-muted">{{ $machine->manufacturer ?? '—' }}</td>
                            <td class="text-muted">{{ $machine->model_number ?? '—' }}</td>
                            <td class="text-end fw-semibold">
                                {{ $machine->capacity !== null ? number_format($machine->capacity, 2) : 'Flexible' }}
                            </td>
                            <td>
                                @if ($machine->isActive())
                                    <span class="erp-badge-active">Active</span>
                                @elseif ($machine->isUnderMaintenance())
                                    <span class="erp-badge-pending">Maint.</span>
                                @elseif ($machine->isDecommissioned())
                                    <span class="badge bg-soft-dark text-dark rounded-pill px-2 py-1">Decom.</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger rounded-pill px-2 py-1">Inactive</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $machine->installation_date ? $machine->installation_date->format('Y-m-d') : '—' }}</td>
                            <td class="text-end">
                                <x-ui.action-dropdown>
                                    @can('update', $machine)
                                        <li>
                                            <a href="{{ route('production.machines.edit', $machine->id) }}" class="dropdown-item">
                                                <i class="feather-edit me-2 text-muted fs-12"></i>Edit Machine
                                            </a>
                                        </li>
                                    @endcan
                                    @can('delete', $machine)
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('production.machines.destroy', $machine->id) }}" method="POST"
                                                  onsubmit="return confirm('Are you sure you want to delete this machine?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="feather-trash-2 me-2 text-danger fs-12"></i>Delete Permanent
                                                </button>
                                            </form>
                                        </li>
                                    @endcan
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted">
                                <i class="feather-info me-2 fs-16"></i>No machines found. Click "Create Machine" to start.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="mt-4">
            {{ $machines->links() }}
        </div>
    </div>

    {{-- Import Machines Modal --}}
    <x-ui.modal id="importMachinesModal" title="Import Machines via Excel/CSV" submitText="Import File" :centered="true">
        <form method="POST" action="{{ route('production.import-export.import-preview', 'machines') }}" enctype="multipart/form-data" id="importMachinesForm">
            @csrf
            <p class="fs-13 text-muted mb-3">Upload an Excel (.xlsx, .xls) or CSV (.csv) file containing Machine records. Make sure the headers match the column names in the template file.</p>
            <x-ui.odoo-form-ui type="file" name="file" label="Excel/CSV File" required placeholder="Choose file..." />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" form="importMachinesForm" class="btn btn-primary">Import File</button>
        </x-slot>
    </x-ui.modal>
@endsection
