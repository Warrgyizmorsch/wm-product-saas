# Production Module REST API Security Architecture

## 1. Dual-Gate Authentication & Request Authorization

All requests targeting `/api/v1/production/*` are safeguarded by two mandatory security perimeters:

```
[Inbound HTTP Request]
          │
          ▼
┌──────────────────────────────────────┐
│  Gate 1: API Secret Key Check        │
│  - Middleware: ProductionApiSecret   │
│  - Header: X-API-SECRET              │
│  - Check: hash_equals() constant-time│
└──────────────────┬───────────────────┘
                   │ OK
                   ▼
┌──────────────────────────────────────┐
│  Gate 2: Sanctum Bearer Token        │
│  - Middleware: auth:sanctum          │
│  - Header: Authorization: Bearer ... │
│  - Verifies token validity & user    │
└──────────────────┬───────────────────┘
                   │ OK
                   ▼
┌──────────────────────────────────────┐
│  Gate 3: Tenant Boundary Enforcement │
│  - Middleware: ProductionTenantEnforce│
│  - Verifies auth user tenant_id ==   │
│    resolved tenant context           │
└──────────────────┬───────────────────┘
                   │ OK
                   ▼
┌──────────────────────────────────────┐
│  Gate 4: RBAC Policy Authorization   │
│  - Gate::authorize('view', $order)   │
│  - Checks specific role & permissions│
└──────────────────┬───────────────────┘
                   │ OK
                   ▼
       [Domain Execution Layer]
```

### Constant-Time API Secret Comparison
To prevent timing attack vectors where attackers deduce secret characters based on response latency, `ProductionApiSecretMiddleware` implements:
```php
$validSecret = (string) config('production.api_secret');
if (empty($validSecret) || !hash_equals($validSecret, (string) $providedSecret)) {
    return response()->json([
        'success' => false,
        'message' => 'Unauthenticated or invalid API credentials.',
    ], 401);
}
```
* The denial message is intentionally generic and identical whether the secret was completely absent, malformed, or mismatched.
* `ProductionApiSecretMiddleware` is registered before `ResolveTenant` in the pipeline priority list to reject unauthorized traffic before expensive database tenant queries occur.

---

## 2. Strict Tenant Isolation & Anti-IDOR Protections

1. **Explicit Multi-Tenant Scoping**:
   Every database query executed by the API controllers incorporates the authenticated tenant's ID:
   ```php
   $order = ProductionOrder::where('tenant_id', $tenantId)->findOrFail($id);
   ```
   If a client authenticated for Tenant A attempts to access an ID belonging to Tenant B:
   - The query returns zero rows.
   - Eloquent throws a `ModelNotFoundException`.
   - The API base controller renders a standard `404 Not Found`.
   - No information about whether the resource ID exists in another tenant is revealed.

2. **Cross-Tenant Header Manipulation Defense**:
   `ProductionTenantEnforcementMiddleware` blocks scenarios where an attacker provides a valid Bearer token for Tenant A but supplies `X-Tenant: tenant-b`:
   ```php
   if ((int) $user->tenant_id !== (int) $tenant->id) {
       return response()->json([
           'success' => false,
           'message' => 'Access denied for this tenant context.',
       ], 403);
   }
   ```

3. **Information Leakage Prevention in API Resources**:
   Raw Eloquent models are never returned to clients. Dedicated API Resources (`ProductionOrderListResource`, `ProductionOrderDetailResource`, `ProductionBomListResource`, etc.) explicitly curate output fields:
   * **Hidden**: `tenant_id`, internal foreign keys used only for joins, `deleted_at`, internal system notes, raw database hashes.
   * **Exposed**: Only clean public business entities, formatted dates, UOMs, and state strings.

---

## 3. Rate Limiting Tiers

Dynamic rate limiting is configured in `app/Providers/AppServiceProvider.php` using Laravel's `RateLimiter`:

| Rate Limiter | Limit | Target Routes | Key Composition |
| :--- | :--- | :--- | :--- |
| `production-api` | 120 requests/minute | General GET queries and dashboards | `t:{tenant_id}:{u:user_id\|ip:client_ip}` |
| `production-api-write` | 60 requests/minute | All POST, PUT, DELETE mutations | `t:{tenant_id}:{u:user_id\|ip:client_ip}` |
| `production-api-mes` | 180 requests/minute | High-frequency MES endpoints (`/mes/*`) | `t:{tenant_id}:{u:user_id\|ip:client_ip}` |

When limits are exceeded, a standard `429 Too Many Requests` response is returned with the `Retry-After` header.

---

## 4. Write Idempotency

To prevent race conditions, duplicate inventory movements, or repeated network retries from duplicating transactions, critical write endpoints support the `Idempotency-Key` header.
* Managed by `ProductionIdempotencyMiddleware`.
* Scoped deterministically per tenant, user, method, path, idempotency key, and request payload hash (`sha256`).
* Cache key pattern: `prod_idem:t_{tenant_id}:u_{user_id}:{hash}` and lock pattern: `prod_lock:t_{tenant_id}:u_{user_id}:{hash}`.
* **Concurrency Lock**: Employs atomic locks with a 30-second lease to prevent race conditions during execution. Concurrent requests with the same key receive `409 Conflict` (`"A request with this Idempotency-Key is currently processing."`).
* **Payload Conflict Detection**: If an idempotency key is reused with a different request payload, the request is rejected immediately with `409 Conflict` (`"Idempotency-Key reused with a different request payload."`).
* **Replay**: Upon successful 2xx completion, the exact response and status code are cached for 24 hours. Subsequent identical retries replay the cached response with the `X-Idempotent-Replay: true` header.

---

## 5. Audit Logging & Event Capture

Every mutating state transition in the Production API automatically writes audit records via the ERP's native `ProductionEventService`:
* Order releases, completions, material issues, progress logs, and scrap recordings create persistent audit logs in `production_events` capturing:
  * `production_order_id`
  * `event_type` (e.g. `Material Issued`, `Production Completed`)
  * `severity` (`info`, `warning`, `error`, `success`)
  * `triggered_by` (Authenticated Sanctum User ID)
  * `event_source` (`ProductionMaterialService`, `ProductionOrderService`, etc.)
