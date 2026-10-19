<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\DTO\WorkCenterDTO;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Repositories\WorkCenterRepositoryInterface;
use InvalidArgumentException;

class WorkCenterService
{
    public function __construct(
        private readonly WorkCenterRepositoryInterface $repository
    ) {}

    public function create(WorkCenterDTO $dto, int $tenantId): WorkCenter
    {
        // Validate unique code per tenant
        if ($this->repository->findByCode($dto->code, $tenantId)) {
            throw new InvalidArgumentException(
                "A work center with code '{$dto->code}' already exists."
            );
        }

        return $this->repository->create(
            array_merge($dto->toArray(), ['tenant_id' => $tenantId])
        );
    }

    public function update(int $id, WorkCenterDTO $dto): WorkCenter
    {
        $workCenter = $this->repository->find($id);

        if (!$workCenter) {
            throw new InvalidArgumentException('Work center not found.');
        }

        // Validate unique code (excluding self)
        $existing = $this->repository->findByCode($dto->code, $workCenter->tenant_id, $id);
        if ($existing) {
            throw new InvalidArgumentException(
                "A work center with code '{$dto->code}' already exists."
            );
        }

        return $this->repository->update($id, $dto->toArray());
    }

    public function delete(int $id): bool
    {
        $workCenter = $this->repository->find($id);

        if (!$workCenter) {
            throw new InvalidArgumentException('Work center not found.');
        }

        // Prevent deletion if work center has active routing operations
        if ($workCenter->operations()->exists()) {
            throw new InvalidArgumentException(
                'Cannot delete a work center that is used in routing operations. Deactivate it instead.'
            );
        }

        return $this->repository->delete($id);
    }

    /**
     * Synchronize shifts for a work center and enforce section shift cascade rules.
     *
     * @param WorkCenter $workCenter
     * @param array $newShiftIds
     * @param int $tenantId
     * @param bool $confirmed
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     */
    public function syncShifts(WorkCenter $workCenter, array $newShiftIds, int $tenantId, bool $confirmed = false): array
    {
        $newShiftIds = array_values(array_unique(array_filter(array_map('intval', $newShiftIds))));
        $currentShiftIds = $workCenter->shifts()->pluck('production_shifts.id')->map(fn($v) => (int)$v)->toArray();
        $removedShiftIds = array_values(array_diff($currentShiftIds, $newShiftIds));

        // If no shifts are being removed, synchronize Work Center shifts directly
        if (empty($removedShiftIds)) {
            $syncData = [];
            foreach ($newShiftIds as $shiftId) {
                $syncData[$shiftId] = ['tenant_id' => $tenantId];
            }
            $workCenter->shifts()->sync($syncData);

            return ['success' => true, 'requires_confirmation' => false, 'affected_sections' => []];
        }

        // Identify all sections under this work center and inspect their assigned shifts
        $sections = $workCenter->sections()->with('shifts')->get();

        $affectedSections = [];
        $blockedSections = [];

        foreach ($sections as $section) {
            $sectionShiftIds = $section->shifts->pluck('id')->map(fn($v) => (int)$v)->toArray();
            $shiftsBeingRemoved = array_values(array_intersect($sectionShiftIds, $removedShiftIds));

            if (!empty($shiftsBeingRemoved)) {
                $remainingCount = count($sectionShiftIds) - count($shiftsBeingRemoved);
                if ($remainingCount <= 0) {
                    $blockedSections[] = [
                        'section' => $section,
                        'removed_shifts' => $shiftsBeingRemoved,
                    ];
                } else {
                    $affectedSections[] = [
                        'section' => $section,
                        'removed_shifts' => $shiftsBeingRemoved,
                    ];
                }
            }
        }

        // Rule 4: If any affected section would lose its only assigned shift, block the entire operation.
        if (!empty($blockedSections)) {
            $details = implode(', ', array_map(function ($item) {
                return "'{$item['section']->name}' ({$item['section']->code})";
            }, $blockedSections));

            throw \Illuminate\Validation\ValidationException::withMessages([
                'shifts' => "Cannot remove shift(s) from Work Center '{$workCenter->name}' because Section(s) {$details} would be left without any assigned shifts. Every Section must have at least one assigned shift.",
            ]);
        }

        // Rule 2: If affected sections would lose shifts but not their last shift, require confirmation.
        if (!empty($affectedSections) && !$confirmed) {
            $sectionNames = implode(', ', array_map(fn($item) => "'{$item['section']->name}'", $affectedSections));
            $warningMessage = "Removing shift(s) from Work Center '{$workCenter->name}' will also remove them from section(s): {$sectionNames}. Please confirm to proceed.";

            throw \Illuminate\Validation\ValidationException::withMessages([
                'confirm_section_shift_removal' => $warningMessage,
            ]);
        }

        // Rule 3: Atomic removal from Work Center and all affected sections
        return \Illuminate\Support\Facades\DB::transaction(function () use ($workCenter, $newShiftIds, $affectedSections, $tenantId) {
            foreach ($affectedSections as $item) {
                $item['section']->shifts()->detach($item['removed_shifts']);
            }

            $syncData = [];
            foreach ($newShiftIds as $shiftId) {
                $syncData[$shiftId] = ['tenant_id' => $tenantId];
            }
            $workCenter->shifts()->sync($syncData);

            return [
                'success' => true,
                'requires_confirmation' => false,
                'affected_sections' => $affectedSections,
            ];
        });
    }
}
