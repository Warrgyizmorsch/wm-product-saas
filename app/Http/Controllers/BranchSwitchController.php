<?php

namespace App\Http\Controllers;

use App\Domains\HRMS\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchSwitchController extends Controller
{
    public function __invoke(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless((int) $branch->company_id === (int) company_id(), 403, 'Branch does not belong to the current company.');

        $request->session()->put('branch_id', $branch->id);

        return redirect()->back()->with('success', 'Branch switched to '.$branch->name.'.');
    }
}
