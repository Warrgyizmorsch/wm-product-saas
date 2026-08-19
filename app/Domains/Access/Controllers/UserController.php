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
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);
    }
}
