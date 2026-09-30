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
        $this->authorizeHrms('hrms.employees.view');

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();

        $data = $this->probationRepository->getIndexData($request->all(), $user, $tenantId);

        return view('modules.hrms.employees.probation.index', $data);
    }

    public function evaluate(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.update');

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
        $this->authorizeHrms('hrms.employees.update');

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

    private function authorizeHrms(string $permission): void
    {
        $user = auth()->user();
        abort_unless(
            $user && app(\App\Services\Access\AccessService::class)->allows($user, $permission, [
                'tenant_id' => $user->tenant_id,
            ]),
            403
        );
    }
}
