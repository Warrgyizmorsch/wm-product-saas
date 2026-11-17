<?php

namespace App\Console\Commands;

use App\Domains\Production\Services\BomLifecycleService;
use Illuminate\Console\Command;

/**
 * BM-IMPL-02: Daily date transitions for BOMs — scheduled becomes approved on effective_date,
 * approved whose expiry_date has passed becomes inactive.
 */
class ProcessBomEffectiveDates extends Command
{
    protected $signature = 'production:process-bom-dates {--date= : Override today (YYYY-MM-DD)}';

    protected $description = 'Activate scheduled BOMs whose start date has arrived and expire BOMs past their end date.';

    public function handle(BomLifecycleService $lifecycle): int
    {
        $counts = $lifecycle->processEffectiveDates($this->option('date') ?: null);
        $this->info("BOMs activated: {$counts['activated']}, expired: {$counts['expired']}");

        return self::SUCCESS;
    }
}

