# 02 - Engineering and planning (FRS 2.8, 2.9, 2.10, F03, F07)

---------------------------------------------------------------------------
## 2.10 Product, BOM and planning - PARTIAL (existing: Production, Inventory)

**Current workflow (verified/survey)**
1. Products are managed in Inventory; Production has multi-level BOMs (items, versions, approvals, effective dates), where-used and explosion (BomExplosionService), routings with alternate machines, ECOs with impact analysis.
2. MRP engine/shortage/lot-sizing/planning exceptions exist; capacity planning and scheduling scenarios exist. Events: BomApproved, RoutingApproved...
3. Sales Material Requirement lets the user reserve stock, raise a PR, or raise a production request per line.

**Required workflow (FRS)**
1. Products in families/variants with options (size, board, laminate, fabric, powder shade, screen, electricals).
2. BOM lines can be fixed, formula-based, optional or approved substitutes. A SELECTION LINE (e.g. "Laminate - 20 sq ft") is replaced by the customer's choice in that order's BOM; the quantity stays locked.
3. One quotation line can carry several choices at once (finish + laminate + 3 accessories). Before confirmation they resolve into a reviewed ORDER-SPECIFIC BOM SNAPSHOT (components, quantities, formulas, cost). No new product code per combination.
4. The chosen material flows into planning, purchase, reservation, issue, consumption, costing and Job Order documents. Stores cannot issue another colour/finish without approval; changes need an amendment/engineering change.
5. Each item is make / buy / job work with Encore material / bought complete, with effective dates; the system blocks issuing company material AND buying the full component for the same demand unless an authorised exception exists.
6. Planning nets demand against free stock, reservations, safety stock, usable offcuts, open orders, lead times; stock reserved for another job is not available.
7. MOQ/pack-size extra needs approval; reorder rules per item; planner sees what to bring forward, delay or cancel when demand changes.
8. Material choices use a live approved item/category master (not free text); fabric uses width/pattern/yield profiles; quantity override records old/new, reason, approver, order, revision and replans only affected demand.

**Changes required**
A-03 selection lines + options; A-04 order BOM snapshot; C-37 sourcing flag/MOQ/reorder; C-38 planner suggestions, fabric yield, override control.
**Connects with:** Sales (confirmation uses the snapshot), Inventory (reservations/offcuts), Purchase (demand), Design (released drawings/BOM/routing), Accounting (cost).
**Acceptance (BASE-10):** two orders for one table model with different laminates get different SKUs with locked quantities; stock reserved to another job is unavailable; excess MOQ needs approval.

---------------------------------------------------------------------------
## 2.9 Job Order and project tasks - PARTIAL (existing: Projects)

**Current workflow (verified)**
1. A CONFIRMED Sales Order can be converted (button) into a Project: one milestone + task list + task per order line, plus an order-level milestone (SalesOrderProjectBridgeService).
2. Projects support task dependencies, schedules, baselines, change requests, issues, time logs, timesheet approval, documents, reviews, closure, billing. When a production order completes, the linked project task completes automatically.

**Required workflow (FRS)**
1. The Job Order is created AUTOMATICALLY when the sales order is confirmed; one number follows the order to final handover; carries customer, site, lines, quantities, terms, promised dates.
2. A project TEMPLATE (by order/product type) applies tasks, milestones, owners and order of work.
3. The system works BACKWARDS from the promised date and highlights dates that cannot be met. Approved schedule is saved as a baseline; later changes are recorded against it.
4. When a task completes the next owner is notified with their documents. Each department has a "My Job Orders" screen.
5. Every handover between departments records sender, receiver, date, evidence, accepted or sent back with reason.
6. Progress rolls up by line/zone/floor from actual completion, not typed percentages. Delays on the critical path escalate automatically. Reopening a completed task needs permission + reason.
7. Risk, blocker, decision, change and closure registers per Job Order.
8. Closure only when vendor challans, material, tasks, snags, documents, invoices and collections are settled, or an authorised waiver with reason exists; customer has signed handover.

**Changes required**
A-01 auto creation; A-12 templates; A-13 back-scheduling; A-14 My Job Orders; A-15 handover records; A-16 registers; A-17 critical-path escalation/roll-up/reopen rule; A-18 closure gate + waiver.
**Connects with:** Sales (source), Design, Production, Purchase, Logistics, Installation, Accounting (collections), Fulfilment (status source).
**Acceptance (BASE-09):** order with template and an open vendor challan; evidence-based handovers; critical delay escalates; closure blocked until reconciled or waived.

---------------------------------------------------------------------------
## 2.8 Design and drawings - NEW DOMAIN `Design` (connects to Sales, Production, Projects)

**Flow**
1. Design tasks auto-created from the opportunity/order type, product family and scope (C-30). Input checklist first: site measurements, layout/BOQ, brief, finish, electrical, ergonomic, target date.
2. Assigned by designer skill and workload; planned vs actual time tracked.
3. Designer prepares layouts, selections, 2D/3D drawings, finish schedule, preliminary BOQ, samples.
4. Internal feasibility + cost review BEFORE anything goes to the customer. Each submission = new revision with expected reply date; customer comments recorded per revision; never overwrite (C-31).
5. After customer approval the design is FROZEN and the factory pack is released: manufacturing drawings, BOM, routing, cut list, CNC files, QC plan, packing logic, installation instructions (C-32). Event DesignReleased makes the BOM/routing effective.
6. Change after release = engineering change request that also decides what to do with material already bought, in production, packed or at site. Obsolete revisions cannot be released to purchase/production (C-33).
**Connects with:** CRM (request from lead), Sales (engineering review feeds confirmation), Production (BOM/routing/ECO exist), Purchase/Inventory (affected material), Portal (customer approves drawings), Documents service (revisions).
**Acceptance (BASE-08):** drawing changed after release creates a new revision + engineering change; affected purchases/WIP/packed/site material receive approved disposition before new release.

---------------------------------------------------------------------------
## F03 Controlled document management - NEW shared service `Core/Documents`
**Today:** documents are scattered (project documents, tenant files, HRMS documents).
**Flow:** every document belongs to a record, has owner, revision, access rules and controlled sharing; source files vs released PDFs are separate; final documents cannot be replaced without a controlled step; submission recipient and approval evidence kept (C-34).
**Used by:** Design, Projects, Purchase (POs/specs), Accounting (vouchers), Portal, Installation (handover report).
