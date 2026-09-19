<?php

namespace App\Domains\CRM\Listeners;

use App\Core\Tenant\Events\TenantProvisioning;
use App\Domains\CRM\Models\DealStatus;
use App\Domains\CRM\Models\LeadStatus;

class ProvisionCrmDefaults
{
    /** name => [color, protected] */
    private const LEAD_STATUSES = [
        'New' => ['bg-primary', true],
        'Qualified' => ['bg-teal', true],
        'Dealing' => ['bg-info', true],
        'Won' => ['bg-success', true],
        'Lost' => ['bg-danger', true],
    ];

    /** name => [color, probability, protected] */
    private const DEAL_STATUSES = [
        'Qualification' => ['bg-primary', 10, false],
        'Needs Analysis' => ['bg-info', 30, false],
        'Proposal' => ['bg-warning', 60, false],
        'Negotiation' => ['bg-dark', 80, false],
        'Won' => ['bg-success', 100, true],
        'Lost' => ['bg-danger', 0, true],
    ];

    public function handle(TenantProvisioning $event): void
    {
        if (! $event->includes('crm')) {
            return;
        }

        $order = 0;
        foreach (self::LEAD_STATUSES as $name => [$color, $protected]) {
            LeadStatus::query()->firstOrCreate(
                ['tenant_id' => $event->tenantId, 'name' => $name],
                [
                    'company_id' => $event->companyId,
                    'branch_id' => $event->branchId,
                    'sort_order' => ++$order,
                    'color' => $color,
                    'is_protected' => $protected,
                    'is_active' => true,
                ],
            );
        }

        $order = 0;
        foreach (self::DEAL_STATUSES as $name => [$color, $probability, $protected]) {
            DealStatus::query()->firstOrCreate(
                ['tenant_id' => $event->tenantId, 'name' => $name],
                [
                    'company_id' => $event->companyId,
                    'branch_id' => $event->branchId,
                    'sort_order' => ++$order,
                    'color' => $color,
                    'probability' => $probability,
                    'is_protected' => $protected,
                    'is_active' => true,
                ],
            );
        }
    }
}
