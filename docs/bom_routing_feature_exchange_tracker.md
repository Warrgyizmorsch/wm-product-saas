# BOM & Routing Feature Exchange Tracker

This document tracks all requirements, architecture shifts, business rules, design decisions, and implementation plans for **inverting the creation and tracking relationship between Bill of Materials (BOM) and Routing**.

* **Reference Architecture Map:** [`docs/bom_routing_architecture_map.md`](file:///c:/xampp/htdocs/wm-product-saas/docs/bom_routing_architecture_map.md)
* **Work Center Linking Tracker:** [`docs/work_center_routing_linking_tracker.md`](file:///c:/xampp/htdocs/wm-product-saas/docs/work_center_routing_linking_tracker.md)
* **Status Legend:**
  * 🟡 **Discussion / Proposed** — Item under discussion and architectural review.
  * 🟢 **Agreed** — Requirements and technical specification agreed upon; queued for batch implementation.
  * 🔵 **In Progress** — Currently being coded during execution phase.
  * 🛠️ **Applied** — Implemented in code/schema; awaiting user verification & UI implementation.
  * ✅ **Verified** — Verified and signed off by user after full UI and end-to-end testing.
  * ⏸️ **On Hold / Postponed** — Deferred for future iteration.

---

## Summary Register

| ID | Title / Area | Status | Impacted Components |
| :--- | :--- | :--- | :--- |
| **TRACK-01** | Invert Parent Entity Tracking Link (`routing_id` on BOM vs `bom_id` on Routing) | 🛠️ **Applied** | Migrations, `ProductionBom`, `Routing`, DTOs |
| **TRACK-02** | Move Operation-Component Assignment into BOM & Populate `RoutingOperationMaterial` | 🛠️ **Applied** | Migrations, `ProductionBomItem`, `RoutingOperationMaterial`, `ProductionBomService` |
| **TRACK-03** | Decouple Routing Creation & Approval from BOM Pre-requisite | 🛠️ **Applied** | `StoreRoutingRequest`, `RoutingService`, `RoutingRepository`, `RoutingController` |
| **TRACK-04** | BOM Creation & Edit UI Overhaul (Routing Header Selector & Component Operation Dropdowns) | 🟢 **Agreed / Finalized UI Spec** | `bom/create.blade.php`, `bom/edit.blade.php`, `routing/create.blade.php`, `routing/edit.blade.php` |
| **TRACK-05** | Material Allocation & Validation Rules (Mandatory Operation, Zero-Material Operations, Multi-Op) | 🛠️ **Applied** | `StoreProductionBomRequest`, `UpdateProductionBomRequest`, `ProductionBomService` |
| **TRACK-06** | Invert Lifecycle & Approval Cascades (Routing State Changes Affecting Connected BOMs) | 🛠️ **Applied** | `RoutingLifecycleService`, `BomLifecycleService`, `ProductionBomService` |
| **TRACK-07** | Refactor Downstream Consumers (`ProductionOrder`, `ProductionPlan`, Readiness & Costing) | ⏸️ **On Hold / Deferred** | `ProductionOrderService`, `ProductionReadinessService`, `ProductionCostService` (After BOM & Routing finalized) |
| **TRACK-08** | Clean Database Migrations & Schema Modernization (Zero Legacy Backfill Needed) | 🛠️ **Applied** | Migrations (`routings`, `boms`, `bom_items`, `routing_operation_materials`), Seeders at end |
| **TRACK-09** | ECO (Engineering Change Order) Compatibility — BOM & Routing Inversion Impact | ⏸️ **On Hold / Deferred** | `ProductionEcoService`, `ProductionEcoImpactAnalysisService`, `ProductionEco` (Deferred - to be finalized later) |
| **TRACK-10** | BOM Duplication & Revision — Clone `routing_operation_id` on Items | 🛠️ **Applied** | `ProductionBomService::duplicateVersion()` |
| **TRACK-11** | Bulk Import Service — Invert `RoutingOperationMaterial` Population Source | 🛠️ **Applied** | `ProductionMasterImportService` (lines 703–793), CSV import schema |
| **TRACK-12** | Feature Test Suite Updates — Align All BOM & Routing Tests with New Architecture | 🛠️ **Applied** | `tests/Feature/Production/BomRoutingInversionTest.php`, `tests/Feature/Production/RoutingTest.php` |

---

## Detailed Feature Tracking & Technical Proposals

---

### TRACK-01: Invert Parent Entity Tracking Link (`routing_id` on BOM vs `bom_id` on Routing)
* **Date Logged:** 2026-10-30
* **Status:** 🛠️ **Applied**
* **Area:** Database Schema, Models & Core Entity Relationships

#### 1. Description & Context
Currently, a Routing points to a BOM via `routings.bom_id`. This forces users to create the BOM first before creating a Routing.
In the target architecture, a Routing represents the manufacturing process (sequence of machine operations) which can be designed first. The BOM represents the recipe of materials used to execute that manufacturing process and therefore points to the Routing via `production_boms.routing_id`.

#### 2. Agreed Architectural Decisions
1. **Mandatory Routing on BOM:**
   - Selecting a `routing_id` is **mandatory** for BOM creation and editing (`required|exists:routings,id`).
2. **Product Decoupling from Routing (`product_id` Dropped on Routings):**
   - Routing represents a pure, generic manufacturing process route (e.g. "Sheet Metal Stamping Line", "Standard Powder Coating Flow").
   - `production_routings.product_id` is dropped, eliminating circular validation conflicts.
   - Routings are reusable across 1-to-many BOMs.
   - The selectable Routings on the BOM form fetch all available active/draft/approved Routings in the tenant without product-bound constraints.
3. **Lifecycle Status Transitions:**
   - When creating or editing a **Draft BOM**, the user is permitted to link a **Draft, Active, or Approved Routing**.
   - A Draft BOM **can be submitted for approval (`pending_approval`)** while the linked Routing is in Draft or Pending Approval.
   - When **Approving the BOM**, the linked Routing must strictly be in an **Approved / Active** state.
   - If the connected Routing is **rejected**, any linked pending BOM is **automatically rejected / returned to draft**.
   - Routing approval does **NOT** auto-approve the BOM (the BOM requires its own independent approval review).
4. **Schema Cleanup for `routings.bom_id` and `routings.product_id`:**
   - Cleanly **drop foreign key and column `bom_id`** from the `routings` table.
   - Cleanly **drop foreign key and column `product_id`** from the `routings` table.

#### 3. Agreed Technical Specification
* **Database Migrations:**
  * Add `routing_id` to `production_boms` (`foreignId('routing_id')->constrained('routings')->restrictOnDelete()`).
  * Drop foreign key and column `bom_id` from `routings`.
* **Model Relationships:**
  * In `ProductionBom.php`: Define `public function routing(): BelongsTo { return $this->belongsTo(Routing::class, 'routing_id'); }`.
  * In `Routing.php`: Define `public function boms(): HasMany { return $this->hasMany(ProductionBom::class, 'routing_id'); }`. Remove `bom()` belongsTo relationship.
* **DTO & Requests:**
  * Update `ProductionBomDTO`: Add `public readonly int $routing_id`.
  * Update `StoreProductionBomRequest` & `UpdateProductionBomRequest`: Add `'routing_id' => 'required|exists:routings,id'`.
  * Update `RoutingDTO`, `StoreRoutingRequest`, `UpdateRoutingRequest`: Remove all `bom_id` references.

#### 4. Files to Modify (During Implementation Batch)
* `database/migrations/xxxx_xx_xx_add_routing_id_to_boms_and_drop_bom_id_from_routings.php`
* `app/Domains/Production/Models/ProductionBom.php`
* `app/Domains/Production/Models/Routing.php`
* `app/Domains/Production/DTO/ProductionBomDTO.php`
* `app/Domains/Production/DTO/RoutingDTO.php`
* `app/Domains/Production/Requests/StoreProductionBomRequest.php`
* `app/Domains/Production/Requests/UpdateProductionBomRequest.php`
* `app/Domains/Production/Requests/StoreRoutingRequest.php`
* `app/Domains/Production/Requests/UpdateRoutingRequest.php`

---

### TRACK-02: Move Operation-Component Assignment into BOM & Populate `RoutingOperationMaterial`
* **Date Logged:** 2026-10-30
* **Status:** 🛠️ **Applied**
* **Area:** Component Assignment & Tracking Architecture

#### 1. Description & Context
Currently, `production_routing_operation_materials` stores which material is consumed in which routing operation, but it is populated when creating a Routing.
In the target architecture, we **keep the same destination table (`production_routing_operation_materials`)** and change the source:
Instead of data originating from the Routing form, the data originates from the **BOM**. When a BOM is saved, it assigns each component to its designated Routing Operation and populates/syncs `production_routing_operation_materials`.

#### 2. Agreed Architectural Decisions
1. **Reuse Existing Table:**
   - Keep `production_routing_operation_materials` as the execution and stage-material tracking table.
   - Do NOT create a separate or redundant mapping table.
2. **Add `bom_id` to `production_routing_operation_materials`:**
   - Add `foreignId('bom_id')->nullable()->constrained('production_boms')->cascadeOnDelete()` to `production_routing_operation_materials`.
   - This provides explicit BOM version attribution to every operation material record.
3. **Capture Operation in BOM Items:**
   - Each component row in the BOM form allows selecting the `routing_operation_id`.
   - `production_bom_items` records `routing_operation_id` (`foreignId('routing_operation_id')->nullable()->constrained('production_routing_operations')->nullOnDelete()`).
4. **Synchronization Flow:**
   - On BOM `create()` and `update()`, `ProductionBomService` syncs the assigned component materials into `production_routing_operation_materials` for that BOM and its operations.
   - Downstream services (`ProductionOrderService`, `ProductionReadinessService`, etc.) continue reading `RoutingOperationMaterial` seamlessly.

#### 3. Files to Modify (During Implementation Batch)
* `database/migrations/xxxx_xx_xx_add_bom_id_to_routing_operation_materials_and_op_to_bom_items.php`
* `app/Domains/Production/Models/ProductionBomItem.php`
* `app/Domains/Production/Models/RoutingOperationMaterial.php`
* `app/Domains/Production/DTO/ProductionBomItemDTO.php`
* `app/Domains/Production/Services/ProductionBomService.php`

---

### TRACK-03: Decouple Routing Creation & Approval from BOM Pre-requisite
* **Date Logged:** 2026-10-30
* **Status:** 🛠️ **Applied**
* **Area:** Routing Domain & Lifecycle Rules

#### 1. Description & Context
Currently:
* `StoreRoutingRequest` / `UpdateRoutingRequest` enforces Rule 4 (BOM component assignment, complete allocation Rule A, foreign component check, over-allocation Rule C).
* `RoutingService::submitApproval()` and `approve()` throw an exception if the routing does not have an active or draft BOM.
* This blocks creating and approving a Routing first!

#### 2. Agreed Architectural Decisions
1. **Fully Standalone Routing Workflow:**
   - Routing is created, submitted for review, and approved 100% on its own merits (validating work centers, machines, sequences, cycle times, quality gates).
   - No BOM is required at any point in the Routing lifecycle.
2. **Material Quantity Mode Removed from Routing:**
   - The concept of `material_quantity_mode` (`per_unit` vs `per_bom_base_quantity`) is removed from Routing because Routing does not care about material quantities.
   - The engineer authoring the BOM will specify consumption quantities directly.
3. **Comment-Out Policy (Preserve Logic for BOM Re-use):**
   - **Do NOT delete the existing validation and allocation code immediately.**
   - Instead, comment it out cleanly with explicit markers (`// [INVERSION-DECOUPLE-TRACK-03] ...`) so the logic remains visible as reference when adapting it into the BOM in TRACK-05.
   - Document all commented-out locations in this tracker file so that once all tracks are verified and tested, they can be removed in a single clean cleanup pass.

#### 3. Planned Comment-Out Locations (Execution Phase)
1. **`app/Domains/Production/Requests/StoreRoutingRequest.php` & `UpdateRoutingRequest.php`:**
   - Comment out `bom_id` and `material_quantity_mode` from `$rules`.
   - Comment out Rule 4 (BOM component assignment & over-allocation validation block, lines ~184–321).
2. **`app/Domains/Production/Services/RoutingService.php`:**
   - In `submitApproval()`: Comment out lines ~138–159 (requiring active/draft BOM check).
   - In `approve()`: Comment out lines ~190–206 (requiring approved/active BOM check).
   - In `syncOperations()`: Comment out lines ~420–441 (populating `RoutingOperationMaterial` from routing operations).
3. **`resources/views/modules/production/routing/create.blade.php` & `edit.blade.php`:**
   - Comment out "Select BOM" dropdown.
   - Comment out "Material Quantity Mode" radio buttons.
   - Comment out "Live BOM Component Allocation Summary Card".
   - Comment out the operation materials assignment modal/button from the operations table.
4. **`app/Domains/Production/Services/RoutingLifecycleService.php`:**
   - Comment out `syncDraftRoutingsToApprovedBom()` (line 223) entirely — this method auto-updates `routings.bom_id` on draft Routings when BOM is approved. Under inversion, Routing does not track BOM. Mark: `// [INVERSION-DECOUPLE-TRACK-03]`.
   - Comment out `archiveRoutingsForDeactivatedBoms()` (line 241) — called from `ProductionBomService::approve()` to archive linked Routings when old BOM versions are deactivated. Under inversion, BOM approval does NOT cascade to Routings. Mark: `// [INVERSION-DECOUPLE-TRACK-03]`.
   - Comment out `handleRoutingsOnBomCancellation()` (line 287) — called from `ProductionBomService::cancel()`. Under inversion, BOM cancellation does NOT cascade to Routings. Mark: `// [INVERSION-DECOUPLE-TRACK-03]`.
5. **`app/Domains/Production/Services/ProductionBomService.php`:**
   - In `approve()` lines ~199–209: Comment out calls to `archiveRoutingsForDeactivatedBoms()` and `syncDraftRoutingsToApprovedBom()`. Mark: `// [INVERSION-DECOUPLE-TRACK-03]`.
   - In `reject()` lines ~257–279: Comment out the entire `$linkedRoutings` block that auto-rejects connected pending Routings when a BOM is rejected. Mark: `// [INVERSION-DECOUPLE-TRACK-03]`.
   - In `cancel()` lines ~299–305: Comment out call to `routingLifecycleService->handleRoutingsOnBomCancellation()`. Mark: `// [INVERSION-DECOUPLE-TRACK-03]`.
6. **`app/Domains/Production/Models/ProductionBom.php`:**
   - Comment out `routings()` HasMany relationship (line 75) — based on old `bom_id` FK on Routing.
   - Comment out `routing()` HasOne relationship (line 80) — based on old `bom_id` FK on Routing.
   - Comment out `getActiveRoutingAttribute()` accessor (line 86) — resolves routing from `WHERE bom_id = ...`; must be replaced with the new `belongsTo` direct FK accessor.
   - Mark all with `// [INVERSION-DECOUPLE-TRACK-03]`.

#### 4. Files to Modify (During Implementation Batch)
* `app/Domains/Production/Requests/StoreRoutingRequest.php`
* `app/Domains/Production/Requests/UpdateRoutingRequest.php`
* `app/Domains/Production/Services/RoutingService.php`
* `resources/views/modules/production/routing/create.blade.php`
* `resources/views/modules/production/routing/edit.blade.php`

---

### TRACK-04: BOM Creation & Edit UI Overhaul (Routing Header Selector & Component Operation Dropdowns)
* **Date Logged:** 2026-10-30
* **Status:** 🟢 **Agreed / Finalized UI Specification**
* **Area:** BOM Frontend Views (`bom/create.blade.php`, `bom/edit.blade.php`), Alpine.js Scripts & Form Validation

#### 1. Description & Context
In the inverted architecture:
* Routing is created first as an independent, reusable manufacturing process route (without `product_id` or `bom_id`).
* When creating a BOM, the user chooses which Finished Product to manufacture (`product_id`) and which Process Routing to use (`routing_id`).
* Each raw material / component in the BOM is mapped to a specific operation of that Routing (`routing_operation_id`), giving complete visibility of where each part is consumed.

#### 2. Finalized UI Specifications

##### A. BOM Header (`bom/create.blade.php` & `bom/edit.blade.php`)
1. **Routing Dropdown Placement:**
   - In the Left Column of the BOM Header card, place the **Process Routing (`routing_id`)** selector directly below/adjacent to the **Item to Produce (`product_id`)** field.
2. **Selectable Options:**
   - Options include all tenant Routings in **`Active`**, **`Approved`**, or **`Draft`** status (not restricted by product).
   - Option label format: `[RTG-XXXX] Routing Name (v1.0.0 · Active)`.
   - Embed operations data (or initialize a client-side lookup dictionary) so Alpine.js has instantaneous, zero-latency access to the operations of the selected routing.
3. **Change Handling:**
   - When the user selects or switches a Routing:
     - The available operations in Alpine.js (`availableOperations`) immediately update to reflect the newly selected routing's operations.
     - If the routing is changed after component operations were already selected, existing assigned rows are alerted/prompted to avoid invalid operation IDs.

##### B. Components Table (`tab-components`)
1. **New "Operation / Station" Column:**
   - Add a dedicated column to the BOM components table: **`Operation / Station`** (`routing_operation_id`).
   - Width: ~16% (responsive table layout).
   - Position: Placed between Component Product and Quantity for intuitive operational flow.
2. **Operation Dropdown Behavior:**
   - Rendered as `<x-ui.odoo-form-ui type="select" name="items[index][routing_operation_id]" ...>` bound to `x-model="item.routing_operation_id"`.
   - **When Routing is selected:** Populates with the selected routing's operations.
     - Option label: `[Seq 10] Op Name (Work Center Name)`.
   - **When no Routing is selected yet:** The select displays: *`-- Select Routing in Header First --`* and is disabled.
   - **Defaulting on New Row (`addItem`):** Automatically defaults `routing_operation_id` to the first operation of the selected routing (`availableOperations[0].id`), eliminating unnecessary clicks for single-step or linear assembly while still allowing the user to change it.
3. **Edit Mode (`bom/edit.blade.php`):**
   - Automatically initializes `routing_id` with the BOM's existing routing.
   - Pre-populates `availableOperations` on page load so all existing component rows display their saved `routing_operation_id` cleanly without missing labels.

##### C. Routing Form Cleanups (`routing/create.blade.php` & `routing/edit.blade.php`)
1. **Header Cleanup (Finalized):**
   - Remove legacy `bom_id` select dropdown and the "Linked BOM" badge from the header.
2. **Operation Accordion Cleanup (Finalized):**
   - Remove the "Consumed Materials" input sections and the "Live BOM Component Allocation Summary Card".
   - The Routing builder focuses 100% on manufacturing process steps, Work Centers, sequence numbers, setup times, cycle times, SOP instructions, and QC gates.
3. **Routing Show View (`routing/show.blade.php`):**
   - To be finalized in dedicated discussion (displays connected BOMs list, conditional operation consumption, etc.).

#### 3. Files to Modify (During Implementation Batch)
* `resources/views/modules/production/bom/create.blade.php`
* `resources/views/modules/production/bom/edit.blade.php`
* `app/Domains/Production/Controllers/ProductionBomController.php`
* `resources/views/modules/production/routing/create.blade.php`
* `resources/views/modules/production/routing/edit.blade.php`

---

### TRACK-05: Material Allocation & Validation Rules (Single vs. Multi-Operation Split & Zero-Material Operations)
* **Date Logged:** 2026-10-30
* **Status:** 🛠️ **Applied**
* **Area:** Business Rules & Validation Engine

#### 1. Description & Context
Previously, Routing validated that every BOM item was allocated to at least one operation.
Now, the validation rules shift to the **BOM**:
The BOM defines the material components, and every component must be assigned to an operation belonging to the BOM's linked Routing.

#### 2. Agreed Architectural Decisions
1. **Zero-Material Operations Allowed:**
   - Routing operations without any components assigned are explicitly allowed (e.g. Quality Inspection, Heat Treatment, Cooling/Drying, CNC Machining). They consume time and labor/machine cost without raw material consumption.
2. **Mandatory Component Assignment:**
   - Every raw material / component row in the BOM **must** be assigned to a valid operation (`routing_operation_id` is required).
   - Packaging materials are also assigned to a designated packaging operation (packaging is modeled as an operation in the routing).
3. **Multi-Operation Consumption Support:**
   - The backend accepts the same material component consumed across multiple different operations (e.g. 10 fasteners at Op 10, and 10 fasteners at Op 20).
   - In `StoreProductionBomRequest`, the duplicate material prevention rule is refined: a material cannot be duplicated for the *same* operation, but can exist across *distinct* operations.
4. **Operation Ownership Validation:**
   - Every `items.*.routing_operation_id` must exist and belong to the selected `routing_id` (`production_routing_operations.routing_id == bom.routing_id`).

#### 3. Technical Specification for `StoreProductionBomRequest` / `UpdateProductionBomRequest`
* Add rule: `'items.*.routing_operation_id' => 'required|integer|exists:production_routing_operations,id'`.
* Validate that each `routing_operation_id` belongs to the routing selected in `routing_id`.
* Update duplicate check: allow same `material_id` only if `routing_operation_id` is different.

#### 4. Additional Sub-Rules to Clarify

5. **Alternative Components & `routing_operation_id`:**
   - An alternative component (`is_alternative = true`) that replaces a primary component must also carry a `routing_operation_id`.
   - The alternative's `routing_operation_id` should default to the same operation as the primary component it replaces (since it substitutes the same consumption slot).
   - The system does NOT enforce that an alternative must use the same operation as its primary — the engineer may route it differently. However, the field is still mandatory (not nullable) for alternatives.
   - **Implication for `StoreProductionBomRequest`:** Alternative items are NOT excluded from the `routing_operation_id` required rule.

6. **Unassigned components at submission (`pending_approval`) vs. draft save:**
   - At **draft save** (`create()` / `update()`): `routing_operation_id` is validated as required (per TRACK-05 Rule 2 above).
   - Rationale: Allowing nullable at draft level would cause silent data loss when RoutingOperationMaterial sync runs.

7. **`RoutingOperationMaterial` sync on BOM update:**
   - When a BOM is updated (items re-synced), the existing `RoutingOperationMaterial` rows for that `bom_id` must be DELETED and re-inserted fresh.
   - Deletion scope: `RoutingOperationMaterial::where('bom_id', $bom->id)->delete()` before re-insertion.
   - This ensures stale operation-material links don't accumulate when item counts or assignments change.

#### 4. Files to Modify (During Implementation Batch)
* `app/Domains/Production/Requests/StoreProductionBomRequest.php`
* `app/Domains/Production/Requests/UpdateProductionBomRequest.php`
* `app/Domains/Production/Services/ProductionBomService.php`

---

### TRACK-06: Invert Lifecycle & Approval Cascades (Routing Changes Affecting Connected BOMs)
* **Date Logged:** 2026-10-30
* **Status:** 🛠️ **Applied**
* **Area:** Domain Lifecycle & State Machine

#### 1. Description & Context
Currently, state changes cascade from BOM to Routing. In the target architecture, Routing is the primary manufacturing process definition, and BOM recipes depend on it. This track defines the exact state machine rules when a Routing is approved, superseded, or cancelled.

#### 2. Agreed Architectural Decisions
1. **Decoupled Routing Versioning (No Product-Linked Auto-Supersession):**
   - Because a Routing is a generic, reusable engineering process route with NO `product_id`, the system will **NOT** automatically supersede, expire, or inactivate other routings upon approval.
   - Routings maintain uniqueness per tenant via **`[tenant_id, name, version]`** and `routing_number`.
   - When a new Routing is approved, it simply becomes `Active` without automatically retiring any older routing.
   - **Manual Inactivation / Historical Transition:** If an older routing is being replaced, the planner/engineer must **manually** set the older routing to `historical` or `inactive`. If not manually retired, multiple routings can happily stay active in parallel, serving different BOMs simultaneously.
2. **BOM Connection & Active Lifecycle:**
   - Each BOM explicitly stores its chosen `routing_id`.
   - Different BOMs can use different Routings (e.g. BOM A uses "Metal Fab v1.0", and BOM B uses "Metal Fab v2.0").
   - Approving a new Routing never disrupts existing active BOMs.
3. **Manual Routing Inactivation or Cancellation Impact:**
   - If an engineer manually marks a Routing as `inactive`, `historical`, or `cancelled`, connected Draft BOMs display an advisory alert:
     `"The linked routing has become inactive or historical. Please review the BOM before submission."`
   - Existing executed or in-flight production orders continue unaffected using their recorded snapshots.
4. **Approval Dependency Rule:**
   - A Draft BOM can be created with a Draft Routing.
   - A Draft BOM **can be submitted for approval (`pending_approval`)** while its linked Routing is in Draft or Pending Approval.
   - A BOM **cannot be approved** unless its linked Routing is already in `approved` or `active` status.
   - If the connected Routing is **rejected**, any linked pending BOM is **automatically rejected / returned to draft**.
   - Routing approval does **NOT** auto-approve connected BOMs (BOMs require independent review).

#### 3. Technical Specification
* In `RoutingLifecycleService::transitionOnApproval()`:
  - Transition active BOMs linked to the retiring routing version to `inactive` with warning reason.
  - Set warning on draft BOMs referencing the retiring routing version.
* In `RoutingLifecycleService::transitionOnCancellation()`:
  - Transition active BOMs linked to the cancelled routing to `inactive`.
  - Set warning on draft BOMs referencing the cancelled routing.
* In `ProductionBomService::approve()`:
  - Validate: `$routing = $bom->routing; if (!$routing || !in_array($routing->status, ['approved', 'active', 'scheduled'])) throw new InvalidArgumentException("Cannot approve BOM: Linked Routing must be approved and active first.");`

#### 4. Additional Lifecycle Sub-Rules

5. **Routing Rejection Cascade → BOMs:**
   - **Decided:** When a Routing is rejected (returned to `draft` from `pending_approval`), connected **pending-approval BOMs** are also returned to `draft`.
   - Rationale: A BOM that was submitted expecting this Routing to be approved is now blocked (cannot be approved without an approved Routing). Rather than leave it in a permanently blocked `pending_approval` state, automatically revert it to `draft` with a warning note:
     `"Automatically returned to draft because linked Routing (RTG-XXXX) was rejected."`
   - Draft BOMs (not yet submitted) are unaffected — they simply receive the standard draft-routing warning.
   - **Implementation:** In `RoutingLifecycleService` (new method `handleBomsOnRoutingRejection()`) called from `RoutingService::reject()`.

6. **`ProductionBomApproval` Audit Log on Routing-Triggered Inactivation:**
   - When a Routing state change causes a connected BOM to transition to `inactive` (per Rules 2 & 3), a `ProductionBomApproval` record must be created logging the action:
     `action = 'Inactivated'`, `comments = "Automatically inactivated because linked Routing (RTG-XXXX) became [historical/cancelled]."`.
   - This ensures full audit traceability for passive/automated BOM state changes.
   - **Implementation:** Inside the loop in `RoutingLifecycleService::transitionOnApproval()` and `transitionOnCancellation()`.

7. **`RoutingController::create()` / `edit()` — Remove `$boms` variable:**
   - `RoutingController::create()` currently passes `$boms` to the view for the BOM dropdown.
   - After TRACK-03, this variable must be removed from the controller (no BOM dropdown in Routing form).
   - Add to TRACK-03 comment-out list: `RoutingController.php` create() and edit() methods — `$boms` query and view pass.

8. **`BomLifecycleService` — Remove Routing Cascade Calls:**
   - `BomLifecycleService::transitionOnApproval()` currently returns `$deactivatedBomIds` which `ProductionBomService::approve()` passes to `archiveRoutingsForDeactivatedBoms()`.
   - After TRACK-03 comment-out, this return value is unused. The method signature and internal logic remain (it's about BOM-to-BOM lifecycle), just the caller no longer acts on it for Routing archiving.

#### 4. Files to Modify (During Implementation Batch)
* `app/Domains/Production/Services/RoutingLifecycleService.php`
* `app/Domains/Production/Services/BomLifecycleService.php`
* `app/Domains/Production/Services/ProductionBomService.php`
* `app/Domains/Production/Models/ProductionOrder.php` (Warning helper for draft orders)

---

### TRACK-07: Refactor Downstream Consumers (`ProductionOrder`, `ProductionPlan`, Readiness & Costing)
* **Date Logged:** 2026-10-30
* **Status:** ⏸️ **On Hold / Deferred**
* **Area:** Downstream Integration & MES Execution

> **Deferred Note:** As agreed with user directive on 2026-10-31, downstream execution adaptations will be handled at a later stage, strictly after the core BOM and Routing redesign is finalized and verified.

#### 1. Description & Context
Multiple services in the ERP currently read `production_routing_operation_materials`:
* **`ProductionOrderService`**:
  * Line 172: Queries `RoutingOperationMaterial::where('routing_operation_id', ...)` to compute operation target quantities and material scaling.
* **`ProductionReadinessService`**:
  * Lines 309 & 317: Queries `RoutingOperationMaterial` to verify if operations have specific material shortages.
* **`ProductionCostService`**:
  * Calculates material cost from BOM items and labor/machine cost from Routing operations.
* **`ProductionPlanService`**:
  * Verifies BOM and Routing pairings when creating a production plan.

#### 2. Key Refactoring Objectives (When Resumed)
1. In `ProductionOrderService`:
   - Standardize material consumption scaling ratio to `quantity / bom->base_quantity` (since `material_quantity_mode` on Routing is obsolete).
   - Filter `RoutingOperationMaterial` by the active order's `bom_id`.
2. In `ProductionPlanService`:
   - Selecting a BOM automatically defaults the Routing from `$bom->routing_id`.
3. In `ProductionCostService`:
   - Update `$bom->routing` to use the direct `belongsTo` foreign key.

---

### TRACK-08: Clean Database Migrations & Schema Modernization (Zero Legacy Backfill Needed)
* **Date Logged:** 2026-10-30
* **Status:** 🛠️ **Applied**
* **Area:** Database Migrations & Schema Cleanup

#### 1. Description & Context
All existing database records are demo/development data. Therefore, complex historical data-backfill scripts are unnecessary. We will execute clean migrations that add required columns, drop deprecated columns, and maintain strict schema integrity.

#### 2. Agreed Migration Decisions
1. **No Data Backfill Required:**
   - Existing demo data can simply be truncated or refreshed; no backfill script is needed.
2. **Schema Cleanup (`routings` table):**
   - Cleanly drop foreign key and column `bom_id` from `routings`.
   - Cleanly drop column `material_quantity_mode` from `routings`.
3. **Columns to Add:**
   - `production_boms`: Add `routing_id` (`foreignId('routing_id')->constrained('routings')->restrictOnDelete()`).
   - `production_bom_items`: Add `routing_operation_id` (`foreignId('routing_operation_id')->constrained('production_routing_operations')->cascadeOnDelete()`).
   - `production_routing_operation_materials`: Add `bom_id` (`foreignId('bom_id')->nullable()->constrained('production_boms')->cascadeOnDelete()`).
   - *(Note: `production_routing_operation_materials` only has `routing_operation_id`, not `routing_id`, so no redundant routing column exists there).*
4. **Seeders:**
   - Seeder refactoring will be handled at the very end after the backend and frontend changes are completely verified.
5. **Architecture Map Update:**
   - The historical note on `production_boms` in the architecture map (`routing_id was dropped in migration 2026_10_15_130000_drop_routing_id_from_production_boms_table.php`) must be corrected — we are **re-adding** `routing_id` as a mandatory FK (it was dropped previously when the old architecture removed it, and is now being re-introduced under the new direction).
6. **`RoutingController` — `$boms` variable removal:**
   - `RoutingController::create()` and `edit()` currently query and pass `$boms` to the view. This query should be dropped as part of TRACK-08 schema-wide cleanup (no `bom_id` on Routing → no BOM dropdown needed).

#### 3. Files to Modify (During Implementation Batch)
* `database/migrations/xxxx_xx_xx_invert_bom_and_routing_relationships.php`
* Seeders (at the final stage)

---

### TRACK-09: ECO (Engineering Change Order) — BOM & Routing Inversion Compatibility
* **Date Logged:** 2026-10-31
* **Status:** ⏸️ **On Hold / Deferred**
* **Area:** Engineering Change Order domain — `ProductionEcoService`, `ProductionEcoImpactAnalysisService`

> **Deferred Note:** As instructed on 2026-10-31, ECO is a module/feature that will be finalized later on. Currently, no ECO changes will be linked to BOM and Routing, and it is acceptable if the ECO module is left broken or disconnected for now. This track is postponed until after the core BOM/Routing architecture and downstream execution flows are settled.

#### 1. Description & Context
The ECO system manages formal change proposals for BOM and Routing revisions. The ECO data model stores:
* `current_bom_id`, `proposed_bom_id` — current and target BOM versions for a change.
* `current_routing_id`, `proposed_routing_id` — current and target Routing versions for a change.

`ProductionEcoService::release()` invokes:
1. `BomLifecycleService::transitionOnEcoRelease()` — activates the proposed BOM and retires the current one.
2. `RoutingLifecycleService::transitionOnEcoRelease()` — activates the proposed Routing and retires the current one.

#### 2. Scope Policy During Inversion
- **No linking:** No changes will be introduced in BOM or Routing services to accommodate or link to ECO at this stage.
- **Allowed to break:** Any existing ECO flows depending on old columns or lifecycle methods will not be updated or refactored until ECO is revisited as a dedicated initiative.
- All detailed concerns (BOM re-linking, ECO impact analysis, release ordering) are archived for future review when ECO is resumed.

---

### TRACK-10: BOM Duplication & Revision — Clone `routing_operation_id` on Items
* **Date Logged:** 2026-10-31
* **Status:** 🛠️ **Applied**
* **Area:** BOM version management (`ProductionBomService::duplicateVersion()`)

#### 1. Description & Context
`ProductionBomService::duplicateVersion()` (lines 317–416) is the two-pass clone algorithm used when creating a new BOM version or revision. It clones all BOM header fields and all `ProductionBomItem` rows into new records.

**Gap:** The clone loop at lines 369–388 copies all item fields **except `routing_operation_id`** (because the column does not yet exist). After TRACK-02 adds `routing_operation_id` to `production_bom_items`, the clone must also carry over `routing_operation_id`.

#### 2. Agreed Decisions
1. **Clone `routing_operation_id` directly:** When duplicating a BOM, each cloned item inherits the original item's `routing_operation_id`. The operation IDs reference the same Routing operations — this is correct since the cloned BOM still initially points to the same Routing.
2. **Clone `routing_id` on BOM header:** The cloned BOM header must also carry over the parent BOM's `routing_id`. The duplicate BOM starts pointing to the same Routing as the original.
3. **Routing validity check at clone time:** If the original BOM's Routing is now `historical` or `cancelled`, the clone is still created in `draft` with a warning badge (per TRACK-06), so the engineer can re-assign to an active Routing before submitting.
4. **Alternative components' `routing_operation_id`:** Alternative components can also have a `routing_operation_id`. The existing two-pass logic for resolving `replaces_item_id` handles alternatives correctly; `routing_operation_id` is just an additional field cloned in Pass 1 alongside the rest.

#### 3. Files to Modify (During Implementation Batch)
* `app/Domains/Production/Services/ProductionBomService.php` — `duplicateVersion()` lines 369–388: add `'routing_operation_id' => $item->routing_operation_id` and `'routing_id' => $original->routing_id` in the header create.
* `app/Domains/Production/Services/ProductionBomVersionService.php` — review for any independent revision flow (file was empty of relevant content; confirms `ProductionBomService` is the authoritative clone source).

---

### TRACK-11: Bulk Import Service — Invert `RoutingOperationMaterial` Population Source
* **Date Logged:** 2026-10-31
* **Status:** 🛠️ **Applied**
* **Area:** `ProductionMasterImportService` — CSV-based bulk import of Routings and BOMs

#### 1. Description & Context
`ProductionMasterImportService` (921 lines) handles bulk creation of Work Centers, Machines, BOMs, and Routings from uploaded Excel/CSV files.

**Current Routing import flow (lines 703–793):**
* Parses `routing_number`, `name`, `product_id`, `version`, `operations[]`, and `operations[].materials[]`.
* Creates `RoutingOperation` records, then directly creates `RoutingOperationMaterial` records for each material in that operation.
* This is the same pattern that is being decoupled in TRACK-03.

**Current BOM import flow (lines 640–701):**
* Creates `ProductionBom` header, then creates `ProductionBomItem` rows.
* Does **not** accept or store `routing_id` or `routing_operation_id` on items.

#### 2. Agreed Decisions
1. **Routing import stops populating `RoutingOperationMaterial`:**
   - Comment out lines 736–745 (update path) and lines 780–790 (create path) in `ProductionMasterImportService` with marker `// [INVERSION-DECOUPLE-TRACK-11]`.
   - Routing import from CSV will create only `RoutingOperation` records (no materials attached at import time).
2. **BOM import must accept `routing_id` and `routing_operation_id`:**
   - The BOM CSV format needs two new columns: `routing_number` (looked up to resolve `routing_id`) and per-item `operation_number` (looked up to resolve `routing_operation_id`).
   - On import, `ProductionBomItem` records are created with the resolved `routing_operation_id`.
   - After saving BOM items, the sync logic (TRACK-02) is triggered to populate `RoutingOperationMaterial`.
3. **Import validation:**
   - Validate that `routing_number` in the BOM CSV resolves to a valid Routing for the same `product_id`.
   - Validate that each `operation_number` in a BOM item row resolves to an operation belonging to that Routing.
   - If `routing_number` column is absent in the CSV, the import row is flagged as an error (since `routing_id` is now mandatory on BOMs).

> [!NOTE]
> The exact CSV column schema (header names, format) is a UI/import template concern that will be finalized when TRACK-04 and the import UI are revisited. The backend validation rules above are backend-only decisions.

#### 3. Files to Modify (During Implementation Batch)
* `app/Domains/Production/Services/ProductionMasterImportService.php`:
  * Comment out `RoutingOperationMaterial::create()` calls in routing import section (lines ~736–745 and ~780–790) with `// [INVERSION-DECOUPLE-TRACK-11]`.
  * Add `routing_id` and `routing_operation_id` resolution in BOM import section.
  * Add import validation for Routing match and operation ownership.

---

### TRACK-12: Feature Test Suite — Align All Affected Tests with New Architecture
* **Date Logged:** 2026-10-31
* **Status:** 🛠️ **Applied**
* **Area:** `tests/Feature/Production/` — all test files that depend on the old BOM-first / Routing-holds-materials architecture.

#### 1. Description & Context
The existing test suite has multiple test files that were written against the old flow. They will break when backend changes from TRACK-01 through TRACK-08 are applied.

#### 2. Test Files Requiring Updates

| Test File | Why It Will Break | Required Change |
| :--- | :--- | :--- |
| `RoutingMaterialAllocationTest.php` (867 lines, 10 test methods) | Entire test suite validates the old BOM-in-Routing allocation rules (Rule A, B, C, `per_unit`, `per_bom_base_quantity`, foreign material rejection) | Comment out old tests with `// [INVERSION-DECOUPLE-TRACK-12]`; write new tests asserting Routing creates without BOM and BOM holds operation assignments |
| `BomAndRoutingLifecycleDatesTest.php` (1064 lines) | Tests that BOM approval cascades to Routing; tests `syncDraftRoutingsToApprovedBom`; checks `routings.bom_id` FK | Invert lifecycle tests to assert Routing approval cascades to BOM; remove `bom_id` FK references |
| `RoutingDrivenSubcontractingTest.php` | References `bom_id` on Routing during subcontracting routing creation | Update Routing creation fixtures to not require `bom_id` |
| `RoutingParallelDependencyTest.php` | Routing creation fixtures may include `bom_id` | Update fixtures |
| `ProductionBatchRoutingContinuityTest.php` | Batch routing tests may reference `bom_id` or material allocation via Routing | Update fixtures and assertions |
| `BomAlternativeComponentsTest.php` | After TRACK-02, alternative components must also carry `routing_operation_id` | Update test to include `routing_operation_id` in alternative component fixtures |
| `RoutingTest.php` | General routing CRUD tests likely assert `bom_id` presence or material_quantity_mode | Update |
| `MasterDataImportExportTest.php` | Tests Routing CSV import with materials; tests BOM import without `routing_id` | Update CSV fixtures and assertions per TRACK-11 |
| `ComplexIndustrialManufacturingScenarioTest.php` | End-to-end scenario — likely creates Routing-with-BOM and asserts material allocations | Update to Routing-first scenario |
| `TableManufacturingSeederTest.php` | Tests seeder output which will be rewritten at end (TRACK-08) | Defer update until seeders are done |

#### 3. New Tests to Write

1. **Routing standalone approval test:** Assert Routing goes `draft → pending_approval → approved` with zero BOM dependency.
2. **BOM requires Routing test:** Assert BOM creation/approval fails without a valid `routing_id`.
3. **BOM item operation assignment test:** Assert each BOM item must carry a `routing_operation_id` belonging to the BOM's Routing.
4. **Duplicate material same operation blocked:** Assert same `material_id` + same `routing_operation_id` combination is rejected.
5. **Duplicate material different operation allowed:** Assert same `material_id` across two different operations is accepted.
6. **RoutingOperationMaterial sync test:** Assert that saving a BOM correctly populates `production_routing_operation_materials` with `bom_id` set.
7. **Routing inactivation → BOM warning test:** Assert that when a Routing becomes `historical`, linked Draft BOMs get `has_warning = true`.
8. **Routing cancellation → Active BOM inactivation test:** Assert connected Active BOMs become `inactive`.
9. **BOM approval gate test:** Assert BOM cannot be approved when Routing status is `draft` or `pending_approval`.
10. **BOM duplication carries `routing_operation_id`:** Assert `duplicateVersion()` correctly clones `routing_operation_id` on all items.

#### 4. Files to Modify (During Implementation Batch)
* `tests/Feature/Production/RoutingMaterialAllocationTest.php`
* `tests/Feature/Production/BomAndRoutingLifecycleDatesTest.php`
* `tests/Feature/Production/RoutingDrivenSubcontractingTest.php`
* `tests/Feature/Production/RoutingParallelDependencyTest.php`
* `tests/Feature/Production/ProductionBatchRoutingContinuityTest.php`
* `tests/Feature/Production/BomAlternativeComponentsTest.php`
* `tests/Feature/Production/RoutingTest.php`
* `tests/Feature/Production/MasterDataImportExportTest.php`
* `tests/Feature/Production/ComplexIndustrialManufacturingScenarioTest.php`
* New file: `tests/Feature/Production/BomRoutingInversionTest.php` (new test suite)

---

*(End of Feature Tracker — Items will be updated and refined during discussion)*

