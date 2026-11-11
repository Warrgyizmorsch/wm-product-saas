# Work Center — Final Decisions & Business Rules

> **Purpose:** The single, final source of agreed Work Center decisions (sections, shifts, machines, manual batches, labor, calculation engine, snapshots, display rules, safeguards).
> Decisions are reviewed one at a time (WC-01 … WC-48) and written here only once agreed.
> **Source documents reviewed:** `docs/calculation_rework_decisions.md`, `docs/calculation_audit_issues_tracker.md`, `docs/work_center_routing_linking_tracker.md` (work-center side only).
> **Implementation tasks:** [`docs/work_center_implementation_tasks.md`](work_center_implementation_tasks.md)

---

## Review Progress

| ID | Topic | Status |
| :--- | :--- | :--- |
| WC-01 | Shifts assigned per section; machines run across all section shifts | ✅ Agreed |
| WC-02 | Breaks counted as production time for machines, manual batches and labor | ✅ Agreed (revised) |
| WC-03 | Section operating hours = union of active shift windows | ✅ Agreed |
| WC-04 | Section with no active shift (fallback & prevention) | ⏸️ Marked — decide later (see WC-ISSUE-01 … 04) |
| WC-05 | Timeline windows cut at shift boundaries; exist for machine-only sections | ✅ Agreed |
| WC-06 | Employee shift vs section shifts (assignment eligibility & "(Ext)" employees) | ✅ Agreed (open shift issues → WC-ISSUE-05 … 07) |
| WC-07 | Machine daily capacity (active machines only; generic units) | ✅ Agreed |
| WC-08 | Machine operating cost: entered cost vs base energy cost | ✅ Agreed (fix → WC-IMPL-04, Method A) |
| WC-09 | Machine daily cost (active machines only) | ✅ Agreed |
| WC-10 | Terminology: "Energy Rate per Unit", never "Tariff Rate" | ✅ Agreed (fix → WC-IMPL-05) |
| WC-11 | Machine form: auto-fill, live label, mismatch confirmation | ✅ Agreed |
| WC-12 | Manual production only via manual batches (old section columns dropped) | ✅ Agreed (cleanup → WC-IMPL-06) |
| WC-13 | Manual batch output = capacity × its shift clock hours; batch creation rules | ✅ Agreed (open shift issues → WC-ISSUE-08, 09) |
| WC-14 | Average | WC-14 … WC-48 | — | ⏳ Pending review | peak manual capacity per hour (with explanatory tooltip) | ✅ Agreed |
| WC-15 | No time-of-day "current speed"; average everywhere, peak only in calculation modals | ✅ Agreed |
| WC-16 | Per-batch labor cost breakdown (not added to section totals again) | ✅ Agreed |
| WC-17 | Internal daily salary = HRMS payroll monthly CTC ÷ 30 | ✅ Agreed (fix → WC-IMPL-07) |
| WC-18 | Internal hourly rate = daily salary ÷ shift clock hours; same rate everywhere | ✅ Agreed (open → WC-ISSUE-10; routing note WC-RTG-01) |
| WC-19 | Contractor labor cost (headcount × rate × shift clock hours; groups per shift) | ✅ Agreed |
| WC-20 | Peak labor cost = highest single timeline-window total | ✅ Agreed |
| WC-21 | Average hourly labor = daily labor ÷ section operating hours | ✅ Agreed |
| WC-22 | Section capacity = parallel sum of machine + manual; average | WC-22 … WC-48 | — | ⏳ Pending review | peak per hour | ✅ Agreed |
| WC-23 | Section cost per day / hour / peak; cost per unit ("N/A" when output 0 and cost > 0) | ✅ Agreed (fix → WC-IMPL-08) |
| WC-24 | Zero-output | WC-24 … WC-48 | — | ⏳ Pending review | partially-unutilized labor warnings (active machines only, clear reason) | ✅ Agreed (fix → WC-IMPL-09) |
| WC-25 | Work Center capacity rollup (active sections; union operating hours; every section saved in batch) | ✅ Agreed |
| WC-26 | Direct Work Center staff: daily cost split across active Work Centers | ✅ Agreed |
| WC-27 | Direct staff hourly rate = allocated daily cost ÷ shift clock hours (same rule as WC-18) | ✅ Agreed (fix → WC-IMPL-11; shift fallbacks → WC-ISSUE-07) |
| WC-28 | Overhead is a flat amount PER DAY; unit shown on add/edit forms and show page | ✅ Agreed (fix → WC-IMPL-12) |
| WC-29 | Work Center cost totals (labor + machines + overhead per day; per hour; per unit) | ✅ Agreed |
| WC-30 | Inactive / deleted sections contribute 0 but are tracked in the batch | ✅ Agreed |
| WC-31 | Page loads never start a calculation (except finishing an expired countdown) | ✅ Agreed |
| WC-32 | Create = no calculation; any Work Center edit arms the countdown | ✅ Agreed |
| WC-33 | Countdown: changes to Work Center data arm it; later changes append without reset | ✅ Agreed (connected-module triggers → WC-FUT-02) |
| WC-34 | Countdown length 1–1440 min (default 10); locked while running | ✅ Agreed |
| WC-35 | Countdown expiry: one batch of queued sections + roll-up; empty queue = Work Center only | ✅ Agreed (fix → WC-IMPL-13; robustness → WC-IMPL-14 proposed) |
| WC-36 | Immediate calculation only by buttons: header = all sections, banner = queued only; browser timer = countdown | ✅ Agreed (fix → WC-IMPL-13) |
| WC-37 | Snapshots are never edited; Work Center snapshot records exactly which section snapshots it used | ✅ Agreed (fix → WC-IMPL-15) |
| WC-38 | Snapshot numbers (#1, #2 …) per section / Work Center; never database IDs; "#N ↗" opens that exact snapshot | ✅ Agreed (rule 3 corrected 2026-11-11 → WC-IMPL-20) |
| WC-39 | Audit details: snapshots store numbers; UI shows plain-English formulas; 52 + 47 audit metrics; no raw JSON | ✅ Agreed |
| WC-40 | Key figures in dedicated snapshot columns; more promoted only after testing | ✅ Agreed |
| WC-41 | Activity log = completed calculations only (one row per snapshot, linked by batch) | ✅ Agreed (cleanup → WC-IMPL-10) |
| WC-42 | Calculated figures from snapshots only; "Calculation Pending" when none (incl. Work Center hero panel) | ✅ Agreed (fix → WC-IMPL-16) |
| WC-43 | Footers in Section Details (live) and Section Calculation (snapshot) modals; not-counted items named by code | ✅ Agreed (fix → WC-IMPL-17, kW label → WC-IMPL-05) |
| WC-44 | Shifts tab footer uses calculation method (union of active shifts); Operating Window card = snapshot or "Calculation Pending" | ✅ Agreed (fix → WC-IMPL-18) |
| WC-45 | Hours via format_hours(); "Capacity" not "Speed"; generic "units"; one canonical key per value | ✅ Agreed (cleanup → WC-IMPL-19) |
| WC-46 | Currency: stored in base, converted at display | ⏸️ Rule recorded; machine | WC-46 … WC-48 | — | ⏳ Pending review | HRMS salary conversion marked — decide later (WC-ISSUE-11, 12) |
| WC-47 | Delete protection: resources used by routing / orders / schedules cannot be deleted — deactivate instead | ✅ Agreed (batch gap → WC-FUT-02) |
| WC-48 | Inactive resource after a routing uses it (approved advisory, drafts blocked) | 🔀 Moved to Routing Review (WC-RTG-02) |

**Review completed 2026-11-10:** 48 / 48 reviewed — **45 agreed**, **2 marked to decide later** (WC-04 shift fallback, WC-46 currency gaps), **1 moved to the routing review** (WC-48).
Open items are tracked in [`work_center_implementation_tasks.md`](work_center_implementation_tasks.md): 📌 Shift Module group (WC-IMPL-02, WC-ISSUE-01 … 10), currency (WC-ISSUE-11, 12 with WC-FINAL-01), future (WC-FUT-01, 02, WC-MACH-01, WC-HRMS-01), routing review (WC-RTG-01, 02).

---

## A. Shifts & Operating Hours

### WC-01: Shift Assignment Level
* Shifts are assigned per **Section** (`production_work_center_sections.assigned_shifts`).
* All active machines in a section run across **all** of that section's active shifts.
* Machines are never assigned to individual shifts (no `shift_id` on `production_machines`).
* *Origin:* `calculation_rework_decisions.md` Q2.

### WC-02: Breaks Are Counted as Production Time
1. **Machines** produce for the full section operating hours; breaks do not reduce machine output.
2. **Manual batches** also produce for the **full shift clock hours, breaks included**: `Batch Daily Output = Batch Capacity per Hour × Shift Clock Hours`. → Task **WC-IMPL-01**.
3. **Labor** (internal employees and external contractors) is paid for the full shift including breaks.
4. **Breaks are informational only** — shown on the shift, never subtracted from output, cost or operating hours.
5. **Timeline:** because breaks are not subtracted anywhere, timeline window output adds up to the section's daily capacity with no special break handling.
6. Break vs duration validation belongs to the **Shift module** (separate UI) and will be handled there. → Task **WC-IMPL-02** (📌 marked).
* *Origin:* `calculation_rework_decisions.md` Q3; revised 2026-11-10 (earlier same-day version excluded breaks for manual batches — replaced).

### WC-03: Section Operating Hours
1. **Operating Hours = union of the section's active shift windows** on a 24-hour clock. Overlapping time is counted once; maximum is 24 hours; overnight shifts (e.g. 22:00–06:00) wrap across midnight. Never hardcode 24 hours.
2. **Gaps** between shifts are idle time: not counted in operating hours, shown as *inactive gaps*.
3. **Inactive shifts** are excluded from all calculations.
4. A shift whose **start time equals its end time** (e.g. 06:00–06:00) is a **24-hour shift**.
5. **Per operating day:** all capacity and cost figures are per operating day. No weekly-off or holiday calendar is applied. (Salary stays Monthly CTC ÷ 30 — see WC-17 — so salary is charged for 30 days even if the plant works fewer days.)
* *Code reference:* `app/Domains/Production/Services/SectionCalculationService.php::calculateOperatingHours()` lines 334–470 (1440-minute coverage map). Matches this decision — no change needed.
* *Origin:* `calculation_rework_decisions.md` §2B; confirmed 2026-11-10.

### WC-04: Section with No Active Shift — ⏸️ MARKED, DECIDE LATER
* **Confirmed rule so far:** A section cannot be **saved** without at least one shift (`StoreWorkCenterSectionRequest.php:58`, `UpdateWorkCenterSectionRequest.php:60`).
* **Open:** The section can still end up with 0 active shifts after saving, and the calculation fallback is inconsistent. Logged as **WC-ISSUE-01 … WC-ISSUE-04** in [`work_center_implementation_tasks.md`](work_center_implementation_tasks.md). Proposal recorded there; not agreed yet.
* *Origin:* `calculation_rework_decisions.md` Remaining Decision 2.

### WC-05: Timeline Windows
1. The section's day is cut into **timeline windows at shift boundaries** (e.g. `06:00–14:00`, `14:00–14:30 overlap`, `14:30–23:00`).
2. Windows are generated whenever the section has at least one active shift — **including machine-only / unstaffed sections** (0 employees, 0 contractors). Only a section with no active shift has no windows.
3. Each window records: duration, active shifts, running capacity (machine, manual, total), running hourly labor cost (internal, external, total), machine cost rate, total cost rate, window output and window cost.
4. For unstaffed windows the explanation shows machine capacity instead of a $0.00 labor rate.
5. Breaks are not subtracted in windows (WC-02), so window outputs add up to the section's daily capacity.
* *Code reference:* `SectionCalculationService.php` lines 1049–1051 (only stops when no shifts), 1190–1232 (window values & explanation). Matches this decision — no change needed (after WC-IMPL-01).
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-15; confirmed 2026-11-10.

### WC-06: Employee Shift vs Section Shifts
**Assignment eligibility** (confirmed working — `app/Domains/Production/Services/WorkCenterEmployeeService.php`):
1. An employee must have an **active HRMS master shift** to be assigned (lines 603–608).
2. The target section must have **active shifts** (lines 610–614).
3. **Exact match:** if the employee's shift is one of the section's shifts → eligible (lines 458–471).
4. **Coverage match:** otherwise the employee's shift is compared minute-by-minute with the **union of the section's shift windows** (lines 473–518):
   * **Section level:** the employee's whole shift must lie **inside** the section's operating hours; any minute outside → blocked (lines 535–555).
     *Example:* employee 08:00–14:00, section shifts 06:00–12:00 and 12:00–18:00 → eligible.
   * **Work Center level:** partial overlap is allowed as long as overlap > 0 (lines 558–576).
   * **Zero overlap** → always blocked (lines 523–533).
5. The same check runs when an assignment is re-activated (lines 247, 297).
6. **Exclusivity** (lines 105–158): an employee cannot hold a Work Center-level and a Section-level assignment at the same time; an employee can be in only **one section**; an employee **can** be in several Work Centers (WC level).

**Calculation for these employees:**
7. Daily cost = full daily salary (Monthly CTC ÷ 30) — see WC-17.
8. Hourly rate = daily salary ÷ shift clock hours, where the shift is chosen in this order: **(1) the employee's manual batch shift** if they are in a batch, **(2) the employee's own HRMS shift**, **(3) the section's first shift** (`SectionCalculationService.php` 846–851). See WC-18. *(Corrected 2026-11-10 during WC-17 review.)*
9. An employee whose shift is not one of the section's shifts (but lies inside section hours) is shown with an **"(Ext)" badge** — meaning *"shift not a section shift, but inside section hours"* (`section-calculation-modal.blade.php`, `is_outside_section_shifts` flag, `SectionCalculationService.php` line 913).
10. Such employees belong **inside the timeline**, during their own shift times (guaranteed inside section hours by rule 4). → Currently not done: **WC-ISSUE-05**.

**Open shift-related issues** (to be handled with the Shift module): **WC-ISSUE-05** (timeline excludes "(Ext)" employees), **WC-ISSUE-06** (24-hour employee shift → 8 h), **WC-ISSUE-07** (no re-check after assignment; 8-hour guesses).
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-13; corrected and confirmed 2026-11-10 after full review of the assignment code.

---

## B. Machines

### WC-07: Machine Daily Capacity
1. **Machine Daily Capacity = Σ (capacity per hour of each active machine) × Section Operating Hours** (WC-03).
2. Only machines with status **`active`** count. `inactive`, `under_maintenance` and `decommissioned` machines contribute **0 capacity for the whole day**; they are still listed in the snapshot as inactive.
3. **Why 0 for the whole day:** a snapshot is a prediction made at that moment. How long maintenance (or inactivity) will last is unknown, so the snapshot treats the machine as unavailable for the full day. When the machine becomes active again, the next calculation picks it up.
4. **Capacity unit is generic** ("units per hour", from `production_machines.capacity`). No unit of measure is attached for now; all machine outputs in a section are summed as generic units. A capacity unit of measure may be added later as a feature (see Future Features in the tasks file).
* *Code reference:* `SectionCalculationService.php::calculateSectionMachines()` lines 501–570 (status check 523–524, capacity 526, daily capacity 566); statuses in `Machine.php` 21–24. Matches this decision — no change needed.
* *Origin:* `calculation_rework_decisions.md` §2A; confirmed 2026-11-10.

### WC-08: Machine Operating Cost
1. **Base Energy Cost / hr = Consumption per Hour × Energy Rate per Unit** (`production_machines.consumption_per_hour × energy_rate_per_unit`).
2. **Final Operating Cost / hr** = the **entered** `operating_cost_per_hour` if filled in; otherwise the base energy cost; otherwise 0.
3. When both entered cost and base energy cost exist and differ, the difference is recorded:

| Entered cost/hr | Base energy cost/hr | Final cost/hr | Recorded as |
| :--- | :--- | :--- | :--- |
| > 0 | > 0, entered higher | entered | **Additional cost** = entered − base (consumables, lubricants, tooling wear) |
| > 0 | > 0, entered lower | entered | **Deductibles / subsidy** = base − entered (power subsidy, solar credits, discounts) |
| > 0 | > 0, equal | entered | Matches base |
| > 0 | 0 | entered | Flat operating cost |
| 0 / blank | > 0 | base | Calculated from energy consumption |
| 0 / blank | 0 | 0 | No energy or operating cost entered |

4. All money in snapshot explanation texts must follow the tenant currency rule (WC-46), never a hardcoded `$`. Snapshots store the **numbers only**; the sentence is built when displayed, in the viewer's current tenant currency. → Task **WC-IMPL-04** (Method A).
* *Code reference:* `SectionCalculationService.php` lines 526–564 — logic matches; only the explanation text currency is wrong.
* *Origin:* `calculation_rework_decisions.md` §4A; confirmed 2026-11-10.

### WC-09: Machine Daily Cost
1. **Machine Daily Cost = Σ (Final Operating Cost per hour of each active machine) × Section Operating Hours** (WC-03, WC-08).
2. A machine that is not `active` (inactive, under maintenance, decommissioned) contributes **0 cost for the whole day** in the snapshot — same reasoning as WC-07 (the snapshot is a prediction at that moment). It is still listed as inactive.
3. The section snapshot also stores, from active machines only: total consumption per hour, total base energy cost per hour, total additional cost per hour, total deductibles per hour.
* *Code reference:* `SectionCalculationService.php` line 567 (per-machine daily cost), 595–608 (active-only totals), 611–622 (returned totals). Matches this decision — no change needed.
* *Origin:* `calculation_rework_decisions.md` §4A; confirmed 2026-11-10.

### WC-10: Terminology — Energy Rate
1. The machine rate is called **"Energy Rate per Unit"** everywhere (`production_machines.energy_rate_per_unit`). It is generic: electricity per kWh, diesel per litre, gas, etc. Never "Tariff Rate".
2. In calculation views and audit texts, consumption is called **"Consumption per Hour"** (`consumption_per_hour`), never "kW" or "Power" (machines are not only electric). The **machine form** shows the unit for the selected energy source (`energy_source`): Electricity → kWh/hr and cost per kWh; Petrol/Diesel → L/hr and cost per litre; Others → units/hr and cost per unit (`machines/create.blade.php` 102–106, 162–175).
3. **"Tariff rebate" / "Energy Tariff Incentive" stay** where they are used as **examples of a subsidy** (machine form tooltips and submit confirmation, section calculation modal tooltip) — they describe the subsidy, not the rate.
4. Audit metric #15 still describes electricity/kW only → Task **WC-IMPL-05**.
* *Code reference:* "Energy Rate" header already applied (ISSUE-01). Remaining "tariff" words: `machines/create.blade.php` 126, 316; `machines/edit.blade.php` 158, 348; `section-calculation-modal.blade.php` 344 (keep), 1228 (fix).
* *Note:* old `docs/calculation_rework_decisions.md` still says "tariff" (lines 159, 211, 319); this file supersedes it, so it is left unchanged.
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-01; confirmed 2026-11-10.

### WC-11: Machine Form — Operating Cost Help & Confirmation
1. **Auto-fill:** when the user types **consumption per hour** or **energy rate per unit**, the operating cost field is filled automatically with `Consumption × Rate`. This **overwrites** any value typed earlier (agreed: keep overwrite — consumption/rate are the source of truth; the user re-enters any extra cost or subsidy afterwards).
2. **Override:** the user may then change the operating cost by hand. A live label under the field shows the difference:
   * higher → *"Additional Expense: +X/hr above baseline energy (Y/hr)"*
   * lower → *"Operating Grant / Subsidy: −X/hr below baseline energy (Y/hr)"*
   * equal → the formula and result
   * no consumption/rate → *"Flat Operating Cost"*
   A **reset to baseline** link sets the cost back to `Consumption × Rate`.
3. **Info tooltip** next to operating cost explains baseline energy cost vs additional expense vs subsidy.
4. **Save confirmation:** if consumption and rate are both filled and the entered cost differs from `Consumption × Rate` by more than **0.01**, saving asks for confirmation (*"Additional Operating Expense Specified"* / *"Operating Grant / Subsidy Specified"*, buttons *"Save with Additional Expense"* / *"Save with Applied Subsidy"*).
5. Units follow the selected energy source (WC-10 rule 2).
* *Code reference:* `resources/views/modules/production/machines/create.blade.php` — tooltip 123–129, auto-fill/live label 199–249, listeners 256–278, save confirmation 287–330; `edit.blade.php` mirrors it. Matches this decision — no change needed. (Currency of amounts in this form → WC-FINAL-01.)
* *Origin:* `calculation_rework_decisions.md` §4B; confirmed 2026-11-10.

---

## C. Manual Batches

### WC-12: Manual Production Lives Only in Manual Batches
1. Manual production exists **only** as manual batches (`production_section_manual_productions`), each with its own shift, capacity per hour, employees (`production_section_manual_production_employees`) and contractor allocations.
2. A section "supports manual production" when it has **at least one active batch** — there is no separate on/off flag on the section (`WorkCenterSection::supportsManualProduction()`, lines 43–46).
3. The old section columns `supports_manual_production` and `manual_capacity_per_hour` are **dropped** (migration `2026_10_27_000001_drop_legacy_columns_from_production_sections_and_calculations.php`).
4. Leftover code that still refers to the dropped columns is removed → Task **WC-IMPL-06**.
* *Origin:* `calculation_rework_decisions.md` Q4; confirmed 2026-11-10.

### WC-13: Manual Batch Output & Creation Rules
**Output:**
1. **Batch Daily Output = Batch Capacity per Hour × the batch's shift clock hours** (breaks included — WC-02). → Task **WC-IMPL-01**.
2. **Section Manual Daily Output = Σ daily output of active batches.** Inactive batches contribute 0 (listed as inactive).

**Creation / edit rules** (confirmed working — `app/Domains/Production/Controllers/SectionManualProductionController.php`):
3. Required: batch name, shift, capacity per hour (> 0) (lines 31–40 store, ~152–160 update).
4. A batch must have **at least one worker** — internal employee or external contractor allocation (lines 43–48).
5. Only employees assigned **directly to this section** can be selected; Work Center-level staff cannot (lines 50–66).
6. A worker can be in only **one active batch** at a time (`validateWorkerExclusivity()`, lines 372–416).
7. Contractor allocations linked to the batch must belong to the same section (store, `->where('section_id', $section->id)`).
8. The batch form lists only the **section's active shifts** (`resources/views/modules/production/work-centers/show.blade.php` 5852–5865).

**Open shift-related issues** (to be handled with the Shift module): **WC-ISSUE-08** (backend does not check the batch shift belongs to the section), **WC-ISSUE-09** (batch workers not checked against the batch shift).
* *Origin:* `calculation_rework_decisions.md` §2A; confirmed 2026-11-10.

### WC-14: Average & Peak Manual Capacity per Hour
1. **Average Manual Capacity per Hour = Section Manual Daily Output ÷ Section Operating Hours** (0 when operating hours = 0).
2. **Peak Manual Capacity per Hour** = the highest combined capacity of batches running at the same time (taken from the timeline windows when available, so overlapping shifts are added together; otherwise the highest per-shift total).
3. Wherever the average is shown, a **tooltip** explains it: *"It is average production per hour throughout section shifts"*, with the live formula (e.g. `440.0 units / 16.5 hrs = 26.7 u/h`) — so it is not confused with a batch's own capacity.
* *Example:* one batch at 55 u/h on an 8 h shift, section open 16.5 h → daily 440 units, average **26.7 u/h**, peak **55 u/h**.
* *Code reference:* `SectionCalculationService.php` 780–783 (average), 117 and 785–788 (peak); tooltips `work-centers/show.blade.php` 2824, `section-calculation-modal.blade.php` 431, 940. Matches this decision — no change needed.
* *Origin:* `calculation_rework_decisions.md` §2B; `calculation_audit_issues_tracker.md` ISSUE-17; confirmed 2026-11-10.

### WC-15: No "Current Speed" Display
1. A time-of-day **"current / active speed"** (depends on which shift is running when the page is opened) is **never shown**.
2. Capacity is shown as **per day** and **average per hour** everywhere.
3. **Peak per hour** values (capacity, operating cost, labor rate) are fixed snapshot figures — the highest rate during overlapping shifts. They are shown **only in the calculation modals** (KPI cards, Manual Batches tab "Peak Concurrent Capacity", audit metrics), never in the main section row or its popovers.
4. Popovers show the uniform columns *Capacity Source · Total Units / Day · Avg Units / Hour*; with no snapshot they show the "Calculation Pending" warning (WC-42).
* *Code reference:* `section-calculation-modal.blade.php` 136, 155, 241 (KPI peaks), 434–435 (batch peak), audit metrics 25, 33, 40, 44, 48, 51; `work-centers/show.blade.php` 3447 (WC KPI peak), WC audit metrics 10, 17, 29, 44. No "current speed" found. Matches this decision — no change needed.
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-10; confirmed 2026-11-10.

### WC-16: Per-Batch Labor Cost Breakdown
1. **Batch internal labor:** each employee in the batch → daily cost = daily salary (Monthly CTC ÷ 30); hourly rate = daily salary ÷ the batch's shift clock hours.
2. **Batch contractor labor:** each allocation in the batch → hourly cost = headcount × pay-grade hourly rate; daily cost = hourly cost × the batch's shift clock hours.
3. **Batch totals:** hourly cost, daily cost, and **Batch Cost per Unit = Batch Daily Labor Cost ÷ Batch Daily Output**. Per-person details are stored in the snapshot (`internal_employees_details`, `external_contractors_details`).
4. **Breakdown only:** batch labor is a view of the section's labor. Section labor totals are calculated from all section workers, each counted once (WC-17 … WC-21) — batch costs are **never added a second time**.
5. **Used by routing:** a manual-batch routing operation's labor cost per unit = batch hourly labor cost ÷ batch capacity per hour. After WC-IMPL-01 this equals rule 3's batch cost per unit.
6. **UI:** expandable batch row in the section calculation modal — Internal Employees card, External Contractors card, total banner (hourly, daily, cost per unit); an empty category collapses to a one-line banner.
* *Note (corrected 2026-11-10):* section personnel costing also uses the **batch shift first** for employees in a batch, so their hourly rate is the same in both places. Whether a batch employee's own HRMS shift must match the batch shift is logged under **WC-ISSUE-09** (Shift Module group).
* *Code reference:* `SectionCalculationService.php` 631–760 (batch labor), 731–733 (totals), 1487–1503 (`getSectionResourceCosts()` for routing); UI `section-calculation-modal.blade.php` ~515 onward. Matches this decision — no change needed.
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-12; confirmed 2026-11-10.

---

## D. Labor

### WC-17: Internal Employee Daily Salary
1. **`employees.current_salary` holds the ANNUAL CTC** (HRMS label "Annual CTC"; payroll divides it by 12; salary slabs use `min_ctc`/`max_ctc`).
2. **Monthly CTC comes from HRMS payroll's own rule** (Option B): HRMS exposes one method (e.g. `Employee::monthlyCtc()` or an HRMS service) returning the payroll monthly CTC — today `current_salary ÷ 12`, the same rule payroll uses (`PayrollCalculationService.php` line 268). **Production calls that method and never divides `current_salary` itself.** If payroll changes its rule, Production follows.
3. **Daily Salary = Monthly CTC ÷ 30** (holidays and weekends are paid — see WC-03 rule 5). **Daily cost to the section = Daily Salary**, whatever the shift length.
4. The full **payroll run** (`calculateSalary()` — attendance, LOP, ad-hoc components for a month) is **not** used for costing: a snapshot needs the cost rate, not last month's paid amount.
5. No salary structure / slab is needed for the total, so employees without a matching structure are still costed correctly.
6. Only **active assignments of active employees** (`employees.status = true`) are counted.
7. A missing salary (0) is costed as 0 with a warning (*"Missing HRMS salary"* — currency text per WC-IMPL-04).
* *Current bug:* Production reads `current_salary` as monthly → labor cost **12× too high** for real HRMS data. → Task **WC-IMPL-07**.
* *Origin:* `calculation_rework_decisions.md` Q1; corrected and confirmed 2026-11-10 after checking HRMS.

### WC-18: Internal Employee Hourly Rate
1. **Hourly Rate = Daily Salary ÷ Shift Clock Hours** (breaks included — breaks are paid, WC-02).
2. **Which shift:** (1) the employee's manual batch shift if in a batch, (2) the employee's own HRMS shift, (3) the section's first shift (WC-06 rule 8).
3. Hourly Rate × Shift Clock Hours = Daily Salary, so timeline window labor cost and the daily figure agree.
4. The hourly rate drives timeline window labor cost, peak labor rate, and routing operator labor cost.
5. **The same employee must have the same hourly rate everywhere** (section calculation, timeline, routing). Today routing differs for "(Ext)" employees → **WC-ISSUE-10** (Shift Module group).
* *Routing note:* routing operator labor adds all non-batch section workers' hourly rates as if they all work at the same time → recorded for the routing review as **WC-RTG-01** (not decided here).
* *Code reference:* `SectionCalculationService.php` 866–898 (section personnel ✔), 1418–1443 (`getSectionResourceCosts()` — see WC-ISSUE-10, WC-RTG-01).
* *Origin:* `calculation_rework_decisions.md` Q1, §3A; confirmed 2026-11-10.

### WC-19: External Contractor Labor Cost
1. Each **allocation** is one contractor group: section, shift, pay grade, headcount (`production_external_worker_allocations.number_of_external_workers`).
2. **Allocation Hourly Cost = Headcount × Pay-Grade Hourly Rate** (`production_external_worker_pay_grades.hourly_rate`).
3. **Allocation Daily Cost = Allocation Hourly Cost × the allocation's Shift Clock Hours** (breaks included — contractors are paid for the full shift, WC-02).
4. **Several groups per shift are allowed** (e.g. 4 helpers at 10/hr + 2 welders at 15/hr); their costs add up. Hourly cost per shift is also stored and feeds the timeline and peak labor rate.
5. **Section Contractor Daily Cost = Σ active allocations.** Inactive allocations are listed with cost 0.
6. An allocation linked to a manual batch (`manual_production_id`) still counts **once** in section labor; the batch view is a breakdown only (WC-16).
* *Code reference:* `SectionCalculationService.php` 981–1047. Matches this decision — no change needed. (8-hour fallback when the allocation shift is not a section shift → WC-ISSUE-03.)
* *Origin:* `calculation_rework_decisions.md` §3B; confirmed 2026-11-10.

### WC-20: Peak Labor Cost During Shift Overlap
1. **Window Labor Cost per Hour = Σ hourly rates of all internal staff and contractors working in that timeline window.** When shifts overlap, both crews are on site and their costs add up.
2. **Peak Labor Cost per Hour = the highest window total** — **not** peak internal + peak external (they can peak in different windows; adding them would overstate the peak). *This corrects metric #44 in old `calculation_rework_decisions.md` §5.*
3. **Peak Internal** and **Peak External** hourly rates are each the highest window value for that group, shown for information.
4. The window holding the peak, when it is an overlap, is flagged `is_peak_overlap` for the UI.
* *Known gap:* "(Ext)" employees are not yet placed in timeline windows (WC-ISSUE-05), so the peak can be understated until that is fixed.
* *Code reference:* `SectionCalculationService.php` 1176–1190 (window labor), 1248–1271 (peaks), 1287 (`peak_hourly_labor_cost`), 134 (used as `peak_hourly_labor_rate`). Matches this decision — no change needed.
* *Origin:* `calculation_rework_decisions.md` §3C; confirmed 2026-11-10.

### WC-21: Average Hourly Labor Cost
1. **Average Internal / External / Combined Labor Cost per Hour = matching Daily Labor Cost ÷ Section Operating Hours** (0 when operating hours = 0).
2. It is an average over the **whole section window**, not any one worker's hourly rate. Example: one employee at ₹1,600/day on an 8 h shift in a section open 16 h → own rate ₹200/hr (WC-18), section average ₹100/hr. Shown alongside the peak (WC-20).
* *Code reference:* `SectionCalculationService.php` 120–132. Matches this decision — no change needed.
* *Origin:* `calculation_rework_decisions.md` §3C; confirmed 2026-11-10.

---

## E. Section Totals

### WC-22: Section Capacity
1. **Section Daily Capacity = Machine Daily Capacity + Manual Daily Capacity** — a **parallel sum** (all machines and batches produce towards the section total at the same time), not limited by the slowest step. Sequencing between steps belongs to the routing (each operation = one section step).
2. **Average Section Capacity per Hour = Section Daily Capacity ÷ Section Operating Hours** (0 when hours = 0).
3. **Peak Section Capacity per Hour = Machine Capacity per Hour + Peak Manual Capacity per Hour** (machines run all section hours; manual peak per WC-14).
4. All outputs are summed as generic units (WC-07 rule 4).
* *Code reference:* `SectionCalculationService.php` 109–118. Matches this decision — no change needed.
* *Origin:* `calculation_rework_decisions.md` Q5, §2A–B; confirmed 2026-11-10.

### WC-23: Section Cost & Cost per Unit
1. **Section Daily Cost = Daily Labor Cost + Daily Machine Cost.** No overhead at section level (overhead is added only at Work Center level — WC-28/29).
2. **Average Section Cost per Hour = Section Daily Cost ÷ Section Operating Hours.**
3. **Peak Section Cost per Hour = Machine Cost per Hour + Peak Labor Cost per Hour** (WC-20).
4. **Cost per Unit** (total, and the machine / internal labor / external labor / total labor parts) **= matching Daily Cost ÷ Section Daily Capacity.**
5. **Zero output:**
   * capacity = 0 **and** cost > 0 → cost per unit is **"N/A"** (stored as `null`, not 0), with a tooltip: *"Output is 0 — cost per unit cannot be calculated. Daily cost X is being incurred with no production."*
   * capacity = 0 **and** cost = 0 → cost per unit is **0**.
   → Task **WC-IMPL-08**.
* *Code reference:* `SectionCalculationService.php` 136–161 (cost, averages, peak, per-unit). Logic matches except the zero-output case.
* *Origin:* `calculation_rework_decisions.md` §5; confirmed 2026-11-10.

### WC-24: Labor Without Production — Warnings
**Why the warning exists:** no **active** machine is running and no active batch is producing, but workers are **still actively assigned** to the section — so labor cost is being incurred with no output. Saving is never blocked (a support / preparation section can be intentional).

1. **Cost with 0 Output** — shown when: **active machines = 0** AND **active batches = 0** AND **active personnel > 0**.
2. **Only active machines count** for the trigger. When active machines = 0, **all** of the section's machines are then looked at to explain the reason clearly:
   * no machines at all → *"No machines assigned and no active manual batches. X workers are actively assigned — labor cost Y/day is incurred with 0 output."*
   * machines exist but none active → *"N machine(s) assigned but none is active (e.g. 1 under maintenance, 1 inactive) and no active manual batches. X workers are actively assigned — labor cost Y/day is incurred with 0 output."*
3. **Partially unutilized labor** — shown when: **active machines = 0** AND **active batches > 0** AND some active workers are **not in any active batch**. Shows how many employees / contractors are unassigned (they are paid but not producing). Same machine-reason wording as rule 2.
4. Shown everywhere: section row badge, advisory banner above the sections table, resource pill, daily capacity icon, section details modal (top alert + Manual Batches tab), section calculation modal.
5. Consistent with WC-07 (machines that are not active produce 0 in the snapshot).
6. **Idle machines must NOT trigger the warning.** An idle machine is technically active, just not running at the moment, so it still counts as an active machine (and keeps its capacity per WC-07). There is no separate "idle" machine status today (statuses: `active`, `inactive`, `under_maintenance`, `decommissioned` — `Machine.php` 21–24). How "idle" is represented will be checked and settled in the Machine module review → **WC-MACH-01** (📌).
→ Task **WC-IMPL-09**.
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-27 (✅ verified by user for rule 1 with "no machines"); rules 2–3 confirmed 2026-11-10.

---

## F. Work Center Roll-Up

### WC-25: Work Center Capacity Roll-Up
1. **Work Center Daily Capacity = Σ Daily Capacity of active sections** (inactive / deleted sections contribute 0 — WC-30).
2. **Work Center Operating Hours = union of all active sections' shift windows** on the 24-hour clock, overlaps counted once (same method as WC-03).
3. **Work Center Capacity per Hour = Work Center Daily Capacity ÷ Work Center Operating Hours** (not the sum of section hourly rates).
4. **Section figures used by the roll-up:** changed sections are recalculated and saved in the same batch; unchanged sections use their **latest saved snapshot**; a section with **no snapshot yet is calculated and saved in the same batch** — so every number in a Work Center snapshot has a section snapshot behind it.
* *Code reference:* `WorkCenterCalculationService.php` 39–42 (active sections), 59/117 (daily capacity), 71–76, 90–92, 409–429 (union hours), 118–120 (capacity/hour), `calculateAndSave()` 213–273 (impacted → save; unchanged → latest; none → calculate **and save**, lines 265–273). Matches this decision — no change needed.
* *Correction (2026-11-10):* rule 4 is already implemented in `calculateAndSave()`. The unsaved on-the-fly path (`calculate()` line 59) is only reachable through unused legacy helpers → cleanup **WC-IMPL-10**.
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-18; confirmed 2026-11-10.

### WC-26: Direct Work Center Staff — Daily Cost
1. **Direct staff** = active employees assigned to the Work Center with **no section** (`production_work_center_employees.section_id IS NULL`) — e.g. supervisors, maintenance leads.
2. **Allocated Daily Cost = Daily Salary (WC-17) ÷ number of active Work Centers the employee is directly assigned to** (minimum 1).
3. **Work Center Daily Labor = Σ section labor + Σ direct staff allocated cost.** Direct staff are not part of any section.
4. UI shows the share: *"X% (1 of N WCs)"* or *"100% Dedicated"*, with the base daily salary under the monthly figure (ISSUE-23).
* *Code reference:* `WorkCenterCalculationService.php` 330–393 (`calculateDirectStaffLabor()`), 343–350 (active WC count), 353–355 (allocation), 94 (added to WC labor). Matches this decision — no change needed. (Salary read as monthly → WC-IMPL-07.)
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-18, ISSUE-23; confirmed 2026-11-10.

### WC-27: Direct Work Center Staff — Hourly Rate
1. **Same rule as everyone else (WC-18):** **Hourly Rate = Allocated Daily Cost ÷ the employee's shift clock hours (breaks included)** — not shift minus break.
2. **The Work Center split applies here too:** the hourly rate is worked out from the **allocated** daily cost (daily salary ÷ number of active Work Centers, WC-26), so an employee in 2 Work Centers has half the hourly rate charged to each.
   *Example:* supervisor, daily salary ₹1,800, assigned to 2 Work Centers, 9 h shift (06:00–15:00, 1 h break) → allocated ₹900/day per Work Center → **₹900 ÷ 9 = ₹100/hr** per Work Center.
3. **Direct staff average per hour** (table footer, audit) = total direct staff allocated daily cost ÷ Work Center operating hours, labelled *"/ hr over {X} hrs operating window"* — never "over 24h" unless the window really is 24 h.
4. Missing / 24-hour / zero-hour shift fallbacks (8 h guesses) are corrected together with the other shift issues → **WC-ISSUE-07** (Shift Module group).
→ Task **WC-IMPL-11**.
* *Code reference:* `WorkCenterCalculationService.php` 353–365 (allocation + hourly rate), `ProductionShift.php` 97–111 (`working_hours` = clock − break, used today); footer `work-centers/show.blade.php` 4156.
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-18, ISSUE-23; confirmed 2026-11-10.

### WC-28: Work Center Overhead
1. **`production_work_centers.overhead_cost` is a flat amount PER DAY** (facility overhead: rent, utilities, admin share, etc., entered as a daily figure).
2. It is added once per day to the Work Center total (WC-29). Not split by sections; not part of any section cost.
3. **The unit must be visible everywhere:**
   * **Add / Edit Work Center forms:** label *"Overhead Cost (₹ / day)"* plus a help note: *"Enter the overhead per day. If you know the monthly amount, divide it by 30."*
   * **Show page:** every overhead figure shows *"/ day"* (hero Facility Overhead card, Direct Personnel & Overhead tab, reconciliation strip, audit metrics).
4. Currency: entered in tenant currency, stored in base currency (WC-46, checked in WC-FINAL-01).
5. Overhead in **routing / BOM costing** stays out of scope (old WC-TRACK-03 Point 3.2, on hold) — for the routing review.
→ Task **WC-IMPL-12**.
* *Code reference:* `WorkCenterCalculationService.php` 109–111 (added as daily ✔); forms `work-centers/create.blade.php` 58, `edit.blade.php` 61 (no unit ✘); `lang/en/production.php` 789 (`overhead_cost` = "Overhead Cost").
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-18 (flat daily); old WC-TRACK-03 Point 3.2 ("monthly lump sum") **superseded**; confirmed 2026-11-10.

### WC-29: Work Center Cost Totals
1. **Work Center Daily Labor = Σ section daily labor + Σ direct staff allocated daily cost** (WC-26).
2. **Work Center Daily Machine Cost = Σ section daily machine cost.**
3. **Work Center Total Daily Cost = Daily Labor + Daily Machine Cost + Daily Overhead** (WC-28).
4. **Average per hour** (labor, machines, total) = matching daily figure ÷ Work Center Operating Hours (WC-25).
5. **Work Center Cost per Unit = Total Daily Cost ÷ Work Center Daily Capacity** — "N/A" when capacity = 0 and cost > 0 (WC-23 rule 5, WC-IMPL-08).
* *Code reference:* `WorkCenterCalculationService.php` 93–125. Matches this decision — no change needed (N/A handled by WC-IMPL-08).
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-18; confirmed 2026-11-10.

### WC-30: Inactive & Deleted Sections
1. Only **active** sections count in the Work Center roll-up. Inactive and deleted (soft-deleted) sections contribute **0** to capacity, labor, machine cost and operating hours.
2. An inactive / deleted section is still **tracked**: making it inactive or deleting it queues it in the countdown; the countdown banner shows *"[CODE] (Inactive)"* / *"(Deleted)"*; the batch records it in `impacted_section_ids`.
3. In the sections table an inactive section shows **0 units/day and 0/day** with an "(Inactive)" pill and explanation. Its last snapshot is kept as history and not shown as current.
4. No new snapshot is saved for an inactive section; saving a snapshot for a **deleted** section is blocked.
* *Code reference:* `WorkCenterCalculationService.php` 39–42, 214 (active only); `SectionCalculationService.php` 24 (returns 0 for inactive/trashed), 1313 (blocks trashed save); `CountdownCalculationEngine::triggerCountdown()` (`withTrashed()`); `WorkCenterSectionService::delete()` (queues section). Matches this decision — no change needed.
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-02 #13; confirmed 2026-11-10.

---

## G. Calculation Lifecycle

### WC-31: Page Loads (GET) & Calculations
1. Opening a Work Center page or a calculation modal **never starts** a new calculation.
2. **Exception — finishing an expired countdown:** if a countdown has already ended, its queued sections have not been calculated yet, and no calculation is running, opening the page runs that **pending** calculation (recorded as `countdown_timer`). It is the same calculation the cron would run — only not left waiting.
3. With no snapshot yet, every calculated figure shows **"Calculation Pending"** (WC-42).
4. The calculation-details endpoint (modal data) is strictly read-only; with no snapshot it returns an empty result.
* *Code reference:* `WorkCenterController.php` 120–132 (`show()` — expired countdown only), 351+ (`calculationDetails()` — read-only). Matches this decision — no change needed.
* *Future:* what happens to connected data when a Work Center / section / machine / batch becomes inactive or is deleted → **WC-FUT-02** (decide later).
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-02 #2, #5; confirmed 2026-11-10.

### WC-32: Creating & Editing a Work Center
1. **Creating** a Work Center performs **no** calculation, saves no snapshot and writes no activity log.
2. **Any edit** of a Work Center (any field — overhead, status, name, code, description, calendar …) **arms the countdown** with no specific section; when it ends, a Work Center roll-up snapshot is created (ISSUE-26 "Work Center only" record). Kept deliberately simple — every edit gives a fresh, traceable snapshot.
* *Code reference:* `WorkCenterService.php` 37 (create — no countdown), 67 (update — `triggerCountdown($updated, null)`). Matches this decision — no change needed.
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-02 #1, #4; confirmed 2026-11-10.

### WC-33: What Arms the Countdown
1. Any change to a section, machine, manual batch, employee assignment, contractor allocation or the Work Center itself **arms a countdown** on the parent Work Center (default **10 minutes**, configurable **1–1440**), queuing the affected section in `pending_calculation_section_ids`.
2. If a countdown is already running, later changes **append** their section to the queue **without resetting** the timer — no immediate calculation, no log spam.
3. Inactive and soft-deleted sections are also tracked in the queue (WC-30).
4. **Covered today** (screens): section create / edit / delete; machine create / edit / delete; batch create / edit / on-off / delete; employee assignment add / on-off / remove; contractor allocation add / quantity / on-off / remove; Work Center edit (WC-32).
5. **Connected modules and other entry points** (bulk actions, API, import, pay grades, shifts, HRMS salary/shift changes, machine moves between sections) must also arm the countdown for every affected section → part of the dependency work **WC-FUT-02** (future).
* *Code reference:* `CountdownCalculationEngine::triggerCountdown()` 44–80; triggers `WorkCenterSectionService.php` 60, 104, 193; `MachineController.php` 68, 155, 175; `SectionManualProductionController.php` 112, 234, 281, 339; `WorkCenterEmployeeController.php` (`triggerCalculationCountdown`); `ExternalWorkerAllocationController.php` 47, 84, 121, 157; `WorkCenterService.php` 67.
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-02 #3, #4; confirmed 2026-11-10.

### WC-34: Countdown Length Lock
1. The countdown length is set **per Work Center** (`production_work_centers.calculation_countdown_minutes`), **1–1440 minutes**, default **10**.
2. It **cannot be changed while a countdown is running** — the button is disabled in the UI (tooltip *"Countdown is actively ticking. Debounce duration cannot be changed."*) and the backend refuses the change.
* *Code reference:* `CountdownCalculationEngine::updateCountdownDuration()` 94–108; UI `work-centers/show.blade.php` 25, live sync 7472–7475. Matches this decision — no change needed.
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-02 #5; confirmed 2026-11-10.

### WC-35: When the Countdown Ends
1. The countdown ends by any of: the browser timer reaching 00:00 (auto-submit), a page reload after the end time (WC-31), or the cron `production:process-calculation-countdowns` (every minute, without overlapping).
2. On expiry, **all queued sections are calculated in one batch** (shared `batch_id`), then the Work Center roll-up; then the countdown resets (end time cleared, queue emptied).
3. **Empty queue = Work Center only:** if the countdown was armed with no section (e.g. a Work Center edit — WC-32), only the **Work Center roll-up** is recalculated, using each section's latest saved snapshot (a section with no snapshot yet is calculated and saved — WC-25 rule 4). Result: **1 Work Center record**, no new section snapshots (ISSUE-26). → Task **WC-IMPL-13**.
4. The header **"Recalculate"** button (explicit user request) recalculates **all active sections** plus the roll-up, even when the queue is empty. The banner **"Recalculate Now"** button processes **only the queued sections** (see WC-36). *(Updated 2026-11-10.)*
5. Only one calculation per Work Center may run at a time (`is_calculation_running`). Robustness of the lock and the cron → **WC-IMPL-14** (proposed).
* *Code reference:* `CountdownCalculationEngine.php` 114–143 (`recalculateNow()`), 149–164 (`processExpiredCountdowns()`); `WorkCenterCalculationService.php` 216–218 (empty queue → all sections today ✘); `routes/console.php` 25–27.
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-02 #6, ISSUE-26; confirmed 2026-11-10.

### WC-36: Immediate Calculation (Buttons) vs Timer
1. An immediate calculation happens **only** when an authorised user (`update` permission on the Work Center) clicks a button:

| Trigger | Where | What it calculates | Recorded as |
| :--- | :--- | :--- | :--- |
| **[⚡ Recalculate]** | Work Center header | **All active sections** + roll-up (even if nothing is queued) | `manual` |
| **[⚡ Recalculate Now]** | Countdown banner (shown only while a countdown runs) | **Only the queued sections** + roll-up — same as the timer ending now (empty queue → roll-up only, WC-35 rule 3) | `manual` |
| Browser timer reaches 00:00 (auto-submit) | Countdown banner JS | **Only the queued sections** + roll-up | `countdown_timer` |
| Page reload after end time | `show()` | **Only the queued sections** + roll-up | `countdown_timer` |
| Cron every minute | `production:process-calculation-countdowns` | **Only the queued sections** + roll-up | `countdown_timer` |

2. The temporary "bypass" used during testing (old Task 5.2) is **removed** — the countdown uses the Work Center's configured length (`CountdownCalculationEngine.php` 70).
→ Task **WC-IMPL-13** (button scopes, auto-submit trigger type).
* *Code reference:* `WorkCenterController.php` 305–312 (`recalculateNow()` → engine `'manual'`), route `work-centers.recalculate-now` (`Routes/web.php` 114); banner form + auto-submit `work-centers/show.blade.php` 508–513, 7432–7434 (today the auto-submit posts the same form → recorded as `manual` ✘).
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-02 #6, #7; `calculation_rework_decisions.md` Task 5.2; confirmed 2026-11-10.

---

## H. Snapshots & Audit

### WC-37: Snapshots Are Never Edited & Fully Traceable
1. Every calculation **creates new rows** — section snapshots in `production_work_center_section_calculations`, Work Center snapshots in `production_work_center_calculations`. Snapshots are **never edited or deleted** (only the test seeder clears them).
2. The only allowed write after creation: section snapshots **of the same batch** receive the Work Center snapshot's `work_center_calculation_id`.
3. Section and Work Center snapshots of one run share the same `batch_id`.
4. **Traceability:** a Work Center snapshot must record, for **every** section it rolled up, **which section snapshot it used** (section snapshot ID and snapshot number) — including older snapshots reused for unchanged sections (WC-25 rule 4). Audit drill-downs from a Work Center snapshot open **that exact** section snapshot, not the latest one. → Task **WC-IMPL-15**.
* *Code reference:* `SectionCalculationService.php` 1320–1326 (create); `WorkCenterCalculationService.php` 280–287 (create), 304–309 (same-batch link only), 76–88 (`calculate()` section details — no snapshot reference today ✘).
* *Origin:* `calculation_rework_decisions.md` §7; confirmed 2026-11-10.

### WC-38: Snapshot Numbers
1. Every snapshot gets a sequential `snapshot_number` (#1, #2 …) — **per section** for section snapshots, **per Work Center** for Work Center snapshots. Activity logs store the snapshot number too.
2. The UI always shows the **snapshot number**, never the database ID (the ID is only a safety fallback: `snapshot_number ?? id`).
3. Activity log buttons "View Snapshot #N" open **that exact snapshot** — never the latest. *(Corrected 2026-11-11 — see below.)*
   * Each button loads its own snapshot (section or Work Center) into the calculation modal; all figures come from that snapshot only — **no live data is mixed in** for an older snapshot.
   * An older snapshot shows a banner: *"Viewing snapshot #2 from {date} — not the latest (latest is #3)."*
   * The snapshot must belong to the same tenant, Work Center (and section); otherwise 404.
   → Task **WC-IMPL-20**.
4. Existing snapshots were backfilled with numbers (migration `2026_10_31_000001`).
* *Code reference:* numbering ✔ `SectionCalculationService.php` 1321; `WorkCenterCalculationService.php` 281; labels `work-centers/show.blade.php` 654, 3421, 4493; `section-calculation-modal.blade.php` 16.
* *Correction (2026-11-11, found by the user):* rule 3 was wrongly recorded as "matches — no change needed". The buttons (`show.blade.php` 1716 / 1720, 4267 / 4271, 4930 / 4934) only open a modal rendered once at page load from the **latest** snapshot (`section-calculation-modal.blade.php` 34 `$section->latestCalculation`; Work Center modal built inline from `$latestSnapshot`, ~3180–4593), and `WorkCenterController::calculationDetails()` 351–364 returns only the latest — so every "#N ↗" opens the latest snapshot. (Concurrent-number clashes → WC-IMPL-14.)
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-02 #14; confirmed 2026-11-10.

### WC-39: Plain-English Audit Details
1. Every snapshot stores **every figure and breakdown step as numbers** (plus a message type where an explanation is needed) in `calculation_details`.
2. The UI turns these into **plain-English explanations and formulas** with the real numbers, in the viewer's tenant currency — e.g. *"Batch capacity 20 u/h × shift 9 hrs = 180 units/day"* (WC-IMPL-04, Method A).
3. **Section audit tab: 52 metrics** in 7 categories; **Work Center audit tab: 47 metrics** in 8 categories. Each has an (i) popover: type (Calculated / Collected), source in plain words (no database column names), formula, explanation. Work Center roll-up metrics link to the exact section snapshots (WC-37).
4. **Raw JSON is never shown.**
* *Code reference:* `section-calculation-modal.blade.php` 1212 (`$auditMetricsMeta`, 52); `work-centers/show.blade.php` 4360 (`$wcAuditMetricsMeta`, 47). Outdated HTML comment mentioning "Raw JSON" at `show.blade.php` 3556 — remove with WC-IMPL-04.
* *Origin:* `calculation_rework_decisions.md` §2C, §5; `calculation_audit_issues_tracker.md` ISSUE-19, ISSUE-24; reworded for WC-IMPL-04 and confirmed 2026-11-10.

### WC-40: Dedicated Snapshot Columns
1. **Every metric** is stored in the snapshot details (`calculation_details` — 52 section / 47 Work Center metrics).
2. Figures shown on the **main page** (section row, popovers, hero cards) also have **dedicated columns**, so the page does not read the details:
   * **Section snapshots** (`production_work_center_section_calculations`): original columns (daily capacity, capacity per hour, labor / machine / total cost per day and per hour, operating hours) **+ 8 breakdown columns** — `hourly_machine_capacity`, `daily_machine_capacity`, `hourly_manual_capacity`, `daily_manual_capacity`, `internal_labor_cost_per_hour`, `total_daily_internal_labor_cost`, `external_labor_cost_per_hour`, `total_daily_external_labor_cost` (migration `2026_10_29_000001`).
   * **Work Center snapshots** (`production_work_center_calculations`): original columns **+** `direct_staff_daily_labor_cost`, `cost_per_unit` (migration `2026_10_29_000002`; `cost_per_unit` becomes nullable under WC-IMPL-08).
3. Further metrics are promoted to columns **only after testing is complete**, if reports need them.
* *Note:* the `column ?? JSON` fallback in `work-centers/show.blade.php` 1208–1225 never runs (new columns default to 0, not null), so snapshots made before 2026-10-29 show 0 in those popovers. Demo data — a recalculation fixes it; no task.
* *Code reference:* `WorkCenterSectionCalculation.php` fillable 24–32; `WorkCenterCalculation.php` 27, 33, 44. Matches this decision — no change needed.
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-16, ISSUE-18; `calculation_rework_decisions.md` Remaining Decision 1; confirmed 2026-11-10.

### WC-41: Activity Log
1. `production_work_center_activity_logs` holds **only completed calculations**: one row per **section snapshot** and one row per **Work Center snapshot**, linked by `batch_id`, with `calculation_type` (`section` / `work_center`), `snapshot_id`, and who triggered it (a user, or empty = system for timer / cron).
2. Description: *"{name} [{code}] snapshot #N calculated"*; each row has a **[View Snapshot #N ↗]** button opening that snapshot.
3. **No interim events** (queued, countdown started, duration changed) and **no resource-change events** are logged.
4. A Work Center-only run (empty queue — WC-35) writes **1** Work Center row.
5. Main tab shows the 10 most recent (newest first by ID); "View All Logs (N)" opens the full list.
* *Code reference:* `WorkCenterActivityLogger.php` 44 (`logSectionCalculationCompleted()`), 76 (`logWorkCenterCalculationCompleted()`), 32 (user). Unused `logResourceChange()` (107–129) to be removed → **WC-IMPL-10**.
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-26, ISSUE-02 #9, #10; confirmed 2026-11-10.

---

## I. Display Rules

### WC-42: Snapshot-Only Display & "Calculation Pending"
1. Every **calculated** figure is shown **from snapshots only** — section row, capacity / cost popovers, section KPI cards, section Cost & Capacity tab, **Work Center hero panel**, Work Center and section calculation modals.
2. When there is **no snapshot yet**, each of these shows the amber **"Calculation Pending"** state (*"Initial calculation snapshot has not been generated yet. Values will update upon the next scheduled calculation cycle or by clicking 'Recalculate'."*) — **never 0, never live numbers** (e.g. no live overhead as "Total Daily Cost").
3. **Exception:** the Section Details modal is an **asset inspector** (live lists of assigned machines, batches, people) — its live footers are covered by WC-43 / WC-44.
→ Task **WC-IMPL-16** (hero panel).
* *Code reference:* section level ✔ `work-centers/show.blade.php` 1324, 1331, 2397, 2423, 2852; hero panel ✘ 804–830 (no pending state; live overhead fallback 813, 3184; total cost 814 = overhead + 0 + 0).
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-04, ISSUE-06, ISSUE-09, ISSUE-20; confirmed 2026-11-10.

### WC-43: Machine & Batch Footers in the Two Modals
**A. Section Details modal (live — inspector of what is assigned; `work-centers/show.blade.php`)**
1. **Machines tab:** lists **all** machines in the section with a **Status** column (code, name, capacity, operating cost, status). The footer **"Total Machine Output"** is the live sum of **active** machines only (idle counts as active — WC-MACH-01). When some machines are not counted, the footer shows a note **naming them by code**, e.g. *"Not counted: MC-PRS-02 (under maintenance), MC-CUT-05 (inactive)"*.
2. **Manual Batches tab:** lists all batches with Active / Inactive badge. Footer **output** (daily total + average per hour) comes from the **snapshot** — never a plain sum of batch speeds; "Calculation Pending" when no snapshot. Footer **workforce counts** (X Emp, Y Ext) include **active batches only**, with a note for workers in inactive batches, naming the batches, e.g. *"+3 workers in inactive batch: Night Packing"*.

**B. Section Calculation modal (snapshot — frozen; `section-calculation-modal.blade.php`)**
3. Machines & Energy and Manual Batches tables list the **active (counted)** machines / batches from the snapshot; footers ("Total Active Machines", "Total Manual Production") come from the snapshot — as today.
4. Under each table, a small **"Not counted in this snapshot"** list shows the inactive machines / batches stored in the snapshot (`inactive_machines`, `inactive_manual_batches`) with **code / name and status**, so the snapshot explains what was left out.
5. Consumption label is "Consumption / hr", not "kW" (WC-10) → WC-IMPL-05.
→ Task **WC-IMPL-17**.
* *Supersedes:* `calculation_audit_issues_tracker.md` ISSUE-04 point 3 (said both footers are live sums — wrong). ISSUE-03's "live sum" rule kept, now active machines only.
* *Origin:* ISSUE-03, ISSUE-04, ISSUE-05; confirmed 2026-11-10.

### WC-44: Shifts Tab Footer & Operating Window Card (Section Details Modal)
1. The **Shifts & Schedule tab** is part of the live inspector. Its footer uses **the same method as the calculation** (WC-02, WC-03): union of the section's **active** shifts, breaks included, overnight shifts wrapped across midnight, overlaps counted once. It shows:
   * **Operating hours** = union hours (not the sum of shift hours);
   * **Operating window** correctly for overnight shifts (e.g. *"22:00 – 06:00"*, or *"24 h"* when continuous);
   * **Workforce** including "(Ext)" employees whose shift lies inside section hours (WC-06).
2. Inactive shifts are listed (with an Inactive badge) but **not counted** in the footer.
3. The **Operating Window KPI card** shows the **snapshot** operating hours; with no snapshot it shows **"Calculation Pending"** (WC-42) — **no** shift-sum fallback.
4. No 8-hour guesses for shifts without times.
→ Task **WC-IMPL-18**.
* *Supersedes:* `calculation_audit_issues_tracker.md` ISSUE-07 (shift-sum footer and KPI fallback).
* *Code reference:* `work-centers/show.blade.php` 2568–2603 (footer: Σ `working_hours`, string sort window ✘), 2346–2375 (KPI fallback ✘); reusable read-only logic `SectionCalculationService::calculateOperatingHours()` 334–470.
* *Origin:* ISSUE-07; confirmed 2026-11-10.

### WC-45: Formatting, Wording & Canonical Keys
1. **Hours / durations** are always shown with `format_hours()` as clock time (e.g. `16.30 hrs`, not `16.5`); minutes-based durations use `format_duration()`.
2. **Wording:** "Capacity" (per day / per hour) — never "Speed". Units are **generic** ("units", "u/h") — never "pcs" (WC-07 rule 4).
3. **One canonical key per value** in snapshots — no duplicate alias keys. Views read the canonical key, with a fallback to the old key only for older snapshots.
→ Task **WC-IMPL-19**.
* *Code reference:* duplicates in `SectionCalculationService.php` 905–925 (personnel), 1018–1021 (contractors); `WorkCenterCalculationService.php` 375–384 (direct staff); label `work-centers/show.blade.php` 6138–6140 ("Target Speed … pcs / hr").
* *Origin:* `calculation_audit_issues_tracker.md` ISSUE-11, ISSUE-14; confirmed 2026-11-10.

### WC-46: Currency — ⏸️ RULE RECORDED, CONVERSION GAPS MARKED (DECIDE LATER)
1. **Intended rule:** all money is **stored in base currency** (rate 1.0) and converted to the tenant's active currency **only at display** — `convert_to_base()` on save, `convert_from_base()` / `format_currency()` on display (`app/helpers.php` 240, 254, 263, 302).
2. **Confirmed working:** Work Center overhead (`WorkCenterController.php` 60, 193) and pay grade hourly rate (`ExternalWorkerPayGradeController.php` 78, 122) are converted to base on save and shown with `format_currency()`.
3. **Open (to check and decide later, together with the final currency audit WC-FINAL-01):**
   * **WC-ISSUE-11** — machine `operating_cost_per_hour` and `energy_rate_per_unit` are **not** converted to base on save, but are shown with `format_currency()`.
   * **WC-ISSUE-12** — HRMS `employees.current_salary` is stored as typed (tenant currency, shown with a hard-coded "₹"), while Production treats it as base.
   Until decided, labor and machine costs for a non-base-currency tenant can be inflated by the exchange rate.
* *Origin:* `calculation_audit_issues_tracker.md` currency architecture note; reviewed 2026-11-10.

---

## J. Safeguards

### WC-47: Deletion Protection
1. A **section, machine or manual batch used by a routing operation** — and for sections also by production orders or schedules — **cannot be deleted**; the user is told to **deactivate it instead**.
2. A Work Center used by routing operations cannot be deleted either (WC-FUT-02 table).
3. Resources with no history can be deleted; for a section, its machines are unassigned and its employee assignments / contractor allocations are removed. The full cascade for everything connected is decided under **WC-FUT-02**.
* *Confirmed working:* section — `WorkCenterSectionService::delete()` + `WorkCenterSection::hasProductionHistory()` 291–305 (routing operations, production orders, schedules); machine — `MachineService::delete()` 160–165 (also used by bulk delete).
* *Gap:* manual batch delete has **no** routing check (`SectionManualProductionController::destroy()`; relation `SectionManualProduction::routingOperations()` line 61 unused) → recorded under **WC-FUT-02**.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-06 Point 6.2; confirmed 2026-11-10.

### WC-48: Inactive Resource After a Routing Uses It — 🔀 MOVED TO ROUTING REVIEW
* This is a **routing rule** (routing screens and validation). Recorded as **WC-RTG-02** in [`work_center_implementation_tasks.md`](work_center_implementation_tasks.md) and to be decided in the routing review.
* The Work Center side (what deactivating a section / machine / batch does to Work Center figures) is covered by WC-07, WC-09, WC-30, WC-47 and WC-FUT-02.
* *Origin:* `work_center_routing_linking_tracker.md` WC-TRACK-06 Point 6.1; moved 2026-11-10.
