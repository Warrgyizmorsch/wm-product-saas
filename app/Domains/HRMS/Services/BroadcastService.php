<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Broadcast;
use App\Domains\HRMS\Models\BroadcastComment;
use App\Domains\HRMS\Models\BroadcastReceipt;
use App\Domains\HRMS\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BroadcastService
{
    /**
     * Create a new Broadcast and generate target receipt placeholders.
     */
    public function createBroadcast(array $data): Broadcast
    {
        return DB::transaction(function () use ($data) {
            $tenantId = $data['tenant_id'] ?? (tenant_id() ?? 1);

            // Generate Broadcast Number (e.g. BC-2026-0001)
            $year = date('Y');
            $latestId = Broadcast::where('tenant_id', $tenantId)->max('id') + 1;
            $data['broadcast_number'] = 'BC-' . $year . '-' . str_pad($latestId, 4, '0', STR_PAD_LEFT);
            $data['created_by_user_id'] = auth()->id() ?? 1;

            if (isset($data['scheduled_at']) && !empty($data['scheduled_at'])) {
                $schedTime = Carbon::parse($data['scheduled_at']);
                if ($schedTime <= Carbon::now()) {
                    $data['status'] = 'published';
                    $data['published_at'] = Carbon::now();
                } else {
                    $data['status'] = 'scheduled';
                }
            } elseif (empty($data['status'])) {
                $data['status'] = 'published';
                $data['published_at'] = Carbon::now();
            }

            $broadcast = Broadcast::create($data);

            // Generate receipts for target audience
            $this->generateTargetReceipts($broadcast);

            return $broadcast;
        });
    }

    /**
     * Process and publish all due scheduled broadcasts.
     */
    public function processScheduledBroadcasts(?int $tenantId = null): void
    {
        $query = Broadcast::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', Carbon::now());

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $dueBroadcasts = $query->get();

        foreach ($dueBroadcasts as $broadcast) {
            $this->publishBroadcast($broadcast);
        }
    }

    /**
     * Generate target receipt placeholders for audience.
     */
    public function generateTargetReceipts(Broadcast $broadcast): void
    {
        $tenantId = $broadcast->tenant_id;
        $empQuery = Employee::where('tenant_id', $tenantId)->where(function ($q) {
            $q->where('status', true)->orWhere('status', 1)->orWhereNull('status');
        });

        $targetType = $broadcast->target_type;
        $targetIds = $broadcast->target_ids ?? [];

        if ($targetType === 'department' && !empty($targetIds)) {
            $empQuery->whereIn('department_id', $targetIds);
        } elseif ($targetType === 'branch' && !empty($targetIds)) {
            $empQuery->whereIn('branch_id', $targetIds);
        } elseif ($targetType === 'designation' && !empty($targetIds)) {
            $empQuery->whereIn('designation_id', $targetIds);
        } elseif ($targetType === 'specific_employees' && !empty($targetIds)) {
            $empQuery->whereIn('id', $targetIds);
        }

        $employeeIds = $empQuery->pluck('id');
        $deliveredAt = ($broadcast->status === 'published') ? Carbon::now() : null;

        foreach ($employeeIds as $empId) {
            BroadcastReceipt::firstOrCreate([
                'tenant_id'    => $tenantId,
                'broadcast_id' => $broadcast->id,
                'employee_id'  => $empId,
            ], [
                'delivered_at' => $deliveredAt,
            ]);
        }
    }

    /**
     * Publish a scheduled or draft broadcast.
     */
    public function publishBroadcast(Broadcast $broadcast): Broadcast
    {
        $broadcast->update([
            'status'       => 'published',
            'published_at' => Carbon::now(),
        ]);

        // Update delivered_at on receipts
        BroadcastReceipt::where('broadcast_id', $broadcast->id)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => Carbon::now()]);

        return $broadcast->fresh();
    }

    /**
     * Mark broadcast read by employee.
     */
    public function markRead(Broadcast $broadcast, int $employeeId): void
    {
        $receipt = BroadcastReceipt::where('broadcast_id', $broadcast->id)
            ->where('employee_id', $employeeId)
            ->first();

        if ($receipt && !$receipt->read_at) {
            $receipt->update(['read_at' => Carbon::now()]);
        }
    }

    /**
     * Record mandatory read acknowledgement.
     */
    public function acknowledgeBroadcast(Broadcast $broadcast, int $employeeId, ?string $ipAddress = null, ?string $deviceType = null): BroadcastReceipt
    {
        $receipt = BroadcastReceipt::firstOrCreate([
            'tenant_id'    => $broadcast->tenant_id,
            'broadcast_id' => $broadcast->id,
            'employee_id'  => $employeeId,
        ]);

        $now = Carbon::now();
        $receipt->update([
            'read_at'         => $receipt->read_at ?? $now,
            'acknowledged_at' => $now,
            'ip_address'      => $ipAddress ?? request()->ip(),
            'device_type'     => substr($deviceType ?? request()->header('User-Agent') ?? '', 0, 100),
        ]);

        return $receipt;
    }

    /**
     * Add employee comment or HR reply.
     */
    public function addComment(Broadcast $broadcast, int $employeeId, string $commentText, ?int $parentId = null): BroadcastComment
    {
        return BroadcastComment::create([
            'tenant_id'    => $broadcast->tenant_id,
            'broadcast_id' => $broadcast->id,
            'employee_id'  => $employeeId,
            'parent_id'    => $parentId,
            'comment_text' => $commentText,
            'status'       => 'published',
        ]);
    }

    /**
     * Toggle pinned status of a comment.
     */
    public function togglePinComment(BroadcastComment $comment): BroadcastComment
    {
        $comment->update(['is_pinned' => !$comment->is_pinned]);
        return $comment;
    }

    /**
     * Get delivery, read, and acknowledgement metrics for a broadcast.
     */
    public function getAnalytics(Broadcast $broadcast): array
    {
        $totalTargeted = $broadcast->receipts()->count();
        $delivered = $broadcast->receipts()->whereNotNull('delivered_at')->count();
        $read = $broadcast->receipts()->whereNotNull('read_at')->count();
        $acknowledged = $broadcast->receipts()->whereNotNull('acknowledged_at')->count();

        $readPct = $totalTargeted > 0 ? (int) round(($read / $totalTargeted) * 100) : 0;
        $ackPct = $totalTargeted > 0 ? (int) round(($acknowledged / $totalTargeted) * 100) : 0;

        $nonResponders = BroadcastReceipt::where('broadcast_id', $broadcast->id)
            ->whereNull('acknowledged_at')
            ->with(['employee.department', 'employee.designation'])
            ->get();

        return [
            'total_targeted'    => $totalTargeted,
            'delivered'         => $delivered,
            'read'              => $read,
            'acknowledged'      => $acknowledged,
            'read_percentage'   => $readPct,
            'ack_percentage'    => $ackPct,
            'non_responders'    => $nonResponders,
        ];
    }
}
