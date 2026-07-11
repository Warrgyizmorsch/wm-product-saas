@extends('layouts.duralux')

@section('title', 'Production Track Status | SaaS ERP')
@section('page-title', 'Production Track Status')
@section('breadcrumb', 'Track Status')

@section('page-actions')
    <span class="badge bg-soft-success text-success p-2 me-2">
        <i class="feather-info me-1"></i> Static Array Mode
    </span>
    <a href="{{ route('production.mes.dashboard') }}" class="btn btn-light btn-sm border">
        <i class="feather-arrow-left me-1"></i> Back to Dashboard
    </a>
@endsection

@php
    /**
     * Helper list of checklist tasks.
     * These will be pre-populated for each tab.
     */
    $checklistTasks = [
        [
            'title' => 'CRUD',
            'desc' => 'Implement Create, Read, Update, and Delete operations.',
            'status' => 'Pending',
        ],
        [
            'title' => 'Toasters',
            'desc' => 'Add toast notifications for success, warning, and error alerts.',
            'status' => 'Pending',
        ],
        [
            'title' => 'Validations',
            'desc' => 'Set up frontend and backend request validations for forms.',
            'status' => 'Pending',
        ],
        [
            'title' => 'Filters (POST)',
            'desc' => 'Implement advanced filtering capabilities using POST requests.',
            'status' => 'Pending',
        ],
        [
            'title' => 'Pagination/Lazy loading',
            'desc' => 'Configure pagination or lazy loading for heavy data grids.',
            'status' => 'Pending',
        ],
        [
            'title' => 'Search Filter',
            'desc' => 'Add live search filtering on listings and tables.',
            'status' => 'Pending',
        ],
        [
            'title' => 'Sorting (asc/dec)',
            'desc' => 'Allow sorting columns in ascending and descending order.',
            'status' => 'Pending',
        ],
        [
            'title' => 'Import File',
            'desc' => 'Enable importing records from CSV / Excel templates.',
            'status' => 'Pending',
        ],
        [
            'title' => 'Export File',
            'desc' => 'Enable exporting records to CSV / Excel formats.',
            'status' => 'Pending',
        ],
        [
            'title' => 'Soft Delete',
            'desc' => 'Support soft deleting records with restore capability.',
            'status' => 'Pending',
        ],
        [
            'title' => 'Using Common Elements',
            'desc' => 'Utilize global blade layout components, icons, and styling helper classes.',
            'status' => 'Pending',
        ],
        [
            'title' => 'If File Upload Then Preview Feature',
            'desc' => 'Add dynamic image/document preview for uploads.',
            'status' => 'Pending',
        ],
        [
            'title' => 'Seeders',
            'desc' => 'Create database model seeders with rich sample demo data.',
            'status' => 'Pending',
        ],
        [
            'title' => 'Support Multi Language/Currency',
            'desc' => 'Support multiple languages (translations) and local currency formats.',
            'status' => 'Pending',
        ],
        [
            'title' => 'Approvals',
            'desc' => 'Implement verification/approval workflows (draft -> approve -> reject).',
            'status' => 'Pending',
        ],
    ];

    /**
     * Map the checklist to each of the 12 actual Production Sub-Modules.
     * Calculated based on structural analysis of the Laravel project domain.
     * Allowed statuses: 'Pending', 'Developer Complete', 'Internal Testing Complete', 'External Testing Complete', 'Rework', 'Complete'
     */
    $tabData = [
        'Bill of Materials (BOM)' => [
            array_merge($checklistTasks[0], ['status' => 'Complete']),
            array_merge($checklistTasks[1], ['status' => 'Complete']),
            array_merge($checklistTasks[2], ['status' => 'Complete']),
            array_merge($checklistTasks[3], ['status' => 'Complete']),
            array_merge($checklistTasks[4], ['status' => 'Complete']),
            array_merge($checklistTasks[5], ['status' => 'Complete']),
            array_merge($checklistTasks[6], ['status' => 'Complete']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Pending']),
            array_merge($checklistTasks[9], ['status' => 'Complete']),
            array_merge($checklistTasks[10], ['status' => 'Complete']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Complete']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Complete']),
        ],
        'Routing' => [
            array_merge($checklistTasks[0], ['status' => 'Complete']),
            array_merge($checklistTasks[1], ['status' => 'Complete']),
            array_merge($checklistTasks[2], ['status' => 'Complete']),
            array_merge($checklistTasks[3], ['status' => 'Complete']),
            array_merge($checklistTasks[4], ['status' => 'Complete']),
            array_merge($checklistTasks[5], ['status' => 'Complete']),
            array_merge($checklistTasks[6], ['status' => 'Complete']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Pending']),
            array_merge($checklistTasks[9], ['status' => 'Complete']),
            array_merge($checklistTasks[10], ['status' => 'Complete']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Complete']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Complete']),
        ],
        'Work Centers' => [
            array_merge($checklistTasks[0], ['status' => 'Complete']),
            array_merge($checklistTasks[1], ['status' => 'Complete']),
            array_merge($checklistTasks[2], ['status' => 'Complete']),
            array_merge($checklistTasks[3], ['status' => 'Complete']),
            array_merge($checklistTasks[4], ['status' => 'Complete']),
            array_merge($checklistTasks[5], ['status' => 'Complete']),
            array_merge($checklistTasks[6], ['status' => 'Complete']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Pending']),
            array_merge($checklistTasks[9], ['status' => 'Complete']),
            array_merge($checklistTasks[10], ['status' => 'Complete']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Complete']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Pending']),
        ],
        'Machines' => [
            array_merge($checklistTasks[0], ['status' => 'Complete']),
            array_merge($checklistTasks[1], ['status' => 'Complete']),
            array_merge($checklistTasks[2], ['status' => 'Complete']),
            array_merge($checklistTasks[3], ['status' => 'Complete']),
            array_merge($checklistTasks[4], ['status' => 'Complete']),
            array_merge($checklistTasks[5], ['status' => 'Complete']),
            array_merge($checklistTasks[6], ['status' => 'Complete']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Pending']),
            array_merge($checklistTasks[9], ['status' => 'Complete']),
            array_merge($checklistTasks[10], ['status' => 'Complete']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Complete']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Pending']),
        ],
        'Production Planning' => [
            array_merge($checklistTasks[0], ['status' => 'Complete']),
            array_merge($checklistTasks[1], ['status' => 'Complete']),
            array_merge($checklistTasks[2], ['status' => 'Complete']),
            array_merge($checklistTasks[3], ['status' => 'Complete']),
            array_merge($checklistTasks[4], ['status' => 'Complete']),
            array_merge($checklistTasks[5], ['status' => 'Complete']),
            array_merge($checklistTasks[6], ['status' => 'Complete']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Pending']),
            array_merge($checklistTasks[9], ['status' => 'Complete']),
            array_merge($checklistTasks[10], ['status' => 'Complete']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Complete']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Complete']),
        ],
        'Production Scheduling' => [
            array_merge($checklistTasks[0], ['status' => 'Complete']),
            array_merge($checklistTasks[1], ['status' => 'Complete']),
            array_merge($checklistTasks[2], ['status' => 'Complete']),
            array_merge($checklistTasks[3], ['status' => 'Complete']),
            array_merge($checklistTasks[4], ['status' => 'Complete']),
            array_merge($checklistTasks[5], ['status' => 'Complete']),
            array_merge($checklistTasks[6], ['status' => 'Complete']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Pending']),
            array_merge($checklistTasks[9], ['status' => 'Complete']),
            array_merge($checklistTasks[10], ['status' => 'Complete']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Complete']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Complete']),
        ],
        'Shifts & Calendars' => [
            array_merge($checklistTasks[0], ['status' => 'Complete']),
            array_merge($checklistTasks[1], ['status' => 'Complete']),
            array_merge($checklistTasks[2], ['status' => 'Complete']),
            array_merge($checklistTasks[3], ['status' => 'Complete']),
            array_merge($checklistTasks[4], ['status' => 'Complete']),
            array_merge($checklistTasks[5], ['status' => 'Complete']),
            array_merge($checklistTasks[6], ['status' => 'Complete']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Pending']),
            array_merge($checklistTasks[9], ['status' => 'Complete']),
            array_merge($checklistTasks[10], ['status' => 'Complete']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Complete']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Pending']),
        ],
        'Quality Plans & Operator Skills' => [
            array_merge($checklistTasks[0], ['status' => 'Complete']),
            array_merge($checklistTasks[1], ['status' => 'Complete']),
            array_merge($checklistTasks[2], ['status' => 'Complete']),
            array_merge($checklistTasks[3], ['status' => 'Complete']),
            array_merge($checklistTasks[4], ['status' => 'Complete']),
            array_merge($checklistTasks[5], ['status' => 'Complete']),
            array_merge($checklistTasks[6], ['status' => 'Complete']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Pending']),
            array_merge($checklistTasks[9], ['status' => 'Complete']),
            array_merge($checklistTasks[10], ['status' => 'Complete']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Complete']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Pending']),
        ],
        'Production Orders' => [
            array_merge($checklistTasks[0], ['status' => 'Complete']),
            array_merge($checklistTasks[1], ['status' => 'Complete']),
            array_merge($checklistTasks[2], ['status' => 'Complete']),
            array_merge($checklistTasks[3], ['status' => 'Complete']),
            array_merge($checklistTasks[4], ['status' => 'Complete']),
            array_merge($checklistTasks[5], ['status' => 'Complete']),
            array_merge($checklistTasks[6], ['status' => 'Complete']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Pending']),
            array_merge($checklistTasks[9], ['status' => 'Complete']),
            array_merge($checklistTasks[10], ['status' => 'Complete']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Complete']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Complete']),
        ],
        'Shop Floor (MES)' => [
            array_merge($checklistTasks[0], ['status' => 'Complete']),
            array_merge($checklistTasks[1], ['status' => 'Complete']),
            array_merge($checklistTasks[2], ['status' => 'Complete']),
            array_merge($checklistTasks[3], ['status' => 'Complete']),
            array_merge($checklistTasks[4], ['status' => 'Complete']),
            array_merge($checklistTasks[5], ['status' => 'Complete']),
            array_merge($checklistTasks[6], ['status' => 'Complete']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Complete']), // Export Scan Logs
            array_merge($checklistTasks[9], ['status' => 'Complete']),
            array_merge($checklistTasks[10], ['status' => 'Complete']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Complete']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Complete']),
        ],
        'Quality Management' => [
            array_merge($checklistTasks[0], ['status' => 'Complete']),
            array_merge($checklistTasks[1], ['status' => 'Complete']),
            array_merge($checklistTasks[2], ['status' => 'Complete']),
            array_merge($checklistTasks[3], ['status' => 'Complete']),
            array_merge($checklistTasks[4], ['status' => 'Complete']),
            array_merge($checklistTasks[5], ['status' => 'Complete']),
            array_merge($checklistTasks[6], ['status' => 'Complete']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Pending']),
            array_merge($checklistTasks[9], ['status' => 'Complete']),
            array_merge($checklistTasks[10], ['status' => 'Complete']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Complete']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Complete']),
        ],
        'Manufacturing Intelligence' => [
            array_merge($checklistTasks[0], ['status' => 'Complete']),
            array_merge($checklistTasks[1], ['status' => 'Complete']),
            array_merge($checklistTasks[2], ['status' => 'Complete']),
            array_merge($checklistTasks[3], ['status' => 'Complete']),
            array_merge($checklistTasks[4], ['status' => 'Complete']),
            array_merge($checklistTasks[5], ['status' => 'Complete']),
            array_merge($checklistTasks[6], ['status' => 'Complete']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Pending']),
            array_merge($checklistTasks[9], ['status' => 'Complete']),
            array_merge($checklistTasks[10], ['status' => 'Complete']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Complete']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Complete']),
        ],
    ];

    // Compute Overall Statistics
    $totalCompletedTasks = 0;
    $totalTasksCount = 0;
    $moduleCompletionRates = [];
    $allowedCompleteStatuses = ['Complete', 'Developer Complete', 'Internal Testing Complete', 'External Testing Complete'];
    
    foreach ($tabData as $modName => $tasks) {
        $completedCount = 0;
        $moduleTasksCount = count($tasks);
        foreach ($tasks as $t) {
            if (in_array($t['status'], $allowedCompleteStatuses)) {
                $completedCount++;
            }
        }
        $rate = $moduleTasksCount > 0 ? round(($completedCount / $moduleTasksCount) * 100) : 0;
        $moduleCompletionRates[$modName] = $rate;
        $totalCompletedTasks += $completedCount;
        $totalTasksCount += $moduleTasksCount;
    }
    
    $overallCompletionRate = $totalTasksCount > 0 ? round(($totalCompletedTasks / $totalTasksCount) * 100) : 0;
    
    $activeModulesCount = count(array_filter($moduleCompletionRates, function($rate) {
        return $rate >= 70;
    }));
@endphp

@section('content')
    <!-- Production Overview Dashboard -->
    <div class="row g-4 mb-4">
        <!-- Overall Progress Card -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);">
                <div class="card-body p-4 text-white">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-uppercase text-white-50 fw-bold fs-12 mb-0">Overall Completion</h6>
                        <div class="bg-white bg-opacity-20 p-2 rounded-circle">
                            <i class="feather-trending-up text-white fs-18"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline mb-2">
                        <h2 class="fw-bold mb-0 text-white display-6">{{ $overallCompletionRate }}%</h2>
                    </div>
                    <div class="progress bg-white bg-opacity-20" style="height: 6px;">
                        <div class="progress-bar bg-white" role="progressbar" style="width: {{ $overallCompletionRate }}%;" aria-valuenow="{{ $overallCompletionRate }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-white-50 mt-2 d-block">Average across all 12 sub-modules</small>
                </div>
            </div>
        </div>

        <!-- Task Checklist Card -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 text-dark">
                        <h6 class="text-uppercase text-muted fw-bold fs-12 mb-0">Checklist Tasks</h6>
                        <div class="bg-soft-primary p-2 rounded-circle text-primary">
                            <i class="feather-check-square fs-18"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline mb-2">
                        <h2 class="fw-bold text-dark mb-0 display-6">{{ $totalCompletedTasks }} <span class="fs-18 text-muted">/ {{ $totalTasksCount }}</span></h2>
                    </div>
                    <div class="progress bg-light" style="height: 6px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: {{ ($totalCompletedTasks / $totalTasksCount) * 100 }}%;" aria-valuenow="{{ $totalCompletedTasks }}" aria-valuemin="0" aria-valuemax="{{ $totalTasksCount }}"></div>
                    </div>
                    <small class="text-muted mt-2 d-block">Task completeness ratio</small>
                </div>
            </div>
        </div>

        <!-- Sub-Modules Card -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 text-dark">
                        <h6 class="text-uppercase text-muted fw-bold fs-12 mb-0">Sub-Modules Status</h6>
                        <div class="bg-soft-success p-2 rounded-circle text-success">
                            <i class="feather-layers fs-18"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline mb-2">
                        <h2 class="fw-bold text-dark mb-0 display-6">{{ $activeModulesCount }} <span class="fs-18 text-muted">/ 12 Active</span></h2>
                    </div>
                    <div class="progress bg-light" style="height: 6px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ ($activeModulesCount / 12) * 100 }}%;" aria-valuenow="{{ $activeModulesCount }}" aria-valuemin="0" aria-valuemax="12"></div>
                    </div>
                    <small class="text-muted mt-2 d-block">Sub-modules with &ge; 70% completion</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Task List Card with Tabs -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Production Module Task Status Tracker</h5>
        </div>
        <div class="card-body p-0">
            <!-- Tab Navigation -->
            <ul class="nav nav-tabs erp-horizontal-tabs px-4 pt-3" id="trackStatusTabs" role="tablist" style="overflow-x: auto; flex-wrap: nowrap; -webkit-overflow-scrolling: touch;">
                @php $isFirst = true; @endphp
                @foreach ($tabData as $tabName => $tasks)
                    @php 
                        $tabId = Str::slug($tabName); 
                        $rate = $moduleCompletionRates[$tabName];
                        $badgeClass = 'bg-soft-primary text-primary';
                        if ($rate >= 80) $badgeClass = 'bg-soft-success text-success';
                        elseif ($rate >= 70) $badgeClass = 'bg-soft-info text-info';
                        elseif ($rate >= 50) $badgeClass = 'bg-soft-warning text-warning';
                    @endphp
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $isFirst ? 'active' : '' }} text-nowrap" 
                                id="{{ $tabId }}-tab" 
                                data-bs-toggle="tab" 
                                data-bs-target="#{{ $tabId }}" 
                                type="button" 
                                role="tab" 
                                aria-controls="{{ $tabId }}" 
                                aria-selected="{{ $isFirst ? 'true' : 'false' }}">
                            {{ $tabName }}
                            <span class="badge {{ $badgeClass }} ms-2 fs-10 px-2 py-1 rounded-pill">{{ $rate }}%</span>
                        </button>
                    </li>
                    @php $isFirst = false; @endphp
                @endforeach
            </ul>

            <!-- Tab Content (Tables) -->
            <div class="tab-content" id="trackStatusTabContent">
                @php $isFirst = true; @endphp
                @foreach ($tabData as $tabName => $tasks)
                    @php 
                        $tabId = Str::slug($tabName); 
                        $rate = $moduleCompletionRates[$tabName];
                    @endphp
                    <div class="tab-pane fade {{ $isFirst ? 'show active' : '' }}" 
                         id="{{ $tabId }}" 
                         role="tabpanel" 
                         aria-labelledby="{{ $tabId }}-tab">
                        <div class="px-4 py-3 bg-light bg-opacity-50 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <span class="fw-bold text-dark fs-14">{{ $tabName }} Progress</span>
                                <div class="progress" style="width: 150px; height: 6px; margin-bottom: 0;">
                                    <div class="progress-bar {{ $rate >= 80 ? 'bg-success' : ($rate >= 70 ? 'bg-info' : 'bg-warning') }}" role="progressbar" style="width: {{ $rate }}%;" aria-valuenow="{{ $rate }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="text-muted fs-12">{{ $rate }}% Completed ({{ count(array_filter($tasks, function($t) use ($allowedCompleteStatuses) { return in_array($t['status'], $allowedCompleteStatuses); })) }}/{{ count($tasks) }} tasks)</span>
                            </div>
                            <span class="badge bg-soft-primary text-primary px-3 py-1 fs-12">Production Module</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle table-hover">
                                <thead class="table-light fs-11 text-uppercase text-muted">
                                    <tr>
                                        <th class="ps-4">Description</th>
                                        <th class="text-end pe-4" style="width: 250px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13 text-dark">
                                    @forelse ($tasks as $task)
                                        @php
                                            // Determine initial class based on status
                                            $rowClass = 'status-pending';
                                            if ($task['status'] === 'Developer Complete') $rowClass = 'status-dev-complete';
                                            elseif ($task['status'] === 'Internal Testing Complete') $rowClass = 'status-internal-test-complete';
                                            elseif ($task['status'] === 'External Testing Complete') $rowClass = 'status-external-test-complete';
                                            elseif ($task['status'] === 'Rework') $rowClass = 'status-rework';
                                            elseif ($task['status'] === 'Complete') $rowClass = 'status-complete';
                                        @endphp
                                        <tr class="{{ $rowClass }}">
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="fw-bold text-dark task-title">
                                                        {{ $task['title'] }}
                                                    </div>
                                                </div>
                                                <small class="fs-12 text-muted task-desc">{{ $task['desc'] }}</small>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="form-group select-wd-lg mb-0 ms-auto" style="width: 230px;">
                                                    <select class="form-control status-select" data-select2-selector="icon">
                                                        <option value="Pending" data-icon="feather-clock" {{ $task['status'] === 'Pending' ? 'selected' : '' }}>Pending</option>
                                                        <option value="Developer Complete" data-icon="feather-code" {{ $task['status'] === 'Developer Complete' ? 'selected' : '' }}>Developer Complete</option>
                                                        <option value="Internal Testing Complete" data-icon="feather-check" {{ $task['status'] === 'Internal Testing Complete' ? 'selected' : '' }}>Internal Testing Complete</option>
                                                        <option value="External Testing Complete" data-icon="feather-shield" {{ $task['status'] === 'External Testing Complete' ? 'selected' : '' }}>External Testing Complete</option>
                                                        <option value="Rework" data-icon="feather-rotate-ccw" {{ $task['status'] === 'Rework' ? 'selected' : '' }}>Rework</option>
                                                        <option value="Complete" data-icon="feather-check-circle" {{ $task['status'] === 'Complete' ? 'selected' : '' }}>Complete</option>
                                                    </select>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-center py-4 text-muted">No tasks defined for {{ $tabName }}.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @php $isFirst = false; @endphp
                @endforeach
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <!-- Select2 Theme Styles -->
    <link class="select2-css" rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link class="select2-css" rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Horizontal custom tab navigation styling */
        .erp-horizontal-tabs {
            border-bottom: 2px solid #e2e8f0;
            gap: 8px;
        }
        .erp-horizontal-tabs .nav-item {
            margin-bottom: -2px;
        }
        .erp-horizontal-tabs .nav-link {
            border: none !important;
            border-bottom: 3px solid transparent !important;
            background: transparent !important;
            color: #64748b !important;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 16px;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            border-top-left-radius: 4px;
            border-top-right-radius: 4px;
        }
        .erp-horizontal-tabs .nav-link:hover {
            color: var(--bs-primary) !important;
            border-bottom-color: #cbd5e1 !important;
        }
        .erp-horizontal-tabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom-color: var(--bs-primary) !important;
            font-weight: 700;
        }

        /* ----------------------------------------------------
           TRANSITIONAL LIFE-CYCLE STYLINGS (ROW / TEXT)
        ---------------------------------------------------- */
        
        .task-title, .task-desc {
            transition: all 0.35s ease;
        }

        /* 1. Pending Style */
        .status-pending {
            border-left: 3px solid transparent;
            transition: all 0.35s ease;
        }

        /* 2. Developer Complete (Grey Cross-out) */
        .status-dev-complete {
            border-left: 3px solid #64748b;
            transition: all 0.35s ease;
        }
        .status-dev-complete .task-title {
            text-decoration: line-through !important;
            text-decoration-color: #64748b !important;
            color: #64748b !important;
        }
        .status-dev-complete .task-desc {
            text-decoration: line-through !important;
            text-decoration-color: #94a3b8 !important;
            color: #94a3b8 !important;
        }

        /* 3. Internal Testing Complete (Green Cross-out) */
        .status-internal-test-complete {
            background-color: rgba(34, 197, 94, 0.02);
            border-left: 3px solid #22c55e;
            transition: all 0.35s ease;
        }
        .status-internal-test-complete .task-title {
            text-decoration: line-through !important;
            text-decoration-color: #22c55e !important;
            color: #166534 !important;
        }
        .status-internal-test-complete .task-desc {
            text-decoration: line-through !important;
            text-decoration-color: #4ade80 !important;
            color: #15803d !important;
        }

        /* 4. External Testing Complete (Blue/Teal Cross-out) */
        .status-external-test-complete {
            background-color: rgba(6, 182, 212, 0.02);
            border-left: 3px solid #06b6d4;
            transition: all 0.35s ease;
        }
        .status-external-test-complete .task-title {
            text-decoration: line-through !important;
            text-decoration-color: #06b6d4 !important;
            color: #0369a1 !important;
        }
        .status-external-test-complete .task-desc {
            text-decoration: line-through !important;
            text-decoration-color: #22d3ee !important;
            color: #0e7490 !important;
        }

        /* 5. Rework (Soft Red Highlights, Warning text, NO Cross-out) */
        .status-rework {
            background-color: rgba(239, 68, 68, 0.04);
            border-left: 3px solid #ef4444;
            transition: all 0.35s ease;
        }
        .status-rework .task-title {
            color: #991b1b !important;
            font-weight: 800 !important;
        }
        .status-rework .task-desc {
            color: #b91c1c !important;
        }

        /* 6. Complete (Final Green double/single line, light muted opacity) */
        .status-complete {
            background-color: rgba(16, 185, 129, 0.01);
            border-left: 3px solid #10b981;
            opacity: 0.55;
            transition: all 0.35s ease;
        }
        .status-complete .task-title {
            text-decoration: line-through !important;
            text-decoration-color: #10b981 !important;
            color: #94a3b8 !important;
        }
        .status-complete .task-desc {
            text-decoration: line-through !important;
            text-decoration-color: #a7f3d0 !important;
            color: #cbd5e1 !important;
        }
        
        .select-wd-lg .select2-container {
            width: 100% !important;
        }
    </style>
@endpush

@push('scripts')
    <!-- Select2 Scripts -->
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(function () {
            // Handle status change
            $(document).on('change', '.status-select', function() {
                var status = $(this).val();
                var row = $(this).closest('tr');
                
                // Clear all custom classes
                row.removeClass('status-pending status-dev-complete status-internal-test-complete status-external-test-complete status-rework status-complete');
                
                // Map select option values to corresponding classes
                if (status === 'Developer Complete') {
                    row.addClass('status-dev-complete');
                } else if (status === 'Internal Testing Complete') {
                    row.addClass('status-internal-test-complete');
                } else if (status === 'External Testing Complete') {
                    row.addClass('status-external-test-complete');
                } else if (status === 'Rework') {
                    row.addClass('status-rework');
                } else if (status === 'Complete') {
                    row.addClass('status-complete');
                } else {
                    row.addClass('status-pending');
                }
            });
        });
    </script>
@endpush
