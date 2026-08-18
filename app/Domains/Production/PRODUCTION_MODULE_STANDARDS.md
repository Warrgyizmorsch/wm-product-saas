# Production Planning Module — Engineering & Development Standards

## 1. Purpose
This document defines mandatory architecture, repository pattern, UI component, styling, multi-tenancy, database transaction, testing, and regression-safety standards for all current and future development in the Laravel Multi-Tenant SaaS ERP Production Planning module (`app/Domains/Production`).

Every developer, pair programmer, or AI assistant modifying code in `app/Domains/Production` MUST read, understand, and comply with this rulebook before making any changes.

---

## 2. Golden Rule
> **Existing Production business behavior, calculations, workflows, status transitions, view variables, and manufacturing rules MUST NOT be changed unless the feature or task explicitly requires a business-rule modification.**

Architectural refactoring or UI enhancements must wrap around existing working logic without altering manufacturing behavior.

---

## 3. Production Architecture Standard
The Production domain strictly enforces the 4-layer architecture:

```text
Controller
    ↓
Service
    ↓
Repository Interface
    ↓
Repository Implementation
    ↓
Eloquent Model / Query Builder
    ↓
Database
```

### Controller Layer
- **Allowed**: Request validation (Form Requests), Authorization gates (`Gate::authorize`), Service method invocation, view responses (`view()`), JSON responses, redirects.
- **Forbidden**: Direct Model queries, complex `where` chains, direct `DB::table` calls, persistence logic (`Model::create`, `Model::update`), scheduling/MRP/WIP logic.

### Service Layer
- **Responsible for**: Business workflows, transaction boundaries (`DB::transaction`), manufacturing calculations, MRP algorithms, forward/backward scheduling, batch reconciliation, WIP rules, quality decisions, material issue/return logic, and cross-domain orchestration.

### Repository Layer
- **Responsible for**: Encapsulating data retrieval, persistence, reusable queries, pagination, pessimistic locking helper methods (`lockForUpdate`), and tenant-aware database access.

---

## 4. Repository Pattern Is Mandatory
Any new Production persistence or query logic must first check whether an existing Repository already owns that aggregate root.

Do NOT write inline Model queries in Controllers:
```php
// FORBIDDEN in Controllers:
ProductionOrder::where('status', 'draft')->get();
ProductionBatch::create($data);

// MANDATORY:
$this->orderRepository->getPendingRequests($tenantId);
$this->batchRepository->create($data);
```

If an aggregate repository exists, extend its interface and implementation instead of bypassing it.

---

## 5. Do Not Create Unnecessary Repositories
Do not create one repository per database table. Repositories must represent meaningful domain aggregates. Child entities (e.g., `ProductionBomItem`, `ProductionOrderOperation`, `ProductionScheduleOperation`) are managed through their parent aggregate repository.

---

## 6. Flat Repository Namespace Standard
All Production repositories MUST be placed in the single flat directory:

```text
app/Domains/Production/Repositories/
```

Namespace:
```php
App\Domains\Production\Repositories
```

Do NOT create nested subdirectories such as `Repositories/Contracts/`, `Repositories/Eloquent/`, or `Repositories/Implementations/`.

---

## 7. Service Container Standard
Repository bindings belong exclusively in `app/Providers/AppServiceProvider.php` inside the `register()` method:

```php
$this->app->bind(
    \App\Domains\Production\Repositories\ProductionOrderRepositoryInterface::class,
    \App\Domains\Production\Repositories\ProductionOrderRepository::class
);
```

Every Repository Interface MUST have exactly one active binding.

---

## 8. Transaction Rule
`DB::transaction(...)` boundaries belong in the Service layer for multi-step Production workflows. Repositories MUST NOT encapsulate full business transactions.

---

## 9. Locking Rule
Repository methods may provide pessimistic locking helpers:
- `findForExecutionLock(int $id)`
- `lockBatchForUpdate(int $batchId)`
- `lockWipForTransfer(int $wipId)`

These methods MUST be executed within an active `DB::transaction()` owned by the calling Service.

---

## 10. Tenant Isolation Rule
All Production queries MUST preserve multi-tenant isolation:
1. Normal Eloquent queries rely on `BelongsToTenant` trait (`addGlobalScope('tenant')`).
2. Any usage of `withoutGlobalScopes()` or `withoutGlobalScope()` MUST be explicitly justified (e.g., background job or cross-tenant uniqueness validation).
3. Any unscoped query MUST explicitly enforce `->where('tenant_id', $tenantId)`.

---

## 11. Production UI Standard — BOM Module Is the Reference
The **BOM module (`resources/views/modules/production/bom/`) is the primary UI reference implementation** for all Production Planning screens.

When building or updating a Production screen, mirror the BOM module for layout, card panels, header toolbars, breadcrumbs, action buttons, table filters, modals, badges, and responsive spacing.

---

## 12. Common UI Components Are Mandatory
Production views MUST use the project's existing blade components located in `resources/views/components/ui/`:

| Component Name | Blade Tag | Purpose |
| :--- | :--- | :--- |
| **Odoo Form UI** | `<x-ui.odoo-form-ui>` | Odoo-style inputs, selects, textareas, form tables |
| **Sort Dropdown** | `<x-ui.sort-dropdown>` | Standard sort dropdown menu |
| **Filter Panel** | `<x-ui.filter>` | Expandable search & filter dropdown |
| **Bulk Actions** | `<x-ui.bulk-actions>` | Multi-select toolbar actions |
| **Import / Export** | `<x-ui.import-export-dropdown>` | Data import/export trigger dropdown |
| **Action Dropdown** | `<x-ui.action-dropdown>` | Row-level table actions menu |
| **Confirm Modal** | `<x-ui.confirm-modal>` | SweetAlert/modal confirmation dialog |
| **Modal** | `<x-ui.modal>` | Standard modal wrapper |
| **Pagination** | `<x-ui.pagination>` | Server-side pagination controls |
| **Horizontal Tabs** | `<x-ui.horizontal-tabs>` | Top navigation tab strip |
| **Vertical Tabs** | `<x-ui.vertical-tabs>` | Side navigation tab strip |
| **Card** | `<x-ui.card>` | Standard content card container |
| **Button / Icon Btn** | `<x-ui.button>`, `<x-ui.icon-btn>` | Styled ERP action buttons |
| **Status Badge** | `<x-ui.status-badge>` | Status indicator badges |
| **Table** | `<x-ui.table>` | Responsive data table wrapper |

---

## 13. Odoo Form Components
All Production forms MUST use `<x-ui.odoo-form-ui>` for form inputs, selects, textareas, and field groups to maintain design consistency with the ERP.

---

## 14. Table Standard
Production tables MUST follow the BOM table structure using `.table-responsive` and `<x-ui.odoo-form-ui type="table">` or `<x-ui.table>` with checkbox selection, column sorting headers, status badges, and action dropdowns.

---

## 15. Modal Standard
Modals MUST use `<x-ui.modal>` or `<x-ui.confirm-modal>`. Do NOT build raw Bootstrap modal HTML snippets.

---

## 16. Pagination Standard
All paginated Production views MUST use `<x-ui.pagination>` or `$models->withQueryString()->links()` ensuring filter parameters (`search`, `status`, `sort_by`) are preserved during page navigation.

---

## 17. Action Dropdown Standard
Row-level table actions MUST use `<x-ui.action-dropdown>`. Do not invent custom three-dot menus per screen.

---

## 18. Filter Standard
Search and filtering UI MUST use `<x-ui.filter>` with form GET submissions, preserving current query parameters and supporting a clear/reset action.

---

## 19. Primary Color Standard
The primary theme color is controlled by the CSS variable defined in `public/assets/css/erp.css`:

```css
:root {
    --bs-primary: #0000FF;
}
```

Production views and styles MUST use `var(--bs-primary)` or color-mix functions. Do NOT hardcode hex codes (e.g. `#4f46e5`, `#2563eb`) in inline styles.

Color mix helper standards:
- **Primary Tint Background**: `color-mix(in srgb, var(--bs-primary) 10%, transparent)`
- **Primary Tint Border**: `color-mix(in srgb, var(--bs-primary) 20%, transparent)`
- **Primary Focus Ring**: `0 0 0 3px color-mix(in srgb, var(--bs-primary) 15%, transparent)`

---

## 20. Do Not Introduce Module-Specific Theme Colors
Production UI must remain visually consistent with the ERP core palette. Use established semantic classes for status indicators:
- **Active / Success**: `.erp-badge-active`, `.btn-success`, `#81b29a`
- **Draft / Neutral**: `.erp-badge-draft`, `.btn-secondary`, `#3d405b`
- **Pending / Warning**: `.erp-badge-pending`, `.btn-warning`, `#f2cc8f`
- **Danger / Cancelled**: `.btn-danger`, `#e76f51`

---

## 21. No Hardcoded Styling When Utility/Common Class Exists
Prefer CSS variables (`var(--bs-primary)`), ERP utility classes (`.erp-single-panel`, `.erp-icon-btn`), or Bootstrap utility classes instead of inline `style=""` attributes.

---

## 22. Layout Consistency
All new Production screens MUST visually align with the single-panel layout (`.erp-single-panel`), card borders, typography scales (`.fs-11`, `.fs-12`, `.fw-bold`), and section headers used in BOM.

---

## 23. Reusable Component First Rule
Before writing new UI HTML, check:
1. Does a common component exist in `resources/views/components/ui/`?
2. How is it implemented in BOM views (`resources/views/modules/production/bom/`)?
3. Can the existing component fulfill the requirement?

---

## 24. No Duplicate Components
Do NOT create duplicate local components (e.g., `production-table`, `production-modal`) when `<x-ui.table>` or `<x-ui.modal>` exists.

---

## 25. Blade Variable Safety
Never rename Blade variables passed from Controllers to views (`compact('orders', 'statusCounts')`). Check all Blade template references before modifying controller responses.

---

## 26. Route & Path Safety
Never guess route names or view paths. Verify routes in `app/Domains/Production/Routes/web.php` and run `php artisan route:list --path=production`.

---

## 27. Existing Public API Compatibility
Do not alter public Service method signatures, controller route parameters, or API JSON response keys unless explicitly instructed.

---

## 28. Model Event Safety
Do not replace Eloquent model operations (`create`, `update`, `delete`) with raw SQL `DB::table()` queries if doing so bypasses Model Observers, events, mutators, or soft deletes.

---

## 29. Status Constants
Use Model constants (e.g., `ProductionOrder::STATUS_RELEASED`, `ProductionBom::STATUS_APPROVED`) instead of hardcoding status strings throughout code.

---

## 30. Business Logic Must Not Live in Blade
Blade views MUST NOT perform manufacturing calculations, MRP explosions, capacity planning, or cost aggregations. Prepare calculated view data in Services.

---

## 31. Avoid N+1 Queries
All list and detail repository queries MUST declare eager loading (`with([...])`) for all required relationships before passing models to views.

---

## 32. Pagination Required for Large Datasets
All production lists (Orders, Batches, WIP, Schedules, Plans, Quality Inspections, NCRs, Serials) MUST use server-side pagination (`paginate($perPage)`).

---

## 33. Search & Filters Must Be Backend Driven
Do not fetch entire datasets to filter via JavaScript on the client. Execute search and filtering in repository query builders.

---

## 34. Validation Standard
Use dedicated Form Request classes (e.g., `StoreProductionOrderRequest`, `UpdateProductionOrderRequest`) for request validation.

---

## 35. Authorization Standard
All Controller actions MUST enforce policy authorization (`Gate::authorize('view', $order)` or `$this->authorize(...)`). Repositories MUST NOT bypass authorization checks.

---

## 36. Error Handling Standard
Catch domain exceptions in Controllers and redirect with user-friendly flash messages (`->with('error', $e->getMessage())`) without exposing raw database stack traces.

---

## 37. AJAX / API Standard
AJAX endpoints must maintain standard JSON shapes (`{ success: true, data: ..., message: ... }`), CSRF protection, and tenant context.

---

## 38. JavaScript Standard
Keep scripts modular or inside designated Blade `@push('scripts')` blocks. Do not inline heavy JS logic directly in content blocks.

---

## 39. CSS Standard
Reuse `public/assets/css/erp.css` and `public/assets/css/production.css`. Do not add page-specific inline `<style>` tags.

---

## 40. Database Change Standard
All database migrations for Production must include `tenant_id` foreign key constraints, proper indexes on lookup columns, and default values.

---

## 41. Migration Safety
Migrations must be non-destructive, reversible, and tenant-aware. Never modify published, historical migrations.

---

## 42. Quantity Integrity Rules
Manufacturing quantities (ordered, planned, issued, produced, rejected, scrapped, reworked) MUST maintain conservation laws. Quantities must never vanish or be double counted.

---

## 43. Batch Continuity Rules
Batch numbers (`batch_no`) and IDs MUST remain continuous and traceable across all operation transitions from creation to finished goods receipt.

---

## 44. Scheduling Safety Rules
Scheduling changes MUST preserve forward scheduling, backward scheduling, operation overlap, transfer batch lag times, machine capacity, and shift calendars.

---

## 45. Quality / Rework / Scrap Safety
Quality failures MUST trigger automatic Quality Holds, NCR generation, and WIP movement restrictions until formal disposition is recorded.

---

## 46. Inventory Integration Safety
Material reservations, issues, returns, and finished goods receipts MUST synchronize with the Inventory domain without double-posting transactions.

---

## 47. Idempotency
Critical state-changing operations (release order, post FG receipt, initialize WIP) MUST implement idempotency guards against duplicate web requests.

---

## 48. Audit Trail
All major production events MUST log event timeline entries via `ProductionEventTimeline` or `ProductionEventService`.

---

## 49. Performance Rule
Ensure query performance scales linearly. Check `with()`, index usage, and server-side pagination for all list endpoints.

---

## 50. Testing Is Mandatory
Run the Production test suite before committing any change:
```bash
php artisan test --filter=Production
```

---

## 51. High-Risk Production Areas
Treat Scheduling, MES execution, WIP movement, Material Issuance, Quality Hold, Rework, Scrap, and Costing as HIGH RISK. Require targeted unit/feature tests for any modification in these areas.

---

## 52. PHP Syntax & Container Verification
Run syntax linting and clear caches after architecture updates:
```bash
composer dump-autoload
php artisan optimize:clear
php artisan route:list --path=production
```

---

## 53. Git Diff Review
Inspect `git diff` prior to completion to ensure no unintended changes were made to condition checks, calculations, status checks, or tenant filters.

---

## 54. Do Not Hide Regressions by Modifying Tests
If an existing test fails after a refactor, fix the underlying code implementation. Do not modify test expectations to cover up bugs.

---

## 55. Definition of Done for Any Production Change
A task in the Production module is DONE only when:
1. Architecture follows Controller → Service → Repository.
2. Repository layer is used consistently.
3. Multi-tenant isolation is preserved.
4. Existing business logic remains unchanged unless explicitly required.
5. Common UI components (`<x-ui.*>`) are used.
6. BOM module layout standard is followed.
7. `var(--bs-primary)` CSS variable is used for primary styling.
8. Blade variable names are preserved.
9. Container bindings resolve without error.
10. `php artisan test --filter=Production` passes cleanly.

---

## 56. Mandatory Pre-Change Checklist
Before starting any Production task:
- [ ] Read `PRODUCTION_MODULE_STANDARDS.md`.
- [ ] Identify target aggregate and repository.
- [ ] Check existing common UI components (`<x-ui.*>`).
- [ ] Inspect BOM views for layout reference.
- [ ] Verify tenant scoping and locking requirements.

---

## 57. Mandatory Post-Change Checklist
After completing any Production task:
- [ ] Run `git diff` to verify zero unintended logic changes.
- [ ] Clear Laravel caches (`php artisan optimize:clear`).
- [ ] Run production route verification (`php artisan route:list --path=production`).
- [ ] Run test suite (`php artisan test --filter=Production`).
