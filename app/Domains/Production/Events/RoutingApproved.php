<?php

namespace App\Domains\Production\Events;

use App\Domains\Production\Models\Routing;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoutingApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Routing $routing) {}
}
