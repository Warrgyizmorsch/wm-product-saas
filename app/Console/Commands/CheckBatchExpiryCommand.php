<?php

namespace App\Console\Commands;

use App\Domains\Inventory\Models\Batch;
use App\Domains\Inventory\Services\InventoryNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckBatchExpiryCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'inventory:check-expiring-batches {--days=30 : Number of days threshold for expiration} {--tenant= : Filter by Tenant ID}';

    /**
     * The console command description.
     */
    protected $description = 'Scan and trigger notification alerts for stock batches expiring soon (FEFO)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;

        $targetDate = Carbon::now()->addDays($days);

        $query = Batch::query()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $targetDate)
            ->where('expiry_date', '>=', Carbon::now())
            ->where('available_qty', '>', 0);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $batches = $query->with(['product', 'warehouse'])->get();

        $this->info("Found {$batches->count()} batch(es) nearing expiration within {$days} days.");

        foreach ($batches as $batch) {
            $daysLeft = (int) Carbon::now()->diffInDays(Carbon::parse($batch->expiry_date), false);
            InventoryNotificationService::notifyBatchExpiring($batch, max(0, $daysLeft));
        }

        return self::SUCCESS;
    }
}

