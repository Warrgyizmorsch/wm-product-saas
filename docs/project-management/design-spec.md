# Project Management Module — Design Specification & UI/UX Standards

> **Document Status:** Canonical Design Specification & UI Reference  
> **Target Location:** `docs/project-management/design-spec.md`  
> **Source Documents:** *Project Management Module – Screen Wise Fields.pdf*, *Project Management Module – Functional Flow.pdf*, Workflow Diagram, and Audit Baseline (September 10, 2026).

---

## 1. UI/UX Principles & Core Design Patterns

### 1.1 Intentional UX Pattern: Fast Project Creation
To eliminate form friction and provide an agile project setup experience:
1. **Creation Step (Modal):**
   - The user triggers "New Project" from the Project Directory toolbar.
   - The creation modal requires **only one input: Project Name**.
   - Defaults are automatically assigned: `start_date = today`, `priority = Medium`, `status = Draft`, `owner_id = logged-in user`.
   - The user is immediately redirected to the Project Detail view.
2. **Detail & Inline Completion (Project Workspace):**
   - On the Project Detail view, all comprehensive metadata fields (Client, Project Manager, End Date, Budget Type, Budget Amount, Budget Hours, Billing Method, Description) are prominently presented in the identity accordion.
   - Every attribute supports responsive **inline AJAX editing** (`<x-ui.inline-edit>`).
   - Project Managers complete or refine project parameters as details crystallize during kickoff.

> [!NOTE]
> **Canonical UX Decision:** Fast Create -> Project Detail -> Inline Completion is the intentional design pattern for this ERP. Coding agents must **not** replace this with a mandatory multi-step creation wizard.

---

### 1.2 Dedicated Workspaces vs. Off-Canvas Drawers
- **Primary Entity Hubs:** Major entities users work in for sustained periods (Projects, Milestones, Tasks) have **dedicated workspace views**:
  - Project Workspace: `projects/{project}`
  - Milestone Workspace: `projects/{project}/milestones/{milestone}`
  - Task Workspace: `projects/{project}/tasks/{task}`
- **Sub-Actions & Auxiliary Data:** Secondary actions (Activity Log history, quick collaborator add/remove, inline row editing) utilize modal popovers or slide-over off-canvas drawers (`<x-ui.drawer>`).

---

### 1.3 Reusable Duralux Component Library
All Project Management screens strictly reuse the ERP's standard Duralux Blade component suite:

| Component | Purpose in Project Management |
|---|---|
| `<x-ui.odoo-form-ui>` | Clean tabular and form layouts |
| `<x-ui.table>`, `<x-table.sort-header>` | Standard directory lists with sort indicators |
| `<x-ui.filter>` | Multi-criteria dropdown filters |
| `<x-ui.pagination>` | Standardized pagination navigation |
| `<x-ui.badge>` | Status pills (`Draft`, `Active`, `Completed`, `Closed`, `On Hold`, `Cancelled`) |
| `<x-ui.inline-edit>` | AJAX in-place field updates |
| `<x-ui.modal>` | Lightweight confirmation and quick-create popups |
| `<x-ui.drawer>` | Slide-over panels (e.g. Activity Log feed) |
| `<x-ui.action-dropdown>` | Row-level contextual menu items |
| `<x-ui.bulk-actions>` | Multi-selection batch operation toolbar |

---

## 2. Screen-by-Screen Specifications

### 2.1 Project Directory (`projects/index.blade.php`)
- **Header:** Title, search keywords input, multi-filter dropdown (Status, Priority, Client, Owner, Start/End Date), Excel Export button, and "New Project" button.
- **Bulk Actions Toolbar:** Hidden by default; appears when rows are checked. Supports batch deletion with permission guards.
- **Table Columns:** Checkbox, Code (`PRJ-0001`), Name, Client, Owner, Priority, Status, Start Date, End Date, Actions.
- **Status:** **FULLY IMPLEMENTED.**

---

### 2.2 Project Detail Workspace (`projects/show.blade.php`)
- **Header Strip:** Project Code, inline-editable Name, Status Badge, "Activity History" drawer trigger, and Context Menu (Delete).
- **Collapsible Identity Accordion ("Details & Collaborators"):**
  - Left Column: Client (select2), Project Owner (select2), Project Manager (select2), Priority (select), Status (transition-aware select).
  - Middle Column: Start Date (date), End Date (date), Billing Method (select), Budget Type (select), Budget Amount (number), Budget Hours (number).
  - Right Column: Collaborator avatar stack with quick-add search popover and remove controls.
  - Bottom Row: Description (inline textarea).
- **Tab Navigation:**
  1. **Summary Tab:** Dashboard stats (task breakdown, milestone progress bar, hour consumption gauge, member count, and recent activity preview).
  2. **Milestones Tab:** Embedded milestone listing with inline creation row, health badges, and link to Milestone Workspace.
  3. **Tasks Tab:** *(Target Phase 2)* Complete task list board.
  4. **Timesheets Tab:** *(Target Phase 3)* Time log ledger for the project.
  5. **Issues Tab:** *(Target Phase 4)* Defect log for the project.
  6. **Documents Tab:** *(Target Phase 4)* Project document repository.
  7. **UAT & Change Requests Tab:** *(Target Phase 5)* Review sign-offs and CRs.
  8. **Billing Tab:** *(Target Phase 7)* Associated Sales Invoices and billing status.
- **Status:** **PARTIALLY IMPLEMENTED (SUMMARY & MILESTONES TABS WORKING; REMAINING TABS TARGETED).**

---

### 2.3 Milestone Directory & Workspace
- **Directory (`milestones/index.blade.php`):** Tenant-wide table showing milestones across all projects with filters.
- **Milestone Workspace (`milestones/workspace.blade.php`):**
  - KPI Strip: Total Tasks, Completed Tasks, Active Tasks, Overdue count, and Task-Weighted Overall Progress %.
  - Health Badge: Dynamic indicator (`On Track`, `At Risk`, `Off Track`, `Blocked`) with explanation tooltip.
  - Overview Tab: Milestone description, owner, timeline, and task distribution.
  - Task Lists Tab: Board-card layout of all task lists and tasks belonging to the milestone.
- **Status:** **FULLY IMPLEMENTED.**

---

### 2.4 Task Workspace (`tasks/workspace.blade.php`)
- **Dedicated Task Command Center:**
  - Hero Header: Task Code (`PRJ-0001-T-001`), inline title edit, Status transition selector (only offers legal next statuses), and Back navigation.
  - Next Action Alert Banner: Highlights the immediate blocker or state (`Blocked by predecessor PRJ-0001-T-002`, `Overdue by 3 days`, `Due today`, `Awaiting QA review`).
  - Left Column (Main Work):
    - Description: Rich/markdown description.
    - Subtasks: Checklist with complete toggle, inline add, and progress fraction (`3/5`).
    - Dependencies: Predecessor list with blocker status; dependent task list ("Blocks X, Y").
    - Time Tracking: *(Target Phase 3)* "Log Time" button and task-specific hours ledger.
    - Attached Documents: *(Target Phase 4)* Task-specific file attachments.
  - Right Column (Meta Rail):
    - Assignee & Reviewer (select2 validated against active project members).
    - Priority (Low, Medium, High, Critical).
    - Milestone & Task List location.
    - Start Date & Due Date.
    - Estimated Hours vs. Actual Hours (dynamically computed from approved timesheets).
  - Bottom Section: Paginated activity history stream specific to the task.
- **Status:** **PARTIALLY IMPLEMENTED (HERO, DESCRIPTION, SUBTASKS, DEPENDENCIES, RAIL & ACTIVITY WORKING; TIME LOGS & ATTACHMENTS TARGETED).**

---

### 2.5 Timeline & Gantt View *(Target Phase 6)*
- **Component:** Native ERP HTML5 Drag & Drop Timeline / Gantt chart (adapted from `resources/views/modules/production/schedules/dispatch-board.blade.php`), or focused SVG connector spike.
- **Features:**
  - Milestone bars with target flag markers.
  - Task bars color-coded by priority and status.
  - Drag-and-drop dependency links (Finish-to-Start, Start-to-Start, Finish-to-Finish).
  - Critical path highlights (red outline on zero-slack tasks).
  - Date adjustment propagation: moving a predecessor bar updates successor task dates via AJAX.

---

### 2.6 Timesheet Management & Approval Screens *(Target Phase 3)*
- **Timesheet Log Modal:** Simple time entry dialog reachable from Task Workspace, Project Show, or Global Header.
- **Timesheet Approval Queue (`projects/timelogs/approval.blade.php`):**
  - Grouped by Resource and Project.
  - Displays: Date, Task, Logged Hours, Billable Amount (`Hours * Rate`), Description.
  - One-click "Approve" and "Reject" buttons with confirmation remarks modal.
  - Batch approval capabilities.

---

### 2.7 Issue Management Screens *(Target Phase 4)*
- **Issue Directory & Workspace (`projects/issues/index.blade.php`, `projects/issues/show.blade.php`):**
  - Issue Code (`PRJ-0001-ISS-001`), Title, Task link, Severity pill, Priority badge, Status.
  - Retest Action Banner: When status is `Resolved`, prominent "Verify & Retest" banner gives QA two choices: "Pass Retest (Close Issue)" or "Fail Retest (Return to In Progress)".

---

### 2.8 Document Repository (`projects/documents/index.blade.php`) *(Target Phase 4)*
- **File Grid / Table:**
  - Category folders (Requirements, Architecture, Design, Test Cases, Meeting Notes, Attachments).
  - Upload file modal with category selection and optional task attachment.
  - File size, uploader avatar, upload date, and secure download action.

---

### 2.9 UAT & Change Request Views *(Target Phase 5)*
- **UAT Sign-off Screen:**
  - Milestone completion checklist verifying 100% completion.
  - Client sign-off form with reviewer name, review date, and status selection (`Approved` / `Rework Required`).
- **Change Request Workspace:**
  - CR Number (`PRJ-0001-CR-001`), Requestor, Description, Impact Analysis breakdown (Schedule days delta, Budget amount delta, Hours delta).
  - Approval action buttons for Project Manager / Tenant Owner.

---

### 2.10 Project Billing Screen *(Target Phase 7)*
- **Billing Summary View:**
  - Aggregates unbilled approved timesheets and unbilled completed milestones.
  - "Generate Invoice" action triggering standard Sales Invoice generation.
  - Display of generated Sales Invoices with direct links to view, send, or receive payment.

---

### 2.11 Executive Dashboard & Reports *(Target Phase 10)*
- **Global Project Dashboard (`projects/dashboard.blade.php`):** Portfolio KPI widgets (Total Active Projects, Overall Health, Budget Consumed vs Planned, Overdue Deliverables, Open Critical Defects).
- **Seven Dedicated Reports (`projects/reports/*.blade.php`):** Clean, printable, filterable tabular reports with CSV/Excel export.

---

## 3. Collaborator Invariant Enforced in UI

All user selectors in the UI strictly enforce project membership:
- The Project Owner, Project Manager, Milestone Owner, Task List Owner, Task Assignee, and Task Reviewer dropdowns only list users who are **active members of that specific project**.
- Adding a user as Owner or Manager automatically inserts them into the Collaborators stack.
- Deactivating or removing a collaborator is blocked in the UI with a descriptive warning if they currently hold an assigned project role.
