@extends('layouts.duralux')

@section('title', __('hrms.goals.page_title_meta'))
@section('page-title', __('hrms.goals.page_title'))
@section('breadcrumb', __('hrms.goals.breadcrumb'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus-circle" data-bs-toggle="modal" data-bs-target="#createGoalModal" class="fw-bold">
            {{ __('hrms.goals.create_objective_goal') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
<style>
    .avatar-initials {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: rgba(var(--bs-primary-rgb), 0.12) !important;
        color: var(--bs-primary) !important;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 12px;
        flex-shrink: 0;
    }
    .badge.bg-primary {
        background-color: var(--bs-primary) !important;
        color: #ffffff !important;
    }
    .badge.bg-primary-subtle {
        background-color: rgba(var(--bs-primary-rgb), 0.12) !important;
        color: var(--bs-primary) !important;
        border: 1px solid rgba(var(--bs-primary-rgb), 0.28) !important;
    }
    .text-primary {
        color: var(--bs-primary) !important;
    }
    .progress-bar.bg-primary {
        background-color: var(--bs-primary) !important;
    }

    /* Goal Alignment Tree Styling */
    .tree-branch {
        position: relative;
        padding-left: 28px;
        margin-top: 16px;
    }
    .tree-branch::before {
        content: '';
        position: absolute;
        left: 8px;
        top: 0;
        bottom: 0;
        width: 2px;
        background-color: #e2e8f0;
    }
    .tree-node-connector {
        position: absolute;
        left: 8px;
        top: 24px;
        width: 20px;
        height: 2px;
        background-color: #e2e8f0;
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
            $goalTabs = [
                [
                    'id' => 'company-goals',
                    'label' => __('hrms.goals.tab_company_strategic'),
                    'active' => ($activeTab === 'company_goals'),
                    'icon' => 'feather-briefcase',
                ],
                [
                    'id' => 'my-team',
                    'label' => __('hrms.goals.tab_my_team_goals'),
                    'active' => ($activeTab === 'my_team_goals'),
                    'icon' => 'feather-user-check',
                ],
                [
                    'id' => 'alignment-tree',
                    'label' => __('hrms.goals.tab_alignment_tree'),
                    'active' => ($activeTab === 'alignment_tree'),
                    'icon' => 'feather-git-merge',
                ],
            ];
            if ($isHrAdmin) {
                $goalTabs[] = [
                    'id' => 'cycles-pillars',
                    'label' => __('hrms.goals.tab_cycles_pillars'),
                    'active' => ($activeTab === 'cycles_pillars'),
                    'icon' => 'feather-layers',
                ];
            }
        @endphp

        <!-- Navigation Tabs Bar with Common UI Component -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 border-bottom pb-2">
            <div class="flex-grow-1" style="min-width: 0;">
                <x-ui.horizontal-tabs id="goalTabs" :tabs="$goalTabs" />
            </div>
        </div>

        <!-- 3. TAB CONTENT WORKSPACES -->
        <div class="tab-content" id="goalTabContent">

            <!-- TAB 1: COMPANY & STRATEGIC GOALS -->
            <div class="tab-pane fade {{ $activeTab === 'company_goals' ? 'show active' : '' }}" id="company-goals" role="tabpanel">
                
                <!-- TOP TOOLBAR: TITLE + SEARCH, SORT, FILTER (RIGHT CORNER) -->
                <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><i class="feather-briefcase me-2 text-primary"></i> {{ __('hrms.goals.company_strategic_objectives') }}</h5>
                        <p class="text-muted fs-12 mb-0">{{ __('hrms.goals.company_strategic_desc') }}</p>
                    </div>

                    <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                        <!-- Search Input Form (Matches standard HRMS pattern: Auto-submits on type, loads URL parameter & keeps cursor) -->
                        <form method="GET" action="{{ route('hrms.goals.index') }}" id="companyGoalsSearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 250px; height: 38px !important; box-sizing: border-box !important;">
                            <input type="hidden" name="active_tab" value="company_goals">
                            @if(request()->filled('cycle_id')) <input type="hidden" name="cycle_id" value="{{ request('cycle_id') }}"> @endif
                            @if(request()->filled('category_id')) <input type="hidden" name="category_id" value="{{ request('category_id') }}"> @endif
                            @if(request()->filled('health_status')) <input type="hidden" name="health_status" value="{{ request('health_status') }}"> @endif
                            @if(request()->filled('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                            @if(request()->filled('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
                            <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                            <input type="text" name="search" id="companyGoalSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13 goal-search-input" placeholder="{{ __('hrms.goals.search_placeholder') }}" value="{{ request('search') }}" autocomplete="off" style="box-shadow: none; height: 100%; outline: none;">
                        </form>

                        <!-- Sort Dropdown -->
                        <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort', 'newest') === 'newest' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('newest')">
                                <span>{{ __('hrms.goals.sort_newest') }}</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'oldest' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('oldest')">
                                <span>{{ __('hrms.goals.sort_oldest') }}</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'progress_desc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('progress_desc')">
                                <span>{{ __('hrms.goals.sort_progress_desc') }}</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'progress_asc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('progress_asc')">
                                <span>{{ __('hrms.goals.sort_progress_asc') }}</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'due_date' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('due_date')">
                                <span>{{ __('hrms.goals.sort_due_date') }}</span>
                            </a>
                        </x-ui.sort-dropdown>

                        <!-- Filter Dropdown -->
                        <x-ui.filter label="{{ __('hrms.common.filter') }}" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                            <form method="GET" action="{{ route('hrms.goals.index') }}" class="goalFilterForm">
                                <input type="hidden" name="active_tab" value="company_goals">
                                <input type="hidden" name="sort" class="goal_sort_input" value="{{ request('sort', 'newest') }}">
                                <input type="hidden" name="search" class="filter_search_hidden" value="{{ request('search') }}">

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.goals.goal_cycle') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="cycle_id">
                                        <option value="">{{ __('hrms.goals.all_goal_cycles') }}</option>
                                        @foreach($cycles as $c)
                                            <option value="{{ $c->id }}" {{ ($selectedCycleId == $c->id && request()->has('cycle_id')) || request('cycle_id') == $c->id ? 'selected' : '' }}>
                                                {{ $c->name }} ({{ strtoupper($c->status) }})
                                            </option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.goals.strategic_pillar') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="category_id">
                                        <option value="">{{ __('hrms.goals.all_strategic_pillars') }}</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.goals.health_status') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="health_status">
                                        <option value="">{{ __('hrms.goals.all_health_statuses') }}</option>
                                        <option value="on_track" {{ request('health_status') == 'on_track' ? 'selected' : '' }}>🟢 {{ __('hrms.goals.health_on_track') }}</option>
                                        <option value="at_risk" {{ request('health_status') == 'at_risk' ? 'selected' : '' }}>🟡 {{ __('hrms.goals.health_at_risk') }}</option>
                                        <option value="behind" {{ request('health_status') == 'behind' ? 'selected' : '' }}>🔴 {{ __('hrms.goals.health_behind') }}</option>
                                        <option value="completed" {{ request('health_status') == 'completed' ? 'selected' : '' }}>🔵 {{ __('hrms.goals.health_completed') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.common.status') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="status">
                                        <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('hrms.common.active') }}</option>
                                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>{{ __('hrms.goals.status_draft') }}</option>
                                        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>{{ __('hrms.goals.status_closed') }}</option>
                                        <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>{{ __('hrms.goals.status_archived') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="dropdown-divider my-3"></div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">{{ __('hrms.common.apply') }}</button>
                                    <a href="{{ route('hrms.goals.index', ['active_tab' => 'company_goals']) }}" class="btn btn-light btn-sm border flex-grow-1 text-center text-decoration-none">{{ __('hrms.common.reset') }}</a>
                                </div>
                            </form>
                        </x-ui.filter>
                    </div>
                </div>

                @if($companyGoals->isEmpty())
                    <div class="text-center py-5">
                        <div class="p-3 bg-light rounded-circle d-inline-flex mb-3 text-muted">
                            <i class="feather-briefcase fs-36"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">{{ __('hrms.goals.no_company_goals_found') }}</h6>
                        <p class="text-muted fs-13 mb-0">{{ __('hrms.goals.no_company_goals_desc') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="companyGoalsTable">
                            <thead class="table-light text-muted fs-11 text-uppercase fw-bold">
                                <tr>
                                    <th style="width: 140px;">{{ __('hrms.goals.th_code_horizon') }}</th>
                                    <th>{{ __('hrms.goals.th_objective_pillar') }}</th>
                                    <th style="width: 180px;">{{ __('hrms.goals.th_owner_scope') }}</th>
                                    <th style="width: 140px;">{{ __('hrms.goals.th_key_results') }}</th>
                                    <th style="width: 160px;">{{ __('hrms.goals.th_progress_health') }}</th>
                                    <th style="width: 120px;" class="text-end pe-3">{{ __('hrms.common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody id="companyGoalsTableBody">
                                @foreach($companyGoals as $g)
                                <tr class="company-goal-row">
                                    <td class="align-top py-3">
                                        <div class="fw-bold text-dark fs-12">{{ $g->code }}</div>
                                        <div class="text-muted fs-11 mt-1 text-wrap" style="max-width: 130px; word-break: break-word; line-height: 1.35;">
                                            {{ $g->cycle?->name ?? __('hrms.goals.general') }}
                                        </div>
                                    </td>
                                    <td class="align-top py-3">
                                        <div class="d-flex flex-column gap-1">
                                            <div>
                                                <a href="{{ route('hrms.goals.show', ['goal' => $g->id, 'from_tab' => 'company_goals']) }}" class="fw-bold text-primary text-decoration-none fs-13 d-block text-wrap" style="line-height: 1.35; word-break: break-word;">
                                                    {{ $g->title }}
                                                </a>
                                            </div>
                                            @if($g->category)
                                                <div class="mt-0.5">
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11 fw-semibold d-inline-flex align-items-center gap-1.5 py-1 px-2.5 text-wrap text-start">
                                                        <i class="feather-target" style="font-size: 13px; line-height: 1; flex-shrink: 0;"></i>
                                                        <span>{{ $g->category->name }}</span>
                                                    </span>
                                                </div>
                                            @endif
                                            @if($g->description)
                                                <div class="text-muted fs-11 mt-1 text-wrap" style="line-height: 1.4; word-break: break-word;">
                                                    {{ $g->description }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="align-top py-3">
                                        @if($g->owner_type === 'company')
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11">
                                                <i class="feather-globe me-1"></i> {{ __('hrms.goals.scope_organization_wide') }}
                                            </span>
                                        @elseif($g->owner_type === 'department')
                                            <span class="badge bg-light text-dark border fs-11 text-wrap text-start">
                                                <i class="feather-users me-1"></i> {{ $g->department?->name ?? __('hrms.goals.scope_dept_goal') }}
                                            </span>
                                        @else
                                            @php
                                                $assignedEmps = $g->employees->isNotEmpty() ? $g->employees : ($g->employee ? collect([$g->employee]) : collect());
                                            @endphp
                                            @if($assignedEmps->count() > 1)
                                                <div class="d-flex align-items-center gap-1.5" title="{{ $assignedEmps->pluck('full_name')->join(', ') }}">
                                                    <div class="d-flex align-items-center">
                                                        @foreach($assignedEmps->take(3) as $idx => $emp)
                                                            <div class="avatar-initials rounded-circle border border-2 border-white shadow-xs" 
                                                                 style="width: 26px; height: 26px; font-size: 10px; margin-left: {{ $idx > 0 ? '-8px' : '0' }}; z-index: {{ 10 - $idx }};" 
                                                                 title="{{ $emp->full_name }}">
                                                                {{ strtoupper(substr($emp->full_name, 0, 1)) }}
                                                            </div>
                                                        @endforeach
                                                        @if($assignedEmps->count() > 3)
                                                            <div class="avatar-initials rounded-circle border border-2 border-white shadow-xs bg-light text-muted" 
                                                                 style="width: 26px; height: 26px; font-size: 10px; margin-left: -8px; z-index: 5;"
                                                                 title="+{{ $assignedEmps->count() - 3 }} {{ __('hrms.goals.more') }}">
                                                                +{{ $assignedEmps->count() - 3 }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <span class="text-dark fs-12 fw-semibold text-truncate" style="max-width: 95px;">
                                                        {{ $assignedEmps->first()->full_name }}
                                                    </span>
                                                    <span class="badge bg-primary text-white rounded-pill px-1.5 py-0.5" style="font-size: 9px;">+{{ $assignedEmps->count() - 1 }}</span>
                                                </div>
                                            @elseif($assignedEmps->count() === 1)
                                                <div class="d-flex align-items-center gap-1.5" title="{{ $assignedEmps->first()->full_name }}">
                                                    <div class="avatar-initials rounded-circle border border-2 border-white shadow-xs" style="width: 26px; height: 26px; font-size: 10px;">
                                                        {{ strtoupper(substr($assignedEmps->first()->full_name, 0, 1)) }}
                                                    </div>
                                                    <span class="text-dark fs-12 fw-semibold text-truncate" style="max-width: 120px;">
                                                        {{ $assignedEmps->first()->full_name }}
                                                    </span>
                                                </div>
                                            @else
                                                <span class="badge bg-light text-muted border fs-11">{{ __('hrms.goals.scope_individual') }}</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="align-top py-3">
                                        <span class="badge bg-light text-dark border fs-11">
                                            <i class="feather-list me-1 text-primary"></i> {{ __('hrms.goals.key_results_count', ['count' => $g->keyResults->count()]) }}
                                        </span>
                                    </td>
                                    <td class="align-top py-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold fs-12 text-dark">{{ number_format($g->progress_percentage, 0) }}%</span>
                                            @if($g->health_status === 'on_track')
                                                <span class="badge bg-success-subtle text-success fs-10 fw-semibold">🟢 {{ __('hrms.goals.health_on_track') }}</span>
                                            @elseif($g->health_status === 'at_risk')
                                                <span class="badge bg-warning-subtle text-warning fs-10 fw-semibold">🟡 {{ __('hrms.goals.health_at_risk') }}</span>
                                            @elseif($g->health_status === 'behind')
                                                <span class="badge bg-danger-subtle text-danger fs-10 fw-semibold">🔴 {{ __('hrms.goals.health_behind') }}</span>
                                            @else
                                                <span class="badge bg-info-subtle text-info fs-10 fw-semibold">🔵 {{ __('hrms.goals.health_completed') }}</span>
                                            @endif
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar {{ $g->health_status === 'behind' ? 'bg-danger' : ($g->health_status === 'at_risk' ? 'bg-warning' : 'bg-success') }}" 
                                                 role="progressbar" style="width: {{ min(100, max(0, $g->progress_percentage)) }}%"></div>
                                        </div>
                                    </td>
                                    <td class="align-top py-3 text-end pe-3" style="white-space: nowrap;">
                                        <div class="d-flex align-items-center justify-content-end gap-1">
                                            <x-ui.icon-btn href="{{ route('hrms.goals.show', ['goal' => $g->id, 'from_tab' => 'company_goals']) }}" icon="feather-eye" variant="soft-primary" size="sm" title="{{ __('hrms.goals.view_objective') }}" />
                                            @php
                                                $canCheckInThis = $isHrAdmin || ($currentEmployee && ($g->employee_id === $currentEmployee->id || $g->employees->contains('id', $currentEmployee->id) || ($g->owner_type === 'department' && $g->department_id === $currentEmployee->department_id)));
                                            @endphp
                                            @if($canCheckInThis)
                                            <button type="button" class="btn btn-sm btn-light border text-primary btn-quick-check-in" data-bs-toggle="modal" data-bs-target="#quickCheckInModal" data-goal-id="{{ $g->id }}" data-goal-title="{{ $g->title }}" data-goal-krs="{{ htmlspecialchars(json_encode($g->keyResults), ENT_QUOTES, 'UTF-8') }}" title="{{ __('hrms.goals.quick_check_in') }}">
                                                <i class="feather-check-square fs-13"></i>
                                            </button>
                                            @endif
                                            @if($isHrAdmin)
                                            <form method="POST" action="{{ route('hrms.goals.destroy', $g->id) }}" class="d-inline m-0 p-0" onsubmit="return confirm('{{ __('hrms.goals.confirm_delete_goal') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="active_tab" value="company_goals">
                                                <x-ui.icon-btn type="submit" icon="feather-trash-2" variant="soft-danger" size="sm" title="{{ __('hrms.goals.delete_goal') }}" />
                                            </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- TAB 2: MY & TEAM GOALS -->
            <div class="tab-pane fade {{ $activeTab === 'my_team_goals' ? 'show active' : '' }}" id="my-team" role="tabpanel">
                
                <!-- TOP TOOLBAR: TITLE + SEARCH, SORT, FILTER (RIGHT CORNER) -->
                <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><i class="feather-user-check me-2 text-primary"></i> {{ __('hrms.goals.my_team_objectives') }}</h5>
                        <p class="text-muted fs-12 mb-0">{{ __('hrms.goals.my_team_desc') }}</p>
                    </div>

                    <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                        <!-- Search Input Form (Matches standard HRMS pattern: Auto-submits on type, loads URL parameter & keeps cursor) -->
                        <form method="GET" action="{{ route('hrms.goals.index') }}" id="myTeamGoalsSearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 250px; height: 38px !important; box-sizing: border-box !important;">
                            <input type="hidden" name="active_tab" value="my_team_goals">
                            @if(request()->filled('cycle_id')) <input type="hidden" name="cycle_id" value="{{ request('cycle_id') }}"> @endif
                            @if(request()->filled('category_id')) <input type="hidden" name="category_id" value="{{ request('category_id') }}"> @endif
                            @if(request()->filled('health_status')) <input type="hidden" name="health_status" value="{{ request('health_status') }}"> @endif
                            @if(request()->filled('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                            @if(request()->filled('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
                            <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                            <input type="text" name="search" id="myTeamGoalSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13 goal-search-input" placeholder="{{ __('hrms.goals.search_placeholder') }}" value="{{ request('search') }}" autocomplete="off" style="box-shadow: none; height: 100%; outline: none;">
                        </form>

                        <!-- Sort Dropdown -->
                        <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort', 'newest') === 'newest' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('newest')">
                                <span>{{ __('hrms.goals.sort_newest') }}</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'oldest' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('oldest')">
                                <span>{{ __('hrms.goals.sort_oldest') }}</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'progress_desc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('progress_desc')">
                                <span>{{ __('hrms.goals.sort_progress_desc') }}</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'progress_asc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('progress_asc')">
                                <span>{{ __('hrms.goals.sort_progress_asc') }}</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'due_date' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('due_date')">
                                <span>{{ __('hrms.goals.sort_due_date') }}</span>
                            </a>
                        </x-ui.sort-dropdown>

                        <!-- Filter Dropdown -->
                        <x-ui.filter label="{{ __('hrms.common.filter') }}" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                            <form method="GET" action="{{ route('hrms.goals.index') }}" class="goalFilterForm">
                                <input type="hidden" name="active_tab" value="my_team_goals">
                                <input type="hidden" name="sort" class="goal_sort_input" value="{{ request('sort', 'newest') }}">
                                <input type="hidden" name="search" class="filter_search_hidden" value="{{ request('search') }}">

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.goals.goal_cycle') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="cycle_id">
                                        <option value="">{{ __('hrms.goals.all_goal_cycles') }}</option>
                                        @foreach($cycles as $c)
                                            <option value="{{ $c->id }}" {{ ($selectedCycleId == $c->id && request()->has('cycle_id')) || request('cycle_id') == $c->id ? 'selected' : '' }}>
                                                {{ $c->name }} ({{ strtoupper($c->status) }})
                                            </option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.goals.strategic_pillar') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="category_id">
                                        <option value="">{{ __('hrms.goals.all_strategic_pillars') }}</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.goals.health_status') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="health_status">
                                        <option value="">{{ __('hrms.goals.all_health_statuses') }}</option>
                                        <option value="on_track" {{ request('health_status') == 'on_track' ? 'selected' : '' }}>🟢 {{ __('hrms.goals.health_on_track') }}</option>
                                        <option value="at_risk" {{ request('health_status') == 'at_risk' ? 'selected' : '' }}>🟡 {{ __('hrms.goals.health_at_risk') }}</option>
                                        <option value="behind" {{ request('health_status') == 'behind' ? 'selected' : '' }}>🔴 {{ __('hrms.goals.health_behind') }}</option>
                                        <option value="completed" {{ request('health_status') == 'completed' ? 'selected' : '' }}>🔵 {{ __('hrms.goals.health_completed') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.common.status') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="status">
                                        <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('hrms.common.active') }}</option>
                                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>{{ __('hrms.goals.status_draft') }}</option>
                                        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>{{ __('hrms.goals.status_closed') }}</option>
                                        <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>{{ __('hrms.goals.status_archived') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="dropdown-divider my-3"></div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">{{ __('hrms.common.apply') }}</button>
                                    <a href="{{ route('hrms.goals.index', ['active_tab' => 'my_team_goals']) }}" class="btn btn-light btn-sm border flex-grow-1 text-center text-decoration-none">{{ __('hrms.common.reset') }}</a>
                                </div>
                            </form>
                        </x-ui.filter>
                    </div>
                </div>

                @if($myTeamGoals->isEmpty())
                    <div class="text-center py-5">
                        <div class="p-3 bg-light rounded-circle d-inline-flex mb-3 text-muted">
                            <i class="feather-user-check fs-36"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">{{ __('hrms.goals.no_my_team_goals_found') }}</h6>
                        <p class="text-muted fs-13 mb-0">{{ __('hrms.goals.no_my_team_goals_desc') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="myTeamGoalsTable">
                            <thead class="table-light text-muted fs-11 text-uppercase fw-bold">
                                <tr>
                                    <th style="width: 140px;">{{ __('hrms.goals.th_code_horizon') }}</th>
                                    <th>{{ __('hrms.goals.th_objective_pillar') }}</th>
                                    <th style="width: 180px;">{{ __('hrms.goals.th_owner_scope') }}</th>
                                    <th style="width: 140px;">{{ __('hrms.goals.th_key_results') }}</th>
                                    <th style="width: 160px;">{{ __('hrms.goals.th_progress_health') }}</th>
                                    <th style="width: 120px;" class="text-end pe-3">{{ __('hrms.common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody id="myTeamGoalsTableBody">
                                @foreach($myTeamGoals as $g)
                                <tr class="my-team-goal-row">
                                    <td class="align-top py-3">
                                        <div class="fw-bold text-dark fs-12">{{ $g->code }}</div>
                                        <div class="text-muted fs-11 mt-1 text-wrap" style="max-width: 130px; word-break: break-word; line-height: 1.35;">
                                            {{ $g->cycle?->name ?? __('hrms.goals.general') }}
                                        </div>
                                    </td>
                                    <td class="align-top py-3">
                                        <div class="d-flex flex-column gap-1">
                                            <div>
                                                <a href="{{ route('hrms.goals.show', ['goal' => $g->id, 'from_tab' => 'my_team_goals']) }}" class="fw-bold text-primary text-decoration-none fs-13 d-block text-wrap" style="line-height: 1.35; word-break: break-word;">
                                                    {{ $g->title }}
                                                </a>
                                            </div>
                                            @if($g->category)
                                                <div class="mt-0.5">
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11 fw-semibold d-inline-flex align-items-center gap-1.5 py-1 px-2.5 text-wrap text-start">
                                                        <i class="feather-target" style="font-size: 13px; line-height: 1; flex-shrink: 0;"></i>
                                                        <span>{{ $g->category->name }}</span>
                                                    </span>
                                                </div>
                                            @endif
                                            @if($g->parentGoal)
                                                <div class="mt-1">
                                                    <span class="text-muted fs-11">
                                                        <i class="feather-corner-down-right text-primary me-1"></i>
                                                        {{ __('hrms.goals.aligned_to') }} <span class="fw-semibold text-dark">{{ $g->parentGoal->title }}</span>
                                                    </span>
                                                </div>
                                            @endif
                                            @if($g->description)
                                                <div class="text-muted fs-11 mt-1 text-wrap" style="line-height: 1.4; word-break: break-word;">
                                                    {{ $g->description }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="align-top py-3">
                                        @if($g->owner_type === 'company')
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11">
                                                <i class="feather-globe me-1"></i> {{ __('hrms.goals.scope_organization_wide') }}
                                            </span>
                                        @elseif($g->owner_type === 'department')
                                            <span class="badge bg-light text-dark border fs-11 text-wrap text-start">
                                                <i class="feather-users me-1"></i> {{ $g->department?->name ?? __('hrms.goals.scope_dept_goal') }}
                                            </span>
                                        @else
                                            @php
                                                $assignedEmps = $g->employees->isNotEmpty() ? $g->employees : ($g->employee ? collect([$g->employee]) : collect());
                                            @endphp
                                            @if($assignedEmps->count() > 1)
                                                <div class="d-flex align-items-center gap-1.5" title="{{ $assignedEmps->pluck('full_name')->join(', ') }}">
                                                    <div class="d-flex align-items-center">
                                                        @foreach($assignedEmps->take(3) as $idx => $emp)
                                                            <div class="avatar-initials rounded-circle border border-2 border-white shadow-xs" 
                                                                 style="width: 26px; height: 26px; font-size: 10px; margin-left: {{ $idx > 0 ? '-8px' : '0' }}; z-index: {{ 10 - $idx }};" 
                                                                 title="{{ $emp->full_name }}">
                                                                {{ strtoupper(substr($emp->full_name, 0, 1)) }}
                                                            </div>
                                                        @endforeach
                                                        @if($assignedEmps->count() > 3)
                                                            <div class="avatar-initials rounded-circle border border-2 border-white shadow-xs bg-light text-muted" 
                                                                 style="width: 26px; height: 26px; font-size: 10px; margin-left: -8px; z-index: 5;"
                                                                 title="+{{ $assignedEmps->count() - 3 }} {{ __('hrms.goals.more') }}">
                                                                +{{ $assignedEmps->count() - 3 }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <span class="text-dark fs-12 fw-semibold text-truncate" style="max-width: 95px;">
                                                        {{ $assignedEmps->first()->full_name }}
                                                    </span>
                                                    <span class="badge bg-primary text-white rounded-pill px-1.5 py-0.5" style="font-size: 9px;">+{{ $assignedEmps->count() - 1 }}</span>
                                                </div>
                                            @elseif($assignedEmps->count() === 1)
                                                <div class="d-flex align-items-center gap-1.5" title="{{ $assignedEmps->first()->full_name }}">
                                                    <div class="avatar-initials rounded-circle border border-2 border-white shadow-xs" style="width: 26px; height: 26px; font-size: 10px;">
                                                        {{ strtoupper(substr($assignedEmps->first()->full_name, 0, 1)) }}
                                                    </div>
                                                    <span class="text-dark fs-12 fw-semibold text-truncate" style="max-width: 120px;">
                                                        {{ $assignedEmps->first()->full_name }}
                                                    </span>
                                                </div>
                                            @elseif($g->department)
                                                <div class="d-flex align-items-center gap-1.5">
                                                    <div class="avatar-initials" style="width: 26px; height: 26px; font-size: 10px;">
                                                        {{ strtoupper(substr($g->department->name, 0, 1)) }}
                                                    </div>
                                                    <small class="text-muted fs-11 fw-semibold">{{ $g->department->name }}</small>
                                                </div>
                                            @else
                                                <span class="badge bg-light text-muted border fs-11">{{ __('hrms.goals.scope_individual') }}</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="align-top py-3">
                                        <span class="badge bg-light text-dark border fs-11">
                                            <i class="feather-list me-1 text-primary"></i> {{ __('hrms.goals.key_results_count', ['count' => $g->keyResults->count()]) }}
                                        </span>
                                    </td>
                                    <td class="align-top py-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold fs-12 text-dark">{{ number_format($g->progress_percentage, 0) }}%</span>
                                            @if($g->health_status === 'on_track')
                                                <span class="badge bg-success-subtle text-success fs-10 fw-semibold">🟢 {{ __('hrms.goals.health_on_track') }}</span>
                                            @elseif($g->health_status === 'at_risk')
                                                <span class="badge bg-warning-subtle text-warning fs-10 fw-semibold">🟡 {{ __('hrms.goals.health_at_risk') }}</span>
                                            @elseif($g->health_status === 'behind')
                                                <span class="badge bg-danger-subtle text-danger fs-10 fw-semibold">🔴 {{ __('hrms.goals.health_behind') }}</span>
                                            @else
                                                <span class="badge bg-info-subtle text-info fs-10 fw-semibold">🔵 {{ __('hrms.goals.health_completed') }}</span>
                                            @endif
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar {{ $g->health_status === 'behind' ? 'bg-danger' : ($g->health_status === 'at_risk' ? 'bg-warning' : 'bg-success') }}" 
                                                 role="progressbar" style="width: {{ min(100, max(0, $g->progress_percentage)) }}%"></div>
                                        </div>
                                    </td>
                                    <td class="align-top py-3 text-end pe-3" style="white-space: nowrap;">
                                        <div class="d-flex align-items-center justify-content-end gap-1">
                                            <x-ui.icon-btn href="{{ route('hrms.goals.show', ['goal' => $g->id, 'from_tab' => 'my_team_goals']) }}" icon="feather-eye" variant="soft-primary" size="sm" title="{{ __('hrms.goals.view_objective') }}" />
                                            <button type="button" class="btn btn-sm btn-light border text-primary btn-quick-check-in" data-bs-toggle="modal" data-bs-target="#quickCheckInModal" data-goal-id="{{ $g->id }}" data-goal-title="{{ $g->title }}" data-goal-krs="{{ htmlspecialchars(json_encode($g->keyResults), ENT_QUOTES, 'UTF-8') }}" title="{{ __('hrms.goals.quick_check_in') }}">
                                                <i class="feather-check-square fs-13"></i>
                                            </button>
                                            @if($isHrAdmin)
                                            <form method="POST" action="{{ route('hrms.goals.destroy', $g->id) }}" class="d-inline m-0 p-0" onsubmit="return confirm('{{ __('hrms.goals.confirm_delete_goal') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="active_tab" value="my_team_goals">
                                                <x-ui.icon-btn type="submit" icon="feather-trash-2" variant="soft-danger" size="sm" title="{{ __('hrms.goals.delete_goal') }}" />
                                            </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- TAB 3: CASCADING ALIGNMENT TREE -->
            <div class="tab-pane fade {{ $activeTab === 'alignment_tree' ? 'show active' : '' }}" id="alignment-tree" role="tabpanel">
                <!-- TOP TOOLBAR: TITLE + BADGE -->
                <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><i class="feather-git-merge me-2 text-primary"></i> {{ __('hrms.goals.cascading_alignment_tree') }}</h5>
                        <p class="text-muted fs-12 mb-0">{{ __('hrms.goals.alignment_tree_desc') }}</p>
                    </div>
                    <div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-12 fw-bold px-3 py-2">
                            <i class="feather-layers me-1"></i> {{ __('hrms.goals.root_strategic_goals_count', ['count' => count($alignmentTree)]) }}
                        </span>
                    </div>
                </div>

                @if(empty($alignmentTree))
                    <div class="text-center py-5">
                        <div class="p-3 bg-light rounded-circle d-inline-flex mb-3 text-muted">
                            <i class="feather-git-merge fs-36"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">{{ __('hrms.goals.no_alignment_configured') }}</h6>
                        <p class="text-muted fs-13">{{ __('hrms.goals.no_alignment_desc') }}</p>
                    </div>
                @else
                    <div class="p-2">
                        @foreach($alignmentTree as $node)
                            <div class="card border mb-3 rounded-3 shadow-sm">
                                <div class="card-body p-3.5">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-primary text-white fs-11">{{ $node['code'] }}</span>
                                            <a href="{{ route('hrms.goals.show', ['goal' => $node['id'], 'from_tab' => 'alignment_tree']) }}" class="fw-bold text-dark fs-14 text-decoration-none">
                                                {{ $node['title'] }}
                                            </a>
                                            <span class="badge bg-light text-dark border fs-11">🏢 {{ $node['owner_name'] }}</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="text-end" style="width: 140px;">
                                                <div class="d-flex justify-content-between fs-11 mb-1">
                                                    <span class="text-muted">{{ __('hrms.goals.progress') }}</span>
                                                    <span class="fw-bold text-dark">{{ number_format($node['progress_percentage'], 0) }}%</span>
                                                </div>
                                                <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar bg-primary" style="width: {{ $node['progress_percentage'] }}%"></div>
                                                </div>
                                            </div>
                                            <a href="{{ route('hrms.goals.show', ['goal' => $node['id'], 'from_tab' => 'alignment_tree']) }}" class="btn btn-sm btn-light border"><i class="feather-arrow-right"></i></a>
                                        </div>
                                    </div>

                                    <!-- 1st Level Children -->
                                    @if(!empty($node['children']))
                                        <div class="tree-branch">
                                            @foreach($node['children'] as $child)
                                                <div class="position-relative mb-2">
                                                    <div class="tree-node-connector"></div>
                                                    <div class="card border rounded-3 p-2.5 bg-light-subtle">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="badge bg-info text-white fs-10">{{ $child['code'] }}</span>
                                                                <a href="{{ route('hrms.goals.show', ['goal' => $child['id'], 'from_tab' => 'alignment_tree']) }}" class="fw-semibold text-dark fs-13 text-decoration-none">
                                                                    {{ $child['title'] }}
                                                                </a>
                                                                <span class="badge bg-white text-muted border fs-10">👥 {{ $child['owner_name'] }}</span>
                                                            </div>
                                                            <span class="fw-bold fs-12 text-primary">{{ number_format($child['progress_percentage'], 0) }}%</span>
                                                        </div>

                                                        <!-- 2nd Level Children (Individuals) -->
                                                        @if(!empty($child['children']))
                                                            <div class="tree-branch mt-2">
                                                                @foreach($child['children'] as $grandChild)
                                                                    <div class="position-relative mb-1.5">
                                                                        <div class="tree-node-connector"></div>
                                                                        <div class="p-2 bg-white rounded border d-flex justify-content-between align-items-center">
                                                                            <div class="d-flex align-items-center gap-2">
                                                                                <span class="badge bg-secondary text-white fs-9">{{ $grandChild['code'] }}</span>
                                                                                <a href="{{ route('hrms.goals.show', ['goal' => $grandChild['id'], 'from_tab' => 'alignment_tree']) }}" class="fs-12 text-dark text-decoration-none">
                                                                                    {{ $grandChild['title'] }}
                                                                                </a>
                                                                                <span class="text-muted fs-11">👤 {{ $grandChild['owner_name'] }}</span>
                                                                            </div>
                                                                            <span class="fw-semibold fs-11 text-success">{{ number_format($grandChild['progress_percentage'], 0) }}%</span>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- TAB 4: CYCLES & STRATEGIC PILLARS (HR ADMIN) -->
            @if($isHrAdmin)
            <div class="tab-pane fade {{ $activeTab === 'cycles_pillars' ? 'show active' : '' }}" id="cycles-pillars" role="tabpanel">
                <!-- TOP TOOLBAR: TITLE -->
                <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><i class="feather-layers me-2 text-primary"></i> {{ __('hrms.goals.cycles_strategic_pillars') }}</h5>
                        <p class="text-muted fs-12 mb-0">{{ __('hrms.goals.cycles_pillars_desc') }}</p>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Cycles Master -->
                    <div class="col-lg-6">
                        <div class="card rounded-3 shadow-sm h-100 bg-white" style="border: 1px solid #e2e8f0;">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2.5 border-bottom">
                                <span class="fw-bold text-dark fs-13"><i class="feather-calendar me-1.5 text-primary"></i> {{ __('hrms.goals.goal_cycles_time_horizons') }}</span>
                                <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createCycleModal">
                                    {{ __('hrms.goals.new_cycle') }}
                                </x-ui.button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="overflow-x: hidden;">
                                    <table class="table table-hover align-middle mb-0 w-100" style="table-layout: fixed;">
                                        <thead class="table-light text-muted fs-11 text-uppercase fw-bold">
                                            <tr>
                                                <th class="ps-3 py-2" style="width: 58%;">{{ __('hrms.goals.th_cycle_time_horizon') }}</th>
                                                <th class="py-2" style="width: 26%;">{{ __('hrms.common.status') }}</th>
                                                <th class="pe-3 py-2 text-end" style="width: 16%;">{{ __('hrms.common.action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($cycles as $c)
                                            <tr>
                                                <td class="ps-3 py-2.5">
                                                    <div class="fw-bold text-dark fs-13 text-wrap" style="line-height: 1.35; word-break: break-word;">{{ $c->name }}</div>
                                                    <div class="text-muted fs-11 mt-0.5 text-wrap">
                                                        <i class="feather-calendar me-1 text-muted"></i>{{ $c->start_date?->format('M d, Y') }} - {{ $c->end_date?->format('M d, Y') }}
                                                    </div>
                                                </td>
                                                <td class="py-2.5 align-middle">
                                                    <span class="badge {{ $c->status === 'active' ? 'bg-success-subtle text-success' : 'bg-light text-muted border' }} fs-10 fw-semibold">
                                                        {{ strtoupper($c->status) }}
                                                    </span>
                                                </td>
                                                <td class="pe-3 py-2.5 text-end align-middle">
                                                    <form method="POST" action="{{ route('hrms.goals.cycle.destroy', $c->id) }}" class="d-inline" onsubmit="return confirm('{{ __('hrms.goals.confirm_delete_cycle') }}')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-ui.icon-btn type="submit" icon="feather-trash-2" variant="soft-danger" size="sm" />
                                                    </form>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="3" class="text-center py-4 text-muted fs-13">
                                                    <i class="feather-calendar fs-24 d-block mb-2 text-muted opacity-50"></i>
                                                    {{ __('hrms.goals.no_cycles_configured') }}
                                                </td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Strategic Categories Master -->
                    <div class="col-lg-6">
                        <div class="card rounded-3 shadow-sm h-100 bg-white" style="border: 1px solid #e2e8f0;">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2.5 border-bottom">
                                <span class="fw-bold text-dark fs-13"><i class="feather-target me-1.5 text-primary"></i> {{ __('hrms.goals.strategic_pillars_categories') }}</span>
                                <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                                    {{ __('hrms.goals.new_pillar') }}
                                </x-ui.button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="overflow-x: hidden;">
                                    <table class="table table-hover align-middle mb-0 w-100" style="table-layout: fixed;">
                                        <thead class="table-light text-muted fs-11 text-uppercase fw-bold">
                                            <tr>
                                                <th class="ps-3 py-2" style="width: 58%;">{{ __('hrms.goals.th_strategic_pillar') }}</th>
                                                <th class="py-2" style="width: 26%;">{{ __('hrms.goals.th_pillar_code') }}</th>
                                                <th class="pe-3 py-2 text-end" style="width: 16%;">{{ __('hrms.common.action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($categories as $cat)
                                            <tr>
                                                <td class="ps-3 py-2.5">
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 fw-semibold fs-11 text-wrap text-start d-inline-flex align-items-center gap-1.5" style="line-height: 1.35; word-break: break-word;">
                                                        <i class="feather-target" style="font-size: 11px; flex-shrink: 0;"></i>
                                                        <span>{{ $cat->name }}</span>
                                                    </span>
                                                </td>
                                                <td class="py-2.5 text-muted fs-12 font-monospace align-middle">{{ $cat->code }}</td>
                                                <td class="pe-3 py-2.5 text-end align-middle">
                                                    <form method="POST" action="{{ route('hrms.goals.category.destroy', $cat->id) }}" class="d-inline" onsubmit="return confirm('{{ __('hrms.goals.confirm_delete_category') }}')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-ui.icon-btn type="submit" icon="feather-trash-2" variant="soft-danger" size="sm" />
                                                    </form>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="3" class="text-center py-4 text-muted fs-13">
                                                    <i class="feather-target fs-24 d-block mb-2 text-muted opacity-50"></i>
                                                    {{ __('hrms.goals.no_pillars_configured') }}
                                                </td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 1: CREATE GOAL / OBJECTIVE -->
<!-- ========================================================================= -->
<x-ui.modal id="createGoalModal" title="<i class='feather-plus-circle text-primary me-2'></i> {{ __('hrms.goals.modal_create_goal_title') }}" size="xl" :showFooter="false" :centered="true" :scrollable="true">
    <form method="POST" action="{{ route('hrms.goals.store') }}" id="createGoalForm">
        @csrf
        <x-ui.odoo-form-ui type="sheet" class="p-2">
            <!-- SECTION 1: HEADER & OBJECTIVE DEFINITION -->
            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                <span class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 22px; height: 22px; font-size: 11px; background-color: var(--bs-primary, #852d3c);">1</span>
                <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase letter-spacing-1">{{ __('hrms.goals.section_objective_classification') }}</h6>
            </div>
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.goals.objective_title') }}" name="title" placeholder="{{ __('hrms.goals.placeholder_objective_title') }}" :required="true" />
                    <!-- GOAL CYCLE WITH INLINE CUSTOM ADD -->
                    <div class="odoo-form-group mb-2">
                        <label class="odoo-form-label">{{ __('hrms.goals.goal_cycle') }}</label>
                        <div class="flex-grow-1">
                            <div class="cycle-select-wrap d-flex align-items-center" style="width: 100%;">
                                <select name="goal_cycle_id" class="odoo-form-control goal-cycle-select select2" style="width: 100%;" onchange="handleGoalCycleSelect(this)">
                                    <option value="">{{ __('hrms.goals.choose_goal_cycle') }}</option>
                                    <option value="__custom__">{{ __('hrms.goals.add_custom_goal_cycle') }}</option>
                                    @foreach($cycles as $c)
                                        <option value="{{ $c->id }}" {{ ($selectedCycleId ? $selectedCycleId == $c->id : ($c->status === 'active' || $loop->first)) ? 'selected' : '' }}>{{ $c->name }} ({{ strtoupper($c->status) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="cycle-input-wrap d-none align-items-center gap-1">
                                <input type="text" name="custom_goal_cycle" class="odoo-form-control cycle-title-input" placeholder="{{ __('hrms.goals.placeholder_custom_goal_cycle') }}" />
                                <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ __('hrms.goals.switch_back_to_list') }}" onclick="switchToGoalCycleSelect(this)" style="height: 24px; font-size: 11px; white-space: nowrap;">
                                    <i class="feather-list"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- STRATEGIC PILLAR WITH INLINE CUSTOM ADD -->
                    <div class="odoo-form-group mb-2">
                        <label class="odoo-form-label">{{ __('hrms.goals.strategic_pillar') }}</label>
                        <div class="flex-grow-1">
                            <div class="pillar-select-wrap d-flex align-items-center" style="width: 100%;">
                                <select name="goal_category_id" class="odoo-form-control goal-pillar-select select2" style="width: 100%;" onchange="handleGoalPillarSelect(this)">
                                    <option value="">{{ __('hrms.goals.choose_strategic_pillar') }}</option>
                                    <option value="__custom__">{{ __('hrms.goals.add_custom_strategic_pillar') }}</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="pillar-input-wrap d-none align-items-center gap-1">
                                <input type="text" name="custom_goal_category" class="odoo-form-control pillar-title-input" placeholder="{{ __('hrms.goals.placeholder_custom_strategic_pillar') }}" />
                                <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ __('hrms.goals.switch_back_to_list') }}" onclick="switchToGoalPillarSelect(this)" style="height: 24px; font-size: 11px; white-space: nowrap;">
                                    <i class="feather-list"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.goals.scope') }}" name="owner_type" id="ownerTypeSelect" onchange="handleOwnerTypeChange(this.value)">
                        @if($isHrAdmin)
                        <option value="company">{{ __('hrms.goals.scope_organization_wide') }}</option>
                        @endif
                        <option value="department">{{ __('hrms.common.department') }}</option>
                        <option value="employee" selected>{{ __('hrms.goals.scope_individual_employee') }}</option>
                    </x-ui.odoo-form-ui>
                    
                    <div id="deptSelectCol" style="display: none;">
                        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.common.department') }}" name="department_id">
                            <option value="">{{ __('hrms.goals.choose_department') }}</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div id="empSelectCol">
                        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.goals.employees_label') }}" name="employee_ids[]" id="createGoalEmployeeSelect" :multiple="true" select2Selector="select2">
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}" {{ $currentEmployee?->id == $emp->id ? 'selected' : '' }}>
                                    {{ $emp->full_name }} ({{ $emp->employee_id }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.goals.parent_goal') }}" name="parent_goal_id">
                        <option value="">{{ __('hrms.goals.none_top_level_goal') }}</option>
                        @foreach($parentGoalOptions as $pg)
                            <option value="{{ $pg->id }}">{{ $pg->code }} - {{ Str::limit($pg->title, 35) }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.goals.priority') }}" name="priority">
                        <option value="low">{{ __('hrms.goals.priority_low') }}</option>
                        <option value="medium" selected>{{ __('hrms.goals.priority_medium') }}</option>
                        <option value="high">{{ __('hrms.goals.priority_high') }}</option>
                        <option value="critical">{{ __('hrms.goals.priority_critical') }}</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.goals.target_due_date') }}" name="due_date" value="{{ date('Y-m-d', strtotime('+90 days')) }}" />
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.goals.context_notes') }}" name="description" placeholder="{{ __('hrms.goals.placeholder_context_notes') }}" rows="2" />
                </div>
            </div>

            <!-- SECTION 2: MEASURABLE KEY RESULTS -->
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 22px; height: 22px; font-size: 11px; background-color: var(--bs-primary, #852d3c);">2</span>
                    <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase letter-spacing-1">{{ __('hrms.goals.section_key_results') }}</h6>
                </div>
                <x-ui.button type="button" variant="light" size="sm" icon="feather-plus" class="border fw-semibold text-dark shadow-none" onclick="addKeyResultRow()">
                    {{ __('hrms.goals.add_key_result') }}
                </x-ui.button>
            </div>

            <div class="table-responsive mb-3">
                <table class="odoo-table mb-0" id="keyResultsTable">
                    <thead>
                        <tr>
                            <th style="width: 44%;">{{ __('hrms.goals.th_kr_description') }} <span class="text-danger">*</span></th>
                            <th style="width: 22%;">{{ __('hrms.goals.th_metric_type') }}</th>
                            <th style="width: 14%;">{{ __('hrms.goals.th_unit') }}</th>
                            <th class="text-end" style="width: 14%;">{{ __('hrms.goals.th_target') }}</th>
                            <th class="text-center" style="width: 6%;"></th>
                        </tr>
                    </thead>
                    <tbody id="keyResultsContainer">
                        <tr class="kr-row">
                            <td>
                                <input type="text" name="kr_title[]" class="odoo-table-input" placeholder="{{ __('hrms.goals.placeholder_kr_title') }}" required>
                            </td>
                            <td>
                                <select name="kr_metric_type[]" class="form-select odoo-table-select kr-metric-select" onchange="handleMetricTypeChange(this)">
                                    <option value="percentage">{{ __('hrms.goals.metric_percentage') }}</option>
                                    <option value="currency">{{ __('hrms.goals.metric_currency') }}</option>
                                    <option value="numeric">{{ __('hrms.goals.metric_count') }}</option>
                                    <option value="boolean_milestone">{{ __('hrms.goals.metric_milestone') }}</option>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="kr_unit[]" class="odoo-table-input kr-unit-input" placeholder="%" value="%">
                            </td>
                            <td>
                                <input type="number" step="any" name="kr_target_value[]" class="odoo-table-input text-end" placeholder="100" value="100" required>
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row-btn" onclick="removeKrRow(this)" title="{{ __('hrms.goals.remove_key_result') }}">
                                    <i class="feather-trash-2"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-plus-circle" class="fw-bold">{{ __('hrms.goals.btn_create_objective') }}</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL 2: QUICK CHECK-IN -->
<!-- ========================================================================= -->
<x-ui.modal id="quickCheckInModal" title="<i class='feather-check-square text-primary me-2'></i> {{ __('hrms.goals.modal_quick_checkin_title') }}" size="lg" :showFooter="false" :centered="true">
    <form method="POST" action="" id="quickCheckInForm">
        @csrf
        <x-ui.odoo-form-ui type="sheet" class="p-2">
            <div class="p-3 bg-light rounded-3 mb-3 border">
                <small class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">{{ __('hrms.goals.target_objective') }}</small>
                <div class="fw-bold text-dark fs-14" id="checkInGoalTitle"></div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12" id="checkInKrSelectGroup">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.goals.target_key_result') }}" name="goal_key_result_id" id="checkInKrSelect" onchange="updateKrInputUnit(this)">
                        <option value="">{{ __('hrms.goals.objective_overall_progress') }}</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="number" step="any" label="{{ __('hrms.goals.new_value') }}" name="new_value" id="checkInNewValue" placeholder="{{ __('hrms.goals.placeholder_new_value') }}" :required="true" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.goals.health_status') }}" name="health_status" :required="true">
                        <option value="on_track" selected>🟢 {{ __('hrms.goals.health_on_track') }}</option>
                        <option value="at_risk">🟡 {{ __('hrms.goals.health_at_risk') }}</option>
                        <option value="behind">🔴 {{ __('hrms.goals.health_behind') }}</option>
                        <option value="completed">🔵 {{ __('hrms.goals.health_completed') }}</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.goals.check_in_note') }}" name="comment" placeholder="{{ __('hrms.goals.placeholder_check_in_note') }}" rows="2" :required="true" />
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.goals.blockers_risks') }}" name="blockers" placeholder="{{ __('hrms.goals.placeholder_blockers') }}" rows="1" />
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-check" class="fw-bold">{{ __('hrms.goals.btn_save_checkin') }}</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL 3: CREATE CYCLE -->
<!-- ========================================================================= -->
<x-ui.modal id="createCycleModal" title="<i class='feather-calendar text-primary me-2'></i> {{ __('hrms.goals.modal_create_cycle_title') }}" size="lg" :showFooter="false" :centered="true">
    <form method="POST" action="{{ route('hrms.goals.cycle.store') }}">
        @csrf
        <x-ui.odoo-form-ui type="sheet" class="p-2">
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.goals.cycle_name') }}" name="name" placeholder="{{ __('hrms.goals.placeholder_cycle_name') }}" :required="true" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.goals.start_date') }}" name="start_date" value="{{ date('Y-m-01') }}" :required="true" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.goals.end_date') }}" name="end_date" value="{{ date('Y-m-t', strtotime('+3 months')) }}" :required="true" />
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.goals.description') }}" name="description" placeholder="{{ __('hrms.goals.placeholder_cycle_desc') }}" rows="2" />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-save" class="fw-bold">{{ __('hrms.goals.btn_save_cycle') }}</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL 4: CREATE STRATEGIC PILLAR (CATEGORY) -->
<!-- ========================================================================= -->
<x-ui.modal id="createCategoryModal" title="<i class='feather-target text-primary me-2'></i> {{ __('hrms.goals.modal_create_pillar_title') }}" size="lg" :showFooter="false" :centered="true">
    <form method="POST" action="{{ route('hrms.goals.category.store') }}">
        @csrf
        <x-ui.odoo-form-ui type="sheet" class="p-2">
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.goals.pillar_name') }}" name="name" placeholder="{{ __('hrms.goals.placeholder_pillar_name') }}" :required="true" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.goals.pillar_code') }}" name="code" placeholder="{{ __('hrms.goals.placeholder_pillar_code') }}" />
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.goals.description') }}" name="description" placeholder="{{ __('hrms.goals.placeholder_pillar_desc') }}" rows="2" />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-save" class="fw-bold">{{ __('hrms.goals.btn_save_pillar') }}</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</x-ui.modal>
@endsection

@push('scripts')
<script>
    function initGoalModalSelects(context) {
        if (typeof $.fn.select2 === 'undefined') return;
        
        var $ctx = $(context || document);
        
        // Multi-select for Employees
        $ctx.find('#createGoalEmployeeSelect:not(.select2-hidden-accessible)').select2({
            dropdownParent: $('#createGoalModal'),
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: @json(__('hrms.goals.select_employees_placeholder')),
            allowClear: true
        });

        // Metric Type dropdowns inside Key Results table
        $ctx.find('.kr-metric-select:not(.select2-hidden-accessible)').each(function() {
            var $sel = $(this);
            $sel.select2({
                dropdownParent: $('#createGoalModal'),
                theme: 'bootstrap-5',
                width: '100%',
                minimumResultsForSearch: Infinity
            }).on('change', function() {
                handleMetricTypeChange(this);
            });
        });
    }

    $(document).ready(function() {
        $('#createGoalModal').on('shown.bs.modal', function () {
            initGoalModalSelects('#createGoalModal');
        });
        $('#quickCheckInModal').on('shown.bs.modal', function () {
            initGoalModalSelects('#quickCheckInModal');
        });
        initGoalModalSelects(document);
    });

    function handleOwnerTypeChange(type) {
        var deptCol = document.getElementById('deptSelectCol');
        var empCol = document.getElementById('empSelectCol');
        if (type === 'company') {
            deptCol.style.display = 'none';
            empCol.style.display = 'none';
        } else if (type === 'department') {
            deptCol.style.display = 'block';
            empCol.style.display = 'none';
        } else {
            deptCol.style.display = 'none';
            empCol.style.display = 'block';
        }
    }

    function handleMetricTypeChange(selectElem) {
        var row = selectElem.closest('.kr-row');
        var unitInput = row ? row.querySelector('.kr-unit-input') : null;
        if (!unitInput) return;
        if (selectElem.value === 'percentage') {
            unitInput.value = '%';
        } else if (selectElem.value === 'currency') {
            unitInput.value = '$';
        } else if (selectElem.value === 'numeric') {
            unitInput.value = 'Units';
        } else if (selectElem.value === 'boolean_milestone') {
            unitInput.value = 'Status';
        }
    }

    function removeKrRow(btn) {
        var container = document.getElementById('keyResultsContainer');
        var rows = container.querySelectorAll('.kr-row');
        if (rows.length > 1) {
            btn.closest('.kr-row').remove();
        } else {
            var row = btn.closest('.kr-row');
            row.querySelectorAll('input').forEach(function(input) {
                if (input.classList.contains('kr-unit-input')) {
                    input.value = '%';
                } else if (input.name === 'kr_target_value[]') {
                    input.value = '100';
                } else {
                    input.value = '';
                }
            });
        }
    }

    function addKeyResultRow() {
        var container = document.getElementById('keyResultsContainer');
        var tr = document.createElement('tr');
        tr.className = 'kr-row';
        tr.innerHTML = `
            <td>
                <input type="text" name="kr_title[]" class="odoo-table-input" placeholder="${@json(__('hrms.goals.placeholder_kr_title'))}" required>
            </td>
            <td>
                <select name="kr_metric_type[]" class="form-select odoo-table-select kr-metric-select" onchange="handleMetricTypeChange(this)">
                    <option value="percentage">${@json(__('hrms.goals.metric_percentage'))}</option>
                    <option value="currency">${@json(__('hrms.goals.metric_currency'))}</option>
                    <option value="numeric">${@json(__('hrms.goals.metric_count'))}</option>
                    <option value="boolean_milestone">${@json(__('hrms.goals.metric_milestone'))}</option>
                </select>
            </td>
            <td>
                <input type="text" name="kr_unit[]" class="odoo-table-input kr-unit-input" placeholder="%" value="%">
            </td>
            <td>
                <input type="number" step="any" name="kr_target_value[]" class="odoo-table-input text-end" placeholder="100" value="100" required>
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row-btn" onclick="removeKrRow(this)" title="${@json(__('hrms.goals.remove_key_result'))}">
                    <i class="feather-trash-2"></i>
                </button>
            </td>
        `;
        container.appendChild(tr);
        initGoalModalSelects(tr);
    }

    function prepareCheckIn(goalId, title, keyResults) {
        var form = document.getElementById('quickCheckInForm');
        form.action = "{{ url('hrms/goals') }}/" + goalId + "/check-in";
        document.getElementById('checkInGoalTitle').textContent = title;

        var krSelect = document.getElementById('checkInKrSelect');
        krSelect.innerHTML = '<option value="">' + @json(__('hrms.goals.objective_overall_progress')) + ' (%)</option>';
        if (typeof keyResults === 'string') {
            try { keyResults = JSON.parse(keyResults); } catch (e) { keyResults = []; }
        }
        if (keyResults && keyResults.length > 0) {
            keyResults.forEach(function(kr) {
                var opt = document.createElement('option');
                opt.value = kr.id;
                opt.textContent = kr.title + ' (' + @json(__('hrms.goals.target_label', ['default' => 'Current'])) + ': ' + kr.current_value + ' ' + kr.unit + ' / ' + @json(__('hrms.goals.th_target')) + ': ' + kr.target_value + ' ' + kr.unit + ')';
                opt.setAttribute('data-unit', kr.unit || '%');
                opt.setAttribute('data-current', kr.current_value || '0');
                opt.setAttribute('data-target', kr.target_value || '100');
                krSelect.appendChild(opt);
            });
        }
        if (typeof $ !== 'undefined' && $(krSelect).hasClass('select2-hidden-accessible')) {
            $(krSelect).trigger('change.select2');
        }
        var newValInput = document.getElementById('checkInNewValue');
        if (newValInput) newValInput.value = '';
        updateKrInputUnit(krSelect);
    }

    function updateKrInputUnit(selectElem) {
        var opt = selectElem ? selectElem.options[selectElem.selectedIndex] : null;
        var newValInput = document.getElementById('checkInNewValue');
        if (!newValInput) return;
        if (opt && opt.getAttribute('data-unit')) {
            newValInput.placeholder = opt.getAttribute('data-current') + ' ' + opt.getAttribute('data-unit') + ' (' + @json(__('hrms.goals.th_target')) + ': ' + opt.getAttribute('data-target') + ')';
        } else {
            newValInput.placeholder = @json(__('hrms.goals.placeholder_progress_percentage'));
        }
    }

    function setGoalSort(sortValue) {
        var activePane = document.querySelector('.tab-pane.show.active') || document.querySelector('.tab-pane.active');
        if (activePane) {
            var form = activePane.querySelector('.goalFilterForm');
            var sortInput = activePane.querySelector('.goal_sort_input');
            if (form && sortInput) {
                sortInput.value = sortValue;
                form.submit();
                return;
            }
        }
        var defaultForm = document.querySelector('.goalFilterForm');
        var defaultSort = document.querySelector('.goal_sort_input');
        if (defaultForm && defaultSort) {
            defaultSort.value = sortValue;
            defaultForm.submit();
        }
    }

    // Standard HRMS Auto-Search with URL parameter loading & end-cursor focus retention
    const activeTabPane = document.querySelector('.tab-pane.show.active') || document.querySelector('.tab-pane.active');
    const activeSearchInput = activeTabPane ? activeTabPane.querySelector('.goal-search-input') : document.querySelector('.goal-search-input');
    
    // Auto-focus search input if search query is present and position cursor at the end
    if (activeSearchInput && activeSearchInput.value) {
        activeSearchInput.focus();
        const len = activeSearchInput.value.length;
        activeSearchInput.setSelectionRange(len, len);
    }

    let goalSearchDebounceTimer;
    // Auto-search on typing with debounce (submits form, loads URL parameters & queries across all tables)
    $(document).on('input', '.goal-search-input', function() {
        const input = $(this);
        const form = input.closest('form');
        clearTimeout(goalSearchDebounceTimer);
        goalSearchDebounceTimer = setTimeout(function() {
            if (form.length) {
                form.submit();
            }
        }, 500);
    });

    // Instant search on pressing Enter key
    $(document).on('keydown', '.goal-search-input', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            clearTimeout(goalSearchDebounceTimer);
            $(this).closest('form').submit();
        }
    });

    // Smart Goal Cycle & Strategic Pillar Select Handlers
    window.handleGoalCycleSelect = function(selectEl) {
        const $sel = $(selectEl);
        const container = selectEl.closest('.odoo-form-group') || selectEl.parentElement;
        if (!container) return;

        const selectWrap = container.querySelector('.cycle-select-wrap');
        const inputWrap = container.querySelector('.cycle-input-wrap');
        const titleInput = container.querySelector('.cycle-title-input');
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
        }
    };

    window.switchToGoalCycleSelect = function(btn) {
        const container = btn.closest('.odoo-form-group') || btn.parentElement;
        if (!container) return;

        const selectWrap = container.querySelector('.cycle-select-wrap');
        const inputWrap = container.querySelector('.cycle-input-wrap');
        const titleInput = container.querySelector('.cycle-title-input');
        const $select = $(container).find('.goal-cycle-select');

        if (titleInput) titleInput.value = '';
        if (inputWrap) {
            inputWrap.classList.add('d-none');
            inputWrap.classList.remove('d-flex');
        }
        if (selectWrap) {
            selectWrap.classList.remove('d-none');
            selectWrap.classList.add('d-flex');
        }
        if ($select.length) {
            $select.val('').trigger('change.select2');
        }
    };

    window.handleGoalPillarSelect = function(selectEl) {
        const $sel = $(selectEl);
        const container = selectEl.closest('.odoo-form-group') || selectEl.parentElement;
        if (!container) return;

        const selectWrap = container.querySelector('.pillar-select-wrap');
        const inputWrap = container.querySelector('.pillar-input-wrap');
        const titleInput = container.querySelector('.pillar-title-input');
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
        }
    };

    window.switchToGoalPillarSelect = function(btn) {
        const container = btn.closest('.odoo-form-group') || btn.parentElement;
        if (!container) return;

        const selectWrap = container.querySelector('.pillar-select-wrap');
        const inputWrap = container.querySelector('.pillar-input-wrap');
        const titleInput = container.querySelector('.pillar-title-input');
        const $select = $(container).find('.goal-pillar-select');

        if (titleInput) titleInput.value = '';
        if (inputWrap) {
            inputWrap.classList.add('d-none');
            inputWrap.classList.remove('d-flex');
        }
        if (selectWrap) {
            selectWrap.classList.remove('d-none');
            selectWrap.classList.add('d-flex');
        }
        if ($select.length) {
            $select.val('').trigger('change.select2');
        }
    };

    // Ensure select2 is initialized in createGoalModal
    if (window.jQuery) {
        $('#createGoalModal').on('shown.bs.modal', function() {
            const $modal = $(this);
            $modal.find('.goal-cycle-select, .goal-pillar-select').each(function() {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $modal,
                        width: '100%'
                    });
                }
            });
            handleOwnerTypeChange($('#ownerTypeSelect').val() || 'employee');
        });

        $(document).on('change change.select2 select2:select', '#ownerTypeSelect', function() {
            handleOwnerTypeChange(this.value);
        });

        $(document).on('click', '.btn-quick-check-in', function() {
            var goalId = $(this).data('goal-id');
            var title = $(this).data('goal-title');
            var keyResults = $(this).data('goal-krs');
            prepareCheckIn(goalId, title, keyResults);
        });

        $(document).on('change change.select2 select2:select', '.goal-cycle-select', function() {
            handleGoalCycleSelect(this);
        });

        $(document).on('change change.select2 select2:select', '.goal-pillar-select', function() {
            handleGoalPillarSelect(this);
        });
    }

    // Sync active tab with URL query state
    document.querySelectorAll('#goalTabs [data-bs-toggle="tab"]').forEach(function(tab) {
        tab.addEventListener('shown.bs.tab', function(e) {
            var target = e.target.getAttribute('data-bs-target').replace('#', '');
            var tabKey = 'company_goals';
            if (target === 'my-team') tabKey = 'my_team_goals';
            else if (target === 'alignment-tree') tabKey = 'alignment_tree';
            else if (target === 'cycles-pillars') tabKey = 'cycles_pillars';

            document.querySelectorAll('.goalFilterForm input[name="active_tab"]').forEach(function(input) {
                input.value = tabKey;
            });

            var url = new URL(window.location);
            url.searchParams.set('active_tab', tabKey);
            window.history.replaceState({}, '', url);
        });
    });
</script>
@endpush
