<?php

namespace App\Console\Commands;

use App\Domains\Projects\Seeders\Context\TenantIsolationContext;
use App\Domains\Projects\Seeders\ProjectsDemoGenerator;
use Illuminate\Console\Command;

class SeedProjectsDemoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:seed-projects
        {--projects=20 : Number of projects to create}
        {--milestones=18 : Milestones per project}
        {--lists=15 : Task lists per milestone}
        {--tasks=18 : Tasks per task list}
        {--users=20 : Size of demo user pool}
        {--tenant= : Target tenant slug or ID}
        {--seed= : Integer seed for deterministic RNG generation}
        {--profile=active-dev : Distribution profile (active-dev, enterprise, maintenance, startup)}
        {--chunk-size=2000 : Database insert batch size}
        {--wipe : Wipe existing project data for target tenant before seeding}
        {--dry-run : Estimate record counts, memory, and batch size without database writes}
        {--fail-fast : Stop execution immediately upon the first project transaction error}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Production-grade high-performance demo data generator for the Projects module.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $options = $this->options();
        $tenantParam = $this->option('tenant');

        try {
            $context = TenantIsolationContext::resolve($tenantParam);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }

        $this->info("Target Tenant: [ID: {$context->tenantId}] {$context->tenantName} ({$context->tenantSlug})");

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN MODE ENABLED - NO DATABASE WRITES WILL BE PERFORMED');
        }

        if ($this->option('wipe') && !$this->option('dry-run')) {
            if (!$this->confirm("WARNING: --wipe flag passed. This will permanently delete all existing Projects module data for tenant [{$context->tenantName}]. Continue?", true)) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        $generator = new ProjectsDemoGenerator();

        if ($this->option('dry-run')) {
            $result = $generator->execute($context, $options, null);
            $this->newLine();
            $this->table(
                ['Metric', 'Estimated Value'],
                [
                    ['Target Tenant ID', $result['tenant_id']],
                    ['Target Tenant Name', $result['tenant_name']],
                    ['Target Projects', number_format($result['projects'])],
                    ['Target User Pool Size', number_format($result['user_pool'])],
                    ['Est. Project Members', number_format($result['est_members'])],
                    ['Est. Milestones', number_format($result['est_milestones'])],
                    ['Est. Task Lists', number_format($result['est_task_lists'])],
                    ['Est. Tasks', number_format($result['est_tasks'])],
                    ['Est. Sub-Tasks', number_format($result['est_subtasks'])],
                    ['Est. Task Dependencies', number_format($result['est_task_dependencies'])],
                    ['Est. Activity Logs', number_format($result['est_activity_logs'])],
                    ['Est. Total Rows', number_format($result['est_total_rows'])],
                    ['Est. Batch Writes', number_format($result['est_batches'])],
                    ['Est. Peak Memory', $result['est_memory']],
                    ['Est. Execution Time', $result['est_time']],
                ]
            );
            $this->info('Dry-run calculation completed cleanly.');
            return Command::SUCCESS;
        }

        $this->info('Starting Projects Module Demo Seeder...');
        
        $result = $generator->execute($context, $options, $this->output);

        $this->newLine();
        $this->table(
            ['Metric', 'Result Value'],
            [
                ['Tenant Name', $result['tenant_name']],
                ['Inserted Projects', number_format($result['inserted_projects'])],
                ['Inserted Members', number_format($result['inserted_members'])],
                ['Inserted Milestones', number_format($result['inserted_milestones'])],
                ['Inserted Task Lists', number_format($result['inserted_task_lists'])],
                ['Inserted Tasks', number_format($result['inserted_tasks'])],
                ['Inserted Sub-Tasks', number_format($result['inserted_sub_tasks'])],
                ['Inserted Dependencies', number_format($result['inserted_task_dependencies'])],
                ['Inserted Activity Logs', number_format($result['inserted_activity_logs'])],
                ['Total Inserted Rows', number_format($result['total_inserted_rows'])],
                ['Execution Time', "{$result['execution_time_seconds']} seconds"],
                ['Peak Memory Usage', "{$result['peak_memory_mb']} MB"],
            ]
        );

        $this->info('Projects Module Demo Seeder completed successfully!');
        return Command::SUCCESS;
    }
}
