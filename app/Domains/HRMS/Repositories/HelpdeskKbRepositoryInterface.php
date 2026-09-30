<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\HelpdeskCategory;
use App\Domains\HRMS\Models\HelpdeskKbArticle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface HelpdeskKbRepositoryInterface
{
    public function getArticles(array $filters, bool $canManage): LengthAwarePaginator;

    public function getActiveCategories(): Collection;

    public function getAllCategoriesWithCounts(): Collection;

    public function findArticleBySlug(string $slug): HelpdeskKbArticle;

    public function createArticle(array $data): HelpdeskKbArticle;

    public function updateArticle(int $id, array $data): bool;

    public function deleteArticle(int $id): bool;

    public function suggestArticles(string $query): Collection;

    public function createCategory(array $data): HelpdeskCategory;

    public function updateCategory(int $id, array $data): bool;

    public function deleteCategory(int $id): bool;
}
