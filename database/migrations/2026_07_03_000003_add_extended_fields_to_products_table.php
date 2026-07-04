<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('tenant_id')->constrained('products')->cascadeOnDelete();
            $table->string('item_type')->default('Goods')->after('type'); // Goods, Service
            $table->string('variation_type')->default('Single')->after('item_type'); // Single, Variant
            $table->foreignId('uom_id')->nullable()->after('variation_type')->constrained('uoms')->nullOnDelete();
            
            $table->string('hsn_sac')->nullable()->after('status');
            $table->decimal('gst_rate', 5, 2)->default(18.00)->after('hsn_sac');
            $table->foreignId('preferred_vendor_id')->nullable()->after('gst_rate')->constrained('vendors')->nullOnDelete();
            
            $table->decimal('selling_price', 12, 4)->default(0.0000)->after('preferred_vendor_id');
            $table->decimal('cost_price', 12, 4)->default(0.0000)->after('selling_price');
            
            $table->string('sales_account')->nullable()->after('cost_price');
            $table->string('purchase_account')->nullable()->after('sales_account');
            $table->string('inventory_account')->nullable()->after('purchase_account');
            
            $table->decimal('reorder_point', 12, 4)->default(0.0000)->after('inventory_account');
            $table->decimal('opening_stock', 12, 4)->default(0.0000)->after('reorder_point');
            $table->decimal('opening_stock_rate', 12, 4)->default(0.0000)->after('opening_stock');
            
            $table->text('description')->nullable()->after('opening_stock_rate');
            
            $table->json('attributes_config')->nullable()->after('description');
            $table->json('variant_values')->nullable()->after('attributes_config');
            
            // Brand & Manufacturer
            $table->string('brand')->nullable()->after('variant_values');
            $table->string('manufacturer')->nullable()->after('brand');
            $table->string('mpn')->nullable()->after('manufacturer');
            
            // Identifiers
            $table->string('barcode')->nullable()->after('mpn');
            $table->string('upc')->nullable()->after('barcode');
            $table->string('ean')->nullable()->after('upc');
            $table->string('isbn')->nullable()->after('ean');
            
            // Dimensions & Weight
            $table->decimal('length', 8, 2)->nullable()->after('isbn');
            $table->decimal('width', 8, 2)->nullable()->after('length');
            $table->decimal('height', 8, 2)->nullable()->after('width');
            $table->decimal('weight', 8, 2)->nullable()->after('height');
            $table->string('dimension_unit')->nullable()->after('height'); // cm, in, mm, m
            $table->string('weight_unit')->nullable()->after('dimension_unit'); // kg, g, lb, oz
            
            // Advanced Tracking
            $table->boolean('track_serial_number')->default(false)->after('weight_unit');
            $table->boolean('track_batch')->default(false)->after('track_serial_number');
            
            $table->string('image_path')->nullable()->after('track_batch');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['uom_id']);
            $table->dropForeign(['preferred_vendor_id']);
            
            $table->dropColumn([
                'parent_id',
                'item_type',
                'variation_type',
                'uom_id',
                'hsn_sac',
                'gst_rate',
                'preferred_vendor_id',
                'selling_price',
                'cost_price',
                'sales_account',
                'purchase_account',
                'inventory_account',
                'reorder_point',
                'opening_stock',
                'opening_stock_rate',
                'description',
                'attributes_config',
                'variant_values',
                'brand',
                'manufacturer',
                'mpn',
                'barcode',
                'upc',
                'ean',
                'isbn',
                'length',
                'width',
                'height',
                'weight',
                'dimension_unit',
                'weight_unit',
                'track_serial_number',
                'track_batch',
                'image_path'
            ]);
        });
    }
};
