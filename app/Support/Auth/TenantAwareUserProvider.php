<?php

namespace App\Support\Auth;

use App\Core\Tenant\TenantResolver;
use App\Services\Access\AccessService;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;

/**
 * The User model is tenant-scoped (BelongsToTenant), so the default Eloquent
 * provider would silently fail to find/re-authenticate a user once the
 * resolved tenant differs from their own tenant_id — which blocks a
 * platform-wide (super_admin) account from ever being looked up under a
 * tenant it isn't natively assigned to. This provider looks the user up
 * without that scope, then re-applies tenant access as an explicit check:
 * same tenant always passes, otherwise the user must hold a platform-scope
 * permission (see AccessService::allows / RolePermission::SCOPE_PLATFORM).
 */
class TenantAwareUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope('tenant');
    }

    public function retrieveById($identifier)
    {
        return $this->tenantAccessible(parent::retrieveById($identifier));
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
        return $this->tenantAccessible(parent::retrieveByToken($identifier, $token));
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        return $this->tenantAccessible(parent::retrieveByCredentials($credentials));
    }

    private function tenantAccessible(?UserContract $user): ?UserContract
    {
        if ($user === null) {
            return null;
        }

        // TenantContext is populated by the app's `tenant` middleware, but
        // that middleware is not guaranteed to run before Laravel resolves
        // the session's authenticated user — the framework's own auth
        // middleware is priority-ordered ahead of any unlisted custom
        // middleware regardless of route-group nesting, so TenantContext
        // can still be empty at this point. Resolve directly from the
        // current request instead of depending on that ordering.
        $currentTenantId = app(TenantResolver::class)->resolve(request())?->id;

        if ($user->tenant_id !== null
            && $currentTenantId !== null
            && (int) $user->tenant_id === (int) $currentTenantId) {
            return $user;
        }

        return app(AccessService::class)->allows($user, 'platform.tenants.manage')
            ? $user
            : null;
    }
}
