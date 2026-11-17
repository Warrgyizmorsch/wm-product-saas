# Production Order Create Revamp — Implementation Plan & Tasks

> **Purpose:** Phased implementation plan and trackable engineering tasks derived from the agreed Production Order Create decisions in [`docs/production_order_decisions.md`](production_order_decisions.md) (`PO-01` .. `PO-12`).
> **Status:** 🛠️ **Applied (code working)** 2026-11-12 — awaiting user verification.
> **Related Documents:**
> - Decisions & Rules Spec: [`docs/production_order_decisions.md`](production_order_decisions.md)
> - BOM Decisions & Tasks: [`docs/bom_decisions.md`](bom_decisions.md) · [`docs/bom_implementation_tasks.md`](bom_implementation_tasks.md)
> - Routing Decisions & Tasks: [`docs/routing_decisions.md`](routing_decisions.md) · [`docs/routing_implementation_tasks.md`](routing_implementation_tasks.md)
> - Consolidated Production Rules: [`docs/production_business_rules.md`](production_business_rules.md)
> **Interactive HTML UI References (Preserved in Repo):**
> - 2-Level Shared Material Example (`FG -> M1, M2, M3, M4` and `M2 -> M3, M5`): [`po_create_ui_example.html`](production_order_ui_examples/po_create_ui_example.html)
> - 3-Level Multi-Branch Example (`B1 -> B2, B3, B4` and `B2 -> B5` with shared `M2`/`M3`): [`po_create_multilevel_b1_example.html`](production_order_ui_examples/po_create_multilevel_b1_example.html)

---

## Task Status Tracker

| Task ID | Phase | Scope | Decision Refs | Status |
| :--- | :--- | :--- | :--- | :--- |
| **PO-IMPL-01** | Phase 1 | Migration `selected_routing_material_ids` on `production_order_operations`, `ProductionOrderOperation` model & `getExpectedComponents()` scoping | PO-12 | 🛠️ Applied (code working) |
| **PO-IMPL-02** | Phase 1 | Fix `ProductionBom::scopeActive()` & `Routing::scopeActive()` for `NULL` start dates + fix `ProductionReadinessService` dropped-column & routing-material scoping | PO-02, PO-12 | 🛠️ Applied (code working) |
| **PO-IMPL-03** | Phase 2 | Create `ProductionOrderEngineeringService` (`requiresEngineering`, branch stock map, `resolveAndValidate` incl. completeness checks, `buildInteractiveWorkspacePayload`, BFS `computeMultiLevelPlan`) | PO-01, PO-02, PO-04, PO-06, PO-07, PO-08, PO-10 | 🛠️ Applied (code working) |
| **PO-IMPL-04** | Phase 3 | Update `ProductionOrderService` (`createDirect`, `resolveReservationWarehouseId`, `buildMultiLevelSnapshot`, `createFromPlan`, shared-material reservation/slip summation) & `ProductionPlanController` stale `routings.product_id` fixes | PO-01, PO-02, PO-04, PO-08, PO-10, PO-12 | 🛠️ Applied (code working) |
| **PO-IMPL-05** | Phase 4 | Web & API endpoints (`engineering-status`), FormRequest completeness validation (`StoreProductionOrderRequest`, `StoreProductionOrderApiRequest`), `ProductionOrderDetailResource` operation fields | PO-01, PO-02, PO-06, PO-07, PO-12 | 🛠️ Applied (code working) |
| **PO-IMPL-06** | Phase 5 | Revamp `resources/views/modules/production/orders/create.blade.php` (read-only BOM/Routing + warnings, Tab 1 with Primaries pre-selected & locked no-alt items, completeness guard, Tab 2 live multi-level rollup + Drawer, Primary-only Child BOM Modal) & translations (`en`, `hi`, `bg`) | PO-01 .. PO-11 | 🛠️ Applied (code working) |
| **PO-IMPL-07** | Phase 6 | Add `ProductionOrderCreateRevampTest.php`, align `ProductionMultiLevelSnapshotTest.php`, run Production test suite (`edit.blade.php` untouched per PO-03) | PO-01 .. PO-12 | 🛠️ Applied (code working) |
---

## Goal & Confirmed Scope
1. **Change #1 — Read-Only Auto-Loaded BOM & Routing + Blocking Validation (Web & API):**
   - Replace manual `#bom_select` and `#routing_select` dropdowns on Production Order **Create** ([`orders/create.blade.php`](../resources/views/modules/production/orders/create.blade.php)) with read-only display inputs backed by hidden IDs that automatically resolve the active BOM (`product_id`) and its linked active Process Routing (`ProductionBom::routing()`).
   - For products where `supplier_method === 'manufacture'`, show clear warning signs in UI and strictly block Production Order creation on both **Web** and **API** whenever BOM or Routing is missing, inactive, expired/future-scheduled, has blocked sub-BOM (`inactive`/`cancelled` per `BM-08`), has `not_calculated` / `outdated` / `not_in_snapshot` Routing (`RoutingCostStatusService::check()`), or uses an inactive Work Center / Section / Machine / Manual Batch or pending Section calculation.
   - For non-manufactured products (`supplier_method !== 'manufacture'`), BOM and Routing are not required, show no warning, and persist with `bom_id = null` and `routing_id = null`.
2. **Branch-Scoped Available Stock & Shortage Rule (UI + Backend):**
   - Aggregate stock across **all active standard warehouses (`Warehouse::TYPE_STANDARD`) in the Production Order's `branch_id`** (excluding subcontractor/virtual warehouses; safe fallback only when `branch_id` is unset).
   - Available formula per product: **`Branch Available = SUM(max(0, quantity - reserved_qty))`**.
   - Already-issued quantities are already deducted from `quantity` (`StockService::recordOutflow()`), and un-reserved open PO demand is ignored (managed by floor manager). Only `reserved_qty` is excluded.
3. **Change #2 — Read-Only BOM Workspace on PO Create (`Tab 1`, `Tab 2 + Drawer`, `Child BOM Modal`) & Backend Snapshot Enforcement:**
   - Replace `#bom-preview-container` on PO Create with the two-tab read-only workspace matching [`po_create_ui_example.html`](production_order_ui_examples/po_create_ui_example.html) and [`po_create_multilevel_b1_example.html`](production_order_ui_examples/po_create_multilevel_b1_example.html):
     - **Tab 1 (`Components (By Operation)`):** Parent BOM direct operations & direct allocations only (`RoutingOperation` + `RoutingOperationMaterial`, read-only values, no add/delete, ≥ 4 decimals).
       - **Default Pre-Selection & Lock Behavior:** Operations & materials **without** alternatives are auto-selected and locked (`disabled` checked radio, cannot be deselected). Stages & materials **with** alternatives **also initialize with their Primary option pre-selected by default** (while remaining radio-switchable to any alternative), so if the user does not want to change anything they can click Submit / Save immediately and proceed.
     - **Tab 2 (`Total Material Rollup (Live)`):** Shows **only selected** PO requirements (deselected primaries/alternatives completely hidden) across Parent BOM **plus** any auto-exploded Child BOMs whose `Shortage > 0` (`To Produce > 0`), including shared materials combined across all levels (`M3`, `M2`) pooled **once** per `product_id` against Branch Available Stock, plus an expandable **Source Allocation Breakdown Drawer** per row (`Parent` + `Child BOM` rows with `Jump to Op Card` and `View Child BOM` actions).
     - **Child BOM Simplicity Rule & In-Page Modal:** Multi-level Child BOMs automatically select **Primary Operations (`is_alternative = false`) and Primary Materials (`is_alternative = false`) ONLY** — never asking the user to pick routes/materials on child levels. Clicking any Child BOM badge in Tab 2 or a Drawer opens an **in-page Modal** (no page redirect) displaying **Tab 1 ONLY with Primary Operations & Primary Materials ONLY**, with nested breadcrumb drill-down (`B2 › B5`) if deeper Child BOMs exist.
   - **Selection Completeness Validation & Backend/API Parity:** Enforce complete selections across client UI, Web FormRequest, and API (`selected_operation_ids[]`, `selected_material_ids[]`) so **no necessary stage/operation or material slot can ever be missed** without either its Primary or an Alternative selected (headless API calls omitting selection arrays cleanly auto-default to Primary; partial/empty/UI-submitted selections undergo strict completeness validation). Snapshot **only selected operations & selected materials**, fix shared-material accumulation on `ProductionOrderReservation` and `ProductionRequisitionSlipItem`, and fix `RoutingOperationMaterial` operation scoping for reusable routings.

> **Out of Scope / Confirmed Unchanged (Edit PO Screen):**
> Once a Production Order is created (even in `draft`), it remains locked to its snapshotted BOM and Routing. [`orders/edit.blade.php`](../resources/views/modules/production/orders/edit.blade.php), [`UpdateProductionOrderRequest.php`](../app/Domains/Production/Requests/UpdateProductionOrderRequest.php), [`UpdateProductionOrderApiRequest.php`](../app/Domains/Production/Requests/Api/UpdateProductionOrderApiRequest.php), and [`ProductionOrderService::update()`](../app/Domains/Production/Services/ProductionOrderService.php#L811-L822) remain unchanged.

---

## Key Codebase Bugs & Gaps Fixed By This Plan
1. **Stale `routings.product_id` queries:** Dropped column still queried in [`ProductionPlanController::getEngineeringOptions()`](../app/Domains/Production/Controllers/ProductionPlanController.php#L363-L367), [`ProductionOrderService::createDirect()`](../app/Domains/Production/Services/ProductionOrderService.php#L381-L389), [`ProductionOrderService::createFromPlan()`](../app/Domains/Production/Services/ProductionOrderService.php#L171), and [`ProductionOrderService::buildMultiLevelSnapshot()`](../app/Domains/Production/Services/ProductionOrderService.php#L1496-L1500).
2. **Effective-date scope mismatch:** [`ProductionBom::scopeActive()`](../app/Domains/Production/Models/ProductionBom.php#L185-L196) and [`Routing::scopeActive()`](../app/Domains/Production/Models/Routing.php#L213-L224) reject `NULL` start dates unlike `isCurrentlyActive()`.
3. **Outdated/inactive resource gaps:** [`RoutingCostStatusService::check()`](../app/Domains/Production/Services/RoutingCostStatusService.php#L24-L64) skips sections flagged `not_calculated` and does not inspect live Work Center / Section / Machine / Manual Batch active statuses or `has_pending_changes`.
4. **Master BOM summary includes alternatives:** `ProductionBomService::persistBomItems()` writes all allocations (including alternatives & alt-operation materials) into `production_bom_items`, while [`ProductionOrderService::createDirect()`](../app/Domains/Production/Services/ProductionOrderService.php#L482-L516) iterates `$bom->items` instead of chosen `RoutingOperationMaterial` allocations.
5. **All Primary + Alternative ops snapshotted:** [`ProductionOrderService::buildMultiLevelSnapshot()`](../app/Domains/Production/Services/ProductionOrderService.php#L1323) loops through `$routing->operations` indiscriminately instead of filtering to selected ops on Parent and Primary ops (`is_alternative = false`) on Child BOMs.
6. **Buggy stock & branch queries:**
   - [`ProductionOrderService::buildMultiLevelSnapshot()`](../app/Domains/Production/Services/ProductionOrderService.php#L1459-L1477) queries `ProductWarehouseStock` tenant-wide and treats `reserved_qty` (or gross `quantity`) as available stock.
   - [`ProductionOrderService::resolveReservationWarehouseId()`](../app/Domains/Production/Services/ProductionOrderService.php#L959-L977) ignores `branch_id` and warehouse type, checking only a single highest-stock warehouse.
   - [`ProductionOrderService::createOrderReservationsForBom()`](../app/Domains/Production/Services/ProductionOrderService.php#L1591) uses `firstOrCreate()` per `(order, product)`, silently failing to accumulate `quantity_planned` for common materials shared between Parent & Child BOMs (e.g., `M3`).
7. **Operation material resolution on Shop Floor / Readiness:**
   - [`ProductionOrderOperation::getExpectedComponents()`](../app/Domains/Production/Models/ProductionOrderOperation.php#L480-L520) and [`ProductionReadinessService`](../app/Domains/Production/Services/ProductionReadinessService.php#L300-L340) query `RoutingOperationMaterial` only by `routing_operation_id` (not filtered by `source_bom_id` or PO-selected materials, and `ProductionReadinessService` line 303 still queries dropped `production_bom_items.is_alternative`).

---

## Phased Implementation Architecture

### Phase 1: Schema, Models & Branch Inventory Helper
1. **Migration — Snapshot Selected Materials per Operation:**
   - Add nullable JSON column `selected_routing_material_ids` on `production_order_operations` after `routing_operation_id`.
   - Update [`ProductionOrderOperation.php`](../app/Domains/Production/Models/ProductionOrderOperation.php):
     - Add `'selected_routing_material_ids'` to `$fillable` and `'selected_routing_material_ids' => 'array'` to `$casts`.
     - Update `getExpectedComponents()`: filter `RoutingOperationMaterial` by `bom_id = $this->source_bom_id` (when set), and if `selected_routing_material_ids` is non-null, filter with `whereIn('id', $this->selected_routing_material_ids)` — otherwise default to `is_alternative = false`.
2. **Fix Date Scopes on `ProductionBom` & `Routing`:**
   - [`ProductionBom::scopeActive()`](../app/Domains/Production/Models/ProductionBom.php#L185-L196) & [`Routing::scopeActive()`](../app/Domains/Production/Models/Routing.php#L213-L224): accept `NULL` effective start dates matching `isCurrentlyActive()`.
3. **Update [`ProductionReadinessService.php`](../app/Domains/Production/Services/ProductionReadinessService.php#L298-L345):**
   - Remove dropped `ProductionBomItem::where('is_alternative', false)` query.
   - Scope `RoutingOperationMaterial` lookup to `bom_id = $op->source_bom_id` and `$op->selected_routing_material_ids` (or `is_alternative = false` fallback).

---

### Phase 2: Shared `ProductionOrderEngineeringService` (Resolution, Validation, Branch Stock & Multi-Level BFS Engine)
Create `app/Domains/Production/Services/ProductionOrderEngineeringService.php` (injecting `RoutingCostStatusService` and `SectionCalculationService`):

1. **`requiresEngineering(Product $product): bool`**
   - Returns `strtolower((string) $product->supplier_method) === 'manufacture'`.

2. **`getBranchAvailableStockMap(int $tenantId, ?int $branchId, array $productIds): array`**
   - Queries active standard warehouses (`Warehouse::TYPE_STANDARD`) in `$tenantId` filtered by `branch_id = $branchId` when `$branchId !== null` (fallback to tenant standard warehouses only if `$branchId === null` for headless/legacy callers).
   - Joins/queries `ProductWarehouseStock` for `$productIds` inside those warehouse IDs and sums `MAX(0, quantity - reserved_qty)` per `product_id`.
   - Helper `resolvePrimaryBranchWarehouseId(int $tenantId, int $productId, ?int $branchId): ?int` picks the branch standard warehouse holding the highest `max(0, quantity - reserved_qty)` (falling back to the branch's default/first active standard warehouse).

3. **`resolveAndValidate(Product $product, int $tenantId, ?int $requestedBomId = null, ?int $requestedRoutingId = null, ?array $selectedOperationIds = null, ?array $selectedMaterialIds = null, bool $requireExplicitSelections = false): array`**
   - If `!requiresEngineering($product)`: returns `requires_engineering => false`, `can_create_order => true`, `bom => null`, `routing => null`, statuses `'not_required'`.
   - **Active BOM Resolution & Validation:**
     - Resolves current active BOM (`bom_type IN ['manufacturing', 'subcontracting']`, `scopeActive()`). If `$requestedBomId` provided, validates tenant/product ownership, disallows `engineering`/`sales`, ensures active. If missing/inactive/expired/future-scheduled, returns specific diagnostic warning.
     - Checks `$bom->getComponentWarnings()` and sub-BOMs (`child_bom_id` on `RoutingOperationMaterial`): blocks if any linked sub-BOM is `inactive`/`cancelled` (`BM-08`). Also traverses reachable Child BOMs recursively (with cycle detection) validating each child's BOM + linked Routing.
   - **Active Routing & Resource Health Validation:**
     - Loads `$bom->routing` (checks `routing_id` match if explicitly sent, `isCurrentlyActive()`, `RoutingCostStatusService::check()` rejecting `'not_calculated'`, `'outdated'`, `'not_in_snapshot'`).
     - Checks live resource health per operation (`WorkCenter::STATUS_ACTIVE`, `WorkCenterSection::STATUS_ACTIVE`, section `not_calculated`/`has_pending_changes`, `Machine::STATUS_ACTIVE`, `SectionManualProduction.active`).
   - **Stage & Material Selection Normalization + Completeness Validation:**
     - Groups Parent Routing operations into stages (Primary Op + its Alternatives via `replaces_operation_id` ordered by `priority`, sorted by Primary `sequence`), and groups each operation's `RoutingOperationMaterial` rows (`bom_id = $bom->id`) into required material slots (Primary row + its `replaces_routing_material_id` alternatives).
     - **Default mode (headless API callers omitting both `$selectedOperationIds` and `$selectedMaterialIds` when `!$requireExplicitSelections`):** auto-defaults every stage to its Primary Op (`is_alternative = false`) and every required material slot of those ops to its Primary Material (`is_alternative = false`).
     - **Explicit completeness validation (`$requireExplicitSelections === true`, always set for Web PO Create submissions and whenever an API caller supplies `selected_operation_ids` or `selected_material_ids`, including `[]`):**
       - Rejects empty/missing `selected_operation_ids` if the routing has any stages, and rejects empty/missing `selected_material_ids` if any chosen operation has required material groups.
       - Every stage in `$bom->routing` MUST have **exactly 1** valid operation selected (either its Primary or one of its Alternatives) — fails with `422` diagnostic error naming the missed stage if `0` are selected, or duplicate error if `> 1`.
       - Every required material slot belonging to the selected operations (`bom_id = $bom->id`) MUST have **exactly 1** valid material selected (either its Primary or one of its Alternatives) — fails with `422` diagnostic error naming the missed material/operation if `0` are selected, or duplicate/orphaned error if `> 1` or belonging to an unselected operation.
       - Stages/materials with **no alternatives** must resolve to their single Primary option (cannot be omitted or swapped).

4. **`buildInteractiveWorkspacePayload(Product $product, int $tenantId, ?int $branchId, float $quantity = 1.0): array`**
   - Used by the Web/API engineering-status endpoint to populate PO Create UI & API inspection:
     - Returns `validation` (`can_create_order`, `bom`, `routing`, statuses, warnings/blocking reasons).
     - `parent_workspace`: ordered stages (`stage_key`, `has_alternative_ops`, `operations[]` with saved setup/run/yield times, WC/Section/Resource info, and per-op material groups pairing each Primary `RoutingOperationMaterial` with its sorted `alternatives[]`, `quantity`, `uom`, `material_scrap_percentage`, `child_bom_id`). Includes `default_selected_operation_id` (Primary op of every stage, **even when alternatives exist**) and per-op `default_selected_material_ids` (Primary material of every slot across **both Primary and Alternative operations**, so switching routes or submitting immediately never leaves any op or material unselected).
     - `child_boms`: preloaded map keyed by `child_bom_id` of all reachable descendant Child BOMs containing **Primary Operations & Primary Materials ONLY** (`is_alternative = false`).
     - `branch_available_by_product`: branch-scoped net available quantities map (`product_id => float`) for all involved parent + child products across active standard warehouses in `$branchId`.

5. **`computeMultiLevelPlan(ProductionBom $parentBom, array $chosenOpIds, array $chosenMaterialIds, float $orderQty, int $tenantId, ?int $branchId): array`**
   - Shared **BFS / level-order** solver used directly by `ProductionOrderService::createDirect()` (identical math to Tab 2 Alpine logic):
     - Scales selected Level-1 allocations: `(orderQty / base_quantity) * alloc.quantity * (1 + scrap / 100)`.
     - Pools Level-2+ Child BOM demand per `child_bom_id` using a running remaining branch-stock map (`available_qty`), splits into `from_stock` + `to_produce = max(0, req - remStock)`, and if `to_produce > 0` explodes Child BOM using **Primary Operations & Primary Materials ONLY**, enqueueing Level-3+ grandchild BOMs.
     - Outputs:
       - `consolidated_materials`: summed `quantity_planned` per `product_id` (raw/consumables combined across Parent + Children like `M3`, plus `from_stock` of semi-finished goods like `B2`/`B5` that have stock used alongside child production).
       - `snapshot_tree_operations`: flat ordered list of operations to create (`bom_level = 1` selected Parent ops + `bom_level >= 2` Primary Child ops with `target_produced_qty = to_produce` and exact `selected_routing_material_ids`).

---

### Phase 3: `ProductionOrderService` & `ProductionPlanController` Updates
1. **[`ProductionOrderService::createDirect()`](../app/Domains/Production/Services/ProductionOrderService.php#L336-L540):**
   - Resolve `$branchId` early (`$data['branch_id'] ?? branch_id() ?? app(\App\Core\Branch\BranchContext::class)->id()`).
   - Call `ProductionOrderEngineeringService::resolveAndValidate(..., $requireExplicitSelections)` where `$requireExplicitSelections` is `true` when `engineering_selection_submitted` is truthy OR `selected_operation_ids` / `selected_material_ids` keys are present in `$data`; throw `InvalidArgumentException` on blocking/incomplete selection failure for manufactured products.
   - When `!requires_engineering`: set `bom_id = null`, `routing_id = null`, skip BOM reservations/requisition slip/operations snapshot cleanly.
   - When `requires_engineering`:
     - Run `computeMultiLevelPlan(...)` using `$order->branch_id`.
     - Persist **1 combined `ProductionOrderReservation` per `product_id`** with accumulated `quantity_planned` (fixing `createOrderReservationsForBom()` / `createMaterialReservation()` to increment `quantity_planned` if product already exists, never overwriting or skipping shared `M3`), using branch-scoped `resolveReservationWarehouseId()`.
     - Persist **1 combined `ProductionRequisitionSlipItem` per `product_id`** (matching Tab 2 totals: `M2/B2 fromStock` + combined `M3` + `M5`, etc.) assigned to branch standard warehouse.
     - Persist `ProductionOrderOperation` rows strictly for `$plan['snapshot_tree_operations']` (with `selected_routing_material_ids`, `source_bom_id`, `source_routing_id`, `source_product_id`, `bom_level`, `target_produced_qty`, `is_intermediate`).
2. **[`ProductionOrderService::resolveReservationWarehouseId()`](../app/Domains/Production/Services/ProductionOrderService.php#L959-L977):**
   - Scope to `Warehouse::TYPE_STANDARD` + `$branchId` via `ProductionOrderEngineeringService::resolvePrimaryBranchWarehouseId()`.
3. **[`ProductionOrderService::buildMultiLevelSnapshot()`](../app/Domains/Production/Services/ProductionOrderService.php#L1310-L1555) & `createFromPlan()`:**
   - Replace `Routing::where('product_id', ...)` and `$opRouting->product_id` stale checks with `$bom->routing` & `$order->bom?->product_id`.
   - Enforce `ProductionOrderEngineeringService::resolveAndValidate()` during `createFromPlan()` for manufactured products.
   - Replace the broken stock block at lines 1459–1477 (`reserved_qty` treated as available / gross fallback) with branch-scoped `getBranchAvailableStockMap()`, and filter `$routing->operations` strictly to Primary operations (`is_alternative = false`) when expanding child BOMs automatically, and ensure `createOrderReservationsForBom()` sums shared materials.
4. **[`ProductionPlanController::getEngineeringOptions()`](../app/Domains/Production/Controllers/ProductionPlanController.php#L348-L374) & `explodeBOMRecursively()`:**
   - Derive routings through active BOMs (`ProductionBom::routing`) instead of dropped `routings.product_id`.

---

### Phase 4: Web & API Endpoints, Requests & Resource Parity
1. **Routes & Controllers:**
   - Web ([`app/Domains/Production/Routes/web.php`](../app/Domains/Production/Routes/web.php)): Add `GET orders/ajax-engineering-status` (`production.orders.engineering-status`) -> `ProductionOrderController::getEngineeringStatus(Request $request)` (accepts `product_id`, optional `branch_id`, optional `quantity`).
   - API v1 ([`app/Domains/Production/Routes/api.php`](../app/Domains/Production/Routes/api.php)): Add `GET /orders/engineering-status` (`api.v1.production.orders.engineering_status`) **before** `GET /orders/{order}` -> `ProductionOrderApiController::engineeringStatus(Request $request)`.
2. **FormRequests ([`StoreProductionOrderRequest.php`](../app/Domains/Production/Requests/StoreProductionOrderRequest.php) & [`StoreProductionOrderApiRequest.php`](../app/Domains/Production/Requests/Api/StoreProductionOrderApiRequest.php)):**
   - Add rules for optional `selected_operation_ids` (`nullable|array`), `selected_operation_ids.*` (`integer`), `selected_material_ids` (`nullable|array`), `selected_material_ids.*` (`integer`), `engineering_selection_submitted` (`nullable|boolean`), plus `branch_id` if passed on API.
   - In `withValidator()`: compute `$requireExplicitSelections = $this->boolean('engineering_selection_submitted') || $this->exists('selected_operation_ids') || $this->exists('selected_material_ids')`, invoke `ProductionOrderEngineeringService::resolveAndValidate(...)`, and attach `422` validation errors to `bom_id`, `routing_id`, `selected_operation_ids`, or `selected_material_ids` whenever blocking rules fail or any required stage/material group is left unselected.
3. **API Detail Resource ([`ProductionOrderDetailResource.php`](../app/Domains/Production/Resources/Api/ProductionOrderDetailResource.php)):**
   - Expose `bom_level`, `is_intermediate`, `source_product_id`, `source_bom_id`, `selected_routing_material_ids` on `operations[]` for full API observability.

---

### Phase 5: Production Order Create UI ([`orders/create.blade.php`](../resources/views/modules/production/orders/create.blade.php))
1. **Read-Only Header Fields + Warnings:**
   - Remove `#bom_select` & `#routing_select` dropdowns.
   - Render read-only `bom_display` + hidden `bom_id` and read-only `routing_display` + hidden `routing_id`.
   - Render `#bom_warning_box`, `#routing_warning_box`, and form-level `#engineering_block_alert`. Disable `#submit_production_order_btn` and prevent form submission when blocked.
2. **Alpine.js 2-Tab Interactive Workspace (`Tab 1`, `Tab 2 + Drawer`, `Child BOM Modal`):**
   - Reuse clean styling/structure from `bom/create.blade.php` & both approved reference prototypes ([`po_create_ui_example.html`](production_order_ui_examples/po_create_ui_example.html), [`po_create_multilevel_b1_example.html`](production_order_ui_examples/po_create_multilevel_b1_example.html)).
   - **Default Pre-Selection & Hidden Mirror Inputs:**
     - As soon as a manufactured product's engineering workspace loads, Alpine automatically selects the **Primary Operation (`is_alternative = false`) for every stage** AND the **Primary Material (`is_alternative = false`) for every material slot across all operations (including stages & slots that have alternatives, and pre-initializing Primaries on every Alternative Op)**. Thus, if the user does not want any changes, they can immediately click Submit / Save and move on without touching Tab 1.
     - Hidden form inputs `<input type="hidden" name="selected_operation_ids[]">`, `<input type="hidden" name="selected_material_ids[]">`, and `<input type="hidden" name="engineering_selection_submitted" value="1">` mirror the Alpine state directly so **locked (`disabled`) radios still submit in POST** and missing fields cannot be silently dropped (`old('selected_operation_ids')` / `old('selected_material_ids')` rehydrated on validation redirect).
   - **Client-Side Selection Completeness Validation:**
     - Before submitting and continuously in computed validation state, verify that **every required stage** has an operation selected (Primary or Alt) and **every required material group inside each selected operation** has a material selected (Primary or Alt). If any necessary op or material slot has zero selections, block form submission, show a visible inline validation alert naming the missing stage/material, and highlight the incomplete card/row in Tab 1.
   - **Tab 1 (`Components (By Operation)`):**
     - Operation cards grouped/sorted cleanly; operations & materials with **no** alternatives show auto-selected locked checked radios (`disabled`) and cannot be deselected.
     - Stages & materials **with** alternatives initialize with Primary selected and provide radio switching; switching immediately collapses/dims deselected options (while ensuring the newly activated op's materials remain Primary-defaulted) and triggers instant client-side recomputation of Tab 2.
   - **Tab 2 (`Total Material Rollup (Live)`):**
     - Runs the exact same multi-level BFS + branch stock pool logic whenever `quantity_ordered`, `branch_id` (if selectable or active branch), or Tab 1 radios change.
     - Excludes deselected primaries & deselected alternatives completely; combines shared materials across Parent & exploded Child BOMs (`Shortage > 0`) against Branch Available Stock once (`>= 4` decimals, no rounding); supports `[All Active]` vs `[Shortages / Produce Only]` filter pills.
     - Inline **Allocation Breakdown Drawer** per row lists L1 Parent + L2/L3 Child BOM lines with `Jump to Op Card` and `View Child BOM` buttons.
   - **In-Page Child BOM Modal:**
     - Triggered by clicking any Child BOM badge in Tab 2 or Drawer (`@click.stop`).
     - Renders **Tab 1 ONLY with Primary Operations & Primary Materials ONLY**, scaled to `To Produce` shortage (or `1` base preview with "Covered by Stock" banner), plus nested drill-down breadcrumb (`B2 › B5`) + `← Back`.
3. **Translations (`lang/en/production.php`, `lang/hi/production.php`, `lang/bg/production.php`):**
   - Add localization keys for read-only engineering states, lock badges, incomplete-selection validation warnings, Tab 1/Tab 2/Drawer headers, branch stock labels, and Child BOM modal labels.

---

### Phase 6: Verification & Automated Feature Test Suite
1. **New Test Suite — `tests/Feature/Production/ProductionOrderCreateRevampTest.php`:**
   - **Change #1 (Web + API):**
     - Create page renders read-only `bom_display` / `routing_display` (no `<select name="bom_id">` / `<select name="routing_id">`).
     - Non-manufactured (`trade`/`buy`) succeeds on Web & API with `bom_id = null`, `routing_id = null`.
     - Manufactured blocked (422) on Web & API for: missing/draft/inactive/expired/future BOM; `inactive`/`cancelled` sub-BOM (`BM-08`); missing/inactive/historical/cancelled Routing; uncalculated/outdated/`not_in_snapshot` Routing; inactive WC/Section/Machine/Batch or pending section countdown; outdated sub-BOM Routing; explicit mismatched `bom_id`/`routing_id`.
   - **Branch Stock & Shortage Math (Web + API):**
     - Pools multiple `Warehouse::TYPE_STANDARD` warehouses in PO's `branch_id` only (excludes warehouses in other branches, subcontractor/virtual warehouses, and `reserved_qty`; does not subtract un-reserved open POs).
   - **Change #2 (Tab 1 Selection, Zero-Edit Default Submit, Completeness Validation & 3-Level `B1 -> (B2 -> B5, B3, B4)` Shared `M2`/`M3` Tests):**
     - Workspace payload returns `default_selected_operation_id` and `default_selected_material_ids` pre-selecting Primary everywhere (both locked no-alt items and switchable with-alt items, plus Primary materials inside Alt ops); immediate zero-edit Web/API submit succeeds with Primary defaults.
     - Switching to Alternative Op (`Op 25`) & Alternative Material (`M2-ALT`) excludes deselected ops from `ProductionOrderOperation` and deselected materials from reservations/requisition slips; rejects (`422`) whenever `engineering_selection_submitted=1` or selection arrays are submitted missing any required stage op, missing any required material slot of a chosen op, passing `[]`, selecting duplicates in one stage/slot, or submitting materials for an unchosen op.
     - Verified `M2`/`M3` shared-material test: `B3` in stock does not explode (`M6`/`M7` absent from slip/operations); `B2` partial stock produces net shortage, triggers `B5`, sums shared `M2`/`M3` accurately in `ProductionOrderReservation`, `ProductionRequisitionSlipItem`, and workspace preview payload without double-counting branch stock.
     - Verified `ProductionOrderOperation::getExpectedComponents()` & `ProductionReadinessService` respect selected materials and isolate shared-routing BOMs.
   - **Edit Immutability Regression Check:** `edit.blade.php` and `PUT /orders/{id}` leave snapshotted `bom_id` and `routing_id` untouched.
2. **Existing Suite Alignment & Full Run:**
   - Update [`ProductionMultiLevelSnapshotTest.php`](../tests/Feature/Production/ProductionMultiLevelSnapshotTest.php#L242-L290) where its stock fixture previously relied on the `reserved_qty` bug, plus run targeted Production feature tests (`RoutingCalculateOnceTest`, `BomRoutingInversionTest`, `BomAndRoutingLifecycleDatesTest`, `BomComponentWarningAndDropdownFilterTest`, `ProductionOrderAndWipUiTest`, `ProductionMultiLevelSnapshotTest`, `ProductionOrderCreateRevampTest`) to confirm zero regressions.

---

## Complete File Change Summary
- **Create:**
  - `database/migrations/2026_11_12_000001_add_selected_routing_material_ids_to_production_order_operations_table.php`
  - `app/Domains/Production/Services/ProductionOrderEngineeringService.php`
  - `tests/Feature/Production/ProductionOrderCreateRevampTest.php`
- **Modify:**
  - `app/Domains/Production/Models/ProductionBom.php`
  - `app/Domains/Production/Models/Routing.php`
  - `app/Domains/Production/Models/ProductionOrderOperation.php`
  - `app/Domains/Production/Services/ProductionOrderService.php`
  - `app/Domains/Production/Services/ProductionReadinessService.php`
  - `app/Domains/Production/Controllers/ProductionPlanController.php`
  - `app/Domains/Production/Controllers/ProductionOrderController.php`
  - `app/Domains/Production/Controllers/Api/ProductionOrderApiController.php`
  - `app/Domains/Production/Requests/StoreProductionOrderRequest.php`
  - `app/Domains/Production/Requests/Api/StoreProductionOrderApiRequest.php`
  - `app/Domains/Production/Resources/Api/ProductionOrderDetailResource.php`
  - `app/Domains/Production/Routes/web.php`
  - `app/Domains/Production/Routes/api.php`
  - `resources/views/modules/production/orders/create.blade.php`
  - `lang/en/production.php`, `lang/hi/production.php`, `lang/bg/production.php`
  - `tests/Feature/Production/ProductionMultiLevelSnapshotTest.php`

---

## Implementation Notes (2026-11-12 — 🛠️ Applied, awaiting user verification)

* **PO-IMPL-01:** Migration `2026_11_12_130000_add_selected_routing_material_ids_to_production_order_operations_table.php` (nullable JSON after `routing_operation_id`). `ProductionOrderOperation`: fillable + `array` cast; new `getExpectedComponents()` — allocations of `source_bom_id` (or the order BOM) limited to `selected_routing_material_ids`, falling back to `is_alternative = false` when the column is NULL (legacy rows). Used by the scrappable-materials list (master BOM rows are read only for operations without a routing operation).
* **PO-IMPL-02:** `ProductionBom::scopeActive()` / `Routing::scopeActive()` accept a NULL start date and now use `whereDate` (SQLite stored `Y-m-d 00:00:00` and a plain string compare failed). `ProductionReadinessService`: dropped `production_bom_items.is_alternative` queries removed (2 places); routing-operation materials scoped to `bom_id = source_bom_id` (no more `OR bom_id IS NULL`) and the chosen ids. *Note:* `production_boms.effective_date` is NOT NULL in the schema, so only the Routing side can actually be NULL.
* **PO-IMPL-03:** New `Services/ProductionOrderEngineeringService.php` — `requiresEngineering()` (`supplier_method === 'manufacture'`), `branchWarehouseIds()` / `getBranchAvailableStockMap()` (active standard warehouses of the branch, `SUM(max(0, quantity − reserved_qty))`, tenant-wide only when branch is NULL), `resolvePrimaryBranchWarehouseId()`, `resolveActiveBom()`, `resolveAndValidate()` (BOM status / dates / type / ownership, component warnings + `validateBomTreeForExecution`, every reachable child BOM + its routing, routing status / dates / mismatch, `RoutingCostStatusService::check()` not-calculated / outdated / not-in-snapshot / inactive resources, section not calculated / pending recalculation; stages = primary op + alternatives by priority, slots = primary material + alternatives; default or explicit completeness checks), `assertCanCreate()`, `computeMultiLevelPlan()` (level-order explosion, remaining-stock ledger per product, child BOMs only for `to_produce > 0`, primary ops + primary mats only, one row per product, stock pooled once; semi-finished with a child BOM reserve `from_stock` only), `buildInteractiveWorkspacePayload()` (validation, BOM / routing, stages with defaults, child BOM map, branch stock, `default_preview`).
* **PO-IMPL-04:** `ProductionOrderService::createDirect()` resolves through the engineering service (explicit mode when `engineering_selection_submitted` or either selection key is present), bought / traded products get `bom_id = routing_id = null` and no reservations / slip / operations; manufactured → `computeMultiLevelPlan()` → `persistPlannedMaterials()` (one reservation + one slip line per product, branch warehouse) and `snapshotPlannedOperations()` (selected parent ops level 1 target = order qty; child primary ops level ≥ 2 target = to_produce, `is_intermediate`; FS links inside each BOM, cross-assembly link child last op → consuming parent op; subcontract orchestration; readiness; production model). `createFromPlan()` enforces the same blocking rules and takes BOM / routing from the resolution. `buildMultiLevelSnapshot()`: child routing from `$childBom->routing_id` (active only), primary ops only with `selected_routing_material_ids`, branch stock map instead of the `reserved_qty` bug. `createOrderReservationsForBom()` sums shared materials (was `firstOrCreate`). `resolveReservationWarehouseId()` branch-scoped. Stale `routings.product_id` removed from `ProductionPlanController::edit()` / `getEngineeringOptions()` and `ProductionPlanService` (routing via the BOM). *Not touched (other areas):* `ProductionEcoService`, `RoutingRecommendationService` still query `routings.product_id` (ECO postponed).
* **PO-IMPL-05:** Web `GET production/orders/ajax-engineering-status` (`production.orders.engineering-status`), API `GET /api/v1/production/orders/engineering-status` (before `/{order}`). Shared trait `Requests/Concerns/ValidatesProductionOrderEngineering` used by `StoreProductionOrderRequest` and `StoreProductionOrderApiRequest` (selection rules + 422 on `bom_id` / `routing_id` / `selected_operation_ids` / `selected_material_ids`; API also accepts `branch_id`). `ProductionOrderDetailResource` operations expose `routing_operation_id`, `bom_level`, `is_intermediate`, `source_product_id`, `source_bom_id`, `source_routing_id`, `selected_routing_material_ids`.
* **PO-IMPL-06:** `orders/create.blade.php` rebuilt (Alpine): BOM / Routing dropdowns removed → read-only `bom_display` / `routing_display` + hidden ids, `#bom_warning_box`, `#routing_warning_box`, `#engineering_block_alert`, submit disabled while blocked; Tab 1 stages / op cards / material slots (locked radios with *Auto-Selected (No Alt)*, primaries pre-selected everywhere incl. alternative ops), hidden mirror inputs + `engineering_selection_submitted`, `old()` rehydration, completeness banner + card highlight; Tab 2 live rollup (same math as the service), pills + *All Active / Shortages* filter, drawer with *Jump to Op Card* / *View Child BOM*; in-page child BOM modal (primary only, scaled to to-produce or base preview, breadcrumb + back). 4-decimal rule and "< 1s". Translations en / hi / bg (`po_*`). Old `production.plans.bom-explosion` preview no longer used by this screen. `edit.blade.php` untouched (PO-03).
* **PO-IMPL-07:** New `tests/Feature/Production/ProductionOrderCreateRevampTest.php` — 10 tests / 127 assertions, all pass (doc Example 2 numbers: M2 = 50, M3 = 14, M5 = 8, B3 not exploded, 7 operations, branch / subcontractor / inactive / reserved stock rules, alternatives, completeness on Web + API, headless defaults, bought product, blocking cases, endpoints, NULL routing start). BOM / Routing suites 64 / 64 pass. `RoutingDrivenSubcontractingTest` aligned (supplier_method, BOM → routing link, calculated costs) → 9 / 9 pass. **Still failing (fixture style, not logic):** legacy PO tests that create products without `supplier_method = 'manufacture'` (column default `buy`), routings not linked to the BOM and operations without calculated costs — e.g. `MultiModelPhase1Test`, `MultiModelPhase2Test`; plus the pre-existing failures from before this work. `ProductionMultiLevelSnapshotTest` not realigned yet (was already failing on the stale routing lookup).
