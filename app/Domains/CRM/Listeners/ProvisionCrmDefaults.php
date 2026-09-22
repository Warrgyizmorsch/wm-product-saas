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

        LeadStatus::seedSystemDefaults();
        DealStatus::seedSystemDefaults();
    }
}
