# Work Center & Section Calculation Decisions & Schema Plan

**Document File:** `docs/calculation_rework_decisions.md`  
**Last Updated:** 2026-10-26  
**Status:** In Progress (Tracking Decisions, Schema Updates, and Action Items)

---

## 1. Questions & Decisions Log

### Q1: Monthly Salary Divisor for Internal Staff
* **Question:** HRMS stores employee monthly salary (`current_salary`). To compute daily labor cost, should we divide by 30 days or 26 days (working days excluding weekends)?
* **User Decision:** **Divide by 30 days.**
* **Reason:** Holidays and weekends are paid by the company as part of monthly CTC, so dividing by 30 calendar days accurately reflects the company's daily labor liability.
* **Agreed Formula:**
  - Daily Salary = HRMS Net Monthly CTC / 30
  - Hourly Labor Rate = Daily Salary / Employee Shift Total Duration (Total Clock Hours, including breaks, since breaks are paid by the company)

---

### Q2: Machine Operating Hours & Shift Assignment
* **Question:** Do all active machines in a section run continuously across the section's active shifts, or should machines be assigned to specific shifts?
* **User Decision:** **Option A (Run across all active section shifts).**
* **Reason:** All active machines in a section are available and operational throughout the section's full operating window. No need to assign individual machines to specific shifts.
* **Schema Impact:** Do NOT add `shift_id` to `production_machines`. Keep table structure clean.
* **Agreed Formula:**
  - Daily Machine Capacity = (Sum of Active Machine Capacity) × Section Unique Operating Hours
  - Daily Machine Cost = (Sum of Active Machine Hourly Opex) × Section Unique Operating Hours

---

### Q3: Machine Output & Break Deductions
* **Question:** When workers take scheduled shift breaks (e.g. 1 hour unpaid break), does machine output pause or does the machine continue running?
* **User Decision:** **Option A (Continuous operation).**
* **Reason:** Machines run continuously throughout the shift clock hours (e.g., continuous automated operations, staggered operator breaks). Machine capacity is not reduced by worker break periods.

---

### Q4: Cleanup Legacy Columns on `production_work_center_sections`
* **Question:** Should we remove the legacy columns `supports_manual_production` and `manual_capacity_per_hour` from `production_work_center_sections`?
* **User Decision:** **DROP both columns.**
* **Reason:** Manual production is now fully managed by the dedicated table `production_section_manual_productions` (which supports multiple shift-anchored batches, named teams, and employee rosters). Dropping these legacy fields removes redundant and conflicting states from the section table and UI forms.
* **Schema Action:**
  - `ALTER TABLE production_work_center_sections DROP COLUMN supports_manual_production;`
  - `ALTER TABLE production_work_center_sections DROP COLUMN manual_capacity_per_hour;`

---

### Q5: Section Capacity Aggregation (Parallel Lines vs Sequential Bottleneck)
* **Question:** When multiple machines or manual batches exist in a section, is the section capacity the SUM of all outputs (parallel production lines), or is it constrained by the slowest step (bottleneck / `min`)?
* **User Decision:** **Parallel Sum.**
* **Reason:** Active machines and manual batches operate concurrently towards section output; total capacity is the sum of active machine outputs plus the sum of manual batch outputs over their respective operating windows.

---

## 2. Finalized Capacity Formulas (Section Level)

### A. Daily Capacity
* **Machine Daily Capacity**:
  - Daily Machine Output = (Sum of Active Machine Hourly Speeds) × Section Effective Operating Hours
* **Manual Batches Daily Capacity**:
  - Each batch only produces during its assigned shift's effective hours.
  - Daily Batch Output = Batch Speed per Hour × Assigned Shift Effective Hours
  - Total Daily Manual Output = Sum of all individual batch daily outputs.
* **Total Section Daily Capacity**:
  - Total Section Daily Capacity = Machine Daily Output + Total Daily Manual Output

### B. Hourly Capacity (Weighted Average)
* **Rule**: Use the **weighted average hourly output** based on the Section's actual operating window.
* **CRITICAL**: Never hardcode 24 hours. Always divide by the Section's actual Effective / Unique Operating Hours (e.g. 16 hours for a section running 06:00–15:00 and 14:00–23:00):
  - Section Hourly Capacity = Total Section Daily Capacity / Section Effective Operating Hours

### C. Human-Friendly English in Audit JSON (`calculation_details`)
* The JSON audit payload must store all breakdown steps and explanations written in **plain, human-friendly English**:
  - Every calculation step must have an explicit English text explanation and formula string.
  - Example: `"formula": "Batch 1 Speed (20 units/hr) x Shift 1 Effective Hours (8.0 hrs) = 160 units/day"`
  - Overlap peak rates, shift rosters, and machine breakdowns must be clearly labeled so any user can inspect and understand them without technical jargon.

---

## 3. Finalized Labor Costing Rules (Section Level)

### A. Internal Employees (Salaried Staff)
* **Daily Salary**:
  - Daily Salary = HRMS Net Monthly CTC / 30 days
* **Hourly Salary Rate**:
  - Hourly Rate = Daily Salary / Total Shift Duration (Clock Hours)
  - Breaks are paid by the company, so the full shift duration (e.g. 9 hours) is used as the divisor.
* **Daily Cost to Section**:
  - Daily Cost = Daily Salary (flat per working day).

### B. External Contractors (Hourly Billing)
* **Contractor Paid Hours**:
  - Contractors are also paid for their full shift hours on site, including lunch break (e.g. 9 hours).
* **Multiple Groups per Shift**:
  - A single shift can have multiple groups (allocations) of external workers (e.g. Group A: 4 Helpers at $10/hr + Group B: 2 Welders at $15/hr).
  - Shift Hourly Contractor Burn Rate = Sum of (Headcount × Hourly Pay Grade Rate) for all groups in that shift.
  - Daily Contractor Cost = Shift Hourly Contractor Burn Rate × Shift Total Duration Hours.

### C. Hourly Labor Rates & Overlap Handling
* **Timeline Surge during Overlap**:
  - When shifts overlap (e.g. 14:00 to 15:00), both shifts' workers are on site simultaneously, and their rates add together to produce the **Peak Hourly Labor Rate**.
* **Average Internal Hourly Rate**:
  - Average Internal Hourly Rate = Total Daily Internal Cost / Section Effective Operating Hours
* **Average External Hourly Rate**:
  - Average External Hourly Rate = Total Daily External Cost / Section Effective Operating Hours
* **Total Combined Average Hourly Labor Rate**:
  - Total Combined Average Hourly Labor Rate = (Total Daily Internal Cost + Total Daily External Cost) / Section Effective Operating Hours

---

## 4. Finalized Machine Opex & Discrepancy Rules (Section Level)

### A. Direct Operating Cost vs. Calculated Energy Rate
* **Base Energy Cost**:
  - Base Energy Cost = Consumption per Hour × Energy Rate per Unit
* **Reconciliation with Entered Operating Cost**:
  - When a user enters `operating_cost_per_hour` on a machine, it may differ from the base energy multiplication:
  - **Case 1: Entered Cost > Base Energy Cost**:
    - The excess difference is tracked as **`additional_cost`** (representing consumables, lubricants, tooling wear & tear, filters, etc.).
    - Human-friendly JSON note: e.g. `"Includes $X.XX/hr additional cost for consumables, lubricants, or tooling wear."`
  - **Case 2: Entered Cost < Base Energy Cost**:
    - The difference is tracked as **`deductibles`** (representing bulk power subsidies, solar credits, freebies, or negotiated vendor discounts).
    - Human-friendly JSON note: e.g. `"Includes $X.XX/hr deductibles for power subsidies, solar credits, or discounts."`
  - **Case 3: Entered Cost == Base Energy Cost (or left blank)**:
    - Standard base energy rate applies, no adjustment needed.
* **Daily Machine Cost**:
  - Daily Machine Cost = (Sum of Active Machine Hourly Operating Costs) × Section Effective Operating Hours

### B. Machine Form Tooltip Requirement
* On the **Create Machine** and **Edit Machine** modal forms, add an info icon ("i" button) next to `operating_cost_per_hour`:
  - Tooltip Text: *"If there are any additional costs (wear & tear, tooling, consumables) or deductions (subsidies, freebies), you can adjust the total operating cost per hour directly."*

---

## 5. Comprehensive JSON Audit Payload (`calculation_details`) Specification

Every calculation run will persist a rich, human-friendly JSON document in `calculation_details`. The JSON will include **every single metric, header, formula string, and breakdown** discussed across our chats:

### A. Complete Categorized Inventory of All Fields & Mid-Calculation Terms

#### 1. Operating Timeline & Shift Window Metrics
1. `shifts_count`: Total number of active shifts assigned to the section.
2. `total_shift_clock_hours`: Sum of individual shift durations before deduplicating overlaps (e.g. 9h + 9h = 18h).
3. `section_unique_operating_hours`: Total continuous factory operating hours on the clock after removing overlaps (e.g. 17.0 hrs).
4. `shift_overlap_hours`: Total hours during the day where more than one shift is active simultaneously (e.g. 1.0 hr).
5. `has_overlapping_shifts`: Boolean flag (`true`/`false`) indicating if any shift overlaps exist.
6. `operational_window_start`: Earliest shift start clock time (e.g. `"06:00"`).
7. `operational_window_end`: Latest shift end clock time (e.g. `"23:00"`).
8. `has_inactive_gaps`: Boolean flag indicating if the section shuts down between shifts during the day.
9. `inactive_gap_hours`: Total downtime hours between shifts (if any).

#### 2. Machinery Capacity & Opex Metrics
10. `active_machines_count`: Number of active machines running in the section.
11. `inactive_machines_count`: Number of machines in maintenance or idle.
12. `hourly_machine_capacity`: Sum of all active machine speeds per hour.
13. `daily_machine_capacity`: `hourly_machine_capacity × section_unique_operating_hours`.
14. `sum_machine_consumption_per_hour`: Total energy consumption per hour (kW or fuel liters).
15. `calculated_machine_base_cost_per_hour`: Sum of `(consumption × tariff)` across all active machines.
16. `total_machine_additional_cost_per_hour`: Sum of extra entered costs for consumables, tooling wear, and lubricants.
17. `total_machine_deductibles_per_hour`: Sum of cost deductions for bulk subsidies, solar credits, or discounts.
18. `hourly_machine_expense`: Final net hourly running cost across all active machines (`base + additional - deductibles`).
19. `total_daily_machine_expense`: `hourly_machine_expense × section_unique_operating_hours`.
20. `machine_cost_per_unit`: `total_daily_machine_expense / total_daily_capacity`.

#### 3. Manual Batch Capacity Metrics
21. `active_manual_batches_count`: Total number of active manual teams/batches.
22. `inactive_manual_batches_count`: Number of paused/inactive batches.
23. `daily_manual_capacity`: Sum of each batch's `(speed_per_hour × assigned_shift_effective_hours)`.
24. `hourly_manual_capacity`: Weighted average hourly manual speed = `daily_manual_capacity / section_unique_operating_hours`.
25. `peak_hourly_manual_capacity`: Highest concurrent manual output rate reached during shift overlap windows (e.g. 90 units/hr).
26. `shift_manual_capacities`: Subtotals of manual production speed for each distinct shift (e.g. Shift 1 = 35 u/h, Shift 2 = 55 u/h).

#### 4. Internal Employee Labor Metrics (Salaried Staff)
27. `internal_employees_count`: Total internal salaried workers assigned to the section.
28. `total_internal_monthly_salary`: Sum of all assigned employees' HRMS monthly net salaries.
29. `total_internal_daily_salary`: `total_internal_monthly_salary / 30 days`.
30. `total_daily_internal_labor_cost`: Sum of daily salaries for all active internal staff.
31. `average_internal_hourly_rate`: `total_daily_internal_labor_cost / section_unique_operating_hours`.
32. `shift_internal_hourly_rates`: Subtotal internal labor burn rates for each shift (e.g. Shift 1 = $16.67/hr, Shift 2 = $22.22/hr).
33. `peak_internal_hourly_rate`: Maximum internal labor rate during shift overlap windows (e.g. $38.89/hr).
34. `internal_labor_cost_per_unit`: `total_daily_internal_labor_cost / total_daily_capacity`.

#### 5. External Contractor Labor Metrics (Hourly Staff)
35. `external_allocations_count`: Number of contractor group allocations.
36. `total_external_headcount`: Sum of all contract workers across all groups (e.g. 4 Helpers + 2 Welders = 6 workers).
37. `total_daily_external_labor_cost`: Sum of `(headcount × pay_grade_hourly_rate × shift_total_clock_hours)` across all groups.
38. `average_external_hourly_rate`: `total_daily_external_labor_cost / section_unique_operating_hours`.
39. `shift_external_hourly_rates`: Subtotal contractor burn rates for each shift (e.g. Shift 1 = $70.00/hr, Shift 2 = $24.00/hr).
40. `peak_external_hourly_rate`: Maximum contractor burn rate during shift overlap windows (e.g. $94.00/hr).
41. `external_labor_cost_per_unit`: `total_daily_external_labor_cost / total_daily_capacity`.

#### 6. Combined Labor Metrics
42. `total_daily_labor_cost`: `total_daily_internal_labor_cost + total_daily_external_labor_cost`.
43. `total_average_hourly_labor_rate`: `total_daily_labor_cost / section_unique_operating_hours`.
44. `peak_hourly_labor_rate`: Maximum total labor burn rate during shift overlap (`peak_internal + peak_external`).
45. `total_labor_cost_per_unit`: `total_daily_labor_cost / total_daily_capacity`.

#### 7. Total Section Financial & Production Summary
46. `total_daily_capacity`: `daily_machine_capacity + daily_manual_capacity`.
47. `total_hourly_capacity`: `total_daily_capacity / section_unique_operating_hours` (dynamic average).
48. `peak_total_hourly_capacity`: Maximum possible output in an hour (`hourly_machine_capacity + peak_hourly_manual_capacity`).
49. `total_daily_operating_cost`: `total_daily_machine_expense + total_daily_labor_cost`.
50. `total_hourly_operating_cost`: `total_daily_operating_cost / section_unique_operating_hours` (dynamic average).
51. `peak_total_hourly_operating_cost`: Maximum running cost in an hour (`hourly_machine_expense + peak_hourly_labor_rate`).
52. `cost_per_unit`: `total_daily_operating_cost / total_daily_capacity`.

---

### B. Detailed Breakdown Arrays in JSON:
* **`machines`**: List of each machine with: name, code, speed, energy source, consumption/hr, tariff/hr, calculated base cost, final operating cost/hr, `additional_cost` or `deductibles` (if mismatched), plain English explanation, and daily cost.
* **`manual_batches`**: List of each batch with: name, assigned shift, hourly speed, shift effective hours, daily output, team members, and English formula string.
* **`internal_employees`**: List of each employee with: name, ID, monthly salary, daily salary (`monthly / 30`), assigned shift, total shift duration (clock hours including paid breaks), hourly salary rate, daily cost, and English formula string.
* **`external_allocations`**: List of each contractor group with: shift, headcount, pay grade name, hourly rate, total shift duration hours, daily cost, and English formula string.
* **`timeline_intervals`**: Detailed timeline windows across the day (e.g. `06:00–14:00`, `14:00–15:00 overlap`, `15:00–23:00`), listing:
  - `window_label`, `start_time`, `end_time`, `duration_hours`, `is_overlap`
  - `active_shifts`
  - `running_machine_capacity`, `running_manual_capacity`, `running_total_capacity`
  - `running_internal_labor_rate`, `running_external_labor_rate`, `running_total_labor_rate`
  - `running_machine_rate`, `running_total_cost_rate`
  - `window_total_cost` (`running_total_cost_rate × duration_hours`)
  - `window_total_output` (`running_total_capacity × duration_hours`)

*(Note: User will later review these metrics to decide which ones should also be promoted to dedicated table columns for fast indexing and reporting).*

---

## 6. Confirmed Schema Changes & Action Items

### Columns to DROP:
| # | Table | Column | Type | Reason / Justification |
| :-: | :--- | :--- | :--- | :--- |
| **1** | `production_work_center_sections` | `supports_manual_production` | `tinyint(1)` | Replaced by dedicated `production_section_manual_productions` table. |
| **2** | `production_work_center_sections` | `manual_capacity_per_hour` | `decimal(12,4)` | Replaced by batch-specific `capacity_per_hour` in manual batches. |
| **3** | `production_work_center_section_calculations` | `work_center_id` | `bigint(20)` | **Redundant foreign key.** Each record is already linked to `section_id` (and the section belongs to a work center), and directly links to the parent calculation run via `work_center_calculation_id`. |

---

## 7. Storage Architecture: Live Data vs Snapshots

1. **Live Entity Tables:**
   - Store configurations, assignments, and speeds:
     - `production_work_centers`
     - `production_work_center_sections`
     - `production_machines`
     - `production_shifts`
     - `production_section_manual_productions`
     - `production_section_manual_production_employees` (pivot)
     - `production_work_center_employees`
     - `production_external_worker_allocations`
2. **Snapshot History Tables:**
   - Store immutable calculation runs generated when countdown timer expires or when user triggers manual recalculation:
     - `production_work_center_calculations`: Parent Work Center calculation snapshot.
     - `production_work_center_section_calculations`: Child Section calculation snapshots linked via `batch_id` (UUID) and `work_center_calculation_id`.
     - `calculation_details` (`longtext` / JSON): Full detailed audit trail (rosters, timeline intervals, machine breakdown, English explanations).

---

## 8. Temporary Testing Setup: Debounce Logic Commented Out
* **Status**: Completed.
* **Changes Made**:
  1. In `app/Domains/Production/Services/CountdownCalculationEngine.php`:
     - Inside `isCountdownActive()`: Temporarily commented out timer checks to return `false` unconditionally. This suppresses UI timer lockouts, countdown banners, and delays.
     - Inside `triggerCountdown()`: Temporarily commented out timer arming logic so modifications do not start 10-minute countdowns.
  2. In `app/Domains/Production/Controllers/WorkCenterController.php`:
     - In `recalculateNow()`: Fixed parameter passing so it triggers immediate calculation (`'manual'`) on the "Recalculate" button click.
* **How to Test**:
  - Open any Work Center page (`/production/work-centers/{id}`).
  - Click the **"Recalculate"** button in the top action bar or bypass banner.
  - Calculation executes immediately and generates fresh snapshots for the Work Center and all of its active Sections.

---

## 9. Remaining Decisions & Discussion Points (Section Level)

Before implementing the new calculation formulas in `SectionCalculationService.php`, the following decisions/preferences remain:

### Remaining Decision 1: Promoted Table Columns vs. JSON-Only Storage
* **User Decision**: **Keep existing columns and store all 52 metrics in JSON first. Promotion of fields to columns will be the last step after all testing is done.**

### Remaining Decision 2: Handling Sections with Zero Active Shifts
* **User Decision**: **Agreed with fallback handling** (even though rare/unlikely in practice):
  - Unique Operating Hours = 0.
  - Daily Capacity = 0, Hourly Capacity = 0.
  - Daily Labor Cost = 0, Daily Machine Cost = 0.
  - Audit JSON stores an advisory note: `"Section has no active shifts assigned. Operating hours and capacities are zero."`

---

## 10. Code Audit & Implementation Task List

This task list tracks every file and change required for the Section calculation rework. Items will be checked off as they are completed. If any additional file or dependency is discovered during development, it will be added here immediately.

### Phase 1: Database Migration (Schema Drops)
- [x] **Task 1.1**: Create and run migration `database/migrations/2026_10_27_000001_drop_legacy_columns_from_production_sections_and_calculations.php`:
  - DROP `supports_manual_production` from `production_work_center_sections`
  - DROP `manual_capacity_per_hour` from `production_work_center_sections`
  - DROP `work_center_id` from `production_work_center_section_calculations` (safely dropping foreign key constraint first if present).
  - SQLite compatibility guarded in migration definitions for automated test suites.

### Phase 2: Model & DTO Cleanup
- [x] **Task 2.1**: Update `app/Domains/Production/Models/WorkCenterSection.php`:
  - Remove `supports_manual_production` and `manual_capacity_per_hour` from `$fillable` and `$casts`.
  - Update `supportsManualProduction()` helper method to check whether active manual productions exist.
- [x] **Task 2.2**: Update `app/Domains/Production/Models/WorkCenterSectionCalculation.php`:
  - Remove `work_center_id` from `$fillable`.
  - Update `workCenter()` relation to access work center through `$this->section->workCenter` or through `workCenterCalculation`.
- [x] **Task 2.3**: Update `app/Domains/Production/DTO/WorkCenterSectionDTO.php`:
  - Cleaned up properties and mapping logic.
  - Retained optional `$supports_manual_production` and `$manual_capacity_per_hour` arguments as deprecated/ignored parameters for backward compatibility with legacy test callers.
- [x] **Task 2.4**: Update Form Requests & Controllers:
  - `app/Domains/Production/Requests/StoreWorkCenterSectionRequest.php`: Removed legacy manual validation rules and prepareForValidation.
  - `app/Domains/Production/Requests/UpdateWorkCenterSectionRequest.php`: Removed legacy manual validation rules and prepareForValidation.
  - `app/Domains/Production/Services/WorkCenterSectionService.php`: Removed legacy manual array defaults.
  - `app/Domains/Production/Controllers/WorkCenterSectionController.php`: Cleaned up store/update payload.

### Phase 3: Section Calculation Engine Overhaul
- [x] **Task 3.1**: Update `app/Domains/Production/Services/SectionCalculationService.php`:
  - `calculateOperatingHours()`: Implemented zero-shift fallback handling (returns 0 hours, advisory notes).
  - `calculateSectionMachines()`:
    - Base energy cost (`consumption * tariff`).
    - Discrepancy reconciliation (`additional_cost` if entered > base, `deductibles` if entered < base).
    - Human-friendly English explanations per machine (no LaTeX/symbols).
    - Daily capacity = `sum(machine speeds) * section_unique_operating_hours`.
    - Daily opex = `sum(machine opex) * section_unique_operating_hours`.
  - `calculateSectionManualBatches()`:
    - Shift-anchored output: `daily_batch_output = batch_speed * shift_effective_hours`.
    - `total_daily_manual_capacity = Sum(batch daily outputs)`.
    - `hourly_manual_capacity = total_daily_manual_capacity / section_unique_operating_hours` (dynamic weighted average).
    - Peak manual speed tracking during shift overlap.
    - Plain English formula strings per batch.
  - `calculateSectionLabor()`:
    - Internal staff: Daily salary = `HRMS CTC / 30`. Hourly rate = `Daily salary / shift_clock_hours` (including paid breaks). Daily cost = `Daily salary`.
    - External contractors: Paid for full shift clock hours (including lunch). Multiple groups per shift supported. Daily cost = `sum(headcount * pay_grade_rate * shift_clock_hours)`.
    - Separate rates: `average_internal_hourly_rate`, `average_external_hourly_rate`, `total_average_hourly_labor_rate`, and `peak_hourly_labor_rate` during shift overlap.
    - Plain English formula strings for each internal employee, external group, and timeline interval.
  - Comprehensive 52-metric JSON audit payload across the 7 categories.
  - `calculateAndSave()`: Saves to `production_work_center_section_calculations` without `work_center_id`.
- [x] **Task 3.2**: Update `app/Domains/Production/Services/WorkCenterActivityLogger.php`:
  - Fixed `logSectionCalculationCompleted()`: resolves `workCenterId` from `$calculation->section?->work_center_id` instead of `$calculation->work_center_id`.
- [x] **Task 3.3**: Verify `app/Domains/Production/Services/WorkCenterCalculationService.php`:
  - Verified parent rollup seamlessly reads section calculation results without referencing `work_center_id` on the section calculation record.

### Phase 4: UI & Tooltip Updates
- [x] **Task 4.1**: Update `resources/views/modules/production/machines/create.blade.php`:
  - Added professional info tooltip ("i" icon) and dynamic inline status label next to `operating_cost_per_hour` input explaining baseline energy formula (`Consumption × Rate`), and clarifying that surplus is accounted as an **Additional Operational Expense** (maintenance, tooling wear, lubricants) while deficit is treated as an applied **Operating Grant, Subsidy, or Tariff Rebate**.
  - Upgraded submit mismatch modal (`Swal.fire` and fallback `confirm()`) with professional financial breakdown distinguishing **Additional Operating Expense Specified** vs. **Operating Grant / Subsidy Specified**.
- [x] **Task 4.2**: Update `resources/views/modules/production/machines/edit.blade.php`:
  - Aligned info tooltip, inline status indicator, and submit confirmation modal with the exact professional additional expense and operating grant/subsidy framework.
- [x] **Task 4.3**: Overhaul Section Calculation Modal in `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php`:
  - Integrated via `@include` into `resources/views/modules/production/work-centers/show.blade.php`.
  - Top 6 KPI summary cards (Daily Cap, Daily Opex, Unit Cost, Operating Hours, Machine Opex, Labor Cost).
  - Tab 1: Machinery & Energy (kW, base energy, additional cost, deductibles, opex, reconciliation badges, English explanations).
  - Tab 2: Manual Batches (shift-anchored daily outputs, team rosters, English formulas).
  - Tab 3: Personnel Labor (internal CTC/30 division, full clock hours divisor, contractor full shift billing, English formulas).
  - Tab 4: Timeline Intervals & Overlap Slices (surge labor burn rates, running capacities, plain English overlap explanations).
  - Tab 5: Complete 52 Audit Metrics (categorized table with keys, values, and definitions).

### Phase 5: Verification & Testing
- [x] **Task 5.1**: Run Section calculation tests via CLI:
  - Created standalone bootstrap test suite `scratch/verify_all_section_calculations.php`.
  - Result: **38 / 38 TESTS PASSED (100%)**.
- [x] **Task 5.2**: Test the "Recalculate" button on Work Center page and verify DB snapshots:
  - Temporarily bypassed debounce timer in `CountdownCalculationEngine.php`.
  - Fixed `recalculateNow` trigger type in `WorkCenterController.php`.
  - Verified snapshot creation end-to-end via `scratch/test_recalculate.php`.
  - Verified snapshot records in `production_work_center_section_calculations` (no `work_center_id`, all 52 metrics present in JSON).
- [x] **Task 5.3**: Update test seeder `Database\Seeders\ProductionTestWorkCenterSeeder`:
  - Automatically truncates all calculation snapshots (`production_work_center_calculations`, `production_work_center_section_calculations`, `production_work_center_activity_logs`).
  - Clears previous manual batches, allocations, assignments, machines, sections, and work centers.
  - Seeds 3 diverse Work Centers (`WC-PMF-01`, `WC-SMF-02`, `WC-AQP-03`).
  - Seeds 6 distinct Sections covering Machine-heavy, Mixed, Pure manual, Day-shift only, and 24/7 3-shift operations.
  - Wires 12 Machines (10 assigned, 2 standalone ready for UI test).
  - Wires 13 Internal Employee assignments with valid monthly CTC.
  - Wires 12 External Worker allocations across 4 Pay Grades.
  - Wires 4 Shift-anchored Manual Production Batches with employee and contractor links.
  - Leaves snapshot tables completely clean so calculations can be triggered and tested fresh in the browser.
- [x] **Task 5.4**: Hardened Calculation Audit Breakdown Modal & Employee Field Aliases:
  - Resolved `ErrorException: Undefined array key "employee_name"` at `section-calculation-modal.blade.php:483`.
  - Added robust aliases in `SectionCalculationService::calculateSectionLabor()` (`employee_name`, `monthly_salary`, `shift_duration_hours`, `hourly_salary_rate`).
  - Protected all array key accesses across machines, manual batches, internal employees, external contractors, and timeline intervals with null-coalescing (`??`) fallbacks.
  - Verified clean view rendering on `GET /production/work-centers/{id}` across all 3 seeded Work Centers (`WC-PMF-01`, `WC-SMF-02`, `WC-AQP-03`).

