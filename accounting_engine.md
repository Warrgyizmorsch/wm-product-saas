# Role

You are a Senior Software Architect, Laravel SaaS ERP Expert, Accounting System Architect, and FinTech Backend Engineer with 15+ years of experience.

Your task is to design and develop a **production-ready Accounting & Finance Module** for my existing **Custom Multi-Tenant SaaS ERP**.

The accounting module should be inspired by modern ERP accounting systems such as Tally, Zoho Books, Odoo, and SAP concepts, but **do not copy any proprietary implementation**.

Build a modern, modular, scalable, configurable accounting system.

---

# Primary Objective

Develop a complete **Double-Entry Accounting System** that integrates with other ERP modules including:

* Sales
* Purchase
* Inventory
* CRM
* Projects
* HRMS
* Payroll
* Production
* Banking
* Tax/GST
* Reporting

The system must support automatic accounting postings from ERP modules through a centralized posting engine.

---

# Critical Architecture Rule

## Modules Must Not Directly Create Accounting Entries

Do NOT allow modules such as Sales, Purchase, Inventory, Payroll, or HRMS to directly insert records into:

* journal_entries
* journal_entry_lines
* vouchers
* voucher_entries

Instead, use the following architecture:

```text
ERP Module
    ↓
Domain Event
    ↓
Accounting Event / Posting Request
    ↓
Posting Rules Engine
    ↓
Validation
    ↓
Database Transaction
    ↓
Journal Entry
    ↓
Journal Entry Lines
    ↓
Financial Reports
```

Example:

```text
SalesInvoicePosted
        ↓
AccountingPostingService
        ↓
Posting Rule Resolver
        ↓
Accounts Receivable   → Debit
Sales Revenue         → Credit
Output GST            → Credit
        ↓
Create Balanced Journal Entry
```

---

# Core Accounting Principle

Every accounting transaction must follow:

```text
Total Debit = Total Credit
```

A journal entry must never be posted unless it is balanced.

The system must validate this before posting.

Use database transactions for all accounting posting operations.

If any step fails:

```text
ROLLBACK ALL TRANSACTION DATA
```

Never allow partial accounting entries.

---

# Existing ERP Requirements

Assume this is an existing Laravel-based SaaS ERP.

The architecture must support:

* Multi-Tenant SaaS
* Tenant Isolation
* Multi-Company
* Multi-Branch
* RBAC
* Modular Architecture
* Laravel Best Practices
* Clean Architecture principles
* Domain-Driven Design where useful
* Service Layer
* Repository Pattern only where genuinely beneficial
* Events and Listeners
* Queues for non-critical background processing
* Database Transactions
* Audit Logs
* API-ready architecture
* Scalable reporting

Do not unnecessarily over-engineer the application.

First inspect the existing project architecture, models, database structure, authentication, tenancy implementation, and coding conventions before making changes.

Follow the existing project conventions wherever possible.

---

# PHASE 1 — ACCOUNTING FOUNDATION

Develop the foundation first.

## 1. Company Accounting Setup

Implement:

* Company Accounting Configuration
* Base Currency
* Financial Year
* Accounting Start Date
* Accounting Method
* Decimal Precision
* Default Cash Account
* Default Bank Account
* Retained Earnings Account
* Suspense Account

Settings must be tenant/company-specific.

---

## 2. Financial Year Management

Create:

### financial_years

Fields:

```text
id
tenant_id
company_id
name
start_date
end_date
status
is_current
closed_at
closed_by
created_at
updated_at
```

Features:

* Create Financial Year
* Set Current Financial Year
* Prevent overlapping financial years
* Open / Closed status
* Lock transactions in closed periods
* Year-end closing support
* Permission-controlled reopening

Validation:

```text
Financial year dates must not overlap for the same company.
```

---

## 3. Accounting Periods

Create accounting periods inside a financial year.

Support:

* Monthly Periods
* Custom Periods if required
* Open
* Locked
* Closed

Rules:

* Transactions cannot be posted to locked periods.
* Transactions cannot be posted to closed periods.
* Only authorized users can reopen a period.
* Reopening must create an audit log.

---

# PHASE 2 — CHART OF ACCOUNTS

Create a configurable Chart of Accounts.

## Account Types

Support:

### Assets

* Current Asset
* Fixed Asset
* Bank
* Cash
* Accounts Receivable
* Inventory
* Tax Receivable

### Liabilities

* Current Liability
* Long-Term Liability
* Accounts Payable
* Tax Payable

### Equity

* Capital
* Retained Earnings

### Income

* Sales Revenue
* Other Income

### Expenses

* Direct Expense
* Operating Expense
* COGS
* Payroll Expense
* Tax Expense

The architecture should allow future custom account types without major code changes.

---

## Account Groups

Create hierarchical account groups.

Example:

```text
Assets
├── Current Assets
│   ├── Cash
│   ├── Bank
│   └── Accounts Receivable
│
├── Fixed Assets
│   ├── Furniture
│   └── Computers
```

Fields:

```text
id
tenant_id
company_id
parent_id
name
code
account_type
normal_balance
is_active
created_at
updated_at
```

Prevent circular parent-child relationships.

---

## Chart of Accounts

Suggested table:

```text
chart_of_accounts
```

Fields:

```text
id
tenant_id
company_id
account_group_id
code
name
description
account_type
normal_balance
is_system_account
is_active
allow_manual_posting
created_at
updated_at
```

Requirements:

* Unique account code per company.
* Support system accounts.
* Prevent deletion if transactions exist.
* Prefer soft deactivation instead of deletion.
* System accounts should require special permissions for modification.

---

# PHASE 3 — JOURNAL & DOUBLE-ENTRY ENGINE

This is the most critical part of the module.

## journal_entries

Fields:

```text
id
tenant_id
company_id
financial_year_id
accounting_period_id
entry_number
entry_date
reference_number
source_module
source_type
source_id
voucher_type_id
description
status
posted_at
posted_by
reversed_entry_id
created_by
created_at
updated_at
```

Statuses:

```text
draft
pending_approval
posted
reversed
cancelled
```

---

## journal_entry_lines

Fields:

```text
id
journal_entry_id
chart_of_account_id
description
debit
credit
cost_center_id
project_id
branch_id
currency_id
exchange_rate
foreign_debit
foreign_credit
reference_type
reference_id
created_at
updated_at
```

---

## Journal Rules

Implement these rules strictly:

1. Every posted journal entry must have at least 2 lines.
2. Total debit must equal total credit.
3. Debit and credit cannot both contain a value on the same line.
4. Negative debit/credit values are not allowed.
5. Posted journal entries cannot be edited.
6. Posted journal entries cannot be deleted.
7. Corrections must be made using reversal entries.
8. Cancelled transactions must not silently delete accounting history.
9. Every posting operation must run inside a database transaction.
10. All accounting changes must be audit logged.

Use money values with appropriate fixed decimal precision. Do not use floating-point values for accounting calculations.

---

# PHASE 4 — GENERIC VOUCHER ENGINE

Create configurable voucher types.

Default voucher types:

* Contra
* Payment
* Receipt
* Journal
* Sales
* Purchase
* Credit Note
* Debit Note

Create:

```text
voucher_types
```

Fields:

```text
id
tenant_id
company_id
code
name
category
prefix
next_number
numbering_method
requires_approval
is_active
created_at
updated_at
```

Features:

* Automatic Numbering
* Manual Numbering
* Financial-Year-wise Numbering
* Prefix Configuration
* Duplicate Number Prevention
* Custom Voucher Types
* Approval Requirement

The voucher engine should generate journal entries through the centralized posting service.

Do not duplicate accounting logic for each voucher type.

---

# PHASE 5 — ACCOUNTING POSTING ENGINE

Create a centralized service.

Suggested structure:

```text
Modules/Finance
├── Domain
│   ├── Events
│   ├── Actions
│   ├── Services
│   └── ValueObjects
│
├── Application
│   ├── Commands
│   ├── DTOs
│   └── Handlers
│
├── Infrastructure
│   ├── Persistence
│   └── Services
│
├── Http
│   ├── Controllers
│   ├── Requests
│   └── Resources
│
└── Models
```

Adapt this structure to the existing ERP architecture instead of forcing a new structure unnecessarily.

Create a central service conceptually similar to:

```text
AccountingPostingService
```

Responsibilities:

* Receive posting request
* Resolve tenant/company context
* Validate accounting period
* Resolve posting rules
* Build journal entry
* Validate debit/credit balance
* Save journal entry
* Save journal lines
* Mark as posted
* Generate audit log
* Return posting result

The service should never depend directly on Sales or Purchase business logic.

---

# PHASE 6 — POSTING RULES ENGINE

Build configurable posting rules.

Example:

```text
Event: Sales Invoice Posted

Rule:
Customer / Accounts Receivable → Debit
Sales Revenue                  → Credit
Output GST                     → Credit
```

Purchase:

```text
Expense or Inventory Account → Debit
Input GST                    → Debit
Vendor / Accounts Payable    → Credit
```

Payment:

```text
Vendor / Expense Account → Debit
Bank Account             → Credit
```

Receipt:

```text
Bank Account             → Debit
Customer Account         → Credit
```

Create a flexible structure for posting rules rather than hard-coding all account mappings.

Suggested concept:

```text
posting_rules
posting_rule_lines
```

Possible fields:

```text
module
event
transaction_type
debit_account_mapping
credit_account_mapping
conditions
priority
is_active
```

Use JSON only where dynamic configuration is genuinely required. Keep critical accounting relationships relational and queryable.

---

# PHASE 7 — SALES INTEGRATION

Integrate with the existing Sales module.

Flow:

```text
Quotation
↓
Sales Order
↓
Delivery
↓
Sales Invoice
↓
Invoice Posted Event
↓
Accounting Posting Engine
↓
Journal Entry
```

On Sales Invoice posting:

```text
Accounts Receivable    Dr
    To Sales Revenue
    To Output GST
```

Requirements:

* Avoid duplicate posting.
* Store source module and source transaction ID.
* Each source transaction should be posted only once unless reversed.
* Use idempotency protection.
* Invoice cancellation should create a reversal or appropriate accounting correction.
* Do not delete the original journal entry.

---

# PHASE 8 — PURCHASE INTEGRATION

Flow:

```text
Purchase Requisition
↓
RFQ
↓
Purchase Order
↓
GRN
↓
Purchase Bill
↓
Bill Posted Event
↓
Accounting Posting Engine
```

Accounting:

```text
Expense / Inventory     Dr
Input GST               Dr
    To Accounts Payable
```

Support:

* Partial Payments
* Advance Payments
* Vendor Outstanding
* Debit Notes
* Purchase Returns

---

# PHASE 9 — ACCOUNTS RECEIVABLE & PAYABLE

## Accounts Receivable

Features:

* Customer Outstanding
* Invoice-wise Outstanding
* Partial Payment
* Advance Receipt
* Credit Notes
* Aging Report
* Payment Allocation

Aging buckets:

```text
0-30 Days
31-60 Days
61-90 Days
90+ Days
```

## Accounts Payable

Features:

* Vendor Outstanding
* Bill-wise Outstanding
* Partial Payment
* Advance Payment
* Debit Notes
* Aging Report
* Payment Allocation

Design bill allocation separately from the journal engine so that payment allocation can support partial and multiple settlements.

---

# PHASE 10 — INVENTORY ACCOUNTING

Integrate Inventory with Finance.

Support:

* Inventory Asset Account
* COGS Account
* Inventory Adjustment Account
* Stock Transfer
* Stock Adjustment
* Opening Stock

Sales accounting flow:

```text
Sales Invoice
↓
Revenue Entry

Inventory Movement
↓
COGS Entry
```

Example:

```text
COGS                 Dr
    To Inventory
```

Support valuation methods:

* FIFO
* Weighted Average

Keep inventory valuation logic inside the Inventory domain. Finance should receive the calculated accounting value through a controlled integration/event.

---

# PHASE 11 — GST & TAX ENGINE

Build the tax engine as a configurable module.

Support:

* GSTIN
* HSN
* SAC
* CGST
* SGST
* IGST
* Input GST
* Output GST
* Tax Exempt Transactions
* Tax Inclusive Pricing
* Tax Exclusive Pricing
* Multiple Tax Rates

Do not hard-code tax percentages.

Create configurable tax masters.

Integrate tax calculations with:

* Sales
* Purchase
* Accounting

Reports:

* GST Sales Register
* GST Purchase Register
* Input Tax Summary
* Output Tax Summary
* Tax Liability Summary

Keep statutory e-invoicing, e-way bill, and government API integrations as separate adapters so the core accounting engine remains independent.

---

# PHASE 12 — BANKING

Implement:

* Bank Accounts
* Cash Accounts
* Bank Transactions
* Payment Vouchers
* Receipt Vouchers
* Bank Reconciliation

Bank reconciliation flow:

```text
Imported Bank Transaction
        ↓
Matching Engine
        ↓
Suggested ERP Transaction
        ↓
Manual Confirmation / Auto Match
        ↓
Reconciled
```

Support CSV/Excel import initially.

Design future integration points for bank APIs.

---

# PHASE 13 — COST CENTERS, PROJECTS & BRANCHES

Support optional accounting dimensions:

* Cost Center
* Project
* Branch
* Department

A journal line may optionally contain these dimensions.

Do not force every transaction to use them.

Reports must support filtering by:

* Company
* Branch
* Cost Center
* Project
* Date
* Financial Year

---

# PHASE 14 — FINANCIAL REPORTING

Develop reports from posted journal entries only.

Never calculate official financial reports from drafts.

## Required Reports

### Core

* Trial Balance
* General Ledger
* Ledger Statement
* Day Book
* Journal Register

### Cash & Banking

* Cash Book
* Bank Book
* Bank Reconciliation Report

### Financial Statements

* Profit & Loss
* Balance Sheet
* Cash Flow

### Receivables

* Customer Outstanding
* Customer Aging

### Payables

* Vendor Outstanding
* Vendor Aging

### Tax

* GST Sales Register
* GST Purchase Register
* Input Tax Report
* Output Tax Report

Reports should support:

* Date Range
* Financial Year
* Company
* Branch
* Cost Center
* Project
* Export-ready architecture

For large datasets, avoid loading all transactions into PHP memory.

Use efficient database aggregation and pagination where appropriate.

---

# PHASE 15 — ADVANCED FEATURES

Implement after the core accounting system is stable.

## Costing

* Cost Centers
* Cost Categories
* Project Costing
* Department-wise Costing

## Fixed Assets

* Asset Categories
* Asset Register
* Asset Acquisition
* Depreciation
* Asset Disposal
* Automatic Depreciation Journal

## Budgeting

* Account-wise Budget
* Department Budget
* Project Budget
* Actual vs Budget
* Budget Alerts

## Multi-Currency

* Base Currency
* Transaction Currency
* Exchange Rates
* Currency Revaluation
* Foreign Exchange Gain/Loss

## Recurring Transactions

* Recurring Journal
* Recurring Expenses
* Scheduled Posting

---

# SECURITY & MULTI-TENANCY

Every Finance query must respect:

```text
tenant_id
company_id
```

Requirements:

* Never trust tenant/company ID directly from the frontend without validation.
* Resolve tenant context using the existing SaaS architecture.
* Prevent cross-tenant data access.
* Prevent cross-company data access unless explicitly authorized.
* Apply authorization policies.
* Use RBAC.

Suggested permissions:

```text
finance.view
finance.manage_accounts
finance.create_voucher
finance.edit_draft_voucher
finance.post_voucher
finance.approve_voucher
finance.reverse_voucher
finance.view_reports
finance.manage_financial_year
finance.close_financial_period
finance.reopen_financial_period
```

---

# AUDIT LOG REQUIREMENTS

Audit the following:

* Account Creation
* Account Modification
* Account Deactivation
* Voucher Creation
* Voucher Posting
* Voucher Approval
* Journal Posting
* Journal Reversal
* Financial Period Locking
* Financial Period Reopening
* Year Closing

Store:

```text
tenant_id
company_id
user_id
action
entity_type
entity_id
old_values
new_values
ip_address if supported by existing system
user_agent if supported by existing system
created_at
```

Do not expose sensitive audit data unnecessarily.

---

# PERFORMANCE REQUIREMENTS

The ERP should be designed to scale.

Requirements:

* Add indexes for tenant/company/date/source transaction queries.
* Use database transactions for posting.
* Prevent N+1 queries.
* Paginate large lists.
* Use queues only for non-critical tasks.
* Financial posting must remain consistent and reliable.
* Heavy report exports may be queued.
* Consider summary tables/materialized approaches only after profiling shows they are needed.
* Do not prematurely optimize.

---

# TESTING REQUIREMENTS

Write automated tests for all critical accounting behavior.

## Mandatory Tests

### Double Entry

Test:

```text
Debit = Credit → Success
Debit ≠ Credit → Fail
```

### Journal Protection

Test:

* Posted journal cannot be edited.
* Posted journal cannot be deleted.
* Reversal creates a balanced opposite entry.

### Financial Period

Test:

* Posting in open period → Allowed.
* Posting in locked period → Rejected.
* Posting in closed period → Rejected.

### Multi-Tenant

Test:

* Tenant A cannot access Tenant B accounting data.
* Company A data cannot be accessed by unauthorized Company B users.

### Idempotency

Test:

```text
Same Sales Invoice Posted Twice
→ Only One Accounting Journal Created
```

### Integration

Test:

* Sales Invoice creates correct journal.
* Purchase Bill creates correct journal.
* Payment creates correct journal.
* Receipt creates correct journal.
* Inventory movement creates COGS entry where applicable.
* GST accounts receive correct entries.

---

# DEVELOPMENT PROCESS

Follow this process strictly.

## Step 1 — Inspect First

Before writing code:

1. Inspect the existing Laravel project.
2. Identify Laravel version.
3. Identify PHP version.
4. Inspect database.
5. Inspect tenancy architecture.
6. Inspect authentication.
7. Inspect RBAC.
8. Inspect existing module architecture.
9. Inspect Sales, Purchase, and Inventory structures.
10. Identify existing coding conventions.

Do not rewrite working infrastructure unnecessarily.

---

## Step 2 — Present Implementation Plan

Before making major changes, provide:

* Existing architecture summary
* Proposed Finance module architecture
* Database tables to add/change
* Models
* Migrations
* Relationships
* Events
* Services
* Controllers/API endpoints
* Risks or conflicts with the existing codebase

If clarification is required because of a real architectural conflict, explain the exact issue.

Otherwise proceed with the best practical solution.

---

## Step 3 — Develop in Small Phases

Do not attempt to build the entire module in one uncontrolled change.

Develop in this order:

### Sprint 1

* Finance Module Setup
* Financial Years
* Accounting Periods
* Chart of Accounts
* Account Groups

### Sprint 2

* Journal Engine
* Journal Lines
* Double Entry Validation
* Journal Posting
* Reversal
* Audit Logs

### Sprint 3

* Voucher Engine
* Voucher Types
* Numbering
* Payment
* Receipt
* Contra
* Journal Vouchers

### Sprint 4

* Trial Balance
* Ledger
* Day Book
* P&L
* Balance Sheet

### Sprint 5

* Posting Rules Engine
* Sales Integration
* Purchase Integration
* Idempotency Protection

### Sprint 6

* Accounts Receivable
* Accounts Payable
* Aging
* Payment Allocation

### Sprint 7

* Inventory Accounting
* COGS
* Stock Valuation Integration

### Sprint 8

* GST Engine
* GST Reports
* Tax Posting

### Sprint 9

* Banking
* Bank Reconciliation

### Sprint 10

* Advanced Accounting Features

After completing each sprint:

1. Run tests.
2. Fix failures.
3. Review database migrations.
4. Review tenant isolation.
5. Review authorization.
6. Check for duplicate posting risks.
7. Provide a concise implementation summary.
8. Ask or proceed to the next sprint based on my instruction.

---

# CODE QUALITY RULES

* Write production-quality code.
* Follow existing project conventions.
* Use meaningful names.
* Keep controllers thin.
* Put business logic in appropriate services/actions.
* Use Form Requests for validation where applicable.
* Use Policies/Gates for authorization.
* Use DTOs where they improve clarity.
* Avoid duplicate code.
* Avoid giant service classes.
* Avoid unnecessary repositories.
* Avoid premature microservices.
* Keep the ERP as a modular monolith.
* Use domain events for module integration.
* Ensure database transactions around financial posting.
* Never use float/double for money calculations.
* Add PHPDoc only where it provides meaningful value.
* Keep code readable and maintainable.

---

# IMPORTANT ACCOUNTING IMMUTABILITY RULE

Once a journal entry is:

```text
POSTED
```

It is immutable.

Never:

```text
UPDATE posted accounting values
DELETE posted accounting entries
```

Instead:

```text
Original Journal Entry
        ↓
Reversal Journal Entry
        ↓
New Correct Transaction if required
```

Maintain complete accounting history.

---

# FINAL EXPECTATION

Act as the lead architect and senior developer for this module.

Do not provide only theoretical suggestions.

When I instruct you to begin development:

1. Inspect the current codebase.
2. Analyze existing architecture.
3. Identify the safest integration approach.
4. Create the implementation plan.
5. Implement the requested sprint.
6. Run and fix relevant tests.
7. Report exactly what was changed.
8. Do not break existing modules.
9. Do not remove existing functionality unless explicitly instructed.
10. Always prioritize accounting correctness, data integrity, tenant isolation, and maintainability over speed of implementation.

Start with:

**"Analyze the existing project architecture and prepare the Finance & Accounting Module integration plan. Do not start major implementation until you have completed the architecture analysis."**
