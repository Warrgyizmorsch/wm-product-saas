<?php

namespace Database\Seeders;

use App\Domains\CRM\Models\DealStatus;
use App\Domains\CRM\Models\LeadStatus;
use Illuminate\Database\Seeder;

class CrmStatusMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds common default Lead and Deal statuses for the whole system (tenant_id = null).
     */
    public function run(): void
    {
        LeadStatus::seedSystemDefaults();
        DealStatus::seedSystemDefaults();
    }
}
