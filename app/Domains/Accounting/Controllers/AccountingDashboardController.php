<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Services\AccountingDashboardService;
use App\Domains\Accounting\Support\DashboardPeriod;
use App\Domains\Accounting\Support\DashboardReport;
use App\Domains\HRMS\Models\Company;
use App\Domains\Platform\Services\DashboardService;
use App\Exports\AccountingDashboardExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Access\AccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class AccountingDashboardController extends Controller
{
    public const VIEWS = [
        'overview' => 'Overview',
        'operations' => 'Operations',
    ];

    /** People running the business see health and cash first. */
    private const OVERVIEW_ROLES = ['super_admin', 'tenant_owner', 'company_admin'];

    /** People keeping the books see the close checklist and compliance first. */
    private const OPERATIONS_ROLES = ['accountant', 'auditor'];

    public function __construct(
        private readonly AccountingDashboardService $dashboard,
        private readonly AccessService $access,
        private readonly DashboardService $layouts,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        [$tenantId, $filters] = $this->authorizeAndValidate($request);

        if ($request->boolean('refresh')) {
            $this->dashboard->refresh($tenantId);

            return redirect()->route('accounting.dashboard', $request->except('refresh'));
        }

        $user = $request->user();
        $activeView = $filters['view'] ?? $this->defaultView($user);

        // Every block on the page is a widget the user can rearrange; the page's own filters apply to all of them.
        $widgetQuery = array_filter([
            'preset' => $filters['preset'] ?? 'this_month',
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'scope' => ($filters['company_scope'] ?? 'current') === 'all' ? 'all' : null,
            'cost_center_id' => $filters['cost_center_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
        $state = $this->layouts->pageState($user, $tenantId, 'accounting', $activeView);

        return view('modules.accounting.dashboard', $this->build($tenantId, $filters) + $state + [
            'initial' => $this->layouts->preload($state['layout'], $user, $tenantId, $widgetQuery),
            'widgetQuery' => (object) $widgetQuery,
            'presets' => DashboardPeriod::PRESETS,
            'views' => self::VIEWS,
            'activeView' => $activeView,
            'costCenters' => CostCenter::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'companyCount' => Company::query()->count(),
            'canPostJournals' => $this->access->allows($user, 'accounting.journals.post', ['tenant_id' => $user->tenant_id]),
            'query' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        [$tenantId, $filters] = $this->authorizeAndValidate($request);

        $data = $this->build($tenantId, $filters);
        $filename = sprintf('AccountingDashboard_%s_%s', $data['period']->from->format('Ymd'), $data['period']->to->format('Ymd'));

        if ($format === 'pdf') {
            return Pdf::loadView('modules.accounting.dashboard-pdf', ['report' => DashboardReport::build($data)])
                ->download($filename.'.pdf');
        }

        return Excel::download(new AccountingDashboardExport($data), $filename.'.xlsx');
    }

    /**
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function authorizeAndValidate(Request $request): array
    {
        abort_unless($this->access->allows($request->user(), 'accounting.reports.view', [
            'tenant_id' => $request->user()->tenant_id,
        ]), 403);

        $tenantId = tenant_id() ?? $request->user()->tenant_id;
        abort_if($tenantId === null, 404);

        $filters = $request->validate([
            'preset' => ['nullable', Rule::in(array_keys(DashboardPeriod::PRESETS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'company_scope' => ['nullable', Rule::in(['current', 'all'])],
            'cost_center_id' => ['nullable', 'integer', Rule::exists('cost_centers', 'id')->where('tenant_id', $tenantId)],
            'view' => ['nullable', Rule::in(array_keys(self::VIEWS))],
        ]);

        return [$tenantId, $filters];
    }

    private function build(int $tenantId, array $filters): array
    {
        $today = Carbon::today();
        $consolidated = ($filters['company_scope'] ?? 'current') === 'all';

        $data = $this->dashboard->cachedSummary(
            $tenantId,
            DashboardPeriod::resolve($filters, $today, $this->dashboard->fiscalYearStart($today)),
            $consolidated,
            isset($filters['cost_center_id']) ? (int) $filters['cost_center_id'] : null,
            $today,
        );

        return $data + ['currencyNote' => $this->currencyNote($consolidated)];
    }

    /**
     * Consolidated totals only mean something when every company keeps its
     * books in the same currency — amounts are added, never converted.
     */
    private function currencyNote(bool $consolidated): ?string
    {
        if (! $consolidated) {
            return company() ? 'In '.company_currency()['code'] : null;
        }

        $currencies = Company::query()->whereNotNull('currency')->distinct()->pluck('currency')->map(fn ($code) => strtoupper($code))->unique();

        return $currencies->count() > 1
            ? 'Warning: companies use different currencies ('.$currencies->implode(', ').'); totals add them without conversion'
            : ($currencies->isNotEmpty() ? 'In '.$currencies->first() : null);
    }

    private function defaultView(User $user): string
    {
        foreach (self::OVERVIEW_ROLES as $role) {
            if ($this->access->hasRole($user, $role, tenant_id())) {
                return 'overview';
            }
        }

        foreach (self::OPERATIONS_ROLES as $role) {
            if ($this->access->hasRole($user, $role, tenant_id())) {
                return 'operations';
            }
        }

        return 'overview';
    }
}
