# 05 - Logistics, site and after-sales (FRS 2.17, 2.18, 2.19, 2.21, F14)

---------------------------------------------------------------------------
## 2.18 Dispatch and delivery - PARTIAL (existing: Sales/Inventory dispatch)

**Current workflow (verified)**
1. Material Requirement items are released (reserve/pick/pack states: Pending -> Picking -> Packed ...).
2. A Dispatch Order is built from the Material Requirement and the invoice items; the system tracks quantity already dispatched per invoice line; serial/batch picking is available.
3. confirm -> ship (stock out, StockOutflowRecorded -> COGS journal) -> deliver; delivery challan PDF, e-way bill service, transporter, tracking update, POD upload; cancel supported. Dispatch statuses sync back to the sales order.

**Required workflow (FRS)**
1. Dispatch plan by Job Order, package, floor/zone, required date, site readiness, vehicle. Full, partial and multiple dispatches; the screen shows required / already sent / current / balance.
2. The tax invoice is made, approved and POSTED BEFORE loading; payment or credit approval recorded. LOADING IS BLOCKED until the invoice is posted and financially released. The plan is frozen before loading; one invoice may cover several approved trips but the same invoice quantity is never used twice.
3. Boxes are SCANNED while loading: wrong project, revision, finish, excess, uninvoiced or already-sent boxes are blocked.
4. Each trip gets its own delivery challan and (where required) e-way bill showing the goods actually on that vehicle (approved description, quantity, unit, HSN, value), linked to its trip, invoice and parent product. Corrections/cancel/vehicle change are controlled.
5. A workstation stays ONE product on the order while its parts (tops, frames, screens, raceways, electricals, hardware) travel on different trucks/days. COMPLETE SETS at site = every mandatory part of the approved configuration (10 tops + 8 frames -> max 8 complete sets). Incomplete sets and missing parts stay visible.
6. Gate-out is blocked if a required document is missing/invalid; transporter, driver, vehicle, LR number, tracking, expected arrival recorded. Statutory e-way rules are configurable with effective dates.
7. Tracked per item: invoiced, released, loaded, dispatched, received, pending.
8. At site boxes are scanned on receipt: accepted, short, excess, damaged, rejected, with photos. Returns, replacements, claims and credit/debit notes link to the original invoice and challan.

**Changes required:** B-07 hard invoice gate; B-08 scan on loading + frozen plan; B-09 partial/component trips with per-trip challan/e-way; B-10 complete sets; B-11 balances; B-12 gate-out; B-13 site receipt; B-14 returns/claims; B-15 decide whether dispatch moves into `Logistics`.
**Connects with:** Accounting (invoice, release, e-invoice), Packing (package IDs), Inventory (in-transit/site stock), Installation (site store), Fulfilment (status), Platform (transporters).
**Acceptance (BASE-18):** 10 workstations dispatched as tops, then 8 frames + parts, then the rest; each trip has its documents; invoice coverage cannot be reused; complete sets need all parts.

---------------------------------------------------------------------------
## 2.17 Packing and labels - NEW DOMAIN `Logistics` (connects to Production, Inventory, Dispatch)

**Today:** only a Pending -> Picking -> Packed status on the material requirement and a label controller in Production.
**Flow**
1. Packing work is created from finished items by Job Order, product, cluster, floor, room, zone and dispatch plan (B-01).
2. System proposes packing by product rule (assembled / semi-assembled / site assembly); authorised users can adjust (B-03).
3. Every carton/bundle/whole product gets a unique PACKAGE ID (parent/child) (B-02). Different products do not share a box unless a rule allows. Identical components can combine; hardware is packed separately in small child packets inside a parent carton, separately per product/unit.
4. A component box cannot exceed 50 kg gross (whole product as one unit = approved exception). The 50 kg rule applies even if domestic documents do not print weight.
5. Contents are scanned; wrong, extra, rejected or unrelated items blocked; all items must go to the same site/zone; an item cannot be packed twice (B-04).
6. 60x40 mm QR/barcode labels, colour-marking labels and packing list are printed (domestic/export formats) (B-05).
7. The box closes only after checking -> sorting area -> Ready to Dispatch. Reopen/reprint needs permission and is recorded (B-06).
**Connects with:** Production (finished output), QC (rejected cannot be packed), Inventory (stock states), Dispatch (loading scans), Installation (package list).
**Acceptance (BASE-17):** a carton above 50 kg is blocked and split; unique parent/child IDs reconcile.

---------------------------------------------------------------------------
## 2.19 Installation and handover - NEW DOMAIN `Installation` (connects to Projects, Logistics, Accounting, Service)

**Today:** only generic project tasks.
**Flow**
1. Installation scope is created from the order lines even when installation is included in price (B-16).
2. SITE READINESS check before sending a team: access, storage, civil/electrical, drawings, floor/zone release, safety, timings, passes, customer coordinator (B-17).
3. Teams scheduled by skill, availability, location, quantity; they receive work order, drawings, package list, checklist (B-18).
4. Boxes received at site go to a SITE STORE and are issued by floor/room/zone (B-19).
5. Daily: installed quantity by product/zone with photos, manpower, hours, blockers, damage, shortages; works OFFLINE and syncs (B-20). Installed quantity can never exceed received + issued.
6. Installation QC creates a SNAG list (owner, target date, photos); replacements link to the original box/job (B-21).
7. When an area passes QC and snags close, an authorised customer representative SIGNS the handover on phone/tablet; a floor/area can be handed over alone; exceptions need explicit approval, scope, responsibility, due date (B-22).
8. Material sent/received/installed/returned/damaged/left over is reconciled before project closure (B-23).
9. The signed handover starts the WARRANTY and triggers retention/payment milestone (B-24).
**Connects with:** Projects (closure gate), Logistics (site receipt), Inventory (site stock), Accounting (milestone/retention), Service (warranty), Portal (customer signature), Fulfilment.
**Acceptance (BASE-19):** one area handed over while another has snags; offline sync keeps evidence once; excess installed quantity blocked.

---------------------------------------------------------------------------
## 2.21 Service, warranty and loyalty - NEW DOMAIN `Service` (connects to Installation, CRM, Production/QC)

**Today:** only the internal HR helpdesk (employee tickets) exists - use only as a design reference.
**Flow**
1. Ticket from phone, email, WhatsApp, portal or salesperson, linked to the installed product/project (B-25).
2. Warranty period/scope/history checked; severity and response time set; classification warranty / goodwill / chargeable; free replacement and goodwill need approval (B-26).
3. Assign remote help, site visit, replacement, repair or vendor claim with parts and team planned.
4. Record root cause, action, parts, labour, photos, customer confirmation, who bears cost; controlled reopen (B-27).
5. Repeated problems create a corrective action for design, purchase, production or QC (uses existing CAPA).
6. After closing, ask for feedback and start referral/repeat-business follow-ups; rewards only on eligible events and reversed if the business is cancelled/returned (B-28, links to F17 commission).
**Connects with:** Installation (handover = warranty start), Inventory (spares), Production QC (CAPA), CRM (referrals), Accounting (chargeable jobs), Portal.
**Acceptance (BASE-21):** complaint on an installed product; repair completed with customer confirmation; reward reversed for returned business.

---------------------------------------------------------------------------
## F14 Customer login portal - NEW DOMAIN `Portal` (own login guard)
**Flow:** customer logs in (separate guard, never the staff login) -> sees only PUBLISHED documents, drawing approvals, order status, invoices/payments, service requests -> approves drawings / acknowledges documents (client acceptance is a separate gate: portal signature alone can NEVER confirm an order or release production) (B-29, B-30).
**Connects with:** Design (drawings), Sales (order pack), Accounting (invoices/payments), Installation (handover signature), Service (tickets), Documents service.
