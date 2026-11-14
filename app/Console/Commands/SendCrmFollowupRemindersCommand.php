<?php

namespace App\Console\Commands;

use App\Domains\CRM\Services\CrmFollowupReminderService;
use Illuminate\Console\Command;

class SendCrmFollowupRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'crm:send-followup-reminders {--tenant= : Filter by Tenant ID}';

    /**
     * The console command description.
     */
    protected $description = 'Dispatch due reminders for CRM Lead and Deal follow-ups and meetings';

    /**
     * Execute the console command.
     */
    public function handle(CrmFollowupReminderService $service): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;

        $this->info("Scanning for due CRM follow-ups and meetings...");
        $dispatched = $service->dispatchDueReminders(tenantId: $tenantId);

        $this->info("Dispatched {$dispatched} CRM follow-up reminder(s).");

        return self::SUCCESS;
    }
}

