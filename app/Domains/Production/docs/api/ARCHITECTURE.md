# Production Module REST API Architecture

## 1. High-Level Architecture & Design Principles

The Production Module REST API (`/api/v1/production/...`) provides a headless, versioned, highly secure interface into the existing Laravel multi-tenant SaaS ERP. Designed specifically to support external clients, mobile MES terminals, automated shop floor integrations, and third-party dashboards, this API strictly avoids duplicating business logic and delegates directly to existing Production services, repositories, and RBAC authorization policies.

```
+-----------------------------------------------------------------------------------+
|                                Client Request                                     |
|  Headers: Accept: application/json, X-Tenant: <slug>, X-API-SECRET: <secret>,     |
|           Authorization: Bearer <sanctum_token>, [Idempotency-Key: <uuid>]       |
+-----------------------------------------------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                             Global Middleware Pipeline                            |
| 1. ProductionApiSecretMiddleware  -> Constant-time secret key check (401 Generic) |
| 2. ResolveTenant / stancl/tenancy -> Header-based tenant identification (404/401) |
| 3. Authenticate (auth:sanctum)    -> Bearer token identity verification (401)     |
| 4. ProductionTenantEnforcement    -> Cross-tenant user/tenant verification (403)  |
| 5. ProductionIdempotency (Writes) -> Request lock & cached response replay (200)  |
| 6. ThrottleRequests               -> Dynamic rate limiting tiers (429)            |
+-----------------------------------------------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                        Routing & Versioning Layer                                 |
|  Prefix: /api/v1/production                                                       |
|  app/Domains/Production/Routes/api.php                                            |
+-----------------------------------------------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                            Form Request Validation                                |
|  - Overridden failedValidation()   -> Standardized 422 JSON envelope              |
|  - Overridden failedAuthorization()-> Standardized 403 JSON envelope              |
|  - Strict whitelisted payload validation                                          |
+-----------------------------------------------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                             API Controller Layer                                  |
|  app/Domains/Production/Controllers/Api/*                                         |
|  - Enforces Gate::authorize() matching Web Policies                               |
|  - Resolves tenant-safe scoped entities (no IDOR)                                 |
|  - Delegates all mutations to existing Domain Services                            |
+-----------------------------------------------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                       Existing Domain Service Layer                               |
|  - ProductionOrderService, ProductionBomService, RoutingService                   |
|  - ProductionMaterialService, ProductionExecutionService, MesExecutionService     |
|  - ProductionPlanService, QualityInspectionService                                |
+-----------------------------------------------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                            API Resource Formatting                                |
|  app/Domains/Production/Resources/Api/*                                           |
|  - Standard JSON Envelope: { success: true, message: "...", data: {...}, meta }  |
|  - Strips internal IDs, tenant_id, deleted_at, system metadata                     |
|  - Distinct Light List vs. Deep Detail resource transformers                      |
+-----------------------------------------------------------------------------------+
```

---

## 2. Dual-Gate Security Pipeline

To prevent unauthorized automated scraping, bot attacks, and token spoofing, the API enforces a mandatory two-gate security model on all production endpoints:

1. **Gate 1: API Secret Key (`X-API-SECRET`)**
   - Every request to `/api/v1/production/*` must pass an `X-API-SECRET` header.
   - Evaluated by `App\Http\Middleware\ProductionApiSecretMiddleware` prior to routing.
   - Checked in constant time using `hash_equals(config('production.api_secret'), $headerSecret)` to neutralize timing attacks.
   - If missing, invalid, or mismatched, the request is immediately halted with a generic `401 Unauthorized` (`{"success": false, "message": "Unauthenticated or invalid API credentials."}`). No hint is given about whether the secret or the token was faulty.

2. **Gate 2: Sanctum Bearer Token (`auth:sanctum`)**
   - Authenticates the user identity and loads the associated user model and permissions.
   - Enforced across all production endpoints (excluding health checks).

---

## 3. Strict Multi-Tenancy & IDOR Shielding

The SaaS architecture relies on `stancl/tenancy` combined with tenant foreign keys on domain tables.

1. **Tenant Identification**:
   - Resolved via the `X-Tenant: <tenant-slug>` header.
   - Supported natively by `App\Http\Middleware\ResolveTenant` and `TenantResolver`.

2. **Tenant Context Enforcement**:
   - `App\Http\Middleware\ProductionTenantEnforcementMiddleware` verifies that:
     - The resolved tenant matches the authenticated user's `tenant_id`.
     - The tenant account is in `active` status.
     - Any cross-tenant attempt immediately yields `403 Forbidden` (`{"success": false, "message": "Access denied for this tenant context."}`).

3. **Controller-Level Scoping**:
   - API controllers never query entities by primary key alone.
   - Every lookup applies `->where('tenant_id', $this->getTenantId())->findOrFail($id)`.
   - Insecure Direct Object References (IDOR) are completely prevented; querying another tenant's record yields a standard `404 Not Found`.

4. **Information Leakage Prevention**:
   - API Resources explicitly filter out `tenant_id`, database primary keys of internal joins, internal timestamps, and soft-delete markers.

---

## 4. RBAC & Existing Policy Enforcement

All existing production authorization gates are preserved. The API controllers call `Gate::authorize()` or `$this->authorize()` before executing actions:

| Resource | Action | Permission Checked | Policy Method |
| :--- | :--- | :--- | :--- |
| Production Orders | List / Show | `production.order.view` | `ProductionOrderPolicy@view` |
| Production Orders | Create | `production.order.create` | `ProductionOrderPolicy@create` |
| Production Orders | Update | `production.order.update` | `ProductionOrderPolicy@update` |
| Production Orders | Release | `production.order.update` | `ProductionOrderPolicy@release` |
| Production Orders | Issue Material | `production.order.update` | `ProductionOrderPolicy@issue` |
| Production Orders | Log Progress | `production.order.update` | `ProductionOrderPolicy@logProgress` |
| Production Orders | Receive FG | `production.order.update` | `ProductionOrderPolicy@receiveFg` |
| Production Orders | Complete | `production.order.update` | `ProductionOrderPolicy@complete` |
| Production Orders | Cancel | `production.order.update` | `ProductionOrderPolicy@cancel` |
| BOM | List / Show | `production.bom.view` | `ProductionBomPolicy@view` |
| BOM | Create | `production.bom.create` | `ProductionBomPolicy@create` |
| BOM | Update / Submit | `production.bom.update` | `ProductionBomPolicy@update` |
| BOM | Approve / Clone | `production.bom.approve` | `ProductionBomPolicy@approve` |
| Routings | List / Show | `production.routing.view` | `RoutingPolicy@view` |
| Routings | Create / Update | `production.routing.create` / `update` | `RoutingPolicy@create` / `update` |
| Production Plans | List / Show | `production.plan.view` | `ProductionPlanPolicy@view` |
| Production Plans | Create / Submit | `production.plan.create` / `update` | `ProductionPlanPolicy@create` / `update` |
| Production Plans | Approve | `production.plan.approve` | `ProductionPlanPolicy@approve` |
| Work Centers / Machines | View / Edit | `production.workcenter.view` / `update` | Direct policy authorization |
| Quality Inspections | View / Record | `quality.inspection.view` / `update` | `QualityInspectionPolicy` |

---

## 5. Domain Service Layer Reuse & State Transitions

No raw Eloquent updates to status columns are allowed. State transitions must occur via explicit, verb-oriented action endpoints:

1. **Production Orders**:
   - `POST /orders/{id}/release`: Transitions order from `draft` to `released` via `ProductionOrderService::release()`.
   - `POST /orders/{id}/issue-material`: Issues stock and updates reservations via `ProductionMaterialService::issueMaterial()`.
   - `POST /orders/{id}/progress`: Logs run/setup time and produced quantities via `ProductionExecutionService::logProgress()`.
   - `POST /orders/{id}/receive-fg`: Enforces completion guardrails, creates goods receipts, and adds stock via `ProductionExecutionService::receiveFinishedGoods()`.
   - `POST /orders/{id}/scrap`: Logs scrap and creates optional NCR records via `ProductionExecutionService::logScrap()`.
   - `POST /orders/{id}/complete`: Validates that all operations are completed/skipped and closes order via `ProductionOrderService::complete()`.
   - `POST /orders/{id}/cancel`: Cancels reservations, schedules, and operations via `ProductionOrderService::cancel()`.

2. **BOMs & Plans**:
   - Explicit two-phase approval: `POST /submit` transitions to `pending_approval`, and `POST /approve` activates the revision and deactivates prior versions.

---

## 6. Idempotency Support

Write endpoints (`/orders`, `/issue-material`, `/receive-fg`, `/progress`, `/scrap`, and MES start/complete) support the `Idempotency-Key` header.
- Handled by `App\Http\Middleware\ProductionIdempotencyMiddleware`.
- Backed by cache lock keys tagged by tenant and user: `production:idempotency:{tenant_id}:{user_id}:{key}`.
- Prevents double-issuance of materials or duplicate goods receipts in mobile environments with intermittent connectivity.
