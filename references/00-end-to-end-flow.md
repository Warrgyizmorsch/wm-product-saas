# 00 - End-to-end flow: what runs today vs what the FRS requires

Confidence: "verified" = read in code (controllers/services/listeners); "survey" = seen in code structure only.

## A. The flow that runs today (verified)

```
Lead (web form / import / Meta / manual / research)
  -> owner assigned (round-robin / rules) + Response SLA clock (60m) LeadAssignmentService
  -> follow-ups logged (call/meeting/visit) + halts SLA clock
  -> mandatory next action + stale lead alert (>3 days)             LeadFollowupService
  -> Qualify (BANT stage gating) -> CRM Deal (stages)              LeadStageGateService
  -> Lost lead: structured reason + nurture follow-up + reopen gate
  -> Inbound enquiry acknowledgement (research leads barred)
  -> Quotation created from lead/deal                              QuotationController / QuotationService
       Sent  -> Deal moves to Proposal (60%), revised quotation -> Negotiation (80%)
       Approved/Accepted -> Lead becomes Won automatically (manual "Won" is blocked; creates NO operational demand)
  -> Deal converted to Customer                                    CrmDealController::executeDealCustomerConversion
  -> Sales Order created FROM the quotation          SalesOrderController (one SO per quotation)
  -> Confirm (only Draft orders)                     SalesOrderController::confirm -> SalesOrderRepository::confirmOrder
       -> Material Requirement created (physical items only; service-only orders skip it)
       -> per line the user chooses:
            reserve stock            MaterialRequirementService::reserveQty
            raise Purchase Requisition  ::createPurchaseRequisition  (status Waiting Purchase / Partially PR Raised)
            raise Production Request    ::createProductionRequest    (draft, status Waiting Production)
  -> (manual) "Convert to project": milestones + tasks per line   SalesOrderProjectBridgeService
  PURCHASE:  PR -> RFQ (vendor rates, compare) -> PO (Approved) -> GRN approve
       -> stock inflow (StockService::recordInflow) + event GoodsReceiptNoteApproved
       -> auto vendor bill (AutoCreateVendorBillOnGrnApproved) -> 3-way match -> bill journal -> vendor payment
  PRODUCTION: request -> production order (draft -> released -> in_progress -> completed -> closed)
       -> MES scans / WIP / rework / scrap / downtime / QC
       -> ProductionOrderCompleted -> project task auto-complete (SyncProductionTaskCompletionListener)
  INVOICE:   Invoice created from Sales Order (draft -> posted) -> InvoicePosted -> sales journal
  DISPATCH:  Dispatch Order built from Material Requirement + invoice items
       -> confirm -> ship (stock out, StockOutflowRecorded -> COGS journal) -> deliver / POD upload
  PAYMENT:   CustomerPayment -> allocation to invoices -> journal -> bank reconciliation
```

Integration style today: services call each other directly in many places; only about 20 events/listeners exist
(GoodsReceiptNoteApproved, InvoicePosted, CustomerPaymentReceived, StockOutflowRecorded, ProductionOrderCompleted, BomApproved...).
Domains are mostly connected through Sales MaterialRequirement, which is the real hub between Sales, Inventory, Purchase and Production.

## B. The flow the FRS requires (target)

```
Lead -> qualify (stage gates, SLA clock) -> Opportunity
  -> Design request / site visit / sample (linked)                       [Design - NEW]
  -> Quotation (cost sheet, Check Earliest Date, approvals, revisions)
  -> Add to Order Fulfilment (2nd-person approval; tracking row only)    [Fulfilment - NEW]
  -> Engineering review of every line + order BOM snapshot               [Sales + Production]
  -> Authorised Sales Order confirmation                                  (CRM "Won" alone creates nothing)
       -> Job Order created automatically (customer, site, lines, terms, promised dates)  [Projects]
       -> project template -> design, planning, purchase, production, invoicing, dispatch, installation tasks
       -> Order Confirmation Pack to customer (WhatsApp + email, delivery logged)
  -> Design freeze -> factory release pack (drawings, BOM, routing, cut list, CNC, QC, packing, installation)
  -> Planning (MRP vs free stock, reservations, offcuts) -> PR/RFQ/PO | job work | production order
  -> Receipts (QC hold -> accepted) -> stores (bin/rack, board IDs) -> production (unit-level gates) -> QC
  -> Packing (Package IDs, 50 kg cap, scan) -> Invoice POSTED + financial release
  -> Dispatch (scan loading, partial trips, challan + e-way per trip) -> gate-out -> site receipt scan
  -> Installation (site store, daily progress, snags) -> customer e-signature handover (per area)
  -> Warranty starts, retention/payment milestone fires -> Service tickets / loyalty
  -> Job Order closes only when challans, material, snags, documents, invoices, collections are settled (or authorised waiver)
Throughout: Order Tracking screen shows each stage from real transactions; every handover has sender, receiver, evidence, accept/return.
```

## C. The main differences to build

| Gap | Today | Required | Task IDs |
|---|---|---|---|
| Job Order | manual "Convert to project" | automatic on authorised confirmation | A-01 |
| Gate before confirm | none | engineering review of every line | A-02 |
| Options in BOM | fixed BOM | selection lines + order BOM snapshot | A-03, A-04 |
| After-confirm changes | edit/cancel/short-close | change request with impact preview | A-05 |
| Earliest date | inside Production scheduling only | button on quotation | A-06 |
| Tracking | per-module dashboards | one order x department board | A-19, A-22 |
| Packing | pick/pack status | package IDs, scans, labels | B-01..B-06 |
| Dispatch | invoice-linked dispatch | hard invoice gate, trips, complete sets, site receipt | B-07..B-14 |
| Installation / Service | none | new domains | B-16..B-28 |
| Approvals | several separate implementations | one engine | A-20, A-21 |

## D. Event contracts to add (cross-module effects must use events, per CLAUDE.md)

| Event (new) | Raised by | Listeners |
|---|---|---|
| SalesOrderConfirmed | Sales | Projects (create Job Order), Fulfilment, Notifications |
| EngineeringReviewCompleted | Production/Design | Sales (unblock confirm) |
| DesignReleased | Design | Production (BOM/routing effective), Purchase, Fulfilment |
| PackageClosed | Logistics | Fulfilment, Dispatch plan |
| DispatchGateOut | Logistics | Inventory (in transit), Accounting, Fulfilment |
| SiteReceiptRecorded | Logistics | Installation (site store), Fulfilment |
| HandoverSigned | Installation | Service (start warranty), Accounting (milestone/retention), Projects, Fulfilment |
| ServiceTicketClosed | Service | CRM (feedback/referral), Production/QC (corrective action) |
