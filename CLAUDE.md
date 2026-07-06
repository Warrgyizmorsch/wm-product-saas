# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel 12 modular monolith for a multi-tenant SaaS ERP (CRM, Inventory, Sales, Purchase, Production, HRMS, Accounting, Projects). Row-based tenant isolation, domain-module architecture, Blade/Duralux admin UI, Tailwind v4 + Vite frontend build.

## Commands

```bash
# Setup
composer install
copy .env.example .env   # or: cp .env.example .env
php artisan key:generate
php artisan migrate
npm install

# Local dev (runs server + queue worker + log tail + vite concurrently)
composer dev

# Tests (clears config cache first, then runs the full suite)
composer test
# or directly:
php artisan test
php artisan test --filter=TestClassName
php artisan test tests/Feature/ProductionBomTest.php

# Single PHPUnit test method
php artisan test --filter=test_method_name

# Frontend build
npm run dev     # vite dev server
npm run build    # production build

# Misc
composer dump-autoload
php artisan route:list
```

Tests run against an in-memory SQLite DB (`phpunit.xml` sets `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), independent of the local `.env` (which is configured for MySQL).

## Architecture

### Tenant isolation (row-based, not separate DBs)

- `app/Core/Tenant/TenantContext.php` — holds the resolved `Tenant` for the current request (bound as a singleton via `App\Support\Tenancy` in `AppServiceProvider`).
- Tenant resolution happens in `routes/web.php`: every module route file (`app_path('Domains/*/Routes/web.php')`) is `require`d inside a `Route::middleware(['tenant'])` group. The `tenant` alias maps to `App\Http\Middleware\ResolveTenant` (see `bootstrap/app.php`). Related middleware: `IdentifyTenant` (`identify.tenant`) and `TenantMiddleware` (`tenant.required`).
- Resolution order: `X-Tenant` header (configurable via `TENANT_HEADER`) → domain/subdomain → fallback slug (`TENANT_LOCAL_FALLBACK_SLUG`). Central (non-tenant) domains are listed in `CENTRAL_DOMAINS` (`config/tenancy.php`).
- `app/Models/Concerns/BelongsToTenant.php` is the trait that does the actual work: adds a global `tenant_id` scope on queries and auto-fills `tenant_id` on `creating`. `app/Core/Database/BaseModel.php` just applies this trait — **every tenant-owned model should extend `BaseModel`**, not `Illuminate\Database\Eloquent\Model` directly.
- Helpers in `app/helpers.php` (autoloaded as a file, not PSR-4): `tenant()`, `tenant_id()`, `current_tenant_id()`, `require_tenant_id()`, `tenant_context()`, `tenant_branding()`. Prefer these over reaching into `TenantContext` directly in application code.

### Domain modules (`app/Domains/{Module}`)

Each module (CRM, Inventory, Sales, Purchase, Production, HRMS, Accounting, Projects, Platform) is self-contained:

```
Controllers/  Models/  Services/  Repositories/  DTO/  Events/  Listeners/  Requests/  Routes/web.php
```

Reference implementation: CRM (`app/Domains/CRM`) — `LeadController`/`Lead`, `CustomerController`/`Customer`/`CustomerService`/`CustomerRepository`, `QuotationController`/`Quotation`/`QuotationService`/`QuotationRepository`.

Rules:
- Controllers stay thin; business logic goes in Services; query/persistence logic goes behind Repositories.
- Cross-module effects (e.g. Sales updating Inventory or Accounting) go through events/listeners — **never** query another module's tables directly.
- Repository interfaces for a module are bound in `AppServiceProvider::register()` (see the Production bindings there as the pattern to follow for new modules).
- New module routes are picked up automatically by the `glob()` in `routes/web.php` — just add `app/Domains/{Module}/Routes/web.php`.

### RBAC / Access control

Custom RBAC layer (not Spatie), living in `app/Models/Access/` (`Role`, `Permission`, `RolePermission`, `UserRole`, `UserPermissionOverride`) and `app/Services/Access/AccessService.php`.

- All permission checks should go through `AccessService::allows($user, string $permissionName, array $context)`, where `$context` carries `tenant_id`/`branch_id`/`department_id`/`owner_id` as relevant. Don't scatter role/permission logic across controllers or policies — policies should call into `AccessService`.
- Permission naming convention: `module.entity.action.scope`, e.g. `crm.leads.view.own`, `inventory.items.edit.branch`. Scopes: `own`, `team`, `department`, `branch`, `tenant`, `platform` (see `RolePermission::SCOPE_*` constants).
- `AccessService` falls back to a **legacy permission map** (`config/production.php` → `permissions`) keyed by the user's simple `role` string when no `Permission` row / role assignment matches. This legacy path currently only covers the Production module — new modules should be seeded into the `permissions`/`role_permissions` tables rather than extended through the legacy map.
- Production module Gate policies (`ProductionBomPolicy`, `WorkCenterPolicy`, `MachinePolicy`, `RoutingPolicy`, `ProductionPlanPolicy`, `ProductionOrderPolicy`) are registered in `AppServiceProvider::boot()` and are the current reference for wiring a policy through `AccessService`.

### Frontend / Views

- `resources/views/layouts/duralux.blade.php` — main admin layout (Duralux theme).
- `resources/views/partials/duralux/` — shared shell partials (header, sidebar, etc.).
- `resources/views/components/{ui,forms,tables}` — reusable Blade components.
- `resources/views/modules/{module}` — module screens only (module names are lowercase: `crm`, `inventory`, `sales`, `purchase`, `production`, `hrms`, `accounting`, `projects`, `platform`, `dashboard`). Do not add new screens under a bare `resources/views/{module}` path.
- Built with Tailwind CSS v4 (`@tailwindcss/vite`) and Laravel Vite plugin; entry points are `resources/css/app.css` and `resources/js/app.js`.

## Notes

- `prompt.md`, `RBAC1.md`, `user-right.md`, `BRD.md`, `rbac.mb` at the repo root are design/planning notes (not authoritative docs) written while building out RBAC and requirements — treat the actual code under `app/Models/Access` and `app/Services/Access` as the source of truth over these notes, since the implementation has moved on from some of the plans described there.
