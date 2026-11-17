# Short Full Review

> **Document Purpose:** Concise point-by-point review tracker capturing UI/code questions, exact root-cause findings from the live codebase, and agreed solutions queued for implementation.
> **Status:** 🛠️ **Applied (code working)** 2026-11-12 — Points 1 & 2 implemented, awaiting user verification.

---

## Point 1 — Operation `+ ₹2658.3335 / run` Display & Stacking Inside `Cost / Unit`

* **Status:** 🛠️ **Applied (code working)** 2026-11-12 — awaiting user verification

### 1.1 Question / Observation
* In the Routing operations table, the **`COST / UNIT`** column shows two lines stacked together (for example, `₹734.0722` and `+ ₹2658.3335 / run`, with `run` wrapping onto another line):
  * What is `+ ₹2658.3335 / run`?
  * Why is it displaying inside `Cost / Unit` and wrapping like that?

### 1.2 What It Is (Code & Formula Basis)
* `+ ₹2658.3335 / run` is the operation's **`setup_cost_per_run`** (one-time setup cost incurred **once per production batch/run** for machine changeover, tool/die setting, etc., driven by **Setup / run (m)** = `setup_time_minutes`).
* By contrast, the top number (`₹734.0722`) is **`cost_per_unit`** (cost repeated for **every piece** produced).
* Exact formulas in [`RoutingOperationCostCalculator.php`](../app/Domains/Production/Services/RoutingOperationCostCalculator.php#L47-L98), [`RoutingOperation.php`](../app/Domains/Production/Models/RoutingOperation.php#L234-L246), [`routing/create.blade.php`](../resources/views/modules/production/routing/create.blade.php#L1647-L1672), and [`routing/edit.blade.php`](../resources/views/modules/production/routing/edit.blade.php#L1780-L1792):
  * **Cost / Unit:**
    `((setup_time_per_piece_minutes + processing_time_minutes) × (labor_cost_rate + machine_cost_rate)) × (100 ÷ expected_yield_percentage)`
  * **Setup Cost / Run:**
    `setup_time_minutes × (labor_cost_rate + machine_cost_rate)`
    * Expected Yield % is **not** applied to `setup_cost_per_run`.
    * External / subcontracted operations (`is_external = true`) have `setup_cost_per_run = 0.0000`.
  * **Batch total meaning:** Running a batch of `N` units on this operation costs `(N × Cost / Unit) + Setup Cost / Run`.

### 1.3 Why It Currently Displays Like That
1. **Business design (`RT-16` / `RT-IMPL-13` in [`docs/routing_decisions.md`](routing_decisions.md#L225-L229)):** A Routing does not know the Production Order batch quantity `N`, so one-time per-run setup cannot be folded into `Cost / Unit` and must be shown separately.
2. **UI stacking + narrow column width:**
   * On Routing Create ([`routing/create.blade.php`](../resources/views/modules/production/routing/create.blade.php#L214-L216), [`L569-L575`](../resources/views/modules/production/routing/create.blade.php#L569-L575)) and Routing Edit ([`routing/edit.blade.php`](../resources/views/modules/production/routing/edit.blade.php#L229-L231), [`L585-L591`](../resources/views/modules/production/routing/edit.blade.php#L585-L591)), `+ {{ setup_cost_per_run }} / run` is stacked inside the **`Cost / Unit`** cell, which has a fixed narrow `width: 105px; min-width: 105px;` and no `text-nowrap`. That forces `run` (and the `(i)` icon in the `COST / UNIT` header) to wrap onto extra lines.
   * On Routing Details ([`routing/show.blade.php`](../resources/views/modules/production/routing/show.blade.php#L314-L316), [`L519-L524`](../resources/views/modules/production/routing/show.blade.php#L519-L524)), `+ {{ format_currency($setupRunCost, 4) }} / run` is likewise stacked underneath `Cost / Unit` inside an `8%` (`min-width: 90px`) column.

### 1.4 Agreed Solution
* **Add a new dedicated column `Op Setup Cost`** across the Routing operations table on:
  * [`resources/views/modules/production/routing/create.blade.php`](../resources/views/modules/production/routing/create.blade.php)
  * [`resources/views/modules/production/routing/edit.blade.php`](../resources/views/modules/production/routing/edit.blade.php)
  * [`resources/views/modules/production/routing/show.blade.php`](../resources/views/modules/production/routing/show.blade.php)
* **Display behavior:**
  * Keep **`Cost / Unit`** strictly for per-unit recurring cost (`cost_per_unit`).
  * Display **`setup_cost_per_run`** inside the new **`Op Setup Cost`** column (with clean non-wrapping alignment, adjusted table `min-width` / column widths so values like `₹2,658.3335` or `₹2658.3335` stay on one clean line; show `—` / `₹0` cleanly when zero or external).
* **Header Tooltip on `Op Setup Cost`:**
  * Add an `(i)` info tooltip (`data-bs-toggle="tooltip"`) on the **`Op Setup Cost`** column header explaining clearly what it is:
    * *"Operation Setup Cost (per run): one-time setup cost incurred once per production run/batch for machine changeover or tool setting = Setup / run (m) × (Labor rate + Machine rate), yield excluded. Shown separately from Cost / Unit because batch size is decided at Production Order time."*
  * Also keep the `Cost / Unit` header tooltip consistent across Create, Edit, and Show (clarifying it covers recurring per-piece cost = `(Setup / piece + Run time) × (Labor + Machine rate) × (100 ÷ Yield)` while per-run setup lives in `Op Setup Cost`).

### 1.5 Review Check (2026-11-12)
* **Findings checked against the code — accurate.** The formulas, the `105px` / `8%` cell widths, the missing `text-nowrap` and the stacked `+ … / run` line on Create / Edit / Show all match what is described. No other screen shows `/ run` inside a cost cell.
* **Missed in the original write-up (now covered):**
  * The new column changes the column count, so the full-width rows had to change too: the subcontract panel row on Create / Edit (`colspan 14 → 15`), the empty row on Show (`14 → 15`), and the Show footer.
  * Show footer: the new column gets its own total, **Σ Op Setup Cost of primary operations** (`$costSummary['setup_cost_per_run']`), next to the Primary Route Baseline. Without it the footer cells would have shifted by one column.

### 1.6 Implementation Notes (🛠️ Applied)
* **Create / Edit** (`routing/create.blade.php`, `routing/edit.blade.php`):
  * `Cost / Unit` header and cell are now `115px` with `text-nowrap`.
  * New `Op Setup Cost` header: `125px`, `text-nowrap`, `(i)` tooltip with the agreed text.
  * New `Op Setup Cost` cell (`routing-op-setup-cost`): shows `calculateSetupCostPerRun()` with the active currency symbol, or `—` when the value is 0 or the operation is external.
  * The `Cost / Unit` header tooltip now has the same text on all three screens.
  * Table `min-width` changed from `1560px` to `1700px`; colspan changed from 14 to 15.
* **Show** (`routing/show.blade.php`):
  * Removed the `/ run` line under `Cost / Unit`.
  * New `Op Setup Cost` column: `format_currency($op->setupCostPerRun(), 4)` (4-decimal rule), or `—`.
  * New footer total for the column; table `min-width` changed from `1300px` to `1420px`.
  * The *Setup Cost per Run* line in the cost summary card is unchanged.

---

## Point 2 — Unified DB-Boundary Currency Conversion Audit & Flow Verification (Production Only)

* **Status:** 🛠️ **Applied (code working)** 2026-11-12 — awaiting user verification
* **Scope:** Production domain only — Work Centers, Sections, Machines, Manual Batches, External Pay Grades, Routings, BOMs, and Production Cost Readers (**HRMS untouched**; HRMS is assumed to store/return base currency cleanly).
* **Core Architectural Rule:**
  1. **Database & Internal Engines = Always Base Currency** (`config('currency.base')`, `'USD'`, rate `1.0`). No service calculator (`SectionCalculationService`, `WorkCenterCalculationService`, `RoutingOperationCostCalculator`, `ProductionCostService`, `RoutingCostService`, `RoutingCostStatusService`) converts currencies during calculation.
  2. **DB Write Boundary = `convert_to_base()` ONCE:** Every UI form submission converts user-entered active-currency values into DB base before validation/persistence.
  3. **DB Read / UI Boundary = `convert_from_base()` / `format_currency()` ONCE:** Every UI display, Edit form pre-fill, UI AJAX resource feed, and JS calculator input converts DB base into active currency once.

### 2.1 Root Cause of Current Failures (e.g. Routing `Costs outdated` on Fresh Snapshot #1)
1. `ValidatesRoutingOperations` checks submitted operation figures against DB-base `SectionCalculationService::getSnapshotResourceCosts()` **before** `RoutingController` converts anything.
2. `RoutingController::store()` (L83–96) and `update()` (L203–216) then convert **only 3 of 7** money fields (`labor_cost_rate`, `machine_cost_rate`, `subcontract_cost_per_unit`), leaving `labor_cost_per_unit`, `machine_cost_per_unit`, `cost_per_unit`, and `setup_cost_per_run` unconverted — creating immediate `Costs outdated` alerts and 85× rate mismatches on `INR` (`rate = 85`).
3. Upstream `MachineController` never converts `operating_cost_per_hour` / `energy_rate_per_unit` on write (causing 85× bloat in snapshots), while `pay-grades/edit.blade.php` renders raw DB `hourly_rate` without `convert_from_base()` (causing 85× shrink every time an admin edits and saves a Pay Grade).

### 2.2 Marked Corrections Needed (File-by-File)

| Module | File & Location | Current Bug / Gap | Marked Correction | Double-Conversion Guard |
| :--- | :--- | :--- | :--- | :--- |
| **Pay Grades** | [`resources/views/modules/production/pay-grades/edit.blade.php`](../resources/views/modules/production/pay-grades/edit.blade.php#L50) | Pre-fills raw `$payGrade->hourly_rate` (base) without `convert_from_base()`; saving re-divides by FX rate (85× shrink); labels in `create`/`edit` omit `active_currency_symbol()`. | Pre-fill `convert_from_base((float) $payGrade->hourly_rate)` formatted with 2/4 decimals; append `active_currency_symbol()` to `create`/`edit` labels. | `ExternalWorkerPayGradeController::store()/update()` convert once on write; `index` uses `format_currency()`. |
| **Machines** | [`app/Domains/Production/Controllers/MachineController.php`](../app/Domains/Production/Controllers/MachineController.php#L153) (`store` & `update`) | Passes validated `energy_rate_per_unit` & `operating_cost_per_hour` raw into `MachineDTO::fromArray()` (no `convert_to_base()`). | Convert `energy_rate_per_unit` and `operating_cost_per_hour` via `convert_to_base()` on `store` and `update`. | `SectionCalculationService` reads DB base without converting. |
| **Machines UI** | [`resources/views/modules/production/machines/edit.blade.php`](../resources/views/modules/production/machines/edit.blade.php#L150-L155) | Pre-fills raw `energy_rate_per_unit` & `operating_cost_per_hour`; `create`/`edit`/`index` render linked `Asset::purchase_cost` with raw `number_format()`. | Pre-fill `energy_rate_per_unit` & `operating_cost_per_hour` via `convert_from_base()`; display `purchase_cost` via `format_currency()`. | Edit converts once on load, controller converts once on save. |
| **Work Centers (API)** | [`WorkCenterApiController.php`](../app/Domains/Production/Controllers/Api/WorkCenterApiController.php), [`WorkCenterResource.php`](../app/Domains/Production/Resources/Api/WorkCenterResource.php#L29-L30) | Web `WorkCenterController` + views are clean. API accepts `overhead_rate` unconverted and ignores `overhead_cost`; `WorkCenterResource` returns dropped/null `$this->cost_per_hour` & `$this->overhead_rate`. | Expose real base columns (`overhead_cost` and `latestCalculation` hourly/unit cost) on `WorkCenterResource`; align API write/DTO contract cleanly in DB base. | Keep API consistently DB base (or document explicitly) so internal consumers never mix units. |
| **WC Legacy Cost Readers** | [`ProductionCostService.php`](../app/Domains/Production/Services/ProductionCostService.php#L73), [`ProductionCostVarianceService.php`](../app/Domains/Production/Services/ProductionCostVarianceService.php#L37-L41), [`ProductionWipService.php`](../app/Domains/Production/Services/ProductionWipService.php#L312-L315), [`MrpEngineService.php`](../app/Domains/Production/Services/MrpEngineService.php#L219), [`ReworkService.php`](../app/Domains/Production/Services/ReworkService.php#L103-L104) | Fallbacks read dropped `production_work_centers.cost_per_hour` / `overhead_rate` columns (`null`/0) instead of `WorkCenterCalculation` / `overhead_cost`. | Flagged alongside currency audit: update fallbacks to read DB-base `latestCalculation->labor_cost_per_hour` & `overhead_cost` (kept 100% DB base). | Zero conversion in services; views already wrap with `format_currency()`. |
| **Section Resources Feed** | [`WorkCenterSectionController::resources()`](../app/Domains/Production/Controllers/WorkCenterSectionController.php#L73-L84) | Sends raw DB-base `SectionCalculationService::getSnapshotResourceCosts()` directly to Routing JS while Routing UI prefixes `{{ active_currency_symbol() }}`. | Keep `SectionCalculationService::getSnapshotResourceCosts()` strictly in DB base; convert money fields via `convert_from_base()` at the controller UI JSON boundary (`resources()`) for Routing Create/Edit JS display. | Keep `ValidatesRoutingOperations` & `RoutingOperationCostCalculator` reading DB-base `getSnapshotResourceCosts()` (not the converted UI feed). |
| **Routing Validation & Save** | [`StoreRoutingRequest.php`](../app/Domains/Production/Requests/StoreRoutingRequest.php), [`UpdateRoutingRequest.php`](../app/Domains/Production/Requests/UpdateRoutingRequest.php), [`RoutingController.php`](../app/Domains/Production/Controllers/RoutingController.php#L83-L96) | Partial conversion (`3 of 7` fields) happens **after** `ValidatesRoutingOperations`, failing validation or corrupting 4 of 7 op columns and triggering false `Costs outdated`. | Convert all 7 op money fields (`labor_cost_rate`, `machine_cost_rate`, `labor_cost_per_unit`, `machine_cost_per_unit`, `cost_per_unit`, `setup_cost_per_run`, `subcontract_cost_per_unit`) via `convert_to_base()` **before** validation; adjust `ValidatesRoutingOperations` tolerance for active-currency 4-decimal round-trip and persist exact DB-base calculator figures + server-built DB-base `resource_snapshot`. | **Crucial guard:** NEVER persist JS-supplied active-currency numbers into `resource_snapshot` JSON — always copy snapshot node from DB-base `getSnapshotResourceCosts()`, otherwise `RoutingCostStatusService::compare()` double-converts via `format_currency()`. |
| **Routing Edit & Duplicate** | [`routing/edit.blade.php`](../resources/views/modules/production/routing/edit.blade.php#L845-L857), [`RoutingService::buildCopyPayload()`](../app/Domains/Production/Services/RoutingService.php#L328-L425) | `edit.blade.php` converts `labor_cost_rate`, `machine_cost_rate`, `subcontract_cost_per_unit` with `convert_from_base()`, conflicting with raw base `snapshotResources`. Duplicate copy flow passes DB-base values to web Create form. | Align `edit.blade.php` cleanly with active-currency `resources()` feed (pre-fill `subcontract_cost_per_unit` and calculated figures in active currency once); convert `buildCopyPayload()` money fields with `convert_from_base()` at the web copy UI boundary while leaving API copy in DB base. | Re-saving Edit or Copy runs through the unified `convert_to_base()` write boundary once. |
| **Routing Scenario Modal** | [`resources/views/modules/production/routing/show.blade.php`](../resources/views/modules/production/routing/show.blade.php#L812-L1085) | Alpine `routeSimulator()` receives raw DB-base op/route costs (`cost_per_unit`, `labor_cost`, `machine_cost`, `subcontract_cost`, `primaryTotalCost`) and prefixes `active_currency_symbol()` without `convert_from_base()`. | Pass `convert_from_base()` values into `routeSimulator()` JS so simulated unit, batch, and savings figures display in active currency accurately. | Read-only display only; does not write to DB. |
| **Master Imports** | [`ProductionMasterImportService.php`](../app/Domains/Production/Services/ProductionMasterImportService.php#L115-L947) | `routingImportPayload()` imports `subcontract_cost_per_unit` raw without `convert_to_base()`; `machines` import writes non-existent `'hourly_cost'` raw without conversion. | Apply `convert_to_base()` on imported active-currency `subcontract_cost_per_unit` and machine `operating_cost_per_hour` (replacing legacy `'hourly_cost'`). | Imports calculate routing figures via DB-base `RoutingOperationCostCalculator` once after conversion. |
| **BOM Module** | `ProductionBomController`, `ProductionBomService`, [`ProductionCostService.php`](../app/Domains/Production/Services/ProductionCostService.php), [`bom/show.blade.php`](../resources/views/modules/production/bom/show.blade.php#L604-L994) | **No money bugs found in BOM itself.** BOM forms have no monetary inputs; `ProductionCostService` computes cleanly in DB base; `bom/show.blade.php` formats every figure once via `format_currency()`. | No BOM currency code changes required (fixing upstream Machines & Routing automatically fixes BOM labor/machine rollups). | Verified zero double conversion across `BOM -> Routing -> WC -> Section`. |

### 2.3 End-to-End Flow Verification Summary (In-Memory SQLite)
Executed synthetic end-to-end verification across `PayGrade ($1/hr = ₹85/hr) + Machine ($2/hr = ₹170/hr) + WC Overhead ($10/day = ₹850/day) + HRMS Employee ($2,400/mo base) -> Section Calc -> Routing (internal + external op) -> BOM (+2 × $4 RM)` under **both `INR (rate = 85)` and `USD (rate = 1)`**:
* **Current Unpatched INR Pipeline:** Pay Grade Edit drops DB rate `1.0000 -> 0.0118 USD` on resave; Machine saves `170 USD` (`85×` bloated); Routing stores mismatched `labor_rate=0.00036` vs `cost_per_unit=4.2959` and immediately reports `outdated = TRUE` on Snapshot `#1`.
* **Verified DB-Boundary Pipeline:**
  * **INR (`85.0`)** & **USD (`1.0`)** produce identical underlying DB base totals (`BOM total = 8.7029 USD` in both, diff = `0`; `Routing total = 5.0958 vs 5.0959 USD`, `0.0001` rounding delta eliminated cleanly when backend persists `$calc['figures']` after validation).
  * `RoutingCostStatusService::check()` returns **`outdated = false`** immediately after create, and when Machine operating cost doubles (`₹170/hr -> ₹340/hr`), it returns **`outdated = true`** with `"Machine cost changed (was ₹170/hr, now ₹340/hr)"` — formatted **once** with zero double conversion.

### 2.4 Review Check (2026-11-12) — Corrections to the table above
* **Routing Edit & Duplicate row — partly wrong.**
  * `RoutingService::buildCopyPayload()` is used **only by the API** (`RoutingApiController` copy), which stays in DB base.
  * The web *Duplicate Version* never uses it. It opens `routing/edit.blade.php` in copy mode, so it is covered by the Edit fix.
  * No web conversion of `buildCopyPayload()` is needed.
* **Routing Validation & Save row — approach changed.**
  * The table says: convert the 7 figures before validation and widen the tolerance. That is not reliable.
  * The form rounds each rate to 4 decimals in **INR** and then multiplies by the cycle minutes. The backend rounds in **USD**. After converting back, the two results can differ by up to `cycle × 0.00005 USD`. For a 100-minute cycle that is 0.005, which is 50× the `0.0001` tolerance.
  * **What was done instead:**
    * The web request checks the figures in the **same currency the form used** (the section resources are converted first), so the check matches the form calculator exactly.
    * The controller then converts once at the DB boundary. Only the user-entered subcontract cost goes through `convert_to_base()`.
    * All 7 figures and `resource_snapshot` are **recalculated on the server from the DB-base section snapshot**. No active-currency number and no round-trip drift reaches the DB.
* **Missed items found during the check** (all fixed unless marked *flagged*):
  1. **Pay Grade → WC link missing.** Changing a pay grade's `hourly_rate` did **not** queue the sections that use it. Machine, allocation and employee changes do queue their sections. The section snapshot kept the old labor cost, so routings never showed *Costs outdated* and Production Orders were never blocked.
  2. **Pay Grade Edit screen returned 500.** The view calls `$payGrade->activeAllocations()`, but that relation did not exist on `ExternalWorkerPayGrade`.
  3. **Pay Grade rounding.**
     * The inputs used `step="0.01"`. The browser blocks a pre-filled value such as `1.0030`, and every re-save rounds the rate.
     * The index, WC Show (5 places) and the section calculation modal showed the rate with 2 decimals, against the 4-decimal rule.
  4. **WC overhead pre-fill.** The Edit form pre-filled `overhead_cost` rounded to 2 decimals, with `step="0.01"`. The value drifted on each re-save.
  5. **Routing subcontract cost pre-fill.** The Edit form used 2 decimals and the input used `step="0.01"`. Same drift.
  6. **More legacy WC readers than the table lists:**
     * `ProductionCostVarianceService` L155–159;
     * `ReworkService` L207–208 and L259–260;
     * `quality/rework/show.blade.php`, which displayed the dropped `cost_per_hour` / `overhead_rate` columns.
  7. **WC API partial update.** A `PATCH` with only some fields reset `overhead_cost` to 0, because the DTO replaces every column. It also failed when `name` / `code` were not sent.
  8. **Routing import.** Imported operations had no `resource_snapshot` and no `work_center_calculation_id`.
  9. *Flagged, not changed.* `MrpEngineService` L222–224 reads the dropped `capacity_per_hour` / `efficiency_percentage` columns, so the capacity check never runs. This is not a currency issue, and the right formula needs a decision.
  10. *Flagged, not changed.* The machine import also writes `work_center_id`, which machines don't have. Machines belong to a section, so the value is silently dropped. Mapping imports to sections needs a decision.

### 2.5 Implementation Notes (🛠️ Applied)
* **Pay Grades:**
  * Edit pre-fills `format_decimal(convert_from_base(hourly_rate), 4)`.
  * Create and Edit labels show `(₹/hr)`; the inputs use `step="any"`.
  * The index, WC Show and the section modal use `format_currency(…, 4)`.
  * New relation `ExternalWorkerPayGrade::activeAllocations()`.
  * `ExternalWorkerPayGradeController::update()`: when the stored base rate changes, every section with an active allocation of that pay grade is queued with `CountdownCalculationEngine::triggerCountdown()`.
* **Machines:**
  * `MachineController::moneyToBase()` converts `energy_rate_per_unit` and `operating_cost_per_hour` once, on store and on update.
  * Edit pre-fills both through `convert_from_base()`.
  * `purchase_cost` on Create, Edit and Index uses `format_currency()`.
* **Work Center:**
  * New model helpers, both in DB base:
    * `calculatedLaborCostPerHour()` — from the latest calculation;
    * `calculatedOverheadCostPerHour()` — snapshot daily overhead ÷ unique operating hours, 0 when not calculated.
  * Used in `ProductionCostService`, `ProductionCostVarianceService` (both places), `ProductionWipService`, `MrpEngineService` (overhead), `ReworkService` (3 places) and the rework Show view.
  * The overhead Edit pre-fill uses 4 decimals with `step="any"`.
* **WC API** (DB base, noted in the code):
  * `WorkCenterResource` returns `currency` and `overhead_cost`, plus `capacity_per_hour` / `cost_per_hour` from the latest calculation.
  * `overhead_rate` is now the overhead per hour.
  * New `latest_calculation` block.
  * `efficiency_percentage` was removed, because the column does not exist.
  * The requests accept `overhead_cost`; `overhead_rate` is kept as an alias.
  * `update()` keeps the stored values for fields a partial PATCH does not send.
* **Section resources feed:**
  * `WorkCenterSectionController::resources()` converts the money keys with `RoutingOperationCostCalculator::resourcesToActiveCurrency()` (keys listed in `MONEY_RESOURCE_KEYS`) and adds `currency`.
  * `getSnapshotResourceCosts()` is unchanged and stays DB base.
* **Routing save:**
  * `ValidatesRoutingOperations::routingFiguresCurrency()` defaults to `base` (API and imports). `StoreRoutingRequest` / `UpdateRoutingRequest` set it to `active`, so their check uses the converted resources.
  * `RoutingController::store()` / `update()` call the new `RoutingService::operationsToBase()`, which:
    * converts the subcontract cost with `convert_to_base()`;
    * recalculates the 7 figures with the base calculator;
    * rebuilds `resource_snapshot` with the new `RoutingOperationCostCalculator::buildResourceSnapshot()` (DB base, same shape as the form's);
    * fills in the section, snapshot and WC calculation ids.
  * The snapshot posted by the JS is ignored, and the old 3-of-7 conversion is removed.
* **Routing Edit:** the subcontract cost pre-fill uses `format_decimal(…, 4)`; the input uses `step="any"` on Create and Edit.
* **Routing Show simulator:** `routeSimulator()` receives `convert_from_base()` values for `cost_per_unit`, `labor_cost`, `machine_cost`, `subcontract_cost` and the baseline total.
* **Master import:**
  * Machines write `operating_cost_per_hour = convert_to_base(hourly_cost)` instead of the non-existent `hourly_cost` column.
  * Routings convert `subcontract_cost_per_unit` once before the base calculator runs, and save `resource_snapshot` and `work_center_calculation_id`.
* **BOM:** no changes, as the audit found.
* **Tests:** new `tests/Feature/Production/ProductionCurrencyBoundaryTest.php` (INR, rate 85) covers:
  * the feed is converted while the snapshot stays in base;
  * an INR routing save stores base figures and a base snapshot, and is not *outdated*;
  * after a machine cost change, the message reads *Machine cost changed ₹2,550 → ₹5,100* (converted once);
  * an external subcontract cost of ₹170 is stored as 2 USD;
  * the Show Op Setup Cost column and the simulator values in INR;
  * the Create column and colspan;
  * Machine store and the Edit pre-fill;
  * Pay Grade Edit pre-fill and a re-save with no loss;
  * a Pay Grade rate change queues its section;
  * the WC fallback helpers and the API resource.

### 2.6 Open Points for the User
* **Existing data is not repaired automatically.**
  * Machines saved in INR before this fix are stored 85× too high.
  * Pay grades re-saved through Edit are 85× too low.
  * Routing operations saved in INR have mixed figures.
  * These need to be re-entered or saved again, or corrected with a one-time data script (your call).
* **Base stored with 4 decimals.** Money columns are `decimal(…, 4)` in base currency. An INR value that doesn't divide exactly by the rate comes back slightly off: a ₹100 subcontract cost is stored as 1.1765 USD and shown as ₹100.0025. Storing 6–8 decimals in base would remove this, but needs a decision and a migration.
* *Flagged items 9 and 10* in §2.4 need decisions.

### 2.7 Second Currency Audit — BOM, Routing, Work Center (2026-11-13)
Every page that reads or writes these modules was checked: forms, Show, list, modals, JS feeds, imports and the API.
The rule being checked: **convert once when saving (active → base), once when displaying (base → active), never inside engines.** Times, quantities, capacities, %, priority, sequence, headcount and days must never be converted.

**A. Checked and correct — no change needed**
| Area | Money fields | Write (→ base) | Read (→ active) | Non-money fields left alone |
|---|---|---|---|---|
| Work Center create/edit | `overhead_cost` | `WorkCenterController` L60 / L193, once | Edit pre-fill `convert_from_base`, 4 dp | countdown minutes, status, codes |
| WC Show / Index / calculation modals | all snapshot costs (labor, machine, overhead, per hour, per unit, daily) | — (engine, base) | `format_currency` / `format_cost_per_unit` | every capacity, u/h, units, consumption, % uses `number_format` (no conversion) ✔ |
| Machines | `energy_rate_per_unit`, `operating_cost_per_hour` | `MachineController::moneyToBase()`, once | Edit pre-fill `convert_from_base` | `capacity`, `consumption_per_hour` ✔ |
| Pay Grades | `hourly_rate` | `ExternalWorkerPayGradeController` L78 / L122, once | Edit pre-fill + index + WC Show + modal | — |
| Internal worker allocation | none — `rate` is the **allocation %** (default 100), not money | not converted ✔ | not shown as money ✔ | `headcount`, `rate` ✔ |
| Section resources feed (routing form only) | 10 money keys in `MONEY_RESOURCE_KEYS` — matches the payload exactly | — | `resourcesToActiveCurrency()` | `capacity`, `capacity_per_hour`, `speed_minutes_per_unit`, `section_operating_hours` ✔ |
| Routing create/edit | 7 figures + `subcontract_cost_per_unit` | `operationsToBase()` (recalculated from base snapshot) | Edit pre-fill rates + subcontract `convert_from_base`; validation-error `old()` re-shows what was typed (no conversion) ✔ | times, yield %, priority, sequence, transfer batch qty, lag minutes, lead/buffer days ✔ |
| Routing Show / Index | costs, simulator, *Costs outdated* messages | — | `format_currency` / simulator `convert_from_base` | yield %, transfer qty ✔ |
| BOM create/edit | no money fields on the form | — | — | quantities, scrap % ✔ |
| BOM Show | material unit/net/gross cost, labor, machine, total | — (Inventory stores `unit_cost` in base: `ProductService` L40-42) | `format_currency` | base qty, quantities, scrap % ✔ |
| BOM / routing cost services | `ProductionCostService` reads base only, no conversion inside | — | — | — |
| Master import (web) | machine hourly cost, routing subcontract cost | `convert_to_base` once | — | — |
| API (incl. API import) | all | stays base — API routes have no session, so `convert_*` uses rate 1 ✔ | stays base | — |
| Background calculation | — | `ProcessCalculationCountdowns` (console, no session) — the engines call no `convert_*` ✔ | — | — |

No double conversion found in any of these paths.

**B. Rule decided by the user (2026-11-13): every money value read from the database is base currency.**
* Production treats any money value it reads from the DB as base — its own tables **and** other modules' tables — and converts it only at the UI boundary. That is the consistent behaviour and it is correct.
* If another module saves an un-converted amount, that is **that module's bug**, fixed there, not worked around in Production.
* Applied to the two cases found in this audit:
  1. **HRMS `current_salary`** (read directly through the HRMS `Employee` model and `monthlyCtc()`, no HRMS service involved): Production reads it as base. ✔ **Not a Production bug.** HRMS saves the salary as typed, without `convert_to_base` — **HRMS bug, recorded for HRMS, out of scope here.** Read points, for reference: `SectionCalculationService` L633 / L812 / L1437, `WorkCenterCalculationService` L361 (also `MaintenanceWorkOrderService` L1125–1161).
  2. **Fixed-asset `purchase_cost`** on Machine Create / Edit / Index: shown with `format_currency()` (read as base). ✔ Kept as done in the previous round. (A short-lived revert to raw `number_format` on 2026-11-13 was undone under this rule.) Accounting saving it as typed is an **Accounting bug, out of scope here.**
* Result: **no open currency defect in BOM, Routing or Work Center.**

---

## Point 3 — Full Audit: BOM, Work Center, Routing (2026-11-13)

* **Status:** 🔎 **Findings recorded for discussion.** No application code was changed by this audit.
* **How it was checked:**
  1. Every decision and task in `work_center_*`, `routing_*` and `bom_*` (decisions + implementation tasks) was compared with the code.
  2. Dropped columns: every column dropped by the migrations was searched for in `app/` and `resources/`, then checked against the **real MySQL schema** (read-only probe).
  3. A temporary end-to-end smoke test (`tests/Feature/Production/ZzAuditSmokeTest.php`, deleted after the audit) built WC → section (shift 06:00–14:00, 30 min break) → machine (60 u/h, 30/hr) → 2 employees (one in a manual batch, 20 u/h) → section and WC snapshots → routing posted through the web form → submit → approve → BOM → submit → approve. It then opened every BOM / Routing / WC page, edited a machine, and ran the delete flows.
  4. The full Production test suite was run.

### 3.1 What works (verified by the smoke run, figures checked by hand)
| Area | Result |
|---|---|
| WC calculation | Daily capacity 640 = machine 60 × 8 h (480) + batch 20 × 8 h (160) ✔ (breaks counted, WC-02). Cost per unit 3.1042 = (labor 1,000 + 666.67 + machine 240 + overhead 80) ÷ 640 ✔ (WC-17, WC-29). |
| WC pages | Index, create, show (no snapshot / with snapshot), edit, calculation details, exact WC and section snapshot modals (WC-IMPL-20): all 200. |
| Machines, pay grades, section feeds | Index / create / edit and the JSON feeds: all 200. |
| Routing form save | Machine op: labor 2.0833/min = 1,000/day ÷ 8 h ÷ 1 machine (RT-14) ✔; machine 0.5/min ✔; Cost / Unit 4.0789 = (0.5 + 1) × 2.5833 × 100 ÷ 95 ✔; setup / run 51.666 ✔. Batch op: 4.1667 = 3 min × 1.3889 ✔ (RT-15). Snapshot #1 stored ✔. |
| Routing lifecycle | Draft → submit → **active**; show / edit / operations JSON: all 200. |
| BOM | Master row built from 2 allocations of the same material: qty 3, gross 3.2 (2 × 1.10 + 1) ✔ (BM-04). Index / create / show / edit / check-child: 200. Submit → approve ✔ (routing gate BM-IMPL-02). |
| Outdated chain | Machine edit queues the section (countdown armed) → after recalculation the routing reports *Costs outdated* ✔ → BOM show still 200. |
| Tests for the new design | All pass: `RoutingCalculateOnceTest`, `RoutingFiguresTest`, `RoutingRetireAndCopyTest`, `RoutingRemainingTasksTest`, `RoutingTest`, `BomOverhaulTest`, `BomFixesTest`, `BomAlternativeComponentsTest`, `BomRoutingInversionTest`, `WorkCenterCountdownScopeTest`, `WorkCenterSnapshotViewerTest`, `WorkCenterFigureFixesTest`, `WorkCenterDisplayPartCTest`, `WorkCenterRoutingIntegrationTest`, `WorkCenterSectionDeletionTest`, `WorkCenterShiftValidationTest`, `ProductionCurrencyBoundaryTest`, `ProductionOrderCreateRevampTest`. |

### 3.2 Flow breaks found (code)
| # | Severity | Where | What breaks | Proof |
|---|---|---|---|---|
| F1 | 🔴 High | `ProductionOrderService::createFromPlan()` L171 | `$bom->items()->whereNotNull('child_bom_id')` queries `production_bom_items.child_bom_id`, which was dropped on 2026-11-05 (it lives on the allocations now). **On MySQL, converting a Production Plan into an Order crashes** whenever the BOM has no sub-BOM in its allocations, because the `||` then runs the bad query. | MySQL probe: `Unknown column 'child_bom_id'`. |
| F2 | 🔴 High (testing gap) | All tests (SQLite) | SQLite treats an unknown double-quoted column as a **string literal**, so queries on dropped columns **pass in tests and crash on MySQL**. F1 passes in the smoke test for this reason. The current suite cannot catch dropped-column bugs. | Same query: SQLite OK, MySQL error. |
| F3 | 🟠 Medium | `RoutingController::destroy()` / `RoutingRepository::delete()` | A **draft routing used by a BOM can be deleted** (soft delete, no BOM check). The BOM then points to a deleted routing: show, edit and index still open, and **submit still succeeds** (status → pending approval). Only approve stops it (*"The linked routing no longer exists"*). RT-26 lists the linked BOMs for Retire / Cancel only, not for Delete. | Smoke run: delete 302, BOM submit 302 → `pending_approval`. |
| F4 | 🟠 Medium (known gap) | `SectionManualProductionController::destroy()` | A **manual batch used by a routing operation is deleted** without a warning. The routing still shows and edits, but its operation loses its resource. Already recorded as the WC-47 gap → WC-FUT-02; still open. | Smoke run: delete 302, batch soft-deleted. |
| F5 | 🟡 Low (agreed, not done) | `WorkCenterSectionController::updateManualProduction()` + `WorkCenterSectionService::updateManualProduction()`; route `work-center-sections.update-manual-production` | Writes the dropped columns `supports_manual_production` / `manual_capacity_per_hour`, so it returns 500 if called. No screen calls it. This is **WC-IMPL-06** (🟢 Agreed, not implemented). | Code read. |
| F6 | 🟡 Low | `Route::resource('work-center-sections')` (`Routes/web.php` L131) | `index` / `show` → *view not found*; `create` / `edit` → *method does not exist* (500). Sections are managed inside WC Show, so no screen links here, but typed URLs fail. `ProductionStabilizationRegressionTest` fails on this. | Smoke run: 4 × 500. |
| F7 | 🟡 Low | `App\Exports\MachineExport` L21–22 | Filtering the machine export by Work Center runs `where('work_center_id')` on `production_machines` (column dropped 2026-10-21), so it crashes on MySQL. It should go through the section, as `MachineRepository` does. | Code read + dropped-column list. |
| F8 | 🟡 Low (flagged before) | `ProductionMasterImportService` L149 | Machine import still writes `work_center_id` (machines belong to a section). The value is silently dropped (item 10 of §2.4). | Code read. |
| F9 | ⚪ Dead code | `RoutingOperation::bomItems()` (dropped `production_bom_items.routing_operation_id`); `ProductionBomItemDTO` (dropped fields); `WorkCenterCalculationService::calculateCapacity()` / `calculateLaborCost()`, `WorkCenterActivityLogger::logResourceChange()` | No callers. The WC part is **WC-IMPL-10** (🟢 Agreed, not done). | grep. |

### 3.3 Code vs decision mismatches in deferred tracks (these screens are outside BOM / Routing / WC, but they read their data)
| # | Where | Mismatch | Belongs to |
|---|---|---|---|
| M1 | `SchedulingService::calculateCapacity()` L1164–1208, `WorkCenterDashboardController` L70 / L101 | Available time = **sum** of shifts **minus breaks** × active machines × `efficiency_percentage`. The decisions say union of shifts, with breaks counted (WC-02 / WC-03). The efficiency column does not exist, so it is always 100 %. | RT-32 |
| M2 | `MrpEngineService` L222–224, `PlanningValidationService` L87–91 | Both read the non-existent `production_work_centers.capacity_per_hour` / `efficiency_percentage`, so the capacity check never runs (item 9 of §2.4; the planning-validation copy was not listed before). | RT-32 / BM-DEF-03 |
| M3 | `ProductionOrderOperation` L329 / L359, `MesController` L917 | Consumption targets use the master `quantity` (primary net) instead of `gross_quantity`, so scrap is ignored (BM-IMPL-04 says readers use gross). | BM-DEF-03 / RT-32 |
| M4 | `CapacityLevelingService`, `CapacityPlanningService`, `ProductionReadinessService`, `ProductionScheduleScenarioService`, `SchedulePreReleaseValidationService`, `PlanningExceptionService` | Still read the old alternate-machines table (RT-11 says alternative operations replace it). | RT-DEF-01 (known) |
| M5 | `ProductionBomController::show()` L193–194 → `ProductionCostService::calculateRoutingCost()` | Computes the BOM routing cost with the **old formula**: (setup + run) × base qty × yield, applied to the per-run setup too, with a raw machine `operating_cost_per_hour` fallback and a **live** section calculation on every page load. The result is **not shown** on the BOM page (only the material cost is), so nothing is wrong on screen today. The work is wasted, though, and the formula goes against RT-16 / RT-19. | BM-DEF-02 (known) |

### 3.4 Mismatches inside the documents
| # | Document | Mismatch |
|---|---|---|
| D1 | `work_center_decisions.md` Review table | Rows WC-14, WC-22, WC-24 and WC-46 are garbled (*"WC-14 \| Average \| WC-14 … WC-48 \| — \| ⏳ Pending review \| peak…"*): a pasted placeholder row is mixed into the text. |
| D2 | `work_center_decisions.md` WC-46 and `work_center_implementation_tasks.md` WC-ISSUE-11 / 12 / WC-FINAL-01 | Still marked *"⏸️ Decide later"*. Machine cost conversion was applied in §2.5, and on 2026-11-13 the user decided that DB money is base (§2.7 B; HRMS salary = HRMS bug). The statuses need updating. |
| D3 | `routing_decisions.md` summary under the Review table | Says *"RT-IMPL-03 (proposed)"* is open, but the register shows RT-IMPL-03 🛠️ Applied. |
| D4 | `routing_implementation_tasks.md` RT-IMPL-13 note | Says `ProductionMasterImportService.php` has a parse error. It no longer does (`php -l` is clean). |
| D5 | `work_center_decisions.md` WC-47 | Says the batch-delete gap is recorded under WC-FUT-02. That is still true, but no task register row lists it, so it is easy to miss (see F4). |
| D6 | `bom_decisions.md` BM-30 | Says *"Removed-design tests deleted, full run at the end (passing = Applied)"*. The full run is **180 failed / 723 passed** (see 3.5), so this rule is not met yet. |

### 3.5 Production test suite: 180 failed, 723 passed
Nearly all failures are **tests written for the old design**, not app bugs. By cause:
* **Old shift design** (shifts attached through a pivot, `->shifts()->attach/sync`, table `production_section_shifts`, `hourly_labor_rate` on shifts): `WorkCenterSectionTest` (30), `EnterpriseSchedulingTest` (14), `SectionBasedShiftArchitectureTest` (9), `SectionCalculationTest` (14), `WorkCenterCalculationTest` (17, also old snapshot keys such as `windows` and `has_missing_rates`), `SectionShiftRedesignAndWorkerAllocationTest`, `WorkCenterTest`, `WorkCenterDashboardDetailTest`, `ExternalWorkerUiTest`.
* **Machines on a Work Center** (dropped `work_center_id`): *"Selected machine … does not belong to Work Center [#1]"* in `ScheduleDispatchAndPreReleaseTest`, `SchedulePostGenerationTest`, `ScheduleScenarioTest`, `OperationIdVsSequenceMultiLevelBomTest`; also `MachineTest`, `MachineAssetLinkingTest`, `FilteredExportsTest`.
* **Routing ↔ BOM material modes** (removed `Routing::MODE_*`): `RoutingMaterialAllocationTest` (9).
* **Auto-retire on approval** (RT-02 says no auto-retire): `BomAndRoutingLifecycleDatesTest` (6).
* **WC `capacity_per_hour` column**: `TableManufacturingSeederTest`, `TableManufacturingE2EValidationTest` (the seeder writes the dropped column).
* **Others, downstream** (orders, WIP, subcontract, MES; mostly fixtures without routing operations or stock): `MultiModelPhase1–4`, `Subcontract*`, `CrossAssemblyQueueThresholdTest`, `RoutingParallelDependencyTest`, `ProductionTimelineIntegrationTest`, etc.
* **Real issue among them:** `ProductionStabilizationRegressionTest` (that is F6).
* Rule BM-30: decide per group whether to **delete** the tests (removed design) or **rewrite** them (behaviour still valid on the new design).

### 3.6 Open items still in the registers (unchanged by this audit)
* 🟢 Agreed, not implemented: **WC-IMPL-06** (F5), **WC-IMPL-10** (F9), **WC-FINAL-01**, **RT-IMPL-19** (do last), **BM-IMPL-20** (do last; `under_revision` is still referenced in 4 places and the master scrap column is still present).
* 🟡 Proposed: **WC-IMPL-14** (countdown robustness), **RT-IMPL-21** (fill saved figures for older routings; until then, older routings show *"Costs not calculated"*).
* ⏸️ Decide later: the shift group **WC-ISSUE-01 … 10**, and **RT-ISSUE-01**.
* ⏭️ Deferred: **RT-DEF-01**, RT-31 / 32 / 33, **BM-DEF-01 / 02 / 03** (M1–M5 belong here).

### 3.7 Suggested order for discussion (nothing done)
1. **F1**: a one-line fix: check only `operationMaterials()` for `child_bom_id`.
2. **F2**: add a guard test that compares every queried column with the schema, or run a small MySQL smoke job before release.
3. **F3**: block deleting a routing that any BOM uses and list those BOMs (as RT-26 does), or let the BOM side handle it as RT-05 does.
4. **F4**: block deleting a batch used by a routing operation (deactivate it instead), as is already done for sections and machines (WC-47).
5. **F6 / F7 / F5 / F9**: small cleanups (limit the resource routes to `store` / `update` / `destroy`, fix the export filter, then do WC-IMPL-06 / 10).
6. **D1–D6**: document status corrections.
7. **3.5**: test clean-up per BM-30.
