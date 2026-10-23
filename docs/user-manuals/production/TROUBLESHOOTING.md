# Production Module — Diagnostic & Troubleshooting Guide

> **Module:** Production Planning & MES (`wm-product-saas`)  
> **Target Document:** `docs/user-manuals/production/TROUBLESHOOTING.md`  
> **Audience:** System Administrators, Production Supervisors, Support Engineers, Technical Leads  
> **Standards:** Safe Diagnostic Procedures & Read-Only SQL Inspection

---

## 1. Diagnostic Principles & Golden Rules

When diagnosing production issues in `wm-product-saas`:
1. **Never execute raw data modification queries (`UPDATE`, `DELETE`) on live production tables.** All data repairs must be performed through verified domain services or audited correction workflows.
2. **All diagnostic queries below are strictly read-only (`SELECT`).** Always substitute `:tenant_id` and `:order_id` with real integer IDs.
3. Check application logs in `storage/logs/laravel.log` and production timeline events in `production_event_timelines`.

---

## 2. Issue Catalogs & Resolution Procedures

### Issue 1: Machine Locked Out on MES Terminal ("Machine Under Maintenance")

#### Symptom
When an operator attempts to click **Start Operation** on the touch MES console (`/production/mes`), the screen displays a red lockout banner: *"Machine [MCH-01] is currently under maintenance or broken down. Operation cannot be started."*

#### Root Cause
The machine's `maintenance_status` is set to `under_maintenance` or `breakdown`, or its `status` is not `Active`. This occurs when a Maintenance Work Order was initiated, an emergency breakdown was reported, or an operator triggered an Andon breakdown alert.

#### Diagnostic Steps (Read-Only SQL)
```sql
SELECT id, code, name, status, maintenance_status, current_state
FROM machines
WHERE tenant_id = :tenant_id AND id = :machine_id;

-- Check active maintenance work orders for this machine
SELECT id, work_order_number, status, maintenance_type, reported_at
FROM production_maintenance_work_orders
WHERE tenant_id = :tenant_id 
  AND machine_id = :machine_id 
  AND status NOT IN ('completed', 'cancelled');
```

#### Safe Resolution Steps
1. Navigate to `/production/maintenance/dashboard`.
2. Locate the active work order corresponding to the locked machine.
3. If repairs are finished: Click **Complete Work Order** (`POST /maintenance/work-orders/{id}/complete`).
4. System updates machine status to `Active`, resolves associated downtime logs, and immediately unblocks the MES terminal.
5. If the work order was created in error: An authorized maintenance supervisor can click **Cancel Work Order** (`POST /maintenance/work-orders/{id}/cancel`).

---

### Issue 2: Material Issuance Blocked ("Insufficient Stock Available in Warehouse")

#### Symptom
Storekeeper clicks **Issue Material** on `/production/orders/{id}/issue`, but the system rejects the transaction with an error message: *"Insufficient on-hand stock for component [RAW-STEEL-TUBE] in warehouse [Main Raw Store]. Required: 50, Available: 12."*

#### Root Cause
Physical inventory in the selected warehouse is lower than the required picking quantity. Stock may reside in another warehouse, or a previous order was not returned.

#### Diagnostic Steps (Read-Only SQL)
```sql
-- Check on-hand stock across all warehouses for the component
SELECT w.name AS warehouse_name, pws.warehouse_id, pws.on_hand
FROM product_warehouse_stocks pws
JOIN warehouses w ON w.id = pws.warehouse_id
WHERE pws.tenant_id = :tenant_id AND pws.product_id = :component_product_id;

-- Check active reservations competing for the same item
SELECT r.production_order_id, po.order_number, r.reserved_qty
FROM production_order_reservations r
JOIN production_orders po ON po.id = r.production_order_id
WHERE r.tenant_id = :tenant_id 
  AND r.product_id = :component_product_id 
  AND r.status = 'active';
```

#### Safe Resolution Steps
1. If stock exists in another warehouse: Use the Inventory module's **Internal Stock Transfer** (`/inventory/transfers`) to move stock into the production warehouse.
2. If physical stock is truly depleted: Run MRP on the Production Plan to raise an emergency Purchase Requisition, or perform a manual stock intake if emergency stock just arrived.
3. Verify that competing orders that were cancelled have had their reservations released.

---

### Issue 3: Subcontract PO Created as "Draft" Instead of "Approved"

#### Symptom
When a production order with an outsourced operation was released, the system created a `Draft` Purchase Order instead of automatically approving it.

#### Root Cause
Evaluated by `SubcontractProcurementPolicyResolver`:
1. Total PO cost (`quantity × unit_cost`) exceeded the tenant's `subcontract_auto_approval_limit`.
2. The subcontract vendor record is missing or inactive.
3. The subcontract unit cost on the routing operation was `$0.00`.
4. Tenant settings have `subcontract_procurement_workflow` configured to `auto_draft_po` or `manual_pr_po`.

#### Diagnostic Steps (Read-Only SQL)
```sql
-- Inspect tenant subcontract configuration
SELECT settings->>'$.subcontract_procurement_workflow' AS workflow_mode,
       settings->>'$.subcontract_auto_approval_limit' AS auto_approval_limit
FROM tenants
WHERE id = :tenant_id;

-- Check operation subcontract configuration
SELECT sequence, is_subcontract, vendor_id, subcontract_cost_per_unit, target_produced_qty
FROM production_order_operations
WHERE id = :operation_id;
```

#### Safe Resolution Steps
* If total cost exceeded threshold: This is a safe intended fallback. Have the Purchasing Manager open `/purchase/orders/{po_id}`, review the line items, and click **Approve PO**.
* To increase the threshold: An administrator can update the limit under `/production/settings` (`POST /production/settings/subcontract`).

---

### Issue 4: Stalled / Orphaned WIP Balances Between Work Centers

#### Symptom
The WIP dashboard displays in-process inventory stranded at a work center, but the downstream operator cannot see the batch on their MES terminal.

#### Root Cause
The previous operation recorded partial progress, creating a `ProductionWip` record in the cell's output buffer, but the physical inter-cell **WIP Transfer** was never logged in the system.

#### Diagnostic Steps (Read-Only SQL)
```sql
SELECT pw.id, pw.production_order_id, po.order_number, 
       wc.name AS work_center_name, pw.quantity, pw.status
FROM production_wips pw
JOIN work_centers wc ON wc.id = pw.work_center_id
JOIN production_orders po ON po.id = pw.production_order_id
WHERE pw.tenant_id = :tenant_id AND pw.quantity > 0;
```

#### Safe Resolution Steps
1. Navigate to `/production/wip`.
2. Locate the orphaned WIP entry.
3. Click **Transfer WIP** (`POST /production/wip/{id}/transfer`).
4. Select the destination work center and confirm transfer. The units will immediately appear in the downstream operator's MES queue.

---

### Issue 5: Scheduling Dispatch Board Operation Cannot Be Moved

#### Symptom
On the visual Gantt Dispatch Board (`/production/schedules/dispatch-board`), a planner attempts to reschedule an operation, but the card resists dragging or leveling.

#### Root Cause
The operation card has been locked (`is_locked = true`) by an engineer to prevent automated capacity leveling from moving a priority run.

#### Diagnostic Steps (Read-Only SQL)
```sql
SELECT id, schedule_id, sequence, is_locked, start_time, end_time
FROM production_schedule_operations
WHERE id = :schedule_operation_id;
```

#### Safe Resolution Steps
1. Locate the operation card on the Dispatch Board.
2. Click the padlock icon on the card, or dispatch `POST /production/schedules/operations/{id}/toggle-lock`.
3. Once unlocked, the operation can be repositioned freely.

---

### Issue 6: Barcode Scanner Fails to Resolve MES Record

#### Symptom
An operator scans a physical traveler barcode, but the scanner returns *"Barcode format unrecognized or record not found."*

#### Root Cause
Barcodes follow standardized syntax generated by `CodeService`:
* Orders: `ORD:{order_number}`
* Batches: `BAT:{batch_number}`
* Serials: `SER:{serial_number}`
* Operators: `OPR:{employee_id}`
* Work Centers: `WKC:{code}`
* Machines: `MCH:{code}`

If a third-party barcode reader injects unwanted carriage returns or missing prefixes, parsing fails.

#### Diagnostic Steps
1. Inspect the scan audit table:
```sql
SELECT raw_payload, parsed_type, resolved_entity_id, status, error_message, created_at
FROM production_scan_logs
WHERE tenant_id = :tenant_id
ORDER BY id DESC LIMIT 10;
```
2. Check if the scanned string matches the expected regex pattern in `CodeService.php`.
3. Configure the hardware barcode scanner to send standard USB HID keyboard emulation without custom preambles.

---

### Issue 7: Linked Project Task Remains In-Progress After Production Order Completion

#### Symptom
A production order associated with a project task was completed (`status: completed`), but the linked Project Task remains `IN_PROGRESS` and its milestone progress was not updated.

#### Root Cause
The `SyncProductionTaskCompletionListener` runs automatically when `ProductionOrderCompleted` is dispatched, but intentionally leaves the parent task in `IN_PROGRESS` if:
1. **Open Sibling Orders:** One or more sibling production orders linked to the same `project_task_id` remain in `draft`, `released`, or `in_progress`. All linked orders must reach `completed` before task completion is evaluated.
2. **Aggregate Output Shortfall:** The combined good finished goods received across all linked orders is less than the total planned quantity:
   $$\sum \text{produced\_quantity} < \left( \sum \text{quantity} - 0.0001 \right)$$
   A shortfall warning (`production.shortfall_warning`) is recorded in the task's activity log.
3. **Bypassed Order Completion Service:** The order's status was modified directly in the database or via an unhandled script rather than calling `ProductionOrderService::completeOrder()`, meaning the `ProductionOrderCompleted` event was never fired.

#### Diagnostic Steps (Read-Only SQL)
```sql
-- 1. Check all production orders linked to the project task
SELECT
    id,
    order_number,
    status,
    quantity_ordered,
    quantity_produced,
    project_id,
    project_milestone_id,
    project_task_id
FROM production_orders
WHERE tenant_id = :tenant_id
  AND project_task_id = :task_id;

-- 2. Evaluate aggregate completion count and yield variance
SELECT
    COUNT(*) AS total_orders,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
    SUM(quantity_ordered) AS total_ordered_qty,
    SUM(quantity_produced) AS total_produced_qty,
    (SUM(quantity_produced) - SUM(quantity_ordered)) AS yield_variance
FROM production_orders
WHERE tenant_id = :tenant_id
  AND project_task_id = :task_id;

-- 3. Check for shortfall warnings logged on the project task
SELECT id, event_type, title, description, metadata, created_at
FROM project_activity_logs
WHERE tenant_id = :tenant_id
  AND subject_type LIKE '%Task%'
  AND subject_id = :task_id
ORDER BY id DESC LIMIT 5;
```

#### Safe Resolution Steps
1. **If open sibling orders exist:** Inspect the remaining linked production orders in `/production/orders`. Progress each order through its operations and execute finished goods receipt until all sibling orders reach `completed`.
2. **If an aggregate yield shortfall exists:**
   * **Authorized Supplementary Order (Standard Path):** If authorized by production planning and verified against inventory demand, an authorized planner may create a supplementary production order linked to the same task for the remaining shortage balance (`POST /projects/{project}/tasks/{task}/link-production-order` or `/production/orders/create?project_id={id}&project_task_id={id}`). Always confirm requirements to avoid duplicate production.
   * **Safeguard Bypass Caution on Manual Completion:** In Project Management, a user with `projects.tasks.update` can technically transition the task status to `Completed` (`PATCH /projects/{project}/tasks/{task}/status`) because task-level status transitions evaluate dependency blockers rather than manufacturing yield. However, doing so **bypasses the production completion safeguards** and leaves the physical output deficit unverified. Manual completion must never be used as a routine workaround for incomplete manufacturing orders without formal administrative and commercial signoff.
   * **Do Not Direct-Update Database:** Never attempt direct SQL `UPDATE` queries to force task or order status flags.
3. **If event was bypassed:** Trigger `ProductionOrderService::completeOrder()` through the standard UI by opening the order and clicking **Complete Order**.

---

### Issue 8: Unexpected Zero or Distorted Rework Labor and Overhead Costs

#### Symptom
A completed rework order (`RWK-XXXXX`) shows `$0.00` actual labor or overhead cost, or actual costs roll up unexpectedly lower than anticipated.

#### Root Cause
`ReworkService` partitions rework costing across three distinct lifecycle stages:
1. **Initial Planned Estimate (`cost_estimate`):** Stored in `production_rework_orders.cost_estimate`. If not explicitly supplied in the creation payload, `ReworkService::createReworkOrder()` computes this estimate dynamically via `calculateDynamicCostEstimate()` based on planned operation minutes (default 30 minutes per stage) multiplied by assigned resource rates.
2. **Actual Incurred Cost (`actual_cost`):** When operations are completed via `completeOperation()`, actual costs accumulate dynamically into `production_rework_orders.actual_cost`:
   * **Labor Rate:** Derived from the assigned Work Center (`$workCenter->cost_per_hour / 60.0`).
   * **Overhead Rate:** Derived from the assigned Work Center (`$workCenter->overhead_rate / 60.0`).
   * **Machine Rate:** Derived from the linked Routing Operation (`$routingOperation->machine_cost_rate`).
3. **Parent Order Cost Adjustments:** When all rework operations finish, non-material expenses (labor, machine, overhead) roll up into `production_cost_adjustments` records on the parent production order under category `rework_expense`. Materials are excluded from this adjustment to prevent double-counting against store outflow records (`ProductionOrderIssue`).

If a rework operation is created without an assigned `work_center_id`, or if the assigned Work Center has its `cost_per_hour` and `overhead_rate` set to `0.00` or `null`, the calculated rates evaluate to `0.00` per minute in `calculateDynamicCostEstimate` and `completeOperation`. (Note: Legacy development seeds and test fixtures historically referenced internal figures of `$35.00/hr` for labor and `$50.00/hr` for machines, while earlier drafts referenced a `$150.00` nominal estimate. However, live execution paths strictly resolve `cost_estimate` dynamically and accumulate actual rates from active master data tables).

#### Diagnostic Steps (Read-Only SQL)
```sql
-- 1. Inspect operations on the rework order and their assigned work centers
SELECT
    ro.id,
    ro.rework_order_id,
    ro.name,
    ro.work_center_id,
    wc.name AS work_center_name,
    wc.cost_per_hour,
    wc.overhead_rate,
    ro.machine_id,
    ro.status,
    ro.processing_time_actual
FROM production_rework_operations ro
LEFT JOIN production_work_centers wc ON wc.id = ro.work_center_id
WHERE ro.tenant_id = :tenant_id
  AND ro.rework_order_id = :rework_order_id;

-- 2. Inspect recorded cost adjustments on the parent production order
SELECT
    id,
    production_order_id,
    cost_component,
    category,
    amount,
    description,
    created_at
FROM production_cost_adjustments
WHERE tenant_id = :tenant_id
  AND production_order_id = :parent_production_order_id;
```

#### Safe Resolution Steps
1. **Ensure Master Data Configuration:** Before executing rework operations, ensure the target Work Center in `/production/work-centers` has non-zero `cost_per_hour` and `overhead_rate` values configured.
2. **Assign Work Centers to Operations:** When creating rework orders, ensure each rework stage specifies an active `work_center_id`.
3. **Retroactive Reconciliation:** If rework operations were already finalized with `$0.00` labor cost, post a corrective adjustment on the parent production order via `/production/orders/{id}` → **Add Cost Adjustment** (`POST /production/orders/{order}/cost-adjustments`) selecting category `rework_expense` and component `labor` or `machine`. Never attempt manual `UPDATE` queries on closed rework tables.
