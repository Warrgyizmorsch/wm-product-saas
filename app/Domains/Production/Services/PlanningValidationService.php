<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\WorkCenter;
use Illuminate\Support\Carbon;

class PlanningValidationService
{
    /**
     * Run all validations on a production plan and return an array of warnings.
     * Each warning has: type, severity (warning/danger), message.
     */
    public function validatePlan(ProductionPlan $plan): array
    {
        $warnings = [];

        // 1. Check Dates
        $today = Carbon::today();
        if ($plan->start_date->lt($today) && $plan->isDraft()) {
            $warnings[] = [
                'type' => 'invalid_dates',
                'severity' => 'warning',
                'message' => "The plan's start date ({$plan->start_date->format('Y-m-d')}) is in the past.",
            ];
        }

        if ($plan->start_date->gt($plan->end_date)) {
            $warnings[] = [
                'type' => 'invalid_dates',
                'severity' => 'danger',
                'message' => 'The start date cannot be after the end date.',
            ];
        }

        // 2. Check BOM
        if (!$plan->bom_id) {
            $warnings[] = [
                'type' => 'missing_bom',
                'severity' => 'danger',
                'message' => 'No Bill of Materials (BOM) has been assigned to this plan.',
            ];
        }

        // 3. Check Routing
        if (!$plan->routing_id) {
            $warnings[] = [
                'type' => 'missing_routing',
                'severity' => 'danger',
                'message' => 'No manufacturing Routing has been assigned to this plan.',
            ];
        } else {
            $routing = $plan->routing;
            if ($routing && $routing->status !== 'active') {
                $warnings[] = [
                    'type' => 'inactive_routing',
                    'severity' => 'warning',
                    'message' => "The selected routing ({$routing->routing_number}) is in status '{$routing->status}' (expected 'active').",
                ];
            }
        }

        // 4. Capacity Overload Validation
        // Calculate operating days in the planning window
        $days = $plan->start_date->diffInDays($plan->end_date) + 1;
        
        // Sum required minutes per Work Center from the plan's snapshotted operations
        if ($plan->operations()->exists()) {
            $wcLoads = $plan->operations()
                ->selectRaw('work_center_id, sum(total_time_minutes) as required_minutes')
                ->groupBy('work_center_id')
                ->get();

            foreach ($wcLoads as $load) {
                $wc = WorkCenter::find($load->work_center_id);
                if (!$wc) {
                    $warnings[] = [
                        'type' => 'missing_work_center',
                        'severity' => 'danger',
                        'message' => "Work Center ID {$load->work_center_id} is referenced but does not exist in the database.",
                    ];
                    continue;
                }

                // If capacity is defined, calculate availability. Null means unlimited.
                if ($wc->capacity_per_hour !== null && $wc->capacity_per_hour > 0) {
                    $efficiency = $wc->efficiency_percentage > 0 ? ($wc->efficiency_percentage / 100) : 1.0;
                    
                    // Available hours = hours/day (8) * operating days * efficiency factor * capacity units/hr
                    $availableHours = 8.0 * $days * $efficiency * $wc->capacity_per_hour;
                    $availableMinutes = $availableHours * 60.0;
                    $requiredMinutes = (float) $load->required_minutes;

                    if ($requiredMinutes > $availableMinutes) {
                        $overloadPercent = round((($requiredMinutes - $availableMinutes) / $availableMinutes) * 100, 1);
                        $reqHrs = round($requiredMinutes / 60, 1);
                        $availHrs = round($availableMinutes / 60, 1);
                        $warnings[] = [
                            'type' => 'capacity_overload',
                            'severity' => 'warning',
                            'message' => "Work Center overload detected at [{$wc->code} - {$wc->name}]: Requires {$reqHrs} hours but only {$availHrs} hours are available ({$overloadPercent}% overload).",
                        ];
                    }
                }
            }
        }

        // 5. Material Shortage Validation
        if ($plan->requirements()->exists()) {
            foreach ($plan->requirements as $req) {
                if ($req->shortage_quantity > 0) {
                    $warnings[] = [
                        'type' => 'material_shortage',
                        'severity' => 'warning',
                        'message' => "Material shortage for [{$req->product->sku} - {$req->product->name}]: Shortage of " . number_format($req->shortage_quantity, 2) . " " . ($req->uom ? $req->uom->code : 'PCS') . ".",
                    ];
                }
            }
        }

        return $warnings;
    }
}
