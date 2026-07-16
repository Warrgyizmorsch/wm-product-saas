@extends('layouts.duralux')

@section('title', __('hrms.employees.title') . ' | SaaS ERP')
@section('page-title', __('hrms.employees.title'))
@section('breadcrumb', 'HRMS / Employees')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="outline-primary" icon="feather-upload" data-bs-toggle="modal" data-bs-target="#importEmployeeModal" class="fw-bold text-uppercase">
            {{ __('hrms.employees.import') }}
        </x-ui.button>
        <x-ui.button variant="outline-primary" icon="feather-download" href="{{ route('hrms.employees.export') }}" class="fw-bold text-uppercase">
            {{ __('hrms.employees.export') }}
        </x-ui.button>
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addEmployeeModal" class="fw-bold text-uppercase">
            {{ __('hrms.employees.create_employee') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .btn-outline-primary {
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
            background-color: transparent !important;
        }
        .btn-outline-primary:hover,
        .btn-outline-primary:focus,
        .btn-outline-primary:active,
        .btn-outline-primary.active,
        .btn-outline-primary.show {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #fff !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@php
    $activeEmployeesCount = $employees->getCollection()->where('status', true)->count();
    $inactiveEmployeesCount = $employees->getCollection()->where('status', false)->count();
    $businessUnitsJson = $businessUnits->map(function ($unit) {
        return [
            'id' => $unit->id,
            'company_id' => $unit->company_id,
            'name' => $unit->name,
        ];
    })->values();
    $branchesJson = $branches->map(function ($branch) {
        return [
            'id' => $branch->id,
            'company_id' => $branch->company_id,
            'business_unit_id' => $branch->business_unit_id,
            'name' => $branch->name,
        ];
    })->values();
    $departmentsJson = $departments->map(function ($department) {
        return [
            'id' => $department->id,
            'company_id' => $department->company_id,
            'business_unit_id' => $department->business_unit_id,
            'branch_id' => $department->branch_id,
            'name' => $department->name,
        ];
    })->values();
    $designationsJson = $designations->map(function ($designation) {
        return [
            'id' => $designation->id,
            'department_id' => $designation->department_id,
            'name' => $designation->name,
        ];
    })->values();
    $payGroupsJson = $payGroups->map(function ($payGroup) {
        return [
            'id' => $payGroup->id,
            'company_id' => $payGroup->company_id,
            'name' => $payGroup->name,
        ];
    })->values();
    $salaryStructuresJson = $salaryStructures->map(function ($salaryStructure) {
        return [
            'id' => $salaryStructure->id,
            'company_id' => $salaryStructure->company_id,
            'pay_group_id' => $salaryStructure->pay_group_id,
            'name' => $salaryStructure->name,
        ];
    })->values();
    $leavePlansJson = $leavePlans->map(function ($leavePlan) {
        return [
            'id' => $leavePlan->id,
            'company_id' => $leavePlan->company_id,
            'name' => $leavePlan->name,
        ];
    })->values();
    $attendancePenaltiesJson = $attendancePenalties->map(function ($penalty) {
        return [
            'id' => $penalty->id,
            'company_id' => $penalty->company_id,
            'name' => ucwords(str_replace('_', ' ', $penalty->rule_type)),
        ];
    })->values();
@endphp

@section('content')
    <style>
        .btn-outline-primary {
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
            transition: all 0.2s ease-in-out;
        }
        .btn-outline-primary:hover,
        .btn-outline-primary:focus,
        .btn-outline-primary:active {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #ffffff !important;
        }

        /* Import uploader custom styling */
        .erp-custom-file-upload {
            display: block;
            width: 100%;
        }
        .erp-custom-file-upload .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px dashed #ced4da;
            border-radius: 12px;
            padding: 24px 16px;
            background-color: #f8fafc;
            color: #475569;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            width: 100%;
        }
        .erp-custom-file-upload .file-upload-label:hover {
            background-color: #f1f5f9;
            border-color: var(--bs-primary);
            color: var(--bs-primary);
        }

        .employee-page {
            padding: 24px;
            background-color: #f8fafc;
            min-height: calc(100vh - 120px);
        }

        @media (max-width: 991.98px) {
            .employee-page {
                padding: 16px;
            }
        }

        .employee-summary-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            padding: 20px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
            height: 100%;
        }

        .employee-summary-label {
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .employee-summary-value {
            color: #0f172a;
            font-size: 30px;
            font-weight: 800;
            line-height: 1;
        }

        .employee-avatar {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.16), rgba(13, 110, 253, 0.04));
            color: var(--bs-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            overflow: hidden;
        }

        .employee-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .employee-filter-card,
        .employee-list-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background-color: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
        }

        .employee-toolbar {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .employee-metric-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background-color: #f8fafc;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
        }

        .employee-empty-state {
            padding: 56px 24px;
            text-align: center;
            color: #64748b;
        }

        .employee-modal-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 16px 0 12px;
            color: #475569;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .employee-modal-section-title::after {
            content: "";
            flex: 1;
            height: 1px;
            background-color: #e2e8f0;
        }

        .employee-photo-panel {
            border: 1px dashed #cbd5e1;
            border-radius: 16px;
            background-color: #f8fafc;
            padding: 18px;
            text-align: center;
            height: 100%;
        }

        .employee-photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 18px;
            overflow: hidden;
            margin: 0 auto 12px;
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.18), rgba(13, 110, 253, 0.05));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--bs-primary);
            font-size: 34px;
            font-weight: 800;
        }

        .employee-photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        #addEmployeeModal .modal-content,
        #editEmployeeModal .modal-content {
            max-height: 90vh;
        }

        #create_blood_group_wrapper .odoo-form-label,
        #edit_blood_group_wrapper .odoo-form-label {
            width: 100px !important;
        }

        #addEmployeeModal .modal-body,
        #editEmployeeModal .modal-body {
            overflow-y: auto;
            max-height: calc(90vh - 140px);
        }

        .employee-search-form {
            min-width: 260px;
            max-width: 320px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }

        .employee-search-form:focus-within {
            background-color: #fff !important;
            border-color: var(--bs-primary) !important;
            box-shadow: 0 0 0 0.18rem rgba(0, 0, 0, 0.05);
        }

        .employee-filter-apply-btn {
            border-radius: 6px;
            font-size: 11px;
            letter-spacing: 0.05em;
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
            color: #fff;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease-in-out;
        }

        .employee-filter-apply-btn:hover,
        .employee-filter-apply-btn:focus {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            filter: brightness(0.9) !important;
            color: #fff !important;
        }

        .employee-list-card.is-loading {
            opacity: 0.68;
            pointer-events: none;
        }
    </style>

    <div class="employee-page">
            @if(session('success'))
                <x-ui.alert variant="success" icon="feather-check-circle" dismissible>
                    {{ session('success') }}
                </x-ui.alert>
            @endif

            <div class="employee-list-card p-4" id="employeeListCard">
                <!-- Card Header -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h4 class="fw-bold text-dark mb-0 fs-16">{{ __('hrms.employees.database_title') }}</h4>
                    </div>
                    
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <!-- Search Bar (EXACT THEME STYLE) -->
                        <form method="GET" action="{{ route('hrms.employees.index') }}" id="employeeSearchForm" class="employee-search-form d-flex align-items-center bg-light border rounded px-3 py-1">
                            <input type="hidden" name="company_id" value="{{ $filters['company_id'] }}">
                            <input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">
                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                            
                            <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                            <input 
                                type="text" 
                                name="search" 
                                class="form-control border-0 bg-transparent p-0 fs-13" 
                                placeholder="{{ __('hrms.employees.search_employees') }}" 
                                value="{{ $filters['search'] }}"
                                style="box-shadow: none; height: 32px;"
                            >
                        </form>
 
                        <!-- SORT Button -->
                        <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                            <a class="dropdown-item employee-sort-link d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'name_asc']) }}" data-sort="name_asc">
                                <span>{{ __('hrms.common.sort_name_asc') }}</span>
                                @if($filters['sort'] === 'name_asc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item employee-sort-link d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'name_desc']) }}" data-sort="name_desc">
                                <span>{{ __('hrms.common.sort_name_desc') }}</span>
                                @if($filters['sort'] === 'name_desc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item employee-sort-link d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'id_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'id_asc']) }}" data-sort="id_asc">
                                <span>{{ __('hrms.employees.sort_id_asc') }}</span>
                                @if($filters['sort'] === 'id_asc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item employee-sort-link d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'id_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'id_desc']) }}" data-sort="id_desc">
                                <span>{{ __('hrms.employees.sort_id_desc') }}</span>
                                @if($filters['sort'] === 'id_desc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item employee-sort-link d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'doj_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'doj_desc']) }}" data-sort="doj_desc">
                                <span>{{ __('hrms.employees.sort_doj_desc') }}</span>
                                @if($filters['sort'] === 'doj_desc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item employee-sort-link d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'doj_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'doj_asc']) }}" data-sort="doj_asc">
                                <span>{{ __('hrms.employees.sort_doj_asc') }}</span>
                                @if($filters['sort'] === 'doj_asc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item employee-sort-link d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'salary_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'salary_desc']) }}" data-sort="salary_desc">
                                <span>{{ __('hrms.employees.sort_salary_desc') }}</span>
                                @if($filters['sort'] === 'salary_desc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item employee-sort-link d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'salary_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'salary_asc']) }}" data-sort="salary_asc">
                                <span>{{ __('hrms.employees.sort_salary_asc') }}</span>
                                @if($filters['sort'] === 'salary_asc') <i class="feather-check ms-3"></i> @endif
                            </a>
                        </x-ui.sort-dropdown>
 
                        <!-- FILTER Button -->
                        <x-ui.filter label="{{ __('hrms.common.filter') }}">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                            <form method="GET" action="{{ route('hrms.employees.index') }}" id="employeeFilterForm">
                                <input type="hidden" name="search" value="{{ $filters['search'] }}">
                                <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.employees.tbl_company') }}</label>
                                    <select name="company_id" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                        <option value="">{{ __('hrms.common.all_companies') }}</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}" @selected((string) $filters['company_id'] === (string) $company->id)>
                                                {{ $company->company_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.employees.tbl_department') }}</label>
                                    <select name="department_id" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                        <option value="">{{ __('hrms.common.all_departments') }}</option>
                                        @foreach($departments as $department)
                                            <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>
                                                {{ $department->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.employees.tbl_status') }}</label>
                                    <select name="status" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                        <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                        <option value="1" @selected($filters['status'] === '1')>{{ __('hrms.employees.tbl_status') }} - Active</option>
                                        <option value="0" @selected($filters['status'] === '0')>{{ __('hrms.employees.tbl_status') }} - Inactive</option>
                                    </select>
                                </div>
                                <div class="d-flex gap-2 justify-content-end mt-4">
                                    <a href="{{ route('hrms.employees.index') }}" class="btn btn-sm btn-light employee-filter-reset text-uppercase fw-bold py-2 px-3" style="border-radius: 6px; font-size: 11px; letter-spacing: 0.05em; background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #475569;">{{ __('hrms.common.reset') }}</a>
                                    <button type="submit" class="btn btn-sm employee-filter-apply-btn text-uppercase fw-bold py-2 px-3">{{ __('hrms.common.apply') }}</button>
                                </div>
                            </form>
                        </x-ui.filter>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="70">#</th>
                                <th>{{ __('hrms.employees.tbl_employee') }}</th>
                                <th>{{ __('hrms.employees.tbl_code') }}</th>
                                <th>{{ __('hrms.employees.tbl_department') }}</th>
                                <th>{{ __('hrms.employees.tbl_designation') }}</th>
                                <th>{{ __('hrms.employees.tbl_company') }}</th>
                                <th>{{ __('hrms.employees.tbl_status') }}</th>
                                <th width="150" class="text-end">{{ __('hrms.employees.tbl_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="employeeTableBody">
                            @forelse($employees as $employee)
                                <tr>
                                    <td>{{ $employees->firstItem() + $loop->index }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="employee-avatar">
                                                @if($employee->photo)
                                                    <img src="{{ asset('storage/' . $employee->photo) }}" alt="{{ $employee->display_name }}">
                                                @else
                                                    {{ strtoupper(substr($employee->first_name, 0, 1) . substr($employee->last_name, 0, 1)) ?: strtoupper(substr($employee->display_name, 0, 2)) }}
                                                @endif
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark">{{ $employee->display_name }}</div>
                                                <div class="text-muted fs-12">{{ $employee->personal_email ?: 'No personal email' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code>{{ $employee->employee_id }}</code></td>
                                    <td>{{ $employee->department?->name ?? 'Not assigned' }}</td>
                                    <td>{{ $employee->designation?->name ?? 'Not assigned' }}</td>
                                    <td>{{ $employee->company?->company_name ?? 'Not assigned' }}</td>
                                    <td>
                                        @if($employee->status)
                                            <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                        @else
                                            <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <x-ui.action-dropdown>
                                            <li>
                                                <a class="dropdown-item" href="{{ route('hrms.employees.show', $employee->id) }}">
                                                    <i class="feather feather-eye me-3"></i>
                                                    <span>{{ __('hrms.employees.view_profile') }}</span>
                                                </a>
                                            </li>
                                            <li>
                                                <a
                                                    class="dropdown-item employee-edit-trigger"
                                                    href="javascript:void(0)"
                                                    data-employee="{{ base64_encode($employee->toJson()) }}"
                                                >
                                                    <i class="feather feather-edit-3 me-3"></i>
                                                    <span>{{ __('hrms.assets.edit') }}</span>
                                                </a>
                                            </li>
                                            <li>
                                                <form action="{{ route('hrms.employees.destroy', $employee->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this employee?');" style="display: inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start">
                                                        <i class="feather feather-trash-2 me-3"></i>
                                                        <span>{{ __('hrms.assets.delete') }}</span>
                                                    </button>
                                                </form>
                                            </li>
                                        </x-ui.action-dropdown>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="employee-empty-state">
                                        <i class="feather-users fs-32 d-block mb-3 text-secondary"></i>
                                        <div class="fw-semibold text-dark mb-1">No employees found.</div>
                                        <div>Create your first employee record or broaden the filters.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @php
                    $currentPage = $employees->currentPage();
                    $totalPages = $employees->lastPage();
                    $totalResults = $employees->total();
                    $perPage = $employees->perPage();
                @endphp
                <div id="employeePaginationWrapper">
                    <x-ui.pagination 
                        class="px-4 py-3 border-top"
                        :current-page="$currentPage"
                        :total-pages="$totalPages"
                        :total-results="$totalResults"
                        :per-page="$perPage"
                    />
                </div>
            </div>
    </div>

    <!-- IMPORT EMPLOYEE MODAL -->
    <div class="modal fade" id="importEmployeeModal" tabindex="-1" aria-labelledby="importEmployeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="importEmployeeModalLabel">
                        <i class="feather-upload me-2 text-primary" style="font-size: 16px;"></i>{{ __('hrms.employees.mdl_import_title') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.employees.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body text-start">
                        <div class="alert bg-light border-0 d-flex flex-column gap-2 p-3 mb-4 rounded-3 text-dark fs-12">
                            <div class="d-flex align-items-center gap-2">
                                <i class="feather-info text-primary fs-15"></i>
                                <span class="fw-bold">{{ __('hrms.employees.mdl_import_instructions') }}</span>
                            </div>
                            <span class="text-muted leading-relaxed">
                                {{ __('hrms.employees.mdl_import_instruction_text') }}
                            </span>
                            <div class="mt-1">
                                <a href="{{ route('hrms.employees.import.template') }}" class="btn btn-xs btn-soft-primary d-inline-flex align-items-center fw-bold py-1.5 px-3" style="border-radius: 6px; font-size: 11px;">
                                    <i class="feather-download me-1.5 fs-12"></i> {{ __('hrms.employees.mdl_download_xlsx') }}
                                </a>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="erp-custom-file-upload">
                                <label class="file-upload-label py-3 px-4 w-100" style="cursor: pointer; border-style: dashed; border-width: 2px;" for="employee_import_file">
                                    <i class="feather-upload-cloud me-2 text-primary fs-20"></i>
                                    <span class="file-text text-muted" id="import_file_text">{{ __('hrms.employees.mdl_select_xlsx') }}</span>
                                    <input type="file" name="file" id="employee_import_file" class="d-none" required accept=".xlsx" onchange="document.getElementById('import_file_text').innerText = this.files[0]?.name || '{{ __('hrms.employees.mdl_select_xlsx') }}'">
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_import') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_discard') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addEmployeeModalLabel">
                        <i class="feather-user-plus me-2 text-primary"></i>{{ __('hrms.employees.create_employee') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.employees.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="form_mode" value="create">
                    <div class="modal-body p-4">
                        @include('modules.hrms.employees.partials.form-fields', ['mode' => 'create'])
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('hrms.employees.mdl_btn_save_employee') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="editEmployeeModalLabel">
                        <i class="feather-edit-3 me-2 text-primary"></i>{{ __('hrms.employees.lbl_edit_employee') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form
                    id="editEmployeeForm"
                    action="{{ old('editing_employee_id') ? route('hrms.employees.update', ['employee' => old('editing_employee_id')]) : '#' }}"
                    method="POST"
                    enctype="multipart/form-data"
                    data-action-template="{{ route('hrms.employees.update', ['employee' => '__ID__']) }}"
                >
                    @csrf
                    <input type="hidden" name="form_mode" value="edit">
                    <input type="hidden" name="editing_employee_id" id="editing_employee_id" value="{{ old('editing_employee_id') }}">
                    <div class="modal-body p-4">
                        @include('modules.hrms.employees.partials.form-fields', ['mode' => 'edit'])
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('hrms.employees.mdl_btn_update_employee') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const businessUnits = @json($businessUnitsJson);
            const branches = @json($branchesJson);
            const departments = @json($departmentsJson);
            const designations = @json($designationsJson);
            const payGroups = @json($payGroupsJson);
            const salaryStructures = @json($salaryStructuresJson);
            const leavePlans = @json($leavePlansJson);
            const attendancePenalties = @json($attendancePenaltiesJson);
            const addEmployeeModal = document.getElementById('addEmployeeModal');
            const editEmployeeModal = document.getElementById('editEmployeeModal');
            const importEmployeeModal = document.getElementById('importEmployeeModal');

            let syncFilterDepartments;

            [addEmployeeModal, editEmployeeModal, importEmployeeModal].forEach(function (modal) {
                if (modal && modal.parentElement !== document.body) {
                    document.body.appendChild(modal);
                }
            });

            function initInlineSelects(container, dropdownParent = null) {
                $(container).find('select').each(function () {
                    const $select = $(this);

                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }

                    const config = {
                        theme: 'bootstrap-5',
                        width: '100%',
                    };

                    if (dropdownParent) {
                        config.dropdownParent = $(dropdownParent);
                    }

                    $select.select2(config);
                });
            }

            function initModalSelects(modal) {
                $(modal).find('select').each(function () {
                    const $select = $(this);

                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }

                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        dropdownParent: $(modal),
                    });
                });
            }

            function buildOptions(select, items, selectedValue, placeholder) {
                const currentValue = selectedValue == null ? '' : String(selectedValue);
                let options = `<option value="">${placeholder}</option>`;

                items.forEach(function (item) {
                    const selected = String(item.id) === currentValue ? 'selected' : '';
                    options += `<option value="${item.id}" ${selected}>${item.name}</option>`;
                });

                select.innerHTML = options;
            }

            function toggleWrapper(id, visible) {
                const wrapper = document.getElementById(id);
                if (wrapper) {
                    wrapper.style.display = visible ? '' : 'none';
                }
            }

            function preferredValue(preferred, currentValue, storedValue) {
                if (preferred !== undefined && preferred !== null) {
                    return preferred;
                }

                if (currentValue !== undefined && currentValue !== null && currentValue !== '') {
                    return currentValue;
                }

                return storedValue || '';
            }

            function syncHierarchy(prefix, selectedValues = {}) {
                const companySelect = document.getElementById(prefix + '_company_id');
                const businessUnitSelect = document.getElementById(prefix + '_business_unit_id');
                const branchSelect = document.getElementById(prefix + '_branch_id');
                const departmentSelect = document.getElementById(prefix + '_department_id');
                const designationSelect = document.getElementById(prefix + '_designation_id');
                const payGroupSelect = document.getElementById(prefix + '_pay_group_id');
                const leavePlanSelect = document.getElementById(prefix + '_leave_plan_id');

                if (!companySelect || !businessUnitSelect || !branchSelect || !departmentSelect || !designationSelect) {
                    return;
                }

                const companyId = companySelect.value;
                const businessUnitId = preferredValue(selectedValues.businessUnitId, businessUnitSelect.value, businessUnitSelect.dataset.selectedValue);
                const branchId = preferredValue(selectedValues.branchId, branchSelect.value, branchSelect.dataset.selectedValue);
                const departmentId = preferredValue(selectedValues.departmentId, departmentSelect.value, departmentSelect.dataset.selectedValue);
                const designationId = preferredValue(selectedValues.designationId, designationSelect.value, designationSelect.dataset.selectedValue);
                const payGroupId = preferredValue(selectedValues.payGroupId, payGroupSelect ? payGroupSelect.value : '', payGroupSelect ? payGroupSelect.dataset.selectedValue : '');
                const leavePlanId = preferredValue(selectedValues.leavePlanId, leavePlanSelect ? leavePlanSelect.value : '', leavePlanSelect ? leavePlanSelect.dataset.selectedValue : '');

                const availableBusinessUnits = businessUnits.filter(item => !companyId || String(item.company_id) === String(companyId));
                buildOptions(businessUnitSelect, availableBusinessUnits, businessUnitId, 'Select Business Unit');
                toggleWrapper(prefix + '_business_unit_wrapper', availableBusinessUnits.length > 0);
                if (availableBusinessUnits.length === 0) {
                    businessUnitSelect.value = '';
                }
                businessUnitSelect.dataset.selectedValue = businessUnitSelect.value;

                const resolvedBusinessUnitId = businessUnitSelect.value;
                const availableBranches = branches.filter(item => {
                    if (companyId && String(item.company_id) !== String(companyId)) {
                        return false;
                    }

                    if (resolvedBusinessUnitId) {
                        return String(item.business_unit_id || '') === String(resolvedBusinessUnitId);
                    }

                    return true;
                });
                buildOptions(branchSelect, availableBranches, branchId, 'Select Branch');
                toggleWrapper(prefix + '_branch_wrapper', availableBranches.length > 0);
                if (availableBranches.length === 0) {
                    branchSelect.value = '';
                }
                branchSelect.dataset.selectedValue = branchSelect.value;

                const resolvedBranchId = branchSelect.value;
                const availableDepartments = departments.filter(item => {
                    if (companyId && String(item.company_id) !== String(companyId)) {
                        return false;
                    }

                    if (resolvedBusinessUnitId && String(item.business_unit_id || '') !== String(resolvedBusinessUnitId)) {
                        return false;
                    }

                    if (resolvedBranchId && String(item.branch_id || '') !== String(resolvedBranchId)) {
                        return false;
                    }

                    return true;
                });
                buildOptions(departmentSelect, availableDepartments, departmentId, 'Select Department');
                departmentSelect.dataset.selectedValue = departmentSelect.value;

                const resolvedDepartmentId = departmentSelect.value;
                const availableDesignations = designations.filter(item => {
                    return !resolvedDepartmentId || String(item.department_id) === String(resolvedDepartmentId);
                });
                buildOptions(designationSelect, availableDesignations, designationId, 'Select Designation');
                designationSelect.dataset.selectedValue = designationSelect.value;

                if (payGroupSelect) {
                    const availablePayGroups = payGroups.filter(item => !item.company_id || (companyId && String(item.company_id) === String(companyId)));
                    buildOptions(payGroupSelect, availablePayGroups, payGroupId, 'Select Pay Group');
                    toggleWrapper(prefix + '_pay_group_wrapper', true); // Keep visible
                    payGroupSelect.dataset.selectedValue = payGroupSelect.value;
                }
 
                if (leavePlanSelect) {
                    const availableLeavePlans = leavePlans.filter(item => !item.company_id || (companyId && String(item.company_id) === String(companyId)));
                    buildOptions(leavePlanSelect, availableLeavePlans, leavePlanId, 'Select Leave Structure');
                    toggleWrapper(prefix + '_leave_plan_wrapper', true); // Keep visible
                    leavePlanSelect.dataset.selectedValue = leavePlanSelect.value;
                }

                $(businessUnitSelect).trigger('change.select2');
                $(branchSelect).trigger('change.select2');
                $(departmentSelect).trigger('change.select2');
                $(designationSelect).trigger('change.select2');
                if (payGroupSelect) {
                    $(payGroupSelect).trigger('change.select2');
                }
                if (leavePlanSelect) {
                    $(leavePlanSelect).trigger('change.select2');
                }
            }

            function attachHierarchyListeners(prefix) {
                const companySelect = document.getElementById(prefix + '_company_id');
                const businessUnitSelect = document.getElementById(prefix + '_business_unit_id');
                const branchSelect = document.getElementById(prefix + '_branch_id');
                const departmentSelect = document.getElementById(prefix + '_department_id');
                const payGroupSelect = document.getElementById(prefix + '_pay_group_id');

                if (companySelect) {
                    $(companySelect).on('change', function () {
                        syncHierarchy(prefix, {});
                    });
                }

                if (businessUnitSelect) {
                    $(businessUnitSelect).on('change', function () {
                        syncHierarchy(prefix, { businessUnitId: businessUnitSelect.value });
                    });
                }

                if (branchSelect) {
                    $(branchSelect).on('change', function () {
                        syncHierarchy(prefix, {
                            businessUnitId: businessUnitSelect ? businessUnitSelect.value : '',
                            branchId: branchSelect.value
                        });
                    });
                }

                if (departmentSelect) {
                    $(departmentSelect).on('change', function () {
                        syncHierarchy(prefix, {
                            businessUnitId: businessUnitSelect ? businessUnitSelect.value : '',
                            branchId: branchSelect ? branchSelect.value : '',
                            departmentId: departmentSelect.value
                        });
                    });
                }

                if (payGroupSelect) {
                    $(payGroupSelect).on('change', function () {
                        syncHierarchy(prefix, {
                            businessUnitId: businessUnitSelect ? businessUnitSelect.value : '',
                            branchId: branchSelect ? branchSelect.value : '',
                            departmentId: departmentSelect ? departmentSelect.value : '',
                            payGroupId: payGroupSelect.value
                        });
                    });
                }
            }

            function setPreview(prefix, photoPath, displayName) {
                const preview = document.getElementById(prefix + '_photo_preview');
                if (!preview) {
                    return;
                }

                const initials = ((displayName || '').split(' ').map(part => part.charAt(0)).join('').substring(0, 2) || 'EM').toUpperCase();

                if (photoPath) {
                    preview.innerHTML = `<img src="/storage/${photoPath}" alt="${displayName || 'Employee'}">`;
                } else {
                    preview.textContent = initials;
                }
            }

            function openEditModal(employee) {
                const form = document.getElementById('editEmployeeForm');
                const editingEmployeeInput = document.getElementById('editing_employee_id');

                if (!form || !editingEmployeeInput) {
                    return;
                }

                form.action = form.dataset.actionTemplate.replace('__ID__', employee.id);
                editingEmployeeInput.value = employee.id;

                const assignments = {
                    edit_employee_id: employee.employee_id || '',
                    edit_full_name: employee.full_name || '',
                    edit_nick_name: employee.nick_name || '',
                    edit_job_title: employee.job_title || '',
                    edit_role: employee.role || '',
                    edit_date_of_joining: employee.date_of_joining ? employee.date_of_joining.substring(0, 10) : '',
                    edit_date_of_birth: employee.date_of_birth ? employee.date_of_birth.substring(0, 10) : '',
                    edit_probation_end_date: employee.probation_end_date ? employee.probation_end_date.substring(0, 10) : '',
                    edit_confirmation_date: employee.confirmation_date ? employee.confirmation_date.substring(0, 10) : '',
                    edit_office: employee.office || '',
                    edit_personal_mobile_number: employee.personal_mobile_number || '',
                    edit_personal_email: employee.personal_email || '',
                    edit_office_email: employee.office_email || '',
                    edit_home_phone: employee.home_phone || '',
                    edit_aadhaar_card_number: employee.aadhaar_card_number || '',
                    edit_pan_card_number: employee.pan_card_number || '',
                    edit_city: employee.city || '',
                    edit_postal_code: employee.postal_code || '',
                    edit_qualification: employee.qualification || '',
                    edit_source_of_hire: employee.source_of_hire || '',
                    edit_experience: employee.experience || '0',
                    edit_current_salary: employee.current_salary || '0',
                    edit_present_address: employee.present_address || '',
                    edit_permanent_address: employee.permanent_address || '',
                    edit_skill_set: employee.skill_set || '',
                    edit_bank_name: employee.bank_name || '',
                    edit_account_number: employee.account_number || '',
                    edit_ifsc_code: employee.ifsc_code || '',
                    edit_emergency_contact_name: employee.emergency_contact_name || '',
                    edit_emergency_contact_number: employee.emergency_contact_number || '',
                    edit_emergency_contact_relation: employee.emergency_contact_relation || '',
                };

                Object.keys(assignments).forEach(function (id) {
                    const element = document.getElementById(id);
                    if (element) {
                        element.value = assignments[id];
                    }
                });

                // Sync address checkbox on edit modal load
                const editCheckbox = document.getElementById('edit_same_as_present');
                const editPresent = document.getElementById('edit_present_address');
                const editPermanent = document.getElementById('edit_permanent_address');
                if (editCheckbox && editPresent && editPermanent) {
                    if (editPresent.value && editPresent.value === editPermanent.value) {
                        editCheckbox.checked = true;
                        editPermanent.readOnly = true;
                    } else {
                        editCheckbox.checked = false;
                        editPermanent.readOnly = false;
                    }
                }

                const selectAssignments = {
                    edit_company_id: employee.company_id || '',
                    edit_reporting_manager_id: employee.reporting_manager_id || '',
                    edit_shift_id: employee.shift_id || '',
                    edit_employee_stage: employee.employee_stage || '',
                    edit_employment_type: employee.employment_type || '',
                    edit_gender: employee.gender || '',
                    edit_marital_status: employee.marital_status || '',
                    edit_blood_group: employee.blood_group || '',
                    edit_diet_preference: employee.diet_preference || '',
                    edit_status: employee.status ? '1' : '0',
                    edit_pay_group_id: employee.pay_group_id || '',
                    edit_leave_plan_id: employee.leave_plan_id || '',
                };

                Object.keys(selectAssignments).forEach(function (id) {
                    const element = document.getElementById(id);
                    if (element) {
                        element.value = selectAssignments[id];
                    }
                });

                syncHierarchy('edit', {
                    businessUnitId: employee.business_unit_id || '',
                    branchId: employee.branch_id || '',
                    departmentId: employee.department_id || '',
                    designationId: employee.designation_id || '',
                    payGroupId: employee.pay_group_id || '',
                    leavePlanId: employee.leave_plan_id || '',
                });

                setPreview('edit', employee.photo, employee.full_name);

                initModalSelects(document.getElementById('editEmployeeModal'));
                bootstrap.Modal.getOrCreateInstance(document.getElementById('editEmployeeModal')).show();
            }

            attachHierarchyListeners('create');
            attachHierarchyListeners('edit');
            syncHierarchy('create', {
                businessUnitId: document.getElementById('create_business_unit_id')?.value || '',
                branchId: document.getElementById('create_branch_id')?.value || '',
                departmentId: document.getElementById('create_department_id')?.value || '',
                designationId: document.getElementById('create_designation_id')?.value || '',
                payGroupId: document.getElementById('create_pay_group_id')?.value || '',
                leavePlanId: document.getElementById('create_leave_plan_id')?.value || '',
            });
            syncHierarchy('edit', {
                businessUnitId: document.getElementById('edit_business_unit_id')?.value || '',
                branchId: document.getElementById('edit_branch_id')?.value || '',
                departmentId: document.getElementById('edit_department_id')?.value || '',
                designationId: document.getElementById('edit_designation_id')?.value || '',
                payGroupId: document.getElementById('edit_pay_group_id')?.value || '',
                leavePlanId: document.getElementById('edit_leave_plan_id')?.value || '',
            });

            $(document).on('click', '.employee-edit-trigger', function () {
                const payload = this.getAttribute('data-employee');
                if (!payload) {
                    return;
                }

                openEditModal(JSON.parse(atob(payload)));
            });

            ['create', 'edit'].forEach(function (prefix) {
                const input = document.getElementById(prefix + '_photo');
                if (!input) {
                    return;
                }

                input.addEventListener('change', function (event) {
                    const file = event.target.files && event.target.files[0];
                    if (!file) {
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function (loadEvent) {
                        const preview = document.getElementById(prefix + '_photo_preview');
                        if (preview) {
                            preview.innerHTML = `<img src="${loadEvent.target.result}" alt="Preview">`;
                        }
                    };
                    reader.readAsDataURL(file);
                });
            });

            if (addEmployeeModal) {
                addEmployeeModal.addEventListener('shown.bs.modal', function () {
                    initModalSelects(this);
                });
            }

            if (editEmployeeModal) {
                editEmployeeModal.addEventListener('shown.bs.modal', function () {
                    initModalSelects(this);
                });
            }

            function setupSameAddress(prefix) {
                const checkbox = document.getElementById(prefix + '_same_as_present');
                const present = document.getElementById(prefix + '_present_address');
                const permanent = document.getElementById(prefix + '_permanent_address');
                if (!checkbox || !present || !permanent) return;

                present.addEventListener('input', function () {
                    if (checkbox.checked) {
                        permanent.value = this.value;
                    }
                });

                checkbox.addEventListener('change', function () {
                    if (this.checked) {
                        permanent.value = present.value;
                        permanent.readOnly = true;
                    } else {
                        permanent.readOnly = false;
                    }
                });
            }

            setupSameAddress('create');
            setupSameAddress('edit');

            // Initial address checkbox sync on DOM load (for validation redirects)
            ['create', 'edit'].forEach(function(prefix) {
                const checkbox = document.getElementById(prefix + '_same_as_present');
                const present = document.getElementById(prefix + '_present_address');
                const permanent = document.getElementById(prefix + '_permanent_address');
                if (checkbox && present && permanent && present.value && present.value === permanent.value) {
                    checkbox.checked = true;
                    permanent.readOnly = true;
                }
            });

            // Filter form company-department sync
            const filterForm = document.getElementById('employeeFilterForm');
            if (filterForm) {
                const filterCompanySelect = filterForm.querySelector('select[name="company_id"]');
                const filterDeptSelect = filterForm.querySelector('select[name="department_id"]');

                if (filterCompanySelect && filterDeptSelect) {
                    const originalSelectedDept = @json($filters['department_id']) || '';

                    syncFilterDepartments = function() {
                        const companyId = filterCompanySelect.value;
                        const availableDepts = departments.filter(function (dept) {
                            return !companyId || String(dept.company_id) === String(companyId);
                        });

                        const currentVal = filterDeptSelect.value || originalSelectedDept;
                        let options = '<option value="">All Departments</option>';
                        availableDepts.forEach(function (dept) {
                            const isSelected = String(dept.id) === String(currentVal);
                            options += `<option value="${dept.id}" ${isSelected ? 'selected' : ''}>${dept.name}</option>`;
                        });

                        filterDeptSelect.innerHTML = options;

                        // If selected department is no longer in the list, reset selected value to empty
                        const hasSelected = availableDepts.some(function (dept) {
                            return String(dept.id) === String(filterDeptSelect.value);
                        });
                        if (!hasSelected && filterDeptSelect.value !== '') {
                            filterDeptSelect.value = '';
                        }

                        // Re-initialize Select2 on the department select
                        if ($(filterDeptSelect).hasClass('select2-hidden-accessible')) {
                            $(filterDeptSelect).select2('destroy');
                        }
                        $(filterDeptSelect).select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            dropdownParent: $(filterForm).parent()
                        });
                    }

                    // Attach change listener using jQuery to catch Select2/Normal changes uniformly
                    $(filterCompanySelect).on('change', syncFilterDepartments);

                    // Initial run to align values on load
                    syncFilterDepartments();
                }
            }

            if (filterForm) {
                initInlineSelects(filterForm, filterForm.parentNode);
            }
            setPreview('create', null, document.getElementById('create_full_name')?.value || 'Employee');

            const formMode = @json(old('form_mode'));
            if (formMode === 'create') {
                initModalSelects(addEmployeeModal);
                bootstrap.Modal.getOrCreateInstance(addEmployeeModal).show();
            }

            if (formMode === 'edit') {
                initModalSelects(editEmployeeModal);
                bootstrap.Modal.getOrCreateInstance(editEmployeeModal).show();
            }

            const employeeIndexUrl = @json(route('hrms.employees.index'));
            let searchTimeout;
            let activeEmployeeRequest;

            function loadEmployeeList(url, closeFilter = false) {
                const listCard = $('#employeeListCard');
                listCard.addClass('is-loading');

                if (activeEmployeeRequest) {
                    activeEmployeeRequest.abort();
                }

                activeEmployeeRequest = $.ajax({
                    url: url,
                    type: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(response, 'text/html');
                        
                        // Update table using native selector to be 100% reliable
                        var newTable = doc.querySelector('.table-responsive');
                        var oldTable = document.querySelector('.table-responsive');
                        if (oldTable && newTable) {
                            oldTable.innerHTML = newTable.innerHTML;
                        }
                        
                        // Update pagination
                        var newPagination = doc.querySelector('#employeePaginationWrapper');
                        var oldPagination = document.querySelector('#employeePaginationWrapper');
                        if (oldPagination && newPagination) {
                            oldPagination.innerHTML = newPagination.innerHTML;
                        }

                        // Sync sorting active classes in dropdown only (non-disruptive, does not touch inputs)
                        var newUrl = new URL(url, window.location.href);
                        var sortVal = newUrl.searchParams.get('sort') || 'name_asc';
                        $('.employee-sort-link').each(function() {
                            var $link = $(this);
                            var sortData = $link.data('sort');
                            $link.removeClass('active');
                            $link.find('.feather-check').remove();

                            if (sortData === sortVal) {
                                $link.addClass('active');
                                $link.append('<i class="feather-check ms-3"></i>');
                            }
                        });

                        if (closeFilter) {
                            $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                            $('.erp-filter-dropdown.show').removeClass('show');
                        }
                    },
                    complete: function() {
                        activeEmployeeRequest = null;
                        listCard.removeClass('is-loading');
                    }
                });
            }

            // Live Search (AJAX) as user types with debounce
            $(document).on('input', '#employeeSearchForm input[name="search"]', function() {
                var $input = $(this);
                clearTimeout(searchTimeout);
                
                searchTimeout = setTimeout(function() {
                    // Update search value in filter form hidden input
                    $('#employeeFilterForm input[name="search"]').val($input.val());

                    var form = $('#employeeSearchForm');
                    var formData = form.serialize();
                    var url = form.attr('action') + '?' + formData;

                    loadEmployeeList(url);
                }, 300);
            });

            // Prevent default form submit on Enter and trigger AJAX load
            $(document).on('submit', '#employeeSearchForm', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var form = $(this);
                var formData = form.serialize();
                var url = form.attr('action') + '?' + formData;
                loadEmployeeList(url);
            });

            // Apply filter form submit
            $(document).on('submit', '#employeeFilterForm', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var form = $(this);

                // Sync filter select values to search form hidden inputs
                $('#employeeSearchForm input[name="company_id"]').val(form.find('select[name="company_id"]').val());
                $('#employeeSearchForm input[name="department_id"]').val(form.find('select[name="department_id"]').val());
                $('#employeeSearchForm input[name="status"]').val(form.find('select[name="status"]').val());

                var formData = form.serialize();
                var url = form.attr('action') + '?' + formData;
                loadEmployeeList(url, true);
            });

            // Reset filter button
            $(document).on('click', '.employee-filter-reset', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var url = $(this).attr('href');

                // Clear all search/filter fields on reset
                $('#employeeSearchForm input[name="search"]').val('');
                $('#employeeFilterForm input[name="search"]').val('');
                $('#employeeSearchForm input[name="sort"]').val('name_asc');
                $('#employeeFilterForm input[name="sort"]').val('name_asc');
                $('#employeeSearchForm input[name="company_id"]').val('');
                $('#employeeSearchForm input[name="department_id"]').val('');
                $('#employeeSearchForm input[name="status"]').val('');

                var companySelect = $('#employeeFilterForm select[name="company_id"]');
                if (companySelect.length) {
                    companySelect.val('').trigger('change.select2');
                }
                var deptSelect = $('#employeeFilterForm select[name="department_id"]');
                if (deptSelect.length) {
                    if (typeof syncFilterDepartments === 'function') {
                        syncFilterDepartments();
                    }
                    deptSelect.val('').trigger('change.select2');
                }
                var statusSelect = $('#employeeFilterForm select[name="status"]');
                if (statusSelect.length) {
                    statusSelect.val('').trigger('change.select2');
                }

                loadEmployeeList(url, true);
            });

            // Sort click
            $(document).on('click', '.employee-sort-link', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var sortCriteria = $(this).data('sort');
                
                // Update hidden inputs in both forms
                $('#employeeSearchForm input[name="sort"]').val(sortCriteria);
                $('#employeeFilterForm input[name="sort"]').val(sortCriteria);

                // Submit search form to trigger reload with new sort criteria
                var form = $('#employeeSearchForm');
                var formData = form.serialize();
                var url = form.attr('action') + '?' + formData;
                loadEmployeeList(url);
            });

            // Pagination click
            $(document).on('click', '#employeePaginationWrapper a', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var url = $(this).attr('href');
                if (!url || url.indexOf('javascript') === 0 || url.startsWith('#')) return;
                loadEmployeeList(url);
            });
        });
    </script>
@endsection
