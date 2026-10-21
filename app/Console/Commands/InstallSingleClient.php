<?php

namespace App\Console\Commands;

use App\Core\Tenant\TenantProvisioner;
use App\Domains\Platform\Services\TenantService;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Sets up a single-client install: the one tenant named by SINGLE_CLIENT_SLUG,
 * its starter masters and its owner login. Safe to re-run — an existing tenant
 * is only re-provisioned (missing masters added), never recreated.
 */
class InstallSingleClient extends Command
{
    protected $signature = 'client:install
        {--name= : Company name shown in the app}
        {--domain= : Domain the app is served on (optional)}
        {--owner-name= : Owner full name}
        {--owner-email= : Owner login email}
        {--owner-password= : Owner password (auto-generated if omitted)}';

    protected $description = 'Create (or re-provision) the one tenant of a single-client install, with its owner login';

    public function handle(TenantService $tenants, TenantProvisioner $provisioner): int
    {
        if (! single_client()) {
            $this->error('Set SINGLE_CLIENT_SLUG in .env first (then run php artisan config:clear).');

            return self::FAILURE;
        }

        $slug = config('tenancy.single_client.slug');
        $existing = Tenant::query()->where('slug', $slug)->first();

        if ($existing !== null) {
            $provisioner->provision($existing);
            $this->info("Tenant [{$slug}] already exists — missing starter masters were added.");

            return self::SUCCESS;
        }

        $data = [
            'name' => $this->option('name') ?: $this->ask('Company name'),
            'domain' => $this->option('domain'),
            'owner_name' => $this->option('owner-name') ?: $this->ask('Owner full name'),
            'owner_email' => $this->option('owner-email') ?: $this->ask('Owner email'),
        ];

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|unique:users,email',
        ]);

        if ($validator->fails()) {
            $this->error(implode(' ', $validator->errors()->all()));

            return self::FAILURE;
        }

        $password = $this->option('owner-password');

        if (blank($password)) {
            $password = Str::password(16);
            $this->comment("Generated owner password: {$password}");
        }

        $tenant = $tenants->create([
            ...$data,
            'slug' => $slug,
            'owner_password' => $password,
            'status' => Tenant::STATUS_ACTIVE,
            'plan' => 'single-client',
            'subscription_status' => Tenant::SUBSCRIPTION_ACTIVE,
            'timezone' => config('app.timezone', 'Asia/Kolkata'),
            'locale' => config('app.locale', 'en'),
        ]);

        $this->info("Created tenant [{$tenant->slug}] with owner [{$data['owner_email']}].");

        return self::SUCCESS;
    }
}
