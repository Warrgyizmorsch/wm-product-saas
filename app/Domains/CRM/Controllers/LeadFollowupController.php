<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Services\LeadFollowupService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LeadFollowupController extends Controller
{
    public function __construct(
        private readonly LeadFollowupService $followupService
    ) {
    }

    public function store(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'followup_date' => 'nullable|string',
            'type' => 'nullable|string',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
            'lead_status' => 'nullable|string',
            'priority' => 'nullable|string',
            'segment' => 'nullable|string',
            'sub_status' => 'nullable|string',
            'tagged_user_id' => 'nullable|exists:users,id',
            'tagged_user_ids' => 'nullable|array',
            'tagged_user_ids.*' => 'nullable|exists:users,id',
            'recording' => 'nullable|file|max:20480',
            'next_activity_type' => 'nullable|string',
            'next_followup_date' => 'nullable|string',
        ]);

        $actionMode = $request->input('action_mode', 'log_note');

        if (!empty($validated['lead_status'])) {
            $lead->status = $validated['lead_status'];
        }
        if (!empty($validated['priority'])) {
            $lead->priority = $validated['priority'];
        }
        if (!empty($validated['segment'])) {
            $lead->segment = $validated['segment'];
        }
        if ($lead->isDirty()) {
            $lead->save();
        }

        if ($request->hasFile('recording')) {
            app(\App\Domains\CRM\Services\LeadService::class)->uploadDocuments($lead, [$request->file('recording')]);
        }

        if ($actionMode === 'schedule') {
            $scheduleType = $request->input('schedule_type') ?: ($validated['type'] ?? 'Call');
            $dueDate = $request->input('followup_date') ?: date('Y-m-d H:i');
            $notes = $request->input('schedule_notes') ?: $validated['notes'];

            $this->followupService->storeFollowup($lead, [
                'type' => $scheduleType,
                'title' => $request->input('title'),
                'duration_minutes' => $request->input('duration_minutes'),
                'guest_emails' => $request->input('guest_emails'),
                'status' => 'Pending',
                'followup_date' => $dueDate,
                'notes' => $notes,
                'tagged_user_ids' => $validated['tagged_user_ids'] ?? null,
                'sync_google_calendar' => $request->boolean('sync_google_calendar'),
                'create_meet_link' => $request->boolean('create_meet_link'),
            ]);

            try {
                $lead->next_followup_date = \Illuminate\Support\Carbon::parse($dueDate);
            } catch (\Exception $e) {
            }
        } else {
            $pastType = $validated['type'] ?? 'Call';
            $pastStatus = $validated['status'] ?? 'Connected';
            $pastNotes = trim($validated['notes'] ?? '');

            // 1. Create Past Interaction Log (Status: Connected / Not Connected / Completed) - No tagged users on past log
            $this->followupService->storeFollowup($lead, [
                'type' => $pastType,
                'status' => $pastStatus,
                'followup_date' => date('Y-m-d H:i'),
                'notes' => $pastNotes,
                'tagged_user_ids' => null,
            ]);

            // 2. Create Next Scheduled Activity Log (Status: Pending) - Includes past notes + pipe separator + context suffix
            $nextDate = $request->input('next_followup_date');
            if (!empty($nextDate)) {
                $nextType = $request->input('next_activity_type') ?: 'Call';
                $contextSuffix = "Scheduled " . $nextType . " after " . strtolower($pastType) . " interaction";
                $nextNotes = !empty($pastNotes) ? ($pastNotes . " | " . $contextSuffix) : $contextSuffix;

                $this->followupService->storeFollowup($lead, [
                    'type' => $nextType,
                    'title' => $request->input('next_title') ?: ($request->input('title') ?: ("Next " . $nextType . " with " . ($lead->company_name ?: $lead->contact_person))),
                    'duration_minutes' => $request->input('next_duration_minutes') ?: ($request->input('duration_minutes') ?: 30),
                    'guest_emails' => $request->input('next_guest_emails') ?: $request->input('guest_emails'),
                    'status' => 'Pending',
                    'followup_date' => $nextDate,
                    'notes' => $nextNotes,
                    'tagged_user_ids' => $validated['tagged_user_ids'] ?? null,
                    'sync_google_calendar' => $request->has('next_sync_google_calendar') ? $request->boolean('next_sync_google_calendar') : ($request->has('sync_google_calendar') ? $request->boolean('sync_google_calendar') : false),
                    'create_meet_link' => $request->has('next_create_meet_link') ? $request->boolean('next_create_meet_link') : $request->boolean('create_meet_link'),
                ]);

                try {
                    $lead->next_followup_date = \Illuminate\Support\Carbon::parse($nextDate);
                } catch (\Exception $e) {
                }
            }
        }

        if ($lead->isDirty()) {
            $lead->save();
        }

        return redirect()->back()->with('success', 'Follow-up / Activity updated successfully!');
    }

    public function update(Request $request, LeadFollowup $followup)
    {
        if ($followup->lead) {
            $this->authorize('update', $followup->lead);
        }

        $tenant = tenant();
        $settings = is_array($tenant?->settings) ? $tenant->settings : [];
        $isGeoVerificationEnabled = !empty($settings['field_visit_verification']);

        $targetStatus = $request->input('status', $followup->status);
        $activityType = $request->input('type', $followup->type);
        $isCompletingMeeting = in_array($targetStatus, ['Completed', 'Not Connected']) && strcasecmp((string) $activityType, 'Meeting') === 0;

        $rules = [
            'status' => 'nullable|string|in:Pending,Completed,Not Connected,Cancelled,Rescheduled',
            'notes' => 'nullable|string',
            'type' => 'nullable|string|in:Call,Email,Meeting,Demo,Task',
            'followup_date' => 'nullable|string',
            'tagged_user_id' => 'nullable|exists:users,id',
            'tagged_user_ids' => 'nullable|array',
            'tagged_user_ids.*' => 'nullable|exists:users,id',
            'is_reschedule' => 'nullable|boolean',
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_address' => ['nullable', 'string', 'max:500'],
            'selfie' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'selfie_base64' => ['nullable', 'string'],
        ];

        // Controller-side strict enforcement only when setting is enabled
        if ($isGeoVerificationEnabled && $isCompletingMeeting) {
            $rules['latitude'] = ['required', 'numeric', 'between:-90,90'];
            $rules['longitude'] = ['required', 'numeric', 'between:-180,180'];

            // Selfie is strictly required if not already uploaded/captured
            if (!$followup->selfie_path && !$request->hasFile('selfie') && !$request->filled('selfie_base64')) {
                $rules['selfie_base64'] = ['required', 'string'];
            }
        }

        $messages = [
            'latitude.required' => 'Live GPS location is required to complete this meeting.',
            'longitude.required' => 'Live GPS coordinates are required to complete this meeting.',
            'selfie.required' => 'A live front camera selfie is required to complete this meeting.',
            'selfie_base64.required' => 'A live front camera selfie is required to complete this meeting.',
        ];

        $validated = $request->validate($rules, $messages);

        if ($request->filled('selfie_base64')) {
            $base64Data = (string) $request->input('selfie_base64');
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
                $imageType = strtolower($type[1]);
                if (in_array($imageType, ['jpeg', 'jpg', 'png', 'webp'])) {
                    $data = substr($base64Data, strpos($base64Data, ',') + 1);
                    $decoded = base64_decode($data);
                    if ($decoded !== false && strlen($decoded) <= 5242880) { // Max 5MB
                        $filename = \Illuminate\Support\Str::uuid() . '.' . ($imageType === 'jpeg' ? 'jpg' : $imageType);
                        \Illuminate\Support\Facades\Storage::disk('public')->put('crm/selfies/' . $filename, $decoded);
                        $validated['selfie_path'] = 'storage/crm/selfies/' . $filename;
                        $validated['location_verified_at'] = now();
                    }
                }
            }
        } elseif ($request->hasFile('selfie')) {
            $file = $request->file('selfie');
            $filename = \Illuminate\Support\Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('crm/selfies', $filename, 'public');
            $validated['selfie_path'] = 'storage/' . $path;
            $validated['location_verified_at'] = now();
        }

        if ($request->filled('latitude')) {
            $validated['latitude'] = (float) $request->input('latitude');
        }
        if ($request->filled('longitude')) {
            $validated['longitude'] = (float) $request->input('longitude');
        }
        if ($request->filled('location_address')) {
            $validated['location_address'] = $request->input('location_address');
        }
        if ($request->filled('latitude') && empty($validated['location_verified_at'])) {
            $validated['location_verified_at'] = now();
        }

        $msg = $this->followupService->updateOrReschedule($followup, $validated, $request->boolean('is_reschedule'));

        return redirect()->back()->with('success', $msg);
    }

    public function destroy(LeadFollowup $followup)
    {
        if ($followup->lead) {
            $this->authorize('update', $followup->lead);
        }

        $this->followupService->deleteFollowup($followup);

        return redirect()->back()->with('success', 'Follow-up successfully deleted!');
    }

    public function storeDealFollowup(Request $request, CrmDeal $deal)
    {
        $validated = $request->validate([
            'followup_date' => 'nullable|string',
            'type' => 'nullable|string',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
            'stage' => 'nullable|string',
            'title' => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'guest_emails' => 'nullable|string',
            'tagged_user_id' => 'nullable|exists:users,id',
            'tagged_user_ids' => 'nullable|array',
            'tagged_user_ids.*' => 'nullable|exists:users,id',
            'next_activity_type' => 'nullable|string',
            'next_followup_date' => 'nullable|string',
        ]);

        $actionMode = $request->input('action_mode', 'log_note');

        if (!empty($validated['stage'])) {
            $isCurrentlyWon = in_array(strtolower((string) $deal->stage), ['won', 'closed won']);
            if (!$isCurrentlyWon) {
                $targetStage = $validated['stage'];
                if (in_array(strtolower($targetStage), ['won', 'closed won'])) {
                    $hasAcceptedQuote = $deal->quotations()->where('status', 'Accepted')->exists();
                    $hasCustomer = !empty($deal->account?->customer_id);
                    if ($hasAcceptedQuote && $hasCustomer) {
                        $deal->stage = 'Won';
                        $deal->save();
                    }
                } else {
                    $deal->stage = $targetStage;
                    $deal->save();
                }
            }
        }

        $tenantId = tenant_id() ?? $deal->tenant_id ?? 1;

        if ($actionMode === 'schedule') {
            $scheduleType = $request->input('schedule_type') ?: ($validated['type'] ?? 'Call');
            $dueDate = $request->input('followup_date') ?: date('Y-m-d H:i');
            $notes = $request->input('schedule_notes') ?: $validated['notes'];

            try {
                $followupDateTime = \Illuminate\Support\Carbon::parse($dueDate);
            } catch (\Exception $e) {
                $followupDateTime = \Illuminate\Support\Carbon::now();
            }

            $taggedUserIds = !empty($validated['tagged_user_ids']) ? array_values(array_filter($validated['tagged_user_ids'])) : null;
            $primaryTaggedId = !empty($taggedUserIds) ? $taggedUserIds[0] : ($validated['tagged_user_id'] ?? null);

            $durationMinutes = !empty($request->input('duration_minutes')) ? (int) $request->input('duration_minutes') : 30;
            $guestEmailsInput = !empty($request->input('guest_emails')) ? trim($request->input('guest_emails')) : null;
            $titleInput = !empty($request->input('title')) ? trim($request->input('title')) : ($scheduleType . " with " . ($deal->title ?: 'Deal #' . $deal->id));

            $followup = LeadFollowup::create([
                'tenant_id' => $tenantId,
                'crm_deal_id' => $deal->id,
                'lead_id' => null,
                'followup_date' => $followupDateTime,
                'type' => $scheduleType,
                'title' => $titleInput,
                'duration_minutes' => $durationMinutes,
                'guest_emails' => $guestEmailsInput,
                'status' => 'Pending',
                'notes' => $notes,
                'tagged_user_id' => $primaryTaggedId,
                'tagged_user_ids' => $taggedUserIds,
            ]);

            $syncGoogle = $request->boolean('sync_google_calendar') || $request->boolean('create_meet_link');
            if ($syncGoogle) {
                try {
                    $createMeet = $request->boolean('create_meet_link');
                    $clientEmail = !empty($deal->contact?->email) ? trim($deal->contact->email) : (!empty($deal->account?->email) ? trim($deal->account->email) : null);
                    if ($clientEmail) {
                        $attendees[] = $clientEmail;
                    }
                    if (auth()->check() && auth()->user()?->email) {
                        $attendees[] = auth()->user()->email;
                    }
                    if (!empty($taggedUserIds)) {
                        $taggedEmails = \App\Models\User::whereIn('id', $taggedUserIds)->pluck('email')->filter()->toArray();
                        $attendees = array_merge($attendees, $taggedEmails);
                    }
                    if (!empty($guestEmailsInput)) {
                        $extraEmails = array_map('trim', explode(',', $guestEmailsInput));
                        $attendees = array_merge($attendees, $extraEmails);
                    }
                    $attendees = array_unique(array_values(array_filter($attendees)));

                    $calService = app(\App\Domains\CRM\Services\GoogleCalendarIntegrationService::class);
                    $res = $calService->createEvent([
                        'summary' => $titleInput,
                        'description' => $notes ?? '',
                        'start_time' => $followupDateTime->toIso8601String(),
                        'end_time' => (clone $followupDateTime)->addMinutes($durationMinutes)->toIso8601String(),
                        'attendees' => $attendees,
                        'create_meet_link' => $createMeet,
                        'deal_id' => $deal->id,
                    ]);

                    if (!empty($res['success'])) {
                        if (\Schema::hasColumn('lead_followups', 'google_event_id')) {
                            $followup->google_event_id = $res['google_event_id'] ?? null;
                        }
                        if (\Schema::hasColumn('lead_followups', 'google_meet_link')) {
                            $followup->google_meet_link = $createMeet ? ($res['meet_link'] ?? null) : null;
                        }
                        if (\Schema::hasColumn('lead_followups', 'is_google_meet')) {
                            $followup->is_google_meet = $createMeet && !empty($res['meet_link']);
                        }
                        if ($createMeet && !empty($res['meet_link'])) {
                            $followup->notes = ($followup->notes ? $followup->notes . "\n" : '') . "Google Meet: " . $res['meet_link'];
                        }
                    } else {
                        if (\Schema::hasColumn('lead_followups', 'google_event_id')) {
                            $followup->google_event_id = null;
                        }
                        if (\Schema::hasColumn('lead_followups', 'google_meet_link')) {
                            $followup->google_meet_link = null;
                        }
                        if (\Schema::hasColumn('lead_followups', 'is_google_meet')) {
                            $followup->is_google_meet = false;
                        }
                        session()->flash('google_sync_warning', $res['message'] ?? 'Google Calendar / Meet could not be synchronized.');
                        if (!empty($res['auth_url'])) {
                            session()->flash('google_auth_url', $res['auth_url']);
                        }
                    }
                    $followup->save();
                } catch (\Throwable $ex) {
                    \Illuminate\Support\Facades\Log::warning('Google Calendar auto-sync notice for deal: ' . $ex->getMessage());
                    session()->flash('google_sync_warning', 'Google Calendar auto-sync failed: ' . $ex->getMessage());
                }
            }
        } else {
            $pastType = $validated['type'] ?? 'Call';
            $pastStatus = $validated['status'] ?? 'Connected';
            $pastNotes = trim($validated['notes'] ?? '');

            LeadFollowup::create([
                'tenant_id' => $tenantId,
                'crm_deal_id' => $deal->id,
                'lead_id' => null,
                'followup_date' => \Illuminate\Support\Carbon::now(),
                'type' => $pastType,
                'status' => $pastStatus,
                'notes' => $pastNotes,
                'tagged_user_id' => null,
                'tagged_user_ids' => null,
            ]);

            $nextDate = $request->input('next_followup_date');
            if (!empty($nextDate)) {
                $nextType = $request->input('next_activity_type') ?: 'Call';
                $contextSuffix = "Scheduled " . $nextType . " after " . strtolower($pastType) . " interaction";
                $nextNotes = !empty($pastNotes) ? ($pastNotes . " | " . $contextSuffix) : $contextSuffix;

                $taggedUserIds = !empty($validated['tagged_user_ids']) ? array_values(array_filter($validated['tagged_user_ids'])) : null;
                $primaryTaggedId = !empty($taggedUserIds) ? $taggedUserIds[0] : ($validated['tagged_user_id'] ?? null);

                try {
                    $nextDateTime = \Illuminate\Support\Carbon::parse($nextDate);
                } catch (\Exception $e) {
                    $nextDateTime = \Illuminate\Support\Carbon::now();
                }

                LeadFollowup::create([
                    'tenant_id' => $tenantId,
                    'crm_deal_id' => $deal->id,
                    'lead_id' => null,
                    'followup_date' => $nextDateTime,
                    'type' => $nextType,
                    'status' => 'Pending',
                    'notes' => $nextNotes,
                    'tagged_user_id' => $primaryTaggedId,
                    'tagged_user_ids' => $taggedUserIds,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Deal activity logged/scheduled successfully!');
    }

    /**
     * My Meetings Suite (Lead & Deal Meetings for the logged-in Executive)
     * Reuses the exact same API logic as the mobile CRM suite.
     */
    public function meetingsIndex(Request $request)
    {
        if ($request->wantsJson() || $request->ajax()) {
            if (!$request->has('type')) {
                $request->merge(['type' => 'Meeting']);
            }
            return app(\App\Domains\CRM\Controllers\Api\LeadFollowupApiController::class)->myMeetings($request);
        }

        $user = auth()->user();
        $userId = (int)($request->input('user_id') ?: ($user?->id ?? 1));
        $tenantId = $user?->tenant_id ?? (tenant_id() ?? 1);

        $tenantObj = tenant() ?? \App\Models\Tenant::find($tenantId);
        $settings = is_array($tenantObj?->settings) ? $tenantObj->settings : [];
        $isGeoVerificationEnabled = !empty($settings['field_visit_verification']);

        $baseQuery = LeadFollowup::withoutGlobalScope('branch')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('type', 'Meeting')
                  ->orWhereNull('type');
            })
            ->where(function ($q) use ($userId) {
                $q->where(function ($sub) use ($userId) {
                    $sub->whereNotNull('crm_deal_id')
                        ->whereHas('deal', function ($dq) use ($userId) {
                            $dq->withoutGlobalScope('branch')->where('owner_id', $userId);
                        });
                })
                ->orWhere(function ($sub) use ($userId) {
                    $sub->whereNotNull('lead_id')
                        ->whereNull('crm_deal_id')
                        ->whereHas('lead', function ($lq) use ($userId) {
                            $lq->withoutGlobalScope('branch')->where('lead_owner_id', $userId);
                        });
                })
                ->orWhere('tagged_user_id', $userId)
                ->orWhereJsonContains('tagged_user_ids', $userId)
                ->orWhereJsonContains('tagged_user_ids', (string)$userId);
            });

        $startOfToday = now()->startOfDay();
        $endOfToday = now()->endOfDay();

        $counts = [
            'today' => (clone $baseQuery)->where('status', 'Pending')->whereBetween('followup_date', [$startOfToday, $endOfToday])->count(),
            'planned' => (clone $baseQuery)->where('status', 'Pending')->where('followup_date', '>', $endOfToday)->count(),
            'overdue' => (clone $baseQuery)->where('status', 'Pending')->where('followup_date', '<', $startOfToday)->count(),
            'activity_logs' => (clone $baseQuery)->where('status', '!=', 'Pending')->count(),
            'total' => (clone $baseQuery)->count(),
        ];

        $activeTab = $request->input('tab', 'today');
        if (!in_array($activeTab, ['today', 'planned', 'overdue', 'activity_logs', 'all'])) {
            $activeTab = 'today';
        }

        $listingQuery = clone $baseQuery;

        if ($activeTab === 'today') {
            $listingQuery->where('status', 'Pending')->whereBetween('followup_date', [$startOfToday, $endOfToday]);
        } elseif ($activeTab === 'planned') {
            $listingQuery->where('status', 'Pending')->where('followup_date', '>', $endOfToday);
        } elseif ($activeTab === 'overdue') {
            $listingQuery->where('status', 'Pending')->where('followup_date', '<', $startOfToday);
        } elseif ($activeTab === 'activity_logs') {
            $listingQuery->where('status', '!=', 'Pending');
        }

        if ($status = $request->input('status')) {
            $listingQuery->where('status', $status);
        }

        if ($dateFrom = $request->input('date_from')) {
            $listingQuery->whereDate('followup_date', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $listingQuery->whereDate('followup_date', '<=', $dateTo);
        }

        if ($search = trim((string)$request->input('search'))) {
            $listingQuery->where(function ($sq) use ($search) {
                $sq->where('title', 'like', "%{$search}%")
                   ->orWhere('notes', 'like', "%{$search}%")
                   ->orWhereHas('lead', function ($lq) use ($search) {
                       $lq->withoutGlobalScope('branch')
                          ->where('company_name', 'like', "%{$search}%")
                          ->orWhere('contact_person', 'like', "%{$search}%")
                          ->orWhere('phone', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                   })
                   ->orWhereHas('deal', function ($dq) use ($search) {
                       $dq->withoutGlobalScope('branch')
                          ->where('title', 'like', "%{$search}%")
                          ->orWhereHas('account', fn($aq) => $aq->where('name', 'like', "%{$search}%"))
                          ->orWhereHas('contact', fn($cq) => $cq->where('name', 'like', "%{$search}%"));
                   });
            });
        }

        $sortBy = $request->input('sort_by', 'followup_date');
        $defaultOrder = ($activeTab === 'activity_logs' ? 'desc' : 'asc');
        $sortOrder = strtolower($request->input('sort_order', $defaultOrder)) === 'desc' ? 'desc' : 'asc';

        if ($sortBy === 'company') {
            $listingQuery->leftJoin('leads', 'lead_followups.lead_id', '=', 'leads.id')
                         ->orderBy('leads.company_name', $sortOrder)
                         ->select('lead_followups.*');
        } else {
            $listingQuery->orderBy('followup_date', $sortOrder);
        }

        $paginated = $listingQuery->with([
            'lead' => function ($q) {
                $q->withoutGlobalScope('branch')->with('owner:id,name,email');
            },
            'deal' => function ($q) {
                $q->withoutGlobalScope('branch')->with('owner:id,name,email', 'account:id,name', 'contact:id,name');
            },
            'taggedUser:id,name,email',
        ])->paginate(15)->withQueryString();

        $paginated->through(function (LeadFollowup $item) use ($isGeoVerificationEnabled) {
            $isMeeting = strcasecmp((string)$item->type, 'Meeting') === 0;

            $leadData = null;
            $leadData = null;
            if ($item->lead) {
                $l = $item->lead;
                $leadData = [
                    'id'              => $l->id,
                    'lead_number'     => $l->lead_number,
                    'company_name'    => $l->company_name,
                    'contact_person'  => $l->contact_person,
                    'designation'     => $l->designation,
                    'phone'           => $l->phone ?: $l->company_phone,
                    'email'           => $l->email ?: $l->company_email,
                    'status'          => $l->status,
                    'priority'        => $l->priority,
                    'segment'         => $l->segment,
                    'industry_type'   => $l->industry_type,
                    'source'          => $l->source,
                    'city'            => $l->city,
                    'state'           => $l->state,
                    'address'         => $l->address,
                    'requirement'     => $l->requirement,
                    'expected_amount' => $l->expected_amount !== null ? (float)$l->expected_amount : null,
                    'lead_owner'      => [
                        'id'    => $l->owner?->id ?? $l->lead_owner_id,
                        'name'  => $l->owner?->name,
                        'email' => $l->owner?->email,
                    ],
                ];
            }

            $dealData = null;
            if ($item->deal) {
                $d = $item->deal;
                $dealData = [
                    'id'              => $d->id,
                    'deal_number'     => $d->deal_number,
                    'title'           => $d->title,
                    'stage'           => $d->stage,
                    'probability'     => $d->probability,
                    'estimated_value' => $d->estimated_value !== null ? (float)$d->estimated_value : null,
                    'customer_name'   => $d->account?->name ?: ($d->contact?->name ?? null),
                    'contact_name'    => $d->contact?->name ?? null,
                    'contact_phone'   => $d->contact?->phone ?? null,
                    'contact_email'   => $d->contact?->email ?? null,
                    'closing_date'    => $d->closing_date ? $d->closing_date->format('d M, Y') : null,
                    'deal_owner'      => [
                        'id'    => $d->owner?->id ?? $d->owner_id,
                        'name'  => $d->owner?->name,
                        'email' => $d->owner?->email,
                    ],
                ];
            }

            $isGeoVerified = !empty($item->latitude) && !empty($item->longitude) && !empty($item->selfie_path);
            $selfieUrl = !empty($item->selfie_path) ? asset('storage/' . $item->selfie_path) : null;
            $isGeoRequired = $isGeoVerificationEnabled && $isMeeting;

            return [
                'id'                           => $item->id,
                'lead_id'                      => $item->lead_id,
                'crm_deal_id'                  => $item->crm_deal_id,
                'type'                         => $item->type,
                'title'                        => $item->title,
                'notes'                        => $item->notes,
                'status'                       => $item->status,
                'followup_date'                => $item->followup_date ? $item->followup_date->format('Y-m-d H:i:s') : null,
                'duration_minutes'             => $item->duration_minutes ?: 30,
                'google_meet_link'             => $item->google_meet_link,
                'latitude'                     => $item->latitude,
                'longitude'                    => $item->longitude,
                'location_address'             => $item->location_address,
                'selfie_path'                  => $item->selfie_path,
                'selfie_url'                   => $selfieUrl,
                'is_geo_verified'              => $isGeoVerified,
                'is_geo_verification_required' => $isGeoRequired,
                'lead'                         => $leadData,
                'deal'                         => $dealData,
                'tagged_user'                  => $item->taggedUser ? [
                    'id'    => $item->taggedUser->id,
                    'name'  => $item->taggedUser->name,
                    'email' => $item->taggedUser->email,
                ] : null,
            ];
        });

        $geoVerification = [
            'is_enabled' => $isGeoVerificationEnabled,
            'field_visit_verification' => $isGeoVerificationEnabled,
            'requires_selfie_and_location' => $isGeoVerificationEnabled,
        ];

        $users = \App\Models\User::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'email']);
        $meetings = $paginated;

        return view('modules.crm.meetings.index', compact(
            'counts',
            'activeTab',
            'meetings',
            'geoVerification',
            'user',
            'users',
            'sortBy',
            'sortOrder'
        ));
    }

    /**
     * Update meeting status (Connected, Not Connected, Cancelled, etc.)
     * Reuses the exact same API endpoint logic.
     */
    public function updateMeetingStatus(Request $request, LeadFollowup $followup)
    {
        $response = app(\App\Domains\CRM\Controllers\Api\LeadFollowupApiController::class)->updateStatus($request, $followup);

        if ($request->wantsJson() || $request->ajax()) {
            return $response;
        }

        $data = $response->getData(true);
        if (!empty($data['success'])) {
            return redirect()->route('crm.meetings.index')->with('success', $data['message'] ?? 'Meeting updated successfully.');
        }

        return redirect()->back()->withErrors($data['errors'] ?? [])->with('error', $data['message'] ?? 'Could not update meeting status.');
    }

    /**
     * Reschedule meeting to a new date/time
     */
    public function rescheduleMeeting(Request $request, LeadFollowup $followup)
    {
        $request->merge(['is_reschedule' => true]);
        $response = app(\App\Domains\CRM\Controllers\Api\LeadFollowupApiController::class)->update($request, $followup);

        if ($request->wantsJson() || $request->ajax()) {
            return $response;
        }

        $data = $response->getData(true);
        if (!empty($data['success'])) {
            return redirect()->route('crm.meetings.index')->with('success', $data['message'] ?? 'Meeting rescheduled successfully.');
        }

        return redirect()->back()->withErrors($data['errors'] ?? [])->with('error', $data['message'] ?? 'Could not reschedule meeting.');
    }
}
