# Production Module — Business Use Cases & Operating Scenarios

> **Module:** Production Planning & MES (`wm-product-saas`)  
> **Target Document:** `docs/user-manuals/production/USE_CASES.md`  
> **Audience:** Business Analysts, QA Engineers, Solution Architects, Operations Leads  
> **Standards:** Implementation-Verified Business Rules & Test Evidence

---

## Use Cases Summary Matrix

| Use Case ID | Scenario Title | Primary Actor | Primary Domain Services | Outcome |
|---|---|---|---|---|
| **UC-PROD-01** | Make-to-Order Production Execution | Production Planner | `ProductionOrderService`, `MesExecutionService` | Order completed; Finished goods received |
| **UC-PROD-02** | Material Shortage & Automated Purchase Requisition | Master Scheduler | `MrpEngineService`, `MrpShortageService` | Net shortage converted to Purchase Requisition |
| **UC-PROD-03** | Material Reservation & Warehouse Store Issuance | Storekeeper | `ProductionMaterialService`, `StockService` | Warehouse stock deducted; Issued to order |
| **UC-PROD-04** | Partial Operation Progress & Inter-Stage WIP Transfer | Machine Operator | `MesExecutionService`, `ProductionWipService` | Partial WIP transferred between work centers |
| **UC-PROD-05** | Quality Inspection Failure, NCR & Rework Execution | QC Inspector | `QualityInspectionService`, `ReworkService` | NCR logged; Dedicated rework order repaired |
| **UC-PROD-06** | Unrecoverable Rework Failure Escalation to Scrap | Rework Specialist | `ReworkService`, `ScrapService`, `StockService` | Failed rework converted to operational scrap |
| **UC-PROD-07** | Subcontract Processing & Delivery Challan Dispatch | Subcontract Lead | `SubcontractProcurementOrchestrator`, `SubcontractMaterialBalanceService` | Auto PO approved; Gate pass issued to vendor |
| **UC-PROD-08** | Emergency Breakdown, MES Lockout & Spare Fulfillment | Maintenance Tech | `MaintenanceWorkOrderService`, `MaintenanceSpareService` | Machine locked out; Spares issued; Restored |
| **UC-PROD-09** | Sheet Metal Cutting & Dimensional Remnant Inventory | Machine Operator | `MesExecutionService`, `RemnantAllocationService` | Offcut saved to remnant catalog for reuse |
| **UC-PROD-10** | Contaminated Supplier Lot Recall (Bidirectional Trace) | Quality Manager | `LotTraceabilityService` | Multi-tier forward trace resolves customer orders |
| **UC-PROD-11** | Order Cost Variance & Unplanned Cost Adjustment | Plant Controller | `ProductionCostAdjustmentService`, `ReportingService` | Manual adjustment attached and reported |

---

## UC-PROD-01: Discrete Make-to-Order Production Execution

### 1. Business Objective
Fulfill a custom sales order by releasing a discrete production order, manufacturing the product through multiple routing operations, and receiving commercial finished goods into warehouse inventory.

### 2. Actors & Permissions
* **Actors:** Production Planner, Shopfloor Operator, Warehouse Storekeeper.
* **Permissions:** `production.orders.create`, `production.orders.release`, `production.mes.execute`, `production.orders.update`.

### 3. Preconditions
* Finished product `FG-DESK-001` has an `Approved` BOM and `Approved` Routing.
* Sales Order `SO-2026-0042` is confirmed in the Sales module.
* Raw materials are in stock in the main raw materials warehouse.

### 4. Sample Data
* Product: `FG-DESK-001` (Modular Office Desk).
* Quantity: `50 Units`.
* Operations: Sequence `10` (Cutting & Edge Banding), Sequence `20` (Assembly & Packaging).

### 5. Main Flow
1. Planner navigates to `/production/orders/create`, inputs quantity `50`, selects `FG-DESK-001`, and links `SO-2026-0042`.
2. System initializes order status `draft` and soft-reserves raw materials (`ProductionOrderReservation`).
3. Planner reviews resource readiness and clicks **Release Order** (`POST /production/orders/{id}/release`). Status transitions to `released`.
4. Storekeeper confirms physical picking slip and clicks **Issue Material** (`POST /production/orders/{id}/issue`). System executes `StockService::recordOutflow()`. Order transitions to `in_progress`.
5. Operator at Cutting Cell starts sequence `10` on `/production/mes`, completes 50 units, and submits.
6. Operator at Assembly Cell starts sequence `20`, completes 50 units, and submits.
7. Planner executes **Receive Finished Goods** (`POST /production/orders/{id}/receive-fg`).
8. System executes `StockService::recordInflow()`, crediting 50 units to Finished Goods Warehouse. Order transitions to `completed`.

### 6. Alternate & Failure Flows
* **Insufficient Raw Stock:** Order release is blocked with a warning dialog indicating missing component stock. Planner triggers UC-PROD-02.
* **Order Completion Gate Block:** If open rework orders, pending subcontract operations, or unresolved NCRs exist, completion is blocked per `ProductionOrderCompletionValidator`.

### 7. Expected Records & Database Impact
* `production_orders`: `status = 'completed'`, `quantity_produced = 50`.
* `stock_transactions`: Outflow records for raw materials (`reference_type = 'Production Material Issue'`); inflow record for `FG-DESK-001` (`reference_type = 'Production Receipt'`).
* `product_warehouse_stocks`: On-hand balance updated in Inventory.
* `journal_entries` & `journal_entry_lines`: Automatic WIP journal posted via `PostProductionConsumptionJournal` (Dr. WIP 1204 / Cr. Raw Materials Inventory).

### 8. Acceptance Criteria & Test Evidence
* `tests/Feature/Production/ProductionOrderAndWipUiTest.php` passes.

---

## UC-PROD-02: Material Shortage Discovery & Automated Purchase Requisition

### 1. Business Objective
Identify raw material deficits during aggregate demand planning and automatically raise Purchase Requisitions to prevent manufacturing bottlenecks.

### 2. Actors & Permissions
* **Actors:** Master Scheduler, Purchasing Executive.
* **Permissions:** `production.plans.manage`, `purchase.requisitions.create`.

### 3. Preconditions
* Production Plan `PLAN-2026-10` is created for 200 units of `FG-TABLE-01`.
* Warehouse stock for component `RAW-STEEL-TUBE` is currently 50 meters; required quantity is 400 meters.

### 4. Main Flow
1. Scheduler navigates to `/production/plans/{plan}` and clicks **Run MRP Engine**.
2. `MrpEngineService` explodes multi-level BOM items and aggregates gross requirements.
3. System identifies a net deficit of 350 meters for `RAW-STEEL-TUBE`.
4. System inserts record into `production_plan_requirements` with `is_shortage = true` and `shortage_qty = 350`.
5. Scheduler navigates to `/production/mrp/shortages` and clicks **Generate Purchase Requisition**.
6. System orchestrates creation of an external Purchase Requisition (`PR-2026-0089`) in the Purchase module.

### 5. Expected Records & Impact
* `production_plan_requirements`: `shortage_qty = 350`.
* `purchase_requisitions`: Created with item `RAW-STEEL-TUBE`, quantity 350.

### 6. Acceptance Criteria & Test Evidence
* `tests/Feature/Production/ComponentLevelExecutionValidationTest.php` passes.

---

## UC-PROD-03: Material Reservation & Warehouse Store Issuance

### 1. Business Objective
Guarantee that warehouse physical inventory is securely earmarked for an active manufacturing order, accurately decremented when physical goods depart the store, and posted to the General Ledger WIP account.

### 2. Actors & Permissions
* **Actors:** Storekeeper, Production Lead.
* **Permissions:** `production.orders.update`.

### 3. Main Flow
1. Order `PO-2026-0100` is created. System executes `ProductionOrderService::reserveMaterials()`.
2. `production_order_reservations` records are inserted with `status = 'active'`. Available inventory in Inventory module reflects reserved quantities.
3. Storekeeper prints `ProductionRequisitionSlip`.
4. Storekeeper picks items and enters lot numbers in `/production/orders/{id}/issue`.
5. System invokes `StockService::recordOutflow()`, inserting audited `stock_transactions` (`reference_type = 'Production Material Issue'`) and updating `production_order_issues`.
6. Event listener `PostProductionConsumptionJournal` captures `StockOutflowRecorded` and books automated double-entry GL journal:
   * **Debit:** Work-in-Progress (Account 1204 / Asset).
   * **Credit:** Raw Material Inventory Asset Account.
   * **Source:** `production`.

### 4. Acceptance Criteria & Test Evidence
* `tests/Feature/Production/ComplexIndustrialManufacturingScenarioTest.php` passes.

---

## UC-PROD-04: Partial Operation Progress & Inter-Stage WIP Transfer

### 1. Business Objective
Record partial manufacturing progress on a high-volume batch and transfer semi-finished work-in-progress (WIP) to a downstream work center without waiting for entire batch completion.

### 2. Actors & Permissions
* **Actors:** Machine Operator, Shopfloor Lead.
* **Permissions:** `production.mes.execute`, `production.wip.manage`.

### 3. Main Flow
1. Batch of 500 units is running on Sequence `10` (Press Cell).
2. Operator completes 200 units and clicks **Log Progress** (`POST /production/mes/{op}/log-progress`).
3. System creates `ProductionOrderProgressLog` for 200 units and initializes `ProductionWip` record in `WC-PRESS-01` output buffer.
4. Shopfloor Lead opens `/production/wip`, selects the 200 units, and clicks **Transfer WIP** (`POST /production/wip/{id}/transfer`).
5. Destination selected: `WC-WELD-02` (Welding Cell).
6. System inserts `ProductionWipTransaction` recording inter-cell transfer.
7. Welding operator immediately starts Sequence `20` on the 200 available units.

### 4. Acceptance Criteria & Test Evidence
* `tests/Feature/Production/WipReconciliationAndPipelineTest.php` passes.

---

## UC-PROD-05: Quality Inspection Failure, NCR & Rework Execution

### 1. Business Objective
Isolate defective components during in-process quality inspection, generate a Non-Conformance Report (NCR), and repair the parts through a controlled rework order.

### 2. Actors & Permissions
* **Actors:** QC Inspector, Rework Technician.
* **Permissions:** `production.quality.inspect`, `production.mes.execute`.

### 3. Main Flow
1. QC Inspector tests a batch of 50 units at Sequence `20`.
2. 5 units fail dimensional tolerance. Inspector logs 45 Accepted, 5 Rejected on `/production/quality/inspections/{id}/results`.
3. System automatically generates `ProductionNcr` (`NCR-2026-0012`).
4. Quality Lead reviews NCR and sets disposition to **Rework** (`POST /production/quality/ncrs/{id}/disposition`).
5. `ReworkService` creates `ProductionReworkOrder` (`RWK-2026-0004`) with specific disassembly and refabrication operations.
6. Technician executes rework operations on `/production/quality/rework`.
7. Re-inspection passes. 5 repaired units are returned to the parent order's WIP balance.

### 4. Acceptance Criteria & Test Evidence
* `tests/Feature/Production/QualityManagementTest.php` passes.

---

## UC-PROD-06: Unrecoverable Rework Failure Escalation to Scrap

### 1. Business Objective
Safely write off defective units that cannot be salvaged during rework, updating scrap records and material efficiency metrics.

### 2. Actors & Permissions
* **Actors:** Quality Lead, Plant Supervisor.
* **Permissions:** `production.quality.inspect`.

### 3. Main Flow
1. Rework order `RWK-2026-0004` is in progress.
2. During refabrication, technician discovers irreparable internal metal cracking on 2 units.
3. Supervisor opens `/production/quality/rework/{id}` and clicks **Fail Rework** (`POST /production/quality/rework/{id}/fail`).
4. System marks rework order as `failed` and triggers `ScrapService::recordOperationalScrap()`.
5. Material is written off as scrap, and a scrap disposal record is generated.

### 4. Acceptance Criteria & Test Evidence
* `tests/Feature/Production/ReworkFailureWorkflowTest.php` passes.

---

## UC-PROD-07: Subcontract Processing & Delivery Challan Dispatch

### 1. Business Objective
Outsource a specialized operation (e.g., powder coating) to an approved vendor, automatically generating an approved Purchase Order and statutory transit Delivery Challan.

### 2. Actors & Permissions
* **Actors:** Subcontract Executive, Warehouse Dispatcher.
* **Permissions:** `production.subcontract.manage`, `production.orders.release`.

### 3. Preconditions
* Tenant setting `subcontract_procurement_workflow` is set to `auto_approved_po`.
* `subcontract_auto_approval_limit` is set to `$5,000.00`.
* Operation #30 requires powder coating for 100 units at unit rate `$15.00` (Total `$1,500.00`).

### 4. Main Flow
1. Planner releases production order.
2. `SubcontractProcurementPolicyResolver` verifies total cost ($1,500.00) is below limit ($5,000.00).
3. `SubcontractProcurementOrchestrator` automatically creates approved `PurchaseOrder` in Purchase module.
4. Subcontract Executive navigates to `/production/subcontract/delivery-challans/create`.
5. System checks internal raw/WIP stock availability (`check-stock`).
6. Executive clicks **Dispatch Challan** (`POST /subcontract/delivery-challans/{id}/dispatch`).
7. System prints statutory Delivery Challan (Gate Pass). Stock is marked in vendor custody.
8. Vendor returns finished parts. Executive clicks **Receive Goods** (`POST /delivery-challans/{id}/receive`). WIP is restored to factory.

### 5. Acceptance Criteria & Test Evidence
* `tests/Feature/Production/SubcontractProcurementAutomationWorkflowTest.php`, `SubcontractDeliveryChallanTest.php` pass.

---

## UC-PROD-08: Emergency Breakdown, MES Lockout & Spare Fulfillment

### 1. Business Objective
Manage an unpredicted machinery failure by locking out shopfloor operations, assigning mechanics, issuing replacement spares from inventory, and reopening machine availability upon repair.

### 2. Actors & Permissions
* **Actors:** Machine Operator, Maintenance Engineer, Storekeeper.
* **Permissions:** `production.mes.execute`, `production.maintenance.manage`.

### 3. Main Flow
1. Hydraulic press `MCH-PRESS-01` develops high-pressure seal failure.
2. Operator clicks **Report Breakdown** (`POST /production/maintenance/work-orders/breakdown`).
3. Machine status transitions to `Breakdown` / `Under Maintenance`.
4. Operator on MES console attempting to start a job on `MCH-PRESS-01` is blocked with a red lockout banner.
5. Maintenance Engineer creates Work Order `MWO-2026-0033`, assigns mechanic with hourly rate `$45.00/hr`, and adds replacement seal spare part (`MaintenanceSpareService::addSpareRequest`).
6. System automatically creates/appends to a Store Requisition Slip (`ProductionRequisitionSlip` with `source_type = 'maintenance_work_order'`).
7. Storekeeper fulfills request via central Store Material Request queue (`/inventory/material-requests`), or Maintenance Engineer issues directly (`POST /maintenance/work-orders/{id}/spares/{spareId}/issue`). `StockService::recordOutflow()` deducts the spare from inventory, updates spare unit cost, and synchronizes MWO `spare_parts_cost`.
8. Engineer logs completed repair, entering additional contractor cost `$120.00`.
9. Engineer clicks **Complete Work Order**. Total repair cost is calculated in base currency.
10. Machine status transitions back to `Active`. MES terminal unblocks.

### 4. Acceptance Criteria & Test Evidence
* `tests/Feature/Production/MaintenanceWorkflowTest.php` passes.
* `tests/Feature/Production/MaintenanceMaterialRequestIntegrationTest.php` passes (7/7 tests, 51 assertions).

---

## UC-PROD-09: Sheet Metal Cutting & Dimensional Remnant Inventory

### 1. Business Objective
Capture valuable offcuts and remnants generated during raw sheet metal cutting, storing them with dimensional metadata for future production reuse.

### 2. Actors & Permissions
* **Actors:** CNC Laser Operator.
* **Permissions:** `production.mes.execute`.

### 3. Main Flow
1. Operator cuts parts from a standard 3000mm × 1500mm sheet metal plate.
2. A usable section measuring 1200mm × 800mm remains.
3. Operator clicks **Save Remnant** on the MES terminal (`POST /production/mes/{op}/remnant`).
4. Operator enters length `1200`, width `800`, thickness `3.0mm`, and specifies rack location `RACK-REMNANT-B2`.
5. System logs `production_order_remnant_allocations` and indexes the remnant in the inventory offcut catalog.
6. Subsequent planners can allocate this remnant before cutting new stock.

### 4. Acceptance Criteria & Test Evidence
* `tests/Feature/Production/RemnantScrapLifecycleTest.php` passes.

---

## UC-PROD-10: Upstream Defective Supplier Lot Recall (Bidirectional Trace)

### 1. Business Objective
Execute a comprehensive forward traceability search following a supplier defect notification, identifying all affected internal production batches and dispatched customer orders.

### 2. Actors & Permissions
* **Actors:** Quality Assurance Manager, Regulatory Officer.
* **Permissions:** `production.orders.view`.

### 3. Main Flow
1. Supplier notifies quality team that fastener lot `LOT-STEEL-998` had improper hardening.
2. Manager opens `/production/mes/traceability` and enters `LOT-STEEL-998`.
3. `LotTraceabilityService::traverse()` executes a graph traversal across `production_order_issues`, `production_batches`, and `production_lot_traces`.
4. Output displays:
   * Consumed in Production Orders: `PO-2026-0044` and `PO-2026-0048`.
   * Finished Product Batches: `BATCH-20261001-01` (Desk Units).
   * Customer Shipments: Fulfillments for Sales Order `SO-9912` (Acme Corp).
5. Manager exports the audited trace to CSV for regulatory compliance.

### 4. Acceptance Criteria & Test Evidence
* `tests/Feature/Production/ProductionTraceabilityIntegrationTest.php` passes.

---

## UC-PROD-11: Manufacturing Cost Variance & Unplanned Cost Adjustment

### 1. Business Objective
Record an unplanned mid-production expense (e.g., expedited courier transit or specialized tooling replacement) against a production order to ensure true cost tracking.

### 2. Actors & Permissions
* **Actors:** Plant Controller, Production Manager.
* **Permissions:** `production.cost_adjustment.manage`.

### 3. Main Flow
1. Controller opens `/production/orders/{id}` and navigates to the **Cost Breakdown** tab.
2. Controller clicks **Add Cost Adjustment** (`POST /production/orders/{order}/cost-adjustments`).
3. Form input: Adjustment type `tooling_charge`, amount `$250.00`, reason "Emergency diamond blade replacement", and attaches supplier invoice receipt.
4. System converts `$250.00` to base currency and records `ProductionCostAdjustment`.
5. The order's actual total cost recalculates immediately, factoring the adjustment into the MIS Cost Variance report.

### 4. Acceptance Criteria & Test Evidence
* `tests/Feature/Production/ProductionCostAdjustmentTest.php`, `ProductionMisReportsTest.php` pass.
