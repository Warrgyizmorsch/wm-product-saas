<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create asset_items table
        Schema::create('asset_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('asset_category_id')->constrained('asset_categories')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Add asset_item_id to assets table
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('asset_item_id')
                  ->nullable()
                  ->after('asset_category_id')
                  ->constrained('asset_items')
                  ->onDelete('cascade');
        });

        // 3. Add asset_item_id and quantity to asset_requests table
        Schema::table('asset_requests', function (Blueprint $table) {
            $table->foreignId('asset_item_id')
                  ->nullable()
                  ->after('asset_category_id')
                  ->constrained('asset_items')
                  ->onDelete('cascade');
            $table->integer('quantity')->default(1)->after('asset_item_id');
        });

        // 4. Data Migration - Re-map existing records
        $existingAssets = DB::table('assets')->get();
        $itemMap = []; // Key: company_id . '_' . asset_category_id . '_' . name, Value: item_id

        foreach ($existingAssets as $asset) {
            $key = $asset->company_id . '_' . $asset->asset_category_id . '_' . strtolower(trim($asset->name));
            if (!isset($itemMap[$key])) {
                $itemId = DB::table('asset_items')->insertGetId([
                    'tenant_id' => $asset->tenant_id,
                    'company_id' => $asset->company_id,
                    'asset_category_id' => $asset->asset_category_id,
                    'name' => $asset->name,
                    'description' => 'Automatically generated item master from existing registry.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $itemMap[$key] = $itemId;
            }

            // Update this asset to point to the new item
            DB::table('assets')
              ->where('id', $asset->id)
              ->update(['asset_item_id' => $itemMap[$key]]);
        }

        // Re-map asset requests to correct items
        $existingRequests = DB::table('asset_requests')->get();
        foreach ($existingRequests as $req) {
            $targetItemId = null;

            if ($req->requested_asset_id) {
                // If they requested a specific asset, look up its mapped item
                $asset = DB::table('assets')->where('id', $req->requested_asset_id)->first();
                if ($asset) {
                    $targetItemId = $asset->asset_item_id;
                }
            }

            if (!$targetItemId) {
                // Fallback: Find the first item in this category or create a generic one
                $firstItem = DB::table('asset_items')
                    ->where('company_id', $req->company_id)
                    ->where('asset_category_id', $req->asset_category_id)
                    ->first();

                if ($firstItem) {
                    $targetItemId = $firstItem->id;
                } else {
                    // Create a generic item for this category
                    $category = DB::table('asset_categories')->where('id', $req->asset_category_id)->first();
                    $itemName = $category ? ($category->name . ' Item') : 'Generic Asset';
                    $targetItemId = DB::table('asset_items')->insertGetId([
                        'tenant_id' => $req->tenant_id,
                        'company_id' => $req->company_id,
                        'asset_category_id' => $req->asset_category_id,
                        'name' => $itemName,
                        'description' => 'Generic item master fallback for historical requests.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('asset_requests')
              ->where('id', $req->id)
              ->update(['asset_item_id' => $targetItemId, 'quantity' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_requests', function (Blueprint $table) {
            $table->dropForeign(['asset_item_id']);
            $table->dropColumn(['asset_item_id', 'quantity']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['asset_item_id']);
            $table->dropColumn(['asset_item_id']);
        });

        Schema::dropIfExists('asset_items');
    }
};
