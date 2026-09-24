<?php

namespace Database\Seeders;

use App\Domains\Platform\Models\NotificationRule;
use App\Domains\Platform\Services\NotificationEventCatalog;
use Illuminate\Database\Seeder;

class NotificationRulesSeeder extends Seeder
{
    /**
     * Seed default notification rules for common ERP events.
     */
    public function run(): void
    {
        $tenantId = 1;
        $catalog = NotificationEventCatalog::getEvents();

        $defaultEventsToSeed = [
            'inventory.stock.low',
            'sales.order.confirmed',
            'sales.payment.received',
            'purchase.grn.received',
            'production.qc.failed',
            'hrms.leave.applied',
        ];

        foreach ($catalog as $modKey => $module) {
            foreach ($module['events'] as $eKey => $event) {
                if (in_array($eKey, $defaultEventsToSeed)) {
                    NotificationRule::updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'event_key' => $eKey,
                        ],
                        [
                            'name' => $event['label'] . ' (Default)',
                            'module' => $modKey,
                            'description' => $event['description'] ?? null,
                            'recipient_roles' => $event['default_roles'] ?? ['Admin'],
                            'recipient_user_ids' => [],
                            'notify_creator' => true,
                            'notify_assigned_user' => true,
                            'title_template' => $event['default_title'],
                            'body_template' => $event['default_body'],
                            'action_route' => $event['action_route'] ?? null,
                            'icon_class' => $event['icon'] ?? 'feather-bell',
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}
