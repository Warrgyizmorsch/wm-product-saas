<?php

namespace App\Domains\Sales\Events;

use App\Domains\Sales\Models\SalesReturn;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SalesReturnApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly SalesReturn $salesReturn)
    {
    }
}
