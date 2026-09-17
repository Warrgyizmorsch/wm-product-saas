<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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

        $user = Auth::user();
        if ($user->role_id && !$user->role) {
            $user->role = $user->primaryRole?->name;
        }
        $token = $user->createToken('api-token')->plainTextToken;
        $employee = \App\Domains\HRMS\Models\Employee::resolveForUser($user);

        $shift = null;
        if ($employee) {
            $shift = $employee->shift;

            if (!$shift) {
                // Check if today's ShiftRoster has a shift assigned
                $todayRoster = \App\Domains\HRMS\Models\ShiftRoster::with(['shift'])
                    ->where('employee_id', $employee->id)
                    ->whereDate('date', now()->toDateString())
                    ->first();
                if ($todayRoster && $todayRoster->shift) {
                    $shift = $todayRoster->shift;
                }
            }

            if (!$shift) {
                // Fallback to active company/general shift
                $shift = \App\Domains\Production\Models\ProductionShift::where('active', true)
                    ->where(function($q) use ($employee) {
                        if ($employee->company_id) {
                            $q->where('company_id', $employee->company_id)
                              ->orWhereNull('company_id');
                        }
                    })
                    ->first();
            }
        }

        return response()->json([
            'user' => $user,
            'employee_id' => $employee?->id,
            'shift' => $shift,
            'token' => $token,
        ]);
    }

    public function apiLogout(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out.'
        ]);
    }
}

