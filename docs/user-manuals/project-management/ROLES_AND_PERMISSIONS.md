# Project Management Module — Roles, Permissions & Access Control

## 1. Access Control Architecture

The Project Management module implements a multi-tiered authorization architecture combining:
1. **Multi-Tenant & Branch Scoping**: All operations are strictly sandboxed by `tenant_id`, `company_id`, and `branch_id` at the database query level via `App\Core\Database\BaseModel` and context middleware. Users cannot view, mutate, or link records belonging to incompatible tenants or branches.
2. **System-Level Permissions (RBAC)**: Managed by `App\Services\Access\AccessService`, checking whether the authenticated user possesses discrete module permission keys (e.g., `projects.projects.view`, `projects.projects.create`, `projects.projects.update`).
3. **Project-Level Role Scoping**: Controlled via the `project_members` roster. A user may have system-wide project access but only possess edit privileges on projects where they are explicitly assigned as a `Project Manager`, `Technical Lead`, or `Member`.
4. **Record-Level Ownership (Own Scope)**: Certain resources (such as pending time logs and reported issues) permit individual authors to edit their own submissions while restricting changes by peer team members.
5. **Super-Admin Override**: Users with the canonical `admin` or `super_admin` role bypass member-level restrictions while remaining strictly confined within their active tenant boundary.

---

## 2. Standard Project Roles & Responsibilities

| Role Name | Scope of Authority | Typical Enterprise Assignments |
| :--- | :--- | :--- |
| **System Admin** | Global tenant access to all projects, settings, reports, bulk actions, and administrative overrides. | IT Administrator, ERP Superuser |
| **Project Manager (PM)** | Full lifecycle control over assigned projects: schedule, team members, rate cards, reviews, timesheet approvals, billing, and closure. | Senior Project Manager, Delivery Director, Engagement Manager |
| **Technical Lead** | Task management, WBS decomposition, dependency planning, review requests, and issue triaging. | Lead Architect, Engineering Lead, Scrum Master |
| **Team Member / Contributor** | Execute assigned tasks, complete subtasks, submit time logs, and report issues. | Software Engineer, Designer, Consultant, Specialist |
| **Project Viewer / Stakeholder** | Read-only access to project overview, Gantt charts, documents, and status reports. | Executive Sponsor, Client Observer, External Auditor |

---

## 3. Master Permission Matrix

The following matrix documents every system permission key recognized by the Project domain, verified against `Database\Seeders\RbacSeeder` and domain policies:

| Permission Key | Resource | Action | Default Roles | Policy / Controller Method | Scope & Restrictions |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `projects.projects.view` | `Project` | List and view project details | Admin, PM, Lead, Member, Viewer | `ProjectPolicy::view()` | Tenant and branch scope. Members can view projects they are assigned to; Admins view all tenant projects. |
| `projects.projects.create` | `Project` | Initialize a new standalone project or convert a Sales Order | Admin, PM | `ProjectPolicy::create()` | Tenant scope. Grants authority to allocate budgets, assign owners, and initialize project WBS. |
| `projects.projects.update` | `Project` | Update metadata, settings, dates, inline fields, collaborators | Admin, PM | `ProjectPolicy::update()` | Enforces PM membership or Admin override. Closed projects are permanently read-only. |
| `projects.projects.delete` | `Project` | Soft-delete project or execute bulk deletion | Admin, Tenant Owner | `ProjectPolicy::delete()` | Enforces Admin role. Projects with invoiced milestones or posted time cannot be deleted. |
| `projects.projects.close` | `Project` | Execute project closure | Admin, PM | `ProjectPolicy::close()` | Evaluates 5 mandatory business gates via `ProjectClosureService`. Once closed, project becomes permanently immutable. |
| `projects.members.manage` | `ProjectMember` | Add/remove team members & manage rate cards | Admin, PM | `ProjectMemberPolicy::manage()` | Manages `rate_per_hour`, `cost_per_hour`, and `budget_hours`. Cannot remove active project owner or manager. |
| `projects.milestones.manage` | `Milestone` | Create, edit, reorder, and complete milestones | Admin, PM | `MilestonePolicy::manage()` | Milestone owner must be an active project collaborator. |
| `projects.tasklists.manage` | `TaskList` | Create, edit, reorder, and delete task lists | Admin, PM, Lead | `TaskListPolicy::manage()` | Scoped to parent project. Deleting a list cascades to child tasks. |
| `projects.tasks.view` | `Task` | View tasks, Gantt timeline, and WBS | Admin, PM, Lead, Member, Viewer | `TaskPolicy::view()` | Inherits project view authorization. |
| `projects.tasks.create` | `Task` | Create tasks and checklists | Admin, PM, Lead | `TaskPolicy::create()` | Allows building deliverables. Assignee must be an active project member. |
| `projects.tasks.update` | `Task` | Edit task status, dates, assignees, subtasks, dependencies | Admin, PM, Lead, Assignee | `TaskPolicy::update()` | Assignees can update status/progress; PM/Lead can alter estimates and dependencies. Cannot jump directly from Open to Completed. |
| `projects.tasks.delete` | `Task` | Delete tasks | Admin, PM | `TaskPolicy::delete()` | Tasks with approved or invoiced time cannot be deleted. |
| `projects.timetracking.view` | `TimeLog` | View time logs and timesheets | Admin, PM, Lead, Member | `TimeLogPolicy::view()` | Active project members can view logs; viewers can inspect summaries. |
| `projects.timetracking.log` | `TimeLog` | Submit time entries against tasks | Admin, PM, Lead, Member | `TimeLogPolicy::create()` | User must be an active project member. Validates hours between 0.01 and 24.00. |
| `projects.timetracking.approve` | `TimeLog` | Approve or reject timesheets | Admin, PM | `TimeLogPolicy::approve()` | User must be designated PM or Admin. Approved logs are permanently locked against modification. |
| `projects.issues.view` | `Issue` | View defects and issue logs | Admin, PM, Lead, Member, Viewer | `IssuePolicy::view()` | Inherits project view authorization. |
| `projects.issues.create` | `Issue` | Log a defect or blocker | Admin, PM, Lead, Member | `IssuePolicy::create()` | Any active project member can report issues. |
| `projects.issues.update` | `Issue` | Update issue description, assignee, priority | Admin, PM, Lead, Assignee | `IssuePolicy::update()` | Assignees and Leads can update investigation notes. |
| `projects.issues.resolve` | `Issue` | Mark issue as resolved | Admin, PM, Lead, Assignee | `IssuePolicy::resolve()` | Records resolution timestamp. Open issues block project closure Gate 2. |
| `projects.issues.retest` | `Issue` | Retest defect if fix is incomplete | Admin, PM, Lead, Reporter | `IssuePolicy::retest()` | Transitions status back to In Progress with retest notes. |
| `projects.issues.delete` | `Issue` | Remove invalid issue records | Admin, PM | `IssuePolicy::delete()` | Restricted to management. |
| `projects.documents.view` | `ProjectDocument` | View and download attached files | Admin, PM, Lead, Member, Viewer | `ProjectDocumentPolicy::view()` | Download endpoints enforce tenant and project authorization. |
| `projects.documents.upload` | `ProjectDocument` | Upload deliverables and attachments | Admin, PM, Lead, Member | `ProjectDocumentPolicy::create()` | Validates maximum file size (25 MB / 25,600 KB) and allowed MIME types. |
| `projects.documents.delete` | `ProjectDocument` | Delete uploaded documents | Admin, PM, Document Uploader | `ProjectDocumentPolicy::delete()` | Uploader or PM can remove unneeded files. |
| `projects.reviews.view` | `ProjectReview` | View review history and UAT signoffs | Admin, PM, Lead, Member, Viewer | `ProjectReviewPolicy::view()` | Historic reviews are preserved for audit traceability. |
| `projects.reviews.create` | `ProjectReview` | Initiate formal milestone or UAT review | Admin, PM, Lead | `ProjectReviewPolicy::create()` | Requires all project milestones to be completed. |
| `projects.reviews.signoff` | `ProjectReview` | Record review decision (Approved / Rework) | Admin, PM, Designee | `ProjectReviewPolicy::signoff()` | Approved signoff clears Gate 3 of project closure. |
| `projects.changerequests.view` | `ChangeRequest` | View change requests | Admin, PM, Lead, Member | `ChangeRequestPolicy::view()` | View scope/budget impact estimates. |
| `projects.changerequests.create` | `ChangeRequest` | Submit a scope change request | Admin, PM, Lead | `ChangeRequestPolicy::create()` | Captures schedule impact and budget delta. |
| `projects.changerequests.approve` | `ChangeRequest` | Approve or reject change requests | Admin, PM | `ChangeRequestPolicy::approve()` | Approved budget deltas automatically revise the project's baseline budget. |
| `projects.billing.view` | `Project` | View financial billing tab and invoices | Admin, PM | `ProjectPolicy::viewBilling()` | Displays unbilled time amount, unbilled milestones, and invoice history. |
| `projects.billing.generate_invoice` | `Project`, `Invoice` | Trigger Sales draft invoice generation | Admin, PM *(Requires `sales.invoices.create`)* | `ProjectPolicy::generateInvoice()` | **Dual Permission Required**: Must have `projects.billing.generate_invoice` (or `projects.projects.update`) **AND** `sales.invoices.create`. |
| `projects.dashboard.view` | Dashboard | View executive KPI dashboard | Admin, PM, Lead, Member, Viewer | `DashboardController::index()` | Tenant-wide executive metrics and health indicators. |
| `projects.reports.view` | Reports | View operational reports and export CSV/XLSX | Admin, PM, Lead | `ProjectReportController::authorizeReports()` | Grants access to all 7 analytical reports and data exports. |

---

## 4. Policy Implementation & Scoping Logic

All project policies are bound to their respective domain models in `app/Providers/AppServiceProvider.php`:

```php
Gate::policy(\App\Domains\Projects\Models\Project::class, \App\Domains\Projects\Policies\ProjectPolicy::class);
Gate::policy(\App\Domains\Projects\Models\ProjectMember::class, \App\Domains\Projects\Policies\ProjectMemberPolicy::class);
Gate::policy(\App\Domains\Projects\Models\Milestone::class, \App\Domains\Projects\Policies\MilestonePolicy::class);
Gate::policy(\App\Domains\Projects\Models\TaskList::class, \App\Domains\Projects\Policies\TaskListPolicy::class);
Gate::policy(\App\Domains\Projects\Models\Task::class, \App\Domains\Projects\Policies\TaskPolicy::class);
Gate::policy(\App\Domains\Projects\Models\TimeLog::class, \App\Domains\Projects\Policies\TimeLogPolicy::class);
Gate::policy(\App\Domains\Projects\Models\Issue::class, \App\Domains\Projects\Policies\IssuePolicy::class);
Gate::policy(\App\Domains\Projects\Models\ProjectDocument::class, \App\Domains\Projects\Policies\ProjectDocumentPolicy::class);
Gate::policy(\App\Domains\Projects\Models\ProjectReview::class, \App\Domains\Projects\Policies\ProjectReviewPolicy::class);
Gate::policy(\App\Domains\Projects\Models\ChangeRequest::class, \App\Domains\Projects\Policies\ChangeRequestPolicy::class);
```

*(Note: `SubTask` and `TaskDependency` do not have standalone policies; their authorization is inherited through parent `TaskPolicy` and controller authorization checks).*

### Dual-Permission Enforcement on Client Invoicing
Generating an invoice from project deliverables mutates the Sales ledger. `ProjectPolicy::generateInvoice()` strictly enforces dual permissions:
```php
public function generateInvoice(User $user, Project $project): bool
{
    // 1. Project Management authorization
    $hasProjectScope = $this->access->allows($user, 'projects.billing.generate_invoice', [
        'tenant_id' => $project->tenant_id,
        'owner_id'  => $project->owner_id,
    ]) || $this->access->allows($user, 'projects.projects.update', [
        'tenant_id' => $project->tenant_id,
        'owner_id'  => $project->owner_id,
    ]);

    // 2. Sales domain authorization
    $hasSalesScope = $this->access->allows($user, 'sales.invoices.create', [
        'tenant_id' => $project->tenant_id,
    ]);

    return $hasProjectScope && $hasSalesScope;
}
```

### Own-Scope Enforcement on Time Tracking
`TimeLogPolicy::update()` enforces that team members can only edit or delete their own time logs while in `Pending` status:
```php
public function update(User $user, TimeLog $timeLog): bool
{
    if ($user->tenant_id !== $timeLog->tenant_id) {
        return false;
    }

    if ($this->access->hasRole($user, 'admin') || $this->access->hasRole($user, 'super_admin')) {
        return true;
    }

    // Approved or invoiced logs are permanently locked
    if ($timeLog->approval_status === TimeLog::STATUS_APPROVED || $timeLog->is_invoiced) {
        return false;
    }

    return (int) $user->id === (int) $timeLog->user_id;
}
```

---

## 5. Security Audit & Invariants

1. **Terminal Closed Status (Immutability)**:
   - When a project reaches `status = 'Closed'`, every mutating policy (`update`, `create`, `delete` across projects, tasks, milestones, time logs, issues, reviews, and documents) immediately returns `false`.
   - **Strict Invariant**: A closed project is permanently read-only and cannot be reopened. If additional delivery is required, a follow-on phase or maintenance project must be initialized.
2. **Rate Card Confidentiality**:
   - `rate_per_hour` (client billing rate) and `cost_per_hour` (internal labor cost) are restricted to authorized project managers and administrators via `ProjectMemberPolicy::manage()`. Standard contributors cannot inspect peer cost rates.
3. **Branch Isolation**:
   - Auto-generated project codes (`PRJ-0001`) and task linking to Production Orders or Purchase Requisitions strictly enforce branch boundaries. A project manager in Branch A cannot link or view manufacturing orders from Branch B.
