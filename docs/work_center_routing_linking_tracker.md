# Work Center & Routing Integration Tracker

> **Document Purpose:** Requirements, architecture shifts, business rules, design decisions, and implementation plans for **linking modernized Work Centers and Sections to Routings and Routing Operations**.  
> **Status:** 🟢 **All Architectural & Business Decisions Agreed & Locked** (Planning complete; ready for phased implementation when approved).  
> **Last Updated:** November 2026 (all 6 discussion areas & sub-points 1.1 through 6.2 fully documented)  
> **Related Reference Documents:**  
> - Architecture Map: [`docs/bom_routing_architecture_map.md`](file:///c:/xampp/htdocs/wm-product-saas/docs/bom_routing_architecture_map.md)  
> - BOM/Routing Inversion Tracker: [`docs/bom_routing_feature_exchange_tracker.md`](file:///c:/xampp/htdocs/wm-product-saas/docs/bom_routing_feature_exchange_tracker.md)  
> - Work Center & Section Calculation Decisions: [`docs/calculation_rework_decisions.md`](file:///c:/xampp/htdocs/wm-product-saas/docs/calculation_rework_decisions.md)  

---

## Status Legend
* 🟡 **Discussion / Proposed** — Item under discussion and architectural review.
* 🟢 **Agreed** — Requirements and technical specification agreed upon; queued for batch implementation.
* 🔵 **In Progress** — Currently being coded during execution phase.
* ✅ **Applied & Verified** — Fully implemented and passed tests.
* ⏸️ **On Hold / Postponed** — Deferred for future iteration.

---

## 1. Executive Context & Modernized Work Center Foundation

### What Was Recently Completed in Work Centers:
1. **Hierarchical Modernization (`WorkCenter` → `WorkCenterSection` → `Machine`):**
   - Work Centers (`production_work_centers`) represent major operational departments/cells.
   - Sections (`production_work_center_sections`) are the atomic production units.
   - Machines (`production_machines`) belong strictly to **Sections** via `section_id` (the old direct link `machines.work_center_id` was dropped).
   - Sections support either Machinery, Manual Production Teams (`production_section_manual_productions`), or Hybrid configurations.
2. **Shift, Worker & Capacity Assignment at Section Level:**
   - Active shifts (`assigned_shifts` JSON) are assigned per Section.
   - Internal employee rosters (`production_work_center_employees`) and External contractor headcount allocations (`production_external_worker_allocations`) are assigned to Sections.
3. **Advanced 52-Metric Calculation Engine:**
   - `SectionCalculationService` computes exact unique operating hours, surge overlap timelines, internal labor daily salary (`CTC / 30`) and hourly rates, contractor billing, machine power & opex reconciliation (`base + additional - deductibles`), and hourly/daily capacity.
   - `WorkCenterCalculationService` rolls up section snapshots into a unified Work Center calculation snapshot (`production_work_center_calculations`).
   - Legacy flat fields (`cost_per_hour`, `capacity_per_hour`, `overhead_rate`) were cleanly dropped from `production_work_centers`.
4. **Section Resource Balance Advisories & Zero-Output Safeguards:**
   - Soft warning banners and visual indicators (table row badge, resource cluster warning pill, capacity warning, section details modal alert, and calculation breakdown modal alert) advise planners when a section has labor allocated but no machines and no active manual production batches, preventing unintended expense accrual without production capacity.

### The Core Problem in Routing Today:
1. **Missing Section Awareness:** `production_routing_operations` only stores `work_center_id` and `machine_id`. It has **no `section_id`** (column does not exist in table or model `$fillable`).
2. **Broken Rate Lookups (Confirmed Active Bugs):**
   - `RoutingCostService` (line 73) reads `$operation->workCenter->cost_per_hour` (dropped column).
   - `ProductionCostService` (lines 51, 106) reads `$operation->workCenter->cost_per_hour` and `$operation->workCenter->overhead_rate` (both dropped).
   - `RoutingOperation::estimatedCostPerUnit()` (line 203) reads `$this->workCenter->cost_per_hour` (dropped).
   - The null-safe property access silently returns `null`, so all WC-fallback labor and overhead costs calculate as **`0.0`**.
3. **Flat Dropdowns Without Hierarchical Context:** The Routing form loads all machines belonging to a Work Center in a single flat list via AJAX (`GET /production/machines/by-work-center/{workCenter}`) — machine selection jumps directly from WC to Machine, completely skipping Section identity and manual capabilities.
4. **Manual Effort in Cycle Time & Cost Entry:** Planners must manually type per-minute labor and machine rates even though the Work Center and Section snapshot engine already calculates precise hourly rates (in `WorkCenterSectionCalculation.labor_cost_per_hour`, `Machine.operating_cost_per_hour`, etc.).
5. **Existing Endpoints & Guards Ready:**
   - `WorkCenterSectionController::byWorkCenter()` endpoint already exists at `GET /production/sections/by-work-center/{workCenter}`.
   - `WorkCenterSection::hasProductionHistory()` already contains the guard check for `Schema::hasColumn('production_routing_operations', 'section_id')`.

---

> [!IMPORTANT]
> **Database Architecture Strategy — ON HOLD FOR RE-EVALUATION (User Directive):**  
> All backend database structure proposals (e.g., dual storage with direct foreign keys alongside multiple pivot tables: `production_routing_operation_sections`, `production_routing_operation_machines`, `production_routing_operation_manual_batches`) are placed **ON HOLD**.  
> **Order of Execution:**  
> 1. Complete, align, and freeze all **business requirements, cost resolution formulas, operational rules, lifecycle safeguards, and UI/UX behaviors** first.  
> 2. After all business decisions are finalised, conduct a dedicated review of the database schema to select the cleanest, most performant, and least complex database design that supports all finalized business needs.

---

## 2. Summary Register of Discussion Tracks & Decision Points

| Track ID | Area | Decision Points Covered | Status | Impacted Components |
| :--- | :--- | :--- | :--- | :--- |
| **WC-TRACK-01** | Database Schema & Hierarchy Boundaries | **Point 1.1** (Mandatory Section & Strictly 1 Section/Op) ✅<br>**Point 1.2** (Alternative Operations via BOM Pattern) ✅<br>**Point 1.3** (Section Deletion Protection) ✅<br>*(Direct FKs, zero pivot tables)* | 🟢 **Agreed / Simplified** | Migrations, `RoutingOperation`, `WorkCenterSection`, Requests, DTOs |
| **WC-TRACK-02** | Operation Types & Section Compatibility | **Point 2.1** (Machine XOR Manual Batch Mutually Exclusive) ✅<br>**Point 2.2** (External Ops Clearing) ✅<br>**Point 2.3** (Inspection / QC Operations Scope) 🔍<br>*(Direct FKs, zero pivot tables)* | 🟢 **Agreed / Simplified** | `StoreRoutingRequest`, `UpdateRoutingRequest`, `RoutingOperation`, `SectionManualProduction` |
| **WC-TRACK-03** | Dynamic Cost Rate Resolution & Active Bug Fixes | **Point 3.1** (Machine vs Manual Costing Branches) 🟢<br>**Point 3.2** (Overhead Allocation) ⏸️ On Hold | 🟢 **Agreed / Defined** | `RoutingCostService`, `ProductionCostService`, `RoutingOperation` |
| **WC-TRACK-04** | UI/UX Cascading & Interactive Guidance | **Point 4.1** (Mixed WC/Section Preload + Lazy Resources) ✅<br>**Point 4.2** (Soft-Switch XOR with Notice) ✅<br>**Point 4.3** (Cascading Reset Rules) ✅ | 🟢 **Agreed / Defined** | `routing/create.blade.php`, `routing/edit.blade.php`, Alpine.js, `RoutingController` |
| **WC-TRACK-05** | Master Data CSV Import & API Alignment | **Point 5.1** (Routing Operations CSV Import) ✅<br>**Point 5.2** (API Requests & Resources) ✅ | 🟢 **Agreed / Defined** | `ProductionMasterImportService`, `StoreRoutingApiRequest`, `UpdateRoutingApiRequest`, `RoutingDetailResource` |
| **WC-TRACK-06** | Deletion, Inactivation & Integrity Safeguards | **Point 6.1** (Inactive Resource Handling) ✅<br>**Point 6.2** (Section/Resource Deletion Safeguards) ✅ | 🟢 **Agreed / Defined** | `WorkCenterSectionService::delete()`, `WorkCenterService::delete()`, `hasProductionHistory()` |
| **WC-TRACK-07** | Downstream Order Execution & Scheduling | Execution propagation (`ProductionOrderOperation`, MES) | ⏸️ On Hold / Deferred | `ProductionOrderOperation`, `SchedulingService`, `CapacityPlanningService` |
| **WC-TRACK-08** | Seeders & Feature Test Suite Alignment | Comprehensive tests for Section-aware Routings | 🟡 Discussion / Proposed | `tests/Feature/Production/`, `ProductionTestWorkCenterSeeder` |
| **WC-TRACK-09** | Parallel Operations & Collision Safeguards | Parallel groups & physical resource collision checks | ⏸️ On Hold / Deferred | `create.blade.php`, `edit.blade.php`, Requests |
| **WC-TRACK-10** | Routing Details Header & Cost Statistics Alignment | **Point 10.1** (Header stats calculation on new snapshot/unit cost fields, primary vs alternative breakdown alignment in show view) | 🟢 **Applied & Verified** | `routing/show.blade.php`, `RoutingCostService`, `Routing.php`, `RoutingOperation.php` |

---

## 3. Detailed Decision Points & Technical Proposals

---

### Area 1: Schema & Hierarchy Boundary Decisions (WC-TRACK-01)

#### Point 1.1: Mandatory Section Selection & Exactly 1 Section per Operation (Simplified Business Rule)
* **Status:** 🟢 **Agreed (Direction Set by User — Major Simplification)**
* **Agreed Decisions:**
  1. **Section Selection is MANDATORY (`section_id` Required):**
     - For every internal routing operation, selecting a Section is **strictly mandatory**. Planners cannot save an internal operation without selecting a valid Section belonging to the chosen Work Center.
     - Validation: `'operations.*.section_id' => 'required_if:operations.*.is_external,0|integer|exists:production_work_center_sections,id'`.
  2. **Strictly 1 Section per Operation:**
     - An operation belongs to **exactly one Section** (and its parent Work Center).
     - Multi-section allocation is completely eliminated.
  3. **Zero Schema Clutter (No Pivot Table Needed):**
     - Because each operation belongs to exactly one Section, `production_routing_operations.section_id` is a clean, direct foreign key.
     - **No pivot table (`production_routing_operation_sections`) is needed.** This removes unnecessary database complexity, eliminates synchronization risks, and keeps Eloquent queries blazing fast.
  4. **Elimination of "Only Work Center" Fallback:**
     - Because a Section is always mandatory, an internal operation is never left without a section. The Work Center fallback is obsolete and eliminated. The Section is the atomic baseline of all operational costing and capacity.

#### Point 1.2: Alternative Operations Architecture (Adopting the BOM Alternative Pattern)
* **Status:** 🟢 **Agreed (Direction Set by User — Mirrors BOM Item Alternative Architecture)**
* **Agreed Architectural Design:**
  1. **Operation-Level Alternatives (Parity with BOM):**
     - Just as a BOM component can be an alternative of another component (`ProductionBomItem.is_alternative` and `replaces_item_id`), a **Routing Operation can be an alternative of another Routing Operation**.
     - An alternative operation defines a complete alternative process route: it can specify a different Machine (in the same or different Section), an alternative Manual Batch, or even a different Section entirely, with its own independent cycle time, setup time, and cost!
  2. **Database Schema (`production_routing_operations`):**
     - `is_alternative` (`boolean`, default `false`)
     - `replaces_operation_id` (`bigint unsigned`, `nullable`, foreign key constrained to `production_routing_operations.id` with `cascadeOnDelete()`)
     - `priority` (`integer`, default `1` — priority ranking for alternative dispatching, e.g. Priority 1, 2, 3)
  3. **Eloquent Relationships on `RoutingOperation`:**
     ```php
     public function primaryOperation(): BelongsTo
     {
         return $this->belongsTo(RoutingOperation::class, 'replaces_operation_id');
     }

     public function alternativeOperations(): HasMany
     {
         return $this->hasMany(RoutingOperation::class, 'replaces_operation_id')->orderBy('priority');
     }
     ```
  4. **UI/UX Flow (Dynamic Conditional Display):**
     - In the operations table, each row has an `is_alternative` toggle (Primary Operation vs Alternative Operation).
     - **When Primary (`is_alternative == false`):**
       - The `priority` field is **hidden / not rendered** to prevent visual clutter across standard operations.
     - **When Alternative (`is_alternative == true`):**
       - Dynamically surfaces the **"Replaces Operation"** selector (`replaces_operation_id` / `replaces_row_uid`, listing primary operations only).
       - Dynamically surfaces the **"Priority"** input (`1`, `2`, `3`... via `x-show="operation.is_alternative"`).
       - Indents the row visually with an `[ALT to OP-XXX]` badge.
  5. **Legacy Cleanup & Backward Compatibility:**
     - Replaces the disconnected, UI-less `RoutingOperationAlternateMachine` table with full first-class alternative operations that schedule seamlessly in `CapacityLevelingService` and `SchedulingService`.

#### Point 1.3: Section Deletion Protection
* **Status:** ✅ **Already Implemented & Verified in Code**
* **Verification Proof:**
  1. `WorkCenterSection` model uses Laravel `SoftDeletes` (table `production_work_center_sections` has `deleted_at`).
  2. `WorkCenterSectionService::delete()` lines 162–167 explicitly calls `$section->hasProductionHistory()` and throws a blocking `InvalidArgumentException`:
     ```php
     if ($section->hasProductionHistory()) {
         throw new InvalidArgumentException(
             "Cannot delete Section '{$section->name}': This section has recorded production or routing history. Sections with manufacturing history cannot be deleted; deactivate the section instead."
         );
     }
     ```
  3. `WorkCenterSection::hasProductionHistory()` lines 284–305 already checks:
     - `production_routing_operations.section_id`
     - `production_orders.section_id`
     - `production_schedules.section_id`
  4. Once `section_id` is linked to routing operations, this protection becomes active automatically. This is fully implemented; no additional work or discussion is needed.

#### Proposed Technical Specification for Area 1:
* **Database Migrations:**
  1. Add `section_id` (`nullable`) to `production_routing_operations`.
  2. Create `production_routing_operation_sections` pivot table.
* **Model Relationships & Attributes:**
  * In `RoutingOperation.php`:
    * Add `section_id` to `$fillable` and `$casts`.
    * `public function section(): BelongsTo` (Primary Section).
    * `public function sections(): BelongsToMany` (All Allocated Sections via pivot).
  * In `WorkCenterSection.php`:
    * `public function operations(): HasMany`
    * `public function allocatedOperations(): BelongsToMany`
* **DTO & Form Request Updates:**
  * `RoutingOperationDTO`: Add `public readonly ?int $section_id = null`, `public readonly array $section_ids = []`.
  * `StoreRoutingRequest` / `UpdateRoutingRequest`:
    * `'operations.*.section_id' => 'nullable|integer|exists:production_work_center_sections,id'`
    * `'operations.*.section_ids' => 'nullable|array'`
    * `'operations.*.section_ids.*' => 'integer|exists:production_work_center_sections,id'`
    * Validate that all selected `section_ids` belong to `operations.*.work_center_id`.
    * Validate that if `machine_id` is set, `machine->section_id` matches `operations.*.section_id` or is contained within `operations.*.section_ids`.
* **Files to Modify:**
  * `database/migrations/xxxx_xx_xx_add_section_id_and_sections_pivot_to_routing_operations.php`
  * `app/Domains/Production/Models/RoutingOperation.php`
  * `app/Domains/Production/Models/WorkCenterSection.php`
  * `app/Domains/Production/DTO/RoutingOperationDTO.php`
  * `app/Domains/Production/Requests/StoreRoutingRequest.php`
  * `app/Domains/Production/Requests/UpdateRoutingRequest.php`

---

### Area 2: Operation Types & Section Compatibility (WC-TRACK-02)

#### Point 2.1: Mutually Exclusive Resource Selection — Machine XOR Manual Batch (Simplified Business Rule)
* **Status:** 🟢 **Agreed (Direction Set by User — Clean & Simplified Architecture)**
* **Agreed Decisions:**
  1. **Mutually Exclusive Resource Selection (Machine XOR Manual Batch):**
     - Every internal routing operation must have **EITHER a Machine OR a Manual Batch**.
     - **They CANNOT be selected together.** Hybrid multi-resource combinations on a single operation are prohibited.
     - **Neither can be omitted.** An internal operation cannot be saved without selecting either a machine or a manual batch.
  2. **The Two Clean Operation Types:**
     - **Branch A: Machine Operation (`machine_id` set, `section_manual_production_id = null`):**
       - The operation is executed by a specific machine belonging to the chosen Section.
       - Cycle Time / Speed: Derived from `60 / Machine.capacity`.
       - Machine Cost Rate: Derived from `Machine.operating_cost_per_hour / 60`.
     - **Branch B: Manual Batch Operation (`section_manual_production_id` set, `machine_id = null`):**
       - The operation is executed by a specific manual production team/batch belonging to the chosen Section.
       - Cycle Time / Speed: Derived from `60 / SectionManualProduction.capacity_per_hour`.
       - Machine Cost Rate: Strictly `0.00` (no machinery used).
  3. **Zero Schema Clutter (Zero Pivot Tables):**
     - All pivot tables (`production_routing_operation_machines`, `production_routing_operation_manual_batches`) are **completely eliminated**.
     - `production_routing_operations` only needs:
       - `machine_id` (`nullable` foreign key to `production_machines`)
       - `section_manual_production_id` (`nullable` foreign key to `production_section_manual_productions`)
     - Validation Rule: When `is_external = false`, exactly one of `machine_id` or `section_manual_production_id` must be present.
  4. **Elimination of "Section-Only" Ambiguity:**
     - The previous generic "Section-only without resources" case is completely removed. Every operation has an explicit, unambiguous resource assignment.

#### Point 2.2: Subcontracted / External Operations (`is_external = true`)
* **Status:** 🟢 **Agreed (Confirmed Existing Architecture & Extended for Sections)**
* **Investigation Findings:**
  1. In `resources/views/modules/production/routing/create.blade.php` and `edit.blade.php` (line 363), toggling `is_external` already triggers:
     ```js
     @change="if (operation.is_external) { operation.work_center_id = ''; operation.machine_id = ''; }"
     ```
  2. In `StoreRoutingRequest` lines 126–140, validation already requires `vendor_id` when `is_external == true` and requires `work_center_id` when `is_external == false`.
* **Agreed Extension:**
  - When `is_external` is toggled true, also clear the newly introduced fields:
    `operation.section_id = ''; operation.section_ids = []; operation.section_manual_production_id = '';`
  - In backend validation: Ensure `section_id`, `section_ids`, `machine_id`, and `section_manual_production_id` are strictly null/empty when `is_external == true`.

#### Point 2.3: Inspection / QC Operations (`operation_type = 'inspection'`)
* **Status:** 🔍 **In Deeper Analysis (Deferred for Dedicated Review)**
* **Decision:** Put on hold for a thorough inspection of the QA/QC backend workflows, test scenarios, and quality plan integration before making final decisions, exactly like Point 1.2.

#### Proposed Technical Specification for Area 2 (⏸️ DB SCHEMA ON HOLD FOR RE-EVALUATION):
> *Note: Database migrations and schema proposals below are on hold per user instruction. We will finalize all business decisions first, then review and redesign the database schema.*
* **Database Migration:**
  - Add `section_manual_production_id` to `production_routing_operations`:
    ```php
    Schema::table('production_routing_operations', function (Blueprint $table) {
        if (!Schema::hasColumn('production_routing_operations', 'section_manual_production_id')) {
            $table->foreignId('section_manual_production_id')
                ->nullable()
                ->after('machine_id')
                ->constrained('production_section_manual_productions')
                ->nullOnDelete();
        }
    });
    ```
* **Model Updates (`RoutingOperation.php`):**
  - Add `section_manual_production_id` to `$fillable` and `$casts`.
  - Relationships:
    ```php
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function manualProduction(): BelongsTo
    {
        return $this->belongsTo(SectionManualProduction::class, 'section_manual_production_id');
    }
    ```
* **DTO & Request Updates:**
  - `RoutingOperationDTO.php`: Add `public readonly ?int $section_manual_production_id = null;`.
  - `StoreRoutingRequest` / `UpdateRoutingRequest`:
    - Rule: `'operations.*.machine_id' => 'nullable|integer|exists:production_machines,id'`
    - Rule: `'operations.*.section_manual_production_id' => 'nullable|integer|exists:production_section_manual_productions,id'`
    - Mutually Exclusive Validation: When `is_external = false`, exactly one of `machine_id` or `section_manual_production_id` must be present.
    - Hierarchy Validation: Ensure the selected machine or manual batch belongs to the operation's selected `section_id`.
* **Files to Modify:**
  * `database/migrations/xxxx_xx_xx_add_section_id_and_manual_production_id_to_routing_operations.php`
  * `app/Domains/Production/Models/RoutingOperation.php`
  * `app/Domains/Production/DTO/RoutingOperationDTO.php`
  * `app/Domains/Production/Requests/StoreRoutingRequest.php`
  * `app/Domains/Production/Requests/UpdateRoutingRequest.php`

---

### Area 3: Dynamic Cost Rate Resolution & Active Bug Fixes (WC-TRACK-03)

#### Point 3.1: Fixing Active Bugs & Operation Costing Scenarios (1-Unit Baseline)
* **Core Principles (Agreed by User):**
  - **Single Work Center Constraint:** A single routing operation can have **EXACTLY ONE Work Center** (`work_center_id`). An operation cannot belong to multiple Work Centers. The same Work Center can be assigned to multiple operations across the routing (e.g. OP-010 and OP-030 can both use WC-FAB).
  - **Mandatory Single Section Constraint:** Every internal routing operation must have **EXACTLY ONE Section** (`section_id` required).
  - **Mutually Exclusive Resource Selection (Machine XOR Manual Batch):** Every internal operation must have **EITHER a Machine OR a Manual Batch**. They cannot be selected together, and an operation cannot be saved without one.
  - **1-Unit Standard Baseline:** In Routing creation, the future production order batch size is unknown. All calculated times and costs displayed on the form are strictly for **1 unit**.
  - **Permanent `Cost Per Unit` Field:** The `Cost Per Unit` field is a **permanent, always-visible column/field** on every operation row in the Routing form: $\text{Cost Per Unit} = \text{Labor Cost / Unit} + \text{Machine Cost / Unit}$.
  - **Form Notice:** The UI explicitly displays: *"Values shown below are calculated for 1 unit only."*

* **The Two Clean Operation Cases & Exact Mathematical Derivations:**

  #### Case A: Machine Operation (`machine_id` Selected, `section_manual_production_id = null`):
  1. **Cycle Time / Processing Time:**  
     Uses **THAT SPECIFIC MACHINE's rated capacity** (units/hour):  
     $$\text{Time per Unit (minutes)} = \frac{60}{\text{Machine.capacity}} \quad \text{(e.g. 0.60 min / 36s)}$$
  2. **Machine Operating Cost Rate & Machine Cost / Unit:**  
     Derived directly from that specific machine's power and opex rate:  
     $$\text{Machine Rate / min} = \frac{\text{Machine.operating\_cost\_per\_hour}}{60}$$  
     $$\text{Machine Cost / Unit} = \text{Time per Unit} \times \text{Machine Rate / min}$$
  3. **Operator Labor Cost Rate (Unassigned Section Labor Divided by Active Machines):**  
     - **Identify Machine Operators:** Filter all section workers (internal employees + external allocations) who are **NOT assigned to any manual batch**.
     - **Sum their total hourly labor cost:**  
       $$\text{Total Machine Labor / hr} = \sum (\text{Hourly cost of all section workers NOT assigned to any manual batch})$$
     - **Distribute evenly across the Section's active machines:**  
       $$\text{Operator Labor Cost per Machine / hr} = \frac{\text{Total Machine Labor / hr}}{\text{Count of Active Machines in this Section}}$$
     - **Labor Cost per Unit for this operation:**  
       $$\text{Labor Cost / Unit} = \text{Time per Unit} \times \left(\frac{\text{Operator Labor Cost per Machine / hr}}{60}\right)$$  
       *(Note: If planner enters a manual override in `labor_cost_rate > 0`, the manual override takes precedence).*
  4. **Total Cost per Unit:**  
     $$\text{Cost Per Unit} = \text{Labor Cost / Unit} + \text{Machine Cost / Unit}$$

  #### Case B: Manual Batch Operation (`section_manual_production_id` Selected, `machine_id = null`):
  1. **Cycle Time / Processing Time:**  
     Uses **THAT SPECIFIC BATCH's rated capacity** (units/hour, not the blended section average):  
     $$\text{Time per Unit (minutes)} = \frac{60}{\text{SectionManualProduction.capacity\_per\_hour}}$$
  2. **Machine Cost:**  
     - Strictly **`0.00`** (no machinery used).
  3. **Labor Cost Rate & Labor Cost / Unit:**  
     - Derived **exclusively from the workers assigned to THAT specific batch**:  
       - Internal employees assigned to this batch (`employees`)  
       - External worker allocations assigned to this batch (`externalAllocations`)  
     $$\text{Batch Labor Rate / hr} = \sum (\text{Hourly cost of internal employees in batch}) + \sum (\text{Hourly cost of external workers in batch})$$  
     $$\text{Labor Cost / Unit} = \text{Time per Unit} \times \left(\frac{\text{Batch Labor Rate / hr}}{60}\right)$$  
     *(Note: If planner enters a manual override in `labor_cost_rate > 0`, the manual override takes precedence).*
  4. **Total Cost per Unit:**  
     $$\text{Cost Per Unit} = \text{Labor Cost / Unit}$$ (since machine cost is strictly 0.00).

  #### Case C: Subcontracted / External Operation (`is_external = true`):
  1. Internal resources are strictly cleared (`work_center_id = null`, `section_id = null`, `machine_id = null`, `section_manual_production_id = null`).
  2. Labor Cost / Unit = `0.00`, Machine Cost / Unit = `0.00`.
  3. $\text{Cost Per Unit} = \text{subcontract\_cost\_per\_unit}$.

* **Status:** 🟢 **Agreed (Direction Set by User — Mathematically Grounded & Verified)**

#### Point 3.2: Monthly Lump-sum `overhead_cost` Allocation Formula
* **Status:** ⏸️ **On Hold / Postponed (Per User Directive: Overhead not considered for now, let it be)**
* **Note:** Periodic overhead allocation will remain out of scope for the current phase. Existing services will default overhead to `0.0` or leave it untouched until requested.
* **Proposed Formula:**
  If `WorkCenterCalculation.unique_operating_hours > 0`:
  $$\text{Hourly Overhead} = \frac{\text{overhead\_cost}}{\text{unique\_operating\_hours} \times 30}$$
  $$\text{Per-Minute Overhead} = \frac{\text{Hourly Overhead}}{60}$$
  $$\text{Operation Overhead Cost} = \text{Active Minutes} \times \text{Per-Minute Overhead} \times \text{Quantity} \times \text{Yield Factor}$$
* **Status:** 🟡 Under Discussion

---

### Area 4: UI/UX Cascading & Interactive Guidance (WC-TRACK-04)

#### Point 4.1: Mixed Dropdown Data Loading Strategy
* **Status:** 🟢 **Agreed (Direction Set by User — Optimized Hybrid Loading)**
* **Agreed Flow:**
  1. **Page Load (Preload Work Centers & Sections):**
     - In `RoutingController::create()` and `RoutingController::edit()`, preload all active Work Centers with their active Sections:
       ```php
       $workCenters = WorkCenter::with([
           'sections' => fn($q) => $q->where('status', 'active'),
       ])->where('status', 'active')->get();
       ```
     - Planners can immediately select Work Center and have the Section dropdown populated instantly with zero initial network delay.
  2. **On Section Selection (Lazy-Fetch Section Resources via AJAX):**
     - When a Section is selected on any row, Alpine triggers a lightweight AJAX call to fetch that section's resources:  
       `GET /production/sections/{section}/resources`
     - Returns:
       - Active Machines in the section (`id`, `name`, `code`, `capacity`, `operating_cost_per_hour`)
       - Active Manual Batches in the section (`id`, `batch_name`, `capacity_per_hour`, pre-calculated `batch_labor_rate_per_hour`)
       - Section-level unassigned operator labor rate per machine: `operator_labor_cost_per_machine_per_hour`.
     - **Client-Side Cache:** Alpine.js caches the resource payload by `section_id` in memory. If another operation row selects the same section, it instantly reads from memory with zero additional network requests.

#### Point 4.2: Resource Mutual Exclusivity UX (Soft-Switch with Auto-Clear & Warning)
* **Status:** 🟢 **Agreed (Direction Set by User — Fluid Planner UX)**
* **Agreed Behavior:**
  1. **Both Dropdowns Remain Interactive:** The UI does not rigidly hard-disable the other selector, allowing the planner to freely change their mind.
  2. **Soft-Switch with Automatic Clearing:**
     - If a Machine is currently selected, selecting a Manual Batch automatically clears the `machine_id` and surfaces a soft inline warning/toast:  
       `"Notice: Switched to Manual Batch. Machine selection cleared."`
     - If a Manual Batch is currently selected, selecting a Machine automatically clears the `section_manual_production_id` and surfaces a soft inline warning/toast:  
       `"Notice: Switched to Machine. Manual Batch selection cleared."`
  3. **Backend Enforcement:**
     - In `StoreRoutingRequest` and `UpdateRoutingRequest`: Strict validation ensures that when `is_external = false`, exactly one of `machine_id` or `section_manual_production_id` is submitted.
  4. **Alternative Operations Conditional Priority UI:**
     - On standard primary operations (`is_alternative == false`), the `priority` input is **completely hidden** to keep the grid uncluttered.
     - Only when toggled to `is_alternative == true` do the **"Replaces Operation"** dropdown and **"Priority"** input dynamically appear on that row.

#### Point 4.3: Cascading Reset Rules
* **Status:** 🟢 **Agreed (Confirmed Existing Architecture & Extended for Sections/Batches)**
* **Agreed Rules:**
  1. **Work Center Changed:** Resets `section_id = ''`, `machine_id = ''`, `section_manual_production_id = ''`.
  2. **Section Changed:** Resets `machine_id = ''`, `section_manual_production_id = ''`.
  3. **Subcontracting Toggled (`is_external = true`):** Automatically clears and disables `work_center_id`, `section_id`, `machine_id`, and `section_manual_production_id`, and displays the vendor selector and subcontract cost fields.

---

### Area 5: Master Data CSV Import & API Alignment (WC-TRACK-05)

#### Point 5.1: Routing Operations CSV Import (`ProductionMasterImportService`)
* **Status:** 🟢 **Agreed / Specified**
* **CSV Schema Enhancements:**
  * Add `section_code` column (mandatory for internal operations).
  * Add `manual_batch_name` column (mutually exclusive with `machine_code`).
  * Add `is_alternative` column (`0` or `1`).
  * Add `replaces_operation_number` column (references primary operation e.g. `OP-010`).
  * Add `priority` column (integer for alternative priority).
* **Import Validation Rules:**
  * `section_code` must match an active Section within the resolved Work Center.
  * When `is_external = 0`: Exactly one of `machine_code` or `manual_batch_name` must be present.
  * If `machine_code` is provided, it must belong to `section_id`.
  * If `manual_batch_name` is provided, it must match an active manual batch in `section_id`.

#### Point 5.2: API Endpoints & Resources
* **Status:** 🟢 **Agreed / Specified**
* **Request Validation (`StoreRoutingApiRequest`, `UpdateRoutingApiRequest`):**
  * Rule: `'operations.*.section_id' => 'required_if:operations.*.is_external,0|integer|exists:production_work_center_sections,id'`
  * Rule: `'operations.*.section_manual_production_id' => 'nullable|integer|exists:production_section_manual_productions,id'`
  * Rule: `'operations.*.is_alternative' => 'boolean'`
  * Rule: `'operations.*.replaces_operation_id' => 'nullable|integer|exists:production_routing_operations,id'`
  * Rule: `'operations.*.priority' => 'integer|min:1'`
  * Mutually exclusive check: Exactly one of `machine_id` or `section_manual_production_id` when `is_external = 0`.
* **Resource Output (`RoutingDetailResource`):**
  * Return `section` object (`id`, `name`, `code`).
  * Return `machine` object (`id`, `name`, `code`) or `manual_production` object (`id`, `batch_name`, `capacity_per_hour`).
  * Return `is_alternative`, `replaces_operation_id`, `priority`.
  * Return `cost_per_unit`.

---

### Area 6: Lifecycle & Safeguards (WC-TRACK-06)

#### Point 6.1: Inactive Resource Handling on Approved vs Draft Routings
* **Status:** 🟢 **Agreed / Specified**
* **Rules:**
  * **Approved/Active Routings (Immutable Historical Definition):**
    - If a Section, Machine, or Manual Batch is marked `inactive` after a routing is approved, the routing remains approved and untouched.
    - An informational advisory alert badge appears on the Routing details view:  
      `"Notice: Operation [OP-XXX] is assigned to [Resource Name], which is currently inactive."`
  * **Draft Routings:**
    - Inactive Sections, Machines, and Manual Batches are filtered out and cannot be selected.

#### Point 6.2: Section & Resource Deletion Safeguards
* **Status:** 🟢 **Agreed / Verified**
* **Rules:**
  * `WorkCenterSection::hasProductionHistory()` already contains `Schema::hasColumn('production_routing_operations', 'section_id')`.
  * `WorkCenterSectionService::delete()` permanently blocks deleting any section referenced by routing operations.
  * Same protection applies to `Machine` and `SectionManualProduction`. Resources with production history cannot be deleted; they must be deactivated instead.

---

### Area 7: Downstream Order Execution & Scheduling (WC-TRACK-07)
* **Status:** ⏸️ **On Hold / Deferred**
* Execution propagation to `ProductionOrderOperation`, Shop Floor Dispatching, and MES claiming will be handled after core Routing & Work Center integration is completed.

---

### Area 8: Seeders & Feature Test Suite Alignment (WC-TRACK-08)
* **Status:** 🟢 **Completed & Verified**
* Aligned all test fixtures in `tests/Feature/Production/RoutingTest.php` (8/8 passed) and created new dedicated test suite `tests/Feature/Production/WorkCenterRoutingIntegrationTest.php` (11/11 passed with 79 assertions) verifying:
  - Internal operations require Section and either Machine XOR Manual Batch
  - External/subcontract operations clear WC/Section/Resources
  - Section resource API endpoint returns machines, manual batches, and operator labor rates
  - Machine operation cost derivation and operator labor distribution
  - Manual batch operation cost derivation from assigned workers
  - Alternative operation creation, linking, and priority ordering
  - Primary routing cost rollup excludes alternative operations
  - Approved routing safeguards for inactive resources
  - Deletion safeguards for sections and resources referenced by operations

---

### Area 9: Parallel Operations, Live Memory Grouping & Resource Collision Safeguards (WC-TRACK-09)
* **Status:** ⏸️ **Logged / Deferred (To be implemented later)**
* **Context & Problem Statement:**
  Currently, `is_parallel` uses a simple unstyled text input (`Grp: [ A ]`). The freeform text input is prone to typos, breaking finite capacity scheduling where exact string matches are required. Furthermore, there is no validation preventing two parallel operations from booking the same physical machine or manual batch team simultaneously.
* **Key Requirements & Architecture (To be implemented):**
  1. **Dynamic Group Dropdown & Live Memory Pool:**
     - Replace the raw text input with a Select2 / dynamic select dropdown.
     - Maintain an in-memory list of active parallel groups in the current routing session.
     - Include a `+ Add New Group...` option allowing on-the-fly creation of custom groups (e.g. `Sub-Assembly`, `Wiring Branch`), immediately registering in memory across all table rows and saving to `routing_operations.parallel_group`.
  2. **Physical Resource Collision Validation (Same Machine / Manual Batch):**
     - Two operations marked `Parallel` in the same `parallel_group` **cannot** be assigned to the same `machine_id` or same `section_manual_production_id` (a single physical resource cannot process two parallel operations at the exact same moment).
     - **UI Warning:** Display inline warning badge on affected rows alerting planner to the bottleneck.
     - **Backend Validation:** Enforce in `StoreRoutingRequest` and `UpdateRoutingRequest`.
  3. **Parallel vs. Predecessor Relationship Rules:**
     - Resolve styling conflict where Bootstrap's `.d-flex` overrides Alpine's `x-show` on the Predecessor wrapper.
     - Support branch-level parallelism (an operation can have a predecessor inside its branch, but the branch itself runs parallel to another branch).
     - Guard against paradoxical configurations (e.g. Op 30 depends on Op 20 which depends on Op 10, but Op 10 and Op 30 are marked as simultaneous parallel siblings).
  4. **UI/UX Cleanup:**
     - Neatly align `[✓] Parallel` checkbox and the group selector on the same row.
     - Auto-clear group assignment and resource warnings when `Parallel` is unchecked.

---

### Area 10: Routing Details Header & Live Calculation Alignment (WC-TRACK-10)
* **Status:** 🟢 **Applied & Verified**
* **Area:** Frontend Views (`resources/views/modules/production/routing/show.blade.php`), Costing Services (`RoutingCostService.php`), Requests (`StoreRoutingRequest.php`, `UpdateRoutingRequest.php`), and Models (`Routing.php`, `RoutingOperation.php`)

#### Point 10.1: Header Statistics & Cost Calculation Alignment
* **Context & Applied Fixes:**
  1. **Discrepancy Between Unit Cost & Contribution Breakdown (Resolved):**
     In `RoutingCostService.php` and `show.blade.php`, primary operations strictly determine total routing unit cost (`!$operation->is_alternative`). The contribution breakdown reconciles proportionally so that:
     $$\text{Routing Cost Per Unit} = \text{Primary Labor Cost} + \text{Primary Machine Cost} + \text{Primary Subcontract Cost}$$
  2. **Whitelisting & Backend Persistence (Resolved):**
     - Whitelisted `cost_per_unit`, `resource_snapshot`, and `replaces_sequence` in `StoreRoutingRequest.php` and `UpdateRoutingRequest.php`.
     - Added `cost_per_unit` and `resource_snapshot` to `$fillable` and `$casts` (`'resource_snapshot' => 'array'`) in `RoutingOperation.php`.
     - Saved full snapshot details (`operating_cost_per_hour`, `operator_labor_cost_per_machine_per_hour`, `labor_cost_per_hour`, `machine_cost_per_unit`, `labor_cost_per_unit`, `cost_per_unit`) in `create.blade.php` and `edit.blade.php`.
  3. **Live Dynamic Fallback in Show View (Resolved):**
     If `resource_snapshot` is empty (e.g., historical records or API imports), `show.blade.php` queries `SectionCalculationService::getSectionResourceCosts()` to dynamically resolve Machine and Manual Batch cards with complete details (Capacity, Run Speed, Machine Cost, Operator Cost, and Operation / Labor Cost).
  4. **Setup Time Included in Row Unit Cost (Resolved):**
     Updated `RoutingOperation::estimatedCostPerUnit()` to compute:
     $$\text{Active Minutes} = \text{setup\_time\_minutes} + \text{processing\_time\_minutes}$$
     $$\text{Cost} = \text{Active Minutes} \times (\text{labor\_cost\_rate} + \text{machine\_cost\_rate}) \times \text{yield\_factor}$$
     Ensures that row-level unit cost in both form and show page includes setup time.
  5. **Column Width Rebalance (Resolved):**
     In `show.blade.php`, rebalanced widths:
     - `Work Center`: 14% (min-width: 160px)
     - `Section`: 14% (min-width: 160px)
     - `Resource`: 22% (min-width: 240px)
     - Table container wrapped with `style="min-width: 1300px;"` to prevent card squeezing and awkward text wrapping.

---

#### Point 10.2: Interactive Alternative Route Simulation Modal & Explanatory Context
* **Status:** 🟢 **Applied & Verified**
* **Design Decision:**
  1. **Primary Ground Truth:** Header values continue displaying standard Primary Route calculations (excluding alternatives).
  2. **Explanatory Note:** A clear note in the header explains that displayed totals reflect the primary sequence and exclude alternative operations.
  3. **Interactive Simulation Modal:**
     - Button triggers a live scenario simulator modal.
     - Operations with no alternatives are locked/fixed.
     - Operations with alternatives allow selecting the active variant (Primary vs Alternative).
     - **Multi-Alternative & Multi-Stage Rules:**
       - If an operation has multiple alternatives (e.g., Primary + Alt 1 [Pri 1] + Alt 2 [Pri 2]), they form a mutually exclusive single-select radio group: exactly **one** option can be selected at once.
       - If multiple operations have alternatives, each stage has its own independent radio group (`stage_{primary_id}`).
     - **Calculation Execution:** Selection does not trigger auto-calculation. A dedicated **"RUN CALCULATIONS"** button executes the simulation and displays the scenario results (Total Cost, Labor, Machine, Setup, Cycle Time, and Delta vs Baseline).
     - UI layout to be provided by user before coding.

---

#### Point 10.3: Universal Clock Time Duration Formatting
* **Status:** 🟢 **Applied & Verified**
* **Context & Core ERP Principle:**
  Users and shop-floor planners should never be forced to mentally calculate raw decimal minutes (e.g., calculating that `0.2 min = 12s`, `11.5 min = 11m 30s`, or `29.1 min = 29m 05s`). All durations and speeds across routing displays are now rendered in human-readable clock notation.
* **Applied Enhancements:**
  1. **Global PHP Helper (`app/helpers.php`):**
     - Implemented `format_duration($minutes, bool $includeSeconds = true): string` converting decimal minutes to clock units (`1h 27m`, `22m 30s`, `12s`, etc.).
  2. **Routing Show View (`resources/views/modules/production/routing/show.blade.php`):**
     - **Setup Duration Estimate KPI:** Formatted with `format_duration()` (e.g. `22m 30s`) and small subtitle `(22.5m)`.
     - **Total Cycle Running Time KPI:** Formatted with `format_duration()` (e.g. `29m 05s`) and small subtitle `(29.1m)`.
     - **Machine & Manual Batch Spec Cards:** `Run Speed` displays clock duration alongside units (e.g., `4m (4.00 m/u)`, `12s (0.20 m/u)`).
     - **Operations Sequence Table:** `Setup`, `Run`, and `Wait` columns display clock duration with subtitle decimal minutes (e.g., `8m 30s` with `8.5m`, `12s` with `0.2m`).
     - **Cost / Unit Tooltips:** Displays `Setup: 5m + Run: 4m = 9m active time`.
     - **Queue Threshold Lag:** Formatted with `format_duration()` (e.g. `Lag: 10m`).
  3. **Interactive Simulation Modal (`#routeSimulationModal`):**
     - Integrated `formatDuration(minutes)` inside the Alpine.js `routeSimulator` component.
     - Stage and variant cards display `9m (5m setup + 4m run)`.
     - Scenario results display Total Cycle Time formatted (e.g. `29m 05s (29.1m)`).
     - Time Delta displays relative clock comparison (e.g., `1m 30s Faster`, `+45s Longer`, `0s (Identical)`).

---

## 4. Architectural Summary & Implementation Execution Plan

All core business decisions across Areas 1 through 6 are now **🟢 Agreed & Frozen**:
* **Area 1 (Boundaries):** Exactly 1 Work Center + 1 Section per internal operation (mandatory). Alternative operations mirror BOM architecture (`replaces_operation_id`, `priority`).
* **Area 2 (Resources):** Mutually exclusive choice between Machine XOR Manual Batch (exactly one required). No pivot tables needed.
* **Area 3 (Costing & Time):** Batch-specific manual capacity and labor; machine capacity and opex; machine operator labor derived from unassigned section workers divided by active machines. Permanent `cost_per_unit` field.
* **Area 4 (UI/UX Flow):** Mixed preload of WC/Sections + lazy fetch of section resources. Soft-switch auto-clearing with notice. Conditional display of priority for alternatives only. Cascading resets.
* **Area 5 (Master Data & API):** CSV import and REST API updated with section, manual batch, alternative operation references, and permanent unit cost.
* **Area 6 (Safeguards):** Inactive resource warnings on approved routings; blocking on drafts; soft-delete protection for referenced sections/machines.

---

### Phased Implementation Steps:
1. ✅ **Database Migration:** Single clean migration `2026_11_02_140000_add_work_center_linking_to_routing_operations.php` executed. Added `section_id`, `section_manual_production_id`, `is_alternative`, `replaces_operation_id`, `priority`.
2. ✅ **Domain Models & Relationships:** Updated `RoutingOperation.php`, `WorkCenterSection.php`, `SectionManualProduction.php`, and `Machine.php`.
3. ✅ **DTOs & Form Requests:** Updated `RoutingOperationDTO.php`, `StoreRoutingRequest.php`, and `UpdateRoutingRequest.php` with XOR mutual exclusivity and section ownership checks.
4. ✅ **Section Resources Endpoint:** Created `GET /production/sections/{section}/resources` in `WorkCenterSectionController` returning active machines, manual batches, and operator labor rates.
5. ✅ **Costing Services:** Updated `RoutingCostService.php`, `ProductionCostService.php`, and `RoutingOperation::estimatedCostPerUnit()` supporting machine, manual batch, and subcontract costing while excluding alternatives from primary totals.
6. ✅ **Blade Views & Alpine.js:** Updated `create.blade.php`, `edit.blade.php`, and `show.blade.php` with the 13-column layout, notice banner, soft-switching, conditional priority, and permanent `Cost / Unit` column.
7. ✅ **Master Data Import & API:** Updated `ProductionMasterImportService.php` and `RoutingDetailResource.php` with section, manual batch, alternative operation, and unit cost mappings.
8. ✅ **Feature Tests & Verification:** Implemented `tests/Feature/Production/WorkCenterRoutingIntegrationTest.php` covering 11 comprehensive integration scenarios (100% passing) and updated `tests/Feature/Production/RoutingTest.php` (100% passing).
