<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\DTO\ProductionPlanDTO;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrderRequest;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\Routing;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ProductionPlanService
{
    public function __construct(
        private readonly ProductionPlanNumberService $numberService
    ) {}

    /**
     * Create a new Production Plan.
     */
    public function create(ProductionPlanDTO $dto, int $tenantId, ?int $userId = null): ProductionPlan
    {
        $request = null;
        if ($dto->production_order_request_id) {
            $request = ProductionOrderRequest::where('tenant_id', $tenantId)
                ->where('status', 'draft')
                ->whereNull('production_plan_id')
                ->whereNull('production_order_id')
                ->with('materialRequirementItem.materialRequirement')
                ->lockForUpdate()
                ->findOrFail($dto->production_order_request_id);

            $dto = new ProductionPlanDTO(
                name: $dto->name,
                product_id: (int) $request->product_id,
                quantity: (float) $request->quantity_requested,
                start_date: $dto->start_date,
                end_date: $dto->end_date,
                production_order_request_id: $dto->production_order_request_id,
                sales_order_id: $request->materialRequirementItem?->materialRequirement?->sales_order_id,
                sales_order_item_id: $request->materialRequirementItem?->sales_order_item_id,
                bom_id: $dto->bom_id,
                routing_id: $dto->routing_id,
                description: $dto->description,
                plan_number: $dto->plan_number,
                status: $dto->status,
            );
        }

        $planNumber = $dto->plan_number ?: $this->numberService->generateNextNumber($tenantId);

        // Fetch default approved BOM and active Routing if not provided
        $bomId = $dto->bom_id;
        if (! $bomId) {
            $defaultBom = ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $dto->product_id)
                ->where('status', 'approved')
                ->first();
            $bomId = $defaultBom ? $defaultBom->id : null;
        }

        $routingId = $dto->routing_id;
        if (! $routingId) {
            $defaultRouting = Routing::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $dto->product_id)
                ->where('status', 'active')
                ->first();
            $routingId = $defaultRouting ? $defaultRouting->id : null;
        }

        // Validate Routing is active for manufacturing BOM/Plan contexts
        if ($routingId && $dto->status !== 'draft') {
            $routing = Routing::withoutGlobalScopes()->find($routingId);
            if ($routing && $routing->status !== 'active') {
                throw new InvalidArgumentException('Only active routings can be assigned to production plans.');
            }
        }

        $plan = ProductionPlan::create([
            'tenant_id' => $tenantId,
            'plan_number' => $planNumber,
            'name' => $dto->name,
            'product_id' => $dto->product_id,
            'bom_id' => $bomId,
            'routing_id' => $routingId,
            'sales_order_id' => $dto->sales_order_id,
            'sales_order_item_id' => $dto->sales_order_item_id,
            'quantity' => $dto->quantity,
            'start_date' => $dto->start_date,
            'end_date' => $dto->end_date,
            'status' => ProductionPlan::STATUS_DRAFT,
            'description' => $dto->description,
            'created_by' => $userId,
        ]);

        if ($request) {
            $request->update([
                'production_plan_id' => $plan->id,
                'status' => 'production-plan-created',
            ]);
        }

        return $plan;
    }

    /**
     * Update an existing Production Plan.
     */
    public function update(int $id, ProductionPlanDTO $dto): ProductionPlan
    {
        $plan = ProductionPlan::findOrFail($id);

        if ($plan->isFrozen()) {
            throw new InvalidArgumentException("Frozen Production Plans in status '{$plan->status}' cannot be updated.");
        }

        $updateData = $dto->toArray();
        // Remove null fields if they should not override defaults
        unset($updateData['plan_number']);
        unset($updateData['status']);

        $plan->update(array_filter($updateData, fn ($val) => ! is_null($val)));

        return $plan->fresh();
    }

    /**
     * Delete a production plan.
     */
    public function delete(int $id): bool
    {
        $plan = ProductionPlan::findOrFail($id);

        if ($plan->isFrozen()) {
            throw new InvalidArgumentException('Approved or released Production Plans cannot be deleted.');
        }

        return $plan->delete();
    }

    /**
     * Submit plan for approval.
     */
    public function submitApproval(int $id): ProductionPlan
    {
        $plan = ProductionPlan::findOrFail($id);

        if (! $plan->isDraft()) {
            throw new InvalidArgumentException('Only Draft plans can be submitted for approval.');
        }

        if (! $plan->bom_id || ! $plan->routing_id) {
            throw new InvalidArgumentException('BOM and Routing references must be set before submitting for approval.');
        }

        $plan->status = ProductionPlan::STATUS_PENDING_APPROVAL;
        $plan->save();

        return $plan;
    }

    /**
     * Approve plan.
     */
    public function approve(int $id, int $userId): ProductionPlan
    {
        $plan = ProductionPlan::findOrFail($id);

        if (! $plan->isPendingApproval()) {
            throw new InvalidArgumentException('Only Pending plans can be approved.');
        }

        $plan->status = ProductionPlan::STATUS_APPROVED;
        $plan->approved_by = $userId;
        $plan->approved_at = Carbon::now();
        $plan->save();

        return $plan;
    }

    /**
     * Reject plan.
     */
    public function reject(int $id): ProductionPlan
    {
        $plan = ProductionPlan::findOrFail($id);

        if (! $plan->isPendingApproval()) {
            throw new InvalidArgumentException('Only Pending plans can be rejected.');
        }

        $plan->status = ProductionPlan::STATUS_DRAFT;
        $plan->save();

        return $plan;
    }

    /**
     * Release plan to shop floor.
     */
    public function release(int $id): ProductionPlan
    {
        $plan = ProductionPlan::findOrFail($id);

        if (! $plan->isApproved() && ! $plan->isMrpGenerated()) {
            throw new InvalidArgumentException('Only Approved or MRP Generated plans can be released.');
        }

        $plan->status = ProductionPlan::STATUS_RELEASED;
        $plan->save();

        return $plan;
    }

    /**
     * Complete plan.
     */
    public function complete(int $id): ProductionPlan
    {
        $plan = ProductionPlan::findOrFail($id);

        if (! $plan->isReleased()) {
            throw new InvalidArgumentException('Only Released plans can be marked as completed.');
        }

        $plan->status = ProductionPlan::STATUS_COMPLETED;
        $plan->save();

        return $plan;
    }

    /**
     * Close plan.
     */
    public function close(int $id): ProductionPlan
    {
        $plan = ProductionPlan::findOrFail($id);

        if (! $plan->isCompleted()) {
            throw new InvalidArgumentException('Only Completed plans can be closed.');
        }

        $plan->status = ProductionPlan::STATUS_CLOSED;
        $plan->save();

        return $plan;
    }

    /**
     * Cancel plan.
     */
    public function cancel(int $id): ProductionPlan
    {
        $plan = ProductionPlan::findOrFail($id);

        if ($plan->isClosed() || $plan->isCompleted() || $plan->isCancelled()) {
            throw new InvalidArgumentException('Completed, closed, or already cancelled plans cannot be cancelled.');
        }

        $plan->status = ProductionPlan::STATUS_CANCELLED;
        $plan->save();

        return $plan;
    }
}
