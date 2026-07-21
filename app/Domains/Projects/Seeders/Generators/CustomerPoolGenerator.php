<?php

namespace App\Domains\Projects\Seeders\Generators;

use App\Domains\CRM\Models\Customer;
use App\Domains\Projects\Seeders\Context\TenantIsolationContext;

class CustomerPoolGenerator
{
    protected static array $curatedCustomers = [
        ['name' => 'Acme Global Enterprises', 'email' => 'contact@acmeglobal.com', 'phone' => '+1-555-0192'],
        ['name' => 'Nexus Cloud Technologies', 'email' => 'info@nexuscloud.io', 'phone' => '+1-555-0143'],
        ['name' => 'Starlight Digital Media', 'email' => 'hello@starlightmedia.com', 'phone' => '+1-555-0188'],
        ['name' => 'Apex Financial Solutions', 'email' => 'support@apexfin.com', 'phone' => '+1-555-0129'],
        ['name' => 'Vanguard Logistics & Supply', 'email' => 'ops@vanguardlog.com', 'phone' => '+1-555-0165'],
        ['name' => 'Horizon Healthcare Systems', 'email' => 'admin@horizonhealth.org', 'phone' => '+1-555-0177'],
        ['name' => 'OmniCorp Manufacturing Ltd', 'email' => 'contact@omnicorp.com', 'phone' => '+1-555-0111'],
        ['name' => 'CyberPulse Security Group', 'email' => 'info@cyberpulse.sec', 'phone' => '+1-555-0154'],
        ['name' => 'Pinnacle Retail Omnichannel', 'email' => 'help@pinnacle.com', 'phone' => '+1-555-0133'],
        ['name' => 'Quantum Data Analytics', 'email' => 'data@quantum.ai', 'phone' => '+1-555-0199'],
    ];

    /**
     * Generate or fetch customer pool for target tenant.
     * Returns an array of Customer model IDs.
     *
     * @return int[]
     */
    public static function generate(TenantIsolationContext $context, int $targetCount = 10): array
    {
        $existingIds = Customer::query()
            ->where('tenant_id', $context->tenantId)
            ->pluck('id')
            ->toArray();

        if (count($existingIds) >= $targetCount) {
            return array_slice($existingIds, 0, $targetCount);
        }

        $customerIds = $existingIds;

        foreach (self::$curatedCustomers as $c) {
            if (count($customerIds) >= $targetCount) {
                break;
            }

            $customer = Customer::query()->firstOrCreate(
                [
                    'tenant_id' => $context->tenantId,
                    'name' => $c['name'],
                ],
                [
                    'email' => $c['email'],
                    'phone' => $c['phone'],
                    'status' => 'Active',
                ]
            );

            if (!in_array($customer->id, $customerIds, true)) {
                $customerIds[] = $customer->id;
            }
        }

        return $customerIds;
    }
}
