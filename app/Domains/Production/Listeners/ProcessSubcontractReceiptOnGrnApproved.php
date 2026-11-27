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

        // SC-03: a system GRN is the PO/bill record of a receipt that SubcontractReceiptService has already booked on the
        // production side — running the production receipt again would count the work twice.
        if ($grn->source_type === \App\Domains\Purchase\Services\GoodsReceiptNoteService::SOURCE_SUBCONTRACT_RECEIPT) {
            return;
        }

        // Delegate to Production SubcontractReceiptOrchestrator
        $this->receiptOrchestrator->processSubcontractReceipt($grn);
    }
}
