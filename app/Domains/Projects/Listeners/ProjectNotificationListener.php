<?php

namespace App\Domains\Projects\Listeners;

use App\Domains\Projects\Events\ChangeRequestCreated;
use App\Domains\Projects\Events\IssueLogged;
use App\Domains\Projects\Events\IssueResolved;
use App\Domains\Projects\Events\IssueRetested;
use App\Domains\Projects\Events\ProjectClosed;
use App\Domains\Projects\Events\ProjectReviewRequested;
use App\Domains\Projects\Events\ProjectReviewSignedOff;
use App\Domains\Projects\Events\TaskAssigned;
use App\Domains\Projects\Events\TaskCompleted;
use App\Domains\Projects\Events\TimesheetApproved;
use App\Domains\Projects\Events\TimesheetRejected;
use App\Domains\Projects\Events\TimesheetSubmitted;
use App\Domains\Projects\Services\ProjectNotificationService;

class ProjectNotificationListener
{
    public function __construct(
        private readonly ProjectNotificationService $notifications
    ) {
    }

    public function handle(object $event): void
    {
        match ($event::class) {
            TaskAssigned::class           => $this->notifications->handleTaskAssigned($event),
            TaskCompleted::class          => $this->notifications->handleTaskCompleted($event),
            IssueLogged::class            => $this->notifications->handleIssueLogged($event),
            IssueResolved::class          => $this->notifications->handleIssueResolved($event),
            IssueRetested::class          => $this->notifications->handleIssueRetested($event),
            TimesheetSubmitted::class     => $this->notifications->handleTimesheetSubmitted($event),
            TimesheetApproved::class      => $this->notifications->handleTimesheetApproved($event),
            TimesheetRejected::class      => $this->notifications->handleTimesheetRejected($event),
            ProjectReviewRequested::class => $this->notifications->handleProjectReviewRequested($event),
            ProjectReviewSignedOff::class => $this->notifications->handleProjectReviewSignedOff($event),
            ChangeRequestCreated::class   => $this->notifications->handleChangeRequestCreated($event),
            ProjectClosed::class          => $this->notifications->handleProjectClosed($event),
            default                       => null,
        };
    }
}
