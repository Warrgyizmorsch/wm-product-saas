<?php

namespace App\Domains\Platform\Listeners;

use App\Core\Tenant\Events\TenantProvisioning;
use App\Domains\Platform\Models\PaymentTerm;

class ProvisionPaymentTerms
{
    /** code => [name, due days, description] */
    private const TERMS = [
        'DUE_RECEIPT' => ['Immediate / Due on Receipt', 0, 'Payment due immediately upon receipt of invoice/bill.'],
        'NET15' => ['Net 15 Days', 15, 'Payment due within 15 calendar days.'],
        'NET30' => ['Net 30 Days', 30, 'Standard Net 30 days payment term.'],
        'NET45' => ['Net 45 Days', 45, 'Payment due within 45 calendar days.'],
        'NET60' => ['Net 60 Days', 60, 'Payment due within 60 calendar days.'],
        'ADV50_DEL50' => ['50% Advance, 50% Delivery', 0, '50% payment in advance and 50% upon delivery.'],
    ];

    public function handle(TenantProvisioning $event): void
    {
        if (! $event->includes('sales', 'purchase')) {
            return;
        }

        foreach (self::TERMS as $code => [$name, $dueDays, $description]) {
            PaymentTerm::query()->firstOrCreate(
                ['tenant_id' => $event->tenantId, 'code' => $code],
                [
                    'company_id' => $event->companyId,
                    'branch_id' => $event->branchId,
                    'name' => $name,
                    'due_days' => $dueDays,
                    'discount_days' => 0,
                    'discount_percentage' => 0,
                    'description' => $description,
                    'is_active' => true,
                ],
            );
        }
    }
}
