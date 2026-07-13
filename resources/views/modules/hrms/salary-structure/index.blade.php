@extends('layouts.duralux')

@section('title', 'SALARY STRUCTURE | SaaS ERP')
@section('page-title', 'Salary Structure')
@section('breadcrumb', 'HRMS / Salary Structure')

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
        .theme-btn-style {
            background-color: #fff !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
            border-radius: 8px !important;
            padding: 8px 16px !important;
            font-weight: 500 !important;
            font-size: 13px !important;
            height: 36px !important;
            transition: all 0.2s ease-in-out !important;
        }
        .theme-btn-style:hover,
        .theme-btn-style:focus,
        .theme-btn-style:active {
            background-color: #f1f5f9 !important;
            border-color: #94a3b8 !important;
            color: #0f172a !important;
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

        /* Theme Filter Dropdown Styles to match Odoo/Saas screenshot */
        .theme-filter-dropdown-menu {
            min-width: 320px !important;
            padding: 24px !important;
            border-radius: 12px !important;
            border: 1px solid #cbd5e1 !important;
            background-color: #ffffff !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05) !important;
            z-index: 1050 !important;
        }
        .theme-filter-header {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            font-size: 14px !important;
            font-weight: 700 !important;
            color: #1e293b !important;
            margin-bottom: 20px !important;
            text-transform: capitalize !important;
        }
        .theme-filter-header i {
            font-size: 16px !important;
        }
        .theme-filter-group {
            margin-bottom: 20px !important;
        }
        .theme-filter-label {
            font-size: 11px !important;
            font-weight: 700 !important;
            color: #475569 !important;
            letter-spacing: 0.5px !important;
            margin-bottom: 6px !important;
            text-transform: uppercase !important;
            display: block !important;
        }
        .theme-filter-field {
            border: none !important;
            border-bottom: 1px solid #ced4da !important;
            border-radius: 0 !important;
            padding: 8px 0 !important;
            font-size: 13px !important;
            background-color: transparent !important;
            color: #1e293b !important;
            width: 100% !important;
            box-shadow: none !important;
            outline: none !important;
            transition: all 0.2s ease-in-out !important;
            cursor: pointer !important;
        }
        .theme-filter-field:focus {
            border-bottom-color: #2563eb !important;
        }
        .theme-filter-footer {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            margin-top: 24px !important;
        }
        .theme-filter-apply-btn {
            background-color: #1e293b !important;
            color: #ffffff !important;
            border: none !important;
            border-radius: 6px !important;
            padding: 10px 16px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            letter-spacing: 0.5px !important;
            text-transform: uppercase !important;
            flex: 1 !important;
            transition: all 0.2s ease-in-out !important;
            cursor: pointer !important;
            text-align: center !important;
        }
        .theme-filter-apply-btn:hover {
            background-color: #0f172a !important;
        }
        .theme-filter-reset-btn {
            background-color: #f1f5f9 !important;
            color: #334155 !important;
            border: none !important;
            border-radius: 6px !important;
            padding: 10px 16px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            letter-spacing: 0.5px !important;
            text-transform: uppercase !important;
            flex: 1 !important;
            transition: all 0.2s ease-in-out !important;
            cursor: pointer !important;
            text-align: center !important;
            text-decoration: none !important;
        }
        .theme-filter-reset-btn:hover {
            background-color: #e2e8f0 !important;
            color: #0f172a !important;
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
                        <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addPayGroupModal">
                            Add Pay Group
                        </x-ui.button>
                    </x-slot>
                    <div class="row g-0">
                        <!-- LEFT COLUMN: ALL PAY GROUPS LIST -->
                        <div class="col-md-4 col-12 border-end">
                            <!-- Search, Sort & Filter Panel -->
                            <div class="p-3 border-bottom bg-light-soft">
                                <!-- Sort & Filter Buttons (Side-by-Side at the top) -->
                                <div class="d-flex gap-2 mb-3">
                                    <!-- Sort Dropdown -->
                                    <div class="dropdown flex-fill">
                                        <button class="btn theme-btn-style w-100 d-flex align-items-center justify-content-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="feather-bar-chart"></i>
                                            <span>SORT</span>
                                        </button>
                                        <ul class="dropdown-menu shadow-sm w-100" style="font-size: 12px; min-width: 160px;">
                                            <li><a class="dropdown-item py-2 active" href="#" data-sort="name_asc" onclick="sortPayGroups('name_asc', this); event.preventDefault();">Name (A-Z)</a></li>
                                            <li><a class="dropdown-item py-2" href="#" data-sort="name_desc" onclick="sortPayGroups('name_desc', this); event.preventDefault();">Name (Z-A)</a></li>
                                            <li><a class="dropdown-item py-2" href="#" data-sort="newest" onclick="sortPayGroups('newest', this); event.preventDefault();">Newest First</a></li>
                                        </ul>
                                    </div>

                                    <!-- Filter Dropdown -->
                                    <div class="dropdown flex-fill">
                                        <button class="btn theme-btn-style w-100 d-flex align-items-center justify-content-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="feather-filter"></i>
                                            <span>FILTER</span>
                                        </button>
                                        <div class="dropdown-menu theme-filter-dropdown-menu shadow-sm">
                                            <div class="theme-filter-header">
                                                <i class="feather-sliders text-primary"></i>
                                                <span>Filter Options</span>
                                            </div>
                                            <form method="GET" action="{{ route('hrms.salary-structure.index') }}">
                                                @if(request()->filled('pay_group_id'))
                                                    <input type="hidden" name="pay_group_id" value="{{ request('pay_group_id') }}">
                                                @endif
                                                @if(request()->filled('tab'))
                                                    <input type="hidden" name="tab" value="{{ request('tab') }}">
                                                @endif
                                                
                                                <div class="theme-filter-group">
                                                    <label class="theme-filter-label">Status</label>
                                                    <select name="pg_status" id="pg_filter_status" class="theme-filter-field">
                                                        <option value="">All Statuses</option>
                                                        <option value="1" @selected(request('pg_status') === '1')>Active</option>
                                                        <option value="0" @selected(request('pg_status') === '0')>Inactive</option>
                                                    </select>
                                                </div>

                                                <div class="theme-filter-group">
                                                    <label class="theme-filter-label">Company</label>
                                                    <select name="pg_company" id="pg_filter_company" class="theme-filter-field">
                                                        <option value="">All Companies</option>
                                                        @foreach($companies as $company)
                                                            <option value="{{ $company->id }}" @selected(request('pg_company') == $company->id)>{{ $company->company_name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="theme-filter-footer">
                                                    <button type="submit" class="theme-filter-apply-btn">Apply Filters</button>
                                                    <a href="{{ route('hrms.salary-structure.index', ['pay_group_id' => request('pay_group_id'), 'tab' => request('tab')]) }}" class="theme-filter-reset-btn">Reset</a>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Search Input (Below the buttons) -->
                                <div class="theme-search-container">
                                    <i class="feather-search"></i>
                                    <input type="text" id="payGroupSearch" class="theme-search-input" placeholder="Search pay groups...">
                                </div>
                            </div>

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

            // Global bulletproof dropdown toggle handler for standard & custom dropdown buttons
            $(document).on('click', '[data-bs-toggle="dropdown"], .sort-toggle-custom, .filter-toggle-custom', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var button = $(this);
                var parent = button.closest('.dropdown, .erp-sort-dropdown, .erp-filter-dropdown');
                var menu = parent.find('.dropdown-menu');
                
                if (menu.hasClass('show')) {
                    menu.attr('style', 'display: none !important;');
                    parent.removeClass('show');
                    menu.removeClass('show');
                } else {
                    // Close all other dropdown menus
                    $('.dropdown-menu').attr('style', 'display: none !important;').removeClass('show');
                    $('.dropdown, .erp-sort-dropdown, .erp-filter-dropdown').removeClass('show');
                    
                    // Show this one
                    parent.addClass('show');
                    menu.addClass('show');
                    
                    var minWidth = menu.css('min-width') || '200px';
                    var padding = menu.css('padding') || '8px';
                    menu.attr('style', 'display: block !important; position: absolute !important; right: 0 !important; left: auto !important; min-width: ' + minWidth + ' !important; padding: ' + padding + ' !important; background-color: #fff !important; border: 1px solid #cbd5e1 !important; border-radius: 8px !important; z-index: 1050 !important; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;');
                }
            });
            
            // Close dropdowns when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.dropdown, .erp-sort-dropdown, .erp-filter-dropdown').length) {
                    $('.dropdown-menu').attr('style', 'display: none !important;').removeClass('show');
                    $('.dropdown, .erp-sort-dropdown, .erp-filter-dropdown').removeClass('show');
                }
            });
        });

        function filterPayGroups() {
            const search = document.getElementById('payGroupSearch').value.toLowerCase().trim();
            const statusRadio = document.querySelector('input[name="pg_filter_status"]:checked');
            const status = statusRadio ? statusRadio.value : 'all';
            const companySelect = document.getElementById('pg_filter_company');
            const companyId = companySelect ? companySelect.value : 'all';
            
            document.querySelectorAll('.plan-item').forEach(item => {
                const name = item.getAttribute('data-name') || '';
                const itemStatus = item.getAttribute('data-status') || '';
                const itemCompanyId = item.getAttribute('data-company-id') || '';
                
                const matchesSearch = name.includes(search);
                const matchesStatus = (status === 'all') || (itemStatus === status);
                const matchesCompany = (companyId === 'all') || (itemCompanyId === companyId);
                
                if (matchesSearch && matchesStatus && matchesCompany) {
                    item.style.setProperty('display', '', 'important');
                } else {
                    item.style.setProperty('display', 'none', 'important');
                }
            });
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
        }
    </script>
@endsection


