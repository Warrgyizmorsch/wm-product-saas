<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                if (Schema::hasColumn('tenants', 'slug')) {
                    $table->string('slug')->change();
                }

                if (Schema::hasColumn('tenants', 'domain')) {
                    $table->string('domain')->nullable()->change();
                }

                if (! Schema::hasColumn('tenants', 'timezone')) {
                    $table->string('timezone')->default('UTC')->after('status');
                }

                if (! Schema::hasColumn('tenants', 'plan')) {
                    $table->string('plan')->default('starter')->after('status');
                }

                if (! Schema::hasColumn('tenants', 'locale')) {
                    $table->string('locale')->default('en')->after('timezone');
                }

                if (! Schema::hasColumn('tenants', 'settings')) {
                    $table->json('settings')->nullable()->after('locale');
                }
            });

            return;
        }

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();
            $table->string('status')->default('active')->index();
            $table->string('plan')->default('starter');
            $table->string('timezone')->default('UTC');
            $table->string('locale')->default('en');
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
