# Quality Plan screen: audit (2026-11-26)

Research only. **No code has been changed.** Each gap has an ID (QP-xx) so we can discuss them and record decisions before anything is built.

Screen: Production → Quality Management → Quality Plans → **Configure New Plan** (and Edit, which is a copy of the same form).

---

## 1. What the screen is for

A **Quality Plan** is a reusable checklist template. It says *what to check* on a product, *what range is acceptable* and *in which unit*. Inspections are created from it.

| Field on screen | Saved to | Meaning |
|---|---|---|
| Plan Name | `production_quality_plans.name` | Free text |
| Version | `.version` | Free text, default "1.0" |
| Scope Type | `.type` | `product` / `work_center` / `process` (the DB and validation also allow `product_category`) |
| Target Product / Target Work Center | `.product_id` / `.work_center_id` | Shown depending on the scope type |
| Approval Status | `.status` | Draft / Submitted / "Approved & Active" |
| Checklist Parameters (Name, Type, Min, Max, Unit, Mandatory) | `production_quality_plan_parameters` | One row per check; Type = Numeric / Pass-Fail / Text |

Files: `resources/views/modules/production/quality/plans/create.blade.php`, `edit.blade.php`, `index.blade.php`; `app/Domains/Production/Controllers/QualityPlanController.php`; `Requests/StoreQualityPlanRequest.php`; models `ProductionQualityPlan`, `ProductionQualityPlanParameter`; API twin `Controllers/Api/QualityPlanApiController.php`; tables created in `database/migrations/2026_07_07_000003_create_quality_exception_tables.php`.

### Where a plan is used afterwards

1. **Desktop Quality Inspection** (`QualityInspectionService::createInspection`, about line 23). The user picks a plan. Every parameter is copied as a blank result row (pre-set to `passed`). `recordResults` (about line 150) then checks the entered numbers against min/max and gives pass/fail. On approval, a failure raises an NCR.
2. **Operator screen QC form** (`mes/operator/operation-execution.blade.php` 701–816). A plan is auto-selected and its parameters are shown as input fields. The operator enters Accepted / Rejected quantities.
3. **FG receipt into quarantine** (`ProductionExecutionService` 913–945). A pending inspection is raised automatically against a plan.
4. **Subcontract GRN** (`SubcontractReceiptOrchestrator` 73–83). Whether QC is required can be decided by "does any plan exist for this product".

---

## 2. Gaps

### A. Data safety (most serious)

**QP-01: Editing a plan erases the recorded results of every past inspection that used it.**
`QualityPlanController::update` line 115 hard-deletes all parameters and creates new ones. The FK `pqir_qpp_fk` on `production_quality_inspection_results.quality_plan_parameter_id` is `cascadeOnDelete` (migration line 107). So the database deletes every inspection result row linked to those parameters. Fixing a typo in a plan wipes all measured values of past inspections.
Inspections that are still open (draft/submitted) also lose their result rows. Entering results then fails, because `recordResults` looks the row up with `firstOrFail` against the new parameter IDs. The API has the same code (`QualityPlanApiController` line 149).

**QP-02: Deleting a plan does the same.**
`destroy` line 137 soft-deletes the plan but hard-deletes its parameters, so the same cascade runs (the API does the same at lines 182–183). Nothing checks whether the plan was already used. The menu item says "Delete permanent" even though the plan row itself is only soft-deleted.

**QP-03: There is no real versioning.**
"Version" is just a text box. An approved plan can be edited in place and stays "approved" with no re-approval. The old version is not kept. An inspection points to the plan ID, so its history shows the *current* spec, not the spec the goods were actually checked against.

### B. Operator readings are thrown away

**QP-04: The checklist on the operator screen is never saved.**
The form sends `parameter_values[<param id>]` (operation-execution.blade 783–811). `MesController::recordQualityInspection` (330–375) neither validates nor forwards it. `processShopfloorInspection` creates no result rows. A search for `parameter_values` in `app/` finds nothing.
So measured GSM or width values are lost, min/max is never checked on the shop floor, and pass/fail comes only from the Accepted/Rejected quantities the operator types. Pass/Fail parameters are also pre-ticked "pass".

**QP-05: "Mandatory" is not enforced.**
It only sets an HTML `required` attribute on the operator screen. `recordResults` does not check it. Result rows start as `passed` (`createInspection` line 55), so a mandatory parameter that was never entered still counts as passed.

### C. Which plan is picked (scope logic)

**QP-06: Approval status has no effect anywhere.**
Every lookup ignores status: `QualityInspectionService` 91–97 and 369–372, `ProductionExecutionService` 915–918, `SubcontractReceiptOrchestrator` 78. The MES lists include Draft plans on purpose (`MesController` 179 and 941). A Draft plan is used exactly like an approved one.

**QP-07: The fallback is "the first plan of the tenant".**
When no plan matches the product, the code takes *any* plan (`QualityInspectionService` 97 and 372, `ProductionExecutionService` 918, operator screen step 4 at line 731). Example: a 3-ply-mask plan gets used for a 20 GSM roll.

**QP-08: The Work Center and Process scopes do nothing.**
No automatic lookup reads `work_center_id`, and "Process General" has no field at all. These plans are used only when a user picks them by hand.

**QP-09: A plan is not linked to a routing operation.**
The routing operation and order operation only carry a yes/no `quality_required`. Nothing says which plan applies at Op 10 (extrusion) versus Op 20 (slitting). The operator screen guesses: source product → type `in_process` → product → first plan.

**QP-10: A plan has no stage.**
Inspections have a stage (incoming / in_process / final), but plans don't. The operator screen looks for a plan of type `in_process` (line 715), which this form cannot create.

**QP-11: Several active plans for the same product are allowed.**
There is no uniqueness rule, and the lookups have no ordering, so which plan wins is arbitrary.

**QP-12: The Category scope is half-built.**
Validation accepts `product_category`, the index filter offers it, and the DB has `product_category_id`. But the form has no Category option or field, and `product_category_id` is not in the validation rules, so it can never be saved.

**QP-13: Subcontract QC is switched on by any plan.**
When the operation's `quality_required` is null, QC becomes required if *any* plan exists for the product, even a Draft one (`SubcontractReceiptOrchestrator` 74–83).

### D. Auto-created plans

**QP-14: Plans are created silently and invalidly in three places.**
`QualityInspectionService` 100–110 and 375–385, and `ProductionExecutionService` 920–930. They have **no parameters**, a type the form doesn't know (`in_process`, `final`), and a status the form doesn't know (`active`). They also set `code`, which is neither a column nor fillable, so it is dropped.
When one of these is opened in Edit, the Scope and Status dropdowns show the wrong value, and saving quietly changes them to Product / Draft.

### E. Validation

**QP-15:** "Product Specific" can be saved with no product, and "Work Center Specific" with no work center (both `nullable`, `StoreQualityPlanRequest` lines 20–21).

**QP-16:** `exists:products,id` and `exists:production_work_centers,id` are not limited to the current tenant. A tampered POST could link another tenant's product.

**QP-17:** Nothing checks that Min ≤ Max. A Numeric parameter with neither Min nor Max always passes. There is no **target/nominal value** and no **± % tolerance**, only absolute min/max. The Vishal NonWoven requirement is a 5–10% tolerance, for example GSM 20 ± 5%.

**QP-18: Min/max are stored as `decimal(10,2)`.**
A value like 0.125 mm is saved as 0.13. This breaks the "no rounding, show the truth" rule and is too coarse for thickness or weight tolerances.

**QP-19:** Duplicate parameter names are allowed, and there is no explicit sort order.

**QP-20:** Unit is free text, not linked to the UoM master.

**QP-21:** The Target Product dropdown lists every product, including raw materials, consumables and services, with no filter to items you actually make or receive.

**QP-22: Sampling exists but is not on the screen.**
`sampling_type` and `sampling_value` exist in the DB, the validation and the API, but not on the form. They always default to fixed / 1. There is no way to say "every roll", "1 in 10 rolls", "% of lot" or AQL.

### F. Approval workflow

**QP-23: Anyone can approve their own plan.**
The creator can pick "Approved & Active" straight away. There is no second approver, and the `esignature` column is never filled. "Submitted" leads nowhere (no Approve/Reject action). "Archived" cannot be set from the form. Editing an approved plan does not send it back for approval.

**QP-24: Only one permission covers everything.**
Every action is behind `production.quality.manage`, checked directly in the controller with `hasProductionPermission`. There is no separate approve permission, and nothing goes through a policy or `AccessService` as CLAUDE.md asks.

### G. Screen / UI

**QP-25:** Alpine.js is loaded twice: by the layout (`duralux.blade.php` line 84) and again by the page (create 137, edit 138), both with a floating `3.x.x` version.

**QP-26:** Wrong translation keys are reused: the "Type" column uses `production.routing_type` and "Draft" uses `production.draft_schedules`. English reads fine, but other languages may show the wrong words.

**QP-27:** The index lists only name, version, scope, target and status. There is no parameter count, no approver, and no "used in N inspections". There is no read-only view and no "Copy / New version" action.

**QP-28:** A Text parameter always counts as passed. That is fine, but the screen doesn't say so.

**QP-29:** Create and Edit are two copies of the same form (the M-34 pattern of one shared partial is not applied here).

---

## 3. Fit for the current client (Vishal NonWoven, roll-based)

Checks this client would typically need:

- GSM (target ± %)
- Roll width
- Roll length
- Roll weight
- Thickness
- Tensile MD / CD
- Elongation
- Colour shade against a standard (pass/fail)
- Visual defects: holes, spots, lumps (pass/fail plus a defect count)
- Core size

What the current model can hold: names plus absolute min/max. What's missing: target ± %, per-roll sampling, values with more than 2 decimals, stage, and a link to the operation (extrusion versus slitting versus bag making). And because of QP-04, none of these readings would be saved from the shop floor today.

---

## 4. Decisions needed from you (nothing is built until you say so)

1. **History safety (QP-01/02/03):** should an approved plan become read-only, with changes made as "New version" (old version kept and still linked to its inspections)? Or should editing in place stay allowed but keep the old parameters?
2. **Approval (QP-23/24):** should approval need a different user or permission from the creator? Should Submitted get Approve/Reject buttons?
3. **Scope (QP-08/09/10/12):** should a plan attach to a *routing operation* (and/or a stage) so the right plan is picked automatically? Keep or drop the Work Center / Process / Category scopes?
4. **Picking rules (QP-06/07/11):** should only Approved plans be used? Should the "first plan of the tenant" fallback be removed (ask the user instead)? Should there be one active plan per product + operation/stage?
5. **Operator readings (QP-04/05):** save the operator's parameter values as result rows, check min/max and Mandatory, and derive pass/fail from them?
6. **Tolerance (QP-17/18):** add Target + ± % (alongside or instead of Min/Max), and store more decimals?
7. **Sampling (QP-22):** show sampling on the screen? Which options: every unit/roll, 1 in N, % of lot, AQL?
8. **Auto-created plans (QP-14):** stop creating empty plans automatically?
