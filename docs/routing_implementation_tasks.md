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
| **RT-IMPL-01** | Remove leftover code that reads the dropped `routings.product_id` / `bom_id` | RT-01 | 🛠️ Applied (code working) |
| **RT-IMPL-02** | Add a manual "Retire" action (active / scheduled → historical) | RT-02 | 🛠️ Applied (code working) |
| **RT-IMPL-03** | Cancelled name+version reuse (service vs DB), stale "archives old version" comments, dead effective-date rule | RT-02 | 🛠️ Applied (code working) |
| **RT-IMPL-04** | Daily job: scheduled → active on start date; active → historical after end date | RT-03 | 🛠️ Applied (code working) |
| **RT-IMPL-05** | BOM routing advisory computed live from routing status (stop storing routing warnings in the BOM warning field) | RT-04 | 🛠️ Applied (code working) |
| **RT-IMPL-06** | One BOM handler for routing retire / expire / cancel: approved → inactive, pending → draft, impact list in confirmation | RT-05 | 🛠️ Applied (code working) |
| **RT-IMPL-07** | Routing API drops section / manual batch and skips operation validations — share one validator with the web form | RT-06 (also RT-07, RT-08, RT-10) | 🛠️ Applied (code working) |
| **RT-IMPL-08** | Alternatives: replace only a primary of the same routing; unique priority per primary; unambiguous lookup | RT-10 | 🛠️ Applied (code working) |
| **RT-IMPL-09** | Routing save keeps operations (update in place), soft-deletes removed ones, warns about affected BOMs; BOM shows deleted op and blocks save until fixed | RT-10 | 🛠️ Applied (code working) |
| **RT-IMPL-10** | Add "1 unit" notice; stop rounding times and unit costs to 2 decimals (form, DB columns, display) | RT-12 | 🛠️ Applied (code working) |
| **RT-IMPL-11** | Routing machine cost uses raw entered cost — share the WC-08 final-cost helper; remove dead Work Center fallback | RT-13 | 🛠️ Applied (code working) |
| **RT-IMPL-12** | Operator labor per machine = non-batch daily labor ÷ section operating hours ÷ active machines | RT-14 | 🛠️ Applied (code working) |
| **RT-IMPL-13** | Two setup times (per operation + new per piece); Cost / Unit and setup cost per run; yield on per-piece only; BOM cost formula | RT-16 | 🛠️ Applied (code working) |
| **RT-IMPL-14** | Calculate once in the form; backend checks then saves all figures; readers use saved values; snapshot numbers stored; outdated warnings | RT-19 | 🛠️ Applied (code working) |
| **RT-IMPL-15** | Clear both setup times whenever run time is cleared in the routing form | RT-22 | 🛠️ Applied (code working) |
| **RT-IMPL-16** | Routing import: section required, one resource, backend calculates figures from latest snapshot, draft-only safe update (no BOM allocation wipe) | RT-27 | 🛠️ Applied (code working) |
| **RT-IMPL-17** | Routing API output: saved values only, remove product block and duplicate keys, fix per-minute "cost_per_hour", add missing fields | RT-28 | 🛠️ Applied (code working) |
| **RT-IMPL-18** | Inactive resources: backend refuses on every path; approved-routing advisory; draft edit keeps and tags inactive resource, blocks save | RT-29 | 🛠️ Applied (code working) |
| **RT-IMPL-20** | Duplicate Version opens pre-filled create form — nothing saved until Save; API returns copy data | RT-35 | 🛠️ Applied (code working) |
| **RT-IMPL-21** | One-time fill of saved figures for routings saved before calculate-once | RT-36 | 🟡 Proposed |
| **RT-IMPL-19** | Final cleanup: remove all commented-out decoupling code (40 markers, 13 files) | RT-34 | 🟢 Agreed — ⏭️ do LAST |
| **RT-DEF-01** | Switch downstream services from the old alternate-machines table to alternative operations; remove the old table | RT-11 | ⏸️ Deferred to RT-32 |
| **RT-ISSUE-01** | External operations: cost per unit × yield factor; web vendor check doesn't confirm vendor exists | RT-08 | ⏸️ Decide Later |

---

## RT-IMPL-01: Remove Leftover Code Reading the Dropped `routings.product_id` / `bom_id`
* **Source Decision:** RT-01 (agreed 2026-11-10)
* **Status:** 🛠️ Applied (code working) 2026-11-11 — awaiting user verification
* **Implementation notes:** (1) `RoutingApiController::duplicate()` version loop now checks **name + version** (RT-02). (2) `Routing::scopeForProduct()` removed. (3) `RoutingDTO`: `product_id`, `bom_id`, `material_quantity_mode` properties / mappings removed (fixture in `BomRoutingInversionTest::createSampleRouting()` updated — it passed the ignored `product_id`). (4) `UpdateCapacityPlanning` no longer logs `product_id`. (5) `app/Exports/RoutingExport.php` rewritten for the current model: one row per operation, columns routing code / name / version / status, operation sequence / name / code / type, work center, **section**, machine, manual batch, setup per run, setup per piece, run time, yield, external, cost per unit; product column, product filter / search and per-material rows removed. (6) `RoutingController` create / edit no longer load `$products`, `$rawMaterials`, `$boms`, `$selectedProductId` (unused by the views). Deferred items (ProductionOrderService 1331, ECO view, commented blocks) untouched. Test: `tests/Feature/Production/RoutingFiguresTest.php`.

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
* **Status:** 🛠️ Applied (code working) 2026-11-12 — awaiting user verification (user asked to apply 2026-11-12)
* **Implementation notes:** Route `POST routing/{routing}/retire` (`production.routing.retire`) → `RoutingController::retire()` (optional comment). `RoutingService::retire()`: active / scheduled only (`Routing::canBeRetired()`), status `historical`, `effective_to` = today, `RoutingApproval` action **`Retired`** (new constant `ACTION_RETIRED`), then the existing `RoutingLifecycleService::handleBomsOnRoutingRetirement()` (approved BOMs → inactive + audit, draft BOMs → warning). Policy `RoutingPolicy::retire()` (approve permission + active / scheduled). UI: **Retire Routing** button + confirmation modal (explains the BOM effect) on the show page action bar, **Retire Routing** row action + modal on the index; both only while active / scheduled. API `POST /api/v1/production/routings/{id}/retire`. Lang keys `retire_routing`, `retire_routing_body`, `retire_reason_placeholder` (en / hi / bg). **Still with RT-IMPL-06:** pending-approval BOMs → draft and the impact list in the confirmation (one shared handler for retire / expire / cancel). Test: `tests/Feature/Production/RoutingRetireAndCopyTest.php` (`test_retire_makes_an_active_routing_historical_and_handles_linked_boms`).

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
* **Status:** 🛠️ Applied (code working) 2026-11-12 — awaiting user verification (user asked to apply 2026-11-12)
* **Implementation notes:** (1) `RoutingService::checkRoutingConflicts()` no longer excludes cancelled routings — a cancelled routing's name + version gives *"A routing with name … and version … already exists."* instead of a database error. (2) Approve docblock / comment and the web approve message now say older versions are **not** retired automatically (*"Older versions stay as they are — retire them with the Retire action when replaced."*; was *"Previous active version archived."*). (3) Dead product-based rule 3 removed from `RoutingLifecycleService::validateEffectiveDates()` (`$productId` kept in the signature, unused). (4) Commented-out legacy cascade methods → RT-IMPL-19. Test: `RoutingRetireAndCopyTest::test_cancelled_routing_name_and_version_give_a_clear_message`.

| # | Problem | File | Lines | Proposed fix |
| :-: | :--- | :--- | :--- | :--- |
| 1 | Service allows reusing a **cancelled** routing's name + version (`whereNotIn('status', [cancelled])`), but the DB unique key `[tenant_id, name, version]` blocks it → raw database error | `RoutingService.php`; migration `2026_11_02_130000` line 37 | 387–403 (392) | Remove the cancelled exclusion so the service gives the clear "already exists" message (cancelled history stays unique) |
| 2 | Comments still say approval "archives old active version" | `RoutingService.php` | 181, 217–218 | Update comments to "no auto-retire (RT-02)" |
| 3 | `validateEffectiveDates()` Rule 3 (start date vs other active versions) only runs when `$productId > 0` — always 0 now → dead code | `RoutingLifecycleService.php` | 57–84 | Remove the product-based block (or re-base on name if the rule is still wanted — decide under RT-03) |
| 4 | Legacy BOM→Routing cascade methods `syncDraftRoutingsToApprovedBom()`, `archiveRoutingsForDeactivatedBoms()`, `handleRoutingsOnBomCancellation()` sit inside a `/* … */` comment block (`[INVERSION-DECOUPLE-TRACK-03]`, from line ~309) | `RoutingLifecycleService.php` | ~309–445 | Remove in the **RT-34** cleanup pass *(corrected 2026-11-10: they are commented out, not live)* |

---

## RT-IMPL-04: Daily Job — Routing Status by Dates
* **Source Decision:** RT-03 rule 5 (agreed 2026-11-10)
* **Status:** 🛠️ Applied (code working) 2026-11-12 — awaiting user verification (user asked to apply 2026-11-12)
* **Implementation notes:** Command `app/Console/Commands/ProcessRoutingEffectiveDates.php` (`production:process-routing-dates`) → `RoutingLifecycleService::processEffectiveDates()`: all tenants (`withoutGlobalScopes()`), end dates first — active / scheduled with `effective_to < today` → `retireExpired()` (historical, audit *"Retired (end date … passed)."*, RT-05 BOM handling with event `expired`); then scheduled with `effective_from <= today` → `activateScheduled()` (active, audit action **`Activated`** — new `RoutingApproval::ACTION_ACTIVATED`). Scheduled daily 00:05 in `routes/console.php` (`withoutOverlapping`). Test: `RoutingRemainingTasksTest::test_daily_job_activates_scheduled_and_expires_ended_routings`.

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
* **Status:** 🛠️ Applied (code working) 2026-11-12 — awaiting user verification (user asked to apply 2026-11-12)
* **Implementation notes:** `ProductionBom::getRoutingAdvisoryAttribute()` (`$bom->routing_advisory`): *"Linked routing RTG-X is {status}. This BOM can't be approved until the routing is active."* while the routing is not active (null for cancelled / inactive BOMs or no routing; *"The linked routing no longer exists…"* if gone) — worked out live, survives BOM edits. Routing lifecycle no longer writes `has_warning` / `warning_reason` (rejection keeps status change + audit; retire / expire / cancel via RT-IMPL-06). BOM show: info banner with link to the routing; BOM index: *"Routing not active"* badge (repository eager-loads `routing`). Lang `routing_advisory`, `routing_not_active` (en / hi / bg). API resource field (item 6) left for the BOM API pass. Test: `RoutingRemainingTasksTest::test_cancel_handles_each_bom_status_and_advisory_is_live`.

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
* **Status:** 🛠️ Applied (code working) 2026-11-12 — awaiting user verification (user asked to apply 2026-11-12)
* **Implementation notes:** `handleBomsOnRoutingRetirement()` and `handleBomsOnRoutingCancellation()` replaced by one `RoutingLifecycleService::handleBomsOnRoutingEnded($routing, 'retired'|'expired'|'cancelled', $userId, $comments)`: approved / scheduled BOM → inactive + audit **Inactivated**; pending approval → draft + audit **Returned to Draft** (comment *"Automatically … because linked routing (RTG-X vY) was {event}. Reason: …"*); draft → nothing stored. Called from `transitionOnCancellation()`, `RoutingService::retire()` and `retireExpired()`. Impact list: `bomImpact($routing)` (BOM, version, status, *Becomes inactive* / *Goes back to draft* / *No change — shows the routing status advisory*) rendered by new partial `routing/partials/bom-impact.blade.php` in the Retire and Cancel modals on the show page and the index rows. Lang `bom_impact_title`, `bom_impact_none`, `bom_impact_effect`. Test: `RoutingRemainingTasksTest::test_cancel_handles_each_bom_status_and_advisory_is_live` (+ `RoutingRetireAndCopyTest` updated: draft BOM gets the live advisory, not a stored warning).

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
* **Status:** 🛠️ Applied (code working) 2026-11-11 — awaiting user verification
* **Implementation notes:** New trait `app/Domains/Production/Requests/Concerns/ValidatesRoutingOperations.php` holds the operation rules (`routingOperationRules()`), messages and checks (`validateRoutingOperations()`: unique sequences, queue threshold, internal / external, work center ↔ section, machine XOR batch, machine / batch ↔ section, alternatives — `replaces_sequence` accepted too, as the API uses it) — used by `StoreRoutingRequest`, `UpdateRoutingRequest`, `Api\StoreRoutingApiRequest`, `Api\UpdateRoutingApiRequest` (partial update: operations optional, same rules when present). API now accepts `section_id`, `section_manual_production_id`, alternatives, priority, `cost_per_unit`, `resource_snapshot` and the RT-IMPL-14 figures. `exists` rules tenant-scoped (work centers, sections, machines, batches, vendors, products, snapshots). Commented BOM rule block dropped from the moved code (RT-34). API store no longer requires `product_id` (dropped column). Unused `RoutingService::validateMachineBelongsToWorkCenter()` / `validateSectionBelongsToWorkCenter()` removed (item 7). Item 6 (API sends + backend checks the same figures) done with RT-IMPL-14. **Also fixed (RT-01 leftovers found here):** `RoutingApiController` no longer eager-loads the non-existent `product` relation (index / show / store / update / submit / approve … returned 500) and no longer filters on the dropped `product_id`. Test fixture `ProductionApiWorkflowsTest::test_routing_lifecycle_crud` updated (section + figures). Tests: `tests/Feature/Production/RoutingCalculateOnceTest.php` (`test_api_uses_the_web_form_rules`: section + machine ✔, no section / neither / both / section of another Work Center / another tenant's section ✘, tampered API figures ✘).

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
* **Status:** 🛠️ Applied (code working) 2026-11-12 — awaiting user verification (user asked to apply 2026-11-12)
* **Implementation notes:** Shared validator `validateAlternativeTargets()` (web, API, import): target resolved `replaces_sequence` → `replaces_row_uid` → `replaces_operation_id` (only as the id of a submitted row of this routing); errors *"An alternative operation must replace an operation of this routing."*, *"An alternative must replace a primary operation, not another alternative."*, *"An operation cannot replace itself."*, *"Two alternatives of OP-XXX have priority N. Each alternative of an operation needs its own priority."* `RoutingService::syncOperations()` Pass 2: tenant-wide fallback and the "id tried as a sequence" lookup removed — only operations of this routing. Test: `RoutingRemainingTasksTest::test_alternative_rules`.

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
* **Status:** 🛠️ Applied (code working) 2026-11-12 — awaiting user verification (user asked to apply 2026-11-12) — routing and BOM parts
* **Implementation notes:**
  * **Routing:** `syncOperations()` rewritten — rows matched to existing operations by `id` (edit form now posts `operations[i][id]`; DTO `operation_id`, rule `operations.*.id`) or, when the payload has no ids (API / import), by `operation_number`; matched rows updated in place (ids stable), new rows created, removed rows **soft-deleted** + routing audit *"Operation OP-XXX (name) removed."* Unique `[routing_id, sequence]` handled in the application: every existing (and older soft-deleted) row of the routing is moved to `sequence = −id` first (MySQL + SQLite); kept rows then get their new sequence (fix found in testing: an unchanged sequence is written back too). Removed operations with BOM allocations → flash warning *"Operation OP-XXX (…) is used by BOM … (n material(s)). It was removed from this routing; those BOMs must move or remove its materials."*; the edit form also asks before removing such a row (`window.routingOpBomUsage` from `RoutingController::edit()`). Migration `2026_11_12_100000_routing_operation_materials_restrict_operation_delete.php`: FK `rom_routing_op_fk` cascade → **restrict** (MySQL; SQLite tests keep the old definition).
  * **BOM:** `ProductionBom::deleted_operation_advisories` / `deletedOperationMessages()` (*"Routing changed: operation OP-XXX (…) was removed from routing RTG-X. Move or remove its materials before saving."*) shown as red banners on BOM show; BOM edit adds the removed operations that still carry this BOM's materials to the routing's operation list with `is_deleted` → card tagged **Deleted** (`operation_deleted` lang key); BOM store / update requests give the removed-operation message; `ProductionBomService::submitApproval()` / `approve()` refuse while any allocation points to a removed operation. After the materials are moved the card / message disappear.
  * Test: `RoutingRemainingTasksTest::test_save_updates_in_place_soft_deletes_removed_and_bom_is_blocked_until_fixed`; `MasterDataImportExportTest::import_confirm_update_strategy_…` now passes.

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
* **Status:** 🛠️ Applied (code working) 2026-11-11 — awaiting user verification
* **Implementation notes:** Notice *"Values shown below are calculated for 1 unit only. Excludes facility overhead."* (`production.routing_one_unit_notice`, en / hi / bg) above the operations table on create, edit and show. Forms: every `toFixed(2)` → `toFixed(4)` (hidden `cost_per_unit`, run time from machine / batch speed, resource cost spans); edit form loads times with 4 decimals (was 2). Migration `2026_11_11_110000_routing_operation_times_four_decimals_and_setup_per_piece.php`: `setup_time_minutes`, `processing_time_minutes`, `wait_time_minutes` → decimal(12,4) (0.0167 min no longer saved as 0.02); casts / rules / DTO already unrounded. Show page: header and cost card, resource cards, Cost / Unit column, footer and simulator at 4 decimals; time subtitles "(0.0167 min)"; run speed m/u at 4 decimals. Durations: `format_duration()` and the form / simulator JS return **"< 1s"** for more than 0 but under 1 second. Currency conversion of the form / simulator symbol-only display → WC-FINAL-01. Test: `tests/Feature/Production/RoutingFiguresTest.php`.
* **Correction 2026-11-12 (user — display rule, RT-12 rules 4–5):** (a) **whole numbers without decimals** — new helpers `decimal_is_whole()` / `format_decimal()` (4 decimals when there are decimal digits, none when whole: 15 → "15", 11.5 → "11.5000"); `format_currency($x, 4)` drops the decimals for whole amounts (₹0 not ₹0.0000; 2-decimal callers unchanged); routing show `number_format(…, 4)` → `format_decimal()`; form / show JS `window.fmt4()` replaces every displayed `toFixed(4)` (create, edit, show / simulator; hidden data fields keep 4 decimals); `RoutingCostStatusService` capacity text and `CalculationText` energy text use `format_decimal()`. (b) **"< 1s" mistake fixed** — the duration formatters rounded 0.5–0.99 s up to "1s"; `format_duration()` and the create / edit / show JS now return "< 1s" for anything under 1 second. Test: `RoutingRetireAndCopyTest::test_display_rule_four_decimals_only_when_there_are_decimals`.

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
* **Status:** 🛠️ Applied (code working) 2026-11-11 — awaiting user verification
* **Implementation notes:** New `Machine::operatingCostBreakdown()` (base energy cost, entered, additional, deductibles, final, explanation type) + `Machine::finalOperatingCostPerHour()` — entered cost if > 0, else consumption × rate, else 0. Used by `SectionCalculationService::calculateSectionMachines()` (inline WC-08 logic replaced) and `getSectionResourceCosts()` (machine `operating_cost_per_hour` / `machine_cost_per_unit` / `cost_per_unit`), `RoutingService::syncOperations()` fallback, `RoutingOperation::estimatedCostPerUnit()`, `RoutingCostService::calculateOperationCost()`. Dead Work Center `cost_per_hour` fallbacks removed from `RoutingService`, `RoutingOperation` and `RoutingCostService` (use the saved `labor_cost_rate`). Not changed: `ProductionCostService` (BOM costing — deferred BM-DEF-02). Test: `tests/Feature/Production/RoutingFiguresTest.php`.

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
* **Status:** 🛠️ Applied (code working) 2026-11-11 — awaiting user verification (was: salary input depends on **WC-IMPL-07** (HRMS monthly CTC) and currency on **WC-ISSUE-12**)
* **Implementation notes:** `getSectionResourceCosts()`: non-batch employees add **daily** cost (`monthlyCtc() ÷ 30`), non-batch contractors add `headcount × rate × allocation shift clock hours` (8 h fallback kept — WC-ISSUE-03); `total_unassigned_labor_cost_per_hour = Σ daily ÷ section operating hours` (`calculateOperatingHours()`); `operator_labor_cost_per_machine_per_hour = that ÷ active machines` (0 when hours or machines = 0). New keys in the response (and per machine): `non_batch_daily_labor_cost`, `section_operating_hours`, `active_machines_count`; the routing forms save them in the operation's `resource_snapshot`; the show page explains the rate (*"Non-batch labor ₹X/day ÷ 16.00 hrs ÷ 1 machine = ₹Y/hr"*). Item 4 decided: same formulas as the section calculation, computed in `getSectionResourceCosts()` (moving the endpoint to the section snapshot is RT-IMPL-14). Example test: 1,000 + 800 per day over 16 h, 1 machine → 112.5/hr (old method 225/hr). WC-RTG-01 resolved. Test: `tests/Feature/Production/RoutingFiguresTest.php`.

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
* **Status:** 🛠️ Applied (code working) 2026-11-11 — awaiting user verification (was: BOM cost part implement with the BOM file; downstream part → RT-32)
* **Implementation notes (routing part):** Column name kept: `setup_time_minutes` now means **setup per operation (once per run)**; new `setup_time_per_piece_minutes` decimal(12,4) nullable (same migration as RT-IMPL-10). Model fillable / casts, `RoutingOperationDTO`, web + API request rules, `RoutingController::operations()` JSON, `RoutingDetailResource` (+ `setup_cost_per_run`), export, import pass-through. New `RoutingOperation::cycleTimePerPieceMinutes()` (setup per piece + run) and `setupCostPerRun()` (setup per run × (labor + machine rate), no yield); `totalCycleTimeMinutes()` includes both setups + wait. Cost / Unit = (setup per piece + run) × rates × (100 ÷ yield) in the form calculator, `RoutingService` fallback, `estimatedCostPerUnit()`, `RoutingCostService` (per operation `setup_per_piece_minutes`, `setup_cost_per_run`; routing total `setup_cost_per_run` of primaries). Form: two inputs "Setup / run" and "Setup / piece" (both optional, tooltips), Cost / Unit cell shows "+ X / run". Show page: two setup columns, "Setup Cost per Run" in the cost card, "+ X / run" under each Cost / Unit, simulator per piece. **Not done here:** BOM cost formula (`ProductionCostService`, BOM views — BOM file), downstream readers (RT-32), seeders. **Note:** `ProductionMasterImportService.php` already has a parse error in the committed code (stray `catch` near line 890) — the import cannot run until RT-IMPL-16 fixes it. Test: `tests/Feature/Production/RoutingFiguresTest.php`.

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
* **Status:** 🛠️ Applied (code working) 2026-11-11 — awaiting user verification (was: builds on RT-IMPL-10 (no rounding), RT-IMPL-11 (final machine cost), RT-IMPL-12 (operator labor), RT-IMPL-13 (two setups); BOM reading part with the BOM file)
* **Implementation notes:**
  * **Section snapshot stores routing figures:** `SectionCalculationService::calculateAndSave()` adds `calculation_details.routing_resources` (= `getSectionResourceCosts()` at snapshot time: per active machine id / code / capacity / speed / final operating cost / operator labor, per active batch id / name / shift / capacity / labor per hour, non-batch daily labor, section hours). New `getSnapshotResourceCosts($section)` returns those figures from the **latest** snapshot + `section_calculation_id`, `snapshot_number`, `work_center_calculation_id` / number, `calculated_at`, `has_pending_changes`, or `not_calculated: true` + message. The `sections/{section}/resources` endpoint uses it (no live calculation). `getSectionResourceCosts()` is now only used inside the section calculation (and by the deferred BOM `ProductionCostService`).
  * **Columns** (migration `2026_11_11_120000_add_saved_cost_figures_to_routing_operations.php`): `labor_cost_per_unit`, `machine_cost_per_unit`, `setup_cost_per_run` decimal(15,4); `costs_calculated_at`; `section_calculation_id` (FK, null on delete), `section_snapshot_number`, `work_center_calculation_id` (FK, null on delete); `routings.cost_snapshot` JSON. Model fillable / casts, DTO fields.
  * **One calculator:** form JS `computeFigures(operation)` (create + edit) derives run time, labor / machine rate, labor / machine per unit, Cost / Unit, setup cost per run from the selected machine / batch of the section snapshot; all hidden fields (+ snapshot reference) come from it; the run-time field shows it. Edit form therefore refreshes figures from the current snapshot on open. PHP mirror `app/Domains/Production/Services/RoutingOperationCostCalculator.php` (same rounding, tolerance 0.0001).
  * **Backend check** (in the shared validator, web + API): section without snapshot → *"Section SEC-X has not been calculated yet. Recalculate the Work Center first."*; resource not in the latest snapshot → error; snapshot of another section → error; any figure off → *"Operation OP-XXX: cost does not match the selected machine / batch. Reload the resource and save again."*; external: subcontract × yield checked. `RoutingService` recalculation fallback removed — values saved as sent + `costs_calculated_at`; when the API omits the snapshot reference, the latest snapshot used by the check is recorded.
  * **Snapshot JSON** (`resource_snapshot`): + work center / section id and code, section snapshot id / number, Work Center snapshot id / number, calculated_at, rate source; run-only per-unit keys renamed `run_machine_cost_per_unit`, `run_labor_cost_per_unit`, `run_cost_per_unit`.
  * **Readers use saved values:** `RoutingCostService::calculateOperationCost()` rewritten (saved cost / labor / machine per unit, setup cost per run; `costs_not_calculated` flag; routing total flag); `RoutingOperation::estimatedCostPerUnit()` = saved `cost_per_unit` (lookup chain removed), new `costsNotCalculated()`, `setupCostPerRun()` prefers the saved value; routing show resource cards use saved figures (live `getSectionResourceCosts()` fallback removed), Cost / Unit cell shows *"Snapshot #N"* or *"Costs not calculated — open and save the routing"*. BOM reader (`ProductionCostService`, item 5.4) left for the BOM file; production orders / MRP → RT-32.
  * **Approval record:** `RoutingLifecycleService::buildApprovalCostSnapshot()` stored in `routings.cost_snapshot` on approve (approver, time, Σ cost / labor / machine per unit, Σ setup cost per run, per-operation figures + section snapshot numbers).
  * **Outdated warnings:** new `RoutingCostStatusService::check($routing)` compares saved figures with the latest section snapshot (numbers only): *"Costs outdated — section SEC-X has a newer calculation (snapshot #N)."*, *"Machine MC-XX cost changed (was A/hr, now B/hr)."*, operator / batch labor and capacity changes, *"Machine … is no longer active in section …"*; action text by status (approved: *"Create a new routing version to update."*, draft: *"Open and save the routing to refresh costs."*). Banner on routing show, *"Costs outdated"* / *"Costs not calculated"* badge on routing index rows. BOM-side badge → BOM file.
  * **Tests:** `tests/Feature/Production/RoutingCalculateOnceTest.php` (5). Test helper `tests/Concerns/FillsRoutingFigures.php` (does what the form does: recalculates the Work Center once if the section has no snapshot, fills the figures) applied to the existing web / API routing fixtures in `RoutingTest`, `WorkCenterRoutingIntegrationTest`, `MultiModelPhase2Test`, `ProductionApiWorkflowsTest`; two fixtures that create operations directly now store their saved figures; `RoutingFiguresTest` updated for saved-value readers. Routing + API routing suites: no test newly failing.

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
* **Status:** 🛠️ Applied (code working) 2026-11-11 — awaiting user verification
* **Implementation notes:** New `clearResourceDerivedTimes(operation)` in both forms clears run time, setup per run and setup per piece; called from all six run-time clearing points (work center changed, section changed, machine cleared / no speed, batch cleared / no speed) and the external toggle (replaces its own two lines). Cost / Unit and setup cost per run then show 0. Test: `tests/Feature/Production/RoutingFiguresTest.php`.

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
* **Status:** 🛠️ Applied (code working) 2026-11-12 — awaiting user verification (user asked to apply 2026-11-12)
* **Implementation notes:** Validation (`ProductionMasterImportService::parseAndValidate('routings')`): section required for internal operations, *"must specify a Machine or a Manual Batch"*, external operations need `vendor_code` (tenant vendor) + optional `subcontract_cost_per_unit`; existing non-draft routing → *"Only draft routings can be updated by import."*; then `routingImportPayload()` works out every figure from the latest section snapshot with `RoutingOperationCostCalculator` (run time from capacity — the file's processing time is no longer read; setup times + yield from the file; alternatives by sequence; *"Section SEC-X has not been calculated yet. Recalculate the Work Center first."*) and new `RoutingImportValidator` runs the **shared** operation checks (active resources, alternatives, cost check). Commit: create / draft update through `RoutingService` (same save as the form — in place by operation number, removed ops soft-deleted, **BOM allocations never deleted**); the old force-delete + allocation delete and the stray block that broke the file's parsing are gone. Template `app/Exports/RoutingTemplate.php`: section, manual batch, setup per piece, alternatives, vendor, subcontract cost; product / processing time / material columns removed. Commented material blocks → RT-IMPL-19. Test: `RoutingRemainingTasksTest::test_import_uses_same_rules_calculates_figures_and_updates_drafts_safely`. *Not changed:* `MasterDataImportExportTest::import_routings_machine_work_center_mismatch_rejection` (already failing before) uses a CSV without section codes, so its first error is now "Section Code is required".

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
* **Status:** 🛠️ Applied (code working) 2026-11-12 — awaiting user verification (user asked to apply 2026-11-12)
* **Implementation notes:** `RoutingDetailResource` rewritten: `product` block and duplicate keys (`operation_sequence`, `operation_name`, `run_time_minutes`, `queue_time_minutes`) removed; `cost_per_hour` replaced by `labor_cost_rate_per_minute` / `machine_cost_rate_per_minute`; saved `cost_per_unit`, `labor_cost_per_unit`, `machine_cost_per_unit`, `setup_cost_per_run`, `costs_not_calculated`; setup per piece, yield, predecessor / parallel; external block (vendor, subcontract cost / lead time / buffers, supply type, service product); traceability (`resource_snapshot`, `section_calculation_id`, `section_snapshot_number`, `costs_calculated_at`); routing level `totals` (Σ primary cost / labor / machine per unit, Σ setup cost per run, setup time per run, cycle time per unit), `approved_by` / `approved_at`, `cost_snapshot`, `costs_outdated`, linked `boms`. `RoutingListResource`: product block removed. `RoutingApiController` eager-loads `DETAIL_RELATIONS` (incl. vendor, boms). ENDPOINTS.md left for the business-rules docs pass. Test: `RoutingRemainingTasksTest::test_api_output_saved_values_one_key_per_value`.

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
* **Status:** 🛠️ Applied (code working) 2026-11-12 — awaiting user verification (user asked to apply 2026-11-12)
* **Implementation notes:** (1) Shared validator (web, API, import) refuses inactive / deleted work center, section, machine (`status != active`) and batch (`active = false`): *"Machine MC-XX is inactive. Choose another machine."* (same wording for work center / section / batch). (2) `RoutingCostStatusService::inactiveResources()` + `check()['inactive']` / `inactive_header`: per operation *"OP-XXX uses machine MC-XX, which is currently inactive."* (or *deleted*), header *"N operation(s) use inactive resources — create a new routing version to update."* (draft: *choose another resource and save*) — red banner on routing show, *Inactive resources* badge on the index (`inactive_resources` lang key). (3) Draft edit (and the duplicate copy form): `RoutingController::keepUsedInactiveResources()` keeps the routing's own inactive / deleted work center and section in the lists and passes inactive machines / batches as `kept_machine` / `kept_batch`, shown selected and tagged *"— Inactive"* / *"— Deleted"* (never cleared silently). (4) Save refused until changed (validator). (5) BOM side: covered by the live routing advisory when the routing stops being active; per-resource advisory on BOMs left for the BOM file. Test: `RoutingRemainingTasksTest::test_inactive_resources_refused_advised_and_kept_in_draft_edit`.

| # | Area | File | Change |
| :-: | :--- | :--- | :--- |
| 1 | Backend check | shared operation validator (RT-IMPL-07) used by `StoreRoutingRequest`, `UpdateRoutingRequest`, API requests, import (RT-IMPL-16) | Refuse inactive / deleted Work Center, section (`status != active`), machine (`status != active`, idle per WC-MACH-01), batch (`active = false`): *"Machine MC-XX is inactive. Choose another machine."* |
| 2 | Approved advisory | `resources/views/modules/production/routing/show.blade.php` (operations table + header banner), `index.blade.php` (row badge) | Per operation: *"OP-XXX uses {type} {code}, which is currently {status}."*; header: *"N operations use inactive resources — create a new routing version to update."* Computed by comparing statuses only (part of RT-IMPL-14 §7 outdated checks) |
| 3 | Draft edit — keep and tag | `resources/views/modules/production/routing/edit.blade.php`; `RoutingController::edit()` ~145–160 | Load the operation's current section / machine / batch even if inactive (and `withTrashed()` for deleted); add it to that row's dropdown tagged **"Inactive"** (or "Deleted"); never clear it silently |
| 4 | Draft edit — block save | backend check (#1) | Save refused while any row keeps an inactive / deleted resource; the form highlights the row |
| 5 | BOMs using the routing | BOM file | Show the routing's inactive-resource advisory (via RT-IMPL-05 / RT-IMPL-14 §7) |
| 6 | Tests | `tests/Feature/Production/WorkCenterRoutingIntegrationTest.php` | Re-check what "approved routing safeguards for inactive resources" asserts today; add: API save with inactive machine rejected; edit draft keeps inactive resource tagged; approved routing shows advisory |

---

## RT-IMPL-20: Duplicate Version — Pre-filled Create Form, Nothing Saved Until Save
* **Source Decision:** RT-35 (agreed 2026-11-12)
* **Status:** 🛠️ Applied (code working) 2026-11-12 — awaiting user verification
* **Implementation notes:**
  * **Web:** `RoutingController::duplicateVersion()` now only checks the version (`checkRoutingConflicts()`, clear message) and redirects to `production.routing.create?source_routing_id={id}&version={v}` — nothing saved. `create()` with `source_routing_id` renders the edit form in **copy mode** (`$copyMode`, `$newVersion`): posts to `store` (no `PUT`), hidden `source_routing_id`, title *"New version of RTG-… (from v…)"* + notice *"nothing is saved until you click Save"* (`routing_copy_title`, `routing_copy_notice`, en / hi / bg), routing number AUTO, version as typed, start today, no end date, button *Save Routing Draft*, Cancel → source routing. Copy rows have no database id (`db_id: null`, `src_id` used only to resolve alternatives), alternatives sent by `replaces_sequence`; figures refresh from the current section snapshots on open (RT-IMPL-14 form logic).
  * **Save:** `StoreRoutingRequest` accepts `source_routing_id` (tenant-scoped); after the normal create, `RoutingService::recordRevisionSource()` sets revision = source + 1, writes the *Revision Created* audit (*"Version X created from RTG-… version Y"*) and fires `RoutingRevisionCreated`.
  * **Service:** `RoutingService::duplicateVersion()` (saved a copy, copied operation materials) **removed**; new read-only `buildCopyPayload(int $id, string $newVersion)` returns the create payload (header, all operations, alternatives / predecessors by sequence, figures from the latest section snapshots via `RoutingOperationCostCalculator` + snapshot reference) and `warnings` (section not calculated, resource not in snapshot).
  * **API:** `POST /routings/{id}/duplicate` returns `buildCopyPayload()` (200, not saved); `StoreRoutingApiRequest` accepts `source_routing_id`; API store records the source the same way. **Also fixed:** API store called `create($dto, userId)` — the user id landed in the **tenant id** argument; now `create($dto, tenantId, userId)`.
  * Materials are not copied (they belong to the BOM).
  * **Tests:** `RoutingRetireAndCopyTest` (web: nothing saved by the button, pre-filled form posts to store, existing version refused; API: copy data with figures, nothing saved, create saves with revision + audit + correct tenant); `RoutingTest::test_routing_duplication_creates_new_revision` rewritten for the new flow.

---

## RT-IMPL-21: One-Time Fill of Saved Figures for Older Routings
* **Source Decision:** RT-36
* **Status:** 🟡 **Proposed** — waiting for the user's decision (conflicts with "approved routings change only through a new version").
* **Proposed change:** command `production:fill-routing-figures` (per tenant, dry-run option): for every operation with `costs_calculated_at` null, take the latest snapshot of its section, run `RoutingOperationCostCalculator`, save the figures + snapshot reference + time, and write a routing audit entry *"Costs filled from section snapshot #N (one-time)"*. Operations whose section has no snapshot or whose resource is not in it are listed, not changed. Routings that already have figures are never touched.

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
