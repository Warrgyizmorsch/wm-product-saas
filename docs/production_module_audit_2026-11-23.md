# Production Module — Full Audit Log (2026-11-23)

> **Status:** 🔎 **Findings only — no application code, migration or data was changed by this audit.**
> **Scope asked by the user:** check the whole Production module in detail; log (1) bugs, (2) what does not align with the business rules, (3) what is still part of the old Work Center / BOM / Routing design (code and data).
> **Rules used as the yardstick:** [`production_business_rules.md`](production_business_rules.md) (G1–G9, WC-xx, RT-xx, BM-xx) and [`production_order_decisions.md`](production_order_decisions.md) (PO-01 … PO-12). Items already recorded in [`short_full_review.md`](short_full_review.md) Point 3 are marked **(known)** and only re-verified.
> **Severity:** 🔴 crash, or wrong stock / money / quantity · 🟠 wrong behaviour or a decided rule not met · 🟡 low / cosmetic / consistency · ⚪ dead or legacy code (no effect today).

---

## 0. How it was checked (and what was NOT)

| Method | What it covered |
| :--- | :--- |
| **Real-MySQL route crawl** | A throw-away copy of the real database (`zz_seed_k`, structure + data) was created; every Production **web GET route** (177 requests) plus ~90 explicit probes (exports, reports, MES, labels) was requested through the real HTTP kernel as the tenant owner. SQLite tests cannot catch dropped-column bugs (F2), MySQL can. |
| **Static analysis against the real schema** | A PHP-Parser script checked every query chain, relation name, `$fillable`, `$casts` and `create()/update()` key in `app/` and the compiled Blade views against the **real MySQL columns** (542 files, 348 models). Tenant-scope (`withoutGlobalScopes()` without `tenant_id`) and authorization-check scans were run on all 485 Production routes. |
| **Lifecycle runs on the scratch copy** | Order 6: issue material → release → schedule → release schedule → MES start / log / complete ×4 → FG → close. Plan: create → submit → approve → MRP → release → create order. Direct order vs plan-derived order. PO-02 blocking rules. Order cancel. |
| **Read-only data audit of the real DB** | ~95 consistency queries (BOM, routing, work center, orders, WIP, reservations, schedules). |
| **Code reading** | Readiness engine, order create / cancel / release, MES completion, MRP, scheduling downtime, cost rules. |
| **Not covered** | Quality / NCR / rework / scrap flows, subcontract flows end-to-end, operator JS screens, dashboard / report **numbers** (only that they render), API endpoints (checked statically, not executed), shift / calendar rules in the scheduler. |

All scratch scripts live in the session scratchpad (not in the repo). The real database was only read.

---

## 1. Top findings (read these first)

| # | Sev | Finding | Detail |
| :-: | :-: | :--- | :-: |
| 1 | 🔴 | **Order created from a Plan reserves ≈ 2× the material** of the same order created directly (product 132 × 5: raw-01 **1,840 vs 915**, raw-07 **19,195 vs 9,570**). The Store would be asked to pick double. | B-01 |
| 2 | 🔴 | **Cancelling a released order does not return issued material**; after cancel the *Return* action is forbidden, the WIP stays `active`, the slip stays "partially issued". Issued stock is stranded. | B-04 |
| 3 | 🟠 | **An order can be closed with no finished goods received** (verified: closed with 0 receipts). | B-05 |
| 4 | 🟠 | **Operations complete beyond the material available.** Readiness says "ready 1.82 of 5" but the operation completed 5 units; nothing enforces the readiness quantity. | R-05 |
| 5 | 🟠 | **Machine downtime never affects readiness**: it checks statuses `scheduled / in_progress`, downtimes only have `open / closed`. | R-07 |
| 6 | 🔴 | **Whole screens crash on MySQL** because of dropped columns/relations: WC dashboard & report, MES work-center page, **BOM export**, machine export with filter, work-center-sections / pay-grade resource routes. | C-01 … C-05 |
| 7 | 🟠 | **61 routes have no permission check** — all 13 **ECO** actions (approve / release), maintenance work-order actions incl. **spare issue (stock)**, PM schedules, MRP "generate PR". Import/export uses the wrong permission. | B-11 |
| 8 | 🟠 | **Machine cost = 0 when "entered cost" is 0 but consumption × rate > 0** — WC-08 says fall back to consumption × rate. 4 seeded machines are costed at 0. | B-20 |
| 9 | 🟠 | **MRP counts materials of *alternative operations* as demand and does not pool shared materials** (each row compared with the full stock). Shortages are under-reported. | B-02 |
| 10 | 🟠 | **Releasing a Plan blocks creating its order** (`released` is rejected by "create order from plan"). | B-03 |
| 11 | 🟠 | **The temp "no-BOM" flow (planned as branch-only) is in `main`** (PR #436, commit `98ec9fe1`), behind `PRODUCTION_TEMP_FLOW`. | B-19 |

---

## 2. Production Readiness tab — how it is decided, and what is wrong with it

Code: `ProductionReadinessService` · view `orders/show.blade.php` L717–897 · written back by `ProductionOrderService::reconcileOperationReadiness()` (called on every order page view).

**Per operation, six dimensions** (material, operation/WIP, machine, subcontract, quality, batch). `ready_qty` = min of the material / dependency / subcontract quantities, forced to 0 when machine, quality, batch (or a dependency at 0) is BLOCKED. Status: blockers and ready 0 → BLOCKED; ready > 0 and < target → PARTIALLY READY; else READY. **Order:** all READY → READY; any *actionable* blocker → BLOCKED; otherwise PARTIALLY READY. Workflow dependencies ("predecessor incomplete") are *not* actionable.
**What the status drives:** only `reconcileOperationReadiness` (op `ready` if it has no blocker, else `waiting`). Material **shortage warnings do not block**; only "usable = 0" does.

| ID | Sev | Finding | Evidence |
| :-: | :-: | :--- | :--- |
| R-01 | 🟡 | `WAITING_PREDECESSOR` is defined and counted (card **"Sequential Waiting"**) but never produced → the card is always 0; a waiting operation shows red BLOCKED, and the **blocked-operation count has no card**. | `ProductionReadinessService` L21, L225; `show.blade.php` L747. Order 6: 0 ready / 0 waiting / 1 partial / 0 blockers while 3 operations are blocked. |
| R-02 | 🟡 | Order badge can read **PARTIALLY READY with 0 ready operations** (both `else` branches are identical). | L247–255. |
| R-03 | 🟠 | **Stock is not PO-04.** Availability = `SUM(quantity)` of *all* warehouses of the tenant (any branch, any warehouse type), no `max(0, quantity − reserved_qty)`. PO-04 says active standard warehouses of the order's branch only. | L419–422. *(Possible double count of "reserved" — `reserveMaterial()` only increments a counter, it does not move stock — not proven.)* |
| R-04 | 🟡 | A material shared by two operations (raw-11 in Op 30 and Op 40) is compared with the **full stock for each**; stock is not shared out across operations / orders. | Order 6 data. |
| R-05 | 🟠 | **The ready quantity is informational only.** The one function that enforces it (`claimBatchToExecute`) has **no callers**. Lifecycle run: Op 10 showed "ready 1.82", only 100 of 275 of raw-07 were issued, yet Op 10 **completed 5 / 5**. | `MesExecutionService` L1139; lifecycle output. |
| R-06 | 🟠 | `release()` does not look at readiness at all (only "slip issued or partially issued"). An order with one operation fully blocked by material releases and schedules normally. | `ProductionOrderService::release` L525–560. |
| R-07 | 🔴 | **Downtime check never matches.** Readiness looks for downtime `status IN ('scheduled','in_progress')`; the model and the data use `open / closed`. Machine 56 has an *open* downtime (since 2026-11-20) but would read READY. | `ProductionReadinessService` L797–801 vs `ProductionMachineDowntime::STATUS_OPEN/CLOSED`; real DB. |
| R-08 | 🟠 | Machine "alternate" logic invents alternates: any *other active machine in the Work Center* counts, and the `alternate machines` table (RT-11: replaced by alternative operations; 0 rows) is still read. RT-06/07: an operation has exactly one resource. | L805–840. |
| R-09 | 🟡 | Quality dimension reads the **live routing operation** `quality_required`; MES uses the order-operation snapshot **or** the routing. Two answers for the same operation. | L963 vs `MesExecutionService` L1084. |
| R-10 | 🟡 | The tab shows `Target: 5.00 | Ready: 1.82` — **rounded to 2 decimals** (real value 1.8182). G1 "no rounding". | `show.blade.php` L842–843. |
| R-11 | 🟡 | Opening a page **writes** (reconcile) — also for *draft* orders, so a draft order already carries `ready` / `waiting` operation statuses. | `ProductionOrderController::show` L211–216; order 6. |
| R-12 | ⚪ | Readiness reads `overlap_enabled` (renamed to `queue_threshold_enabled`; works through an accessor) and the live routing operation instead of the order snapshot. | L565–570, L647–650. |
| R-13 | 🟡 | "PARTIALLY READY" material badge is drawn in the red "danger" style; only READY is green. | `show.blade.php` L846–849. |

---

## 3. 🔴 Crashes on MySQL (dropped columns / missing relations / missing methods)

Verified = reproduced on the MySQL copy. *Static* = found by the schema analysis, not executed.

| ID | Sev | Where | What happens | Status |
| :-: | :-: | :--- | :--- | :-: |
| C-01 | 🔴 | `OeeCalculationService` L145, `DashboardRefreshService` L88/159/166 | `production_machines.work_center_id` (dropped). **500:** `/production/intelligence/work-centers`, `/intelligence/dashboard?work_center_id=…`, `/intelligence/reports/work-center` (+ export / excel / pdf). | Verified |
| C-02 | 🔴 | `mes/work-center-detail.blade.php` L156 | `$workCenter->machines()->where('status', …)` → *"Column 'status' is ambiguous"*. **500:** `/production/mes/work-centers/{id}`. The same line prints the dropped `efficiency_percentage`. | Verified |
| C-03 | 🔴 | `app/Exports/MachineExport.php` L22 | Filter by Work Center → `work_center_id` on machines. **500.** | Verified (known F7) |
| C-04 | 🔴 | `app/Exports/BomExport.php` L30 | `with('items.childBom')` — relation does not exist. **500: every BOM export** (`/import-export/export/boms`). | Verified (new) |
| C-05 | 🔴 | `Route::resource('work-center-sections')`; `pay-grades/{id}` | `index/show` → view missing; `create/edit` → method missing; **`pay-grades.show` → method missing.** | Verified (known F6 + new) |
| C-06 | 🔴 | Static | API & services use relations / columns that do not exist: `MesExecutionApiController` L46 `productionOrder.product` (**GET api/…/mes/operations**), `ProductionPlanApiController` L80, `ProductionBatchRepository` L84, `ProductionCostTraceabilityRepository` L49, `ProductionPlanRepository` L38, `ProductionScheduleRepository` L14/38/39/45/52/59 (`scenarios`, `changeLogs`, `is_active`, `planned_start_time`), `SchedulingCalendarService` L45 `activeMachines`, `QualityInspectionApiController` L52 `operation_name`, `QualityPlanApiController` L37 `code`, `CodeService` L373/404 (`barcode`, `qr_code` on machines / work centers → **scanner lookup**). | Static |
| C-07 | 🔴 | `ProductionEcoService` L38, `RoutingRecommendationService` L125 | `Routing::where('product_id')` (dropped) and routing status `'approved'` (not a routing status). ECO create / recommendation crash. *(ECO is deferred — RT-33 — but it is reachable.)* | Static |
| C-08 | 🟡 | `ProductionPlanController::getBomExplosion` | No validation: missing `product_id` → `TypeError` 500 instead of 422. | Verified |

---

## 4. 🟠 Silent data loss (keys that are ignored)

`create()` keys that are not in `$fillable` are **dropped without an error**.

| ID | Sev | Where | Effect |
| :-: | :-: | :--- | :--- |
| S-01 | 🟠 | `SubcontractProcurementOrchestrator` L66–76 / L144–149, `SubcontractReceiptOrchestrator` L174–180, `SubcontractMaterialBalanceService` L304–308 / 432–437 / 463–468 | Progress-log rows are written with `quantity_completed`, `status`, `log_type`, `logged_by` — none exist. The "subcontract setup required / dispatched / received" events become **blank progress rows** (remarks only, **no user**, no type). |
| S-02 | 🟡 | `BatchProductionService` L846–849 | Genealogy rows lose `component_product_id` and `recorded_by` → traceability does not say *which component*. |
| S-03 | 🟡 | `ProductionExecutionService` L909, `QualityInspectionService` L105 / L379 | Quality plan `code` ignored; the auto-created plan status differs (`active` vs `approved`). |
| S-04 | 🟠 | `MesExecutionApiController::resolveScheduleOperationId` L117–140 | The API *invents a schedule* for any order operation: status **`confirmed`** (not a schedule status), `start_date/end_date` ignored, `created_by ?: 1`. Bypasses "generate schedule → release". |
| S-05 | 🟡 | `ProductionMasterImportService` L60–92, L781–824; `WorkCenterExport` | Import/export still carry `capacity_hours_per_day`, `efficiency_percentage` (work center), machine `work_center_id` (known F8) — ignored / exported blank. |

---

## 5. 🟠 Business-rule misalignments

| ID | Sev | Rule | Finding | Evidence |
| :-: | :-: | :--- | :--- | :--- |
| **B-01** | 🔴 | PO-08 / PO-12 (one combined reservation per product from the selected structure) | **Plan → order doubles the material.** `createFromPlan` clones *every plan requirement row* (already multi-level) into reservations **and** then runs the multi-level snapshot, which adds them again. Same product × qty: direct order **915 / 9,570**, plan order **1,840 / 19,195** (≈ 2×, +residual). The requisition slip carries the same inflated quantities. It also does not use the PO-06/07 selected operations / materials. | `ProductionOrderService::createFromPlan` L93–235; scratch run: order 7 (plan) vs order 8 (direct). |
| **B-02** | 🟠 | BM-04 / BM-07 rule 4 / PO-08 | **MRP:** (a) allocations in an *alternative operation* count as required demand; (b) one requirement row per allocation — the same material twice (raw-11: 15 + 5) is compared with the full stock **each time**, so shortage is under-reported; (c) stock = tenant-wide `available_qty` (PO-04). | `MrpEngineService::explodeBom` L92–150; plan 1 rows 5 and 7; routing 14 op 58 is alternative with 3 "primary" allocations. |
| **B-03** | 🟠 | Plan lifecycle | `release` sets the plan `released`; `create order from plan` accepts only `approved / mrp_generated`. After "Release to shop floor" the order **cannot** be created. Release does nothing else. | `ProductionPlanService::release` L214; `createFromPlan` L98. |
| **B-04** | 🔴 | PRODUCTION_ORDER_GUIDE ("unissued stock returned") | **Cancel keeps issued material issued.** Run: issued 10 + 10, cancel → stock unchanged, `quantity_issued` stays 10, slip "partially issued", WIP `active`; `return` is allowed only for released / in-progress, so it can no longer be undone. `cancelled_by ?: 1`. | `ProductionOrderService::cancel` L730–770; `ProductionOrderPolicy::return` L50. |
| **B-05** | 🟠 | Order lifecycle | **`close` needs only "completed"** — no check that FG was received. Run: closed with 0 receipts. | `ProductionOrderPolicy::close` L74. |
| **B-06** | 🟠 | Order lifecycle | The order **auto-completes on the last operation**, so *Receive FG* and *Complete* on the order screen return **403** (policy needs released / in-progress). FG can only be received via the WIP "convert to FG" route. Until then `order.quantity_produced` = **only the last "complete" quantity** (3) although the operation shows 5 (partial logs are not added); WIP shows 2. After conversion it becomes 5. | Run on order 6: `receive-fg` 403, `convert-fg` OK "5 units". |
| B-07 | 🟠 | Material control | Same as R-05 — production is not limited by issued / available material; consumption of BOM materials is only "issued", never "consumed". | |
| B-08 | 🟡 | Order numbering | Number is unique per **tenant + branch**: `ORD-2026-000001` exists twice (branch 1 and 3). Number-based lookups (scanner, traceability, search) are ambiguous; numbering is `max+1` without a lock. | Real DB; `ProductionOrderNumberService`. |
| B-09 | 🟠 | WC-47 | `WorkCenterSection::hasProductionHistory()` checks `production_orders.section_id` / `production_schedules.section_id` (do not exist) → protection only via routing operations. **Routing delete ignores BOMs (F3)** and **batch delete ignores routings (F4)** — still open. | `WorkCenterSection.php` L289–306; `RoutingRepository::delete` L82; `SectionManualProductionController::destroy`. |
| B-10 | 🟠 | WC-33 / PO-02 #5 | Starting a **maintenance work order sets the machine `under_maintenance` without arming the calculation countdown**; capacity snapshot stays stale and the PO-02 gate (which reads snapshots) does not see it. *(PO-02 itself works when the status is changed in the machine form — verified: blocked before and after recalculation.)* | `MaintenanceWorkOrderService` L521. (Open item WC-FUT-02.) |
| **B-11** | 🟠 | RBAC (`AccessService`) | **61 routes without a visible permission check.** Real ones: **ECO** — all 13 (`submit/approve/reject/release/close/cancel`, CRUD; also `tenant_id() ?? 1`); **maintenance WO** `schedule`, `assignments`, `start`, `complete`, `cancel`, `spares`, `spares/{id}/issue`, `edit`, `update`, `destroy`, `breakdown`; PM schedules (6); maintenance dashboard; `variances/create-eco`; subcontract analytics / check-stock; `quality/quick-inspection`; planning-exceptions; routing operations AJAX; plan AJAX; schedule calendar / work-center views; BOM `check-child` and API `clone`; dashboard API (3); inventory `mrp-shortage/generate-pr`. **Import / export / templates are guarded by `production.mes.execute`** (operators) instead of BOM / routing / work-center permissions. | Route × controller scan; `ProductionImportExportController` L45/62. |
| B-12 | 🟠 | G7 (real user, never a fallback) | **63** `userId ?: 1` / `?? 1` in Production (RoutingController 10, ProductionEcoService 7, CapacityPlanningService 7, RoutingApiController 7, QualityInspectionService 6, SubcontractMaterialBalance 5 …). Examples: `cancelled_by ?: 1`, `Auth::id() ?? 1` on schedule store, `created_by ?: 1` in the MES API bridge. | grep |
| B-13 | 🟠 | G6 (tenant isolation) | **14** `tenant_id() ?? 1` fallbacks (ECO controller 6, `StoreRoutingRequest` / `UpdateRoutingRequest` 4, `BomLifecycleService`, `SubcontractAnalyticsController`) → a missing tenant context silently becomes tenant 1. A scan also lists **125** model queries using `withoutGlobalScopes()` and **19** `DB::table()` reads with no `tenant_id` in the same chain (most are id look-ups inside tenant-bound rows; not individually reviewed). | scan |
| B-14 | 🟡 | G1 no rounding / G2 durations | **434** `number_format(…, 2)` in 38 views (orders/show 69, MES operator 40, report views 214, WIP, plans, MRP …), **152** `round(…, 2)` in services, **21** `toFixed(2)`. The downstream screens were never moved to the 4-decimal rule. | grep |
| B-15 | 🟡 | G4 wording | **32** "pcs" in views; "Speed" in 2 lang entries. | grep |
| B-16 | 🟠 | Scheduling | An **open downtime** (`end_time` null) is booked as `start → now` (`Carbon::parse(null)` = now): the machine looks free for every future slot. Capacity calc still reads the dropped `efficiency_percentage` and sums shifts (known M1). | `SchedulingService` L934–946 |
| B-17 | 🟡 | Statuses | Requisition-slip statuses are free strings in **8 spellings** (`Fully Issued`, `Partially Issued`, `partially issued`, `partial`, `pending`, `Pending`, `issued`, `fully_issued`); slip logic lives in the **Sales** module (`MaterialRequestService`). WIP, quality, NCR, rework have no status constants. | grep |
| B-18 | 🟡 | BM-31 | `under_revision` still a BOM constant and still **editable** in `ProductionBomPolicy`; BOM model has duplicate constants (`approved`, `inactive` twice). | `ProductionBomPolicy` L33 |
| **B-19** | 🟠 | Governance | The temp "no-BOM" flow (plan: *branch-only, never merged*) is in `main` — commit `98ec9fe1` came in through PR #436. With `PRODUCTION_TEMP_FLOW=true` it switches off RT-06/07, BOM and cost rules in ~15 places. Default `false`; `.env` has it commented out. | `config/production.php` L30; `git branch --contains` |
| B-20 | 🟠 | WC-08 | `Machine::operatingCostBreakdown()` returns **0** when the entered cost is 0 even if consumption × rate > 0 (there is no "energy only" branch). Machines 59, 60, 62, 65 (consumption 7.5 / 5.5 / 12 / 9, rate 8.5) come out at 0 by this rule (their effect in the saved snapshots / routings was not re-checked). | `Machine.php` L214–240; real DB |
| B-21 | 🟡 | PO-03 / BM-09 | `ProductionOrderService::create()` — the old create path (formulas / parameters, no tenant check on `bom_id`) — has **no callers**; it also reads another tenant's BOM by id. | L37–90 |
| B-22 | 🟡 | Routes | `quality/rework/{id}` and `quality/rework/{rework}` both map to `ReworkController@show`; `production.quality.rework.index` is referenced (guarded by `Route::has`) but does not exist, so the *Quality & Rework* buttons never show. | routes |

---

## 6. What is still part of the OLD Work Center / BOM / Routing design

### 6.1 Code

| Area | Leftover | Where / how many |
| :--- | :--- | :--- |
| **WC** | `efficiency_percentage` read from the work center (column dropped) | `SchedulingService` L1049 / 1359 / 2513, `MrpEngineService` L223, `PlanningValidationService` L88, `CapacityPlanningService` L57, `WorkCenterDashboardController` L70 / 101, `mes/work-center-detail.blade.php` L156–158, `WorkCenterExport` — 20 refs (known M1 / M2) |
| WC | `capacity_hours_per_day`, `hourly_cost`, machine `work_center_id` in import / export | `ProductionMasterImportService`, `WorkCenterExport` |
| WC | `production_machines.work_center_id` queries | OEE, dashboard refresh, machine export (C-01, C-03) |
| WC | `supports_manual_production`, `manual_capacity_per_hour` (WC-IMPL-06) | `WorkCenterSectionController` L61 / 169–188, DTO, `WorkCenterSectionService` L142 (known F5) |
| WC | **Old shift design:** `InternalWorkerAllocation` model + service + controller on tables `production_internal_worker_allocations` / `production_section_shifts` — neither table exists; **no route** reaches them | 4 files, ~18 refs (known F9) |
| WC | Machine `barcode` / `qr_code` lookups | `CodeService` L373 / 404 |
| **BOM** | `replaces_item_id` aliases and `ProductionBomItemDTO` (master-row design) | 20 refs in 10 files (known F9) |
| BOM | `ProductionBomItem::childBom` used by the export but removed | C-04 |
| BOM | `RoutingOperation::bomItems()` | dead (F9) |
| BOM | `under_revision` constant / method / policy / 3 lang files | BM-31 |
| BOM | Consumption targets use master `quantity`, not `gross_quantity` | `ProductionOrderOperation` L354 / 384 (known M3) |
| BOM | `ProductionOrderService::create()` with formulas / parameters wording | B-21 |
| **Routing** | `routings.is_default` — *"A3: routing alternatives flag"*; DTO default `true`; **all 7 routings are `1`**; meaningless since RT-01 | `Routing.php` L58 / 229, `RoutingDTO`, `StoreRoutingRequest` |
| Routing | **Alternate-machines table** (0 rows) still read in 6 services | readiness, `CapacityLevelingService`, `CapacityPlanningService`, scenarios, pre-release validation, `PlanningExceptionService` (known M4) |
| Routing | `$op->routing_id` on order operations (column is `source_routing_id`) → branch never runs | `ProductionExecutionService` L362, `ProductionWipService` L729 |
| Routing | `overlap_enabled` (renamed `queue_threshold_enabled`) — alias in ~50 places | accessor / mutator keeps it working |
| Routing | `getActiveRouting()` / `getPrimaryActiveRouting()` stubs returning `null` — no callers; `[DECOUPLE-…]` markers | `RoutingRepository` L88–98 |
| Docs | `PRODUCTION_ORDER_GUIDE.md` still describes a "Material Readiness %" meter and the old release guard | `app/Domains/Production/docs/` |

### 6.2 Data (real database)

| Item | Count | Note |
| :--- | :-: | :--- |
| Machines with **no section** (ids 48, 50, 51, 52) | 4 | old "machine belongs to a work center" data; unusable in any routing or capacity |
| Routings with `is_default = 1` | 7 / 7 | old per-product default concept |
| Shifts with `company_id` NULL | 14 / 18 | known |
| Allocations with `sequence = 0` (NonWoven seeder) | 41 | known |
| Machines with entered cost 0 but consumption × rate > 0 | 4 | B-20 |
| Duplicate order number across branches | 1 | B-08 |
| Rows from the old routing-embedded materials design (`bom_id` NULL) | 0 | clean |
| Alternate-machine rows | 0 | clean |
| BOM master vs allocations, UoM, sub-BOM, operation-in-routing, routing resource consistency, WIP / reservation / receipt sums, schedule ↔ operation statuses | — | **all consistent** (the 3 "master ≠ allocation" rows of BOM 18 are *correct*: they are materials of an alternative operation, BM-07 rule 4) |
| BOMs 23 / 24 / 25 point to inactive sub-BOM 19 | 3 | correct BM-08 warning; a newer approved BOM 27 exists |

---

## 7. Verified OK (no finding)

* Plan lifecycle (create → submit → approve → MRP → release) and the full **order → release → schedule → MES → WIP convert** path run on MySQL without error.
* **PO-02** blocking works through the snapshot path (BOM inactive / expired, routing historical, section with pending recalculation, machine inactive / under maintenance **after the machine form is saved** → blocked; active again after recalculation → allowed).
* Direct order matches the MRP numbers; shared-material pooling on `createDirect` (one reservation per product) is correct.
* BOM ↔ allocation ↔ routing data integrity, one approved BOM per product + type, sub-BOM rules, expiry dates (BM-02 / BM-08), WC snapshots present for every work center and section, no stuck countdowns.
* 129 of the 177 crawled GET pages return 200 (others are 403 by policy, 404 for other-branch data, or are the crashes above).
* Lang: only 2 dynamic keys unresolved; all 4 unknown route names are guarded by `Route::has`; **96 English keys are missing in `hi` / `bg`**; the English file defines 41 keys twice (12 with different text, e.g. `capacity_per_hour`, `scrap_quantity`, `active_machines`).

---

## 8. Decisions needed before any fix (none taken)

1. **Plan → order (B-01 / B-02 / B-03):** should `createFromPlan` use the same engine as `createDirect` (PO-06/07/08/12), and should MRP follow BM-04 / BM-07 / PO-04? What should *Release* on a Plan mean?
2. **Cancel (B-04):** auto-return issued material to the issuing warehouse, or block cancel until it is returned? What happens to WIP and the slip?
3. **Close / FG (B-05 / B-06):** require FG received (or an explicit "no FG") before *close*; should the order stay `in_progress` until FG is received, or keep auto-complete and move the receive action?
4. **Readiness (R-05 / R-06):** advisory (as today) or enforcing (cap logged quantity at ready quantity; block release)? Which stock definition — PO-04 for the whole module?
5. **Authorization (B-11):** which permissions guard ECO, maintenance, PM, import / export?
6. **Temp no-BOM flow in `main` (B-19):** keep, or remove from `main`?
7. **Old-design clean-up:** one task for the dead WC / BOM / routing code (section 6.1) after you confirm; ECO stays deferred.
8. **Machine cost (B-20):** confirm WC-08 — "0 entered" means "not filled" → use consumption × rate.
