<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOperatorAssignment;
use App\Domains\Production\Models\ProductionOperatorAssignmentLog;
use App\Domains\Production\Models\ProductionOperatorSkill;
use Illuminate\Support\Facades\DB;

class OperatorAssignmentService
{
    /**
     * Assign operator to an operation.
     */
    public function assign(int $tenantId, int $operationId, int $operatorId, int $assignedBy, ?string $remarks = null): ProductionOperatorAssignment
    {
        return DB::transaction(function () use ($tenantId, $operationId, $operatorId, $assignedBy, $remarks) {
            $op = ProductionOrderOperation::findOrFail($operationId);

            $this->validateSchedulingRequirements($op);
            $this->validateOperatorQualification($operatorId, $op, $tenantId);

            // Cancel any existing pending/active assignments for this operation
            ProductionOperatorAssignment::where('tenant_id', $tenantId)
                ->where('production_order_operation_id', $operationId)
                ->whereIn('status', [
                    ProductionOperatorAssignment::STATUS_ASSIGNED,
                    ProductionOperatorAssignment::STATUS_ACCEPTED
                ])
                ->update(['status' => ProductionOperatorAssignment::STATUS_REJECTED]);

            $assignment = ProductionOperatorAssignment::create([
                'tenant_id'                      => $tenantId,
                'production_order_operation_id'  => $operationId,
                'user_id'                        => $operatorId,
                'assigned_by'                    => $assignedBy,
                'assigned_at'                    => now(),
                'status'                         => ProductionOperatorAssignment::STATUS_ASSIGNED,
                'remarks'                        => $remarks,
            ]);

            ProductionOperatorAssignmentLog::create([
                'tenant_id'              => $tenantId,
                'operator_assignment_id' => $assignment->id,
                'previous_operator_id'   => null,
                'new_operator_id'        => $operatorId,
                'action'                 => 'assigned',
                'remarks'                => $remarks,
                'changed_by'             => $assignedBy,
            ]);

            return $assignment;
        });
    }

    /**
     * Reassign operator.
     */
    public function reassign(int $tenantId, int $assignmentId, int $newOperatorId, int $changerId, ?string $remarks = null): ProductionOperatorAssignment
    {
        return DB::transaction(function () use ($tenantId, $assignmentId, $newOperatorId, $changerId, $remarks) {
            $assignment = ProductionOperatorAssignment::findOrFail($assignmentId);
            $op = ProductionOrderOperation::findOrFail($assignment->production_order_operation_id);

            $this->validateSchedulingRequirements($op);
            $this->validateOperatorQualification($newOperatorId, $op, $tenantId);

            $oldOperatorId = $assignment->user_id;

            $assignment->update([
                'user_id'     => $newOperatorId,
                'assigned_by' => $changerId,
                'assigned_at' => now(),
                'status'      => ProductionOperatorAssignment::STATUS_ASSIGNED,
                'remarks'     => $remarks,
            ]);

            ProductionOperatorAssignmentLog::create([
                'tenant_id'              => $tenantId,
                'operator_assignment_id' => $assignment->id,
                'previous_operator_id'   => $oldOperatorId,
                'new_operator_id'        => $newOperatorId,
                'action'                 => 'reassigned',
                'remarks'                => $remarks,
                'changed_by'             => $changerId,
            ]);

            return $assignment;
        });
    }

    /**
     * Accept assignment.
     */
    public function accept(int $tenantId, int $assignmentId, int $operatorId, ?string $remarks = null): ProductionOperatorAssignment
    {
        return DB::transaction(function () use ($tenantId, $assignmentId, $operatorId, $remarks) {
            $assignment = ProductionOperatorAssignment::findOrFail($assignmentId);

            if ($assignment->user_id !== $operatorId) {
                throw new \LogicException("You are not authorized to accept this assignment.");
            }

            $assignment->update([
                'status'      => ProductionOperatorAssignment::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            ProductionOperatorAssignmentLog::create([
                'tenant_id'              => $tenantId,
                'operator_assignment_id' => $assignment->id,
                'previous_operator_id'   => null,
                'new_operator_id'        => $operatorId,
                'action'                 => 'accepted',
                'remarks'                => $remarks,
                'changed_by'             => $operatorId,
            ]);

            return $assignment;
        });
    }

    /**
     * Reject assignment.
     */
    public function reject(int $tenantId, int $assignmentId, int $operatorId, ?string $remarks = null): ProductionOperatorAssignment
    {
        return DB::transaction(function () use ($tenantId, $assignmentId, $operatorId, $remarks) {
            $assignment = ProductionOperatorAssignment::findOrFail($assignmentId);

            if ($assignment->user_id !== $operatorId) {
                throw new \LogicException("You are not authorized to reject this assignment.");
            }

            $assignment->update([
                'status' => ProductionOperatorAssignment::STATUS_REJECTED,
            ]);

            ProductionOperatorAssignmentLog::create([
                'tenant_id'              => $tenantId,
                'operator_assignment_id' => $assignment->id,
                'previous_operator_id'   => null,
                'new_operator_id'        => $operatorId,
                'action'                 => 'rejected',
                'remarks'                => $remarks,
                'changed_by'             => $operatorId,
            ]);

            return $assignment;
        });
    }

    /**
     * Complete assignment.
     */
    public function complete(int $tenantId, int $assignmentId, int $operatorId, ?string $remarks = null): ProductionOperatorAssignment
    {
        return DB::transaction(function () use ($tenantId, $assignmentId, $operatorId, $remarks) {
            $assignment = ProductionOperatorAssignment::findOrFail($assignmentId);

            if ($assignment->user_id !== $operatorId) {
                throw new \LogicException("You are not authorized to complete this assignment.");
            }

            $assignment->update([
                'status'       => ProductionOperatorAssignment::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            ProductionOperatorAssignmentLog::create([
                'tenant_id'              => $tenantId,
                'operator_assignment_id' => $assignment->id,
                'previous_operator_id'   => null,
                'new_operator_id'        => $operatorId,
                'action'                 => 'completed',
                'remarks'                => $remarks,
                'changed_by'             => $operatorId,
            ]);

            return $assignment;
        });
    }

    /**
     * Validate if operator is qualified for the operation.
     */
    public function validateOperatorQualification(int $operatorId, ProductionOrderOperation $op, int $tenantId): void
    {
        // Super Admin / Admin exemption
        $user = \App\Models\User::find($operatorId);
        if ($user && (in_array(strtolower($user->role ?? ''), ['super_admin', 'superadmin', 'admin', 'administrator']) || $user->email === 'admin@example.com')) {
            return;
        }

        $skills = ProductionOperatorSkill::where('tenant_id', $tenantId)
            ->where('user_id', $operatorId)
            ->where('active', true)
            ->get();

        if ($skills->isEmpty()) {
            throw new \LogicException("Operator is not qualified: No active skills configured.");
        }

        $qualified = false;
        $unrestrictedKeywords = ['UNRESTRICTED', 'ALL', 'GENERAL', 'SKL-ALL', 'SKILL-ALL', 'MASTER', 'ADMIN', 'FULL', 'GLOBAL', 'ANY', 'SUPER', ''];

        foreach ($skills as $skill) {
            $codeUpper = strtoupper(trim($skill->skill_code ?? ''));

            // Check if skill is unrestricted / general / master permission
            if (
                in_array($codeUpper, $unrestrictedKeywords) ||
                str_contains($codeUpper, 'UNRESTRICTED') ||
                str_contains($codeUpper, 'GENERAL') ||
                str_contains($codeUpper, 'MASTER') ||
                str_contains($codeUpper, 'ADMIN') ||
                str_contains($codeUpper, 'FULL') ||
                str_contains($codeUpper, 'GLOBAL') ||
                str_contains($codeUpper, 'ALL')
            ) {
                $qualified = true;
                break;
            }

            // Check machine match
            if ($skill->machine_id !== null && $op->machine_id !== null && $skill->machine_id === $op->machine_id) {
                $qualified = true;
                break;
            }

            // Check work center match
            if ($skill->work_center_id !== null && $skill->work_center_id === $op->work_center_id) {
                $qualified = true;
                break;
            }

            // Check generic skill match (e.g. check if skill code matches operation name/type)
            if (!empty($skill->skill_code)) {
                $code = strtolower($skill->skill_code);
                $opName = strtolower($op->name ?? '');
                $opType = strtolower($op->operation_type ?? '');
                if (str_contains($opName, $code) || str_contains($code, $opName) || ($opType && str_contains($opType, $code))) {
                    $qualified = true;
                    break;
                }
            }
        }

        if (!$qualified) {
            throw new \LogicException("Operator lacks required skills/qualification for this operation.");
        }
    }

    /**
     * Validate that a schedule exists for the production order.
     */
    private function validateSchedulingRequirements(ProductionOrderOperation $op): void
    {
        $order = $op->order;
        if (!$order || $order->schedules()->count() === 0) {
            throw new \LogicException("Cannot assign operator: A schedule must be generated for the production order first.");
        }
    }
}
