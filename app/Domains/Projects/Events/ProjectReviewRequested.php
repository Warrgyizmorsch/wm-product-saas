<?php

namespace App\Domains\Projects\Events;

use App\Domains\Projects\Models\ProjectReview;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectReviewRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ProjectReview $review,
        public readonly User $actor
    ) {
    }
}
