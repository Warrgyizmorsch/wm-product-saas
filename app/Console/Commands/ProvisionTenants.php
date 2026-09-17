<?php

namespace App\Console\Commands;

use App\Core\Tenant\TenantProvisioner;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ProvisionTenants extends Command
{
    protected $signature = 'tenant:provision
        {tenant?* : Slug(s) of the tenants to set up}
        {--all : Set up every tenant}';

    protected $description = 'Add the default master data every tenant needs (company, branch, CRM statuses, units, warehouse, payment terms, shifts, chart of accounts). Safe to re-run: only missing rows are added.';

    public function handle(TenantProvisioner $provisioner): int
    {
        $slugs = $this->argument('tenant');

        if (! $this->option('all') && $slugs === []) {
            $this->error('Pass one or more tenant slugs, or --all.');

            return self::FAILURE;
        }

        $tenants = Tenant::query()
            ->when(! $this->option('all'), fn ($query) => $query->whereIn('slug', $slugs))
            ->orderBy('id')
            ->get();

        $missing = array_diff($slugs, $tenants->pluck('slug')->all());

        if (! $this->option('all') && $missing !== []) {
            $this->error('No tenant found with slug: '.implode(', ', $missing));

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $provisioner->provision($tenant);
            $this->info("Provisioned [{$tenant->slug}].");
        }

        return self::SUCCESS;
    }
}
