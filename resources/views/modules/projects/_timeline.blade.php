@php
    $canUpdateAnyTask = auth()->user()->can('update', $project);
    $activeMilestones = isset($milestones) ? $milestones : $project->milestones()->orderBy('milestone_order')->get();
@endphp

<div x-data="ganttTimeline({
    projectId: {{ $project->id }},
    milestoneId: {{ !empty($milestoneId) ? (int) $milestoneId : (!empty($selectedMilestoneId) ? (int) $selectedMilestoneId : 'null') }},
    dataUrl: '{{ route('projects.timeline.data', $project) }}',
    rescheduleBaseUrl: '{{ url('projects/' . $project->id . '/timeline/tasks') }}',
    csrfToken: '{{ csrf_token() }}',
    canUpdate: {{ $canUpdateAnyTask ? 'true' : 'false' }},
    translations: {
        taskRescheduled: '{{ __('projects.task_rescheduled') }}',
        rescheduleFailed: '{{ __('projects.reschedule_failed') }}',
        noTasks: '{{ __('projects.no_tasks_for_timeline') }}',
        critical: '{{ __('projects.critical_path') }}',
        days: '{{ __('projects.days') }}',
        ripple: '{{ __('projects.shift_mode_ripple') }}',
        isolated: '{{ __('projects.shift_mode_isolated') }}',
    }
})" x-init="init()" class="gantt-board-wrapper mb-4">

    {{-- Common Toast Component --}}
    <x-ui.toast id="ganttTimelineToast" class="d-none" />

    {{-- Board Controls Toolbar --}}
    <div class="card border mb-3 shadow-none">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                {{-- Left: Filter & Scale --}}
                <div class="d-flex flex-wrap align-items-center gap-2">
                    {{-- Milestone Selector (Using odoo-form-ui component) --}}
                    @if(empty($milestoneId))
                        <div class="d-flex align-items-center me-2" style="min-width: 260px;">
                            <x-ui.odoo-form-ui type="select" id="ganttMilestoneFilter" name="gantt_milestone_filter" label="<i class='feather-flag me-1'></i>{{ __('projects.filter_by_milestone') }}" x-model="selectedMilestone" @change="onMilestoneFilterChange()">
                                <option value="">{{ __('projects.all_milestones') }}</option>
                                @foreach($activeMilestones as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                    @endif

                    {{-- Scale Selector --}}
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn" :class="scale === 'day' ? 'btn-primary' : 'btn-outline-secondary'" @click="setScale('day')">
                            {{ __('projects.scale_day') }}
                        </button>
                        <button type="button" class="btn" :class="scale === 'week' ? 'btn-primary' : 'btn-outline-secondary'" @click="setScale('week')">
                            {{ __('projects.scale_week') }}
                        </button>
                        <button type="button" class="btn" :class="scale === 'month' ? 'btn-primary' : 'btn-outline-secondary'" @click="setScale('month')">
                            {{ __('projects.scale_month') }}
                        </button>
                    </div>

                    {{-- Highlight Critical Path Toggle --}}
                    <button type="button" class="btn btn-sm d-flex align-items-center gap-1"
                        :class="highlightCritical ? 'btn-danger' : 'btn-outline-secondary'"
                        @click="highlightCritical = !highlightCritical">
                        <i class="feather-zap"></i>
                        <span>{{ __('projects.highlight_critical_path') }}</span>
                    </button>
                </div>

                {{-- Right: Reschedule Mode & Refresh --}}
                <div class="d-flex flex-wrap align-items-center gap-3">
                    {{-- Shift Mode (Ripple vs Isolated) - Perfectly Centered --}}
                    <div class="d-flex align-items-center gap-2 border rounded px-2 py-1 bg-light">
                        <span class="fs-12 fw-semibold text-muted d-inline-flex align-items-center" title="{{ __('projects.ripple_mode_tooltip') }}">
                            <i class="feather-sliders me-1"></i>{{ __('projects.shift_mode') }}:
                        </span>
                        <div class="gantt-radio-wrap form-check form-check-inline mb-0 fs-12 d-inline-flex align-items-center">
                            <input class="form-check-input mt-0 me-1" type="radio" name="shiftMode" id="shiftRipple" value="ripple" x-model="shiftMode">
                            <label class="form-check-label fw-semibold text-primary mb-0" for="shiftRipple" title="{{ __('projects.ripple_mode_tooltip') }}">
                                {{ __('projects.shift_mode_ripple') }}
                            </label>
                        </div>
                        <div class="gantt-radio-wrap form-check form-check-inline mb-0 fs-12 d-inline-flex align-items-center">
                            <input class="form-check-input mt-0 me-1" type="radio" name="shiftMode" id="shiftIsolated" value="isolated" x-model="shiftMode">
                            <label class="form-check-label fw-semibold text-secondary mb-0" for="shiftIsolated" title="{{ __('projects.isolated_mode_tooltip') }}">
                                {{ __('projects.shift_mode_isolated') }}
                            </label>
                        </div>
                    </div>

                    {{-- Refresh Button --}}
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="loadSchedule()" :disabled="loading" title="Refresh">
                        <i class="feather-refresh-cw" :class="loading ? 'feather-spin' : ''"></i>
                    </button>
                </div>
            </div>

            {{-- Legend & Summary Bar --}}
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-2 pt-2 border-top fs-12 text-muted">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span class="d-flex align-items-center gap-1">
                        <span class="d-inline-block rounded" style="width: 12px; height: 12px; background-color: #ef4444;"></span>
                        <strong class="text-danger">{{ __('projects.critical_path_legend') }}</strong>
                    </span>
                    <span class="d-flex align-items-center gap-1">
                        <span class="d-inline-block rounded" style="width: 12px; height: 12px; background-color: #3b82f6;"></span>
                        <span>{{ __('projects.non_critical') }}</span>
                    </span>
                    <span class="d-flex align-items-center gap-1">
                        <span class="d-inline-block rounded" style="width: 12px; height: 12px; background-color: #10b981;"></span>
                        <span>{{ __('projects.statuses.Completed') }}</span>
                    </span>
                    <span class="d-flex align-items-center gap-1">
                        <span class="d-inline-block" style="width: 10px; height: 10px; transform: rotate(45deg); background-color: #f59e0b;"></span>
                        <span>{{ __('projects.milestone') }}</span>
                    </span>
                </div>
                <template x-if="schedule">
                    <div class="d-flex align-items-center gap-2">
                        <span>{{ __('projects.duration') }}: <strong class="text-dark" x-text="schedule.project_duration + ' ' + (schedule.project_duration === 1 ? '{{ __('projects.day') }}' : '{{ __('projects.days') }}')"></strong></span>
                        <span class="text-muted">·</span>
                        <span>Tasks: <strong class="text-dark" x-text="schedule.tasks.length"></strong></span>
                        <span class="text-muted">·</span>
                        <span>Critical Tasks: <strong class="text-danger" x-text="schedule.critical_path.length"></strong></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Gantt Layout Container --}}
    <div class="card border shadow-none position-relative overflow-hidden" style="min-height: 400px;">
        {{-- Loading Overlay --}}
        <template x-if="loading">
            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 50;">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="fs-12 text-muted mt-2">{{ __('ui.loading') }}</div>
                </div>
            </div>
        </template>

        {{-- Empty State --}}
        <template x-if="!loading && schedule && schedule.tasks.length === 0">
            <div class="text-center py-5">
                <div class="avatar-text avatar-lg bg-soft-secondary text-secondary mx-auto mb-3">
                    <i class="feather-calendar fs-2"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">{{ __('projects.no_tasks_for_timeline') }}</h6>
                <p class="fs-12 text-muted mb-0">Create tasks with start and due dates to view the interactive Gantt chart.</p>
            </div>
        </template>

        {{-- Gantt Split Workspace --}}
        <template x-if="schedule && schedule.tasks.length > 0">
            <div class="d-flex flex-row w-100 overflow-hidden" style="min-height: 480px;">
                {{-- Left Pane: Task Details Grid --}}
                <div class="border-end bg-white flex-shrink-0" style="width: 380px; z-index: 10;">
                    {{-- Header --}}
                    <div class="d-flex align-items-center px-3 border-bottom bg-light text-muted fw-bold fs-11 text-uppercase" style="height: 48px;">
                        <div style="width: 85px;">{{ __('projects.code') }}</div>
                        <div class="flex-grow-1">{{ __('projects.task_name') }}</div>
                        <div style="width: 60px;" class="text-center">{{ __('projects.duration') }}</div>
                        <div style="width: 65px;" class="text-center">{{ __('projects.total_float') }}</div>
                    </div>
                    {{-- Rows --}}
                    <div class="task-rows-list">
                        <template x-for="(task, index) in schedule.tasks" :key="task.id">
                            <div class="d-flex align-items-center px-3 border-bottom fs-12 gantt-task-row"
                                :class="{'bg-soft-danger': highlightCritical && task.is_critical && !(task.is_completed || task.status === 'Completed'), 'bg-light-hover': true}"
                                style="height: 44px; cursor: pointer;"
                                @click="openRescheduleModal(task)"
                                @mouseenter="hoveredTaskId = task.id"
                                @mouseleave="hoveredTaskId = null"
                                x-bind:title="'{{ __('projects.reschedule_task') }}: ' + task.task_code + ' - ' + task.title">
                                <div style="width: 85px;" class="font-monospace text-primary fw-semibold text-truncate" x-bind:title="task.task_code" x-text="task.task_code"></div>
                                <div class="flex-grow-1 text-truncate fw-medium text-dark pe-2" x-bind:title="task.title" x-text="task.title"></div>
                                <div style="width: 60px;" class="text-center text-muted" x-text="task.duration + 'd'"></div>
                                <div style="width: 65px;" class="text-center">
                                    <template x-if="task.is_completed || task.status === 'Completed'">
                                        <span class="badge bg-success text-white py-1 px-1 fs-10">{{ __('projects.statuses.Completed') }}</span>
                                    </template>
                                    <template x-if="!(task.is_completed || task.status === 'Completed') && task.is_critical">
                                        <span class="badge bg-danger text-white py-1 px-1 fs-10" x-bind:title="'ES: ' + task.early_start + ', LS: ' + task.late_start">0d Crit</span>
                                    </template>
                                    <template x-if="!(task.is_completed || task.status === 'Completed') && !task.is_critical">
                                        <span class="badge bg-soft-secondary text-secondary py-1 px-1 fs-10" x-bind:title="'ES: ' + task.early_start + ', LS: ' + task.late_start" x-text="'+' + task.total_float + 'd'"></span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Right Pane: Timeline Canvas & SVG Overlay --}}
                <div class="flex-grow-1 overflow-auto position-relative bg-white" id="ganttCanvasContainer" style="cursor: default;" @scroll="onCanvasScroll()">
                    <div class="position-relative" :style="'width: ' + totalCanvasWidth + 'px; min-height: ' + (48 + schedule.tasks.length * 44) + 'px;'">

                        {{-- Date Scale Header --}}
                        <div class="d-flex border-bottom bg-light position-sticky top-0" style="height: 48px; z-index: 8;">
                            <template x-for="(col, cIdx) in timeColumns" :key="cIdx">
                                <div class="border-end d-flex flex-column align-items-center justify-content-center text-muted fs-11"
                                    :class="col.isToday ? 'bg-soft-primary fw-bold text-primary' : (col.isWeekend ? 'bg-light text-muted' : '')"
                                    :style="'width: ' + columnWidth + 'px; flex-shrink: 0;'">
                                    <span class="text-uppercase" style="font-size: 10px;" x-text="col.topLabel"></span>
                                    <span class="fw-semibold text-dark" x-text="col.bottomLabel"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Grid Background Columns --}}
                        <div class="position-absolute start-0 w-100 d-flex" style="top: 48px; bottom: 0; pointer-events: none; z-index: 1;">
                            <template x-for="(col, cIdx) in timeColumns" :key="'grid-' + cIdx">
                                <div class="border-end h-100"
                                    :class="col.isToday ? 'bg-soft-primary' : (col.isWeekend ? 'bg-light opacity-50' : '')"
                                    :style="'width: ' + columnWidth + 'px; flex-shrink: 0;'"></div>
                            </template>
                        </div>

                        {{-- SVG Overlay for Dependency Connectors --}}
                        <svg class="position-absolute start-0 top-0 w-100 h-100" style="pointer-events: none; z-index: 6;">
                            <defs>
                                <marker id="gantt-arrow-default" viewBox="0 0 10 10" refX="6" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                                    <path d="M 0 1 L 8 5 L 0 9 z" fill="#64748b" />
                                </marker>
                                <marker id="gantt-arrow-critical" viewBox="0 0 10 10" refX="6" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                                    <path d="M 0 1 L 8 5 L 0 9 z" fill="#ef4444" />
                                </marker>
                            </defs>
                            <template x-for="(pathItem, pIdx) in connectorPaths" :key="'path-' + pIdx">
                                <path :d="pathItem.d"
                                    :stroke="pathItem.isCritical && highlightCritical ? '#ef4444' : '#64748b'"
                                    :stroke-width="pathItem.isCritical && highlightCritical ? '2.5' : '1.5'"
                                    :stroke-dasharray="pathItem.isCritical && highlightCritical ? 'none' : '3,3'"
                                    fill="none"
                                    :marker-end="pathItem.isCritical && highlightCritical ? 'url(#gantt-arrow-critical)' : 'url(#gantt-arrow-default)'" />
                            </template>
                        </svg>

                        {{-- Task Rows and Bars --}}
                        <div class="position-relative" style="top: 0; z-index: 4;">
                            <template x-for="(task, rIdx) in schedule.tasks" :key="'bar-row-' + task.id">
                                <div class="position-relative border-bottom d-flex align-items-center"
                                    :style="'height: 44px; width: ' + totalCanvasWidth + 'px;'"
                                    @dragover.prevent=""
                                    @drop.prevent="onDropRow($event, task, rIdx)">

                                    {{-- Task Bar Element --}}
                                    <div class="gantt-bar-item position-absolute rounded shadow-sm d-flex align-items-center justify-content-between px-2 text-white fs-11"
                                        :id="'gantt-bar-' + task.id"
                                        :style="getTaskBarStyle(task, rIdx)"
                                        :class="getTaskBarClass(task)"
                                        :draggable="canUpdate && !(task.is_completed || task.status === 'Completed')"
                                        @click="onBarClick($event, task)"
                                        @dragstart="onDragStart($event, task)"
                                        @dragend="onDragEnd($event)"
                                        @mouseenter="hoveredTaskId = task.id"
                                        @mouseleave="hoveredTaskId = null"
                                        x-bind:title="getTaskTooltip(task)">

                                        {{-- Task Code / Title Inside Bar --}}
                                        <div class="d-flex align-items-center gap-1 text-truncate pe-1">
                                            <template x-if="task.is_completed || task.status === 'Completed'">
                                                <i class="feather-check-circle text-white me-1" style="font-size: 11px;"></i>
                                            </template>
                                            <template x-if="!(task.is_completed || task.status === 'Completed') && task.is_critical && highlightCritical">
                                                <i class="feather-zap text-warning" style="font-size: 11px;"></i>
                                            </template>
                                            <span class="fw-bold" x-text="task.task_code"></span>
                                            <span class="text-truncate opacity-90" x-text="task.title"></span>
                                        </div>

                                        {{-- Duration Label Inside Bar --}}
                                        <span class="badge bg-black bg-opacity-25 fs-10 px-1" x-text="task.duration + 'd'"></span>
                                    </div>
                                </div>
                            </template>
                        </div>

                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Drag & Drop Confirmation Modal (Matching Production Dispatch Board) --}}
    <x-ui.modal id="modalConfirmTaskDrag" title="<i class='feather-move me-2 text-primary'></i>{{ __('projects.confirm_reschedule') }}"
        :centered="true" :static="true" :showFooter="false">
        <p class="mb-3 text-dark fs-13">{{ __('projects.confirm_proposed_move') }}<strong id="dragTaskTitle">—</strong>?</p>

        <div class="p-3 bg-light rounded border mb-3 fs-12">
            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                <span class="text-muted">{{ __('projects.current_start') }} &rarr; {{ __('projects.current_due') }}</span>
                <span class="text-dark font-monospace fw-medium"><span id="dragCurrentStartDisplay">—</span> to <span id="dragCurrentDueDisplay">—</span></span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                <span class="text-muted">{{ __('projects.proposed_start') }} &rarr; {{ __('projects.proposed_due') }}</span>
                <strong class="text-primary font-monospace"><span id="dragNewStartDisplay">—</span> to <span id="dragNewDueDisplay">—</span></strong>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-muted">{{ __('projects.duration') }}</span>
                <span id="dragDurationDisplay" class="badge bg-soft-primary text-primary font-monospace">—</span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">{{ __('projects.shift_mode') }}</span>
                <span id="dragShiftModeBadge" class="badge bg-soft-info text-info">{{ __('projects.shift_mode_ripple') }}</span>
            </div>
        </div>

        <div id="dragValidationAlert" class="alert alert-danger py-2 px-3 fs-12 d-none mb-3">
            <i class="feather-alert-octagon me-1"></i><span id="dragValidationText"></span>
        </div>

        <div class="mb-3">
            <label class="form-label fs-12 fw-semibold text-dark mb-1">{{ __('projects.shift_mode') }}</label>
            <div class="d-flex gap-3">
                <div class="gantt-radio-wrap form-check form-check-inline fs-12 d-inline-flex align-items-center">
                    <input class="form-check-input mt-0 me-1" type="radio" name="dragShiftModeChoice" id="dragModeRipple" value="ripple">
                    <label class="form-check-label mb-0" for="dragModeRipple">{{ __('projects.shift_mode_ripple') }}</label>
                </div>
                <div class="gantt-radio-wrap form-check form-check-inline fs-12 d-inline-flex align-items-center">
                    <input class="form-check-input mt-0 me-1" type="radio" name="dragShiftModeChoice" id="dragModeIsolated" value="isolated">
                    <label class="form-check-label mb-0" for="dragModeIsolated">{{ __('projects.shift_mode_isolated') }}</label>
                </div>
            </div>
            <div class="form-text fs-11 text-muted mt-1" id="dragModeHelpText">
                {{ __('projects.ripple_mode_tooltip') }}
            </div>
        </div>

        <div class="modal-footer px-0 pb-0 pt-3 border-top">
            <x-ui.button type="button" id="btnCancelTaskDrag" variant="secondary">{{ __('projects.cancel') }}</x-ui.button>
            <x-ui.button type="button" id="btnApplyTaskDrag" variant="primary" icon="feather-check">{{ __('projects.confirm_save_move') }}</x-ui.button>
        </div>
    </x-ui.modal>

    {{-- Task Reschedule Quick Modal (Matching Production Dispatch Board Quick Edit) --}}
    <x-ui.modal id="modalRescheduleTask" title="<i class='feather-calendar me-2 text-primary'></i>{{ __('projects.reschedule_task') }}"
        size="md" :centered="true" :static="true" :showFooter="false">
        <input type="hidden" id="rescheduleTaskId">

        <div class="p-3 bg-light rounded border mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span id="rescheduleTaskCode" class="badge bg-soft-primary text-primary font-monospace fs-11">—</span>
                <span id="rescheduleCriticalBadge" class="badge bg-soft-secondary text-secondary fs-11">—</span>
            </div>
            <h6 id="rescheduleTaskTitle" class="fw-bold text-dark mb-1 fs-13">—</h6>
            <div class="d-flex align-items-center gap-3 text-muted fs-11">
                <span>{{ __('projects.duration') }}: <strong id="rescheduleDurationText" class="text-dark">—</strong></span>
                <span>{{ __('projects.total_float') }}: <strong id="rescheduleFloatText" class="text-dark">—</strong></span>
            </div>
        </div>

        <div id="rescheduleValidationAlert" class="alert alert-danger py-2 px-3 fs-12 d-none mb-3">
            <i class="feather-alert-octagon me-1"></i><span id="rescheduleValidationText"></span>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6">
                <label class="form-label fs-12 fw-semibold text-dark mb-1" for="rescheduleStartDateInput">{{ __('projects.start_date') }}</label>
                <input type="date" id="rescheduleStartDateInput" class="form-control form-control-sm">
            </div>
            <div class="col-6">
                <label class="form-label fs-12 fw-semibold text-dark mb-1" for="rescheduleDueDateInput">{{ __('projects.due_date') }}</label>
                <input type="date" id="rescheduleDueDateInput" class="form-control form-control-sm">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fs-12 fw-semibold text-dark mb-1">{{ __('projects.shift_mode') }}</label>
            <div class="d-flex gap-3">
                <div class="gantt-radio-wrap form-check form-check-inline fs-12 d-inline-flex align-items-center">
                    <input class="form-check-input mt-0 me-1" type="radio" name="rescheduleShiftModeChoice" id="rescheduleModeRipple" value="ripple">
                    <label class="form-check-label mb-0" for="rescheduleModeRipple">{{ __('projects.shift_mode_ripple') }}</label>
                </div>
                <div class="gantt-radio-wrap form-check form-check-inline fs-12 d-inline-flex align-items-center">
                    <input class="form-check-input mt-0 me-1" type="radio" name="rescheduleShiftModeChoice" id="rescheduleModeIsolated" value="isolated">
                    <label class="form-check-label mb-0" for="rescheduleModeIsolated">{{ __('projects.shift_mode_isolated') }}</label>
                </div>
            </div>
            <div class="form-text fs-11 text-muted mt-1" id="rescheduleModeHelpText">
                {{ __('projects.ripple_mode_tooltip') }}
            </div>
        </div>

        <div class="modal-footer px-0 pb-0 pt-3 border-top">
            <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal">{{ __('projects.cancel') }}</x-ui.button>
            <x-ui.button type="button" id="btnApplyTaskReschedule" variant="primary" icon="feather-check">{{ __('projects.apply_reschedule') }}</x-ui.button>
        </div>
    </x-ui.modal>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('ganttTimeline', (config) => ({
        projectId: config.projectId,
        milestoneId: config.milestoneId,
        selectedMilestone: config.milestoneId || '',
        dataUrl: config.dataUrl,
        rescheduleBaseUrl: config.rescheduleBaseUrl,
        csrfToken: config.csrfToken,
        canUpdate: config.canUpdate,
        translations: config.translations,

        scale: 'day',
        highlightCritical: true,
        shiftMode: 'ripple', // default ripple
        loading: false,
        errorMessage: null,
        successMessage: null,

        schedule: null,
        timeColumns: [],
        columnWidth: 40,
        totalCanvasWidth: 1200,
        connectorPaths: [],
        hoveredTaskId: null,

        dragTask: null,
        dragStartColIndex: null,
        dragJustEnded: false,

        init() {
            this.loadSchedule();
            this.$nextTick(() => {
                if (typeof $ !== 'undefined' && $.fn.select2) {
                    const $sel = $('#ganttMilestoneFilter');
                    if ($sel.length && !$sel.hasClass('select2-hidden-accessible')) {
                        $sel.select2({
                            theme: 'bootstrap-5',
                            width: '100%'
                        });
                    }
                    $sel.off('change.ganttFilter').on('change.ganttFilter', (e) => {
                        this.selectedMilestone = e.target.value;
                        this.onMilestoneFilterChange();
                    });
                }
            });
        },

        setScale(newScale) {
            this.scale = newScale;
            this.computeTimeColumns();
            this.$nextTick(() => this.recomputeConnectors());
        },

        onMilestoneFilterChange() {
            this.loadSchedule(this.selectedMilestone);
        },

        loadSchedule(milestoneFilter = null) {
            this.loading = true;
            this.errorMessage = null;

            let url = this.dataUrl;
            let mId = milestoneFilter !== null ? milestoneFilter : this.selectedMilestone;
            if (mId) {
                url += (url.includes('?') ? '&' : '?') + 'milestone_id=' + encodeURIComponent(mId);
            }

            fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error('Failed to load project schedule.');
                return res.json();
            })
            .then(data => {
                this.schedule = data;
                this.computeTimeColumns();
                this.$nextTick(() => {
                    this.recomputeConnectors();
                });
            })
            .catch(err => {
                this.showToast('error', err.message || 'Error loading timeline schedule.');
            })
            .finally(() => {
                this.loading = false;
            });
        },

        formatLocalDate(date) {
            if (!date) return '';
            let year = date.getFullYear();
            let month = String(date.getMonth() + 1).padStart(2, '0');
            let day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },

        parseLocalDate(dateStr) {
            if (!dateStr) return new Date();
            let parts = String(dateStr).split('-');
            return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        },

        computeTimeColumns() {
            if (!this.schedule || !this.schedule.tasks || this.schedule.tasks.length === 0) {
                this.timeColumns = [];
                return;
            }

            // Determine bounds
            let minDateStr = this.schedule.min_start;
            let maxDateStr = this.schedule.max_due;

            let today = new Date();
            let todayStr = this.formatLocalDate(today);

            if (!minDateStr || !maxDateStr) {
                minDateStr = todayStr;
                let future = new Date(today);
                future.setDate(future.getDate() + 14);
                maxDateStr = this.formatLocalDate(future);
            }

            let start = this.parseLocalDate(minDateStr);
            let end = this.parseLocalDate(maxDateStr);

            // Add margin days
            start.setDate(start.getDate() - 3);
            end.setDate(end.getDate() + 7);

            this.canvasStartDate = new Date(start);
            this.canvasEndDate = new Date(end);

            let cols = [];
            let curr = new Date(start);

            if (this.scale === 'day') {
                this.columnWidth = 42;
                while (curr <= end) {
                    let dStr = this.formatLocalDate(curr);
                    let dayOfWeek = curr.toLocaleDateString('en-US', { weekday: 'narrow' });
                    let dayNum = curr.getDate();
                    let isWeekend = (curr.getDay() === 0 || curr.getDay() === 6);

                    cols.push({
                        dateStr: dStr,
                        topLabel: dayOfWeek,
                        bottomLabel: dayNum,
                        isToday: (dStr === todayStr),
                        isWeekend: isWeekend,
                        dateObj: new Date(curr)
                    });
                    curr.setDate(curr.getDate() + 1);
                }
            } else if (this.scale === 'week') {
                this.columnWidth = 90;
                while (curr <= end) {
                    let dStr = this.formatLocalDate(curr);
                    let weekNum = 'W' + Math.ceil(curr.getDate() / 7);
                    let monthShort = curr.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

                    cols.push({
                        dateStr: dStr,
                        topLabel: weekNum,
                        bottomLabel: monthShort,
                        isToday: (dStr === todayStr),
                        isWeekend: false,
                        dateObj: new Date(curr)
                    });
                    curr.setDate(curr.getDate() + 7);
                }
            } else { // month
                this.columnWidth = 140;
                while (curr <= end) {
                    let dStr = this.formatLocalDate(curr);
                    let monthName = curr.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });

                    cols.push({
                        dateStr: dStr,
                        topLabel: curr.getFullYear(),
                        bottomLabel: monthName,
                        isToday: (dStr.substring(0, 7) === todayStr.substring(0, 7)),
                        isWeekend: false,
                        dateObj: new Date(curr)
                    });
                    curr.setMonth(curr.getMonth() + 1);
                }
            }

            this.timeColumns = cols;
            this.totalCanvasWidth = Math.max(900, cols.length * this.columnWidth);
        },

        getDaysDiff(d1, d2) {
            if (!d1 || !d2) return 0;
            let p1 = String(d1).split('-').map(Number);
            let p2 = String(d2).split('-').map(Number);
            let t1 = new Date(p1[0], p1[1] - 1, p1[2]).getTime();
            let t2 = new Date(p2[0], p2[1] - 1, p2[2]).getTime();
            return Math.round((t2 - t1) / 86400000);
        },

        dateToPixels(dateStr) {
            if (!this.canvasStartDate || !dateStr) return 0;
            let canvasStartStr = this.formatLocalDate(this.canvasStartDate);
            let days = this.getDaysDiff(canvasStartStr, dateStr);
            if (this.scale === 'day') {
                return days * this.columnWidth;
            } else if (this.scale === 'week') {
                return (days / 7) * this.columnWidth;
            } else {
                return (days / 30.416) * this.columnWidth;
            }
        },

        getTaskBarStyle(task, rIdx) {
            let leftPx = this.dateToPixels(task.start_date);
            let rightPx = this.dateToPixels(task.due_date);
            let durationDays = Math.max(1, this.getDaysDiff(task.start_date, task.due_date) + 1);

            let widthPx;
            if (this.scale === 'day') {
                widthPx = Math.max(this.columnWidth, durationDays * this.columnWidth);
            } else if (this.scale === 'week') {
                widthPx = Math.max(28, (durationDays / 7) * this.columnWidth);
            } else {
                widthPx = Math.max(20, (durationDays / 30.416) * this.columnWidth);
            }

            return `left: ${Math.max(4, leftPx)}px; width: ${widthPx}px; top: 6px; height: 32px;`;
        },

        getTaskBarClass(task) {
            if (task.is_completed || task.status === 'Completed') {
                return 'bg-success border border-success';
            }
            if (this.highlightCritical && task.is_critical) {
                return 'bg-danger border border-danger shadow';
            }
            return 'bg-primary border border-primary';
        },

        getTaskTooltip(task) {
            return `${task.task_code} - ${task.title}\nDates: ${task.start_date} to ${task.due_date} (${task.duration} days)\nES: ${task.early_start} | EF: ${task.early_finish}\nLS: ${task.late_start} | LF: ${task.late_finish}\nTotal Float: ${task.total_float}d | Free Float: ${task.free_float}d${task.is_critical ? ' (CRITICAL PATH)' : ''}`;
        },

        showToast(type, message) {
            const el = document.getElementById('ganttTimelineToast');
            if (el) {
                el.setAttribute('data-type', type);
                el.setAttribute('data-title', message);
                el.click();
            } else if (typeof Swal !== 'undefined') {
                Swal.fire({ toast: true, position: 'top-end', timer: 3000, showConfirmButton: false, icon: type, title: message });
            } else {
                alert(message);
            }
        },

        validateConstraints(task, proposedStart, proposedDue, mode) {
            if (proposedDue < proposedStart) {
                return 'Due date cannot be earlier than start date.';
            }

            if (!this.schedule || !this.schedule.links || !this.schedule.tasks) {
                return null;
            }

            let taskMap = {};
            this.schedule.tasks.forEach(t => {
                taskMap[t.id] = t;
            });

            // 1. Verify Predecessor Constraints (Both Isolated & Ripple)
            for (let link of this.schedule.links) {
                let pId = link.predecessor_id ?? link.depends_on_task_id;
                let sId = link.successor_id ?? link.task_id;

                if (sId === task.id) {
                    let pred = taskMap[pId];
                    if (!pred) continue;

                    let type = link.type ?? link.dependency_type ?? 'Finish-to-Start';
                    let lag = parseInt(link.lag_days ?? 0, 10) || 0;

                    if (type === 'Finish-to-Start' && pred.due_date) {
                        let pDue = this.parseLocalDate(pred.due_date);
                        pDue.setDate(pDue.getDate() + 1 + lag);
                        let minStartStr = this.formatLocalDate(pDue);
                        if (proposedStart < minStartStr) {
                            return `Cannot start on ${proposedStart}. Finish-to-Start predecessor '${pred.task_code}' (${pred.title}) finishes on ${pred.due_date}${lag ? ' with ' + lag + 'd lag.' : '.'}`;
                        }
                    } else if (type === 'Start-to-Start' && pred.start_date) {
                        let pStart = this.parseLocalDate(pred.start_date);
                        pStart.setDate(pStart.getDate() + lag);
                        let minStartStr = this.formatLocalDate(pStart);
                        if (proposedStart < minStartStr) {
                            return `Cannot start on ${proposedStart}. Start-to-Start predecessor '${pred.task_code}' (${pred.title}) starts on ${pred.start_date}${lag ? ' with ' + lag + 'd lag.' : '.'}`;
                        }
                    } else if (type === 'Finish-to-Finish' && pred.due_date) {
                        let pDue = this.parseLocalDate(pred.due_date);
                        pDue.setDate(pDue.getDate() + lag);
                        let minDueStr = this.formatLocalDate(pDue);
                        if (proposedDue < minDueStr) {
                            return `Cannot finish on ${proposedDue}. Finish-to-Finish predecessor '${pred.task_code}' (${pred.title}) finishes on ${pred.due_date}${lag ? ' with ' + lag + 'd lag.' : '.'}`;
                        }
                    } else if (type === 'Start-to-Finish' && pred.start_date) {
                        let pStart = this.parseLocalDate(pred.start_date);
                        pStart.setDate(pStart.getDate() + lag);
                        let minDueStr = this.formatLocalDate(pStart);
                        if (proposedDue < minDueStr) {
                            return `Cannot finish on ${proposedDue}. Start-to-Finish predecessor '${pred.task_code}' (${pred.title}) starts on ${pred.start_date}${lag ? ' with ' + lag + 'd lag.' : '.'}`;
                        }
                    }
                }
            }

            // 2. In Isolated mode, verify Successor Constraints (since downstream tasks won't shift)
            if (mode === 'isolated') {
                for (let link of this.schedule.links) {
                    let pId = link.predecessor_id ?? link.depends_on_task_id;
                    let sId = link.successor_id ?? link.task_id;

                    if (pId === task.id) {
                        let succ = taskMap[sId];
                        if (!succ) continue;

                        let type = link.type ?? link.dependency_type ?? 'Finish-to-Start';
                        let lag = parseInt(link.lag_days ?? 0, 10) || 0;

                        if (type === 'Finish-to-Start' && succ.start_date) {
                            let pDue = this.parseLocalDate(proposedDue);
                            pDue.setDate(pDue.getDate() + 1 + lag);
                            let minSuccStartStr = this.formatLocalDate(pDue);
                            if (succ.start_date < minSuccStartStr) {
                                return `Cannot reschedule in Isolated mode: Would violate dependency with successor '${succ.task_code}' (${succ.title}) starting on ${succ.start_date}. Switch to Ripple mode to shift downstream tasks automatically.`;
                            }
                        } else if (type === 'Start-to-Start' && succ.start_date) {
                            let pStart = this.parseLocalDate(proposedStart);
                            pStart.setDate(pStart.getDate() + lag);
                            let minSuccStartStr = this.formatLocalDate(pStart);
                            if (succ.start_date < minSuccStartStr) {
                                return `Cannot reschedule in Isolated mode: Would violate Start-to-Start dependency with '${succ.task_code}'. Switch to Ripple mode to shift downstream tasks automatically.`;
                            }
                        } else if (type === 'Finish-to-Finish' && succ.due_date) {
                            let pDue = this.parseLocalDate(proposedDue);
                            pDue.setDate(pDue.getDate() + lag);
                            let minSuccDueStr = this.formatLocalDate(pDue);
                            if (succ.due_date < minSuccDueStr) {
                                return `Cannot reschedule in Isolated mode: Would violate Finish-to-Finish dependency with '${succ.task_code}'. Switch to Ripple mode to shift downstream tasks automatically.`;
                            }
                        }
                    }
                }
            }

            // 3. In Ripple mode, safeguard against downstream completed tasks
            if (mode === 'ripple') {
                for (let link of this.schedule.links) {
                    let pId = link.predecessor_id ?? link.depends_on_task_id;
                    let sId = link.successor_id ?? link.task_id;
                    if (pId === task.id) {
                        let succ = taskMap[sId];
                        if (succ && (succ.is_completed || succ.status === 'Completed')) {
                            let type = link.type ?? link.dependency_type ?? 'Finish-to-Start';
                            let lag = parseInt(link.lag_days ?? 0, 10) || 0;
                            if (type === 'Finish-to-Start') {
                                let pDue = this.parseLocalDate(proposedDue);
                                pDue.setDate(pDue.getDate() + 1 + lag);
                                if (succ.start_date < this.formatLocalDate(pDue)) {
                                    return `Cannot ripple reschedule: Downstream task '${succ.task_code}' is already Completed and cannot be automatically rescheduled.`;
                                }
                            }
                        }
                    }
                }
            }

            return null; // Valid
        },

        recomputeConnectors() {
            if (!this.schedule || !this.schedule.links || !this.schedule.tasks) {
                this.connectorPaths = [];
                return;
            }

            let taskMap = {};
            this.schedule.tasks.forEach((t, idx) => {
                taskMap[t.id] = { task: t, rowIndex: idx };
            });

            let paths = [];
            const headerHeight = 48;
            const rowHeight = 44;

            this.schedule.links.forEach(link => {
                let pId = link.predecessor_id ?? link.depends_on_task_id;
                let sId = link.successor_id ?? link.task_id;
                let p = taskMap[pId];
                let s = taskMap[sId];
                if (!p || !s) return;

                let pY = headerHeight + (p.rowIndex * rowHeight) + (rowHeight / 2);
                let sY = headerHeight + (s.rowIndex * rowHeight) + (rowHeight / 2);

                let pStartPx = this.dateToPixels(p.task.start_date);
                let pDur = Math.max(1, this.getDaysDiff(p.task.start_date, p.task.due_date) + 1);
                let pWidthPx = (this.scale === 'day') ? (pDur * this.columnWidth) : (pDur / 7 * this.columnWidth);
                let pEndPx = pStartPx + pWidthPx;

                let sStartPx = this.dateToPixels(s.task.start_date);
                let sDur = Math.max(1, this.getDaysDiff(s.task.start_date, s.task.due_date) + 1);
                let sWidthPx = (this.scale === 'day') ? (sDur * this.columnWidth) : (sDur / 7 * this.columnWidth);
                let sEndPx = sStartPx + sWidthPx;

                let type = link.type ?? link.dependency_type ?? 'Finish-to-Start';
                let x1, y1, x2, y2, d;

                if (type === 'Finish-to-Start') {
                    x1 = pEndPx;
                    y1 = pY;
                    x2 = sStartPx;
                    y2 = sY;

                    if (x2 >= x1 + 10) {
                        let midX = (x1 + x2) / 2;
                        d = `M ${x1} ${y1} C ${midX} ${y1}, ${midX} ${y2}, ${x2} ${y2}`;
                    } else {
                        // Negative gap/lead or backward overlap
                        let loopX1 = x1 + 15;
                        let loopX2 = x2 - 15;
                        let midY = (y1 + y2) / 2;
                        d = `M ${x1} ${y1} H ${loopX1} V ${midY} H ${loopX2} V ${y2} H ${x2}`;
                    }
                } else if (type === 'Start-to-Start') {
                    x1 = pStartPx;
                    y1 = pY;
                    x2 = sStartPx;
                    y2 = sY;
                    let leftOffset = Math.min(x1, x2) - 20;
                    d = `M ${x1} ${y1} H ${leftOffset} V ${y2} H ${x2}`;
                } else if (type === 'Finish-to-Finish') {
                    x1 = pEndPx;
                    y1 = pY;
                    x2 = sEndPx;
                    y2 = sY;
                    let rightOffset = Math.max(x1, x2) + 20;
                    d = `M ${x1} ${y1} H ${rightOffset} V ${y2} H ${x2}`;
                } else if (type === 'Start-to-Finish') {
                    x1 = pStartPx;
                    y1 = pY;
                    x2 = sEndPx;
                    y2 = sY;
                    let midX = (x1 + x2) / 2;
                    d = `M ${x1} ${y1} C ${x1 - 20} ${y1}, ${x2 + 20} ${y2}, ${x2} ${y2}`;
                }

                paths.push({
                    d: d,
                    isCritical: Boolean((link.is_critical || (p.task.is_critical && s.task.is_critical)) && highlightCritical)
                });
            });

            this.connectorPaths = paths;
        },

        onCanvasScroll() {
            // Keep SVG and bars in sync
        },

        onDragStart(event, task) {
            if (!this.canUpdate) return;
            if (task.is_completed || task.status === 'Completed') {
                event.preventDefault();
                this.showToast('warning', 'Completed tasks are locked and cannot be rescheduled.');
                return;
            }
            this.dragTask = task;
            this.dragJustEnded = false;
            event.dataTransfer.setData('text/plain', task.id);
            event.dataTransfer.effectAllowed = 'move';
        },

        onDragEnd(event) {
            this.dragJustEnded = true;
            setTimeout(() => {
                this.dragJustEnded = false;
                this.dragTask = null;
            }, 250);
        },

        onBarClick(event, task) {
            event.stopPropagation();
            if (this.dragJustEnded) return;
            if (task.is_completed || task.status === 'Completed') {
                this.showToast('info', 'This task is already completed and its schedule is locked.');
                return;
            }
            this.openRescheduleModal(task);
        },

        onDropRow(event, task, rIdx) {
            if (!this.canUpdate || !this.dragTask) return;

            let rect = event.currentTarget.getBoundingClientRect();
            let dropX = event.clientX - rect.left;

            // Compute target date from pixel
            let dayOffset;
            if (this.scale === 'day') {
                dayOffset = Math.floor(dropX / this.columnWidth);
            } else if (this.scale === 'week') {
                dayOffset = Math.floor((dropX / this.columnWidth) * 7);
            } else {
                dayOffset = Math.floor((dropX / this.columnWidth) * 30.416);
            }

            let newStartDateObj = new Date(this.canvasStartDate);
            newStartDateObj.setDate(newStartDateObj.getDate() + dayOffset);

            let duration = Math.max(1, this.getDaysDiff(this.dragTask.start_date, this.dragTask.due_date) + 1);
            let newDueDateObj = new Date(newStartDateObj);
            newDueDateObj.setDate(newDueDateObj.getDate() + (duration - 1));

            let newStartDateStr = this.formatLocalDate(newStartDateObj);
            let newDueDateStr = this.formatLocalDate(newDueDateObj);

            if (newStartDateStr === this.dragTask.start_date && newDueDateStr === this.dragTask.due_date) {
                return;
            }

            // Prompt Confirmation Modal matching Production Dispatch Board
            this.promptDragConfirm(this.dragTask, newStartDateStr, newDueDateStr);
        },

        promptDragConfirm(task, proposedStart, proposedDue) {
            const titleEl = document.getElementById('dragTaskTitle');
            if (titleEl) titleEl.textContent = `${task.task_code} ${task.title}`;

            const curStartEl = document.getElementById('dragCurrentStartDisplay');
            if (curStartEl) curStartEl.textContent = task.start_date;

            const curDueEl = document.getElementById('dragCurrentDueDisplay');
            if (curDueEl) curDueEl.textContent = task.due_date;

            const newStartEl = document.getElementById('dragNewStartDisplay');
            if (newStartEl) newStartEl.textContent = proposedStart;

            const newDueEl = document.getElementById('dragNewDueDisplay');
            if (newDueEl) newDueEl.textContent = proposedDue;

            const durEl = document.getElementById('dragDurationDisplay');
            if (durEl) durEl.textContent = `${task.duration} ${task.duration === 1 ? '{{ __('projects.day') }}' : '{{ __('projects.days') }}'}`;

            const rippleRadio = document.getElementById('dragModeRipple');
            const isolatedRadio = document.getElementById('dragModeIsolated');
            const badgeEl = document.getElementById('dragShiftModeBadge');
            const helpTextEl = document.getElementById('dragModeHelpText');
            const valAlertEl = document.getElementById('dragValidationAlert');
            const valTextEl = document.getElementById('dragValidationText');

            const updateDragBadge = (mode) => {
                if (mode === 'isolated') {
                    if (badgeEl) {
                        badgeEl.textContent = '{{ __('projects.shift_mode_isolated') }}';
                        badgeEl.className = 'badge bg-soft-warning text-warning';
                    }
                    if (helpTextEl) helpTextEl.textContent = '{{ __('projects.isolated_mode_tooltip') }}';
                } else {
                    if (badgeEl) {
                        badgeEl.textContent = '{{ __('projects.shift_mode_ripple') }}';
                        badgeEl.className = 'badge bg-soft-info text-info';
                    }
                    if (helpTextEl) helpTextEl.textContent = '{{ __('projects.ripple_mode_tooltip') }}';
                }

                // Check validation
                const err = this.validateConstraints(task, proposedStart, proposedDue, mode);
                if (err && valAlertEl && valTextEl) {
                    valTextEl.textContent = err;
                    valAlertEl.classList.remove('d-none');
                } else if (valAlertEl) {
                    valAlertEl.classList.add('d-none');
                }
            };

            if (this.shiftMode === 'isolated') {
                if (isolatedRadio) isolatedRadio.checked = true;
                updateDragBadge('isolated');
            } else {
                if (rippleRadio) rippleRadio.checked = true;
                updateDragBadge('ripple');
            }

            if (rippleRadio) rippleRadio.onchange = () => updateDragBadge('ripple');
            if (isolatedRadio) isolatedRadio.onchange = () => updateDragBadge('isolated');

            const modalEl = document.getElementById('modalConfirmTaskDrag');
            if (!modalEl) return;
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();

            const btnApply = document.getElementById('btnApplyTaskDrag');
            const btnCancel = document.getElementById('btnCancelTaskDrag');

            const cleanup = () => {
                if (btnApply) btnApply.onclick = null;
                if (btnCancel) btnCancel.onclick = null;
            };

            if (btnCancel) {
                btnCancel.onclick = () => {
                    modal.hide();
                    cleanup();
                    this.dragTask = null;
                };
            }

            if (btnApply) {
                btnApply.onclick = () => {
                    const selectedMode = document.querySelector('input[name="dragShiftModeChoice"]:checked')?.value || this.shiftMode;
                    const valErr = this.validateConstraints(task, proposedStart, proposedDue, selectedMode);
                    if (valErr) {
                        this.showToast('error', valErr);
                        return;
                    }
                    modal.hide();
                    cleanup();
                    this.submitReschedule(task, proposedStart, proposedDue, selectedMode);
                };
            }
        },

        openRescheduleModal(task) {
            if (!this.canUpdate) return;
            if (task.is_completed || task.status === 'Completed') {
                this.showToast('warning', 'Completed tasks cannot be rescheduled.');
                return;
            }

            const codeEl = document.getElementById('rescheduleTaskCode');
            if (codeEl) codeEl.textContent = task.task_code;

            const titleEl = document.getElementById('rescheduleTaskTitle');
            if (titleEl) titleEl.textContent = task.title;

            const durText = document.getElementById('rescheduleDurationText');
            if (durText) durText.textContent = `${task.duration} ${task.duration === 1 ? '{{ __('projects.day') }}' : '{{ __('projects.days') }}'}`;

            const floatText = document.getElementById('rescheduleFloatText');
            if (floatText) floatText.textContent = `${task.total_float}d (Free: ${task.free_float}d)`;

            const critBadge = document.getElementById('rescheduleCriticalBadge');
            if (critBadge) {
                if (task.is_critical) {
                    critBadge.textContent = '0d Critical Path';
                    critBadge.className = 'badge bg-danger text-white fs-11';
                } else {
                    critBadge.textContent = `Float: +${task.total_float}d`;
                    critBadge.className = 'badge bg-soft-secondary text-secondary fs-11';
                }
            }

            const startInput = document.getElementById('rescheduleStartDateInput');
            if (startInput) startInput.value = task.start_date;

            const dueInput = document.getElementById('rescheduleDueDateInput');
            if (dueInput) dueInput.value = task.due_date;

            const rippleRadio = document.getElementById('rescheduleModeRipple');
            const isolatedRadio = document.getElementById('rescheduleModeIsolated');
            const resHelpText = document.getElementById('rescheduleModeHelpText');
            const valAlertEl = document.getElementById('rescheduleValidationAlert');
            const valTextEl = document.getElementById('rescheduleValidationText');

            const validateModalInputs = () => {
                const sVal = startInput?.value;
                const dVal = dueInput?.value;
                const modeVal = document.querySelector('input[name="rescheduleShiftModeChoice"]:checked')?.value || 'ripple';
                if (!sVal || !dVal) {
                    if (valAlertEl) valAlertEl.classList.add('d-none');
                    return;
                }
                const err = this.validateConstraints(task, sVal, dVal, modeVal);
                if (err && valAlertEl && valTextEl) {
                    valTextEl.textContent = err;
                    valAlertEl.classList.remove('d-none');
                } else if (valAlertEl) {
                    valAlertEl.classList.add('d-none');
                }
            };

            if (startInput && dueInput) {
                startInput.onchange = () => {
                    if (startInput.value) {
                        const sDate = this.parseLocalDate(startInput.value);
                        sDate.setDate(sDate.getDate() + (task.duration - 1));
                        dueInput.value = this.formatLocalDate(sDate);
                    }
                    validateModalInputs();
                };
                dueInput.onchange = () => {
                    validateModalInputs();
                };
            }

            const updateResHelp = (mode) => {
                if (resHelpText) {
                    resHelpText.textContent = (mode === 'isolated') ? '{{ __('projects.isolated_mode_tooltip') }}' : '{{ __('projects.ripple_mode_tooltip') }}';
                }
                validateModalInputs();
            };

            if (this.shiftMode === 'isolated') {
                if (isolatedRadio) isolatedRadio.checked = true;
                updateResHelp('isolated');
            } else {
                if (rippleRadio) rippleRadio.checked = true;
                updateResHelp('ripple');
            }

            if (rippleRadio) rippleRadio.onchange = () => updateResHelp('ripple');
            if (isolatedRadio) isolatedRadio.onchange = () => updateResHelp('isolated');

            // Reset validation alert
            if (valAlertEl) valAlertEl.classList.add('d-none');

            const modalEl = document.getElementById('modalRescheduleTask');
            if (!modalEl) return;
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();

            const btnApply = document.getElementById('btnApplyTaskReschedule');
            if (btnApply) {
                btnApply.onclick = () => {
                    const newStart = startInput?.value;
                    const newDue = dueInput?.value;
                    if (!newStart || !newDue) {
                        this.showToast('error', 'Both start date and due date are required.');
                        return;
                    }
                    const selectedMode = document.querySelector('input[name="rescheduleShiftModeChoice"]:checked')?.value || this.shiftMode;
                    const valErr = this.validateConstraints(task, newStart, newDue, selectedMode);
                    if (valErr) {
                        this.showToast('error', valErr);
                        return;
                    }
                    modal.hide();
                    btnApply.onclick = null;
                    this.submitReschedule(task, newStart, newDue, selectedMode);
                };
            }
        },

        submitReschedule(task, newStartDate, newDueDate, mode) {
            this.loading = true;

            let url = `${this.rescheduleBaseUrl}/${task.id}/reschedule`;

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    start_date: newStartDate,
                    due_date: newDueDate,
                    shift_mode: mode
                })
            })
            .then(async res => {
                let data = await res.json();
                if (!res.ok) {
                    let msg = data.message || 'Rescheduling failed.';
                    if (data.errors) {
                        let detail = Object.values(data.errors).flat().join(' ');
                        if (detail) msg += ' ' + detail;
                    }
                    throw new Error(msg);
                }
                return data;
            })
            .then(data => {
                this.showToast('success', data.message || this.translations.taskRescheduled);
                this.loadSchedule();
            })
            .catch(err => {
                this.showToast('error', err.message || this.translations.rescheduleFailed);
                this.loading = false;
            });
        }
    }));
});
</script>
@endpush

<style>
.gantt-board-wrapper .odoo-form-group {
    margin-bottom: 0 !important;
    display: flex !important;
    align-items: center !important;
}
.gantt-board-wrapper .odoo-form-label {
    width: auto !important;
    margin-right: 8px !important;
    margin-bottom: 0 !important;
    white-space: nowrap !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    display: inline-flex !important;
    align-items: center !important;
}
.gantt-board-wrapper .odoo-form-control {
    padding: 2px 8px !important;
    font-size: 12px !important;
    height: 30px !important;
}
.gantt-board-wrapper .form-check,
.gantt-board-wrapper .gantt-radio-wrap {
    display: inline-flex !important;
    align-items: center !important;
    margin-bottom: 0 !important;
    padding-left: 0 !important;
    min-height: auto !important;
}
.gantt-board-wrapper .form-check-input {
    float: none !important;
    margin: 0 4px 0 0 !important;
    vertical-align: middle !important;
    flex-shrink: 0 !important;
    position: relative !important;
}
.gantt-board-wrapper .form-check-label {
    margin-bottom: 0 !important;
    line-height: 1 !important;
    display: inline-flex !important;
    align-items: center !important;
    user-select: none !important;
    cursor: pointer !important;
    font-size: 12px !important;
}
.gantt-board-wrapper .gantt-bar-item {
    cursor: pointer;
    user-select: none;
    transition: box-shadow 0.15s ease, filter 0.15s ease;
}
.gantt-board-wrapper .gantt-bar-item[draggable="true"] {
    cursor: grab;
}
.gantt-board-wrapper .gantt-bar-item:active {
    cursor: grabbing;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25) !important;
}
.gantt-board-wrapper .gantt-bar-item:hover {
    filter: brightness(1.08);
}
.gantt-board-wrapper .gantt-task-row {
    cursor: pointer;
    transition: background-color 0.15s ease;
}
.gantt-board-wrapper .gantt-task-row:hover {
    background-color: #f1f5f9;
}
</style>
