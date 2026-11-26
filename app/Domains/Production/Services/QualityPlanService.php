<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionQualityPlanApproval;
use App\Domains\Production\Models\ProductionQualityPlanParameter;
use App\Support\TenantSequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Quality Plan lifecycle (docs/production_quality_rules.md):
 *  - QR-07: draft → pending_approval → approved, reject → draft, approved → historical (manual)
 *  - QR-08: a new plan is v1; editing a Draft changes it in place; editing an Approved vN creates vN+1 as a Draft.
 *           vN stays approved until vN+1 is approved, then becomes historical automatically.
 *  - A1:    only one open newer version (draft / pending) per plan family
 *  - QR-10: delete only in Draft, always a soft delete (plan + parameters)
 *  - QR-11: name + version unique per tenant (soft-deleted rows ignored)
 */
class QualityPlanService
{
    /**
     * Create a new plan: always Draft, version 1, its own plan number.
     */
    public function create(int $tenantId, array $data, int $userId): ProductionQualityPlan
    {
        $header = $this->normalizeHeader($data);
        $this->assertUniqueNameVersion($tenantId, $header['name'], '1');
        $this->assertProductFree($tenantId, $header);

        return DB::transaction(function () use ($tenantId, $header, $data, $userId) {
            $plan = ProductionQualityPlan::create($header + [
                'tenant_id'   => $tenantId,
                'plan_number' => $this->nextPlanNumber($tenantId),
                'version'     => '1',
                'status'      => ProductionQualityPlan::STATUS_DRAFT,
                'created_by'  => $userId,
            ]);
            $plan->update(['root_plan_id' => $plan->id]);

            $this->writeParameters($plan, $data['parameters'] ?? []);
            $this->log($plan, $userId, ProductionQualityPlanApproval::ACTION_CREATED, 'Version 1 created.');

            return $plan->fresh(['parameters']);
        });
    }

    /**
     * Save an edit. Returns the plan that now holds the edit: the same draft, or the newly created next version.
     */
    public function update(ProductionQualityPlan $plan, array $data, int $userId): ProductionQualityPlan
    {
        if ($plan->isDraft()) {
            $header = $this->normalizeHeader($data);
            $this->assertUniqueNameVersion($plan->tenant_id, $header['name'], (string) $plan->version, $plan->id);
            $this->assertProductFree($plan->tenant_id, $header, $plan->root_plan_id ?: $plan->id);

            return DB::transaction(function () use ($plan, $header, $data, $userId) {
                $plan->update($header);
                ProductionQualityPlanParameter::where('quality_plan_id', $plan->id)->get()->each->delete(); // soft delete
                $this->writeParameters($plan, $data['parameters'] ?? []);
                $this->log($plan, $userId, ProductionQualityPlanApproval::ACTION_UPDATED, null);

                return $plan->fresh(['parameters']);
            });
        }

        if ($plan->isApproved()) {
            return $this->createNextVersion($plan, $data, $userId);
        }

        throw new InvalidArgumentException(
            $plan->isPendingApproval()
                ? 'This plan is waiting for approval and cannot be edited. Reject it first to make changes.'
                : 'Historical plans are read only.'
        );
    }

    /**
     * Editing an Approved vN → vN+1 as a Draft (header + parameters from the submitted form). vN stays approved.
     */
    public function createNextVersion(ProductionQualityPlan $plan, array $data, int $userId): ProductionQualityPlan
    {
        $open = $this->openNewerVersion($plan);
        if ($open) {
            throw new InvalidArgumentException(
                "Version {$open->version} of this plan is already open ({$open->statusLabel()}). Finish or delete it before creating another version."
            );
        }

        $header = $this->normalizeHeader($data);
        $rootId = $plan->root_plan_id ?: $plan->id;
        $nextVersion = (string) ($this->maxFamilyVersion($plan->tenant_id, $rootId) + 1);
        $this->assertUniqueNameVersion($plan->tenant_id, $header['name'], $nextVersion);
        $this->assertProductFree($plan->tenant_id, $header, $rootId);

        return DB::transaction(function () use ($plan, $header, $data, $userId, $rootId, $nextVersion) {
            $new = ProductionQualityPlan::create($header + [
                'tenant_id'      => $plan->tenant_id,
                'plan_number'    => $plan->plan_number,
                'root_plan_id'   => $rootId,
                'source_plan_id' => $plan->id,
                'version'        => $nextVersion,
                'status'         => ProductionQualityPlan::STATUS_DRAFT,
                'created_by'     => $userId,
            ]);

            $this->writeParameters($new, $data['parameters'] ?? []);
            $this->log($new, $userId, ProductionQualityPlanApproval::ACTION_VERSION_CREATED, "Version {$nextVersion} created from version {$plan->version}.");

            return $new->fresh(['parameters']);
        });
    }

    public function submit(ProductionQualityPlan $plan, int $userId): ProductionQualityPlan
    {
        if (!$plan->isDraft()) {
            throw new InvalidArgumentException('Only draft plans can be submitted for approval.');
        }
        if ($plan->parameters()->count() === 0) {
            throw new InvalidArgumentException('Add at least one checklist parameter before submitting.');
        }

        return DB::transaction(function () use ($plan, $userId) {
            $plan->update([
                'status'       => ProductionQualityPlan::STATUS_PENDING_APPROVAL,
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]);
            $this->log($plan, $userId, ProductionQualityPlanApproval::ACTION_SUBMITTED, null);

            return $plan->fresh();
        });
    }

    /**
     * Approve vN; every other approved version of the family becomes historical in the same step (QR-08).
     */
    public function approve(ProductionQualityPlan $plan, int $userId, ?string $comments = null): ProductionQualityPlan
    {
        if (!$plan->isPendingApproval()) {
            throw new InvalidArgumentException('Only plans pending approval can be approved.');
        }

        return DB::transaction(function () use ($plan, $userId, $comments) {
            $rootId = $plan->root_plan_id ?: $plan->id;

            ProductionQualityPlan::where('tenant_id', $plan->tenant_id)
                ->where('root_plan_id', $rootId)
                ->where('id', '!=', $plan->id)
                ->where('status', ProductionQualityPlan::STATUS_APPROVED)
                ->get()
                ->each(function (ProductionQualityPlan $old) use ($plan, $userId) {
                    $old->update([
                        'status'        => ProductionQualityPlan::STATUS_HISTORICAL,
                        'historical_at' => now(),
                    ]);
                    $this->log($old, $userId, ProductionQualityPlanApproval::ACTION_HISTORICAL, "Replaced by version {$plan->version}.");
                });

            $plan->update([
                'status'      => ProductionQualityPlan::STATUS_APPROVED,
                'approved_by' => $userId,
                'approved_at' => now(),
                'esignature'  => hash('sha256', $userId . '|' . $plan->id . '|approved|' . now()->timestamp),
            ]);
            $this->log($plan, $userId, ProductionQualityPlanApproval::ACTION_APPROVED, $comments);

            return $plan->fresh();
        });
    }

    public function reject(ProductionQualityPlan $plan, int $userId, ?string $comments): ProductionQualityPlan
    {
        if (!$plan->isPendingApproval()) {
            throw new InvalidArgumentException('Only plans pending approval can be rejected.');
        }
        if (trim((string) $comments) === '') {
            throw new InvalidArgumentException('Enter a reason for the rejection.');
        }

        return DB::transaction(function () use ($plan, $userId, $comments) {
            $plan->update([
                'status'       => ProductionQualityPlan::STATUS_DRAFT,
                'submitted_by' => null,
                'submitted_at' => null,
            ]);
            $this->log($plan, $userId, ProductionQualityPlanApproval::ACTION_REJECTED, $comments);

            return $plan->fresh();
        });
    }

    public function markHistorical(ProductionQualityPlan $plan, int $userId, ?string $comments = null): ProductionQualityPlan
    {
        if (!$plan->isApproved()) {
            throw new InvalidArgumentException('Only approved plans can be marked historical.');
        }

        return DB::transaction(function () use ($plan, $userId, $comments) {
            $plan->update([
                'status'        => ProductionQualityPlan::STATUS_HISTORICAL,
                'historical_at' => now(),
            ]);
            $this->log($plan, $userId, ProductionQualityPlanApproval::ACTION_HISTORICAL, $comments);

            return $plan->fresh();
        });
    }

    /**
     * QR-10: Draft only; the user sees "permanently deleted", the database keeps a soft-deleted copy.
     */
    public function delete(ProductionQualityPlan $plan, int $userId): void
    {
        if (!$plan->isDraft()) {
            throw new InvalidArgumentException('Only draft plans can be deleted. Approved plans can only be marked historical.');
        }

        DB::transaction(function () use ($plan, $userId) {
            $this->log($plan, $userId, ProductionQualityPlanApproval::ACTION_DELETED, null);
            ProductionQualityPlanParameter::where('quality_plan_id', $plan->id)->get()->each->delete();
            $plan->delete();
        });
    }

    /** A1: the open (draft / pending) newer version of this plan's family, if any. */
    public function openNewerVersion(ProductionQualityPlan $plan): ?ProductionQualityPlan
    {
        return ProductionQualityPlan::where('tenant_id', $plan->tenant_id)
            ->where('root_plan_id', $plan->root_plan_id ?: $plan->id)
            ->where('id', '!=', $plan->id)
            ->whereIn('status', [ProductionQualityPlan::STATUS_DRAFT, ProductionQualityPlan::STATUS_PENDING_APPROVAL])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * QM-14: a product can have only ONE quality plan in use — any draft / pending / approved plan of another plan family
     * blocks a new one. A new plan for the product is possible only once the existing one is historical (or deleted).
     * Returns the plan that holds the product, or null.
     */
    public function productPlanHolder(int $tenantId, int $productId, ?int $ignoreRootId = null): ?ProductionQualityPlan
    {
        return ProductionQualityPlan::where('tenant_id', $tenantId)
            ->where('type', ProductionQualityPlan::SCOPE_PRODUCT)
            ->where('product_id', $productId)
            ->whereIn('status', [
                ProductionQualityPlan::STATUS_DRAFT,
                ProductionQualityPlan::STATUS_PENDING_APPROVAL,
                ProductionQualityPlan::STATUS_APPROVED,
            ])
            ->when($ignoreRootId, fn ($q) => $q->where(fn ($r) => $r->where('root_plan_id', '!=', $ignoreRootId)->orWhereNull('root_plan_id')))
            ->orderByRaw("CASE status WHEN 'approved' THEN 0 WHEN 'pending_approval' THEN 1 ELSE 2 END")
            ->first();
    }

    /**
     * Product id => the plan that already holds it (draft / pending / approved), for the plan form's product dropdown.
     *
     * @return array<int, ProductionQualityPlan>
     */
    public function productPlanHolders(int $tenantId): array
    {
        return ProductionQualityPlan::where('tenant_id', $tenantId)
            ->where('type', ProductionQualityPlan::SCOPE_PRODUCT)
            ->whereNotNull('product_id')
            ->whereIn('status', [
                ProductionQualityPlan::STATUS_DRAFT,
                ProductionQualityPlan::STATUS_PENDING_APPROVAL,
                ProductionQualityPlan::STATUS_APPROVED,
            ])
            ->orderByRaw("CASE status WHEN 'approved' THEN 0 WHEN 'pending_approval' THEN 1 ELSE 2 END")
            ->get()
            ->unique('product_id')
            ->keyBy('product_id')
            ->all();
    }

    private function assertProductFree(int $tenantId, array $header, ?int $ignoreRootId = null): void
    {
        if (($header['type'] ?? null) !== ProductionQualityPlan::SCOPE_PRODUCT || empty($header['product_id'])) {
            return;
        }

        $holder = $this->productPlanHolder($tenantId, (int) $header['product_id'], $ignoreRootId);
        if ($holder) {
            throw new InvalidArgumentException(
                "This product already has quality plan {$holder->plan_number} ({$holder->name} v{$holder->version}, {$holder->statusLabel()}). "
                . 'Edit that plan instead — a new plan for this product can be created only after it becomes historical.'
            );
        }
    }

    /** QR-11: name + version unique per tenant; soft-deleted plans are ignored (a deleted draft's name can be reused). */
    public function assertUniqueNameVersion(int $tenantId, string $name, string $version, ?int $ignoreId = null): void
    {
        $exists = ProductionQualityPlan::where('tenant_id', $tenantId)
            ->where('name', $name)
            ->where('version', $version)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException("A quality plan named '{$name}' with version {$version} already exists.");
        }
    }

    /**
     * QR-16: plans the routing QC modal can offer — Approved Section + Process General plans — plus any plan already
     * linked in the routing being edited (so an older linked version still shows, flagged). Keyed by plan id.
     *
     * @param  array<int>  $includeIds
     * @return array<int, array>
     */
    public function routingCatalog(int $tenantId, array $includeIds = []): array
    {
        $plans = ProductionQualityPlan::where('tenant_id', $tenantId)
            ->where(function ($q) use ($includeIds) {
                $q->where(function ($a) {
                    $a->where('status', ProductionQualityPlan::STATUS_APPROVED)
                        ->whereIn('type', [ProductionQualityPlan::SCOPE_SECTION, ProductionQualityPlan::SCOPE_PROCESS]);
                });
                if (!empty($includeIds)) {
                    $q->orWhereIn('id', $includeIds);
                }
            })
            ->with(['parameters', 'section'])
            ->orderBy('name')
            ->get();

        $latestApproved = ProductionQualityPlan::where('tenant_id', $tenantId)
            ->approved()
            ->whereIn('root_plan_id', $plans->pluck('root_plan_id')->filter()->unique())
            ->get()
            ->keyBy('root_plan_id');

        return $plans->mapWithKeys(function (ProductionQualityPlan $p) use ($latestApproved) {
            $option = $this->planOption($p);
            $current = $latestApproved[$p->root_plan_id] ?? null;
            $option['newer_version'] = ($current && $current->id !== $p->id) ? $current->versionNumber() : null;
            $option['newer_version_id'] = ($current && $current->id !== $p->id) ? $current->id : null;

            return [$p->id => $option];
        })->all();
    }

    /**
     * QR-09: for each linked plan of a routing, a warning when the plan is no longer the approved version
     * (a newer approved version exists, or it was marked historical). Keyed by RoutingOperationQualityPlan id.
     * Loads operations.qualityPlanLinks.plan on the routing.
     *
     * @return array<int, string>
     */
    public function routingPlanWarnings(\App\Domains\Production\Models\Routing $routing): array
    {
        $routing->loadMissing('operations.qualityPlanLinks.plan');

        $links = $routing->operations->flatMap(fn ($op) => $op->quality_required ? $op->qualityPlanLinks : collect());
        if ($links->isEmpty()) {
            return [];
        }

        $approvedByRoot = ProductionQualityPlan::where('tenant_id', $routing->tenant_id)
            ->approved()
            ->whereIn('root_plan_id', $links->map(fn ($l) => $l->plan?->root_plan_id)->filter()->unique())
            ->get()
            ->keyBy('root_plan_id');

        $warnings = [];
        foreach ($links as $link) {
            $plan = $link->plan;
            if (!$plan) {
                continue;
            }
            $current = $approvedByRoot[$plan->root_plan_id] ?? null;
            if ($current && $current->id !== $plan->id) {
                $warnings[$link->id] = "QC plan {$plan->name} v{$plan->version} has a newer approved version v{$current->version}.";
            } elseif (!$plan->isApproved()) {
                $warnings[$link->id] = "QC plan {$plan->name} v{$plan->version} is {$plan->statusLabel()} and has no approved version.";
            }
        }

        return $warnings;
    }

    /** Shape used by the routing QC modal and its in-modal preview. */
    public function planOption(ProductionQualityPlan $p): array
    {
        return [
            'id'              => $p->id,
            'name'            => $p->name,
            'plan_number'     => $p->plan_number,
            'version'         => $p->versionNumber(),
            'scope'           => $p->type,
            'scope_label'     => $p->type === ProductionQualityPlan::SCOPE_SECTION ? 'Section' : ($p->type === ProductionQualityPlan::SCOPE_PROCESS ? 'Process general' : 'Product'),
            'section_id'      => $p->section_id,
            'status'          => $p->status,
            'status_label'    => $p->statusLabel(),
            'show_url'        => route('production.quality-plans.show', $p->id),
            'checks'          => $p->parameters->map(fn ($param) => [
                'name'      => $param->name,
                'type'      => $param->typeLabel(),
                'limits'    => $param->type === ProductionQualityPlanParameter::TYPE_TEXT ? 'Free notes at inspection' : $param->limitsLabel(),
                'mandatory' => (bool) $param->is_mandatory,
            ])->values()->all(),
            'checks_count'    => $p->parameters->count(),
            'mandatory_count' => $p->parameters->where('is_mandatory', true)->count(),
        ];
    }

    public function nextPlanNumber(int $tenantId): string
    {
        $n = TenantSequence::next($tenantId, 'quality_plan', function () use ($tenantId) {
            return (int) ProductionQualityPlan::withTrashed()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('plan_number')
                ->pluck('plan_number')
                ->map(fn ($num) => (int) preg_replace('/\D/', '', (string) $num))
                ->max();
        });

        return 'QP-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    private function maxFamilyVersion(int $tenantId, int $rootId): int
    {
        return (int) ProductionQualityPlan::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('root_plan_id', $rootId)
            ->pluck('version')
            ->map(fn ($v) => (int) $v)
            ->max();
    }

    /** QR-02: only the field that belongs to the chosen scope is kept. */
    private function normalizeHeader(array $data): array
    {
        $type = $data['type'] ?? ProductionQualityPlan::SCOPE_PROCESS;

        return [
            'name'                => trim((string) ($data['name'] ?? '')),
            'type'                => $type,
            'product_id'          => $type === ProductionQualityPlan::SCOPE_PRODUCT ? ($data['product_id'] ?? null) : null,
            'section_id'          => $type === ProductionQualityPlan::SCOPE_SECTION ? ($data['section_id'] ?? null) : null,
            'work_center_id'      => null,
            'product_category_id' => null,
        ];
    }

    /** QR-06: min/max/unit only for numeric rows, basis only for pass/fail rows — anything else is dropped. */
    private function writeParameters(ProductionQualityPlan $plan, array $parameters): void
    {
        // Unit comes from the UOM master: uom_id is saved with its code as the label shown everywhere
        $uomCodes = \App\Domains\Inventory\Models\Uom::withoutGlobalScopes()->where('tenant_id', $plan->tenant_id)
            ->whereIn('id', collect($parameters)->pluck('uom_id')->filter()->map(fn ($id) => (int) $id)->unique()->all())
            ->pluck('code', 'id');

        foreach (array_values($parameters) as $i => $param) {
            $type = $param['type'] ?? ProductionQualityPlanParameter::TYPE_NUMERIC;
            $isNumeric = $type === ProductionQualityPlanParameter::TYPE_NUMERIC;
            $num = static fn ($v) => ($v === null || $v === '') ? null : (float) $v;
            $uomId = $isNumeric && !empty($param['uom_id']) && isset($uomCodes[(int) $param['uom_id']]) ? (int) $param['uom_id'] : null;

            ProductionQualityPlanParameter::create([
                'tenant_id'       => $plan->tenant_id,
                'quality_plan_id' => $plan->id,
                'name'            => trim((string) ($param['name'] ?? '')),
                'type'            => $type,
                'min_value'       => $isNumeric ? $num($param['min_value'] ?? null) : null,
                'max_value'       => $isNumeric ? $num($param['max_value'] ?? null) : null,
                'uom_id'          => $uomId,
                'unit_of_measure' => $uomId ? $uomCodes[$uomId] : null,
                'basis'           => $type === ProductionQualityPlanParameter::TYPE_PASS_FAIL ? (trim((string) ($param['basis'] ?? '')) ?: null) : null,
                'is_mandatory'    => filter_var($param['is_mandatory'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'sort_order'      => $i + 1,
            ]);
        }
    }

    private function log(ProductionQualityPlan $plan, ?int $userId, string $action, ?string $comments): void
    {
        ProductionQualityPlanApproval::create([
            'tenant_id'       => $plan->tenant_id,
            'quality_plan_id' => $plan->id,
            'user_id'         => $userId,
            'action'          => $action,
            'comments'        => $comments,
        ]);
    }
}
