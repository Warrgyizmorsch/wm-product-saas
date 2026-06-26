# WM Product SaaS ERP

Laravel 12 modular monolith starter for a multi-tenant SaaS ERP. The foundation follows the structure in `skills.md`: tenant-first request handling, row-based tenant isolation, domain modules, service/repository layers, and a Duralux Blade admin shell.

## Architecture

- `app/Core` contains platform-level building blocks such as tenancy, database base classes, and future auth primitives.
- `app/Domains` contains ERP modules. Each module owns its controllers, models, services, repositories, DTOs, events, listeners, and routes.
- `app/Core/Database/BaseModel.php` is the default parent for tenant-owned Eloquent models.
- `app/Models/Concerns/BelongsToTenant.php` applies tenant global scope and auto-fills `tenant_id` on create.
- `resources/views/layouts/duralux.blade.php` is the main admin layout.
- `resources/views/modules/{module}` is the Blade location for module screens.

## Tenant Flow

Tenant resolution is handled by the `tenant` middleware:

1. Read tenant key from `X-Tenant` header when present.
2. Otherwise resolve from domain or subdomain.
3. Store the tenant in `App\Core\Tenant\TenantContext`.
4. Use `tenant()` and `tenant_id()` helpers throughout services/models.
5. Tenant-owned models extending `BaseModel` are automatically scoped.

Central domains are configured in `config/tenancy.php` using `CENTRAL_DOMAINS`.

## Module Pattern

Use CRM Leads and Customers as the reference module:

- Lead Controller: `app/Domains/CRM/Controllers/LeadController.php`
- Lead Model: `app/Domains/CRM/Models/Lead.php`
- Controller: `app/Domains/CRM/Controllers/CustomerController.php`
- Model: `app/Domains/CRM/Models/Customer.php`
- Service: `app/Domains/CRM/Services/CustomerService.php`
- Repository: `app/Domains/CRM/Repositories/CustomerRepository.php`
- Routes: `app/Domains/CRM/Routes/web.php`
- Views: `resources/views/modules/crm/...`

When building a new module feature, keep controller methods thin, put business logic in services, put query persistence behind repositories, and use events/listeners for cross-module workflows.

## View Structure

- `resources/views/layouts` contains app-level layouts such as Duralux.
- `resources/views/partials` contains shared shell partials such as header, sidebar, and footer.
- `resources/views/components` contains reusable UI/form/table components.
- `resources/views/modules/{module}` contains module pages only.
- `resources/views/modules/dashboard` contains dashboard-specific screens.

Do not place new module screens directly under `resources/views/crm`, `resources/views/sales`, etc. Keep them under `resources/views/modules/{module}`.

## Current Domains

- CRM
- Inventory
- Sales
- Purchase
- Production
- HRMS
- Accounting
- Projects

## Developer Rules

- Tenant-owned tables must include an indexed `tenant_id`.
- Tenant-owned models should extend `App\Core\Database\BaseModel`.
- Do not call another module's database tables directly from module logic.
- Use events for cross-module side effects such as Sales updating Inventory or Accounting.
- Keep Duralux pages under `resources/views/modules`.
- Keep shared UI in `resources/views/components` or `resources/views/partials`.

## Useful Commands

```bash
composer dump-autoload
php artisan migrate
php artisan route:list
php artisan test
```
