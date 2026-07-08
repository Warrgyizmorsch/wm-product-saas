<?php

namespace App\Domains\Access\Controllers;

use App\Domains\Access\Services\RoleService;
use App\Http\Controllers\Controller;
use App\Models\Access\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roles,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('modules.access.roles.index', [
            'roles' => $this->roles->visibleTo(tenant_id()),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('modules.access.roles.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('roles', 'slug')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
            'level' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $role = $this->roles->create(tenant_id(), $validated);

        return redirect()
            ->route('access.roles.show', $role)
            ->with('success', 'Role created successfully.');
    }

    public function show(Role $role): View
    {
        $this->authorize('view', $role);

        return view('modules.access.roles.show', [
            'role' => $role,
            'matrix' => $this->roles->matrixFor($role),
            'canEditPermissions' => Gate::allows('managePermissions', $role),
        ]);
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('managePermissions', $role);

        $validated = $request->validate([
            'grants' => ['nullable', 'array'],
        ]);

        $this->roles->syncPermissions($role, $validated['grants'] ?? []);

        return redirect()
            ->route('access.roles.show', $role)
            ->with('success', 'Permissions updated successfully.');
    }
}
