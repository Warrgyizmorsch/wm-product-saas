<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cost_centers')) {
            Schema::create('cost_centers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('code');
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
                $table->index('tenant_id');
            });
        }

        if (!Schema::hasColumn('journal_entries', 'cost_center_id')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->unsignedBigInteger('cost_center_id')->nullable()->after('chart_of_account_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('journal_entries', 'cost_center_id')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->dropColumn('cost_center_id');
            });
        }

        Schema::dropIfExists('cost_centers');
    }
};
