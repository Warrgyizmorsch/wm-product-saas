<?php

/**
 * Production Module Configuration
 *
 * A5: Permission-to-role mapping.
 * No role strings are hardcoded in policies. All checks go through
 * HasProductionPermissions::hasProductionPermission() which reads from here.
 *
 * To add a new role: add it to the array for relevant permissions.
 * To add a new permission: add a new key with allowed roles.
 * Future RBAC: replace this static map with a DB-backed query inside the trait.
 */
return [

    'permissions' => [

        // Work Center & Machine master data management
        'production.work_center.manage' => [
            'admin',
            'production_manager',
            'production_engineer',
        ],

        'production.machine.manage' => [
            'admin',
            'production_manager',
            'production_engineer',
        ],

        // Routing lifecycle permissions
        'production.routing.create' => [
            'admin',
            'production_manager',
            'production_engineer',
        ],

        'production.routing.update' => [
            'admin',
            'production_manager',
            'production_engineer',
        ],

        // Only managers can approve — Q4 decision
        'production.routing.approve' => [
            'admin',
            'production_manager',
        ],

        'production.routing.cancel' => [
            'admin',
            'production_manager',
        ],

    ],

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
