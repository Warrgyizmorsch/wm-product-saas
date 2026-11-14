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
        Schema::table('routings', function (Blueprint $table) {
            if (Schema::hasColumn('routings', 'product_id')) {
                // Drop index if present
                try {
                    $table->dropIndex('routings_tenant_product_status_index');
                } catch (\Throwable $e) {
                    // index might not exist under this name
                }

                // Drop foreign key and column
                try {
                    $table->dropForeign(['product_id']);
                } catch (\Throwable $e) {
                    // foreign key might already be dropped or named differently
                }

                $table->dropColumn('product_id');
            }

            // Index tenant_id and status for fast listing
            $table->index(['tenant_id', 'status'], 'routings_tenant_status_index');

            // Unique index for name and version per tenant
            $table->unique(['tenant_id', 'name', 'version'], 'routings_tenant_name_version_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routings', function (Blueprint $table) {
            try {
                $table->dropUnique('routings_tenant_name_version_unique');
            } catch (\Throwable $e) {}

            try {
                $table->dropIndex('routings_tenant_status_index');
            } catch (\Throwable $e) {}

            if (!Schema::hasColumn('routings', 'product_id')) {
                $table->foreignId('product_id')
                    ->nullable()
                    ->after('routing_number')
                    ->constrained('products')
                    ->nullOnDelete();

                $table->index(['tenant_id', 'product_id', 'status'], 'routings_tenant_product_status_index');
            }
        });
    }
};

