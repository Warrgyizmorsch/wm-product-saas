---
name: dashboard-widget
description: Add or change a dashboard widget in this Laravel ERP — a KPI, list, chart or whole block that users can add, move, resize and remove on the common dashboard or a module dashboard (Accounting today). Also covers making another module's dashboard customizable with the same widget grid. Use when the user asks for a new dashboard widget/card/chart/KPI, wants a widget to appear in "Add widget", or wants CRM/Production/HRMS/Inventory/Sales/Purchase dashboards to be customizable like the Accounting one.
---

# Dashboard widgets

Every customizable dashboard (`/dashboard`, `/accounting/dashboard`) is a grid of widgets. A widget is **one entry in a module's `Dashboard/widgets.php`**. Nothing else needs registering: `WidgetRegistry` globs `app/Domains/*/Dashboard/widgets.php`, so the widget shows up in **Add widget** for everyone who is allowed to see it.

Read these before changing anything non-trivial:
- `app/Core/Dashboard/WidgetRegistry.php` — the definition contract (doc comment at the top is authoritative).
- `app/Domains/Platform/Services/DashboardService.php` — layouts, starter sets, running widgets.
- `app/Domains/Accounting/Dashboard/widgets.php` — the most complete examples (KPI, chart, list, HTML blocks).
- `tests/Feature/CommonDashboardTest.php` — checks every registered widget automatically.

## Add a widget to the common dashboard

Edit your own module's file (create it if the module has none): `app/Domains/{Module}/Dashboard/widgets.php`. It returns a list:

```php
<?php

use App\Core\Dashboard\WidgetContext;
use App\Domains\Sales\Models\SalesOrder;

return [
    [
        'key' => 'sales.orders_today',          // unique: {module}.{name}
        'title' => 'Orders Today',
        'module' => 'sales',                    // plan-gated module key (lowercase, as in the plan's features)
        'permission' => 'sales.orders.view',    // must be a real permission name; null = no gate (own data only)
        'type' => 'kpi',
        'icon' => 'feather-shopping-cart',
        'w' => 3, 'h' => 2,                     // default size on the 12-column grid (row = ~70px)
        'description' => 'Sales orders created today.',   // shown in Add widget
        'settings' => ['period' => true],       // optional, see below
        'data' => fn (WidgetContext $c): array => [
            'value' => number_format(SalesOrder::query()->whereBetween('created_at', [$c->period->from, $c->period->to])->count()),
            'sub' => $c->period->label(),
            'tone' => 'primary',                // primary|success|warning|danger|info
        ],
    ],
];
```

### What `data` must return, by `type`

| type | payload |
|---|---|
| `kpi` | `['value' => string, 'sub' => ?string, 'tone' => ?string]` |
| `list` | `['rows' => [['label' => string, 'value' => string], ...]]` |
| `links` | `['rows' => [['label' => string, 'url' => string], ...]]` |
| `donut` | `['labels' => [...], 'values' => [...]]` |
| `bar` | `['labels' => [...], 'values' => [...]]` |
| `line` | `['labels' => [...], 'series' => [['name' => string, 'data' => [...]], ...]]` |
| `html` | `['html' => string, 'charts' => [...]]` — see "Module dashboards" |

Return only JSON-serialisable values. Format numbers into strings yourself (`number_format`).

### The context (`WidgetContext $c`)

- `$c->user`, `$c->tenantId`
- `$c->period` — `Period` with `from`, `to` (Carbon, start/end of day), `preset`, `label()`. Already resolved from the dashboard filter or the widget's own setting.
- `$c->consolidated` — the "All companies" scope is on (the request already runs inside `CompanyScopeRunner`; you rarely need this — accounting passes it to its cached summary).
- `$c->limit` — rows to show (default 8, user can set 3–20).
- `$c->costCenterId` — Accounting's cost-centre filter.

### Optional keys

- `'settings' => ['period' => true, 'limit' => true]` — shows Period / Rows fields in the widget's gear dialog. Declare only what your `data` actually reads (`$c->period` / `$c->limit`); anything else is dropped on save.
- `'dashboards' => ['common']` — which dashboards offer the widget (default `['common']`).
- `'chrome' => false` — the widget draws its own card (used by `html` blocks).
- `'cache' => false` — skip the 60 s per-user cache (use when the data is already cached, e.g. Accounting's summary).

## Rules that keep widgets safe

1. **Permission is required.** Use the same permission the module's own screen checks (`crm.leads.view`, `accounting.reports.view`, ...). Only use `null` for data that is inherently the current user's own (approvals, notifications). If the permission is new, add it to `RbacSeeder` **and** a migration that inserts it for existing installs (see `2026_09_21_100000_create_dashboard_layouts_table.php` for the pattern).
2. **Stay inside your module.** Query your module's models. Never read another module's tables; for cross-module numbers reuse that module's service (e.g. `AccountingDashboardService::cachedSummary`).
3. **Use tenant-scoped models** (`BaseModel` / `BelongsToTenant`). The endpoint runs in the tenant context; do not add manual `tenant_id` filters unless the model lacks the scope.
4. **Keep it cheap.** No N+1, no loading whole tables (`->limit()`, aggregate in SQL). Widgets load in parallel and one slow widget slows the page.
5. **Do not catch errors to hide them.** A failing widget shows "could not be loaded" on its own card and is logged (`storage/logs/laravel.log`); the rest of the page is unaffected.
6. `module` must be the plan-gated module key. The `platform` module is always allowed.

## Module dashboards (like Accounting)

For a module dashboard that must keep its existing look, use `type => 'html'`:

1. Move each block's Blade markup into `resources/views/modules/{module}/dashboard/widgets/{name}.blade.php` (the block draws its own card).
2. In `widgets.php`, render it with the data you already have:

```php
[
    'key' => 'accounting.gst_tds', 'title' => 'GST & TDS', 'module' => 'accounting',
    'permission' => 'accounting.reports.view',
    'type' => 'html', 'chrome' => false, 'cache' => false,
    'dashboards' => ['accounting'],          // only offered on that dashboard
    'icon' => 'feather-percent', 'w' => 5, 'h' => 7, 'description' => '...',
    'data' => fn (WidgetContext $c): array => [
        'html' => view('modules.accounting.dashboard.widgets.gst', $summary($c) + ['money' => $money])->render(),
        'charts' => [],   // or ApexCharts specs, below
    ],
],
```

Charts inside an html block: give the element an id in your Blade (`<div id="acc-trend-chart"></div>`) and return specs:

```php
'charts' => [['id' => 'acc-trend-chart', 'format' => ['axis' => 0, 'tooltip' => 2], 'options' => [/* ApexCharts options, JSON only */]]]
```
`format` (decimals) makes the browser add number formatters — do not put JavaScript functions in `options`.

See the `$block` / `$page` helpers at the top of `app/Domains/Accounting/Dashboard/widgets.php`; copy that pattern.

## Make another module's dashboard customizable

Reuse the same grid; do not write new JS. For a `crm` dashboard:

1. `DashboardService::DASHBOARDS` — add `'crm'` (the endpoints validate against it).
2. `DashboardService::STARTERS` — add `'crm' => ['default' => [widget keys in order]]` (several named layouts show up as "Load a layout…"), and `DEFAULT_STARTER['crm'] = 'default'`.
3. Give the module's widgets `'dashboards' => ['crm']` (add `'common'` too if it should also be on the workspace dashboard).
4. Controller: build the state and preload, exactly like `AccountingDashboardController::index`:
   ```php
   $state = $this->layouts->pageState($user, $tenantId, 'crm');
   view(..., $state + [
       'initial' => $this->layouts->preload($state['layout'], $user, $tenantId, $widgetQuery),  // optional: paints without a fetch per widget
       'widgetQuery' => (object) $widgetQuery,   // the page's filters: preset, from, to, scope, cost_center_id
   ]);
   ```
5. View: put `@include('partials.dashboard.actions')` in `page-actions` and `@include('partials.dashboard.grid')` where the widgets go, and define the filters in a script: `window.dashboardBaseQuery = () => @json($widgetQuery);` If your filters change client-side, call `window.dashboardReload()` after updating them.
6. Keep the page's existing filters, exports and access check; only the blocks below them become widgets.

Presets accepted for `preset` are `Period::PRESETS` (`app/Core/Dashboard/Period.php`). Add a preset there only if every dashboard can use it.

## Test it

```bash
php artisan test --filter=CommonDashboardTest
```
This already loops over **every registered widget** (definition valid, returns data for a tenant owner, survives every filter and the All-companies scope), so a broken or missing key fails the run. For a new module dashboard also assert its page renders with every block (see `test_the_accounting_page_renders_every_block_of_both_layouts`), and run that module's existing dashboard tests.

Checklist before you finish:
- [ ] unique `key`, correct `module`, real `permission`
- [ ] payload matches the `type` table
- [ ] `settings` declared only for what `data` reads
- [ ] no other module's tables queried; no unbounded queries
- [ ] `CommonDashboardTest` passes; open the dashboard → Customize → Add widget → your widget is listed, adds, saves and reloads
