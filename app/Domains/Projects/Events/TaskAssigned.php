<?php

namespace App\Domains\Projects\Events;

use App\Domains\Projects\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Task $task,
        public readonly User $actor,
        public readonly ?int $previousAssigneeId = null
    ) {
    }
}
