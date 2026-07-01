For a large-scale SaaS ERP like this, you should use **RBAC + scoped permissions**:

**Core User Levels**

1. **Platform Super Admin**
   Full control across all tenants: tenant creation, subscriptions, plans, global settings, billing, system health, backups, impersonation, audit access.

2. **Tenant Owner / Company Admin**
   Full control inside one tenant/company only: users, roles, company settings, modules, branches, approvals, reports.

3. **Module Admin**
   Manages a specific module such as CRM, Sales, Production, Inventory, HR, Finance, or Purchase.

4. **Manager / Approver**
   Can approve, reject, assign, escalate, and view team/department records.

5. **Standard User**
   Can create and manage own operational records based on assigned module rights.

6. **Read-Only / Auditor**
   Can view records, reports, and audit logs but cannot modify business data.

7. **External User**
   Limited portal access for customers, vendors, contractors, or employees.

**Permission Actions**

Use granular actions per module:

```text
view
view_all
create
edit
delete
restore
approve
reject
assign
export
import
print
email
manage_settings
manage_workflow
```

**Required ERP Permission Areas**

For your current app/modules, I’d structure rights like this:

```text
platform.tenants.*
platform.subscriptions.*
platform.usage_limits.*

crm.leads.*
crm.customers.*
crm.quotations.*

sales.orders.*
sales.invoices.*
sales.receipts.*

inventory.items.*
inventory.warehouses.*
inventory.stock_moves.*
inventory.adjustments.*

purchase.suppliers.*
purchase.requests.*
purchase.orders.*
purchase.bills.*

production.boms.*
production.work_centers.*
production.machines.*
production.routing.*
production.work_orders.*
production.quality.*

hr.employees.*
hr.attendance.*
hr.leave.*
hr.payroll.*
hr.organization_settings.*

finance.chart_of_accounts.*
finance.journals.*
finance.ledgers.*
finance.tax_reports.*

projects.projects.*
projects.tasks.*
projects.timesheets.*

reports.sales.*
reports.inventory.*
reports.finance.*
reports.payroll.*

automation.workflows.*
automation.webhooks.*
settings.localization.*
settings.currencies.*
audit.logs.view
access.roles.*
access.permissions.*
access.teams.*
```

**Important Scopes**

Do not rely only on role names. Add data scope:

```text
own records only
team records
department records
branch records
company/tenant records
all tenants/platform records
```

Example: a sales user may have `crm.leads.view`, but only for `own` leads. A sales manager may have `crm.leads.view_all` for their branch.

**Recommended Default Roles**

```text
Super Admin
Tenant Owner
Company Admin
Branch Manager
Department Manager
Sales Manager
Sales Executive
CRM User
Production Manager
Production Planner
Machine Operator
Inventory Manager
Storekeeper
Purchase Manager
Purchase Executive
Finance Manager
Accountant
HR Manager
Payroll Officer
Project Manager
Auditor
Read Only User
Customer Portal User
Vendor Portal User
```

For Laravel, the practical choice is usually **Spatie Laravel Permission**, with custom columns or policies for `tenant_id`, `branch_id`, `department_id`, and ownership checks. That gives you clean roles/permissions while keeping SaaS tenant isolation strong.