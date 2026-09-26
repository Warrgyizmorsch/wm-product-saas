<?php

namespace App\Http\Controllers;

use App\Models\Access\AccessAuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        protected AccountService $accountService,
        protected ?\App\Domains\HRMS\Repositories\EmployeeRepository $employeeRepository = null
    ) {
        if (class_exists(\App\Domains\HRMS\Repositories\EmployeeRepository::class) && !$this->employeeRepository) {
            $this->employeeRepository = app(\App\Domains\HRMS\Repositories\EmployeeRepository::class);
        }
    }

    /**
     * Display the universal, module-agnostic Profile detail page.
     */
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['primaryRole', 'company', 'branch']);

        $tenant = tenant() ?? $user->tenant ?? Tenant::find($user->tenant_id);

        $employee = null;
        $employeeData = [];
        if (class_exists(\App\Domains\HRMS\Models\Employee::class)) {
            $employee = $user->relationLoaded('employee')
                ? $user->employee
                : \App\Domains\HRMS\Models\Employee::resolveForUser($user);

            if ($employee && $this->employeeRepository) {
                $employeeData = $this->employeeRepository->getProfileData($employee, $request->all());
            }
        }

        $recentActivity = collect();
        if (class_exists(AccessAuditLog::class) && $user->tenant_id) {
            $recentActivity = AccessAuditLog::query()
                ->where('tenant_id', $user->tenant_id)
                ->where(function ($q) use ($user) {
                    $q->where('actor_id', $user->id)
                      ->orWhere('target_user_id', $user->id);
                })
                ->latest('id')
                ->take(15)
                ->get();
        }

        $viewData = array_merge($employeeData, compact('user', 'tenant', 'employee', 'recentActivity'));

        return view('profile.show', $viewData);
    }

    /**
     * Update the authenticated user's profile details.
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'  => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
        ]);

        $this->accountService->updateProfile($user, $validated, $request->file('avatar'));

        return redirect()->route('profile.show')->with('success', __('Profile updated successfully.'));
    }

    /**
     * Reset / change the authenticated user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed', Password::default()],
        ]);

        $this->accountService->updatePassword($user, $validated['password']);

        return redirect()->route('profile.show', ['tab' => 'security'])
            ->with('success', __('Password changed successfully.'));
    }
}
