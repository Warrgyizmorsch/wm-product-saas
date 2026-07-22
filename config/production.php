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
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        'production.machine.manage' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        // Routing lifecycle permissions
        'production.routing.create' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        'production.routing.update' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        // Only managers can approve — Q4 decision
        'production.routing.approve' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
        ],

        'production.routing.cancel' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
        ],

        'production.intelligence.view' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        'production.quality.manage' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        'production.quality.approve' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
        ],

        // BOM lifecycle permissions
        'production.bom.create' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        'production.bom.update' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        'production.bom.approve' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
        ],

        // Planning permissions
        'production.planning.create' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        'production.planning.update' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        'production.planning.approve' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
        ],

        'production.planning.cancel' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
        ],

        // Order permissions
        'production.order.create' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        'production.order.update' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        'production.order.cancel' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
        ],

        // MES & Schedule permissions
        'production.mes.execute' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        'production.schedule.manage' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        'production.cost_adjustment.create' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
        ],

        'production.cost_adjustment.update' => [
            'super_admin',
            'admin',
            'tenant_owner',
            'company_admin',
            'production_manager',
            'production_engineer',
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
