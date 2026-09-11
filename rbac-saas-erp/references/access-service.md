# AccessService — Reference Implementation

Read this before writing or modifying `app/Domains/RBAC/Services/AccessService.php`,
`CheckPermission` middleware, or any Policy method.

## AccessService

```php
namespace App\Domains\RBAC\Services;

use App\Models\User;
use App\Domains\RBAC\Models\PermissionAuditLog;

class AccessService
{
    public function allows(User $user, string $permission, array $context = []): bool
    {
        // 1. Hard tenant check first — fail closed regardless of permission/role state.
        if (isset($context['tenant_id']) && $context['tenant_id'] !== $user->tenant_id) {
            return false;
        }

        // 2. Explicit deny override always wins, even over a role grant.
        $override = $this->findOverride($user, $permission);
        if ($override && !$override->allowed) {
            return false;
        }
        if ($override && $override->allowed) {
            return $this->matchesScope($override->scope, $user, $context);
        }

        // 3. Controlled platform bypass — audited, not a blanket flag.
        if ($user->is_dev && ($context['allow_platform_override'] ?? false)) {
            PermissionAuditLog::record($user, 'platform_bypass', $permission, $context);
            return true;
        }

        // 4. Normal role-based grant.
        $grant = $this->resolveRoleGrant($user, $permission); // cached lookup
        if (!$grant) {
            return false;
        }

        return $this->matchesScope($grant->scope, $user, $context);
    }

    protected function matchesScope(string $scope, User $user, array $context): bool
    {
        return match ($scope) {
            'own'        => ($context['owner_id'] ?? null) === $user->id,
            'team'       => $this->inSameTeam($user, $context),
            'department' => ($context['department_id'] ?? null) === $user->department_id,
            'branch'     => ($context['branch_id'] ?? null) === $user->branch_id,
            'tenant'     => true, // tenant match already verified in step 1
            'platform'   => $user->hasPlatformRole(),
            default      => false,
        };
    }
}
```

Key ordering matters: **explicit deny > platform bypass > role grant.** Don't reorder
this — an override deny must always be able to lock someone out even if their role would
otherwise allow it (e.g. someone under investigation, or an offboarding employee whose
role removal hasn't propagated yet).

## CheckPermission middleware

```php
namespace App\Http\Middleware;

class CheckPermission
{
    public function handle($request, Closure $next, string $permission)
    {
        $user = auth()->user();

        if (!$user || $user->tenant_id !== tenant()->id) {
            abort(403, 'Tenant mismatch');
        }

        if (!app(AccessService::class)->allows($user, $permission, $this->contextFrom($request))) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
```

Route usage:
```php
Route::middleware(['auth', 'permission:production.routing.approve'])
    ->post('/production/routings/{routing}/approve', [RoutingController::class, 'approve']);
```

## Caching

Role → permission resolution is checked on nearly every request. Cache per
user+tenant, not globally:

```php
$key = "user:{$user->id}:tenant:{$user->tenant_id}:permissions";

Cache::remember($key, now()->addHours(6), fn () =>
    $user->roles()->with('permissions')->get()
        ->pluck('permissions')->flatten()
);
```

**Bust this cache** on: role assignment/removal, role_permissions change, override
create/update/delete. Put the `Cache::forget()` call in the same service method that
performs the mutation — don't rely on remembering to do it separately in a controller.

## Audit logging

Every write to `user_roles`, `user_permission_overrides`, or a `platform_bypass` grant
must produce a `permission_audit_log` row. Put this inside the service layer method
that performs the mutation, not in the controller — controllers should stay thin and
this must not be skippable by calling the service a different way.
