@extends('layouts.duralux')

@section('title', 'KRA & KPI Performance Management | HRMS')
@section('page-title', 'KRA & KPI Performance Management')
@section('breadcrumb', 'HRMS / Performance / KRA & KPI')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        @if($isHrOrAdmin)
            <x-ui.button variant="primary" icon="feather-user-check" data-bs-toggle="modal" data-bs-target="#assignTemplateModal" class="fw-bold">
                Assign Goal Sheets
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

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        @php
            $mainTabs = [
                ['id' => 'tab-scorecards', 'label' => 'All Scorecards', 'active' => ($activeTab === 'plans'), 'icon' => 'feather-list'],
            ];
            if ($isHrOrAdmin) {
                $mainTabs[] = ['id' => 'tab-cycles', 'label' => 'Appraisal Cycles', 'active' => ($activeTab === 'cycles'), 'icon' => 'feather-calendar'];
                $mainTabs[] = ['id' => 'tab-library', 'label' => 'KRA & KPI Library', 'active' => ($activeTab === 'library'), 'icon' => 'feather-database'];
                $mainTabs[] = ['id' => 'tab-templates', 'label' => 'Role Templates', 'active' => ($activeTab === 'templates'), 'icon' => 'feather-layers'];
            }
            if ($myPlan) {
                $mainTabs[] = ['id' => 'tab-mygoals', 'label' => 'My Active Goals', 'active' => ($activeTab === 'myplan'), 'icon' => 'feather-target'];
            }
            if ($teamPlans->isNotEmpty()) {
                $mainTabs[] = ['id' => 'tab-team', 'label' => 'Team Reviews', 'active' => ($activeTab === 'team'), 'icon' => 'feather-users'];
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
                    <input type="text" name="search" id="kraSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13" placeholder="Search by name, ID, plan #..." value="{{ request('search') }}" autocomplete="off" style="box-shadow: none; height: 100%; outline: none;">
                </form>

                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown label="SORT">
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort', 'newest') === 'newest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}">
                        <span>Newest First</span>
                        @if(request('sort', 'newest') === 'newest') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'oldest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'oldest']) }}">
                        <span>Oldest First</span>
                        @if(request('sort') === 'oldest') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'score_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'score_desc']) }}">
                        <span>Highest Score First</span>
                        @if(request('sort') === 'score_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'score_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'score_asc']) }}">
                        <span>Lowest Score First</span>
                        @if(request('sort') === 'score_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <x-ui.filter label="FILTER">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                    <form method="GET" action="{{ route('hrms.kra-kpi.index') }}">
                        <input type="hidden" name="active_tab" value="{{ $activeTab }}">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Appraisal Cycle</label>
                            <x-ui.odoo-form-ui type="select" name="filter_cycle">
                                <option value="">All Cycles</option>
                                @foreach($cycles as $c)
                                    <option value="{{ $c->id }}" @selected(request('filter_cycle') == $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="filter_status">
                                <option value="">All Statuses</option>
                                <option value="overdue" @selected(request('filter_status') === 'overdue')>⚠️ Overdue Submissions</option>
                                <option value="draft" @selected(request('filter_status') === 'draft')>Draft</option>
                                <option value="submitted" @selected(request('filter_status') === 'submitted')>Goals Submitted</option>
                                <option value="approved" @selected(request('filter_status') === 'approved')>Goals Active</option>
                                <option value="self_reviewed" @selected(request('filter_status') === 'self_reviewed')>Self-Reviewed</option>
                                <option value="manager_reviewed" @selected(request('filter_status') === 'manager_reviewed')>Manager-Reviewed</option>
                                <option value="calibrated" @selected(request('filter_status') === 'calibrated')>Calibrated</option>
                                <option value="signed_off" @selected(request('filter_status') === 'signed_off')>Completed & Signed</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Department</label>
                            <x-ui.odoo-form-ui type="select" name="filter_department">
                                <option value="">All Departments</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" @selected(request('filter_department') == $dept->id)>{{ $dept->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4 pt-2 border-top">
                            <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">APPLY FILTERS</x-ui.button>
                            <x-ui.button href="{{ route('hrms.kra-kpi.index', ['active_tab' => $activeTab]) }}" variant="light" size="sm" class="border flex-grow-1">RESET</x-ui.button>
                        </div>
                    </form>
                </x-ui.filter>
            </div>
        </div>

        <!-- TABS CONTENT AREA -->
        <div class="tab-content" id="kraTabsContent">

            <!-- TAB 1: ALL SCORECARDS & APPRAISALS -->
            <div class="tab-pane fade {{ $activeTab === 'plans' ? 'show active' : '' }}" id="tab-scorecards" role="tabpanel">
                <div class="table-responsive" style="overflow-x: auto;">
                    <x-ui.table hoverable>
                        <thead>
                            <tr>
                                <th style="width: 25%;">Employee & Role</th>
                                <th style="width: 14%;">Plan #</th>
                                <th style="width: 20%;">Appraisal Cycle</th>
                                <th style="width: 12%;">Goals Count</th>
                                <th style="width: 13%;">Score & Grade</th>
                                <th style="width: 11%;">Stage Status</th>
                                <th style="width: 5%;" class="text-end">Actions</th>
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
                                                <div class="fw-bold text-dark fs-13 text-wrap">{{ $plan->employee->full_name ?? 'Employee' }}</div>
                                                <div class="small text-muted fs-11 text-wrap lh-sm">
                                                    {{ $plan->employee->designation->name ?? 'Designation N/A' }} &bull; {{ $plan->employee->department->name ?? 'General' }}
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
                                        <div class="fw-semibold text-dark fs-12 text-wrap mb-1 lh-sm">{{ $plan->appraisalCycle->name ?? 'N/A' }}</div>
                                        <x-ui.badge variant="secondary" soft class="fs-10 text-uppercase">{{ $plan->appraisalCycle->period_type ?? 'Annual' }}</x-ui.badge>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $plan->items->count() }} KPIs</div>
                                        <div class="small text-muted fs-11">Weight: {{ $plan->total_weightage }}%</div>
                                    </td>
                                    <td>
                                        @if($plan->final_score !== null && in_array($plan->status, ['self_reviewed', 'manager_reviewed', 'calibrated', 'signed_off']))
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
                                            <span class="text-muted fs-12">In Progress</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $statusBadges = [
                                                'draft' => ['secondary', 'Draft Goals'],
                                                'submitted' => ['info', 'Goals Submitted'],
                                                'approved' => ['primary', 'Goals Active'],
                                                'self_reviewed' => ['warning', 'Self-Reviewed'],
                                                'manager_reviewed' => ['primary', 'Manager-Reviewed'],
                                                'calibrated' => ['success', 'Calibrated'],
                                                'signed_off' => ['success', 'Completed & Signed'],
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
                                    <td class="text-end text-nowrap">
                                        <div class="d-inline-flex justify-content-end align-items-center gap-1">
                                            <x-ui.icon-btn href="{{ route('hrms.kra-kpi.show', $plan->id) }}" icon="feather-eye" variant="soft-primary" size="sm" title="View Scorecard" />
                                            @if($isHrOrAdmin)
                                                <form method="POST" action="{{ route('hrms.kra-kpi.plan.destroy', $plan->id) }}" id="deletePlanForm_{{ $plan->id }}" class="d-inline m-0 p-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" title="Delete Scorecard" onclick="confirmAction({ title: 'Delete Scorecard', message: 'Delete scorecard #{{ $plan->plan_number }} and all associated data?', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deletePlanForm_{{ $plan->id }}').submit(); })" />
                                                </form>
                                            @endif
                                            @if($isHrOrAdmin && in_array($plan->status, ['manager_reviewed', 'calibrated', 'signed_off']) && $plan->final_score !== null && $plan->final_score < 60 && !$plan->pip_triggered)
                                                <form method="POST" action="{{ route('hrms.kra-kpi.trigger-pip', $plan->id) }}" id="pipTriggerForm_{{ $plan->id }}" class="d-inline m-0 p-0">
                                                    @csrf
                                                    <x-ui.icon-btn type="button" icon="feather-alert-octagon" variant="soft-danger" size="sm" title="Bridge to PIP" onclick="confirmAction({ title: 'Initiate PIP', message: 'Initiate 60-day formal Performance Improvement Plan (PIP) for this employee?', variant: 'danger', confirmText: 'Initiate PIP' }, function() { document.getElementById('pipTriggerForm_{{ $plan->id }}').submit(); })" />
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="feather-inbox fs-32 d-block mb-2 text-muted"></i>
                                        <h6 class="fw-bold mb-1">No Appraisal Scorecards Found</h6>
                                        <p class="fs-12 mb-0">Launch an appraisal cycle or click "Assign Goal Sheets" above to generate scorecards for employees.</p>
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

            <!-- TAB 2: APPRAISAL CYCLES -->
            @if($isHrOrAdmin)
            <div class="tab-pane fade {{ $activeTab === 'cycles' ? 'show active' : '' }}" id="tab-cycles" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark mb-0">Appraisal Review Cycles</h6>
                    <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createCycleModal" class="fw-bold">
                        Add Cycle
                    </x-ui.button>
                </div>
                <div class="table-responsive">
                    <x-ui.table hoverable>
                        <thead>
                            <tr>
                                <th>Cycle Name</th>
                                <th>Period Type</th>
                                <th>Evaluation Window</th>
                                <th>Milestone Deadlines</th>
                                <th>Weight Split (Goals / Comp)</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cycles as $c)
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $c->name }}</div>
                                        <div class="small text-muted fs-11">Code: {{ $c->code ?: 'N/A' }}</div>
                                    </td>
                                    <td class="text-capitalize">{{ $c->period_type }}</td>
                                    <td>{{ $c->start_date->format('d M Y') }} &rarr; {{ $c->end_date->format('d M Y') }}</td>
                                    <td>
                                        <div class="fs-12 text-dark">
                                            <span class="text-muted fs-11">Goals Due:</span> 
                                            <strong>{{ $c->goal_setting_deadline ? $c->goal_setting_deadline->format('d M Y') : 'Not Set' }}</strong>
                                        </div>
                                        <div class="fs-12 text-dark mt-0.5">
                                            <span class="text-muted fs-11">Self-Review:</span> 
                                            <strong>{{ $c->self_review_deadline ? $c->self_review_deadline->format('d M Y') : 'Not Set' }}</strong>
                                        </div>
                                    </td>
                                    <td>{{ $c->goal_weightage_percent }}% / {{ $c->competency_weightage_percent }}%</td>
                                    <td>
                                        <x-ui.badge variant="{{ $c->status == 'in_progress' ? 'success' : 'primary' }}" soft>
                                             {{ ucfirst(str_replace('_', ' ', $c->status)) }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('hrms.kra-kpi.cycle.destroy', $c->id) }}" id="deleteCycleForm_{{ $c->id }}" class="d-inline m-0 p-0">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" title="Delete Cycle" onclick="confirmAction({ title: 'Delete Appraisal Cycle', message: 'Delete this appraisal cycle and all associated configurations?', variant: 'danger', confirmText: 'Delete Cycle' }, function() { document.getElementById('deleteCycleForm_{{ $c->id }}').submit(); })" />
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No appraisal cycles configured.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-ui.table>
                </div>
            </div>

            <!-- TAB 3: KRA & KPI MASTER LIBRARY -->
            <div class="tab-pane fade {{ $activeTab === 'library' ? 'show active' : '' }}" id="tab-library" role="tabpanel">
                <div class="row g-4">
                    <!-- Left: KRA Focus Areas -->
                    <div class="col-lg-4 col-md-5">
                        <div class="border rounded-3 p-3 bg-white h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <h6 class="fw-bold text-dark mb-0">KRA Strategic Focus Areas</h6>
                                <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createKraModal" class="fw-bold">
                                    Add KRA
                                </x-ui.button>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                @foreach($kraCategories as $kra)
                                    <div class="px-3 py-2.5 rounded-2 border d-flex justify-content-between align-items-center bg-light">
                                        <div class="d-flex align-items-center ps-1">
                                            <div style="width: 12px; height: 12px; border-radius: 50%; background-color: {{ $kra->color }}; flex-shrink: 0; margin-right: 12px;"></div>
                                            <span class="fw-bold text-dark fs-13">{{ $kra->name }}</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 pe-1">
                                            <x-ui.badge variant="primary" soft>{{ $kra->kpi_masters_count }} KPIs</x-ui.badge>
                                            @if($isHrOrAdmin)
                                                <form method="POST" action="{{ route('hrms.kra-kpi.kra-category.destroy', $kra->id) }}" id="deleteKraForm_{{ $kra->id }}" class="d-inline m-0 p-0 ms-1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" title="Delete KRA Focus" onclick="confirmAction({ title: 'Delete KRA Focus Area', message: 'Delete KRA focus area and remove it from master library?', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deleteKraForm_{{ $kra->id }}').submit(); })" />
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Right: KPI Master Library Table -->
                    <div class="col-lg-8 col-md-7">
                        <div class="border rounded-3 p-3 bg-white h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <h6 class="fw-bold text-dark mb-0">KPI Master Metrics</h6>
                                <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createKpiMasterModal" class="fw-bold">
                                    Add Metric
                                </x-ui.button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="table-layout: auto;">
                                    <thead>
                                        <tr class="text-muted fs-12 border-bottom">
                                            <th style="white-space: normal;">KPI NAME</th>
                                            <th style="white-space: normal;">KRA CATEGORY</th>
                                            <th style="white-space: normal;">DIRECTION & UNIT</th>
                                            <th style="white-space: normal;">DEFAULT TARGET</th>
                                            @if($isHrOrAdmin)
                                                <th class="text-end" style="width: 50px;">ACTION</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($kpiMasters as $km)
                                            <tr>
                                                <td class="fw-bold text-dark fs-12" style="white-space: normal; word-break: break-word; max-width: 180px;">{{ $km->name }}</td>
                                                <td style="white-space: normal;"><x-ui.badge variant="primary" soft>{{ $km->kraCategory->name ?? 'General' }}</x-ui.badge></td>
                                                <td style="white-space: normal;">
                                                    <div class="small text-dark text-capitalize">{{ str_replace('_', ' ', $km->calculation_type) }}</div>
                                                    <div class="text-muted fs-11">({{ $km->unit }})</div>
                                                </td>
                                                <td style="white-space: normal;">
                                                    <span class="fw-bold fs-12 text-dark">{{ $km->default_target }}</span>
                                                    <span class="text-muted fs-11">{{ $km->unit }}</span>
                                                </td>
                                                @if($isHrOrAdmin)
                                                    <td class="text-end">
                                                        <form method="POST" action="{{ route('hrms.kra-kpi.kpi-master.destroy', $km->id) }}" id="deleteKmForm_{{ $km->id }}" class="d-inline m-0 p-0">
                                                            @csrf
                                                            @method('DELETE')
                                                            <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" title="Delete KPI Metric" onclick="confirmAction({ title: 'Delete Master KPI', message: 'Delete master KPI metric from library?', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deleteKmForm_{{ $km->id }}').submit(); })" />
                                                        </form>
                                                    </td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ $isHrOrAdmin ? '5' : '4' }}" class="text-center py-4 text-muted">No KPI metrics in library.</td>
                                            </tr>
                                        @endforelse
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
                    <h6 class="fw-bold text-dark mb-0">Role KPI Templates</h6>
                    <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createTemplateModal" class="fw-bold">
                        Create Template
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
                                            Dept: {{ $tpl->department->name ?? 'All Departments' }} &bull; Role: {{ $tpl->designation->name ?? 'All Roles' }}
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <x-ui.badge variant="primary" soft>{{ $tpl->items->count() }} KPIs</x-ui.badge>
                                        @if($isHrOrAdmin)
                                            <x-ui.icon-btn type="button" icon="feather-edit" variant="soft-primary" size="sm" title="Edit Role Template" data-bs-toggle="modal" data-bs-target="#editTemplateModal_{{ $tpl->id }}" />
                                            <form method="POST" action="{{ route('hrms.kra-kpi.template.destroy', $tpl->id) }}" id="deleteTplForm_{{ $tpl->id }}" class="d-inline m-0 p-0">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" title="Delete Role Template" onclick="confirmAction({ title: 'Delete Role Template', message: 'Delete this role template?', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deleteTplForm_{{ $tpl->id }}').submit(); })" />
                                            </form>
                                        @endif
                                    </div>
                                </div>
                                <div class="border-top pt-2 mt-auto">
                                    <small class="text-muted d-block fs-11 mb-1">Included KPIs:</small>
                                    <ul class="list-unstyled mb-0 fs-12">
                                        @foreach($tpl->items->take(4) as $ti)
                                            <li class="text-truncate text-muted">&bull; {{ $ti->title }} ({{ $ti->weightage }}%)</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center py-5 text-muted">
                            <i class="feather-layers fs-32 d-block mb-2"></i>
                            <h6>No Role Templates Created Yet</h6>
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
                        <h5 class="fw-bold text-dark mb-0">{{ $myPlan->appraisalCycle->name ?? 'Active Appraisal Cycle' }}</h5>
                        <small class="text-muted">Plan Number: <strong>{{ $myPlan->plan_number }}</strong> &bull; Manager: {{ $myPlan->manager->full_name ?? 'Direct Manager' }}</small>
                    </div>
                    <div>
                        <x-ui.button variant="primary" size="sm" icon="feather-external-link" href="{{ route('hrms.kra-kpi.show', $myPlan->id) }}" class="fw-bold">
                            Open Full Scorecard
                        </x-ui.button>
                    </div>
                </div>

                <div class="row g-3">
                    @foreach($myPlan->items as $item)
                        <div class="col-md-6 col-lg-4">
                            <div class="card border shadow-sm rounded-3 h-100 p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <x-ui.badge variant="primary" soft>{{ $item->kraCategory->name ?? 'General' }}</x-ui.badge>
                                    <span class="fw-bold text-dark fs-12">Weight: {{ $item->weightage }}%</span>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">{{ $item->title }}</h6>
                                <p class="text-muted fs-12 mb-3">{{ $item->description ?: 'Targeted functional KPI objective.' }}</p>

                                <div class="bg-light p-2.5 rounded-2 d-flex justify-content-between align-items-center mt-auto">
                                    <div>
                                        <small class="text-muted d-block fs-10 text-uppercase">Target</small>
                                        <span class="fw-bold text-dark fs-13">{{ $item->target }} {{ $item->unit }}</span>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block fs-10 text-uppercase">Actual Achieved</small>
                                        <span class="fw-bold text-primary fs-13">{{ $item->actual !== null ? $item->actual . ' ' . $item->unit : 'Not Logged' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- TAB 6: TEAM REVIEWS -->
            @if($teamPlans->isNotEmpty())
            <div class="tab-pane fade {{ $activeTab === 'team' ? 'show active' : '' }}" id="tab-team" role="tabpanel">
                <div class="table-responsive">
                    <x-ui.table hoverable>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Designation & Dept</th>
                                <th>Total Goals</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($teamPlans as $tp)
                                <tr>
                                    <td class="fw-bold text-dark">{{ $tp->employee->full_name ?? 'Employee' }}</td>
                                    <td>{{ $tp->employee->designation->name ?? 'N/A' }} ({{ $tp->employee->department->name ?? 'General' }})</td>
                                    <td>{{ $tp->items->count() }} KPIs ({{ $tp->total_weightage }}%)</td>
                                    <td>
                                        <x-ui.badge variant="primary" soft>{{ ucfirst(str_replace('_', ' ', $tp->status)) }}</x-ui.badge>
                                    </td>
                                    <td class="fw-bold">{{ $tp->final_score !== null ? $tp->final_score . '%' : 'Pending' }}</td>
                                    <td class="text-end">
                                        <x-ui.button variant="primary" size="sm" href="{{ route('hrms.kra-kpi.show', $tp->id) }}" class="fw-bold">
                                            Review Scorecard
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>

<!-- MODAL 1: CREATE APPRAISAL CYCLE (ODOO FORM UI) -->
@if($isHrOrAdmin)
<x-ui.modal id="createCycleModal" title="Create New Appraisal Review Cycle" size="lg" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.cycle.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-8">
                <x-ui.odoo-form-ui type="input" label="Cycle Name" name="name" placeholder="e.g. Annual Performance Review FY 2026-27" :required="true" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="Cycle Code" name="code" placeholder="e.g. FY26-ANNUAL" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="select" label="Period Frequency" name="period_type" id="cyclePeriodType" :required="true">
                    <option value="annual" selected>Annual</option>
                    <option value="semi_annual">Semi-Annual</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="monthly">Monthly</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="Start Date" name="start_date" id="cycleStartDate" inputType="date" :value="date('Y-m-d')" :required="true" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="End Date" name="end_date" id="cycleEndDate" inputType="date" :value="date('Y-12-31')" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Goal Weight %" name="goal_weightage_percent" inputType="number" step="0.01" value="70.00" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Values Weight %" name="competency_weightage_percent" inputType="number" step="0.01" value="30.00" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Goal Setting Deadline" name="goal_setting_deadline" inputType="date" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Self-Review Deadline" name="self_review_deadline" inputType="date" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="select" label="Initial Status" name="status" :required="true">
                    <option value="goal_setting" selected>Goal Setting (Open for Employee Submissions)</option>
                    <option value="draft">Draft (Planning Phase)</option>
                    <option value="in_progress">In Progress (Active Tracking)</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" label="Cycle Guidelines" name="description" rows="2" placeholder="Write appraisal guidelines..." />
            </div>
        </div>
        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">Create Appraisal Cycle</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- MODAL 2: ASSIGN TEMPLATE TO EMPLOYEES (ODOO FORM UI) -->
<x-ui.modal id="assignTemplateModal" title="Assign KPI Goal Sheets / Templates" size="md" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.assign-template') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="select" label="Appraisal Cycle" name="appraisal_cycle_id" :required="true">
                    @foreach($cycles as $c)
                        <option value="{{ $c->id }}" {{ $activeCycle?->id == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="select" label="KPI Template Pack" name="kpi_template_id">
                    <option value="">-- Custom Blank Scorecard --</option>
                    @foreach($kpiTemplates as $tpl)
                        <option value="{{ $tpl->id }}">{{ $tpl->name }} ({{ $tpl->items->count() }} KPIs)</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="select" label="Assignment Scope" name="target_type" id="assignmentTargetType" :required="true">
                    <option value="department">By Department</option>
                    <option value="individual">Specific Individual Employee</option>
                    <option value="all">All Active Employees in Company</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-12" id="deptSelectWrap">
                <x-ui.odoo-form-ui type="select" label="Choose Department" name="department_id">
                    <option value="">Select Department...</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-12 d-none" id="empSelectWrap">
                <x-ui.odoo-form-ui type="select" label="Choose Employee" name="employee_id">
                    <option value="">Select Employee...</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id ?? ('#' . $emp->id) }})</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
        </div>
        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">Assign Goals</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- MODAL 3: CREATE KRA FOCUS CATEGORY (ODOO FORM UI) -->
<x-ui.modal id="createKraModal" title="Add KRA Strategic Focus Area" size="md" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.kra-category.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" label="KRA Name" name="name" placeholder="e.g. Financial & Growth" :required="true" />
            </div>
            <div class="col-12">
                <div class="odoo-form-group">
                    <label class="odoo-form-label" for="kraColorPicker">Theme Color</label>
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <input type="color" name="color" id="kraColorPicker" value="#3b82f6" class="kra-color-circle shadow-sm" />
                        <span class="text-muted fs-12">Click the color circle to select category color directly.</span>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" rows="2" placeholder="Description of this strategic area..." />
            </div>
        </div>
        <div class="mt-3 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">Save KRA</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- MODAL 4: CREATE KPI MASTER METRIC (ODOO FORM UI) -->
<x-ui.modal id="createKpiMasterModal" title="Add Standard KPI to Master Library" size="lg" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.kpi-master.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" label="KPI Metric Name" name="name" placeholder="e.g. SLA Resolution Rate" :required="true" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="select" label="KRA Category" name="kra_category_id" :required="true">
                    @foreach($kraCategories as $kra)
                        <option value="{{ $kra->id }}">{{ $kra->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="Unit of Measure" name="unit" :required="true">
                    <option value="percentage">Percentage (%)</option>
                    <option value="currency">Currency (₹ / $)</option>
                    <option value="number">Numeric Count</option>
                    <option value="rating">Rating (1 to 5)</option>
                    <option value="boolean">Milestone (Yes/No)</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="Direction" name="calculation_type" :required="true">
                    <option value="higher_is_better">Higher is Better (Sales, Uptime)</option>
                    <option value="lower_is_better">Lower is Better (Bugs, Churn)</option>
                    <option value="milestone">Milestone Target</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Default Target" name="default_target" inputType="number" step="0.01" value="100.00" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Default Weight %" name="default_weightage" inputType="number" step="0.01" value="20.00" :required="true" />
            </div>
        </div>
        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">Add to Library</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- MODAL 5: CREATE KPI TEMPLATE PACK (ODOO FORM UI) -->
<x-ui.modal id="createTemplateModal" title="Create Role-Based KPI Template Pack" size="xl" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.template.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" label="Template Name" name="name" placeholder="e.g. Senior Software Engineer Scorecard" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="Department" name="department_id">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="Designation" name="designation_id">
                    <option value="">All Designations</option>
                    @foreach($designations as $desig)
                        <option value="{{ $desig->id }}">{{ $desig->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <div class="mt-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <h6 class="fw-bold text-dark mb-0">KPI Goals in this Template (Weightage sum should be 100%)</h6>
                <x-ui.button type="button" variant="light" size="sm" icon="feather-plus" id="addTemplateRowBtn" class="border fw-semibold">
                    Add Goal Row
                </x-ui.button>
            </div>
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 28%;">KPI Title <span class="text-danger">*</span></th>
                        <th style="width: 22%;">KRA Focus</th>
                        <th style="width: 18%;">Direction</th>
                        <th style="width: 12%;">Unit</th>
                        <th style="width: 9%;">Target <span class="text-danger">*</span></th>
                        <th style="width: 9%;">Weight % <span class="text-danger">*</span></th>
                        <th style="width: 42px;" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="templateRowsTbody">
                    <tr>
                        <td>
                            <x-ui.odoo-form-ui type="input" name="items[0][title]" placeholder="e.g. Code Quality & Bug Escape" :required="true" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[0][kra_category_id]">
                                @foreach($kraCategories as $kra)
                                    <option value="{{ $kra->id }}">{{ $kra->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[0][calculation_type]">
                                <option value="higher_is_better">Higher is Better</option>
                                <option value="lower_is_better">Lower is Better</option>
                                <option value="milestone">Milestone</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[0][unit]">
                                <option value="percentage">%</option>
                                <option value="number">Qty</option>
                                <option value="currency">₹</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[0][target]" value="95" :required="true" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[0][weightage]" value="50" :required="true" />
                        </td>
                        <td class="text-center">
                            <x-ui.icon-btn variant="soft-danger" icon="feather-trash-2" size="sm" class="remove-template-row" title="Remove Goal" />
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <x-ui.odoo-form-ui type="input" name="items[1][title]" placeholder="e.g. Sprint Task Delivery" :required="true" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[1][kra_category_id]">
                                @foreach($kraCategories as $kra)
                                    <option value="{{ $kra->id }}">{{ $kra->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[1][calculation_type]">
                                <option value="higher_is_better">Higher is Better</option>
                                <option value="lower_is_better">Lower is Better</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[1][unit]">
                                <option value="percentage">%</option>
                                <option value="number">Qty</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[1][target]" value="90" :required="true" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[1][weightage]" value="50" :required="true" />
                        </td>
                        <td class="text-center">
                            <x-ui.icon-btn variant="soft-danger" icon="feather-trash-2" size="sm" class="remove-template-row" title="Remove Goal" />
                        </td>
                    </tr>
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">Save KPI Template</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- EDIT ROLE TEMPLATE MODALS -->
@foreach($kpiTemplates as $tpl)
<x-ui.modal id="editTemplateModal_{{ $tpl->id }}" title="Edit Role Template: {{ $tpl->name }}" size="xl" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.template.update', $tpl->id) }}">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" label="Template Name" name="name" value="{{ $tpl->name }}" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="Department" name="department_id">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" @selected($tpl->department_id == $dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="Designation" name="designation_id">
                    <option value="">All Designations</option>
                    @foreach($designations as $desig)
                        <option value="{{ $desig->id }}" @selected($tpl->designation_id == $desig->id)>{{ $desig->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <div class="mt-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <h6 class="fw-bold text-dark mb-0">KPI Goals in this Template (Weightage sum should be 100%)</h6>
                <x-ui.button type="button" variant="light" size="sm" icon="feather-plus" class="border fw-semibold" onclick="addEditTemplateRow({{ $tpl->id }})">
                    Add Goal Row
                </x-ui.button>
            </div>
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 28%;">KPI Title <span class="text-danger">*</span></th>
                        <th style="width: 22%;">KRA Focus</th>
                        <th style="width: 18%;">Direction</th>
                        <th style="width: 12%;">Unit</th>
                        <th style="width: 9%;">Target <span class="text-danger">*</span></th>
                        <th style="width: 9%;">Weight % <span class="text-danger">*</span></th>
                        <th style="width: 42px;" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="editTemplateRowsTbody_{{ $tpl->id }}">
                    @foreach($tpl->items as $idx => $item)
                        <tr>
                            <td>
                                <x-ui.odoo-form-ui type="input" name="items[{{ $idx }}][title]" value="{{ $item->title }}" :required="true" />
                            </td>
                            <td>
                                <x-ui.odoo-form-ui type="select" name="items[{{ $idx }}][kra_category_id]">
                                    @foreach($kraCategories as $kra)
                                        <option value="{{ $kra->id }}" @selected($item->kra_category_id == $kra->id)>{{ $kra->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </td>
                            <td>
                                <x-ui.odoo-form-ui type="select" name="items[{{ $idx }}][calculation_type]">
                                    <option value="higher_is_better" @selected($item->calculation_type === 'higher_is_better')>Higher is Better</option>
                                    <option value="lower_is_better" @selected($item->calculation_type === 'lower_is_better')>Lower is Better</option>
                                    <option value="milestone" @selected($item->calculation_type === 'milestone')>Milestone</option>
                                </x-ui.odoo-form-ui>
                            </td>
                            <td>
                                <x-ui.odoo-form-ui type="select" name="items[{{ $idx }}][unit]">
                                    <option value="percentage" @selected($item->unit === 'percentage')>%</option>
                                    <option value="number" @selected($item->unit === 'number')>Qty</option>
                                    <option value="currency" @selected($item->unit === 'currency')>₹</option>
                                </x-ui.odoo-form-ui>
                            </td>
                            <td>
                                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[{{ $idx }}][target]" value="{{ $item->target }}" :required="true" />
                            </td>
                            <td>
                                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[{{ $idx }}][weightage]" value="{{ $item->weightage }}" :required="true" />
                            </td>
                            <td class="text-center">
                                <x-ui.icon-btn variant="soft-danger" icon="feather-trash-2" size="sm" class="remove-template-row" title="Remove Goal" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">Update KPI Template</x-ui.button>
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

        // Auto-calculate Appraisal Cycle End Date based on Period Frequency and Start Date
        function calculateCycleEndDate() {
            const modal = document.getElementById('createCycleModal');
            if (!modal) return;

            const periodSelect = modal.querySelector('[name="period_type"]');
            const startDateInput = modal.querySelector('[name="start_date"]');
            const endDateInput = modal.querySelector('[name="end_date"]');

            if (!periodSelect || !startDateInput || !endDateInput) return;
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
            endDateInput.value = `${y}-${m}-${d}`;
        }

        if (window.jQuery) {
            $(document).on('change change.select2 select2:select', '#createCycleModal [name="period_type"]', calculateCycleEndDate);
            $(document).on('input change', '#createCycleModal [name="start_date"]', calculateCycleEndDate);
            $('#createCycleModal').on('shown.bs.modal', calculateCycleEndDate);
        } else {
            document.addEventListener('change', function(e) {
                if (e.target && e.target.matches('#createCycleModal [name="period_type"], #createCycleModal [name="start_date"]')) {
                    calculateCycleEndDate();
                }
            });
            document.addEventListener('input', function(e) {
                if (e.target && e.target.matches('#createCycleModal [name="start_date"]')) {
                    calculateCycleEndDate();
                }
            });
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
                // all active employees
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

        // Dynamic Add/Remove Row for KPI Template Builder
        let templateRowIndex = 2;
        const kraOptionsHtml = `{!! addslashes(collect($kraCategories)->map(fn($k) => "<option value='{$k->id}'>{$k->name}</option>")->implode('')) !!}`;

        document.getElementById('addTemplateRowBtn')?.addEventListener('click', function() {
            const tbody = document.getElementById('templateRowsTbody');
            if (!tbody) return;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <input type="text" name="items[${templateRowIndex}][title]" class="odoo-table-input" placeholder="e.g. Operational Goal" required />
                </td>
                <td>
                    <select name="items[${templateRowIndex}][kra_category_id]" class="odoo-table-select">
                        ${kraOptionsHtml}
                    </select>
                </td>
                <td>
                    <select name="items[${templateRowIndex}][calculation_type]" class="odoo-table-select">
                        <option value="higher_is_better">Higher is Better</option>
                        <option value="lower_is_better">Lower is Better</option>
                        <option value="milestone">Milestone</option>
                    </select>
                </td>
                <td>
                    <select name="items[${templateRowIndex}][unit]" class="odoo-table-select">
                        <option value="percentage">%</option>
                        <option value="number">Qty</option>
                        <option value="currency">₹</option>
                    </select>
                </td>
                <td>
                    <input type="number" step="0.01" name="items[${templateRowIndex}][target]" value="100" class="odoo-table-input" required />
                </td>
                <td>
                    <input type="number" step="0.01" name="items[${templateRowIndex}][weightage]" value="25" class="odoo-table-input" required />
                </td>
                <td class="text-center">
                    <button type="button" class="btn erp-icon-btn erp-icon-btn--danger btn-sm remove-template-row" title="Remove Goal">
                        <i class="feather-trash-2"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            templateRowIndex++;
        });

        // Dynamic Add Row for Edit Template Modals
        window.addEditTemplateRow = function(tplId) {
            const tbody = document.getElementById('editTemplateRowsTbody_' + tplId);
            if (!tbody) return;
            const rowIndex = tbody.querySelectorAll('tr').length;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <input type="text" name="items[${rowIndex}][title]" class="odoo-table-input" placeholder="e.g. Goal Title" required />
                </td>
                <td>
                    <select name="items[${rowIndex}][kra_category_id]" class="odoo-table-select">
                        ${kraOptionsHtml}
                    </select>
                </td>
                <td>
                    <select name="items[${rowIndex}][calculation_type]" class="odoo-table-select">
                        <option value="higher_is_better">Higher is Better</option>
                        <option value="lower_is_better">Lower is Better</option>
                        <option value="milestone">Milestone</option>
                    </select>
                </td>
                <td>
                    <select name="items[${rowIndex}][unit]" class="odoo-table-select">
                        <option value="percentage">%</option>
                        <option value="number">Qty</option>
                        <option value="currency">₹</option>
                    </select>
                </td>
                <td>
                    <input type="number" step="0.01" name="items[${rowIndex}][target]" value="100" class="odoo-table-input" required />
                </td>
                <td>
                    <input type="number" step="0.01" name="items[${rowIndex}][weightage]" value="20" class="odoo-table-input" required />
                </td>
                <td class="text-center">
                    <button type="button" class="btn erp-icon-btn erp-icon-btn--danger btn-sm remove-template-row" title="Remove Goal">
                        <i class="feather-trash-2"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        };

        // Delegated Row Removal Listener
        document.addEventListener('click', function(e) {
            if (e.target && e.target.closest('.remove-template-row')) {
                const btn = e.target.closest('.remove-template-row');
                const tr = btn.closest('tr');
                const tbody = tr?.closest('tbody');
                if (tbody && tbody.querySelectorAll('tr').length > 1) {
                    tr.remove();
                } else {
                    alert('At least one KPI goal row is required.');
                }
            }
        });

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
    });
</script>
@endpush

<x-ui.confirmation-modal />
@endsection
