<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Repositories\HelpdeskKbRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpdeskKbController extends Controller
{
    public function __construct(
        private readonly HelpdeskKbRepositoryInterface $kbRepository,
        private readonly AccessService $access
    ) {
    }

    public function index(Request $request): View
    {
        $tenantId = tenant_id();
        $user = auth()->user();

        $canManage = $user && ($this->access->allows($user, 'hrms.helpdesk.manage', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId]));

        $search = $request->input('search');
        $categoryId = $request->input('category_id');

        $articles = $this->kbRepository->getArticles([
            'search' => $search,
            'category_id' => $categoryId,
        ], $canManage);

        $categories = $this->kbRepository->getActiveCategories();

        return view('modules.hrms.helpdesk.kb.index', compact('articles', 'categories', 'canManage', 'search', 'categoryId'));
    }

    public function show(string $slug): View
    {
        $tenantId = tenant_id();
        $user = auth()->user();
        $canManage = $user && ($this->access->allows($user, 'hrms.helpdesk.manage', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId]));

        $article = $this->kbRepository->findArticleBySlug($slug);
        $categories = $this->kbRepository->getActiveCategories();

        return view('modules.hrms.helpdesk.kb.show', compact('article', 'canManage', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'category_id'  => 'nullable|exists:helpdesk_categories,id',
            'content'      => 'required|string',
            'is_published' => 'nullable|boolean',
        ]);

        $this->kbRepository->createArticle([
            'category_id'  => $request->input('category_id'),
            'title'        => $request->input('title'),
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

        $request->validate([
            'title'        => 'required|string|max:255',
            'category_id'  => 'nullable|exists:helpdesk_categories,id',
            'content'      => 'required|string',
            'is_published' => 'nullable|boolean',
        ]);

        $this->kbRepository->updateArticle($id, [
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

        $this->kbRepository->deleteArticle($id);

        return redirect()->route('hrms.helpdesk.kb.index')
            ->with('success', 'Knowledge Base article deleted successfully.');
    }

    /**
     * AJAX endpoint for live ticket deflection suggestions
     */
    public function suggest(Request $request): JsonResponse
    {
        $query = (string) $request->input('q');
        $articles = $this->kbRepository->suggestArticles($query);

        return response()->json($articles);
    }
}
