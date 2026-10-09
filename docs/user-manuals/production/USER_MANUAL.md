# Production Planning & Manufacturing Execution (MES) — End-User Manual

> **Product:** Multi-Tenant SaaS ERP (`wm-product-saas`)  
> **Module:** Production Planning & Shopfloor MES  
> **Audience:** Production Planners, Manufacturing Managers, Shopfloor Operators, QC Inspectors, Storekeepers, Maintenance Engineers  
> **Version:** 2.0 (Production Verified)

---

## 1. Introduction & Navigation Overview

The Production Planning & Manufacturing Execution (MES) module is an industrial-grade enterprise solution designed for discrete and batch manufacturing environments. It covers the entire manufacturing lifecycle from initial demand planning, Bill of Materials (BOM) engineering, and finite capacity scheduling to shopfloor execution, quality inspection, plant maintenance, and lot genealogy traceability.

### 1.1 Navigating the Production Workspace

All Production module features are accessed through the primary sidebar navigation under the **Production** group (base URL `/production`):

| UI Menu Item | Web Route | Target Personas | Purpose |
|---|---|---|---|
| **Dashboard** | `/production/dashboard` | Plant Managers, Planners | High-level plant KPIs, active order status, machine states, and output trends |
| **Bills of Materials (BOM)** | `/production/boms` | Manufacturing Engineers, Planners | Multi-level product structures, scrap allowances, and parametric formulas |
| **Work Centers** | `/production/work-centers` | Industrial Engineers, Planners | Plant departments, machine cells, capacities, and hourly overhead rates |
| **Machines** | `/production/machines` | Plant Engineers, Maintenance | Individual machines, fixed asset links, run states, and maintenance history |
| **Routings** | `/production/routing` | Process Engineers, Planners | Sequential manufacturing operations, standard runtimes, and subcontract services |
| **Production Plans** | `/production/plans` | Master Schedulers, Planners | Aggregate monthly/weekly demand planning, MRP explosion, and shortage checks |
| **Production Orders** | `/production/orders` | Planners, Production Leads | Discrete work orders, material reservations, picking slips, and FG receipts |
| **Scheduling & Dispatch** | `/production/schedules` | Schedulers, Shopfloor Leads | Finite capacity Gantt scheduling, Dispatch Board, and scenario modeling |
| **MES Shopfloor Terminal** | `/production/mes` | Machine Operators, Leads | Touchscreen operator console, operation timers, progress logs, and scrap |
| **Work-in-Progress (WIP)** | `/production/wip` | Shopfloor Leads, Supervisors | Intermediate floor stock balances, inter-stage transfers, and FG conversions |
| **Quality Control** | `/production/quality/inspections` | QC Inspectors, Quality Leads | First-piece/in-process inspections, parameter tolerances, NCRs, and CAPAs |
| **Subcontracting** | `/production/subcontract/delivery-challans` | Subcontract Executives, Stores | Outward vendor Delivery Challans (Gate Passes) and processed goods receipt |
| **Plant Maintenance** | `/production/maintenance/dashboard` | Maintenance Engineers | Preventive Maintenance (PM) schedules, breakdown reports, and spare parts |
| **Lot Traceability** | `/production/mes/traceability` | Quality Leads, Compliance | Bidirectional lot genealogy: Raw Material Lot → Batch → Finished Good → Sales Order |
| **Intelligence & Reports** | `/production/intelligence/reports` | Operations Executives, Controllers | Cost variance, material consumption, OEE analytics, and CSV/Excel/PDF exports |

---

## 2. Master Data Management

Accurate master data is the foundation of manufacturing control. Ensure Work Centers, Machines, BOMs, and Routings are configured before creating production plans.

### 2.1 Work Centers

Work Centers represent physical production areas, work cells, or assembly lines where manufacturing operations occur.

* **Navigation:** `Production → Master Data → Work Centers` (`/production/work-centers`)
* **Key Fields:**
  * `code`: Unique plant identifier (e.g., `WC-FAB-01`, `WC-ASSY-LINE`).
  * `name`: Descriptive name (e.g., `Heavy Metal Fabrication Cell`).
  * `capacity_per_hour`: Standard units processed per nominal operating hour.
  * `cost_per_hour`: Direct labor and machine rate per operating hour (in active tenant currency).
  * `overhead_rate`: Plant overhead markup applied per operating hour.
  * `efficiency`: Expected operational efficiency percentage (e.g., `90%`).
* **System Action:** Hourly rates and overhead are used by the costing engine to evaluate planned versus actual operational run costs.

### 2.2 Machines & Asset Interlinking

Machines represent specific machinery assigned to Work Centers.

* **Navigation:** `Production → Master Data → Machines` (`/production/machines`)
* **Creating a Machine:**
  1. Click **New Machine**.
  2. Select the parent **Work Center**.
  3. Specify machine code, manufacturer, model, serial number, and maximum tonnage/capacity.
  4. (Optional) Select an existing Fixed Asset record from the **Fixed Asset Link** dropdown to connect maintenance history to capitalized balance sheet assets.
* **Operating States:** `Active`, `Idle`, `Under Maintenance`, `Breakdown`, or `Decommissioned`. If a machine is under maintenance or broken down, the system automatically blocks operators from starting MES operations on that unit.

### 2.3 Bills of Materials (BOM)

A Bill of Materials defines the hierarchical recipe of components, raw materials, and sub-assemblies required to manufacture one unit of a finished product.

* **Navigation:** `Production → Engineering → Bills of Materials` (`/production/boms`)
* **BOM Lifecycle & Revision Control:**
  `Draft` → Click **Submit for Approval** → `Pending Approval` → Authorized Manager clicks **Approve** → `Approved`.
* **Important Guidelines:**
  * Only `Approved` BOMs can be used on Production Plans and Orders.
  * To change an approved BOM, open the BOM and click **Create Revision**. The system creates an editable clone (e.g., `v2`), leaving the active revision untouched until the new version is approved.
  * **Scrap Percentage:** Enter expected process scrap (e.g., `3%`). The MRP engine will automatically explode extra material requirements to account for nominal cutting or machining loss.
  * **Parametric Formulas:** For variable-dimension manufacturing (e.g., custom glass or sheet metal cutting), use the **Formula Preview** utility (`/production/boms/preview-formula`) to evaluate dynamic quantity expressions based on parent dimensions.

### 2.4 Production Routings

A Routing defines the step-by-step sequential operations required to turn raw components into finished products.

* **Navigation:** `Production → Engineering → Routings` (`/production/routing`)
* **Configuring Routing Operations:**
  * `sequence`: Order of execution (e.g., `10`, `20`, `30`). Always space sequences by 10s to allow intermediate step insertions.
  * `work_center_id`: Primary Work Center responsible for the step.
  * `setup_time_minutes`: Machine preparation time independent of batch quantity.
  * `processing_time_minutes`: Standard runtime required per unit.
  * `labor_cost_rate` & `machine_cost_rate`: Baseline operating costs per hour.
  * **Subcontracting Fields:** If the operation is outsourced (e.g., heat treating, powder coating), set `is_subcontract = true`, select the subcontract vendor, specify `subcontract_cost_per_unit`, and define `subcontract_lead_time_days`.

---

## 3. Production Planning & Material Requirements (MRP)

### 3.1 Creating Aggregate Production Plans

Production Plans aggregate demand across multiple finished goods over a defined planning horizon (weekly, monthly, or quarterly).

* **Navigation:** `Production → Planning → Production Plans` (`/production/plans`)
* **Step-by-Step Instructions:**
  1. Click **Create Production Plan**.
  2. Select the target **Product** and specify the **Planned Quantity**.
  3. Set the **Planned Start Date** and **Planned End Date**.
  4. (Optional) Link to an existing **Sales Order** (`sales_order_id`) to tag Make-to-Order customer demand.
  5. Select the **Approved BOM** and **Approved Routing**.
  6. Click **Save Plan**.
  7. Click **Submit for Approval** and subsequently **Approve Plan**.

### 3.2 Running MRP & Shortage Analysis

Once a plan is approved, run the Material Requirements Planning (MRP) engine to explode multi-level component requirements against current warehouse stocks.

* **Triggering MRP:** Open the plan and click **Run MRP Engine** (`POST /production/plans/{plan}/run-mrp`).
* **MRP Evaluation Results:**
  * **Gross Requirements:** Total raw materials calculated from BOM multipliers and scrap factors.
  * **On-Hand Stock:** Current physical inventory across active raw material warehouses.
  * **Net Shortage:** `Gross Requirement - Available Stock`.
* **Procurement Actions:** If a shortage is detected, navigate to `/production/mrp/shortages`. The system allows planners to convert verified shortages directly into Purchase Requisitions with a single click.

### 3.3 Releasing Plans & Spawning Orders

Once material availability is confirmed:
1. Click **Release Plan** (`POST /production/plans/{plan}/release`). The plan status changes to `Released`.
2. Click **Create Production Order** from the plan header to generate executable shopfloor orders.

---

## 4. Production Orders & Material Logistics

### 4.1 Order Creation & Reservation

Production Orders represent discrete manufacturing jobs executed on the factory floor.

* **Navigation:** `Production → Execution → Production Orders` (`/production/orders`)
* **Order Status Progression:**
  `Draft` → `Planned` → `Released` → `In Progress` → `Completed` → `Closed`.
* **Material Soft-Reservation:**
  Upon order creation, the system immediately inserts `ProductionOrderReservation` records. This reserves physical warehouse inventory for the order, preventing sales orders or other manufacturing jobs from claiming the same stock.

### 4.2 Material Picking & Requisition Slips

Before operators can assemble parts, physical goods must be picked from warehouse storage.

* **Printing Requisition Slips:**
  1. Open the Production Order detail view.
  2. Click **Print Requisition Slip**.
  3. Hand the signed document to the warehouse storekeeper. The slip displays required SKU codes, bin locations, lot suggestions, and exact quantities.

### 4.3 Material Issuing (Storekeeper Workflow)

When goods physically depart the warehouse:
1. Open the Production Order and click **Issue Material** (`POST /production/orders/{order}/issue`).
2. Select the source warehouse, verify quantities, and specify raw material lot numbers.
3. Click **Confirm Issuance**.
4. **Inventory Impact:** The system automatically executes `StockService::recordOutflow()`. On-hand warehouse stock is decremented immediately, and an audited stock transaction ledger record is generated.
5. **Automated General Ledger Impact:** The inventory outflow event (`StockOutflowRecorded` with reference `'Production Material Issue'`) is captured by `PostProductionConsumptionJournal`, automatically posting a double-entry journal:
   * **Debit:** Work-in-Progress (WIP Account `1204` / Asset).
   * **Credit:** Raw Material Inventory Asset Account.

### 4.4 Material Returns

If excess material was issued or remnants remain unconsumed:
1. Click **Return Material** (`POST /production/orders/{order}/return`).
2. Enter the unused quantity, select the return warehouse, and click **Process Return**.
3. The system executes `StockService::recordInflow()`, returning stock to active inventory.

### 4.5 Order Completion Prerequisites (`ProductionOrderCompletionValidator`)

Before a Production Order can be marked as `Completed` or Finished Goods received into inventory, the system enforces strict quality and operational prerequisites:
* **Subcontract Clearance:** All outsourced operations must be fully completed and cleared through Subcontract QC (`subcontract_qc_pending` operations block completion).
* **WIP Quality Clearance:** No intermediate WIP may remain in `quality_hold` or `rework`.
* **Zero Open Defect Tickets:** All Non-Conformance Reports (NCRs) linked to this order must be resolved and closed (`open` or `under_review` NCRs block completion).
* **Vendor Material Balance Reconciled:** If company raw material was issued to an external subcontractor, all material balances must be reconciled (`remaining = 0`).

---

## 5. Shopfloor Execution (MES) Operator Terminal

The Manufacturing Execution System (MES) interface is optimized for industrial touchscreens and tablets on the plant floor.

* **Terminal URL:** `/production/mes` (or `/production/mes/operator` for personalized operator views).
* **Role Required:** Machine Operator, Assembly Technician, or Shopfloor Lead (`production.mes.execute` permission).

### 5.1 Operating Sequence

1. **Select Operation:** Operators locate their assigned machine or work order on the live board.
2. **Start Operation:** Click **Start Operation** (`POST /production/mes/{op}/start`).
   * The live browser timer activates.
   * Operation status transitions to `in_progress`.
   * Machine state updates to `Active`.
3. **Pauses & Breaks:** If the operator takes a lunch break or machine tooling needs adjustment, click **Pause Operation** with an explanatory reason (`Break`, `Tooling Change`, `Inspection Wait`). Machine state switches to `Idle`.
4. **Logging Progress & Over-Production:**
   * Throughout the shift, click **Log Progress** to record partial production quantities completed so far.
   * **Batch Overflow Behavior:** If the reported output exceeds the batch planned quantity (`actual_quantity > planned_quantity`), the system caps the parent batch at its planned quantity and automatically creates a linked **Overflow Batch** for the excess quantity to preserve lot traceability.
   * **Automatic Quality Routing on Defect / Scrap:**
     * Logging rejected parts (`rejected > 0`) automatically creates an open Non-Conformance Report (`NCR-AUTO-...`, disposition `rework`) and initiates a child Rework Order.
     * Logging scrap (`scrapped > 0`) automatically creates an auto-NCR (`disposition = scrap`) and initiates a Scrap Disposal entry.
5. **Complete Operation:** When the batch run finishes, click **Complete Operation**. Enter final good quantity produced, operational scrap count, and rejected units.

### 5.2 Andon Alerts

If an immediate problem occurs (material shortage, safety hazard, tooling breakdown):
1. Click the red **Andon Alert** button on the MES terminal.
2. Select the alert category (`Machine Breakdown`, `Quality Defect`, `Material Starvation`).
3. Enter a brief description and submit.
4. An immediate plant alert flashes across the Plant Andon Monitor (`/production/intelligence/andon`) and notifies maintenance and engineering leads.

### 5.3 Remnants & Offcuts Tracking

When cutting dimensional materials (e.g., sheet metal, timber, bar stock):
1. On the MES screen, click **Record Remnant** (`POST /production/mes/{op}/remnant`).
2. Enter length, width, thickness, material SKU, and storage rack location.
3. The offcut is added to the remnant inventory repository, allowing future planners to allocate offcuts before consuming new raw stock.

---

## 6. Work-in-Progress (WIP) Management

WIP represents semi-finished goods that have finished one operation but have not yet been completed into final warehouse stock.

* **Navigation:** `Production → Execution → WIP Management` (`/production/wip`)
* **Key Operations:**
  * **Inter-Stage Transfer:** Transfer completed batches from one Work Center buffer to the downstream Work Center (`POST /production/wip/{wip}/transfer`).
  * **WIP Adjustment:** If parts are physically dropped or damaged in transit between machines, perform an audited WIP write-down (`POST /production/wip/{wip}/adjust`).
  * **Convert to Finished Goods:** When the final routing stage completes, click **Convert WIP to Finished Goods** (`POST /production/wip/{wip}/convert`) to trigger commercial warehouse intake.

---

## 7. Quality Inspection & Non-Conformance Management

### 7.1 Quality Inspections

* **Trigger:** Configured Quality Plans can require inspections at specific routing checkpoints (e.g., first-piece inspection, in-process check, or pre-packaging audit).
* **Navigation:** `Production → Quality → Inspections` (`/production/quality/inspections`)
* **Performing Inspections:**
  1. Open the pending inspection record.
  2. Record measured dimensions or test values against standard tolerances.
  3. The system highlights out-of-tolerance values in red.
  4. Decision: Click **Approve & Accept** or **Reject**.

### 7.2 Non-Conformance Reports (NCR)

When goods fail inspection, an NCR is generated automatically.

* **Navigation:** `Production → Quality → NCRs` (`/production/quality/ncrs`)
* **Dispositions Available:**
  * **Rework:** Routed to `ReworkService` to generate a dedicated repair job order (`RWK-XXXXX`).
  * **Operational Scrap:** Material is condemned and written off as process scrap.
  * **Use As Is (Deviation):** Requires formal sign-off via `/production/quality/deviations`.
  * **Return to Vendor:** Applicable for defective subcontracted services or supplier parts.

### 7.3 Corrective and Preventive Actions (CAPA)

For critical or recurring defects, open a CAPA (`/production/quality/capas`) to conduct structured root-cause analysis (5-Why analysis, Fishbone diagram) and implement preventive actions.

---

## 8. Subcontracting Management

When manufacturing involves external third-party processing (e.g., electroplating, heat treatment):

### 8.1 Procurement Automation

* If the tenant setting `subcontract_procurement_workflow` is set to `auto_approved_po` and the total cost is below the `subcontract_auto_approval_limit`, releasing the production order automatically generates an approved Purchase Order for the subcontract vendor.
* If above the threshold or validation checks fail, the system falls back safely to creating a `Draft PO` for purchasing team review.

### 8.2 Subcontract Delivery Challan (Gate Pass)

Raw materials or intermediate WIP sent to a vendor must be accompanied by an official Delivery Challan.

* **Navigation:** `Production → Subcontracting → Delivery Challans` (`/production/subcontract/delivery-challans`)
* **Workflow:**
  1. Click **Create Delivery Challan**.
  2. Select the subcontract operation, vendor, vehicle number, and dispatch date.
  3. Click **Dispatch Challan** (`POST /delivery-challans/{challan}/dispatch`). Internal WIP is debited to vendor custody.
  4. Click **Print Gate Pass** to generate the mandatory statutory transit slip.
  5. When processed goods return, click **Receive Goods** (`POST /delivery-challans/{challan}/receive`). The system reconciles returned quantities, vendor rejects, and returns custody to shopfloor WIP.

---

## 9. Plant Maintenance & Equipment Reliability

Equipment reliability directly affects manufacturing capacity.

* **Navigation:** `Production → Maintenance → Dashboard` (`/production/maintenance/dashboard`)

### 9.1 Preventive Maintenance (PM)

* Set up recurring maintenance intervals (e.g., every 30 days or every 500 operating hours) via `Production → Maintenance → PM Schedules`.
* Click **Generate Due Work Orders** to automatically convert upcoming maintenance intervals into actionable maintenance tickets.

### 9.2 Corrective Breakdown Workflow

1. If a machine crashes unexpectedly, click **Report Breakdown** (`POST /maintenance/work-orders/breakdown`).
2. Enter machine ID, breakdown severity, and failure symptoms.
3. **Immediate Automation:** Machine state transitions to `Breakdown` / `Under Maintenance`. The MES scheduler immediately blocks new operations on that machine.
4. Maintenance technicians assign mechanics with hourly wage rates.
5. Spare parts are requested and fulfilled through stores or direct issue (see Section 9.3).
6. When repairs conclude, click **Complete Work Order**. The downtime log is closed, total repair and spare parts costs are calculated in tenant base currency, and the machine is restored to `Active`. (If repair is economically unviable, select **Complete & Scrap Machine** to decommission the asset).

### 9.3 Maintenance Spare-Parts Requisition & Store Fulfillment

When machine maintenance requires replacement parts (bearings, seals, belts, hydraulic valves):

1. **Requesting Spares:**
   * On the Maintenance Work Order details page, navigate to the **Spare Parts** tab and click **Request Spare Part**.
   * Select the product SKU and requested quantity. Specifying a warehouse is optional; maintenance engineers may leave it blank so the warehouse team can designate optimal stock bins.
   * **Automated Requisition Creation:** The system automatically generates or appends to a central **Store Requisition Slip** (`ProductionRequisitionSlip` with `source_type = 'maintenance_work_order'`).
2. **Fulfillment Paths:**
   * **Path A — Central Store Queue (`/inventory/material-requests`):**
     * The storekeeper opens the pending Store Material Request queue.
     * The storekeeper selects the issuing warehouse, reserves stock (`MaterialRequestService::reserve`), and executes **Issue**.
     * The system records a stock outflow (`StockService::recordOutflow` with reference `'MaintenanceWorkOrder'`), decrements physical warehouse inventory, calculates valuation cost, and synchronizes the Maintenance Work Order's `spare_parts_cost` and `total_cost`.
   * **Path B — Direct Maintenance Screen Issue (`/maintenance/work-orders/spares/{id}/issue`):**
     * Authorized plant engineers can click **Issue Spare** directly on the Work Order screen.
     * The system validates stock availability via `StockService::getAvailableStock`, records outflow, updates the work order cost rollups, and marks the linked store requisition slip item as issued.
3. **Shortage Handling & Controls:**
   * Both fulfillment paths enforce strict over-issue validation (`issue_qty <= requested_qty - issued_qty`) and block negative stock.
   * If parts are out of stock, purchasing raises a Purchase Requisition before the spare can be issued.

---

## 10. Lot Traceability & Label Printing

### 10.1 Barcode & QR Label Printing

Physical tracking requires standardized industrial labels.
* **Navigation:** Use print shortcuts on order, batch, and serial records:
  * Order Traveler: `/production/labels/orders/{id}`
  * Batch Barcode Tag: `/production/labels/batches/{id}`
  * Individual Serial QR Code: `/production/labels/serials/{id}`
  * Product SKU Label: `/production/labels/products/{id}/sku`
* Labels are rendered in clean, printer-friendly HTML and format directly on thermal barcode printers (Zebra, TSC, Brother).

### 10.2 Bidirectional Genealogy Query

In regulated industries (automotive, aerospace, medical, food), complete traceability is mandatory:
* **Navigation:** `Production → Traceability → Genealogy Explorer` (`/production/mes/traceability`)
* **Search:** Search by finished good serial number, internal batch code, or supplier raw material lot.
* **Results:**
  * **Backward Trace:** Finished Serial → Production Batch → Work Centers → Raw Material Supplier Lots & Purchase Invoices.
  * **Forward Trace:** Defective Supplier Raw Lot → Consumed Production Batches → Shipped Finished Goods → Customer Sales Orders.

---

## 11. Reports, Dashboards & Cost Variance

* **Executive Dashboard:** `/production/intelligence/dashboard` — Live plant OEE (Availability × Performance × Quality), machine state distributions, and output pacing.
* **Production MIS Reports:** `/production/intelligence/reports`
  * **Production Orders Summary:** Batch throughput, completion percentages, and delivery schedules.
  * **Material Consumption & Variance:** Planned component quantities vs actual quantities issued and floor scrap.
  * **Manufacturing Cost Variance:** Planned cost vs actual material, labor, machine overhead, and manual cost adjustments.
* **Exporting:** Every report supports one-click export to streaming CSV, multi-sheet Excel (`.xlsx`), and print-ready PDF, with all monetary values denominated in the tenant's active currency symbol (`active_currency_symbol()`).

---

## 12. Common User Mistakes & Troubleshooting Tips

| Mistake / Issue | Common Symptom | Root Cause | Recommended Resolution |
|---|---|---|---|
| **Cannot start MES operation** | Error 403 or "Machine under maintenance" message | The machine assigned to this operation is in `Breakdown` or `Under Maintenance` state | Complete the active Maintenance Work Order on `/production/maintenance/dashboard` to restore the machine to `Active`. |
| **Material Issuance blocked** | "Insufficient stock available in warehouse" | Warehouse on-hand stock is lower than required quantity, or stock is in another warehouse | Navigate to Inventory, verify physical stock, transfer stock to the production warehouse, or run MRP to raise a Purchase Requisition. |
| **BOM cannot be selected in Plan** | BOM does not appear in dropdown | BOM is still in `Draft` or `Pending Approval` state | Open the BOM, submit it, and have an authorized manager click **Approve BOM**. |
| **Schedule Dispatch Board dragging disabled** | Operations cannot be shifted or leveled | Operation sequence is toggled as **Locked** (`is_locked = true`) | Click the lock icon on the Dispatch Board operation card to unlock it prior to rescheduling. |
| **Subcontract PO created as Draft instead of Approved** | PO status is `Draft` requiring manual purchase approval | Total cost exceeded the tenant's auto-approval limit, or the vendor was inactive | Review the PO in the Purchase module, verify vendor details, or increase the auto-approval threshold under `/production/settings`. |

---

## 13. Visual Verification & Screenshot Checklist

When auditing the production UI or assembling training collateral, verify the presence and layout of these verified screens and interactive controls:

| Screen Identifier | Target Route | Mandatory UI Elements & Controls to Verify |
|---|---|---|
| **MES Operator Terminal** | `/production/mes` | Operation status cards, active execution timer, "Start Operation", "Pause Operation", "Log Progress", "Log Scrap", and red "Andon Alert" button. |
| **Production Order Show** | `/production/orders/{id}` | Order header badge, Material Reservations tab, Operations sequence list, "Issue Material" button, "Print Traveler", and "Receive Finished Goods" action. |
| **Production Plan View** | `/production/plans/{id}` | Plan status badge (`Draft` / `Approved` / `Released`), planned date range, "Run MRP Engine" button, shortage summary table, and "Release Plan" action. |
| **BOM Engineering View** | `/production/boms/{id}` | Status badge (`Draft` / `Approved`), version pill, component hierarchy table, scrap percentage column, and "Create Revision" button. |
| **Scheduling Dispatch Board** | `/production/schedules/dispatch` | Work Center swimlanes, draggable operation cards, machine assignment tags, lock/unlock toggle icon, and schedule conflict warnings. |
| **Subcontract Delivery Challan**| `/production/subcontract/delivery-challans` | Outward Gate Pass table, vehicle registration input, "Dispatch Challan" action, and "Receive Processed Goods" reconciliation modal. |
| **Maintenance Work Order** | `/production/maintenance/work-orders/{id}` | Downtime duration counter, machine state selector, Mechanic Assignment table, and "Spare Parts Requisition" tab with store issue status. |
| **Traceability Explorer** | `/production/mes/traceability` | Dual-direction search bar, interactive lot hierarchy tree, supplier purchase order link, and linked customer sales order cards. |
| **Production MIS Reports** | `/production/intelligence/reports` | Date range filter, format selection buttons ("Export CSV", "Export Excel", "Export PDF"), and active tenant currency symbol in column headers. |
