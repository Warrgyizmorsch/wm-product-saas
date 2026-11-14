<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ExternalWorkerPayGrade;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExternalWorkerPayGradeService
{
    /**
     * Create a new external worker pay grade for a tenant.
     *
     * @param array{name: string, code: string, hourly_rate?: float|numeric, description?: string|null, active?: bool} $data
     * @param int $tenantId
     * @return ExternalWorkerPayGrade
     */
    public function create(array $data, int $tenantId): ExternalWorkerPayGrade
    {
        $code = trim($data['code'] ?? '');
        $name = trim($data['name'] ?? '');

        if ($code === '') {
            throw new InvalidArgumentException('Pay grade code is required.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('Pay grade name is required.');
        }

        // Validate unique code within tenant
        if ($this->findByCode($code, $tenantId)) {
            throw new InvalidArgumentException("A pay grade with code '{$code}' already exists for this tenant.");
        }

        $hourlyRate = isset($data['hourly_rate']) ? (float) $data['hourly_rate'] : 0.0000;
        if ($hourlyRate < 0) {
            throw new InvalidArgumentException('Hourly rate cannot be negative.');
        }

        return ExternalWorkerPayGrade::create([
            'tenant_id'   => $tenantId,
            'name'        => $name,
            'code'        => $code,
            'hourly_rate' => $hourlyRate,
            'description' => $data['description'] ?? null,
            'active'      => isset($data['active']) ? (bool) $data['active'] : true,
        ]);
    }

    /**
     * Update an existing pay grade.
     */
    public function update(int $id, array $data, int $tenantId): ExternalWorkerPayGrade
    {
        $payGrade = ExternalWorkerPayGrade::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$payGrade) {
            throw new InvalidArgumentException("Pay grade #{$id} not found for this tenant.");
        }

        $updateData = [];

        if (isset($data['code'])) {
            $code = trim((string) $data['code']);
            if ($code === '') {
                throw new InvalidArgumentException('Pay grade code cannot be empty.');
            }

            $existing = ExternalWorkerPayGrade::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('code', $code)
                ->where('id', '!=', $id)
                ->first();

            if ($existing) {
                throw new InvalidArgumentException("A pay grade with code '{$code}' already exists for this tenant.");
            }
            $updateData['code'] = $code;
        }

        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new InvalidArgumentException('Pay grade name cannot be empty.');
            }
            $updateData['name'] = $name;
        }

        if (isset($data['hourly_rate'])) {
            $rate = (float) $data['hourly_rate'];
            if ($rate < 0) {
                throw new InvalidArgumentException('Hourly rate cannot be negative.');
            }
            $updateData['hourly_rate'] = $rate;
        }

        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['active'])) {
            $newActive = (bool) $data['active'];
            if (!$newActive && $payGrade->isActive()) {
                $hasActiveAllocations = DB::table('production_external_worker_allocations')
                    ->where('tenant_id', $tenantId)
                    ->where('pay_grade_id', $id)
                    ->where('active', 1)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($hasActiveAllocations) {
                    throw new InvalidArgumentException("Cannot deactivate pay grade '{$payGrade->name}' because it has active external worker allocations. Deactivate all referencing allocations before deactivating.");
                }
            }
            $updateData['active'] = $newActive;
        }

        if (!empty($updateData)) {
            $payGrade->update($updateData);
        }

        return $payGrade->fresh();
    }

    /**
     * Delete a pay grade.
     */
    public function delete(int $id, int $tenantId): bool
    {
        $payGrade = ExternalWorkerPayGrade::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$payGrade) {
            throw new InvalidArgumentException("Pay grade #{$id} not found for this tenant.");
        }

        // Rule A: Pay Grade must not be deleted while any active External Worker Allocation references it.
        $hasActiveAllocations = DB::table('production_external_worker_allocations')
            ->where('tenant_id', $tenantId)
            ->where('pay_grade_id', $id)
            ->where('active', 1)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasActiveAllocations) {
            throw new InvalidArgumentException("Cannot delete pay grade '{$payGrade->name}' because it has active external worker allocations. Deactivate all referencing allocations before deleting.");
        }

        return (bool) $payGrade->delete();
    }

    public function findByCode(string $code, int $tenantId): ?ExternalWorkerPayGrade
    {
        return ExternalWorkerPayGrade::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', trim($code))
            ->first();
    }

    public function getAll(int $tenantId, bool $activeOnly = false): Collection
    {
        $query = ExternalWorkerPayGrade::withoutGlobalScopes()
            ->where('tenant_id', $tenantId);

        if ($activeOnly) {
            $query->where('active', true);
        }

        return $query->orderBy('name')->get();
    }
}
