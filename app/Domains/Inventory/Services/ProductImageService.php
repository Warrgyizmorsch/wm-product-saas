<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageService
{
    /**
     * Upload and save Main (Thumbnail) image for a product
     */
    public function saveMainImage(Product $product, UploadedFile $file, int $tenantId): ProductImage
    {
        $dir = "uploads/tenants/{$tenantId}/products/{$product->id}";
        $filename = 'main_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($dir, $filename, 'public');

        // Unset previous primary image
        ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);

        $productImage = ProductImage::create([
            'tenant_id' => $tenantId,
            'company_id' => $product->company_id,
            'branch_id' => $product->branch_id,
            'product_id' => $product->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'image_type' => 'primary',
            'is_primary' => true,
            'sort_order' => 0,
            'alt_text' => $product->name,
        ]);

        // Keep legacy image_path column synchronized
        $product->update(['image_path' => $path]);

        return $productImage;
    }

    /**
     * Upload and append multiple Detail (Gallery) images
     */
    public function saveDetailImages(Product $product, array $files, int $tenantId): array
    {
        $saved = [];
        $dir = "uploads/tenants/{$tenantId}/products/{$product->id}";
        $startOrder = (int)ProductImage::where('product_id', $product->id)->max('sort_order') + 1;

        foreach ($files as $file) {
            if (!($file instanceof UploadedFile) || !$file->isValid()) {
                continue;
            }

            $filename = 'detail_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($dir, $filename, 'public');

            $saved[] = ProductImage::create([
                'tenant_id' => $tenantId,
                'company_id' => $product->company_id,
                'branch_id' => $product->branch_id,
                'product_id' => $product->id,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'image_type' => 'detail',
                'is_primary' => false,
                'sort_order' => $startOrder++,
                'alt_text' => $product->name . ' Detail',
            ]);
        }

        return $saved;
    }

    /**
     * Upload and save Variant-specific image (backward compatibility)
     */
    public function saveVariantImage(Product $variant, UploadedFile $file, int $tenantId): ProductImage
    {
        return $this->saveMainImage($variant, $file, $tenantId);
    }

    /**
     * Save complete media package for a variant (Main + Detail gallery images)
     */
    public function saveVariantMedia(Product $variant, array $vData, int $tenantId): void
    {
        // 1. Delete requested images
        if (!empty($vData['deleted_image_ids']) && is_array($vData['deleted_image_ids'])) {
            foreach ($vData['deleted_image_ids'] as $delId) {
                $this->deleteImage((int)$delId, $variant);
            }
        }

        // 2. Main / Primary image
        $mainFile = $vData['main_image'] ?? $vData['image'] ?? null;
        if ($mainFile instanceof UploadedFile && $mainFile->isValid()) {
            $this->saveMainImage($variant, $mainFile, $tenantId);
        } elseif (!empty($vData['primary_image_id'])) {
            $this->setPrimary((int)$vData['primary_image_id'], $variant);
        }

        // 3. Detail / Gallery images
        if (!empty($vData['detail_images']) && is_array($vData['detail_images'])) {
            $this->saveDetailImages($variant, $vData['detail_images'], $tenantId);
        }
    }

    /**
     * Set a specific image as Primary Thumbnail
     */
    public function setPrimary(int $imageId, Product $product): bool
    {
        $image = ProductImage::where('id', $imageId)
            ->where('product_id', $product->id)
            ->first();

        if (!$image) {
            return false;
        }

        ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);
        $image->update([
            'is_primary' => true,
            'image_type' => 'primary',
        ]);

        $product->update(['image_path' => $image->file_path]);

        return true;
    }

    /**
     * Delete an image from storage and DB
     */
    public function deleteImage(int $imageId, Product $product): bool
    {
        $image = ProductImage::where('id', $imageId)
            ->where('product_id', $product->id)
            ->first();

        if (!$image) {
            return false;
        }

        $wasPrimary = $image->is_primary;

        Storage::disk('public')->delete($image->file_path);
        if ($image->thumbnail_path) {
            Storage::disk('public')->delete($image->thumbnail_path);
        }
        $image->delete();

        // If primary was deleted, elect next available image as primary
        if ($wasPrimary) {
            $next = ProductImage::where('product_id', $product->id)->orderBy('sort_order', 'asc')->first();
            if ($next) {
                $next->update(['is_primary' => true, 'image_type' => 'primary']);
                $product->update(['image_path' => $next->file_path]);
            } else {
                $product->update(['image_path' => null]);
            }
        }

        return true;
    }
}
