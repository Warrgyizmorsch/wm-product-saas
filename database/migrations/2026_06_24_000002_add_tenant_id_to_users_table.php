<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            } else {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->after('id');
            }

            if ($this->indexExists('users', 'users_email_unique')) {
                $table->dropUnique('users_email_unique');
            }

            if (! $this->indexExists('users', 'users_tenant_id_email_unique')) {
                $table->unique(['tenant_id', 'email']);
            }

            if (! $this->foreignKeyExists('users', 'users_tenant_id_foreign')) {
                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if ($this->foreignKeyExists('users', 'users_tenant_id_foreign')) {
                $table->dropForeign('users_tenant_id_foreign');
            }

            if ($this->indexExists('users', 'users_tenant_id_email_unique')) {
                $table->dropUnique(['tenant_id', 'email']);
            }

            if (! $this->indexExists('users', 'users_email_unique')) {
                $table->unique('email');
            }

            if (Schema::hasColumn('users', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = database()')
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        return DB::table('information_schema.table_constraints')
            ->whereRaw('constraint_schema = database()')
            ->where('table_name', $table)
            ->where('constraint_name', $foreignKey)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
