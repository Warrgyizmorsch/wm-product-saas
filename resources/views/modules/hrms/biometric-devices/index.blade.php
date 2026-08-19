@extends('layouts.duralux')

@section('title', 'Biometric Device Master | SaaS ERP')
@section('page-title', 'Biometric Device Master')
@section('breadcrumb', 'HRMS / Biometric Device Master')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addDeviceModal" class="fw-bold text-uppercase">
            Add Biometric Device
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <style>
        @media (min-width: 992px) {
            .nxl-content {
                padding: 0 !important;
            }
            .page-header {
                padding: 24px 24px 16px 24px !important;
                margin-bottom: 0 !important;
                border-bottom: 1px solid #e5e7eb;
                background-color: #fff;
            }
            .main-content {
                padding: 0 !important;
            }
            .settings-container {
                display: flex;
                min-height: calc(100vh - 120px);
                background-color: #f8fafc;
            }
            .settings-content-col {
                flex-grow: 1;
                padding: 24px 30px;
                background-color: #f8fafc;
                min-width: 0;
            }
        }

        @media (max-width: 991.98px) {
            .settings-content-col {
                width: 100%;
                padding: 0 15px;
            }
        }

        .nav-tabs .nav-link {
            border: none !important;
            border-bottom: 2px solid transparent !important;
            background: transparent !important;
            color: #64748b !important;
            padding: 12px 16px !important;
        }

        .nav-tabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
            font-weight: 600 !important;
        }
    </style>
@endpush

@section('content')
    <!-- Alert Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert" style="background-color: #e8f5e9; color: #2e7d32;">
            <div class="d-flex align-items-center">
                <i class="feather-check-circle me-2 fs-18"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="settings-container">
        <div class="settings-content-col erp-single-panel bg-white flex-grow-1 p-4 shadow-sm rounded border-0 text-dark biometric-pane-wrapper">
            
            <!-- Main Tabs Header and Search / Filters -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom gap-3">
                <div>
                    <ul class="nav nav-tabs border-0" id="biometricTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-semibold border-0 {{ request('tab', 'devices') === 'devices' ? 'active' : '' }}" id="devices-tab" data-bs-toggle="tab" data-bs-target="#devices-pane" type="button" role="tab" aria-controls="devices-pane" aria-selected="true">
                                <i class="feather-cpu me-2"></i>Devices List
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold border-0 {{ request('tab') === 'simulator' ? 'active' : '' }}" id="simulator-tab" data-bs-toggle="tab" data-bs-target="#simulator-pane" type="button" role="tab" aria-controls="simulator-pane" aria-selected="false">
                                <i class="feather-play-circle me-2"></i>Biometric Simulator
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <form method="GET" action="{{ route('hrms.biometric-devices.index') }}" class="d-flex align-items-center gap-2 m-0" id="biometricFilterForm">
                        <input type="hidden" name="tab" value="devices">
                        <input type="hidden" name="sort" id="biometric_sort" value="{{ $sort ?? 'name_asc' }}">

                        <!-- Search -->
                        <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                            <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                            <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search devices..." value="{{ $search ?? '' }}" style="box-shadow: none; height: 32px;">
                        </div>

                        <div class="d-flex gap-2">
                            <!-- Sort Dropdown Component -->
                            <x-ui.sort-dropdown label="Sort">
                                <a class="dropdown-item py-2 {{ ($sort ?? 'name_asc') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('biometric', 'name_asc', this); event.preventDefault();">Name (A-Z)</a>
                                <a class="dropdown-item py-2 {{ ($sort ?? '') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('biometric', 'name_desc', this); event.preventDefault();">Name (Z-A)</a>
                                <a class="dropdown-item py-2 {{ ($sort ?? '') == 'serial_asc' ? 'active' : '' }}" href="#" onclick="changeSort('biometric', 'serial_asc', this); event.preventDefault();">Serial (Ascending)</a>
                                <a class="dropdown-item py-2 {{ ($sort ?? '') == 'serial_desc' ? 'active' : '' }}" href="#" onclick="changeSort('biometric', 'serial_desc', this); event.preventDefault();">Serial (Descending)</a>
                            </x-ui.sort-dropdown>

                            <!-- Filter Dropdown Component -->
                            <x-ui.filter label="Filter" offset="0, 5" :reset-url="route('hrms.biometric-devices.index', ['tab' => 'devices'])">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                                
                                <div class="mb-3" style="min-width: 260px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Company</label>
                                    <x-ui.odoo-form-ui type="select" name="company_id">
                                        <option value="">All Companies</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}" @selected((string)$selectedCompanyId === (string)$company->id)>{{ $company->company_name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Business Unit</label>
                                    <x-ui.odoo-form-ui type="select" name="business_unit_id">
                                        <option value="">All Business Units</option>
                                        @foreach($businessUnits as $bu)
                                            <option value="{{ $bu->id }}" @selected((string)$selectedBusinessUnitId === (string)$bu->id)>{{ $bu->name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Branch</label>
                                    <x-ui.odoo-form-ui type="select" name="branch_id">
                                        <option value="">All Branches</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" @selected((string)$selectedBranchId === (string)$branch->id)>{{ $branch->name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                            </x-ui.filter>
                        </div>
                    </form>
                </div>
            </div>

            <div class="tab-content" id="biometricTabsContent">
                <!-- Tab 1: Devices List -->
                <div class="tab-pane fade show active {{ request('tab', 'devices') === 'devices' ? 'show active' : '' }}" id="devices-pane" role="tabpanel" aria-labelledby="devices-tab">

                    <!-- Device Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-light text-uppercase fs-11 text-muted" style="letter-spacing: .5px;">
                                <tr>
                                    <th class="ps-4 py-3" style="width: 25%;">Device Info</th>
                                    <th style="width: 15%;">Serial Number</th>
                                    <th style="width: 25%;">Org Level Scoping</th>
                                    <th style="width: 15%;">Network Info</th>
                                    <th style="width: 10%;">Status</th>
                                    <th class="pe-4 text-end" style="width: 10%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="biometricTableBody">
                                @forelse($devices as $dev)
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="avatar-text avatar-md bg-soft-primary text-primary fw-bold rounded-circle flex-shrink-0" style="width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center;">
                                                    <i class="feather-cpu fs-16"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark">{{ $dev->name }}</div>
                                                    <div class="text-muted fs-11">Registered: {{ $dev->created_at ? $dev->created_at->format('d M Y') : '—' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <code class="px-2 py-1 bg-light rounded text-secondary fw-semibold">{{ $dev->device_serial }}</code>
                                        </td>
                                        <td>
                                            <div class="fs-12">
                                                <div class="fw-semibold text-dark">{{ $dev->company->company_name ?? '—' }}</div>
                                                <div class="text-muted fs-11">
                                                    BU: {{ $dev->businessUnit->name ?? 'Global' }} | 
                                                    Branch: {{ $dev->branch->name ?? 'Global' }}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fs-12">
                                                <div><i class="feather-wifi me-1 text-muted"></i>{{ $dev->ip_address ?? 'Cloud ADMS' }}</div>
                                                <div class="text-muted fs-11">Port: {{ $dev->port ?? '4370' }}</div>
                                            </div>
                                        </td>
                                        <td>
                                            <x-ui.badge :variant="$dev->status ? 'success' : 'danger'" soft>
                                                {{ $dev->status ? 'Active' : 'Inactive' }}
                                            </x-ui.badge>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <x-ui.action-dropdown>
                                                <li>
                                                    <a class="dropdown-item py-2" href="#" 
                                                        data-bs-toggle="modal" data-bs-target="#editDeviceModal" 
                                                        data-id="{{ $dev->id }}"
                                                        data-name="{{ $dev->name }}"
                                                        data-serial="{{ $dev->device_serial }}"
                                                        data-company="{{ $dev->company_id }}"
                                                        data-bu="{{ $dev->business_unit_id }}"
                                                        data-branch="{{ $dev->branch_id }}"
                                                        data-ip="{{ $dev->ip_address }}"
                                                        data-port="{{ $dev->port }}"
                                                        data-status="{{ $dev->status ? '1' : '0' }}"
                                                        onclick="populateEditModal(this); event.preventDefault();">
                                                        <i class="feather-edit me-2 text-muted"></i>Edit Device
                                                    </a>
                                                </li>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li>
                                                    <form action="{{ route('hrms.biometric-devices.destroy', $dev->id) }}" method="POST" class="d-inline delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <a class="dropdown-item py-2 text-danger fw-semibold" href="#" onclick="confirmDelete(this); event.preventDefault();">
                                                            <i class="feather-trash-2 me-2"></i>Delete Device
                                                        </a>
                                                    </form>
                                                </li>
                                            </x-ui.action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="feather-alert-circle fs-30 mb-2 d-block text-secondary"></i>
                                            <div>No biometric devices registered. Register a virtual device to start testing!</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div id="biometricPaginationWrapper">
                        @if($devices->hasPages())
                            <div class="card-footer border-top bg-white p-3">
                                {{ $devices->links() }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Tab 2: Biometric Simulator Testing Panel -->
                <div class="tab-pane fade show {{ request('tab') === 'simulator' ? 'show active' : '' }}" id="simulator-pane" role="tabpanel" aria-labelledby="simulator-tab">
                    <div class="row m-0 border-top">
                        <!-- Instructions Side Panel -->
                        <div class="col-lg-4 p-4 bg-light border-end">
                            <h5 class="fw-bold text-dark fs-14 mb-3"><i class="feather-info me-2 text-primary"></i>Biometric Punch Simulator</h5>
                            <p class="text-muted fs-12 leading-relaxed">
                                This panel emulates a physical biometric device pushing logs to the cloud application. It allows you to test:
                            </p>
                            <ul class="ps-3 fs-12 text-muted leading-relaxed mb-4">
                                <li class="mb-2"><strong>Check-In Logs</strong> (Creates attendance entry)</li>
                                <li class="mb-2"><strong>Check-Out Logs</strong> (Closes attendance, calculates hours)</li>
                                <li class="mb-2"><strong>Roster Off-Days</strong> (Sunday defaults to rest days)</li>
                                <li class="mb-2"><strong>Shift Penalties</strong> (Checks grace periods)</li>
                            </ul>
                            
                            <div class="alert bg-soft-primary text-primary border-0 fs-12 mb-0" style="padding: 12px;">
                                <i class="feather-alert-triangle me-1"></i>
                                <strong>System Note:</strong> Triggering a mock punch dispatches a background job that processes the database asynchronously.
                            </div>
                        </div>

                        <!-- Simulator Form -->
                        <div class="col-lg-8 p-4">
                            <form action="{{ route('hrms.biometric-devices.simulate-punch') }}" method="POST" class="needs-validation" novalidate>
                                @csrf
                                <div class="row g-3">
                                    <!-- Device Select -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-muted fs-11 text-uppercase">Target Virtual Device</label>
                                        <select name="biometric_device_id" class="form-select border shadow-sm">
                                            <option value="">Virtual Simulator Port</option>
                                            @foreach($devices as $dev)
                                                <option value="{{ $dev->id }}">{{ $dev->name }} ({{ $dev->device_serial }})</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Employee Select -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-muted fs-11 text-uppercase">Select Employee <span class="text-danger">*</span></label>
                                        <select name="employee_id" class="form-select border shadow-sm" required>
                                            <option value="">-- Choose Employee --</option>
                                            @foreach($allEmployeesForSim as $emp)
                                                <option value="{{ $emp->id }}">
                                                    {{ $emp->full_name }} ({{ $emp->employee_id ?? 'No ID' }}) 
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback">Employee is required.</div>
                                    </div>

                                    <!-- Punch Type -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-muted fs-11 text-uppercase">Punch Event Type <span class="text-danger">*</span></label>
                                        <select name="punch_type" class="form-select border shadow-sm" required>
                                            <option value="auto">Auto-Resolve (Sequence Mode)</option>
                                            <option value="in">Check-In</option>
                                            <option value="out">Check-Out</option>
                                        </select>
                                    </div>

                                    <!-- Timestamp -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-muted fs-11 text-uppercase">Punch Time & Date <span class="text-danger">*</span></label>
                                        <input type="datetime-local" name="punch_time" class="form-control border shadow-sm" value="{{ now()->format('Y-m-d\TH:i') }}" required>
                                        <div class="invalid-feedback">Punch time is required.</div>
                                    </div>

                                    <div class="col-12 mt-4 text-end">
                                        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2 ms-auto shadow-sm">
                                            <i class="feather-zap fs-14"></i> Trigger Test Punch
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- ========================================== -->
<!-- MODALS -->
<!-- ========================================== -->

<!-- Add Device Modal -->
<x-ui.modal 
    id="addDeviceModal" 
    title='<i class="feather-cpu me-1 text-primary"></i>Register Biometric Device' 
    submitText="Save Device"
    formAction="{{ route('hrms.biometric-devices.store') }}" 
    formMethod="POST">
    
    <x-ui.odoo-form-ui type="input" label="Device Name" name="name" placeholder="Office Main Gate" required />
    <x-ui.odoo-form-ui type="input" label="Device Serial" name="device_serial" placeholder="ZK9500-1049382" required />
    
    <x-ui.odoo-form-ui type="select" label="Company" name="company_id" required>
        <option value="">-- Select Company --</option>
        @foreach($companies as $company)
            <option value="{{ $company->id }}">{{ $company->company_name }}</option>
        @endforeach
    </x-ui.odoo-form-ui>
    
    <x-ui.odoo-form-ui type="select" label="Business Unit" name="business_unit_id">
        <option value="">Global / All Units</option>
        @foreach($businessUnits as $bu)
            <option value="{{ $bu->id }}">{{ $bu->name }}</option>
        @endforeach
    </x-ui.odoo-form-ui>
    
    <x-ui.odoo-form-ui type="select" label="Branch" name="branch_id">
        <option value="">Global / All Branches</option>
        @foreach($branches as $branch)
            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
        @endforeach
    </x-ui.odoo-form-ui>
    
    <x-ui.odoo-form-ui type="input" label="IP Address" name="ip_address" placeholder="192.168.1.201" />
    <x-ui.odoo-form-ui type="input" label="Port" name="port" type="number" value="4370" required />
    
    <x-ui.odoo-form-ui type="select" label="Status" name="status" id="addDeviceStatus" required>
        <option value="1" selected>Active</option>
        <option value="0">Inactive</option>
    </x-ui.odoo-form-ui>
</x-ui.modal>

<!-- Edit Device Modal -->
<x-ui.modal 
    id="editDeviceModal" 
    title='<i class="feather-cpu me-1 text-primary"></i>Edit Biometric Device' 
    submitText="Save Changes"
    formAction="placeholder" 
    formMethod="PUT">
    
    <x-ui.odoo-form-ui type="input" label="Device Name" name="name" id="edit_name" required />
    <x-ui.odoo-form-ui type="input" label="Device Serial" name="device_serial" id="edit_serial" required />
    
    <x-ui.odoo-form-ui type="select" label="Company" name="company_id" id="edit_company_id" required>
        @foreach($companies as $company)
            <option value="{{ $company->id }}">{{ $company->company_name }}</option>
        @endforeach
    </x-ui.odoo-form-ui>
    
    <x-ui.odoo-form-ui type="select" label="Business Unit" name="business_unit_id" id="edit_business_unit_id">
        <option value="">Global / All Units</option>
        @foreach($businessUnits as $bu)
            <option value="{{ $bu->id }}">{{ $bu->name }}</option>
        @endforeach
    </x-ui.odoo-form-ui>
    
    <x-ui.odoo-form-ui type="select" label="Branch" name="branch_id" id="edit_branch_id">
        <option value="">Global / All Branches</option>
        @foreach($branches as $branch)
            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
        @endforeach
    </x-ui.odoo-form-ui>
    
    <x-ui.odoo-form-ui type="input" label="IP Address" name="ip_address" id="edit_ip_address" />
    <x-ui.odoo-form-ui type="input" label="Port" name="port" id="edit_port" type="number" required />
    
    <x-ui.odoo-form-ui type="select" label="Status" name="status" id="edit_status" required>
        <option value="1">Active</option>
        <option value="0">Inactive</option>
    </x-ui.odoo-form-ui>
</x-ui.modal>

<script>
    // Validation bootstrap
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms)
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
    })()

    // Populate dynamic edit modal values
    function populateEditModal(button) {
        var id = button.getAttribute('data-id');
        var name = button.getAttribute('data-name');
        var serial = button.getAttribute('data-serial');
        var company = button.getAttribute('data-company');
        var bu = button.getAttribute('data-bu') || '';
        var branch = button.getAttribute('data-branch') || '';
        var ip = button.getAttribute('data-ip') || '';
        var port = button.getAttribute('data-port');
        var status = button.getAttribute('data-status');

        document.getElementById('edit_name').value = name;
        document.getElementById('edit_serial').value = serial;
        
        var companySelect = document.getElementById('edit_company_id');
        if (companySelect) {
            companySelect.value = company;
            if (window.jQuery && $(companySelect).data('select2')) {
                $(companySelect).trigger('change');
            }
        }
        
        var buSelect = document.getElementById('edit_business_unit_id');
        if (buSelect) {
            buSelect.value = bu;
            if (window.jQuery && $(buSelect).data('select2')) {
                $(buSelect).trigger('change');
            }
        }
        
        var branchSelect = document.getElementById('edit_branch_id');
        if (branchSelect) {
            branchSelect.value = branch;
            if (window.jQuery && $(branchSelect).data('select2')) {
                $(branchSelect).trigger('change');
            }
        }

        document.getElementById('edit_ip_address').value = ip;
        document.getElementById('edit_port').value = port;
        
        var statusSelect = document.getElementById('edit_status');
        if (statusSelect) {
            statusSelect.value = status;
            if (window.jQuery && $(statusSelect).data('select2')) {
                $(statusSelect).trigger('change');
            }
        }

        var form = document.querySelector('#editDeviceModal form');
        if (form) {
            var baseAction = "{{ route('hrms.biometric-devices.update', ':id') }}";
            form.action = baseAction.replace(':id', id);
        }
    }

    // Swel delete confirmation wrapper
    function confirmDelete(button) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Are you sure?',
                text: "You are about to delete this biometric device registration.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    button.closest('form').submit();
                }
            });
        } else {
            if (confirm("Are you sure you want to delete this biometric device?")) {
                button.closest('form').submit();
            }
        }
    }

    // Handle sort dropdown options
    function changeSort(tab, criteria, element) {
        var input = document.getElementById(tab + '_sort');
        if (input) {
            input.value = criteria;
        }

        if (element) {
            var menu = element.closest('.dropdown-menu');
            if (menu) {
                menu.querySelectorAll('.dropdown-item').forEach(function(el) {
                    el.classList.remove('active');
                });
            }
            element.classList.add('active');
        }

        if (input) {
            const form = input.closest('form');
            if (form) {
                const url = new URL(form.action || window.location.href);
                const formData = new FormData(form);
                for (const [key, val] of formData.entries()) {
                    url.searchParams.set(key, val);
                }
                url.searchParams.delete('page');
                refreshBiometricList(url);
            }
        }
    }

    var activeRequest = null;
    function refreshBiometricList(url) {
        if (activeRequest) {
            activeRequest.abort();
        }

        const controller = new AbortController();
        activeRequest = controller;

        const pane = document.getElementById('devices-pane');
        if (pane) {
            pane.classList.add('is-loading');
        }

        fetch(url.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Unable to refresh list.');
            }
            return response.text();
        })
        .then(function (html) {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            
            const newTbody = doc.getElementById('biometricTableBody');
            const oldTbody = document.getElementById('biometricTableBody');
            const newPagination = doc.getElementById('biometricPaginationWrapper');
            const oldPagination = document.getElementById('biometricPaginationWrapper');

            if (newTbody && oldTbody) {
                oldTbody.innerHTML = newTbody.innerHTML;
            }
            if (newPagination && oldPagination) {
                oldPagination.innerHTML = newPagination.innerHTML;
            }

            // Push state to update browser URL
            history.pushState(null, '', url.toString());
        })
        .catch(function (error) {
            if (error.name !== 'AbortError') {
                window.location.href = url.toString();
            }
        })
        .finally(function () {
            if (activeRequest === controller) {
                if (pane) {
                    pane.classList.remove('is-loading');
                }
                activeRequest = null;
            }
        });
    }

    // Toggle search/filter form visibility based on active tab
    document.addEventListener('DOMContentLoaded', function () {
        var devicesTab = document.getElementById('devices-tab');
        var simulatorTab = document.getElementById('simulator-tab');
        var filterForm = document.getElementById('biometricFilterForm');

        function toggleControls() {
            if (devicesTab && devicesTab.classList.contains('active')) {
                if (filterForm) filterForm.style.setProperty('display', 'flex', 'important');
            } else {
                if (filterForm) filterForm.style.setProperty('display', 'none', 'important');
            }
        }

        if (devicesTab && simulatorTab && filterForm) {
            devicesTab.addEventListener('shown.bs.tab', toggleControls);
            simulatorTab.addEventListener('shown.bs.tab', toggleControls);
            toggleControls();
        }

        // Debounced quick search to avoid needing to press Enter
        var searchTimeout = null;
        $(document).on('input', '#biometricFilterForm input[name="search"]', function () {
            const input = this;
            const form = input.closest('form');
            if (!form) return;
            
            const url = new URL(form.action || window.location.href);
            const formData = new FormData(form);
            for (const [key, val] of formData.entries()) {
                url.searchParams.set(key, val);
            }
            url.searchParams.delete('page');

            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                refreshBiometricList(url);
            }, 250);
        });

        // Intercept GET form submissions (search/filters)
        $(document).on('submit', '#biometricFilterForm', function (event) {
            const form = this;
            event.preventDefault();
            
            const url = new URL(form.action || window.location.href);
            const formData = new FormData(form);
            for (const [key, val] of formData.entries()) {
                url.searchParams.set(key, val);
            }
            url.searchParams.delete('page');

            refreshBiometricList(url);
            
            // Close the filter dropdown menu safely
            $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
            $('.erp-filter-dropdown.show').removeClass('show');
        });

        // Intercept Pagination link clicks
        $(document).on('click', '#biometricPaginationWrapper a[href]', function (event) {
            const href = this.getAttribute('href');
            if (!href || href.startsWith('javascript:') || href === '#') return;

            event.preventDefault();
            const urlObj = new URL(href, window.location.origin);
            refreshBiometricList(urlObj);
        });
    });
</script>
@endsection
