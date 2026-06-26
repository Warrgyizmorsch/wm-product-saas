<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantSwitchController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_if($tenant->status !== 'active', 403, 'Tenant is not active.');

        $request->session()->put('tenant_slug', $tenant->slug);

        return redirect()
            ->back()
            ->with('success', 'Tenant switched to '.$tenant->name.'.');
    }
}
