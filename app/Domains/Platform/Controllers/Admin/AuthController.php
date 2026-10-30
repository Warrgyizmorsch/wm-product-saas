<?php

namespace App\Domains\Platform\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Sign-in for the /admin platform console. Anyone can attempt it; only accounts
 * AccessService::canUsePlatformConsole accepts stay signed in.
 */
class AuthController extends Controller
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->isPlatformStaff($request->user())) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login', [
            'loginAction' => route('admin.login.store'),
            'loginBadge' => 'Platform Console',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        if (! $this->isPlatformStaff(Auth::user())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'This account does not have access to the platform console.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function isPlatformStaff($user): bool
    {
        return $user !== null && $this->access->canUsePlatformConsole($user);
    }
}
