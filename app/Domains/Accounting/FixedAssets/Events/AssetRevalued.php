<?php

namespace App\Domains\Accounting\FixedAssets\Events;

use App\Domains\Accounting\FixedAssets\Models\AssetRevaluation;
use Illuminate\Foundation\Events\Dispatchable;

class AssetRevalued
{
    use Dispatchable;

    public function __construct(public readonly AssetRevaluation $revaluation)
    {
    }
}
