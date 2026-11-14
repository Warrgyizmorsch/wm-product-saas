<?php

namespace App\Console\Commands;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\TdsDeduction;
use App\Domains\Accounting\Services\PayrollAccountingService;
use App\Domains\HRMS\Models\PayrollRun;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SyncPayrollTds extends Command
{
    protected $signature = 'tds:sync-payroll
        {tenant?* : Slug(s) of the tenants to sync}
        {--all : Sync every tenant}';

    protected $description = 'Put salary TDS (section 192) from payroll runs posted before the TDS register existed into the register, for 24Q and Form 16. Safe to re-run.';

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

            $done = TdsDeduction::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->where('source_type', TdsDeduction::SOURCE_PAYROLL_RUN)->pluck('source_id')->unique()->all();

            $runs = PayrollRun::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->whereNotIn('id', $done)->orderBy('payroll_month')->get();

            foreach ($runs as $run) {
                if ($payroll->backfillSalaryTds($run)) {
                    $this->info("[{$tenant->slug}] Payroll {$run->payroll_month} (run #{$run->id}): recorded.");
                } else {
                    $this->warn("[{$tenant->slug}] Payroll {$run->payroll_month} (run #{$run->id}): skipped - not posted, or salary TDS recalculated today no longer matches what was posted.");
                }
            }
        }

        return self::SUCCESS;
    }
}
