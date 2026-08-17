@extends('layouts.duralux')

@section('title', 'Interactive Production Dispatch Board | SaaS ERP')

@section('page-back-button')
    <x-ui.icon-btn href="{{ route('production.schedules.index') }}" icon="feather-arrow-left" variant="transparent-dark"
        title="Back to Schedules" />
@endsection

@section('content')
    {{-- Workflow Guide Component --}}
    <x-ui.workflow-guide title="Interactive Dispatch Board & Planner Workspace">
        Drag-and-drop schedule operations horizontally to adjust timing or vertically across machine swimlanes. The Laravel
        backend enforces routing dependencies, overlap rules, machine qualifications, downtime collisions, optimistic
        concurrency, and capacity leveling optimization.
    </x-ui.workflow-guide>

    <div class="erp-single-panel">
        <x-ui.odoo-form-ui type="sheet">

            @if(isset($activeScenario) || request('scenario_id'))
                @php
                    $scen = $activeScenario ?? \App\Domains\Production\Models\ProductionScheduleScenario::withoutGlobalScopes()->find(request('scenario_id'));
                    $isPromoted = $scen && $scen->isPromoted();
                    $isDiscarded = $scen && $scen->isDiscarded();
                @endphp
                <div
                    class="alert {{ $isPromoted ? 'alert-success border-success' : ($isDiscarded ? 'alert-secondary border-secondary' : 'alert-warning border-warning') }} d-flex align-items-center justify-content-between mb-4 shadow-sm py-2 px-3">
                    <div class="d-flex align-items-center gap-2">
                        <i
                            class="feather-{{ $isPromoted ? 'check-circle text-success' : ($isDiscarded ? 'x-circle text-secondary' : 'alert-triangle text-warning') }} fs-5"></i>
                        <div>
                            <strong class="text-dark">WHAT-IF SCENARIO MODE (ID
                                #{{ $scen->id ?? request('scenario_id') }}):</strong>
                            @if($isPromoted)
                                <span class="badge bg-success ms-1">Promoted to Live</span>
                                <span class="text-muted small ms-2">This scenario was successfully promoted to the live
                                    schedule.</span>
                            @elseif($isDiscarded)
                                <span class="badge bg-secondary ms-1">Discarded</span>
                                <span class="text-muted small ms-2">This scenario was discarded and is in read-only view.</span>
                            @else
                                <span class="badge bg-warning text-dark ms-1">Draft</span>
                                <span class="text-muted small ms-2">Experimental planning workspace. Changes here do not affect the
                                    live production schedule until promoted.</span>
                            @endif
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @if(!$isPromoted && !$isDiscarded)
                            <button type="button" class="btn btn-sm btn-success fw-bold"
                                onclick="promoteCurrentScenario({{ $scen->id ?? request('scenario_id') }}, '{{ addslashes($scen->name ?? 'Scenario #' . request('scenario_id')) }}')">
                                <i class="feather-check-circle me-1"></i> Promote to Live Board
                            </button>
                        @endif
                        <a href="{{ route('production.schedules.scenarios.index') }}" class="btn btn-sm btn-outline-dark">
                            <i class="feather-arrow-left me-1"></i> Exit Scenario Mode
                        </a>
                    </div>
                </div>
            @endif

            {{-- Active Schedule Focus Banner --}}
            @if(isset($activeSchedule) && $activeSchedule)
                <div class="alert alert-info border-info d-flex flex-wrap align-items-center justify-content-between p-3 mb-4 rounded shadow-sm">
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-calendar text-info fs-18"></i>
                        <div>
                            <strong class="text-dark">Focused Schedule:</strong>
                            <span class="font-monospace fw-bold text-primary me-2">#{{ $activeSchedule->schedule_number }}</span>
                            <span class="text-muted">Order: <strong>{{ $activeSchedule->order->order_number ?? 'N/A' }}</strong> ({{ $activeSchedule->order->product->name ?? 'N/A' }})</span>
                            <span class="badge {{ $activeSchedule->isReleased() ? 'bg-success' : 'bg-primary' }} ms-2 text-capitalize">{{ $activeSchedule->status }}</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ route('production.schedules.dispatch-board') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="feather-x me-1"></i> Clear Schedule Filter
                        </a>
                    </div>
                </div>
            @endif

            {{-- Board Header & Filter Toolbar --}}
            <div class="mb-4 pb-3 border-bottom">
                {{-- Row 1: Title & Timeline Date Controls + Filter Component --}}
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                    <div>
                        <h4 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                            <i class="feather-grid text-primary"></i> Production Dispatch Board & Timeline
                            <span id="readOnlyBadge" class="badge bg-soft-secondary text-secondary fs-11 d-none">Read Only</span>
                        </h4>
                        <p class="text-muted fs-13 mb-0">Interactive Gantt Planner & Machine Capacity Dispatcher</p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        {{-- Date Navigation --}}
                        <div class="d-flex align-items-center gap-1 me-2">
                            <button type="button" id="btnNavPrev" class="btn btn-sm btn-outline-secondary px-2 py-1" title="Previous Period"><i class="feather-chevron-left"></i></button>
                            <button type="button" id="btnNavToday" class="btn btn-sm btn-outline-secondary px-3 py-1 fw-semibold">Today</button>
                            <button type="button" id="btnNavNext" class="btn btn-sm btn-outline-secondary px-2 py-1" title="Next Period"><i class="feather-chevron-right"></i></button>
                        </div>

                        {{-- Scale Toggle --}}
                        <div class="d-flex align-items-center gap-1 me-2">
                            <button type="button" id="btnScaleDay" class="btn btn-sm btn-outline-primary active px-3 py-1 fs-12 fw-semibold">Day View</button>
                            <button type="button" id="btnScaleWeek" class="btn btn-sm btn-outline-primary px-3 py-1 fs-12 fw-semibold">Week View</button>
                        </div>

                        {{-- Date Range Inputs --}}
                        <div class="d-flex align-items-center gap-1 me-2">
                            <div style="width: 140px;">
                                <x-ui.odoo-form-ui type="input" inputType="date" id="filterStartDate" name="start_date" :value="request('start_date', now()->format('Y-m-d'))" />
                            </div>
                            <span class="text-muted fs-12">to</span>
                            <div style="width: 140px;">
                                <x-ui.odoo-form-ui type="input" inputType="date" id="filterEndDate" name="end_date" :value="request('end_date', now()->addDays(14)->format('Y-m-d'))" />
                            </div>
                        </div>

                        {{-- Standard ERP Filter Component Dropdown --}}
                        <x-ui.filter label="Filter" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Dispatch Board Filters</h6>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Production Schedule</label>
                                <x-ui.odoo-form-ui type="select" id="filterSchedule" name="schedule_id">
                                    <option value="">All Schedules</option>
                                    @if(isset($schedulesList))
                                        @foreach($schedulesList as $sch)
                                            <option value="{{ $sch->id }}" data-status="{{ $sch->status }}" {{ (request('schedule_id') == $sch->id || (isset($activeSchedule) && $activeSchedule && $activeSchedule->id == $sch->id)) ? 'selected' : '' }}>
                                                #{{ $sch->schedule_number }} ({{ $sch->order->order_number ?? 'Order' }}) - {{ ucfirst($sch->status) }}
                                            </option>
                                        @endforeach
                                    @endif
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Work Center</label>
                                <x-ui.odoo-form-ui type="select" id="filterWorkCenter" name="work_center_id">
                                    <option value="">All Work Centers</option>
                                    @foreach($workCenters as $wc)
                                        <option value="{{ $wc->id }}" {{ request('work_center_id') == $wc->id ? 'selected' : '' }}>
                                            {{ $wc->name }} ({{ $wc->code }})
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Machine</label>
                                <x-ui.odoo-form-ui type="select" id="filterMachine" name="machine_id">
                                    <option value="">All Machines</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Operation Status</label>
                                <x-ui.odoo-form-ui type="select" id="filterStatus" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="released">Released</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="d-flex gap-2 pt-2 border-top">
                                <button type="button" id="btnApplyFilterInside" class="btn btn-sm btn-primary flex-grow-1">
                                    <i class="feather-filter me-1"></i> Apply Filters
                                </button>
                                <button type="button" id="btnResetDispatchFilters" class="btn btn-sm btn-outline-secondary">
                                    Reset
                                </button>
                            </div>
                        </x-ui.filter>
                    </div>
                </div>

                {{-- Row 2: Planner Action Bar (Starts cleanly on next line with Ripple Shift) --}}
                <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 pt-2 border-top border-light">
                    {{-- Adjustment Mode Selector --}}
                    <div class="dropdown">
                        <button
                            class="btn btn-sm btn-outline-dark dropdown-toggle d-inline-flex align-items-center gap-1.5 fs-12"
                            type="button" id="btnShiftModeToggle" data-bs-toggle="dropdown" aria-expanded="false"
                            title="Shift Mode determines how downstream operations react to schedule moves">
                            <i class="feather-git-commit text-primary"></i> <span id="currentShiftModeLabel">Ripple Shift</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end fs-12 shadow p-2" aria-labelledby="btnShiftModeToggle"
                            style="min-width: 300px; max-width: 360px;">
                            <li>
                                <a class="dropdown-item active rounded p-2 text-wrap" href="javascript:void(0)"
                                    id="optShiftRipple" onclick="setShiftMode('ripple')">
                                    <div class="fw-bold mb-1"><i class="feather-check me-1 text-success d-inline"
                                            id="iconRippleCheck"></i> Ripple Shift</div>
                                    <div class="text-muted fs-11 text-wrap lh-sm ms-3">Recalculates downstream dependent
                                        operations automatically.</div>
                                </a>
                            </li>
                            <li>
                                <hr class="dropdown-divider my-1">
                            </li>
                            <li>
                                <a class="dropdown-item rounded p-2 text-wrap" href="javascript:void(0)"
                                    id="optShiftIsolated" onclick="setShiftMode('isolated')">
                                    <div class="fw-bold mb-1"><i class="feather-check me-1 text-success d-none"
                                            id="iconIsolatedCheck"></i> Isolated Shift</div>
                                    <div class="text-muted fs-11 text-wrap lh-sm ms-3">Moves only target operation; rejects
                                        moves that break dependencies.</div>
                                </a>
                            </li>
                        </ul>
                    </div>

                    {{-- What-If Scenarios --}}
                    <a href="{{ route('production.schedules.scenarios.index') }}"
                        class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 fs-12">
                        <i class="feather-layers text-primary"></i> What-If Scenarios
                    </a>

                    {{-- Level Capacity --}}
                    <x-ui.button id="btnLevelCapacity" variant="outline-warning" size="sm" icon="feather-zap">
                        Level Capacity
                    </x-ui.button>

                    {{-- Pre-Release Check Button (Dynamically visible ONLY when schedule is ready to release) --}}
                    <x-ui.button id="btnPreReleaseCheck" variant="success" size="sm" icon="feather-shield-check" class="d-none">
                        Pre-Release Check
                    </x-ui.button>

                    {{-- Audit Log --}}
                    <x-ui.button id="btnChangeHistory" variant="outline-secondary" size="sm" icon="feather-clock">
                        Audit Log
                    </x-ui.button>

                    {{-- Refresh --}}
                    <button type="button" id="btnRefreshBoardData" class="btn btn-sm btn-outline-secondary px-2 py-1" title="Refresh Board Data">
                        <i class="feather-refresh-cw"></i>
                    </button>
                </div>
            </div>

            {{-- Summary Stat Cards --}}
            <div class="row g-3 mb-4">
                <div class="col-md-2">
                    <div class="p-3 bg-light rounded border text-center">
                        <span class="text-muted fs-11 uppercase fw-bold">Visible Operations</span>
                        <h4 id="statTotalOps" class="fw-bold text-dark mb-0 mt-1">—</h4>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="p-3 bg-light rounded border text-center">
                        <span class="text-muted fs-11 uppercase fw-bold">Active Swimlanes</span>
                        <h4 id="statTotalResources" class="fw-bold text-primary mb-0 mt-1">—</h4>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="p-3 bg-light rounded border text-center">
                        <span class="text-muted fs-11 uppercase fw-bold">Locked Ops</span>
                        <h4 id="statTotalLocked" class="fw-bold text-danger mb-0 mt-1">0</h4>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="p-3 bg-light rounded border text-center">
                        <span class="text-muted fs-11 uppercase fw-bold">Manual Adjustments</span>
                        <h4 id="statTotalManual" class="fw-bold text-warning mb-0 mt-1">0</h4>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="p-3 bg-light rounded border text-center">
                        <span class="text-muted fs-11 uppercase fw-bold">Active Conflicts</span>
                        <h4 id="statTotalConflicts" class="fw-bold text-danger mb-0 mt-1">0</h4>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="p-3 bg-light rounded border text-center">
                        <span class="text-muted fs-11 uppercase fw-bold">Work Center Overloads</span>
                        <h4 id="statTotalOverloads" class="fw-bold text-warning mb-0 mt-1">0</h4>
                    </div>
                </div>
            </div>

            {{-- Active Warnings Banner --}}
            <div id="dispatchWarningsContainer" class="d-none mb-4">
                <div class="alert alert-warning border-warning bg-soft-warning p-3 rounded mb-0">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-warning-dark mb-0"><i class="feather-alert-triangle me-1"></i> Planner
                            Warnings & Capacity Overloads</h6>
                        <span class="badge bg-warning text-dark font-monospace fs-11" id="dispatchWarningsCount">0
                            warnings</span>
                    </div>
                    <ul id="dispatchWarningsList" class="mb-0 ps-3 fs-13 text-dark"></ul>
                </div>
            </div>

            {{-- Main Gantt Swimlanes Container --}}
            <div class="gantt-board-wrapper border rounded bg-white overflow-hidden shadow-sm mb-4">
                <div class="d-flex gantt-header border-bottom bg-light fs-12 fw-bold text-dark sticky-top"
                    style="z-index: 10;">
                    <div class="gantt-resource-col border-end p-2 px-3 bg-light d-flex align-items-center justify-content-between"
                        style="width: 280px; min-width: 280px;">
                        <span>Work Center / Resource</span>
                        <span class="text-muted fs-11">Load %</span>
                    </div>
                    <div class="gantt-timeline-col flex-grow-1 overflow-hidden" id="ganttHeaderTimeline">
                        <div class="d-flex align-items-center h-100 text-center" id="ganttHeaderTicks">
                            {{-- Dynamic date ticks rendered here --}}
                        </div>
                    </div>
                </div>

                <div id="ganttSwimlanesBody" class="gantt-body position-relative" style="min-height: 350px;">
                    <div class="text-center py-5 text-muted fs-13">
                        <div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div> Loading
                        Dispatch Board timeline...
                    </div>
                </div>
            </div>

            {{-- Legend Bar --}}
            <div class="d-flex flex-wrap align-items-center justify-content-between p-3 bg-light rounded border fs-12">
                <div class="d-flex align-items-center gap-3">
                    <span class="fw-semibold text-dark"><i class="feather-info me-1"></i> Legend:</span>
                    <span class="d-flex align-items-center gap-1"><span class="d-inline-block rounded-circle bg-primary"
                            style="width: 10px; height: 10px;"></span> Scheduled</span>
                    <span class="d-flex align-items-center gap-1"><span class="d-inline-block rounded-circle bg-info"
                            style="width: 10px; height: 10px;"></span> Released</span>
                    <span class="d-flex align-items-center gap-1"><span class="d-inline-block rounded-circle bg-success"
                            style="width: 10px; height: 10px;"></span> In Progress</span>
                    <span class="d-flex align-items-center gap-1"><span class="d-inline-block rounded-circle bg-secondary"
                            style="width: 10px; height: 10px;"></span> Completed</span>
                    <span class="d-flex align-items-center gap-1"><i class="feather-lock text-danger"></i> Locked</span>
                    <span class="d-flex align-items-center gap-1"><i class="feather-edit-2 text-warning"></i> Manual
                        Override</span>
                    <span class="d-flex align-items-center gap-1"><span
                            class="badge bg-soft-warning text-dark border border-warning fs-10">+4h 30m</span> Baseline
                        Variance</span>
                    <span class="d-flex align-items-center gap-1"><span class="d-inline-block bg-danger opacity-25 rounded"
                            style="width: 12px; height: 12px;"></span> Machine Downtime</span>
                </div>
                <div class="text-muted fs-11">
                    Drag operation bars horizontally to reschedule timing, or vertically between machine lanes to reassign
                    equipment.
                </div>
            </div>

        </x-ui.odoo-form-ui>

        {{-- Capacity Leveling Scope Modal (Using x-ui.modal Component) --}}
        <x-ui.modal id="modalLevelCapacityScope"
            title="<i class='feather-zap text-warning me-2'></i>Capacity Leveling Scope Selection" :centered="true"
            :showFooter="false">
            <p class="text-muted fs-13 mb-3">Select the scope for automatic capacity leveling optimization. The optimizer
                evaluates candidate operations to resolve overloads without mutating live schedules until approved.</p>

            <div class="row g-3 mb-3 fs-13">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Start Date" id="levelScopeStartDate"
                        name="start_date" :value="request('start_date', now()->format('Y-m-d'))" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="End Date" id="levelScopeEndDate" name="end_date"
                        :value="request('end_date', now()->addDays(14)->format('Y-m-d'))" />
                </div>
            </div>

            <div class="row g-3 mb-3 fs-13">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Work Center Scope" id="levelScopeWorkCenter"
                        name="work_center_id">
                        <option value="">All Work Centers</option>
                        @foreach($workCenters as $wc)
                            <option value="{{ $wc->id }}">{{ $wc->name }} ({{ $wc->code }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Target Machine Scope" id="levelScopeMachine" name="machine_id">
                        <option value="">All Machines</option>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <div class="modal-footer px-0 pb-0 pt-3 border-top">
                <x-ui.button type="button" variant="secondary" size="sm" data-bs-dismiss="modal">Cancel</x-ui.button>
                <x-ui.button type="button" id="btnGenerateLevelingPreview" variant="warning" size="sm" icon="feather-play">
                    Generate Preview
                </x-ui.button>
            </div>
        </x-ui.modal>

        {{-- Capacity Leveling Preview Modal (Using x-ui.modal & x-ui.odoo-form-ui Sheet/Table) --}}
        <x-ui.modal id="modalCapacityLevelingPreview"
            title="<i class='feather-sliders text-warning me-2'></i>Capacity Leveling Optimization Preview" size="xl"
            :centered="true" :showFooter="false">
            <div id="levelingPreviewContainer">
                {{-- Dynamically rendered via JS --}}
            </div>
            <div class="modal-footer px-0 pb-0 pt-3 border-top d-flex justify-content-between align-items-center">
                <span class="text-muted fs-11"><i class="feather-info me-1"></i> Preview expires in 30 minutes. Schedule
                    remains unchanged until applied.</span>
                <div>
                    <x-ui.button type="button" variant="secondary" size="sm" data-bs-dismiss="modal">Cancel</x-ui.button>
                    <x-ui.button type="button" id="btnApplyLeveling" variant="success" size="sm"
                        icon="feather-check-circle">
                        Apply Leveling Proposal
                    </x-ui.button>
                </div>
            </div>
        </x-ui.modal>

        {{-- Quick Edit Operation Modal (Using x-ui.modal & x-ui.odoo-form-ui Component) --}}
        <x-ui.modal id="modalQuickEditOp"
            title="<i class='feather-sliders text-primary me-2'></i>Operation Details & Rescheduling" size="lg"
            :centered="true" :showFooter="false">
            <input type="hidden" id="editOpId">
            <input type="hidden" id="editOpVersion">

            <div class="row g-3 mb-3 fs-13">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded border">
                        <span class="text-muted fs-11 uppercase fw-bold">Production Order</span>
                        <div id="editOrderNumber" class="fw-bold text-dark fs-14 mt-1">—</div>
                        <div id="editProductName" class="text-muted fs-12">—</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded border">
                        <span class="text-muted fs-11 uppercase fw-bold">Operation Info</span>
                        <div id="editOpName" class="fw-bold text-dark fs-14 mt-1">—</div>
                        <div id="editWorkCenterName" class="text-muted fs-12">—</div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3 fs-13">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="Planned Start Timestamp" inputType="datetime-local"
                        id="editPlannedStart" name="planned_start" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Assigned Machine" id="editMachineId" name="machine_id">
                        <option value="">No Machine (Manual Operation)</option>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <div class="row g-3 mb-3 fs-13">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Shift Propagation Mode" id="editShiftMode" name="shift_mode">
                        <option value="ripple">Ripple Shift (Auto-shift successors)</option>
                        <option value="isolated">Isolated Shift (Move target operation only)</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Adjustment Reason" id="editReason" name="reason">
                        <option value="Planner Adjustment">Planner Manual Adjustment</option>
                        <option value="Machine Breakdown">Machine Breakdown / Unscheduled Maintenance</option>
                        <option value="Rush Order">Rush Order / High Priority Shift</option>
                        <option value="Material Delay">Material Shortage / Delivery Lag</option>
                        <option value="Operator Absence">Operator Staffing Absence</option>
                        <option value="Tooling Issue">Tooling / Fixture Calibration</option>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <div class="p-3 bg-light rounded border mb-3 fs-13">
                <h6 class="fw-bold text-dark mb-2 fs-12 uppercase"><i class="feather-clock me-1 text-primary"></i> Baseline
                    Schedule Variance</h6>
                <div class="row text-center fs-12">
                    <div class="col-md-4">
                        <span class="text-muted d-block">Baseline Start</span>
                        <strong id="editBaselineStart">—</strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted d-block">Baseline Finish</span>
                        <strong id="editBaselineFinish">—</strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted d-block">Start Variance</span>
                        <span id="editVarianceBadge" class="badge bg-soft-secondary text-secondary">—</span>
                    </div>
                </div>
            </div>

            <div
                class="d-flex align-items-center justify-content-between p-3 bg-soft-warning rounded border border-warning mb-3 fs-13">
                <div>
                    <strong id="editLockStatusText" class="text-dark">Lock Status: Unlocked</strong>
                    <div class="text-muted fs-11">Locked operations cannot be moved via drag-and-drop or automatic capacity
                        leveling.</div>
                </div>
                <x-ui.button type="button" id="btnToggleLockInModal" variant="outline-danger" size="sm" icon="feather-lock">
                    Toggle Lock
                </x-ui.button>
            </div>

            <div class="modal-footer px-0 pb-0 pt-3 border-top">
                <x-ui.button type="button" variant="secondary" size="sm" data-bs-dismiss="modal">Cancel</x-ui.button>
                <x-ui.button type="button" id="btnSaveQuickEdit" variant="primary" size="sm" icon="feather-check">
                    Apply Reschedule
                </x-ui.button>
            </div>
        </x-ui.modal>

        {{-- Adjustment Drag Drop Confirmation Popover Modal (Using x-ui.modal Component) --}}
        <x-ui.modal id="modalConfirmDrag" title="<i class='feather-move me-2 text-primary'></i>Confirm Schedule Adjustment"
            :centered="true" :static="true" :showFooter="false">
            <p class="mb-3 text-dark fs-13">Confirm the proposed schedule move for operation <strong id="dragOpTitle">#10
                    Operation</strong>:</p>

            <div class="p-3 bg-light rounded border mb-3 fs-12">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Proposed Start:</span>
                    <strong id="dragNewStartDisplay" class="text-primary font-monospace">—</strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Target Machine:</span>
                    <strong id="dragNewMachineDisplay" class="text-dark">—</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Shift Mode:</span>
                    <span id="dragShiftModeBadge" class="badge bg-soft-info text-info">Ripple Shift</span>
                </div>
            </div>

            <div class="mb-3 fs-13">
                <x-ui.odoo-form-ui type="select" label="Adjustment Reason" id="dragReasonSelect" name="drag_reason">
                    <option value="Planner Adjustment">Planner Manual Adjustment</option>
                    <option value="Machine Breakdown">Machine Breakdown / Maintenance</option>
                    <option value="Rush Order">Rush Order Priority Shift</option>
                    <option value="Material Delay">Material Supply Lag</option>
                    <option value="Operator Absence">Operator Staffing Absence</option>
                    <option value="Tooling Issue">Tooling / Fixture Issue</option>
                </x-ui.odoo-form-ui>
            </div>

            <div class="modal-footer px-0 pb-0 pt-3 border-top">
                <x-ui.button type="button" id="btnCancelDrag" variant="secondary" size="sm">Cancel</x-ui.button>
                <x-ui.button type="button" id="btnApplyDrag" variant="primary" size="sm" icon="feather-check">
                    Confirm & Save Move
                </x-ui.button>
            </div>
        </x-ui.modal>

        {{-- Pre-Release Check Modal (Using x-ui.modal Component) --}}
        <x-ui.modal id="modalPreReleaseCheck"
            title="<i class='feather-shield-check text-info me-2'></i>Schedule Pre-Release Validation" size="lg"
            :centered="true" :showFooter="false">
            <div id="preReleaseModalBody" class="fs-13">
                <div class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm me-2 text-info"></div> Running completeness, machine
                    readiness, downtime collision, dependency, and material availability checks...
                </div>
            </div>
            <div class="modal-footer px-0 pb-0 pt-3 border-top" id="preReleaseModalFooter">
                <x-ui.button type="button" variant="secondary" size="sm" data-bs-dismiss="modal">Close</x-ui.button>
            </div>
        </x-ui.modal>

        {{-- Audit Log Change History Drawer / Modal (Using x-ui.modal Component) --}}
        <x-ui.modal id="modalChangeHistory"
            title="<i class='feather-clock text-secondary me-2'></i>Schedule Change Audit Logs" size="xl" :centered="true"
            :showFooter="false">
            <div id="changeHistoryModalBody" class="fs-13">
                <div class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm me-2"></div> Loading change history audit logs...
                </div>
            </div>
            <div class="modal-footer px-0 pb-0 pt-3 border-top">
                <x-ui.button type="button" variant="secondary" size="sm" data-bs-dismiss="modal">Close</x-ui.button>
            </div>
        </x-ui.modal>

        {{-- ERP Common Confirmation Modal Component --}}
        <x-ui.confirmation-modal id="dispatchBoardConfirmModal" title="Schedule Validation Alert" confirmText="OK"
            cancelText="Close" variant="danger" />
    </div>

    {{-- Inline Custom Dispatch Board JavaScript --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Global State Variables
            let currentShiftMode = 'ripple';
            let currentScale = 'day'; // 'day' or 'week'
            let dispatchBoardData = null;
            let dragContext = null;
            let quickEditOp = null;
            let activeOptimizationRunId = null;

            // DOM References
            const filterStartDate = document.getElementById('filterStartDate');
            const filterEndDate = document.getElementById('filterEndDate');
            const filterSchedule = document.getElementById('filterSchedule');
            const filterWorkCenter = document.getElementById('filterWorkCenter');
            const filterMachine = document.getElementById('filterMachine');
            const filterStatus = document.getElementById('filterStatus');
            const btnFetch = document.getElementById('btnFetchDispatchData');

            // Styled ERP Confirmation Modal / Alert Helper Function
            function showPlannerAlert(title, message, variant = 'danger', callback = null) {
                if (typeof window.confirmAction === 'function') {
                    window.confirmAction({
                        title: title,
                        message: message,
                        variant: variant,
                        confirmButtonText: 'OK',
                        cancelButtonText: '',
                        onConfirm: function () {
                            if (typeof callback === 'function') callback();
                        }
                    });
                } else if (window.Swal) {
                    Swal.fire({
                        icon: variant === 'danger' ? 'error' : (variant === 'warning' ? 'warning' : 'info'),
                        title: title,
                        text: message,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        if (typeof callback === 'function') callback();
                    });
                } else {
                    if (window.showAppToast) {
                        showAppToast(variant === 'danger' ? 'error' : variant, `${title}: ${message}`);
                    } else if (window.confirmAction) {
                        confirmAction({ title: title, message: message, variant: variant });
                    }
                    if (typeof callback === 'function') callback();
                }
            }

            // Level Capacity Button Click -> Open Scope Modal
            document.getElementById('btnLevelCapacity').addEventListener('click', function () {
                const scopeStartInput = document.querySelector('#levelScopeStartDate input') || document.getElementById('levelScopeStartDate');
                const scopeEndInput = document.querySelector('#levelScopeEndDate input') || document.getElementById('levelScopeEndDate');
                scopeStartInput.value = filterStartDate.value;
                scopeEndInput.value = filterEndDate.value;

                const scopeWcSelect = document.querySelector('#levelScopeWorkCenter select') || document.getElementById('levelScopeWorkCenter');
                scopeWcSelect.value = filterWorkCenter.value;

                const scopeMachineSelect = document.querySelector('#levelScopeMachine select') || document.getElementById('levelScopeMachine');
                scopeMachineSelect.value = filterMachine.value;

                const modal = new bootstrap.Modal(document.getElementById('modalLevelCapacityScope'));
                modal.show();
            });

            // Generate Preview Handler
            document.getElementById('btnGenerateLevelingPreview').addEventListener('click', function () {
                const scopeStartInput = document.querySelector('#levelScopeStartDate input') || document.getElementById('levelScopeStartDate');
                const scopeEndInput = document.querySelector('#levelScopeEndDate input') || document.getElementById('levelScopeEndDate');
                const scopeWcSelect = document.querySelector('#levelScopeWorkCenter select') || document.getElementById('levelScopeWorkCenter');
                const scopeMachineSelect = document.querySelector('#levelScopeMachine select') || document.getElementById('levelScopeMachine');

                const payload = {
                    start_date: scopeStartInput.value,
                    end_date: scopeEndInput.value,
                    work_center_id: scopeWcSelect.value || null,
                    machine_id: scopeMachineSelect.value || null,
                };

                bootstrap.Modal.getInstance(document.getElementById('modalLevelCapacityScope')).hide();

                const previewModalContainer = document.getElementById('levelingPreviewContainer');
                previewModalContainer.innerHTML = `<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2 text-warning"></div> Analyzing production capacity overloads and calculating non-overlapping feasible slots...</div>`;

                const modalPreview = new bootstrap.Modal(document.getElementById('modalCapacityLevelingPreview'));
                modalPreview.show();

                fetch(`{{ route('production.schedules.capacity-leveling.preview') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                })
                    .then(r => r.json().then(data => ({ status: r.status, body: data })))
                    .then(res => {
                        if (res.status !== 200) {
                            modalPreview.hide();
                            showPlannerAlert('Preview Generation Failed', res.body.message || 'Validation error', 'danger');
                            return;
                        }

                        const data = res.body;
                        activeOptimizationRunId = data.run_id;

                        const summary = data.summary || {};
                        const proposedChanges = data.proposed_changes || [];
                        const capacityBefore = data.capacity_before || [];
                        const capacityAfter = data.capacity_after || [];

                        let changesTableHtml = '';
                        if (proposedChanges.length > 0) {
                            changesTableHtml = `
                                    <x-ui.odoo-form-ui type="table">
                                        <thead class="table-light fs-12">
                                            <tr>
                                                <th>Order / Op</th>
                                                <th>Equipment Shift</th>
                                                <th>Proposed Timings</th>
                                                <th>Baseline Variance</th>
                                                <th>Due Date Impact</th>
                                                <th>Reason</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${proposedChanges.map(ch => `
                                                <tr>
                                                    <td class="fs-12">
                                                        <strong>${ch.production_order_number}</strong>
                                                        <br><small class="text-muted">#${ch.sequence} ${ch.operation_name}</small>
                                                    </td>
                                                    <td class="fs-12">
                                                        ${ch.old_machine_name}
                                                        <i class="feather-arrow-right mx-1 text-primary"></i>
                                                        <strong class="text-success">${ch.new_machine_name}</strong>
                                                    </td>
                                                    <td class="fs-11 font-monospace">
                                                        <div>Start: ${ch.new_start.slice(0, 16).replace('T', ' ')}</div>
                                                        <div>Finish: ${ch.new_finish.slice(0, 16).replace('T', ' ')}</div>
                                                    </td>
                                                    <td class="fs-12 font-monospace">
                                                        ${ch.variance_after_minutes !== 0 ? `<span class="badge bg-warning text-dark">+${ch.variance_after_minutes}m</span>` : '<span class="badge bg-light text-muted">0m</span>'}
                                                    </td>
                                                    <td class="fs-12">
                                                        ${ch.lateness_after_minutes > 0 ? `<span class="badge bg-danger">Late (+${ch.lateness_after_minutes}m)</span>` : '<span class="badge bg-success">On-Time</span>'}
                                                    </td>
                                                    <td class="fs-11 text-muted">${ch.reason || 'Capacity Leveling'}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </x-ui.odoo-form-ui>
                                `;
                        } else {
                            changesTableHtml = `<div class="text-center py-4 text-muted fs-13">No operation movements required. Capacity load is within limits.</div>`;
                        }

                        previewModalContainer.innerHTML = `
                                <div class="row g-3 mb-3 text-center">
                                    <div class="col-md-3">
                                        <div class="p-3 bg-light rounded border">
                                            <span class="text-muted fs-11 uppercase fw-bold">Overloads Before</span>
                                            <h4 class="fw-bold text-danger mb-0 mt-1">${summary.overloads_before ?? 0}</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="p-3 bg-light rounded border">
                                            <span class="text-muted fs-11 uppercase fw-bold">Overloads After</span>
                                            <h4 class="fw-bold text-success mb-0 mt-1">${summary.overloads_after ?? 0}</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="p-3 bg-light rounded border">
                                            <span class="text-muted fs-11 uppercase fw-bold">Operations Changed</span>
                                            <h4 class="fw-bold text-primary mb-0 mt-1">${summary.operations_changed ?? 0}</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="p-3 bg-light rounded border">
                                            <span class="text-muted fs-11 uppercase fw-bold">Machines Reassigned</span>
                                            <h4 class="fw-bold text-info mb-0 mt-1">${summary.machines_reassigned ?? 0}</h4>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="fw-bold text-dark mb-2 fs-13"><i class="feather-list text-primary me-1"></i> Proposed Operation Changes (${proposedChanges.length})</h6>
                                ${changesTableHtml}
                            `;
                    })
                    .catch(err => {
                        modalPreview.hide();
                        showPlannerAlert('Preview Error', err.message, 'danger');
                    });
            });

            // Apply Leveling Button Click
            document.getElementById('btnApplyLeveling').addEventListener('click', function () {
                if (!activeOptimizationRunId) {
                    showPlannerAlert('Apply Leveling', 'No active optimization preview session found.', 'warning');
                    return;
                }

                const btn = this;
                btn.disabled = true;

                fetch(`{{ route('production.schedules.capacity-leveling.apply') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ run_id: activeOptimizationRunId })
                })
                    .then(r => r.json().then(data => ({ status: r.status, body: data })))
                    .then(res => {
                        btn.disabled = false;
                        bootstrap.Modal.getInstance(document.getElementById('modalCapacityLevelingPreview')).hide();

                        if (res.status === 200 && res.body.success) {
                            showToast('success', res.body.message || 'Capacity leveling applied successfully.');
                            loadDispatchData();
                        } else if (res.status === 409) {
                            showPlannerAlert('Optimization Preview Stale', 'The schedule changed after this preview was generated. Generate a new preview using the latest schedule.', 'warning', function () {
                                loadDispatchData();
                            });
                        } else {
                            showPlannerAlert('Apply Failed', res.body.message || 'Validation error', 'danger');
                        }
                    })
                    .catch(err => {
                        btn.disabled = false;
                        bootstrap.Modal.getInstance(document.getElementById('modalCapacityLevelingPreview')).hide();
                        showPlannerAlert('Apply Error', err.message, 'danger');
                    });
            });

            // Shift Mode Handler
            window.setShiftMode = function (mode) {
                currentShiftMode = mode;
                document.getElementById('currentShiftModeLabel').textContent = mode === 'ripple' ? 'Ripple Shift' : 'Isolated Shift';
                document.getElementById('iconRippleCheck').className = mode === 'ripple' ? 'feather-check me-1 text-success d-inline' : 'feather-check me-1 text-success d-none';
                document.getElementById('iconIsolatedCheck').className = mode === 'isolated' ? 'feather-check me-1 text-success d-inline' : 'feather-check me-1 text-success d-none';
                document.getElementById('optShiftRipple').classList.toggle('active', mode === 'ripple');
                document.getElementById('optShiftIsolated').classList.toggle('active', mode === 'isolated');
            };

            // View Scale Handlers
            document.getElementById('btnScaleDay').addEventListener('click', function () {
                currentScale = 'day';
                this.classList.add('active');
                document.getElementById('btnScaleWeek').classList.remove('active');
                const start = new Date(filterStartDate.value);
                const end = new Date(start);
                end.setDate(end.getDate() + 13);
                filterEndDate.value = formatDate(end);
                loadDispatchData();
            });

            document.getElementById('btnScaleWeek').addEventListener('click', function () {
                currentScale = 'week';
                this.classList.add('active');
                document.getElementById('btnScaleDay').classList.remove('active');
                const start = new Date(filterStartDate.value);
                const end = new Date(start);
                end.setDate(end.getDate() + 27);
                filterEndDate.value = formatDate(end);
                loadDispatchData();
            });

            // Date Navigation Buttons
            document.getElementById('btnNavToday').addEventListener('click', function () {
                const today = new Date();
                filterStartDate.value = formatDate(today);
                const end = new Date(today);
                end.setDate(end.getDate() + 14);
                filterEndDate.value = formatDate(end);
                loadDispatchData();
            });

            document.getElementById('btnNavPrev').addEventListener('click', function () {
                const s = new Date(filterStartDate.value);
                const e = new Date(filterEndDate.value);
                const days = currentScale === 'day' ? 1 : 7;
                s.setDate(s.getDate() - days);
                e.setDate(e.getDate() - days);
                filterStartDate.value = formatDate(s);
                filterEndDate.value = formatDate(e);
                loadDispatchData();
            });

            document.getElementById('btnNavNext').addEventListener('click', function () {
                const s = new Date(filterStartDate.value);
                const e = new Date(filterEndDate.value);
                const days = currentScale === 'day' ? 1 : 7;
                s.setDate(s.getDate() + days);
                e.setDate(e.getDate() + days);
                filterStartDate.value = formatDate(s);
                filterEndDate.value = formatDate(e);
                loadDispatchData();
            });

            // Work Center Filter Change -> Populate Machine Filter Dropdown
            filterWorkCenter.addEventListener('change', function () {
                const wcId = this.value;
                filterMachine.innerHTML = '<option value="">All Machines</option>';
                if (dispatchBoardData && dispatchBoardData.resources) {
                    dispatchBoardData.resources.forEach(res => {
                        if (!wcId || res.id == wcId) {
                            res.machines.forEach(m => {
                                const opt = document.createElement('option');
                                opt.value = m.id;
                                opt.textContent = `${m.name} (${m.code})`;
                                filterMachine.appendChild(opt);
                            });
                        }
                    });
                }
                loadDispatchData();
            });

            if (filterSchedule) {
                filterSchedule.addEventListener('change', loadDispatchData);
            }
            filterMachine.addEventListener('change', loadDispatchData);
            filterStatus.addEventListener('change', loadDispatchData);
            if (btnFetch) {
                btnFetch.addEventListener('click', loadDispatchData);
            }

            const btnApplyFilterInside = document.getElementById('btnApplyFilterInside');
            if (btnApplyFilterInside) {
                btnApplyFilterInside.addEventListener('click', loadDispatchData);
            }

            const btnResetDispatchFilters = document.getElementById('btnResetDispatchFilters');
            if (btnResetDispatchFilters) {
                btnResetDispatchFilters.addEventListener('click', function () {
                    if (filterSchedule) filterSchedule.value = '';
                    if (filterWorkCenter) filterWorkCenter.value = '';
                    if (filterMachine) filterMachine.value = '';
                    if (filterStatus) filterStatus.value = '';
                    loadDispatchData();
                });
            }

            const btnRefreshBoardData = document.getElementById('btnRefreshBoardData');
            if (btnRefreshBoardData) {
                btnRefreshBoardData.addEventListener('click', loadDispatchData);
            }

            // Dynamic Button Visibility & State Updater
            function updateDynamicButtons(data) {
                const btnPreRelease = document.getElementById('btnPreReleaseCheck');
                const urlParams = new URLSearchParams(window.location.search);
                const scenarioId = urlParams.get('scenario_id');

                // In Scenario Mode, hide Pre-Release check (scenarios must be promoted first)
                if (scenarioId) {
                    if (btnPreRelease) btnPreRelease.classList.add('d-none');
                    return;
                }

                let isReleasable = false;

                // 1. If explicit schedule filter is selected
                if (filterSchedule && filterSchedule.value) {
                    const selectedOpt = filterSchedule.options[filterSchedule.selectedIndex];
                    const status = selectedOpt ? selectedOpt.getAttribute('data-status') : null;
                    if (status === 'draft' || status === 'scheduled') {
                        isReleasable = true;
                    }
                } else if (data && data.operations && data.operations.length > 0) {
                    // 2. Check operations loaded on board
                    isReleasable = data.operations.some(op => {
                        const st = op.schedule_status;
                        return st === 'draft' || st === 'scheduled';
                    });
                }

                if (btnPreRelease) {
                    if (isReleasable) {
                        btnPreRelease.classList.remove('d-none');
                    } else {
                        btnPreRelease.classList.add('d-none');
                    }
                }
            }

            // Fetch Main Board Data API
            function loadDispatchData() {
                const startDate = filterStartDate.value;
                const endDate = filterEndDate.value;
                const workCenterId = filterWorkCenter.value;
                const machineId = filterMachine.value;
                const status = filterStatus.value;
                const scheduleIdVal = filterSchedule ? filterSchedule.value : null;
                const urlParams = new URLSearchParams(window.location.search);
                const scenarioId = urlParams.get('scenario_id');
                const scheduleIdUrl = urlParams.get('schedule_id');

                const params = new URLSearchParams({
                    start_date: startDate,
                    end_date: endDate,
                });
                if (workCenterId) params.append('work_center_id', workCenterId);
                if (machineId) params.append('machine_id', machineId);
                if (status) params.append('status', status);
                if (scenarioId) params.append('scenario_id', scenarioId);
                if (scheduleIdVal || scheduleIdUrl) params.append('schedule_id', scheduleIdVal || scheduleIdUrl);

                const bodyEl = document.getElementById('ganttSwimlanesBody');
                bodyEl.innerHTML = `<div class="text-center py-5 text-muted fs-13"><div class="spinner-border spinner-border-sm me-2 text-primary"></div> Loading Dispatch Board timeline...</div>`;

                fetch(`{{ route('production.schedules.dispatch-board.data') }}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(r => {
                        if (!r.ok) throw new Error(`HTTP ${r.status}`);
                        return r.json();
                    })
                    .then(data => {
                        dispatchBoardData = data;

                        // Update summary stats
                        document.getElementById('statTotalOps').textContent = data.meta?.total_operations ?? 0;
                        document.getElementById('statTotalResources').textContent = data.meta?.total_resources ?? 0;

                        let lockedCount = 0;
                        let manualCount = 0;
                        (data.operations || []).forEach(op => {
                            if (op.locked) lockedCount++;
                            if (op.manual_override) manualCount++;
                        });
                        document.getElementById('statTotalLocked').textContent = lockedCount;
                        document.getElementById('statTotalManual').textContent = manualCount;

                        // Update warnings
                        const warningsContainer = document.getElementById('dispatchWarningsContainer');
                        const warningsList = document.getElementById('dispatchWarningsList');
                        warningsList.innerHTML = '';

                        let conflictCount = 0;
                        let overloadCount = 0;

                        if (data.warnings && data.warnings.length > 0) {
                            warningsContainer.classList.remove('d-none');
                            document.getElementById('dispatchWarningsCount').textContent = `${data.warnings.length} warning(s)`;
                            data.warnings.forEach(w => {
                                if (w.type === 'MACHINE_CONFLICT') conflictCount++;
                                if (w.type === 'CAPACITY_OVERLOAD') overloadCount++;

                                const li = document.createElement('li');
                                li.textContent = w.message;
                                warningsList.appendChild(li);
                            });
                        } else {
                            warningsContainer.classList.add('d-none');
                        }

                        document.getElementById('statTotalConflicts').textContent = conflictCount;
                        document.getElementById('statTotalOverloads').textContent = overloadCount;

                        // Render Machine Filter dropdown options if empty
                        if (filterMachine.options.length <= 1 && data.resources) {
                            data.resources.forEach(res => {
                                res.machines.forEach(m => {
                                    const opt = document.createElement('option');
                                    opt.value = m.id;
                                    opt.textContent = `${m.name} (${m.code})`;
                                    filterMachine.appendChild(opt);
                                });
                            });
                        }

                        renderTimelineAndOperations(data);
                        updateDynamicButtons(data);
                    })
                    .catch(err => {
                        document.getElementById('ganttSwimlanesBody').innerHTML = `
                                <div class="text-center py-5 text-danger fs-13">
                                    <i class="feather-alert-octagon me-1 fs-16"></i> Unable to load production schedule dispatch data: ${err.message}
                                    <div class="mt-2"><button onclick="loadDispatchData()" class="btn btn-sm btn-outline-danger">Retry</button></div>
                                </div>
                            `;
                    });
            }

            // Render Timeline Scale Header and Resource Swimlanes
            function renderTimelineAndOperations(data) {
                const bodyEl = document.getElementById('ganttSwimlanesBody');
                const headerTicksEl = document.getElementById('ganttHeaderTicks');

                bodyEl.innerHTML = '';
                headerTicksEl.innerHTML = '';

                if (!data.resources || data.resources.length === 0) {
                    bodyEl.innerHTML = `<div class="text-center py-5 text-muted fs-13">No scheduled operations found for the selected date range and filters.</div>`;
                    return;
                }

                // Timeline Date Calculations
                const startDate = new Date(data.range.start + 'T00:00:00');
                const endDate = new Date(data.range.end + 'T23:59:59');
                const startMs = startDate.getTime();
                const endMs = endDate.getTime();
                const totalMs = endMs - startMs;

                // Render Header Ticks
                const tickDays = Math.ceil(totalMs / (86400 * 1000));
                if (currentScale === 'week') {
                    const totalWeeks = Math.max(1, Math.ceil(tickDays / 7));
                    for (let w = 0; w < totalWeeks; w++) {
                        const wStart = new Date(startMs + w * 7 * 86400 * 1000);
                        const wEnd = new Date(startMs + Math.min(tickDays - 1, (w + 1) * 7 - 1) * 86400 * 1000);
                        const wNum = getWeekNumber(wStart);
                        const label = `W${wNum} (${wStart.getDate()}/${wStart.getMonth() + 1} - ${wEnd.getDate()}/${wEnd.getMonth() + 1})`;
                        const tickDiv = document.createElement('div');
                        tickDiv.className = 'gantt-header-tick border-end p-1 flex-grow-1 text-center text-truncate fs-11 fw-bold';
                        tickDiv.textContent = label;
                        headerTicksEl.appendChild(tickDiv);
                    }
                } else {
                    for (let d = 0; d < tickDays; d++) {
                        const tickDate = new Date(startMs + d * 86400 * 1000);
                        const dayLabel = tickDate.toLocaleDateString('en-US', { weekday: 'short', month: 'numeric', day: 'numeric' });
                        const tickDiv = document.createElement('div');
                        tickDiv.className = 'gantt-header-tick border-end p-1 flex-grow-1 text-center text-truncate fs-11';
                        tickDiv.textContent = dayLabel;
                        headerTicksEl.appendChild(tickDiv);
                    }
                }

                function getWeekNumber(d) {
                    const tempDate = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
                    tempDate.setUTCDate(tempDate.getUTCDate() + 4 - (tempDate.getUTCDay() || 7));
                    const yearStart = new Date(Date.UTC(tempDate.getUTCFullYear(), 0, 1));
                    return Math.ceil((((tempDate - yearStart) / 86400000) + 1) / 7);
                }

                // Render Swimlanes Body
                data.resources.forEach(res => {
                    // Work Center Header Row
                    const wcRow = document.createElement('div');
                    wcRow.className = 'gantt-row-wc d-flex align-items-center bg-light border-bottom fw-bold fs-12 py-1.5 px-2';

                    const wcUtil = res.capacity_utilization_percent || 0;
                    const wcUtilBadge = wcUtil > 100
                        ? `<span class="badge bg-danger text-white ms-2 font-monospace fs-10">${wcUtil}% ⚠</span>`
                        : `<span class="badge bg-soft-info text-info ms-2 font-monospace fs-10">${wcUtil}%</span>`;

                    wcRow.innerHTML = `
                                <div class="gantt-resource-col d-flex align-items-center justify-content-between pe-3" style="width: 280px; min-width: 280px;">
                                    <span><i class="feather-layers text-primary me-1"></i> ${res.name} (${res.code})</span>
                                    ${wcUtilBadge}
                                </div>
                                <div class="gantt-timeline-col flex-grow-1 text-muted fs-11 px-2">
                                    ${res.machines.length} machine lane(s)
                                </div>
                            `;
                    bodyEl.appendChild(wcRow);

                    // Machine Swimlane Rows
                    const machineLanes = res.machines.length > 0 ? res.machines : [{ id: null, name: 'Manual / Machine-less Operations', code: 'MANUAL', status: 'active', work_center_id: res.id }];

                    machineLanes.forEach(m => {
                        const laneRow = document.createElement('div');
                        laneRow.className = 'gantt-row-machine d-flex border-bottom position-relative';
                        laneRow.style.minHeight = '52px';
                        laneRow.dataset.workCenterId = res.id;
                        laneRow.dataset.machineId = m.id ?? '';

                        const mIcon = m.id ? 'feather-cpu text-secondary' : 'feather-user text-info';
                        const laneResourceCol = `
                                    <div class="gantt-resource-col border-end p-2 fs-12 d-flex align-items-center justify-content-between bg-white sticky-start" style="width: 280px; min-width: 280px; z-index: 5;">
                                        <div class="text-truncate" title="${m.name}">
                                            <i class="${mIcon} me-1"></i> <strong>${m.name}</strong>
                                            ${m.code ? `<span class="text-muted fs-11">(${m.code})</span>` : ''}
                                        </div>
                                        <span class="badge bg-soft-success text-success fs-10">${m.status}</span>
                                    </div>
                                `;

                        const laneTimelineCol = document.createElement('div');
                        laneTimelineCol.className = 'gantt-timeline-lane flex-grow-1 position-relative bg-white';
                        laneTimelineCol.style.minHeight = '52px';

                        // Swimlane Column Grid Lines Background
                        const stepPct = currentScale === 'week' ? (100 / Math.max(1, Math.ceil(tickDays / 7))) : (100 / Math.max(1, tickDays));
                        laneTimelineCol.style.backgroundImage = `linear-gradient(to right, rgba(0,0,0,0.06) 1px, transparent 1px)`;
                        laneTimelineCol.style.backgroundSize = `${stepPct}% 100%`;

                        // Render Machine Downtime Blockers
                        if (m.id && data.downtimes) {
                            const machineDowntimes = data.downtimes.filter(d => d.machine_id === m.id);
                            machineDowntimes.forEach(dt => {
                                const dtStart = new Date(dt.start_time).getTime();
                                const dtEnd = new Date(dt.end_time).getTime();
                                if (dtEnd > startMs && dtStart < endMs) {
                                    const leftPct = Math.max(0, ((dtStart - startMs) / totalMs) * 100);
                                    const widthPct = Math.min(100 - leftPct, ((dtEnd - Math.max(startMs, dtStart)) / totalMs) * 100);

                                    const dtBlock = document.createElement('div');
                                    dtBlock.className = 'gantt-downtime-block position-absolute bg-danger opacity-25 rounded h-75 my-auto top-0 bottom-0';
                                    dtBlock.style.left = `${leftPct}%`;
                                    dtBlock.style.width = `${widthPct}%`;
                                    dtBlock.title = `Downtime: ${dt.reason} (${dt.start_time} to ${dt.end_time})`;
                                    laneTimelineCol.appendChild(dtBlock);
                                }
                            });
                        }

                        // Render Operations in this lane
                        const machineOps = (data.operations || []).filter(op => {
                            if (m.id) return op.machine_id === m.id;
                            return op.work_center_id === res.id && !op.machine_id;
                        });

                        machineOps.forEach(op => {
                            const opStartMs = new Date(op.planned_start).getTime();
                            const opFinishMs = new Date(op.planned_finish).getTime();

                            const leftPct = Math.max(0, ((opStartMs - startMs) / totalMs) * 100);
                            const minWidthPct = currentScale === 'week' ? 3.5 : 1.8;
                            const widthPct = Math.max(minWidthPct, Math.min(100 - leftPct, ((opFinishMs - Math.max(startMs, opStartMs)) / totalMs) * 100));

                            const bar = document.createElement('div');
                            bar.className = `gantt-op-bar position-absolute rounded p-1.5 px-2 shadow-sm text-white fs-11 font-monospace d-flex align-items-center justify-content-between overflow-hidden ${getOpStatusClass(op.status)}`;
                            bar.style.left = `${leftPct}%`;
                            bar.style.width = `${widthPct}%`;
                            bar.style.top = '8px';
                            bar.style.height = '36px';
                            bar.style.cursor = op.locked ? 'not-allowed' : 'grab';
                            bar.dataset.opId = op.schedule_operation_id;
                            bar.dataset.version = op.version;
                            bar.dataset.locked = op.locked ? '1' : '0';

                            // Badges/Icons
                            const lockIcon = op.locked ? '<i class="feather-lock text-warning me-1" title="Locked Operation"></i>' : '';
                            const manualIcon = op.manual_override ? '<i class="feather-edit-2 text-info me-1" title="Manually Adjusted"></i>' : '';
                            const formattedVariance = formatVariance(op.start_variance_minutes);
                            const varianceBadge = formattedVariance
                                ? `<span class="badge bg-dark text-warning font-monospace fs-10 ms-1" title="Variance from Baseline: ${op.start_variance_minutes} mins">${formattedVariance}</span>`
                                : '';

                            bar.innerHTML = `
                                        <div class="text-truncate d-flex align-items-center gap-1 me-1">
                                            ${lockIcon}${manualIcon}
                                            <strong>#${op.sequence} ${op.operation_name}</strong>
                                            <span class="opacity-75">(${op.production_order_number})</span>
                                        </div>
                                        <div class="flex-shrink-0">${varianceBadge}</div>
                                    `;

                            // Hover Tooltip Payload matching Scheduling Calendar View
                            const tooltipContent = `
                                        <div class="text-start p-1" style="font-size: 11px; line-height: 1.45;">
                                            <div class="fw-bold border-bottom border-secondary pb-1 mb-1 text-white">#${op.sequence} ${escapeHtml(op.operation_name)}</div>
                                            <div><strong>Order:</strong> ${escapeHtml(op.production_order_number)}</div>
                                            <div><strong>Product:</strong> ${escapeHtml(op.product_name || 'N/A')}</div>
                                            <div><strong>Work Center:</strong> ${escapeHtml(res.name)}</div>
                                            ${m.name ? `<div><strong>Machine:</strong> ${escapeHtml(m.name)}</div>` : ''}
                                            <div><strong>Start:</strong> ${formatIsoDate(op.planned_start)}</div>
                                            <div><strong>Finish:</strong> ${formatIsoDate(op.planned_finish)}</div>
                                            ${op.baseline_start ? `<div><strong>Baseline Start:</strong> ${formatIsoDate(op.baseline_start)}</div>` : ''}
                                            ${op.start_variance_minutes ? `<div><strong>Variance:</strong> <span class="badge bg-warning text-dark">${formatVariance(op.start_variance_minutes)}</span></div>` : ''}
                                            <div class="mt-1 pt-1 border-top border-secondary d-flex align-items-center justify-content-between">
                                                <span><strong>Status:</strong> <span class="badge bg-light text-dark">${op.status.toUpperCase()}</span></span>
                                                ${op.locked ? '<span class="badge bg-danger text-white ms-1">LOCKED</span>' : ''}
                                            </div>
                                        </div>
                                    `;

                            bar.setAttribute('data-bs-toggle', 'tooltip');
                            bar.setAttribute('data-bs-html', 'true');
                            bar.setAttribute('data-bs-title', tooltipContent);
                            bar.setAttribute('title', tooltipContent);

                            if (window.bootstrap && bootstrap.Tooltip) {
                                new bootstrap.Tooltip(bar, {
                                    html: true,
                                    trigger: 'hover',
                                    placement: 'top',
                                    container: 'body'
                                });
                            }

                            // Click to open Quick Edit Modal
                            bar.addEventListener('click', function (e) {
                                e.stopPropagation();
                                openQuickEditModal(op);
                            });

                            // Drag & Drop Handling (HTML5 Drag & Drop)
                            if (!op.locked && op.status !== 'completed' && op.status !== 'in_progress') {
                                bar.draggable = true;

                                bar.addEventListener('dragstart', function (e) {
                                    dragContext = {
                                        op: op,
                                        element: bar,
                                        originalParent: bar.parentElement,
                                        originalLeft: bar.style.left,
                                        originalMachineId: m.id,
                                        originalWorkCenterId: res.id,
                                        startMs: startMs,
                                        totalMs: totalMs,
                                    };
                                    bar.classList.add('opacity-50');
                                    e.dataTransfer.setData('text/plain', op.schedule_operation_id);
                                });

                                bar.addEventListener('dragend', function () {
                                    bar.classList.remove('opacity-50');
                                });
                            }

                            laneTimelineCol.appendChild(bar);
                        });

                        // Drop Target Handlers for Lane
                        laneTimelineCol.addEventListener('dragover', function (e) {
                            e.preventDefault();
                            laneTimelineCol.classList.add('bg-soft-primary');
                        });

                        laneTimelineCol.addEventListener('dragleave', function () {
                            laneTimelineCol.classList.remove('bg-soft-primary');
                        });

                        laneTimelineCol.addEventListener('drop', function (e) {
                            e.preventDefault();
                            laneTimelineCol.classList.remove('bg-soft-primary');
                            if (!dragContext) return;

                            const rect = laneTimelineCol.getBoundingClientRect();
                            const clickX = e.clientX - rect.left;
                            const dropRatio = Math.max(0, Math.min(1, clickX / rect.width));

                            const newStartMs = startMs + dropRatio * totalMs;
                            // Snap to 15 minute interval
                            const snapMs = 15 * 60 * 1000;
                            const snappedMs = Math.round(newStartMs / snapMs) * snapMs;

                            const proposedStartDate = new Date(snappedMs);
                            const proposedStartIso = proposedStartDate.toISOString().slice(0, 19);

                            const targetMachineId = m.id;
                            const targetMachineName = m.id ? `${m.name} (${m.code})` : 'Manual Operation';

                            // Prompt Drag Confirmation Modal
                            promptDragConfirm(dragContext.op, proposedStartIso, targetMachineId, targetMachineName);
                        });

                        laneRow.innerHTML = laneResourceCol;
                        laneRow.appendChild(laneTimelineCol);
                        bodyEl.appendChild(laneRow);
                    });
                });
            }

            // Prompt Confirmation Modal for Drag & Drop Move
            function promptDragConfirm(op, proposedStartIso, targetMachineId, targetMachineName) {
                document.getElementById('dragOpTitle').textContent = `#${op.sequence} ${op.operation_name} (${op.production_order_number})`;
                document.getElementById('dragNewStartDisplay').textContent = proposedStartIso.replace('T', ' ');
                document.getElementById('dragNewMachineDisplay').textContent = targetMachineName;
                document.getElementById('dragShiftModeBadge').textContent = currentShiftMode === 'ripple' ? 'Ripple Shift' : 'Isolated Shift';

                const modalEl = document.getElementById('modalConfirmDrag');
                const modal = new bootstrap.Modal(modalEl);
                modal.show();

                const btnApply = document.getElementById('btnApplyDrag');
                const btnCancel = document.getElementById('btnCancelDrag');

                function cleanup() {
                    btnApply.onclick = null;
                    btnCancel.onclick = null;
                }

                btnCancel.onclick = function () {
                    modal.hide();
                    cleanup();
                    dragContext = null;
                    loadDispatchData(); // Rollback visual position
                };

                btnApply.onclick = function () {
                    modal.hide();
                    const reasonSelect = document.querySelector('#dragReasonSelect select') || document.getElementById('dragReasonSelect');
                    const reason = reasonSelect.value;

                    submitOpAdjustment(op.schedule_operation_id, proposedStartIso, targetMachineId, currentShiftMode, reason, op.version, cleanup);
                };
            }

            window.promoteCurrentScenario = function (scenarioId, scenarioName) {
                const name = scenarioName || `Scenario #${scenarioId}`;
                confirmAction({
                    title: 'Promote What-If Scenario',
                    message: `Are you sure you want to promote ${name} to the live production schedule? This action will apply scenario timing adjustments to live shop floor operations.`,
                    confirmButtonText: 'Promote to Live Board',
                    variant: 'success',
                    onConfirm: function () {
                        fetch(`{{ url('production/schedules/scenarios') }}/${scenarioId}/promote`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                            .then(r => r.json().then(data => ({ status: r.status, body: data })))
                            .then(res => {
                                if (res.status === 200 && res.body.success) {
                                    showToast('success', res.body.message || 'Scenario promoted to Live Schedule successfully!');
                                    window.location.href = `{{ route('production.schedules.dispatch-board') }}`;
                                } else {
                                    confirmAction({
                                        title: 'Promotion Error',
                                        message: res.body.message || 'Failed to promote scenario.',
                                        confirmButtonText: 'OK',
                                        variant: 'danger'
                                    });
                                }
                            })
                            .catch(err => {
                                confirmAction({
                                    title: 'Promotion Error',
                                    message: err.message,
                                    confirmButtonText: 'OK',
                                    variant: 'danger'
                                });
                            });
                    }
                });
            };

            // Submit Reschedule Adjustment via AJAX to POST /production/schedules/operations/{op}/adjust
            function submitOpAdjustment(opId, plannedStart, machineId, shiftMode, reason, expectedVersion, callback) {
                const urlParams = new URLSearchParams(window.location.search);
                const scenarioId = urlParams.get('scenario_id');

                const payload = {
                    planned_start: plannedStart,
                    machine_id: machineId,
                    shift_mode: shiftMode,
                    reason: reason,
                    expected_version: expectedVersion
                };
                if (scenarioId) {
                    payload.scenario_id = scenarioId;
                }

                fetch(`{{ url('production/schedules/operations') }}/${opId}/adjust`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                })
                    .then(r => r.json().then(data => ({ status: r.status, body: data })))
                    .then(res => {
                        if (callback) callback();

                        if (res.status === 200 && res.body.success) {
                            showToast('success', res.body.message || 'Operation schedule updated successfully.');
                            loadDispatchData(); // Reload authoritative board data
                        } else if (res.status === 409) {
                            showPlannerAlert('Concurrency Conflict', 'This operation was modified by another planner. The Dispatch Board will now refresh with the latest schedule.', 'warning', function () {
                                loadDispatchData();
                            });
                        } else {
                            showPlannerAlert('Adjustment Rejected', res.body.message || 'Validation error', 'danger', function () {
                                loadDispatchData(); // Rollback
                            });
                        }
                    })
                    .catch(err => {
                        if (callback) callback();
                        showPlannerAlert('Adjustment Error', err.message, 'danger', function () {
                            loadDispatchData();
                        });
                    });
            }

            // Quick Edit Operation Modal Handler
            function openQuickEditModal(op) {
                quickEditOp = op;
                document.getElementById('editOpId').value = op.schedule_operation_id;
                document.getElementById('editOpVersion').value = op.version;
                document.getElementById('editOrderNumber').textContent = op.production_order_number;
                document.getElementById('editProductName').textContent = op.product_name;
                document.getElementById('editOpName').textContent = `#${op.sequence} ${op.operation_name}`;
                document.getElementById('editWorkCenterName').textContent = `Work Center ID: ${op.work_center_id}`;

                // Format datetime-local string
                const d = new Date(op.planned_start);
                const localIso = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
                const editStartInput = document.querySelector('#editPlannedStart input') || document.getElementById('editPlannedStart');
                editStartInput.value = localIso;

                // Populate Machines Select
                const selectM = document.querySelector('#editMachineId select') || document.getElementById('editMachineId');
                selectM.innerHTML = '<option value="">No Machine (Manual Operation)</option>';
                if (dispatchBoardData && dispatchBoardData.resources) {
                    dispatchBoardData.resources.forEach(res => {
                        if (res.id === op.work_center_id) {
                            res.machines.forEach(m => {
                                const opt = document.createElement('option');
                                opt.value = m.id;
                                opt.textContent = `${m.name} (${m.code})`;
                                if (m.id === op.machine_id) opt.selected = true;
                                selectM.appendChild(opt);
                            });
                        }
                    });
                }

                const editShiftSelect = document.querySelector('#editShiftMode select') || document.getElementById('editShiftMode');
                editShiftSelect.value = currentShiftMode;
                document.getElementById('editBaselineStart').textContent = op.baseline_start || 'N/A';
                document.getElementById('editBaselineFinish').textContent = op.baseline_finish || 'N/A';

                const varBadge = document.getElementById('editVarianceBadge');
                varBadge.textContent = op.start_variance_minutes ? `${op.start_variance_minutes > 0 ? '+' : ''}${op.start_variance_minutes} mins` : '0 mins';
                varBadge.className = op.start_variance_minutes !== 0 ? 'badge bg-warning text-dark font-monospace' : 'badge bg-soft-secondary text-secondary font-monospace';

                document.getElementById('editLockStatusText').textContent = op.locked ? 'Lock Status: Locked 🔒' : 'Lock Status: Unlocked';

                const modal = new bootstrap.Modal(document.getElementById('modalQuickEditOp'));
                modal.show();
            }

            // Quick Edit Save Button
            document.getElementById('btnSaveQuickEdit').addEventListener('click', function () {
                if (!quickEditOp) return;
                const opId = quickEditOp.schedule_operation_id;
                const editStartInput = document.querySelector('#editPlannedStart input') || document.getElementById('editPlannedStart');
                const plannedStart = editStartInput.value;
                const selectM = document.querySelector('#editMachineId select') || document.getElementById('editMachineId');
                const machineId = selectM.value;
                const editShiftSelect = document.querySelector('#editShiftMode select') || document.getElementById('editShiftMode');
                const shiftMode = editShiftSelect.value;
                const editReasonSelect = document.querySelector('#editReason select') || document.getElementById('editReason');
                const reason = editReasonSelect.value;
                const version = quickEditOp.version;

                bootstrap.Modal.getInstance(document.getElementById('modalQuickEditOp')).hide();
                submitOpAdjustment(opId, plannedStart, machineId, shiftMode, reason, version);
            });

            // Toggle Lock inside Quick Edit Modal
            document.getElementById('btnToggleLockInModal').addEventListener('click', function () {
                if (!quickEditOp) return;
                const opId = quickEditOp.schedule_operation_id;

                fetch(`{{ url('production/schedules/operations') }}/${opId}/toggle-lock`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            showToast('success', res.message);
                            bootstrap.Modal.getInstance(document.getElementById('modalQuickEditOp')).hide();
                            loadDispatchData();
                        } else {
                            showPlannerAlert('Lock Operation Failed', res.message, 'danger');
                        }
                    })
                    .catch(err => showPlannerAlert('Lock Operation Error', err.message, 'danger'));
            });

            // Pre-Release Check Runner Function
            window.runPreReleaseCheck = function (targetScheduleId) {
                if (!dispatchBoardData || !dispatchBoardData.operations || dispatchBoardData.operations.length === 0) {
                    showPlannerAlert('Pre-Release Check', 'No operations loaded on board to perform pre-release validation.', 'warning');
                    return;
                }

                let scheduleId = targetScheduleId;
                if (!scheduleId) {
                    const selVal = filterSchedule ? filterSchedule.value : null;
                    const urlParams = new URLSearchParams(window.location.search);
                    scheduleId = selVal || urlParams.get('schedule_id');
                }

                // Extract distinct schedules present on the board
                const distinctSchedules = {};
                (dispatchBoardData.operations || []).forEach(op => {
                    if (op.schedule_id) {
                        distinctSchedules[op.schedule_id] = {
                            id: op.schedule_id,
                            number: op.schedule_number || `SCH-${op.schedule_id}`,
                            orderNumber: op.production_order_number || 'Order'
                        };
                    }
                });
                const schedList = Object.values(distinctSchedules);

                if (!scheduleId && schedList.length > 0) {
                    scheduleId = schedList[0].id;
                }

                if (!scheduleId) {
                    showPlannerAlert('Pre-Release Check', 'Please select a schedule to run pre-release check.', 'warning');
                    return;
                }

                const modalBody = document.getElementById('preReleaseModalBody');
                const modalFooter = document.getElementById('preReleaseModalFooter');
                const modalEl = document.getElementById('modalPreReleaseCheck');
                const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.show();

                let selectorHtml = '';
                if (schedList.length > 1) {
                    selectorHtml = `
                        <div class="mb-3 p-2 bg-white rounded border">
                            <label class="form-label text-dark fw-bold mb-1 fs-12"><i class="feather-filter me-1 text-primary"></i> Select Schedule to Validate & Release:</label>
                            <select class="form-select form-select-sm fw-semibold text-dark" onchange="runPreReleaseCheck(this.value)">
                                ${schedList.map(s => `<option value="${s.id}" ${s.id == scheduleId ? 'selected' : ''}>#${s.number} (${s.orderNumber})</option>`).join('')}
                            </select>
                        </div>
                    `;
                }

                modalBody.innerHTML = selectorHtml + `<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2 text-info"></div> Running completeness, machine readiness, downtime collision, dependency, and material availability checks for Schedule #${scheduleId}...</div>`;

                fetch(`{{ url('production/schedules') }}/${scheduleId}/pre-release-check`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(r => r.json())
                    .then(res => {
                        let errorsHtml = '';
                        let warningsHtml = '';

                        if (res.errors && res.errors.length > 0) {
                            errorsHtml = `
                                <div class="alert alert-danger border-danger p-3 mb-3">
                                    <h6 class="fw-bold text-danger mb-2"><i class="feather-x-circle me-1"></i> Blocking Errors (${res.errors.length})</h6>
                                    <ul class="mb-0 ps-3">
                                        ${res.errors.map(e => `<li><strong>${e.code}:</strong> ${e.message}</li>`).join('')}
                                    </ul>
                                </div>
                            `;
                        }

                        if (res.warnings && res.warnings.length > 0) {
                            warningsHtml = `
                                <div class="alert alert-warning border-warning p-3 mb-3">
                                    <h6 class="fw-bold text-warning-dark mb-2"><i class="feather-alert-triangle me-1"></i> Warnings (${res.warnings.length})</h6>
                                    <ul class="mb-0 ps-3">
                                        ${res.warnings.map(w => `<li><strong>${w.code}:</strong> ${w.message}</li>`).join('')}
                                    </ul>
                                </div>
                            `;
                        }

                        if (!errorsHtml && !warningsHtml) {
                            errorsHtml = `<div class="alert alert-success border-success p-3 mb-3"><i class="feather-check-circle me-1"></i> Schedule passed all pre-release validation checks cleanly! Ready for shop-floor release.</div>`;
                        }

                        modalBody.innerHTML = `
                            ${selectorHtml}
                            <div class="d-flex align-items-center justify-content-between mb-3 p-3 bg-light rounded border">
                                <div>
                                    <h6 class="fw-bold text-dark mb-0">Schedule ID #${scheduleId} Pre-Release Summary</h6>
                                    <span class="text-muted fs-11">${res.summary?.total_operations ?? 0} operations evaluated</span>
                                </div>
                                <div>
                                    ${res.can_release ? '<span class="badge bg-success">Can Release</span>' : '<span class="badge bg-danger">Blocked</span>'}
                                    ${res.has_warnings ? '<span class="badge bg-warning text-dark ms-1">Has Warnings</span>' : ''}
                                </div>
                            </div>
                            ${errorsHtml}
                            ${warningsHtml}
                        `;

                        if (res.can_release) {
                            const confirmBtn = res.has_warnings
                                ? `<button type="button" onclick="executeScheduleRelease(${scheduleId}, true)" class="btn btn-warning btn-sm"><i class="feather-play me-1"></i> Release Schedule With Warnings</button>`
                                : `<button type="button" onclick="executeScheduleRelease(${scheduleId}, false)" class="btn btn-primary btn-sm"><i class="feather-play me-1"></i> Release Schedule to Shop Floor</button>`;
                            modalFooter.innerHTML = `<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button> ${confirmBtn}`;
                        } else {
                            modalFooter.innerHTML = `<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button><button class="btn btn-danger btn-sm" disabled>Release Disabled (Blocking Errors Exist)</button>`;
                        }
                    })
                    .catch(err => {
                        modalBody.innerHTML = selectorHtml + `<div class="alert alert-danger p-3 mb-0">Error running pre-release check: ${err.message}</div>`;
                    });
            };

            // Toolbar Pre-Release Check Click Handler
            document.getElementById('btnPreReleaseCheck').addEventListener('click', function () {
                runPreReleaseCheck(null);
            });

            // Execute Schedule Release
            window.executeScheduleRelease = function (scheduleId, confirmWarnings) {
                fetch(`{{ url('production/schedules') }}/${scheduleId}/release`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        confirm_warnings: confirmWarnings ? 1 : 0
                    })
                })
                    .then(r => r.json())
                    .then(res => {
                        const preReleaseModal = document.getElementById('modalPreReleaseCheck');
                        if (preReleaseModal && bootstrap.Modal.getInstance(preReleaseModal)) {
                            bootstrap.Modal.getInstance(preReleaseModal).hide();
                        }
                        if (res.success) {
                            showToast('success', res.message);
                            const targetUrl = res.redirect_url || "{{ route('production.mes.dashboard') }}";
                            setTimeout(function () {
                                window.location.href = targetUrl;
                            }, 400);
                        } else {
                            showPlannerAlert('Schedule Release Failed', res.message, 'danger');
                        }
                    })
                    .catch(err => showPlannerAlert('Schedule Release Error', err.message, 'danger'));
            };

            // Change History Audit Log Drawer Handler
            document.getElementById('btnChangeHistory').addEventListener('click', function () {
                if (!dispatchBoardData || !dispatchBoardData.operations || dispatchBoardData.operations.length === 0) {
                    showPlannerAlert('Change History Audit Log', 'No operations loaded on board to view change history.', 'warning');
                    return;
                }

                const scheduleId = dispatchBoardData.operations[0].schedule_id;
                const modalBody = document.getElementById('changeHistoryModalBody');
                const modal = new bootstrap.Modal(document.getElementById('modalChangeHistory'));
                modal.show();

                modalBody.innerHTML = `<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div> Loading audit logs for Schedule #${scheduleId}...</div>`;

                fetch(`{{ url('production/schedules') }}/${scheduleId}/change-history`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(r => r.json())
                    .then(res => {
                        const logs = res.data || [];
                        if (logs.length === 0) {
                            modalBody.innerHTML = `<div class="text-center py-5 text-muted">No schedule change logs found.</div>`;
                            return;
                        }

                        modalBody.innerHTML = `
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 fs-12">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Changed By</th>
                                                <th>Operation</th>
                                                <th>Type</th>
                                                <th>Mode</th>
                                                <th>Start Shift</th>
                                                <th>Machine Shift</th>
                                                <th>Reason</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${logs.map(log => `
                                                <tr>
                                                    <td class="font-monospace">${log.created_at ? log.created_at.slice(0, 19).replace('T', ' ') : 'N/A'}</td>
                                                    <td>${log.changed_by?.name || 'Planner'}</td>
                                                    <td><strong>#${log.operation?.sequence || ''} ${log.operation?.order_operation?.name || 'Op'}</strong></td>
                                                    <td><span class="badge bg-soft-primary text-primary">${log.change_type}</span></td>
                                                    <td><span class="badge bg-soft-info text-info">${log.shift_mode}</span></td>
                                                    <td class="font-monospace fs-11">
                                                        ${log.old_planned_start ? log.old_planned_start.slice(0, 16).replace('T', ' ') : ''}
                                                        <i class="feather-arrow-right mx-1 text-muted"></i>
                                                        <strong class="text-primary">${log.new_planned_start ? log.new_planned_start.slice(0, 16).replace('T', ' ') : ''}</strong>
                                                    </td>
                                                    <td class="fs-11">
                                                        ${log.old_machine ? log.old_machine.name : 'None'} → <strong>${log.new_machine ? log.new_machine.name : 'None'}</strong>
                                                    </td>
                                                    <td class="text-muted">${log.reason || 'Manual Reschedule'}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            `;
                    })
                    .catch(err => {
                        modalBody.innerHTML = `<div class="alert alert-danger p-3 mb-0">Error loading change history: ${err.message}</div>`;
                    });
            });

            // Utility Functions
            function formatDate(d) {
                return d.toISOString().split('T')[0];
            }

            function formatIsoDate(isoStr) {
                if (!isoStr) return 'N/A';
                const d = new Date(isoStr);
                if (isNaN(d.getTime())) return isoStr;
                const day = String(d.getDate()).padStart(2, '0');
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const hours = String(d.getHours()).padStart(2, '0');
                const mins = String(d.getMinutes()).padStart(2, '0');
                return `${day}/${month} ${hours}:${mins}`;
            }

            function escapeHtml(str) {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function getOpStatusClass(status) {
                switch (status) {
                    case 'completed': return 'bg-secondary';
                    case 'in_progress': return 'bg-success';
                    case 'released': return 'bg-info';
                    case 'scheduled': return 'bg-primary';
                    default: return 'bg-primary';
                }
            }

            function formatVariance(mins) {
                if (mins === undefined || mins === null || mins === 0) return '';
                const sign = mins > 0 ? '+' : '';
                const absMins = Math.abs(mins);
                if (absMins >= 1440) {
                    return `${sign}${(mins / 1440).toFixed(1)}d`;
                }
                if (absMins >= 60) {
                    return `${sign}${(mins / 60).toFixed(1)}h`;
                }
                return `${sign}${mins}m`;
            }

            function showToast(type, msg) {
                if (window.Swal) {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: type,
                        title: msg,
                        showConfirmButton: false,
                        timer: 3000
                    });
                } else {
                    showPlannerAlert(type.toUpperCase(), msg, type === 'error' ? 'danger' : 'info');
                }
            }

            // Initial Board Load
            loadDispatchData();
        });
    </script>

    {{-- Gantt Custom Styles --}}
    <style>
        .gantt-board-wrapper {
            font-family: inherit;
        }

        .gantt-header-tick {
            min-width: 90px;
        }

        .gantt-op-bar {
            transition: left 0.2s ease, width 0.2s ease;
            border-left: 3px solid rgba(0, 0, 0, 0.3);
        }

        .gantt-op-bar:hover {
            z-index: 20;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15) !important;
            filter: brightness(1.05);
        }

        .gantt-downtime-block {
            pointer-events: auto;
        }

        .sticky-start {
            position: sticky;
            left: 0;
            z-index: 5;
        }
    </style>

@endsection