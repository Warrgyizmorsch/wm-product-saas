<?php

namespace App\Domains\Production\Events;

use App\Domains\Production\Models\ProductionBom;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BomVersionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ProductionBom $bom)
    {
    }
}
