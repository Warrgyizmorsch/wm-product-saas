<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create routings table
        Schema::create('routings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // 2. Add unit_cost to products table
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'unit_cost')) {
                $table->decimal('unit_cost', 12, 4)->default(0.0000)->after('status');
            }
        });

        // 3. Add columns to production_boms
        Schema::table('production_boms', function (Blueprint $table) {
            $table->string('bom_name')->nullable()->after('bom_number');
            $table->string('bom_type')->default('manufacturing')->after('bom_name');
            $table->decimal('base_quantity', 12, 4)->default(1.0000)->after('product_id');
            $table->foreignId('base_uom_id')->nullable()->after('base_quantity')->constrained('uoms')->nullOnDelete();
            $table->text('revision_reason')->nullable()->after('version');
            $table->foreignId('routing_id')->nullable()->after('revision_reason')->constrained('routings')->nullOnDelete();
        });

        // 4. Rename wastage_percentage and add columns to production_bom_items
        Schema::table('production_bom_items', function (Blueprint $table) {
            $table->renameColumn('wastage_percentage', 'material_scrap_percentage');
        });

        Schema::table('production_bom_items', function (Blueprint $table) {
            $table->integer('priority')->default(1)->after('alternative_group');
            $table->date('effective_from')->nullable()->after('sequence');
            $table->date('effective_to')->nullable()->after('effective_from');
        });

        // 5. Create production_bom_approvals table
        Schema::create('production_bom_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bom_id')->constrained('production_boms')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // Created, Submitted, Approved, Rejected, Revision Created
            $table->text('comments')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_bom_approvals');

        Schema::table('production_bom_items', function (Blueprint $table) {
            $table->dropColumn(['priority', 'effective_from', 'effective_to']);
            $table->renameColumn('material_scrap_percentage', 'wastage_percentage');
        });

        Schema::table('production_boms', function (Blueprint $table) {
            $table->dropForeign(['base_uom_id']);
            $table->dropForeign(['routing_id']);
            $table->dropColumn(['bom_name', 'bom_type', 'base_quantity', 'base_uom_id', 'revision_reason', 'routing_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });

        Schema::dropIfExists('routings');
    }
};
