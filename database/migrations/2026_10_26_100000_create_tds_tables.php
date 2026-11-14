<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TDS (non-salary) — Tally's "TDS Nature of Payment" setup:
 *  - tds_sections: the section master (194C, 194J ...) with rates, thresholds
 *    and the payable ledger each section posts to.
 *  - tds_deductions: every deduction (and every bill below threshold, so the
 *    annual aggregate stays right) — the register 26Q and Form 16A come from.
 *  - tds_challans: tax deposited to the government (ITNS 281).
 *  - tds_configurations: deductor details (TAN, responsible person).
 * Plus TDS fields on vendors, vendor bills and customer receipts.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'accounting.tds.view' => ['tds', 'view'],
        'accounting.tds.configure' => ['tds', 'configure'],
        'accounting.tds.challans.create' => ['tds_challans', 'create'],
        'accounting.tds.returns.view' => ['tds_returns', 'view'],
        'accounting.tds.returns.export' => ['tds_returns', 'export'],
    ];

    public function up(): void
    {
        Schema::create('tds_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 20);            // 194C, 194J ...
            $table->string('name');
            $table->string('nature_of_payment')->nullable();
            $table->decimal('rate_individual', 6, 3)->default(0); // individual / HUF
            $table->decimal('rate_other', 6, 3)->default(0);      // company, firm, others
            $table->decimal('rate_no_pan', 6, 3)->default(20);    // section 206AA
            $table->decimal('single_threshold', 15, 2)->nullable();
            $table->decimal('aggregate_threshold', 15, 2)->nullable();
            $table->string('threshold_period', 20)->default('annual'); // per_transaction | annual | monthly
            // 194Q: tax only the part of the year's purchases above the limit,
            // instead of catching up the whole aggregate once it is crossed.
            $table->boolean('deduct_on_excess_only')->default(false);
            $table->unsignedBigInteger('payable_account_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code', 'name'], 'tds_sections_tenant_code_unique');
        });

        Schema::create('tds_challans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('challan_number', 30);  // challan serial no.
            $table->string('bsr_code', 10)->nullable();
            $table->string('cin', 30)->nullable();
            $table->date('deposit_date');
            $table->string('period_month', 7);     // YYYY-MM the tax was deducted in
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('interest', 15, 2)->default(0);
            $table->decimal('late_fee', 15, 2)->default(0);
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('journal_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'deposit_date']);
        });

        Schema::create('tds_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->foreignId('tds_section_id')->constrained('tds_sections')->restrictOnDelete();
            $table->string('party_type', 20);      // vendor (JournalEntry party types)
            $table->unsignedBigInteger('party_id');
            $table->string('party_name')->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('deductee_type', 20)->nullable();
            $table->string('source_type', 40);     // vendor_bill | journal
            $table->unsignedBigInteger('source_id');
            $table->string('source_number')->nullable();
            $table->unsignedBigInteger('journal_id')->nullable()->index();
            $table->date('deduction_date');
            $table->decimal('base_amount', 15, 2);
            // Earlier bills of the year taxed on this one when it crossed the
            // aggregate threshold (194C-style catch-up). TDS = (base + catchup) x rate.
            $table->decimal('catchup_base', 15, 2)->default(0);
            $table->decimal('rate', 6, 3)->default(0);
            $table->decimal('tds_amount', 15, 2)->default(0);
            $table->string('reason', 20)->default('normal'); // normal | no_pan | lower_cert | below_threshold
            $table->string('fiscal_year', 9);     // 2026-27
            $table->unsignedTinyInteger('quarter'); // 1..4, April-March
            $table->foreignId('tds_challan_id')->nullable()->constrained('tds_challans')->nullOnDelete();
            $table->boolean('is_void')->default(false); // source journal reversed
            $table->timestamps();

            $table->unique(['tenant_id', 'source_type', 'source_id', 'tds_section_id'], 'tds_deductions_source_unique');
            $table->index(['tenant_id', 'party_type', 'party_id', 'fiscal_year'], 'tds_deductions_party_fy_idx');
            $table->index(['tenant_id', 'fiscal_year', 'quarter'], 'tds_deductions_quarter_idx');
        });

        Schema::create('tds_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('tan', 10)->nullable();
            $table->string('deductor_type', 30)->nullable(); // company, firm, individual, government ...
            $table->string('deductor_name')->nullable();
            $table->string('deductor_pan', 10)->nullable();
            $table->string('responsible_person_name')->nullable();
            $table->string('responsible_person_designation')->nullable();
            $table->string('responsible_person_pan', 10)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'company_id']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->boolean('tds_applicable')->default(false);
            $table->unsignedBigInteger('tds_section_id')->nullable();
            $table->string('deductee_type', 20)->nullable(); // individual | huf | company | firm | others
            $table->string('ldc_number', 30)->nullable();     // section 197 lower deduction certificate
            $table->decimal('ldc_rate', 6, 3)->nullable();
            $table->date('ldc_valid_from')->nullable();
            $table->date('ldc_valid_to')->nullable();
            $table->decimal('ldc_limit', 15, 2)->nullable();
        });

        Schema::table('vendor_bills', function (Blueprint $table) {
            $table->unsignedBigInteger('tds_section_id')->nullable();
            $table->decimal('tds_base_amount', 15, 4)->default(0);
            $table->decimal('tds_rate', 6, 3)->default(0);
            $table->decimal('tds_amount', 15, 4)->default(0);
            $table->decimal('tds_catchup_base', 15, 2)->default(0);
            $table->string('tds_reason', 20)->nullable();
            $table->boolean('tds_overridden')->default(false);
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->decimal('tds_amount', 15, 2)->default(0);
            $table->string('tds_section_code', 20)->nullable();
            $table->boolean('tds_certificate_received')->default(false);
        });

        $this->grantPermissions();
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropColumn(['tds_amount', 'tds_section_code', 'tds_certificate_received']);
        });
        Schema::table('vendor_bills', function (Blueprint $table) {
            $table->dropColumn(['tds_section_id', 'tds_base_amount', 'tds_rate', 'tds_amount', 'tds_catchup_base', 'tds_reason', 'tds_overridden']);
        });
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['tds_applicable', 'tds_section_id', 'deductee_type', 'ldc_number', 'ldc_rate', 'ldc_valid_from', 'ldc_valid_to', 'ldc_limit']);
        });

        Schema::dropIfExists('tds_configurations');
        Schema::dropIfExists('tds_deductions');
        Schema::dropIfExists('tds_challans');
        Schema::dropIfExists('tds_sections');

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
            DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }

    /** Existing installs don't re-run RbacSeeder, so add the permissions and grants here. */
    private function grantPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        $editors = ['tenant_owner', 'company_admin', 'accountant'];
        $grants = [
            'accounting.tds.view' => [...$editors, 'auditor'],
            'accounting.tds.configure' => $editors,
            'accounting.tds.challans.create' => $editors,
            'accounting.tds.returns.view' => [...$editors, 'auditor'],
            'accounting.tds.returns.export' => $editors,
        ];

        foreach (self::PERMISSIONS as $name => [$entity, $action]) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['module' => 'accounting', 'entity' => $entity, 'action' => $action, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            );
            $permissionId = DB::table('permissions')->where('name', $name)->value('id');

            foreach (DB::table('roles')->whereIn('slug', $grants[$name])->pluck('id') as $roleId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId, 'scope' => 'tenant'],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }

            $superAdmin = DB::table('roles')->where('slug', 'super_admin')->value('id');
            if ($superAdmin) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $superAdmin, 'permission_id' => $permissionId, 'scope' => 'platform'],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }
};
