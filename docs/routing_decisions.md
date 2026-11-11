# Routing — Final Decisions & Business Rules

> **Purpose:** The single, final source of agreed Routing decisions (routing identity & lifecycle, operation structure, operation costing, routing form, show page, import / API, safeguards).
> Decisions are reviewed one at a time (RT-01 … RT-34) and written here only once agreed.
> **Source documents reviewed:** `docs/work_center_routing_linking_tracker.md` (routing side, WC-TRACK-01 … 10), `docs/bom_routing_feature_exchange_tracker.md` (TRACK-01, 03, 06, 13), `docs/bom_routing_architecture_map.md`; items carried over from the Work Center review (WC-RTG-01, WC-RTG-02).
> **Depends on:** [`docs/work_center_decisions.md`](work_center_decisions.md) (sections, machines, batches, labor rates).
> **Implementation tasks:** [`docs/routing_implementation_tasks.md`](routing_implementation_tasks.md)

---

## Review Progress

> **Status note:** 🔁 **Provisional** = rules look right today but are marked to be revisited and finalised later.

| ID | Topic | Status |
| :--- | :--- | :--- |
| RT-01 | Routing is a standalone, reusable process route (no product, no BOM link) | ✅ Agreed (cleanup → RT-IMPL-01) |
| RT-02 | Uniqueness (number; name + version); no auto-retire; manual Retire action | ✅ Agreed (fix → RT-IMPL-02; RT-IMPL-03 proposed) |
| RT-03 | Routing lifecycle, edit rules, date rules; daily job for scheduled → active and expired → historical | ✅ Agreed (fix → RT-IMPL-04) |
| RT-04 | Routing rejected → pending BOMs back to draft + audit; BOM routing advisory shown live | ✅ Agreed (fix → RT-IMPL-05) |
| RT-05 | Routing retired / expired / cancelled → approved BOMs inactive, pending BOMs back to draft, drafts advisory | ✅ Agreed (fix → RT-IMPL-06) |
| RT-06 | Exactly 1 work center + 1 section per internal operation; same validation on every entry path | ✅ Agreed (API fix → RT-IMPL-07) |
| RT-07 | Internal operation has exactly one resource: machine XOR manual batch (from its section) | ✅ Agreed (API → RT-IMPL-07) |
| RT-08 | External (subcontract) operations | 🔁 Provisional — revisit later (open → RT-ISSUE-01) |
| RT-09 | Inspection / QC operations | ⏸️ Deferred (dedicated QC review) |
| RT-10 | Alternative operations; routing save keeps operations, soft-deletes removed ones, BOMs flagged until fixed | ✅ Agreed (fix → RT-IMPL-08, RT-IMPL-09) |
| RT-11 | Alternative operations replace the old alternate-machines table (switch + removal deferred to RT-32) | ✅ Agreed (deferred work → RT-DEF-01) |
| RT-12 | All routing figures are for 1 unit; Cost / Unit always shown; no rounding — show the true value | ✅ Agreed (fix → RT-IMPL-10) |
| RT-13 | Machine operation: time = 60 ÷ capacity; machine cost uses the same final operating cost as WC-08 | ✅ Agreed (fix → RT-IMPL-11) |
| RT-14 | Machine operator labor = non-batch daily labor ÷ section operating hours ÷ active machines; no manual rates | ✅ Agreed (fix → RT-IMPL-12; resolves WC-RTG-01) |
| RT-15 | Manual batch operation: time = 60 ÷ batch capacity; labor from that batch only; machine cost 0 | ✅ Agreed |
| RT-16 | Two setup times: per operation (once per run) and per piece; yield on per-piece costs only | ✅ Agreed (fix → RT-IMPL-13) |
| RT-17 | Overhead in routing / BOM cost | ⏸️ On Hold (note "excludes facility overhead" → RT-IMPL-10) |
| RT-18 | Routing totals (cost / unit, setup per run, cycle time) from primary operations only | ✅ Agreed |
| RT-19 | Costs calculated once in the form, checked and saved by backend, never recalculated; snapshot numbers stored; outdated warnings | ✅ Agreed (fix → RT-IMPL-14) |
| RT-20 | Routing form loading: preload Work Centers + sections; section resources fetched (latest snapshot) and cached | ✅ Agreed |
| RT-21 | Machine ↔ batch soft-switch: picking one clears the other with an inline notice | ✅ Agreed |
| RT-22 | Cascading resets: Work Center → section → resource; outsourced clears all; setup cleared with run time | ✅ Agreed (fix → RT-IMPL-15) |
| RT-23 | Routing form contains only routing data (no BOM / materials); controller loads only what the form uses | ✅ Agreed (cleanup → RT-IMPL-01) |
| RT-24 | Show page header (primary route) + route simulation modal; layout accepted as built | ✅ Agreed (data fixes via RT-IMPL-10, 13, 14) |
| RT-25 | Durations as clock time; under 1 second shown as "< 1s" with the real minutes | ✅ Agreed (fix → RT-IMPL-10) |
| RT-26 | Routing show page lists the linked BOMs (also used for Retire / Cancel impact) | ✅ Agreed |
| RT-27 | Routing CSV import: same rules as the form; backend calculates figures from latest snapshot; safe draft-only update | ✅ Agreed (fix → RT-IMPL-16) |
| RT-28 | Routing API: input same as form; output = saved values, one key per value, correct units | ✅ Agreed (fix → RT-IMPL-07, RT-IMPL-17) |
| RT-29 | Inactive resources: approved routings unchanged + advisory; drafts blocked (form + backend); edit keeps and tags inactive resource | ✅ Agreed (fix → RT-IMPL-18; resolves WC-RTG-02) |
| RT-30 | Machine / batch → section → Work Center ownership checked on every path | ✅ Agreed (helper cleanup → RT-IMPL-07) |
| RT-31 | Parallel operations, group picker, collision check, longest-branch cycle time (merged WC-TRACK-09 + TRACK-13) | ⏸️ Deferred (with RT-32) |
| RT-32 | Downstream: production orders, plans, MRP, scheduling, MES (combined 11-item list) | ⏸️ Deferred |
| RT-33 | ECO (engineering change orders) — 4 notes recorded | ⏸️ Deferred |
| RT-34 | Remove commented-out decoupling code in one final pass | ✅ Agreed (→ RT-IMPL-19, do last) |

**Routing review completed 2026-11-11:** 34 / 34 reviewed — **28 agreed**, **1 provisional** (RT-08), **5 deferred / on hold** (RT-09, RT-17, RT-31, RT-32, RT-33), plus carried-over WC-RTG-01 / WC-RTG-02 decided (RT-14, RT-29).
Open items in [`routing_implementation_tasks.md`](routing_implementation_tasks.md): RT-ISSUE-01 (external cost / vendor), RT-IMPL-03 (proposed), RT-DEF-01 (deferred to RT-32). Several tasks have a BOM part to do with the BOM file (RT-IMPL-05, 09, 13, 14).

---

## A. Routing Identity & Lifecycle

### RT-01: Standalone, Reusable Routing
1. A Routing is a **generic manufacturing process route** (sequence of operations). It has **no `product_id`** and **no `bom_id`**.
2. The **BOM** chooses the product it makes and the Routing it uses (`production_boms.routing_id`). **One Routing can serve many BOMs** (`Routing::boms()`).
3. The BOM create / edit forms list **all draft, approved and active** Routings of the tenant — no product filter (edit also keeps the BOM's current Routing in the list).
* *Code reference:* columns dropped — `routings.bom_id` (migration `2026_10_31_120000_invert_bom_and_routing_relationships.php`), `routings.product_id` (`2026_11_02_130000_drop_product_id_from_routings_table.php`); `Routing.php` 52–53 (removed from fillable), 92–98 (relations commented out); `ProductionBomController.php` 220 (create list), 263–270 (edit list). Matches this decision.
* *Leftover code still reading the dropped `product_id`* → **RT-IMPL-01**.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-01 (decision 2), TRACK-03; confirmed 2026-11-10.

### RT-02: Uniqueness & Retiring Old Routings
1. A Routing is unique per tenant by **routing number** and by **name + version**.
2. **Approving a Routing never retires other Routings** automatically. Several Routings (and several versions) can be active at the same time; BOMs choose which one they use.
3. **Retire action (new):** an older Routing is retired **manually** with a **"Retire"** action: `active` (or `scheduled`) → `historical`, `effective_to` = retire date, audit entry in routing approvals. A historical Routing stays visible as history and **cannot be selected for new BOMs**. What happens to the BOMs that use it is decided under **RT-05**.
4. **Cancel** stays for Routings that should never be used (draft / pending / scheduled, or withdrawn).
5. *(Proposed, not yet confirmed)* A cancelled Routing's name + version **cannot be reused** — the service gives a clear message instead of a database error (RT-IMPL-03).
→ Tasks **RT-IMPL-02** (Retire action), **RT-IMPL-03** (proposed cleanup).
* *Code reference:* unique keys `[tenant_id, name, version]` (migration `2026_11_02_130000`, line 37), `[tenant_id, routing_number]` (`2026_06_30_000003`, line 63); `RoutingService::checkRoutingConflicts()` 387–403, number check 33–42; `RoutingLifecycleService::transitionOnApproval()` 95–150 (no auto-retire ✔); routes `Routes/web.php` 178–182 (no retire action ✘); statuses `Routing.php` 23–28 (no `inactive`).
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-06 decision 1; `bom_routing_architecture_map.md` rule 9; confirmed 2026-11-10.

### RT-03: Routing Lifecycle & Dates
1. **Statuses:** `draft` → *(submit)* → `pending_approval` → *(approve)* → `active` (or `scheduled` when the start date is in the future). *(reject)* → back to `draft`. *(retire)* → `historical` (RT-02). *(cancel)* → `cancelled` (from any status except `cancelled` / `historical`). There is **no `approved` status** for Routings.
2. **Only drafts can be edited.** Active, scheduled, historical and cancelled Routings are read-only; changing an approved Routing means **creating a new version** (duplicate → new draft).
3. **Submit** needs at least **1 operation**. Neither submit nor approve needs a BOM (RT-01).
4. **Dates:** start date cannot be in the past; end date ≥ start date; if approval happens **after** the planned start date, the start moves to the approval date and a note is added to the description.
5. **Daily job (new):** each day, `scheduled` Routings whose start date has arrived become `active`; `active` Routings whose end date has passed become `historical` — with the same BOM handling as Retire (RT-05) and an audit entry.
→ Task **RT-IMPL-04**.
* *Code reference:* `RoutingService.php` 83–95 (edit draft only), 128–137 (submit), 186–192 (approve), 235–243 (reject), 268–276 (cancel); `RoutingLifecycleService.php` 30–55 (date rules 1–2), 95–150 (`transitionOnApproval()`, scheduled at 124, late-approval note 132–136); `Routing.php` 23–28 (statuses), 148–151 (`isScheduled()`), 189–198 (`isReadOnly()`), 205–212 (`scopeActive()` treats scheduled-with-past-start as active).
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-03; code review 2026-11-10; confirmed 2026-11-10.

### RT-04: Routing Rejection & the BOM Routing Advisory
1. When a Routing is **rejected** (pending → draft), every linked BOM in **pending approval** goes back to **draft**, with a BOM audit entry (*"Automatically returned to draft because linked routing (RTG-X) was rejected. Reason: …"*). Draft BOMs are unchanged.
2. **A BOM's routing advisory is worked out live** from the linked Routing's current status — **not stored**. Whenever the Routing is not `active` (draft, pending approval, scheduled, historical, cancelled), the BOM shows: *"Linked routing RTG-X is {status}. This BOM can't be approved until the routing is active."* It survives BOM edits and clears itself when the Routing becomes active.
3. The stored BOM warning (`production_boms.has_warning`, `warning_reason`) is for **component warnings only**.
→ Task **RT-IMPL-05**.
* *Code reference:* `RoutingService::reject()` 233–262 → `RoutingLifecycleService::handleBomsOnRoutingRejection()` 282–306 ✔; BOM warning overwritten by `ProductionBom::refreshWarningStatus()` 388–409 (component checks only), called from `ProductionBomService.php` 136, 232 ✘.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-06 sub-rule 5; confirmed 2026-11-10.

### RT-05: BOMs When a Routing Is Retired, Expires or Is Cancelled
The same handling applies to **Retire** (RT-02), **end date passed** (daily job, RT-03) and **Cancel**:

| Linked BOM status | What happens |
| :--- | :--- |
| **Approved** (BOM "active" — `STATUS_ACTIVE = 'approved'`) | Becomes **inactive**, with a BOM audit entry naming the routing, the event (retired / expired / cancelled) and the reason |
| **Pending approval** | Goes back to **draft**, with a BOM audit entry — it can never be approved on a non-active routing |
| **Draft** | No status change; the live routing advisory (RT-04) shows the routing status |

1. The **Retire** and **Cancel** confirmations list the BOMs that use the Routing and what will happen to each, so the planner sees the impact before confirming.
2. Production orders already created keep running on their own snapshots (downstream rules → RT-32).
3. To keep producing, the planner creates a **new BOM version** on an active Routing.
→ Task **RT-IMPL-06**.
* *Supersedes:* `bom_routing_feature_exchange_tracker.md` TRACK-06 decision 3 (draft-only advisory) — the §3 / sub-rule 6 behaviour (approved BOMs inactive) is kept and extended to pending BOMs.
* *Code reference:* `RoutingLifecycleService.php` 165–185 (`transitionOnCancellation()`), 236–280 (`handleBomsOnRoutingCancellation()` — approved → inactive ✔, pending not handled ✘), 191–233 (`handleBomsOnRoutingRetirement()` — never called ✘); `ProductionBom.php` 24–31 (statuses).
* *Origin:* TRACK-06; confirmed 2026-11-10.

---

## B. Operation Structure

### RT-06: One Work Center + One Section per Internal Operation
1. Every **internal** operation (`is_external = false`) has **exactly 1 Work Center and 1 Section**, both mandatory; the Section must belong to the Work Center. No multi-section operations, no "Work Center only" operations.
2. Stored as direct columns on `production_routing_operations` (`work_center_id`, `section_id`) — no pivot table.
3. The **same validation applies on every entry path** — web form, API and import — ideally one shared validator instead of separate copies.
→ Task **RT-IMPL-07** (API).
* *Code reference:* `StoreRoutingRequest.php` / `UpdateRoutingRequest.php` rule 3, lines ~150–200 ✔; migration `2026_11_02_140000_add_work_center_linking_to_routing_operations.php`; API `Requests/Api/StoreRoutingApiRequest.php` 56–70, `UpdateRoutingApiRequest.php` 54–68 (no section / batch rules ✘); `RoutingApiController.php` 99, 127 (`fromArray($request->validated())` drops them).
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-01 Point 1.1, WC-TRACK-05 Point 5.2; confirmed 2026-11-10.

### RT-07: Machine XOR Manual Batch
1. Every internal operation has **exactly one resource**: a **machine** (`machine_id`) **or** a **manual batch** (`section_manual_production_id`) — never both, never neither.
2. The machine / batch must belong to the operation's **own section** (RT-06).
3. **Machine operation:** time and machine cost come from that machine; **manual batch operation:** time and labor come from that batch, machine cost = 0 (costing rules in section C).
4. External operations have neither (RT-08).
* *Code reference:* `StoreRoutingRequest.php` / `UpdateRoutingRequest.php` from ~202 (neither / both / belongs-to-section checks) ✔; API → RT-IMPL-07; import → RT-27.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-02 Point 2.1; confirmed 2026-11-10.

### RT-08: External (Subcontract) Operations — 🔁 PROVISIONAL, REVISIT LATER
*Rules below look correct today but are marked to be revisited and finalised later. Open points → **RT-ISSUE-01**.*
1. An external operation (`is_external = true`) has **no** Work Center, Section, machine or batch — the form clears them and the backend forces them empty even if sent.
2. A **vendor is required**; lead time, dispatch buffer and return buffer days cannot be negative; subcontract cost per unit ≥ 0. Optional: subcontract service product, material supply type (company-supplied / vendor-supplied / none), subcontract input type.
3. Labor and machine cost are 0.
4. The operation's own setup / run / wait times are 0; the vendor's time is the **lead time in days** plus dispatch and return buffers.
5. **Open — cost per unit:** today `subcontract cost per unit × yield factor` (100 ÷ yield %) → RT-ISSUE-01 (with RT-16).
6. **Open — vendor check:** the vendor must exist and belong to the tenant on every entry path → RT-ISSUE-01.
* *Code reference:* `routing/create.blade.php` 1389–1415 (`onExternalToggle()`); `RoutingOperationDTO.php` 85–95 (forces empty); `StoreRoutingRequest.php` 80–85 (rules), internal/external check (~158–185); `RoutingService.php` 437–438 (cost per unit).
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-02 Point 2.2, WC-TRACK-03 Case C; reviewed 2026-11-10.

### RT-09: Inspection / QC Operations — ⏸️ DEFERRED
Deferred to a dedicated QA / QC review (quality plans, inspection workflow, test scenarios).
* **Today:** `operation_type = 'inspection'` has **no special routing rules** — it needs a Work Center, Section and machine / batch like any internal operation (RT-06, RT-07). The **QC flag** `quality_required` on an operation is used downstream to block / hold operations until QC passes (`MesExecutionService.php` 547, 840, 1067, 1190; `ProductionWipService.php` 855, 1741; `ProductionReadinessService.php` 953; `ProductionExecutionService.php` 998; `ReworkService.php` 379). Import accepts `inspection` (`ProductionMasterImportService.php` 480).
* **Questions for the QC review:** may an inspection operation have no machine / batch (inspector only)? How is it costed? Is `quality_required` enough, or must inspection link to a quality plan?
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-02 Point 2.3; kept deferred 2026-11-10.

### RT-10: Alternative Operations & Saving a Routing
**Alternative operations**
1. An alternative operation replaces **a primary operation of the same Routing** — never another alternative, never itself.
2. Each alternative of a primary has a **unique priority** (1, 2, 3 …). Primary operations do not use priority (hidden in the form).
3. An alternative uses **the same predecessor** as its primary; an operation that picks an alternative as predecessor is redirected to that alternative's primary.

**Saving a (draft) Routing**
4. Saving **keeps existing operations** and updates them in place (matched by row id). Only operations the user removed are deleted — and they are **soft-deleted**, never permanently deleted.
5. If a removed operation is used by any BOM's operation material allocations, the user is **warned, not blocked** (the Routing is still a draft). The warning lists the affected BOMs and says the operation will be **permanently removed from the Routing** and those BOMs must be fixed.
6. Affected BOMs show a **"Routing changed"** advisory: *"Operation OP-XXX was removed from routing RTG-X. Move or remove its materials."* (worked out live, like RT-04).
7. **BOM edit:** the removed operation's card is still shown, tagged **"Deleted"**, with its allocated materials, so the user can move or remove them. The BOM **cannot be saved (or submitted / approved) until no materials remain on deleted operations**. Once fixed, the deleted operation card is no longer shown.
→ Tasks **RT-IMPL-08** (alternatives), **RT-IMPL-09** (save in place, soft delete, BOM handling).
* *Code reference:* `StoreRoutingRequest.php` 248–262 (alternative checks); `RoutingService::syncOperations()` 413–415 (`forceDelete()` all operations ✘), 491–523 (bind replaced op — any tenant op id accepted at 509 ✘, sequence-first lookup at 505), 526–560 (predecessors ✔); FK `production_routing_operation_materials.routing_operation_id` **cascadeOnDelete** (migration `2026_07_01_000002`, 15–18) ✘; unique `[routing_id, sequence]` (`2026_06_30_000004`, 77).
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-01 Point 1.2, WC-TRACK-04 Point 4.2.4; extended 2026-11-10.

### RT-11: Old Alternate-Machines Table Replaced by Alternative Operations
1. **Alternative operations (RT-10) are the only way to define alternatives.** The old table `production_routing_operation_alternate_machines` (`RoutingOperationAlternateMachine`) is not used for new data — the routing UI never writes to it.
2. Scheduling, capacity, readiness and planning services must switch to alternative operations, and the old table, model, relation, seeder rows and tests are then removed — **deferred to RT-32** (downstream work). Listed in **RT-DEF-01** so nothing is lost.
* *Today:* with real data the table stays empty, so scheduling / capacity never see the alternatives users define.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-01 Point 1.2.5; confirmed 2026-11-10.

---

## C. Operation Costing (1-Unit Baseline)

### RT-12: 1-Unit Baseline & True Values
1. When a Routing is made the order quantity is unknown, so **all times and costs on the routing form and show page are for 1 unit**. A notice above the operations table says so: *"Values shown below are calculated for 1 unit only."*
2. Every operation shows **Cost / Unit** (labor + machine cost per unit, or subcontract cost for external), saved with the operation (`production_routing_operations.cost_per_unit`).
3. **No rounding — show the truth.** Times and costs are stored and displayed with their real precision (at least 4 decimals), not rounded to 2. E.g. a screw costing ₹0.0042 per unit shows **₹0.0042**, not ₹0.00; a machine at 3,600 u/h shows **0.0167 min** (1s), not 0.02 min.
→ Task **RT-IMPL-10**.
* *Code reference:* `routing/create.blade.php` 209–210 (column ✔), 232 (saved ✔), 1548–1563 (`calculateUnitCost()` → `.toFixed(2)` ✘), 1494, 1519 (processing time `.toFixed(2)` ✘); `RoutingService.php` 433 (backend fallback); columns `setup_time_minutes` / `processing_time_minutes` **decimal(10,2)** (migration `2026_06_30_000004`, 43, 45 ✘), `cost_per_unit` decimal(15,4) ✔; `format_currency()` default 2 decimals (`app/helpers.php` 302); no "1 unit" notice anywhere ✘.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-03 Point 3.1; confirmed 2026-11-10.

### RT-13: Machine Operation — Time & Machine Cost
1. **Time per unit (min) = 60 ÷ machine capacity per hour.**
2. **Machine cost per unit = time per unit × (machine final operating cost per hour ÷ 60)**, where *final operating cost* uses the **same rule as the Work Center calculation (WC-08)**: entered `operating_cost_per_hour`, otherwise `consumption_per_hour × energy_rate_per_unit`, otherwise 0 — **one shared helper** used by both the Work Center calculation and routing.
3. Only **active** machines are offered (idle counts as active — WC-MACH-01).
4. The rates are saved on the operation when the Routing is saved (`processing_time_minutes`, `machine_cost_rate`, `cost_per_unit`, `resource_snapshot`); when they are refreshed → RT-19.
5. The old fallback "operation with a Work Center but no machine / batch → `work_centers.cost_per_hour`" is **removed** (column dropped; RT-07 requires a resource).
6. Setup time and yield → RT-16. No rounding (RT-12).
→ Task **RT-IMPL-11**.
* *Code reference:* `SectionCalculationService::getSectionResourceCosts()` 1465–1484 (uses raw `$m->operating_cost_per_hour` at 1467 ✘); `RoutingService::syncOperations()` 453–458 (raw `operating_cost_per_hour` ✘), 467–471 (`$wc->cost_per_hour` dropped column ✘); WC-08 logic `SectionCalculationService::calculateSectionMachines()` 526–564; form `routing/create.blade.php` 1494.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-03 Case A; confirmed 2026-11-10.

### RT-14: Machine Operator Labor
1. **Machine operators** = the section's active workers who are **not in any active manual batch** (active employee assignments of active employees + active contractor allocations without a batch).
2. **Operator labor per machine per hour = (Σ daily cost of those workers ÷ section operating hours) ÷ number of active machines in the section.** (Average over the hours the section is open — same averaging idea as WC-21.)
   *Example:* morning + evening operator, ₹1,600/day each, section open 16 h, 1 machine → ₹3,200 ÷ 16 ÷ 1 = **₹200/hr** (not ₹400/hr).
3. Daily cost per worker follows the Work Center rules: employees = daily salary (WC-17, from HRMS monthly CTC), contractors = headcount × rate × shift clock hours (WC-19). Using daily cost means no shift-hours lookup per employee (removes the 8-hour problem of WC-ISSUE-10 for routing).
4. **Labor cost per unit = time per unit × (operator labor per machine per hour ÷ 60).**
5. **There are no manual rates.** Labor and machine rates always come from the chosen machine / batch. `labor_cost_rate` and `machine_cost_rate` on the operation are **saved copies of those calculated rates** (hidden form fields, filled automatically — `routing/create.blade.php` 230–231, 1498–1499, 1523–1524). *(Corrected 2026-11-10 — the old "manual override" wording came from WC-TRACK-03; the UI never had a manual rate field.)*
6. Setup time and yield → RT-16.
→ Task **RT-IMPL-12**.
* *Resolves:* `work_center_implementation_tasks.md` **WC-RTG-01** (operators on different shifts summed) and the routing part of **WC-ISSUE-10**.
* *Code reference:* `SectionCalculationService::getSectionResourceCosts()` 1410–1471 (sums per-employee hourly rates ✘); `RoutingService.php` 460 (override ✔).
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-03 Case A; confirmed 2026-11-10.

### RT-15: Manual Batch Operation
1. **Time per unit (min) = 60 ÷ the batch's own capacity per hour** (not the section average).
2. **Labor cost per unit = time per unit × (the batch's labor cost per hour ÷ 60)** — batch labor per hour = Σ hourly rates of the batch's employees (WC-16, WC-18) + Σ headcount × rate of its contractor allocations (WC-19).
3. **Machine cost = 0.**
4. **There are no manual rates.** The labor rate always comes from the chosen batch; `labor_cost_rate` / `machine_cost_rate` on the operation are saved copies of the calculated rates. *(Corrected 2026-11-10.)*
5. Only **active** batches can be selected; batch capacity is always > 0 (batch validation).
6. Setup time and yield → RT-16. No rounding (RT-12).
* *Code reference:* `SectionCalculationService::getSectionResourceCosts()` 1487–1503; `RoutingService::syncOperations()` 442–451; batch capacity rule `SectionManualProductionController.php` 34. Matches this decision — no change needed (matches Work Center batch cost per unit after WC-IMPL-01).
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-03 Case B; confirmed 2026-11-10.

### RT-16: Setup Time (Per Operation & Per Piece) and Yield
1. Each operation has **two optional setup times** — the user may fill **neither, either or both**:
   * **Setup per operation** (once per production run — e.g. die change, program load): the existing column `setup_time_minutes`, **renamed / relabelled** to make the meaning explicit.
   * **Setup per piece** (repeated for every piece — e.g. loading / clamping each part): **new nullable column**.
2. **Cost / Unit** (routing, 1-unit baseline) = **(setup per piece + run time) × (labor rate + machine rate) × yield factor**.
3. **Setup cost per run** = **setup per operation × (labor rate + machine rate)** — shown **separately** on the routing ("Setup cost per run"), **not** spread into Cost / Unit (batch size unknown — RT-12).
4. **Yield factor** (100 ÷ yield %) applies to **per-piece** costs only (setup per piece + run). Setup per operation happens once whatever the yield — **no yield factor**. (Yield on subcontract cost → RT-ISSUE-01.)
5. **When the quantity is known** (BOM, production order): **cost = quantity × Cost / Unit + setup cost per run (once per run)**.
   *Example:* setup per op 30 min, setup per piece 0.5 min, run 1 min, ₹10/min, yield 100 %, 100 pieces → Cost / Unit = (0.5 + 1) × 10 = **₹15**; setup per run = 30 × 10 = **₹300**; 100 pieces = 100 × 15 + 300 = **₹1,800**.
6. **Cycle time per piece** = setup per piece + run time; setup per operation shown as "setup per run". Downstream orders / MRP / scheduling already treat `setup_time_minutes` as once per run (`setup + run × qty`) — they must add setup per piece × qty (→ RT-32).
→ Task **RT-IMPL-13**.
* *Today (wrong):* Cost / Unit = (setup + run) × rates × yield — whole setup charged to 1 piece; BOM cost = (setup + run) × rate × **base quantity** × yield — setup multiplied by quantity (`ProductionCostService.php` 34–110).
* *Code reference:* form `routing/create.blade.php` 1555–1561; `RoutingService.php` 435–473; `RoutingCostService.php` 17–20, 81–88; `RoutingOperation::estimatedCostPerUnit()` ~229–245; downstream `ProductionOrderService.php` 205, 223, 1360, 1379; `MrpEngineService.php` 43, 55.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-03 Point 3.1 vs WC-TRACK-10 Point 10.1.4; decided 2026-11-10.

### RT-17: Overhead in Routing & BOM Cost — ⏸️ ON HOLD
1. Routing and BOM costs **exclude facility overhead** for now (today both return 0 — `RoutingCostService.php`, `ProductionCostService::calculateOverheadCost()` 113–120).
2. Work Center cost **does** include overhead (WC-28, WC-29), so Work Center cost per unit and routing / BOM cost per unit differ by the overhead — expected while this is on hold.
3. Routing and BOM cost screens show a note: *"Excludes facility overhead."* (with the 1-unit notice — RT-IMPL-10).
4. **Formula kept for later** (based on WC-28 daily overhead):
   * Overhead per minute = Work Center overhead per day ÷ Work Center operating hours ÷ 60
   * Overhead per unit = (setup per piece + run) × overhead per minute × yield factor
   * Overhead per run = setup per operation × overhead per minute
* *Supersedes:* old WC-TRACK-03 Point 3.2 "monthly lump-sum" formula.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-03 Point 3.2; kept on hold 2026-11-10.

### RT-18: Routing Totals — Primary Operations Only
1. **Routing Cost / Unit = Σ Cost / Unit of primary operations** (labor + machine + subcontract).
2. **Routing setup cost per run = Σ setup cost per run of primary operations**, shown separately (RT-16).
3. **Routing cycle time = Σ primary (setup per piece + run + wait)**; setup per run shown separately; parallel operations → RT-31 (deferred); external operations add 0 minutes (lead time in days — RT-08).
4. **Alternative operations are excluded from totals** — they replace a primary, they don't add to it; their effect is shown through the simulation (RT-24).
* *Code reference:* `RoutingCostService::calculateRoutingCost()` 40–73 (primary only ✔); `Routing::totalCycleTimeMinutes()` 234–239 (primary only ✔). Setup split → RT-IMPL-13.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-10 Point 10.1.1; confirmed 2026-11-10.

### RT-19: Calculate Once, Save Everything, Warn When Outdated
1. **The browser calculates** all operation figures **while editing** (one calculator in the routing form): time per piece, labor and machine rates, Cost / Unit, labor / machine split, setup cost per run (RT-12 … RT-16).
2. **The backend re-checks, then follows the form.** On save it runs the same formula on the submitted inputs; if the data has **discrepancies** (e.g. rates don't match the chosen machine / batch, cost doesn't match the formula, machine not in the section) it returns a **validation error**; otherwise it **saves the submitted values as they are**. The same applies to the API (same fields, same checks).
3. **Saved once, never recalculated.** Every calculated figure is stored on the operation when the Routing is saved. Show page, routing totals, simulation, BOM costing and production orders **read the saved values** — they do not recalculate. If a needed figure has no column today, add one.
4. **Snapshot for traceability.** With the figures, each operation stores what they were based on: Work Center, Section, machine / batch (ids, codes, names), capacity, rates, **and the Work Center / section calculation snapshot** used — saved in **dedicated database columns**: the snapshot **id** (for tracking and the outdated check) and the snapshot **number** (#N, shown to users — WC-38); new columns are added because the database does not save them today. On approval the Routing keeps who approved it, when, and these figures — so it is always known which Routing was approved when, with what costs and with which Work Centers.
5. **Outdated warning.** When the underlying data changes (a newer section snapshot with different figures, machine cost changed, batch changed, resource inactive), the Routing shows **labels and warnings**: *"Costs are outdated — section SEC-X has a newer calculation (snapshot #N). Create a new routing version to update."* The saved figures are **not** changed automatically. (Approved routings change only through a new version — RT-03; a draft is refreshed by re-opening and saving it.)
6. No fallback to Work Center `cost_per_hour` (column dropped).
7. **Figures come from the latest section snapshot.** The routing form's machine / batch / operator figures are read from the **latest calculation snapshot of the operation's section** (not calculated live), and that snapshot's number is stored on the operation (rule 4). So the section snapshot must also hold the routing figures: per active machine (capacity, final operating cost — RT-13), per active batch (capacity, labor per hour — RT-15), and operator labor per machine per hour (RT-14).
   * Section with **no snapshot yet** → the operation cannot be costed; the form shows *"Section SEC-X has not been calculated yet. Recalculate the Work Center first."* and saving is refused.
   * Section with a **pending countdown** (changes queued, WC-33) → the form warns *"Section SEC-X has pending changes; figures may change after the next calculation."*, saving is allowed.
   * **Only the routing create / edit form reads the latest section snapshots.** Every other page (routing show, totals, simulation, BOM, production orders, reports) reads the **routing's own saved values** from the database. Latest snapshots **never overwrite** a routing's saved figures — the figures change only when a routing (new version, or a draft being edited) is saved through the form. Elsewhere, newer snapshots only drive the **outdated warning** (rule 5). *(Clarified by the user 2026-11-10.)*
→ Task **RT-IMPL-14**.
* *Today:* operation cost is calculated in 6 places with different formulas and sources (form, `RoutingService::syncOperations()`, `RoutingCostService`, `RoutingOperation::estimatedCostPerUnit()`, routing show resource cards, `ProductionCostService` for BOM) — the same operation can show ₹310, ₹10 and a third value. `resource_snapshot` today stores machine / batch details and run-only per-unit costs (`routing/create.blade.php` 233, 1348–1380) but **no** section snapshot number, no Work Center / section ids, no timestamp.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-10 Points 10.1.2–10.1.3; decided 2026-11-10.

---

## D. Routing Form (UI)

### RT-20: Form Loading
1. On page load, all **active** Work Centers with their **active** sections are loaded, so picking a Work Center fills the section list instantly.
2. When a section is picked, its machines and batches (with figures) are fetched — from the **latest section snapshot** (RT-19 rule 7), with snapshot number and pending-changes warning.
3. The result is **cached per section** while the form is open; another row with the same section does not fetch again.
4. Only this form reads latest snapshots; every other page reads the routing's saved values (RT-19 rule 7).
* *Code reference:* `RoutingController.php` 48, 149 (preload); `routing/create.blade.php` 692, 1535–1542 (fetch + cache). Matches this decision — snapshot source via RT-IMPL-14.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-04 Point 4.1; confirmed 2026-11-10.

### RT-21: Machine ↔ Batch Soft-Switch
1. Machine and batch dropdowns are both usable (neither is locked).
2. Picking one **clears the other**, with a short inline notice on that row (and a close button).
3. The backend accepts exactly one (RT-07).
* *Optional wording tweak:* drop the technical "(Mutual Exclusivity)" from the notices, e.g. *"Machine selected — manual batch cleared."*
* *Code reference:* `routing/create.blade.php` 1483–1491 (`onMachineSelect()`), 1505–1515 (`onManualBatchSelect()`), 363–370, 444–450 (inline notice), 185–192 (form notice). Matches this decision — no change needed.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-04 Point 4.2; confirmed 2026-11-10.

### RT-22: Cascading Resets
1. Changing the **Work Center** resets section, machine / batch and the calculated figures (rates, run time, costs).
2. Changing the **section** resets machine / batch and the calculated figures.
3. Turning **outsourced** on clears all internal fields (RT-08).
4. **Setup times are cleared together with run time.** Whenever run time is cleared (Work Center changed, section changed, machine / batch cleared, outsourced on), **both** setup times — setup per operation and setup per piece (RT-16) — are cleared too, so no setup from the old resource is left behind. *(Changed by the user 2026-11-10.)*
→ Task **RT-IMPL-15**.
* *Code reference:* `routing/create.blade.php` 1429–1452 (`workCenterChanged()` — clears run time, keeps setup ✘), 1454+ (`sectionChanged()` — same ✘), 1503–1504, 1528–1529 (resource cleared — check), 1389–1415 (`onExternalToggle()` — already clears setup ✔).
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-04 Point 4.3; confirmed 2026-11-10.

### RT-23: Routing Form Contains Only Routing Data
1. The routing form holds **only routing data** — header, operations, resources. **Nothing about BOMs or materials** (no BOM dropdown, no material quantity mode, no allocation card, no operation materials).
2. The controller loads only what the form uses: Work Centers with sections, vendors, service products (external operations), operation types.
→ Cleanup in **RT-IMPL-01**.
* *Code reference:* views clean ✔ (no `bom_id` / `material_quantity_mode`); `RoutingController.php` create 43–60, edit ~145–160 still load unused `$boms` (empty), `$products`, `$rawMaterials`, `$selectedProductId` ✘; `$serviceProducts` used (`create.blade.php` 643, `edit.blade.php` 639).
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-03, TRACK-06 sub-rule 7, TRACK-08 #6; confirmed 2026-11-10.

---

## E. Routing Show Page

### RT-24: Header & Route Simulation
1. The header shows the **primary route's saved totals** (RT-18) with a note that alternative operations are excluded.
2. **Route simulation modal** ("Simulate route"): one stage per primary operation; stages without alternatives are locked; stages with alternatives offer a **single choice** (primary or one alternative, ordered by priority). Nothing is calculated until **Run Calculations**; results show Cost / Unit (labor, machine, subcontract), **setup cost per run** (RT-16), cycle time, and the difference (amount and %) against the primary route. Orphan alternatives are attached to a stage for safety.
3. **Layout accepted as built** (the old "layout to be provided by user" note is closed).
4. The simulation reads **saved** operation values with **no rounding** → RT-IMPL-10, RT-IMPL-13, RT-IMPL-14.
* *Code reference:* `routing/show.blade.php` 743–810 (stage / operation data — `round(…, 2)` ✘, recalculates via `RoutingCostService::calculateOperationCost()` / `estimatedCostPerUnit()` ✘), 825+ (`#routeSimulationModal`, `routeSimulator`).
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-10 Points 10.1, 10.2; confirmed 2026-11-10.

### RT-25: Durations as Clock Time
1. All durations are shown as **clock time** with `format_duration()` (PHP) / `formatDuration()` (JS): e.g. `12s`, `1m 05s`, `22m 30s`, `1h 27m`.
2. **Under 1 second:** shown as **`< 1s`** together with the real time in minutes — e.g. **`< 1s` (0.0040 min)**. Never `0s` for a value above zero, never fractional seconds (`0.24s`), no milliseconds.
3. The real time in minutes shown next to the clock value uses **at least 4 decimals** (RT-12) — e.g. `12s (0.2000 min)`.

| Real time per unit | Shown |
| :--- | :--- |
| 0.004 min | `< 1s` (0.0040 min) |
| 0.0083 min | `< 1s` (0.0083 min) |
| 0.0167 min | `1s` (0.0167 min) |
| 0.2 min | `12s` (0.2000 min) |
| 1.0833 min | `1m 05s` (1.0833 min) |
→ Fix in **RT-IMPL-10**.
* *Code reference:* `app/helpers.php` 432+ (`format_duration()` — rounds to whole seconds → `0s` ✘); simulation `formatDuration()` in `routing/show.blade.php` (same ✘); subtitles `number_format(…, 1)` at `routing/show.blade.php` 460, 466, 472 ✘; spec card speed `number_format($speedMin, 2)` ✘.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-10 Point 10.3; confirmed 2026-11-10.

### RT-26: Linked BOMs on the Routing Show Page
1. The routing show page lists **every BOM that uses the Routing** (`Routing::boms()`, BOMs with this `routing_id`): BOM number (link) and name, product to produce (with SKU), version, base quantity, status, view action; plus a "N BOM(s)" badge in the header.
2. The same list feeds the **Retire / Cancel confirmation** impact list (RT-05, RT-IMPL-06).
* *Code reference:* `routing/show.blade.php` 133 (badge), 563–620 (card + table). Matches this decision — no change needed.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-04 §4; confirmed 2026-11-10.

---

## F. Import, API & Safeguards

### RT-27: Routing CSV Import
1. Import columns include `section_code`, `machine_code` / `manual_batch_name`, `is_alternative`, `replaces_operation_number`, `priority` (plus the existing header / operation columns). Routing import **does not create materials** (they belong to the BOM).
2. **Same rules as the form:** section **required** for internal operations and must belong to the Work Center (RT-06); **exactly one** of machine / batch, belonging to the section (RT-07); external operations need a vendor (RT-08); alternatives replace a primary of the same routing with a unique priority (RT-10).
3. **The backend calculates on import.** There is no browser, so the import uses the **same PHP calculator** as the backend check (RT-IMPL-14) with the **latest section snapshot**, and saves all figures, the resource snapshot and the section snapshot id / number exactly as the form would (RT-19). **Run time comes from the machine / batch** (60 ÷ capacity — RT-13, RT-15); setup times and yield come from the file. A section with no snapshot → the row is rejected (RT-19 rule 7).
4. **Update strategy:** only **draft** routings can be updated (RT-03); operations are kept and updated in place, removed ones soft-deleted, **BOM allocations never deleted** (RT-10); the update saves **all** fields (section, batch, alternative, priority, figures).
→ Task **RT-IMPL-16**.
* *Code reference:* `app/Domains/Production/Services/ProductionMasterImportService.php` — parsing 399–429 ✔; validation 490–560 ✔ (section only checked if given ✘, "neither machine nor batch" not caught ✘); update path 779–830 (drops section / batch / alternative ✘, deletes all `RoutingOperationMaterial` at 787 ✘, force-deletes operations ✘, no status check ✘); create path 838–856 (saves section / batch / alternative ✔, no figures ✘); materials blocks commented `[INVERSION-DECOUPLE-TRACK-11]` ✔.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-05 Point 5.1; `bom_routing_feature_exchange_tracker.md` TRACK-11; decided 2026-11-11.

### RT-28: Routing API
1. **API input** = the same fields and the same checks as the web form, including the calculated figures (RT-06, RT-07, RT-19 rule 2) → RT-IMPL-07.
2. **API output** returns the routing's **saved** values only — no recalculation:
   * structure: Work Center, section, machine / batch, alternative, replaces, priority, external fields (vendor, subcontract cost, lead time, buffers, supply type);
   * times: setup per operation, setup per piece, run, wait;
   * figures: labor / machine rate per minute, Cost / Unit, labor / machine cost per unit, setup cost per run, yield;
   * traceability: resource snapshot, section snapshot id + number, calculated at; routing approval info.
3. **One key per value** (WC-45), no `product` block (RT-01), units in key names (e.g. `labor_cost_rate_per_minute`).
→ Tasks **RT-IMPL-07** (input), **RT-IMPL-17** (output).
* *Code reference:* `app/Domains/Production/Resources/Api/RoutingDetailResource.php` 30–85 — `product` block 31 ✘; duplicates `sequence`/`operation_sequence`, `name`/`operation_name`, `processing_time_minutes`/`run_time_minutes`, `wait_time_minutes`/`queue_time_minutes` ✘; `cost_per_hour` = per-minute rates summed ✘; `cost_per_unit` via `estimatedCostPerUnit()` ✘.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-05 Point 5.2; confirmed 2026-11-11.

### RT-29: Inactive Resources on Routings
1. **Approved routings stay unchanged.** Each affected operation shows *"OP-XXX uses {machine / batch / section} {code}, which is currently {inactive / under maintenance / decommissioned / deleted}."* — part of the **outdated warnings** (RT-19 rule 5): badge on the routing list, banner on the show page, *"Create a new routing version to update."*
2. **Drafts:** inactive sections / machines / batches cannot be selected in the form, **and the backend refuses** an inactive resource on save — on every path (form, API, import; shared validator RT-IMPL-07).
3. **Editing a draft** that already uses a now-inactive resource: the resource is **still shown** on its row, tagged **"Inactive"** — never silently dropped — and the draft **cannot be saved** until it is replaced (same pattern as the deleted-operation card on the BOM, RT-10).
4. **Idle machines count as active** (WC-MACH-01) and never trigger this.
→ Task **RT-IMPL-18**.
* *Code reference:* form active-only ✔ (`RoutingController.php` 48, 149; `getSectionResourceCosts()` active filters); backend active check ✘ (`StoreRoutingRequest`, `UpdateRoutingRequest`, API requests); advisory ✘ (no "inactive" text in `routing/*.blade.php`); edit drops inactive resource ✘ (`routing/edit.blade.php` dropdowns built from active lists).
* *Resolves:* `work_center_implementation_tasks.md` **WC-RTG-02**.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-06 Point 6.1; confirmed 2026-11-11.

### RT-30: Resource Ownership Chain
1. An internal operation's machine / batch must belong to its **section**, and the section must belong to its **Work Center** (machine → section → Work Center; `machines.work_center_id` no longer exists).
2. Checked on **every entry path** — form, API, import (shared validator).
* *Code reference:* `StoreRoutingRequest.php` / `UpdateRoutingRequest.php` section ↔ Work Center and machine / batch ↔ section checks ✔; unused helpers `RoutingService.php` 579–583, 588–593 → RT-IMPL-07 item 7.
* *Origin:* `bom_routing_architecture_map.md` §5.2; confirmed 2026-11-11.

---

## G. Deferred & Cleanup

### RT-31: Parallel Operations & Resource Collisions — ⏸️ DEFERRED (merged)
*One item replacing the duplicates WC-TRACK-09 (`work_center_routing_linking_tracker.md`) and TRACK-13 (`bom_routing_feature_exchange_tracker.md`). To be decided together with scheduling (RT-32).*
**Requirements recorded (not yet decided in detail):**
1. **Group picker:** replace the free-text group box (`Grp: [ A ]`) with a dropdown of the groups used in this routing + "+ Add New Group…", saved to `parallel_group`.
2. **Collision check:** two operations in the **same parallel group** cannot use the **same machine or the same manual batch** — UI warning on the rows + backend refusal (shared validator).
3. **Parallel vs predecessor:** support parallel branches (an operation may have a predecessor inside its branch); block impossible set-ups (e.g. Op 10 ∥ Op 30 while Op 30 → Op 20 → Op 10); fix the predecessor box not hiding (Bootstrap `.d-flex` vs Alpine `x-show`).
4. **UI:** Parallel checkbox and group on one line; clear group and warnings when Parallel is unticked.
5. **Cycle time:** routing cycle time = **longest parallel branch**, not the sum (from RT-18 rule 3).
**Today:** `is_parallel`, `parallel_group` (free text, max 50) accepted (`StoreRoutingRequest.php` 98–99); parallel operations get no predecessor (`RoutingService::syncOperations()` Pass 3, ~532–534); scheduling matches groups by exact text (`SchedulingService.php` 141, 214–215); no collision check; `Routing::totalCycleTimeMinutes()` (234–239) sums all primary operations.
* *Origin:* WC-TRACK-09, TRACK-13; kept deferred and merged 2026-11-11.

### RT-32: Downstream (Production Orders, Plans, MRP, Scheduling, MES) — ⏸️ DEFERRED
Deferred until BOM and Routing are final (old TRACK-07, WC-TRACK-07). **Combined list of downstream work** so nothing found in the Work Center / Routing reviews is lost:

| # | From | Downstream item |
| :-: | :--- | :--- |
| 1 | TRACK-07 | Material scaling ratio = `quantity ÷ bom.base_quantity` (old `material_quantity_mode` is gone) |
| 2 | TRACK-07 | Filter `production_routing_operation_materials` by the order's `bom_id` |
| 3 | TRACK-07 | Production plan takes the Routing from the BOM (`bom.routing_id`) |
| 4 | TRACK-07 | Costing uses `bom.routing` via the direct link |
| 5 | RT-01 / RT-IMPL-01 | `ProductionOrderService.php` 1331 — material scaling gated on `routing.product_id = order.product_id`, never true now |
| 6 | RT-11 / RT-DEF-01 | 7 scheduling / capacity / readiness / planning services still use the old alternate-machines table → switch to alternative operations, drop the table |
| 7 | RT-16 / RT-IMPL-13 | Orders / MRP use `setup + run × qty` (`ProductionOrderService.php` 205, 1360; `MrpEngineService.php` 43) → add **setup per piece × qty**; setup per operation once per run |
| 8 | RT-19 / RT-IMPL-14 | Orders / MRP **read the routing's saved figures** (cost, times, snapshot) — never recalculate |
| 9 | RT-31 | Parallel groups, collisions, longest-branch time — with scheduling |
| 10 | RT-IMPL-09 | Production order operations reference `routing_operation_id` — soft-deleted operations from routing edits must still resolve (`withTrashed()`) |
| 11 | RT-09 | `quality_required` QC holds in MES / WIP / readiness — with the QC review |
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-07; `work_center_routing_linking_tracker.md` WC-TRACK-07; kept deferred 2026-11-11.

### RT-33: ECO (Engineering Change Orders) — ⏸️ DEFERRED
ECO is not linked to the new BOM / Routing design yet; it may stay broken / disconnected until it is redone as its own piece of work (old TRACK-09). **Notes recorded for when it resumes:**
1. **ECO release must use the RT-05 BOM handling.** `RoutingLifecycleService::transitionOnEcoRelease()` (430–470) sets the current routing to `historical` and the proposed one to `active`, but does not inactivate approved BOMs / return pending BOMs to draft — it is the third path (with Retire and the daily job) that makes a routing historical.
2. `ProductionEcoService.php` 48, 63–64, 216–220 — `current_routing_id` / `proposed_routing_id` still valid.
3. `resources/views/modules/production/ecos/create.blade.php` 65 reads the dropped `$routing->product_id` (also listed in RT-IMPL-01).
4. `ProductionEcoImpactAnalysisService.php` 183–196 checks BOM **formula** changes (affected by the BOM formula drop) and reads `setup_time_minutes` (affected by RT-16).
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-09; kept deferred 2026-11-11.

### RT-34: Cleanup of Commented-Out Decoupling Code
1. All commented-out decoupling blocks and their markers (`[INVERSION-DECOUPLE-TRACK-03]`, `[INVERSION-DECOUPLE-TRACK-11]`, `[DECOUPLE-PRODUCT-INDEPENDENT]`, `[DECOUPLE-MANUAL-LIFECYCLE]`) are removed in **one final cleanup pass**.
2. It runs **last** — after the other routing tasks are applied and verified by the user (several of them touch the same files). BOM-side files go in the same pass.
3. Done when the marker search returns nothing.
→ Task **RT-IMPL-19** (do last).
* *Today:* 40 markers in 13 files (2026-11-11) — see RT-IMPL-19.
* *Origin:* `bom_routing_feature_exchange_tracker.md` TRACK-03 (comment-out policy); confirmed 2026-11-11.
