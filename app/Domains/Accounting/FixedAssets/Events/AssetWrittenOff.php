<?php

namespace App\Domains\Accounting\FixedAssets\Events;

use App\Domains\Accounting\FixedAssets\Models\AssetWriteOff;
use Illuminate\Foundation\Events\Dispatchable;

class AssetWrittenOff
{
    use Dispatchable;

    public function __construct(public readonly AssetWriteOff $writeOff)
    {
    }
}
