<?php

namespace App\Domains\Purchase\Events;

use App\Domains\Purchase\Models\GoodsReceiptNote;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GoodsReceiptNoteApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly GoodsReceiptNote $grn)
    {
    }
}
