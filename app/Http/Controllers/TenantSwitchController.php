<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantSwitchController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->isAccessible(), 403, 'Tenant is not available.');

        $user = $request->user();
        abort_if($user?->tenant_id !== null && (int) $user->tenant_id !== (int) $tenant->id, 403, 'You are not assigned to this tenant.');

        $request->session()->put('tenant_slug', $tenant->slug);

        return redirect()
            ->back()
            ->with('success', 'Tenant switched to '.$tenant->name.'.');
    }
}
