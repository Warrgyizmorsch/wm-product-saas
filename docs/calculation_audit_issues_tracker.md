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
| **ISSUE-02** | Unexpected Auto-Calculation & Audit Log on GET Page Load | **Unsolved / On Hold** (Temp Disabled for Testing — Final Decision Pending) | `section-calculation-modal.blade.php`, `WorkCenterController.php` |
| **ISSUE-03** | Section Details Modal (Machines Tab): Total Operating Cost Shows $0.00/hr When Uncalculated While Capacity Shows Sum | **Applied** | `show.blade.php` |
| **ISSUE-04** | Main Section Row & Capacity Popover: Remove Ad-hoc Database Fallbacks — Strictly Source from Calculation Table | **Applied** | `show.blade.php` |
| **ISSUE-05** | Section Details Modal (Manual Batches Tab): Missing Table Footer / Total Row for Target Rate & Allocated Workforce | **Applied** | `show.blade.php` |
| **ISSUE-06** | Section Details Modal (Cost & Capacity Tab & Top 2 KPIs): Uncalculated Warning & Remove Fallbacks | **Applied** | `show.blade.php` |
| **ISSUE-07** | Section Details Modal: Shifts & Schedule Missing Footer Total & Operating Window KPI Fallback | **Applied** | `show.blade.php` |
| **ISSUE-08** | WorkCenterCalculationService: Fix `operating_hours` typo to `operating_timeline` for Section Shifts Extraction | **Applied** | `WorkCenterCalculationService.php` |
| **ISSUE-09** | Main Section Row: Daily Operating Cost Column Missing Matching Hover Breakdown Popover | **Applied** | `show.blade.php` |
| **ISSUE-10** | Manual Batch Output Dilution (Active Speed Omitted to Prevent Redundancy & Confusion) | **Applied** | `show.blade.php`, popovers & modals |
| **ISSUE-11** | Canonical Keys & UI Consistency (Terminology: Hourly Capacity; Reconciliation Tooltip) | **Applied** | `section-calculation-modal.blade.php`, `SectionCalculationService.php` |
| **ISSUE-12** | Section Calculation Modal: Manual Batches Tab UI Overhaul | **On Hold** (Per user directive pending frontend overhaul guidance) | `section-calculation-modal.blade.php` |
| **ISSUE-13** | Section Calculation Modal: External HRMS Shift Resolution & Warning Badge | **Applied** | `section-calculation-modal.blade.php`, `SectionCalculationService.php` |
| **ISSUE-14** | Format Time/Hours Consistently Across Modals & Views via `format_hours()` (`16.30 hrs` vs raw `.5`) | **Applied** | `section-calculation-modal.blade.php`, `show.blade.php`, `SectionCalculationService.php`, `helpers.php` |
| **ISSUE-15** | Section Calculation Modal: Timeline Intervals Not Generated for Unstaffed / Machine-Only Sections | **Applied** | `SectionCalculationService.php` |
| **ISSUE-16** | Table Column Optimization for Popovers & Cost/Capacity Tab (Dedicated Columns vs JSON) | **Applied** | Migration, `WorkCenterSectionCalculation.php`, `SectionCalculationService.php`, `show.blade.php` |

> **Live Work Center URL:** `http://127.0.0.1:8000/production/work-centers/1`

---

## Detailed Issue Tracking

### ISSUE-01: Ambiguous "Tariff Rate" Label in Section Modal & Machine Views
* **Date Logged:** 2026-10-27
* **Status:** Queued for Batch Implementation
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

### ISSUE-02: Unexpected Auto-Calculation & Audit Log on GET Page Load
* **Date Logged:** 2026-10-27
* **Status:** Unsolved / On Hold (Temporarily disabled for testing — final decision pending)
* **Reported By:** User
* **Area:** View Rendering & Calculation Lifecycle (`section-calculation-modal.blade.php`)

> **Testing Status Note:** Auto-calculation was temporarily disabled/commented out during audit testing (in `section-calculation-modal.blade.php` and `WorkCenterController.php`) to keep GET requests read-only. However, this issue is marked as **Unsolved / On Hold** because this was only a temporary testing measure; the final architectural decision on whether and how auto-calculation should behave across the application lifecycle will be made later.

#### 1. Issue Description
When a user opened or refreshed the Work Center page (`GET /production/work-centers/1`) right after clearing calculation tables or running a seeder, new entries appeared in the Activity & Audit History log saying:
`Section calculation completed for CNC Milling & High-Speed Machining... (manual)`
even though the user had NOT clicked "Recalculate" and before any terminal command was authorized.

#### 2. Reason / Root Cause
In `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php` (lines 22-25), the Blade template contains an inline mutation fallback:
```blade
@php
    $secCalc = $section->latestCalculation;
    if (!$secCalc) {
        $secCalc = app(\App\Domains\Production\Services\SectionCalculationService::class)->calculateAndSave($section, 'manual');
    }
@endphp
```
Whenever `$section->latestCalculation` is null, simply rendering the page executes `calculateAndSave($section, 'manual')`, which inserts records into `production_work_center_section_calculations` and immediately triggers `WorkCenterActivityLogger->logSectionCalculationCompleted()`. This creates unexpected audit entries and performs database writes during a read-only HTTP GET request.

#### 3. Agreed Solution
1. Remove `calculateAndSave()` from the Blade view. View templates must remain strictly read-only.
2. If `$section->latestCalculation` is null, initialize `$secCalc = null` and display a clean "Not Calculated Yet — Please click Recalculate" badge/empty-state in the modal instead of running an unprompted calculation.
3. Calculations must only be executed through explicit user actions (clicking the "Recalculate" button) or controlled background schedulers (countdown timer / artisan commands).

#### 4. Files to Modify (During Batch)
* `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php`

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
* **Status:** Queued for Batch Implementation
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
* **Status:** Queued for Batch Implementation
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

#### 4. Impacted Files
* `resources/views/modules/production/work-centers/show.blade.php`

---

### ISSUE-07: Section Details Modal: Shifts & Schedule Missing Footer Total & Operating Window KPI Fallback
* **Date Logged:** 2026-10-27
* **Status:** Queued for Batch Implementation
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
* **Status:** Queued for Batch Implementation
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
* **Status:** Queued for Batch Implementation
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

### ISSUE-12: Section Calculation Modal: Manual Batches Tab UI Overhaul
* **Date Logged:** 2026-10-28
* **Status:** **On Hold** *(Per user directive: User requested to focus on consistency first; user will guide full frontend changes and redesign later)*
* **Reported By:** User (via Manual Batches snapshot screenshot)
* **Area:** Section Calculation Breakdown Modal (`Manual Batches` tab)

#### 1. Issue Description
When viewing the **Manual Batches (1)** tab in the Section Calculation Breakdown Modal:
1. **Missing Row Values**:
   - **`Hourly Speed`** displayed **`0.0 u/h`**, despite the calculation formula right beside it showing `Batch speed (55.00 units/hr)` and the top summary bar showing `Peak Concurrent Output: 55.0 units/hr`.
   - **`Shift Eff. Hours`** displayed **`0.0 hrs`**, despite the calculation formula showing `Shift effective hours (8.00 hrs)`.
   - **`Daily Output`** displayed **`0.0 units`**, despite the calculation formula showing `= 440.00 units/day` and the footer displaying `440.0 units/day`.
   - **Subtitle under batch name `gello`** displayed **`0 internal emps`**, despite internal employee `Rajesh Sharma` being assigned to this batch.
2. **Hidden Workforce / Missing Contractor Information**:
   - The batch `gello` has **2 external contractors** allocated (`2x Grade B - Skilled Welder & Technician`), stored in the calculation snapshot under `contractor_allocations`.
   - The modal table has **no display, column, or pill for contractor allocations**, completely hiding the 2 external workers performing manual production.
   - The subtitle only displays `<small>{{ count(...) }} internal emps</small>`, hiding specific employee names (`Rajesh Sharma`).
3. **Redundant / Space-Wasting Column (`Calculation Formula`)**:
   - The table dedicates **27% of its width** to a `Calculation Formula (English)` text column that prints:
     `Batch speed (55.00 units/hr) x Shift effective hours (8.00 hrs) = 440.00 units/day`
   - All three variables in this string already exist in the adjacent columns in the exact same row (`Hourly Speed`, `Shift Eff. Hours`, `Daily Output`).
   - This formula column consumes significant table real estate while critical workforce information (assigned employees and contractor allocations) is squeezed into an incomplete subtitle.
4. **Header vs. Footer Metric Semantic Mismatch**:
   - The column header is **`HOURLY SPEED`**, showing the active batch production rate (**`55.0 u/h`** during its 8-hour shift).
   - Directly underneath in the table footer, it displays **`Avg 26.7 u/h`** (the 24-hour section-wide time-weighted average diluted across the entire 16.5-hour section operating window).
   - Having a footer of `Avg 26.7 u/h` under a single row of `55.0 u/h` without clear distinguishing labels makes users perceive the footer as mathematically incorrect.
5. **Empty Footer Cells**:
   - The footer leaves empty `<td></td>` cells under `Shift Eff. Hours` and `Calculation Formula`, missing an opportunity to show shift operating hours or total workforce summary.

#### 2. Root Cause Analysis
1. **Field Name Mismatches in Snapshot Data (Addressed in ISSUE-11)**:
   - Blade looked for `$b['capacity_per_hour']`, `$b['shift_effective_hours']`, `$b['daily_output']`, and `$b['assigned_employees']`.
   - The snapshot stored `$b['cap_hr']`, `$b['eff_hrs']`, `$b['daily_cap']`, and `$b['team_members']`.
2. **UI Design Omission for Contractors**:
   - In manual production, batches can be staffed by internal employees, external contractors, or both.
   - The calculation service correctly collects and stores `$allocSummary` (e.g. `['2x Grade B - Skilled Welder & Technician']`) into `$item['contractor_allocations']`.
   - However, the Blade template was never coded to render `$b['contractor_allocations']`, rendering contractors completely invisible in this view.
3. **Formula Text Redundancy**:
   - The 6-column layout included `Calculation Formula (English)` as a dedicated column, which duplicates arithmetic and pushes out useful resource details.

#### 3. Proposed Solutions (Awaiting User Acceptance & Confirmation)

##### Option A: Modern High-Density Table with Dedicated Workforce Column (Recommended)
1. **Replace the redundant `Calculation Formula` column with a `Workforce & Team Allocation` column**:
   - Instead of dedicating 27% width to repeating arithmetic, display the active workforce:
     - **Internal Staff**: `<span class="badge bg-soft-info text-info"><i class="feather-user me-1"></i>Rajesh Sharma</span>`
     - **External Contractors**: `<span class="badge bg-soft-primary text-primary"><i class="feather-users me-1"></i>2x Grade B (Welder)</span>`
   - Move the formula string to a hover tooltip on the `Daily Output` number (e.g., *Title: "55.0 u/h × 8.0 hrs = 440.0 units"*).
2. **Clarify Batch Shift Speed vs. Section Average in Header & Footer**:
   - Header: **`Batch Shift Speed`** (or `Active Speed`).
   - Row: **`55.0 u/h`** with subtitle `(during Morning Shift)`.
   - Footer: **`Avg Section Output: 26.7 u/h`** with tooltip: *"Section-weighted average: 440 units / 16.5 section operating hours"*.
3. **Populate Footer Hours**:
   - Under `Shift Eff. Hours`, display the shift duration: `8.0 hrs`.

##### Option B: Keep Formula Column, but Add Contractor Pills under Batch Name
1. Keep the `Calculation Formula (English)` column as-is.
2. In the first column under Batch Name, display both internal employee and contractor summary badges:
   - `<small class="text-info">1 Internal (Rajesh Sharma)</small> &bull; <small class="text-primary">2x Grade B Contractor</small>`.
3. Add explanatory tooltip to footer `Avg 26.7 u/h`.

#### 4. Impacted Files (Pending Confirmation)
* `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php`

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

<!-- Additional issues will be appended below as they are reported -->





