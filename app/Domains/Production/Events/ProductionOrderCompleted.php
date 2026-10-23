<?php

namespace App\Domains\Production\Events;

use App\Domains\Production\Models\ProductionOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductionOrderCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ProductionOrder $order)
    {
    }
}
