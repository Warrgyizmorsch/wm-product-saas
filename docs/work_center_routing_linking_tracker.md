# Work Center & Routing Integration Tracker

> **Document Purpose:** Requirements, architecture shifts, business rules, design decisions, and implementation plans for **linking modernized Work Centers and Sections to Routings and Routing Operations**.  
> **Status:** 🟡 **Discussion & Planning Phase** (No code changes implemented until architectural consensus is reached).  
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
| **WC-TRACK-01** | Database Schema & Hierarchy Boundaries | **Point 1.1** (Optional + Tooltip + Multi-Section) ✅<br>**Point 1.2** (Alternate Machines Scope) 🔍<br>**Point 1.3** (Section Deletion Protection) ✅<br>*(DB Schema on hold for re-evaluation)* | ⏸️ **Business Agreed / DB Schema On Hold** | Migrations, `RoutingOperation`, `WorkCenterSection`, Requests, DTOs |
| **WC-TRACK-02** | Operation Types & Section Compatibility | **Point 2.1** (Manual Batch Column + Machine + Hybrid + Multi-Resource) ✅<br>**Point 2.2** (External Ops Clearing) ✅<br>**Point 2.3** (Inspection / QC Operations Scope) 🔍<br>*(DB Schema on hold for re-evaluation)* | ⏸️ **Business Agreed / DB Schema On Hold** | `StoreRoutingRequest`, `UpdateRoutingRequest`, `RoutingOperation`, `SectionManualProduction` |
| **WC-TRACK-03** | Dynamic Cost Rate Resolution & Active Bug Fixes | **Point 3.1** (Labor & Machine Cost Hierarchy)<br>**Point 3.2** (Lump-sum Overhead Allocation) | 🟡 Discussion / Proposed | `RoutingCostService`, `ProductionCostService`, `RoutingOperation` |
| **WC-TRACK-04** | UI/UX Cascading & Interactive Guidance | **Point 4.1** (Preloaded Matrix vs AJAX)<br>**Point 4.2** (Cascading Reset Rules)<br>**Point 4.3** (Rated Speed / Capacity Helper) | 🟡 Discussion / Proposed | `routing/create.blade.php`, `routing/edit.blade.php`, Alpine.js, `RoutingController` |
| **WC-TRACK-05** | Master Data CSV Import & API Alignment | **Point 5.1** (Routing Operations CSV Import)<br>**Point 5.2** (API Requests & Resources) | 🟡 Discussion / Proposed | `ProductionMasterImportService`, `StoreRoutingApiRequest`, `UpdateRoutingApiRequest`, `RoutingDetailResource` |
| **WC-TRACK-06** | Deletion, Inactivation & Integrity Safeguards | **Point 6.1** (Inactive Section Handling)<br>**Point 6.2** (Section Deletion Safeguards) | 🟡 Discussion / Proposed | `WorkCenterSectionService::delete()`, `WorkCenterService::delete()`, `hasProductionHistory()` |
| **WC-TRACK-07** | Downstream Order Execution & Scheduling | Execution propagation (`ProductionOrderOperation`, MES) | ⏸️ On Hold / Deferred | `ProductionOrderOperation`, `SchedulingService`, `CapacityPlanningService` |
| **WC-TRACK-08** | Seeders & Feature Test Suite Alignment | Comprehensive tests for Section-aware Routings | 🟡 Discussion / Proposed | `tests/Feature/Production/`, `ProductionTestWorkCenterSeeder` |

---

## 3. Detailed Decision Points & Technical Proposals

---

### Area 1: Schema & Hierarchy Boundary Decisions (WC-TRACK-01)

#### Point 1.1: Optional Section Selection, Work Center Fallback Tooltip & Multi-Section Allocation
* **Status:** 🟢 **Agreed (Direction Set by User)**
* **Agreed Decisions:**
  1. **Frontend `section_id` is OPTIONAL:**
     - The section selector is not required. Planners can leave it empty.
  2. **All-Sections Warning / Tool-tip:**
     - When no section is selected, the UI displays an informational badge/tooltip:  
       `"Notice: No section selected. It will be assumed that all sections in this work center are required for this operation."`
     - When no section is selected, rate and capacity resolution defaults to the parent **Work Center Calculation snapshot** (`WorkCenterCalculation`), which already computes the aggregate daily capacity and labor rates across all sections.
  3. **Multi-Section Allocation Allowed:**
     - A single operation can be allocated to **multiple sections** within the selected Work Center (e.g. an assembly op requiring Sub-Assembly Bay 1 + Main Assembly Bay 2).
     - **Database Architecture Proposal (⏸️ ON HOLD FOR RE-EVALUATION):**
       > *Note: All backend DB schema options below are on hold per user instruction. We will finalize all business decisions first, then review and redesign the database schema.*
       - Keep `production_routing_operations.section_id` (`nullable`, foreign key) as the **primary section** for quick lookups and single-section backward compatibility.
       - Introduce a dedicated pivot table: **`production_routing_operation_sections`**
         ```php
         Schema::create('production_routing_operation_sections', function (Blueprint $table) {
             $table->id();
             $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
             $table->foreignId('routing_operation_id')->constrained('production_routing_operations')->cascadeOnDelete();
             $table->foreignId('section_id')->constrained('production_work_center_sections')->cascadeOnDelete();
             $table->boolean('is_primary')->default(false);
             $table->timestamps();
             $table->unique(['routing_operation_id', 'section_id'], 'op_section_unique');
         });
         ```
       - Relationship: `RoutingOperation::sections(): BelongsToMany(WorkCenterSection::class, 'production_routing_operation_sections')`.
       - If multiple sections are assigned, machines from any of those assigned sections can be selected for the operation.

#### Point 1.2: Alternate Machines (`RoutingOperationAlternateMachine`) — Deeper Analysis
* **Status:** 🔍 **In Deeper Analysis (Deferred for Dedicated Review)**
* **Codebase Investigation Findings:**
  1. `RoutingOperationAlternateMachine` is **NOT currently exposed on the frontend** in `create.blade.php`, `edit.blade.php`, `StoreRoutingRequest`, or `UpdateRoutingRequest`.
  2. It is populated solely via production seeders (`TableManufacturingProductionSeeder`, `CrusherMachineProductionSeeder`, `FurnitureProductionSeeder`, `PoonaRadiatorsProductionSeeder`, `ProductionVariationDemoSeeder`).
  3. It is consumed by advanced scheduling services (`CapacityLevelingService`, `CapacityPlanningService`, `SchedulingService`, `PlanningExceptionService`, `ProductionScheduleScenarioService`).
* **Decision:** Keep alternate machines scoped to backend/scheduling for now. We will conduct a dedicated deep-dive architectural review for exposing alternate machines on the frontend once the primary Work Center $\rightarrow$ Section $\rightarrow$ Machine linking is complete and verified.

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

#### Point 2.1: Manual Batch Selection Column, Multiple Machine & Manual Batch Allocations, and Hybrid Resources
* **Status:** 🟢 **Agreed (Direction Set by User with Multi-Resource Clarification)**
* **Agreed Decisions:**
  1. **Manual Batch Selection Column in Operations Grid:**
     - In the operations grid, provide a dedicated selector for **Manual Production Batch** (`section_manual_production_id`) alongside the **Machine** selector.
  2. **Multi-Machine & Multi-Manual Batch Support:**
     - A single operation can have **multiple machines** selected (e.g. tandem stamping presses, cutting station with auxiliary trimmer).
     - A single operation can have **multiple manual production batches** selected (e.g. multiple bench assembly crews working together).
     - **Database Architecture Proposal (⏸️ ON HOLD FOR RE-EVALUATION):**
       > *Note: Database schema options below are on hold per user directive. We will finalize all business decisions first, then review and redesign the database schema.*
       - **Primary Direct Columns on `production_routing_operations`:**
         - `machine_id` (`nullable` foreign key) $\rightarrow$ primary machine (for single-machine setups, backward compatibility, and primary display).
         - `section_manual_production_id` (`nullable` foreign key) $\rightarrow$ primary manual batch.
       - **Pivot Tables for Multi-Resource Allocation:**
         - `production_routing_operation_machines`:  
           `['id', 'tenant_id', 'routing_operation_id', 'machine_id', 'is_primary', 'timestamps']`
         - `production_routing_operation_manual_batches`:  
           `['id', 'tenant_id', 'routing_operation_id', 'section_manual_production_id', 'is_primary', 'timestamps']`
  3. **Flexible Resource Combinations (Pure vs Hybrid):**
     - An operation can have:
       - **Machine Only (One or Multiple):** (`machine_ids` set, no manual batches) $\rightarrow$ automated machine processing.
       - **Manual Batch Only (One or Multiple):** (no machines, `manual_batch_ids` set) $\rightarrow$ purely manual bench team processing.
       - **Both (Hybrid):** (machines AND manual batches set) $\rightarrow$ hybrid operation (e.g. machine stamping with dedicated manual deburring/loading crews).
  4. **Resource Fallback & Advisory Tooltip:**
     - If **neither** a machine nor a manual batch is selected (both empty):
       - The UI displays the consistent advisory warning / tool-tip:  
         `"Notice: No specific machine or manual batch selected. It is assumed that all resources of the designated section/work center are utilized for this operation."`
       - This avoids unnecessary ambiguity, leaves the user in full control, and applies the full capacity/labor snapshot.
  5. **Cost Rate Resolution for Multi-Resources:**
     - **Machine Cost Rate:** When multiple machines are assigned, the combined machine operating cost per hour is the sum of `operating_cost_per_hour` across all selected machines (converted to per-minute rate by dividing by 60).
     - **Manual Labor Rate:** When multiple manual batches are assigned, the labor cost combines the teams across those batches.
     - **Fallback:** If neither is selected, rates fallback to Section / Work Center calculation snapshot rates.

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
  - Create `production_routing_operation_machines` pivot table for multi-machine allocation:
    ```php
    Schema::create('production_routing_operation_machines', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
        $table->foreignId('routing_operation_id')->constrained('production_routing_operations')->cascadeOnDelete();
        $table->foreignId('machine_id')->constrained('production_machines')->cascadeOnDelete();
        $table->boolean('is_primary')->default(false);
        $table->timestamps();
        $table->unique(['routing_operation_id', 'machine_id'], 'op_machine_unique');
    });
    ```
  - Create `production_routing_operation_manual_batches` pivot table for multi-manual batch allocation:
    ```php
    Schema::create('production_routing_operation_manual_batches', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
        $table->foreignId('routing_operation_id')->constrained('production_routing_operations')->cascadeOnDelete();
        $table->foreignId('section_manual_production_id')->constrained('production_section_manual_productions')->cascadeOnDelete();
        $table->boolean('is_primary')->default(false);
        $table->timestamps();
        $table->unique(['routing_operation_id', 'section_manual_production_id'], 'op_manual_batch_unique');
    });
    ```
* **Model Updates (`RoutingOperation.php`):**
  - Relationships:
    ```php
    public function primaryMachine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function machines(): BelongsToMany
    {
        return $this->belongsToMany(Machine::class, 'production_routing_operation_machines', 'routing_operation_id', 'machine_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function primaryManualProduction(): BelongsTo
    {
        return $this->belongsTo(SectionManualProduction::class, 'section_manual_production_id');
    }

    public function manualProductions(): BelongsToMany
    {
        return $this->belongsToMany(SectionManualProduction::class, 'production_routing_operation_manual_batches', 'routing_operation_id', 'section_manual_production_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
    ```
* **DTO & Request Updates:**
  - `RoutingOperationDTO.php`:
    - Add `public readonly ?int $section_manual_production_id = null;`
    - Add `public readonly array $machine_ids = [];`
    - Add `public readonly array $section_manual_production_ids = [];`
  - `StoreRoutingRequest` / `UpdateRoutingRequest`:
    - Rule: `'operations.*.machine_ids' => 'nullable|array'`
    - Rule: `'operations.*.machine_ids.*' => 'integer|exists:production_machines,id'`
    - Rule: `'operations.*.section_manual_production_ids' => 'nullable|array'`
    - Rule: `'operations.*.section_manual_production_ids.*' => 'integer|exists:production_section_manual_productions,id'`
    - Validation: Ensure all selected machines and manual batches belong to the operation's selected section(s) (or work center if no section selected).
* **Files to Modify:**
  * `database/migrations/xxxx_xx_xx_add_section_manual_production_and_resource_pivots_to_routing_operations.php`
  * `app/Domains/Production/Models/RoutingOperation.php`
  * `app/Domains/Production/DTO/RoutingOperationDTO.php`
  * `app/Domains/Production/Requests/StoreRoutingRequest.php`
  * `app/Domains/Production/Requests/UpdateRoutingRequest.php`

---

### Area 3: Dynamic Cost Rate Resolution & Active Bug Fixes (WC-TRACK-03)

#### Point 3.1: Fixing Active Bugs & Labor/Machine Resolution Hierarchy
* **Problem Context:** In the Work Center rework, `cost_per_hour` and `overhead_rate` were cleanly dropped from `production_work_centers`. The existing costing services still read these dropped fields:
  * `RoutingCostService::calculateOperationCost()` (line 73) $\rightarrow$ reads `$operation->workCenter->cost_per_hour` (returns `null` $\rightarrow$ `0.0`).
  * `ProductionCostService::calculateLaborCost()` (line 51) $\rightarrow$ reads `$operation->workCenter->cost_per_hour` (returns `null` $\rightarrow$ `0.0`).
  * `ProductionCostService::calculateOverheadCost()` (line 106) $\rightarrow$ reads `$operation->workCenter->overhead_rate` (returns `null` $\rightarrow$ `0.0`).
  * `RoutingOperation::estimatedCostPerUnit()` (line 203) $\rightarrow$ reads `$this->workCenter->cost_per_hour`.
* **Proposed Labor Rate per Minute Resolution Hierarchy:**
  1. **Level 1 (Manual Override):** If `operations.*.labor_cost_rate > 0`, use the planner-entered rate.
  2. **Level 2 (Section Calculation Snapshot):** If operation has `section_id` and `$operation->section?->latestCalculation`:
     Rate per minute = `$section->latestCalculation->labor_cost_per_hour / 60.0`.
  3. **Level 3 (Work Center Calculation Snapshot):** If section snapshot missing but `$operation->workCenter?->latestCalculation`:
     Rate per minute = `$workCenter->latestCalculation->labor_cost_per_hour / 60.0`.
  4. **Level 4 (Default):** `0.00`.
* **Proposed Machine Rate per Minute Resolution Hierarchy:**
  1. **Level 1 (Manual Override):** If `operations.*.machine_cost_rate > 0`, use the planner-entered rate.
  2. **Level 2 (Assigned Machine(s) Opex):**
     - If multiple machines assigned: Rate per minute = $\sum(\text{machine.operating\_cost\_per\_hour}) / 60.0$.
     - If single machine assigned (`machine_id`): Rate per minute = `$operation->machine->operating_cost_per_hour / 60.0`.
  3. **Level 3 (Section Machine Opex Snapshot Fallback):** If no specific machine is assigned but the section runs machines:
     Rate per minute = `$section->latestCalculation->machine_cost_per_hour / 60.0`.
  4. **Level 4 (Work Center Machine Opex Fallback):**
     Rate per minute = `$workCenter->latestCalculation->machine_cost_per_hour / 60.0`.
  5. **Level 5 (Default / Pure Manual):** `0.00`.
* **Status:** 🟡 Under Discussion

#### Point 3.2: Monthly Lump-sum `overhead_cost` Allocation Formula
* **Problem Context:** `WorkCenter.overhead_cost` is a monthly/periodic lump sum (e.g. ₹50,000/month rent/utilities), not a per-minute rate.
* **Proposed Formula:**
  If `WorkCenterCalculation.unique_operating_hours > 0`:
  $$\text{Hourly Overhead} = \frac{\text{overhead\_cost}}{\text{unique\_operating\_hours} \times 30}$$
  $$\text{Per-Minute Overhead} = \frac{\text{Hourly Overhead}}{60}$$
  $$\text{Operation Overhead Cost} = \text{Active Minutes} \times \text{Per-Minute Overhead} \times \text{Quantity} \times \text{Yield Factor}$$
* **Status:** 🟡 Under Discussion

---

### Area 4: UI/UX Cascading & Interactive Guidance (WC-TRACK-04)

#### Point 4.1: Dropdown Hierarchy Population — Preloaded Matrix vs AJAX
* **Options:**
  * **Option A (Client-Side Preloaded Hierarchy Matrix — Recommended):**
    * `RoutingController::create()` and `RoutingController::edit()` query active Work Centers with eager loaded active Sections and active Machines:
      ```php
      $workCenterHierarchy = WorkCenter::with([
          'sections' => fn($q) => $q->where('status', 'active')->with([
              'machines' => fn($mq) => $mq->where('status', 'active'),
              'manualProductions' => fn($mpq) => $mpq->where('active', true),
              'latestCalculation',
          ]),
          'latestCalculation',
      ])->where('status', 'active')->get();
      ```
    * Pass `$workCenterHierarchy` as JSON to the Alpine.js component.
    * **Pros:** Instant, zero latency, no race conditions when adding multiple operations quickly, Alpine can auto-calculate placeholders for labor and machine rates immediately on selection.
  * **Option B (Asynchronous AJAX Endpoints):**
    * Step 1: Work Center change calls `GET /production/sections/by-work-center/{workCenter}` (already exists).
    * Step 2: Section change calls a new `GET /production/machines/by-section/{sectionId}` endpoint.
    * **Cons:** Slower table editing, network spinners in dynamic grid.
* **Current Recommendation:** **Option A**.
* **Status:** 🟡 Under Discussion

#### Point 4.2: Cascading Reset Rules on Form
* **Rules:**
  * When a user changes `Work Center`: `section_id` and `machine_id` automatically reset to empty.
  * When a user changes `Section`: `machine_id` resets to empty; labor rate placeholder updates to Section's calculated rate.
  * When a user selects a `Machine`: Machine rate placeholder updates to `Machine.operating_cost_per_hour / 60`.
  * When user toggles `is_external` (subcontracting): WC, Section, and Machine fields are cleared and disabled.
* **Status:** 🟡 Under Discussion

#### Point 4.3: Rated Machine Speed / Capacity Helper
* **Feature:** When an engineer selects a machine with a rated capacity (e.g. 120 units/hour), render a clickable badge below processing time:
  `"Rated Speed: 120 u/h (~0.50 min/unit)"` $\rightarrow$ clicking auto-fills `processing_time_minutes = 0.50`.
* **Status:** 🟡 Under Discussion

---

### Area 5: Master Data CSV Import & API Alignment (WC-TRACK-05)

#### Point 5.1: Routing Operations CSV Import (`ProductionMasterImportService`)
* **Problem Context:** `ProductionMasterImportService` currently imports routing operations with `work_center_id` and machines directly.
* **Recommendation:**
  * Add `section_code` column to the CSV template for Routing Operations.
  * On import, resolve `section_id` by matching `section_code` within the resolved Work Center.
  * Validate that if a `machine_code` is provided in the CSV, the machine belongs to that `section_id`.
* **Status:** 🟡 Under Discussion

#### Point 5.2: API Endpoints & Resources
* **Recommendation:**
  * Update `StoreRoutingApiRequest` and `UpdateRoutingApiRequest` to accept and validate `operations.*.section_id`.
  * Update `RoutingDetailResource` to return `section` details (`id`, `name`, `code`, `supports_manual_production`).
* **Status:** 🟡 Under Discussion

---

### Area 6: Lifecycle & Safeguards (WC-TRACK-06)

#### Point 6.1: Inactive Section Handling on Approved vs Draft Routings
* **Rules:**
  * **Approved/Active Routings:** If a Section is marked `inactive` after a routing is approved, the routing remains valid (immutable historical definition), but displays an informational alert:
    `"Notice: Operation OP-020 is assigned to Section [Name], which is currently inactive."`
  * **Draft Routings:** Inactive Sections cannot be chosen in new or edited operations.
* **Status:** 🟡 Under Discussion

#### Point 6.2: Section Deletion Block via `WorkCenterSectionService::delete()` and `hasProductionHistory()`
* **Rules:**
  * `WorkCenterSection::hasProductionHistory()` already contains `Schema::hasColumn('production_routing_operations', 'section_id')`.
  * Once `section_id` is linked to routing operations, `WorkCenterSectionService::delete()` will permanently block deleting any section that has attached routing operations.
* **Status:** 🟡 Under Discussion

---

### Area 7: Downstream Order Execution & Scheduling (WC-TRACK-07)
* **Status:** ⏸️ **On Hold / Deferred**
* Execution propagation to `ProductionOrderOperation`, Shop Floor Dispatching, and MES claiming will be handled after core Routing & Work Center integration is completed.

---

### Area 8: Seeders & Feature Test Suite Alignment (WC-TRACK-08)
* **Status:** 🟡 **Discussion / Proposed**
* Align all test fixtures in `tests/Feature/Production/RoutingTest.php`, `BomRoutingInversionTest.php`, and create new `WorkCenterRoutingIntegrationTest.php` linking operations to realistic sections from `ProductionTestWorkCenterSeeder`.

---

## 4. Next Steps: Point-by-Point Decision Walkthrough

We are now ready to walk through and freeze each point sequentially:
1. **Decision on Point 1.1:** Mandatory vs. Optional `section_id` for internal operations.
2. **Decision on Point 1.2:** Scope of Alternate Machines (Same Section vs. Across Sections).
3. **Decision on Point 1.3:** Foreign Key Constraint & Deletion Guard.
4. *(Followed by Area 2, Area 3, Area 4, Area 5, Area 6)*.
