@extends('layouts.duralux')

@section('title', __('hrms.holiday.title') . ' | SaaS ERP')
@section('page-title', __('hrms.holiday.title'))
@section('breadcrumb', 'HRMS / ' . __('hrms.holiday.title'))

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
        .badge-scope-global {
            background-color: rgba(99, 102, 241, 0.08) !important;
            color: #6366f1 !important;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .badge-scope-company {
            background-color: rgba(139, 92, 246, 0.08) !important;
            color: #8b5cf6 !important;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .badge-scope-bu {
            background-color: rgba(20, 184, 166, 0.08) !important;
            color: #14b8a6 !important;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .badge-scope-branch {
            background-color: rgba(245, 158, 11, 0.08) !important;
            color: #f59e0b !important;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
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
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <div class="settings-container">
        <div class="settings-content-col erp-single-panel bg-white flex-grow-1 p-4 shadow-sm rounded border-0 text-dark holidays-pane-wrapper" id="holidays-pane">
            
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="feather-check-circle me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="feather-alert-triangle me-2"></i> <strong>Validation Errors:</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
                <div>
                    <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">{{ __('hrms.holiday.title') }}</h5>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Search & Filters Form using UI Dropdowns -->
                    <form method="GET" action="{{ route('hrms.holidays.index') }}" class="d-flex align-items-center gap-2 m-0" id="holidayFilterForm">
                        <input type="hidden" name="sort" id="holiday_sort" value="{{ $filters['sort'] ?? 'date_asc' }}">

                        <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                            <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                            <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.holiday.search_placeholder') }}" value="{{ $filters['search'] ?? '' }}" style="box-shadow: none; height: 32px;">
                        </div>

                        <div class="d-flex gap-2">
                            <x-ui.sort-dropdown label="Sort">
                                <a class="dropdown-item py-2 {{ ($filters['sort'] ?? 'date_asc') == 'date_asc' ? 'active' : '' }}" href="#" onclick="changeSort('holiday', 'date_asc', this); event.preventDefault();">Date (Ascending)</a>
                                <a class="dropdown-item py-2 {{ ($filters['sort'] ?? '') == 'date_desc' ? 'active' : '' }}" href="#" onclick="changeSort('holiday', 'date_desc', this); event.preventDefault();">Date (Descending)</a>
                                <a class="dropdown-item py-2 {{ ($filters['sort'] ?? '') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('holiday', 'name_asc', this); event.preventDefault();">Name (A-Z)</a>
                                <a class="dropdown-item py-2 {{ ($filters['sort'] ?? '') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('holiday', 'name_desc', this); event.preventDefault();">Name (Z-A)</a>
                            </x-ui.sort-dropdown>

                            <x-ui.filter label="Filter" offset="0, 5" :reset-url="route('hrms.holidays.index')">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                                
                                <div class="mb-3" style="min-width: 260px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.holiday.scope_company') }}</label>
                                    <select name="company_id" id="filter_company_id" class="form-select font-size-13">
                                        <option value="">{{ __('hrms.holiday.all_companies') }}</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}" @selected((string)($filters['company_id'] ?? '') === (string)$company->id)>{{ $company->company_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.holiday.scope_bu') }}</label>
                                    <select name="business_unit_id" id="filter_business_unit_id" class="form-select font-size-13">
                                        <option value="">{{ __('hrms.holiday.all_business_units') }}</option>
                                        @foreach($businessUnits as $bu)
                                            <option value="{{ $bu->id }}" data-company-id="{{ $bu->company_id }}" @selected((string)($filters['business_unit_id'] ?? '') === (string)$bu->id)>{{ $bu->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.holiday.scope_branch') }}</label>
                                    <select name="branch_id" id="filter_branch_id" class="form-select font-size-13">
                                        <option value="">{{ __('hrms.holiday.all_branches') }}</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" data-company-id="{{ $branch->company_id }}" data-business-unit-id="{{ $branch->business_unit_id }}" @selected((string)($filters['branch_id'] ?? '') === (string)$branch->id)>{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.holiday.select_year') }}</label>
                                    <select name="year" class="form-select font-size-13">
                                        <option value="">{{ __('hrms.holiday.all_years') }}</option>
                                        @foreach($availableYears as $year)
                                            <option value="{{ $year }}" @selected((string)($filters['year'] ?? '') === (string)$year)>{{ $year }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.holiday.status') }}</label>
                                    <select name="status" class="form-select font-size-13">
                                        <option value="">All Statuses</option>
                                        <option value="1" @selected((string)($filters['status'] ?? '') === '1')>{{ __('hrms.holiday.active') }}</option>
                                        <option value="0" @selected((string)($filters['status'] ?? '') === '0')>{{ __('hrms.holiday.inactive') }}</option>
                                    </select>
                                </div>
                            </x-ui.filter>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table content -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center" style="table-layout: fixed; width: 100%;">
                    <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="text-start px-4" style="width: 8%;">#</th>
                            <th style="width: 17%;">{{ __('hrms.holiday.holiday_date') }}</th>
                            <th style="width: 25%;">{{ __('hrms.holiday.holiday_name') }}</th>
                            <th style="width: 20%;">{{ __('hrms.holiday.scope') }}</th>
                            <th style="width: 20%;">{{ __('hrms.org.description') }}</th>
                            <th style="width: 10%;">{{ __('hrms.holiday.status') }}</th>
                            <th class="text-end px-4" style="width: 10%;">{{ __('hrms.org.tbl_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="holidaysTableBody">
                        @forelse($holidays as $holiday)
                            <tr>
                                <td class="text-start px-4 font-weight-semibold text-secondary">{{ $loop->iteration + ($holidays->currentPage() - 1) * $holidays->perPage() }}</td>
                                <td>
                                    <div class="fw-bold text-dark fs-13">
                                        <i class="feather-calendar me-1.5 text-primary"></i>
                                        {{ $holiday->holiday_date->format('Y-m-d') }}
                                    </div>
                                </td>
                                <td>
                                    <span class="fs-13 fw-semibold text-dark">{{ $holiday->name }}</span>
                                </td>
                                <td>
                                    @if($holiday->branch_id)
                                        <span class="badge-scope-branch">
                                            <i class="feather-git-commit me-1"></i>
                                            {{ $holiday->branch->name }}
                                        </span>
                                    @elseif($holiday->business_unit_id)
                                        <span class="badge-scope-bu">
                                            <i class="feather-grid me-1"></i>
                                            {{ $holiday->businessUnit->name }}
                                        </span>
                                    @elseif($holiday->company_id)
                                        <span class="badge-scope-company">
                                            <i class="feather-briefcase me-1"></i>
                                            {{ $holiday->company->company_name }}
                                        </span>
                                    @else
                                        <span class="badge-scope-global">
                                            <i class="feather-globe me-1"></i>
                                            Global
                                        </span>
                                    @endif
                                </td>
                                <td class="text-secondary small text-truncate" title="{{ $holiday->description }}">
                                    {{ $holiday->description ?: '-' }}
                                </td>
                                <td>
                                    @if($holiday->status)
                                        <x-ui.badge variant="success" soft>{{ __('hrms.holiday.active') }}</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="danger" soft>{{ __('hrms.holiday.inactive') }}</x-ui.badge>
                                    @endif
                                </td>
                                <td class="pe-4 text-end">
                                    <form action="{{ route('hrms.holidays.destroy', $holiday->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.holiday.confirm_delete') }}', { title: 'Delete Holiday', variant: 'danger', confirmButtonText: 'Delete' });" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.action-dropdown>
                                            <li>
                                                <a class="dropdown-item" href="javascript:void(0)" 
                                                   data-bs-toggle="modal" 
                                                   data-bs-target="#editHolidayModal"
                                                   data-id="{{ $holiday->id }}"
                                                   data-name="{{ $holiday->name }}"
                                                   data-date="{{ $holiday->holiday_date->format('Y-m-d') }}"
                                                   data-description="{{ $holiday->description }}"
                                                   data-company-id="{{ $holiday->company_id }}"
                                                   data-business-unit-id="{{ $holiday->business_unit_id }}"
                                                   data-branch-id="{{ $holiday->branch_id }}"
                                                   data-status="{{ $holiday->status ? '1' : '0' }}">
                                                    <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('hrms.common.edit') ?? 'Edit' }}
                                                </a>
                                            </li>
                                            <li>
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('hrms.common.delete') ?? 'Delete' }}
                                                </button>
                                            </li>
                                        </x-ui.action-dropdown>
                                    </form>
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

            <div id="holidaysPaginationWrapper" class="d-flex justify-content-end mt-3">
                @if($holidays->hasPages())
                    {{ $holidays->links() }}
                @endif
            </div>
        </div>
    </div>

    <!-- ADD HOLIDAY MODAL -->
    <div class="modal fade" id="addHolidayModal" tabindex="-1" aria-labelledby="addHolidayModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 8px;">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="addHolidayModalLabel">
                        <i class="feather-plus me-2 text-primary"></i>{{ __('hrms.holiday.add_holiday') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.holidays.store') }}" method="POST" id="addHolidayForm">
                    @csrf
                    <div class="modal-body">
                        @include('modules.hrms.holiday-calendar.holiday-form-fields', ['mode' => 'create'])
                    </div>
                    <div class="modal-footer bg-light border-top-0">
                        <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal" style="border-radius: 6px;">{{ __('hrms.wfh.close') }}</button>
                        <button type="submit" class="btn btn-primary fw-bold text-uppercase" style="border-radius: 6px;">{{ __('hrms.common.save') ?? 'Save' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT HOLIDAY MODAL -->
    <div class="modal fade" id="editHolidayModal" tabindex="-1" aria-labelledby="editHolidayModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 8px;">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="editHolidayModalLabel">
                        <i class="feather-edit me-2 text-primary"></i>{{ __('hrms.holiday.edit_holiday') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editHolidayForm">
                    @csrf
                    <div class="modal-body">
                        @include('modules.hrms.holiday-calendar.holiday-form-fields', ['mode' => 'edit'])
                    </div>
                    <div class="modal-footer bg-light border-top-0">
                        <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal" style="border-radius: 6px;">{{ __('hrms.wfh.close') }}</button>
                        <button type="submit" class="btn btn-primary fw-bold text-uppercase" style="border-radius: 6px;">{{ __('hrms.common.save') ?? 'Save' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
                        throw new Error('Unable to refresh list.');
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
                    const originalBranches = branchSelect.data('original-options');
                    const currentBranchVal = branchSelect.val();
                    branchSelect.empty();
                    originalBranches.each(function() {
                        const option = $(this);
                        let matchesComp = !initialComp || String(option.data('company-id')) === String(initialComp);
                        let matchesBU = !initialBU || String(option.data('business-unit-id')) === String(initialBU);
                        if (!option.val() || (matchesComp && matchesBU)) {
                            branchSelect.append(option.clone());
                        }
                    });
                    branchSelect.val(currentBranchVal);
                }
            }

            function setupModalCascadingDropdowns(prefix) {
                const companySelect = $('#' + prefix + '_company_id');
                const buSelect = $('#' + (prefix === 'add_holiday' ? 'add_holiday_business_unit_id' : 'edit_holiday_bu_id'));
                const branchSelect = $('#' + prefix + '_branch_id');

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
                    buSelect.trigger('change.select2');
                    
                    filterBranches();
                });

                buSelect.on('change', function() {
                    const buVal = $(this).val();
                    const selectedOption = buSelect.find(`option[value="${buVal}"]`);
                    const companyId = selectedOption.data('company-id');
                    
                    if (buVal && companyId && String(companySelect.val()) !== String(companyId)) {
                        companySelect.val(companyId).trigger('change.select2');
                    }
                    
                    filterBranches();
                });

                branchSelect.on('change', function() {
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
                    branchSelect.trigger('change.select2');
                }
            }

            $(document).ready(function() {
                // Append modals to body to fix backdrop/z-index issues
                $('#addHolidayModal').appendTo('body');
                $('#editHolidayModal').appendTo('body');

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

                    // Set up cascading select options inside active modals
                    if (modal.attr('id') === 'addHolidayModal') {
                        setupModalCascadingDropdowns('add_holiday');
                    } else if (modal.attr('id') === 'editHolidayModal') {
                        setupModalCascadingDropdowns('edit_holiday');
                    }
                });

                // Populate Edit Modal
                $('#editHolidayModal').on('show.bs.modal', function(event) {
                    var button = $(event.relatedTarget);
                    var holidayId = button.data('id');
                    var name = button.data('name');
                    var date = button.data('date');
                    var description = button.data('description');
                    var companyId = button.data('company-id');
                    var buId = button.data('business-unit-id');
                    var branchId = button.data('branch-id');
                    var status = button.data('status');

                    var modal = $(this);
                    modal.find('form').attr('action', '/hrms/holidays/update/' + holidayId);
                    
                    modal.find('#edit_holiday_name').val(name);
                    modal.find('#edit_holiday_holiday_date').val(date);
                    modal.find('#edit_holiday_description').val(description);
                    
                    modal.find('#edit_holiday_company_id').val(companyId).trigger('change');
                    
                    // We must wait for cascading bu Select to load before populating the selected BU
                    setTimeout(function() {
                        modal.find('#edit_holiday_bu_id').val(buId).trigger('change');
                        
                        // We must wait for cascading branch select to load before populating branch
                        setTimeout(function() {
                            modal.find('#edit_holiday_branch_id').val(branchId).trigger('change');
                        }, 100);
                    }, 100);

                    modal.find('#edit_holiday_status').val(status).trigger('change');
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
