<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FRS 2.3 (task C-15): second-person verification of GSTIN, PAN and bank
 * details before a customer or vendor becomes active.
 *
 * The verification_status column DEFAULTS to 'verified' so every existing row,
 * and every code path that has not been taught about verification (seeders,
 * imports, tests), keeps behaving exactly as before. Only the create paths that
 * explicitly ask for it start a party as 'pending_verification'.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers', 'vendors'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'verification_status')) {
                    $table->string('verification_status', 30)->default('verified')->index();
                }
                if (! Schema::hasColumn($tableName, 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'submitted_by')) {
                    $table->unsignedBigInteger('submitted_by')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'verified_by')) {
                    $table->unsignedBigInteger('verified_by')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'verified_at')) {
                    $table->timestamp('verified_at')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'verification_remarks')) {
                    $table->text('verification_remarks')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'verification_checks')) {
                    $table->json('verification_checks')->nullable();
                }
            });
        }

        // Customers did not carry PAN or bank details; verification needs them.
        Schema::table('customers', function (Blueprint $table) {
            foreach (['pan' => 20, 'bank_name' => 150, 'account_number' => 50, 'ifsc_code' => 30] as $column => $length) {
                if (! Schema::hasColumn('customers', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }
        });

        // Append-only trail: who submitted / verified / returned what, and when.
        if (! Schema::hasTable('party_verification_logs')) {
            Schema::create('party_verification_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('party_type', 20); // customer | vendor
                $table->unsignedBigInteger('party_id');
                $table->string('action', 30); // submitted, verified, returned, resubmitted, reverification_required
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30)->nullable();
                $table->text('remarks')->nullable();
                $table->json('changes')->nullable(); // old / new values of the sensitive fields
                $table->timestamps();

                $table->index(['party_type', 'party_id']);
            });
        }

        // Rows that existed before this feature were never independently checked.
        // They keep working (status verified) but are labelled honestly.
        foreach (['customers', 'vendors'] as $tableName) {
            DB::table($tableName)
                ->whereNull('verification_remarks')
                ->where('verification_status', 'verified')
                ->whereNull('verified_by')
                ->update(['verification_remarks' => 'Legacy record: existed before second-person verification, not independently verified.']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('party_verification_logs');

        Schema::table('customers', function (Blueprint $table) {
            foreach (['pan', 'bank_name', 'account_number', 'ifsc_code'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        foreach (['customers', 'vendors'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'verification_status')) {
                    $table->dropIndex($tableName . '_verification_status_index');
                }
                foreach (['verification_status', 'created_by', 'submitted_by', 'submitted_at', 'verified_by', 'verified_at', 'verification_remarks', 'verification_checks'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
