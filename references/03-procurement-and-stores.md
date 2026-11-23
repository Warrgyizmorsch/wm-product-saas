# 03 - Procurement and stores (FRS 2.11, 2.12, 2.13, F12)

---------------------------------------------------------------------------
## 2.11 Purchase and RFQ - PARTIAL (existing: Purchase)

**Current workflow (verified)**
1. Shortage lines in the Sales Material Requirement create a Purchase Requisition (draft, linked to the requirement; line status Waiting Purchase / Partially PR Raised). Requisitions can also be created manually.
2. Requisition items go to RFQ (several vendors, rates, comparison, savings dashboard) -> PO created from the RFQ (or directly from pending items) -> PO approval.
3. Goods receipt (GRN) approval records stock inflow, raises GoodsReceiptNoteApproved, auto-creates the vendor bill; 3-way match; landed cost; purchase returns; advances; vendor payments; TDS on bills.
4. **Vendor branches, delivery targets & GSTR-2B branch matching (C-17, built Nov 2026).** Purchase orders carry `vendor_branch_id`, frozen `supplier_gstin`/`supplier_state_code` and `buyer_gstin`/`buyer_state_code`, and require **exactly one** delivery target: our warehouse (`location`, dynamically filtered by branch on both create and edit forms), our own receiving branch (`delivery_branch_id`), or a customer site drop-ship (`delivery_party_branch_id` + optional `site_contact_id`). Both `purchase/orders/create.blade.php` and `purchase/orders/edit.blade.php` support interactive delivery target switching, cascading warehouse/site-contact dropdowns, and supplying vendor branch selection with auto GST resolution. Semantics on `purchase_orders`: `branch_id` (from `BelongsToBranch`) stays the ordering/billing branch and source of our `buyer_gstin`; `delivery_branch_id` is the receiving branch (PO's global branch scope includes `OR delivery_branch_id = current_branch_id` so receiving branches can view and receive POs ordered on their behalf). Direct-to-customer-site POs receive GRNs against a virtual `Direct-to-Site (Virtual)` warehouse (`Warehouse::ensureDirectToSiteWarehouse`) so 3-way match and billing run without inflating physical godown stock. `vendor_bills` inherit `vendor_branch_id`, `supplier_gstin`, and `buyer_gstin` from the PO so `Gstr2bReconciliationService` matches against vendor-branch GSTINs and guards against mixed buyer GSTINs, while payables remain on the parent vendor's single ledger. Inventory Warehouses expose a Branch assignment dropdown on the warehouse directory screen.

**Required workflow (FRS)**
1. Requisitions are created automatically when free stock + open orders cannot cover approved demand.
2. Similar requirements are grouped; draft RFQs for the same vendor can be merged ("Merge RFQs") only when vendor, company, currency, delivery location, terms, tax and dates match. Lines merge only when item, specification, revision and unit match; each merged line still shows which Job Order needs how much and by when; all source requirements stay openable. Source RFQs keep merged/cancelled status.
3. RFQs use the vendor's own item names/codes, last price, MOQ, lead time, specification.
4. Quotes compared on landed cost, quality, delivery, payment terms; exceptions approved.
5. A vendor price increase needs old and new price, effective date, reason and approval before any PO may use it.
6. On PO confirmation: expected receipts, QC requirements, payment milestones, follow-ups are set up automatically; vendor acknowledgement and changed promised dates are recorded.
7. An RFQ already sent cannot be merged/changed without a revision and fresh approval; a requirement already on a PO can never be ordered again.

**Changes required:** C-20 merge RFQs; C-21 price-increase approval; C-22 acknowledgement/promised dates/over-receipt; C-23 duplicate-demand guard + sent-RFQ revision + auto-requisition; C-19 vendor item codes/price history.
**Connects with:** Sales MaterialRequirement (demand), Production MRP (demand), Inventory (receipts), Accounting (bill, TDS), Party master (vendor hold blocks POs).
**Acceptance (BASE-11):** three Job Orders need one item across two compatible draft RFQs; merge keeps every source quantity/date; ordering an already-ordered demand is blocked.

---------------------------------------------------------------------------
## 2.12 Job work, metal and e-way bill - PARTIAL (existing: Production subcontract, Purchase, Sales e-way)

**Current workflow (survey)**
Subcontract delivery challans, subcontract procurement/receipt orchestrators, material balance and performance analytics, GRN approval triggers subcontract receipt processing (ProcessSubcontractReceiptOnGrnApproved), e-way bill service, transporter master.

**Required workflow (FRS)**
1. Job work with Encore material: service PO + returnable challan; material sent is shown as stock AT THE VENDOR. On return the system matches sent / used / returned / scrapped / lost / pending.
2. Items bought complete = no company material issued; both routes cannot be used for the same requirement unless approved.
3. Metal lots: lot number on receipt with actual kg, number of pipes, verified length, supplier batch, size, thickness. The system computes the REAL kg per metre of that lot (e.g. 1.42 vs 1.48). Pipes are issued in METRES; stock reduces in KG by that lot's figure; finished components counted in pieces and traceable to the lot. Standard kg/m is for planning only. Every kg is marked "weighed" or "calculated".
4. Offcuts, reusable lengths and scrap recorded separately with weight.
5. Powder-coat challan records pieces and kg sent; return records received/accepted/rejected/damaged/pending; the vendor's kg bill is checked against actuals. Standard backflush within tolerance; excess needs reason/approval. Service and freight cost apply to the processed component.
6. E-way bill is made directly from the job-work challan (no e-invoice) and for the return trip; vehicle change, cancellation, extension supported.

**Changes required:** B-31 metal lot model; B-32 issue in metres / deplete kg / piece traceability; B-33 powder-coat reconciliation vs vendor bill; B-34 e-way from job-work challan.
**Connects with:** Inventory (lots, vendor stock), Production (routing sourcing), Purchase (service PO/bill), Accounting (landed/service cost), Logistics (gate pass).
**Acceptance (BASE-12):** two lots of the same pipe with different kg/m; equal lengths deplete different kg; vendor stock and accepted/rejected/lost/pending pieces reconcile to the service bill.

---------------------------------------------------------------------------
## 2.13 Stores, boards and offcuts - PARTIAL (existing: Inventory)

**Current workflow (survey)**
Warehouses with bins, batches and serials, stock reservations, adjustments, transfers, barcode API, valuation tables; REMNANTS (offcuts) with consumption tracking (InventoryRemnant, RemnantInventoryService); production allocates remnants (RemnantAllocationService). Stock moves through StockService (inflow/outflow).

**Required workflow (FRS)**
1. Goods received only against PO, job-work return, production or transfer; items needing inspection go to QC HOLD first; accepted stock is put away by warehouse/zone/rack/shelf/bin using barcode scans.
2. Stock is reserved by Job Order AND revision; moving to another job needs permission.
3. Material is issued only to an authorised production step, job worker, packing or site.
4. Returns, offcuts, scrap, vendor stock, WIP and site stock are kept separate; differences are handled by cycle counts and approved adjustments. Posted quantities are never edited directly; backdating/adjustments need reason, evidence, user, device, timestamp. Available stock excludes QC hold, rejected, vendor-held, WIP, packed, in-transit, site-issued and installed states.
5. Boards (particle board, MDF, plywood): own ID + barcode at receipt, stock in sheets AND sq ft, attributes (material, grade, thickness, colour, finish, shade, grain direction, original size, current usable size, location, status).
6. At cutting the operator scans the board and records pieces; each reusable offcut gets its own ID linked to the board and Job Order; pieces below the minimum size go to scrap.
7. FIT CHECK before allocating: panel must fit the board/offcut after saw loss and edge trim and in the right grain direction (an 8x4 sheet + 22 sq ft offcut vs two 27 sq ft offcuts: same 54 sq ft, only the sheet gives a full panel).
8. No stock can go negative at any level; a board reserved for one Job Order cannot be given to another.

**Changes required:** B-35 fit check; B-36 per-board ID + dual stock + attributes; B-37 cycle-count workflow; B-38 availability excludes non-usable states; B-39 reservation by Job Order + revision.
**Connects with:** Purchase (GRN), Production (issues, cutting), Quality (hold/reject), Logistics (in transit/site stock), Installation (site store), Accounting (valuation).
**Acceptance (BASE-13):** only a geometrically fitting available sheet is accepted; child offcuts keep parent/grain/Job Order lineage; no double allocation.

---------------------------------------------------------------------------
## F12 Consumables - NEW feature inside Inventory (+ Production)
**Flow:** consumable items are issued by a controlled request to a work centre/Job Order, actual use is recorded at consumption, unused quantity is returned, cost posts to the Job Order (B-41).
**Today:** only generic stock issue exists.
