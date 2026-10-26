<?php

namespace App\Domains\Platform\Controllers\Api;

use App\Domains\Platform\Models\DemoRequest;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PublicDemoRequestController extends Controller
{
    /**
     * Store a newly submitted demo request from the public website (mossierp.com).
     */
    public function store(Request $request): JsonResponse
    {
        // Normalize input keys so both website variations work seamlessly
        $fullName = $request->input('full_name') ?? $request->input('name') ?? $request->input('fullname');
        $phone = $request->input('phone') ?? $request->input('mobile_number') ?? $request->input('mobile');
        $email = $request->input('email') ?? $request->input('work_email');
        $companyName = $request->input('company_name') ?? $request->input('company');
        $teamSize = $request->input('team_size') ?? $request->input('company_size') ?? $request->input('employees');
        $notes = $request->input('notes') ?? $request->input('message') ?? $request->input('comment');

        // Preferred Date and Time (Tarika A: 24-hour time or AM/PM string, plus optional ISO slot)
        $preferredDate = $request->input('preferred_date') ?? $request->input('preferred_demo_date') ?? $request->input('demo_date');
        $preferredTime = $request->input('preferred_time') ?? $request->input('preferred_demo_time') ?? $request->input('demo_time');
        $preferredSlot = $request->input('preferred_slot') ?? $request->input('demo_slot');

        // Handle modules: could be array or comma-separated string
        $modules = $request->input('modules');
        if (is_string($modules)) {
            $decoded = json_decode($modules, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $modules = $decoded;
            } else {
                $modules = array_filter(array_map('trim', explode(',', $modules)));
            }
        }

        $dataToValidate = [
            'full_name' => $fullName,
            'phone' => $phone,
            'email' => $email,
            'company_name' => $companyName,
            'team_size' => $teamSize,
            'modules' => $modules,
            'preferred_date' => $preferredDate,
            'preferred_time' => $preferredTime,
            'preferred_slot' => $preferredSlot,
            'notes' => $notes,
        ];

        $validator = Validator::make($dataToValidate, [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'team_size' => ['nullable', 'string', 'max:100'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', 'max:100'],
            'preferred_date' => ['nullable', 'date'],
            'preferred_time' => ['nullable', 'string', 'max:50'],
            'preferred_slot' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide valid information for the demo booking.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Parse date and time (Tarika A: 24-hour time or 12-hour AM/PM)
        $parsedDate = null;
        $parsedTime = null;
        $parsedDateTime = null;

        if (! empty($validated['preferred_slot'])) {
            try {
                $carbonSlot = Carbon::parse($validated['preferred_slot']);
                $parsedDate = $carbonSlot->format('Y-m-d');
                $parsedTime = $carbonSlot->format('H:i');
                $parsedDateTime = $carbonSlot;
            } catch (\Exception $e) {
                // fall through
            }
        }

        if (! $parsedDateTime && ! empty($validated['preferred_date'])) {
            try {
                $timeString = ! empty($validated['preferred_time']) ? trim($validated['preferred_time']) : '10:00';
                $carbonSlot = Carbon::parse($validated['preferred_date'] . ' ' . $timeString);
                $parsedDate = $carbonSlot->format('Y-m-d');
                $parsedTime = $carbonSlot->format('H:i');
                $parsedDateTime = $carbonSlot;
            } catch (\Exception $e) {
                $parsedDate = $validated['preferred_date'];
                $parsedTime = $validated['preferred_time'] ?? null;
            }
        }

        $demoRequest = DemoRequest::create([
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'company_name' => $validated['company_name'] ?? null,
            'team_size' => $validated['team_size'] ?? null,
            'modules' => $validated['modules'] ?? [],
            'preferred_demo_date' => $parsedDate,
            'preferred_demo_time' => $parsedTime,
            'preferred_demo_at' => $parsedDateTime,
            'notes' => $validated['notes'] ?? null,
            'status' => DemoRequest::STATUS_NEW,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you! Your demo request has been received. Our team will contact you shortly.',
            'data' => [
                'id' => $demoRequest->id,
                'full_name' => $demoRequest->full_name,
                'email' => $demoRequest->email,
                'phone' => $demoRequest->phone,
                'preferred_demo_date' => $demoRequest->preferred_demo_date?->format('Y-m-d'),
                'preferred_demo_time' => $demoRequest->preferred_demo_time,
                'preferred_demo_formatted' => $demoRequest->preferred_demo_at ? $demoRequest->preferred_demo_at->format('d M Y, h:i A') : null,
                'created_at' => $demoRequest->created_at->toIso8601String(),
            ],
        ], 201);
    }
}
