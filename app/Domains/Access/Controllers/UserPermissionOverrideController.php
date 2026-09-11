<?php

namespace App\Domains\Access\Controllers;

use App\Domains\Access\Services\PermissionOverrideService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserPermissionOverrideController extends Controller
{
    public function __construct(
        private readonly PermissionOverrideService $overrides,
    ) {
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('modules.access.users.overrides', [
            'targetUser' => $user,
            'matrix' => $this->overrides->matrixFor($user),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'overrides' => ['nullable', 'array'],
            'overrides.*.state' => ['required', 'in:inherit,allow,deny'],
            'overrides.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->overrides->syncOverrides(auth()->user(), $user, $validated['overrides'] ?? []);

        return redirect()
            ->route('access.users.overrides.edit', $user)
            ->with('success', 'Permission overrides updated successfully.');
    }
}
