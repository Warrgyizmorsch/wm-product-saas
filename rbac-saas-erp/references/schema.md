# RBAC Schema — Reference

Read this when writing migrations, models, or seeders for the RBAC layer.

## Tables

```php
Schema::create('roles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
    // NULL tenant_id = system template role, cloned into every new tenant on signup
    $table->string('name');
    $table->string('slug')->index();
    $table->unsignedTinyInteger('level')->default(0); // for hierarchy checks if ever needed
    $table->boolean('is_system')->default(false);
    $table->timestamps();

    $table->unique(['tenant_id', 'slug']);
});

Schema::create('permissions', function (Blueprint $table) {
    $table->id();
    $table->string('module');   // e.g. production
    $table->string('entity');   // e.g. routing
    $table->string('action');   // e.g. approve
    $table->string('name')->unique(); // module.entity.action, generated/stored
    $table->timestamps();
});

Schema::create('role_permissions', function (Blueprint $table) {
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
    $table->enum('scope', ['own', 'team', 'department', 'branch', 'tenant', 'platform']);
    $table->primary(['role_id', 'permission_id']);
});

Schema::create('user_roles', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    // tenant_id kept here too (not just derived from role) so a user who somehow
    // belongs to >1 tenant can't have their role misapplied cross-tenant.
    $table->primary(['user_id', 'role_id', 'tenant_id']);
});

Schema::create('user_permission_overrides', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
    $table->enum('scope', ['own', 'team', 'department', 'branch', 'tenant', 'platform']);
    $table->boolean('allowed')->default(true); // false = explicit deny, overrides role grant
    $table->timestamp('expires_at')->nullable(); // optional: temporary elevated access
    $table->timestamps();
});

Schema::create('permission_audit_log', function (Blueprint $table) {
    $table->id();
    $table->foreignId('actor_id')->constrained('users');       // who made the change
    $table->foreignId('target_user_id')->nullable()->constrained('users');
    $table->foreignId('tenant_id')->constrained();
    $table->string('action'); // role_assigned, role_removed, override_granted, platform_bypass, etc.
    $table->json('before_state')->nullable();
    $table->json('after_state')->nullable();
    $table->timestamps();
});
```

## Models — key points only (full class bodies in main app, not repeated here)

- `Permission` model does **not** extend `BaseModel` — it has no `tenant_id`, it's a
  platform-fixed table. Extending `BaseModel` here would silently apply a tenant scope
  that doesn't apply and break every permission lookup.
- `Role` extends `BaseModel` but with a modified scope: system template roles
  (`tenant_id IS NULL`) must remain visible even under the tenant global scope, or the
  cloning service can't read them. Use a dedicated `scopeSystemTemplates()` query that
  explicitly bypasses the global scope (`withoutGlobalScope(TenantScope::class)`), not a
  blanket exemption.
- `user_roles` and `user_permission_overrides` are plain pivot/override tables — don't
  make them extend `BaseModel`, filter by `user_id`/`tenant_id` explicitly in the
  relevant queries instead.

## Common mistake to avoid

Do not add a `tenant_id` column to the `permissions` table "just in case." Permissions
are the platform's fixed vocabulary of actions — if a permission needs to exist
differently per tenant, that's a role/scope problem, not a permission problem. Keeping
`permissions` tenant-free is what keeps `Gate::authorize()` checks in code predictable.
