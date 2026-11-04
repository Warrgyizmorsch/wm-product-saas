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
    protected $signature = 'attendance:prune-location-logs {--days=35 : Cutoff in days for deleting closed payroll/past shift logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely purge raw periodic location breadcrumbs for closed payroll periods/completed shifts older than N days.';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceRouteService $routeService): int
    {
        $daysOption = $this->option('days');
        $days = ($daysOption !== null && $daysOption !== '') ? (int) $daysOption : 35;
        if ($days < 1) {
            $days = 35;
        }

        $this->info("Pruning raw location logs older than {$days} days (post-payroll retention window)...");

        $deletedCount = $routeService->pruneOldLogs($days);

        $this->info("Successfully pruned {$deletedCount} stale location log records while preserving Last Known Locations & polylines.");

        return Command::SUCCESS;
    }
}
