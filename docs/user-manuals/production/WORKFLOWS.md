# Manufacturing Workflows & Process Lifecycles

> **Module:** Production Planning & MES (`wm-product-saas`)  
> **Target Document:** `docs/user-manuals/production/WORKFLOWS.md`  
> **Audience:** Manufacturing Consultants, ERP Business Analysts, Developers, Process Engineers  
> **Architecture Pattern:** Verified Implementation Lifecycles

---

## 1. Overview of Core Manufacturing Workflows

This document models the complete set of verified, end-to-end manufacturing process lifecycles in the `wm-product-saas` Production module. Each workflow illustrates the real interplay between user actions, domain services, database transactions, and cross-module integrations.

```mermaid
graph TD
    W1["1. Demand-to-Order (Planning & MRP)"] --> W2["2. Execution-to-Finished Goods"]
    W2 --> W3["3. Work-in-Progress (WIP) Logistics"]
    W2 --> W4["4. Quality Control, NCR, Rework & Scrap"]
    W1 & W2 --> W5["5. Bidirectional Lot & Serial Traceability"]
    W2 --> W6["6. Plant Maintenance & Equipment Reliability"]
    W2 & W4 --> W7["7. Inventory & Accounting Effects"]
    W2 & W8["8. Make-to-Project Order Execution & Milestone Synchronization"] --> W7
```

---

## 2. Workflow 1: Demand to Production Order (Planning & MRP)

### 2.1 Process Flow Diagram

```mermaid
flowchart TD
    Start(["Sales Order Demand / Forecast"]) --> Plan["Create Production Plan (ProductionPlan)"]
    Plan --> SubmitPlan["Submit for Approval (status: pending_approval)"]
    SubmitPlan --> ApprovePlan["Approve Plan (status: approved)"]
    ApprovePlan --> RunMRP["Run MRP Engine (MrpEngineService)"]
    
    RunMRP --> ExplodeBOM["Explode Multi-Level BOM Items"]
    ExplodeBOM --> CheckStock["Query ProductWarehouseStock"]
    CheckStock --> ShortageCheck{"Stock Shortage Detected?"}
    
    ShortageCheck -- Yes --> LogShortage["Create MrpShortage Record"]
    LogShortage --> RaisePR["Generate Purchase Requisition (Procurement)"]
    RaisePR --> POVendor["Purchase Order to Supplier"]
    POVendor --> GRNStock["Warehouse GRN Inflow"]
    GRNStock --> ReleasePlan
    
    ShortageCheck -- No --> ReleasePlan["Release Production Plan (status: released)"]
    ReleasePlan --> SpawnOrders["Generate Production Orders (ProductionOrder)"]
    SpawnOrders --> ReserveStock["Insert ProductionOrderReservations (Stock Reserved)"]
    ReserveStock --> End(["Ready for Shopfloor Release"])
```

### 2.2 Workflow Breakdown
* **Initiating Action:** A customer Sales Order is confirmed in the Sales module, or a planner creates an aggregate replenishment forecast in `/production/plans`.
* **System Components:** `ProductionPlanController`, `ProductionPlanService`, `MrpEngineService`, `MrpShortageService`, `StockService`.
* **Decision Points:**
  * If on-hand stock is sufficient: The plan transitions directly to `released`.
  * If shortages exist: Requirements are logged in `production_plan_requirements`. The planner generates external Purchase Requisitions through `/production/mrp/shortages`.
* **Status Transitions:**
  * `ProductionPlan`: `draft` → `pending_approval` → `approved` → `released` → `in_progress` → `completed`.
* **Data Created/Updated:** `production_plans`, `production_plan_requirements`, `production_orders`, `production_order_reservations`.
* **Final Outcome:** Manufacturing orders are created and scheduled, with raw materials safely reserved against competing orders.

---

## 3. Workflow 2: Order Release to Finished Goods Receipt

### 3.1 Process Flow Diagram

```mermaid
sequenceDiagram
    autonumber
    actor Planner
    actor Storekeeper
    actor Operator
    actor QC
    participant Order as ProductionOrder
    participant Stock as StockService (Inventory)
    participant MES as MesExecutionService
    participant Quality as QualityInspectionService

    Planner->>Order: Release Order (status: released)
    Planner->>Storekeeper: Issue Material Requisition Slip
    Storekeeper->>Order: POST /orders/{order}/issue
    Order->>Stock: StockService::recordOutflow() (decrement warehouse on_hand)
    Order-->>Order: Status -> in_progress

    Operator->>MES: Start Operation #10 (POST /mes/{op}/start)
    Note over Operator,MES: Machine state -> Active, Live timer running
    Operator->>MES: Complete Operation #10 (POST /mes/{op}/complete)
    MES->>Quality: Auto-create Quality Inspection Trigger

    QC->>Quality: Inspect parameters (POST /quality/inspections/{id}/results)
    Quality-->>MES: Inspection Passed

    Operator->>MES: Complete Final Operation (Packing)
    MES->>Order: POST /orders/{order}/receive-fg
    Order->>Stock: StockService::recordInflow() (increment FG warehouse on_hand)
    Order-->>Order: Status -> completed
```

### 3.2 Workflow Breakdown
* **Initiating Action:** Planner clicks **Release Order** on `/production/orders/{order}`.
* **Material Issuance:** Storekeeper uses **Issue Material**. Physical inventory is decremented via `StockService::recordOutflow()`, and status updates to `in_progress`.
* **MES Shopfloor Execution:** Operators interact with `/production/mes`. Timers record machine and labor runtime.
* **Finished Goods Intake:** Upon the final operation, `ProductionExecutionService::receiveFinishedGoods()` writes an inflow `StockTransaction` to the designated finished goods warehouse.
* **Quarantine Handling:** If quality inspection indicates `quarantine`, the inflow automatically posts to a quarantine storage location rather than commercial stock.

---

## 4. Workflow 3: Partial Operation Completion & Work-In-Progress (WIP)

### 4.1 Process Flow Diagram

```mermaid
flowchart TD
    BatchRun["Order Batch: 1,000 Units at Sequence #10 (Stamping)"] --> StartOp["Operator Starts Stamping"]
    StartOp --> PartialLog["Log Partial Progress: 400 Units Produced"]
    
    PartialLog --> CreateWIP["Insert / Update ProductionWip Record"]
    CreateWIP --> InterBuffer["Hold in Work Center Output Buffer"]
    
    InterBuffer --> TransferChoice{"Transfer to Next Work Center?"}
    TransferChoice -- Yes --> StageTransfer["POST /production/wip/{wip}/transfer"]
    StageTransfer --> DownstreamWC["Sequence #20 (Welding) Input Buffer"]
    DownstreamWC --> StartSeq20["Operator Starts Sequence #20 on 400 Units"]
    
    TransferChoice -- No --> HoldBuffer["Hold in Stamping Cell"]
    
    StartSeq20 --> FinalCheck{"Is Sequence #20 Final Stage?"}
    FinalCheck -- Yes --> ConvertFG["POST /production/wip/{wip}/convert"]
    ConvertFG --> FGWarehouse["Receipt into Finished Goods Warehouse Stock"]
    FinalCheck -- No --> NextWIP["Transfer to Subsequent Work Center Buffer"]
```

### 4.2 Workflow Breakdown
* **Initiating Action:** Operator logs partial output on a large order batch before the total run is complete.
* **System Components:** `MesController`, `MesExecutionService`, `WipController`, `ProductionWipService`.
* **Data Flow:**
  * Partial completions create `production_order_progress_logs` and update `production_wips`.
  * `ProductionWipTransaction` tracks movement history between plant work centers.
* **Business Benefit:** Eliminates "black hole" manufacturing by providing visibility into where semi-finished parts reside across physical departments.

---

## 5. Workflow 4: Quality Rejection, Non-Conformance, Rework & Scrap

### 5.1 Process Flow Diagram

```mermaid
flowchart TD
    QC["QC Inspection on Operation Batch"] --> Evaluate{"Tolerances Acceptable?"}
    Evaluate -- Pass --> AcceptQty["Record Good Quantity (Proceed to next stage)"]
    
    Evaluate -- Fail --> CreateNCR["Auto-Generate NCR (production_ncrs)"]
    CreateNCR --> AssignDisp{"Disposition Decision"}
    
    AssignDisp -- Rework --> CreateRework["Create Rework Order (RWK-XXXXX)"]
    CreateRework --> ExecRework["Execute Rework Operations (ReworkService)"]
    ExecRework --> ReQC{"Re-Inspection Pass?"}
    ReQC -- Yes --> ReturnToWIP["Re-integrate into Main Production Order WIP"]
    ReQC -- No --> EscalateScrap["Rework Failed -> Escalate to Scrap"]
    
    AssignDisp -- Operational Scrap --> LogScrap["POST /production/mes/{op}/scrap"]
    EscalateScrap --> LogScrap
    LogScrap --> OutflowScrap["Record Scrap Outflow (StockService::recordOutflow)"]
    OutflowScrap --> WriteDown["Write down order material efficiency"]
    
    AssignDisp -- Deviation / Use As Is --> CreateDev["Request Engineering Deviation"]
    CreateDev --> DevApproval["Management Sign-off (production_deviations)"]
    DevApproval --> AcceptQty
```

### 5.2 Workflow Breakdown
* **Initiating Action:** An inspection check records dimensional deviation or cosmetic defects.
* **System Components:** `QualityInspectionService`, `NcrService`, `ReworkService`, `ScrapService`, `StockService`.
* **Decision Paths:**
  * **Rework:** Spawns a dedicated `ProductionReworkOrder` with specialized repair operations. If rework fails, `ReworkService::failRework()` converts the damaged parts to operational scrap.
  * **Scrap:** Writes off the scrapped material and decrements shopfloor inventory via `StockService::recordOutflow()`.
  * **Deviation:** Formal deviation document allows controlled acceptance without alteration.

---

## 6. Workflow 5: Bidirectional Lot, Batch & Serial Traceability

### 6.1 Process Flow Diagram

```mermaid
flowchart LR
    subgraph Upstream_Suppliers["1. Raw Material Inflow"]
        SUP["Supplier Lot #SUP-9921"] --> RM["Raw Material Inward GRN"]
    end

    subgraph Production_Execution["2. Manufacturing Run"]
        RM --> ISSUE["Issued to Order #PO-2026-0042"]
        ISSUE --> BATCH["Run in Batch #BATCH-20261009-001"]
        BATCH --> TRACE["production_lot_traces Linking Table"]
    end

    subgraph Finished_Goods["3. Finished Products"]
        TRACE --> FG_BATCH["Finished Goods Lot #FG-LOT-881"]
        FG_BATCH --> SERIAL["Assigned Serial #SN-900214"]
    end

    subgraph Downstream_Sales["4. Customer Fulfillment"]
        SERIAL --> DISP["Delivery Note / Dispatch"]
        DISP --> SO["Sales Order #SO-50012"]
        SO --> CUST["Customer: Global Logistics Inc."]
    end
```

### 6.2 Workflow Breakdown
* **Initiating Action:** Quality investigation or regulatory audit initiated on `/production/mes/traceability`.
* **Trace Directions:**
  * **Forward Recall Trace:** Given defective raw supplier lot `SUP-9921`, the engine navigates forward to identify all batches, serial numbers, and customer sales orders that received the contaminated material.
  * **Backward Root-Cause Trace:** Given customer warranty return `SN-900214`, the engine traverses backward to display the exact machine, operator shift, and raw material supplier lots consumed.

---

## 7. Workflow 6: Plant Maintenance & Equipment Reliability

### 7.1 Process Flow Diagram

```mermaid
flowchart TD
    Trigger{"Maintenance Trigger"} --> |Schedule Due| PM["PM Schedule Next Due Date"]
    Trigger --> |Machine Crashes| BD["Report Emergency Breakdown (POST /breakdown)"]
    
    PM --> GenWO["Generate PM Work Order"]
    BD --> GenDraftWO["Generate Draft Breakdown Work Order"]
    
    GenDraftWO --> StartWO["Technician Clicks Start Work Order"]
    GenWO --> StartWO
    
    StartWO --> LockMachine["Machine Status -> under_maintenance"]
    LockMachine --> BlockMES["MES Shopfloor Terminal Blocks Machine Operations"]
    
    StartWO --> AssignMech["Assign Mechanics with Hourly Wage Rates"]
    StartWO --> AddSpares["Request Maintenance Spare Parts"]
    
    AddSpares --> SlipGen["Auto-create/Append Store Requisition Slip (source_type: maintenance_work_order)"]
    SlipGen --> FulfillPath{"Fulfillment Method"}
    
    FulfillPath --> |Store Queue (/inventory/material-requests)| StoreIssue["Storekeeper: Reserve & Issue via MaterialRequestService"]
    FulfillPath --> |Direct Maintenance Issue| DirectIssue["Maintenance Lead: Issue via MaintenanceSpareService"]
    
    StoreIssue --> StockOut["StockService::recordOutflow(ref: MaintenanceWorkOrder)"]
    DirectIssue --> StockOut
    
    StockOut --> UpdateCosts["Update Spare Costs & Recalculate MWO total_cost"]
    UpdateCosts --> CompleteWO["Complete Work Order"]
    AssignMech --> CompleteWO
    
    CompleteWO --> ScrapOrRestore{"Machine Economically Viable?"}
    ScrapOrRestore -- Restore Active --> Restore["Machine Status -> Active, Downtime Closed"]
    Restore --> UnblockMES["MES Operations Re-Enabled on Machine"]
    
    ScrapOrRestore -- Scrap Machine --> Decom["Machine Status -> Decommissioned"]
    Decom --> PermanentLock["Machine Removed from Available Production Capacity"]
```

### 7.2 Workflow Breakdown
* **Initiating Action:** Calendar schedule trigger or operator reporting an unexpected breakdown.
* **Safety Lockout:** When a machine transitions to `under_maintenance` or `breakdown`, `MesController` actively blocks operators from launching production operations on that machine.
* **Spare Parts Requisition & Dual Fulfillment:**
  * Requesting spare parts on an MWO automatically creates/attaches to a `ProductionRequisitionSlip` with `source_type = 'maintenance_work_order'`. Warehouse assignment is deferred to storekeepers.
  * Storekeepers fulfill through the central Store queue (`/inventory/material-requests`), or authorized maintenance engineers fulfill directly via the MWO interface.
  * In both paths, stock outflow is recorded under reference `MaintenanceWorkOrder`, preventing negative stock and syncing `spare_parts_cost` and `total_cost` on the work order.

---

## 8. Workflow 7: Inventory & Accounting Integration Effects

### 8.1 Process Flow Diagram

```mermaid
flowchart TD
    subgraph Production_Transactions["Production Events"]
        T1["Material Issuance to Order"]
        T2["Operational Scrap Logged"]
        T3["Finished Goods Receipt"]
        T4["Maintenance Spares Issued"]
    end

    subgraph Inventory_StockService["Inventory Ledger (Audited)"]
        T1 --> I1["StockService::recordOutflow('Production Material Issue')"]
        T2 --> I2["StockService::recordOutflow('Production Scrap')"]
        T3 --> I3["StockService::recordInflow('Production Receipt')"]
        T4 --> I4["StockService::recordOutflow('MaintenanceWorkOrder')"]
        
        I1 & I2 & I3 & I4 --> LEDGER["Insert Immutable stock_transactions"]
        LEDGER --> LEVEL["Update product_warehouse_stocks.on_hand & valuation"]
    end

    subgraph Accounting_Module["Accounting / General Ledger"]
        I1 -->|"StockOutflowRecorded Event"| GL_WIP["PostProductionConsumptionJournal<br><b>Dr. Work-in-Progress (1204)</b><br><b>Cr. Raw Material Inventory</b><br>Source: 'production'"]
        
        I3 -.->|"Valuation Updates"| GL_FG["FG Capitalization (Periodic Accounting Valuation / Reconciliation)"]
        
        Note["Note: Material Consumption triggers automated double-entry GL journal vouchers immediately. FG receipts update inventory moving-average valuation without direct automated GL posting."]
    end
```

### 8.2 Summary of Accounting & Financial Boundaries
1. **Automated WIP Accounting Voucher:** When raw materials are issued to a production order, `PostProductionConsumptionJournal` captures `StockOutflowRecorded` and immediately books a General Ledger double-entry voucher: **Debiting Work-in-Progress (Account 1204 / Asset)** and **Crediting Raw Material Inventory**.
2. **Finished Goods Valuation:** Receiving finished goods updates warehouse quantities and weighted-average unit costs in `product_warehouse_stocks`. Capitalization of finished goods and clearing of WIP is performed via periodic accounting inventory valuation adjustments.
3. **Scrap & Maintenance Spares:** Write down physical stock and update operational job costs in base currency; downstream write-off journals are booked through periodic accounting ledger reconciliations.

---

## 9. Workflow 8: Make-to-Project Order Execution & Milestone Synchronization

### 9.1 Process Flow Diagram

```mermaid
flowchart TD
    subgraph Project_Management["Project Management Domain"]
        Task["Project Task (status: IN_PROGRESS)"]
        Milestone["Project Milestone (progress: X%)"]
    end

    subgraph Linkage_Establishment["Relational Linkage & Validation"]
        Task --> ModeA["A. Create New Order via Prefilled URL<br><code>/production/orders/create?project_id=...&project_task_id=...</code>"]
        Task --> ModeB["B. Link Existing Order from Task Drawer<br><code>POST /projects/{project}/tasks/{task}/link-production-order</code>"]
        ModeA & ModeB --> ValGate{"Validate Tenancy, Company & Branch Consistency"}
        ValGate -- Mismatch --> RejErr["Throw ValidationException<br>(projects.company_mismatch_error / branch_mismatch)"]
        ValGate -- Pass --> LinkPersist["Persist Linkage on ProductionOrder:<br><code>project_id, project_milestone_id, project_task_id</code>"]
    end

    subgraph Production_Execution["Manufacturing Execution"]
        LinkPersist --> OrderDraft["Production Order (status: draft)"]
        OrderDraft --> ReleaseOrder["Release Order (status: released)"]
        ReleaseOrder --> IssueMat["Issue Raw Materials (StockService::recordOutflow)"]
        IssueMat --> ShopfloorOps["Execute Shopfloor Operations / Job Cards (MES)"]
        ShopfloorOps --> ReceiveFG["Receive Finished Goods (StockService::recordInflow)"]
        ReceiveFG --> CompletePO["Complete Order (ProductionOrderService::completeOrder)<br><code>status: completed</code>"]
    end

    subgraph Event_Driven_Sync["Cross-Domain Completion Synchronization"]
        CompletePO --> EmitEvent["Emit ProductionOrderCompleted Event"]
        EmitEvent --> Listener["SyncProductionTaskCompletionListener"]

        Listener --> QuerySiblings["Query All Orders Where:<br><code>project_task_id = task.id</code>"]
        QuerySiblings --> CheckStatus{"Are ALL Linked Sibling Orders Completed?"}

        CheckStatus -- No (Draft/Released/In-Progress Orders Exist) --> KeepOpen["Leave Task Status IN_PROGRESS<br>No Milestone Recalculation"]

        CheckStatus -- Yes --> EvalYield{"Aggregate Good Yield Check:<br><code>∑ quantity_produced ≥ (∑ quantity_ordered - 0.0001)</code>"}

        EvalYield -- Insufficient (Scrap Shortfall) --> ShortfallPath["Leave Task Status IN_PROGRESS<br>Log Activity: <code>production.shortfall_warning</code><br>Include shortfall delta in log"]

        EvalYield -- Meets or Exceeds Threshold --> SuccessPath["Update Task Status: COMPLETED<br>Set <code>completed_at = now()</code><br>Log Activity: <code>production.completed</code>"]

        SuccessPath --> RecalcMilestone["MilestoneService::recalculateProgress(milestone)<br>Recalculate Weighted Progress %"]
    end

    subgraph Commercial_Boundaries["Commercial & Financial Boundary (Non-Automated)"]
        SuccessPath -.-> NonAutoNote["Note: Automated synchronization updates Task status and Milestone progress only.<br>It does NOT auto-generate Customer Delivery Challans, Sales Invoices, or Revenue Vouchers.<br>Commercial billing remains under Project Management / Finance authorization."]
    end
```

### 9.2 Workflow Breakdown
* **Initiating Action:** A project deliverable requires manufacturing fulfillment. A user initiates linking either:
  1. From the Task Details drawer in Project Management using **Link Production Order** (`POST /projects/{project}/tasks/{task}/link-production-order`), or
  2. In Production by navigating to `/production/orders/create?project_id={id}&project_task_id={id}`, where the UI displays a "Linked to Project" banner and preselects target parameters.
* **Validation & Security Invariants:**
  * Tenancy check (`tenant_id`).
  * Company check: `production_orders.company_id` must match `tasks.company_id` (or `projects.company_id`).
  * Branch check: `production_orders.branch_id` must match `tasks.branch_id` (or `projects.branch_id`).
  * Task hierarchy: `project_task_id` must belong to `project_id`.
* **Execution & Completion:**
  * The production order advances through standard manufacturing steps (release, material issuance, MES operations, and finished goods receipt).
  * When `ProductionOrderService::completeOrder()` transitions the order to `completed`, it dispatches the `ProductionOrderCompleted` domain event.
* **Aggregate Yield Evaluation (`SyncProductionTaskCompletionListener`):**
  * The listener retrieves all production orders associated with the parent task: `project_task_id = $task->id`.
  * **Sibling Status Gate:** If any sibling production order remains incomplete (`draft`, `released`, or `in_progress`), the listener stops without altering task status.
  * **Yield Threshold Gate:** If all sibling orders are completed, the listener sums the total planned quantity and total finished goods output:
    $$\sum \text{quantity\_produced} \ge \left( \sum \text{quantity\_ordered} - 0.0001 \right)$$
    The small tolerance (`0.0001`) prevents floating-point rounding discrepancies from blocking completion.
* **Outcome Paths:**
  * **Success Path:** If aggregate good output meets or exceeds the threshold, the listener marks the task as `Completed`, sets `completed_at`, records an audit event (`production.completed`) in `project_activity_logs`, and calls `MilestoneService::recalculateProgress()` to update the milestone's weighted percentage.
  * **Shortfall Path:** If machining scrap or rejected parts cause aggregate output to fall short of the required total, the task remains `IN_PROGRESS` and records an audit warning (`production.shortfall_warning`) with the deficit amount. Planners can conditionally spawn an authorized supplementary order after demand review. While Project Management status transitions (`PATCH /projects/{project}/tasks/{task}/status`) allow authorized users with `projects.tasks.update` to set task status directly, this bypasses the manufacturing completion safeguards and should only occur under verified administrative exception.
* **Explicit Operational Boundaries:**
  * Synchronization is strictly one-directional upon manufacturing completion: Production order completion drives task and milestone status. Project Management actions do not force-close incomplete production orders.
  * No downstream commercial transactions (customer invoices, billing milestones, dispatch gate passes, or accounting journals) are triggered automatically by this synchronization.
