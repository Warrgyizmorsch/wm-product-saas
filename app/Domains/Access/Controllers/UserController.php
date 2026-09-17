<?php

namespace App\Domains\Access\Controllers;

use App\Domains\Access\Services\UserService;
use App\Domains\Platform\Exceptions\UsageLimitExceededException;
use App\Domains\Platform\Services\UsageLimitService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly UsageLimitService $usageLimits,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        return view('modules.access.users.index', [
            'users' => $this->users->all(),
            'roles' => $this->users->assignableRoles(auth()->user(), tenant_id()),
            'userLimit' => tenant() ? $this->usageLimits->maxUsers(tenant()) : null,
            'userLimitRemaining' => tenant() ? $this->usageLimits->remainingUserSlots(tenant()) : null,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('modules.access.users.create', [
            'user' => new User(),
            'roles' => $this->users->assignableRoles(auth()->user(), tenant_id()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        try {
            $this->users->create(auth()->user(), $this->validated($request));
        } catch (UsageLimitExceededException $e) {
            return redirect()
                ->route('access.users.create')
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('access.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('modules.access.users.edit', [
            'user' => $user,
            'roles' => $this->users->assignableRoles(auth()->user(), tenant_id()),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->users->update(auth()->user(), $user, $this->validated($request, $user));

        return redirect()
            ->route('access.users.index')
            ->with('success', 'User updated successfully.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        // Accept the new multi-select `role_ids[]` field, falling back to the
        // legacy single `role_id` field so existing callers/integrations that
        // only ever sent one role keep working unchanged.
        if (! $request->has('role_ids') && $request->filled('role_id')) {
            $request->merge(['role_ids' => [$request->input('role_id')]]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);
    }
}
