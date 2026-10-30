<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Models\LeadHistory;
use App\Domains\CRM\Repositories\LeadFollowupRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LeadFollowupService
{
    public function __construct(
        private readonly LeadFollowupRepository $followupRepo
    ) {
    }

    /**
     * Store new followup, log history, and sync lead.
     */
    public function storeFollowup(Lead $lead, array $validated): LeadFollowup
    {
        try {
            $followupDateTime = Carbon::parse($validated['followup_date']);
        } catch (\Exception $e) {
            $followupDateTime = Carbon::now();
        }

        $taggedUserIds = !empty($validated['tagged_user_ids']) ? array_values(array_filter($validated['tagged_user_ids'])) : null;
        $primaryTaggedId = !empty($taggedUserIds) ? $taggedUserIds[0] : ($validated['tagged_user_id'] ?? null);

        $durationMinutes = !empty($validated['duration_minutes']) ? (int) $validated['duration_minutes'] : 30;
        $guestEmailsInput = !empty($validated['guest_emails']) ? trim($validated['guest_emails']) : null;
        $titleInput = !empty($validated['title']) ? trim($validated['title']) : (($validated['type'] ?? 'Scheduled Call') . " with " . ($lead->company_name ?: $lead->contact_person));

        $followup = $this->followupRepo->create([
            'tenant_id' => $lead->tenant_id ?? (tenant_id() ?? auth()->user()?->tenant_id),
            'company_id' => $lead->company_id ?? (company_id() ?? auth()->user()?->company_id),
            'branch_id' => $lead->branch_id ?? (branch_id() ?? auth()->user()?->branch_id),
            'lead_id' => $lead->id,
            'followup_date' => $followupDateTime,
            'type' => $validated['type'],
            'title' => $titleInput,
            'duration_minutes' => $durationMinutes,
            'guest_emails' => $guestEmailsInput,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'tagged_user_id' => $primaryTaggedId,
            'tagged_user_ids' => $taggedUserIds,
            'latitude' => isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            'longitude' => isset($validated['longitude']) ? (float) $validated['longitude'] : null,
            'location_address' => $validated['location_address'] ?? null,
            'selfie_path' => $validated['selfie_path'] ?? null,
            'location_verified_at' => $validated['location_verified_at'] ?? (!empty($validated['latitude']) || !empty($validated['selfie_path']) ? now() : null),
        ]);

        $eventType = $followup->status === 'Pending' ? 'activity_scheduled' : 'activity_completed';
        $description = $followup->status === 'Pending'
            ? "Scheduled a {$followup->type} activity on " . $followup->followup_date->format('d/m/Y h:i A')
            : "Logged a {$followup->type} interaction: " . ($followup->notes ?: 'No details');

        // Auto-Sync with Google Calendar if either calendar or meet switch is enabled
        $syncGoogle = !empty($validated['sync_google_calendar']) || !empty($validated['create_meet_link']);
        if ($followup->status === 'Pending' && $syncGoogle) {
            try {
                $createMeet = !empty($validated['create_meet_link']);
                $primaryLeadEmail = !empty($lead->email) ? trim($lead->email) : (!empty($lead->company_email) ? trim($lead->company_email) : null);
                if ($primaryLeadEmail) {
                    $attendees[] = $primaryLeadEmail;
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

                $calService = app(GoogleCalendarIntegrationService::class);
                $res = $calService->createEvent([
                    'summary' => $titleInput,
                    'description' => $validated['notes'] ?? '',
                    'start_time' => $followupDateTime->toIso8601String(),
                    'end_time' => (clone $followupDateTime)->addMinutes($durationMinutes)->toIso8601String(),
                    'attendees' => $attendees,
                    'create_meet_link' => $createMeet,
                    'lead_id' => $lead->id,
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
                \Illuminate\Support\Facades\Log::warning('Google Calendar auto-sync notice: ' . $ex->getMessage());
                session()->flash('google_sync_warning', 'Google Calendar auto-sync failed: ' . $ex->getMessage());
            }
        }

        LeadHistory::logEvent(
            $lead,
            $eventType,
            null,
            $followup->type,
            $description
        );

        $this->followupRepo->syncLeadNextFollowupDate($lead);

        return $followup;
    }

    /**
     * Update or Reschedule followup, log history, and sync lead.
     */
    public function updateOrReschedule(LeadFollowup $followup, array $validated, bool $isRescheduleRequested): string
    {
        $lead = $followup->lead ?? ($followup->crm_deal_id ? Lead::where('crm_deal_id', $followup->crm_deal_id)->first() : null);
        $isReschedule = !empty($validated['followup_date']) && ($isRescheduleRequested || $followup->status === 'Pending');

        $taggedUserIds = isset($validated['tagged_user_ids']) ? array_values(array_filter($validated['tagged_user_ids'])) : null;
        $primaryTaggedId = !empty($taggedUserIds) ? $taggedUserIds[0] : (array_key_exists('tagged_user_id', $validated) ? ($validated['tagged_user_id'] ?: null) : $followup->tagged_user_id);

        if ($isReschedule && !empty($validated['followup_date'])) {
            try {
                $newFollowupDateTime = Carbon::parse($validated['followup_date']);
                $oldStatus = $followup->status;

                $this->followupRepo->update($followup, ['status' => 'Rescheduled']);

                $this->followupRepo->create([
                    'lead_id' => $followup->lead_id,
                    'crm_deal_id' => $followup->crm_deal_id,
                    'type' => $validated['type'] ?? $followup->type,
                    'followup_date' => $newFollowupDateTime,
                    'status' => 'Pending',
                    'notes' => $validated['notes'] ?? $followup->notes,
                    'tagged_user_id' => $primaryTaggedId,
                    'tagged_user_ids' => $taggedUserIds ?? $followup->tagged_user_ids,
                    'rescheduled_from_id' => $followup->id,
                    'original_followup_date' => $followup->original_followup_date ?: $followup->followup_date,
                ]);

                if ($lead) {
                    LeadHistory::logEvent(
                        $lead,
                        'activity_rescheduled',
                        $oldStatus,
                        'Rescheduled',
                        "Rescheduled {$followup->type} activity from " . $followup->followup_date->format('d/m/Y h:i A') . " to " . $newFollowupDateTime->format('d/m/Y h:i A')
                    );
                    $this->followupRepo->syncLeadNextFollowupDate($lead);
                }
                return 'Activity successfully rescheduled!';
            } catch (\Exception $e) {
                // Fall through to standard update on parse exception
            }
        }

        $updateData = [];
        if (isset($validated['status']))
            $updateData['status'] = $validated['status'];
        if (isset($validated['notes']))
            $updateData['notes'] = $validated['notes'];
        if (isset($validated['type']))
            $updateData['type'] = $validated['type'];
        if (array_key_exists('tagged_user_ids', $validated)) {
            $updateData['tagged_user_ids'] = $taggedUserIds;
            $updateData['tagged_user_id'] = $primaryTaggedId;
        } elseif (array_key_exists('tagged_user_id', $validated)) {
            $updateData['tagged_user_id'] = $validated['tagged_user_id'] ?: null;
        }
        if (isset($validated['followup_date'])) {
            try {
                $updateData['followup_date'] = Carbon::parse($validated['followup_date']);
            } catch (\Exception $e) {
            }
        }
        if (array_key_exists('latitude', $validated))
            $updateData['latitude'] = $validated['latitude'];
        if (array_key_exists('longitude', $validated))
            $updateData['longitude'] = $validated['longitude'];
        if (array_key_exists('location_address', $validated))
            $updateData['location_address'] = $validated['location_address'];
        if (array_key_exists('checkin_latitude', $validated))
            $updateData['checkin_latitude'] = $validated['checkin_latitude'];
        if (array_key_exists('checkin_longitude', $validated))
            $updateData['checkin_longitude'] = $validated['checkin_longitude'];
        if (array_key_exists('checkin_address', $validated))
            $updateData['checkin_address'] = $validated['checkin_address'];
        if (array_key_exists('checkin_distance_meters', $validated))
            $updateData['checkin_distance_meters'] = $validated['checkin_distance_meters'];
        if (array_key_exists('checkin_status', $validated))
            $updateData['checkin_status'] = $validated['checkin_status'];
        if (array_key_exists('checkin_at', $validated))
            $updateData['checkin_at'] = $validated['checkin_at'];
        if (array_key_exists('selfie_path', $validated))
            $updateData['selfie_path'] = $validated['selfie_path'];
        if (array_key_exists('location_verified_at', $validated))
            $updateData['location_verified_at'] = $validated['location_verified_at'];

        $oldStatus = $followup->status;
        $this->followupRepo->update($followup, $updateData);

        if ($oldStatus !== $followup->status && in_array($followup->status, ['Completed', 'Not Connected'])) {
            $locSnippet = !empty($followup->location_address) ? " at {$followup->location_address}" : (!empty($followup->latitude) ? " [GPS Verified]" : "");
            $photoSnippet = !empty($followup->selfie_path) ? " [Photo Attached]" : "";
            if ($lead) {
                LeadHistory::logEvent(
                    $lead,
                    $followup->status === 'Completed' ? 'activity_completed' : 'activity_not_connected',
                    $oldStatus,
                    $followup->status,
                    "Marked scheduled {$followup->type} activity (scheduled for " . $followup->followup_date->format('d/m/Y h:i A') . ") as {$followup->status}{$locSnippet}{$photoSnippet}"
                );
            }
        }

        if ($lead) {
            $this->followupRepo->syncLeadNextFollowupDate($lead);
        }
        return 'Follow-up successfully updated!';
    }

    /**
     * Delete followup, log history, and sync lead.
     */
    public function deleteFollowup(LeadFollowup $followup): void
    {
        $lead = $followup->lead ?? ($followup->crm_deal_id ? Lead::where('crm_deal_id', $followup->crm_deal_id)->first() : null);

        if ($lead) {
            LeadHistory::logEvent(
                $lead,
                'activity_deleted',
                $followup->type,
                null,
                "Deleted {$followup->type} activity (scheduled/logged for " . $followup->followup_date->format('d/m/Y h:i A') . ")"
            );
        }

        $this->followupRepo->delete($followup);
        if ($lead) {
            $this->followupRepo->syncLeadNextFollowupDate($lead);
        }
    }

    /**
     * Check if Geo-Verification & Selfie are strictly required by tenant policy.
     */
    public function isGeoVerificationRequired(?string $targetStatus, ?string $activityType, ?LeadFollowup $existingFollowup = null): bool
    {
        $tenant = tenant();
        $settings = is_array($tenant?->settings) ? $tenant->settings : [];
        if (empty($settings['field_visit_verification'])) {
            return false;
        }

        $effectiveType = $activityType ?: ($existingFollowup?->type ?? 'Meeting');
        $effectiveStatus = $targetStatus ?: ($existingFollowup?->status ?? 'Completed');

        $isMeeting = strcasecmp((string) $effectiveType, 'Meeting') === 0;
        $isCompleting = in_array((string) $effectiveStatus, ['Completed', 'Not Connected']);

        return $isMeeting && $isCompleting;
    }

    /**
     * Process selfie upload from either raw file or base64 data string.
     */
    public function processSelfieUpload(Request $request): ?string
    {
        if ($request->filled('selfie_base64')) {
            $base64Data = trim((string) $request->input('selfie_base64'));
            $imageType = 'jpg';
            $data = $base64Data;

            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
                $rawExt = strtolower($type[1]);
                $imageType = ($rawExt === 'jpeg') ? 'jpg' : $rawExt;
                $data = substr($base64Data, strpos($base64Data, ',') + 1);
            }

            if (in_array($imageType, ['jpeg', 'jpg', 'png', 'webp'])) {
                $decoded = base64_decode($data, true);
                if ($decoded !== false && strlen($decoded) <= 5242880) { // Max 5MB
                    $filename = Str::uuid() . '.' . ($imageType === 'jpeg' ? 'jpg' : $imageType);
                    Storage::disk('public')->put('crm/selfies/' . $filename, $decoded);
                    return 'storage/crm/selfies/' . $filename;
                }
            }
        } elseif ($request->hasFile('selfie')) {
            $file = $request->file('selfie');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
            $filename = Str::uuid() . '.' . $ext;
            $path = $file->storeAs('crm/selfies', $filename, 'public');
            return 'storage/' . $path;
        }

        return null;
    }

    /**
     * Extract verification payload from request (coordinates, address, selfie).
     * When $isCheckin is true, coordinates are saved to checkin_* fields and scheduled venue fields are preserved.
     */
    public function extractVerificationPayload(Request $request, ?LeadFollowup $existingFollowup = null, bool $isCheckin = false): array
    {
        $payload = [];
        $selfiePath = $this->processSelfieUpload($request);

        if ($selfiePath) {
            $payload['selfie_path'] = $selfiePath;
            $payload['location_verified_at'] = now();
        } elseif ($existingFollowup?->selfie_path) {
            $payload['selfie_path'] = $existingFollowup->selfie_path;
        }

        if ($isCheckin) {
            // When marking meeting Connected / Not Connected, save live device GPS as checkin location
            $liveLat = $request->input('checkin_latitude') ?? $request->input('latitude');
            $liveLng = $request->input('checkin_longitude') ?? $request->input('longitude');
            $liveAddress = $request->input('checkin_address') ?? $request->input('location_address');

            if ($liveLat !== null && $liveLat !== '') {
                $payload['checkin_latitude'] = (float) $liveLat;
            }
            if ($liveLng !== null && $liveLng !== '') {
                $payload['checkin_longitude'] = (float) $liveLng;
            }
            if ($liveAddress !== null && $liveAddress !== '') {
                $payload['checkin_address'] = (string) $liveAddress;
            }
            $payload['checkin_at'] = now();
            $payload['location_verified_at'] = now();

            // Calculate distance & verify against original scheduled venue
            $scheduledLat = $existingFollowup?->latitude;
            $scheduledLng = $existingFollowup?->longitude;
            if ($scheduledLat !== null && $scheduledLng !== null && isset($payload['checkin_latitude']) && isset($payload['checkin_longitude'])) {
                $earthRadius = 6371000; // in meters
                $dLat = deg2rad($payload['checkin_latitude'] - $scheduledLat);
                $dLng = deg2rad($payload['checkin_longitude'] - $scheduledLng);
                $a = sin($dLat / 2) * sin($dLat / 2) +
                     cos(deg2rad($scheduledLat)) * cos(deg2rad($payload['checkin_latitude'])) *
                     sin($dLng / 2) * sin($dLng / 2);
                $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                $distanceMeters = (int) round($earthRadius * $c);

                $tenantId = $existingFollowup->tenant_id ?? (tenant_id() ?? 1);
                $tenantObj = tenant() ?? \App\Models\Tenant::find($tenantId);
                $settings = is_array($tenantObj?->settings) ? $tenantObj->settings : [];
                $allowedRadius = (int)($settings['geofence_radius_meters'] ?? 500);

                $payload['checkin_distance_meters'] = $distanceMeters;
                $payload['checkin_status'] = ($distanceMeters <= $allowedRadius)
                    ? "Verified On-Site ({$distanceMeters}m)"
                    : "Location Mismatch ({$distanceMeters}m away)";
            } elseif (isset($payload['checkin_latitude'])) {
                $payload['checkin_status'] = "GPS Logged (No Scheduled Venue)";
            }

            // CRITICAL: Preserve original scheduled venue coordinates, DO NOT overwrite!
            if ($existingFollowup?->latitude !== null) {
                $payload['latitude'] = $existingFollowup->latitude;
            }
            if ($existingFollowup?->longitude !== null) {
                $payload['longitude'] = $existingFollowup->longitude;
            }
            if ($existingFollowup?->location_address !== null) {
                $payload['location_address'] = $existingFollowup->location_address;
            }
        } else {
            // Normal creation / scheduling of meeting: save into scheduled venue fields
            if ($request->filled('latitude')) {
                $payload['latitude'] = (float) $request->input('latitude');
            } elseif ($existingFollowup?->latitude !== null) {
                $payload['latitude'] = $existingFollowup->latitude;
            }

            if ($request->filled('longitude')) {
                $payload['longitude'] = (float) $request->input('longitude');
            } elseif ($existingFollowup?->longitude !== null) {
                $payload['longitude'] = $existingFollowup->longitude;
            }

            if ($request->filled('location_address')) {
                $payload['location_address'] = (string) $request->input('location_address');
            } elseif ($existingFollowup?->location_address !== null) {
                $payload['location_address'] = $existingFollowup->location_address;
            }

            if ((!empty($payload['latitude']) || !empty($payload['selfie_path'])) && empty($payload['location_verified_at'])) {
                $payload['location_verified_at'] = now();
            }
        }

        return $payload;
    }
}
