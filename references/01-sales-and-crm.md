# 01 - Sales and CRM modules (FRS 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, F17)

Task IDs refer to the Excel tracker (Feature Breakdown sheet).

---------------------------------------------------------------------------
## 2.3 Customer, vendor and contact master - BUILT (C-15, C-16, C-17, C-18, C-19)

**Current workflow**
1. Customers are created from a converted CRM deal (or directly); CRM accounts hold contacts. Vendors live in Purchase (and an Inventory vendor model).
2. Leads are checked for duplicates (LeadDuplicateService). Opening balances can be posted for customers/vendors.
3. GST/PAN format validation exists (TaxIdentityService).
4. **Second-person verification (C-15, built Nov 2026; see 08-change-log.md).** A customer or vendor created from a screen, API or deal conversion starts as `pending_verification` and inactive. A different user holding `crm.customers.verify` / `purchase.vendors.verify` ticks off each of GSTIN, PAN and bank details and verifies (party becomes active) or returns it with a reason. The creator, or whoever last edited those fields, cannot verify. Editing GSTIN, PAN or bank details on a verified party sends it back to pending. Existing records stay active, labelled "legacy, not independently verified". Switch off with `PARTY_VERIFICATION_ENABLED=false`.
5. **Vendor bank changes (C-16 bank part, built Nov 2026; enhanced Nov 2026).** Once verified, a vendor's bank name/account/IFSC are locked. Changing them needs a bank change request: a different user with `purchase.vendors.bank_change.approve` phones the vendor on the number on file when the request was raised, records the call, and approves; only then do the new details apply. Every step is logged with old and new values. In Accounts Payable (`purchase.payments.create`, API, show and PDF vouchers), the vendor payout screen displays the verified beneficiary bank details, warns with high-visibility alerts if a bank change request is open, and prevents unconfirmed payouts while changes are pending.
6. **Inactive reasons, reactivation control and transaction blocking (C-16 states part, built Nov 2026; enhanced Nov 2026).** One inactive state with a recorded reason (agreed deviation: no separate Hold state). Stopping a party for a risk reason (Credit risk, Suspected fraud, Compliance issue) requires a different user with `crm.customers.reactivate` / `purchase.vendors.reactivate` to reactivate it. Unverified or inactive/hold parties are blocked with an "iron gate" across operational workflows: NEW quotations, deal conversions, sales order confirmation, purchase order approval, RFQ vendor lines, advance payments, subcontract challans, and especially **Factory Dispatch** (blocked from pending dispatch queues, manual create screen redirects, order confirmation, outward shipping release, and Delivery Challan generation; prevents the "5 truckloads to defaulter" failure mode). Vendor payments are also **blocked** for unverified vendors and vendors stopped for **Suspected fraud** or **Compliance issue**; for other inactive reasons (Dormant, Business closed, Duplicate, Credit risk, Other), verified suppliers with open dues remain selectable on the payment screen with a warning so existing bills can still be settled.
7. **Own-branch GSTIN & multi-branch customers/vendors + unified role-based contacts (C-17, built Nov 2026; see 08-change-log.md).** HRMS `branches` carry optional `gst_number` (validated against company PAN, falls back to company GSTIN when blank) and `is_head_office` (max one per company); `billing_organization_details()` resolves branch GSTIN/state first and documents freeze seller/buyer GSTIN snapshots. Customers and vendors have polymorphic `party_branches` (Head Office synced from parent; extra branches/sites enforce same PAN and branch-level second-person verification without deactivating the parent) and unified `contacts` with multi-role `contact_roles` (manageable in CRM Settings). `CrmDeal::contact()` remains linked to `CrmContact` (matching `crm_deals.crm_contact_id`) to preserve deal health, quotation contacts, follow-up emails, and Google Calendar attendee lookups. Quotations intentionally do not carry branches; branch selection starts at the Sales Order and carries to Invoice and Dispatch. GSTR-1 and GSTR-2B include a mixed-GSTIN guard; E-Invoice and E-Way Bill use frozen branch details (hardcoded GSTIN/address fallbacks removed; missing buyer GSTIN on E-Invoice generation is handled gracefully with clear screen notifications and invoice notice banners instead of 500 crashes). Switch off document branch UI with `PARTY_BRANCHES_ENABLED=false`.
8. **Party full history (FRS 2.3 item 6, built Nov 2026; see 08-change-log.md).** The customer and vendor pages have a **Full History** tab: one newest-first timeline with a type filter and pagination. Customer: leads, deals, quotations (via the customer's CRM account), sales orders, invoices, payments received, sales returns. Vendor: RFQs, purchase orders, vendor bills, payments made, advance payments. It reads live from each module through `PartyHistoryProvider` classes (no copy table), so a party merge keeps history automatically. A row appears only if the user can view that record type (e.g. `sales.invoices.view`).

**Required workflow (FRS)**
1. Before creating any party, search by name, GSTIN, PAN, mobile, email, address to avoid duplicates.
2. One legal party can have branches, billing/delivery addresses and sites; contacts have roles (decision maker, architect, accounts, purchase, site receiver).
3. Vendors carry items they supply, own item codes/names, price history, lead time, MOQ, tax and bank details.
4. GSTIN, PAN and bank details are verified by a SECOND person before the party becomes active; states: verification, active, hold, inactive; holds block transactions.
5. Bank-detail changes need independent verification (callback). Parties linked to transactions are never hard-deleted.
6. Opening a party shows its full history (visits, leads, quotations, orders, invoices, payments, RFQs, POs, quality issues, tickets). Merge keeps all history.

**Changes required**
- Second-person verification: C-15 DONE for the core flow. Still open: supporting-document upload, live GSTIN lookup, and the self-verification rule for roles/lone owners (planned with the RBAC work).
- Bank-detail change control (part of C-16): DONE for vendors (callback-verified change requests, beneficiary payout visibility, and pending bank change payout confirmation guard; see 08-change-log.md). Customers' bank details are not covered yet.
- States + hold enforcement (C-16): DONE as ONE inactive state with a recorded reason (agreed deviation: no separate Hold state). Making a verified party inactive needs a reason; a party stopped for a risk reason (credit, fraud, compliance) can only be reactivated by a different user holding `crm.customers.reactivate` / `purchase.vendors.reactivate`; an inactive or unverified party is refused on NEW sales orders, dispatches, purchase orders, RFQ vendor lines, advance payments and subcontract challans. Vendor payments are also blocked for unverified vendors and vendors stopped for **Suspected fraud** or **Compliance issue**, while normal inactive verified suppliers with open dues remain payable with a warning. Invoices, bills, receipts, returns and existing documents are not blocked. See 08-change-log.md (2026-11-18).
- Edits of a verified party: GSTIN, PAN, and **phone** are sensitive fields (`sensitive_fields` in `config/party_verification.php`). Changing any of them on a verified customer or vendor immediately switches it to `pending_verification` and `inactive` (preventing an attacker from swapping callback phone numbers prior to raising bank changes). Bank details of verified vendors are locked (`bank_details_locked = true`) and can only be updated via callback-verified change requests (`PartyBankChangeService`). Name, email and non-sensitive master fields can be updated directly.
- Parent/branch/site/bill-to/ship-to model & role-based contacts: **C-17 DONE** (Nov 2026; see 08-change-log.md). Quotations intentionally do not carry branches (branches start at Sales Order). Merge tool: **C-18 DONE** (Nov 2026; atomic transaction consolidating invoices, orders, payments, branches with code deduplication, contacts, ledgers, CRM pipeline handover when target has none, live PAN mismatch warning, and permanent duplicate archival; see 08-change-log.md).
- Duplicate search before creating and editing a customer or vendor: **DONE** (Nov 2026; see 08-change-log.md). Matches same-type parties tenant-wide on GSTIN, PAN, email, mobile, address, and **fuzzy near-spelling name matching** (same word count, each differing word a spelling variant; short names and extra words do not match). Create and edit forms warn live and require an explicit "save anyway" tick; on edit only fields that were changed are searched. The customer/vendor create and update APIs return 409 until `confirm_duplicate=true`. The live lookup needs the create right (or the edit right when editing). Intentionally left open on CSV import and Tally sync to allow duplicate staging for the C-18 merge tool.
- Party full history (FRS item 6): **PARTIALLY DONE** (Nov 2026). Built for leads, deals, quotations, orders, invoices, payments, returns, RFQs, POs, bills and advances. **Still open:** visits, quality issues and tickets, because those modules do not exist yet; each can be added later as one more provider.
- Party deletion protection (FRS item 5): **DONE** (Nov 2026; see 08-change-log.md). Parties linked to transactions can never be hard-deleted. Enforced via `PartyDeletionGuard`, model-level `deleting` hooks on Customer, Vendor, and CrmAccount throwing `PartyDeletionException`, policy gate `CustomerPolicy::delete`, and API destroy hardening that automatically deactivates parties with transaction history instead of hard-deleting them. Clean-slate parties with zero transactions remain safely deletable.
- Vendor item codes, price history, lead time, MOQ, and document-expiry alerts (C-19): **DONE** (Nov 2026; see 08-change-log.md). Vendor-specific item catalog mapping (`vendor_items`), price hike audit log (`vendor_item_price_histories`), lead time & MOQ soft validation on PO create, and compliance document tracker (`vendor_documents`) with LDC sync and dual-disk download fallback. Expiry alerts: banner on the vendor page plus daily `purchase:check-vendor-document-expiry` notification. RFQs now display and compare vendor catalog part numbers, agreed MOQs, lead times, and last agreed purchase rates in the comparison matrix desktop/mobile views and supplier portal.

**Connects with:** Sales (credit/hold checks on orders), Purchase (hold blocks POs), Accounting (bank details, GST), Service (history).
**Acceptance (BASE-03):** a customer with 2 branches and 4 contacts; activation blocked before verification; history stays linked.

---------------------------------------------------------------------------
## 2.5 CRM: leads and opportunities - BUILT for the 2.5 text (checked 23 Nov 2026; test file `tests/Feature/LeadFrs25GatingAndSlaTest.php`, 26 tests). Separate flows F04, F05, F06, F10, F14, F17 are NOT part of this and stay open (C-06..C-08); raising a design request / site visit / sample / estimate from a lead depends on the Design module and is also left.

**Current workflow (verified in code and tests)**
1. **Sources and provenance.** Leads arrive from the web form, API, Excel/IndiaMART import, Meta, WhatsApp bot, visitor entry or manually. Each lead stores `provenance` (`web_form`, `api_import`, `import`, `manual`, `researched`) and `is_inbound_enquiry` (true only for web form / Meta).
2. **Duplicates and merge.** Duplicates are detected (`LeadDuplicateService`). An authorised user can merge a duplicate into a lead from the lead page ("Merge duplicate", permission `crm.leads.merge` or tenant-wide update). `LeadMergeService` moves all follow-ups, history, documents and quotations to the surviving lead, fills its empty fields, and soft-deletes the duplicate with `merged_into_lead_id` (nothing is lost). A Won / converted / deal-linked lead cannot be merged away.
3. **Assignment and response clock.** Settings -> CRM -> Lead Assignment: mode (creator / round-robin / unassigned pool) plus **routing rules** by territory (state/city/country), segment, source and product keyword, each with a pool and a strategy (least open leads = workload, round-robin, first), plus "route to existing account owner". Rules run before the mode. Assignment stamps `assigned_at` / `response_sla_minutes`; reassigning restarts the clock. The clock stops only on a real (non-Pending) interaction. `scanOverdueResponseSlas()` (every minute via `crm:send-followup-reminders`) escalates to owner and managers; the SLA can be counted in working hours only (`sla_working_hours_only`, Mon-Sat 09:00-18:00, override with `work_start`/`work_end`/`work_days` in `crm_lead_policy`).
4. **Qualification and stage gate.** Fields on the create and edit forms: project type, approximate quantity, decision timeline, competitor, decision maker identified, plus company, contact role (designation), location, requirement, budget. `LeadStageGateService` blocks Qualified/Dealing/Proposal/Negotiation/Won until they are filled and one real interaction exists. Manual Won is blocked; Won happens when a quotation is accepted (customer linked, no operational demand - BASE-05). Tenant switch `crm_lead_policy.enforce_stage_gating`.
5. **Next action always present.** An open, owned lead (not on hold) always has a Pending follow-up: `LeadNextActionService::ensureNextAction()` creates a system "First response" / "Next action required" task on assignment, on creation (manual, API, import) and whenever the last pending activity is completed or cancelled (`LeadFollowupRepository::syncLeadNextFollowupDate`). Deleting the only pending activity is refused; logging a note still asks for the next date. The stale scanner (owner missing, no next action, inactivity over `stale_lead_days`) alerts owner and managers every `stale_alert_cadence_days`.
6. **Hold / customer pause / internal clarification.** "Put on hold" on the lead page (`LeadHoldService`): type, reason and review date are mandatory; the review date becomes a dated "Hold review" task; SLA and stale alerts are suspended while the hold is valid; history records start and end; "End hold" resumes and re-creates a next action. No outreach is forced during a hold.
7. **Loss, nurture, reopen, reassign.** Both the Lead and Deal screens and the API require `loss_reason_id` (and competitor when the reason demands it). Closing a Deal as Lost opens a structured modal and syncs the linked Lead(s) with the loss reason, competitor, remarks, and nurture follow-up; a Dealing lead can also be closed as Lost (its linked deal is closed with the same reason). A nurture outreach task is scheduled after `follow_up_days`. Reopen and reassign need `crm.leads.reopen` / `crm.leads.reassign` or tenant-wide `crm.leads.update`; owners cannot be blanked. Owner, status, expected value, expected date, hold and merge changes are in the lead history.
8. **Acknowledgement.** `LeadAcknowledgementService`: genuine inbound enquiries only, once, WhatsApp + email, only when `crm_lead_policy.auto_ack_inbound` is on, using the tenant's template (`ack_template`, `ack_subject`, placeholders {name} {brand} {owner} {company}). Never for a lead flagged "Do not contact" (`communication_opt_out`, set on the lead form or by a declined consent on the web form) nor for imported / API / manual / researched leads. `acknowledgement_sent_at` is set only when a channel accepted the message.
9. **Reports** (CRM -> Lead Reports, `crm.reports.leads`): response time (average, median, within SLA, breached, awaiting, by salesperson), funnel conversion, ageing of open leads by salesperson, win and loss reasons, forecast by salesperson (open leads by expected month; open deals weighted by probability). Scoped by the normal row visibility.
10. **BASE-05.** Won links customer and quotation and creates no Job Order, BOM demand or reservation; only authorised Sales Order confirmation does.

**Known limits inside 2.5**
- System-triggered Won (quotation accepted) is not subject to the BANT gate; it already requires the customer and the accepted quotation.
- The next action for a lead created without any owner is created as soon as an owner is assigned; the "Unassigned pool" mode leaves it visible to the stale scanner until then.
- Acknowledgement uses one tenant-wide template (not per product/channel templates).
**Required workflow (FRS)**
1. Every lead keeps its original source and provenance (including authorised AI-assisted research).
2. Assignment starts a RESPONSE-TIME CLOCK; overdue responses go to the sales manager.
3. Qualification captures company, contact role, location, project type, quantity, product need, budget, decision process, timeline, competition.
4. A lead cannot move to the next stage until the required details and activities for that stage are filled.
5. From the lead sales can raise a design request, site visit, sample or estimate, all linked.
6. A WON opportunity links to the customer and accepted quotation, then passes commercial, engineering and order-confirmation gates. Only authorised Sales Order confirmation creates the Job Order. Won alone creates nothing.
7. Loss records reason, competitor, timing and applies nurture rules. Reassign/reopen needs permission and keeps history.
8. No open lead without owner, next action and due date; stale leads alert owner and manager.
9. New enquiries get an acknowledgement via approved WhatsApp/email. Research-sourced leads are NOT enquiries: no automatic acknowledgement.
10. Referral partners, order influencers and commission link to each opportunity (F17).

**Changes status**
- **C-01 Response SLA clock:** DONE (wall-clock or working hours, hold-aware).
- **C-02 Next-action rule and stale alert:** DONE (system-created next action, delete guard, hold, stale scanner).
- **C-03 Stage gating:** DONE.
- **C-04 Loss, nurture, reopen/reassign permission, merge:** DONE.
- **C-05 Inbound acknowledgement (permission-aware):** DONE.
- **Assignment rules, hold state, lead merge, 2.5 reports:** DONE (part of FRS 2.5 itself).
- **C-06 Commission (F17):** Open - separate requirement.
- **C-07 AI lead intake (F04):** Open - separate requirement.
- **C-08 AI next activity (F05):** Open - separate requirement.
**Connects with:** Quotation (2.7), Visitor (convert visit to lead), Marketing (campaign responses), Design (requests), AI core, Accounting (commission settlement).
**Acceptance (BASE-05):** enquiry without qualification cannot advance; marking Won does not create operational demand before authorised confirmation. **(VERIFIED IN CODE & TESTS)**

---------------------------------------------------------------------------
## 2.6 Sales field app - PARTIAL (existing: CRM + HRMS attendance)

**Current workflow (verified)**
1. Completing a meeting (follow-up) can require live GPS + front-camera selfie (setting). Distance to the saved venue is compared with a configurable radius (default 500 m); the note records "Verified On-Site" or "Location Mismatch".
2. HRMS attendance API supports check-in, check-out, break in/out, snapshot upload and background location tracking (trackLocation). A route polyline and kilometres are built from location logs (AttendanceRouteService). Travel expense accounting exists.

**Required workflow (FRS)**
1. Start Day: live selfie + location + time (also field attendance for HR). Planned visits from CRM follow-ups/beat plan on a map.
2. Tracking only between Start Day and End Day; samples every few minutes/distance; observed route with stops; gaps stay visible, never shown as complete.
3. Visit check-in must be inside the configured geofence; outside = EXCEPTION with reason + evidence, visibly UNVERIFIED until a manager decides. Location/selfie never prove a meeting by themselves.
4. Record who was met, linked lead/opportunity, purpose, outcome, next action (becomes a CRM follow-up).
5. Fake-GPS/developer-option detection where Android allows; consent on first login; photo only live; retention per policy.
6. Offline: save on phone with correct time, sync once, no duplicates.
7. End Day: km from route -> conveyance claim at the configured rate -> manager approval -> payroll.
8. Manager: live team map, route replay per day, planned vs actual, flagged visits.

**Changes required:** C-24 exception queue; C-25 fake-GPS; C-26 consent + tracking window + retention; C-27 manager map/replay; C-28 offline sync check; C-29 End Day claim to payroll.
**Connects with:** HRMS (attendance, payroll, expense), CRM (follow-ups, new lead from field), Accounting (TravelExpenseAccountingService).
**Acceptance (BASE-06):** offline outside-geofence visit syncs once, stays unverified, route gaps visible, claim awaits approval.

---------------------------------------------------------------------------
## 2.7 Quotation and sales order - PARTIAL (existing: CRM quotations, Sales)

**Current workflow (verified)**
1. Quotation from lead/deal with items, item-wise or order-wise discount, GST, revisions, PDF, send by email/WhatsApp. Approval policy setting exists; approve/reject by an authorised user.
2. Sales Order is created from an approved quotation (one SO per quotation; duplicates redirected to the existing SO).
3. Confirm works only on Draft orders and creates the Material Requirement. Cancel and short-close exist. Convert-to-project is a separate manual action.
4. Then invoice, dispatch, payment, returns (see 00-end-to-end-flow).

**Required workflow (FRS)**
1. Quotation from opportunity, design/BOQ or catalogue; every revision gets a new number; old revisions and communications kept; only ONE active accepted revision.
2. Cost sheet (material, process, job work, packing, freight, installation, overhead) vs target margin - authorised roles only.
3. Discount/margin/payment-term/warranty approvals by value band.
4. "Check Earliest Date" button: considers confirmed orders, capacity, holidays, breakdowns, stock, open POs, vendor/powder-coat lead times, design time, packing/dispatch capacity, installation teams; shows earliest production/dispatch/installation dates, reason, risk level, assumptions, shortage; recalculates when inputs change. Pipeline quotes affect only the weighted forecast, never block stock.
5. "Add to Order Fulfilment" (second-person approval) creates ONE tracking row - no stock, purchase, production or accounting effect. On confirmation the same row becomes the order.
6. Before confirmation: customer PO, accepted revision, quantities, site, credit terms, taxes, promised date are checked.
7. MANDATORY engineering review of every line (new BOM / engineering change / existing BOM OK / no design change). Confirmation is blocked while any review is unresolved or mandatory options are missing. Order BOM snapshot is approved before confirmation.
8. On authorised confirmation: ONE Job Order + workflow records (design, planning, purchase, production, invoicing, dispatch, installation). Creating records does not release production (client acceptance, internal approval, confirmation and production release are separate gates).
9. Order Confirmation Pack to the customer on WhatsApp + email (terms, payment schedule, approvals, steps, contacts); failed sends become owned follow-ups.
10. After confirmation: quantity/spec change, part cancel, hold/restart only by CHANGE REQUEST showing impact (reserved material, open POs, WIP, packed, dispatched, invoiced, cost, dates); approver decides disposition; nothing is deleted.

**Changes required**
A-01 auto Job Order; A-02 engineering gate; A-04 BOM snapshot (with A-03); A-05 change requests; A-06 earliest date; A-07 cost sheet; A-08 confirmation pack; A-09 revision rule/expiry/proforma/BOQ; A-10 value-band approvals; A-11 Add to Order Fulfilment.
**Connects with:** CRM, Production (BOM, capacity), Projects (Job Order), Fulfilment (tracking), Accounting (invoice before dispatch), Design.
**Acceptance (BASE-07):** line with unresolved engineering review blocks confirmation; tracking row creates no demand; valid confirmation converts the same row, creates one Job Order and logs the pack.

---------------------------------------------------------------------------
## 2.2 Visitor management - BUILT (small gaps)

**Current:** pre-registration, QR pass, check-in/out, mark arrived, start meeting, notify host, deny entry, extend, incident, blacklist, draft-contact approval, convert to lead, import/export, API.
**Remaining:** WhatsApp host alert with backup escalation; restricted-area/NDA acknowledgement; material gate pass on exit; retention policy; peak-hour/overdue reports (C-35); optional face recognition (C-36).
**Connects with:** CRM (convert to lead), HRMS (candidate visits), Purchase (vendor visits).

---------------------------------------------------------------------------
## 2.4 Marketing and campaigns - NEW DOMAIN `Marketing` (connects to CRM)

**Why new:** only a UTM filter and inbound Meta leads exist today.
**Flow**
1. Create a campaign: segment, area, product, offer, channel, budget, landing page, owner, success measure (C-09).
2. Track content/agency tasks with briefs, versions, approvals (C-14).
3. Each channel gets its own tracking code. Every response is captured ONCE as an interaction: match an existing CRM record or create one lead after duplicate check; keep source, ad, keyword, consent (C-10).
4. Audiences by rules (visitors, unconverted quotes, lost leads, past customers) synced to Meta (C-11, F06). Buyers, opt-outs and disqualified people are removed automatically (C-12).
5. Cost vs qualified leads, orders and margin; attribution first/last/shared; budget overrun approval; ad spend pulled from Google/Meta/LinkedIn (C-13).
**Connects with:** CRM (leads, interactions), Sales (orders for attribution), Accounting (margin), Platform (Meta settings/webhook, existing).
**Acceptance (BASE-04):** reply from the same prospect stays on one record; source carries to order/margin; opt-out stops targeting.

---------------------------------------------------------------------------
## F17 Order influencers and commission - NEW feature inside CRM (settles via Accounting)
**Flow:** influencer/referral partner linked to opportunity -> attribution rule on win -> approval -> commission calculated on invoiced/collected value -> settlement posted by an Accounting listener. (C-06). Reversal if order is cancelled/returned.
