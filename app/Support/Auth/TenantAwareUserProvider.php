<?php

namespace App\Support\Auth;

use App\Core\Tenant\TenantResolver;
use App\Services\Access\AccessService;
use Closure;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Support\Arrayable;

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

    /**
     * The same email can belong to accounts in several tenants. Prefer the one
     * in the tenant this request resolves to (LoginController points the
     * session there on shared hosts), then tenant-less platform accounts, and
     * only then any other — rather than whichever account has the lowest id,
     * which would reject a valid sign-in to the second tenant.
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        $credentials = array_filter(
            $credentials,
            fn ($key) => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if ($credentials === []) {
            return null;
        }

        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } elseif ($value instanceof Closure) {
                $value($query);
            } else {
                $query->where($key, $value);
            }
        }

        $candidates = $query
            ->orderByRaw('CASE WHEN tenant_id = ? THEN 0 WHEN tenant_id IS NULL THEN 1 ELSE 2 END', [$this->currentTenantId()])
            ->orderBy('id')
            ->get();

        foreach ($candidates as $user) {
            if ($this->tenantAccessible($user) !== null) {
                return $user;
            }
        }

        return null;
    }

    private function tenantAccessible(?UserContract $user): ?UserContract
    {
        if ($user === null) {
            return null;
        }

        $currentTenantId = $this->currentTenantId();

        if ($user->tenant_id !== null
            && $currentTenantId !== null
            && (int) $user->tenant_id === (int) $currentTenantId) {
            return $user;
        }

        return app(AccessService::class)->allows($user, 'platform.tenants.manage')
            ? $user
            : null;
    }

    private function currentTenantId(): ?int
    {
        // TenantContext is populated by the app's `tenant` middleware, but
        // that middleware is not guaranteed to run before Laravel resolves
        // the session's authenticated user — the framework's own auth
        // middleware is priority-ordered ahead of any unlisted custom
        // middleware regardless of route-group nesting, so TenantContext
        // can still be empty at this point. Resolve directly from the
        // current request instead of depending on that ordering.
        return app(TenantResolver::class)->resolve(request())?->id;
    }
}
