# Production Module — Technical Architecture & Engineering Standards

> **Domain Location:** `app/Domains/Production`  
> **Documentation Root:** `app/Domains/Production/docs/ARCHITECTURE.md`  
> **Architectural Pattern:** Domain-Driven Design (DDD) with 4-Layer Architecture & Repository Pattern

---

## 1. Domain Architecture Overview

The Production module is built strictly upon a decoupled 4-layer architecture. Business logic, calculations, algorithm orchestration, and transactional integrity reside exclusively in the **Service Layer**. Persistence is encapsulated behind **Repositories**, while **Controllers** act purely as HTTP traffic coordinators, FormRequest orchestrators, and authorization gates.

```mermaid
graph TD
    subgraph Presentation_Layer["1. Controller Layer (app/Domains/Production/Controllers)"]
        C1["ProductionOrderController"]
        C2["ProductionScheduleController"]
        C3["MesController"]
        C4["ProductionBomController"]
        C5["WipController"]
    end

    subgraph Business_Layer["2. Domain Service Layer (app/Domains/Production/Services)"]
        S1["ProductionOrderService"]
        S2["SchedulingService"]
        S3["MesExecutionService"]
        S4["ProductionWipService"]
        S5["QualityInspectionService"]
        S6["CapacityPlanningService"]
    end

    subgraph Repository_Layer["3. Repository Layer (app/Domains/Production/Repositories)"]
        R1["ProductionOrderRepositoryInterface"]
        R2["ProductionScheduleRepositoryInterface"]
        R3["ProductionWipRepositoryInterface"]
        R4["ProductionBomRepositoryInterface"]
        RI1["ProductionOrderRepository (Eloquent)"]
        RI2["ProductionScheduleRepository (Eloquent)"]
        RI3["ProductionWipRepository (Eloquent)"]
        RI4["ProductionBomRepository (Eloquent)"]
    end

    subgraph Data_Layer["4. Data & Persistence Layer (app/Domains/Production/Models)"]
        M1["ProductionOrder"]
        M2["ProductionSchedule"]
        M3["ProductionWip"]
        M4["ProductionBom"]
        DB[("MySQL Database - 71 Scoped Tables")]
    end

    C1 --> S1
    C2 --> S2 & S6
    C3 --> S3
    C4 --> S1
    C5 --> S4
    
    S1 --> R1
    S2 --> R2
    S3 --> R2 & R3
    S4 --> R3
    
    R1 -.-> RI1
    R2 -.-> RI2
    R3 -.-> RI3
    R4 -.-> RI4
    
    RI1 --> M1
    RI2 --> M2
    RI3 --> M3
    RI4 --> M4
    
    M1 & M2 & M3 & M4 --> DB
```

---

## 2. The 4 Layers & Responsibilities

### Layer 1: Controllers (`Controllers/`)
- **Permitted Responsibilities:**
  - Route binding and HTTP method coordination.
  - FormRequest validation triggers (`StoreProductionOrderRequest`, `MesCompleteOperationRequest`).
  - Fine-grained Gate authorization checks (`Gate::authorize('create', ProductionOrder::class)`).
  - Calling corresponding Domain Service methods.
  - Returning Blade views, JSON payloads, or HTTP redirects.
- **Strictly Forbidden:**
  - Direct Eloquent queries (`ProductionOrder::where(...)`).
  - Direct persistence calls (`Model::create()`, `$model->save()`).
  - Multi-step business transactions (`DB::transaction(...)` belongs in Services).
  - Business calculations (MRP explosion, leveling, WIP math).

### Layer 2: Domain Services (`Services/`)
- **Permitted Responsibilities:**
  - Encapsulating all domain workflow logic, status transitions, and scheduling algorithms.
  - Enforcing database transaction boundaries (`DB::transaction(function() { ... })`).
  - Orchestrating multi-model interactions (e.g., updating `ProductionOrder`, generating `ProductionWip`, emitting timeline events).
  - Performing cross-domain integration calls (Inventory `StockService`, Purchase PR/PO orchestration).
  - Emitting domain timeline events via `ProductionEventService`.

### Layer 3: Repositories (`Repositories/`)
- **Structure:**
  - Located in a **flat directory**: `app/Domains/Production/Repositories/`.
  - Pair of `*RepositoryInterface.php` and `*Repository.php`.
  - Registered exclusively in `app/Providers/AppServiceProvider.php`.
- **Responsibilities:**
  - Encapsulating raw Eloquent queries, pagination, eager loading, and filtering.
  - Managing pessimistic database locking helpers (`lockForUpdate()`).
  - Providing reusable aggregate query methods scoped by tenant.

### Layer 4: Eloquent Models (`Models/`)
- **Responsibilities:**
  - Representing database tables with typed `$casts`, `$fillable`, and relationships (`hasMany`, `belongsTo`).
  - Enforcing automatic multi-tenant scoping via `BelongsToTenant` trait.
  - Providing domain helper methods (e.g. `$order->isDraft()`, `$schedule->isFrozen()`).

---

## 3. Multi-Tenancy & Tenant Isolation

Tenant isolation is strictly enforced at every boundary:
1. **Global Scope:** Models implement `App\Models\Concerns\BelongsToTenant`, applying a global query scope `where('tenant_id', $currentTenantId)` to every query automatically.
2. **Database Schema:** Every single Production table (`71` tables) includes an indexed `tenant_id unsignedBigInteger` column with foreign key cascade to `tenants.id`.
3. **Route & Middleware:** Incoming requests traverse `App\Http\Middleware\ResolveTenant`, resolving tenant from hostname, custom domain, or local fallback header (`config('tenancy.local_fallback_slug')`).
4. **Service Context:** Methods requiring tenant ID accept `$tenantId = require_tenant_id();` and explicitly bind it on model instantiation.

---

## 4. Immutable Snapshot Architecture

A foundational architectural requirement in industrial manufacturing is that **work in progress must never be corrupted by ongoing engineering changes**. If an engineer modifies a BOM or changes machine cycle times on a Routing, active shopfloor jobs must proceed under their original approved engineering baseline.

The system solves this via the **Immutable Snapshot Architecture**:

```mermaid
sequenceDiagram
    participant Engineer as Product Engineer
    participant Master as Master Data (BOM & Routing)
    participant Service as ProductionOrderService
    participant Order as ProductionOrder
    participant Snapshot as Snapshot Tables (Order Operations & Reservations)

    Engineer->>Master: Creates/Revises Master BOM & Routing
    Service->>Master: Read active approved BOM & Routing
    Service->>Order: Create ProductionOrder header
    Service->>Snapshot: Clone BOM items -> ProductionOrderReservation
    Service->>Snapshot: Clone Routing steps -> ProductionOrderOperation
    Service->>Order: Freeze engineering references
    Note over Snapshot: Production order now operates entirely<br/>on frozen snapshot tables.
    Engineer->>Master: Updates Master BOM component qty
    Note over Snapshot: Active ProductionOrder is UNAFFECTED.<br/>Reservations & Operations remain frozen!
```

---

## 5. Event Bus & Audit Event Timeline

Production execution is fully tracked via a dedicated operational event bus:
- **Service:** `App\Domains\Production\Services\ProductionEventService`
- **Model:** `App\Domains\Production\Models\ProductionEventTimeline`
- **Events Tracked:**
  - `Order Created`, `Material Reserved`, `Order Released`
  - `Schedule Generated`, `Schedule Levelled`, `Schedule Released`
  - `Operation Started`, `Operation Paused`, `Operation Completed`
  - `QC Inspection Recorded`, `Operational Scrap Recorded`, `Rework Order Created`
  - `Finished Goods Received`

Every event captures `tenant_id`, `production_order_id`, `event_type`, `title`, `description`, `severity` (`info`, `warning`, `danger`, `success`), `event_source`, and `triggered_by` (user ID).

---

## 6. Cross-Domain Integrations

```mermaid
graph LR
    subgraph Production_Domain["Production Domain (app/Domains/Production)"]
        POS["ProductionOrderService"]
        MES["MesExecutionService"]
        WIP["ProductionWipService"]
        SUB["SubcontractProcurementOrchestrator"]
    end

    subgraph Inventory_Domain["Inventory Domain (app/Domains/Inventory)"]
        SS["StockService"]
        PWS["ProductWarehouseStock"]
        ST["StockTransaction"]
    end

    subgraph Purchase_Domain["Purchase Domain (app/Domains/Purchase)"]
        PR["PurchaseRequisition"]
        PO["PurchaseOrder"]
    end

    subgraph Quality_Domain["Quality Domain (app/Domains/Production/Quality)"]
        QI["QualityInspectionService"]
        NCR["NcrService"]
        CAPA["CapaService"]
    end

    POS -- "Raw Material Reservation" --> SS
    WIP -- "Finished Goods Receipt" --> SS
    MES -- "Operational Scrap Outflow" --> SS
    SS --> ST & PWS
    
    SUB -- "External Operation PR/PO" --> PR & PO
    
    MES -- "Run QC" --> QI
    QI -- "Defect Rejection" --> NCR
    NCR -- "Root Cause Analysis" --> CAPA
```
