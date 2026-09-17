<?php

namespace App\Console\Commands;

use App\Domains\Accounting\Services\ExchangeRates\ExchangeRateSyncService;
use Illuminate\Console\Command;

class SyncExchangeRates extends Command
{
    protected $signature = 'accounting:sync-exchange-rates
                            {--tenant= : Sync only this tenant id (runs even if its auto-sync is off)}';

    protected $description = 'Fetch the latest exchange rates for tenants with auto-sync enabled';

    public function handle(ExchangeRateSyncService $sync): int
    {
        $tenantId = $this->option('tenant');

        $results = $tenantId !== null
            ? [(int) $tenantId => $sync->syncTenant((int) $tenantId)]
            : $sync->syncEnabledTenants();

        if ($results === []) {
            $this->info('No tenants have exchange-rate auto-sync enabled.');

            return self::SUCCESS;
        }

        foreach ($results as $id => $result) {
            $line = "Tenant {$id} [{$result['status']}] {$result['message']}";
            in_array($result['status'], ['failed'], true) ? $this->error($line) : $this->line($line);
        }

        return self::SUCCESS;
    }
}
