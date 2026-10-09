# Project Management Module — Frequently Asked Questions (FAQ)

## 1. General & Portfolio Governance

### Q1.1: Can I create internal projects without linking an external CRM client?
**Yes.** The `customer_id` field on the project master is entirely optional. When creating non-client engagements (such as internal IT migrations, research and development, or corporate process initiatives), set the Project Type to `Internal` and leave the Customer field blank. The system will bypass client billing checks.

### Q1.2: What happens if I leave the Project Code blank during creation?
If you do not specify an explicit code, `ProjectService` automatically generates a standardized sequence number following the pattern `PRJ-YYYY-XXXX` (where `YYYY` is the current calendar year and `XXXX` is a zero-padded incremental sequence scoped to the tenant).

### Q1.3: How does the system ensure multi-tenant data isolation?
Every domain model in the Project module extends `App\Core\Database\BaseModel`, which applies global tenant scoping traits (`BelongsToTenant`, `BelongsToCompany`, `BelongsToBranch`). All queries automatically append `WHERE tenant_id = :current_tenant_id`. Additionally, the web routing middleware (`tenant.context`) rejects any request attempting to access resources outside the authenticated user's organization.

---

## 2. Customer Master & CRM Boundaries

### Q2.1: Can I create a new CRM customer directly from within the Project Management module?
**No.** By architectural design, the **CRM** module owns the customer lifecycle. To maintain data hygiene and prevent duplicates, customers must be created and approved within CRM. The Project Management creation form provides an autocomplete lookup that searches active CRM customers.

### Q2.2: What happens if a customer is deactivated in CRM while an active project is underway?
The existing project retains its relational link to the customer ID so that historical records and open invoices remain intact. However, the autocomplete endpoint will exclude deactivated customers when creating new projects.

---

## 3. Team Members, Roles & Rate Cards

### Q3.1: Why can't I find an employee in the Add Member dropdown?
The Add Member dropdown queries active user accounts (`App\Models\User`) registered within your tenant. If an individual does not appear, ensure that:
1. An active ERP user account has been provisioned for them.
2. The user has not been deactivated or suspended by an Administrator.
3. The user belongs to the active tenant workspace.

### Q3.2: Can a team member have different hourly billing rates on different projects?
**Yes.** Billing and cost rates are defined at the **project member level** (`project_members` table), not globally on the user profile. A Senior Architect may be billed to Client A at $150/hr and to Client B at $180/hr depending on project contract agreements.

### Q3.3: What is the difference between Hourly Rate and Cost Rate?
- **Rate Per Hour (`rate_per_hour`)**: The billable rate charged to the client per hour of approved work. Used when calculating sales invoice line items.
- **Cost Per Hour (`cost_per_hour`)**: The internal estimated labor cost paid to or incurred by the team member per hour. Used in the **Budget & Cost Variance Report** to compute internal project margins.

---

## 4. Tasks, Dependencies & Gantt Scheduling

### Q4.1: Can I delete a task if team members have already logged time against it?
**No.** To preserve financial and operational audit trails, the system forbids deleting tasks that have associated `project_time_logs`. If the task is no longer required, change its status to `Cancelled`. This removes it from active backlogs while preserving historical timesheets.

### Q4.2: What is Total Float and why is a task highlighted in red on the Gantt chart?
**Total Float** is the amount of time a task can be delayed from its Early Start without delaying the overall project completion date. Tasks with a Total Float of zero or less ($\text{Total Float} \le 0$) reside on the **Critical Path**. They are highlighted in red on the Gantt view because any slippage on these tasks directly postpones project delivery.

### Q4.3: What types of task dependencies does the system support?
The system supports four standard Precedence Diagramming Method (PDM) relationship types:
- **Finish-to-Start (FS)**: Task B cannot start until Task A finishes (most common).
- **Start-to-Start (SS)**: Task B cannot start until Task A starts.
- **Finish-to-Finish (FF)**: Task B cannot finish until Task A finishes.
- **Start-to-Finish (SF)**: Task B cannot finish until Task A starts.

### Q4.4: How does lead/lag time work in task dependencies?
Lag is specified in calendar days:
- **Positive Lag (+X)**: Adds a mandatory delay (e.g., FS + 3 days for concrete curing).
- **Negative Lag (-X / Lead)**: Allows the successor task to overlap and start before the predecessor finishes.

---

## 5. Time Tracking & Timesheet Approvals

### Q5.1: Can an employee modify a time log after it has been approved?
**No.** Once a Project Manager marks a time log as `Approved`, it becomes locked against modifications by the submitter. If an error is identified, an Administrator or Project Manager must reject the log or make the adjustment directly.

### Q5.2: Can a Project Manager approve their own submitted timesheets?
To ensure compliance with corporate governance and segregation of duties, the system enforces approval segregation: if a Project Manager logs hours on a project, another designated manager or a System Administrator must review and approve the entry.

### Q5.3: Are unbilled time logs lost if an invoice is generated for milestones only?
**No.** The billing engine explicitly tracks `is_invoiced` as a boolean flag on each individual `project_time_logs` record and `project_milestones` record. If you generate a milestone-based invoice, only the selected milestones are marked `is_invoiced = true`. Approved time logs remain unbilled and ready for future invoicing.

---

## 6. Billing, Invoices & Accounting

### Q6.1: Does the Project Management module create General Ledger journal entries?
**No.** Project Management has no direct coupling with the General Ledger. When you click **Generate Invoice**, the module aggregates unbilled work and invokes `SalesInvoiceCreationService::createDraftInvoice()` in the **Sales** domain. Sales creates the official draft invoice. When the Sales department posts the invoice, Sales's listener `PostSalesInvoiceJournal` reacts to the `InvoicePosted` event and delegates to `SalesAccountingService::postInvoiceJournal()` to book the double-entry accounting journals.

### Q6.2: What happens if a sales invoice generated from a project is cancelled or voided in Sales?
If a draft invoice is deleted or cancelled prior to posting, the Project Manager can reopen the project billing screen to inspect the associated logs. If an invoice is cancelled after posting, standard credit note procedures in Sales and Accounting apply.

---

## 7. Project Closure & Quality Gates

### Q7.1: Can a closed project be reopened?
A closed project is read-only by default. If a project was closed prematurely or scope is added post-handover, an Administrator with `projects.projects.edit` privileges can reopen the project by transitioning its status back to `active`.

### Q7.2: What are the five mandatory gates evaluated during closure?
`ProjectClosureService::evaluateGates()` programmatically evaluates **five strict condition gates**:
1. **Gate 1: Tasks & Subtasks**: Zero open/in-progress tasks (`Completed` or `Cancelled` only) and zero incomplete subtasks.
2. **Gate 2: Issues**: Zero unresolved issues (`Resolved` or `Closed` only).
3. **Gate 3: Reviews & Governance**: If milestones exist, at least one approved review must exist; zero pending reviews and zero pending change requests.
4. **Gate 4: Billing Settlement (Hard Blocker)**: Zero unbilled approved billable time logs, zero pending timesheets, and zero unbilled completed milestones.
5. **Gate 5: Milestone Completion**: Zero open or uncompleted milestones.

---

## 8. Cross-Module Handoffs: Production & Purchase

### Q8.1: Can I trigger a Production Work Order directly from a project task?
**Not automatically in the current release.** Production Orders do not contain a `project_id` foreign key, and exhaustive code search confirms zero project integrations in the Production domain. If a project requires custom physical manufacturing, the standard ERP business practice is to raise a Sales Order linked to the client; Production then executes the Work Orders against that Sales Order.

### Q8.2: Can procurement expenses from Purchase Orders be allocated to a project?
Currently, there is no direct foreign key on Purchase Orders or Purchase Requisitions linking them to project tasks. Procurement expenses incurred for project materials can be recorded manually as financial adjustments or tracked through standard departmental expense reports.
