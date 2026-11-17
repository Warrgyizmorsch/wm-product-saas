<?php

namespace App\Console\Commands;

use App\Domains\Production\Services\RoutingLifecycleService;
use Illuminate\Console\Command;

/**
 * RT-IMPL-04 (RT-03 rule 5): daily routing status by dates, for every tenant —
 * scheduled routings whose start date has arrived become active; active / scheduled routings whose end date
 * has passed become historical (with the same BOM handling as Retire — RT-05).
 */
class ProcessRoutingEffectiveDates extends Command
{
    protected $signature = 'production:process-routing-dates';

    protected $description = 'Activate scheduled routings on their start date and make expired routings historical';

    public function handle(RoutingLifecycleService $lifecycle): int
    {
        $result = $lifecycle->processEffectiveDates();

        $this->info("Routings activated: {$result['activated']}; made historical (end date passed): {$result['expired']}.");

        return self::SUCCESS;
    }
}
