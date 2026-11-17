# Production — Business Rules & Workflows (Work Center · Routing · BOM)

> **Purpose:** The business rules and workflows of the Production master data, in plain language — what the system must do, not how the code does it.
> **Source of truth:** every rule comes from an agreed decision. The decision ID is shown in brackets, e.g. *(WC-17)*. Details, code references and history are in:
> [`work_center_decisions.md`](work_center_decisions.md) · [`routing_decisions.md`](routing_decisions.md) · [`bom_decisions.md`](bom_decisions.md)
> **Implementation status** (what is already true in the code and what still has to be built) is tracked in the task files, not here:
> [`work_center_implementation_tasks.md`](work_center_implementation_tasks.md) · [`routing_implementation_tasks.md`](routing_implementation_tasks.md) · [`bom_implementation_tasks.md`](bom_implementation_tasks.md)
> **Open items** (decide later / deferred) are listed in [Section 7](#7-open-items--not-yet-decided). They are not rules yet.
> *Written 2026-11-11 after the Work Center, Routing and BOM reviews.*
> **Status: 📝 DRAFT — accepted for now; to be finalised after all implementation tasks are done** (implementation may change some rules). Update this file whenever a task changes a rule.

---

## Contents
1. [How the pieces fit together](#1-how-the-pieces-fit-together)
2. [General rules (all of Production)](#2-general-rules-all-of-production)
3. [Work Center rules](#3-work-center-rules)
4. [Routing rules](#4-routing-rules)
5. [BOM rules](#5-bom-rules)
6. [Workflows](#6-workflows)
7. [Open items — not yet decided](#7-open-items--not-yet-decided)

---

## 1. How the pieces fit together

```
Work Center ──► Section ──► Machine         (machine production)
                       └──► Manual Batch    (manual production: workers + shift + capacity)
                       └──► Shifts, employees, contractors

Routing  = an ordered list of operations; each internal operation uses
           1 Work Center + 1 Section + (1 Machine OR 1 Manual Batch)

BOM      = makes 1 Product, uses 1 Routing;
           its materials are allocated to the Routing's operations
```

* **Work Centers** describe capacity and cost: how much each section can produce per day and what it costs. These figures are frozen in **calculation snapshots**.
* **Routings** describe *how* something is made — the operations in order. A Routing has **no product**; one Routing can serve many BOMs. *(RT-01)*
* **BOMs** describe *what* is made and *with which materials*: one product, one Routing, and materials placed on that Routing's operations. *(BM-01)*
* Order of dependence: **Work Center → Routing → BOM**. A Routing reads Work Center snapshots; a BOM reads the Routing's saved figures.

---

## 2. General rules (all of Production)

| # | Rule | Source |
| :-: | :--- | :--- |
| G1 | **No rounding — show the truth.** Costs, rates, quantities and times are stored and shown with their real precision (at least 4 decimals), never rounded to 2 for display. A screw at ₹0.0042 shows ₹0.0042. | RT-12, BM-15, BM-19 |
| G2 | **Durations as clock time:** `12s`, `1m 05s`, `1h 27m`. Under one second: `< 1s` plus the real minutes, e.g. `< 1s (0.0040 min)`. Hours as clock hours (e.g. `16.30 hrs`). | RT-25, WC-45 |
| G3 | **Currency:** money is stored in the base currency and converted to the tenant's currency only when shown. *(Known gaps are open — see 7.)* | WC-46 |
| G4 | **Wording:** "Capacity" (never "Speed"); units are generic ("units", "u/h"), never "pcs"; machine rate is "Energy Rate per Unit"; consumption is "Consumption per Hour". | WC-07, WC-10, WC-45 |
| G5 | **Same rules on every entry path.** The web form, the API and the import apply exactly the same checks (one shared validator per entity). | RT-06, BM-01, BM-27, BM-28 |
| G6 | **Tenant isolation:** every lookup and check (routings, operations, sub-BOMs, version numbers) is limited to the current tenant. | BM-01, BM-03, BM-05, BM-08 |
| G7 | **Audit trail:** status changes record the real user (or "System" for jobs) — never a fallback user. | BM-02 |
| G8 | **Never drop data silently.** Items whose operation or resource was removed or became inactive are shown with a tag ("Deleted", "Inactive") and must be fixed; they are never filtered out quietly. | RT-29, BM-17 |
| G9 | **Only drafts are edited.** Approved records change only through a new version. | RT-03, BM-02 |

---

## 3. Work Center rules

### 3.1 Shifts & operating hours
1. Shifts are assigned **per section**. All active machines in a section run across all of that section's active shifts; machines are never given their own shift. *(WC-01)*
2. **Breaks count as production time.** Machines and manual batches produce, and workers are paid, for the full shift including breaks. Breaks are shown for information only. *(WC-02)*
3. **Section operating hours = the union of its active shift windows** on a 24-hour clock: overlaps counted once, gaps are idle, overnight shifts wrap past midnight, maximum 24 h. A shift with start = end is a 24-hour shift. Inactive shifts are ignored. *(WC-03)*
4. All figures are **per operating day**; no holiday calendar is applied. *(WC-03)*
5. A section cannot be saved without at least one shift. *(WC-04 — the "no active shift" case is open)*
6. The day is cut into **timeline windows** at shift boundaries; each window has its own running capacity and cost. Windows add up to the daily figure. *(WC-05)*

### 3.2 Employees on sections
1. An employee can be assigned only with an **active HRMS shift**, to a section that has active shifts. *(WC-06)*
2. The employee's shift must be one of the section's shifts, **or lie fully inside** the section's operating hours (section level). At Work Center level a partial overlap is enough. No overlap → blocked. *(WC-06)*
3. An employee can be in **one section** only, and cannot hold a Work Center-level and a section-level assignment at the same time; they **can** be directly in several Work Centers. *(WC-06)*
4. An employee whose shift is not a section shift but lies inside section hours is marked **"(Ext)"**. *(WC-06)*

### 3.3 Machines
1. **Machine daily capacity = Σ capacity per hour of active machines × section operating hours.** *(WC-07)*
2. Only **active** machines count. Inactive, under-maintenance and decommissioned machines count **0 for the whole day** (a snapshot is a prediction; the end of maintenance is unknown). Idle machines count as active. *(WC-07, WC-24)*
3. **Operating cost per hour** = the entered operating cost if filled, otherwise consumption per hour × energy rate per unit, otherwise 0. A difference from the energy baseline is shown as **additional cost** (higher) or **subsidy / deductible** (lower). *(WC-08)*
4. **Machine daily cost = Σ operating cost per hour of active machines × section operating hours.** *(WC-09)*
5. Machine form: typing consumption or rate fills the operating cost automatically; the user may then override it; saving with a different cost asks for confirmation ("Additional Expense" / "Subsidy"). *(WC-11)*

### 3.4 Manual batches
1. Manual production exists **only as manual batches** — each with its own shift, capacity per hour and workers. A section "supports manual production" when it has at least one active batch. *(WC-12)*
2. **Batch daily output = batch capacity per hour × the batch's shift clock hours.** Section manual output = Σ active batches. *(WC-13)*
3. A batch needs a name, a section shift, capacity > 0 and **at least one worker**. Only employees assigned directly to that section can be chosen; a worker can be in **one active batch** at a time; contractor allocations must be of the same section. *(WC-13)*
4. **Average manual capacity per hour** = manual daily output ÷ section operating hours; **peak** = the highest combined capacity of batches running at the same time. No "current speed" is ever shown. *(WC-14, WC-15)*
5. Batch labor is a **breakdown** of section labor — never added a second time. **Batch cost per unit = batch daily labor ÷ batch daily output.** *(WC-16)*

### 3.5 Labor
1. **Daily salary = HRMS monthly CTC ÷ 30.** HRMS stores the **annual** CTC; Production always asks HRMS for the monthly CTC (payroll's own rule) and never divides it itself. *(WC-17)*
2. **Hourly rate = daily salary ÷ shift clock hours.** Shift used: the employee's batch shift → their HRMS shift → the section's first shift. The same employee has the same rate everywhere. *(WC-18)*
3. Only active assignments of active employees count; a missing salary counts as 0 with a warning. *(WC-17)*
4. **Contractors:** allocation hourly cost = headcount × pay-grade hourly rate; daily cost = hourly cost × the allocation's shift clock hours. Several groups per shift are allowed. *(WC-19)*
5. **Peak labor cost per hour** = the highest timeline-window total (not peak internal + peak external). **Average labor cost per hour** = daily labor ÷ section operating hours. *(WC-20, WC-21)*

### 3.6 Section totals
1. **Section daily capacity = machine + manual daily capacity** (parallel sum). Average per hour = daily ÷ operating hours; peak = machine per hour + peak manual. *(WC-22)*
2. **Section daily cost = labor + machine cost** (no overhead at section level). **Cost per unit = daily cost ÷ daily capacity.** *(WC-23)*
3. Output 0 and cost > 0 → cost per unit shows **"N/A"** with an explanation; output 0 and cost 0 → 0. *(WC-23)*
4. **Warnings:** "Cost with 0 output" when there are no active machines and no active batches but workers are assigned; "Partially unutilized labor" when there are no active machines, some active batches, and workers outside them. The warning names the reason (no machines / machines under maintenance or inactive). Saving is never blocked. *(WC-24)*

### 3.7 Work Center roll-up
1. **Work Center capacity = Σ active sections**; operating hours = union of all active sections' shifts; capacity per hour = daily ÷ those hours. *(WC-25)*
2. Every Work Center figure has a section snapshot behind it: changed sections are recalculated, unchanged ones reuse their latest snapshot, sections without one are calculated and saved. *(WC-25)*
3. **Direct Work Center staff** (no section, e.g. supervisors): daily cost = daily salary ÷ number of Work Centers they are directly in; hourly rate = that share ÷ their shift clock hours. *(WC-26, WC-27)*
4. **Overhead is a flat amount per day**, added once to the Work Center total; forms and pages always show "/ day". *(WC-28)*
5. **Work Center daily cost = labor (sections + direct staff) + machine cost + overhead**; cost per unit = daily cost ÷ daily capacity ("N/A" rule as sections). *(WC-29)*
6. Inactive and deleted sections count 0, are still tracked in the queue, and keep their old snapshots as history. *(WC-30)*

### 3.8 When calculations run
1. **Opening a page never starts a calculation** — except finishing a countdown that has already expired. *(WC-31)*
2. Creating a Work Center calculates nothing; **any edit** arms the countdown (Work Center roll-up only). *(WC-32)*
3. Any change to a section, machine, batch, assignment, allocation or the Work Center **arms a countdown** (default 10 min, 1–1440, set per Work Center). Further changes join the queue without resetting the timer. The length cannot be changed while the countdown runs. *(WC-33, WC-34)*
4. When the countdown ends (timer, page reload after the end, or the every-minute cron), **only the queued sections** are calculated in one batch, then the roll-up. An empty queue → roll-up only. One calculation per Work Center at a time. *(WC-35)*
5. Buttons: **"Recalculate"** (header) = all active sections + roll-up; **"Recalculate Now"** (countdown banner) = queued sections only. Both need edit permission. *(WC-36)*

### 3.9 Snapshots, audit & display
1. **Snapshots are never edited.** Each run creates new section and Work Center snapshots sharing one batch id; a Work Center snapshot records exactly which section snapshot it used. *(WC-37)*
2. Snapshots are numbered **#1, #2 …** per section / per Work Center; the UI shows the number, never the database id. *(WC-38)*
3. Every figure is stored as numbers and explained in plain English with the real numbers in the viewer's currency (52 section / 47 Work Center audit metrics). Raw JSON is never shown. *(WC-39, WC-40)*
4. The activity log holds only **completed calculations** (one row per snapshot), with "View Snapshot #N". *(WC-41)*
5. **Calculated figures come only from snapshots.** With no snapshot the page shows **"Calculation Pending"** — never 0 or live numbers. The Section Details modal is a live inspector of what is assigned (active items counted; uncounted items named). *(WC-42, WC-43, WC-44)*
6. A section, machine, manual batch or Work Center **used by a routing** (sections also by orders / schedules) **cannot be deleted** — deactivate it instead. *(WC-47)*

---

## 4. Routing rules

### 4.1 Identity & lifecycle
1. A Routing is a **generic, reusable process route** with **no product and no BOM**. It is unique per tenant by routing number and by name + version. *(RT-01, RT-02)*
2. **Statuses:** draft → pending approval → **active** (or **scheduled** when it starts later); reject → draft; **Retire** → historical; cancel → cancelled. There is no "approved" status for Routings. *(RT-03)*
3. **Only drafts can be edited**; changing an approved Routing means a new version. Submit needs at least one operation; no BOM is needed. *(RT-03)*
4. **Dates:** start not in the past, end ≥ start; a late approval moves the start to the approval date with a note. *(RT-03)*
5. **Approving never retires other Routings.** Several Routings and versions can be active together. Old ones are retired manually with **Retire** (active / scheduled → historical). Historical Routings cannot be chosen for new BOMs. *(RT-02)*
6. **Daily job:** scheduled → active on the start date; active → historical after the end date. *(RT-03)*

### 4.2 Effect on BOMs
1. **Routing rejected:** linked BOMs pending approval go back to draft (audit entry). *(RT-04)*
2. **Routing retired, expired or cancelled:** approved BOMs → **inactive**; pending BOMs → **draft**; draft BOMs unchanged (they show the advisory). The Retire / Cancel confirmation lists the affected BOMs first. Running production orders continue. *(RT-05)*
3. The routing show page lists every BOM using it. *(RT-26)*

### 4.3 Operations
1. Every **internal** operation has **exactly one Work Center and one Section** (section belongs to the Work Center), and **exactly one resource: a machine or a manual batch** of that section — never both, never neither. *(RT-06, RT-07, RT-30)*
2. **External (subcontract) operations** have no Work Center, section or resource; a vendor is required; time is lead time in days plus buffers; labor and machine cost are 0. *(RT-08 — provisional)*
3. **Alternative operations** replace a primary operation of the same Routing (never another alternative, never itself), each with a **unique priority**, using the same predecessor as the primary. They are the only way to define alternatives. *(RT-10, RT-11)*
4. **Saving a draft Routing keeps its operations** and updates them in place; removed operations are **soft-deleted**. If BOMs use a removed operation, the user is **warned, not blocked**, and those BOMs must be fixed. *(RT-10)*
5. **Inactive resources:** drafts cannot select or save an inactive section / machine / batch (shown tagged "Inactive" until replaced); approved Routings stay unchanged but show an outdated warning. *(RT-29)*

### 4.4 Operation costing (1-unit baseline)
1. All routing times and costs are **for 1 unit**; the page says so. *(RT-12)*
2. **Machine operation:** time per unit = 60 ÷ machine capacity per hour; machine cost per unit = time × (operating cost per hour ÷ 60), using the same operating-cost rule as the Work Center. *(RT-13)*
3. **Machine operator labor per machine per hour** = (daily cost of the section's workers not in a batch ÷ section operating hours) ÷ active machines; labor cost per unit = time × (that rate ÷ 60). *(RT-14)*
4. **Manual batch operation:** time per unit = 60 ÷ batch capacity per hour; labor cost per unit = time × (batch labor per hour ÷ 60); machine cost 0. *(RT-15)*
5. **No manual rates** — labor and machine rates always come from the chosen machine / batch. *(RT-14, RT-15)*
6. **Two optional setup times:** setup **per operation** (once per run) and setup **per piece**.
   * **Cost / Unit = (setup per piece + run time) × (labor rate + machine rate) × (100 ÷ yield %)**
   * **Setup cost per run = setup per operation × (labor rate + machine rate)** — shown separately, no yield.
   * **With a known quantity: cost = quantity × Cost / Unit + setup cost per run (once).** *(RT-16)*
7. **Routing totals count primary operations only**; alternatives are shown through the route simulation. *(RT-18, RT-24)*
8. **Calculate once, save everything, warn when outdated:** the form calculates from the **latest section snapshot**; the backend re-checks and saves the figures with the snapshot id and number; every other page reads the **saved** figures; when the underlying data changes the Routing shows an **outdated warning** — saved figures are never changed automatically. A section with no snapshot cannot be costed. *(RT-19)*
9. Overhead is **not** included in routing / BOM cost for now; screens say "Excludes facility overhead." *(RT-17 — on hold)*

### 4.5 Routing form, import & API
1. The form holds **only routing data** (no BOM or materials). Picking a resource clears the other; changing Work Center / section / resource clears run time **and both setup times**. *(RT-20 … RT-23)*
2. **Import** uses the same rules, calculates figures on the server from the latest section snapshot, and updates **only draft** Routings without deleting BOM allocations. *(RT-27)*
3. **API** input = form fields and checks; API output = saved values only, one key per value. *(RT-28)*

---

## 5. BOM rules

### 5.1 Identity & lifecycle
1. A BOM has **one product and one Routing — both mandatory.** Any draft, approved or active Routing of the tenant can be chosen (no product filter); historical and cancelled Routings cannot. A Routing used by BOMs cannot be deleted. *(BM-01)*
2. **Statuses:** draft → pending approval → **approved** (the "active" BOM) or **scheduled** when it starts later; reject → draft; superseded / expired → **inactive**; cancel → cancelled. *(BM-02, BM-31)*
3. **Only drafts can be edited.** *(BM-02)*
4. **Submit** needs at least one material. **Approve** needs the Routing to be **active**, or **scheduled to start on or before the BOM's start**. *(BM-02)*
5. **One approved BOM per product + BOM type.** Approving a new version retires the previous one automatically: starting today → old one inactive; starting later → old one gets an end date and the new one is scheduled. (Routings deliberately do not do this — they have no product.) Late approval moves the start date with a note. *(BM-02)*
6. **Daily job:** scheduled → approved on the start date; approved → inactive after the end date. *(BM-02)*

### 5.2 Revise & duplicate
1. **Revise** (next version suggested) and **Duplicate** (version typed) open the **create form pre-filled** from the source BOM — **nothing is saved until the user clicks Save**; Cancel leaves nothing behind. Same for the API (returns the copy data without saving). *(BM-03)*
2. The copy has revision + 1 and starts today; the source stays approved until the new version is approved. *(BM-03)*
3. All allocations are copied with every field (no formula). A Routing that is no longer allowed is left empty with a warning; allocations on deleted operations are shown on the "Deleted" card. Version clash checks are per tenant. *(BM-03)*

### 5.3 Structure — materials on operations
1. The user enters **allocations**: a material on an operation, with quantity, unit, scrap %, notes. The **master material list** (one row per material) is **built from the allocations on every save** — never typed. *(BM-04)*
2. **Master quantity = sum of primary allocations only.** Alternatives do not add (they replace). A material used only as an alternative has quantity 0. *(BM-04)*
3. The master row also holds **alternative quantity** (possible alternative usage) and **gross quantity = Σ primary quantity × (1 + scrap %)**. Requirements use the gross quantity. *(BM-04)*
4. **One unit of measure per material** within a BOM. *(BM-04)*
5. **Every allocation is on an operation of the BOM's own Routing** (main or alternative operation). Operations with **no materials are allowed**. *(BM-05)*
6. **Quantities are fixed numbers only** (per the BOM's base quantity). There are **no formulas and no production parameters**. *(BM-09)*

### 5.4 Duplicates & alternatives inside an operation
1. A material may be used in **several operations**. *(BM-06)*
2. **Inside one operation:** a material is a **primary only once**; it **may also** be an alternative there; a primary may have a given material as its alternative **only once** (another primary may also use it); a material is **never an alternative of itself**.
   *Example (Op 10):* MT-1 10 primary ✔ · MT-2 30 primary ✔ · MT-1 5 as alternative of MT-2 ✔ · MT-1 as alternative of MT-3 ✔ · second MT-1 primary ✘ · MT-1 again as alternative of MT-2 ✘. *(BM-06)*
3. An alternative must name the **primary it replaces, in the same operation**; alternatives cannot chain. *(BM-07)*
4. **Priority is required for alternatives and unique per primary** (1 = first choice); primaries have no priority. An alternative has its own quantity — the amount needed to fully replace the primary. *(BM-07)*
5. **Materials in an alternative operation** count as alternative demand. *(BM-07)*
6. Removing a primary in the form asks first and removes its alternatives with it. *(BM-07)*

### 5.5 Sub-BOMs (child BOMs)
1. **One sub-BOM choice per material**, made in the material list (Tab 2) and written to every allocation of that material — so one material can never use two different sub-BOM versions in one BOM. The current active BOM is pre-selected. *(BM-08)*
2. **Selectable:** active; active but scheduled to be deactivated (warning: *"…will be deactivated on {date}; you will then have to create a new version of this BOM"*); scheduled to be activated (warning: *"…this BOM can only become active after the sub-BOM activates on {date}; its start date will move"*). Not selectable: inactive, cancelled, draft, pending. Only manufactured materials get the option. *(BM-08)*
3. **On approval** the parent's start date becomes the latest of its own start and any scheduled sub-BOM's start; approval is blocked if a chosen sub-BOM has become inactive. *(BM-08)*
4. **A sub-BOM that becomes inactive or cancelled after the parent is approved:** the parent gets a **warning** to create a new version; its status does **not** change; it **cannot be used for new production** until a new version is approved; production already running continues. *(BM-08)*
5. No guessing: the system never silently picks a sub-BOM; a material with no sub-BOM is not exploded further. *(BM-08)*

### 5.6 Routing changes seen from the BOM
1. **Changing the Routing** on a BOM with materials asks first, then clears all materials (on edit: nothing changes until Save). *(BM-14)*
2. **Deleted operation:** materials on an operation removed from the Routing appear on a **"Deleted"** card with **"Move to Op …"** and **Remove**; the BOM cannot be saved, submitted or approved until they are moved or removed. Only draft and pending BOMs can be affected. *(BM-17, RT-10)*
3. **Live routing advisory** (worked out on the fly, never stored): (a) Routing not active, (b) operation removed — both **block approval**; (c) Routing data outdated — **information only**. The stored BOM warning is for component warnings only (including the sub-BOM warning). *(BM-18, RT-04)*

### 5.7 BOM form & pages
1. **Form layout:** one card per Routing operation in sequence order, with materials inside, a primary / alternative badge, the Routing's saved setup / run / yield figures, and a notice for operations without materials. Create and edit share one form. *(BM-12, BM-13)*
2. **Material rollup tab (live):** one row per material with quantity, alternative quantity, gross quantity, effective scrap %, unit (once) and the sub-BOM picker; a drawer per material lists its allocations (with gross per allocation) and "Jump to Op Card". The browser uses the same rules as the server. Alternatives are badged "Alternative — not in total" and excluded from every total. *(BM-15, BM-22, BM-23, BM-24)*
3. **Draft recovery after F5:** unsaved work is restored from the browser (7-day expiry, "Discard draft"), kept separate per tenant and user; on edit an old draft is discarded if the BOM changed since. *(BM-16)*
4. **Show page — Components tab:** this BOM's direct materials only (no nested tree, no sub-BOM column), each with an allocation drawer. *(BM-19)*
5. **Show page — Multi-level explosion tab:** explodes **the BOM being viewed** (any status), following each material's chosen sub-BOM, using gross primary demand; alternatives listed for information only, never exploded or summed; sub-BOM status badges. *(BM-20)*

### 5.8 BOM import & API
1. **Import** uses the same service and rules as the form. One row per allocation; the Routing (by number) and the operation (by sequence) are **required**; columns for alternative-of, priority and sub-BOM. The Routing does not need to match the product. Only **draft** BOMs can be updated (matched by number + version). The export uses the same layout. *(BM-27)*
2. **API** accepts and checks exactly what the form does. A header-only update **never touches the materials**. Responses return both the material list and the allocations, plus the Routing advisory. *(BM-28)*

---

## 6. Workflows

### W1. Set up a Work Center and get its figures
1. Create the Work Center (no calculation yet — pages show "Calculation Pending"). *(WC-32, WC-42)*
2. Add sections, each with at least one shift. *(WC-04)*
3. Add machines and/or manual batches to sections; assign employees (shift check) and contractor allocations. *(WC-06, WC-13, WC-19)*
4. Each change arms the countdown; when it ends the queued sections are calculated, then the Work Center roll-up. Or click **Recalculate** for everything now. *(WC-33 … WC-36)*
5. Snapshots #N are created; every page shows snapshot figures with plain-English audit details. *(WC-37 … WC-42)*

### W2. Create, approve and retire a Routing
1. Create a draft; add operations — Work Center → section → machine **or** batch (or external with vendor). Figures come from the section's latest snapshot; a section without a snapshot cannot be used. *(RT-06, RT-07, RT-19)*
2. Enter setup per operation / per piece and yield; the form shows Cost / Unit and setup cost per run for 1 unit. *(RT-12, RT-16)*
3. Add alternative operations if needed (replace a primary, unique priority). *(RT-10)*
4. Save — the backend re-checks and stores all figures with the snapshot number. *(RT-19)*
5. Submit (≥ 1 operation) → approve → **active** (or scheduled). *(RT-03)*
6. Later changes: duplicate → new draft version → approve. The old version stays active until it is **retired** (or its end date passes); retiring makes its approved BOMs inactive and pending BOMs draft — the confirmation lists them first. *(RT-02, RT-05)*

### W3. Create and approve a BOM
1. Create a draft: choose the product and the Routing; the operation cards appear. *(BM-01, BM-12)*
2. Add materials inside each operation card (quantity, unit, scrap %); add alternatives with priority where needed. *(BM-05 … BM-07)*
3. Check the material rollup tab; choose sub-BOMs per material (warnings for scheduled ones). *(BM-08, BM-15)*
4. Save — the master list is rebuilt from the allocations. *(BM-04)*
5. Submit (≥ 1 material) → approve (Routing active or starting in time; no deleted operations; no inactive sub-BOM). *(BM-02, BM-17, BM-18)*
6. On approval the previous approved BOM of the same product and type is retired automatically (or scheduled to expire). *(BM-02)*

### W4. Change an approved BOM (new version)
1. Click **Revise** (or Duplicate) on the approved BOM → the create form opens pre-filled; nothing is saved yet. *(BM-03)*
2. Make the changes and save → a new draft version exists; the old version is still approved and in use.
3. Submit and approve the new version → the old one becomes inactive (or gets an end date if the new one starts later). *(BM-02)*

### W5. A Routing changes under existing BOMs
| What happened to the Routing | Draft BOM | Pending BOM | Approved BOM |
| :--- | :--- | :--- | :--- |
| Draft Routing edited, operation removed | "Deleted" card; fix before save *(BM-17)* | Back to draft needed; fix before approval *(BM-17)* | Not possible (approved BOMs use active Routings) |
| Routing rejected | Unchanged, advisory *(RT-04)* | → draft *(RT-04)* | — |
| Routing retired / expired / cancelled | Unchanged, advisory *(RT-05)* | → draft *(RT-05)* | → inactive; create a new BOM version on an active Routing *(RT-05)* |
| Routing data outdated (new snapshot, inactive resource) | Advisory (info only) *(BM-18)* | Advisory (info only) | Advisory (info only); keeps using saved figures |

### W6. A sub-BOM changes under a parent BOM
| What happened to the sub-BOM | Effect on the parent |
| :--- | :--- |
| Scheduled to be deactivated (chosen anyway) | Warning while editing the draft *(BM-08)* |
| Scheduled to be activated (chosen) | Warning while editing; on approval the parent's start moves to the sub-BOM start → scheduled *(BM-08)* |
| Became inactive / cancelled before parent approval | Parent approval blocked *(BM-08)* |
| Became inactive / cancelled after parent approval | Parent warning "create a new version"; status unchanged; **no new production** with it; running orders continue *(BM-08)* |

---

## 7. Open items — not yet decided

These are **not rules yet**. They are listed so nobody treats current behaviour as agreed.

| Area | Item | Where tracked |
| :--- | :--- | :--- |
| Work Center | Section with no active shift (fallbacks) | WC-04 → WC-ISSUE-01 … 04 |
| Work Center | Shift-module issues: "(Ext)" employees in timeline, 24-hour employee shifts, 8-hour guesses, batch shift checks, rate consistency | WC-ISSUE-05 … 10 (Shift module review) |
| Work Center | Currency conversion gaps (machine costs, HRMS salary) + final currency audit | WC-46 → WC-ISSUE-11, 12, WC-FINAL-01 |
| Work Center | How an "idle" machine is represented | WC-MACH-01 (Machine module) |
| Work Center | What inactive / delete does to connected data; countdown triggers from other modules | WC-FUT-02 |
| Work Center | Capacity unit of measure | WC-FUT-01 |
| Routing | External (subcontract) operations — cost with yield, vendor check | RT-08 (provisional) → RT-ISSUE-01 |
| Routing | Inspection / QC operations | RT-09 |
| Routing / BOM | Overhead in routing and BOM cost | RT-17 (on hold) |
| Routing | Parallel operations and resource collisions | RT-31 |
| Routing / BOM | Downstream: production orders, plans, MRP, scheduling, MES | RT-32, BM-29 → BM-DEF-03 |
| Routing / BOM | ECO (engineering change orders) | RT-33 |
| BOM | Scrap rules in detail and the link with routing yield | BM-10 → BM-DEF-01 |
| BOM | **All BOM costing** — material cost live vs saved, which product cost field, sub-BOM material cost, BOM routing cost, cost columns | BM-21 … BM-26 → BM-DEF-02 |
