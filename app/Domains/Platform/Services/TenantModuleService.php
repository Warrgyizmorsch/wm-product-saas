<?php

namespace App\Domains\Platform\Services;

use App\Core\Tenant\TenantProvisioner;
use App\Domains\Platform\Models\SubscriptionPayment;
use App\Domains\Platform\Models\TenantModule;
use App\Http\Middleware\EnsureTenantModuleAccess;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Self-service module add-ons on top of a tenant's plan (one-time fee, see
 * TenantModuleController). Uninstalling only hides a module — its data is
 * never deleted and other modules keep posting into it — and a module that
 * was paid for once can be reinstalled for free.
 *
 * Plan modules are not managed here: they come and go with the plan.
 */
class TenantModuleService
{
    public function __construct(
        private readonly TenantProvisioner $provisioner,
    ) {
    }

    /**
     * Every gated module with its state for this tenant:
     * 'plan' (included in the plan), 'addon' (bought, installed),
     * 'uninstalled' (bought, reinstall is free) or 'available' (not bought).
     *
     * @return array<string, string>
     */
    public function states(Tenant $tenant): array
    {
        $planFeatures = $tenant->planCatalog?->features;
        $rows = $tenant->addonModules->keyBy('module');

        $states = [];
        foreach (EnsureTenantModuleAccess::GATED_MODULES as $module) {
            $states[$module] = match (true) {
                $planFeatures === null || in_array($module, $planFeatures, true) => 'plan',
                $rows->get($module)?->isActive() === true => 'addon',
                $rows->has($module) => 'uninstalled',
                default => 'available',
            };
        }

        return $states;
    }

    /** @return list<string> modules $module cannot work without */
    public function requirements(string $module): array
    {
        return config("navigation.module_requires.$module", []);
    }

    /**
     * Throws if installing $modules would leave one of them without a module it
     * requires. Modules bought in the same checkout count as present.
     *
     * @param list<string> $modules
     */
    public function assertRequirementsMet(Tenant $tenant, array $modules): void
    {
        $present = [...($tenant->planModules() ?? EnsureTenantModuleAccess::GATED_MODULES), ...$modules];

        foreach ($modules as $module) {
            $missing = array_diff($this->requirements($module), $present);

            if ($missing !== []) {
                throw new RuntimeException(sprintf(
                    '%s needs %s — install it too.',
                    $this->label($module),
                    $this->labels($missing),
                ));
            }
        }
    }

    /**
     * Grants $modules to the tenant (new rows, or reactivates uninstalled ones)
     * and fills their starter masters. Called once a module-add-on payment is
     * confirmed (SubscriptionPaymentService) and for a free reinstall.
     *
     * @param list<string> $modules
     */
    public function install(Tenant $tenant, array $modules, ?SubscriptionPayment $payment = null, ?int $userId = null): void
    {
        if ($modules === []) {
            return;
        }

        $userId ??= auth()->id();

        DB::transaction(function () use ($tenant, $modules, $payment, $userId): void {
            foreach (array_unique($modules) as $module) {
                $row = $tenant->addonModules()->firstOrNew(['module' => $module]);

                $row->fill([
                    'tenant_id' => $tenant->id,
                    'installed_at' => now(),
                    'installed_by' => $userId,
                    'uninstalled_at' => null,
                    'uninstalled_by' => null,
                ]);

                if ($payment !== null) {
                    $row->subscription_payment_id = $payment->id;
                }

                $row->save();
            }

            $tenant->unsetRelation('addonModules');

            $this->provisioner->provision($tenant->fresh());
        });
    }

    /** Free reinstall of a module this tenant bought and later uninstalled. */
    public function reinstall(Tenant $tenant, string $module, ?int $userId = null): void
    {
        if (($this->states($tenant)[$module] ?? null) !== 'uninstalled') {
            throw new RuntimeException("{$this->label($module)} can't be reinstalled — it isn't an uninstalled add-on.");
        }

        $this->assertRequirementsMet($tenant, [$module]);

        $this->install($tenant, [$module], null, $userId);
    }

    /**
     * Hides a bought module. Nothing is deleted: reinstalling brings every
     * record back, and cross-module postings (e.g. journals from Sales) keep
     * happening meanwhile so the books stay whole.
     */
    public function uninstall(Tenant $tenant, string $module, ?int $userId = null): void
    {
        $state = $this->states($tenant)[$module] ?? null;

        if ($state === 'plan') {
            throw new RuntimeException("{$this->label($module)} is included in your plan — change your plan to remove it.");
        }

        if ($state !== 'addon') {
            throw new RuntimeException("{$this->label($module)} is not installed.");
        }

        $installed = array_diff($tenant->planModules() ?? [], [$module]);
        $dependents = array_values(array_filter(
            $installed,
            fn (string $other) => in_array($module, $this->requirements($other), true),
        ));

        if ($dependents !== []) {
            throw new RuntimeException(sprintf(
                '%s is needed by %s — uninstall that first.',
                $this->label($module),
                $this->labels($dependents),
            ));
        }

        $tenant->addonModules()->where('module', $module)->update([
            'uninstalled_at' => now(),
            'uninstalled_by' => $userId ?? auth()->id(),
        ]);

        $tenant->unsetRelation('addonModules');
    }

    public function label(string $module): string
    {
        return config("navigation.apps.$module.label", ucfirst($module));
    }

    /** @param array<int, string> $modules */
    public function labels(array $modules): string
    {
        return collect($modules)->map(fn (string $m) => $this->label($m))->implode(', ');
    }
}
