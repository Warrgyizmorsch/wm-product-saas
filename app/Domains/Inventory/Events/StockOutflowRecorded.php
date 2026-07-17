<?php

namespace App\Domains\Inventory\Events;

use App\Domains\Inventory\Models\StockTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockOutflowRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly StockTransaction $transaction)
    {
    }
}
