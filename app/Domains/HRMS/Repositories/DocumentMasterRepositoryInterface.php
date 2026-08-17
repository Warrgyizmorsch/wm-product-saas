<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\DocumentCategory;
use App\Domains\HRMS\Models\DocumentMaster;

interface DocumentMasterRepositoryInterface
{
    public function getIndexData(array $inputs): array;

    public function storeCategory(array $validated): DocumentCategory;

    public function updateCategory(DocumentCategory $category, array $validated): bool;

    public function deleteCategory(DocumentCategory $category): bool;

    public function storeDocument(array $validated): DocumentMaster;

    public function updateDocument(DocumentMaster $document, array $validated): bool;

    public function deleteDocument(DocumentMaster $document): bool;
}
