# Section Calculation & Audit UI — Issues & Improvements Task List

This document tracks all issues, discrepancies, and enhancement requests identified during testing.
Each entry documents the **Issue**, the **Reason / Root Cause**, and the **Agreed Solution**.
All items will be queued here and implemented together in a clean batch once review is complete.

---

## 🛠️ Temporary Test Overrides (Revert After Audit)

| File | Functions / Lines | Original Behavior | Temporary Audit Behavior | Reason |
| :--- | :--- | :--- | :--- | :--- |
| `app/helpers.php` | `convert_from_base()`, `convert_to_base()`, `format_currency()`, `active_currency_symbol()` | Multiplied base USD amounts by `active_currency_rate()` (85 for INR) and formatted as Indian Rupees (`₹`). | **Conversion disabled**: Returns raw database values (1:1 rate) formatted as base currency (`$`). | Allows direct 1-to-1 visual audit against raw values stored in database columns without conversion noise. |
| `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php` | Lines 22–26 | Executed `calculateAndSave($section, 'manual')` on the fly whenever `$section->latestCalculation` was null. | **Auto-calculation blocked**: Leaves `$secCalc = null`, allowing UI to render zeros/nulls without writing DB records. | Prevents GET page view from firing unwanted calculations and audit logs. |
| `app/Domains/Production/Controllers/WorkCenterController.php` | `calculationDetails()` (lines 344–350) | Executed `calculateAndSave($workCenter)` if `$workCenter->latestCalculation` was null. | **Auto-calculation blocked**: Returns null snapshot without writing DB records. | Keeps GET endpoints pure and read-only. |
| `app/Domains/Production/Services/WorkCenterSectionService.php` | `create()`, `update()`, `recalculate()`, `updateManualProduction()`, `delete()` | Automatically fired `$this->calculationService->calculateAndSave()` and WorkCenter rollup calculation on every create, edit, manual production update, and delete. | **Auto-calculations commented out**: Methods create/update/delete records without triggering automatic recalculations. | Ensures only the manual "Recalculate" button triggers calculations during audit. |
| `app/Domains/Production/Services/WorkCenterService.php` | `recalculate()` (lines 71–75) | Fired `calculateAndSave($workCenter)`. | **Commented out**: Prevents indirect recalculation triggers. | Ensures only explicit "Recalculate" button runs calculations. |

---

## Summary Register

| ID | Title / Area | Status | Impacted Files |
| :--- | :--- | :--- | :--- |
| **ISSUE-01** | Rename "Tariff Rate" to "Energy Rate" in Modal & Machine Views | **Applied** | `section-calculation-modal.blade.php`, `machines/create.blade.php`, `machines/edit.blade.php` |
| **ISSUE-02** | Calculation Lifecycle, Debounce Countdown Engine & Mutating Triggers Architecture | **Applied & Verified** | `CountdownCalculationEngine.php`, `WorkCenterSectionService.php`, `MachineService.php`, `show.blade.php`, `routes/console.php` |
| **ISSUE-03** | Section Details Modal (Machines Tab): Total Operating Cost Shows $0.00/hr When Uncalculated While Capacity Shows Sum | **Applied** | `show.blade.php` |
| **ISSUE-04** | Main Section Row & Capacity Popover: Remove Ad-hoc Database Fallbacks — Strictly Source from Calculation Table | **Applied** | `show.blade.php` |
| **ISSUE-05** | Section Details Modal (Manual Batches Tab): Missing Table Footer / Total Row for Target Rate & Allocated Workforce | **Applied** | `show.blade.php` |
| **ISSUE-06** | Section Details Modal (Cost & Capacity Tab & Top 2 KPIs): Uncalculated Warning & Remove Fallbacks | **Applied** | `show.blade.php` |
| **ISSUE-07** | Section Details Modal: Shifts & Schedule Missing Footer Total & Operating Window KPI Fallback | **Applied** | `show.blade.php` |
| **ISSUE-08** | WorkCenterCalculationService: Fix `operating_hours` typo to `operating_timeline` for Section Shifts Extraction | **Applied** | `WorkCenterCalculationService.php` |
| **ISSUE-09** | Main Section Row: Daily Operating Cost Column Missing Matching Hover Breakdown Popover | **Applied** | `show.blade.php` |
| **ISSUE-10** | Manual Batch Output Dilution (Active Speed Omitted to Prevent Redundancy & Confusion) | **Applied** | `show.blade.php`, popovers & modals |
| **ISSUE-11** | Canonical Keys & UI Consistency (Terminology: Hourly Capacity; Reconciliation Tooltip) | **Applied** | `section-calculation-modal.blade.php`, `SectionCalculationService.php` |
| **ISSUE-12** | Section Calculation Modal: Manual Batches Tab Internal & External Workforce Display | **Applied** | `section-calculation-modal.blade.php` |
| **ISSUE-13** | Section Calculation Modal: External HRMS Shift Resolution & Warning Badge | **Applied** | `section-calculation-modal.blade.php`, `SectionCalculationService.php` |
| **ISSUE-14** | Format Time/Hours Consistently Across Modals & Views via `format_hours()` (`16.30 hrs` vs raw `.5`) | **Applied** | `section-calculation-modal.blade.php`, `show.blade.php`, `SectionCalculationService.php`, `helpers.php` |
| **ISSUE-15** | Section Calculation Modal: Timeline Intervals Not Generated for Unstaffed / Machine-Only Sections | **Applied** | `SectionCalculationService.php` |
| **ISSUE-16** | Table Column Optimization for Popovers & Cost/Capacity Tab (Dedicated Columns vs JSON) | **Applied** | Migration, `WorkCenterSectionCalculation.php`, `SectionCalculationService.php`, `show.blade.php` |
| **ISSUE-17** | Section Details & Calculation Modals: Add Explanatory Tooltip & Formula for Average Hourly Manual Capacity | **Applied** | `show.blade.php`, `section-calculation-modal.blade.php` |
| **ISSUE-18** | Work Center Level Calculation Engine Rework (Average Capacity / hr, Multi-WC Direct Staff Allocation, Flat Overhead) | **Applied** | `WorkCenterCalculationService.php`, `show.blade.php`, `WorkCenterCalculation.php`, Migration |
| **ISSUE-19** | Section Calculation Modal (Audit Metrics Tab): Add Explanatory Info Popovers `(i)` for All 52 Audit Metrics (Source / Formula in Plain Language) | **Applied** | `section-calculation-modal.blade.php`, `show.blade.php` |
| **ISSUE-20** | Work Center Hero Panel (3-Column Layout): Redesign to Display Inline Section Capacity/Cost Contributions & Direct Personnel Rates (Zero-Hover UI) | **Applied** | `show.blade.php` |
| **ISSUE-21** | Work Center Calculation Modal (Timeline Tab): 24-Hour Visual Coverage Bar & Shift x Section Production & Cost Matrix | **Applied** | `show.blade.php` |
| **ISSUE-22** | Work Center Calculation Modal (Contributing Sections Tab): Card Hierarchy Reorder & Grand Total Cost Reconciliation | **Applied** | `show.blade.php` |
| **ISSUE-23** | Work Center Calculation Modal (Direct Personnel & Overhead Tab): Reorder Hierarchy, Burden Summary & Overhead Breakdown | **Applied** | `show.blade.php` |
| **ISSUE-24** | Work Center Calculation Modal: Replace Raw Snapshot JSON with 47-Metric Work Center Audit Table & Complete Raw JSON Removal | **Applied** | `show.blade.php` |
| **ISSUE-25** | Work Center Calculation Modal: 3x2 KPI Header Grid (Combined Output, Unit Cost, 24/7 Hours) | **Applied** | `show.blade.php` |
| **ISSUE-26** | Work Center Activity & Audit Logs Table Rework (Granular Per-Snapshot Schema, Explicit `snapshot_id`, Simplified Descriptions & Direct Historical Drill-Down) | **Applied** | Migration, `WorkCenterActivityLog.php`, `WorkCenterActivityLogger.php`, `show.blade.php` |

> **Live Work Center URL:** `http://127.0.0.1:8000/production/work-centers/1`

---

## Detailed Issue Tracking

### ISSUE-01: Ambiguous "Tariff Rate" Label in Section Modal & Machine Views
* **Date Logged:** 2026-10-27
* **Status:** **Applied**
* **Reported By:** User
* **Area:** Machine Operating Expense & Section Breakdown Modal

#### 1. Issue Description
In the Section Calculation Breakdown Modal (`Machines & Energy` tab), the table header displays **"Tariff Rate"**. Similarly, the machine create and edit views refer to "Tariff cost per unit". The user noted that the database table `production_machines` has no column named `tariff` or `tariff_rate`.

#### 2. Reason / Root Cause
* The actual database column in `production_machines` is **`energy_rate_per_unit`** (`decimal(10,4)`).
* "Tariff" is only colloquial for electricity utility pricing, but machines can run on diesel, petrol, or other energy sources where the cost is "Fuel price per liter", not a tariff. Using "Tariff Rate" causes confusion with non-existent database columns.

#### 3. Agreed Solution
1. In `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php`:
   - Change table header `<th style="width: 12%" class="text-end">Tariff Rate</th>` to `<th style="width: 12%" class="text-end">Energy Rate</th>`.
2. In `resources/views/modules/production/machines/create.blade.php` & `edit.blade.php`:
   - Align helper labels and dynamic text to "Energy rate per unit" instead of "Tariff cost per unit".
3. In `docs/calculation_rework_decisions.md`:
   - Update references from tariff to `energy_rate_per_unit`.

#### 4. Files to Modify (During Batch)
* `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php`
* `resources/views/modules/production/machines/create.blade.php`
* `resources/views/modules/production/machines/edit.blade.php`

---

### ISSUE-02: Calculation Lifecycle, Debounce Countdown Engine & Mutating Triggers Architecture
* **Date Logged:** 2026-10-27 (Architecture finalized & applied: 2026-10-31)
* **Status:** **Applied & Verified**
* **Reported By:** User
* **Area:** Calculation Lifecycle, Event Triggers, Debounce Engine & View Rendering (`CountdownCalculationEngine.php`, `WorkCenterSectionService.php`, `WorkCenterService.php`, `ExternalWorkerAllocationController.php`, `WorkCenterEmployeeController.php`, `MachineController.php`, `show.blade.php`, `routes/console.php`)

#### 1. Core Lifecycle Rules & Behavioral Invariants
1. **Work Center Creation (`WorkCenterService::create`)**:
   - Creating a Work Center performs zero calculations, generates zero snapshots, and writes zero calculation audit logs.
2. **Page Load / View Rendering (`GET /production/work-centers/{id}`)**:
   - HTTP GET requests remain strictly read-only.
   - If no snapshot has been calculated yet, the UI displays the amber `Calculation Pending` badge / empty state. No database mutation or calculation is ever triggered on page load.
3. **Section Creation (`WorkCenterSectionService::create`)**:
   - When a Section is created, the debounce countdown timer is armed for that Work Center (`triggerCountdown($workCenter, $sectionId)`).
   - Sets `calculation_countdown_end_at = now() + duration_minutes` and queues the Section ID in `pending_calculation_section_ids`.
   - The page displays the Active Countdown Banner with live ticking seconds.
4. **Subsequent Asset Changes (Section Edit, Machine Assigned/Edited, Batch Created/Updated, Workforce Allocated, Shifts Changed)**:
   - All subsequent mutating events append the affected Section ID to `pending_calculation_section_ids` **without resetting the timer**.
   - Zero immediate calculations are fired during asset edits, preventing multi-edit audit log spam.
5. **Debounce Configuration Lockout while Timer is Actively Ticking**:
   - While `isCountdownActive() === true`, the Debounce duration button is disabled on the UI and locked out in the modal.
   - On the backend, `CountdownCalculationEngine::updateCountdownDuration()` throws `InvalidArgumentException` if a countdown timer is actively running, preventing mid-countdown changes.
6. **Timer Expiration & Background Execution**:
   - When the countdown timer completes (via the browser JS ticker, page reload, or the cron schedule `php artisan production:process-calculation-countdowns`):
     * All pending queued sections are calculated in a single unified batch (`batch_id`).
     * The parent Work Center rollup calculation is executed and recorded with `trigger_type = 'countdown_timer'`.
     * Countdown state resets: `calculation_countdown_end_at = null` and `pending_calculation_section_ids = []`.
7. **Immediate Calculation Guarantee**:
   - The **ONLY** time an immediate calculation is executed is when an authorized user explicitly clicks:
     * `[⚡ Recalculate]` button in the Work Center header, OR
     * `[⚡ Recalculate Now]` button on the Active Countdown Banner.
   - These fire `recalculateNow($workCenter, 'manual')`, immediately processing all pending sections and bypassing any remaining wait time.

#### 2. Comprehensive Audit of Create, Edit & Save Buttons
A full audit of all controllers and forms confirmed that **zero Save, Create, or Edit buttons trigger immediate calculations**:
* `WorkCenterController::store()` / `update()` &rarr; Saves Work Center record; does NOT fire calculation.
* `WorkCenterSectionController::store()` / `update()` / `destroy()` &rarr; Saves Section record; previously had immediate calculation commented out. Will now cleanly arm/append to the countdown timer.
* `SectionManualProductionController::store()` / `update()` / `toggleActive()` / `destroy()` &rarr; Saves batch record; calls `countdownEngine->onSectionModified()`.
* `ExternalWorkerAllocationController::store()` / `updateQuantity()` / `toggleStatus()` / `destroy()` &rarr; Saves contractor allocation; does NOT fire calculation. Will now trigger countdown.
* `WorkCenterEmployeeController::store()` / `toggleStatus()` / `destroy()` &rarr; Saves employee assignment; does NOT fire calculation. Will now trigger countdown.
* `MachineController::store()` / `update()` / `destroy()` &rarr; Saves machine record; does NOT fire calculation. Will now trigger countdown if machine belongs to a section.
* **Conclusion**: ONLY explicit `[⚡ Recalculate]` and `[⚡ Recalculate Now]` buttons execute immediate synchronous calculations.

#### 3. Identified Gaps & Implementation Blueprint
1. **JavaScript Ticker HTML ID Mismatch (`show.blade.php`)**:
   - Element has `id="countdownTimerDisplay"` (camelCase at line 465), while JS ticker was selecting `$('#countdown-timer-display')` (kebab-case at line 7068). Aligning both to `countdownTimerDisplay`.
2. **Missing `onSectionModified()` Method (`CountdownCalculationEngine.php`)**:
   - Add `onSectionModified(WorkCenterSection $section, string $event, ?int $entityId = null)` helper method to `CountdownCalculationEngine` so calls from `SectionManualProductionController` successfully queue the section and arm the countdown instead of silently catching an undefined method error.
3. **Re-Enable Engine Logic & Wire Services**:
   - In `CountdownCalculationEngine.php`: Uncomment `isCountdownActive()` and `triggerCountdown()`.
   - In `WorkCenterSectionService.php`: Call `$this->countdownEngine->triggerCountdown($workCenter, $sectionId)` in `create()`, `update()`, `updateManualProduction()`, and `delete()`.
   - In `WorkCenterService.php`: Call countdown engine in `update()`.
   - In `ExternalWorkerAllocationService.php` & `WorkCenterEmployeeService.php`: Trigger countdown on the parent Work Center when allocations or assignments are updated.
   - In `MachineService.php`: Trigger countdown when a machine assigned to a section is updated.
4. **Console Schedule Registration (`routes/console.php`)**:
   - Register `Schedule::command('production:process-calculation-countdowns')->everyMinute()->withoutOverlapping()` so expired countdowns across all tenants are automatically processed by Laravel's background worker.
5. **Page Reload Auto-Process Safety (`WorkCenterController::show`)**:
   - If a user opens/reloads the page after the countdown end time has elapsed (`now() >= calculation_countdown_end_at`), automatically execute the pending calculation so the user instantly sees fresh numbers even if the background cron has a slight delay.
6. **Frontend Auto-Recalculate Form (`show.blade.php`)**:
   - Fixed JS ticker ID (`#countdownTimerDisplay`) and wired an auto-submitting form (`#autoCountdownRecalculateForm`) that fires `recalculateNow` as soon as the client-side countdown timer hits `00:00`.
7. **`$eventType` Parameter Fix (`CountdownCalculationEngine.php`)**:
   - Fixed fatal parameter exception on worker allocation/employee modals by updating `$this->activityLogger->log()` calls to `$this->activityLogger->logResourceChange()`.
8. **Real-time AJAX Banner Sync without Page Refresh (`show.blade.php`)**:
   - Wrapped the countdown banner in `#countdownBannerContainer` with `data-remaining-seconds`.
   - Implemented `syncCountdownBannerFromHtml($newDoc)` and connected it to the AJAX completion hooks of both `Section Personnel Modal` and `Work Center Employees Modal`.
   - The banner and ticker now appear and start ticking immediately upon saving worker allocations without requiring an F5 page refresh.
9. **Deterministic Activity Log Sequencing (`WorkCenter.php`, `show.blade.php`)**:
   - Work Center calculations strictly execute and persist *after* all section calculations in `WorkCenterCalculationService::calculateAndSave()`.
   - Fixed apparent out-of-order log entries caused by MySQL `created_at DESC` timestamp ties by enforcing `orderByDesc('id')` across the `activityLogs()` relationship and in `show.blade.php`.
10. **Strict Calculation Snapshot Audit Logging (`CountdownCalculationEngine.php`)**:
   - Removed all intermediate event logging (`calculation_queued`, `countdown_started`, `countdown_duration_updated`) from `CountdownCalculationEngine`.
   - Purged clutter event logs from the database so `production_work_center_activity_logs` is strictly reserved for genuine completed calculation snapshots (`Section Calc` and `WC Rollup`), eliminating fake `#1` snapshot links.
11. **Top Header Action Toolbar Countdown Pill (`show.blade.php`)**:
   - Added `#topBarCountdownTimerBadge` (`<span id="topCountdownTimerDisplay">`) directly in the top action toolbar beside `Debounce` and `Recalculate`.
   - Automatically synchronizes with the main body banner, ticking in real-time when active and hiding when inactive.
12. **Section Code Badges in Countdown Banner (`show.blade.php`)**:
   - Replaced raw database IDs (`SEC #1`, `SEC #2`, `SEC #7`) in the Active Countdown Banner with clean, human-readable Section Codes (e.g., `SEC-CNC-01`, `SF`, `WAF`).
13. **Inactive & Deleted Section Lifecycle (Track in Batch & Banner, Exclude from Calculation Values)**:
   - **Tracking**:
     - Inactive and soft-deleted sections are retained and tracked in `pending_calculation_section_ids` via `CountdownCalculationEngine::triggerCountdown()` using `WorkCenterSection::withTrashed()`.
     - When a section is deleted, `WorkCenterSectionService::delete()` enqueues the section ID and arms the countdown timer so the pending deletion is tracked.
     - The Active Countdown Banner displays badges for inactive (`[SEC-01] (Inactive)`) and deleted (`[SEC-01] (Deleted)`) sections.
     - When the batch runs (recalculate now or timer expiry), the deleted and inactive section IDs are preserved in `impacted_section_ids` and tied to the unified `batch_id`.
   - **Calculation Isolation (Values Strictly NOT Calculated)**:
     - `WorkCenterCalculationService::calculateAndSave()` strictly resolves active, non-deleted sections for rollup calculations; deleted and inactive sections contribute 0 to capacity, labor costs, machine costs, and operating hours.
     - `SectionCalculationService::calculate()` guards against `!$section->isActive() || $section->trashed()`, immediately returning 0.0 values with clear explanatory details.
     - `SectionCalculationService::calculateAndSave()` throws an exception if invoked on a trashed section.
     - In the main sections table (`show.blade.php`), inactive sections display `0.0 units/day` and `$0.00/day` with an `(Inactive)` pill and explanatory popover, preventing stale active snapshot values from leaking.
14. **Human-Readable Sequential Snapshot Numbering (`snapshot_number` across all models & UI)**:
   - Added sequential `snapshot_number` column (indexed with custom short names) to `production_work_center_calculations`, `production_work_center_section_calculations`, and `production_work_center_activity_logs`.
   - Automatically scoped per Work Center and per Section (`#1, #2, #3...`) in `WorkCenterCalculationService`, `SectionCalculationService`, and `WorkCenterActivityLogger`.
   - Replaced raw MySQL auto-increment primary keys everywhere in the UI: activity logs table, tab activity table, full logs modal, WC metric header, and calculation breakdown modals.

#### 4. Verification Results
* **Syntax Linting**: All modified PHP files (`CountdownCalculationEngine.php`, `WorkCenterSectionService.php`, `WorkCenterService.php`, `MachineController.php`, `WorkCenterEmployeeController.php`, `ExternalWorkerAllocationController.php`, `WorkCenterController.php`, `WorkCenter.php`, `routes/console.php`) pass syntax linting with 0 errors.
* **Console Command**: `php artisan production:process-calculation-countdowns` ran successfully with exit code 0.
* **View Rendering Verification**: Executed `test_view.php` against live database rendering `show.blade.php` &rarr; returned **HTTP 200 OK (Content Length: 2,236,787 bytes)** with all accordion rows, contractor badges, banner sync hooks, and countdown tickers functional.

#### 5. Impacted Files
* `app/Domains/Production/Services/CountdownCalculationEngine.php`
* `app/Domains/Production/Models/WorkCenter.php`
* `app/Domains/Production/Services/WorkCenterSectionService.php`
* `app/Domains/Production/Services/WorkCenterService.php`
* `app/Domains/Production/Services/ExternalWorkerAllocationService.php`
* `app/Domains/Production/Services/WorkCenterEmployeeService.php`
* `app/Domains/Production/Services/MachineService.php`
* `app/Domains/Production/Controllers/WorkCenterController.php`
* `resources/views/modules/production/work-centers/show.blade.php`
* `routes/console.php`

---

### ISSUE-03: Section Details Modal (Machines Tab Footer): Permanent Live Machine Sum
* **Date Logged:** 2026-10-27
* **Status:** **Applied (Permanent Architecture Decision — Do Not Alter)**
* **Reported By:** User
* **Area:** Section Details Modal (`#sectionDetailsModal_{id}`) &rarr; Machines Tab Footer (`#sec-machines-pane-{id}`)

#### 1. Issue Description & Context
In the Section Details Modal (`Machines` tab), the table lists individual machines assigned to the section with their individual `CAPACITY / HR` and `OPERATING COST / HR`.
The user confirmed that the Machines tab is an **asset assignment inspector**, and its table footer (`Total Machine Output:`) must always show the true live sum of the machines listed directly above it in the table.

#### 2. Agreed & Locked Solution (Permanent Architecture Rule)
In `resources/views/modules/production/work-centers/show.blade.php` (lines 2110–2122), the Machines tab table footer (`Total Machine Output:`) is strictly decoupled from calculation snapshots and directly sums the active assigned machines:
* **Total Machine Output (Capacity / HR)**: `(float) $section->machines->sum('capacity')` (e.g. `125.0 u/h`)
* **Total Machine Output (Operating Cost / HR)**: `(float) $section->machines->sum('operating_cost_per_hour')` (e.g. `$27.00/hr`)

**Note:** This live table sum behavior is locked and must be kept exactly as it is. It must not be replaced by calculation snapshot metrics.

#### 3. Impacted Files
* `resources/views/modules/production/work-centers/show.blade.php` (lines 2110–2122)

---

### ISSUE-04: Main Section Row & Capacity Popover: Remove Ad-hoc Database Fallbacks — Strictly Source from Calculation Table
* **Date Logged:** 2026-10-27
* **Status:** **Applied**
* **Reported By:** User
* **Area:** Section Row &rarr; Daily Capacity & Operating Cost Columns + Hover Popover (`resources/views/modules/production/work-centers/show.blade.php`)

#### 1. Issue Description
When hovering over the Daily Capacity info icon on the main section row before calculation snapshots are generated:
- `Machine Cap:` displays `0.0 u/d (120.0 u/h)`
- `Manual Batch:` displays `0.0 u/d (55.0 u/h)`
- `Total Cap:` displays `0.0 u/d (0.0 u/h)`
Here, the Machine and Manual lines show live hourly capacity sums from raw tables (`120.0 u/h` and `55.0 u/h`), but their daily capacities are `0.0 u/d`, and the total hourly capacity is `0.0 u/h`.

#### 2. Reason / Root Cause
In `resources/views/modules/production/work-centers/show.blade.php` (lines 804–810):
```blade
$totals = $secCalc?->calculation_details['totals'] ?? [];
$machHourlyCap = (float) ($totals['hourly_machine_capacity'] ?? ($section->machines ? $section->machines->sum('capacity') : 0));
$machDailyCap  = (float) ($totals['daily_machine_capacity'] ?? 0);
$manHourlyCap  = (float) ($totals['hourly_manual_capacity'] ?? ($section->activeManualProductions ? $section->activeManualProductions->sum('capacity_per_hour') : 0));
$manDailyCap   = (float) ($totals['daily_manual_capacity'] ?? 0);
$totalDailyCap = (float) ($totals['total_daily_capacity'] ?? $dailyCap);
$totalHourlyCap= (float) ($totals['total_hourly_capacity'] ?? $capPerHour);
```
- The main section table columns (Daily Capacity, Daily Operating Cost) and this popover represent calculated engine metrics.
- Currently, ad-hoc fallback sums (`$section->machines->sum('capacity')` and `$section->activeManualProductions->sum('capacity_per_hour')`) are mixed in when `$secCalc` is null, creating partial/inconsistent numbers.

#### 3. Agreed Solution (User Decision)
1. **Strictly Source from Calculation Tables**:
   - The main section table columns and capacity popover belong strictly to the calculation engine.
   - **Do NOT use ad-hoc database collection fallbacks** for capacity or operating cost outside the modal.
   - If a calculation snapshot has not been generated yet, all calculation values must strictly be `0.0`.
2. **Professional Uncalculated State Warning / Tooltip**:
   - Display a professional, non-intrusive indicator (subtle badge or info tooltip) when `$secCalc` is null:
     > *"Calculation Pending: Initial calculation snapshot has not been generated yet. Values will update upon the next scheduled calculation cycle or by clicking 'Recalculate'."*
   - This provides immediate clarity during the initial debounce period of newly created Work Centers / Sections so users know why figures show zero without displaying confusing partial numbers.
3. **Clear Distinction from Section Details Modal**:
   - The **Section Details Modal** (`#sectionDetailsModal_{id}`) is an asset assignment inspector (listing raw machines, personnel, and batches). For that modal's tabs, live assignment totals (e.g. sum of machine capacities in the Machines tab footer and sum of manual batch rates in the Manual Batches tab footer) are correct and appropriate (see ISSUE-03 & ISSUE-05).
   - But for the **Main Section Row & Capacity Popover**, no calculation fallbacks will be used — values will come 100% from the calculation tables.

#### 4. Files to Modify (During Batch)
* `resources/views/modules/production/work-centers/show.blade.php` (lines 804–826, 954–980)

---

### ISSUE-05: Section Details Modal (Manual Batches Tab): Missing Table Footer / Total Row for Target Rate & Allocated Workforce
* **Date Logged:** 2026-10-27
* **Status:** **Applied**
* **Reported By:** User
* **Area:** Section Details Modal (`#sectionDetailsModal_{id}`) &rarr; Manual Batches Tab (`#sec-batches-pane-{id}`)

#### 1. Issue Description
In the Section Details Modal (`Manual Batches` tab):
- The table displays individual batches with columns: `BATCH NAME`, `SHIFT`, `TARGET RATE`, `WORKFORCE ALLOCATED`, `STATUS`.
- While the **Machines** tab has a summary footer row (`Total Machine Output:` with total capacity and total cost), the **Manual Batches** table has **no `<tfoot>` footer row at all**.
- Users cannot see the total manual target rate (e.g., `15.0 + 12.0 = 27.0 u/h`) or total workforce allocated across manual batches (`2 Emp, 3 Ext`).

#### 2. Reason / Root Cause
In `resources/views/modules/production/work-centers/show.blade.php` (lines 2013–2067), the `<table>` ends immediately after `</tbody>` without a `<tfoot>` block:
```blade
<table class="table table-sm table-hover align-middle mb-0 fs-12">
    <thead class="table-light">...</thead>
    <tbody>...</tbody>
    {{-- Missing <tfoot> here --}}
</table>
```
The variable `$secDetailsManHourlyCap` is already computed at line 1665:
```blade
$secDetailsManHourlyCap = (float) ($secDetailsMan['hourly_manual_capacity'] ?? $secDetailsTotals['hourly_manual_capacity'] ?? ($section->activeManualProductions ? $section->activeManualProductions->sum('capacity_per_hour') : 0));
```
However, it is never rendered in a table footer for the tab.

#### 3. Agreed Solution (User Decision & Architectural Refinement)
1. **Avoid Inaccurate Ad-Hoc Sums**:
   - Because manual batches are **shift-anchored**, multiple batches on different shifts (e.g. Batch A at 15 u/h on Morning Shift and Batch B at 12 u/h on Evening Shift) **never run at the same time**.
   - Simply summing them as `27 u/h` is mathematically incorrect and misleading to users.
2. **Table Footer Sourcing**:
   - The footer must source from the **calculation engine snapshot** rather than an ad-hoc arithmetic sum.
   - When a snapshot exists:
     - Show **Total Daily Manual Output** (e.g. `440.0 u/d`) and **Day-Average Hourly Speed** (e.g. `26.7 u/h`), with an optional note/tooltip indicating peak/active speed.
     - Show **Total Allocated Workforce** (`X Emp`, `Y Ext`).
   - When uncalculated (`$secCalc` is null):
     - Do **NOT** render a raw arithmetic sum of the batch rates.
     - Display the standard **Calculation Pending** tooltip/badge (`0.0 u/d`), ensuring the user isn't misled by false fallback numbers.

#### 4. Files to Modify (During Batch)
* `resources/views/modules/production/work-centers/show.blade.php` (lines 2064–2066)

---

### ISSUE-06: Section Details Modal (Cost & Capacity Tab & Top 2 KPIs): Uncalculated Warning & Remove Fallbacks
* **Date Logged:** 2026-10-27
* **Status:** **Applied**
* **Reported By:** User
* **Area:** Section Details Modal (`#sectionDetailsModal_{id}`) &rarr; Top 2 KPI Cards & Cost & Capacity Tab (`#sec-cost-pane-{id}`)

#### 1. Issue Description
Inside the Section Details Modal:
1. **Top 2 KPI Cards (Daily Capacity & Daily Operating Cost)**:
   - When a section has not been calculated, Card 2 and Card 3 displayed `0.0 units/day (Avg 0.0 units/hr)` and `$0.00 ($0.00/hr)`.
2. **Cost & Capacity Tab**:
   - `Operating Cost Breakdown` displayed all `$0.00` values when uncalculated.
   - But in `Capacity Breakdown`:
     - `Machine Daily Capacity:` displayed `0.0 u/d (60.0 u/h)`
     - `Machine Cost per Hour:` displayed `$7.00/hr`
   - Even though daily totals were `0`, the hourly machine capacity `(60.0 u/h)` and machine opex `$7.00/hr` bled through from live database collection fallbacks.

#### 2. Reason / Root Cause
In `resources/views/modules/production/work-centers/show.blade.php`:
- Cards 2 & 3 had no `@if($secDetailsCalc)` check, rendering raw 0 numbers.
- Lines 1782–1788 fell back to querying `$section->machines->sum('capacity')` and `operating_cost_per_hour` when `$secDetailsCalc` was null.

#### 3. Implemented Solution
1. **Top KPI Cards 2 & 3**:
   - When `$secDetailsCalc` is null, both cards now render the amber **Calculation Pending** warning block:
     > *"Calculation Pending: Initial calculation snapshot has not been generated yet. Values will update upon recalculation."*
   - Eliminates confusing `0.0` outputs for uncalculated sections.
2. **Cost & Capacity Tab Alert Language**:
   - Standardized alert banner text to:
     > *"Calculation Pending: Initial calculation snapshot has not been generated yet. Values will update upon recalculation."*
3. **Removed Ad-hoc Fallbacks**:
   - Hourly machine capacity and machine cost now strictly evaluate to `0.0` when uncalculated, preventing live machine numbers from bleeding into an uncalculated breakdown.
4. **Purpose & Operational Notes Placement**:
   - Moved the Section Purpose & Operational Notes card directly below the Top 4 KPI Cards and above the tab navigation bar.
5. **Cost & Capacity Tab Overhaul (Columns 7 to 22 Direct Sourcing)**:
   - Replaced flat ad-hoc text list with two structured 3-column comparative tables (`Component/Source`, `Daily`, `Hourly`):
     * **Operating Cost Breakdown (Cols 13–22)**:
       - Row 1: Internal Labor (`total_daily_internal_labor_cost` [16] & `internal_labor_cost_per_hour` [15])
       - Row 2: External Workforce (`total_daily_external_labor_cost` [18] & `external_labor_cost_per_hour` [17])
       - Row 3: Total Labor Subtotal (`total_daily_labor_cost` [14] & `labor_cost_per_hour` [13])
       - Row 4: Machinery Expense (`total_daily_machine_cost` [20] & `machine_cost_per_hour` [19])
       - Footer: Total Daily Operating Cost (`total_daily_operating_cost` [22] & `total_operating_cost_per_hour` [21])
     * **Capacity Breakdown (Cols 7–12)**:
       - Row 1: Machinery Output (`daily_machine_capacity` [10] & `hourly_machine_capacity` [9])
       - Row 2: Manual Batches (`daily_manual_capacity` [12] & `hourly_manual_capacity` [11] with explanatory tooltip)
       - Row 3: Active Operating Window (`unique_operating_hours` [23] / net schedule)
       - Row 4: Unit Production Cost (Derived: Total Cost / Total Units)
       - Footer: Total Section Capacity (`daily_capacity` [8] & `capacity_per_hour` [7])

#### 4. Impacted Files
* `resources/views/modules/production/work-centers/show.blade.php`

---

### ISSUE-07: Section Details Modal: Shifts & Schedule Missing Footer Total & Operating Window KPI Fallback
* **Date Logged:** 2026-10-27
* **Status:** **Applied**
* **Reported By:** User
* **Area:** Section Details Modal (`#sectionDetailsModal_{id}`) &rarr; Shifts & Schedule Tab (`#sec-shifts-pane-{id}`) + Top KPI Card

#### 1. Issue Description
1. In the Section Details Modal, the **Shifts & Schedule** tab displays assigned shifts with columns: `Shift Code`, `Shift Name`, `Operating Window`, `Net Working Hours`, `Workforce On Duty`.
   - Like the Manual Batches tab, it lacks a table footer (`<tfoot>`) totaling the Net Working Hours or summarizing the combined shift operating window.
2. In the top 4 KPI cards of the modal, the **Operating Window** card displays `0:00 hrs/day` when a calculation snapshot has not been created, even though shifts are assigned and visible right below.

#### 2. Reason / Root Cause
In `resources/views/modules/production/work-centers/show.blade.php`:
- Lines 1834–1878: The `<table>` in `#sec-shifts-pane` has no `<tfoot>` to total the working hours of assigned shifts.
- Line 1706: The top KPI card strictly reads `$secDetailsCalc?->unique_operating_hours ?? 0`. Without a snapshot, it displays `0:00`.

#### 3. Agreed Solution (User Decision)
1. **Add `<tfoot>` to Shifts & Schedule Table**:
   - Total Net Working Hours: Sum of working hours across active shifts (e.g., `8.0 hrs + 8.0 hrs = 16.0 hrs`).
   - Combined Operating Window: Earliest start time to latest end time (e.g., `06:00 – 22:30`) or union operating hours.
   - Total Workforce On Duty: Total employees and external workers assigned across all shifts.
2. **Carry Forward to Top KPI Card**:
   - The **Operating Window** top KPI card should display the total shift hours / operating window calculated from the assigned shifts (falling back to the shifts table sum/window when uncalculated, or displaying the snapshot's `unique_operating_hours` once calculated).

#### 4. Files to Modify (During Batch)
* `resources/views/modules/production/work-centers/show.blade.php` (lines 1705–1717, 1877–1879)

---

### ISSUE-08: WorkCenterCalculationService: Fix `operating_hours` Typo to `operating_timeline` for Shift Extraction
* **Date Logged:** 2026-10-27
* **Status:** **Applied**
* **Reported By:** Code Audit
* **Area:** `app/Domains/Production/Services/WorkCenterCalculationService.php` (line 66)

#### 1. Issue Description
In `WorkCenterCalculationService::calculate()`, the service extracts section shifts to compute the overall Work Center unique operating hours across all sections:
```php
$secShifts = $secCalc['calculation_details']['operating_hours']['shifts'] ?? [];
```
However, in `SectionCalculationService::calculate()`, Category 1 in `calculation_details` is named `'operating_timeline'`, not `'operating_hours'`.

#### 2. Reason / Root Cause
Because the key is `'operating_timeline'`, `$secShifts` always resolves to `[]` (empty array), meaning `$allShiftsAcrossSections` never receives any section shifts during roll-up calculation. This causes Work Center `unique_operating_hours` to calculate to `0.0` unless direct work center shifts exist.

#### 3. Agreed Solution
Update line 66 in `WorkCenterCalculationService.php` to check `operating_timeline` with fallback:
```php
$secShifts = $secCalc['calculation_details']['operating_timeline']['shifts'] 
    ?? $secCalc['calculation_details']['operating_hours']['shifts'] 
    ?? [];
```

#### 4. Files to Modify (During Batch)
* `app/Domains/Production/Services/WorkCenterCalculationService.php` (line 66)

---

### ISSUE-09: Main Section Row: Daily Operating Cost Column Missing Matching Hover Breakdown Popover
* **Date Logged:** 2026-10-27
* **Status:** **Applied**
* **Reported By:** User
* **Area:** Main Section Table Row &rarr; Daily Operating Cost Column (`resources/views/modules/production/work-centers/show.blade.php`)

#### 1. Issue Description
In the Main Sections table:
- Column 4 (**Daily Capacity**) has a hover breakdown popover (`Daily Capacity Breakdown`) showing:
  - Machine Capacity (Daily & Hourly)
  - Manual Batch Capacity (Daily & Hourly)
  - Total Capacity
- But Column 5 (**Daily Operating Cost**) has **no hover popover at all**. It only has a static text line `Labor: $X | Mach: $Y` and a modal click trigger, creating an inconsistent user experience across adjacent metric columns.

#### 2. Reason / Root Cause
In `resources/views/modules/production/work-centers/show.blade.php`:
- Lines 955–965 wrap the Daily Capacity cell with `data-bs-toggle="popover"` and render `$capacityPopoverHtml`.
- Lines 982–1004 render the Daily Operating Cost cell inside a plain `<div>` without any popover attributes or HTML breakdown template.

#### 3. Agreed Solution
1. Construct `$costPopoverHtml` in `show.blade.php` mirroring `$capacityPopoverHtml`:
   - Title: `<i class="feather-dollar-sign text-warning me-1.5"></i>Daily Operating Cost Breakdown`
   - Row 1: **Labor Cost**: Daily amount + Hourly average in parentheses:
     - Internal Labor & External Labor sub-breakdown
   - Row 2: **Machine Expense**: Daily machine cost + Hourly average in parentheses
   - Row 3: **Total Operating Cost**: Total daily cost + Hourly rate in parentheses
2. Add `data-bs-toggle="popover"` with `cost-popover-trigger` to the Daily Operating Cost column.
3. In uncalculated state (`$secCalc` is null):
   - Strictly source values from the calculation snapshot (evaluating to `$0.00 /day ($0.00 /hr)`).
   - Display the standard uncalculated pending indicator/tooltip without ad-hoc collection fallbacks.

#### 4. Files to Modify (During Batch)
* `resources/views/modules/production/work-centers/show.blade.php` (lines 804–826, 981–1004)

---

### ISSUE-10: Manual Batch Hourly Capacity Dilution (Active Speed Omitted to Prevent Redundancy & Confusion)
* **Date Logged:** 2026-10-27
* **Status:** **Applied / Closed (Decision Finalized)**
* **Reported By:** User
* **Area:** `show.blade.php` &rarr; Hover Popovers & Section Details Modal

#### 1. Issue Description
A section has 1 active manual batch configured with target speed = **55.0 u/h** assigned to the **Morning Shift** (8.0 hours).
After recalculating, the section-wide average manual speed displays as **26.7 u/h** across the section's 16.5-hour operating window ($440\text{ units} / 16.5\text{ hrs}$).

#### 2. Agreed Decision & Final Simplification (User Confirmed)
1. **Omit Active Speed Completely**:
   - The user explicitly decided: **Do NOT show current active speed** anywhere (neither in hover popovers nor in modals), as that introduces unnecessary calculation, cognitive overhead, and extra work.
   - Popovers and tables strictly display standard, uniform 3-column rows (`Capacity Source`, `Total Units / Day`, `Avg Units / Hour`).
2. **Simple Warning-Only State for Uncalculated Scenario**:
   - When calculation snapshot has not been generated (`$secCalc` is null), hover popovers render the compact **Calculation Pending** warning alert box rather than empty or zero-filled tables.

#### 3. Impacted Files
* `resources/views/modules/production/work-centers/show.blade.php`

---

### ISSUE-11: Section Calculation Modal: Canonical Keys & UI Consistency (Terminology & Reconciliation Tooltip)
* **Date Logged:** 2026-10-28
* **Status:** **Applied**
* **Reported By:** User
* **Area:** Section Calculation Breakdown Modal (`Machines & Energy` and `Manual Batches` tabs) & Calculation Service

#### 1. Issue Description & User Directives
1. **No Duplicate Backend Keys**: The user explicitly rejected adding duplicate alias keys into the calculation service / JSON database payload (e.g. duplicating `cap_hr` as `capacity`, `eff_hrs` as `shift_effective_hours`, etc.). Adding aliases creates schema pollution, bloat, and confusion.
2. **Standardize Frontend on Canonical Keys**: All frontend views must read canonical backend keys directly (`cap_hr`, `calculated_base_cost_hr`, `additional_cost_per_hour`, `deductibles_per_hour`, `eff_hrs`, `daily_cap`, `team_members`).
3. **Terminology Consistency**: The UI must use standardized, professional terminology across all modules. If a metric represents capacity, label it "Capacity" / "Hourly Capacity" — do not use colloquial terms like "Speed" or "Hourly Speed" in one modal and "Capacity" in another.
4. **Missing Reconciliation Tooltip**: The Section Calculation Breakdown modal's `Machines & Energy` table was missing the detailed, professional reconciliation tooltip and badge definitions applied to the Machine Create/Edit views explaining baseline energy vs additional operational expense vs operating grant/subsidy.

#### 2. Root Cause Analysis
1. Blade template was referencing legacy or ad-hoc key names instead of the canonical keys emitted by `SectionCalculationService.php`.
2. Terminology differed across tabs ("Speed" in machines/batches vs "Capacity" in summary cards and work center views).
3. The table header for `Reconciliation` lacked the informational tooltip explaining the cost reconciliation logic.

#### 3. Implemented Solution
1. **Clean Backend Architecture (Removed Aliases in `SectionCalculationService.php`)**:
   - Removed temporary alias keys from `calculateSectionMachines()` and `calculateSectionManualBatches()`.
   - Updated formula text from "Batch speed" to "Batch capacity".
2. **Frontend Canonical Key Consumption (`section-calculation-modal.blade.php`)**:
   - Machines table: Directly consumes `$m['cap_hr']`, `$m['calculated_base_cost_hr']`, `$m['additional_cost_per_hour']`, `$m['deductibles_per_hour']`.
   - Manual batches table: Directly consumes `$b['cap_hr']`, `$b['eff_hrs']`, `$b['daily_cap']`, `$b['team_members']`.
   - Defensive fallbacks (`?? $m['capacity'] ?? 0`) retained solely for seamless backward compatibility with historical database snapshots.
3. **Terminology Standardization**:
   - Renamed table headers from `Speed` &rarr; `Hourly Capacity` and `Hourly Speed` &rarr; `Hourly Capacity`.
   - Renamed summary card labels: `Weighted Avg Speed` &rarr; `Weighted Avg Capacity` and `Peak Concurrent Output` &rarr; `Peak Concurrent Capacity`.
4. **Professional Reconciliation Tooltip & Badge Standards**:
   - Added professional tooltip to the `Reconciliation` table header in `section-calculation-modal.blade.php`:
     *"Compares entered operating cost against theoretical base energy cost (Consumption × Energy Rate). Surpluses represent additional operating expenses (tooling, coolant, wear); deficits represent operating grants, subsidies, or energy offsets."*
   - Updated badges with clear contextual tooltips:
     - `+$X.XX/hr extra`: "Operating cost includes additional operational expenses beyond base energy."
     - `-$X.XX/hr subsidy`: "Operating cost reflects operational grant, subsidy, or rate discount."
     - `Exact Base`: "Operating cost exactly equals calculated base energy cost."

#### 4. Impacted Files
* `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php`
* `app/Domains/Production/Services/SectionCalculationService.php`
* `docs/calculation_audit_issues_tracker.md`

---

### ISSUE-12: Section Calculation Modal: Manual Batches Tab Internal & External Workforce Display & Cost Breakdown Inline Accordion Row
* **Date Logged:** 2026-10-28
* **Status:** **Applied**
* **Reported By:** User (via Manual Batches snapshot screenshot & UI reference photos)
* **Area:** Section Calculation Breakdown Modal (`Manual Batches` tab) & `SectionCalculationService.php`

#### 1. Issue Description & User Requirements
1. **Vertical Space & Row Bloat**: Stacking multiple external contractor allocation badges under the batch name caused rows with many allocations (e.g. 5 allocations totaling 29 workers in `kurta`) to expand vertically, distorting table spacing and visual balance.
2. **Missing Granular Batch Labor Costing**: The table displayed capacity numbers but lacked the granular cost breakdown separating internal employee labor expense from external contractor expense for each specific batch.
3. **User Clarified UX (Photos 2 & 3 - Accordion Expand/Collapse)**:
   - Floating popup dropdowns overlapped adjacent table columns inside the modal.
   - The user provided reference screenshots demonstrating an inline expandable/collapsible accordion sub-row pattern:
     * A compact batch row with an expand button on the left showing a right-pointing chevron (`>`).
     * Next to it, the batch name and compact workforce count pills (`X Emp` and `Y Ext`).
     * When toggled, the chevron button smoothly rotates downwards (`v`), and an inline accordion row (`<tr><td colspan="6">...</td></tr>`) expands downwards directly beneath the batch row.
     * The expanded container displays a clean 2-column card layout:
       - **Internal Employees** (left): names, employee codes, hourly costs, daily salaries, and subtotal.
       - **External Contractors** (right): pay grade names, worker headcounts, rates, hourly burns, daily costs, and subtotal.
       - **Labor Expense Summary Banner** (bottom): total batch hourly burn, daily expense, and cost per unit output.

#### 2. Implemented Solution
1. **Enhanced `SectionCalculationService::calculateSectionManualBatches()`**:
   - Computes exact labor costs for each manual batch:
     * **Internal Employees**: Monthly salary CTC / 30 / shift clock hours &rarr; `$empHourlyRate`, `$empDailyCost`.
     * **External Contractors**: Headcount × pay grade rate &rarr; `$allocHourlyBurn`, `$allocDailyCost` (full shift clock duration).
     * **Batch Totals**: `internal_hourly_cost`, `internal_daily_cost`, `external_hourly_cost`, `external_daily_cost`, `total_batch_hourly_cost`, `total_batch_daily_cost`, and `cost_per_unit`.
     * Stores granular employee and allocation arrays (`internal_employees_details`, `external_contractors_details`) directly in the calculation snapshot payload.
2. **Inline Accordion Row UI (`section-calculation-modal.blade.php`)**:
   - Compact batch row featuring:
     * A dedicated `.batch-expand-btn` styled with primary background in collapsed state and rotating chevron animation.
     * Batch name and compact workforce badges: `X Emp` (`bg-soft-info`) and `Y Ext` (`bg-soft-primary`).
     * Standard metrics: Assigned Shift, Hourly Capacity, Shift Eff. Hours, Daily Output, and English Formula.
   - Collapsible sub-row (`<tr class="batch-detail-row"><td colspan="6"><div class="collapse batch-collapse-pane">`):
     * **Left Card**: Internal Employees table with individual employee names, codes, hourly costs, daily salaries, and subtotal.
     * **Right Card**: External Workers table with headcounts, pay grades, rates, hourly burns, daily costs, and subtotal.
     * **Footer Banner**: Full-width card with total hourly burn, daily expense, shift details, and cost per unit output.
   - Includes graceful live fallback for older historical snapshots.
3. **Adaptive Zero-Data Layout**:
   - When a batch has zero internal employees (or zero contractors), the empty category collapses into a sleek 1-line status banner (`0 Emp | Internal Employees: No salaried staff assigned to this batch — $0.00/hr • $0.00/day`, ~34px high).
   - The active category expands across full width (`col-12`) with 4 spacious tabular columns (`Allocation/Pay Grade`, `Hourly Rate`, `Hourly Burn`, `Daily Cost`), completely eliminating empty 400px white boxes.
4. **Formula Column Optimization**:
   - Replaced wide dedicated formula columns across the Manual Batches table and personnel labor tables with discrete `(i)` info tooltips on Daily Output and Daily Cost.
5. **Manual Production Setup Toolbar Shift Code Badges**:
   - In the batch toolbar of the Manual Production Setup modal, added shift code badges beside batch names (`[ ✏️ gello | MRN | 55 u/h | Active ]`).
6. **Event Listeners & Sync (`show.blade.php`)**:
   - Integrated Bootstrap 5 collapse event delegation to handle toggle animations and sync `aria-expanded` attributes on triggers.

#### 3. Impacted Files
* `app/Domains/Production/Services/SectionCalculationService.php`
* `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php`
* `resources/views/modules/production/work-centers/show.blade.php`
* `docs/calculation_audit_issues_tracker.md`

---

### ISSUE-13: Section Calculation Modal: Personnel Labor Tab Redundant Columns, Shift N/A Anomaly & Diluted Hourly Footers
* **Date Logged:** 2026-10-28
* **Status:** **Applied** *(Shift N/A anomaly resolved & hours formatting applied; UI layout improvements queued for batch frontend overhaul)*
* **Reported By:** User (via Personnel Labor snapshot screenshot)
* **Area:** Section Calculation Breakdown Modal (`Personnel Labor` tab) & Calculation Service

#### 1. Issue Description
When viewing the **Personnel Labor** tab in the Section Calculation Breakdown Modal:

1. **Shift N/A Anomaly with Fabricated 8.0 hrs (Row 2: Aarti Patel)**:
   - In Table 1 (`Internal Employees`), Aarti Patel's shift displayed as badge **`[N/A]`** with **`8.0 hrs`** subtitle.
   - Her hourly salary rate was computed using fabricated 8.0 hours rather than her actual shift schedule:
     $$\$1,266.67\text{ daily salary} / 8.0\text{ hrs} = \mathbf{\$158.33/hr}$$
   - **Root Cause**: Aarti Patel's employee record has HRMS shift `Shift ID: 1` (`General Day Shift`, 09:00 - 17:30, 8.5 clock hours). However, Section 1 is configured with only `Shift 21` (`Morning Shift`) and `Shift 61` (`Plant Evening Shift`). Because Shift 1 is not part of the section's active shifts map, `SectionCalculationService::calculatePersonnel()` failed to resolve the shift name and defaulted to `'N/A'` and `8.0 hrs`.
2. **Time Formatting in Personnel Table**:
   - Durations displayed as raw decimals (`8.5 hrs`) rather than standardized format.

#### 2. Implemented Solution
1. **Graceful HRMS Shift Resolution (`SectionCalculationService.php`)**:
   - In `calculatePersonnel()`: When an employee's assigned HRMS shift is not present in `$shiftMap` (i.e. outside the section's active shifts), the service now resolves the shift directly via `ProductionShift::find($shiftId)`:
     - Extracts the true shift name (e.g. `General Day Shift`).
     - Computes exact clock duration between start and end time (e.g. 09:00 to 17:30 = 8.5 hrs) rather than hardcoded 8.0.
     - Sets `'is_outside_section_shifts' => true` to explicitly flag the condition for the frontend.
2. **Warning Pill Display (`section-calculation-modal.blade.php`)**:
   - Replaced `[N/A]` with an amber warning badge:
     `<span class="badge bg-soft-warning text-warning" title="Employee is assigned to this shift in HRMS, which is external to this section's active shifts">{{ $emp['shift_name'] }} (Ext)</span>`.
3. **Applied `format_hours()`**:
   - All internal employee and external contractor shift durations are now passed through `format_hours()`.

#### 3. Impacted Files
* `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php`
* `app/Domains/Production/Services/SectionCalculationService.php`
* `docs/calculation_audit_issues_tracker.md`

---

### ISSUE-14: Format Time/Hours Consistently Across Modals & Views via `format_hours()` Instead of Raw Decimals (`.5` vs `:30` / `.30`)
* **Date Logged:** 2026-10-28
* **Status:** **Applied**
* **Reported By:** User (via Timeline Intervals snapshot screenshot)
* **Area:** Section Calculation Breakdown Modal (`Timeline Intervals`, `KPI Cards`, `Personnel`, `Manual Batches`), Work Center Main Show View, and Service Formula Strings

#### 1. Issue Description
Throughout the Section Calculation Breakdown Modal (specifically shown in the `Timeline Intervals (3)` tab), time durations were rendered as raw decimal fractions (e.g. `0.50 h`, `8.5 hrs`, `16.50 h`) instead of human-readable clock hours (e.g. `0.30 hrs`, `8.30 hrs`, `16.30 hrs`).
* In the screenshot (`Timeline Intervals (3)`):
  - Window `14:00 - 14:30 [Overlap Surge]`: Duration displayed as **`0.50 h`** instead of `0.30 hrs`.
  - Footer `Total Day Window`: Duration displayed as **`16.50 h`** instead of `16.30 hrs`.
* Across other tabs in the modal:
  - Top KPI Card 5 (Operating Window): Displayed `16.5 hrs/day` and `Overlap: 0.5h`.
  - Manual Batches: Displayed raw decimal `8.0 hrs`.
  - Personnel Labor: Displayed raw decimal `8.5 hrs`.
  - Audit Metrics (Category 1): Displayed raw decimals.
* Across Work Center views (`show.blade.php`):
  - Operating window cards and schedule tables rendered raw decimals.

#### 2. Root Cause Analysis
Blade views were applying `number_format($hours, 1)` or `number_format($hours, 2)` directly to decimal hours instead of invoking `format_hours()` from `app/helpers.php`, which converts decimal fractions into true clock minutes (e.g. `0.50` &rarr; `0.30 hrs`, `16.50` &rarr; `16.30 hrs`).

#### 3. Implemented Solution
Standardized all hour and duration renderings to call `format_hours()`:
1. **`section-calculation-modal.blade.php`**:
   - Top KPI Card 5: Unique operating hours and overlap hours wrapped in `format_hours()`.
   - Manual Batches: Effective shift hours wrapped in `format_hours()`.
   - Personnel Labor: Internal employee and contractor clock hours wrapped in `format_hours()`.
   - Timeline Intervals: Interval duration and footer Total Day Window wrapped in `format_hours()`.
   - Audit Metrics Tab (Category 1): Shift clock hours, unique operating hours, overlap hours, and gap hours wrapped in `format_hours()`.
2. **`show.blade.php`**:
   - Work Center Operating Hours KPI cards and section details modal wrapped in `format_hours()`.
   - Shift table footers and tooltips wrapped in `format_hours()`.
   - Work Center timeline breakdown intervals and breakdown rows wrapped in `format_hours()`.
3. **`SectionCalculationService.php`**:
   - Explanation formula strings (machines, manual batches, internal salaries, and contractor burns) now call `format_hours()` on all duration parameters.

#### 4. Impacted Files
* `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php`
* `resources/views/modules/production/work-centers/show.blade.php`
* `app/Domains/Production/Services/SectionCalculationService.php`
* `docs/calculation_audit_issues_tracker.md`

---

### ISSUE-15: Section Calculation Modal: Timeline Intervals Not Generated for Unstaffed / Machine-Only Sections
* **Date Logged:** 2026-10-29
* **Status:** **Applied**
* **Reported By:** User (via section `hello` [SF] snapshot screenshot)
* **Area:** Section Calculation Engine (`SectionCalculationService.php` &mdash; Timeline Interval Slicing)

#### 1. Issue Description
When viewing the Section Calculation Breakdown Modal for Section **SF (`hello`)**:
* The section has:
  - 2 active shifts: `Plant Morning Shift (06:00 - 14:30)` and `Evening Shift 2 (14:00 - 23:00)`.
  - An operating window of `06:00 – 23:00` (17.00 hrs/day) with a 30-minute shift overlap (`14:00 - 14:30`).
  - 2 active machines running at total $140.0\text{ u/h}$ capacity and $\$16.60\text{/hr}$ operating cost (Daily output: $2,380.0\text{ units}$, Daily opex: $\$282.20$).
  - **0 internal employees and 0 external contractors** assigned.
* Despite having shifts, operating hours, and active machinery, the **`Timeline Intervals`** tab displays:
  - Tab title: **`Timeline Intervals (0)`**
  - Table body: **`No timeline intervals generated.`**

#### 2. Reason / Root Cause
In [`app/Domains/Production/Services/SectionCalculationService.php`](file:///c:/xampp/htdocs/wm-product-saas/app/Domains/Production/Services/SectionCalculationService.php#L916):
```php
// ── 3. 1440-Minute Timeline Interval Slicing ───────────────────────────────
if (empty($shiftsData) || (empty($activeEmployees) && empty($activeAllocations))) {
    $totalDailyLabor = round($totalDailyInternalCost + $totalDailyExternalCost, 4);
    $avgHourlyRate   = $uniqueOperatingHours > 0 ? round($totalDailyLabor / $uniqueOperatingHours, 4) : 0.0;

    return [
        ...
        'timeline_intervals' => [],
        ...
    ];
}
```
* **The Root Cause**: The guard clause `if (empty($shiftsData) || (empty($activeEmployees) && empty($activeAllocations)))` assumes that if a section has no workforce (neither salaried staff nor contractors), it has no timeline intervals.
* **Why this is wrong**:
  - The Timeline Intervals represent the **factory operating schedule discretized at shift boundaries**.
  - Slicing boundaries are determined entirely by `$shiftsData` (`start_minute`, `end_minute`).
  - Each interval contains:
    1. Window time and duration (e.g. `06:00 - 14:00`, `14:00 - 14:30 [Overlap]`, `14:30 - 23:00`).
    2. Running Machinery Capacity ($140.0\text{ u/h}$) and Machine Opex ($\$16.60\text{/hr}$).
    3. Window Output and Window Cost.
  - The actual interval loop (lines 944–1105) already works perfectly with 0 workers (it computes `$internalBurn = 0.0` and `$totalLaborBurn = 0.0`, while accurately applying `$hourlyMachineCapacity` and `$hourlyMachineExpense`).
  - The only scenario where intervals cannot be formed is when a section has **no shifts assigned** (`empty($shiftsData)`).

#### 3. Proposed Solution (Awaiting User Acceptance & Confirmation)
1. **Remove the workforce requirement from the timeline slicing guard in [`SectionCalculationService.php`](file:///c:/xampp/htdocs/wm-product-saas/app/Domains/Production/Services/SectionCalculationService.php#L916)**:
   Change:
   ```php
   if (empty($shiftsData) || (empty($activeEmployees) && empty($activeAllocations)))
   ```
   To:
   ```php
   if (empty($shiftsData))
   ```
2. **Handle Contextual Explanations for Unstaffed Windows**:
   When `$totalLaborBurn == 0`, adjust the interval explanation string so it doesn't print "Labor burn rate surges to $0.00/hr":
   ```php
   if ($totalLaborBurn > 0) {
       $explanation = $isOverlap
           ? "Shift Overlap: " . count($currentActiveShifts) . " shifts active concurrently. Labor burn rate surges to $" . number_format($totalLaborBurn, 2) . "/hr."
           : "Regular Window: 1 shift active. Labor burn rate: $" . number_format($totalLaborBurn, 2) . "/hr.";
   } else {
       $explanation = $isOverlap
           ? "Shift Overlap: " . count($currentActiveShifts) . " shifts active concurrently. Machine capacity: " . number_format($intervalTotalCap, 1) . " u/h (Unstaffed / Automated)."
           : "Regular Window: 1 shift active. Machine capacity: " . number_format($intervalTotalCap, 1) . " u/h (Unstaffed / Automated).";
   }
   ```
3. **Expected Result for Section `hello`**:
   The `Timeline Intervals (3)` tab will immediately render the 3 intervals:
   * **Window 1 (06:00 – 14:00, 8.00 hrs)**: Capacity: `140.0 u/h`, Labor: `$0.00/hr`, Machine Opex: `$16.60/hr`, Total: `$16.60/hr`, Output: `1,120.0 u`, Cost: `$132.80`.
   * **Window 2 (14:00 – 14:30, 0.30 hrs [Overlap Surge])**: Capacity: `140.0 u/h`, Labor: `$0.00/hr`, Machine Opex: `$16.60/hr`, Total: `$16.60/hr`, Output: `70.0 u`, Cost: `$8.30`.
   * **Window 3 (14:30 – 23:00, 8.30 hrs)**: Capacity: `140.0 u/h`, Labor: `$0.00/hr`, Machine Opex: `$16.60/hr`, Total: `$16.60/hr`, Output: `1,190.0 u`, Cost: `$141.10`.
   * **Total Day Window**: `17.00 hrs`, Output: `2,380.0 u`, Total Cost: `$282.20`.

#### 4. Impacted Files
* `app/Domains/Production/Services/SectionCalculationService.php`
* `docs/calculation_audit_issues_tracker.md`

---

### ISSUE-16: Table Column Optimization for Popovers & Cost/Capacity Tab (Dedicated Columns vs. JSON Extraction)
* **Date Logged:** 2026-10-29
* **Status:** **Applied** *(Migration executed, Model updated, Service populated, View direct-read enabled)*
* **Reported By:** User
* **Area:** Database Schema (`production_work_center_section_calculations`), Calculation Service, and Views (`show.blade.php`)

#### 1. Issue Description & Architectural Goal
Currently, the **Main Section Table Row** reads directly from dedicated table columns, but:
1. **Daily Capacity Breakdown Hover Popover** decodes JSON keys from `$secCalc->calculation_details['totals']` for machine output and manual output.
2. **Daily Operating Cost Breakdown Hover Popover** decodes JSON keys from `$secCalc->calculation_details['internal_labor']` and `['external_labor']` for internal salary burn and contractor rates.
3. **Section Details Modal &rarr; Cost & Capacity Tab** decodes JSON keys for machine daily/hourly capacity and manual daily/hourly capacity.

**Performance Impact:**
Parsing JSON blobs via `json_decode` on main list views consumes CPU cycles and PHP memory for every section rendered. Storing these sub-breakdowns directly as dedicated, typed database columns will eliminate JSON parsing entirely from `show.blade.php`, allowing instant, zero-overhead page rendering and direct SQL indexing/rollups.

---

#### 2. Detailed Field Audit & Column Mapping

##### A. "Daily Capacity Breakdown" Hover Popover
| Field Displayed | Current Source | Dedicated Column Exists? | Target Table Column Name | Recommended Column Definition |
| :--- | :--- | :--- | :--- | :--- |
| **Machine Output (Daily)** | JSON `totals -> daily_machine_capacity` | ❌ **No (Needs to be added)** | `daily_machine_capacity` | `decimal(14, 4)->default(0.0000)` |
| **Machine Output (Hourly)** | JSON `totals -> hourly_machine_capacity` | ❌ **No (Needs to be added)** | `hourly_machine_capacity` | `decimal(12, 4)->default(0.0000)` |
| **Manual Batches (Daily)** | JSON `totals -> daily_manual_capacity` | ❌ **No (Needs to be added)** | `daily_manual_capacity` | `decimal(14, 4)->default(0.0000)` |
| **Manual Batches (Hourly)** | JSON `totals -> hourly_manual_capacity` | ❌ **No (Needs to be added)** | `hourly_manual_capacity` | `decimal(12, 4)->default(0.0000)` |
| **Total Capacity (Daily)** | Table column `$secCalc->daily_capacity` | ✅ **Yes (Already exists)** | `daily_capacity` | `decimal(14, 4)` *(Existing)* |
| **Total Capacity (Hourly)** | Table column `$secCalc->capacity_per_hour` | ✅ **Yes (Already exists)** | `capacity_per_hour` | `decimal(12, 4)` *(Existing)* |

---

##### B. "Daily Operating Cost Breakdown" Hover Popover
| Field Displayed | Current Source | Dedicated Column Exists? | Target Table Column Name | Recommended Column Definition |
| :--- | :--- | :--- | :--- | :--- |
| **Labor Cost (Daily)** | Table column `$secCalc->total_daily_labor_cost` | ✅ **Yes (Already exists)** | `total_daily_labor_cost` | `decimal(14, 4)` *(Existing)* |
| **Labor Cost (Hourly)** | Table column `$secCalc->labor_cost_per_hour` | ✅ **Yes (Already exists)** | `labor_cost_per_hour` | `decimal(12, 4)` *(Existing)* |
| **• Internal Labor (Daily)** | JSON `internal_labor -> total_daily_internal_labor_cost` | ❌ **No (Needs to be added)** | `total_daily_internal_labor_cost` | `decimal(14, 4)->default(0.0000)` |
| **• Internal Labor (Hourly)**| JSON `internal_labor -> average_internal_hourly_rate` | ❌ **No (Needs to be added)** | `internal_labor_cost_per_hour` | `decimal(12, 4)->default(0.0000)` |
| **• External Labor (Daily)** | JSON `external_labor -> total_daily_external_labor_cost` | ❌ **No (Needs to be added)** | `total_daily_external_labor_cost` | `decimal(14, 4)->default(0.0000)` |
| **• External Labor (Hourly)**| JSON `external_labor -> average_external_hourly_rate` | ❌ **No (Needs to be added)** | `external_labor_cost_per_hour` | `decimal(12, 4)->default(0.0000)` |
| **Machine Expense (Daily)**| Table column `$secCalc->total_daily_machine_cost` | ✅ **Yes (Already exists)** | `total_daily_machine_cost` | `decimal(14, 4)` *(Existing)* |
| **Machine Expense (Hourly)**| Table column `$secCalc->machine_cost_per_hour` | ✅ **Yes (Already exists)** | `machine_cost_per_hour` | `decimal(12, 4)` *(Existing)* |
| **Total Cost (Daily)** | Table column `$secCalc->total_daily_operating_cost` | ✅ **Yes (Already exists)** | `total_daily_operating_cost` | `decimal(14, 4)` *(Existing)* |
| **Total Cost (Hourly)** | Table column `$secCalc->total_operating_cost_per_hour` | ✅ **Yes (Already exists)** | `total_operating_cost_per_hour` | `decimal(12, 4)` *(Existing)* |

---

##### C. Cost & Capacity Tab (Section Details Modal)
| Section & Field | Current Source | Dedicated Column Exists? | Target Table Column Name / Action |
| :--- | :--- | :--- | :--- |
| **Operating Cost Breakdown &rarr; Machine Cost per Hour** | JSON with Table fallback | ✅ **Yes (Already exists)** | **Read directly from:** `$secDetailsCalc?->machine_cost_per_hour` *(No JSON fallback needed)* |
| **Capacity Breakdown &rarr; Machine Daily Capacity (Daily)** | JSON `machinery -> daily_machine_capacity` | ❌ **No (Needs to be added)** | `daily_machine_capacity` *(Shared with Popover A)* |
| **Capacity Breakdown &rarr; Machine Daily Capacity (Hourly)**| JSON `machinery -> hourly_machine_capacity` | ❌ **No (Needs to be added)** | `hourly_machine_capacity` *(Shared with Popover A)* |
| **Capacity Breakdown &rarr; Manual Daily Capacity (Daily)** | JSON `manual_batches -> daily_manual_capacity`| ❌ **No (Needs to be added)** | `daily_manual_capacity` *(Shared with Popover A)* |
| **Capacity Breakdown &rarr; Manual Daily Capacity (Hourly)** | JSON `manual_batches -> hourly_manual_capacity`| ❌ **No (Needs to be added)** | `hourly_manual_capacity` *(Shared with Popover A)* |
| **Capacity Breakdown &rarr; Average Hourly Output** | JSON with Table fallback | ✅ **Yes (Already exists)** | **Read directly from:** `$secDetailsCalc?->capacity_per_hour` *(No JSON fallback needed)* |
| **Capacity Breakdown &rarr; Total Daily Production Capacity** | JSON with Table fallback | ✅ **Yes (Already exists)** | **Read directly from:** `$secDetailsCalc?->daily_capacity` *(No JSON fallback needed)* |

---

#### 3. Summary of Columns to Add vs. Existing Columns

##### Existing Columns (Can be switched immediately to direct table column reads):
1. `daily_capacity` (`decimal(14,4)`) &rarr; Total Daily Capacity
2. `capacity_per_hour` (`decimal(12,4)`) &rarr; Average Hourly Capacity
3. `total_daily_operating_cost` (`decimal(14,4)`) &rarr; Total Daily Operating Cost
4. `total_operating_cost_per_hour` (`decimal(12,4)`) &rarr; Total Hourly Operating Cost
5. `total_daily_labor_cost` (`decimal(14,4)`) &rarr; Total Daily Combined Labor Cost
6. `labor_cost_per_hour` (`decimal(12,4)`) &rarr; Average Hourly Combined Labor Rate
7. `total_daily_machine_cost` (`decimal(14,4)`) &rarr; Total Daily Machinery Operating Cost
8. `machine_cost_per_hour` (`decimal(12,4)`) &rarr; Machinery Hourly Operating Cost

##### New Columns to Add (Total 8 Columns to achieve 100% Zero-JSON List Views):
```php
Schema::table('production_work_center_section_calculations', function (Blueprint $table) {
    // 1-4: Capacity Sub-Breakdowns
    $table->decimal('hourly_machine_capacity', 12, 4)->default(0.0000)->after('daily_capacity');
    $table->decimal('daily_machine_capacity', 14, 4)->default(0.0000)->after('hourly_machine_capacity');
    $table->decimal('hourly_manual_capacity', 12, 4)->default(0.0000)->after('daily_machine_capacity');
    $table->decimal('daily_manual_capacity', 14, 4)->default(0.0000)->after('hourly_manual_capacity');

    // 5-8: Labor Sub-Breakdowns
    $table->decimal('internal_labor_cost_per_hour', 12, 4)->default(0.0000)->after('total_daily_labor_cost');
    $table->decimal('total_daily_internal_labor_cost', 14, 4)->default(0.0000)->after('internal_labor_cost_per_hour');
    $table->decimal('external_labor_cost_per_hour', 12, 4)->default(0.0000)->after('total_daily_internal_labor_cost');
    $table->decimal('total_daily_external_labor_cost', 14, 4)->default(0.0000)->after('external_labor_cost_per_hour');
});
```

#### 4. Required Implementation Steps (When Approved)
1. **Database Migration**: Create `add_breakdown_columns_to_section_calculations_table.php` adding the 8 columns.
2. **Model Fillable & Casts**: Add the 8 fields to `WorkCenterSectionCalculation::$fillable` and `$casts` as `decimal:4`.
3. **Calculation Service**: In `SectionCalculationService::calculateAndSave()`, map the 8 fields from `$calc['calculation_details']` directly into the database insert payload.
4. **View Consumption (`show.blade.php`)**: Update popovers and Cost & Capacity tab to read directly from `$secCalc->hourly_machine_capacity`, `$secCalc->total_daily_internal_labor_cost`, etc., with backward-compatibility fallbacks to JSON for historical records.

---

### ISSUE-17: Section Details & Calculation Modals: Add Explanatory Tooltip & Formula for Average Hourly Manual Capacity
* **Date Logged:** 2026-10-29
* **Status:** **Applied**
* **Reported By:** User
* **Area:** `show.blade.php` (Section Details modal) & `section-calculation-modal.blade.php` (Calculation Breakdown modal)

#### 1. Issue Description
When a manual batch has a target speed (e.g., 55.0 u/h) on an 8-hour shift, but the section operates for 16.5 hours across multiple shifts, the section-wide hourly manual capacity displays as a diluted/averaged rate (e.g., 26.7 u/h = 440 units / 16.5 hrs). Without contextual explanation, users frequently confuse this diluted number with the individual batch's target speed and assume a calculation error.

#### 2. Agreed Solution
Wherever average hourly manual capacity is displayed (Section Details Modal Pane 4 footer, Pane 5 Capacity Breakdown, Calculation Breakdown Modal top KPI cards, and Category 3 Audit Metrics table), attach an informative tooltip and badge:
1. **Explanation / Reason**: *"It is average production per hour throughout section shifts."*
2. **Formula Context**: *"Total Daily Manual Output / Section Operating Hours"* (with dynamic numbers, e.g. `440.0 units / 16.5 hrs = 26.7 u/h`).

#### 3. Implemented Changes
- Added tooltip and warning icon on Pane 4 footer & Pane 5 in [`resources/views/modules/production/work-centers/show.blade.php`](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/work-centers/show.blade.php).
- Added contextual badge and dynamic formula tooltip in [`resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php`](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php).

---

### ISSUE-18: Work Center Level Calculation Engine Rework (Average Capacity / hr, Multi-WC Direct Staff Allocation, Flat Overhead)
* **Date Logged:** 2026-10-29
* **Status:** **Applied**
* **Reported By:** User
* **Area:** `WorkCenterCalculationService.php`, `show.blade.php`, `WorkCenterCalculation.php`

#### 1. Issue Description
1. **Capacity per Hour**: The current Work Center calculation engine simply sums the hourly capacities of all active sections (`Section 1 + Section 2 = 80 u/h`). This reflects concurrent peak speed, not the true average hourly production across the Work Center's operating window.
2. **Direct Personnel Labor Allocation**: Direct employees (assigned to the Work Center with `section_id IS NULL`, like supervisors or maintenance leads) are currently assigned 100% of their daily salary to a single Work Center, even if they oversee multiple Work Centers simultaneously. Additionally, their hourly rate does not account for their assigned shift duration.
3. **Overhead Cost Hourly Rate**: Work Center calculates a derived hourly overhead rate (`overhead / hours`), which is unnecessary clutter; overhead should remain a flat daily cost rolled directly into the Total Daily Operating Cost.
4. **Modal Data Key Mismatch**: The Work Center Calculation Breakdown Modal in `show.blade.php` attempts to read `$wcDetails['sections']` and `$wcDetails['direct_personnel']`, while the service stores them as `sections_contribution` and `direct_work_center_staff`, leaving tables blank.

#### 2. Agreed Solution
1. **Average Hourly Capacity**:
   $$\text{Work Center Capacity Per Hour} = \frac{\text{Work Center Daily Capacity}}{\text{Work Center Unique Operating Hours}}$$
2. **Direct Staff Multi-Work Center Split**:
   - Query `production_work_center_employees` to count how many active Work Centers the employee is assigned to (`active_wc_count`).
   - Base Daily Salary: `Monthly CTC / 30`.
   - Allocated Daily Cost for this Work Center: `Base Daily Salary / active_wc_count`.
   - Staff Hourly Rate: `Allocated Daily Cost / Employee Predefined Shift Duration Hours`.
3. **Flat Overhead Integration**:
   - Keep overhead as a flat daily amount (`overhead_cost`).
   - Total Daily Operating Cost = `Total Daily Labor + Total Daily Machinery + Daily Overhead`.
   - Total Cost Per Hour = `Total Daily Operating Cost / Work Center Unique Operating Hours`.
4. **Dedicated Database Columns for Zero-JSON Reads**:
   - Add 2 lean columns to `production_work_center_calculations` via migration:
     * `direct_staff_daily_labor_cost` (`decimal(14,4)`): Stores total allocated daily salary of unassigned Work Center employees so Column 3 in the Hero panel reads directly from the database without decoding JSON.
     * `cost_per_unit` (`decimal(12,4)`): Stores $\text{Total Daily Cost} / \text{Daily Capacity}$.
   - Update `WorkCenterCalculation` model `$fillable` and `$casts`.
5. **Fix Modal Key Alignment**:
   - Align blade variables to read `sections_contribution` and `direct_work_center_staff`.

#### 3. Implemented Changes
- Ran database migration [`database/migrations/2026_10_29_000002_add_direct_labor_and_unit_cost_to_work_center_calculations_table.php`](file:///c:/xampp/htdocs/wm-product-saas/database/migrations/2026_10_29_000002_add_direct_labor_and_unit_cost_to_work_center_calculations_table.php).
- Updated [`app/Domains/Production/Models/WorkCenterCalculation.php`](file:///c:/xampp/htdocs/wm-product-saas/app/Domains/Production/Models/WorkCenterCalculation.php) `$fillable` and `$casts`.
- Updated [`app/Domains/Production/Services/WorkCenterCalculationService.php`](file:///c:/xampp/htdocs/wm-product-saas/app/Domains/Production/Services/WorkCenterCalculationService.php) with average capacity across operating window, multi-WC direct staff allocation, flat overhead, and compatibility aliases.

---

### ISSUE-19: Section Calculation Modal (Audit Metrics Tab): Add Explanatory Info Popovers `(i)` for All 52 Audit Metrics (Source / Formula in Plain Language)
* **Date Logged:** 2026-10-29
* **Status:** **Applied**
* **Reported By:** User
* **Area:** `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php` (Audit Metrics Tab)

#### 1. Issue Description
In the Section Calculation Breakdown Modal (`Audit Metrics` tab), the 52-metric table lists metric names and computed values across 7 categories. Users cannot see how each individual metric is derived, whether it is directly collected from inputs or calculated via a formula, leading to ambiguity during audits.

#### 2. Agreed Solution
1. **Interactive Info Icon `(i)`**: Add an info icon next to every metric name in the "METRIC NAME" column (matching the UI popover pattern in Photo 3).
2. **Hover Popover Content**:
   - **For Calculated Metrics**: Show the exact mathematical formula in plain language (e.g., `Total Daily Operating Cost / Total Daily Production Capacity`, `Base Daily Salary / Shift Clock Hours`).
   - **For Collected / Sourced Metrics**: Show a clear 1-line plain English description of where the data originates (e.g., *"Assigned shift schedule selected in section profile"*, *"Rated nameplate speed entered in machine profile"*, *"Base monthly salary from HRMS employee record"*), completely avoiding raw database column names (like `energy_rate_per_unit` or `production_machines.capacity`).
3. **Structured Mapping Dictionary**: Build a clean helper dictionary in the template or service mapping each of the 52 metric keys to its friendly source explanation or formula.

#### 3. Implemented Changes
- Added `$auditMetricsMeta` definition mapping all 52 metrics to clean sources, mathematical formulas, and plain-English explanations in [`resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php`](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php).
- Rendered interactive `(i)` info icons with Bootstrap hover popovers styled via `.metric-audit-popover` in [`resources/views/modules/production/work-centers/show.blade.php`](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/work-centers/show.blade.php).

---

### ISSUE-20: Work Center Hero Panel (3-Column Layout): Redesign to Display Inline Section Capacity/Cost Contributions & Direct Personnel Rates (Zero-Hover UI)
* **Date Logged:** 2026-10-29
* **Status:** **Applied**
* **Reported By:** User
* **Area:** `resources/views/modules/production/work-centers/show.blade.php` (Lines 463–640)

#### 1. Issue Description
In the top hero section of the Work Center view (`show.blade.php`), high-level numbers are displayed without breakdown details. Direct personnel are tucked behind a tiny popover button showing only employee names without their hourly cost or daily allocation. Capacity and costs show total numbers with no inline visibility into individual section contributions, forcing users to click or hover across multiple screens.

#### 2. Agreed Solution
Implement a scalable hybrid architecture for the 3-column Hero profile panel:
1. **Column 1 (Identity & Direct Assigned Staff Card)**:
   - Preserves the clean profile layout with identity parameters.
   - Includes a dedicated card for `Direct Assigned Staff (count)` showing the total allocated daily salary badge (`$XX.XX/day`).
   - Hover popover reveals employee code, full name, role/designation, allocated daily salary, and hourly wage rate (without shift clutter).
2. **Column 2 (Capacity & Parameter Metrics - Hybrid Hover & Direct Cards)**:
   - **Capacity / Hour Card**: Shows major KPI (`Avg / hr` speed). Hovering displays the styled breakdown card showing each section's contribution.
   - **Daily Production Capacity Card**: Shows major KPI (`Total Output`). Hovering displays the styled breakdown card showing each section's daily units.
   - **Operational Schedule Card**: Kept directly on the page showing Operating Window (`XX.XX hrs / day`) and Production Calendar.
3. **Column 3 (Operating Cost Structure - Hybrid Hover & Direct Cards)**:
   - **Daily Labor Cost Card**: Shows major KPI (`Total Daily Labor` & `Avg $/hr`). Hovering displays the styled breakdown card showing each section's labor and direct personnel costs.
   - **Daily Machinery Expense Card**: Shows major KPI (`Total Daily Machinery` & `Avg $/hr`). Hovering displays each section's machinery expense.
   - **Facility Overhead Card**: Kept directly on the page showing flat facility daily expense.
   - **Total Daily Operating Cost Card**: Kept directly on the page with effective hourly cost and green unit cost badge (`$XX.XX / unit`).
4. **Click Interactivity**: Clicking on any of the capacity or cost cards smoothly opens `#workCenterCalculationModal`.

#### 3. Implemented Changes
- Implemented the hybrid architecture in [`resources/views/modules/production/work-centers/show.blade.php`](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/work-centers/show.blade.php).
- Configured hover popovers (`.wc-breakdown-popover`) containing rich card layouts for hourly capacity, daily output, labor costs, and machine expenses.
- Scaled hover popovers to 80%–90% of column width (`width: 350px !important; max-width: 360px !important;`) and reduced vertical spacing (`py-1.5` header, `p-2.5` body, `py-1` rows) for a compact, balanced feel.
- Column 1: Configured compact profile layout (`gap-2 mt-2.5`), live direct staff labor calculation, and moved the "Purpose & Operational Notes" description card directly into the lower empty space of Column 1 below Direct Assigned Staff, eliminating the extra full-width section below the hero grid.
- Columns 2 & 3: Retain snapshot-driven architecture reading directly from `$latestSnapshot` columns without JSON overhead.
- Total Daily Cost Card: Corrected header to `TOTAL DAILY COST` with `text-nowrap` on title and effective hourly badge, eliminating awkward two-line text wrapping.

---

### ISSUE-21: Work Center Calculation Modal (Timeline Tab): Replace Empty 1,440-Minute Intervals with 24-Hour Visual Coverage Bar & Shift x Section Operating Matrix
* **Date Logged:** 2026-10-30
* **Status:** **Applied**
* **Reported By:** User
* **Area:** `resources/views/modules/production/work-centers/show.blade.php` (`#wc-intervals-pane`) & `WorkCenterCalculationService.php`

#### 1. Issue Description & Context
In the Work Center Level Calculation Breakdown Modal, Tab 1 ("Timeline Intervals") currently displays an empty table stating *"No timeline intervals calculated"*. Unlike SectionCalculationService (which performs minute-by-minute discrete timeline slicing within a single section), WorkCenterCalculationService aggregates cross-section scalar totals and union operating windows, leaving this table blank.
Furthermore, displaying granular shift-level resource and worker rosters at the Work Center level is redundant because full machine and worker details already exist in each section's own calculation breakdown modal.

#### 2. Agreed Architectural Solution
Implement a practical, executive-level 2D operational matrix paired with a top visual coverage bar:
1. **24-Hour Visual Coverage Bar (Mini-Gantt)**:
   - Positioned at the top of the tab, displaying a 24-hour horizontal track (`00:00 – 24:00`) for each contributing section.
   - Visually differentiates active running windows from off-hours (idle) time (e.g., CNC Milling running 24/7 across all 3 shifts, vs. Robotic Welding running 16.30 hrs with 7.5 hrs idle).
   - Shows the peak concurrency window (`06:00 – 22:30`) where all sections run simultaneously.
2. **2D Shift &times; Section Operating Matrix**:
   - **Shifts as Rows**: Each plant shift (Morning Shift `06:00–14:30`, Evening Shift `14:00–22:30/23:00`, Night Shift `22:00–06:30`) with time window and duration.
   - **Sections as Column Headers**: Each column represents one contributing section (e.g. `[SEC-CNC-01]`, `[SEC-WLD-02]`, `[SF]`).
   - **Intersection Cells**:
     - When Active: Displays `Active` (or `Solo Running`) badge, shift output units, shift operating cost, and unit cost.
     - When Inactive: Displays `Off-Hours (Idle)` with `$0 cost • 0 output`.
   - **Rightmost Column (Shift Combined)**: Sums combined output and combined operating cost for all sections running in that shift.
3. **Table Footers (`<tfoot>`)**:
   - Sums each section vertically across the day: Total Daily Units, Total Daily Cost, and Unit Cost.
   - Grand totals for all contributing sections: `7,171.0 units` and `$164,678.87`.
4. **Reconciliation Card**:
   - Omitted from Tab 1 to avoid redundancy, as comprehensive 4-card reconciliation is hosted in Tab 2 (Contributing Sections).

#### 3. Implemented Changes
- Added `buildTimelineMatrix()` in [`app/Domains/Production/Services/WorkCenterCalculationService.php`](file:///c:/xampp/htdocs/wm-product-saas/app/Domains/Production/Services/WorkCenterCalculationService.php) to build the structured Shift $\times$ Section matrix and store it in `calculation_details['timeline_matrix']`.
- Updated [`resources/views/modules/production/work-centers/show.blade.php`](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/work-centers/show.blade.php):
  - Tab title changed to `Timeline Matrix (N Shifts)`.
  - **Modernized 24-Hour Visual Coverage Bar (Mini-Gantt)**:
    - **Shift Code Only**: Segments now display shift codes only (`SH-MORN`, `SH-EVN`, `SH-NGHT`, `MRN`) instead of full strings, preventing clipping on narrow overnight wrap segments. Full details (name, hours, window) are available via tooltip.
    - **Industrial UI Upgrade**: Multi-tone gradient color styling based on shift time (Morning blue, Evening indigo, Night purple), crisp white segment divider borders, subtle inner shadows, 7 vertical hour gridlines (at 03:00, 06:00, 09:00, 12:00, 15:00, 18:00, 21:00), and an authentic diagonal idle hatch pattern for off-hours intervals.
  - **Space-Optimized Shift &times; Section Production & Cost Matrix**:
    - **Section Codes Only**: Column headers now display section codes only in soft primary badges (`[SEC-CNC-01]`, `[SEC-WLD-02]`, `[SF]`) with full names in hover tooltips, eliminating awkward multi-line text truncation and saving horizontal space.
    - **Shift Codes Only**: Operating shift rows display shift codes prominently in soft dark badges (`SH-MORN`, `MRN`) alongside the time window and duration, saving significant vertical and horizontal real estate.
  - Added table totals footer (`<tfoot>`) summing vertical section outputs and costs. (The bottom reconciliation card was removed per user request, as full labor & expense reconciliation is presented in comprehensive detail in Tab 2 Contributing Sections).
  - Included a fallback parser to build the matrix and tracks on the fly for existing snapshots.

---

### ISSUE-22: Work Center Calculation Modal (Contributing Sections Tab): Add Daily/Hourly Metric Pairs, Unit Cost, Drill-Down Links, Totals Footer, and Labor Reconciliation Strip
* **Date Logged:** 2026-10-30
* **Status:** **Applied**
* **Reported By:** User
* **Area:** `resources/views/modules/production/work-centers/show.blade.php` (`#wc-sections-pane`) & `WorkCenterCalculationService.php`

#### 1. Issue Description & Context
In the Work Center Level Calculation Breakdown Modal, Tab 2 ("Contributing Sections") displays a roll-up table with columns for Code, Section Name, Operating Hours, Daily Capacity, Daily Labor Cost, and Daily Machine Cost.
The table currently ends abruptly on the last section row with no table footer (`<tfoot>`), lacks total operating costs and cost per unit for each section, omits average hourly capacity and labor rate metrics present in the section details header, and offers no reconciliation explanation for the difference between total section labor ($163,826.67) and total Work Center labor ($249,160.00).

#### 2. Agreed Architectural Solution
Refactor `#wc-sections-pane` into an audit-grade section roll-up table:
1. **Paired Daily & Hourly Metric Columns**:
   - **Capacity**: Daily Capacity (`3,760.0 units`) paired with sub-line Average Hourly Capacity (`156.7 u/h`).
   - **Labor Cost**: Daily Labor Cost (`$9,056.67`) paired with sub-line Average Labor Rate (`$377.36/hr`).
   - **Machinery Cost**: Daily Machine Expense (`$504.00`) paired with sub-line Machine Rate (`$21.00/hr`).
   - **Total Operating Cost**: Total daily cost (`$9,560.67`) paired with sub-line Effective Rate (`$398.36/hr`).
   - **Cost / Unit Badge**: Direct efficiency metric per section (`$2.54 / u`, `$23.27 / u`, `$51.32 / u`).
2. **Interactive Section Audit Drill-Down (`↗`)**:
   - Quick-action button on each row allowing users/auditors to open `#sectionCalculationModal_{id}` to inspect that section's complete 52-metric breakdown.
3. **Table Footer Totals (`<tfoot>`)**:
   - Sums all contributing sections: Total Capacity `7,171.0 units` (Avg `298.8 u/h`), Sections Labor `$163,826.67`, Sections Machinery `$852.20`, and Total Sections Cost `$164,678.87` (Avg `$22.96 / u`).
4. **Labor & Work Center Expense Reconciliation Strip**:
   - Placed directly below the table:
     $$\text{Section Labor (\$163,826.67)} + \text{Direct WC Staff (\$85,333.33)} = \mathbf{\$249,160.00}\text{ Total Labor}$$
     $$+ \text{ Machinery \& Overhead (\$852.20)} = \mathbf{\$250,012.20}\text{ Work Center Total Cost}$$

#### 3. Implemented Changes
- Refactored [`#wc-sections-pane`](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/work-centers/show.blade.php):
  - **Summary First Hierarchy**: Reordered pane elements so the executive summary **Contributing Sections Labor & Expense Reconciliation** card sits prominently at the top (`mb-3`), followed directly by the detailed **Section Capacity & Expense Roll-Up** itemized table.
  - Formatted each cell with paired daily totals and average hourly speeds/burn rates (Capacity, Labor, Machine, Total Cost).
  - Added formatted Cost / Unit efficiency badge (`$X.XX / u`).
  - Added direct drill-down button (`↗`) launching that section's 52-metric breakdown modal.
  - Added comprehensive `<tfoot>` row summing all section capacities and costs.
  - 4-card Labor & Expense Reconciliation Strip breaking down Section Labor vs Direct WC Personnel vs Machinery/Overhead.

---

### ISSUE-23: Work Center Calculation Modal (Direct Personnel & Overhead Tab): Multi-WC Allocation Transparency, Base Salary Breakdown, and Footer Totals
* **Date Logged:** 2026-10-30
* **Status:** **Applied**
* **Reported By:** User
* **Area:** `resources/views/modules/production/work-centers/show.blade.php` (`#wc-direct-pane`) & `WorkCenterCalculationService.php`

#### 1. Issue Description & Context
In the Work Center Level Calculation Breakdown Modal, Tab 3 ("Direct Personnel & Overhead") lists unassigned station staff and flat overhead.
Currently, for employees assigned across multiple Work Centers (e.g. Rohan Kulkarni, assigned across 2 Work Centers), the table displays Monthly CTC `$720,000.00` and Daily Salary `$12,000.00` under a column header labeled `DAILY SALARY (CTC / 30)`. Without an explicit allocation factor column, auditors can suspect a division error (`$720,000 / 30 = $24,000`, not `$12,000`). Additionally, the table lacks a summary footer (`<tfoot>`) summing total monthly CTC and daily allocated salaries, and does not show the station fixed cost impact per production unit.

#### 2. Agreed Architectural Solution
Refactor `#wc-direct-pane` to provide transparent multi-station allocation accounting:
1. **Dedicated WC Allocation Column**:
   - Displays clear allocation badges: e.g. `<span class="badge bg-soft-warning">50% (1 of 2 WCs)</span>` vs. `<span class="badge bg-soft-success">100% Dedicated</span>`.
   - Sub-line under Monthly CTC displays unallocated base daily salary (e.g., `Base: $24,000.00/d`), making the math $24,000 \times 50\% = \$12,000.00$ immediately transparent.
2. **Table Footer Totals (`<tfoot>`)**:
   - Sums all direct station staff: Total Monthly HRMS CTC (`$2,920,000.00 / mo`), effective allocation percentage (`~87.4%`), and Total Daily Allocated Salary (`$85,333.33 / day` &bull; `$3,555.56 / hr` over 24h).
3. **Station-Level Fixed Burden Callout**:
   - Executive callout summarizing total station fixed burden (Direct Staff $85,333.33 + Overhead $0.00 = $85,333.33/day) and its direct unit cost contribution:
     $$\mathbf{+\$11.90\text{ / unit}}\text{ across 7,171.0 total daily units}$$

#### 3. Implemented Changes
- Refactored [`#wc-direct-pane`](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/work-centers/show.blade.php):
  - **Summary First Hierarchy**: Reordered pane elements so the executive summary **Direct Station Fixed Burden** callout sits at the top (`mb-3`), followed directly by the detailed **Direct Work Center Staff (Unassigned to any Section)** personnel table (`mb-3`), and concluding with the **Work Center Flat Overhead Integration** configuration card.
  - Fixed snapshot key consumption: Read `$dst['active_wc_count'] ?? $dst['total_active_work_centers'] ?? 1` and `$dst['allocation_percentage'] ?? (100 / $activeWcCount)` so employees assigned across multiple Work Centers (e.g. Rohan Kulkarni and Neha Joshi, assigned to 2 WCs) accurately display `<span class="badge bg-soft-warning">50% (1 of 2 WCs)</span>` and subtitle `Assigned across 2 Work Centers` rather than incorrectly defaulting to `100% Dedicated`.
  - Updated [`WorkCenterCalculationService.php`](file:///c:/xampp/htdocs/wm-product-saas/app/Domains/Production/Services/WorkCenterCalculationService.php) to store canonical `total_active_work_centers` and `allocation_percentage` keys directly in the calculation snapshot JSON.
  - Added dedicated WC Allocation column with color-coded badges (`50% (1 of 2 WCs)` vs `100% Dedicated`).
  - Added sub-line under Monthly CTC displaying base daily salary (`Base: $XX.XX/d`).
  - Added summary `<tfoot>` row summing Monthly CTC, effective allocation percentage, and daily allocated salary with 24h hourly burn.
  - Added station-level fixed burden callout showing fixed daily burden and unit cost impact (`+$11.90 / unit`).

---

### ISSUE-24: Work Center Calculation Modal: Replace Raw Snapshot JSON Tab with Structured Work Center Audit Metrics & Drill-Down Traceability to Section Audit Fields
* **Date Logged:** 2026-10-30
* **Status:** **Applied**
* **Reported By:** User
* **Area:** `resources/views/modules/production/work-centers/show.blade.php` (`#wc-audit-pane`) & `section-calculation-modal.blade.php`

#### 1. Issue Description & User Requirement
In the Work Center Level Calculation Breakdown Modal, Tab 5 previously displayed "Raw Snapshot JSON" in a dark code block.
Raw JSON dumps are difficult for non-technical operations auditors to parse. The user decided to remove the Raw JSON tab completely and replace it with a structured **Work Center Audit Metrics** view copied directly from the style and theme of the 52-metric Section Audit Metrics table in `section-calculation-modal.blade.php`, including direct traceability drill-down links to section audit fields.

#### 2. Agreed Architectural Direction
1. **Full 47-Metric Audit Table Across 8 Categories**:
   - Mirror the sticky-header, striped, audit-grade table styling from `section-calculation-modal.blade.php`.
   - Organize all station metrics into 8 distinct operational categories:
     1. Operating Timeline & Plant Schedule (Metrics 1–7)
     2. Work Center Production Capacity Roll-Up (Metrics 8–12)
     3. Contributing Sections Labor Roll-Up (Metrics 13–18)
     4. Direct Work Center Personnel (Station-Level Allocation) (Metrics 19–26)
     5. Total Blended Labor & Workforce Cost (Metrics 27–31)
     6. Machinery & Physical Asset Operating Expenses (Metrics 32–36)
     7. Facility Overhead & Station Fixed Burden (Metrics 37–41)
     8. Total Financial & Manufacturing Cost Summary (Metrics 42–47)
2. **Interactive Info Popovers on Every Metric**:
   - Add hover/focus Bootstrap popovers (`feather-info` triggers with `.metric-audit-popover` custom class) detailing:
     - Type: `Calculated` (`bg-soft-primary`) vs. `Collected` (`bg-soft-success`)
     - Data Source Registry
     - Plain-language mathematical formula / logic in code block
     - Comprehensive operational description
3. **Traceability Links to Section Audit Fields**:
   - For all section roll-up metrics (Active Sections, Total Daily Capacity, Section Labor, Machinery Expense, Financial Summary), provide clickable drill-down badges `[SEC-CNC-01 ↗]`, `[SEC-WLD-02 ↗]`, `[SF ↗]` that directly trigger each section's complete 52-metric breakdown modal (`#sectionCalculationModal_{id}`).
4. **Complete Removal of Raw JSON**:
   - The raw JSON payload block and its toggle button have been completely eliminated from the modal view, providing a 100% clean, executive audit table.

#### 3. Implemented Changes
- Updated tab header in [`show.blade.php`](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/work-centers/show.blade.php#L3117): Replaced `Raw Snapshot JSON` with `<i class="feather-check-circle text-success"></i> Audit Metrics (47)`.
- Replaced `#wc-json-pane` with `#wc-audit-pane` in [`show.blade.php`](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/work-centers/show.blade.php#L3774):
  - Completely removed the raw JSON pre block and any JSON toggle buttons.
  - Added supporting computation block aggregating machine power draws, manual capacities, shift counts, peak matrix burn rates, and multi-station employee allocations.
  - Defined `$wcAuditMetricsMeta` cataloging all 47 Work Center audit metrics with exact names, formulas, sources, and explanations.
  - Implemented `$renderWcAuditMetricRow` generating sticky table rows with info popovers and section drill-down links.
  - Added `shown.bs.tab` event listener ensuring popovers are initialized when switching between modal tabs.

---

### ISSUE-25: Work Center Calculation Modal (Top 6 KPI Summary Cards): Restructure into 3x2 Grid Aligned with Section Details (Adding Cost Per Unit & Operating Hours, Combining Daily/Hourly Capacity)
* **Date Logged:** 2026-10-30
* **Status:** **Applied**
* **Reported By:** User
* **Area:** `resources/views/modules/production/work-centers/show.blade.php` (Lines 2915–3025)

#### 1. Issue Description & Context
In the Work Center Level Calculation Breakdown Modal, the top header previously displayed 6 narrow cards in a single row (`col-md-2`).
Daily Capacity and Average Capacity/hr occupied two separate cards, while **Cost Per Unit** (one of the most critical manufacturing KPIs, currently `$34.86 / unit`) was completely missing from the top summary cards. Furthermore, the Work Center unique operating hours (`24.00 hrs • 06:00 – 06:00`) only existed as tiny subtitle text rather than a primary operational KPI. The narrow 6-card row caused text crowding and lacked the visual hierarchy and category badges present in the Section Details modal.

#### 2. Agreed Architectural Solution
Restructure the top KPI header into a spacious 3 &times; 2 grid (6 cards total) mirroring the Section Details modal architecture:
1. **Card 1: Daily Capacity (Output Badge - Green)**:
   - Primary metric: `7,171.0 units/day`.
   - Sub-metrics: `Avg: 298.8 u/h` &bull; `Peak: 360.0 u/h` (calculated dynamically from shift matrix).
2. **Card 2: Daily Operating Cost (Opex Badge - Amber)**:
   - Primary metric: `$250,012.20`.
   - Sub-metrics: `Avg: $10,417.18/hr` &bull; `Window: 24.00 hrs`.
3. **Card 3: Cost Per Unit (Efficiency Badge - Blue)**:
   - Primary metric: `$34.86 / unit`.
   - Sub-metrics: `Sections: $22.96` &bull; `Burden: +$11.90`.
4. **Card 4: Operating Hours (Schedule Badge - Purple/Green)**:
   - Primary metric: `24.00 hrs/day`.
   - Sub-metrics: `Window: 06:00 – 06:00` &bull; `3 Active Sections` (`Full 24/7` badge).
5. **Card 5: Machinery Expense (Asset Opex Badge - Orange)**:
   - Primary metric: `$852.20 / day`.
   - Sub-metrics: `Rate: $35.51/hr` &bull; `Share: 0.3%` of total daily opex.
6. **Card 6: Total Labor Cost (Blended Labor Badge - Cyan)**:
   - Primary metric: `$249,160.00 / day`.
   - Sub-metrics: `Sections: $163.8k` &bull; `Direct Staff: $85.3k`.

#### 3. Implemented Changes
- Refactored the top summary cards grid in [`resources/views/modules/production/work-centers/show.blade.php`](file:///c:/xampp/htdocs/wm-product-saas/resources/views/modules/production/work-centers/show.blade.php):
  - Converted the layout from 6 single-row narrow cards (`col-md-2`) to a 3 &times; 2 grid (`col-md-4 col-sm-6`), aligning typography, card heights (`d-flex flex-column justify-content-between`), and category badges with the Section Calculation modal.
  - Added peak hourly capacity calculation (`$wcPeakHourlyCap`) across shift intervals.
  - Added operational window string derivation (`$wcOpWindowStr`) from shifts: Correctly recognizes 24.00 continuous hours (e.g. `06:00 – 06:00`), resolving the previous erroneous `06:00 – 23:00` display caused by naive min/max string comparisons that dropped overnight wrap shifts.
  - Added direct calculation of sections cost per unit (`$wcSecCostPerUnit`) and direct burden per unit (`$stationFixedBurdenPerUnit`) for Card 3.
  - Implemented dynamic badges for Operating Hours (`Full 24/7` vs `N Shifts`).
  - Added percentage share calculation for Machinery Expense (`Share: X.X%`).
  - Added labor reconciliation sub-line in Card 6 breaking down Sections Labor vs Direct Station Staff.

#### 4. Impacted Files
* `resources/views/modules/production/work-centers/show.blade.php`
* `docs/calculation_audit_issues_tracker.md`

---

### ISSUE-26: Work Center Activity & Audit Logs Table Rework (Granular Per-Snapshot Schema, Explicit `snapshot_id`, Simplified Descriptions & Direct Historical Drill-Down)
* **Date Logged:** 2026-10-30
* **Status:** **Applied**
* **Reported By:** User
* **Area:** Database Schema (`production_work_center_activity_logs`), `WorkCenterActivityLog.php`, `WorkCenterActivityLogger.php`, `show.blade.php` (Tab 4: Activity & Audit History)

#### 1. Issue Description & User Requirements
The user reviewed the Activity & Audit History table in the Work Center view and clarified the exact operational logging requirements:
1. **Primary Purpose**: Log completion of a calculation each time a snapshot calculation finishes, capturing when it happened (timestamp), which user triggered it, and the resulting snapshot ID.
2. **Granular Per-Snapshot Logging Flow**:
   - When multiple sections and the Work Center are recalculated (e.g., 3 sections modified):
     * The system must record **one log entry per section calculation** indicating that specific section's snapshot calculation with its unique `snapshot_id`.
     * Then, upon completing the Work Center rollup calculation, record **one single log entry for the Work Center** with its unique `snapshot_id`.
     * If 3 sections change: 3 section records + 1 Work Center record = 4 records in sequence sharing the same calculation batch.
   - If only the Work Center was calculated (e.g., direct personnel or flat overhead changed without section alterations):
     * Record **1 single log entry for the Work Center** with its `snapshot_id`.
3. **Simplified Description Format**:
   - Keep descriptions clean and human-readable, storing the entity name and code:
     * Section record: `"{section_name} [{section_code}] snapshot #{snapshot_id} calculated"`
     * Work Center record: `"{work_center_name} [{work_center_code}] snapshot #{snapshot_id} calculated"`
4. **Historical Snapshot Access & Traceability**:
   - The user must be able to view the log and, directly from each log row, click a button to inspect that snapshot:
     * Section log row &rarr; Quick drill-down button `[View Snapshot #ID ↗]` opening that section's audit breakdown.
     * Work Center log row &rarr; Quick drill-down button `[View Snapshot #ID ↗]` opening the Work Center audit modal.
5. **Database Table Evaluation & Cleanup**:
   - The existing table `production_work_center_activity_logs` contained polymorphic developer-centric column names (`entity_type`, `entity_id`, `event_type`) and a redundant JSON `properties` column.
   - The template also had a display bug where `$log->action_type` was referenced instead of `$log->event_type`, leaving the "ACTION TYPE" badge completely blank.

#### 2. Agreed Architectural Solution
1. **Database Migration (`rework_production_work_center_activity_logs_table`)**:
   - Add explicit columns:
     - `calculation_type` (`VARCHAR(30)`): `'section'` or `'work_center'`.
     - `snapshot_id` (`BIGINT UNSIGNED`): explicit snapshot ID.
     - `batch_id` (`VARCHAR(64)`, nullable): calculation batch UUID linking all granular section snapshots to the parent rollup snapshot.
   - Backfill existing data (`snapshot_id = entity_id`, `calculation_type = 'section' | 'work_center'`, `batch_id = properties->batch_id`).
   - Safely drop deprecated polymorphic columns (`entity_type`, `entity_id`, `event_type`, `properties`) and outdated indexes.
   - Add optimized indexes on `['tenant_id', 'work_center_id', 'calculation_type']`, `['snapshot_id']`, and `['batch_id']`.
2. **Model Update (`WorkCenterActivityLog.php`)**:
   - Update `$fillable` array to `['tenant_id', 'work_center_id', 'section_id', 'user_id', 'calculation_type', 'snapshot_id', 'batch_id', 'description']`.
   - Add dedicated relationships: `sectionCalculation()`, `workCenterCalculation()`, `workCenter()`, `section()`, and `user()`.
3. **Logger Service Update (`WorkCenterActivityLogger.php`)**:
   - Update `logSectionCalculationCompleted()` and `logWorkCenterCalculationCompleted()` to generate the standardized simple description and write to `calculation_type`, `snapshot_id`, and `batch_id`.
4. **UI Improvements (`show.blade.php`)**:
   - Fix the blank badge: Render clear, styled badges:
     - `Section Calculation` (`bg-soft-success text-success`)
     - `Work Center Rollup` (`bg-soft-primary text-primary`)
   - Add Batch ID tag for grouping multi-section calculation runs.
   - Add direct **`[View Snapshot #ID ↗]`** action button on each row for instantaneous historical inspection.
   - Section Details Modal Header Clean-up: Removed the cluttered inline description string from the modal header subtitle, giving it a dedicated **Purpose & Operational Notes** card in the modal body directly above the 4 KPI metric cards.
   - **Header Button Replacement & "View All Logs" Modal (`#allActivityLogsModal`)**:
     * Replaced `[Inspect Full Snapshot]` button in the Activity & Audit History header with `[View All Logs (N)]`.
     * Main page table maintains clean presentation by displaying the 10 most recent activity logs, with a card footer link (`View all N activity & audit logs →`) if total logs exceed 10.
     * Created `#allActivityLogsModal` (Bootstrap modal-xl, scrollable, sticky table header) presenting the complete, unfiltered audit trail ordered chronologically (newest first) with snapshot inspection quick actions.

#### 3. Impacted Files
* `database/migrations/2026_10_30_000001_rework_production_work_center_activity_logs_table.php`
* `app/Domains/Production/Models/WorkCenterActivityLog.php`
* `app/Domains/Production/Services/WorkCenterActivityLogger.php`
* `resources/views/modules/production/work-centers/show.blade.php`
* `docs/calculation_audit_issues_tracker.md`

---

<!-- Additional issues will be appended below as they are reported -->










