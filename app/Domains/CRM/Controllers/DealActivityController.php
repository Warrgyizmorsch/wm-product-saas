<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\LeadFollowup;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DealActivityController extends Controller
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
            ->whereNotNull('crm_deal_id')
            ->whereBetween('followup_date', [$monthStart->copy()->subDays(7), $monthEnd->copy()->addDays(7)])
            ->with(['deal.account', 'deal.contact'])
            ->orderBy('followup_date', 'asc')
            ->get();

        $deals = CrmDeal::where('tenant_id', $tenantId)->with(['account', 'contact'])->orderBy('title')->get();
        $users = \App\Models\User::orderBy('name')->get();

        if ($request->has('google_connected') || $request->has('connected') || $request->has('user_id')) {
            session(['google_calendar_connected' => true]);
        }

        $calService = app(\App\Domains\CRM\Services\GoogleCalendarIntegrationService::class);
        $isGoogleConnected = session('google_calendar_connected', false) || $calService->isAccountConnected(auth()->id());

        return view('modules.crm.deals.activities', compact('followups', 'deals', 'users', 'view', 'startDate', 'monthStart', 'monthEnd', 'isGoogleConnected'));
    }
}
