<?php

/**
 * Production Module Configuration
 *
 * Permission checks go through HasProductionPermissions::hasProductionPermission(),
 * which delegates to AccessService — permissions are seeded as real Permission/
 * RolePermission rows via RbacSeeder, not read from this file.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Routing Number Format
    |--------------------------------------------------------------------------
    | Q1: Format for auto-generated routing numbers.
    | Pattern: RTG-{YEAR}-{SEQUENCE padded to 6 digits}
    | Example: RTG-2026-000001
    */
    'routing_number_prefix' => 'RTG',

    /*
    |--------------------------------------------------------------------------
    | Work Center Type Suggestions
    |--------------------------------------------------------------------------
    | A4: Suggested values for work_center_type field.
    | These are hints only — the field is a free VARCHAR, not an enum.
    | Tenants may use their own type labels.
    */
    'work_center_types' => [
        'machining'   => 'Machining',
        'assembly'    => 'Assembly',
        'painting'    => 'Painting',
        'packaging'   => 'Packaging',
        'inspection'  => 'Inspection / QC',
        'outsourced'  => 'Outsourced / Subcontract',
        'warehouse'   => 'Warehouse / Storage',
        'transport'   => 'Internal Transport',
        'maintenance' => 'Maintenance Bay',
    ],

    /*
    |--------------------------------------------------------------------------
    | Operation Types
    |--------------------------------------------------------------------------
    | Used for validation and UI dropdown in routing operations.
    */
    'operation_types' => [
        'manufacturing' => 'Manufacturing',
        'inspection'    => 'Inspection / Quality',
        'outsourcing'   => 'Outsourcing / Subcontracting',
        'transport'     => 'Transport / Material Handling',
        'maintenance'   => 'Maintenance / Servicing',
    ],

    /*
    |--------------------------------------------------------------------------
    | Machine Statuses
    |--------------------------------------------------------------------------
    | Q3: Defined machine status values.
    */
    'machine_statuses' => [
        'active'            => 'Active',
        'inactive'          => 'Inactive',
        'under_maintenance' => 'Under Maintenance',
        'decommissioned'    => 'Decommissioned',
    ],

];
