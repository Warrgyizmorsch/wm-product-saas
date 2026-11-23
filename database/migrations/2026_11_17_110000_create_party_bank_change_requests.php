<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRS 2.3 (task C-16, bank part): a verified vendor's bank details can only be
 * changed through a request that a different person approves after a recorded
 * callback to the number already on file.
 *
 * vendors.bank_details_locked DEFAULTS to true, so every existing vendor is
 * protected from now on. New vendors are created unlocked (the model sets that)
 * so their first bank details can be entered and verified normally; they lock
 * when first verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vendors', 'bank_details_locked')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->boolean('bank_details_locked')->default(true);
            });
        }

        if (! Schema::hasTable('party_bank_change_requests')) {
            Schema::create('party_bank_change_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('party_type', 20); // vendor
                $table->unsignedBigInteger('party_id');
                $table->string('status', 20)->default('pending')->index(); // pending, approved, rejected, cancelled

                $table->string('old_bank_name', 150)->nullable();
                $table->string('old_account_number', 50)->nullable();
                $table->string('old_ifsc_code', 30)->nullable();
                $table->string('new_bank_name', 150)->nullable();
                $table->string('new_account_number', 50)->nullable();
                $table->string('new_ifsc_code', 30)->nullable();
                $table->text('reason')->nullable();

                // The phone on the vendor record when the request was raised: the callback must use this number.
                $table->string('phone_on_file', 30)->nullable();

                $table->unsignedBigInteger('requested_by')->nullable();
                $table->timestamp('requested_at')->nullable();

                $table->unsignedBigInteger('callback_by')->nullable();
                $table->timestamp('callback_at')->nullable();
                $table->string('callback_number', 30)->nullable();
                $table->string('callback_contact', 150)->nullable();
                $table->text('callback_notes')->nullable();

                $table->unsignedBigInteger('decided_by')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->text('decision_remarks')->nullable();
                $table->timestamp('applied_at')->nullable();

                $table->timestamps();

                $table->index(['party_type', 'party_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('party_bank_change_requests');

        if (Schema::hasColumn('vendors', 'bank_details_locked')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->dropColumn('bank_details_locked');
            });
        }
    }
};
