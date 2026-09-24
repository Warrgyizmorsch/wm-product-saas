<?php

namespace App\Core\Dashboard;

use App\Models\User;
use App\Services\Access\AccessService;

/**
 * Collects dashboard widgets from every module's Dashboard/widgets.php — discovered
 * the same way MenuRegistry discovers Routes/menu.php, so a module adds widgets by
 * adding one file.
 *
 * Each file returns a list of definitions:
 *
 *     ['key' => 'crm.open_leads', 'title' => 'Open Leads', 'module' => 'crm',
 *      'permission' => 'crm.leads.view', 'type' => 'kpi', 'icon' => 'feather-users',
 *      'w' => 3, 'h' => 2, 'description' => '...', 'data' => fn (WidgetContext $c): array => [...]]
 *
 * type decides the payload `data` must return:
 *  - kpi:   ['value' => string, 'sub' => ?string, 'tone' => ?string]
 *  - list:  ['rows' => [['label' => string, 'value' => string], ...]]
 *  - links: ['rows' => [['label' => string, 'url' => string], ...]]
 *  - donut: ['labels' => [...], 'values' => [...]]
 *  - bar:   ['labels' => [...], 'values' => [...]]
 *  - line:  ['labels' => [...], 'series' => [['name' => string, 'data' => [...]], ...]]
 *
 *  - html:  ['html' => string, 'charts' => [['id' => string, 'options' => array], ...]] — a block the
 *           module renders itself (used to keep a module dashboard's existing look)
 *
 * Optional keys: 'dashboards' (which dashboards offer the widget; default ['common']),
 * 'chrome' => false (the widget draws its own card), 'cache' => false, and
 * 'settings' => ['period' => true, 'limit' => true] lets people set a
 * date range and/or row count per widget; the callback reads them from WidgetContext.
 */
class WidgetRegistry
{
    public const TYPES = ['kpi', 'list', 'links', 'donut', 'bar', 'line', 'html'];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $widgets = null;

    public function __construct(private readonly AccessService $access)
    {
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        if ($this->widgets === null) {
            $this->widgets = [];

            foreach (glob(str_replace('/', DIRECTORY_SEPARATOR, app_path('Domains/*/Dashboard/widgets.php'))) ?: [] as $file) {
                foreach (require $file as $definition) {
                    $this->widgets[$definition['key']] = $definition + ['w' => 3, 'h' => 2, 'icon' => 'feather-bar-chart-2', 'description' => '', 'settings' => [], 'dashboards' => ['common'], 'chrome' => true, 'cache' => true];
                }
            }
        }

        return $this->widgets;
    }

    /** @return array<string, mixed>|null */
    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /** A widget is offered only when the plan includes its module and the user holds its permission. */
    public function isAvailable(string $key, User $user, int $tenantId, string $dashboard = 'common'): bool
    {
        $widget = $this->find($key);

        if ($widget === null || ! in_array($dashboard, $widget['dashboards'], true)) {
            return false;
        }

        $planModules = tenant_allowed_modules();

        // 'platform' widgets (approvals) belong to no plan-gated module.
        if ($planModules !== null && $widget['module'] !== 'platform' && ! in_array($widget['module'], $planModules, true)) {
            return false;
        }

        return $widget['permission'] === null
            || $this->access->allows($user, $widget['permission'], ['tenant_id' => $user->tenant_id]);
    }

    /** @return array<string, array<string, mixed>> */
    public function availableFor(User $user, int $tenantId, string $dashboard = 'common'): array
    {
        return array_filter($this->all(), fn (array $w) => $this->isAvailable($w['key'], $user, $tenantId, $dashboard));
    }
}
