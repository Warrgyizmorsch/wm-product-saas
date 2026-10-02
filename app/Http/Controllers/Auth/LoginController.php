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

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // On a shared host (localhost / a central domain) the URL doesn't identify
        // the tenant, so take it from the account being signed into. TenantResolver
        // reads it from the session on this and every following request, which also
        // lets TenantAwareUserProvider accept the user for their own tenant.
        if (! $request->hasHeader(config('tenancy.header'))
            && in_array($request->getHost(), config('tenancy.central_domains'), true)) {
            $tenantSlug = app(LoginTenantLocator::class)->slugFor($credentials['email'], $credentials['password']);

            if ($tenantSlug !== null) {
                $request->session()->put('tenant_slug', $tenantSlug);
            }
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
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

        if (! Auth::attempt($credentials)) {
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
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user'        => $user,
            'employee_id' => $employee?->id,
            'token'       => $token,
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
                'id'        => $user->id,
                'tenant_id' => $user->tenant_id,
                'name'      => $user->name,
                'email'     => $user->email,
                'role'      => $user->role,
                'avatar'    => $user->avatar_url,
            ],
            'employee' => $employee ? [
                'id'               => $employee->id,
                'employee_id'      => $employee->employee_id ?? null,
                'name'             => $employee->full_name,
                'email'            => $employee->office_email ?? $employee->personal_email ?? $user->email,
                'department_id'    => $employee->department_id,
                'department_name'  => $employee->department?->name,
                'designation_id'   => $employee->designation_id,
                'designation_name' => $employee->designation?->name,
            ] : null,
            'roles'              => $roles,
            'is_manager'         => $isManager,
            'is_admin'           => $isPlatformAdmin,
            'permissions'        => $permissions,
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

