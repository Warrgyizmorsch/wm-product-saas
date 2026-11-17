<?php

namespace App\Console\Commands;

use App\Domains\Production\Models\WorkCenterActivityLog;
use Illuminate\Console\Command;

class PruneWorkCenterActivityLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'production:prune-activity-logs
                            {--days=180 : Number of days of activity logs to retain}
                            {--tenant= : Optional tenant ID to restrict pruning to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old Work Center activity logs older than specified retention days (default 180 days)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $days = 180;
        }

        $cutoff = now()->subDays($days);
        $this->info("Pruning Work Center activity logs older than {$days} days (prior to {$cutoff->toDateTimeString()})...");

        $query = WorkCenterActivityLog::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff);

        if ($tenantId = $this->option('tenant')) {
            $query->where('tenant_id', (int) $tenantId);
            $this->line("Filtering by tenant ID: {$tenantId}");
        }

        $deleted = $query->delete();

        $this->info("Successfully pruned {$deleted} old Work Center activity log(s).");

        return self::SUCCESS;
    }
}
