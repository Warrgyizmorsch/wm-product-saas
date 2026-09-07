# FIXED ASSET MANAGEMENT — ERP DOMAIN RULES

## 1. MODULE POSITION

Fixed Asset Management is a dedicated ERP domain integrated with existing modules.

Primary ownership:

Accounting → Fixed Assets

Integrations:

Fixed Assets ↔ Purchase
Fixed Assets ↔ Inventory
Fixed Assets ↔ HRMS
Fixed Assets ↔ Production
Fixed Assets ↔ Accounting
Fixed Assets ↔ Documents
Fixed Assets ↔ Notifications
Fixed Assets ↔ Audit

Do NOT create duplicate Employee, Vendor, Item, Department, Branch, Location, Account, Tax, User or Document masters.

Reuse existing ERP masters and services.

---

# 2. IMPORTANT ARCHITECTURE RULE

Do NOT treat Fixed Assets as Inventory.

Inventory represents goods/materials controlled for:

* purchase
* stock
* consumption
* manufacturing
* sale
* transfer
* adjustment

Fixed Assets represent long-term business assets controlled for:

* capitalization
* assignment
* location
* depreciation
* maintenance
* transfer
* revaluation
* disposal
* write-off

An inventory item may become a fixed asset through an explicit capitalization workflow.

---

# 3. CROSS-MODULE INTEGRATION RULE

Cross-module integration IS allowed and required.

However:

Do NOT create tightly coupled business logic between modules.

Preferred pattern:

Module A
→ Application Service / Domain Service
→ Event / Integration Contract
→ Module B

Avoid:

Controller in Module A
→ direct manipulation of Module B's internal tables.

Use existing services, actions, events, contracts or domain interfaces whenever available.

Direct Eloquent relationships are acceptable for read/reference purposes when consistent with the existing architecture.

Financial posting must always use the central Accounting services.

---

# 4. FIXED ASSET DOMAIN STRUCTURE

Follow the application's existing module structure.

Preferred conceptual structure:

app/Domains/FixedAssets/

```
Models/
    Asset.php
    AssetCategory.php
    AssetAssignment.php
    AssetTransfer.php
    AssetMaintenance.php
    AssetDepreciation.php
    AssetDepreciationSchedule.php
    AssetCapitalization.php
    AssetDisposal.php
    AssetWriteOff.php
    AssetRevaluation.php
    AssetActivity.php

Services/
    AssetService.php
    AssetCapitalizationService.php
    AssetDepreciationService.php
    AssetTransferService.php
    AssetDisposalService.php
    AssetWriteOffService.php
    AssetRevaluationService.php

Actions/
    CreateAsset.php
    CapitalizeAsset.php
    AssignAsset.php
    TransferAsset.php
    PostDepreciation.php
    DisposeAsset.php
    WriteOffAsset.php

Events/
    AssetCreated.php
    AssetCapitalized.php
    AssetAssigned.php
    AssetTransferred.php
    DepreciationPosted.php
    AssetDisposed.php
    AssetWrittenOff.php

Policies/
    AssetPolicy.php

DTOs/
    AssetData.php
    DepreciationData.php

Enums/
    AssetStatus.php
    DepreciationMethod.php
    DisposalType.php
```

Use the project's actual architecture if different.

Do not force this structure onto the existing application.

---

# 5. ASSET MASTER

Asset must contain or reference:

Identity:

* tenant_id
* asset_code
* asset_name
* category_id
* description

Item:

* item_id nullable
* serial_number
* model_number
* manufacturer

Purchase:

* vendor_id nullable
* purchase_invoice_id nullable
* purchase_order_id nullable
* grn_id nullable
* invoice_number
* purchase_date
* capitalization_date
* commissioning_date

Financial:

* acquisition_cost
* directly_attributable_cost
* capitalization_cost
* recoverable_tax
* non_recoverable_tax
* residual_value
* useful_life_months
* depreciation_method
* depreciation_start_date
* accumulated_depreciation
* book_value

Organization:

* branch_id
* department_id
* location_id
* employee_id nullable
* user_id nullable

Operational:

* status
* condition
* warranty_start_date
* warranty_end_date
* insurance_start_date
* insurance_end_date

Tracking:

* barcode
* qr_code
* notes

Audit:

* created_by
* updated_by

Use existing BaseModel and tenant scope.

---

# 6. ASSET CATEGORY

Asset categories define default accounting behavior.

Example:

Computer Equipment

Asset Account
→ Computer Equipment

Accumulated Depreciation Account
→ Accumulated Depreciation - Computer

Depreciation Expense
→ Depreciation Expense - Computer

Gain on Disposal
→ Gain on Asset Disposal

Loss on Disposal
→ Loss on Asset Disposal

Other configuration:

* depreciation method
* useful life
* residual value
* capitalization threshold
* status

Never hard-code account IDs.

Use the existing Chart of Accounts.

---

# 7. ACCOUNTING INTEGRATION

Fixed Assets MUST use the existing Accounting engine.

Never create a second journal/GL system.

Required flow:

Asset Transaction
→ Accounting Service
→ Journal Entry
→ Journal Lines
→ General Ledger
→ Financial Reports

Accounting events must be atomic.

Use database transactions.

Example:

Asset Capitalization:

Dr Fixed Asset Account
Dr Input GST where recoverable
Cr Vendor / Payable / Bank

Depreciation:

Dr Depreciation Expense
Cr Accumulated Depreciation

Disposal:

Dr Bank / Customer
Dr Accumulated Depreciation
Dr Loss on Disposal if applicable
Cr Fixed Asset
Cr Output Tax if applicable

Actual ledger selection MUST come from configuration.

---

# 8. GST / TAX RULE

Never assume GST is part of asset cost.

Determine whether tax is:

Recoverable
OR
Non-recoverable

Recoverable GST:

→ Input GST ledger

Non-recoverable tax:

→ may form part of asset capitalization cost according to configuration/accounting rules.

Never hard-code GST percentages.

Use the existing Tax/GST module.

---

# 9. PURCHASE INTEGRATION

Purchase Invoice may contain asset-eligible lines.

Example:

Laptop:

₹80,000
GST:

₹14,400

Purchase Invoice should allow:

"Capitalize as Fixed Asset"

After capitalization:

Asset:

₹80,000

Recoverable GST:

₹14,400

Do not automatically capitalize every expensive purchase.

Use:

* asset category
* capitalization threshold
* explicit user selection
* existing business rules

---

# 10. INVENTORY → ASSET

Inventory and Fixed Assets must remain separate concepts.

Example:

Purchase:

10 laptops

GRN:

10 units

After capitalization:

LAP-0001
LAP-0002
...
LAP-0010

Each asset may contain:

* serial number
* employee
* location
* department
* warranty
* depreciation schedule

Use existing inventory transaction services.

Do not create duplicate stock movements.

---

# 11. HRMS → ASSET

Reuse existing Employee model.

Do not create AssetEmployee.

Asset assignment:

Asset
→ Existing Employee

Maintain assignment history.

Example:

Employee Rahul:

Laptop LAP-0001
Monitor MON-0001
Mobile MOB-0001

Employee exit:

HRMS Exit
→ Asset Clearance
→ Return / Transfer / Exception

Do not dispose assets automatically when employee exits.

---

# 12. PRODUCTION → ASSET

Production machines can reference Fixed Assets.

Examples:

* CNC
* Lathe
* Generator
* Forklift
* Welding Machine

Do not duplicate machine information if Production already has a Machine/Resource model.

Preferred relationship:

Production Machine
↔ Fixed Asset

Asset can track:

* machine status
* production usage
* maintenance
* downtime
* location

---

# 13. DEPRECIATION ENGINE

Support:

* Straight Line
* Written Down Value / Declining Balance

Architecture must allow additional methods later.

Inputs:

* capitalization cost
* depreciation start date
* useful life
* residual value
* method
* fiscal year
* frequency

Default frequency:

Monthly

Never hard-code fiscal year dates.

Use company/tenant accounting configuration.

---

# 14. DEPRECIATION SCHEDULE

Before posting depreciation:

Generate Schedule
→ Review
→ Approve
→ Post

Prevent duplicate posting.

Unique business key should prevent:

same asset
+
same depreciation period
+
duplicate posting

Depreciation calculations must be deterministic and testable.

---

# 15. ACCOUNTING PERIOD CONTROL

Depreciation must respect existing accounting period rules.

If an accounting period is:

* closed
* locked
* finalized

do not allow depreciation posting into that period.

Use existing fiscal-year and period services.

---

# 16. ASSET ASSIGNMENT

Support assignment to:

* employee
* department
* branch
* location
* production area

Maintain history.

Never overwrite historical assignments.

Assignment should record:

* assigned date
* returned date
* assigned by
* returned by
* condition
* remarks

---

# 17. ASSET TRANSFER

Support:

Employee → Employee

Department → Department

Branch → Branch

Location → Location

Internal transfer should normally not affect P&L.

Do not create accounting entries unless the existing accounting configuration/business rules require them.

---

# 18. ASSET MAINTENANCE

Track:

* service
* repair
* AMC
* warranty
* maintenance vendor
* cost
* service date
* next service
* downtime
* attachments

Do not automatically capitalize maintenance.

Provide explicit:

Expense

OR

Capitalize

decision.

If capitalized:

Maintenance/Improvement
→ Asset capitalization adjustment
→ Updated depreciation basis

---

# 19. ASSET DISPOSAL

Support:

* Sale
* Scrap
* Write-off
* Lost

Before disposal calculate:

Original Cost
Accumulated Depreciation
Net Book Value
Sale Proceeds
Tax
Gain/Loss

Prevent disposal of already disposed assets.

Disposal must use an approval flow if existing ERP supports approvals.

---

# 20. ASSET WRITE-OFF

Write-off should:

1. Validate asset
2. Calculate NBV
3. Obtain approval if configured
4. Create accounting entry
5. Update asset status
6. Record audit history

Never simply delete the asset.

---

# 21. ASSET REVALUATION

Treat revaluation as a controlled accounting operation.

Do not implement casually.

If supported:

Asset
→ Revaluation
→ Approval
→ Accounting Entry
→ Updated Book Value
→ Revised Depreciation Schedule

Follow applicable company/accounting configuration.

Never modify historical acquisition cost directly.

---

# 22. ASSET STATUS

Use controlled enum/state values:

DRAFT
PENDING_CAPITALIZATION
ACTIVE
IDLE
UNDER_MAINTENANCE
FULLY_DEPRECIATED
DISPOSED
SOLD
SCRAPPED
WRITTEN_OFF
LOST

Implement valid state transitions.

---

# 23. DOCUMENTS

Reuse existing document/attachment infrastructure.

Support:

* invoice
* warranty
* insurance
* AMC
* installation certificate
* ownership documents
* asset photos
* disposal documents

Do not build another file-storage system.

---

# 24. QR / BARCODE

Assets may have:

* QR code
* barcode

Scanning should open the Asset Profile.

Permission rules must determine what information the user can view.

Do not expose accounting values to unauthorized users.

---

# 25. AUDIT LOGGING

Reuse existing audit system.

Track:

* creation
* capitalization
* assignment
* transfer
* maintenance
* depreciation
* revaluation
* disposal
* write-off
* edits

Audit history should be immutable.

---

# 26. PERMISSIONS

Follow existing permission pattern:

fixed_assets.assets.view
fixed_assets.assets.create
fixed_assets.assets.edit
fixed_assets.assets.delete

fixed_assets.assets.capitalize
fixed_assets.assets.assign
fixed_assets.assets.transfer

fixed_assets.depreciation.view
fixed_assets.depreciation.generate
fixed_assets.depreciation.post

fixed_assets.maintenance.view
fixed_assets.maintenance.create

fixed_assets.disposal.view
fixed_assets.disposal.create
fixed_assets.disposal.approve

fixed_assets.writeoff.create
fixed_assets.writeoff.approve

fixed_assets.reports.view
fixed_assets.reports.export

Apply existing scope model:

own
team
department
branch
tenant
platform

Do not bypass RBAC through frontend-only restrictions.

---

# 27. REPORTS

Required reports:

Fixed Asset Register
Asset Category Report
Asset Location Report
Employee Asset Report
Asset Assignment History
Asset Transfer History
Depreciation Schedule
Depreciation Register
Accumulated Depreciation
Net Book Value
Maintenance Report
Warranty Expiry
Disposal Report
Gain/Loss on Disposal
Capital Expenditure
Asset vs GL Reconciliation

Reports must respect:

tenant
branch
department
location
permission scope

---

# 28. ASSET vs GL RECONCILIATION

Implement reconciliation between:

Asset Register
vs
Fixed Asset GL

and:

Accumulated Depreciation Register
vs
Accumulated Depreciation GL

Show:

Asset Register Balance
GL Balance
Difference

Never hide discrepancies.

---

# 29. DASHBOARD

Fixed Asset Dashboard:

Total Asset Cost
Accumulated Depreciation
Net Book Value
Current Month Depreciation
Assets Under Maintenance
Warranty Expiring
Fully Depreciated Assets
Recently Capitalized
Recently Disposed

Charts:

Asset Value by Category
Asset Value by Location
Depreciation Trend
Asset Status

Reuse existing dashboard components.

---

# 30. MULTI-TENANCY

Every asset domain record must respect tenant isolation.

Never trust tenant_id supplied from request.

Tenant must come from the existing tenant context/current tenant mechanism.

Never permit:

Tenant A
→ Tenant B Asset

Test explicitly.

---

# 31. DATABASE DESIGN RULE

Before creating migrations:

Inspect existing database.

Look for:

* items
* products
* purchase invoices
* purchase orders
* GRN
* vendors
* employees
* departments
* branches
* locations
* accounts
* journal entries
* journal lines
* tax
* attachments
* audit logs

Reuse existing structures.

Only create missing Asset domain tables.

---

# 32. TRANSACTION INTEGRITY

Financial operations must be atomic.

Example:

CAPITALIZE ASSET

BEGIN TRANSACTION

Create capitalization record
Create/update asset
Create journal
Create journal lines
Create audit record

COMMIT

If any operation fails:

ROLLBACK ALL

Never allow partially completed financial transactions.

---

# 33. EVENTS

Use events when useful:

AssetCreated
AssetCapitalized
AssetAssigned
AssetTransferred
DepreciationGenerated
DepreciationPosted
AssetMaintained
AssetDisposed
AssetWrittenOff

Events must not replace transactional consistency.

Financial journal creation must remain inside the required transaction boundary.

---

# 34. QUEUES

Use queues for:

* large depreciation generation
* bulk imports
* large report exports
* bulk QR generation
* notifications

Do not queue simple CRUD.

Follow existing Redis/Horizon architecture.

---

# 35. API

If ERP has APIs, expose Asset APIs using existing API conventions.

Do not create APIs merely because this skill normally generates APIs.

Follow existing authentication:

Sanctum or existing API auth.

---

# 36. TESTING

Required tests:

Asset creation
Asset capitalization
Asset assignment
Asset transfer
Asset maintenance
Straight-line depreciation
WDV depreciation
Depreciation posting
Duplicate depreciation prevention
Asset disposal
Gain/loss calculation
Write-off
Accounting journal creation
Accounting period validation
RBAC
Tenant isolation
Cross-tenant access prevention

Test financial calculations independently.

---

# 37. CRITICAL DEVELOPMENT RULE

Before implementing any Asset feature:

FIRST inspect the existing implementation of the related module.

Example:

For Purchase integration:

Inspect Purchase Invoice first.

For HRMS:

Inspect Employee first.

For Inventory:

Inspect Item/Stock/GRN first.

For Accounting:

Inspect Journal/GL first.

For Production:

Inspect Machine/Resource first.

Then integrate.

Never guess existing table names, model names, service names or relationships.

---

# 38. NO DUPLICATE SERVICES

Before creating:

AssetAccountingService

check whether existing:

AccountingService
JournalEntryService
LedgerService
PostingService

already exists.

If it exists:

reuse it.

Asset-specific services should orchestrate business rules, not duplicate accounting functionality.

---

# 39. DEVELOPMENT ORDER

Always implement in this order:

1. Architecture audit
2. Database design
3. Models/enums
4. Policies/RBAC
5. Asset Category
6. Asset Master
7. Accounting integration
8. Purchase integration
9. Inventory integration
10. HRMS integration
11. Depreciation
12. Production integration
13. Maintenance
14. Transfer
15. Disposal
16. Write-off
17. Reports
18. Dashboard
19. Notifications
20. Testing
21. Documentation

Do not build UI before understanding the underlying domain workflow.

---

# 40. CLAUDE CODE BEHAVIOR

When working on Fixed Assets:

DO:

* inspect before modifying
* reuse existing services
* reuse existing models
* reuse existing components
* respect tenant scope
* respect RBAC
* use database transactions
* write tests
* preserve backward compatibility
* report files changed
* report migrations
* report accounting impact

DO NOT:

* create duplicate accounting
* create duplicate employee master
* create duplicate vendor master
* create duplicate inventory
* bypass tenant scope
* hard-code account IDs
* hard-code GST rates
* hard-code tenant IDs
* delete existing data
* modify unrelated modules
* rewrite existing architecture without reason

---

# 41. DEFINITION OF DONE

Fixed Asset Management is considered complete only when:

Purchase
→ Inventory/GRN
→ Capitalization
→ Asset
→ Assignment
→ Depreciation
→ Accounting
→ Maintenance
→ Transfer
→ Disposal

works consistently.

And:

# Asset Register

Financial Asset GL

where applicable.

Every important transaction is:

* tenant-safe
* permission-safe
* auditable
* reversible where appropriate
* tested
* traceable to accounting
* traceable to the originating transaction

The module must behave as a native part of the existing SaaS ERP rather than as a separate application.
