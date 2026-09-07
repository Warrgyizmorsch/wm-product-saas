<?php

namespace App\Console\Commands;

use App\Domains\Accounting\FixedAssets\Services\AssetDepreciationService;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Bulk-generates DRAFT depreciation schedules for a period. Deliberately
 * never posts — review/approve/post always require an explicit human action
 * (asset-mng.md §14/§32), so this command only covers the "generate" step of
 * the generate -> review -> approve -> post pipeline.
 */
class GenerateAssetDepreciation extends Command
{
    protected $signature = 'assets:generate-depreciation
                            {--tenant= : Specific tenant ID (defaults to all tenants)}
                            {--year= : Period year (defaults to current year)}
                            {--month= : Period month 1-12 (defaults to current month)}';

    protected $description = 'Generate draft depreciation schedules for active fixed assets for a given month';

    public function handle(AssetDepreciationService $depreciation): int
    {
        $year = (int) ($this->option('year') ?: now()->year);
        $month = (int) ($this->option('month') ?: now()->month);

        if ($month < 1 || $month > 12) {
            $this->error("Invalid month: {$month}");
            return self::FAILURE;
        }

        $tenantId = $this->option('tenant');
        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No matching tenants found.');
            return self::SUCCESS;
        }

        $totalGenerated = 0;

        foreach ($tenants as $tenant) {
            $generated = $depreciation->generateForPeriod($tenant->id, $year, $month);
            $totalGenerated += $generated->count();

            $this->info("Tenant #{$tenant->id} ({$tenant->name}): generated {$generated->count()} draft schedule(s) for {$year}-{$month}.");
        }

        $this->info("Done. Total draft schedules generated: {$totalGenerated}.");

        return self::SUCCESS;
    }
}
