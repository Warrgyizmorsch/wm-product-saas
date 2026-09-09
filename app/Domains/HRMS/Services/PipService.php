<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\PerformanceImprovementPlan;
use App\Domains\HRMS\Models\PipCheckin;
use App\Domains\HRMS\Models\PipObjective;
use App\Domains\HRMS\Models\Employee;
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
            $end = Carbon::parse($data['end_date']);
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

            return $pip->load(['employee', 'manager', 'objectives', 'checkins']);
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
        if (in_array($data['rating_status'], ['off_track', 'at_risk']) && $pip->status === 'active') {
            $pip->update(['status' => 'under_review']);
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
                    $updateData['status']       = 'completed_success';
                    $updateData['completed_at'] = now();
                    break;

                case 'pip_extension':
                    $extensionDays = (int) ($data['extension_days'] ?? 30);
                    $updateData['status']       = 'extended';
                    $updateData['end_date']     = Carbon::parse($pip->end_date)->addDays($extensionDays)->format('Y-m-d');
                    $updateData['duration_days'] = $pip->duration_days + $extensionDays;
                    // Do NOT set completed_at — PIP remains active
                    break;

                case 'role_reassignment':
                    $updateData['status']       = 'role_reassigned';
                    $updateData['completed_at'] = now();
                    break;

                case 'termination':
                    $updateData['status']       = 'failed_terminated';
                    $updateData['completed_at'] = now();
                    break;
            }

            $pip->update($updateData);

            // Handle Employee Stage side-effects according to enterprise standards
            if ($pip->employee) {
                if ($outcome === 'successful_completion') {
                    $pip->employee->update(['employee_stage' => 'Active']);
                } elseif ($outcome === 'termination') {
                    $pip->employee->update(['employee_stage' => 'Terminated']);
                }
            }

            return $pip->fresh();
        });
    }
}
