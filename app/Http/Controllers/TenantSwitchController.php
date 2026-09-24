<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantSwitchController extends Controller
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->isAccessible(), 403, 'Tenant is not available.');

        $user = $request->user();
        $ownsTenant = $user !== null && $user->tenant_id !== null && (int) $user->tenant_id === (int) $tenant->id;
        // Switching into another tenant is a super_admin-only capability, narrower than
        // platform.tenants.manage (which also covers plans/currencies/etc. for 'admin').
        $isSuperAdmin = $user !== null && $this->access->hasRole($user, 'super_admin');

        abort_unless($ownsTenant || $isSuperAdmin, 403, 'You are not assigned to this tenant.');

        $request->session()->put('tenant_slug', $tenant->slug);

        return redirect()
            ->back()
            ->with('success', 'Tenant switched to '.$tenant->name.'.');
    }
}
