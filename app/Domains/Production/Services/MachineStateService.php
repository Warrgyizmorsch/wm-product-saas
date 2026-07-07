<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineStateHistory;
use Illuminate\Support\Facades\DB;

class MachineStateService
{
    /**
     * Transition a machine to a new state and record in history.
     */
    public function transitionState(
        int $tenantId,
        int $machineId,
        string $newState,
        ?string $reason = null,
        ?int $userId = null,
        ?string $remarks = null
    ): void {
        DB::transaction(function () use ($tenantId, $machineId, $newState, $reason, $userId, $remarks) {
            $machine = Machine::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($machineId);

            // 1. Close current active state record if any exists
            $activeHistory = ProductionMachineStateHistory::where('tenant_id', $tenantId)
                ->where('machine_id', $machineId)
                ->whereNull('ended_at')
                ->first();

            if ($activeHistory) {
                $now = now();
                $duration = max(0, $now->diffInSeconds($activeHistory->started_at));
                $activeHistory->update([
                    'ended_at'         => $now,
                    'duration_seconds' => $duration,
                ]);
            }

            // 2. Create new state history record
            ProductionMachineStateHistory::create([
                'tenant_id'        => $tenantId,
                'machine_id'       => $machineId,
                'state'            => $newState,
                'reason'           => $reason,
                'started_at'       => now(),
                'ended_at'         => null,
                'duration_seconds' => null,
                'changed_by'       => $userId,
                'remarks'          => $remarks,
            ]);

            // 3. Update Machine model current state
            $machine->update([
                'current_state'        => $newState,
                'current_state_reason' => $reason,
            ]);
        });
    }
}
