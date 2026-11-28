# Quality Control rework: rules, plan and status (2026-11-26)

## Status per rule

Status words: **Agreed** (decided, not coded) → **Applied** (code written, not tested by me) → **Verified** (written only by the user).

| Rule | Subject | Status |
|---|---|---|
| QR-01 | Scope dropdown switches the second field | Applied (2026-11-26, not tested by me) |
| QR-02 | Scopes = Product / Section / Process General; Work Center scope removed | Applied (2026-11-26, not tested by me) |
| QR-03 | Plans for traded and produced products (blocking only for produced) | Applied (2026-11-26, not tested by me) |
| QR-04 | Produced product with a plan cannot go into a warehouse before QC; quarantine removed | Applied (2026-11-26, not tested by me) |
| QR-05 / QR-17 | Looped semi: QC on the last maker operation of that order only | Applied (2026-11-26, not tested by me) |
| QR-06 | Parameter Type row: Min/Max/Unit only for Numeric; Pass/Fail gets Basis / Reason | Applied (2026-11-26, not tested by me) |
| QR-07 | Status locked: Draft → Pending → Approved, Reject → Draft | Applied (2026-11-26, not tested by me) |
| QR-08 | Automatic versions; old version Historical when the new one is approved | Applied (2026-11-26, not tested by me) |
| QR-09 | Routing warning when a linked plan has a newer approved version | Applied (2026-11-26, not tested by me) |
| QR-10 | Delete only in Draft (soft delete) | Applied (2026-11-26, not tested by me) |
| QR-11 | Name + Version unique per tenant (deleted drafts ignored) | Applied (2026-11-26, not tested by me) |
| QR-12 | Quality Plan View screen | Applied (2026-11-26, not tested by me) |
| QR-13 | Row action menu by status | Applied (2026-11-26, not tested by me) |
| QR-14 | Only Approved plans used downstream; no auto-created empty plans | Applied (2026-11-26, not tested by me) |
| QR-15 / QR-16 | Routing QC gate + mapping table + modal with eye icon | Applied (2026-11-26, not tested by me) |
| QR-18 | Finished product QC in WIP blocks conversion | Applied (2026-11-26, not tested by me) |

## Implementation notes (2026-11-26)

Run `php artisan migrate` first. This runs two migrations: `2026_11_26_100000_quality_plan_lifecycle_and_mapping` and `2026_11_26_100100_normalize_quality_plans_data`.

**Where things live**
- Plan lifecycle: `QualityPlanService`.
- Permissions: `QualityPlanPolicy`, which replaces `QualityManagementPolicy` for plans only.
- Screens: `quality/plans/{index,create,edit,show}` plus `quality/plans/partials/form`.
- Routing link:
  - `routing/partials/qc-plan-modal.blade.php`
  - `ValidatesRoutingOperations::validateRoutingOperationQualityPlans`
  - `RoutingService::syncOperationQualityPlans`
  - `RoutingController::qcFormData`
- Order snapshot and the looped semi: `OrderOperationQualityService`, called from `ProductionOrderService` wherever an order operation is created, and from `syncPlannedTree` for the last operation of each sub-assembly set.
- Checklist save and evaluation: `QualityInspectionService::evaluateChecklist` / `storeChecklistResults`.
- Finished-product gate: `FinalQualityGateService` (used by `receiveFinishedGoods`, the WIP conversions and the WIP screen), and `QualityInspectionService::recordFinalInspection` through the route `production.wip.final-qc`.

**Where the build differs from the plan text**
1. The routing modal gets its plan list embedded in the page (`$qcPlanCatalog`) instead of a JSON endpoint, so there are no extra requests. The list holds the Approved Section and Process General plans, plus any plan already linked in that routing.
2. When a routing links a version that is no longer approved, a new order takes that plan family's approved version. The routing itself is not changed; it only shows the warning.
3. Orders created before this change have no plan snapshot. Their routing plans are copied the first time the QC form for that operation is opened.
4. Rejected units in the WIP final QC raise an NCR and stay in WIP until they are handled through rework or scrap. They can never be received. "Waiting for final QC" = WIP ready − (final QC inspected − received).
5. A QC operation with no plan attached records accepted and rejected quantities only. The inspection then has no plan, because `quality_plan_id` is now nullable.

**Where to check in the UI**
1. **Quality Plans → Configure New Plan:**
   - Scope Product / Section / Process General switches the target field.
   - Numeric rows show Min/Max/Unit; Pass/Fail rows show Basis / Reason; Text rows show neither.
   - Version is 1 and the status is Draft.
2. **Plan View:**
   - Submit for approval, then Approve, or Reject with a reason (the plan goes back to Draft).
   - Mark historical.
   - The approval history fills in.
3. **Edit an Approved plan:** v2 is created as a Draft. Approving v2 turns v1 Historical. The version history lists both.
4. **Delete a Draft:** it disappears from the list (soft delete).
5. **Routing create / edit:**
   - Ticking QC opens the modal: filter, multi-select, Add, Mandatory tick, Preview, Remove.
   - The eye icon reopens the modal; unticking discards the choices.
   - Saving with QC ticked and no plan is refused.
   - The routing View screen lists the plans next to the QC badge, with the newer-version warning.
6. **Order with a semi made inside it:**
   - The last operation of that semi asks for QC with the semi's product plan.
   - Completing it shows the "Semi … has been produced" warning.
   - The next operation waits until the semi's QC is accepted.
7. **Operator screen / MES dashboard QC:**
   - Only the operation's plans are shown, and every value is saved.
   - Leaving a mandatory check empty is refused.
   - A failed mandatory check with 0 rejected needs the confirmation tick.
8. **WIP list, order row:**
   - "Final QC (N pending)" opens the checklist.
   - "Receive completed FG" offers only the accepted quantity, and quarantine is gone.

## Context

The Quality Plan screen was audited (`docs/production_quality_plan_audit_2026-11-26.md`, QP-01..29). The worst findings:
- Editing or deleting a plan erases past inspection results.
- Operator checklist values are never saved.
- Plan status has no effect, and any plan can be picked for any product.
- The scope dropdown is broken.

Over messages QM-01..QM-10 (log below) the user dictated:
- a proper plan master (scope, approval flow, automatic versions, delete rules, View screen)
- a routing-level link from operations to plans
- two QC gates: the finished product in WIP, and a semi made inside the order

The user asked to build everything **together** after the routing part was agreed. Rules QR-01..QR-18 below are the spec. Status words: after coding = **Applied**; only the user writes **Verified**. No tests are run by me (memory rule); `php -l` and `view:clear` only.

## Implementation steps

### Step 0: Docs
- Copy this rule log to `docs/production_quality_rules.md`: verbatim QM messages, QR rules, and a status column per rule.

### Step 1: Database (new migrations, dated `2026_11_26_*`)
1. `production_quality_plans`, new columns:
   - `plan_number` (QP-0001, shared by all versions, from `App\Support\TenantSequence`)
   - `root_plan_id` (version family)
   - `source_plan_id`
   - `section_id` (→ `production_work_center_sections`)
   - `submitted_by` / `submitted_at`
   - `historical_at`

   Statuses become `draft | pending_approval | approved | historical`. `version` holds whole numbers ("1", "2"…). Index on `(tenant_id, name, version)`. The unique check is app-level (it ignores soft-deleted rows; QR-11).
2. `production_quality_plan_parameters`:
   - add `basis` (text, Pass/Fail acceptance rule) and `sort_order`
   - add `deleted_at` (parameters are never hard-deleted again)
   - widen `min_value` / `max_value` to `decimal(18,4)` (no-rounding rule; QP-18)
3. New `production_quality_plan_approvals`: audit log like `production_routing_approvals` (`tenant_id`, `quality_plan_id`, `user_id`, `action`, `comments`, `created_at`).
4. New `production_routing_operation_quality_plans`: `tenant_id`, `routing_operation_id`, `quality_plan_id`, `is_mandatory`, `sort_order` (QR-15).
5. New `production_order_operation_quality_plans`: `tenant_id`, `production_order_operation_id`, `quality_plan_id`, `is_mandatory`, `source` (`routing` | `sub_assembly`) (QR-17).
6. New `production_quality_inspection_plans`: `quality_inspection_id`, `quality_plan_id`, `is_mandatory`. One inspection can cover several plans; the results rows already link parameter → plan.
7. Data clean-up (dummy data):
   - delete `type = work_center` plans (QR-02)
   - map `submitted` → `pending_approval`, `active` → `approved`, `archived` → `historical`
   - map `in_process` / `final` types → `product` when there is a product, otherwise `process`
   - set version "1" and backfill `plan_number` / `root_plan_id`; rename duplicate name + version pairs by adding ` (#id)`

### Step 2: Models
- `ProductionQualityPlan`:
  - status constants and helpers `isDraft`, `isPendingApproval`, `isApproved`, `isHistorical`, `isEditable`
  - relations `section()`, `approvals()`, `versions()` (same `root_plan_id`), `latestApprovedVersion()`
  - scope `approved()`
  - pattern: `Routing` model lines 23–35 and 176–205
- New models:
  - `ProductionQualityPlanApproval` (copy of `RoutingApproval`)
  - `RoutingOperationQualityPlan`
  - `ProductionOrderOperationQualityPlan`
- Relations: `RoutingOperation::qualityPlans()`, `ProductionOrderOperation::qualityPlans()`.
- `ProductionQualityPlanParameter`: add `SoftDeletes`, add `basis`.

### Step 3: Plan lifecycle service (new `QualityPlanService`, Production domain)
Mirrors `RoutingService` submit / approve / reject / retire (lines 124–300) and `checkRoutingConflicts` (519).
- `create`: always Draft, version 1, new `plan_number`. Logs "Created".
- `update`:
  - Draft → edited in place.
  - Approved → creates vN+1 as a new Draft (header and parameters copied, `source_plan_id` set), logs "Version created". Refused if an open newer Draft or Pending version exists (A1).
  - Pending or Historical → refused.
- `submit` (Draft → Pending), `approve` (Pending → Approved; in the same transaction any other Approved version of the same family → Historical, `historical_at` set; QR-08), `reject` (→ Draft, comment required), `markHistorical` (Approved → Historical).
- `delete`: Draft only, soft delete of the plan and its parameters (QR-10).
- Validation in `StoreQualityPlanRequest`:
  - type `in:product,section,process`
  - `product_id` required for product scope; `section_id` required for section scope; both checked against the current tenant (QP-15/16)
  - status and version are no longer accepted
  - numeric rows: `max >= min`; non-numeric rows: min/max/unit dropped server-side; Pass/Fail: `basis` (optional text)
- `QualityPlanApiController` store / update / destroy go through the same service. New API routes added for submit / approve / reject / historical.
- Policy: new methods on `QualityManagementPolicy` (view / update / delete / submit / approve / reject / retire per plan), using `production.quality.manage` and `production.quality.approve`.

### Step 4: Plan screens
- **Create and edit forms:** one shared partial, used by `create.blade.php` and `edit.blade.php`.
  - Scope is Product / Section / Process General. The second field switches correctly (QR-01): listen to select2's jQuery `change` event and push the value into Alpine, or render that select without select2.
  - Remove the second Alpine `<script>` (page line 137 / 138).
  - Version is shown read-only; the status dropdown is removed.
  - The product list includes traded and produced goods and excludes services (`item_type` Service).
  - Parameter row: Type Numeric shows Min/Max/Unit; Pass/Fail hides and clears them and shows "Basis / Reason"; Text hides and clears them (QR-06).
- **New View screen** `quality/plans/show.blade.php` + route `production.quality-plans.show`, exactly as the approved mockup (QR-12):
  - banner with workflow buttons
  - header and scope cards
  - checklist
  - version history
  - where used (routing operations with a newer-version flag, inspection count)
  - approval history
  - reject modal
- **Index:** the name opens View; the row menu depends on status (QR-13); "Delete permanent" only for Draft; filters cover the new statuses and scopes.
- **Approval Center:** pending plans listed in `app/Services/Approval/ApprovalCenterService.php` next to the BOM / Routing blocks (lines 99–122), gated by `production.quality.approve`.

### Step 5: Routing link (QR-15, QR-16, QR-09)
- `routing/create.blade.php` and `edit.blade.php`, QC cell (line 586 / 602):
  - Ticking opens the modal; an eye icon appears.
  - Modal table: Approved plans of the row's `section_id` plus Process General, each with Pick and Mandatory ticks.
  - The state is kept in `operation.quality_plans` (Alpine) and posted as `operations[i][quality_plans][k][id|is_mandatory]`.
  - Unticking clears it. If the row's section changes, picks from the old section are cleared.
- **Modal layout, version 2 (QM-11: the user asked for a dropdown instead of the full list; mockup v2 shown 2026-11-26):**
  - Title "Quality checks for Op N · <name>", with the section under it.
  - **Add plans:**
    - A **filter dropdown** with 3 options, **Section plans / Process general / Both** (QM-12; replaces the two buttons), next to a **searchable multi-select dropdown** (select2 multiple) that lists only Approved plans for that filter: the row's section, Process General, or both. When "Both" is chosen, each option shows its scope.
    - Plans already added are not listed again. Changing the filter clears unsaved choices in the dropdown.
    - **Add** moves the chosen plans to the list below. Clicking Add with nothing chosen shows "Choose at least one plan in the dropdown first".
  - **Selected plans** table: Plan (name, QP number, version, check count), Scope, **Mandatory** tick (default Yes after Add), Actions (Preview, Remove).
  - Footer: "N plans · M mandatory", Cancel, Save choices. Saving with an empty list shows "Add at least one plan, or untick QC".
  - Cancel on a fresh tick with nothing saved unticks QC.
  - **No new column on the routing form** (QM-12). The operations table only gets the modal and the eye icon next to the existing QC tick. The eye reopens the modal to review or change.
  - Data for the dropdown: a small JSON endpoint (Approved plans by section / process, with their parameters for the preview), loaded when the modal opens.
  - **Preview (decided, QM-12):** the eye button on a selected plan expands that row **inside the modal** to show the plan's checks read-only (parameter, type, limits / basis), with a small "Open full plan in a new tab" link. No redirect and no second popup.
- `Requests/Concerns/ValidatesRoutingOperations.php` (line 72):
  - `operations.*.quality_plans` is required when `quality_required` is ticked (min 1)
  - each plan must exist, belong to the tenant, be Approved, and be section scope with the same section, or process scope
- `RoutingOperationDTO` carries `quality_plans`. `RoutingService` create / update syncs the mapping table and clears it when the gate is off. `buildCopyPayload` (line 390) copies it.
- **Routing View screen:** plans listed next to the QC badge (`show.blade.php` line 546). Warning "QC plan X vN has a newer approved version vM" per operation, plus a banner line (warning only, never blocks).

### Step 6: Order snapshot + looped semi gate (QR-17, QR-05)
- `ProductionOrderService` lines 1780 and 2266: when an order operation is created from a routing operation, copy its mapping rows into `production_order_operation_quality_plans` (source `routing`).
- **Sub-assembly sets:** for the LAST maker operation of each set (same "own operations of the set" rule as `OperationMaterialService::madeFromSet`, line 343), if the semi has Approved product-scope plans:
  - set `quality_required = true` on that order operation only
  - add those plans with source `sub_assembly`, mandatory (A4)
  - re-applied after `SubAssemblySetService::merge` / `split` rebuild the operations
- **The block mostly exists already:** a QC-required operation completes only when the QC-accepted quantity reaches its target (`QualityInspectionService::processShopfloorInspection` ~line 398 adds the accepted quantity to `quantity_produced`; `MesExecutionService::qualityGateIsPendingOrFailed`, line 1131). The parent waits until all makers are completed (UR-07), so the semi can't be used before QC.
- **Added:** when completing such a maker operation with pending QC, the message reads "Semi <SKU> has been produced — perform its quality check before it can be used." The Readiness / operation flow label shows "Waiting for QC of semi".

### Step 7: Operator QC records the checklist (QR-14, QR-16, QP-04/05)
- Operator screen (`mes/operator/operation-execution.blade.php` 701–816):
  - The plan dropdown is replaced by the operation's snapshot plans, one checklist block per plan, with mandatory plans and mandatory parameters marked.
  - The hard-coded fallback checklist is removed.
  - Field names become `parameter_values[<plan_id>][<param_id>]`.
- `MesController::recordQualityInspection` (line 330): validate `parameter_values` (mandatory plan + mandatory parameter required) and pass them on.
- `processShopfloorInspection`: one inspection per submission, `production_quality_inspection_plans` rows, one result row per parameter evaluated like `recordResults` (min/max, pass/fail). The accepted / rejected split stays the operator's input. If a mandatory parameter fails while 0 units are rejected, the operator must confirm (A3).
- **QR-14:** every plan pick and lookup uses `approved()` only:
  - `QualityInspectionController` line 37
  - `MesController` 179 / 941
  - `QualityInspectionService` 91–97, 369–372
  - `ProductionExecutionService` 915–918
  - `SubcontractReceiptOrchestrator` 78
- The automatic creation of empty plans (`QualityInspectionService` 100–110, 375–385; `ProductionExecutionService` 920–930) is removed.

### Step 8: Finished-product gate in WIP (QR-04, QR-18)
- New "Final QC" action on the WIP screen (`wip/show.blade.php` + `WipController`) for orders whose product has Approved product-scope plans:
  - the same checklist + accepted / rejected form
  - creates an inspection with stage `final` (via `QualityInspectionService`)
- `ProductionWipService::convertWipToFinishedGoods` (line 1292) / `convertOrderWipToFinishedGoods` (1346) and `ProductionExecutionService::receiveFinishedGoods` (790):
  - refuse more than (final QC accepted − already received), with the message "Final QC pending for N units of <product>"
  - the WIP screen shows the same warning banner
- The quarantine path is removed: the `qualityStatus` quarantine branch (814–833, 913–945) and the option in the convert / receive forms.

### Step 9: Small fixes found in the audit
- Wrong translation keys on the plan forms (QP-26); new `lang/en/production.php` keys for the new labels.

## Assumptions (agreed or mine, visible for review)
- **A1 (agreed):** only one open newer version per plan.
- **A2 (agreed):** Text rows have no extra field.
- **A3 (mine):** a mandatory parameter failing with 0 rejected units needs the operator's confirmation, not a hard block.
- **A4 (replaced by QM-14):** a product can have only one plan in use, so there is only ever one approved product plan per product.

## Critical files
- `app/Domains/Production/Controllers/QualityPlanController.php`
- `app/Domains/Production/Controllers/Api/QualityPlanApiController.php`
- `app/Domains/Production/Requests/StoreQualityPlanRequest.php`
- `app/Domains/Production/Models/ProductionQualityPlan.php`, `ProductionQualityPlanParameter.php`, `RoutingOperation.php`, `ProductionOrderOperation.php`
- new `app/Domains/Production/Services/QualityPlanService.php`
- `app/Domains/Production/Policies/QualityManagementPolicy.php`
- `resources/views/modules/production/quality/plans/{index,create,edit,show}.blade.php`
- `resources/views/modules/production/routing/{create,edit,show}.blade.php`
- `app/Domains/Production/Requests/Concerns/ValidatesRoutingOperations.php`, `DTO/RoutingOperationDTO.php`, `Services/RoutingService.php`
- `app/Domains/Production/Services/ProductionOrderService.php`, `SubAssemblySetService.php`, `OperationMaterialService.php`, `MesExecutionService.php`
- `app/Domains/Production/Services/QualityInspectionService.php`, `ProductionExecutionService.php`, `ProductionWipService.php`, `SubcontractReceiptOrchestrator.php`
- `app/Domains/Production/Controllers/MesController.php`, `QualityInspectionController.php`, `WipController.php`
- `resources/views/modules/production/mes/operator/operation-execution.blade.php`, `wip/show.blade.php`
- `app/Services/Approval/ApprovalCenterService.php`
- `app/Domains/Production/Routes/web.php`, `Routes/api.php`

## Verification
- After coding: `php -l` on every changed PHP file and `php artisan view:clear`. The user runs `php artisan migrate`.
- No PHPUnit runs (memory rule). Existing tests that use the old plan behaviour (`QualityPlanAndSkillCrudTest`, `QualityManagementTest`, `SimplifiedQualityControlWorkflowTest`, `ProductionApiRemediationTest`) may need updating; they will be listed for the user.
- Hand-over list of where to check in the UI:
  1. Plan create: scope switch, row types
  2. Draft → Submit → Approve, Reject
  3. Edit an approved plan → v2 Draft → approve → v1 Historical
  4. Delete a draft
  5. View screen
  6. Routing QC modal and eye icon; mandatory tick
  7. Order with a looped semi → maker operation needs QC → parent waits
  8. Operator QC saves the checklist
  9. WIP final QC blocks conversion until accepted

---

# Decision log (verbatim messages and rules)

Background audit: `docs/production_quality_plan_audit_2026-11-26.md` (QP-01..29).

## QM-01: user's message (verbatim)

> Let me first tell you a few UI changes that I noticed in the front end. Firstly, Changing the scope type doesn't change the drop down for product. It still shows the drop down for product. If scope type is changed to work center, then the drop down should show the list of work centers. If the scope type is being changed to a section, then the drop down should show section. And if a drop down is being changed to process general, it will do, then there will be no other drop down additional for anything. It will be for process, so it can be used by any process.
> While just we will remove the work center level. as that will create too much of the ambiguity and will cause issues with the section level quality checks. So we will leave it.
> Quality checks can be created for both type of products, traded one and produced one. For produced one, if a produced product has a quality check, then that item cannot be accepted or transferred to a warehouse before a particular quality check has been performed on that item. And also during a Looped production. Sometimes a semi product will be produced in the same production order if the semi goods are not available in the stock. So in those cases also those semis cannot be used in the production before the quality check has been done. So a warning will be shown before completing the operation that this semi has been produced. Please perform a quality check on this semi produced products. So you can say either a operation will be added or you can say just a button will be there to produce a quality check on those semis. Did you understand?

## My understanding (rules QR-xx)

### QR-01: The scope dropdown must switch the second field
- **Bug:** changing Scope Type leaves the Product dropdown on screen.
- **Root cause:** `odoo-form-ui` turns every select into a select2 widget (`components/ui/odoo-form-ui.blade.php` lines 605–615). Select2 fires only jQuery `change` events. Alpine's `x-model="scopeType"` (plans/create.blade.php line 33) listens for native events, so `scopeType` stays `product`. Alpine is also loaded twice (layout line 84 plus the page at line 137).
- **Wanted:**
  - Product → Product dropdown.
  - Section → Section dropdown.
  - Process General → no second dropdown (the plan can be used by any process).

### QR-02: Scope options become Product / Section / Process General
- **Work Center scope is removed:** it would make it ambiguous which plan applies when a section-level plan also exists.
- **"Section"** = `WorkCenterSection` (`production_work_center_sections`, belongs to a work center). Routing operations already carry `section_id` (`RoutingOperation` lines 42 and 151), so a section plan can apply to every operation run in that section.
- **To be added:** `section_id` on `production_quality_plans`. `work_center_id` stays in the DB but is not offered on screen.
- **To confirm:** how existing `work_center` plans are treated.

### QR-03: Plans can be made for traded AND produced products
- The Target Product list must contain both kinds.
- **To confirm:** where a traded product's QC happens. Purchase GRN has no QC today, and that is the Purchase module.

### QR-04: A produced product with a QC plan cannot go into a warehouse before QC
- Finished-goods receipt (`ProductionExecutionService::receiveFinishedGoods`, line 790) must be blocked until that quantity has passed its QC.
- **To confirm:** what happens to the existing "quarantine" path (receive into the Quarantine warehouse and inspect there).

### QR-05: A semi made inside the same order cannot be used before its QC
- This is the "looped production" case: a sub-assembly made in the same order because there wasn't enough stock (UR-10 sets).
- **When the maker operation completes:** show a warning: "this semi has been produced, please perform a quality check on it".
- **The consuming operation** cannot use the semi until its QC is done.
- **The user leaves the shape to me:** either an extra QC operation, or just a QC button.
- **My recommendation:** a button. Reuse the existing per-operation QC mechanism (`quality_required` + pending-QC quantity + `QualityInspectionService::processShopfloorInspection`) on the LAST maker operation of the semi's set. The set's "made" quantity (`OperationMaterialService::madeFromSet`, line 343) then counts only QC-accepted units. No new operation is needed, so routing, scheduling and step numbers stay unchanged.

## QM-02: user's answers to my questions (2026-11-26)

| Question | Answer | Effect on the rules |
|---|---|---|
| Traded products: where does QC happen? | **"Later, produced only"** | QR-03: traded-product plans can be created now. Blocking is built only for produced products this round. Purchase GRN is not touched. |
| What happens to the FG "Quarantine" receipt option? | **"Remove it"** | QR-04: no quarantine route. QC must pass before receipt, and only the accepted quantity can be received into any warehouse. The auto quarantine inspection (`ProductionExecutionService` 913–945) goes away. |
| Semi made in the order without QC: block or warn? | **"Block next operation"** | QR-05: a warning appears when the maker operation is completed. The operation that uses the semi cannot start or log output until the semi's QC is done. |
| Existing "Work Center Specific" plans? | **"delete them, we currently do not care about the data as it is dumy data only"** | QR-02: delete all `type = work_center` plans. Note: inspections linked to them are deleted too (FK `pqi_qp_fk` is cascadeOnDelete), which is acceptable because the data is dummy. |

## QM-03: user's message (verbatim)

> One fix will be there for all the operations being performed in that section won't get that QC by default. It would have to be turned on while creating the routing. So we will update the routing form a bit. I will tell you about it later on. As sometimes a single section could have multiple uh, inspection plans. Check this part in the backend also and tell me, is there restrictions for a product to have only single inspection plan, or can a product have multiple inspection plans? Same with sections.

**Correction to QR-02:** a Section plan does NOT apply automatically to every operation in that section. QC is switched on per operation in the routing form. The routing form changes will come later from the user.
A section can have several inspection plans.

**Backend check (answer given):**
- **Product:** no restriction. A product can have any number of plans.
  - There is no unique index (migration `2026_07_07_000003`, lines 12–35).
  - There is no unique rule in `StoreQualityPlanRequest` or the API.
  - The same product, name and version can even be repeated, in any status.
- **Section:** there is no section scope yet (no `section_id` on plans), so there is no restriction either.
- **The real limit:** one inspection carries ONE `quality_plan_id`. With several plans, the system picks one:
  - The automatic lookups take an arbitrary match (`value('id')` / `first()`, no order, status ignored).
  - The operator screen pre-selects the first match, and the operator can switch to another.
  - The desktop inspection lets the user choose from every plan of the tenant.
- There is no "run all applicable plans" behaviour.

## QM-04: user's message (verbatim)

> Nice, you reminded me of another thing. Uh, Changing the type doesn't change the values like the min max and all those fields are not being changed. I check this issue also. And another thing, we will lock the status and version. Status cannot be changed by default. It cannot be approved and anything at go. It will follow the same flow as BOM and routing follows. It will first get drafted and then it will be submitted for approval, and then it will get approved. Similar flow.
> And for version, we will log the versions. It will be auto updated every time quality plan is edited. So by default, every new quality plan will have version 1. And when That quality plan is edited, its version will get updated automatically. The old quality plan will get historical automatically, and also this will solve our problem of old quality plans not having a record. This way we can properly be able to track quality plans and everything, so there is no issue in that. Yeah. Its impact on the routing if a quality plan whose version has been updated, the similar impact will be there on routing. It won't automatically update, but it will show a warning. Similarly, how we show a warning in bombs when a routing version get updated or outdated. So similarly, these are the how things are gonna be handled. So this will give us a proper records. And remember, quality if a quality plan is deleted, it can only be deleted when it's in draft. After getting approved, it can only be historical, and also when deleted, User will see permanently deleted, but a database will always soft delete it.
> So technically, name and version will be the unique key, a combination of those. That a quality plane with same name and same version cannot have a copy. You understand what I mean?

### QR-06: Parameter Type change must update the row
- **Code today** (create.blade.php lines 96–109): the Type dropdown is a plain select with `x-model="param.type"`. Min/Max/Unit only get `:disabled` when the type is not Numeric.
- Anything already typed is not cleared, nothing is hidden, and nothing type-specific appears.
- The user reports the fields don't change at all. Alpine is loaded twice (layout line 84 plus the page at line 137), which is the likely cause; it can't be confirmed live because the browser needs a login.
- **Fix:** the row reacts to the type (Numeric shows Min/Max/Unit; Pass/Fail and Text clear and lock them, or hide them, as the user decides), and Alpine is loaded only once.

### QR-07: Status is locked and follows the BOM/Routing flow
- Draft → Submit for approval (`pending_approval`) → Approve, or Reject (back to Draft).
- The Approval Status dropdown is removed from the form.
- Pattern to follow: `RoutingService::submitApproval` / `approve` / `reject` (lines 124, 184, 230) and `ProductionBomService` (188, 231, 379).
- An approval history table like `RoutingApproval` / `ProductionBomApproval` is needed.
- Approve permission: the existing `production.quality.approve` (already used in `ApprovalCenterService` line 102). Pending plans are listed in the Approval Center the same way BOM and Routing are (lines 99–122).

### QR-08: The version is automatic
- A new plan = version 1. The Version field becomes read-only.
- Editing creates the next version (1 → 2 → 3) as a new record with its own parameters. The old version becomes **historical** automatically and keeps its parameters.
- Past inspections keep pointing to the version they were checked with. This solves QP-01/02/03, because parameters are never deleted.
- **Difference from Routing:** a Routing makes a new version only through "Duplicate Version", with a version typed by hand (`RoutingService::buildCopyPayload`, line 390), and approval does NOT retire older versions (RT-02, line 218). The Quality Plan will be automatic instead, as the user asked.
- **Open:** whether a Draft edit bumps the version, and when exactly the old version becomes historical.

### QR-09: Routing warning when a plan has a newer version
- An operation linked to plan vN is not auto-updated. The routing shows a warning that a newer version exists.
- This follows the BOM pattern: `ProductionBom::buildRoutingAdvisories` (line 115), a live list of `{code, message, blocks_approval}`.
- The routing-form link to a plan comes later (the user will describe it).

### QR-10: Delete only in Draft
- Delete is allowed only in Draft. After approval, a plan can only become historical.
- The user sees "Permanently deleted", but the DB always soft-deletes (the plan model already has `SoftDeletes`).
- Parameters must not be hard-deleted any more.

### QR-11: Name + Version is unique (per tenant)
- No two plans can share the same name and version.
- Pattern to follow: `RoutingService::checkRoutingConflicts` (line 519) plus the DB unique `(tenant_id, name, version)` (routing migration `2026_11_02_130000`, line 37).
- **Open:** whether a soft-deleted Draft's name + version can be reused.

## QM-05: user's answers (2026-11-26)

| Question | Answer | Effect |
|---|---|---|
| Does a Draft edit bump the version? | **"No, only approved bump"** | QR-08: a Draft is edited in place (stays vN). Editing an Approved vN creates vN+1 as a new Draft (copy of header and parameters). |
| When does the old version become Historical? | **"When v2 is approved"** | QR-08: vN stays Approved and usable until vN+1 is approved. Then vN becomes Historical automatically. |
| Can a deleted Draft's Name + Version be reused? | **"Yes, can reuse"** | QR-11: the unique check ignores soft-deleted rows. This is an app-level check like `checkRoutingConflicts`, because a MySQL unique index cannot skip soft-deleted rows. |
| What happens to Min/Max/Unit on a Type change? | **"they are cleared and hidden so no garbage value is send to back-end.... and for pass / fail a coulmn will show "bases / reason" this will be the text box in which parameter will be written for a pass or fail to be checked."** | QR-06: Min/Max/Unit are cleared AND hidden for Pass/Fail and Text, and the server also ignores them for non-numeric rows. Pass/Fail rows get a new **"Basis / Reason"** text box with the acceptance criterion, e.g. "No holes visible on light table". This needs a new column on `production_quality_plan_parameters`. |

**My assumptions (to confirm):**
- **A1:** only one open new version at a time. While vN+1 is Draft or Pending, vN cannot be edited again; the user is sent to the open draft.
- **A2:** Text rows show no extra field (free notes are entered at inspection time).

## QM-06: user's message (verbatim)

> Yes agreed with all the above, each QP will get a detailed / view screen. the SS will get the new options for submit and approval. also historical.... almost same as BOM and Routing, you draft a view screen for me and show me a visual version of it then I will tell you what updates are needed.

A1 and A2 are agreed.

### QR-12: Quality Plan view screen (draft shown to the user as a mockup; waiting for their changes)
Modelled on `resources/views/modules/production/routing/show.blade.php`. Sections:
- **Top bar:** Back to list, plus actions that depend on the status:
  - Draft: Edit draft, Delete
  - Approved: Edit (new version)
  - Historical: Open current version
- **Lifecycle banner:** status badge, one-line meaning, and the workflow buttons:
  - Draft: Submit for approval
  - Pending: Approve / Reject (with comment)
  - Approved: Mark historical
- **Historical versions:** a warning strip saying "old version, vN is current, read only".
- **Header card:** name, plan number, version, status, created by, approved by / on.
- **Scope card:** scope type, product or section, product kind (traded / produced), parameter count (mandatory count).
- **Checklist parameters (read only):** #, parameter, type, limits (Numeric: min–max with unit) or basis (Pass/Fail), mandatory.
- **Version history:** every version of the same plan, its status and approval date, with links.
- **Where used:** routing operations linked to this plan (with a "newer version exists" flag; depends on the routing form changes still to come) and the count of inspections on this version.
- **Approval history:** date, user, action (Created / Version created / Submitted / Approved / Rejected / Marked historical), comments.

### QR-13: Row action menu on the plans list, by status
- **Draft:** View, Edit draft, Submit for approval, Delete permanent (soft delete in the DB).
- **Pending approval:** View, Approve, Reject.
- **Approved:** View, Edit (new version), Mark historical.
- **Historical:** View, Open current version.
- The plan name on the list opens the View screen (today it opens Edit).

## QM-07: user approved the view screen (verbatim)

> Approved, all this look good.... add this in the plan.... Now let's Start Implimenting this....

QR-12 and QR-13 are **approved** by the user.

### QM-08: user's answers on scope (2026-11-26)

| Question | Answer |
|---|---|
| What does this implementation round cover? | **"okay then let's discuss the routing part also so then you can Impliment Everything Together"** → no implementation yet. The routing-form rules are discussed first, then QR-01..QR-13, the gates (QR-04/05) and the routing changes are built **together**. |
| Use only Approved plans? | **"Yes, only Approved"** → QR-14 below. |

### QR-14: Only Approved plans are used downstream
- The plan dropdowns use Approved plans only. That covers inspection create (`QualityInspectionController` line 37) and the MES dashboard and operator screen (`MesController` 179 / 941).
- The automatic picks (`QualityInspectionService` 91–97 and 369–372, `ProductionExecutionService` 915–918, `SubcontractReceiptOrchestrator` 78) use Approved only, never the "first plan of the tenant".
- Draft, Pending and Historical versions are never used for new inspections.
- The automatic creation of empty "Standard …" plans is removed (QP-14).

### Routing form today (input for the routing discussion)
- Each routing operation row has a single **QC** checkbox (`routing/create.blade.php` line 586, `edit.blade.php` line 602), which saves `quality_required`.
- Ticking it also changes the hidden `operation_type` to `inspection` (create line 232).
- It is not linked to any Quality Plan.
- The flag is copied to order operations and read by MES, WIP, readiness, rework, subcontract, reporting and the QC services.

## QM-09: user's message on the routing part (verbatim)

> Okay so here is the thing, Then Digmark will return a gate only. But what we will do is we will create a new table and add a column in the existing routing tables. So what we will do is the new table will store what are the QC checks or what are the plans that are to be performed against that operation, and the routing tables will hold the IDs of those columns in which these are defined. So in short, we will create a new mapping table. Or what we can do is we do not need to add any column in the routing tables. Mapping table can just match the operation ID or something like that. So yeah, we won't even need an extra column in the routing table. The gate will be there. If a gate is active, then it's gonna go and search in the Quality plan map table for that particular operation, that what are the QCs that particular operation have, and then accordingly things will work. For the UI, when clicking the tick box, a model will open along with a view icon alongside the tick mark. So once everything has been done, that will be temporarily stored in the browser, and we can review those choices by clicking the eye icon. And if the tick mark is being deactivated, then the eye icon will be removed and the data will be trashed. Accordingly, things will happen. So now each operation can have even multiple quality checks to be ran at the operation level. So that's how the things will work. So using that, you can just turn on that gate for the semi and just add the product level QC at that. however you want to handle that in the backend. But remember, it shouldn't be permanent. Not all every time that those operations are being run, that final QC should run. That's not at every time. That's only specific for the loop time. Otherwise, it will be run in the work in progress section. From there, it will give a warning. because I do not want every time we create a QC for a product uh, that affects the routing, because routing is not product specific, routing is general. So that's why it will never affect the complete database for those routings.

### QR-15: The QC tick mark stays a gate, and a mapping table holds the plans
- `quality_required` on the routing operation stays the **gate** (on/off). No new column on the routing tables.
- **New mapping table** `production_routing_operation_quality_plans`: `tenant_id`, `routing_operation_id`, `quality_plan_id` (a specific approved version), `sort_order`, timestamps. It stores many plans per operation.
- If the gate is on, the system reads that operation's plans from the mapping table. If the gate is off, the mapping is ignored and removed on save.
- QR-09 (newer-version warning) works off this link: the mapped plan is vN and a newer approved vN+1 exists → warning on the routing (and on its View screen), never an automatic update.

### QR-16: Routing form UI
- Ticking QC opens a **modal** to pick one or more Quality Plans for that operation. An **eye icon** then appears next to the tick.
- The choices live in the browser (Alpine form state) until the routing is saved. The eye icon reopens the modal to review or change them.
- Unticking QC removes the eye icon and discards that operation's choices.
- Saved with the routing (create and edit); shown on the routing View screen next to the QC badge.
- "Duplicate Version" copies the mappings as well.

### QR-17: Looped semi QC is per order, never written into the routing
- For a semi made inside the order (sub-assembly set, UR-10), the gate is switched on for the LAST maker operation **of that production order only**, and the semi's approved **product-level** plan is attached there.
- Never written to the routing: the routing is general, not product-specific. Creating a product plan never changes any routing.
- **Backend (my design, which the user left to me):** a snapshot table at order level, `production_order_operation_quality_plans` (`production_order_operation_id`, `quality_plan_id`, `source` = `routing` | `sub_assembly`). When the order's operations are built from the routing (`ProductionOrderService` lines 1780 and 2266 already copy `quality_required`), the routing mappings are copied in. For the last maker operation of each sub-assembly set, the semi's approved product plan is added with source `sub_assembly`, and `quality_required` is set on that order operation only.
- A running order is not changed by later routing or plan edits.

### QR-18: The normal end product's QC runs in the WIP section
- When a product is the order's finished good (including a semi made in its own order), its product-level QC runs in the **WIP section**, where WIP is converted to finished goods (`ProductionWipService::convertWipToFinishedGoods` line 1292 / `convertOrderWipToFinishedGoods` line 1346).
- The WIP screen shows a warning while that QC is pending (blocking still to confirm).

### QM-10: user's answers on the routing part (2026-11-26)

| Question | Answer | Effect |
|---|---|---|
| WIP → finished goods for a product with a plan | **"Block + warning"** | QR-18: conversion and receipt are blocked until QC passes, and only the QC-accepted quantity can be converted. A warning shows in WIP. |
| QC tick with no plan picked? | **"Yes, at least one"** | QR-16: the routing can't be saved with a QC tick that has no plan. |
| Which plans can be picked in the routing modal? | **"Section + Process General"** | QR-16: only Approved plans of the operation's own section, plus Process General plans. Product plans are not offered. |
| Several plans on one operation | **"similar to the plan, the modal will have a column for mandatory"** | QR-16: every picked plan has a **Mandatory** tick in the modal. Mandatory plans must all be inspected before the operation's QC counts as done; optional plans may be skipped. `is_mandatory` is added on the mapping table and the order snapshot table. |

## QM-11: user's message on the modal (verbatim)

> I have a few changes in mind. Like instead of showing the complete list, as there could be so many quality plans, what we will do is we will adapt a drop down multi select type of technique. So what it will do is initially there will be in the model there will be a screen to select what QC plans he wants to select. uh, just near the drop down there will be an option to select the filter for section plans or process general plans, and accordingly the drop down will show the data. And once it selects that plan and click on add, then he will have an option to select that is this plan a mandatory plan or not. And also, what will this view button do? Will it open another tab in a separate tab, or will it display a model, or will it just redirect you to that screen? Because that is a very required UX decision that we need to finalize.

## QM-12: user's answer on the View button (verbatim)

> Preview inside modal, I agree with this, only a small chnage in modal view, instead of 2 btn for section and process general, it will be drop down.... with a 3rd option as both.... and yess, for routing form QC plans this column won't be added. only the modal and eye icon will be added

## QM-13: user's message on outdated routing plan versions (verbatim, 2026-11-26)

Quoting my earlier note: "If a routing still links an old plan version, new orders use that plan's current approved version; the routing only shows the warning."

> no, they will either use the old version, or maybe we will block new orders from getting created, they are few life cycle decisions we need to make accross the production Module, we will do that later... keep it for now it will block....

### QR-19: Outdated routing QC plan blocks new orders (for now)
- When a NEW order's operations are built from a routing whose QC operation links a plan version that is no longer approved (a newer version is approved, or it is historical), the order is not created. The error message names the routing, the operation and the plan, and says to update the routing's QC plans first.
- Code: `OrderOperationQualityService::copyRoutingPlans($op, $blockOutdated = true)` / `throwOutdated()`.
- Orders that already exist (plans copied on their first QC use) keep the linked version as it is; nothing is blocked there.
- Open: the production-wide lifecycle decision (use the old version, or block) comes later from the user.
- Status: Applied (2026-11-26, not tested by me).

## QM-14: user's message on product plans (verbatim, 2026-11-26)

Quoting my earlier note: "A4: when a product has several approved product plans, all of them apply."

> Let's make it so that in the case of products, there can be only one approved version per product.
>
> So in the product dropdown itself, those products who already have a quality plan will get disabled and show a warning that a quality plan for this product has already been created. Go and edit that. Only in the case of historical, because a historical plan does not show a edit button. So only in the case of plans that have become historical, in those cases only we can create new ones, because edit option won't be there.

### QR-20: One quality plan in use per product
- A product can be held by only one plan family. A draft, pending or approved plan blocks any other plan for that product. A new plan can be created only once the existing one is historical (or a deleted draft).
- Plan form: in the Target Product dropdown, products held by another plan are disabled. Their label reads "quality plan QP-xxxx (status) already exists, edit that plan", and a help line explains the rule. When you edit a plan, its own product stays selectable.
- Server check on create, on editing a draft and on creating a new version: `QualityPlanService::productPlanHolder()` / `assertProductFree()`.
- This replaces A4: there is only ever one approved product plan per product.
- Status: Applied (2026-11-26, not tested by me).

## QR-05 shape (superseded by QR-17)

A **button** (my recommendation, which the user left to me). It is not answered explicitly yet, so it stays open until the user confirms.

## Files likely touched later (not now)
- `resources/views/modules/production/quality/plans/create.blade.php`, `edit.blade.php`, `index.blade.php`
- `app/Domains/Production/Controllers/QualityPlanController.php`, `Requests/StoreQualityPlanRequest.php`, `Models/ProductionQualityPlan.php`
- new migration: `section_id` on `production_quality_plans`
- `app/Domains/Production/Services/ProductionExecutionService.php` (FG receipt gate)
- `app/Domains/Production/Services/OperationMaterialService.php`, MES complete flow (semi QC gate and warning)

## Verification (when implemented)
The user checks in the UI themself (no tests run by me unless asked). `php -l` / `view:clear` only.

# QC flow review 2026-11-28 (findings; nothing fixed yet)

The user asked what is left in quality inspection, where it shows on the MES board, and how it affects the flow. "Let's fix that flow quickly." The findings are below. Fixes wait for the user's go-ahead.

**Where QC shows on the MES board** (`mes/dashboard.blade.php`)
- **Process column:**
  - a "QC" badge next to Seq
  - "QC: N" in the metrics bar (yellow while units wait)
  - Acc / Rej counts
- **Actions column:**
  - a yellow "Run QC (N)" button while units wait
  - "QC Passed" once the operation is completed
  - a red "Rework / Scrap (N)" button for rejected units
- **Add production modal:** the quantity is "Processed Output Qty (Pending QC)", with a yellow notice.
- **Run QC modal:**
  - the operation's own plan checklist
  - inspector
  - accepted / rejected / defect reason / remarks
  - the confirmation tick for a failed mandatory check
- **Same form elsewhere:** the operator screen (`mes/operator/operation-execution.blade.php`) has it too. The WIP screen has "Final QC (N pending)" for the finished product. Quality → Inspections is a separate, older path.

**How it affects the flow (code)**
1. **Logged output waits for QC:** on a QC operation, logged output does not count as made. Waiting = logged − inspected (`MesExecutionService::getPendingQcQuantity`).
2. **Run QC books the result** (`QualityInspectionService::processShopfloorInspection`):
   - accepted units are added to the operation's output and WIP, then passed to the next operation
   - rejected units get an NCR plus Rework / Scrap
   - the operation completes when the accepted quantity reaches the target
   - on an outsourced operation, the waiting receipts are booked and their GRN is written (`bookAfterQc`)
3. **Next operation can't start before QC:** it can't start while a completed predecessor's QC gate is pending or failed (`qualityGateIsPendingOrFailed`).

**Gaps found**

| # | Gap | Effect | Proposed fix |
|---|---|---|---|
| QF-01 | The QC gate also counts inspections with no operation (`orWhereNull('production_order_operation_id')`): the WIP final QC, or one made from Quality → Inspections. | A passed final / manual inspection opens the gate of every QC operation of that order. | Count only the operation's own inspections. |
| QF-02 | The gate is "any passed inspection exists", not "nothing waiting". | After one partial pass, "force complete" completes the operation while units still wait for QC. | Gate also fails while units are waiting for QC. |
| QF-03 | Quality → Inspections (create → results → approve) can target an order operation but has no accepted / rejected quantity. | In-house op: adds no output, but opens the gate (QF-01/02). Outsourced op: approve runs the old `SubcontractReceiptOrchestrator::processQcApproval`, which adds passed qty, or else the inspected / order qty, to the operation outside the receipt / GRN flow, so it can be booked twice. | That menu no longer takes an order operation (in-process QC of an operation = Run QC on the MES board / operator screen). Remove the `processQcApproval` call and the leftover orchestrator. |
| QF-04 | `approveInspection` still moves stock out of a "Quarantine" warehouse (QR-04 removed quarantine). | Dead path, which could move stock if such a warehouse exists. | Remove. |
| QF-05 | Old plan-picker JS (`updateQualityChecklist`, a hard-coded "Visual Surface Finish" fallback checklist) is left on the MES board and operator screen. `MesController` still loads `$qualityPlans` for it. No plan dropdown exists any more (QR-16). | Dead code / clutter. | Remove. |

**User 2026-11-28 (verbatim):** "Yes, fix all five, and we will do the same thing with QC that we done with the subcontracting. The button on the shop floor will not open up pop up. It will redirect us to the inspection create page. You can say on that page the inspections will already be created, or you can say yeah, we won't be creating new inspection from that page. For an operation, when operation is started and it needs a QC, its inspection will already get created with the rules that are there. Additional inspections can be created for that QC, depending upon the process and other things if it is needed. Otherwise, uh, it will work like that. So each operations inspection will get recorded in our inspection page, and performing a QC will need to be done from the inspection page following the inspection algorithm and inspection rules. Did you understand that? Now it wouldn't be no longer just enter the amount that passed QC and all those. Not that basic thing. It will be a detailed one, a proper QC one. Okay."

**Fix status (Applied 2026-11-28, `php -l` clean, views compile, not run)**
- **QF-01 Applied:** the QC gate counts only the operation's own inspections. The `orWhereNull` was removed in 4 places:
  - `MesExecutionService::qualityGateIsPendingOrFailed`
  - `ProductionExecutionService::ensureQualityGatePassed`
  - `ProductionReadinessService` (QC readiness)
  - `ProductionWipService` (batch journey QC badge)
- **QF-02 Applied:** in `MesExecutionService` (log / complete), a QC operation is not completed while units are still waiting for QC. "Force complete" is refused with "N unit(s) of this operation are waiting for QC. Inspect them before completing the operation."
- **QF-03 Applied (part):** `approveInspection` no longer calls `SubcontractReceiptOrchestrator::processQcApproval`, and that file is deleted (it had no other caller). An outsourced operation's units are booked only by its shop-floor QC (`bookAfterQc`).
  - **Held:** "Quality → Inspections stops taking an order operation". The QD design below makes the inspection page the QC path, so this part is replaced by QD.
- **QF-04 Applied:** the quarantine stock-transfer block was removed from `approveInspection`, together with its two unused imports.
- **QF-05 Applied:** removed the old plan-picker JS (`qualityPlansMap`, `updateQualityChecklist`, the hard-coded fallback checklist, the `quality_plan_id` handlers) from `mes/dashboard.blade.php` and `mes/operator/operation-execution.blade.php`. `MesController` no longer loads `$qualityPlans` for them (dashboard + operator screen).
- **Old tests:** `ComplexIndustrialManufacturingScenarioTest`, `MultiModelPhase3CoverageTest`, `MultiModelPhase3Test` and `MultiModelPhase4Test` still use the deleted orchestrator (they were already among the 20 old tests).

## QD: QC done on the inspection page (user's design 2026-11-28, understood, NOT coded; questions open)

**My understanding:**
- **QD-01 Redirect, no popup:** the shop-floor "Run QC" button (MES board + operator screen) no longer opens a popup. It goes to the operation's inspection page, as dispatch and receipt do for subcontracting.
- **QD-02 Created at start:** when an operation that needs QC is started, its inspection is created automatically from the operation's plans:
  - the order snapshot: routing plans plus the semi's plans on a looped semi
  - every check of those plans, with their rules (limits, pass / fail, mandatory)

  Nobody creates it by hand.
- **QD-03 Extra inspections:** more inspections for the same operation can be added on that page when the process needs them.
- **QD-04 All on the inspection page:** every operation's inspections are listed in Quality → Inspections. QC is performed there following the plan rules, as a detailed QC, not just "accepted / rejected quantity".
- **QD-05 Popup removed:** the quick Run QC popup goes. Its results (units move on, NCR + Rework / Scrap for rejects, subcontract receipts booked) are then produced by the inspection page.

**Open questions:** see the chat of 2026-11-28; the answers are recorded here.

### QD: user's follow-up 2026-11-28 (verbatim)
> "Check the current structure. Tell me exactly how the current structure works. Because a QC can only be done when goods production is logged. So I think currently it can only be done when all the goods are completely produced. So either we can follow that, and so after all the goods are produced, there will be one single QC check. And if we want to do QC checks in parts for the goods that are produced, like if 10 goods are produced, 10 QC checks, then there will be multiple check records. You can say each QC check record will be linked via the operation ID, you can say, or the order ID. I mean the both the order ID and the order's operation ID. It will be linked by both. So it would be like a table. So it can have multiple QCs for same operation. That won't be an issue. So technically it would be per order. So now, so it doesn't matter. Uh, for those operations. For more, what we need to do is we will need to maintain the QC for items also. As the thing is, if see, currently it's a batch wise production. If it's produced by item number, then we need to check how item numbers are generated and how they can be logged per QC. Because if items are generated, then which items are rejected, we need to log those too. If they are being managed serial number wise, if they are managed lot wise, then we will see what need to be done."

### Current structure (from code, 2026-11-28)
- **QC in parts already works:** every output log on a QC operation goes to "waiting for QC" (waiting = logged − inspected, `getPendingQcQuantity`). Run QC can be done whenever units are waiting, for up to that many. Each Run QC writes one inspection record. Example: log 10 → QC 10 → log 30 → QC 30 gives 2 records. The operation completes when the accepted quantity reaches the target. It does **not** wait for all goods to be produced.
- **Inspection record** (`production_quality_inspections`):
  - links: `production_order_id` + `production_order_operation_id`, `batch_id`, `serial_number_id` (one only), machine, operator
  - state: stage (incoming / in_process / final), status (draft / submitted / approved), result
  - quantities: `inspected_quantity` / `passed_qty` / `failed_qty`, plus `sample_size` (a column, never filled from the floor)

  Many records per operation are allowed. Plans: many per record (`production_quality_inspection_plans`). Readings: **one value per check** per record (`production_quality_inspection_results`), not per sample piece or per unit.
- **How a product is tracked:** `products.track_batch` / `track_serial_number` set the order's `production_mode` (standard / batch / serial / batch_and_serial).
- **Batches:** made by hand on the order (`BatchProductionService::createBatch`, split / merge, auto batch number).
  - Output logs carry `production_batch_id`. Inspections carry `batch_id`, and the waiting-for-QC count can be per batch.
  - The MES board counts per operation, not per batch.
- **Serials** (`production_serial_numbers`): made on the operator screen's Serial tab, either a generated range (prefix + 6 digits, e.g. SN000001) or typed by hand. Each serial has status planned / produced / packed / shipped / returned / scrapped / reworked.
  - **Gaps:**
    - logging output never says which serials were made
    - QC never records which serials passed or were rejected
    - no QC path sets a serial to scrapped or reworked
    - serials are only picked at finished-goods receipt (checked against the order)
- **Lots:** production has no lot concept separate from the batch. The inventory batch is created at the finished-goods receipt.

### QD: user's rule for the record quantity (2026-11-28, verbatim)
> "For quantity per inspection, there will be a fixed quantity. So all the checks will be for a quantity. So if like 20 quantities passed, all three checks, those will be in a single quality check record. And then if another 20 quantities doesn't pass, those will be in the separate inspection record, even though those both quantities belong to the same OP of the same order. Did you understand that? And we will display this as a tooltip also. Those quantities that pass the QC will move to the next operation, as QC is done after the completion of those operations. So it will move to the next operation. If that doesn't pass a QC, I think their NCR should be generated, or you can say a link in the NCR will be there to generate, or maybe no need for NCR. We will see what needs to be the further stage. This for the failed quantity we will discuss it. First, let's implement the quantity that have been passed."
>
> Then: "For serial number items, we will discuss it later. For now, just fix it and then update the seeder if needed, and then also create the data for production orders…"

### QD: what was built (Applied 2026-11-28; `php -l` clean, views compile, seeder run, pages checked in the browser)

| # | Rule | Where |
|---|---|---|
| QD-01 | Shop-floor QC buttons go to the operation's inspection page, with no popup. This covers the MES board "Run QC (N)" and the operator screen "QC CHECK". | `mes/dashboard.blade.php`, `mes/operator/operation-execution.blade.php` → route `production.quality.operation-inspection.show` |
| QD-02 | A QC operation always has one **open** record (status draft, result pending) with its plans. It is made when the operation starts (`MesExecutionService::startOperation`), when a subcontract receipt waits for QC (`SubcontractReceiptService::receive`), after each performed record, and when the page opens (older orders). | `QualityInspectionService::openOperationInspection` |
| QD-03 | "Add inspection" on the page adds another open record. Quality → Inspections → Create with an order operation does the same, then redirects to the page. | `OperationInspectionController::add`, `QualityInspectionController::store` |
| QD-04 | **One record = one quantity with one result.** The inspector enters the quantity (≤ waiting) and every check of the operation's plans (mandatory checks required). All recorded checks inside limits → the whole quantity passed; **any** failed check → the whole quantity failed. With no plan attached, the inspector picks Passed / Failed. A tooltip on the page, on the record badges and on the MES QC badge explains the rule. | `QualityInspectionService::performOperationInspection`, `quality/inspections/operation.blade.php` |
| QD-05 | **Passed:** units added to the operation's output and WIP, then on to the next operation. The operation completes at target. On an outsourced operation the waiting receipts are booked and their GRN is written. **Failed:** counted as rejected, NCR, Rework / Scrap as before (**open, to discuss**). | same |
| | Removed: the QC popups and their JS, `MesController::recordQualityInspection` + route `production.mes.quality-inspection`, `processShopfloorInspection` (accepted + rejected in one record). | |
| | Readiness: a failed record blocks the operation's QC readiness only while no quantity has passed. | `ProductionReadinessService::evaluateQualityDimension` |

**My decisions (please check):**
1. **Optional checks count:** "any failed check fails the record" includes a failed reading on a non-mandatory parameter or an optional plan that was ticked. Mandatory = must be filled.
2. **Inspection numbers:** `INSP-YYYYMMDD-0001` (per tenant, per day).
3. **Unchanged:** the WIP final QC (finished product) keeps its accepted + rejected form. Serial / batch QC is left for later (user).
4. **Gap found while seeding:** `ProductionOrderOperation::needsDispatchSlip()` was missing from the model while five places called it, so the MES board crashed. I added it back as the subcontract plan defined it: outsourced and (previous output or BOM rows).
5. **Outdated guides:** `app/Domains/Production/docs/SHOPFLOOR_QC_GUIDE.md` and `DEVELOPER_ONBOARDING.md` still describe the old popup / `processShopfloorInspection`.
