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
            if (! $this->indexExists('tenants', 'tenants_slug_unique')) {
                $table->unique('slug');
            }

            if (! $this->indexExists('tenants', 'tenants_domain_unique')) {
                $table->unique('domain');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if ($this->indexExists('tenants', 'tenants_domain_unique')) {
                $table->dropUnique('tenants_domain_unique');
            }

            if ($this->indexExists('tenants', 'tenants_slug_unique')) {
                $table->dropUnique('tenants_slug_unique');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $indices = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indices as $idx) {
                if ($idx->name === $index) {
                    return true;
                }
            }
            return false;
        }

        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = database()')
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
