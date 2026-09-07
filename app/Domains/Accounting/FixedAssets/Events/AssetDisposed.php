<?php

namespace App\Domains\Accounting\FixedAssets\Events;

use App\Domains\Accounting\FixedAssets\Models\AssetDisposal;
use Illuminate\Foundation\Events\Dispatchable;

class AssetDisposed
{
    use Dispatchable;

    public function __construct(public readonly AssetDisposal $disposal)
    {
    }
}
