<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\EmployeeProfileUpdateRequest;
use App\Domains\HRMS\Repositories\EmployeeProfileRequestRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeProfileRequestController extends Controller
{
    public function __construct(
        private readonly EmployeeProfileRequestRepositoryInterface $profileRequestRepository
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizeHrms('hrms.employees.view');

        $data = $this->profileRequestRepository->getIndexData($request->all());

        return view('modules.hrms.employees.profile-requests.index', $data);
    }

    public function approve(Request $request, EmployeeProfileUpdateRequest $profileRequest): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.update');

        if ($profileRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'This request has already been processed.');
        }

        $employee = $profileRequest->employee;
        if (!$employee) {
            return redirect()->back()->with('error', 'Associated employee record not found.');
        }

        $success = $this->profileRequestRepository->approveRequest($profileRequest);

        if (!$success) {
            return redirect()->back()->with('error', 'Failed to process employee profile update.');
        }

        return redirect()->back()->with('success', "Profile edit request for {$employee->full_name} has been approved.");
    }

    public function reject(Request $request, EmployeeProfileUpdateRequest $profileRequest): RedirectResponse
    {
        $this->authorizeHrms('hrms.employees.update');

        if ($profileRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'This request has already been processed.');
        }

        $employee = $profileRequest->employee;
        $reason = $request->input('rejection_reason', 'Changes rejected by HR administrator.');

        $this->profileRequestRepository->rejectRequest($profileRequest, $reason);

        return redirect()->back()->with('success', "Profile edit request for {$employee?->full_name} has been rejected.");
    }

    private function authorizeHrms(string $permission): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        $context = ['tenant_id' => $user->tenant_id];
        $access = app(AccessService::class);

        $allowed = $access->allows($user, $permission, $context)
            || $access->allows($user, 'hrms.profile_requests.manage', $context)
            || $access->allows($user, 'hr.settings.manage', $context)
            || $access->allows($user, 'hrms.employees.manage', $context);

        abort_unless($allowed, 403, 'Unauthorized action in Employee Profile Requests.');
    }
}
