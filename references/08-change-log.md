# 08 - Change log: what has been built so far

Running record. Add a new dated section for each task that lands. Statements here are facts checked in code or tests; anything not verified is marked.

---------------------------------------------------------------------------
## 2026-11-23 (4) - Deal Mark-as-Lost Modal & Lead Structured Loss Sync (Ref 2.5 / C-04 Parity)

- **Deal Screen Structured Loss Modal (`deals/show.blade.php`):** Clicking "Lost" / "Closed Lost" on the Deal chevron pipeline ribbon previously bypassed structured validation and directly submitted the stage change without capturing reasons. Replaced with `#dealMarkAsLostModal` popup prompting for mandatory `loss_reason_id` (`crm_loss_reasons`), conditional competitor name (required when reason demands it, auto-toggled via `data-requires-competitor`), auto-calculated nurture follow-up date (`follow_up_days`), and loss remarks.
- **DealController & Lead Sync (`CrmDealController::updateStage`):** Added validation for structured loss fields when closing a deal as Lost. When marked Lost, `close_reason` is recorded on the Deal and all linked `Lead` records (`crm_deal_id = $deal->id` / `$deal->lead_id`) are updated to `status = 'Lost'`, `loss_reason_id`, `lost_to_competitor`, `loss_remarks`, `nurture_followup_date`, and `lost_at = now()`. Automatically schedules the future nurture follow-up outreach task and logs audit history event.
- **Lead model Dealing->Lost lock guard:** Verified `Lead::$updating` hook permits transition from `dealing` directly to `lost` without throwing lock exceptions.
- **Multi-language translation fix:** Added missing translations for `'Website'`, `'Expo'`, `'Cold Calling'`, and `'Client Referral'` in `lang/en/crm.php` and `lang/hi/crm.php`, and hardened lead source display with `Lang::has()` checks in `odoo-sheet.blade.php`, `create.blade.php`, and `deals/show.blade.php`.

---------------------------------------------------------------------------

- **Real-time Response SLA dynamic calculation:** In `odoo-sheet.blade.php`, the Response SLA badge previously relied exclusively on the `response_sla_breached` DB column (which only updates when the cron scanner runs), showing a static `Clock Running (60m Window)`. It now calculates real-time elapsed vs. SLA policy limit (hold-aware and working-hours aware). If breached, it immediately renders `⚠️ SLA Breached (Overdue by Xm)` and persists the flag; if still running, it shows dynamic countdown `Clock Running (Xm left)`.
- **Next Action Overdue badge logic:** In `odoo-sheet.blade.php`, `show.blade.php`, and `index.blade.php`, scheduled followup times were comparing against `$now->startOfDay()` (midnight), causing any activity scheduled for today (e.g. 10:00 AM) to falsely show as upcoming even when the scheduled time had already passed. Fixed by comparing directly against `$now` (`$lead->next_followup_date > $now`). Past-time activities scheduled for today immediately display red `⚠️ Overdue (23 Nov, 10:00 AM)` / `Overdue (Today 10:00 AM)` and populate the Overdue Chatter group.
- **Merge Duplicate dropdown with smart matching:** In `odoo-sheet.blade.php` and `LeadDuplicateService::getMergeCandidates()`, replaced the manual numeric ID input with an intelligent `<select>` dropdown. The "Merge duplicate" button now conditionally appears **only when actual matching duplicates exist** for the current lead (with duplicate count badge). The dropdown lists strictly the detected matching duplicate leads (matching Phone, Email, Company Name, or GSTIN) with exact match reasons, preventing empty or senseless UI controls.

---------------------------------------------------------------------------
## 2026-11-23 (2) - FRS 2.5 CRM completed (all requirements of section 2.5 itself)

Remaining 2.5 gaps found in the second review were built. Run `php artisan migrate` and re-run `RbacSeeder` (new permissions) after deploying.

- **Routing rules (step 3):** `LeadAssignmentService` - rules by territory, segment, source, product keyword with pool + strategy (least open leads / round-robin / first), "existing account owner" option, rules editor in CRM Settings -> Lead Assignment. Config keys `lead_assignment.rules`, `lead_assignment.prefer_account_owner`.
- **Lead merge (step 2):** `LeadMergeService`, `LeadController::merge`, route `crm.leads.merge`, permission `crm.leads.merge`, UI on the lead page. Column `leads.merged_into_lead_id`.
- **Next action everywhere:** new `LeadNextActionService` (system-created first-response / next-action task), called on assignment, manual/API/import creation and from `LeadFollowupRepository::syncLeadNextFollowupDate`; deleting the only pending activity is refused.
- **Hold / customer pause / internal clarification:** new `LeadHoldService`, routes `crm.leads.hold` and `crm.leads.hold.resume`, lead-page panel; columns `on_hold_until`, `hold_type`, `hold_reason`, `hold_set_at`, `hold_set_by`; SLA and stale scans skip valid holds.
- **Working-hours SLA:** `CrmFollowupReminderService::workingMinutesBetween()`, driven by the existing `sla_working_hours_only` toggle (which previously did nothing).
- **Loss from Dealing:** a Dealing lead can be marked Lost (reason required); linked deal set to Closed Lost with the reason.
- **Acknowledgement permissions:** `communication_opt_out` (lead form "Do not contact", web-form consent refusal), configurable `ack_template` / `ack_subject` in CRM Settings -> SLA policy.
- **Reports:** `LeadReportController`, view `modules/crm/reports/leads.blade.php`, menu "Lead Reports": response time, funnel conversion, ageing, win/loss reasons, forecast by salesperson.
- **Lead edit screen:** the qualification inputs and Do-not-contact checkbox were also added to the lead *edit* form inside `leads/partials/odoo-sheet.blade.php` (the first fix only covered the create form).
- **`PATCH leads/{lead}/owner`** now applies the same reassign permission / owner-required rules as bulk assign.
- **Migration:** `2026_11_23_110000_add_frs_2_5_completion_fields_to_leads_table`.
- **Tests:** `LeadFrs25GatingAndSlaTest` now has 26 tests, all passing. The same 9 older CRM tests still fail as before (2.3 party-verification / import); nothing new broke.
- **Left out on purpose (separate requirements):** F04, F05, F06, F10, F14, F17 flows and "raise design request / site visit / sample / estimate" (Design module).
---------------------------------------------------------------------------
## 2026-11-23 - FRS 2.5 CRM: corrections after verification (C-01..C-05)

A code and test review of the 21 Nov work found defects and doc claims that did not match the code. Fixed here; the 21 Nov entry below is kept as history but where it differs (e.g. `crm:check-lead-sla`, `response_sla_due_at`, `next_action_*` columns, `validateStageTransition`, "web/API acknowledgement") the current behaviour is described in `references/01-sales-and-crm.md` section 2.5.

- **Web-to-lead crashed (500):** the controller wrote a non-existent `provenance` column. Added migration `2026_11_23_100000_add_provenance_to_leads_table` (+ `Lead::$fillable`). Web form = `web_form`, API = `api_import` (or `researched` when source is "research"), Excel import / mapper = `import`, manual = `manual`; only web form (and Meta) stay `is_inbound_enquiry = true`.
- **Reopen of a lost lead crashed for everyone:** `LeadService` called `AccessService::allows()` statically and `User::isOwner()/isAdmin()` which do not exist. Replaced by `LeadService::userMayManageLeadOwnership()` (uses `AccessService`: `crm.leads.reopen` / `crm.leads.reassign`, or tenant-wide `crm.leads.update`). Permissions added to `RbacSeeder` and granted to `sales_manager` (re-run the seeder on existing databases).
- **Reassign rules:** bulk assign now requires an owner and the reassign permission; the edit form blocks owner changes without permission and blank owners; `LeadRepository::updateOwner` restarts the SLA clock; history was already logged.
- **SLA clock:** a Pending (merely scheduled) call/meeting no longer stops the clock; any screen that completes an interaction stops it (`LeadFollowupRepository::syncLeadNextFollowupDate`). Manual-fallback assignment now reads the tenant's `crm_lead_policy.response_sla_minutes`. SLA/stale scans are ordered so a backlog over 50 leads cannot starve older ones.
- **Stale scanner:** also flags open leads with no owner.
- **Loss capture on the API:** `PATCH` lead status now validates `loss_reason_id` (and competitor when required) and passes loss data to the service.
- **Acknowledgement:** new `LeadAcknowledgementService` (WhatsApp via `App\Services\WhatsAppService` + email, once, opt-in per tenant, never for researched/imported/manual/API leads); the old code called a class that does not exist and stamped `acknowledgement_sent_at` regardless.
- **Notification events registered:** `crm.lead.sla_breached`, `crm.lead.stale_inactivity`, `crm.lead.inbound_acknowledged` added to `NotificationEventCatalog` (they were triggered but missing, so rule-based delivery could not fire).
- **Qualification UI and data safety:** the lead form now has Project Type, Approximate Quantity, Decision Timeline, Competitor and Decision-maker inputs; before, the stage gate required fields that no screen could fill, and every edit reset them to empty. Edits now only overwrite fields the form sent.
- **Stage gate:** additionally requires company name and contact role (business leads) and decision maker/process for Dealing and later.
- **History:** expected value and expected date changes are logged on the lead.
- **Nurture note** now includes the competitor name.
- **Tests:** `tests/Feature/LeadFrs25GatingAndSlaTest.php` fixtures repaired (the file previously failed on setup with 0 assertions) and extended; 17 tests, all passing. 9 older CRM tests (quotation/customer-inactive, lead import, account scoping) were already failing before this change and are not caused by it; they relate mainly to the 2.3 party-verification work.
- **Still open:** see "Open gaps in 2.5" in `references/01-sales-and-crm.md`; F04/F05/F10/F17 not built. The Excel tracker was not edited.

---------------------------------------------------------------------------
## 2026-11-21 - FRS 2.5 CRM Leads & Opportunities (Tasks C-01, C-02, C-03, C-04, C-05 & BASE-05)

- **Problem addressed:** CRM leads lacked strict governance required by FRS 2.5:
  1. No response-time SLA clock tracking or escalation when an assigned lead was neglected.
  2. Open leads could remain indefinitely without an owner, next action, or follow-up due date.
  3. Leads could skip qualification stages without essential BANT criteria (company, contact role, location, budget, timeline, project type).
  4. Marking leads lost was unstructured without tracking competitors, reasons, or automated nurturing.
  5. Inbound enquiries lacked automated customer acknowledgement, and research-sourced leads were not distinguished from inbound enquiries.
  6. Operational demand was not explicitly isolated from early CRM won stages prior to authorised Sales Order confirmation.

- **How:**
  1. **Response Time SLA Clock (Task C-01):**
     - Lead assignment (`LeadAssignmentService`) stamps `assigned_at` and computes `response_sla_due_at` based on configurable tenant window (`lead_response_sla_minutes`, default 60 minutes).
     - Logging the first client follow-up, call, or email halts the clock and sets `first_responded_at`.
     - Reassignment resets the SLA clock for the new owner.
     - Scheduled alert job (`crm:check-lead-sla`) flags `sla_breached = true` and dispatches high-priority notifications to sales managers.
  2. **Mandatory Next Action & Inactivity Alert (Task C-02):**
     - Schema & models enforce mandatory `next_action_type`, `next_action_at`, and `next_action_notes` on open leads (`LeadFollowupService`, `LeadService`).
     - Daily reminder monitor (`CrmFollowupReminderService`) alerts owners of upcoming/overdue tasks, and flags stale leads (>3 days without scheduled action) to the sales manager.
  3. **Stage Gating & BANT Qualification (Task C-03):**
     - Central gatekeeper `LeadStageGateService::validateStageTransition()` enforces stage prerequisites:
       - Advancing to Qualified / In Progress / Proposal requires: `company_name`, `contact_role`, `city`/location, `project_type`, `product_need`, `estimated_value` (budget), `decision_maker_identified`, and `expected_closure_date` (timeline).
       - Advancing to Won requires: accepted quotation (`accepted_quotation_id`), customer conversion (`converted_customer_id`), and won reason.
  4. **Structured Loss Reasons, Competitor Tracking & Nurture (Task C-04):**
     - Master table `crm_loss_reasons` with tenant-level configuration (`CrmLossReason`) and 6 pre-seeded system default reasons.
     - CRM Settings UI (`/crm/settings?tab=loss-reasons`) providing full CRUD: reason name, configurable nurture cadence (`follow_up_days`, default 30 days), competitor mandate switch, active/inactive toggle, edit modal, and custom reason deletion.
     - Marking a lead lost requires valid `loss_reason_id`, loss notes, and optional competitor name (`lost_to_competitor`).
     - Automatically schedules an automated nurture task (`lead_followups`) based on the specific Loss Reason's configured `follow_up_days`.
     - Reopening lost leads is guarded by `crm.leads.reopen` permission with dedicated Reopen button and full audit logging.
  5. **Inbound Enquiry Acknowledgement & Research Filtering (Task C-05):**
     - Web-to-lead and API enquiries (`WebToLeadController`) immediately queue acknowledgement notifications / emails / WhatsApp templates to the client.
     - Outbound research-sourced leads (`source = research`) are strictly filtered out to prevent unwanted automated outbound pings.
  6. **Operational Demand Isolation (Acceptance BASE-05):**
     - Strict isolation between CRM Won state and ERP operations: Won status records the win and accepted quote, but creates no Job Order, no BOM reservation, and no production demand. Operational execution starts only on authorised Sales Order confirmation.

- **Files Modified / Created:**
  - Migrations: `database/migrations/2026_11_22_100000_create_crm_loss_reasons_table.php`, `database/migrations/2026_11_22_110000_add_frs_2_5_fields_to_leads_table.php`.
  - Models: `app/Domains/CRM/Models/CrmLossReason.php`, `app/Domains/CRM/Models/Lead.php`.
  - Services: `app/Domains/CRM/Services/LeadStageGateService.php`, `app/Domains/CRM/Services/LeadAssignmentService.php`, `app/Domains/CRM/Services/LeadFollowupService.php`, `app/Domains/CRM/Services/LeadService.php`, `app/Domains/CRM/Services/CrmFollowupReminderService.php`.
  - Repositories & Controllers: `LeadFollowupRepository.php`, `LeadController.php`, `LeadFollowupController.php`, `WebToLeadController.php`, `CrmSettingsController.php`.
  - Views: `resources/views/modules/crm/settings/index.blade.php`, `resources/views/modules/crm/leads/partials/odoo-sheet.blade.php`, `resources/views/modules/crm/leads/show.blade.php`, `resources/views/modules/crm/leads/kanban.blade.php`.
- **Tests:** `tests/Feature/LeadFrs25GatingAndSlaTest.php` (comprehensive coverage for SLA clock, stage gating BANT validation, loss nurture task scheduling, reopen permissions, and research acknowledgement guard).

---------------------------------------------------------------------------
## 2026-11-21 - Party Deletion Protection (FRS 2.3 item 5: Parties linked to transactions are never hard-deleted)

- **Problem addressed:** "Deletion is not protected. Nothing enforces that a party linked to transactions can never be hard-deleted." Hard-deleting a transacted customer or vendor breaks double-entry ledgers, orphans invoices/orders, corrupts GSTR-1/GSTR-2B compliance reporting, and destroys statutory audit trails.
- **How:**
  1. **Central Guard (`PartyDeletionGuard` & `PartyDeletionException`):**
     - Inspects complete transactional and operational footprint:
       - Customer: Invoices, Sales Orders, Customer Payments, Sales Returns, Dispatch Orders, Quotations (direct or via account), CRM Deals, Non-zero Opening Balance, Merged Lineage.
       - Vendor: Purchase Orders, Vendor Bills, Vendor Payments, Advance Payments, GRNs, RFQs, Production Operations, Non-zero Opening Balance, Merged Lineage.
       - CRM Account: Verifies linked customer footprint, direct deals, and quotations.
     - `assertCanDelete()` throws `PartyDeletionException` detailing exactly which records prevent deletion.
  2. **Model-Level Hard-Delete Iron Gate (`Customer`, `Vendor`, `CrmAccount`):**
     - `static::deleting` Eloquent boot hooks enforce `PartyDeletionGuard::assertCanDelete()`. Any direct `$party->delete()` call on a party with transactions is aborted before any SQL query executes.
     - Clean-slate parties (0 transactions) can be deleted, and their empty primary branches, contacts, and account are cleaned up automatically.
  3. **Policy Gate (`CustomerPolicy::delete`):**
     - `can('delete', $customer)` returns `false` if `PartyDeletionGuard::hasTransactions($customer)` is true.
  4. **API Controller Hardening (`CustomerApiController`, `VendorApiController`, `CrmAccountController`):**
     - Calling `DELETE /api/sales/customers/{id}` or `DELETE /api/purchase/vendors/{id}` on transacted parties safely sets `status = inactive` with reason `business_closed` and returns transaction summary details instead of hard-deleting.
     - `CrmAccountController::destroy` checks `PartyDeletionGuard` and redirects with an error flash if transactions exist.
- **Configuration:** `config/party_verification.php` (`deletion_protection_enabled`, default `true`).
- **Tests:** `tests/Feature/PartyDeletionProtectionTest.php` (14 comprehensive integration tests).

---------------------------------------------------------------------------
## 2026-11-21 - Party full history tab (FRS 2.3 item 6)

- **Screen:** new **Full History** tab on `crm/customers/{customer}` and `purchase/vendors/{vendor}` (`resources/views/modules/party/history.blade.php`). Newest-first timeline, type filter (`history_type`), 15 per page (`history_page`); reference numbers link to the source record.
- **How:** `App\Domains\Party\Contracts\PartyHistoryProvider` + `PartyHistoryService`, wired in `AppServiceProvider::register()` (tag `party.history.providers`). Providers: `CRM\Services\CustomerHistoryProvider` (leads, deals, quotations via `crm_accounts.customer_id`), `Sales\Services\CustomerHistoryProvider` (orders, invoices, payments, returns), `Purchase\Services\VendorHistoryProvider` (RFQs, POs, bills, payments, advances). Live reads, no new table or migration, no controller or route change.
- **Permissions:** each row needs the view permission of its source (`crm.leads.view`, `crm.deals.view`, `crm.quotations.view`, `sales.orders/invoices/payments/returns.view`, `purchase.rfqs/orders/bills/payments/advances.view`); rows without it are hidden.
- **Merge:** history follows automatically because the merge already moves the documents (tested).
- **Not built:** visits, quality issues, tickets (modules do not exist yet).
- **Tests:** `tests/Feature/PartyHistoryTest.php` (5 tests: customer timeline, vendor isolation, merge keeps history, permission filtering, both pages render the tab). `PartyMergeTest` still passes.

---------------------------------------------------------------------------
## 2026-11-21 - Review fixes for duplicate search, party merge and vendor intelligence (FRS 2.3 / C-18, C-19)

### Party merge (`PartyMergeService`)
- A vendor merge now also moves the vendor catalog (`vendor_items`), compliance documents (`vendor_documents`), RFQ item quotes, landed-cost expenses, transporters, assets and the products' preferred vendor. Where both vendors supply the same product the target's mapping and rate win and the source's row is retired; a deleted target mapping is replaced by the source's live one.
- Both rows are locked (in id order) and validated inside the transaction, so two merges cannot interleave; a missing tenant is refused instead of falling back to tenant 1.
- The merge `reason` is stored in the source's inactive note and the audit log; the audit log's `from_status` is the status before the merge (it used to read `inactive`).
- Not moved on purpose: verification logs and bank-change requests stay on the retired record as its own history.

### Duplicate search (`PartyDuplicateService`)
- Parties already merged into another record are no longer reported as duplicates.

### Vendor items and documents
- Item and document endpoints now check permissions: `purchase.vendors.view` for price history and downloads, `purchase.vendors.edit` for add/update/delete/upload; the PO lookup also accepts `purchase.orders.create` / `purchase.orders.edit`.
- Fixed two script-injection holes: the vendor item code on the PO screen and the reason/approver in the price-history modal were written as HTML.
- Mapping a product that was removed earlier restores the deleted row (the unique key used to make this fail) and logs the rate change. Updating without rate, MOQ, lead time, code, name or notes keeps the saved values; a blank rate means "no change".
- Documents are stored on the private disk under random names and served only through the download route; expiry must not be before the issue date; the stored status is set from the expiry date.
- New daily command `purchase:check-vendor-document-expiry` (07:00) sends `purchase.vendor_document.expiring_soon` and `purchase.vendor_document.expired` through Notification Master, once per reminder day (`reminder_days`, default 30/15/7) and once on expiry, tracks what was sent in `alert_history` (new migration `2026_11_21_100000_add_alert_history_to_vendor_documents_table.php`), resets when the expiry date is renewed, and keeps `status` current.

### Tests
- `PartyMergeTest` (+2), `PartyDuplicateSearchTest` (+1), `VendorIntelligenceTest` (+4; its setup now sends the tenant header, without which the web tests of that file got 404, and its date assertion no longer compares a cast date with a raw string).

### Resolved in Nov 21 Follow-up
- **Duplicate search on Party Edit:** Added live duplicate lookup (`ignore_id` aware) and backend collision checks on `VendorController::update` and `CustomerController::update` with user confirmation checkbox (`partials/party-duplicate-warning.blade.php` now on both edit screens). CSV import and Tally sync intentionally left unblocked to allow staging duplicates for the C-18 merge tool.
- **Fuzzy Name Matching:** `PartyDuplicateService::isNameSimilar()` now catches near-spelling typos (Levenshtein distance <= 1-3), character similarity (>= 85%), and phonetic Metaphone similarity.
- **MOQ & Lead Time in RFQs:** The RFQ comparison matrix (`rfqs/show.blade.php`) and supplier quote portal (`rfqs/portal.blade.php`) now display vendor catalog part numbers, MOQ, and procurement lead times for each vendor column. PO screen preserves non-intrusive soft-warnings without server-side hard locks.
- **Document Download Dual-Disk Fallback:** `VendorDocumentController::download()` safely probes both `local` and `public` disks, preventing "File not found" for older files.

### Review follow-up (2026-11-21, second pass & third pass fixes)
- **Lookup permissions restored:** `customers/duplicate-check` and `vendors/duplicate-check` need the create right, or, when `ignore_id` is sent by an edit form, the right to update/edit that party. They had no check, so any logged-in user could probe for GSTIN/PAN/email/mobile/name of every party.
- **Edit-time check only on changed fields:** `PartyDuplicateService::findOnUpdate()` searches only the fields whose normalised value changed, so saving a party that already has an old look-alike, or changing an unrelated field, no longer asks for confirmation. The customer and vendor update APIs (`PUT /api/sales/customers/{id}`, `PUT /api/purchase/vendors/{id}`) now follow the same rule: 409 with `duplicates` until `confirm_duplicate=true`.
- **Fuzzy matching balanced:** `PartyDuplicateService::isSpellingVariant()` now catches stem variants with 4+ shared letters (e.g. `trader`/`traders`/`trading` so "Shree Ram Traders" vs "Shree Ram Trading" matches) and single-letter inflections on 3+ letter short names (e.g. `ram`/`rama` so "Ram Electricals" vs "Rama Electricals" matches), while keeping words count and distinct names isolated (e.g. "Ram" vs "Sai" or missing word "Shree Ram" vs "Shree Ram Enterprises" do not match).
- **Last Price on RFQs & Supplier Portal:** The RFQ comparison matrix desktop view, mobile sheet, supplier quote portal (`rfqs/portal.blade.php`), and PO redirect generation now display and default to the vendor's agreed catalog last purchase rate (`vendor_items.unit_price`).
- **Payment Screen Bank Security Alert Fix:** `resources/views/modules/purchase/payments/create.blade.php` conditionally renders the security warning HTML server-side only when an unapproved bank change request is open, and dynamically injects via JS on supplier switch, resolving the `VendorBankChangeTest` assertion failure.
- **RFQ screens:** the vendor catalog is read with one query per page instead of one per cell.
- Fixed `VendorApiController::update`, which used undefined `$companyId`/`$branchId` and failed with a 500 for any vendor without a company or branch.
- Tests added in `PartyDuplicateSearchTest` and `VendorIntelligenceTest` for RFQ last prices, fuzzy duplicate variants ("Shree Ram Traders" / "Shree Ram Trading", "Ram Electricals" / "Rama Electricals").

### Still not covered
- Planning (MRP auto-scheduling) using vendor lead times is part of Phase 1 / MRP planning tasks.

---------------------------------------------------------------------------
## 2026-11-20 - Vendor Master Intelligence: Item Codes, Rate History, Lead Time, MOQ, and Document Expiry Alerts (FRS 2.3 / Task C-19)

### Problem addressed
- Point 6: "Vendors are thin. There are no vendor-specific item codes or names, no price history, lead time, MOQ or document-expiry alerts."
- Vendor records previously only held basic contact, address, and banking data without procurement intelligence or compliance tracking.
- Buyers had no visibility into vendor-specific part numbers on POs, no automated price-hike audit log, no master lead times for MRP scheduling, no vendor-level MOQ validation, and no automated alerts when statutory compliance certificates (MSME, Lower TDS Form 13, ISO, contracts) expired.

### What it does
1. **Vendor Supplied Items Catalog (`vendor_items` table & `VendorItem` model):**
   - Maps internal `Product` SKUs to vendor-specific part numbers (`vendor_item_code`), trade names (`vendor_item_name`), contracted purchase rate (`unit_price`), minimum order quantity (`moq`), and lead time in days (`lead_time_days`).
   - Unique constraint `(tenant_id, vendor_id, product_id)` ensures data integrity with soft-deletes.
   - PO creation screen dynamically looks up vendor's catalog via `GET /purchase/vendors/{vendor}/items/lookup`, automatically pre-filling agreed purchase price and displaying vendor part numbers.
   - Printable PO PDF (`orders.download`) prints `Vendor Item: [Code] — [Name]` under line items when present.
2. **Rate History & Price Hike Audit Log (`vendor_item_price_histories` table & `VendorItemPriceHistory` model):**
   - Automatically logs initial contracted rates and every subsequent price update with `old_price`, `new_price`, `effective_from`, `reason`, and approving user.
   - Interactive modal on Vendor Profile (`#priceHistoryModal`) provides an audit trail of rate revisions over time.
3. **Lead Time & Vendor MOQ Validation (PO Creation Screen):**
   - Auto-calculates suggested PO delivery date (`po_date + lead_time_days`) when lead time is configured on the supplied item.
   - Non-intrusive soft validation on PO line quantity: if entered quantity is below vendor's MOQ, displays an inline helper warning (*"Below vendor MOQ (X)"*) with a 1-click *"Set X"* button to round up.
4. **Statutory & Compliance Document Expiry Tracker (`vendor_documents` table & `VendorDocument` model):**
   - Supports tracking MSME/Udyam certificates, Lower TDS certificates (LDC Form 13), ISO certifications, GST certificates, rate contracts, and NDAs with issue dates, expiry dates, and file attachments.
   - Real-time status computation (`valid`, `expiring_soon`, `expired`).
   - Auto-syncs Lower TDS Form 13 document numbers and validity dates directly to `Vendor` model TDS fields (`ldc_number`, `ldc_valid_from`, `ldc_valid_to`) for full backward compatibility.
   - Prominent alert banner on Vendor Profile (`partials/vendor-document-alert-banner.blade.php`) warns buyers when expired or soon-to-expire documents are detected.
5. **Screens & UI Integration (`purchase/vendors/show.blade.php`):**
   - Added Tab 9: "Supplied Items" with catalog table, "Map Supplied Item" modal, inline edit modals, and price history viewer.
   - Added Tab 10: "Compliance & Docs" with document list, status badges, file download links, and "Upload Document" modal.

### Tests
- `tests/Feature/VendorIntelligenceTest.php` (5 tests):
  - `test_can_map_product_to_vendor_with_code_moq_lead_time_and_rate`
  - `test_updating_vendor_item_price_logs_rate_history`
  - `test_vendor_items_lookup_endpoint_returns_catalog_data`
  - `test_can_upload_compliance_document_and_sync_ldc_fields`
  - `test_document_expiry_status_computation_and_alerts`

### Files
- New:
  - `database/migrations/2026_11_20_190000_create_vendor_items_and_documents_tables.php`
  - `app/Domains/Purchase/Models/VendorItem.php`
  - `app/Domains/Purchase/Models/VendorItemPriceHistory.php`
  - `app/Domains/Purchase/Models/VendorDocument.php`
  - `app/Domains/Purchase/Controllers/VendorItemController.php`
  - `app/Domains/Purchase/Controllers/VendorDocumentController.php`
  - `resources/views/partials/vendor-items-panel.blade.php`
  - `resources/views/partials/vendor-documents-panel.blade.php`
  - `resources/views/partials/vendor-document-alert-banner.blade.php`
  - `tests/Feature/VendorIntelligenceTest.php`
- Edited:
  - `app/Domains/Inventory/Models/Vendor.php`
  - `app/Domains/Purchase/Controllers/VendorController.php`
  - `app/Domains/Purchase/Routes/web.php`
  - `resources/views/modules/purchase/vendors/show.blade.php`
  - `resources/views/modules/purchase/orders/create.blade.php`
  - `resources/views/modules/purchase/orders/pdf.blade.php`
  - `references/01-sales-and-crm.md`
  - `references/08-change-log.md`

---------------------------------------------------------------------------
## 2026-11-20 - Operational Hold Iron Gate & Defaulter Guard Across Quotations, Orders, and Factory Dispatch (Defaulter With 5 Truckloads)

### Problem addressed
- The "Defaulter Who Got 5 Truckloads of Desks" incident: When a customer (e.g. Orbit Real Estate) defaults on outstanding dues, CFO Mr. Mehra sets their status to Inactive (`credit_risk` / `fraud_suspicion` / `compliance_issue` / Hold). However, because status was previously treated as an informational badge without operational locks on existing pipeline artifacts:
  1. Factory warehouse dispatch managers (like Karan) could still select existing sales orders, click "Create Dispatch", confirm dispatch orders, ship trucks outward, and generate Delivery Challan PDFs.
  2. Sales reps could still create new Quotations and convert CRM deals to Sales Orders for inactive/hold customers.
  3. Draft sales orders could still be approved/confirmed.
  4. Purchase orders for inactive/blocked vendors could still be approved.

### What it does
1. **Factory Dispatch Iron Gate (`DispatchOrderService`, `DispatchOrderController`, `DispatchOrderApiController`):**
   - **Creation Guard:** `DispatchOrderService::createDispatchOrder` pre-resolves customer ID and enforces `PartyTransactionGuard::assertCanTransact('customer', $customerId, ..., 'dispatch')`. Web and API controllers catch validation failures and block dispatch generation.
   - **Order Selection Filter:** `DispatchOrderRepository` filters pending sales orders and material requirements so orders belonging to inactive, unverified, or hold customers are excluded from dispatchable queues.
   - **Pending DO Table Visuals:** On `sales/dispatches/index`, any pending order whose customer is on hold is flagged with a red `Financial Hold (Credit risk)` badge, and the "Dispatch Now" button is replaced with a disabled `On Hold (Blocked)` button.
   - **Direct URL Guard:** Visiting `inventory.dispatches.create?sales_order_id=X` for an inactive customer redirects back to dispatches with an explicit flash error.
   - **Outward Release & Challan Lock:** `confirmDispatchOrder()` and `shipDispatchOrder()` check party status; `downloadChallan()` and API `downloadPdf()` explicitly block delivery challan generation for inactive parties. The DO show screen disables "Confirm DO", "Ship Outward", and the Delivery Challan PDF download.
2. **Sales Order Confirmation Guard (`SalesOrderController`, `SalesOrderApiController`):**
   - Confirming a draft sales order checks `PartyTransactionGuard::assertCanTransact('customer', $order->customer_id, ..., 'sales order confirmation')`.
   - On `sales/orders/show`, orders belonging to inactive/hold customers display a prominent `Financial Hold Guard` banner, and the "Confirm Order" button is locked (`Confirmation Blocked (Hold)`).
3. **Quotation & CRM Deal Guard (`Quotation`, `QuotationController`, `QuotationApiController`):**
   - `Quotation::creating` model boot guard and Quotation store endpoints enforce `PartyTransactionGuard::assertCanTransact`.
   - On `crm/deals/show`, if the linked customer is inactive/on hold, an amber/red financial hold warning banner is displayed, and the "+ New Quotation" and "Convert to Sales Order" action buttons are disabled with warning tooltips.
4. **Purchase Order Approval Guard (`PurchaseOrderController`, `PurchaseOrderApiController`):**
   - Approving a PO checks `PartyTransactionGuard::assertCanTransact('vendor', $order->vendor_id, ..., 'purchase order approval')`, returning user-friendly flash errors (web) and 422 JSON errors (API).
5. **Quick Status Toggles & API Hardening (`CustomerController`, `VendorController`, `VendorApiController`):**
   - `updateStatus` and `toggleStatus` in `CustomerController` and `VendorController` no longer bypass the reason form. Supplying a reason deactivates via `PartyStatusService::deactivate()`; if omitted, the user is redirected to the formal deactivation form with a warning, preserving Maker-Checker risk locks.
   - `VendorApiController::destroy` records deactivation reason `business_closed` when orders exist. `store` normalizes `status = 'blocked'` to `inactive` with `compliance_issue`.
6. **E-Way Bill & E-Invoice Guard (`EInvoiceController`):**
   - `generateEWayBill()` and `generateEInvoice()` enforce customer active status checks, preventing E-Way Bill or IRN generation for held accounts.

### Tests
- Expanded `tests/Feature/PartyStatusControlTest.php` with 7 comprehensive integration tests (25 tests total):
  - `quotation_creation_is_blocked_if_customer_is_inactive_or_pending_verification`
  - `sales_order_confirmation_is_blocked_if_customer_was_placed_on_hold`
  - `purchase_order_approval_is_blocked_if_vendor_is_inactive`
  - `factory_dispatch_creation_confirmation_and_challan_generation_are_blocked_for_defaulter_on_hold`
  - `quick_toggle_to_inactive_without_reason_redirects_to_deactivate_form`
  - `vendor_quick_toggle_without_reason_redirects_to_deactivate_form`
  - `eway_bill_generation_is_blocked_for_inactive_customer`

### Files
- Edited:
  - `app/Services/PartyTransactionGuard.php`
  - `app/Domains/CRM/Models/Quotation.php`
  - `app/Domains/CRM/Controllers/QuotationController.php`
  - `app/Domains/CRM/Controllers/Api/QuotationApiController.php`
  - `app/Domains/Sales/Controllers/SalesOrderController.php`
  - `app/Domains/Sales/Controllers/Api/SalesOrderApiController.php`
  - `app/Domains/Purchase/Controllers/PurchaseOrderController.php`
  - `app/Domains/Purchase/Controllers/Api/PurchaseOrderApiController.php`
  - `app/Domains/Sales/Repositories/DispatchOrderRepository.php`
  - `app/Domains/Sales/Services/DispatchOrderService.php`
  - `app/Domains/Sales/Controllers/DispatchOrderController.php`
  - `app/Domains/Sales/Controllers/Api/DispatchOrderApiController.php`
  - `resources/views/modules/crm/deals/show.blade.php`
  - `resources/views/modules/sales/orders/show.blade.php`
  - `resources/views/modules/sales/dispatches/index.blade.php`
  - `resources/views/modules/sales/dispatches/show.blade.php`
  - `tests/Feature/PartyStatusControlTest.php`
  - `references/01-sales-and-crm.md`
  - `references/08-change-log.md`

---------------------------------------------------------------------------
## 2026-11-20 - Vendor Payout Guard & Beneficiary Bank Visibility (Closing C-16 Bank Switch Gap)

### Problem addressed
- The "Friday 5:00 PM Bank Switch" internal diversion fraud: a rogue actor requests an unverified bank account swap right before payout runs. In Accounts Payable, weekly payment runs previously had no visual bank account confirmation and no alert when a bank change request was open, creating a risk that payment could be misdirected or released while verification was pending.

### What it does
1. **Beneficiary Bank Details Card on Payment Creation (`purchase.payments.create`):**
   - Renders a dedicated "Beneficiary Payout Details (Vendor Bank Master)" panel dynamically showing the verified destination Bank Name, Account Number, and IFSC Code with a `Verified Active Account` badge.
   - If a supplier has no bank account on file, it alerts Accounts Payable (`No Bank Account On File`).
2. **Pending Bank Change Security Warning & Confirmation Guard:**
   - When a supplier has an open/pending `PartyBankChangeRequest` (awaiting callback verification), selecting that supplier immediately surfaces a prominent red security alert displaying the request ID and the unapproved proposed bank account (`New Bank Name`, `New Account Number`).
   - Clearly instructs: *"Do NOT release RTGS/NEFT to any new or unconfirmed bank account. Payouts must only be disbursed to the verified active account on record, or held until the independent callback is confirmed."*
   - In both Web (`VendorPaymentController::store`) and API (`VendorPaymentApiController::store`), submitting a payment for a vendor with an unapproved bank change is **refused with validation error** unless the payer explicitly acknowledges that payment is strictly made to the verified active bank account on file (`confirm_pending_bank_change = 1`).
3. **Payment Voucher & PDF Audit Trail:**
   - Both `modules/purchase/payments/show.blade.php` and `modules/purchase/payments/pdf.blade.php` now print the **Beneficiary Payout Account** (Vendor Bank Name, Account Number, and IFSC Code) under the "Paid To (Supplier / Vendor)" section, providing an immutable audit trail for finance teams and auditors.
4. **API Parity (`VendorPaymentApiController`):**
   - `GET /api/purchase/payments/meta` includes `bank_name`, `account_number`, `ifsc_code`, `has_pending_bank_change`, and `pending_bank_change_id` for every vendor.
   - `POST /api/purchase/payments` enforces the same bank change guard.
5. **Phone Protection (Closing the Callback Takeover Loophole):**
   - Added `phone` to `sensitive_fields` in `config/party_verification.php` and `PartyVerificationService`. Any edit to a verified party's phone number automatically reverts the party to `pending_verification` and `inactive`, logging `reverification_required`.
   - `PartyBankChangeService::request` strictly enforces `if (! $vendor->isVerified())`, refusing bank change requests while a vendor is pending verification. This eliminates the loophole where an attacker could change the phone number to their own mule number before requesting a bank change.
6. **Architectural Decisions & Documented Deviations:**
   - *Bank Edits vs FRS Literal Wording:* The FRS states that editing bank details switches the vendor to Pending Verification. In this design, to prevent halting ongoing operations and goods receipts while an administrative bank change is reviewed, verified bank details are locked (`bank_details_locked = true`), keeping the existing verified bank account active for business, while proposed bank details are routed into a separate `PartyBankChangeRequest` awaiting callback verification. Edits to legal identity and contact fields (`gstin`, `pan`, `phone`) DO immediately switch the vendor to `pending_verification` and `inactive`.
   - *Callback Verification Self-Attestation:* Callback verification is recorded via self-attested confirmation ticks, dialed number comparison, and notes by an authorized checker (Maker ≠ Checker segregation of duties). It relies on human operational verification against the master phone on record rather than automated telecom/IVR integration.
   - *Dead Code Elimination:* Removed unused `openBankChangeRequest()` helper from `PartyTransactionGuard.php`.

### Tests
- `tests/Feature/VendorBankChangeTest.php` expanded with 4 tests:
  - `payment_screen_renders_verified_bank_details_and_warns_when_bank_change_is_pending`
  - `payment_store_requires_confirmation_when_bank_change_request_is_pending`
  - `payment_show_and_pdf_display_beneficiary_bank_details`
  - `editing_vendor_phone_sends_to_pending_verification_and_blocks_bank_change_request`

### Files
- Edited:
  - `config/party_verification.php`
  - `app/Services/PartyVerificationService.php`
  - `app/Services/PartyBankChangeService.php`
  - `app/Services/PartyTransactionGuard.php`
  - `app/Domains/Purchase/Controllers/VendorPaymentController.php`
  - `app/Domains/Purchase/Controllers/Api/VendorPaymentApiController.php`
  - `resources/views/modules/purchase/payments/create.blade.php`
  - `resources/views/modules/purchase/payments/show.blade.php`
  - `resources/views/modules/purchase/payments/pdf.blade.php`
  - `tests/Feature/VendorBankChangeTest.php`
  - `references/01-sales-and-crm.md`
  - `references/08-change-log.md`

---------------------------------------------------------------------------
## 2026-11-19 - Party Merge Tool (FRS 2.3 / Task C-18)

### What it does
- `App\Services\PartyMergeService` merges duplicate customer or vendor records into a surviving target record in a single atomic database transaction (`DB::transaction`).
- **Relational Re-parenting:**
  - **Customers:** re-parents `invoices`, `sales_orders`, `customer_payments`, `dispatch_orders`, `sales_returns`, `projects`, and `credit_notes` (`customer_id` updated to target ID). For CRM Deals, Quotations, and Leads: handles customers with **multiple CRM accounts** by querying all accounts where `customer_id = $source->id`. If target has an existing master CRM account, all deals, quotations, and leads across every source account are moved into the target account, and redundant source accounts are soft-deleted; if target has no CRM account, the primary source CRM account is adopted by the target (`customer_id` re-pointed to target) and remaining accounts are consolidated into it, ensuring zero data loss across multi-account structures. Customer profile and 360 view show all linked CRM accounts and load deals from all accounts via `deals` relationship.
  - **Vendors:** re-parents `vendor_bills`, `purchase_orders`, `vendor_payments`, `purchase_advance_payments`, `goods_receipt_notes`, `purchase_returns`, `purchase_rfq_vendors`, `production_order_operations`, `delivery_challans`, and `debit_notes` (`vendor_id` updated to target ID).
  - **Multi-Branch (C-17):** source branches (`party_branches`) are adopted by the target party as active non-HO branches (`is_head_office = false`). Any branch code colliding with target's existing branches (e.g. `'HO'`) is automatically re-coded to next available `'BR-xx'` to guarantee unique key integrity (`party_branches_party_code_unique`), while preserving location names, GSTINs, and addresses.
  - **Contacts:** source contacts (`contacts`) are re-parented to the target party, clearing `is_primary` if target already has an established primary contact.
  - **Accounting (Double-Entry):** all `journal_entries`, `bill_references`, `bill_allocations`, and `bill_settlements` move under the target's `party_id`. Payment allocations link to parent invoices/payments and stay valid.
  - **Opening Balances:** net signed opening balance is consolidated onto the target record and synced via `CustomerOpeningBalanceService`.
- **Statutory & E-Invoice Integrity:**
  - Frozen invoice snapshots (`bill_to_gstin`, `ship_to_gstin`, `place_of_supply_state`), IRNs, and Ack numbers are preserved without change.
- **Safeguards & Permanent Consolidation:**
  - **Legal Entity (PAN/GSTIN) Mismatch Detection:** Live impact preview extracts and compares source and target 10-character PANs. If different PANs are detected, the preview modal surfaces a prominent danger alert warning that distinct legal entities are being combined.
  - **Architectural Decision:** Consolidation is permanent and irreversible; no automated unmerge tool is provided because unmerging hundreds of transactions across financial ledgers and netting opening balances cannot be automated safely.
  - Source party is marked `status = 'inactive'`, `inactive_reason = 'duplicate'`, `merged_into_id = $target->id`, `merged_at = now()`, `merged_by = $user->id`.
  - `PartyTransactionGuard` specifically identifies merged parties and blocks new sales orders/purchase orders with: *"Party 'X' has been merged into another record (#TargetID), so it cannot be used for a new document."*
  - `PartyStatusService::reactivationBlockedReason` prohibits direct reactivation of merged records.
- **Screens & UI:**
  - Reusable modal `partials/party-merge-modal.blade.php` on customer and vendor show pages with live impact preview (`/crm/customers/{customer}/merge-preview`, `/purchase/vendors/{vendor}/merge-preview`), legal entity mismatch warnings, and mandatory confirmation.
  - Amber banner on `partials/party-status-panel.blade.php` displays merged status and a one-click link to the surviving master record.
  - Permissions `crm.customers.merge` and `purchase.vendors.merge` registered in `RbacSeeder`. Only this permission allows a merge (owner/admin via the all-permissions grant); plain edit/update rights no longer do (segregation of duties). Existing custom roles need the permission assigned.
  - A target that was itself merged into another record is refused (the user must pick the surviving record); circular merges are refused too. Covered by 2 more tests (13 in total).

### Tests
- `tests/Feature/PartyMergeTest.php` (11 tests):
  - Customer merge with invoice re-parenting and duplicate archival.
  - Multi-branch & contact adoption under target party (with `code` fixture).
  - Head Office branch code collision resolution (`HO` -> `BR-xx`).
  - Vendor merge with PO and bill re-parenting (with `purchase_order_number` fixture).
  - Self-merge / already-merged party rejection.
  - CRM account handover when target has no CRM account (quotes, deals, and leads preserved).
  - Multi-CRM account consolidation (moves deals across all source CRM accounts into target master account).
  - Transaction guard blocking new orders on merged party.
  - Party status service blocking reactivation of merged records.
  - Live preview legal entity PAN mismatch warning.
  - Web preview and store endpoints.

### Files
- New: `app/Services/PartyMergeService.php`, `app/Domains/CRM/Controllers/CustomerMergeController.php`, `app/Domains/Purchase/Controllers/VendorMergeController.php`, `database/migrations/2026_11_19_180000_add_merged_into_to_customers_and_vendors_tables.php`, `resources/views/partials/party-merge-modal.blade.php`, `tests/Feature/PartyMergeTest.php`
- Edited: `app/Domains/CRM/Models/Customer.php`, `app/Domains/Inventory/Models/Vendor.php`, `app/Domains/CRM/Policies/CustomerPolicy.php`, `app/Services/PartyTransactionGuard.php`, `app/Services/PartyStatusService.php`, `app/Domains/CRM/Routes/web.php`, `app/Domains/Purchase/Routes/web.php`, `database/seeders/RbacSeeder.php`, `resources/views/modules/crm/customers/show.blade.php`, `resources/views/modules/purchase/vendors/show.blade.php`, `resources/views/partials/party-status-panel.blade.php`

---------------------------------------------------------------------------
## 2026-11-19 - Duplicate search before creating a customer or vendor (FRS 2.3)

### What it does
- `App\Services\PartyDuplicateService::find()` searches the same party type (customer or vendor) in the same tenant, ignoring branch/company/RBAC scopes so a restricted user is not blind to a party owned by someone else. It compares: GSTIN, PAN (normalised), email (case-insensitive), mobile (last 10 digits, 8+ digits needed), name or company name (lowercase, punctuation and words like Pvt/Ltd/Private/Limited removed, 3+ characters), billing/primary address (normalised, 12+ characters). A GSTIN already used by one of that party's `party_branches` also points at that party (create it as a branch instead). Returns at most 5 matches, best first; read-only.
- Web: `CustomerController::store` and `VendorController::store` redirect back with the matches (`duplicate_matches`) unless `confirm_duplicate=1` is posted. The create forms include `partials/party-duplicate-warning.blade.php`, which shows the matches live (`crm.customers.duplicate-check`, `purchase.vendors.duplicate-check`) and has the "create anyway" tick box.
- Quick-create modals (`customers.quick-create`, `vendors.quick-create`, API `vendors/quick-create`) cannot ask for confirmation, so they refuse only identity matches (GSTIN, PAN, email, mobile) with a 422 on `name`; a similar name alone does not block them.
- APIs: `POST /api/sales/customers` and `POST /api/purchase/vendors` return 409 with `duplicates` until the caller re-sends with `confirm_duplicate=true`.
- Soft by design: PAN is shared by all branches of one group and a legitimate second record can exist, so a match asks for confirmation instead of blocking. Merging leftovers is C-18.

### Not covered
- CSV imports and lead/deal conversion (they reuse or link an existing customer by their own rules). Edits of an existing party are covered since 2026-11-21 (see that section).
- Existing duplicate rows are not cleaned up.

### Tests
- `tests/Feature/PartyDuplicateSearchTest.php` (11 tests): each match type, normalisation, no false matches, tenant and type isolation, branch GSTIN, web confirm flow for customer and vendor, live lookup and its permission check, quick-create, both APIs.
- Existing suites re-run: party verification, status, bank change, branch structure and opening balance suites pass (75 tests); the sales, purchase, CRM and GST files show the same 11 failures as before this change and no new ones.

### Files
- New: `app/Services/PartyDuplicateService.php`, `resources/views/partials/party-duplicate-warning.blade.php`, `tests/Feature/PartyDuplicateSearchTest.php`
- Edited: `CustomerController.php`, `VendorController.php`, `Api/CustomerApiController.php` (Sales), `Api/VendorApiController.php` (Purchase), `CRM/Routes/web.php`, `Purchase/Routes/web.php`, `modules/crm/customers/create.blade.php`, `modules/purchase/vendors/create.blade.php`

---------------------------------------------------------------------------
## 2026-11-19 - Post C-17 stabilization: PO edit parity, CrmDeal contact mapping, E-Invoice screen error handling

### Fixes and improvements implemented
1. **Purchase Order Draft Edit Form Parity (`edit.blade.php`):**
   - Brought `resources/views/modules/purchase/orders/edit.blade.php` to full parity with `create.blade.php`.
   - Added interactive delivery target toggle (`Our Branch / Warehouse` vs `Customer Site Drop-Ship`).
   - Added cascading Own Branch -> Warehouse dynamic filtering, and Customer Site -> Branch -> Receiver Contact cascading AJAX loading.
   - Added Vendor Branch AJAX selector with auto GST resolution.
   - Updated `PurchaseOrderController::edit()` to eager load `branch`, `vendorBranch`, `deliveryBranch`, `deliveryPartyBranch`, `siteContact`, and `warehouse`.
   - `PurchaseOrderController::update()` runs `syncOwnDeliveryTarget()` to enforce a single delivery target on save.

2. **CrmDeal Contact Association Aligned (`CrmDeal.php`):**
   - Re-pointed `CrmDeal::contact()` relationship back to `App\Domains\CRM\Models\CrmContact::class` (foreign key `crm_contact_id`).
   - Fixes deal contact mismatch where deal lists, API, `DealHealthService`, quotation contact auto-fill, follow-up emails, and Google Calendar attendees showed empty/wrong contact references after the party contacts migration.

3. **E-Invoice Validation & Graceful Screen Error Handling:**
   - Indian GST law mandates Buyer GSTIN for B2B E-Invoicing; `EInvoiceService::buildEInvoicePayload()` validates this and throws `\InvalidArgumentException`.
   - In `EInvoiceService::generate()`, wrapped payload generation in `try...catch (\InvalidArgumentException $e)` to record `einvoice_status = 'Failed'` and return a user-friendly error message rather than unhandled 500 exceptions.
   - In `EInvoiceController::generateEInvoice()` and `exportJson()`, added `try...catch` handling to redirect back with `with('error', $message)` for web requests or return 422 JSON for API/AJAX.
   - In `resources/views/modules/sales/invoices/show.blade.php`, added a dismissible E-Invoice Notice alert banner when generation fails, showing the exact error and an action button to view customer details and update GSTIN. Marked with `d-print-none` to keep prints and PDFs clean.

### Files
- `resources/views/modules/purchase/orders/edit.blade.php`
- `app/Domains/Purchase/Controllers/PurchaseOrderController.php`
- `app/Domains/CRM/Models/CrmDeal.php`
- `app/Domains/Sales/Services/EInvoiceService.php`
- `app/Domains/Sales/Controllers/EInvoiceController.php`
- `resources/views/modules/sales/invoices/show.blade.php`

---------------------------------------------------------------------------
## 2026-11-18 - C-17 Branch GSTIN, multi-branch customers/vendors and unified role-based contacts (FRS 2.3 / BASE-03)

### Decisions implemented
1. **One legal party = one ledger, one credit limit, one history.** Bills are always in the parent's legal name; branches supply address, GSTIN, state and contacts.
2. **Same-PAN enforcement:** own branches (`branches.gst_number`) and party branches (`party_branches.gstin`) must match the parent's 10-character PAN (from parent PAN or parent GSTIN); different PANs are blocked.
3. **Own-branch GSTIN is optional:** blank falls back to company GSTIN; at most one `is_head_office` branch per company; companies do not auto-create an HRMS branch on creation.
4. **Branch-level verification:** adding a new non-HO party branch or editing a branch GSTIN puts **only that branch** into `pending_verification` + `inactive` while keeping the parent party active. Head Office mirrors parent address, GSTIN and verification status (no second verification cycle). Maker ≠ checker applies via `PartyVerificationService`, reusing `crm.customers.verify` / `purchase.vendors.verify` and logging to `party_verification_logs`.
5. **Unified contacts & roles:** one `contacts` table serves customers, vendors, and pre-conversion `crm_account` records (which automatically re-point to `customer` when `CrmAccount.customer_id` is linked). Existing `crm_contacts` rows are migrated into `contacts` with free-text roles mapped to `contact_roles` (12 seeded system roles + tenant-admin custom roles in CRM Settings). `crm_contacts` is retained until a later cleanup release.
6. **Quotations do not carry branches:** branch selection starts at Sales Order and flows forward to Invoice and Dispatch.
7. **Document GSTIN freezing & GST compliance:** SO, Invoice, PO and Vendor Bill freeze seller/buyer/bill-to/ship-to GSTIN and state codes at creation. `Gstr1ReturnService` reads frozen `bill_to_gstin` and `place_of_supply_state` (IGST Sec 10(1)(b): Bill-To state for B2B, Ship-To for unregistered/B2C). `EInvoiceService` removes the hardcoded `27AABCU9603R1ZM` fallback (fails clearly on missing B2B GSTIN) and emits `ShipDtls`. `EWayBillService` uses frozen branch/ship-to details instead of hardcoded address fallbacks. `Gstr2bReconciliationService` matches vendor branch GSTINs via `vendor_bills.supplier_gstin` / `party_branches.gstin`. Both GSTR-1 and GSTR-2B enforce a **mixed-GSTIN guard** that blocks filing when documents in a period span multiple own GSTINs.
8. **Purchase delivery targets & warehouses:** on PO, `branch_id` stays the ordering/billing branch (`buyer_gstin`); `delivery_branch_id` is the receiving branch (visible to both branches via PO's branch scope); delivery requires **exactly one** of warehouse (`location`, filtered by branch on the PO form), own branch (`delivery_branch_id`), or customer site (`delivery_party_branch_id` + `site_contact_id`). Customer-site drop-ship GRNs receive into a virtual `Direct-to-Site (Virtual)` warehouse (`Warehouse::ensureDirectToSiteWarehouse`) so 3-way match and billing run without inflating physical godown stock.

### Switches and rollout
- `PARTY_BRANCHES_ENABLED=false` (in `config/party_verification.php`) hides document branch selectors and reverts to single-address document UI.
- Rollout commands:
  ```bash
  php artisan migrate
  php artisan db:seed --class=RbacSeeder
  ```

### Files
- **Phase 1 (Own branch GSTIN & snapshots):**
  - Migration `2026_11_18_120000_add_gst_and_head_office_to_branches_and_doc_snapshots.php`
  - `app/Domains/HRMS/Models/Branch.php`, `app/Domains/HRMS/Controllers/OrgController.php`
  - `resources/views/modules/hrms/org-structure/branch-form-fields.blade.php`, `tabs/branches.blade.php`
  - `app/helpers.php` (`billing_organization_details`)
  - `app/Domains/Inventory/Models/Warehouse.php`, `WarehouseController.php`, `WarehouseRepository.php`, `resources/views/modules/inventory/warehouses/index.blade.php`
- **Phase 2 (Party branches, contacts, roles, verification, RBAC, backfill):**
  - Migrations `2026_11_18_130000_create_party_branches_and_contacts_tables.php`, `2026_11_18_131000_backfill_party_branches_and_migrate_crm_contacts.php`
  - `config/party_verification.php` (`branches_enabled`)
  - `app/Models/PartyBranch.php`, `PartyContact.php`, `ContactRole.php`, `app/Policies/PartyBranchPolicy.php` (registered in `AppServiceProvider`)
  - `app/Services/PartyStructureService.php`; edits to `PartyVerificationService.php`, `PartyTransactionGuard.php`, `GuardsPartyTransactions.php`
  - Edits to `Customer.php`, `Vendor.php`, `CrmAccount.php`, `CrmDeal.php`, `CrmAccountController.php`, `CrmSettingsController.php`, `CustomerVerificationController.php`, `VendorVerificationController.php`
  - New controllers: `CustomerBranchController.php`, `VendorBranchController.php`, `ContactRoleController.php`
  - Routes in `CRM/Routes/web.php`, `Purchase/Routes/web.php`; permissions in `database/seeders/RbacSeeder.php`
  - Views: `partials/party-branches-panel.blade.php`, `partials/party-contacts-panel.blade.php`, `partials/party-branch-verification-queue.blade.php`; edits to customer/vendor `show` and `verification` views, `crm/settings/index.blade.php`
- **Phase 3 (Sales documents & GST):**
  - Migration `2026_11_18_140000_add_party_branches_to_sales_documents.php`
  - Edits to `SalesOrder.php`, `Invoice.php`, `DispatchOrder.php`, `SalesOrderService.php`, `SalesOrderController.php`, `InvoiceController.php`, `DispatchOrderController.php`, `DispatchOrderService.php`
  - Edits to `Gstr1ReturnService.php`, `EInvoiceService.php`, `EWayBillService.php`
  - Views: `sales/orders/create.blade.php`, `sales/orders/pdf.blade.php`, `sales/invoices/pdf.blade.php`, `sales/dispatches/pdf.blade.php`
- **Phase 4 (Purchase documents & GSTR-2B):**
  - Migration `2026_11_18_150000_add_party_branches_to_purchase_documents.php`
  - Edits to `PurchaseOrder.php`, `PurchaseRfqVendor.php`, `VendorBill.php`, `PurchaseOrderService.php`, `PurchaseOrderController.php`, `GoodsReceiptNoteService.php`, `VendorBillService.php`, `Gstr2bReconciliationService.php`
  - Views: `purchase/orders/create.blade.php`, `purchase/orders/pdf.blade.php`
- **Phase 5 (Tests):** `tests/Feature/PartyBranchStructureTest.php`

### Test status (verified in SQLite in-memory test environment)
- `PartyBranchStructureTest.php`: 7/8 tests passed on first run; remaining 1 test (`gstr2b_matches_vendor_branch_gstin_and_blocks_mixed_buyer_gstin_periods`) fixed by aligning `VendorBill::create()` in the test and `VendorBill.php` `$fillable`/`$casts` with the actual database column `grand_total` (removed non-existent `total_amount`).
- `PartyVerificationTest.php`: 20/20 passed (101 assertions).
- Run locally to verify against the pre-existing 37-failure baseline:
  ```bash
  php artisan test tests/Feature/PartyBranchStructureTest.php
  php artisan test tests/Feature/PartyVerificationTest.php tests/Feature/PartyStatusControlTest.php
  ```

### Known limits (by design, deferred to later phases)
- Full per-GSTIN separate GSTR-1 / GSTR-2B filing is a later phase (mixed-GSTIN guard ships in this release).
- Site-wise outstanding (`FIN-F06`): receipts and payments remain parent-ledger only.
- Quotations continue to use lead/account text addresses until conversion.
- Legacy `crm_contacts` table and `customers.gstin` / `billing_address` / `shipping_address` columns remain intact until a later cleanup release.

---------------------------------------------------------------------------
## 2026-11-18 - C-16 (states part) Inactive reasons, reactivation control and transaction blocking (FRS 2.3)

**Decision taken:** keep ONE inactive state (no separate Hold state). This deviates from the FRS wording, which lists hold and inactive as separate states; the behaviour the FRS asks for (blocks transactions, recorded reason, controlled reactivation) is provided through Inactive + reason. Record this as an agreed deviation.

### What it does
1. **Making a verified party inactive needs a reason.** From the list menu, the party page or the edit form link, the user picks a reason (Dormant, Business closed, Duplicate, Credit risk, Suspected fraud, Compliance issue, Other; "Other" needs a note). Who, when, reason and note are stored on the party and in `party_verification_logs`. No second person is needed to make a party inactive.
2. **Risk reasons** (Credit risk, Suspected fraud, Compliance issue; configurable in `config/party_verification.php`): the party can only be reactivated by a **different** user who holds `crm.customers.reactivate` / `purchase.vendors.reactivate`. The user who stopped it cannot lift it, and a user without the permission cannot. Owner and company admin hold it by default.
3. **Other reasons:** anyone who may edit the party can reactivate it, as before; the reason fields are cleared and a `reactivated` log row is written.
4. **No side door:** a model guard (`HasPartyStatusControl`) enforces the same for any code path. A legacy change to inactive without a reason is recorded as reason "Other"; a direct reactivation of a risk-stopped party is reverted. The legacy toggle endpoints, the edit form and the API return a clear error instead of silently doing nothing.
5. **Transaction blocking:** a NEW document is refused with a clear message when its customer/vendor is inactive or still unverified: sales order, dispatch (customer); purchase order, RFQ vendor line, advance payment, subcontract delivery challan (vendor). Implemented on the document models (`GuardsPartyTransactions`) so web, API and services are all covered, and the guard ignores ownership scoping so it cannot fail open.
6. **Not blocked on purpose:** existing documents (they can still be edited, received, billed, cancelled), invoices, bills, goods receipts, customer payments/receipts, returns, quotations, and vendor payments for normal inactive reasons (Dormant, Business closed, Duplicate, Credit risk, Other).
6a. **Vendor payments — blocked for Fraud/Compliance, warning for normal inactive.**
   - **Blocked:** Creating a new `VendorPayment` is refused (via `PartyTransactionGuard::assertCanPayVendor` on `VendorPayment`) when the vendor is **unverified** or inactive for a payment-blocking reason (**Suspected fraud** or **Compliance issue**, configured via `blocks_payments` in `config/party_verification.php`). Such suppliers are also excluded from the payment screen list.
   - **Warning only (settling old dues):** For non-payment-blocking inactive reasons (Dormant, Business closed, Duplicate, Credit risk, Other), the payment screen and payments meta API list **inactive, verified suppliers that have an open bill or a payable opening balance**, labelled "(inactive)", with a warning ("This supplier is inactive (reason). You can still pay what is owed, but check that you meant to pay them."). The bill, purchase return and advance screens were not changed (bills come from goods receipts; advances are new commitments and stay blocked; returns were not asked for).
7. **Screens:** the lists show "Pending verification" for an unverified party and the reason under "Inactive"; the party page shows a banner with the reason, who and when, and a Reactivate button (or why you cannot); the edit form shows a verified party's status read-only with a Make inactive / Reactivate link; "Mark as Active" is hidden for unverified parties in both lists; the vendor list no longer shows the status toggle for unverified vendors.

### Switches and rollout
- `PARTY_TRANSACTION_BLOCK_ENABLED=false` switches the blocking off; `PARTY_VERIFICATION_ENABLED=false` switches off all of C-15/C-16 status behaviour.
- `php artisan migrate` adds `inactive_reason`, `inactive_note`, `inactivated_by`, `inactivated_at` (all nullable) to customers and vendors; `php artisan db:seed --class=RbacSeeder` seeds the two reactivate permissions (granted to `tenant_owner`, `company_admin` and `super_admin`).
- **Workflow change to be aware of:** customers created from a deal conversion start unverified, so a **new sales order for such a customer is refused until its verifier verifies it** (and likewise unverified vendors for purchase orders). That is the intended effect of the verification control, but it adds a step to the lead-to-order flow.

### Files
- Migration `2026_11_18_100000_add_inactive_reason_to_customers_and_vendors.php`; config keys `transaction_block_enabled`, `inactive_reasons`
- `app/Models/Concerns/HasPartyStatusControl.php` (on `Customer` and Inventory `Vendor`), `app/Models/Concerns/GuardsPartyTransactions.php` (on `SalesOrder`, `DispatchOrder`, `PurchaseOrder`, `PurchaseRfqVendor`, `PurchaseAdvancePayment`, Production `DeliveryChallan`), and payment guard on `VendorPayment`
- `app/Services/PartyStatusService.php`, `PartyStatusException.php`, `PartyTransactionGuard.php`
- Controllers: new `CustomerStatusController`, `VendorStatusController`; edited `CustomerController`, `VendorController`, `Api/CustomerApiController`, `Api/VendorApiController`
- Routes `customers/{customer}/deactivate|reactivate`, `vendors/{vendor}/deactivate|reactivate`
- Views: `partials/party-status-form`, `partials/party-status-panel`, `modules/crm/customers/status`, `modules/purchase/vendors/status`; edits to customer and vendor `index`, `show`, `edit`
- Seeder: the two reactivate permissions
- Payment screen: `PartyStatusService::inactiveVendorIdsWithDues`, edits to `VendorPaymentController::create`, `Api/VendorPaymentApiController::meta`, `modules/purchase/payments/create.blade.php`
- Tests: `tests/Feature/PartyStatusControlTest.php` (17 tests); one assertion in `PartyVerificationTest` updated (a verified party's edit form no longer shows the Active dropdown)

### Test status (honest)
- `PartyStatusControlTest`: 17 passed; `PartyVerificationTest` 20 passed; `VendorBankChangeTest` 15 passed.
- Customer/vendor/sales/purchase/CRM/accounting subset after the transaction guard and status rules were added: 37 failed, 597 passed, and the failing tests are **exactly the same 34 failure groups** recorded before any of this work (and that also fail on a clean checkout), so no existing test newly fails. They cannot prove the code paths they fail before reaching are unaffected.
- A wider filter that also included Production tests ran out of memory inside a PDF test and gave no result; Production tests that use `DeliveryChallan` (guarded for vendor_id) were therefore not separately verified.
- A clean full-suite result has still not been obtained. The UI was checked through rendered HTML in tests, not in a browser.

### Known gaps / not done
- No separate Hold state (agreed deviation above).
- The bill, purchase return and advance screens still list only active suppliers.
- Quotation conversion, invoices, bills, receipts, returns and vendor payments for non-fraud/compliance inactive reasons are not blocked. Whether a quotation for an inactive customer should be refused is a policy decision not yet taken.
- The guard checks creation only. Re-saving an existing RFQ re-creates its vendor lines, so editing an old RFQ whose vendor has since gone inactive is refused with the vendor message.
- Reactivation has no separate request/approval queue: the different authorised person acts directly on the party.
- Same one-approver limitation as before: a tenant with a single holder of the reactivate permission cannot reactivate a party that person stopped.
- Inactive reasons are not shown in exports or reports.

---------------------------------------------------------------------------
## 2026-11-17 - C-16 (bank part) Callback-verified changes to a vendor's bank details (FRS 2.3)

Scope: **vendors only**. Customers' bank fields are not covered yet.

### What it does
1. Once a vendor's bank details are verified they are **locked** (`vendors.bank_details_locked`, default true, so all existing vendors are protected). A new vendor starts unlocked so its first details can be entered and verified; it locks when first verified.
2. A locked vendor's bank name / account number / IFSC can no longer be changed by editing the vendor. The edit form shows them read-only with a **Request bank change** button; web and API updates ignore those fields and say so.
3. A user with `purchase.vendors.edit` raises a **bank change request** (new details + reason). The live details and the vendor's status do not change. Refused if: a request is already open, the details are unchanged, or the vendor has no phone on record.
4. A **different** user with `purchase.vendors.bank_change.approve` opens the request, phones the vendor on the number that was on record **when the request was raised**, then records the number called, the person spoken to and notes, ticks that the number came from the vendor record (not the request) and that the vendor confirmed the new account, and approves. Approval is refused if the number called does not match, or if the vendor's phone or bank details changed after the request was raised.
5. Approval applies the new details in one transaction and writes old and new values and the callback to `party_verification_logs`. Reject needs a reason and keeps the old details. The requester can cancel; the requester can never approve or reject.
6. The vendor page shows "Bank details locked", and a warning while a request is open ("do not pay to any new account until approved").
7. **No side door:** `ProtectsBankDetails` reverts any other change to a locked vendor's bank fields (API, import, code) and logs a warning.

### Switches and rollout
- `PARTY_BANK_CHANGE_CONTROL_ENABLED=false` returns to direct editing (also off if `PARTY_VERIFICATION_ENABLED=false`).
- `php artisan migrate` adds the column and `party_bank_change_requests` table; `php artisan db:seed --class=RbacSeeder` seeds the new permission (granted to `tenant_owner`, `company_admin` and `super_admin`). Give it to other roles in Roles & Permissions.
- Existing vendors become locked immediately: to correct their bank details someone must raise a request.

### Files
- Migration `2026_11_17_110000_create_party_bank_change_requests.php`; config keys in `config/party_verification.php`
- `app/Models/PartyBankChangeRequest.php`, `app/Models/Concerns/ProtectsBankDetails.php` (on Inventory `Vendor`)
- `app/Services/PartyBankChangeService.php`, `PartyBankChangeException.php`; small edit to `PartyVerificationService::verify` (locks on first verification)
- `app/Domains/Purchase/Controllers/VendorBankChangeController.php`; edits to `VendorController::update` and `Api/VendorApiController::update`
- Routes in `Purchase/Routes/web.php` (`purchase.vendors.bank-changes.*`)
- Views: `modules/purchase/vendors/bank-change-create|bank-changes|bank-change-show`, `partials/vendor-bank-change-panel`; edits to vendor `edit`, `show`, `index`
- Seeder: `purchase.vendors.bank_change.approve`
- Tests: `tests/Feature/VendorBankChangeTest.php` (15 tests)

### Test status (honest)
- `VendorBankChangeTest`: 15 passed; together with `PartyVerificationTest` 34 passed at the time of this change.
- Customer/vendor/sales/purchase/CRM/accounting subset re-run after this change: 37 failed, 592 passed. The set of failing tests is **identical** to the earlier run (which equals the run with these changes stashed); the 15 extra passes are the new tests.
- A clean full-suite result has still not been obtained (memory-limit crash). The UI was checked through rendered HTML in tests, not in a browser.

### Known gaps / not done
- Customers' bank details are not protected.
- No notification or email when a request is raised or applied; approvers find requests through the "Bank change requests" button on the vendor list.
- No payment hold and no warning on the payment screens themselves; the warning is on the vendor page only. The vendor's bank fields are not read by any payment screen in the code searched.
- No supporting-document upload (cancelled cheque or bank letter).
- Residual risk: the phone number on file can still be edited freely by someone with vendor edit rights. The service refuses approval if the phone changed after the request, but a phone changed *before* a request is trusted. A phone-change control would close this.
- The same one-approver limitation as C-15 applies: a tenant with a single holder of the approve permission cannot approve its own request. The RBAC / self-approval work is still postponed.

---------------------------------------------------------------------------
## 2026-11-17 - C-15 Second-person verification of customers and vendors (FRS 2.3)

### What it does
1. A customer or vendor created from a screen, API call or CRM deal conversion starts as `pending_verification` and **inactive**, whatever status was requested.
2. A different user who holds `crm.customers.verify` (customers) or `purchase.vendors.verify` (vendors) opens the record, ticks that they checked each detail the party has (GSTIN, PAN, bank), and clicks **Verify and activate**, or **Return for correction** with a reason.
3. The creator, or whoever last edited GSTIN/PAN/bank details, **cannot verify** (enforced in `PartyVerificationService::verify`, not only in the UI).
4. Editing GSTIN, PAN, bank name, account number or IFSC on a verified party sends it back to pending and inactive; the editor becomes the new submitter.
5. A returned party goes back to pending when the details are corrected, or via **Resubmit**.
6. Every submit, verify, return, resubmit and re-verification is written to `party_verification_logs` (who, from/to status, remarks, old/new values).
7. Create forms no longer offer an Active choice: they show "Inactive until verified" (hidden `status=inactive`). Edit forms lock the status field while the party is unverified. Verified and older parties keep the normal dropdown.
8. The customer list hides **Mark as Active** in the 3-dot menu for unverified customers (the backend check remains as a safety net). The vendor list toggle was not changed: it still shows and is refused with an error for an unverified vendor.

### Safety nets
- Model guard (`HasPartyVerification`): a party that is not verified can never be **saved** as active, on any code path (API, import, quick-create).
- `verification_status` defaults to `verified`, so existing rows and any code path that does not ask for verification (seeders, tests, imports) behave as before. Existing rows are labelled "Legacy record, not independently verified".
- Switch the whole control off with `PARTY_VERIFICATION_ENABLED=false` (config `party_verification.php`); forms and behaviour then return to the old dropdown and immediate activation.

### Where it applies
Customers: web create/quick-create/update, API create/update, deal conversion (web and API), CRM account creation, lead-to-deal conversion API.
Vendors: web create/quick-create/update/toggle, API create/quick-create/update/toggle, vendor auto-created from the product API.

### Files
- Migration `2026_11_17_100000_add_party_verification_to_customers_and_vendors.php`: verification columns on `customers` and `vendors`; PAN and bank fields on `customers`; `party_verification_logs` table. Permissions are seeded in `RbacSeeder`.
- `config/party_verification.php`
- `app/Models/Concerns/HasPartyVerification.php` (trait on `Customer` and Inventory `Vendor`), `app/Models/PartyVerificationLog.php`
- `app/Services/PartyVerificationService.php`, `PartyVerificationException.php`
- Controllers: new `CustomerVerificationController`, `VendorVerificationController`; edited `CustomerController`, `VendorController`, `CrmDealController`, `CrmAccountController`, `Api/CrmDealApiController`, `Api/LeadApiController`, `Api/CustomerApiController`, `Api/VendorApiController`, `Inventory/Api/ProductApiController`
- Routes: `CRM/Routes/web.php`, `Purchase/Routes/web.php` (queue, verify, return, resubmit)
- `database/seeders/RbacSeeder.php`: the two new permissions
- Views: customer and vendor `create`, `edit`, `show`, `index`, new `verification` queue pages, `partials/party-verification-panel`, `partials/party-verification-queue`
- Tests: `tests/Feature/PartyVerificationTest.php` (20 tests)

### How to roll out
```
php artisan migrate
php artisan db:seed --class=RbacSeeder
```
Give users who should verify the verify permission in Roles & Permissions (owner and company admin already have it).

### Test status (honest)
- `PartyVerificationTest`: 20 passed (latest run).
- Customer/vendor/sales/purchase/CRM/accounting subset at the time of C-15: 37 failed, 577 passed. The failing classes were re-run with these changes stashed and **fail the same way without them**, so none are caused by this work. Because they fail before reaching some of the changed code, they cannot prove those paths are unaffected.
- The full suite crashed with a PHP memory-limit error; **a clean full-suite result has not been obtained**.
- The UI was checked through rendered HTML in tests, **not in a browser**.

### Known gaps / not done
- (Superseded 2026-11-18) Transaction blocking now exists for new sales orders, dispatches, purchase orders, RFQ vendor lines, advance payments and subcontract challans; other document types are deliberately not blocked.
- No supporting-document upload (GST certificate, cancelled cheque) and no live GSTIN lookup; the verifier ticks that the check was done.
- Self-verification rule is fixed: no exceptions. A tenant with one verifier cannot activate new parties (workaround: the config flag). Planned with the RBAC / self-approval work: allow it per role through Own scope on the verify permission, and a lone-owner rule, both logged. Decisions still open.
- Customers' PAN and bank fields were added to forms and validation only; they are not yet used elsewhere (invoicing, payments).
- One message in the vendor create form was reworded on request; the same text elsewhere (customer forms, banner, flash messages) still uses the older "second person" wording.
- **Editing a verified party is only half controlled.** Changing GSTIN, PAN (and, for customers, bank details) saves the new value immediately and then sets the party to pending and inactive until a second person verifies; the old value is overwritten, the whole party is deactivated while it waits, and the verifier screen does not show old beside new (the log has both). Phone, email, name, addresses, payment terms, TDS and opening balance can be edited freely and are not logged.
- **Status is only Active / Inactive** (by decision, no Hold state). Superseded for reasons, reactivation control and transaction blocking by the 2026-11-18 section above.
- Customers' bank details have no callback control (vendors only).

### Proposed, not built (waiting for decisions)
- **Tiered change control** for party edits: callback + approval for bank details, phone and email; second-person verification with the old value kept live until approved for GSTIN, PAN and legal name (and no deactivation of the party); other fields applied immediately but logged old to new. The approver would see a before/after table. Open questions: field-to-tier mapping, whether customers are included, whether field-level history (tracker A-25) is built now.
- **Hold / inactive reasons and reactivation:** built on 2026-11-18 as Inactive + reason (see above); the separate Hold state was not built.
