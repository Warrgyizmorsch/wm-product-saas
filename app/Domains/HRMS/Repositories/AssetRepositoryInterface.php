<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\AssetItem;
use App\Domains\HRMS\Models\AssetRequest;
use Illuminate\Http\Request;

interface AssetRepositoryInterface
{
    public function getIndexData(array $inputs): array;

    public function storeAsset(array $validated): Asset;

    public function createAssetItemWithUnits(array $validated): AssetItem;

    public function updateAsset(Asset $asset, array $validated): bool;

    public function updateAssetItem(AssetItem $assetItem, array $validated): bool;

    public function deleteAsset(Asset $asset): bool;

    public function storeCategory(array $validated): AssetCategory;

    public function updateCategory(AssetCategory $category, array $validated): bool;

    public function deleteCategory(AssetCategory $category): bool;

    public function allocateAsset(Asset $asset, array $validated): bool;

    public function returnAsset(Asset $asset, array $validated): bool;

    public function allocateItem(AssetItem $assetItem, array $validated): bool;

    public function returnItem(AssetItem $assetItem, array $validated): bool;

    public function storeRequest(array $validated): AssetRequest;

    public function updateRequest(AssetRequest $assetRequest, array $validated): bool;

    public function deleteRequest(AssetRequest $assetRequest): bool;
}
