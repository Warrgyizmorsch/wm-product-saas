# Production Planning — Dining Table Manufacturing Demo Reference

## 1. Demo Purpose

This document defines the official business logic, master data structure, quantity semantics, and execution behavior for the **Dining Table Manufacturing Demo Dataset** (`app/Domains/Production`).

### Core Business Rule
> **Production Order Finished Good quantity MUST NOT automatically become the execution quantity of every routing operation.**

Every operation in a manufacturing routing executes against the quantity of the **actual item/component/subassembly being processed by that operation**.

#### Example
A Production Order for **10 Industrial Dining Tables** (`FG-TBL-001`) must naturally derive component-level operation targets:
- **40 Table Legs** (`SFG-TBL-LEG`)
- **20 Horizontal Support Beams** (`SFG-TBL-SUPPORT`)
- **10 Table Frames** (`SFG-TBL-FRAME`)
- **10 Table Tops** (`SFG-TBL-TOP`)
- **10 Frame Finishings** (`SFG-TBL-FRAME`)
- **10 Finished Dining Tables** (`FG-TBL-001`)

---

## 2. Product Structure & BOM Hierarchy

| SKU | Name | Type | Planning Type | UOM | Cost Price | Selling Price | Opening Stock | Linked Routing |
| --- | --- | --- | --- | :---: | :---: | :---: | :---: | :---: |
| `FG-TBL-001` | **Industrial Dining Table** | `finished_good` | `manufacture` | PCS | ₹10,200.00 | ₹18,500.00 | 0.00 PCS | `RT-TBL-FG` |
| `SFG-TBL-FRAME` | **Table Frame Assembly** | `semi_finished` | `manufacture` | PCS | ₹4,500.00 | ₹6,800.00 | 0.00 PCS | `RT-TBL-FRAME` |
| `SFG-TBL-LEG` | **Table Leg Heavy Steel** | `semi_finished` | `manufacture` | PCS | ₹650.00 | ₹950.00 | 0.00 PCS | `RT-TBL-LEG` |
| `SFG-TBL-SUPPORT` | **Horizontal Support Beam** | `semi_finished` | `manufacture` | PCS | ₹400.00 | ₹600.00 | 0.00 PCS | `RT-TBL-SUPPORT` |
| `SFG-TBL-TOP` | **Engineered Wood Table Top** | `semi_finished` | `manufacture` | PCS | ₹3,200.00 | ₹4,800.00 | 0.00 PCS | `RT-TBL-TOP` |
| `RM-TBL-PIPE` | **Steel Square Pipe Heavy Stock** | `raw_material` | `purchase` | MTR | ₹300.00 | ₹0.00 | 200.00 MTR | - |
| `RM-TBL-TOP-BOARD` | **Engineered Wood Table Top Board** | `raw_material` | `purchase` | PCS | ₹2,200.00 | ₹0.00 | 50.00 PCS | - |
| `RM-TBL-FASTENER` | **Dining Table Fastener Set** | `raw_material` | `purchase` | PCS | ₹250.00 | ₹0.00 | 100.00 PCS | - |

### Multi-Level BOM Tree & Child BOM Linkage (`child_bom_id`)
```text
Industrial Dining Table (FG-TBL-001) [BOM: BOM-TBL-FG | Routing: RT-TBL-FG]
├── Table Frame Assembly (SFG-TBL-FRAME) × 1.0 PCS [Child BOM: BOM-TBL-FRAME | Routing: RT-TBL-FRAME]
│   ├── Table Leg Heavy Steel (SFG-TBL-LEG) × 4.0 PCS [Child BOM: BOM-TBL-LEG | Routing: RT-TBL-LEG]
│   │   └── Steel Square Pipe Heavy Stock (RM-TBL-PIPE) = 0.75 MTR
│   └── Horizontal Support Beam (SFG-TBL-SUPPORT) × 2.0 PCS [Child BOM: BOM-TBL-SUPPORT | Routing: RT-TBL-SUPPORT]
│       └── Steel Square Pipe Heavy Stock (RM-TBL-PIPE) = 0.60 MTR
├── Engineered Wood Table Top (SFG-TBL-TOP) × 1.0 PCS [Child BOM: BOM-TBL-TOP | Routing: RT-TBL-TOP]
│   └── Engineered Wood Table Top Board (RM-TBL-TOP-BOARD) = 1.0 PCS
└── Dining Table Fastener Set (RM-TBL-FASTENER) × 1.0 PCS
```

Every manufactured component item in `ProductionBomItem` explicitly populates:
- `child_bom_id`: References the child component's `ProductionBom` ID.
- `routing_id` on `ProductionBom`: References the corresponding `Routing` ID for that component.

---

## 3. Operation Structure

| Sequence | Operation Number | Operation Name | Processing / Output Item | Consumed Material Inputs (`RoutingOperationMaterial`) | Work Center | Machine | Setup Time | Unit Cycle Time |
| :---: | --- | --- | --- | --- | --- | --- | :---: | :---: |
| **10** | `OP10` | Table Leg Pipe Cutting | **Table Leg Heavy Steel** (`SFG-TBL-LEG`) | `RM-TBL-PIPE` (0.75 MTR) | Tube & Component Cutting (`WC-TBL-CUT`) | Tube Cutting Machine 01 (`MAC-TBL-CUT-01`) | 10 Min | 2.0 Min / Leg |
| **20** | `OP20` | Horizontal Support Cutting | **Horizontal Support Beam** (`SFG-TBL-SUPPORT`) | `RM-TBL-PIPE` (0.60 MTR) | Tube & Component Cutting (`WC-TBL-CUT`) | Tube Cutting Machine 01 (`MAC-TBL-CUT-01`) | 10 Min | 2.0 Min / Support |
| **30** | `OP30` | Frame MIG Welding Assembly | **Table Frame Assembly** (`SFG-TBL-FRAME`) | `SFG-TBL-LEG` (4.0 PCS) + `SFG-TBL-SUPPORT` (2.0 PCS) | Frame Welding & Fabrication (`WC-TBL-WELD`) | MIG Welding Station 01 (`MAC-TBL-WELD-01`) | 15 Min | 12.0 Min / Frame |
| **40** | `OP40` | Table Top Panel Processing | **Engineered Wood Table Top** (`SFG-TBL-TOP`) | `RM-TBL-TOP-BOARD` (1.0 PCS) | Table Top Processing (`WC-TBL-TOP`) | Panel Saw & Edge Bander 01 (`MAC-TBL-TOP-01`) | 10 Min | 8.0 Min / Top |
| **50** | `OP50` | Frame Surface Finishing | **Table Frame Assembly** (`SFG-TBL-FRAME`) | *(WIP Table Frame Assembly)* | Surface Finishing (`WC-TBL-FINISH`) | Grinding & Surface Finishing Bay (`MAC-TBL-FIN-01`) | 10 Min | 6.0 Min / Frame |
| **60** | `OP60` | Final Dining Table Assembly | **Industrial Dining Table** (`FG-TBL-001`) | `SFG-TBL-FRAME` (1.0 PCS) + `SFG-TBL-TOP` (1.0 PCS) + `RM-TBL-FASTENER` (1.0 SET) | Dining Table Final Assembly (`WC-TBL-ASSY`) | Final Fitting Assembly Station (`MAC-TBL-ASSY-01`) | 10 Min | 15.0 Min / Table |

---

## 4. Quantity Semantics

The Production engine maintains a strict separation between four quantitative concepts:

1. `ProductionOrder.quantity_ordered`:
   Represents the Finished Good demand quantity (e.g. **10 Industrial Dining Tables**).
2. `ProductionOrderOperation.target_produced_qty`:
   Represents the required **net good output quantity** of the operation's specific processing item (e.g. **40 Legs** for OP10, **10 Tables** for OP60).
3. `RoutingOperationMaterial`:
   Represents the **material or child component inputs** staged and consumed at that operation stage per unit of output (e.g. 0.75 MTR Steel Pipe per Leg).
4. **Raw Material Input Demand**:
   Calculated as $Target Produced Qty \times Input Rate$. Raw material demand and component output target must **NEVER** be confused or mixed.

### Example (OP10 Pipe Cutting)
- **Input Demand**: $40 \text{ Legs} \times 0.75 \text{ MTR} = \mathbf{30.0\text{ MTR Steel Square Pipe}}$
- **Processing Output Target**: $\mathbf{40.0\text{ PCS Table Legs}}$

---

## 5. 10-Table Reference Matrix

When a Production Order is created for **10 Industrial Dining Tables**:

| Operation | Processing Item | SKU | Component Ratio / FG | Target Output (`target_produced_qty`) | UOM |
| :---: | --- | --- | :---: | :---: | :---: |
| **OP10** | Table Leg Heavy Steel | `SFG-TBL-LEG` | 4.0 | **40.0 PCS** | PCS |
| **OP20** | Horizontal Support Beam | `SFG-TBL-SUPPORT` | 2.0 | **20.0 PCS** | PCS |
| **OP30** | Table Frame Assembly | `SFG-TBL-FRAME` | 1.0 | **10.0 PCS** | PCS |
| **OP40** | Engineered Wood Table Top | `SFG-TBL-TOP` | 1.0 | **10.0 PCS** | PCS |
| **OP50** | Table Frame Assembly | `SFG-TBL-FRAME` | 1.0 | **10.0 PCS** | PCS |
| **OP60** | Industrial Dining Table | `FG-TBL-001` | 1.0 | **10.0 PCS** | PCS |

---

## 6. Material Requirement Reference

For **10 Industrial Dining Tables**:

- **Steel Square Pipe (`RM-TBL-PIPE`)**:
  - OP10 Leg Cutting: $40 \text{ Legs} \times 0.75 \text{ MTR} = 30.0 \text{ MTR}$
  - OP20 Support Cutting: $20 \text{ Supports} \times 0.60 \text{ MTR} = 12.0 \text{ MTR}$
  - **Total Raw Pipe Requirement**: $\mathbf{42.0\text{ MTR}}$
- **Engineered Wood Table Top Board (`RM-TBL-TOP-BOARD`)**:
  - OP40 Top Processing: $10 \text{ Tops} \times 1.0 \text{ PCS} = \mathbf{10.0\text{ PCS}}$
- **Dining Table Fastener Set (`RM-TBL-FASTENER`)**:
  - OP60 Final Assembly: $10 \text{ Tables} \times 1.0 \text{ SET} = \mathbf{10.0\text{ SETS}}$

---

## 7. Scheduling Reference

Planned operation runtimes calculate strictly using component target quantities without double-scaling:

$$\text{Planned Duration} = \text{Setup Time} + (\text{Processing Target} \times \text{Unit Cycle Time})$$

- **OP10 Leg Cutting**: $10 \text{ min} + (40 \times 2.0 \text{ min}) = \mathbf{90.0\text{ Minutes}}$ (NOT $90 \times 10 = 900$)
- **OP20 Support Cutting**: $10 \text{ min} + (20 \times 2.0 \text{ min}) = \mathbf{50.0\text{ Minutes}}$
- **OP30 Frame Welding**: $15 \text{ min} + (10 \times 12.0 \text{ min}) = \mathbf{135.0\text{ Minutes}}$
- **OP40 Top Processing**: $10 \text{ min} + (10 \times 8.0 \text{ min}) = \mathbf{90.0\text{ Minutes}}$
- **OP50 Frame Finishing**: $10 \text{ min} + (10 \times 6.0 \text{ min}) = \mathbf{70.0\text{ Minutes}}$
- **OP60 Final Assembly**: $10 \text{ min} + (10 \times 15.0 \text{ min}) = \mathbf{160.0\text{ Minutes}}$

---

## 8. MES Execution Reference

In the Shop Floor MES Interface:

### Operator View Context (OP10 Pipe Cutting)
- **Order Context**: `10 Industrial Dining Tables` (`MO-TBL-10`)
- **Processing Item**: `Table Leg Heavy Steel` (`SFG-TBL-LEG`)
- **Operation Target**: `40 PCS`
- **Logged Output Progress**: Incremental logging (e.g. 15, then 10, then 15) reaches `40 / 40 PCS`.

### Core MES Execution Rule
> Logging progress on intermediate component operations (OP10–OP50) increments the operation's `quantity_produced` ONLY. Parent Production Order `quantity_produced` remains strictly **0.00** until final FG receipt.

---

## 9. WIP / QC / Costing / FG Rules

1. **WIP Movement**: SFGs move through WIP genealogy (`cross_assembly` dependencies in `ProductionOrderOperationDependency`). They are NOT treated as purchased raw materials.
2. **Quality Control**: Inspections execute against the processing item count (e.g. Frame QC inspects **10 Frames**, Final QC inspects **10 Tables**).
3. **Scrap & Rework**: If 2 Legs are scrapped, OP10 target remains **40 Good Legs**. Replacements must be logged to complete the operation.
4. **Order Costing**: `ProductionCostService` aggregates 42 MTR Steel Pipe + 10 Boards + 10 Fasteners + machine/labor runtimes, dividing total cost over **10 Finished Tables**.
5. **FG Receipt**: `ProductionExecutionService::completeOrder()` updates `ProductionOrder.quantity_produced = 10.00` and posts **10 Finished Tables** to inventory ONLY at final completion.

---

## 10. Seeder Files

- [`database/seeders/TableManufacturingProductSeeder.php`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/database/seeders/TableManufacturingProductSeeder.php): Creates Products, UOMs, Warehouses, COA entries, and initial raw material stock.
- [`database/seeders/TableManufacturingProductionSeeder.php`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/database/seeders/TableManufacturingProductionSeeder.php): Creates Work Centers, Machines, Multi-Level BOMs with `child_bom_id` and `routing_id` linkages, Routings, and Operation Materials.
- Registered in [`database/seeders/DatabaseSeeder.php`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/database/seeders/DatabaseSeeder.php) (Poona Radiators seeders retained in code but commented out).

---

## 11. Automated Test Suite

The following feature tests safeguard component-level execution semantics:

1. [`tests/Feature/Production/TableManufacturingSeederTest.php`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/tests/Feature/Production/TableManufacturingSeederTest.php): Tests seeder master data, `child_bom_id` linkage, `routing_id` linkage, idempotency, and 10-table component target calculations.
2. [`tests/Feature/Production/TableManufacturingE2EValidationTest.php`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/tests/Feature/Production/TableManufacturingE2EValidationTest.php): Validates full 24-point E2E lifecycle (Order $\to$ Snapshot $\to$ Schedule $\to$ MES $\to$ Costing $\to$ FG Receipt).
3. [`tests/Feature/Production/ComponentLevelOperationQuantityTest.php`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/tests/Feature/Production/ComponentLevelOperationQuantityTest.php): Verifies component target scaling, scheduling without double multiplication, and MES isolation.
4. [`tests/Feature/Production/ComplexIndustrialManufacturingScenarioTest.php`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/tests/Feature/Production/ComplexIndustrialManufacturingScenarioTest.php): Validates multi-level industrial assembly (Jaw Crusher).

---

## 12. Regression Warning

> [!WARNING]
> **REGRESSION RULE**: Any future code change that causes a 10-table Production Order to derive anything other than:
> - **40 Table Legs**
> - **20 Horizontal Supports**
> - **10 Table Frames**
> - **10 Table Tops**
> - **10 Finished Dining Tables**
> 
> MUST be treated as a critical Production calculation regression and reverted immediately!

---

## 13. Strictly Forbidden Anti-Patterns

- ❌ Setting `target_produced_qty = quantity_ordered` for every operation blindly.
- ❌ Omitting `child_bom_id` on sub-assembly BOM items, breaking the BOM Details tree explosion.
- ❌ Omitting `routing_id` on `ProductionBom`, resulting in "No Routing" on the BOM Details header.
- ❌ Using `RoutingOperationMaterial` output items as fake consumed raw materials.
- ❌ Mixing raw material UOMs (e.g. 42 MTR Steel Pipe) with component piece counts (60 pieces).
- ❌ Manually hardcoding operation targets in database queries instead of deriving via BOM ratio explosion.
- ❌ Incrementing Finished Good produced quantity on intermediate MES progress logs.
- ❌ Recalculating snapshot targets on released orders from live BOM edits.
