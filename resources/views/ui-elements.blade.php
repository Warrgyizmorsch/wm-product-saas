@extends('layouts.duralux')

@section('title', 'Testing Common UI Components | SaaS ERP')
@section('page-title', 'Common UI Components Sandbox')
@section('breadcrumb', 'Component Sandbox')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <div id="reportrange" class="reportrange-picker d-flex align-items-center">
        <span class="reportrange-picker-field"></span>
    </div>
    <div class="dropdown filter-dropdown">
        <a class="btn btn-md btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
            <i class="feather-filter me-2"></i>
            <span>Filter</span>
        </a>
        <div class="dropdown-menu dropdown-menu-end">
            @foreach (['Company', 'Branch', 'Department', 'Owner', 'Status'] as $filter)
                <div class="dropdown-item">
                    <x-ui.checkbox label="{{ $filter }}" name="filter_{{ strtolower($filter) }}" id="filter{{ $filter }}" checked />
                </div>
            @endforeach
            <div class="dropdown-divider"></div>
            <a href="javascript:void(0);" class="dropdown-item">
                <i class="feather-plus me-3"></i>
                <span>Create New</span>
            </a>
            <a href="javascript:void(0);" class="dropdown-item">
                <i class="feather-filter me-3"></i>
                <span>Manage Filter</span>
            </a>
        </div>
    </div>
    <x-ui.button variant="primary" icon="feather-plus">
        New Workflow
    </x-ui.button>
@endsection

@section('content')
    

    <!-- ERP Domains Grid -->
    <x-ui.card title="Large Scale ERP Domains">
        <div class="row g-3 erp-domain-grid">
            @foreach (['CRM', 'Sales', 'Inventory', 'Purchase', 'Production', 'HRMS', 'Projects', 'Accounting', 'Administration'] as $domain)
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                    <a href="#" class="erp-domain-tile">
                        <span>{{ $domain }}</span>
                        <i class="feather-arrow-up-right"></i>
                    </a>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    <!-- Component Showcase Section -->
    <div class="row g-4 mt-2">
        <!-- Column 1: Buttons, Badges & Alerts -->
        <div class="col-xxl-6">
            <x-ui.card title="UI Buttons, Badges & Alerts Showcase" stretch>
                <h6 class="text-uppercase fs-11 text-muted mb-3">Button Variants & Sizing</h6>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <x-ui.button variant="primary">Primary</x-ui.button>
                    <x-ui.button variant="secondary">Secondary</x-ui.button>
                    <x-ui.button variant="success" icon="feather-check">Success</x-ui.button>
                    <x-ui.button variant="danger" icon="feather-alert-triangle" icon-position="right">Danger</x-ui.button>
                    <x-ui.button variant="warning" size="sm">Warning Sm</x-ui.button>
                    <x-ui.button variant="info" size="lg">Info Lg</x-ui.button>
                    <x-ui.button variant="light-brand">Light Brand</x-ui.button>
                    <x-ui.button variant="primary" disabled>Disabled</x-ui.button>
                    <x-ui.button variant="link" href="https://laravel.com" target="_blank">Link Button</x-ui.button>
                    <x-ui.button variant="primary" href="{{ route('dashboard') }}">Dashboard (via href)</x-ui.button>
                    <x-ui.button variant="primary" onclick="window.location.href='{{ route('dashboard') }}'">Dashboard (via onclick)</x-ui.button>
                </div>

                <h6 class="text-uppercase fs-11 text-muted mb-3">Badge Variants (Solid vs Soft)</h6>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <x-ui.badge variant="primary">Primary</x-ui.badge>
                    <x-ui.badge variant="success">Success</x-ui.badge>
                    <x-ui.badge variant="warning">Warning</x-ui.badge>
                    <x-ui.badge variant="danger">Danger</x-ui.badge>
                    
                    <span class="mx-2 text-muted">|</span>
                    
                    <x-ui.badge variant="primary" soft>Primary Soft</x-ui.badge>
                    <x-ui.badge variant="success" soft>Success Soft</x-ui.badge>
                    <x-ui.badge variant="warning" soft>Warning Soft</x-ui.badge>
                    <x-ui.badge variant="danger" soft>Danger Soft</x-ui.badge>
                    <x-ui.badge variant="info" soft>Info Soft</x-ui.badge>
                </div>

                <h6 class="text-uppercase fs-11 text-muted mb-3">Alert Banners</h6>
                <x-ui.alert variant="success" icon="feather-check-circle" dismissible>
                    <strong>Success!</strong> Your changes have been successfully saved globally.
                </x-ui.alert>
                <x-ui.alert variant="warning" icon="feather-alert-circle" dismissible>
                    <strong>Warning!</strong> Component state might change during test simulations.
                </x-ui.alert>
                <x-ui.alert variant="danger" icon="feather-x-circle">
                    <strong>Critical!</strong> Database synchronization was delayed. (Non-dismissible)
                </x-ui.alert>
            </x-ui.card>
        </div>

        <!-- Column 2: Interactive Modals, Drawers & Toasts Triggers -->
        <div class="col-xxl-6">
            <x-ui.card title="Interactive UI Elements (Modal, Drawer, Toast)" stretch>
                <p class="text-muted fs-13 mb-4">
                    These components utilize Bootstrap 5 dynamics. Use the buttons below to trigger live overlays and notifications.
                </p>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="border border-dashed rounded p-3 text-center">
                            <i class="feather-external-link fs-24 text-primary mb-2 d-block"></i>
                            <x-ui.button variant="primary" size="sm" data-bs-toggle="modal" data-bs-target="#demoModal">
                                Launch Modal
                            </x-ui.button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border border-dashed rounded p-3 text-center">
                            <i class="feather-sidebar fs-24 text-success mb-2 d-block"></i>
                            <x-ui.button variant="success" size="sm" data-bs-toggle="offcanvas" data-bs-target="#demoDrawer">
                                Open Drawer
                            </x-ui.button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border border-dashed rounded p-3 text-center">
                            <i class="feather-bell fs-24 text-warning mb-2 d-block"></i>
                            <x-ui.button variant="warning" size="sm" onclick="window.erpToasts['demoToast'].show()">
                                Trigger Toast
                            </x-ui.button>
                        </div>
                    </div>
                </div>

                <div class="bg-light rounded p-3">
                    <h6 class="fs-12 text-uppercase text-muted mb-2">JavaScript Integration Hook</h6>
                    <code class="d-block fs-11 text-dark">
                        // To trigger the toast programmatically:<br>
                        window.erpToasts['demoToast'].show();
                    </code>
                </div>
            </x-ui.card>
        </div>
    </div>

    <!-- Form Controls Showcase -->
    <div class="row g-4 mt-4">
        <div class="col-12">
            <x-ui.card title="Common Form Inputs Showcase">
                <div class="row g-3">
                    <div class="col-md-3">
                        <x-ui.input label="Sandbox Username" name="username" placeholder="Enter username..." helper-text="Keep username unique inside sandbox settings" required />
                    </div>
                    <div class="col-md-3">
                        <x-ui.select label="Tenant Branch" name="branch" :options="['hq' => 'Headquarters', 'us-east' => 'US East Division', 'eu-west' => 'EU West Office']" selected="hq" data-select2-selector="default" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-dark fs-12 text-uppercase mb-2 d-block">Checkbox Options</label>
                        <div class="d-flex flex-column gap-2 mt-2">
                            <x-ui.checkbox label="Enable debug trace logs" name="debug_trace" checked />
                            <x-ui.checkbox label="Auto-sync DB updates" name="auto_sync" />
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-dark fs-12 text-uppercase mb-2 d-block">Radio Options</label>
                        <div class="d-flex flex-column gap-2 mt-2">
                            <x-ui.radio label="Production Mode" name="env_mode" value="prod" />
                            <x-ui.radio label="Staging Sandbox" name="env_mode" value="stage" checked />
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>
    </div>

    <!-- Table Component Showcase -->
    <div class="row g-4 mt-4">
        <div class="col-12">
            <x-ui.table title="Data Table Component (Array-driven)" search-placeholder="Search modules..." striped hoverable>
                @php
                    $headers = ['Module ID', 'Module Name', 'Status', 'Version', 'Actions'];
                    $rows = [
                        ['M-101', 'Core Inventory', '<span class="badge bg-soft-success text-success">Active</span>', 'v2.4.1', '<button class="btn btn-sm btn-light-brand">Configure</button>'],
                        ['M-102', 'Sales & CRM', '<span class="badge bg-soft-success text-success">Active</span>', 'v3.0.0', '<button class="btn btn-sm btn-light-brand">Configure</button>'],
                        ['M-103', 'HRMS Payroll', '<span class="badge bg-soft-warning text-warning">Pending</span>', 'v1.1.2', '<button class="btn btn-sm btn-light-brand">Configure</button>'],
                        ['M-104', 'Double-Entry Accounting', '<span class="badge bg-soft-danger text-danger">Inactive</span>', 'v0.9.0', '<button class="btn btn-sm btn-light-brand">Configure</button>'],
                    ];
                @endphp
                <thead>
                    <tr>
                        @foreach($headers as $header)
                            <th scope="col">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td>{!! $cell !!}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </div>

        <div class="col-12">
            <x-ui.table title="Leads Listing" search-placeholder="Search leads..." hoverable>
                <thead>
                    <tr>
                        <th>Call Date & Time</th>
                        <th>Company Name</th>
                        <th>Contact Person</th>
                        <th>Contact Phone/Email</th>
                        <th>Expected Amount</th>
                        <th>Sale Date</th>
                        <th>Source</th>
                        <th>Priority</th>
                        <th>Segment</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="table-date-block">
                                <div class="table-date-icon">
                                    <i class="feather-calendar"></i>
                                </div>
                                <div class="table-date-content">
                                    <span class="fw-bold text-dark fs-13">25/06/2026</span>
                                    <small class="text-muted fs-11">11:45 AM</small>
                                </div>
                            </div>
                        </td>
                        <td><span class="fw-bold text-dark">Sagar Corp</span></td>
                        <td>Prakash</td>
                        <td>
                            <div class="d-flex flex-column fs-12">
                                <span><i class="feather-phone me-1 text-muted"></i> 9988776655</span>
                                <small class="text-muted"><i class="feather-mail me-1"></i> prakash@sagar.com</small>
                            </div>
                        </td>
                        <td><span class="fw-bold text-dark">₹50,000.00</span></td>
                        <td>—</td>
                        <td><span class="text-muted fs-12">Select an Option</span></td>
                        <td>
                            <x-ui.badge variant="success" soft>
                                <i class="feather-arrow-down me-1"></i> Select an Option
                            </x-ui.badge>
                        </td>
                        <td>
                            <x-ui.badge variant="info" soft>
                                Select an Option
                            </x-ui.badge>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="table-date-block">
                                <div class="table-date-icon">
                                    <i class="feather-calendar"></i>
                                </div>
                                <div class="table-date-content">
                                    <span class="fw-bold text-dark fs-13">25/06/2026</span>
                                    <small class="text-muted fs-11">11:30 AM</small>
                                </div>
                            </div>
                        </td>
                        <td><span class="fw-bold text-dark">SK Contractors</span></td>
                        <td>Sagar Kumar</td>
                        <td>
                            <div class="d-flex flex-column fs-12">
                                <span><i class="feather-phone me-1 text-muted"></i> 9876543210</span>
                                <small class="text-muted"><i class="feather-mail me-1"></i> sagar@skcontractors.in</small>
                            </div>
                        </td>
                        <td><span class="fw-bold text-dark">₹150,000.00</span></td>
                        <td>15/07/2026</td>
                        <td>Employee Referral</td>
                        <td>
                            <x-ui.badge variant="danger" soft>
                                <i class="feather-arrow-up me-1"></i> High
                            </x-ui.badge>
                        </td>
                        <td>
                            <x-ui.badge variant="info" soft>
                                Enterprise
                            </x-ui.badge>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="table-date-block">
                                <div class="table-date-icon">
                                    <i class="feather-calendar"></i>
                                </div>
                                <div class="table-date-content">
                                    <span class="fw-bold text-dark fs-13">25/06/2026</span>
                                    <small class="text-muted fs-11">10:15 AM</small>
                                </div>
                            </div>
                        </td>
                        <td><span class="fw-bold text-dark">Raju Enterprises</span></td>
                        <td>Raju Prasad</td>
                        <td>
                            <div class="d-flex flex-column fs-12">
                                <span><i class="feather-phone me-1 text-muted"></i> 08754677797</span>
                                <small class="text-muted"><i class="feather-mail me-1"></i> raju@rajuent.com</small>
                            </div>
                        </td>
                        <td><span class="fw-bold text-dark">₹75,000.00</span></td>
                        <td>01/08/2026</td>
                        <td>Cold Call</td>
                        <td>
                            <x-ui.badge variant="warning" soft>
                                <span class="me-1">—</span> Medium
                            </x-ui.badge>
                        </td>
                        <td>
                            <x-ui.badge variant="info" soft>
                                SMB
                            </x-ui.badge>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="table-date-block">
                                <div class="table-date-icon">
                                    <i class="feather-calendar"></i>
                                </div>
                                <div class="table-date-content">
                                    <span class="fw-bold text-dark fs-13">24/06/2026</span>
                                    <small class="text-muted fs-11">04:45 PM</small>
                                </div>
                            </div>
                        </td>
                        <td><span class="fw-bold text-dark">Zenith Tech</span></td>
                        <td>Manish Sharma</td>
                        <td>
                            <div class="d-flex flex-column fs-12">
                                <span><i class="feather-phone me-1 text-muted"></i> 9911223344</span>
                                <small class="text-muted"><i class="feather-mail me-1"></i> manish@zenith.io</small>
                            </div>
                        </td>
                        <td><span class="fw-bold text-dark">₹220,000.00</span></td>
                        <td>10/07/2026</td>
                        <td>Partner</td>
                        <td>
                            <x-ui.badge variant="danger" soft>
                                <i class="feather-arrow-up me-1"></i> High
                            </x-ui.badge>
                        </td>
                        <td>
                            <x-ui.badge variant="info" soft>
                                Enterprise
                            </x-ui.badge>
                        </td>
                    </tr>
                </tbody>
            </x-ui.table>
        </div>
    </div>

    <!-- Overlay Component Instances (Modal, Drawer, Toast) -->
    <!-- Modal Instance -->
    <x-ui.modal id="demoModal" title="Simulated ERP Settings" submit-text="Apply Settings" centered>
        <p class="fs-13 text-muted">Configure your testing suite parameters here. Changes will apply immediately to this browser session.</p>
        <x-ui.input label="Tenant Sandbox Name" name="tenant_name" id="demoTenantName" value="sandbox-erp-domain" />
        <x-ui.select label="Interface Mode" name="theme_mode" id="demoThemeMode" :options="['light' => 'Light Mode', 'dark' => 'Dark Mode', 'system' => 'Follow System']" selected="dark" data-select2-selector="default" />
    </x-ui.modal>

    <!-- Drawer Instance -->
    <x-ui.drawer id="demoDrawer" title="Quick Task Panel" position="end">
        <p class="fs-13 text-muted">Use this panel for contextual tasks without moving away from your active dashboard workspace.</p>
        
        <div class="list-group list-group-flush mt-4">
            <div class="list-group-item px-0 py-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-semibold text-dark fs-13">Audit logs generation</span>
                    <x-ui.badge variant="warning" soft>Pending</x-ui.badge>
                </div>
                <div class="progress ht-5">
                    <div class="progress-bar bg-warning" style="width: 45%"></div>
                </div>
            </div>
            <div class="list-group-item px-0 py-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-semibold text-dark fs-13">Database indexes validation</span>
                    <x-ui.badge variant="success" soft>Completed</x-ui.badge>
                </div>
                <div class="progress ht-5">
                    <div class="progress-bar bg-success" style="width: 100%"></div>
                </div>
            </div>
        </div>

        <x-slot name="footer">
            <x-ui.button variant="light-brand" data-bs-dismiss="offcanvas">Close Panel</x-ui.button>
            <x-ui.button variant="primary">Save Progress</x-ui.button>
        </x-slot>
    </x-ui.drawer>

    <!-- Toast Container & Toast Instance -->
    <div class="erp-toast-container">
        <x-ui.toast id="demoToast" title="Real-time ERP Audit" subtitle="just now" type="success" delay="4000">
            A background task successfully scanned all 4 integration hooks without errors.
        </x-ui.toast>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var start = moment().subtract(29, 'days');
            var end = moment();

            function setRangeLabel(startDate, endDate) {
                $('.reportrange-picker-field').html(startDate.format('MMM D, YYYY') + ' - ' + endDate.format('MMM D, YYYY'));
            }

            $('#reportrange').daterangepicker({
                startDate: start,
                endDate: end,
                ranges: {
                    Today: [moment(), moment()],
                    Yesterday: [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            }, setRangeLabel);

            setRangeLabel(start, end);
        });
    </script>
@endpush
