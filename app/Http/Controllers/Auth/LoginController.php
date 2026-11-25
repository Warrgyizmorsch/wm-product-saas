<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

use App\Core\Tenant\LoginTenantLocator;

class LoginController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function showResetForm(Request $request, ?string $token = null): View
    {
        return view('auth.reset-password', [
            'token' => $token ?? $request->route('token'),
            'email' => $request->input('email'),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = \Illuminate\Support\Facades\Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => \Illuminate\Support\Facades\Hash::make($password),
                ])->setRememberToken(\Illuminate\Support\Str::random(60));
                $user->save();
            }
        );

        return $status === \Illuminate\Support\Facades\Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', __('Your password has been set successfully! Please log in.'))
            : back()->withErrors(['email' => __($status)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // On shared hosts, central domains, or any common login entry where the URL
        // doesn't isolate the tenant, resolve the tenant from the user account credentials.
        if (! $request->hasHeader(config('tenancy.header'))) {
            $tenantSlug = app(LoginTenantLocator::class)->slugFor($credentials['email'], $credentials['password']);

            if ($tenantSlug !== null) {
                $request->session()->put('tenant_slug', $tenantSlug);

                $resolvedTenant = \App\Models\Tenant::query()
                    ->where('slug', $tenantSlug)
                    ->whereIn('status', \App\Models\Tenant::accessibleStatuses())
                    ->first();

                if ($resolvedTenant !== null) {
                    app(\App\Core\Tenant\TenantContext::class)->set($resolvedTenant);
                    app(\App\Support\Tenancy::class)->set($resolvedTenant);
                }
            }
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user && $user->isDeactivated()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => __('This account has been deactivated. Please contact your workspace administrator to reactivate your access.')])
                ->onlyInput('email');
        }

        if ($user) {
            $user->syncWithEmployee();
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function apiLogin(Request $request): \Illuminate\Http\JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Login runs outside the `tenant` middleware, so an unknown tenant header
        // would otherwise be ignored and a platform admin signed in regardless.
        if (filled($request->header(config('tenancy.header')))
            && app(\App\Core\Tenant\TenantResolver::class)->resolve($request) === null) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'These credentials do not match our records.'
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user && $user->isDeactivated()) {
            Auth::logout();
            return response()->json([
                'message' => __('This account has been deactivated. Please contact your workspace administrator.')
            ], 403);
        }

        if ($user->role_id && !$user->role) {
            $user->role = $user->primaryRole?->name;
        }
        $employee = \App\Domains\HRMS\Models\Employee::resolveForUser($user);
        if ($employee) {
            $user->syncWithEmployee($employee);
        }
        $user->unsetRelation('employee');
        $user->loadMissing('primaryRole');

        // Provide fallback default company_id & branch_id for the tenant if null
        if (empty($user->company_id)) {
            $defaultCompanyId = \App\Domains\HRMS\Models\Company::where('tenant_id', $user->tenant_id)->where('is_default', 1)->value('id')
                ?: \App\Domains\HRMS\Models\Company::where('tenant_id', $user->tenant_id)->orderBy('id')->value('id')
                ?: \App\Domains\HRMS\Models\Company::orderBy('id')->value('id');
            if ($defaultCompanyId) {
                $user->company_id = (int) $defaultCompanyId;
            }
        }

        if (empty($user->branch_id)) {
            $defaultBranchId = \App\Domains\HRMS\Models\Branch::where('tenant_id', $user->tenant_id)
                ->when($user->company_id, fn($q) => $q->where('company_id', $user->company_id))
                ->where('is_default', 1)->value('id')
                ?: \App\Domains\HRMS\Models\Branch::where('tenant_id', $user->tenant_id)
                    ->when($user->company_id, fn($q) => $q->where('company_id', $user->company_id))
                    ->orderBy('id')->value('id')
                ?: \App\Domains\HRMS\Models\Branch::where('tenant_id', $user->tenant_id)->orderBy('id')->value('id')
                ?: \App\Domains\HRMS\Models\Branch::orderBy('id')->value('id');
            if ($defaultBranchId) {
                $user->branch_id = (int) $defaultBranchId;
            }
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'employee_id' => $employee?->id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'token' => $token,
        ]);
    }

    /**
     * Authenticated endpoint to return current user profile, roles, permissions, and employee info.
     */
    public function apiMe(Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user->role_id && !$user->role) {
            $user->role = $user->primaryRole?->name;
        }

        $employee = \App\Domains\HRMS\Models\Employee::resolveForUser($user);
        if ($employee) {
            $employee->loadMissing(['department', 'designation']);
        }

        return response()->json($this->buildAuthPayload($user, $employee));
    }

    /**
     * Build standard user authorization profile for API responses.
     */
    protected function buildAuthPayload(User $user, ?\App\Domains\HRMS\Models\Employee $employee): array
    {
        $accessService = app(\App\Services\Access\AccessService::class);
        $roleIds = $accessService->effectiveRoleIds($user, $user->tenant_id);

        $roles = \App\Models\Access\Role::whereIn('id', $roleIds)->pluck('name')->toArray();
        if (empty($roles) && !empty($user->role)) {
            $roles = [$user->role];
        }

        $isPlatformAdmin = (bool) (
            $user->is_admin
            || in_array('super_admin', \App\Models\Access\Role::whereIn('id', $roleIds)->pluck('slug')->toArray())
            || in_array(strtolower($user->role ?? ''), ['admin', 'super_admin', 'super admin'])
        );

        $permissions = [];
        if ($isPlatformAdmin) {
            $permissions = ['*'];
        } elseif (!empty($roleIds)) {
            $permissions = \App\Models\Access\RolePermission::whereIn('role_id', $roleIds)
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->pluck('permissions.name')
                ->unique()
                ->values()
                ->toArray();
        }

        $isManager = false;
        if ($employee) {
            $isManager = \App\Domains\HRMS\Models\Employee::where('tenant_id', $employee->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : 1) ?? 1)
                ->where('reporting_manager_id', $employee->id)
                ->exists();
        }

        $accessibleModules = $accessService->allowedModulesFor($user) ?? ['hrms', 'purchase', 'sales', 'crm', 'inventory', 'accounting', 'production', 'projects'];

        $payload = [
            'user' => [
                'id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar_url,
            ],
            'employee' => $employee ? [
                'id' => $employee->id,
                'employee_id' => $employee->employee_id ?? null,
                'name' => $employee->full_name,
                'email' => $employee->office_email ?? $employee->personal_email ?? $user->email,
                'department_id' => $employee->department_id,
                'department_name' => $employee->department?->name,
                'designation_id' => $employee->designation_id,
                'designation_name' => $employee->designation?->name,
            ] : null,
            'roles' => $roles,
            'is_manager' => $isManager,
            'is_admin' => $isPlatformAdmin,
            'permissions' => $permissions,
            'accessible_modules' => $accessibleModules,
        ];

        return $payload;
    }

    public function apiLogout(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out.'
        ]);
    }
}

