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

### 1.3 Reusable Duralux Component Mapping
All Project Management screens strictly follow the dual-tier component standard established for the ERP:

| UI Need | Mandatory Component / Pattern | Purpose & Capabilities |
|---|---|---|
| **Form Fields & Controls** | `<x-ui.odoo-form-ui>` | Sheet layouts, text/number/date inputs, searchable selects, textareas, checkboxes, radios, file uploads, rich text editors, validation error feedback. |
| **Tables & Data Grids** | `<x-ui.odoo-form-ui type="table">` | All PM directories, subtask tables, timesheets, issues, documents, UAT/CR lists, and reports. Styled headers, clean borders, inline table-inputs. |
| **Inline Editing** | `<x-ui.inline-edit>` | AJAX in-place field updates using Odoo-form control styling (`.odoo-form-control`, `.odoo-table-input`, `.odoo-table-select`). |
| **Page Panels & Layout** | `.erp-single-panel` | Standard ERP card wrapper for uniform margins, background, and responsive spacing. |
| **Modals & Dialogs** | `<x-ui.modal>`, `<x-ui.confirmation-modal>`, `<x-ui.confirm-modal>` | Lightweight popups for quick creation, deletion confirmation, and status review. |
| **Slide-over Drawers** | `<x-ui.drawer>` | Off-canvas side panels (e.g. Activity Log feed, audit trails). |
| **Tab Navigation** | `<x-ui.horizontal-tabs>`, `<x-ui.vertical-tabs>` | Workspace navigation across summary, tasks, timesheets, issues, documents, and billing. |
| **Filters & Search** | `<x-ui.filter>`, `<x-ui.sort-dropdown>` | Dropdown filter panels (containing Odoo-form inputs/selects) and multi-column sorting. |
| **Row Actions** | `<x-ui.action-dropdown>` | Contextual dropdowns for view, edit, approve, reject, duplicate, and delete actions. |
| **Bulk Actions** | `<x-ui.bulk-actions>` | Checkbox-driven toolbar for batch operations (delete, approve, status change). |
| **Badges & Statuses** | `<x-ui.badge>`, `<x-ui.status-badge>`, `<x-ui.priority-badge>` | Standardized status pills (`Draft`, `Active`, `In Progress`, `Completed`, `On Hold`, `Cancelled`). |
| **Buttons & Icons** | `<x-ui.button>`, `<x-ui.icon-btn>`, standard Duralux buttons | Primary, secondary, danger, and icon buttons. |
| **Notifications & Toasts** | `<x-ui.toast>`, `<x-ui.alert>` | Server-side flash messages and alert banners. |

---

## 2. Global Component Selection Rules

> [!IMPORTANT]
> **Core Architectural Governance Principle & Exact Distinction:**  
> **Use the existing Odoo-form system exclusively for PM form fields and PM tables. For all other UI needs, freely reuse the existing common ERP components.**  
> - The rule does **NOT** say "Use only common components" (that would lead to fragmented form inputs and generic tables).  
> - The rule does **NOT** say "Every PM element must come from Odoo-form" (modals, drawers, tabs, buttons, badges, and filters are shared ERP components).  
> - Every AI agent and frontend developer implementing Project Management views must strictly adhere to the following component selection rules across all phases.

### 2.1 Form Fields — Odoo-Form Only (`<x-ui.odoo-form-ui>`)
For Project Management, **all form-related UI must use the existing Odoo-form component/system (`<x-ui.odoo-form-ui>`)** or its supported variants:
- **Supported Field Types:**
  - `type="sheet"` — Main white container card (`.odoo-sheet.bg-white`) for forms.
  - `type="input"` — Text, number, date, datetime-local, password, email inputs.
  - `type="select"` — Single and multiple dropdowns, with built-in Select2 searchable capabilities (`searchable="true"`, `select2Selector="default"`, `data-master="..."`).
  - `type="textarea"` — Multi-line textareas (`rows="3"`).
  - `type="checkbox"` — Custom styled checkboxes (`.form-check-input`).
  - `type="radio"` — Grouped radio buttons.
  - `type="file"` — File upload dropzone with upload icon and preview.
  - `type="editor"` or `type="text-editor"` — Embedded Quill rich-text editor (`.odoo-editor`).
- **Form Controls & Composition:**
  - Field labels (`label="..."`, `.odoo-form-label`, width 130px with required red asterisk).
  - Field help text (`helperText="..."`).
  - Validation error presentation (`errorText="..."`, `alpineError="..."`, dynamic invalid feedback).
  - Form groups (`.odoo-form-group`) and form rows (`.row.g-3`, `.row.g-4`).
- **Explicit Restriction:**
  - Do **NOT** use standalone/common form-field components when an equivalent Odoo-form field exists.
  - Specifically, do **NOT** introduce or use:
    - `<x-ui.input>`
    - `<x-ui.select>`
    - `<x-ui.textarea>`
    - `<x-ui.checkbox>`
    - `<x-ui.radio>`
  - The purpose is to guarantee **one unified, polished form language** across all PM views rather than mixing mismatched input styling.

### 2.2 Tables & Data Grids — Odoo-Form Table Only (`<x-ui.odoo-form-ui type="table">`)
For Project Management tables, use the **table/grid/table-layout capability provided by the existing Odoo-form component (`<x-ui.odoo-form-ui type="table">`)**:
- **Explicit Restriction:**
  - Do **NOT** use the separate/common `<x-ui.table>` for PM tables when the Odoo-form table capability is available.
  - Do **NOT** create a new PM-specific table component.
- **Scope of Application:**
  This rule applies to all PM tabular presentations:
  - Project Directory (`projects/index.blade.php`)
  - Milestone Directory & embedded milestone tables
  - Task List tables and task grids
  - Subtask tables and checklists
  - Timesheet ledger tables and Approval Queue (`projects/timelogs/approval.blade.php`)
  - Issue directories and defect listings
  - Document repository tables
  - UAT checklist and Change Request grids
  - Operational report tables (`projects/reports/*.blade.php`)
  - Project Detail tab tables (Members, Milestones, Tasks, Timesheets, Issues, Documents, Invoices)
- **Table Presentation Standards:**
  - Wrap table in `<div class="table-responsive">`.
  - Header: `<thead class="table-light">` with uppercase muted column titles (`.odoo-table th`, font-size 11px, letter-spacing 0.5px).
  - Body: `.odoo-table td` with top vertical alignment and subtle row borders (`#e9ecef`).
  - Editable Grid Rows: When rows contain editable cells, use `.odoo-table-input` and `.odoo-table-select` for seamless borderless inline spreadsheet editing.

### 2.3 Inline Editing — Existing Inline-Edit + Odoo-Form Field System
Project Management requires responsive inline editing for fast parameter refinement:
- **Preserve Existing Architecture:** Keep the existing `<x-ui.inline-edit>` component, pencil hover indicators, AJAX persistence, validation, permissions, and tooltips.
- **Field Component Standard:** When an inline edit activates an editable control, **the control itself must use the Odoo-form field system** (`.odoo-form-control`, `.odoo-table-input`, `.odoo-table-select`, or Odoo-form field variants).
- Do **NOT** create a separate PM-specific inline edit system.
- Preserve:
  - AJAX endpoint contracts (`HandlesInlineFieldUpdates`).
  - In-place error display and validation messages.
  - Keyboard triggers (Enter to save, Esc to cancel).
  - Dynamic display text updates upon successful AJAX response.

### 2.4 Other UI Elements — Common ERP Components Strongly Encouraged
Apart from the two strict restrictions above (Form Fields and Tables), **freely reuse the rich suite of existing common ERP components by their established APIs**:
- **Modals:** `<x-ui.modal>`, `<x-ui.confirmation-modal>`, `<x-ui.confirm-modal>`
- **Slide-over Drawers:** `<x-ui.drawer>` (for Activity History, Collaborator management)
- **Tabs:** `<x-ui.horizontal-tabs>`, `<x-ui.vertical-tabs>`
- **Pagination:** `<x-ui.pagination>` / standard `$records->links()`
- **Filter Menus:** `<x-ui.filter>` and `<x-ui.sort-dropdown>` (note: form fields inside the filter dropdown must use `<x-ui.odoo-form-ui type="input">` / `type="select"`)
- **Buttons & Icon Controls:** `<x-ui.button>`, `<x-ui.icon-btn>`, standard Duralux buttons (`btn btn-primary`, `btn-secondary`, `btn-light`)
- **Action Dropdowns:** `<x-ui.action-dropdown>`, `<x-ui.dropdown>`, `<x-ui.dropdown-item>`
- **Bulk Actions Toolbar:** `<x-ui.bulk-actions>` (for multi-selection batch delete/approve)
- **Badges & Pills:** `<x-ui.badge>`, `<x-ui.status-badge>`, `<x-ui.priority-badge>`, `.erp-badge-active`, `.erp-badge-draft`, `.erp-badge-pending`
- **Alerts & Toasts:** `<x-ui.alert>`, `<x-ui.toast>`
- **Cards & Progress:** `<x-ui.card>`, `<x-ui.stat-widget>`, `<x-ui.stat-pill>`, `<x-ui.progress-bar>`
- **Empty States:** Clean centered empty states with feather icons and muted guidance hints.
- Do **NOT** recreate or reinvent these components for Project Management.

### 2.5 Common Layout & Panels — Standard ERP Patterns (`erp-single-panel`)
All main Project Management screens must be enclosed within:
```html
<div class="erp-single-panel bg-white p-4 rounded-3 border">
    ...
</div>
```
- Ensures consistent white background, rounded border, standard ERP gutter spacing, and responsive padding.
- Do not create custom or standalone layout containers when `erp-single-panel` provides the required structure.

### 2.6 Production BOM Module — Primary Practical Visual Reference
Inspect the existing **Production BOM module** (`resources/views/modules/production/bom/`) as the **primary practical reference** for:
- **Page Structure:** `erp-single-panel` panel wrapper, header title, action buttons, breadcrumbs.
- **Form Composition:** `<x-ui.odoo-form-ui type="sheet">` with two-column responsive grid (`col-md-6`), collapsible advanced sections, clean field alignment.
- **Table Composition:** `<x-ui.odoo-form-ui type="table">` inside `.table-responsive`, row checkboxes, batch selection, column width percentages, text alignment (`text-end` for numbers/hours), action dropdown integration.
- **Tabs Integration:** `<x-ui.horizontal-tabs>` linking tab panes smoothly.
- **Filter & Sort Bars:** `<x-ui.filter>` containing Odoo-form inputs and selects, combined with `<x-ui.sort-dropdown>`.
- **Bulk Actions:** `<x-ui.bulk-actions>` dynamically appearing when rows are checked.
- **Action Dropdowns:** `<x-ui.action-dropdown :viewUrl="...">` with permission-guarded list items.
- **Modals:** Centered `<x-ui.modal>` dialogs with clear action buttons.
- **Buttons & Spacing:** Consistent use of `<x-ui.button>` variants (`primary`, `secondary`, `light`, `danger`).

> [!CAUTION]
> **Reference Boundary:** The Production BOM module is a **UI, layout, and component reference only**. Coding agents must **NOT** copy BOM manufacturing business logic, routing models, scrap calculations, routes, permissions, or database structures into Project Management.

---

## 3. Screen-by-Screen Specifications

### 3.1 Project Directory (`projects/index.blade.php`)
- **Panel & Layout:** Enclosed in `.erp-single-panel bg-white`.
- **Header:** Title, search input, multi-filter dropdown (`<x-ui.filter>` containing Odoo-form inputs and selects for Status, Priority, Client, Owner, Start/End Date), Excel Export button, and "New Project" button (`<x-ui.button>`).
- **Bulk Actions Toolbar:** `<x-ui.bulk-actions>`; hidden by default, toggled when row checkboxes are checked. Supports batch deletion with permission guards.
- **Table:** `<x-ui.odoo-form-ui type="table">` inside `.table-responsive`.
- **Table Columns:** Checkbox (`.form-check-input`), Code (`PRJ-0001`), Name, Client, Owner, Priority (`<x-ui.priority-badge>`), Status (`<x-ui.status-badge>`), Start Date, End Date, Actions (`<x-ui.action-dropdown>`).
- **Status:** **FULLY IMPLEMENTED.**

---

### 3.2 Project Detail Workspace (`projects/show.blade.php`)
- **Panel & Layout:** Enclosed in `.erp-single-panel bg-white`.
- **Header Strip:** Project Code, inline-editable Name, Status Badge (`<x-ui.status-badge>`), "Activity History" drawer trigger (`<x-ui.drawer>`), and Context Menu (`<x-ui.action-dropdown>`).
- **Collapsible Identity Accordion ("Details & Collaborators"):**
  - Left Column: Client (Odoo-form searchable select), Project Owner (Odoo-form searchable select), Project Manager (Odoo-form searchable select), Priority (Odoo-form select), Status (transition-aware Odoo-form select).
  - Middle Column: Start Date (date), End Date (date), Billing Method (select), Budget Type (select), Budget Amount (number), Budget Hours (number).
  - Right Column: Collaborator avatar stack with quick-add search popover and remove controls.
  - Bottom Row: Description (inline textarea using Odoo-form styling).
- **Tab Navigation (`<x-ui.horizontal-tabs>`):**
  1. **Summary Tab:** Dashboard stats (task breakdown, milestone progress bar, hour consumption gauge, member count, and recent activity preview).
  2. **Milestones Tab:** Embedded milestone listing using `<x-ui.odoo-form-ui type="table">` with inline creation row, health badges, and link to Milestone Workspace.
  3. **Tasks Tab:** *(Target Phase 2)* Complete task list board using `<x-ui.odoo-form-ui type="table">`.
  4. **Timesheets Tab:** *(Target Phase 3)* Time log ledger table using `<x-ui.odoo-form-ui type="table">`.
  5. **Issues Tab:** *(Target Phase 4)* Defect log table using `<x-ui.odoo-form-ui type="table">`.
  6. **Documents Tab:** *(Target Phase 4)* Project document repository table using `<x-ui.odoo-form-ui type="table">`.
  7. **UAT & Change Requests Tab:** *(Target Phase 5)* Review sign-offs and CR grids using `<x-ui.odoo-form-ui type="table">`.
  8. **Billing Tab:** *(Target Phase 7)* Associated Sales Invoices table using `<x-ui.odoo-form-ui type="table">`.
- **Status:** **PARTIALLY IMPLEMENTED (SUMMARY, MILESTONES, AND TIMESHEETS TABS WORKING; REMAINING TABS TARGETED).**

---

### 3.3 Milestone Directory & Workspace
- **Directory (`milestones/index.blade.php`):** Tenant-wide table using `<x-ui.odoo-form-ui type="table">` inside `.erp-single-panel` showing milestones across all projects with `<x-ui.filter>` and sort dropdowns.
- **Milestone Workspace (`milestones/workspace.blade.php`):**
  - KPI Strip: Total Tasks, Completed Tasks, Active Tasks, Overdue count, and Task-Weighted Overall Progress %.
  - Health Badge: Dynamic indicator (`On Track`, `At Risk`, `Off Track`, `Blocked`) with explanation tooltip.
  - Overview Tab: Milestone description, owner, timeline, and task distribution.
  - Task Lists Tab: Board-card layout of all task lists and tasks belonging to the milestone.
- **Status:** **FULLY IMPLEMENTED.**

---

### 3.4 Task Workspace (`tasks/workspace.blade.php`)
- **Dedicated Task Command Center:** Enclosed in `.erp-single-panel bg-white`.
  - Hero Header: Task Code (`PRJ-0001-T-001`), inline title edit, Status transition selector (only offers legal next statuses), and Back navigation button (`<x-ui.button>`).
  - Next Action Alert Banner: Highlights the immediate blocker or state (`Blocked by predecessor PRJ-0001-T-002`, `Overdue by 3 days`, `Due today`, `Awaiting QA review`) using `<x-ui.alert>`.
  - Left Column (Main Work):
    - Description: Rich/markdown description.
    - Subtasks: Checklist and metadata table using `<x-ui.odoo-form-ui type="table">` with complete toggle, inline add, and progress fraction (`3/5`).
    - Dependencies: Predecessor list with blocker status; dependent task list ("Blocks X, Y").
    - Time Tracking: *(Phase 3)* "Log Time" button opening `<x-ui.modal>` with `<x-ui.odoo-form-ui>` inputs, and task-specific hours ledger table (`<x-ui.odoo-form-ui type="table">`).
    - Attached Documents: *(Target Phase 4)* Task-specific file attachments table.
  - Right Column (Meta Rail):
    - Assignee & Reviewer (Odoo-form searchable select validated against active project members).
    - Priority (Low, Medium, High, Critical) using `<x-ui.priority-badge>`.
    - Milestone & Task List location.
    - Start Date & Due Date (inline editable date controls).
    - Estimated Hours vs. Actual Hours (dynamically computed from approved timesheets).
  - Bottom Section: Paginated activity history stream specific to the task.
- **Status:** **PARTIALLY IMPLEMENTED (HERO, DESCRIPTION, SUBTASKS, DEPENDENCIES, RAIL, TIME LOGS & ACTIVITY WORKING; ATTACHMENTS TARGETED).**

---

### 3.5 Timeline & Gantt View *(Target Phase 6)*
- **Component:** Native ERP HTML5 Drag & Drop Timeline / Gantt chart (adapted from `resources/views/modules/production/schedules/dispatch-board.blade.php`), or focused SVG connector spike.
- **Features:**
  - Milestone bars with target flag markers.
  - Task bars color-coded by priority and status.
  - Drag-and-drop dependency links (Finish-to-Start, Start-to-Start, Finish-to-Finish).
  - Critical path highlights (red outline on zero-slack tasks).
  - Date adjustment propagation: moving a predecessor bar updates successor task dates via AJAX.

---

### 3.6 Timesheet Management & Approval Screens *(Phase 3 — Completed)*
- **Timesheet Log Modal:** Simple time entry dialog (`<x-ui.modal>`) containing `<x-ui.odoo-form-ui>` fields (date, hours, billable switch, description) reachable from Task Workspace, Project Show, or Global Header.
- **Timesheet Approval Queue (`projects/timelogs/approval.blade.php`):**
  - Enclosed in `.erp-single-panel bg-white`.
  - Filter bar (`<x-ui.filter>` or dedicated filter form) with Odoo-form project and user selects.
  - Tabular Ledger: `<x-ui.odoo-form-ui type="table">` displaying Team Member, Project, Task, Date, Hours, Billable pill, Rate / Amount, Description, and Actions.
  - One-click "Approve" button and "Reject" button with `<x-ui.modal>` feedback dialog.
  - Empty state with feather check icon when all timesheets are processed.
- **Status:** **FULLY IMPLEMENTED.**

---

### 3.7 Issue Management Screens *(Target Phase 4)*
- **Issue Directory & Workspace (`projects/issues/index.blade.php`, `projects/issues/show.blade.php`):**
  - Panel wrapper: `.erp-single-panel bg-white`.
  - Issue Directory Table: `<x-ui.odoo-form-ui type="table">` with Issue Code (`PRJ-0001-ISS-001`), Title, Task link, Severity pill, Priority badge, Status.
  - Issue Create/Edit Forms: Standard `<x-ui.odoo-form-ui type="sheet">` with Odoo-form inputs, selects, and textareas.
  - Retest Action Banner: When status is `Resolved`, prominent "Verify & Retest" banner gives QA two choices: "Pass Retest (Close Issue)" or "Fail Retest (Return to In Progress)".

---

### 3.8 Document Repository (`projects/documents/index.blade.php`) *(Target Phase 4)*
- **File Table:** `<x-ui.odoo-form-ui type="table">` inside `.erp-single-panel`.
  - Category folders (Requirements, Architecture, Design, Test Cases, Meeting Notes, Attachments).
  - Upload file modal (`<x-ui.modal>`) with `<x-ui.odoo-form-ui type="file">` and category select.
  - File size, uploader avatar, upload date, and secure download action.

---

### 3.9 UAT & Change Request Views *(Target Phase 5)*
- **UAT Sign-off Screen:**
  - Milestone completion checklist table using `<x-ui.odoo-form-ui type="table">` verifying 100% completion.
  - Client sign-off form using `<x-ui.odoo-form-ui>` with reviewer name, review date, and status selection (`Approved` / `Rework Required`).
- **Change Request Workspace:**
  - Change Request grid using `<x-ui.odoo-form-ui type="table">`: CR Number (`PRJ-0001-CR-001`), Requestor, Description, Impact Analysis breakdown (Schedule days delta, Budget amount delta, Hours delta).
  - Approval action buttons (`<x-ui.button>`) for Project Manager / Tenant Owner.

---

### 3.10 Project Billing Screen *(Target Phase 7)*
- **Billing Summary View:**
  - Table using `<x-ui.odoo-form-ui type="table">` aggregating unbilled approved timesheets and unbilled completed milestones.
  - "Generate Invoice" action button (`<x-ui.button>`) triggering standard Sales Invoice generation.
  - Table of generated Sales Invoices (`<x-ui.odoo-form-ui type="table">`) with direct links to view, send, or receive payment.

---

### 3.11 Executive Dashboard & Reports *(Target Phase 10)*
- **Global Project Dashboard (`projects/dashboard.blade.php`):** Portfolio KPI widgets (`<x-ui.card>`, `<x-ui.stat-widget>`) for Total Active Projects, Overall Health, Budget Consumed vs Planned, Overdue Deliverables, Open Critical Defects.
- **Seven Dedicated Reports (`projects/reports/*.blade.php`):** Clean, printable, filterable tabular reports rendered via `<x-ui.odoo-form-ui type="table">` with `<x-ui.filter>` and CSV/Excel export buttons (`<x-ui.button>`).

---

## 4. Collaborator Invariant Enforced in UI

All user selectors in the UI strictly enforce project membership:
- The Project Owner, Project Manager, Milestone Owner, Task List Owner, Task Assignee, and Task Reviewer dropdowns only list users who are **active members of that specific project** using Odoo-form searchable selects.
- Adding a user as Owner or Manager automatically inserts them into the Collaborators stack.
- Deactivating or removing a collaborator is blocked in the UI with a descriptive warning if they currently hold an assigned project role.
