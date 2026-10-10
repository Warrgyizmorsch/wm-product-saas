# Project Management Module — Enterprise Use Cases

## Overview
This document details 11 verified, end-to-end enterprise use cases supported by the current codebase of the Project Management module. Each scenario is mapped to actual database schema impacts, domain services, authorization checks, event dispatches, and automated test cases.

---

### Use Case 01: Internal Infrastructure Setup Project
- **Actor**: IT Operations Director / System Administrator
- **Goal**: Create and execute an internal non-billable project to upgrade company network infrastructure.
- **Preconditions**: User has `projects.projects.create` permission; no CRM customer required.
- **Main Flow**:
  1. Actor navigates to `/projects/create`.
  2. Enters Project Name "HQ Network Upgrade 2026", Type "Internal", Billing Type "Non-Billable".
  3. Leaves `customer_id` blank.
  4. Allocates budget of 15,000 and planned hours of 120.
  5. Selects internal Network Engineer as Project Manager.
  6. Saves project; creates Task Lists ("Procurement", "Cabling", "Configuration").
- **Alternative Flow**: If budget is exceeded during execution, the system highlights cost variance in the Budget Cost report.
- **System Behavior**: `ProjectService::createProject()` auto-assigns code `PRJ-YYYY-XXXX`; attaches tenant context.
- **Database Impact**: `INSERT INTO projects (tenant_id, company_id, code, name, customer_id, type, billing_type, budget_amount, status) VALUES (?, ?, ?, ?, NULL, 'Internal', 'Non-Billable', 15000, 'planning')`.
- **Events**: Activity log recorded in `project_activity_logs`.
- **Permissions**: `projects.projects.create`.
- **Expected Result**: Project appears in Portfolio; billing cards remain hidden since project is non-billable.
- **Relevant Test**: `tests/Feature/ProjectLifecycleEndToEndTest.php`.

---

### Use Case 02: Fixed-Price Client Delivery Project with Milestones
- **Actor**: Project Manager
- **Goal**: Deliver a fixed-price custom equipment installation for an external CRM customer with 3 billing milestones.
- **Preconditions**: CRM Customer exists (`id = 10`); User has `projects.projects.create` and `projects.projects.edit`.
- **Main Flow**:
  1. Creates Project "Apex Automation Line", selects Customer "Apex Industries", Billing Type "Fixed Price", Budget 60,000.
  2. Navigates to Milestones tab; adds Milestone 1: "Design Sign-off" ($20,000, Due Day 30).
  3. Adds Milestone 2: "Hardware Factory Acceptance Test" ($20,000, Due Day 60).
  4. Adds Milestone 3: "Site Commissioning & Final Handover" ($20,000, Due Day 90).
  5. Teams execute tasks grouped under respective milestone task lists.
- **Alternative Flow**: Customer requests milestone date postponement; PM updates milestone target date and notes schedule variance.
- **System Behavior**: `MilestoneService::createMilestone()` sets `is_completed = false` and `is_invoiced = false`.
- **Database Impact**: 3 records inserted into `project_milestones` table linked to `project_id`.
- **Events**: Activity log recorded.
- **Permissions**: `projects.projects.create`, `projects.projects.edit`.
- **Expected Result**: Milestones tracked against schedule; when marked complete, they become eligible for sales invoicing.
- **Relevant Test**: `tests/Feature/MilestoneTest.php`.

---

### Use Case 03: Time & Materials Consulting Engagement
- **Actor**: Professional Services Lead
- **Goal**: Track billable consulting hours across senior and junior consultants at tiered billing rates.
- **Preconditions**: Project created with Billing Type "Time & Materials".
- **Main Flow**:
  1. PM opens Members tab; adds Senior Architect at `rate_per_hour = 150.00` (`cost_per_hour = 75.00`).
  2. Adds Technical Consultant at `rate_per_hour = 95.00` (`cost_per_hour = 45.00`).
  3. Members execute tasks and log daily hours specifying `is_billable = true`.
  4. Hours accumulate under approved timesheets in `project_time_logs`.
  5. At month-end, PM triggers Sales Invoicing bridge to generate client draft invoice.
- **Alternative Flow**: Member accidentally marks internal training hours as billable; PM rejects timesheet during approval.
- **System Behavior**: `TimeLogService` looks up rates from `project_members.rate_per_hour` and computes financial value.
- **Database Impact**: Rows inserted into `project_time_logs` with `is_billable = 1`, `hourly_rate = 150.00`.
- **Events**: `TimesheetSubmitted`, `TimesheetApproved`.
- **Permissions**: `projects.projects.edit`.
- **Expected Result**: System tracks billable accrued revenue vs. internal payroll cost.
- **Relevant Test**: `tests/Feature/ProjectBillingTest.php`.

---

### Use Case 04: Multi-Member Collaborative Task Planning & Delegation
- **Actor**: Scrum Master / Project Lead
- **Goal**: Decompose a complex phase into discrete tasks, assign checklist items, and assign team members.
- **Preconditions**: Project has active members.
- **Main Flow**:
  1. PM creates Task List "Sprint 1: Core API".
  2. Creates Task "Build Authentication Controller", assigns to Developer A, estimates 16 hours.
  3. Developer A opens task drawer and creates Subtasks: "Setup Sanctum", "Add Rate Limiter", "Write Tests".
  4. Developer A checks off subtasks as they are implemented.
  5. Subtask completion automatically updates parent task progress bar to 33%, 66%, then 100%.
- **Alternative Flow**: Developer A is reallocated; PM reassigns task to Developer B; system dispatches notification.
- **System Behavior**: `SubTaskService::toggleCompletion()` triggers `TaskService::recalculateProgress()`.
- **Database Impact**: Rows inserted into `project_tasks` and `project_sub_tasks`.
- **Events**: `TaskAssigned`, `TaskCompleted`.
- **Permissions**: `projects.projects.edit`.
- **Expected Result**: Task progress reflects actual checklist completion.
- **Relevant Test**: `tests/Feature/SubTaskTest.php`.

---

### Use Case 05: Task Dependency Sequencing & Critical Path Identification
- **Actor**: Technical Project Manager
- **Goal**: Establish predecessors for a multi-stage project and identify which tasks dictate the final delivery date.
- **Preconditions**: Tasks created with duration estimates.
- **Main Flow**:
  1. PM links Task 1 (Foundation) as Finish-to-Start predecessor to Task 2 (Framing) via `depends_on_task_id`.
  2. Links Task 2 to Task 3 (Roofing) and Task 4 (Electrical).
  3. Links Task 3 and Task 4 to Task 5 (Final Inspection).
  4. Navigates to Schedule / Gantt tab (`/projects/{project}/schedule`).
  5. System executes 2-pass CPM engine: calculates Early Dates, Late Dates, and Total Float.
  6. Tasks on the Critical Path (Float $\le 0$) are flagged and highlighted in red on the Gantt chart.
- **Alternative Flow**: User attempts to make Task 5 a predecessor of Task 1; system detects circular dependency and rejects save.
- **System Behavior**: `TaskDependencyService::wouldCreateCycle()` returns true; `ProjectScheduleService` updates `total_float` and `is_critical`.
- **Database Impact**: Rows in `project_task_dependencies`; columns `total_float` and `is_critical` updated in `project_tasks`.
- **Events**: None.
- **Permissions**: `projects.projects.edit`.
- **Expected Result**: Accurate schedule network diagram with clear critical path visibility.
- **Relevant Test**: `tests/Feature/ProjectScheduleTest.php`, `tests/Feature/TaskDependencyTest.php`.

---

### Use Case 06: Timesheet Submission, Rejection & Approved Billing Readiness
- **Actor**: Software Engineer (Submitter) & Project Manager (Approver)
- **Goal**: Submit weekly hours for work done, receive feedback on rejected entries, and approve valid entries for invoicing.
- **Preconditions**: Tasks in progress; users assigned to project.
- **Main Flow**:
  1. Engineer submits 6.0 hours on Task "Database Optimization" with detailed notes.
  2. PM opens Timesheet Approvals page (`/timesheets/approvals`).
  3. PM reviews log, checks billability, and clicks **Approve Selected**.
  4. Status transitions from `Pending` to `Approved`.
- **Alternative Flow (Rejection)**:
  1. Engineer logs 8.0 hours with vague description "General debugging".
  2. PM selects row, enters rejection remarks "Please provide ticket IDs worked on", and clicks **Reject Selected**.
  3. Status changes to `Rejected`; Engineer receives in-app rejection alert with feedback.
  4. Engineer edits remarks and resubmits.
- **System Behavior**: `TimeLogService::approve()` / `reject()` executes within a transaction.
- **Database Impact**: `project_time_logs.approval_status` updated to `'Approved'` or `'Rejected'`.
- **Events**: `TimesheetSubmitted`, `TimesheetApproved`, `TimesheetRejected`.
- **Permissions**: `projects.projects.edit`.
- **Expected Result**: Only `Approved` logs can be aggregated into a Sales invoice.
- **Relevant Test**: `tests/Feature/TimeLogTest.php`.

---

### Use Case 07: Critical Defect Triage, Resolution & Retest Workflow
- **Actor**: QA Analyst & Senior Developer
- **Goal**: Log a blocker defect during staging tests, assign it for resolution, and close it after verification.
- **Preconditions**: Project active; task undergoing testing.
- **Main Flow**:
  1. QA logs Issue "Payment Gateway Times Out on Checkout", sets Severity "Critical", links to Task "Payment Integration".
  2. Developer receives in-app alert, moves status to "In Progress", commits fix, moves status to "Resolved".
  3. QA retests in staging environment; verifies fix and marks Issue "Closed".
- **Alternative Flow**: Retest fails; QA triggers IssueRetested; system alerts developer to resume investigation.
- **System Behavior**: `IssueService` transitions status and sets `resolved_at` timestamp.
- **Database Impact**: Row inserted and updated in `project_issues` table.
- **Events**: `IssueLogged`, `IssueResolved`, `IssueRetested`.
- **Permissions**: `projects.projects.edit`.
- **Expected Result**: Defect tracked to closure; Gate 2 blocks project closure as long as defect remains unclosed.
- **Relevant Test**: `tests/Feature/IssueTest.php`.

---

### Use Case 08: Milestone UAT Review & Change Request Baseline Adjustment
- **Actor**: Client Project Representative & Project Manager
- **Goal**: Perform client User Acceptance Testing (UAT) on Phase 1 deliverables; handle requested scope modifications.
- **Preconditions**: Milestone 1 tasks completed; Project Review requested.
- **Main Flow**:
  1. PM triggers Project Review for "Milestone 1 Deliverables".
  2. Reviewer conducts UAT testing and reports that an additional export format is required.
  3. Reviewer marks review "Rework Required".
  4. PM submits Change Request: "Add Custom CSV Export", schedule impact +5 days, cost impact +$3,500.
  5. PM / Client approves Change Request.
  6. System updates project target end date by 5 days and budget by $3,500.
- **Alternative Flow**: Client rejects change request cost; scope modification is declined, and original baseline remains active.
- **System Behavior**: `ProjectReviewService::signOff()`, `ChangeRequestService::approve()`.
- **Database Impact**: Updates in `project_reviews`, `project_change_requests`, and `projects`.
- **Events**: `ProjectReviewRequested`, `ProjectReviewSignedOff`, `ChangeRequestCreated`.
- **Permissions**: `projects.projects.edit`.
- **Expected Result**: Scope variations formally documented without unbudgeted scope creep.
- **Relevant Test**: `tests/Feature/ProjectReviewTest.php`.

---

### Use Case 09: Aggregated Customer Invoicing Bridge via Sales Domain
- **Actor**: Billing Specialist / Project Manager
- **Goal**: Generate an official Sales Invoice from approved time logs and completed milestones for client billing.
- **Preconditions**: Project has 40 approved unbilled hours and 1 completed unbilled milestone ($10,000).
- **Main Flow**:
  1. User navigates to Project Details → Billing tab (`/projects/{project}/billing`).
  2. System shows Unbilled Items summary: 40 hrs labor ($6,000) + Milestone 1 ($10,000) = Total $16,000.
  3. User clicks **Generate Invoice**.
  4. System builds invoice line items, mapping labor to the active Inventory Service product SKU.
  5. System invokes `SalesInvoiceCreationService::createDraftInvoice()`.
  6. A new Sales Invoice is created with `status = 'Draft'` and `project_id` foreign key.
  7. Time logs and milestones are atomically updated with `is_invoiced = true` and `invoice_id = invoice.id`.
  8. User is redirected to view the draft invoice in the Sales module.
- **Alternative Flow**: When Sales accountant approves and posts invoice, `InvoicePosted` event triggers `PostSalesInvoiceJournal` in Sales, booking GL ledger debits and credits via `SalesAccountingService`.
- **System Behavior**: Atomic transaction with pessimistic locking in `ProjectBillingService::generateInvoice()`.
- **Database Impact**: `INSERT INTO invoices (customer_id, project_id, total_amount, status) ...`; `UPDATE project_time_logs SET is_invoiced = 1, invoice_id = ?`; `UPDATE project_milestones SET is_invoiced = 1, invoice_id = ?`.
- **Events**: Dispatches `InvoiceCreated` (Sales domain).
- **Permissions**: `projects.projects.edit`.
- **Expected Result**: Project work billed accurately without double-billing or orphaned timesheets.
- **Relevant Test**: `tests/Feature/ProjectBillingTest.php`.

---

### Use Case 10: Controlled Project Closure with Strict Gate Validation (5 Gates)
- **Actor**: Project Manager & Delivery Director
- **Goal**: Formally close a completed project, ensuring zero dangling work, resolving all bugs, and recording lessons learned.
- **Preconditions**: Project deliverables finished; PM navigates to `/projects/{project}/closure`.
- **Main Flow**:
  1. System executes `ProjectClosureService::evaluateGates()`:
     - Gate 1 (Tasks/Subtasks): 0 open tasks, 0 incomplete subtasks (Pass).
     - Gate 2 (Issues): 0 open/in-progress issues (Pass).
     - Gate 3 (Reviews/CRs): At least 1 approved review exists; 0 pending reviews; 0 pending CRs (Pass).
     - Gate 4 (Billing Settlement): 0 unbilled approved billable logs; 0 pending timesheets; 0 unbilled milestones (Pass).
     - Gate 5 (Milestones): 0 incomplete milestones (Pass).
  2. PM selects `closure_status = 'Completed'`.
  3. Enters `client_feedback_rating = 5`, `lessons_learned = 'Early CPM analysis prevented critical path delays'`.
  4. Clicks **Finalize Project Closure**.
  5. Project transitions to `closed` status; `closed_at` and `closed_by` timestamps recorded.
- **Alternative Flow (Gate Blocked)**:
  1. One issue remains in `in_progress` status or an approved billable time log remains uninvoiced.
  2. Gate 2 or Gate 4 triggers a hard blocker error.
  3. PM cannot click Finalize Closure; must resolve blocker before closure is allowed.
- **System Behavior**: `ProjectClosureService::assertClosable()` throws `ValidationException` if any gate fails.
- **Database Impact**: `UPDATE projects SET status = 'closed', closure_status = 'Completed', closed_at = NOW(), closed_by = ? WHERE id = ?`.
- **Events**: `ProjectClosed`.
- **Permissions**: `projects.projects.edit`.
- **Expected Result**: Project becomes read-only; historical performance recorded in corporate memory.
- **Relevant Test**: `tests/Feature/ProjectClosureTest.php`, `tests/Feature/ProjectLifecycleEndToEndTest.php`.

---

### Use Case 11: Executive Portfolio Risk Review & Export Reporting
- **Actor**: Chief Operations Officer (COO) / Portfolio Manager
- **Goal**: Review corporate project health across active engagements, identify overdue tasks, and export utilization data for executive board meetings.
- **Preconditions**: Multiple active projects across various stages.
- **Main Flow**:
  1. Executive navigates to `/projects/dashboard`.
  2. Reviews KPI summary (`DashboardKpiDTO`): Active Projects, Portfolio Health %, Budget vs Actual, Overdue Tasks/Milestones.
  3. Identifies projects categorized as `At Risk` or `Critical`.
  4. Navigates to `/projects/reports`, selects **Resource Utilization Report**.
  5. Filters by Current Quarter and All Projects.
  6. Clicks **Export Excel (XLSX)**.
  7. Downloads formatted workbook showing billable utilization percentages by resource.
- **Alternative Flow**: Executive selects **Budget & Cost Variance Report** and exports streaming CSV for import into corporate BI tools.
- **System Behavior**: `ProjectDashboardService` aggregates KPIs; `ProjectReportExport` compiles spreadsheet.
- **Database Impact**: Read-only aggregations across `projects`, `project_tasks`, `project_time_logs`.
- **Events**: None.
- **Permissions**: `projects.projects.view`.
- **Expected Result**: Immediate cross-project transparency and verified exportable analytics.
- **Relevant Test**: `tests/Feature/ProjectDashboardTest.php`, `tests/Feature/ProjectReportTest.php`.
