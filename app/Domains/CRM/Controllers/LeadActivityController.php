<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\LeadFollowup;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LeadActivityController extends Controller
{
    public function index(Request $request)
    {
        // The activity calendar shows every lead's follow-ups — same access as the leads list.
        $this->authorize('viewAny', Lead::class);

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
            ->whereNotNull('lead_id')
            ->whereBetween('followup_date', [$monthStart->copy()->subDays(7), $monthEnd->copy()->addDays(7)])
            ->with(['lead.owner'])
            ->orderBy('followup_date', 'asc')
            ->get();

        $leads = Lead::where('tenant_id', $tenantId)->orderBy('company_name')->get();
        $users = \App\Models\User::orderBy('name')->get();

        $calService = app(\App\Domains\CRM\Services\GoogleCalendarIntegrationService::class);

        if ($request->filled('auth_code') || $request->filled('code')) {
            $authCode = $request->input('auth_code') ?: $request->input('code');
            $googleUserId = $request->input('google_user_id') ?: $request->input('user_id') ?: auth()->id();
            if ($googleUserId) {
                session(['google_user_id' => (int)$googleUserId]);
            }
            $calService->exchangeAuthCode($authCode, (int)$googleUserId);
            return redirect()->route('crm.activities.index')->with('success', 'Google Calendar connected successfully!');
        }

        if ($request->filled('token') || $request->filled('access_token')) {
            $token = $request->input('token') ?: $request->input('access_token');
            $googleUserId = $request->input('google_user_id') ?: $request->input('user_id') ?: auth()->id();
            session([
                'google_token' => $token,
                'google_user_id' => (int)$googleUserId,
                'google_calendar_connected' => true,
            ]);

            if (auth()->check()) {
                $user = auth()->user();
                $settings = is_array($user->settings) ? $user->settings : (json_decode($user->settings ?? '{}', true) ?: []);
                $settings['google_token'] = $token;
                $settings['google_user_id'] = (int)$googleUserId;
                $user->settings = $settings;
                $user->saveQuietly();
            }

            return redirect()->route('crm.activities.index')->with('success', 'Google Calendar connected successfully!');
        }

        if ($request->filled('user_id') || $request->filled('google_user_id')) {
            session(['google_user_id' => (int)($request->input('google_user_id') ?: $request->input('user_id'))]);
        }

        $isGoogleConnected = $calService->isAccountConnected(auth()->id());

        return view('modules.crm.activities.index', compact('followups', 'leads', 'users', 'view', 'startDate', 'monthStart', 'monthEnd', 'isGoogleConnected'));
    }
}
