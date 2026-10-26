# TDS module — Phase 1 (non-salary TDS + customer TDS receivable)

## Context
Item 2 of `missing-accounting-features.md`. Today TDS is just a "TDS Payable" ledger (2140), a payroll deduction that posts to it, and `tds_section`/`tds_rate` fields on transporters. Vendor bills always credit the vendor the full `grand_total`, nothing tracks sections or thresholds, and there are no challans, no 26Q data and no Form 16A. A Tally user expects TDS to be deducted automatically when a vendor bill is booked.

Decisions already made:
- Phase 1 covers non-salary TDS only (194C/J/I/H/Q and similar). Salary TDS (24Q, Form 16) moves to Phase 2, together with the "Payroll statutory returns" item.
- Each section gets its own payable ledger.
- Basic customer-side TDS goes on receipts (to TDS Receivable 1640).

Rejected: storing sections in the `tax_rates` table (`type = tds`). TDS needs thresholds, rates per deductee type and a no-PAN rate, so it gets its own master.

## Data model (one migration, following `2026_09_30_180000_create_gst_return_tables.php`, which also seeds permissions and role grants)

1. **`tds_sections`** (tenant-owned, extends `BaseModel`)
   - Columns: `code` (194C…), `name`, `nature_of_payment`.
   - Rates: `rate_individual` (for individuals/HUF), `rate_other`, `rate_no_pan` (206AA, default 20).
   - Thresholds: `single_threshold`, `aggregate_threshold`, plus `threshold_period` (`per_transaction`, `annual` or `monthly`, the last for rent).
   - `payable_account_id`, `is_active`.
   - Seeded starter rows, editable per tenant:
     - 194C: 1% / 2%; 30k single, 1L annual.
     - 194J (professional): 10%; 50k.
     - 194J (technical): 2%; 50k.
     - 194I (plant and machinery): 2%.
     - 194I (land and building): 10%; 50k a month.
     - 194H: 2%; 20k.
     - 194Q: 0.1%; 50L annual.
     - 194A: 10%.
2. **Per-section ledgers.** New system accounts 2141–2148 (`tds_payable_194c` …) under parent 2000, subtype `duties_taxes`.
   - Add them to `SystemAccount.php` (the list is frozen, so new rows go at the end) and to the seed list in `ChartOfAccountsService.php:218`.
   - 2140 stays as-is for salary TDS (192) and the balances already posted to it.
   - Existing tenants get the new rows from a backfill migration, because `provisionDefaultsIfMissing` skips any tenant that already has accounts.
3. **`vendors` new columns:**
   - `tds_applicable`, `tds_section_id`, `deductee_type` (individual, huf, company, firm, others).
   - Section 197 lower-deduction certificate: `ldc_number`, `ldc_rate`, `ldc_valid_from`, `ldc_valid_to`, `ldc_limit`.
4. **`vendor_bills` new columns:** `tds_section_id`, `tds_base_amount`, `tds_rate`, `tds_amount`, `tds_overridden`.
5. **`tds_deductions`**, the register that 26Q and Form 16A are built from:
   - Who: `tds_section_id`, `party_type`/`party_id`, `pan` snapshot, `deductee_type`.
   - Where it came from: `source_type`/`source_id`, `journal_entry_id`.
   - The deduction: `deduction_date`, `base_amount`, `rate`, `tds_amount`, `reason` (normal, no_pan, lower_cert, below_threshold), `fiscal_year_id`, `quarter`.
   - Payment link: `tds_challan_id` (nullable).
6. **`tds_challans`**:
   - Challan details: `challan_number`, `bsr_code`, `deposit_date`, `period_month`.
   - Amounts: `tax_amount`, `interest`, `late_fee`.
   - Posting: `bank_account_id`, `journal_entry_id`.
7. **`tds_configurations`**: TAN, deductor type, responsible person name, designation and PAN, and address. Modelled on `app/Models/GstConfiguration.php`, since no TAN field exists anywhere today.
8. **`customer_payments`** new columns: `tds_amount`, `tds_section_code`, `tds_certificate_received`.

## Services (`app/Domains/Accounting/Services/Tds/`)
1. **`TdsCalculator`**: `forBill(vendor, base, date, ?billId)` returns section, rate, amount and reason.
   - Rate order: lower-deduction certificate (if valid on that date and under its limit), then no-PAN rate, then the deductee-type rate.
   - Thresholds:
     - Per-transaction: check the base against `single_threshold`.
     - Annual: sum the vendor's base for the year from `tds_deductions`, including rows below threshold.
     - Catch-up: on the bill that first crosses the annual limit, deduct on the earlier untaxed base too. That is how 194C works.
   - Bound to an interface in `AppServiceProvider::register()`. Purchase calls the interface, not the accounting tables directly.
2. **`TdsDeductionRecorder`**: writes or updates `tds_deductions` rows for a posted journal, and is idempotent on source. Bills below the threshold are recorded too, so the annual totals stay correct.
3. **`TdsChallanService`**: records a challan and links the chosen open deductions.
   - The journal debits each section ledger and debits interest and late fee to expense accounts (5xxx), then credits the bank.
   - Posts through `JournalService::postOnce` with reference type `tds_challan`.
4. **`TdsReturnService`**: builds 26Q data for a quarter.
   - Deductee rows: PAN, section, date, amount paid, TDS amount, challan link.
   - Challan rows, a CSV/Excel export, and a list of problems (missing PAN or TAN, deductions not linked to a challan).
   - Reuses `GstReportPeriod` for periods and the existing report exports.
5. **`Form16AService`**: a quarterly PDF for each vendor, built with the existing PDF export used by report exports.

## Posting changes
- **`VendorBillService::storeBill()`** (and the service-bill path in `VendorBillController::storeService`):
  - Calls the calculator and stores the TDS fields on the bill.
  - Sets `due_amount = grand_total − tds_amount`.
  - The bill form shows the TDS that will be deducted and lets the user override it, which sets `tds_overridden`.
- **`PostPurchaseBillJournal.php:344`**:
  - Credits AP 2010 with `grand_total − tds_amount`.
  - Adds a credit line to the section's payable ledger, then calls `TdsDeductionRecorder`.
  - The bill-wise `newRef` already covers the whole AP line, so the bill opens at the net amount and the register needs no change.
- **Every place that recomputes `due_amount = grand_total − paid`** must subtract `tds_amount` too: `VendorPaymentService.php:78-86,190-196`, `VendorPaymentApiController`, `VendorBillApiController`, `PurchaseReturnController` and its API, and `PurchaseOrder`. The cleanest fix is a `VendorBill::netPayable()` accessor that all of them call.
- **Manual TDS:** when a journal or payment voucher line hits a TDS section ledger, the voucher form asks for the party and base amount and writes a `tds_deductions` row. This covers rent or professional fees booked directly, without a vendor bill.
- **Customer TDS:**
  - `PostCustomerPaymentJournal` debits 1640 for `tds_amount`, so the invoice is settled at gross: bank plus TDS equals the amount allocated.
  - The customer payment form gets a TDS field.
  - A "TDS Receivable" register lists entries by customer, quarter and whether the certificate was received.

## UI, routes, permissions
- **Screens** under `resources/views/modules/accounting/tds/`: sections master, configuration (TAN), deductions register, challans (list and create), 26Q quarter view and export, Form 16A, and the TDS receivable register.
- **Routes** go in `app/Domains/Accounting/Routes/web.php` near the GST block (lines 166–177). **Menu:** a "TDS" group in `Routes/menu.php` next to GST (lines 57–61).
- **Vendor create/edit forms** get a TDS block: applicable, section, deductee type, lower-deduction certificate. Validation goes in `VendorController` and the API controller, using `TaxIdentityService::panRules`.
- **Permissions:**
  - New: `accounting.tds.view`, `accounting.tds.configure`, `accounting.tds.challans.create`, `accounting.tds.returns.view`, `accounting.tds.returns.export`.
  - Granted to the accountant role; the auditor role gets view only.
  - Seeded in both the migration and `RbacSeeder.php`. Every check goes through `AccessService::allows`.
- **Provisioning:** `ProvisionAccountingMasters` also seeds the TDS sections. The backfill migration seeds them for existing tenants.

## Out of scope (Phase 2)
- 24Q, Form 16, and the salary income-tax calculation.
- TDS on advance payments, including reversing it when the bill arrives.
- FVU/TRACES file format (Phase 1 exports CSV/Excel in the 26Q layout).
- Matching TDS receivable against 26AS.
- TCS.

## Verification
- **New `tests/Feature/Accounting/TdsTest.php`:**
  - Calculator: below the threshold no TDS; crossing the annual 194C limit triggers catch-up; no PAN gives 20%; a valid lower-deduction certificate gives its rate, and an expired one does not.
  - Bill posting: AP is credited net, the section ledger is credited, the journal balances, and `due_amount`, the bill-wise outstanding and the payment screens all show the net amount.
  - Paying the bill in full leaves it marked Paid.
  - A challan clears the section ledger and links its deductions.
  - 26Q totals match the deductions.
  - A customer receipt with TDS debits 1640 and settles the invoice in full.
  - Tenant isolation: each tenant sees only its own sections and deductions.
- **Regression:** rerun `PurchaseAccountingAutoPostingTest`, `VendorBillAdvanceAllocationTest`, `BillWiseSettlementTest`, `SystemAccountKeysTest` and `AccountingDashboardTest`. Compare against the known failing baseline.
- **Manual check in the browser:** create a TDS vendor, book a bill, pay it, deposit a challan, export 26Q and download Form 16A.