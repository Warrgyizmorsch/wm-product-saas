<?php

namespace App\Domains\Sales\Events;

use App\Domains\Sales\Models\CustomerPayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerPaymentReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly CustomerPayment $payment)
    {
    }
}
