@extends('layouts.duralux')

@section('title', 'Transporters Directory | SaaS ERP')
@section('page-title', 'Transporters Directory')
@section('breadcrumb', 'Logistics / Transporters')

@section('page-actions')
    <x-ui.button href="{{ route('platform.transporters.create') }}" variant="primary" icon="feather-plus">
        NEW TRANSPORTER
    </x-ui.button>
@endsection

@section('content')

    @php
        $sortBy = request('sort_by', 'created_at');
        $sortOrder = request('sort_order', 'desc');
        $activeStatus = request('status');
    @endphp

    <div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show fs-13 py-2.5 mb-3" role="alert">
                <i class="feather-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- 1. Header & Actions Toolbar --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0"><i class="feather-truck text-primary me-2"></i>Transporters Listing</h5>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Search Box -->
                <form method="GET" action="{{ route('platform.transporters.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 260px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="Search name, ID, GSTIN, phone..." value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('platform.transporters.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown label="SORT">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Latest Created</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Oldest Created</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Transporter Name (A - Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Transporter Name (Z - A)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <form method="GET" action="{{ route('platform.transporters.index') }}" class="d-inline">
                    @foreach(request()->except(['status', 'search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <x-ui.filter label="FILTER" offset="0, 5">
                        <div class="p-3 style-filter-menu" style="min-width: 250px;">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                                <x-ui.odoo-form-ui type="input" name="search" placeholder="Search transporter name, phone..." value="{{ request('search') }}" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Transporter Status</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="all" @selected(request('status', 'all') === 'all')>All Transporters</option>
                                    <option value="active" @selected(request('status') === 'active')>Active Only</option>
                                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive Only</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="d-flex justify-content-between pt-2 border-top">
                                <a href="{{ route('platform.transporters.index') }}" class="btn btn-xs btn-light">Reset</a>
                                <x-ui.button type="submit" variant="primary" size="xs">Apply Filter</x-ui.button>
                            </div>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- 2. Common ERP Odoo Table Component --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="transportersTable" class="mb-0">
                <thead>
                    <tr style="background-color: #e8ecf1 !important;">
                        <th style="width: 35px; background-color: #e8ecf1 !important;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="selectAllTransporters">
                        </th>
                        <th style="background-color: #e8ecf1 !important;">Transporter Name</th>
                        <th style="background-color: #e8ecf1 !important;">15-Digit Transporter ID</th>
                        <th style="background-color: #e8ecf1 !important;">GSTIN / PAN</th>
                        <th style="background-color: #e8ecf1 !important;">Phone / Mobile</th>
                        <th style="background-color: #e8ecf1 !important;">City / State</th>
                        <th style="background-color: #e8ecf1 !important;">Status</th>
                        <th style="width: 8%; background-color: #e8ecf1 !important;" class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transporters as $transporter)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input transporter-checkbox">
                            </td>
                            <td>
                                <div>
                                    <a href="{{ route('platform.transporters.show', $transporter) }}" class="fw-bold text-dark hover-primary text-decoration-none d-block">
                                        {{ $transporter->name }}
                                    </a>
                                    <div class="fs-11 text-muted">
                                        @if($transporter->code)
                                            <span class="font-monospace text-primary fw-semibold">Code: {{ $transporter->code }}</span>
                                        @endif
                                        @if($transporter->email)
                                            <span class="ms-1"><i class="feather-mail me-1"></i>{{ $transporter->email }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($transporter->transporter_id)
                                    <span class="badge bg-light text-dark font-monospace border px-2 py-1 fs-11">{{ $transporter->transporter_id }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>
                                @if($transporter->gstin)
                                    <span class="font-monospace text-uppercase fs-12 text-primary fw-bold">{{ $transporter->gstin }}</span>
                                @elseif($transporter->pan_number)
                                    <span class="font-monospace text-uppercase fs-12 text-dark">{{ $transporter->pan_number }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>
                                @if($transporter->phone)
                                    <span class="text-dark"><i class="feather-phone me-1 text-muted fs-11"></i>{{ $transporter->phone }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>
                                @if($transporter->city || $transporter->state)
                                    <span>{{ implode(', ', array_filter([$transporter->city, $transporter->state])) }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>
                                @if(strtolower($transporter->status) === 'active')
                                    <x-ui.status-badge status="active" label="Active" size="sm" />
                                @else
                                    <x-ui.status-badge status="inactive" label="Inactive" size="sm" />
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <x-ui.action-dropdown :viewUrl="route('platform.transporters.show', $transporter)">
                                    <li>
                                        <a href="{{ route('platform.transporters.show', $transporter) }}" class="dropdown-item fs-12 py-1.5">
                                            <i class="feather-eye me-2 text-primary"></i>View 360° Profile
                                        </a>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item fs-12 py-1.5" data-bs-toggle="modal" data-bs-target="#editTransporterModal{{ $transporter->id }}">
                                            <i class="feather-edit-2 me-2 text-info"></i>Edit Quick Master
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li>
                                        <form method="POST" action="{{ route('platform.transporters.destroy', $transporter) }}" onsubmit="return confirm('Are you sure you want to delete this transporter?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item fs-12 py-1.5 text-danger">
                                                <i class="feather-trash-2 me-2"></i>Delete Transporter
                                            </button>
                                        </form>
                                    </li>
                                </x-ui.action-dropdown>

                                <!-- Edit Modal Component for each row -->
                                <x-ui.modal id="editTransporterModal{{ $transporter->id }}" title="Edit Transporter Master" size="md" :centered="true" :formAction="route('platform.transporters.update', $transporter)" formMethod="PUT" submitText="Save Changes" closeText="Cancel">
                                    <div class="mb-3">
                                        <x-ui.odoo-form-ui type="input" label="Transporter Name" name="name" :value="$transporter->name" :required="true" />
                                    </div>
                                    <div class="mb-3">
                                        <x-ui.odoo-form-ui type="input" label="Transporter Code" name="code" :value="$transporter->code" placeholder="e.g. TRP-001" />
                                    </div>
                                    <div class="mb-3">
                                        <x-ui.odoo-form-ui type="input" label="15-Digit Transporter ID (E-Way Bill)" name="transporter_id" :value="$transporter->transporter_id" placeholder="Optional 15-digit ID" />
                                    </div>
                                    <div class="mb-3">
                                        <x-ui.odoo-form-ui type="input" label="GSTIN Number" name="gstin" :value="$transporter->gstin" placeholder="Optional 15-digit GSTIN" />
                                    </div>
                                    <div class="mb-3">
                                        <x-ui.odoo-form-ui type="input" label="Phone Number" name="phone" :value="$transporter->phone" placeholder="Mobile / Landline" />
                                    </div>
                                    <div class="mb-3">
                                        <x-ui.odoo-form-ui type="input" inputType="email" label="Email Address" name="email" :value="$transporter->email" placeholder="email@domain.com" />
                                    </div>
                                    <div class="mb-3">
                                        <x-ui.odoo-form-ui type="select" label="Status" name="status" :required="true" :searchable="false">
                                            <option value="active" @selected($transporter->status === 'active')>Active</option>
                                            <option value="inactive" @selected($transporter->status === 'inactive')>Inactive</option>
                                        </x-ui.odoo-form-ui>
                                    </div>
                                </x-ui.modal>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="feather-truck fs-32 d-block mb-2 text-muted"></i>
                                No transporters found matching your criteria.
                                <div class="mt-2">
                                    <a href="{{ route('platform.transporters.create') }}" class="btn btn-sm btn-primary">
                                        <i class="feather-plus me-1"></i>New Transporter Master
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        {{-- 3. Common ERP Pagination Component --}}
        @if($transporters->hasPages())
            <div class="pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fs-12 text-muted">Showing {{ $transporters->firstItem() }} to {{ $transporters->lastItem() }} of {{ $transporters->total() }} transporters</span>
                {{ $transporters->links() }}
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('selectAllTransporters');
        const checkboxes = document.querySelectorAll('.transporter-checkbox');

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes.forEach(cb => cb.checked = selectAll.checked);
            });
        }
    });
</script>
@endpush
