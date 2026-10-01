@extends('layouts.duralux')

@section('title', 'Goals & OKRs Management | HRMS')
@section('page-title', 'Goals & OKRs Management')
@section('breadcrumb', 'HRMS / Performance / Goals & OKRs')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus-circle" data-bs-toggle="modal" data-bs-target="#createGoalModal" class="fw-bold">
            Create Objective / Goal
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
                    'label' => 'Company & Strategic',
                    'active' => ($activeTab === 'company_goals'),
                    'icon' => 'feather-briefcase',
                ],
                [
                    'id' => 'my-team',
                    'label' => 'My & Team Goals',
                    'active' => ($activeTab === 'my_team_goals'),
                    'icon' => 'feather-user-check',
                ],
                [
                    'id' => 'alignment-tree',
                    'label' => 'Cascading Alignment Tree',
                    'active' => ($activeTab === 'alignment_tree'),
                    'icon' => 'feather-git-merge',
                ],
            ];
            if ($isHrAdmin) {
                $goalTabs[] = [
                    'id' => 'cycles-pillars',
                    'label' => 'Cycles & Pillars',
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
                        <h5 class="fw-bold text-dark mb-0"><i class="feather-briefcase me-2 text-primary"></i> Company & Strategic Objectives</h5>
                        <p class="text-muted fs-12 mb-0">Define and monitor top-level organization objectives and strategic pillars</p>
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
                            <input type="text" name="search" id="companyGoalSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13 goal-search-input" placeholder="Search goals, owner, code, pillar..." value="{{ request('search') }}" autocomplete="off" style="box-shadow: none; height: 100%; outline: none;">
                        </form>

                        <!-- Sort Dropdown -->
                        <x-ui.sort-dropdown label="Sort">
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort', 'newest') === 'newest' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('newest')">
                                <span>Newest First</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'oldest' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('oldest')">
                                <span>Oldest First</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'progress_desc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('progress_desc')">
                                <span>Highest Progress</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'progress_asc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('progress_asc')">
                                <span>Lowest Progress</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'due_date' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('due_date')">
                                <span>Due Date (Nearest)</span>
                            </a>
                        </x-ui.sort-dropdown>

                        <!-- Filter Dropdown -->
                        <x-ui.filter label="Filter" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                            <form method="GET" action="{{ route('hrms.goals.index') }}" class="goalFilterForm">
                                <input type="hidden" name="active_tab" value="company_goals">
                                <input type="hidden" name="sort" class="goal_sort_input" value="{{ request('sort', 'newest') }}">
                                <input type="hidden" name="search" class="filter_search_hidden" value="{{ request('search') }}">

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Goal Cycle</label>
                                    <x-ui.odoo-form-ui type="select" name="cycle_id">
                                        <option value="">All Goal Cycles</option>
                                        @foreach($cycles as $c)
                                            <option value="{{ $c->id }}" {{ ($selectedCycleId == $c->id && request()->has('cycle_id')) || request('cycle_id') == $c->id ? 'selected' : '' }}>
                                                {{ $c->name }} ({{ strtoupper($c->status) }})
                                            </option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Strategic Pillar</label>
                                    <x-ui.odoo-form-ui type="select" name="category_id">
                                        <option value="">All Strategic Pillars</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Health Status</label>
                                    <x-ui.odoo-form-ui type="select" name="health_status">
                                        <option value="">All Health Statuses</option>
                                        <option value="on_track" {{ request('health_status') == 'on_track' ? 'selected' : '' }}>🟢 On Track</option>
                                        <option value="at_risk" {{ request('health_status') == 'at_risk' ? 'selected' : '' }}>🟡 At Risk</option>
                                        <option value="behind" {{ request('health_status') == 'behind' ? 'selected' : '' }}>🔴 Behind</option>
                                        <option value="completed" {{ request('health_status') == 'completed' ? 'selected' : '' }}>🔵 Completed</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                    <x-ui.odoo-form-ui type="select" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                                        <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="dropdown-divider my-3"></div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                                    <a href="{{ route('hrms.goals.index', ['active_tab' => 'company_goals']) }}" class="btn btn-light btn-sm border flex-grow-1 text-center text-decoration-none">Reset</a>
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
                        <h6 class="fw-bold text-dark mb-1">No Company Strategic Goals Found</h6>
                        <p class="text-muted fs-13 mb-0">Define top-level organization objectives to cascade down to departments and teams.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="companyGoalsTable">
                            <thead class="table-light text-muted fs-11 text-uppercase fw-bold">
                                <tr>
                                    <th style="width: 140px;">Code / Horizon</th>
                                    <th>Objective & Strategic Pillar</th>
                                    <th style="width: 180px;">Owner / Scope</th>
                                    <th style="width: 140px;">Key Results</th>
                                    <th style="width: 160px;">Progress & Health</th>
                                    <th style="width: 120px;" class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="companyGoalsTableBody">
                                @foreach($companyGoals as $g)
                                <tr class="company-goal-row">
                                    <td class="align-top py-3">
                                        <div class="fw-bold text-dark fs-12">{{ $g->code }}</div>
                                        <div class="text-muted fs-11 mt-1 text-wrap" style="max-width: 130px; word-break: break-word; line-height: 1.35;">
                                            {{ $g->cycle?->name ?? 'General' }}
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
                                                <i class="feather-globe me-1"></i> Organization-Wide
                                            </span>
                                        @elseif($g->owner_type === 'department')
                                            <span class="badge bg-light text-dark border fs-11 text-wrap text-start">
                                                <i class="feather-users me-1"></i> {{ $g->department?->name ?? 'Dept Goal' }}
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
                                                                 title="+{{ $assignedEmps->count() - 3 }} more">
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
                                                <span class="badge bg-light text-muted border fs-11">Individual</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="align-top py-3">
                                        <span class="badge bg-light text-dark border fs-11">
                                            <i class="feather-list me-1 text-primary"></i> {{ $g->keyResults->count() }} Key Results
                                        </span>
                                    </td>
                                    <td class="align-top py-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold fs-12 text-dark">{{ number_format($g->progress_percentage, 0) }}%</span>
                                            @if($g->health_status === 'on_track')
                                                <span class="badge bg-success-subtle text-success fs-10 fw-semibold">🟢 On Track</span>
                                            @elseif($g->health_status === 'at_risk')
                                                <span class="badge bg-warning-subtle text-warning fs-10 fw-semibold">🟡 At Risk</span>
                                            @elseif($g->health_status === 'behind')
                                                <span class="badge bg-danger-subtle text-danger fs-10 fw-semibold">🔴 Behind</span>
                                            @else
                                                <span class="badge bg-info-subtle text-info fs-10 fw-semibold">🔵 Completed</span>
                                            @endif
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar {{ $g->health_status === 'behind' ? 'bg-danger' : ($g->health_status === 'at_risk' ? 'bg-warning' : 'bg-success') }}" 
                                                 role="progressbar" style="width: {{ min(100, max(0, $g->progress_percentage)) }}%"></div>
                                        </div>
                                    </td>
                                    <td class="align-top py-3 text-end pe-3" style="white-space: nowrap;">
                                        <div class="d-flex align-items-center justify-content-end gap-1">
                                            <x-ui.icon-btn href="{{ route('hrms.goals.show', ['goal' => $g->id, 'from_tab' => 'company_goals']) }}" icon="feather-eye" variant="soft-primary" size="sm" title="View Objective" />
                                            <button type="button" class="btn btn-sm btn-light border text-primary" data-bs-toggle="modal" data-bs-target="#quickCheckInModal" onclick="prepareCheckIn({{ $g->id }}, '{{ addslashes($g->title) }}', {{ json_encode($g->keyResults) }})" title="Quick Check-in">
                                                <i class="feather-check-square fs-13"></i>
                                            </button>
                                            @if($isHrAdmin)
                                            <form method="POST" action="{{ route('hrms.goals.destroy', $g->id) }}" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this goal?')">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.icon-btn type="submit" icon="feather-trash-2" variant="soft-danger" size="sm" title="Delete Goal" />
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
                        <h5 class="fw-bold text-dark mb-0"><i class="feather-user-check me-2 text-primary"></i> My & Team Objectives</h5>
                        <p class="text-muted fs-12 mb-0">Track individual and departmental goals aligned with strategic organizational pillars</p>
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
                            <input type="text" name="search" id="myTeamGoalSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13 goal-search-input" placeholder="Search goals, owner, code, pillar..." value="{{ request('search') }}" autocomplete="off" style="box-shadow: none; height: 100%; outline: none;">
                        </form>

                        <!-- Sort Dropdown -->
                        <x-ui.sort-dropdown label="Sort">
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort', 'newest') === 'newest' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('newest')">
                                <span>Newest First</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'oldest' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('oldest')">
                                <span>Oldest First</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'progress_desc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('progress_desc')">
                                <span>Highest Progress</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'progress_asc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('progress_asc')">
                                <span>Lowest Progress</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'due_date' ? 'active' : '' }}" href="javascript:void(0);" onclick="setGoalSort('due_date')">
                                <span>Due Date (Nearest)</span>
                            </a>
                        </x-ui.sort-dropdown>

                        <!-- Filter Dropdown -->
                        <x-ui.filter label="Filter" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                            <form method="GET" action="{{ route('hrms.goals.index') }}" class="goalFilterForm">
                                <input type="hidden" name="active_tab" value="my_team_goals">
                                <input type="hidden" name="sort" class="goal_sort_input" value="{{ request('sort', 'newest') }}">
                                <input type="hidden" name="search" class="filter_search_hidden" value="{{ request('search') }}">

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Goal Cycle</label>
                                    <x-ui.odoo-form-ui type="select" name="cycle_id">
                                        <option value="">All Goal Cycles</option>
                                        @foreach($cycles as $c)
                                            <option value="{{ $c->id }}" {{ ($selectedCycleId == $c->id && request()->has('cycle_id')) || request('cycle_id') == $c->id ? 'selected' : '' }}>
                                                {{ $c->name }} ({{ strtoupper($c->status) }})
                                            </option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Strategic Pillar</label>
                                    <x-ui.odoo-form-ui type="select" name="category_id">
                                        <option value="">All Strategic Pillars</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Health Status</label>
                                    <x-ui.odoo-form-ui type="select" name="health_status">
                                        <option value="">All Health Statuses</option>
                                        <option value="on_track" {{ request('health_status') == 'on_track' ? 'selected' : '' }}>🟢 On Track</option>
                                        <option value="at_risk" {{ request('health_status') == 'at_risk' ? 'selected' : '' }}>🟡 At Risk</option>
                                        <option value="behind" {{ request('health_status') == 'behind' ? 'selected' : '' }}>🔴 Behind</option>
                                        <option value="completed" {{ request('health_status') == 'completed' ? 'selected' : '' }}>🔵 Completed</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                    <x-ui.odoo-form-ui type="select" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                                        <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="dropdown-divider my-3"></div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                                    <a href="{{ route('hrms.goals.index', ['active_tab' => 'my_team_goals']) }}" class="btn btn-light btn-sm border flex-grow-1 text-center text-decoration-none">Reset</a>
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
                        <h6 class="fw-bold text-dark mb-1">No Personal or Team Goals Found</h6>
                        <p class="text-muted fs-13 mb-0">Create goals aligned with strategic company pillars to track and report your progress.</p>
                    </div>
                @else
                    <div class="row g-3" id="myTeamGoalsGrid">
                        @foreach($myTeamGoals as $g)
                        <div class="col-md-6 col-lg-4 my-team-goal-card">
                            <div class="card rounded-3 h-100 bg-white hover-shadow transition-all" style="border: 1px solid #e2e8f0; box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
                                <div class="card-body p-3.5 d-flex flex-column justify-content-between">
                                    <div>
                                        <!-- Header Pills -->
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="d-flex flex-column gap-2 align-items-start">
                                                <span class="badge bg-light text-muted border fs-10 fw-bold px-2 py-1">{{ $g->code }}</span>
                                                @if($g->category)
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-10 fw-semibold d-inline-flex align-items-center gap-1.5 py-1 px-2 text-wrap text-start">
                                                        <i class="feather-target" style="font-size: 11px; line-height: 1;"></i>
                                                        <span>{{ $g->category->name }}</span>
                                                    </span>
                                                @endif
                                            </div>
                                            <div>
                                                @if($g->health_status === 'on_track')
                                                    <span class="badge bg-success-subtle text-success fs-10 fw-semibold">🟢 On Track</span>
                                                @elseif($g->health_status === 'at_risk')
                                                    <span class="badge bg-warning-subtle text-warning fs-10 fw-semibold">🟡 At Risk</span>
                                                @elseif($g->health_status === 'behind')
                                                    <span class="badge bg-danger-subtle text-danger fs-10 fw-semibold">🔴 Behind</span>
                                                @else
                                                    <span class="badge bg-info-subtle text-info fs-10 fw-semibold">🔵 Completed</span>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Title -->
                                        <a href="{{ route('hrms.goals.show', ['goal' => $g->id, 'from_tab' => 'my_team_goals']) }}" class="fw-bold text-dark text-decoration-none fs-14 mb-3 d-block text-truncate-2" style="line-height: 1.4;">
                                            {{ $g->title }}
                                        </a>

                                        <!-- Parent Alignment Badge -->
                                        @if($g->parentGoal)
                                            <div class="mb-3">
                                                <small class="text-muted fs-11">
                                                    <i class="feather-corner-down-right text-primary me-1"></i>
                                                    Aligned to: <span class="fw-semibold text-dark">{{ Str::limit($g->parentGoal->title, 32) }}</span>
                                                </small>
                                            </div>
                                        @endif

                                        <!-- Key Results Summary List -->
                                        @if($g->keyResults->isNotEmpty())
                                            <div class="bg-light p-3 rounded-3 my-3 border border-light-subtle">
                                                <div class="text-muted fw-bold text-uppercase fs-10 mb-2" style="letter-spacing: 0.04em;">
                                                    <i class="feather-list text-primary me-1"></i>Key Results ({{ $g->keyResults->count() }})
                                                </div>
                                                <div class="d-flex flex-column gap-2">
                                                    @foreach($g->keyResults->take(2) as $kr)
                                                        <div class="d-flex justify-content-between align-items-center fs-11">
                                                            <span class="text-dark text-truncate me-2" style="max-width: 170px;" title="{{ $kr->title }}">{{ $kr->title }}</span>
                                                            <span class="fw-bold text-primary font-monospace flex-shrink-0">{{ number_format($kr->current_value, 0) }} / {{ number_format($kr->target_value, 0) }} {{ $kr->unit }}</span>
                                                        </div>
                                                    @endforeach
                                                    @if($g->keyResults->count() > 2)
                                                        <div class="text-muted fs-10 pt-1 border-top border-light-subtle">+ {{ $g->keyResults->count() - 2 }} more results</div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Bottom Meta & Actions -->
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center mb-1 fs-12">
                                            <span class="text-muted">Progress</span>
                                            <span class="fw-extrabold text-dark">{{ number_format($g->progress_percentage, 0) }}%</span>
                                        </div>
                                        <div class="progress mb-3" style="height: 6px;">
                                            <div class="progress-bar {{ $g->health_status === 'behind' ? 'bg-danger' : ($g->health_status === 'at_risk' ? 'bg-warning' : 'bg-primary') }}" 
                                                 role="progressbar" style="width: {{ min(100, max(0, $g->progress_percentage)) }}%"></div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                            @php
                                                $assignedEmployees = $g->employees->isNotEmpty() ? $g->employees : ($g->employee ? collect([$g->employee]) : collect());
                                            @endphp

                                            @if($assignedEmployees->count() > 1)
                                                <div class="d-flex align-items-center gap-1.5" title="{{ $assignedEmployees->pluck('full_name')->join(', ') }}">
                                                    <div class="d-flex align-items-center">
                                                        @foreach($assignedEmployees->take(3) as $idx => $emp)
                                                            <div class="avatar-initials rounded-circle border border-2 border-white shadow-xs" 
                                                                 style="width: 26px; height: 26px; font-size: 10px; margin-left: {{ $idx > 0 ? '-8px' : '0' }}; z-index: {{ 10 - $idx }};" 
                                                                 title="{{ $emp->full_name }}">
                                                                {{ strtoupper(substr($emp->full_name, 0, 1)) }}
                                                            </div>
                                                        @endforeach
                                                        @if($assignedEmployees->count() > 3)
                                                            <div class="avatar-initials rounded-circle border border-2 border-white shadow-xs bg-light text-muted" 
                                                                 style="width: 26px; height: 26px; font-size: 10px; margin-left: -8px; z-index: 5;"
                                                                 title="+{{ $assignedEmployees->count() - 3 }} more">
                                                                +{{ $assignedEmployees->count() - 3 }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <small class="text-muted fs-11 fw-semibold text-truncate" style="max-width: 110px;">
                                                        {{ $assignedEmployees->first()->full_name }}
                                                    </small>
                                                    <span class="badge bg-primary text-white rounded-pill px-1.5 py-0.5" style="font-size: 9px;">+{{ $assignedEmployees->count() - 1 }}</span>
                                                </div>
                                            @elseif($assignedEmployees->count() === 1)
                                                <div class="d-flex align-items-center gap-1.5" title="{{ $assignedEmployees->first()->full_name }}">
                                                    <div class="avatar-initials" style="width: 26px; height: 26px; font-size: 10px;">
                                                        {{ strtoupper(substr($assignedEmployees->first()->full_name, 0, 1)) }}
                                                    </div>
                                                    <small class="text-muted fs-11 fw-semibold text-truncate" style="max-width: 140px;">
                                                        {{ $assignedEmployees->first()->full_name }}
                                                    </small>
                                                </div>
                                            @else
                                                <div class="d-flex align-items-center gap-1.5">
                                                    <div class="avatar-initials" style="width: 26px; height: 26px; font-size: 10px;">
                                                        {{ strtoupper(substr($g->department?->name ?? 'O', 0, 1)) }}
                                                    </div>
                                                    <small class="text-muted fs-11 fw-semibold">{{ $g->department?->name ?? 'Organization' }}</small>
                                                </div>
                                            @endif

                                            <div class="d-flex align-items-center gap-1">
                                                <button type="button" class="btn btn-xs btn-light border fw-semibold text-dark shadow-none py-1 px-2 fs-11" data-bs-toggle="modal" data-bs-target="#quickCheckInModal" onclick="prepareCheckIn({{ $g->id }}, '{{ addslashes($g->title) }}', {{ json_encode($g->keyResults) }})">
                                                    <i class="feather-plus text-primary me-1"></i> Check-in
                                                </button>
                                                <x-ui.icon-btn href="{{ route('hrms.goals.show', ['goal' => $g->id, 'from_tab' => 'my_team_goals']) }}" icon="feather-arrow-right" variant="soft-primary" size="sm" title="View Objective" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- TAB 3: CASCADING ALIGNMENT TREE -->
            <div class="tab-pane fade {{ $activeTab === 'alignment_tree' ? 'show active' : '' }}" id="alignment-tree" role="tabpanel">
                <!-- TOP TOOLBAR: TITLE + BADGE -->
                <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><i class="feather-git-merge me-2 text-primary"></i> Cascading Alignment Tree</h5>
                        <p class="text-muted fs-12 mb-0">Visual hierarchical mapping of how individual key results roll up to organizational objectives</p>
                    </div>
                    <div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-12 fw-bold px-3 py-2">
                            <i class="feather-layers me-1"></i> {{ count($alignmentTree) }} Root Strategic Goals
                        </span>
                    </div>
                </div>

                @if(empty($alignmentTree))
                    <div class="text-center py-5">
                        <div class="p-3 bg-light rounded-circle d-inline-flex mb-3 text-muted">
                            <i class="feather-git-merge fs-36"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">No Cascading Alignments Configured</h6>
                        <p class="text-muted fs-13">Create company-level goals and link department/individual goals via the "Parent Goal" selector.</p>
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
                                                    <span class="text-muted">Progress</span>
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
                        <h5 class="fw-bold text-dark mb-0"><i class="feather-layers me-2 text-primary"></i> Cycles & Strategic Pillars</h5>
                        <p class="text-muted fs-12 mb-0">Configure performance review cycles, time horizons, and organizational focus pillars</p>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Cycles Master -->
                    <div class="col-lg-6">
                        <div class="card rounded-3 shadow-sm h-100 bg-white" style="border: 1px solid #e2e8f0;">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2.5 border-bottom">
                                <span class="fw-bold text-dark fs-13"><i class="feather-calendar me-1.5 text-primary"></i> Goal Cycles & Time Horizons</span>
                                <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createCycleModal">
                                    New Cycle
                                </x-ui.button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="overflow-x: hidden;">
                                    <table class="table table-hover align-middle mb-0 w-100" style="table-layout: fixed;">
                                        <thead class="table-light text-muted fs-11 text-uppercase fw-bold">
                                            <tr>
                                                <th class="ps-3 py-2" style="width: 58%;">Cycle & Time Horizon</th>
                                                <th class="py-2" style="width: 26%;">Status</th>
                                                <th class="pe-3 py-2 text-end" style="width: 16%;">Action</th>
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
                                                    <form method="POST" action="{{ route('hrms.goals.cycle.destroy', $c->id) }}" class="d-inline" onsubmit="return confirm('Delete this cycle?')">
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
                                                    No goal cycles configured yet.
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
                                <span class="fw-bold text-dark fs-13"><i class="feather-target me-1.5 text-primary"></i> Strategic Pillars & Categories</span>
                                <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                                    New Pillar
                                </x-ui.button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="overflow-x: hidden;">
                                    <table class="table table-hover align-middle mb-0 w-100" style="table-layout: fixed;">
                                        <thead class="table-light text-muted fs-11 text-uppercase fw-bold">
                                            <tr>
                                                <th class="ps-3 py-2" style="width: 58%;">Strategic Pillar</th>
                                                <th class="py-2" style="width: 26%;">Pillar Code</th>
                                                <th class="pe-3 py-2 text-end" style="width: 16%;">Action</th>
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
                                                    <form method="POST" action="{{ route('hrms.goals.category.destroy', $cat->id) }}" class="d-inline" onsubmit="return confirm('Delete this category?')">
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
                                                    No strategic pillars configured yet.
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
<x-ui.modal id="createGoalModal" title="<i class='feather-plus-circle text-primary me-2'></i> Create New Objective / Goal" size="xl" :showFooter="false" :centered="true" :scrollable="true">
    <form method="POST" action="{{ route('hrms.goals.store') }}" id="createGoalForm">
        @csrf
        <x-ui.odoo-form-ui type="sheet" class="p-2">
            <!-- SECTION 1: HEADER & OBJECTIVE DEFINITION -->
            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                <span class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 22px; height: 22px; font-size: 11px; background-color: var(--bs-primary, #852d3c);">1</span>
                <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase letter-spacing-1">Objective & Classification</h6>
            </div>
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="Objective Title" name="title" placeholder="e.g. Accelerate Enterprise SaaS Pipeline Expansion" :required="true" />
                    <!-- GOAL CYCLE WITH INLINE CUSTOM ADD -->
                    <div class="odoo-form-group mb-2">
                        <label class="odoo-form-label">Goal Cycle</label>
                        <div class="flex-grow-1">
                            <div class="cycle-select-wrap d-flex align-items-center" style="width: 100%;">
                                <select name="goal_cycle_id" class="odoo-form-control goal-cycle-select select2" style="width: 100%;" onchange="handleGoalCycleSelect(this)">
                                    <option value="">Choose Goal Cycle...</option>
                                    <option value="__custom__">+ Add Custom Goal Cycle (Type Manually)</option>
                                    @foreach($cycles as $c)
                                        <option value="{{ $c->id }}" {{ ($selectedCycleId ? $selectedCycleId == $c->id : ($c->status === 'active' || $loop->first)) ? 'selected' : '' }}>{{ $c->name }} ({{ strtoupper($c->status) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="cycle-input-wrap d-none align-items-center gap-1">
                                <input type="text" name="custom_goal_cycle" class="odoo-form-control cycle-title-input" placeholder="Type custom goal cycle name..." />
                                <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="Switch back to list" onclick="switchToGoalCycleSelect(this)" style="height: 24px; font-size: 11px; white-space: nowrap;">
                                    <i class="feather-list"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- STRATEGIC PILLAR WITH INLINE CUSTOM ADD -->
                    <div class="odoo-form-group mb-2">
                        <label class="odoo-form-label">Strategic Pillar</label>
                        <div class="flex-grow-1">
                            <div class="pillar-select-wrap d-flex align-items-center" style="width: 100%;">
                                <select name="goal_category_id" class="odoo-form-control goal-pillar-select select2" style="width: 100%;" onchange="handleGoalPillarSelect(this)">
                                    <option value="">Choose Strategic Pillar...</option>
                                    <option value="__custom__">+ Add Custom Strategic Pillar (Type Manually)</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="pillar-input-wrap d-none align-items-center gap-1">
                                <input type="text" name="custom_goal_category" class="odoo-form-control pillar-title-input" placeholder="Type custom strategic pillar name..." />
                                <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="Switch back to list" onclick="switchToGoalPillarSelect(this)" style="height: 24px; font-size: 11px; white-space: nowrap;">
                                    <i class="feather-list"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Scope" name="owner_type" id="ownerTypeSelect" onchange="handleOwnerTypeChange(this.value)">
                        <option value="company">Organization-Wide</option>
                        <option value="department">Department</option>
                        <option value="employee" selected>Individual Employee</option>
                    </x-ui.odoo-form-ui>
                    
                    <div id="deptSelectCol" style="display: none;">
                        <x-ui.odoo-form-ui type="select" label="Department" name="department_id">
                            <option value="">Choose Department...</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div id="empSelectCol">
                        <x-ui.odoo-form-ui type="select" label="Employees" name="employee_ids[]" id="createGoalEmployeeSelect" :multiple="true" select2Selector="select2">
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}" {{ $currentEmployee?->id == $emp->id ? 'selected' : '' }}>
                                    {{ $emp->full_name }} ({{ $emp->employee_id }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <x-ui.odoo-form-ui type="select" label="Parent Goal" name="parent_goal_id">
                        <option value="">None (Top-Level Goal)</option>
                        @foreach($parentGoalOptions as $pg)
                            <option value="{{ $pg->id }}">{{ $pg->code }} - {{ Str::limit($pg->title, 35) }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Priority" name="priority">
                        <option value="low">Low Priority</option>
                        <option value="medium" selected>Medium Priority</option>
                        <option value="high">High Priority</option>
                        <option value="critical">Critical / Urgent</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Target Due Date" name="due_date" value="{{ date('Y-m-d', strtotime('+90 days')) }}" />
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="Context & Notes" name="description" placeholder="Provide background on why this objective matters and key expectations..." rows="2" />
                </div>
            </div>

            <!-- SECTION 2: MEASURABLE KEY RESULTS -->
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 22px; height: 22px; font-size: 11px; background-color: var(--bs-primary, #852d3c);">2</span>
                    <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase letter-spacing-1">Measurable Key Results (OKRs)</h6>
                </div>
                <x-ui.button type="button" variant="light" size="sm" icon="feather-plus" class="border fw-semibold text-dark shadow-none" onclick="addKeyResultRow()">
                    Add Key Result
                </x-ui.button>
            </div>

            <div class="table-responsive mb-3">
                <table class="odoo-table mb-0" id="keyResultsTable">
                    <thead>
                        <tr>
                            <th style="width: 44%;">Key Result Description <span class="text-danger">*</span></th>
                            <th style="width: 22%;">Metric Type</th>
                            <th style="width: 14%;">Unit</th>
                            <th class="text-end" style="width: 14%;">Target</th>
                            <th class="text-center" style="width: 6%;"></th>
                        </tr>
                    </thead>
                    <tbody id="keyResultsContainer">
                        <tr class="kr-row">
                            <td>
                                <input type="text" name="kr_title[]" class="odoo-table-input" placeholder="e.g. Close $100K ARR in new deals" required>
                            </td>
                            <td>
                                <select name="kr_metric_type[]" class="form-select odoo-table-select kr-metric-select" onchange="handleMetricTypeChange(this)">
                                    <option value="percentage">% Percentage</option>
                                    <option value="currency">Currency ($)</option>
                                    <option value="numeric">Count (#)</option>
                                    <option value="boolean_milestone">Milestone (0/1)</option>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="kr_unit[]" class="odoo-table-input kr-unit-input" placeholder="%" value="%">
                            </td>
                            <td>
                                <input type="number" step="any" name="kr_target_value[]" class="odoo-table-input text-end" placeholder="100" value="100" required>
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row-btn" onclick="removeKrRow(this)" title="Remove Key Result">
                                    <i class="feather-trash-2"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-plus-circle" class="fw-bold">Create Objective</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL 2: QUICK CHECK-IN -->
<!-- ========================================================================= -->
<x-ui.modal id="quickCheckInModal" title="<i class='feather-check-square text-primary me-2'></i> Log Progress Check-in" size="lg" :showFooter="false" :centered="true">
    <form method="POST" action="" id="quickCheckInForm">
        @csrf
        <x-ui.odoo-form-ui type="sheet" class="p-2">
            <div class="p-3 bg-light rounded-3 mb-3 border">
                <small class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Target Objective</small>
                <div class="fw-bold text-dark fs-14" id="checkInGoalTitle"></div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12" id="checkInKrSelectGroup">
                    <x-ui.odoo-form-ui type="select" label="Target Key Result" name="goal_key_result_id" id="checkInKrSelect" onchange="updateKrInputUnit(this)">
                        <option value="">Objective Overall Progress</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="New Value" name="new_value" id="checkInNewValue" placeholder="Updated current value" :required="true" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Health Status" name="health_status" :required="true">
                        <option value="on_track" selected>🟢 On Track</option>
                        <option value="at_risk">🟡 At Risk</option>
                        <option value="behind">🔴 Behind</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="Check-in Note" name="comment" placeholder="Briefly describe what progress was accomplished..." rows="2" :required="true" />
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="Blockers / Risks" name="blockers" placeholder="Any roadblocks requiring management assistance?" rows="1" />
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-check" class="fw-bold">Save Check-in</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL 3: CREATE CYCLE -->
<!-- ========================================================================= -->
<x-ui.modal id="createCycleModal" title="<i class='feather-calendar text-primary me-2'></i> Create Goal Cycle" size="lg" :showFooter="false" :centered="true">
    <form method="POST" action="{{ route('hrms.goals.cycle.store') }}">
        @csrf
        <x-ui.odoo-form-ui type="sheet" class="p-2">
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <x-ui.odoo-form-ui type="input" label="Cycle Name" name="name" placeholder="e.g. Q2 2026 OKR Cycle" :required="true" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Start Date" name="start_date" value="{{ date('Y-m-01') }}" :required="true" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="End Date" name="end_date" value="{{ date('Y-m-t', strtotime('+3 months')) }}" :required="true" />
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="Guidance and scope for this time horizon..." rows="2" />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-save" class="fw-bold">Save Cycle</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL 4: CREATE STRATEGIC PILLAR (CATEGORY) -->
<!-- ========================================================================= -->
<x-ui.modal id="createCategoryModal" title="<i class='feather-target text-primary me-2'></i> Create Strategic Pillar" size="lg" :showFooter="false" :centered="true">
    <form method="POST" action="{{ route('hrms.goals.category.store') }}">
        @csrf
        <x-ui.odoo-form-ui type="sheet" class="p-2">
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <x-ui.odoo-form-ui type="input" label="Pillar Name" name="name" placeholder="e.g. Revenue & Growth" :required="true" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="Pillar Code" name="code" placeholder="e.g. REV" />
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="Strategic pillar focus and scope..." rows="2" />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-save" class="fw-bold">Save Pillar</x-ui.button>
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
            placeholder: 'Select Employee(s)...',
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
                <input type="text" name="kr_title[]" class="odoo-table-input" placeholder="e.g. Close $100K ARR in new deals" required>
            </td>
            <td>
                <select name="kr_metric_type[]" class="form-select odoo-table-select kr-metric-select" onchange="handleMetricTypeChange(this)">
                    <option value="percentage">% Percentage</option>
                    <option value="currency">Currency ($)</option>
                    <option value="numeric">Count (#)</option>
                    <option value="boolean_milestone">Milestone (0/1)</option>
                </select>
            </td>
            <td>
                <input type="text" name="kr_unit[]" class="odoo-table-input kr-unit-input" placeholder="%" value="%">
            </td>
            <td>
                <input type="number" step="any" name="kr_target_value[]" class="odoo-table-input text-end" placeholder="100" value="100" required>
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row-btn" onclick="removeKrRow(this)" title="Remove Key Result">
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
        krSelect.innerHTML = '<option value="">Objective Overall Progress (%)</option>';
        if (keyResults && keyResults.length > 0) {
            keyResults.forEach(function(kr) {
                var opt = document.createElement('option');
                opt.value = kr.id;
                opt.textContent = kr.title + ' (Current: ' + kr.current_value + ' ' + kr.unit + ' / Target: ' + kr.target_value + ' ' + kr.unit + ')';
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
            newValInput.placeholder = 'Current: ' + opt.getAttribute('data-current') + ' ' + opt.getAttribute('data-unit') + ' (Target: ' + opt.getAttribute('data-target') + ')';
        } else {
            newValInput.placeholder = 'Progress percentage (0 - 100%)';
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
