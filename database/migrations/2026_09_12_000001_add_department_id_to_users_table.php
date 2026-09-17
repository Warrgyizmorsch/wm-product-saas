<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AccessService needs a department to compare a SCOPE_DEPARTMENT grant
     * against, the same way it compares company_id/branch_id (added in
     * 2026_09_01_010100). Resolving it through the HRMS Employee record instead
     * would couple the shared access layer to a domain module, so the column
     * lives on users alongside the other two scope anchors.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            });
        }

        // Backfill from the HRMS employee record where a user is already linked
        // to one. Users with no employee record (platform admins, service
        // accounts) keep a null department and simply never match a
        // department-scoped grant.
        if (Schema::hasTable('employees')) {
            DB::table('employees')
                ->whereNotNull('user_id')
                ->whereNotNull('department_id')
                ->orderBy('id')
                ->chunkById(500, function ($employees): void {
                    foreach ($employees as $employee) {
                        DB::table('users')
                            ->where('id', $employee->user_id)
                            ->whereNull('department_id')
                            ->update(['department_id' => $employee->department_id]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropConstrainedForeignId('department_id');
            }
        });
    }
};
