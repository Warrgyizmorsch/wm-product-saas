<div class="row g-4">
    <!-- Section 1: Stat Widgets Showcase -->
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">Duralux KPI Stat Widgets</h5>
                <p class="fs-12 text-muted mb-0">Configurable statistic cards with icons, trends, colors, and variant styles.</p>
            </div>
            <span class="badge bg-soft-primary text-primary fw-semibold">Phase 1 Component</span>
        </div>

        <div class="row g-3">
            <div class="col-xl-3 col-md-6">
                <x-ui.stat-widget
                    title="Total Projects"
                    value="48"
                    subtitle="Active across 6 clients"
                    trend="+12.5%"
                    trendDirection="up"
                    icon="feather-folder"
                    color="primary"
                />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-ui.stat-widget
                    title="Completed Tasks"
                    value="1,240"
                    subtitle="94.2% completion rate"
                    trend="+8.4%"
                    trendDirection="up"
                    icon="feather-check-circle"
                    color="success"
                />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-ui.stat-widget
                    title="Hours Tracked"
                    value="342 hrs"
                    subtitle="Billable project hours"
                    trend="-2.1%"
                    trendDirection="down"
                    icon="feather-clock"
                    color="warning"
                />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-ui.stat-widget
                    title="Overdue Tasks"
                    value="7"
                    subtitle="Requires immediate review"
                    trend="+3"
                    trendDirection="down"
                    icon="feather-alert-triangle"
                    color="danger"
                />
            </div>
        </div>
    </div>

    <!-- Section 2: Avatar Groups & Badges Showcase -->
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">Avatar Groups & Status/Priority Badges</h5>
                <p class="fs-12 text-muted mb-0">Overlapping avatar stacks, online status indicators, and pill badges.</p>
            </div>
        </div>

        <div class="row g-4">
            <!-- Avatar Groups -->
            <div class="col-lg-6">
                <x-ui.card title="Avatar Stack Showcase">
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded-3">
                            <span class="fs-13 fw-semibold text-dark">2 Collaborators (Small)</span>
                            <x-ui.avatar-group
                                :users="[
                                    ['name' => 'Alice Smith', 'online' => true],
                                    ['name' => 'Bob Jones', 'online' => false]
                                ]"
                                size="sm"
                            />
                        </div>

                        <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded-3">
                            <span class="fs-13 fw-semibold text-dark">4 Assignees (Medium + Online Dot)</span>
                            <x-ui.avatar-group
                                :users="[
                                    ['name' => 'Charlie Brown', 'online' => true],
                                    ['name' => 'Diana Prince', 'online' => true],
                                    ['name' => 'Edward Elric', 'online' => false],
                                    ['name' => 'Fiona Gallagher', 'online' => true]
                                ]"
                                size="md"
                                :max="4"
                            />
                        </div>

                        <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded-3">
                            <span class="fs-13 fw-semibold text-dark">8+ Members (Overflow Counter)</span>
                            <x-ui.avatar-group
                                :users="[
                                    ['name' => 'George Clark'],
                                    ['name' => 'Hannah Abbott'],
                                    ['name' => 'Ian Malcolm'],
                                    ['name' => 'Julia Roberts'],
                                    ['name' => 'Kevin Bacon'],
                                    ['name' => 'Laura Croft'],
                                    ['name' => 'Michael Scott'],
                                    ['name' => 'Nina Williams']
                                ]"
                                size="md"
                                :max="4"
                            />
                        </div>
                    </div>
                </x-ui.card>
            </div>

            <!-- Badges -->
            <div class="col-lg-6">
                <x-ui.card title="Status & Priority Badges">
                    <div class="mb-3">
                        <h6 class="fs-12 text-uppercase text-muted fw-bold mb-2">Priority Badges</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <x-ui.priority-badge priority="urgent" />
                            <x-ui.priority-badge priority="high" />
                            <x-ui.priority-badge priority="medium" />
                            <x-ui.priority-badge priority="low" />
                        </div>
                    </div>

                    <div class="pt-3 border-top">
                        <h6 class="fs-12 text-uppercase text-muted fw-bold mb-2">Status Badges</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <x-ui.status-badge status="active" />
                            <x-ui.status-badge status="in_progress" />
                            <x-ui.status-badge status="completed" />
                            <x-ui.status-badge status="on_hold" />
                            <x-ui.status-badge status="delayed" />
                            <x-ui.status-badge status="draft" />
                        </div>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>

    <!-- Section 3: Duralux Project Cards Showcase -->
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">Duralux Project Cards Showcase</h5>
                <p class="fs-12 text-muted mb-0">High-density project cards with milestone stats, progress tracking, and avatar stacks.</p>
            </div>
        </div>

        <div class="row g-4">
            <!-- Active Project -->
            <div class="col-xl-4 col-md-6">
                <x-ui.project-card
                    name="ERP SaaS Core Infrastructure"
                    client="Acme Industrial Solutions"
                    description="Architecture and multi-tenant domain expansion for enterprise resource planning."
                    status="in_progress"
                    priority="high"
                    health="on_track"
                    :progress="68"
                    dueDate="2026-08-30"
                    :milestoneCount="5"
                    :taskCount="24"
                    :completedTaskCount="16"
                    :users="[
                        ['name' => 'Alex Johnson', 'online' => true],
                        ['name' => 'Sarah Connor', 'online' => true],
                        ['name' => 'David Miller', 'online' => false]
                    ]"
                    icon="feather-layers"
                    color="primary"
                >
                    <x-slot:actions>
                        <li><a class="dropdown-item fs-12" href="javascript:void(0)"><i class="feather-eye me-2"></i>View Details</a></li>
                        <li><a class="dropdown-item fs-12" href="javascript:void(0)"><i class="feather-edit me-2"></i>Edit Project</a></li>
                    </x-slot:actions>
                </x-ui.project-card>
            </div>

            <!-- High Priority / At Risk Project -->
            <div class="col-xl-4 col-md-6">
                <x-ui.project-card
                    name="Warehouse Inventory Tracking Module"
                    client="Global Logistics Co."
                    description="Barcode scanner integration, stock replenishment automation, and location audit systems."
                    status="in_progress"
                    priority="urgent"
                    health="at_risk"
                    :progress="42"
                    dueDate="2026-07-28"
                    :milestoneCount="4"
                    :taskCount="18"
                    :completedTaskCount="7"
                    :users="[
                        ['name' => 'Michael Chang'],
                        ['name' => 'Elena Rostova'],
                        ['name' => 'Robert Taylor'],
                        ['name' => 'Maria Garcia']
                    ]"
                    icon="feather-box"
                    color="warning"
                >
                    <x-slot:actions>
                        <li><a class="dropdown-item fs-12" href="javascript:void(0)"><i class="feather-eye me-2"></i>View Details</a></li>
                        <li><a class="dropdown-item fs-12" href="javascript:void(0)"><i class="feather-edit me-2"></i>Edit Project</a></li>
                    </x-slot:actions>
                </x-ui.project-card>
            </div>

            <!-- Completed Project -->
            <div class="col-xl-4 col-md-6">
                <x-ui.project-card
                    name="CRM Customer Portal Redesign"
                    client="Apex Retailers Inc."
                    description="Self-service ticketing portal, online quotation approval, and account management."
                    status="completed"
                    priority="medium"
                    health="on_track"
                    :progress="100"
                    dueDate="2026-06-15"
                    :milestoneCount="3"
                    :taskCount="12"
                    :completedTaskCount="12"
                    :users="[
                        ['name' => 'Jessica Alba'],
                        ['name' => 'Tom Hardy']
                    ]"
                    icon="feather-check-circle"
                    color="teal"
                >
                    <x-slot:actions>
                        <li><a class="dropdown-item fs-12" href="javascript:void(0)"><i class="feather-eye me-2"></i>View Details</a></li>
                    </x-slot:actions>
                </x-ui.project-card>
            </div>
        </div>
    </div>

    <!-- Section 4: Duralux Task Cards Showcase -->
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">Duralux Task Cards Showcase</h5>
                <p class="fs-12 text-muted mb-0">Kanban grid cards and condensed list row views for tasks.</p>
            </div>
        </div>

        <div class="row g-4">
            <!-- Kanban Column View Demo -->
            <div class="col-lg-6">
                <x-ui.card title="Kanban Column View">
                    <x-ui.task-card
                        title="Integrate Select2 dropdown theme with Duralux primary color token"
                        priority="urgent"
                        status="in_progress"
                        dueDate="Tomorrow"
                        :labels="['UI/UX', 'CSS']"
                        :progress="75"
                        :commentCount="4"
                        :attachmentCount="2"
                        :users="[
                            ['name' => 'Alice Smith'],
                            ['name' => 'Bob Jones']
                        ]"
                        variant="kanban"
                    >
                        <x-slot:actions>
                            <li><a class="dropdown-item fs-12" href="javascript:void(0)"><i class="feather-edit me-2"></i>Edit Task</a></li>
                        </x-slot:actions>
                    </x-ui.task-card>

                    <x-ui.task-card
                        title="Database migration script for multi-tenant project roles"
                        priority="medium"
                        status="completed"
                        :completed="true"
                        dueDate="Jul 18"
                        :labels="['Backend', 'Migration']"
                        :commentCount="1"
                        :users="[
                            ['name' => 'Charlie Brown']
                        ]"
                        variant="kanban"
                    />
                </x-ui.card>
            </div>

            <!-- List View Demo -->
            <div class="col-lg-6">
                <x-ui.card title="List Row View">
                    <x-ui.task-card
                        title="Configure Redis queue worker for PDF generation"
                        priority="high"
                        status="in_progress"
                        dueDate="Jul 25"
                        :labels="['Infrastructure']"
                        :users="[
                            ['name' => 'David Miller'],
                            ['name' => 'Elena Rostova']
                        ]"
                        variant="list"
                    >
                        <x-slot:actions>
                            <li><a class="dropdown-item fs-12" href="javascript:void(0)"><i class="feather-edit me-2"></i>Edit Task</a></li>
                        </x-slot:actions>
                    </x-ui.task-card>

                    <x-ui.task-card
                        title="Review security audit findings for API authentication"
                        priority="urgent"
                        status="delayed"
                        dueDate="Overdue"
                        :labels="['Security']"
                        :users="[
                            ['name' => 'Sarah Connor']
                        ]"
                        variant="list"
                    />

                    <x-ui.task-card
                        title="Setup initial project documentation in UI Sandbox"
                        priority="low"
                        status="completed"
                        :completed="true"
                        dueDate="Jul 20"
                        :labels="['Docs']"
                        :users="[
                            ['name' => 'Alex Johnson']
                        ]"
                        variant="list"
                    />
                </x-ui.card>
            </div>
        </div>
    </div>

    <!-- Section 5: Phase 2 Milestone Cards Showcase -->
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">Phase 2: Milestone Cards</h5>
                <p class="fs-12 text-muted mb-0">Presentation-only milestone summary cards displaying precomputed status, health, and progress metrics.</p>
            </div>
            <span class="badge bg-soft-success text-success fw-semibold">Phase 2 Component</span>
        </div>

        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <x-ui.milestone-card
                    title="Phase 1: Multi-Tenant Database Architecture Setup"
                    description="Database migrations, tenant scoping traits, and Redis connection pools."
                    status="completed"
                    health="on_track"
                    dueDate="2026-06-30"
                    :progress="100"
                    :tasksCompleted="12"
                    :tasksTotal="12"
                    :users="[
                        ['name' => 'Alex Johnson'],
                        ['name' => 'Sarah Connor']
                    ]"
                >
                    <x-slot:actions>
                        <li><a class="dropdown-item fs-12" href="javascript:void(0)"><i class="feather-eye me-2"></i>View Milestone</a></li>
                    </x-slot:actions>
                </x-ui.milestone-card>
            </div>

            <div class="col-lg-4 col-md-6">
                <x-ui.milestone-card
                    title="Phase 2: Production & Work Center Dashboard UI"
                    description="Odoo-style form inputs, work center routing tables, and real-time WIP status cards."
                    status="in_progress"
                    health="on_track"
                    dueDate="2026-08-15"
                    :progress="65"
                    :tasksCompleted="13"
                    :tasksTotal="20"
                    :users="[
                        ['name' => 'Michael Chang'],
                        ['name' => 'Elena Rostova'],
                        ['name' => 'David Miller']
                    ]"
                >
                    <x-slot:details>
                        <i class="feather-info me-1 text-primary"></i> <strong>Note:</strong> Work center capacity loading integration is currently under review by QA.
                    </x-slot:details>
                    <x-slot:actions>
                        <li><a class="dropdown-item fs-12" href="javascript:void(0)"><i class="feather-edit me-2"></i>Edit Milestone</a></li>
                    </x-slot:actions>
                </x-ui.milestone-card>
            </div>

            <div class="col-lg-4 col-md-6">
                <x-ui.milestone-card
                    title="Phase 3: Inventory Replenishment & Barcode Scanner API"
                    description="Automated stock level reordering triggers and handheld scanner webhook endpoints."
                    status="in_progress"
                    health="at_risk"
                    dueDate="2026-07-29"
                    :progress="35"
                    :tasksCompleted="5"
                    :tasksTotal="15"
                    :users="[
                        ['name' => 'Robert Taylor'],
                        ['name' => 'Maria Garcia']
                    ]"
                >
                    <x-slot:actions>
                        <li><a class="dropdown-item fs-12 text-danger" href="javascript:void(0)"><i class="feather-alert-triangle me-2"></i>Flag Obstacle</a></li>
                    </x-slot:actions>
                </x-ui.milestone-card>
            </div>
        </div>
    </div>

    <!-- Section 6: Phase 2 Activity Timelines Showcase -->
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">Phase 2: Activity Timeline Feed</h5>
                <p class="fs-12 text-muted mb-0">Vertical timeline displaying precomputed activity nodes, user avatars, and attachment pills.</p>
            </div>
            <span class="badge bg-soft-success text-success fw-semibold">Phase 2 Component</span>
        </div>

        <div class="row g-4">
            <!-- Feed with Activity Items -->
            <div class="col-lg-7">
                <x-ui.card title="Project Activity Log (Active Feed)">
                    <x-ui.activity-timeline
                        :items="[
                            [
                                'user' => ['name' => 'Alex Johnson'],
                                'timestamp' => '10 minutes ago',
                                'title' => 'Uploaded ERP Architecture Blueprint',
                                'description' => 'Attached updated database schema diagram for multi-tenant tenant isolation.',
                                'icon' => 'feather-upload-cloud',
                                'color' => 'primary',
                                'attachments' => [
                                    ['name' => 'erp_schema_v2.pdf', 'url' => '#'],
                                    ['name' => 'tenant_flow.png', 'url' => '#']
                                ]
                            ],
                            [
                                'user' => ['name' => 'Sarah Connor'],
                                'timestamp' => '2 hours ago',
                                'title' => 'Completed Task: Select2 Custom Styling',
                                'description' => 'Updated select2-theme.min.css to align with Duralux primary CSS variables.',
                                'icon' => 'feather-check-circle',
                                'color' => 'success',
                            ],
                            [
                                'user' => ['name' => 'David Miller'],
                                'timestamp' => 'Yesterday at 4:30 PM',
                                'title' => 'Flagged Obstacle on Barcode Scanner API',
                                'description' => 'Handheld device API endpoint timed out during stress testing on 1,000 requests/sec.',
                                'icon' => 'feather-alert-triangle',
                                'color' => 'warning',
                            ]
                        ]"
                    />
                </x-ui.card>
            </div>

            <!-- Empty Timeline State Demo -->
            <div class="col-lg-5">
                <x-ui.card title="Timeline Empty State Demo">
                    <x-ui.activity-timeline
                        :items="[]"
                        emptyTitle="No Activity Recorded"
                        emptyMessage="Activities and audit logs for this project will appear here automatically."
                    />
                </x-ui.card>
            </div>
        </div>
    </div>

    <!-- Section 7: Phase 2 Financial Budget Widgets Showcase -->
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">Phase 2: Project Financial Budget Widgets</h5>
                <p class="fs-12 text-muted mb-0">Presentation-only financial widgets displaying precomputed budget, spent, remaining, and burn rate metrics.</p>
            </div>
            <span class="badge bg-soft-success text-success fw-semibold">Phase 2 Component</span>
        </div>

        <div class="row g-4">
            <!-- Healthy Budget (45% Burn) -->
            <div class="col-lg-4">
                <x-ui.budget-widget
                    budget="$150,000.00"
                    spent="$67,500.00"
                    remaining="$82,500.00"
                    :burnPercentage="45"
                    status="normal"
                >
                    <x-slot:footer>
                        <i class="feather-info me-1 text-success"></i> <strong>Status:</strong> Budget consumption is within planned quarterly allocation.
                    </x-slot:footer>
                </x-ui.budget-widget>
            </div>

            <!-- Near Threshold Budget (88% Burn) -->
            <div class="col-lg-4">
                <x-ui.budget-widget
                    budget="$80,000.00"
                    spent="$70,400.00"
                    remaining="$9,600.00"
                    :burnPercentage="88"
                    status="warning"
                >
                    <x-slot:footer>
                        <i class="feather-alert-triangle me-1 text-warning"></i> <strong>Warning:</strong> 88% of total budget consumed with 30 days remaining.
                    </x-slot:footer>
                </x-ui.budget-widget>
            </div>

            <!-- Over-Budget Alert (115% Burn) -->
            <div class="col-lg-4">
                <x-ui.budget-widget
                    budget="$50,000.00"
                    spent="$57,500.00"
                    remaining="-$7,500.00"
                    :burnPercentage="115"
                    status="over_budget"
                >
                    <x-slot:footer>
                        <i class="feather-alert-octagon me-1 text-danger"></i> <strong>Critical:</strong> Project has exceeded allocated budget by $7,500.00.
                    </x-slot:footer>
                </x-ui.budget-widget>
            </div>
        </div>
    </div>

    <!-- Section 8: Phase 2 Project Progress Overview Cards Showcase -->
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">Phase 2: Project Progress Overview Cards</h5>
                <p class="fs-12 text-muted mb-0">Compact dashboard overview cards rendering precomputed progress, task/milestone ratios, and days remaining.</p>
            </div>
            <span class="badge bg-soft-success text-success fw-semibold">Phase 2 Component</span>
        </div>

        <div class="row g-4">
            <!-- Healthy Overview Card -->
            <div class="col-lg-4 col-md-6">
                <x-ui.project-progress-card
                    title="Odoo Form Component Integration"
                    :progress="82"
                    :daysRemaining="14"
                    :tasksCompleted="18"
                    :tasksTotal="22"
                    :milestonesCompleted="4"
                    :milestonesTotal="5"
                    health="on_track"
                    dueDate="2026-08-05"
                    lastUpdated="10 mins ago"
                    :users="[
                        ['name' => 'Alex Johnson'],
                        ['name' => 'Sarah Connor']
                    ]"
                >
                    <x-slot:actions>
                        <li><a class="dropdown-item fs-12" href="javascript:void(0)"><i class="feather-eye me-2"></i>Open Overview</a></li>
                    </x-slot:actions>
                </x-ui.project-progress-card>
            </div>

            <!-- At Risk Overview Card -->
            <div class="col-lg-4 col-md-6">
                <x-ui.project-progress-card
                    title="HRMS Payroll & Tax Calculation Engine"
                    :progress="48"
                    :daysRemaining="5"
                    :tasksCompleted="12"
                    :tasksTotal="25"
                    :milestonesCompleted="2"
                    :milestonesTotal="6"
                    health="at_risk"
                    dueDate="2026-07-26"
                    lastUpdated="1 hour ago"
                    :users="[
                        ['name' => 'Michael Chang'],
                        ['name' => 'Elena Rostova'],
                        ['name' => 'David Miller']
                    ]"
                >
                    <x-slot:actions>
                        <li><a class="dropdown-item fs-12" href="javascript:void(0)"><i class="feather-edit me-2"></i>Edit Parameters</a></li>
                    </x-slot:actions>
                </x-ui.project-progress-card>
            </div>

            <!-- Off Track / Overdue Overview Card -->
            <div class="col-lg-4 col-md-6">
                <x-ui.project-progress-card
                    title="Legacy Data Import & Migration Script"
                    :progress="20"
                    daysRemaining="Overdue (-3)"
                    :tasksCompleted="4"
                    :tasksTotal="20"
                    :milestonesCompleted="0"
                    :milestonesTotal="4"
                    health="off_track"
                    dueDate="2026-07-18"
                    lastUpdated="Yesterday"
                    :users="[
                        ['name' => 'Robert Taylor']
                    ]"
                >
                    <x-slot:actions>
                        <li><a class="dropdown-item fs-12 text-danger" href="javascript:void(0)"><i class="feather-alert-triangle me-2"></i>Report Issue</a></li>
                    </x-slot:actions>
                </x-ui.project-progress-card>
            </div>
        </div>
    </div>
</div>
