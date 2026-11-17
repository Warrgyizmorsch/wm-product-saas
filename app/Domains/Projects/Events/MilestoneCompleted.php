<?php

namespace App\Domains\Projects\Events;

use App\Domains\Projects\Models\Milestone;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MilestoneCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Milestone $milestone,
        public readonly User $actor
    ) {
    }
}

