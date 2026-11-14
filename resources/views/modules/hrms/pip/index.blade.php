@extends('layouts.duralux')

@section('title', __('hrms.pip.meta_title'))
@section('page-title', __('hrms.pip.title'))
@section('breadcrumb', __('hrms.pip.breadcrumb'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        @if($activeTab === 'categories')
            <x-ui.button variant="primary" icon="feather-plus-circle" data-bs-toggle="modal" data-bs-target="#createCategoryModal" class="fw-bold text-uppercase">
                {{ __('hrms.pip.add_pip_category') }}
            </x-ui.button>
        @else
            <x-ui.button variant="primary" icon="feather-plus-circle" data-bs-toggle="modal" data-bs-target="#createPipModal" class="fw-bold text-uppercase">
                {{ __('hrms.pip.initiate_new_pip') }}
            </x-ui.button>
        @endif
    </div>
@endsection

@push('styles')
<style>
    /* Underlined Horizontal Tabs (Matching Standard Platform UI) */
    #pipTabs .nav-link {
        border: none !important;
        background-color: transparent !important;
        color: #64748b;
        font-weight: 500;
        padding: 12px 20px;
        border-bottom: 2px solid transparent !important;
        transition: all 0.2s ease-in-out;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    #pipTabs .nav-link:hover {
        color: var(--bs-primary);
    }
    #pipTabs .nav-link.active {
        color: var(--bs-primary) !important;
        border-bottom: 2px solid var(--bs-primary) !important;
        font-weight: 600;
    }

    .avatar-initials {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background-color: rgba(var(--bs-primary-rgb), 0.1);
        color: var(--bs-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
        flex-shrink: 0;
    }

    /* PIP Tables: Prevent horizontal scrollbar & wrap long content to next line */
    .pip-table {
        width: 100% !important;
        table-layout: fixed !important;
    }
    .pip-table th,
    .pip-table td {
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        word-break: break-word !important;
        white-space: normal !important;
    }
    .pip-table .badge-wrap,
    .pip-table .badge {
        white-space: normal !important;
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        word-break: break-word !important;
        line-height: 1.35 !important;
        max-width: 100% !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">

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

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        @php
            $mainTabs = [
                [
                    'id' => 'tab-plans',
                    'label' => __('hrms.pip.tab_active_plans'),
                    'active' => ($activeTab === 'plans'),
                    'icon' => 'feather-list',
                    'url' => route('hrms.pip.index', ['active_tab' => 'plans']),
                ],
                [
                    'id' => 'tab-categories',
                    'label' => __('hrms.pip.tab_categories'),
                    'active' => ($activeTab === 'categories'),
                    'icon' => 'feather-tag',
                    'url' => route('hrms.pip.index', ['active_tab' => 'categories']),
                ],
            ];
        @endphp

        <!-- Navigation Tabs & Right Toolbar (Search, Sort, Filter at Right Corner) -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 border-bottom pb-2">
            <!-- Left Common UI Horizontal Tabs -->
            <div class="flex-grow-1" style="min-width: 0;">
                <x-ui.horizontal-tabs id="pipMainTabs" :tabs="$mainTabs" />
            </div>

            <!-- Right Toolbar: Search, Sort, Filter aligned at Right Corner (Standard UI) -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                @if($activeTab === 'plans')
                    <!-- Search Input Form with Instant Auto-Submit (no Enter required) -->
                    <form method="GET" action="{{ route('hrms.pip.index') }}" id="pipSearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 250px; height: 36px !important; box-sizing: border-box !important;">
                        <input type="hidden" name="active_tab" value="plans">
                        <input type="hidden" name="status" value="{{ request('status') }}">
                        <input type="hidden" name="department_id" value="{{ request('department_id') }}">
                        <input type="hidden" name="category_id" value="{{ request('category_id') }}">
                        <input type="hidden" name="sort" value="{{ request('sort', 'newest') }}">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="search" id="pipSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.pip.search_plans_placeholder') }}" value="{{ request('search') }}" autocomplete="off" style="box-shadow: none; height: 100%; outline: none;">
                    </form>

                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort', 'newest') === 'newest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'newest', 'active_tab' => 'plans']) }}">
                            <span>{{ __('hrms.pip.sort_newest') }}</span>
                            @if(request('sort', 'newest') === 'newest') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'oldest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'oldest', 'active_tab' => 'plans']) }}">
                            <span>{{ __('hrms.pip.sort_oldest') }}</span>
                            @if(request('sort') === 'oldest') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'pip_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'pip_asc', 'active_tab' => 'plans']) }}">
                            <span>{{ __('hrms.pip.sort_pip_asc') }}</span>
                            @if(request('sort') === 'pip_asc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'pip_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'pip_desc', 'active_tab' => 'plans']) }}">
                            <span>{{ __('hrms.pip.sort_pip_desc') }}</span>
                            @if(request('sort') === 'pip_desc') <i class="feather-check ms-3"></i> @endif
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown -->
                    <x-ui.filter label="{{ __('hrms.common.filter') }}">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                        <form method="GET" action="{{ route('hrms.pip.index') }}">
                            <input type="hidden" name="active_tab" value="plans">
                            <input type="hidden" name="search" value="{{ request('search') }}">
                            <input type="hidden" name="sort" value="{{ request('sort') }}">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.common.status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                    <option value="active" @selected(request('status') === 'active')>{{ __('hrms.pip.status_active') }}</option>
                                    <option value="under_review" @selected(request('status') === 'under_review')>{{ __('hrms.pip.status_under_review') }}</option>
                                    <option value="completed_success" @selected(request('status') === 'completed_success')>{{ __('hrms.pip.status_completed_success') }}</option>
                                    <option value="extended" @selected(request('status') === 'extended')>{{ __('hrms.pip.status_extended') }}</option>
                                    <option value="role_reassigned" @selected(request('status') === 'role_reassigned')>{{ __('hrms.pip.status_role_reassigned') }}</option>
                                    <option value="failed_terminated" @selected(request('status') === 'failed_terminated')>{{ __('hrms.pip.status_failed_terminated') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.common.department') }}</label>
                                <x-ui.odoo-form-ui type="select" name="department_id">
                                    <option value="">{{ __('hrms.common.all_departments') }}</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" @selected(request('department_id') == $dept->id)>{{ $dept->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.pip.category') }}</label>
                                <x-ui.odoo-form-ui type="select" name="category_id">
                                    <option value="">{{ __('hrms.pip.all_categories') }}</option>
                                    @foreach($categoriesList as $cat)
                                        <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4 pt-2 border-top">
                                <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.pip.apply_filters') }}</x-ui.button>
                                <x-ui.button href="{{ route('hrms.pip.index', ['active_tab' => 'plans']) }}" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.common.reset') }}</x-ui.button>
                            </div>
                        </form>
                    </x-ui.filter>
                @elseif($activeTab === 'categories')
                    <!-- Search Input Form with Instant Auto-Submit for Categories -->
                    <form method="GET" action="{{ route('hrms.pip.index') }}" id="catSearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 250px; height: 36px !important; box-sizing: border-box !important;">
                        <input type="hidden" name="active_tab" value="categories">
                        <input type="hidden" name="status" value="{{ request('status') }}">
                        <input type="hidden" name="sort" value="{{ request('sort', 'newest') }}">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="search" id="catSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.pip.search_categories_placeholder') }}" value="{{ request('search') }}" autocomplete="off" style="box-shadow: none; height: 100%; outline: none;">
                    </form>

                    <!-- Sort Dropdown for Categories -->
                    <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort', 'newest') === 'newest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'newest', 'active_tab' => 'categories']) }}">
                            <span>{{ __('hrms.pip.sort_newest') }}</span>
                            @if(request('sort', 'newest') === 'newest') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'oldest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'oldest', 'active_tab' => 'categories']) }}">
                            <span>{{ __('hrms.pip.sort_oldest') }}</span>
                            @if(request('sort') === 'oldest') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'name_asc', 'active_tab' => 'categories']) }}">
                            <span>{{ __('hrms.common.sort_name_asc') }}</span>
                            @if(request('sort') === 'name_asc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'name_desc', 'active_tab' => 'categories']) }}">
                            <span>{{ __('hrms.common.sort_name_desc') }}</span>
                            @if(request('sort') === 'name_desc') <i class="feather-check ms-3"></i> @endif
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown for Categories -->
                    <x-ui.filter label="{{ __('hrms.common.filter') }}">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                        <form method="GET" action="{{ route('hrms.pip.index') }}">
                            <input type="hidden" name="active_tab" value="categories">
                            <input type="hidden" name="search" value="{{ request('search') }}">
                            <input type="hidden" name="sort" value="{{ request('sort') }}">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.common.status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                    <option value="active" @selected(request('status') === 'active')>{{ __('hrms.pip.status_active') }}</option>
                                    <option value="inactive" @selected(request('status') === 'inactive')>{{ __('hrms.common.inactive') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4 pt-2 border-top">
                                <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.pip.apply_filters') }}</x-ui.button>
                                <x-ui.button href="{{ route('hrms.pip.index', ['active_tab' => 'categories']) }}" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.common.reset') }}</x-ui.button>
                            </div>
                        </form>
                    </x-ui.filter>
                @elseif($activeTab === 'templates')
                    <x-ui.button variant="outline-primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createTemplateModal" class="fw-bold">
                        {{ __('hrms.pip.btn_add_template') }}
                    </x-ui.button>
                @endif
            </div>
        </div>

        <!-- TAB CONTENT SLOTS -->
        @if($activeTab === 'plans')

            <!-- PIP PLANS TABLE -->
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0 fs-13 pip-table">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11" style="width: 10%;">{{ __('hrms.pip.th_pip_no') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11" style="width: 21%;">{{ __('hrms.common.employee') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11" style="width: 18%;">{{ __('hrms.pip.th_reason_category') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11" style="width: 17%;">{{ __('hrms.pip.th_timeline') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11" style="width: 11%;">{{ __('hrms.pip.th_checkin_freq') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11 text-center" style="width: 13%;">{{ __('hrms.common.status') }}</th>
                            <th class="text-end pe-3 py-3 text-muted text-uppercase fs-11" style="width: 10%;">{{ __('hrms.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $plan)
                            <tr>
                                <td class="ps-3 fw-bold text-primary">
                                    <a href="{{ route('hrms.pip.show', $plan->id) }}" class="text-decoration-none text-primary">
                                        {{ $plan->pip_number }}
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                                        <div class="avatar-initials flex-shrink-0">
                                            {{ strtoupper(substr($plan->employee->full_name ?? 'E', 0, 2)) }}
                                        </div>
                                        <div class="text-break" style="min-width: 0;">
                                            <div class="fw-semibold text-dark text-wrap">{{ $plan->employee->full_name ?? __('hrms.common.na') }}</div>
                                            <small class="text-muted fs-11 d-block text-wrap" style="line-height: 1.3;">{{ $plan->employee->designation?->name ?? __('hrms.common.na') }} &bull; {{ $plan->employee->department?->name ?? __('hrms.common.na') }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-inline-flex align-items-start gap-1.5 px-2 py-1 bg-light text-dark border rounded-2 fs-11 text-wrap text-break" style="max-width: 100%; line-height: 1.35;">
                                        <i class="feather-tag text-primary flex-shrink-0" style="font-size: 11px; margin-top: 2px;"></i>
                                        <span class="text-break" style="word-break: break-word; white-space: normal;">{{ $plan->category?->name ?? __('hrms.pip.general_performance') }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fs-12 fw-semibold text-dark text-wrap lh-sm">
                                        {{ $plan->start_date ? $plan->start_date->format('M d, Y') : __('hrms.common.na') }} &ndash; {{ $plan->end_date ? $plan->end_date->format('M d, Y') : __('hrms.common.na') }}
                                    </div>
                                    <small class="text-muted fs-11 d-block mt-0.5">
                                        @if($plan->end_date && $plan->end_date->isFuture() && $plan->status === 'active')
                                            @php
                                                $remainingDays = (int) ceil(now()->diffInDays($plan->end_date));
                                            @endphp
                                            {{ __('hrms.pip.days_remaining', ['count' => $remainingDays, 'unit' => \Illuminate\Support\Str::plural('day', $remainingDays)]) }}
                                        @else
                                            {{ __('hrms.pip.timeline_finished') }}
                                        @endif
                                    </small>
                                </td>
                                <td>
                                    <span class="text-capitalize fw-medium text-secondary d-block">
                                        <i class="feather-clock me-1 text-muted"></i> {{ __('hrms.pip.freq_' . $plan->checkin_frequency) }}
                                    </span>
                                    @if($plan->is_checkin_overdue)
                                        <x-ui.badge soft variant="danger" class="mt-1 fs-11" title="{{ __('hrms.pip.overdue') }}">
                                            <i class="feather-alert-triangle me-1"></i> {{ __('hrms.pip.overdue') }} ({{ $plan->next_checkin_due_date?->format('M d') }})
                                        </x-ui.badge>
                                    @elseif($plan->next_checkin_due_date)
                                        <small class="text-muted fs-11 d-block mt-0.5">{{ __('hrms.pip.next_prefix') }} {{ $plan->next_checkin_due_date->format('M d') }}</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @php
                                        $badgeVariant = match($plan->status) {
                                            'active' => 'success',
                                            'under_review' => 'warning',
                                            'completed_success' => 'info',
                                            'extended' => 'primary',
                                            'failed_terminated' => 'danger',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <x-ui.badge soft variant="{{ $badgeVariant }}" class="text-capitalize text-nowrap">
                                        {{ __('hrms.pip.status_' . $plan->status) }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-end pe-3">
                                    <x-ui.action-dropdown id="pipActions{{ $plan->id }}" viewUrl="{{ route('hrms.pip.show', $plan->id) }}" align="end">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('hrms.pip.show', $plan->id) }}">
                                                <i class="feather-eye me-2 text-primary"></i> {{ __('hrms.pip.open_workspace') }}
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editPipModal_{{ $plan->id }}">
                                                <i class="feather-edit-2 me-2 text-info"></i> {{ __('hrms.pip.edit_plan_details') }}
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="javascript:void(0);" onclick="confirmAction('{{ addslashes(__('hrms.pip.confirm_delete_pip', ['pip' => $plan->pip_number])) }}', function() { document.getElementById('deletePipForm{{ $plan->id }}').submit(); }, { title: '{{ addslashes(__('hrms.pip.delete_pip_plan_title')) }}', confirmText: '{{ addslashes(__('hrms.pip.yes_delete')) }}', variant: 'danger' });">
                                                <i class="feather-trash-2 me-2"></i> {{ __('hrms.pip.delete_plan') }}
                                            </a>
                                        </li>
                                    </x-ui.action-dropdown>
                                    <form id="deletePipForm{{ $plan->id }}" action="{{ route('hrms.pip.destroy', $plan->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <!-- EDIT PIP MODAL -->
                                    <x-ui.modal id="editPipModal_{{ $plan->id }}" title="<i class='feather-edit me-1.5 text-primary'></i> {{ __('hrms.pip.modal_edit_plan_title', ['pip' => $plan->pip_number]) }}" size="lg" :showFooter="false">
                                        <form action="{{ route('hrms.pip.update', $plan->id) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.pip.pip_category') }}" name="pip_category_id">
                                                        <option value="">{{ __('hrms.pip.select_category_placeholder') }}</option>
                                                        @foreach($categoriesList as $cat)
                                                            <option value="{{ $cat->id }}" @selected($plan->pip_category_id == $cat->id)>{{ $cat->name }}</option>
                                                        @endforeach
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.pip.checkin_frequency') }}" name="checkin_frequency" :required="true">
                                                        <option value="weekly" @selected($plan->checkin_frequency === 'weekly')>{{ __('hrms.pip.freq_weekly') }}</option>
                                                        <option value="biweekly" @selected($plan->checkin_frequency === 'biweekly')>{{ __('hrms.pip.freq_biweekly') }}</option>
                                                        <option value="monthly" @selected($plan->checkin_frequency === 'monthly')>{{ __('hrms.pip.freq_monthly') }}</option>
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.pip.start_date') }}" name="start_date" value="{{ $plan->start_date?->format('Y-m-d') }}" :required="true" />
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.pip.end_date') }}" name="end_date" value="{{ $plan->end_date?->format('Y-m-d') }}" :required="true" />
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.pip.plan_status') }}" name="status" :required="true">
                                                    <option value="active" @selected($plan->status === 'active')>{{ __('hrms.pip.status_active') }}</option>
                                                    <option value="under_review" @selected($plan->status === 'under_review')>{{ __('hrms.pip.status_under_review') }}</option>
                                                    <option value="extended" @selected($plan->status === 'extended')>{{ __('hrms.pip.status_extended') }}</option>
                                                    <option value="completed_success" @selected($plan->status === 'completed_success')>{{ __('hrms.pip.status_completed_success') }}</option>
                                                    <option value="role_reassigned" @selected($plan->status === 'role_reassigned')>{{ __('hrms.pip.status_role_reassigned') }}</option>
                                                    <option value="failed_terminated" @selected($plan->status === 'failed_terminated')>{{ __('hrms.pip.status_failed_terminated') }}</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                            <div class="mb-0">
                                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.reason_deficiencies') }}" name="reason_details" rows="3" value="{{ $plan->reason_details }}" :required="true" />
                                            </div>
                                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                                <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                                                <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold px-4">{{ __('hrms.pip.btn_update_plan') }}</x-ui.button>
                                            </div>
                                        </form>
                                    </x-ui.modal>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="feather-inbox fs-34 d-block mb-2 text-secondary opacity-50"></i>
                                    {{ __('hrms.pip.empty_plans') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($plans->total() > 0)
                <x-ui.pagination
                    class="mt-3"
                    :current-page="$plans->currentPage()"
                    :total-pages="$plans->lastPage()"
                    :total-results="$plans->total()"
                    :per-page="$plans->perPage()"
                    page-param="page"
                    tab="plans"
                />
            @endif

        @elseif($activeTab === 'categories')
            <!-- TAB 2: PIP CATEGORIES MASTER -->
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0 fs-13 pip-table">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11" style="width: 25%;">{{ __('hrms.pip.th_category_name') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11" style="width: 50%;">{{ __('hrms.pip.th_description') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11 text-center" style="width: 15%;">{{ __('hrms.common.status') }}</th>
                            <th class="text-end pe-3 py-3 text-muted text-uppercase fs-11" style="width: 10%;">{{ __('hrms.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $cat)
                            <tr>
                                <td class="ps-3 fw-bold text-dark">{{ $cat->name }}</td>
                                <td class="text-muted">{{ $cat->description ?? __('hrms.common.na') }}</td>
                                <td class="text-center">
                                    @php
                                        $catActive = !isset($cat->status) || $cat->status === 'active' || $cat->status == 1;
                                    @endphp
                                    <x-ui.badge soft variant="{{ $catActive ? 'success' : 'secondary' }}">
                                        {{ $catActive ? __('hrms.pip.status_active') : __('hrms.common.inactive') }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-end pe-3">
                                    <form id="deleteCatForm{{ $cat->id }}" action="{{ route('hrms.pip.category.destroy', $cat->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-btn type="button" variant="soft-danger" icon="feather-trash-2" title="{{ __('hrms.pip.delete_category_modal_title') }}" class="btn-delete-cat" data-cat-id="{{ $cat->id }}" data-cat-name="{{ $cat->name }}" />
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="feather-inbox fs-34 d-block mb-2 text-secondary opacity-50"></i>
                                    {!! __('hrms.pip.empty_categories') !!}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($categories->total() > 0)
                <x-ui.pagination
                    class="mt-3"
                    :current-page="$categories->currentPage()"
                    :total-pages="$categories->lastPage()"
                    :total-results="$categories->total()"
                    :per-page="$categories->perPage()"
                    page-param="categories_page"
                    tab="categories"
                />
            @endif

        @elseif($activeTab === 'templates')
            <!-- TAB 3: POLICY TEMPLATES MASTER -->
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0 fs-13 pip-table">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11" style="width: 25%;">{{ __('hrms.pip.th_template_name') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11" style="width: 15%;">{{ __('hrms.pip.th_duration_days') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11" style="width: 15%;">{{ __('hrms.pip.th_checkin_freq') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11" style="width: 35%;">{{ __('hrms.pip.th_description') }}</th>
                            <th class="text-end pe-3 py-3 text-muted text-uppercase fs-11" style="width: 10%;">{{ __('hrms.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($policyTemplates as $tmpl)
                            <tr>
                                <td class="ps-3 fw-bold text-dark">{{ $tmpl->name }}</td>
                                <td><x-ui.badge soft variant="secondary">{{ __('hrms.pip.days_duration', ['count' => $tmpl->duration_days]) }}</x-ui.badge></td>
                                <td class="text-capitalize">{{ __('hrms.pip.freq_' . $tmpl->checkin_frequency . '_simple') }}</td>
                                <td class="text-muted">{{ $tmpl->description ?? __('hrms.common.na') }}</td>
                                <td class="text-end pe-3">
                                    <form id="deleteTmplForm{{ $tmpl->id }}" action="{{ route('hrms.pip.template.destroy', $tmpl->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-btn type="button" variant="soft-danger" icon="feather-trash-2" title="{{ __('hrms.common.delete') }}" class="btn-delete-tmpl" data-tmpl-id="{{ $tmpl->id }}" data-tmpl-name="{{ $tmpl->name }}" />
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">{{ __('hrms.pip.empty_templates') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($policyTemplates->total() > 0)
                <x-ui.pagination
                    class="mt-3"
                    :current-page="$policyTemplates->currentPage()"
                    :total-pages="$policyTemplates->lastPage()"
                    :total-results="$policyTemplates->total()"
                    :per-page="$policyTemplates->perPage()"
                    page-param="templates_page"
                    tab="templates"
                />
            @endif
        @endif

    </div>
</div>

<!-- INITIATE PIP MODAL -->
<x-ui.modal id="createPipModal" title="<i class='feather-plus-circle me-1.5 text-primary'></i> {{ __('hrms.pip.modal_initiate_title') }}" size="lg" :showFooter="false">
    <form action="{{ route('hrms.pip.store') }}" method="POST">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.pip.field_target_employee') }}" name="employee_id" :required="true">
                    <option value="">{{ __('hrms.pip.select_employee_placeholder') }}</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.pip.field_manager') }}" name="manager_id">
                    <option value="">{{ __('hrms.pip.select_manager_placeholder') }}</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.pip.field_category') }}" name="pip_category_id">
                    <option value="">{{ __('hrms.pip.select_category_placeholder') }}</option>
                    @foreach($categoriesList as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.pip.field_template') }}" name="policy_template_id" id="pipPolicyTemplate">
                    <option value="">{{ __('hrms.pip.no_template_manual') }}</option>
                    @foreach($policyTemplatesList as $tmpl)
                        <option value="{{ $tmpl->id }}"
                            data-duration="{{ $tmpl->duration_days }}"
                            data-frequency="{{ $tmpl->checkin_frequency }}">
                            {{ $tmpl->name }} ({{ __('hrms.pip.days_duration', ['count' => $tmpl->duration_days]) }}, {{ __('hrms.pip.freq_' . $tmpl->checkin_frequency . '_simple') }})
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.pip.field_checkin_freq') }}" name="checkin_frequency" id="pipCheckinFreq" :required="true">
                    <option value="weekly" selected>{{ __('hrms.pip.freq_weekly') }}</option>
                    <option value="biweekly">{{ __('hrms.pip.freq_biweekly') }}</option>
                    <option value="monthly">{{ __('hrms.pip.freq_monthly') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.pip.field_start_date') }}" name="start_date" value="{{ date('Y-m-d') }}" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.pip.field_end_date') }}" name="end_date" value="{{ date('Y-m-d', strtotime('+30 days')) }}" :required="true" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.field_reason') }}" name="reason_details" rows="3" placeholder="{{ __('hrms.pip.placeholder_reason') }}" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.field_support') }}" name="support_provided" rows="2" placeholder="{{ __('hrms.pip.placeholder_support') }}" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.field_consequences') }}" name="consequences" rows="2" placeholder="{{ __('hrms.pip.placeholder_consequences') }}" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.field_initial_notes') }}" name="initial_notes" rows="2" placeholder="{{ __('hrms.pip.placeholder_initial_notes') }}" />
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold px-4">{{ __('hrms.pip.btn_initiate_pip') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- ADD CATEGORY MODAL -->
<x-ui.modal id="createCategoryModal" title="<i class='feather-tag me-1.5 text-primary'></i> {{ __('hrms.pip.modal_add_category_title') }}" size="md" :showFooter="false">
    <form action="{{ route('hrms.pip.category.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.pip.field_category_name') }}" name="name" placeholder="{{ __('hrms.pip.placeholder_category_name') }}" :required="true" />
        </div>
        <div class="mb-0">
            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.field_description') }}" name="description" rows="3" placeholder="{{ __('hrms.pip.placeholder_category_desc') }}" />
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold px-4">{{ __('hrms.pip.btn_save_category') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- ADD POLICY TEMPLATE MODAL -->
<x-ui.modal id="createTemplateModal" title="<i class='feather-file-text me-1.5 text-primary'></i> {{ __('hrms.pip.modal_add_template_title') }}" size="md" :showFooter="false">
    <form action="{{ route('hrms.pip.template.store') }}" method="POST" novalidate id="createTemplateForm">
        @csrf
        <div class="mb-3">
            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.pip.field_template_name') }}" name="name" placeholder="{{ __('hrms.pip.placeholder_template_name') }}" :required="true" />
        </div>
        <div class="mb-3">
            <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('hrms.pip.field_duration_days') }}" name="duration_days" value="30" :required="true" min="7" max="180" />
        </div>
        <div class="mb-3">
            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.pip.field_checkin_freq') }}" name="checkin_frequency" :required="true">
                <option value="weekly">{{ __('hrms.pip.freq_weekly_simple') }}</option>
                <option value="biweekly">{{ __('hrms.pip.freq_biweekly_simple') }}</option>
                <option value="monthly">{{ __('hrms.pip.freq_monthly_simple') }}</option>
            </x-ui.odoo-form-ui>
        </div>
        <div class="mb-0">
            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.field_description') }}" name="description" rows="3" placeholder="{{ __('hrms.pip.placeholder_template_desc') }}" />
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold px-4">{{ __('hrms.pip.btn_save_template') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<x-ui.confirmation-modal />

@push('scripts')
<script>
    $(document).ready(function() {
        $('#createPipModal, #createCategoryModal, #createTemplateModal').each(function() {
            if ($(this).parent().get(0) !== document.body) {
                $(this).appendTo(document.body);
            }
        });

        // Category Delete confirmation handler
        $(document).on('click', '.btn-delete-cat', function(e) {
            e.preventDefault();
            const id = $(this).data('cat-id');
            const name = $(this).data('cat-name') || '';
            const confirmMsg = @json(__('hrms.pip.confirm_delete_category', ['name' => ':name'])).replace(':name', name);
            confirmAction(confirmMsg, function() {
                const form = document.getElementById('deleteCatForm' + id);
                if (form) form.submit();
            }, { title: @json(__('hrms.pip.delete_category_modal_title')), confirmText: @json(__('hrms.pip.delete_confirm_btn')), variant: 'danger' });
        });

        // Policy Template Delete confirmation handler
        $(document).on('click', '.btn-delete-tmpl', function(e) {
            e.preventDefault();
            const id = $(this).data('tmpl-id');
            confirmAction(@json(__('hrms.pip.confirm_delete_template')), function() {
                const form = document.getElementById('deleteTmplForm' + id);
                if (form) form.submit();
            }, { title: @json(__('hrms.pip.confirm_delete_template')), confirmText: @json(__('hrms.pip.delete_confirm_btn')), variant: 'danger' });
        });

        // Instant Auto Search without pressing Enter key (for Plans and Categories)
        let pipSearchDebounceTimer;
        $(document).on('input', '#pipSearchInput, #catSearchInput', function() {
            const form = $(this).closest('form');
            clearTimeout(pipSearchDebounceTimer);
            pipSearchDebounceTimer = setTimeout(function() {
                form.submit();
            }, 450);
        });

        function calculateEndDate(startDateStr, durationDays) {
            if (!startDateStr) return '';
            const parts = startDateStr.split('-');
            if (parts.length !== 3) return '';
            const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            d.setDate(d.getDate() + parseInt(durationDays || 30));
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        }

        // Policy Template auto-fill: when template selected, update End Date & Check-in Frequency
        $(document).on('change', '#pipPolicyTemplate', function() {
            const selected = $(this).find('option:selected');
            const duration = selected.data('duration') || 30;
            const frequency = selected.data('frequency');

            const startVal = $('input[name="start_date"]').val() || '{{ date("Y-m-d") }}';
            $('input[name="end_date"]').val(calculateEndDate(startVal, duration));

            if (frequency) {
                const freq = $('#pipCheckinFreq');
                freq.find('option').removeAttr('selected');
                freq.val(frequency).trigger('change');
            }
        });

        // When start_date changes, ensure end_date is updated if template is selected or if end_date <= start_date
        $(document).on('change', 'input[name="start_date"]', function() {
            const startVal = $(this).val();
            const selected = $('#pipPolicyTemplate').find('option:selected');
            const duration = selected.data('duration') || 30;
            const currentEndVal = $('input[name="end_date"]').val();

            if (selected.val() || !currentEndVal || currentEndVal <= startVal) {
                $('input[name="end_date"]').val(calculateEndDate(startVal, duration));
            }
        });
    });

    $(document).on('show.bs.modal', '.modal', function () {
        if ($(this).parent().get(0) !== document.body) {
            $(this).appendTo(document.body);
        }
    });
</script>
@endpush
@endsection
