<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\HelpdeskCategory;
use App\Domains\HRMS\Models\HelpdeskKbArticle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HelpdeskKbRepository implements HelpdeskKbRepositoryInterface
{
    public function getArticles(array $filters, bool $canManage): LengthAwarePaginator
    {
        $tenantId = tenant_id();
        $query = HelpdeskKbArticle::where('tenant_id', $tenantId)
            ->with(['category']);

        if (!$canManage) {
            $query->where('is_published', true);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        return $query->orderBy('view_count', 'desc')->paginate(12);
    }

    public function getActiveCategories(): Collection
    {
        $tenantId = tenant_id();
        return HelpdeskCategory::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getAllCategoriesWithCounts(): Collection
    {
        $tenantId = tenant_id();
        return HelpdeskCategory::where('tenant_id', $tenantId)
            ->with(['defaultAgent'])
            ->withCount('tickets')
            ->orderBy('name')
            ->get();
    }

    public function findArticleBySlug(string $slug): HelpdeskKbArticle
    {
        $tenantId = tenant_id();
        $article = HelpdeskKbArticle::where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->firstOrFail();

        $article->increment('view_count');

        return $article;
    }

    public function createArticle(array $data): HelpdeskKbArticle
    {
        $tenantId = tenant_id();
        $slug = Str::slug($data['title']) . '-' . time();

        return DB::transaction(function () use ($tenantId, $data, $slug) {
            return HelpdeskKbArticle::create([
                'tenant_id'    => $tenantId,
                'category_id'  => $data['category_id'] ?? null,
                'title'        => $data['title'],
                'slug'         => $slug,
                'content'      => $data['content'],
                'is_published' => $data['is_published'] ?? true,
            ]);
        });
    }

    public function updateArticle(int $id, array $data): bool
    {
        $tenantId = tenant_id();
        $article = HelpdeskKbArticle::where('tenant_id', $tenantId)->findOrFail($id);

        return DB::transaction(function () use ($article, $data) {
            return $article->update([
                'category_id'  => $data['category_id'] ?? null,
                'title'        => $data['title'],
                'content'      => $data['content'],
                'is_published' => $data['is_published'] ?? true,
            ]);
        });
    }

    public function deleteArticle(int $id): bool
    {
        $tenantId = tenant_id();
        $article = HelpdeskKbArticle::where('tenant_id', $tenantId)->findOrFail($id);

        return (bool) $article->delete();
    }

    public function suggestArticles(string $query): Collection
    {
        $tenantId = tenant_id();
        if (empty($query) || strlen($query) < 3) {
            return new Collection();
        }

        return HelpdeskKbArticle::where('tenant_id', $tenantId)
            ->where('is_published', true)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get(['id', 'title', 'slug', 'content']);
    }

    public function createCategory(array $data): HelpdeskCategory
    {
        $tenantId = tenant_id();
        $code = Str::slug($data['name'], '_');

        return DB::transaction(function () use ($tenantId, $data, $code) {
            return HelpdeskCategory::create([
                'tenant_id'         => $tenantId,
                'name'              => $data['name'],
                'code'              => $code,
                'description'       => $data['description'] ?? null,
                'default_agent_id'  => $data['default_agent_id'] ?? null,
                'default_sla_hours' => $data['default_sla_hours'] ?? 24,
                'is_confidential'   => !empty($data['is_confidential']),
                'is_active'         => true,
            ]);
        });
    }

    public function updateCategory(int $id, array $data): bool
    {
        $tenantId = tenant_id();
        $category = HelpdeskCategory::where('tenant_id', $tenantId)->findOrFail($id);

        return DB::transaction(function () use ($category, $data) {
            return $category->update([
                'name'              => $data['name'],
                'description'       => $data['description'] ?? null,
                'default_agent_id'  => $data['default_agent_id'] ?? null,
                'default_sla_hours' => $data['default_sla_hours'] ?? 24,
                'is_confidential'   => !empty($data['is_confidential']),
                'is_active'         => isset($data['is_active']) ? (bool) $data['is_active'] : $category->is_active,
            ]);
        });
    }

    public function deleteCategory(int $id): bool
    {
        $tenantId = tenant_id();
        $category = HelpdeskCategory::where('tenant_id', $tenantId)->findOrFail($id);

        return (bool) $category->delete();
    }
}
