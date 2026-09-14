<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\HelpdeskCategory;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HelpdeskCategoryController extends Controller
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

        if (!$canManage) {
            abort(403, 'Unauthorized to manage helpdesk categories.');
        }

        $categories = HelpdeskCategory::where('tenant_id', $tenantId)
            ->with(['defaultAgent'])
            ->withCount('tickets')
            ->orderBy('name')
            ->get();

        $agents = Employee::where('tenant_id', $tenantId)->orderBy('full_name')->get();

        return view('modules.hrms.helpdesk.categories', compact('categories', 'agents', 'canManage'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = tenant_id();
        $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'default_agent_id'  => 'nullable|exists:employees,id',
            'default_sla_hours' => 'required|integer|min:1',
            'is_confidential'   => 'nullable|boolean',
        ]);

        $code = Str::slug($request->input('name'), '_');

        HelpdeskCategory::create([
            'tenant_id'         => $tenantId,
            'name'              => $request->input('name'),
            'code'              => $code,
            'description'       => $request->input('description'),
            'default_agent_id'  => $request->input('default_agent_id'),
            'default_sla_hours' => $request->input('default_sla_hours', 24),
            'is_confidential'   => $request->boolean('is_confidential'),
            'is_active'         => true,
        ]);

        return redirect()->route('hrms.helpdesk.categories.index')
            ->with('success', 'Helpdesk category created successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id();
        $category = HelpdeskCategory::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'default_agent_id'  => 'nullable|exists:employees,id',
            'default_sla_hours' => 'required|integer|min:1',
            'is_confidential'   => 'nullable|boolean',
            'is_active'         => 'nullable|boolean',
        ]);

        $category->update([
            'name'              => $request->input('name'),
            'description'       => $request->input('description'),
            'default_agent_id'  => $request->input('default_agent_id'),
            'default_sla_hours' => $request->input('default_sla_hours', 24),
            'is_confidential'   => $request->boolean('is_confidential'),
            'is_active'         => $request->has('is_active') ? $request->boolean('is_active') : $category->is_active,
        ]);

        return redirect()->route('hrms.helpdesk.categories.index')
            ->with('success', 'Helpdesk category updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $tenantId = tenant_id();
        $category = HelpdeskCategory::where('tenant_id', $tenantId)->findOrFail($id);
        $category->delete();

        return redirect()->route('hrms.helpdesk.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
