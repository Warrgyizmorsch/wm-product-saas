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
