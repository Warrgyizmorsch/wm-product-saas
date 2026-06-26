<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'slug')) {
                $table->string('slug')->change();
            }

            if (Schema::hasColumn('tenants', 'domain')) {
                $table->string('domain')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'slug')) {
                $table->string('slug')->change();
            }

            if (Schema::hasColumn('tenants', 'domain')) {
                $table->string('domain')->nullable()->change();
            }
        });
    }
};
