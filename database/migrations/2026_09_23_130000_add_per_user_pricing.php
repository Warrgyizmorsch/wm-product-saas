<?php

use App\Http\Middleware\EnsureTenantModuleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Whole rupees per user per month; yearly_* is the per-month price when
        // billed yearly (shown like Zoho's "₹1,250 /user/month billed annually").
        // Null = not sold on that cycle.
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('monthly_price_per_user')->nullable()->after('price');
            $table->unsignedInteger('yearly_price_per_user')->nullable()->after('monthly_price_per_user');
        });

        // Platform-wide add-on price list, one row per gated module.
        Schema::create('module_prices', function (Blueprint $table) {
            $table->id();
            $table->string('module')->unique();
            $table->unsignedInteger('monthly_price_per_user')->nullable();
            $table->unsignedInteger('yearly_price_per_user')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('module_prices')->insert(array_map(
            fn (string $module) => ['module' => $module, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            EnsureTenantModuleAccess::GATED_MODULES,
        ));

        // 'lifetime' = bought with the old one-time fee, grandfathered free;
        // 'recurring' = billed per user every cycle.
        Schema::table('tenant_modules', function (Blueprint $table) {
            $table->string('billing')->default('recurring')->after('module');
        });

        DB::table('tenant_modules')->update(['billing' => 'lifetime']);
    }

    public function down(): void
    {
        Schema::table('tenant_modules', fn (Blueprint $table) => $table->dropColumn('billing'));
        Schema::dropIfExists('module_prices');
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn(['monthly_price_per_user', 'yearly_price_per_user']));
    }
};
