<?php

namespace App\Domains\Accounting\FixedAssets\Events;

use App\Domains\HRMS\Models\Asset;
use Illuminate\Foundation\Events\Dispatchable;

class AssetCapitalized
{
    use Dispatchable;

    public function __construct(public readonly Asset $asset)
    {
    }
}
