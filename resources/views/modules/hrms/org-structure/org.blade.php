@extends('layouts.duralux')

@section('title', __('hrms.org.title') . ' | SaaS ERP')
@section('page-title', __('hrms.org.title'))
@section('breadcrumb', 'HRMS / ' . __('hrms.org.title'))

@section('page-actions')
    <div id="add-btn-legal-entities" class="org-add-btn-wrapper">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
            {{ __('hrms.org.add_legal_entity') }}
        </x-ui.button>
    </div>
    <div id="add-btn-business-units" class="org-add-btn-wrapper d-none">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addBuModal">
            {{ __('hrms.org.add_business_unit') }}
        </x-ui.button>
    </div>
    <div id="add-btn-branches" class="org-add-btn-wrapper d-none">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addBranchModal">
            {{ __('hrms.org.add_branch') }}
        </x-ui.button>
    </div>
    <div id="add-btn-departments" class="org-add-btn-wrapper d-none">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addDeptModal">
            {{ __('hrms.org.add_department') }}
        </x-ui.button>
    </div>
    <div id="add-btn-designations" class="org-add-btn-wrapper d-none">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addDesigModal">
            {{ __('hrms.org.add_designation') }}
        </x-ui.button>
    </div>
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
            /* Override container paddings to allow flush layout next to main sidebar */
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
                padding: 24px 30px;
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

        /* Underlined Horizontal Tabs */
        #orgTabs .nav-link {
            border: none !important;
            background-color: transparent !important;
            color: #64748b;
            font-weight: 500;
            padding: 12px 20px;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s ease-in-out;
        }
        #orgTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #orgTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
        }

        /* Colors and theme attributes */
        .bg-soft-purple { background-color: rgba(139, 92, 246, 0.08) !important; }
        .text-purple { color: #8b5cf6 !important; }
        .bg-soft-teal { background-color: rgba(20, 184, 166, 0.08) !important; }
        .text-teal { color: #14b8a6 !important; }
        .bg-soft-indigo { background-color: rgba(99, 102, 241, 0.08) !important; }
        .text-indigo { color: #6366f1 !important; }
        .bg-soft-muted { background-color: rgba(100, 116, 139, 0.08) !important; }
        
        .transition-all {
            transition: all 0.2s ease-in-out !important;
        }
        .bg-soft-light-hover:hover {
            background-color: rgba(13, 110, 253, 0.02) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03) !important;
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
            <div class="tab-content" id="settingsSubSidebarContent">
                <!-- Org Structure Pane -->
                <div class="tab-pane fade show active" id="org-structure-pane" role="tabpanel" aria-labelledby="org-structure-menu">
                    <div class="row">
                        <!-- Horizontal Navigation directly above table content -->
                        <div class="col-12 mb-3">
                            <ul class="nav gap-2 border-bottom pb-2" id="orgTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="legal-entities-tab" data-bs-toggle="tab" data-bs-target="#legal-entities" type="button" role="tab" aria-controls="legal-entities" aria-selected="true">
                                        <i class="feather-home me-2"></i>{{ __('hrms.org.legal_entities') }}
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="business-units-tab" data-bs-toggle="tab" data-bs-target="#business-units" type="button" role="tab" aria-controls="business-units" aria-selected="false">
                                        <i class="feather-briefcase me-2"></i>{{ __('hrms.org.business_units') }}
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="branches-tab" data-bs-toggle="tab" data-bs-target="#branches" type="button" role="tab" aria-controls="branches" aria-selected="false">
                                        <i class="feather-map-pin me-2"></i>{{ __('hrms.org.branches') }}
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="departments-tab" data-bs-toggle="tab" data-bs-target="#departments" type="button" role="tab" aria-controls="departments" aria-selected="false">
                                        <i class="feather-users me-2"></i>{{ __('hrms.org.departments') }}
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="designations-tab" data-bs-toggle="tab" data-bs-target="#designations" type="button" role="tab" aria-controls="designations" aria-selected="false">
                                        <i class="feather-award me-2"></i>{{ __('hrms.org.designations') }}
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Tabs Content -->
                        <div class="col-12">
                            <div class="tab-content" id="orgTabsContent">
                                <div class="tab-pane fade show active" id="legal-entities" role="tabpanel" aria-labelledby="legal-entities-tab">
                                    @include('modules.hrms.org-structure.tabs.legal-entities')
                                </div>
                                <div class="tab-pane fade" id="business-units" role="tabpanel" aria-labelledby="business-units-tab">
                                    @include('modules.hrms.org-structure.tabs.business-units')
                                </div>
                                <div class="tab-pane fade" id="branches" role="tabpanel" aria-labelledby="branches-tab">
                                    @include('modules.hrms.org-structure.tabs.branches')
                                </div>
                                <div class="tab-pane fade" id="departments" role="tabpanel" aria-labelledby="departments-tab">
                                    @include('modules.hrms.org-structure.tabs.departments')
                                </div>
                                <div class="tab-pane fade" id="designations" role="tabpanel" aria-labelledby="designations-tab">
                                    @include('modules.hrms.org-structure.tabs.designations')
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Salary Structure Pane -->
                <div class="tab-pane fade" id="salary-structure-pane" role="tabpanel" aria-labelledby="salary-structure-menu">
                    @include('modules.hrms.salary-structure.tabs.salary-components')
                </div>

                <!-- Leave Structure Pane -->
                <div class="tab-pane fade" id="leave-structure-pane" role="tabpanel" aria-labelledby="leave-structure-menu">
                    <div class="card stretch stretch-full mb-0">
                        <div class="card-body py-5 text-center">
                            <div class="avatar-text avatar-xl bg-soft-warning text-warning mx-auto mb-4" style="width: 60px; height: 60px; min-width: 60px; min-height: 60px;">
                                <i class="feather-calendar fs-24"></i>
                            </div>
                            <h4 class="fw-bold mb-2">{{ __('hrms.org.leave_structure_settings') }}</h4>
                            <p class="text-muted mb-0">{{ __('hrms.org.leave_structure_desc') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include all Unified Modals at body level to prevent parent wrapper blur/backdrop overlay bugs -->
    @include('modules.hrms.partials.modals')

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Move all modal divs to the body root level to prevent stacking context/blur backdrop issues!
            document.querySelectorAll('.modal').forEach(function(modal) {
                document.body.appendChild(modal);
            });

            // Toggle Add buttons in header on tab change
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                const targetTabId = e.target.getAttribute('aria-controls');
                $('.org-add-btn-wrapper').addClass('d-none');
                $('#add-btn-' + targetTabId).removeClass('d-none');
            });

            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            const companyFormMode = @json(old('form_mode'));
            if (tabParam) {
                setTimeout(function() {
                    const tabButton = document.getElementById(tabParam + '-tab');
                    if (tabButton) {
                        tabButton.click();
                    }
                }, 100);
            }

            // AJAX Quick Search, Sort, Filter, and Pagination for Organization Structure
            let searchTimeout = null;
            let activeOrgRequest = null;

            function syncOrgForms(params, tabId) {
                const prefix = {
                    'legal-entities': 'co_',
                    'business-units': 'bu_',
                    'branches': 'br_',
                    'departments': 'dp_',
                    'designations': 'ds_'
                }[tabId];

                if (!prefix) return;

                const fields = [prefix + 'search', prefix + 'status', prefix + 'sort'];
                if (tabId === 'business-units') fields.push('bu_company_id');
                if (tabId === 'branches') { fields.push('br_company_id'); fields.push('br_business_unit_id'); }
                if (tabId === 'departments') { fields.push('dp_company_id'); fields.push('dp_business_unit_id'); fields.push('dp_branch_id'); }
                if (tabId === 'designations') fields.push('ds_department_id');

                fields.forEach(name => {
                    document.querySelectorAll(`[name="${name}"]`).forEach(field => {
                        // Skip the active search input so we don't disrupt active typing
                        if (field === document.activeElement) return;

                        field.value = params.get(name) || '';
                        if (field.tagName === 'SELECT' && $(field).hasClass('select2-hidden-accessible')) {
                            $(field).trigger('change.select2');
                        }
                    });
                });
            }

            function syncSortLinks(params, tabId) {
                const prefix = {
                    'legal-entities': 'co_',
                    'business-units': 'bu_',
                    'branches': 'br_',
                    'departments': 'dp_',
                    'designations': 'ds_'
                }[tabId];

                if (!prefix) return;

                const sortParam = prefix + 'sort';
                const currentSort = params.get(sortParam) || 'name_asc';

                const tabPane = document.getElementById(tabId);
                if (!tabPane) return;

                tabPane.querySelectorAll('.dropdown-item[href*="' + sortParam + '="]').forEach(link => {
                    const urlObj = new URL(link.href, window.location.origin);
                    const sortVal = urlObj.searchParams.get(sortParam);

                    link.classList.remove('active');
                    const existingCheck = link.querySelector('.feather-check');
                    if (existingCheck) {
                        existingCheck.remove();
                    }

                    if (sortVal === currentSort) {
                        link.classList.add('active');
                        const checkIcon = document.createElement('i');
                        checkIcon.className = 'feather-check ms-3';
                        link.appendChild(checkIcon);
                    }
                });
            }

            function refreshOrgTabList(url, tabId, options) {
                const targetUrl = url instanceof URL ? url : new URL(url, window.location.origin);

                if (activeOrgRequest) {
                    activeOrgRequest.abort();
                }

                const controller = new AbortController();
                activeOrgRequest = controller;

                const tabPane = document.getElementById(tabId);
                const targetIds = {
                    'legal-entities': {
                        tbody: 'companiesTableBody',
                        pagination: 'companiesPaginationWrapper'
                    },
                    'business-units': {
                        tbody: 'businessUnitsTableBody',
                        pagination: 'businessUnitsPaginationWrapper'
                    },
                    'branches': {
                        tbody: 'branchesTableBody',
                        pagination: 'branchesPaginationWrapper'
                    },
                    'departments': {
                        tbody: 'departmentsTableBody',
                        pagination: 'departmentsPaginationWrapper'
                    },
                    'designations': {
                        tbody: 'designationsTableBody',
                        pagination: 'designationsPaginationWrapper'
                    }
                }[tabId];

                if (tabPane) {
                    tabPane.classList.add('is-loading');
                }

                fetch(targetUrl.toString(), {
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
                    
                    if (targetIds) {
                        const newTbody = doc.getElementById(targetIds.tbody);
                        const oldTbody = document.getElementById(targetIds.tbody);
                        const newPagination = doc.getElementById(targetIds.pagination);
                        const oldPagination = document.getElementById(targetIds.pagination);

                        if (newTbody && oldTbody) {
                            oldTbody.innerHTML = newTbody.innerHTML;
                        }
                        if (newPagination && oldPagination) {
                            oldPagination.innerHTML = newPagination.innerHTML;
                        }
                    }

                    // Sync rest of the fields/dropdowns and active sorting icons
                    syncOrgForms(targetUrl.searchParams, tabId);
                    syncSortLinks(targetUrl.searchParams, tabId);

                    // Push state to update browser URL
                    history.pushState(null, '', targetUrl.toString());
                })
                .catch(function (error) {
                    if (error.name !== 'AbortError') {
                        window.location.href = targetUrl.toString();
                    }
                })
                .finally(function () {
                    if (activeOrgRequest === controller) {
                        if (tabPane) {
                            tabPane.classList.remove('is-loading');
                        }
                        activeOrgRequest = null;
                    }
                });
            }

            // 1. Debounced search input handler
            $(document).on('input', 'input[name$="_search"]', function () {
                const form = this.closest('form');
                if (!form) return;
                const tabId = form.querySelector('input[name="tab"]').value;
                const url = new URL(form.action || window.location.href);
                
                const formData = new FormData(form);
                for (const [key, val] of formData.entries()) {
                    url.searchParams.set(key, val);
                }

                // Reset page parameter for this tab on new search
                const pageParam = {
                    'legal-entities': 'co_page',
                    'business-units': 'bu_page',
                    'branches': 'br_page',
                    'departments': 'dp_page',
                    'designations': 'ds_page'
                }[tabId];
                if (pageParam) {
                    url.searchParams.delete(pageParam);
                }

                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function () {
                    refreshOrgTabList(url, tabId);
                }, 250);
            });

            // 2. Intercept GET form submissions (search/filters)
            $(document).on('submit', '#orgTabsContent form', function (event) {
                const form = this;
                if (form.method && form.method.toLowerCase() !== 'get') {
                    return;
                }
                event.preventDefault();
                const tabId = form.querySelector('input[name="tab"]').value;
                const url = new URL(form.action || window.location.href);
                
                const formData = new FormData(form);
                for (const [key, val] of formData.entries()) {
                    url.searchParams.set(key, val);
                }

                const pageParam = {
                    'legal-entities': 'co_page',
                    'business-units': 'bu_page',
                    'branches': 'br_page',
                    'departments': 'dp_page',
                    'designations': 'ds_page'
                }[tabId];
                if (pageParam) {
                    url.searchParams.delete(pageParam);
                }

                refreshOrgTabList(url, tabId);
                
                // Close the filter dropdown menu safely
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');
            });

            // 3. Intercept Sort, Reset and Pagination links
            $(document).on('click', '#orgTabsContent a[href]', function (event) {
                const href = this.getAttribute('href');
                if (!href || href.startsWith('javascript:')) return;

                const urlObj = new URL(href, window.location.origin);
                const tabId = urlObj.searchParams.get('tab');
                const supportedTabs = ['legal-entities', 'business-units', 'branches', 'departments', 'designations'];

                if (!tabId || !supportedTabs.includes(tabId)) return;

                event.preventDefault();
                refreshOrgTabList(urlObj, tabId);
            });

            // Initialize Select2 on all filter dropdown selects to follow the theme's design
            function initFilterSelects() {
                $('.erp-filter-dropdown').each(function() {
                    const dropdown = $(this);
                    const menu = dropdown.find('.dropdown-menu');
                    dropdown.find('select').each(function() {
                        const select = $(this);
                        if (select.hasClass('select2-hidden-accessible')) {
                            select.select2('destroy');
                        }
                        select.select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            dropdownParent: menu
                        });
                    });
                });
            }
            initFilterSelects();

            if (companyFormMode === 'add_company') {
                setTimeout(function() {
                    const modalElement = document.getElementById('addCompanyModal');
                    if (modalElement) {
                        bootstrap.Modal.getOrCreateInstance(modalElement).show();
                    }
                }, 150);
            }

            if (companyFormMode === 'edit_company') {
                setTimeout(function() {
                    const modalElement = document.getElementById('editCompanyModal');
                    if (modalElement) {
                        bootstrap.Modal.getOrCreateInstance(modalElement).show();
                    }
                }, 150);
            }

            // Generic modal Select2 initializer inside HRMS boundaries
            $(document).on('shown.bs.modal', '.modal', function () {
                var modal = $(this);

                // Trigger company filter for business unit modals
                const buCompSelects = modal.find('#add_bu_company_id, #edit_bu_company_id');
                buCompSelects.each(function () {
                    $(this).trigger('change');
                });

                // Trigger company/bu filters for branch modals
                const branchSelects = modal.find('#add_branch_company_id, #add_branch_business_unit_id, #edit_branch_company_id, #edit_branch_bu_id');
                branchSelects.each(function () {
                    $(this).trigger('change');
                });

                // Trigger company/bu/branch filters for department modals
                const deptSelects = modal.find('#add_dept_company_id, #add_dept_business_unit_id, #add_dept_branch_id, #edit_dept_company_id, #edit_dept_bu_id, #edit_dept_branch_id');
                deptSelects.each(function () {
                    $(this).trigger('change');
                });

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

            // Dynamic filtering of Unit Head by selected Company in Add Business Unit modal
            const addCompanySelect = $('#add_bu_company_id');
            const addHeadSelect = $('#add_bu_head_employee_id');
            if (addCompanySelect.length && addHeadSelect.length) {
                addCompanySelect.on('change', function () {
                    const compId = addCompanySelect.val();
                    let originalOptions = addHeadSelect.data('original-options');
                    if (!originalOptions) {
                        originalOptions = addHeadSelect.find('option').clone();
                        addHeadSelect.data('original-options', originalOptions);
                    }

                    const currentSelected = addHeadSelect.val();
                    addHeadSelect.empty();

                    originalOptions.each(function () {
                        const opt = $(this);
                        const optVal = opt.val();
                        const optionCompId = opt.attr('data-company-id');

                        if (!optVal || !compId || String(optionCompId) === String(compId)) {
                            addHeadSelect.append(opt.clone());
                        }
                    });

                    if (addHeadSelect.find(`option[value="${currentSelected}"]`).length) {
                        addHeadSelect.val(currentSelected);
                    } else {
                        addHeadSelect.val('');
                    }

                    if (addHeadSelect.hasClass('select2-hidden-accessible')) {
                        addHeadSelect.trigger('change.select2');
                    }
                });
            }

            // Dynamic filtering of Branch Manager by business unit/company in modals
            function bindBranchManagerFilter(modalSelector, companySelectSelector, buSelectSelector, managerSelectSelector) {
                $(document).on('change', modalSelector + ' ' + companySelectSelector + ', ' + modalSelector + ' ' + buSelectSelector, function () {
                    const modal = $(modalSelector);
                    const companySelect = modal.find(companySelectSelector);
                    const buSelect = modal.find(buSelectSelector);
                    const managerSelect = modal.find(managerSelectSelector);

                    if (!managerSelect.length) return;

                    const compId = companySelect.val();
                    const buId = buSelect.val();

                    let originalOptions = managerSelect.data('original-options');
                    if (!originalOptions) {
                        originalOptions = managerSelect.find('option').clone();
                        managerSelect.data('original-options', originalOptions);
                    }

                    const currentSelected = managerSelect.val();
                    managerSelect.empty();

                    originalOptions.each(function () {
                        const opt = $(this);
                        const optVal = opt.val();
                        const optionBuId = opt.attr('data-business-unit-id');
                        const optionCompId = opt.attr('data-company-id');

                        if (!optVal) {
                            managerSelect.append(opt.clone());
                            return;
                        }

                        if (!buId && !compId) {
                            managerSelect.append(opt.clone());
                        } else if (buId) {
                            if (String(optionBuId) === String(buId)) {
                                managerSelect.append(opt.clone());
                            }
                        } else if (compId) {
                            if (String(optionCompId) === String(compId)) {
                                managerSelect.append(opt.clone());
                            }
                        }
                    });

                    if (managerSelect.find(`option[value="${currentSelected}"]`).length) {
                        managerSelect.val(currentSelected);
                    } else {
                        managerSelect.val('');
                    }

                    if (managerSelect.hasClass('select2-hidden-accessible')) {
                        managerSelect.trigger('change.select2');
                    }
                });
            }

            bindBranchManagerFilter('#addBranchModal', '#add_branch_company_id', '#add_branch_business_unit_id', '#add_branch_manager_id');
            bindBranchManagerFilter('#editBranchModal', '#edit_branch_company_id', '#edit_branch_bu_id', '#edit_branch_manager_id');

            // Dynamic filtering of Department Head by branch/business unit/company in modals
            function bindDeptHeadFilter(modalSelector, companySelectSelector, buSelectSelector, branchSelectSelector, headSelectSelector) {
                $(document).on('change', modalSelector + ' ' + companySelectSelector + ', ' + modalSelector + ' ' + buSelectSelector + ', ' + modalSelector + ' ' + branchSelectSelector, function () {
                    const modal = $(modalSelector);
                    const companySelect = modal.find(companySelectSelector);
                    const buSelect = modal.find(buSelectSelector);
                    const branchSelect = modal.find(branchSelectSelector);
                    const headSelect = modal.find(headSelectSelector);

                    if (!headSelect.length) return;

                    const companyId = companySelect.val();
                    const buId = buSelect.val();
                    const branchId = branchSelect.val();

                    let originalOptions = headSelect.data('original-options');
                    if (!originalOptions) {
                        originalOptions = headSelect.find('option').clone();
                        headSelect.data('original-options', originalOptions);
                    }

                    const currentSelected = headSelect.val();
                    headSelect.empty();

                    originalOptions.each(function () {
                        const opt = $(this);
                        const optVal = opt.val();
                        const optionBranchId = opt.attr('data-branch-id');
                        const optionBuId = opt.attr('data-business-unit-id');
                        const optionCompId = opt.attr('data-company-id');

                        if (!optVal) {
                            headSelect.append(opt.clone());
                            return;
                        }

                        if (branchId) {
                            if (String(optionBranchId) === String(branchId)) {
                                headSelect.append(opt.clone());
                            }
                        } else if (buId) {
                            if (String(optionBuId) === String(buId)) {
                                headSelect.append(opt.clone());
                            }
                        } else if (companyId) {
                            if (String(optionCompId) === String(companyId)) {
                                headSelect.append(opt.clone());
                            }
                        } else {
                            headSelect.append(opt.clone());
                        }
                    });

                    if (headSelect.find(`option[value="${currentSelected}"]`).length) {
                        headSelect.val(currentSelected);
                    } else {
                        headSelect.val('');
                    }

                    if (headSelect.hasClass('select2-hidden-accessible')) {
                        headSelect.trigger('change.select2');
                    }
                });

                // Hierarchical filters for BU and branch selects
                $(document).on('change', modalSelector + ' ' + companySelectSelector + ', ' + modalSelector + ' ' + buSelectSelector, function () {
                    const modal = $(modalSelector);
                    const companySelect = modal.find(companySelectSelector);
                    const buSelect = modal.find(buSelectSelector);
                    const branchSelect = modal.find(branchSelectSelector);

                    const companyId = companySelect.val();
                    const buId = buSelect.val();

                    if (buSelect.length) {
                        let originalBUs = buSelect.data('original-options');
                        if (!originalBUs) {
                            originalBUs = buSelect.find('option').clone();
                            buSelect.data('original-options', originalBUs);
                        }
                        const currentBU = buSelect.val();
                        buSelect.empty();
                        originalBUs.each(function () {
                            const opt = $(this);
                            if (!opt.val() || !companyId || String(opt.attr('data-company-id')) === String(companyId)) {
                                buSelect.append(opt.clone());
                            }
                        });
                        if (buSelect.find(`option[value="${currentBU}"]`).length) {
                            buSelect.val(currentBU);
                        } else {
                            buSelect.val('');
                        }
                        if (buSelect.hasClass('select2-hidden-accessible')) {
                            buSelect.trigger('change.select2');
                        }
                    }

                    if (branchSelect.length) {
                        let originalBranches = branchSelect.data('original-options');
                        if (!originalBranches) {
                            originalBranches = branchSelect.find('option').clone();
                            branchSelect.data('original-options', originalBranches);
                        }
                        const currentBranch = branchSelect.val();
                        branchSelect.empty();
                        originalBranches.each(function () {
                            const opt = $(this);
                            if (!opt.val()) {
                                branchSelect.append(opt.clone());
                                return;
                            }
                            if (buId) {
                                if (String(opt.attr('data-business-unit-id')) === String(buId)) {
                                    branchSelect.append(opt.clone());
                                }
                            } else if (companyId) {
                                if (String(opt.attr('data-company-id')) === String(companyId)) {
                                    branchSelect.append(opt.clone());
                                }
                            } else {
                                branchSelect.append(opt.clone());
                            }
                        });
                        if (branchSelect.find(`option[value="${currentBranch}"]`).length) {
                            branchSelect.val(currentBranch);
                        } else {
                            branchSelect.val('');
                        }
                        if (branchSelect.hasClass('select2-hidden-accessible')) {
                            branchSelect.trigger('change.select2');
                        }
                    }
                });
            }

            bindDeptHeadFilter('#addDeptModal', '#add_dept_company_id', '#add_dept_business_unit_id', '#add_dept_branch_id', '#add_dept_head_id');
            bindDeptHeadFilter('#editDeptModal', '#edit_dept_company_id', '#edit_dept_bu_id', '#edit_dept_branch_id', '#edit_dept_head_id');
        });
    </script>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush
