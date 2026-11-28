# Production test seeders: gap audit and rebuild plan (2026-11-26)

## Status (status words: Applied = coded by Claude, not run; only the user writes Verified)

| Item | What | Status |
|---|---|---|
| SR-01 | Money ×85 in master data (products, pay grades, employees, machines, WC overhead) | Applied (2026-11-26, not run by me) |
| SR-02 | Pasted calculation snapshots + activity logs removed; engine recalculates every WC | Applied (2026-11-26, not run by me) |
| SR-03 | Machine execution leftovers cleared | Applied (2026-11-26, not run by me) |
| SR-04 | Execution seeder (all production orders) deleted | Applied (2026-11-26, not run by me) |
| SR-05 | Quality plan seeder: 4 units, 26 plan families / 28 versions through QualityPlanService | Applied (2026-11-26, not run by me) |
| SR-06 | Engineering seeder: 9 routings (costed, QC links, approved) + 13 BOM rows through the lifecycle services | Applied (2026-11-26, not run by me) |
| SR-07 | Stock seeder: opening stock per SKU (SD-03 / SD-05) | Applied (2026-11-26, not run by me) |
| SR-08 | Cleaner resets the `quality_plan` tenant sequence | Applied (2026-11-26, not run by me) |
| SR-09 | `TestProductionDataTest` adapted to the new structure (not run) | Applied (2026-11-26, not run by me) |
| SR-10 | Subcontract master data: 4 job-work vendors, 4 goods + 5 service products, subcontract procurement setting (Part C) | Applied (2026-11-27, not run by me) |
| SR-11 | Subcontract QC: 4 more plans QP-0027 … QP-0030 (2 process, 2 product) | Applied (2026-11-27, not run by me) |
| SR-12 | Subcontract engineering: routings 010–012 with outsourced operations in every mode, BOMs semi-09 / semi-10 / semifinal-04 / final-03 (TF-01 flags, TF-02 vendor-supplied lines) | Applied (2026-11-27, not run by me) |
| SR-13 | Opening stock of the new goods; services hold no stock | Applied (2026-11-27, not run by me) |
| SR-14 | Cleaner also wipes `delivery_challans` / `delivery_challan_items` (dispatch slips) | Applied (2026-11-27, not run by me) |

## Context
The user asked for a deep audit of the production test seeders, done by reading the seeder files only, not the live DB.

**Seeders in scope:** `DatabaseSeeder` → `ProductionE2EDemoSeeder` → `TestProductionMasterDataSeeder`, `TestProductionEngineeringSeeder`, `TestProductionExecutionSeeder`.

**What the user wants:**
- every gap, checked against the services and business rules
- all production orders removed
- money values fixed (the base currency changed)
- BOMs and routings rebuilt properly
- Quality Plans added, so the new QC flow (QR-01..QR-20) can be tested together with BOM and routing
- the work-center data re-checked

**Work rules (memory):**
- Record decisions in a doc first; implement only on request.
- Commit nothing.
- I run no tests and no seeding (`php -l` / `view:clear` only).
- Only the user writes "Verified".

## User decisions (2026-11-26, verbatim answers to my questions)

| # | Question | Answer |
|---|---|---|
| SD-01 | SEC-4 has 0 capacity | **"Leave it at 0 capacity"** |
| SD-02 | QM-13 demo routing (links an outdated QC version) | **"Yes, one demo routing"** |
| SD-03 | Opening stock of semis and finished goods | **"give them mixed, so when I create a PO inital PO should all be able to be completed from stock.... If I will want a mix one... I will just increase the QT to be produced, give all raw atleast 500K stock.... because they are required in huge numbers"** |
| SD-04 | Version-history examples | **"Yes, one BOM + one QC plan"** |
| SD-05 | Plan review (ExitPlanMode rejected) | **"Increase the number of opening stock, it is too low for other materials... and also make a few more QP and make some routing have 2 or 3 QP for a single OP so we can test that case also.... so make as much combo possible...."** |

---

# Part A: Gaps found (each checked against the code)

### A1. Money is still in the old USD base (biggest gap)
- Until commit d53239b1 (2026-10-21), `config/currency.php` used base USD with INR at rate 85. The base is now **INR = 1**.
- The test data was typed in ₹ through the UI on 2026-11-13/14. This branch then stored ₹ ÷ 85 (`scratch_production/HANDOFF.md` line 15: "₹80 → 0.9412").
- The seeders write these rows with raw `DB::table` inserts (`LoadsTestProductionData.php` 39–61), so nothing converts them.
- **Result: every money value in the 3 test seeders is 85× too small.**
  - test-final-01 price 72.9412 = ₹6,200
  - pay grade 0.9412 = ₹80/h
  - energy rate 0.1176 = ₹10/kWh
  - WC-1 overhead 29.4118 = ₹2,500/day
  - test-emp-1 CTC 7,058.82 = ₹6,00,000
- The HRMS employees used as direct work-centre staff (WRG-002/004/005) are already in ₹ (`HrmsDemoSeeder` 1245/1267/1278: CTC 22,00,000 / 13,50,000 / 7,20,000). The seeded WC-1 snapshot therefore adds ₹2,250/day of direct staff to 175/day of ÷85 section labour. **Every calculated cost is wrong.**
- The engine itself never converts on read (it reads `operating_cost_per_hour`, `hourly_rate`, `overhead_cost` and `current_salary`÷12÷30 raw). Conversion happens only in the controllers (`convert_to_base`), so the seeder must write ₹ directly.
- **Affected fields:**
  - products: `selling_price`, `cost_price`, `unit_cost`
  - pay grades: `hourly_rate`
  - test employees: `current_salary` (annual CTC)
  - machines: `energy_rate_per_unit`, `operating_cost_per_hour`
  - work centres: `overhead_cost` (flat per day)
  - opening-stock cost
  - all calculation snapshots, routing operation costs and `cost_snapshot`

### A2. Calculated data is pasted in, not produced by the services
- **Calculation snapshots.** `production_work_center_calculations` (3 rows) and `production_work_center_section_calculations` (10 rows) are pasted JSON.
  - The JSON carries raw employee ids (45, 46, 62, 63, 68…) and shift ids (2, 3, 63–69). The seeder does not own these: employees get the next free id and shifts get no fixed id (`TestProductionLinks` translates top-level keys only).
  - After a reseed these ids point at the wrong rows.
  - In the WC-14 snapshot, WRG-002 has CTC 4,500, which does not match HRMS.
- **Activity logs.** `production_work_center_activity_logs` (13 rows) point at the pasted snapshot ids. The engine writes its own logs anyway.
- **Routing operation costs.** These are pasted: `processing_time_minutes`, rates, `cost_per_unit`, `setup_cost_per_run`, `resource_snapshot`, snapshot ids, and `cost_snapshot`.
  - Order creation is blocked when `resource_snapshot` differs from the latest section snapshot (`RoutingCostStatusService` 146–195, `ProductionOrderEngineeringService` 348–402).
  - With ₹ inputs every pasted figure is wrong.
- **Reference pattern that already does this right:** `NonWovenPlantSeeder`.
  - It stores inputs only (INR).
  - It runs `CountdownCalculationEngine::recalculateNow($wc,'manual',SCOPE_ALL)` (line 862).
  - It costs operations with `SectionCalculationService::getSnapshotResourceCosts()` and `RoutingOperationCostCalculator::calculate()` / `buildResourceSnapshot()` (1011–1025).
  - It approves with `RoutingLifecycleService::transitionOnApproval()` and `BomLifecycleService::transitionOnApproval()`.
  - It derives BOM items from the operation allocations (1163–1230, same rules as `ProductionBomService::persistBomItems`).

### A3. Production orders and everything hanging off them (the user says delete)
- **What `TestProductionExecutionSeeder` holds:**
  - 4 completed orders (qty 1)
  - 15 operations, 10 dependencies, 15 progress logs
  - 26 reservations, 4 requisition slips with 26 items
  - 26 issues with 26 issue batches, 4 receipts
  - 4 schedules with 15 schedule operations
  - 4 WIPs with 53 WIP transactions
  - 69 timeline events, 1 machine downtime, 21 machine state histories
  - stock ledger rows posted by `postStockMovements()`
- **Its opening stock** = 100 per product plus whatever the orders issued.
- **Leftovers in the master data:** machines 44, 45, 49 and 54 carry `current_state_reason` 'Operation Completed'. Machine 44 has `maintenance_status` 'none' and `last_maintenance_date` 2026-11-14.

### A4. Routings: too thin, placeholder names, no QC
- **Only 2 routings,** named "R1" and "R2". Operation names are "op1", "op2", "op12", "op1 3", "op1 4".
- **WC-3 is never used.** SEC-9 assembly and SEC-10 "Final Inspection & Crating" appear in no routing.
- **No QC anywhere:** `quality_required` = 0 on every operation, and no rows in `production_routing_operation_quality_plans`.
- **Status:** 'active' is a valid routing status (`Routing.php` 23–37: draft, pending_approval, active, scheduled, historical, cancelled), but it is hand-typed rather than set by the lifecycle.
- **Past start dates:** `effective_from` 2026-11-14 is in the past, so a re-save through the services is refused ("start date cannot be in the past").

### A5. BOMs: broken version chain, odd quantities, gaps in coverage
- **Copies switched product.** BOMs 11, 13 and 17 carry "Duplicated from…" and a `Revision Created` row, but each was copied from another product's BOM. This breaks BM-03 rule 1: a copy is a new draft of the same product.
- **Inconsistent versions and numbers:**
  - BOMs 13 and 16 are versions of the same product (semifinal-01), but their numbers differ (SEMI-03 / SEMI-04). A copy keeps its number (`ProductionBomService` 47–52).
  - The version goes 5 → 2.
  - The numbers BOM-TEST-SEMI-03/04/05 belong to semi-final products; the generator would make BOM-TEST-SEMIFINAL-01 (`ProductionBomNumberService` 18).
- **Placeholder names:** B1, B2, B25, B50. "B2" is used on 3 BOMs.
- **Quantities that make no sense:**
  - 1 steel leg column needs 55 m of gasket and 34 foot pads
  - 1 table needs 55 chassis
  - scrap is always 0
- **Products with no BOM:** semi-03..08, semifinal-03, final-02.
- **Raw materials never used:** 11 of 25.
- **Same routing reused:** 4 different semis all run the same R1.
- No cost rollup is stored anywhere (by design: BM-21 is open), so there is nothing extra to seed.

### A6. Quality Control: nothing seeded
- **No plans of any kind:** no plan, parameter, approval or routing QC link. QR-01..QR-20 can't be tested without building everything by hand.
- **No measuring units:** at seed time only Pcs, Mtr, Set and Kg exist. The provisioner adds only time and service units (`ProvisionInventoryDefaults` 12–25). There is no mm or µm for numeric checks.
- **QP numbers won't restart:** they come from `tenant_sequences` key `quality_plan` (`QualityPlanService::nextPlanNumber` 413–425). `CleanBeforeHrmsSeeder` never resets that row, so after a reseed numbers continue from the old counter.

### A7. Work centres, sections, machines, staffing (re-checked)
1. **SEC-4 "Hardware Staging & Kitting"** has 2 employees and 2 external allocations but no machine and no batch.
   - Capacity 0, cost per unit N/A, zero-output warning; no routing operation can use it (`ValidatesRoutingOperations` 246–272).
   - **SD-01: keep as is.** test-semi-08 therefore gets no BOM or routing; the finished tables use the traded fastener set test-raw-23 instead.
2. **SEC-5** batch-5 and **SEC-3** batch-1 are staffed by external workers only (allowed).
3. **Machines with no section:** TEST-MAH-8, 10, 11 and 12. They are in no calculation and can't be used in a routing. Kept as spare machines for assignment tests.
4. **test-emp-25** (SH-16-20) sits in SEC-1, whose shifts are MRN, EVE and SH-00-06. The engine flags `is_outside_section_shifts`: it counts the cost but leaves the employee out of the timeline. **test-emp-8** (4-hour shift) is direct staff on WC-2 and WC-3. Both kept as warning cases.
5. **8 employees assigned nowhere:** test-emp-5, 7, 11, 16, 24, 28, 29 and 30. This includes the 3 inspectors.
6. **Unused shifts:** SH-19-04 and SH-07-19 are used by no section.
7. **WRG-005** (Rohan Kulkarni) is direct staff on 2 work centres but in an exit / notice period in HRMS.
8. **HR-side data quality (not changed; listed only):**
   - all 30 test employees are 'Male', including Pooja, Sneha, Meena, Kavita…
   - `experience` 0
   - all on the "Executive & Leadership" pay group, salary structure and leave plan
   - `weekly_pattern` '["off"]'
9. **Raw user ids:** `created_by` / `approved_by` = 1 and activity-log `user_id` = 3, instead of being looked up.
10. **What checks out:** batch shifts, allocation shifts, batch-to-employee links, and the one-section-per-employee rule all match `WorkCenterEmployeeService` / `ExternalWorkerAllocation` rules.

### A8. Stock
- Every product gets 100, plus what the orders issued, at cost ÷ 85.
- **Stock that counts for an order:** branch stock (`quantity − reserved_qty`) in warehouses of the order's branch that are active and not subcontractor/virtual (`ProductionOrderEngineeringService` 49–89).
- The test warehouses sit on branch **HQ**. The tester's session branch must be HQ, or no stock counts and every semi is made inside the order.

---

# Part B: The rebuild

### Step 0: Docs and memory
- New `docs/production_seeder_audit_2026-11-26.md`, containing:
  - Part A
  - decisions SD-01..SD-05 (verbatim)
  - the data tables below
  - a status column per item (Applied → the user writes Verified)
- Memory: a project note for this audit, plus an index line.

### Step 1: Seeder structure
```
ProductionE2EDemoSeeder
  1 TestProductionMasterDataSeeder      products, shifts, pay grades, employees, WCs, sections, machines,
                                        batches, staffing, allocations  → then recalculate every WC (engine)
  2 TestProductionQualitySeeder (new)   units mm / µm / Nm / deg, 26 quality plans (28 versions) through QualityPlanService
  3 TestProductionEngineeringSeeder     9 routings (costed by the calculator, QC links, approved)
                                        + 13 BOM rows for 12 products (items derived; semi-04 has 2 versions)
  4 TestProductionStockSeeder (new)     opening stock per SKU (SD-03); no orders, no movements
TestProductionExecutionSeeder          deleted (all orders and execution tables gone)
```
- Owned rows are keyed by code; all references are natural keys (`TestProductionLinks`).
- Users are looked up by email: `admin@example.com`, which `DatabaseSeeder` creates. A missing user stops the seed with a clear message.
- The engine, plan and lifecycle calls run inside `TenantRunner::run($tenant, …)`. `HrmsDemoSeeder` leaves a tenant context set.
- `CleanBeforeHrmsSeeder`: additionally delete this tenant's `tenant_sequences` row with key `quality_plan`, so plan numbers restart at QP-0001. No other cleaner change.
- `AddTestMastersSeeder` is unchanged. Its test asserts exactly 4 units, so mm / µm are added by the quality seeder.

### Step 2: Master data changes (`TestProductionMasterDataSeeder`)
1. **Money ×85**, written as the ₹ value originally typed. Every converted value is a whole rupee; the implementation checks this.

   | What | ₹ values |
   |---|---|
   | Pay grades pg-1..6 | 80 / 120 / 175 / 225 / 300 / 380 per hour |
   | WC overhead per day | WC-1 2,500 · WC-2 3,200 · WC-3 1,800 |
   | Machine energy rate | electricity 10/kWh · diesel (MAH-14) 90 · others (MAH-15) 80 |
   | Machine operating cost per hour, MAH-1..15 | 200, 120, 150, 140, 60, 180, 40, 160, 210, 260, 100, 350, 450, 600, 800 |
   | Test employee CTC (annual) | 6,00,000 · 5,40,000 · 5,10,000 · 4,80,000 · 4,50,000 · 4,20,000 · 3,90,000 · 3,60,000 (each value ×85) |

   **Products (cost; selling price only for finals):**

   | SKU | ₹ | SKU | ₹ | SKU | ₹ |
   |---|---|---|---|---|---|
   | raw-01 | 80 | raw-10 | 60 | raw-19 | 2 |
   | raw-02 | 150 | raw-11 | 110 | raw-20 | 15 |
   | raw-03 | 220 | raw-12 | 140 | raw-21 | 65 |
   | raw-04 | 25 | raw-13 | 280 | raw-22 | 120 |
   | raw-05 | 15 | raw-14 | 450 | raw-23 | 75 |
   | raw-06 | 30 | raw-15 | 650 | raw-24 | 160 |
   | raw-07 | 180 | raw-16 | 40 | raw-25 | 350 |
   | raw-08 | 240 | raw-17 | 5 | | |
   | raw-09 | 320 | raw-18 | 3 | | |

   | SKU | ₹ |
   |---|---|
   | semi-01..08 | 180, 120, 220, 850, 320, 190, 260, 110 |
   | semifinal-01 / 02 / 03 | 1,400 / 1,950 / 1,200 |
   | final-01 | cost 3,500, sell 6,200 |
   | final-02 | cost 4,800, sell 8,500 |

2. **Calculations:** delete the pasted calculations (3 + 10 rows) and activity logs (13 rows). At the end, call `CountdownCalculationEngine::recalculateNow($wc, 'manual', SCOPE_ALL)` for WC-1, WC-2 and WC-3. That gives snapshot #1 per section and work centre, with the engine's own logs.
3. **Machines:** remove the execution leftovers. MAH-44/45/49/54 get no `current_state_reason`; MAH-44 gets `maintenance_status` 'operational' and no `last_maintenance_date`. All machines start Idle.
4. **Everything else unchanged:** work centres, sections, machines, batches, staffing, allocations, shifts, employees (including SEC-4 at 0 capacity, per SD-01) and the HR-side fields in A7-8.

### Step 3: Quality plans (new `TestProductionQualitySeeder`)
- **Units:** add `mm` (Millimetre), `um` (Micron), `Nm` (Newton metre) and `deg` (Degree) to the tenant.
- **How plans are made:** every plan goes through `QualityPlanService` `create → submit → approve`. Version 2 uses `createNextVersion → submit → approve`, which turns v1 historical automatically. Numbers, `root_plan_id`, `tenant_sequences` and the approval history therefore come from the real code.
- **Parameters:** Numeric rows carry min/max and a unit; Pass/Fail rows carry a basis. ★ = mandatory, others optional.

There are 26 plan families: 28 version rows, with numbers QP-0001..QP-0026 in creation order.

**Section plans**

| QP | Name | Section | Status | Checks |
|---|---|---|---|---|
| 0001 | Edge deburr check | SEC-3 | Approved | ★Sharp edges (P/F: "No sharp edge felt with a gloved hand") · ★Burr height 0–0.2 mm |
| 0002 | Cut length check | SEC-1 | Approved | ★Cut length deviation −0.5 to 0.5 mm · ★Cut squareness (P/F: "No gap under a try-square") |
| 0003 | Bend angle check | SEC-2 | Approved | ★Bend angle 89.5–90.5 deg · Springback notes (text) |
| 0004 | Weld inspection | SEC-8 | Approved | ★Porosity / cracks (P/F: "None visible under 5× lens") · ★Fillet size 4–6 mm · Notes (text) |
| 0005 | Weld spatter & cleanup | SEC-8 | Approved | ★Spatter removed (P/F) · Grinding marks (P/F) |
| 0006 | Powder coat inspection | SEC-8 | Approved | ★Coating thickness 60–90 µm · ★Cross-hatch adhesion (P/F: "Class 0–1, ISO 2409") · Colour match (P/F) |
| 0007 | Hand finish touch test | SEC-6 | Approved | ★Smooth to touch (P/F) · Grit grade used (text) |
| 0008 | Seal bead check | SEC-5 | Approved | ★Bead width 3–5 mm · ★Continuous bead (P/F) |
| 0009 | Assembly torque check | SEC-9 | Approved | ★Bolt torque 22–26 Nm · ★All fasteners fitted (P/F) |
| 0010 | Final crating inspection | SEC-10 | Approved | ★Surface scratches (P/F) · ★Label & barcode (P/F) · Carton drop test (P/F) |
| 0011 | Packing count & seal | SEC-10 | Approved | ★Parts count matches packing list (P/F) · ★Tape seal intact (P/F). Pass/Fail only. |
| 0012 | Flange hole check | SEC-7 | **v1 Historical, v2 Approved** (SD-02) | v1: hole pitch −0.2 to 0.2 mm · v2: hole pitch −0.1 to 0.1 mm, ★thread go/no-go (P/F) |
| 0013 | CNC first-article check | SEC-7 | **Draft** (never submitted) | ★Hole position −0.1 to 0.1 mm · edit / submit / delete test |
| 0014 | Coating gloss check | SEC-8 | **Draft after a rejection** (created → submitted → rejected: "Add the gloss meter reading in GU") | ★Gloss level (P/F) · reject history test |

**Process-general plans**

| QP | Name | Status | Checks |
|---|---|---|---|
| 0015 | Dimensional check — general | Approved | ★Length deviation −1 to 1 mm · ★Squareness deviation 0–2 mm. Numeric only. |
| 0016 | Visual surface check — general | Approved | ★Dents / scratches (P/F: "None larger than 2 mm at 1 m") · Notes (text) |
| 0017 | Safety & sharp-edge check — general | Approved | ★No sharp edges or burrs (P/F) · Guard notes (text) |
| 0018 | Cleanliness check — general | Approved | Oil / dust free (P/F) · Notes (text). **No mandatory check at all.** |
| 0019 | Packaging check — general | **Pending approval** | ★Carton undamaged (P/F) · approve / reject test (Approval Center) |

**Product plans** (at most one family per product, QM-14)

| QP | Name | Product | Status | Checks / purpose |
|---|---|---|---|---|
| 0020 | Steel leg column check | semi-01 | Approved | ★Length 719–721 mm · ★Straightness (P/F: "≤ 0.5 mm over length"). Looped-semi QC. |
| 0021 | Corner brace check | semi-05 | Approved | ★Brace angle 44–46 deg · ★Weld joint (P/F). Looped QC, 2nd level. |
| 0022 | Base chassis check | semifinal-01 | Approved | ★Diagonal difference 0–3 mm · ★Weld completeness (P/F). Looped QC in final-01 orders. |
| 0023 | Top board check | semifinal-03 | Approved | ★Thickness 24.5–25.5 mm · ★Edge seal (P/F) · Notes (text). Looped QC in final-01 and final-02 orders. |
| 0024 | Executive table final QC | final-01 | Approved | ★Top flatness 0–1.5 mm · ★Wobble test (P/F) · ★Finish (P/F) · Remarks (text). WIP final QC. |
| 0025 | Premium table final QC | final-02 | **v1 Historical, v2 Approved** (SD-04) | v1: flatness 0–2 mm, wobble · v2: flatness 0–1.5 mm, wobble, ★cable routing (P/F). WIP final QC uses v2. |
| 0026 | Steel tube incoming check | raw-01 (traded) | Approved | ★Wall thickness 1.9–2.1 mm · ★Rust (P/F). No gate yet (GRN QC is deferred, QM-02). |

semi-02, 03, 04, 06 and 07 and semifinal-02 have **no** product plan. A loop that makes them shows that case: routing QC only, no product check.

**Lifecycle states covered:** draft, draft-after-reject, pending, approved, and historical (2 families). Plans with no mandatory check, pass/fail-only plans and numeric-only plans are all present.

### Step 4: Routings (rewrite of `TestProductionEngineeringSeeder`, part 1)
- **How routings are built:**
  - Data rows use codes (section, machine, batch).
  - Each operation is costed with `getSnapshotResourceCosts` + `RoutingOperationCostCalculator` (NonWoven pattern), so figures and `resource_snapshot` match the engine exactly.
  - An alternative operation gets `replaces_operation_id` + `priority` 1 and copies its primary's predecessor; other operations chain to the previous primary (rule in `RoutingService` 698–740).
  - QC operations get `quality_required` = 1 and `operation_type` 'inspection' (as the form does), plus rows in `production_routing_operation_quality_plans` (`is_mandatory`, `sort_order`).
  - Audit rows Created → Submitted → Approved, then `RoutingLifecycleService::transitionOnApproval` (status active, effective today, `cost_snapshot`).
- **Numbers and versions:** RTG-2026-000001..009, version 1.0.0, `is_default` 1.
- **Operation lists:** "alt" = alternative to the operation named; QC entries list the linked plans.

Operation list per routing. **★ = mandatory plan**, (opt) = optional plan, **"looped +"** = the product plan added on that operation when the semi is made inside an order (source `sub_assembly`, always mandatory).

**001 Tube Cutting & Deburring** (used by semi-01)

| Op | Resource | QC plans |
|---|---|---|
| OP-010 Cut tube to length | SEC-1 / MAH-5 cold saw | ★QP-0002 cut length + QP-0015 dimensional (opt) |
| OP-020 Cut tube — plasma (alt OP-010) | SEC-1 / MAH-9 | ★QP-0002 only (an alternative with different QC from its primary) |
| OP-030 Deburr & clean | SEC-3 / batch-1 | ★QP-0001 deburr + ★QP-0017 safety; **looped + ★QP-0020** (3 mandatory) |

**002 Sheet Blanking & Bending** (semi-02)

| Op | Resource | QC plans |
|---|---|---|
| OP-010 Laser blanking | SEC-1 / MAH-1 | **QC off** |
| OP-020 Press-brake bending | SEC-2 / MAH-2 | ★QP-0003 bend angle (single plan) |
| OP-030 Forge-press bending (alt OP-020) | SEC-2 / MAH-14 | ★QP-0003 + QP-0018 cleanliness (opt) |
| OP-040 Deburr | SEC-3 / batch-1 | ★QP-0015 dimensional + QP-0001 (opt) + QP-0017 (opt): 1 mandatory, 2 optional |

**003 CNC Panel Machining & Finishing** (semi-04, both BOM versions)

| Op | Resource | QC plans |
|---|---|---|
| OP-010 CNC route & chamfer | SEC-7 / MAH-3 | QC off |
| OP-020 CNC route — 5-axis (alt OP-010) | SEC-7 / MAH-13 | QC off |
| OP-030 Hand finishing | SEC-6 / batch-6 | ★QP-0007 touch test + ★QP-0016 visual (section + process, both mandatory) |

**004 Robotic Weld & Powder Coat** (semi-05, semifinal-01)

| Op | Resource | QC plans |
|---|---|---|
| OP-010 Robotic welding | SEC-8 / MAH-4 | ★QP-0004 weld + ★QP-0005 spatter (2 plans of the same section) + QP-0015 (opt) |
| OP-020 Powder coating | SEC-8 / MAH-6 | ★QP-0006 coat; **looped + ★QP-0021** (semi-05) **or ★QP-0022** (semifinal-01) |

**005 Profile Cutting & Coating** (semi-06, semi-07)

| Op | Resource | QC plans |
|---|---|---|
| OP-010 Cut to length | SEC-1 / MAH-5 | QP-0002 (opt) only: QC on, nothing mandatory |
| OP-020 Pre-wash & powder coat | SEC-8 / MAH-6 | ★QP-0006 + QP-0018 (opt) |

**006 Frame Assembly & Priming** (semifinal-02)

| Op | Resource | QC plans |
|---|---|---|
| OP-010 Frame assembly | SEC-9 / batch-9A | ★QP-0009 torque + ★QP-0015 dimensional |
| OP-020 Priming & curing | SEC-9 / MAH-15 | QP-0016 (opt) + QP-0018 (opt): only optional plans |

**007 Top Board Sealing** (semifinal-03)

| Op | Resource | QC plans |
|---|---|---|
| OP-010 Seal edges & fit inserts | SEC-5 / batch-5 | ★QP-0008 seal bead + ★QP-0016 visual + QP-0017 (opt); **looped + ★QP-0023** (4 plans) |

**008 Final Assembly, Inspection & Packing** (final-01, final-02)

| Op | Resource | QC plans |
|---|---|---|
| OP-010 Table assembly | SEC-9 / batch-9A | ★QP-0009 torque |
| OP-020 Table assembly — morning crew (alt OP-010) | SEC-9 / batch-9B | ★QP-0009 + QP-0017 (opt) |
| OP-030 Final inspection & crating | SEC-10 / batch-10A | ★QP-0010 crating + ★QP-0011 packing count (2 plans of the same section) + QP-0015 (opt) |
| OP-040 Automated packing | SEC-10 / MAH-7 | QC off |

The finished table then needs **WIP final QC**: QP-0024 for final-01, QP-0025 v2 for final-02.

**009 Flange Drilling (QC version demo)** (semi-03)

| Op | Resource | QC plans |
|---|---|---|
| OP-010 CNC drill & tap | SEC-7 / MAH-13 | ★QP-0012 **v1 (historical, outdated)** + ★QP-0015 (current): an outdated and a current plan on one operation |

**Combinations covered:**

| Case | Where |
|---|---|
| QC off | 002/010, 003/010–020, 008/040 |
| 1 mandatory plan | 002/020, 008/010 |
| 1 optional plan only | 005/010 |
| 2 optional plans only | 006/020 |
| section + process, both mandatory | 003/030, 006/010 |
| section mandatory + process optional | 001/010, 005/020 |
| 2 plans of the same section + 1 optional | 004/010, 008/030 |
| 1 mandatory + 2 optional | 002/040 |
| 3 mandatory (with looped) | 001/030 |
| 4 plans (with looped) | 007/010 |
| alternative operation with different QC | 001/020, 002/030, 008/020 |
| routing plan + looped product plan | 001/030, 004/020, 007/010 |
| outdated + current plan | 009/010 |

- **Routing 009 is the SD-02 demo.** The link to QP-0012 v1 is inserted directly, because the routing form refuses a historical plan. The routing view then shows "newer approved version v2", and new orders of semi-03 are blocked (QR-19).
- **Other links:** all point at the current approved version. Links are written in the order listed (`sort_order`).
- **Setup times:** 5–15 min per run; 0–1 min per piece on manual operations; yield 100%, except cutting at 98%.
- **Coverage:** all 3 work centres and 8 of 10 sections are used. SEC-2 is used only through MAH-2 / MAH-14; SEC-4 has 0 capacity (SD-01).
- **Alternatives covered:** a machine alternative (001, 002, 003) and a crew alternative (008).

### Step 5: BOMs (rewrite of `TestProductionEngineeringSeeder`, part 2)
- **How BOMs are built:**
  - Draft BOM → operation allocations (codes) → BOM items derived as in `persistBomItems` → audit rows Created / Submitted → `BomLifecycleService::transitionOnApproval`.
  - Child BOMs are approved before their parents, and `child_bom_id` always points at the current approved child (BM-08).
- **Numbers and names:** BOM-TEST-{SKU}, version 1.0.0, base qty 1, named after the product.
- **Quantities** are per 1 unit; "@OP" is the routing operation the material is allocated to. An alternative operation repeats the materials of its primary.

| BOM | Routing | Materials |
|---|---|---|
| semi-01 Leg column | 001 | raw-01 0.72 Mtr @010/@020 (alt material raw-02 0.72 Mtr, priority 1) · raw-11 0.02 Kg @010/@020 |
| semi-02 Bracket | 002 | raw-14 0.1 Pcs (10 per sheet) @010 |
| semi-03 Flange plate | 009 | raw-16 1 Pcs · raw-11 0.01 Kg @010 |
| semi-04 Top panel **v1.0.0 → inactive** | 003 | raw-15 1 Pcs @010/@020 |
| semi-04 Top panel **v1.1.0 approved** (same number; SD-04) | 003 | raw-15 1 Pcs @010/@020 · raw-13 0.05 Kg edge seal @030. The lifecycle supersedes v1.0.0 and writes a "Deactivated" row. |
| semi-05 Corner brace leg | 004 | semi-01 1 Pcs ▸child · semi-02 1 Pcs ▸child · raw-12 0.05 Kg @010 · raw-07 0.05 Kg @020 |
| semi-06 Stiffener | 005 | raw-01 0.8 Mtr @010 · raw-07 0.1 Kg @020 |
| semi-07 Aluminium profile (per Mtr) | 005 | raw-02 1 Mtr, 2% scrap @010 · raw-25 0.05 Set + raw-07 0.08 Kg @020 |
| semifinal-01 Base chassis | 004 | semi-05 4 Pcs ▸child · semi-06 2 Pcs ▸child · raw-12 0.2 Kg @010 · raw-10 0.5 Kg + raw-07 0.3 Kg @020 |
| semifinal-02 Frame assembly | 006 | semi-07 5.4 Mtr ▸child · semi-03 4 Pcs ▸child @010 · raw-08 0.2 Kg + raw-09 0.05 Kg @020 |
| semifinal-03 Top board | 007 | semi-04 1 Pcs ▸child (v1.1.0) · raw-13 0.3 Kg · raw-04 5.4 Mtr @010 |
| final-01 Executive table | 008 | semifinal-01 1 ▸child · semifinal-03 1 ▸child · raw-23 1 Set · raw-20 4 Pcs @010/@020 · raw-21 1 + raw-22 1 @040 |
| final-02 Premium table | 008 | semifinal-02 1 ▸child · semifinal-03 1 ▸child · raw-23 2 Set · raw-24 1 Set · raw-20 6 Pcs @010/@020 · raw-21 1 + raw-22 2 @040 |

**Product trees:**
- final-01 → semifinal-01 → semi-05 → semi-01 / semi-02; semifinal-01 → semi-06; final-01 → semifinal-03 → semi-04. That is 4 levels.
- final-02 → semifinal-02 → semi-07 / semi-03; final-02 → semifinal-03.

**Not used, kept as traded spares:** semi-08 (SD-01), raw-03, 05, 06, 17, 18, 19.

### Step 6: Opening stock (new `TestProductionStockSeeder`, SD-03)

| Product | Stock |
|---|---|
| All 25 raw materials | **5,00,000** each (WH-RM-TBL) |
| semi-01 Leg column | 2,000 |
| semi-02 Bracket | 2,000 |
| semi-03 Flange plate | 10,000 (very high, so the final-02 tree never has to make it and hit the SD-02 block) |
| semi-04 Top panel | 500 |
| semi-05 Corner brace | 400 |
| semi-06 Stiffener | 200 |
| semi-07 Profile | 2,000 Mtr |
| semi-08 | 0 (unused, SD-01) |
| semifinal-01 Base chassis | 100 |
| semifinal-02 Frame assembly | 100 |
| semifinal-03 Top board | 150 (shared by both tables) |
| final-01 / final-02 | 25 each |

- Semis are booked in WH-WIP-TBL, finals in WH-FG-TBL.
- **Every first, normal-size order runs from stock.** A larger quantity makes semis inside the order, deeper as it grows (thresholds while nothing is reserved):
  - final-01 up to 100 runs from stock.
  - 101+ makes chassis in the order: looped QC QP-0022.
  - Above 200 (100 chassis made × 4 braces = 400) braces are made too: QP-0021.
  - Top boards are made above 150 (shared with final-02): QP-0023.
  - Legs are made only beyond 2,000 braces: QP-0020.
  - A semi-01 or semi-05 order on its own always runs from raw stock.
- **How it is booked:**
  - one 'Opening Stock' IN ledger row per product and warehouse at the product's ₹ cost, `balance_qty` = qty
  - `product_warehouse_stocks` gets `quantity` = `available_qty` = qty, `reserved_qty` 0
  - `products.opening_stock` / `opening_stock_rate` set
  - no journals (as today)

### Step 7: Follow-ups in the same change
- `tests/Feature/Seeding/TestProductionDataTest.php` reads every seeder's `tables()`, and the shapes change (the execution seeder goes, engineering uses codes). I'll update it to the new structure; I won't run it (memory rule).
- `scratch_production/run_production_master_seed.php`: update its call list if it names the execution seeder.

## Files to change
- `database/seeders/TestProductionMasterDataSeeder.php`: money, calculations removed + engine call, machine leftovers.
- `database/seeders/TestProductionEngineeringSeeder.php`: rewritten (routings + BOMs as above).
- `database/seeders/TestProductionQualitySeeder.php` (new).
- `database/seeders/TestProductionStockSeeder.php` (new).
- `database/seeders/TestProductionExecutionSeeder.php`: deleted.
- `database/seeders/ProductionE2EDemoSeeder.php`: new call order.
- `database/seeders/Support/TestProductionLinks.php`: add a user lookup by email, a section/machine/batch code lookup and a product SKU lookup.
- `database/seeders/CleanBeforeHrmsSeeder.php`: reset the `quality_plan` row in `tenant_sequences`.
- `tests/Feature/Seeding/TestProductionDataTest.php`: adapted, not run.
- `docs/production_seeder_audit_2026-11-26.md` (new).

**Services reused, not changed:** `CountdownCalculationEngine`, `SectionCalculationService`, `RoutingOperationCostCalculator`, `RoutingLifecycleService`, `BomLifecycleService`, `QualityPlanService`, `TenantRunner`.

## Verification
- **Me:** `php -l` on every changed or new seeder. No seeding, no tests.
- **The user:** run `php artisan db:seed` (it wipes and reseeds the test tenant), then check in the UI:
  1. **Work centres:** each WC and section shows ₹ figures (e.g. a pay grade of ₹80/h, WC-1 overhead ₹2,500/day) with snapshot #1. SEC-4 shows its zero-output warning.
  2. **Routings:** 9 active routings with costs and no "costs not calculated" warning. QC eye icons show the linked plans. Routing 009 shows the newer-version warning.
  3. **BOMs:** 13 rows. semi-04 shows v1.0.0 inactive + v1.1.0 approved. No component warnings.
  4. **Quality plans:**
     - 26 plans (QP-0001..0026, 28 versions) across every status.
     - QP-0012 and QP-0025 show version history; QP-0014 shows its reject comment; QP-0019 appears in the Approval Center.
     - The routing modal offers only approved section plans of the operation's own section, plus process plans.
  5. **Orders:**
     - A final-01 order of any normal quantity (≤ 100) runs from stock. Its QC operations show 1–3 plan checklists, as in the combinations table.
     - A final-01 order of 101+ makes chassis inside the order; the powder-coating maker operation then also needs QP-0022.
     - A semi-03 order is blocked by the outdated QC version.
  6. **WIP:** finishing a final-01 order asks for Final QC (QP-0024), and final-02 for QP-0025 v2, before receipt.
- The session branch must be **HQ** for the stock to count.

---

## Implementation notes (2026-11-26, Applied — not run by me)

**Seeder chain:** `ProductionE2EDemoSeeder` → Master → Quality → Engineering → Stock.

**Changed or new files**
- `TestProductionMasterDataSeeder.php`
  - 183 money literals ×85, rewritten as the typed ₹ value by a checked script. Every result is a whole rupee, within the stored precision.
  - Pasted calculations and activity logs removed.
  - `recalculateWorkCenters()` runs `CountdownCalculationEngine::recalculateNow(…, SCOPE_ALL)` for TEST-WC-1/2/3 inside `TenantRunner`.
  - Machine leftovers cleared.
- `TestProductionQualitySeeder.php` (new)
  - Adds units `mm`, `um`, `Nm` and `deg` (category "Goods", as `ProvisionInventoryDefaults` uses).
  - Creates 26 families through `QualityPlanService`.
- `TestProductionEngineeringSeeder.php` (rewritten)
  - Data in `routings()` / `boms()` with codes.
  - Operations are costed by `RoutingOperationCostCalculator` on the latest section snapshot.
  - QC links are checked with the same rules as the routing form. The only exception is the SD-02 link marked `outdated`.
  - Routing audit: Created → Submitted → Approved (the lifecycle writes Approved).
  - BOM audit: Created (→ Revision Created) → Submitted → Approved, with `BomLifecycleService` superseding semi-04 v1.0.0.
- `TestProductionStockSeeder.php` (new): opening stock as in Step 6.
- `TestProductionExecutionSeeder.php`: **deleted**.
- `LoadsTestProductionData`: its stock helper for orders (`issuedByProductWarehouse`) is removed.
- `TestProductionLinks`: new lookups (`userId`, `productId`, `uomId`, `sectionId`, `machineId`, `batchId`). Seeded records are created and approved by `admin@example.com`.
- `CleanBeforeHrmsSeeder`: deletes the tenant's `tenant_sequences` row `quality_plan` (backed up like the other wipes).
- `tests/Feature/Seeding/TestProductionDataTest.php` (not run)
  - Checks master rows against the schema.
  - Checks money is in ₹.
  - Checks that routings, BOMs, plans and stock point only at codes the data owns: section/machine/batch match, section plans on their own section, no product plan on a routing, one plan per product, children before parents.

**Small points to review**
- Every routing operation gets `priority` 1: the column is NOT NULL, default 1, which is what `RoutingOperationDTO` always writes.
- An operation with QC gets `operation_type` 'inspection', as the routing form does.
- `scratch_production/run_production_master_seed.php` is unchanged.
  - It never called the execution seeder.
  - Its wipe list doesn't include the quality tables. A rerun through it keeps existing plans, which the quality seeder then skips by name.
- **To load:** run `php artisan db:seed`. On its own: `php artisan db:seed --class=ProductionE2EDemoSeeder`, after the base seeders.

---

# Part C: Subcontracting examples (2026-11-27, SR-10 … SR-14, Applied, not run by me)

**Request (verbatim):** "go and check seed data, and if the data does not have sub-contracting then add it, and update the seeder with some examples of sub-contracting... so we will check these along with the QC parts.... Go and do it"

**What the check found:** the test data had no subcontracting at all.
- No routing operation was outsourced (`is_external` = 0 everywhere).
- There was no service product and no job-work vendor. The only vendors were the trading vendors VEND-ACME, APEX and MATRIX from `InventoryMasterDemoSeeder`.
- There was no subcontract setting.

**Approach:** a new, small product tree carries the examples. The 9 routings, 13 BOMs, QC combinations and stock thresholds of Parts A–B are unchanged. The flow they test is the reworked one in `docs/subcontracting_module_audit_2026-11-26.md` §11–13:
- the PR/PO is raised by button
- the store issue is the only stock-out
- the dispatch slip moves no stock
- receipt is from the menu or the floor, with a system GRN
- normal shop-floor QC applies

### C1. Master data (`TestProductionMasterDataSeeder`)

**Job-work vendors.** These are new rows, matched on `code`. The cleaner never wipes vendors, so a rerun updates the same rows.

| Code | Name | Job works |
|---|---|---|
| VEND-JW-LASER | Laser Cut Job Works | laser cutting (RTG-010 OP-010) |
| VEND-JW-ZINC | Zinc Plating Job Works | zinc plating (RTG-010 OP-030) |
| VEND-JW-GALVA | Hot-Dip Galvanising Job Works | galvanising (RTG-012 OP-020) |
| VEND-JW-COAT | Powder Coating Job Works | vendor-made rail (RTG-011) **and** powder coating (RTG-012 OP-040): one vendor, two services |

All four are active, on company default / branch HQ, with `example.com` e-mails and dummy phone numbers.

**Products, ids 137–145.**

| SKU | Type | ₹ cost | What |
|---|---|---|---|
| test-semi-09 | semi, model hybrid | 95 | Laser-cut zinc-plated gusset plate |
| test-semi-10 | semi, model subcontract_complete | 260 | Support rail the vendor makes from its own tube and powder |
| test-semifinal-04 | semi, model hybrid | 2,100 | Galvanised outdoor table frame |
| test-final-03 | finished | 3,700 (sell 6,400) | Outdoor café table 1200×750 |
| test-srv-laser | **service** | 18 | Job work: laser cutting |
| test-srv-zinc | **service** | 12 | Job work: zinc plating |
| test-srv-galva | **service** | 180 | Job work: hot-dip galvanising |
| test-srv-coat | **service** | 110 | Job work: powder coating (our powder) |
| test-srv-rail | **service** | 240 | Job work: rail made from the vendor's material |

The service products have:
- `type` service and `item_type` Service, so the routing dropdown lists them and `isService()` is true
- unit Pcs, SAC heading 9988, GST 18%
- purchase account **1204** (TF-07) and no inventory account
- no stock

**Setting** (tenant `settings`; only these two keys are written, every other key is kept):
- `subcontract_procurement_workflow` = `manual_pr_po`: the button raises a PR
- `subcontract_auto_approval_limit` = 25,000: used only after switching to "Auto-approved PO". A PO above ₹25,000 then falls back to a Draft PO.

### C2. QC plans (`TestProductionQualitySeeder`)
The new plans are appended, so QP-0001 … QP-0026 keep their numbers. There are now 30 families and 32 versions.

| QP | Name | Scope | Checks |
|---|---|---|---|
| 0027 | Subcontract receipt check — general | process | ★Count matches dispatch slip (P/F) · ★Transit damage (P/F) · Vendor remarks (text) |
| 0028 | Zinc plating check — general | process | ★Zinc thickness 8–15 µm · ★Adhesion bend test (P/F) · White rust (P/F, optional) |
| 0029 | Outdoor frame check | product test-semifinal-04 | ★Galvanised coating 45–85 µm · ★Diagonal difference 0–3 mm · ★Coating coverage (P/F) |
| 0030 | Outdoor table final QC | product test-final-03 | ★Top flatness 0–1.5 mm · ★Wobble test (P/F) · ★Outdoor finish (P/F) · Remarks (text) |

An outsourced operation has no section, so it can only carry **process** plans (QR-16). The seeder refuses a section plan on one.

### C3. Routings (`TestProductionEngineeringSeeder`)

**How an outsourced operation is saved:** exactly as `RoutingOperationDTO` saves one from the form.
- `operation_type` outsourcing
- no work centre, section, machine or batch
- setup and run time 0
- vendor, service product, rate, supply type, processing mode, lead time and buffers
- WIP job work saved as `company_supplied`: the form hides that select but still posts it
- cost per unit = rate × 100 ÷ yield % (the calculator's outsourced branch), with resource snapshot type `outsourced`

The seeder refuses an inactive vendor, a non-service or inactive service product, and WIP job work on the first operation (M-02).

**RTG-2026-000010 Gusset Plate: Job-Work Laser Cutting & Zinc Plating** (test-semi-09)

| Op | Resource | Mode | Rate / lead | QC |
|---|---|---|---|---|
| OP-010 Laser cutting — job work | VEND-JW-LASER · test-srv-laser | company_supplied + **bom_raw_materials** (first operation: sheet sent) | ₹18, yield 98% → ₹18.3673 / good pc · lead 2, buffers 1 / 1 | ★QP-0027 + QP-0015 (opt) |
| OP-020 Deburr plates | SEC-3 / batch-1 | in-house | | ★QP-0001 |
| OP-030 Zinc plating — job work | VEND-JW-ZINC · test-srv-zinc | **previous_operation_wip** (deburred plates sent) | ₹12 · lead 3, buffers 0 / 1 | ★QP-0028 + ★QP-0027 |

**RTG-2026-000011 Support Rail: Fully Outsourced (vendor material)** (test-semi-10)

| Op | Resource | Mode | Rate / lead | QC |
|---|---|---|---|---|
| OP-010 Make & coat support rail — job work | VEND-JW-COAT · test-srv-rail | **vendor_supplied** + bom_raw_materials: nothing is sent, nothing is requested | ₹240 · lead 4, buffers 0 / 1 | ★QP-0016 visual |

**RTG-2026-000012 Outdoor Frame: Weld, Job-Work Galvanising & Coating** (test-semifinal-04)

| Op | Resource | Mode | Rate / lead | QC |
|---|---|---|---|---|
| OP-010 Frame welding | SEC-8 / MAH-4 | in-house | | ★QP-0004 |
| OP-020 Hot-dip galvanising — job work | VEND-JW-GALVA · test-srv-galva | previous_operation_wip | ₹180 · lead 5, buffers 1 / 2 | **QC off** |
| OP-030 Powder coating | SEC-8 / MAH-6 | in-house | | ★QP-0006 |
| OP-040 Powder coating — job work (**alternative of OP-030**) | VEND-JW-COAT · test-srv-coat | previous_operation_wip **plus our powder** (company material on the slip) | ₹110 · lead 3, buffers 1 / 1 | ★QP-0027 + QP-0016 (opt) |

### C4. BOMs
- **Previous OP materials (TF-01):** every BOM now stores `operation_input_flags` for every operation of its routing, as the BOM form posts them. The default is the form's: on for an operation with a predecessor, off for the first operation. For the 13 existing BOMs this changes the stored row only; the result is the one the default rule already gave.
- **Vendor-supplied (TF-02):** `supplied_by` = vendor on a row marked so, and on every row of a vendor_supplied operation (the form's default).

| BOM | Routing | Materials |
|---|---|---|
| BOM-TEST-SEMI-09 | 010 | raw-14 0.125 Pcs @010 (company: requested, issued, sent to the laser cutter) · raw-25 0.01 Set @030 **Vendor-supplied** (the plater's own chemicals, on a company-supplied operation) |
| BOM-TEST-SEMI-10 (type **subcontracting**) | 011 | raw-01 0.6 Mtr + raw-07 0.05 Kg @010: both vendor-supplied, because the operation is vendor_supplied |
| BOM-TEST-SEMIFINAL-04 | 012 | semi-01 4 ▸child · semi-09 4 ▸child · semi-10 2 ▸child · raw-12 0.25 Kg @010 · raw-07 0.4 Kg @030 and @040 (the alternative repeats it; on the job work it is sent with the frame) |
| BOM-TEST-FINAL-03 | 008 (final assembly) | semifinal-04 1 ▸child · semifinal-03 1 ▸child · raw-23 1 Set · raw-20 4 Pcs @010/@020 · raw-21 1 + raw-22 1 @040 |

**Product tree:** final-03 → semifinal-04 → semi-01 / semi-09 / semi-10, and final-03 → semifinal-03 (top board, shared with final-01 and final-02).

### C5. Opening stock
| Product | Stock | Effect |
|---|---|---|
| test-semi-09 | 400 | 100 frames' worth |
| test-semi-10 | 200 | 100 frames' worth |
| test-semifinal-04 | 50 | final-03 up to 50 runs from stock |
| test-final-03 | 10 | |
| service products | none | services hold no stock |

An order of a product always runs that product's own routing, so a semi-09 / semi-10 / semifinal-04 order always runs its job works. Stock decides only what a **larger** final-03 order makes inside the order:
- **≤ 50:** frames come from stock.
- **51–150:** frames are made in the order, together with their galvanising job work and looped QC QP-0029.
- **Above 150:** gussets and rails are made too (laser, zinc and rail job works inside one order), and top boards above 150 (shared stock).

### C6. Cases covered
| Case | Where |
|---|---|
| Company raw material sent to a vendor (first operation) | 010/OP-010 |
| WIP job work (previous operation's output sent) | 010/OP-030, 012/OP-020, 012/OP-040 |
| WIP job work **plus** company material on the same slip | 012/OP-040 |
| Vendor's own material, nothing sent (vendor_supplied) | 011/OP-010 |
| A single vendor-supplied line on a company-supplied operation | BOM semi-09 raw-25 @030 |
| Two job works with different vendors in one order (N-06 / H-04) | 010 |
| Job work as the last operation | 010/OP-030, 011/OP-010 |
| Job work in the middle (no FG at an intermediate op, TF-09) | 010/OP-010, 012/OP-020 |
| Outsourced alternative of an in-house operation | 012/OP-040 |
| Fully outsourced product (model subcontract_complete, BOM type subcontracting) | semi-10 |
| One vendor with two different job works | VEND-JW-COAT |
| Yield below 100% on a job work (rate × 100 ÷ 98) | 010/OP-010 |
| External QC: 1 mandatory + 1 optional · 2 mandatory · 1 mandatory · QC off | 010/OP-010 + 012/OP-040 · 010/OP-030 · 011/OP-010 · 012/OP-020 |
| Product QC after job works (WIP final QC / looped) | QP-0029 frame, QP-0030 table |
| Job work inside a larger order (looped sub-assembly) | final-03 > 50 |
| Auto-PO limit fallback (after switching the setting) | galvanising 200 frames = ₹36,000 > ₹25,000 |

### C7. My own decisions (please check)
1. **A new product tree** instead of outsourcing operations in the existing routings, so Parts A–B stay as you reviewed them.
2. **No subcontractor warehouse.** In the reworked flow the dispatch slip moves no stock (TF-03/04) and the slip form has no warehouse field. The earlier follow-up note in the subcontract doc (§4) listed one; it is not needed now.
3. **No inactive vendor or service product.** The routing dropdowns list only active ones. To test M-02, deactivate one in the UI.
4. **Rates, costs, lead times and buffers** are my own figures.
5. **Service product details:** SAC heading 9988 and GST 18% are generic values.
6. **WIP job-work operations are saved `company_supplied`**, like the form.
7. **Explicit TF-01 flags** on every BOM, including the existing 13 (same result as the default rule).
8. **The setting is written on every run:** manual PR → PO, limit ₹25,000. Only those two keys are touched.
9. **The cleaner now also wipes `delivery_challans` and `delivery_challan_items`** (SR-14). These are production tables without the prefix. Order ids restart at 1 after a reset, so old dispatch slips would otherwise attach to the new orders.
10. **The seeder refuses vendor-supplied material on an in-house operation, and on a sub-BOM row.** This is my own guard; the BOM form does not have it.

### C8. Found while doing this (not changed)
1. **Purchase documents from old test orders survive the reset.** PRs, POs, GRNs and bills are Purchase / Accounting data and the cleaner does not touch them. After a reseed the order and operation ids restart at 1, so `SubcontractProcurementOrchestrator::existingDocument()` (which matches on `production_order_operation_id`) can return an **old** PR or PO for a new order's operation. The "Raise PR / PO" button then opens that old document.
   - **Proposal:** the cleaner also deletes purchase documents linked to production orders (subcontract PRs/POs, their items, system GRNs and their bills).
   - **Your call:** it touches Purchase / Accounting data.
2. **The PR list filter "Subcontract"** (`PurchaseRequisitionRepository` line 31) filters on `products.product_type`. That column does not exist, so choosing the filter fails with an SQL error. This is pre-existing Purchase code and was not changed.

### C9. How to check (after `php artisan migrate` and `php artisan db:seed`; session branch HQ)
1. **Settings and masters**
   - Production → Settings: Manual PR → PO, limit ₹25,000.
   - Vendors: the 4 job-work vendors.
   - Routing form: the 5 service products appear in "Subcontract Service Product".
2. **Routings 010–012** are active and costed.
   - The outsourced cards show vendor, mode, lead time and buffers. 010/OP-010 costs ₹18.3673.
   - The routing view lists the process plans on the outsourced operations.
3. **BOMs**
   - semi-09: the raw-25 line is marked Vendor-supplied.
   - semi-10: both lines are vendor's.
   - "Previous OP materials" is on for every operation except the first.
4. **Order of test-semi-09 (e.g. 80)**
   1. Only raw-14 can be requested (raw-25 is the vendor's). Request and issue it.
   2. Make the dispatch slip for OP-010 (sheets) and raise the PR/PO by button: PR → PO, or PO directly in the auto modes.
   3. Receive from the Subcontracting menu: QP-0027 / QP-0015 inspection, then OP-020 deburr with QP-0001.
   4. Make the dispatch slip for OP-030 with the WIP plates, raise its PR/PO and receive: QP-0028 + QP-0027.
   5. Check that each receipt makes a system GRN and moves its PO to Partially Received / Completed.
   6. Check the dashboard: at-vendor, delayed and partial counts.
5. **Order of test-semi-10:** nothing to request and no dispatch slip.
   - Raise the PR/PO.
   - Receive on the MES floor (no slip), then QP-0016.
6. **Order of test-semifinal-04 (e.g. 10)**
   - The children come from stock.
   - Weld with QP-0004.
   - The galvanising slip carries 10 frames. It has **no QC**, so the receipt goes straight to OP-030.
   - The order then needs the WIP final QC QP-0029.
   - Create it again choosing **OP-040** (coating job work): raw-07 is requested and issued, and goes on the slip together with the frames.
7. **Order of test-final-03**
   - ≤ 50: from stock.
   - 60: 10 frames made inside the order, with the galvanising job work and looped QP-0029.
   - 160: gussets and rails made too.
   - Final QC QP-0030.
8. **Auto PO:** switch the setting to Auto-approved PO.
   - A semifinal-04 order of 100 → galvanising PO of ₹18,000, approved directly.
   - An order of 200 → ₹36,000 → Draft PO with the fallback note.

### Files (Part C)
- `database/seeders/TestProductionMasterDataSeeder.php`: vendors, 9 products, setting.
- `database/seeders/TestProductionQualitySeeder.php`: QP-0027 … 0030.
- `database/seeders/TestProductionEngineeringSeeder.php`:
  - outsourced operations (`outsourcedOperation()`)
  - process-plan-only QC on them
  - `bom_type`, `operation_input_flags`, `supplied_by`
  - routings 010–012 and the 4 BOMs
- `database/seeders/TestProductionStockSeeder.php`: services skipped, stock for the new goods.
- `database/seeders/Support/TestProductionLinks.php`: `vendorId()`.
- `database/seeders/CleanBeforeHrmsSeeder.php`: dispatch-slip tables wiped.
- `database/seeders/ProductionE2EDemoSeeder.php`: docblock.
- `tests/Feature/Seeding/TestProductionDataTest.php`: 30 plans, vendor and outsourced-operation checks, services without stock (not run).

`php -l` is clean on all 8 files. I seeded nothing, ran no tests and committed nothing.

### C10. Changed by the subcontracting simplification (2026-11-28, Applied, not run by me)
See `docs/production_ui_rules_review_2026-11-27.md` (SF-1..SF-5). In the test data:
- **Removed:** the 5 `test-srv-*` service products and the subcontract setting (manual PR → PO, ₹25,000). A reseed takes the two old setting keys out of the tenant settings. The 4 `VEND-JW-*` vendors stay.
- **Routings 010–012:** each outsourced operation holds only the vendor and three times in hours (dispatch · processing · receipt): laser 6 · 48 · 6, zinc 4 · 72 · 4, rail 0 · 96 · 24, galvanising 24 · 120 · 36, coating job work 8 · 72 · 8. No rate, so job-work ops cost ₹0 in routing / BOM.
- **BOMs:** a material row is always ours. semi-09 loses the raw-25 vendor row; its OP-030 gets the "Vendor-supplied materials" tick (deburred plates go via Previous OP materials). semi-10 has no rows; its OP-010 has the tick, so it needs no dispatch slip.
- **How to check now:** receive from the receipt page (slip "Receive from vendor", order Subcontract tab "Receive", MES button); each booked receipt writes a GRN without a PO; "Create vendor bill" opens the Service Bill with head "Job Work" (account 5034); the order's Subcontract tab shows the billed amount.

## Part D. Production orders (TestProductionOrderSeeder, 2026-11-28, Applied, run on the local DB)

**User (verbatim):** "update the seeder if needed, and then also create the data for production orders that has completed all these things. So accordingly, QC data will be created. Everything needs to be created, and also keep some production order in multiple stages, like in scheduled, like before scheduling, at subcontracting stages… a few mid stages ready production order needs to be in the seeder, and few complete production orders need to be in the seeder, and all the data related to those needs to be in the seeder."

**How it works:**
- Every step calls the service its screen calls:
  - `createDirect`, then `sendMaterialRequest`
  - store `MaterialRequestService::reserve` / `issue`
  - `release` + `SchedulingService::generateSchedule` + schedule released
  - MES `startOperation` / `completeOperation`
  - `performOperationInspection` (QD)
  - dispatch slip (`prefillLines` → `dispatchProblems` → repository create → `processDispatchActions`)
  - `SubcontractReceiptService::receive`
  - `VendorBillController::storeService` (head Job Work)
  - `recordFinalInspection` when the product has plans, then `receiveFinishedGoods`, then `complete`
- Acting user: admin@example.com, branch HQ.
- One transaction per order. Called last by `ProductionE2EDemoSeeder`.

| Order | Product · qty | Stage |
|---|---|---|
| ORD-2026-000001 | semi-02 · 20 | **Completed.** In-house; QC in parts: OP-020 12 + 8, OP-040 10 + 10 passed; output into WH-WIP-TBL |
| ORD-2026-000002 | semi-09 · 40 | **Completed.** Laser job work (slip, receipt 40, QC, GRN, bill ₹720 + GST) → deburr (QC) → zinc job work (slip, receipts 25 + 15, two QC records, two GRNs, two bills ₹300 / ₹180) |
| ORD-2026-000003 | semi-10 · 20 | **Completed.** Vendor-made, no slip: receipt 20 → QC → GRN → bill ₹4,800 |
| ORD-2026-000004 | final-01 · 10 | Draft |
| ORD-2026-000005 | semi-06 · 30 | Material requested (Locked), nothing issued |
| ORD-2026-000006 | semi-01 · 50 | Issued + released, not scheduled |
| ORD-2026-000007 | semi-04 · 20 | Scheduled + released to the floor, not started |
| ORD-2026-000008 | semi-02 · 30 | Running: OP-010 done, OP-020 logged 20 → **waiting for QC** (open record) |
| ORD-2026-000009 | semi-01 · 40 | OP-010: **30 passed + 10 failed** (two records, NCR); the cut stays open (the next op can't start before it completes) |
| ORD-2026-000010 | semi-09 · 40 | Laser job work **at the vendor** (slip dispatched) |
| ORD-2026-000011 | semi-09 · 24 | **Received from the vendor, waiting for QC** (receipt qc_pending, no GRN yet) |
| ORD-2026-000012 | semi-10 · 10 | Vendor-made, scheduled, **waiting for the receipt** |

**Checked (2026-11-28):**
- 4 job-work bills post ₹6,000 to 5034 Job Work Charges, with 0 posting failures.
- Every order page (all tabs), the MES board, the operator screen, the receipt pages, the slips, analytics, inspections, WIP, schedules, settings and bills load (HTTP 200).
- An over-quantity inspection submit is refused with no change.

**Reset:** `CleanBeforeHrmsSeeder` now also removes the subcontract Purchase documents (GRNs with source `subcontract_receipt`, their bills, bill references and bill journals). Otherwise they would stay orphaned and double the 5034 balance on the next seed.
- The full `php artisan db:seed` (with the reset) was **not run by me**: it was blocked as destructive. I ran only `php artisan db:seed --class=TestProductionOrderSeeder` on the existing test data (additive).

**Limits:**
- Timestamps of logs, receipts and bills are "now" (the services stamp now()); only order and schedule dates are in the past / future.
- Order ids start at 83: dry runs used up auto-increment ids. A full reseed restarts them.
- `notifications` rows from the seeded orders are not removed by the reset.
