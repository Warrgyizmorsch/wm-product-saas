<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\HelpdeskCategory;
use App\Domains\HRMS\Models\HelpdeskKbArticle;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HelpdeskKbController extends Controller
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function index(Request $request): View
    {
        $tenantId = tenant_id();
        $user = auth()->user();

        $canManage = $user && ($this->access->allows($user, 'hrms.helpdesk.manage', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId]));

        $search = $request->input('search');
        $categoryId = $request->input('category_id');

        $query = HelpdeskKbArticle::where('tenant_id', $tenantId)
            ->with(['category']);

        if (!$canManage) {
            $query->where('is_published', true);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $articles = $query->orderBy('view_count', 'desc')->paginate(12);
        $categories = HelpdeskCategory::where('tenant_id', $tenantId)->where('is_active', true)->get();

        return view('modules.hrms.helpdesk.kb.index', compact('articles', 'categories', 'canManage', 'search', 'categoryId'));
    }

    public function show(string $slug): View
    {
        $tenantId = tenant_id();
        $user = auth()->user();
        $canManage = $user && ($this->access->allows($user, 'hrms.helpdesk.manage', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId]));

        $article = HelpdeskKbArticle::where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->firstOrFail();

        $article->increment('view_count');
        $categories = HelpdeskCategory::where('tenant_id', $tenantId)->where('is_active', true)->get();

        return view('modules.hrms.helpdesk.kb.show', compact('article', 'canManage', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = tenant_id();
        $request->validate([
            'title'        => 'required|string|max:255',
            'category_id'  => 'nullable|exists:helpdesk_categories,id',
            'content'      => 'required|string',
            'is_published' => 'nullable|boolean',
        ]);

        $slug = Str::slug($request->input('title')) . '-' . time();

        HelpdeskKbArticle::create([
            'tenant_id'    => $tenantId,
            'category_id'  => $request->input('category_id'),
            'title'        => $request->input('title'),
            'slug'         => $slug,
            'content'      => $request->input('content'),
            'is_published' => $request->boolean('is_published', true),
        ]);

        return redirect()->route('hrms.helpdesk.kb.index')
            ->with('success', 'Knowledge Base article created successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id();
        $user = auth()->user();
        $canManage = $user && ($this->access->allows($user, 'hrms.helpdesk.manage', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId]));

        if (!$canManage) {
            abort(403, 'Unauthorized to update knowledge base articles.');
        }

        $article = HelpdeskKbArticle::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'title'        => 'required|string|max:255',
            'category_id'  => 'nullable|exists:helpdesk_categories,id',
            'content'      => 'required|string',
            'is_published' => 'nullable|boolean',
        ]);

        $article->update([
            'category_id'  => $request->input('category_id'),
            'title'        => $request->input('title'),
            'content'      => $request->input('content'),
            'is_published' => $request->boolean('is_published', true),
        ]);

        return redirect()->route('hrms.helpdesk.kb.index')
            ->with('success', 'Knowledge Base article updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $tenantId = tenant_id();
        $user = auth()->user();
        $canManage = $user && ($this->access->allows($user, 'hrms.helpdesk.manage', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId]));

        if (!$canManage) {
            abort(403, 'Unauthorized to delete knowledge base articles.');
        }

        $article = HelpdeskKbArticle::where('tenant_id', $tenantId)->findOrFail($id);
        $article->delete();

        return redirect()->route('hrms.helpdesk.kb.index')
            ->with('success', 'Knowledge Base article deleted successfully.');
    }

    /**
     * AJAX endpoint for live ticket deflection suggestions
     */
    public function suggest(Request $request): JsonResponse
    {
        $tenantId = tenant_id();
        $query = $request->input('q');

        if (empty($query) || strlen($query) < 3) {
            return response()->json([]);
        }

        $articles = HelpdeskKbArticle::where('tenant_id', $tenantId)
            ->where('is_published', true)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get(['id', 'title', 'slug', 'content']);

        return response()->json($articles);
    }
}
