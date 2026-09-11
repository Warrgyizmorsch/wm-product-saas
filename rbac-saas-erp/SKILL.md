---
name: rbac-saas-erp
description: >
  Custom multi-tenant RBAC architecture for this Laravel SaaS ERP (single database,
  shared-schema, tenant_id scoping). Use this skill whenever working on roles, permissions,
  policies, AccessService, tenant scoping, branch/department/team scoping, permission
  overrides, audit logging, or the Production/CRM/Inventory/HRMS/Finance module access
  layer. Trigger also on "RBAC", "role", "permission", "scope", "AccessService", "tenant
  isolation", "policy", "middleware auth", "bypass", "can user", "authorize", or any request
  to add/modify a controller, policy, service, or migration that touches user access control.
  Always consult this skill before writing authorization code in this project — it encodes
  decisions already made for this specific codebase (custom RBAC over Spatie, module rollout
  order, schema shape) that must not be silently reinvented or reverted.
---

# RBAC Architecture — This Project

This project uses a **custom RBAC layer** (not Spatie) built on top of existing Laravel
Policies and tenant scoping. This was a deliberate decision — see "Why custom, not Spatie"
below. Do not suggest replacing it with a package unless the user explicitly asks to
reconsider.

## Non-negotiable ground rules

1. **Never bypass tenant isolation.** Every tenant-owned model extends `BaseModel`
   (`app/Core/BaseModel.php`), which applies a global `TenantScope`. Never write raw
   queries that skip this scope. Never add a "skip tenant check" flag that isn't
   explicitly gated and audited.
2. **No authorization bypass outside testing.** `AppServiceProvider.php` and
   `HasProductionPermissions.php` previously had bypasses that allowed access in
   non-testing environments — this was a known bug, already flagged for removal. If you
   see a `if (!app()->environment('testing'))`-style bypass guarding an authorization
   check, flag it and fix it before adding anything else on top.
3. **All permission logic goes through `AccessService`.** Controllers and Policies must
   never hand-roll scope checks (`if ($record->owner_id == $user->id)` inline). They call
   `AccessService::allows()`. This keeps the scope rules in one place.
4. **Permission names are fixed strings**, pattern `module.entity.action` (scope is a
   separate column, not baked into the name — see schema below). Don't invent ad-hoc
   permission strings inline in controllers; add them to the seeder.

## Architecture at a glance

```
permissions        — fixed, platform-defined, no tenant_id
roles               — tenant_id nullable (NULL = system template, cloned per tenant)
role_permissions    — role_id, permission_id, scope
user_roles          — user_id, role_id, tenant_id   (pivot — supports multiple roles/user)
user_permission_overrides — user_id, permission_id, scope, allowed
permission_audit_log — who changed what access, when, for which tenant
```

Scopes (fixed enum, used everywhere): `own`, `team`, `department`, `branch`, `tenant`, `platform`.

Full column-level schema → `references/schema.md`
Full `AccessService` implementation pattern → `references/access-service.md`
Module rollout order and seed data → `references/rollout-plan.md`

## Request flow

```
Request → IdentifyTenant middleware → Auth → CheckPermission middleware
        → Controller → Policy → AccessService::allows() → Service Layer → Events → Response
```

`CheckPermission` middleware does a tenant-match check *in addition to* the permission
check — never rely on the permission check alone, because a role ID collision across
tenants is a real risk in a shared-schema design.

## Policies stay — they just delegate

Do not remove or replace existing Laravel Policies. Update their bodies to call
`AccessService`:

```php
public function approve(User $user, Routing $routing): bool
{
    return app(AccessService::class)->allows($user, 'production.routing.approve', [
        'tenant_id'     => $routing->tenant_id,
        'owner_id'      => $routing->created_by ?? null,
        'branch_id'     => $routing->branch_id ?? null,
        'department_id' => $routing->department_id ?? null,
    ]);
}
```

## Why custom, not Spatie

Spatie's package models scope as `team_id` only out of the box. This project needs
`own` / `team` / `department` / `branch` / `tenant` / `platform` simultaneously, and
permission scope varies *per role* (e.g. `crm.leads.view` is `own` for Sales Executive
but `team` for Sales Manager — same permission, different scope, without duplicating
permission rows). A custom `role_permissions.scope` column handles this directly; forcing
it into Spatie means fighting the package's model. Don't re-litigate this without a
concrete new constraint that changes the tradeoff.

## Module rollout order — do not add all modules at once

Production is the pilot module (it already has policy coverage). Only after Production's
`AccessService` integration is tested and stable should CRM, Inventory, HRMS, Finance be
added — see `references/rollout-plan.md` for the exact order and the seed permission list
per module.

## When asked to add a new permission

1. Check it doesn't already exist (`module.entity.action` pattern, grep the permissions
   seeder first).
2. Add it to the relevant module's seeder, not inline in a migration.
3. Decide default scope per default role in the same seeder — don't leave a permission
   unassigned to every role, that silently locks everyone out including admins.
4. If the module doesn't have a Policy yet, create one and route it through
   `AccessService`, following the Production module pattern.

## When asked to debug "user can't access X"

Check in this order (matches where things actually break in this design):
1. Tenant mismatch — is `$user->tenant_id` even equal to the record's `tenant_id`?
2. Does the user's role actually have the permission (`role_permissions`)?
3. Is there a `user_permission_overrides` row setting `allowed = false` for this user?
4. Is the scope check failing — e.g. record's `branch_id` doesn't match the user's
   `branch_id`?
5. Is the permission cache stale (see caching note in `references/access-service.md`)?

Don't guess — trace this order, it covers the actual failure points in this architecture.
