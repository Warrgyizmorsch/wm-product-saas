@extends('layouts.duralux')

@section('title', __('hrms.kra_kpi.title') . ' | ' . config('app.name', 'HRMS'))
@section('page-title', __('hrms.kra_kpi.title'))
@section('breadcrumb', 'HRMS / ' . __('hrms.kra_kpi.performance') . ' / ' . __('hrms.kra_kpi.breadcrumb_kra_kpi'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        @if($isHrOrAdmin)
            <x-ui.button variant="primary" icon="feather-user-check" data-bs-toggle="modal" data-bs-target="#assignTemplateModal" class="fw-bold">
                {{ __('hrms.kra_kpi.assign_goal_sheets') }}
            </x-ui.button>
        @endif
    </div>
@endsection

@push('styles')
<style>
    .avatar-initials {
        width: 36px;
        height: 36px;
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
    .kra-color-circle {
        width: 30px !important;
        height: 30px !important;
        border-radius: 50% !important;
        border: 2px solid #cbd5e1 !important;
        padding: 0 !important;
        cursor: pointer;
        outline: none;
        background: transparent;
        flex-shrink: 0;
    }
    .kra-color-circle::-webkit-color-swatch-wrapper {
        padding: 0;
        border-radius: 50%;
    }
    .kra-color-circle::-webkit-color-swatch {
        border: none;
        border-radius: 50%;
    }
    .kra-color-circle::-moz-color-swatch {
        border: none;
        border-radius: 50%;
    }

    /* Single-line text truncation with ellipsis (...) for selects & inputs */
    .odoo-table-select,
    select.odoo-table-select,
    select.odoo-form-control,
    .odoo-form-control select,
    .kpi-select-wrap select,
    .kra-select-wrap select {
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        padding-right: 24px !important;
        max-width: 100% !important;
    }

    /* Select2 container and single selection truncation */
    .select2-container {
        max-width: 100% !important;
        width: 100% !important;
    }
    .select2-container--bootstrap-5 .select2-selection--single {
        position: relative !important;
        padding-right: 24px !important;
        height: 30px !important;
        min-height: 30px !important;
        display: flex !important;
        align-items: center !important;
        box-sizing: border-box !important;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        padding-left: 0 !important;
        padding-right: 16px !important;
        font-size: 13px !important;
        color: #212529 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        display: block !important;
        max-width: 100% !important;
        line-height: normal !important;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
        position: absolute !important;
        top: 50% !important;
        right: 0 !important;
        transform: translateY(-50%) !important;
        width: 20px !important;
        height: 20px !important;
    }

    /* Keep dropdown menu results showing complete text with wrapping */
    .select2-container--bootstrap-5 .select2-results__option {
        white-space: normal !important;
        word-break: break-word !important;
        line-height: 1.35 !important;
        padding: 8px 12px !important;
        font-size: 13px !important;
    }

    /* KRA & KPI Library Unified Panel & Modern Styling */
    .kra-card-item {
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        border: 1px solid #e5e7eb !important;
    }
    .kra-card-item:hover {
        background-color: #ffffff !important;
        border-color: #cbd5e1 !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }
    .kra-card-item.active-kra {
        background-color: rgba(var(--bs-primary-rgb), 0.06) !important;
        border-color: var(--bs-primary) !important;
        box-shadow: 0 2px 8px rgba(var(--bs-primary-rgb), 0.15) !important;
    }
    .kra-kpi-divider {
        border-right: 1px solid #e5e7eb !important;
    }
    @media (max-width: 767.98px) {
        .kra-kpi-divider {
            border-right: none !important;
            border-bottom: 1px solid #e5e7eb !important;
        }
    }

    /* Custom Slim Scrollbars for KRA & KPI Containers */
    .kra-custom-scroll,
    .kpi-table-scroll {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    .kra-custom-scroll::-webkit-scrollbar,
    .kpi-table-scroll::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    .kra-custom-scroll::-webkit-scrollbar-track,
    .kpi-table-scroll::-webkit-scrollbar-track {
        background: transparent;
    }
    .kra-custom-scroll::-webkit-scrollbar-thumb,
    .kpi-table-scroll::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 4px;
    }
    .kra-custom-scroll::-webkit-scrollbar-thumb:hover,
    .kpi-table-scroll::-webkit-scrollbar-thumb:hover {
        background-color: #94a3b8;
    }

    /* Sticky Header for KPI Table inside Scrollable Container */
    .kpi-table-scroll thead th {
        position: sticky;
        top: 0;
        background-color: #f8fafc !important;
        z-index: 2;
        box-shadow: inset 0 -1px 0 #e5e7eb;
    }

    /* Table wrap text & scrollbar prevention in index */
    .table-scorecards-wrap .table-responsive {
        overflow-x: hidden !important;
        padding: 0 !important;
    }
    .table-scorecards-wrap table.table {
        table-layout: fixed !important;
        width: 100% !important;
    }
    .table-scorecards-wrap th,
    .table-scorecards-wrap td {
        white-space: normal !important;
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        vertical-align: middle;
    }
    .table-scorecards-wrap th.actions-col,
    .table-scorecards-wrap td.actions-col {
        white-space: nowrap !important;
        text-align: right !important;
        word-wrap: normal !important;
        overflow-wrap: normal !important;
        padding-right: 12px !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">

    @if(session('success'))
        <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="mb-4">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
            {{ session('error') }}
        </x-ui.alert>
    @endif

    @if($errors->any())
        <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        @php
            $mainTabs = [];
            if ($isHrOrAdmin) {
                $mainTabs[] = ['id' => 'tab-scorecards', 'label' => __('hrms.kra_kpi.tab_all_scorecards'), 'active' => ($activeTab === 'plans'), 'icon' => 'feather-list'];
                $mainTabs[] = ['id' => 'tab-cycles', 'label' => __('hrms.kra_kpi.tab_appraisal_cycles'), 'active' => ($activeTab === 'cycles'), 'icon' => 'feather-refresh-cw'];
                $mainTabs[] = ['id' => 'tab-library', 'label' => __('hrms.kra_kpi.tab_library'), 'active' => ($activeTab === 'library'), 'icon' => 'feather-database'];
                $mainTabs[] = ['id' => 'tab-templates', 'label' => __('hrms.kra_kpi.tab_role_templates'), 'active' => ($activeTab === 'templates'), 'icon' => 'feather-layers'];
                if ($myPlan) {
                    $mainTabs[] = ['id' => 'tab-mygoals', 'label' => __('hrms.kra_kpi.tab_my_active_goals'), 'active' => ($activeTab === 'myplan'), 'icon' => 'feather-target'];
                }
            } else {
                if ($myPlan) {
                    $mainTabs[] = ['id' => 'tab-mygoals', 'label' => __('hrms.kra_kpi.tab_my_active_goals'), 'active' => ($activeTab === 'myplan'), 'icon' => 'feather-target'];
                }
                $mainTabs[] = ['id' => 'tab-scorecards', 'label' => __('hrms.kra_kpi.tab_my_scorecards'), 'active' => ($activeTab === 'plans' || !$myPlan), 'icon' => 'feather-list'];
            }
        @endphp

        <!-- Navigation Tabs & Right Toolbar (Search, Sort, Filter at Right Corner) -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 border-bottom pb-2">
            <!-- Left Common UI Horizontal Tabs -->
            <div class="flex-grow-1" style="min-width: 0;">
                <x-ui.horizontal-tabs id="kraTabs" :tabs="$mainTabs" />
            </div>

            <!-- Right Toolbar: Search, Sort, Filter aligned at Right Corner (Standard Platform UI) -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <!-- Search Input Form with Instant Submission -->
                <form method="GET" action="{{ route('hrms.kra-kpi.index') }}" id="kraSearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 250px; height: 36px !important; box-sizing: border-box !important;">
                    <input type="hidden" name="active_tab" value="{{ $activeTab }}">
                    <input type="hidden" name="filter_cycle" value="{{ request('filter_cycle') }}">
                    <input type="hidden" name="filter_status" value="{{ request('filter_status') }}">
                    <input type="hidden" name="filter_department" value="{{ request('filter_department') }}">
                    <input type="hidden" name="sort" value="{{ request('sort', 'newest') }}">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input type="text" name="search" id="kraSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.kra_kpi.search_placeholder') }}" value="{{ request('search') }}" autocomplete="off" style="box-shadow: none; height: 100%; outline: none;">
                </form>

                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort', 'newest') === 'newest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}">
                        <span>{{ __('hrms.kra_kpi.sort_newest') }}</span>
                        @if(request('sort', 'newest') === 'newest') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'oldest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'oldest']) }}">
                        <span>{{ __('hrms.kra_kpi.sort_oldest') }}</span>
                        @if(request('sort') === 'oldest') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'score_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'score_desc']) }}">
                        <span>{{ __('hrms.kra_kpi.sort_score_desc') }}</span>
                        @if(request('sort') === 'score_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'score_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'score_asc']) }}">
                        <span>{{ __('hrms.kra_kpi.sort_score_asc') }}</span>
                        @if(request('sort') === 'score_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <x-ui.filter :label="__('hrms.common.filter')">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                    <form method="GET" action="{{ route('hrms.kra-kpi.index') }}">
                        <input type="hidden" name="active_tab" value="{{ $activeTab }}">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.kra_kpi.filter_appraisal_cycle') }}</label>
                            <x-ui.odoo-form-ui type="select" name="filter_cycle">
                                <option value="">{{ __('hrms.kra_kpi.all_cycles') }}</option>
                                @foreach($cycles as $c)
                                    <option value="{{ $c->id }}" @selected(request('filter_cycle') == $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.common.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="filter_status">
                                <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                <option value="overdue" @selected(request('filter_status') === 'overdue')>{{ __('hrms.kra_kpi.status_overdue') }}</option>
                                <option value="draft" @selected(request('filter_status') === 'draft')>{{ __('hrms.kra_kpi.status_draft') }}</option>
                                <option value="submitted" @selected(request('filter_status') === 'submitted')>{{ __('hrms.kra_kpi.status_goals_submitted') }}</option>
                                <option value="approved" @selected(request('filter_status') === 'approved')>{{ __('hrms.kra_kpi.status_goals_active') }}</option>
                                <option value="self_reviewed" @selected(request('filter_status') === 'self_reviewed')>{{ __('hrms.kra_kpi.status_self_reviewed') }}</option>
                                <option value="manager_reviewed" @selected(request('filter_status') === 'manager_reviewed')>{{ __('hrms.kra_kpi.status_completed_review') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.common.department') }}</label>
                            <x-ui.odoo-form-ui type="select" name="filter_department">
                                <option value="">{{ __('hrms.common.all_departments') }}</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" @selected(request('filter_department') == $dept->id)>{{ $dept->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4 pt-2 border-top">
                            <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.kra_kpi.apply_filters') }}</x-ui.button>
                            <x-ui.button href="{{ route('hrms.kra-kpi.index', ['active_tab' => $activeTab]) }}" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.common.reset') }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.filter>
            </div>
        </div>

        <!-- TABS CONTENT AREA -->
        <div class="tab-content" id="kraTabsContent">

            <!-- TAB 1: ALL SCORECARDS & APPRAISALS -->
            <div class="tab-pane fade {{ $activeTab === 'plans' ? 'show active' : '' }}" id="tab-scorecards" role="tabpanel">
                <div class="table-scorecards-wrap">
                    <x-ui.table hoverable>
                        <thead>
                            <tr>
                                <th style="width: 23%;">{{ __('hrms.kra_kpi.th_employee_role') }}</th>
                                <th style="width: 13%;">{{ __('hrms.kra_kpi.th_plan_no') }}</th>
                                <th style="width: 22%;">{{ __('hrms.kra_kpi.th_appraisal_cycle') }}</th>
                                <th style="width: 11%;">{{ __('hrms.kra_kpi.th_goals_count') }}</th>
                                <th style="width: 10%;">{{ __('hrms.kra_kpi.th_score_grade') }}</th>
                                <th style="width: 11%;">{{ __('hrms.kra_kpi.th_stage_status') }}</th>
                                <th style="width: 10%;" class="text-end text-nowrap actions-col">{{ __('hrms.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($allPlans as $plan)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-initials">
                                                {{ strtoupper(substr($plan->employee->full_name ?? 'EM', 0, 2)) }}
                                            </div>
                                            <div class="text-break" style="min-width: 0;">
                                                <div class="fw-bold text-dark fs-13 text-wrap">{{ $plan->employee->full_name ?? __('hrms.common.employee') }}</div>
                                                <div class="small text-muted fs-11 text-wrap lh-sm">
                                                    {{ $plan->employee->designation->name ?? __('hrms.kra_kpi.designation_na') }} &bull; {{ $plan->employee->department->name ?? __('hrms.kra_kpi.dept_general') }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="{{ route('hrms.kra-kpi.show', $plan->id) }}" class="fw-bold text-primary text-decoration-none">
                                            {{ $plan->plan_number }}
                                        </a>
                                        <div class="small text-muted fs-11">{{ $plan->created_at->format('d M Y') }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark fs-12 text-wrap mb-1 lh-sm">{{ $plan->appraisalCycle->name ?? __('hrms.common.na') }}</div>
                                        <x-ui.badge variant="secondary" soft class="fs-10 text-uppercase">{{ $plan->appraisalCycle->period_type ? ucfirst(str_replace('_', ' ', $plan->appraisalCycle->period_type)) : __('hrms.kra_kpi.period_annual') }}</x-ui.badge>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ __('hrms.kra_kpi.kpis_count', ['count' => $plan->items->count()]) }}</div>
                                        <div class="small text-muted fs-11">{{ __('hrms.kra_kpi.weight_label', ['weight' => $plan->total_weightage]) }}</div>
                                    </td>
                                    <td>
                                        @if($plan->final_score !== null && in_array($plan->status, ['self_reviewed', 'manager_reviewed']))
                                            @php
                                                $scoreVariant = $plan->final_score >= 90 ? 'success' : ($plan->final_score >= 60 ? 'primary' : 'danger');
                                            @endphp
                                            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                <span class="fw-extrabold fs-13 text-{{ $scoreVariant }}">
                                                    {{ $plan->final_score }}%
                                                </span>
                                                <x-ui.badge variant="{{ $scoreVariant }}" soft class="fs-10">
                                                    {{ $plan->final_grade }}
                                                </x-ui.badge>
                                            </div>
                                        @else
                                            <span class="text-muted fs-12">{{ __('hrms.kra_kpi.in_progress') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $statusBadges = [
                                                'draft' => ['secondary', __('hrms.kra_kpi.status_draft_goals')],
                                                'submitted' => ['info', __('hrms.kra_kpi.status_goals_submitted')],
                                                'approved' => ['primary', __('hrms.kra_kpi.status_goals_active')],
                                                'self_reviewed' => ['warning', __('hrms.kra_kpi.status_self_reviewed')],
                                                'manager_reviewed' => ['success', __('hrms.kra_kpi.status_completed')],
                                            ];
                                            $st = $statusBadges[$plan->status] ?? ['secondary', $plan->status];
                                        @endphp
                                        <x-ui.badge variant="{{ $st[0] }}" soft class="text-nowrap">
                                            {{ $st[1] }}
                                        </x-ui.badge>
                                        @if($plan->getOverdueStatus())
                                            <div class="mt-1">
                                                <x-ui.badge variant="danger" soft class="fs-10 text-nowrap">
                                                    <i class="feather-alert-circle me-1"></i>{{ $plan->getOverdueStatus() }}
                                                </x-ui.badge>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap actions-col">
                                        <div class="d-inline-flex justify-content-end align-items-center gap-1">
                                            <x-ui.icon-btn href="{{ route('hrms.kra-kpi.show', $plan->id) }}" icon="feather-eye" variant="soft-primary" size="sm" :title="__('hrms.kra_kpi.view_scorecard')" />
                                            @if($isHrOrAdmin)
                                                <form method="POST" action="{{ route('hrms.kra-kpi.plan.destroy', $plan->id) }}" id="deletePlanForm_{{ $plan->id }}" class="d-inline m-0 p-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" :title="__('hrms.kra_kpi.delete_scorecard')" onclick="confirmAction({ title: '{{ addslashes(__('hrms.kra_kpi.delete_scorecard')) }}', message: '{{ addslashes(__('hrms.kra_kpi.confirm_delete_scorecard', ['plan' => $plan->plan_number])) }}', variant: 'danger', confirmText: '{{ addslashes(__('hrms.common.delete')) }}' }, function() { document.getElementById('deletePlanForm_{{ $plan->id }}').submit(); })" />
                                                </form>
                                            @endif
                                            @if($isHrOrAdmin && in_array($plan->status, ['manager_reviewed']) && $plan->final_score !== null && $plan->final_score < 60 && !$plan->pip_triggered)
                                                 <form method="POST" action="{{ route('hrms.kra-kpi.trigger-pip', $plan->id) }}" id="pipTriggerForm_{{ $plan->id }}" class="d-inline m-0 p-0">
                                                     @csrf
                                                     <x-ui.icon-btn type="button" icon="feather-alert-octagon" variant="soft-danger" size="sm" :title="__('hrms.kra_kpi.bridge_to_pip')" onclick="confirmAction({ title: '{{ addslashes(__('hrms.kra_kpi.initiate_pip')) }}', message: '{{ addslashes(__('hrms.kra_kpi.confirm_initiate_pip')) }}', variant: 'danger', confirmText: '{{ addslashes(__('hrms.kra_kpi.initiate_pip')) }}' }, function() { document.getElementById('pipTriggerForm_{{ $plan->id }}').submit(); })" />
                                                 </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="feather-inbox fs-32 d-block mb-2 text-muted"></i>
                                        <h6 class="fw-bold mb-1">{{ __('hrms.kra_kpi.no_scorecards_found') }}</h6>
                                        <p class="fs-12 mb-0">{{ __('hrms.kra_kpi.no_scorecards_desc') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-ui.table>
                </div>

                <!-- Custom Pagination Component -->
                @if($allPlans->hasPages())
                    <div class="mt-3">
                        <x-ui.pagination 
                            :currentPage="$allPlans->currentPage()" 
                            :totalPages="$allPlans->lastPage()" 
                            :totalResults="$allPlans->total()" 
                            :perPage="$allPlans->perPage()" 
                        />
                    </div>
                @endif
            </div>

            @if($isHrOrAdmin)
            <!-- TAB 2: APPRAISAL CYCLES -->
            <div class="tab-pane fade {{ $activeTab === 'cycles' ? 'show active' : '' }}" id="tab-cycles" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">{{ __('hrms.kra_kpi.appraisal_cycles') }}</h6>
                        <small class="text-muted">{{ __('hrms.kra_kpi.cycles_subtitle') }}</small>
                    </div>
                    <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createCycleModal" class="fw-bold">
                        {{ __('hrms.kra_kpi.create_appraisal_cycle') }}
                    </x-ui.button>
                </div>

                <div class="table-responsive">
                    <x-ui.table hoverable>
                        <thead>
                            <tr>
                                <th style="width: 26%;">{{ __('hrms.kra_kpi.th_cycle_name') }}</th>
                                <th style="width: 11%;">{{ __('hrms.kra_kpi.th_frequency') }}</th>
                                <th style="width: 14%;">{{ __('hrms.kra_kpi.th_start_date') }}</th>
                                <th style="width: 14%;">{{ __('hrms.kra_kpi.th_end_date') }}</th>
                                <th style="width: 14%;">{{ __('hrms.kra_kpi.th_review_due') }}</th>
                                <th style="width: 11%;">{{ __('hrms.kra_kpi.th_scorecards') }}</th>
                                <th style="width: 10%;" class="text-end text-nowrap">{{ __('hrms.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cycles as $cycle)
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark fs-13">{{ $cycle->name }}</div>
                                        @if($activeCycle?->id === $cycle->id)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle fs-10 mt-1">{{ __('hrms.kra_kpi.current_active_cycle') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <x-ui.badge variant="secondary" soft class="fs-11 text-uppercase">{{ $cycle->period_type ? ucfirst(str_replace('_', ' ', $cycle->period_type)) : __('hrms.kra_kpi.period_monthly') }}</x-ui.badge>
                                    </td>
                                    <td>
                                        <span class="text-dark fs-13">{{ $cycle->start_date ? $cycle->start_date->format('d M Y') : '—' }}</span>
                                    </td>
                                    <td>
                                        <span class="text-dark fs-13">{{ $cycle->end_date ? $cycle->end_date->format('d M Y') : '—' }}</span>
                                    </td>
                                    <td>
                                        @if($cycle->self_review_deadline)
                                            <span class="fw-semibold text-dark fs-13">{{ $cycle->self_review_deadline->format('d M Y') }}</span>
                                            @if($cycle->self_review_deadline->isPast())
                                                <small class="d-block text-danger fs-10">{{ __('hrms.kra_kpi.review_ended') }}</small>
                                            @else
                                                <small class="d-block text-muted fs-10">{{ $cycle->self_review_deadline->diffForHumans() }}</small>
                                            @endif
                                        @else
                                            <span class="text-muted fs-12">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border fs-12 fw-semibold">{{ __('hrms.kra_kpi.scorecards_count', ['count' => $cycle->goalPlans->count()]) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex align-items-center justify-content-end gap-1">
                                            <x-ui.icon-btn type="button" icon="feather-edit" variant="soft-primary" size="sm" :title="__('hrms.kra_kpi.edit_cycle')" data-bs-toggle="modal" data-bs-target="#editCycleModal_{{ $cycle->id }}" />
                                            <form method="POST" action="{{ route('hrms.kra-kpi.cycle.destroy', $cycle->id) }}" id="deleteCycleForm_{{ $cycle->id }}" class="d-inline m-0 p-0">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" :title="__('hrms.kra_kpi.delete_cycle')" onclick="confirmAction({ title: '{{ addslashes(__('hrms.kra_kpi.delete_cycle')) }}', message: '{{ addslashes(__('hrms.kra_kpi.confirm_delete_cycle', ['name' => $cycle->name])) }}', variant: 'danger', confirmText: '{{ addslashes(__('hrms.kra_kpi.delete_cycle')) }}' }, function() { document.getElementById('deleteCycleForm_{{ $cycle->id }}').submit(); })" />
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="feather-calendar fs-32 d-block mb-2"></i>
                                        <h6>{{ __('hrms.kra_kpi.no_cycles_found') }}</h6>
                                        <p class="small mb-0">{{ __('hrms.kra_kpi.no_cycles_desc') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-ui.table>
                </div>
            </div>

            <!-- TAB 3: KRA & KPI MASTER LIBRARY -->
            <div class="tab-pane fade {{ $activeTab === 'library' ? 'show active' : '' }}" id="tab-library" role="tabpanel">
                <div class="card border rounded-3 bg-white shadow-sm overflow-hidden mb-0">
                    <div class="row g-0">
                        <!-- Left: KRA Focus Areas with Vertical Divider -->
                        <div class="col-lg-4 col-md-5 p-3 p-lg-4 kra-kpi-divider bg-white d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="fw-bold text-dark mb-0">{{ __('hrms.kra_kpi.kra_focus_areas') }}</h6>
                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5 fs-11" id="kraCountBadge">{{ $kraCategories->count() }}</span>
                                </div>
                                <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createKraModal" class="fw-bold text-nowrap">
                                    {{ __('hrms.kra_kpi.add_kra') }}
                                </x-ui.button>
                            </div>

                            <!-- KRA Searchbar (Common Platform UI style) -->
                            <div class="d-flex align-items-center bg-light border rounded px-3 py-1 mb-3" style="height: 36px !important; box-sizing: border-box !important;">
                                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                <input type="text" id="kraSearchInputLocal" class="w-100 border-0 bg-transparent p-0 fs-13 text-dark" placeholder="{{ __('hrms.kra_kpi.search_focus_areas') }}" autocomplete="off" style="box-shadow: none; height: 100%; outline: none;">
                                <button type="button" id="kraClearSearchBtn" class="btn btn-link p-0 text-muted ms-1 d-none" style="text-decoration: none; font-size: 13px; line-height: 1;" :title="__('hrms.kra_kpi.clear_search')">
                                    <i class="feather-x"></i>
                                </button>
                            </div>

                            <div class="d-flex flex-column gap-2 flex-grow-1 kra-custom-scroll pe-1" id="kraListContainer" style="max-height: 480px; overflow-y: auto;">
                                @forelse($kraCategories as $kra)
                                    <div class="kra-card-item px-3 py-2.5 rounded-2 d-flex justify-content-between align-items-center bg-light"
                                         data-kra-name="{{ strtolower($kra->name) }}"
                                         data-kra-id="{{ $kra->id }}"
                                         title="{{ __('hrms.kra_kpi.click_to_filter_kpi', ['name' => $kra->name]) }}">
                                        <div class="pe-3 flex-grow-1" style="min-width: 0;">
                                            <span class="fw-bold text-dark fs-13 kra-title-text" style="word-break: break-word; line-height: 1.45; display: block;">{{ $kra->name }}</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 pe-1 flex-shrink-0 ms-auto">
                                            <x-ui.badge variant="primary" soft>{{ __('hrms.kra_kpi.kpis_count', ['count' => $kra->kpi_masters_count]) }}</x-ui.badge>
                                            @if($isHrOrAdmin)
                                                <form method="POST" action="{{ route('hrms.kra-kpi.kra-category.destroy', $kra->id) }}" id="deleteKraForm_{{ $kra->id }}" class="d-inline m-0 p-0 ms-1" onclick="event.stopPropagation();">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" :title="__('hrms.kra_kpi.delete_kra_focus')" onclick="event.stopPropagation(); confirmAction({ title: '{{ addslashes(__('hrms.kra_kpi.delete_kra_title')) }}', message: '{{ addslashes(__('hrms.kra_kpi.confirm_delete_kra')) }}', variant: 'danger', confirmText: '{{ addslashes(__('hrms.common.delete')) }}' }, function() { document.getElementById('deleteKraForm_{{ $kra->id }}').submit(); })" />
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center py-4 border rounded-2 bg-light">
                                        <i class="feather-layers text-muted fs-20 d-block mb-1"></i>
                                        <span class="text-muted fs-12 d-block mb-2">{{ __('hrms.kra_kpi.no_kra_areas') }}</span>
                                        <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createKraModal" class="fw-bold">
                                            {{ __('hrms.kra_kpi.add_first_kra') }}
                                        </x-ui.button>
                                    </div>
                                @endforelse

                                <!-- No search results state for KRA -->
                                <div id="noKraFound" class="text-center py-4 border rounded-2 bg-light d-none">
                                    <i class="feather-search text-muted fs-18 d-block mb-1"></i>
                                    <span class="text-muted fs-12">{{ __('hrms.kra_kpi.no_kra_match') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right: KPI Master Library Table -->
                        <div class="col-lg-8 col-md-7 p-3 p-lg-4 bg-white d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="fw-bold text-dark mb-0">{{ __('hrms.kra_kpi.kpi_master_metrics') }}</h6>
                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5 fs-11" id="kpiCountBadge">{{ $kpiMasters->count() }}</span>
                                    <!-- Active Filter Pill if filtered by KRA click -->
                                    <span id="activeKraFilterBadge" class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 fs-11 d-none align-items-center gap-1">
                                        {{ __('hrms.kra_kpi.filtered_by') }} <span id="activeKraFilterName" class="fw-bold"></span>
                                        <i class="feather-x ms-1 cursor-pointer" id="resetKraFilterBtn" :title="__('hrms.kra_kpi.clear_kra_filter')" style="cursor: pointer;"></i>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <!-- KPI Searchbar (Common Platform UI style) -->
                                    <div class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 220px; height: 36px !important; box-sizing: border-box !important;">
                                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                        <input type="text" id="kpiSearchInputLocal" class="w-100 border-0 bg-transparent p-0 fs-13 text-dark" placeholder="{{ __('hrms.kra_kpi.search_kpi_metrics') }}" autocomplete="off" style="box-shadow: none; height: 100%; outline: none;">
                                        <button type="button" id="kpiClearSearchBtn" class="btn btn-link p-0 text-muted ms-1 d-none" style="text-decoration: none; font-size: 13px; line-height: 1;" :title="__('hrms.kra_kpi.clear_search')">
                                            <i class="feather-x"></i>
                                        </button>
                                    </div>
                                    <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createKpiMasterModal" class="fw-bold text-nowrap">
                                        {{ __('hrms.kra_kpi.add_metric') }}
                                    </x-ui.button>
                                </div>
                            </div>

                            <div class="table-responsive flex-grow-1 kpi-table-scroll" style="max-height: 480px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0" id="kpiMasterTable" style="table-layout: auto;">
                                    <thead>
                                        <tr class="text-muted fs-12 border-bottom">
                                            <th style="white-space: normal;">{{ __('hrms.kra_kpi.th_kpi_name') }}</th>
                                            <th style="white-space: normal;">{{ __('hrms.kra_kpi.th_kra_category') }}</th>
                                            <th style="white-space: normal;">{{ __('hrms.kra_kpi.th_direction_unit') }}</th>
                                            @if($isHrOrAdmin)
                                                <th class="text-end text-nowrap" style="width: 70px;">{{ __('hrms.common.action') }}</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody id="kpiMasterTableBody">
                                        @forelse($kpiMasters as $km)
                                            <tr class="kpi-master-row" 
                                                data-kpi-name="{{ strtolower($km->name) }}" 
                                                data-kra-name="{{ strtolower($km->kraCategory->name ?? 'general') }}" 
                                                data-kra-id="{{ $km->kra_category_id }}" 
                                                data-calc="{{ strtolower(str_replace('_', ' ', $km->calculation_type)) }}" 
                                                data-unit="{{ strtolower($km->unit) }}">
                                                <td class="fw-bold text-dark fs-12" style="white-space: normal; word-break: break-word; max-width: 200px;">{{ $km->name }}</td>
                                                <td style="white-space: normal;"><x-ui.badge variant="primary" soft>{{ $km->kraCategory->name ?? __('hrms.kra_kpi.dept_general') }}</x-ui.badge></td>
                                                <td style="white-space: normal;">
                                                    <div class="small text-dark text-capitalize">{{ str_replace('_', ' ', $km->calculation_type) }}</div>
                                                    <div class="text-muted fs-11">({{ $km->unit }})</div>
                                                </td>
                                                @if($isHrOrAdmin)
                                                    <td class="text-end">
                                                        <form method="POST" action="{{ route('hrms.kra-kpi.kpi-master.destroy', $km->id) }}" id="deleteKmForm_{{ $km->id }}" class="d-inline m-0 p-0">
                                                            @csrf
                                                            @method('DELETE')
                                                            <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" :title="__('hrms.kra_kpi.delete_kpi_metric')" onclick="confirmAction({ title: '{{ addslashes(__('hrms.kra_kpi.delete_master_kpi')) }}', message: '{{ addslashes(__('hrms.kra_kpi.confirm_delete_kpi')) }}', variant: 'danger', confirmText: '{{ addslashes(__('hrms.common.delete')) }}' }, function() { document.getElementById('deleteKmForm_{{ $km->id }}').submit(); })" />
                                                        </form>
                                                    </td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr id="emptyKpiRow">
                                                <td colspan="{{ $isHrOrAdmin ? '4' : '3' }}" class="text-center py-4 text-muted">{{ __('hrms.kra_kpi.no_kpi_in_library') }}</td>
                                            </tr>
                                        @endforelse
                                        <!-- No search results state for KPI -->
                                        <tr id="noKpiFoundRow" class="d-none">
                                            <td colspan="{{ $isHrOrAdmin ? '4' : '3' }}" class="text-center py-4 text-muted fs-12">
                                                <i class="feather-search text-muted fs-18 d-block mb-1"></i>
                                                {{ __('hrms.kra_kpi.no_kpi_match') }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: ROLE TEMPLATES -->
            <div class="tab-pane fade {{ $activeTab === 'templates' ? 'show active' : '' }}" id="tab-templates" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark mb-0">{{ __('hrms.kra_kpi.role_kpi_templates') }}</h6>
                    <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createTemplateModal" class="fw-bold">
                        {{ __('hrms.kra_kpi.create_template') }}
                    </x-ui.button>
                </div>
                <div class="row g-3">
                    @forelse($kpiTemplates as $tpl)
                        <div class="col-md-6 col-lg-4">
                            <div class="card p-3 h-100" style="border: 1px solid #d1d5db !important; border-radius: 10px; background: #ffffff; box-shadow: 0 1px 4px rgba(0,0,0,0.06);">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0">{{ $tpl->name }}</h6>
                                        <div class="small text-muted mt-0.5 fs-11">
                                            {{ __('hrms.kra_kpi.dept_label') }} {{ $tpl->department->name ?? __('hrms.common.all_departments') }} &bull; {{ __('hrms.kra_kpi.role_label') }} {{ $tpl->designation->name ?? __('hrms.kra_kpi.all_roles') }}
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <x-ui.badge variant="primary" soft>{{ __('hrms.kra_kpi.kpis_count', ['count' => $tpl->items->count()]) }}</x-ui.badge>
                                        @if($isHrOrAdmin)
                                            <x-ui.icon-btn type="button" icon="feather-edit" variant="soft-primary" size="sm" :title="__('hrms.kra_kpi.edit_role_template')" data-bs-toggle="modal" data-bs-target="#editTemplateModal_{{ $tpl->id }}" />
                                            <form method="POST" action="{{ route('hrms.kra-kpi.template.destroy', $tpl->id) }}" id="deleteTplForm_{{ $tpl->id }}" class="d-inline m-0 p-0">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" :title="__('hrms.kra_kpi.delete_role_template')" onclick="confirmAction({ title: '{{ addslashes(__('hrms.kra_kpi.delete_role_template')) }}', message: '{{ addslashes(__('hrms.kra_kpi.confirm_delete_template')) }}', variant: 'danger', confirmText: '{{ addslashes(__('hrms.common.delete')) }}' }, function() { document.getElementById('deleteTplForm_{{ $tpl->id }}').submit(); })" />
                                            </form>
                                        @endif
                                    </div>
                                </div>
                                <div class="border-top pt-2 mt-auto">
                                    <small class="text-muted d-block fs-11 mb-1">{{ __('hrms.kra_kpi.included_kpis') }}</small>
                                    <ul class="list-unstyled mb-2 fs-12">
                                        @foreach($tpl->items->take(4) as $ti)
                                            <li class="text-truncate text-muted">&bull; {{ $ti->title }} ({{ $ti->weightage }}%)</li>
                                        @endforeach
                                    </ul>
                                    @if($isHrOrAdmin)
                                        <button type="button" class="btn btn-sm btn-outline-primary w-100 fw-bold d-flex align-items-center justify-content-center gap-1.5 py-1" onclick="openAssignModalForTemplate({{ $tpl->id }}, {{ $tpl->department_id ? $tpl->department_id : 'null' }})">
                                            <i class="feather-user-check"></i> {{ __('hrms.kra_kpi.assign_to_employees') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center py-5 text-muted">
                            <i class="feather-layers fs-32 d-block mb-2"></i>
                            <h6>{{ __('hrms.kra_kpi.no_templates_found') }}</h6>
                        </div>
                    @endforelse
                </div>
            </div>
            @endif

            <!-- TAB 5: MY ACTIVE GOALS (ESS) -->
            @if($myPlan)
            <div class="tab-pane fade {{ $activeTab === 'myplan' ? 'show active' : '' }}" id="tab-mygoals" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">{{ $myPlan->appraisalCycle->name ?? __('hrms.kra_kpi.active_cycle_default') }}</h5>
                        <small class="text-muted">{{ __('hrms.kra_kpi.plan_number_label') }} <strong>{{ $myPlan->plan_number }}</strong> &bull; {{ __('hrms.kra_kpi.manager_label') }} {{ $myPlan->manager->full_name ?? __('hrms.kra_kpi.direct_manager') }}</small>
                    </div>
                    <div>
                        <x-ui.button variant="primary" size="sm" icon="feather-external-link" href="{{ route('hrms.kra-kpi.show', $myPlan->id) }}" class="fw-bold">
                            {{ __('hrms.kra_kpi.open_full_scorecard') }}
                        </x-ui.button>
                    </div>
                </div>

                <div class="row g-3">
                    @foreach($myPlan->items as $item)
                        <div class="col-md-6 col-lg-4">
                            <div class="card border shadow-sm rounded-3 h-100 p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <x-ui.badge variant="primary" soft>{{ $item->kraCategory->name ?? __('hrms.kra_kpi.dept_general') }}</x-ui.badge>
                                    <span class="fw-bold text-dark fs-12">{{ __('hrms.kra_kpi.weight_label', ['weight' => $item->weightage]) }}</span>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">{{ $item->title }}</h6>
                                <p class="text-muted fs-12 mb-3">{{ $item->description ?: __('hrms.kra_kpi.target_default_desc') }}</p>

                                <div class="bg-light p-2.5 rounded-2 d-flex justify-content-between align-items-center mt-auto">
                                    <div>
                                        <small class="text-muted d-block fs-10 text-uppercase">{{ __('hrms.kra_kpi.target_label') }}</small>
                                        <span class="fw-bold text-dark fs-13">{{ $item->target }} {{ $item->unit }}</span>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block fs-10 text-uppercase">{{ __('hrms.kra_kpi.actual_achieved') }}</small>
                                        <span class="fw-bold text-primary fs-13">{{ $item->actual !== null ? $item->actual . ' ' . $item->unit : __('hrms.kra_kpi.not_logged') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif



        </div>
    </div>
</div>

<!-- MODAL: ASSIGN TEMPLATE TO EMPLOYEES (WITH INTEGRATED PERIOD CREATION) -->
@if($isHrOrAdmin)
<x-ui.modal id="assignTemplateModal" :title="__('hrms.kra_kpi.modal_assign_title')" size="lg" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.assign-template') }}" id="assignGoalSheetForm">
        @csrf
        <input type="hidden" name="cycle_mode" id="cycleModeInput" value="{{ $cycles->isNotEmpty() ? 'existing' : 'new' }}">

        <!-- 1. Appraisal Review Period (Embedded with Existing vs + New Toggle) -->
        <div class="p-3 bg-light rounded-3 border mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <label class="form-label fw-bold text-dark fs-12 text-uppercase mb-0">
                    <i class="feather-calendar text-primary me-1"></i> {{ __('hrms.kra_kpi.appraisal_review_period_cycle') }} <span class="text-danger">*</span>
                </label>
                @if($cycles->isNotEmpty())
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-sm btn-primary fw-semibold" id="btnModeExisting" onclick="switchCycleMode('existing')">
                            {{ __('hrms.kra_kpi.existing_period') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" id="btnModeNew" onclick="switchCycleMode('new')">
                            {{ __('hrms.kra_kpi.create_new_period') }}
                        </button>
                    </div>
                @else
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11">{{ __('hrms.kra_kpi.first_period_setup') }}</span>
                @endif
            </div>

            <!-- Existing Period Selection -->
            @if($cycles->isNotEmpty())
                <div id="existingCycleSection" class="mt-2">
                    <x-ui.odoo-form-ui type="select" name="appraisal_cycle_id" id="assignAppraisalCycleSelect">
                        @foreach($cycles as $c)
                            <option value="{{ $c->id }}" {{ $activeCycle?->id == $c->id ? 'selected' : '' }}>
                                {{ $c->name }} ({{ $c->start_date ? $c->start_date->format('d M Y') : '' }} – {{ $c->end_date ? $c->end_date->format('d M Y') : '' }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
            @endif

            <!-- New Period On-The-Fly (Inline, No Modal over Modal!) -->
            <div id="newCycleSection" class="{{ $cycles->isNotEmpty() ? 'd-none' : '' }} mt-2">
                <div class="row g-2">
                    <div class="col-md-8">
                        <x-ui.odoo-form-ui type="input" :label="__('hrms.kra_kpi.period_cycle_name')" name="new_cycle_name" id="newCycleNameInput" placeholder="{{ __('hrms.kra_kpi.placeholder_period_name', ['month' => date('F Y')]) }}" :value="__('hrms.kra_kpi.default_period_name', ['month' => date('F Y')])" />
                    </div>
                    <div class="col-md-4">
                        <x-ui.odoo-form-ui type="select" :label="__('hrms.kra_kpi.frequency')" name="period_type" id="newCyclePeriodType">
                            <option value="monthly" selected>{{ __('hrms.kra_kpi.period_monthly') }}</option>
                            <option value="quarterly">{{ __('hrms.kra_kpi.period_quarterly') }}</option>
                            <option value="semi_annual">{{ __('hrms.kra_kpi.period_semi_annual') }}</option>
                            <option value="annual">{{ __('hrms.kra_kpi.period_annual') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="col-md-4">
                        <x-ui.odoo-form-ui type="input" :label="__('hrms.kra_kpi.start_date')" name="start_date" id="newCycleStartDate" inputType="date" :value="date('Y-m-01')" />
                    </div>
                    <div class="col-md-4">
                        <x-ui.odoo-form-ui type="input" :label="__('hrms.kra_kpi.end_date')" name="end_date" id="newCycleEndDate" inputType="date" :value="date('Y-m-t')" />
                    </div>
                    <div class="col-md-4">
                        <x-ui.odoo-form-ui type="input" :label="__('hrms.kra_kpi.review_due')" name="self_review_deadline" id="newCycleReviewDeadline" inputType="date" :value="date('Y-m-t')" />
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Goal Template & Target Employees -->
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.kra_kpi.kpi_template_pack')" name="kpi_template_id" id="assignKpiTemplateSelect">
                    <option value="">{{ __('hrms.kra_kpi.custom_blank_scorecard') }}</option>
                    @foreach($kpiTemplates as $tpl)
                        <option value="{{ $tpl->id }}">{{ $tpl->name }} ({{ __('hrms.kra_kpi.kpis_count', ['count' => $tpl->items->count()]) }})</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.kra_kpi.assignment_scope')" name="target_type" id="assignmentTargetType" :required="true">
                    <option value="department">{{ __('hrms.kra_kpi.scope_by_department') }}</option>
                    <option value="individual">{{ __('hrms.kra_kpi.scope_specific_employee') }}</option>
                    <option value="all">{{ __('hrms.kra_kpi.scope_all_employees') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-12" id="deptSelectWrap">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.kra_kpi.choose_department')" name="department_id" id="assignDepartmentSelect">
                    <option value="">{{ __('hrms.kra_kpi.select_department_placeholder') }}</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-12 d-none" id="empSelectWrap">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.kra_kpi.choose_employee')" name="employee_id" id="assignEmployeeSelect">
                    <option value="">{{ __('hrms.kra_kpi.select_employee_placeholder') }}</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id ?? ('#' . $emp->id) }})</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">{{ __('hrms.kra_kpi.btn_assign_goals') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- MODAL: CREATE APPRAISAL CYCLE -->
<x-ui.modal id="createCycleModal" :title="__('hrms.kra_kpi.modal_create_cycle_title')" size="lg" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.cycle.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-8">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.kra_kpi.cycle_name')" name="name" id="createCycleName" placeholder="{{ __('hrms.kra_kpi.placeholder_period_name', ['month' => date('F Y')]) }}" :value="__('hrms.kra_kpi.default_period_name', ['month' => date('F Y')])" :required="true" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.kra_kpi.frequency')" name="period_type" id="createCyclePeriodType">
                    <option value="monthly" selected>{{ __('hrms.kra_kpi.period_monthly') }}</option>
                    <option value="quarterly">{{ __('hrms.kra_kpi.period_quarterly') }}</option>
                    <option value="semi_annual">{{ __('hrms.kra_kpi.period_semi_annual') }}</option>
                    <option value="annual">{{ __('hrms.kra_kpi.period_annual') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.kra_kpi.start_date')" name="start_date" id="createCycleStartDate" inputType="date" :value="date('Y-m-01')" :required="true" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.kra_kpi.end_date')" name="end_date" id="createCycleEndDate" inputType="date" :value="date('Y-m-t')" :required="true" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.kra_kpi.review_due')" name="self_review_deadline" id="createCycleReviewDeadline" inputType="date" :value="date('Y-m-t')" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" :label="__('hrms.kra_kpi.notes_description')" name="description" rows="2" placeholder="{{ __('hrms.kra_kpi.placeholder_cycle_notes') }}" />
            </div>
        </div>
        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">{{ __('hrms.kra_kpi.btn_create_cycle') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- MODALS: EDIT APPRAISAL CYCLE -->
@foreach($cycles as $cycle)
<x-ui.modal id="editCycleModal_{{ $cycle->id }}" :title="__('hrms.kra_kpi.modal_edit_cycle_title', ['name' => $cycle->name])" size="lg" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.cycle.update', $cycle->id) }}">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-md-8">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.kra_kpi.cycle_name')" name="name" :value="$cycle->name" :required="true" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.kra_kpi.frequency')" name="period_type">
                    <option value="monthly" {{ $cycle->period_type === 'monthly' ? 'selected' : '' }}>{{ __('hrms.kra_kpi.period_monthly') }}</option>
                    <option value="quarterly" {{ $cycle->period_type === 'quarterly' ? 'selected' : '' }}>{{ __('hrms.kra_kpi.period_quarterly') }}</option>
                    <option value="semi_annual" {{ $cycle->period_type === 'semi_annual' ? 'selected' : '' }}>{{ __('hrms.kra_kpi.period_semi_annual') }}</option>
                    <option value="annual" {{ $cycle->period_type === 'annual' ? 'selected' : '' }}>{{ __('hrms.kra_kpi.period_annual') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.kra_kpi.start_date')" name="start_date" inputType="date" :value="$cycle->start_date ? $cycle->start_date->format('Y-m-d') : ''" :required="true" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.kra_kpi.end_date')" name="end_date" inputType="date" :value="$cycle->end_date ? $cycle->end_date->format('Y-m-d') : ''" :required="true" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.kra_kpi.review_due')" name="self_review_deadline" inputType="date" :value="$cycle->self_review_deadline ? $cycle->self_review_deadline->format('Y-m-d') : ''" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" :label="__('hrms.kra_kpi.notes_description')" name="description" rows="2" :value="$cycle->description" placeholder="{{ __('hrms.kra_kpi.placeholder_cycle_notes') }}" />
            </div>
        </div>
        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">{{ __('hrms.kra_kpi.btn_update_cycle') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
@endforeach

<!-- MODAL 3: CREATE KRA FOCUS CATEGORY (ODOO FORM UI) -->
<x-ui.modal id="createKraModal" :title="__('hrms.kra_kpi.modal_create_kra_title')" size="md" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.kra-category.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.kra_kpi.kra_name')" name="name" placeholder="{{ __('hrms.kra_kpi.placeholder_kra_name') }}" :required="true" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" :label="__('hrms.kra_kpi.description')" name="description" rows="3" placeholder="{{ __('hrms.kra_kpi.placeholder_kra_desc') }}" />
            </div>
        </div>
        <div class="mt-3 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">{{ __('hrms.kra_kpi.btn_save_kra') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- MODAL 4: CREATE KPI MASTER METRIC (ODOO FORM UI) -->
<x-ui.modal id="createKpiMasterModal" :title="__('hrms.kra_kpi.modal_create_kpi_title')" size="lg" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.kpi-master.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.kra_kpi.kpi_metric_name')" name="name" placeholder="{{ __('hrms.kra_kpi.placeholder_kpi_name') }}" :required="true" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.kra_kpi.kra_category')" name="kra_category_id" :required="true">
                    @foreach($kraCategories as $kra)
                        <option value="{{ $kra->id }}">{{ $kra->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.kra_kpi.unit_of_measure')" name="unit" :required="true">
                    <option value="percentage">{{ __('hrms.kra_kpi.unit_percentage') }}</option>
                    <option value="currency">{{ __('hrms.kra_kpi.unit_currency') }}</option>
                    <option value="number">{{ __('hrms.kra_kpi.unit_number') }}</option>
                    <option value="rating">{{ __('hrms.kra_kpi.unit_rating') }}</option>
                    <option value="boolean">{{ __('hrms.kra_kpi.unit_milestone') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.kra_kpi.direction')" name="calculation_type" :required="true">
                    <option value="higher_is_better">{{ __('hrms.kra_kpi.dir_higher') }}</option>
                    <option value="lower_is_better">{{ __('hrms.kra_kpi.dir_lower') }}</option>
                    <option value="milestone">{{ __('hrms.kra_kpi.dir_milestone') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <input type="hidden" name="default_target" value="100.00">
            <input type="hidden" name="default_weightage" value="20.00">
        </div>
        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">{{ __('hrms.kra_kpi.btn_add_to_library') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- MODAL 5: CREATE KPI TEMPLATE PACK (ODOO FORM UI) -->
<x-ui.modal id="createTemplateModal" title="{{ __('hrms.kra_kpi.modal_create_template_title') }}" size="xl" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.template.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.kra_kpi.template_name') }}" name="name" placeholder="{{ __('hrms.kra_kpi.placeholder_template_name') }}" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.kra_kpi.department') }}" name="department_id">
                    <option value="">{{ __('hrms.kra_kpi.all_departments') }}</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.kra_kpi.designation') }}" name="designation_id">
                    <option value="">{{ __('hrms.kra_kpi.all_designations') }}</option>
                    @foreach($designations as $desig)
                        <option value="{{ $desig->id }}">{{ $desig->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <div class="mt-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3 flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold text-dark mb-0">{{ __('hrms.kra_kpi.template_goals_heading') }}</h6>
                    <small class="text-muted">{{ __('hrms.kra_kpi.template_goals_desc_create') }}</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success-subtle text-success border border-success-subtle fs-12 px-2.5 py-1.5 fw-semibold weightage-total-badge" id="createTemplateWeightBadge">{{ __('hrms.kra_kpi.total_weight_label', ['weight' => 100]) }}</span>
                    <x-ui.button type="button" variant="light" size="sm" icon="feather-plus" id="addTemplateRowBtn" class="border fw-semibold">
                        {{ __('hrms.kra_kpi.add_goal_row') }}
                    </x-ui.button>
                </div>
            </div>
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 28%;">{{ __('hrms.kra_kpi.th_kpi_metric_title') }} <span class="text-danger">*</span></th>
                        <th style="width: 22%;">{{ __('hrms.kra_kpi.th_kra_focus') }}</th>
                        <th style="width: 18%;">{{ __('hrms.kra_kpi.th_direction') }}</th>
                        <th style="width: 12%;">{{ __('hrms.kra_kpi.th_unit') }}</th>
                        <th style="width: 9%;">{{ __('hrms.kra_kpi.th_target') }} <span class="text-danger">*</span></th>
                        <th style="width: 9%;">{{ __('hrms.kra_kpi.th_weight') }} <span class="text-danger">*</span></th>
                        <th style="width: 42px;" class="text-center">{{ __('hrms.kra_kpi.th_action') }}</th>
                    </tr>
                </thead>
                <tbody id="templateRowsTbody">
                    <tr>
                        <td>
                            <div class="kpi-select-wrap d-flex align-items-center" style="width: 100%;">
                                <select class="odoo-table-select odoo-select2 kpi-template-select" style="width: 100%;">
                                    <option value="">{{ __('hrms.kra_kpi.choose_standard_kpi') }}</option>
                                    <option value="__custom__">{{ __('hrms.kra_kpi.add_custom_kpi') }}</option>
                                    @foreach($kpiMasters as $km)
                                        <option value="{{ $km->name }}"
                                            data-kpi-id="{{ $km->id }}"
                                            data-kra-id="{{ $km->kra_category_id }}"
                                            data-kra-name="{{ $km->kraCategory->name ?? '' }}"
                                            data-calc="{{ $km->calculation_type }}"
                                            data-unit="{{ $km->unit }}"
                                            data-target="{{ $km->default_target }}"
                                            data-weight="{{ $km->default_weightage }}">
                                            {{ $km->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="kpi-input-wrap d-none align-items-center gap-1">
                                <input type="text" name="items[0][title]" class="odoo-table-input kpi-title-input" placeholder="{{ __('hrms.kra_kpi.placeholder_custom_goal') }}" />
                                <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ __('hrms.kra_kpi.switch_back_to_list') }}" onclick="switchToTemplateSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                                    <i class="feather-list"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            <div class="kra-select-wrap d-flex align-items-center" style="width: 100%;">
                                <select class="odoo-table-select odoo-select2 kra-template-select" style="width: 100%;">
                                    <option value="">{{ __('hrms.kra_kpi.choose_kra_focus') }}</option>
                                    <option value="__custom__">{{ __('hrms.kra_kpi.add_custom_kra') }}</option>
                                    @foreach($kraCategories as $kra)
                                        <option value="{{ $kra->id }}">{{ $kra->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="kra-locked-wrap d-none align-items-center justify-content-between px-2 py-1 bg-light border rounded text-dark fs-12" style="height: 31px; width: 100%;">
                                <div class="d-flex align-items-center gap-1.5 text-truncate" style="min-width: 0;">
                                    <i class="feather-lock text-muted fs-11 flex-shrink-0" title="{{ __('hrms.kra_kpi.fixed_standard_kpi_category') }}"></i>
                                    <span class="kra-locked-text text-truncate fw-medium text-dark"></span>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0.5 px-1 fs-10 flex-shrink-0" style="font-size: 9px; line-height: 1.2;">{{ __('hrms.kra_kpi.library_badge') }}</span>
                            </div>
                            <div class="kra-input-wrap d-none align-items-center gap-1">
                                <input type="text" class="odoo-table-input kra-title-input" placeholder="{{ __('hrms.kra_kpi.type_custom_kra') }}" />
                                <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ __('hrms.kra_kpi.switch_back_to_list') }}" onclick="switchToTemplateKraSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                                    <i class="feather-list"></i>
                                </button>
                            </div>
                            <input type="hidden" name="items[0][kra_category_id]" class="kra-final-id-input" value="" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[0][calculation_type]">
                                <option value="higher_is_better">{{ __('hrms.kra_kpi.dir_higher_short') }}</option>
                                <option value="lower_is_better">{{ __('hrms.kra_kpi.dir_lower_short') }}</option>
                                <option value="milestone">{{ __('hrms.kra_kpi.dir_milestone_short') }}</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[0][unit]">
                                <option value="percentage">%</option>
                                <option value="number">{{ __('hrms.kra_kpi.unit_qty_short') }}</option>
                                <option value="currency">{{ __('hrms.kra_kpi.unit_currency_symbol') }}</option>
                                <option value="rating">{{ __('hrms.kra_kpi.unit_rating_scale') }}</option>
                                <option value="boolean">{{ __('hrms.kra_kpi.unit_milestone_label') }}</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[0][target]" value="95" :required="true" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[0][weightage]" value="50" :required="true" />
                        </td>
                        <td class="text-center">
                            <x-ui.icon-btn variant="soft-danger" icon="feather-trash-2" size="sm" class="remove-template-row" title="{{ __('hrms.kra_kpi.remove_goal') }}" />
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="kpi-select-wrap d-flex align-items-center" style="width: 100%;">
                                <select class="odoo-table-select odoo-select2 kpi-template-select" style="width: 100%;">
                                    <option value="">{{ __('hrms.kra_kpi.choose_standard_kpi') }}</option>
                                    <option value="__custom__">{{ __('hrms.kra_kpi.add_custom_kpi') }}</option>
                                    @foreach($kpiMasters as $km)
                                        <option value="{{ $km->name }}"
                                            data-kpi-id="{{ $km->id }}"
                                            data-kra-id="{{ $km->kra_category_id }}"
                                            data-kra-name="{{ $km->kraCategory->name ?? '' }}"
                                            data-calc="{{ $km->calculation_type }}"
                                            data-unit="{{ $km->unit }}"
                                            data-target="{{ $km->default_target }}"
                                            data-weight="{{ $km->default_weightage }}">
                                            {{ $km->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="kpi-input-wrap d-none align-items-center gap-1">
                                <input type="text" name="items[1][title]" class="odoo-table-input kpi-title-input" placeholder="{{ __('hrms.kra_kpi.placeholder_custom_goal') }}" />
                                <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ __('hrms.kra_kpi.switch_back_to_list') }}" onclick="switchToTemplateSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                                    <i class="feather-list"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            <div class="kra-select-wrap d-flex align-items-center" style="width: 100%;">
                                <select class="odoo-table-select odoo-select2 kra-template-select" style="width: 100%;">
                                    <option value="">{{ __('hrms.kra_kpi.choose_kra_focus') }}</option>
                                    <option value="__custom__">{{ __('hrms.kra_kpi.add_custom_kra') }}</option>
                                    @foreach($kraCategories as $kra)
                                        <option value="{{ $kra->id }}">{{ $kra->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="kra-locked-wrap d-none align-items-center justify-content-between px-2 py-1 bg-light border rounded text-dark fs-12" style="height: 31px; width: 100%;">
                                <div class="d-flex align-items-center gap-1.5 text-truncate" style="min-width: 0;">
                                    <i class="feather-lock text-muted fs-11 flex-shrink-0" title="{{ __('hrms.kra_kpi.fixed_standard_kpi_category') }}"></i>
                                    <span class="kra-locked-text text-truncate fw-medium text-dark"></span>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0.5 px-1 fs-10 flex-shrink-0" style="font-size: 9px; line-height: 1.2;">{{ __('hrms.kra_kpi.library_badge') }}</span>
                            </div>
                            <div class="kra-input-wrap d-none align-items-center gap-1">
                                <input type="text" class="odoo-table-input kra-title-input" placeholder="{{ __('hrms.kra_kpi.type_custom_kra') }}" />
                                <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ __('hrms.kra_kpi.switch_back_to_list') }}" onclick="switchToTemplateKraSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                                    <i class="feather-list"></i>
                                </button>
                            </div>
                            <input type="hidden" name="items[1][kra_category_id]" class="kra-final-id-input" value="" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[1][calculation_type]">
                                <option value="higher_is_better">{{ __('hrms.kra_kpi.dir_higher_short') }}</option>
                                <option value="lower_is_better">{{ __('hrms.kra_kpi.dir_lower_short') }}</option>
                                <option value="milestone">{{ __('hrms.kra_kpi.dir_milestone_short') }}</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[1][unit]">
                                <option value="percentage">%</option>
                                <option value="number">{{ __('hrms.kra_kpi.unit_qty_short') }}</option>
                                <option value="currency">{{ __('hrms.kra_kpi.unit_currency_symbol') }}</option>
                                <option value="rating">{{ __('hrms.kra_kpi.unit_rating_scale') }}</option>
                                <option value="boolean">{{ __('hrms.kra_kpi.unit_milestone_label') }}</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[1][target]" value="90" :required="true" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[1][weightage]" value="50" :required="true" />
                        </td>
                        <td class="text-center">
                            <x-ui.icon-btn variant="soft-danger" icon="feather-trash-2" size="sm" class="remove-template-row" title="{{ __('hrms.kra_kpi.remove_goal') }}" />
                        </td>
                    </tr>
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">{{ __('hrms.kra_kpi.btn_save_template') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- EDIT ROLE TEMPLATE MODALS -->
@foreach($kpiTemplates as $tpl)
<x-ui.modal id="editTemplateModal_{{ $tpl->id }}" title="{{ __('hrms.kra_kpi.modal_edit_template_title', ['name' => $tpl->name]) }}" size="xl" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.template.update', $tpl->id) }}">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.kra_kpi.template_name') }}" name="name" value="{{ $tpl->name }}" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.kra_kpi.department') }}" name="department_id">
                    <option value="">{{ __('hrms.kra_kpi.all_departments') }}</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" @selected($tpl->department_id == $dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.kra_kpi.designation') }}" name="designation_id">
                    <option value="">{{ __('hrms.kra_kpi.all_designations') }}</option>
                    @foreach($designations as $desig)
                        <option value="{{ $desig->id }}" @selected($tpl->designation_id == $desig->id)>{{ $desig->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <div class="mt-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3 flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold text-dark mb-0">{{ __('hrms.kra_kpi.template_goals_heading') }}</h6>
                    <small class="text-muted">{{ __('hrms.kra_kpi.template_goals_desc_edit') }}</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-12 px-2.5 py-1.5 fw-semibold weightage-total-badge" id="editTemplateWeightBadge_{{ $tpl->id }}">{{ __('hrms.kra_kpi.total_weight_label', ['weight' => $tpl->items->sum('weightage')]) }}</span>
                    <x-ui.button type="button" variant="light" size="sm" icon="feather-plus" class="border fw-semibold" onclick="addEditTemplateRow({{ $tpl->id }})">
                        {{ __('hrms.kra_kpi.add_goal_row') }}
                    </x-ui.button>
                </div>
            </div>
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 28%;">{{ __('hrms.kra_kpi.th_kpi_title') }} <span class="text-danger">*</span></th>
                        <th style="width: 22%;">{{ __('hrms.kra_kpi.th_kra_focus') }}</th>
                        <th style="width: 18%;">{{ __('hrms.kra_kpi.th_direction') }}</th>
                        <th style="width: 12%;">{{ __('hrms.kra_kpi.th_unit') }}</th>
                        <th style="width: 9%;">{{ __('hrms.kra_kpi.th_target') }} <span class="text-danger">*</span></th>
                        <th style="width: 9%;">{{ __('hrms.kra_kpi.th_weight') }} <span class="text-danger">*</span></th>
                        <th style="width: 42px;" class="text-center">{{ __('hrms.kra_kpi.th_action') }}</th>
                    </tr>
                </thead>
                <tbody id="editTemplateRowsTbody_{{ $tpl->id }}">
                    @foreach($tpl->items as $idx => $item)
                        @php
                            $isStandardKpi = $kpiMasters->contains('name', $item->title);
                        @endphp
                        <tr>
                            <td>
                                <div class="kpi-select-wrap {{ $isStandardKpi ? 'd-flex' : 'd-none' }} align-items-center" style="width: 100%;">
                                    <select class="odoo-table-select odoo-select2 kpi-template-select" style="width: 100%;">
                                        <option value="">{{ __('hrms.kra_kpi.choose_standard_kpi') }}</option>
                                        <option value="__custom__" @selected(!$isStandardKpi)>{{ __('hrms.kra_kpi.add_custom_kpi') }}</option>
                                        @foreach($kpiMasters as $km)
                                            <option value="{{ $km->name }}"
                                                data-kpi-id="{{ $km->id }}"
                                                data-kra-id="{{ $km->kra_category_id }}"
                                                data-kra-name="{{ $km->kraCategory->name ?? '' }}"
                                                data-calc="{{ $km->calculation_type }}"
                                                data-unit="{{ $km->unit }}"
                                                data-target="{{ $km->default_target }}"
                                                data-weight="{{ $km->default_weightage }}"
                                                @selected($item->title === $km->name)>
                                                {{ $km->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="kpi-input-wrap {{ $isStandardKpi ? 'd-none' : 'd-flex' }} align-items-center gap-1">
                                    <input type="text" name="items[{{ $idx }}][title]" value="{{ $item->title }}" class="odoo-table-input kpi-title-input" placeholder="{{ __('hrms.kra_kpi.placeholder_custom_goal') }}" />
                                    <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ __('hrms.kra_kpi.switch_back_to_list') }}" onclick="switchToTemplateSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                                        <i class="feather-list"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                @php
                                    $isKnownKra = $kraCategories->contains('id', $item->kra_category_id);
                                    $kraDisplayWrap = $isStandardKpi ? 'd-none' : ($isKnownKra ? 'd-flex' : 'd-none');
                                    $kraInputWrap = $isStandardKpi ? 'd-none' : ($isKnownKra ? 'd-none' : 'd-flex');
                                    $kraLockedWrap = $isStandardKpi ? 'd-flex' : 'd-none';
                                @endphp
                                <div class="kra-select-wrap {{ $kraDisplayWrap }} align-items-center" style="width: 100%;">
                                    <select class="odoo-table-select odoo-select2 kra-template-select" style="width: 100%;">
                                        <option value="">{{ __('hrms.kra_kpi.choose_kra_focus') }}</option>
                                        <option value="__custom__" @selected(!$isKnownKra && $item->kra_category_id)>{{ __('hrms.kra_kpi.add_custom_kra') }}</option>
                                        @foreach($kraCategories as $kra)
                                            <option value="{{ $kra->id }}" @selected($item->kra_category_id == $kra->id)>{{ $kra->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="kra-locked-wrap {{ $kraLockedWrap }} align-items-center justify-content-between px-2 py-1 bg-light border rounded text-dark fs-12" style="height: 31px; width: 100%;">
                                    <div class="d-flex align-items-center gap-1.5 text-truncate" style="min-width: 0;">
                                        <i class="feather-lock text-muted fs-11 flex-shrink-0" title="{{ __('hrms.kra_kpi.fixed_standard_kpi_category') }}"></i>
                                        <span class="kra-locked-text text-truncate fw-medium text-dark">{{ $item->kraCategory->name ?? __('hrms.kra_kpi.standard_kra') }}</span>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0.5 px-1 fs-10 flex-shrink-0" style="font-size: 9px; line-height: 1.2;">{{ __('hrms.kra_kpi.library_badge') }}</span>
                                </div>
                                <div class="kra-input-wrap {{ $kraInputWrap }} align-items-center gap-1">
                                    <input type="text" value="{{ $isKnownKra ? '' : ($item->kraCategory->name ?? '') }}" class="odoo-table-input kra-title-input" placeholder="{{ __('hrms.kra_kpi.type_custom_kra') }}" />
                                    <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ __('hrms.kra_kpi.switch_back_to_list') }}" onclick="switchToTemplateKraSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                                        <i class="feather-list"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="items[{{ $idx }}][kra_category_id]" class="kra-final-id-input" value="{{ $item->kra_category_id }}" />
                            </td>
                            <td>
                                <x-ui.odoo-form-ui type="select" name="items[{{ $idx }}][calculation_type]">
                                    <option value="higher_is_better" @selected($item->calculation_type === 'higher_is_better')>{{ __('hrms.kra_kpi.dir_higher_short') }}</option>
                                    <option value="lower_is_better" @selected($item->calculation_type === 'lower_is_better')>{{ __('hrms.kra_kpi.dir_lower_short') }}</option>
                                    <option value="milestone" @selected($item->calculation_type === 'milestone')>{{ __('hrms.kra_kpi.dir_milestone_short') }}</option>
                                </x-ui.odoo-form-ui>
                            </td>
                            <td>
                                <x-ui.odoo-form-ui type="select" name="items[{{ $idx }}][unit]">
                                    <option value="percentage" @selected($item->unit === 'percentage')>%</option>
                                    <option value="number" @selected($item->unit === 'number')>{{ __('hrms.kra_kpi.unit_qty_short') }}</option>
                                    <option value="currency" @selected($item->unit === 'currency')>{{ __('hrms.kra_kpi.unit_currency_symbol') }}</option>
                                    <option value="rating" @selected($item->unit === 'rating')>{{ __('hrms.kra_kpi.unit_rating_scale') }}</option>
                                    <option value="boolean" @selected($item->unit === 'boolean')>{{ __('hrms.kra_kpi.unit_milestone_label') }}</option>
                                </x-ui.odoo-form-ui>
                            </td>
                            <td>
                                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[{{ $idx }}][target]" value="{{ $item->target }}" :required="true" />
                            </td>
                            <td>
                                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[{{ $idx }}][weightage]" value="{{ $item->weightage }}" :required="true" />
                            </td>
                            <td class="text-center">
                                <x-ui.icon-btn variant="soft-danger" icon="feather-trash-2" size="sm" class="remove-template-row" title="{{ __('hrms.kra_kpi.remove_goal') }}" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">{{ __('hrms.kra_kpi.btn_update_template') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
@endforeach
@endif

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const assignmentTarget = document.getElementById('assignmentTargetType');
        const deptWrap = document.getElementById('deptSelectWrap');
        const empWrap = document.getElementById('empSelectWrap');

        if (assignmentTarget) {
            assignmentTarget.addEventListener('change', function () {
                if (this.value === 'department') {
                    deptWrap?.classList.remove('d-none');
                    empWrap?.classList.add('d-none');
                } else if (this.value === 'individual') {
                    deptWrap?.classList.add('d-none');
                    empWrap?.classList.remove('d-none');
                } else {
                    deptWrap?.classList.add('d-none');
                    empWrap?.classList.add('d-none');
                }
            });
        }

        // Switch between Existing Cycle and New Cycle inside Assign Goal Sheets Modal
        window.switchCycleMode = function(mode) {
            const input = document.getElementById('cycleModeInput');
            const existingSection = document.getElementById('existingCycleSection');
            const newSection = document.getElementById('newCycleSection');
            const btnExisting = document.getElementById('btnModeExisting');
            const btnNew = document.getElementById('btnModeNew');
            const selectCycle = document.getElementById('assignAppraisalCycleSelect');
            const newCycleName = document.getElementById('newCycleNameInput');
            const newCycleStart = document.getElementById('newCycleStartDate');
            const newCycleEnd = document.getElementById('newCycleEndDate');

            if (input) input.value = mode;

            if (mode === 'existing') {
                if (btnExisting) {
                    btnExisting.classList.remove('btn-outline-primary');
                    btnExisting.classList.add('btn-primary');
                }
                if (btnNew) {
                    btnNew.classList.remove('btn-primary');
                    btnNew.classList.add('btn-outline-primary');
                }
                if (existingSection) existingSection.classList.remove('d-none');
                if (newSection) newSection.classList.add('d-none');

                if (selectCycle) selectCycle.required = true;
                if (newCycleName) newCycleName.required = false;
                if (newCycleStart) newCycleStart.required = false;
                if (newCycleEnd) newCycleEnd.required = false;
            } else {
                if (btnNew) {
                    btnNew.classList.remove('btn-outline-primary');
                    btnNew.classList.add('btn-primary');
                }
                if (btnExisting) {
                    btnExisting.classList.remove('btn-primary');
                    btnExisting.classList.add('btn-outline-primary');
                }
                if (existingSection) existingSection.classList.add('d-none');
                if (newSection) newSection.classList.remove('d-none');

                if (selectCycle) selectCycle.required = false;
                if (newCycleName) newCycleName.required = true;
                if (newCycleStart) newCycleStart.required = true;
                if (newCycleEnd) newCycleEnd.required = true;

                calculateNewCycleDates();
            }
        };

        // Auto-calculate dates for on-the-fly cycle in assign modal
        function calculateNewCycleDates() {
            const startDateInput = document.getElementById('newCycleStartDate');
            const endDateInput = document.getElementById('newCycleEndDate');
            const periodSelect = document.getElementById('newCyclePeriodType');
            const reviewDeadlineInput = document.getElementById('newCycleReviewDeadline');
            if (!startDateInput || !endDateInput || !periodSelect) return;
            if (!startDateInput.value) return;

            const parts = startDateInput.value.split('-');
            if (parts.length !== 3) return;

            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1;
            const day = parseInt(parts[2], 10);

            const start = new Date(year, month, day);
            if (isNaN(start.getTime())) return;

            let end = new Date(start);
            const freq = periodSelect.value;

            if (freq === 'monthly') {
                end.setMonth(end.getMonth() + 1);
                end.setDate(end.getDate() - 1);
            } else if (freq === 'quarterly') {
                end.setMonth(end.getMonth() + 3);
                end.setDate(end.getDate() - 1);
            } else if (freq === 'semi_annual') {
                end.setMonth(end.getMonth() + 6);
                end.setDate(end.getDate() - 1);
            } else if (freq === 'annual') {
                end.setFullYear(end.getFullYear() + 1);
                end.setDate(end.getDate() - 1);
            }

            const y = end.getFullYear();
            const m = String(end.getMonth() + 1).padStart(2, '0');
            const d = String(end.getDate()).padStart(2, '0');
            const formatted = `${y}-${m}-${d}`;
            endDateInput.value = formatted;
            if (reviewDeadlineInput) {
                reviewDeadlineInput.value = formatted;
            }
        }

        if (window.jQuery) {
            $(document).on('change change.select2 select2:select', '#newCyclePeriodType', calculateNewCycleDates);
            $(document).on('input change', '#newCycleStartDate', calculateNewCycleDates);
        } else {
            document.getElementById('newCycleStartDate')?.addEventListener('input', calculateNewCycleDates);
            document.getElementById('newCycleStartDate')?.addEventListener('change', calculateNewCycleDates);
            document.getElementById('newCyclePeriodType')?.addEventListener('change', calculateNewCycleDates);
        }

        // Auto-calculate Cycle End Date based on Period Frequency and Start Date
        function calculateCreateCycleDates() {
            const startDateInput = document.getElementById('createCycleStartDate');
            const endDateInput = document.getElementById('createCycleEndDate');
            const periodSelect = document.getElementById('createCyclePeriodType');
            const reviewDeadlineInput = document.getElementById('createCycleReviewDeadline');
            if (!startDateInput || !endDateInput || !periodSelect) return;
            if (!startDateInput.value) return;

            const parts = startDateInput.value.split('-');
            if (parts.length !== 3) return;

            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1;
            const day = parseInt(parts[2], 10);

            const start = new Date(year, month, day);
            if (isNaN(start.getTime())) return;

            let end = new Date(start);
            const freq = periodSelect.value;

            if (freq === 'monthly') {
                end.setMonth(end.getMonth() + 1);
                end.setDate(end.getDate() - 1);
            } else if (freq === 'quarterly') {
                end.setMonth(end.getMonth() + 3);
                end.setDate(end.getDate() - 1);
            } else if (freq === 'semi_annual') {
                end.setMonth(end.getMonth() + 6);
                end.setDate(end.getDate() - 1);
            } else if (freq === 'annual') {
                end.setFullYear(end.getFullYear() + 1);
                end.setDate(end.getDate() - 1);
            }

            const y = end.getFullYear();
            const m = String(end.getMonth() + 1).padStart(2, '0');
            const d = String(end.getDate()).padStart(2, '0');
            const formatted = `${y}-${m}-${d}`;
            endDateInput.value = formatted;
            if (reviewDeadlineInput) {
                reviewDeadlineInput.value = formatted;
            }
        }

        if (window.jQuery) {
            $(document).on('change change.select2 select2:select', '#createCyclePeriodType', calculateCreateCycleDates);
            $(document).on('input change', '#createCycleStartDate', calculateCreateCycleDates);
        } else {
            document.getElementById('createCycleStartDate')?.addEventListener('input', calculateCreateCycleDates);
            document.getElementById('createCycleStartDate')?.addEventListener('change', calculateCreateCycleDates);
            document.getElementById('createCyclePeriodType')?.addEventListener('change', calculateCreateCycleDates);
        }

        // Dynamic Field Switcher for Assign Goal Sheets Modal
        function handleAssignmentScopeChange() {
            const scopeSelect = document.getElementById('assignmentTargetType');
            const deptWrap = document.getElementById('deptSelectWrap');
            const empWrap = document.getElementById('empSelectWrap');
            
            if (!scopeSelect || !deptWrap || !empWrap) return;
            const val = scopeSelect.value;
            
            if (val === 'department') {
                deptWrap.classList.remove('d-none');
                empWrap.classList.add('d-none');
            } else if (val === 'individual') {
                deptWrap.classList.add('d-none');
                empWrap.classList.remove('d-none');
            } else {
                deptWrap.classList.add('d-none');
                empWrap.classList.add('d-none');
            }
        }

        if (window.jQuery) {
            $(document).on('change change.select2 select2:select', '#assignmentTargetType', handleAssignmentScopeChange);
            $('#assignTemplateModal').on('shown.bs.modal', handleAssignmentScopeChange);
        }
        document.addEventListener('change', function(e) {
            if (e.target && e.target.id === 'assignmentTargetType') {
                handleAssignmentScopeChange();
            }
        });

        // Auto-synchronize Direction, Unit, and Target
        window.syncRowDirectionAndUnit = function(tr, changedField) {
            if (!tr) return;
            const $calcSelect = $(tr).find('select[name$="[calculation_type]"]');
            const $unitSelect = $(tr).find('select[name$="[unit]"]');
            const targetInput = tr.querySelector('input[name$="[target]"]');
            if (!$calcSelect.length || !$unitSelect.length || !targetInput) return;

            const calcVal = $calcSelect.val();
            const unitVal = $unitSelect.val();

            if (changedField === 'direction') {
                if (calcVal === 'milestone') {
                    // Milestone calculation: Unit must be Milestone (boolean), Target must be 1
                    if (unitVal !== 'boolean') {
                        $unitSelect.val('boolean').trigger('change.select2');
                    }
                    targetInput.value = 1;
                    targetInput.setAttribute('step', '1');
                    targetInput.setAttribute('min', '1');
                    targetInput.setAttribute('max', '1');
                    targetInput.setAttribute('placeholder', '1 (Done)');
                } else {
                    // Switched back to Higher or Lower is better
                    if (unitVal === 'boolean') {
                        $unitSelect.val('percentage').trigger('change.select2');
                        targetInput.value = 100;
                        targetInput.removeAttribute('max');
                        targetInput.setAttribute('step', '0.01');
                        targetInput.setAttribute('min', '0');
                        targetInput.setAttribute('placeholder', '100%');
                    }
                }
            } else if (changedField === 'unit') {
                if (unitVal === 'boolean') {
                    // Milestone unit selected
                    if (calcVal !== 'milestone') {
                        $calcSelect.val('milestone').trigger('change.select2');
                    }
                    targetInput.value = 1;
                    targetInput.setAttribute('step', '1');
                    targetInput.setAttribute('min', '1');
                    targetInput.setAttribute('max', '1');
                    targetInput.setAttribute('placeholder', '1 (Done)');
                } else {
                    // Quantitative units: %, Qty, ₹, Rating
                    if (calcVal === 'milestone') {
                        $calcSelect.val('higher_is_better').trigger('change.select2');
                    }
                    targetInput.removeAttribute('max');
                    targetInput.setAttribute('step', '0.01');
                    targetInput.setAttribute('min', '0');

                    if (unitVal === 'percentage') {
                        if (parseFloat(targetInput.value) > 100 || targetInput.value === '1') {
                            targetInput.value = 100;
                        }
                        targetInput.setAttribute('max', '100');
                        targetInput.setAttribute('placeholder', '100%');
                    } else if (unitVal === 'rating') {
                        if (parseFloat(targetInput.value) > 5 || targetInput.value === '100' || targetInput.value === '1') {
                            targetInput.value = 4.5;
                        }
                        targetInput.setAttribute('max', '5');
                        targetInput.setAttribute('step', '0.1');
                        targetInput.setAttribute('placeholder', '4.5 (1-5)');
                    } else if (unitVal === 'currency') {
                        if (targetInput.value === '1' || targetInput.value === '100' || targetInput.value === '95') {
                            targetInput.value = 100000;
                        }
                        targetInput.setAttribute('placeholder', '₹ e.g. 500000');
                    } else if (unitVal === 'number') {
                        if (targetInput.value === '100' || targetInput.value === '1') {
                            targetInput.value = 10;
                        }
                        targetInput.setAttribute('placeholder', 'Qty e.g. 10');
                    }
                }
            }
        };

        // Initialize Select2 in Modals
        if (window.jQuery) {
            $('#assignTemplateModal, #createCycleModal, [id^="editCycleModal_"], #createTemplateModal, [id^="editTemplateModal_"]').on('shown.bs.modal', function() {
                const $modal = $(this);
                $modal.find('.odoo-select2').each(function() {
                    if (!$(this).hasClass('select2-hidden-accessible')) {
                        const isSearchable = $(this).hasClass('kpi-template-select') || $(this).hasClass('kra-template-select');
                        $(this).select2({
                            theme: 'bootstrap-5',
                            dropdownParent: $modal,
                            width: '100%',
                            minimumResultsForSearch: isSearchable ? 0 : Infinity
                        });
                    }
                });
            });

            $(document).on('change change.select2 select2:select', '.kpi-template-select', function() {
                handleTemplateKpiSelect(this);
            });

            $(document).on('change change.select2 select2:select', '.kra-template-select', function() {
                handleTemplateKraSelect(this);
            });

            $(document).on('change change.select2 select2:select', 'select[name$="[calculation_type]"]', function() {
                syncRowDirectionAndUnit(this.closest('tr'), 'direction');
            });

            $(document).on('change change.select2 select2:select', 'select[name$="[unit]"]', function() {
                syncRowDirectionAndUnit(this.closest('tr'), 'unit');
            });
        }

        // KPI Masters & KRA Categories Data for Dynamic Rows
        let kraOptionsHtml = `<option value="">{{ addslashes(__('hrms.kra_kpi.choose_kra_focus')) }}</option><option value="__custom__">{{ addslashes(__('hrms.kra_kpi.add_custom_kra')) }}</option>{!! addslashes(collect($kraCategories)->map(fn($k) => "<option value='{$k->id}'>{$k->name}</option>")->implode('')) !!}`;
        let kpiOptionsHtml = `<option value="">{{ addslashes(__('hrms.kra_kpi.choose_standard_kpi')) }}</option><option value="__custom__">{{ addslashes(__('hrms.kra_kpi.add_custom_kpi')) }}</option>{!! addslashes(collect($kpiMasters)->map(fn($km) => "<option value='{$km->name}' data-kpi-id='{$km->id}' data-kra-id='{$km->kra_category_id}' data-kra-name='" . addslashes($km->kraCategory->name ?? '') . "' data-calc='{$km->calculation_type}' data-unit='{$km->unit}' data-target='{$km->default_target}' data-weight='{$km->default_weightage}'>{$km->name}</option>")->implode('')) !!}`;

        // Smart KPI Select Handler for Template Builder
        window.handleTemplateKpiSelect = function(selectEl) {
            const $sel = $(selectEl);
            const tr = selectEl.closest('tr');
            if (!tr) return;

            const selectWrap = tr.querySelector('.kpi-select-wrap');
            const inputWrap = tr.querySelector('.kpi-input-wrap');
            const titleInput = tr.querySelector('.kpi-title-input');
            const val = $sel.val();

            const kraSelectWrap = tr.querySelector('.kra-select-wrap');
            const kraLockedWrap = tr.querySelector('.kra-locked-wrap');
            const kraInputWrap = tr.querySelector('.kra-input-wrap');
            const kraLockedText = tr.querySelector('.kra-locked-text');
            const $kraSelect = $(tr).find('.kra-template-select');
            const finalKra = tr.querySelector('.kra-final-id-input');

            if (val === '__custom__') {
                if (selectWrap) selectWrap.classList.add('d-none');
                if (inputWrap) {
                    inputWrap.classList.remove('d-none');
                    inputWrap.classList.add('d-flex');
                }
                if (titleInput) {
                    titleInput.value = '';
                    titleInput.focus();
                }

                // Unlock KRA for custom KPI
                if (kraLockedWrap) {
                    kraLockedWrap.classList.add('d-none');
                    kraLockedWrap.classList.remove('d-flex');
                }
                if (!kraInputWrap || kraInputWrap.classList.contains('d-none')) {
                    if (kraSelectWrap) {
                        kraSelectWrap.classList.remove('d-none');
                        kraSelectWrap.classList.add('d-flex');
                    }
                }
                return;
            }

            if (val) {
                if (titleInput) {
                    titleInput.value = val;
                }
                const opt = selectEl.options ? selectEl.selectedOptions[0] : null;
                if (opt) {
                    const kraId = opt.dataset.kraId;
                    const kraName = opt.dataset.kraName || (kraId && $kraSelect.find(`option[value="${kraId}"]`).text().trim()) || '{{ addslashes(__('hrms.kra_kpi.standard_kra')) }}';
                    const calc = opt.dataset.calc;
                    const unit = opt.dataset.unit;
                    const target = opt.dataset.target;
                    const weight = opt.dataset.weight;

                    // Standard KPI picked: Auto-set and LOCK KRA to its official library category
                    if (kraId) {
                        if (kraInputWrap) {
                            kraInputWrap.classList.add('d-none');
                            kraInputWrap.classList.remove('d-flex');
                        }
                        if (kraSelectWrap) {
                            kraSelectWrap.classList.add('d-none');
                            kraSelectWrap.classList.remove('d-flex');
                        }
                        if (kraLockedWrap) {
                            kraLockedWrap.classList.remove('d-none');
                            kraLockedWrap.classList.add('d-flex');
                        }
                        if (kraLockedText) {
                            kraLockedText.innerText = kraName;
                        }
                        $kraSelect.val(kraId);
                        if (finalKra) finalKra.value = kraId;
                    }

                    const $calcSelect = $(tr).find('select[name$="[calculation_type]"]');
                    if ($calcSelect.length && calc) $calcSelect.val(calc).trigger('change.select2');

                    const $unitSelect = $(tr).find('select[name$="[unit]"]');
                    if ($unitSelect.length && unit) $unitSelect.val(unit).trigger('change.select2');

                    const targetInput = tr.querySelector('input[name$="[target]"]');
                    if (targetInput && target) targetInput.value = target;

                    const weightInput = tr.querySelector('input[name$="[weightage]"]');
                    if (weightInput && weight) weightInput.value = weight;
                }
            } else {
                if (titleInput) {
                    titleInput.value = '';
                }
                if (kraLockedWrap) {
                    kraLockedWrap.classList.add('d-none');
                    kraLockedWrap.classList.remove('d-flex');
                }
                if (kraInputWrap) {
                    kraInputWrap.classList.add('d-none');
                    kraInputWrap.classList.remove('d-flex');
                }
                if (kraSelectWrap) {
                    kraSelectWrap.classList.remove('d-none');
                    kraSelectWrap.classList.add('d-flex');
                }
                $kraSelect.val('').trigger('change.select2');
                if (finalKra) finalKra.value = '';
            }
        };

        window.switchToTemplateSelect = function(btn) {
            const tr = btn.closest('tr');
            if (!tr) return;
            const selectWrap = tr.querySelector('.kpi-select-wrap');
            const inputWrap = tr.querySelector('.kpi-input-wrap');
            const selectEl = tr.querySelector('.kpi-template-select');
            const titleInput = tr.querySelector('.kpi-title-input');

            const kraSelectWrap = tr.querySelector('.kra-select-wrap');
            const kraLockedWrap = tr.querySelector('.kra-locked-wrap');
            const kraInputWrap = tr.querySelector('.kra-input-wrap');
            const $kraSelect = $(tr).find('.kra-template-select');
            const finalKra = tr.querySelector('.kra-final-id-input');

            if (inputWrap) {
                inputWrap.classList.add('d-none');
                inputWrap.classList.remove('d-flex');
            }
            if (selectWrap) {
                selectWrap.classList.remove('d-none');
                selectWrap.classList.add('d-flex');
            }
            if (selectEl) {
                $(selectEl).val('').trigger('change.select2');
                $(selectEl).select2('open');
            }
            if (titleInput) {
                titleInput.value = '';
            }

            // Reset KRA when switching back to select
            if (kraLockedWrap) {
                kraLockedWrap.classList.add('d-none');
                kraLockedWrap.classList.remove('d-flex');
            }
            if (kraInputWrap) {
                kraInputWrap.classList.add('d-none');
                kraInputWrap.classList.remove('d-flex');
            }
            if (kraSelectWrap) {
                kraSelectWrap.classList.remove('d-none');
                kraSelectWrap.classList.add('d-flex');
            }
            if ($kraSelect.length) $kraSelect.val('').trigger('change.select2');
            if (finalKra) finalKra.value = '';
        };

        window.handleTemplateKraSelect = function(selectEl) {
            const $sel = $(selectEl);
            const tr = selectEl.closest('tr');
            if (!tr) return;

            const selectWrap = tr.querySelector('.kra-select-wrap');
            const inputWrap = tr.querySelector('.kra-input-wrap');
            const titleInput = tr.querySelector('.kra-title-input');
            const finalInput = tr.querySelector('.kra-final-id-input');
            const val = $sel.val();

            if (val === '__custom__') {
                if (selectWrap) selectWrap.classList.add('d-none');
                if (inputWrap) {
                    inputWrap.classList.remove('d-none');
                    inputWrap.classList.add('d-flex');
                }
                if (titleInput) {
                    titleInput.value = '';
                    titleInput.focus();
                }
                if (finalInput) finalInput.value = '';
                return;
            }

            if (val) {
                if (finalInput) finalInput.value = val;
            } else {
                if (finalInput) finalInput.value = '';
            }
        };

        window.switchToTemplateKraSelect = function(btn) {
            const tr = btn.closest('tr');
            if (!tr) return;
            const selectWrap = tr.querySelector('.kra-select-wrap');
            const inputWrap = tr.querySelector('.kra-input-wrap');
            const selectEl = tr.querySelector('.kra-template-select');
            const titleInput = tr.querySelector('.kra-title-input');
            const finalInput = tr.querySelector('.kra-final-id-input');

            if (inputWrap) {
                inputWrap.classList.add('d-none');
                inputWrap.classList.remove('d-flex');
            }
            if (selectWrap) {
                selectWrap.classList.remove('d-none');
                selectWrap.classList.add('d-flex');
            }
            if (selectEl) {
                $(selectEl).val('').trigger('change.select2');
                $(selectEl).select2('open');
            }
            if (titleInput) titleInput.value = '';
            if (finalInput) finalInput.value = '';
        };

        $(document).on('input', '.kra-title-input', function() {
            const tr = this.closest('tr');
            if (!tr) return;
            const finalInput = tr.querySelector('.kra-final-id-input');
            if (finalInput) finalInput.value = this.value;
        });

        // Dynamic Add/Remove Row for KPI Template Builder
        function updateModalWeightage(modalOrTable) {
            if (!modalOrTable) return;
            const container = modalOrTable.closest ? (modalOrTable.closest('.modal') || modalOrTable) : modalOrTable;
            const badge = container.querySelector ? container.querySelector('.weightage-total-badge') : null;
            const submitBtn = container.querySelector ? container.querySelector('button[type="submit"]') : null;

            let total = 0;
            container.querySelectorAll('input[name$="[weightage]"]').forEach(input => {
                const val = parseFloat(input.value);
                if (!isNaN(val)) total += val;
            });
            total = Math.round(total * 100) / 100;

            const isBalanced = Math.abs(total - 100) < 0.01;

            if (badge) {
                if (isBalanced) {
                    badge.className = 'badge bg-success text-white fs-12 px-2.5 py-1.5 fw-semibold weightage-total-badge';
                    badge.innerHTML = '<i class="feather-check-circle me-1"></i> {{ addslashes(__('hrms.kra_kpi.weight_balanced')) }}';
                } else if (total > 100) {
                    badge.className = 'badge bg-danger text-white fs-12 px-2.5 py-1.5 fw-semibold weightage-total-badge';
                    badge.innerHTML = `<i class="feather-alert-triangle me-1"></i> ${'{{ addslashes(__('hrms.kra_kpi.weight_exceeds', ['total' => '___TOTAL___'])) }}'.replace('___TOTAL___', total)}`;
                } else {
                    badge.className = 'badge bg-warning text-dark fs-12 px-2.5 py-1.5 fw-semibold weightage-total-badge';
                    badge.innerHTML = `<i class="feather-alert-circle me-1"></i> ${'{{ addslashes(__('hrms.kra_kpi.weight_target', ['total' => '___TOTAL___'])) }}'.replace('___TOTAL___', total)}`;
                }
            }

            if (submitBtn) {
                if (isBalanced) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('disabled');
                    submitBtn.removeAttribute('title');
                } else {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('disabled');
                    submitBtn.setAttribute('title', `${'{{ addslashes(__('hrms.kra_kpi.weight_must_be_100_title', ['total' => '___TOTAL___'])) }}'.replace('___TOTAL___', total)}`);
                }
            }
        }

        $(document).on('input change', 'input[name$="[weightage]"]', function() {
            updateModalWeightage(this);
        });

        $('#createTemplateModal, [id^="editTemplateModal_"]').on('shown.bs.modal', function() {
            updateModalWeightage(this);
        });

        document.getElementById('addTemplateRowBtn')?.addEventListener('click', function() {
            const tbody = document.getElementById('templateRowsTbody');
            if (!tbody) return;
            const uniqueIdx = 'new_' + Date.now() + '_' + Math.floor(Math.random() * 1000);

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="kpi-select-wrap d-flex align-items-center" style="width: 100%;">
                        <select class="odoo-table-select odoo-select2 kpi-template-select" style="width: 100%;">
                            ${kpiOptionsHtml}
                        </select>
                    </div>
                    <div class="kpi-input-wrap d-none align-items-center gap-1">
                        <input type="text" name="items[${uniqueIdx}][title]" class="odoo-table-input kpi-title-input" placeholder="{{ addslashes(__('hrms.kra_kpi.placeholder_custom_goal')) }}" />
                        <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ addslashes(__('hrms.kra_kpi.switch_back_to_list')) }}" onclick="switchToTemplateSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                            <i class="feather-list"></i>
                        </button>
                    </div>
                </td>
                <td>
                    <div class="kra-select-wrap d-flex align-items-center" style="width: 100%;">
                        <select class="odoo-table-select odoo-select2 kra-template-select" style="width: 100%;">
                            ${kraOptionsHtml}
                        </select>
                    </div>
                    <div class="kra-locked-wrap d-none align-items-center justify-content-between px-2 py-1 bg-light border rounded text-dark fs-12" style="height: 31px; width: 100%;">
                        <div class="d-flex align-items-center gap-1.5 text-truncate" style="min-width: 0;">
                            <i class="feather-lock text-muted fs-11 flex-shrink-0" title="{{ addslashes(__('hrms.kra_kpi.fixed_standard_kpi_category')) }}"></i>
                            <span class="kra-locked-text text-truncate fw-medium text-dark"></span>
                        </div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0.5 px-1 fs-10 flex-shrink-0" style="font-size: 9px; line-height: 1.2;">{{ addslashes(__('hrms.kra_kpi.library_badge')) }}</span>
                    </div>
                    <div class="kra-input-wrap d-none align-items-center gap-1">
                        <input type="text" class="odoo-table-input kra-title-input" placeholder="{{ addslashes(__('hrms.kra_kpi.type_custom_kra')) }}" />
                        <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ addslashes(__('hrms.kra_kpi.switch_back_to_list')) }}" onclick="switchToTemplateKraSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                            <i class="feather-list"></i>
                        </button>
                    </div>
                    <input type="hidden" name="items[${uniqueIdx}][kra_category_id]" class="kra-final-id-input" />
                </td>
                <td>
                    <select name="items[${uniqueIdx}][calculation_type]" class="odoo-table-select odoo-select2" style="width: 100%;">
                        <option value="higher_is_better">{{ addslashes(__('hrms.kra_kpi.dir_higher_short')) }}</option>
                        <option value="lower_is_better">{{ addslashes(__('hrms.kra_kpi.dir_lower_short')) }}</option>
                        <option value="milestone">{{ addslashes(__('hrms.kra_kpi.dir_milestone_short')) }}</option>
                    </select>
                </td>
                <td>
                    <select name="items[${uniqueIdx}][unit]" class="odoo-table-select odoo-select2" style="width: 100%;">
                        <option value="percentage">%</option>
                        <option value="number">{{ addslashes(__('hrms.kra_kpi.unit_qty_short')) }}</option>
                        <option value="currency">{{ addslashes(__('hrms.kra_kpi.unit_currency_symbol')) }}</option>
                        <option value="rating">{{ addslashes(__('hrms.kra_kpi.unit_rating_scale')) }}</option>
                        <option value="boolean">{{ addslashes(__('hrms.kra_kpi.unit_milestone_label')) }}</option>
                    </select>
                </td>
                <td>
                    <input type="number" step="0.01" name="items[${uniqueIdx}][target]" value="100" class="odoo-table-input" required />
                </td>
                <td>
                    <input type="number" step="0.01" name="items[${uniqueIdx}][weightage]" value="25" class="odoo-table-input" required />
                </td>
                <td class="text-center">
                    <button type="button" class="btn erp-icon-btn erp-icon-btn--danger btn-sm remove-template-row" title="{{ addslashes(__('hrms.kra_kpi.remove_goal')) }}">
                        <i class="feather-trash-2"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            
            const $newRow = $(tr);
            $newRow.find('.odoo-select2').each(function() {
                const isSearchable = $(this).hasClass('kpi-template-select') || $(this).hasClass('kra-template-select');
                $(this).select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $('#createTemplateModal'),
                    width: '100%',
                    minimumResultsForSearch: isSearchable ? 0 : Infinity
                });
            });

            updateModalWeightage(tbody);
        });

        // Dynamic Add Row for Edit Template Modals
        window.addEditTemplateRow = function(tplId) {
            const tbody = document.getElementById('editTemplateRowsTbody_' + tplId);
            if (!tbody) return;
            const uniqueIdx = 'new_' + Date.now() + '_' + Math.floor(Math.random() * 1000);

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="kpi-select-wrap d-flex align-items-center" style="width: 100%;">
                        <select class="odoo-table-select odoo-select2 kpi-template-select" style="width: 100%;">
                            ${kpiOptionsHtml}
                        </select>
                    </div>
                    <div class="kpi-input-wrap d-none align-items-center gap-1">
                        <input type="text" name="items[${uniqueIdx}][title]" class="odoo-table-input kpi-title-input" placeholder="{{ addslashes(__('hrms.kra_kpi.placeholder_custom_goal')) }}" />
                        <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ addslashes(__('hrms.kra_kpi.switch_back_to_list')) }}" onclick="switchToTemplateSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                            <i class="feather-list"></i>
                        </button>
                    </div>
                </td>
                <td>
                    <div class="kra-select-wrap d-flex align-items-center" style="width: 100%;">
                        <select class="odoo-table-select odoo-select2 kra-template-select" style="width: 100%;">
                            ${kraOptionsHtml}
                        </select>
                    </div>
                    <div class="kra-locked-wrap d-none align-items-center justify-content-between px-2 py-1 bg-light border rounded text-dark fs-12" style="height: 31px; width: 100%;">
                        <div class="d-flex align-items-center gap-1.5 text-truncate" style="min-width: 0;">
                            <i class="feather-lock text-muted fs-11 flex-shrink-0" title="{{ addslashes(__('hrms.kra_kpi.fixed_standard_kpi_category')) }}"></i>
                            <span class="kra-locked-text text-truncate fw-medium text-dark"></span>
                        </div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0.5 px-1 fs-10 flex-shrink-0" style="font-size: 9px; line-height: 1.2;">{{ addslashes(__('hrms.kra_kpi.library_badge')) }}</span>
                    </div>
                    <div class="kra-input-wrap d-none align-items-center gap-1">
                        <input type="text" class="odoo-table-input kra-title-input" placeholder="{{ addslashes(__('hrms.kra_kpi.type_custom_kra')) }}" />
                        <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ addslashes(__('hrms.kra_kpi.switch_back_to_list')) }}" onclick="switchToTemplateKraSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                            <i class="feather-list"></i>
                        </button>
                    </div>
                    <input type="hidden" name="items[${uniqueIdx}][kra_category_id]" class="kra-final-id-input" />
                </td>
                <td>
                    <select name="items[${uniqueIdx}][calculation_type]" class="odoo-table-select odoo-select2" style="width: 100%;">
                        <option value="higher_is_better">{{ addslashes(__('hrms.kra_kpi.dir_higher_short')) }}</option>
                        <option value="lower_is_better">{{ addslashes(__('hrms.kra_kpi.dir_lower_short')) }}</option>
                        <option value="milestone">{{ addslashes(__('hrms.kra_kpi.dir_milestone_short')) }}</option>
                    </select>
                </td>
                <td>
                    <select name="items[${uniqueIdx}][unit]" class="odoo-table-select odoo-select2" style="width: 100%;">
                        <option value="percentage">%</option>
                        <option value="number">{{ addslashes(__('hrms.kra_kpi.unit_qty_short')) }}</option>
                        <option value="currency">{{ addslashes(__('hrms.kra_kpi.unit_currency_symbol')) }}</option>
                        <option value="rating">{{ addslashes(__('hrms.kra_kpi.unit_rating_scale')) }}</option>
                        <option value="boolean">{{ addslashes(__('hrms.kra_kpi.unit_milestone_label')) }}</option>
                    </select>
                </td>
                <td>
                    <input type="number" step="0.01" name="items[${uniqueIdx}][target]" value="100" class="odoo-table-input" required />
                </td>
                <td>
                    <input type="number" step="0.01" name="items[${uniqueIdx}][weightage]" value="20" class="odoo-table-input" required />
                </td>
                <td class="text-center">
                    <button type="button" class="btn erp-icon-btn erp-icon-btn--danger btn-sm remove-template-row" title="{{ addslashes(__('hrms.kra_kpi.remove_goal')) }}">
                        <i class="feather-trash-2"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);

            const $newRow = $(tr);
            $newRow.find('.odoo-select2').each(function() {
                const isSearchable = $(this).hasClass('kpi-template-select') || $(this).hasClass('kra-template-select');
                $(this).select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $('#editTemplateModal_' + tplId),
                    width: '100%',
                    minimumResultsForSearch: isSearchable ? 0 : Infinity
                });
            });

            updateModalWeightage(tbody);
        };

        // Delegated Row Removal Listener
        document.addEventListener('click', function(e) {
            if (e.target && e.target.closest('.remove-template-row')) {
                const btn = e.target.closest('.remove-template-row');
                const tr = btn.closest('tr');
                const tbody = tr?.closest('tbody');
                if (tbody && tbody.querySelectorAll('tr').length > 1) {
                    tr.remove();
                    updateModalWeightage(tbody);
                } else {
                    alert('{{ addslashes(__('hrms.kra_kpi.at_least_one_row_required')) }}');
                }
            }
        });

        // 1-Click Template Assignment Helper
        window.openAssignModalForTemplate = function(templateId, deptId) {
            const modalEl = document.getElementById('assignTemplateModal');
            if (!modalEl) return;

            const tplSelect = modalEl.querySelector('select[name="kpi_template_id"]');
            if (tplSelect) {
                $(tplSelect).val(templateId).trigger('change');
            }

            if (deptId) {
                const targetType = document.getElementById('assignmentTargetType');
                if (targetType) {
                    targetType.value = 'department';
                    targetType.dispatchEvent(new Event('change'));
                }
                const deptSelect = modalEl.querySelector('select[name="department_id"]');
                if (deptSelect) {
                    $(deptSelect).val(deptId).trigger('change');
                }
            }

            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        };

        // Ensure title and KRA inputs are synchronized and weightage is strictly 100% before submitting
        $(document).on('submit', '#createTemplateModal form, [id^="editTemplateModal_"] form', function(e) {
            // Synchronize any chosen standard KPI dropdowns or typed inputs
            $(this).find('tbody tr').each(function() {
                const select = $(this).find('.kpi-template-select');
                const input = $(this).find('.kpi-title-input');
                if (select.length && input.length && !input.val() && select.val() && select.val() !== '__custom__') {
                    input.val(select.val());
                }
                const kraSelect = $(this).find('.kra-template-select');
                const kraInput = $(this).find('.kra-title-input');
                const kraFinal = $(this).find('.kra-final-id-input');
                if (kraFinal.length && !kraFinal.val()) {
                    if (kraSelect.length && kraSelect.val() && kraSelect.val() !== '__custom__') {
                        kraFinal.val(kraSelect.val());
                    } else if (kraInput.length && kraInput.val()) {
                        kraFinal.val(kraInput.val());
                    }
                }
            });

            // Calculate total weight of valid KPI rows
            let totalWeight = 0;
            let validRowCount = 0;
            $(this).find('tbody tr').each(function() {
                const titleVal = $(this).find('.kpi-title-input').val();
                const weightVal = parseFloat($(this).find('input[name$="[weightage]"]').val());
                if (titleVal && titleVal.trim() !== '') {
                    validRowCount++;
                    if (!isNaN(weightVal)) {
                        totalWeight += weightVal;
                    }
                }
            });
            totalWeight = Math.round(totalWeight * 100) / 100;

            if (validRowCount === 0) {
                e.preventDefault();
                alert('{{ addslashes(__('hrms.kra_kpi.add_at_least_one_goal_msg')) }}');
                return false;
            }

            if (Math.abs(totalWeight - 100.0) > 0.01) {
                e.preventDefault();
                if (totalWeight > 100.0) {
                    alert(`${'{{ addslashes(__('hrms.kra_kpi.total_weight_exceed_msg', ['total' => '___TOTAL___'])) }}'.replace('___TOTAL___', totalWeight)}`);
                } else {
                    alert(`${'{{ addslashes(__('hrms.kra_kpi.total_weight_not_100_msg', ['total' => '___TOTAL___'])) }}'.replace('___TOTAL___', totalWeight)}`);
                }
                $(this).find('input[name$="[weightage]"]').first().focus();
                return false;
            }
        });

        // AJAX Quick Creation Handler for createKpiMasterModal
        const createKpiModalForm = document.querySelector('#createKpiMasterModal form');
        if (createKpiModalForm) {
            createKpiModalForm.addEventListener('submit', function(e) {
                if (window.lastActiveKpiSelect) {
                    e.preventDefault();
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> {{ addslashes(__('hrms.kra_kpi.saving')) }}';

                    const formData = new FormData(this);

                    fetch(this.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;

                        if (data.success && data.kpi) {
                            const k = data.kpi;
                            const optHtml = `<option value="${k.name}" data-kpi-id="${k.id}" data-kra-id="${k.kra_category_id}" data-calc="${k.calculation_type}" data-unit="${k.unit}" data-target="${k.default_target}" data-weight="${k.default_weightage}">${k.name} (${k.kra_category_name})</option>`;
                            kpiOptionsHtml += optHtml;

                            document.querySelectorAll('.kpi-options-group').forEach(group => {
                                group.insertAdjacentHTML('beforeend', optHtml);
                            });

                            if (window.lastActiveKpiSelect) {
                                window.lastActiveKpiSelect.value = k.name;
                                window.handleTemplateKpiSelect(window.lastActiveKpiSelect);
                                window.lastActiveKpiSelect = null;
                            }

                            const modalEl = document.getElementById('createKpiMasterModal');
                            if (modalEl) {
                                const bsModal = bootstrap.Modal.getInstance(modalEl);
                                if (bsModal) bsModal.hide();
                            }
                            createKpiModalForm.reset();
                        } else {
                            alert(data.message || '{{ addslashes(__('hrms.kra_kpi.error_creating_kpi')) }}');
                        }
                    })
                    .catch(err => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                        alert('{{ addslashes(__('hrms.kra_kpi.failed_save_kpi')) }}');
                    });
                }
            });
        }

        // Instant Debounce Search (Standard across HRMS)
        const searchInput = document.getElementById('kraSearchInput');
        const searchForm = document.getElementById('kraSearchForm');
        let searchTimeout;
        if (searchInput && searchForm) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    searchForm.submit();
                }, 500);
            });
        }

        // Tab 3: Interactive KRA & KPI Library Search and Filter
        const searchKraInput = document.getElementById('kraSearchInputLocal');
        const clearKraBtn = document.getElementById('kraClearSearchBtn');
        const kraItems = document.querySelectorAll('.kra-card-item');
        const noKraFound = document.getElementById('noKraFound');
        const kraCountBadge = document.getElementById('kraCountBadge');

        const searchKpiInput = document.getElementById('kpiSearchInputLocal');
        const clearKpiBtn = document.getElementById('kpiClearSearchBtn');
        const kpiRows = document.querySelectorAll('.kpi-master-row');
        const noKpiFoundRow = document.getElementById('noKpiFoundRow');
        const kpiCountBadge = document.getElementById('kpiCountBadge');
        const activeKraFilterBadge = document.getElementById('activeKraFilterBadge');
        const activeKraFilterName = document.getElementById('activeKraFilterName');
        const resetKraFilterBtn = document.getElementById('resetKraFilterBtn');

        let selectedKraId = null;

        function filterKpis() {
            const query = (searchKpiInput ? searchKpiInput.value.toLowerCase().trim() : '');
            let visibleCount = 0;

            if (clearKpiBtn) {
                if (query.length > 0) {
                    clearKpiBtn.classList.remove('d-none');
                } else {
                    clearKpiBtn.classList.add('d-none');
                }
            }

            kpiRows.forEach(row => {
                const kpiName = row.dataset.kpiName || '';
                const kraName = row.dataset.kraName || '';
                const kraId = row.dataset.kraId || '';
                const calc = row.dataset.calc || '';
                const unit = row.dataset.unit || '';
                const target = row.dataset.target || '';

                const matchesQuery = !query || 
                    kpiName.includes(query) || 
                    kraName.includes(query) || 
                    calc.includes(query) || 
                    unit.includes(query) ||
                    target.includes(query);

                const matchesKraSelection = !selectedKraId || kraId === selectedKraId;

                if (matchesQuery && matchesKraSelection) {
                    row.classList.remove('d-none');
                    visibleCount++;
                } else {
                    row.classList.add('d-none');
                }
            });

            if (kpiRows.length > 0) {
                if (visibleCount === 0) {
                    if (noKpiFoundRow) noKpiFoundRow.classList.remove('d-none');
                } else {
                    if (noKpiFoundRow) noKpiFoundRow.classList.add('d-none');
                }
            }
            if (kpiCountBadge) {
                kpiCountBadge.textContent = visibleCount;
            }
        }

        if (searchKpiInput) {
            searchKpiInput.addEventListener('input', filterKpis);
        }
        if (clearKpiBtn) {
            clearKpiBtn.addEventListener('click', function() {
                searchKpiInput.value = '';
                filterKpis();
                searchKpiInput.focus();
            });
        }

        function filterKras() {
            const query = (searchKraInput ? searchKraInput.value.toLowerCase().trim() : '');
            let visibleCount = 0;

            if (clearKraBtn) {
                if (query.length > 0) {
                    clearKraBtn.classList.remove('d-none');
                } else {
                    clearKraBtn.classList.add('d-none');
                }
            }

            kraItems.forEach(item => {
                const kraName = item.dataset.kraName || '';
                if (!query || kraName.includes(query)) {
                    item.classList.remove('d-none');
                    visibleCount++;
                } else {
                    item.classList.add('d-none');
                }
            });

            if (kraItems.length > 0) {
                if (visibleCount === 0) {
                    if (noKraFound) noKraFound.classList.remove('d-none');
                } else {
                    if (noKraFound) noKraFound.classList.add('d-none');
                }
            }
            if (kraCountBadge) {
                kraCountBadge.textContent = visibleCount;
            }
        }

        if (searchKraInput) {
            searchKraInput.addEventListener('input', filterKras);
        }
        if (clearKraBtn) {
            clearKraBtn.addEventListener('click', function() {
                searchKraInput.value = '';
                filterKras();
                searchKraInput.focus();
            });
        }

        // KRA item click to filter KPIs
        kraItems.forEach(item => {
            item.addEventListener('click', function(e) {
                if (e.target.closest('form') || e.target.closest('button')) {
                    return;
                }

                const kraId = item.dataset.kraId;
                const kraTitle = item.querySelector('.kra-title-text')?.textContent.trim() || '{{ addslashes(__('hrms.kra_kpi.selected_kra')) }}';

                if (selectedKraId === kraId) {
                    selectedKraId = null;
                    item.classList.remove('active-kra');
                    if (activeKraFilterBadge) activeKraFilterBadge.classList.add('d-none');
                } else {
                    kraItems.forEach(i => i.classList.remove('active-kra'));
                    item.classList.add('active-kra');
                    selectedKraId = kraId;
                    if (activeKraFilterBadge && activeKraFilterName) {
                        activeKraFilterName.textContent = kraTitle;
                        activeKraFilterBadge.classList.remove('d-none');
                        activeKraFilterBadge.classList.add('d-inline-flex');
                    }
                }
                filterKpis();
            });
        });

        if (resetKraFilterBtn) {
            resetKraFilterBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                selectedKraId = null;
                kraItems.forEach(i => i.classList.remove('active-kra'));
                if (activeKraFilterBadge) {
                    activeKraFilterBadge.classList.add('d-none');
                    activeKraFilterBadge.classList.remove('d-inline-flex');
                }
                filterKpis();
            });
        }
    });
</script>
@endpush

@push('styles')
<style>
    .select2-container--bootstrap-5 .select2-dropdown {
        min-width: 130px !important;
    }
    .select2-container--bootstrap-5 .select2-results__option {
        white-space: nowrap !important;
    }
</style>
@endpush

<x-ui.confirmation-modal />
@endsection
