# 04 - Manufacturing, quality and assets (FRS 2.14, 2.15, 2.16, F11, F13)

---------------------------------------------------------------------------
## 2.14 Production - BUILT (small gaps)

**Current workflow (verified/survey)**
Production request (from Sales MaterialRequirement) -> production order (draft -> released -> in_progress -> completed -> closed) from approved plan/BOM/routing -> operations per work centre/section with dependencies -> MES execution + scan logs -> WIP transactions -> rework/scrap/downtime -> batch genealogy and lot traceability -> completion: ProductionOrderCompleted (closes linked project task, receipts finished goods, cost/variance, journals).
Also: shifts, calendars, machine states, OEE, KPI targets, andon/alerts, cost adjustments, import/export, subcontract.

**Required workflow (FRS)**
1. Orders release from approved planning using the CURRENT BOM, routing, drawing and Job Order.
2. Job cards per work centre (panel, metal, upholstery, assembly, finishing).
3. An operation may start ONLY for the specific units/lot quantity whose predecessor and QC are complete and whose material is accepted; accepted partial quantities may advance while the balance stays upstream (genuine parallel work needs approved routing).
4. Start/stop records operator, machine, quantity, time, setup, downtime, reason (scanning where possible). Material is consumed at the operation where used; returns, offcuts, scrap and approved extra issue recorded.
5. Output above the open manufacturing quantity is blocked; consumption tolerances and rework/scrap cost are configurable.
6. Breakdown puts affected work on hold and raises a maintenance job; defects go to rework/rejection linked to the operation.
7. Finished output is labelled and moved to next operation, store, assembly, packing or job work; handovers between stages are acknowledged (F13).

**Changes required:** B-44 unit/lot-level gating + over-completion block + Job Order/revision lineage; B-43 acknowledged material route/handover (F13).
**Connects with:** Sales (requests), Inventory (issues/receipts), Quality, Maintenance, Projects (task completion), Packing (finished output), Accounting (cost).
**Acceptance (BASE-14):** Seat Upholstery cannot start before Seat Ply Cutting is accepted and fabric issued; accepted partial output advances with balance open; breakdown holds work and creates maintenance.

---------------------------------------------------------------------------
## F13 Material routes and acknowledged production handover - PARTIAL
**Today:** requisition slips and order issues move material to production.
**Required:** every move between store -> work centre -> next stage records sender, receiver, quantity, time, evidence; receiver ACCEPTS or sends back with a reason; unaccepted stock stays with the sender (A-15 pattern, B-43).

---------------------------------------------------------------------------
## 2.15 Quality - BUILT (small gaps)

**Current workflow (survey)**
Quality plans with parameters -> inspections (incoming/in-process/final) with results -> NCR -> CAPA and deviations -> rework orders; quality dashboard.

**Required workflow (FRS)**
1. Inspections are created automatically by item, vendor, process, operation and sampling plan; inspector records measurements, visual checks, sample size, instrument, result, photos. Tolerances cannot be changed during inspection; checklist and photo evidence are enforced.
2. Rejected quantity is moved aside at once and cannot be reserved, issued, packed or dispatched.
3. Manufacturing QC outcomes: ACCEPTED (moves on), REJECTED FOR SCRAP (segregate; complete authorised scrap disposition before posting scrap; never silently back to usable stock), REWORK (inspector names the operation; linked rework job; back to QC before release). All stay linked to the original production order.
4. Incoming and subcontract-return QC keep vendor-return, replacement and concession paths; rejection does not auto-scrap everything. A concession has approved scope/validity and never changes the master specification.
5. Calibrated-instrument validity and inspector independence are enforced; repeated/serious issues get root cause + corrective action with owner, due date, effectiveness check.

**Changes required:** B-45 calibration validity, concession scope/validity, inspector independence, vendor-return path.
**Acceptance (BASE-15):** accepted advances, scrap posts only after authorisation, rework returns to QC, all quantities trace to the production order.

---------------------------------------------------------------------------
## 2.16 Assets, machines, tools and maintenance - PARTIAL

**Current workflow (survey)**
Machines (location, capacity), PM schedules, maintenance work orders with assignments, spares and logs, breakdown downtime; HR asset register/allocation (laptops, ID cards...); Accounting fixed assets (depreciation, revaluation, disposal, write-off).

**Required workflow (FRS)**
1. Preventive maintenance by time, usage or condition in line with the production plan; operators report breakdowns with photos and affected jobs go on hold.
2. Technician records diagnosis, parts, labour, outside service, downtime, test result; spares are reserved/issued under control; critical spares re-ordered.
3. Instruments due for calibration are BLOCKED from use until calibrated.
4. A machine returns to production only after authorised test and release.
5. Asset/tool register with custodian, serial number, location, financial-asset link, warranty, manuals, lifecycle; transfer, disposal, external repair and warranty claims keep approvals and cost history.
6. RETURNABLE HAND TOOLS (F11): issue to a person, custody, return, maintenance (NEW).

**Changes required:** B-40 hand tools (new, in Inventory); B-42 calibration block + test release.
**Connects with:** Production (hold/maintenance), HRMS (assets/custody), Accounting (fixed assets), Inventory (spares).
**Acceptance (BASE-16):** machine fails during a work order and an instrument has expired calibration: work held, parts/downtime/cost recorded, instrument blocked, production resumes only after test release.
