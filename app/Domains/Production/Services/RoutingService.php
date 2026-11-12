<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\DTO\RoutingDTO;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingApproval;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Repositories\RoutingRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RoutingService
{
    public function __construct(
        private readonly RoutingRepositoryInterface $routingRepository,
        private readonly RoutingNumberService $numberService,
        private readonly RoutingLifecycleService $routingLifecycleService
    ) {}

    /**
     * Create a new routing draft.
     */
    public function create(RoutingDTO $dto, int $tenantId, ?int $creatorUserId = null): Routing
    {
        // Resolve routing number
        $routingNumber = trim($dto->routing_number ?? '');
        if (empty($routingNumber) || strtoupper($routingNumber) === 'AUTO') {
            $routingNumber = $this->numberService->generateNextNumber($tenantId);
        } else {
            if (!$this->numberService->validateNumber($routingNumber)) {
                throw new InvalidArgumentException('Routing number format is invalid.');
            }
            if ($this->numberService->isDuplicate($routingNumber, $tenantId)) {
                throw new InvalidArgumentException("A routing with number '{$routingNumber}' already exists.");
            }
        }

        // Check version conflicts for this routing name
        $this->checkRoutingConflicts($dto->name, $dto->version, $tenantId);
        $this->routingLifecycleService->validateEffectiveDates(
            $dto->effective_from,
            $dto->effective_to,
            0,
            null,
            $tenantId
        );

        return DB::transaction(function () use ($dto, $routingNumber, $tenantId, $creatorUserId) {
            $routing = $this->routingRepository->create(array_merge($dto->toArray(), [
                'tenant_id'      => $tenantId,
                'routing_number' => $routingNumber,
                'status'         => Routing::STATUS_DRAFT,
                'created_by'     => $creatorUserId,
                'revision'       => 0,
            ]));

            $this->syncOperations($routing, $dto->operations, $tenantId);

            RoutingApproval::create([
                'tenant_id'  => $tenantId,
                'routing_id' => $routing->id,
                'user_id'    => $creatorUserId,
                'action'     => RoutingApproval::ACTION_CREATED,
                'comments'   => 'Initial routing draft created.',
            ]);

            event(new \App\Domains\Production\Events\RoutingCreated($routing));

            return $routing;
        });
    }

    /**
     * Update a draft routing.
     */
    public function update(int $id, RoutingDTO $dto): Routing
    {
        $routing = $this->routingRepository->find($id);

        if (!$routing) {
            throw new InvalidArgumentException('Routing not found.');
        }

        if (!$routing->isEditable()) {
            throw new InvalidArgumentException(
                'Only draft routings can be edited. Approved routings are read-only.'
            );
        }

        $this->checkRoutingConflicts($dto->name ?? $routing->name, $dto->version, $routing->tenant_id, $id);
        $this->routingLifecycleService->validateEffectiveDates(
            $dto->effective_from,
            $dto->effective_to,
            0,
            $id,
            $routing->tenant_id
        );

        return DB::transaction(function () use ($routing, $dto) {
            $data = $dto->toArray();
            if (empty($data['routing_number'])) {
                unset($data['routing_number']);
            }

            $this->routingRepository->update($routing->id, $data);
            $routing->refresh();

            $this->syncOperations($routing, $dto->operations, $routing->tenant_id);

            return $routing->load('operations');
        });
    }

    /**
     * Submit routing for approval.
     */
    public function submitApproval(int $id, int $userId): Routing
    {
        $routing = $this->routingRepository->find($id);

        if (!$routing) {
            throw new InvalidArgumentException('Routing not found.');
        }

        if (!$routing->isDraft()) {
            throw new InvalidArgumentException('Only draft routings can be submitted for approval.');
        }

        if ($routing->operations()->count() === 0) {
            throw new InvalidArgumentException('Routing must have at least one operation before submitting for approval.');
        }

        // [INVERSION-DECOUPLE-TRACK-03] Decoupled: Routing no longer requires a linked BOM to submit for approval
        /*
        // Require linked active or draft BOM before routing can be submitted for approval
        $bom = $routing->bom;
        if (!$bom && $routing->product_id) {
            $bom = ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $routing->tenant_id)
                ->where('product_id', $routing->product_id)
                ->whereIn('status', ['approved', 'active', 'draft'])
                ->latest('id')
                ->first();
            if ($bom) {
                $routing->update(['bom_id' => $bom->id]);
            }
        }

        if (!$bom) {
            throw new InvalidArgumentException('Cannot submit routing for approval: A linked Bill of Materials (BOM) in active or draft status is required.');
        }

        if (!in_array($bom->status, ['approved', 'active', 'draft'])) {
            throw new InvalidArgumentException("Cannot submit routing for approval: The linked BOM must be active or draft (currently '{$bom->status}').");
        }
        */

        return DB::transaction(function () use ($routing, $userId) {
            $this->routingRepository->update($routing->id, ['status' => Routing::STATUS_PENDING_APPROVAL]);

            RoutingApproval::create([
                'tenant_id'  => $routing->tenant_id,
                'routing_id' => $routing->id,
                'user_id'    => $userId,
                'action'     => RoutingApproval::ACTION_SUBMITTED,
                'comments'   => 'Submitted for engineering and production manager approval.',
            ]);

            return $routing->fresh();
        });
    }

    /**
     * Approve a pending routing. Makes it active (or scheduled). Older versions are NOT retired automatically —
     * they are retired manually with the Retire action (RT-02).
     */
    public function approve(int $id, int $approverUserId): Routing
    {
        $routing = $this->routingRepository->find($id);

        if (!$routing) {
            throw new InvalidArgumentException('Routing not found.');
        }

        if (!$routing->isPendingApproval()) {
            throw new InvalidArgumentException('Only routings pending approval can be approved.');
        }

        // [INVERSION-DECOUPLE-TRACK-03] Decoupled: Routing no longer requires a linked BOM to approve
        /*
        // Require linked BOM to be approved and active before routing can be approved
        $bom = $routing->bom;
        if (!$bom && $routing->product_id) {
            $bom = ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $routing->tenant_id)
                ->where('product_id', $routing->product_id)
                ->whereIn('status', ['approved', 'active'])
                ->latest('id')
                ->first();
            if ($bom) {
                $routing->update(['bom_id' => $bom->id]);
            }
        }

        if (!$bom || !in_array($bom->status, ['approved', 'active', 'scheduled'])) {
            throw new InvalidArgumentException('Cannot approve routing: The linked Bill of Materials (BOM) must be approved and active first.');
        }
        */

        return DB::transaction(function () use ($routing, $approverUserId) {
            // Centralized routing approval lifecycle transition — no auto-retire of older versions (RT-02)
            $this->routingLifecycleService->transitionOnApproval($routing, $approverUserId);

            event(new \App\Domains\Production\Events\RoutingApproved($routing->fresh()));

            return $routing->fresh();
        });
    }

    /**
     * Reject a pending routing — returns to draft.
     */
    public function reject(int $id, int $userId, ?string $comments = null): Routing
    {
        $routing = $this->routingRepository->find($id);

        if (!$routing) {
            throw new InvalidArgumentException('Routing not found.');
        }

        if (!$routing->isPendingApproval()) {
            throw new InvalidArgumentException('Only routings pending approval can be rejected.');
        }

        return DB::transaction(function () use ($routing, $userId, $comments) {
            $this->routingRepository->update($routing->id, ['status' => Routing::STATUS_DRAFT]);

            RoutingApproval::create([
                'tenant_id'  => $routing->tenant_id,
                'routing_id' => $routing->id,
                'user_id'    => $userId,
                'action'     => RoutingApproval::ACTION_REJECTED,
                'comments'   => $comments ?: 'Routing rejected during review.',
            ]);

            // [TRACK-06] Revert pending-approval BOMs linked to this rejected routing back to draft
            $this->routingLifecycleService->handleBomsOnRoutingRejection($routing, $userId, $comments);

            return $routing->fresh();
        });
    }

    /**
     * Cancel an active or pending routing.
     */
    public function cancel(int $id, int $userId, ?string $comments = null): Routing
    {
        $routing = $this->routingRepository->find($id);

        if (!$routing) {
            throw new InvalidArgumentException('Routing not found.');
        }

        if ($routing->isCancelled() || $routing->isHistorical()) {
            throw new InvalidArgumentException('This routing is already cancelled or historical.');
        }

        return DB::transaction(function () use ($routing, $userId, $comments) {
            $this->routingLifecycleService->transitionOnCancellation($routing, $userId, $comments);

            return $routing->fresh();
        });
    }

    /**
     * RT-IMPL-02 (RT-02 rule 3): retire an active / scheduled routing → historical. effective_to = today, audit entry,
     * then the linked BOMs are handled (approved BOMs → inactive, drafts get a warning).
     */
    public function retire(int $id, int $userId, ?string $comments = null): Routing
    {
        $routing = $this->routingRepository->find($id);

        if (!$routing) {
            throw new InvalidArgumentException('Routing not found.');
        }

        if (!$routing->canBeRetired()) {
            throw new InvalidArgumentException('Only active or scheduled routings can be retired.');
        }

        return DB::transaction(function () use ($routing, $userId, $comments) {
            $today = Carbon::today()->toDateString();

            $routing->update([
                'status'       => Routing::STATUS_HISTORICAL,
                'effective_to' => $today,
            ]);

            RoutingApproval::create([
                'tenant_id'  => $routing->tenant_id,
                'routing_id' => $routing->id,
                'user_id'    => $userId,
                'action'     => RoutingApproval::ACTION_RETIRED,
                'comments'   => $comments ?: "Routing retired on {$today}.",
            ]);

            // RT-IMPL-06: approved BOMs → inactive, pending BOMs → draft, drafts → live advisory
            $this->routingLifecycleService->handleBomsOnRoutingEnded($routing, 'retired', $userId, $comments);

            return $routing->fresh();
        });
    }

    /**
     * RT-IMPL-20: Duplicate Version saves nothing. Returns the copy of a routing as a create payload (same shape the
     * create form / store endpoint take): header with the new version, every operation, alternatives and
     * predecessors linked by sequence, and the cost figures worked out from the latest section snapshots
     * (RT-IMPL-14). Problems (section not calculated, resource no longer in the snapshot) come back as warnings;
     * the save then refuses until they are fixed.
     */
    public function buildCopyPayload(int $id, string $newVersion): array
    {
        $original = $this->routingRepository->getRoutingWithOperations($id);

        if (!$original) {
            throw new InvalidArgumentException('Source routing not found.');
        }

        $this->checkRoutingConflicts($original->name, $newVersion, $original->tenant_id);

        $calculator = app(RoutingOperationCostCalculator::class);
        $sectionService = app(SectionCalculationService::class);
        $ops = $original->operations->sortBy('sequence')->values();
        $seqById = $ops->pluck('sequence', 'id');
        $snapshots = [];
        $warnings = [];
        $rows = [];

        foreach ($ops as $op) {
            $row = [
                'sequence'                       => $op->sequence,
                'operation_number'               => $op->operation_number,
                'name'                           => $op->name,
                'operation_type'                 => $op->operation_type,
                'description'                    => $op->description,
                'instructions'                   => $op->instructions,
                'is_alternative'                 => (bool) $op->is_alternative,
                'replaces_sequence'              => $op->is_alternative ? ($seqById[$op->replaces_operation_id] ?? null) : null,
                'priority'                       => (int) ($op->priority ?? 1),
                'work_center_id'                 => $op->work_center_id,
                'section_id'                     => $op->section_id,
                'machine_id'                     => $op->machine_id,
                'section_manual_production_id'   => $op->section_manual_production_id,
                'setup_time_minutes'             => (float) $op->setup_time_minutes,
                'setup_time_per_piece_minutes'   => $op->setup_time_per_piece_minutes !== null ? (float) $op->setup_time_per_piece_minutes : null,
                'processing_time_minutes'        => (float) $op->processing_time_minutes,
                'wait_time_minutes'              => (float) ($op->wait_time_minutes ?? 0),
                'expected_yield_percentage'      => (float) $op->expected_yield_percentage,
                'quality_required'               => (bool) $op->quality_required,
                'is_external'                    => (bool) $op->is_external,
                'vendor_id'                      => $op->vendor_id,
                'material_supply_type'           => $op->material_supply_type,
                'subcontract_input_type'         => $op->subcontract_input_type,
                'subcontract_lead_time_days'     => $op->subcontract_lead_time_days,
                'subcontract_cost_per_unit'      => $op->subcontract_cost_per_unit !== null ? (float) $op->subcontract_cost_per_unit : null,
                'subcontract_service_product_id' => $op->subcontract_service_product_id,
                'dispatch_buffer_days'           => $op->dispatch_buffer_days,
                'return_buffer_days'             => $op->return_buffer_days,
                'queue_threshold_enabled'        => (bool) ($op->queue_threshold_enabled ?? $op->overlap_enabled),
                'transfer_batch_quantity'        => $op->transfer_batch_quantity,
                'transfer_lag_minutes'           => $op->transfer_lag_minutes,
                'is_parallel'                    => (bool) $op->is_parallel,
                'parallel_group'                 => $op->parallel_group,
                'predecessor_sequence'           => $op->previous_operation_id ? ($seqById[$op->previous_operation_id] ?? null) : null,
            ];

            $resources = null;
            if (!$op->is_external && $op->section_id) {
                if (!array_key_exists($op->section_id, $snapshots)) {
                    $section = WorkCenterSection::find($op->section_id);
                    $snapshots[$op->section_id] = $section ? $sectionService->getSnapshotResourceCosts($section) : null;
                }
                $resources = $snapshots[$op->section_id];
                if (!$resources || !empty($resources['not_calculated'])) {
                    $warnings[] = "Operation {$op->name}: " . ($resources['message'] ?? 'section not found.');
                    $rows[] = $row;
                    continue;
                }
            }

            $calc = $calculator->calculate($row, $resources);
            if ($calc['error'] !== null) {
                $warnings[] = "Operation {$op->name}: the selected machine / batch is not in the latest section calculation — choose another resource.";
            } else {
                foreach ($calc['figures'] as $k => $v) {
                    $row[$k] = round((float) $v, 4);
                }
                if ($resources) {
                    $row['section_calculation_id'] = $resources['section_calculation_id'] ?? null;
                    $row['section_snapshot_number'] = $resources['snapshot_number'] ?? null;
                }
            }
            $rows[] = $row;
        }

        return [
            'source_routing_id' => $original->id,
            'source_version'    => $original->version,
            'routing_number'    => 'AUTO',
            'name'              => $original->name,
            'version'           => $newVersion,
            'effective_from'    => Carbon::today()->toDateString(),
            'effective_to'      => null,
            'is_default'        => (bool) $original->is_default,
            'description'       => $original->description,
            'operations'        => $rows,
            'warnings'          => $warnings,
        ];
    }

    /**
     * RT-IMPL-20: a routing saved from a Duplicate Version copy records where it came from — revision = source + 1,
     * "Revision Created" audit entry, revision event. Called after the normal create.
     */
    public function recordRevisionSource(Routing $routing, int $sourceRoutingId, ?int $userId = null): Routing
    {
        $source = Routing::where('tenant_id', $routing->tenant_id)->find($sourceRoutingId);
        if (!$source) {
            return $routing;
        }

        $routing->update(['revision' => (int) $source->revision + 1]);

        RoutingApproval::create([
            'tenant_id'  => $routing->tenant_id,
            'routing_id' => $routing->id,
            'user_id'    => $userId,
            'action'     => RoutingApproval::ACTION_REVISION_CREATED,
            'comments'   => "Version {$routing->version} created from {$source->routing_number} version {$source->version}.",
        ]);

        event(new \App\Domains\Production\Events\RoutingRevisionCreated($routing->fresh()));

        return $routing->fresh();
    }

    /**
     * Guard against duplicate [name, version] combinations per tenant.
     */
    public function checkRoutingConflicts(?string $name, string $version, int $tenantId, ?int $ignoreId = null): void
    {
        $query = Routing::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', $name)
            ->where('version', $version); // RT-IMPL-03: cancelled routings keep their name + version (DB unique key) — clear message instead of a database error

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException(
                "A routing with name '{$name}' and version '{$version}' already exists."
            );
        }
    }

    /**
     * RT-IMPL-09 (RT-10 rules 4–7): save operations IN PLACE — submitted rows are matched to the routing's existing
     * operations (by operation id from the form; by operation number when the payload carries no ids — API / import),
     * matched rows are updated (ids stay stable, so BOM allocations keep pointing at them), new rows are created and
     * only the operations the user removed are SOFT-deleted (their BOM allocations are kept; the BOM shows them).
     * Sequences of existing rows are moved out of the way first (sequence = −id) so the unique [routing_id, sequence]
     * key never clashes — soft-deleted rows keep their negative sequence.
     *
     * @return array<int, string> warnings for removed operations that BOMs still use
     */
    private function syncOperations(Routing $routing, array $operationDTOs, int $tenantId): array
    {
        $existing = $routing->operations()->get()->keyBy('id');
        $matchById = collect($operationDTOs)->contains(fn($d) => !empty($d->operation_id));
        $byNumber = $existing->keyBy('operation_number');

        $matched = [];
        $used = [];
        foreach ($operationDTOs as $index => $opDto) {
            $model = null;
            if ($matchById) {
                if ($opDto->operation_id && isset($existing[$opDto->operation_id])) {
                    $model = $existing[$opDto->operation_id];
                }
            } elseif ($opDto->operation_number && isset($byNumber[$opDto->operation_number])) {
                $model = $byNumber[$opDto->operation_number];
            }
            if ($model && !isset($used[$model->id])) {
                $matched[$index] = $model;
                $used[$model->id] = true;
            }
        }

        // Free every sequence of this routing (kept, removed and older soft-deleted rows) before writing new ones
        RoutingOperation::withTrashed()->where('routing_id', $routing->id)->where('sequence', '>', 0)
            ->update(['sequence' => DB::raw('0 - id')]);

        // Removed operations: warn about BOM allocations, soft-delete, audit
        $warnings = [];
        foreach ($existing as $op) {
            if (isset($used[$op->id])) {
                continue;
            }
            $allocations = RoutingOperationMaterial::where('routing_operation_id', $op->id)->get();
            if ($allocations->isNotEmpty()) {
                $bomNumbers = ProductionBom::withoutGlobalScopes()->whereIn('id', $allocations->pluck('bom_id')->filter()->unique())
                    ->pluck('bom_number')->implode(', ');
                $warnings[] = "Operation {$op->operation_number} ({$op->name}) is used by BOM {$bomNumbers} ({$allocations->count()} material(s)). "
                    . 'It was removed from this routing; those BOMs must move or remove its materials.';
            }
            $op->delete();
            RoutingApproval::create([
                'tenant_id'  => $routing->tenant_id,
                'routing_id' => $routing->id,
                'user_id'    => auth()->id(),
                'action'     => 'Operation Removed',
                'comments'   => "Operation {$op->operation_number} ({$op->name}) removed.",
            ]);
        }

        $createdOps = [];
        $opsBySeq = [];
        $uidToOpMap = [];

        foreach ($operationDTOs as $index => $opDto) {
            $seq = $opDto->sequence ?: (($index + 1) * 10);
            $data = array_merge($opDto->toArray(), [
                'tenant_id'        => $tenantId,
                'routing_id'       => $routing->id,
                'sequence'         => $seq,
                'operation_number' => $opDto->operation_number ?: 'OP-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            ]);
            unset($data['previous_operation_id'], $data['replaces_operation_id'], $data['materials']);

            // RT-IMPL-14 (RT-19 rule 2): figures are calculated ONCE (form / API), checked by the request
            // (ValidatesRoutingOperations), then saved exactly as sent — no recalculation here.
            $data['costs_calculated_at'] = now();
            if (!$opDto->is_external && $opDto->section_id && empty($data['section_calculation_id'])) {
                // API callers may omit the reference: record the snapshot whose figures the check used
                $section = \App\Domains\Production\Models\WorkCenterSection::find($opDto->section_id);
                if ($section) {
                    $meta = app(\App\Domains\Production\Services\SectionCalculationService::class)->getSnapshotResourceCosts($section);
                    $data['section_calculation_id']     = $meta['section_calculation_id'] ?? null;
                    $data['section_snapshot_number']    = $meta['snapshot_number'] ?? null;
                    $data['work_center_calculation_id'] = $meta['work_center_calculation_id'] ?? null;
                }
            }

            if (isset($matched[$index])) {
                $model = $matched[$index];
                // the row was moved to sequence = −id above; tell the model so an unchanged sequence is still written
                $model->setRawAttributes(array_merge($model->getAttributes(), ['sequence' => -$model->id]), true);
                $model->fill($data);
                $model->save();
            } else {
                $model = RoutingOperation::create($data);
            }

            $createdOps[] = ['model' => $model, 'dto' => $opDto];
            $opsBySeq[$seq] = $model->id;
            if (!empty($opDto->row_uid)) {
                $uidToOpMap[$opDto->row_uid] = $model;
            }
        }

        $idsOfThisRouting = collect($createdOps)->map(fn($c) => $c['model']->id)->all();

        // Pass 2 (RT-IMPL-08): bind alternatives unambiguously, only to operations of THIS routing
        foreach ($createdOps as $item) {
            $model = $item['model'];
            $dto = $item['dto'];

            if ($dto->is_alternative) {
                $resolvedReplacesId = null;

                if (!empty($dto->replaces_sequence) && isset($opsBySeq[$dto->replaces_sequence])) {
                    $resolvedReplacesId = $opsBySeq[$dto->replaces_sequence];
                } elseif (!empty($dto->replaces_row_uid) && isset($uidToOpMap[$dto->replaces_row_uid])) {
                    $resolvedReplacesId = $uidToOpMap[$dto->replaces_row_uid]->id;
                } elseif (!empty($dto->replaces_operation_id) && in_array((int) $dto->replaces_operation_id, $idsOfThisRouting, true)) {
                    $resolvedReplacesId = (int) $dto->replaces_operation_id;
                }

                // Guard: an operation can NEVER replace itself
                if ($resolvedReplacesId === $model->id) {
                    $resolvedReplacesId = null;
                }

                $model->replaces_operation_id = $resolvedReplacesId;
            } else {
                $model->replaces_operation_id = null;
            }
        }

        // Pass 3: Bind dependencies (previous_operation_id)
        // Rule 1: Non-alternatives resolve predecessor (-- Auto skips alternatives)
        // Rule 2: Alternatives mirror their primary operation's predecessor
        foreach ($createdOps as $index => $item) {
            $model = $item['model'];
            $dto = $item['dto'];

            if ($dto->is_parallel) {
                $model->previous_operation_id = null;
            } elseif ($dto->is_alternative) {
                if ($model->replaces_operation_id) {
                    $primaryOp = collect($createdOps)->first(fn($c) => $c['model']->id === $model->replaces_operation_id);
                    $model->previous_operation_id = $primaryOp ? $primaryOp['model']->previous_operation_id : null;
                } else {
                    $model->previous_operation_id = null;
                }
            } elseif ($dto->predecessor_sequence !== null) {
                if ($dto->predecessor_sequence <= 0) {
                    $model->previous_operation_id = null;
                } else {
                    $targetPredId = $opsBySeq[$dto->predecessor_sequence] ?? null;
                    $targetPredItem = collect($createdOps)->first(fn($c) => $c['model']->id === $targetPredId);
                    if ($targetPredItem && $targetPredItem['dto']->is_alternative) {
                        $model->previous_operation_id = $targetPredItem['model']->replaces_operation_id ?? null;
                    } else {
                        $model->previous_operation_id = $targetPredId;
                    }
                }
            } elseif ($index > 0) {
                $prevModel = null;
                for ($p = $index - 1; $p >= 0; $p--) {
                    if (!$createdOps[$p]['dto']->is_alternative) {
                        $prevModel = $createdOps[$p]['model'];
                        break;
                    }
                }
                $model->previous_operation_id = $prevModel?->id;
            } else {
                $model->previous_operation_id = null;
            }

            $model->save();
        }

        if ($warnings !== []) {
            session()->flash('warning', implode(' ', $warnings));
        }

        return $warnings;
    }

    // RT-IMPL-07 / RT-30: unused validateMachineBelongsToWorkCenter() / validateSectionBelongsToWorkCenter() removed —
    // the checks live in Requests\Concerns\ValidatesRoutingOperations (shared by web + API).


    /**
     * Validate that a machine belongs to the specified section.
     */
    public function validateMachineBelongsToSection(int $machineId, int $sectionId): bool
    {
        return Machine::where('id', $machineId)
            ->where('section_id', $sectionId)
            ->exists();
    }

    /**
     * Validate that a manual production batch belongs to the specified section.
     */
    public function validateManualProductionBelongsToSection(int $manualProductionId, int $sectionId): bool
    {
        return SectionManualProduction::where('id', $manualProductionId)
            ->where('section_id', $sectionId)
            ->exists();
    }
}
