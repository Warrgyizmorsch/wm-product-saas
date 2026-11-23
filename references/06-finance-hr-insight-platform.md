# 06 - Finance, HR, insight and platform (FRS 2.1, 2.20, 2.22, 2.23, 2.24, 2.25, 2.26, 2.27, 2.28, F16)

---------------------------------------------------------------------------
## 2.20 Accounts and finance - BUILT (extras pending)

**Current workflow (verified/survey)**
Chart of accounts, ledger groups, cost centres, fiscal years/periods + close checklist; vouchers and journals (with approval), bill-wise settlement (Agst Ref/New Ref/Advance/On Account), AR/AP ageing, bank statement upload + matching rules + reconciliation, GSTR-1/2B/3B + summary, TDS (sections, thresholds, challans, 26Q/24Q, Form 16/16A), fixed assets, budgets and variance, exchange rates, Tally integration (idempotent ingest), posting-failure recorder.
Automatic postings (listeners): sales invoice, customer payment, COGS, purchase bill, purchase return, sales return, vendor payment, production consumption, payroll runs and payouts, travel expense, maintenance work orders.

**Required workflow (FRS)**
1. Customer invoice is made BEFORE dispatch from the approved dispatch plan (no retyping); posted invoice goes by email + WhatsApp, failed delivery creates a follow-up.
2. Dispatch gets financial release only after invoice posted and payment/credit/management approval.
3. Revenue and ALL costs (material, labour, job work, packing, freight, installation, rework, replacements) collect against each project, product and Job Order to show margin (F15/F25).
4. Payment reminders on email/WhatsApp per schedule, skipping hold/dispute. MSME 45-day vendor dues. E-invoice/e-way/GST/TDS with cancellation and reconciliation.
5. Periods lock after approved reconciliation; corrections only by reversal/amendment. Duplicate invoices/payments blocked; vendor bills matched to approved POs and accepted receipts.
6. Statutory compliance calendar with owners and evidence (F16).

**Changes required:** D-10 multi-currency/forex; D-11 Job Order profitability; D-12 WIP/FG/work-centre/landed costing; D-13 compliance calendar (F16, new); D-14 reminders + MSME dues; D-15 director dashboard/working capital/scenarios; D-16 recurring vouchers, PDC, TCS, FVU/26AS, Schedule III, ratios; B-07 financial release gate for dispatch.
**Connects with:** Sales (invoice/payment), Purchase (bills), Production (cost), HRMS (payroll), Logistics (release gate), Installation (milestone/retention), CRM (commission).
**Acceptance (BASE-20):** frozen dispatch + unmatched vendor bill; dispatch release follows posted coverage and approval; mismatch routes for review; duplicate payment blocked.

---------------------------------------------------------------------------
## 2.22 HR, attendance and payroll - BUILT (small gaps)

**Current workflow (verified/survey)**
Employees/org (company, branch, department, designation, shift) -> attendance (biometric devices/webhook, manual, mobile check-in with snapshot, breaks, corrections, penalties) -> leave (types, balances, approvals, encashment) / overtime / WFH / shift change -> expense and travel claims -> payroll run (salary structure, statutory PF/ESI, TDS, holds, retro adjustments) -> payslips -> accounting journals and payout -> FnF and exit clearance. Recruitment, goals, PIP, 360, SOPs, documents, broadcasts, helpdesk.

**Required workflow (FRS)**
Field attendance comes from Start/End Day in the sales app; late marks/overtime/corrections follow Encore rules; leave, correction, overtime, expense (incl. travel from the sales app) and advances go through approvals; salary from structure + attendance + leave + OT + incentives + deductions + loans + statutory rules; payslips and bank files are released only after a SECOND CHECK and payroll LOCK; transfer/exit reassigns tasks, removes access, recovers assets, completes clearance; certificate expiry tracked; no automatic unreviewed penalty.

**Changes required:** D-17 second check + lock, certificate-expiry alerts, reviewed penalties, field-day attendance. Also fix the defects listed in HRMS_Test_Report.md (assets static call, leave-encashment import, exit columns, biometric check-out).
**Connects with:** Sales field app, Accounting (payroll journals), Projects/Production (task reassignment), Assets, KPI module.

---------------------------------------------------------------------------
## 2.28 Employee KPIs and scorecards - PARTIAL (existing: HRMS KRA/KPI)

**Current:** KRA/KPI masters, KPI templates, goals, appraisal cycles, 360 feedback, PIPs.
**Required:** KPIs per role with formula, source, target, unit, weight (total 100%), higher/lower-better, reviewer; scores fed AUTOMATICALLY from ERP work data (tasks on time, follow-up compliance, quotation turnaround, approved output, first-time-right, rework, installation progress, tickets closed); delays caused by customer/material/machine are reviewed before they count; scorecard shows target vs actual, achievement, weight, score, rating, attendance summary, pending commitments, trend; managers drill from a score to the underlying records; employee can comment; approval locks the period and keeps versions; nothing is deducted or penalised automatically.
**Changes:** D-18 KPI definition/weight validation; D-19 automatic feeds (needs Fulfilment/handover data); D-20 dependency review, drill-down, lock/versioning.
**Acceptance (BASE-28):** sales and production employees with different KPI sets; approved attendance correction; dependency delay reviewed; locked monthly scorecard; no payroll penalty.

---------------------------------------------------------------------------
## 2.1 Common rules + 2.26 System, security and integrations - PARTIAL (existing: Access, Platform)

**Current workflow (verified/survey)**
Custom RBAC (roles, permissions, scopes own/team/department/branch/tenant/platform, per-user overrides) via AccessService; audit logs; approval services in separate places (journals, GRN, HRMS, global approvals); approval reminders; notification rules/catalogue; subscriptions/plans/Razorpay; email/Twilio/WhatsApp/Meta/Google Calendar settings; OTP for password reset; API with docs; Accounting integration layer with idempotency keys.

**Required workflow (FRS)**
1. Information entered once where it starts; role decides what a user can see/do; margins and cost prices hidden from operational staff.
2. Approvals go by value/risk (discount to a limit -> sales manager, above -> management); NOBODY approves their own request; when an approver is on leave it goes to the backup; if nobody acts it escalates one level; returned/rejected keeps comments and resubmission starts a new cycle; emergency override is reason-coded and time-bound.
3. Every change keeps who/what/old/new/when; posted entries are never deleted (reversal/credit note/adjustment).
4. Role matrix separates create, edit, approve, post, reverse, export, admin; high-risk master/payment/stock-adjust/scrap/write-off/override need independent approval.
5. Login with OTP or SSO; integrations (website, Google/Meta ads, email, WhatsApp, calendar, biometric, barcode/label printers, bank, GST provider, transporters) retry on failure, never create duplicates, failed events go to an ERROR LIST with owner and reconciliation.
6. Output catalogue (quotation, order pack, PO, challan, invoice, labels, packing list, gate pass, handover report) with controlled reprint and QR/barcode; documents stored with the record, revisioned.
7. Backups with tested restore, DR plan, release/rollback, measurable service targets, separation of test/production, data ownership and exports.

**Changes required:** A-20 one approval engine (extend app/Services/Approval); A-21 backup approver/escalation/emergency override; A-25 field-level history; A-26 margin-hiding audit; A-27 no-self-approval audit; D-24 OTP/SSO; D-25 integration error list + retry; D-26 printers/bank/GST/transporter integrations + output catalogue; D-27 backup/DR/service targets; D-28 Encore role matrix; D-29 BASE-01..28 acceptance test pack.
**Acceptance (BASE-01):** creator tries to self-approve after the SLA passes: blocked; backup/escalation routes; history retained. (BASE-26): failed integration retries without duplicate posting; error history and restore evidence exist.

---------------------------------------------------------------------------
## 2.23 Order tracking screen and dashboards - NEW DOMAIN `Fulfilment` (read-only; reads every module)

**Today:** per-module dashboards, dashboard layout/widgets, a partial order tracking report inside Production reports.
**Flow**
1. Every approved quotation and running order = ONE row. Columns: Sales Order, Design, BOM, Manufacturing, Purchase, Inventory, Packing, Invoice, Payment, Dispatch, Installation, Quality, Handover, Closure (A-19).
2. Each cell: status (Completed, Ongoing, Stuck, Waiting, Delayed, Partial...), owner, due date, last update, blocker, next action, data-as-of. Status comes from REAL transactions (never typed) through a status contract each module implements (A-22). Click opens the underlying transaction according to access.
3. Filters: company, period, territory, customer, vendor, product, Job Order, department, owner, site, status. Alerts: delay, shortage, margin drop, quality, payment risk, integration failure (A-24).
4. Role home screens (priorities, overdue, approvals); scheduled reports to authorised people with delivery logged; governed metric catalogue - one formula and owner per KPI, stale-data/failed-refresh visible (A-23).
**Connects with:** all modules (status providers), Notifications, KPI scorecards.
**Acceptance (BASE-23):** QC rejection and overdue payment update Quality/Payment cells; each requirement appears once; drill-down respects roles.

---------------------------------------------------------------------------
## 2.24 AI assistant and automation - NEW shared service `Core/Ai` (+ module use cases)

**Today:** Gemini call analysis (CRM Call Assistant), deal-health scoring, WhatsApp bot service - no governance layer.
**Flow**
1. Register each use case: owner, purpose, input sources, allowed tools/actions, prohibited data, model/instruction version, output schema, approval, evaluation, fallback (D-01).
2. Assistant answers ERP questions using the USER'S OWN access rights; suggests lead details, summaries, drafts, anomalies; every suggestion shows the records used and can be edited/rejected with a reason (D-04).
3. AI recommendations never authorise actions. Only approved deterministic rules may create/update CRM activities or send internal notifications. Customer-facing messages need an authorised person or approved policy. AI NEVER marks Won, confirms orders, grants discounts, makes commitments, or approves financial/stock/BOM/employee changes.
4. Every AI-assisted action is logged (data used, output, actor, authorising approval/policy and version) without exposing secrets; prompt-injection safeguards, redaction, retention, rate/cost limits, monitoring (D-02, D-03).
5. KILL SWITCH: AI off at once while deterministic workflows continue.
**Connects with:** CRM (F04 intake, F05 next activity), Marketing (F09), Documents, Platform settings.
**Acceptance (BASE-24):** AI suggests an out-of-policy commitment: blocked; AI suspended: workflow continues.

---------------------------------------------------------------------------
## 2.25 Carbon footprint - NEW DOMAIN `Sustainability` (reads Purchase, Inventory, Production, Accounting)

**Flow:** facilities/boundary/period/base year (GHG Protocol) (D-05) -> activity data from electricity, diesel, purchases (board, laminate, steel), freight, production, job work, waste (D-07) -> factor library with source/version/validity (CEA grid factors) (D-06) -> Scope 1 / 2 / selected Scope 3 results per factory, product (from BOM) and Job Order, planned vs actual, estimates marked (D-08) -> review, approve, LOCK; factor change creates a revised report keeping the approved original; Excel + BRSR Core export (D-09). Offsets never reduce the main figure. Applicability is never assumed without an authorised compliance decision.
**Acceptance (BASE-25):** sample month with electricity, diesel, materials, freight, job work, waste; revised factor restates without losing the approved report.

---------------------------------------------------------------------------
## 2.27 Data from Odoo - NEW DOMAIN `Migration` (admin tool; touches all masters)

**Flow:** full read-only Odoo backup/archive at start (D-22) -> per-object owner, field mapping, transformation/default/exclusion rules (D-21) -> cleanse duplicates and wrong units BEFORE load -> migrate customers, vendors, contacts, products+units, BOMs, open quotations/orders/POs/production orders, stock by lot and location, receivables, payables, opening balances -> rehearsal + count/quantity/value reconciliation signed by data owners (D-23) -> department-by-department cutover while Odoo keeps running for others; excluded history archived with controlled access.
**Reuse:** per-module import mappers (leads, visitors, products, production masters, assets), opening-balance services in Accounting.
**Acceptance (BASE-27):** duplicate contacts, wrong units and open balances reconcile with evidence; no duplicate processing between Odoo and the new ERP.

---------------------------------------------------------------------------
## F16 Statutory compliance calendar - NEW feature inside Accounting
Due dates (GST returns, TDS deposits/returns, PF/ESI, other statutory), owner, evidence upload, reminders, status; reads existing GST/TDS/payroll-statutory modules (D-13).
