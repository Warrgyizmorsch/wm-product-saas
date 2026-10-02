<?php

namespace App\Domains\Projects\Events;

use App\Domains\Projects\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IssueLogged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Issue $issue,
        public readonly User $actor
    ) {
    }
}
