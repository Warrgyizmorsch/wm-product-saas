<?php

namespace App\Domains\Projects\Events;

use App\Domains\Projects\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChangeRequestCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ChangeRequest $changeRequest,
        public readonly User $actor
    ) {
    }
}
