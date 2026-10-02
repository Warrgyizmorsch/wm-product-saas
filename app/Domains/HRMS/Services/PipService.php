<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\PerformanceImprovementPlan;
use App\Domains\HRMS\Models\PipCheckin;
use App\Domains\HRMS\Models\PipObjective;
use App\Domains\HRMS\Models\Employee;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PipService
{
    /**
     * Generate unique PIP number.
     */
    public function generatePipNumber(int $tenantId): string
    {
        $year = date('Y');
        $lastPip = PerformanceImprovementPlan::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->latest('id')
            ->first();

        $sequence = $lastPip ? intval(substr($lastPip->pip_number, -4)) + 1 : 1;

        return 'PIP-' . $year . '-' . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new PIP record with goals (Alias for createPip).
     */
    public function createPlan(array $data): PerformanceImprovementPlan
    {
        return $this->createPip($data);
    }

    /**
     * Create a new PIP record with goals.
     */
    public function createPip(array $data): PerformanceImprovementPlan
    {
        return DB::transaction(function () use ($data) {
            $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
            $data['tenant_id'] = $tenantId;
            $data['pip_number'] = $this->generatePipNumber($tenantId);
            $data['status'] = $data['status'] ?? 'active';

            // Calculate duration in days
            $start = Carbon::parse($data['start_date']);
            $end = Carbon::parse($data['end_date'] ?? $start->copy()->addDays((int)($data['duration_days'] ?? 30)));
            $data['duration_days'] = $start->diffInDays($end);

            $pip = PerformanceImprovementPlan::create($data);

            // Create Objectives if passed
            if (!empty($data['objectives']) && is_array($data['objectives'])) {
                foreach ($data['objectives'] as $obj) {
                    if (!empty($obj['title'])) {
                        PipObjective::create([
                            'tenant_id' => $tenantId,
                            'pip_id' => $pip->id,
                            'title' => $obj['title'],
                            'description' => $obj['description'] ?? null,
                            'target_criteria' => $obj['target_criteria'] ?? null,
                            'support_provided' => $obj['support_provided'] ?? null,
                            'weightage' => $obj['weightage'] ?? 100.00,
                            'status' => 'pending',
                        ]);
                    }
                }
            }

            $pip->load(['employee.reportingManager', 'manager', 'objectives', 'checkins']);

            // Dispatch Notifications
            try {
                if ($pip->employee) {
                    NotificationService::sendToEmployee(
                        $pip->employee,
                        'Performance Improvement Plan (PIP) Initiated',
                        "A Performance Improvement Plan #{$pip->pip_number} has been initiated for you.",
                        route('hrms.pip.show', $pip->id),
                        'hrms',
                        'pip_initiated',
                        'feather-alert-triangle'
                    );
                }

                $manager = $pip->manager ?? $pip->employee?->reportingManager;
                if ($manager && $pip->employee && $manager->id !== $pip->employee->id) {
                    NotificationService::sendToEmployee(
                        $manager,
                        'Team Member PIP Initiated',
                        "A PIP #{$pip->pip_number} has been established for your team member {$pip->employee->full_name}.",
                        route('hrms.pip.show', $pip->id),
                        'hrms',
                        'pip_manager_notice',
                        'feather-alert-circle'
                    );
                }

                NotificationService::sendToHrAdmins(
                    'PIP Initiated',
                    "Performance Improvement Plan #{$pip->pip_number} created for {$pip->employee?->full_name}.",
                    route('hrms.pip.show', $pip->id),
                    'pip_hr_alert',
                    'feather-alert-triangle'
                );
            } catch (\Throwable $e) {
                // Suppress notification errors
            }

            return $pip;
        });
    }

    /**
     * Add 1-on-1 Milestone Check-in
     */
    public function createCheckin(PerformanceImprovementPlan $pip, array $data): PipCheckin
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $data['tenant_id'] = $tenantId;
        $data['pip_id'] = $pip->id;
        $data['reviewer_id'] = $data['reviewer_id'] ?? auth()->id();

        $checkin = PipCheckin::create($data);

        // Update overall PIP status if rated at risk or off track
        if (in_array($data['rating_status'] ?? '', ['off_track', 'at_risk']) && $pip->status === 'active') {
            $pip->update(['status' => 'under_review']);
        }

        // Dispatch Check-in Notification
        try {
            $pip->loadMissing(['employee', 'manager']);
            if ($pip->employee) {
                NotificationService::sendToEmployee(
                    $pip->employee,
                    'PIP Check-In Logged',
                    "A progress review check-in has been recorded for your PIP #{$pip->pip_number}.",
                    route('hrms.pip.show', $pip->id),
                    'hrms',
                    'pip_checkin',
                    'feather-file-text'
                );
            }

            if (in_array($data['rating_status'] ?? '', ['off_track', 'at_risk'])) {
                NotificationService::sendToHrAdmins(
                    'PIP Progress Warning',
                    "PIP #{$pip->pip_number} for {$pip->employee?->full_name} is flagged as " . strtoupper(str_replace('_', ' ', $data['rating_status'] ?? 'at risk')) . ".",
                    route('hrms.pip.show', $pip->id),
                    'pip_warning',
                    'feather-alert-circle'
                );
            }
        } catch (\Throwable $e) {
            // Suppress notification errors
        }

        return $checkin;
    }

    /**
     * Execute Final PIP Evaluation Outcome
     */
    public function evaluateFinalOutcome(PerformanceImprovementPlan $pip, array $data): PerformanceImprovementPlan
    {
        return DB::transaction(function () use ($pip, $data) {
            $outcome = $data['evaluation_outcome'] ?? $data['final_outcome'];
            $remarks = $data['final_remarks'] ?? $data['final_comments'] ?? null;

            $updateData = [
                'final_outcome'  => $outcome,
                'final_comments' => $remarks,
            ];

            switch ($outcome) {
                case 'successful_completion':
                case 'completed_success':
                    $updateData['status']       = 'completed_success';
                    $updateData['completed_at'] = now();
                    break;

                case 'pip_extension':
                case 'extended':
                    $extensionDays = (int) ($data['extension_days'] ?? 30);
                    $updateData['status']       = 'extended';
                    $updateData['end_date']     = Carbon::parse($pip->end_date)->addDays($extensionDays)->format('Y-m-d');
                    $updateData['duration_days'] = $pip->duration_days + $extensionDays;
                    break;

                case 'role_reassignment':
                case 'failed_role_change':
                case 'failed_demoted':
                    $updateData['status']       = 'role_reassigned';
                    $updateData['completed_at'] = now();
                    break;

                case 'termination':
                case 'failed_terminated':
                    $updateData['status']       = 'failed_terminated';
                    $updateData['completed_at'] = now();
                    break;
            }

            $pip->update($updateData);

            // Handle Employee Stage side-effects according to enterprise standards
            if ($pip->employee) {
                if (in_array($outcome, ['successful_completion', 'completed_success'])) {
                    $pip->employee->update(['employee_stage' => 'Active']);
                } elseif (in_array($outcome, ['termination', 'failed_terminated'])) {
                    $pip->employee->update(['employee_stage' => 'Terminated']);
                }
            }

            // Dispatch Outcome Notifications
            try {
                $outcomeLabel = ucwords(str_replace('_', ' ', $outcome));
                $pip->loadMissing(['employee', 'manager']);

                if ($pip->employee) {
                    NotificationService::sendToEmployee(
                        $pip->employee,
                        'PIP Evaluation Concluded',
                        "Your PIP #{$pip->pip_number} has concluded with outcome: {$outcomeLabel}.",
                        route('hrms.pip.show', $pip->id),
                        'hrms',
                        'pip_concluded',
                        'feather-check-circle'
                    );
                }

                NotificationService::sendToHrAdmins(
                    'PIP Outcome Concluded',
                    "PIP #{$pip->pip_number} for {$pip->employee?->full_name} concluded with outcome: {$outcomeLabel}.",
                    route('hrms.pip.show', $pip->id),
                    'pip_concluded_hr',
                    'feather-clipboard'
                );
            } catch (\Throwable $e) {
                // Suppress notification errors
            }

            return $pip->fresh();
        });
    }
}

