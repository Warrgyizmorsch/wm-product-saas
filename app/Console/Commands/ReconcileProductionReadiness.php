<?php

namespace App\Console\Commands;

use App\Domains\Production\Services\ProductionReadinessRefresher;
use Illuminate\Console\Command;

/**
 * PX-10: scheduled sweep that refreshes operation readiness of every released / in-progress order.
 * Event hooks (release, material issue / return, machine status, downtime, progress) do the immediate work;
 * this catches anything that changes without an event (for example a downtime that starts or ends by the clock).
 */
class ReconcileProductionReadiness extends Command
{
    protected $signature = 'production:reconcile-readiness {--tenant= : Only this tenant id}';

    protected $description = 'Refresh operation readiness of all released and in-progress production orders';

    public function handle(ProductionReadinessRefresher $refresher): int
    {
        $count = $refresher->sweep($this->option('tenant') ? (int) $this->option('tenant') : null);
        $this->info("Orders refreshed: {$count}.");

        return self::SUCCESS;
    }
}
