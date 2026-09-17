# Rollout Plan — Reference

Read this when deciding what to build next, or when asked "what module should RBAC
cover now."

## Order (do not skip ahead)

```
1. Remove authorization bypasses (AppServiceProvider, HasProductionPermissions)
2. Migrations + models (roles, permissions, pivots, overrides, audit log)
3. IdentifyTenant + CheckPermission middleware
4. AccessService (scope resolution + audit hook + is_dev handling)
5. Seed Production module permissions + default roles
6. Connect Production policies → AccessService
7. Tests (policy-level + AccessService unit tests)
8. Rate limiting per tenant
9. Roll out to CRM → Inventory → HRMS → Finance, one module at a time
10. Admin UI: Access > Roles / Permissions / Assign / Overrides / Audit Log
```

If asked to add RBAC to a module that isn't Production yet, check whether Production's
rollout (steps 1–7) is actually done and stable first. If not, say so — don't silently
start a second module in parallel, that's how scope checks end up inconsistent across
modules.

## Production module — seed permissions

```
production.work_center.manage
production.machine.manage
production.routing.create
production.routing.update
production.routing.approve
production.routing.cancel
production.bom.create
production.bom.update
production.bom.approve
```

## Default roles (seed on install, tenant_id = NULL, cloned per tenant on signup)

```
super_admin          — platform scope, cross-tenant, is_dev-gated actions
tenant_owner         — tenant scope, full access within their tenant
company_admin        — tenant scope, most modules, no billing/platform actions
production_manager   — branch/department scope on production.*
production_engineer  — team/own scope on production.*
auditor              — tenant scope, read-only across modules + audit log access
read_only            — own/team scope, view-only everywhere
```

When CRM/Inventory/HRMS/Finance are added later, extend these same roles with the new
module's permissions rather than creating parallel role sets — e.g. `production_manager`
doesn't need a CRM equivalent role; give `company_admin` and dedicated
`sales_manager`/`inventory_manager` etc. the CRM/Inventory permissions when those
modules land, following the same scope pattern (own/team/branch/tenant).
