<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Models\LeadHistory;
use App\Models\TwilioConfiguration;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CrmCallAssistantController extends Controller
{
    /**
     * Analyze call conversation & audio recording using Google Gemini AI.
     */
    public function analyzeCall(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $request->validate([
            'call_notes' => ['nullable', 'string', 'max:10000'],
            'duration_seconds' => ['nullable', 'integer'],
            'audio_file' => ['nullable', 'file', 'max:25600'],
            'audio_base64' => ['nullable', 'string'],
            'dialed_number' => ['nullable', 'string', 'max:50'],
            'call_sid' => ['nullable', 'string', 'max:100'],
        ]);

        $callNotes = trim((string)$request->input('call_notes', ''));
        $durationSeconds = (int)$request->input('duration_seconds', 60);
        $dialedNumber = $request->input('dialed_number') ?: ($lead->phone ?: $lead->company_phone);
        $callSid = $request->input('call_sid');

        // Handle Audio Upload / Base64 from Browser
        $audioBase64 = null;
        $audioMime = 'audio/webm';
        if ($request->hasFile('audio_file')) {
            $file = $request->file('audio_file');
            $audioBase64 = base64_encode(file_get_contents($file->getRealPath()));
            $audioMime = $file->getMimeType() ?: 'audio/webm';
        } elseif ($request->filled('audio_base64')) {
            $rawBase64 = $request->input('audio_base64');
            if (preg_match('/^data:(audio\/[^;]+);base64,(.+)$/', $rawBase64, $matches)) {
                $audioMime = $matches[1];
                $audioBase64 = $matches[2];
            } else {
                $audioBase64 = preg_replace('/^data:audio\/[^;]+;base64,/', '', $rawBase64);
            }
        }

        $tenantId = $lead->tenant_id ?? (current_tenant_id() ?? 1);
        $twilioConfig = TwilioConfiguration::where('tenant_id', $tenantId)->first();
        $apiKey = $twilioConfig?->gemini_api_key ?: (config('services.gemini.api_key') ?: env('GEMINI_API_KEY'));
        if (empty($apiKey)) {
            $fallbackConfig = TwilioConfiguration::whereNotNull('gemini_api_key')->where('gemini_api_key', '!=', '')->first();
            $apiKey = $fallbackConfig?->gemini_api_key;
        }

        // 1. Fetch Twilio Dual-Channel Audio Recording (Contains BOTH Agent & Customer voices)
        if ($twilioConfig && !empty($twilioConfig->account_sid) && !empty($twilioConfig->auth_token)) {
            try {
                $accSid = trim($twilioConfig->account_sid);
                $authTok = trim($twilioConfig->auth_token);

                for ($attempt = 1; $attempt <= 2; $attempt++) {
                    $endpoint = !empty($callSid)
                        ? "https://api.twilio.com/2010-04-01/Accounts/{$accSid}/Recordings.json?CallSid={$callSid}&PageSize=1"
                        : "https://api.twilio.com/2010-04-01/Accounts/{$accSid}/Recordings.json?PageSize=1";

                    $recRes = Http::withBasicAuth($accSid, $authTok)->timeout(8)->get($endpoint);

                    if ($recRes->successful()) {
                        $recordings = $recRes->json('recordings') ?? [];
                        if (!empty($recordings[0]['sid'])) {
                            $recSid = $recordings[0]['sid'];
                            $mp3Res = Http::withBasicAuth($accSid, $authTok)
                                ->timeout(15)
                                ->get("https://api.twilio.com/2010-04-01/Accounts/{$accSid}/Recordings/{$recSid}.mp3");
                            if ($mp3Res->successful() && strlen($mp3Res->body()) > 500) {
                                $audioBase64 = base64_encode($mp3Res->body());
                                $audioMime = 'audio/mp3';
                                break;
                            }
                        }
                    }
                    if ($attempt < 2 && empty($audioBase64)) {
                        usleep(1000000); // 1.0s wait for Twilio transcoder to finish MP3
                    }
                }
            } catch (\Throwable $recErr) {
                Log::warning('Twilio dual call recording fetch notice: ' . $recErr->getMessage());
            }
        }

        // 2. Fallback to Browser microphone audio if Twilio recording was not found
        if (empty($audioBase64)) {
            if ($request->hasFile('audio_file')) {
                $file = $request->file('audio_file');
                $audioBase64 = base64_encode(file_get_contents($file->getRealPath()));
                $audioMime = $file->getMimeType() ?: 'audio/webm';
            } elseif ($request->filled('audio_base64')) {
                $rawBase64 = $request->input('audio_base64');
                if (preg_match('/^data:(audio\/[^;]+);base64,(.+)$/', $rawBase64, $matches)) {
                    $audioMime = $matches[1];
                    $audioBase64 = $matches[2];
                } else {
                    $audioBase64 = preg_replace('/^data:audio\/[^;]+;base64,/', '', $rawBase64);
                }
            }
        }

        // 3. Save Call Audio Recording to Local Storage for Playback
        $recordingUrl = null;
        if (!empty($audioBase64)) {
            try {
                $rawAudio = base64_decode($audioBase64);
                if ($rawAudio && strlen($rawAudio) > 100) {
                    $ext = ($audioMime === 'audio/mp3' || $audioMime === 'audio/mpeg') ? 'mp3' : 'webm';
                    $fileName = 'call_' . $lead->id . '_' . time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
                    Storage::disk('public')->put('call_recordings/' . $fileName, $rawAudio);
                    $recordingUrl = 'storage/call_recordings/' . $fileName;
                }
            } catch (\Throwable $fileErr) {
                Log::warning('Call audio storage notice: ' . $fileErr->getMessage());
            }
        }

        if (empty($callNotes) && empty($audioBase64)) {
            // Check if call had zero duration or notes
            if ($durationSeconds <= 5) {
                $callNotes = "Not answering";
            } else {
                $callNotes = "";
            }
        }

        // Check if lead had a previously scheduled follow-up
        $pendingFollowup = LeadFollowup::where('lead_id', $lead->id)
            ->where('status', 'scheduled')
            ->latest('followup_date')
            ->first();

        // Normalize next_followup_date to ISO format (Y-m-d\TH:i) strictly when provided
        $formatIsoDate = function($val) {
            if (!$val || strtolower((string)$val) === 'null' || strtolower((string)$val) === 'none' || trim((string)$val) === '') return null;
            try {
                return Carbon::parse($val)->format('Y-m-d\TH:i');
            } catch (\Throwable $e) {
                return null;
            }
        };

        // Smart Heuristic date extractor as fallback/enhancement for Hindi & English time mentions
        $extractDateFromText = function(string $text) {
            $lower = strtolower($text);
            $now = Carbon::now();
            
            $hour = null;
            $minute = 0;
            $isTomorrow = str_contains($lower, 'kal') || str_contains($lower, 'tomorrow') || str_contains($lower, 'next day');
            
            if (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(?:p\.?m\.?|pm|afternoon|sham|shaam|dopahar)/i', $lower, $m)) {
                $h = (int)$m[1];
                if ($h < 12) $h += 12;
                $hour = $h;
                $minute = isset($m[2]) ? (int)$m[2] : 0;
            } elseif (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(?:a\.?m\.?|am|subah|morning)/i', $lower, $m)) {
                $h = (int)$m[1];
                if ($h === 12) $h = 0;
                $hour = $h;
                $minute = isset($m[2]) ? (int)$m[2] : 0;
            } elseif (preg_match('/(?:after|post|ke baad|baad|at)\s*(\d{1,2})(?::(\d{2}))?/i', $lower, $m)) {
                $h = (int)$m[1];
                if ($h >= 1 && $h <= 7) $h += 12;
                $hour = $h;
                $minute = isset($m[2]) ? (int)$m[2] : 0;
            } elseif (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(?:baje|ghante)/i', $lower, $m)) {
                $h = (int)$m[1];
                if ($h >= 1 && $h <= 7) $h += 12;
                $hour = $h;
                $minute = isset($m[2]) ? (int)$m[2] : 0;
            }

            // Specific Date: e.g. "10th of October", "10 October", "15th Nov"
            if (preg_match('/(\d{1,2})(?:st|nd|rd|th)?\s*(?:of\s*)?(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)/i', $lower, $m)) {
                try {
                    $day = (int)$m[1];
                    $monthStr = $m[2];
                    $targetDate = Carbon::parse("{$day} {$monthStr} {$now->year}");
                    $targetDate->setHour($hour !== null ? $hour : 11)->setMinute($minute)->setSecond(0);
                    return $targetDate->format('Y-m-d\TH:i');
                } catch (\Throwable $e) {}
            }
            
            if ($hour !== null) {
                $targetDate = $isTomorrow ? $now->copy()->addDay() : $now->copy();
                if (!$isTomorrow && $targetDate->hour >= $hour) {
                    $targetDate->addDay();
                }
                $targetDate->setHour($hour)->setMinute($minute)->setSecond(0);
                return $targetDate->format('Y-m-d\TH:i');
            } elseif ($isTomorrow) {
                return $now->copy()->addDay()->setHour(11)->setMinute(0)->format('Y-m-d\TH:i');
            }
            
            return null;
        };

        // If Gemini API key is available, call Gemini Flash API
        if (!empty($apiKey)) {
            $geminiResult = $this->queryGeminiAi($apiKey, $lead, $callNotes, $pendingFollowup, $audioBase64, $audioMime, $dialedNumber);
            if ($geminiResult) {
                $summary = trim($geminiResult['summary'] ?? ($callNotes ?: 'Not answering'));
                $transcript = trim($geminiResult['transcript'] ?? $callNotes);
                $nextDateIso = $formatIsoDate($geminiResult['next_followup_date'] ?? null);
                if (empty($nextDateIso)) {
                    $nextDateIso = $extractDateFromText($transcript . ' ' . $summary . ' ' . $callNotes);
                }
                $nextAction = trim($geminiResult['next_action'] ?? ($summary === 'Not answering' ? 'Retry Call' : 'Follow-up Call'));
                $contactName = $lead->contact_person ?: $lead->company_name ?: 'Client';

                $followupMsg = ($summary === 'Not answering' || $summary === 'Disconnected') ? '' : 
                    "Hi {$contactName}, thank you for speaking with us today! As discussed, {$summary}." . 
                    ($nextDateIso ? " We look forward to connecting again on " . Carbon::parse($nextDateIso)->format('d M Y, h:i A') . "." : "");

                return response()->json([
                    'success' => true,
                    'ai_powered' => true,
                    'transcript' => $transcript,
                    'summary' => $summary,
                    'discussion_summary' => $summary,
                    'next_action' => $nextAction,
                    'next_activity_title' => $nextAction,
                    'next_activity_type' => str_contains(strtolower($nextAction), 'meeting') ? 'Meeting' : (str_contains(strtolower($nextAction), 'demo') ? 'Demo' : (str_contains(strtolower($nextAction), 'whatsapp') ? 'WhatsApp' : 'Call')),
                    'next_followup_date' => $nextDateIso,
                    'sentiment' => $geminiResult['sentiment'] ?? ($summary === 'Not answering' ? 'Neutral' : 'Interested'),
                    'suggested_lead_status' => $geminiResult['suggested_lead_status'] ?? ($lead->status === 'New' ? 'Contacted' : $lead->status),
                    'existing_meeting_update' => $geminiResult['existing_meeting_update'] ?? null,
                    'followup_message_preview' => $followupMsg,
                    'dialed_number' => $dialedNumber,
                    'recording_url' => $recordingUrl,
                    'audio_duration' => $durationSeconds,
                    'has_pending_followup' => (bool)$pendingFollowup,
                    'pending_followup_id' => $pendingFollowup?->id,
                ]);
            }
        }

        // Strict Heuristic Fallback (No dummy data)
        $fallback = $this->generateHeuristicAnalysis($lead, $callNotes, $pendingFollowup);
        $summary = $fallback['summary'];
        $transcript = $fallback['transcript'];
        $nextDateIso = $formatIsoDate($fallback['next_followup_date'] ?? null) ?: $extractDateFromText($callNotes);
        $nextAction = $fallback['next_action'];
        $contactName = $lead->contact_person ?: $lead->company_name ?: 'Client';

        $followupMsg = ($summary === 'Not answering' || $summary === 'Disconnected') ? '' : 
            "Hi {$contactName}, thank you for speaking with us today! As discussed, {$summary}." . 
            ($nextDateIso ? " We look forward to connecting again on " . Carbon::parse($nextDateIso)->format('d M Y, h:i A') . "." : "");

        return response()->json([
            'success' => true,
            'ai_powered' => false,
            'transcript' => $transcript,
            'summary' => $summary,
            'discussion_summary' => $summary,
            'next_action' => $nextAction,
            'next_activity_title' => $nextAction,
            'next_activity_type' => str_contains(strtolower($nextAction), 'meeting') ? 'Meeting' : (str_contains(strtolower($nextAction), 'demo') ? 'Demo' : (str_contains(strtolower($nextAction), 'whatsapp') ? 'WhatsApp' : 'Call')),
            'next_followup_date' => $nextDateIso,
            'sentiment' => $fallback['sentiment'],
            'suggested_lead_status' => $fallback['suggested_lead_status'],
            'existing_meeting_update' => $fallback['existing_meeting_update'],
            'followup_message_preview' => $followupMsg,
            'dialed_number' => $dialedNumber,
            'recording_url' => $recordingUrl,
            'audio_duration' => $durationSeconds,
            'has_pending_followup' => (bool)$pendingFollowup,
            'pending_followup_id' => $pendingFollowup?->id,
        ]);
    }

    /**
     * User confirms/approves the AI call analysis popup.
     */
    public function confirmCallFollowup(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $request->validate([
            'summary' => ['required', 'string', 'max:5000'],
            'transcript' => ['nullable', 'string', 'max:20000'],
            'duration_seconds' => ['nullable', 'integer'],
            'next_followup_date' => ['nullable', 'date'],
            'next_action' => ['nullable', 'string', 'max:100'],
            'lead_status' => ['nullable', 'string', 'max:50'],
            'sentiment' => ['nullable', 'string', 'max:50'],
            'pending_followup_id' => ['nullable', 'integer'],
            'recording_url' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = $lead->tenant_id ?? (current_tenant_id() ?? 1);
        $companyId = $lead->company_id ?? current_company_id();
        $branchId = $lead->branch_id ?? current_branch_id();
        $userId = auth()->id() ?: ($lead->lead_owner_id ?: 1);

        DB::beginTransaction();
        try {
            $durationSeconds = (int)($request->input('duration_seconds', 60));
            $summary = trim($request->input('summary'));
            $transcript = trim((string)$request->input('transcript', ''));
            $sentiment = $request->input('sentiment') ?: 'Interested';
            $nextFollowupDate = !empty($request->input('next_followup_date')) ? Carbon::parse($request->input('next_followup_date')) : null;
            $nextAction = $request->input('next_action') ?: 'Follow-up Call';
            $recordingUrl = $request->input('recording_url') ?: null;

            // 1. Mark existing scheduled followup as completed with meeting notes
            if (!empty($request->input('pending_followup_id'))) {
                $pendingFollowup = LeadFollowup::find($request->input('pending_followup_id'));
                if ($pendingFollowup && $pendingFollowup->lead_id === $lead->id) {
                    $pendingFollowup->status = 'completed';
                    $pendingFollowup->notes = ($pendingFollowup->notes ? $pendingFollowup->notes . "\n\n" : '') . 
                        "[Call Completed on " . now()->format('d M Y h:i A') . "]\n" . $summary;
                    $pendingFollowup->save();
                }
            }

            // Construct rich discussion notes with full conversation details
            $notesContent = $summary;
            if (!empty($transcript) && $transcript !== $summary) {
                $notesContent .= "\n\n[Conversation Transcript / Discussion]:\n" . $transcript;
            }
            if ($sentiment && $summary !== 'Not answering' && $summary !== 'Disconnected') {
                $notesContent .= "\n\n[Lead Sentiment: {$sentiment}]";
            }

            // 2. Create Completed Call Record / Interaction Log
            $callFollowup = new LeadFollowup();
            $callFollowup->tenant_id = $tenantId;
            $callFollowup->company_id = $companyId;
            $callFollowup->branch_id = $branchId;
            $callFollowup->lead_id = $lead->id;
            $callFollowup->tagged_user_id = $userId;
            $callFollowup->type = 'Call';
            $callFollowup->recording_url = $recordingUrl;
            $callFollowup->audio_duration = $durationSeconds;
            
            if ($summary === 'Not answering') {
                $callFollowup->status = 'Not Answering';
                $callFollowup->title = "Phone Call (Not Answered) - " . ($lead->contact_person ?: $lead->company_name);
            } elseif ($summary === 'Disconnected') {
                $callFollowup->status = 'Not Connected';
                $callFollowup->title = "Phone Call (Disconnected) - " . ($lead->contact_person ?: $lead->company_name);
            } else {
                $callFollowup->status = 'Completed';
                $callFollowup->title = "Phone Call (" . gmdate("i:s", $durationSeconds) . "s) with " . ($lead->contact_person ?: $lead->company_name);
            }

            $callFollowup->duration_minutes = max(1, ceil($durationSeconds / 60));
            $callFollowup->followup_date = now();
            $callFollowup->notes = $notesContent;
            $callFollowup->save();

            // 3. If Next Followup Date provided, schedule new activity (prevent duplicate creation)
            if ($nextFollowupDate) {
                $existingDuplicate = LeadFollowup::where('lead_id', $lead->id)
                    ->where('status', 'Pending')
                    ->where('followup_date', $nextFollowupDate)
                    ->where('created_at', '>=', now()->subSeconds(60))
                    ->first();

                if (!$existingDuplicate) {
                    $newFollowup = new LeadFollowup();
                    $newFollowup->tenant_id = $tenantId;
                    $newFollowup->company_id = $companyId;
                    $newFollowup->branch_id = $branchId;
                    $newFollowup->lead_id = $lead->id;
                    $newFollowup->tagged_user_id = $userId;
                    $newFollowup->type = str_contains(strtolower($nextAction), 'meeting') ? 'Meeting' : (str_contains(strtolower($nextAction), 'demo') ? 'Demo' : 'Call');
                    $newFollowup->status = 'Pending';
                    $newFollowup->title = $nextAction . " - " . ($lead->company_name ?: $lead->contact_person);
                    $newFollowup->followup_date = $nextFollowupDate;
                    $newFollowup->notes = "Scheduled action: " . $nextAction . "\nContext from call: " . $summary;
                    $newFollowup->save();

                    $lead->next_followup_date = $nextFollowupDate;
                }
            }

            // 4. Update Lead Status & Call Date
            $leadStatus = $request->input('lead_status');
            if (!empty($leadStatus) && $leadStatus !== 'Select an Option') {
                $lead->status = $leadStatus;
            } elseif ($lead->status === 'New') {
                $lead->status = 'Contacted';
            }
            $lead->call_date = now();
            $lead->save();

            // 5. Add History Timeline
            LeadHistory::logEvent(
                $lead,
                'call_completed_ai',
                null,
                $lead->status,
                "Phone Call logged with AI assistant. Summary: {$summary}." . ($nextFollowupDate ? " Next follow-up scheduled for " . $nextFollowupDate->format('d M Y, h:i A') : '')
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Call logged and next follow-up activity scheduled successfully!',
                'lead_id' => $lead->id,
                'lead_status' => $lead->status,
                'next_followup_formatted' => $lead->next_followup_date ? $lead->next_followup_date->format('d M Y, h:i A') : null,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Call confirmation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save call follow-up: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Initiate real outbound phone call via Twilio Cloud Telephony.
     */
    public function initiateOutboundCall(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $request->validate([
            'to_number' => ['required', 'string', 'max:30'],
        ]);

        $rawNumber = trim((string)$request->input('to_number'));
        // Clean and format number for international E.164 (default to +91 if Indian 10 digits)
        $cleanNumber = preg_replace('/[^\d+]/', '', $rawNumber);
        if (!str_starts_with($cleanNumber, '+')) {
            if (strlen($cleanNumber) === 10) {
                $cleanNumber = '+91' . $cleanNumber;
            } else {
                $cleanNumber = '+' . $cleanNumber;
            }
        }

        $tenantId = $lead->tenant_id ?? (current_tenant_id() ?? 1);
        $twilioConfig = TwilioConfiguration::where('tenant_id', $tenantId)->first();

        if (!$twilioConfig || empty($twilioConfig->account_sid) || empty($twilioConfig->auth_token) || empty($twilioConfig->phone_number)) {
            return response()->json([
                'success' => false,
                'message' => 'Twilio is not configured for this tenant. Please setup Twilio credentials in Platform Settings.',
            ], 422);
        }

        try {
            $accountSid = trim($twilioConfig->account_sid);
            $authToken = trim($twilioConfig->auth_token);
            $fromNumber = trim($twilioConfig->phone_number);

            $contactName = $lead->contact_person ?: $lead->company_name ?: 'Customer';
            $companyName = tenant()?->name ?: 'Demo ERP';

            $agentClient = 'crm_agent_' . (auth()->id() ?: 1);
            // Construct live Two-Way Bridge TwiML payload connecting customer phone directly to CRM browser agent
            $twiml = "<Response>" .
                     "<Dial callerId=\"{$fromNumber}\" record=\"record-from-answer-dual\" trim=\"trim-silence\">" .
                     "<Client>{$agentClient}</Client>" .
                     "</Dial>" .
                     "</Response>";

            $response = Http::asForm()
                ->withBasicAuth($accountSid, $authToken)
                ->timeout(15)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls.json", [
                    'To' => $cleanNumber,
                    'From' => $fromNumber,
                    'Twiml' => $twiml,
                    'Record' => 'true',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => "Live Twilio Call initiated to {$cleanNumber}! Phone is ringing.",
                    'call_sid' => $data['sid'] ?? null,
                    'status' => $data['status'] ?? 'queued',
                    'to' => $cleanNumber,
                    'from' => $fromNumber,
                ]);
            }

            $error = $response->json();
            $errorMessage = $error['message'] ?? 'Twilio API call failed with status: ' . $response->status();

            return response()->json([
                'success' => false,
                'message' => 'Twilio Error: ' . $errorMessage,
            ], 400);

        } catch (\Throwable $e) {
            Log::error('Twilio Outbound Call Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate call: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Terminate / Hangup an active Twilio outbound call.
     */
    public function terminateOutboundCall(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $callSid = $request->input('call_sid');
        $tenantId = $lead->tenant_id ?? (current_tenant_id() ?? 1);
        $twilioConfig = TwilioConfiguration::where('tenant_id', $tenantId)->first();

        if ($twilioConfig && !empty($twilioConfig->account_sid) && !empty($twilioConfig->auth_token)) {
            try {
                $accountSid = trim($twilioConfig->account_sid);
                $authToken = trim($twilioConfig->auth_token);

                if (!empty($callSid)) {
                    // 1. Terminate the main call leg
                    Http::asForm()
                        ->withBasicAuth($accountSid, $authToken)
                        ->timeout(6)
                        ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls/{$callSid}.json", [
                            'Status' => 'completed',
                        ]);

                    // 2. Terminate any child/bridged call legs
                    $childRes = Http::withBasicAuth($accountSid, $authToken)
                        ->timeout(6)
                        ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls.json?ParentCallSid={$callSid}&Status=in-progress");
                    if ($childRes->successful()) {
                        foreach ($childRes->json('calls') ?? [] as $childCall) {
                            if (!empty($childCall['sid'])) {
                                Http::asForm()
                                    ->withBasicAuth($accountSid, $authToken)
                                    ->timeout(5)
                                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls/{$childCall['sid']}.json", [
                                        'Status' => 'completed',
                                    ]);
                            }
                        }
                    }
                } else {
                    // Safety fallback: Hang up any active in-progress calls
                    $activeRes = Http::withBasicAuth($accountSid, $authToken)
                        ->timeout(6)
                        ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls.json?Status=in-progress");
                    if ($activeRes->successful()) {
                        foreach ($activeRes->json('calls') ?? [] as $activeCall) {
                            if (!empty($activeCall['sid'])) {
                                Http::asForm()
                                    ->withBasicAuth($accountSid, $authToken)
                                    ->timeout(5)
                                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls/{$activeCall['sid']}.json", [
                                        'Status' => 'completed',
                                    ]);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Twilio hangup notice: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true, 'message' => 'Call disconnected on telephony network.']);
    }

    /**
     * Check real-time call status from Twilio (e.g. ringing, in-progress, completed, busy, no-answer, canceled, failed).
     */
    public function checkCallStatus(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $callSid = $request->input('call_sid');
        if (!$callSid) {
            return response()->json(['success' => false, 'status' => 'unknown', 'message' => 'No Call SID provided.']);
        }

        $tenantId = $lead->tenant_id ?? (current_tenant_id() ?? 1);
        $twilioConfig = TwilioConfiguration::where('tenant_id', $tenantId)->first();

        if (!$twilioConfig || empty($twilioConfig->account_sid) || empty($twilioConfig->auth_token)) {
            return response()->json(['success' => false, 'status' => 'unknown']);
        }

        try {
            $accountSid = trim($twilioConfig->account_sid);
            $authToken = trim($twilioConfig->auth_token);

            $response = Http::withBasicAuth($accountSid, $authToken)
                ->timeout(6)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls/{$callSid}.json");

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'status' => $data['status'] ?? 'unknown',
                    'duration' => (int)($data['duration'] ?? 0),
                    'answered_by' => $data['answered_by'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Twilio checkCallStatus error: ' . $e->getMessage());
        }

        return response()->json(['success' => false, 'status' => 'unknown']);
    }

    /**
     * Generate Twilio Voice JWT Token for in-browser WebRTC two-way audio calling.
     */
    /**
     * Generate Twilio Voice JWT Token for in-browser WebRTC two-way audio calling.
     */
    public function generateVoiceToken(Request $request): JsonResponse
    {
        $tenantId = current_tenant_id() ?? (auth()->user()?->tenant_id ?? 1);
        $twilioConfig = TwilioConfiguration::where('tenant_id', $tenantId)->first();

        if (!$twilioConfig || empty($twilioConfig->account_sid) || empty($twilioConfig->auth_token)) {
            return response()->json([
                'success' => false,
                'message' => 'Twilio credentials not configured.',
            ], 422);
        }

        $accountSid = trim($twilioConfig->account_sid);
        $authToken = trim($twilioConfig->auth_token);
        $appSid = trim($twilioConfig->twiml_app_sid ?: '');
        $identity = 'crm_agent_' . (auth()->id() ?: 1);

        $extra = $twilioConfig->extra_metadata ?: [];
        $apiKeySid = $extra['api_key_sid'] ?? null;
        $apiKeySecret = $extra['api_key_secret'] ?? null;

        if (!$apiKeySid || !$apiKeySecret) {
            try {
                $keyRes = Http::withBasicAuth($accountSid, $authToken)
                    ->timeout(10)
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Keys.json", [
                        'FriendlyName' => 'CRM Voice WebRTC Key'
                    ]);
                if ($keyRes->successful()) {
                    $keyData = $keyRes->json();
                    $apiKeySid = $keyData['sid'] ?? null;
                    $apiKeySecret = $keyData['secret'] ?? null;
                    if ($apiKeySid && $apiKeySecret) {
                        $extra['api_key_sid'] = $apiKeySid;
                        $extra['api_key_secret'] = $apiKeySecret;
                        $twilioConfig->extra_metadata = $extra;
                        $twilioConfig->save();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to generate Twilio API Key: ' . $e->getMessage());
            }
        }

        $token = $this->buildTwilioJwtToken($accountSid, $authToken, $appSid, $identity, $apiKeySid, $apiKeySecret);

        return response()->json([
            'success' => true,
            'token' => $token,
            'identity' => $identity,
            'caller_id' => $twilioConfig->phone_number,
        ]);
    }

    /**
     * Public Twilio Voice Webhook: Bridges the Browser WebRTC client directly with the customer's phone.
     */
    public function handleTwilioVoiceWebhook(Request $request): \Illuminate\Http\Response
    {
        $toNumber = $request->input('To') ?: $request->input('to_number');
        $fromNumber = $request->input('From') ?: $request->input('Caller');

        $twilioConfig = TwilioConfiguration::where('is_active', true)->first();
        $callerId = $twilioConfig?->phone_number ?: $fromNumber;

        // Clean target number to E.164
        $cleanToNumber = preg_replace('/[^\d+]/', '', (string)$toNumber);
        if (!str_starts_with($cleanToNumber, '+')) {
            if (strlen($cleanToNumber) === 10) {
                $cleanToNumber = '+91' . $cleanToNumber;
            } else {
                $cleanToNumber = '+' . $cleanToNumber;
            }
        }

        // TwiML Dial response to bridge browser WebRTC audio with mobile telecom network
        $twiml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
                 '<Response>' .
                 '<Dial callerId="' . htmlspecialchars($callerId) . '" record="record-from-answer-dual" trim="trim-silence">' .
                 '<Number>' . htmlspecialchars($cleanToNumber) . '</Number>' .
                 '</Dial>' .
                 '</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Public Twilio Status Callback Webhook.
     */
    public function handleTwilioStatusWebhook(Request $request): \Illuminate\Http\Response
    {
        Log::info('Twilio Status Callback: ' . json_encode($request->all()));
        return response('<Response/>', 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Build standard Twilio Access Token JWT with VoiceGrant without external dependencies.
     */
    private function buildTwilioJwtToken(string $accountSid, string $authToken, string $appSid, string $identity, ?string $apiKeySid = null, ?string $apiKeySecret = null): string
    {
        $signingKeySid = $apiKeySid ?: $accountSid;
        $signingSecret = $apiKeySecret ?: $authToken;

        $now = time();
        $exp = $now + 86400; // 24 hours validity

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
            'cty' => 'twilio-fpa;v=1'
        ];

        $payload = [
            'jti' => $signingKeySid . '-' . $now . '-' . mt_rand(1000, 9999),
            'iss' => $signingKeySid,
            'sub' => $accountSid,
            'nbf' => $now,
            'exp' => $exp,
            'grants' => [
                'identity' => $identity,
                'voice' => [
                    'outgoing' => [
                        'application_sid' => $appSid
                    ],
                    'incoming' => [
                        'allow' => true
                    ]
                ]
            ]
        ];

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $signingSecret, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Query Google Gemini API with structured prompt & optional audio data.
     */
    private function queryGeminiAi(
        string $apiKey,
        Lead $lead,
        string $callNotes,
        ?LeadFollowup $pendingFollowup,
        ?string $audioBase64 = null,
        ?string $audioMime = 'audio/webm',
        ?string $dialedNumber = null
    ): ?array
    {
        try {
            $nowStr = now()->format('Y-m-d H:i:s');
            $todayDate = now()->format('Y-m-d');
            $tomorrowStr = now()->addDay()->format('Y-m-d');
            $contact = $lead->contact_person ?: $lead->company_name ?: 'Customer';

            $prompt = <<<PROMPT
You are an expert CRM Sales Voice & Audio AI Assistant.
Analyze the customer phone call from the provided dual-channel audio recording and/or sales conversation notes.

Lead & Call Information:
- Contact Person: {$contact}
- Company: {$lead->company_name}
- Dialed Phone Number: {$dialedNumber}
- Current Status: {$lead->status}
- Current Server Time: {$nowStr}
- Today Date: {$todayDate}
- Tomorrow Date: {$tomorrowStr}

Call Notes / Speech Context:
"{$callNotes}"

CRITICAL RULES & STRICT 2-WAY DIALOGUE:
1. "transcript": Return the full conversation in authentic TWO-WAY dialogue format between the CRM Agent and {$contact}. All dialogue MUST be translated and written in clear ENGLISH.
   Format:
   Agent: [Agent opening / discussion / inquiry]
   {$contact}: [Customer response / discussion / query / objection / requirement]
   Agent: [Agent response / next step agreement]
   
   If call was not answered or rejected, return "".
2. "summary": Summarize the complete conversation and what was agreed in 1-2 clear sentences in ENGLISH ONLY (translate any spoken Hindi/Hinglish to English).
   - If call was NOT answered, rejected, or cut without conversation, set summary to "Not answering".
   - If call failed to connect, set summary to "Disconnected".
   - DO NOT fabricate discussions.
3. "next_followup_date":
   - Detect any specific date or time discussed in Hindi or English (e.g. "after 2:00 pm", "2 baje ke baad", "3 pm", "kal 4 baje", "tomorrow morning", "somwar ko").
   - Calculate the exact ISO datetime in format "YYYY-MM-DDTHH:MM" based on Current Server Time ({$nowStr}).
   - If NO next follow-up date or time was discussed at all, return null.
4. "next_action":
   - Specific next step in ENGLISH ONLY (e.g. "Follow-up Call", "Send Quotation", "Schedule Demo", "Meeting").
   - If call was not answered, return "Retry Call".
5. "sentiment": One of ["Hot / High Intent", "Interested", "Neutral", "Cold / Not Interested"].
6. "suggested_lead_status": One of ["Contacted", "Qualified", "Dealing", "Won", "Lost"] or keep "{$lead->status}".
7. "existing_meeting_update": A 1-sentence note in ENGLISH stating what happened.

LANGUAGE RULE: All output values MUST be in ENGLISH ONLY. Do NOT return Devanagari/Hindi script in any JSON field.

Return ONLY a valid JSON object with keys: transcript, summary, next_action, next_followup_date, sentiment, suggested_lead_status, existing_meeting_update.
PROMPT;

            $parts = [];
            if (!empty($audioBase64)) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $audioMime ?: 'audio/webm',
                        'data' => $audioBase64
                    ]
                ];
            }
            $parts[] = ['text' => $prompt];

            $models = ['gemini-3.5-flash', 'gemini-3.8-flash', 'gemini-flash-latest'];
            foreach ($models as $modelName) {
                try {
                    $response = Http::timeout(20)
                        ->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}", [
                            'contents' => [
                                [
                                    'parts' => $parts
                                ]
                            ],
                            'generationConfig' => [
                                'response_mime_type' => 'application/json',
                                'temperature' => 0.1,
                            ]
                        ]);

                    if ($response->successful()) {
                        $rawText = $response->json('candidates.0.content.parts.0.text');
                        if ($rawText) {
                            $json = json_decode($rawText, true);
                            if (is_array($json)) {
                                return $json;
                            }
                        }
                    } else {
                        Log::warning("Gemini model {$modelName} returned status " . $response->status() . ": " . $response->body());
                    }
                } catch (\Throwable $mErr) {
                    Log::warning("Gemini model {$modelName} error: " . $mErr->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini API call analysis failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Strict heuristic extraction when Gemini API is offline (No dummy data).
     */
    private function generateHeuristicAnalysis(Lead $lead, string $notes, ?LeadFollowup $pendingFollowup): array
    {
        $lower = strtolower(trim($notes));
        
        // 1. Not Answered / Rejected / Busy
        if (empty($notes) || $lower === 'not answering' || str_contains($lower, 'not answer') || str_contains($lower, 'rejected') || str_contains($lower, 'busy') || str_contains($lower, 'no-answer') || str_contains($lower, 'declined')) {
            return [
                'summary' => 'Not answering',
                'transcript' => '',
                'next_action' => 'Retry Call',
                'next_followup_date' => null,
                'sentiment' => 'Neutral',
                'suggested_lead_status' => $lead->status === 'New' ? 'Contacted' : $lead->status,
                'existing_meeting_update' => 'Call was not answered / rejected by customer.',
            ];
        }

        // 2. Disconnected / Failed
        if ($lower === 'disconnected' || str_contains($lower, 'disconnect') || str_contains($lower, 'failed')) {
            return [
                'summary' => 'Disconnected',
                'transcript' => '',
                'next_action' => 'Retry Call',
                'next_followup_date' => null,
                'sentiment' => 'Neutral',
                'suggested_lead_status' => $lead->status,
                'existing_meeting_update' => 'Call could not connect or disconnected.',
            ];
        }

        // 3. Conversation Sentiment
        $sentiment = 'Interested';
        if (str_contains($lower, 'urgent') || str_contains($lower, 'ready') || str_contains($lower, 'order') || str_contains($lower, 'immediately')) {
            $sentiment = 'Hot / High Intent';
        } elseif (str_contains($lower, 'not interested') || str_contains($lower, 'wrong number')) {
            $sentiment = 'Cold / Not Interested';
        }

        // 4. Suggested Status
        $suggestedStatus = $lead->status === 'New' ? 'Contacted' : $lead->status;
        if (str_contains($lower, 'quotation') || str_contains($lower, 'price') || str_contains($lower, 'quote')) {
            $suggestedStatus = 'Dealing';
        } elseif ($sentiment === 'Hot / High Intent') {
            $suggestedStatus = 'Qualified';
        }

        // 5. Next Action & Date (Only if explicitly mentioned)
        $nextAction = 'Follow-up Call';
        $nextDate = null;

        if (str_contains($lower, 'quote') || str_contains($lower, 'quotation')) {
            $nextAction = 'Send Quotation';
        } elseif (str_contains($lower, 'demo') || str_contains($lower, 'meeting')) {
            $nextAction = 'Product Demo Meeting';
        }

        if (str_contains($lower, 'today')) {
            $nextDate = now()->setHour(17)->setMinute(0);
        } elseif (str_contains($lower, 'tomorrow') || str_contains($lower, 'kal')) {
            $nextDate = now()->addDay()->setHour(15)->setMinute(0);
        } elseif (str_contains($lower, 'next week') || str_contains($lower, 'monday')) {
            $nextDate = now()->next(Carbon::MONDAY)->setHour(11)->setMinute(0);
        }

        return [
            'summary' => $notes,
            'transcript' => $notes,
            'next_action' => $nextAction,
            'next_followup_date' => $nextDate ? $nextDate->format('Y-m-d H:i') : null,
            'sentiment' => $sentiment,
            'suggested_lead_status' => $suggestedStatus,
            'existing_meeting_update' => "Call conducted: {$notes}",
        ];
    }

    // =========================================================================
    // CRM DEAL CALLING & GEMINI AI AUDIO ANALYSIS
    // =========================================================================

    /**
     * Initiate real outbound phone call for a CRM Deal via Twilio Cloud Telephony.
     */
    public function initiateDealOutboundCall(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        $request->validate([
            'to_number' => ['required', 'string', 'max:30'],
        ]);

        $rawNumber = trim((string)$request->input('to_number'));
        $cleanNumber = preg_replace('/[^\d+]/', '', $rawNumber);
        if (!str_starts_with($cleanNumber, '+')) {
            if (strlen($cleanNumber) === 10) {
                $cleanNumber = '+91' . $cleanNumber;
            } else {
                $cleanNumber = '+' . $cleanNumber;
            }
        }

        $tenantId = $deal->tenant_id ?? (current_tenant_id() ?? 1);
        $twilioConfig = TwilioConfiguration::where('tenant_id', $tenantId)->first();

        if (!$twilioConfig || empty($twilioConfig->account_sid) || empty($twilioConfig->auth_token) || empty($twilioConfig->phone_number)) {
            return response()->json([
                'success' => false,
                'message' => 'Twilio is not configured for this tenant. Please setup Twilio credentials in Platform Settings.',
            ], 422);
        }

        try {
            $accountSid = trim($twilioConfig->account_sid);
            $authToken = trim($twilioConfig->auth_token);
            $fromNumber = trim($twilioConfig->phone_number);

            $agentClient = 'crm_agent_' . (auth()->id() ?: 1);
            $twiml = "<Response>" .
                     "<Dial callerId=\"{$fromNumber}\" record=\"record-from-answer-dual\" trim=\"trim-silence\">" .
                     "<Client>{$agentClient}</Client>" .
                     "</Dial>" .
                     "</Response>";

            $response = Http::asForm()
                ->withBasicAuth($accountSid, $authToken)
                ->timeout(15)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls.json", [
                    'To' => $cleanNumber,
                    'From' => $fromNumber,
                    'Twiml' => $twiml,
                    'Record' => 'true',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => "Live Twilio Call initiated to {$cleanNumber}! Phone is ringing.",
                    'call_sid' => $data['sid'] ?? null,
                    'status' => $data['status'] ?? 'queued',
                    'to' => $cleanNumber,
                    'from' => $fromNumber,
                ]);
            }

            $error = $response->json();
            $errorMessage = $error['message'] ?? 'Twilio API call failed with status: ' . $response->status();

            return response()->json([
                'success' => false,
                'message' => 'Twilio Error: ' . $errorMessage,
            ], 400);

        } catch (\Throwable $e) {
            Log::error('Twilio Deal Outbound Call Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate call: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Terminate an active outbound call for a Deal.
     */
    public function terminateDealOutboundCall(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        $callSid = $request->input('call_sid');
        $tenantId = $deal->tenant_id ?? (current_tenant_id() ?? 1);
        $twilioConfig = TwilioConfiguration::where('tenant_id', $tenantId)->first();

        if ($twilioConfig && !empty($twilioConfig->account_sid) && !empty($twilioConfig->auth_token)) {
            try {
                $accountSid = trim($twilioConfig->account_sid);
                $authToken = trim($twilioConfig->auth_token);

                if (!empty($callSid)) {
                    // 1. Terminate the main call leg
                    Http::asForm()
                        ->withBasicAuth($accountSid, $authToken)
                        ->timeout(6)
                        ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls/{$callSid}.json", [
                            'Status' => 'completed',
                        ]);

                    // 2. Terminate any child/bridged call legs
                    $childRes = Http::withBasicAuth($accountSid, $authToken)
                        ->timeout(6)
                        ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls.json?ParentCallSid={$callSid}&Status=in-progress");
                    if ($childRes->successful()) {
                        foreach ($childRes->json('calls') ?? [] as $childCall) {
                            if (!empty($childCall['sid'])) {
                                Http::asForm()
                                    ->withBasicAuth($accountSid, $authToken)
                                    ->timeout(5)
                                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls/{$childCall['sid']}.json", [
                                        'Status' => 'completed',
                                    ]);
                            }
                        }
                    }
                } else {
                    // Safety fallback: Hang up any active in-progress calls
                    $activeRes = Http::withBasicAuth($accountSid, $authToken)
                        ->timeout(6)
                        ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls.json?Status=in-progress");
                    if ($activeRes->successful()) {
                        foreach ($activeRes->json('calls') ?? [] as $activeCall) {
                            if (!empty($activeCall['sid'])) {
                                Http::asForm()
                                    ->withBasicAuth($accountSid, $authToken)
                                    ->timeout(5)
                                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls/{$activeCall['sid']}.json", [
                                        'Status' => 'completed',
                                    ]);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Twilio deal call hangup notice: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true, 'message' => 'Call disconnected on telephony network.']);
    }

    /**
     * Check real-time call status for Deal.
     */
    public function checkDealCallStatus(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        $callSid = $request->input('call_sid');
        if (!$callSid) {
            return response()->json(['success' => false, 'status' => 'unknown', 'message' => 'No Call SID provided.']);
        }

        $tenantId = $deal->tenant_id ?? (current_tenant_id() ?? 1);
        $twilioConfig = TwilioConfiguration::where('tenant_id', $tenantId)->first();

        if (!$twilioConfig || empty($twilioConfig->account_sid) || empty($twilioConfig->auth_token)) {
            return response()->json(['success' => false, 'status' => 'unknown']);
        }

        try {
            $accountSid = trim($twilioConfig->account_sid);
            $authToken = trim($twilioConfig->auth_token);

            $response = Http::withBasicAuth($accountSid, $authToken)
                ->timeout(6)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls/{$callSid}.json");

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'status' => $data['status'] ?? 'unknown',
                    'duration' => (int)($data['duration'] ?? 0),
                    'answered_by' => $data['answered_by'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Twilio checkDealCallStatus error: ' . $e->getMessage());
        }

        return response()->json(['success' => false, 'status' => 'unknown']);
    }

    /**
     * Analyze Deal Call Conversation & Audio Recording using Google Gemini AI.
     */
    public function analyzeDealCall(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        $request->validate([
            'call_notes' => ['nullable', 'string', 'max:10000'],
            'duration_seconds' => ['nullable', 'integer'],
            'audio_file' => ['nullable', 'file', 'max:25600'],
            'audio_base64' => ['nullable', 'string'],
            'dialed_number' => ['nullable', 'string', 'max:50'],
            'call_sid' => ['nullable', 'string', 'max:100'],
        ]);

        $callNotes = trim((string)$request->input('call_notes', ''));
        $durationSeconds = (int)$request->input('duration_seconds', 60);
        $dialedNumber = $request->input('dialed_number') ?: ($deal->contact?->phone ?: ($deal->account?->phone ?: ($deal->lead?->phone)));
        $callSid = $request->input('call_sid');

        // Handle Audio Upload / Base64 from Browser
        $audioBase64 = null;
        $audioMime = 'audio/webm';
        if ($request->hasFile('audio_file')) {
            $file = $request->file('audio_file');
            $audioBase64 = base64_encode(file_get_contents($file->getRealPath()));
            $audioMime = $file->getMimeType() ?: 'audio/webm';
        } elseif ($request->filled('audio_base64')) {
            $rawBase64 = $request->input('audio_base64');
            if (preg_match('/^data:(audio\/[^;]+);base64,(.+)$/', $rawBase64, $matches)) {
                $audioMime = $matches[1];
                $audioBase64 = $matches[2];
            } else {
                $audioBase64 = preg_replace('/^data:audio\/[^;]+;base64,/', '', $rawBase64);
            }
        }

        $tenantId = $deal->tenant_id ?? (current_tenant_id() ?? 1);
        $twilioConfig = TwilioConfiguration::where('tenant_id', $tenantId)->first();
        $apiKey = $twilioConfig?->gemini_api_key ?: (config('services.gemini.api_key') ?: env('GEMINI_API_KEY'));
        if (empty($apiKey)) {
            $fallbackConfig = TwilioConfiguration::whereNotNull('gemini_api_key')->where('gemini_api_key', '!=', '')->first();
            $apiKey = $fallbackConfig?->gemini_api_key;
        }

        // Fetch Twilio Dual-Channel Audio Recording with retry
        if ($twilioConfig && !empty($twilioConfig->account_sid) && !empty($twilioConfig->auth_token)) {
            try {
                $accSid = trim($twilioConfig->account_sid);
                $authTok = trim($twilioConfig->auth_token);

                for ($attempt = 1; $attempt <= 2; $attempt++) {
                    $endpoint = !empty($callSid)
                        ? "https://api.twilio.com/2010-04-01/Accounts/{$accSid}/Recordings.json?CallSid={$callSid}&PageSize=1"
                        : "https://api.twilio.com/2010-04-01/Accounts/{$accSid}/Recordings.json?PageSize=1";

                    $recRes = Http::withBasicAuth($accSid, $authTok)->timeout(8)->get($endpoint);

                    if ($recRes->successful()) {
                        $recordings = $recRes->json('recordings') ?? [];
                        if (!empty($recordings[0]['sid'])) {
                            $recSid = $recordings[0]['sid'];
                            $mp3Res = Http::withBasicAuth($accSid, $authTok)
                                ->timeout(15)
                                ->get("https://api.twilio.com/2010-04-01/Accounts/{$accSid}/Recordings/{$recSid}.mp3");
                            if ($mp3Res->successful() && strlen($mp3Res->body()) > 500) {
                                $audioBase64 = base64_encode($mp3Res->body());
                                $audioMime = 'audio/mp3';
                                break;
                            }
                        }
                    }
                    if ($attempt < 2 && empty($audioBase64)) {
                        usleep(1000000); // 1.0s wait for Twilio
                    }
                }
            } catch (\Throwable $recErr) {
                Log::warning('Twilio deal call recording fetch notice: ' . $recErr->getMessage());
            }
        }

        // Save Call Audio Recording to Local Storage for Playback
        $recordingUrl = null;
        if (!empty($audioBase64)) {
            try {
                $rawAudio = base64_decode($audioBase64);
                if ($rawAudio && strlen($rawAudio) > 100) {
                    $ext = ($audioMime === 'audio/mp3' || $audioMime === 'audio/mpeg') ? 'mp3' : 'webm';
                    $fileName = 'deal_call_' . $deal->id . '_' . time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
                    Storage::disk('public')->put('call_recordings/' . $fileName, $rawAudio);
                    $recordingUrl = 'storage/call_recordings/' . $fileName;
                }
            } catch (\Throwable $fileErr) {
                Log::warning('Deal call audio storage notice: ' . $fileErr->getMessage());
            }
        }

        if (empty($callNotes) && empty($audioBase64)) {
            if ($durationSeconds <= 5) {
                $callNotes = "Not answering";
            } else {
                $callNotes = "";
            }
        }

        $pendingFollowup = LeadFollowup::where('crm_deal_id', $deal->id)
            ->where('status', 'Pending')
            ->latest('followup_date')
            ->first();

        $formatIsoDate = function($val) {
            if (!$val || strtolower((string)$val) === 'null' || strtolower((string)$val) === 'none' || trim((string)$val) === '') return null;
            try {
                return Carbon::parse($val)->format('Y-m-d\TH:i');
            } catch (\Throwable $e) {
                return null;
            }
        };

        $extractDateFromText = function(string $text) {
            $lower = strtolower($text);
            $now = Carbon::now();
            
            $hour = null;
            $minute = 0;
            $isTomorrow = str_contains($lower, 'kal') || str_contains($lower, 'tomorrow') || str_contains($lower, 'next day');
            
            if (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(?:p\.?m\.?|pm|afternoon|sham|shaam|dopahar)/i', $lower, $m)) {
                $h = (int)$m[1];
                if ($h < 12) $h += 12;
                $hour = $h;
                $minute = isset($m[2]) ? (int)$m[2] : 0;
            } elseif (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(?:a\.?m\.?|am|subah|morning)/i', $lower, $m)) {
                $h = (int)$m[1];
                if ($h === 12) $h = 0;
                $hour = $h;
                $minute = isset($m[2]) ? (int)$m[2] : 0;
            } elseif (preg_match('/(?:after|post|ke baad|baad|at)\s*(\d{1,2})(?::(\d{2}))?/i', $lower, $m)) {
                $h = (int)$m[1];
                if ($h >= 1 && $h <= 7) $h += 12;
                $hour = $h;
                $minute = isset($m[2]) ? (int)$m[2] : 0;
            } elseif (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(?:baje|ghante)/i', $lower, $m)) {
                $h = (int)$m[1];
                if ($h >= 1 && $h <= 7) $h += 12;
                $hour = $h;
                $minute = isset($m[2]) ? (int)$m[2] : 0;
            }

            if (preg_match('/(\d{1,2})(?:st|nd|rd|th)?\s*(?:of\s*)?(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)/i', $lower, $m)) {
                try {
                    $day = (int)$m[1];
                    $monthStr = $m[2];
                    $targetDate = Carbon::parse("{$day} {$monthStr} {$now->year}");
                    $targetDate->setHour($hour !== null ? $hour : 11)->setMinute($minute)->setSecond(0);
                    return $targetDate->format('Y-m-d\TH:i');
                } catch (\Throwable $e) {}
            }
            
            if ($hour !== null) {
                $targetDate = $isTomorrow ? $now->copy()->addDay() : $now->copy();
                if (!$isTomorrow && $targetDate->hour >= $hour) {
                    $targetDate->addDay();
                }
                $targetDate->setHour($hour)->setMinute($minute)->setSecond(0);
                return $targetDate->format('Y-m-d\TH:i');
            } elseif ($isTomorrow) {
                return $now->copy()->addDay()->setHour(11)->setMinute(0)->format('Y-m-d\TH:i');
            }
            
            return null;
        };

        $contactName = $deal->contact?->name ?: ($deal->account?->name ?: ($deal->lead?->contact_person ?: $deal->lead?->company_name ?: 'Client'));

        if (!empty($apiKey)) {
            $geminiResult = $this->queryGeminiDealAi($apiKey, $deal, $callNotes, $pendingFollowup, $audioBase64, $audioMime, $dialedNumber);
            if ($geminiResult) {
                $summary = trim($geminiResult['summary'] ?? ($callNotes ?: 'Not answering'));
                $transcript = trim($geminiResult['transcript'] ?? $callNotes);
                $nextDateIso = $formatIsoDate($geminiResult['next_followup_date'] ?? null);
                if (empty($nextDateIso)) {
                    $nextDateIso = $extractDateFromText($transcript . ' ' . $summary . ' ' . $callNotes);
                }
                $nextAction = trim($geminiResult['next_action'] ?? ($summary === 'Not answering' ? 'Retry Call' : 'Follow-up Call'));

                $followupMsg = ($summary === 'Not answering' || $summary === 'Disconnected') ? '' : 
                    "Hi {$contactName}, thank you for speaking with us today! As discussed, {$summary}." . 
                    ($nextDateIso ? " We look forward to connecting again on " . Carbon::parse($nextDateIso)->format('d M Y, h:i A') . "." : "");

                return response()->json([
                    'success' => true,
                    'ai_powered' => true,
                    'transcript' => $transcript,
                    'summary' => $summary,
                    'discussion_summary' => $summary,
                    'next_action' => $nextAction,
                    'next_activity_title' => $nextAction,
                    'next_activity_type' => str_contains(strtolower($nextAction), 'meeting') ? 'Meeting' : (str_contains(strtolower($nextAction), 'demo') ? 'Demo' : (str_contains(strtolower($nextAction), 'whatsapp') ? 'WhatsApp' : 'Call')),
                    'next_followup_date' => $nextDateIso,
                    'sentiment' => $geminiResult['sentiment'] ?? ($summary === 'Not answering' ? 'Neutral' : 'Interested'),
                    'suggested_stage' => $geminiResult['suggested_stage'] ?? $deal->stage,
                    'existing_meeting_update' => $geminiResult['existing_meeting_update'] ?? null,
                    'followup_message_preview' => $followupMsg,
                    'dialed_number' => $dialedNumber,
                    'recording_url' => $recordingUrl,
                    'audio_duration' => $durationSeconds,
                    'has_pending_followup' => (bool)$pendingFollowup,
                    'pending_followup_id' => $pendingFollowup?->id,
                ]);
            }
        }

        $fallback = $this->generateHeuristicDealAnalysis($deal, $callNotes, $pendingFollowup);
        $summary = $fallback['summary'];
        $transcript = $fallback['transcript'];
        $nextDateIso = $formatIsoDate($fallback['next_followup_date'] ?? null) ?: $extractDateFromText($callNotes);
        $nextAction = $fallback['next_action'];

        $followupMsg = ($summary === 'Not answering' || $summary === 'Disconnected') ? '' : 
            "Hi {$contactName}, thank you for speaking with us today! As discussed, {$summary}." . 
            ($nextDateIso ? " We look forward to connecting again on " . Carbon::parse($nextDateIso)->format('d M Y, h:i A') . "." : "");

        return response()->json([
            'success' => true,
            'ai_powered' => false,
            'transcript' => $transcript,
            'summary' => $summary,
            'discussion_summary' => $summary,
            'next_action' => $nextAction,
            'next_activity_title' => $nextAction,
            'next_activity_type' => str_contains(strtolower($nextAction), 'meeting') ? 'Meeting' : (str_contains(strtolower($nextAction), 'demo') ? 'Demo' : (str_contains(strtolower($nextAction), 'whatsapp') ? 'WhatsApp' : 'Call')),
            'next_followup_date' => $nextDateIso,
            'sentiment' => $fallback['sentiment'],
            'suggested_stage' => $fallback['suggested_stage'],
            'existing_meeting_update' => $fallback['existing_meeting_update'],
            'followup_message_preview' => $followupMsg,
            'dialed_number' => $dialedNumber,
            'recording_url' => $recordingUrl,
            'audio_duration' => $durationSeconds,
            'has_pending_followup' => (bool)$pendingFollowup,
            'pending_followup_id' => $pendingFollowup?->id,
        ]);
    }

    /**
     * Confirm Deal Call Follow-up & Activity in CRM.
     */
    public function confirmDealCallFollowup(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        $request->validate([
            'summary' => ['required', 'string', 'max:5000'],
            'transcript' => ['nullable', 'string', 'max:20000'],
            'duration_seconds' => ['nullable', 'integer'],
            'next_followup_date' => ['nullable', 'date'],
            'next_action' => ['nullable', 'string', 'max:100'],
            'next_activity_type' => ['nullable', 'string', 'max:50'],
            'stage' => ['nullable', 'string', 'max:50'],
            'deal_stage' => ['nullable', 'string', 'max:50'],
            'sentiment' => ['nullable', 'string', 'max:50'],
            'pending_followup_id' => ['nullable', 'integer'],
            'recording_url' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = $deal->tenant_id ?? (current_tenant_id() ?? 1);
        $companyId = $deal->company_id ?? current_company_id();
        $branchId = $deal->branch_id ?? current_branch_id();
        $userId = auth()->id() ?: ($deal->owner_id ?: 1);

        DB::beginTransaction();
        try {
            $durationSeconds = (int)($request->input('duration_seconds', 60));
            $summary = trim($request->input('summary'));
            $transcript = trim((string)$request->input('transcript', ''));
            $sentiment = $request->input('sentiment') ?: 'Interested';
            $nextFollowupDate = !empty($request->input('next_followup_date')) ? Carbon::parse($request->input('next_followup_date')) : null;
            $nextAction = $request->input('next_action') ?: ($request->input('next_activity_type') ?: 'Follow-up Call');
            $recordingUrl = $request->input('recording_url') ?: null;

            // 1. Mark existing pending followup as completed
            if (!empty($request->input('pending_followup_id'))) {
                $pendingFollowup = LeadFollowup::find($request->input('pending_followup_id'));
                if ($pendingFollowup && $pendingFollowup->crm_deal_id === $deal->id) {
                    $pendingFollowup->status = 'Completed';
                    $pendingFollowup->notes = ($pendingFollowup->notes ? $pendingFollowup->notes . "\n\n" : '') . 
                        "[Call Completed on " . now()->format('d M Y h:i A') . "]\n" . $summary;
                    $pendingFollowup->save();
                }
            }

            $contactPerson = $deal->contact?->name ?: ($deal->account?->name ?: ($deal->lead?->contact_person ?: $deal->lead?->company_name ?: $deal->title));

            // Format full discussion notes with conversation details
            $notesContent = $summary;
            if (!empty($transcript) && $transcript !== $summary) {
                $notesContent .= "\n\n[Conversation Transcript / Discussion]:\n" . $transcript;
            }
            if ($sentiment && $summary !== 'Not answering' && $summary !== 'Disconnected') {
                $notesContent .= "\n\n[Deal Sentiment: {$sentiment}]";
            }

            // 2. Create Completed Call Record / Interaction Log
            $callFollowup = new LeadFollowup();
            $callFollowup->tenant_id = $tenantId;
            $callFollowup->company_id = $companyId;
            $callFollowup->branch_id = $branchId;
            $callFollowup->crm_deal_id = $deal->id;
            $callFollowup->lead_id = $deal->lead?->id ?? null;
            $callFollowup->tagged_user_id = $userId;
            $callFollowup->type = 'Call';
            $callFollowup->recording_url = $recordingUrl;
            $callFollowup->audio_duration = $durationSeconds;
            
            if ($summary === 'Not answering') {
                $callFollowup->status = 'Not Answering';
                $callFollowup->title = "Phone Call (Not Answered) - " . $contactPerson;
            } elseif ($summary === 'Disconnected') {
                $callFollowup->status = 'Not Connected';
                $callFollowup->title = "Phone Call (Disconnected) - " . $contactPerson;
            } else {
                $callFollowup->status = 'Completed';
                $callFollowup->title = "Phone Call (" . gmdate("i:s", $durationSeconds) . "s) with " . $contactPerson;
            }

            $callFollowup->duration_minutes = max(1, ceil($durationSeconds / 60));
            $callFollowup->followup_date = now();
            $callFollowup->notes = $notesContent;
            $callFollowup->save();

            // 3. If Next Followup Date provided, schedule new pending activity
            if ($nextFollowupDate) {
                // Check if an identical pending followup was already created in the last 60 seconds
                $existingDuplicate = LeadFollowup::where('crm_deal_id', $deal->id)
                    ->where('status', 'Pending')
                    ->where('followup_date', $nextFollowupDate)
                    ->where('created_at', '>=', now()->subSeconds(60))
                    ->first();

                if (!$existingDuplicate) {
                    $newFollowup = new LeadFollowup();
                    $newFollowup->tenant_id = $tenantId;
                    $newFollowup->company_id = $companyId;
                    $newFollowup->branch_id = $branchId;
                    $newFollowup->crm_deal_id = $deal->id;
                    $newFollowup->lead_id = $deal->lead?->id ?? null;
                    $newFollowup->tagged_user_id = $userId;
                    $newFollowup->type = str_contains(strtolower($nextAction), 'meeting') ? 'Meeting' : (str_contains(strtolower($nextAction), 'demo') ? 'Demo' : 'Call');
                    $newFollowup->status = 'Pending';
                    $newFollowup->title = $nextAction . " - " . ($deal->title ?: $contactPerson);
                    $newFollowup->followup_date = $nextFollowupDate;
                    $newFollowup->notes = "Scheduled action: " . $nextAction . "\nContext from call: " . $summary;
                    $newFollowup->save();
                }
            }

            // 4. Update Deal Stage
            $stage = $request->input('deal_stage') ?: ($request->input('stage') ?: null);
            if (!empty($stage) && $stage !== 'Select an Option' && $stage !== 'Select Option') {
                $deal->stage = $stage;
            }
            $deal->save();

            // Also log history on linked lead if exists
            if ($deal->lead) {
                LeadHistory::logEvent(
                    $deal->lead,
                    'deal_call_completed_ai',
                    null,
                    $deal->stage,
                    "Phone Call on Deal #{$deal->deal_number} logged with AI assistant. Summary: {$summary}." . ($nextFollowupDate ? " Next follow-up scheduled for " . $nextFollowupDate->format('d M Y, h:i A') : '')
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Deal call logged and follow-up scheduled successfully!',
                'deal_id' => $deal->id,
                'stage' => $deal->stage,
                'next_followup_formatted' => $nextFollowupDate ? $nextFollowupDate->format('d M Y, h:i A') : null,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Deal call confirmation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save deal call follow-up: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Query Google Gemini API for Deal analysis.
     */
    private function queryGeminiDealAi(
        string $apiKey,
        CrmDeal $deal,
        string $callNotes,
        ?LeadFollowup $pendingFollowup,
        ?string $audioBase64 = null,
        ?string $audioMime = 'audio/webm',
        ?string $dialedNumber = null
    ): ?array
    {
        try {
            $nowStr = now()->format('Y-m-d H:i:s');
            $todayDate = now()->format('Y-m-d');
            $tomorrowStr = now()->addDay()->format('Y-m-d');
            $contact = $deal->contact?->name ?: ($deal->account?->name ?: ($deal->lead?->contact_person ?: $deal->lead?->company_name ?: 'Customer'));
            $company = $deal->account?->name ?: ($deal->lead?->company_name ?: 'N/A');

            $prompt = <<<PROMPT
You are an expert CRM Enterprise Sales Voice & Audio AI Assistant.
Analyze the customer phone call for this sales deal from the provided dual-channel audio recording and/or notes.

Deal Information:
- Deal Number: {$deal->deal_number}
- Deal Title: {$deal->title}
- Contact Person: {$contact}
- Account / Company: {$company}
- Estimated Value: {$deal->estimated_value}
- Current Stage: {$deal->stage}
- Dialed Phone Number: {$dialedNumber}
- Current Server Time: {$nowStr}
- Today Date: {$todayDate}
- Tomorrow Date: {$tomorrowStr}

Call Notes / Speech Context:
"{$callNotes}"

CRITICAL RULES & STRICT 2-WAY DIALOGUE:
1. "transcript": Return the full conversation in realistic TWO-WAY dialogue format between the CRM Sales Executive and {$contact}. All dialogue MUST be translated and written in clear ENGLISH.
   Format:
   Agent: [Agent opening / discussion / proposal point]
   {$contact}: [Customer response / discussion / deal requirements / pricing discussion / agreement]
   Agent: [Agent closing / next step]
   
   If call was not answered or rejected, return "".
2. "summary": Summarize the real conversation that took place in 1-2 clear sentences in ENGLISH ONLY (translate any spoken Hindi/Hinglish to English).
   - If call was NOT answered, rejected, or cut without conversation, set summary to "Not answering".
   - If call failed to connect, set summary to "Disconnected".
   - DO NOT fabricate discussions.
3. "next_followup_date":
   - Detect any specific date/time discussed in Hindi or English (e.g. "after 4:00 pm", "4 baje ke baad", "kal 3 baje", "15th October").
   - Calculate ISO datetime "YYYY-MM-DDTHH:MM" based on Current Server Time ({$nowStr}).
   - If NO follow-up date was discussed, return null.
4. "next_action":
   - Specific next step in ENGLISH ONLY (e.g. "Follow-up Call", "Send Revised Proposal", "Schedule Product Demo", "Meeting").
   - If call was not answered, return "Retry Call".
5. "sentiment": One of ["Hot / High Intent", "Interested", "Neutral", "Cold / Not Interested"].
6. "suggested_stage": One of ["Qualification", "Needs Analysis", "Proposal", "Negotiation", "Won", "Lost"] or keep "{$deal->stage}".
7. "existing_meeting_update": A 1-sentence note in ENGLISH stating what happened.

LANGUAGE RULE: All output values MUST be in ENGLISH ONLY. Do NOT return Devanagari/Hindi script in any JSON field. Translate any spoken Hindi/Hinglish to English.

Return ONLY a valid JSON object with keys: transcript, summary, next_action, next_followup_date, sentiment, suggested_stage, existing_meeting_update.
PROMPT;

            $parts = [];
            if (!empty($audioBase64)) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $audioMime ?: 'audio/webm',
                        'data' => $audioBase64
                    ]
                ];
            }
            $parts[] = ['text' => $prompt];

            $models = ['gemini-3.5-flash', 'gemini-3.8-flash', 'gemini-flash-latest'];
            foreach ($models as $modelName) {
                try {
                    $response = Http::timeout(20)
                        ->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}", [
                            'contents' => [
                                [
                                    'parts' => $parts
                                ]
                            ],
                            'generationConfig' => [
                                'response_mime_type' => 'application/json',
                                'temperature' => 0.1,
                            ]
                        ]);

                    if ($response->successful()) {
                        $rawText = $response->json('candidates.0.content.parts.0.text');
                        if ($rawText) {
                            $json = json_decode($rawText, true);
                            if (is_array($json)) {
                                return $json;
                            }
                        }
                    } else {
                        Log::warning("Gemini deal model {$modelName} returned status " . $response->status());
                    }
                } catch (\Throwable $mErr) {
                    Log::warning("Gemini deal model {$modelName} error: " . $mErr->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini Deal API call analysis failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Strict heuristic fallback for Deals.
     */
    private function generateHeuristicDealAnalysis(CrmDeal $deal, string $notes, ?LeadFollowup $pendingFollowup): array
    {
        $lower = strtolower(trim($notes));
        
        if (empty($notes) || $lower === 'not answering' || str_contains($lower, 'not answer') || str_contains($lower, 'rejected') || str_contains($lower, 'busy') || str_contains($lower, 'no-answer') || str_contains($lower, 'declined')) {
            return [
                'summary' => 'Not answering',
                'transcript' => '',
                'next_action' => 'Retry Call',
                'next_followup_date' => null,
                'sentiment' => 'Neutral',
                'suggested_stage' => $deal->stage,
                'existing_meeting_update' => 'Call was not answered / rejected by customer.',
            ];
        }

        if ($lower === 'disconnected' || str_contains($lower, 'disconnect') || str_contains($lower, 'failed')) {
            return [
                'summary' => 'Disconnected',
                'transcript' => '',
                'next_action' => 'Retry Call',
                'next_followup_date' => null,
                'sentiment' => 'Neutral',
                'suggested_stage' => $deal->stage,
                'existing_meeting_update' => 'Call could not connect or disconnected.',
            ];
        }

        $sentiment = 'Interested';
        if (str_contains($lower, 'urgent') || str_contains($lower, 'ready') || str_contains($lower, 'order') || str_contains($lower, 'agree') || str_contains($lower, 'close')) {
            $sentiment = 'Hot / High Intent';
        } elseif (str_contains($lower, 'not interested') || str_contains($lower, 'reject') || str_contains($lower, 'cancel')) {
            $sentiment = 'Cold / Not Interested';
        }

        $suggestedStage = $deal->stage;
        if (str_contains($lower, 'proposal') || str_contains($lower, 'quotation')) {
            $suggestedStage = 'Proposal';
        } elseif (str_contains($lower, 'negotiat') || str_contains($lower, 'discount') || str_contains($lower, 'final price')) {
            $suggestedStage = 'Negotiation';
        } elseif (str_contains($lower, 'won') || str_contains($lower, 'deal closed') || str_contains($lower, 'payment sent')) {
            $suggestedStage = 'Won';
        }

        $nextAction = 'Follow-up Call';
        $nextDate = null;

        if (str_contains($lower, 'proposal') || str_contains($lower, 'quote')) {
            $nextAction = 'Send Revised Proposal';
        } elseif (str_contains($lower, 'demo') || str_contains($lower, 'meeting')) {
            $nextAction = 'Product Demo Meeting';
        }

        if (str_contains($lower, 'today')) {
            $nextDate = now()->setHour(17)->setMinute(0);
        } elseif (str_contains($lower, 'tomorrow') || str_contains($lower, 'kal')) {
            $nextDate = now()->addDay()->setHour(15)->setMinute(0);
        } elseif (str_contains($lower, 'next week') || str_contains($lower, 'monday')) {
            $nextDate = now()->next(Carbon::MONDAY)->setHour(11)->setMinute(0);
        }

        return [
            'summary' => $notes,
            'transcript' => $notes,
            'next_action' => $nextAction,
            'next_followup_date' => $nextDate ? $nextDate->format('Y-m-d H:i') : null,
            'sentiment' => $sentiment,
            'suggested_stage' => $suggestedStage,
            'existing_meeting_update' => "Deal call conducted: {$notes}",
        ];
    }
}
