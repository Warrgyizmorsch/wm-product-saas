# Bill-wise settlement — Phase 2 (any party, any ledger; pick bills; settle; opening breakup)

## Context
Phase 1 (branch `feature/bill-wise-settlement`, commits 28b21efd + 0c6e7de4) built the register (`bill_references`, `bill_allocations`, `BillAllocationService`), auto-allocation from invoices/bills/receipts/payments/returns/openings, the backfill, and register-based ageing / Bills Outstanding / party-ledger refs. Gaps the user hit:
- Only **customers/vendors on 1100 AR / 2010 AP** are tracked. Employees (salary payable, advances, expense claims), transporters and other party ledgers (2015 Creditors for Expenses, 1410/1420/2200 advances) are not.
- **Accounting vouchers carry no party** (`VoucherController::store` only takes free-text `party_name`), so a payment/receipt/journal voucher can never settle a bill.
- No Tally-style **bill picker** while entering a voucher, no **settlement screen** for On Account money (e.g. OB Test Customer Debtor: Opening 75,000 vs On Account −75,000), no **opening breakup** into old bills, and advances are labelled "On Account".

User decisions: payroll gets **one Salary Payable line per employee**; bill-wise by default on the **party ledgers** (AR, AP, Creditors for Expenses, Advance from Customers, Advance to Suppliers, Advance to Employees, Salary Payable), with a per-ledger switch.

## 1. Any ledger, any party
- Migration: `chart_of_accounts.bill_wise` (bool, default false) — set true for system keys `accounts_receivable, accounts_payable, creditors_for_expenses, advance_from_customers, advance_to_suppliers, advance_to_employees, salary_payable` (add `creditors_for_expenses`/`salary_payable`/`advance_to_*` keys to `SystemAccount::TEMPLATE` if missing); provisioning (`ChartOfAccountsService` default chart) sets it the same way. COA create/edit form gets a "Track bill-by-bill" checkbox (`resources/views/modules/accounting/chart-of-accounts/index.blade.php`, `ChartOfAccountController` validation).
- `BillAllocationService::controlAccounts()` → every `bill_wise` ledger of the tenant; kind = `receivable` if debit-normal else `payable` (Bills Outstanding / ageing "Receivables" vs "Payables"); `openBills()/onAccount()` gain an optional ledger-id filter.
- Party types: add `JournalEntry::PARTY_EMPLOYEE = 'employee'`, `PARTY_TRANSPORTER = 'transporter'`. New `app/Domains/Accounting/Support/PartyDirectory.php`: `types()`, `name(type,id)`, `options(type, search)` (Customer, `Inventory\Models\Vendor`, `HRMS\Models\Employee`, `Platform\Models\Transporter` — a transporter linked to a vendor (`transporters.vendor_id`) resolves to that vendor so both views agree). Used by `BillAgeing`, `PartyLedgerController` (currently hard-limited to customer/vendor at line 34), Bills Outstanding, the voucher forms.
- `BillAllocationService::partyMatches` unchanged; posting lines may now carry any of the four types.

## 2. Employees tagged in payroll and HR expenses
- `PayrollAccountingService::postPayrollRunJournal`: Salary Payable credited **per employee** (`party_type=employee`), hint `newRef('payable', "SAL-{month}", monthEnd, monthEnd, 'payroll_salary', employeeId)`; payout `postPayrollPayoutJournal`: Dr Salary Payable per employee (held payslips excluded) with `againstRef` that employee's SAL bill. Employee loan/advance recovery lines (Cr 1420) tagged to the employee, `againstRef` On Account of their advance.
- `TravelExpenseAccountingService`: cash advance Dr 1420 tagged employee (New Ref = advance number); expense report: Cr 1420 tagged employee `againstRef` the advance; any reimbursement payable tagged employee.
- Old payroll/expense journals stay untagged (not in register); stated in release notes.

## 3. Bill picker on vouchers and journals (Tally's allocation box)
- `vouchers/_form.blade.php` and `journals/_form.blade.php`: when a line's ledger is `bill_wise`, show Party (type + searchable select via `PartyDirectory`) and an **Allocations** sub-table: Type (New Ref / Agst Ref / Advance / On Account), Bill (for Agst Ref: select of that party's open bills, loaded from a new JSON endpoint), Ref/Due date (for New Ref), Amount; running "unallocated" figure; whatever isn't allocated is On Account on save.
- New endpoint `GET accounting/bill-wise/open-bills?party_type&party_id&account_id` (controller `BillWiseController@openBills`, gated `accounting.journals.view`).
- `VoucherController::store` + `JournalController::store`: validate `items.*.party_type/party_id` (party must exist via `PartyDirectory`), `items.*.allocations.*` (type, bill_reference_id, ref_number, due_date, amount ≤ line); pass party on lines and allocations as `bill_allocations` hints with a new `line_index` key.
- Engine: `allocateJournal` supports `line_index` (hint targets the n-th line) and `bill_reference_id`; pending (maker-checker) vouchers keep their hints in `journals.bill_allocation_hints` (json, migration) and apply them on `approve()`.

## 4. Settlement screen (adjust On Account / Advance against bills, no new journal)
- Accounting → **Bill Settlement** (`BillSettlementController`, view `accounting/bill-wise/settlement.blade.php`, menu under Banking/Vouchers, permission `accounting.journals.create`): choose party + ledger → left: open bills (opposite side), right: unadjusted On Account / Advance pieces (per journal line, with date & voucher no.) → enter amounts or **Auto-match oldest first** (fills the form; user still saves) → Save.
- `BillAllocationService::settle(partyType, partyId, ledgerId, matches)`: new `bill_settlements` row (who/when/note) and, per match, two allocations on the *same journal line* with that `settlement_id`: `on_account/advance  +x` and `against_ref −x` on the bill — line totals and the ledger invariant are unchanged. `unsettle(settlementId)` deletes them. Both audit-logged via `AccountingAuditLogService`.
- Cross-ledger advances (2200 customer advance ↔ AR bill, 1410 ↔ AP bill) can't be reclassified without the ledger: the screen offers **"Apply advance"** which posts Dr 2200 / Cr AR (or Dr AP / Cr 1410) Agst the bill via a generalised `AdvanceAdjustmentService::apply(...)`.
- Opening dues: Bills Outstanding / party ledger rows link to the settlement screen pre-filtered.

## 5. Opening balance breakup into old bills
- Migration: `customers.opening_bills` and `vendors.opening_bills` (json: `[{ref, bill_date, due_date, amount}]`).
- "Split opening into bills" modal on the settlement screen (and a link from the "Opening Balance" row in Bills Outstanding): rows must sum to the party's opening balance. Saving stores the json and re-allocates the opening journal line (replace its `new_ref` allocation with one per old bill) — no journal change.
- `OpeningBalanceService::openingBillHints` uses `opening_bills` when present and its total equals the opening, so a later opening re-sync keeps the breakup; the backfill (`BuildBillRegister::openingHints`) too.

## 6. Reports polish
- Advances show as **Advance** (not "On Account") in Bills Outstanding, ageing and party ledger (`BillAgeing` keeps `on_account` and `advance` separately; views/exports get an Advance column).
- Bills Outstanding / ageing: ledger filter (any bill-wise ledger) and party-type filter; party ledger accepts all four party types.

## Reuse
`BillAllocationService` (newRef/againstRef/openBills/onAccount/billOutstanding/allocateJournal/mirrorReversal), `AdvanceAdjustmentService`, `JournalService` (post/submit/approve/postOnce), `AccountingAuditLogService`, `SystemAccountService`, `BillAgeing`, `BuildBillRegister` (extend hints), select2 searchable selects used elsewhere (`data-searchable`), `tenant_exists()` validation rule.

## Files (representative)
New: migrations (`bill_wise` flag; `bill_settlements`; `journals.bill_allocation_hints`; `opening_bills` json), `Support/PartyDirectory.php`, `Controllers/BillWiseController.php`, `Controllers/BillSettlementController.php`, views `accounting/bill-wise/settlement.blade.php` (+ opening-split modal partial), JS for the allocation box (inline in the forms, jQuery like the existing forms).
Modified: `BillAllocationService.php`, `AdvanceAdjustmentService.php`, `JournalService.php` (approve hints), `JournalEntry.php`, `ChartOfAccount.php` + COA controller/view, `ChartOfAccountsService.php`, `SystemAccount.php`, `VoucherController.php`, `JournalController.php`, `vouchers/_form.blade.php`, `journals/_form.blade.php`, `PayrollAccountingService.php`, `TravelExpenseAccountingService.php`, `OpeningBalanceService.php`, `BuildBillRegister.php`, `BillAgeing.php`, `BillsOutstandingController.php`, `PartyLedgerController.php`, ageing/bills-outstanding/party-ledger views, `ReportTables.php`, `Routes/web.php`, `Routes/menu.php`.

## Verification
1. Tests (extend `tests/Feature/Accounting/BillWiseSettlementTest.php`, new `BillSettlementTest.php`):
   - a ledger switched bill-wise (e.g. 2015) is tracked; one switched off is not.
   - payment voucher with party + Agst Ref allocation settles that bill; leftover is On Account; pending voucher applies its hints on approve.
   - settlement: On Account 75,000 vs Opening 75,000 → both cleared; invariant holds; unsettle restores; FIFO suggestion picks oldest.
   - apply advance (2200 → AR bill) posts the journal and closes the bill.
   - opening breakup: two old bills sum to the opening; ageing uses their dates; a later opening re-sync keeps them.
   - payroll: salary entry has one Salary Payable line per employee with SAL bills; payout settles them; held employee stays open. Cash advance + expense report settle per employee.
   - `assertRegisterMatchesLedger` for every bill-wise ledger.
2. Regression in batches (known failures on main: SalesOrderTest×3, PurchaseOrderTest×2, AccountingAutoPosting invoice×3, ProfileAndAccountSettings×2, ExchangeRate×1, menu×4).
3. In the app (local): run `accounting:build-bill-register`; on **Bill Settlement** match OB Test Customer Debtor's 75,000 On Account against its Opening Balance → Bills Outstanding shows 0 for them; enter a Payment Voucher to Acme picking "Agst Opening Balance 35,000" → Acme clears; split Apex's opening into two old bills; check an employee in Party Ledger after a test payroll run (use a fresh local run, not the teammate's September run).