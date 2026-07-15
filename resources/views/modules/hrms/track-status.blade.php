@extends('layouts.duralux')

@section('title', __('hrms.track_status.title') . ' | SaaS ERP')
@section('page-title', __('hrms.track_status.title'))
@section('breadcrumb', __('hrms.track_status.title'))

@section('page-actions')
    <x-ui.badge variant="success" soft class="p-2 me-2">
        <i class="feather-info me-1"></i> Static Array Mode
    </x-ui.badge>
    <x-ui.button href="{{ route('crm.leads.index') }}" variant="light" icon="feather-arrow-left">
        {{ __('hrms.track_status.back') }}
    </x-ui.button>
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
     * Map the checklist to each of the tabs (Lead, Quotation, Invoices).
     * You can edit individual status values here.
     * Allowed statuses: 'Pending', 'Developer Complete', 'Internal Testing Complete', 'External Testing Complete', 'Rework', 'Complete'
     */
    $tabData = [
        'Organization Structure' => [
            array_merge($checklistTasks[0], ['status' => 'Pending']),                  // CRUD
            array_merge($checklistTasks[1], ['status' => 'Pending']),        // Toasters
            array_merge($checklistTasks[2], ['status' => 'Pending']), // Validations
            array_merge($checklistTasks[3], ['status' => 'Pending']), // Filters
            array_merge($checklistTasks[4], ['status' => 'Pending']),                    // Pagination
            array_merge($checklistTasks[5], ['status' => 'Pending']),                   // Search Filter
            array_merge($checklistTasks[6], ['status' => 'Pending']),                   // Sorting
            array_merge($checklistTasks[7], ['status' => 'Pending']),                   // Import
            array_merge($checklistTasks[8], ['status' => 'Pending']),                   // Export
            array_merge($checklistTasks[9], ['status' => 'Pending']),                   // Soft Delete
            array_merge($checklistTasks[10], ['status' => 'Pending']),                 // Using Common Elements
            array_merge($checklistTasks[11], ['status' => 'Pending']),                  // File Preview
            array_merge($checklistTasks[12], ['status' => 'Pending']),                 // Seeders
            array_merge($checklistTasks[13], ['status' => 'Pending']),                  // Multi Language/Currency
            array_merge($checklistTasks[14], ['status' => 'Pending']),                  // Approvals
        ],
        'Leave Structure' => [
            array_merge($checklistTasks[0], ['status' => 'Pending']),
            array_merge($checklistTasks[1], ['status' => 'Pending']),
            array_merge($checklistTasks[2], ['status' => 'Pending']),
            array_merge($checklistTasks[3], ['status' => 'Pending']),
            array_merge($checklistTasks[4], ['status' => 'Pending']),
            array_merge($checklistTasks[5], ['status' => 'Pending']),
            array_merge($checklistTasks[6], ['status' => 'Pending']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Pending']),
            array_merge($checklistTasks[9], ['status' => 'Pending']),
            array_merge($checklistTasks[10], ['status' => 'Pending']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Pending']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Pending']),
        ],
        'Salary Structure' => [
            array_merge($checklistTasks[0], ['status' => 'Pending']),
            array_merge($checklistTasks[1], ['status' => 'Pending']),
            array_merge($checklistTasks[2], ['status' => 'Pending']),
            array_merge($checklistTasks[3], ['status' => 'Pending']),
            array_merge($checklistTasks[4], ['status' => 'Pending']),
            array_merge($checklistTasks[5], ['status' => 'Pending']),
            array_merge($checklistTasks[6], ['status' => 'Pending']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Pending']),
            array_merge($checklistTasks[9], ['status' => 'Pending']),
            array_merge($checklistTasks[10], ['status' => 'Pending']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Pending']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Pending']),
        ],
        'Penalization Policy' => [
            array_merge($checklistTasks[0], ['status' => 'Pending']),
            array_merge($checklistTasks[1], ['status' => 'Pending']),
            array_merge($checklistTasks[2], ['status' => 'Pending']),
            array_merge($checklistTasks[3], ['status' => 'Pending']),
            array_merge($checklistTasks[4], ['status' => 'Pending']),
            array_merge($checklistTasks[5], ['status' => 'Pending']),
            array_merge($checklistTasks[6], ['status' => 'Pending']),
            array_merge($checklistTasks[7], ['status' => 'Pending']),
            array_merge($checklistTasks[8], ['status' => 'Pending']),
            array_merge($checklistTasks[9], ['status' => 'Pending']),
            array_merge($checklistTasks[10], ['status' => 'Pending']),
            array_merge($checklistTasks[11], ['status' => 'Pending']),
            array_merge($checklistTasks[12], ['status' => 'Pending']),
            array_merge($checklistTasks[13], ['status' => 'Pending']),
            array_merge($checklistTasks[14], ['status' => 'Pending']),
        ],
       
    ];
@endphp

@section('content')
    <!-- Task List Card with Tabs -->
    <x-ui.card title="Task Status Tracker" bodyClass="p-0">
        @php
            $statusTabs = [];
            $isFirst = true;
            foreach (array_keys($tabData) as $tabName) {
                $statusTabs[] = [
                    'id' => Str::slug($tabName),
                    'label' => $tabName,
                    'active' => $isFirst,
                    'icon' => $tabName === 'Lead' ? 'feather-users' : 'feather-file-text'
                ];
                $isFirst = false;
            }
        @endphp
        <div class="px-4 pt-3 border-bottom">
            <x-ui.horizontal-tabs id="trackStatusTabs" :tabs="$statusTabs" />
        </div>

            <!-- Tab Content (Tables) -->
            <div class="tab-content" id="trackStatusTabContent">
                @php $isFirst = true; @endphp
                @foreach ($tabData as $tabName => $tasks)
                    @php 
                        $tabId = Str::slug($tabName); 
                    @endphp
                    <div class="tab-pane fade {{ $isFirst ? 'show active' : '' }}" 
                         id="{{ $tabId }}" 
                         role="tabpanel" 
                         aria-labelledby="{{ $tabId }}-tab">
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
                                                    <x-ui.odoo-form-ui type="select" name="status" class="status-select" select2-selector="icon">
                                                        <option value="Pending" data-icon="feather-clock" {{ $task['status'] === 'Pending' ? 'selected' : '' }}>Pending</option>
                                                        <option value="Developer Complete" data-icon="feather-code" {{ $task['status'] === 'Developer Complete' ? 'selected' : '' }}>Developer Complete</option>
                                                        <option value="Internal Testing Complete" data-icon="feather-check" {{ $task['status'] === 'Internal Testing Complete' ? 'selected' : '' }}>Internal Testing Complete</option>
                                                        <option value="External Testing Complete" data-icon="feather-shield" {{ $task['status'] === 'External Testing Complete' ? 'selected' : '' }}>External Testing Complete</option>
                                                        <option value="Rework" data-icon="feather-rotate-ccw" {{ $task['status'] === 'Rework' ? 'selected' : '' }}>Rework</option>
                                                        <option value="Complete" data-icon="feather-check-circle" {{ $task['status'] === 'Complete' ? 'selected' : '' }}>Complete</option>
                                                    </x-ui.odoo-form-ui>
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
    </x-ui.card>
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
        .select-wd-lg .mb-3 {
            margin-bottom: 0 !important;
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
