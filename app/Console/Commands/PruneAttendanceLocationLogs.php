<?php

namespace App\Console\Commands;

use App\Domains\HRMS\Services\AttendanceRouteService;
use Illuminate\Console\Command;

class PruneAttendanceLocationLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:prune-location-logs {--hours= : Cutoff in hours for deleting completed shift logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely purge raw periodic location breadcrumbs for completed attendance shifts older than N hours.';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceRouteService $routeService): int
    {
        $hoursOption = $this->option('hours');
        $hours = ($hoursOption !== null && $hoursOption !== '') ? (int) $hoursOption : 24;
        if ($hours < 1) {
            $hours = 24;
        }

        $this->info("Pruning raw location logs older than {$hours} hours for completed shifts...");

        $deletedCount = $routeService->pruneOldLogs($hours);

        $this->info("Successfully pruned {$deletedCount} stale location log records.");

        return Command::SUCCESS;
    }
}
