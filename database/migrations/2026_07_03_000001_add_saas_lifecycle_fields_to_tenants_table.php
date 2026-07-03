<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'subscription_status')) {
                $table->string('subscription_status')->default('trial')->after('plan')->index();
            }

            if (! Schema::hasColumn('tenants', 'billing_email')) {
                $table->string('billing_email')->nullable()->after('domain');
            }

            if (! Schema::hasColumn('tenants', 'max_users')) {
                $table->unsignedInteger('max_users')->nullable()->after('subscription_status');
            }

            if (! Schema::hasColumn('tenants', 'max_storage_mb')) {
                $table->unsignedInteger('max_storage_mb')->nullable()->after('max_users');
            }

            if (! Schema::hasColumn('tenants', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('max_storage_mb');
            }

            if (! Schema::hasColumn('tenants', 'plan_started_at')) {
                $table->timestamp('plan_started_at')->nullable()->after('trial_ends_at');
            }

            if (! Schema::hasColumn('tenants', 'plan_expires_at')) {
                $table->timestamp('plan_expires_at')->nullable()->after('plan_started_at');
            }

            if (! Schema::hasColumn('tenants', 'onboarded_at')) {
                $table->timestamp('onboarded_at')->nullable()->after('plan_expires_at');
            }

            if (! Schema::hasColumn('tenants', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('onboarded_at')->index();
            }

            if (! Schema::hasColumn('tenants', 'owner_user_id')) {
                $table->unsignedBigInteger('owner_user_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('tenants', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (! $this->foreignKeyExists('tenants', 'tenants_owner_user_id_foreign')) {
                $table->foreign('owner_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });

        DB::table('tenants')
            ->where('status', 'inactive')
            ->update(['status' => 'suspended']);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if ($this->foreignKeyExists('tenants', 'tenants_owner_user_id_foreign')) {
                $table->dropForeign('tenants_owner_user_id_foreign');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            foreach ([
                'owner_user_id',
                'billing_email',
                'subscription_status',
                'max_users',
                'max_storage_mb',
                'trial_ends_at',
                'plan_started_at',
                'plan_expires_at',
                'onboarded_at',
                'archived_at',
                'deleted_at',
            ] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $foreignKeys = DB::select("PRAGMA foreign_key_list('{$table}')");
            foreach ($foreignKeys as $fk) {
                if ($fk->table === 'users' && $fk->from === 'owner_user_id') {
                    return true;
                }
            }

            return false;
        }

        return DB::table('information_schema.table_constraints')
            ->whereRaw('constraint_schema = database()')
            ->where('table_name', $table)
            ->where('constraint_name', $foreignKey)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
