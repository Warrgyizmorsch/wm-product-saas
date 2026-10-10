# Project Management Module — End-User Operations Manual

## 1. Introduction & Navigation Overview

The Project Management module provides a unified workspace for planning, staffing, executing, tracking, and closing client and internal projects. It integrates with CRM for customer master records, Sales for invoice generation, and the core ERP User directory for team assignments.

### Main Navigation Entry Points
All Project Management functionality is accessible from the primary ERP sidebar under the **Revenue Cycle** section:
- **Project Portfolio / Master List**: `/projects` (`feather-briefcase` icon)
- **Executive Dashboard**: `/projects/dashboard`
- **Timesheet Approvals**: `/timesheets/approvals`
- **Project Reports**: `/projects/reports`

---

## 2. Setting Up a New Project

### Step 1: Create a Project
- **Prerequisites**: Active tenant user session with `projects.projects.create` permission. If linking to a client, the customer must already exist in CRM.
- **Navigation**: Navigate to `/projects` and click the **+ New Project** button.
- **Input Fields**:
  - `name`: Full project title (required, string up to 255 chars).
  - `code`: Project reference code (optional; if left blank, the system automatically generates `PRJ-YYYY-XXXX`).
  - `customer_id`: Autocomplete lookup linked to CRM Customers. Leave blank for internal projects.
  - `type`: `Internal`, `Client Delivery`, `Research & Development`, or `Maintenance`.
  - `billing_type`: `Time & Materials`, `Fixed Price`, `Milestone Based`, or `Non-Billable`.
  - `start_date`: Scheduled project kick-off date (`YYYY-MM-DD`).
  - `target_end_date`: Target delivery date (`YYYY-MM-DD`).
  - `budget_amount`: Allocated financial budget in tenant currency.
  - `estimated_hours`: Total planned labor hours.
  - `manager_id`: Designated Project Manager (select from active ERP users).
  - `description`: Scope statement and objectives.
- **Expected Result**: Project is saved with `status = planning` and redirects to the Project Overview dashboard.
- **Common Error**: "The customer_id field must be valid" occurs if an invalid customer ID is passed.
- **Recovery**: Select the customer from the live dropdown list.

### Step 2: Staffing & Project Members
- **Prerequisites**: `projects.projects.edit` permission.
- **Navigation**: Open Project → Click **Members** tab (`/projects/{project}/members`).
- **User Actions**:
  1. Click **+ Add Member**.
  2. Select an active ERP user from the dropdown.
  3. Assign a project role: `Project Manager`, `Technical Lead`, `Member`, or `Viewer`.
  4. Specify `rate_per_hour`: Billing rate charged to the client per billable hour.
  5. Specify `cost_per_hour`: Internal cost rate per hour for profitability calculations.
  6. Specify `budget_hours`: Allocated hour cap for this member.
  7. Click **Save Member**.
- **Expected Result**: Member appears in the roster; user is granted project-level collaboration permissions.

---

## 3. Work Breakdown Structure (WBS) & Tasks

### Step 3: Organizing Task Lists
- **Purpose**: Group tasks logically into phases, sprints, or functional deliverables.
- **Navigation**: Project Details → **Task Lists** tab (`/projects/{project}/task-lists`).
- **User Actions**: Click **+ Add Task List**, enter the list title, optional milestone link, and sort position. Click **Save**.

### Step 4: Creating Tasks & Assignments
- **Navigation**: Project Details → **Tasks** tab (`/projects/{project}/tasks`).
- **User Actions**:
  1. Click **+ New Task**.
  2. Select the parent **Task List**.
  3. Enter `title`, `description`, `priority` (`Low`, `Medium`, `High`, `Critical`).
  4. Assign to a project member from the `assigned_to` dropdown.
  5. Enter `start_date`, `due_date`, and `estimated_hours`.
  6. Set `is_milestone` checkbox if this task represents a major milestone.
  7. Click **Create Task**.
- **Expected Result**: Task appears in both the Task Table and Kanban board with status `Open`. Assigned user receives an in-app assignment alert.

### Step 5: Checklists & Subtasks
- **Navigation**: Open any Task detail drawer → Click **Subtasks** tab.
- **User Actions**: Enter subtask title and optional assignee. When work is completed, check the completion checkbox.
- **Expected Result**: Subtask status updates; parent task progress percentage automatically recalculates based on completed checklist items.

---

## 4. Dependencies, CPM & Gantt Schedule

### Step 6: Setting Task Dependencies
- **Purpose**: Enforce logical sequences between tasks.
- **Navigation**: Open Task detail drawer → **Dependencies** tab (`/tasks/{task}/dependencies`).
- **User Actions**:
  1. Click **+ Add Dependency**.
  2. Select the **Predecessor Task** (`depends_on_task_id`).
  3. Select Dependency Type:
     - `Finish-to-Start (FS)`: Predecessor must finish before this task starts.
     - `Start-to-Start (SS)`: Both tasks start simultaneously.
     - `Finish-to-Finish (FF)`: Both tasks finish simultaneously.
     - `Start-to-Finish (SF)`: Predecessor must start before this task finishes.
  4. Enter `lag_days` (positive number for wait delay, negative for lead time).
  5. Click **Save Dependency**.
- **Common Error**: "Circular dependency detected: Task A cannot depend on Task B because Task B already depends on Task A."
- **Recovery**: Inspect the Gantt chart and remove the conflicting predecessor.

### Step 7: Viewing the Gantt Chart & Critical Path
- **Navigation**: Project Details → **Schedule / Gantt** tab (`/projects/{project}/schedule`).
- **Features**:
  - Interactive timeline view plotting all tasks against calendar dates.
  - Dependency connector lines connecting predecessor and successor tasks.
  - **Critical Path Highlight**: Tasks with Total Float $\le 0$ are highlighted in red. Any delay in these tasks will directly delay project delivery.

---

## 5. Time Tracking & Timesheet Approvals

### Step 8: Logging Time
- **Navigation**: Project Details → **Time Logs** tab or Quick Actions menu (`/projects/{project}/time-logs`).
- **User Actions**:
  1. Select the task worked on.
  2. Enter `log_date`, `hours` (decimal, e.g., `3.50`), and detailed `description` of work performed.
  3. Check `is_billable` if the hours are chargeable to the client.
  4. System automatically populates `hourly_rate` from the member's rate card (`rate_per_hour`).
  5. Click **Submit Time Log**.
- **Expected Result**: Log is saved with `approval_status = Pending`.

### Step 9: Timesheet Review & Approvals
- **Prerequisites**: Project Manager role or `projects.projects.edit` permission.
- **Navigation**: Sidebar → **Timesheets** → **Approvals** (`/timesheets/approvals`).
- **User Actions**:
  1. Filter by Project or Team Member.
  2. Review submitted hours, billability, and work notes.
  3. Select individual logs or batch-select rows.
  4. Click **Approve Selected** or **Reject Selected** (requires entering rejection remarks).
- **Expected Result**: Approved logs transition to `Approved` and become locked and eligible for client billing. Rejected logs return to the user with feedback.

---

## 6. Quality Control: Issues, Documents & Reviews

### Step 10: Reporting & Resolving Issues
- **Navigation**: Project Details → **Issues** tab (`/projects/{project}/issues`).
- **User Actions**:
  1. Click **+ Report Issue**.
  2. Enter title, severity (`Minor`, `Major`, `Critical`), priority (`Low`, `Medium`, `High`, `Critical`), and assign to a team member.
  3. To transition status: Open → **Assigned** → **In Progress** → **Resolved** → **Closed**.
  4. If retesting is required, submit a retest request.
- **Expected Result**: Status updates immediately; all changes are recorded in the activity audit log.

### Step 11: Document Vault & Deliverables
- **Navigation**: Project Details → **Documents** tab (`/projects/{project}/documents`).
- **User Actions**:
  1. Click **Upload Document**.
  2. Attach file (PDF, Office DOCX/XLSX/CSV, image, or ZIP up to 25MB).
  3. Enter document title, category, and remarks.
  4. Click **Upload**.
- **Expected Result**: Document is stored securely on `local` storage under `tenants/{tenant_id}/projects/{project_id}/documents/`; team members can download it via authorized stream.

### Step 12: Milestone & Client UAT Reviews
- **Navigation**: Project Details → **Reviews** tab (`/projects/{project}/reviews`).
- **User Actions**:
  1. Click **+ Request Review**.
  2. Select review type: `Milestone Sign-off`, `Client UAT`, or `Internal Code Quality`.
  3. Assign designated Reviewer.
  4. When conducting review, the reviewer selects **Approved** or **Rework Required**, and enters formal findings.
- **Impact**: All reviews must be resolved before project closure can proceed.

### Step 13: Change Request Workflow
- **Navigation**: Project Details → **Change Requests** tab (`/projects/{project}/change-requests`).
- **User Actions**:
  1. Click **+ New Change Request**.
  2. Enter change title, justification, `schedule_impact_days`, and `cost_impact_amount`.
  3. Submit for review.
  4. Project Manager / Client approves or rejects the request (`Approved`, `Rejected`, `Implemented`).
  5. Upon approval, system updates the project budget and target delivery date baseline.

---

## 7. Customer Billing & Invoicing Bridge

### Step 14: Generating a Sales Invoice from Project
- **Prerequisites**: Approved unbilled time logs or completed unbilled milestones; `projects.projects.edit` permission.
- **Navigation**: Project Details → **Billing** tab (`/projects/{project}/billing`).
- **User Actions**:
  1. Review unbilled summary:
     - **Approved Unbilled Hours**: Sum of approved billable time logs (`approval_status = 'Approved'`, `is_invoiced = false`).
     - **Completed Unbilled Milestones**: Milestones marked complete with billing amounts (`is_invoiced = false`).
  2. Click **Generate Invoice**.
  3. Review line items preview:
     - Aggregates hours by member/task rate card.
     - Adds completed milestone deliverables.
     - Maps items to active 'Service' SKU from Inventory master.
  4. Enter payment terms and due date.
  5. Click **Create Draft Sales Invoice**.
- **Expected Result**: 
  - System invokes `SalesInvoiceCreationService::createDraftInvoice()` in the **Sales** domain.
  - A new Sales Invoice is created with `status = 'Draft'` and `project_id` foreign key.
  - Included time logs and milestones are atomically updated with `is_invoiced = true` and `invoice_id = invoice.id`.
  - Screen displays link to view the generated Sales Invoice.
- **Accounting Posting**: When the Sales department confirms and posts the invoice, the core ERP Sales listener (`PostSalesInvoiceJournal`) automatically posts GL debits to Accounts Receivable and credits to Sales Revenue.

---

## 8. Controlled Project Closure (5 Gates)

### Step 15: Executing Closure Gates
- **Prerequisites**: Project Manager role; project deliverables completed.
- **Navigation**: Project Details → **Closure** tab (`/projects/{project}/closure`).
- **Automated Gate Evaluation**:
  `ProjectClosureService::evaluateGates()` enforces **five strict condition gates**:
  1. **Gate 1: Tasks & Subtasks**: Zero open/in-progress tasks and zero incomplete subtasks.
  2. **Gate 2: Issues**: Zero unresolved issues (`whereNotIn('status', ['Resolved', 'Closed'])`).
  3. **Gate 3: Reviews & Governance**: If milestones exist, at least one approved review must exist; zero pending reviews and zero pending change requests.
  4. **Gate 4: Billing Settlement (Hard Blocker)**: Zero unbilled approved billable time logs, zero pending timesheets, and zero unbilled completed milestones.
  5. **Gate 5: Milestone Completion**: Zero open or uncompleted milestones.
- **User Actions**:
  1. If any gate fails, inspect the blockers table and resolve them.
  2. When all 5 gates pass, select `closure_status`: `Completed`, `Terminated`, or `Handed Over`.
  3. Enter `actual_completion_date`, `client_sign_off_date`, `client_feedback_rating`, and `lessons_learned`.
  4. Click **Finalize Project Closure**.
- **Expected Result**: Project status transitions to `closed`; project becomes read-only for general team members.

---

## 9. Dashboards & Reports

### Step 16: Executive Dashboard
- **Navigation**: `/projects/dashboard`.
- **Widgets**:
  - **KPI Cards (`DashboardKpiDTO`)**: Active Projects, On-Track Projects, Overdue Tasks, Approved Billable Hours, Active Issues.
  - **Portfolio Health Status**: Evaluated as `On Track`, `At Risk`, or `Critical`.
  - **Resource Workload**: Heatmap of active tasks assigned per team member.

### Step 17: Generating & Exporting Reports
- **Navigation**: `/projects/reports`.
- **Available Reports**:
  1. **Project Summary Report**: High-level portfolio status, start/end dates, budget vs. actual.
  2. **Task Status Report**: Breakdown of tasks by list, assignee, priority, and status.
  3. **Resource Utilization Report**: Planned hours vs. logged hours per team member.
  4. **Timesheet Billability Report**: Billable vs. non-billable hours and billable value.
  5. **Issue & Defect Density Report**: Bugs logged, turnaround time, and severity breakdown.
  6. **Milestone Variance Report**: Planned milestone dates vs. actual completion dates.
  7. **Budget & Cost Variance Report**: Financial budget vs. actual labor costs and billing totals.
- **Exporting**: Select filters and date range, then click **Export CSV** or **Export Excel (XLSX)**.

---

## 10. Screenshot & Visual Verification Checklist

When capturing official screenshots for enterprise user documentation or client onboarding training, adhere to the following checklist:

| Screen Identifier | Target Route | Purpose of Screen | Required Visible Controls | Suggested State & Data |
| :--- | :--- | :--- | :--- | :--- |
| **SCR-PRJ-01** | `/projects` | Project Portfolio List | Search bar, status filter dropdown, '+ New Project' button, pagination, export button. | At least 4 projects displaying various statuses (Planning, In Progress, Completed). |
| **SCR-PRJ-02** | `/projects/create` | Project Creation Form | Code, Name, Customer autocomplete, Type, Billing Type, Date pickers, Budget, Manager select. | Partially filled form showing client selection dropdown open. |
| **SCR-PRJ-03** | `/projects/{project}` | Project Overview / Hub | Header banner, progress bar, KPI badges (Hours, Budget, Tasks), tab navigation bar. | Active project with 60% progress and member avatars visible. |
| **SCR-PRJ-04** | `/projects/{project}/tasks` | Task Management (Kanban/Table) | View switcher (Table / Kanban), '+ New Task' button, filter by assignee, priority badges. | Kanban board with cards populated across Open, In Progress, Review, Completed. |
| **SCR-PRJ-05** | `/projects/{project}/schedule` | Gantt & Critical Path View | Date zoom controls (Day/Week/Month), task bars, dependency connector lines, critical path toggle. | Multi-task timeline with critical path tasks highlighted in red. |
| **SCR-PRJ-06** | `/timesheets/approvals` | Timesheet Approval Hub | Batch selection checkboxes, Approve button, Reject button, Filter by project/member. | 3 pending time log submissions showing hours, member name, and task link. |
| **SCR-PRJ-07** | `/projects/{project}/issues` | Issue & Defect Tracking | '+ Report Issue' button, severity filter (Critical, Major), issue status badges, task link. | List showing 2 resolved issues and 1 critical in-progress bug. |
| **SCR-PRJ-08** | `/projects/{project}/billing` | Project Billing & Invoice Bridge | Unbilled hours card, unbilled milestones card, 'Generate Invoice' button, invoiced history table. | Summary showing 42 approved unbilled hours and 1 completed milestone ready to invoice. |
| **SCR-PRJ-09** | `/projects/{project}/closure` | Controlled Closure Gateways | 5 Gate check indicators (Green/Red checkmarks), lessons learned textarea, 'Finalize Closure' button. | Screen showing all 5 gates passing (green checkmarks) ready for final sign-off. |
| **SCR-PRJ-10** | `/projects/dashboard` | Executive Dashboard | Top KPI summary cards (`DashboardKpiDTO`), health score, resource allocation, overdue task list. | Live dashboard showing aggregate enterprise project metrics. |
| **SCR-PRJ-11** | `/projects/reports` | Report Generator & Export | Report selector dropdown, date range picker, project multi-select, 'Export CSV', 'Export Excel'. | Report preview table displaying Timesheet Billability calculations. |
