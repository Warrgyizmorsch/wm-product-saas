@extends('layouts.duralux')

@section('title', 'Transporter Master | SaaS ERP')
@section('page-title', 'Transporter Master')
@section('breadcrumb', 'Logistics / Transporters')

@section('page-actions')
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTransporterModal">
        <i class="feather-plus me-1.5"></i>Add Transporter
    </button>
@endsection

@section('content')
<div class="erp-single-panel">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show fs-13 py-2.5 mb-3" role="alert">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Top Filter & Search Bar --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h5 class="fw-bold text-dark mb-0"><i class="feather-truck text-primary me-2"></i>Transporters Listing</h5>
        
        <div class="d-flex align-items-center flex-wrap gap-2">
            <!-- Search Box -->
            <form method="GET" action="{{ route('sales.transporters.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 260px;">
                @foreach(request()->except(['search', 'page']) as $k => $v)
                    @if(is_scalar($v) && $v !== '')
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endif
                @endforeach
                <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="Search name, ID, GSTIN, phone..." value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                @if(request('search'))
                    <a href="{{ route('sales.transporters.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                        <i class="feather-x fs-12"></i>
                    </a>
                @endif
            </form>

            <!-- Filter Dropdown Component -->
            <form method="GET" action="{{ route('sales.transporters.index') }}" class="d-inline">
                <x-ui.filter label="Filter" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Transporters</h6>
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="active" @selected(request('status') === 'active')>Active</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-between pt-2 border-top">
                        <a href="{{ route('sales.transporters.index') }}" class="btn btn-xs btn-light border">Reset</a>
                        <button type="submit" class="btn btn-xs btn-primary">Apply Filter</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>
    </div>

    {{-- Main Transporters Table Card --}}
    <div class="card border shadow-none">
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="transportersTable">
                <thead class="table-light fs-11 text-uppercase fw-bold text-muted">
                    <tr>
                        <th class="ps-4" style="width: 25%;">Transporter Name</th>
                        <th style="width: 20%;">15-Digit Transporter ID</th>
                        <th style="width: 15%;">GSTIN</th>
                        <th style="width: 15%;">Phone</th>
                        <th style="width: 13%;">City / State</th>
                        <th style="width: 7%;">Status</th>
                        <th class="text-end pe-4" style="width: 5%;">Action</th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
                    @forelse($transporters as $transporter)
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark">{{ $transporter->name }}</div>
                                @if($transporter->email)
                                    <small class="text-muted d-block fs-11">{{ $transporter->email }}</small>
                                @endif
                            </td>
                            <td>
                                @if($transporter->transporter_id)
                                    <span class="badge bg-light text-dark font-monospace border px-2 py-1 fs-12">{{ $transporter->transporter_id }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>
                                @if($transporter->gstin)
                                    <span class="font-monospace text-uppercase fs-12">{{ $transporter->gstin }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>{{ $transporter->phone ?: '—' }}</td>
                            <td>
                                @if($transporter->city || $transporter->state)
                                    <span>{{ implode(', ', array_filter([$transporter->city, $transporter->state])) }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>
                                @if($transporter->status === 'active')
                                    <span class="badge bg-soft-success text-success fs-11">Active</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary fs-11">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <button type="button" class="btn btn-xs btn-icon btn-light border" data-bs-toggle="modal" data-bs-target="#editTransporterModal{{ $transporter->id }}" title="Edit">
                                        <i class="feather-edit-2"></i>
                                    </button>
                                    <form method="POST" action="{{ route('sales.transporters.destroy', $transporter) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this transporter?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-icon btn-light text-danger border" title="Delete">
                                            <i class="feather-trash-2"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit Modal Component using x-ui.modal formAction -->
                        <x-ui.modal id="editTransporterModal{{ $transporter->id }}" title="Edit Transporter Master" size="md" :centered="true" :formAction="route('sales.transporters.update', $transporter)" formMethod="PUT" submitText="Save Changes" closeText="Cancel">
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="input" label="Transporter Name" name="name" :value="$transporter->name" :required="true" />
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
                                <x-ui.odoo-form-ui type="textarea" label="Office / Branch Address" name="address" rows="2" placeholder="Full address...">{{ $transporter->address }}</x-ui.odoo-form-ui>
                            </div>
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="input" label="City" name="city" :value="$transporter->city" />
                            </div>
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="input" label="State" name="state" :value="$transporter->state" />
                            </div>
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="input" label="Pincode" name="pincode" :value="$transporter->pincode" />
                            </div>
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="select" label="Status" name="status" :required="true" :searchable="false">
                                    <option value="active" @selected($transporter->status === 'active')>Active</option>
                                    <option value="inactive" @selected($transporter->status === 'inactive')>Inactive</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </x-ui.modal>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="feather-truck fs-32 d-block mb-2 text-muted"></i>
                                No transporters found. Click "Add Transporter" above to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>
        @if($transporters->hasPages())
            <div class="card-footer bg-white py-3 border-top">
                {{ $transporters->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Create Modal Component using x-ui.modal formAction -->
<x-ui.modal id="createTransporterModal" title="Add New Transporter Master" size="md" :centered="true" :formAction="route('sales.transporters.store')" submitText="Save Transporter" closeText="Cancel">
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" label="Transporter Name" name="name" :required="true" placeholder="e.g. Blue Dart Express, V-Trans, TCI Logistics" />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" label="15-Digit Transporter ID (E-Way Bill)" name="transporter_id" placeholder="Optional 15-digit E-Way Transporter ID" />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" label="GSTIN Number" name="gstin" placeholder="Optional 15-digit GSTIN" />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" label="Phone Number" name="phone" placeholder="Mobile / Landline" />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" inputType="email" label="Email Address" name="email" placeholder="email@domain.com" />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="textarea" label="Office / Branch Address" name="address" rows="2" placeholder="Full address..." />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" label="City" name="city" placeholder="City" />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" label="State" name="state" placeholder="State" />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" label="Pincode" name="pincode" placeholder="Pincode" />
    </div>
</x-ui.modal>
@endsection
