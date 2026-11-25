# Production Execution — Draft Rules: Material Display, Readiness, Operation Tracking & Order Lifecycle

> **Status (2026-11-23, updated):** 🟢 **Locked on the user's instruction "apply all changes, then I will directly verify them"** — every open decision **D-01 … D-09 is taken as the *Recommended* option** in section 6 (the user did not pick individually). **PX-22 (sub-assembly sequencing) is NOT applied** — the user has not yet explained its details (D-10 / D-11 open). Implementation status of each rule is in section 9; nothing is marked **Verified** (only the user does that).
> *Original draft note:* No application code, migration or data was changed when this was first written.
> **Raised by:** user, 2026-11-23, on a screenshot of the *Operation Tracking* tab (OP10 · `test-semi-02`): *"update the rules for Readiness, Operation Tracking, material requirement being shown double … and even more."*
> **Evidence:** [`production_module_audit_2026-11-23.md`](production_module_audit_2026-11-23.md) (IDs **R-xx**, **B-xx**, **C-xx** below point there) + the root-cause trace in section 0.
> **Builds on (does not replace):** [`production_order_decisions.md`](production_order_decisions.md) PO-04, PO-08, PO-10, PO-12 · [`production_business_rules.md`](production_business_rules.md) G1 (no rounding), G4 (wording), G7 (real user).
> **Workflow:** discuss → *(you are here)* → you decide each **D-xx** in section 6 → rules move to "Locked" → implementation tasks are written → implement on request → **Applied** → only the user marks **Verified**.
> **Number format used in every rule:** whole numbers without decimals, otherwise up to 4 decimals, never rounded to 2 (G1).

---

## 0. Root cause of the doubled material list (verified, read-only)

| Fact | Evidence |
| :--- | :--- |
| The "Material Inputs Required" box and the tree view read `RoutingOperationMaterial::where('routing_operation_id', …)->get()` — **no BOM filter, no selection filter**. | [`orders/show.blade.php`](../resources/views/modules/production/orders/show.blade.php) L2237 (drawer) and L2355 (tree view) |
| A routing operation is **shared by several BOMs**; each BOM keeps its own material rows on it. Routing operation **51** (OP10 of Order #6) has rows for BOMs **10, 11, 13, 16, 17** = **13 rows**; test-raw-01 appears **5×**. | real DB `production_routing_operation_materials` |
| The order itself chose only rows **64, 65** (BOM 11): test-raw-01 ×5 and test-raw-07 ×55 per piece → **25 and 275** for 5 pcs. | `production_order_operations.selected_routing_material_ids` of Order #6 op 18 |
| The correct resolver already exists and is the one PO-12 requires, but **nothing in the views uses it**. | `ProductionOrderOperation::getExpectedComponents()` L554–572 |
| The quantity is `quantity × order quantity` — wrong for sub-assembly operations (their own `target_produced_qty` differs) and ignores scrap (`gross_quantity`). | `show.blade.php` L2250 |
| Child-BOM rows (`child_bom_id` set, e.g. test-semi-01 ×22) are listed as if they were raw materials. | routing materials 76 / 87 / 99 |

So PO-12 ("Shop Floor & Readiness scoping by `source_bom_id` and the selected ids") is **already a locked rule**; the two views simply break it.

---

## 1. Summary index

| ID | Area | Proposed rule (one line) | Source |
| :--- | :--- | :--- | :--- |
| **PX-01** | Material display | One resolver (`getExpectedComponents()`) for every screen; no view queries the routing-material table directly. | PO-12, §0 |
| **PX-02** | Material display | Required qty = `(op target ÷ BOM base qty) × allocation qty × (1 + scrap %)`, from the operation snapshot. | PO-08 |
| **PX-03** | Material display | Per-operation row = required (this op) + order-level reserved / issued / pending (pooled), with a "shared with OPxx" badge. | PO-12 |
| **PX-04** | Material display | Sub-assembly (child-BOM) rows are shown as "produced by OPxx", never as raw material. | PO-08, PO-10 |
| **PX-05** | Readiness | Two quantities: **Ready qty** (from issued material + predecessor output) and **Issuable** (store can still cover, PO-04). | R-03, R-05 |
| **PX-06** | Readiness | Shared materials are allocated to operations in sequence order from one pool — not compared with the full stock each time. | R-04, PO-08 |
| **PX-07** | Readiness | Machine downtime uses real statuses (`open` / `closed`); open downtime blocks; "invented alternates" removed. | R-07, R-08, B-16 |
| **PX-08** | Readiness | Four emitted operation states (READY / PARTIALLY READY / WAITING / BLOCKED) with distinct counts and colours. | R-01, R-02, R-13 |
| **PX-09** | Readiness | Readiness is **enforced at execution** (log / complete capped at Ready qty); release gets a warning, not a silent pass. | R-05, R-06 |
| **PX-10** | Readiness | Readiness is recomputed on events, not on page view; draft orders carry no readiness status. | R-11 |
| **PX-11** | Readiness | The order snapshot is the only source (quality, batch, overlap) — never the live routing. | R-09, R-12, PO-03 |
| **PX-12** | Readiness | Ready qty shown at 4-decimal rule; schedule / resource caveats shown as separate chips. | R-10 |
| **PX-13** | Operation Tracking | Target = `target_produced_qty` of the snapshot; "per FG" ratio = target ÷ order qty (legacy BOM-items lookup removed). | §0, B-14 |
| **PX-14** | Operation Tracking | Good / rejected / scrapped / rework / pending defined once; pending = target − good. | |
| **PX-15** | Operation Tracking | Quantities follow G1 (no 2-decimal rounding, one decimal rule per tab); UoM from the product, never hard-coded "Pcs". | B-14, B-15 |
| **PX-16** | Operation Tracking | One operation status set with fixed meanings; "Not scheduled" and "no machine" are chips, not a contradicting status. | |
| **PX-17** | Operation Tracking | Shortcuts deep-link to *this* operation (MES), and offer "Issue material" when short. | |
| **PX-18** | Operation Tracking | `order.quantity_produced` = sum of good output of the final operation(s); partial logs add up. | B-06 |
| **PX-19** | Order lifecycle | FG receipt / complete / close flow (auto-complete vs. receive FG). | B-05, B-06 → **D-05** |
| **PX-20** | Order lifecycle | Cancel returns issued material. | B-04 → **D-06** |
| **PX-21** | Order lifecycle | Plan → order uses the same engine as a direct order (no second reservation pass). | B-01/02/03 → **D-07** |
| **PX-22** | Sub-assembly sequencing | **RAISED — details pending from user.** When a needed sub-assembly is not available, its (child-BOM) operations are added and placed *before* the operation that needs it, with priority. | user, 2026-11-23 → **D-11** |

---

## 2. Material display (Operation Tracking drawer, tree view, and every other list of operation materials)

### PX-01 — One resolver
* Every screen that lists an operation's materials (order **Operations** tab drawer, **tree view**, MES operator screen, requisition/print views, readiness, API resources) takes them from `ProductionOrderOperation::getExpectedComponents()`: `bom_id = source_bom_id ?: order.bom_id`, limited to `selected_routing_material_ids`, falling back to primary (`is_alternative = false`) rows only for legacy rows without a stored choice.
* Views never call `RoutingOperationMaterial::where(…)` themselves. The two direct queries in `orders/show.blade.php` (L2237, L2355) are removed; the controller/model hands the view a prepared list.
* **Done when:** Order #6 / OP10 shows exactly **2** rows (test-raw-01 **25**, test-raw-07 **275**), not 13.

### PX-02 — Quantity formula (matches PO-08, per operation)
$$\text{Required}_{op} = \frac{\text{op.target\_produced\_qty}}{\text{source BOM base qty}} \times \text{allocation qty} \times \left(1+\frac{\text{scrap \%}}{100}\right)$$
* Level-1 operations: target = order quantity. Level-2+ (sub-assembly) operations: target = *to-produce* quantity of that sub-assembly (PO-12) — **not** the order quantity.
* The number shown must equal what the reservation / requisition slip plans for that operation's share (one check in the test: Σ over operations per product = `ProductionOrderReservation.quantity_planned`).

### PX-03 — What each row shows
| Column | Meaning |
| :--- | :--- |
| Material (+ SKU) | product of the resolved row |
| Required (this op) | PX-02, with the product's UoM |
| Order total reserved / issued / pending | from the **single pooled reservation per product** (PO-12) — labelled "order total", not "this operation" |
| Badge `Shared with OP20, OP40` | when the same product is needed by another operation of this order |
| Badge `Alternative selected` | when the chosen row is an alternative material (PO-06/07) |
* One row per product per operation (if two slots in one operation hold the same product, the quantities are added and the row says "2 slots").

### PX-04 — Sub-assembly (child-BOM) rows
* A resolved row with `child_bom_id` is **not a raw material**. It is shown in its own group **"Sub-assemblies"**: `test-semi-01 — needs 22 · from stock X · to produce Y · produced by OP10 (L2)` with a link to that operation (PO-08: child BOM exploded only for the *to-produce* part).
* Sub-assembly rows are excluded from raw-material issue totals and from the "Material Inputs" count.

**Files likely touched (when implemented):** `orders/show.blade.php` L2229–2259 and L2351–2420; `ProductionOrderOperation::getExpectedComponents()` (add quantity + shared-with info, or a small presenter); controller `show()` to pre-load; test `ProductionOrderCreateRevampTest` (extend).

---

## 3. Readiness

### PX-05 — Two quantities instead of one
* **Ready qty** = the most this operation can produce *now* from (a) material **already issued to the order** (floor stock, per PX-06 allocation) and (b) the predecessor's good output (dependency). This is the number execution is limited by (PX-09).
* **Issuable** = what the Store could still issue for this operation, using **PO-04**: `SUM(max(0, quantity − reserved_qty))` over the active standard warehouses of the order's branch, excluding stock reserved for **other** orders (this order's own reservation counts as available to it). Shown as information ("Store can cover 2.5 more — issue it"), never as ready quantity.
* Replaces today's tenant-wide `SUM(quantity)` over all warehouses (R-03; `ProductionReadinessService` L419–422).

### PX-06 — Shared materials are allocated, not repeated
* When one product is used by several operations (or levels), the pooled issued/reservable quantity is handed out **in operation sequence order**; each operation sees what is left (R-04). No operation is compared with the full stock.

### PX-07 — Machine & downtime
* Downtime statuses are `open` / `closed` (model constants). An **open** downtime blocks the machine from its `start_time` until closed; a **closed or planned** downtime overlapping the operation's scheduled window is a **warning**.
* Scheduling treats an open downtime as running until closed (not "until now") — B-16.
* "Alternate machines": removed from readiness (RT-06/07: one resource per operation; alternatives are *operations* chosen on the order, RT-11). The alternate-machines table is no longer read (R-08).
* Machine `under_maintenance` / `inactive` stay blockers.

### PX-08 — Operation and order states
| Operation state | When |
| :--- | :--- |
| **READY** | all dimensions OK and Ready qty ≥ target |
| **PARTIALLY READY** | Ready qty > 0 but < target, or only warnings |
| **WAITING** | the only reason is an incomplete predecessor (workflow dependency — not a fault) |
| **BLOCKED** | an own blocker: Ready qty = 0 from material, machine/batch/quality blocker, subcontract not returned |
* Order badge: all READY → READY · any operation with an actionable blocker → BLOCKED · else if ≥ 1 ready or partially ready → PARTIALLY READY · else (only waiting) → **WAITING**. (Fixes the identical `else` branches, R-02.)
* Summary cards show **all four counts** (Ready / Partial / Waiting / **Blocked**); "Sequential Waiting" is fed by WAITING (R-01).
* Colours: READY green, PARTIALLY READY amber (not red, R-13), WAITING grey/blue, BLOCKED red.

### PX-09 — What readiness controls
* **Execution (enforced):** logging output or completing an operation is rejected when it would exceed **Ready qty** (issued material + predecessor output). Message names the limiting material or predecessor. Wire the existing `claimBatchToExecute` check into start / log / complete, or replace it — it has no callers today (R-05, `MesExecutionService` L1139).
* **Release (warning, not block):** `release()` lists operations that are BLOCKED and asks for confirmation; it does not hide them (R-06). Material may legitimately be issued after release (partial-issue slips exist).
* A manager override (permission name to be chosen at implementation) can bypass the cap; every override is logged with user and reason.

### PX-10 — When readiness is computed
* Recomputed and stored on **events**: material issue / return, operation start / complete / log, machine status change, downtime open / close, schedule release, predecessor completion; plus the existing scheduler tick. Opening the order page **only reads** (R-11).
* **Draft** orders carry no readiness-driven operation status (no `ready` / `waiting` before release).

### PX-11 — Single source for the order
* Quality, batch, queue-threshold ("overlap") and resource settings come from the **order-operation snapshot** (PO-03 immutability). The live routing is never consulted for an existing order (R-09, R-12). Quality: readiness and MES must agree.

### PX-12 — Display
* Ready / Target shown by G1 (e.g. `1.8182 / 5`, not `1.82`) (R-10).
* Resource and schedule caveats (no machine, not scheduled) are **chips next to** the readiness status; they do not change it unless the routing requires that resource.

**Files likely touched:** `ProductionReadinessService.php` (L21, L225, L247–255, L419–422, L797–840, L963), `ProductionOrderService::reconcileOperationReadiness` + `release` (L525–560), `ProductionOrderController::show` (L211–216), `MesExecutionService` (L1139 + start / log / complete), `orders/show.blade.php` L717–897, `SchedulingService` L934–946; new events/listeners per module rules.

---

## 4. Operation Tracking tab (per-operation rows)

### PX-13 — Target and ratio
* **Target** = `production_order_operations.target_produced_qty` (snapshot). The fallback `order qty × ratio` is removed.
* **"per FG" ratio** = `target ÷ order quantity`, 4-decimal rule. The lookup in the legacy `ProductionBomItem` table (`show.blade.php` L2029–2036) is removed — it ignores base qty, scrap and multi-level.

### PX-14 — Quantity definitions (one place)
| Field | Rule |
| :--- | :--- |
| Good produced | `quantity_produced` — sum of good output from all progress logs (partial + final) |
| Rejected / Scrapped / Rework | as logged; shown only when > 0 |
| **Pending** | `max(0, target − good)` — scrapped / rejected units are not "done", they must be replaced |
| Progress % | `good ÷ target`, capped for the bar only; the number itself may exceed 100 % and is flagged "over-production" |

### PX-15 — Number and wording rules on this tab
* G1: the progress cell today mixes 1-decimal done and 0-decimal target (`L2164`, `L2166`); every figure on the tab uses the one rule (whole, or up to 4 decimals).
* UoM = the item's product UoM; the hard-coded fallback `'Pcs'` / "units" is removed (G4 / B-15) — missing UoM shows nothing rather than a wrong unit.

### PX-16 — Status set and meaning
| Status | Meaning |
| :--- | :--- |
| waiting | a predecessor is not complete (PX-08 WAITING) |
| ready | no blocker; may start (needs release) |
| running | started |
| qc_hold / rework | as quality flows set them |
| completed | target reached or closed by user |
* "Not Scheduled" and "Machine not assigned" are **chips**. "Machine not assigned" appears only when the snapshot says the operation needs a machine (an operation on a manual batch or an external vendor never shows it). A **ready** operation that is not scheduled is allowed, but flagged so planning sees it.

### PX-17 — Shortcuts
* "Open MES Console" opens **this operation** (not the generic dashboard); hidden unless the order is released.
* "Issue material" appears when the operation is short on issued material (links to the order's issue action); "Manage Subcontract" stays for external operations.

### PX-18 — Order-level produced quantity
* `production_orders.quantity_produced` = good output of the final operation(s) that produce the FG, **cumulative over all logs** (today it keeps only the last "complete" quantity: 3 vs 5, B-06). WIP and the operation must show the same total at any time.

**Files likely touched:** `orders/show.blade.php` L2024–2220, `ProductionOrderOperation`, `MesExecutionService::completeOperation` / `logPartialProgress`, `ProductionExecutionService::logProgress`; lang keys under `production.*`.

---

## 5. Order lifecycle rules linked to the above (decisions pending — see section 6)

### PX-19 — FG receipt, complete, close (B-05, B-06)
* Today the order auto-completes with the last operation, so *Receive FG* / *Complete* answer **403**; FG only enters through the WIP convert route; and *close* needs only "completed".
* Proposed: `close` requires FG received (or an explicit, reasoned "no FG"); *Receive FG* works while the order is `completed` and FG is still pending. Options in **D-05**.

### PX-20 — Cancel (B-04)
* Proposed: cancelling an order with issued material returns the unconsumed part to the issuing warehouse in the same action, closes the slip and WIP, and records `cancelled_by` = real user (G7). Options in **D-06**.

### PX-21 — Plan → order (B-01, B-02, B-03)
* Proposed: `createFromPlan` builds reservations through the same engine as `createDirect` (PO-06/07/08/12) and does not add the plan's already-multi-level rows a second time (direct 915 / 9,570 vs plan 1,840 / 19,195). MRP counts primary operations only and pools shared materials (BM-04/07). Plan *Release* must not block order creation. Options in **D-07**.

### PX-22 — Sub-assembly operations placed ahead of the operation that needs them *(raised by user, details pending — NOT yet a proposed rule)*

**User's requirement (own words, 2026-11-23):** add the operations of the child BOM and give them priority when the child-BOM (semi-finished) material is not available, because it has to be made first; place them according to the need of the semi-material. Example: *OP30 needs semi-4; semi-4 is not available, so its raw material is requested; before OP30 can start, the operations of semi-4 are placed ahead of it.* The user will explain the details later.

**What the code does today (read-only check, 2026-11-23):**

| Point | Current behaviour | Where |
| :--- | :--- | :--- |
| When child operations exist | A child BOM is exploded **only for the part not covered by stock** (`to_produce > 0`); its operations are snapshotted with `bom_level ≥ 2`, `is_intermediate = true`, `target_produced_qty = to_produce`, primary operations only (PO-08, PO-10, PO-12). Fully covered by stock → no child operations. | `snapshotPlannedOperations` L1695–1748 |
| Link to the consuming operation | **Already exists:** a `cross_assembly` finish-to-start dependency from the child's **last** operation to the parent operation that consumes the semi-product (found through the parent's selected material rows). | `ProductionOrderService` L1778–1801 |
| Fallback when no match | If no parent operation's selected materials contain the sub-assembly, the link goes to the parent's **first** operation (by sequence) — possibly the wrong one. | L1786–1793 |
| Numbering | Child operations keep **their own routing's sequence numbers** (10, 20 …), so a child OP10 and a parent OP10 can both exist in one order; there is no single order-wide execution sequence. | L1715 |
| Display order | Order → operations are listed by `bom_level` **descending**, then sequence (children first, grouped by level) — not placed next to the operation that needs them. | `ProductionOrder::operations()` L224–228 |
| Scheduling | Uses the dependency chain (predecessor finish before successor start). "Priority" for child operations is not a concept in the scheduler today. | `SchedulingService` L315, L508, L1951, L2032–2058 |
| Materials | The child's raw materials are part of the pooled reservation / requisition slip (PO-12) — already requested together with the parent's. | PO-12 |
| Evidence in real data | **All 6 orders in the real database have only level-1 operations** — the child-operation path has run only in automated tests, never on real data. To be proven in a scratch run before any rule is locked. | real DB |

**Points to discuss with the user (nothing assumed):**
1. What "priority" means: only ordering (child finishes before the consumer starts), or also precedence over other orders' operations on the same machines?
2. Numbering / display: one order-wide sequence with each child block shown directly before its consuming operation (e.g. `OP10, OP20, [semi-4: OP10, OP20], OP30`), and how it is labelled.
3. Partly available sub-assembly: stock covers 3 of 10 → child operations for 7 (as today)? Does the consumer start on the first 3 while the rest is made?
4. Stock arriving after the order is created: keep the snapshot (PO-03) or drop child operations?
5. Which operation counts as the consumer when several parent operations use the same semi-product, and what replaces the "first operation" fallback.
6. Multi-level (semi inside semi): the same rule recursively.
7. Readiness view: consumer shows WAITING (PX-08) with the reason "waiting for semi-4 (OP20 of sub-assembly)", and Ready qty follows the child's good output (PX-05).
8. Scheduling: do child operations get a deadline (finish before the consumer's planned start) and is the planned start of the consumer pushed back when they cannot fit?

**Status:** captured only. No rule number is final until the user explains it (see **D-11**).

---

## 6. Decisions needed from you (recommendation first)

| ID | Question | Options | Recommended |
| :--- | :--- | :--- | :--- |
| **D-01** | Tracking drawer: show the order-pooled reserved/issued per material (PX-03)? | A) yes, labelled "order total" · B) only required qty | **A** |
| **D-02** | Readiness material basis (PX-05) | A) issued only for Ready qty, stock as "Issuable" · B) issued + available stock (as today, corrected to PO-04) | **A** — floor can only use what was issued |
| **D-03** | Execution limit (PX-09) | A) hard cap at Ready qty with manager override · B) warning only · C) advisory as today | **A** |
| **D-04** | Release when an operation is blocked (PX-09) | A) warn + confirm · B) hard block · C) nothing | **A** |
| **D-05** | FG / close flow (PX-19) | A) keep auto-complete, make *Receive FG* valid after it, require FG before close · B) keep order `in_progress` until FG received | **A** |
| **D-06** | Cancel with issued material (PX-20) | A) auto-return · B) block cancel until returned manually | **A** |
| **D-07** | Plan → order (PX-21) | A) same engine as direct order · B) keep separate, patch the double count only | **A** |
| **D-08** | Stock definition for **every** module check (readiness, MRP, release) | PO-04 everywhere / only on PO create | **PO-04 everywhere** |
| **D-09** | Readiness recomputation (PX-10) | A) events + scheduler · B) keep recompute on page view but not for drafts | **A** |
| **D-10** | Anything else you noticed ("and even more")? | *free text* | — |
| **D-11** | PX-22 — sub-assembly operations placed before the operation that needs them (details of the 8 points in section 5) | *user will explain* | — |

---

## 7. Suggested implementation order (after decisions; nothing started)

1. **PX-01 / 02 / 04** (display fix — views + resolver only, no data change; smallest, removes the screenshot problem).
2. **PX-13 / 14 / 15 / 16** (tracking tab quantities and wording).
3. **PX-05 – 08, 11, 12** (readiness engine: stock, shared pool, downtime, states, snapshot source).
4. **PX-09 / 10** (enforcement + event-driven recompute; needs D-03 / D-04 / D-09).
5. **PX-18 – 21** (order lifecycle; needs D-05 / D-06 / D-07).

Each step: Applied only after my tests pass; **Verified** only by you.

## 8. Test sketch (for the implementation tasks)

* Reusable routing shared by 3 BOMs → drawer shows only the order's BOM rows; selected-alternative material shown; sub-assembly row in its own group.
* Σ per-operation required = reservation `quantity_planned` per product, for a 3-level order with a shared material.
* Readiness: open downtime → BLOCKED; closed overlapping downtime → warning; two operations sharing one material with half the stock → first READY, second PARTIALLY READY; predecessor incomplete → WAITING (counted, not red).
* Execution: logging more than Ready qty → rejected with the limiting material named; with override → accepted and logged.
* Page view writes nothing (no `updated_at` change on operations).
* Tracking row for an order with partial logs 3 + 2 → good 5, order `quantity_produced` 5, pending 0.

---

## 9. Implementation status (2026-11-23) — **Applied (code working properly). Nothing is Verified: only the user verifies.**

| Rule | Status | Where / note |
| :--- | :--- | :--- |
| PX-01 one resolver | Applied | `OperationMaterialService::resolve/describe` (uses `getExpectedComponents()`); both order-page views no longer query `RoutingOperationMaterial`. Test: order with a second BOM on the same routing operation shows only its own rows. |
| PX-02 quantity formula | Applied | `OperationMaterialService::requiredQty` = target ÷ BOM base × gross qty. |
| PX-03 row content | Applied | required, order planned / issued / pending, "Shared with OPxx", alternative badge. |
| PX-04 sub-assemblies | Applied | own group: required, from stock, to produce, made by OPxx; the stock part is still an issuable material. |
| PX-05 ready qty from issued material | Applied | `ProductionReadinessService::evaluateMaterialDimensionPooled`; stock (same PO-04 function as the create screen) is only "issuable". **Orders with no BOM allocation rows keep the old stock-based check.** |
| PX-06 shared material pool | Applied | `OperationMaterialService::poolAllocation` (deepest level first, then sequence). |
| PX-07 downtime / alternates | Applied | `open`/`closed` statuses; open downtime blocks; alternate machines removed from readiness, planner, leveling, scenarios, suggestions, exceptions. Downtime path not covered by a unit test in the readiness file (covered in scheduling tests). |
| PX-08 states | Applied (partly) — gap found 2026-11-25 | Operation state, order badge and cards use WAITING; amber for partial. **Gap:** the "Operation / WIP" column of the six-dimension table still shows red BLOCKED for a pure predecessor wait (G-R1 below). |
| PX-09 enforcement | Applied | cap in `logProgress` and MES `completeOperation`; override permission `production.mes.override_readiness` (**re-run `RbacSeeder` on live tenants to create it**); release asks for "Release anyway". |
| PX-10 events | Applied | page view no longer writes; refresh on release, issue/return, machine status, downtime, progress + `production:reconcile-readiness` every 5 min (**needs the scheduler running**). Draft orders carry no readiness status. |
| PX-11 snapshot source | Applied (partly) | readiness uses the same quality expression as MES; new orders copy `quality_required` into the snapshot; **old orders still fall back to the routing**. |
| PX-12 display | Applied (partly) — gap found 2026-11-25 | `qty_format` (whole number, otherwise 4 decimals). **Gap:** "Store can still issue N of the missing material" adds different materials together and names none (G-R2 below). |
| PX-13..PX-17 tracking tab | Applied | snapshot target, ratio = target ÷ order qty, units from the product, resource chips (machine / manual batch name), shortcuts open the operation / issue material. |
| PX-18 produced quantity | Applied | `syncProducedQuantity` + MES complete use the cumulative good output of the final FG operation. |
| PX-19 FG / close | Applied | `receiveFg` allowed on a completed order while FG is pending; `close` needs FG received or a reason (event logged). |
| PX-20 cancel | Applied | unconsumed issued material returned (estimate = output × per-unit ratio), slip + WIP closed. **WIP cost write-off is not done.** |
| PX-21 plan → order | Applied | same engine as a direct order; MRP skips alternative-operation materials, pools shared materials, divides by BOM base qty, uses PO-04 stock; a released plan can still create its order. Verified on the MySQL copy: plan order = direct order (10 products, 12 operations, identical quantities). |
| **PX-22 sub-assembly sequencing** | **NOT applied** | waiting for the user's details (D-10 / D-11). |

**Known leftovers (not changed):** `receive-fg` accepts a quantity above what was produced; other screens still format with 2 decimals (B-14); `efficiency_percentage` reads (dropped column) in scheduling; `SchedulingService::findNextAvailableMachine` is unused legacy; the operator execution screen header does not show the resource.

**Gaps found 2026-11-25 (user's screenshot of the Order #6 Readiness tab) — recorded only, no code changed yet; waiting for the user's changes.** Order #6 (ORD-2026-000005) is still `draft`, qty 5, 7 reservations, `issued = 0` for every material. The page was replayed read-only on the real database and gives exactly the screenshot.

| # | What the screen shows | Rule it breaks | Cause in code |
| :--- | :--- | :--- | :--- |
| G-R1 | OP20 / OP30 / OP40 show red **BLOCKED** in "Operation / WIP"; the only reason in that column is "predecessor operation is incomplete" | PX-08: a pure predecessor wait is **WAITING** (grey/blue), not a fault; PX-12 colours | `ProductionReadinessService::evaluateDependencyDimension` (L911) returns `BLOCKED` for workflow waits too, so the page's WAITING badge (`orders/show.blade.php` L874) never fires. A fix must also touch `evaluateOperationReadiness` (L107, hard-block test), `ProductionOrderService::reconcileOperationReadiness` (L2086) and `CrossAssemblyQueueThresholdTest` (L388, L409) |
| G-R2 | OP10: "Store can still issue **125** of the missing material" | PX-03 / PX-05: figures are per material; 125 = 25 (test-raw-01) + 100 (test-raw-07), and no material is named | `evaluateMaterialDimensionPooled` sums `issuable` over all materials into one `issuable_qty`; the view prints that one sum (`show.blade.php` L860–861) |
| G-R3 | The stored operation status of this draft order is `ready` (OP10) / `waiting` (OP20–40) | PX-10: draft orders carry no readiness-driven status | written by the old code on 2026-11-21; `reconcileOperationReadiness` now skips drafts but does not clear old values |
| G-R4 | Every column is red on a draft order with nothing issued yet | not a rule break — for the user's decision | PX-05 counts only issued material, and a draft order has issued nothing; should it show this red table or a neutral "not released yet" state? |

*2026-11-25 later: G-R1 and G-R2 are closed by UR-02 and UR-01 (partially reviewed by the user). G-R3 and G-R4 are still open.*

## 10. Scheduling — rules applied on top of the above (2026-11-23, user: "check scheduling … include manual batches … proper screen updates")

| ID | Rule | Status |
| :--- | :--- | :--- |
| **SC-01** | An operation has ONE resource — a machine **or** a manual batch of a section (RT-06 / RT-07). There are no alternate machines (RT-11): planner moves, ripple, capacity leveling, what-if scenarios, load-balance suggestions and planning exceptions only move work **in time on the same resource**. A manual-batch operation can never get a machine. | Applied |
| **SC-02** | Capacity = sum over the work center's active resources of the minutes **that resource** works: a machine → its section's shifts, a manual batch → its own shift (an evening crew is not credited with the day shift). Capacity screens list machines **and** manual batches. | Applied |
| **SC-03** | Downtime statuses are `open` / `closed`. An **open downtime with no end** is not "until now": the slot gets error warning `MACHINE_DOWNTIME_OPEN` and the schedule cannot be released until it is closed (B-16, R-07). | Applied |
| **SC-04** | Release check judges operations with the **readiness engine** (material not issued / short, quality, batch, subcontract) → **warnings the planner confirms** (D-04), summary line; stock-only check removed. Machine problems stay errors. | Applied |
| **SC-05** | A planner move / ripple computes finish times in the **resource's own shifts** and validates that the manual batch (and its section) is active. | Applied |
| **SC-06** | Leveling and scenarios detect collisions per resource (machine or batch); the scenario sandbox keeps the batch (**new migration `2026_11_23_000001` — run `php artisan migrate`**, nullable columns only). | Applied |
| **SC-07** | Screens: calendar (one row per manual batch), dispatch board (batch lanes, batch filter, no machine reassign by drag, open downtimes drawn), capacity planner (batch load rows, resource column, no machine select for batch operations), work-center view, schedule detail (resource, readiness reason, batches in capacity), MES dashboard / my operations (batch name + search), order Operations tab (batch name). | Applied |
| **SC-08** | Shop-floor start: a batch operation takes no machine, its batch must be active, and one batch runs one operation at a time. | Applied |
| **SC-09** | Planning exceptions: machine down → "reschedule" (no "assign alternate machine"); inactive manual batch → critical exception. | Applied |

Tests: `ProductionExecutionRulesTest` (11), `SchedulingResourcesTest` (12), plus updated expectations in `ProductionReadinessTest` (WAITING, no alternate machine), `SectionArchitectureSchedulingTest` (material issued before start), `ScheduleDispatchAndPreReleaseTest` (downtime status `open`), `ProductionPlanningExceptionTest` (recommended action), `CapacityLevelingTest` (no alternate reassignment).

**Test result (SQLite suite, same 1,256 production tests run on the ORIGINAL code and on this code, plus the 23 new ones):** original = 265 failed / 991 passed; now = 264 failed / 1,015 passed. **No test that passed on the original code fails now.** The remaining 264 failures are the same ones as before (old fixtures — machines without sections / shifts, dropped columns — e.g. `ScheduleScenarioTest`, `ScheduleDispatchAndPreReleaseTest` cases); they were not touched.
**MySQL copy of the real data (throw-away database, already dropped):** every scheduling / capacity / calendar / dispatch / MES screen returned 200 with real manual-batch operations; the dispatch data lists the batch lanes; a ripple move of a batch operation finished inside the batch's own shift; the what-if scenario kept its batch. The real database was never written to.

## 11. Readiness — the user's column rules (2026-11-25, message M-14 in `docs/production_readiness_user_messages.md`)

**Status (2026-11-25, after M-17):** the user confirmed my reading of UR-01 … UR-07 and said: implement everything asked so far, **and run no tests** ("I will check each and everything"). All of UR-01 … UR-07 are now written in code — **Applied (code written). Not tested by me (as asked) and not Verified: the user checks each one.** **Update (M-18): the user reviewed UR-01 … UR-07 on screen — "They are looking fine. We will review them in detail later on" — so they are marked partially reviewed, not Verified.** Files: `ProductionReadinessService.php`, `OperationMaterialService.php` (`operationTree`), `ProductionOrderService.php` (`reconcileOperationReadiness`), `ProductionOrderController.php` (`show`), `MesExecutionService.php` + `ProductionExecutionService.php` (the "parent waits" gate), `orders/show.blade.php` + new partials `operation-tracking-row` and `readiness-row`, `lang/en/production.php`; test files updated for the changed rules (`ProductionExecutionRulesTest`, `ProductionReadinessTest`, `SchedulingResourcesTest`, `CrossAssemblyQueueThresholdTest`) but not run. Scheduling of the sub-assembly operations is the next step (PX-22) — not touched.

These rules **replace** my earlier PX rules wherever they differ (PX-05 store-stock line, PX-08 states, PX-10 "opening the page only reads") and they close gaps **G-R1** and **G-R2** above. Columns the user has not spoken about yet are **not touched**.

| ID | Column | Status |
| :--- | :--- | :--- |
| UR-01 | Material (direct materials only) | Applied — **partially reviewed by the user** ("looking fine", detailed review later); not Verified |
| UR-02 | Operation Flow (new heading, replaces "Operation / WIP") | Applied — **partially reviewed by the user** ("looking fine", detailed review later); not Verified |
| UR-03 | Resources (new heading, replaces "Machine") | Applied — **partially reviewed by the user** ("looking fine", detailed review later); not Verified |
| UR-04 | Subcontract | Applied — **partially reviewed by the user** ("looking fine", detailed review later); not Verified |
| UR-05 | When the checks run | Applied — **partially reviewed by the user** ("looking fine", detailed review later); not Verified |
| UR-06 | Everything else | Not touched — "I will tell you Rest of the rules later" |
| UR-07 | Sub-assembly operations in an arrow drawer; the parent always waits until they are completed | Applied — **partially reviewed by the user** ("looking fine", detailed review later); not Verified |

### UR-01 — Material column (direct materials only)
**Words (M-14):** "First, we will check the target. If target is to produce 5 QT... And then we check BOM to determine how many materials are required to produce the 1 QT... We check that against the issued quantity. If that much is issued, we will say partially issued. If that much is not issued, we will say blocked, cannot produce, minimum quantity not issued accordingly. And if all are issued, we will say ready."

**My reading**
1. Scope: only **direct materials** — BOM rows that are not child-BOM (semi-finished) rows. Child-BOM rows stay out of this verdict until the user gives their rules (PX-22); they stay visible in the drawer's "Sub-assemblies" group as today.
2. **Target** = the operation's target quantity (example: 5).
3. **Need for 1 unit** = for each direct material, the BOM quantity needed for 1 unit of this operation's output (BOM base quantity and scrap included — the PX-02 formula divided by the target).
4. It is compared with what is **issued to the order** (the pooled issue, handed out in operation order as today). Store stock is not part of the verdict.
5. Result:

| Situation | Column says |
| :--- | :--- |
| every material is issued for the whole target | **Ready** |
| every material is issued for at least 1 unit, but at least one is short of the whole target | **Partially issued** — with the number of units that can be made now |
| at least one material is issued for less than 1 unit | **Blocked — cannot produce: minimum quantity not issued** — names the material: needs X for 1 unit, issued Y |

6. An operation with no direct materials shows "No direct materials" and counts as fine.
7. Consequence: the output cap of PX-09 uses the same number, so with less than the minimum issued nothing can be logged ("cannot produce").

**Example (Order #6, OP10, target 5):** 1 unit needs 5 × test-raw-01 and 55 × test-raw-07. Issued 3 and 100 → Blocked (test-raw-01 covers only 0.6 unit). Issued 12 and 150 → Partially issued (can make 2.4). Issued 25 and 275 → Ready.

**My assumptions — the user confirms or corrects:** A1 "minimum quantity" = the amount for 1 unit (if the target is below 1, the target itself). A2 the "Store can still issue …" line is removed from this column (it added different materials together, G-R2); if wanted back it returns per material. A3 the "Ready" number under the operation name = units the issued material covers (0 when blocked).

### UR-02 — Operation Flow column (new heading; replaces "Operation / WIP")
**Words (M-14):** "Now for operation work in progress, we will need a better heading for that column, but that will simply check that if we are checking for operation 20, then if it is awaiting operation 10, or is it awaiting operation 20 or any other operation, or is it ready to be performed now as it is independent, or is it in progress or anything. We will check all of these…"

**My reading:** the column says where the operation stands in the sequence, in words — not as ready / blocked:

| Situation | Column says |
| :--- | :--- |
| a previous operation is not finished | **Waiting for Op 10** (names every operation it waits for) |
| the previous operation has handed over part of the quantity (overlap / transfer batch) | **Can start: 3 of 5 received from Op 10** |
| first operation, or independent / parallel, or the previous operation is finished | **Ready to start** |
| running | **In progress** |
| paused | **Paused** |
| finished | **Completed** |
| anything else it can be (QC hold, rework, skipped, cancelled) | shown by its own name |

A wait is never shown as red "Blocked" (this closes G-R1). **Heading proposal: "Operation Flow"** — the user chooses the final word.

### UR-03 — Resources column (new heading; replaces "Machine")
**Words (M-14):** "We will check the heading of next column to resources. It won't be just tracking machines, it will be tracking each and every resources that it is required, including machines and manual batches. So we will see … if they are ready to produce or not, if any other operation is already scheduled on it. And for machines, any other checks, maintenance and status checks, others that are already in place, we will continue those."

**My reading**
1. The column lists **every resource the operation needs**, by name — a machine or a manual batch.
2. For each one: **ready to produce or not**, and whether **another operation is already scheduled on it**.
3. Checks that exist today stay: machine inactive / under maintenance / breakdown / open downtime (blocks); a downtime overlapping the scheduled slot (warning); no active machine in the work center; manual batch active + its section active + its shift active.
4. New: other operations (any order) booked on the same machine or batch are named — order, operation and time slot.

**My assumptions:** B1 an operation of another order that is **running now** on the resource → "Busy now" (not ready now). B2 another operation whose scheduled slot **overlaps** this operation's slot → warning "Booked: …" (information, not a block). B3 an external operation, or one without a work center, needs no resource → "Not needed".

### UR-04 — Subcontract column
**Words (M-14):** "Subcontractor, it will say uh, subcontracted, because all operations won't be having subcontracting. So for some we will say subcontracted, we will say not subcontracted. And if it will be subcontracted, I will tell you the tests and check later on. For now, just keep this to subcontracted, not subcontracted."

**My reading:** only two values — **Subcontracted** (the operation is external) or **Not subcontracted**. No ready / blocked verdict for now; the vendor-return check stops affecting readiness until the user gives the tests.

### UR-05 — When the checks run
**Words (M-14):** "…these screen will be checked each and every time the operation order is being loaded. It will get updated every time a production order is loaded, that screen is loaded. So all these checks will be performed every time that screen loads."

**My reading:** every time the production order screen loads, **all** the checks above run fresh — nothing is taken from an older saved value — and the saved operation status is refreshed at that moment, so the Readiness tab and the Operations tab agree. Events (issue, start, log …) and the 5-minute refresh stay as a safety net. This **replaces PX-10** ("opening the page only reads").

**My assumptions:** C1 the page load also *saves* the refreshed status (only when it changed). C2 draft orders: the columns are shown, but no status is saved before release (G-R3 stays open).

### UR-06 — Not touched yet
"I will tell you Rest of the rules later." Quality and Batch / Lot columns; the operation's overall state, the order badge and the four cards; release and execution gates (PX-09); child-BOM items (PX-22); how a draft order is displayed (G-R3, G-R4). They keep working as today. Code that reads the changed columns (overall state, saved status, output cap, tests) is adjusted only so it keeps giving the same answers.

**How I would implement it (after the user's OK):** `ProductionReadinessService` (material, flow, resources and subcontract evaluators; flow and subcontract stop producing blockers), `OperationMaterialService` (need-per-unit helper, direct-only filter), `ProductionOrderController::show` + `ProductionOrderService::reconcileOperationReadiness` (refresh on load), `orders/show.blade.php` (the table and its headings), the `production` language file (headings and labels), and the tests that expect the old words (`ProductionReadinessTest`, `ProductionExecutionRulesTest`, `CrossAssemblyQueueThresholdTest`) plus new tests, one per rule.

### UR-07 — Sub-assembly operations inside a drawer of the operation that needs them (M-15, M-17) — Applied — **partially reviewed by the user** ("looking fine", detailed review later); not Verified
**Words (M-15):** "…operation 20 needs a material that has a child bomb, and that material is in shortage in store. So at the time of production order only, we have decided at the time of creation of draft that if a material is short, we will send the material raw material request for that short material directly. In short, we are directly creating that material in this same PO…. So the operations for creation of that material will be placed inside a drawer inside operation 20… It's the same arrow drawer that we use in the bomb and other places… those operations are not always gonna be shown on the screen. Those are only gonna be shown when the drawer is active by clicking the arrow."

**My reading**
1. Case: operation 20 needs a material that has a child BOM and the store is short of it. When the draft is created, the short part is made inside the same production order and its raw materials are requested right then. This is how the order already works today (PO-12): the child-BOM operations are real operations of the same order.
2. On screen those child operations are **not** listed as separate rows. They sit inside a **drawer of operation 20** — the same arrow drawer as in the BOM screens. Closed by default; they show only when the arrow is clicked.
3. Inside the drawer: the operations that make that material, each with its own tracking (the same columns and rules as any operation). If one of them needs a child-BOM material that is short, it gets its own drawer — the same again, as deep as the BOM goes.
4. No shortage → no child operations → no arrow on operation 20.

**Confirmed by the user (M-17):** "Yes, your idea is correct." and "the parent operation will always be in waiting or cannot be executed until the child operations are completed". Scheduling them accordingly is a later step.

**How it is implemented**
1. `OperationMaterialService::operationTree()` builds the tree: for every operation, the sub-assembly rows whose making operations are in this order; those making operations are not top-level rows any more. A maker needed by two operations is listed in the drawer of the first one (the other drawer points to it); operations that belong to nobody stay visible as normal rows.
2. **Component Plan (Operation Tracking)** — partial `operation-tracking-row`: the arrow drawer of an operation now also holds a block "Sub-assembly operations" with, per sub-assembly, the needed / from stock / to produce quantities, "x of y operations done" and a table of its operations (each with its own arrow drawer, again recursive). A closed row shows a small badge "Makes semi-4 · 1 of 3 operations done".
3. **Readiness table** — partial `readiness-row`: an operation with a sub-assembly gets an arrow in its Seq cell; the drawer shows the readiness rows (all columns) of its making operations.
4. **The rule:** `ProductionReadinessService::pendingSubAssemblies()` / `assertSubAssembliesComplete()`. Until ALL making operations are completed (or skipped / cancelled): the operation's Operation Flow says "Waiting for sub-assembly …", its ready quantity is 0, its saved status is `waiting`, and start (`MesExecutionService::startOperation`), logging (`ProductionExecutionService::logProgress`, which the MES partial-progress also uses) and completing (`MesExecutionService::completeOperation`) are refused with a clear message. There is no manager override. Stock of the sub-assembly does not change this, and the early start on a transfer batch / queue threshold no longer applies to sub-assemblies.
5. Not changed: the flat "Operations" tab (execution list with the Log buttons) and the hierarchical BOM tree still list every operation; scheduling / dispatch board / MES lists are unchanged.
**My assumptions — the user confirms or corrects:** D1 the child operations stay real operations (scheduling, shop floor and MES unchanged) — only the order screen nests them. D2 a closed row shows a short summary (for example "Makes semi-4 · 1 of 3 operations done"). D3 operation 20's Operation Flow says it waits for the sub-assembly; where the child operations are placed and their priority (PX-22) the user will explain later. D4 screens: Operation Tracking and the Readiness table first (the user says if the tree view or other screens too).


## 12. Round 2 (M-19, 2026-11-25) — sub-operations above the parent, and every gap of the Operations-tab audit fixed

**Status: Applied (code written). Not tested by me (the user's rule: no tests) and not Verified — the user checks each item in the UI.** *M-22 (2026-11-25): the user looked at them in the UI — "I reviewed them, they look fine…." (no bug reported). Recorded as reviewed; the word Verified is the user's to write.* Source: M-19 in `docs/production_readiness_user_messages.md`; the gap IDs are those of `docs/production_operations_tab_drawer_audit_2026-11-25.md`.

### UR-08 — Sub-operations are shown ABOVE the operation that needs them (replaces "below" in UR-07)
*Words (M-19):* "The child operations or sub operations will be shown at the top of the parent operations because they are gonna be executed first … sub operations are gonna be shown at the top in the sequence, then the parent operations will be shown … Even do these things recursively."
* The arrow drawer of a parent now opens **above** the parent row (arrow up = open, arrow down = close), closed by default, and opens by itself while an operation inside is running. The closed row shows "n operations · x done · y ready · z running".
* **Step numbers follow the execution order**: the deepest sub-operations are 1, 2, 3 …, then the operations that need them, the finished-good operations last. (Order #7 after the fix: the semi-01 operations first, then semifinal-01, then the order's own operations.)
* Recursive: a sub-operation that needs a sub-assembly again has its own drawer above it, as deep as the BOMs go.
* A sub-assembly needed by two operations is listed above the one that **executes first (the deepest)**; the other one points to it.

### UR-09 — Scheduler (M-19: "the findings in the scheduler, those things can be set good too")
* Every operation that makes a sub-assembly is a predecessor of the operation that needs it — **all of them, not only the last one** (new link type `sub_assembly`, created with the order and again whenever a schedule is generated: `SubAssemblyDependencyService`). The WIP / consumption code, which only reads `cross_assembly`, is unaffected.
* Such a link is **strictly finish-to-start**: no transfer-batch / overlap early start in the forward and backward scheduling, in the planner's move checks (isolated / ripple), in scenarios, in the calendar conflict check and in the pre-release validation.
* Sub-operations are scheduled before the parent (dependency order); "partial reschedule" now also works in dependency order (it used plain sequence order, which breaks across BOM levels).

### Gap by gap — what was done and where to check it

| ID | What was done | Where to check in the UI |
| :--- | :--- | :--- |
| **OT-01** | Drawer closed by default; closed row shows counts (done / ready / running); opens by itself when something inside is running | Order → **Operations** tab: arrow ▲ + badge on the parent row |
| **OT-02 / OT-03** | SEQ = execution step (sub-operations first); top level by sequence, inside a drawer by sequence | Order → **Operations** tab, open the arrow |
| **OT-04** | "Machine" → **Resources** (name + machine / manual batch + ready / busy / booked / unavailable); "Not assigned" only when really so | Operations tab, Resources column (op on a manual batch no longer says "Any") |
| **OT-05** | Status badge + the Operation Flow line under it ("Waiting for Op 10", "Waiting for sub-assembly …"); a draft order shows "Draft — not released" | Operations tab, Status column |
| **OT-06** | Log button disabled with the reason while the operation waits; the log modal lists only operations that can take a log, in execution order, labelled "OP-010 — op1 · test-semi-01 (L3)"; numbers in whole / 4 decimals; the "scrap of earlier operations" no longer mixes BOM levels | Operations tab → Log button; "Log progress" window |
| **OT-07** | Quantities with the project's number rule; minutes as 294h 16m 48s | Operations tab, planned / actual / produced columns; Progress Logs tab |
| **OT-08** | Header note reworded | Operations tab, top right |
| **OT-09** | The "Level n" badge became the group header "Makes test-semi-01 … to make N (M needed, K from stock)"; operator / subcontract controls stay in each row | Open a drawer |
| **OT-10** | Counters say "incl. n sub-assembly operations"; the production dashboard lists the order's own operations first | Component Plan header; Production dashboard |
| **SA-01** | **Engine fixed**: each child BOM is processed once, after every BOM that needs it (longest path), so a sub-assembly needed at two levels keeps its whole requirement; the operations get the deepest level. **Existing orders (Order #7) are not changed — re-create them.** | New order for test-final-01 qty 8: test-semi-02 gets its operations and raw-material plan |
| **SA-02** | The part of a sub-assembly taken from stock is judged in the Material column like a material (tagged "sub-assembly, from stock") | Readiness tab, Material column |
| **SA-03** | A shared maker is listed under the deepest operation that needs it; the other drawer points to it | Order with a sub-assembly needed twice |
| **SA-04** | Reviewed: after the makers complete, the parent's limit uses its own BOM row for the sub-assembly (before: the first row of the BOM) | — |
| **OS-02** | BOM tree sub-tab lists in execution order, indented by depth | Component Plan → "Hierarchical BOM tree" |
| **OS-05** | Scrap / rework selects and the Progress Logs tab show product and level | Scrap / Rework windows; Progress Logs tab |
| **OS-07** | MES dashboard: the lock says "waiting for the operations that make its sub-assembly"; operator screen: START is disabled with the reason | Production → MES; operator execution screen |
| **OS-08** | Schedule detail shows the level of sub-assembly rows; scheduler fixed (UR-09) | Schedule detail; generate a schedule for the new order |
| **PW-01** | The old "Sub-assemblies" list in the Component Plan drawer is gone (the drawer above carries it) | Component Plan, open a row's own arrow |
| **PW-02** | A draft order's Readiness tab shows a plain note instead of the red blocker box; operations show "Draft — not released" | Readiness / Operations tab of a draft order |
| **PW-03** | Each operation is resolved once per page (`resolveAll`, `describeAll`, shared readiness context) | page speed |
| **PW-04 / PW-05** | English wording only for now; no tests written or run (the user's rule) | — |

**Not changed on purpose:** the Subcontract and WIP tabs (flat lists of external / WIP operations); the API (it already carries `bom_level`, `source_product_id`, `source_bom_id` per operation).

### UR-10 — A sub-assembly needed by more than one operation (M-20, M-22, M-23, M-24) — Applied in Round 3 (section 13), not Verified
**Words:** M-20 "both op will get the sub link, with the number of quantity required by each op, and user will have a option to produce all sub at initial level if he wants". M-22 (the full text is in the log) says how: "both of those operations will get operations as a sequence", "at each level, there will be a button … To produce all semis at once", "Only after scheduling that button will be disabled, or you can say locked", "the quantities will also get combined".

**My reading (M-22 replaces my first reading of M-20 and the assumptions E1–E3 I wrote after it)**
1. **Default (nothing clicked): every operation that needs the sub-assembly gets its OWN maker operations directly above it, for its OWN quantity.** Op 20 needs 20 → its drawer makes 20; Op 40 needs 20 → its drawer makes 20 more, with a second set of the same operations. The sets are separate operations, and each set is a predecessor (strict finish-to-start) of its own operation only. This replaces the Round 2 behaviour "one shared set, listed under the deepest operation" (SA-03).
2. **A button in every such drawer except the one of the last level** (wording open, e.g. "Produce all here"). Click = the set of this level now makes the needs of this level AND of all later levels together; the later sets are removed (no operations before the later levels any more) and the quantities are added up. With 3 levels (Op 20, 40, 60) the button shows at Op 20 and Op 40, never at Op 60. Click at Op 20 → one drawer at Op 20 for the sum, nothing before Op 40 and Op 60. Click at Op 40 → Op 20 keeps its own set, Op 40 makes its own + Op 60's, nothing before Op 60.
3. The later operations still need the sub-assembly: they keep the requirement line (marked as supplied by the batch made at the earlier operation) and still wait for those makers (which finish long before, because they run before Op 20).
4. **Recursive:** the kept set's quantity grows, so the sub-sub-assembly operations above it are recomputed for the new quantity; the sub-sub-assembly operations of a removed set go with it. The order's raw-material totals do not change (same sum).
5. **Only before scheduling.** Once the order has a schedule the buttons stay visible but disabled ("locked"), with the reason.
6. "Level" = a consuming operation, in execution order. "Initial level" = the consumer that runs first — also when the two consumers sit at different BOM levels (for example an operation of the final product and an operation of a child BOM).
7. **Per child BOM (M-23).** The rule applies to each sub-assembly separately: if Op 20 needs two different semis, both get their own operations in Op 20's drawer, as two groups. The sets, the quantities, the button and the merge are per sub-assembly — a sub-assembly that only one operation needs has no button, while another sub-assembly of the same operation may have one.
8. **Several sub-assemblies at one operation: BOM order (M-23).** The semi mentioned first in the BOM is produced first: its operations get the lower step numbers and sit first; the next semi's operations follow, then the operation that needs them. (Today the groups follow the order of the operation's routing-material rows — creation order, not necessarily the BOM line order — this would change to the BOM line order.)

**My assumptions — the user confirms or corrects:** A1 the click changes the operation list at once (not only when the schedule is generated). A2 a second click on the same button undoes it (every later level gets its own set again) as long as the order is not scheduled. A3 "scheduling" = a schedule exists for the order (any status except cancelled); the buttons are also locked as soon as an operation of the affected sets has progress logged, so quantity already done is never lost. A4 the stock of the sub-assembly is handed out in execution order (the operation that runs first takes from stock first, like raw materials); a merged set makes total need − stock. A5 "operation 14" in M-22 means operation 40 (dictation). A6 wording "Produce all here" / "Produce separately". A7 "produced first" (item 8) also holds in the scheduler: the first semi's last operation finishes before the second semi's first operation starts (one after the other, strictly finish-to-start like the other sub-assembly links), not side by side.

**What it would touch (nothing written yet):** the plan engine / snapshot (one maker set per consuming operation with its own target); the maker → consumer links (each set linked to its own operation only — the links already exist, so no new table is expected, to be confirmed when implementing); `OperationMaterialService::resolve` / `operationTree` (makers found through the links, shown above their own operation, a merged set at the earliest level); one new action + button in the drawer header (Operations tab and Component Plan); the lock check; the recompute of nested quantities. The readiness gates (parent waits for its makers) stay as they are. For M-23: the order of the groups (BOM line order, step numbers) and one more strict link between consecutive sub-assemblies of the same operation.

### UR-11 — Material requests are sent by hand, per order, as many times as needed (M-23) — Applied in Round 3 (section 13), not Verified
**Words (M-23):** "currently the requests are being sent as soon as the drafts are getting created. We will make that process manual and that process will no longer be automated. The user will select which items request are to be sent per PO and multiple requests can be sent per PO." and "The UI for the material is set. I will tell you that part later".

**Today (read in the code):** creating an order (`ProductionOrderService::createDirect` → `persistPlannedMaterials`) at once creates ONE store requisition slip (MR-…, status pending, notes "Generated automatically from Production Order …") with one line for every consolidated raw material, together with the reservation rows (the planned quantity per product) and the event "Materials Reserved". The button "Request additional material" (Reservations tab, `createAdHocRequisitionSlip`) already creates further slips afterwards.

**My reading**
1. Creating an order no longer creates a requisition slip. The reservation rows (the material plan) stay, so Readiness, Component Plan and the material tab keep working; nothing is "requested" yet.
2. On the order the user ticks the items to request and sends them: that creates ONE request (one requisition slip with its own MR- number) with exactly those items. Items that are not ticked stay "not requested".
3. Several requests per order: the user can send another request later for other items (or for the quantity still not requested). Each request is its own slip; the order lists all of them.
4. "Request additional material" (more than the plan), purchase requisitions for shortages and the subcontract procurement are not touched.
5. The material screen is "set": I change nothing in it except what selecting and sending needs, until the user tells me.

**My assumptions — the user confirms or corrects:** B1 "requests" = the store requisition slips (MR-…), not purchase requisitions. B2 an item is requested for the quantity not yet requested (planned − already requested, derived from the slip lines); changing a quantity belongs to the material UI the user will describe later.

### UR-12 — Production order flow: Draft → Locked (first request sent) → release for scheduling → schedule (M-23, M-24) — Applied in Round 3 (section 13), not Verified
**Words (M-23):** "the production order is drafted. It's gonna be kept in draft. All these things will be checked proper in detail, and then against that we have edit options. So then the drafted PO can be edited. We will improve the edition screen also, and then once its requests are sent, its status will be locked. So then the PO cannot be edited, neither it can be deleted. It can only be cancelled then. And then after we receive the material, we will release it for scheduling, and its schedule will be generated, and then accordingly we will go further with the flow."

**Today (read in the code):** statuses draft / released / in_progress / completed / closed / cancelled. A draft can be edited (the edit screen has quantity, dates, production mode / model and description; saving changes only these fields — it does not rebuild operations or materials) and deleted. "Frozen" = every status except draft (no edit, no delete). Cancel is possible until completed / closed and returns unconsumed material to stock. Release works only from draft and, when a requisition slip exists, only after the store issued material (at least partly). Generating a schedule for a draft order releases it automatically (with the PX-09 confirmation for blocked operations).

**My reading (the flow "for now")**
1. A new order is created and **stays in Draft**. The user checks everything in detail (Readiness, Operations, Component Plan, materials).
2. A draft can be **edited** (the edit screen — the user will tell me how to improve it later, not now) and deleted, as long as no request has been sent.
3. **When the first request is sent, the order becomes Locked:** it cannot be edited and cannot be deleted. The only way out is **cancel** (the existing cancel: issued material goes back to stock, slips, reservations and schedule are closed).
4. A locked order can still send further requests (UR-11) and still use the UR-10 buttons until it is scheduled (they do not change the material totals): only editing and deleting are locked.
5. **After the material is received, the user releases the order for scheduling and its schedule is generated**; then the normal flow continues (running, completed, closed). The release rule stays as today: when a request exists, the store must have issued material (at least partly) first.

**My assumptions — the user confirms or corrects:** C1 "Locked" = a draft order with at least one request that is not cancelled; shown as the status "Locked" while the stored status stays `draft`, so reports, API and filters that know "draft" keep working (a separate stored status only if the user wants it). C2 the lock is enforced everywhere (edit screen, delete, service, API), also for admins. C3 "release it for scheduling" = the existing Release button; the schedule is generated right after (by the existing generate-schedule action, or automatically at release if the user prefers); scheduling a draft or locked order from the schedule screen keeps releasing it as today. C4 an order that has planned materials needs at least one request sent and some material issued before it can be released; an order without planned materials can be released at once. C5 cancelled requests do not lock.

**What UR-11 + UR-12 would touch (nothing written yet):** `createDirect` / `persistPlannedMaterials` (no slip at creation); a new "send the selected items" action that creates a slip with the chosen items (like `createAdHocRequisitionSlip`, reservation bookkeeping kept); a derived `isLocked()` on the order used by the policy (update / delete), the edit screen, the service (`update` / `delete`), the API and the order page (badge, buttons); the release check. No new table or column is expected for the derived lock (to be confirmed when implementing).

**Decisions (M-24, 2026-11-25) — the user answered the assumptions and said "implement everything":**
- **A7 → SOFT.** The BOM order of the semis is only an indication for the scheduler (which semi is planned first), never a hard link or lock between the semis.
- **C1 → a REAL new status `locked`** (stored in `production_orders.status`; the column is a plain string, so no migration is needed). It replaces my "derived badge" idea.
- **C3 → SOFT warning instead of a hard block.** When materials are not issued, release / schedule asks "The materials are not released. Do you still want to schedule it?"; on OK the order is released and scheduled even with 0 issued.
- **C4 → open** (the user wants to check a few things first). Behaviour stays as today: release, then the existing "generate schedule" action (which also releases a draft / locked order).
- Everything else (A1–A6, B1–B2, C2, C5) stands as written above. No tests are written or run (the user's rule).


## 13. Round 3 (M-24, 2026-11-25) — UR-10, UR-11 and UR-12 implemented

**Status: Applied (code written). Not tested by me (the user's rule: no tests — only `php -l` and Blade compile checks were run) and not Verified — the user checks each item in the UI.** Source: M-22, M-23, M-24 in `docs/production_readiness_user_messages.md`.

### Commands to run first
1. `php artisan migrate` — **required**: new migration `2026_11_25_100000_add_sub_assembly_plan_columns_to_production_orders` (columns `plan_key`, `execution_order` on `production_order_operations`, `sub_assembly_plan` JSON on `production_orders`). The order pages read these columns. The new status `locked` needs no migration (the status column is a plain string).
2. `php artisan optimize:clear`
3. **Create a NEW order** (for example test-final-01 × 8). Orders created before this change keep their old shared sets and cannot use the new button.

### What was built (rule → what was done → where to check)

| Rule | What was done | Where to check in the UI |
| :--- | :--- | :--- |
| **UR-10 default** — every operation that needs a sub-assembly gets its OWN set of operations for its OWN quantity | `ProductionOrderEngineeringService::computeMultiLevelPlan` now plans per consuming operation (stock is handed out in execution order, the operation that runs first takes first); `ProductionOrderService::syncPlannedTree` creates the operations (plan key + execution order) and the links "set → the operation it serves" | Operations tab / Component Plan of a new order whose BOM needs one sub-assembly at two operations: one drawer above each operation, each with its own quantity |
| **UR-10 per child BOM, BOM order** | Each sub-assembly has its own sets, button and merge; the sub-assemblies of one operation follow the BOM line order (`OperationMaterialService::resolve` sorts the rows by BOM item) | Operation that needs two different semis: two groups in its drawer, the semi listed first in the BOM first |
| **UR-10 button** "Produce all here" / "Produce separately" | `SubAssemblySetService` (merge / split → re-plan with the stock of creation → update operations in place), routes `orders.sub-assembly.merge / split`, partial `sub-assembly-group-actions`; the quantities are added up recursively, the later sets are removed | Drawer header of an operation that needs the sub-assembly (every level except the last); the later operation shows "made together with the batch before …" |
| **UR-10 lock** | Disabled with the reason once a schedule exists, once work has started (running / produced / claimed), when the order is completed / closed / cancelled | Same button after "Release & Generate Schedule" |
| **A7 soft** — BOM order is only an indication for the scheduler | `SchedulingService::sortOperationsTopologically`: of the operations that are ready, the one first in the execution order is planned first; no link between the semis | Generate a schedule: the first semi gets the earlier slot; nothing waits for it |
| **UR-11 manual requests** | No requisition slip is created with the order any more (`persistPlannedMaterials`, plan path); `ProductionOrderService::sendMaterialRequest` creates ONE slip per click with the selected items (planned − already requested); many requests per order; "Request additional material" stays | Order → **Material status** tab: card "Request material from the store" (tick items, quantity, Send) |
| **UR-12 Locked status** | New real status `locked` (`ProductionOrder::STATUS_LOCKED`): the first request (manual or additional) turns a draft into locked; locked = frozen for edit / delete (`isFrozen`), can still send more requests, can be released or cancelled; policy `release` / `issue` accept it; badges, list filter, dashboard counters, planning exceptions, reports know it | Send a request → status badge "Locked", header menu has only Cancel; Edit / Delete are gone; the order list filter has "Locked" |
| **C3 soft warning** | `materialsNotReleased()` (planned material and 0 issued) → `release()` throws `MaterialsNotReleasedException` unless confirmed; the "Release & Plan" dialog shows the warning and asks "The materials are not released. Do you still want to schedule it?"; OK → released and scheduled even with 0 issued; API: `confirm_materials=true` | Order (draft or locked) → Release & Plan |
| **C4** | Open (the user will say). Behaviour as before: Release & Generate Schedule releases and schedules in one step | — |

### Notes and limits (for the review)
* Orders created before this change (no `sub_assembly_plan`) use the old code path (one shared set under the deepest operation); they are not converted. Existing draft orders that already have the automatic slip stay `draft`.
* Locked stays until the order is released or cancelled — cancelling a single slip does not unlock it (so C5 "cancelled requests do not lock" is not applicable with a real status).
* "Produce all here" is refused when a subcontracted (external) operation is in a set that would disappear.
* The Projects module counts "pending" production orders by status `draft` only — locked orders are not counted there (another module's logic is not touched).
* The bulk actions of the order list call service methods that do not exist (`releaseOrder`, `cancelOrder`, `completeOrder`) — an older problem, only the status conditions were updated.
* English wording only (bg / hi not translated). The edit screen is unchanged (to be improved later, the user will say how).


## 14. Round 3 review (M-25, 2026-11-25) — the first look at the new sets

**Status (after M-26): RV-01 closed — the user says the buttons are in the right place, nothing to change. RV-02 explained (not an error). RV-03 (the sub-assembly quantity in the Material column): the user chose rule A (M-27) — Applied (code written 2026-11-25), not tested by me (the user's rule), not Verified.** Source: M-25 and M-26 in `docs/production_readiness_user_messages.md`, with a screenshot of the **Readiness tab** of ORD-2026-000008 (order #9, test-final-01 × 5, plan made with the Round 3 code). The order was checked read-only (database + the data the page receives).

### RV-01 — no "Produce all here" button on the Readiness tab
* The button ("Produce all here" / "Produce separately") and the line "needs X · stock Y · produce Z" are part of the group header of the **Operations tab** and of the **Component Plan** tab (`operations-row`, `operation-tracking-row` include `sub-assembly-group-actions`).
* The **Readiness tab** (`readiness-row`, the one in the screenshot) builds its own group header with only the name and "x of y operations done" — it never got the button and never got the quantities line. That is why nothing can be chosen there.
* For this order the choice IS offered by the data: under test-semifinal-01 → op2 the group "test-semi-02" can be merged (the later operation is test-final-01 → op1, which needs 20). In the Operations tab / Component Plan tab the button sits in the header of that group.
* **Closed by the user (M-26): "Buttons are at correct place... do not change that".** The buttons stay in the Operations tab and the Component Plan tab only; the Readiness tab is NOT to get them.

### RV-02 — why the target is 9,469 (not an error)
The number is the BOM arithmetic of the order, level by level (every BOM here has a base quantity of 1 and no scrap):

| Step | Calculation | Result |
| :--- | :--- | :--- |
| test-final-01 ordered | | 5 |
| test-semifinal-01 needed (BOM 18, 55 per unit — picked for op1) | 5 × 55 | 275 |
| test-semifinal-01 in stock | | 101 → **174 to make** |
| test-semi-02 needed by those 174 (BOM 16, op2, 55 per unit) | 174 × 55 | 9,570 |
| test-semi-02 in stock — taken by the operation that runs first | | 101 → **9,469 to make** (this is the target in the screenshot) |
| test-semi-02 needed by test-final-01 → op1 itself (BOM 18, 4 per unit) | 5 × 4 | 20 → stock already used → **20 to make** (own set, operations 60–63) |
| with "Produce all here" on the first set | 9,469 + 20 | one set of **9,489** |

So the size comes from "55 per unit" at two levels (55 × 55 = 3,025 test-semi-02 for one test-final-01). The earlier engine gave the same numbers (order ×8: 339 × 55 + 32 = 18,677). The alternative lines (test-semifinal-01 × 6 on the alternative operation "op1 4", test-semi-02 × 55 on op "op1 3") were not picked for this order.

### RV-03 — the "1 unit needs" figure of a sub-assembly line in the Material column (the "quantity issue")
**User (M-26):** "do fix the quantity issue but first explain this to me in detail... the calculation". Explained in the chat; **not fixed yet**.

**How the column computes a line:** need for 1 unit = the line's required quantity ÷ the operation's target (`ProductionReadinessService::evaluateMaterialDimensionPooled`), compared with what is issued. For a direct material the required quantity is target × BOM quantity, so the figure is the BOM quantity (5, 55, 54, 34 …) — correct. For a sub-assembly that is only PARTLY from stock, `OperationMaterialService::resolve` keeps only the **stock part** as the material line (the made part is the drawer's job), so the figure becomes stock part ÷ target — not a BOM number. Read-only figures of order #9:

| Operation (target) | Sub-assembly | BOM per unit | Total need | From stock | Made in the drawer | Shown "1 unit needs" |
| :--- | :--- | ---: | ---: | ---: | ---: | ---: |
| test-semifinal-01 → op1 (174) | test-semi-01 | 22 | 3,828 | 101 | 3,727 | **0.5805** |
| test-semifinal-01 → op2 (174) | test-semi-02 | 55 | 9,570 | 101 | 9,469 | **0.5805** |
| test-final-01 → op1 (5) | test-semifinal-01 | 55 | 275 | 101 | 174 | **20.2** |
| test-final-01 → "op1 3" (5) | test-semifinal-02 | 5 | 25 | 25 | 0 | 5 (right: nothing is made) |

**Effects (from the code):** (1) with nothing issued the verdict "Blocked" is right only by coincidence; (2) if 10 test-semi-01 were issued it would say "enough for 17.2 of 174 units" (10 ÷ 0.5805) while at 22 per unit that is 0.45 unit; (3) the made part is never counted, so after all makers have finished the operation still counts as blocked (output cap PX-09: "material issued covers 0 of 174 units") until the store has issued the stock part.

**Origin:** audit gap SA-02 (stock part of a sub-assembly not checked by the Material column) was fixed in Round 2 by judging the stock part "like a material" with the same per-unit formula.

**Options (the user chooses):**
* **A (recommended)** — judge the whole sub-assembly like a UR-01 material: 1 unit needs = the BOM quantity (22 / 55 / 55 / 5); available = issued from the store + what its maker operations produced (counted once ALL operations of the set are completed — the parent waits for them anyway); Blocked below 1 unit, Partially issued with "enough for N of M units", Ready when the whole need is available.
* **B** — take sub-assemblies out of the Material verdict again (direct materials only, as in M-14); the quantities stay in the drawer header ("needs · stock · produce"); this reopens SA-02.

**Decision (M-27): "Yes, apply A". Applied (code written 2026-11-25) — not tested by me (the user's rule: only `php -l`, Blade compile check and `view:clear` were run), not Verified; the user checks it in the UI.**

| What was done | Where |
| :--- | :--- |
| A sub-assembly made inside the order is ONE line in the Material column: need for 1 unit = its BOM quantity (22 / 55 / 55 / 4), total need, available = issued from the store (the stock part) + what its maker operations produced | `ProductionReadinessService::evaluateMaterialDimensionPooled` |
| "Made" = the good quantity of the LAST operation of the set, counted only once ALL operations of the set are finished (completed / skipped / cancelled — the same test as the "parent waits" rule). A set that serves several operations ("Produce all here") hands its output out in execution order, each up to its own share | new `OperationMaterialService::madeFromSet` |
| Same three states as UR-01: below 1 unit → Blocked; some → Partially issued ("enough for N of M units"); the whole need → Ready. The messages say "available" for these lines | same function |
| While the maker operations are still running, the shortfall is a pure WAIT (workflow dependency): the operation's overall status stays Waiting, not Blocked (PX-08 / UR-02). Once they are all done a shortfall is a real blocker again | same function |
| Line text: "test-semi-01 [sub-assembly]: 1 unit needs 22, available 0" and under it "3,828 in total: 101 from stock (issued 0) + 3,727 made above (0 of 4 operations done)" | `readiness-row.blade.php`; `lang/en/production.php` (7 keys: `sub_assembly_tag`, `material_one_unit_needs_available`, `material_available_of`, `sa_line_total`, `sa_line_from_stock`, `sa_line_made`, `sa_line_made_done`) |

**Where to check (order #9 or a new order):** Order → Readiness tab → Material column.
* test-semifinal-01 → op1: test-semi-01 "1 unit needs 22, available 0" · "3,828 in total: 101 from stock (issued 0) + 3,727 made above (0 of 4 operations done)"; → op2: test-semi-02 "1 unit needs 55 …" · "9,570 in total: 101 from stock … + 9,469 made above …".
* test-final-01 → op1: test-semifinal-01 "1 unit needs 55 …" · "275 in total: 101 from stock … + 174 made above (0 of 12 operations done)", and test-semi-02 "1 unit needs 4, available 0" · "20 in total: 20 made above (0 of 4 operations done)" — a sub-assembly that is made completely (nothing from stock) has a line now too.
* test-final-01 → "op1 3": test-semifinal-02 stays a normal material line (all from stock, 1 unit needs 5).
* When the store issues the stock part, or the maker operations are completed, a line moves to "Partially issued — enough for N of 174 units" and, when everything is there, to Ready.

**Notes / limits**
* Output cap (PX-09): the cap of these operations is now (issued + made) ÷ BOM quantity, so after the makers have finished, logging is no longer stopped by an unissued stock part.
* Orders created before the per-operation plan (no `sub_assembly_plan`) use the same rule with their shared set: two operations that share one set would each be credited with the full output (those orders are not converted).
* Not changed: direct materials, the buttons, the plan and the targets. No migration; nothing to run (only the compiled views were cleared).
* The old test `test_material_column_judges_only_direct_materials` (UR-01: "child-BOM rows are not part of the verdict") contradicted both this rule and the Round 2 change SA-02, so it was replaced by the small `test_material_column_judges_a_sub_assembly_as_one_line` (`tests/Feature/Production/ProductionExecutionRulesTest.php`). **Not run** — only `php -l`.

### Where the rest of the plan stands (read-only check of order #9)
The operations, their order (execution 1–19), the links (FS, cross_assembly, sub_assembly) and the stock hand-out (first operation to run takes the stock) all match the rules of M-22 .. M-24. Nothing else was found wrong.

## 15. Request form in a modal (M-30, 2026-11-25) — Applied (code written), not tested, not Verified

The "Request material from the store" form (UR-11) is no longer on the Material status tab. It is now a pop-up modal (`#sendMaterialRequestModal`) opened by a button **"Request materials"** in the header of the **Material Requisitions** tab (`orders/show.blade.php`, tab `vtab-procurement`). Same fields and rules as before (tick items, quantity, note, one slip per send; first request locks a draft). The button is disabled when everything is already requested, and hidden for completed / closed / cancelled orders. After sending, the page returns to the Material Requisitions tab (`ProductionOrderController::sendMaterialRequest`). New key `production.request_materials_btn`.

**Fix (M-31, "it is showing wrong status"):** the Store status column of the Material status tab said "Store request sent" for every line that was not reserved or issued — even when nothing had been requested (a leftover of the automatic slip). It now says, per material: **Not requested** (nothing requested), **Partly requested** (less than planned + additional), **Store request sent** (requested in full); reserved / issued states are unchanged. The "What's next" hint of a draft order now points to the Material Requisitions tab / "Request materials" button. New keys `not_requested`, `partly_requested`.

## 16. How the system treats a purchased item that is short in the store (M-32, 2026-11-25) — findings only, nothing changed

1. **Plan:** a purchased (raw) item is planned for its FULL need whatever the store holds (test-raw-07: planned 531,465, store 100). Only semi-finished items are split into "from stock / to produce". The create screen (Roll-up tab) shows "⚠ short X (from stock Y)" and a shortage counter — a warning only, nothing is created.
2. **Request (`ProductionOrderService::sendMaterialRequest`):** never looks at store stock. The slip asks for the quantity ticked (default = planned − already requested). No purchase requisition is created, and the modal does not show stock or shortage.
3. **Store side (Sales module, `MaterialRequestService`):** the store can reserve only up to what is available (error "No available stock…" when none) and issue up to reserved + available. For the rest, a per-item "Shortage → Raise purchase requisition" button (or a bulk one) creates a Draft PR (`source_type material_request`, `source_id` = slip; quantity = planned − issued − reserved). It does not check buy vs manufacture.
4. **Back on the order:** the Material Requisitions tab lists the slips with their linked PRs (by `source_id`) and PR status. The material line stays "Store request sent / Partly requested" until the store reserves; there is no "waiting for purchase" state on the line. When the goods arrive the store reserves / issues the rest from the same slip item.
5. **Readiness:** only ISSUED quantity counts — the item stays Blocked / Partially issued; planning exceptions raise "Material shortage"; release gives the soft "materials not released" warning.
6. **Not involved:** the MRP Shortage screen (`MrpShortageService`) works from Sales Material Requirements, not from production orders.

Gaps seen (user decides): no automatic PR and no purchase status on the order line; the request modal hides store stock / shortage; the store's shortage button is not type-aware (a manufactured semi whose stock part vanished could get a PR).
