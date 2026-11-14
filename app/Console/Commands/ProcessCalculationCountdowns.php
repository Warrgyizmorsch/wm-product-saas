<?php

namespace App\Console\Commands;

use App\Domains\Production\Services\CountdownCalculationEngine;
use Illuminate\Console\Command;

class ProcessCalculationCountdowns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'production:process-calculation-countdowns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process expired Work Center calculation countdowns and execute pending section/WC recalculations';

    /**
     * Execute the console command.
     */
    public function handle(CountdownCalculationEngine $engine): int
    {
        $this->info('Scanning for expired Work Center calculation countdowns...');

        $processed = $engine->processExpiredCountdowns();

        $this->info("Successfully processed {$processed} expired calculation countdown(s).");

        return self::SUCCESS;
    }
}
