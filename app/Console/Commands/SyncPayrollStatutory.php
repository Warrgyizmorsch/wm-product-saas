<?php

namespace App\Console\Commands;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Services\PayrollAccountingService;
use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Models\PayrollStatutoryLine;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SyncPayrollStatutory extends Command
{
    protected $signature = 'payroll:sync-statutory
        {tenant?* : Slug(s) of the tenants to sync}
        {--all : Sync every tenant}';

    protected $description = 'Keep PF / ESI return lines (ECR, ESIC) for payroll runs posted before they were recorded. Safe to re-run.';

    public function handle(PayrollAccountingService $payroll): int
    {
        $slugs = $this->argument('tenant');

        if (! $this->option('all') && $slugs === []) {
            $this->error('Pass one or more tenant slugs, or --all.');

            return self::FAILURE;
        }

        $tenants = Tenant::query()
            ->when(! $this->option('all'), fn ($query) => $query->whereIn('slug', $slugs))
            ->orderBy('id')->get();

        foreach ($tenants as $tenant) {
            app(TenantContext::class)->set($tenant);

            $done = PayrollStatutoryLine::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->pluck('payroll_run_id')->unique()->all();

            $runs = PayrollRun::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->whereNotIn('id', $done)->orderBy('payroll_month')->get();

            foreach ($runs as $run) {
                if ($payroll->backfillStatutoryLines($run)) {
                    $this->info("[{$tenant->slug}] Payroll {$run->payroll_month} (run #{$run->id}): recorded.");
                } else {
                    $this->warn("[{$tenant->slug}] Payroll {$run->payroll_month} (run #{$run->id}): skipped - not posted, or PF/ESI recalculated today no longer matches what was posted.");
                }
            }
        }

        return self::SUCCESS;
    }
}
