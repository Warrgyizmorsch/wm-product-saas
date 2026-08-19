<?php

namespace App\Domains\Production\Listeners;

use App\Domains\Purchase\Events\GoodsReceiptNoteApproved;
use App\Domains\Production\Services\SubcontractReceiptOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessSubcontractReceiptOnGrnApproved
{
    public function __construct(
        protected SubcontractReceiptOrchestrator $receiptOrchestrator
    ) {}

    public function handle(GoodsReceiptNoteApproved $event): void
    {
        $grn = $event->grn;
        if (!$grn) {
            return;
        }

        // Delegate to Production SubcontractReceiptOrchestrator
        $this->receiptOrchestrator->processSubcontractReceipt($grn);
    }
}
