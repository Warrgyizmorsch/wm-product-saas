@extends('layouts.duralux')

@section('title', __('hrms.salary.title') . ' | SaaS ERP')
@section('page-title', __('hrms.salary.title'))
@section('breadcrumb', 'HRMS / ' . __('hrms.salary.title'))

@section('page-actions')
    <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addPayGroupModal">
        Add Pay Group
    </x-ui.button>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <style>
        /* Modern layouts for connected settings sidebar */
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
            .settings-sidebar-col {
                width: 280px;
                min-width: 280px;
                background-color: #fff;
                border-right: 1px solid #e5e7eb;
                display: flex;
                flex-direction: column;
            }
            .settings-content-col {
                flex-grow: 1;
                padding: 24px 24px 24px 16px;
                background-color: #f8fafc;
                min-width: 0;
            }
        }

        @media (max-width: 991.98px) {
            .settings-sidebar-col {
                width: 100%;
                background-color: #fff;
                border-bottom: 1px solid #e5e7eb;
                margin-bottom: 20px;
                padding: 10px;
            }
            .settings-content-col {
                width: 100%;
                padding: 0 15px;
            }
        }

        /* Theme styles for Sort/Filter buttons and Search Input */
        .col-md-4.border-end .erp-filter-dropdown,
        .col-md-4.border-end .erp-sort-dropdown {
            flex: 1 1 auto;
        }
        .col-md-4.border-end .erp-filter-dropdown .btn,
        .col-md-4.border-end .erp-sort-dropdown .btn {
            width: 100% !important;
            justify-content: center !important;
        }
        .theme-search-container {
            position: relative !important;
            width: 100% !important;
        }
        .theme-search-container i {
            position: absolute !important;
            left: 16px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            color: #64748b !important;
            font-size: 14px !important;
        }
        .theme-search-input {
            background-color: #f1f5f9 !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            padding: 8px 16px 8px 40px !important;
            font-size: 13px !important;
            height: 40px !important;
            width: 100% !important;
            outline: none !important;
            transition: all 0.2s ease-in-out !important;
        }
        .theme-search-input:focus {
            background-color: #fff !important;
            border-color: var(--bs-primary) !important;
            box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb), 0.1) !important;
        }

        /* Settings Subsidebar Items */
        #settingsSubSidebar .nav-link {
            background-color: transparent;
            transition: all 0.2s ease-in-out;
            border-radius: 6px !important;
            font-size: 14px;
            font-weight: 500;
            color: #475569 !important;
            padding: 12px 16px !important;
            border: 0 !important;
        }
        #settingsSubSidebar .nav-link:hover {
            background-color: #f1f5f9;
            color: var(--bs-primary) !important;
        }
        #settingsSubSidebar .nav-link.active {
            background-color: var(--bs-primary) !important;
            color: #fff !important;
            font-weight: 600;
        }

        /* Underlined Horizontal Tabs to match Org Structure style */
        #salaryStructureTabs .nav-link {
            border: none !important;
            background-color: transparent !important;
            color: #64748b;
            font-weight: 500;
            padding: 12px 20px;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s ease-in-out;
        }
        #salaryStructureTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #salaryStructureTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
        }

        /* Plans List items styling */
        .plan-item {
            border-left: 4px solid transparent !important;
            transition: all 0.15s ease-in-out;
            border-bottom: 1px solid #f1f5f9 !important;
            color: #475569 !important;
        }
        .plan-item.active {
            background-color: #f1f5f9 !important;
            border-color: #f1f5f9 !important;
            border-left-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
        }
        .plan-item:hover:not(.active) {
            background-color: #f8fafc !important;
        }
    </style>

    <div class="settings-container">
        <!-- Left Subsidebar Column -->
        <div class="settings-sidebar-col">
            @include('modules.hrms.partials.settings-sidebar')
        </div>

        <!-- Right Content Column -->
        <div class="settings-content-col flex-grow-1">
            @if(session('success'))
                <x-ui.alert variant="success" icon="feather-check-circle" dismissible>
                    {{ session('success') }}
                </x-ui.alert>
            @endif
            @if(session('error'))
                <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                    {{ session('error') }}
                </x-ui.alert>
            @endif

            <div class="col-12">
                <x-ui.card title="Pay Groups" bodyClass="p-0" stretch>
                    <x-slot name="headerAction">
                        <div class="d-flex align-items-center gap-2">
                            <!-- Search Input -->
                            <div class="theme-search-container" style="width: 240px !important; position: relative;">
                                <i class="feather-search"></i>
                                <input type="text" id="payGroupSearch" class="theme-search-input" placeholder="Search pay groups...">
                            </div>

                            <!-- Sort Dropdown -->
                            <x-ui.sort-dropdown label="SORT">
                                <a class="dropdown-item py-2 active" href="#" data-sort="name_asc" onclick="sortPayGroups('name_asc', this); event.preventDefault();">Name (A-Z)</a>
                                <a class="dropdown-item py-2" href="#" data-sort="name_desc" onclick="sortPayGroups('name_desc', this); event.preventDefault();">Name (Z-A)</a>
                                <a class="dropdown-item py-2" href="#" data-sort="newest" onclick="sortPayGroups('newest', this); event.preventDefault();">Newest First</a>
                            </x-ui.sort-dropdown>

                            <!-- Filter Dropdown -->
                            <x-ui.filter label="FILTER">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                    <x-ui.odoo-form-ui type="select" name="pg_status" id="pg_filter_status">
                                        <option value="">All Statuses</option>
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Company</label>
                                    <x-ui.odoo-form-ui type="select" name="pg_company" id="pg_filter_company">
                                        <option value="">All Companies</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="dropdown-divider my-3"></div>

                                <div class="d-flex gap-2">
                                    <x-ui.button type="button" variant="primary" size="sm" class="flex-grow-1" onclick="filterPayGroups()">Apply Filters</x-ui.button>
                                    <x-ui.button type="button" variant="light" size="sm" class="border flex-grow-1" onclick="resetPayGroupFilters()">Reset</x-ui.button>
                                </div>
                            </x-ui.filter>
                        </div>
                    </x-slot>
                    <div class="row g-0">
                        <!-- LEFT COLUMN: ALL PAY GROUPS LIST -->
                        <div class="col-md-4 col-12 border-end">

                            <div class="list-group list-group-flush rounded-0" style="min-height: 400px; max-height: 600px; overflow-y: auto;">
                                @forelse($payGroups as $pg)
                                    @php
                                        $isActive = $selectedPayGroup && $selectedPayGroup->id === $pg->id;
                                    @endphp
                                    <a href="{{ route('hrms.salary-structure.index', ['pay_group_id' => $pg->id, 'tab' => request()->get('tab', 'structures')]) }}" 
                                       class="list-group-item list-group-item-action py-3 px-4 plan-item {{ $isActive ? 'active' : '' }}"
                                       data-name="{{ strtolower($pg->name) }}"
                                       data-status="{{ $pg->status ? 'active' : 'inactive' }}"
                                       data-company-id="{{ $pg->company_id }}"
                                       data-created-at="{{ $pg->created_at ? $pg->created_at->timestamp : 0 }}">
                                        <span class="fw-bold {{ $isActive ? 'text-primary' : 'text-dark' }}" style="font-size: 14px;">
                                            {{ $pg->name }}
                                        </span>
                                        <div class="fs-11 text-muted text-capitalize mt-1">
                                            {{ $pg->status ? 'Active' : 'Inactive' }} &bull; {{ $pg->company ? $pg->company->company_name : 'No Company' }}
                                        </div>
                                    </a>
                                @empty
                                    <div class="text-center py-5 text-muted px-3">
                                        <i class="feather-grid fs-24 mb-2 d-block text-secondary"></i>
                                        <span>No Pay Groups configured yet.</span>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: SELECTED PAY GROUP DETAILS & CONFIGURATION TABS -->
                        <div class="col-md-8 col-12">
                            @if($selectedPayGroup)
                                <div class="p-4">
                                    <!-- Selected Pay Group Details -->
                                    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                                        <div>
                                            <h5 class="fw-bold text-dark mb-1" style="font-size: 16px;">{{ $selectedPayGroup->name }}</h5>
                                            <div class="text-muted d-flex align-items-center gap-2" style="font-size: 12px;">
                                                <span><i class="feather-briefcase me-1"></i>{{ $selectedPayGroup->company ? $selectedPayGroup->company->company_name : 'All Companies' }}</span>
                                                <span>&bull;</span>
                                                <span>
                                                    @if($selectedPayGroup->status)
                                                        <span class="text-success"><i class="feather-check-circle me-1"></i>Active</span>
                                                    @else
                                                        <span class="text-danger"><i class="feather-slash me-1"></i>Inactive</span>
                                                    @endif
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Actions Dropdown for Pay Group -->
                                        <form action="{{ route('hrms.salary-structure.pay-group.destroy', $selectedPayGroup->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Pay Group? Linked components and structures will be unassigned.');">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.action-dropdown>
                                                <li>
                                                    <a class="dropdown-item edit-pay-group-btn" href="javascript:void(0)" data-pay-group="{{ base64_encode($selectedPayGroup->toJson()) }}">
                                                        <i class="feather feather-edit-3 me-3"></i>
                                                        <span>Edit Pay Group</span>
                                                    </a>
                                                </li>
                                                <li class="dropdown-divider"></li>
                                                <li>
                                                    <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                        <i class="feather feather-trash-2 me-3"></i>
                                                        <span>Delete Pay Group</span>
                                                    </button>
                                                </li>
                                            </x-ui.action-dropdown>
                                        </form>
                                    </div>

                                    @if($selectedPayGroup->description)
                                        <div class="p-3 bg-light rounded mb-4" style="font-size: 13px; color: #475569;">
                                            <i class="feather-info me-2 text-primary"></i>{{ $selectedPayGroup->description }}
                                        </div>
                                    @endif

                                    <!-- Nav tabs inside the selected Pay Group details -->
                                    <ul class="nav gap-2 border-bottom mb-4" id="salaryStructureTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link {{ request()->get('tab', 'structures') === 'structures' ? 'active' : '' }} px-4 py-3" id="structures-tab" data-bs-toggle="tab" data-bs-target="#structures-pane" type="button" role="tab" aria-controls="structures-pane" aria-selected="true">
                                                <i class="feather-grid me-2"></i>Salary Structures (Slabs)
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link {{ request()->get('tab') === 'components' ? 'active' : '' }} px-4 py-3" id="components-tab" data-bs-toggle="tab" data-bs-target="#components-pane" type="button" role="tab" aria-controls="components-pane" aria-selected="false">
                                                <i class="feather-list me-2"></i>Salary Components
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content" id="settingsSubSidebarContent">
                                        <div class="tab-pane fade {{ request()->get('tab', 'structures') === 'structures' ? 'show active' : '' }}" id="structures-pane" role="tabpanel" aria-labelledby="structures-tab">
                                            @include('modules.hrms.salary-structure.tabs.salary-structures')
                                        </div>
                                        <div class="tab-pane fade {{ request()->get('tab') === 'components' ? 'show active' : '' }}" id="components-pane" role="tabpanel" aria-labelledby="components-tab">
                                            @include('modules.hrms.salary-structure.tabs.salary-components')
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-5 text-muted">
                                    <i class="feather-info fs-32 mb-2 d-block text-secondary"></i>
                                    <span>Please select or add a Pay Group to configure its settings.</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>

    <!-- Include all Unified Modals at body level to prevent parent wrapper blur/backdrop overlay bugs -->
    @include('modules.hrms.partials.modals')

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Edit Pay Group Trigger
            $(document).on('click', '.edit-pay-group-btn', function() {
                let dataStr = $(this).attr('data-pay-group');
                if (!dataStr) return;

                let pg = JSON.parse(atob(dataStr));
                
                $('#editPayGroupForm').attr('action', `/hrms/salary-structure/pay-group/update/${pg.id}`);
                $('#edit_pg_name').val(pg.name);
                $('#edit_pg_company_id').val(pg.company_id || '');
                $('#edit_pg_description').val(pg.description || '');
                
                let statusVal = (pg.status === true || pg.status === 1 || pg.status === '1') ? '1' : '0';
                $('#edit_pg_status').val(statusVal);

                $('#editPayGroupModal').modal('show');
            });

            // Move all modal divs to the body root level to prevent stacking context/blur backdrop issues!
            document.querySelectorAll('.modal').forEach(function(modal) {
                document.body.appendChild(modal);
            });

            // Client-side instant filter for left sidebar Pay Groups
            const payGroupSearchInput = document.getElementById('payGroupSearch');
            if (payGroupSearchInput) {
                payGroupSearchInput.addEventListener('input', filterPayGroups);
            }

            // Prevent structures search form submit
            $(document).on('submit', 'form:has(input[name="struct_search"])', function(e) {
                e.preventDefault();
            });

            // Auto submit theme filter form on radio/select changes
            $(document).on('change', '.theme-filter-form input[type="radio"], .theme-filter-form select', function() {
                $(this).closest('form').submit();
            });

            // Track tab changes in URL params
            $('#salaryStructureTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                const tabId = e.target.id.replace('-tab', '');
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('tab', tabId);
                const newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?' + urlParams.toString();
                window.history.pushState({path:newurl}, '', newurl);
            });

            // Automatically activate the correct tab if passed in query string (handles redirects after submit)
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const tabButton = document.getElementById(tabParam + '-tab');
                if (tabButton) {
                    tabButton.click();
                }
            }

            const subtabParam = urlParams.get('subtab');
            if (subtabParam) {
                const subtabButton = document.getElementById(subtabParam + '-subtab');
                if (subtabButton) {
                    // Slight delay to ensure the parent tab pane is fully shown first
                    setTimeout(() => {
                        subtabButton.click();
                    }, 50);
                }
            }

            // Generic modal Select2 initializer inside HRMS boundaries
            $(document).on('shown.bs.modal', '.modal', function () {
                var modal = $(this);
                modal.find('select').each(function() {
                    var $select = $(this);
                    if ($select.hasClass("select2-hidden-accessible")) {
                        $select.select2('destroy');
                    }
                    
                    var selectorType = $select.attr('data-select2-selector') || 'default';
                    var options = {
                        theme: 'bootstrap-5',
                        dropdownParent: modal.find('.modal-content'),
                        width: '100%'
                    };
                    
                    if (selectorType === 'status' && typeof bgformat === 'function') {
                        options.templateResult = bgformat;
                        options.templateSelection = bgformat;
                        options.minimumResultsForSearch = Infinity;
                    } else if (selectorType === 'currency' && typeof currencyformat === 'function') {
                        options.templateResult = currencyformat;
                        options.templateSelection = currencyformat;
                    } else if (selectorType === 'country' && typeof countryformat === 'function') {
                        options.templateResult = countryformat;
                        options.templateSelection = countryformat;
                    } else if (selectorType === 'tzone' && typeof tzoneformat === 'function') {
                        options.templateResult = tzoneformat;
                        options.templateSelection = tzoneformat;
                    }
                    
                    $select.select2(options);
                });
            });

            // Close dropdowns when clicking outside (excluding Select2 containers)
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.dropdown, .erp-sort-dropdown, .erp-filter-dropdown, .select2-container, .select2-dropdown').length) {
                    $('.dropdown-menu').removeClass('show');
                    $('.dropdown, .erp-sort-dropdown, .erp-filter-dropdown').removeClass('show');
                }
            });
        });

        function filterPayGroups() {
            const search = document.getElementById('payGroupSearch').value.toLowerCase().trim();
            
            const statusSelect = document.getElementById('pg_filter_status');
            const statusVal = statusSelect ? statusSelect.value : '';
            const status = statusVal === '1' ? 'active' : (statusVal === '0' ? 'inactive' : 'all');
            
            const companySelect = document.getElementById('pg_filter_company');
            const companyId = companySelect ? companySelect.value : '';
            
            document.querySelectorAll('.plan-item').forEach(item => {
                const name = item.getAttribute('data-name') || '';
                const itemStatus = item.getAttribute('data-status') || '';
                const itemCompanyId = item.getAttribute('data-company-id') || '';
                
                const matchesSearch = name.includes(search);
                const matchesStatus = (status === 'all') || (itemStatus === status);
                const matchesCompany = (companyId === '') || (itemCompanyId === companyId);
                
                if (matchesSearch && matchesStatus && matchesCompany) {
                    item.style.setProperty('display', '', 'important');
                } else {
                    item.style.setProperty('display', 'none', 'important');
                }
            });

            // Auto close dropdowns
            $('.dropdown-menu').removeClass('show');
            $('.dropdown, .erp-sort-dropdown, .erp-filter-dropdown').removeClass('show');
        }

        function resetPayGroupFilters() {
            $('#pg_filter_status').val('').trigger('change');
            $('#pg_filter_company').val('').trigger('change');
            document.getElementById('payGroupSearch').value = '';
            filterPayGroups();
        }
        
        function sortPayGroups(criteria, element) {
            // Toggle active class on sort options
            element.closest('.dropdown-menu').querySelectorAll('.dropdown-item').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            
            const list = document.querySelector('.list-group-flush');
            if (!list) return;
            const items = Array.from(list.querySelectorAll('.plan-item'));
            
            items.sort((a, b) => {
                if (criteria === 'name_asc') {
                    return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
                } else if (criteria === 'name_desc') {
                    return b.getAttribute('data-name').localeCompare(a.getAttribute('data-name'));
                } else if (criteria === 'newest') {
                    return parseInt(b.getAttribute('data-created-at') || 0) - parseInt(a.getAttribute('data-created-at') || 0);
                }
                return 0;
            });
            
            // Re-append in sorted order
            items.forEach(item => list.appendChild(item));

            // Auto close dropdowns
            $('.dropdown-menu').removeClass('show');
            $('.dropdown, .erp-sort-dropdown, .erp-filter-dropdown').removeClass('show');
        }

        window.filterPayGroups = filterPayGroups;
        window.resetPayGroupFilters = resetPayGroupFilters;
        window.sortPayGroups = sortPayGroups;
    </script>
@endsection


