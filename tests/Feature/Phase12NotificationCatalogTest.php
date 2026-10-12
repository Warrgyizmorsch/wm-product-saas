<?php

namespace Tests\Feature;

use App\Domains\Platform\Services\NotificationEventCatalog;
use Tests\TestCase;

class Phase12NotificationCatalogTest extends TestCase
{
    public function test_projects_module_is_registered_in_notification_event_catalog(): void
    {
        $events = NotificationEventCatalog::getEvents();

        $this->assertArrayHasKey('projects', $events);
        $this->assertEquals('Project Management', $events['projects']['label']);

        $projectEvents = $events['projects']['events'];

        // Canonical Phase 12 lifecycle events
        $expectedEvents = [
            'projects.project.created',
            'projects.project.status_changed',
            'projects.project.completed',
            'projects.milestone.created',
            'projects.milestone.completed',
            'projects.task.created',
            'projects.task.assigned',
            'projects.task.completed',
            'projects.issue.logged',
            'projects.issue.resolved',
            'projects.issue.retested',
            'projects.timesheet.submitted',
            'projects.timesheet.approved',
            'projects.timesheet.rejected',
            'projects.review.requested',
            'projects.review.signed_off',
            'projects.change_request.created',
            'projects.change_request.approved',
            'projects.project.closed',
        ];

        foreach ($expectedEvents as $eventKey) {
            $this->assertArrayHasKey($eventKey, $projectEvents, "Event {$eventKey} is missing from NotificationEventCatalog.");

            $eventDetails = NotificationEventCatalog::getEventDetails($eventKey);
            $this->assertNotNull($eventDetails);
            $this->assertEquals('projects', $eventDetails['module']);
            $this->assertNotEmpty($eventDetails['label']);
            $this->assertNotEmpty($eventDetails['default_title']);
            $this->assertNotEmpty($eventDetails['default_body']);
        }
    }
}
