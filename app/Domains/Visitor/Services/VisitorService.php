<?php

namespace App\Domains\Visitor\Services;

use App\Domains\Visitor\Models\Visitor;
use App\Domains\Visitor\Models\VisitorPass;
use App\Services\Notification\NotificationService;
use App\Services\Pusher\PusherBroadcastService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class VisitorService
{
    /**
     * Resolve tenant context
     */
    public function resolveTenantContext(): array
    {
        $user = auth()->user();
        $tenantId = $user?->tenant_id ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId = $user?->branch_id ?? (branch_id() ?? null);

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * Generate unique visitor pass number
     */
    public function generatePassNumber(int $tenantId): string
    {
        $date = Carbon::now()->format('Ymd');
        $random = strtoupper(Str::random(4));
        return "VP-{$date}-{$random}";
    }

    /**
     * Get live headcount of visitors currently inside premises
     */
    public function getActiveVisitorsCount(int $tenantId, ?int $branchId = null): int
    {
        $query = VisitorPass::where('tenant_id', $tenantId)
            ->whereIn('status', ['Checked-In', 'Meeting in Progress', 'Overstayed'])
            ->whereNull('check_out_at');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->count();
    }

    /**
     * Get Dashboard Stats for Visitor Overview
     */
    public function getDashboardStats(int $tenantId, ?int $branchId = null): array
    {
        $baseQuery = VisitorPass::where('tenant_id', $tenantId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

        $totalVisitors = Visitor::where('tenant_id', $tenantId)->count();
        $totalPasses = (clone $baseQuery)->count();
        
        // Active inside premises
        $activeInside = (clone $baseQuery)
            ->whereIn('status', ['Checked-In', 'Meeting in Progress', 'Overstayed'])
            ->whereNull('check_out_at')
            ->count();

        $todayExpected = (clone $baseQuery)->where('status', 'Expected')->whereDate('expected_arrival_at', today())->count();
        $todayCheckedIn = (clone $baseQuery)->whereIn('status', ['Checked-In', 'Meeting in Progress'])->whereDate('check_in_at', today())->count();
        $totalCheckedOut = (clone $baseQuery)->where('status', 'Checked-Out')->count();
        $todayCheckedOut = (clone $baseQuery)->where('status', 'Checked-Out')->whereDate('check_out_at', today())->count();
        $waitingApproval = (clone $baseQuery)->where('status', 'Waiting Approval')->count();
        $deniedCount = (clone $baseQuery)->whereIn('status', ['Denied', 'Rejected'])->count();
        
        // Overstayed calculation: Checked-in where elapsed time > expected duration minutes
        $checkedInPasses = (clone $baseQuery)
            ->whereIn('status', ['Checked-In', 'Meeting in Progress', 'Overstayed'])
            ->whereNull('check_out_at')
            ->whereNotNull('check_in_at')
            ->get(['id', 'check_in_at', 'expected_duration_minutes', 'status']);

        $overstayedCount = 0;
        foreach ($checkedInPasses as $p) {
            $durationLimit = $p->expected_duration_minutes ?: 60;
            if (now()->diffInMinutes($p->check_in_at) > $durationLimit) {
                $overstayedCount++;
            }
        }

        $totalFees = (clone $baseQuery)->whereDate('created_at', today())->sum('fee_amount');
        $repeatVisitorsCount = Visitor::where('tenant_id', $tenantId)->has('passes', '>', 1)->count();

        return [
            'total_visitors'    => $totalVisitors,
            'total_passes'      => $totalPasses,
            'active_inside'     => $activeInside,
            'today_expected'    => $todayExpected,
            'today_checked_in'  => $todayCheckedIn,
            'total_checked_out' => $totalCheckedOut,
            'today_checked_out' => $todayCheckedOut,
            'waiting_approval'  => $waitingApproval,
            'denied_count'      => $deniedCount,
            'overstayed_count'  => $overstayedCount,
            'repeat_visitors'   => $repeatVisitorsCount,
            'today_fees'        => (float)$totalFees,
        ];
    }

    /**
     * Create or find visitor and generate visitor pass
     */
    public function createPass(array $data, int $tenantId, int $companyId, ?int $branchId = null): VisitorPass
    {
        // Find existing visitor by phone or create new
        $visitor = Visitor::firstOrNew([
            'tenant_id' => $tenantId,
            'phone'     => trim($data['phone']),
        ]);

        // Security Check: Block entry if visitor is blacklisted unless manager override is authorized
        if ($visitor->exists && $visitor->is_blacklisted && empty($data['allow_blacklisted_override'])) {
            $reason = $visitor->blacklist_reason ?: 'Blacklisted by Security / Administration';
            throw \Illuminate\Validation\ValidationException::withMessages([
                'phone' => "⛔ Entry Prohibited: Visitor {$visitor->full_name} is BLACKLISTED ({$reason}). Pass creation blocked."
            ]);
        }

        $visitor->company_id = $companyId;
        $visitor->branch_id = $branchId;
        $visitor->full_name = trim($data['full_name']);
        if (!empty($data['email'])) $visitor->email = trim($data['email']);
        if (!empty($data['company_name'])) $visitor->company_name = trim($data['company_name']);
        if (!empty($data['designation'])) $visitor->designation = trim($data['designation']);
        if (!empty($data['visitor_type'])) $visitor->visitor_type = $data['visitor_type'];
        if (!empty($data['id_proof_type'])) $visitor->id_proof_type = $data['id_proof_type'];
        if (!empty($data['id_proof_number'])) $visitor->id_proof_number = trim($data['id_proof_number']);
        if (!empty($data['photo_url'])) $visitor->photo_url = $data['photo_url'];
        $visitor->save();

        $passNotes = $data['notes'] ?? null;
        if ($visitor->is_blacklisted && !empty($data['allow_blacklisted_override'])) {
            $overrideReason = !empty($data['blacklist_override_reason']) ? trim($data['blacklist_override_reason']) : 'Manager Authorized Exception';
            $overrideHeader = "[SECURITY OVERRIDE: Blacklisted Visitor Admitted - Reason: {$overrideReason} (Auth by " . (auth()->user()?->name ?? 'Admin') . ")]";
            $passNotes = $overrideHeader . ($passNotes ? "\n" . $passNotes : "");
        }

        $passNumber = $this->generatePassNumber($tenantId);
        $qrToken = 'QR-' . Str::uuid()->toString();
        $status = !empty($data['check_in_now']) ? 'Checked-In' : ($data['status'] ?? 'Expected');

        $pass = VisitorPass::create([
            'tenant_id'                 => $tenantId,
            'company_id'                => $companyId,
            'branch_id'                 => $branchId,
            'pass_number'               => $passNumber,
            'visitor_id'                => $visitor->id,
            'host_user_id'              => !empty($data['host_user_id']) ? (int)$data['host_user_id'] : null,
            'department_id'             => !empty($data['department_id']) ? (int)$data['department_id'] : null,
            'purpose'                   => $data['purpose'] ?? 'Meeting',
            'visitor_type'              => $data['visitor_type'] ?? ($visitor->visitor_type ?? 'Client'),
            'entry_type'                => $data['entry_type'] ?? 'Walk-in',
            'status'                    => $status,
            'expected_arrival_at'       => !empty($data['expected_arrival_at']) ? Carbon::parse($data['expected_arrival_at']) : now(),
            'arrived_at'                => (!empty($data['check_in_now']) || $status === 'Waiting Approval' || $status === 'Arrived') ? now() : null,
            'check_in_at'               => !empty($data['check_in_now']) ? now() : null,
            'expected_duration_minutes' => !empty($data['expected_duration_minutes']) ? (int)$data['expected_duration_minutes'] : 60,
            'accompanying_count'        => !empty($data['accompanying_count']) ? (int)$data['accompanying_count'] : 0,
            'accompanying_names'        => $data['accompanying_names'] ?? null,
            'vehicle_type'              => $data['vehicle_type'] ?? null,
            'vehicle_number'            => $data['vehicle_number'] ?? null,
            'parking_slot'              => $data['parking_slot'] ?? null,
            'id_verification_status'    => !empty($data['id_verification_status']) ? $data['id_verification_status'] : (!empty($data['id_proof_number']) ? 'Verified' : 'Pending'),
            'id_verified_by'            => (!empty($data['id_proof_number']) && !empty($data['check_in_now'])) ? auth()->id() : null,
            'badge_number'              => $data['badge_number'] ?? null,
            'badge_printed'             => !empty($data['badge_printed']),
            'nda_safety_acknowledged'   => !empty($data['nda_safety_acknowledged']),
            'restricted_area_access'    => !empty($data['restricted_area_access']),
            'gate_pass_reference'       => $data['gate_pass_reference'] ?? null,
            'source_module'             => $data['source_module'] ?? 'manual',
            'source_reference_id'       => !empty($data['source_reference_id']) ? (int)$data['source_reference_id'] : null,
            'source_reference_no'       => $data['source_reference_no'] ?? null,
            'qr_token'                  => $qrToken,
            'gate_number'               => $data['gate_number'] ?? 'Gate 1',
            'fee_amount'                => (isset($data['fee_amount']) && is_numeric($data['fee_amount'])) ? (float)$data['fee_amount'] : 0.00,
            'notes'                     => $passNotes,
        ]);

        // Save Belongings/Laptops if provided
        if (!empty($data['belongings']) && is_array($data['belongings'])) {
            foreach ($data['belongings'] as $item) {
                if (!empty($item['item_type'])) {
                    $pass->belongings()->create([
                        'tenant_id'        => $tenantId,
                        'item_type'        => $item['item_type'],
                        'brand_model'      => $item['brand_model'] ?? null,
                        'serial_number'    => $item['serial_number'] ?? null,
                        'quantity'         => (int)($item['quantity'] ?? 1),
                        'is_returnable'    => isset($item['is_returnable']) ? (bool)$item['is_returnable'] : true,
                        'gate_pass_number' => $item['gate_pass_number'] ?? null,
                        'remarks'          => $item['remarks'] ?? null,
                    ]);
                }
            }
        }

        // Always trigger notification to Host whenever host is assigned
        if ($pass->host_user_id) {
            if ($pass->status === 'Waiting Approval') {
                $this->notifyHost($pass, 'approval_request');
            } elseif ($pass->status === 'Checked-In') {
                $this->notifyHost($pass, 'checked_in');
            } elseif ($pass->status === 'Expected') {
                $this->notifyHost($pass, 'pre_registered');
            } else {
                $this->notifyHost($pass, 'arrived');
            }
        }

        return $pass->load(['visitor', 'host', 'belongings']);
    }

    /**
     * Send host notification via In-App Notification and Pusher Broadcast
     */
    public function notifyHost(VisitorPass $pass, string $eventType = 'host_ping'): void
    {
        if (!$pass->host_user_id) return;

        $visitorName = $pass->visitor?->full_name ?? 'A visitor';
        $companyName = $pass->visitor?->company_name ? " ({$pass->visitor->company_name})" : '';
        $gate = $pass->gate_number ?? 'Main Gate';
        $tenantId = $pass->tenant_id;

        if ($eventType === 'approval_request') {
            $title = "Visitor Entry Approval Required";
            $message = "{$visitorName}{$companyName} has arrived at {$gate} for '{$pass->purpose}' and is waiting for your entry approval.";
            $actionUrl = route('visitor.approvals.index');
        } elseif ($eventType === 'checked_in') {
            $title = "Visitor Checked In";
            $message = "{$visitorName}{$companyName} has completed check-in at {$gate} for '{$pass->purpose}' and is on their way.";
            $actionUrl = route('visitor.passes.show', $pass->id);
        } elseif ($eventType === 'pre_registered') {
            $timeStr = $pass->expected_arrival_at ? Carbon::parse($pass->expected_arrival_at)->format('d M, h:i A') : 'soon';
            $title = "Visitor Pre-Registered";
            $message = "A visit for {$visitorName}{$companyName} has been scheduled ({$pass->purpose}) for {$timeStr}.";
            $actionUrl = route('visitor.passes.show', $pass->id);
        } elseif ($eventType === 'arrived') {
            $title = "Visitor Arrived at Gate";
            $message = "{$visitorName}{$companyName} has physically arrived at {$gate} to meet you.";
            $actionUrl = route('visitor.passes.show', $pass->id);
        } elseif ($eventType === 'meeting_started') {
            $title = "Meeting In Progress";
            $message = "Your meeting session with {$visitorName}{$companyName} is now marked in progress.";
            $actionUrl = route('visitor.passes.show', $pass->id);
        } elseif ($eventType === 'checked_out') {
            $title = "Visitor Checked Out";
            $message = "{$visitorName}{$companyName} has checked out and departed through {$gate}.";
            $actionUrl = route('visitor.passes.show', $pass->id);
        } elseif ($eventType === 'visit_extended') {
            $title = "Visit Duration Extended";
            $message = "Visit time for {$visitorName}{$companyName} has been extended to {$pass->expected_duration_minutes} minutes.";
            $actionUrl = route('visitor.passes.show', $pass->id);
        } elseif ($eventType === 'incident_reported') {
            $title = "Security Note / Incident Logged";
            $message = "A security or front desk incident was reported regarding visitor {$visitorName}{$companyName}.";
            $actionUrl = route('visitor.passes.show', $pass->id);
        } else {
            $title = "Visitor Waiting Ping";
            $message = "Front desk reminder: {$visitorName}{$companyName} is waiting for you at {$gate}.";
            $actionUrl = route('visitor.passes.show', $pass->id);
        }

        // Update notification timestamp
        $pass->update(['host_notified_at' => now()]);

        // 1. In-App Notification (Bell Icon)
        NotificationService::send(
            user: $pass->host_user_id,
            title: $title,
            message: $message,
            actionUrl: $actionUrl,
            module: 'visitor',
            type: $eventType,
            iconClass: 'feather-user-check',
            extraData: ['pass_id' => $pass->id, 'tenant_id' => $tenantId]
        );

        // 2. Real-time Pusher WebSockets
        PusherBroadcastService::broadcast(
            channels: ["user-{$pass->host_user_id}"],
            eventName: "visitor.{$eventType}",
            data: [
                'pass_id'      => $pass->id,
                'pass_number'  => $pass->pass_number,
                'visitor_name' => $visitorName,
                'company_name' => $pass->visitor?->company_name,
                'gate_number'  => $gate,
                'purpose'      => $pass->purpose,
                'title'        => $title,
                'message'      => $message,
                'url'          => $actionUrl,
            ]
        );
    }
}

