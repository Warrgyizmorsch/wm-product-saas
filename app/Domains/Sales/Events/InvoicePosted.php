<?php

namespace App\Domains\Sales\Events;

use App\Domains\Sales\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Invoice $invoice)
    {
    }
}
