<?php

namespace App\Http\Controllers;

use App\Domains\HRMS\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanySwitchController extends Controller
{
    public function __invoke(Request $request, Company $company): RedirectResponse
    {
        abort_unless((int) $company->tenant_id === (int) tenant_id(), 403, 'Company does not belong to this tenant.');

        $request->session()->put('company_id', $company->id);
        $request->session()->forget('branch_id');

        return redirect()->back()->with('success', 'Company switched to '.$company->company_name.'.');
    }
}
