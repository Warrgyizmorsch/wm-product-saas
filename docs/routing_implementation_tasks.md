# Routing — Implementation Task List

> **Purpose:** Code changes and open issues that come out of the agreed decisions in [`docs/routing_decisions.md`](routing_decisions.md).
> Each task carries the exact files, lines and data mappings so it can be implemented without re-investigation.
> **Rule:** Tasks are implemented only when the user says so. After implementation a task is marked **🛠️ Applied**; only the user marks it **✅ Verified** (passing tests ≠ verified).
> **Related:** Work Center tasks in [`docs/work_center_implementation_tasks.md`](work_center_implementation_tasks.md) (WC-RTG-01, WC-RTG-02 were moved here from that review).

## Status Legend
* 🟢 **Agreed** — Decision final, waiting for go-ahead to implement.
* 🟡 **Proposed** — Logged with a recommended fix; not yet confirmed by the user.
* 🛠️ **Applied** — Implemented; awaiting user verification.
* ✅ **Verified** — Verified by the user.
* ❌ **Cancelled** — Superseded by a later decision; kept for history.
* 📌 **Marked for Other Module** — Belongs to another module's UI/flow; tracked here so it is not lost.
* ⏸️ **Marked Issue — Decide Later** — Problem confirmed in code; solution not yet agreed.
* ⏭️ **Deferred** (RT-DEF-xx) — Agreed direction, but the work belongs to a deferred track; listed so nothing is lost.

---

## Summary Register

| Task ID | Title | Source Decision | Status |
| :--- | :--- | :--- | :--- |
| **RT-IMPL-01** | Remove leftover code that reads the dropped `routings.product_id` / `bom_id` | RT-01 | 🟢 Agreed |
| **RT-IMPL-02** | Add a manual "Retire" action (active / scheduled → historical) | RT-02 | 🟢 Agreed (BOM effect depends on RT-05) |
| **RT-IMPL-03** | Cancelled name+version reuse (service vs DB), stale "archives old version" comments, dead effective-date rule | RT-02 | 🟡 Proposed |
| **RT-IMPL-04** | Daily job: scheduled → active on start date; active → historical after end date | RT-03 | 🟢 Agreed (BOM effect depends on RT-05) |
| **RT-IMPL-05** | BOM routing advisory computed live from routing status (stop storing routing warnings in the BOM warning field) | RT-04 | 🟢 Agreed (implement with the BOM file) |
| **RT-IMPL-06** | One BOM handler for routing retire / expire / cancel: approved → inactive, pending → draft, impact list in confirmation | RT-05 | 🟢 Agreed |
| **RT-IMPL-07** | Routing API drops section / manual batch and skips operation validations — share one validator with the web form | RT-06 (also RT-07, RT-08, RT-10) | 🟢 Agreed |
| **RT-IMPL-08** | Alternatives: replace only a primary of the same routing; unique priority per primary; unambiguous lookup | RT-10 | 🟢 Agreed |
| **RT-IMPL-09** | Routing save keeps operations (update in place), soft-deletes removed ones, warns about affected BOMs; BOM shows deleted op and blocks save until fixed | RT-10 | 🟢 Agreed (BOM part with the BOM file) |
| **RT-IMPL-10** | Add "1 unit" notice; stop rounding times and unit costs to 2 decimals (form, DB columns, display) | RT-12 | 🟢 Agreed |
| **RT-IMPL-11** | Routing machine cost uses raw entered cost — share the WC-08 final-cost helper; remove dead Work Center fallback | RT-13 | 🟢 Agreed |
| **RT-IMPL-12** | Operator labor per machine = non-batch daily labor ÷ section operating hours ÷ active machines | RT-14 | 🟢 Agreed |
| **RT-IMPL-13** | Two setup times (per operation + new per piece); Cost / Unit and setup cost per run; yield on per-piece only; BOM cost formula | RT-16 | 🟢 Agreed |
| **RT-IMPL-14** | Calculate once in the form; backend checks then saves all figures; readers use saved values; snapshot numbers stored; outdated warnings | RT-19 | 🟢 Agreed |
| **RT-IMPL-15** | Clear both setup times whenever run time is cleared in the routing form | RT-22 | 🟢 Agreed |
| **RT-IMPL-16** | Routing import: section required, one resource, backend calculates figures from latest snapshot, draft-only safe update (no BOM allocation wipe) | RT-27 | 🟢 Agreed |
| **RT-IMPL-17** | Routing API output: saved values only, remove product block and duplicate keys, fix per-minute "cost_per_hour", add missing fields | RT-28 | 🟢 Agreed |
| **RT-IMPL-18** | Inactive resources: backend refuses on every path; approved-routing advisory; draft edit keeps and tags inactive resource, blocks save | RT-29 | 🟢 Agreed |
| **RT-IMPL-19** | Final cleanup: remove all commented-out decoupling code (40 markers, 13 files) | RT-34 | 🟢 Agreed — ⏭️ do LAST |
| **RT-DEF-01** | Switch downstream services from the old alternate-machines table to alternative operations; remove the old table | RT-11 | ⏸️ Deferred to RT-32 |
| **RT-ISSUE-01** | External operations: cost per unit × yield factor; web vendor check doesn't confirm vendor exists | RT-08 | ⏸️ Decide Later |

---

## RT-IMPL-01: Remove Leftover Code Reading the Dropped `routings.product_id` / `bom_id`
* **Source Decision:** RT-01 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

### Live code (runs today — always gets `null`)
| # | File | Lines | Current | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Controllers/Api/RoutingApiController.php` | 195–204 (new version number loop) | Uniqueness loop `->where('product_id', $routing->product_id)->where('version', …)` — `product_id` is always null, so it checks every product-less routing | Check uniqueness by **routing name + version** (RT-02 rule) — align with `RoutingService` revision logic |
| 2 | `app/Domains/Production/Models/Routing.php` | 223–226 (`scopeForProduct()`) | Filters on dropped column; no callers found (2026-11-10) | Remove |
| 3 | `app/Domains/Production/DTO/RoutingDTO.php` | 12–17, 38–40, 57–58 | `product_id`, `bom_id` (and `material_quantity_mode`) still declared and mapped, excluded from `toArray()` | Remove the properties and mappings |
| 4 | `app/Domains/Production/Listeners/UpdateCapacityPlanning.php` | 24 | Logs `$event->routing->product_id` (always null) | Remove the key (or log linked BOM ids) |
| 5 | `app/Exports/RoutingExport.php` | 149 (+ whole export) | `product_code` column from `$routing->product?->sku`; export rows are built per operation **material** | Remove `product_code`; review the export against the current model (materials now belong to the BOM — see RT-27 import) |
| 6 | `app/Domains/Production/Controllers/RoutingController.php` | create 43–60; edit ~145–160 | Loads unused `$boms` (empty collection "for view compatibility"), `$products` (all FG / SFG — leftover of routing product), `$rawMaterials` (old operation materials), `$selectedProductId` | Remove them and from `compact()`; keep `$workCenters`, `$vendors`, `$serviceProducts`, `$operationTypes` (RT-23) |

### Not in this task (belongs to deferred tracks)
| File | Lines | Why deferred |
| :--- | :--- | :--- |
| `app/Domains/Production/Services/ProductionOrderService.php` | 1331 (`$routing->product_id === $order->product_id` gate for material scaling — never true now) | Downstream execution → **RT-32** (TRACK-07) |
| `resources/views/modules/production/ecos/create.blade.php` | 65 (`data-product-id="{{ $routing->product_id }}"`) | ECO → **RT-33** (TRACK-09) |
| `app/Domains/Production/Services/RoutingService.php` | 140–160, 195–210, 300–330 (commented blocks using `product_id` / `bom_id`) | Commented-out `[INVERSION-DECOUPLE-TRACK-03]` code → **RT-34** cleanup pass |

---

## RT-IMPL-02: Manual "Retire" Action for Routings
* **Source Decision:** RT-02 rule 3 (agreed 2026-11-10)
* **Status:** 🟢 Agreed — **effect on linked BOMs depends on RT-05** (decide RT-05 before implementing)

### Today
* Routing actions: submit, approve, reject, cancel, duplicate (`app/Domains/Production/Routes/web.php` 178–182). No way to reach `historical`.
* `RoutingLifecycleService::handleBomsOnRoutingRetirement()` (191–233: active BOMs → inactive + audit; draft BOMs → warning) exists but is **never called**.

### Required Change
| # | Layer | File | Change |
| :-: | :--- | :--- | :--- |
| 1 | Route | `app/Domains/Production/Routes/web.php` (next to 178–182) | `POST routing/{routing}/retire` → `RoutingController::retire` (name `routing.retire`) |
| 2 | Controller | `app/Domains/Production/Controllers/RoutingController.php` | `retire()` — authorize (approve-level permission), optional comment, call service, flash result |
| 3 | Service | `app/Domains/Production/Services/RoutingService.php` | `retire(int $id, int $userId, ?string $comments)`: allowed from `active` / `scheduled` only; set `status = historical`, `effective_to = today`; `RoutingApproval` entry (action `Retired`); then the BOM handling decided in **RT-05** (likely `handleBomsOnRoutingRetirement()`) |
| 4 | Model | `app/Domains/Production/Models/Routing.php` | Helper `canBeRetired()` |
| 5 | UI | `resources/views/modules/production/routing/show.blade.php` (action bar), `index.blade.php` (row actions) | "Retire" button with confirmation explaining the BOM effect |
| 6 | BOM form | `ProductionBomController.php` 220, 263–270 | Already excludes `historical` from the list ✔ (edit keeps the BOM's current routing) |
| 7 | API | `app/Domains/Production/Controllers/Api/RoutingApiController.php` | Matching `retire` endpoint (optional) |
| 8 | Tests | `tests/Feature/Production/` | Retire allowed / refused by status; historical not selectable for new BOMs |

---

## RT-IMPL-03: Name + Version Reuse, Stale Comments, Dead Date Rule
* **Source Decision:** RT-02 rule 5 (logged 2026-11-10)
* **Status:** 🟡 **Proposed** — user has not confirmed yet.

| # | Problem | File | Lines | Proposed fix |
| :-: | :--- | :--- | :--- | :--- |
| 1 | Service allows reusing a **cancelled** routing's name + version (`whereNotIn('status', [cancelled])`), but the DB unique key `[tenant_id, name, version]` blocks it → raw database error | `RoutingService.php`; migration `2026_11_02_130000` line 37 | 387–403 (392) | Remove the cancelled exclusion so the service gives the clear "already exists" message (cancelled history stays unique) |
| 2 | Comments still say approval "archives old active version" | `RoutingService.php` | 181, 217–218 | Update comments to "no auto-retire (RT-02)" |
| 3 | `validateEffectiveDates()` Rule 3 (start date vs other active versions) only runs when `$productId > 0` — always 0 now → dead code | `RoutingLifecycleService.php` | 57–84 | Remove the product-based block (or re-base on name if the rule is still wanted — decide under RT-03) |
| 4 | Legacy BOM→Routing cascade methods `syncDraftRoutingsToApprovedBom()`, `archiveRoutingsForDeactivatedBoms()`, `handleRoutingsOnBomCancellation()` sit inside a `/* … */` comment block (`[INVERSION-DECOUPLE-TRACK-03]`, from line ~309) | `RoutingLifecycleService.php` | ~309–445 | Remove in the **RT-34** cleanup pass *(corrected 2026-11-10: they are commented out, not live)* |

---

## RT-IMPL-04: Daily Job — Routing Status by Dates
* **Source Decision:** RT-03 rule 5 (agreed 2026-11-10)
* **Status:** 🟢 Agreed — **historical BOM handling depends on RT-05** (same as Retire, RT-IMPL-02)

### Today
* No job or command changes Routing status when dates pass (search 2026-11-10: no scheduler entry, no command).
* `Routing::scopeActive()` (`app/Domains/Production/Models/Routing.php` 205–212) already treats `scheduled` with `effective_from <= today` as active, and `isCurrentlyActive()` (154–170) checks the dates — but the stored `status` stays `scheduled`, and screens show "Scheduled".
* An `active` Routing whose `effective_to` has passed stays `active`.

### Required Change
| # | Layer | File | Change |
| :-: | :--- | :--- | :--- |
| 1 | Command | new `app/Console/Commands/ProcessRoutingEffectiveDates.php` (`production:process-routing-dates`) | For each tenant: (a) `scheduled` with `effective_from <= today` → `active`; (b) `active` / `scheduled` with `effective_to < today` → `historical` |
| 2 | Service | `app/Domains/Production/Services/RoutingLifecycleService.php` | Methods `activateScheduled(Routing)` and `retireExpired(Routing)`; write a `RoutingApproval` audit row (`Activated (scheduled date reached)` / `Retired (end date passed)`); call the RT-05 BOM handling on retire (share code with RT-IMPL-02) |
| 3 | Schedule | `routes/console.php` (next to line 25) | `Schedule::command('production:process-routing-dates')->dailyAt('00:05')->withoutOverlapping()` |
| 4 | Tenancy | command | Run per tenant (`withoutGlobalScopes()` + explicit `tenant_id`), same pattern as `ProcessCalculationCountdowns` — see WC-IMPL-14 note on tenant context |
| 5 | Tests | `tests/Feature/Production/` | Scheduled routing activates on its start date; expired active routing becomes historical; BOM handling per RT-05 |

---

## RT-IMPL-05: BOM Routing Advisory Computed Live
* **Source Decision:** RT-04 rules 2–3 (agreed 2026-11-10)
* **Status:** 🟢 Agreed — implement together with the BOM file review

### Problem
Routing lifecycle methods write routing warnings into the BOM's single warning field, but `ProductionBom::refreshWarningStatus()` (`app/Domains/Production/Models/ProductionBom.php` 388–409) rewrites that field from **component checks only** whenever the BOM is updated or approved (`ProductionBomService.php` 136, 232). The routing warning is lost after the first BOM edit.

### Required Change
| # | File | Lines | Change |
| :-: | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Models/ProductionBom.php` | new | Accessor e.g. `getRoutingAdvisoryAttribute(): ?string` — returns *"Linked routing {routing_number} is {status}. This BOM can't be approved until the routing is active."* when `$this->routing` is missing or not `active`; `null` otherwise |
| 2 | `app/Domains/Production/Services/RoutingLifecycleService.php` | 222–228 (draft BOM warning on retirement), 268–275 (draft BOM warning on cancellation), 293–297 (rejection) | Stop writing `has_warning` / `warning_reason`; keep status changes and `ProductionBomApproval` audit rows |
| 3 | same | 200–205, 247–252 (active BOMs inactivated) | Keep or change per **RT-05** decision; the reason belongs in the audit row, not the warning field |
| 4 | `resources/views/modules/production/bom/show.blade.php` | ~173–180 (warning banner) | Show `$bom->routing_advisory` as its own banner, separate from component warnings |
| 5 | `resources/views/modules/production/bom/index.blade.php` | ~311–316 (warning icon) | Show a routing-status icon/tooltip from the accessor (eager-load `routing`) |
| 6 | `app/Domains/Production/Resources/Api/*Bom*` | — | Expose `routing_advisory` in the API resource (optional) |
| 7 | Tests | `tests/Feature/Production/` | Advisory present while routing is draft/pending; still present after editing the BOM; gone after routing approval |

---

## RT-IMPL-06: One BOM Handler for Routing Retire / Expire / Cancel
* **Source Decision:** RT-05 (agreed 2026-11-10)
* **Status:** 🟢 Agreed — used by **RT-IMPL-02** (Retire) and **RT-IMPL-04** (daily job); together with **RT-IMPL-05** (no stored routing warnings)

### Today (`app/Domains/Production/Services/RoutingLifecycleService.php`)
| Method | Lines | Approved BOMs | Pending BOMs | Draft BOMs | Called? |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `handleBomsOnRoutingCancellation()` | 236–280 | → inactive + warning + audit ✔ | **not handled** ✘ | stored warning | yes, from `transitionOnCancellation()` 165–185 |
| `handleBomsOnRoutingRetirement()` | 191–233 | → inactive + warning + audit | **not handled** ✘ | stored warning | **never** ✘ |

### Required Change
| # | Change |
| :-: | :--- |
| 1 | Replace both methods with one `handleBomsOnRoutingEnded(Routing $routing, string $event /* retired | expired | cancelled */, int $userId, ?string $comments)` |
| 2 | Approved (`ProductionBom::STATUS_ACTIVE` = `'approved'`) → `inactive` + `ProductionBomApproval` (action `Inactivated`, comment *"Automatically inactivated because linked routing {number} was {event}. Reason: …"*) |
| 3 | Pending approval → `draft` + `ProductionBomApproval` (action `Returned to Draft`, same comment pattern) |
| 4 | Draft → nothing stored (live advisory, RT-IMPL-05) |
| 5 | Do **not** write `has_warning` / `warning_reason` (RT-IMPL-05) |
| 6 | Call it from `transitionOnCancellation()` (`cancelled`), the new `retire()` (RT-IMPL-02, `retired`) and the daily job (RT-IMPL-04, `expired`) |
| 7 | Confirmation dialogs: `routing/show.blade.php` / `index.blade.php` Retire and Cancel buttons show the list of linked BOMs (`$routing->boms` with status) and the effect on each — e.g. a small endpoint `GET routing/{routing}/impact` or data rendered into the modal |
| 8 | Check BOM `scheduled` status (if used) — treat like approved |
| 9 | Tests: each event × each BOM status |

---

## RT-IMPL-07: Routing API — Same Operation Validation as the Web Form
* **Source Decision:** RT-06 rule 3 (agreed 2026-11-10); also needed for RT-07 (machine XOR batch), RT-08 (external), RT-10 (alternatives)
* **Status:** 🟢 Agreed

### Problem
| File | Lines | Today |
| :--- | :--- | :--- |
| `app/Domains/Production/Requests/Api/StoreRoutingApiRequest.php` | 56–70 | Rules for `work_center_id`, `machine_id`, `is_external`, `vendor_id` only — **no** `section_id`, `section_manual_production_id`, `is_alternative`, `replaces_operation_id`, `priority`, `cost_per_unit`, `resource_snapshot`; no `withValidator()` checks |
| `app/Domains/Production/Requests/Api/UpdateRoutingApiRequest.php` | 54–68 | Same |
| `app/Domains/Production/Controllers/Api/RoutingApiController.php` | 99, 127 | `RoutingDTO::fromArray($request->validated())` — fields without rules are dropped, so API operations can never have a section or batch |
| `app/Domains/Production/Resources/Api/RoutingDetailResource.php` | 52, 64 | Output already includes `section_id`, `section_manual_production_id` ✔ |

### Required Change
1. Move the operation rules + `withValidator()` checks from `StoreRoutingRequest.php` (rules ~59–84, checks ~105–400: sequences, queue threshold, internal/external, WC ↔ section, machine XOR batch, machine/batch ↔ section, alternatives) into **one shared place** (e.g. a trait or a `RoutingOperationValidator` class) used by the web Store/Update requests **and** both API requests.
2. API rules then include: `operations.*.section_id`, `operations.*.section_manual_production_id`, `operations.*.is_alternative`, `operations.*.replaces_operation_id` / `replaces_sequence`, `operations.*.priority`, and the other fields the web form accepts.
3. Make the `exists:` rules tenant-scoped (`Rule::exists(...)->where('tenant_id', …)`) — today they are plain `exists:production_work_center_sections,id` etc.
4. Leave out the commented-out BOM rule block (`[INVERSION-DECOUPLE-TRACK-03]` Rule 4, `StoreRoutingRequest.php` 267–408) when moving the code; delete it in RT-34.
6. **API follows the same save rule as the web form (RT-19 rule 2).** It must send the same operation fields (rates, `cost_per_unit`, labor / machine split, setup cost per run, `resource_snapshot`); the backend re-checks them with the same formula and **rejects discrepancies**, otherwise saves them as sent (no manual rates — RT-14 rule 5, RT-15 rule 4). The API requests and controller are **not updated yet** — they must be brought to the same rules as the web form. *(Replaces the earlier "ignored and recalculated" wording, 2026-11-10.)*
5. Tests: API create/update with section + machine, section + batch, both / neither (rejected), section of another Work Center (rejected).
7. **(RT-30)** Unused helpers `RoutingService::validateMachineBelongsToWorkCenter()` (579–583) and `validateSectionBelongsToWorkCenter()` (588–593) — reuse them in the shared validator or remove them (no callers on 2026-11-11).

---

## RT-ISSUE-01: External Operations — Cost per Unit and Vendor Check
* **Source Decision:** RT-08 (logged 2026-11-10; RT-08 itself is 🔁 provisional)
* **Status:** ⏸️ **Marked — Decide Later**

### 1. Cost per unit
| File | Lines | Today |
| :--- | :--- | :--- |
| `app/Domains/Production/Services/RoutingService.php` | 433–438 (`syncOperations()`) | When `cost_per_unit` not sent: `subcontract_cost_per_unit × (100 ÷ expected_yield_percentage)` |
| `app/Domains/Production/Models/RoutingOperation.php` | 238–241 (`estimatedCostPerUnit()`) | Same yield factor for external |
| `routing/create.blade.php`, `edit.blade.php` | external toggle / row cost | Form-side calculation (check whether yield is applied there too) |
**To decide (with RT-16):** does yield apply to subcontract cost (vendor charges per good unit vs per input unit)?

### 2. Vendor check
| File | Line | Today |
| :--- | :--- | :--- |
| `app/Domains/Production/Requests/StoreRoutingRequest.php` | 81 | `'operations.*.vendor_id' => 'nullable|integer'` — no exists / tenant check |
| `app/Domains/Production/Requests/UpdateRoutingRequest.php` | 84 | Same |
| `Requests/Api/StoreRoutingApiRequest.php` / `UpdateRoutingApiRequest.php` | 70 / 68 | `exists:vendors,id` (not tenant-scoped) |
**Direction noted (not agreed):** tenant-scoped exists check on every path (fits into RT-IMPL-07's shared validator).

---

## RT-IMPL-08: Alternative Operation Rules
* **Source Decision:** RT-10 rules 1–2 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

| # | Rule | File | Lines | Change |
| :-: | :--- | :--- | :--- | :--- |
| 1 | Replaced operation must be a **primary** | `StoreRoutingRequest.php` / `UpdateRoutingRequest.php` (→ shared validator, RT-IMPL-07) | 248–262 | Resolve the target row in the submitted operations; error if it is itself `is_alternative` |
| 2 | Replaced operation must be in the **same routing** | `app/Domains/Production/Services/RoutingService.php` | 503–511 | Remove the tenant-wide fallback (`RoutingOperation::where('tenant_id')->where('id')`, 509); resolve only within this routing's operations |
| 3 | Unambiguous lookup | same | 499–508 | Use explicit fields: `replaces_sequence` (form) or `replaces_row_uid`; treat `replaces_operation_id` only as an id of this routing — don't try it as a sequence first (505) |
| 4 | Unique priority per primary | validator | — | Error when two alternatives of the same primary share a priority |
| 5 | Tests | `tests/Feature/Production/` | — | Alt of alt rejected; alt of other routing's op rejected; duplicate priority rejected |

---

## RT-IMPL-09: Routing Save Keeps Operations; Removed Operations Soft-Deleted; BOMs Flagged
* **Source Decision:** RT-10 rules 4–7 (agreed 2026-11-10)
* **Status:** 🟢 Agreed — routing part now; **BOM part implement with the BOM file** (with RT-IMPL-05)

### Problem
* `RoutingService::syncOperations()` (`app/Domains/Production/Services/RoutingService.php` 413–415) **force-deletes all operations and recreates them** on every save.
* `production_routing_operation_materials.routing_operation_id` → **cascadeOnDelete** (migration `2026_07_01_000002`, 15–18), so every save **permanently deletes all BOM operation allocations** for that Routing (BOMs may link draft Routings — TRACK-01). Operation ids also change on every save.
* Force delete was used because of the unique key `[routing_id, sequence]` (`2026_06_30_000004`, 77) clashing with soft-deleted rows.

### Required Change — Routing side
| # | Change |
| :-: | :--- |
| 1 | `syncOperations()`: match submitted rows to existing operations by `row_uid` / operation id; **update in place**; create new rows; **soft-delete** only the operations the user removed |
| 2 | Unique sequence vs soft-deleted rows: change the unique key to ignore soft-deleted rows (e.g. unique on `[routing_id, sequence, deleted_at]`, or re-number deleted rows' sequence, or a generated "active sequence" column) — pick the MySQL + SQLite (tests) compatible option at implementation |
| 3 | Change the FK on `production_routing_operation_materials.routing_operation_id` from cascade to **restrict / no action** (soft delete never fires it, but a later hard delete must not wipe BOM data silently) |
| 4 | Before saving, the form (or a pre-save check endpoint) lists removed operations that have BOM allocations: *"Operation OP-XXX is used by BOM-… (n materials). It will be permanently removed from this routing; those BOMs must be fixed."* — **warn, allow** |
| 5 | Pass 2 / Pass 3 (replacements, predecessors) work on the kept / new operation models (ids stable) |
| 6 | Audit: `RoutingApproval` / activity entry "Operation OP-XXX removed" (optional) |

### Required Change — BOM side (with the BOM file)
| # | Change |
| :-: | :--- |
| 7 | Live advisory on the BOM: if any `production_routing_operation_materials` row of the BOM points to a **soft-deleted** operation → *"Routing changed: operation OP-XXX was removed from routing RTG-X. Move or remove its materials."* (same accessor approach as RT-IMPL-05) |
| 8 | BOM edit (`resources/views/modules/production/bom/edit.blade.php`): load deleted operations that still have this BOM's allocations (`withTrashed()`); render their card with a **"Deleted"** tag; allow moving materials to another operation or removing them |
| 9 | BOM update / submit / approve validation (`UpdateProductionBomRequest`, `ProductionBomService`): **reject** while any allocation points to a deleted operation (*"Operation OP-XXX was removed from the routing. Move or remove its materials before saving."*) |
| 10 | After the BOM is fixed, the deleted operation has no allocations for that BOM → its card is no longer shown |
| 11 | Tests: routing edit removes op used by a BOM → warning, op soft-deleted, BOM allocations kept; BOM edit shows deleted card; save blocked until moved; then succeeds |

---

## RT-DEF-01: Downstream Services Still Use the Old Alternate-Machines Table
* **Source Decision:** RT-11 (agreed 2026-11-10)
* **Status:** ⏭️ **Deferred to RT-32** (downstream execution / scheduling)

### Where the old table is still read
| Service | File | Lines |
| :--- | :--- | :--- |
| Capacity leveling | `app/Domains/Production/Services/CapacityLevelingService.php` | 14, 164–183 |
| Capacity planning | `app/Domains/Production/Services/CapacityPlanningService.php` | 15, 76, 98, 142, 168, 506–510 |
| Planning exceptions | `app/Domains/Production/Services/PlanningExceptionService.php` | 397 |
| Production readiness | `app/Domains/Production/Services/ProductionReadinessService.php` | 15, 801 |
| Schedule scenarios | `app/Domains/Production/Services/ProductionScheduleScenarioService.php` | 13, 250 |
| *(others — re-run search)* | `grep -rln "alternateMachines\|RoutingOperationAlternateMachine" app/Domains/Production/Services` (7 files on 2026-11-10) | — |

### Where it is still written
| Place | File | Line |
| :--- | :--- | :--- |
| Demo seeder | `database/seeders/CrusherMachineProductionSeeder.php` | 857 |
| Tests | `tests/Feature/Production/CapacityLevelingTest.php` 129; `EnterpriseSchedulingTest.php` 245, 645; `ScheduleConflictScopeAndBackwardReservationTest.php` 201 | — |

### When resumed
1. Replace "alternate machines of an operation" with "alternative operations of the operation" (`RoutingOperation::alternativeOperations()`, ordered by priority) — each alternative brings its own section / machine or batch / times.
2. Remove `RoutingOperation::alternateMachines()` (`RoutingOperation.php` 179–181), the model, the seeder rows, and rewrite the tests.
3. Drop the table with a migration.

---

## RT-IMPL-10: 1-Unit Notice; No Rounding of Times and Unit Costs
* **Source Decision:** RT-12 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

### 1. Notice
Add *"Values shown below are calculated for 1 unit only. Excludes facility overhead."* (overhead part from RT-17) above the operations table in `resources/views/modules/production/routing/create.blade.php`, `edit.blade.php`, `show.blade.php` (new `lang/en/production.php` key).

### 2. No rounding — form
| File | Lines | Today | Required |
| :--- | :--- | :--- | :--- |
| `routing/create.blade.php` (+ same in `edit.blade.php`) | 1553, 1562 (`calculateUnitCost()`) | `.toFixed(2)` → saved in hidden `cost_per_unit` (232) | Return the unrounded number (or 6 decimals) for the hidden field |
| same | 1494, 1519 (machine / batch speed → `processing_time_minutes`) | `.toFixed(2)` | Keep full precision (e.g. 4 decimals) |
| same | 424–436, 505–509 (resource cost spans) and any other `toFixed(2)` on rates / costs | 2 decimals | Show at least 4 decimals for per-unit / per-minute values |

### 3. No rounding — database
| Column | Migration | Today | Required |
| :--- | :--- | :--- | :--- |
| `production_routing_operations.setup_time_minutes` | `2026_06_30_000004` line 43 | decimal(10,2) | decimal(12,4) (new migration) |
| `production_routing_operations.processing_time_minutes` | same, line 45 | decimal(10,2) — 0.0167 min stored as 0.02 (≈ 20 % error at 3,600 u/h) | decimal(12,4) |
| `wait_time_minutes` | same | check | same treatment |
| `cost_per_unit` | `2026_11_04_120000` line 16 | decimal(15,4) ✔ | keep |
| `labor_cost_rate`, `machine_cost_rate` | `2026_06_30_000004` 55, 57 | decimal(12,4) ✔ | keep |
Also check `RoutingOperation::$casts` and `RoutingOperationDTO` don't round, and request rules allow 4 decimals.

### 4. No rounding — display
* Routing show (`routing/show.blade.php` 210–236, 386–448) and simulation modal: unit costs via `format_currency($value, 4)` (helper accepts `$decimals`, `app/helpers.php` 302) — or a small helper `format_unit_cost()` that shows up to 4 significant decimals without trailing zeros.
* Times via `format_duration()` (already shows seconds).
* Same rule for BOM / work center unit costs → note for the BOM file and `WC-FINAL-01`.

---

## RT-IMPL-11: One "Final Machine Operating Cost" Helper; Remove Dead Fallback
* **Source Decision:** RT-13 rules 2, 5 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

| # | File | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Models/Machine.php` (or a small service) | new | — | `finalOperatingCostPerHour(): float` = entered `operating_cost_per_hour` if > 0, else `consumption_per_hour × energy_rate_per_unit` if > 0, else 0 (WC-08). Optionally also return `base_energy_cost`, `additional_cost`, `deductibles` for the snapshot |
| 2 | `app/Domains/Production/Services/SectionCalculationService.php` | 526–564 (`calculateSectionMachines()`) | Inline WC-08 logic | Use the helper (keep the additional / deductible breakdown) |
| 3 | same | 1467 (`getSectionResourceCosts()`) | `$m->operating_cost_per_hour` raw | Use the helper for `operating_cost_per_hour`, `machine_cost_per_unit`, `cost_per_unit` |
| 4 | `app/Domains/Production/Services/RoutingService.php` | 453–458 (`syncOperations()`) | `$machine->operating_cost_per_hour / 60` raw | Use the helper |
| 5 | same | 467–471 | `elseif ($opDto->work_center_id …) $wc->cost_per_hour / 60` (column dropped) | Remove the branch |
| 6 | `app/Domains/Production/Models/RoutingOperation.php` | `estimatedCostPerUnit()` (~200–245) | check for raw machine cost / Work Center fallback | Align with the helper / remove fallback |
| 7 | Tests | — | — | Machine with blank operating cost but consumption × rate → routing machine cost = base energy cost |

---

## RT-IMPL-12: Operator Labor per Machine — Average over Section Operating Hours
* **Source Decision:** RT-14 (agreed 2026-11-10) — resolves **WC-RTG-01** and the routing part of **WC-ISSUE-10**
* **Status:** 🟢 Agreed — salary input depends on **WC-IMPL-07** (HRMS monthly CTC) and currency on **WC-ISSUE-12**

### Today (`app/Domains/Production/Services/SectionCalculationService.php`, `getSectionResourceCosts()`)
| Lines | Step |
| :--- | :--- |
| 1410–1416 | Collect employee assignment ids in any batch of the section |
| 1418–1443 | For each non-batch active employee: `hourly = (current_salary / 30) / shift hours` (8 h fallback) → **summed** |
| 1446–1458 | For each non-batch active contractor allocation: `headcount × rate` → **summed** |
| 1460–1463 | `operator_labor_cost_per_machine_per_hour = Σ hourly ÷ active machines` |

### Required Change
1. Sum **daily cost** instead of hourly rates:
   * employee: daily salary (`HRMS monthlyCtc() ÷ 30` — WC-IMPL-07);
   * contractor: `headcount × pay grade rate × allocation shift clock hours` (WC-19).
2. `operator_labor_cost_per_machine_per_hour = (Σ daily cost ÷ section operating hours) ÷ active machines` — operating hours from `calculateOperatingHours($section)` (WC-03); 0 when hours = 0 or no active machines.
3. Keep returning `total_unassigned_labor_cost_per_hour` = Σ daily cost ÷ operating hours (rename / add `non_batch_daily_labor_cost` for the snapshot).
4. Better: read these figures from the **latest section snapshot** if fresh enough, or reuse `calculateSectionLabor()` results, so routing and the Work Center calculation share one labor source — decide at implementation.
5. Update `resource_snapshot` keys saved on the operation and the routing show page explanation text (e.g. *"Non-batch labor ₹3,200/day ÷ 16 h ÷ 1 machine = ₹200/hr"*).
6. Tests: two shifts, one machine → rate = half of today's.

### Also update
* `docs/work_center_implementation_tasks.md` **WC-RTG-01** → resolved by RT-14 / RT-IMPL-12.

---

## RT-IMPL-13: Setup per Operation + Setup per Piece; Correct Cost Formulas
* **Source Decision:** RT-16 (agreed 2026-11-10)
* **Status:** 🟢 Agreed — BOM cost part implement with the BOM file; downstream part → RT-32

### 1. Database
| # | Change | Migration |
| :-: | :--- | :--- |
| 1 | Rename / relabel `production_routing_operations.setup_time_minutes` → meaning **setup per operation (once per run)** — choose name at implementation (e.g. `setup_time_per_run_minutes`), or keep the column name and relabel everywhere if renaming 40+ references is too risky | new |
| 2 | Add `setup_time_per_piece_minutes` **decimal(12,4) nullable** | new |
| 3 | Both at 4 decimals (RT-IMPL-10) | same |
| 4 | Existing rows: values stay in the per-operation column (current data is demo data — TRACK-08); per-piece = null | — |

### 2. Every place that reads `setup_time_minutes` (40 references, 2026-11-10)
`grep -rln "setup_time_minutes" app resources/views database/seeders`:
* **Routing:** `RoutingController.php`, `RoutingOperationDTO.php`, `RoutingOperation.php`, `Routing.php`, `StoreRoutingRequest.php`, `UpdateRoutingRequest.php`, `Api/StoreRoutingApiRequest.php`, `Api/UpdateRoutingApiRequest.php`, `RoutingDetailResource.php`, `RoutingService.php`, `RoutingCostService.php`, `routing/create.blade.php`, `edit.blade.php`, `show.blade.php`, `RoutingExport.php`, `ProductionMasterImportService.php`
* **BOM (with the BOM file):** `ProductionCostService.php` 34–110, `bom/create.blade.php`, `bom/edit.blade.php`, `bom/show.blade.php`
* **Downstream (RT-32):** `ProductionOrderService.php` 205, 223, 1360, 1379; `MrpEngineService.php` 43, 55; `ProductionPlanOperation.php` 21, 28; `plans/show.blade.php`; `SchedulingService.php`, `MesExecutionService.php`; `ProductionEcoImpactAnalysisService.php` (ECO, RT-33)
* **Seeders:** `CrusherMachineProductionSeeder`, `FurnitureManufacturingDemoSeeder`, `FurnitureProductionSeeder`, `PoonaRadiatorsProductionSeeder`, `ProductionBomSeeder`, `ProductionRoutingBomDemoSeeder`, `ProductionVariationDemoSeeder`, `TableManufacturingProductionSeeder`

### 3. Formulas
| Figure | Formula |
| :--- | :--- |
| Cost / Unit (routing) | `(setup_per_piece + processing) × (labor_rate + machine_rate) × (100 ÷ yield)` |
| Setup cost per run | `setup_per_operation × (labor_rate + machine_rate)` (no yield) |
| Routing header totals (`RoutingCostService`, show page) | Σ primary Cost / Unit; Σ primary setup cost per run (shown separately) |
| BOM cost (`ProductionCostService`) | `base_quantity × Σ Cost / Unit + Σ setup cost per run` (instead of `(setup + run) × rate × base_qty × yield`) |
| Cycle time per piece | `setup_per_piece + processing`; setup per run shown separately |

### 4. UI
* Routing form: two inputs ("Setup / run", "Setup / piece"), both optional; tooltip explaining the difference; live Cost / Unit + "Setup cost per run" (`create.blade.php` / `edit.blade.php`, `calculateUnitCost()` 1548–1563).
* Routing show: columns for both setups; header "Setup cost per run"; simulation modal (RT-24) uses the new formulas.
* Import / API / export: new column.

---

## RT-IMPL-14: Calculate Once, Save Everything, Snapshot Numbers, Outdated Warnings
* **Source Decision:** RT-19 (agreed 2026-11-10)
* **Status:** 🟢 Agreed — builds on RT-IMPL-10 (no rounding), RT-IMPL-11 (final machine cost), RT-IMPL-12 (operator labor), RT-IMPL-13 (two setups); BOM reading part with the BOM file

### 1. One calculator in the form
* `resources/views/modules/production/routing/create.blade.php` / `edit.blade.php`: replace `calculateUnitCost()` (1548–1563) and the rate-filling code (1494–1529) with **one** JS calculator returning: `processing_time_minutes`, `labor_cost_rate`, `machine_cost_rate`, `labor_cost_per_unit`, `machine_cost_per_unit`, `cost_per_unit`, `setup_cost_per_run`, `cycle_time_per_piece`.
* Share `create` / `edit` code (today duplicated) — e.g. a Blade partial or JS module.

### 2. Backend: check, then save as sent
| # | File | Change |
| :-: | :--- | :--- |
| 1 | new `app/Domains/Production/Services/RoutingOperationCostCalculator.php` | PHP mirror of the same formula (used only to **check**) |
| 2 | shared operation validator (RT-IMPL-07) | Re-run the formula on submitted inputs + current resource values; on mismatch beyond a tiny tolerance (e.g. 0.0001) → error *"Operation OP-XXX: cost does not match the selected machine / batch. Reload the resource and save again."*; also error when the resource doesn't belong to the section, is inactive, etc. |
| 3 | `app/Domains/Production/Services/RoutingService.php` 433–473 | Remove the recalculation fallback (and the `$wc->cost_per_hour` branch 467–471); save submitted values as they are |
| 4 | API requests / controller | Same fields, same checks (RT-IMPL-07 item 6) |

### 3. Save every figure (new columns where missing) — `production_routing_operations`
| Figure | Today | Change |
| :--- | :--- | :--- |
| `cost_per_unit` | ✔ decimal(15,4) | keep |
| `labor_cost_rate`, `machine_cost_rate` (per minute) | ✔ decimal(12,4) | keep (saved copies — RT-14 rule 5) |
| `labor_cost_per_unit`, `machine_cost_per_unit` | only inside `resource_snapshot` (run-only) | **new columns** decimal(15,4) |
| `setup_cost_per_run` | — | **new column** decimal(15,4) (RT-16) |
| `setup_time_per_piece_minutes` | — | new (RT-IMPL-13) |
| `costs_calculated_at` | — | **new** timestamp |
| `section_calculation_id` | — (not saved anywhere today) | **new column**, FK → `production_work_center_section_calculations.id` (nullable, no cascade) — the exact section snapshot the figures came from (for tracking / outdated check) |
| `section_snapshot_number` | — | **new column** (unsigned int) — human-readable snapshot number (#N) shown in the UI (WC-38) |
| `work_center_calculation_id` | — | **new column** (nullable FK) — optional, the Work Center roll-up snapshot of the same batch |

### 4. Snapshot contents (`resource_snapshot`, JSON) — extend
Today (`create.blade.php` 1348–1380): type, machine / batch id, code, name, capacity, speed, operating cost per hour, operator labor per hour, run-only per-unit costs.
Add: `work_center_id` + code, `section_id` + code, **`section_calculation_id` + `section_snapshot_number`** (and `work_center_calculation_id` / number) of the section snapshot whose figures were used, rate sources (e.g. operator labor = non-batch daily labor ÷ hours ÷ machines), `calculated_at`. Rename the run-only per-unit keys (e.g. `run_cost_per_unit`) so they are not confused with the operation's `cost_per_unit`.
* **Decided (RT-19 rule 7):** the section resources endpoint (`GET sections/{section}/resources` → `WorkCenterSectionController::resources()` line 73 → `SectionCalculationService::getSectionResourceCosts()` 1395–1515) returns figures **from the latest section snapshot** plus its id / number, instead of calculating live:
  * `SectionCalculationService::calculateAndSave()` (~1310–1340) must store the routing figures in the section snapshot (`calculation_details`): per active machine — id, code, capacity, **final operating cost per hour** (RT-IMPL-11), run cost per unit; per active batch — id, name, shift, capacity, **labor per hour**; **operator labor per machine per hour** (RT-IMPL-12 formula).
  * Endpoint response adds `section_calculation_id`, `snapshot_number`, `calculated_at`, `has_pending_changes` (section in the Work Center's `pending_calculation_section_ids`).
  * No snapshot → endpoint returns `not_calculated: true`; the form shows *"Section SEC-X has not been calculated yet. Recalculate the Work Center first."* and the backend check refuses the save.
  * Pending changes → form warning; save allowed.
  * `getSectionResourceCosts()` live calculation is then only used inside the section calculation itself (or removed).

### 5. Readers use saved values only (no recalculation)
| # | Place | File | Change |
| :-: | :--- | :--- | :--- |
| 1 | Routing totals | `RoutingCostService.php` 40–190 | Sum saved `cost_per_unit`, `labor_cost_per_unit`, `machine_cost_per_unit`, `setup_cost_per_run`; remove snapshot / live / `cost_per_hour` lookups |
| 2 | Model helper | `RoutingOperation.php` `estimatedCostPerUnit()` 229–275 | Return saved `cost_per_unit`; remove the lookup chain (or remove the method) |
| 3 | Show page resource cards | `routing/show.blade.php` 343–415 | Show saved values + snapshot; remove live `getSectionResourceCosts()` fallback; label run-only vs full cost clearly |
| 4 | BOM cost | `ProductionCostService.php` 34–110 | `base_quantity × Σ saved cost_per_unit + Σ saved setup_cost_per_run` (BOM file) |
| 5 | Production orders / MRP | → RT-32 | read saved values |
| 6 | Old routings without saved figures | — | show "Costs not calculated — open and save the routing" (no silent live calculation) |

### 6. Approval record
On approve (`RoutingLifecycleService::transitionOnApproval()` 95–150): store a routing-level approval snapshot (approver, time, Σ costs, per-operation figures + section snapshot numbers) — e.g. JSON on the `RoutingApproval` row or a `cost_snapshot` column on `routings`.

### 7. Outdated detection & warnings
| Check (compare saved snapshot with current) | Warning |
| :--- | :--- |
| Section has a newer calculation snapshot than `section_snapshot_number` **and** the figures used differ | *"Costs outdated — section SEC-X has a newer calculation (snapshot #N)."* |
| Machine final operating cost / capacity changed; batch capacity or labor changed | *"Machine MC-XX cost changed (was ₹A/hr, now ₹B/hr)."* |
| Machine / batch / section inactive or deleted | per RT-29 |
* Badge on routing index rows, banner on routing show (per-operation detail), and on BOMs using the routing (BOM file).
* Text for approved routings: *"Create a new routing version to update."*; for drafts: *"Open and save the routing to refresh costs."*
* Computed when the page is displayed by **comparing numbers only** — no cost recalculation.

---

## RT-IMPL-15: Clear Setup Times Whenever Run Time Is Cleared
* **Source Decision:** RT-22 rule 4 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

### Places where run time is cleared today (`resources/views/modules/production/routing/create.blade.php`; same in `edit.blade.php`)
| # | Function | Lines (approx.) | Run time | Setup today | Required |
| :-: | :--- | :--- | :--- | :--- | :--- |
| 1 | `workCenterChanged()` | 1429–1452 (1438) | `processing_time_minutes = '0.00'` | kept ✘ | also clear setup per operation + setup per piece |
| 2 | `sectionChanged()` | 1454+ (~1462) | cleared | kept ✘ | same |
| 3 | `onMachineSelect()` / machine cleared | ~1498–1504 (1502) | cleared when no machine | kept ✘ | same |
| 4 | `onManualBatchSelect()` / batch cleared or batch without speed | ~1515–1529 (1521, 1527) | cleared | kept ✘ | same |
| 5 | `onExternalToggle()` | 1389–1415 (1402) | cleared | **already cleared** ✔ | keep |

### Notes
* "Setup" = both fields after RT-IMPL-13: setup per operation (`setup_time_minutes`, relabelled) and setup per piece (new).
* Use one small helper (e.g. `clearResourceDerivedTimes(operation)`) called from all five places, so the rule stays in one spot.
* Cost / Unit and setup cost per run are recalculated (become 0) after clearing.

### RT-IMPL-10 addition (RT-25, 2026-11-10): durations under 1 second
| # | File | Lines | Change |
| :-: | :--- | :--- | :--- |
| 1 | `app/helpers.php` | 432+ (`format_duration()`) | If `0 < minutes × 60 < 1` → return `"< 1s"` (instead of `"0s"`); 0 or empty stays `"0s"` |
| 2 | `resources/views/modules/production/routing/show.blade.php` | `routeSimulator.formatDuration()` | Same rule in JS |
| 3 | same | 460, 466, 472 (`number_format($op->setup/processing/wait_time_minutes, 1)` subtitles) | Show real minutes with 4 decimals, e.g. `(0.0040 min)` |
| 4 | same | spec cards Run Speed (`number_format($speedMin, 2)`) | 4 decimals |
| 5 | routing `create.blade.php` / `edit.blade.php` | any duration display | Same rule |

---

## RT-IMPL-16: Routing Import — Same Rules, Backend Calculates, Safe Update
* **Source Decision:** RT-27 (agreed 2026-11-11)
* **Status:** 🟢 Agreed — uses the calculator from RT-IMPL-14, the in-place save from RT-IMPL-09, the shared validator from RT-IMPL-07

### File: `app/Domains/Production/Services/ProductionMasterImportService.php`
| # | Area | Lines | Today | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | Validation — section | 499–510 | Checked only if `section_code` given | **Required** for internal operations (RT-06) |
| 2 | Validation — resource | ~512–530 | "both machine and batch" rejected; "neither" not caught | Also reject **neither** (RT-07); machine / batch must be active |
| 3 | Validation — alternatives | ~536–545 | replaces + priority ≥ 1 | Also: replaced op must be a primary; unique priority per primary (RT-IMPL-08) |
| 4 | Validation — vendor | — | — | External op: vendor exists for the tenant (RT-ISSUE-01 when decided) |
| 5 | Prefer reusing the shared operation validator (RT-IMPL-07) instead of a separate copy | 490–560 | separate copy | shared |
| 6 | Figures | create 838–856, update 779–830 | No rates / costs / snapshot; `processing_time_minutes` from the CSV | Compute with `RoutingOperationCostCalculator` (RT-IMPL-14) from the **latest section snapshot**: run time = 60 ÷ capacity, rates, `cost_per_unit`, labor / machine per unit, `setup_cost_per_run`, `resource_snapshot`, `section_calculation_id`, `section_snapshot_number`, `costs_calculated_at`; setup times + yield from the file |
| 7 | No section snapshot | — | — | Reject the row: *"Section SEC-X has not been calculated yet."* |
| 8 | Update — status | ~779 | No status check | Only **draft** routings; others → row error *"Only draft routings can be updated by import."* |
| 9 | Update — fields | 791–804 | Saves only basic fields (no section / batch / alternative / priority) | Save **all** fields, same as create |
| 10 | Update — BOM allocations | 786–787 | `RoutingOperationMaterial::whereIn(...)->delete()` | **Remove** — never delete BOM allocations (RT-10) |
| 11 | Update — operations | 788 | `forceDelete()` all, then recreate | Update in place by `operation_number`; soft-delete removed ones; affected BOMs flagged (RT-IMPL-09) |
| 12 | Commented material blocks | 806–818, 870–882 | `[INVERSION-DECOUPLE-TRACK-11]` | Delete in RT-34 cleanup |

### Also
* Import template / sample export: add the new columns and remove material columns (`app/Exports/` routing sample, `RoutingExport.php` — see RT-IMPL-01 #5).
* Setup per piece column (RT-IMPL-13).
* Tests: `tests/Feature/Production/MasterDataImportExportTest.php` — section required, neither resource rejected, figures saved, update of approved routing rejected, BOM allocations kept.

---

## RT-IMPL-17: Routing API Output — Saved Values, One Key per Value, Correct Units
* **Source Decision:** RT-28 rules 2–3 (agreed 2026-11-11)
* **Status:** 🟢 Agreed — after RT-IMPL-13 / RT-IMPL-14 (new columns exist)

### File: `app/Domains/Production/Resources/Api/RoutingDetailResource.php` (30–85)
| # | Today | Required |
| :-: | :--- | :--- |
| 1 | `product` block (`whenLoaded('product')`, line 31) | Remove (RT-01); optionally add `boms` (linked BOMs, RT-26) |
| 2 | `sequence` + `operation_sequence` | keep `sequence` |
| 3 | `name` + `operation_name` | keep `name` |
| 4 | `processing_time_minutes` + `run_time_minutes` | keep `processing_time_minutes` |
| 5 | `wait_time_minutes` + `queue_time_minutes` | keep `wait_time_minutes` |
| 6 | `cost_per_hour` = `labor_cost_rate + machine_cost_rate` (per-minute values labelled per hour) | Remove; add `labor_cost_rate_per_minute`, `machine_cost_rate_per_minute` |
| 7 | `cost_per_unit` = `estimatedCostPerUnit()` (recalculated) | Saved `cost_per_unit` |
| 8 | missing | `labor_cost_per_unit`, `machine_cost_per_unit`, `setup_cost_per_run`, `setup_time_per_piece_minutes` (RT-IMPL-13), `expected_yield_percentage` |
| 9 | missing | external: `is_external`, `vendor` (id, name), `subcontract_cost_per_unit`, `subcontract_lead_time_days`, `dispatch_buffer_days`, `return_buffer_days`, `material_supply_type`, `subcontract_service_product_id` |
| 10 | missing | traceability: `resource_snapshot`, `section_calculation_id`, `section_snapshot_number`, `costs_calculated_at` |
| 11 | missing (routing level) | saved totals (Σ primary Cost / Unit, Σ setup cost per run, cycle time), approval info (approved by / at), outdated flag (RT-IMPL-14 §7) |

### Also
* `app/Domains/Production/Controllers/Api/RoutingApiController.php` — eager-load what the resource needs; remove `product` loads.
* API docs: `app/Domains/Production/docs/api/ENDPOINTS.md` routing section (old module docs — update with the business-rules pass).
* Tests: API response shape (no duplicate keys, saved values).

---

## RT-IMPL-18: Inactive Resources — Backend Check, Approved Advisory, Draft Edit Tag
* **Source Decision:** RT-29 (agreed 2026-11-11) — resolves **WC-RTG-02**
* **Status:** 🟢 Agreed — "active" for machines depends on **WC-MACH-01** (idle = active)

| # | Area | File | Change |
| :-: | :--- | :--- | :--- |
| 1 | Backend check | shared operation validator (RT-IMPL-07) used by `StoreRoutingRequest`, `UpdateRoutingRequest`, API requests, import (RT-IMPL-16) | Refuse inactive / deleted Work Center, section (`status != active`), machine (`status != active`, idle per WC-MACH-01), batch (`active = false`): *"Machine MC-XX is inactive. Choose another machine."* |
| 2 | Approved advisory | `resources/views/modules/production/routing/show.blade.php` (operations table + header banner), `index.blade.php` (row badge) | Per operation: *"OP-XXX uses {type} {code}, which is currently {status}."*; header: *"N operations use inactive resources — create a new routing version to update."* Computed by comparing statuses only (part of RT-IMPL-14 §7 outdated checks) |
| 3 | Draft edit — keep and tag | `resources/views/modules/production/routing/edit.blade.php`; `RoutingController::edit()` ~145–160 | Load the operation's current section / machine / batch even if inactive (and `withTrashed()` for deleted); add it to that row's dropdown tagged **"Inactive"** (or "Deleted"); never clear it silently |
| 4 | Draft edit — block save | backend check (#1) | Save refused while any row keeps an inactive / deleted resource; the form highlights the row |
| 5 | BOMs using the routing | BOM file | Show the routing's inactive-resource advisory (via RT-IMPL-05 / RT-IMPL-14 §7) |
| 6 | Tests | `tests/Feature/Production/WorkCenterRoutingIntegrationTest.php` | Re-check what "approved routing safeguards for inactive resources" asserts today; add: API save with inactive machine rejected; edit draft keeps inactive resource tagged; approved routing shows advisory |

---

## RT-IMPL-19: Final Cleanup — Remove Commented-Out Decoupling Code
* **Source Decision:** RT-34 (agreed 2026-11-11)
* **Status:** 🟢 Agreed — ⏭️ **Do LAST** (after the other routing tasks are applied and user-verified)

### Search
`grep -rn "INVERSION-DECOUPLE\|DECOUPLE-PRODUCT-INDEPENDENT\|DECOUPLE-MANUAL-LIFECYCLE" app resources database tests --include=*.php`

### Files (40 markers, 2026-11-11)
| File (`app/Domains/Production/…`) | Markers | What is commented out |
| :--- | :-: | :--- |
| `Models/Routing.php` | 8 | `product_id` / `bom_id` fillable, `product()`, `bom()` relations |
| `DTO/RoutingDTO.php` | 6 | `product_id`, `bom_id`, `material_quantity_mode` (with RT-IMPL-01 #3) |
| `Services/RoutingService.php` | 5 | BOM checks on submit (~140–160) / approve (~195–210), BOM pick on duplicate (~300–330), materials in `syncOperations()` |
| `Services/ProductionMasterImportService.php` | 3 | Routing import material blocks (806–818, 870–882; with RT-IMPL-16) |
| `Services/ProductionBomService.php` | 3 | Old routing cascades on BOM approve / reject / cancel |
| `Requests/StoreRoutingRequest.php` | 3 | BOM allocation rule block 267–408; `bom_id` / `material_quantity_mode` rules |
| `Requests/UpdateRoutingRequest.php` | 3 | Same |
| `Services/RoutingLifecycleService.php` | 2 | Old BOM→routing cascade methods (~309–425) |
| `Repositories/RoutingRepository.php` | 2 | BOM-related queries |
| `Controllers/RoutingController.php` | 2 | `$boms` (with RT-IMPL-01 #6) |
| `Requests/StoreProductionBomRequest.php` | 1 | Routing product check |
| `Requests/UpdateProductionBomRequest.php` | 1 | Routing product check |
| `Models/ProductionBom.php` | 1 | Old `routings()` / `routing()` HasOne |

### Also
* Old tests written for the BOM-in-routing design (TRACK-12 list, e.g. `RoutingMaterialAllocationTest.php`) — delete or rewrite in the same pass.
* Done = the search above returns nothing and the test suite passes.
