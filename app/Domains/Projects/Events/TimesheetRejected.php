<?php

namespace App\Domains\Projects\Events;

use App\Domains\Projects\Models\TimeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class TimesheetRejected
{
    use Dispatchable, SerializesModels;

    public readonly Collection|EloquentCollection $timeLogs;

    public function __construct(
        Collection|EloquentCollection|TimeLog $timeLogs,
        public readonly User $actor,
        public readonly ?string $rejectionRemarks = null
    ) {
        $this->timeLogs = $timeLogs instanceof TimeLog ? collect([$timeLogs]) : $timeLogs;
    }
}
