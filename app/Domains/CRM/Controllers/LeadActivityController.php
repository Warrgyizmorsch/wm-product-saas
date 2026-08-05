<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadFollowup;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LeadActivityController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $view = $request->input('view', 'month'); // month, week, day
        $startParam = $request->input('start', now()->toDateString());
        
        $startDate = Carbon::parse($startParam);

        if ($view === 'month') {
            $monthStart = $startDate->copy()->startOfMonth();
            $monthEnd = $startDate->copy()->endOfMonth();
        } elseif ($view === 'week') {
            $monthStart = $startDate->copy()->startOfWeek();
            $monthEnd = $startDate->copy()->endOfWeek();
        } else {
            $monthStart = $startDate->copy()->startOfDay();
            $monthEnd = $startDate->copy()->endOfDay();
        }

        $followups = LeadFollowup::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('followup_date', [$monthStart->copy()->subDays(7), $monthEnd->copy()->addDays(7)])
            ->with(['lead'])
            ->orderBy('followup_date', 'asc')
            ->get();

        $leads = Lead::where('tenant_id', $tenantId)->orderBy('company_name')->get();
        $users = \App\Models\User::orderBy('name')->get();

        return view('modules.crm.activities.index', compact('followups', 'leads', 'users', 'view', 'startDate', 'monthStart', 'monthEnd'));
    }
}
