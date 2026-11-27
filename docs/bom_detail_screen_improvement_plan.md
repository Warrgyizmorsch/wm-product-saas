# BOM Detail Display Screens — Improvement Plan & Cost Audit

> **Created:** 2026-11-26  
> **Updated:** 2026-11-27  
> **Status:** Tabs 1–4 Implemented & Verified (Tabs 5–6 and remaining costing discussions pending)  
> **Workspace Location:** [`docs/bom_detail_screen_improvement_plan.md`](file:///c:/xampp/htdocs/wm-product-saas/docs/bom_detail_screen_improvement_plan.md)  
> **Feature Tests:** [`BomDetailDisplayImprovementsTest.php`](file:///c:/xampp/htdocs/wm-product-saas/tests/Feature/Production/BomDetailDisplayImprovementsTest.php)
> **Related Docs:** [`docs/bom_decisions.md`](file:///c:/xampp/htdocs/wm-product-saas/docs/bom_decisions.md), [`docs/bom_implementation_tasks.md`](file:///c:/xampp/htdocs/wm-product-saas/docs/bom_implementation_tasks.md)

---

## 0. Target Files & Reference BOM

### Key Code Files
- **View:** [`resources/views/modules/production/bom/show.blade.php`](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/bom/show.blade.php)
- **Controller:** [`app/Domains/Production/Controllers/ProductionBomController.php`](file:///c:/xampp/htdocs/wm-product-saas/app/Domains/Production/Controllers/ProductionBomController.php)
- **Repository:** [`app/Domains/Production/Repositories/ProductionBomRepository.php`](file:///c:/xampp/htdocs/wm-product-saas/app/Domains/Production/Repositories/ProductionBomRepository.php)
- **Cost Service:** [`app/Domains/Production/Services/ProductionCostService.php`](file:///c:/xampp/htdocs/wm-product-saas/app/Domains/Production/Services/ProductionCostService.php)
- **Explosion Service:** [`app/Domains/Production/Services/BomExplosionService.php`](file:///c:/xampp/htdocs/wm-product-saas/app/Domains/Production/Services/BomExplosionService.php)
- **Routing Cost Reference:** [`app/Domains/Production/Services/RoutingCostService.php`](file:///c:/xampp/htdocs/wm-product-saas/app/Domains/Production/Services/RoutingCostService.php) & [`resources/views/modules/production/routing/show.blade.php`](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/routing/show.blade.php)

### Reference BOM Audited (Real DB Data Verified on 2026-11-26)
- **BOM:** `BOM-TEST-FINAL-01` (ID `12`) — *Executive conference table 1800×900* (`test-final-01`), `v1.0.0`, `base_quantity = 1`, `status = approved`
- **Linked Routing:** `RTG-2026-000008` (ID `8`) — *Final Assembly, Inspection & Packing*, `v1.0.0`, `status = active`
- **Routing Operations:**
  - `OP-010` (`id = 18`, `seq = 10`, **Primary**): *Table assembly* — Manual Batch `test-batch-9A`
  - `OP-020` (`id = 19`, `seq = 20`, **Alternative to OP-010**, `priority = 1`): *Table assembly — morning crew* — Manual Batch `test-batch-9B`
  - `OP-030` (`id = 20`, `seq = 30`, **Primary**): *Final inspection & crating* — Manual Batch `test-batch-10A`
  - `OP-040` (`id = 21`, `seq = 40`, **Primary**): *Automated packing* — Machine `Automated Packaging Line`

---

## 1. Decision Lock Summary

| # | Tab / Area | Status | Finalized Decisions |
|---|---|---|---|
| 1 | **Tab 1: Components** | 🛠️ **Implemented** | Remove the **Allocations by operation** section (`show.blade.php` L482–623). Keep **Materials (master list)** + expandable per-row allocation drawer (`L358–480`). Operation-wise material placement belongs in **Tab 3: Workflow & Routing**. |
| 2 | **Tab 2: Multi-Level BOM Explosion** | 🛠️ **Implemented** | 1. Remove the standalone left-hand numeric `Level` column.<br>2. Use **Arrows + Subtle Level** (`L1`, `L2`, etc. pill + Enter-style `↳` arrow on the left side of each child item name).<br>3. **Hierarchy:** `L1` = Finished Good produced by this BOM; `L2` = Direct components required by this BOM (all 6 on `BOM-TEST-FINAL-01`); `L3+` = Child BOMs of `L2` components.<br>4. **Collapsed by default:** Show **L1 + L2 only** on initial load; `L3+` start collapsed inside expandable per-node drawers (chevron toggle on any row with a Sub-BOM, plus `Expand All` / `Default (L1–L2 Only)` buttons). |
| 3 | **Tab 3: Workflow & Routing** | 🛠️ **Implemented** | Authoritative view for material allocations on the active routing. Fix: badge alternative operations (`OP-020`), clarify alternative-branch materials, display actual Manual Batch / Machine / Vendor + Section instead of `"Generic Capacity"`, display full `Times & Yield` (`Setup/Run`, `Setup/Pc`, `Run`, `Yield %`), and enrich allocated material details. |
| 4 | **Tab 4: Estimated Material Cost Summary** | 🛠️ **Core Implemented** *(⏳ additional cost items to be discussed)* | 1. **Primary-only initial baseline** (just like Routing): exclude alternative materials AND materials on alternative operations (`op.is_alternative = true`). Show Routing-style notice + *"+N alternative(s) available to simulate"* badge.<br>2. **Align routing costs** with Routing's saved figures (`labor_cost_per_unit`, `machine_cost_per_unit`, `subcontract_cost_per_unit` × `base_quantity`, with `setup_cost_per_run` shown separately — never mixed into per-unit minutes).<br>3. **Remove `Work Center Overhead` card** from this screen.<br>4. **Show Rolled-Up Sub-BOM Cost vs. Direct/Catalog Product Cost** side-by-side.<br>5. **Add `Simulate Cost` modal** inside Tab 4 (like Routing's `Simulate Route`) covering **both alternative routes/operations AND alternative materials**. |
| 5 | **Tab 5: Audit Log & History** | ⏳ **Pending Review** | Not yet discussed. |
| 6 | **Tab 6: Where Used** | ⏳ **Pending Review** | Not yet discussed. |

---

## 2. Detailed Tab-by-Tab Specification

### 2.1 Tab 1: Components (`#tab-components`) — 🔒 Finalized
- **Current code:** [`show.blade.php` L355–624](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/bom/show.blade.php#L355-L624).
  - Lines `358–480`: **Materials (master list)** table (one row per unique material in `$bom->items`) with expandable collapse drawer `#bomMatDrawer{id}` showing its allocations and `"View in Routing tab"` jump link (`L467`).
  - Lines `482–623`: **Allocations by operation** (`__('production.bom_allocations_by_operation')`).
- **Required changes:**
  1. Remove lines `482–623` (**Allocations by operation** heading and cards) completely.
  2. Keep **Materials (master list)** (`L358–480`) intact.
  3. Add an `@else` empty-state block when `$masterRows->isEmpty()` (using `__('production.no_components_spec_yet')`), since previously the `@else` only sat on `$opMaterials->isNotEmpty()`.
  4. Check if `bom_allocations_by_operation` in [`lang/en/production.php`](file:///c:/xampp/htdocs/wm-product-saas/lang/en/production.php#L2399) (and `bg`/`hi`) is referenced anywhere else; remove or mark unused if not.

---

### 2.2 Tab 2: Multi-Level BOM Explosion (`#tab-explosion`) — 🔒 Finalized
- **Current code:** [`show.blade.php` L627–780](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/bom/show.blade.php#L627-L780), [`BomExplosionService.php`](file:///c:/xampp/htdocs/wm-product-saas/app/Domains/Production/Services/BomExplosionService.php).
- **Agreed Level Hierarchy:**
  - **Level 1 (`L1`):** Finished product produced by the viewed BOM (`test-final-01`, `BOM-TEST-FINAL-01`).
  - **Level 2 (`L2`):** Direct components required by the viewed BOM (on `BOM-TEST-FINAL-01`: the 6 components `test-semifinal-01`, `test-semifinal-03`, `test-raw-23`, `test-raw-20`, `test-raw-21`, `test-raw-22`).
  - **Level 3+ (`L3`, `L4`, `L5`…):** Components required by the child BOMs of `L2` components (e.g., expanding `test-semifinal-01` reveals `L3`: `test-semi-05`, `test-semi-06`, `test-raw-12`, `test-raw-10`, `test-raw-07`; expanding `test-semi-05` reveals `L4`, etc.).
- **Required changes:**
  1. **Remove standalone `Level` column** (`L644–646`, `L759`) and redistribute column widths cleanly across the remaining 7 columns (`Component Product`, `Linked Sub-BOM`, `BOM Base Qty`, `Qty Required`, `UOM`, `Version`, `Status`).
  2. **Arrows + Subtle Level UI inside Component Product cell (`L650–671`):**
     - Keep indentation (`($level - 1) * 18` or `20` px).
     - Add expand/collapse chevron button (`›` / `⌄`) for any node that has children/Sub-BOM (or fixed-width spacer for leaves).
     - Add subtle level pill (`L1`, `L2`, `L3`, ...).
     - For `$level > 1`, add an Enter-style `↳` SVG arrow on the left side of the component name (softened on `Alternative — info only` rows); for `$level === 1`, show a root dot/icon.
  3. **Collapsed by Default (Per-Node Drawers):**
     - Initial render shows **`L1` and `L2` ONLY** (`L3+` rows are hidden/collapsed by default).
     - Clicking the chevron on any row with a Sub-BOM toggles its **direct children** (`L3` under `L2`, `L4` under `L3`, etc.) — preserving nested collapse state cleanly.
     - Add **`Expand All`** and **`Default (L1–L2 Only)`** toolbar buttons alongside the `calc_qty` control.
- **Open detail to confirm during/before implementation:**
  - Currently `BomExplosionService::resolveChildren()` (`L163–189`) also emits `Alternative — info only` rows at Level 2 for `OP-020` (4 extra rows, making 10 rows at L2 instead of 6). Confirm whether L2 should show only the 6 primary components cleanly, or keep `Alternative — info only` rows (or collapse/group them).

---

### 2.3 Tab 3: Workflow & Routing (`#tab-routing`) — 🔒 UI Fixes Finalized
- **Current code:** [`show.blade.php` L783–902](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/bom/show.blade.php#L783-L902), [`ProductionBomRepository.php` L103–117](file:///c:/xampp/htdocs/wm-product-saas/app/Domains/Production/Repositories/ProductionBomRepository.php#L103-L117).
- **Verified bugs on `BOM-TEST-FINAL-01` (`RTG-2026-000008`):**
  1. **Alternative operation indicator missing:** `OP-020` has `is_alternative = true`, `replaces_operation_id = 18` (`OP-010`), `priority = 1`, yet renders identically to primary operations.
  2. **Manual Batch / Resource ignored (`"Generic Capacity"` bug):** `L869–875` only checks `$op->machine`, so `OP-010` (`test-batch-9A`), `OP-020` (`test-batch-9B`), and `OP-030` (`test-batch-10A`) all falsely print `"Generic Capacity"`. Also `$op->section` and `$op->vendor` (when `is_external`) are not displayed.
  3. **Incomplete `Times & Yield`:** `L877–880` omits `setup_time_per_piece_minutes` (`1m` on `OP-010`, `OP-020`, `OP-030`), `wait_time_minutes`, and `expected_yield_percentage`.
  4. **Alternative-operation material badges misleading:** Materials inside `OP-020` have `opMat->is_alternative = false` (primary within that alternative branch), so `L836` shows a blue `Primary` badge without indicating the operation itself is alternative.
- **Required changes:**
  1. **Eager-load relations** in `ProductionBomRepository::getBomWithComponents()`: `routing.operations.workCenter.parent`, `routing.operations.section`, `routing.operations.machine`, `routing.operations.manualProduction`, `routing.operations.vendor`, `routing.operations.primaryOperation`.
  2. **Routing Header Summary (`L785–791`):** Show primary stage count + `+N alternative branch(es)` and link to routing detail page.
  3. **Operation Stage (`L811–846`):**
     - If `$op->is_alternative`, apply subtle warning row styling and show badge: `Alternative · Substitutes {OP-XXX} · Priority {N}`. Otherwise show `Primary` badge.
     - In `ALLOCATED CONSUMED MATERIALS`, distinguish `"Branch Primary"` / `"Used when alternative runs"` vs `"Primary"` vs `"Alternative (P{n}) of {material}"`, and include scrap % / gross qty (when scrap > 0) + sub-BOM pill if `child_bom_id` is set.
  4. **Work Center & Resource (`L852–876`):** Show Section under Work Center; show Machine (`name + code`), Manual Batch (`batch_name` + shift if available), or External Vendor (`vendor->name`) — only fall back to `"Generic Capacity"` when none is assigned.
  5. **Times & Yield (`L877–880`):** Show `Setup/Run`, `Setup/Pc` (when > 0), `Run`, and `Yield: {pct}%`.

---

### 2.4 Tab 4: Estimated Material Cost Summary (`#tab-costing`) — 🔒 Core Finalized (⏳ More Items Pending)

#### A. Audit Findings on Current Code (Verified on `BOM-TEST-FINAL-01`, `base_quantity = 1`)

| Metric | Currently Shown | Why It Is Wrong | Verified Correct Value (`BOM-TEST-FINAL-01`) |
|---|---|---|---|
| **Component Cost (Net)** | `₹5,655.00` | `ProductionCostService::calculateMaterialCost()` & `ProductionBomController::show()` skip `$item->is_alternative` but **fail to skip `$item->routingOperation?->is_alternative`**. All 4 items on alternative `OP-020` (`₹2,735.00`) are double-counted. | **`₹2,920.00`** (Direct primary `OP-010 + OP-040`) / **`₹1,962.20`** (Rolled-up raw leaves across all 5 levels) |
| **Scrap Loss** | `₹0.00` | Same missing `routingOperation?->is_alternative` check in `calculateScrapAdjustment()`. | `₹0.00` (with guard fixed) |
| **Labor Cost** | `₹176.38` (`176.3787`) | `ProductionCostService::calculateLaborCost()` uses `(setup_time_minutes + processing_time_minutes) * labor_rate * base_quantity * yield_factor`. It mixes one-time `setup_time_minutes` into per-unit time and **ignores `setup_time_per_piece_minutes`**. | **`₹42.8993`** per unit (`RoutingCostService` saved primary labor) |
| **Machine Cost** | `₹3.53` (`3.5335`) | Same formula bug: multiplies `OP-040`'s `5m` setup time as per-unit machine cost (`5.3 * 0.6667 = 3.5335`). | **`₹0.2000`** per unit (`0.3 * 0.6667`) |
| **Setup Cost / Run** | *(Not shown separately)* | Folded erroneously into per-unit labor & machine above. | **`₹153.4365`** once per run (`OP-010` `107.7160` + `OP-030` `29.2595` + `OP-040` `16.4610`) |
| **Work Center Overhead** | `₹0.00` | `calculateOverheadCost()` is hardcoded to `return 0.0;`. | **Remove card from screen** |
| **Subcontract Cost** | *(Not shown)* | `is_external` operations are skipped in labor/machine and have no cost card. | `₹0.00` on this BOM (add support when `is_external` ops exist) |
| **Total Mfg Cost** | `₹5,834.91` | Inflated by `+₹2,735.00` duplicate alt-op materials and `+₹136.81` wrong setup math. | **Direct Catalog:** `₹2,963.10` (`2,963.0993` + `₹153.4365` setup/run)<br>**Rolled-Up Sub-BOM:** `₹2,231.48` (`1,962.20` raw + `269.2837` multi-level routing + setups) |
| **Item Breakdown Table** | 10 untagged rows (`₹5,655.00` footer) | Lists `OP-020` duplicate rows with no Operation column and no Alternative tag. | Show primary rows cleanly with Operation #, Catalog vs Sub-BOM Roll-Up columns, and clearly tag excluded alternatives. |

#### B. Verified Multi-Level Sub-BOM Roll-Up Reference (`BOM-TEST-FINAL-01`)
Computed via `RoutingCostService` saved figures across the full tree:
- **`BOM-TEST-FINAL-01` (Level 1):** Direct Primary Materials = `₹2,920.00`; Own Routing/unit = `₹43.0993` (Labor `₹42.8993`, Machine `₹0.2000`); Own Setup/run = `₹153.4365`.
  - **`BOM-TEST-SEMIFINAL-01` (`test-semifinal-01`, catalog `unit_cost = ₹1,400.00`):**
    - Rolled-up raw leaves = **`₹759.20`**, Rolled-up routing/unit = **`₹180.3235`** → True rolled-up total (excl. setups) = **`₹939.5235`** (Setup/run across branch = `₹700.0304`).
  - **`BOM-TEST-SEMIFINAL-03` (`test-semifinal-03`, catalog `unit_cost = ₹1,200.00`):**
    - Rolled-up raw leaves = **`₹883.00`**, Rolled-up routing/unit = **`₹45.8609`** → True rolled-up total (excl. setups) = **`₹928.8609`** (Setup/run across branch = `₹114.0660`).
  - **Direct Raw Items on `BOM-TEST-FINAL-01`:** `test-raw-23` (`₹75`) + `test-raw-20` (`₹60`) + `test-raw-21` (`₹65`) + `test-raw-22` (`₹120`) = **`₹320.00`**.
- **Combined Multi-Level Totals (`BOM-TEST-FINAL-01`):**
  - Rolled-up Raw Materials = `759.20 + 883.00 + 320.00` = **`₹1,962.2000`**
  - Rolled-up Per-Unit Routing = `43.0993 + 180.3235 + 45.8609` = **`₹269.2837`**
  - Rolled-up Total (excl. setups) = **`₹2,231.4837`** (Combined setups across all sub-BOM runs = `₹967.5329`)

#### C. Locked Tab 4 Features
1. **Primary-Only Baseline + Routing-Style Notice:**
   - Initial calculations use **Primary only** (primary operations + primary materials).
   - Display notice matching [`routing/show.blade.php` L229-236](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/routing/show.blade.php#L229-L236): *"Primary baseline (excludes alternative operations & alternative materials)"* and *"+N alternative(s) available to simulate"*.
2. **Work Center Overhead Removed:**
   - Remove the `Work Center Overhead` card from Tab 4.
3. **Roll-Up vs. Direct / Actual Product Cost:**
   - Display both Direct Catalog Cost (`products.unit_cost` on level-1 components) and Rolled-Up Multi-Level Sub-BOM Cost so gaps between standard product prices and actual manufactured sub-BOM costs are immediately visible.
4. **`Simulate Cost` Modal (Routes + Materials):**
   - Modeled after Routing's `#routeSimulationModal` (`routing/show.blade.php` L839–1020), opened via a **`Simulate Cost`** button in Tab 4.
   - **Stage / Route Selection:** For any stage with alternative operations (e.g., `OP-010` vs `OP-020`), select which operation runs; switching the operation automatically switches that stage's consumed materials and routing costs.
   - **Material Alternative Selection:** Within any active operation, for any primary material with alternative replacement rows (`om.is_alternative = true` / `replaces_routing_material_id`), select Primary vs Priority 1/2/3 Alternative.
   - **Run Calculations:** Computes simulated Material Cost (Direct & Rolled-Up), Scrap Loss, Routing Cost (Labor, Machine, Subcontract), Setup Cost / Run, and Total Manufacturing Cost, plus exact `+/-` delta vs Primary Baseline.

---

## 3. Interactive Visual Mockups (Created During Planning)
- **Full 4-Tab Interactive Prototype (with BOM-TEST-FINAL-01 data, L1–L2 collapsed Tab 2 drawers, and working Simulate Cost modal):**  
  `C:\Users\Tarun\.gemini\antigravity\brain\024aa548-25ea-4474-b89c-76232d66e00e\bom_4tabs_visual_preview.html`
- **Cost Audit Explainer Charts:**  
  `C:\Users\Tarun\.gemini\antigravity\brain\024aa548-25ea-4474-b89c-76232d66e00e\bom_cost_visual_explainer.html`
- **Explosion Arrow Comparison Preview:**  
  `C:\Users\Tarun\.gemini\antigravity\brain\024aa548-25ea-4474-b89c-76232d66e00e\bom_arrow_preview.html`

---

## 4. Remaining Items to Discuss (Next Steps)
1. **Tab 4 (Additional Cost Points):** User has more points on costing to discuss (e.g., `unit_cost` vs `cost_price`, live vs snapshot material cost, cost-per-unit display, decimal precision, subcontracting cost card, setup cost inclusion/exclusion in unit total, `calc_qty` scaling, etc.).
2. **Tab 2 small confirmation:** Whether `OP-020` alternative-operation material rows should stay omitted from the default Level 2 list (recommended: keep Level 2 clean with the 6 primary components, or show a subtle info note).
3. **Tab 5 (`Audit Log & History`):** Review requirements and UI improvements.
4. **Tab 6 (`Where Used`):** Review requirements and UI improvements.
5. **Implementation Phase (Only after all tabs are finalized & approved):**
   - Apply changes to `ProductionCostService`, `ProductionBomController`, `ProductionBomRepository`, `BomExplosionService` (if needed), and `show.blade.php`.
   - Write/update feature tests covering `BOM-TEST-FINAL-01`-style multi-level + alternative-op + alternative-material + setup/per-piece scenarios.
