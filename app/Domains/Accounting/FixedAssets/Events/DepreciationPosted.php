<?php

namespace App\Domains\Accounting\FixedAssets\Events;

use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use Illuminate\Foundation\Events\Dispatchable;

class DepreciationPosted
{
    use Dispatchable;

    public function __construct(public readonly AssetDepreciationSchedule $schedule)
    {
    }
}
