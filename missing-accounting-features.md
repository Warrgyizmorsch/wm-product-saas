Still missing, roughly in order of how much a Tally user would miss it

~~Bill-wise settlement.~~ DONE (Phase 1 + Phase 2, 2026-10-24). Every bill-wise ledger (AR, AP, Creditors for Expenses, advance ledgers, Salary Payable) tracks bills per party: customers, suppliers, employees and transporters. Vouchers and journals pick bills (Agst Ref / New Ref / Advance / On Account). Bill Settlement adjusts On Account money and advances against bills. Opening balances split into old bills. Payroll, payouts, cash advances and expense claims settle per employee. Ageing and Bills Outstanding show On Account and Advance as separate columns. See "# Bill-wise settlement — Phase 2.md".
TDS module. It needs sections (194C/J/I/H/Q), thresholds, automatic deduction on vendor bills, challan tracking, 26Q/24Q return data, and Form 16/16A. Today TDS is only a ledger plus a payroll deduction.
Payroll statutory returns. No PF ECR file, no ESI return, and no Form 16 for employees. The salary entries are correct now, but filing still happens outside the ERP.
Multi-currency. Exchange rates exist, but foreign-currency vouchers and parties, realised/unrealised forex gain or loss, and month-end revaluation were rolled back earlier (commit ea7d1378). That matters for any export or import client.
Tally data import. Customers moving from Tally need to bring in masters and opening balances, and ideally history. There’s no import path today, and it’s often the deciding factor in a sale.
Everyday Tally conveniences. Recurring vouchers, post-dated cheques with a PDC register, cheque printing, interest on overdue party balances, and enforcing credit limits at invoice time.
Statutory-format statements. Schedule III layout for the Balance Sheet and P&L, plus a ratio-analysis report. Ratios exist only as dashboard tiles.
Two checks needed before claiming parity:
Edit log: confirm the audit trail meets the Companies (Accounts) Rules edit-log requirement. Every change to a voucher or master must be logged and impossible to switch off.
Closing stock: confirm it flows into the Balance Sheet and P&L at FIFO or weighted average, the way Tally does automatically.