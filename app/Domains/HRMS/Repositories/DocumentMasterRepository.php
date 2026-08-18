<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\DocumentCategory;
use App\Domains\HRMS\Models\DocumentMaster;

class DocumentMasterRepository implements DocumentMasterRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        // 1. Categories list (dropdowns and modals)
        $allCategories = DocumentCategory::query()->orderBy('name', 'asc')->get();

        // 2. Filtered Categories query (for Category List Tab)
        $categoriesQuery = DocumentCategory::query();

        if (!empty($inputs['category_search'])) {
            $search = $inputs['category_search'];
            $categoriesQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (!empty($inputs['category_company_id'])) {
            $categoriesQuery->where('company_id', $inputs['category_company_id']);
        }

        $categorySort = $inputs['category_sort'] ?? 'name_asc';
        if ($categorySort === 'name_desc') {
            $categoriesQuery->orderBy('name', 'desc');
        } elseif ($categorySort === 'newest') {
            $categoriesQuery->orderBy('created_at', 'desc');
        } else {
            $categoriesQuery->orderBy('name', 'asc');
        }

        $categories = $categoriesQuery->paginate(10, ['*'], 'category_page')->withQueryString();

        // 3. Filtered Documents query (for Document Master Tab)
        $documentsQuery = DocumentMaster::query()->with('category');

        if (!empty($inputs['doc_search'])) {
            $search = $inputs['doc_search'];
            $documentsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (!empty($inputs['doc_category_id'])) {
            $documentsQuery->where('document_category_id', $inputs['doc_category_id']);
        }

        if (!empty($inputs['doc_status'])) {
            $documentsQuery->where('status', $inputs['doc_status']);
        }

        $docSort = $inputs['doc_sort'] ?? 'name_asc';
        if ($docSort === 'name_desc') {
            $documentsQuery->orderBy('name', 'desc');
        } elseif ($docSort === 'code_asc') {
            $documentsQuery->orderBy('code', 'asc');
        } elseif ($docSort === 'code_desc') {
            $documentsQuery->orderBy('code', 'desc');
        } elseif ($docSort === 'newest') {
            $documentsQuery->orderBy('created_at', 'desc');
        } else {
            $documentsQuery->orderBy('name', 'asc');
        }

        $documents = $documentsQuery->paginate(10, ['*'], 'doc_page')->withQueryString();

        $companies = \App\Domains\HRMS\Models\Company::query()->orderBy('company_name')->get();

        return [
            'categories' => $categories,
            'allCategories' => $allCategories,
            'documents' => $documents,
            'companies' => $companies,
            'filters' => $inputs,
        ];
    }

    public function storeCategory(array $validated): DocumentCategory
    {
        return DocumentCategory::create($validated);
    }

    public function updateCategory(DocumentCategory $category, array $validated): bool
    {
        return $category->update($validated);
    }

    public function deleteCategory(DocumentCategory $category): bool
    {
        return $category->delete();
    }

    public function storeDocument(array $validated): DocumentMaster
    {
        return DocumentMaster::create($validated);
    }

    public function updateDocument(DocumentMaster $document, array $validated): bool
    {
        return $document->update($validated);
    }

    public function deleteDocument(DocumentMaster $document): bool
    {
        return $document->delete();
    }
}
