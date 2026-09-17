Where each item stands

Accounting sheet

Item	Status in code	Size
A/c Dashboard	Done. Accounting Dashboard is built (AccountingDashboardController/Service, filters, consolidated view, caching) and merged (PR #258).	Medium
A/c Reporting	Done. All 15 reports (Trial Balance, P&L, Balance Sheet, Cash Flow, GL, Party Ledger, Day Book, AR/AP Aging, GST Summary, GSTR-1, GSTR-3B, Budget vs Actual, Audit Trail) export to both PDF and Excel via one shared route (accounting.reports.export/{report}/{format}).	Small–Medium
Tenant logo / module / platform owner	Partly built. The logo upload and display work, and a tenant has an owner. The plan decides which modules a tenant gets. Unclear what's still missing.	?

Voucher-wise by staff, bank reconciliation	Mostly built. Bank statements import from Excel/CSV, with auto-match. Vouchers record who posted them, but you can't filter or report by staff member.	Small
Asset depreciation / head recheck	Built. Depreciation, disposal, revaluation and write-off exist. This needs a review of which accounts depreciation posts to.	Small

Platform Setting sheet
Item	Status in code	Size
Side menu dynamic rendering	Done. Sidebar is now built from each module's own Routes/menu.php file plus a shared MenuBuilder — adding or changing a menu item no longer means editing one big view.	Medium
Module-wise menu / UI adjustment	Done. Every module's menu entries (Purchase, HRMS, Accounting, CRM, Inventory, Production, Projects, Sales, Access, Platform) are now gated by the same permission their screen actually enforces, so a role only sees the modules/screens it has rights to. A guardrail test fails the build if a new menu entry ships without a permission check.	Medium
Top header corrections	Found one stale leftover about the deleted currency switcher. I need your list of what's wrong.	?
RBAC improvements	Done: team scope now behaves like department instead of own, Production's old hard-coded permission list is fully removed (real DB-seeded permissions only), and a platform admin seeder exists. Remaining known gap: some screens (mostly in Production and a handful in CRM/Inventory) have no server-side permission check at all yet — their sidebar links are deliberately left open to match that, tracked in MenuPermissionCoverageTest's allow-list.	Medium
Multi-currency setup	About half built. Tenant currency, exchange rates and foreign-currency journals exist. Missing: foreign-currency invoices, bills and payments, plus FX gain/loss.	Large
Third-party integration (2-way)	Not started. No integration code exists.	Large
Tally / Busy / Marg	Not started. Each depends on the integration framework above.	Large each  