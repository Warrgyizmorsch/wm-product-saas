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
