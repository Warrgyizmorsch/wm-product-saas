# BOM — Final Decisions & Business Rules

> **Purpose:** The single, final source of agreed BOM decisions (identity & lifecycle, 2-tier structure, BOM form, show page, costing, import / API).
> Decisions are reviewed one at a time (BM-01 … BM-31) and written here only once agreed.
> **Source documents reviewed:** `docs/bom_routing_feature_exchange_tracker.md` (TRACK-01, 02, 04, 05, 10, 11, 12, 14, 15), `docs/bom_routing_architecture_map.md`; the formula-drop decision raised at the start of the review; BOM parts carried over from the Routing review (RT-IMPL-05, 09, 13, 14).
> **Depends on:** [`docs/work_center_decisions.md`](work_center_decisions.md), [`docs/routing_decisions.md`](routing_decisions.md).
> **Implementation tasks:** [`docs/bom_implementation_tasks.md`](bom_implementation_tasks.md)

---

## Review Progress

> **Status note:** 🔁 **Provisional** = rules look right today but are marked to be revisited and finalised later.

| ID | Topic | Status |
| :--- | :--- | :--- |
| BM-01 | BOM links one product to one routing (routing mandatory; any draft / approved / active routing selectable) | ✅ Agreed (fix → BM-IMPL-01) |
| BM-02 | BOM lifecycle & approval (statuses, submit / approve gates, automatic supersession, daily date job) | ✅ Agreed (fix → BM-IMPL-02) |
| BM-03 | Duplicate / Revise = pre-filled **create** form (no DB row until Save), faithful copy, routing & deleted-operation checks | ✅ Agreed (fix → BM-IMPL-03) |
| BM-04 | Two tables: allocations entered, master built on save (primary-only qty, alternative qty, gross qty, one UoM per material) | ✅ Agreed (fix → BM-IMPL-04) |
| BM-05 | Every allocation on an operation of the BOM's own routing (required, tenant-scoped, not deleted; API + import too); empty operations allowed | ✅ Agreed (fix → BM-IMPL-05) |
| BM-06 | Duplicate rule: material once as primary per operation; may also be an alternative, but only once per primary it replaces; all paths; finish UI bug fix | ✅ Agreed (fix → BM-IMPL-06) |
| BM-07 | Component alternatives: same operation, required unique priority, own qty, alternative operations = alternative demand, all paths | ✅ Agreed (fix → BM-IMPL-07) |
| BM-08 | Sub-BOM: picked once per material in Tab 2 (UI), stored per allocation (backend unchanged); active / scheduled states with warnings; inactive sub-BOM → parent warning, no new production | ✅ Agreed (fix → BM-IMPL-08) |
| BM-09 | Fixed quantities only — formula feature and production parameters dropped (no data conversion) | ✅ Agreed (fix → BM-IMPL-09) |
| BM-10 | Scrap %: per allocation + gross qty decided (BM-04); full scrap rules and Routing yield connection deferred | 🟢 Partly agreed · ⏭️ rest deferred (→ BM-DEF-01) |
| BM-11 | Master quantity & role | ✅ Covered by BM-04 |
| BM-12 | Form layout: one card per operation; header shows saved routing figures (RT-16 / RT-25); Op number in notice; translations; shared create/edit partial | ✅ Agreed (fix → BM-IMPL-10) |
| BM-13 | Card header badge: primary / alternative (substitutes Op x, priority); cards stay in sequence order | ✅ Agreed (no new task; translations in BM-IMPL-10) |
| BM-14 | Changing the routing with materials entered: confirm, then clear all materials (edit: "nothing changes until save") | ✅ Agreed (small UI parts in BM-IMPL-10) |
| BM-15 | Live material rollup tab: BM-04 columns (qty, alt qty, gross), sub-BOM picker, same rules as server, no rounding, drawer + Jump to Op Card | ✅ Agreed (fix → BM-IMPL-11) |
| BM-16 | Draft recovery after F5: tenant + user key, stale-edit guard, separate copy key, 7-day expiry | ✅ Agreed (fix → BM-IMPL-12) |
| BM-17 | Deleted operation card: RT-10 / RT-IMPL-09 rules + Move to Op, no silent drop, pending BOM approval blocked, show page card | ✅ Agreed (fix → BM-IMPL-13 + RT-IMPL-09 7–11) |
| BM-18 | Live routing advisory: not active / operation removed (block approval) / routing outdated (info); stored warning = components only | ✅ Agreed (fix → BM-IMPL-14 + RT-IMPL-05) |
| BM-19 | Show page Components tab: direct materials only, BM-04 figures, allocation drawer, no sub-BOM column, no rounding | ✅ Agreed (fix → BM-IMPL-15) |
| BM-20 | Explosion tab: explode the viewed BOM, follow chosen sub-BOMs, gross primary demand, alternatives info only, status badges | ✅ Agreed (fix → BM-IMPL-16) |
| BM-21 | Material cost: live vs saved (TRACK-15 vs RT-19); cost field; sub-BOM material cost | ⏭️ Deferred (→ BM-DEF-02) |
| BM-22 | Master row figures across operations (effective scrap % display) | 🟢 Qty part agreed (BM-IMPL-17) · ⏭️ cost part → BM-DEF-02 |
| BM-23 | Alternatives excluded from all totals, badged | 🟢 Qty part agreed (BM-IMPL-17) · ⏭️ cost part → BM-DEF-02 |
| BM-24 | Drawer rows: net, scrap %, gross per allocation | 🟢 Qty part agreed (BM-IMPL-17) · ⏭️ cost share → BM-DEF-02 |
| BM-25 | BOM routing cost (base qty × saved Cost / Unit + setup per run; primary ops; overhead excluded) | ⏭️ Deferred (→ BM-DEF-02, all costing) |
| BM-26 | Material cost = gross qty × unit cost, primary only | ⏭️ Deferred (→ BM-DEF-02, all costing) |
| BM-27 | BOM import: same service + validator, one row per allocation, routing required (no product match), draft-only update by number + version | ✅ Agreed (fix → BM-IMPL-18) |
| BM-28 | BOM API: form-equal requests + checks, header-only update keeps allocations, two-tier response | ✅ Agreed (fix → BM-IMPL-19) |
| BM-29 | Downstream use of BOM data (where-used, MRP, orders, plans, ECO) | ⏭️ Deferred (→ BM-DEF-03) |
| BM-30 | Tests: per task, removed-design tests deleted, full run at the end (passing = Applied) | ✅ Agreed |
| BM-31 | Final cleanup: drop master scrap column, remove under_revision, covered leftovers | ✅ Agreed (fix → BM-IMPL-20, do last) |

---

## A. BOM Identity & Lifecycle

### BM-01: One Product + One Routing
1. A BOM states the **product** it makes (`production_boms.product_id`) and the **Routing** it uses (`production_boms.routing_id`). **Both are mandatory.**
2. The BOM form lists **all draft, approved and active** Routings of the tenant — no product filter (RT-01). Edit also keeps the BOM's current Routing in the list.
3. The backend accepts only a Routing of the **same tenant** in an allowed status (draft / pending / active / scheduled); **historical and cancelled Routings cannot be chosen** for a BOM being saved (RT-02 rule 3).
4. Deleting a Routing that BOMs use is blocked (FK `restrictOnDelete`).
5. Same rules on every entry path — form, API, import.
→ Task **BM-IMPL-01**.
* *Code reference:* `StoreProductionBomRequest.php` / `UpdateProductionBomRequest.php` 50–51 (`product_id` required, `routing_id` `required|integer|exists:routings,id` ✔ — not tenant-scoped, no status check ✘); `Requests/Api/StoreBomApiRequest.php` 19, `UpdateBomApiRequest.php` 15 (`routing_id` **nullable** ✘); DB column `production_boms.routing_id` **nullable** with `restrictOnDelete` (migration `2026_10_31_120000`, 16–22); BOM form lists `ProductionBomController.php` 220, 263–270 ✔.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-01; confirmed 2026-11-11.

### BM-02: BOM Lifecycle & Approval
1. **Statuses:** draft → pending approval → **approved** (the "active" status, `STATUS_ACTIVE = 'approved'`), or **scheduled** when its start date is in the future. Reject sends it back to **draft**. Superseded or expired → **inactive**. Cancel → **cancelled**. `under_revision` is kept as an editable status *(superseded by BM-31: status removed)*.
2. Only **draft** and **under_revision** BOMs can be edited (`ProductionBomPolicy.php` 33 ✔).
3. **Submit** needs **at least one material**. The audit row records the real user, or "system" — never user 1.
4. **Approve** needs the BOM's Routing to be **active**, or **scheduled with a start date on or before the BOM's start date**. The non-existent routing status `'approved'` is removed from the check.
5. **One approved BOM per product + BOM type.** Approving a new version automatically retires the previous one: start today → old BOM becomes **inactive** (audit "Deactivated"); start later → old BOM keeps approved and gets an **expiry date** = new start (audit "Scheduled Expiry"), new BOM becomes **scheduled**. This differs from Routings on purpose (Routings have no product). Late approval moves the start date to today with a note (same as Routings).
6. A **daily job** (same pattern as RT-03): scheduled → approved on the start date; approved past its expiry date → inactive; each with an audit row.
→ Task **BM-IMPL-02**.
* *Code reference:* `ProductionBomService.php` `submitApproval()` 145–174 (no material check ✘, `auth()->id() ?: 1` ✘), `approve()` 175–245 (routing gate `[active, scheduled, 'approved']` ✘ — no start-date check); `BomLifecycleService.php` `transitionOnApproval()` 100–200 ✔; `ProductionBom.php` `scopeActive()` 185–195 / `isCurrentlyActive()` use dates ✔ but stored status never moves ✘ (no job).
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-01 #3, TRACK-06 #4; architecture map rule 9; confirmed 2026-11-11.

### BM-03: Duplicate & Revise a BOM
1. **Revise** (next version number suggested automatically) and **Duplicate** (user types the version) both start a **new draft** of the same product: revision = source revision + 1, start date = today. The source BOM is not changed and stays approved until the new version is approved and replaces it (BM-02 rule 5).
2. **No database entry is created by the button.** It opens the BOM **create form pre-filled** from the source BOM (not the edit form). The new BOM, its items, allocations and the "Revision Created" audit row are written **only when the user saves**. Cancel leaves nothing behind. The suggested version is editable and checked for clashes on save.
3. **Faithful copy:** all **allocations** are pre-filled with every field (operation, quantity, UoM, scrap, alternative link, priority, sequence, notes, sub-BOM). The master list is **not** copied — it is rebuilt from the allocations on save (BM-04). Formula fields are not copied (BM-09). *(Corrected 2026-11-11: master rows no longer have alternative / priority / sequence / notes columns — dropped by migration `2026_11_05_170000`.)*
4. **Routing:** pre-filled only if still allowed (BM-01). Otherwise the form opens with the warning *"Routing RTG-X is {status} — choose another routing"* and cannot be saved until it is changed.
5. **Allocations on deleted operations** are pre-filled but shown as the RT-10 "deleted operation" card; the BOM cannot be saved until they are fixed. Never dropped silently.
6. The version clash check is **limited to the tenant**.
7. `under_revision` is not used for copies; existing rows stay readable; removal of the status waits for cleanup. *(Decided in BM-31: status removed — BM-IMPL-20.)*
8. The **API** duplicate / revision endpoint follows the same rule: it returns the pre-filled copy data **without saving**; the client then calls the normal create endpoint.
→ Task **BM-IMPL-03**.
* *Code reference:* `ProductionBomController.php` `createRevision()` 96–130, `duplicateVersion()` 496–519 (both save immediately and redirect to **edit** ✘); `ProductionBomService.php` `duplicateVersion()` 335–457 (routing copied unchecked ✘, deleted operations copied ✘, master rows copied with `replaces_item_id` set on columns that no longer exist 375–393 (dead code ✘ — remove), formula copied ✘), `checkBomConflicts()` 673–686 (`withoutGlobalScopes()`, no tenant filter ✘); `Api/ProductionBomApiController.php` ~230–260 (saves immediately).
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-10; confirmed 2026-11-11 with the addition "fill the create form instead of creating a DB entry".

## B. 2-Tier Structure

### BM-04: Two Tables — Allocations Entered, Master Built on Save
1. The user enters **allocations** (`production_routing_operation_materials`: material per operation). The **master list** (`production_bom_items`: one row per material per BOM) is **always built from the allocations on save** — never typed directly. While the BOM is a draft, both tables are rebuilt on every save.
2. **Master `quantity` = sum of primary allocations only.** Alternatives replace a primary, so they do not add. A material used only as an alternative gets master quantity **0** and role `alternative`. (Role primary / alternative / mixed kept.)
3. **New nullable column `alternative_quantity`** on the master row = sum of the material's alternative allocations (possible usage), so readers can show it.
4. **Scrap stays per allocation.** **New column `gross_quantity`** on the master row = Σ primary allocation quantity × (1 + scrap % / 100), stored at ≥ 4 decimals, no rounding. MRP, explosion, costing and requirements use **gross**; the master `material_scrap_percentage` (always 0 today) is no longer used.
5. **One UoM per material within a BOM.** Save is blocked when allocations of the same material use different units: *"Material X uses kg and g in this BOM — use one unit."*
6. **Readers use master rows for totals and allocations for per-operation detail**; none recalculates totals from raw rows on its own.
→ Task **BM-IMPL-04**.
* *Code reference:* `ProductionBomService.php` `persistBomItems()` 467–620 — sums **all** allocations incl. alternatives 494 ✘, first allocation's UoM 492 ✘, master scrap hard-coded 0.00 518 ✘, rebuild on save 131 / 524 ✔, auto-picks newest approved sub-BOM 538–551 (→ BM-08); readers of master items: `BomExplosionService.php` 107, `MrpEngineService.php` 87, `MrpShortageService.php` 287, `ProductionCostService.php` 18 / 132, `ProductionEcoImpactAnalysisService.php` 162–163, `ProductionBomService::calculateRequirements()` 650.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-14 §4; confirmed 2026-11-11.

### BM-05: Every Allocation on an Operation of the BOM's Own Routing
1. **Every allocation has an operation** (`routing_operation_id` required), and it must belong to the **BOM's own Routing** — main or alternative operations.
2. **Operations with zero materials are allowed**; there is no rule that every operation must use a material.
3. The check is **tenant-scoped**, and an operation **soft-deleted** from the Routing is not valid — it shows as the RT-10 "deleted operation" card and blocks save.
4. The **API** requires `items.*.routing_operation_id` with the same checks as the form.
5. The **import file** gets an operation column (operation sequence / code within the BOM's Routing); missing or wrong operation → row error *"Row N: operation {x} is not in routing RTG-Y."* Full import layout in BM-27.
→ Task **BM-IMPL-05**.
* *Code reference:* `StoreProductionBomRequest.php` 61 (`required|integer|exists:production_routing_operations,id` — not tenant-scoped ✘), 82–120 (belongs-to-routing check ✔, only when routing loads); `UpdateProductionBomRequest.php` 62, 115–125 (same); `Requests/Api/StoreBomApiRequest.php` 15–40 / `UpdateBomApiRequest.php` — **no** `routing_operation_id` rule ✘; `ProductionMasterImportService.php` item array 297–305 has no operation ✘, saves `routing_operation_id => $item['routing_operation_id'] ?? null` 713 / 762 (always null) ✘.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-05 #1, #2, #4; confirmed 2026-11-11.

### BM-06: Same Material in Several Operations — Duplicate Rules Inside One Operation
1. The **same material may be used in several operations** of one BOM; its master row sums the primary allocations (BM-04).
2. **Inside one operation** (main or alternative operation, each checked on its own):
   * a material may be a **primary only once** — key `routing_operation_id + material_id` among primary rows;
   * the same material **may also appear as an alternative** in that operation, even when it is already a primary there;
   * a primary may have a given material as its alternative **only once** — key `routing_operation_id + replaced primary row + material_id`; another primary in the same operation may also use that material as its alternative;
   * a material can **never be an alternative of itself**.
   * *Example (Op 10):* `MT-1` 10 primary ✔, `MT-2` 30 primary ✔, `MT-1` 5 alternative of `MT-2` ✔, `MT-1` alternative of `MT-3` ✔; a second `MT-1` primary ✘; `MT-1` again as alternative of `MT-2` ✘; `MT-2` as alternative of `MT-2` ✘. Master row for `MT-1`: quantity 10, alternative_quantity 5, role mixed (BM-04).
3. The **same checks run on form (server + browser), API and import**. Messages: *"Material X is already a primary material in operation {seq}."* / *"Material X is already an alternative for {primary} in operation {seq}."* / *"A material cannot be an alternative of itself."* Import: *"Row N: …"*.
4. **Finish the UI bug fix** (TRACK-05 §5): `clearOdooError()` must not hide Alpine-managed error text (`[x-text]`), so the red outline and the message always show together.
→ Task **BM-IMPL-06**.
* *Code reference:* `StoreProductionBomRequest.php` 128–135 / `UpdateProductionBomRequest.php` same block — key `{$materialId}_{$opId}` ignores primary / alternative ✘ (blocks the allowed example); `bom/create.blade.php` `validateDuplicateMaterials()` ~1860–1925, `bom/edit.blade.php` ~1915–1980 — same key in browser ✘, feedback refresh 1921 / 1974 ✔; `components/ui/odoo-form-ui.blade.php` `clearOdooError()` 675 — still hides Alpine feedback ✘; API / import — no check ✘.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-05 #3, §5; changed and confirmed 2026-11-11.

### BM-07: Component Alternatives
1. An alternative must name the **primary it replaces**, and that primary must be **in the same operation**. Kept: primary must exist in this BOM, a row cannot point to itself, alternatives cannot chain (alternative → alternative).
2. **Priority is required for alternatives and unique per primary** (1 = first choice, then 2, …). Two alternatives of the same primary cannot share a priority. Primaries have no priority.
3. An alternative has **its own quantity and UoM** — the amount needed to fully replace its primary. One UoM per material (BM-04 rule 5) applies.
4. **Alternative operations:** every material in an alternative operation counts as **alternative demand** (`alternative_quantity`, BM-04). Inside that operation, primaries and alternatives follow the normal rules.
5. **Removing a primary in the form** asks for confirmation and removes its alternatives with it — no alternative is left pointing at nothing.
6. Same checks on **form, API and import**. Import gets columns **"alternative of"** (primary material code) and **"priority"** (layout in BM-27).
→ Task **BM-IMPL-07**.
* *Code reference:* `StoreProductionBomRequest.php` 150–185 / `UpdateProductionBomRequest.php` same — primary required ✔, found ✔, not self ✔, no chain ✔, **no same-operation check** ✘; `priority` `nullable|integer|min:1`, defaulted to 1 in `ProductionBomService.php` 556 ✘, not unique ✘; Pass 2 linking 577–605 ✔; alternative-operation demand `$altOpIdsMap` 473–478 / 495 ✔; API — fields accepted, no checks ✘; import 297–305 — no alternative columns ✘.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-04 #5, TRACK-14; confirmed 2026-11-11.

### BM-08: Sub-BOM (Child BOM) — One Choice Per Material
1. **Storage unchanged:** `child_bom_id` stays on **each allocation** (`production_routing_operation_materials`); the backend receives it per allocation as today. `production_bom_items` gets no sub-BOM column.
2. **UI only:** the BOM form shows **one sub-BOM picker per material in Tab 2** (master list), never per allocation, so the user does not see repeated data. On save the form writes that choice into **every allocation of that material**. Default = the material's current active BOM, pre-selected.
3. **Consistency (server check):** all allocations of one material in a BOM must carry the **same** `child_bom_id` — otherwise *"Material X has different sub-BOMs in this BOM — choose one."* (Guards API / import.) This prevents Op 10 using v1 and Op 20 using v2.
4. **Selectable sub-BOMs** (same tenant, same product as the material, manufactured materials only):

   | Sub-BOM state | Selectable | Warning while editing the draft |
   | :--- | :-: | :--- |
   | Active (approved, no expiry) | ✔ | — |
   | Active but **scheduled to be deactivated** (approved with expiry date) | ✔ | *"This sub-BOM will be deactivated on {date}. After that you will have to create a new version of this BOM."* |
   | **Scheduled to be activated** (future start) | ✔ | *"This BOM can only become active after the sub-BOM activates on {date}. Its start date will move to {date} and on approval it will go to Scheduled."* |
   | Inactive / cancelled / draft / pending | ✘ | not listed |

5. **Approval:** parent start date = the latest of its own start and the start of any scheduled sub-BOM; if in the future → **Scheduled** (BM-02). Approval is blocked if a chosen sub-BOM has meanwhile become inactive / cancelled.
6. **Sub-BOM becomes inactive or cancelled after the parent is approved** (same pattern as the Routing → BOM warning): the parent gets a **warning** *"Sub-BOM {bom_number} v{x} for {material} is {status} — create a new version of this BOM."* The parent's **status does not change** and nothing is blocked, **except** it **cannot be used for new production** (new production plans / orders) until a new version is approved. Production orders already started continue with it.
7. Kept checks (`validateChildBomLink()`): same product, not itself, no circular link, dates fit; plus tenant + status (rule 4).
8. **No guessing:** the save no longer auto-picks a sub-BOM silently (the form pre-selects visibly instead), and readers do not substitute their own pick; they read the allocation's `child_bom_id`. A material with no sub-BOM is not exploded further.
→ Task **BM-IMPL-08**.
* *Code reference:* save `ProductionBomService.php` 532–571 (per allocation ✔), silent auto-pick 538–556 ✘; `validateChildBomLink()` 689–725 (no tenant / status check ✘); readers use the **master row**, whose `child_bom_id` was dropped (migration `2026_11_05_170000` 44–66) → always null ✘: `BomExplosionService.php` 129 (own pick 66–75), `MrpEngineService.php` 130 (own pick 133–139), `ProductionOrderService.php` 504 / 510; `detectCircularChildLink()` 737–744 walks master rows ✘; queries on the dropped column ✘: `ProductionOrderService.php` 157, `ProductionBomService::updateParentBomWarningsForComponent()` 750–758 (called on approve 235 / cancel 326); `ProductionBomItem.php` fillable still lists dropped columns ✘.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-14 table 2; decided 2026-11-11 (UI-only single choice, per-allocation storage, warning-only cascade).

### BM-09: Fixed Quantities Only — Formula Feature Dropped
1. **BOM quantities are fixed numbers only** (per the BOM's base quantity). No formulas, no production parameters.
2. **Remove the feature completely:** form UI (toggle, formula input, preview), show-page display, preview route + `BomFormulaController` + `BomFormulaEvaluatorService`, request rules, DTO fields, model methods (`isFormula()` / `isFixed()`), formula branches in every reader, ECO formula comparison, demo seeder formula row, parameterized-BOM tests, translation keys.
3. **Database:** drop `quantity_type` and `formula` from `production_bom_items` and `production_routing_operation_materials`; drop `production_orders.parameters`; remove the `$parameters` arguments in explosion / MRP / order services.
4. **Existing data:** no conversion — current data is demo / test data only.
5. **API rejects** `quantity_type` / `formula` if sent: *"Formula quantities are not supported; send a fixed quantity."* Import has no formula column (no change).
6. Old docs (`bom_routing_feature_exchange_tracker.md`, `bom_routing_architecture_map.md`) get a note: formula feature dropped by BM-09.
→ Task **BM-IMPL-09**.
* *Code reference:* feature is already half dead — readers test `isFormula()` on **master rows**, but the save never writes `quantity_type` / `formula` to master rows (`ProductionBomService.php` 510–519), so entered formulas are ignored today. Full file list in BM-IMPL-09.
* *Origin:* decision raised by the user at the start of the review (not in any earlier doc); confirmed 2026-11-11.

### BM-10: Scrap % — Partly Decided, Rest Deferred
* **Decided (BM-04 rule 4):** scrap % is entered **per allocation**; the master row carries `gross_quantity` = Σ primary quantity × (1 + scrap % / 100); the master `material_scrap_percentage` is no longer used.
* **⏭️ Deferred for detailed discussion and check (→ BM-DEF-01):**
  1. Full scrap rules (meaning of scrap per allocation, limits, how it shows on Tab 2 / show page / costing).
  2. **Connection with the Routing's yield** (`expected_yield_percentage` on routing operations, RT-16: yield applies to per-piece time) — whether material scrap and operation yield overlap, stack, or must be kept apart, and how input quantity = output / yield (`RoutingOperation.php` ~220) relates to BOM gross quantity.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-14, TRACK-15 Q1; marked 2026-11-11.

### BM-11: Master Quantity & Role — Covered by BM-04
* Master `quantity` = sum of **primary** allocations; alternatives → `alternative_quantity`; role primary / alternative / mixed (BM-04 rules 2–3). The column is **`quantity`** — the old doc's `total_quantity` is wrong (doc note only, no task).

## C. BOM Form

### BM-12: Form Layout — One Card Per Operation
1. **Layout kept:** one card per operation of the BOM's Routing, **sorted by sequence**, materials inside the card, a zero-material notice, expand / collapse per card and for all cards.
2. **Card header shows the Routing's saved figures:** setup per run and setup per piece (RT-16), run time per unit, yield; times in the RT-25 format ("< 1s" + minutes); costs never rounded.
3. **Zero-material notice names the operation:** *"…Add Material to Op 20…"*.
4. **All card text is translatable** (en / bg / hi).
5. **Create and edit forms share one Blade partial and one script**, so each rule is written once; edit only adds loading the existing BOM. Done **before** the other BOM UI tasks.
→ Task **BM-IMPL-10**.
* *Code reference:* `bom/create.blade.php` 212–600 (cards ✔), sort 1330 ✔, collapse 219 / 955 ✔, header times `setup_time_minutes + 'm'` / `processing_time_minutes + 'm/unit'` ~276–284 ✘ (no per-piece setup, no RT-25 format), zero notice `['seq' => '']` 583 ✘, hard-coded English ("Material(s)", "materials configured", "operations", "Substitutes Op", "Setup", "Run", "Priority") ✘; `bom/edit.blade.php` same at +10–35 lines; files 2,153 / 2,206 lines, near duplicates ✘.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-04 Option A; confirmed 2026-11-11.

### BM-13: Operation Card Header — Primary or Alternative
1. Badges kept: **"Primary Operation"**, or **"Alternative Operation · Substitutes Op {x} · Priority {n}"** (translated, BM-IMPL-10 #4).
2. Cards stay in **plain sequence order** (an alternative operation is not moved next to the operation it replaces).
* *Code reference:* `bom/create.blade.php` 262–276, `bom/edit.blade.php` 273–287 ✔.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-04 Issue A; confirmed 2026-11-11.

### BM-14: Changing the Routing After Materials Are Entered
1. Kept: when materials are entered, changing the Routing **asks for confirmation, then clears all materials** and loads the new operations; Cancel restores the previous Routing. No automatic matching to the new operations. No materials entered → change applies directly.
2. On the **edit** form the message adds *"Nothing is changed until you save."*
3. Dialog buttons ("Yes, Change Routing", "Cancel") translated.
→ UI parts in **BM-IMPL-10** #6.
* *Code reference:* `bom/create.blade.php` 1482–1561, `bom/edit.blade.php` 1520–1600 ✔; message `lang/en/production.php` 72–73; server rejects allocations not on the new Routing (BM-05).
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-04 Issue B; confirmed 2026-11-11.

### BM-15: Live Material Rollup Tab (Tab 2)
1. Tab kept: **one row per material**, updated live; a **drawer per material** lists every allocation (Op no. + name, alternative-operation info, substitutes, priority, quantity, scrap %, notes) with **"Jump to Op Card"**.
2. **Columns follow BM-04:** material, type, role, **quantity (primary only)**, **alternative quantity**, **gross quantity**, UoM (**once**), and the **sub-BOM picker** (BM-08).
3. The browser uses **exactly the same rules as the server build** (BM-04), so the saved master list equals what the user saw; the server stays the source of truth.
4. **No rounding:** quantities shown with ≥ 4 decimals.
5. `quantity_type` display removed (BM-09); all texts translated.
* Costs and the open drawer questions (BM-21 … BM-24) are not part of this decision.
→ Task **BM-IMPL-11**.
* *Code reference:* `bom/create.blade.php` tab 600–790, `summarizedMaterials` ~985–1025 (total adds alternatives 1022 ✘), `toFixed(2)` on row and drawer quantities ✘, no gross ✘, UoM twice ✘, drawer `alloc.quantity_type` ✘, hard-coded English ✘; `jumpToOpCard()` 1055 ✔; `bom/edit.blade.php` same.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-14; confirmed 2026-11-11.

### BM-16: Draft Recovery After F5 (Browser Storage)
1. Kept: **autosave to browser storage**; on reload the form is restored with the banner *"Draft restored ({time}) · Discard draft"*; **7-day** expiry; cleared on successful save; server-returned input (validation error) wins over the draft.
2. **Draft key includes tenant and user:** `bom_draft_{tenant}_{user}_create` / `…_edit_{bom_id}`.
3. **Edit:** the draft stores the BOM's `updated_at`; restored only if it still matches — otherwise discarded with *"This BOM was changed after your draft was saved; the draft was discarded."*
4. **Pre-filled copy (BM-03)** uses its own key `…_copy_{source_bom_id}` and never mixes with a normal create draft; on first open the pre-fill wins.
5. Formula fields removed from the draft (BM-09); texts translated.
→ Task **BM-IMPL-12**.
* *Code reference:* `bom/create.blade.php` banner 30–47 (hard-coded English ✘), `getSavedDraft()` / `saveDraft()` / `restoreFromDraft()` 1107–1300 (stores `quantity_type` / `formula` ✘), key `bom_create_draft_v1` 914 ✘, clear on submit 1605 ✔, `_hasServerOldInput` 920 ✔; `bom/edit.blade.php` key `bom_edit_draft_v1_{{ $bom->id }}` 947 ✘, no `updated_at` check ✘.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-14; confirmed 2026-11-11.

### BM-17: Deleted Operation Card
1. **Adopts RT-10 and RT-IMPL-09 rows 7–11 as written** (soft-deleted operation, allocations kept, live advisory, "Deleted" card on BOM edit, save / submit / approve rejected until fixed, card gone once fixed). Not duplicated here.
2. **Who is affected:** only **draft and pending** BOMs (a Routing can lose operations only while editable; a BOM is approved only on an active Routing — BM-02 rule 4). A **pending** BOM that is hit cannot be approved until it is sent back to draft and fixed.
3. On the deleted card each material row has a **"Move to Op …"** selector (the Routing's live operations) and **Remove**. Moving into an operation that already has that material follows the BM-06 duplicate rules.
4. The **silent drop** of rows whose operation is not in the list is removed — such rows always show on the deleted card.
5. The **BOM show page** also shows the deleted card (read-only, tagged) plus the advisory.
→ Task **BM-IMPL-13** (+ RT-IMPL-09 rows 7–11).
* *Code reference:* not implemented — routing save force-deletes operations and FK cascades (RT-IMPL-09 rows 1–3); BOM form lists live operations only `bom/create.blade.php` 1330, silent filter 1335 ✘ (edit same).
* *Origin:* RT-10 (routing review); confirmed 2026-11-11.

### BM-18: Live Routing Advisory on the BOM
1. **One live routing advisory per BOM** — computed, never stored. It lists any of:
   * **a. Routing not active** — *"Linked routing RTG-X is {status}. This BOM can't be approved until the routing is active."* (RT-04)
   * **b. Operation removed** — *"Operation OP-XXX was removed from routing RTG-X. Move or remove its materials."* (RT-IMPL-09 #7, BM-17)
   * **c. Routing outdated** (inactive resource RT-29, or section snapshot changed RT-19 rule 5) — *"Linked routing RTG-X has outdated data ({reason}). BOM costs use the routing's saved figures; create a new routing version to update."*
2. The stored warning (`has_warning`, `warning_reason`) is for **component warnings only** — including the BM-08 "sub-BOM inactive" warning.
3. **Shown:** BOM form (live, on routing choice and on load); show page banner separate from component warnings; icon + tooltip in the BOM list; `routing_advisory` in the API.
4. **Only a and b block approval** (BM-02 rule 4, BM-17). **c is information only** — an approved BOM keeps working on the routing's saved figures.
→ Task **BM-IMPL-14** (extends RT-IMPL-05).
* *Code reference:* routing lifecycle writes into the BOM warning field (`RoutingLifecycleService.php` 222–228, 268–275, 293–297), overwritten by `ProductionBom::refreshWarningStatus()` 388–409 from `ProductionBomService.php` 136 / 232 ✘; no live advisory ✘.
* *Origin:* RT-04, RT-19, RT-29, RT-IMPL-05 / 09 / 14 (routing review); confirmed 2026-11-11.

## D. BOM Show Page

### BM-19: Components Tab — This BOM's Direct Materials Only
1. The **Components** tab shows **this BOM's direct materials only** — one row per master row. The recursive child-BOM tree is **removed** from this tab.
2. Row figures follow BM-04 / BM-15: material, type, role, quantity, alternative quantity, gross quantity, UoM. **No sub-BOM column** — sub-BOMs are seen in the **Multi-Level BOM Explosion** tab (BM-20). Cost columns wait for BM-21 … BM-26.
3. Each row expands into an **allocation drawer** (same as form Tab 2): Op no. + name, alternative-operation info, substitutes, priority, quantity, scrap %, notes, and a **"View in Routing tab"** link to that operation.
4. Reads of **dropped master columns** (`sequence`, `notes`, `is_alternative`, `replaces_item_id` / `primaryItem`, `childBom`) and formula fields are removed; alternative / notes / sequence come from the allocations.
5. **No rounding** — quantities ≥ 4 decimals.
→ Task **BM-IMPL-15**.
* *Code reference:* `bom/show.blade.php` 330–478 — recursive `renderComponentTreeRows()` 349–466 (also defined ~50–108) ✘, no drawer ✘, reads dropped master columns + `quantity_type` / `formula` ✘, scrap / gross from master scrap (always 0) ✘, `number_format(…, 2)` ✘.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-15 Option A; confirmed 2026-11-11 (no sub-BOM column).

### BM-20: Multi-Level BOM Explosion — Own Tab
1. The explosion stays in its **own tab** with the `calc_qty` input.
2. It explodes **the BOM being viewed** (by its id, any status). Below level 1 it follows each material's **chosen sub-BOM** (BM-08); a material without a sub-BOM is a leaf.
3. Quantities use **gross primary demand** (BM-04) at every level. **Alternatives are not exploded and not added to totals** — listed under their material as *"Alternative: X (qty)"* for information.
4. Each sub-BOM row shows its **status**; inactive / cancelled / scheduled sub-BOMs get a badge with the BM-08 warning text.
5. **No rounding** (≥ 4 decimals). Costs in this tab follow BM-21 … BM-26.
→ Task **BM-IMPL-16**.
* *Code reference:* `ProductionBomController.php` 141 `explode($bom->product_id, …)` → `BomExplosionService::explode()` 14–37 with `forcedBomId = null` (explodes the product's active BOM, not the viewed one ✘); `resolveChildren()` 100–135 uses master `quantity` (sums alternatives ✘), master scrap (0 ✘), master `child_bom_id` (dropped ✘), `is_alternative` / `replaces_item_id` (dropped ✘), formula branch ✘; tab `bom/show.blade.php` 479–624.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-15; confirmed 2026-11-11.

## E. BOM Costing

### BM-21: Material Cost — Live or Saved — ⏭️ DEFERRED
* **Deferred for later discussion (→ BM-DEF-02).** Nothing changes until decided.
* **Conflict:** TRACK-15 says material costs are calculated **live** from `products.unit_cost`, never saved; RT-19 (routing) says **calculate once, save, warn when outdated**, and BOM costing reads the routing's saved figures — so today the BOM cost is half saved (routing) and half live (materials).
* **Options on the table:** A — keep live; B — save `unit_cost` + material cost per master row on BOM save and again on approval, outdated warning when the product cost changes (recommended, same as RT-19); C — saved cost official + "current cost" variance column.
* **Sub-questions:** (1) which product field is the material cost — `unit_cost` (used today) or `cost_price` (`Inventory/Models/Product.php` 47, 52); (2) a material with a sub-BOM — product `unit_cost` or the sub-BOM's saved total cost (material + routing).
* *Code reference:* `ProductionCostService::calculateMaterialCost()` 13–29 and `ProductionBomController::show()` 149–162 read `material->unit_cost` live; also use dropped `is_alternative` and master scrap (fixed by BM-IMPL-04).
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-15; RT-19; deferred 2026-11-11.

### BM-22 / BM-23 / BM-24: Master Row Figures, Alternatives in Totals, Drawer Figures (TRACK-15 Q1–Q3)
**Quantity parts — agreed:**
1. **BM-22 (Q1):** master net (`quantity`) and `gross_quantity` as BM-04; the master row also shows **effective scrap % = (gross − net) ÷ net × 100** — display only, ≥ 4 decimals, never stored; the master `material_scrap_percentage` column is not used.
2. **BM-23 (Q2):** alternatives are **excluded from every total** (quantity and cost). Alternative quantity is shown with the badge *"Alternative — not in total"* on the master row and in the drawer.
3. **BM-24 (Q3):** each drawer allocation row shows quantity (net), scrap %, and **gross = qty × (1 + scrap % / 100)**, ≥ 4 decimals.

**Cost parts — ⏭️ deferred with BM-DEF-02:** net / gross cost of the master row, an alternative's own cost, operation cost share in the drawer.
→ Task **BM-IMPL-17**.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-15 §3 Q1–Q3; confirmed 2026-11-11.

### BM-25 / BM-26: BOM Routing Cost & Material Cost — ⏭️ DEFERRED
* **All BOM cost decisions are deferred and decided later (→ BM-DEF-02).** Nothing about costing changes until then.
* **Proposal on the table for BM-25 (from RT-16 / 17 / 18 / 19, RT-IMPL-13 §3, RT-IMPL-14):** BOM routing cost = base quantity × Σ saved Cost / Unit + Σ saved setup cost per run, primary operations only, never recalculated; labor / machine split shown; overhead excluded (*"Excludes facility overhead."*); BOM cost = material + routing; cost per unit of product = BOM cost ÷ base quantity (≥ 4 decimals); BM-18 (c) advisory beside the cost.
* **BM-26 (map 5.5):** material cost = gross quantity × unit cost, primary components only — depends on BM-21.
* *Today:* `ProductionCostService.php` 34–110 recalculates `(setup + run) × rate × base_quantity × yield` (setup multiplied by quantity; saved routing figures not read); material cost 13–29 live from `unit_cost`.
* *Origin:* RT-16 … RT-19; `bom_routing_architecture_map.md` 5.5; deferred 2026-11-11.

## F. Import / API

### BM-27: BOM Import
1. Import uses the **same service and shared validator** as the form and API — every rule BM-01 … BM-09 applies; errors per row (*"Row N: …"*).
2. **File layout — one row per allocation.** First row of each BOM carries the header: `bom_number`, `bom_name`, `product_code`, **`routing_number` (required)**, `base_quantity`, `base_uom_code`, `version`, `bom_type`, `usage_context`, `effective_date`, `expiry_date`. Every row carries the allocation: **`operation_sequence` (required)**, `component_code`, `item_quantity`, `item_uom_code`, `material_scrap_percentage`, **`alternative_of`** (primary component code in the same operation), **`priority`**, **`sub_bom_number`** (same on every row of a material — BM-08), `notes`.
3. **Routing** matched by `routing_number`, same tenant, allowed status (BM-01). It does **not** need to match the product (RT-01) — TRACK-11 corrected.
4. **Update** matches `bom_number` + `version`, and **only draft BOMs** can be updated (same as RT-27); other status → row error *"BOM X v{y} is {status} — create a new version."* Update rebuilds both tables through the service.
5. **BOM types** = the same list as the form.
6. **Sample template and export** use the same columns (no formula column), so an exported file can be re-imported.
→ Task **BM-IMPL-18**.
* *Code reference:* `ProductionMasterImportService.php` — parse 160–345 never puts `routing_number` into row data (320–338) ✘; no operation column ✘; save 680–775 writes items to master rows with dropped `routing_operation_id` / `child_bom_id` (not fillable → ignored) then calls empty `syncRoutingOperationMaterials()` (`ProductionBomService.php` 630) → **no allocations** ✘; update matches `bom_number` only with `first()` and overwrites any status ✘; direct model writes bypass service + validation ✘; bom_type limited to manufacturing / phantom ✘; product / UoM / qty / scrap / circular checks ✔.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-11; RT-01, RT-27; confirmed 2026-11-11.

### BM-28: BOM API — Same Fields and Checks as the Form
1. The API accepts and checks **exactly what the form does**, through the same service + shared validator (BM-01 … BM-09). **Request shape = form:** header + `items[]` = allocations, each with `routing_operation_id`, `material_id`, `quantity`, `uom_id`, `material_scrap_percentage`, `is_alternative`, `replaces_row_uid` / `replaces_item_id`, `priority`, `child_bom_id`, `notes`, `row_uid`.
2. **Header-only update leaves the allocations untouched** — when `items` is not sent only the header changes; items are never rebuilt from master rows. When `items` is sent it **replaces** all allocations (same as the form).
3. **Response returns both tiers:** `materials[]` (master rows: `quantity`, `alternative_quantity`, `gross_quantity`, role, UoM) and `allocations[]` (rule 1 fields + operation sequence / name), plus `routing`, `routing_advisory` (BM-18) and `warnings`. Duplicate / dropped fields removed; no rounding.
4. `clone` and `duplicate` both follow BM-03 (return the copy payload, no save) — one endpoint kept, the other marked as alias.
→ Task **BM-IMPL-19**.
* *Code reference:* `Api/ProductionBomApiController.php` store 104–120 / update 125–175 via service + DTO ✔, draft-only edit 132 ✔; header-only update rebuilds items from master rows 137–150 (`sequence`, `child_bom_id`, `notes`, non-existent `is_critical`; no operation) → `persistBomItems()` deletes all allocations ✘; `clone()` alias 267; `Resources/Api/ProductionBomDetailResource.php` 51–63+ master items only, dropped fields, material id ×3, scrap ×2 ✘.
* *Origin:* confirmed 2026-11-11.

## G. Downstream & Cleanup

### BM-29: Downstream Use of BOM Data — ⏭️ DEFERRED
* **Deferred — to be explained and decided later (→ BM-DEF-03).**
* *Scope when resumed:* where-used tab (`BomWhereUsedService.php` 15–60 — finds parents by product on master rows, not by pinned sub-BOM version); MRP engine / MRP shortage / production orders / plans / ECO reading BOM data (alongside RT-32).
* Already-agreed items that touch downstream code stay as agreed: BM-IMPL-04 #5 (readers use `gross_quantity`), BM-IMPL-08 #7–#9, #12 (sub-BOM resolver, dropped-column queries, no new production on inactive sub-BOM).
* *Origin:* `bom_routing_feature_exchange_tracker.md`; RT-32; deferred 2026-11-11.

### BM-30: Tests Aligned With the New Design
1. **Every BM-IMPL task carries its own tests** (listed in each task's last row).
2. Tests of **removed designs** are deleted / rewritten in the task that removes the feature — formula tests in BM-IMPL-09 (`ProductionParameterizedBomTest.php`), BOM-in-routing tests in RT-IMPL-19 (e.g. `RoutingMaterialAllocationTest.php`).
3. After the BOM tasks, the **full suite is run** and failures are reported with their output. Passing tests = **🛠️ Applied (code working)** — only the user marks ✅ Verified.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-12; confirmed 2026-11-11.

### BM-31: Final Cleanup
1. Leftovers already covered: `ProductionBomItem::$fillable` dropped columns (BM-IMPL-08 #13); `child_bom_id` read from master rows (BM-IMPL-08 #7–#10); empty `syncRoutingOperationMaterials()` (BM-IMPL-18 #6); master-row copy in `duplicateVersion()` (BM-IMPL-03 #5); `[DECOUPLE-…]` markers (RT-IMPL-19).
2. **Drop `production_bom_items.material_scrap_percentage`** — unused after BM-04 (scrap per allocation + `gross_quantity`).
3. **Remove the `under_revision` status** — never set; demo data only. *(Supersedes the "kept" wording in BM-02 rules 1–2 and BM-03 rule 7.)*
4. Done = the cleanup greps return nothing and the test suite passes.
→ Task **BM-IMPL-20** — ⏭️ **Do LAST** (after the other BOM tasks are applied and user-verified).
* *Origin:* confirmed 2026-11-11.

---

## Review Completion Summary (2026-11-11)
* **Agreed:** BM-01 … BM-09, BM-12 … BM-20, BM-27, BM-28, BM-30, BM-31; quantity parts of BM-22 / BM-23 / BM-24; BM-11 covered by BM-04; BM-13 no new task.
* **Partly agreed + deferred:** BM-10 (scrap detail + Routing yield → BM-DEF-01).
* **Deferred:** BM-21, BM-25, BM-26 and cost parts of BM-22 … BM-24 (all costing → BM-DEF-02); BM-29 (downstream → BM-DEF-03).
* **Tasks:** BM-IMPL-01 … BM-IMPL-20 (BM-IMPL-10 first among UI tasks; BM-IMPL-20 last). Routing tasks with BOM parts: RT-IMPL-05, 09, 13, 14.
* **Next:** separate business rules document (after all module reviews).
