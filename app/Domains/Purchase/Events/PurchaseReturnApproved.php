<?php

namespace App\Domains\Purchase\Events;

use App\Domains\Purchase\Models\PurchaseReturn;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseReturnApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly PurchaseReturn $purchaseReturn)
    {
    }
}
