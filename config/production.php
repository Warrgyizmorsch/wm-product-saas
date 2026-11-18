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
    | Production REST API Secret Key
    |--------------------------------------------------------------------------
    | Mandatory security gate for /api/v1/production/* endpoints.
    | Header: X-API-SECRET
    */
    'api_secret' => env('PRODUCTION_API_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Temp "no-BOM" production flow (branch-only prototype)
    |--------------------------------------------------------------------------
    | When true: work center / section / machine / cost figures are optional on routings, orders
    | need no BOM and create no material requirements, scheduling accepts operations without a
    | work center, and shop floor logs record the raw materials consumed (stock is deducted).
    | Set PRODUCTION_TEMP_FLOW=true in .env on the prototype branch.
    */
    'temp_flow' => (bool) env('PRODUCTION_TEMP_FLOW', false),

    /** Folder of the per-tenant consumption.json sidecar (default storage/app/temp-production). */
    'temp_consumption_path' => env('PRODUCTION_TEMP_CONSUMPTION_PATH'),

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

    /*
    |--------------------------------------------------------------------------
    | Maintenance Hourly Rate Working Hours Basis
    |--------------------------------------------------------------------------
    | Standard monthly working hours basis used to derive an employee mechanic's
    | hourly wage from their monthly basic salary.
    | Default: 208 hours (26 working days * 8 hours/day).
    */
    'maintenance_working_hours_per_month' => (float) env('PRODUCTION_MAINTENANCE_WORKING_HOURS_PER_MONTH', 208.0),

];
