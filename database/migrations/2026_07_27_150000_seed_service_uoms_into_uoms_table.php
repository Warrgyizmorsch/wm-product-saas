<?php

use Illuminate\Database\Migrations\Migration;
use App\Domains\Inventory\Models\Uom;
use App\Models\Tenant;

return new class extends Migration
{
    public function up(): void
    {
        $serviceUnits = [
            ['name' => 'Hours', 'code' => 'HRS'],
            ['name' => 'Days', 'code' => 'DAYS'],
            ['name' => 'Visits', 'code' => 'VST'],
            ['name' => 'Jobs', 'code' => 'JOB'],
            ['name' => 'Sessions', 'code' => 'SES'],
            ['name' => 'Service Units', 'code' => 'UNTS'],
            ['name' => 'Months', 'code' => 'MTH'],
            ['name' => 'Years', 'code' => 'YRS'],
        ];

        $tenantIds = Tenant::pluck('id')->toArray();
        if (empty($tenantIds)) {
            if (Tenant::where('id', 1)->exists()) {
                $tenantIds = [1];
            } else {
                return;
            }
        }

        foreach ($tenantIds as $tenantId) {
            foreach ($serviceUnits as $unit) {
                Uom::withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $tenantId, 'code' => $unit['code']],
                    ['name' => $unit['name']]
                );
            }
        }
    }

    public function down(): void
    {
        // No-op
    }
};
