<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transporters', function (Blueprint $table) {
            if (!Schema::hasColumn('transporters', 'code')) {
                $table->string('code', 50)->nullable()->after('name');
            }
            if (!Schema::hasColumn('transporters', 'pan_number')) {
                $table->string('pan_number', 20)->nullable()->after('gstin');
            }
            if (!Schema::hasColumn('transporters', 'tds_section')) {
                $table->string('tds_section', 50)->nullable()->default('194C')->after('pan_number');
            }
            if (!Schema::hasColumn('transporters', 'tds_rate')) {
                $table->decimal('tds_rate', 5, 2)->default(1.00)->after('tds_section');
            }
            if (!Schema::hasColumn('transporters', 'has_194c_declaration')) {
                $table->boolean('has_194c_declaration')->default(false)->after('tds_rate');
            }
            if (!Schema::hasColumn('transporters', 'declaration_reference')) {
                $table->string('declaration_reference', 100)->nullable()->after('has_194c_declaration');
            }
            if (!Schema::hasColumn('transporters', 'sac_code')) {
                $table->string('sac_code', 20)->nullable()->default('996511')->after('declaration_reference');
            }
            if (!Schema::hasColumn('transporters', 'transport_mode')) {
                $table->string('transport_mode', 50)->default('road')->after('sac_code');
            }
            if (!Schema::hasColumn('transporters', 'fleet_type')) {
                $table->string('fleet_type', 100)->nullable()->after('transport_mode');
            }
            if (!Schema::hasColumn('transporters', 'serviceable_zones')) {
                $table->text('serviceable_zones')->nullable()->after('fleet_type');
            }
            if (!Schema::hasColumn('transporters', 'bank_name')) {
                $table->string('bank_name', 150)->nullable()->after('serviceable_zones');
            }
            if (!Schema::hasColumn('transporters', 'branch_name')) {
                $table->string('branch_name', 150)->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('transporters', 'account_name')) {
                $table->string('account_name', 150)->nullable()->after('branch_name');
            }
            if (!Schema::hasColumn('transporters', 'account_number')) {
                $table->string('account_number', 50)->nullable()->after('account_name');
            }
            if (!Schema::hasColumn('transporters', 'ifsc_code')) {
                $table->string('ifsc_code', 20)->nullable()->after('account_number');
            }
            if (!Schema::hasColumn('transporters', 'payment_terms')) {
                $table->string('payment_terms', 100)->default('Net 30 Days')->after('ifsc_code');
            }
            if (!Schema::hasColumn('transporters', 'opening_balance')) {
                $table->decimal('opening_balance', 15, 2)->default(0.00)->after('payment_terms');
            }
            if (!Schema::hasColumn('transporters', 'contact_person_name')) {
                $table->string('contact_person_name', 150)->nullable()->after('opening_balance');
            }
            if (!Schema::hasColumn('transporters', 'contact_person_phone')) {
                $table->string('contact_person_phone', 30)->nullable()->after('contact_person_name');
            }
            if (!Schema::hasColumn('transporters', 'contact_person_email')) {
                $table->string('contact_person_email', 150)->nullable()->after('contact_person_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transporters', function (Blueprint $table) {
            $table->dropColumn([
                'code', 'pan_number', 'tds_section', 'tds_rate',
                'has_194c_declaration', 'declaration_reference', 'sac_code',
                'transport_mode', 'fleet_type', 'serviceable_zones',
                'bank_name', 'branch_name', 'account_name', 'account_number',
                'ifsc_code', 'payment_terms', 'opening_balance',
                'contact_person_name', 'contact_person_phone', 'contact_person_email'
            ]);
        });
    }
};
