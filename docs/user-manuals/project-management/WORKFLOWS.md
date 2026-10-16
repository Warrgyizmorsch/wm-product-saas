# Project Management Module — End-to-End Operational Workflows

## 1. Project Lifecycle & Canonical State Machine

Projects follow a strictly governed state machine from scoping to formal closure. Transitions are managed by `ProjectService` and guarded by authorization policies.

```mermaid
stateDiagram-v2
    [*] --> Draft: Created via /projects/create (Quick Create)
    [*] --> Active: Direct Store / Sales Order Conversion
    Draft --> Active: Project Kickoff / Tasks Activated
    Active --> On_Hold: Operational Pause / Temporarily Blocked
    On_Hold --> Active: Resumed Execution
    Active --> Completed: Deliverables Finished / Ready for Closure Audit
    Completed --> Closed: All 5 Closure Gates Cleared (ProjectClosureService)
    Draft --> Cancelled: Cancelled Prior to Kickoff
    Active --> Cancelled: Cancelled by Management
    On_Hold --> Cancelled: Cancelled by Management
    Closed --> [*]: Permanent Read-Only Immutability
    Cancelled --> [*]: Terminal State
```

### Canonical Lifecycle States:
1. **Draft**: Initial scoping state. Child deliverables (tasks, milestones) can be drafted without triggering external notifications.
2. **Active**: Normal execution state. Team members log time, execute tasks, report issues, and link shop-floor production jobs.
3. **On Hold**: Temporarily paused. Time logging and scheduling updates are restricted.
4. **Completed**: All delivery work has concluded. Awaiting formal closure audit.
5. **Closed**: Terminal state. Can only be reached if all **5 Quality & Financial Gates** pass. Once closed, the project and all child records are permanently read-only and immutable.
6. **Cancelled**: Abandoned or terminated project. Cannot be reopened.

---

## 2. Commercial Sales Order Conversion Workflow

Delivery projects can be initiated directly from confirmed commercial contracts:

```mermaid
sequenceDiagram
    autonumber
    actor PM as Project Manager
    participant UI as Create Project Modal (/projects/create)
    participant Ctrl as ProjectController
    participant Bridge as SalesOrderProjectBridgeService
    participant SO as SalesOrder (Sales Domain)
    participant DB as Database (Projects Domain)

    PM->>UI: Select Confirmed Sales Order from Dropdown
    UI->>Ctrl: POST /projects (store) with sales_order_id
    Ctrl->>SO: Verify order->status === 'Confirmed'
    Ctrl->>SO: Verify order has no existing projects (whereDoesntHave)

    Ctrl->>Bridge: convertSalesOrderToProject($order, $data)

    Bridge->>DB: Create Project (inherits customer_id, crm_deal_id, client_contact_id, budget_amount)
    Bridge->>DB: Create Task List ('Fulfillment & Delivery')

    loop For Each SalesOrderItem
        Bridge->>DB: Create Milestone ('Deliver: ' + item_name, billing_amount)
        Bridge->>DB: Create Task ('Fulfill ' + item_name, milestone_id, task_list_id)
    end

    Bridge-->>Ctrl: Return Created Project
    Ctrl-->>PM: Redirect to /projects/{project} with Success Banner
```

### Conversion Safeguards:
- **Eligibility**: Only orders with `status === 'Confirmed'` appear in the selection dropdown and pass backend validation.
- **Duplicate Protection**: A Sales Order cannot be converted twice; `whereDoesntHave('projects')` filters the list, and pre-store validation rejects duplicate attempts.
- **Traceability**: Source Sales Order, CRM Deal, and Client Contact references are persisted directly onto the project header.

---

## 3. Work Breakdown, Task Execution & Subtasks

Tasks enforce structured execution rules to prevent skipping stages:

```mermaid
flowchart TD
    Start([Task Created: Status = 'Open']) --> Assign[Assigned to Active Project Member]
    Assign --> StepInProg[Assignee Starts Work: Status = 'In Progress']

    StepInProg --> DirectCompCheck{Attempt to Skip Directly to 'Completed'?}
    DirectCompCheck -- Yes --> RejectJump[Blocked by Workflow: Must Move Through In Progress]
    DirectCompCheck -- No --> CheckDeps{Predecessors Finished?}

    CheckDeps -- Blocked by Dependency --> BlockStart[Status Change Blocked by Dependency Engine]
    CheckDeps -- Satisfied --> ExecTask[Execute Work]

    ExecTask --> HasSub{Subtasks Exist?}
    HasSub -- Yes --> ToggleSub[Toggle Subtask Checkbox: Sets completed_at]
    ToggleSub --> DynCalc[Dynamically Compute Subtask Progress %]
    DynCalc --> SubRule[Rule: Completing Subtasks Does NOT Auto-Complete Task]
    SubRule --> SendReview

    HasSub -- No --> SendReview[Transition to 'Under Review']
    SendReview --> PMReview{Lead / PM Review}
    PMReview -- Rework Required --> StepInProg
    PMReview -- Approved --> MarkComp[Status = 'Completed', Sets completed_at]
    MarkComp --> RecalcMile[MilestoneService::recalculateProgress]
    RecalcMile --> EndTask([Task Finished])
```

### Execution Rules:
1. **Sequential Progression**: Allowed task flow is `Open` → `In Progress` → `Under Review` → `Completed` (or `Cancelled`). Skipping directly from `Open` to `Completed` is prohibited.
2. **Subtask Independence**: Subtask checklist completion percentage is **computed dynamically** in real time (`(completed / total) * 100`). Completing all subtasks does **not** automatically mark the parent task completed.
3. **Dependency Blocking**: A task cannot transition to `In Progress` or `Completed` if upstream predecessor dependencies (FS, SS, FF, SF) are unsatisfied, unless overridden by an authorized manager.

---

## 4. Manufacturing & Procurement Task Integration Workflow

Project Management tasks connect directly to shop-floor Production Orders and Purchase Requisitions:

```mermaid
flowchart TD
    subgraph TaskWorkspace [Task Workspace: /projects/{project}/tasks/{task}]
        Task[Task: Fulfill CNC Machine]
        LinkPO[Action: Link Production Order]
        LinkPR[Action: Link Purchase Requisition Item]
    end

    subgraph Validation [Scope Guards]
        BranchCheck{Same Company & Branch as Project?}
    end

    subgraph Execution [Shop Floor & Procurement]
        PRItem[Purchase Requisition Line: project_task_id set]
        PO1[Production Order 1: MO-001]
        PO2[Production Order 2: MO-002]
        ShopComp[Shop Floor Marks Order Completed]
    end

    subgraph SyncEngine [SyncProductionTaskCompletionListener]
        EvalOrders[Evaluate All Production Orders Linked to Task]
        CheckAllDone{All Linked Orders Completed?}
        CheckYield{Aggregate Produced Yield >= Total Ordered Quantity?}
        AutoTask[Set Task status = Completed, completed_at = NOW]
        Recalc[MilestoneService::recalculateProgress]
        ShortfallWarn[Task Remains In Progress\nLog Activity Warning: production.shortfall_warning]
    end

    LinkPO --> BranchCheck
    LinkPR --> BranchCheck
    BranchCheck -- Mismatch --> AbortLink[ValidationException: Scope Mismatch]
    BranchCheck -- Valid --> PRItem
    BranchCheck -- Valid --> PO1
    BranchCheck -- Valid --> PO2

    PO1 --> ShopComp
    PO2 --> ShopComp
    ShopComp --> EvalOrders
    EvalOrders --> CheckAllDone

    CheckAllDone -- No: Sibling Order Open --> ShortfallWarn
    CheckAllDone -- Yes --> CheckYield
    CheckYield -- No: Scrap/Shortfall --> ShortfallWarn
    CheckYield -- Yes: Yield Satisfied --> AutoTask
    AutoTask --> Recalc
```

### Key Integration Rules:
1. **Scope Protection**: Linked Production Orders and Purchase Requisitions must belong to the **same company and branch** as the parent project.
2. **Aggregate Yield Completion Rule**:
   - The task is marked `Completed` **only** when all linked Production Orders reach `Completed` AND total good produced units satisfy ordered quantity:
     $$\sum \text{quantity\_produced} \ge \left(\sum \text{quantity\_ordered} - 0.0001\right)$$
   - If scrap causes an aggregate shortfall, the task remains open and records a warning note in the project activity audit drawer.
3. **Unlinked Orders**: Production Orders with no project task link (`project_task_id = null`) execute normally without project overhead.

---

## 5. Timeline, CPM & Interactive Rescheduling

The Gantt timeline (`/projects/{project}?tab=timeline` or `/projects/{project}/timeline`) provides interactive scheduling:

```mermaid
flowchart TD
    UserDrag[User Adjusts Task Dates on Gantt Timeline] --> ModeSelect{Selected Reschedule Mode}

    ModeSelect -- Isolated Mode --> IsoCheck{Violates Predecessor or Successor Boundaries?}
    IsoCheck -- Yes --> RejectIso[Reject Adjustment: Boundary Violation Error]
    IsoCheck -- No --> SaveIso[Update Task Dates Independently]

    ModeSelect -- Ripple Mode --> CalcDelta[Calculate Date Delta]
    CalcDelta --> ShiftSuccessors[Shift Downstream Successor Tasks by Delta]
    ShiftSuccessors --> SafeCompleted[Guard Rule: Completed Tasks are NEVER Modified]
    SafeCompleted --> PersistRipple[Transactionally Update Successor Chain]

    SaveIso --> RecalcCPM[Run 2-Pass CPM Engine: Recalculate Floats]
    PersistRipple --> RecalcCPM
    RecalcCPM --> UpdateCrit[Update Critical Path: Highlight Zero-Float Tasks]
```

### Rescheduling Modes:
- **Isolated Mode**: Moves the selected task independently. Rejects changes that violate predecessor finish dates or successor start dates.
- **Ripple Mode**: Automatically shifts all downstream successor tasks by the same delta, preserving lag buffers while strictly safeguarding already `Completed` tasks from accidental modification.

---

## 6. Time Tracking & Timesheet Approvals

```mermaid
flowchart TD
    LogStart([User Logs Hours Worked on Task]) --> ValTime{Validate: min 0.01h, max 24.00h, Active Member?}
    ValTime -- Invalid --> ShowErr[Display Form Validation Error]
    ValTime -- Valid --> LookRate[Inherit Member rate_per_hour]
    LookRate --> SavePending[Save project_time_logs: approval_status = 'Pending']
    SavePending --> EmitEvt[Dispatch TimesheetSubmitted Event]

    EmitEvt --> PMQueue[Manager Navigates to /projects/timesheets/approval]
    PMQueue --> PMDecision{Manager Decision}

    PMDecision -- Reject --> EnterNotes[Enter Mandatory Rejection Feedback]
    EnterNotes --> MarkRej[approval_status = 'Rejected': Dispatches TimesheetRejected]

    PMDecision -- Approve --> MarkAppr[approval_status = 'Approved': Dispatches TimesheetApproved]
    MarkAppr --> LockLog[Permanently Lock Record Against Modification]
    MarkAppr --> ReadyBill[Eligible for Client Invoicing & Closure Gate 4]
```

---

## 7. Project Billing & Invoicing Bridge Workflow

```mermaid
flowchart TD
    StartBill([User Opens /projects/{project}?tab=billing]) --> ClickGen[Click 'Generate Invoice']
    ClickGen --> AuthCheck{Dual Permission Check:\nprojects.billing.generate_invoice AND sales.invoices.create?}
    AuthCheck -- No --> DenyAuth[HTTP 403: Forbidden]
    AuthCheck -- Yes --> PrereqCheck{Prerequisites Met:\nCustomer Assigned AND Active Service Product Exists?}
    PrereqCheck -- No --> AbortPrereq[Display Prerequisite Validation Alert]
    PrereqCheck -- Yes --> OpenModal[Display Generate Invoice Modal]

    OpenModal --> SelectItems[Select Unbilled Approved TimeLogs & Completed Milestones]
    SelectItems --> SubmitGen[Submit Invoice Generation]

    SubmitGen --> DBTrans[DB::transaction with lockForUpdate]
    DBTrans --> CheckAlreadyInv{Any Selected Item Already Invoiced?}
    CheckAlreadyInv -- Yes --> AbortDoubleBill[Throw InvalidArgumentException: Double-Billing Guard]
    CheckAlreadyInv -- No --> CallSales[SalesInvoiceCreationService::createDraftInvoice]

    CallSales --> CreateInv[Insert Sales Invoice: status = 'Draft', project_id = project.id]
    CreateInv --> FlagItems[Update time_logs & milestones: is_invoiced = true, invoice_id = inv.id]
    FlagItems --> SuccessBan[Display Draft Invoice Link in Billing Tab]

    SuccessBan --> PostSales{Sales Officer Verifies & Posts Invoice}
    PostSales --> DispatPost[Dispatch InvoicePosted Event]
    DispatPost --> AccListener[Sales Listener: PostSalesInvoiceJournal]
    AccListener --> PostGL[Write General Ledger Double-Entry in Accounting]
```

---

## 8. Controlled Project Closure Workflow (The 5 Gates)

Project closure enforces formal audit verification via `ProjectClosureService`:

```mermaid
flowchart TD
    ReqClose([PM Clicks 'Close Project' on Show Page]) --> Gate1{Gate 1: Tasks Clean?\nZero Open/Active Tasks & Zero Incomplete Subtasks}
    Gate1 -- Fail --> Block1[Block Closure: Complete or Cancel All Tasks]
    Gate1 -- Pass --> Gate2{Gate 2: Issues Resolved?\nZero Open/Active Issues}
    Gate2 -- Fail --> Block2[Block Closure: Resolve or Close All Issues]
    Gate2 -- Pass --> Gate3{Gate 3: Reviews Signed Off?\nLatest Review Approved & Zero Pending Reviews/CRs}
    Gate3 -- Fail --> Block3[Block Closure: Complete UAT Signoff & Clear Pending CRs]
    Gate3 -- Pass --> Gate4{Gate 4: Financial Settlement?\nZero Unbilled Approved Hours, Zero Pending Timesheets, Zero Unbilled Milestones}
    Gate4 -- Fail --> Block4[Block Closure: Invoice Approved Hours or Settle Timesheets]
    Gate4 -- Pass --> Gate5{Gate 5: Milestones Delivered?\nZero Incomplete Milestones}
    Gate5 -- Fail --> Block5[Block Closure: Complete All Milestone Deliverables]
    Gate5 -- Pass --> OpenModal[Display Closure Modal: Closure Status, Client Ref, Final Remarks]

    OpenModal --> ExecClose[ProjectClosureService::close Transaction]
    ExecClose --> SetClosed[Set status = 'Closed', closure_date = NOW, closed_by = user.id]
    SetClosed --> EmitClose[Dispatch ProjectClosed Event & Record Activity Log]
    SetClosed --> LockAll[Enforce Read-Only Immutability Across All Policies]
```

### The 5 Closure Gates:
1. **Gate 1 — Tasks Clean**: All tasks must be `Completed` or `Cancelled`, and all subtasks completed.
2. **Gate 2 — Issues Resolved**: All defects must be `Resolved` or `Closed`.
3. **Gate 3 — Formal Review Signoff**: If milestones exist, at least one approved review is required. No reviews or change requests may be `Pending`.
4. **Gate 4 — Financial Settlement**: Zero unbilled approved billable time logs, zero pending timesheets, and zero unbilled completed milestones.
5. **Gate 5 — Milestones Delivered**: All milestones must be in `Completed` or `Closed` status.
6. **Permanent Immutability**: Once `Closed`, all domain policies reject further mutations. Closed projects cannot be reopened.
