# Production Planning & Manufacturing Execution — Industry & Technical Glossary

> **Module:** Production Planning & MES (`wm-product-saas`)  
> **Target Document:** `docs/user-manuals/production/GLOSSARY.md`  
> **Audience:** Manufacturing Planners, Developers, Business Analysts, Quality Inspectors

---

## A

### Active Currency
The specific currency configured by a tenant for display and user input in the ERP (e.g., USD, INR, EUR, GBP), rendered via `active_currency_symbol()`. Contrasts with *Base Currency*.

### Andon
A visual and audible lean manufacturing signaling system used on the shopfloor to notify supervisors, maintenance, and quality engineers of an immediate problem or stoppage on an active production line.

### Available-to-Promise (ATP)
The uncommitted portion of a company's inventory and planned production, calculated by subtracting active reservations from on-hand physical stock.

---

## B

### Base Currency
The canonical underlying financial denomination used for mathematical calculations, internal rate evaluation, and database column storage across all tenant entities. Amounts entered in an active currency are normalized via `convert_to_base()` prior to persistence.

### Bill of Materials (BOM)
A formal, hierarchical engineering recipe listing all raw materials, sub-assemblies, intermediate parts, and quantities required to produce one unit of a finished product.

### Breakdown Maintenance (Corrective Maintenance)
Unscheduled maintenance performed to restore machinery or equipment that has ceased operating due to an unforeseen component or mechanical failure.

---

## C

### Capacity Leveling
An algorithmic scheduling process that shifts non-critical operations across work center timeline slots to eliminate resource over-utilization without violating sequential operation dependencies.

### Corrective and Preventive Action (CAPA)
A formal quality management investigation methodology (incorporating 5-Why and Fishbone analyses) designed to identify the systemic root cause of manufacturing defects and prevent recurrence.

---

## D

### Delivery Challan (Gate Pass)
An official statutory transport voucher accompanying raw materials or semi-finished goods shipped to an external subcontracting vendor, certifying custody transfer without changing ownership.

### Dispatch Board
An interactive visual planning board displaying scheduled work orders mapped against specific plant work centers and machines, allowing drag-and-drop sequencing and capacity adjustment.

---

## F

### Finite Capacity Scheduling
A scheduling methodology that plans production runs against strictly bounded machine and labor constraints, ensuring no work center is scheduled beyond its actual available working hours.

### Fixed Asset Link
A relational database association (`fixed_asset_id` on `machines`) linking a shopfloor production machine to its capitalized financial asset record in the Accounting module.

---

## G

### Genealogy (Lot/Batch)
The bidirectional record of history, application, and location of a manufactured lot, tracing upstream to raw material supplier batches and downstream to customer sales shipments.

---

## M

### Manufacturing Execution System (MES)
The operational software layer connecting high-level ERP planning with physical shopfloor execution, capturing real-time machine runtimes, operator progress logs, and scrap counts.

### Material Requirements Planning (MRP)
A computational planning algorithm that explodes Bills of Materials against master production plans to calculate gross requirements, checks available warehouse stock, and determines net purchasing shortages.

---

## N

### Non-Conformance Report (NCR)
A formal quality record documenting products, parts, or processes that fail to satisfy engineering specifications, initiating disposition review (rework, scrap, or deviation).

---

## O

### Overall Equipment Effectiveness (OEE)
A gold-standard manufacturing productivity metric calculated as:
$$\text{OEE} = \text{Availability} \times \text{Performance} \times \text{Quality}$$

### Operational Scrap
Unavoidable or accidental material loss generated during production operations (machining filings, damaged stampings, or cutting trim) that cannot be reworked.

---

## P

### Parametric BOM Formula
A mathematical expression configured on a BOM item allowing dynamic component quantity calculation based on variable parent dimensions (e.g., length × width × density).

### Preventive Maintenance (PM)
Scheduled inspection, servicing, and component replacement conducted at predetermined calendar intervals or operating hours to prevent unexpected machine breakdowns.

### Production Order
A discrete, authorized manufacturing shopfloor job defining the quantity of finished goods to produce, target delivery dates, material reservations, and required routing operations.

---

## R

### Remnant (Dimensional Offcut)
Usable, off-dimensional material left over from primary cutting operations (e.g., remaining sheet metal plate, bar stock, or fabric) that is cataloged for future order allocation.

### Requisition Slip (Picking List)
A warehouse picking document listing component SKU codes, required quantities, and bin locations, used by storekeepers to physically assemble and issue materials.

### Rework Order
A specialized secondary production order created to salvage defective units rejected during quality inspection through corrective machining or re-assembly.

### Routing
The sequential roadmap of manufacturing operations, standard runtimes, setup durations, machine assignments, and work centers required to produce a product.

---

## S

### Stock Inflow / Outflow
The canonical inventory transactions executed via `StockService` that increment or decrement warehouse `on_hand` inventory balances while preserving an immutable ledger audit trail.

### Subcontracting
The outsourcing of specific intermediate routing operations (e.g., heat treatment, electroplating, painting) to an external specialist vendor.

---

## T

### Tenant Isolation
The architectural security boundary ensuring that all database queries, cache stores, and file assets are strictly constrained to the authenticated customer's `tenant_id`.

---

## W

### Work Center
A distinct physical department, production cell, or machine grouping within a manufacturing facility that possesses measurable capacity and cost rates per hour.

### Work-in-Progress (WIP)
Materials and partially finished components that have been issued from raw material stores and are undergoing processing on the factory floor, but have not yet reached final finished goods completion.
