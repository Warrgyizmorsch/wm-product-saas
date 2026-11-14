# Client Demo & Requirement Discussion Notes: Production Module

**Context:** Client Demo Preparation & Requirement Decisions  
**Industry:** Polymer Extrusion, Non-Woven Fabric & Converting (Slitting, Lamination, Bags & Masks)  
**Baseline Code Reference:** `main` branch  

---

## 1. Product Master Architecture (Demo Inventory Setup)

### Category A: Raw Materials (RM) — *Purchased in KG*
* **RM-PP: Polypropylene (PP) Granules (Virgin Polymer Seeds / Pellets)**
  * *UOM:* KG
  * *Purpose:* Base plastic resin for extrusion.
* **RM-MB-WHT: White Masterbatch (Color Seeds / Pigment Pellets)**
  * *UOM:* KG
  * *Purpose:* Color additive mixed with base polymer.
* **RM-MB-BLU: Blue Masterbatch (Color Seeds / Pigment Pellets)**
  * *UOM:* KG
  * *Purpose:* Color additive for medical blue fabric.
* **RM-FILLER: Calcium Carbonate (CaCO3) Filler Masterbatch**
  * *UOM:* KG
  * *Purpose:* Cost-optimization and strength additive granules.
* **RM-RECYCLE: In-House Recycled PP Granules (Regrind Pellets)**
  * *UOM:* KG
  * *Purpose:* Recycled granules generated from off-cuts & edge trims.
* **RM-MELTBLOWN: Meltblown BFE 99% Filter Fabric**
  * *UOM:* KG
  * *Purpose:* Middle filtration layer for 3-ply surgical masks.
* **RM-EARLOOP: Elastic Spandex Earloop Cord**
  * *UOM:* KG
  * *Purpose:* Welded ear loops for masks.
* **RM-NOSEWIRE: Polyethylene / Metal Nose Bridge Strip**
  * *UOM:* KG
  * *Purpose:* Bendable nose wire for masks.
* **RM-CORE-TUBE: Heavy-Duty Paper Core Tube 3-inch (Roll Winding)**
  * *UOM:* PCS
  * *Purpose:* Core tube used at winder (Op 20) for winding jumbo and slit rolls.
* **RM-WRAP-FILM: Stretch Wrap Packaging Film (Roll Wrapping)**
  * *UOM:* KG
  * *Purpose:* Stretch wrap film used at packaging (Op 20) for palletizing slit rolls.
* **RM-LABEL-ROLL: Barcode Identification & QC Label (All Ops)**
  * *UOM:* PCS
  * *Purpose:* Barcode tag applied at inspection/QC operations across rolls, bags, and masks.
* **RM-BOX-BAG: Master Corrugated Shipping Box (1,000 D-Cut Bags)**
  * *UOM:* PCS
  * *Purpose:* Outer shipper carton used at Op 30 for packaging 1,000 D-cut bags.
* **RM-BOX-MASK: Printed Inner Dispenser Box (50 3-Ply Masks)**
  * *UOM:* PCS
  * *Purpose:* Inner retail dispenser box used at Op 20 for packaging 50 masks (20 boxes / 1,000 masks).

---

### Category B: Semi-Finished Goods (SFG) — *Produced in Extrusion / Slitting*
* **SFG-ROLL-50-WHT: Non-Woven Spunbond Roll 50 GSM White (100 inch)**
  * *UOM:* KG
  * *Process:* Output of Extrusion Line 1. Parent roll for slitting/bag making.
* **SFG-ROLL-50-BLU: Non-Woven Spunbond Roll 50 GSM Blue (100 inch)**
  * *UOM:* KG
  * *Process:* Output of Extrusion Line 1.
* **SFG-ROLL-20-WHT: Non-Woven Spunbond Roll 20 GSM White (63 inch)**
  * *UOM:* KG
  * *Process:* Output of Extrusion Line 2 (inner layer for masks).
* **SFG-ROLL-20-BLU: Non-Woven Spunbond Roll 20 GSM Blue (63 inch)**
  * *UOM:* KG
  * *Process:* Output of Extrusion Line 2 (outer layer for masks).
* **SFG-SLIT-50-50IN: Non-Woven Slit Roll 50 GSM White (50 inch)**
  * *UOM:* KG
  * *Process:* Output of Slitter-Rewinder `NW-SLT-01` by splitting 100″ jumbo roll. Sellable or alternative input for bags.
* **SFG-SLIT-50-20IN: Non-Woven Slit Roll 50 GSM White (20 inch)**
  * *UOM:* KG
  * *Process:* Output of Slitter-Rewinder `NW-SLT-01` by slitting 100″ jumbo roll. Sellable or alternative input for bags.

---

### Category C: Finished Goods (FG) — *Sold to Customers*
* **FG-BAG-DCUT: D-Cut Non-Woven Shopping Bag (12x16 White)**
  * *UOM:* PCS
  * *Process:* Bag-making machine consuming 50 GSM white roll.
* **FG-MASK-3PLY: 3-Ply Surgical Face Mask (Blue)**
  * *UOM:* PCS
  * *Process:* Mask ultrasonic machine consuming 20 GSM Blue + Meltblown + 20 GSM White + Earloop + Nose wire.

---

### Category D: Scrap & Waste Items — *Tracked for Yield Reconciliation*
* **SCR-RECYCLABLE: Recyclable Spunbond Edge-Trim Offcuts**
  * *UOM:* KG
  * *Handling:* Fed into in-house pelletizer `NW-RCY-01` -> converts into `RM-RECYCLE`.
* **SCR-WASTE: Non-Recyclable Plant Waste & Sweepings**
  * *UOM:* KG
  * *Handling:* Sold to external scrap buyer or disposed of.

---

## 2. Discussion Decisions — Products, BOM & Rolls

| # | Decision | Status |
|---|---|---|
| D1 | Every item has a **single UOM**. Rolls (and all raw materials / scrap) in **KG**; bags & masks in **PCS**. | Agreed |
| D2 | Roll products are defined by **Color + GSM only** (e.g. *Roll 50 GSM White*). | Agreed |
| D3 | **Width and length are NOT product attributes.** They are recorded on each physical roll. Length is effectively the quantity and is derived from KG. | Agreed |
| D4 | Extrusion BOM is defined **per 100 kg** of roll output and has **no width**. Jumbo width comes from the machine. | Agreed |
| D5 | One **shared extrusion routing** serves all roll products (BOM -> routing; routing is product-independent on this branch). | Agreed |
| D6 | Different widths/lengths are produced by **cutting** the jumbo roll (slitting = width, rewinding = length) — not by new products or BOMs. Stock only reduces by trim scrap. | Agreed |
| D7 | Width appears in a BOM **only as a requirement** on a component line when a roll is consumed (e.g. bag needs a 16" roll). | Agreed |
| D8 | **Roll size** (e.g. "1 roll of 500 kg" or "rolls of 300 m") is an **order specification** on the sales/production order, limited by the machine's maximum roll size. | Agreed |

---

## 3. Work Centers, Sections, Shifts & Warehouses (Demo Plant Setup)

> **Status:** Agreed & Configured in `database/seeders/data/nonwoven_demo_data.json`.  
> **Scope:** Tenant 1, Branch 3 ("Bangalore Tech Center").

### 3.1 Four Production Shifts (Tenant-Wide)

| Shift Code | Shift Name | Timings | Break | Net Hours | Role in Demo |
|---|---|:---:|:---:|:---:|---|
| **NW-SH-MRN** | Morning Continuous Shift (Shift A) | 06:00 – 14:30 | 30m | 8.0h | Primary continuous extrusion and day operations |
| **NW-SH-EVE** | Evening Continuous Shift (Shift B) | 14:00 – 22:30 | 30m | 8.0h | Continuous extrusion; 30m handover overlap with Shift A (14:00–14:30) |
| **NW-SH-NGT** | Night Continuous Shift (Shift C) | 22:00 – 06:30 | 30m | 8.0h | Cross-midnight continuous run; 30m handover with Shift A |
| **NW-SH-DAY** | General Day Shift | 09:00 – 18:00 | 60m | 8.0h | **Overlaps Shift A & Shift B**; used for manual packing & maintenance |

### 3.2 Work Centers & Sections Architecture

| Work Center | Code | Overhead/Day | Section Code | Section Name | Unit | Machines Assigned | Shifts |
|---|---|:---:|---|---|:---:|---|---|
| **Extrusion Hub** | `NW-WC-EXT` | ₹2,550 ($30.00) | `NW-SEC-EXT` | Spunbond Extrusion Cell | kg | `NW-EXT-01`, `NW-EXT-02` | MRN, EVE, NGT |
| | | | `NW-SEC-MB` | Meltblown Extrusion Cell | kg | `NW-MB-01` | MRN, EVE |
| | | | `NW-SEC-RCY` | Scrap Recycling & Pelletizing | kg | `NW-RCY-01` | NGT, DAY |
| **Roll Converting** | `NW-WC-CNV` | ₹1,700 ($20.00) | `NW-SEC-SLT` | Slitting & Rewinding Line | kg | `NW-SLT-01`, `NW-RWD-01` | MRN, EVE |
| | | | `NW-SEC-PRT` | Lamination & Flexo Printing | kg | `NW-LAM-01`, `NW-PRT-01` | DAY, EVE |
| | | | `NW-SEC-WRP` | Roll Wrapping & Finishing | kg | `NW-WRP-01` | DAY, EVE |
| **Converting Plant** | `NW-WC-FAB` | ₹2,125 ($25.00) | `NW-SEC-BAG` | Automatic Bag Making Line | pcs | `NW-BAG-01`, `NW-BAG-02`* | MRN, EVE |
| | | | `NW-SEC-MSK` | Ultrasonic Mask Line | pcs | `NW-MSK-01` | MRN, EVE |
| | | | `NW-SEC-PCK` | Manual Packing & Boxing | pcs | *(No machines — pure manual)* | DAY, EVE |

*\*Note: `NW-BAG-02` is configured with `status = under_maintenance` to demonstrate schedule validation and machine availability handling.*

### 3.3 Manual Production Batches (`production_section_manual_productions`)

| Section Code | Batch Name | Shift Code | Cap/Hour | Unit | Purpose |
|---|---|---|:---:|:---:|---|
| `NW-SEC-RCY` | `NW-BAT-RCY-FEED` | `NW-SH-DAY` | 120 | kg | Scrap sorting and pelletizer hopper loading batch |
| `NW-SEC-WRP` | `NW-BAT-WRP-DAY` | `NW-SH-DAY` | 800 | kg | Day shift roll weighing, core inspection, and barcode labeling batch |
| `NW-SEC-WRP` | `NW-BAT-WRP-EVE` | `NW-SH-EVE` | 800 | kg | Evening shift pallet strapping and staging batch |
| `NW-SEC-BAG` | `NW-BAT-BAG-INSP` | `NW-SH-DAY` | 2,500 | pcs | D-Cut bag manual sample counting and QC check batch |
| `NW-SEC-PCK` | `NW-BAT-PCK-DAY` | `NW-SH-DAY` | 600 | pcs | Day shift bag & mask carton boxing team |
| `NW-SEC-PCK` | `NW-BAT-PCK-EVE` | `NW-SH-EVE` | 600 | pcs | Evening shift bag & mask carton boxing team |

### 3.4 Warehouses (Branch 3) & Opening Stock

* **`NW-WH-RM`**: Raw Materials Store (Granules, Masterbatch, Filter, Earloops, Nose wire)
* **`NW-WH-ROLL`**: Jumbo & Slit Roll Store (SFG White & Blue rolls)
* **`NW-WH-FG`**: Finished Goods Warehouse (Default dispatch location for Bags & Masks)
* **`NW-WH-SCRAP`**: Scrap & Regrind Yard (Edge-trim offcuts & recycled pellets)
* **`NW-WH-SUBCON`**: Subcontractor Transit Location (Job-work delivery challans)

---

## 4. Process Routings & Operation Specifications

> **Status:** Agreed & Documented.  
> **Key Architecture Rule:** Routings are generic, reusable manufacturing workflows decoupled from specific products. Operations belong to a Section and are bound to a planned Machine or Manual Batch.

### 4.1 Cost & Capacity Calculation Formulas
* **Processing Time (min/unit):** $\text{Processing Time} = 60 \div \text{Capacity per Hour}$
* **Machine Rate (/min):** $\text{Machine Hourly Operating Cost} \div 60$
* **Labor Rate (/min):** $\text{Allocated Staff Hourly Labor Cost} \div 60$
* **Operation Cost per Unit:** $(\text{Cycle Time} \times (\text{Machine Rate} + \text{Labor Rate})) \times (100 \div \text{Expected Yield \%})$
* **Setup Cost per Run:** $\text{Setup Minutes} \times (\text{Machine Rate} + \text{Labor Rate})$ (incurred once per production run)

---

### 4.2 Routing 1: Spunbond Fabric Extrusion (`RT-EXT-SPUNBOND`)
* **Produces:** All Spunbond Master Jumbo Rolls (50 GSM White/Blue, 20 GSM White/Blue)
* **Output Unit:** Kg | **Version:** 1.0 | **Default:** Yes

| Seq | Op # | Operation Name | Section | Planned Resource | Setup (min) | Run Time (min/kg) | Expected Yield % | QC Req? |
|:---:|:---:|---|---|---|:---:|:---:|:---:|:---:|
| **10** | `OP-010` | Continuous Extrusion & Calender Bonding | `NW-SEC-EXT` | `NW-EXT-01` *(100″, 250 kg/h)* | 30.0 | 0.2400 | 96.15% | No |
| **20** | `OP-020` | In-Line Winding & Roll Doffing | `NW-SEC-EXT` | `NW-EXT-01` *(Winder)* | 10.0 | 0.2400 | 100.00% | No |
| **30** | `OP-030` | Roll Inspection, Weighing & Tagging | `NW-SEC-WRP` | `NW-BAT-WRP-DAY` *(800 kg/h)* | 5.0 | 0.0750 | 100.00% | **Yes** |

*Notes:*
- Op 10 consumes raw polymer mix (PP + MB + Filler).
- Op 20 synchronizes with line speed; consumes zero raw materials (*TRACK-05 Rule 1*).
- Op 30 performs catch-weight measurement, GSM verification, and prints the unique Roll Barcode.

---

### 4.3 Routing 2: Roll Slitting & Rewinding (`RT-SLT-ROLLS`)
* **Produces:** Child/Slit Rolls cut by width (e.g., 100″ → 80″ + 20″) or length (e.g., 1000m → 700m + 300m)
* **Output Unit:** Kg | **Version:** 1.0 | **Default:** Yes

| Seq | Op # | Operation Name | Section | Planned Resource | Setup (min) | Run Time (min/kg) | Expected Yield % | QC Req? |
|:---:|:---:|---|---|---|:---:|:---:|:---:|:---:|
| **10** | `OP-010` | Rotary Shear Width Slitting & Rewinding | `NW-SEC-SLT` | `NW-SLT-01` *(600 kg/h)* | 20.0 | 0.1000 | 98.00% | No |
| **20** | `OP-020` | Slit Roll Palletizing & Strapping | `NW-SEC-WRP` | `NW-BAT-WRP-DAY` *(800 kg/h)* | 5.0 | 0.0750 | 100.00% | **Yes** |

*Notes:*
- Op 10 unrolls parent jumbo roll and slits into specified widths, generating recyclable edge trim.
- Op 20 applies child roll barcode labels and packages rolls for storage or direct customer dispatch.

---

### 4.4 Routing 3: D-Cut Bag Making (`RT-BAG-DCUT`) — *3 Operations Split*
* **Produces:** `FG-BAG-DCUT` (D-Cut Non-Woven Bags)
* **Output Unit:** Pcs | **Version:** 1.0 | **Default:** Yes

| Seq | Op # | Operation Name | Section | Planned Resource | Setup (min) | Run Time (min/pc) | Expected Yield % | QC Req? |
|:---:|:---:|---|---|---|:---:|:---:|:---:|:---:|
| **10** | `OP-010` | Roll Conversion & D-Cut Punching | `NW-SEC-BAG` | `NW-BAG-01` *(4,800 pcs/h)* | 30.0 | 0.0125 | 98.00% | No |
| **20** | `OP-020` | Manual Bundle Counting & QC Check | `NW-SEC-BAG` | `NW-BAT-BAG-INSP` *(2,500 pcs/h)* | 5.0 | 0.0240 | 100.00% | **Yes** |
| **30** | `OP-030` | Carton Boxing & Pallet Packing | `NW-SEC-PCK` | `NW-BAT-PCK-DAY` *(600 pcs/h)* | 10.0 | 0.1000 | 100.00% | No |

*Notes:*
- Op 10 consumes 50 GSM White roll; continuous bottom gusset folding, side ultrasonic welding, and die punching.
- Op 20 performs 50/100-pc bundle banding, seal strength checks, and defect sorting.
- Op 30 packs bundles into 1,000-pc master cartons with packing list labels.

---

### 4.5 Routing 4: 3-Ply Surgical Face Mask (`RT-MASK-3PLY`)
* **Produces:** `FG-MASK-3PLY` (3-Ply Surgical Masks)
* **Output Unit:** Pcs | **Version:** 1.0 | **Default:** Yes

| Seq | Op # | Operation Name | Section | Planned Resource | Setup (min) | Run Time (min/pc) | Expected Yield % | QC Req? |
|:---:|:---:|---|---|---|:---:|:---:|:---:|:---:|
| **10** | `OP-010` | Pleating, Nose Wire & Earloop Welding | `NW-SEC-MSK` | `NW-MSK-01` *(6,000 pcs/h)* | 25.0 | 0.0100 | 97.00% | No |
| **20** | `OP-020` | Dispenser Box Packing (50 Pcs/Box) & QC | `NW-SEC-PCK` | `NW-BAT-PCK-DAY` *(600 pcs/h)* | 5.0 | 0.1000 | 100.00% | **Yes** |

*Notes:*
- Op 10 feeds 3 rolls (Spunbond Blue outer + Meltblown filter + Spunbond White inner) + nose bridge + ultrasonic earloops.
- Op 20 performs visual QC and packs masks into 50-piece retail dispenser boxes.

---

## 5. Bill of Materials (BOM) & Workstation Allocations

> **Status:** Agreed & Configured in `database/seeders/data/nonwoven_demo_data.json`.  
> **Architecture Rule (2-Tier BOM):**
> * **Tier 1 (`production_bom_items`):** Consolidated master recipe per finished product. One row per unique material per BOM (`allocation_role` = `primary` or `alternative`).
> * **Tier 2 (`production_routing_operation_materials`):** Workstation allocations assigned to specific routing operations. Every operation in the routing has designated direct materials, packaging, or QA consumables allocated to ensure complete visibility in the BOM UI and accurate unit costing. Supports alternative materials with priority and replacement targets.

---

### 5.1 Extrusion Master Rolls (Semi-Finished Goods)
* **Linked Routing:** `RT-EXT-SPUNBOND` | **Base Output:** `100.00 kg` | **UOM:** Kg
* **Workstation Allocations Across ALL Operations:**
  * **Op 10 (`OP-010` Extrusion & Calendering):** Raw polymer blend (PP Granules + Color Masterbatch + CaCO3 Filler).
  * **Op 20 (`OP-020` In-Line Winding & Roll Doffing):** 1x `RM-CORE-TUBE` (Paper core tube).
  * **Op 30 (`OP-030` Roll Inspection, Weighing & Tagging):** 1x `RM-LABEL-ROLL` (Barcode identification tag).

| BOM Code | Target Product | Materials at Op 10 (`OP-010`) | Materials at Op 20 & 30 | Total Input | Net Yield | Expected Scrap Offcut |
|---|---|---|---|:---:|:---:|:---:|
| `BOM-ROLL-50-WHT` | `SFG-ROLL-50-WHT` (50 GSM White, 100″) | `RM-PP`: **95.68 kg**<br>`RM-MB-WHT`: **3.12 kg**<br>`RM-FILLER`: **5.20 kg** | **Op 20:** 1x `RM-CORE-TUBE`<br>**Op 30:** 1x `RM-LABEL-ROLL` | 104.00 kg | 96.15% (100 kg) | 3 kg `SCR-RECYCLABLE`<br>1 kg `SCR-WASTE` |
| `BOM-ROLL-50-BLU` | `SFG-ROLL-50-BLU` (50 GSM Blue, 100″) | `RM-PP`: **95.68 kg**<br>`RM-MB-BLU`: **3.12 kg**<br>`RM-FILLER`: **5.20 kg** | **Op 20:** 1x `RM-CORE-TUBE`<br>**Op 30:** 1x `RM-LABEL-ROLL` | 104.00 kg | 96.15% (100 kg) | 3 kg `SCR-RECYCLABLE`<br>1 kg `SCR-WASTE` |
| `BOM-ROLL-20-WHT` | `SFG-ROLL-20-WHT` (20 GSM White, 63″) | `RM-PP`: **99.84 kg**<br>`RM-MB-WHT`: **3.12 kg**<br>`RM-FILLER`: **1.04 kg** | **Op 20:** 1x `RM-CORE-TUBE`<br>**Op 30:** 1x `RM-LABEL-ROLL` | 104.00 kg | 96.15% (100 kg) | 3 kg `SCR-RECYCLABLE`<br>1 kg `SCR-WASTE` |
| `BOM-ROLL-20-BLU` | `SFG-ROLL-20-BLU` (20 GSM Blue, 63″) | `RM-PP`: **99.84 kg**<br>`RM-MB-BLU`: **3.12 kg**<br>`RM-FILLER`: **1.04 kg** | **Op 20:** 1x `RM-CORE-TUBE`<br>**Op 30:** 1x `RM-LABEL-ROLL` | 104.00 kg | 96.15% (100 kg) | 3 kg `SCR-RECYCLABLE`<br>1 kg `SCR-WASTE` |

---

### 5.2 Slit Rolls (Converted Semi-Finished Goods)
* **Linked Routing:** `RT-SLT-ROLLS` | **Base Output:** `100.00 kg` | **UOM:** Kg
* **Workstation Allocations Across ALL Operations:**
  * **Op 10 (`OP-010` Slitting & Rewinding):** 102.04 kg Parent Jumbo Roll (`SFG-ROLL-50-WHT`) + 1x `RM-CORE-TUBE`.
  * **Op 20 (`OP-020` Slit Roll Palletizing & Strapping):** 0.50 kg `RM-WRAP-FILM` + 1x `RM-LABEL-ROLL`.

| BOM Code | Target Product | Op 10 (`OP-010` Slitting) | Op 20 (`OP-020` Packing) | Yield % | Scrap |
|---|---|---|---|:---:|:---:|
| `BOM-SLIT-50-50IN` | `SFG-SLIT-50-50IN` (50 GSM White, 50″) | `SFG-ROLL-50-WHT`: **102.04 kg**<br>`RM-CORE-TUBE`: **1.0 Pcs** | `RM-WRAP-FILM`: **0.50 kg**<br>`RM-LABEL-ROLL`: **1.0 Pcs** | 98.00% | 2.04 kg `SCR-RECYCLABLE` |
| `BOM-SLIT-50-20IN` | `SFG-SLIT-50-20IN` (50 GSM White, 20″) | `SFG-ROLL-50-WHT`: **102.04 kg**<br>`RM-CORE-TUBE`: **1.0 Pcs** | `RM-WRAP-FILM`: **0.50 kg**<br>`RM-LABEL-ROLL`: **1.0 Pcs** | 98.00% | 2.04 kg `SCR-RECYCLABLE` |

---

### 5.3 D-Cut Non-Woven Bag with Alternative Materials (`BOM-BAG-DCUT`)
* **Target Product:** `FG-BAG-DCUT` (12x16 White Bag)
* **Base Output:** `1,000 Pcs` | **UOM:** Pcs | **Linked Routing:** `RT-BAG-DCUT` (3 Ops)
* **Workstation Allocations Across ALL Operations:**
  * **Op 10 (`OP-010` Bag Machine Conversion):**
    * **Primary Material:** `SFG-ROLL-50-WHT` (100″ Jumbo Roll) = **13.60 kg** (`is_alternative = 0`, `role = primary`, priority = 1)
    * **Alternative 1:** `SFG-SLIT-50-50IN` (50″ Slit Roll) = **13.60 kg** (`is_alternative = 1`, `role = alternative`, priority = 2, replaces primary)
    * **Alternative 2:** `SFG-SLIT-50-20IN` (20″ Slit Roll) = **13.60 kg** (`is_alternative = 1`, `role = alternative`, priority = 3, replaces primary)
  * **Op 20 (`OP-020` Manual Bundle Counting & QC Check):** 1x `RM-LABEL-ROLL` (QC inspection pass tag).
  * **Op 30 (`OP-030` Carton Boxing & Pallet Packing):** 1x `RM-BOX-BAG` (1,000-bag capacity master shipper carton).

---

### 5.4 3-Ply Surgical Face Mask (`BOM-MASK-3PLY`)
* **Target Product:** `FG-MASK-3PLY` (3-Ply Blue Face Mask)
* **Base Output:** `1,000 Pcs` | **UOM:** Pcs | **Linked Routing:** `RT-MASK-3PLY` (2 Ops)
* **Workstation Allocations Across ALL Operations:**
  * **Op 10 (`OP-010` Pleating & Ultrasonic Welding):**
    * `SFG-ROLL-20-BLU` (Outer spunbond layer): **0.66 kg**
    * `RM-MELTBLOWN` (BFE 99% middle filter layer): **0.83 kg**
    * `SFG-ROLL-20-WHT` (Inner skin-contact layer): **0.66 kg**
    * `RM-EARLOOP` (Ultrasonic welded spandex cord): **0.50 kg**
    * `RM-NOSEWIRE` (PE/metal nose bridge strip): **0.30 kg**
    * *Total Input Fabric & Accessories:* **2.95 kg**
  * **Op 20 (`OP-020` Dispenser Box Packing & QC):**
    * `RM-BOX-MASK` (Inner printed dispenser box, 50 pcs/box): **20.0 Pcs**
    * `RM-LABEL-ROLL` (Master box QC barcode seal): **1.0 Pcs**


