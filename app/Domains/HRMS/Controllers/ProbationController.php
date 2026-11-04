<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Repositories\ProbationRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProbationController extends Controller
{
    public function __construct(
        private readonly ProbationRepositoryInterface $probationRepository
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeHrms(['hrms.probation.manage', 'hrms.employees.view', 'hrms.employees.manage', 'hr.settings.manage']);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();

        $data = $this->probationRepository->getIndexData($request->all(), $user, $tenantId);

        return view('modules.hrms.employees.probation.index', $data);
    }

    public function evaluate(Request $request, Employee $employee): RedirectResponse
    {
        $user = auth()->user();
        $canEvaluate = $user && (
            $this->checkHrmsPermission(['hrms.probation.manage', 'hrms.employees.update', 'hr.settings.manage']) ||
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->canAccessEmployee($user, $employee)
        );
        abort_unless($canEvaluate, 403, 'Unauthorized to evaluate probation for this employee.');

        $validated = $request->validate([
            'performance_rating' => 'required|integer|min:1|max:5',
            'attendance_rating' => 'required|integer|min:1|max:5',
            'culture_rating' => 'required|integer|min:1|max:5',
            'recommendation' => 'required|string|in:confirm,extend,terminate',
            'extension_days' => 'nullable|required_if:recommendation,extend|integer|min:1|max:180',
            'termination_mode' => 'nullable|required_if:recommendation,terminate|string|in:immediate,notice',
            'termination_notice_days' => 'nullable|integer|min:0|max:90',
            'termination_reason_category' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $msg = $this->probationRepository->evaluate($employee, $validated, auth()->id(), $tenantId);

        return redirect()->back()->with('success', $msg);
    }

    public function quickConfirm(Request $request, Employee $employee): RedirectResponse
    {
        $user = auth()->user();
        $canConfirm = $user && (
            $this->checkHrmsPermission(['hrms.probation.manage', 'hrms.employees.update', 'hr.settings.manage']) ||
            app(\App\Domains\HRMS\Services\HrmsScopeService::class)->canAccessEmployee($user, $employee)
        );
        abort_unless($canConfirm, 403, 'Unauthorized to confirm probation for this employee.');

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $msg = $this->probationRepository->quickConfirm(
            $employee,
            $request->input('confirmation_date'),
            $request->input('remarks'),
            auth()->id(),
            $tenantId
        );

        return redirect()->back()->with('success', $msg);
    }

    private function checkHrmsPermission(string|array $permission): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        $context = [
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'department_id' => $user->department_id,
            'owner_id' => $user->id,
        ];
        $access = app(\App\Services\Access\AccessService::class);
        $permissions = is_array($permission) ? $permission : [$permission];

        foreach ($permissions as $perm) {
            if ($access->allows($user, $perm, $context)) {
                return true;
            }
        }

        if (app(\App\Domains\HRMS\Services\HrmsScopeService::class)->isCompanyAdmin($user)) {
            return true;
        }

        return false;
    }

    private function authorizeHrms(string|array $permission): void
    {
        abort_unless($this->checkHrmsPermission($permission), 403, 'Unauthorized action in Probation Management.');
    }
}
