<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->foreignId('plan_id')->nullable()->after('plan')->constrained('plans')->nullOnDelete();
        });

        // Backfill: link every existing tenant to the catalog row matching its legacy enum value.
        // The `plan` string column is left in place for now — reads/writes migrate to `plan_id`
        // over subsequent changes, and the enum column is dropped only once nothing references it.
        $plans = DB::table('plans')->pluck('id', 'slug');

        foreach ($plans as $slug => $planId) {
            DB::table('tenants')->where('plan', $slug)->update(['plan_id' => $planId]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('plan_id');
        });
    }
};
