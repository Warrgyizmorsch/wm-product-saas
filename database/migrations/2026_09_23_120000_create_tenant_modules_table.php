<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Self-service module add-ons, one row per tenant+module ever bought
        // (see TenantModuleService). Uninstalling only stamps uninstalled_at —
        // the row stays, so the one-time fee isn't charged again on reinstall.
        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('module');
            $table->foreignId('subscription_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('installed_at');
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uninstalled_at')->nullable();
            $table->foreignId('uninstalled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'module']);
        });

        // Move the old tenants.settings.installed_modules list into rows.
        foreach (DB::table('tenants')->whereNotNull('settings')->get(['id', 'settings']) as $tenant) {
            $settings = json_decode($tenant->settings, true) ?: [];
            $modules = $settings['installed_modules'] ?? [];

            if (! array_key_exists('installed_modules', $settings)) {
                continue;
            }

            $payments = DB::table('subscription_payments')
                ->where('tenant_id', $tenant->id)
                ->where('purpose', 'module_addon')
                ->where('status', 'paid')
                ->orderBy('id')
                ->get(['id', 'modules', 'updated_at']);

            foreach (array_unique($modules) as $module) {
                $payment = $payments->first(fn ($p) => in_array($module, json_decode($p->modules ?? '[]', true) ?: [], true));

                DB::table('tenant_modules')->insert([
                    'tenant_id' => $tenant->id,
                    'module' => $module,
                    'subscription_payment_id' => $payment?->id,
                    'installed_at' => $payment?->updated_at ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            unset($settings['installed_modules']);
            DB::table('tenants')->where('id', $tenant->id)->update(['settings' => json_encode($settings)]);
        }
    }

    public function down(): void
    {
        $active = DB::table('tenant_modules')->whereNull('uninstalled_at')->get(['tenant_id', 'module'])->groupBy('tenant_id');

        foreach ($active as $tenantId => $rows) {
            $settings = json_decode(DB::table('tenants')->where('id', $tenantId)->value('settings') ?? '[]', true) ?: [];
            $settings['installed_modules'] = $rows->pluck('module')->values()->all();
            DB::table('tenants')->where('id', $tenantId)->update(['settings' => json_encode($settings)]);
        }

        Schema::dropIfExists('tenant_modules');
    }
};
