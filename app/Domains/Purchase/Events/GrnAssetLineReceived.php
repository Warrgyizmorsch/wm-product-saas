<?php

namespace App\Domains\Purchase\Events;

use App\Domains\Purchase\Models\GoodsReceiptNoteItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GrnAssetLineReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly GoodsReceiptNoteItem $item)
    {
    }
}
