<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionAlertConfiguration;
use App\Domains\Production\Models\ProductionMachineDowntime;
use Carbon\Carbon;

class AlertService
{
    public function __construct(
        private readonly OeeCalculationService $oeeService,
        private readonly ProductionEventService $eventService
    ) {}

    /**
     * Audit machine metrics against alert thresholds and publish events.
     */
    public function checkAlerts(int $tenantId): void
    {
        $start = Carbon::today();
        $end   = Carbon::today()->endOfDay();

        // 1. Fetch alert thresholds
        $configs = ProductionAlertConfiguration::where('tenant_id', $tenantId)
            ->where('active', true)
            ->get();

        $machines = Machine::where('tenant_id', $tenantId)->get();

        foreach ($machines as $machine) {
            $metrics = $this->oeeService->calculateForMachine($tenantId, $machine->id, $start, $end);

            // Audit OEE threshold
            $oeeConfig = $configs->where('alert_type', 'oee_below_threshold')->first();
            $oeeLimit = $oeeConfig ? (float)$oeeConfig->threshold : 80.00;

            if ($metrics['oee'] < $oeeLimit && $metrics['total_produced'] > 0) {
                $this->eventService->writeEvent($tenantId, [
                    'machine_id'   => $machine->id,
                    'event_type'   => 'Alert Fired',
                    'title'        => 'Low OEE Alert',
                    'description'  => "Machine {$machine->name} is running at an OEE of {$metrics['oee']}% (Threshold: {$oeeLimit}%).",
                    'severity'     => $oeeConfig->severity ?? 'warning',
                    'event_source' => 'AlertService',
                    'metadata'     => [
                        'oee'       => $metrics['oee'],
                        'threshold' => $oeeLimit
                    ]
                ]);
            }

            // Audit high scrap rates
            $scrapConfig = $configs->where('alert_type', 'scrap_rate_high')->first();
            $scrapLimit = $scrapConfig ? (float)$scrapConfig->threshold : 5.00;

            $totalProduced = $metrics['total_produced'];
            $scrapped = $metrics['scrapped_quantity'] + $metrics['rejected_quantity'];
            $scrapRate = $totalProduced > 0 ? ($scrapped / $totalProduced) * 100 : 0.00;

            if ($scrapRate > $scrapLimit) {
                $this->eventService->writeEvent($tenantId, [
                    'machine_id'   => $machine->id,
                    'event_type'   => 'Alert Fired',
                    'title'        => 'High Scrap Rate Alert',
                    'description'  => "Machine {$machine->name} has a scrap/reject rate of {$scrapRate}% (Threshold: {$scrapLimit}%).",
                    'severity'     => $scrapConfig->severity ?? 'critical',
                    'event_source' => 'AlertService',
                    'metadata'     => [
                        'scrap_rate' => $scrapRate,
                        'threshold'  => $scrapLimit
                    ]
                ]);
            }

            // Audit active breakdown downtime exceeding 30 mins
            $activeBreakdowns = ProductionMachineDowntime::where('tenant_id', $tenantId)
                ->where('machine_id', $machine->id)
                ->where('category', 'Breakdown')
                ->where('status', 'open')
                ->get();

            foreach ($activeBreakdowns as $ab) {
                $dur = now()->diffInMinutes($ab->start_time);
                if ($dur > 30) {
                    $this->eventService->writeEvent($tenantId, [
                        'machine_id'   => $machine->id,
                        'event_type'   => 'Alert Fired',
                        'title'        => 'Critical Breakdown Alert',
                        'description'  => "Machine {$machine->name} has been down due to breakdown for {$dur} minutes.",
                        'severity'     => 'critical',
                        'event_source' => 'AlertService',
                        'metadata'     => [
                            'downtime_id' => $ab->id,
                            'duration'    => $dur
                        ]
                    ]);
                }
            }
        }
    }
}
