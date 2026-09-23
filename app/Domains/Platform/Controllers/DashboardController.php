<?php

namespace App\Domains\Platform\Controllers;

use App\Core\Dashboard\Period;
use App\Core\Dashboard\WidgetRegistry;
use App\Core\Navigation\MenuBuilder;
use App\Domains\HRMS\Models\Company;
use App\Domains\Platform\Requests\SaveDashboardLayoutRequest;
use App\Domains\Platform\Services\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The common dashboard, plus the layout and widget-data endpoints every customizable dashboard uses. */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly WidgetRegistry $registry,
        private readonly MenuBuilder $menu,
    ) {
    }

    public function index(Request $request): View
    {
        return view('modules.dashboard.index', $this->dashboard->pageState($request->user(), $this->tenantId($request), 'common') + [
            'multiCompany' => Company::query()->withoutGlobalScopes()->where('tenant_id', $this->tenantId($request))->count() > 1,
        ]);
    }

    /** The Apps grid: every module the user can open, for jumping in from the workspace. */
    public function apps(Request $request): View
    {
        return view('modules.dashboard.apps', [
            'apps' => $this->menu->navigation($request->user())['apps'],
        ]);
    }

    public function widget(Request $request, string $key): JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->tenantId($request);

        $filters = $request->validate([
            'dashboard' => ['nullable', Rule::in(DashboardService::DASHBOARDS)],
            'preset' => ['nullable', Rule::in(array_keys(Period::PRESETS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'scope' => ['nullable', Rule::in(['current', 'all'])],
            'limit' => ['nullable', 'integer', 'min:'.DashboardService::MIN_LIMIT, 'max:'.DashboardService::MAX_LIMIT],
            'cost_center_id' => ['nullable', 'integer', Rule::exists('cost_centers', 'id')->where('tenant_id', $tenantId)],
        ]);

        abort_unless($this->registry->isAvailable($key, $user, $tenantId, $filters['dashboard'] ?? 'common'), 404);

        unset($filters['dashboard']);
        $data = $this->dashboard->payload($key, $user, $tenantId, $filters);

        // One failing widget must not break the page: it shows its own error state.
        return $data === null
            ? response()->json(['error' => 'This widget could not be loaded.'], 500)
            : response()->json($data);
    }

    public function save(SaveDashboardLayoutRequest $request): JsonResponse
    {
        $scope = $request->validated('scope');

        abort_if($scope !== 'personal' && ! $this->dashboard->canManage($request->user()), 403);

        $this->dashboard->save(
            $request->user(),
            $this->tenantId($request),
            $request->validated('widgets'),
            $scope,
            $scope === 'role' ? (int) $request->validated('role_id') : null,
            $request->validated('dashboard') ?? 'common',
        );

        return response()->json(['saved' => true]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate(['dashboard' => ['nullable', Rule::in(DashboardService::DASHBOARDS)]]);

        $this->dashboard->reset($request->user(), $request->input('dashboard', 'common'));

        return response()->json(['reset' => true]);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = tenant_id() ?? $request->user()->tenant_id;
        abort_if($tenantId === null, 404);

        return (int) $tenantId;
    }
}
