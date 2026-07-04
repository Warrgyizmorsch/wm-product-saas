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
    <x-ui.filter label="Global Filter" offset="0, 10">
        <h6 class="fs-11 text-uppercase text-muted mb-2">Toggle Visible Modules</h6>
        <div class="d-flex flex-column gap-2">
            <x-ui.checkbox label="Inventory Module" name="show_inv" checked />
            <x-ui.checkbox label="Production Module" name="show_prod" checked />
            <x-ui.checkbox label="Accounting Module" name="show_acc" />
        </div>
        <div class="dropdown-divider my-2"></div>
        <button class="btn btn-xs btn-primary w-100">Apply Filter</button>
    </x-ui.filter>

    <x-ui.button variant="primary" icon="feather-plus">
        Create Entry
    </x-ui.button>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4">
        <!-- ERP Domains Grid -->
    <x-ui.card title="ERP Domains Dashboard Overview" class="mb-4">
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

    <!-- Main Navigation Tabs -->
    @php
        $mainTabs = [
            ['id' => 'tab-odoo-forms', 'label' => 'Odoo Form UI & Inputs', 'active' => true, 'icon' => 'feather-layout'],
            ['id' => 'tab-buttons-badges', 'label' => 'Buttons & Badges', 'active' => false, 'icon' => 'feather-box'],
            ['id' => 'tab-overlays', 'label' => 'Overlays & Toasts', 'active' => false, 'icon' => 'feather-layers'],
            ['id' => 'tab-tables', 'label' => 'Tables & Filters', 'active' => false, 'icon' => 'feather-database'],
            ['id' => 'tab-vertical-tabs', 'label' => 'Sidebar & Vertical Tabs', 'active' => false, 'icon' => 'feather-menu'],
        ];
    @endphp

    <x-ui.horizontal-tabs id="sandboxMainTabs" :tabs="$mainTabs" />

    <div class="tab-content mt-3" id="sandboxMainTabsContent">
        
        <!-- Tab 1: Odoo Form UI & Inputs -->
        <div class="tab-pane fade show active" id="tab-odoo-forms" role="tabpanel" aria-labelledby="tab-odoo-forms-tab">
            <div class="row">
                <div class="">
                    <!-- Odoo Sheet Container -->
                    <x-ui.odoo-form-ui type="sheet">
                        <div class="pb-3 mb-4 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold text-dark mb-0">SaaS Document Sheet: Odoo Form UI Template</h5>
                            <span class="badge bg-soft-primary text-primary">Draft</span>
                        </div>

                        <div class="row g-4">
                            <!-- Left Column -->
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="Required Code" name="document_code" value="SO-2026-0091" placeholder="e.g. SO-2026-0001" :required="true" />
                                
                                <x-ui.odoo-form-ui type="input" label="Standard Text Field" name="document_title" placeholder="Enter document title..." />
                                
                                <x-ui.odoo-form-ui type="input" label="Execution Date" name="doc_date" inputType="date" :value="date('Y-m-d')" :required="true" />
                                
                                <x-ui.odoo-form-ui type="select" label="Select Item (Master)" name="sandbox_prod_select" data-master="product" :required="true">
                                    <option value="">Choose item...</option>
                                    <option value="__ADD_NEW__" class="fw-bold text-primary">+ Add New Product</option>
                                    <option value="1">Screws (Standard Box)</option>
                                    <option value="2">Steel Plate (10mm)</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <!-- Right Column -->
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="file" label="Upload Blueprint" name="blueprint_file" :required="true" />
                                
                                <x-ui.odoo-form-ui type="checkbox" label="Notification Status" name="notify_client" :required="false">
                                    Send status updates automatically to partner
                                </x-ui.odoo-form-ui>

                                <x-ui.odoo-form-ui type="radio" label="Severity Level" name="sev_level" :required="true">
                                    <div class="form-check">
                                        <input type="radio" id="sev_low" name="sev_level" value="low" class="form-check-input" checked>
                                        <label class="form-check-label fs-13" for="sev_low">Low</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" id="sev_high" name="sev_level" value="high" class="form-check-input">
                                        <label class="form-check-label fs-13" for="sev_high">High</label>
                                    </div>
                                </x-ui.odoo-form-ui>

                                <x-ui.odoo-form-ui type="textarea" label="Internal Notes" name="internal_notes" placeholder="Write additional logs/notes..." rows="2" />
                            </div>
                        </div>

                        <!-- Advanced Form Fields Demonstration -->
                        <div class="mt-4 pt-4 border-top">
                            <h6 class="fw-bold text-dark mb-3">Advanced Odoo Form Elements & Validation</h6>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="Tags Select" name="tags[]" :multiple="true" select2Selector="tag" :required="true" helperText="Select one or multiple tags to categorize this item.">
                                        <option value="success" data-bg="bg-success">VIP</option>
                                        <option value="info" data-bg="bg-info">Bugs</option>
                                        <option value="primary" data-bg="bg-primary">Team</option>
                                        <option value="teal" data-bg="bg-teal">Primary</option>
                                        <option value="success" data-bg="bg-success">Updates</option>
                                        <option value="warning" data-bg="bg-warning">Personal</option>
                                        <option value="danger" data-bg="bg-danger" selected>Promotions</option>
                                        <option value="indigo" data-bg="bg-indigo">Customs</option>
                                        <option value="primary" data-bg="bg-primary">Wholesale</option>
                                        <option value="danger" data-bg="bg-danger">Low Budget</option>
                                        <option value="teal" data-bg="bg-teal" selected>High Budget</option>
                                    </x-ui.odoo-form-ui>

                                    <x-ui.odoo-form-ui type="input" label="Username Input" name="username_demo" placeholder="Enter username..." helperText="Username should be unique and must contain at least 6 characters." />

                                    <x-ui.odoo-form-ui type="select" label="Priority Level" name="priority_demo" :required="true" errorText="Please select a priority level.">
                                        <option value="">Choose priority...</option>
                                        <option value="low">Low Priority</option>
                                        <option value="medium">Medium Priority</option>
                                        <option value="high">High Priority</option>
                                    </x-ui.odoo-form-ui>

                                    <x-ui.odoo-form-ui type="input" label="Email Address" name="email_demo" value="invalid-email-format" placeholder="Enter email..." errorText="Please specify a valid email address (e.g. user@domain.com)." />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="editor" label="Project Description" name="project_description" editorHeight="ht-200" :required="true" helperText="A rich text editor field for project descriptions.">
                                        <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Asperiores beatae inventore reiciendis ipsum natus, porro recusandae sunt accusantium reprehenderit aliquid commodi est veniam sit molestiae, nesciunt cupiditate. Laborum, culpa maxime.</p>
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>
                        </div>

                        <!-- Nested Odoo Table Component -->
                        <div class="mt-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2">Line Items Grid</h6>
                            <x-ui.odoo-form-ui type="table">
                                <thead>
                                    <tr>
                                        <th style="width: 40%">Material / Component</th>
                                        <th style="width: 25%">Unit of Measure</th>
                                        <th style="width: 20%" class="text-end">Required Qty</th>
                                        <th style="width: 15%" class="text-center">Active</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <x-ui.odoo-form-ui type="select" data-master="product" name="grid_product[]">
                                                <option value="">Choose item...</option>
                                                <option value="__ADD_NEW__">+ Add New Product</option>
                                                <option value="1" selected>Aluminium Alloy T6</option>
                                                <option value="2">Titanium Bar</option>
                                            </x-ui.odoo-form-ui>
                                        </td>
                                        <td>
                                            <x-ui.odoo-form-ui type="select" data-master="uom" name="grid_uom[]">
                                                <option value="">Choose UOM...</option>
                                                <option value="__ADD_NEW__">+ Add New UOM</option>
                                                <option value="1" selected>Kilograms (kg)</option>
                                                <option value="2">Meters (m)</option>
                                            </x-ui.odoo-form-ui>
                                        </td>
                                        <td>
                                            <x-ui.odoo-form-ui type="input" inputType="number" step="any" class="text-end" value="15.50" name="grid_qty[]" />
                                        </td>
                                        <td class="text-center">
                                            <x-ui.odoo-form-ui type="checkbox" name="grid_active[]" checked />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <x-ui.odoo-form-ui type="select" data-master="product" name="grid_product[]">
                                                <option value="">Choose item...</option>
                                                <option value="__ADD_NEW__">+ Add New Product</option>
                                                <option value="1">Aluminium Alloy T6</option>
                                                <option value="2" selected>Titanium Bar</option>
                                            </x-ui.odoo-form-ui>
                                        </td>
                                        <td>
                                            <x-ui.odoo-form-ui type="select" data-master="uom" name="grid_uom[]">
                                                <option value="">Choose UOM...</option>
                                                <option value="__ADD_NEW__">+ Add New UOM</option>
                                                <option value="1">Kilograms (kg)</option>
                                                <option value="2" selected>Meters (m)</option>
                                            </x-ui.odoo-form-ui>
                                        </td>
                                        <td>
                                            <x-ui.odoo-form-ui type="input" inputType="number" step="any" class="text-end" value="5.00" name="grid_qty[]" />
                                        </td>
                                        <td>
                                            <x-ui.odoo-form-ui type="checkbox" name="grid_active[]" />
                                        </td>
                                    </tr>
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex gap-2">
                            <button type="button" class="btn btn-primary px-4">Submit Sheet</button>
                            <button type="button" class="btn btn-light border px-4">Discard</button>
                        </div>
                    </x-ui.odoo-form-ui>
                </div>
            </div>
        </div>

        <!-- Tab 2: Buttons & Badges -->
        <div class="tab-pane fade" id="tab-buttons-badges" role="tabpanel" aria-labelledby="tab-buttons-badges-tab">
            <div class="row g-4">
                <div class="col-md-6">
                    <x-ui.card title="Standard Action Buttons" stretch>
                        <div class="d-flex flex-wrap gap-2">
                            <x-ui.button variant="primary">Primary Button</x-ui.button>
                            <x-ui.button variant="secondary">Secondary Button</x-ui.button>
                            <x-ui.button variant="success" icon="feather-check">Success</x-ui.button>
                            <x-ui.button variant="danger" icon="feather-alert-triangle" icon-position="right">Danger</x-ui.button>
                            <x-ui.button variant="warning" size="sm">Warning Sm</x-ui.button>
                            <x-ui.button variant="info" size="lg">Info Lg</x-ui.button>
                            <x-ui.button variant="light-brand">Light Brand</x-ui.button>
                        </div>
                    </x-ui.card>
                </div>

                <div class="col-md-6">
                    <x-ui.card title="Icon Buttons (icon-btn)" stretch>
                        <p class="text-muted fs-12 mb-3">
                            Showcasing <code>x-ui.icon-btn</code> in various variants and sizes (built for dense interfaces and tabular layouts).
                        </p>
                        <h6 class="fs-11 text-uppercase text-muted mb-2">Sizes (Small, Medium, Large)</h6>
                        <div class="d-flex align-items-center gap-2 mb-4">
                            <x-ui.icon-btn variant="primary" size="sm" icon="feather-edit" title="Small Edit" />
                            <x-ui.icon-btn variant="primary" size="md" icon="feather-edit" title="Medium Edit" />
                            <x-ui.icon-btn variant="primary" size="lg" icon="feather-edit" title="Large Edit" />
                        </div>

                        <h6 class="fs-11 text-uppercase text-muted mb-2">Color Variants</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <x-ui.icon-btn variant="primary" icon="feather-plus" title="Primary Plus" />
                            <x-ui.icon-btn variant="success" icon="feather-check" title="Success Check" />
                            <x-ui.icon-btn variant="danger" icon="feather-trash-2" title="Danger Trash" />
                            <x-ui.icon-btn variant="warning" icon="feather-alert-circle" title="Warning Info" />
                            <x-ui.icon-btn variant="info" icon="feather-info" title="Info Details" />
                            <x-ui.icon-btn variant="soft-primary" icon="feather-eye" title="Soft Primary View" />
                            <x-ui.icon-btn variant="soft-success" icon="feather-refresh-cw" title="Soft Success Refresh" />
                            <x-ui.icon-btn variant="soft-danger" icon="feather-slash" title="Soft Danger Block" />
                            <x-ui.icon-btn variant="light-brand" icon="feather-settings" title="Light Brand Settings" />
                        </div>
                    </x-ui.card>
                </div>

                <div class="col-12">
                    <x-ui.card title="Status Badges & Banner Alerts">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6 class="fs-11 text-uppercase text-muted mb-2">Badges</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <x-ui.badge variant="primary">Primary</x-ui.badge>
                                    <x-ui.badge variant="success">Success</x-ui.badge>
                                    <x-ui.badge variant="warning">Warning</x-ui.badge>
                                    <x-ui.badge variant="danger">Danger</x-ui.badge>
                                    <x-ui.badge variant="primary" soft>Primary Soft</x-ui.badge>
                                    <x-ui.badge variant="success" soft>Success Soft</x-ui.badge>
                                    <x-ui.badge variant="warning" soft>Warning Soft</x-ui.badge>
                                    <x-ui.badge variant="danger" soft>Danger Soft</x-ui.badge>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fs-11 text-uppercase text-muted mb-2">Alerts</h6>
                                <x-ui.alert variant="success" icon="feather-check-circle" dismissible>
                                    <strong>Success!</strong> All parameters passed verification tests.
                                </x-ui.alert>
                                <x-ui.alert variant="danger" icon="feather-x-circle">
                                    <strong>Error!</strong> Failsafe protocol has been engaged.
                                </x-ui.alert>
                            </div>
                        </div>
                    </x-ui.card>
                </div>

                <div class="col-12 mt-4">
                    <x-ui.card title="Action Dropdowns & Options">
                        <p class="text-muted fs-13 mb-3">
                            Showcasing the <code>x-ui.action-dropdown</code> containing list item actions.
                        </p>
                        <div class="d-flex justify-content-start">
                            <x-ui.action-dropdown viewUrl="javascript:void(0);">
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0)">
                                        <i class="feather feather-edit-3 me-3"></i>
                                        <span>Edit</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item printBTN" href="javascript:void(0)">
                                        <i class="feather feather-printer me-3"></i>
                                        <span>Print</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0)">
                                        <i class="feather feather-clock me-3"></i>
                                        <span>Remind</span>
                                    </a>
                                </li>
                                <li class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0)">
                                        <i class="feather feather-archive me-3"></i>
                                        <span>Archive</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0)">
                                        <i class="feather feather-alert-octagon me-3"></i>
                                        <span>Report Spam</span>
                                    </a>
                                </li>
                                <li class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0)">
                                        <i class="feather feather-trash-2 me-3"></i>
                                        <span>Delete</span>
                                    </a>
                                </li>
                            </x-ui.action-dropdown>
                        </div>
                    </x-ui.card>
                </div>
            </div>
        </div>

        <!-- Tab 3: Overlays & Toasts -->
        <div class="tab-pane fade" id="tab-overlays" role="tabpanel" aria-labelledby="tab-overlays-tab">
            <div class="row g-4">
                <div class="col-md-6">
                    <x-ui.card title="Interactive Overlays (Modals & Drawers)" stretch>
                        <p class="text-muted fs-13 mb-4">
                            These elements launch off-canvas widgets and centered overlay dialogs styled with the dynamic primary color.
                        </p>
                        
                        <div class="d-flex gap-3">
                            <x-ui.button variant="primary" data-bs-toggle="modal" data-bs-target="#demoModal">
                                <i class="feather-external-link me-2"></i>Launch Centered Modal
                            </x-ui.button>
                            <x-ui.button variant="success" data-bs-toggle="offcanvas" data-bs-target="#demoDrawer">
                                <i class="feather-sidebar me-2"></i>Open Right Drawer
                            </x-ui.button>
                        </div>
                    </x-ui.card>
                </div>

                <div class="col-md-6">
                    <x-ui.card title="Toasts & Notifications Trigger" stretch>
                        <p class="text-muted fs-13 mb-4">
                            Click below to fire the updated Theme Toast component or the native SweetAlert2 animated alerts.
                        </p>
                        
                        <div class="d-flex flex-wrap gap-2">
                            <x-ui.toast
                                id="demoToast"
                                class="btn btn-warning"
                                title="Your invoice has been saved successfully."
                                type="success"
                            >
                                <i class="feather-bell me-2"></i>Trigger Live Toast
                            </x-ui.toast>
                        </div>
                    </x-ui.card>
                </div>
            </div>
        </div>

        <!-- Tab 4: Tables & Filters -->
        <div class="tab-pane fade" id="tab-tables" role="tabpanel" aria-labelledby="tab-tables-tab">
            <x-ui.card title="Advanced Filters & Data Tables">
                <div class="d-flex align-items-center mb-3">
                    <div class="d-flex gap-2 ms-auto">
                        <!-- Custom Sort Component -->
                        <x-ui.sort-dropdown label="Sort">
                            <a href="javascript:void(0);" class="dropdown-item active">
                                <span>Material Code (Asc)</span>
                                <i class="feather-chevron-up text-muted fs-12"></i>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <span>Material Code (Desc)</span>
                                <i class="feather-chevron-down text-muted fs-12"></i>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <span>Material Name (A-Z)</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <span>Material Name (Z-A)</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <span>In Stock (Low to High)</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <span>In Stock (High to Low)</span>
                            </a>
                        </x-ui.sort-dropdown>

                        <!-- Custom Filter Component -->
                        <x-ui.filter label="Filter" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                            
                            <!-- Search Bar (Name, Number, Code) -->
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                                <x-ui.odoo-form-ui type="input" name="filter_search" placeholder="Search by name, code..." />
                            </div>

                            <!-- Stock Status Dropdown -->
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Stock Status</label>
                                <x-ui.odoo-form-ui type="select" name="filter_status">
                                    <option value="">All Statuses</option>
                                    <option value="in_stock">In Stock</option>
                                    <option value="low_stock">Low Stock</option>
                                    <option value="out_of_stock">Out of Stock</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <!-- Start Date -->
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Start Date</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="filter_start_date" />
                            </div>

                            <!-- End Date -->
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">End Date</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="filter_end_date" />
                            </div>

                            <!-- Warehouse / Location -->
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Warehouse Location</label>
                                <x-ui.odoo-form-ui type="select" name="filter_warehouse">
                                    <option value="">All Warehouses</option>
                                    <option value="main">Main Warehouse</option>
                                    <option value="secondary">Secondary Storage</option>
                                    <option value="qa">QA/Inspection Lab</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <!-- Category Checkboxes -->
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Category</label>
                                <div class="d-flex flex-column gap-2 mt-1">
                                    <x-ui.checkbox label="Raw Materials" name="raw_mat" checked />
                                    <x-ui.checkbox label="Sub-Assemblies" name="sub_ass" />
                                    <x-ui.checkbox label="Packaging" name="pack" />
                                </div>
                            </div>

                            <div class="dropdown-divider my-3"></div>

                            <div class="d-flex gap-2">
                                <x-ui.button type="button" variant="primary" size="sm" class="flex-grow-1">Apply Filters</x-ui.button>
                                <x-ui.button type="button" variant="light" size="sm" class="border flex-grow-1" onclick="document.querySelectorAll('.erp-filter-dropdown input, .erp-filter-dropdown select').forEach(el => el.value='')">Reset</x-ui.button>
                            </div>
                        </x-ui.filter>
                    </div>
                </div>

                <x-ui.table title="Material Master Database" search-placeholder="Search materials..." hoverable>
                    <thead>
                        <tr>
                            <th>Image / Code</th>
                            <th>Material Name</th>
                            <th>Type</th>
                            <th>In Stock</th>
                            <th>Min Level</th>
                            <th>Cost</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="fw-bold text-dark">MAT-AL-001</span></td>
                            <td>Aluminium Sheet 2mm</td>
                            <td>Raw Material</td>
                            <td>450.00 kg</td>
                            <td>100.00 kg</td>
                            <td>₹250.00</td>
                            <td><x-ui.badge variant="success" soft>In Stock</x-ui.badge></td>
                        </tr>
                        <tr>
                            <td><span class="fw-bold text-dark">MAT-SC-892</span></td>
                            <td>Steel Bolt M6</td>
                            <td>Component</td>
                            <td>1,200.00 PCS</td>
                            <td>500.00 PCS</td>
                            <td>₹4.50</td>
                            <td><x-ui.badge variant="success" soft>In Stock</x-ui.badge></td>
                        </tr>
                        <tr>
                            <td><span class="fw-bold text-dark">MAT-LD-122</span></td>
                            <td>Lithium Cell 3.7V</td>
                            <td>Raw Material</td>
                            <td>12.00 PCS</td>
                            <td>50.00 PCS</td>
                            <td>₹85.00</td>
                            <td><x-ui.badge variant="danger" soft>Low Stock</x-ui.badge></td>
                        </tr>
                    </tbody>
                </x-ui.table>

                <!-- Custom Pagination Component -->
                <x-ui.pagination :currentPage="1" :totalPages="3" :totalResults="28" :perPage="10" />
            </x-ui.card>
        </div>

        <!-- Tab 5: Sidebar & Vertical Tabs -->
        <div class="tab-pane fade" id="tab-vertical-tabs" role="tabpanel" aria-labelledby="tab-vertical-tabs-tab">
            <x-ui.card title="Vertical Sidebar Tabs Panel Demo">
                <div class="row">
                    <div class="col-md-3 border-end">
                        <!-- Custom Vertical Tabs Component -->
                        @php
                            $vertTabs = [
                                ['id' => 'vtab-general', 'label' => 'General Settings', 'active' => true, 'icon' => 'feather-settings'],
                                ['id' => 'vtab-security', 'label' => 'Security & Keys', 'active' => false, 'icon' => 'feather-lock'],
                                ['id' => 'vtab-network', 'label' => 'API Endpoints', 'active' => false, 'icon' => 'feather-globe'],
                            ];
                        @endphp
                        <x-ui.vertical-tabs id="sandboxVerticalTabs" :tabs="$vertTabs" />
                    </div>
                    <div class="col-md-9 ps-md-4">
                        <div class="tab-content" id="sandboxVerticalTabsContent">
                            <div class="tab-pane fade show active" id="vtab-general" role="tabpanel" aria-labelledby="vtab-general-tab">
                                <h6 class="fw-bold text-dark mb-2">General Settings</h6>
                                <p class="text-muted fs-13">Configure the core parameters for the SaaS Sandbox instance. These values dictate validation limits and debug mode settings.</p>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <x-ui.input label="Instance Name" name="inst_name" value="SaaS-Demo-Production" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.input label="Max Operations Limit" name="max_ops" type="number" value="100" />
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="vtab-security" role="tabpanel" aria-labelledby="vtab-security-tab">
                                <h6 class="fw-bold text-dark mb-2">Security & Keys</h6>
                                <p class="text-muted fs-13">Update your access keys and security tokens below. Never commit these values directly to repository configuration files.</p>
                                <x-ui.input label="API Security Token" name="api_token" type="password" value="secret-token-key-123" />
                            </div>
                            <div class="tab-pane fade" id="vtab-network" role="tabpanel" aria-labelledby="vtab-network-tab">
                                <h6 class="fw-bold text-dark mb-2">API Endpoints</h6>
                                <p class="text-muted fs-13">Define the external Webhook and web service endpoints for sync operations.</p>
                                <x-ui.input label="Webhook Endpoint URL" name="webhook_url" value="https://api.saas-erp.com/webhook" placeholder="https://example.com/callback" />
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>

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


    {{-- Global master quick-create modals --}}
    <x-ui.master-modals :masters="['product', 'uom']" />
@endsection

@push('scripts')
    <script>
        $(function () {
            // Initialize Date Range Picker for Filter materials
            $('#filter_date_range').daterangepicker({
                autoUpdateInput: false,
                locale: {
                    cancelLabel: 'Clear',
                    format: 'YYYY-MM-DD'
                }
            });

            $('#filter_date_range').on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD') + ' to ' + picker.endDate.format('YYYY-MM-DD'));
            });

            $('#filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
            });

            // Manually initialize dropdowns in case theme JS conflicts with Bootstrap automatic init
            if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
                var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
                dropdownElementList.forEach(function (dropdownToggleEl) {
                    bootstrap.Dropdown.getOrCreateInstance(dropdownToggleEl);
                });
            }
        });
    </script>
@endpush
