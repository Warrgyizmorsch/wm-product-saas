<?php

namespace App\Core\Tenant;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Finds which tenant a sign-in belongs to when the URL can't tell us — on a
 * shared host such as localhost or a central domain, TenantResolver would
 * otherwise fall back to a default tenant for everyone.
 *
 * The same email may exist in more than one tenant, so the password decides:
 * the first account (by id) whose password matches, in an accessible tenant, wins.
 */
class LoginTenantLocator
{
    public function slugFor(string $email, string $password): ?string
    {
        $candidates = User::withoutGlobalScopes()
            ->where('email', $email)
            ->whereNotNull('tenant_id')
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'password']);

        foreach ($candidates as $user) {
            if (! Hash::check($password, $user->password)) {
                continue;
            }

            $slug = Tenant::query()
                ->whereKey($user->tenant_id)
                ->whereIn('status', Tenant::accessibleStatuses())
                ->value('slug');

            if ($slug !== null) {
                return $slug;
            }
        }

        return null;
    }
}
