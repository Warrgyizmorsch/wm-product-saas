<?php

namespace App\Exports;

class ExportRegistry
{
    /**
     * Get all available column definitions (key => Label) for a given export type.
     */
    public static function getColumnsForType(string $type): array
    {
        return match ($type) {
            'boms' => BomExport::availableColumns(),
            'routings' => RoutingExport::availableColumns(),
            'work-centers' => WorkCenterExport::availableColumns(),
            'machines' => MachineExport::availableColumns(),
            'orders' => ProductionOrderExport::availableColumns(),
            'plans' => ProductionPlanExport::availableColumns(),
            'wip' => ProductionWipExport::availableColumns(),
            'schedules' => ProductionScheduleExport::availableColumns(),
            default => [],
        };
    }

    /**
     * Get human-friendly title for the modal.
     */
    public static function getTitleForType(string $type): string
    {
        return match ($type) {
            'boms' => 'Bill of Materials (BOM)',
            'routings' => 'Production Routings',
            'work-centers' => 'Work Centers',
            'machines' => 'Production Machines',
            'orders' => 'Production Orders',
            'plans' => 'Production Plans',
            'wip' => 'Work-In-Progress (WIP)',
            'schedules' => 'Production Schedules',
            default => ucfirst(str_replace('-', ' ', $type)),
        };
    }
}
