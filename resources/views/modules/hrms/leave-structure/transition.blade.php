@extends('layouts.duralux')

@section('title', __('hrms.leave.plan_transition') . ' | SaaS ERP')
@section('page-title', __('hrms.leave.plan_transition'))
@section('breadcrumb', 'HRMS / ' . __('hrms.sidebar.leave_structure') . ' / ' . __('hrms.leave.plan_transition'))

@section('page-actions')
    <a href="{{ route('hrms.leave-structure.index') }}" class="btn btn-primary fw-bold text-uppercase d-flex align-items-center gap-1">
        <i class="feather-arrow-left me-1"></i> {{ __('hrms.leave.back_to_structures') }}
    </a>
@endsection

@push('styles')
    <style>
        @media (min-width: 992px) {
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

        .employee-row:hover {
            background-color: #f8fafc;
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
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--bs-primary) 15%, transparent) !important;
        }
        .erp-filter-dropdown .form-select:focus {
            border-color: var(--bs-primary) !important;
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--bs-primary) 15%, transparent) !important;
            outline: none !important;
        }
    </style>
@endpush

@section('content')
    <div class="settings-container">
        <!-- Sidebar Column -->
        <div class="settings-sidebar-col">
            @include('modules.hrms.partials.settings-sidebar')
        </div>

        <!-- Content Column -->
        <div class="settings-content-col">
            @if(session('success'))
                <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
                <x-ui.alert variant="success" icon="feather-check-circle" dismissible>
                    {{ session('success') }}
                </x-ui.alert>
            @endif

            @if(session('error'))
                <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
                <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                    {{ session('error') }}
                </x-ui.alert>
            @endif

            <form action="{{ route('hrms.leave-structure.transition.process') }}" method="POST" id="bulkTransitionForm">
                @csrf
                <div class="row g-4">
                    <!-- Left: Employee Selection -->
                    <div class="col-lg-7 col-12">
                        <x-ui.card :title="__('hrms.leave.select_employees_for_transition')" bodyClass="p-0">
                            <x-slot name="headerAction">
                                <div class="d-flex align-items-center gap-2">
                                    <!-- Search Input -->
                                    <div class="theme-search-container" style="width: 180px !important; position: relative;">
                                        <i class="feather-search"></i>
                                        <input type="text" id="employeeSearchInput" class="theme-search-input" placeholder="{{ __('hrms.leave.search_employees') }}">
                                    </div>
                                </div>
                            </x-slot>

                            <!-- Select All Checkbox on the Left Side of List Header -->
                            <div class="p-3 border-bottom d-flex align-items-center bg-light justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="form-check mb-0 me-3">
                                        <input class="form-check-input" type="checkbox" id="selectAllEmployees" style="width: 18px; height: 18px; cursor: pointer;">
                                    </div>
                                    <span class="fw-semibold fs-12 text-dark">{{ __('hrms.leave.employee_list') }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-light text-secondary border fs-11 px-2.5 py-2 rounded-pill me-1" id="selectedCountBadge" data-template="{{ __('hrms.leave.selected_count', ['count' => '__count__']) }}">{{ __('hrms.leave.selected_count', ['count' => 0]) }}</span>

                                    <!-- Sort Dropdown -->
                                    <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                                        <a class="dropdown-item py-2 active" href="#" data-sort="name_asc" onclick="sortEmployees('name_asc', this); event.preventDefault();">{{ __('hrms.common.sort_name_asc') }}</a>
                                        <a class="dropdown-item py-2" href="#" data-sort="name_desc" onclick="sortEmployees('name_desc', this); event.preventDefault();">{{ __('hrms.common.sort_name_desc') }}</a>
                                        <a class="dropdown-item py-2" href="#" data-sort="id_asc" onclick="sortEmployees('id_asc', this); event.preventDefault();">{{ __('hrms.employees.tbl_code') }}</a>
                                    </x-ui.sort-dropdown>

                                    <x-ui.filter :label="__('hrms.common.filter')">
                                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                                        
                                        <div class="mb-3" style="min-width: 250px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_company') }}</label>
                                            <x-ui.odoo-form-ui type="select" name="filter_company" id="filter_company">
                                                <option value="">{{ __('hrms.common.all_companies') }}</option>
                                                @foreach($companies as $company)
                                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="mb-3" style="min-width: 250px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_department') }}</label>
                                            <x-ui.odoo-form-ui type="select" name="filter_department" id="filter_department">
                                                <option value="">{{ __('hrms.common.all_departments') }}</option>
                                                @foreach($departments as $dept)
                                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="mb-3" style="min-width: 250px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tab_leaves') }}</label>
                                            <x-ui.odoo-form-ui type="select" name="filter_plan" id="filter_plan">
                                                <option value="">{{ __('hrms.leave.leave_plans') }}</option>
                                                @foreach($leavePlans as $plan)
                                                    <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="dropdown-divider my-3"></div>

                                        <div class="d-flex gap-2">
                                            <button type="button" id="btnApplyFilters" class="btn btn-primary btn-sm flex-grow-1">{{ __('hrms.common.apply') }}</button>
                                            <button type="button" id="btnResetFilters" class="btn btn-light btn-sm border flex-grow-1">{{ __('hrms.common.reset') }}</button>
                                        </div>
                                    </x-ui.filter>
                                </div>
                            </div>

                            <!-- Employees List -->
                            <div style="max-height: 450px; overflow-y: auto;" id="employeesListContainer">
                                @forelse($employees as $emp)
                                    @php
                                        $planName = $emp->leavePlan ? $emp->leavePlan->name : 'No Leave Plan';
                                    @endphp
                                    <div class="employee-row d-flex align-items-center justify-content-between p-3 border-bottom transition-all" 
                                         data-company="{{ $emp->company_id }}" 
                                         data-department="{{ $emp->department_id }}"
                                         data-plan="{{ $emp->leave_plan_id ?: '' }}"
                                         data-name="{{ strtolower($emp->full_name) }}"
                                         data-id="{{ strtolower($emp->employee_id ?: '') }}">
                                        <div class="d-flex align-items-center">
                                            <div class="form-check mb-0 me-3">
                                                <input class="form-check-input employee-checkbox" type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" id="emp_check_{{ $emp->id }}">
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark fs-13">{{ $emp->full_name }}</div>
                                                <div class="text-muted fs-11">
                                                    {{ __('hrms.employees.tbl_code') }}: {{ $emp->employee_id ?: 'N/A' }} &bull; {{ __('hrms.employees.tbl_department') }}: {{ $emp->department->name ?? 'N/A' }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-light text-secondary border fs-10 px-2 py-1 rounded-pill">
                                                {{ __('hrms.common.current') }}: {{ $planName }}
                                            </span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-4 text-center text-muted">
                                        <i class="feather-users fs-24 mb-2"></i>
                                        <p class="mb-0">{{ __('hrms.roster.no_employees_matching') }}</p>
                                    </div>
                                @endforelse
                            </div>
                        </x-ui.card>
                    </div>

                    <!-- Right: Plan & Transition Settings -->
                    <div class="col-lg-5 col-12">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white border-bottom p-4">
                                <h6 class="card-title fw-bold text-dark mb-0">{{ __('hrms.leave.transition_parameters') }}</h6>
                            </div>
                            <div class="card-body p-4">
                                <!-- Target Plan -->
                                <div class="mb-4">
                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.target_leave_plan')" name="new_leave_plan_id" id="new_leave_plan_id" select2-selector="default" :required="true">
                                        <option value="" disabled selected>{{ __('hrms.leave.select_target_leave_plan') }}</option>
                                        @foreach($leavePlans as $plan)
                                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <!-- Transition Method -->
                                <div class="mb-4">
                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.transition_method')" name="leave_transition_action" id="leave_transition_action">
                                        <option value="transfer" selected>{{ __('hrms.leave.transfer_full_quota') }}</option>
                                        <option value="prorate">{{ __('hrms.leave.prorate_pro_rata_quota') }}</option>
                                    </x-ui.odoo-form-ui>
                                    <small class="form-text text-muted fs-11 mt-1 d-block">
                                        {{ __('hrms.leave.transition_method_desc') }}
                                    </small>
                                </div>

                                <!-- Unused Leaves Action -->
                                <div class="mb-4">
                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.unused_leaves_action')" name="leave_transition_unused" id="leave_transition_unused">
                                        <option value="carry" selected>{{ __('hrms.leave.transition_carry') }}</option>
                                        <option value="encash">Encash Unused Leaves (Payout & Reset)</option>
                                        <option value="lapse">{{ __('hrms.leave.transition_lapse') }}</option>
                                    </x-ui.odoo-form-ui>
                                    <small class="form-text text-muted fs-11 mt-1 d-block">
                                        {{ __('hrms.leave.unused_leaves_action_desc') }}
                                    </small>
                                </div>

                                <div class="border-top pt-4">
                                    <x-ui.button type="submit" variant="primary" id="transitionSubmitBtn" class="w-100" style="height: 42px;" data-error-msg="{{ __('hrms.leave.select_at_least_one_employee') }}" data-loading-template="{{ __('hrms.leave.transitioning_count_employees', ['count' => '__count__']) }}">
                                        <span class="spinner-border spinner-border-sm d-none me-2" id="transitionSpinner" role="status" aria-hidden="true"></span>
                                        <span id="transitionBtnText"><i class="feather-shuffle me-1"></i> {{ __('hrms.leave.confirm_and_transition_plans') }}</span>
                                    </x-ui.button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        $(document).ready(function() {
            // Sort Employees logic
            window.sortEmployees = function(criteria, element) {
                $(element).siblings().removeClass('active');
                $(element).addClass('active');

                let container = $('#employeesListContainer');
                let rows = container.find('.employee-row').get();

                rows.sort(function(a, b) {
                    let keyA, keyB;
                    if (criteria === 'name_asc' || criteria === 'name_desc') {
                        keyA = $(a).attr('data-name');
                        keyB = $(b).attr('data-name');
                    } else if (criteria === 'id_asc') {
                        keyA = $(a).attr('data-id');
                        keyB = $(b).attr('data-id');
                    }

                    if (keyA < keyB) return (criteria === 'name_desc') ? 1 : -1;
                    if (keyA > keyB) return (criteria === 'name_desc') ? -1 : 1;
                    return 0;
                });

                $.each(rows, function(index, row) {
                    container.append(row);
                });
            };

            // Filter logic
            function filterEmployees() {
                let company = $('#filter_company').val();
                let department = $('#filter_department').val();
                let plan = $('#filter_plan').val();
                let search = $('#employeeSearchInput').val().toLowerCase().trim();

                $('.employee-row').each(function() {
                    let row = $(this);
                    let rowCompany = row.attr('data-company');
                    let rowDepartment = row.attr('data-department');
                    let rowPlan = row.attr('data-plan');
                    let rowName = row.attr('data-name');
                    let rowId = row.attr('data-id');

                    let matchCompany = !company || rowCompany === company;
                    let matchDepartment = !department || rowDepartment === department;
                    let matchPlan = !plan || rowPlan === plan;
                    let matchSearch = !search || rowName.includes(search) || rowId.includes(search);

                    if (matchCompany && matchDepartment && matchPlan && matchSearch) {
                        row.removeClass('d-none');
                    } else {
                        row.addClass('d-none');
                        row.find('.employee-checkbox').prop('checked', false);
                    }
                });
                
                updateSelectAllState();
                updateSelectedCount();
            }

            // Bind filter actions
            $('#btnApplyFilters').on('click', function() {
                filterEmployees();
                $('.erp-filter-dropdown .dropdown-menu').removeClass('show');
                $('.erp-filter-dropdown').removeClass('show');
            });

            $('#btnResetFilters').on('click', function() {
                $('#filter_company').val('').trigger('change');
                $('#filter_department').val('').trigger('change');
                $('#filter_plan').val('').trigger('change');
                filterEmployees();
                $('.erp-filter-dropdown .dropdown-menu').removeClass('show');
                $('.erp-filter-dropdown').removeClass('show');
            });

            $('#employeeSearchInput').on('input', filterEmployees);

            // Select all logic
            $('#selectAllEmployees').on('change', function() {
                let checked = $(this).is(':checked');
                $('.employee-row:not(.d-none) .employee-checkbox').prop('checked', checked);
                updateSelectedCount();
            });

            // Maintain select all state and count when individual rows are clicked
            $(document).on('change', '.employee-checkbox', function() {
                updateSelectAllState();
                updateSelectedCount();
            });

            function updateSelectAllState() {
                let totalVisible = $('.employee-row:not(.d-none) .employee-checkbox').length;
                let totalChecked = $('.employee-row:not(.d-none) .employee-checkbox:checked').length;
                
                $('#selectAllEmployees').prop('checked', totalVisible > 0 && totalVisible === totalChecked);
            }

            function updateSelectedCount() {
                let count = $('.employee-checkbox:checked').length;
                let template = $('#selectedCountBadge').attr('data-template') || '__count__ selected';
                $('#selectedCountBadge').text(template.replace('__count__', count));
            }

            // Form Submit loader
            $('#bulkTransitionForm').on('submit', function(e) {
                let selectedCount = $('.employee-checkbox:checked').length;
                if (selectedCount === 0) {
                    e.preventDefault();
                    let errorMsg = $('#transitionSubmitBtn').attr('data-error-msg') || 'Please select at least one employee.';
                    let toast = document.createElement('div');
                    toast.className = 'position-fixed top-0 end-0 p-3';
                    toast.style.zIndex = '9999';
                    toast.innerHTML = `
                        <div class="toast show align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                          <div class="d-flex">
                            <div class="toast-body d-flex align-items-center gap-2">
                              <i class="feather-alert-octagon"></i> ${errorMsg}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                          </div>
                        </div>
                    `;
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 4000);
                    return false;
                }

                $('#transitionSubmitBtn').attr('disabled', true);
                $('#transitionSpinner').removeClass('d-none');
                let loadingTemplate = $('#transitionSubmitBtn').attr('data-loading-template') || 'Transitioning __count__ Employee(s)...';
                $('#transitionBtnText').text(loadingTemplate.replace('__count__', selectedCount));
            });
        });
    </script>
    @endpush
@endsection
