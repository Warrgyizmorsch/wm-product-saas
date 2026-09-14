# Production Module — Diagnostic & Troubleshooting Guide

> **Domain Location:** `app/Domains/Production`  
> **Documentation Root:** `app/Domains/Production/docs/TROUBLESHOOTING.md`  
> **Purpose:** Practical debugging recipes and diagnostic procedures for real operational exceptions

---

## 1. Issue: Production Order Cannot Be Released

### Symptoms
When clicking **Release to Shopfloor** on `/production/orders/{id}`, the user receives an HTTP redirect error:  
> *"Cannot release order: Raw materials must be fully or partially issued by the store department first."*

### Root Cause Analysis
- `ProductionOrderService::release()` checks the order's requisition slips:
  ```php
  $latestSlip = $order->requisitionSlips()->latest('id')->first();
  $hasIssuedMaterial = in_array(strtolower($latestSlip?->status ?? ''), [
      'fully issued', 'partially issued', 'completed', 'issued', 'partial'
  ]);
  ```
- If the store has not yet picked and issued stock, the release gate blocks execution to prevent staging unsupplied machines.

### Diagnostic SQL
```sql
SELECT id, requisition_number, status 
FROM production_requisition_slips 
WHERE production_order_id = :order_id;
```

### Resolution
1. Navigate to the Inventory Store module and process the pending Requisition Slip.
2. If this is an urgent line trial, an authorized administrator can pass `force=true` or release via the Dispatch Board confirmation prompt.

---

## 2. Issue: Schedule Release Blocked

### Symptoms
Releasing from the Dispatch Board returns an HTTP 422:  
> *"Release blocked due to schedule errors: [Machine is currently in maintenance breakdown]."*

### Root Cause Analysis
`SchedulePreReleaseValidationService::validate()` performs an automated pre-release health audit:
- Confirms machines are active in `production_machines.status`.
- Verifies that no active `production_machine_downtimes` or `production_maintenance_work_orders` overlap with the scheduled runtime.

### Diagnostic SQL
```sql
SELECT id, machine_code, status 
FROM production_machines 
WHERE id IN (
    SELECT machine_id FROM production_schedule_operations 
    WHERE production_schedule_id = :schedule_id
);
```

### Resolution
1. In the Plant Maintenance module, confirm the breakdown work order is completed and machine status is restored to `idle` or `running`.
2. Alternatively, reassign the operation to an alternate machine on the Dispatch Board.

---

## 3. Issue: Operation Missing from MES Shopfloor Console

### Symptoms
The order is released, but the machine operator cannot see Operation 20 on the MES console (`/production/mes`).

### Root Cause Analysis
Operations in a linear routing strictly respect sequential dependencies:
- Operation 20 has `status = 'waiting'` and `previous_operation_id = [Op 10 ID]`.
- An operation only becomes visible in the operator's **Ready** queue once its predecessor reaches `status = 'completed'` or an approved transfer batch quantity is logged!

### Diagnostic SQL
```sql
SELECT id, sequence, operation_number, name, status, previous_operation_id 
FROM production_order_operations 
WHERE production_order_id = :order_id 
ORDER BY sequence ASC;
```

### Resolution
1. Verify that Operation 10 was completed in the MES console.
2. If Operation 10 is completed, check if `reconcileOperationReadiness()` was triggered; opening the order show view `/production/orders/{id}` automatically reconciles readiness.

---

## 4. Issue: "Run QC" Action Missing on Shopfloor

### Symptoms
The operator finishes welding, but no **Run QC** button appears on the MES console.

### Root Cause Analysis
1. The snapshot row `production_order_operations.routing_operation_id` points to a routing step where `quality_required` was left unchecked (`false`).
2. The current authenticated user lacks permission `production.mes.execute`.

### Diagnostic SQL
```sql
SELECT poo.id, poo.name, ro.quality_required 
FROM production_order_operations poo
JOIN production_routing_operations ro ON poo.routing_operation_id = ro.id
WHERE poo.id = :operation_id;
```

### Resolution
- For future orders, update the Master Routing to enable **Quality Required** on that operation.
- For the active in-flight order, an authorized quality lead can execute an ad-hoc inspection via `/production/quality/inspections/create`.

---

## 5. Issue: Completed WIP Cannot Be Converted to Finished Goods

### Symptoms
Clicking **Receive Finished Goods** in WIP tracking throws an error:  
> *"Cannot convert WIP: This tracking card has no remaining completed or available quantity."*

### Root Cause Analysis
1. The final operation has not yet been marked `completed`. Intermediate stage cards cannot produce finished goods inventory.
2. The units were already converted in a previous transaction (the card has `available_quantity = 0`).

### Diagnostic SQL
```sql
SELECT id, product_id, current_routing_operation_id, available_quantity, completed_quantity, status 
FROM production_wips 
WHERE production_order_id = :order_id;
```

### Resolution
- Confirm that the final routing step (e.g. Packaging & Final Sign-Off) is completed.
- Check `production_wip_transactions` to verify whether the units were already received into warehouse inventory.
