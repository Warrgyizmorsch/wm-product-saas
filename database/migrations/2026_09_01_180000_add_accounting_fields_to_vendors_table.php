<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'gstin')) {
                $table->string('gstin')->nullable()->after('code');
            }
            if (!Schema::hasColumn('vendors', 'pan')) {
                $table->string('pan')->nullable()->after('gstin');
            }
            if (!Schema::hasColumn('vendors', 'company_name')) {
                $table->string('company_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('vendors', 'billing_address')) {
                $table->text('billing_address')->nullable()->after('address');
            }
            if (!Schema::hasColumn('vendors', 'shipping_address')) {
                $table->text('shipping_address')->nullable()->after('billing_address');
            }
            if (!Schema::hasColumn('vendors', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('shipping_address');
            }
            if (!Schema::hasColumn('vendors', 'account_number')) {
                $table->string('account_number')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('vendors', 'ifsc_code')) {
                $table->string('ifsc_code')->nullable()->after('account_number');
            }
            if (!Schema::hasColumn('vendors', 'payment_terms')) {
                $table->string('payment_terms')->nullable()->after('ifsc_code');
            }
            if (!Schema::hasColumn('vendors', 'opening_balance')) {
                $table->decimal('opening_balance', 15, 2)->default(0.00)->after('payment_terms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn([
                'gstin',
                'pan',
                'company_name',
                'billing_address',
                'shipping_address',
                'bank_name',
                'account_number',
                'ifsc_code',
                'payment_terms',
                'opening_balance',
            ]);
        });
    }
};
