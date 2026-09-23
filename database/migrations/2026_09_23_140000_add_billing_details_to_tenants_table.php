<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who the subscription tax invoice is made out to (checkout "Pay" step).
        // billing_email already exists on tenants.
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('billing_name')->nullable()->after('billing_email');
            $table->string('billing_gstin', 15)->nullable()->after('billing_name');
            $table->text('billing_address')->nullable()->after('billing_gstin');
            $table->string('billing_state')->nullable()->after('billing_address');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['billing_name', 'billing_gstin', 'billing_address', 'billing_state']);
        });
    }
};
