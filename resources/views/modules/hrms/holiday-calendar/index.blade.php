@extends('layouts.duralux')

@section('title', __('hrms.holiday.title') . ' | SaaS ERP')
@section('page-title', __('hrms.holiday.title'))
@section('breadcrumb', __('hrms.holiday.breadcrumb'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addHolidayModal" class="fw-bold text-uppercase">
            {{ __('hrms.holiday.add_holiday') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* ── Table Responsive Dropdown Visibility Fix ── */
        .table-responsive {
            position: relative;
        }
        .table-responsive:has(.dropdown-menu.show) {
            overflow: visible !important;
        }

        /* Modern layout container for connected settings sidebar */
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
        
        /* Local Form Style Overrides */
        .modal .odoo-form-label {
            width: 160px !important;
        }
        .modal .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding-right: 24px !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }

        .holidays-pane-wrapper.is-loading {
            opacity: 0.6;
            pointer-events: none;
            transition: opacity 0.15s ease-in-out;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
@endpush

@section('content')
    <div class="settings-container">
        <div class="settings-content-col erp-single-panel bg-white flex-grow-1 p-4 shadow-sm rounded border-0 text-dark holidays-pane-wrapper" id="holidays-pane">
            
            @if(session('success'))
                <x-ui.alert variant="success" :dismissible="true" icon="feather-check-circle" class="mb-3">
                    {{ session('success') }}
                </x-ui.alert>
            @endif

            @if($errors->any())
                <x-ui.alert variant="danger" :dismissible="true" icon="feather-alert-triangle" class="mb-3">
                    <strong>{{ __('hrms.common.validation_errors') }}</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
                <div>
                    <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">{{ __('hrms.holiday.title') }}</h5>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Search & Filters Form using UI Dropdowns -->
                    <form method="GET" action="{{ route('hrms.holidays.index') }}" class="d-flex align-items-center gap-2 m-0" id="holidayFilterForm">
                        <input type="hidden" name="sort" id="holiday_sort" value="{{ $filters['sort'] ?? 'date_asc' }}">

                        <div class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 220px; max-width: 280px; height: 38px;">
                            <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                            <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.holiday.search_placeholder') }}" value="{{ $filters['search'] ?? '' }}" style="box-shadow: none; height: 32px;">
                        </div>

                        <div class="d-flex gap-2">
                            <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                                <a class="dropdown-item py-2 d-flex justify-content-between align-items-center {{ ($filters['sort'] ?? 'date_asc') == 'date_asc' ? 'active' : '' }}" href="#" onclick="changeSort('holiday', 'date_asc', this); event.preventDefault();">
                                    <span>{{ __('hrms.holiday.sort_date_asc') }}</span>
                                    @if(($filters['sort'] ?? 'date_asc') == 'date_asc') <i class="feather-check ms-2"></i> @endif
                                </a>
                                <a class="dropdown-item py-2 d-flex justify-content-between align-items-center {{ ($filters['sort'] ?? '') == 'date_desc' ? 'active' : '' }}" href="#" onclick="changeSort('holiday', 'date_desc', this); event.preventDefault();">
                                    <span>{{ __('hrms.holiday.sort_date_desc') }}</span>
                                    @if(($filters['sort'] ?? '') == 'date_desc') <i class="feather-check ms-2"></i> @endif
                                </a>
                                <div class="dropdown-divider my-1"></div>
                                <a class="dropdown-item py-2 d-flex justify-content-between align-items-center {{ ($filters['sort'] ?? '') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('holiday', 'name_asc', this); event.preventDefault();">
                                    <span>{{ __('hrms.common.sort_name_asc') }}</span>
                                    @if(($filters['sort'] ?? '') == 'name_asc') <i class="feather-check ms-2"></i> @endif
                                </a>
                                <a class="dropdown-item py-2 d-flex justify-content-between align-items-center {{ ($filters['sort'] ?? '') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('holiday', 'name_desc', this); event.preventDefault();">
                                    <span>{{ __('hrms.common.sort_name_desc') }}</span>
                                    @if(($filters['sort'] ?? '') == 'name_desc') <i class="feather-check ms-2"></i> @endif
                                </a>
                            </x-ui.sort-dropdown>

                            <x-ui.filter :label="__('hrms.common.filter')" offset="0, 5" :reset-url="route('hrms.holidays.index')" :submit-label="__('hrms.common.apply')" :reset-label="__('hrms.common.reset')">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                                
                                <div class="mb-3" style="min-width: 260px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.holiday.scope_company') }}</label>
                                    <select name="company_id" id="filter_company_id" class="form-select form-select-sm font-size-13">
                                        <option value="">{{ __('hrms.holiday.all_companies') }}</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}" @selected((string)($filters['company_id'] ?? '') === (string)$company->id)>{{ $company->company_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.holiday.scope_bu') }}</label>
                                    <select name="business_unit_id" id="filter_business_unit_id" class="form-select form-select-sm font-size-13">
                                        <option value="">{{ __('hrms.holiday.all_business_units') }}</option>
                                        @foreach($businessUnits as $bu)
                                            <option value="{{ $bu->id }}" data-company-id="{{ $bu->company_id }}" @selected((string)($filters['business_unit_id'] ?? '') === (string)$bu->id)>{{ $bu->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.holiday.scope_branch') }}</label>
                                    <select name="branch_id" id="filter_branch_id" class="form-select form-select-sm font-size-13">
                                        <option value="">{{ __('hrms.holiday.all_branches') }}</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" data-company-id="{{ $branch->company_id }}" data-business-unit-id="{{ $branch->business_unit_id }}" @selected((string)($filters['branch_id'] ?? '') === (string)$branch->id)>{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.holiday.select_year') }}</label>
                                    <select name="year" id="filter_year" class="form-select form-select-sm font-size-13">
                                        <option value="">{{ __('hrms.holiday.all_years') }}</option>
                                        @foreach($availableYears as $year)
                                            <option value="{{ $year }}" @selected((string)($filters['year'] ?? '') === (string)$year)>{{ $year }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.holiday.status') }}</label>
                                    <select name="status" id="filter_status" class="form-select form-select-sm font-size-13">
                                        <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                        <option value="1" @selected((string)($filters['status'] ?? '') === '1')>{{ __('hrms.holiday.active') }}</option>
                                        <option value="0" @selected((string)($filters['status'] ?? '') === '0')>{{ __('hrms.holiday.inactive') }}</option>
                                    </select>
                                </div>
                            </x-ui.filter>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Active filter badges --}}
            @php
                $statusFilter = $filters['status'] ?? '';
                $hasActiveFilters = !empty($filters['search']) || !empty($filters['company_id']) || !empty($filters['business_unit_id']) || !empty($filters['branch_id']) || !empty($filters['year']) || ($statusFilter !== '' && $statusFilter !== null);
            @endphp
            @if($hasActiveFilters)
                <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                    <span class="fs-12 text-muted fw-semibold"><i class="feather-filter me-1"></i>{{ __('hrms.common.active_filters') }}</span>
                    @if(!empty($filters['search']))
                        <x-ui.badge variant="primary" soft class="d-inline-flex align-items-center gap-1">
                            <i class="feather-search fs-11"></i> "{{ $filters['search'] }}"
                        </x-ui.badge>
                    @endif
                    @if(!empty($filters['company_id']) && ($selComp = $companies->firstWhere('id', $filters['company_id'])))
                        <x-ui.badge variant="primary" soft class="d-inline-flex align-items-center gap-1">
                            <i class="feather-briefcase fs-11"></i> {{ $selComp->company_name }}
                        </x-ui.badge>
                    @endif
                    @if(!empty($filters['business_unit_id']) && ($selBU = $businessUnits->firstWhere('id', $filters['business_unit_id'])))
                        <x-ui.badge variant="info" soft class="d-inline-flex align-items-center gap-1">
                            <i class="feather-grid fs-11"></i> {{ $selBU->name }}
                        </x-ui.badge>
                    @endif
                    @if(!empty($filters['branch_id']) && ($selBranch = $branches->firstWhere('id', $filters['branch_id'])))
                        <x-ui.badge variant="warning" soft class="d-inline-flex align-items-center gap-1">
                            <i class="feather-git-commit fs-11"></i> {{ $selBranch->name }}
                        </x-ui.badge>
                    @endif
                    @if(!empty($filters['year']))
                        <x-ui.badge variant="secondary" soft class="d-inline-flex align-items-center gap-1">
                            <i class="feather-calendar fs-11"></i> {{ $filters['year'] }}
                        </x-ui.badge>
                    @endif
                    @if($statusFilter !== '' && $statusFilter !== null)
                        <x-ui.badge variant="{{ (string)$statusFilter === '1' ? 'success' : 'danger' }}" soft class="d-inline-flex align-items-center gap-1">
                            {{ (string)$statusFilter === '1' ? __('hrms.holiday.active') : __('hrms.holiday.inactive') }}
                        </x-ui.badge>
                    @endif
                    <a href="{{ route('hrms.holidays.index') }}" class="text-danger fs-12 text-decoration-none fw-semibold ms-1">
                        <i class="feather-x fs-11"></i> {{ __('hrms.common.clear_all') }}
                    </a>
                </div>
            @endif

            <!-- Table content -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center" style="table-layout: fixed; width: 100%;">
                    <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="text-start px-3" style="width: 6%;">#</th>
                            <th style="width: 16%;">{{ __('hrms.holiday.holiday_date') }}</th>
                            <th style="width: 24%;">{{ __('hrms.holiday.holiday_name') }}</th>
                            <th style="width: 18%;">{{ __('hrms.holiday.scope') }}</th>
                            <th style="width: 20%;">{{ __('hrms.org.description') }}</th>
                            <th style="width: 8%;">{{ __('hrms.holiday.status') }}</th>
                            <th class="text-end px-3" style="width: 8%;">{{ __('hrms.org.tbl_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="holidaysTableBody">
                        @forelse($holidays as $holiday)
                            <tr>
                                <td class="text-start px-3 font-weight-semibold text-secondary">{{ $loop->iteration + ($holidays->currentPage() - 1) * $holidays->perPage() }}</td>
                                <td>
                                    <div class="fw-bold text-dark fs-13">
                                        <i class="feather-calendar me-1.5 text-primary"></i>
                                        {{ $holiday->holiday_date ? $holiday->holiday_date->format('Y-m-d') : '-' }}
                                    </div>
                                </td>
                                <td>
                                    <span class="fs-13 fw-semibold text-dark">{{ $holiday->name }}</span>
                                </td>
                                <td>
                                    @if($holiday->branch_id && $holiday->branch)
                                        <x-ui.badge variant="warning" soft class="d-inline-flex align-items-center gap-1">
                                            <i class="feather-git-commit fs-10"></i>
                                            {{ $holiday->branch->name }}
                                        </x-ui.badge>
                                    @elseif($holiday->business_unit_id && $holiday->businessUnit)
                                        <x-ui.badge variant="info" soft class="d-inline-flex align-items-center gap-1">
                                            <i class="feather-grid fs-10"></i>
                                            {{ $holiday->businessUnit->name }}
                                        </x-ui.badge>
                                    @elseif($holiday->company_id && $holiday->company)
                                        <x-ui.badge variant="primary" soft class="d-inline-flex align-items-center gap-1">
                                            <i class="feather-briefcase fs-10"></i>
                                            {{ $holiday->company->company_name }}
                                        </x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" soft class="d-inline-flex align-items-center gap-1">
                                            <i class="feather-globe fs-10"></i>
                                            {{ __('hrms.holiday.scope_global_short') }}
                                        </x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-secondary small text-truncate" title="{{ $holiday->description }}">
                                    {{ $holiday->description ?: '-' }}
                                </td>
                                <td>
                                    <x-ui.status-badge :status="$holiday->status ? 'active' : 'inactive'" />
                                </td>
                                <td class="pe-3 text-end">
                                    <x-ui.action-dropdown>
                                        <li>
                                            <a class="dropdown-item" href="javascript:void(0)" 
                                               data-bs-toggle="modal" 
                                               data-bs-target="#editHolidayModal"
                                               data-id="{{ $holiday->id }}"
                                               data-name="{{ $holiday->name }}"
                                               data-date="{{ $holiday->holiday_date ? $holiday->holiday_date->format('Y-m-d') : '' }}"
                                               data-description="{{ $holiday->description }}"
                                               data-company-id="{{ $holiday->company_id }}"
                                               data-business-unit-id="{{ $holiday->business_unit_id }}"
                                               data-branch-id="{{ $holiday->branch_id }}"
                                               data-status="{{ $holiday->status ? '1' : '0' }}">
                                                <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('hrms.common.edit') }}
                                            </a>
                                        </li>
                                        <li>
                                            <form action="{{ route('hrms.holidays.destroy', $holiday->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.holiday.confirm_delete') }}', { title: '{{ __('hrms.holiday.delete_holiday') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.common.delete') }}' });" class="m-0 p-0">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('hrms.common.delete') }}
                                                </button>
                                            </form>
                                        </li>
                                    </x-ui.action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-secondary">
                                    <i class="feather-calendar display-4 text-muted mb-3 d-block"></i>
                                    {{ __('hrms.holiday.empty') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="holidaysPaginationWrapper">
                <x-ui.pagination :paginator="$holidays" />
            </div>
        </div>
    </div>

    <!-- ADD HOLIDAY MODAL -->
    <x-ui.modal
        id="addHolidayModal"
        title='<i class="feather-plus me-2 text-primary"></i>{{ __("hrms.holiday.add_holiday") }}'
        size="lg"
        :centered="true"
        formAction="{{ route('hrms.holidays.store') }}"
        formMethod="POST"
        submitText="{{ __('hrms.common.save') }}"
        closeText="{{ __('hrms.common.close') }}"
    >
        @include('modules.hrms.holiday-calendar.holiday-form-fields', ['mode' => 'create'])
    </x-ui.modal>

    <!-- EDIT HOLIDAY MODAL -->
    <x-ui.modal
        id="editHolidayModal"
        title='<i class="feather-edit me-2 text-primary"></i>{{ __("hrms.holiday.edit_holiday") }}'
        size="lg"
        :centered="true"
        formAction="#"
        formMethod="PUT"
        submitText="{{ __('hrms.common.save') }}"
        closeText="{{ __('hrms.common.close') }}"
    >
        @include('modules.hrms.holiday-calendar.holiday-form-fields', ['mode' => 'edit'])
    </x-ui.modal>

    @push('scripts')
        <script>
            var activeRequest = null;
            function refreshHolidaysList(url) {
                if (activeRequest) {
                    activeRequest.abort();
                }

                const controller = new AbortController();
                activeRequest = controller;

                const pane = document.getElementById('holidays-pane');
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
                        throw new Error('{{ __('hrms.common.unable_refresh') }}');
                    }
                    return response.text();
                })
                .then(function (html) {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    
                    const newTbody = doc.getElementById('holidaysTableBody');
                    const oldTbody = document.getElementById('holidaysTableBody');
                    const newPagination = doc.getElementById('holidaysPaginationWrapper');
                    const oldPagination = document.getElementById('holidaysPaginationWrapper');

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
                            const icon = el.querySelector('.feather-check');
                            if (icon) icon.remove();
                        });
                    }
                    element.classList.add('active');
                    if (!element.querySelector('.feather-check')) {
                        const checkIcon = document.createElement('i');
                        checkIcon.className = 'feather-check ms-2';
                        element.appendChild(checkIcon);
                    }
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
                        refreshHolidaysList(url);
                    }
                }
            }

            // Setup cascading selectors for filtering
            function setupFilterCascading() {
                const companySelect = $('#filter_company_id');
                const buSelect = $('#filter_business_unit_id');
                const branchSelect = $('#filter_branch_id');

                if (!companySelect.length) return;

                // Cache original options
                if (!buSelect.data('original-options')) {
                    buSelect.data('original-options', buSelect.find('option').clone());
                }
                if (!branchSelect.data('original-options')) {
                    branchSelect.data('original-options', branchSelect.find('option').clone());
                }

                companySelect.on('change', function() {
                    const companyId = $(this).val();
                    const originalBUs = buSelect.data('original-options');
                    const currentBUVal = buSelect.val();
                    
                    buSelect.empty();
                    originalBUs.each(function() {
                        const option = $(this);
                        const optionCompId = option.data('company-id');
                        if (!option.val() || !companyId || String(optionCompId) === String(companyId)) {
                            buSelect.append(option.clone());
                        }
                    });
                    
                    if (buSelect.find(`option[value="${currentBUVal}"]`).length) {
                        buSelect.val(currentBUVal);
                    } else {
                        buSelect.val('');
                    }

                    filterBranches();
                });

                buSelect.on('change', function() {
                    const buVal = $(this).val();
                    const selectedOption = buSelect.find(`option[value="${buVal}"]`);
                    const companyId = selectedOption.data('company-id');
                    
                    if (buVal && companyId && String(companySelect.val()) !== String(companyId)) {
                        companySelect.val(companyId);
                    }
                    
                    filterBranches();
                });

                function filterBranches() {
                    const companyId = companySelect.val();
                    const buId = buSelect.val();
                    
                    const originalBranches = branchSelect.data('original-options');
                    const currentBranchVal = branchSelect.val();
                    branchSelect.empty();
                    
                    originalBranches.each(function() {
                        const option = $(this);
                        const optionCompId = option.data('company-id');
                        const optionBuId = option.data('business-unit-id');
                        
                        let matchesCompany = !companyId || String(optionCompId) === String(companyId);
                        let matchesBU = !buId || String(optionBuId) === String(buId);
                        
                        if (!option.val() || (matchesCompany && matchesBU)) {
                            branchSelect.append(option.clone());
                        }
                    });
                    
                    if (branchSelect.find(`option[value="${currentBranchVal}"]`).length) {
                        branchSelect.val(currentBranchVal);
                    } else {
                        branchSelect.val('');
                    }
                }
                
                // Initialize cascading dropdown values correctly on load
                const initialComp = companySelect.val();
                if (initialComp) {
                    const originalBUs = buSelect.data('original-options');
                    const currentBUVal = buSelect.val();
                    buSelect.empty();
                    originalBUs.each(function() {
                        const option = $(this);
                        if (!option.val() || String(option.data('company-id')) === String(initialComp)) {
                            buSelect.append(option.clone());
                        }
                    });
                    buSelect.val(currentBUVal);
                }

                const initialBU = buSelect.val();
                if (initialBU || initialComp) {
                    filterBranches();
                }
            }

            function setupModalCascadingHandlers(prefix) {
                const companySelect = $('#' + prefix + '_company_id');
                const buSelect = $('#' + prefix + '_business_unit_id');
                const branchSelect = $('#' + prefix + '_branch_id');

                if (!companySelect.length) return;

                // Cache original options once
                if (!buSelect.data('original-options')) {
                    buSelect.data('original-options', buSelect.find('option').clone());
                }
                if (!branchSelect.data('original-options')) {
                    branchSelect.data('original-options', branchSelect.find('option').clone());
                }

                companySelect.off('change.modalCascade').on('change.modalCascade', function() {
                    const companyId = $(this).val();
                    const originalBUs = buSelect.data('original-options');
                    const currentBUVal = buSelect.val();
                    
                    buSelect.empty();
                    originalBUs.each(function() {
                        const option = $(this);
                        const optionCompId = option.data('company-id');
                        if (!option.val() || !companyId || String(optionCompId) === String(companyId)) {
                            buSelect.append(option.clone());
                        }
                    });
                    
                    if (buSelect.find(`option[value="${currentBUVal}"]`).length) {
                        buSelect.val(currentBUVal);
                    } else {
                        buSelect.val('');
                    }
                    buSelect.trigger('change.select2');
                    
                    filterModalBranches();
                });

                buSelect.off('change.modalCascade').on('change.modalCascade', function() {
                    const buVal = $(this).val();
                    const selectedOption = buSelect.find(`option[value="${buVal}"]`);
                    const companyId = selectedOption.data('company-id');
                    
                    if (buVal && companyId && String(companySelect.val()) !== String(companyId)) {
                        companySelect.val(companyId).trigger('change.select2');
                    }
                    
                    filterModalBranches();
                });

                branchSelect.off('change.modalCascade').on('change.modalCascade', function() {
                    const branchVal = $(this).val();
                    const selectedOption = branchSelect.find(`option[value="${branchVal}"]`);
                    const companyId = selectedOption.data('company-id');
                    const buId = selectedOption.data('business-unit-id');

                    if (branchVal) {
                        if (buId && String(buSelect.val()) !== String(buId)) {
                            buSelect.val(buId).trigger('change.select2');
                        }
                        if (companyId && String(companySelect.val()) !== String(companyId)) {
                            companySelect.val(companyId).trigger('change.select2');
                        }
                    }
                });

                function filterModalBranches() {
                    const companyId = companySelect.val();
                    const buId = buSelect.val();
                    
                    const originalBranches = branchSelect.data('original-options');
                    const currentBranchVal = branchSelect.val();
                    branchSelect.empty();
                    
                    originalBranches.each(function() {
                        const option = $(this);
                        const optionCompId = option.data('company-id');
                        const optionBuId = option.data('business-unit-id');
                        
                        let matchesCompany = !companyId || String(optionCompId) === String(companyId);
                        let matchesBU = !buId || String(optionBuId) === String(buId);
                        
                        if (!option.val() || (matchesCompany && matchesBU)) {
                            branchSelect.append(option.clone());
                        }
                    });
                    
                    if (branchSelect.find(`option[value="${currentBranchVal}"]`).length) {
                        branchSelect.val(currentBranchVal);
                    } else {
                        branchSelect.val('');
                    }
                    branchSelect.trigger('change.select2');
                }
            }

            function populateModalFields(prefix, companyId, buId, branchId) {
                const companySelect = $('#' + prefix + '_company_id');
                const buSelect = $('#' + prefix + '_business_unit_id');
                const branchSelect = $('#' + prefix + '_branch_id');

                if (!buSelect.data('original-options')) {
                    buSelect.data('original-options', buSelect.find('option').clone());
                }
                if (!branchSelect.data('original-options')) {
                    branchSelect.data('original-options', branchSelect.find('option').clone());
                }

                // Filter BU options
                const originalBUs = buSelect.data('original-options');
                buSelect.empty();
                originalBUs.each(function() {
                    const option = $(this);
                    const optionCompId = option.data('company-id');
                    if (!option.val() || !companyId || String(optionCompId) === String(companyId)) {
                        buSelect.append(option.clone());
                    }
                });
                buSelect.val(buId ? String(buId) : '');

                // Filter Branch options
                const originalBranches = branchSelect.data('original-options');
                branchSelect.empty();
                originalBranches.each(function() {
                    const option = $(this);
                    const optionCompId = option.data('company-id');
                    const optionBuId = option.data('business-unit-id');
                    let matchesCompany = !companyId || String(optionCompId) === String(companyId);
                    let matchesBU = !buId || String(optionBuId) === String(buId);
                    if (!option.val() || (matchesCompany && matchesBU)) {
                        branchSelect.append(option.clone());
                    }
                });
                branchSelect.val(branchId ? String(branchId) : '');

                companySelect.val(companyId ? String(companyId) : '');

                companySelect.trigger('change.select2');
                buSelect.trigger('change.select2');
                branchSelect.trigger('change.select2');
            }

            $(document).ready(function() {
                setupFilterCascading();

                // Select2 modals initializer
                $(document).on('shown.bs.modal', '.modal', function() {
                    var modal = $(this);
                    
                    modal.find('select').each(function() {
                        var $select = $(this);
                        if ($select.hasClass("select2-hidden-accessible")) {
                            $select.select2('destroy');
                        }
                        $select.select2({
                            theme: 'bootstrap-5',
                            dropdownParent: modal.find('.modal-content'),
                            width: '100%'
                        });
                    });

                    // Set up cascading handlers
                    if (modal.attr('id') === 'addHolidayModal') {
                        setupModalCascadingHandlers('add_holiday');
                    } else if (modal.attr('id') === 'editHolidayModal') {
                        setupModalCascadingHandlers('edit_holiday');
                    }
                });

                // Reset Add Modal on open
                $('#addHolidayModal').on('show.bs.modal', function() {
                    var form = $('#addHolidayModal').find('form')[0];
                    if (form) {
                        form.reset();
                    }
                    populateModalFields('add_holiday', '', '', '');
                    $('#add_holiday_status').val('1').trigger('change.select2');
                });

                // Populate Edit Modal
                $('#editHolidayModal').on('show.bs.modal', function(event) {
                    var button = $(event.relatedTarget);
                    if (!button.length) return;

                    var holidayId = button.data('id');
                    var name = button.data('name');
                    var date = button.data('date');
                    var description = button.data('description');
                    var companyId = button.data('company-id') || '';
                    var buId = button.data('business-unit-id') || '';
                    var branchId = button.data('branch-id') || '';
                    var status = button.data('status');

                    var modal = $(this);
                    var updateUrl = "{{ route('hrms.holidays.update', ':id') }}".replace(':id', holidayId);
                    modal.find('form').attr('action', updateUrl);
                    
                    modal.find('#edit_holiday_name').val(name);
                    modal.find('#edit_holiday_holiday_date').val(date);
                    modal.find('#edit_holiday_description').val(description);
                    modal.find('#edit_holiday_status').val(String(status)).trigger('change.select2');

                    populateModalFields('edit_holiday', companyId, buId, branchId);
                });

                // Debounced quick search to avoid needing to press Enter
                var searchTimeout = null;
                $(document).on('input', 'input[name="search"]', function () {
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
                        refreshHolidaysList(url);
                    }, 250);
                });

                // Intercept GET form submissions (search/filters)
                $(document).on('submit', '#holidayFilterForm', function (event) {
                    const form = this;
                    event.preventDefault();
                    
                    const url = new URL(form.action || window.location.href);
                    const formData = new FormData(form);
                    for (const [key, val] of formData.entries()) {
                        url.searchParams.set(key, val);
                    }
                    url.searchParams.delete('page');

                    refreshHolidaysList(url);
                    
                    // Close the filter dropdown menu safely
                    $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                    $('.erp-filter-dropdown.show').removeClass('show');
                });

                // Intercept Pagination link clicks
                $(document).on('click', '#holidaysPaginationWrapper a[href]', function (event) {
                    const href = this.getAttribute('href');
                    if (!href || href.startsWith('javascript:') || href === '#') return;

                    event.preventDefault();
                    const urlObj = new URL(href, window.location.origin);
                    refreshHolidaysList(urlObj);
                });
            });
        </script>
    @endpush
@endsection


