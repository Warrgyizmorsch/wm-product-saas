<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Repositories\HelpdeskKbRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpdeskCategoryController extends Controller
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

        if (!$canManage) {
            abort(403, 'Unauthorized to manage helpdesk categories.');
        }

        $categories = $this->kbRepository->getAllCategoriesWithCounts();
        $agents = Employee::where('tenant_id', $tenantId)->orderBy('full_name')->get();

        return view('modules.hrms.helpdesk.categories', compact('categories', 'agents', 'canManage'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'default_agent_id'  => 'nullable|exists:employees,id',
            'default_sla_hours' => 'required|integer|min:1',
            'is_confidential'   => 'nullable|boolean',
        ]);

        $this->kbRepository->createCategory([
            'name'              => $request->input('name'),
            'description'       => $request->input('description'),
            'default_agent_id'  => $request->input('default_agent_id'),
            'default_sla_hours' => $request->input('default_sla_hours', 24),
            'is_confidential'   => $request->boolean('is_confidential'),
        ]);

        return redirect()->route('hrms.helpdesk.categories.index')
            ->with('success', 'Helpdesk category created successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'default_agent_id'  => 'nullable|exists:employees,id',
            'default_sla_hours' => 'required|integer|min:1',
            'is_confidential'   => 'nullable|boolean',
            'is_active'         => 'nullable|boolean',
        ]);

        $this->kbRepository->updateCategory($id, [
            'name'              => $request->input('name'),
            'description'       => $request->input('description'),
            'default_agent_id'  => $request->input('default_agent_id'),
            'default_sla_hours' => $request->input('default_sla_hours', 24),
            'is_confidential'   => $request->boolean('is_confidential'),
            'is_active'         => $request->has('is_active') ? $request->boolean('is_active') : null,
        ]);

        return redirect()->route('hrms.helpdesk.categories.index')
            ->with('success', 'Helpdesk category updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->kbRepository->deleteCategory($id);

        return redirect()->route('hrms.helpdesk.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
