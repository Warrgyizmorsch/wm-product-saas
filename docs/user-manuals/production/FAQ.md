# Production Module — Frequently Asked Questions (FAQ)

> **Module:** Production Planning & MES (`wm-product-saas`)  
> **Target Document:** `docs/user-manuals/production/FAQ.md`  
> **Audience:** End Users, Production Managers, Developers, Support Teams

---

## 1. General & Architecture FAQs

### Q1: Does the Production module post double-entry journal vouchers directly to Accounting?
**Yes, for raw material consumption.** When raw materials are issued to a production order:
* `StockService::recordOutflow` emits the `StockOutflowRecorded` event with `reference_type = 'Production Material Issue'`.
* The Accounting listener `PostProductionConsumptionJournal` captures this event and automatically posts a double-entry General Ledger journal:
  * **Debit:** Work-in-Progress (WIP Account `1204` / Asset).
  * **Credit:** Raw Material Inventory Asset Account.
  * **Journal Source:** `Journal::SOURCE_PRODUCTION` (`'production'`).
  * **Idempotency Key:** Keyed by the stock transaction ID to prevent duplicate ledger postings across incremental issues.
* **Finished Goods Receipts & Scrap:** In contrast, Finished Goods intake (`Production Receipt`) and operational scrap write-downs update physical inventory and moving-average inventory valuation without triggering an automated GL journal; capitalization of FG and scrap write-offs are booked through periodic inventory valuation adjustments.

### Q2: How does multi-tenancy protect our proprietary formulas and product designs?
Every database query in the Production module is automatically constrained by `TenantScope` and `require_tenant_id()`. All 74 production tables enforce strict tenant isolation. Even if a user possesses an administrative role in Tenant A, they have zero visibility or query capability into the BOMs, routings, or production runs of Tenant B.

### Q3: Where is the currency symbol configured for Production views and exports?
Currency formatting follows the tenant's global setting via the canonical helper functions:
* `active_currency_symbol()`: Returns the currency symbol (e.g., `$`, `₹`, `€`).
* `active_currency()`: Returns the ISO code (e.g., `USD`, `INR`, `EUR`).
* `format_currency($amount)`: Formats numbers with appropriate decimal separators and currency symbols.
* All database persistence occurs strictly in **Base Currency** via `convert_to_base()` and `convert_from_base()`.

---

## 2. Planning & Engineering FAQs

### Q4: How do I edit an already approved Bill of Materials (BOM)?
Approved BOMs are locked to preserve the historical integrity of past orders. To modify an approved BOM:
1. Open the BOM in `/production/boms/{id}`.
2. Click **Create Revision**.
3. The system creates a duplicate revision in `draft` state (e.g., `v2`).
4. Modify lines, adjust scrap percentages, and click **Submit for Approval**.
5. Once the new revision is approved, mark the older revision as `obsolete`.

### Q5: How does the Scrap Percentage field on a BOM affect MRP calculation?
The scrap percentage accounts for nominal machining loss (e.g., metal filings, fabric trimming, sawdust). When `MrpEngineService` runs, it multiplies the gross component demand by `(1 + scrap_percentage / 100)`. If 100 meters are required with 5% scrap, MRP demands 105 meters to ensure sufficient material is issued.

### Q6: What are Parameterized BOM Formulas?
For variable-dimension manufacturing (e.g., custom glass panels or structural steel), BOM lines can define mathematical formulas instead of static quantities (e.g., `[width] * [height] * 0.0025`). Planners can test dynamic expressions using the **Formula Preview** utility (`POST /production/boms/preview-formula`).

---

## 3. Shopfloor Execution & Quality FAQs

### Q7: Can an operator produce more units than originally planned on a batch run?
**Yes.** When reported output exceeds the batch planned quantity (`actual_quantity > planned_quantity`), `ProductionExecutionService::recordProgress` caps the parent batch at its planned quantity and automatically creates a linked **Overflow Batch** (`ProductionBatchService::createOverflowBatch`) for the remaining quantity. Both the parent and overflow batches maintain independent serial and lot tracking records.

### Q8: What happens to physical warehouse stock when an operator logs Operational Scrap?
When scrap is logged on `/production/mes/{op}/scrap`:
* If the material was already issued from the warehouse to the shopfloor, `StockService::recordOutflow()` writes down the scrapped quantity to process scrap.
* Intermediate WIP scrap (partially completed parts damaged between operations) updates the WIP floor balance without affecting warehouse finished goods stock, as finished goods have not yet been received.

### Q9: If an inspection fails and parts are reworked, how are rework costs tracked?
When an NCR disposition is set to **Rework**, `ReworkService` creates a dedicated `ProductionReworkOrder` (`RWK-XXXXX`). Labor hours and replacement materials expended during the repair are tracked separately and roll up into the parent order's total incurred cost.

---

## 4. Subcontracting & Maintenance FAQs

### Q10: Why did my subcontract Purchase Order create as Draft instead of Auto-Approved?
`SubcontractProcurementPolicyResolver` falls back to `Draft PO` whenever:
1. Total PO cost (`quantity × unit_cost`) exceeds the tenant's `subcontract_auto_approval_limit`.
2. The subcontract vendor is missing, unapproved, or marked inactive.
3. The unit cost on the routing operation was set to `$0.00`.
4. Tenant setting `subcontract_procurement_workflow` is set to manual review.

### Q11: How do I scrap and decommission a machine permanently during maintenance?
When completing a breakdown or corrective maintenance ticket on `/production/maintenance/work-orders/{id}`:
1. Select the completion action: **Complete & Scrap Machine**.
2. Enter the decision note explaining the terminal failure.
3. Upon submission, the work order closes, and the machine's status transitions to `Decommissioned`. It is permanently removed from available scheduling capacity.

### Q12: Can maintenance engineers request spare parts without specifying a warehouse?
**Yes.** When adding a spare part request to a Maintenance Work Order, specifying a `warehouse_id` is optional. The system automatically creates or appends to a central Store Requisition Slip (`ProductionRequisitionSlip` with `source_type = 'maintenance_work_order'`). Storekeepers can view the pending requisition in `/inventory/material-requests`, select the appropriate warehouse, reserve stock, and issue the parts. The work order's spare costs and total cost are updated automatically.

---

## 5. Cross-Module Integration & Costing FAQs

### Q13: How does linking a Production Order to a Project Task work, and what triggers automatic task completion?
A Production Order can be associated with a Project Management task through two methods:
1. **Creation from Task Context:** Navigating to `/production/orders/create?project_id={id}&project_task_id={id}`, where product specifications and quantity prefill and a "Linked to Project" banner displays.
2. **Direct Link from Task Workspace:** In the Project Management task workspace via `POST /projects/{project}/tasks/{task}/link-production-order` (`projects.tasks.link-production-order`).

**Validation Rules:** The system enforces strict organizational consistency across `tenant_id`, `company_id`, and `branch_id`. If the order and project task belong to different companies or branches, creation or linking is rejected with validation errors (`projects.company_mismatch_error` or `projects.branch_mismatch_error`).

**Automatic Completion Evaluation:**
When an order transitions to `completed` via `ProductionOrderService::completeOrder()`, the `ProductionOrderCompleted` event triggers `SyncProductionTaskCompletionListener`. The listener evaluates all orders linked to that task (`project_task_id = $task->id`):
* **Sibling Gate:** If any sibling order remains in `draft`, `released`, or `in_progress`, the task remains `IN_PROGRESS`.
* **Aggregate Yield Check:** If all sibling orders are completed, the listener sums good produced quantities against ordered quantities:
  $$\sum \text{quantity\_produced} \ge \left( \sum \text{quantity\_ordered} - 0.0001 \right)$$
* **Outcome:** If good yield meets or exceeds the threshold, the task is marked `Completed`, `completed_at` is set, an audit log entry (`production.completed`) is recorded in `project_activity_logs`, and `MilestoneService::recalculateProgress()` recalculates the milestone's overall progress. If aggregate yield falls short (due to scrap or partial receipts), the task remains `IN_PROGRESS` and records an audit log warning (`production.shortfall_warning`).
* **Commercial Boundaries:** This synchronization is purely operational. It does **not** generate customer delivery notes, dispatch challans, sales invoices, or financial billing milestones.

### Q14: How are Rework labor and machine rates calculated, and what happens if Work Center rates are unconfigured?
In `ReworkService`, rework costing is partitioned across three distinct stages:
1. **Initial Planned Estimate (`cost_estimate`):** Stored in `production_rework_orders.cost_estimate`. When creating a rework order (`createReworkOrder`), if a planned estimate is not explicitly passed in the creation payload, it is dynamically computed via `calculateDynamicCostEstimate()` based on planned operation minutes (standard default 30 minutes per planned stage) and assigned resource rates.
2. **Actual Incurred Execution Cost (`actual_cost`):** During operation completion (`completeOperation`), non-material costs accumulate dynamically on `production_rework_orders.actual_cost`:
   * **Labor Cost:** Calculated as $\text{actual\_minutes} \times \left( \frac{\text{WorkCenter.cost\_per\_hour}}{60.0} \right)$.
   * **Overhead Cost:** Calculated as $\text{actual\_minutes} \times \left( \frac{\text{WorkCenter.overhead\_rate}}{60.0} \right)$.
   * **Machine Cost:** Calculated as $\text{actual\_minutes} \times \text{RoutingOperation.machine\_cost\_rate}$ (if a machine is assigned).
3. **Posted Cost Adjustments:** When all rework operations are finalized, non-material expenses (labor, machine, overhead) roll up into `production_cost_adjustments` records on the parent production order under category `rework_expense`. Materials are excluded from this adjustment to prevent double-counting against store outflow records (`ProductionOrderIssue`).

**Missing Configuration Behavior:**
If an operation is executed without an assigned Work Center, or if the assigned Work Center has its `cost_per_hour` or `overhead_rate` set to `0.00` or `null`, the calculated rates evaluate to `0.00` per minute in `calculateDynamicCostEstimate` and `completeOperation`, resulting in `$0.00` recorded actual labor and overhead costs.
* *Note on Historical Defaults:* Earlier development seeds and test fixtures referenced internal figures of `$35.00/hr` for labor and `$50.00/hr` for machines. These values do not exist in live calculation paths.
* *Best Practice:* Always ensure Work Centers have appropriate `cost_per_hour` and `overhead_rate` values defined in `/production/work-centers` before executing shopfloor rework.
