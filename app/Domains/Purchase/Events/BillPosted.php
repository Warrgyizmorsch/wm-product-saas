<?php

namespace App\Domains\Purchase\Events;

use App\Domains\Purchase\Models\VendorBill;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BillPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly VendorBill $bill)
    {
    }
}
