# Production Module — API Architecture Audit & Specification

**Date**: 2026-09-22  
**Status**: Phase 1 — Read-Only Architecture Audit  
**Module**: Production Planning & MES (`app/Domains/Production`)  
**Target**: Versioned REST API Layer (`/api/v1/production/...`)

---

## 1. Executive Summary

The **Production Planning and MES Module** in this multi-tenant SaaS ERP is a mature, feature-complete domain encompassing Bill of Materials (BOM), Work Centers, Machines, Routings, Production Planning & MRP, Production Orders, Work-in-Progress (WIP) Tracking, Advanced MES (Shop Floor Execution), Quality Control (Inspections, NCR, CAPA), Plant Maintenance, Subcontracting, and Manufacturing Intelligence.

This audit establishes the architectural foundation for a **production-grade, versioned REST API (`v1`)**. The API layer is designed as an **additive access interface** to the existing domain services and repositories. In accordance with the **Golden Rule** of `PRODUCTION_MODULE_STANDARDS.md`, the API layer will **NOT duplicate business logic, alter database schemas, change model event hooks, or bypass domain state machines**.

---

## 2. Current Architecture Overview

### 2.1 Multi-Layer Architecture
The existing Production module strictly follows a 4-layer architecture:
```text
Controller (HTTP / Presentation)
    ↓
Service (Business Logic, Transactions, State Transitions, Events)
    ↓
Repository Interface (Aggregate Data Access Contract)
    ↓
Repository Implementation (Query Builders, Scopes, Pessimistic Locking)
    ↓
Eloquent Model / BaseModel (Tenant Global Scope, Events, Casts)
    ↓
Database (SQLite in Test, MySQL in Production)
```

### 2.2 Key Domain Inventory
* **Models**: 71 Eloquent Models extending `App\Core\Database\BaseModel` (which implements `BelongsToTenant`).
* **Services**: 76 specialized Domain Services in `app/Domains/Production/Services/` (e.g., `ProductionOrderService`, `ProductionBomService`, `RoutingService`, `ProductionPlanService`, `MesExecutionService`, `QualityInspectionService`, `SchedulingService`, `DowntimeService`).
* **Repositories**: 14 Domain Repositories with interfaces bound in `App\Providers\AppServiceProvider::register()`.
* **Policies**: 13 Gate Policies in `app/Domains/Production/Policies/` registered in `AppServiceProvider::boot()`.
* **Form Requests**: 46 Form Requests in `app/Domains/Production/Requests/`.
* **Controllers**: 54 Web Controllers in `app/Domains/Production/Controllers/`.

---

## 3. Authentication & Security Gate Analysis

### 3.1 Existing Token Authentication
* **Provider**: Laravel Sanctum (`auth:sanctum`).
* **User Provider**: `App\Support\Auth\TenantAwareUserProvider` registered in `AppServiceProvider`.
* **Token Issuance**: `LoginController::apiLogin` (`POST /api/auth/login`) issues Sanctum personal access tokens (`$user->createToken('api-token')->plainTextToken`).
* **Revocation**: `LoginController::apiLogout` (`POST /api/auth/logout`) deletes `$request->user()->currentAccessToken()`.
* **Verdict**: Sanctum is established and functioning. No custom or redundant token system should be introduced.

### 3.2 Mandatory API Secret Key Gate
* Currently, no API Secret Key middleware exists in the application.
* **Architecture Decision**:
  * Implement `App\Http\Middleware\ProductionApiSecretMiddleware`.
  * Header name: `X-API-SECRET`.
  * Config key: `config('production.api_secret')` mapped to `env('PRODUCTION_API_SECRET')`.
  * Comparison: Constant-time string comparison (`hash_equals`) to prevent timing attacks.
  * Rejection: `401 Unauthorized` with generic response `{ "success": false, "message": "API access denied." }` without revealing internal configuration.
  * Scope: Prepended to all `/api/v1/production/*` routes before user authentication.

### 3.3 Access Flow Pipeline
```text
HTTP Request
    │
    ▼
[ Rate Limiter: throttle:production-api ]
    │
    ▼
[ Secret Key: ProductionApiSecretMiddleware (X-API-SECRET) ]
    │
    ▼
[ Tenant Resolution: ResolveTenant (X-Tenant or Domain) ]
    │
    ▼
[ Bearer Token Auth: auth:sanctum ]
    │
    ▼
[ Tenant Context Validation: BelongsToTenant & require_tenant_id() ]
    │
    ▼
[ RBAC Authorization: Gate::authorize via AccessService ]
    │
    ▼
[ API Controller & Form Request Validation ]
    │
    ▼
[ Existing Production Domain Service ]
    │
    ▼
[ Eloquent Models & Database ]
    │
    ▼
[ API Resource Transformation (Strict Payload) ]
    │
    ▼
HTTP JSON Response
```

---

## 4. Multi-Tenancy Architecture Analysis

### 4.1 Tenant Isolation Mechanism
1. **Tenant Resolution**: Handled by `App\Http\Middleware\ResolveTenant`.
   - Resolution order: `X-Tenant` header (`config('tenancy.header')`) -> Session `tenant_slug` -> Host domain/subdomain -> Local fallback slug.
   - For mobile and external APIs, `X-Tenant` header or authenticated user's `tenant_id` provides the authoritative tenant context.
2. **Context Singletons**: Bound as singletons `App\Core\Tenant\TenantContext` and `App\Support\Tenancy`.
3. **Database Scoping**:
   - `BaseModel` incorporates `App\Models\Concerns\BelongsToTenant`.
   - Query global scope: `static::addGlobalScope('tenant', fn ($q) => $q->where($table . '.tenant_id', require_tenant_id()));`.
   - Auto-filling: On `creating`, `tenant_id` is automatically set to `require_tenant_id()`.
4. **API Safety Assurance**:
   - The API will never accept an arbitrary `tenant_id` in request payloads.
   - The authenticated user's tenant or verified `X-Tenant` header is enforced across every query.
   - Cross-tenant lookups trigger `404 Not Found` (via tenant global scope) or `403 Forbidden` (via policy checks).

---

## 5. Authorization & RBAC Analysis

### 5.1 Existing RBAC System
* The application uses a custom high-performance RBAC layer (`App\Services\Access\AccessService`).
* Policy models implement `HasProductionPermissions` trait on `User`.
* Policies registered in `AppServiceProvider`:
  * `ProductionBomPolicy`
  * `WorkCenterPolicy`
  * `MachinePolicy`
  * `RoutingPolicy`
  * `ProductionPlanPolicy`
  * `ProductionOrderPolicy`
  * `AdvancedMesPolicy`
  * `QualityManagementPolicy`
  * `ProductionSchedulePolicy`
  * `DeliveryChallanPolicy`
  * `ProductionCostAdjustmentPolicy`
* **Permission Naming Standard**:
  * `production.bom.view`, `production.bom.create`, `production.bom.approve`
  * `production.work_center.manage`, `production.machine.manage`
  * `production.routing.view`, `production.routing.create`, `production.routing.approve`
  * `production.order.view`, `production.order.create`, `production.order.release`, `production.order.complete`
  * `production.mes.execute`
  * `production.quality.inspect`, `production.quality.approve`
* **API Rule**: Controllers will invoke existing policies using `Gate::authorize()` or `$this->authorize()`. No custom API permissions will be invented.

---

## 6. Business Logic Preservation (Anti-Duplication Strategy)

To ensure zero business logic duplication:

| Functional Area | Existing Service Reused by API | Existing Repository Reused |
| :--- | :--- | :--- |
| **BOM Management** | `ProductionBomService`, `BomExplosionService` | `ProductionBomRepositoryInterface` |
| **Work Centers & Machines** | `WorkCenterService`, `MachineService` | `WorkCenterRepositoryInterface`, `MachineRepositoryInterface` |
| **Routings** | `RoutingService`, `RoutingCostService` | `RoutingRepositoryInterface` |
| **Production Plans** | `ProductionPlanService`, `MrpEngineService` | `ProductionPlanRepositoryInterface` |
| **Production Orders** | `ProductionOrderService`, `ProductionExecutionService`, `ProductionMaterialService` | `ProductionOrderRepositoryInterface` |
| **MES Operations** | `MesExecutionService`, `OperatorAssignmentService` | `ProductionBatchRepositoryInterface`, `ProductionOrderRepositoryInterface` |
| **Quality Control** | `QualityInspectionService`, `NcrService`, `CapaService`, `ReworkService`, `ScrapService` | `ProductionQualityRepositoryInterface` |
| **Downtime & OEE** | `DowntimeService`, `MachineStateService` | `MaintenanceRepositoryInterface`, `MachineRepositoryInterface` |
| **Shopfloor Batches** | `BatchProductionService`, `SerialNumberService` | `ProductionBatchRepositoryInterface` |

State transitions (e.g. `release`, `start`, `pause`, `complete`, `approve`) will call direct domain service methods rather than altering status attributes via generic updates.

---

## 7. Endpoint Coverage Matrix

The versioned REST API (`/api/v1/production`) focuses on core business capabilities required by Mobile MES clients, ERP dashboards, and external integrations:

| Category | Endpoint | Method | Action | Auth / Permission | Existing Service |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Dashboard** | `/dashboard` | GET | Operational KPI Summary | Secret + Bearer (`production.order.view`) | `ProductionDashboardController` / `KpiCalculationService` |
| **BOM** | `/boms` | GET | List BOMs (Paginated, Filtered) | Secret + Bearer (`production.bom.view`) | `ProductionBomRepository` |
| **BOM** | `/boms/{id}` | GET | BOM Details & Items | Secret + Bearer (`production.bom.view`) | `ProductionBomRepository` |
| **BOM** | `/boms` | POST | Create BOM Draft | Secret + Bearer (`production.bom.create`) | `ProductionBomService` |
| **BOM** | `/boms/{id}` | PUT | Update BOM Draft | Secret + Bearer (`production.bom.update`) | `ProductionBomService` |
| **BOM** | `/boms/{id}/submit` | POST | Submit BOM for Approval | Secret + Bearer (`production.bom.create`) | `ProductionBomService` |
| **BOM** | `/boms/{id}/approve` | POST | Approve BOM | Secret + Bearer (`production.bom.approve`) | `ProductionBomService` |
| **BOM** | `/boms/{id}/duplicate` | POST | Create Revision / Clone | Secret + Bearer (`production.bom.create`) | `ProductionBomService` |
| **Work Centers**| `/work-centers` | GET | List Work Centers | Secret + Bearer (`production.work_center.view`) | `WorkCenterRepository` |
| **Work Centers**| `/work-centers/{id}` | GET | Work Center Detail | Secret + Bearer (`production.work_center.view`) | `WorkCenterRepository` |
| **Work Centers**| `/work-centers` | POST | Create Work Center | Secret + Bearer (`production.work_center.manage`) | `WorkCenterService` |
| **Work Centers**| `/work-centers/{id}` | PUT | Update Work Center | Secret + Bearer (`production.work_center.manage`) | `WorkCenterService` |
| **Machines** | `/machines` | GET | List Machines (Filter by WC) | Secret + Bearer (`production.machine.view`) | `MachineRepository` |
| **Machines** | `/machines/{id}` | GET | Machine Detail & Telemetry | Secret + Bearer (`production.machine.view`) | `MachineRepository` |
| **Machines** | `/machines` | POST | Create Machine | Secret + Bearer (`production.machine.manage`) | `MachineService` |
| **Machines** | `/machines/{id}` | PUT | Update Machine | Secret + Bearer (`production.machine.manage`) | `MachineService` |
| **Routings** | `/routings` | GET | List Routings | Secret + Bearer (`production.routing.view`) | `RoutingRepository` |
| **Routings** | `/routings/{id}` | GET | Routing Details & Operations | Secret + Bearer (`production.routing.view`) | `RoutingRepository` |
| **Routings** | `/routings` | POST | Create Routing Draft | Secret + Bearer (`production.routing.create`) | `RoutingService` |
| **Routings** | `/routings/{id}` | PUT | Update Routing | Secret + Bearer (`production.routing.update`) | `RoutingService` |
| **Routings** | `/routings/{id}/submit` | POST | Submit Routing | Secret + Bearer (`production.routing.create`) | `RoutingService` |
| **Routings** | `/routings/{id}/approve` | POST | Approve Routing | Secret + Bearer (`production.routing.approve`) | `RoutingService` |
| **Plans** | `/plans` | GET | List Production Plans | Secret + Bearer (`production.plan.view`) | `ProductionPlanRepository` |
| **Plans** | `/plans/{id}` | GET | Plan Details & Items | Secret + Bearer (`production.plan.view`) | `ProductionPlanRepository` |
| **Plans** | `/plans` | POST | Create Production Plan | Secret + Bearer (`production.plan.create`) | `ProductionPlanService` |
| **Plans** | `/plans/{id}/approve` | POST | Approve Plan | Secret + Bearer (`production.plan.approve`) | `ProductionPlanService` |
| **Plans** | `/plans/{id}/release` | POST | Release Plan to Shop Floor | Secret + Bearer (`production.plan.release`) | `ProductionPlanService` |
| **Plans** | `/plans/{id}/create-order` | POST | Generate Orders from Plan | Secret + Bearer (`production.plan.release`) | `ProductionOrderService` |
| **Orders** | `/orders` | GET | List Orders (Compact) | Secret + Bearer (`production.order.view`) | `ProductionOrderRepository` |
| **Orders** | `/orders/{id}` | GET | Order Detail (Full) | Secret + Bearer (`production.order.view`) | `ProductionOrderRepository` |
| **Orders** | `/orders` | POST | Create Production Order | Secret + Bearer (`production.order.create`) | `ProductionOrderService` |
| **Orders** | `/orders/{id}` | PUT | Update Order Draft | Secret + Bearer (`production.order.update`) | `ProductionOrderService` |
| **Orders** | `/orders/{id}/release` | POST | Release Order (Reserve Stock) | Secret + Bearer (`production.order.release`) | `ProductionOrderService` |
| **Orders** | `/orders/{id}/issue-material` | POST | Issue Components | Secret + Bearer (`production.order.issue`) | `ProductionMaterialService` |
| **Orders** | `/orders/{id}/receive-fg` | POST | Receive Finished Goods | Secret + Bearer (`production.order.receive`) | `ProductionExecutionService` |
| **Orders** | `/orders/{id}/complete` | POST | Complete Order | Secret + Bearer (`production.order.complete`) | `ProductionOrderService` |
| **Orders** | `/orders/{id}/cancel` | POST | Cancel Order | Secret + Bearer (`production.order.cancel`) | `ProductionOrderService` |
| **MES** | `/mes/operations` | GET | Dispatch / Operator Work Queue | Secret + Bearer (`production.mes.execute`) | `MesExecutionService` |
| **MES** | `/mes/operations/{op}` | GET | Operation Execution Context | Secret + Bearer (`production.mes.execute`) | `MesExecutionService` |
| **MES** | `/mes/operations/{op}/start` | POST | Start Operation Work | Secret + Bearer (`production.mes.execute`) | `MesExecutionService` |
| **MES** | `/mes/operations/{op}/pause` | POST | Pause Operation | Secret + Bearer (`production.mes.execute`) | `MesExecutionService` |
| **MES** | `/mes/operations/{op}/resume` | POST | Resume Operation | Secret + Bearer (`production.mes.execute`) | `MesExecutionService` |
| **MES** | `/mes/operations/{op}/complete` | POST | Complete Operation | Secret + Bearer (`production.mes.execute`) | `MesExecutionService` |
| **MES** | `/mes/operations/{op}/log-progress` | POST | Log Intermediate Output | Secret + Bearer (`production.mes.execute`) | `MesExecutionService` |
| **MES** | `/mes/operations/{op}/scrap` | POST | Report Operational Scrap | Secret + Bearer (`production.mes.execute`) | `ScrapService` |
| **MES** | `/mes/operations/{op}/quick-qc` | POST | Record Operator QC Check | Secret + Bearer (`production.quality.inspect`) | `QualityInspectionService` |
| **Downtime** | `/mes/downtime/start` | POST | Log Machine Downtime | Secret + Bearer (`production.mes.execute`) | `DowntimeService` |
| **Downtime** | `/mes/downtime/{id}/end` | POST | Conclude Downtime | Secret + Bearer (`production.mes.execute`) | `DowntimeService` |
| **Quality** | `/quality/inspections` | GET | List Inspections | Secret + Bearer (`production.quality.view`) | `ProductionQualityRepository` |
| **Quality** | `/quality/inspections/{id}` | GET | Inspection Detail & Criteria | Secret + Bearer (`production.quality.view`) | `ProductionQualityRepository` |
| **Quality** | `/quality/inspections/{id}/results` | POST | Record Inspection Results | Secret + Bearer (`production.quality.inspect`) | `QualityInspectionService` |
| **Quality** | `/quality/inspections/{id}/approve` | POST | Disposition / Approve | Secret + Bearer (`production.quality.approve`) | `QualityInspectionService` |

---

## 8. Data Optimization & Serialization Standard

### 8.1 Unified Response Envelope
All API responses will strictly adhere to the project envelope:
```json
{
    "success": true,
    "message": "Production orders retrieved successfully.",
    "data": [],
    "meta": {
        "current_page": 1,
        "per_page": 25,
        "total": 120,
        "last_page": 5
    }
}
```

### 8.2 Summary vs Detail Resource Segregation
To guarantee high performance and low network bandwidth for mobile/IoT devices:
1. **Summary / List Resources** (e.g. `ProductionOrderListResource`):
   - Returns primary IDs, document numbers, product name/sku, planned quantity, completed quantity, status, dates.
   - Constrained relationships only (e.g. `product:id,name,sku`).
   - No deep nesting of operations, materials, logs, or genealogy.
2. **Detail Resources** (e.g. `ProductionOrderDetailResource`):
   - Returns full lifecycle status, operations with work center/machine assignments, material requirements with issue statuses, WIP stage, and quality disposition.
   - Relationships eager loaded using specific column constraints (`with(['product:id,name,sku,uom_id', 'bom:id,bom_number'])`).

### 8.3 Safe Pagination & Filtering
* Maximum page size: `100` items (default: `25`).
* Whitelisted sorting: `created_at`, `order_number`, `planned_start_date`, `status`.
* Whitelisted filtering: `status`, `product_id`, `work_center_id`, `from_date`, `to_date`, `search`.

---

## 9. Error Handling & Exception Strategy

* Domain exceptions (e.g., `ValidationException`, `DomainException`, `ModelNotFoundException`, `AuthorizationException`) will be handled gracefully and converted to uniform JSON responses:
  - `401 Unauthorized`: Missing or invalid secret key or Bearer token.
  - `403 Forbidden`: Permission denied or tenant mismatch.
  - `404 Not Found`: Record does not exist in the tenant context.
  - `409 Conflict`: Invalid state transition (e.g. attempting to release a completed order).
  - `422 Unprocessable Entity`: Form request validation failures.
  - `429 Too Many Requests`: Rate limit exceeded.
  - `500 Internal Server Error`: Generic safe message `"An error occurred while processing the request."` (no raw SQL, traces, or file paths exposed).

---

## 10. Audit Sign-off

Phase 1 audit confirms:
1. The domain architecture and repository bindings are completely ready for an additive API layer.
2. Existing business logic in services will be 100% reused.
3. Multi-tenant isolation is enforced at the BaseModel global scope level and will be reinforced via middleware and route model binding.
4. Sanctum provides the ideal Bearer authentication mechanism, and `X-API-SECRET` provides the required gateway security.
