# Production Module REST API Endpoints Reference

All endpoints are prefixed by `/api/v1/production`.

## Standard Headers

Every request to an authenticated Production API endpoint requires:
* `Accept: application/json`
* `Content-Type: application/json`
* `X-API-SECRET: <your-configured-api-secret>`
* `Authorization: Bearer <sanctum-personal-access-token>`
* `X-Tenant: <tenant-slug>` (e.g. `acme-corp`)
* `Idempotency-Key: <unique-uuid-or-hash>` (Mandatory or strongly recommended for POST/PUT mutations)

---

## Standard JSON Envelopes

### Success Envelope (Single Item)
```json
{
  "success": true,
  "message": "Resource operation completed successfully.",
  "data": {
    "id": 1,
    "attributes": "..."
  }
}
```

### Success Envelope (Paginated List)
```json
{
  "success": true,
  "message": "Production orders retrieved successfully.",
  "data": [
    { "id": 1, "order_number": "PO-2026-0001", "status": "draft" }
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "to": 15,
    "per_page": 15,
    "total": 1,
    "last_page": 1
  }
}
```

### Error Envelope (4xx / 5xx)
```json
{
  "success": false,
  "message": "Human-readable error explanation.",
  "errors": {
    "quantity_ordered": [
      "The quantity ordered field must be at least 0.0001."
    ]
  }
}
```

---

## 1. Dashboard & Analytics

### `GET /dashboard`
Aggregated high-level production KPIs (Active orders, shop floor operations, master resources).
* **Auth**: Sanctum (`production.intelligence.view`, `production.order.view`, or `admin`)
* **Status**: `200 OK`

### `GET /dashboard/metrics`
Detailed executive & shopfloor time-series metrics (OEE, production summary, utilizations, scrap stats, Six Big Losses, downtime rate).
* **Auth**: Sanctum (`production.intelligence.view`, `production.order.view`, or `admin`)
* **Query Params**: `from_date` (YYYY-MM-DD), `to_date` (YYYY-MM-DD), `work_center_id` (integer)
* **Status**: `200 OK`

### `GET /dashboard/alerts`
Open shop floor blockers, alert configurations, timeline events, and quality holds.
* **Auth**: Sanctum (`production.intelligence.view`, `production.order.view`, or `admin`)
* **Status**: `200 OK`

---

## 2. Production Orders

### `GET /orders`
Paginated list of production orders using lightweight `ProductionOrderListResource`.
* **Query Params**:
  * `status` (string, e.g. `draft`, `released`, `in_progress`, `completed`, `cancelled`)
  * `product_id` (integer)
  * `production_model` (string)
  * `from_date`, `to_date` (date)
  * `search` (order number or description)
  * `sort_by` (whitelisted: `created_at`, `order_number`, `start_date`, `end_date`, `status`)
  * `sort_dir` (`asc` / `desc`)
  * `per_page` (integer, max capped at 100)
* **Status**: `200 OK`

### `POST /orders`
Create a new production order in `draft` mode.
* **Idempotency Supported**: Yes
* **Request Body**:
```json
{
  "product_id": 10,
  "bom_id": 5,
  "routing_id": 3,
  "quantity_ordered": 100.0,
  "production_mode": "standard",
  "production_model": "pure_manufacturing",
  "start_date": "2026-10-01",
  "end_date": "2026-10-15",
  "description": "Custom client production run"
}
```
* **Status**: `201 Created`

### `GET /orders/{id}`
Deep detail of a production order using `ProductionOrderDetailResource` including operations, reservations, and BOM/routing snapshots.
* **Status**: `200 OK`

### `PUT /orders/{id}`
Update non-frozen production order header fields before execution.
* **Status**: `200 OK` / `409 Conflict` (if order is completed or frozen)

### `POST /orders/{id}/release`
Explicit action: Release a draft production order to the shop floor.
* **Status**: `200 OK`

### `POST /orders/{id}/issue-material`
Explicit action: Issue reserved raw materials to a production order.
* **Idempotency Supported**: Yes
* **Request Body**:
```json
{
  "reservation_id": 42,
  "warehouse_id": 1,
  "quantity": 25.5,
  "remarks": "Batch issue for stage 1 assembly"
}
```
* **Status**: `200 OK`

### `POST /orders/{id}/progress`
Explicit action: Log operation execution progress and labor/machine time.
* **Idempotency Supported**: Yes
* **Request Body**:
```json
{
  "operation_id": 12,
  "quantity_produced": 50.0,
  "quantity_rejected": 2.0,
  "quantity_scrapped": 1.0,
  "setup_minutes_logged": 15.0,
  "run_minutes_logged": 120.0,
  "machine_id": 3,
  "complete_operation": false,
  "remarks": "Shift A morning production"
}
```
* **Status**: `200 OK`

### `POST /orders/{id}/receive-fg`
Explicit action: Receive finished goods into inventory stock upon completion.
* **Idempotency Supported**: Yes
* **Request Body**:
```json
{
  "quantity_received": 50.0,
  "warehouse_id": 2,
  "quality_status": "passed",
  "remarks": "Received into FG warehouse"
}
```
* **Status**: `200 OK`

### `POST /orders/{id}/scrap`
Explicit action: Record scrap with optional NCR generation.
* **Request Body**:
```json
{
  "quantity": 2.0,
  "reason": "Dimensional variance outside tolerance",
  "operation_id": 12,
  "create_ncr": true,
  "ncr_category": "dimensional"
}
```
* **Status**: `200 OK`

### `POST /orders/{id}/complete`
Explicit action: Complete a production order after all operations are accounted for.
* **Status**: `200 OK`

### `POST /orders/{id}/cancel`
Explicit action: Cancel order and release reservations.
* **Status**: `200 OK`

---

## 3. Bill of Materials (BOM)

* `GET /boms`: List BOMs (paginated, with product, type, and status filtering).
* `POST /boms`: Create a draft BOM revision.
* `GET /boms/{id}`: Detailed view of BOM items, sequence, scrap factors, and child assemblies.
* `PUT /boms/{id}`: Update draft/under-revision BOM items and headers.
* `POST /boms/{id}/submit`: Transition draft BOM to `pending_approval`.
* `POST /boms/{id}/approve`: Approve BOM revision (deactivates older revisions).
* `POST /boms/{id}/clone`: Clone existing BOM into a new minor or major revision.

---

## 4. Routings & Operations

* `GET /routings`: Paginated list of production routings.
* `POST /routings`: Create a new routing and its sequential operations.
* `GET /routings/{id}`: Detail view of routing sequence, work centers, setup/cycle times.
* `PUT /routings/{id}`: Update routing operations.

---

## 5. Production Master Plans

* `GET /plans`: Paginated list of production plans.
* `POST /plans`: Create a master production plan.
* `GET /plans/{id}`: Plan detail with target schedules and demand requirements.
* `PUT /plans/{id}`: Edit draft plan parameters.
* `POST /plans/{id}/submit`: Submit draft plan for formal review.
* `POST /plans/{id}/approve`: Approve production plan for MRP generation.

---

## 6. Work Centers & Machines

* `GET /work-centers`: Paginated list of shop floor work centers.
* `POST /work-centers`: Register a new work center with capacity and cost per hour.
* `GET /work-centers/{id}`: Work center detail including assigned machines.
* `PUT /work-centers/{id}`: Update work center parameters.
* `GET /machines`: Paginated list of machines and their live operating state.
* `POST /machines`: Register a machine under a work center.
* `GET /machines/{id}`: Machine detail, capacity, and current state.
* `PUT /machines/{id}`: Update machine specifications.

---

## 7. MES Execution (Shop Floor Terminals)

* `GET /mes/queue`: Operator work queue filterable by `work_center_id` and `machine_id`.
* `POST /mes/operations/{id}/start`: Start execution on an operation.
* `POST /mes/operations/{id}/pause`: Pause execution with reason code.
* `POST /mes/operations/{id}/resume`: Resume paused operation.
* `POST /mes/operations/{id}/complete`: Finalize operation and advance sequence to next step.
* `POST /mes/downtime/start`: Log machine downtime start incident with work center, machine, and reason code.
* `POST /mes/downtime/{id}/end`: End active machine downtime incident.

---

## 8. Quality Inspections

* `GET /quality/inspections`: List pending and completed in-process quality inspections.
* `GET /quality/inspections/{id}`: Inspection checklist and criteria details.
* `POST /quality/inspections/{id}/submit`: Submit pass/fail criteria measurement results.
* `POST /quality/orders/{order}/quick-check`: Fast operator inline quality check from shopfloor tablet.
