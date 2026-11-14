# Production Order Create — Decisions, UI/UX Spec, Branch Stock & Multi-Level Rollup Rules

> **Document Purpose:** Locked business rules, UI/UX specification, branch stock shortage calculation, multi-level BOM explosion/rollup rules, and selection validation rules for **Production Order Create** (Web & API).
> **Depends on:** [`work_center_decisions.md`](work_center_decisions.md) · [`routing_decisions.md`](routing_decisions.md) · [`bom_decisions.md`](bom_decisions.md) · [`production_business_rules.md`](production_business_rules.md)
> **Implementation Tasks:** [`production_order_implementation_tasks.md`](production_order_implementation_tasks.md)
> **Interactive HTML UI References:**
> - 2-Level Shared Material Example (`FG -> M1, M2, M3, M4` and `M2 -> M3, M5`): [`po_create_ui_example.html`](production_order_ui_examples/po_create_ui_example.html)
> - 3-Level Multi-Branch Example (`B1 -> B2, B3, B4` and `B2 -> B5` with shared `M2`/`M3`): [`po_create_multilevel_b1_example.html`](production_order_ui_examples/po_create_multilevel_b1_example.html)
> **Status:** 🟢 **Agreed & Locked** (2026-11-12)

---

## Summary Index

| ID | Rule Area | Core Decision |
| :--- | :--- | :--- |
| **PO-01** | Auto-Loaded BOM & Routing | Read-only BOM & Process Routing auto-resolved from selected Product (`product_id` → active BOM → `ProductionBom::routing()`); dropdowns removed on Create. |
| **PO-02** | Engineering Blocking & Product Method | Strict Web + API blocking (`422`) when `supplier_method === 'manufacture'` and BOM/Routing/Resources are missing, inactive, expired, uncalculated, or outdated; not required otherwise. |
| **PO-03** | Edit PO Immutability | Existing PO Edit screen and update API/service remain unchanged and locked to snapshotted BOM & Routing. |
| **PO-04** | Branch Available Stock & Shortage | Pool **all active standard warehouses (`Warehouse::TYPE_STANDARD`)** in PO's `branch_id`; `SUM(max(0, quantity - reserved_qty))`; no double-counting issued qty or un-reserved open POs. |
| **PO-05** | PO Create 2-Tab Workspace | Read-only 2-tab workspace on Create (`Tab 1: Components (By Operation)` + `Tab 2: Total Material Rollup (Live)`), fixed/read-only quantities at `≥ 4` decimals. |
| **PO-06** | Tab 1 Default Pre-Selection & Locking | No-Alt ops/materials are auto-selected & locked (`disabled`); With-Alt ops/materials **also initialize with Primary pre-selected** so users can submit directly without edits. |
| **PO-07** | Selection Completeness Validation | UI, Web FormRequest, and API enforce 100% completeness: every stage has 1 op and every material slot on chosen ops has 1 material (Primary or Alt); zero missed ops/materials. |
| **PO-08** | Tab 2 Multi-Level Live Rollup | Shows **only selected** active PO requirements (deselected Primaries/Alts hidden), exploding Child BOMs only on shortage (`To Produce > 0`), pooling shared materials once across all levels. |
| **PO-09** | Source Allocation Breakdown Drawer | Inline expandable drawer on Tab 2 rows breaking down L1 Parent + L2/L3 Child contributions with `Jump to Op Card` and `View Child BOM` actions. |
| **PO-10** | Child BOM Simplicity Rule | Multi-level Child BOMs automatically use **Primary Operations & Primary Materials ONLY** (`is_alternative = false`) — no child-level route/material pickers. |
| **PO-11** | In-Page Child BOM Preview Modal | Clicking any Child BOM badge opens an in-page modal (no page redirect) showing **Tab 1 ONLY with Primary Ops & Primary Mats ONLY**, scaled to `To Produce`, with breadcrumb drill-down. |
| **PO-12** | Backend Snapshot & Reservation Parity | Snapshot only selected ops/materials (with `selected_routing_material_ids`), sum shared materials on Reservations & Requisition Slips, and isolate Shop Floor/Readiness by `source_bom_id`. |

---

## Detailed Rules

### PO-01 — Read-Only Auto-Loaded BOM & Process Routing (Create Screen)
* On Production Order **Create** (`resources/views/modules/production/orders/create.blade.php`), manual `<select>` dropdowns for `#bom_select` and `#routing_select` are **removed**.
* Selecting a `product_id` automatically loads and renders:
  * **Bill of Materials:** read-only display (`bom_display` showing BOM number / version / status) backed by hidden `bom_id`.
  * **Process Routing:** read-only display (`routing_display` showing Routing code / name / cost status) backed by hidden `routing_id`, derived strictly from `ProductionBom::routing()`.
* Stale direct `routings.product_id` lookups (column dropped in the BOM/Routing inversion) must not be used anywhere in Create, Plan Explosion, or Snapshot flows.

### PO-02 — Engineering Blocking Rules & Non-Manufactured Products
* **When `product.supplier_method === 'manufacture'`:**
  * Show clear inline warning signs (`#bom_warning_box`, `#routing_warning_box`, `#engineering_block_alert`), disable submit on the UI, and strictly block PO creation (`422` on Web & API; `InvalidArgumentException` in service) if any of the following hold:
    1. No active `manufacturing` / `subcontracting` BOM exists, or the BOM is `draft` / `pending_approval` / `inactive` / `cancelled` / expired / future-scheduled.
    2. `ProductionBom::getComponentWarnings()` reports missing/invalid components, or any reachable Child BOM (`child_bom_id` on `RoutingOperationMaterial`) is `inactive` / `cancelled` (`BM-08`) or fails BOM/Routing validation.
    3. Linked Routing is missing, inactive (`isCurrentlyActive()`), expired, future-scheduled, or explicitly mismatched with requested `routing_id`.
    4. `RoutingCostStatusService::check()` returns `not_calculated`, `outdated`, or `not_in_snapshot`.
    5. Any Work Center (`WorkCenter::STATUS_ACTIVE`), Section (`WorkCenterSection::STATUS_ACTIVE`), Machine (`Machine::STATUS_ACTIVE`), or Manual Batch (`SectionManualProduction.active`) referenced by the Routing is inactive, or any Section has `has_pending_changes` / `not_calculated` cost status.
* **When `product.supplier_method !== 'manufacture'` (e.g., `buy` / `trade`):**
  * BOM and Process Routing are **not required**.
  * Show no warning banner and allow PO creation cleanly with `bom_id = null` and `routing_id = null` (skipping BOM reservations, requisition slip items, and BOM operation snapshots).

### PO-03 — Edit PO Screen Remains Immutable (Out of Scope)
* Once a Production Order is created (even in `draft` status), it stays locked to its snapshotted BOM and Routing.
* `orders/edit.blade.php`, `UpdateProductionOrderRequest`, `UpdateProductionOrderApiRequest`, and `ProductionOrderService::update()` remain **unchanged**.

### PO-04 — Branch-Scoped Net Available Stock & Shortage Formula
* Stock availability on PO Create (UI live preview + Backend BFS solver + Reservations + Requisition Slips) is strictly **branch-scoped**:
  * Include **all active standard warehouses (`Warehouse::TYPE_STANDARD`)** belonging to the Production Order's `branch_id` (excluding subcontractor and virtual warehouses; fall back to tenant active standard warehouses only when `branch_id === null`).
  * Formula per product:
    $$\text{Branch Available} = \sum_{w \in \text{ActiveStandardWarehouses}(\text{branch\_id})} \max\bigl(0,\; \text{quantity}_w - \text{reserved\_qty}_w\bigr)$$
* **Explicit exclusions (no double-counting):**
  * Do **not** subtract previously issued quantities again — `StockService::recordOutflow()` already decrements physical `quantity`.
  * Do **not** subtract un-reserved open demand from other POs — only actual `reserved_qty` is deducted.
  * When assigning a primary warehouse on `ProductionOrderReservation` / `ProductionRequisitionSlipItem`, pick the branch active standard warehouse holding the highest net available stock for that product (falling back to the branch default / first active standard warehouse).

### PO-05 — Read-Only 2-Tab Workspace on PO Create (`Tab 1` & `Tab 2`)
* Replaces the old static BOM preview container on PO Create with a 2-tab read-only engineering workspace (styled consistently with `bom/create.blade.php`):
  * **Tab 1 — `Components (By Operation)`:** Shows only Parent BOM direct operations and direct component allocations (`RoutingOperation` + `RoutingOperationMaterial`). Users cannot add rows, delete rows, or edit per-unit quantities/UOMs/scrap %. All numeric quantities use **`>= 4` decimal precision** (no integer/2-decimal rounding).
  * **Tab 2 — `Total Material Rollup (Live)`:** Consolidates the active PO material requirements across Parent BOM + auto-exploded Child BOMs against Branch Available Stock (see **PO-08**), with quick summary pills and `[All Active]` / `[Shortages / Produce Only]` filters.

### PO-06 — Tab 1 Default Pre-Selection & Lock Behavior
* Operations in the Parent Routing are grouped by **Stage** (Primary Operation `is_alternative = false` + its Alternatives via `replaces_operation_id` ordered by `priority`, sorted by Primary `sequence`).
* Component rows inside each operation are grouped by **Material Slot** (Primary `RoutingOperationMaterial` `is_alternative = false` + its `replaces_routing_material_id` alternatives ordered by `priority`).
* **Single-option rules (no alternatives):**
  * Operations without alternative ops and materials without alternative materials are **auto-selected and locked** (`checked disabled` radio with `Auto-Selected (No Alt)` badge) and cannot be deselected.
* **With-alternative rules:**
  * Stages with alternative operations and material slots with alternative materials **also initialize with their Primary option pre-selected by default**.
  * Every Alternative Operation's own material slots also pre-initialize with their Primary materials (so switching to an Alternative Operation never leaves required materials unselected).
  * **Result:** If the user does not wish to change any operation or material, they can simply enter PO details / quantity and click **Save / Submit** right away; if they want an alternative, they switch the radio in Tab 1.
* **POST Submission Mirroring:** Because HTML `disabled` inputs are omitted from form POST bodies, Tab 1 renders hidden mirror inputs `<input type="hidden" name="selected_operation_ids[]">` and `<input type="hidden" name="selected_material_ids[]">` (plus `<input type="hidden" name="engineering_selection_submitted" value="1">`) directly from Alpine state so locked selections always submit intact.

### PO-07 — Selection Completeness Validation (No Missed Operations or Materials)
* **Client-Side Guard:** Before submitting and continuously in computed UI state, verify that:
  1. Every Stage in the active Parent Routing has **exact 1** Operation selected (Primary or Alternative).
  2. Every Material Slot belonging to each currently selected Operation has **exact 1** Material selected (Primary or Alternative).
  * If any necessary stage or material slot has zero selections, block form submission, show an inline validation banner naming the missing stage/material, and highlight the incomplete card/row in Tab 1.
* **Server-Side Guard (Web & API):**
  * When `$requireExplicitSelections === true` — always true on Web PO Create submissions (`engineering_selection_submitted=1`) and whenever an API caller supplies `selected_operation_ids` or `selected_material_ids` (even as `[]`):
    * Reject `422` if `selected_operation_ids` omits any stage (0 ops selected), selects >1 op for the same stage, or picks an op outside the resolved Routing.
    * Reject `422` if `selected_material_ids` omits any required material slot of any chosen operation (0 materials selected), selects >1 material for a slot, or includes materials belonging to unselected operations / other BOMs.
  * When a headless API caller **omits** both `selected_operation_ids` and `selected_material_ids` completely (`!$requireExplicitSelections`), the service cleanly auto-defaults every stage and required material slot to its Primary option (`is_alternative = false`).

### PO-08 — Tab 2 (`Total Material Rollup (Live)`) — Active-Only Multi-Level Rollup & Shared-Material Pooling
* **Strict Exclusion of Deselected Options:** Any deselected Primary or deselected Alternative (operation or material) in Tab 1 is **completely hidden from Tab 2 and excluded from calculations**, reservations, and requisition slips.
* **Parent vs. Child BOM Scaling:**
  * Parent L1 allocation gross qty:
    $$\text{Parent Gross Qty} = \left(\frac{\text{PO Qty}}{\text{Parent BOM Base Qty}}\right) \times \text{Base Allocation Qty} \times \left(1 + \frac{\text{Scrap \%}}{100}\right)$$
* **Conditional Multi-Level Child BOM Explosion (BFS / Level-Order):**
  * Child BOM demands at Level 2+ are aggregated per `child_bom_id` using a virtual remaining branch-stock ledger per product so stock is never double-counted across levels.
  * For a semi-finished Child BOM item:
    $$\text{From Stock} = \min(\text{Required Qty},\; \text{Remaining Branch Available}), \quad \text{To Produce} = \max(0,\; \text{Required Qty} - \text{From Stock})$$
  * Only if `To Produce > 0` does the engine explode that Child BOM (scaling its Primary allocations by `To Produce / Child BOM Base Qty`), enqueueing Level 3+ grandchild demands as needed. If `To Produce === 0` (`Covered by Stock`), the Child BOM is **not** exploded and its child-only raw materials do not enter the PO rollup.
* **Common Material Consolidation (e.g., `M3`, `M2` Required by Both Parent & Child BOMs):**
  * Unlike the static BOM master rollup view, PO Create Tab 2 combines demand across Parent BOM + all exploded Child BOMs into **one consolidated row per `product_id`**:
    $$\text{Total PO Req} = \text{Parent Req} + \text{Child BOMs Req}$$
    $$\text{Shortage} = \max(0,\; \text{Total PO Req} - \text{Branch Available})$$
  * Branch Available Stock is pooled **once** per raw material/consumable product against the combined `Total PO Req` (with clear columns: `Parent Req`, `+ Child BOMs`, `= Total PO Req`, `Branch Avail`, `Status / Action` + badges such as `Used in Parent + Child BOM`, `Shared (B1 + N Child)`, `Via Child BOM only`).

### PO-09 — Source Allocation Breakdown Drawer
* Every Tab 2 row expands inline into a **Source Allocation Breakdown Drawer** showing:
  * **Header Summary:** `Parent (...) + Child BOMs (...) = Total PO Req (...) | Branch Avail (...)`.
  * **L1 Parent Source Rows:** Source Level (`Parent BOM` / `L1 · Parent B1`), Operation (`Op Seq · Name`), Selection (`Primary Op / Primary`, `Alt Op Selected`, etc.), Net Qty, Scrap %, Gross Qty, and **`Jump to Op Card`** button (switches to Tab 1 and scrolls/highlights that card).
  * **L2+ Child BOM Source Rows:** Source Level (`L2 · Child B2`, `L3 · Child B5`, etc.), Operation (`Op Seq · Name (Producing X)`), Selection (`Primary Auto`), Net Qty, Scrap %, Gross Qty, and **`Open/View Child BOM`** button (opens the in-page Child BOM modal).

### PO-10 — Child BOM Simplicity Rule (Primary Operations & Primary Materials Only)
* Multi-level Child BOMs automatically use **Primary Operations (`is_alternative = false`) and Primary Materials (`is_alternative = false`) ONLY**.
* Users are **never** asked to choose alternative operations or alternative materials on Child BOMs (preventing exponential UI complexity across deep multi-level trees).
* Any Child BOM's internal alternative operations or alternative materials are completely excluded from PO explosion math, snapshots, and the Child BOM Modal.

### PO-11 — In-Page Child BOM Preview Modal (`Tab 1 Only`)
* Clicking any Child BOM badge in Tab 2 (`@click.stop`) or `View Child BOM` in a Drawer opens an **in-page Modal** instead of redirecting to another page.
* **Modal Contents:**
  * Header context strip: BOM number, version, status, Product name/code, `PO Needs`, `Branch Avail`, and either **`Producing Shortage: X`** (when `To Produce > 0`) or **`Covered by Branch Stock (Producing 0.0000 · Base 1.0000 preview)`** banner (when stock covers demand).
  * Body renders **Tab 1 ONLY with Primary Operations & Primary Materials ONLY**, showing each Primary operation card and scaled material rows (`Base Qty`, `Qty For Shortage` or `Base Preview Qty`, `UOM`, `Scrap %`, `✔ Primary Auto`), tagging shared items (e.g., `Also in Parent BOM`).
  * **Nested Multi-Level Drill-Down:** If a material inside the modal has its own Child BOM (e.g., `B2` contains `B5`), clicking its `View B5 Child BOM →` button pushes onto the in-modal navigation stack (`modalStack`) showing breadcrumb navigation (`B2 (BOM-B2-001) › B5 (BOM-B5-001)`) and a `← Back` button.

### PO-12 — Backend Snapshot, Reservation, Requisition Slip & Shop Floor Alignment
* **Operation Snapshot Schema:** Add nullable JSON `selected_routing_material_ids` on `production_order_operations` to record exact chosen `RoutingOperationMaterial` IDs per snapshotted operation.
* **Snapshot Persistence (`createDirect` & `createFromPlan`):**
  * Level 1 Parent creates `ProductionOrderOperation` rows strictly for the selected Parent operations (`bom_level = 1`, `target_produced_qty = orderQty`, `is_intermediate = false`, exact `selected_routing_material_ids`).
  * Level 2+ Exploded Child BOMs (`to_produce > 0`) create `ProductionOrderOperation` rows strictly for their **Primary operations (`is_alternative = false`)** (`bom_level >= 2`, `target_produced_qty = toProduce`, `is_intermediate = true`, Primary-only `selected_routing_material_ids`).
* **Shared-Material Accumulation:** Because `MaterialRequestService::reserve()` and `issue()` look up one `ProductionOrderReservation` per `(production_order_id, product_id)` and group `ProductionRequisitionSlipItem`s by `(slip_id, product_id)`:
  * Persist **1 combined `ProductionOrderReservation` per `product_id`** summing `quantity_planned` across Parent + all exploded Child BOMs (including semi-finished `from_stock` + shared raw materials like `M2` / `M3`) — never skipping or overwriting on shared products.
  * Persist **1 combined `ProductionRequisitionSlipItem` per `product_id`** matching Tab 2 totals, tied to a branch active standard warehouse.
* **Shop Floor & Readiness Scoping:** `ProductionOrderOperation::getExpectedComponents()` and `ProductionReadinessService` filter `RoutingOperationMaterial` by `bom_id = $op->source_bom_id` (required for reusable routings) and `$op->selected_routing_material_ids` (falling back to `is_alternative = false` for legacy rows without `selected_routing_material_ids`, never querying dropped `production_bom_items.is_alternative`).

---

## Verified Reference Examples

### Example 1 — 2-Level Shared Material (`FG -> M1, M2, M3, M4` and `M2 -> M3, M5`)
* **Interactive Reference:** [`docs/production_order_ui_examples/po_create_ui_example.html`](production_order_ui_examples/po_create_ui_example.html)
* **Setup (`PO Qty = 10`):**
  * Parent `Op 10` (Locked): `M1` (`1/unit = 10`), `M2` (`1/unit = 10`, Child `BOM-M2-001`)
  * Parent `Op 20` (Primary, default) vs `Op 25` (Alt): `M3` (`1/unit = 10`) / `M3-ALT` (`1/unit`), `M4` (`1/unit = 10`)
  * Child `BOM-M2-001` (Primary Only — ignores Alt `Op 15` & `M3-ALT`): `Op 10` uses `M3` (`2/unit`), `Op 20` uses `M5` (`1/unit`)
  * Branch Stock: `M1=12`, `M2=4` (or `15` toggle), `M3=15`, `M4=10`, `M5=5`
* **Outcome when `M2 Avail = 4.0000` (Default Primaries):**
  * `M2`: Need `10`, Avail `4`, From Stock `4`, **Produce `6`** -> `BOM-M2-001` explodes for `6`.
  * `M3` (**Parent + Child Combined**): Parent (`10`) + Child `M2` (`6 × 2 = 12`) = **Total `22.0000`** vs Avail `15.0000` -> **From Stock `15.0000`, Short `7.0000`**.
  * `M5` (**Via Child BOM only**): Child `M2` (`6 × 1 = 6.0000`) vs Avail `5.0000` -> **Short `1.0000`**.
* **Outcome when `M2 Avail = 15.0000`:** `M2` is **Covered by Stock** (`0` produced), `M3` drops back to `10.0000` (Sufficient), and `M5` is excluded from Tab 2.

### Example 2 — 3-Level Multi-Branch (`B1 -> B2, B3, B4` and `B2 -> B5` with Shared `M2` & `M3`)
* **Interactive Reference:** [`docs/production_order_ui_examples/po_create_multilevel_b1_example.html`](production_order_ui_examples/po_create_multilevel_b1_example.html)
* **Setup (`PO Qty of B1 = 10`):**
  * `B1`: `Op 10` (`M1=1`, `B2=1`), `Op 20` (`B3=1`, `M2=1` or `M2-ALT=1`) vs `Op 25` (`B3=1`, `M2=1.5`), `Op 30` (`B4=1`, `M3=1`)
  * `B2` (L2 Primary): `Op 10` (`B5=1`, `M2=2`, ignoring `M2-ALT`), `Op 20` (`M4=1`) — ignores Alt `Op 15`
  * `B5` (L3 Primary, child of `B2`): `Op 10` (`M5=2`, `M2=3`, `M3=1`)
  * `B3` (L2 Primary): `Op 10` (`M6=1`, `M7=2`)
  * `B4` (L2 Primary): `Op 10` (`M8=1`, `M2=2`)
  * Branch Stock: `B2=4`, `B3=12`, `B4=2`, `B5=2`, `M1=15`, `M2=30`, `M3=11`, `M4=8`, `M5=5`, `M6=20`, `M7=20`, `M8=6`
* **Consolidated Rollup (`Default Primaries`):**

| Item | Level / Role | B1 Req | Child BOMs Req | Total PO Req | Branch Avail | Status / Action |
| :--- | :--- | ---: | ---: | ---: | ---: | :--- |
| **M1** | Raw Material | `10.0000` | `0.0000` | `10.0000` | `15.0000` | ✔ Sufficient |
| **B2** | Semi-Finished (L2 Child) | `10.0000` | `0.0000` | `10.0000` | `4.0000` | ⚙ Produce `6.0000` (`From Stock: 4.0000`) → Explodes `BOM-B2-001` |
| **B3** | Semi-Finished (L2 Child) | `10.0000` | `0.0000` | `10.0000` | `12.0000` | ✔ Covered by Stock → Not Exploded (`M6`, `M7` omitted) |
| **M2** | Raw (**Shared `B1 + B2 + B4 + B5`**) | `10.0000` | `+40.0000` (`B2:12 + B4:16 + B5:12`) | `50.0000` | `30.0000` | ⚠ Short `20.0000` (`From Stock: 30.0000`) |
| **B4** | Semi-Finished (L2 Child) | `10.0000` | `0.0000` | `10.0000` | `2.0000` | ⚙ Produce `8.0000` (`From Stock: 2.0000`) → Explodes `BOM-B4-001` |
| **M3** | Consumable (**Shared `B1 + B5`**) | `10.0000` | `+4.0000` (`B5:4`) | `14.0000` | `11.0000` | ⚠ Short `3.0000` (`From Stock: 11.0000`) |
| **B5** | L3 Grandchild (via `B2`) | `—` | `+6.0000` (`B2:6`) | `6.0000` | `2.0000` | ⚙ Produce `4.0000` (`From Stock: 2.0000`) → Explodes `BOM-B5-001` |
| **M4** | Raw (`Via Child BOM B2 only`) | `—` | `+6.0000` | `6.0000` | `8.0000` | ✔ Sufficient |
| **M8** | Raw (`Via Child BOM B4 only`) | `—` | `+8.0000` | `8.0000` | `6.0000` | ⚠ Short `2.0000` (`From Stock: 6.0000`) |
| **M5** | Raw (`Via Child BOM B5 only`) | `—` | `+8.0000` | `8.0000` | `5.0000` | ⚠ Short `3.0000` (`From Stock: 5.0000`) |

