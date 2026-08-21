<?php

namespace App\Domains\Purchase\Events;

use App\Domains\Purchase\Models\VendorPayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VendorPaymentRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly VendorPayment $payment)
    {
    }
}
