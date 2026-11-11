# SCRATCH — Happy-Path (Ideal Flow) Plan — parked for later

> **Status:** 📝 Scratch / parked (2026-11-11). Not yet recorded as tasks. Pick up later.
> **Goal:** make the ideal flow run end-to-end once: Work Center created once → Routing created once → BOM created once → Production Order → shop floor → finished goods → close. Edge cases later.

## Probe results (2026-11-11)
A temporary probe test (copy kept at the session scratchpad as `TmpHappyPathProbeTest.php`; removed from the repo) ran the flow with new-design data (routing without product, BOM linked to routing, materials allocated to operations).

| Step | Result |
| :--- | :--- |
| Create Work Center, section, machine; Recalculate (`CountdownCalculationEngine::recalculateNow`) | ✅ Works — 1 section snapshot, 1 WC snapshot |
| Routing via web form (`production.routing.store`, `operation_type = manufacturing`) → submit → approve | ✅ Works — routing becomes `active` |
| BOM create via `ProductionBomService::create()` with 2 allocations (same material, 2 operations) → submit | ✅ Works — 1 master row (qty 3 = 2 + 1), 2 allocations; status `pending_approval` |
| **BOM approve** (`ProductionBomService::approve()`) | ❌ `RelationNotFoundException: Call to undefined relationship [childBom] on model ProductionBomItem` — from `ProductionBom::refreshWarningStatus()` `ProductionBom.php` 212 (`loadMissing(['items.material', 'items.childBom'])`), 226 (`$item->child_bom_id` on master rows — column dropped) |
| **Production order create** (`production.orders.store` → `ProductionOrderService::createDirect()`) | ❌ `Call to undefined method Routing::isPerBomBaseQuantity()` — `ProductionOrderService.php` 179 and 1339 (old routing-with-materials logic, gated on `routing.product_id`, filters allocations by operation not by `bom_id`) |
| Release → issue materials → log progress → receive FG → close | ❓ Not reached |

* Existing tests `ProductionOrderTest.php` / `ProductionBomTest.php` use the **old design** fixtures (routing with `product_id`, machine with `work_center_id`, BOM without `routing_id` / allocations) → 19 failed / 14 passed for unrelated reasons; they are not a reliable signal.

## Proposed order (to confirm before recording as tasks)
* **HP-0 — Permanent "ideal flow" end-to-end test** (from the probe): Work Center → Routing → BOM → order → release → issue → progress → FG receipt → close. Acceptance check for the phase; shows the next break after each fix.
* **HP-1 — BOM approval works** (from BM-IMPL-08): sub-BOM read from allocations (#7, #11), dropped-column queries fixed (#9 + `refreshWarningStatus` 212/226), circular check walks allocations (#10). Sub-BOM picker UI not needed yet.
* **HP-2 — Production order creation works** (from **deferred** RT-32 #1, #2, #3, #5 + BOM items): material per operation = allocation qty ÷ BOM base quantity from the BOM's own allocations (`bom_id`); remove `isPerBomBaseQuantity` / `routing.product_id` gate (`ProductionOrderService.php` 170–200, 1325–1350); order takes routing from the BOM; fix `child_bom_id` reads (`ProductionOrderService.php` 157, 504, 510 — BM-IMPL-08 #7, #8); reservations use gross quantity incl. scrap (BM-IMPL-04 #1, #2, #4, #5).
* **HP-3 — Release → shop floor → FG → close:** whatever HP-0 finds after HP-2; each break logged as a task first.
* **HP-4 — Main figures right:** WC-IMPL-07 (labor cost 12× too high — annual CTC read as monthly).
* **Decision needed:** HP-2 / HP-3 touch production orders, which are **deferred** (RT-32, BM-DEF-03) → un-defer only these ideal-path items.
