Where each item stands

Accounting sheet

Item	Status in code	Size
A/c Dashboard	Missing. No accounting dashboard exists, though all the data behind it does.	Medium
A/c Reporting	Mostly built. 15 reports exist (Trial Balance, P&L, Balance Sheet, Cash Flow, GL, Party Ledger, Day Book, AR/AP Aging, GST Summary, GSTR-1, GSTR-3B, Budget vs Actual, Audit Trail). Only Balance Sheet has a PDF export; nothing exports to Excel.	Small–Medium
Tenant logo / module / platform owner	Partly built. The logo upload and display work, and a tenant has an owner. The plan decides which modules a tenant gets. Unclear what's still missing.	?
Third-party integration (2-way)	Not started. No integration code exists.	Large
Tally / Busy / Marg	Not started. Each depends on the integration framework above.	Large each
Voucher-wise by staff, bank reconciliation	Mostly built. Bank statements import from Excel/CSV, with auto-match. Vouchers record who posted them, but you can't filter or report by staff member.	Small
Asset depreciation / head recheck	Built. Depreciation, disposal, revaluation and write-off exist. This needs a review of which accounts depreciation posts to.	Small

Platform Setting sheet

Item	Status in code	Size
Side menu dynamic rendering	The sidebar is a 690-line hard-coded list in one view. It's already filtered by plan and role, but every menu change means editing that file.	Medium
Module-wise menu / UI adjustment	Depends on the dynamic menu item above.	Medium
Top header corrections	Found one stale leftover about the deleted currency switcher. I need your list of what's wrong.	?
RBAC improvements	Known gaps: the team scope behaves like own, Production still uses the old permission list, and no seeder creates a platform admin.	Medium
Multi-currency setup	About half built. Tenant currency, exchange rates and foreign-currency journals exist. Missing: foreign-currency invoices, bills and payments, plus FX gain/loss.	Large
Landing page / website	Not started. / goes to the logged-in dashboard.	Large