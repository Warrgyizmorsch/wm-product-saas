# Project Management Module — Domain Glossary & Terminology

This glossary defines technical, operational, and architectural terms used across the Project Management module in `wm-product-saas`. Definitions reflect the actual codebase implementation.

---

### Activity Log (`ActivityLog`)
An immutable audit log record created whenever significant mutations occur on a project (such as task status changes, member additions, or budget updates). Stored in `project_activity_logs`. Captures the actor's `user_id`, action verb, title, description, and metadata JSON.

### Baseline
The approved schedule and financial target established during project kick-off (initial `start_date`, `target_end_date`, and `budget_amount`). Baselines can be formally revised through an approved **Change Request**.

### Billing Type
The contractual billing model associated with a project. Supported values in `projects.billing_type`:
- `Time & Materials`: Billed according to approved labor hours multiplied by member rates.
- `Fixed Price`: Billed at a fixed total sum, typically broken down into milestone payments.
- `Milestone Based`: Billed only upon formal completion and sign-off of discrete deliverables.
- `Non-Billable`: Internal or overhead projects where hours and expenses are not charged to an external client.

### Change Request (`ChangeRequest`)
A formal domain entity used to manage scope alterations. Stored in `project_change_requests`. Tracks title, description, `schedule_impact_days`, `cost_impact_amount`, and status (`Pending`, `Approved`, `Rejected`, `Implemented`). Approving a change request updates the active project baseline.

### Closure Gate
A programmatic validation checkpoint enforced by `ProjectClosureService::evaluateGates()` before a project can transition to `closed`. Evaluates five conditions: zero open tasks & incomplete subtasks, zero unresolved issues, reviews & governance satisfaction, complete billing settlement (zero unbilled approved hours or milestones), and complete milestone conclusion.

### Critical Path
The continuous sequence of dependent tasks that represents the longest overall duration through the project network. Any delay to a task on the critical path directly delays the project delivery date. Critical path tasks have a **Total Float** of zero or less ($\text{Total Float} \le 0$) and are flagged `is_critical = true`.

### Critical Path Method (CPM)
A deterministic mathematical network analysis algorithm implemented in `ProjectScheduleService`. Uses a two-pass calculation (Forward Pass and Backward Pass) over a Directed Acyclic Graph (DAG) to determine Early Dates, Late Dates, and Float values.

### Customer (CRM Canonical)
The client organization or individual receiving project deliverables. Sourced from the **CRM** module (`App\Domains\CRM\Models\Customer`). The `projects` table stores a foreign key `customer_id`.

### Dependency (`TaskDependency`)
A relational constraint linking two tasks in a directed sequence. Stored in `project_task_dependencies`. Characterized by:
- **Task ID (`task_id`)**: The dependent successor task.
- **Depends On Task ID (`depends_on_task_id`)**: The predecessor task controlling timing.
- **Relationship Type (`dependency_type`)**: Finish-to-Start (`Finish-to-Start`), Start-to-Start (`Start-to-Start`), Finish-to-Finish (`Finish-to-Finish`), or Start-to-Finish (`Start-to-Finish`).
- **Lag Days (`lag_days`)**: Delay or lead offset applied to the relationship.

### Early Finish (EF) & Early Start (ES)
- **Early Start (ES)**: The earliest possible date a task can begin based on the completion of its predecessors.
- **Early Finish (EF)**: The earliest possible date a task can finish ($ES + \text{Duration} - 1$).

### Free Float
The amount of time a task can be delayed without delaying the **Early Start** of any immediate successor task.

### Gantt Chart
An interactive graphical timeline displaying tasks as horizontal bars plotted against calendar dates. Visualizes WBS groupings, progress percentages, dependency connector lines, and critical path highlights.

### Issue (`Issue`)
A defect, blocker, or risk reported against a project or specific task. Stored in `project_issues`. Characterized by `severity` (`Minor`, `Major`, `Critical`), `priority` (`Low`, `Medium`, `High`, `Critical`), and `status` (`Open`, `Assigned`, `In Progress`, `Resolved`, `Closed`).

### Lag Days
An offset in calendar days applied to a task dependency:
- **Positive Lag**: A mandatory waiting period after the predecessor event (e.g., waiting 3 days for paint to dry).
- **Negative Lag (Lead Time)**: An acceleration allowing the successor to begin before the predecessor fully concludes.

### Late Finish (LF) & Late Start (LS)
- **Late Finish (LF)**: The latest possible date a task can finish without postponing the overall project completion date.
- **Late Start (LS)**: The latest possible date a task can start ($LF - \text{Duration} + 1$).

### Milestone (`Milestone`)
A major project event marking the completion of a significant phase, deliverable, or decision point. Stored in `project_milestones`. Milestones have zero planned labor duration but can carry a contractual `billing_amount`.

### Multi-Tenant Scoping
The security framework ensuring complete organizational data isolation. Enforced via `App\Core\Database\BaseModel` traits (`tenant_id`, `company_id`, `branch_id`) and route middleware (`tenant.context`).

### Project (`Project`)
The root domain aggregate representing a planned enterprise undertaking with defined start dates, end dates, budget, customer, manager, and lifecycle status. Stored in `projects`.

### Project Member (`ProjectMember`)
An associative entity linking an ERP `User` to a specific `Project`. Stored in `project_members`. Defines the collaborator's project role (`project_role`), billable `rate_per_hour`, internal `cost_per_hour`, and `budget_hours`.

### Project Review (`ProjectReview`)
A formal review checkpoint evaluating deliverables. Stored in `project_reviews`. Categorized by `review_type` (`Internal`, `Client UAT`, `Milestone Gate`) and recorded with formal status (`Pending`, `Approved`, `Rework Required`).

### Sales Invoice Creation Service (`SalesInvoiceCreationService`)
The domain service residing in `App\Domains\Sales\Services\` responsible for generating official ERP sales invoices. Project Management delegates billing execution to `SalesInvoiceCreationService::createDraftInvoice()` rather than writing directly to billing tables.

### Subtask (`SubTask`)
A checklist line item subordinate to a parent `Task`. Stored in `project_sub_tasks`. Used to break down work into discrete verifiable steps. Checking off subtasks automatically updates the parent task's completion percentage.

### Task (`Task`)
The primary unit of executable work within a project. Stored in `project_tasks`. Belongs to a `TaskList`, tracks `estimated_hours`, `actual_hours`, `priority`, `status`, and scheduling parameters (`start_date`, `due_date`, `total_float`, `is_critical`).

### Task List (`TaskList`)
A grouping container used to structure tasks into phases, work packages, or sprint backlogs. Stored in `project_task_lists`. Supports sort position reordering.

### Time Log (`TimeLog`)
A record of labor time spent by a team member on a specific task. Stored in `project_time_logs`. Stores date, decimal hours, billability flag, hourly billing rate, and approval status (`Pending`, `Approved`, `Rejected`).

### Total Float
The amount of time a task can be delayed from its Early Start without delaying the project completion date ($\text{Total Float} = LS - ES = LF - EF$). Tasks with $\text{Total Float} \le 0$ are on the Critical Path.

### Work Breakdown Structure (WBS)
A hierarchical decomposition of total project scope into manageable deliverables. In this ERP, WBS is structured as **Project → Task Lists → Tasks → Subtasks**.
