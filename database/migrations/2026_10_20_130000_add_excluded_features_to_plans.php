<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Feature keys (config/plan_features.php) this plan leaves out; they stay
        // view-only for its tenants. Null/empty = every feature of its modules.
        Schema::table('plans', function (Blueprint $table) {
            $table->json('excluded_features')->nullable()->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('excluded_features');
        });
    }
};
