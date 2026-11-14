@extends('layouts.duralux')
{{-- Job Requisitions List --}}

@section('title', __('hrms.recruitment.requisitions_title'))
@section('page-title', __('hrms.recruitment.requisitions_page_title'))
@section('breadcrumb', __('hrms.recruitment.requisitions_breadcrumb'))

@php
    $authUser = auth()->user();
    $canCreateRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.create') || $authUser->hasHrPermission('hr.settings.manage') || $authUser->hasHrPermission('hrms.recruitment.manage'));
    $canUpdateRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.update') || $authUser->hasHrPermission('hr.settings.manage') || $authUser->hasHrPermission('hrms.recruitment.manage'));
    $canManageRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.manage') || $authUser->hasHrPermission('hr.settings.manage'));

    $recruitmentTabs = [
        [
            'id' => 'tab-requisitions',
            'label' => __('hrms.recruitment.tab_requisitions'),
            'url' => route('hrms.recruitment.requisitions.index'),
            'active' => true,
            'icon' => 'feather-briefcase',
        ],
        [
            'id' => 'tab-candidates',
            'label' => __('hrms.recruitment.tab_candidates'),
            'url' => route('hrms.recruitment.candidates.index'),
            'active' => false,
            'icon' => 'feather-users',
        ],
    ];
@endphp

@section('page-actions')
    @if($canCreateRecruitment)
        <div class="d-flex align-items-center gap-2">
            <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createRequisitionModal">
                {{ __('hrms.recruitment.btn_new_requisition') }}
            </x-ui.button>
        </div>
    @endif
@endsection

@push('styles')
<style>
    .req-search-form {
        min-width: 260px;
        max-width: 320px;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }
    .req-search-form:focus-within {
        background-color: #fff !important;
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 0.18rem rgba(0, 0, 0, 0.05);
    }

    /* Recruitment Tables: Prevent horizontal scrollbar & wrap long content to next line */
    .recruitment-table {
        width: 100% !important;
        table-layout: fixed !important;
    }
    .recruitment-table th,
    .recruitment-table td {
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        word-break: break-word !important;
        white-space: normal !important;
    }

    /* Table Dropdown Overflow Fix: Ensure dropdown menu escapes container clipping and floats above all elements */
    .erp-single-panel .table-responsive,
    .erp-single-panel .table-responsive:has(.dropdown.show) {
        overflow: visible !important;
    }
    .recruitment-table .dropdown-menu {
        z-index: 1065 !important;
        min-width: 225px !important;
        border-radius: 8px !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
        border: 1px solid #e2e8f0 !important;
    }
    .recruitment-table .dropdown-item {
        padding: 8px 14px !important;
        font-size: 12.5px !important;
        display: flex !important;
        align-items: center !important;
    }
    .recruitment-table .dropdown-item:hover {
        background-color: #f8fafc !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">

    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- ERP Single Panel Container -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        <!-- Panel Header with Sub Navigation Tabs -->
        <div class="mb-4 border-bottom pb-2">
            <x-ui.horizontal-tabs id="recruitmentTabs" :tabs="$recruitmentTabs" />
        </div>

        @if(isset($upcomingInterviews) && $upcomingInterviews->count() > 0)
            <!-- Upcoming Scheduled Interviews Widget -->
            <div class="mb-4">
                <div class="card border border-warning-subtle bg-soft-warning rounded-3 shadow-sm">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-2 px-3">
                        <span class="fw-bold text-dark fs-13">
                            <i class="feather-calendar text-warning me-2"></i> {{ __('hrms.recruitment.upcoming_interviews_heading', ['count' => $upcomingInterviews->count()]) }}
                        </span>
                    </div>
                    <div class="card-body p-3 pt-0">
                        <div class="row g-2">
                            @foreach($upcomingInterviews as $interview)
                                @php
                                    $candidate = $interview->application->candidate ?? null;
                                    $req = $interview->application->requisition ?? null;
                                @endphp
                                <div class="col-md-6 col-lg-4">
                                    <div class="bg-white border rounded p-3 h-100 shadow-xs d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <h6 class="mb-0 fw-bold text-dark fs-13">{{ $candidate->full_name ?? __('hrms.recruitment.candidate_fallback') }}</h6>
                                                <x-ui.badge variant="warning" soft>{{ __('hrms.recruitment.round_prefix', ['number' => $interview->round_number]) }}</x-ui.badge>
                                            </div>
                                            <div class="text-muted fs-12 mb-2">
                                                <i class="feather-briefcase me-1"></i>{{ $req->job_title ?? __('hrms.recruitment.role_fallback') }}
                                            </div>
                                            <div class="text-secondary fs-12 mb-2">
                                                <i class="feather-clock text-warning me-1"></i>
                                                {{ $interview->scheduled_at ? $interview->scheduled_at->format('M d, Y - h:i A') : __('hrms.common.tbd') }}
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between pt-2 border-top gap-2">
                                            @if($req)
                                                <x-ui.button :href="route('hrms.recruitment.pipeline', ['requisition' => $req->id, 'candidate_id' => $candidate?->id])" variant="outline-primary" size="sm" icon="feather-columns">
                                                    {{ __('hrms.recruitment.btn_pipeline') }}
                                                </x-ui.button>
                                            @endif
                                            @if($interview->meeting_link)
                                                <x-ui.button :href="$interview->meeting_link" target="_blank" variant="primary" size="sm" icon="feather-video">
                                                    {{ __('hrms.recruitment.btn_join') }}
                                                </x-ui.button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Search, Sort & Filter Toolbar -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h5 class="fw-bold text-dark mb-0 fs-16">{{ __('hrms.recruitment.requisitions_heading') }}</h5>
                <small class="text-muted">{{ __('hrms.recruitment.requisitions_subtitle') }}</small>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Standard Search Bar -->
                <form method="GET" action="{{ route('hrms.recruitment.requisitions.index') }}" class="req-search-form d-flex align-items-center bg-light border rounded px-3 py-1" style="height: 38px;">
                    <input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control border-0 bg-transparent p-0 fs-13" 
                        placeholder="{{ __('hrms.recruitment.search_placeholder_req') }}" 
                        value="{{ $filters['search'] }}"
                        style="box-shadow: none;"
                    >
                    @if($filters['search'])
                        <a href="{{ route('hrms.recruitment.requisitions.index', ['department_id' => $filters['department_id'], 'status' => $filters['status'], 'sort' => $filters['sort']]) }}" class="text-muted ms-1"><i class="feather-x"></i></a>
                    @endif
                </form>

                <!-- Standard Sort Dropdown Component -->
                <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'date_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'date_desc']) }}">
                        <span>{{ __('hrms.recruitment.sort_newest_first') }}</span>
                        @if($filters['sort'] === 'date_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'date_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'date_asc']) }}">
                        <span>{{ __('hrms.recruitment.sort_oldest_first') }}</span>
                        @if($filters['sort'] === 'date_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'title_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'title_asc']) }}">
                        <span>{{ __('hrms.recruitment.sort_title_asc') }}</span>
                        @if($filters['sort'] === 'title_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'title_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'title_desc']) }}">
                        <span>{{ __('hrms.recruitment.sort_title_desc') }}</span>
                        @if($filters['sort'] === 'title_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'vacancies_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'vacancies_desc']) }}">
                        <span>{{ __('hrms.recruitment.sort_most_vacancies') }}</span>
                        @if($filters['sort'] === 'vacancies_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                </x-ui.sort-dropdown>

                <!-- Standard Filter Component -->
                <x-ui.filter :label="__('hrms.common.filter')">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                    <form method="GET" action="{{ route('hrms.recruitment.requisitions.index') }}">
                        <input type="hidden" name="search" value="{{ $filters['search'] }}">
                        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                        
                        <x-ui.odoo-form-ui type="select" :label="__('hrms.common.department')" name="department_id">
                            <option value="">{{ __('hrms.common.all_departments') }}</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ (string) $filters['department_id'] === (string) $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" :label="__('hrms.common.status')" name="status">
                            <option value="">{{ __('hrms.common.all_statuses') }}</option>
                            <option value="approved" {{ $filters['status'] === 'approved' ? 'selected' : '' }}>{{ __('hrms.recruitment.status_open') }}</option>
                            <option value="pending_approval" {{ $filters['status'] === 'pending_approval' ? 'selected' : '' }}>{{ __('hrms.recruitment.status_pending_approval') }}</option>
                            <option value="closed" {{ $filters['status'] === 'closed' ? 'selected' : '' }}>{{ __('hrms.recruitment.status_closed') }}</option>
                        </x-ui.odoo-form-ui>

                        <div class="pt-2 d-flex gap-2">
                            <x-ui.button type="submit" variant="primary" size="sm" class="w-100 fw-bold">{{ __('hrms.recruitment.btn_apply_filters') }}</x-ui.button>
                            <x-ui.button variant="light" size="sm" href="{{ route('hrms.recruitment.requisitions.index') }}" class="w-100 border">{{ __('hrms.common.reset') }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.filter>
            </div>
        </div>

        <div class="table-responsive border rounded" style="min-height: 200px;">
            <table class="table table-hover align-middle mb-0 recruitment-table">
                <thead class="bg-light text-muted fs-12 text-uppercase">
                    <tr>
                        <th class="ps-3" style="width: 25%;">{{ __('hrms.recruitment.th_code_job_title') }}</th>
                        <th style="width: 20%;">{{ __('hrms.recruitment.th_dept_role') }}</th>
                        <th style="width: 14%;">{{ __('hrms.recruitment.th_req_exp') }}</th>
                        <th style="width: 11%;">{{ __('hrms.recruitment.th_vacancies') }}</th>
                        <th class="text-center" style="width: 9%;">{{ __('hrms.recruitment.th_priority') }}</th>
                        <th class="text-center" style="width: 8%;">{{ __('hrms.common.status') }}</th>
                        <th class="text-end pe-3" style="width: 13%;">{{ __('hrms.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requisitions as $req)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-dark fs-14">{{ $req->job_title }}</div>
                                <span class="badge bg-light text-secondary border mt-1">{{ $req->requisition_code }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark fs-13">{{ $req->department->name ?? __('hrms.common.na') }}</div>
                                <small class="text-muted fs-12 d-block">{{ $req->designation->name ?? __('hrms.common.na') }}</small>
                            </td>
                            <td>
                                <x-ui.badge variant="primary" soft>
                                    <i class="feather-award me-1"></i>{{ __('hrms.recruitment.exp_years_range', ['min' => $req->min_experience_years, 'max' => $req->max_experience_years]) }}
                                </x-ui.badge>
                            </td>
                            <td>
                                <span class="fw-bold text-dark fs-13">{{ __('hrms.recruitment.vacancies_positions', ['count' => $req->vacancies]) }}</span>
                            </td>
                            <td class="text-center">
                                @if($req->priority === 'urgent')
                                    <x-ui.badge variant="danger">{{ __('hrms.recruitment.priority_urgent') }}</x-ui.badge>
                                @elseif($req->priority === 'high')
                                    <x-ui.badge variant="warning">{{ __('hrms.recruitment.priority_high') }}</x-ui.badge>
                                @elseif($req->priority === 'medium')
                                    <x-ui.badge variant="info" soft>{{ __('hrms.recruitment.priority_medium') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="secondary" soft>{{ __('hrms.recruitment.priority_low') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-center">
                                <x-ui.status-badge :status="$req->status" :label="in_array($req->status, ['approved', 'published', 'open']) ? __('hrms.recruitment.status_open') : null" />
                            </td>
                            <td class="text-end pe-3">
                                <x-ui.action-dropdown align="end">
                                    <x-slot:extraActions>
                                        <x-ui.button :href="route('hrms.recruitment.pipeline', $req->id)" variant="primary" size="sm" icon="feather-columns" class="text-nowrap">
                                            {{ __('hrms.recruitment.btn_pipeline') }}
                                        </x-ui.button>
                                    </x-slot:extraActions>

                                    @if($canUpdateRecruitment)
                                        <li>
                                            <form action="{{ route('hrms.recruitment.requisitions.status', $req->id) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="dropdown-item py-2 px-3 {{ $req->status === 'approved' ? 'active fw-bold' : '' }}">
                                                    <i class="feather-check-circle text-success me-2 fs-13"></i> {{ __('hrms.recruitment.btn_mark_open') }}
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form action="{{ route('hrms.recruitment.requisitions.status', $req->id) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="status" value="published">
                                                <button type="submit" class="dropdown-item py-2 px-3 {{ $req->status === 'published' ? 'active fw-bold' : '' }}">
                                                    <i class="feather-globe text-primary me-2 fs-13"></i> {{ __('hrms.recruitment.btn_mark_published') }}
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form action="{{ route('hrms.recruitment.requisitions.status', $req->id) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="status" value="closed">
                                                <button type="submit" class="dropdown-item py-2 px-3 {{ $req->status === 'closed' ? 'active fw-bold' : '' }}">
                                                    <i class="feather-lock text-danger me-2 fs-13"></i> {{ __('hrms.recruitment.btn_mark_closed') }}
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form action="{{ route('hrms.recruitment.requisitions.status', $req->id) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="status" value="cancelled">
                                                <button type="submit" class="dropdown-item py-2 px-3 {{ $req->status === 'cancelled' ? 'active fw-bold' : '' }}">
                                                    <i class="feather-slash text-muted me-2 fs-13"></i> {{ __('hrms.recruitment.btn_mark_cancelled') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="feather-folder-minus fs-36 d-block mb-2 text-secondary"></i>
                                {{ __('hrms.recruitment.no_requisitions_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            <x-ui.pagination :paginator="$requisitions" />
        </div>
    </div>
</div>

<!-- Modal: New Job Requisition -->
<x-ui.modal id="createRequisitionModal" :title="'<i class=\'feather-plus-circle text-primary me-2\'></i>' . __('hrms.recruitment.modal_create_req_title')" size="lg" :centered="true" :showFooter="false">
    <form action="{{ route('hrms.recruitment.requisitions.store') }}" method="POST">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_job_title')" name="job_title" :required="true" :placeholder="__('hrms.recruitment.placeholder_job_title')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.common.department')" name="department_id" :required="true">
                    <option value="">{{ __('hrms.recruitment.select_department') }}</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_designation')" name="designation_id" :required="true">
                    <option value="">{{ __('hrms.recruitment.select_designation') }}</option>
                    @foreach($designations as $desig)
                        <option value="{{ $desig->id }}">{{ $desig->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.recruitment.field_vacancies_count')" name="vacancies" value="1" :required="true" />
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.recruitment.field_min_exp')" name="min_experience_years" value="0" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.recruitment.field_max_exp')" name="max_experience_years" value="5" :required="true" />
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_work_mode')" name="work_mode">
                    <option value="onsite">{{ __('hrms.recruitment.work_mode_onsite') }}</option>
                    <option value="remote">{{ __('hrms.recruitment.work_mode_remote') }}</option>
                    <option value="hybrid">{{ __('hrms.recruitment.work_mode_hybrid') }}</option>
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_employment_type')" name="employment_type">
                    <option value="full_time">{{ __('hrms.recruitment.emp_type_full_time') }}</option>
                    <option value="part_time">{{ __('hrms.recruitment.emp_type_part_time') }}</option>
                    <option value="contract">{{ __('hrms.recruitment.emp_type_contract') }}</option>
                    <option value="internship">{{ __('hrms.recruitment.emp_type_internship') }}</option>
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.th_priority')" name="priority">
                    <option value="low">{{ __('hrms.recruitment.priority_low') }}</option>
                    <option value="medium" selected>{{ __('hrms.recruitment.priority_medium') }}</option>
                    <option value="high">{{ __('hrms.recruitment.priority_high') }}</option>
                    <option value="urgent">{{ __('hrms.recruitment.priority_urgent') }}</option>
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.recruitment.field_target_joining_date')" name="target_joining_date" />
            </div>

            <div class="col-md-12">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_skills_required')" name="skills_required" :placeholder="__('hrms.recruitment.placeholder_skills_required')" />
            </div>

            <div class="col-md-12">
                <x-ui.odoo-form-ui type="textarea" :label="__('hrms.recruitment.field_job_description')" name="job_description" rows="3" :placeholder="__('hrms.recruitment.placeholder_job_description')" />
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 mt-3 border-top">
            <x-ui.button variant="light" size="sm" class="border px-4" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm" class="px-4 fw-bold" icon="feather-check">{{ __('hrms.recruitment.btn_save_requisition') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
@endsection
