<?php

namespace App\Console\Commands;

use App\Domains\Production\Models\ProductionScheduleOperation;
use Illuminate\Console\Command;

class RepairScheduleWarnings extends Command
{
    protected $signature = 'production:repair-warnings';

    protected $description = 'Clean up and aggregate duplicate HOLIDAY_SKIPPED warning entries in existing production schedule operations';

    public function handle(): int
    {
        $this->info('Starting warning repairs on existing schedule operations...');

        $operations = ProductionScheduleOperation::withoutGlobalScopes()
            ->whereNotNull('warnings')
            ->get();

        $count = 0;
        foreach ($operations as $op) {
            $oldWarnings = $op->warnings;
            if (empty($oldWarnings)) {
                continue;
            }

            // This will trigger the Model saving event, which aggregates and deduplicates warnings.
            $op->save();

            $newWarnings = $op->warnings;
            if ($oldWarnings !== $newWarnings) {
                $count++;
            }
        }

        $this->info("Successfully repaired warnings on {$count} schedule operations.");

        return self::SUCCESS;
    }
}
