<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pay_groups')) {
            Schema::create('pay_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->default(1);
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('salary_components') && ! Schema::hasColumn('salary_components', 'pay_group_id')) {
            Schema::table('salary_components', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_group_id')->nullable()->after('company_id');
                $table->foreign('pay_group_id')->references('id')->on('pay_groups')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('salary_structures') && ! Schema::hasColumn('salary_structures', 'pay_group_id')) {
            Schema::table('salary_structures', function (Blueprint $table) {
                $table->unsignedBigInteger('pay_group_id')->nullable()->after('company_id');
                $table->foreign('pay_group_id')->references('id')->on('pay_groups')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('salary_structures') && Schema::hasColumn('salary_structures', 'pay_group_id')) {
            Schema::table('salary_structures', function (Blueprint $table) {
                $table->dropForeign(['pay_group_id']);
                $table->dropColumn('pay_group_id');
            });
        }

        if (Schema::hasTable('salary_components') && Schema::hasColumn('salary_components', 'pay_group_id')) {
            Schema::table('salary_components', function (Blueprint $table) {
                $table->dropForeign(['pay_group_id']);
                $table->dropColumn('pay_group_id');
            });
        }

        Schema::dropIfExists('pay_groups');
    }
};
