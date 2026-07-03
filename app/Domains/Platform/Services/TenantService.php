<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Repositories\TenantRepository;
use App\Models\Access\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantService
{
    public function __construct(
        private readonly TenantRepository $tenants,
    ) {
    }

    public function all(): Collection
    {
        return $this->tenants->all();
    }

    public function summary(): array
    {
        return [
            'total' => $this->tenants->count(),
            'active' => $this->tenants->activeCount(),
            'trial' => $this->tenants->trialCount(),
            'suspended' => $this->tenants->suspendedCount(),
        ];
    }

    public function create(array $data): Tenant
    {
        return DB::transaction(function () use ($data): Tenant {
            $tenant = $this->tenants->create($this->payload($data));
            $owner = $this->createOwnerUser($tenant, $data);

            if ($owner !== null) {
                $this->tenants->update($tenant, [
                    'owner_user_id' => $owner->id,
                    'onboarded_at' => now(),
                ]);

                $tenant->setRelation('owner', $owner);
                $tenant->owner_user_id = $owner->id;
                $tenant->onboarded_at = now();
            }

            return $tenant;
        });
    }

    public function update(Tenant $tenant, array $data): bool
    {
        return $this->tenants->update($tenant, $this->payload($data, $tenant));
    }

    public function updateStatus(Tenant $tenant, string $status): bool
    {
        return $this->tenants->update($tenant, ['status' => $status]);
    }

    private function payload(array $data, ?Tenant $tenant = null): array
    {
        $settings = $tenant?->settings ?? [];

        return [
            'name' => $data['name'],
            'slug' => $data['slug'] ?: Str::slug($data['name']),
            'domain' => $data['domain'] ?: null,
            'billing_email' => $data['billing_email'] ?: null,
            'status' => $data['status'],
            'plan' => $data['plan'],
            'subscription_status' => $data['subscription_status'],
            'max_users' => $data['max_users'] ?: null,
            'max_storage_mb' => $data['max_storage_mb'] ?: null,
            'trial_ends_at' => $data['trial_ends_at'] ?: null,
            'plan_started_at' => $data['plan_started_at'] ?: null,
            'plan_expires_at' => $data['plan_expires_at'] ?: null,
            'archived_at' => $data['status'] === Tenant::STATUS_ARCHIVED ? ($tenant?->archived_at ?? now()) : null,
            'timezone' => $data['timezone'],
            'locale' => $data['locale'],
            'settings' => [
                'display_name' => $data['display_name'] ?: $data['name'],
                'logo_full' => $this->storedLogoPath($data['logo_full'] ?? null, $settings['logo_full'] ?? null),
                'logo_abbr' => $this->storedLogoPath($data['logo_abbr'] ?? null, $settings['logo_abbr'] ?? null),
                'branch' => $data['branch'] ?: 'Main Office',
                'currency' => $data['currency'] ?: 'INR',
                'financial_year' => $data['financial_year'] ?: 'FY '.now()->format('Y'),
            ],
        ];
    }

    private function createOwnerUser(Tenant $tenant, array $data): ?User
    {
        if (empty($data['owner_email']) || empty($data['owner_name'])) {
            return null;
        }

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $data['owner_name'],
            'email' => $data['owner_email'],
            'password' => $data['owner_password'],
            'role' => 'tenant_owner',
        ]);

        $role = Role::query()
            ->where('slug', 'tenant_owner')
            ->whereNull('tenant_id')
            ->first();

        if ($role !== null) {
            $user->roles()->syncWithoutDetaching([
                $role->id => ['tenant_id' => $tenant->id],
            ]);

            $user->forceFill(['role_id' => $role->id])->save();
        }

        return $user;
    }

    private function storedLogoPath(mixed $file, ?string $existingPath): ?string
    {
        if ($file instanceof UploadedFile) {
            return $file->store('tenant-branding', 'public');
        }

        return $existingPath;
    }
}
