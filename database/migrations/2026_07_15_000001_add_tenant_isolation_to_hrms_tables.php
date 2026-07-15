<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that currently have no tenant_id column at all, in an order that
     * respects their foreign key dependencies (parents before children).
     */
    private array $tables = [
        'organizations',
        'companies',
        'business_units',
        'branches',
        'departments',
        'designations',
        'pay_groups',
        'salary_components',
        'salary_structures',
        'salary_structure_items',
        'leave_plans',
        'leave_types',
        'attendance_penalties',
        'employees',
        'employee_penalties',
        'employee_employment_histories',
        'employee_adhoc_components',
        'asset_categories',
        'assets',
        'asset_allocations',
        'asset_requests',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        // All existing rows in these tables were created by HrmsDemoSeeder for the "demo" tenant
        // (confirmed: single identical created_at timestamp across every row, no other tenant data present).
        $demoTenantId = DB::table('tenants')->where('slug', 'demo')->value('id');

        if ($demoTenantId !== null) {
            foreach ($this->tables as $table) {
                DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $demoTenantId]);
            }
        }

        // These unique constraints were defined without tenant scoping, so they currently block
        // any two tenants from ever using the same code/slug/employee id. Rescope them per tenant.
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique('organizations_slug_unique');
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::table('business_units', function (Blueprint $table) {
            $table->dropUnique('business_units_code_unique');
            $table->unique(['tenant_id', 'code']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique('branches_code_unique');
            $table->unique(['tenant_id', 'code']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropUnique('assets_asset_code_unique');
            $table->unique(['tenant_id', 'asset_code']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_employee_id_unique');
            $table->dropUnique('employees_aadhaar_card_number_unique');
            $table->dropUnique('employees_pan_card_number_unique');
            $table->dropUnique('employees_personal_email_unique');
            $table->unique(['tenant_id', 'employee_id']);
            $table->unique(['tenant_id', 'aadhaar_card_number']);
            $table->unique(['tenant_id', 'pan_card_number']);
            $table->unique(['tenant_id', 'personal_email']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'personal_email']);
            $table->dropUnique(['tenant_id', 'pan_card_number']);
            $table->dropUnique(['tenant_id', 'aadhaar_card_number']);
            $table->dropUnique(['tenant_id', 'employee_id']);
            $table->unique('employee_id');
            $table->unique('aadhaar_card_number');
            $table->unique('pan_card_number');
            $table->unique('personal_email');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'asset_code']);
            $table->unique('asset_code');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->unique('code');
        });

        Schema::table('business_units', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->unique('code');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->unique('slug');
        });

        foreach (array_reverse($this->tables) as $table) {
            if (! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['tenant_id']);
                $blueprint->dropColumn('tenant_id');
            });
        }
    }
};
