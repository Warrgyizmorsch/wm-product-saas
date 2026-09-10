# Project Management Module — Technical Architecture Document

> **Document Status:** Canonical Architectural Standard  
> **Target Location:** `docs/project-management/ARCHITECTURE.md`  
> **Audience:** Core Engineers & AI Coding Agents

---

## 1. Architectural Overview

The Project Management module resides inside [`app/Domains/Projects`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/Projects) as a distinct vertical domain within this multi-tenant SaaS ERP.

The module follows a layered clean architecture:
- **Presentation Layer:** Thin controllers handling HTTP requests, delegating to FormRequests for validation, calling Domain Services, and returning JSON or Blade views.
- **Domain Service Layer:** Houses all business rules, lifecycle state transitions, sequential code generation, transaction management, and activity logging.
- **Data Access Layer:** Repository interfaces bound to Eloquent implementations, abstracting query construction, filtering, and sorting.
- **Persistence Layer:** Eloquent models extending [`BaseModel`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Core/Database/BaseModel.php) and enforcing strict multi-tenancy via global scopes.
- **Security & Authorization Layer:** Granular policies querying the central [`AccessService`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Services/Access/AccessService.php) with scoped permission evaluation.

```
       HTTP Request
            │
            ▼
┌───────────────────────┐
│     Route Group       │ ◄─── [tenant, auth, company, branch, module.access]
└───────────┬───────────┘
            │
            ▼
┌───────────────────────┐
│      Controller       │ ◄─── Thin: Authorize → Validate → Delegate → Respond
└─────┬───────────┬─────┘
      │           │
      ▼           ▼
┌───────────┐ ┌───────────────┐
│FormRequest│ │ Domain Policy │ ◄─── Calls AccessService::allows() (own/team/tenant)
└───────────┘ └───────────────┘
      │
      ▼
┌───────────────────────┐
│    Domain Service     │ ◄─── Business Logic, Transactions, Code Gen, Activity Logs
└───────────┬───────────┘
            │
            ▼
┌───────────────────────┐
│ Repository Interface  │ ◄─── Bound in AppServiceProvider
└───────────┬───────────┘
            │
            ▼
┌───────────────────────┐
│    Eloquent Model     │ ◄─── Extends BaseModel, BelongsToTenant, Company, Branch
└───────────┬───────────┘
            │
            ▼
┌───────────────────────┐
│    Database Table     │ ◄─── InnoDB, tenant_id FK, project_* prefixed tables
└───────────────────────┘
```

---

## 2. Directory Structure

```
app/Domains/Projects/
├── Concerns/          # Shared controller/service traits (e.g. BuildsBackUrl)
├── Controllers/       # Thin HTTP action handlers
├── DTO/               # Data Transfer Objects for complex cross-layer payloads
├── Events/            # Domain events (dispatched on key mutations)
├── Exports/           # Maatwebsite Excel export classes (e.g. ProjectsExport)
├── Listeners/         # Event listeners & notification dispatchers
├── Models/            # Eloquent entities extending BaseModel
├── Policies/          # Authorization policies calling AccessService
├── Repositories/      # Interfaces and Eloquent repository implementations
├── Requests/          # Dedicated FormRequest validation classes
├── Routes/            # Domain web and API route definitions (web.php)
├── Seeders/           # Realistic demo generators & RBAC permission seeders
├── Services/          # Domain services encapsulating business rules
└── Support/           # Payloads & helpers (e.g. TaskDrawerPayload)
```

---

## 3. Layered Responsibilities & Standards

### 3.1 Controllers
- **Rule:** Controllers must remain thin.
- Responsibilities:
  1. Check authorization via `$this->authorize('action', [Model::class, $context])`.
  2. Accept validated data via typed `FormRequest`.
  3. Invoke the relevant `DomainService`.
  4. Return a Blade view, a JSON response for AJAX calls, or a redirect with a localized flash toast.
- **Anti-pattern:** Never write raw Eloquent mutation queries (`Model::create()`, `DB::table()->update()`) or direct business logic inside controllers.

### 3.2 Form Requests (Validation)
- **Rule:** Every write operation (POST, PUT, PATCH, DELETE) must have a dedicated `FormRequest`.
- Responsibilities:
  - Enforce tenant isolation in validation rules:
    ```php
    Rule::exists('customers', 'id')->where('tenant_id', require_tenant_id())
    ```
  - Enforce project-boundary integrity (e.g., verifying that a selected task list belongs to the route project):
    ```php
    Rule::exists('project_task_lists', 'id')
        ->where('tenant_id', $tenantId)
        ->where('project_id', $project?->id)
    ```
- **Anti-pattern:** Do not place inline `$request->validate([...])` arrays in controller methods.

### 3.3 Domain Services (Business Logic)
- **Rule:** All mutation operations and state transitions belong in Services.
- Responsibilities:
  - State machine governance (e.g. [`ProjectService::TRANSITIONS`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/Projects/Services/ProjectService.php#L24-L31), [`TaskService::TRANSITIONS`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/Projects/Services/TaskService.php#L19-L26)).
  - Transaction boundaries: wrap multi-table writes in `DB::transaction()`.
  - Sequential code generation (`PRJ-0001`, `PRJ-0001-T-001`).
  - Invariant enforcement (e.g. `ProjectMemberService::ensureCollaborator()`).
  - Activity audit logging via `ActivityLogService::record()`.
- **Anti-pattern:** Do not perform HTTP redirects or read request input directly inside domain services; accept plain arrays or DTOs.

### 3.4 Repositories & Data Access
- **Rule:** Read queries, complex filtering, search, and pagination must be handled in Repositories.
- Repositories must define an Interface (`*RepositoryInterface`) and an Eloquent Implementation (`*Repository`).
- All interfaces must be registered in [`AppServiceProvider::register()`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Providers/AppServiceProvider.php).
- Single source of query truth:
  - Use a private `baseQuery(array $filters)` method shared by `getAll()` (paginated) and `getQuery()` (unpaginated for exports) to guarantee identical filtering and sorting.
  - Whitelist all sortable columns to prevent SQL injection.

### 3.5 Models
- **Rule:** Every model in `app/Domains/Projects/Models` must extend [`App\Core\Database\BaseModel`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Core/Database/BaseModel.php).
- Must include the core tenant traits:
  ```php
  use App\Models\Concerns\BelongsToTenant;
  use App\Models\Concerns\BelongsToCompany;
  use App\Models\Concerns\BelongsToBranch;
  use Illuminate\Database\Eloquent\SoftDeletes;
  ```
- Must define explicit `$fillable`, typed `$casts`, and explicit Eloquent relationships.
- Table naming convention: every new table in this domain must be prefixed with `project_*` (except the root `projects` table) to prevent namespace collisions.

---

## 4. Multi-Tenancy & Data Isolation

This application operates as a single-database, multi-tenant SaaS.

1. **Global Tenant Scope:**
   - [`BelongsToTenant`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Models/Concerns/BelongsToTenant.php) automatically applies a global scope filtering every `SELECT`, `UPDATE`, and `DELETE` by the active `tenant_id`:
     ```php
     static::addGlobalScope('tenant', function (Builder $builder) {
         if ($tenantId = tenant_id()) {
             $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
         }
     });
     ```
   - It also automatically injects `tenant_id` on model creation.
2. **Company & Branch Scoping:**
   - All PM tables include `company_id` and `branch_id` foreign keys to allow organizational sub-scoping within the tenant.
3. **Route Model Binding Protection:**
   - All nested routes must use `->scopeBindings()` in `web.php`. This guarantees that an attempt to access `/projects/1/tasks/99` where task 99 belongs to project 2 aborts with 404.
4. **Direct URL Protection:**
   - Querying another tenant's project ID or code returns 404 because the global tenant scope prevents the row from being visible.

---

## 5. Role-Based Access Control (RBAC) & Policies

1. **AccessService Standard:**
   - Every policy must inject and call [`App\Services\Access\AccessService::allows()`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Services/Access/AccessService.php).
   - Never use legacy role string comparisons (e.g. `$user->role === 'admin'`).
2. **Scope Contexts:**
   - Policies pass the data context (`tenant_id`, `owner_id`, `company_id`, `branch_id`) to allow `AccessService` to evaluate user permission scopes (`own`, `team`, `tenant`):
     ```php
     public function update(User $user, Project $project): bool
     {
         return $this->access->allows($user, 'projects.projects.update', [
             'tenant_id' => $project->tenant_id,
             'owner_id'  => $project->owner_id,
         ]);
     }
     ```
3. **Registration:**
   - All domain policies are registered in [`AppServiceProvider::boot()`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Providers/AppServiceProvider.php) via `Gate::policy()`.

---

## 6. Shared ERP Infrastructure Reuse (Mandatory Rules)

Future coding agents must reuse established shared components rather than creating parallel infrastructure:

| Capability | Reused ERP Component | Rule |
|---|---|---|
| **Clients / Customers** | [`App\Domains\CRM\Models\Customer`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/CRM/Models/Customer.php) | Reference via `customer_id`. Do NOT create a `ProjectClient` model. |
| **Users / Resources** | [`App\Models\User`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Models/User.php) | Reference via `user_id`. Do NOT create a separate `Resource` model. |
| **Employees** | *Do not use HRMS Employee directly* | HRMS `Employee` lacks tenant scoping. Always link through `User`. |
| **Invoicing & Billing** | [`App\Domains\Sales\Models\Invoice`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/Sales/Models/Invoice.php) | **Do NOT create `project_invoices`.** Project billing creates Sales Invoices. |
| **Accounting / GL** | [`App\Domains\Accounting`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/Accounting) | Handled automatically through standard Sales Invoice auto-posting. |
| **Notifications** | Laravel native database channel on `User` | Use the `Notifiable` trait. Do NOT install separate packages. |
| **Inline Editing** | [`HandlesInlineFieldUpdates`](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Support/InlineEdit/HandlesInlineFieldUpdates.php) | Use standard inline-edit schema and `x-ui.inline-edit` Blade component. |

---

## 7. Database & Migration Rules

1. **Foreign Key Constraints:**
   - All foreign keys must be explicit with cascade or nullify rules (`cascadeOnDelete()`, `nullOnDelete()`).
2. **Soft Deletes:**
   - All primary business entities (`projects`, `project_members`, `project_milestones`, `project_task_lists`, `project_tasks`, `project_sub_tasks`) must support soft deletes (`$table->softDeletes()`).
   - Pure join/edge tables (e.g. `project_task_dependencies`) and log streams (`project_activity_logs`) do not require soft deletes.
3. **No Destructive Operations:**
   - Never rename existing tables, columns, or drop columns without an explicit migration and prior approval.

---

## 8. Canonical Rules for Coding Agents

1. **Thin Controllers:** Zero business logic in controllers.
2. **Domain Services for Logic:** Encapsulate all multi-step workflows, transactions, and state validations in `Services`.
3. **FormRequests for Validation:** Every write route must be validated via a FormRequest.
4. **Server-Side Authorization:** Never rely on UI button hiding alone; enforce policy checks on all controller actions.
5. **No Duplicate Infrastructure:** Always check existing ERP domains before introducing new cross-cutting capabilities.
6. **Preserve Fast-Create UX:** Keep minimal initial modal creation followed by inline detail completion.
7. **Maintain Test Coverage:** Every new feature must be accompanied by comprehensive Feature tests, maintaining a 100% test pass rate.
