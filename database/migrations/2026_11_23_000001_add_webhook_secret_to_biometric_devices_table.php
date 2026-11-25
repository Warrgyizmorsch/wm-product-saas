<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('biometric_devices') && !Schema::hasColumn('biometric_devices', 'webhook_secret')) {
            Schema::table('biometric_devices', function (Blueprint $table) {
                $table->text('webhook_secret')->nullable()->after('device_serial');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('biometric_devices') && Schema::hasColumn('biometric_devices', 'webhook_secret')) {
            Schema::table('biometric_devices', function (Blueprint $table) {
                $table->dropColumn('webhook_secret');
            });
        }
    }
};
