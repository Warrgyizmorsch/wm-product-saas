<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enhance the existing 'routings' stub table.
        // The stub was created in 2026_06_26_000020_enhance_production_boms_tables.php
        // with only: id, tenant_id, name, status (default 'active'), timestamps.
        //
        // This migration adds all required production routing columns.
        // Does NOT drop or recreate the table.
        // Does NOT touch production_boms.routing_id FK — it remains intact.

        Schema::table('routings', function (Blueprint $table) {
            // Routing business number — RTG-YYYY-NNNNNN format
            $table->string('routing_number')->nullable()->after('id')
                ->comment('Auto-generated: RTG-YYYY-NNNNNN. Unique per tenant.');

            // Link to the product being manufactured
            $table->foreignId('product_id')->nullable()->after('routing_number')
                ->constrained('products')->nullOnDelete()
                ->comment('The finished product this routing describes how to manufacture.');

            // Versioning
            $table->string('version', 50)->default('1.0.0')->after('name');
            $table->integer('revision')->default(0)->after('version');

            // A3: Routing alternatives support — is_default flag
            $table->boolean('is_default')->default(true)->after('revision')
                ->comment('A3: true = primary routing, false = alternative routing for same product.');

            // Effective date range
            $table->date('effective_from')->nullable()->after('is_default');
            $table->date('effective_to')->nullable()->after('effective_from');

            // Extended description
            $table->text('description')->nullable()->after('effective_to');

            // Approval audit trail
            $table->foreignId('created_by')->nullable()->after('description')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable()->after('approved_by');

            // A6: SoftDeletes
            $table->softDeletes();
        });

        // Change status default from 'active' (stub) to 'draft' (proper workflow)
        // We do this in a separate statement for cross-DB compatibility
        Schema::table('routings', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
        });

        // Add unique index for routing_number per tenant (after column is created)
        Schema::table('routings', function (Blueprint $table) {
            $table->unique(['tenant_id', 'routing_number'], 'routings_tenant_routing_number_unique');
            $table->index(['tenant_id', 'product_id', 'status'], 'routings_tenant_product_status_index');
            $table->index(['tenant_id', 'effective_from', 'effective_to'], 'routings_tenant_effective_index');
        });
    }

    public function down(): void
    {
        Schema::table('routings', function (Blueprint $table) {
            $table->dropUnique('routings_tenant_routing_number_unique');
            $table->dropIndex('routings_tenant_product_status_index');
            $table->dropIndex('routings_tenant_effective_index');
        });

        Schema::table('routings', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropForeign(['product_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'routing_number',
                'product_id',
                'version',
                'revision',
                'is_default',
                'effective_from',
                'effective_to',
                'description',
                'created_by',
                'approved_by',
                'approved_at',
            ]);
            $table->string('status')->default('active')->change();
        });
    }
};
