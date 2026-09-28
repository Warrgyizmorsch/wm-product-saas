# Production Module REST API Endpoints Reference (v1)

All endpoints are prefixed by `/api/v1/production`.

## Standard Headers

Every request to an authenticated Production API endpoint requires:
* `Accept: application/json`
* `Content-Type: application/json`
* `X-API-SECRET: <your-configured-api-secret>`
* `Authorization: Bearer <sanctum-personal-access-token>`
* `X-Tenant: <tenant-slug>` (e.g. `acme-corp`)
* `Idempotency-Key: <unique-uuid-or-hash>` (Mandatory or strongly recommended for POST/PUT state mutations)

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
  "message": "Resources retrieved successfully.",
  "data": [
    { "id": 1, "order_number": "PO-2026-0001", "status": "draft" }
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "to": 25,
    "per_page": 25,
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

* `GET /dashboard`: High-level aggregated production KPIs (Active orders, shop floor operations, master resources).
* `GET /dashboard/metrics`: Detailed executive & shopfloor time-series metrics (OEE, production summary, utilizations, scrap stats, Six Big Losses, downtime rate). Query: `from_date`, `to_date`, `work_center_id`.
* `GET /dashboard/alerts`: Open shop floor blockers, alert configurations, timeline events, and quality holds.

---

## 2. Engineering Masters (BOM & Routings)

### Bill of Materials (BOM)
* `GET /boms`: List BOMs (paginated, with product, type, and status filtering).
* `POST /boms`: Create a draft BOM revision.
* `GET /boms/{id}`: Detailed view of BOM items, sequence, scrap factors, and child assemblies.
* `PUT /boms/{id}`: Update draft/under-revision BOM items and headers.
* `POST /boms/{id}/submit`: Transition draft BOM to `pending_approval`.
* `POST /boms/{id}/approve`: Approve BOM revision (deactivates older revisions).
* `POST /boms/{id}/reject`: Reject pending-approval BOM revision and return to draft. Body: `rejection_reason`.
* `POST /boms/{id}/cancel`: Cancel active or draft BOM. Body: `cancellation_reason`.
* `POST /boms/{id}/clone`: Clone existing BOM into a new minor or major revision.
* `GET /boms/export`: Export BOMs spreadsheet (`?format=xlsx` or `csv`).
* `POST /boms/import`: Bulk import BOMs from CSV/XLSX (`multipart/form-data`, strategies: `create`, `update`).

### Routings & Operations
* `GET /routings`: Paginated list of production routings.
* `POST /routings`: Create a new routing and its sequential operations.
* `GET /routings/{id}`: Detail view of routing sequence, work centers, setup/cycle times.
* `PUT /routings/{id}`: Update routing operations.
* `GET /routings/{id}/operations`: List sequenced operations belonging to routing.
* `POST /routings/{id}/submit`: Submit routing for approval.
* `POST /routings/{id}/approve`: Approve submitted routing.
* `POST /routings/{id}/reject`: Reject submitted routing back to draft.
* `POST /routings/{id}/cancel`: Cancel active or draft routing.
* `POST /routings/{id}/duplicate`: Clone routing to new version.
* `DELETE /routings/{id}`: Delete unapproved draft routing.
* `GET /routings/export`: Export routings spreadsheet (`?format=xlsx` or `csv`).
* `POST /routings/import`: Bulk import routings from CSV/XLSX.

---

## 3. Plant Resources (Work Centers, Machines & Shifts)

### Work Centers
* `GET /work-centers`: Paginated list of shop floor work centers.
* `POST /work-centers`: Register a new work center with capacity and cost per hour.
* `GET /work-centers/{id}`: Work center detail including assigned machines.
* `PUT /work-centers/{id}`: Update work center parameters.
* `DELETE /work-centers/{id}`: Delete unreferenced work center.
* `GET /work-centers/export`: Export work centers spreadsheet.
* `POST /work-centers/import`: Bulk import work centers from CSV/XLSX.

### Machines
* `GET /machines`: Paginated list of machines and their live operating state.
* `POST /machines`: Register a machine under a work center.
* `GET /machines/{id}`: Machine detail, capacity, and current state.
* `PUT /machines/{id}`: Update machine specifications.
* `DELETE /machines/{id}`: Delete unreferenced machine.
* `POST /machines/{id}/link-asset`: Link fixed asset accounting ID. Body: `asset_id`.
* `POST /machines/{id}/unlink-asset`: Unlink fixed asset from machine.
* `GET /machines/export`: Export machines spreadsheet.
* `POST /machines/import`: Bulk import machines from CSV/XLSX.

### Production Shifts
* `GET /shifts`: List work center operating shifts. Query: `work_center_id`, `is_active`.
* `POST /shifts`: Create operating shift window with capacity factor and break minutes.
* `PUT /shifts/{id}`: Update shift times, working days, and capacity factors.
* `DELETE /shifts/{id}`: Delete an unused production shift.

---

## 4. Production Plans (MPS / MRP)

* `GET /plans`: Paginated list of master production plans.
* `POST /plans`: Create master production plan.
* `GET /plans/{id}`: Detailed plan view with target items and status.
* `PUT /plans/{id}`: Update draft plan headers and items.
* `POST /plans/{id}/submit`: Submit draft plan for formal approval.
* `POST /plans/{id}/approve`: Approve submitted plan for execution.
* `POST /plans/{id}/reject`: Reject submitted plan back to draft. Body: `rejection_reason`.
* `POST /plans/{id}/cancel`: Cancel plan. Body: `cancellation_reason`.
* `POST /plans/{id}/release`: Release approved plan to shop floor.
* `POST /plans/{id}/complete`: Complete all plan items.
* `POST /plans/{id}/close`: Formally close completed plan.
* `DELETE /plans/{id}`: Delete unreleased draft plan.
* `POST /plans/{id}/create-order`: Generate production work orders from plan items. Body: `start_date`, `end_date`.
* `POST /plans/{id}/run-mrp`: Run Material Requirements Planning engine for plan.
* `GET /plans/export`: Export production plans spreadsheet (`?format=xlsx` or `csv`).

---

## 5. Production Orders

* `GET /orders`: Paginated list of production orders with comprehensive filtering (`status`, `product_id`, `date range`, `search`).
* `POST /orders`: Create a production work order.
* `GET /orders/{id}`: Comprehensive detail view (operations, reservations, issues, remnants, progress, scrap).
* `PUT /orders/{id}`: Update draft production order parameters.
* `POST /orders/{id}/release`: Release order to shop floor. Allocates stock and initializes operations.
* `POST /orders/{id}/cancel`: Cancel active/released order. Body: `reason`.
* `POST /orders/{id}/close`: Close completed production order.
* `POST /orders/{id}/issue-material`: Issue reserved raw materials to order.
* `POST /orders/{id}/return-material`: Return issued excess materials to warehouse. Body: `reservation_id`, `quantity`, `warehouse_id`, `remarks`.
* `POST /orders/{id}/request-additional-material`: Request unplanned material reservation. Body: `items` array (`product_id`, `quantity`, `reason`).
* `POST /orders/{id}/remnants`: Record reusable offcut / remnant generated during execution.
* `POST /orders/{id}/allocate-remnant`: Allocate available remnant to order.
* `POST /orders/{id}/release-remnant-allocation/{allocation}`: Release unconsumed remnant back to pool.
* `POST /orders/{id}/consume-remnant/{allocation}`: Consume allocated remnant, deducting stock.
* `POST /orders/{id}/progress`: Log order progress (completed quantity, scrap, rework).
* `POST /orders/{id}/scrap`: Record scrapped quantity with failure reason code.
* `POST /orders/{id}/rework`: Log rework operation details and quantity. Body: `operation_id`, `quantity`, `reason`.
* `POST /orders/{id}/receive-fg`: Receive finished goods into target warehouse.
* `POST /orders/{id}/complete`: Complete all remaining operations and finalize order.
* `GET /orders/export`: Export production orders spreadsheet (`?format=xlsx` or `csv`).

---

## 6. MES Execution (Shop Floor Terminals)

* `GET /mes/queue`: Operator work queue filterable by `work_center_id` and `machine_id`.
* `POST /mes/operations/{id}/start`: Start execution on an operation.
* `POST /mes/operations/{id}/pause`: Pause execution with reason code.
* `POST /mes/operations/{id}/resume`: Resume paused operation.
* `POST /mes/operations/{id}/complete`: Finalize operation and advance sequence to next step.
* `POST /mes/operations/{id}/hold`: Place in-progress operation on quality or tooling hold. Body: `reason`.
* `POST /mes/operations/{id}/progress`: Log operator piece progress. Body: `quantity_produced`, `quantity_rejected`, `notes`.
* `POST /mes/operations/{id}/andon-alert`: Trigger shop floor Andon alert notification. Body: `alert_type`, `severity`, `message`.
* `POST /mes/operations/{id}/scrap`: Log scrap directly from MES terminal. Body: `quantity`, `reason_code`, `notes`.
* `POST /mes/downtime/start`: Log machine downtime start incident with work center, machine, and reason code.
* `POST /mes/downtime/{id}/end`: End active machine downtime incident.

---

## 7. Quality Management

### Inspections & Quick Check
* `GET /quality/inspections`: List pending and completed in-process quality inspections.
* `POST /quality/inspections`: Create an in-process inspection record.
* `GET /quality/inspections/{id}`: Inspection checklist and criteria details.
* `POST /quality/inspections/{id}/submit`: Submit pass/fail criteria measurement results.
* `POST /quality/inspections/{id}/approve`: Approve quality inspection.
* `POST /quality/orders/{order}/quick-check`: Fast operator inline quality check from shopfloor tablet.

### Quality Plans
* `GET /quality-plans`: List quality plans with pagination and status filters.
* `POST /quality-plans`: Create a quality inspection plan with check parameters.
* `GET /quality-plans/{id}`: Detailed plan view with parameters and tolerances.
* `PUT /quality-plans/{id}`: Update quality inspection plan.
* `DELETE /quality-plans/{id}`: Delete an unused quality plan.

### Non-Conformance Reports (NCR) & Scrap Disposal
* `GET /quality/ncrs`: List non-conformance reports.
* `POST /quality/ncrs`: Create an NCR against a production order or lot.
* `GET /quality/ncrs/{id}`: NCR detail, root cause, and disposition status.
* `POST /quality/ncrs/{id}/disposition`: Formally disposition non-conforming items (`use_as_is`, `rework`, `scrap`, `return_to_vendor`).
* `POST /quality/ncrs/{id}/close`: Close resolved NCR with electronic signature.
* `POST /quality/scrap/{id}/approve`: Approve scrap disposal workflow.

---

## 8. Work-in-Progress (WIP) Tracking

*Strict non-monetary design: all responses strictly report quantities, location stages, and physical progression without monetary valuation.*

* `GET /wip`: List shop-floor WIP balances across orders and work centers.
* `GET /wip/{id}`: Detailed WIP stage tracking, available balance, and routing position.
* `POST /wip/{id}/transfer`: Transfer physical WIP quantity to successor routing operation. Body: `from_operation_id`, `to_operation_id`, `quantity`, `remarks`.
* `POST /wip/{id}/convert`: Convert final WIP into finished goods inventory inflow. Body: `warehouse_id`, `quality_status`, `remarks`.
* `GET /wip/export`: Export WIP tracking records spreadsheet (`?format=xlsx` or `csv`).

---

## 9. Production Scheduling

*Finite-capacity engine respecting work center calendars, machine capabilities, parallel operations, and shift windows.*

* `GET /schedules`: List finite-capacity schedules.
* `POST /schedules`: Generate schedule for an order. Body: `production_order_id`, `scheduling_type` (`forward` or `backward`), `start_date`, `notes`.
* `GET /schedules/{id}`: Detailed schedule timeline with operation slot allocations.
* `POST /schedules/{id}/release`: Release schedule to activate operations on the shop floor.
* `POST /schedules/{id}/cancel`: Cancel schedule allocations.
* `GET /schedules/export`: Export schedules spreadsheet (`?format=xlsx` or `csv`).

---

## 10. Plant Maintenance

* `GET /maintenance/work-orders`: List maintenance work orders with machine and status filters.
* `POST /maintenance/work-orders`: Schedule preventive or standard work order.
* `POST /maintenance/work-orders/breakdown`: Log emergency breakdown work order.
* `GET /maintenance/work-orders/{id}`: Detailed work order view, downtime duration, and parts.
* `POST /maintenance/work-orders/{id}/complete`: Formally complete work order. Body: `resolution_notes`.
* `POST /maintenance/work-orders/{id}/cancel`: Cancel maintenance work order. Body: `reason`.
