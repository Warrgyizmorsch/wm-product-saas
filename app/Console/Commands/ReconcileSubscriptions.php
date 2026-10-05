<?php

namespace App\Console\Commands;

use App\Domains\Platform\Services\TenantSubscriptionService;
use Illuminate\Console\Command;

class ReconcileSubscriptions extends Command
{
    protected $signature = 'billing:reconcile-subscriptions';

    protected $description = 'Catch up on missed payment-gateway webhooks, then lock tenants to billing whose grace period or cancelled subscription has run out. Data is never deleted.';

    public function handle(TenantSubscriptionService $subscriptions): int
    {
        $result = $subscriptions->reconcile();

        $this->info("Synced {$result['synced']} subscription(s) from the gateway; suspended {$result['suspended']} tenant(s).");

        return self::SUCCESS;
    }
}
