# Production Module — Status & State Machine Specification Guide

> **Domain Location:** `app/Domains/Production`  
> **Documentation Root:** `app/Domains/Production/docs/STATUS_LIFECYCLE.md`  
> **Purpose:** Authoritative reference for all entity lifecycle state machines, allowed transitions, and validation guards.

---

## 1. Production Order State Machine

```mermaid
stateDiagram-v2
    [*] --> Draft: Order Created (Direct or Plan)
    Draft --> Scheduled: Schedule Generated
    Scheduled --> Released: Released to Shopfloor (Materials Issued)
    Draft --> Released: Force Release (Admin override)
    Released --> In_Progress: First Operation Started
    In_Progress --> Completed: All Operations Completed & FG Received
    Completed --> Closed: Financial Accounts Reconciled
    Draft --> Cancelled: Cancelled by Planner
    Scheduled --> Cancelled: Cancelled by Planner
    Released --> Cancelled: Cancelled (Unissued stock returned)
```

### Transition Table: Production Order
| From State | To State | Trigger / UI Action | Validation Rules | Database & System Effect |
|---|---|---|---|---|
| `draft` | `scheduled` | SchedService generates schedule | Order must have valid product and quantity > 0 | Generates `production_schedules` row. |
| `draft` / `scheduled` | `released` | Click "Release to Shopfloor" | Raw materials must be issued by store; machines operational | Sets `released_at = now()`; initializes `ProductionWip`; unlocks first operation as `ready`. |
| `released` | `in_progress` | Operator clicks "Start" in MES | Predecessor dependencies met | Sets `actual_start_date = now()`. |
| `in_progress` | `completed` | Final operation completed & FG received | All operations must be `completed`, `skipped`, or `cancelled` | Sets `completed_at = now()`, `actual_end_date = now()`. |
| `completed` | `closed` | Click "Close Order" | No open NCRs or WIP discrepancies | Sets `closed_at = now()`. Order becomes completely immutable. |
| Any open | `cancelled` | Click "Cancel Order" | Order cannot be completed or closed | Releases raw material reservations; cancels pending schedules. |

---

## 2. Production Order Operation State Machine

```mermaid
stateDiagram-v2
    [*] --> Waiting: Successor Operation Created
    [*] --> Ready: First Operation in Sequence
    Waiting --> Ready: Predecessor Completed / Transfer Batch Logged
    Ready --> In_Progress: Operator Clicks "Start" (POST /mes/{op}/start)
    In_Progress --> Paused: Operator Clicks "Pause" (POST /mes/{op}/pause)
    Paused --> In_Progress: Operator Clicks "Resume" (POST /mes/{op}/resume)
    In_Progress --> Quality_Hold: Quality Required Gate Triggered
    Quality_Hold --> In_Progress: QC Passed
    In_Progress --> Completed: Operator Clicks "Complete" (Target Reached)
    Ready --> Skipped: Engineering Override (Supervisor)
    Ready --> Cancelled: Order Cancelled
```

### Transition Table: Operations
| From State | To State | Trigger | Validation | Next State Allowed |
|---|---|---|---|---|
| `waiting` | `ready` | Predecessor operation marked `completed` or transfer batch met | Dependency condition met | `in_progress`, `skipped` |
| `ready` | `in_progress` | Operator clicks "Start" in MES | Machine must be active and not in breakdown | `paused`, `completed`, `on_hold` |
| `in_progress` | `paused` | Operator clicks "Pause" | Pause reason mandatory | `in_progress` |
| `in_progress` | `completed` | Operator clicks "Complete" | Quality gate passed if `quality_required = true` | Unlocks successor operation to `ready` |

---

## 3. Work-in-Progress (WIP) State Machine

```mermaid
stateDiagram-v2
    [*] --> Active: Order Released (initializeWip)
    Active --> Quality_Hold: Sent to Inspection (sendToQuality)
    Quality_Hold --> Active: Inspection Passed (disposeInspection: passed)
    Quality_Hold --> Rework: Inspection Defect (disposeInspection: rework)
    Rework --> Active: Rework Completed & Re-inspected
    Active --> Transferred: Moved to Next Operation (transferWip)
    Active --> Completed: Final Stage Converted to FG (convertWipToFinishedGoods)
```

---

## 4. Quality Inspection State Machine

```mermaid
stateDiagram-v2
    [*] --> Pending: "Run QC" Opened
    Pending --> Passed: Inspector Enters Measurements within Tolerance
    Pending --> Failed: Inspector Rejects Out of Spec Units
    Pending --> Hold: Awaiting Lab / Metallurgical Test
    Hold --> Passed: Secondary Test Passes
    Hold --> Failed: Secondary Test Fails
```

---

## 5. Subcontract Delivery Challan State Machine

```mermaid
stateDiagram-v2
    [*] --> Draft: Challan Created (/subcontract/delivery-challans/create)
    Draft --> Dispatched: Security Gate Dispatches (POST .../dispatch)
    Dispatched --> Received: Receiving Dock Confirms Return (POST .../receive)
    Draft --> Cancelled: Cancelled before physical dispatch
```
