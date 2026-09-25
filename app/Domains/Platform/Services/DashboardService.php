<?php

namespace App\Domains\Platform\Services;

use App\Core\Company\CompanyScopeRunner;
use App\Core\Dashboard\Period;
use App\Core\Dashboard\WidgetContext;
use App\Core\Dashboard\WidgetRegistry;
use App\Domains\Platform\Models\DashboardLayout;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Layouts and widget data for every customizable dashboard ('common', 'accounting', ...).
 * A layout is resolved per dashboard: personal, else role default, else tenant default,
 * else the built-in starter set.
 */
class DashboardService
{
    public const DASHBOARDS = ['common', 'accounting', 'hrms'];

    public const MIN_LIMIT = 3;

    public const MAX_LIMIT = 20;

    private const MANAGE_PERMISSION = 'dashboard.tenant.manage';

    private const WIDGET_TTL_SECONDS = 60;

    private const COLUMNS = 12;

    /** Shown until someone customizes: the widgets a user can see, in this order. */
    private const STARTERS = [
        'common' => [
            'default' => [
                'accounting.cash', 'accounting.net_profit', 'sales.open_orders', 'sales.outstanding_invoices',
                'crm.open_leads', 'hrms.headcount', 'production.open_orders', 'inventory.low_stock_count',
                'crm.pipeline', 'accounting.receivable_payable', 'platform.pending_approvals',
            ],
        ],
        'accounting' => [
            // For the people running the business: health and cash first.
            'overview' => [
                'accounting.kpi_revenue', 'accounting.kpi_expenses', 'accounting.kpi_net_profit', 'accounting.kpi_cash',
                'accounting.trend_detail', 'accounting.financial_health',
                'accounting.receivables_aging', 'accounting.payables_aging',
                'accounting.cash_forecast', 'accounting.expense_mix',
                'accounting.report_links',
            ],
            // For the people keeping the books: the close checklist and compliance first.
            'operations' => [
                'accounting.kpi_revenue', 'accounting.kpi_expenses', 'accounting.kpi_net_profit', 'accounting.kpi_cash',
                'accounting.month_end_close', 'accounting.gst_tds',
                'accounting.budget_alerts', 'accounting.bank_reconciliation',
                'accounting.recent_journals', 'accounting.cash_balances',
                'accounting.report_links',
            ],
        ],
        'hrms' => [
            'overview' => [
                'hrms.kpi_total_employees', 'hrms.kpi_today_attendance', 'hrms.kpi_pending_approvals', 'hrms.kpi_probation_exits',
                'hrms.web_punch', 'hrms.assigned_leave_plan',
                'hrms.pending_approvals', 'hrms.my_shift_details',
                'hrms.recent_late_arrivals', 'hrms.unprocessed_penalties', 'hrms.latest_salary_slip',
                'hrms.approved_leaves', 'hrms.upcoming_holidays', 'hrms.celebrations',
                'hrms.probation_ending_soon', 'hrms.active_exits_offboarding', 'hrms.department_headcount',
                'hrms.new_joinees_spotlight',
            ],
            'self_service' => [
                'hrms.web_punch', 'hrms.assigned_leave_plan', 'hrms.my_shift_details',
                'hrms.latest_salary_slip', 'hrms.celebrations', 'hrms.upcoming_holidays',
            ],
        ],
    ];

    private const DEFAULT_STARTER = ['common' => 'default', 'accounting' => 'overview', 'hrms' => 'overview'];

    public function __construct(
        private readonly WidgetRegistry $registry,
        private readonly AccessService $access,
    ) {
    }

    /** Owners and admins may set the tenant-wide and per-role defaults. */
    public function canManage(User $user): bool
    {
        // Judged in the user's own tenant, like the sidebar: a user switched into another tenant keeps their rights.
        return $this->access->allows($user, self::MANAGE_PERMISSION, ['tenant_id' => $user->tenant_id]);
    }

    /**
     * Everything a dashboard page needs to draw the customizable grid.
     *
     * @param string|null $starter which built-in layout to fall back to (dashboards with several)
     * @return array<string, mixed>
     */
    public function pageState(User $user, int $tenantId, string $dashboard, ?string $starter = null): array
    {
        $layout = $this->resolve($user, $tenantId, $dashboard, $starter);
        $canManage = $this->canManage($user);

        return [
            'dashboard' => $dashboard,
            'layout' => $layout['widgets'],
            'source' => $layout['source'],
            'catalog' => collect($this->registry->availableFor($user, $tenantId, $dashboard))->map(fn (array $w) => [
                'key' => $w['key'], 'title' => $w['title'], 'module' => $w['module'], 'type' => $w['type'],
                'icon' => $w['icon'], 'w' => $w['w'], 'h' => $w['h'], 'min_w' => $w['min_w'] ?? 2, 'min_h' => $w['min_h'] ?? 1, 'description' => $w['description'],
                'settings' => (object) $w['settings'], 'chrome' => $w['chrome'],
            ])->values(),
            'canManage' => $canManage,
            'roles' => $canManage
                ? Role::query()->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->orderBy('name')->get(['id', 'name'])
                : collect(),
            'periods' => Period::PRESETS,
            'layoutPresets' => $this->presets($user, $tenantId, $dashboard),
        ];
    }

    /**
     * Personal layout, else the user's role default, else the tenant default, else the starter set.
     * Widgets the user can no longer see (plan or permission changed) are dropped.
     *
     * @return array{widgets: list<array<string, mixed>>, source: string}
     */
    public function resolve(User $user, int $tenantId, string $dashboard = 'common', ?string $starter = null): array
    {
        $layouts = fn () => DashboardLayout::query()->where('dashboard', $dashboard);

        $layout = $layouts()->where('user_id', $user->id)->first();
        $source = 'personal';

        if ($layout === null) {
            $roleIds = $this->roleIds($user);
            $layout = $roleIds === [] ? null : $layouts()->whereIn('role_id', $roleIds)->orderBy('role_id')->first();
            $source = 'role';
        }

        if ($layout === null) {
            $layout = $layouts()->whereNull('user_id')->whereNull('role_id')->first();
            $source = 'tenant';
        }

        $widgets = $layout === null
            ? $this->starter($user, $tenantId, $dashboard, $starter)
            : $this->sanitize($layout->widgets ?? [], $user, $tenantId, $dashboard);

        return ['widgets' => $widgets, 'source' => $layout === null ? 'starter' : $source];
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @param 'personal'|'role'|'tenant' $scope
     */
    public function save(User $user, int $tenantId, array $widgets, string $scope, ?int $roleId = null, string $dashboard = 'common'): void
    {
        $key = match ($scope) {
            'personal' => ['user_id' => $user->id, 'role_id' => null],
            'role' => ['user_id' => null, 'role_id' => $roleId],
            default => ['user_id' => null, 'role_id' => null],
        };

        DashboardLayout::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'dashboard' => $dashboard] + $key,
            ['widgets' => $this->sanitize($widgets, $user, $tenantId, $dashboard)],
        );
    }

    /** Back to the shared default: removes only the user's own layout. */
    public function reset(User $user, string $dashboard = 'common'): void
    {
        DashboardLayout::query()->where('dashboard', $dashboard)->where('user_id', $user->id)->delete();
    }

    /**
     * Keeps only real, permitted widgets and clamps geometry, whatever the client sent.
     *
     * @param list<array<string, mixed>> $widgets
     * @return list<array<string, mixed>>
     */
    public function sanitize(array $widgets, User $user, int $tenantId, string $dashboard = 'common'): array
    {
        $clean = [];

        foreach ($widgets as $item) {
            $key = (string) ($item['key'] ?? '');

            if (! $this->registry->isAvailable($key, $user, $tenantId, $dashboard)) {
                continue;
            }

            $w = max(2, min(self::COLUMNS, (int) ($item['w'] ?? 3)));
            $config = $this->sanitizeConfig((array) ($item['config'] ?? []), $this->registry->find($key)['settings']);

            $clean[] = [
                'id' => preg_match('/^[A-Za-z0-9_.-]{1,64}$/', (string) ($item['id'] ?? '')) ? $item['id'] : $key.'-'.Str::lower(Str::random(6)),
                'key' => $key,
                'x' => max(0, min(self::COLUMNS - $w, (int) ($item['x'] ?? 0))),
                'y' => max(0, (int) ($item['y'] ?? 0)),
                'w' => $w,
                'h' => max(1, min(24, (int) ($item['h'] ?? 2))),
                'config' => $config,
            ];
        }

        return $clean;
    }

    /**
     * Runs one widget and returns what the browser draws, or null when it failed — one
     * broken widget must never break the page.
     *
     * @param array{preset?: ?string, from?: ?string, to?: ?string, scope?: ?string, limit?: int|string|null, cost_center_id?: int|string|null} $filters
     * @return array<string, mixed>|null
     */
    public function payload(string $key, User $user, int $tenantId, array $filters): ?array
    {
        $widget = $this->registry->find($key);

        if ($widget === null) {
            return null;
        }

        $context = new WidgetContext(
            $user,
            $tenantId,
            Period::resolve($filters['preset'] ?? null, $filters['from'] ?? null, $filters['to'] ?? null),
            ($filters['scope'] ?? 'current') === 'all',
            (int) ($filters['limit'] ?? WidgetContext::DEFAULT_LIMIT),
            isset($filters['cost_center_id']) && $filters['cost_center_id'] !== '' ? (int) $filters['cost_center_id'] : null,
        );

        $load = fn () => $context->consolidated
            ? app(CompanyScopeRunner::class)->acrossCompanies(fn () => $widget['data']($context))
            : $widget['data']($context);

        try {
            $data = $widget['cache']
                ? Cache::remember(implode(':', ['dashboard-widget', $tenantId, $user->id, company_id() ?? 0, branch_id() ?? 0, $key, md5(json_encode($filters))]), self::WIDGET_TTL_SECONDS, $load)
                : $load();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        return ['type' => $widget['type']] + $data;
    }

    /**
     * The payload of every widget in a layout, computed on the server so a page can
     * paint complete instead of showing a card per widget while it loads.
     *
     * @param list<array<string, mixed>> $layout
     * @param array<string, mixed> $filters the page's filters
     * @return array<string, array<string, mixed>|null> by layout item id
     */
    public function preload(array $layout, User $user, int $tenantId, array $filters): array
    {
        $out = [];

        foreach ($layout as $item) {
            $itemFilters = $filters;

            // A widget's own period beats the page filter, exactly as the browser does when it fetches.
            if (! empty($item['config']['period'])) {
                $itemFilters['preset'] = $item['config']['period'];
                unset($itemFilters['from'], $itemFilters['to']);
            }

            if (isset($item['config']['limit'])) {
                $itemFilters['limit'] = $item['config']['limit'];
            }

            $out[$item['id']] = $this->payload($item['key'], $user, $tenantId, $itemFilters);
        }

        return $out;
    }

    /**
     * Built-in layouts a user can load into the editor, for dashboards that ship several.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function presets(User $user, int $tenantId, string $dashboard): array
    {
        $sets = self::STARTERS[$dashboard] ?? [];

        if (count($sets) < 2) {
            return [];
        }

        return array_map(
            fn (array $keys) => $this->lay($keys, fn (string $key) => $this->registry->isAvailable($key, $user, $tenantId, $dashboard)),
            $sets,
        );
    }

    /**
     * The starter widgets of the modules a plan includes (null = all modules) — what a
     * tenant's default common layout is seeded with. Per-user permissions are applied when it is read.
     *
     * @param list<string>|null $modules
     * @return list<array<string, mixed>>
     */
    public function starterForModules(?array $modules): array
    {
        return $this->lay(self::STARTERS['common']['default'], function (string $key) use ($modules) {
            $module = $this->registry->find($key)['module'] ?? null;

            return $module !== null && ($modules === null || $module === 'platform' || in_array($module, $modules, true));
        });
    }

    /**
     * Title always; period and row count only for widgets that declare them.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $settings the widget's declared settings
     * @return array<string, mixed>
     */
    private function sanitizeConfig(array $config, array $settings): array
    {
        $clean = [];
        $title = trim((string) ($config['title'] ?? ''));

        if ($title !== '') {
            $clean['title'] = Str::limit($title, 60, '');
        }

        if (! empty($settings['period']) && array_key_exists((string) ($config['period'] ?? ''), Period::PRESETS) && $config['period'] !== 'custom') {
            $clean['period'] = $config['period'];
        }

        if (! empty($settings['limit']) && isset($config['limit'])) {
            $clean['limit'] = max(self::MIN_LIMIT, min(self::MAX_LIMIT, (int) $config['limit']));
        }

        return $clean;
    }

    /** @return list<array<string, mixed>> */
    private function starter(User $user, int $tenantId, string $dashboard, ?string $starter): array
    {
        $sets = self::STARTERS[$dashboard] ?? self::STARTERS['common'];
        $keys = $sets[$starter] ?? $sets[self::DEFAULT_STARTER[$dashboard] ?? 'default'];

        return $this->lay($keys, fn (string $key) => $this->registry->isAvailable($key, $user, $tenantId, $dashboard));
    }

    /**
     * Packs widgets left to right, wrapping at the grid width.
     *
     * @param list<string> $keys
     * @param callable(string): bool $wanted
     * @return list<array<string, mixed>>
     */
    private function lay(array $keys, callable $wanted): array
    {
        $x = $y = $rowHeight = 0;
        $widgets = [];

        foreach ($keys as $key) {
            if ($this->registry->find($key) === null || ! $wanted($key)) {
                continue;
            }

            $def = $this->registry->find($key);

            if ($x + $def['w'] > self::COLUMNS) {
                $x = 0;
                $y += $rowHeight;
                $rowHeight = 0;
            }

            $widgets[] = ['id' => $key, 'key' => $key, 'x' => $x, 'y' => $y, 'w' => $def['w'], 'h' => $def['h'], 'config' => []];
            $x += $def['w'];
            $rowHeight = max($rowHeight, $def['h']);
        }

        return $widgets;
    }

    /** @return list<int> */
    private function roleIds(User $user): array
    {
        $ids = UserRole::query()->where('user_id', $user->id)->pluck('role_id')->all();

        if ($user->role_id) {
            $ids[] = $user->role_id;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
