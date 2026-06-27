<?php

namespace App\Domains\Production\Listeners;

use App\Domains\Production\Events\BomApproved;
use Illuminate\Support\Facades\Log;

class CalculateBomCost
{
    public function handle(BomApproved $event): void
    {
        Log::info("Calculating costs for BOM ID: {$event->bom->id} (Number: {$event->bom->bom_number})");
        // Business logic for cost roll-up goes here in the future
    }
}
