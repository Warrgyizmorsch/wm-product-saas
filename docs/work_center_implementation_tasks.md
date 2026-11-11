# Work Center — Implementation Task List

> **Purpose:** Code changes that come out of the agreed decisions in [`docs/work_center_decisions.md`](work_center_decisions.md).
> Each task carries the exact files, lines and data mappings so it can be implemented without re-investigation.
> **Rule:** Tasks are implemented only when the user says so. After implementation a task is marked **🛠️ Applied**; only the user marks it **✅ Verified** (passing tests ≠ verified).

## Status Legend
* 🟢 **Agreed** — Decision final, waiting for go-ahead to implement.
* 🟡 **Proposed** — Logged with a recommended fix; not yet confirmed by the user.
* 🛠️ **Applied** — Implemented; awaiting user verification.
* ✅ **Verified** — Verified by the user.
* ❌ **Cancelled** — Superseded by a later decision; kept for history.
* 📌 **Marked for Other Module** — Belongs to another module's UI/flow; tracked here so it is not lost.
* 🔀 **For Routing Review** — Found during Work Center review but belongs to the routing costing rules; to be decided in the routing file.
* ⏸️ **Marked Issue — Decide Later** — Problem confirmed in code; solution not yet agreed. Not to be implemented until decided.

---

## Summary Register

| Task ID | Title | Source Decision | Status |
| :--- | :--- | :--- | :--- |
| **WC-IMPL-01** | Manual batch output uses full shift hours (breaks included) | WC-02 rule 2 | 🟢 Agreed |
| **WC-IMPL-02** | Shift module: handle break ≥ shift duration (0 working hours) at shift creation/edit | WC-02 | 📌 Marked for Shift Module |
| **WC-IMPL-03** | ~~Timeline windows ignore breaks for manual batches~~ | — | ❌ Cancelled — superseded by WC-IMPL-01 |
| **WC-IMPL-04** | Hardcoded `$` in calculation snapshot explanation texts (tenant currency not used) | WC-08, WC-46 | 🟢 Agreed — Method A |
| **WC-IMPL-05** | Audit metric #15 described as electricity/kW only (should be generic energy) | WC-10 | 🟢 Agreed |
| **WC-IMPL-06** | Remove leftover code for dropped section manual-production columns | WC-12 | 🟢 Agreed |
| **WC-IMPL-07** | Production reads annual CTC as monthly (labor 12× too high) — use HRMS monthly CTC method | WC-17 | 🟢 Agreed |
| **WC-HRMS-01** | HRMS: employee form label "Current Salary" doesn't say annual; FnF guesses annual vs monthly | WC-17 | 📌 Marked for HRMS Module |
| **WC-IMPL-08** | Cost per unit shows 0 when output is 0 but cost > 0 — show "N/A" (null) | WC-23 | 🟢 Agreed |
| **WC-IMPL-09** | Zero-output warning counts machines of any status — use active machines, explain reason | WC-24 | 🟢 Agreed |
| **WC-IMPL-10** | Remove unused legacy code: `calculateCapacity()` / `calculateLaborCost()` (WorkCenterCalculationService), `logResourceChange()` (WorkCenterActivityLogger) | WC-25, WC-41 | 🟢 Agreed |
| **WC-IMPL-11** | Direct staff hourly rate uses shift minus break; footer says "over 24h" | WC-27 | 🟢 Agreed |
| **WC-IMPL-12** | Overhead unit "/ day" missing on Work Center add/edit forms (and check show page) | WC-28 | 🟢 Agreed |
| **WC-IMPL-13** | Countdown with empty queue recalculates every section — should be Work Center roll-up only | WC-35 | 🟢 Agreed |
| **WC-IMPL-14** | Countdown robustness: per-WC error handling in cron, lock checked by button, stuck lock, tenant context in cron | WC-35 | 🟡 Proposed |
| **WC-IMPL-15** | Work Center snapshot doesn't record which section snapshots it used (reused older ones untraceable) | WC-37 | 🟢 Agreed |
| **WC-IMPL-16** | Work Center hero panel: no "Calculation Pending" state; live overhead fallback shown as total cost | WC-42 | 🟢 Agreed |
| **WC-IMPL-17** | Modal footers: Details modal counts inactive machines/batches; snapshot modal hides not-counted items | WC-43 | 🟢 Agreed |
| **WC-IMPL-18** | Shifts tab footer and Operating Window card use different maths from the calculation | WC-44 | 🟢 Agreed |
| **WC-IMPL-19** | Duplicate alias keys in snapshots; "Target Speed / pcs" label; final format/wording sweep | WC-45 | 🟢 Agreed |
| **WC-MACH-01** | Idle machines: confirm status meaning so idle machines count as active (no zero-output warning) | WC-24, WC-07 | 📌 Marked for Machine Module |
| **WC-ISSUE-11** | Machine operating cost and energy rate not converted to base currency on save | WC-46 | ⏸️ Decide Later (with WC-FINAL-01) |
| **WC-ISSUE-12** | HRMS salary stored in tenant currency; Production treats it as base | WC-46 | ⏸️ Decide Later (with WC-FINAL-01; 📌 HRMS) |
| **WC-RTG-01** | Routing operator labor sums all non-batch workers as if on the floor at the same time | WC-18 | ✅ Decided in routing review → RT-14 / RT-IMPL-12 |
| **WC-RTG-02** | Inactive resource on routings: approved advisory missing; backend allows inactive; drafts drop now-inactive resources | WC-48 | ✅ Decided in routing review → RT-29 / RT-IMPL-18 |
| **WC-FINAL-01** | **Final task:** audit that every cost field is converted from base currency at display | WC-IMPL-04, WC-46 | 🟢 Agreed — ⏭️ do LAST |
| **WC-ISSUE-01** | Shift deactivated/deleted in Shift module leaves sections with 0 active shifts | WC-04 | ⏸️ Decide Later (📌 Shift Module) |
| **WC-ISSUE-02** | Section form accepts inactive shifts | WC-04 | ⏸️ Decide Later |
| **WC-ISSUE-03** | Contractor allocation shift not required to be one of the section's shifts (8-hour fallback) | WC-04 | ⏸️ Decide Later |
| **WC-ISSUE-04** | Zero-active-shift calculation fallback: wrong advisory text, labor still charged, 8-hour guesses | WC-04 | ⏸️ Decide Later |
| **WC-ISSUE-05** | Timeline excludes "(Ext)" employees (shift inside section hours but not a section shift) | WC-06 | ⏸️ Decide Later (📌 Shift Module) |
| **WC-ISSUE-06** | 24-hour employee shift (start = end) calculated as 8 hours | WC-06 | ⏸️ Decide Later (📌 Shift Module) |
| **WC-ISSUE-07** | No re-check of employee assignments after shift changes; 8-hour guesses in calculation | WC-06 | ⏸️ Decide Later (📌 Shift Module) |
| **WC-ISSUE-08** | Manual batch shift not validated (backend) as one of the section's shifts | WC-13 | ⏸️ Decide Later (📌 Shift Module) |
| **WC-ISSUE-09** | Manual batch workers not checked against the batch's shift | WC-13 | ⏸️ Decide Later (📌 Shift Module) |
| **WC-ISSUE-10** | Routing operator labor uses 8 h for "(Ext)" employees (different hourly rate than section calculation) | WC-18 | ⏸️ Decide Later (📌 Shift Module) |

> **📌 Shift Module group:** WC-IMPL-02 and WC-ISSUE-01 … WC-ISSUE-10 are all shift-related. They will be discussed and decided together when the Shift module is reviewed, after the remaining Work Center decisions are done.

---

## WC-IMPL-01: Manual Batch Output Uses Full Shift Hours (Breaks Included)
* **Source Decision:** WC-02 rule 2 (revised 2026-11-10 — breaks are counted, not excluded)
* **Status:** 🟢 Agreed

### Current Behaviour (Conflicts with Decision)
Batch output subtracts the shift break. In `app/Domains/Production/Services/SectionCalculationService.php`:
* Shift parsing, lines 371–377: `effective_minutes = duration_minutes − break_minutes` (falls back to full duration when the result is 0).
* Batch output, line 653: `$effHours = $shiftMap[$shiftId]['effective_hours'] ?? …duration_hours ?? 0.0;` → `daily_cap = capacity_per_hour × effective_hours` (line 658).

**Example:** batch 20 u/h on a 9 h shift with a 1 h break → today 160 units/day; required **180 units/day**.

### Required Behaviour
1. Manual batch daily output = **batch capacity per hour × full shift clock hours** (breaks included).
2. The break is shown for information only; it does not reduce output, labor cost or machine output anywhere.
3. Timeline windows already use full clock hours for manual output (lines 1190–1198), so after this change the timeline total equals daily manual capacity automatically (WC-IMPL-03 cancelled).
4. The "0 working hours" case (break ≥ duration) no longer affects output, so no output warning is needed for it. Break-vs-duration checks stay with the Shift module (WC-IMPL-02).

### Code Mapping
| Layer | File | Lines | Change |
| :--- | :--- | :--- | :--- |
| Batch output | `app/Domains/Production/Services/SectionCalculationService.php` | 653, 658 | Use `duration_hours` instead of `effective_hours` for `$effHours` / `daily_cap`. |
| Batch formula text | same file | 729 | `"Batch capacity (X units/hr) x Shift hours (H, breaks included) = Y units/day"` — drop "effective". |
| Snapshot key | same file | 741 (`'eff_hrs'`) | Value becomes shift hours. Keep key `eff_hrs` (old snapshots read it) or rename to a canonical `shift_hours` — **decide at implementation**; per ISSUE-11 no duplicate alias keys. |
| Shift item | same file | 371–377, 393–394 | `effective_minutes` / `effective_hours` no longer used by the calculation; remove them and the 0→full-duration fallback (373–375). Keep `break_minutes` / `break_hours` for display. |
| UI — batch table header | `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php` | 483 | `Shift Eff. Hours` → `Shift Hours`. |
| UI — batch row | same file | 606 | Displays the value above (no logic change). |
| UI — batch detail banner | same file | 896 | `({eff_hrs} eff. hrs)` → `({hours} shift hrs)`; remove the `?? 8` fallback. |
| UI — section details modal, Shifts tab | `resources/views/modules/production/work-centers/show.blade.php` | 2532 (header `Net Working Hours`), ~2542 (`$sh->working_hours_formatted ?? … '8.00 hrs'`) | Shows shift minus break today, with an `8.00 hrs` fallback. Align to the rule (show shift hours, break as info); remove the `8.00 hrs` fallback. |

### Related Observation (to be discussed under WC-13, not part of this task yet)
* Line 653: if a batch's shift is **not one of the section's assigned shifts**, `$shiftMap[$shiftId]` is missing and `$effHours` silently becomes 0 — no reason is shown.
* Lines 654–657: `$shiftClockHours` falls back to **8.0 hours** when the shift is missing/zero, which drives batch labor cost.

---

## WC-IMPL-02: Shift Module — Handle Break ≥ Shift Duration (0 Working Hours)
* **Source Decision:** WC-02 (answer b, agreed 2026-11-10)
* **Status:** 📌 **Marked for Shift Module** (to be designed when the Shift UI/module is reviewed)

### Context
Shifts are created in a separate UI (Production → Shifts). The user will handle break validation there. Recorded here so it is not lost.

### Current State
| Layer | File | Lines | Current |
| :--- | :--- | :--- | :--- |
| Validation | `app/Domains/Production/Requests/StoreShiftRequest.php` | 21 | `'break_minutes' => 'required|integer|min:0'` — no check against shift duration. |
| Edit validation | — | — | No `UpdateShiftRequest` exists; check which request `ShiftController::update()` uses. |
| Forms | `resources/views/modules/production/shifts/create.blade.php` / `edit.blade.php` | 35 / 36 | Plain number input "Break Minutes", default `30`. |
| Controller | `app/Domains/Production/Controllers/ShiftController.php`, `Api/ShiftApiController.php` | — | To review. |

### To Decide (when Shift module is reviewed)
* Block saving when break ≥ shift duration, or allow with warning.
* Overnight shift duration handling must match `SectionCalculationService` (lines 363–368: overnight wrap; `00:00–00:00` = 24 h).

---

## WC-ISSUE-01: Shift Deactivated/Deleted in Shift Module Leaves Sections with 0 Active Shifts
* **Source Decision:** WC-04 (logged 2026-11-10)
* **Status:** ⏸️ **Marked — Decide Later** (📌 belongs with the Shift module review, alongside WC-IMPL-02)

### Problem
Sections store their shift IDs in `production_work_center_sections.assigned_shifts` (JSON). The Shift module does not check this list before changing a shift:
| Path | File | Lines | Current Behaviour |
| :--- | :--- | :--- | :--- |
| Deactivate | `app/Domains/Production/Controllers/ShiftController.php` | 58–73 (`update()`, line 67 `$data['active'] = $request->boolean('active')`) | Sets `active = false` with no check for sections using the shift. |
| Delete | `ShiftController.php` 75–86 (`destroy()`) → `app/Domains/Production/Repositories/WorkCenterRepository.php` 195–199 (`deleteShift()`) | — | Deletes the shift with no check. Section `assigned_shifts` keeps a dangling ID. |
| Section → shifts | `app/Domains/Production/Models/WorkCenterSection.php` | 142–146 (`shifts()`) | `ProductionShift::whereIn('id', assigned_shift_ids)` — silently drops missing shifts. |
| Calculation filter | `app/Domains/Production/Services/SectionCalculationService.php` | 338–340 | Counts only `production_shifts.active = true`. |

**Result:** a section whose only shift was deactivated/deleted calculates with 0 active shifts while its workers and contractors are still assigned.

### Proposal (not agreed)
Before deactivating or deleting a shift, check sections that use it (`assigned_shifts`) and block or warn, listing the affected sections.

---

## WC-ISSUE-02: Section Form Accepts Inactive Shifts
* **Source Decision:** WC-04 (logged 2026-11-10)
* **Status:** ⏸️ **Marked — Decide Later**

### Problem
| File | Lines | Current Rule |
| :--- | :--- | :--- |
| `app/Domains/Production/Requests/StoreWorkCenterSectionRequest.php` | 58–62 | `'shifts' => 'required|array|min:1'`, `'shifts.*' => Rule::exists('production_shifts','id')->where('tenant_id', …)` — checks existence only, not `active`. |
| `app/Domains/Production/Requests/UpdateWorkCenterSectionRequest.php` | 60–64 | Same. |

A section can be saved whose only shift is already inactive.

### Proposal (not agreed)
Add `->where('active', true)` to the `shifts.*` exists rule in both requests; show only active shifts in the section shift selector; message: *"Selected shift is inactive."*

---

## WC-ISSUE-03: Contractor Allocation Shift Not Required to Be One of the Section's Shifts
* **Source Decision:** WC-04 (logged 2026-11-10)
* **Status:** ⏸️ **Marked — Decide Later**

### Problem
| Layer | File | Lines | Current |
| :--- | :--- | :--- | :--- |
| Validation | `app/Domains/Production/Requests/StoreExternalWorkerAllocationRequest.php` | 25–29 | `shift_id` → `required|integer|exists:production_shifts` (tenant). No check that it is in the section's `assigned_shifts` or active. |
| Calculation | `app/Domains/Production/Services/SectionCalculationService.php` | ~1001–1004 (`calculateSectionLabor()` external loop) | `$shiftClockHours = $shiftMap[$shiftId]['duration_hours'] ?? 8.0;` → contractor cost silently uses **8 hours** when the shift is not a section shift. |

Same pattern for manual batches (lines 653–657) and internal employees whose HRMS shift cannot be resolved (lines 886–893, 896–898) — see WC-IMPL-01 "Related Observation".

### Proposal (not agreed)
Allocation `shift_id` must be one of the section's active shifts (validation). In the calculation, remove the 8.0 fallback; show a warning instead.

---

## WC-ISSUE-04: Zero-Active-Shift Calculation Fallback Is Inconsistent
* **Source Decision:** WC-04 (logged 2026-11-10)
* **Status:** ⏸️ **Marked — Decide Later**

### Doc vs Code
| Item | Old doc (`calculation_rework_decisions.md` Remaining Decision 2) | Code (`SectionCalculationService.php`) |
| :--- | :--- | :--- |
| Operating hours / capacity | 0 | 0 ✔ |
| Machine cost | 0 | 0 ✔ (hourly × 0 hours) |
| Internal labor cost | 0 | **Full daily salary** (CTC ÷ 30) charged — lines 864, 897 |
| Internal hourly rate | — | Uses employee's HRMS shift; **8.0 h fallback** if not found — lines 886–893, 896–898 |
| External labor cost | 0 | Headcount × rate × **8.0 h fallback** — ~line 1003 |
| Advisory note | "…costs are zero" | Lines 176–177 say *"…capacities, and costs are zero"* — **wrong**, labor is non-zero |
| Timeline intervals | — | Early return when no shifts — line 1049–1051 |

### Options Discussed (not agreed)
1. **Prevent at source** (WC-ISSUE-01/02/03) so the state cannot normally occur, and keep a safe fallback: hours, capacity and all costs = 0; no 8-hour guesses; clear warning: *"Section has no active shift. Shift X was deactivated/deleted. Assign an active shift to calculate."* *(proposed)*
2. Charge real salaried labor cost with 0 output; contractors 0 with warning.
3. Everything 0 as the old doc said, with the advisory text corrected.

---

## WC-IMPL-03: ~~Timeline Windows Ignore Breaks for Manual Batches~~
* **Status:** ❌ **Cancelled (2026-11-10)** — Superseded by revised WC-02: breaks are counted as production time. Timeline windows already use full clock hours for manual output (`SectionCalculationService.php` 1190–1198), so once WC-IMPL-01 is applied the timeline total equals daily manual capacity. Options A/B/C are no longer needed.

---

## WC-ISSUE-05: Timeline Excludes "(Ext)" Employees
* **Source Decision:** WC-06 rule 10 (logged 2026-11-10)
* **Status:** ⏸️ **Marked — Decide Later** (📌 Shift Module group)

### Problem
Assignment allows an employee whose HRMS shift is **not** one of the section's shifts, as long as it lies fully inside section operating hours (`WorkCenterEmployeeService.php` 473–555). The calculation counts their salary but leaves them out of the timeline:
| Figure | File | Lines | Current |
| :--- | :--- | :--- | :--- |
| Daily labor cost | `app/Domains/Production/Services/SectionCalculationService.php` | 864, 897 | Includes the employee's full daily salary ✔ |
| Shift resolution | same file | 868–885 | Resolves the employee's own shift via `ProductionShift::find()`, sets `is_outside_section_shifts = true` ✔ |
| Timeline placement | same file | 1100–1104 | `if (!$sId || !isset($shiftMap[$sId])) continue;` — employee skipped because their shift is not in the section's `$shiftMap` ✘ |
| Window labor | same file | 1178–1190 | Window labor burn sums only placed employees → timeline labor total < daily labor cost ✘ |

**Example:** employee 08:00–14:00, section shifts 06:00–12:00 and 12:00–18:00 → salary counted in daily cost, but the employee appears in no timeline window.

### Proposal (not agreed)
Place the employee on the 1440-minute map using **their own shift's start/end times** (already resolved at 873–885) instead of looking them up in `$shiftMap`. Timeline labor total then equals daily labor cost.

---

## WC-ISSUE-06: 24-Hour Employee Shift (Start = End) Calculated as 8 Hours
* **Source Decision:** WC-06 rule 8, WC-03 rule 4 (logged 2026-11-10)
* **Status:** ⏸️ **Marked — Decide Later** (📌 Shift Module group)

### Problem
| Path | File | Lines | Current |
| :--- | :--- | :--- | :--- |
| Section shifts | `SectionCalculationService.php` | 365–369 | start = end → `durationMin = 1440` (24 h) ✔ |
| "(Ext)" employee shift | same file | 878–884 | `Carbon::parse(start)`, `Carbon::parse(end)`; `if ($et->lt($st)) addDay()` — when start = end, `lt` is false → diff = **0 min** ✘ |
| Zero guard | same file | 896–898 | `if ($shiftClockHours <= 0) $shiftClockHours = 8.0;` → **8 hours** used ✘ |
| Assignment check | `WorkCenterEmployeeService.php` | 493–507 | start = end → `empEnd <= empStart` → treated as full 24 h ✔ |

Hourly rate for such an employee = daily salary ÷ 8 instead of ÷ 24.

### Proposal (not agreed)
Use one shared shift-duration rule everywhere (start = end → 24 h; end < start → overnight). Replace the Carbon block with the same minute logic used at 362–369.

---

## WC-ISSUE-07: No Re-check of Assignments After Shift Changes; 8-Hour Guesses
* **Source Decision:** WC-06 (logged 2026-11-10)
* **Status:** ⏸️ **Marked — Decide Later** (📌 Shift Module group; related to WC-ISSUE-01, WC-ISSUE-03)

### Problem
Eligibility (WC-06 rules 1–5) is checked only at assign / re-activate time (`WorkCenterEmployeeService.php` 67–103, 247, 297). Nothing re-checks existing assignments when:
* the employee's HRMS shift is changed, deactivated or deleted (HRMS / Shift module);
* the section's assigned shifts are changed (`WorkCenterSectionService` update);
* a section shift is deactivated or deleted (`ShiftController` — see WC-ISSUE-01).

The calculation then falls back to guesses:
| Case | File | Lines | Fallback |
| :--- | :--- | :--- | :--- |
| Employee shift no longer found | `SectionCalculationService.php` | 886–889 | First section shift's hours, else **8.0 h** |
| Employee has no shift | same file | 890–893 | First section shift's hours, else **8.0 h** |
| Shift hours ≤ 0 | same file | 896–898 | **8.0 h** |

Also possible: an employee's new shift no longer lies inside section hours, but the assignment stays active with no warning.
**Direct Work Center staff** (added 2026-11-10, from WC-27): `WorkCenterCalculationService.php` 358–363 — no shift → **8.0 h**; 24 h shift (start = end) → `working_hours` = 0 → **8.0 h**; 0 hours → **8.0 h**; shift resolved via `defaultShift ?? shift ?? resolveShiftForDate(now())` — the last option makes the result depend on the **day the calculation runs**.

### Proposal (not agreed)
1. Remove 8-hour guesses; show a warning on the employee row (*"Employee shift missing/changed — hourly rate cannot be calculated"*), still charging daily salary.
2. When shifts change (HRMS shift, section shifts, shift deactivate/delete), re-check affected assignments and flag or block incompatible ones.

---

## Future Features (Not Scheduled)
Ideas agreed as "not now"; recorded so they are not lost. Not to be implemented unless the user asks.

| ID | Feature | Source | Notes |
| :--- | :--- | :--- | :--- |
| **WC-FUT-01** | Unit of measure for machine capacity (e.g. kg/hr vs pcs/hr) | WC-07 (2026-11-10) | Today `production_machines.capacity` is generic "units/hr" and all machines in a section are summed. Would affect section/work-center capacity totals, cost per unit, and routing cycle time (`60 / Machine.capacity`). |
| **WC-FUT-02** | **Dependencies of Work Center data:** (a) inactive / delete cascades for Work Center and everything connected or dependent (sections, machines, batches, assignments, allocations, shifts); (b) connecting other modules and entry points so their changes arm the countdown | WC-31, WC-33 (2026-11-10) | **Decide later.** Current behaviour and known gaps recorded below. |

---

## WC-IMPL-04: Hardcoded `$` in Calculation Snapshot Explanation Texts
* **Source Decision:** WC-08 rule 4, WC-46 (agreed 2026-11-10)
* **Status:** 🟢 Agreed — **Method A chosen (2026-11-10)**: store numbers, build text at display

### Problem
Explanation / formula strings are built inside the calculation services with a literal `$` and raw base-currency numbers, then stored in the snapshot JSON (`calculation_details`). A tenant using INR sees `$`, and the amount is not converted from base currency.

| # | File | Line | Text |
| :-: | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Services/SectionCalculationService.php` | 546 | `'Includes $X/hr additional cost for consumables…'` |
| 2 | same | 550 | `'Includes $X/hr deductibles for power subsidies…'` |
| 3 | same | 553 | `'Matches calculated base energy consumption rate ($X/hr).'` |
| 4 | same | 557 | `'Flat operating cost specified ($X/hr).'` |
| 5 | same | 560 | `'Calculated from energy consumption (… @ $X/unit = $Y/hr).'` |
| 6 | same | 563 | `'No energy consumption or operating cost entered ($0.00/hr).'` |
| 7 | same | 570 | Machine `$costFormula`: `'$X/hr x H section operating hrs = $Y/day'` |
| 8 | same | 860 | `'Missing HRMS salary: defaults to $0.00'` |
| 9 | same | 902 | Internal employee `$formula`: `"Monthly CTC ($X) / 30 days = $Y/day; …"` |
| 10 | same | 1008 | External allocation `$formula`: `"N workers x $X/hr x H … = $Y/day"` |
| 11 | same | 1207–1208 | Timeline explanation: `"…Labor burn rate surges to $X/hr."` / `"…Labor burn rate: $X/hr."` |
| 12 | `app/Domains/Production/Services/WorkCenterCalculationService.php` | 367 | Direct staff `$formula`: `"Monthly CTC ($X) / …"` |

> Line list from a search on 2026-11-10; re-run `grep -n '\$' ` on both services at implementation time for any further occurrences (e.g. manual batch formula text).

Currency helpers available in `app/helpers.php`: `active_currency_symbol()` (240), `convert_from_base()` (254), `format_currency()` (302).

### Method Options
* **A — Store numbers, build text at display (✅ CHOSEN):** services store only the numeric values (in **base currency**) plus a message key (e.g. `explanation_type: 'additional_cost'`, `additional_cost_per_hour: 1.25`); Blade views build the sentence with `format_currency()`. Text is always in the viewer's current tenant currency, even for old snapshots and if the tenant changes currency later. Old snapshots that only have the text keep showing it as-is.
* **B — Use `format_currency()` inside the services:** quick, but the currency is frozen into the stored text at calculation time, and the background cron (`production:process-calculation-countdowns`) must have the tenant context resolved for `format_currency()` to pick the right currency.
> **Conversion requirement (user, 2026-11-10):** every money number placed in these sentences must be **converted from base currency** to the tenant currency at display (`format_currency()` / `convert_from_base()`), not just given a different symbol. Checking all cost fields across Work Center views is tracked separately as **WC-FINAL-01** (done last).

> **Also (WC-39):** remove the outdated HTML comment mentioning "Raw JSON" at `resources/views/modules/production/work-centers/show.blade.php` line 3556.
### Views that display these texts (to update with A)
* `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php` — machines tab (explanation, reconciliation), personnel tab (formulas), timeline tab (explanation).
* `resources/views/modules/production/work-centers/show.blade.php` — work center calculation modal, direct personnel tab (formula).

---

## WC-IMPL-05: Audit Metric #15 Described as Electricity / kW Only
* **Source Decision:** WC-10 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

### Problem
`resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php` line 1228 — audit metric #15 (`calculated_machine_base_cost_per_hour`) in `$auditMetricsMeta`:
* `source` = *"Machine Energy & Electricity Tariffs"*
* `formula` = *"Sum of (Machine Power kW * Electricity Cost …)"*

Machines can run on electricity, diesel, gas, etc. (`energy_rate_per_unit` is generic), so this text is wrong for non-electric machines.

### Required Change
* `source` → *"Machine energy consumption and energy rate"*
* `formula` → *"Sum of (Consumption per Hour × Energy Rate per Unit) across active machines"*
* While editing `$auditMetricsMeta`, check neighbouring machine metrics (e.g. #14 `sum_machine_consumption_per_hour`) for the same "kW / electricity" wording and make them generic.
* **(WC-43)** Also replace the hard-coded **"kW"** consumption unit in `section-calculation-modal.blade.php` lines **308** (Total Power summary) and **409** (machines table footer) with "/ hr" generic consumption (label "Consumption / hr"); rename "Total Power" → "Total Consumption".

### Not to Change (WC-10 rule 3)
"Tariff rebate" / "Energy Tariff Incentive" used as **subsidy examples** stay as they are:
`machines/create.blade.php` 126, 316 · `machines/edit.blade.php` 158, 348 · `section-calculation-modal.blade.php` 344.

---

## WC-FINAL-01: Final Audit — Every Cost Field Converted from Base Currency at Display
* **Source Decision:** WC-IMPL-04, WC-46 (agreed 2026-11-10)
* **Status:** 🟢 Agreed — ⏭️ **Do LAST**, after all other Work Center tasks are applied

### Rule
All money is stored in **base currency**. Every money value shown to the user must be converted to the tenant's active currency with `format_currency()` (`app/helpers.php` 302) or `convert_from_base()` (254) + `active_currency_symbol()` (240). Changing only the symbol is not enough.

### Scope to Check
| Area | Files |
| :--- | :--- |
| Work Center page (hero panel, section table, popovers, section details modal, WC calculation modal, activity logs) | `resources/views/modules/production/work-centers/show.blade.php` |
| Section calculation modal (KPIs, machines, batches, personnel, timeline, audit metrics) | `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php` |
| Machine forms (tooltips, live labels, submit confirmation — JS-built amounts) | `resources/views/modules/production/machines/create.blade.php`, `edit.blade.php` |
| Work Center forms (`overhead_cost`) | `resources/views/modules/production/work-centers/create.blade.php`, `edit.blade.php` |
| Snapshot texts (after WC-IMPL-04) | `SectionCalculationService.php`, `WorkCenterCalculationService.php` |
| Inputs | Entered amounts (operating cost, energy rate, overhead) must be converted **to** base on save (`convert_to_base()`) and **from** base on edit forms. |

> **Known gaps to resolve as part of this audit:** **WC-ISSUE-11** (machine costs not converted on save) and **WC-ISSUE-12** (HRMS salary currency).
### How to Verify
1. Search each file for `number_format(` on money values and for literal `$` / `₹`.
2. Switch the tenant to a non-USD currency (e.g. INR) and check every screen above: symbol **and** converted amount.
3. Record each file checked here with ✔ / ✘.

---

## WC-IMPL-06: Remove Leftover Code for Dropped Section Manual-Production Columns
* **Source Decision:** WC-12 rule 4 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

### Problem
`supports_manual_production` and `manual_capacity_per_hour` were dropped from `production_work_center_sections` (migration `2026_10_27_000001`), but an unused endpoint still writes to them. Calling it would fail with a database error. No view calls it today.

### Code to Remove / Change
| # | File | Lines | Change |
| :-: | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Routes/web.php` | 122 | Remove route `PATCH work-center-sections/{section}/manual-production` (`work-center-sections.update-manual-production`). Search views/JS for `update-manual-production` / `manual-production` URL first to confirm unused. |
| 2 | `app/Domains/Production/Controllers/WorkCenterSectionController.php` | 157–~215 | Remove `updateManualProduction()` (validates and passes `supports_manual_production` / `manual_capacity_per_hour`). |
| 3 | `app/Domains/Production/Services/WorkCenterSectionService.php` | ~115–152 | Remove `updateManualProduction()` (writes the dropped columns, then arms the countdown). |
| 4 | `app/Domains/Production/DTO/WorkCenterSectionDTO.php` | 17–18 | Remove deprecated constructor params `$supports_manual_production`, `$manual_capacity_per_hour`. |
| 5 | `tests/Feature/Production/SectionCalculationTest.php` | 151–304 (multiple) | Remove the old named args; tests that relied on `manual_capacity_per_hour` (e.g. 15.5, 20.0, 25.0) must create a **manual batch** instead to test manual capacity. |
| 6 | `tests/Feature/Production/SectionBasedShiftArchitectureTest.php` | 177–178, 437 | Remove the old args / payload key. |

### Keep
* `WorkCenterSection::supportsManualProduction()` (derived from active batches) and the `'supports_manual_production'` key in the section API payload (`WorkCenterSectionController.php` line 61) — it is a computed value, not the dropped column.

### Check at Implementation
* Re-run `grep -rn "supports_manual_production\|manual_capacity_per_hour" app resources tests database/seeders routes` — only the computed key (line 61) and the migration should remain.
* `routes/web.php` lines 408–410 duplicate the manual batch routes outside the named group — check whether intentional (API) before touching; not part of this task.

---

## WC-ISSUE-08: Manual Batch Shift Not Validated (Backend) as One of the Section's Shifts
* **Source Decision:** WC-13 (logged 2026-11-10)
* **Status:** ⏸️ **Marked — Decide Later** (📌 Shift Module group; related to WC-ISSUE-03, WC-ISSUE-07)

### Problem
| Layer | File | Lines | Current |
| :--- | :--- | :--- | :--- |
| Form | `resources/views/modules/production/work-centers/show.blade.php` | 5852–5865 | Lists only the section's active shifts ✔ (only guard) |
| Store validation | `app/Domains/Production/Controllers/SectionManualProductionController.php` | 33 | `'shift_id' => 'required|integer|exists:production_shifts,id'` — not tenant-scoped, not checked against section `assigned_shifts`, not checked `active` ✘ |
| Update validation | same file | 154 | Same ✘ |
| Calculation | `app/Domains/Production/Services/SectionCalculationService.php` | 653 | Shift missing from `$shiftMap` → `$effHours = 0` → batch output **0**, no reason shown ✘ |
| Calculation | same file | 654–657 | `$shiftClockHours` falls back to **8.0 h** → batch labor cost uses 8 h ✘ |

Also reachable **after** creation: section shifts changed, or the batch's shift deactivated/deleted (no re-check — see WC-ISSUE-01, WC-ISSUE-07).

### Proposal (not agreed)
* Validation: batch `shift_id` must be tenant-scoped, active, and in the section's `assigned_shifts`.
* Calculation: if the batch shift is not a section shift, output 0 **with a reason** (*"Batch shift X is not one of this section's active shifts"*); no 8-hour guess.

---

## WC-ISSUE-09: Manual Batch Workers Not Checked Against the Batch's Shift
* **Source Decision:** WC-13 (logged 2026-11-10)
* **Status:** ⏸️ **Marked — Decide Later** (📌 Shift Module group)

### Problem
* Employee assignment checks the employee's HRMS shift against the **section's** operating hours (WC-06; `WorkCenterEmployeeService.php` 473–555).
* Batch creation/update (`SectionManualProductionController.php` 43–68, `validateWorkerExclusivity()` 372–416) checks section membership and one-active-batch exclusivity, but **not** that the employee's HRMS shift (or the contractor allocation's `shift_id`) matches or covers the **batch's** shift.
* **Example:** batch on Morning shift (06:00–14:00) can include an employee whose HRMS shift is Evening (14:00–22:00).

### Related Calculation Inconsistency (for the same discussion)
* Batch labor: employee hourly rate = daily salary ÷ **batch shift** clock hours (`SectionCalculationService.php` 654–657, 678).
* Section personnel labor: shift is chosen as **batch shift first**, then the employee's HRMS shift, then the section's first shift (same file 846–851) — so for an employee in a batch, both use the **batch shift** and the hourly rates match. *(Corrected 2026-11-10.)*
* **Remaining concern:** for an employee in a batch, their own HRMS shift is ignored for costing and timeline placement — if their HRMS shift differs from the batch shift (this issue), the cost and timeline follow the batch shift.

### Proposal (not agreed)
Batch workers must be on the batch's shift: employee HRMS shift = batch shift (or lies inside it), and contractor allocation `shift_id` = batch shift.

---

## WC-IMPL-07: Production Reads Annual CTC as Monthly — Use HRMS Monthly CTC Method
* **Source Decision:** WC-17 (agreed 2026-11-10, Option B)
* **Status:** 🟢 Agreed

### Problem
`employees.current_salary` is the **annual CTC** in HRMS, but Production treats it as **monthly** and divides by 30. Example: annual CTC ₹6,00,000 → costed at ₹20,000/day instead of ₹1,667/day. Affects section labor, work center cost, cost per unit, routing operation labor cost, and therefore BOM/routing cost.

**Evidence (HRMS = annual):** compensation tab label `hrms.employees.lbl_annual_ctc` (`resources/views/modules/hrms/employees/tabs/compensation.blade.php` 129–130); `PayrollCalculationService.php` 268 (`$monthlyCTC = current_salary / 12`), 339, 369 (daily wage = `current_salary / 12 / divisor`), 235, 242 (yearly component values); slab matching on `min_ctc`/`max_ctc` (`PayrollCalculationService.php` 211–212, `EmployeeRepository.php` 138–139, `EmployeeApiController.php` 230–231); `MaintenanceWorkOrderService.php` 1093–1125 (Production already treats it as annual there); `HrmsDemoSeeder.php` 1152–1185 (28,00,000 etc.).

### Required Change
1. **HRMS (owner of the rule):** add one method returning payroll monthly CTC, e.g. `Employee::monthlyCtc(): float` → `current_salary / 12` (same rule as `PayrollCalculationService.php` 268). Optionally make `PayrollCalculationService` line 268 call it so there is one source.
2. **Production:** replace every direct read of `current_salary` as monthly with the HRMS method:

| # | File | Line | Context |
| :-: | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Services/SectionCalculationService.php` | 676 | Batch labor (`calculateSectionManualBatches()`) |
| 2 | same | 855 | Section personnel (`calculateSectionLabor()`) |
| 3 | same | 1439 | Routing operator labor (`getSectionResourceCosts()`) |
| 4 | `app/Domains/Production/Services/WorkCenterCalculationService.php` | 353 | Direct Work Center staff |
| 5 | `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php` | 530 | Fallback for old snapshots |

3. **Snapshot / formula text:** show *"Annual CTC X ÷ 12 = Monthly CTC Y ÷ 30 = Daily Z"* (built at display per WC-IMPL-04); rename keys/labels that say "Monthly CTC" for the raw value (e.g. `monthly_salary`, `total_internal_monthly_salary`) so they hold the **monthly** figure, not annual.
4. **Seeder:** `database/seeders/ProductionTestWorkCenterSeeder.php` 185–188 (and other `'salary' =>` rows, 209, 217) seeds monthly amounts (45,000 …) — change to annual CTC (e.g. 5,40,000).
5. **Tests:** update any Production test that seeds `current_salary` as monthly and asserts daily = salary ÷ 30.
6. **Do NOT** call `PayrollCalculationService::calculateSalary()` — it is a month payroll run (attendance, LOP), not a cost rate.

### Check at Implementation
* `grep -rn "current_salary" app/Domains/Production resources/views/modules/production` → only `MaintenanceWorkOrderService.php` (already annual) and the new HRMS method calls should remain.
* Old snapshots keep their stored (wrong) numbers; only new calculations are correct. Recalculate all work centers after deploy.

---

## WC-HRMS-01: HRMS Salary Field Ambiguity
* **Source Decision:** WC-17 (logged 2026-11-10)
* **Status:** 📌 **Marked for HRMS Module** (not Work Center work; recorded so it is not lost)

| Item | File | Lines | Observation |
| :--- | :--- | :--- | :--- |
| Form label | `resources/views/modules/hrms/employees/form-fields.blade.php`; `lang/en/hrms.php` | 264; 1600 (`frm_salary` = "Current Salary") | Doesn't say **annual CTC**, though the compensation tab shows it as "Annual CTC". Suggest label "Annual CTC". |
| Full & Final | `app/Domains/HRMS/Services/FnFCalculationService.php` | 33–39 | Guesses: values > 2,00,000 treated as annual (÷ 12), else monthly. Inconsistent with payroll (always annual). |
| FnF statement | `resources/views/modules/hrms/employees/exits/documents/fnf-statement.blade.php` | 385 | Shows `$` + raw `current_salary`. |

---

## WC-ISSUE-10: Routing Operator Labor Uses 8 Hours for "(Ext)" Employees
* **Source Decision:** WC-18 rule 5 (logged 2026-11-10)
* **Status:** ⏸️ **Marked — Decide Later** (📌 Shift Module group; same root cause as WC-ISSUE-05, WC-ISSUE-06)

### Problem
| Calculation | File | Lines | Shift hours used |
| :--- | :--- | :--- | :--- |
| Section personnel | `app/Domains/Production/Services/SectionCalculationService.php` | 866–898 | Batch shift → own HRMS shift (resolved via `ProductionShift::find()` even if not a section shift) → section first shift ✔ |
| Routing operator labor (`getSectionResourceCosts()`) | same file | 1433–1437 | `$shiftMap[$emp->shift_id]['duration_hours'] ?? 8.0` — only section shifts; an "(Ext)" employee's shift is not in `$shiftMap` → **8.0 h** ✘ |

**Result:** the same employee has one hourly rate in the section calculation and another in routing cost.

### Proposal (not agreed)
One shared helper that resolves an employee's costing shift and clock hours (WC-18 rule 2, with the 24 h rule from WC-ISSUE-06), used by section personnel, batch labor, timeline and `getSectionResourceCosts()`. No 8-hour guesses (WC-ISSUE-07).

---

## WC-RTG-01: Routing Operator Labor Sums All Non-Batch Workers as If at the Same Time
* **Source Decision:** WC-18 (logged 2026-11-10)
* **Status:** 🔀 **For Routing Review** — routing costing rule (old `work_center_routing_linking_tracker.md` WC-TRACK-03, Point 3.1 Case A); not decided in the Work Center review.

### Current Rule (Case A)
`Operator Labor Cost per Machine / hr = Σ hourly rate of all section workers NOT in a manual batch ÷ active machines in the section`, then `Labor Cost / Unit = operator rate / machine capacity`.

### Code
| Step | File | Lines |
| :--- | :--- | :--- |
| Workers in batches excluded | `SectionCalculationService.php` | 1410–1429 |
| Internal hourly rates summed | same | 1424–1443 |
| External hourly burn summed (headcount × rate) | same | 1446–1458 |
| ÷ active machines | same | 1460–1463 |
| Per-machine labor / unit | same | 1465–1471 |

### Problem
Workers on **different shifts** are added together. Example: 1 morning operator + 1 evening operator, 1 machine → operator rate = 2 operators' hourly rate, although only one is on the floor at any time → routing labor cost per unit doubled.

### Options for the Routing Review (not agreed)
* Use the **average concurrent** labor rate (non-batch labor daily cost ÷ section operating hours) ÷ active machines.
* Or use the labor rate of the shift the operation runs in (needs operation ↔ shift link).

---

## WC-IMPL-08: Cost per Unit = "N/A" When Output Is 0 but Cost > 0
* **Source Decision:** WC-23 rule 5 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

### Problem
Every cost-per-unit figure is `0` when capacity is 0, even when cost is being incurred — it reads as "free" instead of "cannot be calculated".

### Rule
* capacity = 0 and cost > 0 → `null` in the snapshot, displayed **"N/A"** with tooltip *"Output is 0 — cost per unit cannot be calculated. Daily cost X is being incurred with no production."*
* capacity = 0 and cost = 0 → `0`.

### Code Mapping
| # | File | Lines | Figure |
| :-: | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Services/SectionCalculationService.php` | 147–161 | Section `cost_per_unit`, `machine_cost_per_unit`, `internal_labor_cost_per_unit`, `external_labor_cost_per_unit`, `total_labor_cost_per_unit` |
| 2 | same | 43 | Zero/inactive-section early return (`cost_per_unit => 0.0`) — keep 0 (cost is also 0) |
| 3 | same | 733 (`$batchCostPerUnit`), 756 | Batch cost per unit (batch with labor but 0 output) |
| 4 | `app/Domains/Production/Services/WorkCenterCalculationService.php` | 123–125, 162, 178, 300 | Work Center `cost_per_unit` — **DB column** `production_work_center_calculations.cost_per_unit` is `decimal(12,4) default 0` (migration `2026_10_29_000002`, line 19) → needs a migration to make it **nullable** |
| 5 | `resources/views/modules/production/work-centers/show.blade.php` | ~20 places (`cost_per_unit`, `CostPerUnit`, "/ u", "/ unit") | Section row, popovers, section details Cost & Capacity tab, WC hero "Total Daily Cost" unit badge, WC calculation modal Card 3, Contributing Sections tab badges, Direct Personnel burden per unit, audit metrics — show "N/A" + tooltip when `null` |
| 6 | `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php` | ~11 places | KPI "Unit Cost" card, batch detail banner, audit metrics (#20, #34, #41, #45, #52) |

### Out of Scope (routing)
`getSectionResourceCosts()` (`SectionCalculationService.php` 1465–1503) gives per-unit routing costs per machine/batch (`capacity > 0 ? … : 0.0`). A machine/batch with 0 capacity cannot be used for a routing cycle time anyway — review with the routing file (note under WC-RTG-01 discussion).

### Check at Implementation
* Search both views for `number_format(... cost_per_unit` / `format_currency(... cost_per_unit` — passing `null` to `format_currency()` must not render as 0.
* Old snapshots store 0 — display them as stored (no reinterpretation).

---

## WC-IMPL-09: Zero-Output Warning — Count Active Machines Only, Explain the Reason
* **Source Decision:** WC-24 rules 1–3 (agreed 2026-11-10)
* **Status:** 🟢 Agreed
* **Depends on:** WC-MACH-01 (idle machines must count as active) — settle the machine status meaning first.

### Problem
The warning counts machines of **any** status. A section whose only machine is under maintenance / inactive / decommissioned produces 0 in the snapshot (WC-07) but shows **no** warning, because the machine count is still 1.

| Place | File | Lines | Current |
| :--- | :--- | :--- | :--- |
| Section row | `resources/views/modules/production/work-centers/show.blade.php` | 1149 | `$machineCount = $section->machines->count()` — all statuses |
| Section row conditions | same | 1166–1168 | `$hasZeroOutputLabor`, `$hasPartialUnutilizedLabor` use `$machineCount === 0` |
| Section row badge / text | same | 1351–1365, 1514 | "Cost with 0 Output (N)", partial-unutilized text |
| Advisory banner list | same | 1016–1060 (`$unutilizedLaborSections` filter) | Check its machine condition too |
| Section details modal | same | 2293–2295, 2320, 2748 | `$secDetailsMachineCount` — same issue |
| Section calculation modal | `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php` | (search "0 Output" / zero-output banner) | Check its machine condition |

### Required Change
1. Trigger on **active** machines: `$activeMachineCount = $section->machines->where('status', Machine::STATUS_ACTIVE)->count()`; use it in the zero-output and partial-unutilized conditions (all places above).
2. When `$activeMachineCount === 0`, look at **all** machines to build the reason:
   * `$section->machines->isEmpty()` → *"No machines assigned and no active manual batches."*
   * otherwise → *"N machine(s) assigned but none is active"* + status breakdown (`under_maintenance`, `inactive`, `decommissioned` counts).
3. Every message ends with: *"X workers are actively assigned — labor cost Y/day is incurred with 0 output."* (Y in tenant currency — WC-46.)
4. Badge text stays short ("Cost with 0 Output"); the full reason goes in the tooltip / banner / modal alert.
5. Better: compute the flags and reason once (e.g. a `WorkCenterSection` helper returning `['zero_output' => bool, 'partial_unutilized' => bool, 'reason' => string]`) and use it in all places, instead of repeating the logic in Blade.

---

## WC-MACH-01: Idle Machines Must Count as Active (No Zero-Output Warning)
* **Source Decision:** WC-24 rule 6, WC-07 (logged 2026-11-10)
* **Status:** 📌 **Marked for Machine Module** — check and update when the Machine module is reviewed. Must be settled **before or together with WC-IMPL-09**.

### Rule (agreed)
An **idle** machine is technically active — available, just not running at the moment. It must:
* count as an **active** machine in the zero-output / partially-unutilized warnings (WC-24), so it does **not** trigger "Cost with 0 Output";
* keep its capacity and cost in the snapshot (WC-07, WC-09).

### Current State
| Item | File | Lines | Observation |
| :--- | :--- | :--- | :--- |
| Machine statuses | `app/Domains/Production/Models/Machine.php` | 21–30 | `active`, `inactive`, `under_maintenance`, `decommissioned` — **no `idle` status** |
| Labels | `lang/en/production.php` | 1112 (`idle`), 1124 (`idle_waiting`), 1226 (`machine_idle_limit`) | "Idle" exists as a label / MES concept, not as a machine master status |
| Old decision doc | `docs/calculation_rework_decisions.md` §5 metric 11 | — | `inactive_machines_count` described as *"machines in maintenance **or idle**"* — conflicts with this rule |
| Calculation | `app/Domains/Production/Services/SectionCalculationService.php` | 523–524 | Only `status === 'active'` counts |

### To Check in the Machine Module Review
1. Is "idle" a run-time state (MES / shop floor: not running right now) rather than a master status? If so, master status stays `active` and nothing changes in the calculation.
2. Is `inactive` currently used by users to mean "idle"? If yes, either add an explicit `idle` status that counts as active, or clarify the meaning of `inactive` (not available) in the UI.
3. Update WC-IMPL-09's active-machine filter accordingly (e.g. `whereIn('status', [active, idle])` if an `idle` status is added).
4. Fix the old metric-11 wording ("maintenance or idle") in the audit-metric descriptions (`section-calculation-modal.blade.php` `$auditMetricsMeta`).

---

## WC-IMPL-10: Remove Unused Legacy Helpers in WorkCenterCalculationService
* **Source Decision:** WC-25 rule 4 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

### Problem
`app/Domains/Production/Services/WorkCenterCalculationService.php`:
* `calculateCapacity(WorkCenter)` (lines ~534–538) and `calculateLaborCost(WorkCenter)` (~543–547) call `calculate($workCenter)` **without** section results, so `calculate()` line 59 computes sections on the fly **without saving** snapshots (against WC-25 rule 4).
* They return `$calc['capacity']` / `$calc['labor_cost']` — both keys exist in the `calculate()` payload (lines 184, 191), so the helpers work; they are just unused.
* No callers found (2026-11-10 search). The `calculateCapacity()` / `calculateLaborCost()` calls elsewhere belong to **other** services (`SchedulingService`, `ProductionCostService`) — **do not touch those**.

### Required Change
1. Re-run `grep -rn "workCenterCalculationService\|WorkCenterCalculationService" app tests` and confirm no caller uses these two helpers.
2. Remove both helpers.
3. Optional: in `calculate()` line 59, keep the `?? calculate($section)` fallback only for read-only previews, or remove it so the roll-up always receives saved section results.
4. **(WC-41)** Remove `WorkCenterActivityLogger::logResourceChange()` (`app/Domains/Production/Services/WorkCenterActivityLogger.php` 104–129). No callers found (2026-11-10); re-run `grep -rn "logResourceChange(" app tests` first. It writes log rows with no snapshot behind them, against WC-41 rule 3.

---

## WC-IMPL-11: Direct Staff Hourly Rate Uses Shift Minus Break; Footer Says "over 24h"
* **Source Decision:** WC-27 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

### Problem
| # | File | Lines | Current | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Services/WorkCenterCalculationService.php` | 358–365 | `$shiftDurationHours = $empShift->working_hours ?? 8.0` → `ProductionShift::getWorkingHoursAttribute()` (`ProductionShift.php` 97–111) = **clock − break** | Use shift **clock hours, breaks included** (same rule/helper as WC-18 section personnel). Example: 9 h shift, 1 h break → divide by **9**, not 8. |
| 2 | same | 365 (`$hourlyRate = $allocatedDailySalary / $shiftDurationHours`) | Already uses the **allocated** (WC-split) daily cost ✔ | Keep — hourly rate per Work Center = allocated daily ÷ clock hours (WC-27 rule 2). |
| 3 | same | 367 (`$formula`) | Text omits that hours are clock hours | Show *"… ÷ N active WCs = allocated/day ÷ H shift clock hrs (breaks included) = rate/hr"* (built at display — WC-IMPL-04). |
| 4 | `resources/views/modules/production/work-centers/show.blade.php` | 4156 | `(… / hr over 24h)` with value `total ÷ $wcUniqOperatingHours` | Keep the value; change label to *"/ hr over {format_hours($wcUniqOperatingHours)} operating window"*. |
| 5 | same | Direct Personnel tab rows / hero popover (ISSUE-20, ISSUE-23) | Shows per-person hourly | Will show the corrected rate automatically after #1. |

### Not in This Task
8-hour fallbacks (no shift, start = end, 0 hours) and the date-dependent `resolveShiftForDate(now())` lookup (line 358) → **WC-ISSUE-07** (Shift Module group).

---

## WC-IMPL-12: Overhead Unit "/ day" on Work Center Forms and Show Page
* **Source Decision:** WC-28 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

### Code Mapping
| # | File | Lines | Current | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | `resources/views/modules/production/work-centers/create.blade.php` | 58 | Label `Overhead Cost (₹)` | `Overhead Cost (₹ / day)` + help text *"Enter the overhead per day. If you know the monthly amount, divide it by 30."* |
| 2 | `resources/views/modules/production/work-centers/edit.blade.php` | 61 | Same | Same |
| 3 | `lang/en/production.php` | 789 | `'overhead_cost' => 'Overhead Cost'` | Add e.g. `'overhead_cost_per_day' => 'Overhead Cost'` + `'overhead_cost_help' => 'Enter the overhead per day…'` (or reuse with ` / day` suffix) |
| 4 | `resources/views/modules/production/work-centers/show.blade.php` | 928 (hero card) | Already "/ day" ✔ | Keep |
| 5 | same | 4175, 4180–4181 (Overhead integration card) | Already "flat daily" / "/ day" ✔ | Keep |
| 6 | same | 3881–3882 (reconciliation "+ Mach & Overhead") | No unit | Add "/ day" |
| 7 | same | 4054 (burden callout) | No unit | Add "/ day" |
| 8 | same | 4410–4413 (audit metrics #37–40) | Name says "Daily" ✔ | Keep; check values show "/ day" |
| 9 | same | Column 1 profile / any other overhead display | — | Search `overhead` in the file and add "/ day" where missing |

### Related (other tasks)
* `show.blade.php` 813, 3184: `$latestSnapshot?->overhead_cost ?? $workCenter->overhead_cost` — falls back to the live value when there is no snapshot; review under **WC-42** (snapshot-only display rule).
* Currency conversion of the entered amount → **WC-FINAL-01**.

### WC-FUT-02: Dependencies of Work Center Data — Current Behaviour (for the later decision)

**(a) Inactive / delete cascades**

| Entity & action | File | Lines | What happens today |
| :--- | :--- | :--- | :--- |
| Work Center **delete** | `app/Domains/Production/Services/WorkCenterService.php` | 72–88 | Blocked if any routing operation uses it (*"Deactivate it instead"*); otherwise soft-deletes the Work Center only. **Not handled:** its sections, machines, employee assignments, contractor allocations, manual batches, snapshots, pending countdown. |
| Work Center **inactive** | `WorkCenterService.php` | 40–70 (`update()`) | Status changes; no cascade to sections / machines / assignments; connected routings, BOMs, open production orders not checked. |
| Section **delete** | `app/Domains/Production/Services/WorkCenterSectionService.php` | 157–195 | Blocked if production/routing history (`hasProductionHistory()`); otherwise: machines unassigned (`section_id = null`), employee assignments soft-deleted, contractor allocations soft-deleted, section soft-deleted, queued in countdown. **Not handled:** manual batches (and their employee links). |
| Section **inactive** | `WorkCenterSectionService.php` `update()` | — | Contributes 0 (WC-30); assignments, allocations, batches stay active; routings using it get an advisory (WC-48). |
| Machine inactive / deleted | `MachineService.php` | — | Contributes 0 (WC-07); routing advisory (WC-48); idle meaning → WC-MACH-01. |
| Manual batch inactive / deleted | `SectionManualProductionController.php` | toggle; `destroy()` (~335–365) | Contributes 0. **Delete has NO routing check** (WC-47): clears contractor links, detaches employees, soft-deletes the batch even when routing operations use it (`SectionManualProduction::routingOperations()` line 61 exists but is not checked) → routing operations keep pointing at a deleted batch. Per WC-47 rule 1 it must be blocked with *"deactivate instead"*. |
| Shift inactive / deleted | `ShiftController.php` | 58–86 | No checks — see WC-ISSUE-01 (Shift Module group). |

**Questions for later:** for each entity — block, cascade (deactivate children), detach, or warn? What happens to historical snapshots, activity logs, routing operations, BOM costs and open production orders that reference it? Can it be re-activated, and what comes back?

**(b) Countdown triggers from connected modules / other entry points** (found under WC-33, 2026-11-10)
| # | Change | File | Lines | Today |
| :-: | :--- | :--- | :--- | :--- |
| 1 | Machine moved from section A to section B (machine edit form) | `app/Domains/Production/Controllers/MachineController.php` | 152–155 | Queues `$oldSectionId ?: new` → only **A**. To verify together with the section-side machine assignment flow (user expects assignment inside the section to trigger B). |
| 2 | Machine bulk actions (delete, activate, deactivate) | same, `bulkAction()` | ~ +21–41 from method start | No countdown |
| 3 | Machine API store / update / destroy | `app/Domains/Production/Controllers/Api/MachineApiController.php` | 97, 120, 144 | No countdown |
| 4 | Machine import | `MachineApiController::import()` / `ProductionMasterImportService` | 214 | No countdown |
| 5 | Pay grade hourly rate change | `app/Domains/Production/Controllers/ExternalWorkerPayGradeController.php` | 108–130 | No countdown for sections using that pay grade |
| 6 | Shift edit / deactivate / delete | `app/Domains/Production/Controllers/ShiftController.php` | 58–86 | No countdown (see also WC-ISSUE-01) |
| 7 | HRMS employee salary or shift change | HRMS module | — | No countdown; cross-module → should be an HRMS event that Production listens to (project rule: modules talk through events) |

**Direction noted (not agreed):** put the countdown trigger in the service layer (e.g. `MachineService` create/update/delete) so screens, bulk actions, API and import all trigger it; queue both old and new sections on a move.

---

## WC-IMPL-13: Countdown With Empty Queue → Work Center Roll-Up Only
* **Source Decision:** WC-35 rules 3–4 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

### Problem
`app/Domains/Production/Services/WorkCenterCalculationService.php` 216–218:
```php
$sectionsToRecalculate = empty($impactedSectionIds) ? $allSections : $allSections->whereIn('id', $impactedSectionIds);
```
An empty queue means **all sections**. Any Work Center edit arms the countdown with no section (WC-32, `WorkCenterService.php` 67), so e.g. renaming a Work Center creates a new snapshot + activity log for **every** section plus the roll-up.

### Required Change
| # | File | Lines | Change |
| :-: | :--- | :--- | :--- |
| 1 | `WorkCenterCalculationService.php` | 216–218 | Empty `$impactedSectionIds` → recalculate **no** sections; use latest snapshots (existing branch 245–264), and calculate + save only sections with **no** snapshot yet (existing branch 265–273). |
| 2 | `app/Domains/Production/Services/CountdownCalculationEngine.php` | 114–131 (`recalculateNow()`) | Add a scope parameter: `all` (header button) → pass **all active section IDs** as impacted; `queue` (banner button, browser timer, page reload, cron) → pass the queue as-is. |
| 3 | `app/Domains/Production/Controllers/WorkCenterController.php` | 305–312 (`recalculateNow()`), 120–132 (`show()` auto-process) | Confirm trigger types: button = `manual`, page-reload expiry = `countdown_timer`. |
| 4 | `app/Domains/Production/Services/WorkCenterActivityLogger.php` | — | Verify a Work Center-only run writes **1** Work Center log entry and no section entries (ISSUE-26). |
| 5 | `WorkCenterController.php` + route `work-centers.recalculate-now` | 305–312; `Routes/web.php` 114 | Add a `scope` input: header button → `scope=all` (all active sections); banner button → `scope=queue` (queued sections only). Both recorded as `manual`. |
| 6 | `resources/views/modules/production/work-centers/show.blade.php` | 508–513 (banner form), header Recalculate form, 7432–7434 (JS auto-submit) | Banner form sends `scope=queue`; header form sends `scope=all`; JS auto-submit at 00:00 sends `scope=queue` **and** `trigger=countdown_timer` (today it posts the banner form → recorded as `manual` ✘). Only accept `trigger=countdown_timer` when the countdown end time has really passed. |

---

## WC-IMPL-14: Countdown Robustness (Cron, Lock, Tenant Context)
* **Source Decision:** WC-35 rule 5 (logged 2026-11-10)
* **Status:** 🟡 **Proposed** — recommended fix; user has not confirmed yet.

| # | Problem | File | Lines | Proposed Fix |
| :-: | :--- | :--- | :--- | :--- |
| 1 | One failing Work Center stops the cron for all others (all tenants) that minute; the failing one is retried every minute | `CountdownCalculationEngine.php` | 149–164 (`processExpiredCountdowns()`) | `try/catch` per Work Center; log the error; continue with the next. Consider marking repeated failures so they are not retried forever. |
| 2 | "Recalculate Now" button ignores the running lock → button + browser timer + cron can run the same calculation at once → duplicate snapshots | `WorkCenterController.php` | 305–312 | Refuse (or wait) when `is_calculation_running = true`; better, an atomic lock (`Cache::lock("wc-calc-{$id}")` or a conditional `UPDATE … WHERE is_calculation_running = 0`). |
| 3 | Stuck lock: if the process is killed mid-run (timeout), `is_calculation_running` stays `true` and the cron skips that Work Center forever | `CountdownCalculationEngine.php` | 117–118, 149–154 | Store the lock start time; treat a lock older than N minutes as stale; or use a cache lock with expiry. |
| 4 | Cron runs with no tenant context; any `tenant()`-based helper used during calculation (currency, branding) may behave differently than in the browser | `app/Console/Commands/ProcessCalculationCountdowns.php`; `CountdownCalculationEngine.php` 149–164 | — | Resolve and set `TenantContext` for each Work Center's tenant before calculating; verify after WC-IMPL-04 (numbers-only snapshots reduce this risk). |

---

## WC-IMPL-15: Work Center Snapshot Records the Section Snapshots It Used
* **Source Decision:** WC-37 rule 4 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

### Problem
A Work Center snapshot can reuse older section snapshots for unchanged sections (WC-25 rule 4). Only same-batch section snapshots are linked (`WorkCenterCalculationService.php` 304–309), and the Work Center snapshot's `calculation_details` does not store any section snapshot reference (`calculate()` 76–88). So you cannot tell which section snapshot fed a given Work Center snapshot.

### Required Change
| # | File | Lines | Change |
| :-: | :--- | :--- | :--- |
| 1 | `app/Domains/Production/Services/WorkCenterCalculationService.php` | 222–273 (`calculateAndSave()` — builds `$sectionCalculationSnapshots` and `$sectionCalculationResults` for every section) | Add `'section_calculation_id' => $snapshot->id` and `'section_snapshot_number' => $snapshot->snapshot_number` to each section's entry in `$sectionCalculationResults` (all three branches: fresh, latest, none-yet). |
| 2 | same | 76–88 (`calculate()` → `$sectionDetails[]`) | Copy both keys into each `sections_contribution` item so they are stored in the Work Center snapshot JSON. |
| 3 | `resources/views/modules/production/work-centers/show.blade.php` | Contributing Sections tab (`#wc-sections-pane`, ISSUE-22 drill-down `↗`), WC audit metric drill-down badges (ISSUE-24) | Drill-down opens the section snapshot by `section_calculation_id` (show "Snapshot #N"); fall back to the latest snapshot only for old Work Center snapshots without the key. |
| 4 | Section snapshot modal loading | `section-calculation-modal.blade.php` / `WorkCenterController::calculationDetails()` | Confirm a specific (historical) section snapshot can be opened by ID (ISSUE-26 "View Snapshot #ID" already does this from the activity log — reuse it). |

### Note
Snapshot numbering (`max + 1`, no lock / unique index — `SectionCalculationService.php` 1321, `WorkCenterCalculationService.php` 281) can duplicate under concurrent runs → covered by the lock in **WC-IMPL-14**.

---

## WC-IMPL-16: Work Center Hero Panel — "Calculation Pending" State, No Live Fallback
* **Source Decision:** WC-42 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

### Problem
With no Work Center snapshot yet, the hero panel (`resources/views/modules/production/work-centers/show.blade.php` 3-column header, ~460–960) shows 0 for capacity / labor / machines, hides the unit-cost badge, and uses the **live** overhead:
* line 813: `$overheadFlat = $latestSnapshot?->overhead_cost ?? $workCenter->overhead_cost ?? 0;`
* line 814: `$totalDailyCost = $latestSnapshot?->total_daily_cost ?? ($overheadFlat + $laborCostDaily + $machCostDaily);` → "Total Daily Cost" = overhead alone.
* line 3184: same live overhead fallback in the Work Center calculation modal block.

### Required Change
| # | Place | Lines | Change |
| :-: | :--- | :--- | :--- |
| 1 | Column 2 cards: Capacity / Hour, Daily Production Capacity, Operational Schedule | Column 2 block (before 800) | When `$latestSnapshot` is null → "Calculation Pending" state instead of 0 |
| 2 | Column 3 cards: Daily Labor, Daily Machinery, Facility Overhead, Total Daily Cost (+ unit-cost badge) | 804–960 | Same pending state |
| 3 | Overhead source | 813, 3184 | Snapshot only: `$latestSnapshot?->overhead_cost` (no `?? $workCenter->overhead_cost`); with no snapshot → pending |
| 4 | Total daily cost / cost per hour / cost per unit fallbacks | 814–816 | Remove computed fallbacks; snapshot columns only (N/A rule from WC-IMPL-08 applies) |
| 5 | Column 1 Direct Assigned Staff card | ISSUE-20 ("live direct staff labor calculation") | Check: daily cost badge must come from the snapshot (`direct_staff_daily_labor_cost`) with pending state; the **list of names** may stay live (asset view) |
| 6 | Hover popovers on hero cards | `$wcDailyLaborPopoverHtml` etc. (~823+) | Pending message instead of zero tables (same pattern as ISSUE-10) |

### Keep
* Configured overhead may still be shown **as configuration** on the Work Center edit form / profile, labelled "Configured overhead (/ day)" — not as a calculated cost.

---

## WC-IMPL-17: Modal Footers — Count Active Only, Name What Was Not Counted
* **Source Decision:** WC-43 (agreed 2026-11-10)
* **Status:** 🟢 Agreed — machine active filter depends on **WC-MACH-01** (idle = active)

### A. Section Details modal (`resources/views/modules/production/work-centers/show.blade.php`)
| # | Place | Lines | Current | Required |
| :-: | :--- | :--- | :--- | :--- |
| 1 | Machines tab rows | 2624–2646 (`@forelse($section->machines as $sm)`) | Code, name, capacity, cost — **no status** | Add **Status** badge column (active / under maintenance / inactive / decommissioned; idle per WC-MACH-01). |
| 2 | Machines tab footer | 2648–2651 (`$section->machines->sum('capacity')`, `->sum('operating_cost_per_hour')`) | Sums **all** machines | Sum **active** machines only; below the totals add *"Not counted: {code} ({status}), …"* listing every non-active machine **by code**. |
| 3 | Manual Batches tab footer — workforce | 2810–2816 (`foreach($section->manualProductions as $b)`) | Counts workers of **all** batches | Count **active** batches only; add *"+N workers in inactive batch(es): {batch name}, …"*. |
| 4 | Manual Batches tab footer — output | 2821–2831 | Snapshot daily + average ✔ / "Pending calculation" | Keep. |

### B. Section Calculation modal (`resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php`)
| # | Place | Lines | Current | Required |
| :-: | :--- | :--- | :--- | :--- |
| 5 | Machines & Energy table | 352 (`$machData['machines']`), footer 405–414 | Active only ✔, footer from snapshot ✔ | Keep; add a **"Not counted in this snapshot"** list below from `inactive_machines` (code, name, status). |
| 6 | Manual Batches table | 491 (`$batchData['batches']`), footer 935–944 | Active only ✔, footer from snapshot ✔ | Keep; add **"Not counted in this snapshot"** list from `inactive_manual_batches` (batch name, shift, status). |
| 7 | Data source | `SectionCalculationService.php` 605–608 (`inactive_machines`), 774–776 (`inactive_manual_batches`) | Already stored in the snapshot | Make sure each inactive item stores `code` / `name` / `status` (machine `status` is in the item at line ~576). |

### Check at Implementation
* Old snapshots may not contain `inactive_machines` / `inactive_manual_batches` details — hide the list when missing.

---

## WC-IMPL-18: Shifts Tab Footer & Operating Window Card Use the Calculation Method
* **Source Decision:** WC-44 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

### Problem (`resources/views/modules/production/work-centers/show.blade.php`)
| # | Place | Lines | Current | Problem |
| :-: | :--- | :--- | :--- | :--- |
| 1 | Shifts tab footer hours | 2575–2576 | `$totalNetHours += $sh->working_hours ?? 8.0` | Sum (overlaps counted twice), break subtracted (`ProductionShift::getWorkingHoursAttribute()` 97–111), 8 h fallback |
| 2 | Shifts tab footer window | 2577–2585 | `sort()` start/end time strings → earliest start – latest end | Wrong for overnight shifts |
| 3 | Shifts included | 2568, 2575 (`$section->shifts`) | All assigned shifts | Inactive shifts counted (calculation counts active only) |
| 4 | Footer workforce | 2579–2580 | Employees whose `employee->shift_id` = the shift; contractor allocations on the shift | "(Ext)" employees (WC-06) not counted |
| 5 | Shifts table column "Net Working Hours" | 2532, ~2542 | Shift minus break, `8.00 hrs` fallback | Already in **WC-IMPL-01** (show shift hours, break as info) |
| 6 | Operating Window KPI card | 2353–2366 | Snapshot hours, else Σ `working_hours` "(Uncalculated Fallback)" | Must be snapshot or "Calculation Pending" |

### Required Change
1. Compute the footer with the read-only `SectionCalculationService::calculateOperatingHours($section)` (334–470) — returns union hours, overlap, window, gaps, active shifts only — instead of Blade loops. (Pass it from the controller or a view helper; it does **not** save anything.)
2. Footer shows union operating hours, overlap hours (if any), window (overnight-safe; "24 h" when continuous), shift count (active / inactive).
3. Workforce count: active employee assignments + active contractor allocations of the section (not per HRMS shift), so "(Ext)" employees are included.
4. KPI card: `$secDetailsCalc?->unique_operating_hours`; when `$secDetailsCalc` is null → amber "Calculation Pending" block (same as the other KPI cards, ISSUE-06). Remove the fallback.
5. Inactive shifts: show an "Inactive" badge in the table row; exclude from the footer.

---

## WC-IMPL-19: Canonical Snapshot Keys, Wording & Format Sweep
* **Source Decision:** WC-45 (agreed 2026-11-10)
* **Status:** 🟢 Agreed

### 1. Duplicate alias keys → keep one canonical key
| Snapshot block | File | Lines | Duplicate pairs (keep **bold**) |
| :--- | :--- | :--- | :--- |
| Section personnel (`internal_employees`) | `app/Domains/Production/Services/SectionCalculationService.php` | 905–925 | **`assignment_id`** / `id`; **`name`** / `employee_name`; **`monthly_salary`** / `hrms_net_monthly_salary` (value becomes monthly CTC per WC-IMPL-07); **`shift_clock_hours`** / `shift_duration_hours`; **`hourly_rate`** / `hourly_salary_rate` |
| Contractor allocations (`external_allocations`) | same | 1018–1021 | **`shift_clock_hours`** / `shift_duration_hours` |
| Batch employee details | same | 685 (`monthly_salary`) | Align with personnel keys |
| Direct staff (`direct_work_center_staff.employees`) | `app/Domains/Production/Services/WorkCenterCalculationService.php` | 375–384 | **`monthly_salary`** / `hrms_net_monthly_salary` / `monthly_ctc`; **`active_wc_count`** / `total_active_work_centers`; **`daily_cost`** / `daily_salary` (both = allocated daily cost) |

*Suggested canonical names above — confirm at implementation.* Then update every view that reads the dropped key:
* `resources/views/modules/production/work-centers/partials/section-calculation-modal.blade.php` (personnel tab ~980–1106, batch detail ~515–860)
* `resources/views/modules/production/work-centers/show.blade.php` (direct personnel tab ~4044–4160, hero popover ISSUE-20)
Keep `?? $old['alias']` fallbacks in views for snapshots created before the change.

### 2. Wording
| File | Lines | Current | Required |
| :--- | :--- | :--- | :--- |
| `work-centers/show.blade.php` | 6138–6140 | "Target Speed: X pcs / hr" (manual batch modal) | "Target Capacity: X units / hr" |
| both views | — | search `Speed`, `speed`, `pcs` in labels | "Capacity", "units" (keep internal JS variable names if not shown) |

### 3. Format sweep
Search both views and the two services for `number_format(` applied to hours / durations and `sprintf('%d:%02d'` — replace with `format_hours()` (or `format_duration()` for minutes).

---

## WC-ISSUE-11: Machine Costs Not Converted to Base Currency on Save
* **Source Decision:** WC-46 (logged 2026-11-10)
* **Status:** ⏸️ **Marked — Decide Later** (check together with **WC-FINAL-01**)

### Problem
| Path | File | Current |
| :--- | :--- | :--- |
| Machine form save | `app/Domains/Production/Controllers/MachineController.php` (store 59, update 144), `app/Domains/Production/Services/MachineService.php` | No `convert_to_base()` on `operating_cost_per_hour`, `energy_rate_per_unit` |
| Machine API / import | `app/Domains/Production/Controllers/Api/MachineApiController.php` (97, 120, 214) | Same |
| Machine edit form | `resources/views/modules/production/machines/edit.blade.php` | Shows stored value as-is (no `convert_from_base()`) |
| Display / calculation | `SectionCalculationService.php` 526–564; views with `format_currency()` | Treats stored value as **base** → converted again at display |

**Example (base USD, tenant INR, rate 83):** entered ₹500/hr → stored 500 → read as $500 → shown ₹41,500/hr.

### Direction noted (not agreed)
Convert both fields to base on every save path (form, API, import, bulk); convert from base on the edit form; the form's live JS labels keep working in the entered currency.

---

## WC-ISSUE-12: HRMS Salary Currency vs Production Base Currency
* **Source Decision:** WC-46 (logged 2026-11-10)
* **Status:** ⏸️ **Marked — Decide Later** (check together with **WC-FINAL-01**; 📌 HRMS-owned field)

### Problem
| Item | File | Lines | Current |
| :--- | :--- | :--- | :--- |
| HRMS save | `app/Domains/HRMS/Controllers/EmployeeController.php` | 634 | `current_salary` validated and stored as typed — no `convert_to_base()` anywhere in HRMS |
| HRMS display | `resources/views/modules/hrms/employees/tabs/compensation.blade.php` | 130 | Hard-coded `₹` + `number_format()` (treated as tenant currency) |
| Production use | `SectionCalculationService.php` 676, 855, 1439; `WorkCenterCalculationService.php` 353 | — | Treated as **base**; results shown with `format_currency()` (converted again) |

**Example (base USD, tenant INR, rate 83):** annual CTC ₹6,00,000 → after ÷12 ÷30 = 1,667 → shown as ₹1,38,333/day instead of ₹1,667.

### Options noted (not agreed)
* **Convert in Production:** the HRMS `monthlyCtc()` method (WC-IMPL-07) is treated as tenant currency; Production applies `convert_to_base()` before calculating. No HRMS data / payroll change.
* **HRMS stores base:** HRMS converts on save and on every display — affects payroll, payslips, FnF and all HRMS screens.

---

## WC-RTG-02: Inactive Resource After a Routing Uses It
* **Source Decision:** WC-48 (moved 2026-11-10)
* **Status:** 🔀 **For Routing Review** — routing rule; not decided in the Work Center review.

### Intended rule (old WC-TRACK-06 Point 6.1)
* **Approved routings** stay unchanged and show an advisory: *"Notice: Operation [OP-XXX] is assigned to [Resource Name], which is currently inactive."*
* **Draft routings** cannot select inactive sections, machines or manual batches.

### Current state
| # | Item | File | Lines | Today |
| :-: | :--- | :--- | :--- | :--- |
| 1 | Routing form offers only active resources | `app/Domains/Production/Controllers/RoutingController.php`; `SectionCalculationService::getSectionResourceCosts()` (endpoint `sections/{section}/resources`, `Routes/web.php` 121) | 48, 149; 1398–1407 | ✔ active Work Centers / sections preloaded; endpoint returns active machines / batches only |
| 2 | Backend active check | `app/Domains/Production/Requests/StoreRoutingRequest.php`, `UpdateRoutingRequest.php` (+ API requests) | — | ✘ no check that section / machine / batch is active (API or crafted form can save inactive) |
| 3 | Advisory on approved routings | `resources/views/modules/production/routing/show.blade.php` (and other routing views) | — | ✘ no "inactive" notice anywhere, although WC-TRACK-06 / WC-TRACK-08 were marked Applied |
| 4 | Editing a draft that already uses a now-inactive resource | `routing/edit.blade.php` | — | ✘ the resource is not in the dropdown → silently dropped from the row |
| 5 | Feature test claimed for this | `tests/Feature/Production/WorkCenterRoutingIntegrationTest.php` | — | Check what "approved routing safeguards for inactive resources" actually asserts |
