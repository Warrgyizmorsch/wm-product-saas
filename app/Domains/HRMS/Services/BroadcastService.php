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
            $tenantId = $data['tenant_id'] ?? tenant_id();
            if (empty($tenantId)) {
                throw new \RuntimeException('Cannot create a Broadcast without a resolvable tenant_id.');
            }

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

            // Dispatch targeted emails if status is published and send_email is true
            if ($broadcast->status === 'published' && !empty($broadcast->send_email)) {
                $this->dispatchBroadcastEmails($broadcast);
            }

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

        if (!empty($broadcast->send_email)) {
            $this->dispatchBroadcastEmails($broadcast);
        }

        return $broadcast->fresh();
    }

    /**
     * Dispatch broadcast email notifications to targeted employees using EmailService with Mail fallback.
     */
    public function dispatchBroadcastEmails(Broadcast $broadcast): void
    {
        if (empty($broadcast->send_email)) {
            return;
        }

        $receipts = BroadcastReceipt::where('broadcast_id', $broadcast->id)
            ->with(['employee'])
            ->get();

        if ($receipts->isEmpty()) {
            return;
        }

        $emailService = app(\App\Services\EmailService::class);
        $actionUrl = route('hrms.broadcasts.show', $broadcast->id);
        $companyName = tenant() ? tenant()->name : config('app.name');

        $priorityBadgeColor = match($broadcast->priority) {
            'urgent'    => '#ef4444',
            'important' => '#f59e0b',
            default     => '#3b82f6'
        };
        $priorityLabel = strtoupper($broadcast->priority);
        $categoryLabel = ucwords(str_replace('_', ' ', $broadcast->category));

        $attachments = [];
        if (!empty($broadcast->attachment_path)) {
            $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($broadcast->attachment_path);
            if (file_exists($fullPath)) {
                $attachments[] = [
                    'path' => $fullPath,
                    'name' => basename($broadcast->attachment_path),
                    'mime' => mime_content_type($fullPath) ?: 'application/octet-stream',
                ];
            }
        }

        foreach ($receipts as $receipt) {
            $emp = $receipt->employee;
            if (!$emp) {
                continue;
            }

            $toEmail = $emp->office_email ?: $emp->personal_email;
            if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $subject = "[{$categoryLabel}] {$broadcast->title}";

            $bodyHtml = "
                <div style='font-family: Arial, Helvetica, sans-serif; max-width: 650px; margin: 0 auto; padding: 24px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <div style='border-bottom: 2px solid #2563eb; padding-bottom: 12px; margin-bottom: 20px;'>
                        <table width='100%' border='0' cellpadding='0' cellspacing='0'>
                            <tr>
                                <td align='left' style='font-weight: bold; color: #1e293b; font-size: 16px;'>" . e($companyName) . " Announcement</td>
                                <td align='right'>
                                    <span style='background-color: {$priorityBadgeColor}; color: #ffffff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase;'>{$priorityLabel}</span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <p style='font-size: 14px; color: #475569;'>Hello <strong>" . e($emp->full_name) . "</strong>,</p>

                    <h2 style='color: #0f172a; font-size: 20px; margin-top: 15px; margin-bottom: 10px;'>" . e($broadcast->title) . "</h2>

                    <div style='background-color: #f8fafc; border-left: 4px solid #3b82f6; padding: 15px; border-radius: 4px; margin: 20px 0; color: #334155; font-size: 14px; line-height: 1.6;'>
                        " . nl2br(e($broadcast->content)) . "
                    </div>

                    <div style='margin-top: 25px; text-align: center;'>
                        <a href='{$actionUrl}' style='display: inline-block; background-color: #2563eb; color: #ffffff; font-weight: bold; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-size: 14px;'>View Announcement in Portal &rarr;</a>
                    </div>

                    <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 30px 0 15px 0;'>
                    <p style='font-size: 11px; color: #94a3b8; text-align: center; margin: 0;'>
                        This is an official company broadcast sent via HRMS portal. Please do not reply directly to this automated email.
                    </p>
                </div>
            ";

            try {
                $emailService->sendEmail([
                    'to'        => $toEmail,
                    'subject'   => $subject,
                    'body_html' => $bodyHtml,
                ], $attachments);
            } catch (\Throwable $e) {
                // Fallback to default Laravel Mail if EmailService tenant SMTP fails or is unconfigured
                try {
                    \Illuminate\Support\Facades\Mail::send([], [], function ($msg) use ($toEmail, $subject, $bodyHtml, $attachments) {
                        $msg->to($toEmail)
                            ->subject($subject)
                            ->html($bodyHtml);

                        foreach ($attachments as $att) {
                            $msg->attach($att['path'], [
                                'as'   => $att['name'],
                                'mime' => $att['mime'],
                            ]);
                        }
                    });
                } catch (\Throwable $ex) {
                    \Illuminate\Support\Facades\Log::warning("Failed to send broadcast email to {$toEmail}: " . $ex->getMessage());
                }
            }
        }
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
            ->with([
                'employee:id,employee_id,full_name,office_email,department_id,designation_id',
                'employee.department:id,name',
                'employee.designation:id,name'
            ])
            ->get()
            ->map(function ($receipt) {
                return [
                    'id'            => $receipt->id,
                    'employee_id'   => $receipt->employee?->id,
                    'code'          => $receipt->employee?->employee_id,
                    'name'          => $receipt->employee?->full_name,
                    'email'         => $receipt->employee?->office_email,
                    'department'    => $receipt->employee?->department?->name,
                    'designation'   => $receipt->employee?->designation?->name,
                    'delivered_at'  => $receipt->delivered_at?->toIso8601String(),
                    'read_at'       => $receipt->read_at?->toIso8601String(),
                ];
            });

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
