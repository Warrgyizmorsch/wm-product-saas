# Production REST API — Postman Collection & Integration Guide

This guide provides step-by-step instructions for importing, configuring, authenticating, and executing requests against the **Laravel SaaS ERP Production Module REST API (`v1`)** using Postman.

---

## 1. Overview & Architecture

The Production API exposes the core capabilities of the ERP's manufacturing domain:
* **Dashboard & Telemetry**: High-level KPI overviews, metrics, and shopfloor alerts.
* **Engineering & Master Data**: Bills of Materials (BOM) with revisioning and process Routings with sequenced operations.
* **Plant Resources**: Work Center capacity planning and Machine states.
* **Planning & Execution**: Production Plans (MPS/MRP) and Production Work Orders with full lifecycle transitions.
* **Manufacturing Execution System (MES)**: Real-time operator queue, machine dispatching, runtime tracking, and downtime logging.
* **Quality Assurance (QA/QC)**: Checklist inspections, parameter disposition, and fast shopfloor operator quick checks.

### Security Architecture
All requests under `/api/v1/production/*` are secured by two required layers:
1. **Mandatory Production API Secret Key**: Header `X-API-SECRET: {{api_secret}}` validated in constant-time comparison via `ProductionApiSecretMiddleware`.
2. **Sanctum Bearer Token**: Header `Authorization: Bearer {{access_token}}` enforcing user identity, role, and domain permissions.
3. **Multi-Tenant Isolation**: Header `X-Tenant: {{tenant_domain}}` (or tenant subdomain) enforcing strict tenant scoping via `ProductionTenantEnforcementMiddleware`. Cross-tenant resources return `404 Not Found`.
4. **Idempotency**: All critical write endpoints accept an `Idempotency-Key` header via `ProductionIdempotencyMiddleware`. Duplicate submissions replay cached responses with `X-Idempotent-Replay: true`, while identical keys with mismatched payloads return `409 Conflict`.

---

## 2. Package Artifacts

The Postman package located in `docs/postman/` includes:

| File | Description |
| :--- | :--- |
| `Production API v1.postman_collection.json` | The full Postman Collection (v2.1.0 schema) containing all 49 canonical Production routes, setup endpoints, and automated security/reliability test suites. |
| `Production API - Local.postman_environment.json` | Pre-configured environment template for local development (`http://demo.localhost`). |
| `Production API - Staging.postman_environment.json` | Pre-configured environment template for staging environments. |
| `POSTMAN_API_GUIDE.md` | This technical guide. |

---

## 3. Quick Start & Import

### Step 1: Import into Postman
1. Open Postman.
2. Click **Import** (top left).
3. Drag and drop:
   - `docs/postman/Production API v1.postman_collection.json`
   - `docs/postman/Production API - Local.postman_environment.json`
   - `docs/postman/Production API - Staging.postman_environment.json`
4. Confirm the import.

### Step 2: Select the Active Environment
1. In the top-right environment selector, choose **Production API - Local** (or **Production API - Staging**).

### Step 3: Configure Environment Variables
Navigate to **Environments** > **Production API - Local** and set the current values:

| Variable | Description | Example / Default |
| :--- | :--- | :--- |
| `base_url` | Base URL of the Laravel application | `http://demo.localhost` |
| `api_version` | API route version | `v1` |
| `api_secret` | Production API Secret configured in `.env` (`PRODUCTION_API_SECRET`) | *Set to your local secret* |
| `tenant_domain` | Tenant slug for tenant context resolution | `warrgyizmorsch` |
| `tenant_id` | Database ID of the active tenant | `1` |
| `access_token` | Sanctum Bearer token | *(Auto-populated by login request)* |

> [!IMPORTANT]
> Never commit actual passwords, tokens, or production API secrets to source control. Always store them as sensitive environment variables or Postman secrets.

---

## 4. Authentication Flow

### Obtaining a Bearer Token via Postman
The collection includes a pre-configured login request in the **`00 - Authentication & Setup`** folder:

1. Open request: **`00 - Authentication & Setup` > `Obtain Bearer Token (API Login)`**.
2. Endpoint: `POST {{base_url}}/api/auth/login`
3. Headers:
   ```http
   Accept: application/json
   Content-Type: application/json
   ```
   > [!NOTE]
   > Neither `X-API-SECRET` nor `X-Tenant` is required for `/api/auth/login`. Login is performed using only `email` + `password` credentials. Subsequent Production API requests (`/api/v1/production/*`) have their own tenant-context (`X-Tenant`) and security-gate (`X-API-SECRET`) requirements.

4. Request Body (seeded local admin credentials):
   ```json
   {
       "email": "admin@example.com",
       "password": "password"
   }
   ```
5. Click **Send**.
6. The included Postman test script automatically captures the returned Sanctum token (`response.token`) and sets it into the `{{access_token}}` collection variable:
   ```javascript
   const json = pm.response.json();
   pm.expect(json).to.have.property("token");
   pm.collectionVariables.set("access_token", json.token);
   if (json.user && json.user.tenant_id) {
       pm.collectionVariables.set("tenant_id", json.user.tenant_id.toString());
   }
   ```

### Collection-Level Authentication for Production Endpoints
All Production API folders inherit Bearer Authentication from the collection root:
* Type: `Bearer Token`
* Token: `{{access_token}}`

Every Production API request (`/api/v1/production/*`) automatically transmits:
```http
Accept: application/json
Content-Type: application/json
X-API-SECRET: {{api_secret}}
X-Tenant: {{tenant_domain}}
Authorization: Bearer {{access_token}}
```

---

## 5. Rate Limits

The Production API applies dedicated named rate limiters scoped by tenant and client identity (`t:{tenant_id}:u:{user_id}` or `t:{tenant_id}:ip:{client_ip}`):

| Rate Limiter | Scope | Limit | Endpoints Protected |
| :--- | :--- | :--- | :--- |
| `production-api` | General Read & Inspection | **120 requests / min** | All GET endpoints (dashboard, boms, routings, work centers, machines, plans, orders, quality) |
| `production-api-write` | Core Write Operations | **60 requests / min** | All POST, PUT actions on BOMs, routings, work centers, machines, plans, orders, and quality |
| `production-api-mes` | Shopfloor High-Frequency MES | **180 requests / min** | MES Queue, Operation Start/Pause/Resume/Complete, and Downtime Start/End |

When a rate limit is exceeded, the server returns HTTP `429 Too Many Requests` with standard `Retry-After` and `X-RateLimit-*` headers.

---

## 6. Idempotency & Reliability

All state-changing production transactions support idempotency via the `Idempotency-Key` HTTP header.

### Endpoints Supporting Idempotency
- `POST /orders/{order}/issue-material`
- `POST /orders/{order}/progress`
- `POST /orders/{order}/receive-fg`
- `POST /orders/{order}/complete`
- `POST /orders/{order}/scrap`
- `POST /mes/operations/{operation}/complete`
- `POST /quality/inspections/{inspection}/submit`
- `POST /quality/orders/{order}/quick-check`

### Dynamic Key Generation
The collection includes a pre-request script generating a unique UUID v4:
```javascript
pm.variables.set("dynamic_idempotency_key", generateUUID());
```
When requests send `Idempotency-Key: {{dynamic_idempotency_key}}`:
* **First request**: Processed and result cached for 24 hours.
* **Network retry (identical key + identical payload)**: Server immediately returns cached response with `X-Idempotent-Replay: true`.
* **Payload mismatch (same key + modified payload)**: Server rejects the tampering with `HTTP 409 Conflict` (`"Idempotency-Key reused with a different request payload."`).

---

## 7. Collection Hierarchy & Route Coverage

The collection contains **49 canonical routes** organized into 10 structured folders:

```text
Production API v1
│
├── 00 - Authentication & Setup
│   └── Obtain Bearer Token (API Login) [POST /api/auth/login]
│
├── 01 - Dashboard (3 routes)
│   ├── Get Dashboard Overview [GET /dashboard]
│   ├── Get Dashboard Metrics [GET /dashboard/metrics]
│   └── Get Dashboard Alerts [GET /dashboard/alerts]
│
├── 02 - BOMs (7 routes)
│   ├── List BOMs [GET /boms]
│   ├── Create BOM [POST /boms]
│   ├── Get BOM Detail [GET /boms/{bom}]
│   ├── Update BOM [PUT /boms/{bom}]
│   ├── Submit BOM for Approval [POST /boms/{bom}/submit]
│   ├── Approve BOM [POST /boms/{bom}/approve]
│   └── Clone BOM Revision [POST /boms/{bom}/clone]
│
├── 03 - Routings (4 routes)
│   ├── List Routings [GET /routings]
│   ├── Create Routing [POST /routings]
│   ├── Get Routing Detail [GET /routings/{routing}]
│   └── Update Routing [PUT /routings/{routing}]
│
├── 04 - Work Centers (4 routes)
│   ├── List Work Centers [GET /work-centers]
│   ├── Create Work Center [POST /work-centers]
│   ├── Get Work Center Detail [GET /work-centers/{workCenter}]
│   └── Update Work Center [PUT /work-centers/{workCenter}]
│
├── 05 - Machines (4 routes)
│   ├── List Machines [GET /machines]
│   ├── Create Machine [POST /machines]
│   ├── Get Machine Detail [GET /machines/{machine}]
│   └── Update Machine [PUT /machines/{machine}]
│
├── 06 - Production Plans (6 routes)
│   ├── List Production Plans [GET /plans]
│   ├── Create Production Plan [POST /plans]
│   ├── Get Production Plan Detail [GET /plans/{plan}]
│   ├── Update Production Plan [PUT /plans/{plan}]
│   ├── Submit Plan for Approval [POST /plans/{plan}/submit]
│   └── Approve Production Plan [POST /plans/{plan}/approve]
│
├── 07 - Production Orders (10 routes)
│   ├── List Production Orders [GET /orders]
│   ├── Create Production Order [POST /orders]
│   ├── Get Production Order Detail [GET /orders/{order}]
│   ├── Update Production Order [PUT /orders/{order}]
│   ├── Release Production Order [POST /orders/{order}/release]
│   ├── Issue Materials to Order [POST /orders/{order}/issue-material]
│   ├── Log Order Progress [POST /orders/{order}/progress]
│   ├── Receive Finished Goods [POST /orders/{order}/receive-fg]
│   ├── Complete Production Order [POST /orders/{order}/complete]
│   └── Log Scrap [POST /orders/{order}/scrap]
│
├── 08 - MES (7 routes)
│   ├── Queue
│   │   └── Get MES Operator Dispatch Queue [GET /mes/queue]
│   ├── Operations
│   │   ├── Start Operation [POST /mes/operations/{operation}/start]
│   │   ├── Pause Operation [POST /mes/operations/{operation}/pause]
│   │   ├── Resume Operation [POST /mes/operations/{operation}/resume]
│   │   └── Complete Operation [POST /mes/operations/{operation}/complete]
│   └── Downtime
│       ├── Start Machine Downtime [POST /mes/downtime/start]
│       └── End Machine Downtime [POST /mes/downtime/{downtime}/end]
│
├── 09 - Quality (4 routes)
│   ├── Inspections
│   │   ├── List Quality Inspections [GET /quality/inspections]
│   │   ├── Get Quality Inspection Detail [GET /quality/inspections/{inspection}]
│   │   └── Submit Inspection Results [POST /quality/inspections/{inspection}/submit]
│   └── Quick Check
│       └── Operator Inline Quick Check [POST /quality/orders/{order}/quick-check]
│
└── 10 - Security & Reliability (12 negative/contract tests)
    ├── Authentication Negative Tests (Missing Secret, Invalid Secret, Missing Token, Invalid Token)
    ├── Authorization Tests (Unauthorized Dashboard, Quick Check, Release)
    ├── Tenant Isolation (Cross-Tenant Order 404, Cross-Tenant BOM 404)
    └── Idempotency (Initial write, Identical replay with header check, Conflict on modified payload)
```

---

## 8. Dynamic Path Variables & Seed Data

The collection utilizes Postman collection variables for dynamic IDs instead of hardcoded numbers:

* `{{bom_id}}`
* `{{routing_id}}`
* `{{work_center_id}}`
* `{{machine_id}}`
* `{{plan_id}}`
* `{{order_id}}`
* `{{operation_id}}`
* `{{inspection_id}}`
* `{{downtime_id}}`

### Automated Variable Capture
When running GET list or POST create requests, test scripts automatically capture returned IDs and store them in collection variables.
For example, running `List Production Orders` or `Create Production Order` automatically sets `pm.collectionVariables.set("order_id", json.data.id);`.

If running individual requests manually against an existing database, ensure the relevant variable contains a valid ID present in the active tenant.

---

## 9. Running with Postman Collection Runner

To execute the entire collection or specific test folders:
1. Select the collection **Production API v1**.
2. Click **Run Collection**.
3. Select your active environment (**Production API - Local**).
4. Choose the desired folders:
   * To verify business workflows: Run folders `00` through `09`.
   * To verify security & hardening: Run folder `10 - Security & Reliability`.
5. Check **Save responses** and click **Run Production API v1**.
6. Review the test results table. All contract, status, and envelope assertions should pass.

---

## 10. Troubleshooting Common Errors

| Status Code | Cause | Resolution |
| :--- | :--- | :--- |
| **`401 Unauthorized`** (`"API access denied."`) | Missing or incorrect `X-API-SECRET` header | Verify `api_secret` in the active environment matches `config('production.api_secret')` in `.env`. |
| **`401 Unauthorized`** (`"Unauthenticated."`) | Missing or invalid Bearer token | Execute the `Obtain Bearer Token` request to refresh `{{access_token}}`. |
| **`403 Forbidden`** (`"This action is unauthorized."`) | User lacks the required RBAC permission | Ensure the authenticated user has the necessary production permission or `admin` role. |
| **`403 Forbidden`** (`"Tenant access denied."`) | User's `tenant_id` does not match the active `X-Tenant` | Verify that the user belongs to the tenant resolved from `{{tenant_domain}}`. |
| **`404 Not Found`** (`"Resource not found."`) | ID does not exist or belongs to another tenant | Check that the resource ID exists for the current tenant. The API enforces strict tenant boundary isolation. |
| **`409 Conflict`** (`"Idempotency-Key reused with a different request payload."`) | The same `Idempotency-Key` was sent with a different payload | Generate a new idempotency key or do not alter the request body when replaying. |
| **`422 Unprocessable Entity`** | Request validation error | Check the response `errors` object for missing or invalid parameters per Form Request validation rules. |
| **`429 Too Many Requests`** | Rate limit exceeded | Respect rate limits (120/min general, 60/min write, 180/min MES). Back off and retry after the specified window. |
