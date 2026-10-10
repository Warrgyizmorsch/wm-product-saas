@extends('layouts.duralux')

@section('title', __('hrms.employees.probation_reviews') . ' | SaaS ERP')
@section('page-title', __('hrms.employees.probation_reviews'))
@section('breadcrumb', 'HRMS / ' . __('hrms.employees.title') . ' / ' . __('hrms.employees.probation_reviews'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-log-out" href="{{ route('hrms.exits.index') }}" class="fw-bold text-uppercase">
            {{ __('hrms.employees.offboarding_workspace') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Evaluation Modal Label Width & Select Adjustments (Prevents wrapping & text overlapping) */
        [id^="evaluateModal_"] .odoo-form-group {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        [id^="evaluateModal_"] .odoo-form-label {
            width: 210px !important;
            min-width: 210px !important;
            flex-shrink: 0 !important;
            white-space: nowrap !important;
            font-size: 13px !important;
        }
        [id^="evaluateModal_"] .odoo-form-control,
        [id^="evaluateModal_"] .select2-container--bootstrap-5 .select2-selection--single {
            border: none !important;
            border-bottom: 1px solid #ced4da !important;
            border-radius: 0 !important;
            background-color: transparent !important;
            padding-right: 20px !important;
            min-height: 28px !important;
        }
        [id^="evaluateModal_"] .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding-left: 2px !important;
            padding-right: 18px !important;
            font-size: 13px !important;
            white-space: nowrap !important;
        }
        [id^="evaluateModal_"] .select2-container--bootstrap-5 .select2-dropdown {
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
            z-index: 1065 !important;
        }
        [id^="evaluateModal_"] .select2-container--bootstrap-5 .select2-results__option {
            font-size: 13px !important;
            padding: 8px 14px !important;
        }
        [id^="evaluateModal_"] .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
            background-color: var(--bs-primary) !important;
            color: #fff !important;
        }

        /* Tabs Toolbar Clean Alignment */
        #probationTabs.erp-horizontal-tabs {
            border-bottom: none !important;
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
            padding-top: 0 !important;
        }

        /* Custom Avatar Initials */
        .avatar-initials {
            width: 38px;
            height: 38px;
            min-width: 38px;
            border-radius: 50%;
            background-color: rgba(var(--bs-primary-rgb), 0.12);
            color: var(--bs-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
        }

        .probation-table {
            table-layout: fixed !important;
            width: 100% !important;
        }
    </style>
@endpush

@section('content')
    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            {{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        @php
            $currentParams = [
                'department_id' => $departmentId,
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'eval_status' => $evalStatus,
            ];

            $tabs = [
                [
                    'id'         => 'tab-in-probation',
                    'label'      => __('hrms.employees.in_probation'),
                    'icon'       => 'feather-user-check',
                    'active'     => $filterStatus === 'in_probation' || empty($filterStatus),
                    'url'        => route('hrms.probation.index', array_merge($currentParams, ['status' => 'in_probation'])),
                    'badge'      => $totalInProbation,
                ],
                [
                    'id'         => 'tab-due-soon',
                    'label'      => __('hrms.employees.due_soon'),
                    'icon'       => 'feather-calendar',
                    'active'     => $filterStatus === 'due_soon',
                    'url'        => route('hrms.probation.index', array_merge($currentParams, ['status' => 'due_soon'])),
                    'badge'      => $dueSoonCount,
                ],
                [
                    'id'         => 'tab-overdue',
                    'label'      => __('hrms.employees.overdue'),
                    'icon'       => 'feather-alert-triangle',
                    'active'     => $filterStatus === 'overdue',
                    'url'        => route('hrms.probation.index', array_merge($currentParams, ['status' => 'overdue'])),
                    'badge'      => $overdueCount,
                ],
                [
                    'id'         => 'tab-confirmed',
                    'label'      => __('hrms.employees.confirmed_employees'),
                    'icon'       => 'feather-award',
                    'active'     => $filterStatus === 'confirmed',
                    'url'        => route('hrms.probation.index', array_merge($currentParams, ['status' => 'confirmed'])),
                    'badge'      => $confirmedThisMonthCount,
                ],
            ];
        @endphp

        <!-- Top Navigation Tabs & Toolbar Container -->
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 pb-2 border-bottom">
            <!-- Left: Tab Navigation via Horizontal Tabs Component -->
            <div class="w-auto" id="probationTabsWrapper">
                <x-ui.horizontal-tabs id="probationTabs" :tabs="$tabs" class="border-0 mb-0 p-0" />
            </div>

            <!-- Right: Search, Sort Dropdown & Filter Dropdown (Standard UI Elements) -->
            <div class="d-flex align-items-center gap-2 flex-wrap ms-lg-auto">
                <!-- Search Form (Common UI Element) -->
                <form method="GET" action="{{ route('hrms.probation.index') }}" id="probationSearchForm" class="m-0" style="min-width: 250px;">
                    <input type="hidden" name="status" value="{{ $filterStatus }}">
                    <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                    <input type="hidden" name="sort_order" value="{{ $sortOrder }}">
                    <input type="hidden" name="department_id" value="{{ $departmentId }}">
                    <input type="hidden" name="eval_status" value="{{ $evalStatus }}">
                    
                    <x-ui.icon-input
                        icon="feather-search"
                        name="search"
                        id="probation_search_input"
                        :value="$search"
                        placeholder="{{ __('hrms.employees.search_employees') }}"
                        autocomplete="off"
                    />
                </form>

                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'probation_end_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ ($sortBy === 'probation_end_date' && $sortOrder === 'asc') ? 'active' : '' }}">
                        <span>{{ __('hrms.employees.probation_end_nearest') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'probation_end_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ ($sortBy === 'probation_end_date' && $sortOrder === 'desc') ? 'active' : '' }}">
                        <span>{{ __('hrms.employees.probation_end_furthest') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'full_name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ ($sortBy === 'full_name' && $sortOrder === 'asc') ? 'active' : '' }}">
                        <span>{{ __('hrms.employees.emp_name_az') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'full_name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ ($sortBy === 'full_name' && $sortOrder === 'desc') ? 'active' : '' }}">
                        <span>{{ __('hrms.employees.emp_name_za') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'date_of_joining', 'sort_order' => 'desc']) }}" class="dropdown-item {{ ($sortBy === 'date_of_joining' && $sortOrder === 'desc') ? 'active' : '' }}">
                        <span>{{ __('hrms.employees.sort_doj_desc') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'date_of_joining', 'sort_order' => 'asc']) }}" class="dropdown-item {{ ($sortBy === 'date_of_joining' && $sortOrder === 'asc') ? 'active' : '' }}">
                        <span>{{ __('hrms.employees.sort_doj_asc') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <form method="GET" action="{{ route('hrms.probation.index') }}" class="d-inline">
                    <input type="hidden" name="status" value="{{ $filterStatus }}">
                    <input type="hidden" name="search" value="{{ $search }}">
                    <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                    <input type="hidden" name="sort_order" value="{{ $sortOrder }}">

                    <x-ui.filter label="{{ __('hrms.common.filter') }}" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_department') }}</label>
                            <x-ui.odoo-form-ui type="select" name="department_id">
                                <option value="">{{ __('hrms.common.all_departments') }}</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" @selected($departmentId == $dept->id)>{{ $dept->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="eval_status">
                                <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                <option value="reviewed" @selected($evalStatus === 'reviewed')>{{ __('hrms.common.reviewed') }}</option>
                                <option value="unreviewed" @selected($evalStatus === 'unreviewed')>{{ __('hrms.common.pending') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        
                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <x-ui.button variant="light" size="sm" href="{{ route('hrms.probation.index', ['status' => $filterStatus]) }}" class="border">
                                {{ __('hrms.common.reset') }}
                            </x-ui.button>
                            <x-ui.button variant="primary" size="sm" type="submit">
                                {{ __('hrms.common.apply') }}
                            </x-ui.button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Table Container -->
        <div class="table-responsive border-0">
            <table class="table table-hover align-middle mb-0 text-dark probation-table" style="table-layout: fixed; width: 100%;">
                <thead class="table-light fs-11 text-uppercase tracking-wider">
                    <tr>
                        <th class="ps-3 py-3" style="width: 24%;">{{ __('hrms.employees.tbl_employee') }}</th>
                        <th class="py-3" style="width: 14%;">{{ __('hrms.employees.reporting_manager') }}</th>
                        <th class="py-3" style="width: 11%;">{{ __('hrms.employees.lbl_doj') }}</th>
                        <th class="py-3" style="width: 13%;">{{ __('hrms.employees.probation_end_date') }}</th>
                        <th class="py-3" style="width: 14%;">{{ __('hrms.employees.evaluation_score') }}</th>
                        <th class="text-end pe-3 py-3" style="width: 24%;">{{ __('hrms.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody id="probationTableBody">
                    @forelse($employees as $emp)
                        @php
                            $today = \Carbon\Carbon::today();
                            $probEnd = $emp->probation_end_date ? \Carbon\Carbon::parse($emp->probation_end_date)->startOfDay() : null;
                            $diffDays = $probEnd ? (int) $today->diffInDays($probEnd, false) : null;
                            $isOverdue = $probEnd && $diffDays < 0 && $emp->employee_stage === 'Probation';
                            $isDueSoon = $probEnd && $diffDays >= 0 && $diffDays <= 15 && $emp->employee_stage === 'Probation';
                            $lastEval = $emp->probationEvaluations->first();
                        @endphp
                        <tr>
                            <td class="ps-3 py-3">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="avatar-initials flex-shrink-0 mt-0.5">
                                        {{ strtoupper(substr($emp->full_name, 0, 2)) }}
                                    </div>
                                    <div class="overflow-hidden" style="min-width: 0;">
                                        <a href="{{ route('hrms.employees.show', $emp->id) }}" class="fw-bold text-dark text-decoration-none d-block fs-13 lh-sm mb-1.5 text-truncate" title="{{ $emp->full_name }}">
                                            {{ $emp->full_name }}
                                        </a>
                                        <div class="text-dark fw-medium fs-12 lh-sm mb-1 text-truncate" title="{{ $emp->designation->name ?? __('hrms.common.na') }}">
                                            {{ $emp->designation->name ?? __('hrms.common.na') }}
                                        </div>
                                        <div class="text-muted fs-11 lh-sm mb-1" title="{{ $emp->department->name ?? __('hrms.common.general') }}">
                                            {{ $emp->department->name ?? __('hrms.common.general') }}
                                        </div>
                                        <div class="text-muted fs-11 lh-sm text-truncate" title="{{ $emp->employee_id }} &bull; {{ $emp->office_email ?: ($emp->user?->email ?: ($emp->personal_email ?: __('hrms.employees.no_email'))) }}">
                                            {{ $emp->employee_id }} &bull; {{ $emp->office_email ?: ($emp->user?->email ?: ($emp->personal_email ?: __('hrms.employees.no_email'))) }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($emp->reportingManager)
                                    <div class="overflow-hidden" style="min-width: 0;">
                                        <div class="fw-medium text-dark fs-12 lh-sm text-truncate" title="{{ $emp->reportingManager->full_name }}">{{ $emp->reportingManager->full_name }}</div>
                                        <span class="text-muted fs-11 d-block text-truncate">{{ $emp->reportingManager->employee_id }}</span>
                                    </div>
                                @else
                                    <span class="text-muted fs-12 fst-italic">{{ __('hrms.common.not_assigned') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-medium text-dark fs-12 lh-sm">{{ $emp->date_of_joining ? \Carbon\Carbon::parse($emp->date_of_joining)->format('d M, Y') : __('hrms.common.na') }}</div>
                                <span class="text-muted fs-11 d-block">{{ $emp->date_of_joining ? \Carbon\Carbon::parse($emp->date_of_joining)->diffForHumans() : '' }}</span>
                            </td>
                            <td>
                                @if($emp->employee_stage === 'Confirmed')
                                    <x-ui.badge soft variant="success" class="fs-10 text-truncate d-inline-block" style="max-width: 100%;">
                                        <i class="feather-check-circle me-1"></i> {{ __('hrms.employees.confirmed') }}
                                    </x-ui.badge>
                                @elseif($probEnd)
                                    <div class="fw-bold fs-12 lh-sm {{ $isOverdue ? 'text-danger' : ($isDueSoon ? 'text-warning' : 'text-dark') }}">
                                        {{ $probEnd->format('d M, Y') }}
                                    </div>
                                    @if($isOverdue)
                                        <x-ui.badge soft variant="danger" class="fs-10 mt-0.5">
                                            {{ __('hrms.employees.overdue_by_days', ['count' => abs($diffDays)]) }}
                                        </x-ui.badge>
                                    @elseif($isDueSoon)
                                        <x-ui.badge soft variant="warning" class="fs-10 mt-0.5">
                                            {{ __('hrms.employees.due_in_days', ['count' => abs($diffDays)]) }}
                                        </x-ui.badge>
                                    @else
                                        <span class="text-muted fs-11 d-block">{{ $probEnd->diffForHumans() }}</span>
                                    @endif
                                @else
                                    <span class="text-muted fs-12">{{ __('hrms.common.not_set') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($lastEval)
                                    <div class="d-flex align-items-center gap-1 mb-0.5 flex-wrap">
                                        <x-ui.badge soft variant="primary" class="fs-10 px-1.5 py-0.5">
                                            ★ {{ $lastEval->average_rating }}/5
                                        </x-ui.badge>
                                        <x-ui.badge soft variant="{{ $lastEval->recommendation === 'confirm' ? 'success' : ($lastEval->recommendation === 'extend' ? 'warning' : 'danger') }}" class="fs-10 px-1.5 py-0.5 text-uppercase">
                                            {{ $lastEval->recommendation }}
                                        </x-ui.badge>
                                    </div>
                                    <span class="text-muted fs-11 d-block">{{ $lastEval->evaluation_date ? \Carbon\Carbon::parse($lastEval->evaluation_date)->format('d M, Y') : __('hrms.common.na') }}</span>
                                @else
                                    <x-ui.badge soft variant="secondary" class="fs-11 px-2 py-0.5">{{ __('hrms.employees.no_review_logged') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex align-items-center justify-content-end gap-2 flex-nowrap">
                                    @if($emp->employee_stage === 'Probation')
                                        <x-ui.button variant="primary" size="sm" icon="feather-check-square" data-bs-toggle="modal" data-bs-target="#evaluateModal_{{ $emp->id }}" class="px-2.5 py-1 fs-11 text-nowrap fw-semibold">
                                            {{ __('hrms.employees.btn_review_evaluate') }}
                                        </x-ui.button>

                                        <x-ui.button variant="outline-success" size="sm" icon="feather-award" data-bs-toggle="modal" data-bs-target="#confirmEmployeeModal_{{ $emp->id }}" class="px-2.5 py-1 fs-11 text-nowrap fw-semibold" title="{{ __('hrms.employees.confirm_employee_btn') }}">
                                            {{ __('hrms.employees.btn_quick_confirm') }}
                                        </x-ui.button>
                                    @else
                                        <x-ui.badge soft variant="success" class="px-2.5 py-1 text-nowrap fs-11">
                                            <i class="feather-check me-1"></i> {{ __('hrms.employees.confirmed') }}
                                        </x-ui.badge>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                    <i class="feather-user-check fs-24"></i>
                                </div>
                                <h6 class="fw-bold mb-1 text-dark">{{ __('hrms.employees.no_probation_employees_found') }}</h6>
                                <p class="fs-13 mb-0 text-muted">{{ __('hrms.employees.no_probation_employees_desc') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div id="probationPaginationWrapper">
            <x-ui.pagination :paginator="$employees" />
        </div>
    </div>

    <!-- Review & Evaluation Modals Container -->
    <div id="probationModalsWrapper">
        @foreach($employees as $emp)
            @if($emp->employee_stage === 'Probation')
                <div class="modal fade text-start" id="evaluateModal_{{ $emp->id }}" tabindex="-1" aria-labelledby="evaluateModalLabel_{{ $emp->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-0 shadow-lg rounded-4">
                            <div class="modal-header bg-light border-bottom p-4">
                                <div>
                                    <h5 class="modal-title fw-bold text-dark mb-1" id="evaluateModalLabel_{{ $emp->id }}">{{ __('hrms.employees.probation_evaluation') }}</h5>
                                    <p class="text-muted fs-13 mb-0">{{ __('hrms.employees.probation_eval_subtitle', ['name' => $emp->full_name, 'id' => $emp->employee_id]) }}</p>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="{{ route('hrms.probation.evaluate', $emp->id) }}">
                                @csrf
                                <div class="modal-body p-4">
                                    <!-- Ratings Section (Individual Full-Width Rows) -->
                                    <div class="row g-3 mb-4">
                                        <div class="col-12">
                                            <x-ui.odoo-form-ui type="select" label="1. {{ __('hrms.employees.performance_execution') }}" name="performance_rating" :required="true">
                                                <option value="5">{{ __('hrms.employees.perf_5') }}</option>
                                                <option value="4" selected>{{ __('hrms.employees.perf_4') }}</option>
                                                <option value="3">{{ __('hrms.employees.perf_3') }}</option>
                                                <option value="2">{{ __('hrms.employees.perf_2') }}</option>
                                                <option value="1">{{ __('hrms.employees.perf_1') }}</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="col-12">
                                            <x-ui.odoo-form-ui type="select" label="2. {{ __('hrms.employees.attendance_punctuality') }}" name="attendance_rating" :required="true">
                                                <option value="5">{{ __('hrms.employees.att_5') }}</option>
                                                <option value="4" selected>{{ __('hrms.employees.att_4') }}</option>
                                                <option value="3">{{ __('hrms.employees.att_3') }}</option>
                                                <option value="2">{{ __('hrms.employees.att_2') }}</option>
                                                <option value="1">{{ __('hrms.employees.att_1') }}</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="col-12">
                                            <x-ui.odoo-form-ui type="select" label="3. {{ __('hrms.employees.culture_fit_teamwork') }}" name="culture_rating" :required="true">
                                                <option value="5">{{ __('hrms.employees.cult_5') }}</option>
                                                <option value="4" selected>{{ __('hrms.employees.cult_4') }}</option>
                                                <option value="3">{{ __('hrms.employees.cult_3') }}</option>
                                                <option value="2">{{ __('hrms.employees.cult_2') }}</option>
                                                <option value="1">{{ __('hrms.employees.cult_1') }}</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                    </div>

                                    <!-- Recommendation Box -->
                                    <div class="p-3 bg-light rounded-3 border mb-4">
                                        <label class="form-label fw-bold text-dark fs-12 text-uppercase mb-2 d-block">{{ __('hrms.employees.final_recommendation') }}</label>
                                        <div class="d-flex align-items-center gap-4 flex-wrap mt-1">
                                            <div class="form-check erp-premium-radio d-flex align-items-center gap-2 m-0">
                                                <input class="form-check-input" type="radio" name="recommendation" id="rec_confirm_{{ $emp->id }}" value="confirm" checked onchange="handleRecommendationChange({{ $emp->id }}, 'confirm')">
                                                <label class="form-check-label fw-semibold text-success fs-13 d-flex align-items-center gap-1.5 cursor-pointer" for="rec_confirm_{{ $emp->id }}">
                                                    <i class="feather-check-circle fs-15"></i> {{ __('hrms.employees.formally_confirm') }}
                                                </label>
                                            </div>
                                            <div class="form-check erp-premium-radio d-flex align-items-center gap-2 m-0">
                                                <input class="form-check-input" type="radio" name="recommendation" id="rec_extend_{{ $emp->id }}" value="extend" onchange="handleRecommendationChange({{ $emp->id }}, 'extend')">
                                                <label class="form-check-label fw-semibold text-warning fs-13 d-flex align-items-center gap-1.5 cursor-pointer" for="rec_extend_{{ $emp->id }}">
                                                    <i class="feather-refresh-cw fs-15"></i> {{ __('hrms.employees.extend_probation_period') }}
                                                </label>
                                            </div>
                                            <div class="form-check erp-premium-radio d-flex align-items-center gap-2 m-0">
                                                <input class="form-check-input" type="radio" name="recommendation" id="rec_terminate_{{ $emp->id }}" value="terminate" onchange="handleRecommendationChange({{ $emp->id }}, 'terminate')">
                                                <label class="form-check-label fw-semibold text-danger fs-13 d-flex align-items-center gap-1.5 cursor-pointer" for="rec_terminate_{{ $emp->id }}">
                                                    <i class="feather-x-circle fs-15"></i> {{ __('hrms.employees.recommend_termination') }}
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Extension Options Box -->
                                        <div id="extension_container_{{ $emp->id }}" class="mt-3 p-3 bg-white rounded-3 border border-warning border-opacity-25 d-none">
                                            <div style="max-width: 380px;">
                                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.extension_duration') }}" name="extension_days">
                                                    <option value="30">{{ __('hrms.employees.days_1_month') }}</option>
                                                    <option value="60">{{ __('hrms.employees.days_2_months') }}</option>
                                                    <option value="90">{{ __('hrms.employees.days_3_months') }}</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                        </div>

                                        <!-- Termination Details Box -->
                                        <div id="termination_container_{{ $emp->id }}" class="mt-3 p-3 bg-white rounded-3 border border-danger border-opacity-25 d-none">
                                            <div class="d-flex align-items-center gap-2 mb-2 text-danger fw-bold fs-13">
                                                <i class="feather-alert-triangle"></i> {{ __('hrms.employees.involuntary_separation_details') }}
                                            </div>
                                            <p class="text-muted fs-12 mb-3">
                                                {{ __('hrms.employees.involuntary_separation_desc') }}
                                            </p>

                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold fs-12 text-uppercase text-muted mb-1 d-block">{{ __('hrms.employees.termination_mode') }}</label>
                                                    <div class="d-flex gap-3 mt-2">
                                                        <div class="form-check erp-premium-radio d-flex align-items-center gap-2 m-0">
                                                            <input class="form-check-input" type="radio" name="termination_mode" id="term_mode_notice_{{ $emp->id }}" value="notice" checked onchange="toggleTerminationNotice({{ $emp->id }}, true)">
                                                            <label class="form-check-label fs-13 text-dark cursor-pointer" for="term_mode_notice_{{ $emp->id }}">
                                                                {{ __('hrms.employees.serve_notice') }}
                                                            </label>
                                                        </div>
                                                        <div class="form-check erp-premium-radio d-flex align-items-center gap-2 m-0">
                                                            <input class="form-check-input" type="radio" name="termination_mode" id="term_mode_imm_{{ $emp->id }}" value="immediate" onchange="toggleTerminationNotice({{ $emp->id }}, false)">
                                                            <label class="form-check-label fs-13 text-dark cursor-pointer" for="term_mode_imm_{{ $emp->id }}">
                                                                {{ __('hrms.employees.immediate_today') }}
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6" id="term_notice_days_box_{{ $emp->id }}">
                                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.notice_duration') }}" name="termination_notice_days">
                                                        <option value="7">{{ __('hrms.employees.notice_7_days') }}</option>
                                                        <option value="15" selected>{{ __('hrms.employees.notice_15_days') }}</option>
                                                        <option value="30">{{ __('hrms.employees.notice_30_days') }}</option>
                                                    </x-ui.odoo-form-ui>
                                                </div>

                                                <div class="col-12">
                                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.primary_reason_category') }}" name="termination_reason_category">
                                                        <option value="Performance / Skill Gap">{{ __('hrms.employees.reason_perf_skill') }}</option>
                                                        <option value="Cultural / Team Misalignment">{{ __('hrms.employees.reason_cultural_misalignment') }}</option>
                                                        <option value="Attendance & Discipline">{{ __('hrms.employees.reason_attendance_discipline') }}</option>
                                                        <option value="Role Fit / Restructuring">{{ __('hrms.employees.reason_role_restructuring') }}</option>
                                                        <option value="Probation Unsuccessful" selected>{{ __('hrms.employees.reason_general_unsuccessful') }}</option>
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold fs-12 text-uppercase text-muted mb-1.5 d-block">{{ __('hrms.employees.eval_comments_notes') }}</label>
                                        <textarea name="remarks" class="form-control" rows="3" placeholder="{{ __('hrms.employees.eval_comments_placeholder') }}"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light border-top p-3">
                                    <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">
                                        {{ __('hrms.common.cancel') }}
                                    </x-ui.button>
                                    <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                                        {{ __('hrms.employees.submit_evaluation') }}
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Confirm Employee Modal (Replaces browser alert with structured confirmation modal) -->
                <div class="modal fade text-start" id="confirmEmployeeModal_{{ $emp->id }}" tabindex="-1" aria-labelledby="confirmEmployeeModalLabel_{{ $emp->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg rounded-4">
                            <div class="modal-header bg-light border-bottom p-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle bg-soft-success text-success" style="width: 36px; height: 36px;">
                                        <i class="feather-award fs-18"></i>
                                    </div>
                                    <div>
                                        <h6 class="modal-title fw-bold text-dark mb-0" id="confirmEmployeeModalLabel_{{ $emp->id }}">{{ __('hrms.employees.confirm_employee_modal_title') }}</h6>
                                        <span class="text-muted fs-11">{{ __('hrms.employees.official_confirmation_decision') }}</span>
                                    </div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="{{ route('hrms.probation.quick-confirm', $emp->id) }}">
                                @csrf
                                <div class="modal-body p-4">
                                    <!-- Employee Summary Card -->
                                    <div class="p-3 bg-light rounded-3 border mb-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                            <div>
                                                <div class="fw-bold text-dark fs-13">{{ $emp->full_name }}</div>
                                                <div class="text-muted fs-11">{{ $emp->employee_id }} &bull; {{ $emp->designation->name ?? __('hrms.common.na') }}</div>
                                            </div>
                                            <x-ui.badge soft variant="warning" class="fs-10">
                                                {{ $emp->employee_stage }}
                                            </x-ui.badge>
                                        </div>
                                        <div class="row g-2 fs-11 text-muted">
                                            <div class="col-6">
                                                <span>{{ __('hrms.employees.tbl_department') }}:</span> <strong class="text-dark">{{ $emp->department->name ?? __('hrms.common.general') }}</strong>
                                            </div>
                                            <div class="col-6">
                                                <span>{{ __('hrms.employees.lbl_doj') }}:</span> <strong class="text-dark">{{ $emp->date_of_joining ? \Carbon\Carbon::parse($emp->date_of_joining)->format('d M, Y') : __('hrms.common.na') }}</strong>
                                            </div>
                                            <div class="col-6">
                                                <span>{{ __('hrms.employees.probation_end_date') }}:</span> <strong class="text-dark">{{ $emp->probation_end_date ? \Carbon\Carbon::parse($emp->probation_end_date)->format('d M, Y') : __('hrms.common.na') }}</strong>
                                            </div>
                                            <div class="col-6">
                                                <span>{{ __('hrms.employees.reporting_manager') }}:</span> <strong class="text-dark">{{ $emp->reportingManager->full_name ?? __('hrms.common.na') }}</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-success bg-soft-success border border-success border-opacity-25 rounded-3 p-2.5 mb-3 fs-12 text-dark">
                                        <i class="feather-check-circle text-success me-1"></i>
                                        {{ __('hrms.employees.confirm_notice_desc') }}
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.effective_confirmation_date') }} <span class="text-danger">*</span></label>
                                        <x-ui.odoo-form-ui type="input" inputType="date" name="confirmation_date" :value="date('Y-m-d')" :required="true" />
                                    </div>

                                    <div class="mb-0">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.confirmation_remarks') }}</label>
                                        <textarea name="remarks" class="form-control fs-12" rows="2" placeholder="{{ __('hrms.employees.confirmation_remarks_placeholder') }}"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                                    <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-3 fw-semibold">{{ __('hrms.common.cancel') }}</x-ui.button>
                                    <x-ui.button variant="success" type="submit" class="px-4 fw-bold">
                                        <i class="feather-check-circle me-1"></i> {{ __('hrms.employees.confirm_employee_btn') }}
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
<script>
    function handleRecommendationChange(empId, recValue) {
        const extBox = document.getElementById('extension_container_' + empId);
        const termBox = document.getElementById('termination_container_' + empId);
        
        if (extBox) extBox.classList.add('d-none');
        if (termBox) termBox.classList.add('d-none');

        if (recValue === 'extend' && extBox) {
            extBox.classList.remove('d-none');
        } else if (recValue === 'terminate' && termBox) {
            termBox.classList.remove('d-none');
        }
    }

    function toggleTerminationNotice(empId, showNotice) {
        const noticeBox = document.getElementById('term_notice_days_box_' + empId);
        if (noticeBox) {
            if (showNotice) {
                noticeBox.classList.remove('d-none');
            } else {
                noticeBox.classList.add('d-none');
            }
        }
    }

    function moveProbationModalsToBody() {
        $('#probationModalsWrapper .modal, .modal[id^="evaluateModal_"], .modal[id^="confirmEmployeeModal_"]').each(function() {
            if ($(this).parent().get(0) !== document.body) {
                $(this).appendTo(document.body);
            }
        });
    }

    $(document).ready(function() {
        moveProbationModalsToBody();
    });

    $(document).on('show.bs.modal', '.modal', function () {
        if ($(this).parent().get(0) !== document.body) {
            $(this).appendTo(document.body);
        }
    });

    let probationSearchTimeout = null;
    let activeProbationRequest = null;

    function refreshProbationList(targetUrl) {
        if (activeProbationRequest) {
            activeProbationRequest.abort();
        }
        const controller = new AbortController();
        activeProbationRequest = controller;

        const tableBody = document.getElementById('probationTableBody');
        if (tableBody) {
            tableBody.style.opacity = '0.5';
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

            const newTbody = doc.getElementById('probationTableBody');
            const oldTbody = document.getElementById('probationTableBody');
            if (newTbody && oldTbody) {
                oldTbody.innerHTML = newTbody.innerHTML;
            }

            const newTabs = doc.getElementById('probationTabsWrapper');
            const oldTabs = document.getElementById('probationTabsWrapper');
            if (newTabs && oldTabs) {
                oldTabs.innerHTML = newTabs.innerHTML;
            }

            const newPagination = doc.getElementById('probationPaginationWrapper');
            const oldPagination = document.getElementById('probationPaginationWrapper');
            if (newPagination && oldPagination) {
                oldPagination.innerHTML = newPagination.innerHTML;
            }

            const newModals = doc.getElementById('probationModalsWrapper');
            const oldModals = document.getElementById('probationModalsWrapper');
            if (newModals && oldModals) {
                oldModals.innerHTML = newModals.innerHTML;
                moveProbationModalsToBody();
            }

            // Sync hidden inputs in filter dropdowns
            const searchVal = targetUrl.searchParams.get('search') || '';
            $('input[name="search"]').not('#probation_search_input').val(searchVal);

            // Update browser URL without full page reload
            history.pushState(null, '', targetUrl.toString());
        })
        .catch(function (error) {
            if (error.name !== 'AbortError') {
                window.location.href = targetUrl.toString();
            }
        })
        .finally(function () {
            if (activeProbationRequest === controller) {
                if (tableBody) {
                    tableBody.style.opacity = '1';
                }
                activeProbationRequest = null;
            }
        });
    }

    // 1. Debounced live search on typing (works automatically as you write without clicking Enter)
    $(document).on('input', '#probation_search_input', function () {
        const form = this.closest('form');
        if (!form) return;
        const url = new URL(form.action || window.location.href);
        
        const formData = new FormData(form);
        for (const [key, val] of formData.entries()) {
            url.searchParams.set(key, val);
        }
        url.searchParams.delete('page');

        clearTimeout(probationSearchTimeout);
        probationSearchTimeout = setTimeout(function () {
            refreshProbationList(url);
        }, 250);
    });

    // 2. Form submit prevention for search form
    $(document).on('submit', '#probationSearchForm', function (e) {
        e.preventDefault();
        const url = new URL(this.action || window.location.href);
        const formData = new FormData(this);
        for (const [key, val] of formData.entries()) {
            url.searchParams.set(key, val);
        }
        url.searchParams.delete('page');
        clearTimeout(probationSearchTimeout);
        refreshProbationList(url);
    });

    // 3. Intercept pagination clicks for instant page changes
    $(document).on('click', '#probationPaginationWrapper a.page-link', function (e) {
        e.preventDefault();
        const href = this.getAttribute('href');
        if (href && href !== '#' && !href.startsWith('javascript:')) {
            const url = new URL(href, window.location.origin);
            refreshProbationList(url);
        }
    });

    $(document).on('shown.bs.modal', '[id^="evaluateModal_"]', function () {
        var $modal = $(this);
        $modal.find('.probation-select2').each(function() {
            var $select = $(this);
            if (!$select.hasClass('select2-hidden-accessible')) {
                $select.select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $modal.find('.modal-content'),
                    minimumResultsForSearch: Infinity,
                    width: '100%'
                });
            }
        });
    });
</script>
@endpush
