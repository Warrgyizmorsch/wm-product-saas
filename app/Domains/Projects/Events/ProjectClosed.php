<?php

namespace App\Domains\Projects\Events;

use App\Domains\Projects\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectClosed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Project $project,
        public readonly User $actor
    ) {
    }
}
