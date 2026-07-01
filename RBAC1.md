Best way for **this project**: integrate RBAC gradually through your existing Laravel **Policies + tenant scope**, using a custom `AccessService`. Do not start with a full rewrite.

Your app already has the right foundations:

- Modular domains: `app/Domains/Production`, `CRM`, `Inventory`, etc.
- Tenant global scope via `BelongsToTenant` / `BaseModel`
- Laravel policies for Production
- Permission-like names already in `config/production.php`

So the clean path is:

**1. Fix Authorization Bypass First**

Before adding RBAC, remove the unsafe bypasses:

- [AppServiceProvider.php](C:/xampp/htdocs/laravel/wm-product-saas/app/Providers/AppServiceProvider.php:47)
- [HasProductionPermissions.php](C:/xampp/htdocs/laravel/wm-product-saas/app/Models/Concerns/HasProductionPermissions.php:24)

Right now they allow access in non-testing environments. That must be fixed first.

**2. Add RBAC Tables**

Use custom tables:

```text
roles
permissions
role_permissions
user_roles
user_permission_overrides
```

Recommended structure:

```text
roles:
id, tenant_id nullable, name, slug, level, is_system

permissions:
id, module, entity, action, name

role_permissions:
role_id, permission_id, scope

user_roles:
user_id, role_id, tenant_id

user_permission_overrides:
user_id, permission_id, scope, allowed
```

Scopes should be:

```text
own
team
department
branch
tenant
platform
```

**3. Create One AccessService**

All permission logic should go through one class:

```php
AccessService::allows($user, 'production.routing.approve', [
    'tenant_id' => $routing->tenant_id,
    'owner_id' => $routing->created_by ?? null,
    'branch_id' => $routing->branch_id ?? null,
    'department_id' => $routing->department_id ?? null,
]);
```

This keeps RBAC logic out of controllers and policies.

**4. Keep Laravel Policies**

Do not replace policies. Update them to call the RBAC service.

Example:

```php
public function approve(User $user, Routing $routing): bool
{
    return app(AccessService::class)->allows($user, 'production.routing.approve', [
        'tenant_id' => $routing->tenant_id,
    ]);
}
```

**5. Start With Production Module**

Do not add permissions for the whole ERP at once. Start with the module that already has policy coverage:

```text
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

Then add CRM, Inventory, HRMS, Finance later.

**6. Seed Default Roles**

Start with:

```text
super_admin
tenant_owner
company_admin
production_manager
production_engineer
auditor
read_only
```

Give `super_admin` platform scope, tenant roles tenant scope, and operational roles module-specific scopes.

**7. Later Add UI**

After backend checks work, add screens for:

```text
Access > Roles
Access > Permissions
Access > Assign Roles
Access > User Overrides
```

Best implementation order:

```text
1. Remove bypasses
2. Add migrations/models
3. Add AccessService
4. Seed production permissions and roles
5. Connect Production policies
6. Add tests
7. Add admin UI
```

For this ERP, I recommend **custom RBAC + Laravel Policies**, not Spatie first. Spatie is good, but your project needs tenant, branch, department, owner, and platform scopes. A small custom layer will fit your architecture better.