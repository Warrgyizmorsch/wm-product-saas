<?php

namespace App\Domains\Production\Listeners;

use App\Domains\Production\Events\BomApproved;
use Illuminate\Support\Facades\Log;

class SyncProductionPlanning
{
    public function handle(BomApproved $event): void
    {
        Log::info("Syncing Production Planning for approved BOM ID: {$event->bom->id}");
        // Sync planning logic goes here in the future
    }
}
