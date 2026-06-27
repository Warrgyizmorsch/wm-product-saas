<?php

namespace App\Domains\Production\Listeners;

use App\Domains\Production\Events\BomApproved;
use Illuminate\Support\Facades\Log;

class UpdateMrpForecast
{
    public function handle(BomApproved $event): void
    {
        Log::info("Updating MRP forecasts for approved BOM ID: {$event->bom->id}");
        // MRP logic goes here in the future
    }
}
