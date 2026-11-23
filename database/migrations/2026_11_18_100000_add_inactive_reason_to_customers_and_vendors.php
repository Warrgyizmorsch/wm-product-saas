<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRS 2.3 (task C-16): a recorded reason when a customer or vendor is made
 * inactive, and a different authorised person to reactivate one that was
 * stopped for a risk reason.
 *
 * All new columns are nullable, so existing rows and code paths are unaffected.
 * A party with no recorded reason is reactivated exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers', 'vendors'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'inactive_reason')) {
                    $table->string('inactive_reason', 30)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'inactive_note')) {
                    $table->text('inactive_note')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'inactivated_by')) {
                    $table->unsignedBigInteger('inactivated_by')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'inactivated_at')) {
                    $table->timestamp('inactivated_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['customers', 'vendors'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['inactive_reason', 'inactive_note', 'inactivated_by', 'inactivated_at'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
