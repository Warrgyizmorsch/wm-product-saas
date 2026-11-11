# BOM — Implementation Task List

> **Purpose:** Code changes and open issues that come out of the agreed decisions in [`docs/bom_decisions.md`](bom_decisions.md).
> Each task carries the exact files, lines and data mappings so it can be implemented without re-investigation.
> **Rule:** Tasks are implemented only when the user says so. After implementation a task is marked **🛠️ Applied**; only the user marks it **✅ Verified** (passing tests ≠ verified).
> **Related:** Routing tasks with a BOM part — [`docs/routing_implementation_tasks.md`](routing_implementation_tasks.md) RT-IMPL-05, RT-IMPL-09, RT-IMPL-13, RT-IMPL-14.

## Status Legend
* 🟢 **Agreed** — Decision final, waiting for go-ahead to implement.
* 🟡 **Proposed** — Logged with a recommended fix; not yet confirmed by the user.
* 🛠️ **Applied** — Implemented; awaiting user verification.
* ✅ **Verified** — Verified by the user.
* ❌ **Cancelled** — Superseded by a later decision; kept for history.
* 📌 **Marked for Other Module** — Belongs to another module; tracked here so it is not lost.
* ⏸️ **Marked Issue — Decide Later** — Problem confirmed in code; solution not yet agreed.
* ⏭️ **Deferred** — Agreed direction, but the work belongs to a deferred track.

---

## Summary Register

| Task ID | Title | Source Decision | Status |
| :--- | :--- | :--- | :--- |
| **BM-IMPL-01** | BOM routing: required on every path, tenant-scoped, no historical / cancelled routing | BM-01 | 🟢 Agreed |
| **BM-IMPL-02** | BOM lifecycle: submit needs materials, real audit user, routing approval gate with start date, daily date job | BM-02 | 🟢 Agreed |
| **BM-IMPL-03** | Duplicate / Revise opens pre-filled create form (no DB row), faithful copy, routing / deleted-op checks, tenant-scoped version check | BM-03 | 🟢 Agreed |
| **BM-IMPL-04** | Master list: primary-only qty, new `alternative_quantity` + `gross_quantity`, one UoM per material, readers use gross | BM-04 | 🟢 Agreed |
| **BM-IMPL-05** | Allocation operation: required + tenant-scoped + not deleted on form, API and import | BM-05 | 🟢 Agreed |
| **BM-IMPL-06** | Duplicate keys: primary once per op, alternative once per replaced primary, no self-alternative; server + browser + API + import; clearOdooError fix | BM-06 | 🟢 Agreed |
| **BM-IMPL-07** | Alternatives: same-operation primary, required unique priority, remove-with-primary in form, API + import columns | BM-07 | 🟢 Agreed |
| **BM-IMPL-08** | Sub-BOM: one picker per material in Tab 2 written to all allocations; status rules + warnings; readers read allocations; fix dropped-column queries; inactive sub-BOM → parent warning + no new production | BM-08 | 🟢 Agreed |
| **BM-IMPL-09** | Remove formula feature: columns, evaluator, preview route, UI, rules, readers, ECO, seeder, tests, lang | BM-09 | 🟢 Agreed |
| **BM-IMPL-10** | BOM form: shared create/edit partial (do first), card header figures, Op number in notice, translations | BM-12 | 🟢 Agreed |
| **BM-IMPL-11** | Material rollup tab: BM-04 columns, sub-BOM picker, no rounding, translations | BM-15 | 🟢 Agreed |
| **BM-IMPL-12** | Draft recovery: scoped keys, stale-edit guard, copy key, no formula fields | BM-16 | 🟢 Agreed |
| **BM-IMPL-13** | Deleted operation card: BOM additions (move, no silent drop, approve guard, show page) — with RT-IMPL-09 | BM-17 | 🟢 Agreed |
| **BM-IMPL-14** | Routing advisory: three live checks (not active, op removed, outdated), approval guard, form / show / list / API — with RT-IMPL-05 | BM-18 | 🟢 Agreed |
| **BM-IMPL-15** | Show page Components tab: direct materials, allocation drawer, remove recursive tree and dropped-column reads | BM-19 | 🟢 Agreed |
| **BM-IMPL-16** | Explosion tab: explode viewed BOM, gross primary demand, chosen sub-BOMs, alternatives as info | BM-20 | 🟢 Agreed |
| **BM-IMPL-17** | Effective scrap % on master row, "not in total" alternative badge, gross per drawer row | BM-22 / 23 / 24 | 🟢 Agreed |
| **BM-IMPL-18** | BOM import: service-based, one row per allocation, routing + operation columns, draft-only update, template / export | BM-27 | 🟢 Agreed |
| **BM-IMPL-19** | BOM API: shared rules, safe header-only update, materials + allocations response | BM-28 | 🟢 Agreed |
| **BM-IMPL-20** | Final BOM cleanup: drop master scrap column, remove under_revision, verify leftovers — do LAST | BM-31 | 🟢 Agreed |
| **BM-DEF-01** | Scrap rules in detail + connection with Routing operation yield | BM-10 | ⏭️ Deferred |
| **BM-DEF-02** | All BOM costing: material cost live vs saved, cost field, sub-BOM cost, routing cost, cost columns | BM-21, 22–26 (cost parts) | ⏭️ Deferred |
| **BM-DEF-03** | Downstream use of BOM data (where-used, MRP, orders, plans, ECO) | BM-29 | ⏭️ Deferred |

---

## BM-IMPL-01: BOM Routing — Required, Tenant-Scoped, Allowed Status Only
* **Source Decision:** BM-01 (agreed 2026-11-11)
* **Status:** 🟢 Agreed

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Requests/StoreProductionBomRequest.php`, `UpdateProductionBomRequest.php` | 51 | `'routing_id' => 'required|integer|exists:routings,id'` | `Rule::exists('routings','id')->where('tenant_id', …)`; in `withValidator()` (~80–92) reject `historical` / `cancelled` Routings: *"Routing RTG-X is {status} and cannot be used for a BOM."* (edit: allow keeping the current routing only if it is still allowed — otherwise the BOM must switch) |
| 2 | `app/Domains/Production/Requests/Api/StoreBomApiRequest.php`, `UpdateBomApiRequest.php` | 19, 15 | `'routing_id' => 'nullable|exists:routings,id'` | **required**, same checks as #1 (share the rules) |
| 3 | `app/Domains/Production/Services/ProductionMasterImportService.php` | 690, 738 (`routing_id => $routingId ?? …`) | may save without routing | Row error when routing missing / not allowed (with BM-27) |
| 4 | DB `production_boms.routing_id` | migration `2026_10_31_120000` 16–22 (nullable) | nullable | *(Proposed)* make **NOT NULL** after checking no BOM has null routing (demo data) |
| 5 | Comment `[DECOUPLE-PRODUCT-INDEPENDENT]` at request line ~90 | — | — | Remove in RT-IMPL-19 cleanup |

---

## BM-IMPL-02: BOM Lifecycle — Submit / Approve Gates and Daily Date Job
* **Source Decision:** BM-02 (agreed 2026-11-11)
* **Status:** 🟢 Agreed

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Services/ProductionBomService.php` `submitApproval()` | 145–174 | Only checks status draft / under_revision | Also reject when the BOM has **no master items**: *"BOM {bom_number} v{version} has no materials and cannot be submitted."* |
| 2 | Same file, audit rows in `submitApproval()` / `approve()` / `reject()` / `cancel()`; controllers `ProductionBomController.php` 121, 508 and `Api/ProductionBomApiController.php` 252 | — | `'user_id' => auth()->id() ?: 1` | Real user id; `null` + "System" label when run by job / console. Never fall back to user 1 |
| 3 | `ProductionBomService.php` `approve()` | 175–245 | Routing status in `[active, scheduled, 'approved']` | `active`, or `scheduled` **and** routing `effective_date` ≤ BOM `effective_date`. Error: *"Routing RTG-X is {status} (starts {date}); the BOM can only be approved when the Routing is active or starts on or before {bom date}."* Remove `'approved'` |
| 4 | **New** console command (e.g. `production:process-bom-dates`) + schedule in `routes/console.php`, sharing the RT-03 job pattern | — | No job; status never moves with dates | Daily, per tenant: `scheduled` with `effective_date ≤ today` → `approved` (audit "Activated"); `approved` with `expiry_date < today` → `inactive` (audit "Expired"). Run supersession rules so only one approved BOM per product + bom_type remains |
| 5 | `BomLifecycleService.php` `transitionOnApproval()` | 100–200 | ✔ Supersession + late-approval date move | Keep; reuse from the job (#4) |
| 6 | Tests | `tests/Feature/ProductionBom*` | — | Submit empty BOM blocked; approve with scheduled routing starting later blocked; job activates / expires |

---

## BM-IMPL-03: Duplicate / Revise — Pre-filled Create Form, No DB Entry Until Save
* **Source Decision:** BM-03 (agreed 2026-11-11)
* **Status:** 🟢 Agreed

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Controllers/ProductionBomController.php` `createRevision()` / `duplicateVersion()`; routes `Routes/web.php` 103, 108 (POST) | 96–130, 496–519 | Calls `duplicateVersion()` → saves BOM, redirects to **edit** | Redirect to `production.boms.create` with `?source_bom_id={id}&bump={patch\|minor\|major}` (or `&version=` for Duplicate). Routes become GET (or keep POST → redirect). Nothing is saved |
| 2 | Same controller `create()` | 211–224 | No pre-fill | When `source_bom_id` given: load source with items + `operationMaterials` (same loader as edit), build pre-fill payload: product, routing (only if allowed — else empty + warning), base qty / UoM, bom_type, usage_context, name, suggested version (tenant-scoped bump loop 112–118), revision +1, notes *"Duplicated from version X."*, items with all fields, allocations with alternative links (client-side keys, not DB ids), **no formula fields**. Allocations on soft-deleted operations flagged `deleted_operation` (RT-IMPL-09 card) |
| 3 | `resources/views/modules/production/bom/create.blade.php` + its JS | — | Empty form only | Accept the pre-fill payload the same way edit loads existing data; show routing warning (#2) and deleted-operation cards; hidden `source_bom_id` |
| 4 | `StoreProductionBomRequest.php` / `ProductionBomService::create()` | — | — | Optional `source_bom_id` (tenant-scoped exists). On save: set `revision` = source revision + 1, write audit **"Revision Created"** (*"Version {v} created from version {x}."*), fire `BomVersionCreated`. Block save while a deleted-operation allocation remains (RT-IMPL-09) |
| 5 | `ProductionBomService.php` `duplicateVersion()` | 335–457 | Saves a copy | Replace with a read-only `buildCopyPayload(int $id)` used by #2 and the API (#7). Payload = allocations only (all fields); master rows not copied (rebuilt on save, BM-IMPL-04 #6). Remove the master-row copy 375–393, which writes `replaces_item_id` to a dropped column |
| 6 | `ProductionBomService.php` `checkBomConflicts()`; `BomRepository::versionExistsUnscoped()` | 673–686 | `withoutGlobalScopes()` with no tenant filter | Add `where('tenant_id', $tenantId)` |
| 7 | `app/Domains/Production/Controllers/Api/ProductionBomApiController.php` duplicate / revision endpoint | ~230–260 | Saves a copy immediately | Return the copy payload (#5) **without saving**; the client then calls the normal create endpoint. Same rules as the form (agreed 2026-11-11) |
| 8 | Buttons `resources/views/modules/production/bom/index.blade.php` 416, `show.blade.php` 939 | — | POST forms | Point to the new create-form flow |
| 9 | Tests | — | — | Clicking Revise creates no row; save creates BOM + audit; disallowed routing blocks save; tenant-scoped version clash |

---

## BM-IMPL-04: Master List Built Correctly From Allocations
* **Source Decision:** BM-04 (agreed 2026-11-11)
* **Status:** 🟢 Agreed

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | **New migration** on `production_bom_items` | — | — | Add `alternative_quantity` decimal(18,6) nullable, `gross_quantity` decimal(18,6) nullable. Backfill existing BOMs by re-running the master build (#2) per BOM |
| 2 | `app/Domains/Production/Services/ProductionBomService.php` `persistBomItems()` STEP 1 | 480–521 | `$totalQuantity += quantity` for every allocation; `material_scrap_percentage => 0.00`; `uom_id = $dtos[0]->uom_id` | Effective-alternative check (`is_alternative` or op in `$altOpIdsMap`, already at 495) decides the bucket: primary → `quantity` += qty, `gross_quantity` += qty × (1 + scrap/100); alternative → `alternative_quantity` += qty. Alternative-only material → `quantity` = 0. Keep role logic 503–508 |
| 3 | Same + `StoreProductionBomRequest.php` / `UpdateProductionBomRequest.php` `withValidator()` (and API / import, BM-27) | — | No check | Block when one material has allocations with different `uom_id`: *"Material {name} uses {uom1} and {uom2} in this BOM — use one unit."* |
| 4 | `ProductionBomItem.php` | 18–38 | — | Add `alternative_quantity`, `gross_quantity` to fillable + float casts |
| 5 | Readers: `BomExplosionService.php` 107, `MrpEngineService.php` 87, `MrpShortageService.php` 287, `ProductionCostService.php` 18 / 132, `ProductionEcoImpactAnalysisService.php` 162–163, `ProductionBomService::calculateRequirements()` 650 | — | Use `quantity` × master scrap (always 0) | Use `gross_quantity` for requirement / cost; per-operation detail from allocations. Remove master-scrap use |
| 6 | `ProductionBomService::duplicateVersion()` / copy payload (BM-IMPL-03) | 375–393 | Copies master rows | Master rows not copied — rebuilt from allocations on save |
| 7 | Tests | — | — | Primary 10 + alternative 8 → master 10, alt 8; scrap 5 % → gross 10.5; mixed UoM blocked |

---

## BM-IMPL-05: Allocation Operation — Required, Own Routing, Every Path
* **Source Decision:** BM-05 (agreed 2026-11-11)
* **Status:** 🟢 Agreed

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Requests/StoreProductionBomRequest.php`, `UpdateProductionBomRequest.php` | 61 / 62 | `exists:production_routing_operations,id` | `Rule::exists(...)->where('tenant_id', tenant_id())->whereNull('deleted_at')` |
| 2 | Same, `withValidator()` | 82–120 / 115–125 | Belongs-to-routing check only `if ($routing && …)` | Keep; with BM-IMPL-01 routing always loads. Soft-deleted op → error tied to RT-IMPL-09 card: *"Operation {seq} was removed from routing RTG-X — move or remove these materials."* Move the shared logic into one rule class used by web, API, import |
| 3 | `Requests/Api/StoreBomApiRequest.php`, `UpdateBomApiRequest.php` | 15–40 | No `items.*.routing_operation_id` | Add `required|integer` + same checks as #1–#2 |
| 4 | `Services/ProductionMasterImportService.php` BOM item parse | 260–305 | No operation column | Read operation column (sequence / code), resolve to the BOM routing's operation id, row error when missing / not found; set `routing_operation_id` in the item array so 713 / 762 save it (layout with BM-27) |
| 5 | Tests | — | — | Operation from another routing / tenant / deleted → blocked; API without operation → 422; import row without operation → row error; operation with no materials → allowed |

---

## BM-IMPL-06: Duplicate Material Rules Per Operation
* **Source Decision:** BM-06 (agreed 2026-11-11)
* **Status:** 🟢 Agreed

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Requests/StoreProductionBomRequest.php`, `UpdateProductionBomRequest.php` `withValidator()` | 128–135 (+ same in Update) | `$key = "{$materialId}_{$opId}"` for every row | Primary row: key `P_{opId}_{materialId}`. Alternative row: resolve the replaced primary (`replaces_row_uid` / `replaces_item_id`), key `A_{opId}_{replacedKey}_{materialId}`; error when replaced primary's material = this material. Messages per BM-06 rule 3. Put in the shared rule class (BM-IMPL-05 #2) |
| 2 | `resources/views/modules/production/bom/create.blade.php` `validateDuplicateMaterials()` | ~1860–1925 | Key on material + operation only | Same keys as #1 so the browser and server agree |
| 3 | `resources/views/modules/production/bom/edit.blade.php` `validateDuplicateMaterials()` | ~1915–1980 | Same | Same as #2 |
| 4 | `resources/views/components/ui/odoo-form-ui.blade.php` `clearOdooError()` | 675–700+ | Adds `d-none` to every `.invalid-feedback` | Skip feedback elements managed by Alpine (`:not([x-text])` / `x-if` nodes) |
| 5 | API requests + `ProductionMasterImportService.php` | — | No duplicate check | Use the shared rule (#1); import errors prefixed *"Row N:"* |
| 6 | Tests | — | — | BM-06 example: allowed rows save; second primary, repeated alternative for same primary, self-alternative → blocked |

---

## BM-IMPL-07: Component Alternatives — Same Operation, Priority, All Paths
* **Source Decision:** BM-07 (agreed 2026-11-11)
* **Status:** 🟢 Agreed

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Requests/StoreProductionBomRequest.php`, `UpdateProductionBomRequest.php` `withValidator()` | 150–185 | No operation compare | Store `routing_operation_id` in `$allRows`; error when target's operation ≠ this row's: *"The alternative must be in the same operation as the primary it replaces (Op {seq})."* Move into the shared rule class (BM-IMPL-05 #2) |
| 2 | Same, rules array | `items.*.priority` | `nullable|integer|min:1` | `required_if:items.*.is_alternative,1|integer|min:1`; in `withValidator()` error when two alternatives of the same primary share a priority: *"Priority {n} is already used for an alternative of {primary}."* Primary rows: priority ignored / null |
| 3 | `app/Domains/Production/Services/ProductionBomService.php` `persistBomItems()` | 556 | `$itemDto->priority ?? 1` | Use the validated priority (no silent default) |
| 4 | `resources/views/modules/production/bom/create.blade.php`, `edit.blade.php` (remove-row handler) | — | Removing a primary leaves its alternatives | Confirm *"Remove {primary} and its {n} alternative(s)?"* then remove them together; priority field required on alternative rows |
| 5 | API requests (`Requests/Api/StoreBomApiRequest.php`, `UpdateBomApiRequest.php`) | 15–40 | Fields accepted, no checks | Use the shared rule |
| 6 | `app/Domains/Production/Services/ProductionMasterImportService.php` BOM item parse | 260–305 | No alternative columns | Columns "alternative of" (primary material code in the same operation) + "priority"; resolve to `is_alternative` / `replaces_row_uid`; row errors per #1–#2 (layout with BM-27) |
| 7 | Tests | — | — | Cross-operation alternative blocked; missing / duplicate priority blocked; alternative-operation material → `alternative_quantity` |

---

## BM-IMPL-08: Sub-BOM — One Choice Per Material, Stored Per Allocation
* **Source Decision:** BM-08 (agreed 2026-11-11)
* **Status:** 🟢 Agreed

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `resources/views/modules/production/bom/create.blade.php`, `edit.blade.php` (Tab 1 allocation rows, Tab 2 master list) | — | Sub-BOM chosen per allocation | Remove the per-allocation picker; add **one picker per material in Tab 2**, options = selectable sub-BOMs (BM-08 rule 4) with state badge + warning text; pre-select current active. On submit copy the choice into `items[*].child_bom_id` of every allocation of that material. A new allocation of an already-present material inherits it |
| 2 | `app/Domains/Production/Controllers/ProductionBomController.php` `create()` / `edit()` (+ copy payload BM-IMPL-03) | 211–224 | — | Pass per material: selectable sub-BOMs (id, number, version, status, effective / expiry dates) |
| 3 | `StoreProductionBomRequest.php` / `UpdateProductionBomRequest.php` / API requests — shared rule class | — | No consistency check | All allocations of one material must have the same `child_bom_id` (BM-08 rule 3) |
| 4 | `app/Domains/Production/Services/ProductionBomService.php` `validateChildBomLink()` | 689–725 | `withoutGlobalScopes()->find()` | Add tenant check; status must be approved (incl. with expiry) or scheduled; keep product / self / circular / date checks |
| 5 | Same, `persistBomItems()` | 538–556 | Silent auto-pick of newest approved BOM | Remove (the form pre-selects visibly) |
| 6 | Same, `approve()` / `BomLifecycleService::transitionOnApproval()` | 175–245 / 100–200 | — | Parent start = max(own start, scheduled sub-BOM starts) → Scheduled if future; block if a chosen sub-BOM is now inactive / cancelled |
| 7 | One shared resolver, e.g. `ProductionBom::subBomFor(materialId)` reading the material's allocation `child_bom_id` | — | Readers read master `child_bom_id` (dropped → null) and guess | Use it in `BomExplosionService.php` 129 (drop own pick 66–75), `MrpEngineService.php` 130 (drop own pick 133–139), `ProductionOrderService.php` 504 / 510 |
| 8 | `ProductionOrderService.php` | 157 | `$bom->items()->whereNotNull('child_bom_id')` (dropped column) | `$bom->operationMaterials()->whereNotNull('child_bom_id')->exists()` |
| 9 | `ProductionBomService.php` `updateParentBomWarningsForComponent()` | 750–758 | `ProductionBomItem … orWhere('child_bom_id')` (dropped column) | Query `RoutingOperationMaterial` by `material_id` / `child_bom_id`; confirm why approve / cancel do not error today |
| 10 | `ProductionBomService.php` `detectCircularChildLink()` | 737–744 | Walks master rows | Walk allocations |
| 11 | Sub-BOM status change → parent warning: on sub-BOM deactivate / expire (BM-IMPL-02 daily job) / cancel / supersede | — | Partial (#9 broken) | Parent `refreshWarningStatus()` adds *"Sub-BOM {no} v{x} for {material} is {status} — create a new version of this BOM."*; parent status unchanged |
| 12 | New production plans / orders (`ProductionOrderService` create, production plan creation) | — | — | Reject a BOM whose chosen sub-BOM is inactive / cancelled: *"BOM {no} v{x} uses an inactive sub-BOM — approve a new version before starting production."* Orders already started are not affected |
| 13 | `app/Domains/Production/Models/ProductionBomItem.php` | 18–33 | Fillable lists dropped columns (`routing_operation_id`, `child_bom_id`, `replaces_item_id`, `is_alternative`, `priority`, `sequence`, `notes`) | Remove them (with `quantity_type` / `formula` in BM-09) |
| 14 | Tests | — | — | One material in two operations → same sub-BOM saved on both; mismatch via API → 422; inactive sub-BOM not selectable; scheduled sub-BOM moves parent start; cancelled sub-BOM → parent warning + new order blocked |

---

## BM-IMPL-09: Remove the Formula Quantity Feature
* **Source Decision:** BM-09 (agreed 2026-11-11)
* **Status:** 🟢 Agreed

| # | File | Lines | Action |
| :-: | :--- | :--- | :--- |
| 1 | **New migration** | — | Drop `quantity_type`, `formula` from `production_bom_items` (added by `2026_09_05_140000`) and `production_routing_operation_materials`; drop `production_orders.parameters` (added by `2026_09_05_140001`). No data conversion (demo data) |
| 2 | `app/Domains/Production/Services/BomFormulaEvaluatorService.php` | whole file | Delete |
| 3 | `app/Domains/Production/Controllers/BomFormulaController.php`; route `Routes/web.php` | whole file; 101, 398–399 | Delete controller + `boms.preview-formula` route |
| 4 | `resources/views/modules/production/bom/create.blade.php`, `edit.blade.php` | ~37 places each (formula toggle / input, `previewFormula()` ~1615–1640, `@click="previewFormula(...)"` ~452) | Remove; quantity input only |
| 5 | `resources/views/modules/production/bom/show.blade.php` | 3 places | Remove formula display |
| 6 | `Requests/StoreProductionBomRequest.php` 64, `UpdateProductionBomRequest.php` 65 | — | Remove `items.*.quantity_type` / `items.*.formula` rules |
| 7 | `Requests/Api/StoreBomApiRequest.php` 27–28, `UpdateBomApiRequest.php` 25–26 | — | Replace with `prohibited` rule + message *"Formula quantities are not supported; send a fixed quantity."* |
| 8 | `DTO/ProductionBomItemDTO.php` | 12, 33, 54–55 | Remove `quantity_type`, `formula` |
| 9 | `Services/ProductionBomService.php` | 418–419 (copy), 573–574 (save) | Remove fields |
| 10 | `Services/BomExplosionService.php` | 105–115; `$parameters` 14, 29, 42, 91, 100 | Remove formula branch + parameters argument |
| 11 | `Services/MrpShortageService.php` | 285–295 | Remove formula branch + parameters |
| 12 | `Services/ProductionOrderService.php` | 484–491, 1574–1582 | Remove formula branch + `$order->parameters` |
| 13 | `Services/ProductionEcoImpactAnalysisService.php` | 181–196 | Remove `is_formula_changed`; compare quantity / scrap only |
| 14 | `Models/ProductionBomItem.php` (fillable 23–24, `isFormula()` / `isFixed()` 40–48), `Models/RoutingOperationMaterial.php` 26, `Models/ProductionOrder.php` 143 / 158 | — | Remove |
| 15 | `database/seeders/ProductionRoutingBomDemoSeeder.php` | 633, 647–648 | Fixed quantity; drop formula wording in notes |
| 16 | `tests/Feature/ProductionParameterizedBomTest.php` | whole file | Delete |
| 17 | `lang/en/production.php`, `lang/bg/production.php`, `lang/hi/production.php` | formula keys | Remove |
| 18 | `docs/bom_routing_feature_exchange_tracker.md`, `docs/bom_routing_architecture_map.md` | — | Note: formula feature dropped by BM-09 |
| 19 | Done check | — | `grep -rni "quantity_type\|BomFormula\|isFormula\|preview-formula" app resources database routes tests lang` returns nothing; tests pass |

---


---

## BM-IMPL-10: BOM Form — Shared Partial, Card Header Figures, Notice, Translations
* **Source Decision:** BM-12 (agreed 2026-11-11)
* **Status:** 🟢 Agreed — ⏩ **Do FIRST among BOM UI tasks** (BM-IMPL-03 / 06 / 07 / 08 / 09 UI parts build on it)

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `resources/views/modules/production/bom/create.blade.php` (2,153 lines), `edit.blade.php` (2,206 lines) | whole files | Two near-identical copies of markup + Alpine script | Move the form body to a shared partial (e.g. `bom/partials/form.blade.php`) and the Alpine component to one script; `create` / `edit` pass mode + initial data (edit: existing BOM; create: empty or BM-IMPL-03 copy payload) |
| 2 | Card header metrics | create ~276–284 / edit same | `setup_time_minutes + 'm'`, `processing_time_minutes + 'm/unit'`, yield | Show setup per run + setup per piece (RT-16 columns), run per unit, yield, using the routing's saved values; durations with the RT-25 formatter (shared with routing pages) |
| 3 | Zero-material notice | create 583 / edit 597 | `__('production.zero_materials_notice', ['seq' => ''])` | Pass the operation sequence (Alpine `x-text` with the op's sequence) |
| 4 | Card texts | create 220–300 / edit same | Hard-coded English | Translation keys in `lang/en|bg|hi/production.php` |
| 5 | Routing-change dialog (BM-14) | create 1517–1545 / edit 1556–1585 | Hard-coded "Yes, Change Routing" / "Cancel"; same text on edit | Translate buttons; on edit add *"Nothing is changed until you save."* |
| 6 | Tests / check | — | — | Create and edit render the same cards; notice shows the Op number |


---

## BM-IMPL-11: Material Rollup Tab — BM-04 Columns, No Rounding
* **Source Decision:** BM-15 (agreed 2026-11-11)
* **Status:** 🟢 Agreed (after BM-IMPL-10 shared partial)

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `resources/views/modules/production/bom/create.blade.php` / `edit.blade.php` (→ shared partial) `summarizedMaterials` | ~985–1025 | `total_qty += qty` for every allocation | `quantity` = primary sum, `alternative_quantity` = alternative sum (incl. alternative operations), `gross_quantity` = Σ primary qty × (1 + scrap/100) — same rules as `persistBomItems()` (BM-IMPL-04) |
| 2 | Rollup table | 620–700 | Columns: role, "Total Planned Qty" (with primary + alt sub-line), UoM in two places | Columns: material, type, role, quantity, alternative qty, gross qty, UoM (once), sub-BOM picker (BM-IMPL-08 #1) |
| 3 | Row + drawer numbers | ~670–770 | `toFixed(2)` | ≥ 4 decimals, no rounding (shared number formatter) |
| 4 | Drawer | ~700–790 | Shows `alloc.quantity_type` | Remove (BM-IMPL-09) |
| 5 | Texts | 600–790 | "Allocation Role", "Total Planned Qty", "Mixed Roles", "Primary Only", "Alternative Only", "Substitutes:", "Sub Op", empty-state paragraph 618 | Translation keys (en / bg / hi) |
| 6 | Check | — | — | Saved master rows (BM-IMPL-04) = rollup figures for the same input |


---

## BM-IMPL-12: Draft Recovery — Scoped Keys, Stale-Edit Guard, Copy Key
* **Source Decision:** BM-16 (agreed 2026-11-11)
* **Status:** 🟢 Agreed (after BM-IMPL-10 shared partial)

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `resources/views/modules/production/bom/create.blade.php` 914, `edit.blade.php` 947 (→ shared script) | — | `bom_create_draft_v1` / `bom_edit_draft_v1_{id}` | `bom_draft_{tenant_id}_{user_id}_create`, `…_edit_{bom_id}`, `…_copy_{source_bom_id}` (BM-IMPL-03 flow) |
| 2 | `saveDraft()` / `restoreFromDraft()` | 1138–1300 | No version stamp | Edit: store `bom_updated_at` (passed from controller); on load compare with current `updated_at` — mismatch → clear + notice *"This BOM was changed after your draft was saved; the draft was discarded."* |
| 3 | Copy flow | — | — | First open with `source_bom_id`: pre-fill wins, draft key `…_copy_{source}`; later reloads restore that key |
| 4 | `saveDraft()` items | 1160–1175 | Saves `quantity_type`, `formula` | Remove (BM-IMPL-09) |
| 5 | Banner | 30–47 | Hard-coded "Draft Restored", "Discard Draft & Reset" | Translation keys |
| 6 | Check | — | — | Two users on one browser do not see each other's drafts; stale edit draft discarded |


---

## BM-IMPL-13: Deleted Operation Card — BOM Additions to RT-IMPL-09
* **Source Decision:** BM-17 (agreed 2026-11-11)
* **Status:** 🟢 Agreed — implement **together with RT-IMPL-09 rows 7–11** (routing file)

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `resources/views/modules/production/bom/create.blade.php` / `edit.blade.php` (→ shared partial) `updateAvailableOperations()` | 1330–1336 | `this.items = this.items.filter(... validOpIds.includes(...))` drops rows silently | Remove the filter; rows on soft-deleted operations go to a "Deleted" card (RT-IMPL-09 #8) |
| 2 | Deleted card rows | — | — | Per row: **"Move to Op …"** select (live operations) + Remove; on move run BM-06 duplicate check |
| 3 | `ProductionBomService.php` `approve()` | 175–245 | — | Pending BOM with an allocation on a deleted operation → reject approval: *"Operation OP-XXX was removed from the routing — send the BOM back to draft and fix it."* (with RT-IMPL-09 #9) |
| 4 | `resources/views/modules/production/bom/show.blade.php` | — | — | Read-only deleted card (tag "Deleted") + RT-IMPL-09 #7 advisory |
| 5 | Tests | — | — | Pending BOM hit → approve blocked; move to an op where the material exists as primary → BM-06 error |


---

## BM-IMPL-14: BOM Routing Advisory — Three Live Checks
* **Source Decision:** BM-18 (agreed 2026-11-11)
* **Status:** 🟢 Agreed — implement **together with RT-IMPL-05** (routing file), which this extends

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Models/ProductionBom.php` | new (RT-IMPL-05 #1) | — | `getRoutingAdvisoryAttribute(): array` returning entries `{code: not_active|operation_removed|routing_outdated, message, blocks_approval}` for checks a / b / c |
| 2 | Check c source | — | — | Reuse the routing outdated-warning logic (RT-IMPL-14 snapshot compare, RT-IMPL-18 inactive resources) — one shared method on `Routing`, not a copy |
| 3 | `ProductionBomService.php` `approve()` | 175–245 | — | Block when any entry has `blocks_approval = true` (a, b) |
| 4 | BOM form (shared partial, BM-IMPL-10) | — | — | Advisory panel refreshed on routing change and on load (data from controller / small JSON endpoint) |
| 5 | `bom/show.blade.php` ~173–180, `bom/index.blade.php` ~311–316, API BOM resource | — | Single warning banner / icon | Separate routing-advisory banner, list icon + tooltip, `routing_advisory` field (RT-IMPL-05 #4–6) |
| 6 | `ProductionBom::refreshWarningStatus()` | 388–409 | Component checks only | Keep component-only; add BM-08 sub-BOM-inactive warning (BM-IMPL-08 #11) |
| 7 | Tests | — | — | Each of a / b / c appears and clears; c does not block approval; a / b do |


---

## BM-IMPL-15: Show Page Components Tab — Direct Materials + Allocation Drawer
* **Source Decision:** BM-19 (agreed 2026-11-11)
* **Status:** 🟢 Agreed (cost columns finalised after BM-21 … BM-26)

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `resources/views/modules/production/bom/show.blade.php` | 349–466 (+ duplicate ~50–108), 467 | Recursive `renderComponentTreeRows()` over `childBom->items` | Remove both definitions; loop over `$bom->items` (master rows) only |
| 2 | Same, table | 330–348 header, rows | Seq, component, net, scrap %, gross, unit / net / gross cost | Material, type, role, quantity, alternative qty, gross qty, UoM; cost columns per BM-21 … BM-26. No sub-BOM column |
| 3 | Same, drawer | new | — | Expandable row → allocations of that material (`$bom->operationMaterials` grouped by `material_id`, eager-load `routingOperation`): Op no. + name, alternative-op info, substitutes, priority, qty, scrap %, notes, "View in Routing tab" (switch to `tab-routing`, scroll to op) |
| 4 | Same | ~395–415 | Reads `is_alternative`, `primaryItem`, `notes`, `sequence`, `quantity_type`, `formula` on master rows | Remove (data from allocations; formula with BM-IMPL-09) |
| 5 | Same | number formatting | `number_format(…, 2)` | ≥ 4 decimals |
| 6 | `ProductionBomController::show()` | ~140–210 | — | Eager-load `operationMaterials.routingOperation`, `items.material`, `items.uom` |


---

## BM-IMPL-16: Explosion Tab — Explode the Viewed BOM Correctly
* **Source Decision:** BM-20 (agreed 2026-11-11)
* **Status:** 🟢 Agreed (with BM-IMPL-04 gross qty and BM-IMPL-08 resolver)

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Services/BomExplosionService.php` | 14–37 | `explode(int $productId, …)` → product's active BOM | Add `explodeBom(int $bomId, float $qty, int $tenantId)` (or a `$bomId` argument) that starts from the given BOM; keep product-based entry for MRP callers |
| 2 | `ProductionBomController.php` `show()` | 141 | `explode($bom->product_id, …)` | Call with `$bom->id` |
| 3 | `BomExplosionService::resolveChildren()` | 100–135 | Master `quantity` + master scrap; `child_bom_id`, `is_alternative`, `replaces_item_id` from master | Use master `gross_quantity` (primary demand); sub-BOM from the BM-IMPL-08 resolver (allocation `child_bom_id`); no sub-BOM → leaf (no own pick, 66–75 removed); alternatives listed as info rows from allocations, not recursed, not summed |
| 4 | `bom/show.blade.php` explosion tab | 479–624 | Status column | Status badge + BM-08 warning text for inactive / cancelled / scheduled sub-BOMs; alternative info rows |
| 5 | Same | number formatting | 2 decimals | ≥ 4 decimals |
| 6 | Tests | — | — | Draft v2 shows its own tree; alternatives not summed; pinned sub-BOM followed |


---

## BM-IMPL-17: Effective Scrap %, Alternative Badge, Drawer Gross
* **Source Decision:** BM-22 / BM-23 / BM-24 (agreed 2026-11-11) — quantity parts only
* **Status:** 🟢 Agreed (with BM-IMPL-11 form Tab 2 and BM-IMPL-15 show page)

| # | Where | Required |
| :-: | :--- | :--- |
| 1 | Form Tab 2 (BM-IMPL-11) + show Components tab (BM-IMPL-15), master row | Effective scrap % = (gross − net) ÷ net × 100 (net 0 → "—"); ≥ 4 decimals; display only |
| 2 | Same, alternative quantity | Badge *"Alternative — not in total"* (translated); excluded from footer / grand totals |
| 3 | Form + show drawer rows | Gross per allocation = qty × (1 + scrap / 100), ≥ 4 decimals |
| 4 | Cost columns | Wait for BM-DEF-02 |


---

## BM-IMPL-18: BOM Import — Service-Based, One Row per Allocation, Draft-Only Update
* **Source Decision:** BM-27 (agreed 2026-11-11)
* **Status:** 🟢 Agreed (after BM-IMPL-01 … 09 rules exist in the shared validator)

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Services/ProductionMasterImportService.php` BOM parse | 160–345 | Header without routing; items without operation / alternative / priority | Read the BM-27 rule 2 columns; resolve `routing_number` → routing (tenant, allowed status), `operation_sequence` → that routing's operation, `alternative_of` → primary row in the same operation, `sub_bom_number` → BOM id; build the same DTO as the form (`ProductionBomDTO` / items) |
| 2 | Same, validation | — | Own partial checks | Run the shared BOM rule class (BM-IMPL-05 #2) on the DTO; map errors to "Row N:" |
| 3 | Same, save | 680–775 | Direct `ProductionBom::create/update`, `$bom->items()->delete()`, `ProductionBomItem::create`, empty sync | Create → `ProductionBomService::create()`; update → match `bom_number` + `version`, only `draft` → `ProductionBomService::update()`; other status → row error |
| 4 | Same | ~215 | `bom_type in [manufacturing, phantom]` | Same list as `StoreProductionBomRequest` |
| 5 | Sample template + `app/Exports/BomExport.php` | — | Old columns | BM-27 rule 2 columns, one row per allocation, no formula column |
| 6 | `ProductionBomService::syncRoutingOperationMaterials()` | 627–633 | Empty method | Remove (no callers after #3) |
| 7 | Tests | — | — | Import creates BOM with routing + allocations; update of approved BOM → row error; export → re-import round trip |


---

## BM-IMPL-19: BOM API — Form-Equal Requests, Safe Header Update, Two-Tier Response
* **Source Decision:** BM-28 (agreed 2026-11-11)
* **Status:** 🟢 Agreed (with BM-IMPL-01 / 05 / 06 / 07 / 08 / 09 API rows)

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Requests/Api/StoreBomApiRequest.php`, `UpdateBomApiRequest.php` | 15–40 / 12–40 | Own rule set (routing nullable, no operation, formula, `scrap_factor`) | Same rules as `StoreProductionBomRequest` / `UpdateProductionBomRequest` via the shared rule class; formula `prohibited` (BM-IMPL-09) |
| 2 | `app/Domains/Production/Controllers/Api/ProductionBomApiController.php` `update()` | 137–150 | Rebuilds `items` from master rows when absent → allocations wiped | When `items` absent: update header only (new service method e.g. `updateHeader()`), never touch allocations / master rows |
| 3 | `app/Domains/Production/Resources/Api/ProductionBomDetailResource.php` | 51–63+ | Master `items` with dropped fields, ids ×3, scrap ×2 | `materials[]` (master: quantity, alternative_quantity, gross_quantity, allocation_role, uom), `allocations[]` (all allocation fields + `operation: {id, sequence, name}`), `routing`, `routing_advisory`, `warnings`; ≥ 4 decimals |
| 4 | Controller `store()` / `update()` / `show()` eager loads | 113, 171 | `items.material.uom`, `items.product` | Add `operationMaterials.routingOperation`, `routing` |
| 5 | `duplicate()` 229–260 / `clone()` 267 | — | Saves a copy | BM-IMPL-03 #7 payload; keep `duplicate`, `clone` = documented alias |
| 6 | Tests | — | — | PATCH header only → allocations unchanged; response has both tiers |


---

## BM-IMPL-20: Final BOM Cleanup
* **Source Decision:** BM-31 (agreed 2026-11-11)
* **Status:** 🟢 Agreed — ⏭️ **Do LAST** (after the other BOM tasks are applied and user-verified)

| # | Item | Action |
| :-: | :--- | :--- |
| 1 | **New migration** | Drop `production_bom_items.material_scrap_percentage`; remove its reads (`ProductionBomItem` fillable / casts, `ProductionBomService` 518, resources) |
| 2 | `under_revision` status | Remove `ProductionBom::STATUS_UNDER_REVISION` (31), `isUnderRevision()` (~181), status comment (48), `ProductionBomPolicy.php` 33, `Api/ProductionBomApiController.php` 132, `submitApproval()` allowed statuses, lang keys / badges |
| 3 | Check covered leftovers are gone | BM-IMPL-03 #5, BM-IMPL-08 #7–#10 / #13, BM-IMPL-18 #6, RT-IMPL-19 markers |
| 4 | Done check | `grep -rn "under_revision\|material_scrap_percentage.*production_bom_items\|syncRoutingOperationMaterials\|isFormula" app resources database routes tests` returns nothing relevant; full test suite passes (BM-30) |

## Deferred Items

### BM-DEF-01: Scrap Rules and Routing Yield Connection
* **Source Decision:** BM-10 (marked 2026-11-11)
* **Status:** ⏭️ Deferred — needs detailed discussion and code check
* **Already decided:** scrap per allocation; master `gross_quantity` (BM-IMPL-04).
* **To discuss:** full scrap rules (meaning, limits, display on Tab 2 / show page / costing); how material scrap relates to Routing operation `expected_yield_percentage` (RT-16; `RoutingOperation.php` ~220 `input_qty = output_qty / (yield / 100)`) — overlap, stacking or kept apart.
* **Also:** note in `bom_routing_feature_exchange_tracker.md` — TRACK-15 Q1 partly answered by BM-04 / BM-10; TRACK-14 `total_quantity` reads `quantity` (BM-11).

### BM-DEF-02: All BOM Costing (Material + Routing)
* **Source Decision:** BM-21, BM-25, BM-26 and the cost parts of BM-22 … BM-24 (deferred 2026-11-11)
* **Status:** ⏭️ Deferred — discuss later
* **Decide:** option A (live) / B (save on save + approval, outdated warning — recommended, matches RT-19) / C (saved + current variance); cost field `unit_cost` vs `cost_price`; sub-BOM material cost = product `unit_cost` or sub-BOM saved total.
* **Affects:** `ProductionCostService.php` 13–29, `ProductionBomController::show()` 149–162, show page cost columns (BM-IMPL-15 #2), explosion costs (BM-IMPL-16), BM-22 … BM-26 cost parts.
* **Also decide (BM-25 / BM-26):** BOM routing cost from saved routing figures (RT-IMPL-13 §3 BOM row + RT-IMPL-14), labor / machine split, overhead note (RT-17), cost per unit of product; material cost = gross qty × unit cost, primary only; cost parts of BM-22 … BM-24 (master net / gross cost, alternative cost, operation cost share).

### BM-DEF-03: Downstream Use of BOM Data
* **Source Decision:** BM-29 (deferred 2026-11-11)
* **Status:** ⏭️ Deferred — explain in simpler steps and decide later
* **Covers:** where-used tab (`BomWhereUsedService.php` 15–60, by product not by pinned version); MRP engine, MRP shortage, production orders, plans, ECO impact reading BOM data — together with RT-32 (`routing_decisions.md`).
* **Not affected:** BM-IMPL-04 #5 and BM-IMPL-08 #7–#9, #12 stay agreed.
